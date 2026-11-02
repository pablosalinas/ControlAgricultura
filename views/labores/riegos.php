<?php
$pageTitle = "Control de Riegos y Turnos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_riego'])) {
    $stmt = $db->prepare("
        INSERT INTO riegos (id_plantacion, fecha_riego, hora_inicio, hora_fin, duracion_horas, volumen_m3, parada_utilizada, tablon_utilizado, tipo_riego, con_fertirrigacion, productos_abono, observaciones, creado_por_id)
        VALUES (:plant, :fecha, :hini, :hfin, :dur, :vol, :parada, :tablon, :tipo, :ferti, :abonos, :obs, :user)
    ");
    $stmt->execute([
        ':plant'    => (int)$_POST['id_plantacion'],
        ':fecha'    => $_POST['fecha_riego'],
        ':hini'     => !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null,
        ':hfin'     => !empty($_POST['hora_fin']) ? $_POST['hora_fin'] : null,
        ':dur'      => (float)$_POST['duracion_horas'],
        ':vol'      => !empty($_POST['volumen_m3']) ? (float)$_POST['volumen_m3'] : null,
        ':parada'   => trim($_POST['parada_utilizada'] ?? ''),
        ':tablon'   => trim($_POST['tablon_utilizado'] ?? ''),
        ':tipo'     => $_POST['tipo_riego'] ?? 'goteo',
        ':ferti'    => isset($_POST['con_fertirrigacion']) ? 1 : 0,
        ':abonos'   => trim($_POST['productos_abono'] ?? ''),
        ':obs'      => trim($_POST['observaciones'] ?? ''),
        ':user'     => Auth::user()['id']
    ]);
    $mensaje = "Riego registrado correctamente en el cuaderno de campo.";
}

$plantaciones = $db->query("
    SELECT p.id_plantacion, p.identificador_cuadro, f.nombre_finca, c.nombre_comun, f.parada, f.tablon
    FROM plantaciones p JOIN fincas f ON p.id_finca = f.id_finca JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY f.nombre_finca ASC
")->fetchAll();

$riegos = $db->query("
    SELECT r.*, f.nombre_finca, c.nombre_comun
    FROM riegos r
    JOIN plantaciones p ON r.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY r.fecha_riego DESC, r.id_riego DESC
    LIMIT 50
")->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-faucet-drip text-primary me-2"></i>Control de Riegos y Turnos de Agua</h2>
            <p class="text-muted mb-0">Gestión de tiempos, metros cúbicos, tandas (parada/tablón) y fertirrigación</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/solicitud-riego" class="btn btn-outline-primary fw-semibold shadow-sm">
                <i class="fa-solid fa-file-invoice me-1"></i> Papeleta Oficial Río Alhárabe
            </a>
            <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formRiego">
                <i class="fa-solid fa-plus me-1"></i> Registrar Riego
            </button>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="collapse mb-4" id="formRiego">
        <div class="card card-custom p-4 border-top border-4 border-primary">
            <h5 class="fw-bold mb-3">Nuevo Registro de Riego</h5>
            <form action="<?= APP_URL ?>/riegos" method="POST">
                <input type="hidden" name="guardar_riego" value="1">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Finca y Parcela</label>
                        <select name="id_plantacion" class="form-select" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>"><?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun']) ?> (Parada: <?= htmlspecialchars($pl['parada'] ?? '-') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha de Riego</label>
                        <input type="date" name="fecha_riego" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Sistema de Riego</label>
                        <select name="tipo_riego" class="form-select">
                            <option value="goteo">Goteo localizado</option>
                            <option value="inundacion_pie">Inundación / Pie / Tanda</option>
                            <option value="aspersion">Aspersión</option>
                            <option value="microaspersion">Microaspersión</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Duración (Horas) <span class="text-danger">*</span></label>
                        <input type="number" step="0.25" name="duracion_horas" class="form-control" required placeholder="Ej: 3.5">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Volumen Estimado (m³)</label>
                        <input type="number" step="0.5" name="volumen_m3" class="form-control" placeholder="Ej: 120">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Parada Utilizada</label>
                        <input type="text" name="parada_utilizada" class="form-control" placeholder="Ej: Parada 2">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tablón Utilizado</label>
                        <input type="text" name="tablon_utilizado" class="form-control" placeholder="Ej: Tablón Norte">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="con_fertirrigacion" name="con_fertirrigacion" value="1">
                            <label class="form-check-label fw-bold text-success fs-6" for="con_fertirrigacion">Con Fertirrigación (Abonado)</label>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Abonos / Fertilizantes aplicados</label>
                        <input type="text" name="productos_abono" class="form-control" placeholder="Ej: Nitrato potásico 25kg, Ácido fosfórico 5L...">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Presión en cabezal de filtrado, caudalímetro..."></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Guardar Riego</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3">Histórico de Riegos</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Finca / Cultivo</th><th>Duración</th><th>Volumen m³</th><th>Tanda (Parada/Tablón)</th><th>Fertirrigación</th><th>Observaciones</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($riegos)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay riegos registrados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($riegos as $r): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($r['fecha_riego'])) ?></td>
                                <td><strong><?= htmlspecialchars($r['nombre_finca']) ?></strong><br><span class="small text-muted"><?= htmlspecialchars($r['nombre_comun']) ?></span></td>
                                <td><span class="badge bg-primary text-white"><?= $r['duracion_horas'] ?> horas</span></td>
                                <td><?= $r['volumen_m3'] ? number_format($r['volumen_m3'], 2, ',', '.') . ' m³' : '-' ?></td>
                                <td><?= htmlspecialchars($r['parada_utilizada'] ?: '-') ?> / <?= htmlspecialchars($r['tablon_utilizado'] ?: '-') ?></td>
                                <td>
                                    <?php if ($r['con_fertirrigacion']): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-vial me-1"></i>Sí</span>
                                        <div class="small text-muted"><?= htmlspecialchars($r['productos_abono'] ?? '') ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Agua sola</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
