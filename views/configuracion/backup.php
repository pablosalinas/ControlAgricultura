<?php
// Acceso restringido estrictamente a Superusuarios y Administradores
if (!Auth::isAdmin()) {
    header("Location: " . APP_URL . "/dashboard");
    exit;
}

$pageTitle = "Copias de Seguridad (Backup) - " . APP_NAME;

$db = Database::getConnection();
$error = '';
$mensaje = '';

// Procesar descarga de copia de seguridad (DEBE ejecutarse antes de enviar cualquier HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generar_backup') {
    require_once SRC_PATH . '/Services/BackupService.php';
    $res = BackupService::createBackup(BackupService::DEFAULT_PASSWORD);
    if ($res['success']) {
        BackupService::streamDownload($res['zipPath'], $res['filename']);
    } else {
        $error = $res['message'];
    }
}

// Información del sistema
$totalFincas = (int)$db->query("SELECT COUNT(*) FROM fincas WHERE activo = 1")->fetchColumn();
$totalPlantaciones = (int)$db->query("SELECT COUNT(*) FROM plantaciones")->fetchColumn();
$totalGastos = (int)$db->query("SELECT COUNT(*) FROM gastos_explotacion WHERE activo = 1")->fetchColumn();
$totalRegantes = (int)$db->query("SELECT COUNT(*) FROM regantes WHERE activo = 1")->fetchColumn();

// Cargar la cabecera HTML solo si no se ha descargado el archivo
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="container py-4">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-shield-halved fs-3 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Copias de Seguridad del Sistema (Backup)</h2>
                <p class="text-muted mb-0 small">Respaldo integral de la Base de Datos y de los archivos de la plataforma web</p>
            </div>
        </div>
        <a href="<?= APP_URL ?>/dashboard" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver al Panel
        </a>
    </div>

    <!-- Alertas -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Tarjeta Principal de Descarga -->
        <div class="col-lg-8">
            <div class="card card-ag shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="fa-solid fa-file-zipper me-2 text-warning"></i>Descargar Respaldo Completo (.ZIP Encriptado)
                    </h5>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill font-monospace">
                        <i class="fa-solid fa-lock me-1"></i>Cifrado Activo
                    </span>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted">
                        Esta herramienta genera un archivo comprimido <strong>.ZIP</strong> que incluye tanto la <strong>base de datos completa</strong> (todas las tablas, regantes, fincas, gastos, histórico y registros) en formato SQL, como la <strong>totalidad de los archivos web</strong> de la aplicación.
                    </p>

                    <!-- Caja de Seguridad y Contraseña -->
                    <div class="alert alert-warning border-warning-subtle d-flex align-items-start gap-3 p-3 rounded-3 mb-4">
                        <i class="fa-solid fa-key fs-4 text-warning mt-1"></i>
                        <div>
                            <h6 class="fw-bold text-dark mb-1">Protección y Clave de Encriptación</h6>
                            <p class="small text-muted mb-2">
                                Por motivos estrictos de confidencialidad y protección de datos agronómicos, el archivo ZIP se descarga <strong>encriptado y protegido con contraseña</strong>:
                            </p>
                            <div class="d-inline-flex align-items-center bg-white border border-warning rounded px-3 py-1 font-monospace fs-6 fw-bold text-primary shadow-sm">
                                <span>@Acemila2026</span>
                            </div>
                            <span class="small text-muted ms-2">(Introduce esta contraseña al descomprimir el archivo con Windows, Mac, WinRAR o 7-Zip).</span>
                        </div>
                    </div>

                    <!-- Botón de Acción Principal -->
                    <form action="<?= APP_URL ?>/backup" method="POST" id="formBackup">
                        <input type="hidden" name="action" value="generar_backup">
                        <div class="d-grid gap-2">
                            <button type="submit" id="btnDescargar" class="btn btn-primary btn-lg fw-bold shadow py-3">
                                <i class="fa-solid fa-cloud-arrow-down me-2"></i> Generar y Descargar Copia de Seguridad Completa
                            </button>
                        </div>
                    </form>
                    <div id="progresoBackup" class="text-center mt-3 d-none">
                        <div class="spinner-border text-primary me-2" role="status"></div>
                        <span class="text-muted small">Generando volcado de base de datos y empaquetando archivos... La descarga se iniciará automáticamente.</span>
                    </div>
                </div>
            </div>

            <!-- ¿Qué contiene la copia de seguridad? -->
            <div class="card card-ag shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-list-check me-2 text-primary"></i>Contenido del Respaldo
                    </h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex align-items-center gap-3 px-0 py-2">
                            <i class="fa-solid fa-database text-primary fs-5"></i>
                            <div>
                                <strong>Carpeta /BASE_DE_DATOS/</strong>: Archivo <code>database_backup_[fecha].sql</code> con todas las tablas, estructura y datos íntegros listos para restaurar en cualquier servidor MySQL / MariaDB.
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center gap-3 px-0 py-2">
                            <i class="fa-solid fa-folder-tree text-success fs-5"></i>
                            <div>
                                <strong>Carpeta /WEB/</strong>: Todos los controladores, vistas, plantillas, configuración, servicios y documentos subidos de la plataforma web.
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center gap-3 px-0 py-2">
                            <i class="fa-solid fa-file-shield text-warning fs-5"></i>
                            <div>
                                <strong>Archivo LEEME_SEGURIDAD.txt</strong>: Metadatos de la copia, fecha exacta de generación y verificación de integridad.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Columna Lateral: Estado Actual del Sistema -->
        <div class="col-lg-4">
            <div class="card card-ag shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-primary">
                        <i class="fa-solid fa-server me-2"></i>Estado de los Datos
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted small"><i class="fa-solid fa-draw-polygon me-2 text-primary"></i>Fincas Activas</span>
                        <strong class="font-monospace text-dark"><?= $totalFincas ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted small"><i class="fa-solid fa-seedling me-2 text-success"></i>Plantaciones</span>
                        <strong class="font-monospace text-dark"><?= $totalPlantaciones ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted small"><i class="fa-solid fa-coins me-2 text-warning"></i>Gastos e Ingresos</span>
                        <strong class="font-monospace text-dark"><?= $totalGastos ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted small"><i class="fa-solid fa-users me-2 text-info"></i>Regantes y Socios</span>
                        <strong class="font-monospace text-dark"><?= $totalRegantes ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small"><i class="fa-solid fa-shield-check me-2 text-success"></i>Cifrado ZIP</span>
                        <span class="badge bg-success">Activo (@Acemila2026)</span>
                    </div>
                </div>
            </div>

            <!-- Recomendación de seguridad -->
            <div class="card bg-light border-0 shadow-sm p-3">
                <h6 class="fw-bold small text-dark mb-2"><i class="fa-solid fa-lightbulb text-warning me-1"></i>Recomendación de Seguridad</h6>
                <p class="small text-muted mb-0">
                    Se recomienda descargar una copia periódica antes de realizar actualizaciones de datos o cada fin de campaña agrícola, y conservarla en un medio de almacenamiento externo seguro.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('formBackup').addEventListener('submit', function() {
    document.getElementById('btnDescargar').disabled = true;
    document.getElementById('btnDescargar').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Empaquetando copia de seguridad...';
    document.getElementById('progresoBackup').classList.remove('d-none');
    setTimeout(function() {
        document.getElementById('btnDescargar').disabled = false;
        document.getElementById('btnDescargar').innerHTML = '<i class="fa-solid fa-cloud-arrow-down me-2"></i> Generar y Descargar Copia de Seguridad Completa';
        document.getElementById('progresoBackup').classList.add('d-none');
    }, 6000);
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
