<?php
if (!Auth::isAdmin()) {
    header("Location: " . APP_URL . "/dashboard");
    exit;
}

$pageTitle = "Configuración del Sistema y Correo Electrónico - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';
require_once SRC_PATH . '/Services/Mailer.php';

$db = Database::getConnection();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Guardar Configuración
    if (isset($_POST['guardar_config'])) {
        $titulo = trim(isset($_POST['titulo_portal']) ? $_POST['titulo_portal'] : '');
        $subtitulo = trim(isset($_POST['subtitulo_portal']) ? $_POST['subtitulo_portal'] : '');
        $desc = trim(isset($_POST['descripcion_bienvenida']) ? $_POST['descripcion_bienvenida'] : '');
        $telefono = trim(isset($_POST['telefono_soporte']) ? $_POST['telefono_soporte'] : '');
        $email = trim(isset($_POST['email_contacto']) ? $_POST['email_contacto'] : '');
        $permitirRegistro = isset($_POST['permitir_registro_publico']) ? 1 : 0;
        $usarCaptcha = isset($_POST['usar_captcha']) ? 1 : 0;

        // SMTP
        $smtpHost = trim(isset($_POST['smtp_host']) ? $_POST['smtp_host'] : 'smtp.ionos.es');
        $smtpPort = (int)(isset($_POST['smtp_port']) ? $_POST['smtp_port'] : 587);
        $smtpEnc = trim(isset($_POST['smtp_encryption']) ? $_POST['smtp_encryption'] : 'tls');
        $emailRemitente = trim(isset($_POST['email_remitente']) ? $_POST['email_remitente'] : 'info@moratalla-murcia.com');
        $smtpPassPlain = trim(isset($_POST['smtp_pass']) ? $_POST['smtp_pass'] : '');

        try {
            $sqlUpd = "
                UPDATE configuracion_web SET
                    titulo_portal = :tit,
                    subtitulo_portal = :sub,
                    descripcion_bienvenida = :des,
                    telefono_soporte = :tel,
                    email_contacto = :em,
                    permitir_registro_publico = :reg,
                    usar_captcha = :cap,
                    smtp_host = :shost,
                    smtp_port = :sport,
                    smtp_encryption = :senc,
                    email_remitente = :srem
            ";
            $params = [
                ':tit'   => $titulo,
                ':sub'   => $subtitulo,
                ':des'   => $desc,
                ':tel'   => $telefono,
                ':em'    => $email,
                ':reg'   => $permitirRegistro,
                ':cap'   => $usarCaptcha,
                ':shost' => $smtpHost,
                ':sport' => $smtpPort,
                ':senc'  => $smtpEnc,
                ':srem'  => $emailRemitente
            ];

            // Si se introdujo una nueva contraseña SMTP, cifrarla con AES-256-CBC + HMAC
            if (!empty($smtpPassPlain)) {
                $sqlUpd .= ", smtp_pass_enc = :spass";
                $params[':spass'] = Mailer::encrypt($smtpPassPlain);
            }
            if (!empty($_POST['smtp_user'])) {
                $sqlUpd .= ", smtp_user_enc = :suser";
                $params[':suser'] = Mailer::encrypt(trim($_POST['smtp_user']));
            }

            $sqlUpd .= " WHERE id_config = 1";
            $stmt = $db->prepare($sqlUpd);
            $stmt->execute($params);

            $mensaje = "Configuración y credenciales de correo actualizadas con éxito.";
        } catch (Exception $e) {
            $error = "Error al actualizar la configuración: " . $e->getMessage();
        }
    }

    // 2. Probar Envío SMTP
    elseif (isset($_POST['probar_smtp'])) {
        $destPrueba = trim(isset($_POST['email_prueba']) ? $_POST['email_prueba'] : '');
        if (empty($destPrueba)) {
            $destPrueba = isset(Auth::user()['email']) ? Auth::user()['email'] : 'info@moratalla-murcia.com';
        }

        $resTest = Mailer::send(
            $destPrueba,
            "Prueba de Correo AgroDietario (Moratalla-Murcia)",
            "<h3>¡Conexión SMTP y Encriptación Funcionando!</h3><p>Este es un correo de prueba generado desde el módulo de configuración de AgroDietario utilizando la cuenta <strong>info@moratalla-murcia.com</strong> en IONOS.</p><p>Fecha y hora: " . date('d/m/Y H:i:s') . "</p>"
        );

        if ($resTest['success']) {
            $mensaje = "✅ ¡Prueba de envío SMTP exitosa! El correo de test fue enviado correctamente a <strong>" . htmlspecialchars($destPrueba) . "</strong>.";
        } else {
            $error = "❌ Falló el envío de correo de prueba: " . htmlspecialchars($resTest['message']);
        }
    }
}

// Cargar configuración actual
$cfg = $db->query("SELECT * FROM configuracion_web WHERE id_config = 1 LIMIT 1")->fetch();

// Descifrar usuario SMTP actual para mostrarlo en el input
$userDescifrado = !empty($cfg['smtp_user_enc']) ? Mailer::decrypt($cfg['smtp_user_enc']) : 'info@moratalla-murcia.com';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-sliders text-primary me-2"></i>Configuración del Sistema</h2>
                    <p class="text-muted mb-0">Personaliza los textos públicos, precios de agua de riego y servidor de correo SMTP encriptado</p>
                </div>
                <a href="<?= APP_URL ?>/" target="_blank" class="btn btn-outline-primary">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver Portada Pública
                </a>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm"><?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="card card-custom p-4 p-md-5 mb-4">
                <form action="<?= APP_URL ?>/admin/configuracion" method="POST">
                    <input type="hidden" name="guardar_config" value="1">

                    <!-- AVISO OFICIAL: TARIFAS Y DERRAMAS GESTIONADAS EN COMUNIDADES DE REGANTES -->
                    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center justify-content-between p-3 mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle p-2 bg-info bg-opacity-20 text-info">
                                <i class="fa-solid fa-water fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-primary">Precios, Tarifas y Derramas de Riego</h6>
                                <p class="small text-muted mb-0">
                                    Los precios de agua (tanda, comunitaria, comunitaria hortalizas), consumos máximos y derramas los fija directamente cada Comunidad de Regantes.
                                </p>
                            </div>
                        </div>
                        <a href="<?= APP_URL ?>/comunidades-regantes" class="btn btn-sm btn-ag text-nowrap ms-2">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ir a Comunidades de Regantes
                        </a>
                    </div>

                    <!-- SECCIÓN 1: CORREO ELECTRÓNICO (SMTP CIFRADO) -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-envelope-shield text-primary me-2"></i>1. Servidor de Correo Electrónico (SMTP Seguro)
                    </h5>
                    <p class="text-muted small">
                        Configuración importada de Moratalla-Murcia-2026. Las credenciales se almacenan encriptadas con <strong>AES-256-CBC y firma HMAC-SHA256</strong>.
                    </p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Servidor Saliente SMTP</label>
                            <input type="text" name="smtp_host" class="form-control font-monospace" value="<?= htmlspecialchars(isset($cfg['smtp_host']) ? $cfg['smtp_host'] : 'smtp.ionos.es') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Puerto</label>
                            <input type="number" name="smtp_port" class="form-control font-monospace" value="<?= isset($cfg['smtp_port']) ? $cfg['smtp_port'] : 587 ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Cifrado</label>
                            <select name="smtp_encryption" class="form-select font-monospace">
                                <option value="tls" <?= (isset($cfg['smtp_encryption']) && $cfg['smtp_encryption'] === 'tls') ? 'selected' : '' ?>>STARTTLS / TLS (587)</option>
                                <option value="ssl" <?= (isset($cfg['smtp_encryption']) && $cfg['smtp_encryption'] === 'ssl') ? 'selected' : '' ?>>SSL (465)</option>
                                <option value="none" <?= (isset($cfg['smtp_encryption']) && $cfg['smtp_encryption'] === 'none') ? 'selected' : '' ?>>Sin cifrado (25)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Usuario SMTP / Cuenta Remitente</label>
                            <input type="text" name="smtp_user" class="form-control font-monospace" value="<?= htmlspecialchars($userDescifrado) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Contraseña SMTP (Almacenamiento Cifrado)</label>
                            <div class="input-group">
                                <input type="password" id="input_smtp_pass" name="smtp_pass" class="form-control font-monospace" placeholder="•••••••••••••••••••• (dejar en blanco para mantener la actual)">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePass('input_smtp_pass', 'eye_smtp')">
                                    <i class="fa-solid fa-eye" id="eye_smtp"></i>
                                </button>
                            </div>
                            <div class="form-text small">La clave se encripta inmediatamente con clave criptográfica militar en base de datos.</div>
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Remitente Visible</label>
                            <input type="email" name="email_remitente" class="form-control" value="<?= htmlspecialchars(isset($cfg['email_remitente']) ? $cfg['email_remitente'] : 'info@moratalla-murcia.com') ?>" required>
                            <div class="form-text small">Dirección desde la que saldrán las solicitudes y notificaciones a los regantes y comunidades.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Comunidad de Regantes Oficial</label>
                            <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-between">
                                <span class="small text-muted"><i class="fa-solid fa-water text-primary me-1"></i>El email de envío se toma de la <strong>ficha de la comunidad activa</strong>.</span>
                                <a href="<?= APP_URL ?>/comunidades-regantes" class="btn btn-sm btn-outline-primary py-1 px-2 text-nowrap"><i class="fa-solid fa-pen-to-square me-1"></i>Ver Ficha</a>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: PORTAL Y BIENVENIDA -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-desktop text-secondary me-2"></i>2. Textos del Portal Web
                    </h5>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Título Principal del Portal</label>
                        <input type="text" name="titulo_portal" class="form-control" value="<?= htmlspecialchars(isset($cfg['titulo_portal']) ? $cfg['titulo_portal'] : '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Subtítulo Descriptivo</label>
                        <input type="text" name="subtitulo_portal" class="form-control" value="<?= htmlspecialchars(isset($cfg['subtitulo_portal']) ? $cfg['subtitulo_portal'] : '') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Texto Explicativo de Bienvenida</label>
                        <textarea name="descripcion_bienvenida" class="form-control" rows="3"><?= htmlspecialchars(isset($cfg['descripcion_bienvenida']) ? $cfg['descripcion_bienvenida'] : '') ?></textarea>
                    </div>

                    <!-- SECCIÓN 3: SEGURIDAD Y CONTACTO -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-shield-halved text-success me-2"></i>3. Parámetros de Seguridad y Contacto
                    </h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Teléfono de Soporte</label>
                            <input type="text" name="telefono_soporte" class="form-control" value="<?= htmlspecialchars(isset($cfg['telefono_soporte']) ? $cfg['telefono_soporte'] : '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email de Contacto</label>
                            <input type="email" name="email_contacto" class="form-control" value="<?= htmlspecialchars(isset($cfg['email_contacto']) ? $cfg['email_contacto'] : '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="permitir_registro_publico" name="permitir_registro_publico" value="1" <?= (!empty($cfg['permitir_registro_publico'])) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-6 fw-bold" for="permitir_registro_publico">Permitir solicitudes de alta pública desde la web</label>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="usar_captcha" name="usar_captcha" value="1" <?= (!empty($cfg['usar_captcha'])) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-6 fw-bold text-success" for="usar_captcha">Exigir Captcha de seguridad en los formularios de registro</label>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-ag px-4 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Todos los Cambios
                        </button>
                    </div>
                </form>
            </div>

            <!-- Panel de Prueba Interactiva SMTP -->
            <div class="card card-custom p-4 border-start border-4 border-info">
                <h5 class="fw-bold text-dark mb-2">
                    <i class="fa-solid fa-vial-circle-check text-info me-2"></i>Probar Conexión SMTP en Tiempo Real
                </h5>
                <p class="text-muted small mb-3">
                    Envía un correo de test instantáneo utilizando el socket TLS nativo y las credenciales cifradas para verificar que IONOS acepta la conexión.
                </p>
                <form action="<?= APP_URL ?>/admin/configuracion" method="POST" class="row g-2 align-items-center">
                    <input type="hidden" name="probar_smtp" value="1">
                    <div class="col-md-8">
                        <input type="email" name="email_prueba" class="form-control" placeholder="Introduce el correo donde recibir la prueba (ej: info@moratalla-murcia.com)" value="<?= htmlspecialchars(isset(Auth::user()['email']) ? Auth::user()['email'] : 'info@moratalla-murcia.com') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="fa-solid fa-paper-plane me-1"></i> Enviar Correo de Prueba
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
function togglePass(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.classList.remove('fa-eye');
        ico.classList.add('fa-eye-slash');
    } else {
        inp.type = 'password';
        ico.classList.remove('fa-eye-slash');
        ico.classList.add('fa-eye');
    }
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
