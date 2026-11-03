<?php
$pageTitle = "Iniciar Sesión - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-custom p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-lock fs-3"></i>
                    </div>
                    <h3 class="fw-bold">Acceso a la Plataforma</h3>
                    <p class="text-muted small">Introduce tus credenciales para acceder a tus fincas y dietario</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($info)): ?>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="fa-solid fa-circle-info me-2 fs-5"></i>
                        <div><?= htmlspecialchars($info) ?></div>
                    </div>
                <?php endif; ?>

                <form action="<?= APP_URL ?>/login" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="tu@email.com" value="<?= htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-key text-muted"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                        </div>
                    </div>

                    <!-- Reto Anti-robot (Captcha Matemático Sencillo) -->
                    <div class="mb-4 p-3 rounded-3 border bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label for="captcha_math" class="form-label fw-semibold small text-primary mb-0">
                                <i class="fa-solid fa-shield-halved me-1"></i> Control de Seguridad Anti-robot
                            </label>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle font-monospace px-2 py-1">
                                <?= htmlspecialchars(isset($mathChallenge['text']) ? $mathChallenge['text'] : '¿Cuánto es 3 + 4?') ?>
                            </span>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted fw-bold">=</span>
                            <input type="number" class="form-control" id="captcha_math" name="captcha_math" required placeholder="Introduce el resultado de la suma" autocomplete="off" min="0" max="30">
                        </div>
                        <div class="form-text small text-muted">Resuelve la suma para verificar que eres una persona.</div>
                    </div>

                    <button type="submit" class="btn btn-ag w-100 py-2 fw-bold mb-3 shadow-sm">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Entrar
                    </button>
                </form>

                <hr class="text-muted my-4">

                <div class="text-center">
                    <p class="small text-muted mb-1">¿No tienes cuenta de propietario o técnico?</p>
                    <a href="<?= APP_URL ?>/register" class="fw-bold text-success text-decoration-none">
                        <i class="fa-solid fa-user-plus me-1"></i>Solicitar acceso al administrador
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
