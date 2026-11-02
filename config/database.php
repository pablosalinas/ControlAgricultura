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

            // Generar nombres candidatos (ej. db5021806071 vs dbs5021806071)
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

            if (self::$instance === null && $ultimoError !== null) {
                throw $ultimoError;
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
