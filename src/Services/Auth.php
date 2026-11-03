<?php
/**
 * Servicio de Autenticación, Sesiones y Control de Accesos Multi-tenant
 * Compatible con PHP 5.6, 7.x, 8.x
 */

require_once dirname(__DIR__, 2) . '/config/database.php';

class Auth {
    /**
     * Intenta autenticar un usuario
     */
    public static function attempt($email, $password) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, p.nombre_razon_social AS nombre_propietario
            FROM usuarios u
            LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->execute(array(':email' => trim($email)));
        $user = $stmt->fetch();

        if (!$user) {
            return array('success' => false, 'message' => 'Credenciales incorrectas.');
        }

        if (!password_verify($password, $user['password_hash'])) {
            return array('success' => false, 'message' => 'Credenciales incorrectas.');
        }

        // Comprobación del estado de aprobación
        if ($user['estado'] === 'pendiente') {
            return array(
                'success' => false,
                'status' => 'pendiente',
                'message' => 'Tu solicitud de acceso está registrada pero aún está pendiente de aprobación por el administrador.'
            );
        }

        if ($user['estado'] === 'rechazado' || $user['estado'] === 'suspendido') {
            return array(
                'success' => false,
                'status' => $user['estado'],
                'message' => 'Tu cuenta se encuentra ' . $user['estado'] . '. Contacta con el administrador.'
            );
        }

        // Actualizar último acceso
        $upd = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id");
        $upd->execute(array(':id' => $user['id_usuario']));

        // Guardar sesión
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user_nombre'] = $user['nombre'];
        $_SESSION['user_apellidos'] = $user['apellidos'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_rol'] = $user['rol'];
        
        // Cargar explotaciones asignadas al usuario
        $tenants = self::getUserTenants($user['id_usuario']);
        $_SESSION['user_tenants'] = array_column($tenants, 'id_propietario');

        // Si no tiene id_propietario definido pero tiene asignaciones, asignar la primera
        $activePropId = $user['id_propietario'];
        $activePropNombre = $user['nombre_propietario'];

        if (empty($activePropId) && !empty($tenants)) {
            $activePropId = $tenants[0]['id_propietario'];
            $activePropNombre = $tenants[0]['nombre_razon_social'];
        }

        $_SESSION['id_propietario'] = $activePropId;
        $_SESSION['nombre_propietario'] = $activePropNombre ?: 'Sin asignar';

        return array('success' => true, 'user' => $user);
    }

    /**
     * Registra una nueva solicitud de usuario (quedando en estado pendiente)
     */
    public static function register($data) {
        $db = Database::getConnection();

        // Verificar si el email ya existe
        $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1");
        $check->execute(array(':email' => trim($data['email'])));
        if ($check->fetch()) {
            return array('success' => false, 'message' => 'Ya existe una cuenta con este correo electrónico.');
        }

        // Si se indica nombre de empresa/explotación nueva, crear la entidad de propietario
        $idPropietario = null;
        if (!empty($data['empresa_o_propietario'])) {
            $stmtProp = $db->prepare("
                INSERT INTO propietarios (nombre_razon_social, cif_nif, telefono, email, activo)
                VALUES (:nombre, :cif, :tel, :email, 1)
            ");
            $stmtProp->execute(array(
                ':nombre' => trim($data['empresa_o_propietario']),
                ':cif'    => isset($data['cif_nif']) ? $data['cif_nif'] : null,
                ':tel'    => isset($data['telefono']) ? $data['telefono'] : null,
                ':email'  => trim($data['email'])
            ));
            $idPropietario = (int)$db->lastInsertId();
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt = $db->prepare("
            INSERT INTO usuarios (
                id_propietario, nombre, apellidos, email, password_hash, telefono,
                rol, estado, motivo_solicitud, fecha_registro
            ) VALUES (
                :id_prop, :nombre, :apellidos, :email, :hash, :tel,
                'propietario', 'pendiente', :motivo, NOW()
            )
        ");

        $stmt->execute(array(
            ':id_prop'   => $idPropietario,
            ':nombre'    => trim($data['nombre']),
            ':apellidos' => isset($data['apellidos']) ? trim($data['apellidos']) : '',
            ':email'     => trim($data['email']),
            ':hash'      => $hash,
            ':tel'       => isset($data['telefono']) ? trim($data['telefono']) : '',
            ':motivo'    => isset($data['motivo']) ? trim($data['motivo']) : 'Solicitud desde el portal web'
        ));

        return array(
            'success' => true,
            'message' => 'Solicitud enviada correctamente. El administrador revisará tus datos para aprobar el acceso.'
        );
    }

    public static function check() {
        return !empty($_SESSION['user_id']);
    }

    public static function user() {
        if (!self::check()) return null;
        return array(
            'id' => $_SESSION['user_id'],
            'nombre' => $_SESSION['user_nombre'],
            'apellidos' => isset($_SESSION['user_apellidos']) ? $_SESSION['user_apellidos'] : '',
            'nombre_completo' => trim($_SESSION['user_nombre'] . ' ' . (isset($_SESSION['user_apellidos']) ? $_SESSION['user_apellidos'] : '')),
            'email' => $_SESSION['user_email'],
            'rol' => $_SESSION['user_rol'],
            'id_propietario' => isset($_SESSION['id_propietario']) ? $_SESSION['id_propietario'] : null,
            'nombre_propietario' => isset($_SESSION['nombre_propietario']) ? $_SESSION['nombre_propietario'] : 'Sin asignar'
        );
    }

    public static function isAdmin() {
        if (!self::check()) return false;
        $rol = isset($_SESSION['user_rol']) ? strtolower(trim($_SESSION['user_rol'])) : '';
        return in_array($rol, array('superadmin', 'administrador', 'admin'));
    }

    public static function isSuperAdmin() {
        if (!self::check()) return false;
        $rol = isset($_SESSION['user_rol']) ? strtolower(trim($_SESSION['user_rol'])) : '';
        return ($rol === 'superadmin');
    }

    /**
     * Comprueba si el usuario tiene permiso para gestionar el equipo (Únicamente Superadmin o Administrador)
     * Los usuarios regulares (propietarios, técnicos, operarios) NO tienen acceso a la gestión de usuarios ni a administradores
     */
    public static function canManageUsers() {
        return self::isAdmin();
    }

    public static function tenantId() {
        return isset($_SESSION['id_propietario']) ? $_SESSION['id_propietario'] : null;
    }

    /**
     * Obtiene la lista de explotaciones asignadas a un usuario específico
     */
    public static function getUserTenants($userId) {
        $db = Database::getConnection();
        if (!$db || empty($userId)) return array();

        $stmt = $db->prepare("
            SELECT p.id_propietario, p.nombre_razon_social, p.cif_nif, p.logo, p.municipio
            FROM usuarios_explotaciones ue
            INNER JOIN propietarios p ON ue.id_propietario = p.id_propietario
            WHERE ue.id_usuario = :uid
            ORDER BY p.nombre_razon_social ASC
        ");
        $stmt->execute(array(':uid' => (int)$userId));
        return $stmt->fetchAll();
    }

    /**
     * Comprueba si el usuario en sesión tiene acceso a una determinada explotación
     */
    public static function canAccessTenant($idPropietario) {
        if (!self::check()) return false;
        if (self::isSuperAdmin()) return true;
        if (empty($idPropietario)) return false;

        $idPropInt = (int)$idPropietario;
        if (isset($_SESSION['id_propietario']) && (int)$_SESSION['id_propietario'] === $idPropInt) {
            return true;
        }

        if (isset($_SESSION['user_tenants']) && is_array($_SESSION['user_tenants'])) {
            return in_array($idPropInt, array_map('intval', $_SESSION['user_tenants']));
        }

        // Consultar DB si no está en sesión
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT 1 FROM usuarios_explotaciones WHERE id_usuario = :uid AND id_propietario = :pid LIMIT 1");
        $stmt->execute(array(':uid' => (int)$_SESSION['user_id'], ':pid' => $idPropInt));
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Permite conmutar de explotación activa (Superadmin a cualquiera, o usuario a cualquiera de sus asignadas)
     */
    public static function switchTenant($idPropietario) {
        if (!self::check()) {
            return false;
        }

        $db = Database::getConnection();
        if (empty($idPropietario)) {
            if (self::isSuperAdmin()) {
                $_SESSION['id_propietario'] = null;
                $_SESSION['nombre_propietario'] = 'Vista Global (Todas)';
                return true;
            }
            return false;
        }

        $idPropInt = (int)$idPropietario;
        if (!self::isSuperAdmin() && !self::canAccessTenant($idPropInt)) {
            return false;
        }

        $stmt = $db->prepare("SELECT id_propietario, nombre_razon_social FROM propietarios WHERE id_propietario = :id LIMIT 1");
        $stmt->execute(array(':id' => $idPropInt));
        if ($prop = $stmt->fetch()) {
            $_SESSION['id_propietario'] = (int)$prop['id_propietario'];
            $_SESSION['nombre_propietario'] = $prop['nombre_razon_social'];
            return true;
        }
        return false;
    }

    /**
     * Actualiza en la sesión el nombre del propietario actual si fue editado
     */
    public static function refreshTenantName() {
        if (empty($_SESSION['id_propietario'])) return;
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT nombre_razon_social FROM propietarios WHERE id_propietario = :id LIMIT 1");
        $stmt->execute(array(':id' => (int)$_SESSION['id_propietario']));
        if ($prop = $stmt->fetch()) {
            $_SESSION['nombre_propietario'] = $prop['nombre_razon_social'];
        }
    }

    public static function logout() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
