<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(isset($pageTitle) ? $pageTitle : APP_NAME) ?></title>
    <!-- Favicon Package Oficial AgroDietario -->
    <link rel="icon" type="image/x-icon" href="<?= APP_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="<?= APP_URL ?>/site.webmanifest">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter / Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --ag-blue-primary: #1d4ed8;       /* Azul real vibrante */
            --ag-blue-hover: #1e40af;         /* Azul zafiro profundo */
            --ag-blue-dark: #091e3a;          /* Azul noche profundo */
            --ag-blue-surface: #0f2b48;       /* Superficie navbar */
            --ag-blue-cyan: #06b6d4;          /* Cyan eléctrico para acentos */
            --ag-blue-sky: #38bdf8;           /* Azul cielo */
            --ag-bg-body: #f1f5f9;            /* Fondo gris azulado ultra limpio */
            --ag-card-border: #e2e8f0;
        }

        body {
            background-color: var(--ag-bg-body);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1, h2, h3, h4, h5, h6, .navbar-brand {
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.02em;
        }

        /* Navbar estilo Aero-Tech Azul */
        .navbar-ag {
            background: linear-gradient(90deg, #091e3a 0%, #0e345a 50%, #164e87 100%);
            box-shadow: 0 4px 20px rgba(9, 30, 58, 0.25);
            border-bottom: 1px solid rgba(56, 189, 248, 0.2);
        }

        .navbar-brand span {
            background: linear-gradient(90deg, #ffffff 30%, #38bdf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .navbar-ag .nav-link {
            color: #cbd5e1;
            font-weight: 500;
            font-size: 0.92rem;
            padding: 0.55rem 0.85rem;
            border-radius: 8px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .navbar-ag .nav-link:hover, .navbar-ag .nav-link.active {
            color: #ffffff;
            background-color: rgba(56, 189, 248, 0.15);
            box-shadow: inset 0 0 0 1px rgba(56, 189, 248, 0.3);
        }

        .btn-ag {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: #ffffff;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 14px rgba(29, 78, 216, 0.35);
            transition: all 0.2s ease;
        }

        .btn-ag:hover {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(29, 78, 216, 0.45);
        }

        .btn-cyan {
            background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%);
            color: #ffffff;
            border: none;
            font-weight: 600;
        }
        .btn-cyan:hover {
            color: #ffffff;
            background: linear-gradient(135deg, #0369a1 0%, #0891b2 100%);
        }

        .card-custom {
            border: 1px solid var(--ag-card-border);
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
            background: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .badge-tenant {
            background: rgba(56, 189, 248, 0.15);
            color: #0284c7;
            border: 1px solid rgba(56, 189, 248, 0.4);
            font-weight: 600;
        }

        .footer-ag {
            margin-top: auto;
            background: #091e3a;
            color: #94a3b8;
            font-size: 0.88rem;
            border-top: 1px solid rgba(56, 189, 248, 0.15);
        }

        /* Optimizaciones Responsive para Móviles y Tablets */
        @media (max-width: 991.98px) {
            .navbar-ag .navbar-collapse {
                background: rgba(9, 30, 58, 0.98);
                backdrop-filter: blur(12px);
                padding: 1rem;
                border-radius: 12px;
                margin-top: 0.6rem;
                border: 1px solid rgba(56, 189, 248, 0.25);
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            }
            .navbar-ag .dropdown-menu {
                background: rgba(14, 52, 90, 0.95);
                border: 1px solid rgba(56, 189, 248, 0.2);
            }
            .navbar-ag .dropdown-item {
                color: #f1f5f9;
            }
            .navbar-ag .dropdown-item:hover {
                background: rgba(56, 189, 248, 0.2);
                color: #38bdf8;
            }
        }

        @media (max-width: 767.98px) {
            body {
                font-size: 0.92rem;
            }
            .container, .container-fluid {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            .card-custom {
                border-radius: 10px;
                padding: 0.9rem !important;
            }
            h1, .h1 { font-size: 1.45rem; }
            h2, .h2 { font-size: 1.25rem; }
            h3, .h3 { font-size: 1.15rem; }
            .btn {
                padding: 0.45rem 0.8rem;
                font-size: 0.88rem;
            }
            /* Tablas responsivas en smartphone */
            .table-responsive {
                -webkit-overflow-scrolling: touch;
                margin-bottom: 0.75rem;
                border-radius: 8px;
            }
            .table td, .table th {
                padding: 0.45rem 0.55rem;
                font-size: 0.85rem;
            }
            /* Form inputs cómodos al tacto sin zoom automático en iOS */
            .form-control, .form-select {
                min-height: 40px;
                font-size: 15px;
            }
        }

        /* Reglas Universales de Impresión Oficial */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 10pt !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .navbar, .no-print, [data-report-toolbar], .btn, .btn-group, .pagination,
            .alert, .modal, form:not(.imprimible), .dropdown, footer, header {
                display: none !important;
            }
            .container, .container-fluid {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
                margin-bottom: 0 !important;
            }
            .card-header {
                background: #ffffff !important;
                color: #000000 !important;
                border-bottom: 2px solid #000000 !important;
                padding: 8px 0 !important;
            }
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 9pt !important;
                color: #000000 !important;
            }
            .table th, .table td {
                border: 1px solid #cccccc !important;
                padding: 4px 6px !important;
                color: #000000 !important;
            }
            .badge {
                border: 1px solid #777777 !important;
                color: #000000 !important;
                background: transparent !important;
            }
            a {
                text-decoration: none !important;
                color: #000000 !important;
            }
            .page-break {
                page-break-after: always;
            }
        }
    </style>
    <!-- Librerías Globales para Exportación y PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>window.APP_URL = '<?= APP_URL ?>';</script>
    <script src="<?= APP_URL ?>/assets/js/report-export.js"></script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-ag sticky-top">
    <div class="container-fluid px-4">
        <!-- Brand con Logo Oficial AgroDietario -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= APP_URL ?>/">
            <img src="<?= APP_URL ?>/assets/img/logo.jpg" alt="AgroDietario" class="rounded-3 shadow-sm border border-info border-opacity-25" style="height: 38px; width: 38px; object-fit: cover;">
            <span class="fs-4">AgroDietario</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/"><i class="fa-solid fa-house-chimney-window me-1 text-info"></i> Inicio</a>
                </li>

                <?php if (Auth::check()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-chart-pie me-1 text-info"></i> Panel</a>
                    </li>

                    <!-- Fincas con icono de teledetección/capas SIG -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-layer-group me-1 text-info"></i> Fincas
                        </a>
                        <ul class="dropdown-menu shadow border-0 rounded-3">
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/fincas"><i class="fa-solid fa-draw-polygon me-2 text-primary"></i>Mis Fincas</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/fincas/nueva"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Nueva Finca</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/parajes"><i class="fa-solid fa-location-crosshairs me-2 text-info"></i>Maestro de Parajes y Paradas</a></li>
                        </ul>
                    </li>

                    <!-- Cultivos con icono de ADN / Genética botánica -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-dna me-1 text-info"></i> Cultivos
                        </a>
                        <ul class="dropdown-menu shadow border-0 rounded-3">
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/cultivos"><i class="fa-solid fa-book-bookmark me-2 text-primary"></i>Catálogo Botánico</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/plantaciones"><i class="fa-solid fa-cubes-stacked me-2 text-info"></i>Plantaciones Activas</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/plantaciones/nueva"><i class="fa-solid fa-plus me-2 text-info"></i>Nueva Plantación</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/diario"><i class="fa-solid fa-camera-retro me-2 text-warning"></i>Diario y Fotos</a></li>
                        </ul>
                    </li>

                    <!-- Labores con icono de telemetría y operaciones -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-sliders me-1 text-info"></i> Labores de Campo
                        </a>
                        <ul class="dropdown-menu shadow border-0 rounded-3">
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/tratamientos"><i class="fa-solid fa-shield-virus me-2 text-danger"></i>Tratamientos (Eco/Conv)</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/labores"><i class="fa-solid fa-mountain-sun me-2 text-secondary"></i>Laboreo y Desbroce</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/podas"><i class="fa-solid fa-arrows-spin me-2 text-success"></i>Podas y Reciclaje</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/riegos"><i class="fa-solid fa-gauge-high me-2 text-primary"></i>Riegos y Tandas</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/solicitud-riego"><i class="fa-solid fa-file-circle-plus me-2 text-info"></i>Nueva Solicitud Riego (A4)</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/partes-riego"><i class="fa-solid fa-list-check me-2 text-primary"></i>Partes y Consultas de Riego</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/recolecciones"><i class="fa-solid fa-scale-balanced me-2 text-info"></i>Cosechas y Ventas</a></li>
                        </ul>
                    </li>

                    <!-- Control Económico y Gastos por Finca -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-coins me-1 text-warning"></i> Gastos
                        </a>
                        <ul class="dropdown-menu shadow border-0 rounded-3">
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/gastos"><i class="fa-solid fa-receipt me-2 text-primary"></i>Control de Gastos e Ingresos</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/gastos?nuevo=1"><i class="fa-solid fa-circle-plus me-2 text-success"></i>Añadir Gasto a Finca</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/gastos/tipos"><i class="fa-solid fa-tags me-2 text-info"></i>Maestro de Tipos de Gastos</a></li>
                        </ul>
                    </li>

                    <!-- Cuaderno oficial con certificado de explotación -->
                    <li class="nav-item">
                        <a class="nav-link text-warning fw-semibold" href="<?= APP_URL ?>/cuaderno">
                            <i class="fa-solid fa-certificate me-1"></i> Cuaderno Oficial
                        </a>
                    </li>

                    <!-- Configuración y Riego -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-light" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-gear me-1 text-info"></i> Configuración
                        </a>
                        <ul class="dropdown-menu shadow border-0 rounded-3" style="min-width: 250px;">
                            <?php if (Auth::isAdmin()): ?>
                                <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/explotaciones"><i class="fa-solid fa-building-wheat me-2 text-warning"></i>Explotaciones Agrarias</a></li>
                                <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/admin/gestion-usuarios"><i class="fa-solid fa-id-card-clip me-2 text-primary"></i>Equipo y Usuarios</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                            <?php endif; ?>

                            <!-- Apartado: Comunidades de Regantes y sus Socios -->
                            <li class="dropdown-header text-uppercase text-muted fw-bold small py-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                <i class="fa-solid fa-water me-1 text-primary"></i> Comunidades y Riego
                            </li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/comunidades-regantes"><i class="fa-solid fa-droplet me-2 text-primary"></i>Comunidades de Regantes</a></li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/regantes"><i class="fa-solid fa-address-book me-2 text-info"></i>Socios Regantes (Censo)</a></li>

                            <li><hr class="dropdown-divider my-1"></li>
                            <li class="dropdown-header text-uppercase text-muted fw-bold small py-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                <i class="fa-solid fa-coins me-1 text-warning"></i> Economía y Balances
                            </li>
                            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/gastos/tipos"><i class="fa-solid fa-tags me-2 text-warning"></i>Maestro de Tipos de Gastos/Ingresos</a></li>

                            <?php if (Auth::isAdmin()): ?>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/admin/accesos"><i class="fa-solid fa-chart-line me-2 text-info"></i>Estadísticas y Logs de Accesos</a></li>
                                <li><a class="dropdown-item py-2 fw-semibold text-primary" href="<?= APP_URL ?>/backup"><i class="fa-solid fa-shield-halved me-2 text-warning"></i>Copias de Seguridad (Backup)</a></li>
                            <?php endif; ?>
                            <?php if (Auth::isSuperAdmin()): ?>
                                <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/admin/usuarios"><i class="fa-solid fa-user-check me-2 text-success"></i>Aprobar Solicitudes</a></li>
                                <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/admin/configuracion"><i class="fa-solid fa-compass-drafting me-2 text-info"></i>Personalizar Portal</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (Auth::check()): ?>
                    <?php $u = Auth::user(); ?>
                    <!-- Vista Móvil: Datos de Usuario y Explotación -->
                    <li class="nav-item d-lg-none my-2 p-2 rounded bg-white bg-opacity-10 text-light w-100">
                        <div class="fw-bold small mb-1"><i class="fa-solid fa-user-circle me-1 text-info"></i> <?= htmlspecialchars($u['nombre_completo']) ?></div>
                        <a href="<?= APP_URL ?>/explotaciones" class="badge badge-tenant text-decoration-none d-inline-block">
                            <i class="fa-solid fa-building-wheat me-1"></i><?= htmlspecialchars($u['nombre_propietario']) ?>
                            <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.65rem;"></i>
                        </a>
                    </li>
                    <li class="nav-item me-3 text-light text-end d-none d-lg-block">
                        <div class="fw-bold" style="font-size:0.9rem;"><?= htmlspecialchars($u['nombre_completo']) ?></div>
                        <a href="<?= APP_URL ?>/explotaciones" class="badge badge-tenant px-2 py-1 text-decoration-none" title="Administrar o conmutar explotación" style="cursor: pointer;">
                            <i class="fa-solid fa-building-wheat me-1"></i><?= htmlspecialchars($u['nombre_propietario']) ?>
                            <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.65rem;"></i>
                        </a>
                    </li>
                    <li class="nav-item w-100 w-lg-auto text-end mt-2 mt-lg-0">
                        <a class="btn btn-sm btn-outline-info text-white w-100 w-lg-auto" href="<?= APP_URL ?>/logout">
                            <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Salir
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item me-2">
                        <a class="btn btn-sm btn-outline-light" href="<?= APP_URL ?>/login">
                            <i class="fa-solid fa-key me-1"></i> Iniciar Sesión
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-cyan fw-bold shadow-sm" href="<?= APP_URL ?>/register">
                            <i class="fa-solid fa-id-card me-1"></i> Solicitar Acceso
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-grow-1">
