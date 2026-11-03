<?php
$pageTitle = "Maestro de Parajes - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR ACCIONES CRUD (CREAR, EDITAR, BORRAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. CREAR PARAJE / PARADA
    if ($action === 'crear_paraje') {
        $codigo = trim(isset($_POST['codigo']) ? $_POST['codigo'] : '');
        $nombre = trim(isset($_POST['nombre_paraje']) ? $_POST['nombre_paraje'] : '');
        $termino = trim(isset($_POST['termino_municipal']) ? $_POST['termino_municipal'] : 'Moratalla');
        $provincia = trim(isset($_POST['provincia']) ? $_POST['provincia'] : 'Murcia');
        $desc = trim(isset($_POST['descripcion']) ? $_POST['descripcion'] : '');

        if (empty($nombre) || empty($termino)) {
            $error = "El nombre del paraje/parada y el término municipal son obligatorios.";
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO parajes (codigo, id_propietario, nombre_paraje, termino_municipal, provincia, descripcion, activo)
                    VALUES (:cod, :prop, :nom, :term, :prov, :desc, 1)
                ");
                $stmt->execute(array(
                    ':cod'  => $codigo ?: null,
                    ':prop' => $isSuperAdmin ? null : $tenantId,
                    ':nom'  => $nombre,
                    ':term' => $termino,
                    ':prov' => $provincia ?: 'Murcia',
                    ':desc' => $desc
                ));
                $mensaje = "Paraje/Parada <strong>" . htmlspecialchars($nombre) . "</strong> añadido correctamente.";
            } catch (Exception $e) {
                $error = "Error al guardar: " . $e->getMessage();
            }
        }
    }

    // 2. EDITAR / MODIFICAR PARAJE / PARADA
    elseif ($action === 'editar_paraje') {
        $idParaje = (int)$_POST['id_paraje'];
        $codigo = trim(isset($_POST['codigo']) ? $_POST['codigo'] : '');
        $nombre = trim(isset($_POST['nombre_paraje']) ? $_POST['nombre_paraje'] : '');
        $termino = trim(isset($_POST['termino_municipal']) ? $_POST['termino_municipal'] : 'Moratalla');
        $provincia = trim(isset($_POST['provincia']) ? $_POST['provincia'] : 'Murcia');
        $desc = trim(isset($_POST['descripcion']) ? $_POST['descripcion'] : '');

        if (empty($nombre) || empty($termino)) {
            $error = "El nombre del paraje/parada y el término municipal son obligatorios.";
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE parajes
                    SET codigo = :cod, nombre_paraje = :nom, termino_municipal = :term, provincia = :prov, descripcion = :desc
                    WHERE id_paraje = :id
                ");
                $stmt->execute(array(
                    ':cod'  => $codigo ?: null,
                    ':nom'  => $nombre,
                    ':term' => $termino,
                    ':prov' => $provincia ?: 'Murcia',
                    ':desc' => $desc,
                    ':id'   => $idParaje
                ));
                $mensaje = "Paraje/Parada <strong>" . htmlspecialchars($nombre) . "</strong> actualizado correctamente.";
            } catch (Exception $e) {
                $error = "Error al modificar: " . $e->getMessage();
            }
        }
    }

    // 3. BORRAR PARAJE
    elseif ($action === 'borrar_paraje') {
        $idParaje = (int)$_POST['id_paraje'];
        try {
            // Comprobar si tiene fincas asociadas
            $chk = $db->prepare("SELECT COUNT(*) FROM fincas WHERE id_paraje = :id");
            $chk->execute(array(':id' => $idParaje));
            if ($chk->fetchColumn() > 0) {
                $error = "No se puede eliminar este paraje porque tiene fincas asignadas. Reasigna primero las fincas a otro paraje.";
            } else {
                $stmtDel = $db->prepare("DELETE FROM parajes WHERE id_paraje = :id");
                $stmtDel->execute(array(':id' => $idParaje));
                $mensaje = "Paraje eliminado correctamente del maestro.";
            }
        } catch (Exception $e) {
            $error = "Error al eliminar paraje: " . $e->getMessage();
        }
    }
}

// Filtro de búsqueda
$filtroTxt = isset($_GET['q']) ? trim($_GET['q']) : '';

$sqlParajes = "
    SELECT p.*, COUNT(f.id_finca) AS total_fincas
    FROM parajes p
    LEFT JOIN fincas f ON p.id_paraje = f.id_paraje
    WHERE (p.activo = 1 OR p.activo IS NULL)
";
$params = array();

if (!empty($filtroTxt)) {
    $sqlParajes .= " AND (p.codigo LIKE :txt OR p.nombre_paraje LIKE :txt OR p.termino_municipal LIKE :txt OR p.descripcion LIKE :txt)";
    $params[':txt'] = '%' . $filtroTxt . '%';
}

$sqlParajes .= " GROUP BY p.id_paraje ORDER BY CASE WHEN p.codigo IS NOT NULL AND p.codigo != '' THEN 0 ELSE 1 END, p.codigo ASC, p.nombre_paraje ASC";
$stmtP = $db->prepare($sqlParajes);
$stmtP->execute($params);
$parajes = $stmtP->fetchAll();
?>

<div class="container py-4">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-location-crosshairs fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Maestro de Parajes y Paradas de Riego</h2>
                <p class="text-muted mb-0 small">Estandarización y catálogo oficial de parajes y paradas de la Comunidad de Regantes</p>
            </div>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formParaje">
            <i class="fa-solid fa-plus-circle me-1"></i> Nueva Parada / Paraje
        </button>
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

    <!-- Formulario Desplegable para Crear Paraje -->
    <div class="collapse mb-4" id="formParaje">
        <div class="card card-ag p-4 p-md-5 border-top border-4 border-primary">
            <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-location-dot me-2"></i>Registrar Nuevo Paraje en el Maestro</h5>
            <form action="<?= APP_URL ?>/parajes" method="POST">
                <input type="hidden" name="action" value="crear_paraje">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Nº / Código de Parada</label>
                        <input type="text" name="codigo" class="form-control font-monospace" placeholder="Ej: 067, 016A...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Nombre de la Parada / Paraje <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_paraje" class="form-control" required placeholder="Ej: BALSAS LOS PERALES, Hornillos...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Término Municipal <span class="text-danger">*</span></label>
                        <input type="text" name="termino_municipal" class="form-control" required value="Moratalla" placeholder="Ej: Moratalla">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">Provincia</label>
                        <input type="text" name="provincia" class="form-control" value="Murcia">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Descripción o Características del Terreno</label>
                    <textarea name="descripcion" class="form-control" rows="2" placeholder="Orientación, altitud, tipo de suelo calizo/aluvial, accesos..."></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-toggle="collapse" data-bs-target="#formParaje">Cancelar</button>
                    <button type="submit" class="btn btn-ag px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Paraje
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Barra de Búsqueda -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm">
        <form action="<?= APP_URL ?>/parajes" method="GET" class="row g-2 align-items-center">
            <div class="col-md-9">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por nombre de paraje, municipio o descripción..." value="<?= htmlspecialchars($filtroTxt) ?>">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button>
                <?php if ($filtroTxt): ?>
                    <a href="<?= APP_URL ?>/parajes" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla Maestra de Parajes (CRUD) -->
    <div class="card card-ag shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-map-location-dot me-2"></i>Parajes Registrados (<?= count($parajes) ?>)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th class="ps-4">Código / Parada</th>
                        <th>Nombre Parada / Paraje</th>
                        <th>Término Municipal</th>
                        <th>Provincia</th>
                        <th>Fincas Asignadas</th>
                        <th>Descripción Técnica</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parajes)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-location-dot fa-2x mb-3 text-secondary"></i>
                                <p class="mb-0">No se encontraron parajes ni paradas registradas.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($parajes as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php if (!empty($p['codigo'])): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle font-monospace fs-6 px-2 py-1">
                                            <?= htmlspecialchars($p['codigo']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border font-monospace">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-map-pin text-primary"></i>
                                        <?= htmlspecialchars($p['nombre_paraje']) ?>
                                    </div>
                                    <div class="small text-muted font-monospace">ID #<?= $p['id_paraje'] ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($p['termino_municipal']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($p['provincia'] ?: 'Murcia') ?></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
                                        <i class="fa-solid fa-layer-group me-1"></i><?= $p['total_fincas'] ?> fincas
                                    </span>
                                </td>
                                <td class="small text-muted" style="max-width: 300px;">
                                    <?= htmlspecialchars($p['descripcion'] ?: 'Sin descripción detallada') ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Modificar -->
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarParaje_<?= $p['id_paraje'] ?>" title="Modificar paraje">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Borrar -->
                                        <form action="<?= APP_URL ?>/parajes" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar el paraje \'<?= htmlspecialchars(addslashes($p['nombre_paraje'])) ?>\'?');">
                                            <input type="hidden" name="action" value="borrar_paraje">
                                            <input type="hidden" name="id_paraje" value="<?= $p['id_paraje'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Eliminar paraje" <?= ($p['total_fincas'] > 0) ? 'disabled title="Tiene fincas asignadas"' : '' ?>>
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Modificar Paraje -->
                                    <div class="modal fade text-start" id="modalEditarParaje_<?= $p['id_paraje'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Paraje: <?= htmlspecialchars($p['nombre_paraje']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="<?= APP_URL ?>/parajes" method="POST">
                                                    <input type="hidden" name="action" value="editar_paraje">
                                                    <input type="hidden" name="id_paraje" value="<?= $p['id_paraje'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Nº / Código Parada</label>
                                                                <input type="text" name="codigo" class="form-control font-monospace fw-bold text-primary" value="<?= htmlspecialchars($p['codigo'] ?: '') ?>" placeholder="Ej: 067">
                                                            </div>
                                                            <div class="col-md-8">
                                                                <label class="form-label fw-semibold small">Nombre de Parada / Paraje *</label>
                                                                <input type="text" name="nombre_paraje" class="form-control" value="<?= htmlspecialchars($p['nombre_paraje']) ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-7">
                                                                <label class="form-label fw-semibold small">Término Municipal *</label>
                                                                <input type="text" name="termino_municipal" class="form-control" value="<?= htmlspecialchars($p['termino_municipal']) ?>" required>
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label fw-semibold small">Provincia</label>
                                                                <input type="text" name="provincia" class="form-control" value="<?= htmlspecialchars($p['provincia'] ?: 'Murcia') ?>">
                                                            </div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold small">Descripción</label>
                                                            <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($p['descripcion'] ?: '') ?></textarea>
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
