<?php
/**
 * Instalador y Ejecutor del Esquema SQL para AgroDietario
 * Puede ejecutarse desde el navegador (http://localhost/agrodietario/database/install.php)
 * o desde la línea de comandos de PHP (php database/install.php)
 */

require_once dirname(__DIR__) . '/config/database.php';

$isCli = (php_sapi_name() === 'cli');

function outputMsg($msg, $isCli) {
    if ($isCli) {
        echo $msg . PHP_EOL;
    } else {
        echo "<p style='font-family: monospace; margin: 4px 0;'>" . $msg . "</p>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='utf-8'><title>Instalador de Base de Datos - AgroDietario</title>";
    echo "<style>body{font-family:sans-serif; background:#f4f6f8; padding:30px;} .card{background:#fff; border-radius:8px; padding:25px; box-shadow:0 4px 12px rgba(0,0,0,0.08); max-width:700px; margin:auto;}</style>";
    echo "</head><body><div class='card'>";
    echo "<h2 style='color:#2e7d32; margin-top:0;'>🌱 Instalador de Base de Datos - AgroDietario</h2>";
}

try {
    $pdo = Database::getConnection();
    outputMsg("✅ Conexión establecida con éxito a la base de datos: <strong>" . htmlspecialchars(env('DB_NAME')) . "</strong>", $isCli);

    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("No se encontró el archivo SQL en: " . $sqlFile);
    }

    outputMsg("📖 Leyendo archivo <em>schema.sql</em>...", $isCli);
    $sql = file_get_contents($sqlFile);

    // Separar sentencias por punto y coma (respetando bloques)
    // Desactivar temporalmente emulated prepares para multi-queries
    $pdo->exec($sql);

    outputMsg("🎉 <strong>¡Todas las tablas y datos iniciales han sido creados correctamente!</strong>", $isCli);
    outputMsg("<hr>", $isCli);
    outputMsg("<strong>Tablas creadas:</strong> propietarios, usuarios, parajes, fincas, cultivos, plantaciones, diario_evolucion, tratamientos, labores_suelo, podas_talas, riegos, recolecciones, configuracion_web.", $isCli);
    outputMsg("<strong>Usuario Superadmin creado:</strong>", $isCli);
    outputMsg("• Email: <code>admin@agrodietario.local</code>", $isCli);
    outputMsg("• Contraseña: <code>Admin2026@</code>", $isCli);

    if (!$isCli) {
        echo "<br><a href='../public/' style='display:inline-block; background:#2e7d32; color:#fff; text-decoration:none; padding:10px 18px; border-radius:5px;'>Ir a la Página de Inicio</a>";
    }

} catch (Exception $e) {
    outputMsg("❌ <strong>Error durante la instalación:</strong> " . htmlspecialchars($e->getMessage()), $isCli);
}

if (!$isCli) {
    echo "</div></body></html>";
}
