<?php
/**
 * Configuración global de la aplicación AgroDietario
 * Compatible con ejecución en subdirectorios tanto en Local como en Producción
 * (Ej: http://localhost/agrodietario o https://www.moratalla-murcia.com/agricultura)
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

// Función helper para leer variables de entorno
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

// Constantes básicas
define('APP_NAME', env('APP_NAME', 'AgroDietario'));
define('APP_ENV', env('APP_ENV', 'local'));
define('APP_DEBUG', env('APP_DEBUG', true));

// Rutas absolutas del sistema de archivos
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('SRC_PATH', ROOT_PATH . '/src');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Cálculo automático e inteligente de la APP_URL (soporta subcarpetas tanto en local como en producción)
if (!defined('APP_URL')) {
    $configuredUrl = env('APP_URL');
    if (!empty($configuredUrl)) {
        define('APP_URL', rtrim($configuredUrl, '/'));
    } else {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $baseDir = preg_replace('#/public$#', '', str_replace('\\', '/', $scriptDir));
        define('APP_URL', rtrim("{$scheme}://{$host}{$baseDir}", '/'));
    }
}

// Gestión de errores
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
