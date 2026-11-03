<?php
$pageTitle = "Inicio - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

// Obtener datos del portal configurables
$config = array(
    'titulo' => 'AgroDietario - Gestión Agrícola de Precisión',
    'subtitulo' => 'Control agronómico, teledetección de parcelas, turnos de riego y cuaderno de explotación digital',
    'descripcion' => 'Diseñado para propietarios, cooperativas y explotaciones agrícolas profesionales que requieren un control riguroso de parcelas catastrales, labores de campo, tratamientos fitosanitarios y ecológicos certificados, y trazabilidad total.'
);

try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT * FROM configuracion_web LIMIT 1");
    if ($row = $stmt->fetch()) {
        $config['titulo'] = $row['titulo_portal'] ? $row['titulo_portal'] : $config['titulo'];
        $config['subtitulo'] = $row['subtitulo_portal'] ? $row['subtitulo_portal'] : $config['subtitulo'];
        $config['descripcion'] = $row['descripcion_bienvenida'] ? $row['descripcion_bienvenida'] : $config['descripcion'];
    }
} catch (Exception $e) {
    // Si la BD no está aún instalada, usa los valores por defecto
}
?>

<!-- Banner Hero con Azul Chulo Tecnológico -->
<div class="text-white py-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #091e3a 0%, #0e3863 50%, #1d4ed8 100%); border-bottom: 2px solid rgba(56, 189, 248, 0.3);">
    <div class="container py-4 text-center position-relative" style="z-index: 2;">
        <span class="badge bg-info bg-opacity-25 text-info px-3 py-2 rounded-pill fw-bold mb-3 border border-info border-opacity-50">
            <i class="fa-solid fa-satellite me-1"></i> Plataforma Agronómica Multi-Explotación &bull; <a href="https://www.moratalla-murcia.com" target="_blank" rel="noopener noreferrer" class="text-warning text-decoration-none"><i class="fa-solid fa-arrow-up-right-from-square ms-1 me-1"></i>www.moratalla-murcia.com</a>
        </span>
        <h1 class="display-4 fw-bold mb-3" style="letter-spacing: -0.03em;"><?= htmlspecialchars($config['titulo']) ?></h1>
        <p class="lead col-lg-8 mx-auto text-light opacity-90 mb-4"><?= htmlspecialchars($config['subtitulo']) ?></p>
        
        <div class="d-flex justify-content-center gap-3">
            <?php if (Auth::check()): ?>
                <a href="<?= APP_URL ?>/dashboard" class="btn btn-cyan btn-lg fw-bold px-4 shadow">
                    <i class="fa-solid fa-chart-pie me-2"></i> Ir a mi Panel de Control
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/login" class="btn btn-cyan btn-lg fw-bold px-4 shadow">
                    <i class="fa-solid fa-key me-2"></i> Acceso a Explotaciones
                </a>
                <a href="<?= APP_URL ?>/register" class="btn btn-outline-light btn-lg px-4 shadow">
                    <i class="fa-solid fa-id-card me-2"></i> Solicitar Cuenta
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row text-center mb-5">
        <div class="col-lg-8 mx-auto">
            <h3 class="fw-bold text-dark mb-3">Tecnología y Control Integral de tus Fincas</h3>
            <p class="text-muted"><?= nl2br(htmlspecialchars($config['descripcion'])) ?></p>
        </div>
    </div>

    <!-- Tarjetas de Módulos con Iconografía Innovadora -->
    <div class="row g-4">
        <!-- Fincas / Teledetección -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4 border-top border-4 border-primary">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary mb-3" style="width: 56px; height: 56px;">
                    <i class="fa-solid fa-layer-group fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark">Catastro y Derechos Hídricos</h5>
                <p class="text-muted small">
                    Integración con referencias catastrales SIGPAC, delimitación de parajes, comunidades de regantes, tandas, paradas, tablones y horas en propiedad y disponibles.
                </p>
            </div>
        </div>

        <!-- Cultivos / Genética -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4 border-top border-4 border-info">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-info bg-opacity-10 text-info mb-3" style="width: 56px; height: 56px;">
                    <i class="fa-solid fa-dna fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark">Botánica y Ciclos Fenológicos</h5>
                <p class="text-muted small">
                    Catálogo de variedades con consulta botánica directa en internet, seguimiento por campañas, cálculo de días a cosecha y diario fotográfico a pie de parcela.
                </p>
            </div>
        </div>

        <!-- Cuaderno Oficial / Trazabilidad -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4 border-top border-4 border-warning">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 text-warning mb-3" style="width: 56px; height: 56px;">
                    <i class="fa-solid fa-certificate fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark">Cuaderno de Campo Homologado</h5>
                <p class="text-muted small">
                    Registro riguroso de tratamientos convencionales y ecológicos certificados, labores de suelo, podas con reciclaje de biomasa, riegos y recolecciones con liquidación.
                </p>
            </div>
        </div>
    </div>

    <!-- Módulo de Seguridad y Multi-Propietario -->
    <div class="card card-custom mt-5 p-4 p-md-5 border-0 text-white" style="background: linear-gradient(135deg, #091e3a 0%, #1e3a8a 100%);">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 mb-2 text-info">
                    <i class="fa-solid fa-people-roof fs-4"></i>
                    <h5 class="fw-bold mb-0 text-white">Arquitectura Multi-Explotación con Aislamiento Estricto</h5>
                </div>
                <p class="text-light opacity-75 mb-0 small">
                    Cada titular agrícola gestiona con total privacidad sus parcelas y equipo técnico. Las nuevas solicitudes son verificadas mediante control de seguridad y requieren validación previa antes de operar.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <a href="<?= APP_URL ?>/register" class="btn btn-cyan btn-lg px-4 shadow">
                    <i class="fa-solid fa-id-card me-2"></i>Solicitar Alta de Explotación
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
