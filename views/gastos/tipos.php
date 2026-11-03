<?php
$pageTitle = "Maestro de Tipos de Gastos e Ingresos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// Procesar Acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // CREAR TIPO (admite petición normal o AJAX)
    if ($action === 'crear_tipo') {
        $nombre = trim(isset($_POST['nombre_tipo']) ? $_POST['nombre_tipo'] : '');
        $naturaleza = in_array(isset($_POST['naturaleza']) ? $_POST['naturaleza'] : '', array('gasto', 'ingreso', 'ambos')) ? $_POST['naturaleza'] : 'gasto';
        $color = trim(isset($_POST['color']) ? $_POST['color'] : ($naturaleza === 'ingreso' ? '#10b981' : '#dc2626'));
        $descripcion = trim(isset($_POST['descripcion']) ? $_POST['descripcion'] : '');
        $isAjax = !empty($_POST['is_ajax']);

        if (empty($nombre)) {
            $errText = "El nombre del tipo de gasto o ingreso es obligatorio.";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(array('success' => false, 'message' => $errText));
                exit;
            }
            $error = $errText;
        } else {
            try {
                // Comprobar si ya existe uno igual para no duplicar
                $check = $db->prepare("
                    SELECT id_tipo, activo, nombre_tipo 
                    FROM tipos_gastos_ingresos 
                    WHERE (id_propietario = :prop OR id_propietario IS NULL) 
                      AND LOWER(nombre_tipo) = LOWER(:nom) 
                    LIMIT 1
                ");
                $check->execute(array(':prop' => $tenantId, ':nom' => $nombre));
                $existente = $check->fetch();

                if ($existente) {
                    if ((int)$existente['activo'] === 0) {
                        // Reactivar si estaba borrado lógicamente
                        $db->prepare("UPDATE tipos_gastos_ingresos SET activo = 1, naturaleza = :nat, color = :col, descripcion = :desc WHERE id_tipo = :id")
                           ->execute(array(':nat' => $naturaleza, ':col' => $color, ':desc' => $descripcion, ':id' => $existente['id_tipo']));
                        $nuevoId = (int)$existente['id_tipo'];
                    } else {
                        $nuevoId = (int)$existente['id_tipo'];
                        if ($isAjax) {
                            header('Content-Type: application/json');
                            echo json_encode(array(
                                'success' => true,
                                'id_tipo' => $nuevoId,
                                'nombre_tipo' => $existente['nombre_tipo'],
                                'naturaleza' => $naturaleza,
                                'color' => $color,
                                'aviso' => 'El tipo ya existía y ha sido seleccionado automáticamente.'
                            ));
                            exit;
                        }
                    }
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO tipos_gastos_ingresos (id_propietario, nombre_tipo, naturaleza, color, descripcion, activo)
                        VALUES (:prop, :nom, :nat, :col, :desc, 1)
                    ");
                    $stmt->execute(array(
                        ':prop' => $tenantId,
                        ':nom'  => $nombre,
                        ':nat'  => $naturaleza,
                        ':col'  => $color ?: '#0284c7',
                        ':desc' => $descripcion ?: null
                    ));
                    $nuevoId = (int)$db->lastInsertId();
                }

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(array(
                        'success' => true,
                        'id_tipo' => $nuevoId,
                        'nombre_tipo' => $nombre,
                        'naturaleza' => $naturaleza,
                        'color' => $color,
                        'message' => 'Tipo guardado y seleccionado correctamente.'
                    ));
                    exit;
                }

                $mensaje = "Tipo de movimiento <strong>" . htmlspecialchars($nombre) . "</strong> registrado con éxito.";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
                    exit;
                }
                $error = "Error al guardar el tipo: " . $e->getMessage();
            }
        }
    }

    // EDITAR TIPO
    elseif ($action === 'editar_tipo') {
        $idTipo = (int)(isset($_POST['id_tipo']) ? $_POST['id_tipo'] : 0);
        $nombre = trim(isset($_POST['nombre_tipo']) ? $_POST['nombre_tipo'] : '');
        $naturaleza = in_array(isset($_POST['naturaleza']) ? $_POST['naturaleza'] : '', array('gasto', 'ingreso', 'ambos')) ? $_POST['naturaleza'] : 'gasto';
        $color = trim(isset($_POST['color']) ? $_POST['color'] : '#0284c7');
        $descripcion = trim(isset($_POST['descripcion']) ? $_POST['descripcion'] : '');

        if (empty($nombre) || $idTipo <= 0) {
            $error = "Datos inválidos para actualizar el tipo.";
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE tipos_gastos_ingresos 
                    SET nombre_tipo = :nom, naturaleza = :nat, color = :col, descripcion = :desc 
                    WHERE id_tipo = :id AND (id_propietario = :prop OR id_propietario IS NULL OR :isSuper = 1)
                ");
                $stmt->execute(array(
                    ':nom'     => $nombre,
                    ':nat'     => $naturaleza,
                    ':col'     => $color,
                    ':desc'    => $descripcion ?: null,
                    ':id'      => $idTipo,
                    ':prop'    => $tenantId,
                    ':isSuper' => $isSuperAdmin ? 1 : 0
                ));
                $mensaje = "Tipo de concepto <strong>" . htmlspecialchars($nombre) . "</strong> actualizado correctamente.";
            } catch (Exception $e) {
                $error = "Error al actualizar tipo: " . $e->getMessage();
            }
        }
    }

    // ELIMINAR TIPO (BORRADO LÓGICO)
    elseif ($action === 'borrar_tipo') {
        $idTipo = (int)(isset($_POST['id_tipo']) ? $_POST['id_tipo'] : 0);
        try {
            // Comprobar cuántos gastos lo utilizan
            $chk = $db->prepare("SELECT COUNT(*) FROM gastos_explotacion WHERE id_tipo = :id AND activo = 1");
            $chk->execute(array(':id' => $idTipo));
            $usos = (int)$chk->fetchColumn();

            // Borrado lógico (activo = 0) para cumplir estrictamente las reglas de protección de datos
            $stmt = $db->prepare("UPDATE tipos_gastos_ingresos SET activo = 0 WHERE id_tipo = :id");
            $stmt->execute(array(':id' => $idTipo));

            if ($usos > 0) {
                $mensaje = "El tipo ha sido desactivado del catálogo. Los $usos gastos existentes que lo tenían asignado se conservan intactos en el historial.";
            } else {
                $mensaje = "Tipo de gasto/ingreso eliminado correctamente del catálogo.";
            }
        } catch (Exception $e) {
            $error = "Error al desactivar tipo: " . $e->getMessage();
        }
    }
}

// Consultar tipos activos
$sqlTipos = "
    SELECT t.*, 
           (SELECT COUNT(*) FROM gastos_explotacion g WHERE g.id_tipo = t.id_tipo AND g.activo = 1) AS total_gastos_asignados,
           (SELECT COALESCE(SUM(g.importe), 0) FROM gastos_explotacion g WHERE g.id_tipo = t.id_tipo AND g.activo = 1 AND g.confirmado = 1) AS suma_importe_confirmado
    FROM tipos_gastos_ingresos t
    WHERE t.activo = 1 AND (t.id_propietario = :prop OR t.id_propietario IS NULL)
    ORDER BY t.naturaleza ASC, t.nombre_tipo ASC
";
$stmtT = $db->prepare($sqlTipos);
$stmtT->execute(array(':prop' => $tenantId));
$tipos = $stmtT->fetchAll();

$totalGastosTipos = 0;
$totalIngresosTipos = 0;
foreach ($tipos as $tp) {
    if ($tp['naturaleza'] === 'gasto' || $tp['naturaleza'] === 'ambos') {
        $totalGastosTipos++;
    }
    if ($tp['naturaleza'] === 'ingreso' || $tp['naturaleza'] === 'ambos') {
        $totalIngresosTipos++;
    }
}
?>

<div class="container py-4">

    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #d97706);">
                <i class="fa-solid fa-tags fs-3 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Maestro de Tipos de Gastos e Ingresos</h2>
                <p class="text-muted mb-0 small">Catálogo clasificador para imputación de costes en fincas y control de partidas presupuestarias</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/gastos" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Ir a Control de Gastos
            </a>
            <button type="button" class="btn btn-ag shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoTipo">
                <i class="fa-solid fa-circle-plus me-1"></i> Nuevo Tipo
            </button>
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

    <!-- Métricas -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-custom p-3 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Total Categorías Activas</div>
                        <div class="fs-3 fw-bold text-dark"><?= count($tipos) ?></div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="fa-solid fa-layer-group fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Tipos para Gastos / Costes</div>
                        <div class="fs-3 fw-bold text-danger"><?= $totalGastosTipos ?></div>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                        <i class="fa-solid fa-arrow-trend-down fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Tipos para Ingresos / Ventas</div>
                        <div class="fs-3 fw-bold text-success"><?= $totalIngresosTipos ?></div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="fa-solid fa-arrow-trend-up fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Maestra -->
    <div class="card card-custom p-4 shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-list-check me-2"></i>Conceptos Económicos Disponibles
            </h5>
            <span class="badge bg-light text-muted border">Puedes añadir nuevos conceptos aquí o directamente sobre la marcha al registrar un gasto</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nombre del Tipo / Concepto</th>
                        <th>Naturaleza</th>
                        <th>Descripción</th>
                        <th class="text-center">Gastos Asociados</th>
                        <th class="text-end">Importe Total Confirmado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tipos)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-tags fs-1 d-block mb-2 opacity-50"></i>
                                No hay tipos registrados. Pulsa en <strong>"Nuevo Tipo"</strong> para añadir uno.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tipos as $t): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle d-inline-block" style="width: 14px; height: 14px; background-color: <?= htmlspecialchars($t['color'] ?: '#0284c7') ?>;"></span>
                                        <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($t['nombre_tipo']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($t['naturaleza'] === 'gasto'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle">
                                            <i class="fa-solid fa-arrow-down me-1"></i>Gasto
                                        </span>
                                    <?php elseif ($t['naturaleza'] === 'ingreso'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">
                                            <i class="fa-solid fa-arrow-up me-1"></i>Ingreso
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle">
                                            Ambos
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small text-muted"><?= htmlspecialchars($t['descripcion'] ?: '-') ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <?= (int)$t['total_gastos_asignados'] ?> registros
                                    </span>
                                </td>
                                <td class="text-end font-monospace fw-bold <?= ($t['naturaleza'] === 'ingreso') ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($t['suma_importe_confirmado'], 2, ',', '.') ?> €
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarTipo_<?= $t['id_tipo'] ?>" title="Editar">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Deseas dar de baja el tipo \'<?= htmlspecialchars(addslashes($t['nombre_tipo'])) ?>\'?');">
                                            <input type="hidden" name="action" value="borrar_tipo">
                                            <input type="hidden" name="id_tipo" value="<?= $t['id_tipo'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Desactivar">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Editar Tipo -->
                                    <div class="modal fade text-start" id="modalEditarTipo_<?= $t['id_tipo'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Editar Tipo: <?= htmlspecialchars($t['nombre_tipo']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="editar_tipo">
                                                    <input type="hidden" name="id_tipo" value="<?= $t['id_tipo'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Nombre del Concepto / Tipo *</label>
                                                            <input type="text" name="nombre_tipo" class="form-control" value="<?= htmlspecialchars($t['nombre_tipo']) ?>" required>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-7">
                                                                <label class="form-label fw-semibold small">Naturaleza</label>
                                                                <select name="naturaleza" class="form-select">
                                                                    <option value="gasto" <?= ($t['naturaleza'] === 'gasto') ? 'selected' : '' ?>>Gasto / Salida de dinero</option>
                                                                    <option value="ingreso" <?= ($t['naturaleza'] === 'ingreso') ? 'selected' : '' ?>>Ingreso / Venta / Subvención</option>
                                                                    <option value="ambos" <?= ($t['naturaleza'] === 'ambos') ? 'selected' : '' ?>>Ambos indistintamente</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label fw-semibold small">Color Identificativo</label>
                                                                <input type="color" name="color" class="form-control form-control-color w-100" value="<?= htmlspecialchars($t['color'] ?: '#0284c7') ?>">
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Descripción o Ejemplos</label>
                                                            <textarea name="descripcion" class="form-control" rows="2"><?= htmlspecialchars($t['descripcion'] ?: '') ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-ag fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Cambios</button>
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

<!-- Modal Nuevo Tipo de Gasto / Ingreso -->
<div class="modal fade text-start" id="modalNuevoTipo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-tags me-2"></i> Añadir Nuevo Tipo de Concepto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear_tipo">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nombre del Concepto / Tipo *</label>
                        <input type="text" name="nombre_tipo" class="form-control" placeholder="Ej: Análisis foliar y de suelos" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Naturaleza</label>
                            <select name="naturaleza" class="form-select">
                                <option value="gasto" selected>Gasto / Salida de dinero</option>
                                <option value="ingreso">Ingreso / Venta / Subvención</option>
                                <option value="ambos">Ambos indistintamente</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Color Identificativo</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="#dc2626">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Descripción explicativa</label>
                        <textarea name="descripcion" class="form-control" rows="2" placeholder="Qué tipo de facturas o conceptos se agrupan aquí..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-ag fw-bold"><i class="fa-solid fa-plus-circle me-1"></i>Crear Tipo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>