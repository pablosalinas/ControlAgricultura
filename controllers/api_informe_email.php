<?php
/**
 * Controlador API para Envío de Informes y Listados por Correo Electrónico
 * al usuario autenticado
 */

@ini_set('memory_limit', '256M');
@set_time_limit(120);
ob_start();

header('Content-Type: application/json; charset=utf-8');

// Capturar posibles errores fatales de memoria o tiempo de ejecución para devolver siempre JSON válido
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR))) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode(array(
            'success' => false,
            'message' => 'Error interno del servidor al procesar el informe: ' . $err['message']
        ));
    }
});

if (!class_exists('Auth') || !Auth::check()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'No autorizado. Debe iniciar sesión en el portal.'));
    exit;
}

$u = Auth::user();
$userEmail = !empty($u['email']) ? trim($u['email']) : '';
$userName = !empty($u['nombre_completo']) ? trim($u['nombre_completo']) : 'Usuario';

if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Tu usuario no tiene configurada una dirección de correo electrónico válida.'));
    exit;
}

$titulo = !empty($_POST['titulo']) ? trim($_POST['titulo']) : 'Informe Oficial de AgroDietario';
$nombreArchivo = !empty($_POST['nombre_archivo']) ? trim($_POST['nombre_archivo']) : 'informe_' . date('Ymd_His') . '.pdf';
$pdfBase64 = !empty($_POST['pdf_base64']) ? $_POST['pdf_base64'] : '';

$attachments = array();

// 1. Priorizar archivo binario subido como Blob (mucho más ligero en red y memoria RAM)
if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['pdf_file']['tmp_name'])) {
    $attachments[] = array(
        'name' => $nombreArchivo,
        'path' => $_FILES['pdf_file']['tmp_name'],
        'mime' => 'application/pdf'
    );
}
// 2. Fallback: PDF codificado en Base64
elseif (!empty($pdfBase64)) {
    $commaPos = strpos($pdfBase64, 'base64,');
    if ($commaPos !== false) {
        $bin = base64_decode(substr($pdfBase64, $commaPos + 7));
    } else {
        $bin = base64_decode($pdfBase64);
    }
    unset($pdfBase64);
    if ($bin !== false && strlen($bin) > 0) {
        $attachments[] = array(
            'name' => $nombreArchivo,
            'data' => $bin,
            'mime' => 'application/pdf'
        );
    }
}

// Cuerpo HTML corporativo del mensaje de correo
$asunto = $titulo . " - AgroDietario (" . date('d/m/Y') . ")";
$fechaEmision = date('d/m/Y H:i');

$cuerpoHtml = "
<!DOCTYPE html>
<html lang='es'>
<head>
<meta charset='utf-8'>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
.card { max-width: 750px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
.header { background: linear-gradient(135deg, #091e3a, #1d4ed8); color: #ffffff; padding: 25px 30px; }
.header h2 { margin: 0; font-size: 22px; font-weight: bold; }
.header p { margin: 5px 0 0; opacity: 0.85; font-size: 13px; }
.body { padding: 30px; line-height: 1.6; }
.badge { display: inline-block; background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 15px; }
.info-box { background: #f1f5f9; border-left: 4px solid #1d4ed8; padding: 15px; border-radius: 4px; margin: 20px 0; font-size: 14px; }
.footer { background: #f8fafc; padding: 20px 30px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
<div class='card'>
    <div class='header'>
        <h2>" . htmlspecialchars($titulo) . "</h2>
        <p>AgroDietario • Cuaderno de Campo Digital y Control Agrícola</p>
    </div>
    <div class='body'>
        <span class='badge'>Documento Oficial Emitido</span>
        <p>Hola <strong>" . htmlspecialchars($userName) . "</strong>,</p>
        <p>Le remitimos adjunto a este correo electrónico el documento oficial en formato PDF de <strong>" . htmlspecialchars($titulo) . "</strong>, generado desde la plataforma AgroDietario.</p>
        <div class='info-box'>
            <div>📅 <strong>Fecha de Emisión:</strong> {$fechaEmision}</div>
            <div>📄 <strong>Archivo Adjunto:</strong> " . htmlspecialchars($nombreArchivo) . "</div>
            <div>👤 <strong>Usuario Solicitante:</strong> " . htmlspecialchars($userName) . " (" . htmlspecialchars($userEmail) . ")</div>
        </div>
        <p>Puede abrir, guardar o imprimir el documento adjunto en cualquier dispositivo.</p>
    </div>
    <div class='footer'>
        Este correo ha sido generado automáticamente a petición de su cuenta en AgroDietario.<br>
        Por favor, no responda a este mensaje automático.
    </div>
</div>
</body>
</html>
";

try {
    require_once SRC_PATH . '/Services/Mailer.php';
    $res = Mailer::send($userEmail, $asunto, $cuerpoHtml, $attachments);
    if (is_array($res) && isset($res['success']) && !$res['success']) {
        throw new Exception(!empty($res['message']) ? $res['message'] : 'Fallo en la entrega SMTP.');
    }

    if (ob_get_length() > 0) {
        ob_clean();
    }
    echo json_encode(array(
        'success' => true,
        'message' => "Informe enviado con éxito a tu correo electrónico (" . $userEmail . ")"
    ));
} catch (Exception $e) {
    if (ob_get_length() > 0) {
        ob_clean();
    }
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => "No se pudo enviar el correo: " . $e->getMessage()
    ));
}
