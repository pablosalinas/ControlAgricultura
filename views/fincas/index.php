<?php
$pageTitle = "Gestión de Fincas y Parcelas - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();

$sql = "
    SELECT f.*, p.nombre_paraje 
    FROM fincas f 
    LEFT JOIN parajes p ON f.id_paraje = p.id_paraje 
";
if ($tenantId && !Auth::isAdmin()) {
    $sql .= " WHERE f.id_propietario = " . (int)$tenantId;
}
$sql .= " ORDER BY f.nombre_finca ASC";

$fincas = $db->query($sql)->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-map-location-dot text-success me-2"></i>Mis Fincas y Parcelas</h2>
            <p class="text-muted mb-0">Control registral, catastral y asignación de derechos de riego por tanda</p>
        </div>
        <div>
            <a href="<?= APP_URL ?>/fincas/nueva" class="btn btn-ag shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Nueva Finca
            </a>
        </div>
    </div>

    <?php if (empty($fincas)): ?>
        <div class="card card-custom p-5 text-center">
            <i class="fa-solid fa-vector-square fs-1 text-muted mb-3"></i>
            <h4 class="fw-bold">No hay fincas registradas</h4>
            <p class="text-muted">Añade tu primera finca para comenzar a gestionar plantaciones y labores.</p>
            <div class="mt-2">
                <a href="<?= APP_URL ?>/fincas/nueva" class="btn btn-ag">Añadir Finca Ahora</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card card-custom p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre de Finca</th>
                            <th>Paraje</th>
                            <th>Superficie</th>
                            <th>Datos Catastrales</th>
                            <th>Comunidad / Parada / Tablón</th>
                            <th>Tanda (Prop / Disp)</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fincas as $f): ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <i class="fa-solid fa-draw-polygon text-success me-2"></i><?= htmlspecialchars($f['nombre_finca']) ?>
                                </td>
                                <td><?= htmlspecialchars($f['nombre_paraje'] ?? 'Sin asignar') ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= number_format($f['superficie_ha'], 2, ',', '.') ?> ha</span>
                                </td>
                                <td>
                                    <?php if ($f['referencia_catastral']): ?>
                                        <a href="https://www1.sedecatastro.gob.es/CYGConsultaInformacionCatastral/CYGCampanyas.aspx?refcat=<?= urlencode($f['referencia_catastral']) ?>" target="_blank" class="text-decoration-none small text-primary fw-semibold" title="Abrir en Sede Electrónica del Catastro">
                                            <?= htmlspecialchars($f['referencia_catastral']) ?> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.7rem;"></i>
                                        </a>
                                        <div class="small text-muted">Pol <?= htmlspecialchars($f['poligono'] ?? '-') ?> / Parc <?= htmlspecialchars($f['parcela'] ?? '-') ?></div>
                                    <?php else: ?>
                                        <span class="small text-muted">Pol <?= htmlspecialchars($f['poligono'] ?? '-') ?> / Parc <?= htmlspecialchars($f['parcela'] ?? '-') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <strong><?= htmlspecialchars($f['zona_riego'] ?? 'Comunidad') ?></strong>
                                    </div>
                                    <div class="small text-muted">
                                        Parada: <?= htmlspecialchars($f['parada'] ?? '-') ?> | Tablón: <?= htmlspecialchars($f['tablon'] ?? '-') ?>
                                        <?php if ($f['num_socio_comunidad']): ?>
                                            (Socio: #<?= htmlspecialchars($f['num_socio_comunidad']) ?>)
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">
                                        <?= $f['horas_propiedad_tanda'] ?>h prop.
                                    </span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">
                                        <?= $f['horas_disponibles_tanda'] ?>h disp.
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= APP_URL ?>/plantaciones?finca_id=<?= $f['id_finca'] ?>" class="btn btn-sm btn-outline-success" title="Ver plantaciones">
                                        <i class="fa-solid fa-seedling"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
