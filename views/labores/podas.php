<?php
$pageTitle = "Podas, Talas y Gestión de Residuos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_poda'])) {
    $stmt = $db->prepare("
        INSERT INTO podas_talas (id_plantacion, fecha, tipo_operacion, destino_residuos, numero_permiso_quema, kilos_o_volumen_estimado, observaciones, creado_por_id)
        VALUES (:plant, :fecha, :op, :residuos, :permiso, :vol, :obs, :user)
    ");
    $stmt->execute([
        ':plant'    => (int)$_POST['id_plantacion'],
        ':fecha'    => $_POST['fecha'],
        ':op'       => $_POST['tipo_operacion'],
        ':residuos' => $_POST['destino_residuos'],
        ':permiso'  => trim($_POST['numero_permiso_quema'] ?? ''),
        ':vol'      => !empty($_POST['kilos_o_volumen_estimado']) ? (float)$_POST['kilos_o_volumen_estimado'] : null,
        ':obs'      => trim($_POST['observaciones'] ?? ''),
        ':user'     => Auth::user()['id']
    ]);
    $mensaje = "Registro de poda/tala y gestión de restos guardado correctamente.";
}

$plantaciones = $db->query("
    SELECT p.id_plantacion, p.identificador_cuadro, f.nombre_finca, c.nombre_comun
    FROM plantaciones p JOIN fincas f ON p.id_finca = f.id_finca JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY f.nombre_finca ASC
")->fetchAll();

$podas = $db->query("
    SELECT pt.*, f.nombre_finca, c.nombre_comun
    FROM podas_talas pt
    JOIN plantaciones p ON pt.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY pt.fecha DESC, pt.id_poda DESC
    LIMIT 50
")->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-scissors text-warning me-2"></i>Podas, Talas y Reciclaje de Restos</h2>
            <p class="text-muted mb-0">Trazabilidad de cortes de madera y gestión sostenible de subproductos vegetales</p>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formPoda">
            <i class="fa-solid fa-plus me-1"></i> Registrar Poda / Tala
        </button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="collapse mb-4" id="formPoda">
        <div class="card card-custom p-4 border-top border-4 border-warning">
            <h5 class="fw-bold mb-3">Nuevo Registro de Poda o Tala</h5>
            <form action="<?= APP_URL ?>/podas" method="POST">
                <input type="hidden" name="guardar_poda" value="1">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Finca y Cultivo</label>
                        <select name="id_plantacion" class="form-select" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>"><?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun'] . ' (' . $pl['identificador_cuadro'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tipo de Operación</label>
                        <select name="tipo_operacion" class="form-select" required>
                            <option value="poda_formacion">Poda de formación</option>
                            <option value="poda_mantenimiento">Poda de producción/mantenimiento</option>
                            <option value="poda_rejuvenecimiento">Poda de rejuvenecimiento</option>
                            <option value="poda_verde">Poda en verde</option>
                            <option value="tala">Tala / Renovación</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Destino y Reciclaje de Restos</label>
                        <select name="destino_residuos" class="form-select" required>
                            <option value="triturado_acolchado">Triturado in situ para acolchado (mulching)</option>
                            <option value="retirada_reciclaje_biomasa">Retirada y reciclaje para biomasa</option>
                            <option value="compostaje">Compostaje agrícola</option>
                            <option value="quema_autorizada">Quema agrícola autorizada</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nº Permiso de Quema (si aplica)</label>
                        <input type="text" name="numero_permiso_quema" class="form-control" placeholder="Ej: EXP-2026/0451">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Volumen / Peso Estimado (kg/m³)</label>
                        <input type="number" step="10" name="kilos_o_volumen_estimado" class="form-control" placeholder="Ej: 850">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Intensidad de la poda, cuadrilla..."></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-warning px-4 fw-bold">Guardar Registro</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3">Histórico de Podas y Gestión de Residuos</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Finca</th><th>Cultivo</th><th>Operación</th><th>Destino Restos / Reciclaje</th><th>Permiso Quema</th><th>Observaciones</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($podas)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay podas ni talas registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($podas as $p): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
                                <td><strong><?= htmlspecialchars($p['nombre_finca']) ?></strong></td>
                                <td><?= htmlspecialchars($p['nombre_comun']) ?></td>
                                <td><span class="badge bg-warning text-dark"><?= str_replace('_', ' ', $p['tipo_operacion']) ?></span></td>
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">
                                        <i class="fa-solid fa-recycle me-1"></i><?= str_replace('_', ' ', $p['destino_residuos']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($p['numero_permiso_quema'] ?: '-') ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($p['observaciones'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
