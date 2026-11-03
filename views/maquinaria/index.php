<?php
$pageTitle = "Inventario de Maquinaria y Herramientas - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR FORMULARIOS (CREAR, EDITAR, BORRADO LÓGICO)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. Guardar nueva herramienta / maquinaria
    if ($action === 'guardar_herramienta') {
        try {
            $nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
            $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'mecanica';
            $categoria = trim(isset($_POST['categoria']) ? $_POST['categoria'] : '');
            $marca = trim(isset($_POST['marca']) ? $_POST['marca'] : '');
            $modelo = trim(isset($_POST['modelo']) ? $_POST['modelo'] : '');
            $serie = trim(isset($_POST['matricula_o_serie']) ? $_POST['matricula_o_serie'] : '');
            $ano = !empty($_POST['ano_adquisicion']) ? (int)$_POST['ano_adquisicion'] : null;
            $estado = isset($_POST['estado']) ? $_POST['estado'] : 'operativa';
            $rev = !empty($_POST['fecha_revision']) ? $_POST['fecha_revision'] : null;
            $horas = !empty($_POST['horas_o_km_uso']) ? (float)$_POST['horas_o_km_uso'] : 0.00;
            $obs = trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : '');
            $propietarioId = $tenantId ? (int)$tenantId : 1;

            if (empty($nombre)) {
                $error = "El nombre o denominación del equipo es obligatorio.";
            } else {
                $stmt = $db->prepare("
                    INSERT INTO maquinaria_herramientas (
                        id_propietario, nombre, tipo, categoria, marca, modelo,
                        matricula_o_serie, ano_adquisicion, estado, fecha_revision,
                        horas_o_km_uso, observaciones, activo
                    ) VALUES (
                        :prop, :nom, :tipo, :cat, :marca, :mod,
                        :serie, :ano, :est, :rev,
                        :horas, :obs, 1
                    )
                ");
                $stmt->execute(array(
                    ':prop'  => $propietarioId,
                    ':nom'   => $nombre,
                    ':tipo'  => $tipo,
                    ':cat'   => $categoria ?: null,
                    ':marca' => $marca ?: null,
                    ':mod'   => $modelo ?: null,
                    ':serie' => $serie ?: null,
                    ':ano'   => $ano,
                    ':est'   => $estado,
                    ':rev'   => $rev,
                    ':horas' => $horas,
                    ':obs'   => $obs ?: null
                ));
                $mensaje = "Equipo o herramienta <strong>" . htmlspecialchars($nombre) . "</strong> añadido al inventario con éxito.";
            }
        } catch (Exception $e) {
            $error = "Error al dar de alta el equipo: " . $e->getMessage();
        }
    }

    // 2. Modificar herramienta / maquinaria existente
    elseif ($action === 'editar_herramienta') {
        try {
            $idH = (int)$_POST['id_herramienta'];
            $nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
            $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'mecanica';
            $categoria = trim(isset($_POST['categoria']) ? $_POST['categoria'] : '');
            $marca = trim(isset($_POST['marca']) ? $_POST['marca'] : '');
            $modelo = trim(isset($_POST['modelo']) ? $_POST['modelo'] : '');
            $serie = trim(isset($_POST['matricula_o_serie']) ? $_POST['matricula_o_serie'] : '');
            $ano = !empty($_POST['ano_adquisicion']) ? (int)$_POST['ano_adquisicion'] : null;
            $estado = isset($_POST['estado']) ? $_POST['estado'] : 'operativa';
            $rev = !empty($_POST['fecha_revision']) ? $_POST['fecha_revision'] : null;
            $horas = !empty($_POST['horas_o_km_uso']) ? (float)$_POST['horas_o_km_uso'] : 0.00;
            $obs = trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : '');

            if (empty($nombre)) {
                $error = "El nombre del equipo es obligatorio.";
            } else {
                $stmtUpd = $db->prepare("
                    UPDATE maquinaria_herramientas
                    SET nombre = :nom, tipo = :tipo, categoria = :cat, marca = :marca,
                        modelo = :mod, matricula_o_serie = :serie, ano_adquisicion = :ano,
                        estado = :est, fecha_revision = :rev, horas_o_km_uso = :horas,
                        observaciones = :obs
                    WHERE id_herramienta = :id
                ");
                $stmtUpd->execute(array(
                    ':nom'   => $nombre,
                    ':tipo'  => $tipo,
                    ':cat'   => $categoria ?: null,
                    ':marca' => $marca ?: null,
                    ':mod'   => $modelo ?: null,
                    ':serie' => $serie ?: null,
                    ':ano'   => $ano,
                    ':est'   => $estado,
                    ':rev'   => $rev,
                    ':horas' => $horas,
                    ':obs'   => $obs ?: null,
                    ':id'    => $idH
                ));
                $mensaje = "Datos de <strong>" . htmlspecialchars($nombre) . "</strong> actualizados correctamente.";
            }
        } catch (Exception $e) {
            $error = "Error al actualizar el equipo: " . $e->getMessage();
        }
    }

    // 3. Borrado lógico seguro (activo = 0)
    elseif ($action === 'borrar_herramienta') {
        try {
            $idH = (int)$_POST['id_herramienta'];
            $stmtDel = $db->prepare("UPDATE maquinaria_herramientas SET activo = 0 WHERE id_herramienta = :id");
            $stmtDel->execute(array(':id' => $idH));
            $mensaje = "Equipo dado de baja y archivado del parque activo (borrado lógico seguro).";
        } catch (Exception $e) {
            $error = "Error al archivar el equipo: " . $e->getMessage();
        }
    }
}

// FILTROS
$filtroTipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$filtroBuscar = isset($_GET['q']) ? trim($_GET['q']) : '';

// CONSULTA DE INVENTARIO ACTIVO
$sql = "SELECT * FROM maquinaria_herramientas WHERE activo = 1";
$params = array();

if ($tenantId) {
    $sql .= " AND (id_propietario = :tenant OR id_propietario IS NULL)";
    $params[':tenant'] = (int)$tenantId;
}
if (!empty($filtroTipo)) {
    $sql .= " AND tipo = :tipo";
    $params[':tipo'] = $filtroTipo;
}
if (!empty($filtroEstado)) {
    $sql .= " AND estado = :est";
    $params[':est'] = $filtroEstado;
}
if (!empty($filtroBuscar)) {
    $sql .= " AND (nombre LIKE :q OR marca LIKE :q OR modelo LIKE :q OR matricula_o_serie LIKE :q OR categoria LIKE :q)";
    $params[':q'] = '%' . $filtroBuscar . '%';
}

$sql .= " ORDER BY CASE tipo WHEN 'tractor' THEN 1 WHEN 'mecanica' THEN 2 WHEN 'apero' THEN 3 WHEN 'manual' THEN 4 ELSE 5 END, nombre ASC";
$stmtH = $db->prepare($sql);
$stmtH->execute($params);
$herramientas = $stmtH->fetchAll();

// MÉTRICAS GLOBALES DEL PARQUE MÓVIL
$totalEquipos = count($herramientas);
$totalMecanica = 0;
$totalManual = 0;
$totalAperos = 0;
$totalRevision = 0;

foreach ($herramientas as $h) {
    if ($h['tipo'] === 'tractor' || $h['tipo'] === 'mecanica') {
        $totalMecanica++;
    } elseif ($h['tipo'] === 'manual') {
        $totalManual++;
    } elseif ($h['tipo'] === 'apero') {
        $totalAperos++;
    }
    if ($h['estado'] === 'en_mantenimiento' || $h['estado'] === 'averiada') {
        $totalRevision++;
    }
}
?>

<div class="container py-4">

    <!-- Cabecera Corporativa con Toolbar Oficial de Exportación -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-wrench fs-2 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Inventario de Maquinaria y Herramientas</h2>
                <p class="text-muted mb-0 small">Control del parque de maquinaria mecánica, tractores, aperos y herramientas manuales agrícolas</p>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php
            $reportTitle = 'Inventario Oficial de Maquinaria, Aperos y Herramientas Agrícolas';
            $reportTargetId = 'tablaInventarioImprimible';
            $reportFilename = 'inventario_maquinaria_herramientas_' . date('Y-m-d') . '.pdf';
            require VIEWS_PATH . '/layouts/toolbar_informes.php';
            ?>
            <button type="button" class="btn btn-ag shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaHerramienta">
                <i class="fa-solid fa-plus-circle me-1"></i> Añadir Equipo / Herramienta
            </button>
        </div>
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

    <!-- Tarjetas de Métricas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card card-custom p-3 border-start border-4 border-primary h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Equipos</div>
                        <h3 class="fw-bold mb-0 text-primary"><?= $totalEquipos ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                        <i class="fa-solid fa-boxes-stacked fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-custom p-3 border-start border-4 border-info h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Mecánica / Tractores</div>
                        <h3 class="fw-bold mb-0 text-info"><?= $totalMecanica ?></h3>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                        <i class="fa-solid fa-tractor fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-custom p-3 border-start border-4 border-success h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Herramientas Manuales</div>
                        <h3 class="fw-bold mb-0 text-success"><?= $totalManual ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                        <i class="fa-solid fa-hammer fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-custom p-3 border-start border-4 border-warning h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Aperos / Revisión</div>
                        <h3 class="fw-bold mb-0 text-warning"><?= $totalAperos ?> <span class="fs-6 fw-normal text-muted">(<?= $totalRevision ?> en rev.)</span></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                        <i class="fa-solid fa-toolbox fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="card card-custom p-3 mb-4 bg-light border-0 shadow-sm">
        <form action="<?= APP_URL ?>/maquinaria" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los Tipos --</option>
                    <option value="tractor" <?= $filtroTipo === 'tractor' ? 'selected' : '' ?>>🚜 Tractores</option>
                    <option value="mecanica" <?= $filtroTipo === 'mecanica' ? 'selected' : '' ?>>⚙️ Maquinaria Mecánica</option>
                    <option value="apero" <?= $filtroTipo === 'apero' ? 'selected' : '' ?>>🌾 Aperos e Implementos</option>
                    <option value="manual" <?= $filtroTipo === 'manual' ? 'selected' : '' ?>>✂️ Herramientas Manuales</option>
                    <option value="vehiculo" <?= $filtroTipo === 'vehiculo' ? 'selected' : '' ?>>🚚 Vehículos y Remolques</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los Estados --</option>
                    <option value="operativa" <?= $filtroEstado === 'operativa' ? 'selected' : '' ?>>🟢 Operativa y En Uso</option>
                    <option value="en_mantenimiento" <?= $filtroEstado === 'en_mantenimiento' ? 'selected' : '' ?>>🟡 En Mantenimiento</option>
                    <option value="averiada" <?= $filtroEstado === 'averiada' ? 'selected' : '' ?>>🔴 Averiada / Taller</option>
                    <option value="baja" <?= $filtroEstado === 'baja' ? 'selected' : '' ?>>⚪ Descatalogada</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por denominación, marca, modelo o matrícula..." value="<?= htmlspecialchars($filtroBuscar) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Filtrar</button>
                <?php if ($filtroTipo || $filtroEstado || $filtroBuscar): ?>
                    <a href="<?= APP_URL ?>/maquinaria" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla Maestra de Inventario (CRUD e Informe Imprimible A4) -->
    <div class="card card-custom shadow-sm border-0" id="tablaInventarioImprimible">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-list-check me-2"></i>Listado de Equipos y Herramientas Registradas (<?= count($herramientas) ?>)
            </h6>
            <div class="small text-muted no-print">
                <i class="fa-solid fa-circle-info me-1"></i>Disponibles para selección en Tratamientos, Laboreo, Podas y Riego
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 45px;">Tipo</th>
                        <th>Equipo / Denominación</th>
                        <th>Categoría Agrícola</th>
                        <th>Marca y Modelo</th>
                        <th>Matrícula / Serie / ITEAF</th>
                        <th>Estado</th>
                        <th>Próx. Revisión</th>
                        <th class="text-end pe-3 no-print">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($herramientas)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-screwdriver-wrench fs-1 d-block mb-3 opacity-25"></i>
                                No se encontraron herramientas o maquinaria con los filtros seleccionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($herramientas as $h): ?>
                            <?php
                            $tipoIcon = 'fa-wrench';
                            $tipoColor = 'text-secondary';
                            if ($h['tipo'] === 'tractor') {
                                $tipoIcon = 'fa-tractor';
                                $tipoColor = 'text-primary';
                            } elseif ($h['tipo'] === 'mecanica') {
                                $tipoIcon = 'fa-gear';
                                $tipoColor = 'text-info';
                            } elseif ($h['tipo'] === 'apero') {
                                $tipoIcon = 'fa-cubes';
                                $tipoColor = 'text-warning';
                            } elseif ($h['tipo'] === 'manual') {
                                $tipoIcon = 'fa-hand';
                                $tipoColor = 'text-success';
                            } elseif ($h['tipo'] === 'vehiculo') {
                                $tipoIcon = 'fa-truck';
                                $tipoColor = 'text-dark';
                            }

                            $estadoBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Operativa</span>';
                            if ($h['estado'] === 'en_mantenimiento') {
                                $estadoBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Mantenimiento</span>';
                            } elseif ($h['estado'] === 'averiada') {
                                $estadoBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Averiada</span>';
                            } elseif ($h['estado'] === 'baja') {
                                $estadoBadge = '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Baja</span>';
                            }
                            ?>
                            <tr>
                                <td class="ps-3 text-center">
                                    <i class="fa-solid <?= $tipoIcon ?> <?= $tipoColor ?> fs-5" title="<?= htmlspecialchars(ucfirst($h['tipo'])) ?>"></i>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($h['nombre']) ?></div>
                                    <?php if (!empty($h['observaciones'])): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars($h['observaciones']) ?>">
                                            <?= htmlspecialchars($h['observaciones']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-primary border">
                                        <?= htmlspecialchars($h['categoria'] ?: 'General') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= htmlspecialchars($h['marca'] ?: '-') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($h['modelo'] ?: '') ?> <?= $h['ano_adquisicion'] ? '(' . $h['ano_adquisicion'] . ')' : '' ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($h['matricula_o_serie'])): ?>
                                        <code class="small fw-bold text-dark bg-light px-2 py-1 rounded border"><?= htmlspecialchars($h['matricula_o_serie']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $estadoBadge ?></td>
                                <td>
                                    <?php if (!empty($h['fecha_revision'])): ?>
                                        <div class="small font-monospace <?= strtotime($h['fecha_revision']) < time() ? 'text-danger fw-bold' : 'text-muted' ?>">
                                            <i class="fa-regular fa-calendar-check me-1"></i><?= date('d/m/Y', strtotime($h['fecha_revision'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3 no-print">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Botón Editar -->
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarHerramienta_<?= $h['id_herramienta'] ?>" title="Modificar Ficha">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Botón Borrado Lógico -->
                                        <form action="<?= APP_URL ?>/maquinaria" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas dar de baja este equipo del inventario activo?');">
                                            <input type="hidden" name="action" value="borrar_herramienta">
                                            <input type="hidden" name="id_herramienta" value="<?= $h['id_herramienta'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Dar de baja / Archivar">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Modificar Herramienta -->
                                    <div class="modal fade text-start" id="modalEditarHerramienta_<?= $h['id_herramienta'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Equipo: <?= htmlspecialchars($h['nombre']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="<?= APP_URL ?>/maquinaria" method="POST">
                                                    <input type="hidden" name="action" value="editar_herramienta">
                                                    <input type="hidden" name="id_herramienta" value="<?= $h['id_herramienta'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-8">
                                                                <label class="form-label fw-semibold small">Denominación del Equipo / Herramienta *</label>
                                                                <input type="text" name="nombre" class="form-control fw-semibold" value="<?= htmlspecialchars($h['nombre']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Tipo de Equipo *</label>
                                                                <select name="tipo" class="form-select" required>
                                                                    <option value="tractor" <?= $h['tipo'] === 'tractor' ? 'selected' : '' ?>>Tractor Agrícola</option>
                                                                    <option value="mecanica" <?= $h['tipo'] === 'mecanica' ? 'selected' : '' ?>>Maquinaria Mecánica</option>
                                                                    <option value="apero" <?= $h['tipo'] === 'apero' ? 'selected' : '' ?>>Apero / Implemento</option>
                                                                    <option value="manual" <?= $h['tipo'] === 'manual' ? 'selected' : '' ?>>Herramienta Manual</option>
                                                                    <option value="vehiculo" <?= $h['tipo'] === 'vehiculo' ? 'selected' : '' ?>>Vehículo / Remolque</option>
                                                                    <option value="otro" <?= $h['tipo'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Categoría de Uso</label>
                                                                <input type="text" name="categoria" class="form-control" value="<?= htmlspecialchars((string)$h['categoria']) ?>" placeholder="Ej: Tratamientos, Laboreo, Poda...">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Marca</label>
                                                                <input type="text" name="marca" class="form-control" value="<?= htmlspecialchars((string)$h['marca']) ?>" placeholder="Ej: John Deere, Stihl, Bellota">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Modelo</label>
                                                                <input type="text" name="modelo" class="form-control" value="<?= htmlspecialchars((string)$h['modelo']) ?>" placeholder="Ej: 5075E, FS 460...">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Matrícula / Serie / ITEAF</label>
                                                                <input type="text" name="matricula_o_serie" class="form-control font-monospace" value="<?= htmlspecialchars((string)$h['matricula_o_serie']) ?>" placeholder="Ej: E-4819-BHG">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Año de Adquisición</label>
                                                                <input type="number" name="ano_adquisicion" class="form-control font-monospace" value="<?= $h['ano_adquisicion'] ?: '' ?>" min="1950" max="<?= date('Y') + 1 ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Estado Operativo *</label>
                                                                <select name="estado" class="form-select" required>
                                                                    <option value="operativa" <?= $h['estado'] === 'operativa' ? 'selected' : '' ?>>🟢 Operativa</option>
                                                                    <option value="en_mantenimiento" <?= $h['estado'] === 'en_mantenimiento' ? 'selected' : '' ?>>🟡 En Mantenimiento</option>
                                                                    <option value="averiada" <?= $h['estado'] === 'averiada' ? 'selected' : '' ?>>🔴 Averiada</option>
                                                                    <option value="baja" <?= $h['estado'] === 'baja' ? 'selected' : '' ?>>⚪ Baja</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Próxima Revisión / ITV / ITEAF</label>
                                                                <input type="date" name="fecha_revision" class="form-control" value="<?= $h['fecha_revision'] ?: '' ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Horas o Km de Uso Acumulados</label>
                                                                <input type="number" step="0.1" name="horas_o_km_uso" class="form-control font-monospace" value="<?= $h['horas_o_km_uso'] ?: '0.00' ?>">
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Observaciones y Pautas de Mantenimiento</label>
                                                            <textarea name="observaciones" rows="2" class="form-control"><?= htmlspecialchars((string)$h['observaciones']) ?></textarea>
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

<!-- Modal Dar de Alta Nueva Herramienta / Maquinaria -->
<div class="modal fade" id="modalNuevaHerramienta" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-plus-circle me-2"></i> Añadir Nuevo Equipo / Herramienta al Inventario
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= APP_URL ?>/maquinaria" method="POST">
                <input type="hidden" name="action" value="guardar_herramienta">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Denominación del Equipo / Herramienta <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control fw-semibold" placeholder="Ej: Tractor John Deere 5075E, Tijeras de podar Bellota Pro..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Tipo de Equipo <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-select" required>
                                <option value="tractor">🚜 Tractor Agrícola</option>
                                <option value="mecanica" selected>⚙️ Maquinaria Mecánica</option>
                                <option value="apero">🌾 Apero / Implemento</option>
                                <option value="manual">✂️ Herramienta Manual</option>
                                <option value="vehiculo">🚚 Vehículo / Remolque</option>
                                <option value="otro">📦 Otro</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Categoría Agrícola</label>
                            <input type="text" name="categoria" class="form-control" placeholder="Ej: Tratamientos, Laboreo, Poda...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Marca</label>
                            <input type="text" name="marca" class="form-control" placeholder="Ej: John Deere, Fitosa, Stihl, Bellota">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Modelo</label>
                            <input type="text" name="modelo" class="form-control" placeholder="Ej: 5075E, Agro-2000, FS 460">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Matrícula / Nº Serie / ITEAF</label>
                            <input type="text" name="matricula_o_serie" class="form-control font-monospace" placeholder="Ej: E-4819-BHG, ITEAF-2025">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Año de Adquisición</label>
                            <input type="number" name="ano_adquisicion" class="form-control font-monospace" value="<?= date('Y') ?>" min="1950" max="<?= date('Y') + 1 ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Estado Inicial</label>
                            <select name="estado" class="form-select" required>
                                <option value="operativa" selected>🟢 Operativa y En Uso</option>
                                <option value="en_mantenimiento">🟡 En Mantenimiento</option>
                                <option value="averiada">🔴 Averiada</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Próxima Revisión / ITV / ITEAF</label>
                            <input type="date" name="fecha_revision" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Horas o Km de Uso Inicial</label>
                            <input type="number" step="0.1" name="horas_o_km_uso" class="form-control font-monospace" value="0.0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Observaciones Técnicas y Mantenimiento</label>
                        <textarea name="observaciones" rows="2" class="form-control" placeholder="Detalles de filtros, aceites, boquillas, afilado o advertencias de seguridad..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-ag">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Registrar en Inventario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
