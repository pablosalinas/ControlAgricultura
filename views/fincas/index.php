<?php
$pageTitle = "Gestión de Fincas y Parcelas - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR ACCIONES CRUD (EDITAR, BORRAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // EDITAR FINCA
    if ($action === 'editar_finca') {
        $idFinca = (int)$_POST['id_finca'];
        $nombreFinca = trim(isset($_POST['nombre_finca']) ? $_POST['nombre_finca'] : '');
        $idParaje = !empty($_POST['id_paraje']) ? (int)$_POST['id_paraje'] : null;
        $idComunidad = !empty($_POST['id_comunidad']) ? (int)$_POST['id_comunidad'] : null;
        $superficie = (float)(isset($_POST['superficie_ha']) ? $_POST['superficie_ha'] : 0);
        $costeIbi = (float)(isset($_POST['coste_anual_ibi']) ? $_POST['coste_anual_ibi'] : 0);
        $poligono = trim(isset($_POST['poligono']) ? $_POST['poligono'] : '');
        $parcela = trim(isset($_POST['parcela']) ? $_POST['parcela'] : '');
        $refCatastral = trim(isset($_POST['referencia_catastral']) ? $_POST['referencia_catastral'] : '');
        $zonaRiego = trim(isset($_POST['zona_riego']) ? $_POST['zona_riego'] : '');
        $parada = trim(isset($_POST['parada']) ? $_POST['parada'] : '');
        $tablon = trim(isset($_POST['tablon']) ? $_POST['tablon'] : '');
        $numSocio = trim(isset($_POST['num_socio_comunidad']) ? $_POST['num_socio_comunidad'] : '');
        $horasProp = (float)(isset($_POST['horas_propiedad_tanda']) ? $_POST['horas_propiedad_tanda'] : 0);
        $horasDisp = (float)(isset($_POST['horas_disponibles_tanda']) ? $_POST['horas_disponibles_tanda'] : 0);

        if ($idComunidad) {
            $nomCom = $db->query("SELECT nombre_comunidad FROM comunidades_regantes WHERE id_comunidad = " . (int)$idComunidad)->fetchColumn();
            if ($nomCom) {
                $zonaRiego = $nomCom;
            }
        }

        if (empty($nombreFinca) || $superficie <= 0) {
            $error = "El nombre de la finca y una superficie mayor a cero son obligatorios.";
        } else {
            try {
                $stmtUpd = $db->prepare("
                    UPDATE fincas
                    SET nombre_finca = :nom, id_paraje = :paraje, id_comunidad = :comunidad, superficie_ha = :sup,
                        coste_anual_ibi = :ibi,
                        poligono = :pol, parcela = :parc, referencia_catastral = :ref,
                        zona_riego = :zona, parada = :parada, tablon = :tablon,
                        num_socio_comunidad = :socio, horas_propiedad_tanda = :hprop,
                        horas_disponibles_tanda = :hdisp
                    WHERE id_finca = :id
                ");
                $stmtUpd->execute(array(
                    ':nom'       => $nombreFinca,
                    ':paraje'    => $idParaje,
                    ':comunidad' => $idComunidad,
                    ':sup'       => $superficie,
                    ':ibi'       => $costeIbi,
                    ':pol'       => $poligono ?: null,
                    ':parc'      => $parcela ?: null,
                    ':ref'       => $refCatastral ?: null,
                    ':zona'      => $zonaRiego ?: null,
                    ':parada'    => $parada ?: null,
                    ':tablon'    => $tablon ?: null,
                    ':socio'     => $numSocio ?: null,
                    ':hprop'     => $horasProp,
                    ':hdisp'     => $horasDisp,
                    ':id'        => $idFinca
                ));
                $mensaje = "Finca <strong>" . htmlspecialchars($nombreFinca) . "</strong> actualizada correctamente.";
            } catch (Exception $e) {
                $error = "Error al actualizar finca: " . $e->getMessage();
            }
        }
    }

    // BORRAR FINCA
    elseif ($action === 'borrar_finca') {
        $idFinca = (int)$_POST['id_finca'];
        try {
            $chk = $db->prepare("SELECT COUNT(*) FROM plantaciones WHERE id_finca = :id");
            $chk->execute(array(':id' => $idFinca));
            if ($chk->fetchColumn() > 0) {
                $error = "No se puede eliminar esta finca porque contiene plantaciones o cultivos asignados. Debes eliminar primero las plantaciones asociadas.";
            } else {
                $stmtDel = $db->prepare("DELETE FROM fincas WHERE id_finca = :id");
                $stmtDel->execute(array(':id' => $idFinca));
                $mensaje = "Finca eliminada correctamente del registro.";
            }
        } catch (Exception $e) {
            $error = "Error al eliminar finca: " . $e->getMessage();
        }
    }
}

// Parajes para modal de edición
$parajes = $db->query("SELECT id_paraje, nombre_paraje, termino_municipal FROM parajes ORDER BY nombre_paraje ASC")->fetchAll();

// Cargar equivalencia de Fanegas por Hectárea desde configuración (por defecto 3.00)
$cfgFan = $db->query("SELECT fanegas_por_hectarea FROM configuracion_web LIMIT 1")->fetch();
$fanegasPorHa = (!empty($cfgFan['fanegas_por_hectarea'])) ? (float)$cfgFan['fanegas_por_hectarea'] : 3.0000;
if ($fanegasPorHa <= 0) {
    $fanegasPorHa = 3.0000;
}

// Cargar comunidades vinculadas a la explotación activa para los modales
$comunidades = array();
if ($tenantId) {
    $stmtCom = $db->prepare("
        SELECT c.id_comunidad, c.nombre_comunidad, c.es_predeterminada
        FROM comunidades_regantes c
        JOIN explotaciones_comunidades ec ON c.id_comunidad = ec.id_comunidad
        WHERE ec.id_propietario = :prop AND c.activo = 1
        ORDER BY c.es_predeterminada DESC, c.nombre_comunidad ASC
    ");
    $stmtCom->execute(array(':prop' => $tenantId));
    $comunidades = $stmtCom->fetchAll();
}
if (empty($comunidades)) {
    $comunidades = $db->query("SELECT id_comunidad, nombre_comunidad, es_predeterminada FROM comunidades_regantes WHERE activo = 1 ORDER BY es_predeterminada DESC, nombre_comunidad ASC")->fetchAll();
}

// Filtros
$filtroParaje = isset($_GET['paraje_id']) ? (int)$_GET['paraje_id'] : 0;
$filtroTxt = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "
    SELECT f.*, p.nombre_paraje, cr.nombre_comunidad AS nombre_comunidad_ref,
           (SELECT COUNT(*) FROM plantaciones pl WHERE pl.id_finca = f.id_finca) AS total_plantaciones
    FROM fincas f 
    LEFT JOIN parajes p ON f.id_paraje = p.id_paraje 
    LEFT JOIN comunidades_regantes cr ON f.id_comunidad = cr.id_comunidad
    WHERE f.activo = 1
";
$params = array();

if ($tenantId && !$isSuperAdmin) {
    $sql .= " AND f.id_propietario = :tenant";
    $params[':tenant'] = (int)$tenantId;
}

if ($filtroParaje > 0) {
    $sql .= " AND f.id_paraje = :paraje";
    $params[':paraje'] = $filtroParaje;
}

if (!empty($filtroTxt)) {
    $sql .= " AND (f.nombre_finca LIKE :txt OR f.referencia_catastral LIKE :txt OR f.poligono LIKE :txt OR f.parcela LIKE :txt)";
    $params[':txt'] = '%' . $filtroTxt . '%';
}

$sql .= " ORDER BY f.nombre_finca ASC";
$stmtF = $db->prepare($sql);
$stmtF->execute($params);
$fincas = $stmtF->fetchAll();
?>

<div class="container py-4">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-layer-group fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Maestro de Fincas y Parcelas</h2>
                <p class="text-muted mb-0 small">Control registral, catastral y asignación de derechos de riego por tanda</p>
            </div>
        </div>
        <a href="<?= APP_URL ?>/fincas/nueva" class="btn btn-ag shadow-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Nueva Finca
        </a>
    </div>

    <!-- Alertas -->
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm">
        <form action="<?= APP_URL ?>/fincas" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar finca, ref. catastral, polígono o parcela..." value="<?= htmlspecialchars($filtroTxt) ?>">
            </div>
            <div class="col-md-4">
                <select name="paraje_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Todos los parajes --</option>
                    <?php foreach ($parajes as $par): ?>
                        <option value="<?= $par['id_paraje'] ?>" <?= ($filtroParaje === (int)$par['id_paraje']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($par['nombre_paraje'] . ' (' . $par['termino_municipal'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Filtrar</button>
                <?php if ($filtroTxt || $filtroParaje): ?>
                    <a href="<?= APP_URL ?>/fincas" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla Maestra de Fincas (CRUD) -->
    <div class="card card-ag shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-draw-polygon me-2"></i>Fincas Registradas (<?= count($fincas) ?>)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th class="ps-4">Nombre de Finca</th>
                        <th>Paraje</th>
                        <th>Superficie</th>
                        <th>Datos Catastrales</th>
                        <th>Comunidad / Parada / Tablón</th>
                        <th>Tanda Riego</th>
                        <th>Plantaciones</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fincas)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-vector-square fa-2x mb-3 text-secondary"></i>
                                <p class="mb-0">No se encontraron fincas registradas.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fincas as $f): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-draw-polygon text-primary"></i>
                                        <?= htmlspecialchars($f['nombre_finca']) ?>
                                    </div>
                                    <div class="small text-muted font-monospace">ID #<?= $f['id_finca'] ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="fa-solid fa-location-pin me-1 text-primary"></i><?= htmlspecialchars($f['nombre_paraje'] ?: 'Sin asignar') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold font-monospace px-3 py-2 rounded-pill">
                                        <?= number_format($f['superficie_ha'], 2, ',', '.') ?> ha
                                    </span>
                                    <div class="mt-1">
                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning-subtle font-monospace small" title="Equivalencia: 1 ha = <?= number_format($fanegasPorHa, 2, ',', '.') ?> fanegas">
                                            <i class="fa-solid fa-wheat-awn text-warning me-1"></i><?= number_format($f['superficie_ha'] * $fanegasPorHa, 2, ',', '.') ?> fanegas
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($f['referencia_catastral']): ?>
                                        <a href="https://www1.sedecatastro.gob.es/CYGConsultaInformacionCatastral/CYGCampanyas.aspx?refcat=<?= urlencode($f['referencia_catastral']) ?>" target="_blank" class="text-decoration-none small text-primary fw-semibold" title="Sede Electrónica del Catastro">
                                            <?= htmlspecialchars($f['referencia_catastral']) ?> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.65rem;"></i>
                                        </a>
                                        <div class="small text-muted font-monospace">Pol <?= htmlspecialchars($f['poligono'] ?: '-') ?> / Parc <?= htmlspecialchars($f['parcela'] ?: '-') ?></div>
                                    <?php else: ?>
                                        <span class="small text-muted font-monospace">Pol <?= htmlspecialchars($f['poligono'] ?: '-') ?> / Parc <?= htmlspecialchars($f['parcela'] ?: '-') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-primary">
                                        <i class="fa-solid fa-water me-1"></i><?= htmlspecialchars(!empty($f['nombre_comunidad_ref']) ? $f['nombre_comunidad_ref'] : (!empty($f['zona_riego']) ? $f['zona_riego'] : 'Sin asignar')) ?>
                                    </div>
                                    <div class="small text-muted">
                                        Parada: <?= htmlspecialchars($f['parada'] ?: '-') ?> | Tablón: <?= htmlspecialchars($f['tablon'] ?: '-') ?>
                                        <?php if ($f['num_socio_comunidad']): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary font-monospace ms-1"><?= htmlspecialchars($f['num_socio_comunidad']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($f['coste_anual_ibi']) && $f['coste_anual_ibi'] > 0): ?>
                                        <div class="small text-dark mt-1">
                                            <i class="fa-solid fa-receipt text-warning me-1"></i>IBI: <strong><?= number_format($f['coste_anual_ibi'], 2, ',', '.') ?> €/año</strong>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle font-monospace">
                                        <?= $f['horas_propiedad_tanda'] ?>h prop.
                                    </span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle font-monospace">
                                        <?= $f['horas_disponibles_tanda'] ?>h disp.
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= APP_URL ?>/plantaciones?finca_id=<?= $f['id_finca'] ?>" class="btn btn-sm btn-outline-info py-1 px-2" title="Ver plantaciones">
                                        <i class="fa-solid fa-seedling me-1"></i><?= $f['total_plantaciones'] ?>
                                    </a>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Modificar -->
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarFinca_<?= $f['id_finca'] ?>" title="Modificar finca">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Borrar -->
                                        <form action="<?= APP_URL ?>/fincas" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar la finca \'<?= htmlspecialchars(addslashes($f['nombre_finca'])) ?>\'?');">
                                            <input type="hidden" name="action" value="borrar_finca">
                                            <input type="hidden" name="id_finca" value="<?= $f['id_finca'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Eliminar finca" <?= ($f['total_plantaciones'] > 0) ? 'disabled title="Contiene plantaciones"' : '' ?>>
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Modificar Finca -->
                                    <div class="modal fade text-start" id="modalEditarFinca_<?= $f['id_finca'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Finca: <?= htmlspecialchars($f['nombre_finca']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="<?= APP_URL ?>/fincas" method="POST">
                                                    <input type="hidden" name="action" value="editar_finca">
                                                    <input type="hidden" name="id_finca" value="<?= $f['id_finca'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Nombre de la Finca *</label>
                                                                <input type="text" name="nombre_finca" class="form-control" value="<?= htmlspecialchars($f['nombre_finca']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Paraje</label>
                                                                <select name="id_paraje" class="form-select">
                                                                    <option value="">-- Sin Paraje Asignado --</option>
                                                                    <?php foreach ($parajes as $par): ?>
                                                                        <option value="<?= $par['id_paraje'] ?>" <?= ((int)$f['id_paraje'] === (int)$par['id_paraje']) ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($par['nombre_paraje'] . ' (' . $par['termino_municipal'] . ')') ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Superficie (ha) *</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.0001" min="0" name="superficie_ha" id="modal_ha_<?= $f['id_finca'] ?>" class="form-control font-monospace fw-bold text-primary" value="<?= $f['superficie_ha'] ?>" required oninput="syncModalHa(<?= $f['id_finca'] ?>)">
                                                                    <span class="input-group-text fw-bold">ha</span>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Superficie (fanegas)</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.01" min="0" id="modal_fan_<?= $f['id_finca'] ?>" class="form-control font-monospace fw-bold text-dark" value="<?= number_format($f['superficie_ha'] * $fanegasPorHa, 2, '.', '') ?>" oninput="syncModalFan(<?= $f['id_finca'] ?>)">
                                                                    <span class="input-group-text fw-bold">fan</span>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Polígono</label>
                                                                <input type="text" name="poligono" class="form-control font-monospace" value="<?= htmlspecialchars($f['poligono'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Parcela</label>
                                                                <input type="text" name="parcela" class="form-control font-monospace" value="<?= htmlspecialchars($f['parcela'] ?: '') ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Referencia Catastral (20 caracteres)</label>
                                                                <input type="text" name="referencia_catastral" class="form-control font-monospace" value="<?= htmlspecialchars($f['referencia_catastral'] ?: '') ?>" maxlength="20">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Coste Anual IBI (€/año)</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.01" name="coste_anual_ibi" class="form-control font-monospace" value="<?= isset($f['coste_anual_ibi']) ? $f['coste_anual_ibi'] : '0.00' ?>">
                                                                    <span class="input-group-text">€/año</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <h6 class="fw-bold text-primary border-top pt-3 mt-3">Derechos de Riego y Comunidad</h6>
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Comunidad de Regantes *</label>
                                                                <select name="id_comunidad" class="form-select" required>
                                                                    <option value="">-- Sin Comunidad Asignada --</option>
                                                                    <?php foreach ($comunidades as $cr): ?>
                                                                        <option value="<?= $cr['id_comunidad'] ?>" <?= ((int)$f['id_comunidad'] === (int)$cr['id_comunidad'] || (empty($f['id_comunidad']) && !empty($cr['es_predeterminada']))) ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($cr['nombre_comunidad']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Nº Socio Comunidad</label>
                                                                <input type="text" name="num_socio_comunidad" class="form-control font-monospace" value="<?= htmlspecialchars($f['num_socio_comunidad'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Parada</label>
                                                                <input type="text" name="parada" class="form-control" value="<?= htmlspecialchars($f['parada'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Tablón</label>
                                                                <input type="text" name="tablon" class="form-control" value="<?= htmlspecialchars($f['tablon'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Horas Propiedad por Tanda</label>
                                                                <input type="number" step="0.01" name="horas_propiedad_tanda" class="form-control font-monospace" value="<?= $f['horas_propiedad_tanda'] ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Horas Disponibles por Tanda</label>
                                                                <input type="number" step="0.01" name="horas_disponibles_tanda" class="form-control font-monospace" value="<?= $f['horas_disponibles_tanda'] ?>">
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

<script>
var FANEGAS_POR_HA = <?= (float)$fanegasPorHa ?>;

function syncModalHa(id) {
    var haEl = document.getElementById('modal_ha_' + id);
    var fanEl = document.getElementById('modal_fan_' + id);
    if (!haEl || !fanEl) return;
    var ha = parseFloat(haEl.value);
    if (!isNaN(ha) && ha >= 0 && FANEGAS_POR_HA > 0) {
        fanEl.value = (ha * FANEGAS_POR_HA).toFixed(2);
    } else {
        fanEl.value = '';
    }
}

function syncModalFan(id) {
    var haEl = document.getElementById('modal_ha_' + id);
    var fanEl = document.getElementById('modal_fan_' + id);
    if (!haEl || !fanEl) return;
    var fan = parseFloat(fanEl.value);
    if (!isNaN(fan) && fan >= 0 && FANEGAS_POR_HA > 0) {
        haEl.value = (fan / FANEGAS_POR_HA).toFixed(4);
    } else {
        haEl.value = '';
    }
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
