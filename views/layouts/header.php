<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --ag-primary: #2e7d32;
            --ag-primary-hover: #1b5e20;
            --ag-accent: #81c784;
            --ag-bg: #f8fafc;
        }
        body {
            background-color: var(--ag-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #2c3e50;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-ag {
            background-color: #1b4332;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-ag .nav-link {
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            padding: 0.5rem 0.9rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .navbar-ag .nav-link:hover, .navbar-ag .nav-link.active {
            color: #ffffff;
            background-color: rgba(255,255,255,0.12);
        }
        .btn-ag {
            background-color: var(--ag-primary);
            color: #ffffff;
            border: none;
        }
        .btn-ag:hover {
            background-color: var(--ag-primary-hover);
            color: #ffffff;
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            background: #ffffff;
        }
        .page-header {
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .footer-ag {
            margin-top: auto;
            background: #0f2419;
            color: #94a3b8;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-ag sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= APP_URL ?>/">
            <i class="fa-solid fa-seedling text-warning fs-4"></i>
            <span>AgroDietario</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/"><i class="fa-solid fa-house me-1"></i> Inicio</a>
                </li>

                <?php if (Auth::check()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-chart-pie me-1"></i> Panel</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-map-location-dot me-1"></i> Fincas y Parajes
                        </a>
                        <ul class="dropdown-menu shadow-sm">
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/fincas"><i class="fa-solid fa-vector-square me-2 text-success"></i>Mis Fincas</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/parajes"><i class="fa-solid fa-location-pin me-2 text-primary"></i>Parajes</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-leaf me-1"></i> Cultivos
                        </a>
                        <ul class="dropdown-menu shadow-sm">
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/cultivos"><i class="fa-solid fa-book-bookmark me-2 text-success"></i>Catálogo Botánico</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/plantaciones"><i class="fa-solid fa-calendar-days me-2 text-info"></i>Plantaciones Activas</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/diario"><i class="fa-solid fa-book-open me-2 text-warning"></i>Diario de Evolución</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-clipboard-list me-1"></i> Labores de Campo
                        </a>
                        <ul class="dropdown-menu shadow-sm">
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/tratamientos"><i class="fa-solid fa-spray-can-sparkles me-2 text-danger"></i>Tratamientos (Eco/Conv)</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/labores"><i class="fa-solid fa-tractor me-2 text-secondary"></i>Laboreo y Desbroce</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/podas"><i class="fa-solid fa-scissors me-2 text-warning"></i>Podas y Reciclaje</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/riegos"><i class="fa-solid fa-faucet-drip me-2 text-primary"></i>Riegos y Tandas</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/recolecciones"><i class="fa-solid fa-basket-shopping me-2 text-success"></i>Cosechas y Ventas</a></li>
                        </ul>
                    </li>

                    <?php if (Auth::isAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-warning" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-shield-halved me-1"></i> Administración
                        </a>
                        <ul class="dropdown-menu shadow-sm">
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/usuarios"><i class="fa-solid fa-users-gear me-2"></i>Aprobar Usuarios</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/configuracion"><i class="fa-solid fa-sliders me-2"></i>Configurar Portal</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (Auth::check()): ?>
                    <?php $u = Auth::user(); ?>
                    <li class="nav-item me-3 text-light text-end d-none d-lg-block">
                        <div class="fw-bold" style="font-size:0.9rem;"><?= htmlspecialchars($u['nombre_completo']) ?></div>
                        <div class="badge bg-success" style="font-size:0.75rem;"><?= htmlspecialchars($u['nombre_propietario']) ?></div>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-outline-light" href="<?= APP_URL ?>/logout">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Cerrar sesión
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item me-2">
                        <a class="btn btn-sm btn-outline-light" href="<?= APP_URL ?>/login">
                            <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Iniciar Sesión
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-warning text-dark fw-bold" href="<?= APP_URL ?>/register">
                            <i class="fa-solid fa-user-plus me-1"></i> Solicitar Acceso
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-grow-1">
