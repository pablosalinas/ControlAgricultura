<?php
$pageTitle = "Tratamientos Fitosanitarios y Ecológicos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();

// Procesar nuevo tratamiento
$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_tratamiento'])) {
    try {
        $idPlantacion = (int)$_POST['id_plantacion'];
        $fecha = $_POST['fecha_tratamiento'];
        $esEco = isset($_POST['es_ecologico']) ? 1 : 0;
        $producto = trim($_POST['nombre_comercial_producto']);
        $materiaActiva = trim($_POST['materia_activa'] ?? '');
        $registro = trim($_POST['num_registro_oficial'] ?? '');
        $dosis = (float)$_POST['dosis_por_hectarea'];
        $unidad = $_POST['unidad_dosis'] ?? 'l/ha';
        $caldo = !empty($_POST['caldo_total_litros']) ? (float)$_POST['caldo_total_litros'] : null;
        $plazo = (int)($_POST['plazo_seguridad_dias'] ?? 0);
        $fechaFinPlazo = $plazo > 0 ? date('Y-m-d', strtotime($fecha . " + {$plazo} days")) : null;
        $plaga = trim($_POST['plaga_o_enfermedad_objetivo'] ?? '');
        $aplicador = trim($_POST['aplicador_responsable'] ?? Auth::user()['nombre']);
        $obs = trim($_POST['observaciones'] ?? '');

        $stmt = $db->prepare("
            INSERT INTO tratamientos (
                id_plantacion, fecha_tratamiento, es_ecologico, nombre_comercial_producto,
                materia_activa, num_registro_oficial, dosis_por_hectarea, unidad_dosis,
                caldo_total_litros, plazo_seguridad_dias, fecha_fin_plazo_seguridad,
                plaga_o_enfermedad_objetivo, aplicador_responsable, observaciones, creado_por_id
            ) VALUES (
                :plantacion, :fecha, :eco, :prod,
                :mat, :reg, :dosis, :unidad,
                :caldo, :plazo, :finplazo,
                :plaga, :aplicador, :obs, :user
            )
        ");
        $stmt->execute([
            ':plantacion' => $idPlantacion,
            ':fecha'      => $fecha,
            ':eco'        => $esEco,
            ':prod'       => $producto,
            ':mat'        => $materiaActiva,
            ':reg'        => $registro,
            ':dosis'      => $dosis,
            ':unidad'     => $unidad,
            ':caldo'      => $caldo,
            ':plazo'      => $plazo,
            ':finplazo'   => $fechaFinPlazo,
            ':plaga'      => $plaga,
            ':aplicador'  => $aplicador,
            ':obs'        => $obs,
            ':user'       => Auth::user()['id']
        ]);
        $mensaje = "Tratamiento registrado correctamente en el cuaderno de campo.";
    } catch (Exception $e) {
        $error = "Error al registrar el tratamiento: " . $e->getMessage();
    }
}

// Obtener plantaciones activas para el desplegable
$plantaciones = $db->query("
    SELECT p.id_plantacion, p.identificador_cuadro, f.nombre_finca, c.nombre_comun
    FROM plantaciones p
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY f.nombre_finca ASC
")->fetchAll();

// Obtener histórico de tratamientos
$tratamientos = $db->query("
    SELECT t.*, f.nombre_finca, c.nombre_comun, p.identificador_cuadro
    FROM tratamientos t
    JOIN plantaciones p ON t.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY t.fecha_tratamiento DESC, t.id_tratamiento DESC
    LIMIT 50
")->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-spray-can-sparkles text-danger me-2"></i>Tratamientos Fitosanitarios y Ecológicos</h2>
            <p class="text-muted mb-0">Control de materias activas, dosis, plagas combatidas y plazos de seguridad</p>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formNuevoTratamiento">
            <i class="fa-solid fa-plus me-1"></i> Registrar Tratamiento
        </button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Formulario Desplegable para Registrar Tratamiento -->
    <div class="collapse mb-4" id="formNuevoTratamiento">
        <div class="card card-custom p-4 p-md-5 border-top border-4 border-danger">
            <h4 class="fw-bold mb-3"><i class="fa-solid fa-file-signature text-danger me-2"></i>Nuevo Registro de Tratamiento</h4>
            <form action="<?= APP_URL ?>/tratamientos" method="POST">
                <input type="hidden" name="guardar_tratamiento" value="1">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Finca y Plantación Objetivo <span class="text-danger">*</span></label>
                        <select name="id_plantacion" class="form-select" required>
                            <option value="">-- Seleccionar Plantación --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>">
                                    <?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun'] . ' (' . $pl['identificador_cuadro'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha de Aplicación <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_tratamiento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-center pt-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="es_ecologico" name="es_ecologico" value="1">
                            <label class="form-check-label fw-bold text-success fs-6" for="es_ecologico">
                                <i class="fa-solid fa-seedling me-1"></i> Producto Ecológico
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nombre Comercial del Producto <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_comercial_producto" class="form-control" required placeholder="Ej: Cobre Nordox, Spintor 480, Bacillus thuringiensis...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Materia Activa</label>
                        <input type="text" name="materia_activa" class="form-control" placeholder="Ej: Óxido cuproso 75%, Spinosad...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Nº Registro Oficial (MAPA)</label>
                        <input type="text" name="num_registro_oficial" class="form-control" placeholder="Ej: 22.145">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Dosis por Hectárea <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" name="dosis_por_hectarea" class="form-control" required placeholder="1.500">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Unidad de Dosis</label>
                        <select name="unidad_dosis" class="form-select">
                            <option value="l/ha">Litros / ha</option>
                            <option value="kg/ha">Kilos / ha</option>
                            <option value="cc/hl">cc / 100 litros (hl)</option>
                            <option value="g/hl">gramos / 100 litros (hl)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Caldo Total (Litros)</label>
                        <input type="number" step="10" name="caldo_total_litros" class="form-control" placeholder="Ej: 1000">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Plazo de Seguridad (Días)</label>
                        <input type="number" name="plazo_seguridad_dias" class="form-control" value="0">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Plaga o Enfermedad Objetivo</label>
                        <input type="text" name="plaga_o_enfermedad_objetivo" class="form-control" placeholder="Ej: Oídio, Mosca de la fruta (Ceratitis), Pulgón...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Aplicador / Carné Fitosanitario</label>
                        <input type="text" name="aplicador_responsable" class="form-control" value="<?= htmlspecialchars(Auth::user()['nombre_completo']) ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Observaciones técnicas o condiciones meteorológicas</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Velocidad del viento, temperatura, gasto de boquillas..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border px-4" data-bs-toggle="collapse" data-bs-target="#formNuevoTratamiento">Cancelar</button>
                    <button type="submit" class="btn btn-danger px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar en Dietario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Histórico de Tratamientos -->
    <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-book-bookmark text-danger me-2"></i>Histórico del Cuaderno de Tratamientos</h5>
        <?php if (empty($tratamientos)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-spray-can fs-1 mb-3"></i>
                <p>No se han registrado tratamientos aún.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Finca / Cultivo</th>
                            <th>Tipo</th>
                            <th>Producto / Materia Activa</th>
                            <th>Dosis</th>
                            <th>Plaga Objetivo</th>
                            <th>Plazo Seg. (Fin)</th>
                            <th>Aplicador</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tratamientos as $t): ?>
                            <tr>
                                <td class="fw-bold small"><?= date('d/m/Y', strtotime($t['fecha_tratamiento'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($t['nombre_finca']) ?></strong><br>
                                    <span class="small text-muted"><?= htmlspecialchars($t['nombre_comun']) ?></span>
                                </td>
                                <td>
                                    <?php if ($t['es_ecologico']): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-leaf me-1"></i>Ecológico</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Convencional</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($t['nombre_comercial_producto']) ?></strong><br>
                                    <span class="small text-muted"><?= htmlspecialchars($t['materia_activa'] ?? '-') ?></span>
                                </td>
                                <td><?= $t['dosis_por_hectarea'] ?> <?= htmlspecialchars($t['unidad_dosis']) ?></td>
                                <td><?= htmlspecialchars($t['plaga_o_enfermedad_objetivo'] ?? '-') ?></td>
                                <td>
                                    <?php if ($t['plazo_seguridad_dias'] > 0): ?>
                                        <span class="badge bg-warning text-dark"><?= $t['plazo_seguridad_dias'] ?> días</span>
                                        <div class="small text-muted"><?= $t['fecha_fin_plazo_seguridad'] ? date('d/m/Y', strtotime($t['fecha_fin_plazo_seguridad'])) : '' ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border">0 días</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($t['aplicador_responsable'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
