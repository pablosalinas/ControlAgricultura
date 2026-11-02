<?php
require_once SRC_PATH . '/Services/Captcha.php';
$captchaImage = Captcha::generateBase64Image();

$pageTitle = "Solicitud de Alta - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card card-custom p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-25 text-dark rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-user-plus fs-3"></i>
                    </div>
                    <h3 class="fw-bold">Solicitud de Acceso</h3>
                    <p class="text-muted small">
                        Completa tus datos para solicitar una cuenta. Tu solicitud requerirá de la <strong>aprobación previa del administrador</strong> antes de poder ingresar.
                    </p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success p-4 text-center" role="alert">
                        <i class="fa-solid fa-circle-check fs-1 text-success mb-3 d-block"></i>
                        <h5 class="fw-bold">¡Solicitud recibida con éxito!</h5>
                        <p class="mb-3"><?= htmlspecialchars($success) ?></p>
                        <a href="<?= APP_URL ?>/login" class="btn btn-success fw-bold px-4">Volver al Inicio de Sesión</a>
                    </div>
                <?php else: ?>

                <form action="<?= APP_URL ?>/register" method="POST">
                    <h6 class="text-uppercase text-muted fw-bold small mb-3 border-bottom pb-2">1. Datos Personales y de Explotación</h6>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="apellidos" class="form-label fw-semibold">Apellidos</label>
                            <input type="text" class="form-control" id="apellidos" name="apellidos" value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="empresa_o_propietario" class="form-label fw-semibold">Nombre de la Explotación o Propietario Agrícola <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="empresa_o_propietario" name="empresa_o_propietario" required placeholder="Ej: Finca Los Naranjos S.L. o Explotación Hnos. Martínez" value="<?= htmlspecialchars($_POST['empresa_o_propietario'] ?? '') ?>">
                        <div class="form-text">Si eres un nuevo propietario, se creará tu espacio de gestión exclusivo.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="cif_nif" class="form-label fw-semibold">NIF / CIF</label>
                            <input type="text" class="form-control" id="cif_nif" name="cif_nif" value="<?= htmlspecialchars($_POST['cif_nif'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label fw-semibold">Teléfono de Contacto</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                        </div>
                    </div>

                    <h6 class="text-uppercase text-muted fw-bold small mt-4 mb-3 border-bottom pb-2">2. Credenciales de Acceso</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="motivo" class="form-label fw-semibold">Motivo o detalles de la solicitud</label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="2" placeholder="Indica las fincas o actividad que deseas gestionar..."><?= htmlspecialchars($_POST['motivo'] ?? '') ?></textarea>
                    </div>

                    <h6 class="text-uppercase text-muted fw-bold small mt-4 mb-3 border-bottom pb-2">3. Control de Seguridad (Anti-Bot)</h6>

                    <div class="mb-4 p-3 bg-light rounded border">
                        <label for="captcha_input" class="form-label fw-semibold d-block">Introduce el código que ves en la imagen <span class="text-danger">*</span></label>
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= $captchaImage ?>" alt="Código Captcha" class="border rounded shadow-sm bg-white" style="height: 45px;">
                            <input type="text" class="form-control text-uppercase fw-bold text-center" style="max-width: 140px; letter-spacing: 3px;" id="captcha_input" name="captcha_input" required maxlength="6" autocomplete="off" placeholder="Código">
                        </div>
                        <div class="form-text small mt-2">Medida de seguridad para garantizar registros legítimos.</div>
                    </div>

                    <button type="submit" class="btn btn-ag w-100 py-3 fw-bold fs-6 shadow">
                        <i class="fa-solid fa-paper-plane me-2"></i>Enviar Solicitud al Administrador
                    </button>
                </form>

                <div class="text-center mt-4">
                    <span class="small text-muted">¿Ya tienes una cuenta aprobada? </span>
                    <a href="<?= APP_URL ?>/login" class="fw-bold text-success text-decoration-none">Iniciar Sesión</a>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
