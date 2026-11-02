<?php
$pageTitle = "Inicio - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

// Obtener datos del portal configurables
$config = [
    'titulo' => 'AgroDietario - Gestión Agrícola Integral',
    'subtitulo' => 'Control agronómico, trazabilidad de fincas, turnos de riego y cuaderno de explotación digital',
    'descripcion' => 'Diseñado para propietarios, cooperativas y agricultores profesionales que requieren un control riguroso de labores, tratamientos convencionales y ecológicos, podas con reciclaje de restos, y seguimiento fenológico de cada plantación.'
];

try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT * FROM configuracion_web LIMIT 1");
    if ($row = $stmt->fetch()) {
        $config['titulo'] = $row['titulo_portal'] ?: $config['titulo'];
        $config['subtitulo'] = $row['subtitulo_portal'] ?: $config['subtitulo'];
        $config['descripcion'] = $row['descripcion_bienvenida'] ?: $config['descripcion'];
    }
} catch (Exception $e) {
    // Si la BD no está aún instalada, usa los valores por defecto
}
?>

<div class="bg-dark text-white py-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);">
    <div class="container py-4 text-center">
        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3 shadow-sm">
            <i class="fa-solid fa-wheat-awn me-1"></i> Cuaderno de Campo Digital Multi-Propietario
        </span>
        <h1 class="display-4 fw-bold mb-3"><?= htmlspecialchars($config['titulo']) ?></h1>
        <p class="lead col-lg-8 mx-auto text-light opacity-90 mb-4"><?= htmlspecialchars($config['subtitulo']) ?></p>
        
        <div class="d-flex justify-content-center gap-3">
            <?php if (Auth::check()): ?>
                <a href="<?= APP_URL ?>/dashboard" class="btn btn-warning btn-lg fw-bold px-4 shadow">
                    <i class="fa-solid fa-gauge-high me-2"></i> Ir a mi Panel de Control
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/login" class="btn btn-warning btn-lg fw-bold px-4 shadow">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Acceso Propietarios
                </a>
                <a href="<?= APP_URL ?>/register" class="btn btn-outline-light btn-lg px-4 shadow">
                    <i class="fa-solid fa-user-plus me-2"></i> Solicitar Cuenta
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row text-center mb-5">
        <div class="col-lg-8 mx-auto">
            <h3 class="fw-bold text-dark mb-3">Plataforma Profesional para el Campo</h3>
            <p class="text-muted"><?= nl2br(htmlspecialchars($config['descripcion'])) ?></p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4 border-top border-4 border-success">
                <div class="text-success fs-1 mb-3"><i class="fa-solid fa-map-location-dot"></i></div>
                <h5 class="fw-bold">Fincas, Catastro y Riegos</h5>
                <p class="text-muted small">
                    Control de referencias catastrales, polígonos, parcelas, superficies en hectáreas, y gestión de turnos de riego (parada, tablón, horas en propiedad y disponibles por tanda).
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-custom h-100 p-4 border-top border-4 border-primary">
                <div class="text-primary fs-1 mb-3"><i class="fa-solid fa-leaf"></i></div>
                <h5 class="fw-bold">Catálogo Botánico y Cultivos</h5>
                <p class="text-muted small">
                    Registro de cultivos con nombre científico, acceso directo a consultas botánicas en internet, variedades y trazabilidad por campañas de siembra y trasplante.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-custom h-100 p-4 border-top border-4 border-warning">
                <div class="text-warning fs-1 mb-3"><i class="fa-solid fa-clipboard-check"></i></div>
                <h5 class="fw-bold">Dietario Completo de Labores</h5>
                <p class="text-muted small">
                    Registro exhaustivo de tratamientos (convencionales y ecológicos certificados), labores de suelo, podas y talas con reciclaje de restos, y liquidaciones de cosechas.
                </p>
            </div>
        </div>
    </div>

    <!-- Acceso de seguridad -->
    <div class="card card-custom mt-5 p-4 bg-light border">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="fw-bold mb-1"><i class="fa-solid fa-shield-halved text-success me-2"></i>Seguridad y Acceso Restringido</h5>
                <p class="text-muted mb-0 small">
                    Para acceder a la información de las fincas y cuadernos de campo es obligatoria la autenticación. Las nuevas solicitudes son validadas con Captcha y requieren aprobación manual por el administrador.
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?= APP_URL ?>/register" class="btn btn-ag px-4 py-2">
                    <i class="fa-solid fa-user-shield me-2"></i>Solicitar Alta
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
