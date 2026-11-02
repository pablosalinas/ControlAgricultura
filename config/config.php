<?php
/**
 * Configuración global de la aplicación AgroDietario
 * Carga variables de entorno desde .env si existe, o utiliza valores por defecto
 */

if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Quitar comillas dobles o simples si las tuviera
                if (preg_match('/^"(.*)"$/', $value, $m) || preg_match('/^\'(.*)\'$/', $value, $m)) {
                    $value = $m[1];
                }

                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
        return true;
    }
}

// Cargar .env desde la raíz del proyecto
loadEnv(dirname(__DIR__) . '/.env');

// Función helper para obtener variables de entorno con valor fallback
if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val === false) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        if ($val === 'true') return true;
        if ($val === 'false') return false;
        return $val;
    }
}

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Constantes de la aplicación
define('APP_NAME', env('APP_NAME', 'AgroDietario'));
define('APP_ENV', env('APP_ENV', 'local'));
define('APP_DEBUG', env('APP_DEBUG', true));
define('APP_URL', rtrim(env('APP_URL', 'http://localhost/agrodietario/public'), '/'));

// Rutas de directorios
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('SRC_PATH', ROOT_PATH . '/src');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Control de errores según entorno
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
