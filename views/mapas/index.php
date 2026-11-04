<?php
$pageTitle = "Visor Cartográfico SIG - Cultivos y Arboleda - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();

// Conmutar explotación si se recibe por parámetro
if (isset($_GET['explotacion_id'])) {
    $conmutarId = (int)$_GET['explotacion_id'];
    Auth::switchTenant($conmutarId);
}

$tenantId = Auth::tenantId();
$isSuperAdmin = Auth::isSuperAdmin();

// Obtener lista de explotaciones para selector rápido
$userTenants = array();
if ($isSuperAdmin) {
    $userTenants = $db->query("SELECT id_propietario, nombre_razon_social FROM propietarios WHERE activo = 1 ORDER BY nombre_razon_social ASC")->fetchAll();
} else if (Auth::check()) {
    $userTenants = Auth::getUserTenants(Auth::user()['id']);
}

// Obtener ÚNICAMENTE las fincas activas de la explotación seleccionada
$sqlFincas = "
    SELECT f.id_finca, f.id_propietario, f.nombre_finca, f.poligono, f.parcela, f.referencia_catastral, f.superficie_ha, 
           f.latitud_centro, f.longitud_centro, f.zoom_mapa, f.geojson_perimetro,
           pa.nombre_paraje, pa.termino_municipal, pa.provincia
    FROM fincas f
    LEFT JOIN parajes pa ON f.id_paraje = pa.id_paraje
    WHERE f.activo = 1
";
if ($tenantId) {
    $sqlFincas .= " AND f.id_propietario = " . (int)$tenantId;
}
$sqlFincas .= " ORDER BY f.nombre_finca ASC";
$fincasDisponibles = $db->query($sqlFincas)->fetchAll();

// Finca seleccionada por defecto (por URL o primera de la lista disponible de la explotación)
$fincaSeleccionadaId = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$idsFincasValidas = array_map(function($item) { return (int)$item['id_finca']; }, $fincasDisponibles);
if ($fincaSeleccionadaId <= 0 || !in_array($fincaSeleccionadaId, $idsFincasValidas)) {
    $fincaSeleccionadaId = !empty($fincasDisponibles) ? (int)$fincasDisponibles[0]['id_finca'] : 0;
}
?>

<!-- Hojas de Estilo para Leaflet y Geoman -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@2.14.2/dist/leaflet-geoman.css" />

<style>
    /* ==========================================================================
       ADAPTACIÓN DEL MAPA A CADA DISPOSITIVO (PC, TABLET, SMARTPHONE)
       ========================================================================== */
    .sig-wrapper {
        position: relative;
        width: 100%;
    }

    /* Altura en PC y pantallas grandes */
    .sig-container {
        position: relative;
        width: 100%;
        height: calc(100vh - 145px);
        min-height: 580px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(9, 30, 58, 0.15);
        border: 1px solid rgba(226, 232, 240, 0.9);
        background: #0f172a;
    }

    /* Modo Pantalla Completa nativo */
    .sig-container:fullscreen, .sig-container:-webkit-full-screen {
        width: 100vw !important;
        height: 100vh !important;
        border-radius: 0 !important;
        border: none !important;
    }

    /* Adaptación en Tablets (768px a 991px) */
    @media (max-width: 991.98px) {
        .sig-container {
            height: calc(100dvh - 120px);
            min-height: 520px;
            border-radius: 12px;
        }
    }

    /* Adaptación en Smartphones / Móviles (< 768px) */
    @media (max-width: 767.98px) {
        .sig-container {
            height: calc(100dvh - 85px);
            min-height: 460px;
            border-radius: 10px;
            margin: 0;
        }
        .sig-toolbar {
            top: 8px !important;
            left: 8px !important;
            right: 8px !important;
        }
        .sig-toolbar-inner {
            padding: 5px 8px !important;
            gap: 4px !important;
        }
        .sig-legend {
            bottom: 50px !important;
            left: 8px !important;
            max-width: 220px !important;
            padding: 8px 10px !important;
            font-size: 0.75rem !important;
        }
        .sig-status-badge {
            bottom: 10px !important;
            right: 8px !important;
            font-size: 0.72rem !important;
            padding: 3px 10px !important;
        }
    }

    #mapaAgricola {
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    /* Barra de Control Flotante Superior */
    .sig-toolbar {
        position: absolute;
        top: 14px;
        left: 55px; /* Deja espacio al control de zoom de Leaflet */
        right: 14px;
        z-index: 999;
        pointer-events: none;
    }

    .sig-toolbar-inner {
        pointer-events: auto;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-radius: 12px;
        padding: 7px 12px;
        box-shadow: 0 4px 20px rgba(9, 30, 58, 0.18);
        border: 1px solid rgba(226, 232, 240, 0.85);
    }

    /* Leyenda Flotante */
    .sig-legend {
        position: absolute;
        bottom: 24px;
        left: 16px;
        z-index: 998;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        padding: 12px 16px;
        max-width: 270px;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.22);
        border: 1px solid rgba(226, 232, 240, 0.8);
        font-size: 0.8rem;
        transition: all 0.2s ease;
    }

    /* Indicador de Estado Inferior */
    .sig-status-badge {
        position: absolute;
        bottom: 20px;
        right: 16px;
        z-index: 998;
        background: rgba(15, 23, 42, 0.9);
        color: #f8fafc;
        backdrop-filter: blur(8px);
        border-radius: 30px;
        padding: 5px 14px;
        font-size: 0.78rem;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    /* PANE CONTROL: El perímetro de la finca NUNCA bloquea las zonas ni los árboles */
    .leaflet-pane.leaflet-panePerimetro-pane,
    .leaflet-pane.leaflet-panePerimetro-pane svg,
    .leaflet-pane.leaflet-panePerimetro-pane svg path {
        pointer-events: none !important;
    }
    .leaflet-pane.leaflet-paneZonas-pane svg path {
        pointer-events: auto !important;
        cursor: pointer;
    }

    /* Tooltip al pasar el ratón (Hover) */
    .leaflet-tooltip.sig-custom-tooltip {
        background: rgba(15, 23, 42, 0.95) !important;
        border: 1px solid rgba(56, 189, 248, 0.5) !important;
        border-radius: 10px !important;
        padding: 10px 14px !important;
        color: #ffffff !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
        font-size: 0.85rem !important;
        line-height: 1.35 !important;
    }
    .leaflet-tooltip-top.sig-custom-tooltip:before {
        border-top-color: rgba(15, 23, 42, 0.95) !important;
    }

    /* Marcadores personalizados para árboles (Scrub / Arrastrables) */
    .tree-pin {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        color: white;
        font-size: 14px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.45);
        border: 2px solid #ffffff;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        cursor: grab;
    }
    .tree-pin:hover, .tree-pin:active {
        transform: scale(1.35);
        box-shadow: 0 0 0 3px #38bdf8, 0 4px 14px rgba(0,0,0,0.5);
        z-index: 1000 !important;
    }
    .tree-pin.vigoroso { background: #15803d; }
    .tree-pin.bueno { background: #22c55e; }
    .tree-pin.regular { background: #eab308; }
    .tree-pin.enfermo { background: #f97316; }
    .tree-pin.seco { background: #ef4444; }
    .tree-pin.arrancado { background: #64748b; }

    /* Paleta de colores para zonas */
    .color-swatch {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        cursor: pointer;
        display: inline-block;
        border: 2px solid transparent;
        transition: all 0.15s ease;
    }
    .color-swatch:hover, .color-swatch.active {
        transform: scale(1.15);
        border-color: #0f172a;
        box-shadow: 0 0 0 2px #38bdf8;
    }

    /* Mensaje flotante de notificación (Toast) */
    .sig-toast {
        position: absolute;
        top: 75px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1050;
        min-width: 280px;
        max-width: 90%;
        display: none;
    }

    /* Botón de Scrub activo */
    .btn-scrub-active {
        background-color: #f59e0b !important;
        color: #ffffff !important;
        border-color: #d97706 !important;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.35) !important;
    }
</style>

<div class="container-fluid px-2 px-md-4 py-2 py-md-3 sig-wrapper">
    <!-- Fila Superior de Selección de Finca -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
            <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 d-none d-sm-block">
                <i class="fa-solid fa-map-location-dot fs-4"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-0 text-dark">Visor Cartográfico SIG - Cultivos</h5>
                <span class="text-muted small d-none d-md-inline">Delimitación con zoom de alta resolución, edición interactiva y recolección anual adaptada.</span>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
            <!-- Selector de Explotación (Multi-tenant) -->
            <?php if (!empty($userTenants) && (count($userTenants) > 1 || $isSuperAdmin)): ?>
            <div class="input-group input-group-sm shadow-sm" style="max-width: 290px;">
                <span class="input-group-text bg-white text-success fw-bold"><i class="fa-solid fa-building-wheat me-1"></i> Explotación:</span>
                <select id="selectorExplotacion" class="form-select fw-semibold" onchange="cambiarExplotacion(this.value)">
                    <?php if ($isSuperAdmin): ?>
                        <option value="0" <?= empty($tenantId) ? 'selected' : '' ?>>Todas las Explotaciones</option>
                    <?php endif; ?>
                    <?php foreach ($userTenants as $t): ?>
                        <option value="<?= $t['id_propietario'] ?>" <?= ($tenantId == $t['id_propietario']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nombre_razon_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php elseif (!empty($_SESSION['nombre_propietario']) && $_SESSION['nombre_propietario'] !== 'Sin asignar'): ?>
            <span class="badge bg-white text-dark border px-2 py-1 small d-none d-lg-inline-flex align-items-center shadow-sm">
                <i class="fa-solid fa-building-wheat me-1 text-success"></i><?= htmlspecialchars($_SESSION['nombre_propietario']) ?>
            </span>
            <?php endif; ?>

            <!-- Selector de Finca Activa (filtrada por la explotación) -->
            <div class="input-group input-group-sm shadow-sm" style="max-width: 320px;">
                <span class="input-group-text bg-white text-primary fw-bold"><i class="fa-solid fa-layer-group me-1"></i> Finca:</span>
                <select id="selectorFinca" class="form-select fw-semibold" onchange="cambiarFinca(this.value)">
                    <?php if (empty($fincasDisponibles)): ?>
                        <option value="0">Sin fincas en esta explotación</option>
                    <?php else: ?>
                        <?php foreach ($fincasDisponibles as $f): ?>
                            <option value="<?= $f['id_finca'] ?>" <?= ((int)$f['id_finca'] === $fincaSeleccionadaId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nombre_finca']) ?> (<?= number_format($f['superficie_ha'], 2, ',', '.') ?> ha)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Botón Localizar Parcela Directo -->
            <button type="button" class="btn btn-sm btn-primary fw-bold shadow-sm" id="btnAutoLocalizar" onclick="forzarLocalizacionCatastral()" title="Buscar y delimitar automáticamente la parcela en Catastro">
                <i class="fa-solid fa-crosshairs me-1"></i> <span class="d-none d-sm-inline">Localizar Parcela</span>
            </button>

            <!-- Botón Pantalla Completa -->
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alternarPantallaCompleta()" title="Pantalla Completa (Ideal para móvil y tablet)">
                <i class="fa-solid fa-expand" id="iconoFullscreen"></i>
            </button>
        </div>
    </div>

    <!-- Contenedor del Mapa SIG -->
    <div class="sig-container" id="contenedorMapa">
        <!-- Notificación Toast Flotante -->
        <div id="sigToast" class="sig-toast alert alert-primary shadow-lg border-0 py-2 px-3 small d-flex align-items-center justify-content-between rounded-pill">
            <span id="sigToastMsg"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando...</span>
            <button type="button" class="btn-close btn-close-sm ms-2" onclick="cerrarToast()"></button>
        </div>

        <!-- Mapa Leaflet -->
        <div id="mapaAgricola"></div>

        <!-- Barra de Herramientas Flotante -->
        <div class="sig-toolbar">
            <div class="sig-toolbar-inner d-flex flex-wrap align-items-center justify-content-between gap-2">
                <!-- Info de la Finca Activa y Desplegable de Objetos -->
                <div class="d-flex align-items-center gap-1 text-truncate flex-wrap">
                    <span class="badge bg-primary px-2 py-1 fw-semibold text-truncate" style="max-width: 170px;" id="lblFincaActiva">Cargando...</span>
                    <span class="badge bg-light text-dark border px-2 py-1 small d-none d-lg-inline" id="lblCatastro">Pol: - | Parc: -</span>

                    <!-- DESPLEGABLE CON TODOS LOS OBJETOS INTRODUCIDOS EN EL MAPA -->
                    <div class="input-group input-group-sm shadow-sm" style="max-width: 270px;">
                        <span class="input-group-text bg-white text-primary px-2 py-0"><i class="fa-solid fa-crosshairs"></i></span>
                        <select id="selectorObjetosMapa" class="form-select form-select-sm fw-semibold" onchange="localizarObjetoSeleccionado(this.value)">
                            <option value="">📍 Localizar objeto (0)</option>
                        </select>
                    </div>
                </div>

                <!-- Botonera de Herramientas (Capas, Zoom Máximo, Scrub/Mover, Dibujo, Árboles) -->
                <div class="d-flex align-items-center gap-1 ms-auto flex-wrap">

                    <!-- ============================================================== -->
                    <!-- 1. SELECTOR PROMINENTE DE CAPAS                                -->
                    <!-- ============================================================== -->
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-dark fw-bold dropdown-toggle shadow-sm bg-white" data-bs-toggle="dropdown" aria-expanded="false" id="btnMenuCapas" title="Seleccionar fondo de satélite y superposiciones">
                            <i class="fa-solid fa-layer-group text-primary me-1"></i><span class="d-none d-sm-inline">Capas</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-3 rounded-3" style="min-width: 280px; z-index: 2000;">
                            <li><h6 class="dropdown-header text-uppercase text-muted fw-bold p-0 mb-2" style="font-size:0.72rem;"><i class="fa-solid fa-satellite me-1 text-primary"></i>Fondo del Mapa</h6></li>
                            <li class="mb-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="capaBaseRadio" id="rbPNOA" value="pnoa" checked onchange="cambiarCapaBase('pnoa')">
                                    <label class="form-check-label fw-semibold small" for="rbPNOA">
                                        🛰️ Ortofoto PNOA (IGN España)
                                        <div class="text-muted" style="font-size:0.7rem;">Máxima nitidez aérea con zoom 22</div>
                                    </label>
                                </div>
                            </li>
                            <li class="mb-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="capaBaseRadio" id="rbEsri" value="esri" onchange="cambiarCapaBase('esri')">
                                    <label class="form-check-label fw-semibold small" for="rbEsri">
                                        🌍 Satélite Mundial (Esri)
                                    </label>
                                </div>
                            </li>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="capaBaseRadio" id="rbOSM" value="osm" onchange="cambiarCapaBase('osm')">
                                    <label class="form-check-label fw-semibold small" for="rbOSM">
                                        🗺️ Callejero / Topográfico (OSM)
                                    </label>
                                </div>
                            </li>

                            <li><hr class="dropdown-divider my-2"></li>
                            <li><h6 class="dropdown-header text-uppercase text-muted fw-bold p-0 mb-2" style="font-size:0.72rem;"><i class="fa-solid fa-eye me-1 text-success"></i>Capas Superpuestas</h6></li>
                            <li class="mb-1">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="chkCatastroWMS" checked onchange="toggleCapaCatastro(this.checked)">
                                    <label class="form-check-label small fw-semibold" for="chkCatastroWMS">
                                        📑 Límites Catastrales (WMS)
                                    </label>
                                </div>
                            </li>
                            <li class="mb-1">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="chkLinderosFinca" checked onchange="toggleCapaPerimetroFinca(this.checked)">
                                    <label class="form-check-label small fw-semibold" for="chkLinderosFinca">
                                        🟦 Perímetro Oficial de la Finca
                                    </label>
                                </div>
                            </li>
                            <li class="mb-1">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="chkZonasCultivo" checked onchange="toggleCapaZonas(this.checked)">
                                    <label class="form-check-label small fw-semibold" for="chkZonasCultivo">
                                        🟩 Zonas de Cultivos (Hortalizas)
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="chkArboles" checked onchange="toggleCapaArboles(this.checked)">
                                    <label class="form-check-label small fw-semibold" for="chkArboles">
                                        🌳 Marcadores de Árboles
                                    </label>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- ============================================================== -->
                    <!-- 2. ZOOM MÁXIMO / ACERCAR                                       -->
                    <!-- ============================================================== -->
                    <button type="button" class="btn btn-sm btn-outline-info fw-bold shadow-sm" onclick="zoomMaximoFinca()" title="Acercar al máximo para ver bancales y árboles grandes">
                        <i class="fa-solid fa-magnifying-glass-plus me-1"></i><span class="d-none d-md-inline">Zoom +</span>
                    </button>

                    <!-- ============================================================== -->
                    <!-- 3. HERRAMIENTAS DE SCRUB (MOVER / EDITAR VÉRTICES / BORRAR)    -->
                    <!-- ============================================================== -->
                    <div class="btn-group btn-group-sm shadow-sm">
                        <!-- Mover / Scrub -->
                        <button type="button" class="btn btn-outline-warning fw-semibold text-dark" id="btnModoMover" onclick="alternarModoMover()" title="Modo Scrub: Arrastrar marcadores y moverlos en el mapa para corregir posición">
                            <i class="fa-solid fa-hand me-1"></i><span class="d-none d-md-inline">Mover</span>
                        </button>
                        <!-- Editar Vértices -->
                        <button type="button" class="btn btn-outline-warning fw-semibold text-dark" id="btnModoEditar" onclick="alternarModoEditarVertices()" title="Modo Editar Vértices: Redimensionar y estirar esquinas de zonas">
                            <i class="fa-solid fa-up-down-left-right me-1"></i><span class="d-none d-md-inline">Vértices</span>
                        </button>
                    </div>

                    <!-- ============================================================== -->
                    <!-- 4. DELIMITAR ZONA (POLÍGONO, CUADRADO, CÍRCULO)                -->
                    <!-- ============================================================== -->
                    <div class="btn-group btn-group-sm shadow-sm">
                        <button type="button" class="btn btn-outline-primary fw-semibold" onclick="iniciarDibujoZona('Polygon')" title="Delimitar bancal o zona de cultivo">
                            <i class="fa-solid fa-draw-polygon me-1 text-success"></i><span class="d-none d-md-inline">Zona</span>
                        </button>
                        <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Más formas</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="iniciarDibujoZona('Polygon')"><i class="fa-solid fa-draw-polygon me-2 text-success"></i>Polígono libre (bancal)</a></li>
                            <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="iniciarDibujoZona('Rectangle')"><i class="fa-solid fa-vector-square me-2 text-primary"></i>Cuadrado / Rectángulo</a></li>
                            <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="iniciarDibujoZona('Circle')"><i class="fa-regular fa-circle me-2 text-warning"></i>Círculo concéntrico</a></li>
                        </ul>
                    </div>

                    <!-- 5. PLANTAR ÁRBOL -->
                    <button type="button" class="btn btn-sm btn-outline-success fw-semibold shadow-sm" onclick="iniciarPlantarArbol()" title="Marcar árbol individual (olivo, frutal, etc.)">
                        <i class="fa-solid fa-tree me-1"></i><span class="d-none d-md-inline">Árbol</span>
                    </button>

                    <!-- 6. FIJAR VISTA -->
                    <button type="button" id="btnFijarVista" class="btn btn-sm btn-outline-secondary" onclick="guardarCentroActual()" title="Fijar esta vista y nivel de zoom para esta finca">
                        <i class="fa-solid fa-thumbtack text-danger"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Leyenda Flotante -->
        <div class="sig-legend" id="panelLeyenda">
            <div class="fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
                <span><i class="fa-solid fa-layer-group me-1 text-primary"></i> Cultivos</span>
                <div class="d-flex align-items-center gap-1">
                    <span class="badge bg-secondary rounded-pill" id="badgeTotalCultivos">0</span>
                    <button type="button" class="btn btn-sm p-0 text-muted ms-1 d-md-none" onclick="toggleLeyenda()"><i class="fa-solid fa-chevron-down" id="iconoToggleLeyenda"></i></button>
                </div>
            </div>
            <div id="cuerpoLeyenda">
                <div id="listaLeyendaCultivos" class="small text-muted mb-2">
                    Sin plantaciones activas.
                </div>
                <hr class="my-1">
                <div class="fw-bold text-dark mb-1 small"><i class="fa-solid fa-tree me-1 text-success"></i> Salud Arboleda</div>
                <div class="d-flex flex-wrap gap-1 small">
                    <span class="badge bg-success" style="background-color: #15803d !important;">Vigoroso</span>
                    <span class="badge bg-success" style="background-color: #22c55e !important;">Bueno</span>
                    <span class="badge bg-warning text-dark" style="background-color: #eab308 !important;">Regular</span>
                    <span class="badge bg-danger" style="background-color: #ef4444 !important;">Enfermo</span>
                </div>
            </div>
        </div>

        <!-- Indicador de Estado, Zoom y Coordenadas -->
        <div class="sig-status-badge d-flex align-items-center gap-2">
            <span id="txtZoomActual" class="badge bg-primary bg-opacity-75">Zoom: 18</span>
            <span id="txtCoordenadasGPS" class="font-monospace">GPS: -</span>
            <span class="opacity-50 d-none d-sm-inline">|</span>
            <span id="txtModoActual" class="text-info fw-semibold d-none d-sm-inline">Consulta</span>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ASIGNAR O EDITAR CULTIVO DE LA ZONA DELIMITADA                      -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalGuardarZona" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalZonaTitle"><i class="fa-solid fa-draw-polygon me-2"></i>Asignar Cultivo a la Zona</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="cancelarDibujoZona()"></button>
            </div>
            <form id="formZona" onsubmit="enviarGuardarZona(event)">
                <input type="hidden" id="zona_id_geometria" value="">
                <input type="hidden" id="zona_tipo_geometria" value="polygon">
                <input type="hidden" id="zona_geojson" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 small d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-info-circle fs-5"></i>
                        <div>Superficie delimitada: <strong id="zona_lbl_superficie">-</strong>.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Plantación / Cultivo en esta Zona <span class="text-danger">*</span></label>
                        <select id="zona_id_plantacion" class="form-select fw-semibold" onchange="alSeleccionarPlantacionZona(this.value)" required>
                            <option value="">-- Seleccionar cultivo/plantación --</option>
                        </select>
                        <div id="cajaInfoPlantacionZona" class="alert alert-light border py-2 px-3 small mt-2 d-none"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nombre o Referencia del Bancal</label>
                        <input type="text" id="zona_nombre" class="form-control" placeholder="Ej: Bancal Norte, Cuadro Tomates, Sector A">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small d-block">Color Distintivo en el Mapa</label>
                        <div class="d-flex align-items-center gap-2" id="paletaColoresZona">
                            <span class="color-swatch active" style="background:#10b981;" data-color="#10b981" onclick="seleccionarColorZona('#10b981', this)"></span>
                            <span class="color-swatch" style="background:#ef4444;" data-color="#ef4444" onclick="seleccionarColorZona('#ef4444', this)"></span>
                            <span class="color-swatch" style="background:#3b82f6;" data-color="#3b82f6" onclick="seleccionarColorZona('#3b82f6', this)"></span>
                            <span class="color-swatch" style="background:#8b5cf6;" data-color="#8b5cf6" onclick="seleccionarColorZona('#8b5cf6', this)"></span>
                            <span class="color-swatch" style="background:#f59e0b;" data-color="#f59e0b" onclick="seleccionarColorZona('#f59e0b', this)"></span>
                            <span class="color-swatch" style="background:#06b6d4;" data-color="#06b6d4" onclick="seleccionarColorZona('#06b6d4', this)"></span>
                            <input type="color" id="zona_color_personalizado" class="form-control form-control-color p-0 border-0" value="#10b981" onchange="seleccionarColorZona(this.value, null)">
                        </div>
                        <input type="hidden" id="zona_color_hex" value="#10b981">
                    </div>

                    <input type="hidden" id="zona_superficie_m2" value="0">
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="cancelarDibujoZona()">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="btnSubmitZona">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Zona
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: REGISTRAR O EDITAR ÁRBOL INDIVIDUAL                                -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalGuardarArbol" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="modalArbolTitle"><i class="fa-solid fa-tree me-2"></i>Registrar Árbol Individual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="cancelarDibujoArbol()"></button>
            </div>
            <form id="formArbol" onsubmit="enviarGuardarArbol(event)">
                <input type="hidden" id="arbol_id" value="">
                <input type="hidden" id="arbol_lat" value="">
                <input type="hidden" id="arbol_lng" value="">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Código del Árbol <span class="text-danger">*</span></label>
                            <input type="text" id="arbol_codigo" class="form-control fw-bold font-monospace" placeholder="Ej: OLV-01, LIM-12" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Estado Sanitario</label>
                            <select id="arbol_estado_salud" class="form-select fw-semibold">
                                <option value="vigoroso">🌿 Vigoroso / Excelente</option>
                                <option value="bueno" selected>🟢 Bueno / Normal</option>
                                <option value="regular">🟡 Regular / Débil</option>
                                <option value="enfermo">🟠 Con plaga / Enfermo</option>
                                <option value="seco">🔴 Seco / Dañado</option>
                                <option value="arrancado">⚪ Arrancado</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Plantación Asociada</label>
                            <select id="arbol_id_plantacion" class="form-select fw-semibold" onchange="alSeleccionarPlantacionArbol(this.value)">
                                <option value="">-- Sin vincular a plantación --</option>
                            </select>
                            <div id="cajaInfoPlantacionArbol" class="alert alert-light border py-2 px-3 small mt-2 d-none"></div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Variedad o Especie</label>
                            <input type="text" id="arbol_especie" class="form-control" placeholder="Ej: Olivo Picual, Limonero Verna">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Año Plantado</label>
                            <input type="number" id="arbol_ano" class="form-control" placeholder="Ej: 2020" min="1900" max="<?= date('Y') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Observaciones / Evolución</label>
                            <textarea id="arbol_notas" class="form-control" rows="2" placeholder="Poda, vigor, producción..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="cancelarDibujoArbol()">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4" id="btnSubmitArbol">
                        <i class="fa-solid fa-tree me-1"></i> Guardar Árbol
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: DUPLICAR / MULTI-COPIAR MARCADOR DE ÁRBOL                         -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalDuplicarArbol" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="modalDuplicarTitle"><i class="fa-solid fa-copy me-2"></i>Duplicar Marcador de Árbol</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formDuplicarArbol" onsubmit="enviarDuplicarArbol(event)">
                <input type="hidden" id="dup_id_arbol" value="">
                <input type="hidden" id="dup_codigo_base" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-light border py-2 px-3 small mb-3" id="cajaInfoDuplicarArbol">
                        <!-- Info del árbol origen -->
                    </div>

                    <!-- Cantidad de copias -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small">¿Cuántas copias deseas generar? <span class="text-danger">*</span></label>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-clone text-success"></i></span>
                            <input type="number" id="dup_cantidad" class="form-control fw-bold fs-5 text-center" value="1" min="1" max="100" required oninput="actualizarPrevisualizacionCopias()">
                        </div>
                        <!-- Botones rápidos de cantidad -->
                        <div class="d-flex gap-1 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fijarCantidadCopias(1)">1 copia</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fijarCantidadCopias(5)">5 copias</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fijarCantidadCopias(10)">10 copias</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fijarCantidadCopias(25)">25 copias</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fijarCantidadCopias(50)">50 copias</button>
                        </div>
                    </div>

                    <!-- Distancia de separación -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Separación estimada entre árboles (metros)</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" id="dup_distancia" class="form-control" value="6" min="1" max="50">
                            <span class="input-group-text">metros</span>
                        </div>
                        <div class="form-text small">Los árboles se crearán correlativos en hilera y podrás arrastrar cualquiera para colocarlo en su posición exacta sobre la foto.</div>
                    </div>

                    <!-- Vista previa de los códigos a generar -->
                    <div class="alert alert-info py-2 px-3 small mb-0">
                        <div class="fw-bold"><i class="fa-solid fa-list-ol me-1"></i>Códigos correlativos que se crearán:</div>
                        <div class="font-monospace mt-1 text-dark" id="txtPrevisualizacionCodigos">-</div>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4" id="btnSubmitDuplicar">
                        <i class="fa-solid fa-copy me-1"></i> Generar Copias
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- PANEL LATERAL DESLIZANTE (OFFCANVAS) PARA DETALLE AL HACER CLIC            -->
<!-- ========================================================================= -->
<div class="offcanvas offcanvas-end shadow-lg" tabindex="-1" id="offcanvasDetalle" style="width: 440px; max-width: 90vw;">
    <div class="offcanvas-header bg-dark text-white p-3">
        <div class="d-flex align-items-center gap-2">
            <div id="offcanvasIcono" class="fs-4"></div>
            <div>
                <h5 class="offcanvas-title fw-bold mb-0" id="offcanvasTitulo">Detalle</h5>
                <span class="small opacity-75" id="offcanvasSubtitulo">Ubicación</span>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0" id="offcanvasContenido"></div>
</div>

<!-- Scripts Leaflet y Geoman -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@2.14.2/dist/leaflet-geoman.min.js"></script>

<script>
    // Variables Globales
    let mapa = null;
    let fincaActual = null;
    let plantacionesActuales = [];
    let objetosMapaMemoria = {
        zonas: [],
        arboles: []
    };

    // Capas Base
    let capaOrtoPNOA = null;
    let capaEsriSat = null;
    let capaOSM = null;

    // Capas Superpuestas
    let capaCatastroWMS = null;
    let capaPerimetroFinca = null;
    let capaGeometrias = null;
    let capaArboles = null;

    let modoPlantarArbol = false;
    let modoMoverActivo = false;
    let modoEditarVerticesActivo = false;
    let formaZonaTemporal = null;

    // Paleta de colores temáticos por cultivo
    const COLORES_CULTIVO = {
        'horticola': '#ef4444',
        'frutal':    '#f59e0b',
        'citrico':   '#eab308',
        'olivar':    '#15803d',
        'vinedo':    '#8b5cf6',
        'cereal':    '#d97706',
        'leñoso':    '#10b981',
        'otro':      '#06b6d4'
    };

    document.addEventListener('DOMContentLoaded', function() {
        iniciarMapaLeaflet();
        const fincaId = document.getElementById('selectorFinca').value;
        if (fincaId && fincaId !== '0') {
            cargarDatosFinca(fincaId);
        }
    });

    /**
     * Inicializa Leaflet con soporte de ZOOM HASTA NIVEL 22 para ver la finca enorme
     */
    function iniciarMapaLeaflet() {
        mapa = L.map('mapaAgricola', {
            center: [38.1883, -1.8906], // Moratalla por defecto
            zoom: 18,
            maxZoom: 22, // Permite un zoom centimétrico ultra cercano
            zoomControl: false
        });

        // Zoom en posición superior izquierda
        L.control.zoom({ position: 'topleft' }).addTo(mapa);

        // Crear paneles con orden z-index explícito para garantizar que nada bloquee a las zonas y árboles
        mapa.createPane('panePerimetro');
        mapa.getPane('panePerimetro').style.zIndex = 350; // Debajo de las zonas
        mapa.getPane('panePerimetro').style.pointerEvents = 'none'; // Clave: los clics traspasan directamente al terreno y cultivos

        mapa.createPane('paneZonas');
        mapa.getPane('paneZonas').style.zIndex = 400; // Zonas de cultivo

        mapa.createPane('paneArboles');
        mapa.getPane('paneArboles').style.zIndex = 500; // Árboles individuales

        // 1. Ortofoto PNOA IGN España (Máxima Resolución con escalado hasta zoom 22)
        capaOrtoPNOA = L.tileLayer.wms('https://www.ign.es/wms-inspire/pnoa-ma?', {
            layers: 'OI.OrthoimageCoverage',
            format: 'image/jpeg',
            transparent: false,
            version: '1.3.0',
            maxZoom: 22,
            maxNativeZoom: 19,
            attribution: '&copy; IGN España - PNOA'
        });

        // 2. Satélite Esri
        capaEsriSat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 22,
            maxNativeZoom: 19,
            attribution: '&copy; Esri World Imagery'
        });

        // 3. OpenStreetMap
        capaOSM = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 22,
            maxNativeZoom: 19,
            attribution: '&copy; OpenStreetMap'
        });

        // 4. Catastro Oficial Transparente (WMS Sede Electrónica)
        capaCatastroWMS = L.tileLayer.wms('https://ovc.catastro.meh.es/Cartografia/WMS/BuscarFirma.aspx?', {
            layers: 'Catastro',
            format: 'image/png',
            transparent: true,
            version: '1.1.1',
            maxZoom: 22,
            attribution: '&copy; D.G. Catastro'
        });

        // Capas iniciales añadidas al mapa
        capaOrtoPNOA.addTo(mapa);
        capaCatastroWMS.addTo(mapa);

        // Grupos de vectores asignados a sus respectivos paneles
        capaPerimetroFinca = L.featureGroup([], { pane: 'panePerimetro' }).addTo(mapa);
        capaGeometrias = L.featureGroup([], { pane: 'paneZonas' }).addTo(mapa);
        capaArboles = L.featureGroup([], { pane: 'paneArboles' }).addTo(mapa);

        // Control de Geoman para trazar zonas
        mapa.pm.setLang('es');
        mapa.pm.addControls({
            position: 'topleft',
            drawMarker: false,
            drawCircleMarker: false,
            drawPolyline: false,
            drawRectangle: false,
            drawPolygon: false,
            drawCircle: false,
            editMode: false,
            dragMode: false,
            cutPolygon: false,
            removalMode: false
        });

        // Evento al terminar de dibujar una nueva zona
        mapa.on('pm:create', function(e) {
            formaZonaTemporal = e.layer;
            abrirModalGuardarZona(e);
        });

        // GPS en la barra inferior
        mapa.on('mousemove', function(e) {
            document.getElementById('txtCoordenadasGPS').textContent = 
                `${e.latlng.lat.toFixed(6)}, ${e.latlng.lng.toFixed(6)}`;
        });

        // Actualizar indicador de nivel de zoom en tiempo real
        mapa.on('zoomend', function() {
            const z = Math.round(mapa.getZoom());
            const badgeZ = document.getElementById('txtZoomActual');
            if (badgeZ) badgeZ.textContent = 'Zoom: ' + z;
        });

        // Evento click en modo plantar árbol
        mapa.on('click', function(e) {
            if (modoPlantarArbol) {
                abrirModalGuardarArbol(e.latlng.lat, e.latlng.lng);
            }
        });
    }

    /**
     * Acercar al máximo nivel de zoom (zoom microscópico para situar plantas)
     */
    function zoomMaximoFinca() {
        const centro = mapa.getCenter();
        mapa.setView(centro, 20, { animate: true });
        mostrarToast("🔍 Zoom de máxima aproximación activado (Nivel 20)", false, 2500);
    }

    /**
     * Alternar el modo SCRUB / MOVER marcadores y figuras
     */
    function alternarModoMover() {
        desactivarModoPlantarArbol();
        modoMoverActivo = !modoMoverActivo;

        const btn = document.getElementById('btnModoMover');
        if (modoMoverActivo) {
            if (modoEditarVerticesActivo) alternarModoEditarVertices();
            mapa.pm.enableGlobalDragMode();
            btn.classList.add('btn-scrub-active');
            document.getElementById('txtModoActual').textContent = '✋ Modo Scrub (Arrastrar para mover)';
            mostrarToast("✋ Modo Mover Activo: Haz clic y arrastra cualquier árbol o zona para cambiarlo de sitio.", false, 4000);
        } else {
            mapa.pm.disableGlobalDragMode();
            btn.classList.remove('btn-scrub-active');
            document.getElementById('txtModoActual').textContent = 'Consulta';
            mostrarToast("Modo Mover desactivado.", false, 2000);
        }
    }

    /**
     * Alternar el modo de edición de vértices para redimensionar zonas
     */
    function alternarModoEditarVertices() {
        desactivarModoPlantarArbol();
        modoEditarVerticesActivo = !modoEditarVerticesActivo;

        const btn = document.getElementById('btnModoEditar');
        if (modoEditarVerticesActivo) {
            if (modoMoverActivo) alternarModoMover();
            mapa.pm.enableGlobalEditMode();
            btn.classList.add('btn-scrub-active');
            document.getElementById('txtModoActual').textContent = '✏️ Editando Vértices de Zonas';
            mostrarToast("✏️ Modo Vértices Activo: Arrastra las esquinas de los bancales para ajustarlos.", false, 4000);
        } else {
            mapa.pm.disableGlobalEditMode();
            btn.classList.remove('btn-scrub-active');
            document.getElementById('txtModoActual').textContent = 'Consulta';
            mostrarToast("Edición de vértices finalizada.", false, 2000);
        }
    }

    /**
     * Cambia la capa base de fondo desde el menú Capas
     */
    function cambiarCapaBase(tipo) {
        mapa.removeLayer(capaOrtoPNOA);
        mapa.removeLayer(capaEsriSat);
        mapa.removeLayer(capaOSM);

        if (tipo === 'pnoa') {
            capaOrtoPNOA.addTo(mapa);
        } else if (tipo === 'esri') {
            capaEsriSat.addTo(mapa);
        } else if (tipo === 'osm') {
            capaOSM.addTo(mapa);
        }
    }

    function toggleCapaCatastro(activo) {
        if (activo) {
            capaCatastroWMS.addTo(mapa);
        } else {
            mapa.removeLayer(capaCatastroWMS);
        }
    }

    function toggleCapaPerimetroFinca(activo) {
        if (activo) {
            capaPerimetroFinca.addTo(mapa);
        } else {
            mapa.removeLayer(capaPerimetroFinca);
        }
    }

    function toggleCapaZonas(activo) {
        if (activo) {
            capaGeometrias.addTo(mapa);
        } else {
            mapa.removeLayer(capaGeometrias);
        }
    }

    function toggleCapaArboles(activo) {
        if (activo) {
            capaArboles.addTo(mapa);
        } else {
            mapa.removeLayer(capaArboles);
        }
    }

    /**
     * Carga todos los datos de la finca y ejecuta la localización automática y delimitación
     */
    function cargarDatosFinca(idFinca) {
        mostrarToast("Cargando finca y localizando linderos...", false);
        document.getElementById('lblFincaActiva').textContent = 'Cargando...';

        fetch(`${window.APP_URL}/api/mapas?action=obtener_finca&finca_id=${idFinca}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    mostrarToast(data.message || 'Error al cargar datos de la finca.', true);
                    return;
                }

                fincaActual = data.finca;
                plantacionesActuales = data.plantaciones;

                // Actualizar labels
                document.getElementById('lblFincaActiva').textContent = fincaActual.nombre_finca;
                const pol = fincaActual.poligono || '-';
                const parc = fincaActual.parcela || '-';
                const ref = fincaActual.referencia_catastral ? ` | Ref: ${fincaActual.referencia_catastral}` : '';
                document.getElementById('lblCatastro').textContent = `Pol: ${pol} | Parc: ${parc}${ref}`;

                actualizarSelectoresPlantacion();

                // Resetear objetos en memoria
                objetosMapaMemoria = { zonas: [], arboles: [] };

                // Limpiar capas previas
                capaPerimetroFinca.clearLayers();
                capaGeometrias.clearLayers();
                capaArboles.clearLayers();

                // 1. Delimitación Catastral Oficial al fondo (nunca bloquea clics)
                gestionarDelimitacionFinca();

                // 2. Renderizar zonas de cultivo
                if (data.geometrias && data.geometrias.length > 0) {
                    data.geometrias.forEach(g => renderizarZonaEnMapa(g));
                }

                // 3. Renderizar árboles en la capa superior
                if (data.arboles && data.arboles.length > 0) {
                    data.arboles.forEach(a => renderizarArbolEnMapa(a));
                }

                // 4. Actualizar desplegable de objetos introducidos en el mapa y leyenda
                actualizarSelectorObjetosMapa();
                actualizarLeyendaCultivos(plantacionesActuales);
            })
            .catch(err => {
                console.error(err);
                mostrarToast("Error de conexión al cargar la finca.", true);
            });
    }

    /**
     * Localiza la parcela catastral, dibuja su perímetro oficial y sitúa la vista.
     * Si el usuario ha fijado una vista con el pincho rojo, se respeta estrictamente
     * el centro y el nivel de zoom guardados.
     */
    function gestionarDelimitacionFinca() {
        if (!fincaActual) return;

        // Comprobar si la finca tiene coordenadas y zoom fijados por el usuario
        const tieneVistaFijada = fincaActual.latitud_centro && 
                                 fincaActual.longitud_centro && 
                                 parseFloat(fincaActual.latitud_centro) !== 0 && 
                                 parseFloat(fincaActual.longitud_centro) !== 0;

        const latFijada = tieneVistaFijada ? parseFloat(fincaActual.latitud_centro) : null;
        const lngFijada = tieneVistaFijada ? parseFloat(fincaActual.longitud_centro) : null;
        const zoomFijado = tieneVistaFijada ? (parseInt(fincaActual.zoom_mapa, 10) || 18) : null;

        // PRIORIDAD 1: Si el usuario TIENE vista fijada con el pincho rojo
        if (tieneVistaFijada) {
            // Fijar inmediatamente la vista y el zoom guardados
            mapa.setView([latFijada, lngFijada], zoomFijado, { animate: false });
            const badgeZ = document.getElementById('txtZoomActual');
            if (badgeZ) badgeZ.textContent = 'Zoom: ' + zoomFijado;

            // Si ya tiene perímetro catastral guardado, pintarlo en el mapa SIN hacer zoom
            if (fincaActual.geojson_perimetro) {
                try {
                    const geoPerim = JSON.parse(fincaActual.geojson_perimetro);
                    dibujarPerimetroOficial(geoPerim, false); // false = NO alterar la vista ni el zoom fijado
                    mostrarToast(`📌 Vista y Zoom (${zoomFijado}) cargados para ${fincaActual.nombre_finca}`, false, 2500);
                    return;
                } catch (e) {
                    console.warn("GeoJSON perimétrico no parseable, buscando en Catastro...");
                }
            }

            // Si no tiene perímetro guardado, buscarlo en segundo plano SIN mover la vista
            consultarCatastroOnline(fincaActual, false);
            mostrarToast(`📌 Vista y Zoom (${zoomFijado}) cargados para ${fincaActual.nombre_finca}`, false, 2500);
            return;
        }

        // PRIORIDAD 2: No tiene vista fijada por el usuario
        // Caso A: Ya tiene perímetro catastral guardado -> ajustar vista al polígono
        if (fincaActual.geojson_perimetro) {
            try {
                const geoPerim = JSON.parse(fincaActual.geojson_perimetro);
                dibujarPerimetroOficial(geoPerim, true);
                mostrarToast(`Finca delimitada: Pol. ${fincaActual.poligono || '-'} Parc. ${fincaActual.parcela || '-'}`, false, 3000);
                return;
            } catch (e) {
                console.warn("GeoJSON perimétrico no parseable, buscando en Catastro...");
            }
        }

        // Caso B: Si tiene cultivos delimitados o árboles, ajustar a ellos
        if (capaGeometrias.getLayers().length > 0 || capaArboles.getLayers().length > 0) {
            const bounds = capaGeometrias.getBounds().extend(capaArboles.getBounds());
            if (bounds.isValid()) {
                mapa.fitBounds(bounds, { padding: [30, 30], maxZoom: 19 });
                mostrarToast("Finca centrada en sus cultivos delimitados.", false, 2500);
                consultarCatastroOnline(fincaActual, false);
                return;
            }
        }

        // Caso C: Consulta directa al Catastro forzando zoom al encontrar la parcela
        consultarCatastroOnline(fincaActual, true);
    }

    /**
     * Consulta el servicio Catastral en Backend y WFS para obtener el polígono oficial
     */
    function consultarCatastroOnline(finca, forzarZoom) {
        mostrarToast("Localizando parcela en la Sede del Catastro...", false);

        fetch(`${window.APP_URL}/api/mapas?action=localizar_catastro&finca_id=${finca.id_finca}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.geojson_perimetro) {
                    const geo = JSON.parse(data.geojson_perimetro);
                    dibujarPerimetroOficial(geo, forzarZoom);
                    mostrarToast(`¡Parcela delimitada con éxito! (Pol ${data.poligono || '-'} Parc ${data.parcela || '-'})`, false, 4000);
                } else if (data.success && data.latitud && data.longitud) {
                    const lat = parseFloat(data.latitud);
                    const lng = parseFloat(data.longitud);
                    if (forzarZoom) {
                        mapa.setView([lat, lng], 18);
                    }
                    mostrarToast(`Parcela localizada: ${data.latitud}, ${data.longitud}`, false, 3500);
                } else {
                    consultarWFSCatastroNavegador(finca, forzarZoom);
                }
            })
            .catch(err => {
                consultarWFSCatastroNavegador(finca, forzarZoom);
            });
    }

    /**
     * Consulta WFS INSPIRE de Catastro directamente desde el navegador del usuario
     */
    function consultarWFSCatastroNavegador(finca, forzarZoom) {
        let refCat = (finca.referencia_catastral || '').trim();
        const pol = (finca.poligono || '').trim();
        const parc = (finca.parcela || '').trim();

        if (!refCat && pol && parc) {
            refCat = '30028A' + pol.padStart(3, '0') + parc.padStart(5, '0');
        }

        if (!refCat) {
            mostrarToast("Esta finca no tiene polígono/parcela ni referencia catastral.", true, 4000);
            return;
        }

        const cleanRef = refCat.replace(/[^A-Za-z0-9]/g, '').substring(0, 14);
        const wfsUrl = `https://ovc.catastro.meh.es/INSPIRE/wfsCP.aspx?service=wfs&version=2&request=getfeature&STOREDQUERIE_ID=GetParcel&refcat=${cleanRef}&srsname=EPSG:4326`;

        fetch(wfsUrl)
            .then(res => res.text())
            .then(xmlStr => {
                const parser = new DOMParser();
                const xml = parser.parseFromString(xmlStr, 'text/xml');
                const posList = xml.querySelector('posList');

                if (posList && posList.textContent) {
                    const raw = posList.textContent.trim().split(/\s+/);
                    const coords = [];
                    let sumLat = 0, sumLng = 0, count = 0;

                    for (let i = 0; i < raw.length - 1; i += 2) {
                        const v1 = parseFloat(raw[i]);
                        const v2 = parseFloat(raw[i + 1]);
                        const lat = (v1 > 20 && v1 < 50) ? v1 : v2;
                        const lng = (v1 > 20 && v1 < 50) ? v2 : v1;

                        coords.push([lng, lat]);
                        sumLat += lat;
                        sumLng += lng;
                        count++;
                    }

                    if (count >= 3) {
                        const geojsonObj = {
                            type: 'Polygon',
                            coordinates: [coords]
                        };
                        dibujarPerimetroOficial(geojsonObj, forzarZoom);

                        const cLat = sumLat / count;
                        const cLng = sumLng / count;
                        
                        // Si la finca ya tiene coordenadas fijadas, NO sobreescribirlas
                        if (!finca.latitud_centro || parseFloat(finca.latitud_centro) === 0) {
                            guardarCentroFincaDirecto(finca.id_finca, cLat, cLng, JSON.stringify(geojsonObj));
                        } else {
                            guardarSoloPerimetroFinca(finca.id_finca, JSON.stringify(geojsonObj));
                        }

                        mostrarToast(`¡Linderos oficiales de la parcela cargados!`, false, 3500);
                        return;
                    }
                }
                mostrarToast("No se pudo obtener la geometría exacta de Catastro.", true, 3000);
            })
            .catch(e => {
                mostrarToast("Asegúrate de que la finca tenga Polígono y Parcela o Referencia Catastral.", true, 4000);
            });
    }

    /**
     * Dibuja el perímetro oficial de la finca y ajusta el zoom grande.
     * Se sitúa en panePerimetro con interactive: false para que NUNCA bloquee clics a cultivos ni árboles.
     */
    function dibujarPerimetroOficial(geojsonObj, hacerZoom) {
        capaPerimetroFinca.clearLayers();

        const layer = L.geoJSON(geojsonObj, {
            pane: 'panePerimetro',
            interactive: false,
            style: {
                color: '#0284c7',
                weight: 3,
                dashArray: '6, 6',
                opacity: 0.95,
                fillColor: '#38bdf8',
                fillOpacity: 0.08,
                interactive: false
            }
        });

        capaPerimetroFinca.addLayer(layer);

        if (hacerZoom) {
            mapa.fitBounds(layer.getBounds(), {
                padding: [25, 25],
                maxZoom: 19, // Zoom grande para ver la parcela con detalle
                animate: true,
                duration: 1.0
            });
        }
    }

    function forzarLocalizacionCatastral() {
        if (!fincaActual) return;
        const btn = document.getElementById('btnAutoLocalizar');
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Buscando...`;
        btn.disabled = true;

        consultarCatastroOnline(fincaActual, true);

        setTimeout(() => {
            btn.innerHTML = `<i class="fa-solid fa-crosshairs me-1"></i> <span class="d-none d-sm-inline">Localizar Parcela</span>`;
            btn.disabled = false;
        }, 2000);
    }

    function guardarCentroFincaDirecto(idFinca, lat, lng, geojson) {
        fetch(`${window.APP_URL}/api/mapas?action=guardar_centro_finca`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_finca: idFinca,
                latitud_centro: lat,
                longitud_centro: lng,
                zoom_mapa: 18,
                geojson_perimetro: geojson
            })
        });
    }

    function guardarSoloPerimetroFinca(idFinca, geojson) {
        fetch(`${window.APP_URL}/api/mapas?action=guardar_centro_finca`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_finca: idFinca,
                geojson_perimetro: geojson
            })
        });
    }

    /**
     * Dibuja y enlaza eventos de una zona (con soporte SCRUB / MOVER / EDITAR)
     */
    function renderizarZonaEnMapa(g) {
        try {
            const geojsonObj = JSON.parse(g.geojson);
            const plantacion = plantacionesActuales.find(p => p.id_plantacion == g.id_plantacion);
            const color = g.color_hex || (plantacion ? (COLORES_CULTIVO[plantacion.tipo_cultivo] || '#10b981') : '#10b981');

            const layer = L.geoJSON(geojsonObj, {
                pane: 'paneZonas',
                style: {
                    color: color,
                    weight: 3,
                    opacity: 0.9,
                    fillColor: color,
                    fillOpacity: 0.4
                }
            });

            const sublayer = layer.getLayers()[0];
            if (!sublayer) return;

            sublayer._idGeometria = g.id_geometria;
            sublayer._datosZona = g;
            sublayer.options.pane = 'paneZonas';

            // Guardar en memoria de objetos para el selector desplegable
            objetosMapaMemoria.zonas.push({
                geometria: g,
                plantacion: plantacion,
                layer: sublayer
            });

            const nombreCultivo = plantacion ? plantacion.nombre_comun : 'Cultivo';
            const variedad = plantacion && plantacion.variedad ? `(${plantacion.variedad})` : '';
            const cuadro = plantacion ? plantacion.identificador_cuadro : (g.nombre_zona || 'Zona');
            const supM2 = g.superficie_m2 ? `${Math.round(g.superficie_m2)} m²` : '';

            // Tooltip con fecha de recolección adaptada
            let txtRecoleccion = '';
            if (plantacion && plantacion.fecha_recoleccion_formateada) {
                txtRecoleccion = `<div class="small text-warning"><strong>Cosecha (${plantacion.es_fija_anual ? 'Anual ' + new Date().getFullYear() : 'Ciclo'}):</strong> ${plantacion.fecha_recoleccion_formateada}</div>`;
            }

            const tooltipHtml = `
                <div class="fw-bold fs-6 text-light"><i class="fa-solid fa-leaf me-1 text-success"></i>${nombreCultivo} ${variedad}</div>
                <div class="small text-light mt-1"><strong>Cuadro:</strong> ${cuadro} ${supM2 ? `(${supM2})` : ''}</div>
                ${txtRecoleccion}
                <div class="small text-info mt-1"><i class="fa-regular fa-hand-pointer me-1"></i>Clic: Ficha | Modo Mover: Arrastrar</div>
            `;

            sublayer.bindTooltip(tooltipHtml, { sticky: true, className: 'sig-custom-tooltip' });

            sublayer.on('mouseover', function() { if (!modoMoverActivo && !modoEditarVerticesActivo) this.setStyle({ weight: 4, fillOpacity: 0.65 }); });
            sublayer.on('mouseout', function() { if (!modoMoverActivo && !modoEditarVerticesActivo) this.setStyle({ weight: 3, fillOpacity: 0.4 }); });
            sublayer.on('click', function(e) {
                L.DomEvent.stopPropagation(e);
                if (modoPlantarArbol) {
                    abrirModalGuardarArbol(e.latlng.lat, e.latlng.lng);
                    return;
                }
                if (!modoMoverActivo && !modoEditarVerticesActivo) {
                    abrirDetalleZona(g, plantacion, sublayer);
                }
            });

            // Guardar automáticamente al mover (Drag) o editar vértices
            sublayer.on('pm:dragend', function() {
                guardarGeometriaModificada(sublayer);
            });
            sublayer.on('pm:edit', function() {
                guardarGeometriaModificada(sublayer);
            });

            capaGeometrias.addLayer(sublayer);
        } catch (e) {
            console.error("Error en zona:", e);
        }
    }

    /**
     * Guarda la nueva geometría tras mover o editar vértices (SCRUB)
     */
    function guardarGeometriaModificada(layer) {
        const idGeom = layer._idGeometria;
        if (!idGeom) return;

        const geojson = JSON.stringify(layer.toGeoJSON().geometry);
        let supM2 = 0;
        if (layer.getLatLngs) {
            const latlngs = layer.getLatLngs()[0];
            supM2 = L.GeometryUtil ? L.GeometryUtil.geodesicArea(latlngs) : 0;
        } else if (layer.getRadius) {
            const r = layer.getRadius();
            supM2 = Math.PI * r * r;
        }

        fetch(`${window.APP_URL}/api/mapas?action=mover_zona`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_geometria: idGeom,
                geojson: geojson,
                superficie_m2: supM2
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                mostrarToast("✅ Posición y dimensiones de la zona actualizadas.", false, 2500);
            }
        });
    }

    /**
     * Dibuja y enlaza eventos de un árbol individual (con SCRUB / ARRASTRABLE)
     */
    function renderizarArbolEnMapa(a) {
        const lat = parseFloat(a.latitud);
        const lng = parseFloat(a.longitud);
        if (isNaN(lat) || isNaN(lng)) return;

        const estado = a.estado_salud || 'bueno';
        const iconoHtml = `<div class="tree-pin ${estado}" title="${a.codigo_arbol} (Arrastrable)"><i class="fa-solid fa-tree"></i></div>`;

        const icono = L.divIcon({
            html: iconoHtml,
            className: 'custom-tree-icon',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        // Marcador arrastrable (Draggable) para permitir Scrub
        const marcador = L.marker([lat, lng], { 
            pane: 'paneArboles',
            icon: icono,
            draggable: true 
        });

        marcador._idArbol = a.id_arbol;
        marcador._datosArbol = a;

        // Guardar en memoria de objetos para el selector desplegable
        objetosMapaMemoria.arboles.push({
            arbol: a,
            marcador: marcador
        });

        const tooltipHtml = `
            <div class="fw-bold fs-6 text-success"><i class="fa-solid fa-tree me-1"></i>${a.codigo_arbol}</div>
            <div class="small text-light mt-1"><strong>Especie:</strong> ${a.especie_variedad || a.nombre_cultivo || 'Árbol'}</div>
            <div class="small text-light"><strong>Salud:</strong> ${estado.toUpperCase()}</div>
            <div class="small text-info mt-1"><i class="fa-solid fa-arrows-up-down-left-right me-1"></i>Arrastra para mover de sitio</div>
        `;

        marcador.bindTooltip(tooltipHtml, { sticky: true, className: 'sig-custom-tooltip' });

        marcador.on('click', function(e) {
            L.DomEvent.stopPropagation(e);
            abrirDetalleArbol(a, marcador);
        });

        // Evento dragend: Al terminar de arrastrar el árbol, guardar su nueva coordenada GPS
        marcador.on('dragend', function(e) {
            const nuevaPos = marcador.getLatLng();
            guardarPosicionArbolMovido(a.id_arbol, nuevaPos.lat, nuevaPos.lng, a.codigo_arbol);
        });

        capaArboles.addLayer(marcador);
    }

    /**
     * Guarda la nueva posición GPS del árbol tras el Scrub / Arrastre
     */
    function guardarPosicionArbolMovido(idArbol, lat, lng, codigo) {
        fetch(`${window.APP_URL}/api/mapas?action=mover_arbol`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_arbol: idArbol,
                latitud: lat,
                longitud: lng
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                mostrarToast(`📍 Árbol ${codigo} movido a: ${lat.toFixed(6)}, ${lng.toFixed(6)}`, false, 3000);
            } else {
                mostrarToast("Error al actualizar posición del árbol.", true, 3000);
            }
        });
    }

    /**
     * Abre el panel lateral con la ficha agronómica completa y fecha adaptada
     */
    function abrirDetalleZona(geometria, plantacion, layer) {
        const offTitulo = document.getElementById('offcanvasTitulo');
        const offSub = document.getElementById('offcanvasSubtitulo');
        const offIcono = document.getElementById('offcanvasIcono');
        const offContenido = document.getElementById('offcanvasContenido');

        const nomCultivo = plantacion ? plantacion.nombre_comun : 'Cultivo';
        const variedad = plantacion && plantacion.variedad ? plantacion.variedad : '';
        const cuadro = geometria.nombre_zona || (plantacion ? plantacion.identificador_cuadro : 'Zona');

        offTitulo.textContent = `${nomCultivo} ${variedad ? '- ' + variedad : ''}`;
        offSub.textContent = `Cuadro: ${cuadro} | Finca: ${fincaActual.nombre_finca}`;
        offIcono.innerHTML = `<i class="fa-solid fa-layer-group text-warning"></i>`;

        let ultimasLabores = plantacion && plantacion.ultimas_labores ? plantacion.ultimas_labores : {};

        // Badge de Plantación Fija vs Temporada
        const esFija = plantacion && plantacion.es_fija_anual;
        const tipoBadge = esFija ? 
            `<span class="badge bg-success bg-opacity-75 px-3 py-1"><i class="fa-solid fa-tree me-1"></i>Plantación Fija / Plurianual</span>` : 
            `<span class="badge bg-primary px-3 py-1"><i class="fa-solid fa-seedling me-1"></i>Plantación de Temporada</span>`;

        // Fecha de recolección adaptada al año en vigor
        let infoCosecha = '';
        if (plantacion && plantacion.fecha_recoleccion_formateada) {
            infoCosecha = `
                <div class="alert ${esFija ? 'alert-success border-success-subtle' : 'alert-info border-info-subtle'} py-2 px-3 small mb-3">
                    <div class="fw-bold"><i class="fa-solid fa-calendar-check me-1"></i>Cosecha / Recolección: ${plantacion.fecha_recoleccion_formateada}</div>
                    ${esFija ? `<div class="text-muted" style="font-size:0.75rem;">(Fecha anual adaptada automáticamente a la campaña en curso ${new Date().getFullYear()})</div>` : ''}
                </div>
            `;
        }

        let html = `
            <div class="p-3 bg-light border-bottom">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    ${tipoBadge}
                    <span class="text-muted small">Campaña: <strong>${plantacion ? plantacion.campana_ano : '-'}</strong></span>
                </div>

                ${infoCosecha}

                <div class="row g-2 text-center mt-1">
                    <div class="col-6">
                        <div class="bg-white p-2 rounded-3 border">
                            <div class="text-muted small">Superficie Delimitada</div>
                            <div class="fw-bold text-dark fs-6">${geometria.superficie_m2 ? Math.round(geometria.superficie_m2) + ' m²' : '-'}</div>
                            <div class="small text-muted">${geometria.superficie_m2 ? (geometria.superficie_m2 / 10000).toFixed(3) + ' ha' : ''}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-white p-2 rounded-3 border">
                            <div class="text-muted small">Plantas / Árboles</div>
                            <div class="fw-bold text-dark fs-6">${plantacion && plantacion.num_arboles_o_plantas ? plantacion.num_arboles_o_plantas : 'En bloque'}</div>
                            <div class="small text-muted">${plantacion && plantacion.marco_plantacion ? plantacion.marco_plantacion : 'Marco libre'}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-3">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-clock-rotate-left me-1"></i> Historial Reciente</h6>

                <!-- Último Riego -->
                <div class="card shadow-sm border-0 mb-2">
                    <div class="card-body p-2 d-flex align-items-start gap-3">
                        <div class="p-2 bg-info bg-opacity-10 text-info rounded-circle"><i class="fa-solid fa-droplet"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold small text-dark">Último Riego</div>
                            ${ultimasLabores.riego ? `
                                <div class="text-muted small">Fecha: <strong>${ultimasLabores.riego.fecha_riego}</strong> (${ultimasLabores.riego.horas_riego || '-'} h)</div>
                            ` : `<div class="text-muted small fst-italic">Sin riegos recientes.</div>`}
                        </div>
                        <a href="${window.APP_URL}/riegos" class="btn btn-sm btn-outline-info"><i class="fa-solid fa-plus"></i></a>
                    </div>
                </div>

                <!-- Último Tratamiento -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-2 d-flex align-items-start gap-3">
                        <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-circle"><i class="fa-solid fa-shield-virus"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold small text-dark">Último Tratamiento</div>
                            ${ultimasLabores.tratamiento ? `
                                <div class="text-muted small">Fecha: <strong>${ultimasLabores.tratamiento.fecha_tratamiento}</strong></div>
                                <div class="small text-dark fw-semibold">${ultimasLabores.tratamiento.producto_comercial || '-'}</div>
                            ` : `<div class="text-muted small fst-italic">Sin tratamientos registrados.</div>`}
                        </div>
                        <a href="${window.APP_URL}/tratamientos" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-plus"></i></a>
                    </div>
                </div>

                <!-- Botones de Acción y Edición (Scrub / Modificar) -->
                <div class="d-grid gap-2 pt-2">
                    <button type="button" class="btn btn-outline-warning text-dark fw-bold btn-sm" onclick="editarDatosZonaActual(${JSON.stringify(geometria).replace(/"/g, '&quot;')})">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Modificar Datos de esta Zona
                    </button>
                    ${plantacion ? `
                        <a href="${window.APP_URL}/plantaciones?finca_id=${fincaActual.id_finca}" class="btn btn-outline-primary fw-semibold btn-sm">
                            <i class="fa-solid fa-cubes-stacked me-1"></i> Ir a Ficha de Plantación
                        </a>
                    ` : ''}
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarZonaDelimitada(${geometria.id_geometria})">
                        <i class="fa-solid fa-trash me-1"></i> Eliminar Delimitación de esta Zona
                    </button>
                </div>
            </div>
        `;

        offContenido.innerHTML = html;
        new bootstrap.Offcanvas(document.getElementById('offcanvasDetalle')).show();
    }

    /**
     * Abre el panel lateral con la ficha del árbol y opciones de mover/editar/borrar
     */
    function abrirDetalleArbol(arbol, marker) {
        const offTitulo = document.getElementById('offcanvasTitulo');
        const offSub = document.getElementById('offcanvasSubtitulo');
        const offIcono = document.getElementById('offcanvasIcono');
        const offContenido = document.getElementById('offcanvasContenido');

        offTitulo.textContent = `Árbol: ${arbol.codigo_arbol}`;
        offSub.textContent = `${arbol.especie_variedad || arbol.nombre_cultivo || 'Frutal'} | ${fincaActual.nombre_finca}`;
        offIcono.innerHTML = `<i class="fa-solid fa-tree text-success"></i>`;

        let html = `
            <div class="p-3 bg-light border-bottom">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-success px-3 py-1 fs-6">${arbol.estado_salud.toUpperCase()}</span>
                    <span class="text-muted small">Año Plantado: <strong>${arbol.ano_plantacion || 'No registrado'}</strong></span>
                </div>
                <div class="bg-white p-2 rounded-3 border small">
                    <div><strong>Coordenadas GPS:</strong> <span class="font-monospace">${parseFloat(arbol.latitud).toFixed(6)}, ${parseFloat(arbol.longitud).toFixed(6)}</span></div>
                    <div><strong>Portainjerto:</strong> ${arbol.patron_portainjerto || 'Estándar'}</div>
                    ${arbol.identificador_cuadro ? `<div><strong>Cuadro:</strong> ${arbol.identificador_cuadro}</div>` : ''}
                </div>
            </div>

            <div class="p-3">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-clipboard-check me-1 text-primary"></i> Observaciones</h6>
                <div class="bg-light p-3 rounded-3 border small mb-3">
                    ${arbol.notas ? arbol.notas : '<span class="text-muted fst-italic">Sin anotaciones específicas.</span>'}
                </div>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-success fw-bold btn-sm shadow-sm" onclick="abrirModalDuplicarArbol(${JSON.stringify(arbol).replace(/"/g, '&quot;')})">
                        <i class="fa-solid fa-copy me-1"></i> Duplicar / Multi-Copiar este Árbol
                    </button>
                    <button type="button" class="btn btn-outline-warning text-dark fw-bold btn-sm" onclick="editarDatosArbol(${JSON.stringify(arbol).replace(/"/g, '&quot;')})">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Modificar Datos de este Árbol
                    </button>
                    <button type="button" class="btn btn-outline-info text-dark fw-semibold btn-sm" onclick="centrarYParpadearArbol(${arbol.latitud}, ${arbol.longitud})">
                        <i class="fa-solid fa-arrows-to-dot me-1"></i> Centrar en Mapa
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarArbolIndividual(${arbol.id_arbol})">
                        <i class="fa-solid fa-trash me-1"></i> Eliminar este Árbol del Mapa
                    </button>
                </div>
            </div>
        `;

        offContenido.innerHTML = html;
        new bootstrap.Offcanvas(document.getElementById('offcanvasDetalle')).show();
    }

    function centrarYParpadearArbol(lat, lng) {
        mapa.setView([lat, lng], 20, { animate: true });
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasDetalle')).hide();
        mostrarToast("📍 Árbol centrado con zoom 20. Puedes arrastrarlo para recolocarlo.", false, 3500);
    }

    /**
     * Inicia dibujo de zona (Polígono, Rectángulo, Círculo)
     */
    function iniciarDibujoZona(tipo) {
        desactivarModoPlantarArbol();
        if (modoMoverActivo) alternarModoMover();
        if (modoEditarVerticesActivo) alternarModoEditarVertices();

        document.getElementById('txtModoActual').textContent = `Dibujando ${tipo}...`;
        mapa.pm.enableDraw(tipo, { snappable: true, snapDistance: 20 });
    }

    function abrirModalGuardarZona(e) {
        const layer = e.layer;
        const shape = e.shape;

        document.getElementById('zona_id_geometria').value = '';
        document.getElementById('zona_tipo_geometria').value = shape.toLowerCase();
        document.getElementById('zona_geojson').value = JSON.stringify(layer.toGeoJSON().geometry);

        let supM2 = 0;
        if (shape === 'Polygon' || shape === 'Rectangle') {
            const latlngs = layer.getLatLngs()[0];
            supM2 = L.GeometryUtil ? L.GeometryUtil.geodesicArea(latlngs) : 0;
        } else if (shape === 'Circle') {
            const r = layer.getRadius();
            supM2 = Math.PI * r * r;
        }

        document.getElementById('zona_superficie_m2').value = supM2;
        document.getElementById('zona_lbl_superficie').textContent = supM2 > 0 ? 
            `${Math.round(supM2)} m² (${(supM2 / 10000).toFixed(3)} ha)` : 'Área libre';

        // Si solo hay una plantación en la finca, preseleccionarla automáticamente
        const selZ = document.getElementById('zona_id_plantacion');
        if (plantacionesActuales.length === 1) {
            selZ.value = plantacionesActuales[0].id_plantacion;
            alSeleccionarPlantacionZona(plantacionesActuales[0].id_plantacion);
        } else if (selZ.value) {
            alSeleccionarPlantacionZona(selZ.value);
        } else {
            document.getElementById('cajaInfoPlantacionZona').classList.add('d-none');
            document.getElementById('zona_nombre').value = '';
        }

        new bootstrap.Modal(document.getElementById('modalGuardarZona')).show();
    }

    /**
     * Permite modificar datos de una zona existente
     */
    function editarDatosZonaActual(geometria) {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasDetalle')).hide();

        document.getElementById('zona_id_geometria').value = geometria.id_geometria;
        document.getElementById('zona_tipo_geometria').value = geometria.tipo_geometria || 'polygon';
        document.getElementById('zona_geojson').value = geometria.geojson;
        document.getElementById('zona_nombre').value = geometria.nombre_zona || '';
        document.getElementById('zona_id_plantacion').value = geometria.id_plantacion || '';
        document.getElementById('zona_color_hex').value = geometria.color_hex || '#10b981';
        document.getElementById('zona_superficie_m2').value = geometria.superficie_m2 || 0;
        document.getElementById('zona_lbl_superficie').textContent = geometria.superficie_m2 ? `${Math.round(geometria.superficie_m2)} m²` : '-';

        if (geometria.id_plantacion) {
            alSeleccionarPlantacionZona(geometria.id_plantacion);
        }

        new bootstrap.Modal(document.getElementById('modalGuardarZona')).show();
    }

    function enviarGuardarZona(e) {
        e.preventDefault();
        const idPlantacion = document.getElementById('zona_id_plantacion').value;
        if (!idPlantacion) {
            alert("Debes seleccionar una plantación.");
            return;
        }

        const data = {
            id_geometria: document.getElementById('zona_id_geometria').value,
            id_finca: fincaActual.id_finca,
            id_plantacion: idPlantacion,
            nombre_zona: document.getElementById('zona_nombre').value,
            tipo_geometria: document.getElementById('zona_tipo_geometria').value,
            geojson: document.getElementById('zona_geojson').value,
            color_hex: document.getElementById('zona_color_hex').value,
            superficie_m2: document.getElementById('zona_superficie_m2').value
        };

        const btn = document.getElementById('btnSubmitZona');
        btn.disabled = true;

        fetch(`${window.APP_URL}/api/mapas?action=guardar_zona`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resp => {
            btn.disabled = false;
            if (resp.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalGuardarZona')).hide();
                if (formaZonaTemporal) {
                    mapa.removeLayer(formaZonaTemporal);
                    formaZonaTemporal = null;
                }
                document.getElementById('txtModoActual').textContent = 'Consulta';
                cargarDatosFinca(fincaActual.id_finca);
            } else {
                alert(resp.message || 'Error al guardar zona.');
            }
        });
    }

    function cancelarDibujoZona() {
        if (formaZonaTemporal) {
            mapa.removeLayer(formaZonaTemporal);
            formaZonaTemporal = null;
        }
        document.getElementById('txtModoActual').textContent = 'Consulta';
    }

    function iniciarPlantarArbol() {
        mapa.pm.disableDraw();
        modoPlantarArbol = true;
        document.getElementById('txtModoActual').textContent = '🌳 Haz clic en el mapa';
        mapa.getContainer().style.cursor = 'crosshair';
        mostrarToast("Modo Árbol activo: Haz clic en el punto exacto para colocar el árbol.", false, 3500);
    }

    function desactivarModoPlantarArbol() {
        modoPlantarArbol = false;
        mapa.getContainer().style.cursor = '';
        document.getElementById('txtModoActual').textContent = 'Consulta';
    }

    function abrirModalGuardarArbol(lat, lng) {
        desactivarModoPlantarArbol();

        document.getElementById('arbol_id').value = '';
        document.getElementById('arbol_lat').value = lat;
        document.getElementById('arbol_lng').value = lng;

        const totalArb = capaArboles.getLayers().length + 1;
        document.getElementById('arbol_codigo').value = `ARB-${String(totalArb).padStart(3, '0')}`;
        document.getElementById('arbol_estado_salud').value = 'bueno';
        document.getElementById('arbol_especie').value = '';
        document.getElementById('arbol_ano').value = new Date().getFullYear();
        document.getElementById('arbol_notas').value = '';

        // Si hay una plantación de arboleda / fija o solo hay una, seleccionarla por defecto
        const selA = document.getElementById('arbol_id_plantacion');
        const plantacionArbol = plantacionesActuales.find(p => p.es_fija_anual) || (plantacionesActuales.length === 1 ? plantacionesActuales[0] : null);
        if (plantacionArbol) {
            selA.value = plantacionArbol.id_plantacion;
            alSeleccionarPlantacionArbol(plantacionArbol.id_plantacion);
        } else if (selA.value) {
            alSeleccionarPlantacionArbol(selA.value);
        } else {
            document.getElementById('cajaInfoPlantacionArbol').classList.add('d-none');
        }

        new bootstrap.Modal(document.getElementById('modalGuardarArbol')).show();
    }

    function editarDatosArbol(arbol) {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasDetalle')).hide();

        document.getElementById('arbol_id').value = arbol.id_arbol;
        document.getElementById('arbol_lat').value = arbol.latitud;
        document.getElementById('arbol_lng').value = arbol.longitud;
        document.getElementById('arbol_codigo').value = arbol.codigo_arbol;
        document.getElementById('arbol_estado_salud').value = arbol.estado_salud || 'bueno';
        document.getElementById('arbol_id_plantacion').value = arbol.id_plantacion || '';
        document.getElementById('arbol_especie').value = arbol.especie_variedad || '';
        document.getElementById('arbol_ano').value = arbol.ano_plantacion || '';
        document.getElementById('arbol_notas').value = arbol.notas || '';

        if (arbol.id_plantacion) {
            alSeleccionarPlantacionArbol(arbol.id_plantacion);
        } else {
            document.getElementById('cajaInfoPlantacionArbol').classList.add('d-none');
        }

        new bootstrap.Modal(document.getElementById('modalGuardarArbol')).show();
    }

    function enviarGuardarArbol(e) {
        e.preventDefault();
        const codigo = document.getElementById('arbol_codigo').value.trim();
        if (!codigo) {
            alert("El código del árbol es obligatorio.");
            return;
        }

        const data = {
            id_arbol: document.getElementById('arbol_id').value,
            id_finca: fincaActual.id_finca,
            id_plantacion: document.getElementById('arbol_id_plantacion').value,
            codigo_arbol: codigo,
            estado_salud: document.getElementById('arbol_estado_salud').value,
            especie_variedad: document.getElementById('arbol_especie').value,
            ano_plantacion: document.getElementById('arbol_ano').value,
            notas: document.getElementById('arbol_notas').value,
            latitud: document.getElementById('arbol_lat').value,
            longitud: document.getElementById('arbol_lng').value
        };

        const btn = document.getElementById('btnSubmitArbol');
        btn.disabled = true;

        fetch(`${window.APP_URL}/api/mapas?action=guardar_arbol`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resp => {
            btn.disabled = false;
            if (resp.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalGuardarArbol')).hide();
                cargarDatosFinca(fincaActual.id_finca);
            } else {
                alert(resp.message || 'Error al guardar árbol.');
            }
        });
    }

    function cancelarDibujoArbol() {
        desactivarModoPlantarArbol();
    }

    function eliminarZonaDelimitada(idGeometria) {
        if (!confirm("¿Deseas eliminar la delimitación de esta zona en el mapa?")) return;
        fetch(`${window.APP_URL}/api/mapas?action=borrar_zona`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_geometria: idGeometria })
        }).then(() => {
            bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasDetalle')).hide();
            cargarDatosFinca(fincaActual.id_finca);
        });
    }

    function eliminarArbolIndividual(idArbol) {
        if (!confirm("¿Deseas dar de baja este árbol del mapa?")) return;
        fetch(`${window.APP_URL}/api/mapas?action=borrar_arbol`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_arbol: idArbol })
        }).then(() => {
            bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasDetalle')).hide();
            cargarDatosFinca(fincaActual.id_finca);
        });
    }

    function guardarCentroActual() {
        if (!fincaActual) {
            mostrarToast("Selecciona una finca primero para fijar su vista.", true);
            return;
        }
        const centro = mapa.getCenter();
        const zoom = Math.round(mapa.getZoom());

        const btnPin = document.getElementById('btnFijarVista');
        const iconoOriginal = btnPin ? btnPin.innerHTML : '';
        if (btnPin) {
            btnPin.disabled = true;
            btnPin.innerHTML = '<span class="spinner-border spinner-border-sm text-danger"></span>';
        }

        fetch(`${window.APP_URL}/api/mapas?action=guardar_centro_finca`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_finca: fincaActual.id_finca,
                latitud_centro: centro.lat,
                longitud_centro: centro.lng,
                zoom_mapa: zoom
            })
        })
        .then(res => res.json())
        .then(data => {
            if (btnPin) {
                btnPin.disabled = false;
                btnPin.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
                setTimeout(() => {
                    btnPin.innerHTML = iconoOriginal;
                }, 2000);
            }
            if (data.success) {
                // Actualizar en el objeto de memoria
                fincaActual.latitud_centro = centro.lat;
                fincaActual.longitud_centro = centro.lng;
                fincaActual.zoom_mapa = zoom;

                const badgeZ = document.getElementById('txtZoomActual');
                if (badgeZ) badgeZ.textContent = 'Zoom: ' + zoom;

                mostrarToast(`📌 ¡Vista y Zoom guardados! (Centro: ${centro.lat.toFixed(5)}, ${centro.lng.toFixed(5)} | Zoom ${zoom}). Se mantendrá esta vista exacta al abrir la finca.`, false, 4500);
            } else {
                mostrarToast(data.message || 'Error al fijar la vista.', true);
            }
        })
        .catch(err => {
            if (btnPin) {
                btnPin.disabled = false;
                btnPin.innerHTML = iconoOriginal;
            }
            mostrarToast("Error de conexión al fijar la vista.", true);
        });
    }

    function actualizarSelectoresPlantacion() {
        const selZona = document.getElementById('zona_id_plantacion');
        const selArb = document.getElementById('arbol_id_plantacion');

        selZona.innerHTML = '<option value="">-- Seleccionar cultivo/plantación --</option>';
        selArb.innerHTML = '<option value="">-- Sin vincular a plantación --</option>';

        plantacionesActuales.forEach(p => {
            const etiquetaTipo = p.es_fija_anual ? '🌳 Fija' : '🌱 Temp';
            const label = `[${etiquetaTipo}] ${p.nombre_comun} (${p.identificador_cuadro}${p.variedad ? ' - ' + p.variedad : ''})`;
            selZona.innerHTML += `<option value="${p.id_plantacion}">${label}</option>`;
            selArb.innerHTML += `<option value="${p.id_plantacion}">${label}</option>`;
        });
    }

    /**
     * Carga automáticamente todos los datos del cultivo/plantación en la zona seleccionada
     */
    function alSeleccionarPlantacionZona(idPlantacion) {
        const p = plantacionesActuales.find(item => item.id_plantacion == idPlantacion);
        const caja = document.getElementById('cajaInfoPlantacionZona');
        const inputNombre = document.getElementById('zona_nombre');

        if (p) {
            caja.classList.remove('d-none');
            caja.innerHTML = `
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-leaf text-success me-1"></i>${p.nombre_comun} ${p.variedad ? '(' + p.variedad + ')' : ''}</span>
                    <span class="badge ${p.es_fija_anual ? 'bg-success' : 'bg-primary'}">${p.es_fija_anual ? 'Plantación Fija' : 'De temporada'}</span>
                </div>
                <div class="text-muted" style="font-size:0.75rem;">
                    Cuadro: <strong>${p.identificador_cuadro}</strong> &bull; Campaña: <strong>${p.campana_ano}</strong> &bull; Marco: <strong>${p.marco_plantacion || 'Libre'}</strong>
                </div>
            `;

            // Cargar nombre sugerido si está vacío o por defecto
            if (!inputNombre.value || inputNombre.value.startsWith('Bancal') || inputNombre.value.startsWith('Zona') || inputNombre.value.includes('- Cuadro')) {
                inputNombre.value = `${p.nombre_comun}${p.variedad ? ' ' + p.variedad : ''} - ${p.identificador_cuadro}`;
            }

            // Seleccionar color temático del cultivo automáticamente
            const color = COLORES_CULTIVO[p.tipo_cultivo] || '#10b981';
            seleccionarColorZona(color, null);
        } else {
            caja.classList.add('d-none');
            caja.innerHTML = '';
        }
    }

    /**
     * Carga automáticamente todos los datos del cultivo/plantación en el árbol seleccionado
     */
    function alSeleccionarPlantacionArbol(idPlantacion) {
        const p = plantacionesActuales.find(item => item.id_plantacion == idPlantacion);
        const caja = document.getElementById('cajaInfoPlantacionArbol');
        const inputEspecie = document.getElementById('arbol_especie');
        const inputCodigo = document.getElementById('arbol_codigo');

        if (p) {
            caja.classList.remove('d-none');
            caja.innerHTML = `
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-tree text-success me-1"></i>${p.nombre_comun} ${p.variedad ? '(' + p.variedad + ')' : ''}</span>
                    <span class="badge ${p.es_fija_anual ? 'bg-success' : 'bg-primary'}">${p.es_fija_anual ? 'Arboleda Fija' : 'Temporada'}</span>
                </div>
                <div class="text-muted" style="font-size:0.75rem;">
                    Cuadro: <strong>${p.identificador_cuadro}</strong> &bull; Marco: <strong>${p.marco_plantacion || 'Libre'}</strong> &bull; Campaña: <strong>${p.campana_ano}</strong>
                </div>
            `;

            // Autocargar especie y variedad en el árbol
            inputEspecie.value = `${p.nombre_comun}${p.variedad ? ' ' + p.variedad : ''}`.trim();

            // Código sugerido inteligente con las siglas del cultivo (ej: OLI-001, LIM-005, ALM-012)
            const siglas = (p.nombre_comun || 'ARB').substring(0, 3).toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const numArb = capaArboles.getLayers().length + 1;
            inputCodigo.value = `${siglas}-${String(numArb).padStart(3, '0')}`;

            if (!document.getElementById('arbol_ano').value && p.campana_ano) {
                document.getElementById('arbol_ano').value = p.campana_ano;
            }
        } else {
            caja.classList.add('d-none');
            caja.innerHTML = '';
        }
    }

    /**
     * Rellena el desplegable de objetos con todas las zonas y árboles introducidos en el mapa
     */
    function actualizarSelectorObjetosMapa() {
        const sel = document.getElementById('selectorObjetosMapa');
        if (!sel) return;

        const totalZonas = (objetosMapaMemoria && objetosMapaMemoria.zonas) ? objetosMapaMemoria.zonas.length : 0;
        const totalArboles = (objetosMapaMemoria && objetosMapaMemoria.arboles) ? objetosMapaMemoria.arboles.length : 0;
        const totalTotal = totalZonas + totalArboles;

        sel.innerHTML = `<option value="">📍 Localizar objeto (${totalTotal})</option>`;

        if (totalTotal === 0) {
            sel.innerHTML += `<option value="" disabled>-- Sin zonas ni árboles aún --</option>`;
            return;
        }

        if (totalZonas > 0) {
            const optgroupZ = document.createElement('optgroup');
            optgroupZ.label = `🌱 Zonas y Bancales (${totalZonas})`;
            objetosMapaMemoria.zonas.forEach(z => {
                const opt = document.createElement('option');
                opt.value = `zona_${z.geometria.id_geometria}`;
                const nomCultivo = z.plantacion ? z.plantacion.nombre_comun : 'Zona';
                const cuadro = z.geometria.nombre_zona || (z.plantacion ? z.plantacion.identificador_cuadro : 'Bancal');
                const sup = z.geometria.superficie_m2 ? ` [${Math.round(z.geometria.superficie_m2)} m²]` : '';
                opt.textContent = `🌱 ${nomCultivo} (${cuadro})${sup}`;
                optgroupZ.appendChild(opt);
            });
            sel.appendChild(optgroupZ);
        }

        if (totalArboles > 0) {
            const optgroupA = document.createElement('optgroup');
            optgroupA.label = `🌳 Árboles Individuales (${totalArboles})`;
            objetosMapaMemoria.arboles.forEach(a => {
                const opt = document.createElement('option');
                opt.value = `arbol_${a.arbol.id_arbol}`;
                const esp = a.arbol.especie_variedad || a.arbol.nombre_cultivo || 'Árbol';
                opt.textContent = `🌳 ${a.arbol.codigo_arbol} - ${esp} [${a.arbol.estado_salud.toUpperCase()}]`;
                optgroupA.appendChild(opt);
            });
            sel.appendChild(optgroupA);
        }
    }

    /**
     * Localiza, centra, destaca y abre la ficha del objeto seleccionado en el desplegable
     */
    function localizarObjetoSeleccionado(valor) {
        if (!valor) return;

        if (valor.startsWith('zona_')) {
            const idGeom = valor.replace('zona_', '');
            const obj = objetosMapaMemoria.zonas.find(z => z.geometria.id_geometria == idGeom);
            if (obj && obj.layer) {
                if (obj.layer.getBounds) {
                    mapa.fitBounds(obj.layer.getBounds(), { padding: [50, 50], maxZoom: 21, animate: true });
                }
                if (obj.layer.setStyle) {
                    const estiloOriginal = {
                        color: obj.layer.options.color || '#10b981',
                        weight: obj.layer.options.weight || 3,
                        fillOpacity: obj.layer.options.fillOpacity || 0.4
                    };
                    obj.layer.setStyle({ color: '#f59e0b', weight: 6, fillOpacity: 0.8 });
                    setTimeout(() => {
                        obj.layer.setStyle(estiloOriginal);
                    }, 2500);
                }
                abrirDetalleZona(obj.geometria, obj.plantacion, obj.layer);
                mostrarToast(`🌱 Zona localizada: ${obj.geometria.nombre_zona || (obj.plantacion ? obj.plantacion.nombre_comun : 'Zona')}`, false, 3000);
            }
        } else if (valor.startsWith('arbol_')) {
            const idArbol = valor.replace('arbol_', '');
            const obj = objetosMapaMemoria.arboles.find(a => a.arbol.id_arbol == idArbol);
            if (obj && obj.marcador) {
                const latLng = obj.marcador.getLatLng();
                mapa.setView(latLng, 21, { animate: true });

                const iconoEl = obj.marcador.getElement();
                if (iconoEl) {
                    iconoEl.style.transition = 'transform 0.3s ease, filter 0.3s ease';
                    iconoEl.style.transform = 'scale(2.0)';
                    iconoEl.style.filter = 'drop-shadow(0 0 12px #22c55e)';
                    setTimeout(() => {
                        iconoEl.style.transform = '';
                        iconoEl.style.filter = '';
                    }, 2500);
                }
                abrirDetalleArbol(obj.arbol, obj.marcador);
                mostrarToast(`🌳 Árbol localizado: ${obj.arbol.codigo_arbol}`, false, 3000);
            }
        }
    }

    function actualizarLeyendaCultivos(plantaciones) {
        const lista = document.getElementById('listaLeyendaCultivos');
        const totalBadge = document.getElementById('badgeTotalCultivos');
        totalBadge.textContent = plantaciones.length;

        if (plantaciones.length === 0) {
            lista.innerHTML = 'Sin plantaciones activas en esta finca.';
            return;
        }

        let html = '';
        plantaciones.forEach(p => {
            const color = COLORES_CULTIVO[p.tipo_cultivo] || '#10b981';
            const iconoTipo = p.es_fija_anual ? '<i class="fa-solid fa-tree me-1 text-muted" title="Plantación Fija Anual"></i>' : '<i class="fa-solid fa-seedling me-1 text-muted" title="De temporada"></i>';
            html += `
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="d-flex align-items-center gap-1 text-truncate">
                        <span class="d-inline-block rounded-circle" style="width: 9px; height: 9px; background-color: ${color};"></span>
                        <span class="fw-semibold text-dark text-truncate">${iconoTipo}${p.nombre_comun}</span>
                    </div>
                    <span class="badge bg-light text-dark border">${p.identificador_cuadro}</span>
                </div>
            `;
        });
        lista.innerHTML = html;
    }

    function seleccionarColorZona(color, element) {
        document.getElementById('zona_color_hex').value = color;
        document.querySelectorAll('#paletaColoresZona .color-swatch').forEach(s => s.classList.remove('active'));
        if (element) element.classList.add('active');
    }

    function cambiarExplotacion(idExplotacion) {
        window.location.href = `${window.APP_URL}/mapas?explotacion_id=${idExplotacion}`;
    }

    function cambiarFinca(idFinca) {
        if (idFinca && idFinca !== '0') {
            window.location.href = `${window.APP_URL}/mapas?finca_id=${idFinca}`;
        }
    }

    function alternarPantallaCompleta() {
        const elem = document.getElementById('contenedorMapa');
        const icono = document.getElementById('iconoFullscreen');

        if (!document.fullscreenElement) {
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            }
            icono.classList.remove('fa-expand');
            icono.classList.add('fa-compress');
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
            icono.classList.remove('fa-compress');
            icono.classList.add('fa-expand');
        }

        setTimeout(() => { mapa.invalidateSize(); }, 300);
    }

    function toggleLeyenda() {
        const cuerpo = document.getElementById('cuerpoLeyenda');
        const icono = document.getElementById('iconoToggleLeyenda');
        if (cuerpo.style.display === 'none') {
            cuerpo.style.display = 'block';
            icono.classList.remove('fa-chevron-up');
            icono.classList.add('fa-chevron-down');
        } else {
            cuerpo.style.display = 'none';
            icono.classList.remove('fa-chevron-down');
            icono.classList.add('fa-chevron-up');
        }
    }

    let toastTimeout = null;
    function mostrarToast(msg, esError = false, duracion = 0) {
        const toast = document.getElementById('sigToast');
        const txt = document.getElementById('sigToastMsg');

        txt.innerHTML = (esError ? '<i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>' : '<i class="fa-solid fa-circle-check text-info me-2"></i>') + msg;
        toast.className = `sig-toast alert ${esError ? 'alert-danger' : 'alert-dark'} shadow-lg border-0 py-2 px-3 small d-flex align-items-center justify-content-between rounded-pill`;
        toast.style.display = 'flex';

        if (toastTimeout) clearTimeout(toastTimeout);
        if (duracion > 0) {
            toastTimeout = setTimeout(() => { cerrarToast(); }, duracion);
        }
    }

    function cerrarToast() {
        document.getElementById('sigToast').style.display = 'none';
    }

    let arbolADuplicarActual = null;

    /**
     * Abre el modal de Duplicar / Multi-Copiar marcador de árbol
     */
    function abrirModalDuplicarArbol(arbol) {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasDetalle')).hide();
        arbolADuplicarActual = arbol;

        document.getElementById('dup_id_arbol').value = arbol.id_arbol;
        document.getElementById('dup_codigo_base').value = arbol.codigo_arbol;
        document.getElementById('dup_cantidad').value = 1;
        document.getElementById('dup_distancia').value = 6;

        document.getElementById('modalDuplicarTitle').innerHTML = `<i class="fa-solid fa-copy me-2"></i>Duplicar Árbol: ${arbol.codigo_arbol}`;

        const caja = document.getElementById('cajaInfoDuplicarArbol');
        caja.innerHTML = `
            <div><strong>Árbol origen:</strong> <span class="badge bg-dark">${arbol.codigo_arbol}</span> &bull; <strong>Especie:</strong> ${arbol.especie_variedad || arbol.nombre_cultivo || 'Árbol'}</div>
            <div class="text-muted mt-1" style="font-size:0.75rem;">Salud: <strong>${(arbol.estado_salud || 'bueno').toUpperCase()}</strong> | Año: <strong>${arbol.ano_plantacion || '-'}</strong> | Portainjerto: <strong>${arbol.patron_portainjerto || 'Estándar'}</strong></div>
        `;

        actualizarPrevisualizacionCopias();
        new bootstrap.Modal(document.getElementById('modalDuplicarArbol')).show();
    }

    function fijarCantidadCopias(num) {
        document.getElementById('dup_cantidad').value = num;
        actualizarPrevisualizacionCopias();
    }

    function actualizarPrevisualizacionCopias() {
        if (!arbolADuplicarActual) return;
        const codOrig = arbolADuplicarActual.codigo_arbol;
        const cant = parseInt(document.getElementById('dup_cantidad').value) || 1;

        let prefijo = 'ARB';
        let numBase = 1;
        const m = codOrig.match(/^(.*?)[-_](\d+)$/);
        if (m) {
            prefijo = m[1];
            numBase = parseInt(m[2]);
        } else {
            prefijo = codOrig;
        }

        // Buscar el número más alto existente en capaArboles con ese prefijo
        let maxNum = numBase;
        capaArboles.eachLayer(layer => {
            if (layer._datosArbol && layer._datosArbol.codigo_arbol) {
                const c = layer._datosArbol.codigo_arbol;
                const m2 = c.match(/[-_](\d+)$/);
                if (m2 && c.startsWith(prefijo)) {
                    const v = parseInt(m2[1]);
                    if (v > maxNum) maxNum = v;
                }
            }
        });

        const codigos = [];
        const limitMostrar = Math.min(cant, 5);
        for (let i = 1; i <= limitMostrar; i++) {
            codigos.push(`${prefijo}-${String(maxNum + i).padStart(3, '0')}`);
        }

        const resto = cant - limitMostrar;
        let texto = codigos.join(', ');
        if (resto > 0) {
            texto += `, ... y ${resto} más (hasta ${prefijo}-${String(maxNum + cant).padStart(3, '0')})`;
        }

        document.getElementById('txtPrevisualizacionCodigos').textContent = texto;
    }

    function enviarDuplicarArbol(e) {
        e.preventDefault();
        const idArbol = document.getElementById('dup_id_arbol').value;
        const cant = parseInt(document.getElementById('dup_cantidad').value) || 1;
        const dist = parseFloat(document.getElementById('dup_distancia').value) || 6.0;

        const btn = document.getElementById('btnSubmitDuplicar');
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Generando ${cant} copias...`;

        fetch(`${window.APP_URL}/api/mapas?action=duplicar_arbol`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_arbol: idArbol,
                cantidad: cant,
                distancia_metros: dist
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = `<i class="fa-solid fa-copy me-1"></i> Generar Copias`;

            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalDuplicarArbol')).hide();
                mostrarToast(data.message + " Arrástralos para ajustar su posición sobre la foto aérea.", false, 5000);
                cargarDatosFinca(fincaActual.id_finca);
            } else {
                alert(data.message || 'Error al duplicar árbol.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = `<i class="fa-solid fa-copy me-1"></i> Generar Copias`;
            alert('Error de conexión al duplicar árbol.');
        });
    }
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
