<?php
$pageTitle = "Plan Oficial de Tratamientos y Labores de Campo - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();

// Datos del Propietario / Explotación activa
$propietario = null;
if ($tenantId) {
    $stmtProp = $db->prepare("SELECT * FROM propietarios WHERE id_propietario = :id LIMIT 1");
    $stmtProp->execute(array(':id' => (int)$tenantId));
    $propietario = $stmtProp->fetch();
}
if (!$propietario) {
    $propietario = $db->query("SELECT * FROM propietarios LIMIT 1")->fetch();
}

// Filtros
$filtroFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$filtroCultivo = isset($_GET['cultivo_id']) ? (int)$_GET['cultivo_id'] : 0;
$filtroLabor = isset($_GET['tipo_labor']) ? trim($_GET['tipo_labor']) : 'todas';
$filtroRegimen = isset($_GET['regimen']) ? trim($_GET['regimen']) : '';

// Catálogo de Fincas y Cultivos para los selectores de filtro
$sqlFincas = "SELECT id_finca, nombre_finca, superficie_ha FROM fincas WHERE activo = 1";
if ($tenantId) {
    $sqlFincas .= " AND (id_propietario = " . (int)$tenantId . " OR id_propietario IS NULL)";
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$fincasList = $db->query($sqlFincas)->fetchAll();

$cultivosList = $db->query("SELECT id_cultivo, nombre_comun, variedad, es_ecologico FROM cultivos WHERE activo = 1 ORDER BY nombre_comun ASC")->fetchAll();

// Herramientas y Maquinaria disponible en la explotación
$sqlHerramientas = "SELECT id_herramienta, nombre, tipo, categoria, marca, modelo FROM maquinaria_herramientas WHERE activo = 1";
if ($tenantId) {
    $sqlHerramientas .= " AND (id_propietario = " . (int)$tenantId . " OR id_propietario IS NULL)";
}
$sqlHerramientas .= " ORDER BY tipo ASC, nombre ASC";
$stmtH = $db->query($sqlHerramientas);
$herramientasDisponibles = $stmtH ? $stmtH->fetchAll() : array();

// Consulta de Plantaciones y Cultivos Activos
$sqlPl = "
    SELECT p.*, f.nombre_finca, f.poligono, f.parcela, f.superficie_ha as finca_total_ha,
           c.nombre_comun, c.nombre_cientifico, c.variedad, c.tipo_cultivo, c.es_ecologico,
           c.descripcion, c.formato_siembra, c.epoca_siembra, c.epoca_trasplante,
           c.epoca_recoleccion, c.tratamientos_recomendados, c.requerimientos_tecnicos,
           c.dias_ciclo_aprox
    FROM plantaciones p
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE (p.activo = 1 OR p.activo IS NULL)
      AND (f.activo = 1 OR f.activo IS NULL)
      AND p.estado != 'arrancado'
";
$params = array();

if ($tenantId) {
    $sqlPl .= " AND (f.id_propietario = :tenant OR f.id_propietario IS NULL)";
    $params[':tenant'] = (int)$tenantId;
}
if ($filtroFinca > 0) {
    $sqlPl .= " AND p.id_finca = :finca";
    $params[':finca'] = $filtroFinca;
}
if ($filtroCultivo > 0) {
    $sqlPl .= " AND p.id_cultivo = :cultivo";
    $params[':cultivo'] = $filtroCultivo;
}
if ($filtroRegimen === 'ecologico') {
    $sqlPl .= " AND c.es_ecologico = 1";
} elseif ($filtroRegimen === 'convencional') {
    $sqlPl .= " AND (c.es_ecologico = 0 OR c.es_ecologico IS NULL)";
}

$sqlPl .= " ORDER BY f.nombre_finca ASC, p.identificador_cuadro ASC";
$stmtPl = $db->prepare($sqlPl);
$stmtPl->execute($params);
$plantaciones = $stmtPl->fetchAll();

// Función auxiliar para sugerir herramientas del inventario por categoría
function obtenerHerramientasRecomendadas($herramientas, $categoriaBuscada) {
    $coincidentes = array();
    foreach ($herramientas as $h) {
        $cat = strtolower($h['categoria'] . ' ' . $h['tipo'] . ' ' . $h['nombre']);
        if (stripos($cat, $categoriaBuscada) !== false) {
            $coincidentes[] = $h['nombre'];
        }
    }
    return !empty($coincidentes) ? implode(', ', array_slice($coincidentes, 0, 3)) : 'Herramienta agrícola estándar';
}
?>

<div class="container py-4">

    <!-- Cabecera Oficial y Toolbar Universal A4 -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3 no-print">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-clipboard-list fs-2 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Plan Oficial de Tratamientos y Labores de Campo</h2>
                <p class="text-muted mb-0 small">Guía técnica personalizada según los cultivos y plantaciones activos con calendario, dosis y herramientas</p>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php
            $reportTitle = 'Plan Oficial de Tratamientos Fitosanitarios y Labores de Campo - ' . ($propietario ? $propietario['nombre_razon_social'] : APP_NAME);
            $reportTargetId = 'documentoPlanLaboresImprimible';
            $reportFilename = 'plan_tratamientos_labores_' . date('Y-m-d') . '.pdf';
            require VIEWS_PATH . '/layouts/toolbar_informes.php';
            ?>
            <a href="<?= APP_URL ?>/maquinaria" class="btn btn-outline-secondary shadow-sm" title="Ver Inventario de Herramientas">
                <i class="fa-solid fa-wrench me-1 text-warning"></i> Inventario Herramientas
            </a>
        </div>
    </div>

    <!-- Barra de Filtros Interactivos (No Imprimible) -->
    <div class="card card-custom p-3 mb-4 bg-light border-0 shadow-sm no-print">
        <form action="<?= APP_URL ?>/plan-labores" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1 text-muted">Finca:</label>
                <select name="finca_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Todas las Fincas --</option>
                    <?php foreach ($fincasList as $fl): ?>
                        <option value="<?= $fl['id_finca'] ?>" <?= ($filtroFinca === (int)$fl['id_finca']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fl['nombre_finca']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1 text-muted">Cultivo / Especie:</label>
                <select name="cultivo_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Todos los Cultivos --</option>
                    <?php foreach ($cultivosList as $cl): ?>
                        <option value="<?= $cl['id_cultivo'] ?>" <?= ($filtroCultivo === (int)$cl['id_cultivo']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['nombre_comun'] . ($cl['variedad'] ? ' (' . $cl['variedad'] . ')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1 text-muted">Labor Específica:</label>
                <select name="tipo_labor" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="todas" <?= $filtroLabor === 'todas' ? 'selected' : '' ?>>Todas las Labores</option>
                    <option value="tratamientos" <?= $filtroLabor === 'tratamientos' ? 'selected' : '' ?>>🛡️ Solo Tratamientos Fitosanitarios</option>
                    <option value="suelo" <?= $filtroLabor === 'suelo' ? 'selected' : '' ?>>🚜 Solo Laboreo y Desbroce</option>
                    <option value="podas" <?= $filtroLabor === 'podas' ? 'selected' : '' ?>>✂️ Solo Podas y Biomasa</option>
                    <option value="riegos" <?= $filtroLabor === 'riegos' ? 'selected' : '' ?>>💧 Solo Riegos y Nutrición</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1 text-muted">Régimen:</label>
                <select name="regimen" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    <option value="ecologico" <?= $filtroRegimen === 'ecologico' ? 'selected' : '' ?>>🌱 Ecológico</option>
                    <option value="convencional" <?= $filtroRegimen === 'convencional' ? 'selected' : '' ?>>🌾 Convencional</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end pt-3">
                <a href="<?= APP_URL ?>/plan-labores" class="btn btn-sm btn-outline-secondary w-100" title="Restablecer Filtros">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- DOCUMENTO OFICIAL A4 IMPRIMIBLE -->
    <div id="documentoPlanLaboresImprimible">

        <!-- Encabezado Oficial del Documento / Cuaderno de Campo -->
        <div class="card card-custom p-4 mb-4 border shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($propietario['logo'])): ?>
                            <img src="<?= APP_URL ?>/uploads/logos/<?= htmlspecialchars($propietario['logo']) ?>" alt="Logo" style="max-height: 55px;" class="rounded border">
                        <?php endif; ?>
                        <div>
                            <h4 class="fw-bold mb-0 text-primary">
                                <?= htmlspecialchars($propietario ? $propietario['nombre_razon_social'] : 'Explotación Agrícola') ?>
                            </h4>
                            <div class="small text-muted">
                                <strong>NIF/CIF:</strong> <?= htmlspecialchars($propietario['cif_nif'] ?: 'Sin NIF') ?> | 
                                <strong>Municipio:</strong> Moratalla (Murcia) | 
                                <strong>Campaña:</strong> <?= date('Y') ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 p-2 px-3">
                        <i class="fa-solid fa-file-signature me-1"></i> Documento Oficial de Planificación
                    </div>
                    <div class="small text-muted mt-1">
                        <strong>Fecha de Emisión:</strong> <?= date('d/m/Y') ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($plantaciones)): ?>
            <div class="card card-custom p-5 text-center shadow-sm">
                <i class="fa-solid fa-wheat-awn-circle-exclamation text-muted fs-1 mb-3 opacity-25"></i>
                <h5 class="fw-bold text-muted">No hay plantaciones activas con los filtros seleccionados</h5>
                <p class="small text-muted mb-3">Registra cuadros y plantaciones en tus fincas para generar automáticamente su plan de labores.</p>
                <div>
                    <a href="<?= APP_URL ?>/plantaciones/nueva" class="btn btn-ag btn-sm">
                        <i class="fa-solid fa-plus me-1"></i> Añadir Plantación
                    </a>
                </div>
            </div>
        <?php else: ?>

            <?php foreach ($plantaciones as $idx => $pl): ?>
                <?php
                $esEco = (bool)$pl['es_ecologico'];
                $nombreCultivo = $pl['nombre_comun'] . ($pl['variedad'] ? ' (' . $pl['variedad'] . ')' : '');
                $tipoC = strtolower($pl['tipo_cultivo'] ?: 'frutal');

                // Detección del tipo de cultivo para recomendaciones específicas
                $esFrutal = in_array($tipoC, array('frutal', 'leñoso', 'citrico', 'olivar', 'vinedo'));
                $esHorticola = in_array($tipoC, array('horticola', 'cereal', 'otro'));

                // Herramientas recomendadas del inventario del usuario
                $herramientaTratamiento = obtenerHerramientasRecomendadas($herramientasDisponibles, 'tratamiento');
                $herramientaSuelo = obtenerHerramientasRecomendadas($herramientasDisponibles, 'laboreo');
                $herramientaPoda = obtenerHerramientasRecomendadas($herramientasDisponibles, 'poda');
                $herramientaCosecha = obtenerHerramientasRecomendadas($herramientasDisponibles, 'cosecha');
                ?>

                <!-- Ficha del Plan por Plantación y Cultivo -->
                <div class="card card-custom mb-4 border shadow-sm" style="page-break-inside: avoid;">
                    
                    <!-- Encabezado de la Finca y Cuadro -->
                    <div class="card-header bg-light py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary fs-6 px-3 py-2">
                                <i class="fa-solid fa-seedling me-1"></i><?= htmlspecialchars($nombreCultivo) ?>
                            </span>
                            <span class="fw-bold text-dark fs-6">
                                <i class="fa-solid fa-draw-polygon me-1 text-primary"></i><?= htmlspecialchars($pl['nombre_finca']) ?>
                            </span>
                            <span class="badge bg-light text-primary border font-monospace">
                                Cuadro: <?= htmlspecialchars($pl['identificador_cuadro']) ?>
                            </span>
                            <?php if ($esEco): ?>
                                <span class="badge bg-success text-white">
                                    <i class="fa-solid fa-leaf me-1"></i>Cultivo Ecológico (Reg. UE 2018/848)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary text-white">
                                    <i class="fa-solid fa-wheat-awn me-1"></i>Producción Convencional
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="small font-monospace text-muted">
                            <strong>Sup:</strong> <?= number_format($pl['superficie_ocupada_ha'], 2, ',', '.') ?> ha | 
                            <strong>Pol/Parc:</strong> <?= htmlspecialchars($pl['poligono'] . '/' . $pl['parcela']) ?> | 
                            <strong>Campaña:</strong> <?= $pl['campana_ano'] ?>
                        </div>
                    </div>

                    <div class="card-body p-4">

                        <!-- Resumen Agronómico del Catálogo -->
                        <div class="row g-2 mb-3 p-3 bg-light rounded-3 border small">
                            <div class="col-md-3">
                                <strong><i class="fa-regular fa-calendar-plus text-primary me-1"></i>Siembra/Plantación:</strong>
                                <div><?= htmlspecialchars($pl['epoca_siembra'] ?: ($pl['fecha_siembra'] ? date('d/m/Y', strtotime($pl['fecha_siembra'])) : 'Primavera / Otoño')) ?></div>
                            </div>
                            <div class="col-md-3">
                                <strong><i class="fa-solid fa-arrows-split-up-and-left text-info me-1"></i>Marco / Trasplante:</strong>
                                <div><?= htmlspecialchars($pl['marco_plantacion'] ?: ($pl['formato_siembra'] ?: 'Marco regular')) ?></div>
                            </div>
                            <div class="col-md-3">
                                <strong><i class="fa-solid fa-basket-shopping text-success me-1"></i>Cosecha Estimada:</strong>
                                <div><?= htmlspecialchars($pl['epoca_recoleccion'] ?: ($pl['fecha_estimada_fin'] ? date('d/m/Y', strtotime($pl['fecha_estimada_fin'])) : 'Verano / Otoño')) ?></div>
                            </div>
                            <div class="col-md-3">
                                <strong><i class="fa-solid fa-dna text-secondary me-1"></i>Nombre Científico:</strong>
                                <div class="fst-italic"><?= htmlspecialchars($pl['nombre_cientifico'] ?: '-') ?></div>
                            </div>
                        </div>

                        <!-- 1. TRATAMIENTOS FITOSANITARIOS Y NUTRICIONALES -->
                        <?php if ($filtroLabor === 'todas' || $filtroLabor === 'tratamientos'): ?>
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                    <h6 class="fw-bold text-danger mb-0">
                                        <i class="fa-solid fa-shield-virus me-2"></i>1. Calendario de Tratamientos Fitosanitarios y Nutrición Foliar
                                    </h6>
                                    <a href="<?= APP_URL ?>/tratamientos?plantacion_id=<?= $pl['id_plantacion'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2 no-print" title="Registrar Tratamiento">
                                        <i class="fa-solid fa-plus me-1"></i>Registrar Labor
                                    </a>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0 small">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 18%;">Momento / Época</th>
                                                <th style="width: 25%;">Plagas / Objetivos</th>
                                                <th style="width: 35%;">Productos y Materias Activas Recomendadas</th>
                                                <th style="width: 22%;">Dosis / Plazo Seg. / Maquinaria</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Fase 1: Parada Invernal -->
                                            <tr>
                                                <td class="fw-semibold text-primary">
                                                    <i class="fa-regular fa-snowflake me-1"></i>Parada Invernal<br>
                                                    <span class="text-muted fw-normal">(Dic - Feb)</span>
                                                </td>
                                                <td>Cicatrización post-poda, chancros bacterianos, cochinilla, huevos invernantes de araña.</td>
                                                <td>
                                                    <?php if ($esEco): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">ECO</span>
                                                        <strong>Oxicloruro de Cobre 50%</strong> (300 g/hl) + <strong>Aceite de Parafina 79%</strong> (1 l/hl).
                                                    <?php else: ?>
                                                        <strong>Oxicloruro de Cobre 50% WP</strong> o Caldo Bordelés (400 g/hl) + Aceite mineral insecticida.
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><strong>Dosis caldo:</strong> 800-1000 L/ha</div>
                                                    <div><strong>P. Seg.:</strong> 15 días</div>
                                                    <div class="small text-muted"><i class="fa-solid fa-tractor me-1"></i><?= htmlspecialchars($herramientaTratamiento) ?></div>
                                                </td>
                                            </tr>

                                            <!-- Fase 2: Brotación y Prefloración -->
                                            <tr>
                                                <td class="fw-semibold text-success">
                                                    <i class="fa-solid fa-leaf me-1"></i>Brotación / Yemas<br>
                                                    <span class="text-muted fw-normal">(Marzo - Abril)</span>
                                                </td>
                                                <td>Abolladura/Lepra, oídio, monilia, pulgones (*Myzus persicae*).</td>
                                                <td>
                                                    <?php if ($esEco): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">ECO</span>
                                                        <strong>Azufre Mojable 80%</strong> (250 g/hl) + <strong>Jabón Potásico</strong> (1-1.5%) + Extracto de Neem.
                                                    <?php else: ?>
                                                        <strong>Difenoconazol 25%</strong> (20-30 cc/hl) + <strong>Acetamiprid 20%</strong> (35-50 g/hl).
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><strong>Dosis caldo:</strong> 600-800 L/ha</div>
                                                    <div><strong>P. Seg.:</strong> 7-14 días</div>
                                                    <div class="small text-muted"><i class="fa-solid fa-spray-can me-1"></i>Pulverización fina</div>
                                                </td>
                                            </tr>

                                            <!-- Fase 3: Post-Floración y Cuajado -->
                                            <tr>
                                                <td class="fw-semibold text-warning">
                                                    <i class="fa-solid fa-certificate me-1"></i>Post-Floración<br>
                                                    <span class="text-muted fw-normal">(Abril - Mayo)</span>
                                                </td>
                                                <td>
                                                    Cuajado óptimo de frutos, orugas defoliadoras, trips.
                                                    <div class="text-danger fw-semibold small mt-1">⚠️ Prohibido insecticida con flor abierta.</div>
                                                </td>
                                                <td>
                                                    <?php if ($esEco): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">ECO</span>
                                                        <strong>Bacillus thuringiensis kurstaki</strong> (1 kg/ha) + Bioestimulante (Aminoácidos con Boro y Molibdeno).
                                                    <?php else: ?>
                                                        Bioestimulante cuajado + <strong>Spinosad</strong> o Spinetoram contra trips/oruga.
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><strong>Dosis caldo:</strong> 800-1000 L/ha</div>
                                                    <div><strong>P. Seg.:</strong> 3 días</div>
                                                    <div class="small text-muted"><i class="fa-solid fa-wrench me-1"></i>Atomizador con boquillas cerámicas</div>
                                                </td>
                                            </tr>

                                            <!-- Fase 4: Engorde y Maduración -->
                                            <tr>
                                                <td class="fw-semibold text-danger">
                                                    <i class="fa-solid fa-apple-whole me-1"></i>Engorde y Cosecha<br>
                                                    <span class="text-muted fw-normal">(Junio - Agosto)</span>
                                                </td>
                                                <td>Mosca de la fruta (*Ceratitis capitata*), podredumbres (Monilia/Botritis), engorde y calibre.</td>
                                                <td>
                                                    <?php if ($esEco): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">ECO</span>
                                                        Trampeo masivo con mosqueros + <strong>Spinosad cebo</strong> a parches + <strong>Sulfato Potásico</strong> foliar (400 g/hl).
                                                    <?php else: ?>
                                                        Trampeo mosca + <strong>Fludioxonil/Ciprodinil</strong> contra botritis/monilia + Nitrato Potásico foliar.
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><strong>Dosis caldo:</strong> 500-800 L/ha</div>
                                                    <div><strong>P. Seg.:</strong> 3-7 días (respetar pre-cosecha)</div>
                                                    <div class="small text-muted"><i class="fa-solid fa-shield me-1"></i>Revisión de trampas semanal</div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <!-- 2. LABOREO DEL SUELO Y DESBROCE -->
                            <?php if ($filtroLabor === 'todas' || $filtroLabor === 'suelo'): ?>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 bg-light h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                            <h6 class="fw-bold text-secondary mb-0">
                                                <i class="fa-solid fa-mountain-sun me-1"></i>2. Laboreo del Suelo y Control de Malezas
                                            </h6>
                                            <a href="<?= APP_URL ?>/labores?finca_id=<?= $pl['id_finca'] ?>&plantacion_id=<?= $pl['id_plantacion'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2 no-print">
                                                <i class="fa-solid fa-plus me-1"></i>Registrar
                                            </a>
                                        </div>
                                        <ul class="small mb-2 ps-3">
                                            <li><strong>Otoño (Post-cosecha):</strong> Pase superficial de cultivador (10-12 cm) para descompactar tras el tráfico de cosechas y facilitar la penetración de lluvias.</li>
                                            <li><strong>Primavera:</strong> Desbroce mecánico de cubierta vegetal entre calles antes de floración para evitar competencia hídrica con el cultivo.</li>
                                            <li><strong>Verano:</strong> Mantener ruedo limpio; dejar restos triturados sobre el terreno como acolchado (*mulching*) para reducir evaporación.</li>
                                        </ul>
                                        <div class="p-2 bg-white rounded border small mt-2">
                                            <strong><i class="fa-solid fa-tractor me-1 text-primary"></i>Herramienta / Apero asignado:</strong><br>
                                            <span class="text-muted"><?= htmlspecialchars($herramientaSuelo) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- 3. PODAS Y VALORIZACIÓN DE BIOMASA -->
                            <?php if ($filtroLabor === 'todas' || $filtroLabor === 'podas'): ?>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 bg-light h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                            <h6 class="fw-bold text-success mb-0">
                                                <i class="fa-solid fa-scissors me-1"></i>3. Podas y Gestión de Biomasa
                                            </h6>
                                            <a href="<?= APP_URL ?>/podas?plantacion_id=<?= $pl['id_plantacion'] ?>" class="btn btn-sm btn-outline-success py-0 px-2 no-print">
                                                <i class="fa-solid fa-plus me-1"></i>Registrar
                                            </a>
                                        </div>
                                        <ul class="small mb-2 ps-3">
                                            <li><strong>Poda Invernal (Ene-Feb):</strong> Aclareo de ramas secas, chupones y apertura de copas para maximizar iluminación y ventilación.</li>
                                            <li><strong>Higiene Sanitaria:</strong> Desinfección de hojas de corte con alcohol de 70º entre árboles. Sellado con pasta cicatrizante cúprica en cortes gruesos (>3 cm).</li>
                                            <li><strong>Gestión de Restos:</strong> Triturado *in situ* e incorporación al suelo para aportar materia orgánica y fijar carbono (evitar quemas).</li>
                                        </ul>
                                        <div class="p-2 bg-white rounded border small mt-2">
                                            <strong><i class="fa-solid fa-scissors me-1 text-success"></i>Herramientas asignadas:</strong><br>
                                            <span class="text-muted"><?= htmlspecialchars($herramientaPoda) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 4. RIEGOS Y RECOLECCIÓN -->
                        <?php if ($filtroLabor === 'todas' || $filtroLabor === 'riegos'): ?>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 bg-light h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                            <h6 class="fw-bold text-primary mb-0">
                                                <i class="fa-solid fa-gauge-high me-1"></i>4. Estrategia de Riego y Fertirrigación
                                            </h6>
                                            <a href="<?= APP_URL ?>/riegos?finca_id=<?= $pl['id_finca'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2 no-print">
                                                <i class="fa-solid fa-plus me-1"></i>Registrar
                                            </a>
                                        </div>
                                        <ul class="small mb-0 ps-3">
                                            <li><strong>Origen de Agua:</strong> Coordinación de tanda comunitaria (tablón/parada oficial) y almacenamiento en depósito/balsa de apoyo.</li>
                                            <li><strong>Frecuencia:</strong> Riego moderado en brotación, refuerzo diario/interdiario por goteo en engorde de fruto (Junio-Julio), y reducción previa a cosecha.</li>
                                            <li><strong>Fertirrigación:</strong> Fraccionar aportes de nitrógeno en primavera temprana y concentrar potasio y calcio durante el llenado de fruto.</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 bg-light h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                            <h6 class="fw-bold text-info mb-0">
                                                <i class="fa-solid fa-scale-balanced me-1"></i>5. Estimación de Cosecha y Logística
                                            </h6>
                                            <a href="<?= APP_URL ?>/recolecciones?plantacion_id=<?= $pl['id_plantacion'] ?>" class="btn btn-sm btn-outline-info py-0 px-2 no-print">
                                                <i class="fa-solid fa-plus me-1"></i>Registrar
                                            </a>
                                        </div>
                                        <ul class="small mb-0 ps-3">
                                            <li><strong>Ventana de Cosecha:</strong> <?= htmlspecialchars($pl['epoca_recoleccion'] ?: 'Verano / Otoño') ?>.</li>
                                            <li><strong>Control de Madurez:</strong> Verificar dureza de pulpa, grados Brix mínimos y coloración uniforme antes del corte.</li>
                                            <li><strong>Equipos de Cosecha:</strong> Tijeras de vendimia curva, carretillas de transporte, cajas homologadas y remolque basculante.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pautas Específicas / Observaciones Registradas en el Catálogo -->
                        <?php if (!empty($pl['requerimientos_tecnicos']) || !empty($pl['tratamientos_recomendados'])): ?>
                            <div class="mt-3 p-3 bg-white border border-primary border-opacity-25 rounded-3 small">
                                <div class="fw-bold text-primary mb-1">
                                    <i class="fa-solid fa-book-open me-1"></i>Notas Técnicas Particulares del Cultivo (Catálogo Botánico):
                                </div>
                                <div class="text-dark">
                                    <?= nl2br(htmlspecialchars($pl['requerimientos_tecnicos'] ?: $pl['tratamientos_recomendados'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pie Oficial del Documento / Firmas Técnicas -->
            <div class="card card-custom p-4 mt-4 border shadow-sm" style="page-break-inside: avoid;">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="text-muted small fw-semibold text-uppercase mb-4">El Titular de la Explotación</div>
                        <div class="border-top pt-2 mx-auto" style="max-width: 260px;">
                            <strong><?= htmlspecialchars($propietario ? $propietario['nombre_razon_social'] : 'Titular') ?></strong><br>
                            <span class="small text-muted">Firma y Conformidad</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small fw-semibold text-uppercase mb-4">Asesor Técnico Agrícola / Cuaderno</div>
                        <div class="border-top pt-2 mx-auto" style="max-width: 260px;">
                            <strong><?= htmlspecialchars($currentUser['nombre_completo']) ?></strong><br>
                            <span class="small text-muted">Responsable Técnico de Campo</span>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
