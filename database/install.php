<?php
/**
 * Instalador y Asistente de Base de Datos - AgroDietario
 * Compatible con XAMPP local y Producción (IONOS / 1&1)
 */

require_once dirname(__DIR__) . '/config/config.php';

$isCli = (php_sapi_name() === 'cli');

$defHost = env('DB_HOST', '127.0.0.1');
$defPort = env('DB_PORT', '3306');
$defDb   = env('DB_NAME', 'agrodietario_db');
$defUser = env('DB_USER', 'root');
$defPass = env('DB_PASS', '');

// Valores sugeridos si estamos en producción y aún están los de local
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

// Si en db_name pegaron el host completo (ej: db5021806071.hosting-data.io)
if (strpos($dbName, '.hosting-data.io') !== false) {
    $host = $dbName;
    $parts = explode('.', $dbName);
    $dbName = $parts[0];
}

$instalado = false;
$error = '';
$mensajes = array();
$basesDetectadas = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $isCli) {
    $pdo = null;
    $bdConectada = null;

    $options = array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    );

    // ESTRATEGIA 1: Probar conexión sin forzar base de datos para auto-descubrir las BD asignadas
    try {
        $dsnSinBd = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pTest = new PDO($dsnSinBd, $user, $pass, $options);
        $mensajes[] = "✅ ¡Usuario <code>{$user}</code> autenticado correctamente en el servidor!";
        
        // Consultar bases de datos disponibles
        $stmt = $pTest->query("SHOW DATABASES");
        $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $filtradas = array();
        foreach ($dbs as $d) {
            if ($d !== 'information_schema' && $d !== 'performance_schema' && $d !== 'mysql' && $d !== 'sys') {
                $filtradas[] = $d;
            }
        }
        $basesDetectadas = $filtradas;

        if (!empty($filtradas)) {
            $mensajes[] = "🔍 Bases de datos encontradas en tu cuenta: <strong>" . implode(', ', $filtradas) . "</strong>";
            // Si la introducida está en la lista la usamos, si no, usamos la primera válida
            if (in_array($dbName, $filtradas)) {
                $bdConectada = $dbName;
            } else {
                $bdConectada = $filtradas[0];
                $mensajes[] = "👉 Conectando a la base de datos detectada: <strong>{$bdConectada}</strong>";
            }
            
            $dsnConBd = "mysql:host={$host};port={$port};dbname={$bdConectada};charset=utf8mb4";
            $pdo = new PDO($dsnConBd, $user, $pass, $options);
        }
    } catch (Exception $e) {
        // Si no permite conectar sin BD, pasamos a la Estrategia 2
    }

    // ESTRATEGIA 2: Si no se conectó en Estrategia 1, probar nombres candidatos directamente
    if (!$pdo) {
        $candidatos = array($dbName);
        if (strpos($dbName, 'db50') === 0) {
            $candidatos[] = 'dbs' . substr($dbName, 2);
        } elseif (strpos($dbName, 'dbs50') === 0) {
            $candidatos[] = 'db' . substr($dbName, 3);
        }
        $candidatos = array_unique($candidatos);

        $intentos = array();
        foreach ($candidatos as $cand) {
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$cand};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, $options);
                $bdConectada = $cand;
                $mensajes[] = "✅ Conexión establecida con la base de datos <strong>{$cand}</strong>.";
                break;
            } catch (PDOException $e) {
                $intentos[$cand] = $e->getMessage();
            }
        }

        if (!$pdo) {
            $detalles = array();
            foreach ($intentos as $c => $err) {
                $detalles[] = "<li>Base de datos <code>{$c}</code>: " . htmlspecialchars($err) . "</li>";
            }
            $error = "El servidor rechazó el acceso a la base de datos.<br><ul class='mt-2 mb-0'>" . implode('', $detalles) . "</ul>";
        }
    }

    // Si tenemos conexión exitosa, instalar las tablas
    if ($pdo && $bdConectada) {
        try {
            $sqlFile = __DIR__ . '/schema.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("No se encontró el archivo schema.sql en: " . $sqlFile);
            }

            $mensajes[] = "📖 Leyendo <em>schema.sql</em> y creando tablas...";
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
            $mensajes[] = "✅ Tablas creadas y superadministrador <strong>pablosalinasmarin@gmail.com</strong> activado.";

            // Guardar configuración funcional en .env
            $envPath = dirname(__DIR__) . '/.env';
            $envContent = "APP_NAME=\"AgroDietario\"\n";
            $envContent .= "APP_ENV=" . (strpos(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '', 'localhost') !== false ? "local" : "production") . "\n";
            $envContent .= "APP_DEBUG=true\n";
            $envContent .= "APP_URL=" . APP_URL . "\n\n";
            $envContent .= "DB_HOST={$host}\n";
            $envContent .= "DB_PORT={$port}\n";
            $envContent .= "DB_NAME={$bdConectada}\n";
            $envContent .= "DB_USER={$user}\n";
            $envContent .= "DB_PASS={$pass}\n\n";
            $envContent .= "CAPTCHA_DRIVER=local_gd\n";
            $envContent .= "UPLOAD_MAX_MB=15\n";

            file_put_contents($envPath, $envContent);
            $mensajes[] = "💾 Archivo <code>.env</code> actualizado con la base de datos <strong>{$bdConectada}</strong>.";

            $instalado = true;

        } catch (Exception $e) {
            $error = "Error al ejecutar el esquema SQL: " . $e->getMessage();
        }
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
        .card-install { background: #ffffff; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); max-width: 720px; margin: auto; border-top: 5px solid #1d4ed8; }
        .btn-install { background: #1d4ed8; color: #fff; font-weight: bold; border: none; padding: 12px; }
        .btn-install:hover { background: #1e40af; color: #fff; }
        .ionos-hint { background: #f0f7ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 12px 16px; }
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
            <p class="mb-2"><strong>Superadministradores activos en el sistema:</strong></p>
            <ul class="mb-3 small">
                <li><code>pablosalinasmarin@gmail.com</code> (Contraseña: <code>p1s2m3</code>)</li>
                <li><code>admin@agrodietario.local</code> (Contraseña: <code>Admin2026@</code>)</li>
            </ul>
            <a href="../login" class="btn btn-success fw-bold px-4 py-2">
                <i class="fa-solid fa-arrow-right me-1"></i> Ir a Iniciar Sesión
            </a>
        </div>
    <?php else: ?>

        <?php if ($error): ?>
            <div class="alert alert-danger p-3 mb-4">
                <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Fallo en la conexión</div>
                <div class="small mt-2"><?= $error ?></div>
            </div>

            <div class="ionos-hint mb-4 small">
                <div class="fw-bold text-primary mb-1"><i class="fa-solid fa-circle-info me-1"></i> ¿Cómo comprobar el Nombre exacto de tu Base de Datos en IONOS?</div>
                <p class="mb-1 text-muted">
                    En el panel de IONOS, el <strong>Servidor</strong> suele ser <code>db5021806071.hosting-data.io</code>, pero el <strong>Nombre de la base de datos</strong> tiene su propio código asignado por IONOS (frecuentemente empieza por <code>dbs...</code>, por ejemplo <code>dbs12345678</code>).
                </p>
                <ol class="mb-0 ps-3 text-muted">
                    <li>Entra en tu panel de IONOS &gt; <strong>Hosting</strong> &gt; <strong>Bases de datos</strong>.</li>
                    <li>Fíjate en la columna <strong>"Nombre de la base de datos"</strong>.</li>
                    <li>Copia ese nombre exacto y pégalo abajo en la casilla <em>"Nombre de la Base de Datos"</em>.</li>
                </ol>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-4">
                Verifica los datos de conexión de tu base de datos MySQL. El instalador intentará detectar automáticamente tu base de datos y crear todas las tablas en un solo clic.
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
                    <div class="form-text small">El nombre que figura en tu panel de IONOS &gt; Bases de datos.</div>
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
