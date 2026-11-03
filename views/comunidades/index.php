<?php
$pageTitle = "Maestro de Comunidades de Regantes - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// Procesar Acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // CREAR COMUNIDAD
    if ($action === 'crear') {
        $nombre = trim(isset($_POST['nombre_comunidad']) ? $_POST['nombre_comunidad'] : '');
        $cif = trim(isset($_POST['cif']) ? $_POST['cif'] : '');
        $email = trim(isset($_POST['email_solicitudes']) ? $_POST['email_solicitudes'] : 'alharabemoratalla@gmail.com');
        $tel = trim(isset($_POST['telefono']) ? $_POST['telefono'] : '');
        $dir = trim(isset($_POST['direccion']) ? $_POST['direccion'] : '');
        $mun = trim(isset($_POST['municipio']) ? $_POST['municipio'] : 'Moratalla');
        $prov = trim(isset($_POST['provincia']) ? $_POST['provincia'] : 'Murcia');
        $banco = trim(isset($_POST['banco_habitual']) ? $_POST['banco_habitual'] : 'CAJAMAR');
        $derrama = (float)(isset($_POST['derrama_anual']) ? $_POST['derrama_anual'] : 150.00);

        // Precios
        $pTanda = (float)(isset($_POST['precio_hora_tanda']) ? $_POST['precio_hora_tanda'] : 0.00);
        $maxTanda = (float)(isset($_POST['consumo_max_tanda_horas']) ? $_POST['consumo_max_tanda_horas'] : 0.00);
        $pCom = (float)(isset($_POST['precio_hora_comunitaria']) ? $_POST['precio_hora_comunitaria'] : 12.00);
        $maxCom = (float)(isset($_POST['consumo_max_comunitaria_horas']) ? $_POST['consumo_max_comunitaria_horas'] : 0.00);
        $pHort = (float)(isset($_POST['precio_hora_hortalizas']) ? $_POST['precio_hora_hortalizas'] : 15.00);
        $maxHort = (float)(isset($_POST['consumo_max_hortalizas_horas']) ? $_POST['consumo_max_hortalizas_horas'] : 2.00);

        $esPredet = !empty($_POST['es_predeterminada']) ? 1 : 0;
        $horario = trim(isset($_POST['horario_atencion']) ? $_POST['horario_atencion'] : 'Lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00');
        $notas = trim(isset($_POST['notas']) ? $_POST['notas'] : '');

        if (empty($nombre) || empty($email)) {
            $error = "El nombre de la comunidad y el email oficial para solicitudes son obligatorios.";
        } else {
            try {
                // Comprobar cuántas activas hay
                $totalPrevias = (int)$db->query("SELECT COUNT(*) FROM comunidades_regantes WHERE activo = 1")->fetchColumn();
                if ($totalPrevias === 0) {
                    $esPredet = 1;
                }

                if ($esPredet) {
                    $db->exec("UPDATE comunidades_regantes SET es_predeterminada = 0");
                }

                $stmt = $db->prepare("
                    INSERT INTO comunidades_regantes (
                        nombre_comunidad, cif, email_solicitudes, telefono, direccion, municipio, provincia,
                        banco_habitual, derrama_anual, precio_hora_tanda, consumo_max_tanda_horas,
                        precio_hora_comunitaria, consumo_max_comunitaria_horas,
                        precio_hora_hortalizas, consumo_max_hortalizas_horas,
                        es_predeterminada, horario_atencion, notas, activo
                    ) VALUES (
                        :nom, :cif, :email, :tel, :dir, :mun, :prov,
                        :banco, :derr, :ptanda, :maxtanda,
                        :pcom, :maxcom,
                        :phort, :maxhort,
                        :predet, :horario, :notas, 1
                    )
                ");
                $stmt->execute([
                    ':nom'      => $nombre,
                    ':cif'      => $cif ?: null,
                    ':email'    => $email,
                    ':tel'      => $tel ?: null,
                    ':dir'      => $dir ?: null,
                    ':mun'      => $mun ?: 'Moratalla',
                    ':prov'     => $prov ?: 'Murcia',
                    ':banco'    => $banco ?: 'CAJAMAR',
                    ':derr'     => $derrama,
                    ':ptanda'   => $pTanda,
                    ':maxtanda' => $maxTanda,
                    ':pcom'     => $pCom,
                    ':maxcom'   => $maxCom,
                    ':phort'    => $pHort,
                    ':maxhort'  => $maxHort,
                    ':predet'   => $esPredet,
                    ':horario'  => $horario,
                    ':notas'    => $notas ?: null
                ]);
                $mensaje = "Comunidad de Regantes <strong>" . htmlspecialchars($nombre) . "</strong> creada correctamente.";
            } catch (Exception $e) {
                $error = "Error al crear comunidad: " . $e->getMessage();
            }
        }
    }

    // EDITAR COMUNIDAD
    elseif ($action === 'editar') {
        $idCom = (int)(isset($_POST['id_comunidad']) ? $_POST['id_comunidad'] : 0);
        $nombre = trim(isset($_POST['nombre_comunidad']) ? $_POST['nombre_comunidad'] : '');
        $cif = trim(isset($_POST['cif']) ? $_POST['cif'] : '');
        $email = trim(isset($_POST['email_solicitudes']) ? $_POST['email_solicitudes'] : 'alharabemoratalla@gmail.com');
        $tel = trim(isset($_POST['telefono']) ? $_POST['telefono'] : '');
        $dir = trim(isset($_POST['direccion']) ? $_POST['direccion'] : '');
        $mun = trim(isset($_POST['municipio']) ? $_POST['municipio'] : 'Moratalla');
        $prov = trim(isset($_POST['provincia']) ? $_POST['provincia'] : 'Murcia');
        $banco = trim(isset($_POST['banco_habitual']) ? $_POST['banco_habitual'] : 'CAJAMAR');
        $derrama = (float)(isset($_POST['derrama_anual']) ? $_POST['derrama_anual'] : 150.00);

        // Precios y cupos máximos
        $pTanda = (float)(isset($_POST['precio_hora_tanda']) ? $_POST['precio_hora_tanda'] : 0.00);
        $maxTanda = (float)(isset($_POST['consumo_max_tanda_horas']) ? $_POST['consumo_max_tanda_horas'] : 0.00);
        $pCom = (float)(isset($_POST['precio_hora_comunitaria']) ? $_POST['precio_hora_comunitaria'] : 12.00);
        $maxCom = (float)(isset($_POST['consumo_max_comunitaria_horas']) ? $_POST['consumo_max_comunitaria_horas'] : 0.00);
        $pHort = (float)(isset($_POST['precio_hora_hortalizas']) ? $_POST['precio_hora_hortalizas'] : 15.00);
        $maxHort = (float)(isset($_POST['consumo_max_hortalizas_horas']) ? $_POST['consumo_max_hortalizas_horas'] : 2.00);

        $esPredet = !empty($_POST['es_predeterminada']) ? 1 : 0;
        $horario = trim(isset($_POST['horario_atencion']) ? $_POST['horario_atencion'] : 'Lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00');
        $notas = trim(isset($_POST['notas']) ? $_POST['notas'] : '');

        if (empty($nombre) || empty($email)) {
            $error = "El nombre de la comunidad y el email oficial para solicitudes son obligatorios.";
        } else {
            try {
                if ($esPredet) {
                    $db->exec("UPDATE comunidades_regantes SET es_predeterminada = 0");
                }

                $stmt = $db->prepare("
                    UPDATE comunidades_regantes SET
                        nombre_comunidad = :nom,
                        cif = :cif,
                        email_solicitudes = :email,
                        telefono = :tel,
                        direccion = :dir,
                        municipio = :mun,
                        provincia = :prov,
                        banco_habitual = :banco,
                        derrama_anual = :derr,
                        precio_hora_tanda = :ptanda,
                        consumo_max_tanda_horas = :maxtanda,
                        precio_hora_comunitaria = :pcom,
                        consumo_max_comunitaria_horas = :maxcom,
                        precio_hora_hortalizas = :phort,
                        consumo_max_hortalizas_horas = :maxhort,
                        es_predeterminada = :predet,
                        horario_atencion = :horario,
                        notas = :notas
                    WHERE id_comunidad = :id
                ");
                $stmt->execute([
                    ':nom'      => $nombre,
                    ':cif'      => $cif ?: null,
                    ':email'    => $email,
                    ':tel'      => $tel ?: null,
                    ':dir'      => $dir ?: null,
                    ':mun'      => $mun ?: 'Moratalla',
                    ':prov'     => $prov ?: 'Murcia',
                    ':banco'    => $banco ?: 'CAJAMAR',
                    ':derr'     => $derrama,
                    ':ptanda'   => $pTanda,
                    ':maxtanda' => $maxTanda,
                    ':pcom'     => $pCom,
                    ':maxcom'   => $maxCom,
                    ':phort'    => $pHort,
                    ':maxhort'  => $maxHort,
                    ':predet'   => $esPredet,
                    ':horario'  => $horario,
                    ':notas'    => $notas ?: null,
                    ':id'       => $idCom
                ]);

                // Si sólo queda una activa, debe ser predeterminada
                $totActivas = (int)$db->query("SELECT COUNT(*) FROM comunidades_regantes WHERE activo = 1")->fetchColumn();
                if ($totActivas === 1) {
                    $db->exec("UPDATE comunidades_regantes SET es_predeterminada = 1 WHERE activo = 1");
                }

                $mensaje = "Comunidad <strong>" . htmlspecialchars($nombre) . "</strong> actualizada con éxito.";
            } catch (Exception $e) {
                $error = "Error al actualizar comunidad: " . $e->getMessage();
            }
        }
    }

    // MARCAR COMO PREDETERMINADA
    elseif ($action === 'hacer_predeterminada') {
        $idCom = (int)(isset($_POST['id_comunidad']) ? $_POST['id_comunidad'] : 0);
        try {
            $db->exec("UPDATE comunidades_regantes SET es_predeterminada = 0");
            $stmt = $db->prepare("UPDATE comunidades_regantes SET es_predeterminada = 1 WHERE id_comunidad = :id");
            $stmt->execute([':id' => $idCom]);
            $mensaje = "Comunidad establecida como <strong>predeterminada</strong> para todas las solicitudes de riego.";
        } catch (Exception $e) {
            $error = "Error al marcar predeterminada: " . $e->getMessage();
        }
    }

    // ELIMINAR COMUNIDAD
    elseif ($action === 'eliminar') {
        $idCom = (int)(isset($_POST['id_comunidad']) ? $_POST['id_comunidad'] : 0);
        try {
            $stmt = $db->prepare("DELETE FROM comunidades_regantes WHERE id_comunidad = :id");
            $stmt->execute([':id' => $idCom]);

            // Si sólo queda una, hacerla predeterminada
            $restantes = $db->query("SELECT id_comunidad FROM comunidades_regantes WHERE activo = 1")->fetchAll();
            if (count($restantes) === 1) {
                $db->prepare("UPDATE comunidades_regantes SET es_predeterminada = 1 WHERE id_comunidad = :id")
                   ->execute([':id' => $restantes[0]['id_comunidad']]);
            }
            $mensaje = "Comunidad de Regantes eliminada correctamente.";
        } catch (Exception $e) {
            $error = "Error al eliminar comunidad: " . $e->getMessage();
        }
    }
}

// Consultar todas las comunidades
$comunidades = $db->query("SELECT * FROM comunidades_regantes WHERE activo = 1 ORDER BY es_predeterminada DESC, nombre_comunidad ASC")->fetchAll();

// Comunidades vinculadas a la explotación activa
$comunidadesVinculadasIds = array();
if ($tenantId) {
    $stmtVin = $db->prepare("SELECT id_comunidad FROM explotaciones_comunidades WHERE id_propietario = :prop");
    $stmtVin->execute(array(':prop' => $tenantId));
    $comunidadesVinculadasIds = array_map('intval', $stmtVin->fetchAll(PDO::FETCH_COLUMN));
}

// Si sólo hay una, asegurar bandera predeterminada
if (count($comunidades) === 1 && empty($comunidades[0]['es_predeterminada'])) {
    $db->prepare("UPDATE comunidades_regantes SET es_predeterminada = 1 WHERE id_comunidad = :id")
       ->execute([':id' => $comunidades[0]['id_comunidad']]);
    $comunidades[0]['es_predeterminada'] = 1;
}

$comPredeterminada = null;
foreach ($comunidades as $c) {
    if (!empty($c['es_predeterminada'])) {
        $comPredeterminada = $c;
        break;
    }
}
if (!$comPredeterminada && !empty($comunidades)) {
    $comPredeterminada = $comunidades[0];
}
?>

<div class="container py-4">

    <!-- Encabezado con Azul Chulo Tecnológico -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-water fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Maestro de Comunidades de Regantes</h2>
                <span class="text-muted small">Gestión de entidades gestoras de agua, buzón de solicitudes oficiales, precios por modalidad y consumos máximos</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/solicitud-riego" class="btn btn-outline-primary fw-semibold">
                <i class="fa-solid fa-file-invoice me-1"></i> Ir a Solicitar Agua
            </a>
            <button type="button" class="btn btn-ag shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevaComunidad">
                <i class="fa-solid fa-plus-circle me-1"></i> Nueva Comunidad de Riego
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

    <!-- Tarjetas Informativas de Cabecera -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-custom p-3 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Total Comunidades Registradas</div>
                        <div class="fs-3 fw-bold text-dark"><?= count($comunidades) ?></div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="fa-solid fa-building-flag fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Comunidad Predeterminada</div>
                        <div class="fs-5 fw-bold text-success text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars(isset($comPredeterminada['nombre_comunidad']) ? $comPredeterminada['nombre_comunidad'] : 'Ninguna') ?>">
                            <?= htmlspecialchars(isset($comPredeterminada['nombre_comunidad']) ? $comPredeterminada['nombre_comunidad'] : 'Ninguna') ?>
                        </div>
                        <div class="small text-muted font-monospace"><i class="fa-solid fa-envelope me-1"></i><?= htmlspecialchars(isset($comPredeterminada['email_solicitudes']) ? $comPredeterminada['email_solicitudes'] : '-') ?></div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="fa-solid fa-star fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Tarifa Comunitaria / Hortalizas</div>
                        <div class="fs-4 fw-bold text-dark">
                            <?= number_format(isset($comPredeterminada['precio_hora_comunitaria']) ? $comPredeterminada['precio_hora_comunitaria'] : 12, 2) ?> € / 
                            <span class="text-success"><?= number_format(isset($comPredeterminada['precio_hora_hortalizas']) ? $comPredeterminada['precio_hora_hortalizas'] : 15, 2) ?> €</span>
                        </div>
                        <div class="small text-muted">Consumos máx: <?= (isset($comPredeterminada['consumo_max_comunitaria_horas']) && $comPredeterminada['consumo_max_comunitaria_horas'] > 0) ? $comPredeterminada['consumo_max_comunitaria_horas'].'h' : 'Según particip.' ?> | <?= (isset($comPredeterminada['consumo_max_hortalizas_horas']) && $comPredeterminada['consumo_max_hortalizas_horas'] > 0) ? $comPredeterminada['consumo_max_hortalizas_horas'].'h fijo' : 'Libre' ?></div>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-gauge-high fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Maestra de Comunidades de Regantes -->
    <div class="card card-custom p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-list-check me-2"></i>Comunidades y Tarifas de Riego</h5>
            <span class="badge bg-light text-dark border">Si sólo hay 1 comunidad, se propone automáticamente como predeterminada</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nombre de la Comunidad</th>
                        <th>Email de Solicitudes y Contacto</th>
                        <th class="text-center">Agua de Tanda</th>
                        <th class="text-center">Comunitaria Ordinaria</th>
                        <th class="text-center">Comunitaria HORTALIZAS</th>
                        <th class="text-center">Derrama Anual</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comunidades)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-water-ladder fs-1 d-block mb-2 opacity-50"></i>
                                No hay comunidades de regantes registradas. Pulsa en <strong>"Nueva Comunidad de Riego"</strong> para darla de alta.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($comunidades as $com): 
                            $estaVinculada = $isSuperAdmin || in_array((int)$com['id_comunidad'], $comunidadesVinculadasIds);
                        ?>
                            <tr class="<?= !empty($com['es_predeterminada']) ? 'table-primary bg-opacity-10' : ($estaVinculada ? '' : 'table-light text-muted') ?>">
                                <td class="ps-3">
                                    <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                        <?= htmlspecialchars($com['nombre_comunidad']) ?>
                                        <?php if (!empty($com['es_predeterminada'])): ?>
                                            <span class="badge bg-success small"><i class="fa-solid fa-star me-1"></i>Predeterminada</span>
                                        <?php endif; ?>
                                        <?php if (!$estaVinculada): ?>
                                            <span class="badge bg-secondary small" title="Esta comunidad no está asociada a la explotación activa"><i class="fa-solid fa-lock me-1"></i>No vinculada</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($estaVinculada): ?>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($com['municipio'] ?: 'Moratalla') ?> <?= $com['provincia'] ? '(' . htmlspecialchars($com['provincia']) . ')' : '' ?>
                                            <?php if ($com['cif']): ?>
                                                | CIF: <span class="font-monospace"><?= htmlspecialchars($com['cif']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="small text-muted fst-italic">Solo accesible el nombre. Debes vincularla a una explotación para consultar datos, tarifas y socios.</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($estaVinculada): ?>
                                        <div class="small fw-bold text-primary">
                                            <i class="fa-solid fa-envelope me-1 text-primary"></i><?= htmlspecialchars($com['email_solicitudes']) ?>
                                        </div>
                                        <?php if ($com['telefono']): ?>
                                            <div class="small text-muted"><i class="fa-solid fa-phone me-1 text-success"></i><?= htmlspecialchars($com['telefono']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border"><i class="fa-solid fa-lock me-1"></i>Restringido</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($estaVinculada): ?>
                                        <div class="fw-bold text-dark font-monospace"><?= number_format($com['precio_hora_tanda'], 2) ?> €/h</div>
                                        <div class="small">
                                            <?php if ($com['consumo_max_tanda_horas'] > 0): ?>
                                                <span class="badge bg-warning text-dark">Fijo: <?= number_format($com['consumo_max_tanda_horas'], 1) ?> h</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Según participaciones</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border"><i class="fa-solid fa-lock me-1"></i>Bloqueado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($estaVinculada): ?>
                                        <div class="fw-bold text-primary font-monospace"><?= number_format($com['precio_hora_comunitaria'], 2) ?> €/h</div>
                                        <div class="small">
                                            <?php if ($com['consumo_max_comunitaria_horas'] > 0): ?>
                                                <span class="badge bg-warning text-dark">Máx: <?= number_format($com['consumo_max_comunitaria_horas'], 1) ?> h</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Según participaciones</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border"><i class="fa-solid fa-lock me-1"></i>Bloqueado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($estaVinculada): ?>
                                        <div class="fw-bold text-success font-monospace"><?= number_format($com['precio_hora_hortalizas'], 2) ?> €/h</div>
                                        <div class="small">
                                            <?php if ($com['consumo_max_hortalizas_horas'] > 0): ?>
                                                <span class="badge bg-success bg-opacity-20 text-success fw-bold">Pautado: <?= number_format($com['consumo_max_hortalizas_horas'], 1) ?> h</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Según participaciones</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border"><i class="fa-solid fa-lock me-1"></i>Bloqueado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($estaVinculada): ?>
                                        <div class="fw-bold text-dark font-monospace"><?= number_format($com['derrama_anual'], 2) ?> €/año</div>
                                        <div class="small text-muted"><?= htmlspecialchars($com['banco_habitual'] ?: 'CAJAMAR') ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border"><i class="fa-solid fa-lock me-1"></i>Bloqueado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <?php if ($estaVinculada): ?>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= APP_URL ?>/solicitud-riego?comunidad_id=<?= $com['id_comunidad'] ?>" class="btn btn-outline-primary" title="Hacer petición de agua a esta comunidad">
                                                <i class="fa-solid fa-file-invoice"></i>
                                            </a>
                                            <a href="<?= APP_URL ?>/regantes?comunidad_id=<?= $com['id_comunidad'] ?>" class="btn btn-outline-info" title="Ver censo de socios de esta comunidad">
                                                <i class="fa-solid fa-users"></i>
                                            </a>

                                            <?php if (empty($com['es_predeterminada'])): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="action" value="hacer_predeterminada">
                                                    <input type="hidden" name="id_comunidad" value="<?= $com['id_comunidad'] ?>">
                                                    <button type="submit" class="btn btn-outline-success" title="Marcar como predeterminada">
                                                        <i class="fa-solid fa-star"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditarCom_<?= $com['id_comunidad'] ?>" title="Editar">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>

                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar la comunidad \'<?= htmlspecialchars(addslashes($com['nombre_comunidad'])) ?>\'?');">
                                                <input type="hidden" name="action" value="eliminar">
                                                <input type="hidden" name="id_comunidad" value="<?= $com['id_comunidad'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <a href="<?= APP_URL ?>/explotaciones" class="btn btn-sm btn-outline-primary" title="Vincular a tu explotación activa">
                                            <i class="fa-solid fa-link me-1"></i>Vincular
                                        </a>
                                    <?php endif; ?>
                                        </form>
                                    </div>

                                    <!-- Modal Editar Comunidad -->
                                    <div class="modal fade text-start" id="modalEditarCom_<?= $com['id_comunidad'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Editar Comunidad: <?= htmlspecialchars($com['nombre_comunidad']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="editar">
                                                    <input type="hidden" name="id_comunidad" value="<?= $com['id_comunidad'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3">
                                                            <div class="col-md-8">
                                                                <label class="form-label fw-semibold">Nombre de la Comunidad *</label>
                                                                <input type="text" name="nombre_comunidad" class="form-control fw-bold" value="<?= htmlspecialchars($com['nombre_comunidad']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">CIF / NIF</label>
                                                                <input type="text" name="cif" class="form-control font-monospace" value="<?= htmlspecialchars($com['cif'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Email Oficial para Solicitudes *</label>
                                                                <input type="email" name="email_solicitudes" class="form-control font-monospace fw-bold" value="<?= htmlspecialchars($com['email_solicitudes']) ?>" required>
                                                                <div class="form-text small">Donde se remitirán automáticamente las papeletas en PDF.</div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Teléfono de Contacto</label>
                                                                <input type="tel" name="telefono" class="form-control" value="<?= htmlspecialchars($com['telefono'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-8">
                                                                <label class="form-label fw-semibold">Dirección / Sede</label>
                                                                <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($com['direccion'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Municipio</label>
                                                                <input type="text" name="municipio" class="form-control" value="<?= htmlspecialchars($com['municipio'] ?: 'Moratalla') ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Entidad Bancaria Cobro</label>
                                                                <input type="text" name="banco_habitual" class="form-control" value="<?= htmlspecialchars($com['banco_habitual'] ?: 'CAJAMAR') ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Derrama Anual (€/año)</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.01" name="derrama_anual" class="form-control font-monospace fw-bold" value="<?= $com['derrama_anual'] ?>">
                                                                    <span class="input-group-text">€/año</span>
                                                                </div>
                                                            </div>

                                                            <!-- Tarifas y Cupos de Riego -->
                                                            <div class="col-12 mt-3">
                                                                <h6 class="fw-bold text-primary border-bottom pb-1"><i class="fa-solid fa-droplet me-1"></i>Tarifas y Cupos de Consumo Máximo / Fijo</h6>
                                                                <p class="text-muted small mb-2">Si el consumo máximo o fijo está a <code>0</code>, depende de las participaciones del socio. Si tiene una cantidad distinta de <code>0</code>, ese será el tope máximo pautado.</p>
                                                            </div>

                                                            <!-- Modalidad 1: Tanda -->
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Precio Hora Agua de Tanda</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.01" name="precio_hora_tanda" class="form-control font-monospace" value="<?= $com['precio_hora_tanda'] ?>">
                                                                    <span class="input-group-text">€/h</span>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Consumo Máximo/Fijo Tanda (Horas)</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.25" name="consumo_max_tanda_horas" class="form-control font-monospace" value="<?= $com['consumo_max_tanda_horas'] ?>">
                                                                    <span class="input-group-text">h (0=particip.)</span>
                                                                </div>
                                                            </div>

                                                            <!-- Modalidad 2: Comunitaria Ordinaria -->
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Precio Hora Comunitaria Ordinaria</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.01" name="precio_hora_comunitaria" class="form-control font-monospace fw-bold text-primary" value="<?= $com['precio_hora_comunitaria'] ?>">
                                                                    <span class="input-group-text">€/h</span>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Consumo Máximo/Fijo Comunitaria (Horas)</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.25" name="consumo_max_comunitaria_horas" class="form-control font-monospace" value="<?= $com['consumo_max_comunitaria_horas'] ?>">
                                                                    <span class="input-group-text">h (0=particip.)</span>
                                                                </div>
                                                            </div>

                                                            <!-- Modalidad 3: Comunitaria HORTALIZAS -->
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Precio Hora Comunitaria HORTALIZAS</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.01" name="precio_hora_hortalizas" class="form-control font-monospace fw-bold text-success" value="<?= $com['precio_hora_hortalizas'] ?>">
                                                                    <span class="input-group-text">€/h</span>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Consumo Máximo/Fijo HORTALIZAS (Horas)</label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.25" name="consumo_max_hortalizas_horas" class="form-control font-monospace" value="<?= $com['consumo_max_hortalizas_horas'] ?>">
                                                                    <span class="input-group-text">h (0=particip.)</span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 mt-2">
                                                                <div class="form-check form-switch fs-6">
                                                                    <input class="form-check-input" type="checkbox" id="predet_<?= $com['id_comunidad'] ?>" name="es_predeterminada" value="1" <?= !empty($com['es_predeterminada']) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label fw-semibold" for="predet_<?= $com['id_comunidad'] ?>">Marcar como Comunidad Predeterminada al solicitar agua</label>
                                                                </div>
                                                            </div>

                                                            <div class="col-12">
                                                                <label class="form-label fw-semibold small">Horario de Atención de la Oficina</label>
                                                                <input type="text" name="horario_atencion" class="form-control" value="<?= htmlspecialchars($com['horario_atencion'] ?: 'Lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00') ?>">
                                                            </div>

                                                            <div class="col-12">
                                                                <label class="form-label fw-semibold small">Notas / Instrucciones Internas</label>
                                                                <textarea name="notas" class="form-control" rows="2"><?= htmlspecialchars($com['notas'] ?: '') ?></textarea>
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

<!-- Modal Nueva Comunidad de Regantes -->
<div class="modal fade text-start" id="modalNuevaComunidad" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-plus-circle me-2"></i> Alta de Nueva Comunidad de Regantes
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nombre de la Comunidad *</label>
                            <input type="text" name="nombre_comunidad" class="form-control fw-bold" placeholder="Ej: Comunidad de Regantes Río Alhárabe" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">CIF / NIF</label>
                            <input type="text" name="cif" class="form-control font-monospace" placeholder="G30000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Oficial para Solicitudes *</label>
                            <input type="email" name="email_solicitudes" class="form-control font-monospace fw-bold" placeholder="alharabemoratalla@gmail.com" required>
                            <div class="form-text small">Dirección donde se enviarán automáticamente las solicitudes en PDF.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Teléfono de Contacto</label>
                            <input type="tel" name="telefono" class="form-control" placeholder="968 00 00 00">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Dirección / Sede</label>
                            <input type="text" name="direccion" class="form-control" placeholder="Calle Mayor, s/n">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Municipio</label>
                            <input type="text" name="municipio" class="form-control" value="Moratalla">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Entidad Bancaria Cobro</label>
                            <input type="text" name="banco_habitual" class="form-control" value="CAJAMAR">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Derrama Anual (€/año)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="derrama_anual" class="form-control font-monospace fw-bold" value="150.00">
                                <span class="input-group-text">€/año</span>
                            </div>
                        </div>

                        <!-- Tarifas y Cupos -->
                        <div class="col-12 mt-3">
                            <h6 class="fw-bold text-primary border-bottom pb-1"><i class="fa-solid fa-droplet me-1"></i>Tarifas y Cupos de Consumo Máximo / Fijo</h6>
                            <p class="text-muted small mb-2">Si el consumo máximo o fijo está a <code>0</code>, depende de las participaciones. Si tiene una cantidad distinta de <code>0</code>, ese será el tope máximo pautado.</p>
                        </div>

                        <!-- Tanda -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Precio Hora Agua de Tanda</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="precio_hora_tanda" class="form-control font-monospace" value="0.00">
                                <span class="input-group-text">€/h</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Consumo Máximo/Fijo Tanda (Horas)</label>
                            <div class="input-group">
                                <input type="number" step="0.25" name="consumo_max_tanda_horas" class="form-control font-monospace" value="0.00">
                                <span class="input-group-text">h (0=particip.)</span>
                            </div>
                        </div>

                        <!-- Comunitaria Ordinaria -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Precio Hora Comunitaria Ordinaria</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="precio_hora_comunitaria" class="form-control font-monospace fw-bold text-primary" value="12.00">
                                <span class="input-group-text">€/h</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Consumo Máximo/Fijo Comunitaria (Horas)</label>
                            <div class="input-group">
                                <input type="number" step="0.25" name="consumo_max_comunitaria_horas" class="form-control font-monospace" value="0.00">
                                <span class="input-group-text">h (0=particip.)</span>
                            </div>
                        </div>

                        <!-- Comunitaria HORTALIZAS -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Precio Hora Comunitaria HORTALIZAS</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="precio_hora_hortalizas" class="form-control font-monospace fw-bold text-success" value="15.00">
                                <span class="input-group-text">€/h</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Consumo Máximo/Fijo HORTALIZAS (Horas)</label>
                            <div class="input-group">
                                <input type="number" step="0.25" name="consumo_max_hortalizas_horas" class="form-control font-monospace" value="2.00">
                                <span class="input-group-text">h (0=particip.)</span>
                            </div>
                        </div>

                        <div class="col-12 mt-2">
                            <div class="form-check form-switch fs-6">
                                <input class="form-check-input" type="checkbox" id="nueva_predet" name="es_predeterminada" value="1">
                                <label class="form-check-label fw-semibold" for="nueva_predet">Establecer como Comunidad Predeterminada</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Horario de Atención de la Oficina</label>
                            <input type="text" name="horario_atencion" class="form-control" value="Lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Notas / Observaciones</label>
                            <textarea name="notas" class="form-control" rows="2" placeholder="Observaciones de la comunidad..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-ag fw-bold"><i class="fa-solid fa-plus-circle me-1"></i>Dar de Alta Comunidad</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
