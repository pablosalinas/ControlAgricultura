<?php
if (!Auth::canManageUsers()) {
    header("Location: " . APP_URL . "/dashboard");
    exit;
}

$pageTitle = "Estadísticas y Logs de Accesos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

// Filtros
$filtros = array(
    'solo_admin'   => isset($_GET['solo_admin']) && $_GET['solo_admin'] === '1',
    'ip'           => isset($_GET['ip']) ? trim($_GET['ip']) : '',
    'usuario'      => isset($_GET['usuario']) ? trim($_GET['usuario']) : '',
    'fecha_desde'  => isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '',
    'fecha_hasta'  => isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : ''
);

$limite = isset($_GET['limite']) ? min(500, max(20, (int)$_GET['limite'])) : 100;

// Obtener datos
$totalVisitas = AccessTracker::getTotalVisits();
$visitasHoy = AccessTracker::getTodayVisits();
$adminVisitas = AccessTracker::getAdminVisits();
$ipsUnicas = AccessTracker::getUniqueIPs();

$logs = AccessTracker::getLogs($filtros, $limite);
?>

<div class="container py-4">

    <!-- Encabezado con Azul Chulo Tecnológico -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-chart-line fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Auditoría, Estadísticas y Accesos</h2>
                <span class="text-muted small">Monitorización de visitas y registro exhaustivo de actividad en el sistema y paneles de administración</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/accesos" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-rotate-right me-1"></i> Actualizar
            </a>
            <a href="<?= APP_URL ?>/admin/gestion-usuarios" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-users me-1"></i> Gestión de Equipo
            </a>
        </div>
    </div>

    <!-- Tarjetas Resumen de Métricas -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-custom p-3 border-0 shadow-sm" style="border-left: 4px solid #1d4ed8 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Visitas Totales</div>
                        <div class="fs-3 fw-bold text-primary mt-1"><?= number_format($totalVisitas, 0, ',', '.') ?></div>
                    </div>
                    <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-eye fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-custom p-3 border-0 shadow-sm" style="border-left: 4px solid #06b6d4 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Visitas Hoy</div>
                        <div class="fs-3 fw-bold text-info mt-1"><?= number_format($visitasHoy, 0, ',', '.') ?></div>
                    </div>
                    <div class="rounded-circle p-3 bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-calendar-day fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-custom p-3 border-0 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Accesos a Administración</div>
                        <div class="fs-3 fw-bold text-warning mt-1"><?= number_format($adminVisitas, 0, ',', '.') ?></div>
                    </div>
                    <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-shield-halved fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-custom p-3 border-0 shadow-sm" style="border-left: 4px solid #10b981 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Visitantes Únicos (IPs)</div>
                        <div class="fs-3 fw-bold text-success mt-1"><?= number_format($ipsUnicas, 0, ',', '.') ?></div>
                    </div>
                    <div class="rounded-circle p-3 bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-network-wired fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros de Búsqueda -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm">
        <form action="<?= APP_URL ?>/admin/accesos" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Buscar por IP</label>
                <input type="text" name="ip" class="form-control form-control-sm" placeholder="Ej: 192.168.1..." value="<?= htmlspecialchars($filtros['ip']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Usuario / Rol</label>
                <input type="text" name="usuario" class="form-control form-control-sm" placeholder="Nombre o rol..." value="<?= htmlspecialchars($filtros['usuario']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Fecha Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_desde']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Fecha Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_hasta']) ?>">
            </div>
            <div class="col-md-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="solo_admin" value="1" id="checkSoloAdmin" <?= $filtros['solo_admin'] ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="form-check-label small fw-bold text-warning" for="checkSoloAdmin">
                        Solo Administración
                    </label>
                </div>
                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
                    <?php if ($filtros['ip'] || $filtros['usuario'] || $filtros['fecha_desde'] || $filtros['fecha_hasta'] || $filtros['solo_admin']): ?>
                        <a href="<?= APP_URL ?>/admin/accesos" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de Logs de Auditoría -->
    <div class="card card-ag shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-list-check me-2"></i>Últimos Accesos Registrados (<?= count($logs) ?>)
            </h6>
            <div class="text-muted small">
                Mostrando hasta <?= $limite ?> eventos más recientes
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th class="ps-4">Fecha / Hora</th>
                        <th>Dirección IP</th>
                        <th>Usuario / Sesión</th>
                        <th>Área / Ruta</th>
                        <th>Método & URL</th>
                        <th class="pe-4">Dispositivo / Agente</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-shield-cat fa-2x mb-3 text-secondary"></i>
                                <p class="mb-0">No se encontraron registros de accesos con los filtros actuales.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="ps-4 text-nowrap">
                                    <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($l['fecha_hora'])) ?></div>
                                    <div class="small text-muted font-monospace"><?= date('H:i:s', strtotime($l['fecha_hora'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark font-monospace border">
                                        <i class="fa-solid fa-location-dot me-1 text-primary"></i><?= htmlspecialchars($l['ip_address']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($l['nombre_usuario']): ?>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($l['nombre_usuario']) ?></div>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle small">
                                            <?= htmlspecialchars($l['rol_usuario'] ?: 'usuario') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-25 text-muted">
                                            <i class="fa-solid fa-user-secret me-1"></i>Visitante / Anónimo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($l['es_admin']): ?>
                                        <span class="badge bg-warning text-dark fw-bold">
                                            <i class="fa-solid fa-shield-halved me-1"></i>Administración
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">
                                            <i class="fa-solid fa-globe me-1"></i>Web Pública / Consulta
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($l['ruta']): ?>
                                        <div class="small font-monospace text-muted mt-1"><?= htmlspecialchars($l['ruta']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-dark small font-monospace me-1"><?= htmlspecialchars($l['metodo']) ?></span>
                                    <span class="small font-monospace text-secondary text-break"><?= htmlspecialchars($l['url_solicitada']) ?></span>
                                </td>
                                <td class="pe-4 small text-muted text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($l['user_agent']) ?>">
                                    <?php
                                    $ua = $l['user_agent'] ?: '';
                                    if (strpos($ua, 'Mobile') !== false) {
                                        echo '<i class="fa-solid fa-mobile-screen-button me-1 text-info"></i> Móvil';
                                    } elseif (strpos($ua, 'Windows') !== false) {
                                        echo '<i class="fa-brands fa-windows me-1 text-primary"></i> Windows';
                                    } elseif (strpos($ua, 'Mac') !== false) {
                                        echo '<i class="fa-brands fa-apple me-1 text-dark"></i> Mac';
                                    } elseif (strpos($ua, 'Linux') !== false) {
                                        echo '<i class="fa-brands fa-linux me-1 text-warning"></i> Linux';
                                    } else {
                                        echo '<i class="fa-solid fa-desktop me-1"></i> PC';
                                    }
                                    ?>
                                    <span class="d-block text-truncate" style="font-size: 0.75rem;"><?= htmlspecialchars($ua) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
