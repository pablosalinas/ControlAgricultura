<?php
/**
 * Diagnóstico de Conexión y Descubrimiento de Base de Datos en IONOS
 */
header('Content-Type: text/html; charset=utf-8');
require_once dirname(__DIR__) . '/config/config.php';

$host = env('DB_HOST', 'db5021806071.hosting-data.io');
$port = env('DB_PORT', '3306');
$dbName = env('DB_NAME', 'db5021806071');
$user = env('DB_USER', 'dbu1550776');
$pass = env('DB_PASS', 'Tortas01.56196798-Salinas_2026_Fincas');

if (strpos($dbName, '.hosting-data.io') !== false) {
    $parts = explode('.', $dbName);
    $dbName = $parts[0];
}

echo "<html><head><title>Diagnóstico Base de Datos IONOS</title>";
echo "<style>body{font-family:system-ui; background:#091e3a; color:#fff; padding:30px; line-height:1.6;} .box{background:#fff; color:#333; padding:20px; border-radius:10px; max-width:700px; margin:auto;} code{background:#eee; padding:2px 6px; border-radius:4px; font-weight:bold; color:#1d4ed8;} pre{background:#f1f5f9; padding:10px; border-radius:6px; overflow:auto;}</style></head><body>";
echo "<div class='box'>";
echo "<h2>🔍 Diagnóstico de Conexión IONOS</h2>";
echo "<p>Host: <code>{$host}:{$port}</code><br>Usuario: <code>{$user}</code><br>Longitud contraseña: " . strlen($pass) . " caracteres</p>";

// TEST 1: Conectar sin DB y listar bases de datos
echo "<h4>Prueba 1: Conexión al servidor sin especificar base de datos</h4>";
try {
    $dsnNoDb = "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsnNoDb, $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ));
    echo "<p style='color:green;'>✅ <strong>¡Conexión aceptada al servidor MySQL!</strong></p>";

    // Usuario autenticado en el servidor
    $userStmt = $pdo->query("SELECT USER() AS u, CURRENT_USER() AS cu");
    $uRow = $userStmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>USER(): <code>{$uRow['u']}</code> | CURRENT_USER(): <code>{$uRow['cu']}</code></p>";

    // Descubrir bases de datos
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p><strong>Bases de datos visibles para este usuario:</strong></p>";
    echo "<pre>" . print_r($dbs, true) . "</pre>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Falló la conexión sin DB: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// TEST 2: Probar bases de datos directas
echo "<h4>Prueba 2: Probar conexión directa con nombres candidatos</h4>";
$candidatos = array(
    'dbs16298625',
    $dbName,
    'dbs5021806071',
    'db5021806071',
    'dbu1550776'
);
$candidatos = array_unique($candidatos);

$targetDb = null;
foreach ($candidatos as $cand) {
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$cand};charset=utf8mb4";
        $p = new PDO($dsn, $user, $pass, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ));
        echo "<p style='color:green;'>✅ ¡ÉXITO! Base de datos válida encontrada: <code>{$cand}</code></p>";
        $targetDb = $cand;
        break;
    } catch (Exception $e) {
        echo "<p style='color:#b91c1c;'>❌ Base <code>{$cand}</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

if ($targetDb && isset($_GET['install']) && $_GET['install'] === '1') {
    echo "<h4>Instalando esquema SQL en <code>{$targetDb}</code>...</h4>";
    try {
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        $dsn = "mysql:host={$host};port={$port};dbname={$targetDb};charset=utf8mb4";
        $p = new PDO($dsn, $user, $pass, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ));
        $p->exec($sql);
        echo "<p style='color:green; font-weight:bold; font-size:18px;'>🎉 ¡TABLAS INSTALADAS CON ÉXITO EN {$targetDb}!</p>";

        // Escribir archivo .env en el servidor
        $envFile = dirname(__DIR__) . '/.env';
        $envData = "APP_NAME=\"AgroDietario\"\nAPP_ENV=production\nAPP_DEBUG=true\nAPP_URL=https://www.moratalla-murcia.com/agricultura\n\nDB_HOST={$host}\nDB_PORT={$port}\nDB_NAME={$targetDb}\nDB_USER={$user}\nDB_PASS={$pass}\n\nCAPTCHA_DRIVER=local_gd\nUPLOAD_MAX_MB=15\n";
        file_put_contents($envFile, $envData);
        echo "<p style='color:green;'>✅ Archivo .env configurado y guardado correctamente.</p>";
        echo "<p><a href='../public/login' style='display:inline-block; background:#10b981; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:bold;'>Ir a Iniciar Sesión</a></p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Error en la instalación: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else if ($targetDb) {
    echo "<p><a href='test_db.php?install=1' style='display:inline-block; background:#10b981; color:#fff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:bold; font-size:16px;'>⚡ Pulsa aquí para Instalar Tablas en {$targetDb} Ahora</a></p>";
}

echo "<hr><p style='text-align:center;'><a href='install.php' style='display:inline-block; background:#1d4ed8; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:bold;'>Ir al Instalador de Tablas</a></p>";
echo "</div></body></html>";
