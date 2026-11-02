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

        if (empty($nombreComun)) {
            $error = 'El nombre común del cultivo es obligatorio.';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE cultivos
                    SET nombre_comun = :comun, nombre_cientifico = :cien, variedad = :var,
                        dias_ciclo_aprox = :dias, tipo_cultivo = :tipo, url_consulta_botanica = :url,
                        requerimientos_tecnicos = :req
                    WHERE id_cultivo = :id
                ");
                $stmt->execute(array(
                    ':comun' => $nombreComun,
                    ':cien'  => $nombreCientifico,
                    ':var'   => $variedad,
                    ':dias'  => $diasCiclo,
                    ':tipo'  => $tipoCultivo,
                    ':url'   => $urlConsulta,
                    ':req'   => $requerimientos,
                    ':id'    => $idCultivo
                ));
                $mensaje = "Cultivo <strong>" . htmlspecialchars($nombreComun) . "</strong> actualizado correctamente.";
            } catch (Exception $e) {
                $error = 'Error al actualizar cultivo: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'borrar_cultivo') {
        $idCultivo = (int)$_POST['id_cultivo'];
        try {
            // Comprobar si tiene plantaciones vinculadas
            $chk = $db->prepare("SELECT COUNT(*) FROM plantaciones WHERE id_cultivo = :id");
            $chk->execute(array(':id' => $idCultivo));
            if ($chk->fetchColumn() > 0) {
                // Desactivar en lugar de borrar para mantener historial
                $stmtDes = $db->prepare("UPDATE cultivos SET activo = 0 WHERE id_cultivo = :id");
                $stmtDes->execute(array(':id' => $idCultivo));
                $mensaje = "El cultivo tiene plantaciones históricas asociadas; ha sido descatalogado / desactivado correctamente.";
            } else {
                $stmtDel = $db->prepare("DELETE FROM cultivos WHERE id_cultivo = :id");
                $stmtDel->execute(array(':id' => $idCultivo));
                $mensaje = "Cultivo eliminado correctamente del catálogo maestro.";
            }
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
        <a href="<?= APP_URL ?>/cultivos/nuevo" class="btn btn-ag shadow-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Añadir Cultivo
        </a>
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
                                        <!-- Modificar -->
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarCultivo_<?= $c['id_cultivo'] ?>" title="Modificar cultivo">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Borrar -->
                                        <form action="<?= APP_URL ?>/cultivos" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar el cultivo \'<?= htmlspecialchars(addslashes($c['nombre_comun'])) ?>\'?');">
                                            <input type="hidden" name="action" value="borrar_cultivo">
                                            <input type="hidden" name="id_cultivo" value="<?= $c['id_cultivo'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Eliminar / Descatalogar">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
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

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">URL Enlace Botánico / Wiki</label>
                                                            <input type="url" name="url_consulta_botanica" class="form-control" value="<?= htmlspecialchars($c['url_consulta_botanica'] ?: '') ?>">
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold small">Requerimientos Agronómicos</label>
                                                            <textarea name="requerimientos_tecnicos" class="form-control" rows="3"><?= htmlspecialchars($c['requerimientos_tecnicos'] ?: '') ?></textarea>
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
