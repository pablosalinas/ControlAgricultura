<?php
$pageTitle = "Diario de Evolución del Cultivo - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();

$filtroPlantacion = !empty($_GET['plantacion_id']) ? (int)$_GET['plantacion_id'] : null;

$mensaje = '';
$error = '';

// Procesar nuevo apunte al diario con subida de fotografía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_diario'])) {
    $idPlantacion = (int)$_POST['id_plantacion'];
    $fecha = $_POST['fecha'];
    $fenologia = trim($_POST['estado_fenologico'] ?? '');
    $clima = trim($_POST['temperatura_clima'] ?? '');
    $observacion = trim($_POST['observacion'] ?? '');
    $fotoUrl = null;

    // Procesar foto si se ha subido
    if (isset($_FILES['foto_evidencia']) && $_FILES['foto_evidencia']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['foto_evidencia']['tmp_name'];
        $fileName = $_FILES['foto_evidencia']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed)) {
            $destDir = ROOT_PATH . '/public/uploads/fotos_evolucion';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            $uniqueName = 'cultivo_' . $idPlantacion . '_' . time() . '_' . random_int(100, 999) . '.' . $ext;
            $destFile = $destDir . '/' . $uniqueName;

            if (move_uploaded_file($fileTmp, $destFile)) {
                $fotoUrl = APP_URL . '/uploads/fotos_evolucion/' . $uniqueName;
            }
        } else {
            $error = "Formato de imagen no válido. Solo se admiten JPG, PNG y WEBP.";
        }
    }

    if (empty($observacion)) {
        $error = "La observación o nota de campo es obligatoria.";
    } elseif (!$error) {
        try {
            $stmt = $db->prepare("
                INSERT INTO diario_evolucion (id_plantacion, fecha, estado_fenologico, temperatura_clima, observacion, foto_evidencia_url, creado_por_id)
                VALUES (:plant, :fecha, :feno, :clima, :obs, :foto, :user)
            ");
            $stmt->execute([
                ':plant' => $idPlantacion,
                ':fecha' => $fecha,
                ':feno'  => $fenologia,
                ':clima' => $clima,
                ':obs'   => $observacion,
                ':foto'  => $fotoUrl,
                ':user'  => Auth::user()['id']
            ]);
            $mensaje = "Entrada añadida al diario de evolución con éxito.";
        } catch (Exception $e) {
            $error = "Error al guardar el apunte: " . $e->getMessage();
        }
    }
}

// Plantaciones disponibles
$plantaciones = $db->query("
    SELECT p.id_plantacion, p.identificador_cuadro, f.nombre_finca, c.nombre_comun
    FROM plantaciones p
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    ORDER BY f.nombre_finca ASC
")->fetchAll();

// Obtener apuntes del diario
$sqlDiario = "
    SELECT d.*, f.nombre_finca, c.nombre_comun, p.identificador_cuadro, u.nombre AS autor
    FROM diario_evolucion d
    JOIN plantaciones p ON d.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    LEFT JOIN usuarios u ON d.creado_por_id = u.id_usuario
";
if ($filtroPlantacion) {
    $sqlDiario .= " WHERE d.id_plantacion = " . (int)$filtroPlantacion;
}
$sqlDiario .= " ORDER BY d.fecha DESC, d.id_diario DESC";
$entradas = $db->query($sqlDiario)->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-camera text-info me-2"></i>Diario de Evolución del Cultivo</h2>
            <p class="text-muted mb-0">Seguimiento fenológico, cuaderno fotográfico y registro de incidencias climáticas</p>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formDiario">
            <i class="fa-solid fa-plus me-1"></i> Nueva Nota / Foto
        </button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Formulario Nueva Entrada Diario -->
    <div class="collapse mb-4" id="formDiario">
        <div class="card card-custom p-4 border-top border-4 border-info">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-pen-to-square text-info me-2"></i>Añadir Apunte de Evolución</h5>
            <form action="<?= APP_URL ?>/diario" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="guardar_diario" value="1">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Plantación Objetivo <span class="text-danger">*</span></label>
                        <select name="id_plantacion" class="form-select" required>
                            <option value="">-- Seleccionar Plantación --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>" <?= $filtroPlantacion === (int)$pl['id_plantacion'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun'] . ' (' . $pl['identificador_cuadro'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Estado Fenológico</label>
                        <select name="estado_fenologico" class="form-select">
                            <option value="Brotación">Brotación</option>
                            <option value="Floración">Floración</option>
                            <option value="Cuajado">Cuajado de frutos</option>
                            <option value="Crecimiento vegetativo">Crecimiento vegetativo</option>
                            <option value="Engorde de fruto">Engorde de fruto</option>
                            <option value="Envero">Envero / Cambio de color</option>
                            <option value="Maduración">Maduración</option>
                            <option value="Post-Cosecha">Post-Cosecha / Caída de hoja</option>
                            <option value="Parada invernal">Parada invernal</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Clima / Temperatura / Incidencias</label>
                        <input type="text" name="temperatura_clima" class="form-control" placeholder="Ej: Soleado 24ºC, Lluvia ligera 15mm, Viento moderado...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fa-solid fa-camera text-muted me-1"></i>Subir Fotografía de Campo</label>
                        <input type="file" name="foto_evidencia" class="form-control" accept="image/*" capture="environment">
                        <div class="form-text small">Puedes tomar la foto directamente con la cámara de tu móvil o subir una imagen.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Observaciones y Evolución <span class="text-danger">*</span></label>
                    <textarea name="observacion" class="form-control" rows="3" required placeholder="Describe el estado vegetativo, vigor, sanidad o cualquier síntoma apreciado..."></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-info px-4 fw-bold text-dark">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar en Diario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Timeline / Galería de Entradas del Diario -->
    <div class="row g-4">
        <?php if (empty($entradas)): ?>
            <div class="col-12">
                <div class="card card-custom p-5 text-center text-muted">
                    <i class="fa-solid fa-images fs-1 mb-3"></i>
                    <h5 class="fw-bold">No hay entradas en el diario todavía</h5>
                    <p>Registra el primer seguimiento fotográfico de tus cultivos.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($entradas as $e): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom h-100 overflow-hidden border">
                        <?php if ($e['foto_evidencia_url']): ?>
                            <a href="<?= htmlspecialchars($e['foto_evidencia_url']) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($e['foto_evidencia_url']) ?>" class="card-img-top" alt="Evidencia cultivo" style="height: 220px; object-fit: cover;">
                            </a>
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center text-muted border-bottom" style="height: 120px;">
                                <i class="fa-solid fa-leaf fs-1 opacity-25"></i>
                            </div>
                        <?php endif; ?>

                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-info text-dark fw-bold"><?= htmlspecialchars($e['estado_fenologico'] ?: 'Seguimiento') ?></span>
                                <small class="text-muted fw-bold"><?= date('d/m/Y', strtotime($e['fecha'])) ?></small>
                            </div>

                            <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($e['nombre_finca']) ?></h5>
                            <div class="small text-muted mb-2"><?= htmlspecialchars($e['nombre_comun'] . ' (' . $e['identificador_cuadro'] . ')') ?></div>

                            <p class="card-text text-secondary mb-3 flex-grow-1" style="font-size:0.95rem;">
                                <?= nl2br(htmlspecialchars($e['observacion'])) ?>
                            </p>

                            <?php if ($e['temperatura_clima']): ?>
                                <div class="small text-muted border-top pt-2 mt-auto">
                                    <i class="fa-solid fa-cloud-sun text-warning me-1"></i><?= htmlspecialchars($e['temperatura_clima']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
