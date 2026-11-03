<?php
$pageTitle = "Podas, Talas y Gestión de Residuos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR FORMULARIOS (GUARDAR PODA, BORRADO LÓGICO)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'guardar_poda' || isset($_POST['guardar_poda'])) {
        try {
            $idPlantacion = (int)$_POST['id_plantacion'];
            $fecha = $_POST['fecha'];
            $op = $_POST['tipo_operacion'];
            $residuos = $_POST['destino_residuos'];
            $permiso = trim(isset($_POST['numero_permiso_quema']) ? $_POST['numero_permiso_quema'] : '');
            $vol = !empty($_POST['kilos_o_volumen_estimado']) ? (float)$_POST['kilos_o_volumen_estimado'] : null;
            $obs = trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : '');

            $stmt = $db->prepare("
                INSERT INTO podas_talas (id_plantacion, fecha, tipo_operacion, destino_residuos, numero_permiso_quema, kilos_o_volumen_estimado, observaciones, creado_por_id, activo)
                VALUES (:plant, :fecha, :op, :residuos, :permiso, :vol, :obs, :user, 1)
            ");
            $stmt->execute(array(
                ':plant'    => $idPlantacion,
                ':fecha'    => $fecha,
                ':op'       => $op,
                ':residuos' => $residuos,
                ':permiso'  => $permiso,
                ':vol'      => $vol,
                ':obs'      => $obs,
                ':user'     => $currentUser['id']
            ));
            $mensaje = "Registro de poda/tala y valorización de biomasa guardado con éxito.";
        } catch (Exception $e) {
            $error = "Error al guardar el registro de poda: " . $e->getMessage();
        }
    } elseif ($action === 'borrar_poda') {
        try {
            $idPoda = (int)$_POST['id_poda'];
            $db->prepare("UPDATE podas_talas SET activo = 0 WHERE id_poda = :id")->execute(array(':id' => $idPoda));
            $mensaje = "Registro de poda eliminado (borrado lógico seguro).";
        } catch (Exception $e) {
            $error = "Error al eliminar poda: " . $e->getMessage();
        }
    }
}

// Filtros para listado e informe A4
$filtroFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$filtroOp = isset($_GET['op']) ? trim($_GET['op']) : '';
$filtroResiduos = isset($_GET['residuos']) ? trim($_GET['residuos']) : '';
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

// Histórico de podas con filtros contextuales para el informe A4
$sqlPodas = "
    SELECT pt.*, f.id_finca, f.nombre_finca, c.nombre_comun, p.identificador_cuadro
    FROM podas_talas pt
    JOIN plantaciones p ON pt.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE (pt.activo = 1 OR pt.activo IS NULL)
";
$params = array();

if ($tenantId && !$isSuperAdmin) {
    $sqlPodas .= " AND f.id_propietario = :tenant";
    $params[':tenant'] = (int)$tenantId;
}
if ($filtroFinca > 0) {
    $sqlPodas .= " AND f.id_finca = :finca";
    $params[':finca'] = $filtroFinca;
}
if (!empty($filtroOp)) {
    $sqlPodas .= " AND pt.tipo_operacion = :op";
    $params[':op'] = $filtroOp;
}
if (!empty($filtroResiduos)) {
    $sqlPodas .= " AND pt.destino_residuos = :residuos";
    $params[':residuos'] = $filtroResiduos;
}
if (!empty($filtroDesde)) {
    $sqlPodas .= " AND pt.fecha >= :desde";
    $params[':desde'] = $filtroDesde;
}
if (!empty($filtroHasta)) {
    $sqlPodas .= " AND pt.fecha <= :hasta";
    $params[':hasta'] = $filtroHasta;
}

$sqlPodas .= " ORDER BY pt.fecha DESC, pt.id_poda DESC";
$stmtP = $db->prepare($sqlPodas);
$stmtP->execute($params);
$podas = $stmtP->fetchAll();

// Estadísticas para el informe
$totalKgBiomasa = 0;
foreach ($podas as $p) {
    $totalKgBiomasa += (float)$p['kilos_o_volumen_estimado'];
}
?>

<div class="container py-4">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #d97706);">
                <i class="fa-solid fa-scissors fs-3 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Podas, Talas y Gestión de Biomasa</h2>
                <p class="text-muted mb-0 small">Trazabilidad de cortes de madera, podas de mantenimiento y aprovechamiento sostenible de subproductos</p>
            </div>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formPoda">
            <i class="fa-solid fa-plus me-1"></i> Registrar Poda / Tala
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

    <!-- FORMULARIO DE NUEVO REGISTRO DE PODA -->
    <div class="collapse mb-4" id="formPoda">
        <div class="card card-ag p-4 border-top border-4 border-warning shadow-sm">
            <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-scissors me-2"></i>Nuevo Registro de Poda o Tala</h5>
            <form action="<?= APP_URL ?>/podas" method="POST">
                <input type="hidden" name="action" value="guardar_poda">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small">Finca y Parcela / Cultivo *</label>
                        <select name="id_plantacion" class="form-select" required>
                            <option value="">-- Seleccionar Parcela --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>">
                                    <?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun'] . ' (' . $pl['identificador_cuadro'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Fecha de la Labor *</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Tipo de Operación *</label>
                        <select name="tipo_operacion" class="form-select fw-semibold" required>
                            <option value="poda_mantenimiento">✂️ Poda de producción y mantenimiento</option>
                            <option value="poda_formacion">🌱 Poda de formación en árboles jóvenes</option>
                            <option value="poda_rejuvenecimiento">🪵 Poda drástica de rejuvenecimiento</option>
                            <option value="poda_verde">🍃 Poda en verde / Deschuponado estival</option>
                            <option value="tala">🪓 Tala o arranque de árboles senescentes</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small">Destino y Valorización de Residuos *</label>
                        <select name="destino_residuos" class="form-select fw-semibold" required>
                            <option value="triturado_acolchado">♻️ Triturado in situ para acolchado orgánico (Mulching)</option>
                            <option value="retirada_reciclaje_biomasa">🪵 Aprovechamiento para leña / Biomasa energética</option>
                            <option value="compostaje">🌿 Compostaje vegetal en montón</option>
                            <option value="quema_autorizada">🔥 Quema agrícola autorizada (Excepción fitosanitaria)</option>
                            <option value="otro">Otro destino autorizado</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Nº Expediente / Permiso de Quema</label>
                        <input type="text" name="numero_permiso_quema" class="form-control" placeholder="Ej: EXP-2026/0451 (Obligatorio en quema)">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Biomasa Estimada (kg)</label>
                        <input type="number" step="50" name="kilos_o_volumen_estimado" class="form-control font-monospace" placeholder="Ej: 1200">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Observaciones Técnicas o Cuadrilla</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Cuadrilla de podadores, intensidad de aclareo de copas, desinfección de herramientas..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-toggle="collapse" data-bs-target="#formPoda">Cancelar</button>
                    <button type="submit" class="btn btn-warning px-4 py-2 fw-bold text-dark shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Registro de Poda
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FILTROS CONTEXTUALES -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm d-print-none">
        <form action="<?= APP_URL ?>/podas" method="GET" class="row g-2 align-items-center">
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
                <label class="form-label small text-muted mb-1">Tipo de Poda:</label>
                <select name="op" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todas las operaciones --</option>
                    <option value="poda_mantenimiento" <?= $filtroOp === 'poda_mantenimiento' ? 'selected' : '' ?>>Poda de producción</option>
                    <option value="poda_formacion" <?= $filtroOp === 'poda_formacion' ? 'selected' : '' ?>>Poda de formación</option>
                    <option value="poda_rejuvenecimiento" <?= $filtroOp === 'poda_rejuvenecimiento' ? 'selected' : '' ?>>Rejuvenecimiento</option>
                    <option value="poda_verde" <?= $filtroOp === 'poda_verde' ? 'selected' : '' ?>>Poda en verde</option>
                    <option value="tala" <?= $filtroOp === 'tala' ? 'selected' : '' ?>>Tala</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Destino Restos:</label>
                <select name="residuos" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los destinos --</option>
                    <option value="triturado_acolchado" <?= $filtroResiduos === 'triturado_acolchado' ? 'selected' : '' ?>>Triturado</option>
                    <option value="retirada_reciclaje_biomasa" <?= $filtroResiduos === 'retirada_reciclaje_biomasa' ? 'selected' : '' ?>>Biomasa / Leña</option>
                    <option value="quema_autorizada" <?= $filtroResiduos === 'quema_autorizada' ? 'selected' : '' ?>>Quema autorizada</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Desde Fecha:</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroDesde) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
                <?php if ($filtroFinca || $filtroOp || $filtroResiduos || $filtroDesde || $filtroHasta): ?>
                    <a href="<?= APP_URL ?>/podas" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ÁREA DE INFORME OFICIAL MAQUETADA PARA A4 -->
    <div class="card card-ag shadow-sm border-0 mb-4" id="area-informe-podas">
        
        <!-- Toolbar Universal de Exportación -->
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 d-print-none">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-contract text-warning fs-5"></i>
                <h6 class="fw-bold mb-0 text-primary">Informe Oficial de Podas y Gestión de Biomasa (<?= count($podas) ?> intervenciones)</h6>
            </div>
            <div>
                <?php 
                    $reportTitle = "Informe Oficial de Podas y Gestion de Residuos Vegetales";
                    $reportFileName = "informe_podas_biomasa_" . date('Ymd_His');
                    $reportContainerId = "area-informe-podas";
                    require VIEWS_PATH . '/layouts/toolbar_informes.php';
                ?>
            </div>
        </div>

        <div class="card-body p-4">
            
            <!-- Encabezado A4 Oficial -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div>
                    <h4 class="fw-bold text-primary mb-1">
                        <i class="fa-solid fa-scissors me-2 text-warning"></i><?= htmlspecialchars(Auth::user()['nombre_propietario'] ?: APP_NAME) ?>
                    </h4>
                    <div class="small text-muted">Cuaderno de Explotación Agraria - Trazabilidad de Poda y Residuos Vegetales</div>
                    <div class="small text-secondary">Fecha de emisión: <strong><?= date('d/m/Y H:i') ?></strong> | Responsable: <?= htmlspecialchars($currentUser['nombre_completo']) ?></div>
                </div>
                <div class="text-end">
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill">GESTIÓN DE BIOMASA</span>
                    <div class="small text-muted mt-1 font-monospace">Eco-Regímenes PAC / Economía Circular</div>
                </div>
            </div>

            <!-- Resumen de Métricas -->
            <div class="row g-2 mb-3 text-center">
                <div class="col-6">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Total de Operaciones Registradas</span>
                        <strong class="fs-5 text-dark font-monospace"><?= count($podas) ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Biomasa / Restos Estimados</span>
                        <strong class="fs-5 text-success font-monospace"><?= number_format($totalKgBiomasa, 0, ',', '.') ?> kg</strong>
                    </div>
                </div>
            </div>

            <!-- Tabla de Podas Maquetada para A4 -->
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light small text-uppercase fw-bold text-secondary">
                        <tr>
                            <th>Fecha</th>
                            <th>Finca / Parcela</th>
                            <th>Cultivo</th>
                            <th>Tipo de Operación</th>
                            <th>Destino de Restos / Valorización</th>
                            <th>Permiso Quema</th>
                            <th class="text-center">Biomasa</th>
                            <th>Observaciones Técnicas</th>
                            <th class="d-print-none text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($podas)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-scissors fa-2x mb-2 text-secondary opacity-50"></i>
                                    <p class="mb-0">No se encontraron podas registradas con los filtros indicados.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($podas as $p): ?>
                                <tr>
                                    <td class="font-monospace fw-semibold"><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['nombre_finca']) ?></strong>
                                        <div class="small text-muted font-monospace"><?= htmlspecialchars($p['identificador_cuadro']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($p['nombre_comun']) ?></td>
                                    <td>
                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning-subtle text-capitalize">
                                            <?= str_replace('_', ' ', $p['tipo_operacion']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">
                                            <i class="fa-solid fa-recycle me-1"></i><?= str_replace('_', ' ', $p['destino_residuos']) ?>
                                        </span>
                                    </td>
                                    <td class="font-monospace small"><?= htmlspecialchars($p['numero_permiso_quema'] ?: '-') ?></td>
                                    <td class="text-center font-monospace">
                                        <?= $p['kilos_o_volumen_estimado'] ? number_format($p['kilos_o_volumen_estimado'], 0, ',', '.') . ' kg' : '-' ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($p['observaciones'] ?: '-') ?></td>
                                    <td class="d-print-none text-end">
                                        <form action="<?= APP_URL ?>/podas" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este registro de poda?');">
                                            <input type="hidden" name="action" value="borrar_poda">
                                            <input type="hidden" name="id_poda" value="<?= $p['id_poda'] ?>">
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
                <span>Dietario Agrícola Digital - Control de Poda y Biomasa</span>
                <span>Documento oficial de trazabilidad para Eco-Regímenes y condicionalidad</span>
            </div>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
