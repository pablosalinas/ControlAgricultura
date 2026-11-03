<?php
$pageTitle = "Recolecciones y Rendimientos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR FORMULARIOS (GUARDAR COSECHA, BORRADO LÓGICO)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'guardar_cosecha' || isset($_POST['guardar_cosecha'])) {
        try {
            $idPlantacion = (int)$_POST['id_plantacion'];
            $fecha = $_POST['fecha_recoleccion'];
            $producto = trim($_POST['producto_cosechado']);
            $kilos = (float)$_POST['kilos_totales'];
            $calidad = $_POST['calidad'];
            $precio = !empty($_POST['precio_kilo_estimado']) ? (float)$_POST['precio_kilo_estimado'] : null;
            $importe = ($precio !== null) ? round($kilos * $precio, 2) : null;
            $albaran = trim(isset($_POST['albaran_o_destino']) ? $_POST['albaran_o_destino'] : '');
            $obs = trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : '');

            $stmt = $db->prepare("
                INSERT INTO recolecciones (id_plantacion, fecha_recoleccion, producto_cosechado, kilos_totales, calidad, precio_kilo_estimado, importe_total, albaran_o_destino, observaciones, creado_por_id, activo)
                VALUES (:plant, :fecha, :prod, :kilos, :calidad, :precio, :importe, :alb, :obs, :user, 1)
            ");
            $stmt->execute(array(
                ':plant'   => $idPlantacion,
                ':fecha'   => $fecha,
                ':prod'    => $producto,
                ':kilos'   => $kilos,
                ':calidad' => $calidad,
                ':precio'  => $precio,
                ':importe' => $importe,
                ':alb'     => $albaran,
                ':obs'     => $obs,
                ':user'    => $currentUser['id']
            ));
            $mensaje = "Cosecha y albarán de entrega guardados con éxito.";
        } catch (Exception $e) {
            $error = "Error al guardar recolección: " . $e->getMessage();
        }
    } elseif ($action === 'borrar_cosecha') {
        try {
            $idRec = (int)$_POST['id_recoleccion'];
            $db->prepare("UPDATE recolecciones SET activo = 0 WHERE id_recoleccion = :id")->execute(array(':id' => $idRec));
            $mensaje = "Registro de recolección eliminado correctamente (borrado lógico seguro).";
        } catch (Exception $e) {
            $error = "Error al eliminar cosecha: " . $e->getMessage();
        }
    }
}

// Filtros para listado e informe A4
$filtroFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$filtroCalidad = isset($_GET['calidad']) ? trim($_GET['calidad']) : '';
$filtroDesde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$filtroHasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

// Fincas de la explotación
$sqlFincas = "SELECT id_finca, nombre_finca FROM fincas WHERE activo = 1";
if ($tenantId && !$isSuperAdmin) {
    $sqlFincas .= " AND id_propietario = " . (int)$tenantId;
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$fincasList = $db->query($sqlFincas)->fetchAll();

// Plantaciones de la explotación
$sqlPlant = "
    SELECT p.id_plantacion, p.id_finca, p.identificador_cuadro, f.nombre_finca, c.nombre_comun
    FROM plantaciones p 
    JOIN fincas f ON p.id_finca = f.id_finca 
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE f.activo = 1
";
if ($tenantId && !$isSuperAdmin) {
    $sqlPlant .= " AND f.id_propietario = " . (int)$tenantId;
}
$sqlPlant .= " ORDER BY f.nombre_finca ASC, p.identificador_cuadro ASC";
$plantaciones = $db->query($sqlPlant)->fetchAll();

// Histórico de cosechas con filtros contextuales para el informe A4
$sqlCosechas = "
    SELECT r.*, f.id_finca, f.nombre_finca, c.nombre_comun, p.identificador_cuadro
    FROM recolecciones r
    JOIN plantaciones p ON r.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE (r.activo = 1 OR r.activo IS NULL)
";
$params = array();

if ($tenantId && !$isSuperAdmin) {
    $sqlCosechas .= " AND f.id_propietario = :tenant";
    $params[':tenant'] = (int)$tenantId;
}
if ($filtroFinca > 0) {
    $sqlCosechas .= " AND f.id_finca = :finca";
    $params[':finca'] = $filtroFinca;
}
if (!empty($filtroCalidad)) {
    $sqlCosechas .= " AND r.calidad = :calidad";
    $params[':calidad'] = $filtroCalidad;
}
if (!empty($filtroDesde)) {
    $sqlCosechas .= " AND r.fecha_recoleccion >= :desde";
    $params[':desde'] = $filtroDesde;
}
if (!empty($filtroHasta)) {
    $sqlCosechas .= " AND r.fecha_recoleccion <= :hasta";
    $params[':hasta'] = $filtroHasta;
}

$sqlCosechas .= " ORDER BY r.fecha_recoleccion DESC, r.id_recoleccion DESC";
$stmtC = $db->prepare($sqlCosechas);
$stmtC->execute($params);
$cosechas = $stmtC->fetchAll();

// Métricas de producción e ingresos
$totalKilos = 0;
$totalIngresos = 0;
foreach ($cosechas as $cs) {
    $totalKilos += (float)$cs['kilos_totales'];
    $totalIngresos += (float)$cs['importe_total'];
}
$precioMedioKilo = $totalKilos > 0 ? ($totalIngresos / $totalKilos) : 0;
?>

<div class="container py-4">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #16a34a);">
                <i class="fa-solid fa-basket-shopping fs-3 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Recolecciones, Cosechas y Liquidaciones</h2>
                <p class="text-muted mb-0 small">Pesadas oficiales, calidades de fruta/cosecha, albaranes de entrega a cooperativa y rendimiento económico</p>
            </div>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formCosecha">
            <i class="fa-solid fa-plus me-1"></i> Registrar Cosecha
        </button>
    </div>

    <!-- Alertas -->
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO DE NUEVA COSECHA -->
    <div class="collapse mb-4" id="formCosecha">
        <div class="card card-ag p-4 border-top border-4 border-success shadow-sm">
            <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-scale-balanced me-2"></i>Nueva Entrada de Cosecha / Recolección</h5>
            <form action="<?= APP_URL ?>/recolecciones" method="POST">
                <input type="hidden" name="action" value="guardar_cosecha">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small">Finca y Parcela / Cultivo *</label>
                        <select name="id_plantacion" id="sel_plantacion_cosecha" class="form-select" required onchange="autocompletarCultivo()">
                            <option value="">-- Seleccionar Parcela Cosechada --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>" data-cultivo="<?= htmlspecialchars($pl['nombre_comun']) ?>">
                                    <?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun'] . ' (' . $pl['identificador_cuadro'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Producto / Fruta Cosechada *</label>
                        <input type="text" name="producto_cosechado" id="producto_cosechado" class="form-control" required placeholder="Ej: Almendra Guara en cáscara, Oliva Picual...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Fecha de Cosecha *</label>
                        <input type="date" name="fecha_recoleccion" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Kilos Totales (Pesada Neta) *</label>
                        <input type="number" step="0.5" name="kilos_totales" id="kilos_totales" class="form-control font-monospace fw-bold text-success" required placeholder="Ej: 3250" oninput="calcularTotalImporte()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Clasificación / Calidad *</label>
                        <select name="calidad" class="form-select fw-semibold" required>
                            <option value="primera">⭐ Primera / Extra Comercial</option>
                            <option value="segunda">🥈 Segunda Categoría</option>
                            <option value="industria">🏭 Destino Industria / Almazara / Concentrado</option>
                            <option value="desecho">❌ Destrío / Desecho</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Precio Estimado (€ / kg)</label>
                        <input type="number" step="0.01" name="precio_kilo_estimado" id="precio_kilo" class="form-control font-monospace" placeholder="Ej: 2.15" oninput="calcularTotalImporte()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Importe Total Estimado (€)</label>
                        <input type="text" id="importe_total_vista" class="form-control font-monospace fw-bold text-primary" readonly placeholder="0.00 €">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Nº Albarán / Cooperativa / Destino</label>
                        <input type="text" name="albaran_o_destino" class="form-control" placeholder="Ej: Coop. Ntra. Sra. de la Asunción - Alb. 2026/894">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Observaciones Técnicas</label>
                        <input type="text" name="observaciones" class="form-control" placeholder="Humedad, rendimiento graso (%), merma...">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-toggle="collapse" data-bs-target="#formCosecha">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Cosecha
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FILTROS CONTEXTUALES -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm d-print-none">
        <form action="<?= APP_URL ?>/recolecciones" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Filtrar por Finca:</label>
                <select name="finca_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Todas las Fincas --</option>
                    <?php foreach ($fincasList as $fl): ?>
                        <option value="<?= $fl['id_finca'] ?>" <?= ($filtroFinca === (int)$fl['id_finca']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fl['nombre_finca']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Calidad:</label>
                <select name="calidad" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todas las calidades --</option>
                    <option value="primera" <?= $filtroCalidad === 'primera' ? 'selected' : '' ?>>Primera / Extra</option>
                    <option value="segunda" <?= $filtroCalidad === 'segunda' ? 'selected' : '' ?>>Segunda</option>
                    <option value="industria" <?= $filtroCalidad === 'industria' ? 'selected' : '' ?>>Industria</option>
                    <option value="desecho" <?= $filtroCalidad === 'desecho' ? 'selected' : '' ?>>Destrío</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Desde Fecha:</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroDesde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Hasta Fecha:</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroHasta) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
                <?php if ($filtroFinca || $filtroCalidad || $filtroDesde || $filtroHasta): ?>
                    <a href="<?= APP_URL ?>/recolecciones" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ÁREA DE INFORME OFICIAL MAQUETADA PARA A4 -->
    <div class="card card-ag shadow-sm border-0 mb-4" id="area-informe-recolecciones">
        
        <!-- Toolbar Universal de Exportación -->
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 d-print-none">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-invoice text-success fs-5"></i>
                <h6 class="fw-bold mb-0 text-primary">Informe Oficial de Producción y Cosechas (<?= count($cosechas) ?> albaranes)</h6>
            </div>
            <div>
                <?php 
                    $reportTitle = "Informe Oficial de Recolecciones y Rendimientos Agricolas";
                    $reportFileName = "informe_cosechas_" . date('Ymd_His');
                    $reportContainerId = "area-informe-recolecciones";
                    require VIEWS_PATH . '/layouts/toolbar_informes.php';
                ?>
            </div>
        </div>

        <div class="card-body p-4">
            
            <!-- Encabezado A4 Oficial -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div>
                    <h4 class="fw-bold text-primary mb-1">
                        <i class="fa-solid fa-basket-shopping me-2 text-success"></i><?= htmlspecialchars(Auth::user()['nombre_propietario'] ?: APP_NAME) ?>
                    </h4>
                    <div class="small text-muted">Cuaderno de Explotación Agraria - Balance de Producción, Pesadas y Liquidaciones</div>
                    <div class="small text-secondary">Fecha de emisión: <strong><?= date('d/m/Y H:i') ?></strong> | Responsable: <?= htmlspecialchars($currentUser['nombre_completo']) ?></div>
                </div>
                <div class="text-end">
                    <span class="badge bg-success px-3 py-2 fs-6 rounded-pill">PRODUCCIÓN Y VENTAS</span>
                    <div class="small text-muted mt-1 font-monospace">Trazabilidad de Cosecha / PAC</div>
                </div>
            </div>

            <!-- Resumen de Métricas -->
            <div class="row g-2 mb-3 text-center">
                <div class="col-4">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Producción Total Cosechada</span>
                        <strong class="fs-5 text-success font-monospace"><?= number_format($totalKilos, 1, ',', '.') ?> kg</strong>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Valor Estimado de Cosecha</span>
                        <strong class="fs-5 text-primary font-monospace"><?= number_format($totalIngresos, 2, ',', '.') ?> €</strong>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Precio Medio Ponderado</span>
                        <strong class="fs-5 text-dark font-monospace"><?= number_format($precioMedioKilo, 2, ',', '.') ?> €/kg</strong>
                    </div>
                </div>
            </div>

            <!-- Tabla de Cosechas Maquetada para A4 -->
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light small text-uppercase fw-bold text-secondary">
                        <tr>
                            <th>Fecha</th>
                            <th>Finca / Parcela</th>
                            <th>Producto Cosechado</th>
                            <th class="text-center">Kilos Netos</th>
                            <th>Calidad</th>
                            <th class="text-center">Precio/kg</th>
                            <th class="text-center">Importe Total</th>
                            <th>Albarán / Destino</th>
                            <th class="d-print-none text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cosechas)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-basket-shopping fa-2x mb-2 text-secondary opacity-50"></i>
                                    <p class="mb-0">No se encontraron cosechas registradas con los filtros indicados.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cosechas as $c): ?>
                                <tr>
                                    <td class="font-monospace fw-semibold"><?= date('d/m/Y', strtotime($c['fecha_recoleccion'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($c['nombre_finca']) ?></strong>
                                        <div class="small text-muted font-monospace"><?= htmlspecialchars($c['identificador_cuadro']) ?></div>
                                    </td>
                                    <td><strong><?= htmlspecialchars($c['producto_cosechado']) ?></strong></td>
                                    <td class="text-center font-monospace fw-bold text-success">
                                        <?= number_format($c['kilos_totales'], 1, ',', '.') ?> kg
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-capitalize">
                                            <?= htmlspecialchars($c['calidad']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center font-monospace">
                                        <?= $c['precio_kilo_estimado'] !== null ? number_format($c['precio_kilo_estimado'], 2, ',', '.') . ' €' : '-' ?>
                                    </td>
                                    <td class="text-center font-monospace fw-bold text-primary">
                                        <?= $c['importe_total'] !== null ? number_format($c['importe_total'], 2, ',', '.') . ' €' : '-' ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($c['albaran_o_destino'] ?: '-') ?></td>
                                    <td class="d-print-none text-end">
                                        <form action="<?= APP_URL ?>/recolecciones" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este registro de cosecha?');">
                                            <input type="hidden" name="action" value="borrar_cosecha">
                                            <input type="hidden" name="id_recoleccion" value="<?= $c['id_recoleccion'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Eliminar (Borrado Lógico)">
                                                <i class="fa-solid fa-trash-can" style="font-size:0.75rem;"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-3 border-top text-muted small d-flex justify-content-between align-items-center">
                <span>Dietario Agrícola Digital - Control de Rendimientos</span>
                <span>Documento oficial de trazabilidad de cosecha y balance agronómico</span>
            </div>
        </div>
    </div>
</div>

<script>
function autocompletarCultivo() {
    const sel = document.getElementById('sel_plantacion_cosecha');
    const opt = sel.options[sel.selectedIndex];
    const txtProd = document.getElementById('producto_cosechado');
    if (opt && opt.getAttribute('data-cultivo') && !txtProd.value) {
        txtProd.value = opt.getAttribute('data-cultivo');
    }
}

function calcularTotalImporte() {
    const kg = parseFloat(document.getElementById('kilos_totales').value) || 0;
    const precio = parseFloat(document.getElementById('precio_kilo').value) || 0;
    const vista = document.getElementById('importe_total_vista');
    if (kg > 0 && precio > 0) {
        const total = (kg * precio).toFixed(2);
        vista.value = total + ' €';
    } else {
        vista.value = '0.00 €';
    }
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
