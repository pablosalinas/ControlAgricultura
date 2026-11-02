<?php
if (!Auth::canManageUsers()) {
    header("Location: " . APP_URL . "/dashboard");
    exit;
}

$pageTitle = "Gestión de Usuarios y Equipo - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();
$tenantId = Auth::tenantId();

$mensaje = '';
$error = '';

// Procesar acciones de formulario (Crear usuario, Editar rol, Suspender)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'crear_usuario') {
        $nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
        $apellidos = trim(isset($_POST['apellidos']) ? $_POST['apellidos'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $telefono = trim(isset($_POST['telefono']) ? $_POST['telefono'] : '');
        $rol = isset($_POST['rol']) ? $_POST['rol'] : 'tecnico';
        
        // Si es superadmin, puede elegir la explotación; si es propietario, se fuerza su propia explotación
        $idProp = $isSuperAdmin ? (!empty($_POST['id_propietario']) ? (int)$_POST['id_propietario'] : null) : $tenantId;

        // Si no es superadmin, no puede crear superadmins
        if (!$isSuperAdmin && $rol === 'superadmin') {
            $rol = 'tecnico';
        }

        if (empty($nombre) || empty($email) || empty($password)) {
            $error = "Nombre, email y contraseña son obligatorios.";
        } else {
            // Comprobar si el email ya existe
            $chk = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1");
            $chk->execute(array(':email' => $email));
            if ($chk->fetch()) {
                $error = "Ya existe un usuario registrado con ese correo electrónico.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtIns = $db->prepare("
                    INSERT INTO usuarios (id_propietario, nombre, apellidos, email, password_hash, telefono, rol, estado, fecha_registro, fecha_aprobacion, aprobado_por_id)
                    VALUES (:prop, :nom, :ape, :em, :pwd, :tel, :rol, 'aprobado', NOW(), NOW(), :admin)
                ");
                $stmtIns->execute(array(
                    ':prop'  => $idProp,
                    ':nom'   => $nombre,
                    ':ape'   => $apellidos,
                    ':em'    => $email,
                    ':pwd'   => $hash,
                    ':tel'   => $telefono,
                    ':rol'   => $rol,
                    ':admin' => $currentUser['id']
                ));
                $mensaje = "Usuario creado y activado correctamente en la explotación.";
            }
        }
    } elseif ($action === 'cambiar_estado') {
        $idTarget = (int)$_POST['id_usuario'];
        $nuevoEstado = $_POST['nuevo_estado'];

        // Comprobar permisos sobre el usuario objetivo
        $stmtChk = $db->prepare("SELECT id_usuario, id_propietario, rol FROM usuarios WHERE id_usuario = :id");
        $stmtChk->execute(array(':id' => $idTarget));
        $targetUser = $stmtChk->fetch();

        if ($targetUser) {
            // Un propietario solo puede modificar usuarios de su propia explotación y no puede modificar superadmins
            if (!$isSuperAdmin && ((int)$targetUser['id_propietario'] !== (int)$tenantId || $targetUser['rol'] === 'superadmin')) {
                $error = "No tienes permiso para modificar este usuario.";
            } else {
                $upd = $db->prepare("UPDATE usuarios SET estado = :est WHERE id_usuario = :id");
                $upd->execute(array(':est' => $nuevoEstado, ':id' => $idTarget));
                $mensaje = "Estado de usuario actualizado.";
            }
        }
    }
}

// Cargar explotaciones (si es superadmin)
$explotaciones = array();
if ($isSuperAdmin) {
    $explotaciones = $db->query("SELECT id_propietario, nombre_razon_social FROM propietarios ORDER BY nombre_razon_social ASC")->fetchAll();
}

// Consultar usuarios según permisos (Superadmin ve todos o filtra; Propietario SOLO ve los de su explotación)
$sqlUsers = "
    SELECT u.*, p.nombre_razon_social AS nombre_explotacion
    FROM usuarios u
    LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
";
if (!$isSuperAdmin) {
    $sqlUsers .= " WHERE u.id_propietario = " . (int)$tenantId;
} elseif (isset($_GET['explotacion_id']) && (int)$_GET['explotacion_id'] > 0) {
    $sqlUsers .= " WHERE u.id_propietario = " . (int)$_GET['explotacion_id'];
}
$sqlUsers .= " ORDER BY u.rol = 'superadmin' DESC, u.id_propietario ASC, u.nombre ASC";
$usuarios = $db->query($sqlUsers)->fetchAll();
?>

<div class="container py-4">
    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-users text-success me-2"></i>Gestión de Usuarios y Equipo</h2>
            <p class="text-muted mb-0">
                <?php if ($isSuperAdmin): ?>
                    <span class="badge bg-warning text-dark me-2">Superadministrador</span> Control técnico global de usuarios de todas las explotaciones
                <?php else: ?>
                    <span class="badge bg-success me-2">Gestor</span> Equipo de trabajo de tu explotación: <strong><?= htmlspecialchars($currentUser['nombre_propietario']) ?></strong>
                <?php endif; ?>
            </p>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formNuevoUsuario">
            <i class="fa-solid fa-user-plus me-1"></i> Dar de Alta Nuevo Usuario
        </button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Formulario Desplegable para Crear Usuario -->
    <div class="collapse mb-4" id="formNuevoUsuario">
        <div class="card card-custom p-4 p-md-5 border-top border-4 border-success">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-user-gear text-success me-2"></i>Nuevo Usuario / Perfil de Trabajo</h5>
            <form action="<?= APP_URL ?>/admin/gestion-usuarios" method="POST">
                <input type="hidden" name="action" value="crear_usuario">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Nombre del trabajador o técnico">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Apellidos</label>
                        <input type="text" name="apellidos" class="form-control" placeholder="Apellidos">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Correo Electrónico (Acceso) <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="usuario@email.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contraseña Inicial <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Teléfono</label>
                        <input type="tel" name="telefono" class="form-control" placeholder="600 000 000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Rol / Perfil de Trabajo <span class="text-danger">*</span></label>
                        <select name="rol" class="form-select" required>
                            <option value="tecnico" selected>Técnico / Encargado (Registra labores y riegos)</option>
                            <option value="propietario">Gestor de Explotación (Control total de la finca)</option>
                            <option value="lector">Lector / Auditor (Solo consulta de cuadernos)</option>
                            <?php if ($isSuperAdmin): ?>
                                <option value="superadmin" class="text-danger fw-bold">Superadministrador (Técnico Global)</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <?php if ($isSuperAdmin): ?>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Explotación Agrícola Asignada</label>
                            <select name="id_propietario" class="form-select">
                                <option value="">-- Sin Explotación / Global --</option>
                                <?php foreach ($explotaciones as $exp): ?>
                                    <option value="<?= $exp['id_propietario'] ?>"><?= htmlspecialchars($exp['nombre_razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Explotación Asignada</label>
                            <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($currentUser['nombre_propietario']) ?>">
                            <div class="form-text small">El usuario operará estrictamente en tu explotación.</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border px-4" data-bs-toggle="collapse" data-bs-target="#formNuevoUsuario">Cancelar</button>
                    <button type="submit" class="btn btn-ag px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar y Activar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtro para Superadministrador -->
    <?php if ($isSuperAdmin && !empty($explotaciones)): ?>
        <div class="card card-custom p-3 mb-4 bg-light border">
            <form action="<?= APP_URL ?>/admin/gestion-usuarios" method="GET" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1">Filtrar por Explotación Agraria:</label>
                    <select name="explotacion_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">-- Todas las explotaciones --</option>
                        <?php foreach ($explotaciones as $exp): ?>
                            <option value="<?= $exp['id_propietario'] ?>" <?= (isset($_GET['explotacion_id']) && (int)$_GET['explotacion_id'] === (int)$exp['id_propietario']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($exp['nombre_razon_social']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 pt-3">
                    <a href="<?= APP_URL ?>/admin/gestion-usuarios" class="btn btn-sm btn-outline-secondary">Limpiar Filtro</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Tabla de Usuarios -->
    <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-id-card-clip text-primary me-2"></i>Usuarios Registrados (<?= count($usuarios) ?>)</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Usuario</th>
                        <th>Email / Teléfono</th>
                        <th>Explotación Agrícola</th>
                        <th>Perfil / Rol</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="fw-bold text-dark">
                                <i class="fa-solid fa-user-circle text-muted me-2 fs-5"></i><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                            </td>
                            <td>
                                <div><i class="fa-solid fa-envelope text-muted me-1 small"></i><?= htmlspecialchars($u['email']) ?></div>
                                <?php if ($u['telefono']): ?>
                                    <div class="small text-muted"><i class="fa-solid fa-phone me-1 small"></i><?= htmlspecialchars($u['telefono']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['nombre_explotacion']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success fw-bold border border-success-subtle">
                                        <?= htmlspecialchars($u['nombre_explotacion']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Global (Técnico Central)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['rol'] === 'superadmin'): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-shield me-1"></i>Superadmin</span>
                                <?php elseif ($u['rol'] === 'propietario'): ?>
                                    <span class="badge bg-primary">Gestor Explotación</span>
                                <?php elseif ($u['rol'] === 'tecnico'): ?>
                                    <span class="badge bg-info text-dark">Técnico / Encargado</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Lector / Auditor</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['estado'] === 'aprobado'): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php elseif ($u['estado'] === 'pendiente'): ?>
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                <?php else: ?>
                                    <span class="badge bg-danger text-capitalize"><?= htmlspecialchars($u['estado']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?>
                            </td>
                            <td class="text-end">
                                <!-- Botones de Suspender o Activar -->
                                <?php if ($u['id_usuario'] != $currentUser['id']): ?>
                                    <form action="<?= APP_URL ?>/admin/gestion-usuarios" method="POST" class="d-inline" onsubmit="return confirm('¿Deseas cambiar el estado de este usuario?');">
                                        <input type="hidden" name="action" value="cambiar_estado">
                                        <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                        <?php if ($u['estado'] === 'aprobado'): ?>
                                            <input type="hidden" name="nuevo_estado" value="suspendido">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Suspender acceso">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="nuevo_estado" value="aprobado">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Activar acceso">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
