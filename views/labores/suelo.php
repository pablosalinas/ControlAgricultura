<?php
$pageTitle = "Laboreo del Suelo y Desbroce - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR ACCIONES (GUARDAR LABOR, BORRADO LÓGICO)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'guardar_labor' || isset($_POST['guardar_labor'])) {
        try {
            $idFinca = (int)$_POST['id_finca'];
            $idPlantacion = !empty($_POST['id_plantacion']) ? (int)$_POST['id_plantacion'] : null;
            $fecha = $_POST['fecha_labor'];
            $tipo = $_POST['tipo_labor'];
            $apero = trim(isset($_POST['apero_maquinaria']) ? $_POST['apero_maquinaria'] : '');
            $horas = !empty($_POST['horas_trabajo']) ? (float)$_POST['horas_trabajo'] : null;
            $operario = trim(isset($_POST['operario']) ? $_POST['operario'] : $currentUser['nombre_completo']);
            $obs = trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : '');

            // Si no se eligió plantación específica pero la finca tiene plantaciones, vincular la primera por defecto para compatibilidad
            if (!$idPlantacion) {
                $stmtCheckP = $db->prepare("SELECT id_plantacion FROM plantaciones WHERE id_finca = :finca LIMIT 1");
                $stmtCheckP->execute(array(':finca' => $idFinca));
                $firstP = $stmtCheckP->fetchColumn();
                if ($firstP) {
                    $idPlantacion = (int)$firstP;
                }
            }

            $stmt = $db->prepare("
                INSERT INTO labores_suelo (id_finca, id_plantacion, fecha_labor, tipo_labor, apero_maquinaria, horas_trabajo, operario, observaciones, creado_por_id, activo)
                VALUES (:finca, :plant, :fecha, :tipo, :apero, :horas, :operario, :obs, :user, 1)
            ");
            $stmt->execute(array(
                ':finca'    => $idFinca,
                ':plant'    => $idPlantacion,
                ':fecha'    => $fecha,
                ':tipo'     => $tipo,
                ':apero'    => $apero,
                ':horas'    => $horas,
                ':operario' => $operario,
                ':obs'      => $obs,
                ':user'     => $currentUser['id']
            ));
            $mensaje = "Labor de suelo / desbroce registrada correctamente en el cuaderno.";
        } catch (Exception $e) {
            $error = "Error al registrar la labor: " . $e->getMessage();
        }
    } elseif ($action === 'borrar_labor') {
        try {
            $idLabor = (int)$_POST['id_labor'];
            $db->prepare("UPDATE labores_suelo SET activo = 0 WHERE id_labor = :id")->execute(array(':id' => $idLabor));
            $mensaje = "Labor eliminada correctamente (borrado lógico seguro).";
        } catch (Exception $e) {
            $error = "Error al eliminar labor: " . $e->getMessage();
        }
    }
}

// Filtros para listado e informe A4
$filtroFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$filtroTipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$filtroDesde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$filtroHasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

// Consultar fincas asociadas a la explotación del usuario actual
$sqlFincas = "SELECT id_finca, nombre_finca, nombre_paraje, superficie_ha FROM fincas WHERE activo = 1";
if ($tenantId && !$isSuperAdmin) {
    $sqlFincas .= " AND id_propietario = " . (int)$tenantId;
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$fincasExplotacion = $db->query($sqlFincas)->fetchAll();

// Consultar plantaciones de la explotación
$sqlPlant = "
    SELECT p.id_plantacion, p.id_finca, p.identificador_cuadro, p.superficie_ocupada_ha,
           f.nombre_finca, c.nombre_comun
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

// Agrupar plantaciones por finca en formato JSON para el selector encadenado
$plantacionesPorFinca = array();
foreach ($plantaciones as $p) {
    $plantacionesPorFinca[$p['id_finca']][] = array(
        'id_plantacion' => $p['id_plantacion'],
        'texto' => $p['nombre_comun'] . ' (' . $p['identificador_cuadro'] . ' - ' . number_format($p['superficie_ocupada_ha'], 2) . ' ha)'
    );
}

// Consultar labores con filtros contextuales para el informe A4
$sqlLabores = "
    SELECT l.*, 
           COALESCE(f.nombre_finca, f2.nombre_finca) AS nombre_finca,
           COALESCE(f.id_finca, f2.id_finca) AS id_finca,
           c.nombre_comun, p.identificador_cuadro
    FROM labores_suelo l
    LEFT JOIN plantaciones p ON l.id_plantacion = p.id_plantacion
    LEFT JOIN fincas f ON l.id_finca = f.id_finca
    LEFT JOIN fincas f2 ON p.id_finca = f2.id_finca
    LEFT JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE (l.activo = 1 OR l.activo IS NULL)
";
$params = array();

if ($tenantId && !$isSuperAdmin) {
    $sqlLabores .= " AND (f.id_propietario = :tenant OR f2.id_propietario = :tenant2)";
    $params[':tenant'] = (int)$tenantId;
    $params[':tenant2'] = (int)$tenantId;
}
if ($filtroFinca > 0) {
    $sqlLabores .= " AND (l.id_finca = :finca OR p.id_finca = :finca2)";
    $params[':finca'] = $filtroFinca;
    $params[':finca2'] = $filtroFinca;
}
if (!empty($filtroTipo)) {
    $sqlLabores .= " AND l.tipo_labor = :tipo";
    $params[':tipo'] = $filtroTipo;
}
if (!empty($filtroDesde)) {
    $sqlLabores .= " AND l.fecha_labor >= :desde";
    $params[':desde'] = $filtroDesde;
}
if (!empty($filtroHasta)) {
    $sqlLabores .= " AND l.fecha_labor <= :hasta";
    $params[':hasta'] = $filtroHasta;
}

$sqlLabores .= " ORDER BY l.fecha_labor DESC, l.id_labor DESC";
$stmtL = $db->prepare($sqlLabores);
$stmtL->execute($params);
$labores = $stmtL->fetchAll();

// Totales para métricas del informe
$totalHorasMaquinaria = 0;
foreach ($labores as $lb) {
    $totalHorasMaquinaria += (float)$lb['horas_trabajo'];
}
?>

<div class="container py-4">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #475569);">
                <i class="fa-solid fa-tractor fs-3 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Laboreo y Manejo del Suelo / Desbroce</h2>
                <p class="text-muted mb-0 small">Control mecánico de adventicias, arado, subsolado, grada, triturado de malezas y laboreo de conservación</p>
            </div>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formLabor">
            <i class="fa-solid fa-plus me-1"></i> Registrar Nueva Labor
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

    <!-- FORMULARIO DE NUEVA LABOR CON SELECCIÓN DE FINCA DE LA EXPLOTACIÓN -->
    <div class="collapse mb-4" id="formLabor">
        <div class="card card-ag p-4 border-top border-4 border-secondary shadow-sm">
            <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-tractor me-2"></i>Nueva Labor de Terreno o Desbroce</h5>
            <form action="<?= APP_URL ?>/labores" method="POST">
                <input type="hidden" name="action" value="guardar_labor">
                
                <div class="row g-3 mb-3">
                    <!-- Selector de Finca de la Explotación -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-primary">
                            <i class="fa-solid fa-draw-polygon me-1"></i>Finca de la Explotación *
                        </label>
                        <select name="id_finca" id="id_finca_labor" class="form-select fw-semibold" required onchange="actualizarParcelasPorFinca()">
                            <option value="">-- Seleccionar Finca --</option>
                            <?php foreach ($fincasExplotacion as $fe): ?>
                                <option value="<?= $fe['id_finca'] ?>">
                                    <?= htmlspecialchars($fe['nombre_finca']) ?> (<?= htmlspecialchars($fe['nombre_paraje'] ?: 'Paraje') ?> - <?= number_format($fe['superficie_ha'], 2) ?> ha)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selector Encadenado de Parcela / Plantación -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-secondary">
                            <i class="fa-solid fa-cubes-stacked me-1"></i>Parcela / Cultivo (Opcional)
                        </label>
                        <select name="id_plantacion" id="id_plantacion_labor" class="form-select">
                            <option value="">-- Toda la Finca / Suelo General --</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Fecha de Ejecución *</label>
                        <input type="date" name="fecha_labor" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Tipo de Labor Realizada *</label>
                        <select name="tipo_labor" class="form-select fw-semibold" required>
                            <option value="desbroce">🌿 Desbroce mecánico de adventicias / maleza</option>
                            <option value="arado">🚜 Arado de vertedera / Chisel</option>
                            <option value="subsolado">⛏️ Subsolado / Descompactación profunda</option>
                            <option value="fresado">⚙️ Fresado / Rotovator</option>
                            <option value="grada">🔘 Grada de discos</option>
                            <option value="cultivador">🌾 Cultivador / Semichisel</option>
                            <option value="eliminacion_malas_hierbas">🌱 Eliminación mecánica de hierba en líneas/ruedos</option>
                            <option value="otro">Otro laboreo de suelo</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small">Apero / Tractor Empleado</label>
                        <input type="text" name="apero_maquinaria" class="form-control" placeholder="Ej: Tractor John Deere 120CV con desbrozadora de martillos...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Horas de Trabajo</label>
                        <input type="number" step="0.25" name="horas_trabajo" class="form-control font-monospace" placeholder="Ej: 4.5">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Operario / Conductor</label>
                        <input type="text" name="operario" class="form-control" value="<?= htmlspecialchars($currentUser['nombre_completo']) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small">Observaciones Técnicas</label>
                        <input type="text" name="observaciones" class="form-control" placeholder="Profundidad de labor (cm), tempero del terreno, velocidad de avance...">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-toggle="collapse" data-bs-target="#formLabor">Cancelar</button>
                    <button type="submit" class="btn btn-secondary px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Labor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FILTROS CONTEXTUALES -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm d-print-none">
        <form action="<?= APP_URL ?>/labores" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Filtrar por Finca:</label>
                <select name="finca_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Todas las Fincas --</option>
                    <?php foreach ($fincasExplotacion as $fl): ?>
                        <option value="<?= $fl['id_finca'] ?>" <?= ($filtroFinca === (int)$fl['id_finca']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fl['nombre_finca']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Tipo de Labor:</label>
                <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los tipos --</option>
                    <option value="desbroce" <?= $filtroTipo === 'desbroce' ? 'selected' : '' ?>>Desbroce mecánico</option>
                    <option value="arado" <?= $filtroTipo === 'arado' ? 'selected' : '' ?>>Arado</option>
                    <option value="subsolado" <?= $filtroTipo === 'subsolado' ? 'selected' : '' ?>>Subsolado</option>
                    <option value="grada" <?= $filtroTipo === 'grada' ? 'selected' : '' ?>>Grada de discos</option>
                    <option value="cultivador" <?= $filtroTipo === 'cultivador' ? 'selected' : '' ?>>Cultivador</option>
                    <option value="fresado" <?= $filtroTipo === 'fresado' ? 'selected' : '' ?>>Fresado</option>
                    <option value="eliminacion_malas_hierbas" <?= $filtroTipo === 'eliminacion_malas_hierbas' ? 'selected' : '' ?>>Eliminación hierbas</option>
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
                <?php if ($filtroFinca || $filtroTipo || $filtroDesde || $filtroHasta): ?>
                    <a href="<?= APP_URL ?>/labores" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ÁREA DE INFORME OFICIAL MAQUETADA PARA A4 -->
    <div class="card card-ag shadow-sm border-0 mb-4" id="area-informe-labores">
        
        <!-- Toolbar Universal de Exportación -->
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 d-print-none">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-lines text-secondary fs-5"></i>
                <h6 class="fw-bold mb-0 text-primary">Informe Oficial de Laboreo y Desbroce (<?= count($labores) ?> operaciones)</h6>
            </div>
            <div>
                <?php 
                    $reportTitle = "Informe Oficial de Laboreo y Desbroce del Suelo";
                    $reportFileName = "informe_labores_suelo_" . date('Ymd_His');
                    $reportContainerId = "area-informe-labores";
                    require VIEWS_PATH . '/layouts/toolbar_informes.php';
                ?>
            </div>
        </div>

        <div class="card-body p-4">
            
            <!-- Encabezado A4 Oficial -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div>
                    <h4 class="fw-bold text-primary mb-1">
                        <i class="fa-solid fa-tractor me-2 text-secondary"></i><?= htmlspecialchars(Auth::user()['nombre_propietario'] ?: APP_NAME) ?>
                    </h4>
                    <div class="small text-muted">Cuaderno de Explotación Agraria - Registro Oficial de Laboreo y Mantenimiento de Suelos</div>
                    <div class="small text-secondary">Fecha de emisión: <strong><?= date('d/m/Y H:i') ?></strong> | Responsable: <?= htmlspecialchars($currentUser['nombre_completo']) ?></div>
                </div>
                <div class="text-end">
                    <span class="badge bg-secondary px-3 py-2 fs-6 rounded-pill">MANEJO DEL SUELO</span>
                    <div class="small text-muted mt-1 font-monospace">Condicionalidad PAC / Buenas Prácticas</div>
                </div>
            </div>

            <!-- Resumen de Métricas -->
            <div class="row g-2 mb-3 text-center">
                <div class="col-6">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Total de Labores Registradas</span>
                        <strong class="fs-5 text-dark font-monospace"><?= count($labores) ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 border rounded bg-light">
                        <span class="small text-muted d-block">Horas Totales de Maquinaria / Apero</span>
                        <strong class="fs-5 text-primary font-monospace"><?= number_format($totalHorasMaquinaria, 2, ',', '.') ?> h</strong>
                    </div>
                </div>
            </div>

            <!-- Tabla de Labores Maquetada para A4 -->
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light small text-uppercase fw-bold text-secondary">
                        <tr>
                            <th>Fecha</th>
                            <th>Finca / Parcela</th>
                            <th>Tipo de Labor</th>
                            <th>Apero / Maquinaria</th>
                            <th class="text-center">Horas</th>
                            <th>Operario</th>
                            <th>Observaciones Técnicas</th>
                            <th class="d-print-none text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($labores)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-tractor fa-2x mb-2 text-secondary opacity-50"></i>
                                    <p class="mb-0">No se encontraron labores con los filtros indicados.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($labores as $l): ?>
                                <tr>
                                    <td class="font-monospace fw-semibold"><?= date('d/m/Y', strtotime($l['fecha_labor'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($l['nombre_finca']) ?></strong>
                                        <?php if ($l['nombre_comun']): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($l['nombre_comun']) ?> (<?= htmlspecialchars($l['identificador_cuadro']) ?>)</div>
                                        <?php else: ?>
                                            <div class="small text-secondary fst-italic">Toda la finca / Suelo</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill text-capitalize">
                                            <?= str_replace('_', ' ', $l['tipo_labor']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($l['apero_maquinaria'] ?: '-') ?></td>
                                    <td class="text-center font-monospace fw-bold text-primary">
                                        <?= $l['horas_trabajo'] ? number_format($l['horas_trabajo'], 2) . ' h' : '-' ?>
                                    </td>
                                    <td><?= htmlspecialchars($l['operario'] ?: '-') ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($l['observaciones'] ?: '-') ?></td>
                                    <td class="d-print-none text-end">
                                        <form action="<?= APP_URL ?>/labores" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar esta labor?');">
                                            <input type="hidden" name="action" value="borrar_labor">
                                            <input type="hidden" name="id_labor" value="<?= $l['id_labor'] ?>">
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
                <span>Dietario Agrícola Digital - Control de Laboreo</span>
                <span>Documento oficial de laboreo conforme a Buenas Prácticas Agrarias</span>
            </div>
        </div>
    </div>
</div>

<script>
const plantacionesData = <?= json_encode($plantacionesPorFinca) ?>;

function actualizarParcelasPorFinca() {
    const fincaId = document.getElementById('id_finca_labor').value;
    const selPlantacion = document.getElementById('id_plantacion_labor');
    selPlantacion.innerHTML = '<option value="">-- Toda la Finca / Suelo General --</option>';

    if (fincaId && plantacionesData[fincaId]) {
        plantacionesData[fincaId].forEach(function(p) {
            const opt = document.createElement('option');
            opt.value = p.id_plantacion;
            opt.textContent = p.texto;
            selPlantacion.appendChild(opt);
        });
    }
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
