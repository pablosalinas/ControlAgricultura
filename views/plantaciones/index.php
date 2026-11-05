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

    // EDITAR PLANTACIÓN (Permite modificar finca, cultivo y todos los parámetros)
    if ($action === 'editar_plantacion') {
        $idPlantacion = (int)$_POST['id_plantacion'];
        $idFinca = (int)(isset($_POST['id_finca']) ? $_POST['id_finca'] : 0);
        $idCultivo = (int)(isset($_POST['id_cultivo']) ? $_POST['id_cultivo'] : 0);
        $cuadro = trim(isset($_POST['identificador_cuadro']) ? $_POST['identificador_cuadro'] : '');
        $campana = (int)(isset($_POST['campana_ano']) ? $_POST['campana_ano'] : date('Y'));
        
        $superficieRaw = isset($_POST['superficie_ocupada_ha']) ? trim($_POST['superficie_ocupada_ha']) : '0';
        $superficie = (float)str_replace(',', '.', $superficieRaw);
        if ($superficie < 0) {
            $superficie = 0.0;
        }

        $arboles = !empty($_POST['num_arboles_o_plantas']) ? (int)$_POST['num_arboles_o_plantas'] : null;
        $marco = trim(isset($_POST['marco_plantacion']) ? $_POST['marco_plantacion'] : '');
        $siembra = !empty($_POST['fecha_siembra']) ? $_POST['fecha_siembra'] : null;
        $transplante = !empty($_POST['fecha_transplante']) ? $_POST['fecha_transplante'] : null;
        $fin = !empty($_POST['fecha_estimada_fin']) ? $_POST['fecha_estimada_fin'] : null;
        $tipoPlantacion = isset($_POST['tipo_plantacion']) ? $_POST['tipo_plantacion'] : 'temporada';
        $diaMesRecoleccion = !empty($fin) ? date('m-d', strtotime($fin)) : null;
        $estado = isset($_POST['estado']) ? $_POST['estado'] : 'en_crecimiento';
        $notas = trim(isset($_POST['notas']) ? $_POST['notas'] : (isset($_POST['notas_cuaderno']) ? $_POST['notas_cuaderno'] : ''));

        if (empty($idFinca) || empty($idCultivo) || empty($cuadro)) {
            $error = "La finca, el cultivo y el identificador de cuadro son obligatorios.";
        } else {
            try {
                // Actualizar plantación con finca, cultivo y campos técnicos
                $stmtUpd = $db->prepare("
                    UPDATE plantaciones
                    SET id_finca = :finca, id_cultivo = :cultivo, identificador_cuadro = :cuadro, campana_ano = :camp,
                        superficie_ocupada_ha = :sup, num_arboles_o_plantas = :arb,
                        marco_plantacion = :marco, fecha_siembra = :siembra,
                        fecha_transplante = :transp, fecha_estimada_fin = :fin,
                        tipo_plantacion = :tipo, dia_mes_recoleccion = :diames,
                        estado = :est, notas = :notas
                    WHERE id_plantacion = :id
                ");
                $stmtUpd->execute(array(
                    ':finca'   => $idFinca,
                    ':cultivo' => $idCultivo,
                    ':cuadro'  => $cuadro,
                    ':camp'    => $campana,
                    ':sup'     => $superficie,
                    ':arb'     => $arboles,
                    ':marco'   => $marco ?: null,
                    ':siembra' => $siembra,
                    ':transp'  => $transplante,
                    ':fin'     => $fin,
                    ':tipo'    => $tipoPlantacion,
                    ':diames'  => $diaMesRecoleccion,
                    ':est'     => $estado,
                    ':notas'   => $notas ?: null,
                    ':id'      => $idPlantacion
                ));

                // Sincronizar también notas_cuaderno si la columna existe en la tabla
                try {
                    $db->prepare("UPDATE plantaciones SET notas_cuaderno = :notas WHERE id_plantacion = :id")->execute(array(
                        ':notas' => $notas ?: null,
                        ':id'    => $idPlantacion
                    ));
                } catch (Exception $eCol) {}

                $mensaje = "Plantación del cuadro <strong>" . htmlspecialchars($cuadro) . "</strong> actualizada correctamente con su finca y cultivo.";
            } catch (Exception $e) {
                $error = "Error al actualizar plantación: " . $e->getMessage();
            }
        }
    }

    // BORRAR PLANTACIÓN (Borrado lógico seguro GEMINI.md)
    elseif ($action === 'borrar_plantacion') {
        $idPlantacion = (int)$_POST['id_plantacion'];
        try {
            $stmtDel = $db->prepare("UPDATE plantaciones SET activo = 0 WHERE id_plantacion = :id");
            $stmtDel->execute(array(':id' => $idPlantacion));
            $mensaje = "Plantación dada de baja y archivada correctamente (borrado lógico seguro).";
        } catch (Exception $e) {
            $error = "Error al eliminar plantación: " . $e->getMessage();
        }
    }
}

// Filtros
$filtroFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$filtroCampana = isset($_GET['campana']) ? (int)$_GET['campana'] : 0;

// Fincas para el filtro y selector de edición
$sqlFincas = "SELECT id_finca, nombre_finca, superficie_ha FROM fincas WHERE activo = 1";
if ($tenantId) {
    $sqlFincas .= " AND (id_propietario = " . (int)$tenantId . " OR id_propietario IS NULL)";
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$fincasList = $db->query($sqlFincas)->fetchAll();

// Catálogo de cultivos activos para el selector de edición
$cultivosList = $db->query("SELECT id_cultivo, nombre_comun, nombre_cientifico, variedad FROM cultivos WHERE activo = 1 ORDER BY nombre_comun ASC")->fetchAll();

// Listado de plantaciones con filtros
$sql = "
    SELECT p.*, f.nombre_finca, c.nombre_comun, c.nombre_cientifico, c.variedad,
           (SELECT COUNT(*) FROM tratamientos t WHERE t.id_plantacion = p.id_plantacion) AS total_tratamientos,
           (SELECT COUNT(*) FROM diario_evolucion d WHERE d.id_plantacion = p.id_plantacion) AS total_diario
    FROM plantaciones p
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE (p.activo = 1 OR p.activo IS NULL)
";
$params = array();

if ($tenantId) {
    $sql .= " AND (f.id_propietario = :tenant OR f.id_propietario IS NULL)";
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
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php
            $reportTitle = 'Listado Oficial de Plantaciones y Cuadros de Cultivo';
            $reportTargetId = 'tablaPlantacionesImprimible';
            $reportFilename = 'plantaciones_cultivos_' . date('Y-m-d') . '.pdf';
            require VIEWS_PATH . '/layouts/toolbar_informes.php';
            ?>
            <a href="<?= APP_URL ?>/mapas<?= $filtroFinca ? '?finca_id=' . $filtroFinca : '' ?>" class="btn btn-outline-primary shadow-sm" title="Ver y delimitar plantaciones en el Mapa Satelital">
                <i class="fa-solid fa-map-location-dot me-1"></i> Ver en Mapa SIG
            </a>
            <a href="<?= APP_URL ?>/plantaciones/nueva" class="btn btn-ag shadow-sm">
                <i class="fa-solid fa-plus-circle me-1"></i> Nueva Plantación
            </a>
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
    <div class="card card-ag shadow-sm border-0" id="tablaPlantacionesImprimible">
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
                                        <button type="button" class="btn btn-outline-primary" onclick="abrirModalEditarPlantacion(<?= htmlspecialchars(json_encode($pl), ENT_QUOTES, 'UTF-8') ?>)" title="Modificar plantación">
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Modificar Plantación (Estable, fuera de la tabla) -->
<div class="modal fade text-start" id="modalEditarPlantacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <h5 class="modal-title fw-bold" id="modalEditarPlantacionTitle">
                    <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Plantación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= APP_URL ?>/plantaciones" method="POST">
                <input type="hidden" name="action" value="editar_plantacion">
                <input type="hidden" name="id_plantacion" id="edit_id_plantacion" value="">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-primary">
                                <i class="fa-solid fa-draw-polygon me-1"></i>Finca de Destino *
                            </label>
                            <select name="id_finca" id="edit_id_finca" class="form-select fw-semibold" required>
                                <?php foreach ($fincasList as $f): ?>
                                    <option value="<?= $f['id_finca'] ?>">
                                        <?= htmlspecialchars($f['nombre_finca']) ?> (<?= number_format($f['superficie_ha'], 2) ?> ha)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold small text-success mb-0">
                                    <i class="fa-solid fa-seedling me-1"></i>Cultivo / Especie *
                                </label>
                                <button type="button" class="btn btn-link btn-sm text-success p-0 fw-bold text-decoration-none" onclick="abrirModalNuevoCultivoRapido('edit_plantacion')">
                                    <i class="fa-solid fa-plus-circle me-1"></i>+ Nueva Planta
                                </button>
                            </div>
                            <div class="input-group">
                                <select name="id_cultivo" id="edit_id_cultivo" class="form-select fw-semibold" required>
                                    <?php foreach ($cultivosList as $cl): ?>
                                        <option value="<?= $cl['id_cultivo'] ?>">
                                            <?= htmlspecialchars($cl['nombre_comun'] . ($cl['variedad'] ? ' (' . $cl['variedad'] . ')' : '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="abrirModalNuevoCultivoRapido('edit_plantacion')" title="Crear nueva planta en el catálogo botánico sin salir">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Identificador Cuadro / Parcela *</label>
                            <input type="text" name="identificador_cuadro" id="edit_identificador_cuadro" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Campaña (Año) *</label>
                            <input type="number" name="campana_ano" id="edit_campana_ano" class="form-control font-monospace" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Tipo Plantación</label>
                            <select name="tipo_plantacion" id="edit_tipo_plantacion" class="form-select">
                                <option value="temporada">Temporada (Hortícola)</option>
                                <option value="permanente">Permanente (Frutal/Olivo/etc)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Superficie Ocupada (ha)</label>
                            <input type="number" step="0.0001" min="0" name="superficie_ocupada_ha" id="edit_superficie_ocupada_ha" class="form-control font-monospace" value="0.0000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Nº Árboles / Plantas</label>
                            <input type="number" name="num_arboles_o_plantas" id="edit_num_arboles_o_plantas" class="form-control font-monospace" placeholder="Opcional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Marco de Plantación</label>
                            <input type="text" name="marco_plantacion" id="edit_marco_plantacion" class="form-control font-monospace" placeholder="Ej: 6 x 4 m">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Fecha Siembra</label>
                            <input type="date" name="fecha_siembra" id="edit_fecha_siembra" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Fecha Trasplante</label>
                            <input type="date" name="fecha_transplante" id="edit_fecha_transplante" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Fecha Estimada Fin / Cosecha</label>
                            <input type="date" name="fecha_estimada_fin" id="edit_fecha_estimada_fin" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Estado del Cuadro *</label>
                            <select name="estado" id="edit_estado" class="form-select" required>
                                <option value="produccion">En Producción</option>
                                <option value="en_crecimiento">En Crecimiento</option>
                                <option value="en_descanso">En Descanso</option>
                                <option value="arrancado">Arrancado</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Notas de Seguimiento</label>
                            <input type="text" name="notas" id="edit_notas" class="form-control" placeholder="Observaciones de seguimiento">
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

<script>
function abrirModalEditarPlantacion(pl) {
    if (!pl) return;
    document.getElementById('edit_id_plantacion').value = pl.id_plantacion || '';
    document.getElementById('edit_id_finca').value = pl.id_finca || '';
    document.getElementById('edit_id_cultivo').value = pl.id_cultivo || '';
    document.getElementById('edit_identificador_cuadro').value = pl.identificador_cuadro || '';
    document.getElementById('edit_campana_ano').value = pl.campana_ano || new Date().getFullYear();
    document.getElementById('edit_tipo_plantacion').value = pl.tipo_plantacion || 'temporada';
    document.getElementById('edit_superficie_ocupada_ha').value = (pl.superficie_ocupada_ha !== null && pl.superficie_ocupada_ha !== undefined) ? pl.superficie_ocupada_ha : '0.0000';
    document.getElementById('edit_num_arboles_o_plantas').value = pl.num_arboles_o_plantas || '';
    document.getElementById('edit_marco_plantacion').value = pl.marco_plantacion || '';
    document.getElementById('edit_fecha_siembra').value = pl.fecha_siembra || '';
    document.getElementById('edit_fecha_transplante').value = pl.fecha_transplante || '';
    document.getElementById('edit_fecha_estimada_fin').value = pl.fecha_estimada_fin || '';
    document.getElementById('edit_estado').value = pl.estado || 'en_crecimiento';
    document.getElementById('edit_notas').value = pl.notas || pl.notas_cuaderno || '';

    var titleEl = document.getElementById('modalEditarPlantacionTitle');
    if (titleEl) {
        var nomFinca = pl.nombre_finca || 'Finca';
        var cuadro = pl.identificador_cuadro || 'Cuadro';
        titleEl.innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i> Modificar Plantación: ' + nomFinca + ' - ' + cuadro;
    }

    var modalEl = document.getElementById('modalEditarPlantacion');
    var modal = bootstrap.Modal.getInstance(modalEl);
    if (!modal) {
        modal = new bootstrap.Modal(modalEl);
    }
    modal.show();
}
</script>

<?php 
require_once VIEWS_PATH . '/plantaciones/modal_nuevo_cultivo.php';
require_once VIEWS_PATH . '/layouts/footer.php'; 
?>
