<?php
/**
 * Punto de entrada universal - AgroDietario
 * Detecta y enruta automáticamente tanto en XAMPP como en IONOS (con o sin mod_rewrite)
 */

// Extraer ruta desde REQUEST_URI si no viene en $_GET['route']
if (empty($_GET['route'])) {
    $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Eliminar la carpeta base de la URL (ej: /agricultura o /public)
    if ($scriptDir !== '/' && $scriptDir !== '\\') {
        $uriPath = substr($uriPath, strlen($scriptDir));
    }
    
    // Limpiar nombres de archivos y barras
    $uriPath = preg_replace('#^/?(index\.php|public)/?#i', '', $uriPath);
    $uriPath = trim($uriPath, '/');
    
    if (!empty($uriPath)) {
        $_GET['route'] = $uriPath;
    }
}

require_once __DIR__ . '/public/index.php';
