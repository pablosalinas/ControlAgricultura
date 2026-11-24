<?php
/**
 * Controlador API Multimodal para el Vademécum de Semillas y Plantas
 * Acepta búsqueda por texto y/o 1 a 3 fotografías (planta completa, tallo y hojas, flor),
 * combinando archivos recién seleccionados con fotos ya guardadas en el registro.
 *
 * Compatible con PHP 5.6, 7.x, 8.x
 */

header('Content-Type: application/json; charset=utf-8');

if (!class_exists('Auth') || !Auth::check()) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'No autorizado. Debe iniciar sesión en el portal.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Método no permitido. Use POST.'));
    exit;
}

require_once SRC_PATH . '/Services/VademecumAiService.php';
require_once SRC_PATH . '/Services/VademecumAccess.php';

$db = Database::getConnection();
$idVademecum = isset($_POST['id_vademecum']) ? (int)$_POST['id_vademecum'] : 0;
$existingRecord = null;

if ($idVademecum > 0) {
    $tenantId = Auth::tenantId();
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $existingRecord = VademecumAccess::findEditableById($db, $idVademecum, $tenantId, $userId);
}

$photoMap = array(
    'planta_completa' => array('field' => 'foto_planta_completa', 'clear' => 'limpiar_foto_planta_completa'),
    'tallo_hojas'     => array('field' => 'foto_tallo_hojas', 'clear' => 'limpiar_foto_tallo_hojas'),
    'flor'            => array('field' => 'foto_flor', 'clear' => 'limpiar_foto_flor'),
    'semilla'         => array('field' => 'foto_semilla', 'clear' => 'limpiar_foto_semilla'),
    'libre'           => array('field' => 'foto_libre', 'clear' => 'limpiar_foto_libre')
);

$allowedExts = array('jpg', 'jpeg', 'png', 'webp');
$mimeByExt = array(
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp'
);

$photos = array();

foreach ($photoMap as $key => $cfg) {
    $field = $cfg['field'];
    $clearFlag = !empty($_POST[$cfg['clear']]) && $_POST[$cfg['clear']] == '1';

    // 1. Archivo recién seleccionado en el formulario
    if (isset($_FILES[$field]) && isset($_FILES[$field]['error']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK && !empty($_FILES[$field]['tmp_name'])) {
        $origName = $_FILES[$field]['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            http_response_code(400);
            echo json_encode(array(
                'success' => false,
                'message' => "Formato de imagen no válido en '{$origName}'. Solo se admiten JPG, PNG y WEBP."
            ));
            exit;
        }

        $mime = !empty($_FILES[$field]['type']) ? $_FILES[$field]['type'] : (isset($mimeByExt[$ext]) ? $mimeByExt[$ext] : 'image/jpeg');
        $photos[$key] = array(
            'path' => $_FILES[$field]['tmp_name'],
            'mime' => $mime
        );
    } elseif (!$clearFlag && $existingRecord && !empty($existingRecord[$field])) {
        // 2. Foto ya guardada en el registro (para re-análisis incremental)
        $savedRel = ltrim($existingRecord[$field], '/');
        $absPath = PUBLIC_PATH . '/' . $savedRel;
        if (file_exists($absPath)) {
            $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
            $mime = isset($mimeByExt[$ext]) ? $mimeByExt[$ext] : 'image/jpeg';
            $photos[$key] = array(
                'path' => $absPath,
                'mime' => $mime
            );
        }
    }
}

try {
    $result = VademecumAiService::analyze($_POST, $photos);
    if (empty($result) || empty($result['success'])) {
        $msg = !empty($result['message']) ? $result['message'] : 'No se pudo obtener información contrastada.';
        echo json_encode(array(
            'success' => false,
            'message' => $msg
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(array(
        'success' => true,
        'photos_analyzed' => count($photos),
        'data' => $result
    ), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Error al consultar el asistente IA del Vademécum: ' . $e->getMessage()
    ), JSON_UNESCAPED_UNICODE);
}
