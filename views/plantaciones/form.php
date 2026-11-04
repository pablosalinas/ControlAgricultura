<?php
$pageTitle = "Nueva Plantación - " . APP_NAME;

$db = Database::getConnection();
$tenantId = Auth::tenantId();

$sqlFincas = "SELECT id_finca, nombre_finca, superficie_ha FROM fincas WHERE activo = 1";
if ($tenantId) {
    $sqlFincas .= " AND (id_propietario = " . (int)$tenantId . " OR id_propietario IS NULL)";
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$fincas = $db->query($sqlFincas)->fetchAll();

$cultivos = $db->query("SELECT id_cultivo, nombre_comun, nombre_cientifico, variedad, dias_ciclo_aprox FROM cultivos WHERE activo = 1 ORDER BY nombre_comun ASC")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idFinca = (int)$_POST['id_finca'];
    $idCultivo = (int)$_POST['id_cultivo'];
    $cuadro = trim(isset($_POST['identificador_cuadro']) ? $_POST['identificador_cuadro'] : 'Cuadro Principal');
    $campana = (int)(isset($_POST['campana_ano']) ? $_POST['campana_ano'] : date('Y'));
    $superficie = (float)(isset($_POST['superficie_ocupada_ha']) ? $_POST['superficie_ocupada_ha'] : 0);
    $numPlantas = !empty($_POST['num_arboles_o_plantas']) ? (int)$_POST['num_arboles_o_plantas'] : null;
    $marco = trim(isset($_POST['marco_plantacion']) ? $_POST['marco_plantacion'] : '');
    $fechaSiembra = !empty($_POST['fecha_siembra']) ? $_POST['fecha_siembra'] : null;
    $fechaTrans = !empty($_POST['fecha_transplante']) ? $_POST['fecha_transplante'] : null;
    $fechaFin = !empty($_POST['fecha_estimada_fin']) ? $_POST['fecha_estimada_fin'] : null;
    $tipoPlantacion = isset($_POST['tipo_plantacion']) ? $_POST['tipo_plantacion'] : 'temporada';
    $diaMesRecoleccion = !empty($fechaFin) ? date('m-d', strtotime($fechaFin)) : null;
    $estado = isset($_POST['estado']) ? $_POST['estado'] : 'en_crecimiento';
    $notas = trim(isset($_POST['notas']) ? $_POST['notas'] : '');

    if (empty($idFinca) || empty($idCultivo)) {
        $error = "Debes seleccionar una finca y un cultivo obligatoriamente.";
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO plantaciones (
                    id_finca, id_cultivo, identificador_cuadro, campana_ano, superficie_ocupada_ha,
                    num_arboles_o_plantas, marco_plantacion, fecha_siembra, fecha_transplante,
                    fecha_estimada_fin, tipo_plantacion, dia_mes_recoleccion, estado, notas
                ) VALUES (
                    :finca, :cultivo, :cuadro, :campana, :sup,
                    :num, :marco, :siembra, :trans,
                    :fin, :tipo, :diames, :estado, :notas
                )
            ");
            $stmt->execute([
                ':finca'    => $idFinca,
                ':cultivo'  => $idCultivo,
                ':cuadro'   => $cuadro,
                ':campana'  => $campana,
                ':sup'      => $superficie,
                ':num'      => $numPlantas,
                ':marco'    => $marco,
                ':siembra'  => $fechaSiembra,
                ':trans'    => $fechaTrans,
                ':fin'      => $fechaFin,
                ':tipo'     => $tipoPlantacion,
                ':diames'   => $diaMesRecoleccion,
                ':estado'   => $estado,
                ':notas'    => $notas
            ]);

            if (!headers_sent()) {
                header("Location: " . APP_URL . "/plantaciones");
            } else {
                echo "<script>window.location.href='" . APP_URL . "/plantaciones';</script>";
            }
            exit;
        } catch (Exception $e) {
            $error = "Error al registrar la plantación: " . $e->getMessage();
        }
    }
}

require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-seedling text-success me-2"></i>Nueva Plantación en Finca</h2>
                    <p class="text-muted mb-0">Establece un ciclo de cultivo con fechas de siembra, trasplante y estimación de cosecha</p>
                </div>
                <a href="<?= APP_URL ?>/plantaciones" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Volver
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card card-custom p-4 p-md-5">
                <form action="<?= APP_URL ?>/plantaciones/nueva" method="POST">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Finca de Destino <span class="text-danger">*</span></label>
                            <select name="id_finca" class="form-select" required>
                                <option value="">-- Seleccionar Finca --</option>
                                <?php foreach ($fincas as $f): ?>
                                    <option value="<?= $f['id_finca'] ?>">
                                        <?= htmlspecialchars($f['nombre_finca'] . ' (' . number_format($f['superficie_ha'], 2) . ' ha)') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cultivo y Variedad <span class="text-danger">*</span></label>
                            <select name="id_cultivo" class="form-select" required>
                                <option value="">-- Seleccionar Cultivo --</option>
                                <?php foreach ($cultivos as $c): ?>
                                    <option value="<?= $c['id_cultivo'] ?>">
                                        <?= htmlspecialchars($c['nombre_comun'] . ($c['variedad'] ? ' - ' . $c['variedad'] : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Identificador de Cuadro / Subparcela</label>
                            <input type="text" name="identificador_cuadro" class="form-control" value="Cuadro 1" placeholder="Ej: Sector Norte, Tablón 2, Cuadro A">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Campaña / Año</label>
                            <input type="number" name="campana_ano" class="form-control" value="<?= date('Y') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Régimen / Tipo</label>
                            <select name="tipo_plantacion" class="form-select fw-semibold">
                                <option value="temporada" selected>🌱 De temporada (hortalizas...)</option>
                                <option value="fija">🌳 Fija / Anual (árboles, olivar...)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Estado Actual</label>
                            <select name="estado" class="form-select">
                                <option value="en_crecimiento" selected>En crecimiento</option>
                                <option value="produccion">En producción</option>
                                <option value="en_descanso">En descanso</option>
                                <option value="arrancado">Arrancado</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Superficie Ocupada (ha)</label>
                            <input type="number" step="0.0001" name="superficie_ocupada_ha" class="form-control" placeholder="0.0000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nº de Árboles o Plantas</label>
                            <input type="number" name="num_arboles_o_plantas" class="form-control" placeholder="Ej: 450">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Marco de Plantación</label>
                            <input type="text" name="marco_plantacion" class="form-control" placeholder="Ej: 5 x 4 m, 6 x 6 m">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de Siembra</label>
                            <input type="date" name="fecha_siembra" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de Trasplante (si aplica)</label>
                            <input type="date" name="fecha_transplante" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha Estimada de Cosecha</label>
                            <input type="date" name="fecha_estimada_fin" class="form-control">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Notas agronómicas</label>
                        <textarea name="notas" class="form-control" rows="2" placeholder="Portainjerto/patrón empleado, procedencia del vivero, sanidad vegetal..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= APP_URL ?>/plantaciones" class="btn btn-light border px-4">Cancelar</a>
                        <button type="submit" class="btn btn-ag px-4 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Plantación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
