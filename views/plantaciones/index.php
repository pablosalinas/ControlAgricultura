<?php
$pageTitle = "Plantaciones Activas - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();

$sql = "
    SELECT p.*, f.nombre_finca, c.nombre_comun, c.nombre_cientifico, c.variedad
    FROM plantaciones p
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
";
if ($tenantId && !Auth::isAdmin()) {
    $sql .= " WHERE f.id_propietario = " . (int)$tenantId;
}
$sql .= " ORDER BY p.campana_ano DESC, f.nombre_finca ASC";

$plantaciones = $db->query($sql)->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-calendar-days text-info me-2"></i>Plantaciones y Ciclos de Cultivo</h2>
            <p class="text-muted mb-0">Control de cuadros, fechas de siembra, trasplante y seguimiento por campaña</p>
        </div>
        <div>
            <a href="<?= APP_URL ?>/plantaciones/nueva" class="btn btn-ag shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Nueva Plantación
            </a>
        </div>
    </div>

    <?php if (empty($plantaciones)): ?>
        <div class="card card-custom p-5 text-center">
            <i class="fa-solid fa-seedling fs-1 text-muted mb-3"></i>
            <h4 class="fw-bold">No hay plantaciones activas registradas</h4>
            <p class="text-muted">Asocia un cultivo botánico a una de tus fincas para iniciar su cuaderno de campo.</p>
            <div class="mt-2">
                <a href="<?= APP_URL ?>/plantaciones/nueva" class="btn btn-ag">Crear Plantación Ahora</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card card-custom p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Finca y Cuadro</th>
                            <th>Cultivo / Variedad</th>
                            <th>Campaña</th>
                            <th>Superficie / Árboles</th>
                            <th>Fechas Clave</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plantaciones as $pl): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($pl['nombre_finca']) ?></div>
                                    <span class="badge bg-light text-secondary border"><?= htmlspecialchars($pl['identificador_cuadro']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($pl['nombre_comun']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($pl['variedad'] ?: $pl['nombre_cientifico'] ?: '-') ?></div>
                                </td>
                                <td><span class="badge bg-secondary"><?= $pl['campana_ano'] ?></span></td>
                                <td>
                                    <div><strong><?= number_format($pl['superficie_ocupada_ha'], 2, ',', '.') ?> ha</strong></div>
                                    <div class="small text-muted"><?= $pl['num_arboles_o_plantas'] ? $pl['num_arboles_o_plantas'] . ' plantas' : '' ?> (<?= htmlspecialchars($pl['marco_plantacion'] ?: '-') ?>)</div>
                                </td>
                                <td class="small">
                                    <?php if ($pl['fecha_siembra']): ?>
                                        <div><strong>Siembra:</strong> <?= date('d/m/Y', strtotime($pl['fecha_siembra'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($pl['fecha_transplante']): ?>
                                        <div><strong>Trasplante:</strong> <?= date('d/m/Y', strtotime($pl['fecha_transplante'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($pl['fecha_estimada_fin']): ?>
                                        <div class="text-success"><strong>Cosecha est.:</strong> <?= date('d/m/Y', strtotime($pl['fecha_estimada_fin'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($pl['estado'] === 'produccion'): ?>
                                        <span class="badge bg-success">En Producción</span>
                                    <?php elseif ($pl['estado'] === 'en_crecimiento'): ?>
                                        <span class="badge bg-info text-dark">En Crecimiento</span>
                                    <?php elseif ($pl['estado'] === 'en_descanso'): ?>
                                        <span class="badge bg-warning text-dark">En Descanso</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark">Arrancado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= APP_URL ?>/diario?plantacion_id=<?= $pl['id_plantacion'] ?>" class="btn btn-sm btn-outline-info" title="Ver Diario de Evolución">
                                        <i class="fa-solid fa-camera me-1"></i> Diario
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
