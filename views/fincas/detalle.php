<?php
$pageTitle = "Ficha Detallada de Finca - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$idFinca = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tenantId = Auth::tenantId();

$sql = "
    SELECT f.*, p.nombre_paraje, p.termino_municipal, p.provincia, prop.nombre_razon_social AS propietario
    FROM fincas f
    LEFT JOIN parajes p ON f.id_paraje = p.id_paraje
    LEFT JOIN propietarios prop ON f.id_propietario = prop.id_propietario
    WHERE f.id_finca = :id
";
if ($tenantId && !Auth::isAdmin()) {
    $sql .= " AND f.id_propietario = " . (int)$tenantId;
}
$stmt = $db->prepare($sql);
$stmt->execute(array(':id' => $idFinca));
$finca = $stmt->fetch();

if (!$finca) {
    echo "<div class='container py-5 text-center'><div class='alert alert-warning'>Finca no encontrada o no tienes permisos para consultarla.</div><a href='" . APP_URL . "/fincas' class='btn btn-ag'>Volver a Fincas</a></div>";
    require_once VIEWS_PATH . '/layouts/footer.php';
    exit;
}

// Obtener plantaciones de esta finca
$stmtPlan = $db->prepare("
    SELECT p.*, c.nombre_comun, c.nombre_cientifico, c.variedad
    FROM plantaciones p
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE p.id_finca = :id
    ORDER BY p.campana_ano DESC, p.identificador_cuadro ASC
");
$stmtPlan->execute(array(':id' => $idFinca));
$plantaciones = $stmtPlan->fetchAll();

// Obtener últimas labores consolidadas en esta finca
$stmtTrat = $db->prepare("
    SELECT t.fecha_tratamiento AS fecha, 'Tratamiento' AS tipo_labor, t.nombre_comercial_producto AS detalle, t.dosis_por_hectarea AS extra, t.es_ecologico
    FROM tratamientos t
    JOIN plantaciones p ON t.id_plantacion = p.id_plantacion
    WHERE p.id_finca = :id
    ORDER BY t.fecha_tratamiento DESC LIMIT 5
");
$stmtTrat->execute(array(':id' => $idFinca));
$ultimosTrat = $stmtTrat->fetchAll();

$stmtRiegos = $db->prepare("
    SELECT r.fecha_riego AS fecha, 'Riego' AS tipo_labor, CONCAT(r.duracion_horas, ' horas') AS detalle, CONCAT(COALESCE(r.volumen_m3, 0), ' m³') AS extra, r.con_fertirrigacion AS es_ecologico
    FROM riegos r
    JOIN plantaciones p ON r.id_plantacion = p.id_plantacion
    WHERE p.id_finca = :id
    ORDER BY r.fecha_riego DESC LIMIT 5
");
$stmtRiegos->execute(array(':id' => $idFinca));
$ultimosRiegos = $stmtRiegos->fetchAll();
?>

<div class="container py-4">
    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <div class="small text-muted mb-1">
                <i class="fa-solid fa-building-wheat me-1"></i> <?= htmlspecialchars($finca['propietario']) ?>
                <span class="mx-2">&bull;</span>
                <i class="fa-solid fa-map-pin me-1"></i> <?= htmlspecialchars($finca['nombre_paraje'] ? $finca['nombre_paraje'] . ' (' . $finca['termino_municipal'] . ')' : 'Sin paraje') ?>
            </div>
            <h2 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-draw-polygon text-success me-2"></i><?= htmlspecialchars($finca['nombre_finca']) ?>
            </h2>
        </div>
        <?php
            $refDirectaCatastro = !empty($finca['referencia_catastral']) ? trim($finca['referencia_catastral']) : '';
            if (empty($refDirectaCatastro) && !empty($finca['poligono']) && !empty($finca['parcela'])) {
                $refDirectaCatastro = '30028A' . str_pad($finca['poligono'], 3, '0', STR_PAD_LEFT) . str_pad($finca['parcela'], 5, '0', STR_PAD_LEFT);
            }
            $urlMapaCatastro = !empty($refDirectaCatastro) ? "https://www1.sedecatastro.gob.es/Cartografia/mapa.aspx?refcat=" . urlencode($refDirectaCatastro) : "https://www1.sedecatastro.gob.es/Cartografia/mapa.aspx";
        ?>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a href="<?= APP_URL ?>/mapas?finca_id=<?= $finca['id_finca'] ?>" class="btn btn-primary fw-semibold" title="Abrir en Visor SIG y delimitar cultivos o árboles">
                <i class="fa-solid fa-map-location-dot me-1"></i> Mapa SIG Cultivos
            </a>
            <a href="<?= $urlMapaCatastro ?>" target="_blank" class="btn btn-outline-primary" title="Localización Directa en Catastro (Pol <?= htmlspecialchars($finca['poligono'] ?: '-') ?> Parc <?= htmlspecialchars($finca['parcela'] ?: '-') ?>)">
                <i class="fa-solid fa-layer-group me-1"></i> Catastro
            </a>
            <a href="https://sigpac.mapa.gob.es/fega/visor/" target="_blank" class="btn btn-outline-success" title="Visor SIGPAC Oficial (FEGA)">
                <i class="fa-solid fa-map-location-dot me-1"></i> SIGPAC (FEGA)
            </a>
            <a href="<?= APP_URL ?>/fincas" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Volver
            </a>
            <a href="<?= APP_URL ?>/plantaciones/nueva?finca_id=<?= $finca['id_finca'] ?>" class="btn btn-ag">
                <i class="fa-solid fa-plus me-1"></i> Nueva Plantación
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Columna Izquierda: Ficha Técnica y Registral -->
        <div class="col-lg-5">
            <div class="card card-custom p-4 mb-4 border-start border-4 border-success">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-landmark text-success me-2"></i>Datos Catastrales y Registrales</h5>
                <ul class="list-group list-group-flush mb-0">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Superficie Total:</span>
                        <strong class="fs-5 text-success"><?= number_format($finca['superficie_ha'], 2, ',', '.') ?> ha</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Referencia Catastral:</span>
                        <div>
                            <?php if ($finca['referencia_catastral']): ?>
                                <a href="https://www1.sedecatastro.gob.es/CYGConsultaInformacionCatastral/CYGCampanyas.aspx?refcat=<?= urlencode($finca['referencia_catastral']) ?>" target="_blank" class="fw-bold text-primary text-decoration-none">
                                    <?= htmlspecialchars($finca['referencia_catastral']) ?> <i class="fa-solid fa-arrow-up-right-from-square small"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">No asignada</span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Polígono / Parcela:</span>
                        <strong>Pol. <?= htmlspecialchars($finca['poligono'] ?: '-') ?> / Parc. <?= htmlspecialchars($finca['parcela'] ?: '-') ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Paraje / Término:</span>
                        <span><?= htmlspecialchars($finca['nombre_paraje'] ?: '-') ?> (<?= htmlspecialchars($finca['termino_municipal'] ?: '-') ?>)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Localización en Mapa:</span>
                        <div class="d-flex gap-1">
                            <a href="<?= $urlMapaCatastro ?>" target="_blank" class="badge bg-primary text-decoration-none py-1 px-2" title="Ortofoto y Delimitación Directa de la Parcela en Catastro">
                                <i class="fa-solid fa-layer-group me-1"></i>Catastro Directo
                            </a>
                            <a href="https://sigpac.mapa.gob.es/fega/visor/" target="_blank" class="badge bg-success text-decoration-none py-1 px-2" title="Visor Oficial SIGPAC (FEGA) - Prov: 30, Mun: 028, Pol: <?= htmlspecialchars($finca['poligono'] ?: '-') ?>, Parc: <?= htmlspecialchars($finca['parcela'] ?: '-') ?>)">
                                <i class="fa-solid fa-map-location-dot me-1"></i>SIGPAC
                            </a>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode(($finca['poligono'] ? 'Polígono ' . $finca['poligono'] . ' ' : '') . ($finca['parcela'] ? 'Parcela ' . $finca['parcela'] . ' ' : '') . ($finca['nombre_paraje'] ? $finca['nombre_paraje'] . ' ' : '') . ($finca['nombre_finca'] ? $finca['nombre_finca'] . ' ' : '') . 'Moratalla Murcia España') ?>" target="_blank" class="badge bg-danger text-decoration-none py-1 px-2" title="Google Maps Satélite">
                                <i class="fa-brands fa-google me-1"></i>Google
                            </a>
                        </div>
                    </li>
                    <?php 
                    $fincaDesc = !empty($finca['descripcion']) ? $finca['descripcion'] : (!empty($finca['observaciones']) ? $finca['observaciones'] : '');
                    if ($fincaDesc): 
                    ?>
                        <li class="list-group-item px-0">
                            <span class="text-muted d-block small mb-1 fw-bold"><i class="fa-solid fa-align-left text-primary me-1"></i>Descripción de la Finca:</span>
                            <div class="small bg-light p-2 rounded border text-dark"><?= nl2br(htmlspecialchars($fincaDesc)) ?></div>
                        </li>
                    <?php endif; ?>
                    <?php if ($finca['datos_registrales']): ?>
                        <li class="list-group-item px-0">
                            <span class="text-muted d-block small mb-1 fw-bold"><i class="fa-solid fa-book-bookmark text-success me-1"></i>Datos Registrales (Registro de la Propiedad):</span>
                            <div class="small bg-light p-2 rounded border"><?= nl2br(htmlspecialchars($finca['datos_registrales'])) ?></div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Datos de Riego -->
            <div class="card card-custom p-4 border-start border-4 border-primary">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-water text-primary me-2"></i>Régimen de Riego y Tanda</h5>
                <ul class="list-group list-group-flush mb-0">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Comunidad de Regantes:</span>
                        <strong><?= htmlspecialchars($finca['zona_riego'] ?: 'Comunidad General') ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Nº Socio / Comunero:</span>
                        <span class="badge bg-light text-dark border fs-6">#<?= htmlspecialchars($finca['num_socio_comunidad'] ?: '-') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Parada / Tablón:</span>
                        <strong>Parada: <?= htmlspecialchars($finca['parada'] ?: '-') ?> | Tablón: <?= htmlspecialchars($finca['tablon'] ?: '-') ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Horas en Propiedad (Tanda):</span>
                        <strong class="text-primary fs-5"><?= $finca['horas_propiedad_tanda'] ?> horas</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Agua disponible Comunitaria:</span>
                        <strong class="text-success fs-5"><?= $finca['horas_disponibles_tanda'] ?> horas</strong>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Columna Derecha: Plantaciones y Labores -->
        <div class="col-lg-7">
            <!-- Cuadros y Plantaciones -->
            <div class="card card-custom p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-seedling text-success me-2"></i>Plantaciones Activas (<?= count($plantaciones) ?>)</h5>
                    <a href="<?= APP_URL ?>/plantaciones/nueva?finca_id=<?= $finca['id_finca'] ?>" class="btn btn-sm btn-outline-success">
                        <i class="fa-solid fa-plus me-1"></i> Añadir
                    </a>
                </div>

                <?php if (empty($plantaciones)): ?>
                    <p class="text-muted text-center py-3 mb-0">No hay cultivos asignados a esta finca todavía.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cuadro</th>
                                    <th>Cultivo</th>
                                    <th>Campaña</th>
                                    <th>Superficie</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plantaciones as $p): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($p['identificador_cuadro']) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($p['nombre_comun']) ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars($p['variedad'] ?: '-') ?></div>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= $p['campana_ano'] ?></span></td>
                                        <td><?= number_format($p['superficie_ocupada_ha'], 2, ',', '.') ?> ha</td>
                                        <td>
                                            <?php if ($p['estado'] === 'produccion'): ?>
                                                <span class="badge bg-success">Producción</span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark text-capitalize"><?= str_replace('_', ' ', $p['estado']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= APP_URL ?>/diario?plantacion_id=<?= $p['id_plantacion'] ?>" class="btn btn-sm btn-outline-info" title="Ver Diario">
                                                <i class="fa-solid fa-camera"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Últimas Labores en esta Finca -->
            <div class="card card-custom p-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-clipboard-list text-warning me-2"></i>Últimos Tratamientos y Riegos</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-danger border-bottom pb-2 mb-2"><i class="fa-solid fa-spray-can me-1"></i>Tratamientos</h6>
                        <?php if (empty($ultimosTrat)): ?>
                            <p class="small text-muted">Sin registros recientes.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($ultimosTrat as $t): ?>
                                    <li class="py-2 border-bottom small">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($t['detalle']) ?></div>
                                        <div class="text-muted d-flex justify-content-between">
                                            <span><?= date('d/m/Y', strtotime($t['fecha'])) ?></span>
                                            <span><?= $t['extra'] ?> l/ha</span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <h6 class="fw-bold text-primary border-bottom pb-2 mb-2"><i class="fa-solid fa-faucet-drip me-1"></i>Riegos</h6>
                        <?php if (empty($ultimosRiegos)): ?>
                            <p class="small text-muted">Sin riegos recientes.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($ultimosRiegos as $r): ?>
                                    <li class="py-2 border-bottom small">
                                        <div class="fw-bold text-dark"><?= $r['detalle'] ?> (<?= $r['extra'] ?>)</div>
                                        <div class="text-muted d-flex justify-content-between">
                                            <span><?= date('d/m/Y', strtotime($r['fecha'])) ?></span>
                                            <span><?= $r['es_ecologico'] ? 'Con abono' : 'Agua sola' ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
