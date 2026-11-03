<?php
/**
 * Controlador API para Asistente IA de Cultivos
 * AgroDietario
 */

header('Content-Type: application/json; charset=utf-8');

if (!class_exists('Auth') || !Auth::check()) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'No autorizado. Debe iniciar sesión en el portal.'));
    exit;
}

$query = isset($_POST['query']) ? trim($_POST['query']) : '';
$isOrganic = (!empty($_POST['es_ecologico']) && $_POST['es_ecologico'] == '1');
$tipoCultivo = isset($_POST['tipo_cultivo']) ? trim($_POST['tipo_cultivo']) : '';

if (empty($query)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Indica el nombre de un cultivo o variedad para buscar.'));
    exit;
}

try {
    require_once SRC_PATH . '/Services/CropAiService.php';
    $data = CropAiService::searchCrop($query, $isOrganic, $tipoCultivo);

    echo json_encode(array(
        'success' => true,
        'data'    => $data
    ));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Error en la consulta agronómica de IA: ' . $e->getMessage()
    ));
}
