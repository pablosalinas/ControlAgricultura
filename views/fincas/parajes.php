<?php
$pageTitle = "Maestro de Parajes - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_paraje'])) {
    $nombre = trim($_POST['nombre_paraje'] ?? '');
    $termino = trim($_POST['termino_municipal'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $desc = trim($_POST['descripcion'] ?? '');

    if (empty($nombre) || empty($termino)) {
        $error = "El nombre del paraje y el término municipal son obligatorios.";
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO parajes (id_propietario, nombre_paraje, termino_municipal, provincia, descripcion)
                VALUES (:prop, :nom, :term, :prov, :desc)
            ");
            $stmt->execute([
                ':prop' => $tenantId,
                ':nom'  => $nombre,
                ':term' => $termino,
                ':prov' => $provincia ?: 'Murcia',
                ':desc' => $desc
            ]);
            $mensaje = "Paraje añadido correctamente a la tabla maestra.";
        } catch (Exception $e) {
            $error = "Error al guardar el paraje: " . $e->getMessage();
        }
    }
}

$parajes = $db->query("
    SELECT p.*, COUNT(f.id_finca) AS total_fincas
    FROM parajes p
    LEFT JOIN fincas f ON p.id_paraje = f.id_paraje
    GROUP BY p.id_paraje
    ORDER BY p.nombre_paraje ASC
")->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-location-dot text-primary me-2"></i>Tabla Maestra de Parajes</h2>
            <p class="text-muted mb-0">Estandarización de localizaciones y zonas agrícolas para tus fincas</p>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formParaje">
            <i class="fa-solid fa-plus me-1"></i> Nuevo Paraje
        </button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="collapse mb-4" id="formParaje">
        <div class="card card-custom p-4 border-top border-4 border-primary">
            <h5 class="fw-bold mb-3">Registrar Nuevo Paraje</h5>
            <form action="<?= APP_URL ?>/parajes" method="POST">
                <input type="hidden" name="guardar_paraje" value="1">
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Nombre del Paraje <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_paraje" class="form-control" required placeholder="Ej: Hoya del Pozo, El Carche...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Término Municipal <span class="text-danger">*</span></label>
                        <input type="text" name="termino_municipal" class="form-control" required placeholder="Ej: Cieza, Jumilla, Hellín...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Provincia</label>
                        <input type="text" name="provincia" class="form-control" value="Murcia">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Descripción o características agroclimáticas</label>
                    <textarea name="descripcion" class="form-control" rows="2" placeholder="Orientación, altitud, tipo de suelo dominante..."></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Guardar Paraje</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3">Parajes Registrados</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Paraje</th>
                        <th>Término Municipal</th>
                        <th>Provincia</th>
                        <th>Fincas Ubicadas</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parajes as $p): ?>
                        <tr>
                            <td class="fw-bold text-dark"><i class="fa-solid fa-map-pin text-danger me-2"></i><?= htmlspecialchars($p['nombre_paraje']) ?></td>
                            <td><?= htmlspecialchars($p['termino_municipal']) ?></td>
                            <td><?= htmlspecialchars($p['provincia']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $p['total_fincas'] ?> fincas</span></td>
                            <td class="small text-muted"><?= htmlspecialchars($p['descripcion'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
