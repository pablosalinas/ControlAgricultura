<?php
/**
 * Front Controller y Enrutador Principal - AgroDietario
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once SRC_PATH . '/Services/Auth.php';
require_once SRC_PATH . '/Services/Captcha.php';

// Obtener la ruta solicitada
$route = $_GET['route'] ?? '';
$route = trim($route, '/');

// Enrutamiento simple y limpio
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
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
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
            $captchaInput = $_POST['captcha_input'] ?? '';
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

    case 'admin/usuarios':
        if (!Auth::isAdmin()) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }
        require_once VIEWS_PATH . '/admin/usuarios.php';
        break;

    // Rutas para Fincas
    case 'fincas':
    case 'fincas/nueva':
    case 'fincas/detalle':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        $sub = explode('/', $route)[1] ?? 'index';
        $viewFile = VIEWS_PATH . '/fincas/' . ($sub === 'nueva' ? 'form.php' : ($sub === 'detalle' ? 'detalle.php' : 'index.php'));
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            require_once VIEWS_PATH . '/fincas/index.php';
        }
        break;

    // Rutas para Cultivos
    case 'cultivos':
    case 'cultivos/nuevo':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        $sub = explode('/', $route)[1] ?? 'index';
        $viewFile = VIEWS_PATH . '/cultivos/' . ($sub === 'nuevo' ? 'form.php' : 'index.php');
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            require_once VIEWS_PATH . '/cultivos/index.php';
        }
        break;

    // Rutas para Labores y Cuaderno de campo
    case 'tratamientos':
    case 'tratamientos/nuevo':
    case 'labores':
    case 'labores/nueva':
    case 'podas':
    case 'podas/nueva':
    case 'riegos':
    case 'riegos/nuevo':
    case 'recolecciones':
    case 'recolecciones/nueva':
    case 'diario':
    case 'diario/nuevo':
    case 'plantaciones':
    case 'parajes':
        if (!Auth::check()) {
            header("Location: " . APP_URL . "/login");
            exit;
        }
        $parts = explode('/', $route);
        $modulo = $parts[0];
        $accion = $parts[1] ?? 'index';

        $directView = VIEWS_PATH . "/labores/{$modulo}.php";
        if (file_exists($directView)) {
            require_once $directView;
        } else {
            // Fallback genérico si aún no existe la vista específica
            $pageTitle = ucfirst($modulo) . " - " . APP_NAME;
            require_once VIEWS_PATH . '/layouts/header.php';
            echo "<div class='container py-5'><div class='card card-custom p-5 text-center'>";
            echo "<h3 class='fw-bold text-success mb-2'>Módulo: " . htmlspecialchars(ucfirst($modulo)) . "</h3>";
            echo "<p class='text-muted'>Página en preparación.</p>";
            echo "<a href='" . APP_URL . "/dashboard' class='btn btn-ag mt-3'>Volver al Panel</a>";
            echo "</div></div>";
            require_once VIEWS_PATH . '/layouts/footer.php';
        }
        break;

    default:
        http_response_code(404);
        $pageTitle = "Página no encontrada - " . APP_NAME;
        require_once VIEWS_PATH . '/layouts/header.php';
        echo "<div class='container py-5 text-center'><h1 class='display-1 fw-bold text-success'>404</h1><p class='lead'>La página solicitada no existe.</p><a href='" . APP_URL . "/' class='btn btn-ag'>Volver al Inicio</a></div>";
        require_once VIEWS_PATH . '/layouts/footer.php';
        break;
}
