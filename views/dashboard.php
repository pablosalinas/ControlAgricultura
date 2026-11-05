<?php
$pageTitle = "Panel de Control - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$user = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();
$db = Database::getConnection();

// Conmutar explotación si se solicita expresamente
if (isset($_GET['explotacion_id'])) {
    $conmutarId = trim($_GET['explotacion_id']);
    if (($conmutarId === '0' || $conmutarId === 'todas') && $isSuperAdmin) {
        Auth::switchTenant(null);
    } else {
        Auth::switchTenant((int)$conmutarId);
    }
}

$tenantId = Auth::tenantId();
$user = Auth::user();

// Obtener lista de explotaciones para el selector
$userTenants = array();
if ($isSuperAdmin) {
    $userTenants = $db->query("SELECT id_propietario, nombre_razon_social FROM propietarios WHERE activo = 1 ORDER BY nombre_razon_social ASC")->fetchAll();
} else if (Auth::check()) {
    $userTenants = Auth::getUserTenants(Auth::user()['id']);
}

// Estadísticas rápidas: Se restringen estrictamente a la explotación seleccionada.
// Salvo que el usuario sea Superadministrador y expresamente elija la "Vista Global (Todas)"
$stats = array(
    'fincas' => 0,
    'hectareas' => 0.0,
    'plantaciones' => 0
);

try {
    if (!empty($tenantId)) {
        // 1. Datos exclusivos de la explotación seleccionada
        $stmtFincas = $db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(superficie_ha), 0) AS total_ha FROM fincas WHERE id_propietario = :prop AND activo = 1");
        $stmtFincas->execute(array(':prop' => (int)$tenantId));
        $rowF = $stmtFincas->fetch();
        $stats['fincas'] = (int)($rowF ? $rowF['total'] : 0);
        $stats['hectareas'] = (float)($rowF ? $rowF['total_ha'] : 0.0);

        $stmtPlan = $db->prepare("
            SELECT COUNT(*) AS total 
            FROM plantaciones p 
            JOIN fincas f ON p.id_finca = f.id_finca 
            WHERE f.id_propietario = :prop 
              AND (p.activo = 1 OR p.activo IS NULL) 
              AND p.estado IN ('en_crecimiento', 'produccion')
        ");
        $stmtPlan->execute(array(':prop' => (int)$tenantId));
        $rowP = $stmtPlan->fetch();
        $stats['plantaciones'] = (int)($rowP ? $rowP['total'] : 0);
    } elseif ($isSuperAdmin) {
        // 2. Vista Global de todas las explotaciones (exclusiva para perfil Superadministrador bajo petición)
        $rowF = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(superficie_ha), 0) AS total_ha FROM fincas WHERE activo = 1")->fetch();
        $stats['fincas'] = (int)($rowF ? $rowF['total'] : 0);
        $stats['hectareas'] = (float)($rowF ? $rowF['total_ha'] : 0.0);
        $stats['plantaciones'] = (int)$db->query("
            SELECT COUNT(*) 
            FROM plantaciones p 
            JOIN fincas f ON p.id_finca = f.id_finca 
            WHERE (p.activo = 1 OR p.activo IS NULL) 
              AND p.estado IN ('en_crecimiento', 'produccion')
        ")->fetchColumn();
    }
} catch (Exception $e) {
    // Si la BD aún está inicializándose
}
?>

<div class="container py-4">
    <!-- Bienvenida y Selector de Explotación -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div>
            <h2 class="fw-bold mb-1 text-dark">Bienvenido, <?= htmlspecialchars($user['nombre']) ?></h2>
            <div class="d-flex flex-wrap align-items-center gap-2 text-muted">
                <?php if (count($userTenants) > 1 || $isSuperAdmin): ?>
                    <!-- Selector Rápido de Explotación Activa -->
                    <form method="GET" action="<?= APP_URL ?>/dashboard" class="d-inline-flex align-items-center">
                        <div class="input-group input-group-sm shadow-sm" style="max-width: 340px;">
                            <span class="input-group-text bg-white text-primary fw-bold"><i class="fa-solid fa-building-wheat me-1"></i> Explotación:</span>
                            <select name="explotacion_id" class="form-select fw-semibold" onchange="this.form.submit()">
                                <?php if ($isSuperAdmin): ?>
                                    <option value="todas" <?= empty($tenantId) ? 'selected' : '' ?>>🌐 Todas las Explotaciones (Vista Global)</option>
                                <?php endif; ?>
                                <?php foreach ($userTenants as $t): ?>
                                    <option value="<?= $t['id_propietario'] ?>" <?= ($tenantId == $t['id_propietario']) ? 'selected' : '' ?>>
                                        🏢 <?= htmlspecialchars($t['nombre_razon_social']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php else: ?>
                    <span>
                        <i class="fa-solid fa-building-wheat text-primary me-1"></i>
                        Explotación: <strong class="text-dark"><?= htmlspecialchars($user['nombre_propietario']) ?></strong>
                    </span>
                <?php endif; ?>

                <a href="<?= APP_URL ?>/explotaciones" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill" style="font-size: 0.78rem;" title="Gestionar y dar de alta explotaciones agrícolas">
                    <i class="fa-solid fa-sliders me-1"></i> Administrar Explotaciones
                </a>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle text-capitalize"><?= htmlspecialchars($user['rol']) ?></span>
                
                <?php if (!empty($tenantId)): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.75rem;">
                        <i class="fa-solid fa-filter me-1"></i>Filtrado: <?= htmlspecialchars($user['nombre_propietario']) ?>
                    </span>
                <?php elseif ($isSuperAdmin): ?>
                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50" style="font-size: 0.75rem;">
                        <i class="fa-solid fa-globe me-1 text-primary"></i>Vista Global de Todas las Explotaciones
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <a href="<?= APP_URL ?>/fincas/nueva" class="btn btn-ag shadow-sm">
                <i class="fa-solid fa-plus-circle me-1"></i> Nueva Finca
            </a>
            <a href="<?= APP_URL ?>/tratamientos" class="btn btn-outline-primary">
                <i class="fa-solid fa-shield-virus me-1"></i> Registrar Tratamiento
            </a>
        </div>
    </div>

    <!-- Tarjetas de Resumen en Azul Chulo -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="card card-custom p-4 border-start border-4 border-primary">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Fincas Registradas</div>
                        <h2 class="fw-bold my-1 text-dark"><?= $stats['fincas'] ?></h2>
                        <span class="text-muted small"><?= number_format($stats['hectareas'], 2, ',', '.') ?> ha gestionadas</span>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 fs-3">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-4">
            <div class="card card-custom p-4 border-start border-4 border-info">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Plantaciones Activas</div>
                        <h2 class="fw-bold my-1 text-dark"><?= $stats['plantaciones'] ?></h2>
                        <span class="text-muted small">En crecimiento o producción</span>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-3 fs-3">
                        <i class="fa-solid fa-dna"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-4">
            <div class="card card-custom p-4 border-start border-4 border-warning">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Cuaderno de Campo</div>
                        <h2 class="fw-bold my-1 text-dark">Oficial</h2>
                        <span class="text-muted small">Trazabilidad para auditorías</span>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 fs-3">
                        <i class="fa-solid fa-certificate"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Malla de Accesos Rápidos con Iconos Innovadores -->
    <h4 class="fw-bold mb-3"><i class="fa-solid fa-bolt text-primary me-2"></i>Operaciones de Campo</h4>
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-4 col-lg-2">
            <a href="<?= APP_URL ?>/tratamientos" class="card card-custom p-3 text-center text-decoration-none text-dark h-100 hover-shadow transition">
                <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mx-auto mb-2" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-shield-virus fs-4"></i>
                </div>
                <div class="fw-bold small">Tratamientos</div>
                <div class="text-muted" style="font-size:0.75rem;">Convencional / Eco</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="<?= APP_URL ?>/riegos" class="card card-custom p-3 text-center text-decoration-none text-dark h-100 hover-shadow transition">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mx-auto mb-2" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-gauge-high fs-4"></i>
                </div>
                <div class="fw-bold small">Riegos</div>
                <div class="text-muted" style="font-size:0.75rem;">Tandas / Caudales</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="<?= APP_URL ?>/labores" class="card card-custom p-3 text-center text-decoration-none text-dark h-100 hover-shadow transition">
                <div class="d-inline-flex align-items-center justify-content-center bg-secondary bg-opacity-10 text-secondary rounded-circle mx-auto mb-2" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-mountain-sun fs-4"></i>
                </div>
                <div class="fw-bold small">Laboreo y Suelo</div>
                <div class="text-muted" style="font-size:0.75rem;">Arado / Desbroce</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="<?= APP_URL ?>/podas" class="card card-custom p-3 text-center text-decoration-none text-dark h-100 hover-shadow transition">
                <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mx-auto mb-2" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-arrows-spin fs-4"></i>
                </div>
                <div class="fw-bold small">Podas y Talas</div>
                <div class="text-muted" style="font-size:0.75rem;">Reciclaje Biomasa</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="<?= APP_URL ?>/recolecciones" class="card card-custom p-3 text-center text-decoration-none text-dark h-100 hover-shadow transition">
                <div class="d-inline-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info rounded-circle mx-auto mb-2" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-scale-balanced fs-4"></i>
                </div>
                <div class="fw-bold small">Recolección</div>
                <div class="text-muted" style="font-size:0.75rem;">Báscula y Venta</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="<?= APP_URL ?>/diario" class="card card-custom p-3 text-center text-decoration-none text-dark h-100 hover-shadow transition">
                <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle mx-auto mb-2" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-camera-retro fs-4"></i>
                </div>
                <div class="fw-bold small">Diario Visual</div>
                <div class="text-muted" style="font-size:0.75rem;">Fotos y Clima</div>
            </a>
        </div>
    </div>

    <!-- Listado Rápido de Fincas -->
    <div class="card card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-layer-group me-2 text-primary"></i>Fincas y Parcelas</h5>
            <a href="<?= APP_URL ?>/fincas" class="btn btn-sm btn-outline-primary">Ver todas</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Finca</th>
                        <th>Paraje</th>
                        <th>Superficie</th>
                        <th>Catastro (Pol/Parc)</th>
                        <th>Derechos Riego (Tanda)</th>
                        <th class="text-end">Ficha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fincas = array();
                    if (!empty($tenantId)) {
                        $stmt = $db->prepare("
                            SELECT f.*, p.nombre_paraje 
                            FROM fincas f 
                            LEFT JOIN parajes p ON f.id_paraje = p.id_paraje 
                            WHERE f.id_propietario = :prop AND f.activo = 1
                            ORDER BY f.nombre_finca ASC 
                            LIMIT 5
                        ");
                        $stmt->execute(array(':prop' => (int)$tenantId));
                        $fincas = $stmt->fetchAll();
                    } elseif ($isSuperAdmin) {
                        $fincas = $db->query("
                            SELECT f.*, p.nombre_paraje 
                            FROM fincas f 
                            LEFT JOIN parajes p ON f.id_paraje = p.id_paraje 
                            WHERE f.activo = 1
                            ORDER BY f.nombre_finca ASC 
                            LIMIT 5
                        ")->fetchAll();
                    }
                    ?>
                    <?php if (empty($fincas)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-circle-info me-1"></i> Aún no has registrado ninguna finca. 
                                <a href="<?= APP_URL ?>/fincas/nueva" class="text-primary fw-bold">Crear la primera finca ahora</a>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fincas as $f): ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <i class="fa-solid fa-draw-polygon text-primary me-2"></i><?= htmlspecialchars($f['nombre_finca']) ?>
                                </td>
                                <td><?= htmlspecialchars($f['nombre_paraje'] ? $f['nombre_paraje'] : 'Sin paraje') ?></td>
                                <td><span class="badge bg-light text-dark border"><?= number_format($f['superficie_ha'], 2, ',', '.') ?> ha</span></td>
                                <td>
                                    <?php if ($f['referencia_catastral']): ?>
                                        <a href="https://www1.sedecatastro.gob.es/CYGConsultaInformacionCatastral/CYGCampanyas.aspx?refcat=<?= urlencode($f['referencia_catastral']) ?>" target="_blank" class="text-decoration-none small text-primary fw-semibold" title="Consultar en Sede del Catastro">
                                            <?= htmlspecialchars($f['referencia_catastral']) ?> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.7rem;"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Pol <?= htmlspecialchars($f['poligono'] ? $f['poligono'] : '-') ?> / Parc <?= htmlspecialchars($f['parcela'] ? $f['parcela'] : '-') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small text-muted">
                                        Parada: <?= htmlspecialchars($f['parada'] ? $f['parada'] : '-') ?> | Tablón: <?= htmlspecialchars($f['tablon'] ? $f['tablon'] : '-') ?><br>
                                        <strong><?= $f['horas_propiedad_tanda'] ?>h</strong> prop. / <strong><?= $f['horas_disponibles_tanda'] ?>h</strong> disp.
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= APP_URL ?>/fincas/detalle?id=<?= $f['id_finca'] ?>" class="btn btn-sm btn-outline-primary" title="Ver ficha técnica">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
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
