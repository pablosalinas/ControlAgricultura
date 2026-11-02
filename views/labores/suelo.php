<?php
$pageTitle = "Laboreo del Suelo y Desbroce - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();

// Guardar labor
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_labor'])) {
    $stmt = $db->prepare("
        INSERT INTO labores_suelo (id_plantacion, fecha_labor, tipo_labor, apero_maquinaria, horas_trabajo, operario, observaciones, creado_por_id)
        VALUES (:plant, :fecha, :tipo, :apero, :horas, :operario, :obs, :user)
    ");
    $stmt->execute([
        ':plant'    => (int)$_POST['id_plantacion'],
        ':fecha'    => $_POST['fecha_labor'],
        ':tipo'     => $_POST['tipo_labor'],
        ':apero'    => trim($_POST['apero_maquinaria'] ?? ''),
        ':horas'    => !empty($_POST['horas_trabajo']) ? (float)$_POST['horas_trabajo'] : null,
        ':operario' => trim($_POST['operario'] ?? Auth::user()['nombre']),
        ':obs'      => trim($_POST['observaciones'] ?? ''),
        ':user'     => Auth::user()['id']
    ]);
    $mensaje = "Labor de terreno registrada correctamente.";
}

$plantaciones = $db->query("
    SELECT p.id_plantacion, p.identificador_cuadro, f.nombre_finca, c.nombre_comun
    FROM plantaciones p JOIN fincas f ON p.id_finca = f.id_finca JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY f.nombre_finca ASC
")->fetchAll();

$labores = $db->query("
    SELECT l.*, f.nombre_finca, c.nombre_comun
    FROM labores_suelo l
    JOIN plantaciones p ON l.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY l.fecha_labor DESC, l.id_labor DESC
    LIMIT 50
")->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-tractor text-secondary me-2"></i>Laboreo y Manejo del Suelo</h2>
            <p class="text-muted mb-0">Arado, subsolado, grada, desbroce y control mecánico de malas hierbas</p>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formLabor">
            <i class="fa-solid fa-plus me-1"></i> Registrar Labor
        </button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="collapse mb-4" id="formLabor">
        <div class="card card-custom p-4 border-top border-4 border-secondary">
            <h5 class="fw-bold mb-3">Nueva Labor de Terreno</h5>
            <form action="<?= APP_URL ?>/labores" method="POST">
                <input type="hidden" name="guardar_labor" value="1">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Finca y Parcela</label>
                        <select name="id_plantacion" class="form-select" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>"><?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun'] . ' (' . $pl['identificador_cuadro'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha</label>
                        <input type="date" name="fecha_labor" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tipo de Labor</label>
                        <select name="tipo_labor" class="form-select" required>
                            <option value="arado">Arado</option>
                            <option value="subsolado">Subsolado</option>
                            <option value="fresado">Fresado / Rotovator</option>
                            <option value="grada">Grada de discos</option>
                            <option value="cultivador">Cultivador</option>
                            <option value="desbroce">Desbroce mecánico</option>
                            <option value="eliminacion_malas_hierbas">Eliminación de hierba</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Apero o Maquinaria Empleada</label>
                        <input type="text" name="apero_maquinaria" class="form-control" placeholder="Tractor John Deere 120CV con vertedera...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Horas de Trabajo</label>
                        <input type="number" step="0.5" name="horas_trabajo" class="form-control" placeholder="Ej: 4.5">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Operario</label>
                        <input type="text" name="operario" class="form-control" value="<?= htmlspecialchars(Auth::user()['nombre_completo']) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Profundidad de labor, humedad de la tierra..."></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-secondary px-4 fw-bold">Guardar Labor</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3">Histórico de Laboreo</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Finca</th><th>Labor</th><th>Apero/Tractor</th><th>Horas</th><th>Operario</th><th>Observaciones</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($labores)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay labores registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($labores as $l): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($l['fecha_labor'])) ?></td>
                                <td><strong><?= htmlspecialchars($l['nombre_finca']) ?></strong></td>
                                <td><span class="badge bg-secondary text-capitalize"><?= str_replace('_', ' ', $l['tipo_labor']) ?></span></td>
                                <td><?= htmlspecialchars($l['apero_maquinaria'] ?? '-') ?></td>
                                <td><?= $l['horas_trabajo'] ? $l['horas_trabajo'] . 'h' : '-' ?></td>
                                <td><?= htmlspecialchars($l['operario'] ?? '-') ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($l['observaciones'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
