<?php
$pageTitle = "Recolecciones y Rendimientos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cosecha'])) {
    $kilos = (float)$_POST['kilos_totales'];
    $precio = !empty($_POST['precio_kilo_estimado']) ? (float)$_POST['precio_kilo_estimado'] : null;
    $importe = ($precio !== null) ? round($kilos * $precio, 2) : null;

    $stmt = $db->prepare("
        INSERT INTO recolecciones (id_plantacion, fecha_recoleccion, producto_cosechado, kilos_totales, calidad, precio_kilo_estimado, importe_total, albaran_o_destino, observaciones, creado_por_id)
        VALUES (:plant, :fecha, :prod, :kilos, :calidad, :precio, :importe, :alb, :obs, :user)
    ");
    $stmt->execute([
        ':plant'    => (int)$_POST['id_plantacion'],
        ':fecha'    => $_POST['fecha_recoleccion'],
        ':prod'     => trim($_POST['producto_cosechado']),
        ':kilos'    => $kilos,
        ':calidad'  => $_POST['calidad'],
        ':precio'   => $precio,
        ':importe'  => $importe,
        ':alb'      => trim($_POST['albaran_o_destino'] ?? ''),
        ':obs'      => trim($_POST['observaciones'] ?? ''),
        ':user'     => Auth::user()['id']
    ]);
    $mensaje = "Cosecha y pesada guardada con éxito.";
}

$plantaciones = $db->query("
    SELECT p.id_plantacion, p.identificador_cuadro, f.nombre_finca, c.nombre_comun
    FROM plantaciones p JOIN fincas f ON p.id_finca = f.id_finca JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY f.nombre_finca ASC
")->fetchAll();

$cosechas = $db->query("
    SELECT r.*, f.nombre_finca, c.nombre_comun
    FROM recolecciones r
    JOIN plantaciones p ON r.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY r.fecha_recoleccion DESC, r.id_recoleccion DESC
    LIMIT 50
")->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-basket-shopping text-success me-2"></i>Recolecciones, Cosechas y Liquidaciones</h2>
            <p class="text-muted mb-0">Pesadas, calidades, albaranes de entrega y rendimientos económicos</p>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formCosecha">
            <i class="fa-solid fa-plus me-1"></i> Registrar Recolección
        </button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="collapse mb-4" id="formCosecha">
        <div class="card card-custom p-4 border-top border-4 border-success">
            <h5 class="fw-bold mb-3">Nuevo Registro de Recolección</h5>
            <form action="<?= APP_URL ?>/recolecciones" method="POST">
                <input type="hidden" name="guardar_cosecha" value="1">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Finca y Cultivo</label>
                        <select name="id_plantacion" class="form-select" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>"><?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha</label>
                        <input type="date" name="fecha_recoleccion" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Producto / Variedad Cosechada</label>
                        <input type="text" name="producto_cosechado" class="form-control" required placeholder="Ej: Melocotón Romea">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Kilos Totales <span class="text-danger">*</span></label>
                        <input type="number" step="0.5" name="kilos_totales" class="form-control" required placeholder="Ej: 4500">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Calidad / Categoría</label>
                        <select name="calidad" class="form-select">
                            <option value="primera">Primera (Extra/1ª)</option>
                            <option value="segunda">Segunda (2ª)</option>
                            <option value="tercera">Tercera</option>
                            <option value="destrio">Destrío</option>
                            <option value="industria">Para Industria / Zumo / Conserva</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Precio / Kilo (€) (opcional)</label>
                        <input type="number" step="0.001" name="precio_kilo_estimado" class="form-control" placeholder="0.850">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Albarán / Cooperativa / Comprador</label>
                        <input type="text" name="albaran_o_destino" class="form-control" placeholder="Ej: Coop. del Campo Alb. 4810">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Grados Brix, dureza, calibre medio..."></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-success px-4 fw-bold">Guardar Cosecha</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3">Histórico de Cosechas</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Finca</th><th>Producto</th><th>Kilos</th><th>Calidad</th><th>Precio/kg</th><th>Importe Total</th><th>Albarán/Destino</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($cosechas)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay cosechas registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cosechas as $c): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($c['fecha_recoleccion'])) ?></td>
                                <td><strong><?= htmlspecialchars($c['nombre_finca']) ?></strong></td>
                                <td><?= htmlspecialchars($c['producto_cosechado']) ?></td>
                                <td><span class="badge bg-success fs-6"><?= number_format($c['kilos_totales'], 2, ',', '.') ?> kg</span></td>
                                <td><span class="badge bg-light text-dark border text-capitalize"><?= htmlspecialchars($c['calidad']) ?></span></td>
                                <td><?= $c['precio_kilo_estimado'] !== null ? number_format($c['precio_kilo_estimado'], 3, ',', '.') . ' €' : '-' ?></td>
                                <td><strong><?= $c['importe_total'] !== null ? number_format($c['importe_total'], 2, ',', '.') . ' €' : '-' ?></strong></td>
                                <td class="small text-muted"><?= htmlspecialchars($c['albaran_o_destino'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
