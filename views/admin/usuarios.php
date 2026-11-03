<?php
if (!Auth::isAdmin()) {
    header("Location: " . APP_URL . "/dashboard");
    exit;
}

$pageTitle = "Gestión y Aprobación de Usuarios - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();

// Procesar aprobación o rechazo
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_usuario'])) {
    $idUser = (int)$_POST['id_usuario'];
    $action = $_POST['action'];

    if ($action === 'aprobar') {
        $rol = isset($_POST['rol']) ? $_POST['rol'] : 'propietario';
        $stmt = $db->prepare("UPDATE usuarios SET estado = 'aprobado', rol = :rol, fecha_aprobacion = NOW(), aprobado_por_id = :admin WHERE id_usuario = :id");
        $stmt->execute(array(':rol' => $rol, ':admin' => Auth::user()['id'], ':id' => $idUser));
        $mensaje = "Usuario aprobado correctamente con rol " . htmlspecialchars($rol) . ".";
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

// Obtener usuarios aprobados y otros
$activos = $db->query("
    SELECT u.*, p.nombre_razon_social AS nombre_propietario
    FROM usuarios u
    LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
    WHERE u.estado != 'pendiente'
    ORDER BY u.id_usuario DESC
")->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-users-gear text-warning me-2"></i>Gestión de Usuarios y Aprobaciones</h2>
            <p class="text-muted mb-0">Control de altas de nuevos propietarios y asignación de permisos</p>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($mensaje) ?>
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
                            <th>Explotación / Propietario</th>
                            <th>Motivo</th>
                            <th>Rol a Asignar</th>
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
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success fw-bold">
                                        <?= htmlspecialchars(isset($p['nombre_propietario']) ? $p['nombre_propietario'] : 'Nuevo Propietario') ?>
                                    </span>
                                </td>
                                <td class="small text-muted" style="max-width:200px;"><?= htmlspecialchars(isset($p['motivo_solicitud']) ? $p['motivo_solicitud'] : '-') ?></td>
                                <td>
                                    <form action="<?= APP_URL ?>/admin/usuarios" method="POST" id="form-aprobar-<?= $p['id_usuario'] ?>">
                                        <input type="hidden" name="id_usuario" value="<?= $p['id_usuario'] ?>">
                                        <input type="hidden" name="action" value="aprobar">
                                        <select name="rol" class="form-select form-select-sm">
                                            <option value="propietario" selected>Propietario</option>
                                            <option value="tecnico">Técnico / Encargado</option>
                                            <option value="lector">Lector / Auditor</option>
                                            <option value="superadmin">Administrador General</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="submit" form="form-aprobar-<?= $p['id_usuario'] ?>" class="btn btn-sm btn-success fw-bold">
                                            <i class="fa-solid fa-check me-1"></i>Aprobar
                                        </button>
                                        <form action="<?= APP_URL ?>/admin/usuarios" method="POST" onsubmit="return confirm('¿Seguro que deseas rechazar esta solicitud?');">
                                            <input type="hidden" name="id_usuario" value="<?= $p['id_usuario'] ?>">
                                            <input type="hidden" name="action" value="rechazar">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
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
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-users text-primary me-2"></i>Usuarios Registrados</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Explotación</th>
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
                            <td><?= htmlspecialchars(isset($u['nombre_propietario']) ? $u['nombre_propietario'] : 'Todas (Admin)') ?></td>
                            <td>
                                <span class="badge bg-secondary text-capitalize"><?= htmlspecialchars($u['rol']) ?></span>
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

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
