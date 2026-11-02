<?php
if (!Auth::isAdmin()) {
    header("Location: " . APP_URL . "/dashboard");
    exit;
}

$pageTitle = "Configuración Global y Tarifas de Riego - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';
require_once SRC_PATH . '/Services/Mailer.php';

$db = Database::getConnection();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Guardar Configuración
    if (isset($_POST['guardar_config'])) {
        $titulo = trim($_POST['titulo_portal'] ?? '');
        $subtitulo = trim($_POST['subtitulo_portal'] ?? '');
        $desc = trim($_POST['descripcion_bienvenida'] ?? '');
        $telefono = trim($_POST['telefono_soporte'] ?? '');
        $email = trim($_POST['email_contacto'] ?? '');
        $permitirRegistro = isset($_POST['permitir_registro_publico']) ? 1 : 0;
        $usarCaptcha = isset($_POST['usar_captcha']) ? 1 : 0;

        // Precios
        $precioComunitaria = (float)($_POST['precio_hora_comunitaria'] ?? 12.00);
        $precioHortalizas = (float)($_POST['precio_hora_hortalizas'] ?? 15.00);
        $derramaComunidad = (float)($_POST['derrama_anual_comunidad'] ?? 150.00);

        // SMTP
        $smtpHost = trim($_POST['smtp_host'] ?? 'smtp.ionos.es');
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $smtpEnc = trim($_POST['smtp_encryption'] ?? 'tls');
        $emailRemitente = trim($_POST['email_remitente'] ?? 'info@moratalla-murcia.com');
        $emailComunidad = trim($_POST['email_comunidad'] ?? 'alharabemoratalla@gmail.com');
        $smtpPassPlain = trim($_POST['smtp_pass'] ?? '');

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
                    precio_hora_comunitaria = :pcom,
                    precio_hora_hortalizas = :phort,
                    derrama_anual_comunidad = :derr,
                    smtp_host = :shost,
                    smtp_port = :sport,
                    smtp_encryption = :senc,
                    email_remitente = :srem,
                    email_comunidad = :scom
            ";
            $params = [
                ':tit'   => $titulo,
                ':sub'   => $subtitulo,
                ':des'   => $desc,
                ':tel'   => $telefono,
                ':em'    => $email,
                ':reg'   => $permitirRegistro,
                ':cap'   => $usarCaptcha,
                ':pcom'  => $precioComunitaria,
                ':phort' => $precioHortalizas,
                ':derr'  => $derramaComunidad,
                ':shost' => $smtpHost,
                ':sport' => $smtpPort,
                ':senc'  => $smtpEnc,
                ':srem'  => $emailRemitente,
                ':scom'  => $emailComunidad
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

            $mensaje = "Configuración, precios de riego y credenciales de correo actualizadas con éxito.";
        } catch (Exception $e) {
            $error = "Error al actualizar la configuración: " . $e->getMessage();
        }
    }

    // 2. Probar Envío SMTP
    elseif (isset($_POST['probar_smtp'])) {
        $destPrueba = trim($_POST['email_prueba'] ?? '');
        if (empty($destPrueba)) {
            $destPrueba = Auth::user()['email'] ?? 'info@moratalla-murcia.com';
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

                    <!-- SECCIÓN 1: PRECIOS Y TARIFAS DE RIEGO -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-droplet text-info me-2"></i>1. Precios y Tarifas Oficiales de Riego
                    </h5>
                    <p class="text-muted small">
                        Estos valores se usarán por defecto en el cálculo automático del coste de riegos y al confeccionar solicitudes a la Comunidad de Regantes del Río Alhárabe.
                    </p>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Precio Hora Comunitaria (€/h)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="precio_hora_comunitaria" class="form-control font-monospace fw-bold" value="<?= $cfg['precio_hora_comunitaria'] ?? '12.00' ?>" required>
                                <span class="input-group-text">€/h</span>
                            </div>
                            <div class="form-text small">Riego ordinario por horas según tierra.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Precio Hora Comunitaria HORTALIZAS (€/h)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="precio_hora_hortalizas" class="form-control font-monospace fw-bold text-success" value="<?= $cfg['precio_hora_hortalizas'] ?? '15.00' ?>" required>
                                <span class="input-group-text">€/h</span>
                            </div>
                            <div class="form-text small">Tarifa especial fines de semana verano con tiempo pautado.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Derrama Anual Comunidad Regantes (€/año)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="derrama_anual_comunidad" class="form-control font-monospace fw-bold" value="<?= $cfg['derrama_anual_comunidad'] ?? '150.00' ?>" required>
                                <span class="input-group-text">€/año</span>
                            </div>
                            <div class="form-text small">Cuota fija de mantenimiento comunero.</div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: CORREO ELECTRÓNICO (SMTP CIFRADO) -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-envelope-shield text-primary me-2"></i>2. Servidor de Correo Electrónico (SMTP Seguro)
                    </h5>
                    <p class="text-muted small">
                        Configuración importada de Moratalla-Murcia-2026. Las credenciales se almacenan encriptadas con <strong>AES-256-CBC y firma HMAC-SHA256</strong>.
                    </p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Servidor Saliente SMTP</label>
                            <input type="text" name="smtp_host" class="form-control font-monospace" value="<?= htmlspecialchars($cfg['smtp_host'] ?? 'smtp.ionos.es') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Puerto</label>
                            <input type="number" name="smtp_port" class="form-control font-monospace" value="<?= $cfg['smtp_port'] ?? 587 ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Cifrado</label>
                            <select name="smtp_encryption" class="form-select font-monospace">
                                <option value="tls" <?= ($cfg['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS / TLS (587)</option>
                                <option value="ssl" <?= ($cfg['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                                <option value="none" <?= ($cfg['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Sin cifrado (25)</option>
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
                            <input type="email" name="email_remitente" class="form-control" value="<?= htmlspecialchars($cfg['email_remitente'] ?? 'info@moratalla-murcia.com') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Oficial de la Comunidad de Regantes</label>
                            <input type="email" name="email_comunidad" class="form-control" value="<?= htmlspecialchars($cfg['email_comunidad'] ?? 'alharabemoratalla@gmail.com') ?>" required>
                            <div class="form-text small">Dirección a la que se envían directamente las papeletas de riego.</div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: PORTAL Y BIENVENIDA -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-desktop text-secondary me-2"></i>3. Textos del Portal Web
                    </h5>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Título Principal del Portal</label>
                        <input type="text" name="titulo_portal" class="form-control" value="<?= htmlspecialchars($cfg['titulo_portal'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Subtítulo Descriptivo</label>
                        <input type="text" name="subtitulo_portal" class="form-control" value="<?= htmlspecialchars($cfg['subtitulo_portal'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Texto Explicativo de Bienvenida</label>
                        <textarea name="descripcion_bienvenida" class="form-control" rows="3"><?= htmlspecialchars($cfg['descripcion_bienvenida'] ?? '') ?></textarea>
                    </div>

                    <!-- SECCIÓN 4: SEGURIDAD Y CONTACTO -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-shield-halved text-success me-2"></i>4. Parámetros de Seguridad y Contacto
                    </h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Teléfono de Soporte</label>
                            <input type="text" name="telefono_soporte" class="form-control" value="<?= htmlspecialchars($cfg['telefono_soporte'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email de Contacto</label>
                            <input type="email" name="email_contacto" class="form-control" value="<?= htmlspecialchars($cfg['email_contacto'] ?? '') ?>">
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
                        <input type="email" name="email_prueba" class="form-control" placeholder="Introduce el correo donde recibir la prueba (ej: info@moratalla-murcia.com)" value="<?= htmlspecialchars(Auth::user()['email'] ?? 'info@moratalla-murcia.com') ?>" required>
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
