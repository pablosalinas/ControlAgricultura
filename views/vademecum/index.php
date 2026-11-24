<?php
/**
 * Vademécum de Semillas y Plantas
 * - Muestra las fichas de todos los usuarios de la misma explotación agrícola.
 * - Muestra también los vademécums compartidos desde otras explotaciones agrícolas.
 * - Solo el Superusuario puede asignar/revocar comparticiones entre usuarios de distintas explotaciones.
 *
 * Compatible con PHP 5.6, 7.x, 8.x
 */

$pageTitle = "Vademécum de Semillas y Plantas - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';
require_once SRC_PATH . '/Services/VademecumAccess.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

if (!empty($_GET['ok'])) {
    $mensaje = "Ficha guardada correctamente en tu Vademécum.";
}

// ACCIONES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. Borrado lógico seguro (activo = 0) solo para registros de la propia explotación
    if ($action === 'borrar_vademecum') {
        $idDel = (int)$_POST['id_vademecum'];
        try {
            $editable = VademecumAccess::findEditableById($db, $idDel, $tenantId, $userId);
            if (!$editable) {
                $error = "No tienes permisos para archivar este registro (puede pertenecer a otra explotación compartida).";
            } else {
                $stmtDel = $db->prepare("UPDATE vademecum_semillas_plantas SET activo = 0 WHERE id_vademecum = :id");
                $stmtDel->execute(array(':id' => $idDel));
                $mensaje = "Registro archivado correctamente del Vademécum (borrado lógico seguro).";
            }
        } catch (Exception $e) {
            $error = "Error al archivar el registro: " . $e->getMessage();
        }
    }

    // 2. Copiar una ficha compartida a mi propia explotación/vademécum
    elseif ($action === 'copiar_vademecum') {
        $idSrc = (int)$_POST['id_vademecum'];
        try {
            $paramsChk = array(':id_src' => $idSrc);
            $whereAcc = VademecumAccess::getAccessibleWhereSql($tenantId, $userId, $paramsChk);
            $stmtSrc = $db->prepare("SELECT * FROM vademecum_semillas_plantas v WHERE v.id_vademecum = :id_src AND {$whereAcc} LIMIT 1");
            $stmtSrc->execute($paramsChk);
            $src = $stmtSrc->fetch();

            if (!$src) {
                $error = "No se encontró la ficha compartida o no tienes acceso.";
            } else {
                $stmtCopy = $db->prepare("
                    INSERT INTO vademecum_semillas_plantas (
                        id_propietario, id_usuario_creador, tipo_registro,
                        nombre_comun, nombre_cientifico, variedad,
                        tax_reino, tax_division, tax_clase, tax_orden, tax_familia, tax_genero, tax_especie, tax_subespecie,
                        es_transgenico, detalle_transgenico, origen_semilla,
                        caracteristicas_semilla, caracteristicas_planta,
                        fecha_siembra, fecha_crecimiento, fecha_recoleccion,
                        estado_tierra, abono_es_ecologico, tipo_abono,
                        enfermedades_plagas, tratamiento_es_ecologico, tratamiento_enfermedades,
                        foto_planta_completa, foto_tallo_hojas, foto_flor,
                        notas, fuente_ia, url_referencia, activo
                    ) VALUES (
                        :prop, :uid, :tipo_reg,
                        :comun, :cien, :var,
                        :t_rei, :t_div, :t_cla, :t_ord, :t_fam, :t_gen, :t_esp, :t_sub,
                        :transg, :det_transg, :origen,
                        :c_sem, :c_pla,
                        :f_siem, :f_crec, :f_reco,
                        :tierra, :ab_eco, :t_abono,
                        :enf, :tr_eco, :trat,
                        :f_planta, :f_tallo, :f_flor,
                        :notas, :fuente, :url, 1
                    )
                ");
                $stmtCopy->execute(array(
                    ':prop'       => $tenantId ?: null,
                    ':uid'        => $userId ?: null,
                    ':tipo_reg'   => $src['tipo_registro'],
                    ':comun'      => $src['nombre_comun'],
                    ':cien'       => $src['nombre_cientifico'],
                    ':var'        => $src['variedad'],
                    ':t_rei'      => $src['tax_reino'],
                    ':t_div'      => $src['tax_division'],
                    ':t_cla'      => $src['tax_clase'],
                    ':t_ord'      => $src['tax_orden'],
                    ':t_fam'      => $src['tax_familia'],
                    ':t_gen'      => $src['tax_genero'],
                    ':t_esp'      => $src['tax_especie'],
                    ':t_sub'      => $src['tax_subespecie'],
                    ':transg'     => $src['es_transgenico'],
                    ':det_transg' => $src['detalle_transgenico'],
                    ':origen'     => $src['origen_semilla'],
                    ':c_sem'      => $src['caracteristicas_semilla'],
                    ':c_pla'      => $src['caracteristicas_planta'],
                    ':f_siem'     => $src['fecha_siembra'],
                    ':f_crec'     => $src['fecha_crecimiento'],
                    ':f_reco'     => $src['fecha_recoleccion'],
                    ':tierra'     => $src['estado_tierra'],
                    ':ab_eco'     => $src['abono_es_ecologico'],
                    ':t_abono'    => $src['tipo_abono'],
                    ':enf'        => $src['enfermedades_plagas'],
                    ':tr_eco'     => $src['tratamiento_es_ecologico'],
                    ':trat'       => $src['tratamiento_enfermedades'],
                    ':f_planta'   => $src['foto_planta_completa'],
                    ':f_tallo'    => $src['foto_tallo_hojas'],
                    ':f_flor'     => $src['foto_flor'],
                    ':notas'      => $src['notas'],
                    ':fuente'     => $src['fuente_ia'],
                    ':url'        => $src['url_referencia']
                ));
                $mensaje = "Ficha <strong>" . htmlspecialchars($src['nombre_comun']) . "</strong> copiada a tu explotación correctamente.";
            }
        } catch (Exception $e) {
            $error = "Error al copiar la ficha: " . $e->getMessage();
        }
    }

    // 3. Compartir Vademécum entre usuarios de distintas explotaciones (SOLO SUPERUSUARIO)
    elseif ($action === 'compartir_vademecum') {
        if (!$isSuperAdmin) {
            $error = "Acción denegada: solo el Superusuario puede compartir vademécums entre distintas explotaciones agrícolas.";
        } else {
            $idOrigUser = (int)(isset($_POST['id_usuario_origen']) ? $_POST['id_usuario_origen'] : 0);
            $idDestUser = (int)(isset($_POST['id_usuario_destino']) ? $_POST['id_usuario_destino'] : 0);
            $notasComp = trim(isset($_POST['notas_compartido']) ? $_POST['notas_compartido'] : '');

            if ($idOrigUser <= 0 || $idDestUser <= 0 || $idOrigUser === $idDestUser) {
                $error = "Debes seleccionar dos usuarios distintos (origen y destino).";
            } else {
                $propOrig = VademecumAccess::getUserPrimaryTenantId($db, $idOrigUser);
                $propDest = VademecumAccess::getUserPrimaryTenantId($db, $idDestUser);

                if (!empty($propOrig) && !empty($propDest) && (int)$propOrig === (int)$propDest) {
                    $error = "Ambos usuarios pertenecen a la misma explotación agrícola, por lo que ya comparten el Vademécum automáticamente.";
                } else {
                    try {
                        // Evitar duplicados activos
                        $chkDup = $db->prepare("
                            SELECT id_compartido FROM vademecum_compartido
                            WHERE activo = 1 AND id_usuario_origen = :uo AND id_usuario_destino = :ud
                            LIMIT 1
                        ");
                        $chkDup->execute(array(':uo' => $idOrigUser, ':ud' => $idDestUser));
                        if ($chkDup->fetchColumn()) {
                            $error = "Esa compartición ya se encuentra activa.";
                        } else {
                            $stmtShare = $db->prepare("
                                INSERT INTO vademecum_compartido (
                                    id_usuario_origen, id_propietario_origen,
                                    id_usuario_destino, id_propietario_destino,
                                    asignado_por_id, notas, activo
                                ) VALUES (:uo, :po, :ud, :pd, :admin, :notas, 1)
                            ");
                            $stmtShare->execute(array(
                                ':uo'    => $idOrigUser,
                                ':po'    => $propOrig,
                                ':ud'    => $idDestUser,
                                ':pd'    => $propDest,
                                ':admin' => $userId,
                                ':notas' => $notasComp ?: null
                            ));
                            $mensaje = "Vademécum compartido correctamente. Ahora está disponible para el usuario destino y para todos los usuarios de su explotación agrícola.";
                        }
                    } catch (Exception $e) {
                        $error = "Error al crear la compartición: " . $e->getMessage();
                    }
                }
            }
        }
    }

    // 4. Revocar compartición (borrado lógico activo = 0, SOLO SUPERUSUARIO)
    elseif ($action === 'revocar_compartido') {
        if (!$isSuperAdmin) {
            $error = "Acción denegada: solo el Superusuario puede revocar comparticiones.";
        } else {
            $idComp = (int)(isset($_POST['id_compartido']) ? $_POST['id_compartido'] : 0);
            try {
                $stmtRev = $db->prepare("UPDATE vademecum_compartido SET activo = 0 WHERE id_compartido = :id");
                $stmtRev->execute(array(':id' => $idComp));
                $mensaje = "Compartición revocada correctamente.";
            } catch (Exception $e) {
                $error = "Error al revocar la compartición: " . $e->getMessage();
            }
        }
    }
}

// Filtros
$filtroQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroTipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$filtroTransg = isset($_GET['transgenico']) ? trim($_GET['transgenico']) : '';

$params = array();
$whereAccess = VademecumAccess::getAccessibleWhereSql($tenantId, $userId, $params);

$sql = "
    SELECT v.*,
           u.nombre AS autor_nombre,
           u.apellidos AS autor_apellidos,
           p.nombre_razon_social AS explotacion_nombre
    FROM vademecum_semillas_plantas v
    LEFT JOIN usuarios u ON v.id_usuario_creador = u.id_usuario
    LEFT JOIN propietarios p ON v.id_propietario = p.id_propietario
    WHERE {$whereAccess}
";

if (!empty($filtroQ)) {
    $sql .= " AND (v.nombre_comun LIKE :q OR v.nombre_cientifico LIKE :q OR v.variedad LIKE :q OR v.tax_familia LIKE :q OR v.tax_especie LIKE :q OR v.enfermedades_plagas LIKE :q)";
    $params[':q'] = '%' . $filtroQ . '%';
}

if (!empty($filtroTipo) && in_array($filtroTipo, array('semilla', 'planta', 'ambos'))) {
    $sql .= " AND v.tipo_registro = :tipo";
    $params[':tipo'] = $filtroTipo;
}

if ($filtroTransg === '1' || $filtroTransg === '0') {
    $sql .= " AND v.es_transgenico = :transg";
    $params[':transg'] = (int)$filtroTransg;
}

$sql .= " ORDER BY v.nombre_comun ASC, v.id_vademecum DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Datos para el panel Superusuario de compartición entre explotaciones
$usuariosActivos = array();
$comparticionesActivas = array();
if ($isSuperAdmin) {
    $usuariosActivos = $db->query("
        SELECT u.id_usuario, u.nombre, u.apellidos, u.email, u.id_propietario,
               p.nombre_razon_social AS nombre_explotacion
        FROM usuarios u
        LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
        WHERE u.estado = 'aprobado'
        ORDER BY p.nombre_razon_social ASC, u.nombre ASC
    ")->fetchAll();

    $comparticionesActivas = $db->query("
        SELECT vc.*,
               uo.nombre AS orig_nombre, uo.apellidos AS orig_apellidos, po.nombre_razon_social AS orig_explotacion,
               ud.nombre AS dest_nombre, ud.apellidos AS dest_apellidos, pd.nombre_razon_social AS dest_explotacion
        FROM vademecum_compartido vc
        LEFT JOIN usuarios uo ON vc.id_usuario_origen = uo.id_usuario
        LEFT JOIN propietarios po ON vc.id_propietario_origen = po.id_propietario
        LEFT JOIN usuarios ud ON vc.id_usuario_destino = ud.id_usuario
        LEFT JOIN propietarios pd ON vc.id_propietario_destino = pd.id_propietario
        WHERE vc.activo = 1
        ORDER BY vc.creado_el DESC
    ")->fetchAll();
}
?>

<div class="container py-4">

    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #059669);">
                <i class="fa-solid fa-book-medical fs-3 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary">Vademécum de Semillas y Plantas</h2>
                <p class="text-muted mb-0 small">
                    Archivo de la explotación agrícola (accesible para todos sus usuarios y vademécums compartidos)
                </p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($isSuperAdmin): ?>
                <button type="button" class="btn btn-outline-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCompartirVademecum">
                    <i class="fa-solid fa-share-nodes me-1"></i> Compartir entre Explotaciones
                    <?php if (!empty($comparticionesActivas)): ?>
                        <span class="badge bg-primary ms-1"><?= count($comparticionesActivas) ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/vademecum/nuevo" class="btn btn-warning fw-bold text-dark shadow-sm">
                <i class="fa-solid fa-plus-circle me-1"></i> Añadir Semilla / Planta con IA
            </a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card card-custom mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= APP_URL ?>/vademecum" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0"
                               placeholder="Buscar por nombre, especie, familia, variedad o enfermedad..."
                               value="<?= htmlspecialchars($filtroQ) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="tipo" class="form-select">
                        <option value="">Todos los tipos</option>
                        <option value="semilla" <?= $filtroTipo === 'semilla' ? 'selected' : '' ?>>Solo Semillas</option>
                        <option value="planta" <?= $filtroTipo === 'planta' ? 'selected' : '' ?>>Solo Plantas</option>
                        <option value="ambos" <?= $filtroTipo === 'ambos' ? 'selected' : '' ?>>Semilla y Planta</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="transgenico" class="form-select">
                        <option value="">Genética: Todas</option>
                        <option value="0" <?= $filtroTransg === '0' ? 'selected' : '' ?>>No Transgénico</option>
                        <option value="1" <?= $filtroTransg === '1' ? 'selected' : '' ?>>Transgénico (OMG)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-ag w-100">Filtrar</button>
                    <?php if (!empty($filtroQ) || !empty($filtroTipo) || $filtroTransg !== ''): ?>
                        <a href="<?= APP_URL ?>/vademecum" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado -->
    <?php if (empty($items)): ?>
        <div class="card card-custom text-center py-5">
            <div class="card-body">
                <i class="fa-solid fa-seedling fs-1 text-success opacity-50 mb-3"></i>
                <h5 class="fw-bold text-dark">Tu Vademécum está vacío</h5>
                <p class="text-muted small mb-4">
                    Aquí solo aparecerán las semillas y plantas añadidas en tu explotación agrícola (o compartidas contigo desde otra explotación).
                </p>
                <a href="<?= APP_URL ?>/vademecum/nuevo" class="btn btn-warning fw-bold text-dark px-4">
                    <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Añadir mi primera semilla o planta
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($items as $item):
                $thumb = '';
                if (!empty($item['foto_planta_completa'])) $thumb = $item['foto_planta_completa'];
                elseif (!empty($item['foto_flor'])) $thumb = $item['foto_flor'];
                elseif (!empty($item['foto_tallo_hojas'])) $thumb = $item['foto_tallo_hojas'];

                $numFotos = (!empty($item['foto_planta_completa']) ? 1 : 0)
                          + (!empty($item['foto_tallo_hojas']) ? 1 : 0)
                          + (!empty($item['foto_flor']) ? 1 : 0);

                $esPropioExplotacion = VademecumAccess::isOwnOrSameTenant($item, $tenantId, $userId);
                $autorFull = trim((isset($item['autor_nombre']) ? $item['autor_nombre'] : '') . ' ' . (isset($item['autor_apellidos']) ? $item['autor_apellidos'] : ''));
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom h-100 overflow-hidden d-flex flex-column">
                    <div class="position-relative" style="height: 170px; background: #e2e8f0;">
                        <?php if ($thumb): ?>
                            <img src="<?= APP_URL . '/' . htmlspecialchars(ltrim($thumb, '/')) ?>"
                                 class="w-100 h-100" style="object-fit: cover;" alt="<?= htmlspecialchars($item['nombre_comun']) ?>">
                        <?php else: ?>
                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                                <i class="fa-solid fa-leaf fs-1 opacity-25"></i>
                                <span class="small opacity-75 mt-1">Sin fotografía aún</span>
                            </div>
                        <?php endif; ?>

                        <div class="position-absolute top-0 start-0 p-2 d-flex gap-1 flex-wrap">
                            <span class="badge bg-dark bg-opacity-75 text-uppercase"><?= htmlspecialchars($item['tipo_registro']) ?></span>
                            <?php if ((int)$item['es_transgenico'] === 1): ?>
                                <span class="badge bg-danger">Transgénico (OMG)</span>
                            <?php else: ?>
                                <span class="badge bg-success">No Transgénico</span>
                            <?php endif; ?>
                            <?php if (!$esPropioExplotacion): ?>
                                <span class="badge bg-info text-dark"><i class="fa-solid fa-share-nodes me-1"></i>Compartido</span>
                            <?php endif; ?>
                        </div>

                        <div class="position-absolute bottom-0 end-0 p-2">
                            <span class="badge bg-light text-dark shadow-sm">
                                <i class="fa-solid fa-camera me-1 text-primary"></i><?= $numFotos ?>/3 fotos
                            </span>
                        </div>
                    </div>

                    <div class="card-body d-flex flex-column flex-grow-1 p-3">
                        <h5 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($item['nombre_comun']) ?></h5>
                        <?php if (!empty($item['nombre_cientifico'])): ?>
                            <div class="small fst-italic text-muted mb-1"><?= htmlspecialchars($item['nombre_cientifico']) ?></div>
                        <?php endif; ?>

                        <?php if ($autorFull || !empty($item['explotacion_nombre'])): ?>
                            <div class="text-muted mb-2" style="font-size: 0.76rem;">
                                <i class="fa-solid fa-user-tag me-1"></i>
                                <?= htmlspecialchars($autorFull ?: 'Usuario') ?>
                                <?php if (!empty($item['explotacion_nombre'])): ?>
                                    · <span class="fw-semibold"><?= htmlspecialchars($item['explotacion_nombre']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <?php if (!empty($item['tax_familia'])): ?>
                                <span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25">Fam: <?= htmlspecialchars($item['tax_familia']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['variedad'])): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-dark border">Var: <?= htmlspecialchars($item['variedad']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="small text-muted mb-3 flex-grow-1">
                            <?php if (!empty($item['fecha_siembra'])): ?>
                                <div><i class="fa-solid fa-seedling me-1 text-success"></i><strong>Siembra:</strong> <?= htmlspecialchars($item['fecha_siembra']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['fecha_recoleccion'])): ?>
                                <div><i class="fa-solid fa-basket-shopping me-1 text-warning"></i><strong>Recolección:</strong> <?= htmlspecialchars($item['fecha_recoleccion']) ?></div>
                            <?php endif; ?>
                            <div class="mt-1">
                                <span class="badge <?= (int)$item['abono_es_ecologico'] === 1 ? 'bg-success bg-opacity-75' : 'bg-secondary' ?>">
                                    Abono <?= (int)$item['abono_es_ecologico'] === 1 ? 'Eco' : 'Conv.' ?>
                                </span>
                                <span class="badge <?= (int)$item['tratamiento_es_ecologico'] === 1 ? 'bg-success bg-opacity-75' : 'bg-secondary' ?>">
                                    Tratamiento <?= (int)$item['tratamiento_es_ecologico'] === 1 ? 'Eco' : 'Conv.' ?>
                                </span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 btn-ver-ficha"
                                    data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>'
                                    data-editable="<?= $esPropioExplotacion ? '1' : '0' ?>">
                                <i class="fa-solid fa-eye me-1"></i>Ficha
                            </button>

                            <?php if ($esPropioExplotacion): ?>
                                <a href="<?= APP_URL ?>/vademecum/editar?id=<?= (int)$item['id_vademecum'] ?>"
                                   class="btn btn-sm btn-outline-success flex-grow-1" title="Editar, añadir fotos o re-analizar con IA">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Editar / IA
                                </a>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Archivar esta ficha del Vademécum?');">
                                    <input type="hidden" name="action" value="borrar_vademecum">
                                    <input type="hidden" name="id_vademecum" value="<?= (int)$item['id_vademecum'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Archivar">
                                        <i class="fa-solid fa-box-archive"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="" class="d-inline flex-grow-1">
                                    <input type="hidden" name="action" value="copiar_vademecum">
                                    <input type="hidden" name="id_vademecum" value="<?= (int)$item['id_vademecum'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-info w-100" title="Copiar esta ficha a mi explotación para editarla">
                                        <i class="fa-solid fa-copy me-1"></i>Copiar a mi exp.
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL FICHA COMPLETA -->
<div class="modal fade" id="modalFichaVademecum" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #059669);">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="mTitulo">Ficha Botánica</h5>
                    <small class="fst-italic opacity-75" id="mCientifico"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="mBody"></div>
            <div class="modal-footer bg-light">
                <a href="#" id="mBtnEditar" class="btn btn-success"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Editar / Añadir fotos y Re-analizar IA</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php if ($isSuperAdmin): ?>
<!-- MODAL SUPERUSUARIO: COMPARTIR VADEMÉCUM ENTRE EXPLOTACIONES AGRÍCOLAS -->
<div class="modal fade" id="modalCompartirVademecum" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <div>
                    <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-share-nodes me-2 text-warning"></i>Compartir Vademécum entre Explotaciones Agrícolas</h5>
                    <small class="opacity-75">Asignación exclusiva de Superusuario · Al compartir con un usuario, queda disponible para toda su explotación</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" action="" class="border rounded-3 p-3 bg-light mb-4">
                    <input type="hidden" name="action" value="compartir_vademecum">
                    <h6 class="fw-bold text-primary mb-3">Nueva Asignación de Compartición</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Usuario Origen (dueño del Vademécum)</label>
                            <select name="id_usuario_origen" class="form-select" required>
                                <option value="">-- Seleccionar usuario origen --</option>
                                <?php foreach ($usuariosActivos as $u): ?>
                                    <option value="<?= (int)$u['id_usuario'] ?>">
                                        <?= htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellidos'])) ?>
                                        (<?= htmlspecialchars($u['nombre_explotacion'] ?: 'Sin explotación') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Usuario Destino (otra explotación)</label>
                            <select name="id_usuario_destino" class="form-select" required>
                                <option value="">-- Seleccionar usuario destino --</option>
                                <?php foreach ($usuariosActivos as $u): ?>
                                    <option value="<?= (int)$u['id_usuario'] ?>">
                                        <?= htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellidos'])) ?>
                                        (<?= htmlspecialchars($u['nombre_explotacion'] ?: 'Sin explotación') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-9">
                            <input type="text" name="notas_compartido" class="form-control form-control-sm" placeholder="Motivo o nota opcional de la compartición...">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-ag btn-sm fw-bold">
                                <i class="fa-solid fa-check me-1"></i>Compartir
                            </button>
                        </div>
                    </div>
                </form>

                <h6 class="fw-bold text-dark mb-2">Comparticiones Activas</h6>
                <?php if (empty($comparticionesActivas)): ?>
                    <p class="text-muted small mb-0">No hay vademécums compartidos entre distintas explotaciones actualmente.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Origen</th>
                                    <th>Destino (y su explotación)</th>
                                    <th>Nota</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comparticionesActivas as $c): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars(trim($c['orig_nombre'] . ' ' . $c['orig_apellidos'])) ?></strong><br>
                                            <span class="text-muted"><?= htmlspecialchars($c['orig_explotacion'] ?: 'Sin explotación') ?></span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars(trim($c['dest_nombre'] . ' ' . $c['dest_apellidos'])) ?></strong><br>
                                            <span class="badge bg-info text-dark"><?= htmlspecialchars($c['dest_explotacion'] ?: 'Sin explotación') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($c['notas'] ?: '-') ?></td>
                                        <td class="text-end">
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Revocar esta compartición?');">
                                                <input type="hidden" name="action" value="revocar_compartido">
                                                <input type="hidden" name="id_compartido" value="<?= (int)$c['id_compartido'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Revocar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    document.querySelectorAll('.btn-ver-ficha').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var item = JSON.parse(this.getAttribute('data-item'));
            var editable = this.getAttribute('data-editable') === '1';

            document.getElementById('mTitulo').textContent = item.nombre_comun + (item.variedad ? ' (' + item.variedad + ')' : '');
            document.getElementById('mCientifico').textContent = item.nombre_cientifico || '';
            var btnEd = document.getElementById('mBtnEditar');
            if (editable) {
                btnEd.href = window.APP_URL + '/vademecum/editar?id=' + item.id_vademecum;
                btnEd.classList.remove('d-none');
            } else {
                btnEd.classList.add('d-none');
            }

            var photosHtml = '<div class="row g-2 mb-4">';
            var photoDefs = [
                { key: 'foto_planta_completa', label: '1. Planta Completa' },
                { key: 'foto_tallo_hojas', label: '2. Tallo y Hojas' },
                { key: 'foto_flor', label: '3. Flor' }
            ];
            photoDefs.forEach(function(pd) {
                var url = item[pd.key] ? (window.APP_URL + '/' + item[pd.key].replace(/^\/+/, '')) : '';
                photosHtml += '<div class="col-md-4 text-center"><div class="border rounded p-2 bg-light h-100">';
                photosHtml += '<div class="small fw-bold mb-1">' + pd.label + '</div>';
                if (url) {
                    photosHtml += '<img src="' + esc(url) + '" class="img-fluid rounded" style="max-height:170px; object-fit:cover;">';
                } else {
                    photosHtml += '<div class="text-muted small py-4">Sin foto</div>';
                }
                photosHtml += '</div></div>';
            });
            photosHtml += '</div>';

            var taxHtml = '<div class="row g-2 mb-3 small">';
            var taxes = [
                ['Reino', item.tax_reino], ['División', item.tax_division],
                ['Clase', item.tax_clase], ['Orden', item.tax_orden],
                ['Familia', item.tax_familia], ['Género', item.tax_genero],
                ['Especie', item.tax_especie], ['Subespecie', item.tax_subespecie]
            ];
            taxes.forEach(function(t) {
                taxHtml += '<div class="col-md-3 col-6"><div class="p-2 border rounded bg-white"><strong>' + t[0] + ':</strong> ' + (esc(t[1]) || '-') + '</div></div>';
            });
            taxHtml += '</div>';

            var html = photosHtml;
            html += '<h6 class="fw-bold text-primary border-bottom pb-1">Taxonomía Botánica</h6>' + taxHtml;

            html += '<div class="row g-3 small">';
            html += '<div class="col-md-6"><div class="p-3 border rounded h-100">';
            html += '<h6 class="fw-bold text-success">Genética y Semilla</h6>';
            html += '<p class="mb-1"><strong>Transgénico (OMG):</strong> ' + (parseInt(item.es_transgenico, 10) === 1 ? '<span class="badge bg-danger">Sí</span>' : '<span class="badge bg-success">No</span>') + '</p>';
            if (item.detalle_transgenico) html += '<p class="mb-1"><strong>Detalle genético:</strong> ' + esc(item.detalle_transgenico) + '</p>';
            if (item.origen_semilla) html += '<p class="mb-1"><strong>Origen:</strong> ' + esc(item.origen_semilla) + '</p>';
            if (item.caracteristicas_semilla) html += '<p class="mb-1"><strong>Semilla:</strong> ' + esc(item.caracteristicas_semilla) + '</p>';
            if (item.caracteristicas_planta) html += '<p class="mb-0"><strong>Planta:</strong> ' + esc(item.caracteristicas_planta) + '</p>';
            html += '</div></div>';

            html += '<div class="col-md-6"><div class="p-3 border rounded h-100">';
            html += '<h6 class="fw-bold text-warning">Calendario y Suelo</h6>';
            html += '<p class="mb-1"><strong>Siembra:</strong> ' + (esc(item.fecha_siembra) || '-') + '</p>';
            html += '<p class="mb-1"><strong>Crecimiento:</strong> ' + (esc(item.fecha_crecimiento) || '-') + '</p>';
            html += '<p class="mb-1"><strong>Recolección:</strong> ' + (esc(item.fecha_recoleccion) || '-') + '</p>';
            html += '<p class="mb-0"><strong>Estado de la tierra:</strong> ' + (esc(item.estado_tierra) || '-') + '</p>';
            html += '</div></div>';

            html += '<div class="col-md-6"><div class="p-3 border rounded h-100">';
            html += '<h6 class="fw-bold text-primary">Abonado (' + (parseInt(item.abono_es_ecologico, 10) === 1 ? 'Ecológico' : 'No Ecológico') + ')</h6>';
            html += '<p class="mb-0" style="white-space:pre-line;">' + (esc(item.tipo_abono) || '-') + '</p>';
            html += '</div></div>';

            html += '<div class="col-md-6"><div class="p-3 border rounded h-100">';
            html += '<h6 class="fw-bold text-danger">Enfermedades y Tratamiento (' + (parseInt(item.tratamiento_es_ecologico, 10) === 1 ? 'Ecológico' : 'No Ecológico') + ')</h6>';
            html += '<p class="mb-2" style="white-space:pre-line;"><strong>Enfermedades/Plagas:</strong> ' + (esc(item.enfermedades_plagas) || '-') + '</p>';
            html += '<p class="mb-0" style="white-space:pre-line;"><strong>Tratamiento:</strong> ' + (esc(item.tratamiento_enfermedades) || '-') + '</p>';
            html += '</div></div>';
            html += '</div>';

            if (item.notas || item.url_referencia) {
                html += '<div class="mt-3 p-3 border rounded bg-light small">';
                if (item.notas) html += '<p class="mb-1"><strong>Notas:</strong> ' + esc(item.notas) + '</p>';
                if (item.url_referencia) html += '<p class="mb-0"><strong>Referencia:</strong> <a href="' + esc(item.url_referencia) + '" target="_blank">' + esc(item.url_referencia) + '</a></p>';
                html += '</div>';
            }

            document.getElementById('mBody').innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById('modalFichaVademecum'));
            modal.show();
        });
    });
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
