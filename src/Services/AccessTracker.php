<?php
/**
 * Servicio de Rastreo, Auditoría y Estadísticas de Visitas y Accesos a la Administración
 * Compatible con PHP 5.6, 7.x, 8.x
 */

require_once dirname(__DIR__, 2) . '/config/database.php';

class AccessTracker {
    private static $cachedTotal = null;

    /**
     * Registra un acceso en la base de datos
     */
    public static function log($route = '') {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) return;

            // Obtener IP real considerando proxys/load balancers
            $ip = '0.0.0.0';
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ips[0]);
            } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            $ip = substr($ip, 0, 45);

            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 250) : 'Desconocido';
            $method = isset($_SERVER['REQUEST_METHOD']) ? substr($_SERVER['REQUEST_METHOD'], 0, 10) : 'GET';
            $url = isset($_SERVER['REQUEST_URI']) ? substr($_SERVER['REQUEST_URI'], 0, 250) : '/';
            
            // Detectar si es un acceso a la administración o gestión
            $esAdmin = 0;
            if (
                strpos($route, 'admin') !== false || 
                strpos($url, '/admin') !== false || 
                strpos($route, 'gestion-usuarios') !== false ||
                strpos($route, 'configuracion') !== false ||
                (isset($_SESSION['user_rol']) && in_array(strtolower($_SESSION['user_rol']), array('superadmin', 'administrador', 'admin')))
            ) {
                $esAdmin = 1;
            }

            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $nombreUsuario = isset($_SESSION['user_nombre']) ? trim($_SESSION['user_nombre'] . ' ' . (isset($_SESSION['user_apellidos']) ? $_SESSION['user_apellidos'] : '')) : null;
            $rolUsuario = isset($_SESSION['user_rol']) ? $_SESSION['user_rol'] : null;
            $idProp = isset($_SESSION['id_propietario']) ? (int)$_SESSION['id_propietario'] : null;

            $stmt = $pdo->prepare("
                INSERT INTO logs_accesos (
                    ip_address, user_agent, metodo, url_solicitada, ruta,
                    id_usuario, nombre_usuario, rol_usuario, id_propietario,
                    es_admin, fecha_hora
                ) VALUES (
                    :ip, :ua, :met, :url, :ruta,
                    :uid, :nom, :rol, :prop,
                    :admin, NOW()
                )
            ");
            $stmt->execute(array(
                ':ip'    => $ip,
                ':ua'    => $userAgent,
                ':met'   => $method,
                ':url'   => $url,
                ':ruta'  => substr($route, 0, 100),
                ':uid'   => $userId,
                ':nom'   => $nombreUsuario,
                ':rol'   => $rolUsuario,
                ':prop'  => $idProp,
                ':admin' => $esAdmin
            ));
        } catch (Exception $e) {
            // Silencioso para no romper la navegación en caso de error
        }
    }

    /**
     * Retorna el número total de visitas
     */
    public static function getTotalVisits() {
        if (self::$cachedTotal !== null) {
            return self::$cachedTotal;
        }
        try {
            $pdo = Database::getConnection();
            if (!$pdo) return 0;
            self::$cachedTotal = (int)$pdo->query("SELECT COUNT(*) FROM logs_accesos")->fetchColumn();
            return self::$cachedTotal;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Retorna visitas del día de hoy
     */
    public static function getTodayVisits() {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) return 0;
            return (int)$pdo->query("SELECT COUNT(*) FROM logs_accesos WHERE DATE(fecha_hora) = CURDATE()")->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Retorna número de accesos al área de administración
     */
    public static function getAdminVisits() {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) return 0;
            return (int)$pdo->query("SELECT COUNT(*) FROM logs_accesos WHERE es_admin = 1")->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Retorna el número de IPs únicas
     */
    public static function getUniqueIPs() {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) return 0;
            return (int)$pdo->query("SELECT COUNT(DISTINCT ip_address) FROM logs_accesos")->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Obtiene registros filtrados para el panel de estadísticas y auditoría
     */
    public static function getLogs($filtros = array(), $limit = 100) {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) return array();

            $sql = "SELECT * FROM logs_accesos WHERE 1=1";
            $params = array();

            if (!empty($filtros['solo_admin'])) {
                $sql .= " AND es_admin = 1";
            }
            if (!empty($filtros['ip'])) {
                $sql .= " AND ip_address LIKE :ip";
                $params[':ip'] = '%' . trim($filtros['ip']) . '%';
            }
            if (!empty($filtros['usuario'])) {
                $sql .= " AND (nombre_usuario LIKE :usr OR rol_usuario LIKE :usr)";
                $params[':usr'] = '%' . trim($filtros['usuario']) . '%';
            }
            if (!empty($filtros['fecha_desde'])) {
                $sql .= " AND fecha_hora >= :fdesde";
                $params[':fdesde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }
            if (!empty($filtros['fecha_hasta'])) {
                $sql .= " AND fecha_hora <= :fhasta";
                $params[':fhasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            $sql .= " ORDER BY fecha_hora DESC LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return array();
        }
    }
}
