<?php
$pageTitle = "Cuaderno de Explotación e Informes - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();

$fincaSel = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$anoSel = isset($_GET['campana']) ? (int)$_GET['campana'] : (int)date('Y');

// Obtener fincas del usuario
$sqlFincas = "SELECT id_finca, nombre_finca, superficie_ha FROM fincas";
if ($tenantId && !Auth::isAdmin()) {
    $sqlFincas .= " WHERE id_propietario = " . (int)$tenantId;
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$fincas = $db->query($sqlFincas)->fetchAll();

// Propietario / Datos de la explotación
$propData = array('nombre' => 'Explotación Agrícola', 'cif' => '-', 'municipio' => '-');
if ($tenantId) {
    $pStmt = $db->prepare("SELECT * FROM propietarios WHERE id_propietario = :id");
    $pStmt->execute(array(':id' => $tenantId));
    if ($row = $pStmt->fetch()) {
        $propData['nombre'] = $row['nombre_razon_social'];
        $propData['cif'] = $row['cif_nif'];
        $propData['municipio'] = $row['municipio'] ?: $row['provincia'];
    }
}

// Obtener tratamientos para el informe
$sqlTrat = "
    SELECT t.*, f.nombre_finca, f.referencia_catastral, f.poligono, f.parcela, c.nombre_comun, p.identificador_cuadro, p.superficie_ocupada_ha
    FROM tratamientos t
    JOIN plantaciones p ON t.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE YEAR(t.fecha_tratamiento) = :ano
";
if ($fincaSel > 0) {
    $sqlTrat .= " AND f.id_finca = " . $fincaSel;
} elseif ($tenantId && !Auth::isAdmin()) {
    $sqlTrat .= " AND f.id_propietario = " . (int)$tenantId;
}
$sqlTrat .= " ORDER BY t.fecha_tratamiento ASC";

$stmtTrat = $db->prepare($sqlTrat);
$stmtTrat->execute(array(':ano' => $anoSel));
$tratamientos = $stmtTrat->fetchAll();

// Obtener cosechas para el informe
$sqlCosechas = "
    SELECT r.*, f.nombre_finca, c.nombre_comun
    FROM recolecciones r
    JOIN plantaciones p ON r.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE YEAR(r.fecha_recoleccion) = :ano
";
if ($fincaSel > 0) {
    $sqlCosechas .= " AND f.id_finca = " . $fincaSel;
} elseif ($tenantId && !Auth::isAdmin()) {
    $sqlCosechas .= " AND f.id_propietario = " . (int)$tenantId;
}
$sqlCosechas .= " ORDER BY r.fecha_recoleccion ASC";

$stmtCosechas = $db->prepare($sqlCosechas);
$stmtCosechas->execute(array(':ano' => $anoSel));
$cosechas = $stmtCosechas->fetchAll();
?>

<style>
@media print {
    nav, footer, .no-print, .btn { display: none !important; }
    body { background: #fff !important; font-size: 11pt; }
    .card-custom { box-shadow: none !important; border: 1px solid #ccc !important; }
    .container { max-width: 100% !important; width: 100% !important; padding: 0 !important; }
    table { font-size: 9.5pt !important; }
}
</style>

<div class="container py-4">
    <!-- Barra de Filtros y Acción de Impresión -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom no-print">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-file-contract text-success me-2"></i>Cuaderno de Campo Oficial</h2>
            <p class="text-muted mb-0">Generación de informes de trazabilidad fitosanitaria y recolección para auditorías</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mt-md-0">
            <?php
            $reportTitle = 'Cuaderno de Campo Oficial - Campaña ' . $anoSel;
            $reportTargetId = 'areaImprimibleCuaderno';
            $reportFilename = 'cuaderno_campo_oficial_' . $anoSel . '.pdf';
            require VIEWS_PATH . '/layouts/toolbar_informes.php';
            ?>
        </div>
    </div>

    <!-- Formulario Filtros (Solo pantalla) -->
    <div class="card card-custom p-3 mb-4 no-print bg-light border">
        <form action="<?= APP_URL ?>/cuaderno" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted mb-1">Filtrar por Finca</label>
                <select name="finca_id" class="form-select form-select-sm">
                    <option value="0">-- Todas las fincas de la explotación --</option>
                    <?php foreach ($fincas as $f): ?>
                        <option value="<?= $f['id_finca'] ?>" <?= $fincaSel === (int)$f['id_finca'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['nombre_finca']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Campaña / Año</label>
                <input type="number" name="campana" class="form-control form-control-sm" value="<?= $anoSel ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end pt-3">
                <button type="submit" class="btn btn-sm btn-ag w-100">
                    <i class="fa-solid fa-filter me-1"></i> Actualizar Informe
                </button>
            </div>
        </form>
    </div>

    <!-- DOCUMENTO OFICIAL IMPRIMIBLE -->
    <div class="card card-custom p-4 p-md-5" id="areaImprimibleCuaderno">
        <!-- Encabezado Oficial -->
        <div class="row align-items-center border-bottom pb-3 mb-4">
            <div class="col-8">
                <h3 class="fw-bold text-success mb-1">DOCUMENTO DE CONTROL Y TRAZABILIDAD AGRÍCOLA</h3>
                <h5 class="text-secondary fw-semibold mb-0">CUADERNO DE EXPLOTACIÓN - CAMPAÑA <?= $anoSel ?></h5>
            </div>
            <div class="col-4 text-end">
                <div class="fw-bold fs-5 text-dark"><?= htmlspecialchars($propData['nombre']) ?></div>
                <div class="small text-muted">NIF/CIF: <?= htmlspecialchars($propData['cif']) ?></div>
                <div class="small text-muted">Término: <?= htmlspecialchars($propData['municipio']) ?></div>
            </div>
        </div>

        <!-- Sección 1: Tratamientos Fitosanitarios -->
        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-spray-can-sparkles text-danger me-2"></i>1. Registro Oficial de Tratamientos Fitosanitarios y Ecológicos</h5>
        <?php if (empty($tratamientos)): ?>
            <div class="alert alert-light border text-center text-muted py-3">No hay tratamientos registrados para esta selección en la campaña <?= $anoSel ?>.</div>
        <?php else: ?>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light text-center small">
                        <tr>
                            <th>Fecha</th>
                            <th>Finca / Parcela</th>
                            <th>Ref. Catastral</th>
                            <th>Cultivo</th>
                            <th>Producto Comercial</th>
                            <th>Nº Registro</th>
                            <th>Materia Activa</th>
                            <th>Dosis</th>
                            <th>Plaga Objetivo</th>
                            <th>Plazo Seg.</th>
                            <th>Aplicador</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($tratamientos as $t): ?>
                            <tr>
                                <td class="text-center fw-bold"><?= date('d/m/Y', strtotime($t['fecha_tratamiento'])) ?></td>
                                <td><?= htmlspecialchars($t['nombre_finca']) ?> (<?= htmlspecialchars($t['identificador_cuadro']) ?>)</td>
                                <td class="small"><?= htmlspecialchars($t['referencia_catastral'] ?: 'Pol ' . $t['poligono'] . ' / Parc ' . $t['parcela']) ?></td>
                                <td><?= htmlspecialchars($t['nombre_comun']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($t['nombre_comercial_producto']) ?></strong>
                                    <?php if ($t['es_ecologico']): ?>
                                        <span class="badge bg-success" style="font-size:0.65rem;">ECO</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($t['num_registro_oficial'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($t['materia_activa'] ?: '-') ?></td>
                                <td class="text-center"><?= $t['dosis_por_hectarea'] ?> <?= htmlspecialchars($t['unidad_dosis']) ?></td>
                                <td><?= htmlspecialchars($t['plaga_o_enfermedad_objetivo'] ?: '-') ?></td>
                                <td class="text-center"><?= $t['plazo_seguridad_dias'] ?> días</td>
                                <td><?= htmlspecialchars($t['aplicador_responsable'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Sección 2: Cosechas y Rendimientos -->
        <h5 class="fw-bold text-dark mb-3 mt-4"><i class="fa-solid fa-basket-shopping text-success me-2"></i>2. Registro de Recolección y Comercialización</h5>
        <?php if (empty($cosechas)): ?>
            <div class="alert alert-light border text-center text-muted py-3">No hay recolecciones registradas en la campaña <?= $anoSel ?>.</div>
        <?php else: ?>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light text-center small">
                        <tr>
                            <th>Fecha</th>
                            <th>Finca</th>
                            <th>Producto Cosechado</th>
                            <th>Kilos Totales</th>
                            <th>Calidad / Categoría</th>
                            <th>Precio / Kilo</th>
                            <th>Importe Total</th>
                            <th>Albarán / Destino</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php 
                        $totalKilos = 0;
                        $totalEuros = 0;
                        foreach ($cosechas as $c): 
                            $totalKilos += (float)$c['kilos_totales'];
                            $totalEuros += (float)($c['importe_total'] ?: 0);
                        ?>
                            <tr>
                                <td class="text-center"><?= date('d/m/Y', strtotime($c['fecha_recoleccion'])) ?></td>
                                <td><?= htmlspecialchars($c['nombre_finca']) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($c['producto_cosechado']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($c['kilos_totales'], 2, ',', '.') ?> kg</td>
                                <td class="text-center text-capitalize"><?= htmlspecialchars($c['calidad']) ?></td>
                                <td class="text-end"><?= $c['precio_kilo_estimado'] !== null ? number_format($c['precio_kilo_estimado'], 3, ',', '.') . ' €' : '-' ?></td>
                                <td class="text-end fw-bold"><?= $c['importe_total'] !== null ? number_format($c['importe_total'], 2, ',', '.') . ' €' : '-' ?></td>
                                <td><?= htmlspecialchars($c['albaran_o_destino'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold small">
                        <tr>
                            <td colspan="3" class="text-end">TOTALES CAMPAÑA:</td>
                            <td class="text-end text-success fs-6"><?= number_format($totalKilos, 2, ',', '.') ?> kg</td>
                            <td colspan="2"></td>
                            <td class="text-end text-primary fs-6"><?= number_format($totalEuros, 2, ',', '.') ?> €</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>

        <!-- Pie de Firma Oficial -->
        <div class="row pt-5 mt-4 text-center">
            <div class="col-6">
                <div class="border-top pt-2 mx-auto" style="max-width: 280px;">
                    <span class="small text-muted">Firma del Titular de la Explotación</span>
                </div>
            </div>
            <div class="col-6">
                <div class="border-top pt-2 mx-auto" style="max-width: 280px;">
                    <span class="small text-muted">Firma del Asesor / Responsable Técnico</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
