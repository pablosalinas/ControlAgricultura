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
        $_SESSION['id_propietario'] = $user['id_propietario'];
        $_SESSION['nombre_propietario'] = $user['nombre_propietario'];

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
        return self::check() && $_SESSION['user_rol'] === 'superadmin';
    }

    public static function isSuperAdmin() {
        return self::isAdmin();
    }

    /**
     * Comprueba si el usuario tiene permiso para gestionar el equipo (Superadmin o Propietario de la explotación)
     */
    public static function canManageUsers() {
        if (!self::check()) return false;
        return ($_SESSION['user_rol'] === 'superadmin' || $_SESSION['user_rol'] === 'propietario');
    }

    public static function tenantId() {
        return isset($_SESSION['id_propietario']) ? $_SESSION['id_propietario'] : null;
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
