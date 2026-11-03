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

// Procesar acciones de formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. CREAR NUEVO USUARIO
    if ($action === 'crear_usuario') {
        $nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
        $apellidos = trim(isset($_POST['apellidos']) ? $_POST['apellidos'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $telefono = trim(isset($_POST['telefono']) ? $_POST['telefono'] : '');
        $rol = isset($_POST['rol']) ? $_POST['rol'] : 'tecnico';
        
        // Multi-explotación
        $expSeleccionadas = isset($_POST['explotaciones']) && is_array($_POST['explotaciones']) ? array_map('intval', $_POST['explotaciones']) : array();
        if (!$isSuperAdmin) {
            $expSeleccionadas = array((int)$tenantId);
        }
        $idProp = !empty($expSeleccionadas) ? $expSeleccionadas[0] : null;

        if (!$isSuperAdmin && $rol === 'superadmin') {
            $rol = 'tecnico';
        }

        if (empty($nombre) || empty($email) || empty($password)) {
            $error = "Nombre, email y contraseña son obligatorios.";
        } else {
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
                $newUserId = (int)$db->lastInsertId();

                // Guardar asignaciones multi-explotación
                if (!empty($expSeleccionadas) && $newUserId > 0) {
                    $insUE = $db->prepare("INSERT IGNORE INTO usuarios_explotaciones (id_usuario, id_propietario) VALUES (?, ?)");
                    foreach ($expSeleccionadas as $pid) {
                        if ($pid > 0) {
                            $insUE->execute(array($newUserId, $pid));
                        }
                    }
                }

                $mensaje = "Usuario creado y activado correctamente en las explotaciones seleccionadas.";
            }
        }
    }

    // 2. MODIFICAR / EDITAR USUARIO EXISTENTE
    elseif ($action === 'editar_usuario') {
        $idTarget = (int)(isset($_POST['id_usuario']) ? $_POST['id_usuario'] : 0);
        $nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
        $apellidos = trim(isset($_POST['apellidos']) ? $_POST['apellidos'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $telefono = trim(isset($_POST['telefono']) ? $_POST['telefono'] : '');
        $rol = isset($_POST['rol']) ? $_POST['rol'] : 'tecnico';
        $estado = isset($_POST['estado']) ? $_POST['estado'] : 'aprobado';
        $newPassword = isset($_POST['nueva_password']) ? trim($_POST['nueva_password']) : '';

        // Comprobar permisos
        $stmtChk = $db->prepare("SELECT id_usuario, id_propietario, rol FROM usuarios WHERE id_usuario = :id");
        $stmtChk->execute(array(':id' => $idTarget));
        $targetUser = $stmtChk->fetch();

        if (!$targetUser) {
            $error = "El usuario seleccionado no existe.";
        } elseif (!$isSuperAdmin && ((int)$targetUser['id_propietario'] !== (int)$tenantId || $targetUser['rol'] === 'superadmin')) {
            $error = "No tienes permiso para modificar este usuario.";
        } else {
            // Verificar si el email ya pertenece a otro usuario
            $chkEmail = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :id LIMIT 1");
            $chkEmail->execute(array(':email' => $email, ':id' => $idTarget));
            if ($chkEmail->fetch()) {
                $error = "El correo electrónico ya está en uso por otro usuario.";
            } else {
                $idProp = $targetUser['id_propietario'];
                $expSeleccionadas = null;
                if ($isSuperAdmin) {
                    if (isset($_POST['explotaciones']) && is_array($_POST['explotaciones'])) {
                        $expSeleccionadas = array_map('intval', $_POST['explotaciones']);
                        $idProp = !empty($expSeleccionadas) ? $expSeleccionadas[0] : null;
                    } else {
                        $expSeleccionadas = array();
                        $idProp = null;
                    }
                }

                if (!$isSuperAdmin && $rol === 'superadmin') {
                    $rol = $targetUser['rol'];
                }

                // Si se proporcionó nueva contraseña, actualizar el hash
                if (!empty($newPassword)) {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmtUpd = $db->prepare("
                        UPDATE usuarios 
                        SET nombre = :nom, apellidos = :ape, email = :em, telefono = :tel,
                            rol = :rol, estado = :est, id_propietario = :prop, password_hash = :pwd
                        WHERE id_usuario = :id
                    ");
                    $stmtUpd->execute(array(
                        ':nom'  => $nombre,
                        ':ape'  => $apellidos,
                        ':em'   => $email,
                        ':tel'  => $telefono,
                        ':rol'  => $rol,
                        ':est'  => $estado,
                        ':prop' => $idProp,
                        ':pwd'  => $newHash,
                        ':id'   => $idTarget
                    ));
                    $mensaje = "Usuario y contraseña actualizados correctamente.";
                } else {
                    $stmtUpd = $db->prepare("
                        UPDATE usuarios 
                        SET nombre = :nom, apellidos = :ape, email = :em, telefono = :tel,
                            rol = :rol, estado = :est, id_propietario = :prop
                        WHERE id_usuario = :id
                    ");
                    $stmtUpd->execute(array(
                        ':nom'  => $nombre,
                        ':ape'  => $apellidos,
                        ':em'   => $email,
                        ':tel'  => $telefono,
                        ':rol'  => $rol,
                        ':est'  => $estado,
                        ':prop' => $idProp,
                        ':id'   => $idTarget
                    ));
                    $mensaje = "Datos del usuario actualizados correctamente.";
                }

                // Sincronizar tabla intermedia si es superadmin
                if ($isSuperAdmin && $expSeleccionadas !== null) {
                    $db->prepare("DELETE FROM usuarios_explotaciones WHERE id_usuario = :id")->execute(array(':id' => $idTarget));
                    if (!empty($expSeleccionadas)) {
                        $insUE = $db->prepare("INSERT IGNORE INTO usuarios_explotaciones (id_usuario, id_propietario) VALUES (:uid, :pid)");
                        foreach ($expSeleccionadas as $pid) {
                            if ($pid > 0) {
                                $insUE->execute(array(':uid' => $idTarget, ':pid' => $pid));
                            }
                        }
                    }
                }
            }
        }
    }

    // 3. DESCATALOGAR / SUSPENDER / ACTIVAR (CAMBIO RÁPIDO DE ESTADO)
    elseif ($action === 'cambiar_estado') {
        $idTarget = (int)$_POST['id_usuario'];
        $nuevoEstado = $_POST['nuevo_estado'];

        $stmtChk = $db->prepare("SELECT id_usuario, id_propietario, rol FROM usuarios WHERE id_usuario = :id");
        $stmtChk->execute(array(':id' => $idTarget));
        $targetUser = $stmtChk->fetch();

        if ($targetUser) {
            if (!$isSuperAdmin && ((int)$targetUser['id_propietario'] !== (int)$tenantId || $targetUser['rol'] === 'superadmin')) {
                $error = "No tienes permiso para modificar este usuario.";
            } elseif ($idTarget === (int)$currentUser['id']) {
                $error = "No puedes suspender o descatalogar tu propia cuenta en sesión.";
            } else {
                $upd = $db->prepare("UPDATE usuarios SET estado = :est WHERE id_usuario = :id");
                $upd->execute(array(':est' => $nuevoEstado, ':id' => $idTarget));
                $mensaje = "El estado del usuario ha cambiado a: " . htmlspecialchars($nuevoEstado);
            }
        }
    }

    // 4. BORRAR / ELIMINAR USUARIO PERMANENTEMENTE
    elseif ($action === 'borrar_usuario') {
        $idTarget = (int)$_POST['id_usuario'];

        $stmtChk = $db->prepare("SELECT id_usuario, id_propietario, rol, nombre, apellidos FROM usuarios WHERE id_usuario = :id");
        $stmtChk->execute(array(':id' => $idTarget));
        $targetUser = $stmtChk->fetch();

        if (!$targetUser) {
            $error = "El usuario a eliminar no existe.";
        } elseif ($idTarget === (int)$currentUser['id']) {
            $error = "No puedes eliminar tu propia cuenta de usuario.";
        } elseif (!$isSuperAdmin && ((int)$targetUser['id_propietario'] !== (int)$tenantId || $targetUser['rol'] === 'superadmin')) {
            $error = "No tienes permiso para eliminar a este usuario.";
        } else {
            try {
                $del = $db->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
                $del->execute(array(':id' => $idTarget));
                $nombreBorrado = htmlspecialchars(trim($targetUser['nombre'] . ' ' . $targetUser['apellidos']));
                $mensaje = "El usuario <strong>{$nombreBorrado}</strong> ha sido eliminado permanentemente del sistema.";
            } catch (Exception $e) {
                $error = "Error al eliminar usuario: " . $e->getMessage();
            }
        }
    }
}

// Cargar todas las explotaciones para asignación
$explotaciones = $db->query("SELECT id_propietario, nombre_razon_social FROM propietarios ORDER BY nombre_razon_social ASC")->fetchAll();

// Cargar mapa de asignaciones multi-explotación
$stmtUE = $db->query("
    SELECT ue.id_usuario, ue.id_propietario, p.nombre_razon_social
    FROM usuarios_explotaciones ue
    JOIN propietarios p ON ue.id_propietario = p.id_propietario
    ORDER BY p.nombre_razon_social ASC
");
$userExplotacionesMap = array();
$userExpIdsMap = array();
while ($ueRow = $stmtUE->fetch()) {
    $uid = (int)$ueRow['id_usuario'];
    $pid = (int)$ueRow['id_propietario'];
    $userExplotacionesMap[$uid][] = $ueRow['nombre_razon_social'];
    $userExpIdsMap[$uid][] = $pid;
}

// Filtros
$filtroEstado = isset($_GET['filtro_estado']) ? trim($_GET['filtro_estado']) : '';
$filtroExp = isset($_GET['explotacion_id']) ? (int)$_GET['explotacion_id'] : 0;
$filtroTxt = isset($_GET['q']) ? trim($_GET['q']) : '';

// Consultar usuarios según permisos
$sqlUsers = "
    SELECT u.*, p.nombre_razon_social AS nombre_explotacion
    FROM usuarios u
    LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
    WHERE 1=1
";

$params = array();

if (!$isSuperAdmin) {
    $sqlUsers .= " AND u.id_propietario = :tenant";
    $params[':tenant'] = (int)$tenantId;
} elseif ($filtroExp > 0) {
    $sqlUsers .= " AND u.id_propietario = :exp";
    $params[':exp'] = $filtroExp;
}

if (!empty($filtroEstado)) {
    $sqlUsers .= " AND u.estado = :est";
    $params[':est'] = $filtroEstado;
}

if (!empty($filtroTxt)) {
    $sqlUsers .= " AND (u.nombre LIKE :txt OR u.apellidos LIKE :txt OR u.email LIKE :txt)";
    $params[':txt'] = '%' . $filtroTxt . '%';
}

$sqlUsers .= " ORDER BY u.rol = 'superadmin' DESC, u.id_propietario ASC, u.nombre ASC";
$stmtUsers = $db->prepare($sqlUsers);
$stmtUsers->execute($params);
$usuarios = $stmtUsers->fetchAll();
?>

<div class="container py-4">

    <!-- Cabecera Corporativa con Azul Chulo Tecnológico -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-users-gear fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Gestión de Usuarios y Equipo</h2>
                <p class="text-muted mb-0 small">
                    <?php if ($isSuperAdmin): ?>
                        <span class="badge bg-warning text-dark me-1">Administración</span> Control técnico: modificar, dar de baja, descatalogar y reasignar usuarios
                    <?php else: ?>
                        <span class="badge bg-primary me-1">Gestor</span> Equipo de trabajo de tu explotación: <strong><?= htmlspecialchars($currentUser['nombre_propietario']) ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formNuevoUsuario">
            <i class="fa-solid fa-user-plus me-1"></i> Dar de Alta Nuevo Usuario
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

    <!-- Formulario Desplegable para Crear Usuario -->
    <div class="collapse mb-4" id="formNuevoUsuario">
        <div class="card card-ag p-4 p-md-5 border-top border-4 border-primary">
            <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-user-plus me-2"></i>Alta de Nuevo Usuario / Perfil de Trabajo</h5>
            <form action="<?= APP_URL ?>/admin/gestion-usuarios" method="POST">
                <input type="hidden" name="action" value="crear_usuario">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Nombre del trabajador o técnico">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Apellidos</label>
                        <input type="text" name="apellidos" class="form-control" placeholder="Apellidos">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Correo Electrónico (Acceso) <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="usuario@email.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Contraseña Inicial <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="4" placeholder="Contraseña de acceso">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Teléfono</label>
                        <input type="tel" name="telefono" class="form-control" placeholder="600 000 000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Rol / Perfil de Trabajo <span class="text-danger">*</span></label>
                        <select name="rol" class="form-select" required>
                            <option value="tecnico" selected>Técnico / Encargado (Registra labores y riegos)</option>
                            <option value="propietario">Gestor de Explotación (Control total de la finca)</option>
                            <option value="lector">Lector / Auditor (Solo consulta de cuadernos)</option>
                            <?php if ($isSuperAdmin): ?>
                                <option value="superadmin" class="text-danger fw-bold">Administrador General</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <?php if ($isSuperAdmin): ?>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
                                    <label class="form-label fw-bold small text-primary mb-0">
                                        <i class="fa-solid fa-building-wheat me-1"></i> Asignación de Explotaciones Agrarias (Una o Varias)
                                    </label>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" onclick="seleccionarTodasExplotaciones('chk_exp_nuevo')">
                                            <i class="fa-solid fa-check-double me-1"></i> Asignar Todas
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="deseleccionarTodasExplotaciones('chk_exp_nuevo')">
                                            <i class="fa-solid fa-ban me-1"></i> Ninguna
                                        </button>
                                    </div>
                                </div>
                                <div id="chk_exp_nuevo" class="row g-2" style="max-height: 180px; overflow-y: auto;">
                                    <?php foreach ($explotaciones as $exp): ?>
                                        <div class="col-md-4 col-sm-6">
                                            <div class="form-check p-2 rounded bg-white border">
                                                <input class="form-check-input ms-0 me-2" type="checkbox" name="explotaciones[]" value="<?= $exp['id_propietario'] ?>" id="exp_nuevo_<?= $exp['id_propietario'] ?>">
                                                <label class="form-check-label small fw-semibold text-truncate d-block" for="exp_nuevo_<?= $exp['id_propietario'] ?>" title="<?= htmlspecialchars($exp['nombre_razon_social']) ?>">
                                                    <?= htmlspecialchars($exp['nombre_razon_social']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text small text-muted mt-2">Puedes seleccionar individualmente o usar los botones para asignar todas o ninguna.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Explotación Asignada</label>
                            <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($currentUser['nombre_propietario']) ?>">
                            <div class="form-text small">El usuario operará estrictamente en tu explotación.</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-toggle="collapse" data-bs-target="#formNuevoUsuario">Cancelar</button>
                    <button type="submit" class="btn btn-ag px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar y Activar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm">
        <form action="<?= APP_URL ?>/admin/gestion-usuarios" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por nombre o email..." value="<?= htmlspecialchars($filtroTxt) ?>">
            </div>
            <div class="col-md-3">
                <select name="filtro_estado" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los estados --</option>
                    <option value="aprobado" <?= $filtroEstado === 'aprobado' ? 'selected' : '' ?>>Activos (Aprobados)</option>
                    <option value="suspendido" <?= $filtroEstado === 'suspendido' ? 'selected' : '' ?>>Descatalogados / Suspendidos</option>
                    <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendientes de Aprobación</option>
                    <option value="rechazado" <?= $filtroEstado === 'rechazado' ? 'selected' : '' ?>>Rechazados</option>
                </select>
            </div>
            <?php if ($isSuperAdmin && !empty($explotaciones)): ?>
                <div class="col-md-3">
                    <select name="explotacion_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">-- Todas las explotaciones --</option>
                        <?php foreach ($explotaciones as $exp): ?>
                            <option value="<?= $exp['id_propietario'] ?>" <?= ($filtroExp === (int)$exp['id_propietario']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($exp['nombre_razon_social']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Filtrar</button>
                <?php if ($filtroTxt || $filtroEstado || $filtroExp): ?>
                    <a href="<?= APP_URL ?>/admin/gestion-usuarios" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla de Usuarios Registrados -->
    <div class="card card-ag shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-id-card-clip me-2"></i>Usuarios Registrados (<?= count($usuarios) ?>)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th class="ps-4">Usuario</th>
                        <th>Email / Contacto</th>
                        <th>Explotación Agrícola</th>
                        <th>Perfil / Rol</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-users-slash fa-2x mb-3 text-secondary"></i>
                                <p class="mb-0">No se encontraron usuarios con los criterios seleccionados.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u): ?>
                            <?php $isSelf = ((int)$u['id_usuario'] === (int)$currentUser['id']); ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-6">
                                                <?= htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellidos'])) ?>
                                                <?php if ($isSelf): ?>
                                                    <span class="badge bg-info text-dark small ms-1">Tú</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted font-monospace">ID #<?= $u['id_usuario'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><i class="fa-solid fa-envelope text-muted me-1 small"></i><?= htmlspecialchars($u['email']) ?></div>
                                    <?php if ($u['telefono']): ?>
                                        <div class="small text-muted"><i class="fa-solid fa-phone me-1 small text-success"></i><?= htmlspecialchars($u['telefono']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $uidActual = (int)$u['id_usuario'];
                                    $misExplotaciones = isset($userExplotacionesMap[$uidActual]) ? $userExplotacionesMap[$uidActual] : array();
                                    $totalAsignadas = count($misExplotaciones);
                                    ?>
                                    <?php if ($totalAsignadas > 0): ?>
                                        <?php if ($totalAsignadas === 1): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary fw-bold border border-primary-subtle">
                                                <i class="fa-solid fa-building-wheat me-1"></i><?= htmlspecialchars($misExplotaciones[0]) ?>
                                            </span>
                                        <?php elseif (!empty($explotaciones) && $totalAsignadas === count($explotaciones)): ?>
                                            <span class="badge bg-success bg-opacity-15 text-success fw-bold border border-success-subtle" title="<?= htmlspecialchars(implode(', ', $misExplotaciones)) ?>">
                                                <i class="fa-solid fa-check-double me-1"></i>Todas las Explotaciones (<?= $totalAsignadas ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info bg-opacity-15 text-primary fw-bold border border-info-subtle" title="<?= htmlspecialchars(implode(', ', $misExplotaciones)) ?>" style="cursor: help;">
                                                <i class="fa-solid fa-layer-group me-1"></i><?= $totalAsignadas ?> Explotaciones
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($u['nombre_explotacion']): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold border border-primary-subtle">
                                            <i class="fa-solid fa-building-wheat me-1"></i><?= htmlspecialchars($u['nombre_explotacion']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fa-solid fa-globe me-1"></i>Sin Asignar / Global</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['rol'] === 'superadmin'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-shield-halved me-1"></i>Administrador</span>
                                    <?php elseif ($u['rol'] === 'propietario'): ?>
                                        <span class="badge bg-primary"><i class="fa-solid fa-user-tie me-1"></i>Gestor Explotación</span>
                                    <?php elseif ($u['rol'] === 'tecnico'): ?>
                                        <span class="badge bg-info text-dark"><i class="fa-solid fa-tractor me-1"></i>Técnico / Encargado</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border"><i class="fa-solid fa-eye me-1"></i>Lector / Auditor</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['estado'] === 'aprobado'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Activo</span>
                                    <?php elseif ($u['estado'] === 'suspendido'): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i>Descatalogado</span>
                                    <?php elseif ($u['estado'] === 'pendiente'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pendiente</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-capitalize"><?= htmlspecialchars($u['estado']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Botón Editar / Modificar -->
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditar_<?= $u['id_usuario'] ?>" title="Modificar datos / Rol / Contraseña">
                                            <i class="fa-solid fa-pen-to-square"></i> Modificar
                                        </button>

                                        <!-- Botón Descatalogar / Activar rápido -->
                                        <?php if (!$isSelf): ?>
                                            <form action="<?= APP_URL ?>/admin/gestion-usuarios" method="POST" class="d-inline" onsubmit="return confirm('¿Confirmas cambiar el estado a este usuario?');">
                                                <input type="hidden" name="action" value="cambiar_estado">
                                                <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                                <?php if ($u['estado'] === 'aprobado'): ?>
                                                    <input type="hidden" name="nuevo_estado" value="suspendido">
                                                    <button type="submit" class="btn btn-outline-warning" title="Descatalogar / Suspender acceso">
                                                        <i class="fa-solid fa-ban"></i> Descatalogar
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="nuevo_estado" value="aprobado">
                                                    <button type="submit" class="btn btn-outline-success" title="Reactivar acceso">
                                                        <i class="fa-solid fa-check"></i> Activar
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Botón Borrar / Eliminar permanentemente -->
                                            <form action="<?= APP_URL ?>/admin/gestion-usuarios" method="POST" class="d-inline" onsubmit="return confirm('ATENCIÓN: ¿Seguro que deseas ELIMINAR PERMANENTEMENTE al usuario \'<?= htmlspecialchars(addslashes($u['nombre'])) ?>\'?\nEsta acción no se puede deshacer.');">
                                                <input type="hidden" name="action" value="borrar_usuario">
                                                <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Eliminar permanentemente">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Modal Modificar Usuario -->
                                    <div class="modal fade text-start" id="modalEditar_<?= $u['id_usuario'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-user-pen me-2"></i> Modificar Usuario: <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="<?= APP_URL ?>/admin/gestion-usuarios" method="POST">
                                                    <input type="hidden" name="action" value="editar_usuario">
                                                    <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Nombre *</label>
                                                                <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($u['nombre']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Apellidos</label>
                                                                <input type="text" name="apellidos" class="form-control" value="<?= htmlspecialchars($u['apellidos'] ?: '') ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Correo Electrónico *</label>
                                                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Teléfono</label>
                                                                <input type="tel" name="telefono" class="form-control" value="<?= htmlspecialchars($u['telefono'] ?: '') ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Perfil / Rol *</label>
                                                                <select name="rol" class="form-select" required>
                                                                    <option value="tecnico" <?= $u['rol'] === 'tecnico' ? 'selected' : '' ?>>Técnico / Encargado</option>
                                                                    <option value="propietario" <?= $u['rol'] === 'propietario' ? 'selected' : '' ?>>Gestor de Explotación</option>
                                                                    <option value="lector" <?= $u['rol'] === 'lector' ? 'selected' : '' ?>>Lector / Auditor</option>
                                                                    <?php if ($isSuperAdmin): ?>
                                                                        <option value="superadmin" <?= $u['rol'] === 'superadmin' ? 'selected' : '' ?> class="text-danger fw-bold">Administrador General</option>
                                                                    <?php endif; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Estado de la Cuenta *</label>
                                                                <select name="estado" class="form-select" required>
                                                                    <option value="aprobado" <?= $u['estado'] === 'aprobado' ? 'selected' : '' ?>>Activo (Aprobado)</option>
                                                                    <option value="suspendido" <?= $u['estado'] === 'suspendido' ? 'selected' : '' ?>>Descatalogado / Suspendido</option>
                                                                    <option value="pendiente" <?= $u['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                                                    <option value="rechazado" <?= $u['estado'] === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <?php if ($isSuperAdmin): ?>
                                                            <?php 
                                                            $assignedIds = isset($userExpIdsMap[$u['id_usuario']]) ? $userExpIdsMap[$u['id_usuario']] : (!empty($u['id_propietario']) ? array((int)$u['id_propietario']) : array()); 
                                                            ?>
                                                            <div class="mb-3">
                                                                <div class="p-3 bg-light rounded-3 border">
                                                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
                                                                        <label class="form-label fw-bold small text-primary mb-0">
                                                                            <i class="fa-solid fa-building-wheat me-1"></i> Explotaciones Agrarias Asignadas (Una o Varias)
                                                                        </label>
                                                                        <div class="btn-group btn-group-sm">
                                                                            <button type="button" class="btn btn-outline-primary" onclick="seleccionarTodasExplotaciones('chk_exp_edit_<?= $u['id_usuario'] ?>')">
                                                                                <i class="fa-solid fa-check-double me-1"></i> Asignar Todas
                                                                            </button>
                                                                            <button type="button" class="btn btn-outline-secondary" onclick="deseleccionarTodasExplotaciones('chk_exp_edit_<?= $u['id_usuario'] ?>')">
                                                                                <i class="fa-solid fa-ban me-1"></i> Ninguna
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                    <div id="chk_exp_edit_<?= $u['id_usuario'] ?>" class="row g-2" style="max-height: 180px; overflow-y: auto;">
                                                                        <?php foreach ($explotaciones as $exp): ?>
                                                                            <?php $isChecked = in_array((int)$exp['id_propietario'], $assignedIds); ?>
                                                                            <div class="col-md-6">
                                                                                <div class="form-check p-2 rounded bg-white border">
                                                                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="explotaciones[]" value="<?= $exp['id_propietario'] ?>" id="exp_edit_<?= $u['id_usuario'] ?>_<?= $exp['id_propietario'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                                                    <label class="form-check-label small fw-semibold text-truncate d-block" for="exp_edit_<?= $u['id_usuario'] ?>_<?= $exp['id_propietario'] ?>" title="<?= htmlspecialchars($exp['nombre_razon_social']) ?>">
                                                                                        <?= htmlspecialchars($exp['nombre_razon_social']) ?>
                                                                                    </label>
                                                                                </div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                    <div class="form-text small text-muted mt-2">Puedes marcar individualmente una o varias explotaciones, o usar los botones de acción rápida.</div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="bg-light p-3 rounded-3 mt-3 border">
                                                            <label class="form-label fw-semibold small text-primary mb-1">
                                                                <i class="fa-solid fa-key me-1"></i> Cambiar Contraseña (Opcional)
                                                            </label>
                                                            <input type="password" name="nueva_password" class="form-control" placeholder="Dejar en blanco para mantener la contraseña actual" minlength="4">
                                                            <div class="form-text small">Si introduces una nueva contraseña, se cifrará y actualizará de inmediato.</div>
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

<script>
function seleccionarTodasExplotaciones(containerId) {
    var c = document.getElementById(containerId);
    if (!c) return;
    var chks = c.querySelectorAll('input[type="checkbox"]');
    chks.forEach(function(cb) {
        cb.checked = true;
    });
}

function deseleccionarTodasExplotaciones(containerId) {
    var c = document.getElementById(containerId);
    if (!c) return;
    var chks = c.querySelectorAll('input[type="checkbox"]');
    chks.forEach(function(cb) {
        cb.checked = false;
    });
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
