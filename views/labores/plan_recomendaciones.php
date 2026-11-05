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
$filtroPeriodo = isset($_GET['periodo']) ? trim($_GET['periodo']) : 'todo';
$filtroFecha = isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
$filtroVista = isset($_GET['vista']) ? trim($_GET['vista']) : 'detallada';

// Detección automática de época por fecha o momento actual
$mesActivo = null;
$fechaDeducidaTexto = '';
if (!empty($filtroFecha)) {
    $timeFecha = strtotime($filtroFecha);
    if ($timeFecha) {
        $mesActivo = (int)date('n', $timeFecha);
        $fechaDeducidaTexto = date('d/m/Y', $timeFecha);
    }
} elseif ($filtroPeriodo === 'actual') {
    $mesActivo = (int)date('n');
    $fechaDeducidaTexto = 'Fecha actual (' . date('d/m/Y') . ')';
}

if ($mesActivo !== null) {
    if (in_array($mesActivo, array(12, 1, 2))) {
        $filtroPeriodo = 'invierno';
    } elseif (in_array($mesActivo, array(3, 4, 5))) {
        $filtroPeriodo = 'primavera';
    } elseif (in_array($mesActivo, array(6, 7, 8))) {
        $filtroPeriodo = 'verano';
    } elseif (in_array($mesActivo, array(9, 10, 11))) {
        $filtroPeriodo = 'otono';
    }
}

// Títulos y etiquetas de periodo
$periodoEtiquetas = array(
    'todo'      => 'Todo el Año (Campaña Completa)',
    'invierno'  => 'Invierno (Diciembre - Febrero)',
    'primavera' => 'Primavera (Marzo - Mayo)',
    'verano'    => 'Verano (Junio - Agosto)',
    'otono'     => 'Otoño (Septiembre - Noviembre)'
);
$nombrePeriodoActivo = isset($periodoEtiquetas[$filtroPeriodo]) ? $periodoEtiquetas[$filtroPeriodo] : 'Todo el Año';

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
            $reportTitle = ($filtroVista === 'resumida' ? 'Resumen Ejecutivo: ' : '') . 'Plan Oficial de Tratamientos y Labores - ' . ($filtroPeriodo !== 'todo' ? $nombrePeriodoActivo . ' - ' : '') . ($propietario ? $propietario['nombre_razon_social'] : APP_NAME);
            $reportTargetId = 'documentoPlanLaboresImprimible';
            $reportFilename = 'plan_' . ($filtroVista === 'resumida' ? 'resumido_' : '') . $filtroPeriodo . '_' . date('Y-m-d') . '.pdf';
            require VIEWS_PATH . '/layouts/toolbar_informes.php';
            ?>
            <a href="<?= APP_URL ?>/maquinaria" class="btn btn-outline-secondary shadow-sm" title="Ver Inventario de Herramientas">
                <i class="fa-solid fa-wrench me-1 text-warning"></i> Inventario Herramientas
            </a>
        </div>
    </div>

    <!-- Barra de Filtros Interactivos (No Imprimible) -->
    <div class="card card-custom p-3 mb-4 bg-light border-0 shadow-sm no-print">
        <form action="<?= APP_URL ?>/plan-labores" method="GET" class="row g-2 align-items-end">
            <!-- Fila 1: Filtros de Ámbito y Tipo -->
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-solid fa-draw-polygon me-1 text-primary"></i>Finca / Explotación:</label>
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
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-solid fa-seedling me-1 text-success"></i>Cultivo / Especie:</label>
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
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-solid fa-list-check me-1 text-info"></i>Labor Específica:</label>
                <select name="tipo_labor" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="todas" <?= $filtroLabor === 'todas' ? 'selected' : '' ?>>Todas las Labores</option>
                    <option value="tratamientos" <?= $filtroLabor === 'tratamientos' ? 'selected' : '' ?>>🛡️ Solo Tratamientos Fitosanitarios</option>
                    <option value="suelo" <?= $filtroLabor === 'suelo' ? 'selected' : '' ?>>🚜 Solo Laboreo y Desbroce</option>
                    <option value="podas" <?= $filtroLabor === 'podas' ? 'selected' : '' ?>>✂️ Solo Podas y Biomasa</option>
                    <option value="riegos" <?= $filtroLabor === 'riegos' ? 'selected' : '' ?>>💧 Solo Riegos y Nutrición</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-solid fa-leaf me-1 text-success"></i>Régimen Agronómico:</label>
                <select name="regimen" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    <option value="ecologico" <?= $filtroRegimen === 'ecologico' ? 'selected' : '' ?>>🌱 Ecológico</option>
                    <option value="convencional" <?= $filtroRegimen === 'convencional' ? 'selected' : '' ?>>🌾 Convencional</option>
                </select>
            </div>

            <!-- Fila 2: Filtros de Época / Fecha y Modo de Visualización -->
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-regular fa-calendar-days me-1 text-warning"></i>Época del Año / Periodo:</label>
                <select name="periodo" class="form-select form-select-sm fw-semibold text-primary" onchange="this.form.submit()">
                    <option value="todo" <?= $filtroPeriodo === 'todo' && empty($filtroFecha) ? 'selected' : '' ?>>📅 Campaña Completa (Todo el Año)</option>
                    <option value="actual" <?= $filtroPeriodo === 'actual' ? 'selected' : '' ?>>⏱️ Momento Actual (<?= date('d/m/Y') ?>)</option>
                    <option value="invierno" <?= $filtroPeriodo === 'invierno' ? 'selected' : '' ?>>❄️ Invierno (Diciembre - Febrero)</option>
                    <option value="primavera" <?= $filtroPeriodo === 'primavera' ? 'selected' : '' ?>>🌸 Primavera (Marzo - Mayo)</option>
                    <option value="verano" <?= $filtroPeriodo === 'verano' ? 'selected' : '' ?>>☀️ Verano (Junio - Agosto)</option>
                    <option value="otono" <?= $filtroPeriodo === 'otono' ? 'selected' : '' ?>>🍂 Otoño (Septiembre - Noviembre)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-solid fa-calendar-day me-1 text-secondary"></i>Fecha Específica del Año:</label>
                <input type="date" name="fecha" value="<?= htmlspecialchars($filtroFecha) ?>" class="form-control form-control-sm" onchange="this.form.submit()" title="Filtrar por fecha exacta">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-solid fa-table-cells-large me-1 text-primary"></i>Modo de Visualización:</label>
                <div class="btn-group w-100 btn-group-sm" role="group">
                    <button type="submit" name="vista" value="detallada" class="btn <?= $filtroVista !== 'resumida' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fa-solid fa-file-lines me-1"></i>Detallada
                    </button>
                    <button type="submit" name="vista" value="resumida" class="btn <?= $filtroVista === 'resumida' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fa-solid fa-table-list me-1"></i>Resumida
                    </button>
                </div>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold" title="Aplicar Filtros">
                    <i class="fa-solid fa-filter me-1"></i> Filtrar
                </button>
                <a href="<?= APP_URL ?>/plan-labores" class="btn btn-sm btn-outline-secondary" title="Restablecer Filtros">
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

        <!-- Aviso y Distintivo de Época y Modo de Informe -->
        <div class="alert alert-light border border-primary border-opacity-25 shadow-sm p-3 mb-4 rounded-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary fs-6 px-3 py-2">
                    <i class="fa-solid fa-calendar-check me-1"></i><?= htmlspecialchars($nombrePeriodoActivo) ?>
                </span>
                <?php if (!empty($fechaDeducidaTexto)): ?>
                    <span class="badge bg-light text-primary border font-monospace">
                        <i class="fa-regular fa-clock me-1"></i>Referencia: <?= htmlspecialchars($fechaDeducidaTexto) ?>
                    </span>
                <?php endif; ?>
                <span class="text-muted small">
                    <?= $filtroPeriodo !== 'todo' ? 'Mostrando exclusivamente los tratamientos fitosanitarios y quehaceres de este periodo.' : 'Mostrando la programación fitotécnica completa del ciclo anual.' ?>
                </span>
            </div>
            <div>
                <?php if ($filtroVista === 'resumida'): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 fw-bold">
                        <i class="fa-solid fa-table-list me-1"></i>Modo Resumido Ejecutivo
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border py-2 px-3">
                        <i class="fa-solid fa-file-lines me-1"></i>Modo Detallado por Plantación
                    </span>
                <?php endif; ?>
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
        <?php elseif ($filtroVista === 'resumida'): ?>

            <!-- ========================================== -->
            <!-- VISTA RESUMIDA: TABLA EJECUTIVA CONSOLIDADA -->
            <!-- ========================================== -->
            <div class="card card-custom mb-4 border shadow-sm">
                <div class="card-header bg-light py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary fs-6 px-3 py-2">
                            <i class="fa-solid fa-clipboard-check me-1"></i>Resumen Ejecutivo de Labores y Tratamientos
                        </span>
                        <span class="fw-bold text-dark fs-6">
                            <?= count($plantaciones) ?> Plantaciones Seleccionadas
                        </span>
                    </div>
                    <div class="small font-monospace text-muted">
                        <strong>Periodo:</strong> <?= htmlspecialchars($nombrePeriodoActivo) ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0 small">
                        <thead class="table-light text-center align-middle">
                            <tr>
                                <th style="width: 14%;">Finca / Paraje y Cuadro</th>
                                <th style="width: 14%;">Cultivo & Régimen</th>
                                <th style="width: 12%;">Época / Fase</th>
                                <th style="width: 28%;">Tratamiento Fitosanitario y Dosis</th>
                                <th style="width: 22%;">Quehaceres Clave (Suelo, Poda, Riego)</th>
                                <th style="width: 10%;" class="no-print">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plantaciones as $pl): ?>
                                <?php
                                $esEco = (bool)$pl['es_ecologico'];
                                $nombreCultivo = $pl['nombre_comun'] . ($pl['variedad'] ? ' (' . $pl['variedad'] . ')' : '');
                                $herramientaTrat = obtenerHerramientasRecomendadas($herramientasDisponibles, 'tratamiento');
                                $herramientaSuelo = obtenerHerramientasRecomendadas($herramientasDisponibles, 'laboreo');
                                $herramientaPoda = obtenerHerramientasRecomendadas($herramientasDisponibles, 'poda');

                                // Definir periodos a iterar según filtro
                                $periodosIterar = ($filtroPeriodo === 'todo') ? array('invierno', 'primavera', 'verano', 'otono') : array($filtroPeriodo);
                                ?>

                                <?php foreach ($periodosIterar as $pKey): ?>
                                    <tr>
                                        <!-- Finca y Cuadro -->
                                        <td>
                                            <div class="fw-bold text-primary">
                                                <i class="fa-solid fa-draw-polygon me-1"></i><?= htmlspecialchars($pl['nombre_finca']) ?>
                                            </div>
                                            <div class="font-monospace text-dark fw-semibold">
                                                Cuadro: <?= htmlspecialchars($pl['identificador_cuadro']) ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                Sup: <?= number_format($pl['superficie_ocupada_ha'], 2, ',', '.') ?> ha
                                                <?php if (!empty($pl['poligono']) || !empty($pl['parcela'])): ?>
                                                    | Pol/Parc: <?= htmlspecialchars($pl['poligono'] . '/' . $pl['parcela']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Cultivo y Régimen -->
                                        <td>
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($nombreCultivo) ?>
                                            </div>
                                            <?php if ($esEco): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 mt-1">
                                                    <i class="fa-solid fa-leaf me-1"></i>Ecológico (UE)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border mt-1">
                                                    <i class="fa-solid fa-wheat-awn me-1"></i>Convencional
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Época / Fase -->
                                        <td class="text-center">
                                            <?php if ($pKey === 'invierno'): ?>
                                                <span class="badge bg-primary text-white p-2 w-100">
                                                    <i class="fa-regular fa-snowflake me-1"></i>Invierno
                                                </span>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">Parada invernal</div>
                                            <?php elseif ($pKey === 'primavera'): ?>
                                                <span class="badge bg-success text-white p-2 w-100">
                                                    <i class="fa-solid fa-leaf me-1"></i>Primavera
                                                </span>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">Brotación y Cuajado</div>
                                            <?php elseif ($pKey === 'verano'): ?>
                                                <span class="badge bg-danger text-white p-2 w-100">
                                                    <i class="fa-solid fa-sun me-1"></i>Verano
                                                </span>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">Engorde y Cosecha</div>
                                            <?php elseif ($pKey === 'otono'): ?>
                                                <span class="badge bg-warning text-dark p-2 w-100">
                                                    <i class="fa-solid fa-wheat-awn me-1"></i>Otoño
                                                </span>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">Post-Cosecha y Caída</div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Tratamientos Fitosanitarios y Dosis -->
                                        <td>
                                            <?php if ($pKey === 'invierno'): ?>
                                                <div class="fw-semibold text-primary mb-1">
                                                    <i class="fa-solid fa-shield-virus me-1"></i>Cicatrización post-poda y Cochinilla
                                                </div>
                                                <div class="small">
                                                    <?= $esEco ? '<strong>Oxicloruro de Cobre 50%</strong> (300 g/hl) + <strong>Aceite de Parafina 79%</strong> (1 l/hl)' : '<strong>Oxicloruro de Cobre 50% WP</strong> o Caldo Bordelés (400 g/hl) + Aceite mineral' ?>
                                                </div>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                                    <strong>Caldo:</strong> 800-1000 L/ha | <strong>P. Seg.:</strong> 15 días | <strong>Apero:</strong> <?= htmlspecialchars($herramientaTrat) ?>
                                                </div>

                                            <?php elseif ($pKey === 'primavera'): ?>
                                                <div class="fw-semibold text-success mb-1">
                                                    <i class="fa-solid fa-shield-virus me-1"></i>Abolladura, Oídio, Pulgones y Cuajado
                                                </div>
                                                <div class="small">
                                                    <?= $esEco ? '<strong>Azufre Mojable 80%</strong> (250 g/hl) + <strong>Jabón Potásico</strong> (1-1.5%). En post-floración: <strong>Bacillus thuringiensis</strong> (1 kg/ha)' : '<strong>Difenoconazol 25%</strong> (20-30 cc/hl) + <strong>Acetamiprid 20%</strong> (35-50 g/hl) + Spinosad en cuajado' ?>
                                                </div>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                                    <strong>Caldo:</strong> 600-800 L/ha | <strong>P. Seg.:</strong> 3-14 días (prohibido insecticida en plena floración)
                                                </div>

                                            <?php elseif ($pKey === 'verano'): ?>
                                                <div class="fw-semibold text-danger mb-1">
                                                    <i class="fa-solid fa-shield-virus me-1"></i>Mosca de la fruta (*Ceratitis*) y Podredumbres
                                                </div>
                                                <div class="small">
                                                    <?= $esEco ? 'Trampeo masivo con mosqueros + <strong>Spinosad cebo</strong> a parches + <strong>Sulfato Potásico</strong> foliar (400 g/hl)' : 'Trampeo mosca + <strong>Fludioxonil/Ciprodinil</strong> contra monilia/botritis + Nitrato Potásico' ?>
                                                </div>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                                    <strong>Caldo:</strong> 500-800 L/ha | <strong>P. Seg.:</strong> 3-7 días (respetar rigurosamente pre-cosecha)
                                                </div>

                                            <?php elseif ($pKey === 'otono'): ?>
                                                <div class="fw-semibold text-warning mb-1">
                                                    <i class="fa-solid fa-shield-virus me-1"></i>Desinfección de heridas y Caída de hoja
                                                </div>
                                                <div class="small">
                                                    <?= $esEco ? '<strong>Oxicloruro de Cobre 50%</strong> (300-400 g/hl) al 50% y 100% de caída de hojas contra bacterias' : 'Tratamiento cúprico de otoño + Abono foliar de recuperación con Zinc y Boro' ?>
                                                </div>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                                    <strong>Caldo:</strong> 800-1000 L/ha | <strong>P. Seg.:</strong> 14 días | Previene entrada de chancros
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Quehaceres Clave del Periodo -->
                                        <td>
                                            <?php if ($pKey === 'invierno'): ?>
                                                <div><strong>🚜 Suelo:</strong> Reposo; evitar laboreo con terreno húmedo.</div>
                                                <div><strong>✂️ Poda:</strong> Poda invernal de regeneración; sellado con pasta cúprica y triturado *in situ*.</div>
                                                <div><strong>💧 Riego:</strong> Purga de goteros y parada de riego.</div>
                                            <?php elseif ($pKey === 'primavera'): ?>
                                                <div><strong>🚜 Suelo:</strong> Desbroce mecánico de cubierta vegetal entre calles antes de floración.</div>
                                                <div><strong>✂️ Poda:</strong> Poda en verde y deschuponado de brotes no deseados.</div>
                                                <div><strong>💧 Riego:</strong> Inicio de fertirrigación con aporte nitrogenado moderado.</div>
                                            <?php elseif ($pKey === 'verano'): ?>
                                                <div><strong>🚜 Suelo:</strong> Ruedo limpio y acolchado de restos triturados (*mulching*).</div>
                                                <div><strong>✂️ Poda:</strong> Aclareo manual de frutos para regular carga y calibre.</div>
                                                <div><strong>💧 Riego:</strong> Riego continuado/interdiario por goteo con refuerzo potásico.</div>
                                            <?php elseif ($pKey === 'otono'): ?>
                                                <div><strong>🚜 Suelo:</strong> Pase superficial de cultivador (10-12 cm) post-cosecha.</div>
                                                <div><strong>✂️ Poda:</strong> Higiene: retirada de frutos momificados y ramas dañadas.</div>
                                                <div><strong>💧 Riego:</strong> Reducción gradual y preparación para el reposo invernal.</div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Acciones Directas -->
                                        <td class="text-center no-print">
                                            <a href="<?= APP_URL ?>/tratamientos?plantacion_id=<?= $pl['id_plantacion'] ?>" class="btn btn-outline-danger btn-sm py-1 px-2 w-100 mb-1" title="Registrar Tratamiento">
                                                <i class="fa-solid fa-spray-can me-1"></i>Tratar
                                            </a>
                                            <a href="<?= APP_URL ?>/labores?finca_id=<?= $pl['id_finca'] ?>&plantacion_id=<?= $pl['id_plantacion'] ?>" class="btn btn-outline-secondary btn-sm py-1 px-2 w-100" title="Registrar Labor">
                                                <i class="fa-solid fa-plus me-1"></i>Labor
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>

            <!-- ============================================== -->
            <!-- VISTA DETALLADA POR PLANTACIÓN (FICHAS TÉCNICAS) -->
            <!-- ============================================== -->
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
                                <div><em class="fst-italic"><?= htmlspecialchars($pl['nombre_cientifico'] ?: '-') ?></em></div>
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
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'invierno'): ?>
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
                                            <?php endif; ?>

                                            <!-- Fase 2: Brotación y Prefloración -->
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'primavera'): ?>
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
                                            <?php endif; ?>

                                            <!-- Fase 4: Engorde y Maduración -->
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'verano'): ?>
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
                                            <?php endif; ?>

                                            <!-- Fase 5: Otoño (Post-Cosecha y Cicatrización Foliar) -->
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'otono'): ?>
                                                <tr>
                                                    <td class="fw-semibold text-warning">
                                                        <i class="fa-solid fa-wheat-awn me-1"></i>Otoño Post-Cosecha<br>
                                                        <span class="text-muted fw-normal">(Sept - Nov)</span>
                                                    </td>
                                                    <td>Cicatrización de heridas de abscisión, chancros, bacteriosis y repilo de otoño.</td>
                                                    <td>
                                                        <?php if ($esEco): ?>
                                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">ECO</span>
                                                            <strong>Oxicloruro de Cobre 50%</strong> (300-400 g/hl) aplicado al 50% y 100% de caída de hojas.
                                                        <?php else: ?>
                                                            Tratamiento cúprico de otoño + Abono foliar de recuperación con Zinc y Boro para reservas.
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div><strong>Dosis caldo:</strong> 800-1000 L/ha</div>
                                                        <div><strong>P. Seg.:</strong> 14 días</div>
                                                        <div class="small text-muted"><i class="fa-solid fa-tractor me-1"></i><?= htmlspecialchars($herramientaTratamiento) ?></div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
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
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'invierno'): ?>
                                                <li><strong>Invierno:</strong> Reposo del suelo; evitar labores profundas con suelo mojado para preservar la estructura.</li>
                                            <?php endif; ?>
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'primavera'): ?>
                                                <li><strong>Primavera:</strong> Desbroce mecánico de cubierta vegetal entre calles antes de floración para evitar competencia hídrica con el cultivo.</li>
                                            <?php endif; ?>
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'verano'): ?>
                                                <li><strong>Verano:</strong> Mantener ruedo limpio; dejar restos triturados sobre el terreno como acolchado (*mulching*) para reducir evaporación.</li>
                                            <?php endif; ?>
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'otono'): ?>
                                                <li><strong>Otoño (Post-cosecha):</strong> Pase superficial de cultivador (10-12 cm) para descompactar tras el tráfico de cosechas y facilitar la penetración de lluvias.</li>
                                            <?php endif; ?>
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
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'invierno'): ?>
                                                <li><strong>Poda Invernal (Ene-Feb):</strong> Aclareo de ramas secas, chupones y apertura de copas para maximizar iluminación y ventilación.</li>
                                                <li><strong>Higiene Sanitaria:</strong> Desinfección de hojas de corte con alcohol de 70º entre árboles. Sellado con pasta cicatrizante cúprica en cortes gruesos (>3 cm).</li>
                                                <li><strong>Gestión de Restos:</strong> Triturado *in situ* e incorporación al suelo para aportar materia orgánica y fijar carbono (evitar quemas).</li>
                                            <?php endif; ?>
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'primavera'): ?>
                                                <li><strong>Poda en Verde (Mayo):</strong> Deschuponado precoz de brotes no deseados en tronco y ramas principales para vigorizar los frutos cuajados.</li>
                                            <?php endif; ?>
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'verano'): ?>
                                                <li><strong>Aclareo Estival:</strong> Aclareo manual de frutos mal formados o apiñados para conseguir calibres comerciales uniformes.</li>
                                            <?php endif; ?>
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'otono'): ?>
                                                <li><strong>Limpieza Post-Cosecha:</strong> Retirada sanitaria de ramas quebradas durante la recolección y momias de frutos secos.</li>
                                            <?php endif; ?>
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
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'invierno'): ?>
                                                <li><strong>Invierno:</strong> Reposo del sistema; revisión y purga de filtros y laterales de goteo.</li>
                                            <?php endif; ?>
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'primavera'): ?>
                                                <li><strong>Primavera:</strong> Inicio moderado de riego por goteo según balance hídrico; aportes de nitrógeno fraccionado.</li>
                                            <?php endif; ?>
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'verano'): ?>
                                                <li><strong>Verano:</strong> Refuerzo diario o interdiario por goteo en engorde; concentrar potasio y calcio para consistencia de fruto.</li>
                                            <?php endif; ?>
                                            <?php if ($filtroPeriodo === 'todo' || $filtroPeriodo === 'otono'): ?>
                                                <li><strong>Otoño:</strong> Reducción progresiva de dotación hídrica tras la cosecha para maduración de madera antes de las heladas.</li>
                                            <?php endif; ?>
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
