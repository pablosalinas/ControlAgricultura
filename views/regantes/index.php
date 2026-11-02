<?php
$pageTitle = "Censo de Socios Regantes - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// Procesar CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // CREAR REGANTE
    if ($action === 'crear_regante') {
        $numRegante = strtoupper(trim($_POST['num_regante'] ?? ''));
        $nombre = trim($_POST['nombre_completo'] ?? '');
        $cif = trim($_POST['cif_nif'] ?? '');
        $dir = trim($_POST['direccion'] ?? '');
        $mun = trim($_POST['municipio'] ?? '');
        $tel = trim($_POST['telefono'] ?? '');
        $banco = trim($_POST['banco_habitual'] ?? 'CAJAMAR');
        $hTanda = (float)($_POST['horas_tanda_disponibles'] ?? 0);
        $m3Tanda = (float)($_POST['volumen_m3_tanda'] ?? 0);
        $hComunitaria = (float)($_POST['horas_comunitaria_disponibles'] ?? 0);
        $m3Comunitaria = (float)($_POST['volumen_m3_comunitaria'] ?? 0);
        $parada = trim($_POST['parada_habitual'] ?? '');
        $paraje = trim($_POST['paraje_habitual'] ?? '');
        $tablon = trim($_POST['tablon_habitual'] ?? '');
        $notas = trim($_POST['notas'] ?? '');

        if (empty($numRegante) || empty($nombre)) {
            $error = "El número de regante y el nombre completo son obligatorios.";
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO regantes (
                        id_propietario, num_regante, nombre_completo, cif_nif, direccion, municipio,
                        telefono, banco_habitual, horas_tanda_disponibles, volumen_m3_tanda,
                        horas_comunitaria_disponibles, volumen_m3_comunitaria, parada_habitual,
                        paraje_habitual, tablon_habitual, notas, activo
                    ) VALUES (
                        :prop, :num, :nom, :cif, :dir, :mun,
                        :tel, :banco, :ht, :m3t,
                        :hc, :m3c, :parada,
                        :paraje, :tablon, :notas, 1
                    )
                ");
                $stmt->execute([
                    ':prop'    => $tenantId ?: 1,
                    ':num'     => $numRegante,
                    ':nom'     => $nombre,
                    ':cif'     => $cif ?: null,
                    ':dir'     => $dir ?: null,
                    ':mun'     => $mun ?: null,
                    ':tel'     => $tel ?: null,
                    ':banco'   => $banco ?: 'CAJAMAR',
                    ':ht'      => $hTanda,
                    ':m3t'     => $m3Tanda,
                    ':hc'      => $hComunitaria,
                    ':m3c'     => $m3Comunitaria,
                    ':parada'  => $parada ?: null,
                    ':paraje'  => $paraje ?: null,
                    ':tablon'  => $tablon ?: null,
                    ':notas'   => $notas ?: null
                ]);
                $mensaje = "Socio regante <strong>" . htmlspecialchars($nombre) . " (" . htmlspecialchars($numRegante) . ")</strong> dado de alta con éxito.";
            } catch (Exception $e) {
                $error = "Error al crear socio regante: " . $e->getMessage();
            }
        }
    }

    // EDITAR REGANTE
    elseif ($action === 'editar_regante') {
        $idReg = (int)($_POST['id_regante'] ?? 0);
        $numRegante = strtoupper(trim($_POST['num_regante'] ?? ''));
        $nombre = trim($_POST['nombre_completo'] ?? '');
        $cif = trim($_POST['cif_nif'] ?? '');
        $dir = trim($_POST['direccion'] ?? '');
        $mun = trim($_POST['municipio'] ?? '');
        $tel = trim($_POST['telefono'] ?? '');
        $banco = trim($_POST['banco_habitual'] ?? 'CAJAMAR');
        $hTanda = (float)($_POST['horas_tanda_disponibles'] ?? 0);
        $m3Tanda = (float)($_POST['volumen_m3_tanda'] ?? 0);
        $hComunitaria = (float)($_POST['horas_comunitaria_disponibles'] ?? 0);
        $m3Comunitaria = (float)($_POST['volumen_m3_comunitaria'] ?? 0);
        $parada = trim($_POST['parada_habitual'] ?? '');
        $paraje = trim($_POST['paraje_habitual'] ?? '');
        $tablon = trim($_POST['tablon_habitual'] ?? '');
        $notas = trim($_POST['notas'] ?? '');

        if (empty($numRegante) || empty($nombre)) {
            $error = "El número de regante y el nombre completo son obligatorios.";
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE regantes SET
                        num_regante = :num,
                        nombre_completo = :nom,
                        cif_nif = :cif,
                        direccion = :dir,
                        municipio = :mun,
                        telefono = :tel,
                        banco_habitual = :banco,
                        horas_tanda_disponibles = :ht,
                        volumen_m3_tanda = :m3t,
                        horas_comunitaria_disponibles = :hc,
                        volumen_m3_comunitaria = :m3c,
                        parada_habitual = :parada,
                        paraje_habitual = :paraje,
                        tablon_habitual = :tablon,
                        notas = :notas
                    WHERE id_regante = :id
                ");
                $stmt->execute([
                    ':num'     => $numRegante,
                    ':nom'     => $nombre,
                    ':cif'     => $cif ?: null,
                    ':dir'     => $dir ?: null,
                    ':mun'     => $mun ?: null,
                    ':tel'     => $tel ?: null,
                    ':banco'   => $banco ?: 'CAJAMAR',
                    ':ht'      => $hTanda,
                    ':m3t'     => $m3Tanda,
                    ':hc'      => $hComunitaria,
                    ':m3c'     => $m3Comunitaria,
                    ':parada'  => $parada ?: null,
                    ':paraje'  => $paraje ?: null,
                    ':tablon'  => $tablon ?: null,
                    ':notas'   => $notas ?: null,
                    ':id'      => $idReg
                ]);
                $mensaje = "Datos del socio <strong>" . htmlspecialchars($numRegante) . " - " . htmlspecialchars($nombre) . "</strong> actualizados correctamente.";
            } catch (Exception $e) {
                $error = "Error al actualizar regante: " . $e->getMessage();
            }
        }
    }

    // ELIMINAR REGANTE
    elseif ($action === 'eliminar_regante') {
        $idReg = (int)($_POST['id_regante'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM regantes WHERE id_regante = :id");
            $stmt->execute([':id' => $idReg]);
            $mensaje = "Socio regante eliminado correctamente del censo.";
        } catch (Exception $e) {
            $error = "Error al eliminar regante: " . $e->getMessage();
        }
    }
}

// Filtro de búsqueda
$q = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM regantes WHERE activo = 1";
$params = [];

if (!empty($q)) {
    $sql .= " AND (num_regante LIKE :q OR nombre_completo LIKE :q OR telefono LIKE :q OR cif_nif LIKE :q OR paraje_habitual LIKE :q OR parada_habitual LIKE :q)";
    $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY num_regante ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$regantes = $stmt->fetchAll();

// Estadísticas de cabecera
$totalSocios = count($regantes);
$totalHorasTanda = 0;
$totalHorasComunitaria = 0;
foreach ($regantes as $r) {
    $totalHorasTanda += (float)$r['horas_tanda_disponibles'];
    $totalHorasComunitaria += (float)$r['horas_comunitaria_disponibles'];
}
?>

<div class="container py-4">

    <!-- Encabezado con Estilo Corporativo Azul Tecnológico -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-address-book fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Censo de Socios Regantes</h2>
                <span class="text-muted small">Comunidad de Regantes del Río Alhárabe (Moratalla) • Gestión de Cuotas y Horas de Riego</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/solicitud-riego" class="btn btn-outline-primary fw-semibold">
                <i class="fa-solid fa-file-invoice me-1"></i> Rellenar Papeleta Riego
            </a>
            <button type="button" class="btn btn-ag shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevoRegante">
                <i class="fa-solid fa-user-plus me-1"></i> Nuevo Socio Regante
            </button>
        </div>
    </div>

    <!-- Alertas -->
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tarjetas de Métricas -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-custom p-3 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Total Socios Registrados</div>
                        <div class="fs-3 fw-bold text-dark"><?= $totalSocios ?></div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="fa-solid fa-users fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Horas Disponibles en Tanda</div>
                        <div class="fs-3 fw-bold text-dark"><?= number_format($totalHorasTanda, 2, ',', '.') ?> <span class="fs-6 text-muted">h</span></div>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-hourglass-half fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Horas Disponibles Comunitaria</div>
                        <div class="fs-3 fw-bold text-dark"><?= number_format($totalHorasComunitaria, 2, ',', '.') ?> <span class="fs-6 text-muted">h</span></div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-droplet fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Buscador y Tabla -->
    <div class="card card-custom p-4">
        <div class="row g-2 align-items-center mb-3">
            <div class="col-md-6">
                <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-list-check me-2"></i>Padrón de Regantes</h5>
            </div>
            <div class="col-md-6">
                <form method="GET" action="" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control font-monospace" placeholder="Buscar por Nº Socio (ej. A1188), nombre, teléfono o paraje..." value="<?= htmlspecialchars($q) ?>">
                    <button type="submit" class="btn btn-primary px-3"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <?php if (!empty($q)): ?>
                        <a href="<?= APP_URL ?>/regantes" class="btn btn-outline-secondary" title="Limpiar filtro"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nº Socio</th>
                        <th>Nombre y DNI / CIF</th>
                        <th>Domicilio y Contacto</th>
                        <th>Banco</th>
                        <th class="text-center">Tanda (h / m³)</th>
                        <th class="text-center">Comunitaria (h / m³)</th>
                        <th>Parada / Paraje / Tablón</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($regantes)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-user-slash fs-1 d-block mb-2 opacity-50"></i>
                                No se encontraron socios regantes registrados<?= !empty($q) ? ' con el filtro indicado' : '' ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($regantes as $r): ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace fs-6 px-2 py-1">
                                        <?= htmlspecialchars($r['num_regante']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($r['nombre_completo']) ?></div>
                                    <?php if ($r['cif_nif']): ?>
                                        <div class="small text-muted font-monospace"><i class="fa-solid fa-id-card me-1"></i><?= htmlspecialchars($r['cif_nif']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['telefono']): ?>
                                        <div class="small fw-semibold text-dark"><i class="fa-solid fa-phone me-1 text-success"></i><?= htmlspecialchars($r['telefono']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($r['direccion'] || $r['municipio']): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars(trim($r['direccion'] . ' ' . $r['municipio'])) ?>">
                                            <i class="fa-solid fa-location-dot me-1 text-primary"></i><?= htmlspecialchars(trim($r['direccion'] . ($r['municipio'] ? ', ' . $r['municipio'] : ''))) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($r['banco_habitual'] ?: 'CAJAMAR') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info bg-opacity-15 text-dark fw-bold">
                                        <?= number_format($r['horas_tanda_disponibles'], 2) ?> h
                                    </span>
                                    <?php if ($r['volumen_m3_tanda'] > 0): ?>
                                        <div class="small text-muted"><?= number_format($r['volumen_m3_tanda'], 0, ',', '.') ?> m³</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success bg-opacity-15 text-dark fw-bold">
                                        <?= number_format($r['horas_comunitaria_disponibles'], 2) ?> h
                                    </span>
                                    <?php if ($r['volumen_m3_comunitaria'] > 0): ?>
                                        <div class="small text-muted"><?= number_format($r['volumen_m3_comunitaria'], 0, ',', '.') ?> m³</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <strong>Parada:</strong> <?= htmlspecialchars($r['parada_habitual'] ?: '-') ?><br>
                                        <strong>Paraje:</strong> <?= htmlspecialchars($r['paraje_habitual'] ?: '-') ?>
                                        <?php if ($r['tablon_habitual']): ?>
                                            | <strong>Tablón:</strong> <?= htmlspecialchars($r['tablon_habitual']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= APP_URL ?>/solicitud-riego?regante_id=<?= $r['id_regante'] ?>" class="btn btn-outline-primary" title="Generar Solicitud de Riego para este socio">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditar_<?= $r['id_regante'] ?>" title="Editar">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar al socio <?= htmlspecialchars($r['num_regante']) ?>?');">
                                            <input type="hidden" name="action" value="eliminar_regante">
                                            <input type="hidden" name="id_regante" value="<?= $r['id_regante'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Editar Regante -->
                                    <div class="modal fade text-start" id="modalEditar_<?= $r['id_regante'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Editar Socio Regante: <?= htmlspecialchars($r['num_regante']) ?> - <?= htmlspecialchars($r['nombre_completo']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="editar_regante">
                                                    <input type="hidden" name="id_regante" value="<?= $r['id_regante'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Nº Regante (Alfanumérico) *</label>
                                                                <input type="text" name="num_regante" class="form-control font-monospace fw-bold" value="<?= htmlspecialchars($r['num_regante']) ?>" required>
                                                            </div>
                                                            <div class="col-md-8">
                                                                <label class="form-label fw-semibold">Nombre Completo del Socio *</label>
                                                                <input type="text" name="nombre_completo" class="form-control fw-bold" value="<?= htmlspecialchars($r['nombre_completo']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">DNI / CIF</label>
                                                                <input type="text" name="cif_nif" class="form-control font-monospace" value="<?= htmlspecialchars($r['cif_nif'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Teléfono</label>
                                                                <input type="tel" name="telefono" class="form-control" value="<?= htmlspecialchars($r['telefono'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Entidad Bancaria</label>
                                                                <input type="text" name="banco_habitual" class="form-control" value="<?= htmlspecialchars($r['banco_habitual'] ?: 'CAJAMAR') ?>">
                                                            </div>
                                                            <div class="col-md-8">
                                                                <label class="form-label fw-semibold">Dirección / Domicilio</label>
                                                                <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($r['direccion'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Municipio</label>
                                                                <input type="text" name="municipio" class="form-control" value="<?= htmlspecialchars($r['municipio'] ?: '') ?>">
                                                            </div>

                                                            <div class="col-12 mt-3">
                                                                <h6 class="fw-bold text-primary border-bottom pb-1"><i class="fa-solid fa-water me-1"></i>Derechos y Horas Disponibles</h6>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Horas Tanda</label>
                                                                <input type="number" step="0.25" name="horas_tanda_disponibles" class="form-control font-monospace" value="<?= $r['horas_tanda_disponibles'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Volumen m³ Tanda</label>
                                                                <input type="number" step="1" name="volumen_m3_tanda" class="form-control font-monospace" value="<?= $r['volumen_m3_tanda'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Horas Comunitaria</label>
                                                                <input type="number" step="0.25" name="horas_comunitaria_disponibles" class="form-control font-monospace" value="<?= $r['horas_comunitaria_disponibles'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Volumen m³ Comunitaria</label>
                                                                <input type="number" step="1" name="volumen_m3_comunitaria" class="form-control font-monospace" value="<?= $r['volumen_m3_comunitaria'] ?>">
                                                            </div>

                                                            <div class="col-12 mt-3">
                                                                <h6 class="fw-bold text-primary border-bottom pb-1"><i class="fa-solid fa-location-crosshairs me-1"></i>Localización Habitual en Riegos</h6>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Parada Habitual</label>
                                                                <input type="text" name="parada_habitual" class="form-control" value="<?= htmlspecialchars($r['parada_habitual'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Paraje Habitual</label>
                                                                <input type="text" name="paraje_habitual" class="form-control" value="<?= htmlspecialchars($r['paraje_habitual'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Tablón Habitual</label>
                                                                <input type="text" name="tablon_habitual" class="form-control font-monospace" value="<?= htmlspecialchars($r['tablon_habitual'] ?: '') ?>">
                                                            </div>

                                                            <div class="col-12">
                                                                <label class="form-label fw-semibold small">Notas / Observaciones</label>
                                                                <textarea name="notas" class="form-control" rows="2"><?= htmlspecialchars($r['notas'] ?: '') ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-ag fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Cambios</button>
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

<!-- Modal Nuevo Socio Regante -->
<div class="modal fade text-start" id="modalNuevoRegante" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-user-plus me-2"></i> Alta de Nuevo Socio Regante
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear_regante">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nº Regante (Alfanumérico) *</label>
                            <input type="text" name="num_regante" class="form-control font-monospace fw-bold" placeholder="Ej: A1188" required>
                            <div class="form-text small">Código identificativo en la Comunidad.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nombre Completo del Socio *</label>
                            <input type="text" name="nombre_completo" class="form-control fw-bold" placeholder="Nombre y apellidos del regante" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">DNI / CIF</label>
                            <input type="text" name="cif_nif" class="form-control font-monospace" placeholder="12345678Z">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="tel" name="telefono" class="form-control" placeholder="600 000 000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Entidad Bancaria</label>
                            <input type="text" name="banco_habitual" class="form-control" value="CAJAMAR">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Dirección / Domicilio</label>
                            <input type="text" name="direccion" class="form-control" placeholder="Calle, número, piso...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Municipio</label>
                            <input type="text" name="municipio" class="form-control" value="Moratalla">
                        </div>

                        <div class="col-12 mt-3">
                            <h6 class="fw-bold text-primary border-bottom pb-1"><i class="fa-solid fa-water me-1"></i>Derechos y Horas Disponibles</h6>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Horas Tanda</label>
                            <input type="number" step="0.25" name="horas_tanda_disponibles" class="form-control font-monospace" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Volumen m³ Tanda</label>
                            <input type="number" step="1" name="volumen_m3_tanda" class="form-control font-monospace" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Horas Comunitaria</label>
                            <input type="number" step="0.25" name="horas_comunitaria_disponibles" class="form-control font-monospace" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Volumen m³ Comunitaria</label>
                            <input type="number" step="1" name="volumen_m3_comunitaria" class="form-control font-monospace" value="0.00">
                        </div>

                        <div class="col-12 mt-3">
                            <h6 class="fw-bold text-primary border-bottom pb-1"><i class="fa-solid fa-location-crosshairs me-1"></i>Localización Habitual en Riegos</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Parada Habitual</label>
                            <input type="text" name="parada_habitual" class="form-control" placeholder="Ej: Perales (067)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Paraje Habitual</label>
                            <input type="text" name="paraje_habitual" class="form-control" placeholder="Ej: Perales">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Tablón Habitual</label>
                            <input type="text" name="tablon_habitual" class="form-control font-monospace" placeholder="Ej: 2">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Notas / Observaciones</label>
                            <textarea name="notas" class="form-control" rows="2" placeholder="Cesiones acordadas, histórico de turnos..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-ag fw-bold"><i class="fa-solid fa-plus-circle me-1"></i>Dar de Alta Socio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
