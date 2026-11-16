<?php
if (!Auth::isAdmin()) {
    header("Location: " . APP_URL . "/dashboard");
    exit;
}

$db = Database::getConnection();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

// Cargar todas las explotaciones activas para poder asignarlas al aprobar
$explotacionesDisp = $db->query("SELECT id_propietario, nombre_razon_social, activo FROM propietarios ORDER BY activo DESC, nombre_razon_social ASC")->fetchAll();

// Procesar aprobación o rechazo (Antes de enviar cualquier cabecera o HTML)
$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_usuario'])) {
    $idUser = (int)$_POST['id_usuario'];
    $action = $_POST['action'];

    // Comprobar rol del usuario destino
    $chkTarget = $db->prepare("SELECT rol FROM usuarios WHERE id_usuario = :id");
    $chkTarget->execute(array(':id' => $idUser));
    $targetRol = $chkTarget->fetchColumn();

    if ($targetRol === 'superadmin' && !$isSuperAdmin) {
        $error = "Acceso denegado: No tienes permisos para gestionar al Superusuario.";
    } elseif ($action === 'aprobar') {
        $rol = isset($_POST['rol']) ? $_POST['rol'] : 'propietario';
        if (!$isSuperAdmin && in_array($rol, array('superadmin', 'administrador', 'admin'))) {
            $rol = 'propietario';
        }

        // Explotación o explotaciones seleccionadas por el administrador
        $expSeleccionadas = array();
        if (isset($_POST['explotaciones']) && is_array($_POST['explotaciones'])) {
            $expSeleccionadas = array_values(array_filter(array_map('intval', $_POST['explotaciones'])));
        } elseif (!empty($_POST['id_propietario'])) {
            $expSeleccionadas = array((int)$_POST['id_propietario']);
        }

        // Si se indicó crear una nueva explotación en el acto al aprobar
        $nuevaExpNombre = isset($_POST['nueva_explotacion_nombre']) ? trim($_POST['nueva_explotacion_nombre']) : '';
        if (!empty($nuevaExpNombre)) {
            $stmtNewExp = $db->prepare("INSERT INTO propietarios (nombre_razon_social, activo, fecha_alta) VALUES (:nom, 1, NOW())");
            $stmtNewExp->execute(array(':nom' => $nuevaExpNombre));
            $newExpId = (int)$db->lastInsertId();
            if ($newExpId > 0) {
                $expSeleccionadas[] = $newExpId;
            }
            // Refrescar lista de explotaciones
            $explotacionesDisp = $db->query("SELECT id_propietario, nombre_razon_social, activo FROM propietarios ORDER BY activo DESC, nombre_razon_social ASC")->fetchAll();
        }

        $idPropPrincipal = !empty($expSeleccionadas) ? $expSeleccionadas[0] : null;

        if (empty($idPropPrincipal) && !in_array($rol, array('superadmin', 'administrador', 'admin'))) {
            $error = "Debes seleccionar o crear al menos una explotación para asignársela al usuario.";
        } else {
            $stmt = $db->prepare("
                UPDATE usuarios 
                SET estado = 'aprobado', rol = :rol, id_propietario = :prop, fecha_aprobacion = NOW(), aprobado_por_id = :admin 
                WHERE id_usuario = :id
            ");
            $stmt->execute(array(
                ':rol'   => $rol,
                ':prop'  => $idPropPrincipal,
                ':admin' => $currentUser['id'],
                ':id'    => $idUser
            ));

            // Sincronizar asignaciones en usuarios_explotaciones
            $db->prepare("DELETE FROM usuarios_explotaciones WHERE id_usuario = :uid")->execute(array(':uid' => $idUser));
            if (!empty($expSeleccionadas)) {
                $insUE = $db->prepare("INSERT IGNORE INTO usuarios_explotaciones (id_usuario, id_propietario) VALUES (:uid, :pid)");
                foreach ($expSeleccionadas as $pid) {
                    if ($pid > 0) {
                        $insUE->execute(array(':uid' => $idUser, ':pid' => $pid));
                    }
                }
            }

            $mensaje = "Usuario dado de alta, aprobado con rol " . htmlspecialchars($rol) . " y vinculado a la explotación seleccionada.";
        }
    } elseif ($action === 'asignar_explotacion') {
        $idPropAsig = !empty($_POST['id_propietario']) ? (int)$_POST['id_propietario'] : null;
        $stmtUpd = $db->prepare("UPDATE usuarios SET id_propietario = :prop WHERE id_usuario = :id");
        $stmtUpd->execute(array(':prop' => $idPropAsig, ':id' => $idUser));
        $db->prepare("DELETE FROM usuarios_explotaciones WHERE id_usuario = :uid")->execute(array(':uid' => $idUser));
        if ($idPropAsig) {
            $db->prepare("INSERT IGNORE INTO usuarios_explotaciones (id_usuario, id_propietario) VALUES (:uid, :pid)")
               ->execute(array(':uid' => $idUser, ':pid' => $idPropAsig));
        }
        $mensaje = "Explotación reasignada correctamente al usuario.";
    } elseif ($action === 'rechazar') {
        $stmt = $db->prepare("UPDATE usuarios SET estado = 'rechazado' WHERE id_usuario = :id");
        $stmt->execute(array(':id' => $idUser));
        $mensaje = "Solicitud rechazada.";
    } elseif ($action === 'suspender') {
        $stmt = $db->prepare("UPDATE usuarios SET estado = 'suspendido' WHERE id_usuario = :id");
        $stmt->execute(array(':id' => $idUser));
        $mensaje = "Usuario suspendido.";
    }
}

// Obtener usuarios pendientes
$pendientes = $db->query("
    SELECT u.*, p.nombre_razon_social AS nombre_propietario
    FROM usuarios u
    LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
    WHERE u.estado = 'pendiente'
    ORDER BY u.fecha_registro DESC
")->fetchAll();

// Obtener usuarios aprobados y otros (ocultando superadmin si el usuario no es superadmin)
$sqlActivos = "
    SELECT u.*, p.nombre_razon_social AS nombre_propietario
    FROM usuarios u
    LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
    WHERE u.estado != 'pendiente'
";
if (!$isSuperAdmin) {
    $sqlActivos .= " AND u.rol != 'superadmin'";
}
$sqlActivos .= " ORDER BY u.id_usuario DESC";
$activos = $db->query($sqlActivos)->fetchAll();

$pageTitle = "Gestión y Aprobación de Usuarios - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-users-gear text-warning me-2"></i>Gestión de Usuarios y Aprobaciones</h2>
            <p class="text-muted mb-0">Control de altas de nuevos solicitantes, asignación de explotación y permisos</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/explotaciones" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-building-wheat me-1"></i>Gestionar Explotaciones
            </a>
            <a href="<?= APP_URL ?>/admin/gestion-usuarios" class="btn btn-ag btn-sm">
                <i class="fa-solid fa-id-card-clip me-1"></i>Gestión Avanzada de Equipo
            </a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Solicitudes Pendientes -->
    <div class="card card-custom p-4 mb-4 border-top border-4 border-warning">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">
                <i class="fa-solid fa-clock-rotate-left text-warning me-2"></i>
                Solicitudes Pendientes de Aprobación 
                <span class="badge bg-warning text-dark ms-2"><?= count($pendientes) ?></span>
            </h5>
        </div>

        <?php if (empty($pendientes)): ?>
            <div class="alert alert-light border text-center py-4 mb-0 text-muted">
                <i class="fa-solid fa-check-double text-success me-1"></i> No hay solicitudes pendientes de validación en este momento.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Solicitante</th>
                            <th>Email / Teléfono</th>
                            <th>Motivo / Detalles</th>
                            <th style="min-width: 240px;">Explotación a Asignar</th>
                            <th style="min-width: 190px;">Rol a Asignar</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendientes as $p): ?>
                            <tr>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($p['fecha_registro'])) ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($p['nombre'] . ' ' . $p['apellidos']) ?></td>
                                <td>
                                    <div><i class="fa-solid fa-envelope text-muted me-1"></i><?= htmlspecialchars($p['email']) ?></div>
                                    <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars(isset($p['telefono']) ? $p['telefono'] : '-') ?></div>
                                </td>
                                <td class="small text-muted" style="max-width:200px;"><?= htmlspecialchars(isset($p['motivo_solicitud']) ? $p['motivo_solicitud'] : '-') ?></td>
                                <td>
                                    <select name="id_propietario" form="form-aprobar-<?= $p['id_usuario'] ?>" class="form-select form-select-sm mb-1" onchange="toggleNuevaExplotacionInput(this, 'nueva-exp-<?= $p['id_usuario'] ?>')">
                                        <option value="">-- Seleccionar explotación existente --</option>
                                        <?php foreach ($explotacionesDisp as $exp): ?>
                                            <?php if ((int)$exp['activo'] === 1): ?>
                                                <option value="<?= $exp['id_propietario'] ?>" <?= ((int)$p['id_propietario'] === (int)$exp['id_propietario']) ? 'selected' : '' ?>>
                                                    🚜 <?= htmlspecialchars($exp['nombre_razon_social']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <option value="nueva" class="fw-bold text-primary">➕ Crear nueva explotación para este usuario...</option>
                                    </select>
                                    <div id="nueva-exp-<?= $p['id_usuario'] ?>" style="display: none;">
                                        <input type="text" name="nueva_explotacion_nombre" form="form-aprobar-<?= $p['id_usuario'] ?>" class="form-control form-control-sm" placeholder="Nombre de la nueva explotación...">
                                    </div>
                                </td>
                                <td>
                                    <form action="<?= APP_URL ?>/admin/usuarios" method="POST" id="form-aprobar-<?= $p['id_usuario'] ?>">
                                        <input type="hidden" name="id_usuario" value="<?= $p['id_usuario'] ?>">
                                        <input type="hidden" name="action" value="aprobar">
                                        <select name="rol" class="form-select form-select-sm">
                                            <option value="propietario" selected>Gestor Explotación / Propietario</option>
                                            <option value="tecnico">Técnico / Encargado</option>
                                            <option value="operario">Operario de Campo</option>
                                            <option value="lector">Lector / Auditor</option>
                                            <?php if ($isSuperAdmin): ?>
                                                <option value="administrador" class="text-primary fw-bold">Administrador</option>
                                                <option value="superadmin" class="text-danger fw-bold">Superusuario</option>
                                            <?php endif; ?>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="submit" form="form-aprobar-<?= $p['id_usuario'] ?>" class="btn btn-sm btn-success fw-bold">
                                            <i class="fa-solid fa-check me-1"></i>Dar de Alta y Asignar
                                        </button>
                                        <form action="<?= APP_URL ?>/admin/usuarios" method="POST" onsubmit="return confirm('¿Seguro que deseas rechazar esta solicitud?');">
                                            <input type="hidden" name="id_usuario" value="<?= $p['id_usuario'] ?>">
                                            <input type="hidden" name="action" value="rechazar">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Rechazar solicitud">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Usuarios del Sistema -->
    <div class="card card-custom p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-users text-primary me-2"></i>Usuarios Registrados y Asignación de Explotación</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Explotación Asignada</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activos as $u): ?>
                        <tr>
                            <td>#<?= $u['id_usuario'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td style="min-width: 230px;">
                                <form action="<?= APP_URL ?>/admin/usuarios" method="POST" class="d-flex align-items-center gap-1">
                                    <input type="hidden" name="action" value="asignar_explotacion">
                                    <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                    <select name="id_propietario" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="" <?= empty($u['id_propietario']) ? 'selected' : '' ?>>-- Sin asignar / Global --</option>
                                        <?php foreach ($explotacionesDisp as $exp): ?>
                                            <option value="<?= $exp['id_propietario'] ?>" <?= ((int)$u['id_propietario'] === (int)$exp['id_propietario']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($exp['nombre_razon_social']) ?><?= ((int)$exp['activo'] === 0) ? ' (Desactivada)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <?php if ($u['rol'] === 'superadmin'): ?>
                                    <span class="badge bg-danger text-white"><i class="fa-solid fa-crown me-1 text-warning"></i>Superusuario</span>
                                <?php elseif (in_array($u['rol'], array('administrador', 'admin'))): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-shield-halved me-1"></i>Administrador</span>
                                <?php elseif ($u['rol'] === 'propietario'): ?>
                                    <span class="badge bg-primary">Gestor Explotación</span>
                                <?php elseif ($u['rol'] === 'tecnico'): ?>
                                    <span class="badge bg-info text-dark">Técnico</span>
                                <?php elseif ($u['rol'] === 'operario'): ?>
                                    <span class="badge bg-secondary">Operario</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Lector</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['estado'] === 'aprobado'): ?>
                                    <span class="badge bg-success">Aprobado</span>
                                <?php elseif ($u['estado'] === 'suspendido'): ?>
                                    <span class="badge bg-danger">Suspendido</span>
                                <?php else: ?>
                                    <span class="badge bg-dark"><?= htmlspecialchars($u['estado']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleNuevaExplotacionInput(selectEl, containerId) {
    var box = document.getElementById(containerId);
    if (!box) return;
    var input = box.querySelector('input');
    if (selectEl.value === 'nueva') {
        box.style.display = 'block';
        if (input) {
            input.required = true;
            input.focus();
        }
    } else {
        box.style.display = 'none';
        if (input) {
            input.required = false;
            input.value = '';
        }
    }
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
