<?php
$pageTitle = "Control de Gastos e Ingresos por Finca - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// Cargar fincas de la explotación activa
$sqlFincas = "SELECT id_finca, nombre_finca, superficie_ha, poligono, parcela FROM fincas WHERE activo = 1";
$paramsF = array();
if ($tenantId) {
    $sqlFincas .= " AND (id_propietario = :prop OR id_propietario IS NULL)";
    $paramsF[':prop'] = $tenantId;
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$stmtF = $db->prepare($sqlFincas);
$stmtF->execute($paramsF);
$fincas = $stmtF->fetchAll();

// Cargar equivalencia de Fanegas por Hectárea desde configuración (por defecto 3.00)
$cfgFan = $db->query("SELECT fanegas_por_hectarea FROM configuracion_web LIMIT 1")->fetch();
$fanegasPorHa = (!empty($cfgFan['fanegas_por_hectarea'])) ? (float)$cfgFan['fanegas_por_hectarea'] : 3.0000;
if ($fanegasPorHa <= 0) {
    $fanegasPorHa = 3.0000;
}

// Cargar tipos de gastos e ingresos activos
$sqlTipos = "
    SELECT id_tipo, nombre_tipo, naturaleza, color 
    FROM tipos_gastos_ingresos 
    WHERE activo = 1 AND (id_propietario = :prop OR id_propietario IS NULL)
    ORDER BY naturaleza ASC, nombre_tipo ASC
";
$stmtT = $db->prepare($sqlTipos);
$stmtT->execute(array(':prop' => $tenantId));
$tipos = $stmtT->fetchAll();

$tiposGastos = array();
$tiposIngresos = array();
foreach ($tipos as $tp) {
    if ($tp['naturaleza'] === 'gasto' || $tp['naturaleza'] === 'ambos') {
        $tiposGastos[] = $tp;
    }
    if ($tp['naturaleza'] === 'ingreso' || $tp['naturaleza'] === 'ambos') {
        $tiposIngresos[] = $tp;
    }
}

// PROCESAR PETICIONES CRUD Y CONMUTACIÓN DE CONFIRMACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // CONMUTAR CHECK DE CONFIRMACIÓN (Vía AJAX o normal)
    if ($action === 'toggle_confirmado') {
        $idGasto = (int)(isset($_POST['id_gasto']) ? $_POST['id_gasto'] : 0);
        $isAjax = !empty($_POST['is_ajax']);
        try {
            $stmtGet = $db->prepare("SELECT confirmado, concepto FROM gastos_explotacion WHERE id_gasto = :id");
            $stmtGet->execute(array(':id' => $idGasto));
            $rowGasto = $stmtGet->fetch();

            if ($rowGasto) {
                $nuevoEstado = ((int)$rowGasto['confirmado'] === 1) ? 0 : 1;
                $upd = $db->prepare("UPDATE gastos_explotacion SET confirmado = :conf, actualizado_el = NOW() WHERE id_gasto = :id");
                $upd->execute(array(':conf' => $nuevoEstado, ':id' => $idGasto));

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(array(
                        'success' => true,
                        'nuevo_estado' => $nuevoEstado,
                        'id_gasto' => $idGasto,
                        'message' => $nuevoEstado ? 'Gasto marcado como CONFIRMADO (se tiene en cuenta en el balance).' : 'Gasto marcado como PENDIENTE (no se computa en el balance).'
                    ));
                    exit;
                }
                $mensaje = "Estado de confirmación actualizado para: <strong>" . htmlspecialchars($rowGasto['concepto']) . "</strong>.";
            }
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(array('success' => false, 'message' => $e->getMessage()));
                exit;
            }
            $error = "Error al actualizar estado: " . $e->getMessage();
        }
    }

    // REGISTRAR NUEVO GASTO O INGRESO
    elseif ($action === 'crear_gasto') {
        $idFinca = !empty($_POST['id_finca']) ? (int)$_POST['id_finca'] : null;
        $idTipo = !empty($_POST['id_tipo']) ? (int)$_POST['id_tipo'] : null;
        $tipoMov = in_array(isset($_POST['tipo_movimiento']) ? $_POST['tipo_movimiento'] : '', array('gasto', 'ingreso')) ? $_POST['tipo_movimiento'] : 'gasto';
        $fecha = !empty($_POST['fecha_gasto']) ? $_POST['fecha_gasto'] : date('Y-m-d');
        $concepto = trim(isset($_POST['concepto']) ? $_POST['concepto'] : '');
        $importe = (float)(isset($_POST['importe']) ? str_replace(',', '.', $_POST['importe']) : 0);
        $proveedor = trim(isset($_POST['proveedor_acreedor']) ? $_POST['proveedor_acreedor'] : '');
        $numFactura = trim(isset($_POST['num_factura']) ? $_POST['num_factura'] : '');
        $confirmado = !empty($_POST['confirmado']) ? 1 : 0;
        $notas = trim(isset($_POST['notas']) ? $_POST['notas'] : '');

        // Propietario de la explotación
        $propId = $tenantId;
        if (!$propId) {
            $primerP = $db->query("SELECT id_propietario FROM propietarios WHERE activo = 1 ORDER BY id_propietario ASC LIMIT 1")->fetchColumn();
            $propId = $primerP ? (int)$primerP : 1;
        }

        if (empty($concepto) || $importe <= 0) {
            $error = "El concepto y un importe mayor que cero son obligatorios.";
        } else {
            try {
                // Manejo de archivo adjunto (factura/ticket)
                $rutaAdjunto = null;
                if (isset($_FILES['archivo_adjunto']) && $_FILES['archivo_adjunto']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['archivo_adjunto']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, array('pdf', 'jpg', 'jpeg', 'png', 'webp'))) {
                        $nomDoc = 'gasto_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $dirUpload = ROOT_PATH . '/uploads/facturas/';
                        @mkdir($dirUpload, 0777, true);
                        if (move_uploaded_file($_FILES['archivo_adjunto']['tmp_name'], $dirUpload . $nomDoc)) {
                            $rutaAdjunto = 'uploads/facturas/' . $nomDoc;
                        }
                    }
                }

                $stmtIns = $db->prepare("
                    INSERT INTO gastos_explotacion (
                        id_propietario, id_finca, id_tipo, tipo_movimiento, fecha_gasto,
                        concepto, importe, proveedor_acreedor, num_factura, confirmado,
                        notas, archivo_adjunto, activo, creado_por
                    ) VALUES (
                        :prop, :finca, :tipo, :mov, :fecha,
                        :concepto, :importe, :prov, :fac, :conf,
                        :notas, :adj, 1, :user
                    )
                ");
                $stmtIns->execute(array(
                    ':prop'     => $propId,
                    ':finca'    => $idFinca,
                    ':tipo'     => $idTipo,
                    ':mov'      => $tipoMov,
                    ':fecha'    => $fecha,
                    ':concepto' => $concepto,
                    ':importe'  => $importe,
                    ':prov'     => $proveedor ?: null,
                    ':fac'      => $numFactura ?: null,
                    ':conf'     => $confirmado,
                    ':notas'    => $notas ?: null,
                    ':adj'      => $rutaAdjunto,
                    ':user'     => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null
                ));

                $mensaje = "Registro de <strong>" . htmlspecialchars($concepto) . "</strong> (" . number_format($importe, 2, ',', '.') . " €) guardado correctamente.";
            } catch (Exception $e) {
                $error = "Error al guardar el gasto: " . $e->getMessage();
            }
        }
    }

    // MODIFICAR GASTO O INGRESO
    elseif ($action === 'editar_gasto') {
        $idGasto = (int)(isset($_POST['id_gasto']) ? $_POST['id_gasto'] : 0);
        $idFinca = !empty($_POST['id_finca']) ? (int)$_POST['id_finca'] : null;
        $idTipo = !empty($_POST['id_tipo']) ? (int)$_POST['id_tipo'] : null;
        $tipoMov = in_array(isset($_POST['tipo_movimiento']) ? $_POST['tipo_movimiento'] : '', array('gasto', 'ingreso')) ? $_POST['tipo_movimiento'] : 'gasto';
        $fecha = !empty($_POST['fecha_gasto']) ? $_POST['fecha_gasto'] : date('Y-m-d');
        $concepto = trim(isset($_POST['concepto']) ? $_POST['concepto'] : '');
        $importe = (float)(isset($_POST['importe']) ? str_replace(',', '.', $_POST['importe']) : 0);
        $proveedor = trim(isset($_POST['proveedor_acreedor']) ? $_POST['proveedor_acreedor'] : '');
        $numFactura = trim(isset($_POST['num_factura']) ? $_POST['num_factura'] : '');
        $confirmado = !empty($_POST['confirmado']) ? 1 : 0;
        $notas = trim(isset($_POST['notas']) ? $_POST['notas'] : '');

        if (empty($concepto) || $importe <= 0 || $idGasto <= 0) {
            $error = "El concepto y un importe mayor que cero son obligatorios.";
        } else {
            try {
                $stmtUpd = $db->prepare("
                    UPDATE gastos_explotacion SET
                        id_finca = :finca,
                        id_tipo = :tipo,
                        tipo_movimiento = :mov,
                        fecha_gasto = :fecha,
                        concepto = :concepto,
                        importe = :importe,
                        proveedor_acreedor = :prov,
                        num_factura = :fac,
                        confirmado = :conf,
                        notas = :notas,
                        actualizado_el = NOW()
                    WHERE id_gasto = :id
                ");
                $stmtUpd->execute(array(
                    ':finca'    => $idFinca,
                    ':tipo'     => $idTipo,
                    ':mov'      => $tipoMov,
                    ':fecha'    => $fecha,
                    ':concepto' => $concepto,
                    ':importe'  => $importe,
                    ':prov'     => $proveedor ?: null,
                    ':fac'      => $numFactura ?: null,
                    ':conf'     => $confirmado,
                    ':notas'    => $notas ?: null,
                    ':id'       => $idGasto
                ));

                $mensaje = "Gasto <strong>" . htmlspecialchars($concepto) . "</strong> actualizado correctamente.";
            } catch (Exception $e) {
                $error = "Error al actualizar gasto: " . $e->getMessage();
            }
        }
    }

    // ELIMINAR GASTO (BORRADO LÓGICO)
    elseif ($action === 'borrar_gasto') {
        $idGasto = (int)(isset($_POST['id_gasto']) ? $_POST['id_gasto'] : 0);
        try {
            $stmtDel = $db->prepare("UPDATE gastos_explotacion SET activo = 0, actualizado_el = NOW() WHERE id_gasto = :id");
            $stmtDel->execute(array(':id' => $idGasto));
            $mensaje = "Registro eliminado del balance.";
        } catch (Exception $e) {
            $error = "Error al eliminar registro: " . $e->getMessage();
        }
    }
}

// Filtros de visualización
$filtroFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$filtroTipo = isset($_GET['tipo_id']) ? (int)$_GET['tipo_id'] : 0;
$filtroMov = isset($_GET['mov']) ? $_GET['mov'] : '';
$filtroConf = isset($_GET['conf']) ? $_GET['conf'] : ''; // '1' = confirmados, '0' = pendientes, '' = todos
$filtroAno = isset($_GET['ano']) ? (int)$_GET['ano'] : 0;
$filtroTxt = isset($_GET['q']) ? trim($_GET['q']) : '';

$sqlGastos = "
    SELECT g.*, f.nombre_finca, f.superficie_ha, t.nombre_tipo, t.color AS tipo_color
    FROM gastos_explotacion g
    LEFT JOIN fincas f ON g.id_finca = f.id_finca
    LEFT JOIN tipos_gastos_ingresos t ON g.id_tipo = t.id_tipo
    WHERE g.activo = 1
";
$paramsG = array();

if ($tenantId) {
    $sqlGastos .= " AND g.id_propietario = :prop";
    $paramsG[':prop'] = $tenantId;
}

if ($filtroFinca > 0) {
    $sqlGastos .= " AND g.id_finca = :finca";
    $paramsG[':finca'] = $filtroFinca;
} elseif ($filtroFinca === -1) {
    // Solo gastos generales sin finca específica
    $sqlGastos .= " AND g.id_finca IS NULL";
}

if ($filtroTipo > 0) {
    $sqlGastos .= " AND g.id_tipo = :tipo";
    $paramsG[':tipo'] = $filtroTipo;
}

if (!empty($filtroMov)) {
    $sqlGastos .= " AND g.tipo_movimiento = :mov";
    $paramsG[':mov'] = $filtroMov;
}

if ($filtroConf === '1') {
    $sqlGastos .= " AND g.confirmado = 1";
} elseif ($filtroConf === '0') {
    $sqlGastos .= " AND g.confirmado = 0";
}

if ($filtroAno > 0) {
    $sqlGastos .= " AND YEAR(g.fecha_gasto) = :ano";
    $paramsG[':ano'] = $filtroAno;
}

if (!empty($filtroTxt)) {
    $sqlGastos .= " AND (g.concepto LIKE :txt OR g.proveedor_acreedor LIKE :txt OR g.num_factura LIKE :txt)";
    $paramsG[':txt'] = '%' . $filtroTxt . '%';
}

$sqlGastos .= " ORDER BY g.fecha_gasto DESC, g.id_gasto DESC";
$stmtG = $db->prepare($sqlGastos);
$stmtG->execute($paramsG);
$gastos = $stmtG->fetchAll();

// CÁLCULO DE TOTALES Y MÉTRICAS
$totalGastosConfirmados = 0;
$totalGastosPendientes = 0;
$totalIngresosConfirmados = 0;
$totalIngresosPendientes = 0;

foreach ($gastos as $item) {
    $val = (float)$item['importe'];
    $esConf = ((int)$item['confirmado'] === 1);
    if ($item['tipo_movimiento'] === 'gasto') {
        if ($esConf) {
            $totalGastosConfirmados += $val;
        } else {
            $totalGastosPendientes += $val;
        }
    } else {
        if ($esConf) {
            $totalIngresosConfirmados += $val;
        } else {
            $totalIngresosPendientes += $val;
        }
    }
}
$balanceNetoConfirmado = $totalIngresosConfirmados - $totalGastosConfirmados;

// Años disponibles en base de datos para el filtro
$anosQuery = $db->query("SELECT DISTINCT YEAR(fecha_gasto) AS ano FROM gastos_explotacion WHERE activo = 1 ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($anosQuery)) {
    $anosQuery = array(date('Y'));
}
?>

<div class="container py-4">

    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #b45309);">
                <i class="fa-solid fa-coins fs-3 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Control de Gastos e Imputación por Finca</h2>
                <p class="text-muted mb-0 small">Seguimiento de costes agronómicos, facturas, insumos y cálculo de balance económico con check de confirmación</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/gastos/tipos" class="btn btn-outline-secondary">
                <i class="fa-solid fa-tags me-1 text-warning"></i> Maestro de Tipos
            </a>
            <button type="button" class="btn btn-ag shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoGasto">
                <i class="fa-solid fa-circle-plus me-1"></i> Añadir Gasto a Finca
            </button>
        </div>
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
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tarjetas de Resumen Económico -->
    <div class="row g-3 mb-4">
        <!-- Gastos Confirmados -->
        <div class="col-md-3">
            <div class="card card-custom p-3 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Gastos Confirmados (Oficial)</div>
                        <div class="fs-4 fw-bold text-danger"><?= number_format($totalGastosConfirmados, 2, ',', '.') ?> €</div>
                        <div class="small text-muted">Se computan en el balance</div>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                        <i class="fa-solid fa-circle-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gastos Pendientes de Confirmar -->
        <div class="col-md-3">
            <div class="card card-custom p-3 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Gastos Pendientes Check</div>
                        <div class="fs-4 fw-bold text-warning"><?= number_format($totalGastosPendientes, 2, ',', '.') ?> €</div>
                        <div class="small text-muted">Borrador / No computan</div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                        <i class="fa-solid fa-clock-rotate-left fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ingresos Confirmados -->
        <div class="col-md-3">
            <div class="card card-custom p-3 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Ingresos Confirmados</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format($totalIngresosConfirmados, 2, ',', '.') ?> €</div>
                        <div class="small text-muted">Ventas / Subvenciones</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="fa-solid fa-arrow-trend-up fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Neto Confirmado -->
        <div class="col-md-3">
            <div class="card card-custom p-3 border-start border-4 <?= ($balanceNetoConfirmado >= 0) ? 'border-primary' : 'border-danger' ?>">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Balance Neto Confirmado</div>
                        <div class="fs-4 fw-bold <?= ($balanceNetoConfirmado >= 0) ? 'text-primary' : 'text-danger' ?>">
                            <?= ($balanceNetoConfirmado >= 0 ? '+' : '') . number_format($balanceNetoConfirmado, 2, ',', '.') ?> €
                        </div>
                        <div class="small text-muted">Ingresos - Gastos verificados</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="fa-solid fa-scale-balanced fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm">
        <form action="<?= APP_URL ?>/gastos" method="GET" class="row g-2 align-items-center">
            <!-- Filtro Finca -->
            <div class="col-md-3">
                <select name="finca_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Todas las fincas --</option>
                    <option value="-1" <?= ($filtroFinca === -1) ? 'selected' : '' ?>>-- Gasto General Explotación --</option>
                    <?php foreach ($fincas as $fin): ?>
                        <option value="<?= $fin['id_finca'] ?>" <?= ($filtroFinca === (int)$fin['id_finca']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fin['nombre_finca']) ?> (<?= number_format($fin['superficie_ha'], 2, ',', '.') ?> ha)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtro Tipo de Gasto -->
            <div class="col-md-3">
                <select name="tipo_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Todos los tipos de gasto --</option>
                    <?php foreach ($tipos as $tp): ?>
                        <option value="<?= $tp['id_tipo'] ?>" <?= ($filtroTipo === (int)$tp['id_tipo']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tp['nombre_tipo']) ?> (<?= ucfirst($tp['naturaleza']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtro Check de Confirmación -->
            <div class="col-md-2">
                <select name="conf" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los checks --</option>
                    <option value="1" <?= ($filtroConf === '1') ? 'selected' : '' ?>>Solo Confirmados</option>
                    <option value="0" <?= ($filtroConf === '0') ? 'selected' : '' ?>>Solo Pendientes</option>
                </select>
            </div>

            <!-- Filtro Año -->
            <div class="col-md-1">
                <select name="ano" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Año</option>
                    <?php foreach ($anosQuery as $a): ?>
                        <option value="<?= $a ?>" <?= ($filtroAno === (int)$a) ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtro Búsqueda Texto -->
            <div class="col-md-3 d-flex gap-1">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Concepto, proveedor, factura..." value="<?= htmlspecialchars($filtroTxt) ?>">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                <?php if ($filtroFinca || $filtroTipo || $filtroConf !== '' || $filtroAno || $filtroTxt): ?>
                    <a href="<?= APP_URL ?>/gastos" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla Maestra de Gastos e Ingresos -->
    <div class="card card-custom shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-receipt me-2"></i>Movimientos Registrados (<?= count($gastos) ?>)
            </h6>
            <div class="small text-muted">
                <i class="fa-solid fa-circle-info text-info me-1"></i>Pulsa en la casilla de check para confirmar o pausar el gasto en el balance al instante.
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Finca Imputada</th>
                        <th>Tipo / Concepto</th>
                        <th>Proveedor / Factura</th>
                        <th class="text-end">Importe</th>
                        <th class="text-center" title="Define si se tiene en cuenta en el balance oficial">Tener en cuenta (Check)</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gastos)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-file-invoice-dollar fa-2x mb-3 text-secondary"></i>
                                <p class="mb-0">No se encontraron gastos o ingresos registrados con los filtros seleccionados.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($gastos as $g): ?>
                            <tr class="<?= ((int)$g['confirmado'] === 1) ? '' : 'table-light text-muted' ?>">
                                <!-- Fecha -->
                                <td class="ps-4 font-monospace small">
                                    <i class="fa-regular fa-calendar me-1 text-primary"></i><?= date('d/m/Y', strtotime($g['fecha_gasto'])) ?>
                                </td>

                                <!-- Finca -->
                                <td>
                                    <?php if (!empty($g['nombre_finca'])): ?>
                                        <a href="<?= APP_URL ?>/fincas?q=<?= urlencode($g['nombre_finca']) ?>" class="fw-bold text-dark text-decoration-none">
                                            <i class="fa-solid fa-draw-polygon text-success me-1"></i><?= htmlspecialchars($g['nombre_finca']) ?>
                                        </a>
                                        <?php if (!empty($g['superficie_ha'])): ?>
                                            <div class="small text-muted">
                                                <?= number_format($g['superficie_ha'], 2, ',', '.') ?> ha 
                                                <span class="ms-1">(<?= number_format($g['superficie_ha'] * $fanegasPorHa, 2, ',', '.') ?> fanegas)</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                            <i class="fa-solid fa-building-wheat me-1"></i>General Explotación
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Tipo / Concepto -->
                                <td>
                                    <div class="fw-bold text-dark">
                                        <?= htmlspecialchars($g['concepto']) ?>
                                    </div>
                                    <?php if (!empty($g['nombre_tipo'])): ?>
                                        <span class="badge rounded-pill text-white mt-1" style="background-color: <?= htmlspecialchars($g['tipo_color'] ?: '#0284c7') ?>; font-size: 0.75rem;">
                                            <?= htmlspecialchars($g['nombre_tipo']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($g['notas'])): ?>
                                        <div class="small text-muted text-truncate mt-1" style="max-width: 280px;" title="<?= htmlspecialchars($g['notas']) ?>">
                                            <i class="fa-regular fa-note-sticky me-1"></i><?= htmlspecialchars($g['notas']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Proveedor / Factura -->
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <?= htmlspecialchars($g['proveedor_acreedor'] ?: '-') ?>
                                    </div>
                                    <?php if (!empty($g['num_factura'])): ?>
                                        <div class="small text-muted font-monospace">
                                            <i class="fa-solid fa-receipt me-1"></i>Doc: <?= htmlspecialchars($g['num_factura']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($g['archivo_adjunto'])): ?>
                                        <a href="<?= APP_URL . '/' . htmlspecialchars($g['archivo_adjunto']) ?>" target="_blank" class="badge bg-light text-primary border text-decoration-none mt-1">
                                            <i class="fa-solid fa-paperclip me-1"></i>Ver Justificante
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <!-- Importe -->
                                <td class="text-end font-monospace fw-bold fs-6 <?= ($g['tipo_movimiento'] === 'ingreso') ? 'text-success' : 'text-danger' ?>">
                                    <?= ($g['tipo_movimiento'] === 'ingreso' ? '+' : '-') . number_format($g['importe'], 2, ',', '.') ?> €
                                </td>

                                <!-- Check de Confirmación (Tener en cuenta en balance) -->
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-sm btn-toggle-check <?= ((int)$g['confirmado'] === 1) ? 'btn-success text-white' : 'btn-outline-warning text-dark' ?> shadow-sm px-3 py-1 rounded-pill" 
                                            data-id="<?= $g['id_gasto'] ?>" 
                                            id="btn_conf_<?= $g['id_gasto'] ?>" 
                                            onclick="toggleConfirmadoGasto(<?= $g['id_gasto'] ?>)" 
                                            title="Haz clic para conmutar si se tiene en cuenta en el balance">
                                        <?php if ((int)$g['confirmado'] === 1): ?>
                                            <i class="fa-solid fa-circle-check me-1"></i>Confirmado
                                        <?php else: ?>
                                            <i class="fa-regular fa-clock me-1"></i>Pendiente Check
                                        <?php endif; ?>
                                    </button>
                                </td>

                                <!-- Acciones -->
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarGasto_<?= $g['id_gasto'] ?>" title="Editar">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form action="<?= APP_URL ?>/gastos" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este apunte de gasto?');">
                                            <input type="hidden" name="action" value="borrar_gasto">
                                            <input type="hidden" name="id_gasto" value="<?= $g['id_gasto'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Editar Gasto -->
                                    <div class="modal fade text-start" id="modalEditarGasto_<?= $g['id_gasto'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Gasto / Ingreso
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="<?= APP_URL ?>/gastos" method="POST">
                                                    <input type="hidden" name="action" value="editar_gasto">
                                                    <input type="hidden" name="id_gasto" value="<?= $g['id_gasto'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Tipo de Movimiento</label>
                                                                <select name="tipo_movimiento" class="form-select">
                                                                    <option value="gasto" <?= ($g['tipo_movimiento'] === 'gasto') ? 'selected' : '' ?>>Gasto / Salida de Dinero</option>
                                                                    <option value="ingreso" <?= ($g['tipo_movimiento'] === 'ingreso') ? 'selected' : '' ?>>Ingreso / Venta / PAC</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label fw-semibold small">Finca Imputada</label>
                                                                <select name="id_finca" class="form-select">
                                                                    <option value="">-- Gasto General Explotación --</option>
                                                                    <?php foreach ($fincas as $fin): ?>
                                                                        <option value="<?= $fin['id_finca'] ?>" <?= ((int)$g['id_finca'] === (int)$fin['id_finca']) ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($fin['nombre_finca']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Fecha del Gasto *</label>
                                                                <input type="date" name="fecha_gasto" class="form-control" value="<?= $g['fecha_gasto'] ?>" required>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Tipo de Concepto Económico</label>
                                                                <select name="id_tipo" class="form-select">
                                                                    <option value="">-- Sin Clasificar --</option>
                                                                    <?php foreach ($tipos as $tp): ?>
                                                                        <option value="<?= $tp['id_tipo'] ?>" <?= ((int)$g['id_tipo'] === (int)$tp['id_tipo']) ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($tp['nombre_tipo']) ?> (<?= ucfirst($tp['naturaleza']) ?>)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Importe (€) *</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.01" min="0.01" name="importe" class="form-control font-monospace fs-5 fw-bold" value="<?= $g['importe'] ?>" required>
                                                                    <span class="input-group-text fw-bold">€</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Concepto / Descripción del Gasto *</label>
                                                            <input type="text" name="concepto" class="form-control" value="<?= htmlspecialchars($g['concepto']) ?>" required>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Proveedor / Acreedor / Empresa</label>
                                                                <input type="text" name="proveedor_acreedor" class="form-control" value="<?= htmlspecialchars($g['proveedor_acreedor'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Nº Factura o Ticket</label>
                                                                <input type="text" name="num_factura" class="form-control font-monospace" value="<?= htmlspecialchars($g['num_factura'] ?: '') ?>">
                                                            </div>
                                                        </div>

                                                        <!-- Checkbox de Confirmación -->
                                                        <div class="p-3 bg-light rounded-3 border mb-3">
                                                            <div class="form-check form-switch fs-5">
                                                                <input class="form-check-input" type="checkbox" id="conf_edit_<?= $g['id_gasto'] ?>" name="confirmado" value="1" <?= ((int)$g['confirmado'] === 1) ? 'checked' : '' ?>>
                                                                <label class="form-check-label fs-6 fw-bold text-dark" for="conf_edit_<?= $g['id_gasto'] ?>">
                                                                    <i class="fa-solid fa-circle-check text-success me-1"></i>Gasto confirmado y validado para tener en cuenta en balance
                                                                </label>
                                                            </div>
                                                            <div class="form-text small ms-4">
                                                                Si está desmarcado, se guardará como borrador y no sumará a los costes oficiales de la finca.
                                                            </div>
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold small">Notas / Observaciones</label>
                                                            <textarea name="notas" class="form-control" rows="2"><?= htmlspecialchars($g['notas'] ?: '') ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-ag fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Cambios</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Registrar Nuevo Gasto con Creación al Vuelo de Tipos -->
<div class="modal fade text-start" id="modalNuevoGasto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #b45309);">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-receipt me-2"></i> Añadir Gasto o Ingreso a Finca
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= APP_URL ?>/gastos" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="crear_gasto">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Naturaleza del Movimiento</label>
                            <select name="tipo_movimiento" class="form-select" id="new_tipo_mov">
                                <option value="gasto" selected>Gasto / Coste Agrícola</option>
                                <option value="ingreso">Ingreso / Venta Cosecha / PAC</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Finca Imputada *</label>
                            <select name="id_finca" class="form-select">
                                <option value="">-- Gasto General Explotación --</option>
                                <?php foreach ($fincas as $fin): ?>
                                    <option value="<?= $fin['id_finca'] ?>" <?= ($filtroFinca === (int)$fin['id_finca']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($fin['nombre_finca']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Fecha del Gasto *</label>
                            <input type="date" name="fecha_gasto" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Tipo de Gasto con Alimentador al Vuelo y Detección de Duplicados -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold small mb-0">Tipo de Gasto / Concepto Maestro</label>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold" id="btnToggleNuevoTipo" onclick="toggleCajaNuevoTipo()">
                                <i class="fa-solid fa-plus-circle me-1"></i>+ Crear Tipo al Vuelo
                            </button>
                        </div>
                        <select name="id_tipo" class="form-select" id="select_id_tipo">
                            <option value="">-- Seleccionar Tipo de Gasto / Ingreso --</option>
                            <optgroup label="Tipos de Gastos" id="optgroup_gastos">
                                <?php foreach ($tiposGastos as $tg): ?>
                                    <option value="<?= $tg['id_tipo'] ?>" data-nombre="<?= htmlspecialchars(strtolower($tg['nombre_tipo'])) ?>">
                                        <?= htmlspecialchars($tg['nombre_tipo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Tipos de Ingresos" id="optgroup_ingresos">
                                <?php foreach ($tiposIngresos as $ti): ?>
                                    <option value="<?= $ti['id_tipo'] ?>" data-nombre="<?= htmlspecialchars(strtolower($ti['nombre_tipo'])) ?>">
                                        <?= htmlspecialchars($ti['nombre_tipo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <div class="form-text small">
                            Los tipos ya introducidos aparecen en la lista. Si necesitas uno nuevo, puedes crearlo directamente sin salir.
                        </div>

                        <!-- Caja Desplegable para Crear Tipo Sobre la Marcha -->
                        <div id="caja_nuevo_tipo" class="p-3 mt-2 bg-light rounded-3 border d-none">
                            <h6 class="fw-bold text-primary mb-2">
                                <i class="fa-solid fa-wand-magic-sparkles me-1 text-warning"></i>Crear Nuevo Tipo Sobre la Marcha
                            </h6>
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-md-6">
                                    <input type="text" id="nuevo_tipo_nombre" class="form-control form-control-sm" placeholder="Nombre (ej. Análisis de suelos)" oninput="comprobarTipoDuplicado()">
                                </div>
                                <div class="col-md-3">
                                    <select id="nuevo_tipo_nat" class="form-select form-select-sm">
                                        <option value="gasto">Gasto</option>
                                        <option value="ingreso">Ingreso</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-success fw-bold flex-grow-1" onclick="guardarTipoAlVuelo()">
                                        <i class="fa-solid fa-floppy-disk me-1"></i>Guardar y Usar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleCajaNuevoTipo()">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="alerta_tipo_duplicado" class="small text-danger d-none">
                                <i class="fa-solid fa-circle-exclamation me-1"></i>¡Atención! Ya existe un tipo similar con este nombre. Se seleccionará automáticamente.
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Concepto o Detalle *</label>
                            <input type="text" name="concepto" class="form-control" placeholder="Ej: Compra abono 15-15-15 (20 sacos de 25kg)" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Importe Total (€) *</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" name="importe" class="form-control font-monospace fs-5 fw-bold text-primary" placeholder="0.00" required>
                                <span class="input-group-text fw-bold">€</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Proveedor / Facturador / Cuadrilla</label>
                            <input type="text" name="proveedor_acreedor" class="form-control" placeholder="Ej: Suministros Agrícolas Moratalla S.L.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nº Factura, Albarán o Ticket</label>
                            <input type="text" name="num_factura" class="form-control font-monospace" placeholder="Ej: FAC-2026/0412">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Adjuntar Factura o Ticket (PDF / Imagen opcional)</label>
                        <input type="file" name="archivo_adjunto" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </div>

                    <!-- Checkbox de Confirmación (Requisito clave) -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="check_confirmado_nuevo" name="confirmado" value="1" checked>
                            <label class="form-check-label fs-6 fw-bold text-dark" for="check_confirmado_nuevo">
                                <i class="fa-solid fa-circle-check text-success me-1"></i>Gasto confirmado para tener en cuenta en balance
                            </label>
                        </div>
                        <div class="form-text small ms-4">
                            Al marcar este check, el gasto se suma de forma oficial al balance y liquidación de la finca. Si se desmarca, quedará en estado pendiente.
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Notas / Observaciones</label>
                        <textarea name="notas" class="form-control" rows="2" placeholder="Observaciones sobre dosis, aplicación, forma de pago..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-ag fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Registrar Gasto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Conmutar Check de Confirmación de Gasto interactivo en la tabla (AJAX)
function toggleConfirmadoGasto(idGasto) {
    var btn = document.getElementById('btn_conf_' + idGasto);
    if (!btn) return;

    btn.disabled = true;
    var formData = new FormData();
    formData.append('action', 'toggle_confirmado');
    formData.append('id_gasto', idGasto);
    formData.append('is_ajax', '1');

    fetch('<?= APP_URL ?>/gastos', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled = false;
        if (res.success) {
            if (res.nuevo_estado === 1) {
                btn.className = 'btn btn-sm btn-toggle-check btn-success text-white shadow-sm px-3 py-1 rounded-pill';
                btn.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>Confirmado';
            } else {
                btn.className = 'btn btn-sm btn-toggle-check btn-outline-warning text-dark shadow-sm px-3 py-1 rounded-pill';
                btn.innerHTML = '<i class="fa-regular fa-clock me-1"></i>Pendiente Check';
            }
        } else {
            alert(res.message || 'Error al conmutar estado');
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        console.error(err);
        location.reload();
    });
}

// Desplegar / ocultar panel para crear tipo al vuelo
function toggleCajaNuevoTipo() {
    var caja = document.getElementById('caja_nuevo_tipo');
    if (caja.classList.contains('d-none')) {
        caja.classList.remove('d-none');
        document.getElementById('nuevo_tipo_nombre').focus();
    } else {
        caja.classList.add('d-none');
    }
}

// Comprobar si el nombre tecleado ya existe para no duplicar
function comprobarTipoDuplicado() {
    var txt = document.getElementById('nuevo_tipo_nombre').value.trim().toLowerCase();
    var alerta = document.getElementById('alerta_tipo_duplicado');
    if (!txt) {
        alerta.classList.add('d-none');
        return;
    }

    var options = document.querySelectorAll('#select_id_tipo option[data-nombre]');
    var encontrado = false;
    for (var i = 0; i < options.length; i++) {
        if (options[i].getAttribute('data-nombre') === txt) {
            encontrado = true;
            break;
        }
    }

    if (encontrado) {
        alerta.classList.remove('d-none');
    } else {
        alerta.classList.add('d-none');
    }
}

// Guardar nuevo tipo al vuelo vía AJAX y seleccionarlo en el combo
function guardarTipoAlVuelo() {
    var nombre = document.getElementById('nuevo_tipo_nombre').value.trim();
    var nat = document.getElementById('nuevo_tipo_nat').value;

    if (!nombre) {
        alert('Debes indicar un nombre para el nuevo tipo.');
        document.getElementById('nuevo_tipo_nombre').focus();
        return;
    }

    var formData = new FormData();
    formData.append('action', 'crear_tipo');
    formData.append('nombre_tipo', nombre);
    formData.append('naturaleza', nat);
    formData.append('is_ajax', '1');

    fetch('<?= APP_URL ?>/gastos/tipos', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            var select = document.getElementById('select_id_tipo');
            var optgroup = (res.naturaleza === 'ingreso') ? document.getElementById('optgroup_ingresos') : document.getElementById('optgroup_gastos');
            
            // Comprobar si ya existía la opción
            var opt = select.querySelector('option[value="' + res.id_tipo + '"]');
            if (!opt) {
                opt = document.createElement('option');
                opt.value = res.id_tipo;
                opt.textContent = res.nombre_tipo;
                opt.setAttribute('data-nombre', res.nombre_tipo.toLowerCase());
                if (optgroup) {
                    optgroup.appendChild(opt);
                } else {
                    select.appendChild(opt);
                }
            }
            select.value = res.id_tipo;

            // Limpiar y cerrar caja
            document.getElementById('nuevo_tipo_nombre').value = '';
            document.getElementById('caja_nuevo_tipo').classList.add('d-none');
            document.getElementById('alerta_tipo_duplicado').classList.add('d-none');
        } else {
            alert(res.message || 'Error al guardar nuevo tipo.');
        }
    })
    .catch(function(err) {
        console.error(err);
        alert('Error de conexión al guardar el tipo.');
    });
}

// Abrir modal automáticamente si se accede con ?nuevo=1
document.addEventListener('DOMContentLoaded', function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('nuevo') === '1') {
        var modal = new bootstrap.Modal(document.getElementById('modalNuevoGasto'));
        modal.show();
    }
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>