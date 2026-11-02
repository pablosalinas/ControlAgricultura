<?php
/**
 * Instalador y Asistente de Base de Datos - AgroDietario
 * Compatible con XAMPP y Producción (IONOS / 1&1)
 */

require_once dirname(__DIR__) . '/config/config.php';

$isCli = (php_sapi_name() === 'cli');

$defHost = env('DB_HOST', '127.0.0.1');
$defPort = env('DB_PORT', '3306');
$defDb   = env('DB_NAME', 'agrodietario_db');
$defUser = env('DB_USER', 'root');
$defPass = env('DB_PASS', '');

if (strpos(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '', 'moratalla-murcia.com') !== false && ($defHost === '127.0.0.1' || $defHost === 'localhost')) {
    $defHost = 'db5021806071.hosting-data.io';
    $defDb   = 'db5021806071';
    $defUser = 'dbu1550776';
}

$host = isset($_POST['db_host']) ? trim($_POST['db_host']) : $defHost;
$port = isset($_POST['db_port']) ? trim($_POST['db_port']) : $defPort;
$dbName = isset($_POST['db_name']) ? trim($_POST['db_name']) : $defDb;
$user = isset($_POST['db_user']) ? trim($_POST['db_user']) : $defUser;
$pass = isset($_POST['db_pass']) ? $_POST['db_pass'] : $defPass;

if (strpos($dbName, '.hosting-data.io') !== false) {
    $host = $dbName;
    $parts = explode('.', $dbName);
    $dbName = $parts[0];
}

$instalado = false;
$error = '';
$mensajes = array();
$diagnosticoBases = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $isCli) {
    try {
        // PASO 1: Conectar al servidor MySQL SIN forzar la base de datos en el DSN
        // para evitar el error 1044 si el nombre de BD tiene alguna variación
        $dsnSinBd = "mysql:host={$host};port={$port}";
        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        );

        $mensajes[] = "⏳ Conectando al servidor MySQL <code>{$host}</code> con usuario <code>{$user}</code>...";
        $pdo = new PDO($dsnSinBd, $user, $pass, $options);
        $mensajes[] = "✅ ¡Conexión con el servidor MySQL aceptada exitosamente!";

        // PASO 2: Descubrir a qué bases de datos tiene acceso este usuario
        $stmtDbs = $pdo->query("SHOW DATABASES");
        $dbsDisponibles = $stmtDbs->fetchAll(PDO::FETCH_COLUMN);
        $diagnosticoBases = $dbsDisponibles;

        $mensajes[] = "🔍 Bases de datos accesibles para este usuario: <strong>" . implode(', ', $dbsDisponibles) . "</strong>";

        // Determinar cuál base de datos usar
        $bdFinal = $dbName;
        if (!in_array($bdFinal, $dbsDisponibles)) {
            // Si la que buscamos no está pero hay otra que empiece por db o dbs
            foreach ($dbsDisponibles as $d) {
                if ($d !== 'information_schema' && $d !== 'performance_schema') {
                    $bdFinal = $d;
                    break;
                }
            }
            $mensajes[] = "ℹ️ Conectando automáticamente a la base de datos asignada: <strong>{$bdFinal}</strong>";
        }

        // PASO 3: Seleccionar la base de datos
        $pdo->exec("USE `{$bdFinal}`");
        $mensajes[] = "✅ Base de datos <strong>{$bdFinal}</strong> seleccionada con éxito.";

        // PASO 4: Ejecutar schema.sql
        $sqlFile = __DIR__ . '/schema.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("No se encontró el archivo schema.sql en: " . $sqlFile);
        }

        $mensajes[] = "📖 Leyendo <em>schema.sql</em> y creando todas las tablas maestras...";
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);

        // Guardar configuración funcional en .env
        $envPath = dirname(__DIR__) . '/.env';
        $envContent = "APP_NAME=\"AgroDietario\"\n";
        $envContent .= "APP_ENV=" . (strpos(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '', 'localhost') !== false ? "local" : "production") . "\n";
        $envContent .= "APP_DEBUG=true\n";
        $envContent .= "APP_URL=" . APP_URL . "\n\n";
        $envContent .= "DB_HOST={$host}\n";
        $envContent .= "DB_PORT={$port}\n";
        $envContent .= "DB_NAME={$bdFinal}\n";
        $envContent .= "DB_USER={$user}\n";
        $envContent .= "DB_PASS={$pass}\n\n";
        $envContent .= "CAPTCHA_DRIVER=local_gd\n";
        $envContent .= "UPLOAD_MAX_MB=15\n";

        file_put_contents($envPath, $envContent);
        $mensajes[] = "💾 Archivo <code>.env</code> guardado automáticamente.";

        $instalado = true;

    } catch (PDOException $e) {
        $error = "Fallo de conexión MySQL: [" . $e->getCode() . "] " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador de Base de Datos - AgroDietario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #091e3a; font-family: 'Segoe UI', system-ui, sans-serif; color: #1e293b; padding: 30px 15px; }
        .card-install { background: #ffffff; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); max-width: 680px; margin: auto; border-top: 5px solid #1d4ed8; }
        .btn-install { background: #1d4ed8; color: #fff; font-weight: bold; border: none; padding: 12px; }
        .btn-install:hover { background: #1e40af; color: #fff; }
    </style>
</head>
<body>

<div class="card card-install p-4 p-md-5">
    <div class="d-flex align-items-center gap-3 mb-3 border-bottom pb-3">
        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-3">
            <i class="fa-solid fa-satellite"></i>
        </div>
        <div>
            <h3 class="fw-bold mb-0 text-primary">Instalador de Base de Datos</h3>
            <span class="text-muted small">AgroDietario - Asistente de Configuración (IONOS & Local)</span>
        </div>
    </div>

    <?php if ($instalado): ?>
        <div class="alert alert-success p-4">
            <h5 class="fw-bold text-success mb-2"><i class="fa-solid fa-circle-check me-2"></i>¡Instalación completada con éxito!</h5>
            <?php foreach ($mensajes as $m): ?>
                <p class="mb-1 small"><?= $m ?></p>
            <?php endforeach; ?>
            <hr>
            <p class="mb-2"><strong>Usuario Superadministrador listo:</strong></p>
            <ul class="mb-3 small">
                <li>Email: <code>admin@agrodietario.local</code></li>
                <li>Contraseña: <code>Admin2026@</code></li>
            </ul>
            <a href="../" class="btn btn-success fw-bold px-4 py-2">
                <i class="fa-solid fa-arrow-right me-1"></i> Ir a la Página de Inicio
            </a>
        </div>
    <?php else: ?>

        <?php if ($error): ?>
            <div class="alert alert-danger p-3 mb-4">
                <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?></div>
                <div class="small mt-2">
                    <strong>Datos enviados:</strong> Host: <code><?= htmlspecialchars($host) ?></code> | Usuario: <code><?= htmlspecialchars($user) ?></code> | Longitud contraseña: <?= strlen($pass) ?> caracteres.
                </div>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-4">
                Verifica los datos de conexión de tu base de datos MySQL. Pulsa el botón para conectar y crear todas las tablas automáticamente.
            </p>
        <?php endif; ?>

        <?php if (!empty($mensajes)): ?>
            <div class="alert alert-info p-3 mb-4 small">
                <?php foreach ($mensajes as $m): ?>
                    <p class="mb-1"><?= $m ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold small">Servidor / Host MySQL</label>
                    <input type="text" name="db_host" class="form-control font-monospace" value="<?= htmlspecialchars($host) ?>" required>
                    <div class="form-text small">En IONOS es <code>db5021806071.hosting-data.io</code> (en local <code>127.0.0.1</code>).</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Puerto</label>
                    <input type="number" name="db_port" class="form-control font-monospace" value="<?= htmlspecialchars($port) ?>" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Nombre de la Base de Datos</label>
                    <input type="text" name="db_name" class="form-control font-monospace" value="<?= htmlspecialchars($dbName) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Usuario de MySQL</label>
                    <input type="text" name="db_user" class="form-control font-monospace" value="<?= htmlspecialchars($user) ?>" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold small">Contraseña de MySQL</label>
                <div class="input-group">
                    <input type="password" id="db_pass" name="db_pass" class="form-control font-monospace" value="<?= htmlspecialchars($pass) ?>" required placeholder="Contraseña de la base de datos">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
                        <i class="fa-solid fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
                <div class="form-text small">Pulsa en el ojo para verificar que no haya caracteres cambiados.</div>
            </div>

            <button type="submit" class="btn btn-install w-100 rounded-3 shadow">
                <i class="fa-solid fa-database me-2"></i>Conectar e Instalar Tablas Ahora
            </button>
        </form>

    <?php endif; ?>
</div>

<script>
function togglePass() {
    const input = document.getElementById('db_pass');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

</body>
</html>
