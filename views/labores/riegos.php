<?php
$pageTitle = "Control de Riegos y Depósitos de Agua - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR FORMULARIOS (GUARDAR RIEGO, LLENADO DE DEPÓSITO, NUEVO DEPÓSITO, BORRADO LÓGICO)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. GUARDAR RIEGO
    if ($action === 'guardar_riego' || isset($_POST['guardar_riego'])) {
        try {
            $idPlantacion = (int)$_POST['id_plantacion'];
            $fechaRiego = $_POST['fecha_riego'];
            $origenAgua = isset($_POST['origen_agua']) ? $_POST['origen_agua'] : 'comunidad';
            $idDeposito = (!empty($_POST['id_deposito']) && $origenAgua === 'deposito_goteo') ? (int)$_POST['id_deposito'] : null;
            $tipoRiego = isset($_POST['tipo_riego']) ? $_POST['tipo_riego'] : ($origenAgua === 'deposito_goteo' ? 'goteo' : 'inundacion_pie');
            $duracion = (float)$_POST['duracion_horas'];
            $volumenM3 = !empty($_POST['volumen_m3']) ? (float)$_POST['volumen_m3'] : null;
            
            // Litros consumidos
            $litrosConsumidos = !empty($_POST['litros_consumidos']) ? (float)$_POST['litros_consumidos'] : ($volumenM3 ? $volumenM3 * 1000 : null);
            if ($litrosConsumidos && !$volumenM3) {
                $volumenM3 = round($litrosConsumidos / 1000.0, 2);
            }

            $parada = ($origenAgua === 'comunidad' && isset($_POST['parada_utilizada'])) ? trim($_POST['parada_utilizada']) : '';
            $tablon = ($origenAgua === 'comunidad' && isset($_POST['tablon_utilizado'])) ? trim($_POST['tablon_utilizado']) : '';
            $conFerti = isset($_POST['con_fertirrigacion']) ? 1 : 0;
            $abonos = isset($_POST['productos_abono']) ? trim($_POST['productos_abono']) : '';
            $obs = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

            $stmt = $db->prepare("
                INSERT INTO riegos (
                    id_plantacion, fecha_riego, hora_inicio, hora_fin, duracion_horas, volumen_m3,
                    parada_utilizada, tablon_utilizado, tipo_riego, con_fertirrigacion, productos_abono,
                    observaciones, creado_por_id, origen_agua, id_deposito, litros_consumidos, activo
                ) VALUES (
                    :plant, :fecha, :hini, :hfin, :dur, :vol,
                    :parada, :tablon, :tipo, :ferti, :abonos,
                    :obs, :user, :origen, :deposito, :litros, 1
                )
            ");
            $stmt->execute(array(
                ':plant'    => $idPlantacion,
                ':fecha'    => $fechaRiego,
                ':hini'     => !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null,
                ':hfin'     => !empty($_POST['hora_fin']) ? $_POST['hora_fin'] : null,
                ':dur'      => $duracion,
                ':vol'      => $volumenM3,
                ':parada'   => $parada,
                ':tablon'   => $tablon,
                ':tipo'     => $tipoRiego,
                ':ferti'    => $conFerti,
                ':abonos'   => $abonos,
                ':obs'      => $obs,
                ':user'     => $currentUser['id'],
                ':origen'   => $origenAgua,
                ':deposito' => $idDeposito,
                ':litros'   => $litrosConsumidos
            ));

            // Si es desde depósito y se consumieron litros, actualizar saldo del depósito
            if ($idDeposito && $litrosConsumidos > 0) {
                $db->prepare("UPDATE depositos_agua SET litros_actuales = GREATEST(0, litros_actuales - :l) WHERE id_deposito = :id")
                   ->execute(array(':l' => $litrosConsumidos, ':id' => $idDeposito));
            }

            $mensaje = "Riego registrado correctamente en el cuaderno oficial.";
        } catch (Exception $e) {
            $error = "Error al guardar riego: " . $e->getMessage();
        }
    }

    // 2. REGISTRAR LLENADO DE DEPÓSITO
    elseif ($action === 'registrar_llenado') {
        try {
            $idDep = (int)$_POST['id_deposito_llenar'];
            $fechaLlenado = $_POST['fecha_llenado'];
            $litros = (float)$_POST['litros_llenados'];
            $procedencia = trim($_POST['procedencia_agua']);
            $coste = !empty($_POST['coste_llenado']) ? (float)$_POST['coste_llenado'] : 0.00;
            $obs = trim(isset($_POST['observaciones_llenado']) ? $_POST['observaciones_llenado'] : '');

            $stmtLlen = $db->prepare("
                INSERT INTO depositos_llenados (id_deposito, fecha_llenado, litros_llenados, procedencia_agua, coste_llenado, observaciones, creado_por_id, activo)
                VALUES (:dep, :fecha, :litros, :proc, :coste, :obs, :user, 1)
            ");
            $stmtLlen->execute(array(
                ':dep'     => $idDep,
                ':fecha'   => $fechaLlenado,
                ':litros'  => $litros,
                ':proc'    => $procedencia,
                ':coste'   => $coste,
                ':obs'     => $obs,
                ':user'    => $currentUser['id']
            ));

            // Sumar al saldo del depósito sin exceder capacidad si está definida
            $db->prepare("UPDATE depositos_agua SET litros_actuales = LEAST(capacidad_litros, litros_actuales + :l) WHERE id_deposito = :id")
               ->execute(array(':l' => $litros, ':id' => $idDep));

            $mensaje = "Llenado de depósito registrado correctamente. Saldo de agua actualizado.";
        } catch (Exception $e) {
            $error = "Error al registrar llenado: " . $e->getMessage();
        }
    }

    // 3. CREAR NUEVO DEPÓSITO / BALSA
    elseif ($action === 'crear_deposito') {
        try {
            $nombreDep = trim($_POST['nombre_deposito']);
            $capacidad = (float)$_POST['capacidad_litros'];
            $litrosIniciales = (float)(isset($_POST['litros_iniciales']) ? $_POST['litros_iniciales'] : 0);
            $idFinca = !empty($_POST['id_finca_deposito']) ? (int)$_POST['id_finca_deposito'] : null;
            $tipoDep = isset($_POST['tipo_deposito']) ? $_POST['tipo_deposito'] : 'deposito';
            $ubicacion = trim(isset($_POST['ubicacion']) ? $_POST['ubicacion'] : '');

            $stmtDep = $db->prepare("
                INSERT INTO depositos_agua (id_propietario, id_finca, nombre_deposito, capacidad_litros, litros_actuales, tipo_deposito, ubicacion, activo)
                VALUES (:prop, :finca, :nombre, :cap, :act, :tipo, :ubi, 1)
            ");
            $stmtDep->execute(array(
                ':prop'   => (int)$tenantId,
                ':finca'  => $idFinca,
                ':nombre' => $nombreDep,
                ':cap'    => $capacidad,
                ':act'    => $litrosIniciales,
                ':tipo'   => $tipoDep,
                ':ubi'    => $ubicacion
            ));
            $mensaje = "Nuevo depósito o balsa '{$nombreDep}' añadido con éxito.";
        } catch (Exception $e) {
            $error = "Error al crear depósito: " . $e->getMessage();
        }
    }

    // 4. BORRADO LÓGICO (GEMINI.md)
    elseif ($action === 'borrar_riego') {
        try {
            $idRiego = (int)$_POST['id_riego'];
            $db->prepare("UPDATE riegos SET activo = 0 WHERE id_riego = :id")->execute(array(':id' => $idRiego));
            $mensaje = "Riego eliminado correctamente del historial (borrado lógico seguro).";
        } catch (Exception $e) {
            $error = "Error al eliminar riego: " . $e->getMessage();
        }
    }
}

// Filtros de búsqueda para el listado e informe A4
$filtroFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$filtroOrigen = isset($_GET['origen']) ? trim($_GET['origen']) : '';
$filtroDesde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$filtroHasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

// Consultar Fincas de la explotación
$sqlFincas = "SELECT id_finca, nombre_finca FROM fincas WHERE activo = 1";
if ($tenantId && !$isSuperAdmin) {
    $sqlFincas .= " AND id_propietario = " . (int)$tenantId;
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$fincasList = $db->query($sqlFincas)->fetchAll();

// Consultar Plantaciones de la explotación
$sqlPlant = "
    SELECT p.id_plantacion, p.identificador_cuadro, f.id_finca, f.nombre_finca, c.nombre_comun, f.parada, f.tablon
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

// Consultar Depósitos de Agua de la explotación
$sqlDep = "
    SELECT d.*, f.nombre_finca
    FROM depositos_agua d
    LEFT JOIN fincas f ON d.id_finca = f.id_finca
    WHERE d.activo = 1
";
if ($tenantId && !$isSuperAdmin) {
    $sqlDep .= " AND d.id_propietario = " . (int)$tenantId;
}
$sqlDep .= " ORDER BY d.nombre_deposito ASC";
$depositos = $db->query($sqlDep)->fetchAll();

// Histórico de Llenados de Depósitos
$sqlLlen = "
    SELECT dl.*, d.nombre_deposito, f.nombre_finca
    FROM depositos_llenados dl
    JOIN depositos_agua d ON dl.id_deposito = d.id_deposito
    LEFT JOIN fincas f ON d.id_finca = f.id_finca
    WHERE dl.activo = 1
";
if ($tenantId && !$isSuperAdmin) {
    $sqlLlen .= " AND d.id_propietario = " . (int)$tenantId;
}
$sqlLlen .= " ORDER BY dl.fecha_llenado DESC, dl.id_llenado DESC LIMIT 20";
$llenadosHistorial = $db->query($sqlLlen)->fetchAll();

// Histórico de Riegos con Filtros Contextuales para el Informe A4
$sqlRiegos = "
    SELECT r.*, f.id_finca, f.nombre_finca, c.nombre_comun, p.identificador_cuadro, d.nombre_deposito
    FROM riegos r
    JOIN plantaciones p ON r.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    LEFT JOIN depositos_agua d ON r.id_deposito = d.id_deposito
    WHERE (r.activo = 1 OR r.activo IS NULL)
";
$params = array();

if ($tenantId && !$isSuperAdmin) {
    $sqlRiegos .= " AND f.id_propietario = :tenant";
    $params[':tenant'] = (int)$tenantId;
}
if ($filtroFinca > 0) {
    $sqlRiegos .= " AND f.id_finca = :finca";
    $params[':finca'] = $filtroFinca;
}
if (!empty($filtroOrigen)) {
    $sqlRiegos .= " AND r.origen_agua = :origen";
    $params[':origen'] = $filtroOrigen;
}
if (!empty($filtroDesde)) {
    $sqlRiegos .= " AND r.fecha_riego >= :desde";
    $params[':desde'] = $filtroDesde;
}
if (!empty($filtroHasta)) {
    $sqlRiegos .= " AND r.fecha_riego <= :hasta";
    $params[':hasta'] = $filtroHasta;
}

$sqlRiegos .= " ORDER BY r.fecha_riego DESC, r.id_riego DESC";
$stmtR = $db->prepare($sqlRiegos);
$stmtR->execute($params);
$riegos = $stmtR->fetchAll();

// Estadísticas de riegos filtrados
$totalHorasRiego = 0;
$totalVolumenM3 = 0;
$totalLitrosGoteo = 0;
foreach ($riegos as $r) {
    $totalHorasRiego += (float)$r['duracion_horas'];
    $totalVolumenM3 += (float)$r['volumen_m3'];
    if ($r['litros_consumidos']) {
        $totalLitrosGoteo += (float)$r['litros_consumidos'];
    }
}
?>

<div class="container py-4">

    <!-- Cabecera Corporativa con Acciones y Acceso al Visor A4 -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #0284c7);">
                <i class="fa-solid fa-faucet-drip fs-3 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Gestión Integral de Riegos y Depósitos</h2>
                <p class="text-muted mb-0 small">Turnos de Comunidad de Regantes, Riego por Goteo desde Depósitos propios y Control de Llenados</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-info fw-semibold shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#panelDepositos">
                <i class="fa-solid fa-database me-1 text-primary"></i> Balsas y Depósitos (<?= count($depositos) ?>)
            </button>
            <a href="<?= APP_URL ?>/solicitud-riego" class="btn btn-outline-primary fw-semibold shadow-sm">
                <i class="fa-solid fa-file-circle-plus me-1"></i> Solicitud Comunidad (A4)
            </a>
            <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formRiego">
                <i class="fa-solid fa-plus me-1"></i> Registrar Riego
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
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- PANEL COLAPSABLE: CONTROL DE DEPÓSITOS Y BALSAS DE AGUA -->
    <div class="collapse mb-4" id="panelDepositos">
        <div class="card border-0 shadow-sm rounded-3" style="background: #f8fafc; border-top: 4px solid #0284c7 !important;">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="fa-solid fa-water-ladder me-2 text-info"></i>Balsas y Depósitos de Almacenamiento de Agua
                    </h5>
                    <span class="text-muted small">Permite regar por goteo localizado sin limitación de tanda comunitaria y controlar el llenado y procedencia del agua</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoDeposito">
                        <i class="fa-solid fa-plus me-1"></i> Añadir Balsa / Depósito
                    </button>
                    <button class="btn btn-sm btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalRegistrarLlenado">
                        <i class="fa-solid fa-faucet me-1"></i> Registrar Llenado de Agua
                    </button>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <?php if (empty($depositos)): ?>
                        <div class="col-12 text-center py-4 text-muted">
                            <i class="fa-solid fa-drum-steelpan fa-2x mb-2 text-secondary opacity-50"></i>
                            <p class="mb-2">No tienes depósitos o balsas registrados todavía.</p>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoDeposito">
                                <i class="fa-solid fa-plus me-1"></i> Crear mi primer depósito
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($depositos as $dep): ?>
                            <?php 
                                $pct = $dep['capacidad_litros'] > 0 ? min(100, round(($dep['litros_actuales'] / $dep['capacidad_litros']) * 100)) : 0;
                                $barColor = $pct > 50 ? 'bg-info' : ($pct > 20 ? 'bg-warning' : 'bg-danger');
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card border bg-white shadow-sm h-100 p-3 rounded-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($dep['nombre_deposito']) ?></h6>
                                            <span class="text-muted small"><i class="fa-solid fa-location-dot me-1 text-danger"></i><?= htmlspecialchars($dep['nombre_finca'] ?: 'General') ?></span>
                                        </div>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle font-monospace">
                                            <?= number_format($dep['litros_actuales'], 0, ',', '.') ?> L
                                        </span>
                                    </div>
                                    <div class="progress mb-2" style="height: 10px;">
                                        <div class="progress-bar <?= $barColor ?> progress-bar-striped" role="progressbar" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small text-muted">
                                        <span>Nivel: <strong><?= $pct ?>%</strong></span>
                                        <span>Capacidad: <?= number_format($dep['capacidad_litros'], 0, ',', '.') ?> L</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Historial Reciente de Llenados -->
                <?php if (!empty($llenadosHistorial)): ?>
                    <div class="border-top pt-3">
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Últimos Llenados Registrados</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Depósito</th>
                                        <th>Litros Almacenados</th>
                                        <th>Procedencia del Agua</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($llenadosHistorial as $lh): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($lh['fecha_llenado'])) ?></td>
                                            <td><strong><?= htmlspecialchars($lh['nombre_deposito']) ?></strong></td>
                                            <td><span class="badge bg-success-subtle text-success font-monospace">+<?= number_format($lh['litros_llenados'], 0, ',', '.') ?> L</span></td>
                                            <td><span class="badge bg-info-subtle text-info"><?= htmlspecialchars($lh['procedencia_agua']) ?></span></td>
                                            <td class="text-muted"><?= htmlspecialchars($lh['observaciones'] ?: '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FORMULARIO DE NUEVO RIEGO (COMUNIDAD VS GOTEO DEPÓSITO) -->
    <div class="collapse mb-4" id="formRiego">
        <div class="card card-ag p-4 border-top border-4 border-primary shadow-sm">
            <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-faucet-drip me-2"></i>Registrar Operación de Riego</h5>
            <form action="<?= APP_URL ?>/riegos" method="POST">
                <input type="hidden" name="action" value="guardar_riego">

                <!-- SELECTOR CRUCIAL: ORIGEN DEL AGUA -->
                <div class="card p-3 mb-3 border border-primary border-opacity-25" style="background: #f0f7ff;">
                    <label class="form-label fw-bold text-primary mb-2"><i class="fa-solid fa-shuffle me-2"></i>Origen y Modalidad del Agua:</label>
                    <div class="d-flex flex-wrap gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="origen_agua" id="origen_comunidad" value="comunidad" checked onchange="cambiarOrigenAgua()">
                            <label class="form-check-label fw-bold text-dark" for="origen_comunidad">
                                🌊 Agua de Comunidad de Regantes (Tanda, Turno y Parada de Acequia)
                            </label>
                            <div class="form-text small">Sujeto a turno concedido, parada oficial y horario estricto.</div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="origen_agua" id="origen_deposito" value="deposito_goteo" onchange="cambiarOrigenAgua()">
                            <label class="form-check-label fw-bold text-success" for="origen_deposito">
                                💧 Riego por Goteo desde Depósito / Balsa Propia
                            </label>
                            <div class="form-text small">Sin límite de tanda comunitaria; consume agua almacenada en tus balsas o depósitos.</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small">Finca y Parcela / Plantación *</label>
                        <select name="id_plantacion" class="form-select" required>
                            <option value="">-- Seleccionar Parcela a Regar --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>">
                                    <?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun'] . ' (' . $pl['identificador_cuadro'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Fecha de Riego *</label>
                        <input type="date" name="fecha_riego" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Sistema de Aplicación</label>
                        <select name="tipo_riego" id="tipo_riego" class="form-select">
                            <option value="goteo">Goteo Localizado de Alta Eficiencia</option>
                            <option value="inundacion_pie">Inundación / Pie / Manta (Tanda tradicional)</option>
                            <option value="aspersion">Aspersión</option>
                            <option value="microaspersion">Microaspersión</option>
                        </select>
                    </div>
                </div>

                <!-- BLOQUE ESPECÍFICO PARA RIEGO POR GOTEO DESDE DEPÓSITO -->
                <div id="bloque_deposito" class="row g-3 mb-3 p-3 bg-light rounded-3 border d-none">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-success">
                            <i class="fa-solid fa-database me-1"></i>Seleccionar Balsa / Depósito de Origen *
                        </label>
                        <select name="id_deposito" id="id_deposito" class="form-select">
                            <option value="">-- Seleccionar Depósito --</option>
                            <?php foreach ($depositos as $dep): ?>
                                <option value="<?= $dep['id_deposito'] ?>">
                                    <?= htmlspecialchars($dep['nombre_deposito']) ?> (Saldo actual: <?= number_format($dep['litros_actuales'], 0, ',', '.') ?> L / <?= number_format($dep['capacidad_litros'], 0, ',', '.') ?> L)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-success">Litros Consumidos del Depósito</label>
                        <div class="input-group">
                            <input type="number" step="10" name="litros_consumidos" id="litros_consumidos" class="form-control font-monospace" placeholder="Ej: 15000" oninput="actualizarLitrosAM3()">
                            <span class="input-group-text">Litros</span>
                        </div>
                        <div class="form-text small">Se descontarán automáticamente del saldo actual del depósito seleccionado.</div>
                    </div>
                </div>

                <!-- BLOQUE ESPECÍFICO PARA COMUNIDAD DE REGANTES (PARADA Y TABLÓN) -->
                <div id="bloque_comunidad" class="row g-3 mb-3 p-3 bg-light rounded-3 border">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-primary">
                            <i class="fa-solid fa-map-pin me-1"></i>Parada Oficial Utilizada (1 a 89)
                        </label>
                        <input type="text" name="parada_utilizada" class="form-control" placeholder="Ej: Parada 003 - JOSÉ GARCÍA MARÍN">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-primary">
                            <i class="fa-solid fa-water me-1"></i>Tablón / Hilada de Acequia
                        </label>
                        <input type="text" name="tablon_utilizado" class="form-control" placeholder="Ej: Tablón Alto / Turno Mañana">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Duración del Riego (Horas) *</label>
                        <input type="number" step="0.25" name="duracion_horas" class="form-control font-monospace" required placeholder="Ej: 3.5">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Volumen Estimado (m³)</label>
                        <input type="number" step="0.5" name="volumen_m3" id="volumen_m3" class="form-control font-monospace" placeholder="Ej: 15.0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Hora de Inicio (Opcional)</label>
                        <input type="time" name="hora_inicio" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Hora de Fin (Opcional)</label>
                        <input type="time" name="hora_fin" class="form-control">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="con_fertirrigacion" name="con_fertirrigacion" value="1">
                            <label class="form-check-label fw-bold text-success fs-6" for="con_fertirrigacion">
                                <i class="fa-solid fa-seedling me-1"></i>Con Fertirrigación (Abonado)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small">Abonos / Fertilizantes Aplicados en el Agua</label>
                        <input type="text" name="productos_abono" class="form-control" placeholder="Ej: Nitrato potásico 25kg, Ácido fosfórico 5L, Quelato de hierro...">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Observaciones Técnicas</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Presión de filtrado (bar), lecturas de contador de agua, estado del suelo..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-toggle="collapse" data-bs-target="#formRiego">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar en Dietario Oficial
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL NUEVO DEPÓSITO -->
    <div class="modal fade" id="modalNuevoDeposito" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #0284c7);">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-database me-2"></i>Añadir Depósito o Balsa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="<?= APP_URL ?>/riegos" method="POST">
                    <input type="hidden" name="action" value="crear_deposito">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nombre Identificativo *</label>
                            <input type="text" name="nombre_deposito" class="form-control" placeholder="Ej: Balsa Principal Polietileno, Depósito Cabezal..." required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Capacidad Total (Litros) *</label>
                                <input type="number" step="100" name="capacidad_litros" class="form-control font-monospace" placeholder="Ej: 50000" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Litros Iniciales Actuales</label>
                                <input type="number" step="100" name="litros_iniciales" class="form-control font-monospace" placeholder="0">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Finca Asociada</label>
                                <select name="id_finca_deposito" class="form-select">
                                    <option value="">-- General / Sin finca fija --</option>
                                    <?php foreach ($fincasList as $fl): ?>
                                        <option value="<?= $fl['id_finca'] ?>"><?= htmlspecialchars($fl['nombre_finca']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Tipo de Estructura</label>
                                <select name="tipo_deposito" class="form-select">
                                    <option value="balsa">Balsa de tierra / polietileno</option>
                                    <option value="deposito">Depósito de hormigón / obra</option>
                                    <option value="fibra">Depósito de fibra de vidrio</option>
                                    <option value="cisterna">Cisterna / Poliéster</option>
                                    <option value="pozo">Pozo con sondeo</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold small">Ubicación / Coordenadas</label>
                            <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Cabezal de riego parcela 4">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold">Guardar Depósito</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL REGISTRAR LLENADO DE AGUA -->
    <div class="modal fade" id="modalRegistrarLlenado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #16a34a);">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-faucet me-2"></i>Registrar Llenado de Depósito</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="<?= APP_URL ?>/riegos" method="POST">
                    <input type="hidden" name="action" value="registrar_llenado">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Depósito / Balsa a Llenar *</label>
                            <select name="id_deposito_llenar" class="form-select" required>
                                <option value="">-- Seleccionar Depósito --</option>
                                <?php foreach ($depositos as $dep): ?>
                                    <option value="<?= $dep['id_deposito'] ?>">
                                        <?= htmlspecialchars($dep['nombre_deposito']) ?> (Capacidad: <?= number_format($dep['capacidad_litros'], 0, ',', '.') ?> L)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Fecha de Llenado *</label>
                                <input type="date" name="fecha_llenado" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Litros Almacenados *</label>
                                <input type="number" step="100" name="litros_llenados" class="form-control font-monospace" placeholder="Ej: 20000" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Procedencia del Agua *</label>
                            <select name="procedencia_agua" class="form-select" required>
                                <option value="Comunidad de Regantes (Tanda/Sobra)">🌊 Comunidad de Regantes (Tanda o Sobra de acequia)</option>
                                <option value="Pozo propio / Sondeo">⚡ Pozo propio / Sondeo subterráneo</option>
                                <option value="Manantial / Nacimiento">🏞️ Manantial natural / Nacimiento de agua</option>
                                <option value="Cuba externa / Transporte">🚛 Cuba de agua externa / Camión cisterna</option>
                                <option value="Lluvia / Recogida Pluvial">🌧️ Lluvia / Recogida de escorrentía pluvial</option>
                                <option value="Canal general / Río">🏞️ Canal general o toma directa de río</option>
                                <option value="Otro">Otro origen</option>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Coste del Llenado (€) (Opcional)</label>
                                <input type="number" step="0.01" name="coste_llenado" class="form-control font-monospace" placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Observaciones</label>
                                <input type="text" name="observaciones_llenado" class="form-control" placeholder="Horas de bomba, turbidez...">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-sm fw-bold">Guardar Llenado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- BARRA DE FILTROS Y BÚSQUEDA ADAPTADOS AL CONTEXTO -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm d-print-none">
        <form action="<?= APP_URL ?>/riegos" method="GET" class="row g-2 align-items-center">
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
                <label class="form-label small text-muted mb-1">Origen del Agua:</label>
                <select name="origen" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los orígenes --</option>
                    <option value="comunidad" <?= $filtroOrigen === 'comunidad' ? 'selected' : '' ?>>🌊 Comunidad de Regantes</option>
                    <option value="deposito_goteo" <?= $filtroOrigen === 'deposito_goteo' ? 'selected' : '' ?>>💧 Goteo desde Depósito</option>
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
                <?php if ($filtroFinca || $filtroOrigen || $filtroDesde || $filtroHasta): ?>
                    <a href="<?= APP_URL ?>/riegos" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ÁREA DE INFORME OFICIAL MAQUETADA PARA TAMAÑO A4 -->
    <div class="card card-ag shadow-sm border-0 mb-4" id="area-informe-riegos">
        
        <!-- Toolbar Universal de Exportación (Imprimir, Visualizar A4, PDF, Enviar Email) -->
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 d-print-none">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-waveform text-primary fs-5"></i>
                <h6 class="fw-bold mb-0 text-primary">Informe Oficial de Riegos y Consumos Hídricos (<?= count($riegos) ?> registros)</h6>
            </div>
            <div>
                <?php 
                    $reportTitle = "Informe Oficial de Riegos y Consumos Hidricos";
                    $reportFileName = "informe_riegos_" . date('Ymd_His');
                    $reportContainerId = "area-informe-riegos";
                    require VIEWS_PATH . '/layouts/toolbar_informes.php';
                ?>
            </div>
        </div>

        <div class="card-body p-4">
            
            <!-- Encabezado A4 de Auditoría Agraria Oficial -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div>
                    <h4 class="fw-bold text-primary mb-1" style="letter-spacing: -0.02em;">
                        <i class="fa-solid fa-seedling me-2 text-success"></i><?= htmlspecialchars(Auth::user()['nombre_propietario'] ?: APP_NAME) ?>
                    </h4>
                    <div class="small text-muted">Cuaderno Oficial de Explotación Agraria - Registro Histórico de Riegos y Recursos Hídricos</div>
                    <div class="small text-secondary">Fecha de emisión: <strong><?= date('d/m/Y H:i') ?></strong> | Responsable: <?= htmlspecialchars($currentUser['nombre_completo']) ?></div>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill">REGISTRO OFICIAL DE AGUA</span>
                    <div class="small text-muted mt-1 font-monospace">Normativa PAC / Cuaderno Digital</div>
                </div>
            </div>

            <!-- Resumen de Métricas del Informe Filtrado -->
            <div class="row g-2 mb-3 text-center">
                <div class="col-4">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Horas Totales de Riego</span>
                        <strong class="fs-5 text-primary font-monospace"><?= number_format($totalHorasRiego, 2, ',', '.') ?> h</strong>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Volumen Estimado Aplicado</span>
                        <strong class="fs-5 text-success font-monospace"><?= number_format($totalVolumenM3, 2, ',', '.') ?> m³</strong>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Agua de Balsas / Depósitos</span>
                        <strong class="fs-5 text-info font-monospace"><?= number_format($totalLitrosGoteo, 0, ',', '.') ?> L</strong>
                    </div>
                </div>
            </div>

            <!-- Tabla de Riegos Optimizada para A4 -->
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light small text-uppercase fw-bold text-secondary">
                        <tr>
                            <th>Fecha</th>
                            <th>Finca / Parcela</th>
                            <th>Origen del Agua</th>
                            <th>Sistema</th>
                            <th class="text-center">Duración</th>
                            <th class="text-center">Volumen</th>
                            <th>Tanda o Depósito</th>
                            <th>Fertirrigación</th>
                            <th class="d-print-none text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riegos)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-faucet-drip fa-2x mb-2 text-secondary opacity-50"></i>
                                    <p class="mb-0">No se encontraron riegos con los filtros seleccionados.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riegos as $r): ?>
                                <tr>
                                    <td class="font-monospace fw-semibold"><?= date('d/m/Y', strtotime($r['fecha_riego'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($r['nombre_finca']) ?></strong>
                                        <div class="small text-muted"><?= htmlspecialchars($r['nombre_comun']) ?> (<?= htmlspecialchars($r['identificador_cuadro']) ?>)</div>
                                    </td>
                                    <td>
                                        <?php if ($r['origen_agua'] === 'deposito_goteo'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                                <i class="fa-solid fa-database me-1"></i>Depósito / Balsa
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                                <i class="fa-solid fa-water me-1"></i>Comunidad
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-capitalize small"><?= str_replace('_', ' ', $r['tipo_riego']) ?></td>
                                    <td class="text-center font-monospace fw-bold text-primary"><?= number_format($r['duracion_horas'], 2) ?> h</td>
                                    <td class="text-center font-monospace">
                                        <?= $r['volumen_m3'] ? number_format($r['volumen_m3'], 2, ',', '.') . ' m³' : ($r['litros_consumidos'] ? number_format($r['litros_consumidos'] / 1000, 2) . ' m³' : '-') ?>
                                    </td>
                                    <td class="small">
                                        <?php if ($r['origen_agua'] === 'deposito_goteo'): ?>
                                            <strong class="text-success"><?= htmlspecialchars($r['nombre_deposito'] ?: 'Balsa propia') ?></strong>
                                            <?php if ($r['litros_consumidos']): ?>
                                                <div class="text-muted font-monospace"><?= number_format($r['litros_consumidos'], 0, ',', '.') ?> L</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <strong>Parada:</strong> <?= htmlspecialchars($r['parada_utilizada'] ?: '-') ?><br>
                                            <span class="text-muted">Tablón: <?= htmlspecialchars($r['tablon_utilizado'] ?: '-') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if ($r['con_fertirrigacion']): ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-vial me-1"></i>Abono</span>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($r['productos_abono'] ?: '') ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">Agua sola</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-print-none text-end">
                                        <form action="<?= APP_URL ?>/riegos" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este riego?');">
                                            <input type="hidden" name="action" value="borrar_riego">
                                            <input type="hidden" name="id_riego" value="<?= $r['id_riego'] ?>">
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
                <span>Dietario Agrícola Digital - Control Hídrico de Precisión</span>
                <span>Documento generado para inspección y cuaderno de campo</span>
            </div>
        </div>
    </div>
</div>

<script>
function cambiarOrigenAgua() {
    const esComunidad = document.getElementById('origen_comunidad').checked;
    const bComunidad = document.getElementById('bloque_comunidad');
    const bDeposito = document.getElementById('bloque_deposito');
    const selTipoRiego = document.getElementById('tipo_riego');

    if (esComunidad) {
        bComunidad.classList.remove('d-none');
        bDeposito.classList.add('d-none');
    } else {
        bComunidad.classList.add('d-none');
        bDeposito.classList.remove('d-none');
        selTipoRiego.value = 'goteo';
    }
}

function actualizarLitrosAM3() {
    const litros = parseFloat(document.getElementById('litros_consumidos').value) || 0;
    const txtM3 = document.getElementById('volumen_m3');
    if (litros > 0) {
        txtM3.value = (litros / 1000.0).toFixed(2);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    cambiarOrigenAgua();
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
