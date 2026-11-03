<?php
/**
 * Controlador API - Consulta Inteligente de Productos Fitosanitarios con IA
 * Permite buscar productos por nombre comercial, materia activa o plaga objetivo
 * y devuelve los datos técnicos homologados para autocompletar la ficha de tratamiento.
 */

header('Content-Type: application/json; charset=utf-8');

// Verificación de sesión de usuario
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'No autorizado. Inicie sesión.'));
    exit;
}

require_once SRC_PATH . '/Services/PhytosanitaryAiService.php';

// Obtener parámetros
$query = isset($_GET['q']) ? trim($_GET['q']) : (isset($_POST['q']) ? trim($_POST['q']) : '');
$eco = isset($_GET['eco']) ? (bool)$_GET['eco'] : (isset($_POST['eco']) ? (bool)$_POST['eco'] : null);
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. Obtener lista de productos sugeridos / más comunes
if ($action === 'sugerencias' || empty($query)) {
    $sugerencias = PhytosanitaryAiService::getSuggestedProducts();
    echo json_encode(array(
        'success' => true,
        'sugerencias' => $sugerencias
    ));
    exit;
}

// 2. Consulta de producto mediante IA / Base de Datos Experta
$resultado = PhytosanitaryAiService::consultProduct($query, $eco);
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
exit;
