<?php
/**
 * Front Controller y Enrutador Principal - AgroDietario
 */

// Iniciar buffer de salida temprano para prevenir fallos de cabeceras en descargas o redirecciones
if (!ob_get_level()) {
    ob_start();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once SRC_PATH . '/Services/Auth.php';
require_once SRC_PATH . '/Services/Captcha.php';
require_once SRC_PATH . '/Services/Mailer.php';
require_once SRC_PATH . '/Services/DatabaseMigrator.php';
require_once SRC_PATH . '/Services/AccessTracker.php';

// Auto-migración y verificación de esquema
DatabaseMigrator::run();

// Obtener la ruta solicitada
$route = isset($_GET['route']) ? $_GET['route'] : '';
$route = trim($route, '/');

// Registro de auditoría y visitas
AccessTracker::log($route);

// Enrutamiento
switch ($route) {
    case '':
        require_once VIEWS_PATH . '/home.php';
        break;

    case 'login':
        if (Auth::check()) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }

        $error = '';
        $info = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $captchaInput = isset($_POST['captcha_math']) ? $_POST['captcha_math'] : '';
            if (!Captcha::validateMath($captchaInput)) {
                $error = 'El control de seguridad anti-robot es incorrecto. Por favor, resuelva la suma matemática.';
            } else {
                $email = isset($_POST['email']) ? $_POST['email'] : '';
                $password = isset($_POST['password']) ? $_POST['password'] : '';
                $result = Auth::attempt($email, $password);

                if ($result['success']) {
                    header("Location: " . APP_URL . "/dashboard");
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
        }
        $mathChallenge = Captcha::generateMathChallenge();
        require_once VIEWS_PATH . '/auth/login.php';
        break;

    case 'register':
        if (Auth::check()) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }

        $error = '';
        $success = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $captchaInput = isset($_POST['captcha_input']) ? $_POST['captcha_input'] : '';
            if (!Captcha::validate($captchaInput)) {
                $error = 'El código de seguridad (Captcha) es incorrecto o ha caducado. Inténtalo de nuevo.';
            } elseif (empty($_POST['nombre']) || empty($_POST['email']) || empty($_POST['password'])) {
                $error = 'Por favor, completa todos los campos obligatorios (*).';
            } else {
                $regResult = Auth::register($_POST);
                if ($regResult['success']) {
                    $success = $regResult['message'];
                } else {
                    $error = $regResult['message'];
                }
            }
        }
        require_once VIEWS_PATH . '/auth/register.php';
        break;

    case 'logout':
        Auth::logout();
        header("Location: " . APP_URL . "/");
        exit;

    case 'dashboard':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/dashboard.php';
        break;

    // Cuaderno Oficial / Informes
    case 'cuaderno':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/informes/cuaderno.php';
        break;

    // Rutas de Administración y Equipo
    case 'explotaciones':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/explotaciones/index.php';
        break;

    case 'admin/gestion-usuarios':
        if (!Auth::isAdmin()) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }
        require_once VIEWS_PATH . '/admin/gestion_usuarios.php';
        break;

    case 'admin/usuarios':
        if (!Auth::isAdmin()) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }
        require_once VIEWS_PATH . '/admin/usuarios.php';
        break;

    case 'admin/configuracion':
        if (!Auth::isAdmin()) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }
        require_once VIEWS_PATH . '/admin/configuracion.php';
        break;

    case 'admin/accesos':
    case 'admin/estadisticas':
        if (!Auth::isAdmin()) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }
        require_once VIEWS_PATH . '/admin/estadisticas_accesos.php';
        break;

    // Rutas para Fincas y Parajes
    case 'fincas':
    case 'fincas/nueva':
    case 'fincas/detalle':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        $parts = explode('/', $route);
        $sub = isset($parts[1]) ? $parts[1] : 'index';
        if ($sub === 'nueva') {
            $viewFile = VIEWS_PATH . '/fincas/form.php';
        } elseif ($sub === 'detalle') {
            $viewFile = VIEWS_PATH . '/fincas/detalle.php';
        } else {
            $viewFile = VIEWS_PATH . '/fincas/index.php';
        }
        require_once $viewFile;
        break;

    case 'parajes':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/fincas/parajes.php';
        break;

    case 'regantes':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/regantes/index.php';
        break;

    case 'comunidades':
    case 'comunidades-regantes':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/comunidades/index.php';
        break;

    // Rutas para Cultivos
    case 'cultivos':
    case 'cultivos/nuevo':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        $parts = explode('/', $route);
        $sub = isset($parts[1]) ? $parts[1] : 'index';
        $viewFile = VIEWS_PATH . '/cultivos/' . ($sub === 'nuevo' ? 'form.php' : 'index.php');
        require_once $viewFile;
        break;

    // Rutas para Plantaciones
    case 'plantaciones':
    case 'plantaciones/nueva':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        $parts = explode('/', $route);
        $sub = isset($parts[1]) ? $parts[1] : 'index';
        $viewFile = VIEWS_PATH . '/plantaciones/' . ($sub === 'nueva' ? 'form.php' : 'index.php');
        require_once $viewFile;
        break;

    // Rutas para Labores de Campo y Cuaderno de Explotación
    case 'tratamientos':
    case 'tratamientos/nuevo':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/labores/tratamientos.php';
        break;

    case 'labores':
    case 'labores/nueva':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/labores/suelo.php';
        break;

    case 'podas':
    case 'podas/nueva':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/labores/podas.php';
        break;

    case 'riegos':
    case 'riegos/nuevo':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/labores/riegos.php';
        break;

    case 'solicitud-riego':
    case 'riegos/solicitud':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/labores/solicitud_riego.php';
        break;

    case 'partes-riego':
    case 'solicitudes-riego':
    case 'riegos/partes':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/labores/partes_riego.php';
        break;

    case 'recolecciones':
    case 'recolecciones/nueva':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/labores/recolecciones.php';
        break;

    case 'diario':
    case 'diario/nuevo':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/labores/diario.php';
        break;

    // Rutas para Inventario de Maquinaria y Herramientas (Manuales y Mecánicas)
    case 'maquinaria':
    case 'herramientas':
    case 'maquinaria/nueva':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/maquinaria/index.php';
        break;

    // Rutas para Plan Oficial de Labores y Recomendaciones Agronómicas
    case 'plan-labores':
    case 'plan-tratamientos':
    case 'recomendaciones-labores':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/labores/plan_recomendaciones.php';
        break;

    // Rutas para Gastos, Costes y Tipos de Gasto/Ingreso
    case 'gastos':
    case 'gastos/nuevo':
    case 'gastos/finca':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/gastos/index.php';
        break;

    case 'gastos/tipos':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/gastos/tipos.php';
        break;

    // Copias de Seguridad (Backup) - Acceso exclusivo Administradores y Superusuarios
    case 'backup':
    case 'configuracion/backup':
        if (!Auth::isAdmin()) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }
        require_once VIEWS_PATH . '/configuracion/backup.php';
        break;

    case 'backup/descargar':
        if (!Auth::isAdmin()) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }
        require_once SRC_PATH . '/Services/BackupService.php';
        $res = BackupService::createBackup(BackupService::DEFAULT_PASSWORD);
        if ($res['success']) {
            BackupService::streamDownload($res['zipPath'], $res['filename']);
        } else {
            $_SESSION['backup_error'] = $res['message'];
            header("Location: " . APP_URL . "/backup");
            exit;
        }
        break;

    // API Universal para Enviar Informes y Listados por Correo Electrónico
    case 'api/informe/enviar-email':
        require_once ROOT_PATH . '/controllers/api_informe_email.php';
        break;

    // API Asistente IA para Búsqueda y Autocompletado de Cultivos
    case 'api/cultivos/ia':
    case 'api/cultivos/consultar-ia':
        require_once ROOT_PATH . '/controllers/api_cultivos_ia.php';
        break;

    // API Asistente IA para Localización de Productos Fitosanitarios y Registro MAPA
    case 'api/fitosanitarios/ia':
    case 'api/fitosanitarios/consultar-ia':
    case 'api/tratamientos/consultar-producto-ia':
        require_once ROOT_PATH . '/controllers/api_fitosanitarios_ia.php';
        break;

    // Visor Cartográfico SIG y Gestión de Cultivos/Árboles en Mapa
    case 'mapas':
    case 'mapa-cultivos':
    case 'visor-sig':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        require_once VIEWS_PATH . '/mapas/index.php';
        break;

    // API para Visor Cartográfico SIG (Geometrías, Árboles y Coordenadas)
    case 'api/mapas':
    case 'api/mapas/datos':
    case 'api/mapas/guardar-zona':
    case 'api/mapas/borrar-zona':
    case 'api/mapas/guardar-arbol':
    case 'api/mapas/borrar-arbol':
    case 'api/mapas/centro':
        require_once ROOT_PATH . '/controllers/api_mapas.php';
        break;

    default:
        http_response_code(404);
        $pageTitle = "Página no encontrada - " . APP_NAME;
        require_once VIEWS_PATH . '/layouts/header.php';
        echo "<div class='container py-5 text-center'><h1 class='display-1 fw-bold text-primary'>404</h1><p class='lead'>La página solicitada no existe.</p><a href='" . APP_URL . "/' class='btn btn-ag'>Volver al Inicio</a></div>";
        require_once VIEWS_PATH . '/layouts/footer.php';
        break;
}
