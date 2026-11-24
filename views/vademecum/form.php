<?php
/**
 * Alta y Edición en el Vademécum de Semillas y Plantas
 * Incluye Asistente IA Multimodal (búsqueda por datos + fotos libres + identificación por hasta 5 fotos)
 * y re-análisis incremental conforme se añaden nuevas fotos o datos.
 */

require_once SRC_PATH . '/Services/VademecumAccess.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$idVademecum = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id_vademecum']) ? (int)$_POST['id_vademecum'] : 0);
$isEdit = ($idVademecum > 0);
$registro = array();

if ($isEdit) {
    $registro = VademecumAccess::findEditableById($db, $idVademecum, $tenantId, $userId);
    if (!$registro) {
        header("Location: " . APP_URL . "/vademecum");
        exit;
    }
}

$pageTitle = ($isEdit ? "Editar Ficha del Vademécum" : "Nueva Semilla o Planta - Vademécum") . " - " . APP_NAME;
$error = '';

/**
 * Procesa la subida de una foto local o la descarga de una foto libre sugerida por la IA
 */
function vademecumHandlePhotoUpload($fieldName, $existingPath, $clearFlag, $iaRemoteUrl, &$errorOut) {
    if ($clearFlag) {
        $existingPath = null;
    }

    // 1. Archivo subido desde galería, carpeta o cámara
    if (isset($_FILES[$fieldName]) && isset($_FILES[$fieldName]['error']) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK && !empty($_FILES[$fieldName]['tmp_name'])) {
        $origName = $_FILES[$fieldName]['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = array('jpg', 'jpeg', 'png', 'webp');
        if (!in_array($ext, $allowed)) {
            $errorOut = "Formato de imagen no válido en '{$origName}'. Solo se admiten JPG, PNG y WEBP.";
            return $existingPath;
        }
        $destDir = PUBLIC_PATH . '/uploads/vademecum';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        $rand = mt_rand(1000, 9999);
        $uniqueName = 'vad_' . $fieldName . '_' . time() . '_' . $rand . '.' . $ext;
        $destFile = $destDir . '/' . $uniqueName;
        if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $destFile)) {
            return 'uploads/vademecum/' . $uniqueName;
        } else {
            $errorOut = "No se pudo guardar la imagen '{$origName}' en el servidor.";
        }
        return $existingPath;
    }

    // 2. Foto libre seleccionada desde sugerencias de Wikimedia Commons
    if (!empty($iaRemoteUrl) && preg_match('#^https://upload\.wikimedia\.org/#i', $iaRemoteUrl)) {
        $ch = curl_init($iaRemoteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AgroDietarioBot/2.0 (vademecum)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $imgData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        curl_close($ch);

        if ($httpCode === 200 && $imgData && strlen($imgData) > 500 && strlen($imgData) < 8 * 1024 * 1024) {
            $ext = 'jpg';
            if (strpos($contentType, 'png') !== false) $ext = 'png';
            elseif (strpos($contentType, 'webp') !== false) $ext = 'webp';

            $destDir = PUBLIC_PATH . '/uploads/vademecum';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            $rand = mt_rand(1000, 9999);
            $uniqueName = 'vad_ia_' . $fieldName . '_' . time() . '_' . $rand . '.' . $ext;
            $destFile = $destDir . '/' . $uniqueName;
            if (@file_put_contents($destFile, $imgData) !== false) {
                return 'uploads/vademecum/' . $uniqueName;
            }
        }
    }

    return $existingPath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipoRegistro = isset($_POST['tipo_registro']) ? $_POST['tipo_registro'] : 'semilla';
    if (!in_array($tipoRegistro, array('semilla', 'planta', 'ambos'))) {
        $tipoRegistro = 'semilla';
    }

    $nombreComun = trim(isset($_POST['nombre_comun']) ? $_POST['nombre_comun'] : '');
    $nombresVulgares = trim(isset($_POST['nombres_vulgares']) ? $_POST['nombres_vulgares'] : '');
    $nombreCientifico = trim(isset($_POST['nombre_cientifico']) ? $_POST['nombre_cientifico'] : '');
    $variedad = trim(isset($_POST['variedad']) ? $_POST['variedad'] : '');

    $taxReino = trim(isset($_POST['tax_reino']) ? $_POST['tax_reino'] : 'Plantae');
    $taxDivision = trim(isset($_POST['tax_division']) ? $_POST['tax_division'] : '');
    $taxClase = trim(isset($_POST['tax_clase']) ? $_POST['tax_clase'] : '');
    $taxOrden = trim(isset($_POST['tax_orden']) ? $_POST['tax_orden'] : '');
    $taxFamilia = trim(isset($_POST['tax_familia']) ? $_POST['tax_familia'] : '');
    $taxGenero = trim(isset($_POST['tax_genero']) ? $_POST['tax_genero'] : '');
    $taxEspecie = trim(isset($_POST['tax_especie']) ? $_POST['tax_especie'] : '');
    $taxSubespecie = trim(isset($_POST['tax_subespecie']) ? $_POST['tax_subespecie'] : '');

    $esTransgenico = (!empty($_POST['es_transgenico']) && $_POST['es_transgenico'] == '1') ? 1 : 0;
    $detalleTransgenico = trim(isset($_POST['detalle_transgenico']) ? $_POST['detalle_transgenico'] : '');
    $origenSemilla = trim(isset($_POST['origen_semilla']) ? $_POST['origen_semilla'] : '');
    $caracSemilla = trim(isset($_POST['caracteristicas_semilla']) ? $_POST['caracteristicas_semilla'] : '');
    $caracPlanta = trim(isset($_POST['caracteristicas_planta']) ? $_POST['caracteristicas_planta'] : '');

    $fechaSiembra = trim(isset($_POST['fecha_siembra']) ? $_POST['fecha_siembra'] : '');
    $fechaCrecimiento = trim(isset($_POST['fecha_crecimiento']) ? $_POST['fecha_crecimiento'] : '');
    $fechaRecoleccion = trim(isset($_POST['fecha_recoleccion']) ? $_POST['fecha_recoleccion'] : '');

    $estadoTierra = trim(isset($_POST['estado_tierra']) ? $_POST['estado_tierra'] : '');
    $abonoEco = (!empty($_POST['abono_es_ecologico']) && $_POST['abono_es_ecologico'] == '1') ? 1 : 0;
    $tipoAbono = trim(isset($_POST['tipo_abono']) ? $_POST['tipo_abono'] : '');

    $enfermedadesPlagas = trim(isset($_POST['enfermedades_plagas']) ? $_POST['enfermedades_plagas'] : '');
    $tratEco = (!empty($_POST['tratamiento_es_ecologico']) && $_POST['tratamiento_es_ecologico'] == '1') ? 1 : 0;
    $tratEnfermedades = trim(isset($_POST['tratamiento_enfermedades']) ? $_POST['tratamiento_enfermedades'] : '');

    $notas = trim(isset($_POST['notas']) ? $_POST['notas'] : '');
    $fuenteIa = trim(isset($_POST['fuente_ia']) ? $_POST['fuente_ia'] : '');
    $urlRef = trim(isset($_POST['url_referencia']) ? $_POST['url_referencia'] : '');

    $fotoPlanta = vademecumHandlePhotoUpload(
        'foto_planta_completa',
        $isEdit ? $registro['foto_planta_completa'] : null,
        !empty($_POST['limpiar_foto_planta_completa']),
        isset($_POST['ia_foto_url_foto_planta_completa']) ? trim($_POST['ia_foto_url_foto_planta_completa']) : '',
        $error
    );
    $fotoTallo = vademecumHandlePhotoUpload(
        'foto_tallo_hojas',
        $isEdit ? $registro['foto_tallo_hojas'] : null,
        !empty($_POST['limpiar_foto_tallo_hojas']),
        isset($_POST['ia_foto_url_foto_tallo_hojas']) ? trim($_POST['ia_foto_url_foto_tallo_hojas']) : '',
        $error
    );
    $fotoFlor = vademecumHandlePhotoUpload(
        'foto_flor',
        $isEdit ? $registro['foto_flor'] : null,
        !empty($_POST['limpiar_foto_flor']),
        isset($_POST['ia_foto_url_foto_flor']) ? trim($_POST['ia_foto_url_foto_flor']) : '',
        $error
    );
    $fotoSemilla = vademecumHandlePhotoUpload(
        'foto_semilla',
        ($isEdit && isset($registro['foto_semilla'])) ? $registro['foto_semilla'] : null,
        !empty($_POST['limpiar_foto_semilla']),
        isset($_POST['ia_foto_url_foto_semilla']) ? trim($_POST['ia_foto_url_foto_semilla']) : '',
        $error
    );
    $fotoLibre = vademecumHandlePhotoUpload(
        'foto_libre',
        ($isEdit && isset($registro['foto_libre'])) ? $registro['foto_libre'] : null,
        !empty($_POST['limpiar_foto_libre']),
        isset($_POST['ia_foto_url_foto_libre']) ? trim($_POST['ia_foto_url_foto_libre']) : '',
        $error
    );

    if (empty($nombreComun)) {
        $error = 'El nombre común de la semilla o planta es obligatorio.';
    }

    if (empty($error)) {
        try {
            if ($isEdit) {
                $stmtUpd = $db->prepare("
                    UPDATE vademecum_semillas_plantas SET
                        tipo_registro = :tipo_reg,
                        nombre_comun = :comun,
                        nombres_vulgares = :vulgares,
                        nombre_cientifico = :cien,
                        variedad = :var,
                        tax_reino = :t_rei,
                        tax_division = :t_div,
                        tax_clase = :t_cla,
                        tax_orden = :t_ord,
                        tax_familia = :t_fam,
                        tax_genero = :t_gen,
                        tax_especie = :t_esp,
                        tax_subespecie = :t_sub,
                        es_transgenico = :transg,
                        detalle_transgenico = :det_transg,
                        origen_semilla = :origen,
                        caracteristicas_semilla = :c_sem,
                        caracteristicas_planta = :c_pla,
                        fecha_siembra = :f_siem,
                        fecha_crecimiento = :f_crec,
                        fecha_recoleccion = :f_reco,
                        estado_tierra = :tierra,
                        abono_es_ecologico = :ab_eco,
                        tipo_abono = :t_abono,
                        enfermedades_plagas = :enf,
                        tratamiento_es_ecologico = :tr_eco,
                        tratamiento_enfermedades = :trat,
                        foto_planta_completa = :f_planta,
                        foto_tallo_hojas = :f_tallo,
                        foto_flor = :f_flor,
                        foto_semilla = :f_semilla,
                        foto_libre = :f_libre,
                        notas = :notas,
                        fuente_ia = :fuente,
                        url_referencia = :url
                    WHERE id_vademecum = :id
                ");
                $stmtUpd->execute(array(
                    ':tipo_reg'   => $tipoRegistro,
                    ':comun'      => $nombreComun,
                    ':vulgares'   => $nombresVulgares ?: null,
                    ':cien'       => $nombreCientifico ?: null,
                    ':var'        => $variedad ?: null,
                    ':t_rei'      => $taxReino ?: 'Plantae',
                    ':t_div'      => $taxDivision ?: null,
                    ':t_cla'      => $taxClase ?: null,
                    ':t_ord'      => $taxOrden ?: null,
                    ':t_fam'      => $taxFamilia ?: null,
                    ':t_gen'      => $taxGenero ?: null,
                    ':t_esp'      => $taxEspecie ?: null,
                    ':t_sub'      => $taxSubespecie ?: null,
                    ':transg'     => $esTransgenico,
                    ':det_transg' => $detalleTransgenico ?: null,
                    ':origen'     => $origenSemilla ?: null,
                    ':c_sem'      => $caracSemilla ?: null,
                    ':c_pla'      => $caracPlanta ?: null,
                    ':f_siem'     => $fechaSiembra ?: null,
                    ':f_crec'     => $fechaCrecimiento ?: null,
                    ':f_reco'     => $fechaRecoleccion ?: null,
                    ':tierra'     => $estadoTierra ?: null,
                    ':ab_eco'     => $abonoEco,
                    ':t_abono'    => $tipoAbono ?: null,
                    ':enf'        => $enfermedadesPlagas ?: null,
                    ':tr_eco'     => $tratEco,
                    ':trat'       => $tratEnfermedades ?: null,
                    ':f_planta'   => $fotoPlanta,
                    ':f_tallo'    => $fotoTallo,
                    ':f_flor'     => $fotoFlor,
                    ':f_semilla'  => $fotoSemilla,
                    ':f_libre'    => $fotoLibre,
                    ':notas'      => $notas ?: null,
                    ':fuente'     => $fuenteIa ?: null,
                    ':url'        => $urlRef ?: null,
                    ':id'         => $idVademecum
                ));
            } else {
                $stmtIns = $db->prepare("
                    INSERT INTO vademecum_semillas_plantas (
                        id_propietario, id_usuario_creador, tipo_registro,
                        nombre_comun, nombres_vulgares, nombre_cientifico, variedad,
                        tax_reino, tax_division, tax_clase, tax_orden, tax_familia, tax_genero, tax_especie, tax_subespecie,
                        es_transgenico, detalle_transgenico, origen_semilla,
                        caracteristicas_semilla, caracteristicas_planta,
                        fecha_siembra, fecha_crecimiento, fecha_recoleccion,
                        estado_tierra, abono_es_ecologico, tipo_abono,
                        enfermedades_plagas, tratamiento_es_ecologico, tratamiento_enfermedades,
                        foto_planta_completa, foto_tallo_hojas, foto_flor, foto_semilla, foto_libre,
                        notas, fuente_ia, url_referencia, activo
                    ) VALUES (
                        :prop, :uid, :tipo_reg,
                        :comun, :vulgares, :cien, :var,
                        :t_rei, :t_div, :t_cla, :t_ord, :t_fam, :t_gen, :t_esp, :t_sub,
                        :transg, :det_transg, :origen,
                        :c_sem, :c_pla,
                        :f_siem, :f_crec, :f_reco,
                        :tierra, :ab_eco, :t_abono,
                        :enf, :tr_eco, :trat,
                        :f_planta, :f_tallo, :f_flor, :f_semilla, :f_libre,
                        :notas, :fuente, :url, 1
                    )
                ");
                $stmtIns->execute(array(
                    ':prop'       => $tenantId ?: null,
                    ':uid'        => $userId ?: null,
                    ':tipo_reg'   => $tipoRegistro,
                    ':comun'      => $nombreComun,
                    ':vulgares'   => $nombresVulgares ?: null,
                    ':cien'       => $nombreCientifico ?: null,
                    ':var'        => $variedad ?: null,
                    ':t_rei'      => $taxReino ?: 'Plantae',
                    ':t_div'      => $taxDivision ?: null,
                    ':t_cla'      => $taxClase ?: null,
                    ':t_ord'      => $taxOrden ?: null,
                    ':t_fam'      => $taxFamilia ?: null,
                    ':t_gen'      => $taxGenero ?: null,
                    ':t_esp'      => $taxEspecie ?: null,
                    ':t_sub'      => $taxSubespecie ?: null,
                    ':transg'     => $esTransgenico,
                    ':det_transg' => $detalleTransgenico ?: null,
                    ':origen'     => $origenSemilla ?: null,
                    ':c_sem'      => $caracSemilla ?: null,
                    ':c_pla'      => $caracPlanta ?: null,
                    ':f_siem'     => $fechaSiembra ?: null,
                    ':f_crec'     => $fechaCrecimiento ?: null,
                    ':f_reco'     => $fechaRecoleccion ?: null,
                    ':tierra'     => $estadoTierra ?: null,
                    ':ab_eco'     => $abonoEco,
                    ':t_abono'    => $tipoAbono ?: null,
                    ':enf'        => $enfermedadesPlagas ?: null,
                    ':tr_eco'     => $tratEco,
                    ':trat'       => $tratEnfermedades ?: null,
                    ':f_planta'   => $fotoPlanta,
                    ':f_tallo'    => $fotoTallo,
                    ':f_flor'     => $fotoFlor,
                    ':f_semilla'  => $fotoSemilla,
                    ':f_libre'    => $fotoLibre,
                    ':notas'      => $notas ?: null,
                    ':fuente'     => $fuenteIa ?: null,
                    ':url'        => $urlRef ?: null
                ));
            }

            if (!headers_sent()) {
                header("Location: " . APP_URL . "/vademecum?ok=1");
            } else {
                echo "<script>window.location.href='" . APP_URL . "/vademecum?ok=1';</script>";
            }
            exit;
        } catch (Exception $e) {
            $error = 'Error al guardar en el Vademécum: ' . $e->getMessage();
        }
    }
}

function vVal($key, $default = '') {
    global $registro;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (!empty($registro) && isset($registro[$key]) && $registro[$key] !== null) {
        return $registro[$key];
    }
    return $default;
}

require_once VIEWS_PATH . '/layouts/header.php';
?>

<style>
    .vad-photo-slot {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        background: #f8fafc;
        transition: all 0.2s ease;
        min-height: 220px;
        position: relative;
        overflow: hidden;
    }
    .vad-photo-slot:hover, .vad-photo-slot.vad-dragover {
        border-color: #0284c7;
        background: #f0f9ff;
    }
    .vad-photo-preview {
        width: 100%;
        height: 145px;
        object-fit: cover;
        border-radius: 8px;
    }
    .vad-ai-updated {
        animation: vadFlash 1.4s ease;
        border-color: #16a34a !important;
        box-shadow: 0 0 0 0.2rem rgba(22, 163, 74, 0.2) !important;
    }
    @keyframes vadFlash {
        0% { background-color: #dcfce7; }
        100% { background-color: #ffffff; }
    }
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-11">

            <!-- Cabecera -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #059669);">
                        <i class="fa-solid fa-book-medical fs-3 text-warning"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0 text-primary">
                            <?= $isEdit ? 'Editar Ficha del Vademécum' : 'Nueva Semilla / Planta en el Vademécum' ?>
                        </h2>
                        <p class="text-muted mb-0 small">
                            Taxonomía completa, nombres vulgares, hasta 5 fotos (galería, carpetas o cámara) e identificación IA con fotos libres
                        </p>
                    </div>
                </div>
                <a href="<?= APP_URL ?>/vademecum" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Volver al Vademécum
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" id="vademecumForm">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id_vademecum" id="id_vademecum" value="<?= (int)$idVademecum ?>">
                <?php endif; ?>
                <input type="hidden" name="fuente_ia" id="fuente_ia" value="<?= htmlspecialchars(vVal('fuente_ia')) ?>">

                <!-- PANEL ASISTENTE IA MULTIMODAL -->
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #091e3a 0%, #1e3a8a 55%, #065f46 100%);">
                    <div class="card-body p-4 text-white">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark fw-bold px-3 py-1">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>ASISTENTE IA BOTÁNICO Y VISUAL
                                </span>
                                <span class="small text-light opacity-75">Búsqueda por datos, fotos libres y hasta 5 fotografías</span>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="aiOverwrite" checked>
                                <label class="form-check-label small text-light" for="aiOverwrite">
                                    Actualizar campos existentes al re-analizar
                                </label>
                            </div>
                        </div>

                        <p class="small text-light opacity-90 mb-3">
                            Busca por nombre/especie o sube fotos desde tu <strong>galería, carpetas o cámara</strong> y pulsa el botón. La IA también localizará <strong>fotografías de licencia libre (CC0 / Dominio Público)</strong> de la planta y semilla para que puedas incorporarlas con un clic.
                        </p>

                        <div class="row g-2 align-items-center">
                            <div class="col-md-8">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-white border-0 text-success">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </span>
                                    <input type="text" id="aiQuery" class="form-control border-0 fw-semibold"
                                           placeholder="Nombre común, vulgar, científico, variedad o pista..."
                                           value="<?= htmlspecialchars(vVal('nombre_cientifico') ?: vVal('nombre_comun')) ?>">
                                </div>
                            </div>
                            <div class="col-md-4 d-grid">
                                <button type="button" id="btnAnalizarIa" class="btn btn-warning btn-lg fw-bold text-dark shadow-sm">
                                    <i class="fa-solid fa-microscope me-2"></i>
                                    <?= $isEdit ? 'Re-analizar con IA (Datos + Fotos)' : 'Buscar / Identificar con IA' ?>
                                </button>
                            </div>
                        </div>

                        <div id="aiStatusBox" class="mt-3 d-none"></div>
                        <div id="aiPhotosBox" class="mt-3 d-none"></div>
                    </div>
                </div>

                <!-- SECCIÓN 1: LAS 5 FOTOGRAFÍAS DE IDENTIFICACIÓN Y SEGUIMIENTO -->
                <div class="card card-custom mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-camera-retro me-2 text-info"></i>1. Fotografías (Galería, Carpetas o Cámara · Opcionales e Incrementales)</h5>
                        <small class="text-muted">No es obligatorio subir todas a la vez. Puedes arrastrar imágenes, elegir de galería/carpetas o hacer la foto directamente con la cámara.</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <?php
                            $photoSlots = array(
                                array(
                                    'field' => 'foto_planta_completa',
                                    'clear' => 'limpiar_foto_planta_completa',
                                    'title' => '1. Planta Completa',
                                    'hint'  => 'Porte general y estructura',
                                    'icon'  => 'fa-tree'
                                ),
                                array(
                                    'field' => 'foto_tallo_hojas',
                                    'clear' => 'limpiar_foto_tallo_hojas',
                                    'title' => '2. Tallo y Hojas',
                                    'hint'  => 'Nervios, pecíolo y tallo',
                                    'icon'  => 'fa-leaf'
                                ),
                                array(
                                    'field' => 'foto_flor',
                                    'clear' => 'limpiar_foto_flor',
                                    'title' => '3. Flor / Inflorescencia',
                                    'hint'  => 'Pétalos, sépalos y estambres',
                                    'icon'  => 'fa-fan'
                                ),
                                array(
                                    'field' => 'foto_semilla',
                                    'clear' => 'limpiar_foto_semilla',
                                    'title' => '4. Simiente / Semilla',
                                    'hint'  => 'Detalle de la semilla o grano',
                                    'icon'  => 'fa-seedling'
                                ),
                                array(
                                    'field' => 'foto_libre',
                                    'clear' => 'limpiar_foto_libre',
                                    'title' => '5. Foto Libre (Usuario)',
                                    'hint'  => 'Fruto, raíz, plaga o detalle',
                                    'icon'  => 'fa-image'
                                )
                            );
                            foreach ($photoSlots as $slot):
                                $fName = $slot['field'];
                                $cName = $slot['clear'];
                                $savedRel = ($isEdit && !empty($registro[$fName])) ? $registro[$fName] : '';
                                $savedUrl = !empty($savedRel) ? (APP_URL . '/' . ltrim($savedRel, '/')) : '';
                            ?>
                            <div class="col-md-4 col-lg">
                                <div class="vad-photo-slot p-3 d-flex flex-column justify-content-between" data-drop-field="<?= $fName ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-dark small"><i class="fa-solid <?= $slot['icon'] ?> me-1 text-success"></i><?= $slot['title'] ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= $slot['hint'] ?></div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-clear-photo <?= empty($savedUrl) ? 'd-none' : '' ?>"
                                                data-field="<?= $fName ?>" title="Quitar foto">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>

                                    <div class="text-center my-2">
                                        <img src="<?= htmlspecialchars($savedUrl) ?>"
                                             id="preview_<?= $fName ?>"
                                             class="vad-photo-preview <?= empty($savedUrl) ? 'd-none' : '' ?>"
                                             alt="<?= htmlspecialchars($slot['title']) ?>">
                                        <div id="placeholder_<?= $fName ?>" class="py-3 text-muted <?= !empty($savedUrl) ? 'd-none' : '' ?>">
                                            <i class="fa-solid fa-cloud-arrow-up fs-3 text-secondary opacity-50"></i>
                                            <div style="font-size: 0.75rem;" class="mt-1">Arrastra o elige imagen</div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-1">
                                        <label for="<?= $fName ?>" class="btn btn-sm btn-outline-primary flex-grow-1 mb-0" style="font-size: 0.75rem;" title="Elegir de galería o carpeta">
                                            <i class="fa-solid fa-folder-open me-1"></i>Galería / Archivo
                                        </label>
                                        <label for="cam_<?= $fName ?>" class="btn btn-sm btn-outline-success mb-0" style="font-size: 0.75rem;" title="Hacer foto con la cámara">
                                            <i class="fa-solid fa-camera"></i>
                                        </label>
                                    </div>

                                    <!-- Input principal (galería/carpetas/archivos) -->
                                    <input type="file" name="<?= $fName ?>" id="<?= $fName ?>"
                                           class="d-none vad-photo-input"
                                           accept="image/*"
                                           data-field="<?= $fName ?>">
                                    <!-- Input auxiliar cámara directa -->
                                    <input type="file" id="cam_<?= $fName ?>"
                                           class="d-none vad-camera-input"
                                           accept="image/*" capture="environment"
                                           data-field="<?= $fName ?>">

                                    <input type="hidden" name="<?= $cName ?>" id="<?= $cName ?>" value="0">
                                    <input type="hidden" name="ia_foto_url_<?= $fName ?>" id="ia_foto_url_<?= $fName ?>" value="">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: IDENTIFICACIÓN GENERAL Y TAXONOMÍA -->
                <div class="card card-custom mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-dna me-2 text-primary"></i>2. Identificación, Nombres Vulgares y Taxonomía Botánica</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">Tipo de Registro</label>
                                <?php $tipoVal = vVal('tipo_registro', 'semilla'); ?>
                                <select name="tipo_registro" id="tipo_registro" class="form-select">
                                    <option value="semilla" <?= $tipoVal === 'semilla' ? 'selected' : '' ?>>Semilla</option>
                                    <option value="planta" <?= $tipoVal === 'planta' ? 'selected' : '' ?>>Planta</option>
                                    <option value="ambos" <?= $tipoVal === 'ambos' ? 'selected' : '' ?>>Semilla y Planta</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">Nombre Común <span class="text-danger">*</span></label>
                                <input type="text" name="nombre_comun" id="nombre_comun" class="form-control fw-bold" required
                                       placeholder="Ej: Tomate" value="<?= htmlspecialchars(vVal('nombre_comun')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">Nombre Científico</label>
                                <input type="text" name="nombre_cientifico" id="nombre_cientifico" class="form-control fst-italic"
                                       placeholder="Ej: Solanum lycopersicum L." value="<?= htmlspecialchars(vVal('nombre_cientifico')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">Variedad / Cultivar</label>
                                <input type="text" name="variedad" id="variedad" class="form-control"
                                       placeholder="Ej: Muchamiel" value="<?= htmlspecialchars(vVal('variedad')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Nombres Vulgares más usados (separados por comas)</label>
                                <input type="text" name="nombres_vulgares" id="nombres_vulgares" class="form-control"
                                       placeholder="Ej: Jitomate, Tomatera, Tomatillo, Pera de huerta..." value="<?= htmlspecialchars(vVal('nombres_vulgares')) ?>">
                            </div>
                        </div>

                        <hr class="my-3">
                        <div class="small fw-bold text-uppercase text-muted mb-2">Clasificación Taxonómica</div>
                        <div class="row g-2">
                            <div class="col-md-3 col-6">
                                <label class="form-label small text-muted mb-1">Reino</label>
                                <input type="text" name="tax_reino" id="tax_reino" class="form-control form-control-sm"
                                       placeholder="Plantae" value="<?= htmlspecialchars(vVal('tax_reino', 'Plantae')) ?>">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label small text-muted mb-1">División / Filo</label>
                                <input type="text" name="tax_division" id="tax_division" class="form-control form-control-sm"
                                       placeholder="Magnoliophyta" value="<?= htmlspecialchars(vVal('tax_division')) ?>">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label small text-muted mb-1">Clase</label>
                                <input type="text" name="tax_clase" id="tax_clase" class="form-control form-control-sm"
                                       placeholder="Magnoliopsida" value="<?= htmlspecialchars(vVal('tax_clase')) ?>">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label small text-muted mb-1">Orden</label>
                                <input type="text" name="tax_orden" id="tax_orden" class="form-control form-control-sm"
                                       placeholder="Solanales" value="<?= htmlspecialchars(vVal('tax_orden')) ?>">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label small text-muted mb-1">Familia</label>
                                <input type="text" name="tax_familia" id="tax_familia" class="form-control form-control-sm"
                                       placeholder="Solanaceae" value="<?= htmlspecialchars(vVal('tax_familia')) ?>">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label small text-muted mb-1">Género</label>
                                <input type="text" name="tax_genero" id="tax_genero" class="form-control form-control-sm fst-italic"
                                       placeholder="Solanum" value="<?= htmlspecialchars(vVal('tax_genero')) ?>">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label small text-muted mb-1">Especie</label>
                                <input type="text" name="tax_especie" id="tax_especie" class="form-control form-control-sm fst-italic"
                                       placeholder="Solanum lycopersicum" value="<?= htmlspecialchars(vVal('tax_especie')) ?>">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label small text-muted mb-1">Subespecie / Var. Botánica</label>
                                <input type="text" name="tax_subespecie" id="tax_subespecie" class="form-control form-control-sm fst-italic"
                                       placeholder="Opcional" value="<?= htmlspecialchars(vVal('tax_subespecie')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: GENÉTICA (TRANSGÉNICO) Y MORFOLOGÍA DE SEMILLA Y PLANTA -->
                <div class="card card-custom mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-seedling me-2 text-success"></i>3. Genética, Transgénicos y Características de Semilla / Planta</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">¿Es Transgénico (OMG)?</label>
                                <?php $transgVal = (string)vVal('es_transgenico', '0'); ?>
                                <select name="es_transgenico" id="es_transgenico" class="form-select">
                                    <option value="0" <?= $transgVal === '0' ? 'selected' : '' ?>>No (Tradicional / Convencional / Natural)</option>
                                    <option value="1" <?= $transgVal === '1' ? 'selected' : '' ?>>Sí (Organismo Modificado Genéticamente)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Origen de la Semilla / Planta</label>
                                <input type="text" name="origen_semilla" id="origen_semilla" class="form-control"
                                       placeholder="Ej: Semilla propia, Autóctona, Híbrida F1, Ecológica..." value="<?= htmlspecialchars(vVal('origen_semilla')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Detalle Genético / Evento Transgénico</label>
                                <input type="text" name="detalle_transgenico" id="detalle_transgenico" class="form-control"
                                       placeholder="Ej: Variedad tradicional sin modificación, o evento OMG..." value="<?= htmlspecialchars(vVal('detalle_transgenico')) ?>">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Características de la Semilla</label>
                                <textarea name="caracteristicas_semilla" id="caracteristicas_semilla" rows="3" class="form-control"
                                          placeholder="Tamaño, color, % y temperatura de germinación, profundidad de siembra, conservación..."><?= htmlspecialchars(vVal('caracteristicas_semilla')) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Características de la Planta</label>
                                <textarea name="caracteristicas_planta" id="caracteristicas_planta" rows="3" class="form-control"
                                          placeholder="Porte, altura, raíz, tallo, hojas, floración, fruto..."><?= htmlspecialchars(vVal('caracteristicas_planta')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 4: FECHAS DE SIEMBRA, CRECIMIENTO Y RECOLECCIÓN -->
                <div class="card card-custom mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-calendar-days me-2 text-warning"></i>4. Fechas de Siembra, Crecimiento y Recolección</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small"><i class="fa-solid fa-seedling me-1 text-success"></i>Fecha / Época de Siembra</label>
                                <input type="text" name="fecha_siembra" id="fecha_siembra" class="form-control"
                                       placeholder="Ej: Febrero - Marzo (o fecha exacta)" value="<?= htmlspecialchars(vVal('fecha_siembra')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small"><i class="fa-solid fa-arrow-up-right-dots me-1 text-info"></i>Fecha / Fase de Crecimiento</label>
                                <input type="text" name="fecha_crecimiento" id="fecha_crecimiento" class="form-control"
                                       placeholder="Ej: Abril - Junio (60-90 días desarrollo)" value="<?= htmlspecialchars(vVal('fecha_crecimiento')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small"><i class="fa-solid fa-basket-shopping me-1 text-warning"></i>Fecha / Época de Recolección</label>
                                <input type="text" name="fecha_recoleccion" id="fecha_recoleccion" class="form-control"
                                       placeholder="Ej: Julio - Octubre" value="<?= htmlspecialchars(vVal('fecha_recoleccion')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 5: ESTADO DE LA TIERRA Y TIPO DE ABONO -->
                <div class="card card-custom mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-mountain-sun me-2 text-secondary"></i>5. Estado de la Tierra y Abonado</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Estado y Requerimientos de la Tierra</label>
                                <textarea name="estado_tierra" id="estado_tierra" rows="3" class="form-control"
                                          placeholder="Textura, pH ideal, drenaje, humedad, laboreo previo..."><?= htmlspecialchars(vVal('estado_tierra')) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold small mb-0">Tipo de Abono</label>
                                    <?php $abonoEcoVal = (string)vVal('abono_es_ecologico', '1'); ?>
                                    <select name="abono_es_ecologico" id="abono_es_ecologico" class="form-select form-select-sm w-auto fw-semibold">
                                        <option value="1" <?= $abonoEcoVal === '1' ? 'selected' : '' ?>>Abono Ecológico</option>
                                        <option value="0" <?= $abonoEcoVal === '0' ? 'selected' : '' ?>>Abono No Ecológico (Convencional)</option>
                                    </select>
                                </div>
                                <textarea name="tipo_abono" id="tipo_abono" rows="3" class="form-control"
                                          placeholder="Compost, humus, estiércol, o fórmula NPK, dosis y momentos..."><?= htmlspecialchars(vVal('tipo_abono')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 6: ENFERMEDADES Y TRATAMIENTOS -->
                <div class="card card-custom mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-shield-virus me-2 text-danger"></i>6. Enfermedades y Tratamientos</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Tipos de Enfermedades y Plagas</label>
                                <textarea name="enfermedades_plagas" id="enfermedades_plagas" rows="4" class="form-control"
                                          placeholder="Hongos (mildiu, oídio), bacterias, virus, pulgones, ácaros, orugas..."><?= htmlspecialchars(vVal('enfermedades_plagas')) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold small mb-0">Tratamiento Recomendado</label>
                                    <?php $tratEcoVal = (string)vVal('tratamiento_es_ecologico', '1'); ?>
                                    <select name="tratamiento_es_ecologico" id="tratamiento_es_ecologico" class="form-select form-select-sm w-auto fw-semibold">
                                        <option value="1" <?= $tratEcoVal === '1' ? 'selected' : '' ?>>Tratamiento Ecológico</option>
                                        <option value="0" <?= $tratEcoVal === '0' ? 'selected' : '' ?>>Tratamiento No Ecológico (Convencional)</option>
                                    </select>
                                </div>
                                <textarea name="tratamiento_enfermedades" id="tratamiento_enfermedades" rows="4" class="form-control"
                                          placeholder="Remedios ecológicos (cobre, azufre, jabón potásico, neem, Bacillus...) o fitosanitarios autorizados..."><?= htmlspecialchars(vVal('tratamiento_enfermedades')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 7: NOTAS Y REFERENCIA -->
                <div class="card card-custom mb-4">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold small">Notas Personales / Observaciones de Campo</label>
                                <textarea name="notas" id="notas" rows="2" class="form-control"
                                          placeholder="Observaciones propias, lote de semilla, lugar de compra o recolección..."><?= htmlspecialchars(vVal('notas')) ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">URL de Referencia Botánica</label>
                                <input type="url" name="url_referencia" id="url_referencia" class="form-control"
                                       placeholder="https://..." value="<?= htmlspecialchars(vVal('url_referencia')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTONERA FINAL -->
                <div class="d-flex justify-content-end gap-2 mb-5">
                    <a href="<?= APP_URL ?>/vademecum" class="btn btn-light border px-4">Cancelar</a>
                    <button type="submit" class="btn btn-ag px-5 py-2 fw-bold">
                        <i class="fa-solid fa-floppy-disk me-2"></i>
                        <?= $isEdit ? 'Guardar Cambios en Vademécum' : 'Guardar en Vademécum' ?>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function showSlotPreview(field, srcUrl) {
        var preview = document.getElementById('preview_' + field);
        var placeholder = document.getElementById('placeholder_' + field);
        var clearInput = document.getElementById('limpiar_' + field);
        var clearBtn = document.querySelector('.btn-clear-photo[data-field="' + field + '"]');

        if (preview) {
            preview.src = srcUrl;
            preview.classList.remove('d-none');
        }
        if (placeholder) placeholder.classList.add('d-none');
        if (clearBtn) clearBtn.classList.remove('d-none');
        if (clearInput) clearInput.value = '0';
    }

    function assignFileToMainInput(field, file) {
        var mainInput = document.getElementById(field);
        var iaUrlInput = document.getElementById('ia_foto_url_' + field);
        if (iaUrlInput) iaUrlInput.value = '';

        if (mainInput && window.DataTransfer) {
            var dt = new DataTransfer();
            dt.items.add(file);
            mainInput.files = dt.files;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            showSlotPreview(field, e.target.result);
        };
        reader.readAsDataURL(file);
    }

    // 1. Selección desde galería / carpeta / archivos
    document.querySelectorAll('.vad-photo-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var field = this.getAttribute('data-field');
            var iaUrlInput = document.getElementById('ia_foto_url_' + field);
            if (iaUrlInput) iaUrlInput.value = '';
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    showSlotPreview(field, e.target.result);
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // 2. Captura desde cámara directa
    document.querySelectorAll('.vad-camera-input').forEach(function(camInput) {
        camInput.addEventListener('change', function() {
            var field = this.getAttribute('data-field');
            if (this.files && this.files[0]) {
                assignFileToMainInput(field, this.files[0]);
            }
        });
    });

    // 3. Drag & Drop sobre los 5 recuadros
    document.querySelectorAll('.vad-photo-slot').forEach(function(slot) {
        var field = slot.getAttribute('data-drop-field');
        slot.addEventListener('dragover', function(e) {
            e.preventDefault();
            slot.classList.add('vad-dragover');
        });
        slot.addEventListener('dragleave', function() {
            slot.classList.remove('vad-dragover');
        });
        slot.addEventListener('drop', function(e) {
            e.preventDefault();
            slot.classList.remove('vad-dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                var f = e.dataTransfer.files[0];
                if (f.type && f.type.indexOf('image/') === 0) {
                    assignFileToMainInput(field, f);
                }
            }
        });
    });

    // 4. Botón limpiar foto
    document.querySelectorAll('.btn-clear-photo').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var field = this.getAttribute('data-field');
            var input = document.getElementById(field);
            var camInput = document.getElementById('cam_' + field);
            var preview = document.getElementById('preview_' + field);
            var placeholder = document.getElementById('placeholder_' + field);
            var clearInput = document.getElementById('limpiar_' + field);
            var iaUrlInput = document.getElementById('ia_foto_url_' + field);

            if (input) input.value = '';
            if (camInput) camInput.value = '';
            if (iaUrlInput) iaUrlInput.value = '';
            if (preview) {
                preview.src = '';
                preview.classList.add('d-none');
            }
            if (placeholder) placeholder.classList.remove('d-none');
            if (clearInput) clearInput.value = '1';
            this.classList.add('d-none');
        });
    });

    // 5. Consulta Multimodal IA + Fotos Libres (Wikimedia Commons)
    var btnIa = document.getElementById('btnAnalizarIa');
    var statusBox = document.getElementById('aiStatusBox');
    var photosBox = document.getElementById('aiPhotosBox');

    function setFieldSmart(id, value, overwrite) {
        var el = document.getElementById(id);
        if (!el || value === undefined || value === null || value === '') return;
        var current = (el.value || '').trim();
        if (overwrite || current === '') {
            el.value = value;
            el.classList.remove('vad-ai-updated');
            void el.offsetWidth;
            el.classList.add('vad-ai-updated');
        }
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    window.asignarFotoLibreIa = function(field, url) {
        var input = document.getElementById(field);
        var iaUrlInput = document.getElementById('ia_foto_url_' + field);
        if (input) input.value = '';
        if (iaUrlInput) iaUrlInput.value = url;
        showSlotPreview(field, url);
    };

    if (btnIa) {
        btnIa.addEventListener('click', function() {
            var form = document.getElementById('vademecumForm');
            var formData = new FormData(form);
            formData.append('query', document.getElementById('aiQuery').value.trim());

            btnIa.disabled = true;
            var originalHtml = btnIa.innerHTML;
            btnIa.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analizando con IA...';

            statusBox.className = 'mt-3 alert alert-info border-0 mb-0 py-2 small';
            statusBox.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i>Consultando taxonomía, analizando fotografías y buscando imágenes de licencia libre...';
            statusBox.classList.remove('d-none');
            photosBox.classList.add('d-none');
            photosBox.innerHTML = '';

            fetch(window.APP_URL + '/api/vademecum/ia', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(resp) {
                if (!resp.success || !resp.data) {
                    statusBox.className = 'mt-3 alert alert-warning border-0 mb-0 py-2 small text-dark';
                    statusBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-2"></i>' + (resp.message || 'No se pudo completar la identificación.');
                    return;
                }

                var d = resp.data;
                var overwrite = document.getElementById('aiOverwrite').checked;

                setFieldSmart('nombre_comun', d.nombre_comun, overwrite);
                setFieldSmart('nombres_vulgares', d.nombres_vulgares, overwrite);
                setFieldSmart('nombre_cientifico', d.nombre_cientifico, overwrite);
                setFieldSmart('variedad', d.variedad, overwrite);

                setFieldSmart('tax_reino', d.tax_reino, overwrite);
                setFieldSmart('tax_division', d.tax_division, overwrite);
                setFieldSmart('tax_clase', d.tax_clase, overwrite);
                setFieldSmart('tax_orden', d.tax_orden, overwrite);
                setFieldSmart('tax_familia', d.tax_familia, overwrite);
                setFieldSmart('tax_genero', d.tax_genero, overwrite);
                setFieldSmart('tax_especie', d.tax_especie, overwrite);
                setFieldSmart('tax_subespecie', d.tax_subespecie, overwrite);

                if (d.es_transgenico !== undefined && (overwrite || document.getElementById('es_transgenico').value === '0')) {
                    document.getElementById('es_transgenico').value = String(d.es_transgenico ? 1 : 0);
                }
                setFieldSmart('detalle_transgenico', d.detalle_transgenico, overwrite);
                setFieldSmart('origen_semilla', d.origen_semilla, overwrite);
                setFieldSmart('caracteristicas_semilla', d.caracteristicas_semilla, overwrite);
                setFieldSmart('caracteristicas_planta', d.caracteristicas_planta, overwrite);

                setFieldSmart('fecha_siembra', d.fecha_siembra, overwrite);
                setFieldSmart('fecha_crecimiento', d.fecha_crecimiento, overwrite);
                setFieldSmart('fecha_recoleccion', d.fecha_recoleccion, overwrite);

                setFieldSmart('estado_tierra', d.estado_tierra, overwrite);
                setFieldSmart('tipo_abono', d.tipo_abono, overwrite);
                setFieldSmart('enfermedades_plagas', d.enfermedades_plagas, overwrite);
                setFieldSmart('tratamiento_enfermedades', d.tratamiento_enfermedades, overwrite);
                setFieldSmart('url_referencia', d.url_referencia, overwrite);

                if (d.fuente_ia) {
                    document.getElementById('fuente_ia').value = d.fuente_ia;
                }

                var msgOk = '<strong><i class="fa-solid fa-circle-check me-1"></i>Ficha actualizada</strong> (' + escHtml(d.fuente_ia || 'IA') + ').';
                if (d.identificacion_confianza) {
                    msgOk += ' Confianza: <span class="badge bg-dark text-uppercase">' + escHtml(d.identificacion_confianza) + '</span>.';
                }
                if (d.identificacion_notas) {
                    msgOk += '<div class="mt-1">' + escHtml(d.identificacion_notas) + '</div>';
                }

                statusBox.className = 'mt-3 alert alert-success border-0 mb-0 py-2 small text-dark';
                statusBox.innerHTML = msgOk;

                // Mostrar fotos libres encontradas (Wikimedia Commons / Dominio Público)
                if (d.fotos_sugeridas && d.fotos_sugeridas.length > 0) {
                    var pHtml = '<div class="bg-white text-dark rounded-3 p-3">';
                    pHtml += '<div class="fw-bold small text-primary mb-2"><i class="fa-solid fa-images me-1"></i>Fotografías de Licencia Libre localizadas (Planta / Semilla) — Pulsa para asignar:</div>';
                    pHtml += '<div class="row g-2">';
                    d.fotos_sugeridas.forEach(function(foto) {
                        var safeUrl = escHtml(foto.url);
                        pHtml += '<div class="col-md-3 col-6"><div class="border rounded p-2 h-100 d-flex flex-column justify-content-between">';
                        pHtml += '<img src="' + safeUrl + '" class="img-fluid rounded mb-1" style="height:95px; width:100%; object-fit:cover;">';
                        pHtml += '<div class="mb-1"><span class="badge bg-success" style="font-size:0.65rem;">' + escHtml(foto.licencia) + '</span> <span class="badge bg-secondary" style="font-size:0.65rem;">' + escHtml(foto.tipo) + '</span></div>';
                        pHtml += '<div class="d-flex flex-column gap-1">';
                        pHtml += '<button type="button" class="btn btn-outline-primary btn-sm py-0" style="font-size:0.72rem;" onclick="asignarFotoLibreIa(\'foto_planta_completa\', \'' + safeUrl + '\')">Usar en 1. Planta</button>';
                        pHtml += '<button type="button" class="btn btn-outline-success btn-sm py-0" style="font-size:0.72rem;" onclick="asignarFotoLibreIa(\'foto_semilla\', \'' + safeUrl + '\')">Usar en 4. Semilla</button>';
                        pHtml += '<button type="button" class="btn btn-outline-secondary btn-sm py-0" style="font-size:0.72rem;" onclick="asignarFotoLibreIa(\'foto_libre\', \'' + safeUrl + '\')">Usar en 5. Libre</button>';
                        pHtml += '</div></div></div>';
                    });
                    pHtml += '</div></div>';
                    photosBox.innerHTML = pHtml;
                    photosBox.classList.remove('d-none');
                }
            })
            .catch(function() {
                statusBox.className = 'mt-3 alert alert-danger border-0 mb-0 py-2 small';
                statusBox.innerHTML = '<i class="fa-solid fa-circle-exclamation me-2"></i>Error de conexión al consultar la IA.';
            })
            .finally(function() {
                btnIa.disabled = false;
                btnIa.innerHTML = originalHtml;
            });
        });
    }
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
