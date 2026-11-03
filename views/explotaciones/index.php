<?php
$pageTitle = "Gestión de Explotaciones Agrarias - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$error = '';
$success = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. Conmutar explotación activa (Superadmin o Usuario con acceso a múltiples explotaciones)
    if ($action === 'switch_tenant') {
        $idSwitch = isset($_POST['id_propietario']) ? $_POST['id_propietario'] : null;
        if (Auth::switchTenant($idSwitch)) {
            $success = "Has cambiado a la explotación activa: <strong>" . htmlspecialchars($_SESSION['nombre_propietario']) . "</strong>";
        } else {
            $error = "No tienes permiso para conmutar a esta explotación.";
        }
    }

    // 2. Crear nueva explotación (Solo Superadmin)
    elseif ($action === 'crear' && Auth::isSuperAdmin()) {
        $nombre = trim(isset($_POST['nombre_razon_social']) ? $_POST['nombre_razon_social'] : '');
        $cif = trim(isset($_POST['cif_nif']) ? $_POST['cif_nif'] : '');
        $tel = trim(isset($_POST['telefono']) ? $_POST['telefono'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $dir = trim(isset($_POST['direccion']) ? $_POST['direccion'] : '');
        $mun = trim(isset($_POST['municipio']) ? $_POST['municipio'] : '');
        $prov = trim(isset($_POST['provincia']) ? $_POST['provincia'] : '');
        $cp = trim(isset($_POST['codigo_postal']) ? $_POST['codigo_postal'] : '');

        if (empty($nombre)) {
            $error = "El nombre o razón social de la explotación es obligatorio.";
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO propietarios (nombre_razon_social, cif_nif, telefono, email, direccion, municipio, provincia, codigo_postal, activo, fecha_alta)
                    VALUES (:nom, :cif, :tel, :email, :dir, :mun, :prov, :cp, 1, NOW())
                ");
                $stmt->execute(array(
                    ':nom' => $nombre,
                    ':cif' => $cif ?: null,
                    ':tel' => $tel ?: null,
                    ':email' => $email ?: null,
                    ':dir' => $dir ?: null,
                    ':mun' => $mun ?: null,
                    ':prov' => $prov ?: null,
                    ':cp' => $cp ?: null
                ));
                $newId = (int)$db->lastInsertId();
                $success = "Explotación agraria <strong>" . htmlspecialchars($nombre) . "</strong> creada con éxito.";
                
                // Si el superadmin lo desea, puede conmutar directamente a ella
                if (!empty($_POST['activar_ahora'])) {
                    Auth::switchTenant($newId);
                }
            } catch (Exception $e) {
                $error = "Error al crear la explotación: " . $e->getMessage();
            }
        }
    }

    // 3. Editar explotación existente (Superadmin o Propietario de la misma)
    elseif ($action === 'editar') {
        $idProp = (int)(isset($_POST['id_propietario']) ? $_POST['id_propietario'] : 0);
        
        // Comprobar permisos
        if (Auth::isSuperAdmin() || (Auth::tenantId() == $idProp && $_SESSION['user_rol'] === 'propietario')) {
            $nombre = trim(isset($_POST['nombre_razon_social']) ? $_POST['nombre_razon_social'] : '');
            $cif = trim(isset($_POST['cif_nif']) ? $_POST['cif_nif'] : '');
            $tel = trim(isset($_POST['telefono']) ? $_POST['telefono'] : '');
            $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
            $dir = trim(isset($_POST['direccion']) ? $_POST['direccion'] : '');
            $mun = trim(isset($_POST['municipio']) ? $_POST['municipio'] : '');
            $prov = trim(isset($_POST['provincia']) ? $_POST['provincia'] : '');
            $cp = trim(isset($_POST['codigo_postal']) ? $_POST['codigo_postal'] : '');

            if (empty($nombre)) {
                $error = "El nombre de la explotación no puede quedar vacío.";
            } else {
                try {
                    $stmt = $db->prepare("
                        UPDATE propietarios 
                        SET nombre_razon_social = :nom, cif_nif = :cif, telefono = :tel, email = :email,
                            direccion = :dir, municipio = :mun, provincia = :prov, codigo_postal = :cp
                        WHERE id_propietario = :id
                    ");
                    $stmt->execute(array(
                        ':nom' => $nombre,
                        ':cif' => $cif ?: null,
                        ':tel' => $tel ?: null,
                        ':email' => $email ?: null,
                        ':dir' => $dir ?: null,
                        ':mun' => $mun ?: null,
                        ':prov' => $prov ?: null,
                        ':cp' => $cp ?: null,
                        ':id' => $idProp
                    ));

                    // Subida de Logo de la explotación
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['logo']['tmp_name'];
                        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, array('jpg', 'jpeg', 'png', 'webp', 'gif'))) {
                            $filename = 'logo_exp_' . $idProp . '_' . time() . '.' . $ext;
                            $dest1 = ROOT_PATH . '/uploads/logos/' . $filename;
                            $dest2 = ROOT_PATH . '/public/uploads/logos/' . $filename;
                            @mkdir(dirname($dest1), 0777, true);
                            @mkdir(dirname($dest2), 0777, true);
                            if (move_uploaded_file($tmpName, $dest1) || copy($tmpName, $dest1)) {
                                @copy($dest1, $dest2);
                                $logoRel = 'uploads/logos/' . $filename;
                                $db->prepare("UPDATE propietarios SET logo = :logo WHERE id_propietario = :id")
                                   ->execute(array(':logo' => $logoRel, ':id' => $idProp));
                            }
                        }
                    }

                    Auth::refreshTenantName();
                    $success = "Datos de la explotación <strong>" . htmlspecialchars($nombre) . "</strong> actualizados correctamente.";
                } catch (Exception $e) {
                    $error = "Error al guardar cambios: " . $e->getMessage();
                }
            }
        } else {
            $error = "No tienes permiso para modificar esta explotación.";
        }
    }
}

// Consultar explotaciones accesibles
if (Auth::isSuperAdmin()) {
    $sql = "
        SELECT p.*,
               (SELECT COUNT(*) FROM fincas f WHERE f.id_propietario = p.id_propietario) AS total_fincas,
               (SELECT COUNT(*) FROM usuarios u WHERE u.id_propietario = p.id_propietario) AS total_usuarios
        FROM propietarios p
        ORDER BY p.id_propietario ASC
    ";
    $stmtProps = $db->query($sql);
    $explotaciones = $stmtProps->fetchAll();
} else {
    $myTenant = Auth::tenantId();
    $sql = "
        SELECT p.*,
               (SELECT COUNT(*) FROM fincas f WHERE f.id_propietario = p.id_propietario) AS total_fincas,
               (SELECT COUNT(*) FROM usuarios u WHERE u.id_propietario = p.id_propietario) AS total_usuarios
        FROM propietarios p
        WHERE p.id_propietario = :id
        ORDER BY p.id_propietario ASC
    ";
    $stmtProps = $db->prepare($sql);
    $stmtProps->execute(array(':id' => (int)$myTenant));
    $explotaciones = $stmtProps->fetchAll();
}

$currentTenantId = Auth::tenantId();
?>

<div class="container py-4">

    <!-- Encabezado con Azul Chulo Tecnológico -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-building-wheat fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Gestión de Explotaciones Agrarias</h2>
                <span class="text-muted small">Administra los datos fiscales, ubicación y fincas de cada explotación o empresa agrícola</span>
            </div>
        </div>
        <div>
            <?php if (Auth::isSuperAdmin()): ?>
                <button type="button" class="btn btn-ag shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearExplotacion">
                    <i class="fa-solid fa-plus-circle me-1"></i> Nueva Explotación Agrícola
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alertas -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Banner de Explotación Activa en Sesión -->
    <div class="card card-ag mb-4 border-start border-4 border-primary">
        <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-primary bg-opacity-10 text-primary p-2 fs-6 rounded-pill">
                    <i class="fa-solid fa-satellite-dish me-1"></i> Contexto Actual
                </span>
                <div>
                    <span class="text-muted small">Estás operando actualmente en:</span>
                    <h5 class="fw-bold mb-0 text-dark">
                        <?= htmlspecialchars($_SESSION['nombre_propietario'] ?: 'Sin asignar / Global') ?>
                        <?php if ($currentTenantId): ?>
                            <span class="badge bg-info text-dark ms-2 small">ID #<?= $currentTenantId ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
            </div>
            <?php if (Auth::isSuperAdmin()): ?>
                <form method="POST" action="" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="action" value="switch_tenant">
                    <select name="id_propietario" class="form-select form-select-sm font-monospace" onchange="this.form.submit()">
                        <option value="" <?= empty($currentTenantId) ? 'selected' : '' ?>>🌐 Vista Global (Todas las explotaciones)</option>
                        <?php foreach ($explotaciones as $exp): ?>
                            <option value="<?= $exp['id_propietario'] ?>" <?= ($currentTenantId == $exp['id_propietario']) ? 'selected' : '' ?>>
                                🚜 <?= htmlspecialchars($exp['nombre_razon_social']) ?> (<?= $exp['total_fincas'] ?> fincas)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lista / Tarjetas de Explotaciones -->
    <div class="card card-ag shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-list-check me-2"></i> Explotaciones Agrícolas Registradas (<?= count($explotaciones) ?>)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nombre / Razón Social</th>
                        <th>CIF / NIF</th>
                        <th>Ubicación</th>
                        <th>Contacto</th>
                        <th class="text-center">Fincas</th>
                        <th class="text-center">Usuarios</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($explotaciones)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-building-wheat fa-2x mb-3 text-secondary"></i>
                                <p class="mb-0">No se encontraron explotaciones agrícolas registradas.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($explotaciones as $exp): ?>
                            <?php $isCurrent = ($currentTenantId == $exp['id_propietario']); ?>
                            <tr class="<?= $isCurrent ? 'table-primary bg-opacity-25' : '' ?>">
                                <td class="ps-4 fw-bold font-monospace text-muted">#<?= $exp['id_propietario'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($exp['logo'])): ?>
                                            <img src="<?= APP_URL ?>/<?= htmlspecialchars($exp['logo']) ?>" class="rounded-3 border shadow-sm" style="width: 44px; height: 44px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2 text-center" style="width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fa-solid fa-building-wheat fs-5"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                                <?= htmlspecialchars($exp['nombre_razon_social']) ?>
                                                <?php if ($isCurrent): ?>
                                                    <span class="badge bg-success small"><i class="fa-solid fa-check me-1"></i>En uso</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted">Alta: <?= date('d/m/Y', strtotime($exp['fecha_alta'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <?= htmlspecialchars($exp['cif_nif'] ?: 'No indicado') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small">
                                        <i class="fa-solid fa-location-dot me-1 text-primary"></i>
                                        <?= htmlspecialchars($exp['municipio'] ?: '-') ?> <?= $exp['provincia'] ? '(' . htmlspecialchars($exp['provincia']) . ')' : '' ?>
                                    </div>
                                    <?php if ($exp['direccion']): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 200px;">
                                            <?= htmlspecialchars($exp['direccion']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($exp['telefono']): ?>
                                        <div class="small"><i class="fa-solid fa-phone me-1 text-success"></i><?= htmlspecialchars($exp['telefono']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($exp['email']): ?>
                                        <div class="small text-muted"><i class="fa-solid fa-envelope me-1 text-info"></i><?= htmlspecialchars($exp['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
                                        <i class="fa-solid fa-layer-group me-1"></i><?= $exp['total_fincas'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill fw-bold">
                                        <i class="fa-solid fa-users me-1"></i><?= $exp['total_usuarios'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (Auth::isSuperAdmin()): ?>
                                            <?php if (!$isCurrent): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="action" value="switch_tenant">
                                                    <input type="hidden" name="id_propietario" value="<?= $exp['id_propietario'] ?>">
                                                    <button type="submit" class="btn btn-outline-primary" title="Trabajar en esta explotación">
                                                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Seleccionar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Botón Editar -->
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditar_<?= $exp['id_propietario'] ?>" title="Editar datos">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                                        </button>
                                    </div>

                                    <!-- Modal Editar Explotación -->
                                    <div class="modal fade text-start" id="modalEditar_<?= $exp['id_propietario'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Editar Explotación: <?= htmlspecialchars($exp['nombre_razon_social']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="" enctype="multipart/form-data">
                                                    <input type="hidden" name="action" value="editar">
                                                    <input type="hidden" name="id_propietario" value="<?= $exp['id_propietario'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3">
                                                            <!-- Logo de la Explotación -->
                                                            <div class="col-12 bg-light p-3 rounded-3 border mb-2">
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <?php if (!empty($exp['logo'])): ?>
                                                                        <img src="<?= APP_URL ?>/<?= htmlspecialchars($exp['logo']) ?>" class="rounded-3 border shadow-sm" style="width: 60px; height: 60px; object-fit: cover;">
                                                                    <?php else: ?>
                                                                        <div class="rounded-3 bg-white border p-3 text-muted text-center" style="width: 60px; height: 60px;">
                                                                            <i class="fa-solid fa-image fs-4"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div class="flex-grow-1">
                                                                        <label class="form-label fw-bold small mb-1">Logotipo Personalizado de la Explotación</label>
                                                                        <input type="file" name="logo" class="form-control form-control-sm" accept="image/*">
                                                                        <div class="form-text small">Sube una imagen representativa (JPG, PNG, WebP). Si no se sube, se usará el logotipo oficial de la web.</div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-md-8">
                                                                <label class="form-label fw-semibold">Nombre o Razón Social *</label>
                                                                <input type="text" name="nombre_razon_social" class="form-control" value="<?= htmlspecialchars($exp['nombre_razon_social']) ?>" required>
                                                                <div class="form-text small">Ejemplo: Finca El Alcaparro, Agrícola del Segura S.L.</div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">CIF / NIF</label>
                                                                <input type="text" name="cif_nif" class="form-control font-monospace" value="<?= htmlspecialchars($exp['cif_nif'] ?: '') ?>" placeholder="B12345678">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Teléfono de Contacto</label>
                                                                <input type="tel" name="telefono" class="form-control" value="<?= htmlspecialchars($exp['telefono'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Correo Electrónico</label>
                                                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($exp['email'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label fw-semibold">Dirección / Sede</label>
                                                                <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($exp['direccion'] ?: '') ?>" placeholder="Paraje El Pozo s/n">
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label fw-semibold">Municipio</label>
                                                                <input type="text" name="municipio" class="form-control" value="<?= htmlspecialchars($exp['municipio'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Provincia</label>
                                                                <input type="text" name="provincia" class="form-control" value="<?= htmlspecialchars($exp['provincia'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold">Código Postal</label>
                                                                <input type="text" name="codigo_postal" class="form-control font-monospace" value="<?= htmlspecialchars($exp['codigo_postal'] ?: '') ?>">
                                                            </div>

                                                            <!-- Aviso: Precios y Tarifas de Riego fijados por la Comunidad de Regantes -->
                                                            <div class="col-12 mt-3">
                                                                <div class="p-3 bg-light rounded-3 border d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                                                                    <div>
                                                                        <div class="fw-bold text-primary small"><i class="fa-solid fa-water me-1"></i>Tarifas y Precios de Riego</div>
                                                                        <div class="small text-muted">Las tarifas de agua (tanda, comunitaria, comunitaria hortalizas) y derramas no se fijan por explotación, sino directamente en la Comunidad de Regantes seleccionada.</div>
                                                                    </div>
                                                                    <a href="<?= APP_URL ?>/comunidades-regantes" class="btn btn-sm btn-outline-primary text-nowrap align-self-start align-self-sm-center" target="_blank">
                                                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Comunidades de Regantes
                                                                    </a>
                                                                </div>
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

<!-- Modal Crear Nueva Explotación (Superadmin) -->
<?php if (Auth::isSuperAdmin()): ?>
<div class="modal fade" id="modalCrearExplotacion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-plus-circle me-2"></i> Alta de Nueva Explotación Agraria
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nombre o Razón Social de la Explotación *</label>
                            <input type="text" name="nombre_razon_social" class="form-control" placeholder="Ej: Finca Las Moreras, Frutas del Noroeste S.L." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">CIF / NIF</label>
                            <input type="text" name="cif_nif" class="form-control font-monospace" placeholder="B12345678">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="tel" name="telefono" class="form-control" placeholder="600 000 000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control" placeholder="contacto@finca.es">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección</label>
                            <input type="text" name="direccion" class="form-control" placeholder="Carretera de Moratalla km 3">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Municipio</label>
                            <input type="text" name="municipio" class="form-control" placeholder="Moratalla">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Provincia</label>
                            <input type="text" name="provincia" class="form-control" value="Murcia">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Código Postal</label>
                            <input type="text" name="codigo_postal" class="form-control font-monospace" placeholder="30440">
                        </div>
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activar_ahora" id="chkActivar" value="1" checked>
                                <label class="form-check-label small fw-semibold" for="chkActivar">
                                    Conmutar y empezar a trabajar inmediatamente en esta nueva explotación
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-ag">
                        <i class="fa-solid fa-check me-1"></i> Registrar Explotación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
