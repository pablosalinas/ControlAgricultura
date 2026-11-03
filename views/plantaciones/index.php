<?php
$pageTitle = "Plantaciones Activas - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR ACCIONES CRUD (EDITAR, BORRAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // EDITAR PLANTACIÓN
    if ($action === 'editar_plantacion') {
        $idPlantacion = (int)$_POST['id_plantacion'];
        $cuadro = trim(isset($_POST['identificador_cuadro']) ? $_POST['identificador_cuadro'] : '');
        $campana = (int)(isset($_POST['campana_ano']) ? $_POST['campana_ano'] : date('Y'));
        $superficie = (float)(isset($_POST['superficie_ocupada_ha']) ? $_POST['superficie_ocupada_ha'] : 0);
        $arboles = !empty($_POST['num_arboles_o_plantas']) ? (int)$_POST['num_arboles_o_plantas'] : null;
        $marco = trim(isset($_POST['marco_plantacion']) ? $_POST['marco_plantacion'] : '');
        $siembra = !empty($_POST['fecha_siembra']) ? $_POST['fecha_siembra'] : null;
        $transplante = !empty($_POST['fecha_transplante']) ? $_POST['fecha_transplante'] : null;
        $fin = !empty($_POST['fecha_estimada_fin']) ? $_POST['fecha_estimada_fin'] : null;
        $estado = isset($_POST['estado']) ? $_POST['estado'] : 'en_crecimiento';
        $notas = trim(isset($_POST['notas_cuaderno']) ? $_POST['notas_cuaderno'] : '');

        if (empty($cuadro) || $superficie <= 0) {
            $error = "El identificador de cuadro y una superficie válida son obligatorios.";
        } else {
            try {
                $stmtUpd = $db->prepare("
                    UPDATE plantaciones
                    SET identificador_cuadro = :cuadro, campana_ano = :camp,
                        superficie_ocupada_ha = :sup, num_arboles_o_plantas = :arb,
                        marco_plantacion = :marco, fecha_siembra = :siembra,
                        fecha_transplante = :transp, fecha_estimada_fin = :fin,
                        estado = :est, notas_cuaderno = :notas
                    WHERE id_plantacion = :id
                ");
                $stmtUpd->execute(array(
                    ':cuadro'  => $cuadro,
                    ':camp'    => $campana,
                    ':sup'     => $superficie,
                    ':arb'     => $arboles,
                    ':marco'   => $marco ?: null,
                    ':siembra' => $siembra,
                    ':transp'  => $transplante,
                    ':fin'     => $fin,
                    ':est'     => $estado,
                    ':notas'   => $notas ?: null,
                    ':id'      => $idPlantacion
                ));
                $mensaje = "Plantación del cuadro <strong>" . htmlspecialchars($cuadro) . "</strong> actualizada correctamente.";
            } catch (Exception $e) {
                $error = "Error al actualizar plantación: " . $e->getMessage();
            }
        }
    }

    // BORRAR PLANTACIÓN
    elseif ($action === 'borrar_plantacion') {
        $idPlantacion = (int)$_POST['id_plantacion'];
        try {
            $stmtDel = $db->prepare("DELETE FROM plantaciones WHERE id_plantacion = :id");
            $stmtDel->execute(array(':id' => $idPlantacion));
            $mensaje = "Plantación eliminada correctamente del registro.";
        } catch (Exception $e) {
            $error = "Error al eliminar plantación: " . $e->getMessage();
        }
    }
}

// Filtros
$filtroFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$filtroCampana = isset($_GET['campana']) ? (int)$_GET['campana'] : 0;

// Fincas para el filtro
$sqlFincas = "SELECT id_finca, nombre_finca FROM fincas WHERE activo = 1";
if ($tenantId) {
    $sqlFincas .= " AND id_propietario = " . (int)$tenantId;
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$fincasList = $db->query($sqlFincas)->fetchAll();

// Listado de plantaciones con filtros
$sql = "
    SELECT p.*, f.nombre_finca, c.nombre_comun, c.nombre_cientifico, c.variedad,
           (SELECT COUNT(*) FROM tratamientos t WHERE t.id_plantacion = p.id_plantacion) AS total_tratamientos,
           (SELECT COUNT(*) FROM diario_evolucion d WHERE d.id_plantacion = p.id_plantacion) AS total_diario
    FROM plantaciones p
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE 1=1
";
$params = array();

if ($tenantId) {
    $sql .= " AND f.id_propietario = :tenant";
    $params[':tenant'] = (int)$tenantId;
}

if ($filtroFinca > 0) {
    $sql .= " AND p.id_finca = :finca";
    $params[':finca'] = $filtroFinca;
}

if (!empty($filtroEstado)) {
    $sql .= " AND p.estado = :est";
    $params[':est'] = $filtroEstado;
}

if ($filtroCampana > 0) {
    $sql .= " AND p.campana_ano = :camp";
    $params[':camp'] = $filtroCampana;
}

$sql .= " ORDER BY p.campana_ano DESC, f.nombre_finca ASC, p.identificador_cuadro ASC";
$stmtPl = $db->prepare($sql);
$stmtPl->execute($params);
$plantaciones = $stmtPl->fetchAll();
?>

<div class="container py-4">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-cubes-stacked fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Plantaciones y Ciclos de Cultivo</h2>
                <p class="text-muted mb-0 small">Control de cuadros parcelarios, fechas de siembra/trasplante, marcos y estado de producción</p>
            </div>
        </div>
        <a href="<?= APP_URL ?>/plantaciones/nueva" class="btn btn-ag shadow-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Nueva Plantación
        </a>
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

    <!-- Barra de Filtros y Búsqueda -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm">
        <form action="<?= APP_URL ?>/plantaciones" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
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
                <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los estados --</option>
                    <option value="produccion" <?= $filtroEstado === 'produccion' ? 'selected' : '' ?>>En Producción</option>
                    <option value="en_crecimiento" <?= $filtroEstado === 'en_crecimiento' ? 'selected' : '' ?>>En Crecimiento</option>
                    <option value="en_descanso" <?= $filtroEstado === 'en_descanso' ? 'selected' : '' ?>>En Descanso</option>
                    <option value="arrancado" <?= $filtroEstado === 'arrancado' ? 'selected' : '' ?>>Arrancado</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="campana" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Todas las Campañas --</option>
                    <?php for ($y = date('Y') + 1; $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= ($filtroCampana === $y) ? 'selected' : '' ?>>Campaña <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Filtrar</button>
                <?php if ($filtroFinca || $filtroEstado || $filtroCampana): ?>
                    <a href="<?= APP_URL ?>/plantaciones" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla Maestra de Plantaciones (CRUD) -->
    <div class="card card-ag shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-cubes-stacked me-2"></i>Plantaciones Registradas (<?= count($plantaciones) ?>)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th class="ps-4">Finca y Cuadro</th>
                        <th>Cultivo / Variedad</th>
                        <th>Campaña</th>
                        <th>Superficie / Plantas</th>
                        <th>Fechas Clave</th>
                        <th>Estado</th>
                        <th>Seguimiento</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plantaciones)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-seedling fa-2x mb-3 text-secondary"></i>
                                <p class="mb-0">No se encontraron plantaciones activas registradas.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($plantaciones as $pl): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($pl['nombre_finca']) ?></div>
                                    <span class="badge bg-light text-primary border font-monospace">Cuadro: <?= htmlspecialchars($pl['identificador_cuadro']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($pl['nombre_comun']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($pl['variedad'] ?: $pl['nombre_cientifico'] ?: '-') ?></div>
                                </td>
                                <td><span class="badge bg-secondary font-monospace"><?= $pl['campana_ano'] ?></span></td>
                                <td>
                                    <div class="fw-bold font-monospace"><?= number_format($pl['superficie_ocupada_ha'], 2, ',', '.') ?> ha</div>
                                    <div class="small text-muted"><?= $pl['num_arboles_o_plantas'] ? $pl['num_arboles_o_plantas'] . ' plantas' : '' ?> <?= $pl['marco_plantacion'] ? '(' . htmlspecialchars($pl['marco_plantacion']) . ')' : '' ?></div>
                                </td>
                                <td class="small">
                                    <?php if ($pl['fecha_siembra']): ?>
                                        <div><strong>Siembra:</strong> <?= date('d/m/Y', strtotime($pl['fecha_siembra'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($pl['fecha_transplante']): ?>
                                        <div><strong>Trasplante:</strong> <?= date('d/m/Y', strtotime($pl['fecha_transplante'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($pl['fecha_estimada_fin']): ?>
                                        <div class="text-success fw-semibold"><strong>Fin est.:</strong> <?= date('d/m/Y', strtotime($pl['fecha_estimada_fin'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($pl['estado'] === 'produccion'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>En Producción</span>
                                    <?php elseif ($pl['estado'] === 'en_crecimiento'): ?>
                                        <span class="badge bg-info text-dark"><i class="fa-solid fa-arrow-trend-up me-1"></i>En Crecimiento</span>
                                    <?php elseif ($pl['estado'] === 'en_descanso'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-moon me-1"></i>En Descanso</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark"><i class="fa-solid fa-tree me-1"></i>Arrancado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= APP_URL ?>/diario?plantacion_id=<?= $pl['id_plantacion'] ?>" class="btn btn-sm btn-outline-info py-1 px-2" title="Ver Diario de Fotos y Evolución">
                                        <i class="fa-solid fa-camera me-1"></i><?= $pl['total_diario'] ?>
                                    </a>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Modificar -->
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarPlantacion_<?= $pl['id_plantacion'] ?>" title="Modificar plantación">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Borrar -->
                                        <form action="<?= APP_URL ?>/plantaciones" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar la plantación del cuadro \'<?= htmlspecialchars(addslashes($pl['identificador_cuadro'])) ?>\'?');">
                                            <input type="hidden" name="action" value="borrar_plantacion">
                                            <input type="hidden" name="id_plantacion" value="<?= $pl['id_plantacion'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Eliminar plantación">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Modificar Plantación -->
                                    <div class="modal fade text-start" id="modalEditarPlantacion_<?= $pl['id_plantacion'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Plantación: <?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['identificador_cuadro']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="<?= APP_URL ?>/plantaciones" method="POST">
                                                    <input type="hidden" name="action" value="editar_plantacion">
                                                    <input type="hidden" name="id_plantacion" value="<?= $pl['id_plantacion'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Finca</label>
                                                                <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($pl['nombre_finca']) ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Cultivo</label>
                                                                <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($pl['nombre_comun'] . ' (' . ($pl['variedad'] ?: 'Estándar') . ')') ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Identificador Cuadro / Parcela *</label>
                                                                <input type="text" name="identificador_cuadro" class="form-control" value="<?= htmlspecialchars($pl['identificador_cuadro']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Campaña (Año) *</label>
                                                                <input type="number" name="campana_ano" class="form-control font-monospace" value="<?= $pl['campana_ano'] ?>" required>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Superficie Ocupada (ha) *</label>
                                                                <input type="number" step="0.0001" name="superficie_ocupada_ha" class="form-control font-monospace" value="<?= $pl['superficie_ocupada_ha'] ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Nº Árboles / Plantas</label>
                                                                <input type="number" name="num_arboles_o_plantas" class="form-control font-monospace" value="<?= $pl['num_arboles_o_plantas'] ?: '' ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Marco de Plantación</label>
                                                                <input type="text" name="marco_plantacion" class="form-control font-monospace" value="<?= htmlspecialchars($pl['marco_plantacion'] ?: '') ?>" placeholder="Ej: 6 x 4 m">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Fecha Siembra</label>
                                                                <input type="date" name="fecha_siembra" class="form-control" value="<?= $pl['fecha_siembra'] ?: '' ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Fecha Trasplante</label>
                                                                <input type="date" name="fecha_transplante" class="form-control" value="<?= $pl['fecha_transplante'] ?: '' ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Fecha Estimada Fin</label>
                                                                <input type="date" name="fecha_estimada_fin" class="form-control" value="<?= $pl['fecha_estimada_fin'] ?: '' ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Estado del Cuadro *</label>
                                                                <select name="estado" class="form-select" required>
                                                                    <option value="produccion" <?= $pl['estado'] === 'produccion' ? 'selected' : '' ?>>En Producción</option>
                                                                    <option value="en_crecimiento" <?= $pl['estado'] === 'en_crecimiento' ? 'selected' : '' ?>>En Crecimiento</option>
                                                                    <option value="en_descanso" <?= $pl['estado'] === 'en_descanso' ? 'selected' : '' ?>>En Descanso</option>
                                                                    <option value="arrancado" <?= $pl['estado'] === 'arrancado' ? 'selected' : '' ?>>Arrancado</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Notas de Seguimiento</label>
                                                                <input type="text" name="notas_cuaderno" class="form-control" value="<?= htmlspecialchars($pl['notas_cuaderno'] ?: '') ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-ag">
                                                            <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                                                        </button>
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

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
