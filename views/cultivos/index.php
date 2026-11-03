<?php
$pageTitle = "Catálogo Botánico de Cultivos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$mensaje = '';
$error = '';

// PROCESAR ACCIONES CRUD (EDITAR, BORRAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'editar_cultivo') {
        $idCultivo = (int)$_POST['id_cultivo'];
        $nombreComun = trim(isset($_POST['nombre_comun']) ? $_POST['nombre_comun'] : '');
        $nombreCientifico = trim(isset($_POST['nombre_cientifico']) ? $_POST['nombre_cientifico'] : '');
        $variedad = trim(isset($_POST['variedad']) ? $_POST['variedad'] : '');
        $diasCiclo = !empty($_POST['dias_ciclo_aprox']) ? (int)$_POST['dias_ciclo_aprox'] : null;
        $tipoCultivo = isset($_POST['tipo_cultivo']) ? $_POST['tipo_cultivo'] : 'frutal';
        $urlConsulta = trim(isset($_POST['url_consulta_botanica']) ? $_POST['url_consulta_botanica'] : '');
        $requerimientos = trim(isset($_POST['requerimientos_tecnicos']) ? $_POST['requerimientos_tecnicos'] : '');
        $esEcologico = isset($_POST['es_ecologico']) ? (int)$_POST['es_ecologico'] : 0;
        $descripcion = trim(isset($_POST['descripcion']) ? $_POST['descripcion'] : '');
        $formatoSiembra = trim(isset($_POST['formato_siembra']) ? $_POST['formato_siembra'] : '');
        $epocaSiembra = trim(isset($_POST['epoca_siembra']) ? $_POST['epoca_siembra'] : '');
        $epocaTrasplante = trim(isset($_POST['epoca_trasplante']) ? $_POST['epoca_trasplante'] : '');
        $epocaRecoleccion = trim(isset($_POST['epoca_recoleccion']) ? $_POST['epoca_recoleccion'] : '');
        $tratamientosRecomendados = trim(isset($_POST['tratamientos_recomendados']) ? $_POST['tratamientos_recomendados'] : '');

        if (empty($nombreComun)) {
            $error = 'El nombre común del cultivo es obligatorio.';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE cultivos
                    SET nombre_comun = :comun, nombre_cientifico = :cien, variedad = :var,
                        dias_ciclo_aprox = :dias, tipo_cultivo = :tipo, url_consulta_botanica = :url,
                        requerimientos_tecnicos = :req, es_ecologico = :eco, descripcion = :desc,
                        formato_siembra = :f_siem, epoca_siembra = :e_siem, epoca_trasplante = :e_tras,
                        epoca_recoleccion = :e_reco, tratamientos_recomendados = :trat
                    WHERE id_cultivo = :id
                ");
                $stmt->execute(array(
                    ':comun'   => $nombreComun,
                    ':cien'    => $nombreCientifico,
                    ':var'     => $variedad,
                    ':dias'    => $diasCiclo,
                    ':tipo'    => $tipoCultivo,
                    ':url'     => $urlConsulta,
                    ':req'     => $requerimientos,
                    ':eco'     => $esEcologico,
                    ':desc'    => $descripcion,
                    ':f_siem'  => $formatoSiembra,
                    ':e_siem'  => $epocaSiembra,
                    ':e_tras'  => $epocaTrasplante,
                    ':e_reco'  => $epocaRecoleccion,
                    ':trat'    => $tratamientosRecomendados,
                    ':id'      => $idCultivo
                ));
                $mensaje = "Cultivo <strong>" . htmlspecialchars($nombreComun) . "</strong> actualizado correctamente.";
            } catch (Exception $e) {
                $error = 'Error al actualizar cultivo: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'borrar_cultivo') {
        $idCultivo = (int)$_POST['id_cultivo'];
        try {
            // Protección Estricta GEMINI.md: Borrado lógico siempre (activo = 0)
            $stmtDes = $db->prepare("UPDATE cultivos SET activo = 0 WHERE id_cultivo = :id");
            $stmtDes->execute(array(':id' => $idCultivo));
            $mensaje = "Cultivo descatalogado y archivado correctamente (borrado lógico seguro).";
        } catch (Exception $e) {
            $error = 'Error al eliminar cultivo: ' . $e->getMessage();
        }
    }
}

// Filtros
$filtroTipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$filtroTxt = isset($_GET['q']) ? trim($_GET['q']) : '';

$sqlCultivos = "
    SELECT c.*,
           (SELECT COUNT(*) FROM plantaciones p WHERE p.id_cultivo = c.id_cultivo) AS total_plantaciones
    FROM cultivos c
    WHERE c.activo = 1
";
$params = array();

if (!empty($filtroTipo)) {
    $sqlCultivos .= " AND c.tipo_cultivo = :tipo";
    $params[':tipo'] = $filtroTipo;
}

if (!empty($filtroTxt)) {
    $sqlCultivos .= " AND (c.nombre_comun LIKE :txt OR c.nombre_cientifico LIKE :txt OR c.variedad LIKE :txt)";
    $params[':txt'] = '%' . $filtroTxt . '%';
}

$sqlCultivos .= " ORDER BY c.nombre_comun ASC";
$stmtC = $db->prepare($sqlCultivos);
$stmtC->execute($params);
$cultivos = $stmtC->fetchAll();
?>

<div class="container py-4">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-dna fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Catálogo Botánico de Cultivos</h2>
                <p class="text-muted mb-0 small">Maestro de especies, variedades botánicas, ciclos vegetativos y consulta agronómica técnica</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/cultivos/nuevo" class="btn btn-warning fw-bold text-dark shadow-sm">
                <i class="fa-solid fa-wand-magic-sparkles me-1 text-primary"></i> Asistente IA Botánico
            </a>
            <a href="<?= APP_URL ?>/cultivos/nuevo" class="btn btn-ag shadow-sm">
                <i class="fa-solid fa-plus-circle me-1"></i> Añadir Cultivo
            </a>
        </div>
    </div>

    <!-- Alertas -->
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm">
        <form action="<?= APP_URL ?>/cultivos" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar especie, nombre científico o variedad..." value="<?= htmlspecialchars($filtroTxt) ?>">
            </div>
            <div class="col-md-4">
                <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los tipos de cultivo --</option>
                    <option value="frutal" <?= $filtroTipo === 'frutal' ? 'selected' : '' ?>>Frutal (Hueso / Pepita)</option>
                    <option value="citrico" <?= $filtroTipo === 'citrico' ? 'selected' : '' ?>>Cítrico</option>
                    <option value="olivar" <?= $filtroTipo === 'olivar' ? 'selected' : '' ?>>Olivar</option>
                    <option value="vinedo" <?= $filtroTipo === 'vinedo' ? 'selected' : '' ?>>Viñedo</option>
                    <option value="horticola" <?= $filtroTipo === 'horticola' ? 'selected' : '' ?>>Hortícola</option>
                    <option value="cereal" <?= $filtroTipo === 'cereal' ? 'selected' : '' ?>>Cereal</option>
                    <option value="otro" <?= $filtroTipo === 'otro' ? 'selected' : '' ?>>Otro</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Filtrar</button>
                <?php if ($filtroTxt || $filtroTipo): ?>
                    <a href="<?= APP_URL ?>/cultivos" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Listado Maestro de Cultivos (Tabla con CRUD) -->
    <div class="card card-ag shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-list-check me-2"></i>Especies y Variedades Registradas (<?= count($cultivos) ?>)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th class="ps-4">Cultivo / Especie</th>
                        <th>Nombre Científico</th>
                        <th>Variedad</th>
                        <th>Tipo</th>
                        <th>Ciclo Estimado</th>
                        <th>Plantaciones</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cultivos)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-seedling fa-2x mb-3 text-secondary"></i>
                                <p class="mb-0">No se encontraron cultivos en el catálogo.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cultivos as $c): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-leaf text-success"></i>
                                        <?= htmlspecialchars($c['nombre_comun']) ?>
                                        <?php if (!empty($c['es_ecologico'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace" style="font-size:0.68rem;" title="Manejo 100% Ecológico">
                                                <i class="fa-solid fa-seedling me-1"></i>Ecológico / Bio
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted font-monospace">ID #<?= $c['id_cultivo'] ?></div>
                                </td>
                                <td>
                                    <?php if ($c['nombre_cientifico']): ?>
                                        <span class="fst-italic fw-semibold text-secondary"><?= htmlspecialchars($c['nombre_cientifico']) ?></span>
                                        <a href="https://www.google.com/search?q=<?= urlencode($c['nombre_cientifico'] . ' cultivo agronomia') ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-1 py-0 px-2 rounded-pill" title="Buscar ficha técnica en Google">
                                            <i class="fa-solid fa-magnifying-glass" style="font-size:0.65rem;"></i>
                                        </a>
                                        <?php if ($c['url_consulta_botanica']): ?>
                                            <a href="<?= htmlspecialchars($c['url_consulta_botanica']) ?>" target="_blank" class="btn btn-sm btn-outline-info ms-1 py-0 px-2 rounded-pill" title="Wiki directa">
                                                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.65rem;"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($c['variedad'] ?: 'Estándar') ?></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle text-capitalize">
                                        <?= htmlspecialchars($c['tipo_cultivo']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $c['dias_ciclo_aprox'] ? '<span class="fw-semibold">' . $c['dias_ciclo_aprox'] . ' días</span>' : '<span class="text-muted small">No especif.</span>' ?>
                                </td>
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 rounded-pill">
                                        <?= $c['total_plantaciones'] ?> activas
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Ficha Técnica y Cuidados IA -->
                                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalFichaCultivo_<?= $c['id_cultivo'] ?>" title="Ver Ficha Agronómica y Cuidados">
                                            <i class="fa-solid fa-clipboard-check"></i>
                                        </button>
                                        <!-- Modificar -->
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarCultivo_<?= $c['id_cultivo'] ?>" title="Modificar cultivo">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Borrar Lógico -->
                                        <form action="<?= APP_URL ?>/cultivos" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas descatalogar el cultivo \'<?= htmlspecialchars(addslashes($c['nombre_comun'])) ?>\'?');">
                                            <input type="hidden" name="action" value="borrar_cultivo">
                                            <input type="hidden" name="id_cultivo" value="<?= $c['id_cultivo'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Descatalogar (Borrado Lógico)">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Ver Ficha Técnica y Cuidados -->
                                    <div class="modal fade text-start" id="modalFichaCultivo_<?= $c['id_cultivo'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="fa-solid fa-seedling fs-5 text-warning"></i>
                                                        <h5 class="modal-title fw-bold mb-0">Ficha Agronómica: <?= htmlspecialchars($c['nombre_comun']) ?></h5>
                                                        <?php if (!empty($c['es_ecologico'])): ?>
                                                            <span class="badge bg-success ms-2"><i class="fa-solid fa-leaf me-1"></i>Ecológico</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6">
                                                            <div class="p-3 bg-light rounded-3">
                                                                <span class="text-muted small d-block">Nombre Científico</span>
                                                                <strong class="fst-italic text-primary"><?= htmlspecialchars($c['nombre_cientifico'] ?: 'No registrado') ?></strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="p-3 bg-light rounded-3">
                                                                <span class="text-muted small d-block">Variedad</span>
                                                                <strong class="text-dark"><?= htmlspecialchars($c['variedad'] ?: 'Estándar') ?></strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="p-3 bg-light rounded-3">
                                                                <span class="text-muted small d-block">Ciclo Aproximado</span>
                                                                <strong class="text-dark"><?= $c['dias_ciclo_aprox'] ? $c['dias_ciclo_aprox'] . ' días' : 'No definido' ?></strong>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($c['descripcion'])): ?>
                                                        <div class="mb-3">
                                                            <h6 class="fw-bold text-primary mb-1"><i class="fa-solid fa-align-left me-1"></i>Descripción del Cultivo</h6>
                                                            <p class="text-muted small mb-0 p-2 bg-light rounded"><?= nl2br(htmlspecialchars($c['descripcion'])) ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Calendario Agronómico -->
                                                    <div class="card border-0 bg-light mb-3">
                                                        <div class="card-body p-3">
                                                            <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-calendar-days text-primary me-2"></i>Calendario de Cultivo</h6>
                                                            <div class="row g-2 text-center">
                                                                <div class="col-md-4">
                                                                    <div class="p-2 bg-white rounded border">
                                                                        <span class="text-muted small d-block">Época de Siembra</span>
                                                                        <span class="fw-bold text-primary small"><?= htmlspecialchars($c['epoca_siembra'] ?: 'No especificada') ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="p-2 bg-white rounded border">
                                                                        <span class="text-muted small d-block">Época de Trasplante</span>
                                                                        <span class="fw-bold text-info small"><?= htmlspecialchars($c['epoca_trasplante'] ?: 'Directa / N/A') ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="p-2 bg-white rounded border">
                                                                        <span class="text-muted small d-block">Época de Recolección</span>
                                                                        <span class="fw-bold text-success small"><?= htmlspecialchars($c['epoca_recoleccion'] ?: 'No especificada') ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($c['formato_siembra'])): ?>
                                                        <div class="mb-3 p-3 bg-light rounded-3">
                                                            <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-ruler-combined text-primary me-2"></i>Formato y Marco de Siembra</h6>
                                                            <p class="text-muted small mb-0"><?= htmlspecialchars($c['formato_siembra']) ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($c['tratamientos_recomendados'])): ?>
                                                        <div class="mb-3 p-3 <?= !empty($c['es_ecologico']) ? 'bg-success-subtle border border-success-subtle' : 'bg-light' ?> rounded-3">
                                                            <h6 class="fw-bold <?= !empty($c['es_ecologico']) ? 'text-success' : 'text-primary' ?> mb-1">
                                                                <i class="fa-solid <?= !empty($c['es_ecologico']) ? 'fa-leaf' : 'fa-spray-can' ?> me-2"></i>
                                                                Tratamientos y Cuidados Recomendados <?= !empty($c['es_ecologico']) ? '(Protocolo Ecológico)' : '' ?>
                                                            </h6>
                                                            <div class="small <?= !empty($c['es_ecologico']) ? 'text-success-emphasis' : 'text-muted' ?>">
                                                                <?= nl2br(htmlspecialchars($c['tratamientos_recomendados'])) ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($c['requerimientos_tecnicos'])): ?>
                                                        <div class="mb-2 p-3 bg-light rounded-3">
                                                            <h6 class="fw-bold text-dark small mb-1"><i class="fa-solid fa-circle-info text-info me-1"></i>Requerimientos Técnicos y de Riego</h6>
                                                            <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($c['requerimientos_tecnicos'])) ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditarCultivo_<?= $c['id_cultivo'] ?>">
                                                        <i class="fa-solid fa-pen-to-square me-1"></i> Modificar Datos
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Modificar Cultivo -->
                                    <div class="modal fade text-start" id="modalEditarCultivo_<?= $c['id_cultivo'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Cultivo: <?= htmlspecialchars($c['nombre_comun']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="<?= APP_URL ?>/cultivos" method="POST">
                                                    <input type="hidden" name="action" value="editar_cultivo">
                                                    <input type="hidden" name="id_cultivo" value="<?= $c['id_cultivo'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Nombre Común *</label>
                                                                <input type="text" name="nombre_comun" class="form-control" value="<?= htmlspecialchars($c['nombre_comun']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Nombre Científico</label>
                                                                <input type="text" name="nombre_cientifico" class="form-control" value="<?= htmlspecialchars($c['nombre_cientifico'] ?: '') ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Variedad</label>
                                                                <input type="text" name="variedad" class="form-control" value="<?= htmlspecialchars($c['variedad'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Tipo de Cultivo</label>
                                                                <select name="tipo_cultivo" class="form-select">
                                                                    <option value="frutal" <?= $c['tipo_cultivo'] === 'frutal' ? 'selected' : '' ?>>Frutal (Hueso/Pepita)</option>
                                                                    <option value="citrico" <?= $c['tipo_cultivo'] === 'citrico' ? 'selected' : '' ?>>Cítrico</option>
                                                                    <option value="olivar" <?= $c['tipo_cultivo'] === 'olivar' ? 'selected' : '' ?>>Olivar</option>
                                                                    <option value="vinedo" <?= $c['tipo_cultivo'] === 'vinedo' ? 'selected' : '' ?>>Viñedo</option>
                                                                    <option value="horticola" <?= $c['tipo_cultivo'] === 'horticola' ? 'selected' : '' ?>>Hortícola</option>
                                                                    <option value="cereal" <?= $c['tipo_cultivo'] === 'cereal' ? 'selected' : '' ?>>Cereal</option>
                                                                    <option value="otro" <?= $c['tipo_cultivo'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Ciclo Aprox. (Días)</label>
                                                                <input type="number" name="dias_ciclo_aprox" class="form-control" value="<?= $c['dias_ciclo_aprox'] ?: '' ?>">
                                                            </div>
                                                        </div>

                                                        <div class="form-check form-switch mb-3 p-3 bg-light rounded-3 ms-0">
                                                            <input class="form-check-input ms-0 me-3" type="checkbox" name="es_ecologico" value="1" id="edit_eco_<?= $c['id_cultivo'] ?>" <?= !empty($c['es_ecologico']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label fw-bold text-success" for="edit_eco_<?= $c['id_cultivo'] ?>">
                                                                <i class="fa-solid fa-leaf me-1"></i> Cultivo con Manejo Ecológico / Biológico Certificado
                                                            </label>
                                                            <div class="form-text small">Adapta las pautas agronómicas y fitosanitarias a normativas ecológicas sin síntesis química.</div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Descripción del Cultivo</label>
                                                            <textarea name="descripcion" class="form-control" rows="2"><?= htmlspecialchars(isset($c['descripcion']) ? $c['descripcion'] : '') ?></textarea>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Formato y Marco de Siembra</label>
                                                                <input type="text" name="formato_siembra" class="form-control" value="<?= htmlspecialchars(isset($c['formato_siembra']) ? $c['formato_siembra'] : '') ?>" placeholder="Ej: 0.8m entre líneas x 0.4m entre plantas">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">URL Enlace Botánico / Wiki</label>
                                                                <input type="url" name="url_consulta_botanica" class="form-control" value="<?= htmlspecialchars($c['url_consulta_botanica'] ?: '') ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Época de Siembra</label>
                                                                <input type="text" name="epoca_siembra" class="form-control" value="<?= htmlspecialchars(isset($c['epoca_siembra']) ? $c['epoca_siembra'] : '') ?>" placeholder="Ej: Febrero - Abril">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Época de Trasplante</label>
                                                                <input type="text" name="epoca_trasplante" class="form-control" value="<?= htmlspecialchars(isset($c['epoca_trasplante']) ? $c['epoca_trasplante'] : '') ?>" placeholder="Ej: Marzo - Mayo">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Época de Recolección</label>
                                                                <input type="text" name="epoca_recoleccion" class="form-control" value="<?= htmlspecialchars(isset($c['epoca_recoleccion']) ? $c['epoca_recoleccion'] : '') ?>" placeholder="Ej: Junio - Septiembre">
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Tratamientos Recomendados y Periodicidad</label>
                                                            <textarea name="tratamientos_recomendados" class="form-control" rows="3" placeholder="Pautas de abonado y sanidad vegetal..."><?= htmlspecialchars(isset($c['tratamientos_recomendados']) ? $c['tratamientos_recomendados'] : '') ?></textarea>
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold small">Requerimientos Agronómicos y Riego</label>
                                                            <textarea name="requerimientos_tecnicos" class="form-control" rows="2"><?= htmlspecialchars($c['requerimientos_tecnicos'] ?: '') ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-ag">
                                                            <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
