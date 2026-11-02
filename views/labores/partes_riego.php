<?php
$pageTitle = "Partes y Consultas de Solicitud de Riego - " . APP_NAME;
require_once SRC_PATH . '/Services/Mailer.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

// Procesar peticiones AJAX de actualización de estado y turno asignado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'actualizar_turno_estado') {
        $id = (int)(isset($_POST['id_solicitud']) ? $_POST['id_solicitud'] : 0);
        $estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'pendiente';
        $fechaAsig = !empty($_POST['fecha_riego_asignado']) ? $_POST['fecha_riego_asignado'] : null;
        $horaAsig = !empty($_POST['hora_riego_asignado']) ? $_POST['hora_riego_asignado'] : null;
        $notasAdmin = isset($_POST['notas_admin']) ? trim($_POST['notas_admin']) : '';

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Identificador de solicitud no válido.']);
            exit;
        }

        $sqlVal = "SELECT id_solicitud FROM solicitudes_riego WHERE id_solicitud = :id";
        if ($tenantId && !$isSuperAdmin) {
            $sqlVal .= " AND id_propietario = " . (int)$tenantId;
        }
        $check = $db->prepare($sqlVal);
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para modificar esta solicitud o no existe.']);
            exit;
        }

        $sqlUpd = "
            UPDATE solicitudes_riego 
            SET estado = :estado, 
                fecha_riego_asignado = :fecha_asig, 
                hora_riego_asignado = :hora_asig, 
                notas_admin = :notas
            WHERE id_solicitud = :id
        ";
        $stmtUpd = $db->prepare($sqlUpd);
        $stmtUpd->execute([
            ':estado' => $estado,
            ':fecha_asig' => $fechaAsig,
            ':hora_asig' => $horaAsig,
            ':notas' => $notasAdmin,
            ':id' => $id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Estado y turno de riego actualizados correctamente.',
            'estado' => $estado,
            'fecha_asignada' => $fechaAsig ? date('d/m/Y', strtotime($fechaAsig)) : '-',
            'hora_asignada' => $horaAsig ? date('H:i', strtotime($horaAsig)) : '-'
        ]);
        exit;
    }

    if ($_POST['action'] === 'eliminar_solicitud') {
        $id = (int)(isset($_POST['id_solicitud']) ? $_POST['id_solicitud'] : 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $sqlDel = "DELETE FROM solicitudes_riego WHERE id_solicitud = :id";
        if ($tenantId && !$isSuperAdmin) {
            $sqlDel .= " AND id_propietario = " . (int)$tenantId;
        }
        $stmtDel = $db->prepare($sqlDel);
        $stmtDel->execute([':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Parte de solicitud eliminado con éxito.']);
        exit;
    }
}

require_once VIEWS_PATH . '/layouts/header.php';

// Filtros de búsqueda
$fFechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fFechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
$fSocio = isset($_GET['socio']) ? trim($_GET['socio']) : '';
$fTipo = isset($_GET['tipo_riego']) ? trim($_GET['tipo_riego']) : '';
$fEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$fComunidad = (int)(isset($_GET['id_comunidad']) ? $_GET['id_comunidad'] : 0);

// Construcción de consulta filtrada
$where = [];
$params = [];

if ($tenantId && !$isSuperAdmin) {
    $where[] = "s.id_propietario = :tenant";
    $params[':tenant'] = (int)$tenantId;
}

if (!empty($fFechaDesde)) {
    $where[] = "s.fecha_solicitud >= :f_desde";
    $params[':f_desde'] = $fFechaDesde;
}

if (!empty($fFechaHasta)) {
    $where[] = "s.fecha_solicitud <= :f_hasta";
    $params[':f_hasta'] = $fFechaHasta;
}

if (!empty($fSocio)) {
    $where[] = "(s.num_regante LIKE :socio OR s.titular LIKE :socio)";
    $params[':socio'] = '%' . $fSocio . '%';
}

if (!empty($fTipo)) {
    $where[] = "s.tipo_riego = :tipo";
    $params[':tipo'] = $fTipo;
}

if (!empty($fEstado)) {
    $where[] = "s.estado = :estado";
    $params[':estado'] = $fEstado;
}

if ($fComunidad > 0) {
    $where[] = "s.id_comunidad = :comunidad";
    $params[':comunidad'] = $fComunidad;
}

$sqlWhere = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : "";

$sqlList = "
    SELECT s.*, c.nombre_comunidad
    FROM solicitudes_riego s
    LEFT JOIN comunidades_regantes c ON s.id_comunidad = c.id_comunidad
    {$sqlWhere}
    ORDER BY s.fecha_solicitud DESC, s.id_solicitud DESC
";

$stmt = $db->prepare($sqlList);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

// Totales y acumuladores para totalización
$totalPartes = count($solicitudes);
$totalHoras = 0.0;
$totalImporte = 0.0;
$countPendientes = 0;
$countAsignados = 0;
$countRegados = 0;
$countDescartados = 0;
$countEnviadosEmail = 0;

foreach ($solicitudes as $row) {
    $totalHoras += (float)$row['horas_solicitadas'];
    $totalImporte += (float)$row['coste_estimado'];
    if ($row['estado'] === 'pendiente') $countPendientes++;
    elseif ($row['estado'] === 'asignado') $countAsignados++;
    elseif ($row['estado'] === 'regado') $countRegados++;
    elseif ($row['estado'] === 'descartado') $countDescartados++;

    if (!empty($row['enviado_email'])) $countEnviadosEmail++;
}

// Maestros para filtros
$comunidades = $db->query("SELECT id_comunidad, nombre_comunidad FROM comunidades_regantes WHERE activo = 1 ORDER BY es_predeterminada DESC, nombre_comunidad ASC")->fetchAll();
$regantes = $db->query("SELECT id_regante, num_regante, nombre_completo FROM regantes WHERE activo = 1 ORDER BY num_regante ASC")->fetchAll();
?>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Encabezado de Navegación y Acciones -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fs-6">
                    <i class="fa-solid fa-water me-1"></i> Control de Riegos y Tandas
                </span>
                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fs-6 d-none d-sm-inline-block">
                    <i class="fa-solid fa-list-check me-1"></i> Partes Oficiales Guardados
                </span>
            </div>
            <h1 class="h2 fw-bold text-dark mb-0">Partes y Consultas de Solicitud de Riego</h1>
            <p class="text-muted small mb-0">Registro histórico, asignación de turnos por la comunidad, estado de ejecución y totalización.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= APP_URL ?>/solicitud-riego" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-file-circle-plus me-1"></i> Nueva Solicitud (A4)
            </a>
            <button type="button" class="btn btn-outline-secondary fw-semibold shadow-sm" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Imprimir Listado
            </button>
        </div>
    </div>

    <!-- TARJETAS RESUMEN DE TOTALES Y ESTADOS (TOTALIZACIÓN) -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-sm-4 col-xl-2">
            <div class="card card-custom p-3 border-start border-4 border-primary h-100">
                <div class="text-muted small fw-semibold text-truncate">Total Partes</div>
                <div class="fs-4 fw-bold text-dark mt-1"><?= $totalPartes ?></div>
                <div class="small text-muted text-truncate"><?= $countEnviadosEmail ?> enviados por email</div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-xl-2">
            <div class="card card-custom p-3 border-start border-4 border-info h-100">
                <div class="text-muted small fw-semibold text-truncate">Horas Solicitadas</div>
                <div class="fs-4 fw-bold text-primary font-monospace mt-1"><?= number_format($totalHoras, 2) ?> h</div>
                <div class="small text-muted text-truncate">Volumen filtrado</div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-xl-2">
            <div class="card card-custom p-3 border-start border-4 border-success h-100">
                <div class="text-muted small fw-semibold text-truncate">Importe Total</div>
                <div class="fs-4 fw-bold text-success font-monospace mt-1"><?= number_format($totalImporte, 2) ?> €</div>
                <div class="small text-muted text-truncate">Coste estimado</div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-xl-2">
            <div class="card card-custom p-3 border-start border-4 border-warning h-100">
                <div class="text-muted small fw-semibold text-truncate">Pendientes</div>
                <div class="fs-4 fw-bold text-warning mt-1"><?= $countPendientes ?></div>
                <div class="small text-muted text-truncate">A la espera de turno</div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-xl-2">
            <div class="card card-custom p-3 border-start border-4 border-primary h-100">
                <div class="text-muted small fw-semibold text-truncate">Turno Asignado</div>
                <div class="fs-4 fw-bold text-info mt-1"><?= $countAsignados ?></div>
                <div class="small text-muted text-truncate">Con fecha/hora fija</div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-xl-2">
            <div class="card card-custom p-3 border-start border-4 border-success h-100">
                <div class="text-muted small fw-semibold text-truncate">Regados / OK</div>
                <div class="fs-4 fw-bold text-success mt-1"><?= $countRegados ?></div>
                <div class="small text-muted text-truncate"><?= $countDescartados ?> descartados</div>
            </div>
        </div>
    </div>

    <!-- PANEL DE FILTROS AVANZADOS -->
    <div class="card card-custom p-3 p-md-4 mb-4 border shadow-sm">
        <form method="GET" action="<?= APP_URL ?>/partes-riego" class="row g-2 g-md-3 align-items-end">
            <!-- Fecha Desde -->
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small fw-bold text-muted mb-1"><i class="fa-solid fa-calendar me-1"></i>Fecha Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($fFechaDesde) ?>">
            </div>

            <!-- Fecha Hasta -->
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small fw-bold text-muted mb-1"><i class="fa-solid fa-calendar me-1"></i>Fecha Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($fFechaHasta) ?>">
            </div>

            <!-- Socio o Titular -->
            <div class="col-12 col-md-3 col-xl-2">
                <label class="form-label small fw-bold text-muted mb-1"><i class="fa-solid fa-user me-1"></i>Socio / Titular</label>
                <input type="text" name="socio" class="form-control form-control-sm" placeholder="Ej: A1188 o Pablo..." value="<?= htmlspecialchars($fSocio) ?>">
            </div>

            <!-- Tipo de Riego -->
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small fw-bold text-muted mb-1"><i class="fa-solid fa-droplet me-1"></i>Modalidad Riego</label>
                <select name="tipo_riego" class="form-select form-select-sm">
                    <option value="">-- Todas las modalidades --</option>
                    <option value="tanda" <?= $fTipo === 'tanda' ? 'selected' : '' ?>>Tanda (Propiedad)</option>
                    <option value="comunitaria" <?= $fTipo === 'comunitaria' ? 'selected' : '' ?>>Comunitaria Ordinaria</option>
                    <option value="comunitaria_hortalizas" <?= $fTipo === 'comunitaria_hortalizas' ? 'selected' : '' ?>>Comunitaria Hortalizas</option>
                </select>
            </div>

            <!-- Estado -->
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small fw-bold text-muted mb-1"><i class="fa-solid fa-flag me-1"></i>Estado del Parte</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">-- Todos los estados --</option>
                    <option value="pendiente" <?= $fEstado === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                    <option value="asignado" <?= $fEstado === 'asignado' ? 'selected' : '' ?>>📅 Turno Asignado</option>
                    <option value="regado" <?= $fEstado === 'regado' ? 'selected' : '' ?>>💧 Regado</option>
                    <option value="descartado" <?= $fEstado === 'descartado' ? 'selected' : '' ?>>❌ Descartado</option>
                </select>
            </div>

            <!-- Botones de Acción de Filtro -->
            <div class="col-12 col-md-6 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm fw-bold w-100">
                    <i class="fa-solid fa-filter me-1"></i> Filtrar
                </button>
                <a href="<?= APP_URL ?>/partes-riego" class="btn btn-outline-secondary btn-sm" title="Limpiar todos los filtros">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- TABLA DE LISTADO DE PARTES DE RIEGO (TOTALIZACIÓN Y RESPONSIVE) -->
    <div class="card card-custom shadow-sm overflow-hidden border">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0" id="tablaPartes">
                <thead class="table-dark text-nowrap">
                    <tr>
                        <th style="width: 70px;">Papeleta</th>
                        <th>Fecha Solicitud</th>
                        <th>Socio Regante</th>
                        <th>Modalidad Riego</th>
                        <th class="text-end">Horas</th>
                        <th class="text-end">Importe (€)</th>
                        <th>Paraje / Parada</th>
                        <th class="text-center">Estado Parte</th>
                        <th>Turno Asignado por Comunidad</th>
                        <th class="text-center">Email</th>
                        <th class="text-end" style="min-width: 150px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($solicitudes)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-water-ladder fs-1 d-block mb-3 opacity-25"></i>
                                <h5>No se encontraron partes de riego con los filtros actuales</h5>
                                <p class="small mb-3">Puedes registrar una nueva solicitud oficial de agua para la comunidad o limpiar los filtros.</p>
                                <a href="<?= APP_URL ?>/solicitud-riego" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-plus-circle me-1"></i> Crear Nueva Solicitud
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($solicitudes as $s): ?>
                            <?php
                            $badgeModalidad = '';
                            if ($s['tipo_riego'] === 'tanda') {
                                $badgeModalidad = '<span class="badge bg-secondary font-monospace"><i class="fa-solid fa-hourglass-half me-1"></i>TANDA</span>';
                            } elseif ($s['tipo_riego'] === 'comunitaria_hortalizas') {
                                $badgeModalidad = '<span class="badge bg-success font-monospace"><i class="fa-solid fa-seedling me-1"></i>HORTALIZAS</span>';
                            } else {
                                $badgeModalidad = '<span class="badge bg-primary font-monospace"><i class="fa-solid fa-droplet me-1"></i>COMUNITARIA</span>';
                            }

                            $badgeEstado = '';
                            if ($s['estado'] === 'regado') {
                                $badgeEstado = '<span class="badge bg-success p-2"><i class="fa-solid fa-check-double me-1"></i>REGADO</span>';
                            } elseif ($s['estado'] === 'asignado') {
                                $badgeEstado = '<span class="badge bg-info text-dark p-2"><i class="fa-solid fa-calendar-check me-1"></i>ASIGNADO</span>';
                            } elseif ($s['estado'] === 'descartado') {
                                $badgeEstado = '<span class="badge bg-danger p-2"><i class="fa-solid fa-ban me-1"></i>DESCARTADO</span>';
                            } else {
                                $badgeEstado = '<span class="badge bg-warning text-dark p-2"><i class="fa-solid fa-clock me-1"></i>PENDIENTE</span>';
                            }

                            $turnoStr = '<span class="text-muted fst-italic small">Sin asignar</span>';
                            if (!empty($s['fecha_riego_asignado'])) {
                                $horaText = !empty($s['hora_riego_asignado']) ? (' a las ' . date('H:i', strtotime($s['hora_riego_asignado'])) . ' h') : '';
                                $turnoStr = '<strong class="text-primary font-monospace"><i class="fa-regular fa-calendar-days me-1"></i>' . date('d/m/Y', strtotime($s['fecha_riego_asignado'])) . $horaText . '</strong>';
                            }
                            ?>
                            <tr id="fila-solicitud-<?= $s['id_solicitud'] ?>">
                                <!-- ID / Papeleta -->
                                <td class="fw-bold font-monospace text-muted">
                                    #<?= str_pad($s['id_solicitud'], 4, '0', STR_PAD_LEFT) ?>
                                </td>

                                <!-- Fecha Solicitud -->
                                <td class="font-monospace text-nowrap">
                                    <?= date('d/m/Y', strtotime($s['fecha_solicitud'])) ?>
                                </td>

                                <!-- Socio Regante & Titular -->
                                <td>
                                    <div class="fw-bold font-monospace text-dark"><?= htmlspecialchars($s['num_regante']) ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 170px;"><?= htmlspecialchars($s['titular']) ?></div>
                                </td>

                                <!-- Modalidad Riego -->
                                <td class="text-nowrap">
                                    <?= $badgeModalidad ?>
                                </td>

                                <!-- Horas -->
                                <td class="text-end font-monospace fw-bold fs-6 text-primary">
                                    <?= number_format($s['horas_solicitadas'], 2) ?> h
                                </td>

                                <!-- Importe -->
                                <td class="text-end font-monospace fw-bold text-success">
                                    <?= number_format($s['coste_estimado'], 2) ?> €
                                </td>

                                <!-- Paraje / Parada / Tablón -->
                                <td class="small">
                                    <div><strong><?= htmlspecialchars($s['paraje'] ?: 'Sin paraje') ?></strong></div>
                                    <div class="text-muted text-truncate" style="max-width: 160px;"><?= htmlspecialchars($s['parada'] ?: '') ?> <?= $s['tablon'] ? (' • Tablón ' . htmlspecialchars($s['tablon'])) : '' ?></div>
                                </td>

                                <!-- Estado Parte -->
                                <td class="text-center text-nowrap" id="badge-container-<?= $s['id_solicitud'] ?>">
                                    <?= $badgeEstado ?>
                                </td>

                                <!-- Turno Asignado -->
                                <td id="turno-container-<?= $s['id_solicitud'] ?>">
                                    <?= $turnoStr ?>
                                    <?php if (!empty($s['notas_admin'])): ?>
                                        <div class="small text-muted fst-italic text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($s['notas_admin']) ?>">
                                            <i class="fa-solid fa-circle-info me-1 text-info"></i><?= htmlspecialchars($s['notas_admin']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Email de envío -->
                                <td class="text-center">
                                    <?php if (!empty($s['enviado_email'])): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1 px-2" title="Enviado a <?= htmlspecialchars($s['email_destino'] ?: 'la comunidad') ?> el <?= !empty($s['fecha_envio_email']) ? date('d/m/Y H:i', strtotime($s['fecha_envio_email'])) : '' ?>">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Enviado
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border py-1 px-2">
                                            No enviado
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Acciones -->
                                <td class="text-end text-nowrap">
                                    <!-- Botón Marcar Estado y Asignar Turno (Modal) -->
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="abrirModalTurno(<?= htmlspecialchars(json_encode([
                                            'id' => $s['id_solicitud'],
                                            'num_regante' => $s['num_regante'],
                                            'titular' => $s['titular'],
                                            'horas' => $s['horas_solicitadas'],
                                            'tipo' => $s['tipo_riego'],
                                            'estado' => $s['estado'],
                                            'fecha_asig' => $s['fecha_riego_asignado'],
                                            'hora_asig' => $s['hora_riego_asignado'] ? substr($s['hora_riego_asignado'], 0, 5) : '',
                                            'notas' => $s['notas_admin']
                                        ])) ?>)" title="Asignar día/hora y cambiar estado">
                                        <i class="fa-solid fa-calendar-check me-1"></i> Turno / Estado
                                    </button>

                                    <!-- Botón Ver / Cargar Papeleta Oficial A4 -->
                                    <a href="<?= APP_URL ?>/solicitud-riego?id=<?= $s['id_solicitud'] ?>" class="btn btn-sm btn-outline-info" title="Ver papeleta A4 oficial de esta solicitud">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>

                                    <!-- Botón Eliminar -->
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarSolicitud(<?= $s['id_solicitud'] ?>)" title="Eliminar este parte">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <!-- PIE DE TABLA CON TOTALIZACIÓN EXACTA SOLICITADA POR EL USUARIO -->
                <tfoot class="table-light fw-bold border-top border-2 border-dark">
                    <tr>
                        <td colspan="4" class="text-end text-uppercase text-dark py-3">
                            <i class="fa-solid fa-calculator me-1 text-primary"></i> TOTALES FILTRADOS:
                        </td>
                        <td class="text-end font-monospace text-primary fs-5 py-3">
                            <?= number_format($totalHoras, 2) ?> h
                        </td>
                        <td class="text-end font-monospace text-success fs-5 py-3">
                            <?= number_format($totalImporte, 2) ?> €
                        </td>
                        <td colspan="5" class="small text-muted py-3">
                            (Total: <strong><?= $totalPartes ?></strong> partes registrados • <strong><?= $countRegados ?></strong> regados • <strong><?= $countPendientes ?></strong> pendientes)
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

<!-- ============================================================================== -->
<!-- MODAL PARA GESTIÓN DE TURNO ASIGNADO POR LA COMUNIDAD Y ESTADO DEL PARTE       -->
<!-- ============================================================================== -->
<div class="modal fade" id="modalTurno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient text-white" style="background: linear-gradient(135deg, #091e3a 0%, #1e40af 100%);">
                <h5 class="modal-title fw-bold" id="modalTurnoTitulo">
                    <i class="fa-solid fa-calendar-check me-2"></i>Asignar Turno de Riego y Estado
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formTurno" onsubmit="guardarTurno(event)">
                <input type="hidden" id="modal_id_solicitud" name="id_solicitud" value="">

                <div class="modal-body p-4">
                    <!-- Resumen del parte seleccionado -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">Socio Regante:</span>
                            <span class="fw-bold font-monospace text-primary" id="modal_socio_txt">A1188</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">Titular:</span>
                            <span class="fw-semibold text-dark" id="modal_titular_txt">Pablo Salinas</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Horas Solicitadas:</span>
                            <span class="badge bg-primary font-monospace" id="modal_horas_txt">2.00 h</span>
                        </div>
                    </div>

                    <!-- Selector de Estado Oficial -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">
                            <i class="fa-solid fa-flag me-1 text-primary"></i>Estado Oficial de la Petición *
                        </label>
                        <select class="form-select fw-bold" id="modal_estado" name="estado" required>
                            <option value="pendiente">⏳ PENDIENTE (Enviada a la comunidad / En espera de turno)</option>
                            <option value="asignado">📅 TURNO ASIGNADO (Fecha y hora confirmadas por el guarda/comunidad)</option>
                            <option value="regado">💧 REGADO (Riego efectuado con éxito en parcela)</option>
                            <option value="descartado">❌ DESCARTADO (Cancelado por el socio o denegado por comunidad)</option>
                        </select>
                    </div>

                    <!-- Fecha y Hora de Riego Asignada por la Comunidad -->
                    <div class="row g-2 mb-3">
                        <div class="col-sm-7">
                            <label class="form-label fw-bold small text-dark">
                                <i class="fa-regular fa-calendar-days me-1 text-info"></i>Día Asignado por Comunidad
                            </label>
                            <input type="date" class="form-control font-monospace" id="modal_fecha_asig" name="fecha_riego_asignado">
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label fw-bold small text-dark">
                                <i class="fa-regular fa-clock me-1 text-info"></i>Hora Asignada
                            </label>
                            <input type="time" class="form-control font-monospace" id="modal_hora_asig" name="hora_riego_asignado" step="900">
                        </div>
                    </div>

                    <!-- Observaciones o Instrucciones del Guarda / Comunidad -->
                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-muted">
                            <i class="fa-solid fa-comment-dots me-1"></i>Observaciones de la Comunidad / Notas del Guarda
                        </label>
                        <textarea class="form-control" id="modal_notas" name="notas_admin" rows="2" placeholder="Ej: Turno de noche, abrir tajadera del brazal 4..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="btnGuardarTurno">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let modalTurnoInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('modalTurno');
    if (el) {
        modalTurnoInstance = new bootstrap.Modal(el);
    }
});

function abrirModalTurno(data) {
    document.getElementById('modal_id_solicitud').value = data.id;
    document.getElementById('modal_socio_txt').innerText = data.num_regante;
    document.getElementById('modal_titular_txt').innerText = data.titular;
    document.getElementById('modal_horas_txt').innerText = parseFloat(data.horas).toFixed(2) + ' h';

    document.getElementById('modal_estado').value = data.estado || 'pendiente';
    document.getElementById('modal_fecha_asig').value = data.fecha_asig || '';
    document.getElementById('modal_hora_asig').value = data.hora_asig || '';
    document.getElementById('modal_notas').value = data.notas || '';

    modalTurnoInstance.show();
}

async function guardarTurno(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGuardarTurno');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...';

    const form = document.getElementById('formTurno');
    const formData = new FormData(form);
    formData.append('action', 'actualizar_turno_estado');

    try {
        const resp = await fetch('<?= APP_URL ?>/partes-riego', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.success) {
            modalTurnoInstance.hide();
            // Recargar para actualizar los totales y filtros de forma limpia
            window.location.reload();
        } else {
            alert('Error: ' + res.message);
        }
    } catch (err) {
        alert('Fallo de conexión al actualizar turno: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios';
    }
}

async function eliminarSolicitud(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este parte de solicitud de riego? Esta acción no se puede deshacer.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'eliminar_solicitud');
    formData.append('id_solicitud', id);

    try {
        const resp = await fetch('<?= APP_URL ?>/partes-riego', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.success) {
            const fila = document.getElementById('fila-solicitud-' + id);
            if (fila) fila.remove();
            window.location.reload();
        } else {
            alert('Error: ' + res.message);
        }
    } catch (err) {
        alert('Fallo al eliminar solicitud: ' + err.message);
    }
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
