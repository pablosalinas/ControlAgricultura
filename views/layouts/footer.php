<?php
// Obtener logo dinámico: de la explotación activa si tiene, o el oficial de la web
$footerLogo = APP_URL . '/assets/img/logo.jpg';
$footerTitle = APP_NAME;
$currentTenantId = class_exists('Auth') ? Auth::tenantId() : null;

if (!empty($currentTenantId) && class_exists('Database')) {
    try {
        $dbFooter = Database::getConnection();
        if ($dbFooter) {
            $stmtLogo = $dbFooter->prepare("SELECT logo, nombre_razon_social FROM propietarios WHERE id_propietario = :id LIMIT 1");
            $stmtLogo->execute(array(':id' => (int)$currentTenantId));
            $rowExp = $stmtLogo->fetch();
            if (!empty($rowExp['logo'])) {
                $footerLogo = APP_URL . '/' . ltrim($rowExp['logo'], '/');
                $footerTitle = $rowExp['nombre_razon_social'];
            }
        }
    } catch (Exception $e) {}
}

// Copyright dinámico: 2026 si es 2026, o 2026 - AñoActual si es posterior
$anioActual = (int)date('Y');
$copyrightPeriodo = ($anioActual > 2026) ? "2026 - {$anioActual}" : "2026";

// Contador de visitas
$contadorVisitas = class_exists('AccessTracker') ? AccessTracker::getTotalVisits() : 0;
?>
</main>

<footer class="footer-ag py-4 mt-5">
    <div class="container">
        <div class="row align-items-center g-3">
            <!-- Título y Logotipo Oficial / Explotación -->
            <div class="col-lg-6 text-center text-lg-start">
                <div class="d-inline-flex align-items-center gap-2 mb-2">
                    <img src="<?= htmlspecialchars($footerLogo) ?>" alt="<?= htmlspecialchars($footerTitle) ?>" class="rounded-3 shadow-sm border border-info border-opacity-25" style="height: 36px; width: 36px; object-fit: cover;">
                    <span class="fw-bold text-white fs-5"><?= htmlspecialchars($footerTitle) ?></span>
                </div>
                <div class="small" style="color: #cbd5e1;">
                    <strong class="text-white">Copyright &copy; <?= $copyrightPeriodo ?> AgroDietario.</strong> Todos los derechos reservados &bull; Plataforma de Gestión Agrícola y Cuaderno de Explotación
                </div>
            </div>

            <!-- Contador de Visitas, Entorno y Seguridad -->
            <div class="col-lg-6 text-center text-lg-end">
                <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-lg-end gap-2">
                    <!-- Contador de Visitas Web (Visible para todos los usuarios y visitantes) -->
                    <span class="badge bg-dark bg-opacity-75 border border-info border-opacity-30 text-info px-3 py-2 small" title="Total de visitas registradas en la plataforma">
                        <i class="fa-solid fa-chart-line me-1 text-cyan"></i> Visitas: <strong class="text-white"><?= number_format($contadorVisitas, 0, ',', '.') ?></strong>
                    </span>

                    <span class="badge bg-primary bg-opacity-20 text-info border border-info border-opacity-25 px-2 py-2 small">
                        <?= strtoupper(APP_ENV) ?>
                    </span>

                    <span class="small ms-1" style="color: #94a3b8;">
                        <i class="fa-solid fa-shield-halved text-info me-1"></i>SSL Seguro
                    </span>

                    <?php if (class_exists('Auth') && Auth::canManageUsers()): ?>
                        <a href="<?= APP_URL ?>/admin/accesos" class="btn btn-sm btn-outline-info py-0 px-2 small ms-1" title="Ver logs detallados de accesos">
                            <i class="fa-solid fa-chart-pie me-1"></i>Auditoría
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
