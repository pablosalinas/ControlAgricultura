<?php
/**
 * Gestor de Conexión a Base de Datos MySQL / MariaDB (PDO)
 * Compatible con PHP 5.6, 7.x, 8.x en XAMPP y Producción (IONOS)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;

    /**
     * Obtiene la instancia única de conexión PDO (Patrón Singleton)
     */
    public static function getConnection() {
        if (self::$instance === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $dbName = env('DB_NAME', 'agrodietario_db');
            $user = env('DB_USER', 'root');
            $pass = env('DB_PASS', '');

            // Corrección inteligente para IONOS si se colocó el host como nombre de BD
            if (strpos($dbName, '.hosting-data.io') !== false) {
                $host = $dbName;
                $parts = explode('.', $dbName);
                $dbName = $parts[0];
            }

            $options = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            );

            // Generar nombres candidatos
            $candidatos = array($dbName);
            if (strpos($dbName, 'db50') === 0) {
                $candidatos[] = 'dbs' . substr($dbName, 2);
            } elseif (strpos($dbName, 'dbs50') === 0) {
                $candidatos[] = 'db' . substr($dbName, 3);
            }
            $candidatos = array_unique($candidatos);

            $ultimoError = null;
            foreach ($candidatos as $cand) {
                try {
                    $dsn = "mysql:host={$host};port={$port};dbname={$cand};charset=utf8mb4";
                    self::$instance = new PDO($dsn, $user, $pass, $options);
                    break;
                } catch (PDOException $e) {
                    $ultimoError = $e;
                }
            }

            // Si los candidatos fallan, intentar auto-descubrimiento sin forzar dbname
            if (self::$instance === null) {
                try {
                    $dsnNoDb = "mysql:host={$host};port={$port};charset=utf8mb4";
                    $pTest = new PDO($dsnNoDb, $user, $pass, $options);
                    $stmt = $pTest->query("SHOW DATABASES");
                    $allDbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($allDbs as $d) {
                        if ($d !== 'information_schema' && $d !== 'performance_schema' && $d !== 'mysql' && $d !== 'sys') {
                            try {
                                $dsnDisc = "mysql:host={$host};port={$port};dbname={$d};charset=utf8mb4";
                                self::$instance = new PDO($dsnDisc, $user, $pass, $options);
                                break;
                            } catch (PDOException $eDisc) {
                                // continuar probando
                            }
                        }
                    }
                } catch (Exception $eDiscovery) {
                    // ignorar fallo de descubrimiento
                }
            }

            // Si sigue sin conectar, gestionar el error limpiamente sin un 'Fatal error' que tumbe la pantalla
            if (self::$instance === null) {
                $isInstallPage = (isset($_SERVER['SCRIPT_NAME']) && (strpos($_SERVER['SCRIPT_NAME'], 'install.php') !== false || strpos($_SERVER['SCRIPT_NAME'], 'test_db.php') !== false));
                if ($isInstallPage) {
                    throw $ultimoError ?: new PDOException("No se pudo conectar a la base de datos.");
                }

                // Renderizar pantalla amigable de diagnóstico en lugar de Fatal Error
                http_response_code(503);
                $installUrl = APP_URL . '/database/install.php';
                $testUrl = APP_URL . '/database/test_db.php';
                $errMsg = $ultimoError ? htmlspecialchars($ultimoError->getMessage()) : 'Acceso denegado o base de datos no configurada';

                echo "<!DOCTYPE html><html lang='es'><head><meta charset='utf-8'><title>Base de Datos no inicializada</title>";
                echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
                echo "<style>body{background:#091e3a; color:#fff; font-family:'Segoe UI',sans-serif; display:flex; align-items:center; min-height:100vh; padding:20px;}</style></head><body>";
                echo "<div class='card shadow-lg mx-auto text-dark p-4 p-md-5 rounded-4' style='max-width:650px; border-top:5px solid #1d4ed8;'>";
                echo "<div class='text-center mb-4'><span class='badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill'>⚙️ Configuración Pendiente en Producción</span></div>";
                echo "<h3 class='fw-bold text-center mb-3 text-primary'>La Base de Datos aún no está lista en IONOS</h3>";
                echo "<p class='text-muted small text-center mb-4'>La aplicación web está funcionando correctamente en el servidor, pero el servicio MySQL no encuentra la base de datos o necesita ser creada en tu panel de IONOS.</p>";
                echo "<div class='alert alert-danger py-2 px-3 small font-monospace mb-4'>{$errMsg}</div>";
                echo "<div class='bg-light p-3 rounded-3 mb-4 small'>";
                echo "<strong>¿Qué debes hacer en tu panel de IONOS?</strong>";
                echo "<ol class='mb-0 mt-2 ps-3 text-muted'>";
                echo "<li>Entra en tu panel de IONOS &gt; <strong>Hosting</strong> &gt; <strong>Bases de datos</strong>.</li>";
                echo "<li>Comprueba si tienes una base de datos MySQL creada o pulsa en <strong>'Crear base de datos'</strong>.</li>";
                echo "<li>Apunta el <strong>Nombre de la base de datos</strong> (suele ser ej. <code>dbs...</code>), el <strong>Usuario</strong> y la <strong>Contraseña</strong>.</li>";
                echo "</ol>";
                echo "</div>";
                echo "<div class='d-flex flex-wrap gap-2 justify-content-center'>";
                echo "<a href='{$installUrl}' class='btn btn-primary fw-bold px-4 py-2'><i class='fa-solid fa-database me-2'></i>Abrir Asistente de Instalación</a>";
                echo "<a href='{$testUrl}' class='btn btn-outline-secondary px-3 py-2'>Ver Diagnóstico</a>";
                echo "</div>";
                echo "</div></body></html>";
                exit;
            }
        }

        return self::$instance;
    }

    /**
     * Comprueba si la conexión es funcional
     */
    public static function testConnection() {
        try {
            self::getConnection();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
