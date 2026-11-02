<?php
/**
 * Instalador y Ejecutor del Esquema SQL para AgroDietario
 * Compatible con XAMPP local y Producción (IONOS / 1&1)
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

$isCli = (php_sapi_name() === 'cli');

function outputMsg($msg, $isCli) {
    if ($isCli) {
        echo strip_tags($msg) . PHP_EOL;
    } else {
        echo "<p style='font-family: -apple-system, system-ui, sans-serif; margin: 8px 0; font-size: 15px;'>" . $msg . "</p>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='utf-8'><title>Instalador de Base de Datos - AgroDietario</title>";
    echo "<style>body{font-family:'Segoe UI', sans-serif; background:#0f172a; padding:40px 20px;} .card{background:#ffffff; border-radius:12px; padding:35px; box-shadow:0 10px 30px rgba(0,0,0,0.3); max-width:720px; margin:auto; border-top: 5px solid #1d4ed8;}</style>";
    echo "</head><body><div class='card'>";
    echo "<h2 style='color:#1d4ed8; margin-top:0;'>🌱 Instalador de Base de Datos - AgroDietario</h2>";
}

$host = env('DB_HOST', '127.0.0.1');
$port = env('DB_PORT', '3306');
$dbName = env('DB_NAME', 'agrodietario_db');
$user = env('DB_USER', 'root');
$pass = env('DB_PASS', '');

// Corrección automática si se introdujo el host como nombre de BD
if (strpos($dbName, '.hosting-data.io') !== false) {
    $host = $dbName;
    $parts = explode('.', $dbName);
    $dbName = $parts[0];
}

outputMsg("<strong>Parámetros detectados:</strong> Host: <code>{$host}:{$port}</code> | BD: <code>{$dbName}</code> | Usuario: <code>{$user}</code>", $isCli);

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $options = array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $pdo = new PDO($dsn, $user, $pass, $options);
    outputMsg("✅ Conexión establecida con éxito con el servidor MySQL.", $isCli);

    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("No se encontró el archivo schema.sql en: " . $sqlFile);
    }

    outputMsg("📖 Leyendo archivo <em>schema.sql</em> y generando tablas maestras...", $isCli);
    $sql = file_get_contents($sqlFile);

    // Ejecutar sentencias SQL
    $pdo->exec($sql);

    outputMsg("🎉 <strong style='color:#16a34a;'>¡Todas las tablas y datos iniciales han sido creados correctamente en Producción!</strong>", $isCli);
    outputMsg("<hr style='border:0; border-top:1px solid #e2e8f0; margin:15px 0;'>", $isCli);
    outputMsg("<strong>Tablas configuradas:</strong> propietarios, usuarios, parajes, fincas, cultivos, plantaciones, diario_evolucion, tratamientos, labores_suelo, podas_talas, riegos, recolecciones, configuracion_web.", $isCli);
    outputMsg("<strong>Usuario Administrador creado:</strong>", $isCli);
    outputMsg("• Email: <code>admin@agrodietario.local</code>", $isCli);
    outputMsg("• Contraseña: <code>Admin2026@</code>", $isCli);

    if (!$isCli) {
        echo "<br><a href='../' style='display:inline-block; background:#1d4ed8; color:#fff; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:bold;'>Ir a la Página de Inicio</a>";
    }

} catch (PDOException $e) {
    outputMsg("<div style='background:#fee2e2; border-left:4px solid #dc2626; padding:15px; border-radius:6px; color:#991b1b; margin-top:15px;'>", $isCli);
    outputMsg("❌ <strong>Fallo de conexión a la Base de Datos:</strong> " . htmlspecialchars($e->getMessage()), $isCli);
    outputMsg("<p style='margin-bottom:0;'>Host intentado: <code>{$host}</code> | Base de datos: <code>{$dbName}</code> | Usuario: <code>{$user}</code></p>", $isCli);
    outputMsg("</div>", $isCli);
} catch (Exception $e) {
    outputMsg("❌ <strong>Error general:</strong> " . htmlspecialchars($e->getMessage()), $isCli);
}

if (!$isCli) {
    echo "</div></body></html>";
}
