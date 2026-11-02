<?php
if (!Auth::isAdmin()) {
    header("Location: " . APP_URL . "/dashboard");
    exit;
}

$pageTitle = "Configuración del Portal Web - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])) {
    $titulo = trim($_POST['titulo_portal'] ?? '');
    $subtitulo = trim($_POST['subtitulo_portal'] ?? '');
    $desc = trim($_POST['descripcion_bienvenida'] ?? '');
    $telefono = trim($_POST['telefono_soporte'] ?? '');
    $email = trim($_POST['email_contacto'] ?? '');
    $permitirRegistro = isset($_POST['permitir_registro_publico']) ? 1 : 0;
    $usarCaptcha = isset($_POST['usar_captcha']) ? 1 : 0;

    try {
        $stmt = $db->prepare("
            UPDATE configuracion_web SET
                titulo_portal = :tit,
                subtitulo_portal = :sub,
                descripcion_bienvenida = :des,
                telefono_soporte = :tel,
                email_contacto = :em,
                permitir_registro_publico = :reg,
                usar_captcha = :cap
            WHERE id_config = 1
        ");
        $stmt->execute([
            ':tit' => $titulo,
            ':sub' => $subtitulo,
            ':des' => $desc,
            ':tel' => $telefono,
            ':em'  => $email,
            ':reg' => $permitirRegistro,
            ':cap' => $usarCaptcha
        ]);
        $mensaje = "Configuración del portal actualizada con éxito. Los cambios son visibles de inmediato en la página de inicio.";
    } catch (Exception $e) {
        $error = "Error al actualizar la configuración: " . $e->getMessage();
    }
}

// Cargar configuración actual
$cfg = $db->query("SELECT * FROM configuracion_web WHERE id_config = 1 LIMIT 1")->fetch();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-sliders text-warning me-2"></i>Configuración del Portal Web</h2>
                    <p class="text-muted mb-0">Personaliza la apariencia, mensajes públicos y políticas de acceso de la web</p>
                </div>
                <a href="<?= APP_URL ?>/" target="_blank" class="btn btn-outline-success">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver Página Pública
                </a>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card card-custom p-4 p-md-5">
                <form action="<?= APP_URL ?>/admin/configuracion" method="POST">
                    <input type="hidden" name="guardar_config" value="1">

                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">1. Textos del Escaparate de Inicio</h5>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Título Principal del Portal</label>
                        <input type="text" name="titulo_portal" class="form-control" value="<?= htmlspecialchars($cfg['titulo_portal'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subtítulo Descriptivo</label>
                        <input type="text" name="subtitulo_portal" class="form-control" value="<?= htmlspecialchars($cfg['subtitulo_portal'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Texto Explicativo de Bienvenida</label>
                        <textarea name="descripcion_bienvenida" class="form-control" rows="4"><?= htmlspecialchars($cfg['descripcion_bienvenida'] ?? '') ?></textarea>
                    </div>

                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">2. Información de Contacto y Soporte</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Teléfono de Soporte / Oficina</label>
                            <input type="text" name="telefono_soporte" class="form-control" value="<?= htmlspecialchars($cfg['telefono_soporte'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email de Contacto</label>
                            <input type="email" name="email_contacto" class="form-control" value="<?= htmlspecialchars($cfg['email_contacto'] ?? '') ?>">
                        </div>
                    </div>

                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">3. Parámetros de Seguridad y Nuevos Accesos</h5>
                    <div class="mb-3">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="permitir_registro_publico" name="permitir_registro_publico" value="1" <?= (!empty($cfg['permitir_registro_publico'])) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-6 fw-bold" for="permitir_registro_publico">Permitir solicitudes de alta pública desde la web</label>
                        </div>
                        <div class="form-text small">Si se desactiva, solo los administradores podrán dar de alta nuevos usuarios.</div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="usar_captcha" name="usar_captcha" value="1" <?= (!empty($cfg['usar_captcha'])) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-6 fw-bold text-success" for="usar_captcha">Exigir Captcha de seguridad en los formularios de registro</label>
                        </div>
                        <div class="form-text small">Previene registros masivos no deseados o intentos de spam.</div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-ag px-4 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Cambios de Configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
