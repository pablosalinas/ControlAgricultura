<?php
/**
 * Front Controller y Enrutador Principal - AgroDietario
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once SRC_PATH . '/Services/Auth.php';
require_once SRC_PATH . '/Services/Captcha.php';

// Obtener la ruta solicitada
$route = isset($_GET['route']) ? $_GET['route'] : '';
$route = trim($route, '/');

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
        if (!Auth::canManageUsers()) {
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

    default:
        http_response_code(404);
        $pageTitle = "Página no encontrada - " . APP_NAME;
        require_once VIEWS_PATH . '/layouts/header.php';
        echo "<div class='container py-5 text-center'><h1 class='display-1 fw-bold text-primary'>404</h1><p class='lead'>La página solicitada no existe.</p><a href='" . APP_URL . "/' class='btn btn-ag'>Volver al Inicio</a></div>";
        require_once VIEWS_PATH . '/layouts/footer.php';
        break;
}
