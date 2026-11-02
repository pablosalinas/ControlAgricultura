<?php
/**
 * Gestor de Conexión a Base de Datos MySQL / MariaDB (PDO)
 * Compatible con XAMPP local y Producción (IONOS / 1&1)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    /**
     * Obtiene la instancia única de conexión PDO (Patrón Singleton)
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $dbName = env('DB_NAME', 'agrodietario_db');
            $user = env('DB_USER', 'root');
            $pass = env('DB_PASS', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    die("<h3>Error al conectar con la Base de Datos:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>" .
                        "<p><strong>Host:</strong> {$host} | <strong>BD:</strong> {$dbName} | <strong>Usuario:</strong> {$user}</p>");
                } else {
                    die("Lo sentimos, no es posible conectar con el servicio de base de datos en este momento.");
                }
            }
        }

        return self::$instance;
    }

    /**
     * Comprueba si la conexión es funcional
     */
    public static function testConnection(): bool {
        try {
            self::getConnection();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
