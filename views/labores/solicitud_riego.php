<?php
$pageTitle = "Papeleta Oficial de Solicitud de Riego - " . APP_NAME;
require_once SRC_PATH . '/Services/Mailer.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

// Procesar peticiones AJAX (Guardar Solicitud y Enviar por Correo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'enviar_por_correo' || $action === 'guardar_solicitud') {
        $pdfBase64 = isset($_POST['pdf_base64']) ? $_POST['pdf_base64'] : '';
        $filename = isset($_POST['filename']) ? $_POST['filename'] : 'solicitud_riego.pdf';
        $destinatario = isset($_POST['destinatario']) ? trim($_POST['destinatario']) : '';
        // El asunto del correo DEBE ser exactamente el nombre del archivo a enviar
        $asunto = $filename;
        $cuerpoHtml = isset($_POST['cuerpo_html']) ? $_POST['cuerpo_html'] : ("<p>Adjuntamos la papeleta oficial de solicitud de riego para la Comunidad de Regantes.</p>");

        // Datos del parte para guardar en base de datos
        $idSolicitud = (int)(isset($_POST['id_solicitud']) ? $_POST['id_solicitud'] : 0);
        $idFinca = !empty($_POST['id_finca']) ? (int)$_POST['id_finca'] : null;
        $idComunidad = !empty($_POST['id_comunidad']) ? (int)$_POST['id_comunidad'] : null;
        $idRegante = !empty($_POST['id_regante']) ? (int)$_POST['id_regante'] : null;
        $numRegante = isset($_POST['num_regante']) ? trim($_POST['num_regante']) : 'A1188';
        $titular = isset($_POST['titular']) ? trim($_POST['titular']) : '';
        $cifNif = isset($_POST['cif_nif']) ? trim($_POST['cif_nif']) : '';
        $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
        $domicilio = isset($_POST['domicilio']) ? trim($_POST['domicilio']) : '';
        $banco = isset($_POST['banco']) ? trim($_POST['banco']) : '';
        $tipoRiego = isset($_POST['tipo_riego']) ? trim($_POST['tipo_riego']) : 'comunitaria';
        $horas = floatval(isset($_POST['horas']) ? $_POST['horas'] : 0);
        $coste = floatval(isset($_POST['coste_estimado']) ? $_POST['coste_estimado'] : 0);
        $parada = isset($_POST['parada']) ? trim($_POST['parada']) : '';
        $paraje = isset($_POST['paraje']) ? trim($_POST['paraje']) : '';
        $tablon = isset($_POST['tablon']) ? trim($_POST['tablon']) : '';
        $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
        $cesionesJson = isset($_POST['cesiones_json']) ? $_POST['cesiones_json'] : null;
        $fechaSolicitud = isset($_POST['fecha_solicitud']) ? $_POST['fecha_solicitud'] : date('Y-m-d');
        $estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'pendiente';
        $fechaAsig = !empty($_POST['fecha_riego_asignado']) ? $_POST['fecha_riego_asignado'] : null;
        $horaAsig = !empty($_POST['hora_riego_asignado']) ? $_POST['hora_riego_asignado'] : null;
        $notasAdmin = isset($_POST['notas_admin']) ? trim($_POST['notas_admin']) : '';

        $enviadoEmail = ($action === 'enviar_por_correo') ? 1 : 0;
        $fechaEnvioEmail = ($action === 'enviar_por_correo') ? date('Y-m-d H:i:s') : null;

        // Guardar o actualizar registro en base de datos
        if ($idSolicitud > 0) {
            $sqlSave = "
                UPDATE solicitudes_riego SET
                    id_finca = :id_finca, id_comunidad = :id_comunidad, id_regante = :id_regante,
                    num_regante = :num_regante, titular = :titular, cif_nif = :cif_nif, telefono = :telefono,
                    domicilio = :domicilio, banco = :banco, tipo_riego = :tipo_riego,
                    horas_solicitadas = :horas, coste_estimado = :coste, parada = :parada, paraje = :paraje,
                    tablon = :tablon, observaciones = :observaciones, cesiones_json = :cesiones,
                    fecha_solicitud = :fecha, nombre_archivo_pdf = :filename, estado = :estado,
                    fecha_riego_asignado = :fecha_asig, hora_riego_asignado = :hora_asig, notas_admin = :notas,
                    enviado_email = IF(:enviado = 1, 1, enviado_email),
                    fecha_envio_email = IF(:enviado = 1, NOW(), fecha_envio_email),
                    email_destino = IF(:enviado = 1, :email_dest, email_destino)
                WHERE id_solicitud = :id
            ";
            $stmtSave = $db->prepare($sqlSave);
            $stmtSave->execute([
                ':id_finca' => $idFinca, ':id_comunidad' => $idComunidad, ':id_regante' => $idRegante,
                ':num_regante' => $numRegante, ':titular' => $titular, ':cif_nif' => $cifNif, ':telefono' => $telefono,
                ':domicilio' => $domicilio, ':banco' => $banco, ':tipo_riego' => $tipoRiego,
                ':horas' => $horas, ':coste' => $coste, ':parada' => $parada, ':paraje' => $paraje,
                ':tablon' => $tablon, ':observaciones' => $observaciones, ':cesiones' => $cesionesJson,
                ':fecha' => $fechaSolicitud, ':filename' => $filename, ':estado' => $estado,
                ':fecha_asig' => $fechaAsig, ':hora_asig' => $horaAsig, ':notas' => $notasAdmin,
                ':enviado' => $enviadoEmail, ':email_dest' => $destinatario, ':id' => $idSolicitud
            ]);
        } else {
            $sqlSave = "
                INSERT INTO solicitudes_riego (
                    id_propietario, id_finca, id_comunidad, id_regante, num_regante, titular, cif_nif,
                    telefono, domicilio, banco, tipo_riego, horas_solicitadas, coste_estimado,
                    parada, paraje, tablon, observaciones, cesiones_json, fecha_solicitud,
                    nombre_archivo_pdf, estado, fecha_riego_asignado, hora_riego_asignado, notas_admin,
                    enviado_email, fecha_envio_email, email_destino
                ) VALUES (
                    :id_prop, :id_finca, :id_comunidad, :id_regante, :num_regante, :titular, :cif_nif,
                    :telefono, :domicilio, :banco, :tipo_riego, :horas, :coste,
                    :parada, :paraje, :tablon, :observaciones, :cesiones, :fecha,
                    :filename, :estado, :fecha_asig, :hora_asig, :notas,
                    :enviado, :fecha_envio, :email_dest
                )
            ";
            $stmtSave = $db->prepare($sqlSave);
            $stmtSave->execute([
                ':id_prop' => $tenantId, ':id_finca' => $idFinca, ':id_comunidad' => $idComunidad, ':id_regante' => $idRegante,
                ':num_regante' => $numRegante, ':titular' => $titular, ':cif_nif' => $cifNif,
                ':telefono' => $telefono, ':domicilio' => $domicilio, ':banco' => $banco, ':tipo_riego' => $tipoRiego,
                ':horas' => $horas, ':coste' => $coste, ':parada' => $parada, ':paraje' => $paraje,
                ':tablon' => $tablon, ':observaciones' => $observaciones, ':cesiones' => $cesionesJson,
                ':fecha' => $fechaSolicitud, ':filename' => $filename, ':estado' => $estado,
                ':fecha_asig' => $fechaAsig, ':hora_asig' => $horaAsig, ':notas' => $notasAdmin,
                ':enviado' => $enviadoEmail, ':fecha_envio' => $fechaEnvioEmail, ':email_dest' => $destinatario
            ]);
            $idSolicitud = (int)$db->lastInsertId();
        }

        if ($action === 'guardar_solicitud') {
            echo json_encode([
                'success' => true,
                'message' => 'Parte de solicitud guardado correctamente para su consulta.',
                'id_solicitud' => $idSolicitud
            ]);
            exit;
        }

        // Si es envío por correo:
        if (empty($destinatario)) {
            $cfgMail = $db->query("SELECT email_solicitudes FROM comunidades_regantes WHERE es_predeterminada = 1 LIMIT 1")->fetch();
            $destinatario = !empty($cfgMail['email_solicitudes']) ? $cfgMail['email_solicitudes'] : 'alharabemoratalla@gmail.com';
        }

        if (empty($pdfBase64)) {
            echo json_encode(['success' => false, 'message' => 'No se recibió el documento PDF generado.']);
            exit;
        }

        // Extraer y limpiar rigurosamente cualquier cabecera data URI (p. ej. data:application/pdf;filename=...;base64,)
        if (strpos($pdfBase64, 'base64,') !== false) {
            $parts = explode('base64,', $pdfBase64);
            $pdfBase64 = end($parts);
        }
        // Reemplazar espacios por + en caso de codificación url
        $pdfBase64 = str_replace(' ', '+', trim($pdfBase64));
        $pdfBytes = base64_decode($pdfBase64);

        if (!$pdfBytes || strlen($pdfBytes) < 50 || substr($pdfBytes, 0, 4) !== '%PDF') {
            echo json_encode(['success' => false, 'message' => 'Error: El archivo PDF generado no es válido o está corrupto.']);
            exit;
        }

        $tempFile = sys_get_temp_dir() . '/' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
        file_put_contents($tempFile, $pdfBytes);

        $res = Mailer::send($destinatario, $asunto, $cuerpoHtml, [
            [
                'name' => $filename,
                'path' => $tempFile,
                'data' => $pdfBytes,
                'mime' => 'application/pdf'
            ]
        ]);
        @unlink($tempFile);

        $res['id_solicitud'] = $idSolicitud;
        echo json_encode($res);
        exit;
    }
}

require_once VIEWS_PATH . '/layouts/header.php';

// Consultar fincas del usuario
$sqlFincas = "
    SELECT f.*, p.nombre_paraje, prop.nombre_razon_social, prop.cif_nif, prop.direccion, prop.municipio, prop.telefono AS tel_prop
    FROM fincas f
    LEFT JOIN parajes p ON f.id_paraje = p.id_paraje
    LEFT JOIN propietarios prop ON f.id_propietario = prop.id_propietario
    WHERE f.activo = 1
";
if ($tenantId && !$isSuperAdmin) {
    $sqlFincas .= " AND f.id_propietario = " . (int)$tenantId;
}
$sqlFincas .= " ORDER BY f.nombre_finca ASC";
$fincas = $db->query($sqlFincas)->fetchAll();

// Consultar censo de regantes (Socios)
$regantes = $db->query("SELECT * FROM regantes WHERE activo = 1 ORDER BY num_regante ASC")->fetchAll();

// Consultar Comunidades de Regantes
$comunidades = $db->query("SELECT * FROM comunidades_regantes WHERE activo = 1 ORDER BY es_predeterminada DESC, nombre_comunidad ASC")->fetchAll();

// Si sólo hay una comunidad activa, asegurar que esté como predeterminada
if (count($comunidades) === 1 && empty($comunidades[0]['es_predeterminada'])) {
    $db->prepare("UPDATE comunidades_regantes SET es_predeterminada = 1 WHERE id_comunidad = :id")
       ->execute([':id' => $comunidades[0]['id_comunidad']]);
    $comunidades[0]['es_predeterminada'] = 1;
}

// Seleccionar la comunidad inicial propuesta
$comSeleccionada = null;
$paramComId = (int)(isset($_GET['comunidad_id']) ? $_GET['comunidad_id'] : 0);
if ($paramComId > 0) {
    foreach ($comunidades as $c) {
        if ((int)$c['id_comunidad'] === $paramComId) {
            $comSeleccionada = $c;
            break;
        }
    }
}
if (!$comSeleccionada) {
    foreach ($comunidades as $c) {
        if (!empty($c['es_predeterminada'])) {
            $comSeleccionada = $c;
            break;
        }
    }
}
if (!$comSeleccionada && !empty($comunidades)) {
    $comSeleccionada = $comunidades[0];
}

// Comprobar si se carga una solicitud existente (?id=)
$idSolCargada = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$solicitudCargada = null;
if ($idSolCargada > 0) {
    $stmtCarg = $db->prepare("SELECT * FROM solicitudes_riego WHERE id_solicitud = :id" . (($tenantId && !$isSuperAdmin) ? " AND id_propietario = " . (int)$tenantId : ""));
    $stmtCarg->execute([':id' => $idSolCargada]);
    $solicitudCargada = $stmtCarg->fetch();
}

if ($solicitudCargada) {
    $defNumRegante = $solicitudCargada['num_regante'];
    $defTablon = $solicitudCargada['tablon'];
    $defParada = $solicitudCargada['parada'];
    $defParaje = $solicitudCargada['paraje'];
    $defNombre = $solicitudCargada['titular'];
    $defDni = $solicitudCargada['cif_nif'];
    $defTelefono = $solicitudCargada['telefono'];
    $defDomicilio = $solicitudCargada['domicilio'];
    $defBanco = $solicitudCargada['banco'];
    $defTipoRiego = $solicitudCargada['tipo_riego'];
    $defHoras = $solicitudCargada['horas_solicitadas'];
    $defObservaciones = $solicitudCargada['observaciones'];
    $defFecha = $solicitudCargada['fecha_solicitud'];
    $defEstado = $solicitudCargada['estado'];
    $defFechaAsig = $solicitudCargada['fecha_riego_asignado'];
    $defHoraAsig = $solicitudCargada['hora_riego_asignado'] ? substr($solicitudCargada['hora_riego_asignado'], 0, 5) : '';
    $defEnviadoEmail = (int)$solicitudCargada['enviado_email'];
    $defFechaEnvioEmail = $solicitudCargada['fecha_envio_email'];
    $defEmailDestino = $solicitudCargada['email_destino'];
    $defNotasAdmin = $solicitudCargada['notas_admin'];
} else {
    // Datos predeterminados del socio inicial
    $reganteInicial = !empty($regantes) ? $regantes[0] : null;
    $preReganteId = (int)(isset($_GET['regante_id']) ? $_GET['regante_id'] : 0);
    if ($preReganteId > 0) {
        foreach ($regantes as $r) {
            if ((int)$r['id_regante'] === $preReganteId) {
                $reganteInicial = $r;
                break;
            }
        }
    }

    $defNumRegante = $reganteInicial ? $reganteInicial['num_regante'] : 'A1188';
    $defTablon = $reganteInicial ? ($reganteInicial['tablon_habitual'] ?: '6') : '6';
    $defParada = $reganteInicial ? ($reganteInicial['parada_habitual'] ?: 'Balsa Perales (67)') : 'Balsa Perales (67)';
    $defParaje = $reganteInicial ? ($reganteInicial['paraje_habitual'] ?: 'Hornillos') : 'Hornillos';
    $defNombre = $reganteInicial ? $reganteInicial['nombre_completo'] : $currentUser['nombre_completo'];
    $defDni = $reganteInicial ? ($reganteInicial['cif_nif'] ?: '52810184E') : '52810184E';
    $defDomicilio = $reganteInicial ? ($reganteInicial['direccion'] . ($reganteInicial['municipio'] ? ' - ' . $reganteInicial['municipio'] : '')) : 'C/ Virgen de Montserrat, 31 1ºB - Totana';
    $defTelefono = $reganteInicial ? ($reganteInicial['telefono'] ?: '679873616') : '679873616';
    $defBanco = $comSeleccionada ? ($comSeleccionada['banco_habitual'] ?: 'CAJAMAR') : ($reganteInicial ? ($reganteInicial['banco_habitual'] ?: 'CAJAMAR') : 'CAJAMAR');
    $defTipoRiego = 'comunitaria';
    $defHoras = '2.00';
    $defObservaciones = '';
    $defFecha = date('Y-m-d');
    $defEstado = 'pendiente';
    $defFechaAsig = '';
    $defHoraAsig = '';
    $defEnviadoEmail = 0;
    $defFechaEnvioEmail = '';
    $defEmailDestino = '';
    $defNotasAdmin = '';
}
?>

<div class="container-fluid px-3 px-md-4 py-4 no-print">

    <!-- Cabecera Corporativa y Acciones Principales -->
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-file-invoice fs-3 text-info"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Papeleta Oficial de Solicitud de Riego</h2>
                    <?php if ($idSolCargada > 0): ?>
                        <span class="badge bg-primary font-monospace">Parte #<?= str_pad($idSolCargada, 4, '0', STR_PAD_LEFT) ?></span>
                    <?php endif; ?>
                </div>
                <span class="text-muted small">Formatos Oficiales de Riego • 3 Variedades: Tanda, Comunitaria y Comunitaria HORTALIZAS (A4 en 1 sola página)</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= APP_URL ?>/partes-riego" class="btn btn-outline-primary fw-bold shadow-sm">
                <i class="fa-solid fa-list-check me-1"></i> Consultar Partes Guardados
            </a>
            <button type="button" class="btn btn-outline-success fw-bold shadow-sm" onclick="guardarSolicitud(false)">
                <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Solicitud
            </button>
            <button type="button" class="btn btn-primary fw-bold shadow-sm" onclick="descargarPDF()">
                <i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF (1 Página A4)
            </button>
            <button type="button" class="btn btn-success fw-bold shadow-sm" id="btnEnviarEmail" onclick="enviarPorCorreo()">
                <i class="fa-solid fa-paper-plane me-1"></i> Enviar por Email a la Comunidad
            </button>
            <button type="button" class="btn btn-outline-secondary fw-semibold shadow-sm" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- Indicador Permanente de Envío por Email (Requerimiento de Confirmación) -->
    <div id="boxEstadoEnvio" class="alert alert-success d-flex flex-column flex-sm-row align-items-sm-center justify-content-between p-3 mb-4 border-0 shadow-sm" style="<?= $defEnviadoEmail ? '' : 'display: none;' ?>">
        <div class="d-flex align-items-center gap-3 mb-2 mb-sm-0">
            <i class="fa-solid fa-circle-check fs-2 text-success"></i>
            <div>
                <h6 class="fw-bold mb-0 text-success">
                    <i class="fa-solid fa-paper-plane me-1"></i>Papeleta Oficial Remitida por Correo Electrónico
                </h6>
                <div class="small text-muted" id="txtEstadoEnvioDetalle">
                    <?php if ($defEnviadoEmail): ?>
                        Enviada con éxito a <strong><?= htmlspecialchars($defEmailDestino ?: 'la comunidad') ?></strong> el <?= !empty($defFechaEnvioEmail) ? date('d/m/Y a las H:i', strtotime($defFechaEnvioEmail)) : '' ?> h.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <a href="<?= APP_URL ?>/partes-riego" class="btn btn-sm btn-outline-success fw-bold text-nowrap">
            <i class="fa-solid fa-list-check me-1"></i> Ver en Partes de Riego
        </a>
    </div>

    <input type="hidden" id="in_id_solicitud" value="<?= $idSolCargada ?>">

    <!-- Panel de Cumplimentación Simplificado -->
    <div class="card card-custom p-4 mb-4 border-top border-4 border-primary shadow-sm">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-sliders me-2"></i>Cumplimentación Simplificada de Petición
            </h5>
            <span class="badge bg-primary bg-opacity-10 text-primary">Pre-rellenado automático por Comunidad, Socio y Finca</span>
        </div>

        <!-- Fila 1: Comunidad de Regantes y Autorellenado -->
        <div class="row g-3 mb-3 bg-light p-3 rounded-3 border">
            <!-- Comunidad de Regantes -->
            <div class="col-md-5">
                <label class="form-label fw-bold small text-primary">
                    <i class="fa-solid fa-water me-1"></i>Comunidad de Regantes Destinataria *
                </label>
                <select id="sel_comunidad" class="form-select fw-bold text-dark font-monospace" onchange="cargarComunidadSeleccionada()">
                    <?php if (empty($comunidades)): ?>
                        <option value="0" data-nom="Comunidad de Regantes del Río Alhárabe" data-email="alharabemoratalla@gmail.com" data-banco="CAJAMAR" data-ptanda="0" data-maxtanda="0" data-pcom="12" data-maxcom="0" data-phort="15" data-maxhort="2" data-derr="150">
                            Comunidad de Regantes del Río Alhárabe (Predeterminada)
                        </option>
                    <?php else: ?>
                        <?php foreach ($comunidades as $com): ?>
                            <option value="<?= $com['id_comunidad'] ?>"
                                data-nom="<?= htmlspecialchars($com['nombre_comunidad']) ?>"
                                data-email="<?= htmlspecialchars($com['email_solicitudes']) ?>"
                                data-banco="<?= htmlspecialchars($com['banco_habitual'] ?: 'CAJAMAR') ?>"
                                data-ptanda="<?= $com['precio_hora_tanda'] ?>"
                                data-maxtanda="<?= $com['consumo_max_tanda_horas'] ?>"
                                data-pcom="<?= $com['precio_hora_comunitaria'] ?>"
                                data-maxcom="<?= $com['consumo_max_comunitaria_horas'] ?>"
                                data-phort="<?= $com['precio_hora_hortalizas'] ?>"
                                data-maxhort="<?= $com['consumo_max_hortalizas_horas'] ?>"
                                data-derr="<?= $com['derrama_anual'] ?>"
                                data-horario="<?= htmlspecialchars($com['horario_atencion'] ?: '') ?>"
                                <?= ($comSeleccionada && $comSeleccionada['id_comunidad'] == $com['id_comunidad']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($com['nombre_comunidad']) ?> <?= !empty($com['es_predeterminada']) ? '★ (Predeterminada)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <div class="form-text small" id="txt_comunidad_email">
                    <i class="fa-solid fa-envelope me-1 text-primary"></i>Destino: <strong><?= htmlspecialchars(!empty($comSeleccionada['email_solicitudes']) ? $comSeleccionada['email_solicitudes'] : 'alharabemoratalla@gmail.com') ?></strong>
                </div>
            </div>

            <!-- Socio Regante -->
            <div class="col-md-4">
                <label class="form-label fw-bold small text-primary"><i class="fa-solid fa-user-check me-1"></i>Socio Regante (Censo)</label>
                <select id="sel_regante" class="form-select font-monospace" onchange="cargarDatosRegante()">
                    <option value="">-- Buscar socio por Nº o Nombre --</option>
                    <?php foreach ($regantes as $rg): ?>
                        <option value="<?= $rg['id_regante'] ?>"
                            data-num="<?= htmlspecialchars($rg['num_regante']) ?>"
                            data-nom="<?= htmlspecialchars($rg['nombre_completo']) ?>"
                            data-dni="<?= htmlspecialchars($rg['cif_nif'] ?: '') ?>"
                            data-tel="<?= htmlspecialchars($rg['telefono'] ?: '') ?>"
                            data-banco="<?= htmlspecialchars($rg['banco_habitual'] ?: 'CAJAMAR') ?>"
                            data-dir="<?= htmlspecialchars(trim($rg['direccion'] . ($rg['municipio'] ? ' - ' . $rg['municipio'] : ''))) ?>"
                            data-parada="<?= htmlspecialchars($rg['parada_habitual'] ?: '') ?>"
                            data-paraje="<?= htmlspecialchars($rg['paraje_habitual'] ?: '') ?>"
                            data-tablon="<?= htmlspecialchars($rg['tablon_habitual'] ?: '') ?>"
                            data-ht="<?= $rg['horas_tanda_disponibles'] ?>"
                            data-hc="<?= $rg['horas_comunitaria_disponibles'] ?>"
                            <?= ($reganteInicial && $reganteInicial['id_regante'] == $rg['id_regante']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($rg['num_regante']) ?> - <?= htmlspecialchars($rg['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Finca -->
            <div class="col-md-3">
                <label class="form-label fw-bold small text-primary"><i class="fa-solid fa-layer-group me-1"></i>Finca Asignada</label>
                <select id="sel_finca" class="form-select" onchange="cargarDatosFinca()">
                    <option value="">-- Cargar Finca --</option>
                    <?php foreach ($fincas as $f): ?>
                        <option value="<?= $f['id_finca'] ?>"
                            data-socio="<?= htmlspecialchars($f['num_socio_comunidad'] ?: 'A1188') ?>"
                            data-tablon="<?= htmlspecialchars($f['tablon'] ?: '') ?>"
                            data-parada="<?= htmlspecialchars($f['parada'] ?: '') ?>"
                            data-paraje="<?= htmlspecialchars($f['nombre_paraje'] ?: '') ?>"
                            data-horas-prop="<?= $f['horas_propiedad_tanda'] ?>"
                            data-horas-disp="<?= $f['horas_disponibles_tanda'] ?>"
                            data-titular="<?= htmlspecialchars($f['nombre_razon_social'] ?: $defNombre) ?>"
                            data-dni="<?= htmlspecialchars($f['cif_nif'] ?: $defDni) ?>"
                            data-domicilio="<?= htmlspecialchars($f['direccion'] ?: $defDomicilio) ?>"
                            data-tel="<?= htmlspecialchars($f['tel_prop'] ?: $defTelefono) ?>"
                        >
                            <?= htmlspecialchars($f['nombre_finca']) ?> (<?= htmlspecialchars($f['parada'] ?: 'Sin parada') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Fila 2: Las 3 Variedades Oficiales de Riego -->
        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <label class="form-label fw-bold small text-primary">Modalidad / Variedad de Riego Solicitada *</label>
                <select id="in_tipo_riego" class="form-select fw-bold text-primary fs-6" onchange="conmutarTipoRiego()">
                    <option value="tanda">⏳ TANDA (Agua en propiedad con cesiones de socios)</option>
                    <option value="comunitaria" selected>💧 COMUNITARIA (Por horas disponibles según tierra)</option>
                    <option value="comunitaria_hortalizas">⭐ COMUNITARIA HORTALIZAS (Fin de semana en verano / horario pautado)</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold small">Horas Totales a Solicitar *</label>
                <div class="input-group">
                    <input type="number" step="0.25" id="in_horas" class="form-control font-monospace fw-bold text-primary fs-5" value="2.00" oninput="actualizarVistaPrevia()">
                    <span class="input-group-text fw-bold">horas</span>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold small">Fecha de Riego *</label>
                <input type="date" id="in_fecha" class="form-control font-monospace" value="<?= date('Y-m-d') ?>" oninput="actualizarVistaPrevia()">
            </div>
        </div>

        <!-- Aviso Dinámico de Consumo Máximo / Fijo -->
        <div id="boxConsumoInfo" class="alert alert-info py-2 px-3 mb-3 small d-flex align-items-center justify-content-between">
            <span id="txtConsumoInfo"><i class="fa-solid fa-scale-balanced me-2"></i>Consumo según participaciones / derechos del socio.</span>
            <span class="badge bg-primary font-monospace" id="badgeTarifaHora">12.00 €/h</span>
        </div>

        <!-- Datos del Solicitante -->
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Nº Regante (Socio)</label>
                <input type="text" id="in_regante" class="form-control font-monospace fw-bold" value="<?= htmlspecialchars($defNumRegante) ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Nº Tablón</label>
                <input type="text" id="in_tablon" class="form-control font-monospace" value="<?= htmlspecialchars($defTablon) ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Teléfono</label>
                <input type="text" id="in_telefono" class="form-control font-monospace" value="<?= htmlspecialchars($defTelefono) ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Parada</label>
                <input type="text" id="in_parada" class="form-control" value="<?= htmlspecialchars($defParada) ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Paraje</label>
                <input type="text" id="in_paraje" class="form-control" value="<?= htmlspecialchars($defParaje) ?>" oninput="actualizarVistaPrevia()">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Titular Solicitante (D.)</label>
                <input type="text" id="in_titular" class="form-control fw-bold" value="<?= htmlspecialchars($defNombre) ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Domicilio Completo</label>
                <input type="text" id="in_domicilio" class="form-control" value="<?= htmlspecialchars($defDomicilio) ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">DNI / NIF</label>
                <input type="text" id="in_dni" class="form-control font-monospace" value="<?= htmlspecialchars($defDni) ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Entidad Bancaria</label>
                <input type="text" id="in_banco" class="form-control" value="<?= htmlspecialchars($defBanco) ?>" oninput="actualizarVistaPrevia()">
            </div>
        </div>

        <!-- Rejilla de Cesiones de Otros Socios (Exclusiva para AGUA TANDA) -->
        <div id="panelCesionesTanda" class="mb-3 p-3 rounded-3 border bg-light" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                <h6 class="fw-bold text-primary mb-0">
                    <i class="fa-solid fa-users me-1"></i>Distribución de Horas y Cesiones de Otros Socios (Estadillo Tanda)
                </h6>
                <span class="badge bg-info text-dark">6 Filas Oficiales Río Alhárabe</span>
            </div>
            <p class="text-muted small mb-3">
                En las tandas, otros comuneros pueden ceder sus horas de riego. Indica aquí el socio cedente, sus horas y su número de regante:
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle bg-white mb-2">
                    <thead class="table-light small">
                        <tr>
                            <th style="width: 70px;">Fila</th>
                            <th style="width: 140px;">Horas</th>
                            <th style="width: 80px;" class="text-center">Unidad</th>
                            <th>Nombre del Regante Cedente / Titular</th>
                            <th style="width: 170px;">Nº Regante</th>
                            <th style="width: 200px;">Cargar desde Censo</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyCesiones">
                        <!-- Fila 1: Titular Principal -->
                        <tr class="table-primary bg-opacity-10">
                            <td class="text-center fw-bold">1 (Tú)</td>
                            <td><input type="number" step="0.25" id="ces_h_1" class="form-control form-control-sm font-monospace fw-bold" value="2.0" oninput="actualizarCesiones()"></td>
                            <td class="text-center font-monospace fw-bold">Hor</td>
                            <td><input type="text" id="ces_nom_1" class="form-control form-control-sm fw-bold" value="<?= htmlspecialchars($defNombre) ?>" oninput="actualizarCesiones()"></td>
                            <td><input type="text" id="ces_num_1" class="form-control form-control-sm font-monospace fw-bold" value="<?= htmlspecialchars($defNumRegante) ?>" oninput="actualizarCesiones()"></td>
                            <td class="text-muted small text-center">Titular Solicitante</td>
                        </tr>
                        <!-- Filas 2 a 6: Cesiones de otros socios -->
                        <?php for ($i = 2; $i <= 6; $i++): ?>
                            <tr>
                                <td class="text-center fw-semibold text-muted"><?= $i ?></td>
                                <td><input type="number" step="0.25" id="ces_h_<?= $i ?>" class="form-control form-control-sm font-monospace" placeholder="0.0" oninput="actualizarCesiones()"></td>
                                <td class="text-center font-monospace text-muted">Hor</td>
                                <td><input type="text" id="ces_nom_<?= $i ?>" class="form-control form-control-sm" placeholder="Nombre del socio cedente..." oninput="actualizarCesiones()"></td>
                                <td><input type="text" id="ces_num_<?= $i ?>" class="form-control form-control-sm font-monospace" placeholder="Ej: A0420" oninput="actualizarCesiones()"></td>
                                <td>
                                    <select class="form-select form-select-sm" onchange="seleccionarCedente(<?= $i ?>, this)">
                                        <option value="">-- Asignar socio --</option>
                                        <?php foreach ($regantes as $rg): ?>
                                            <option value="<?= htmlspecialchars($rg['num_regante']) ?>" data-nom="<?= htmlspecialchars($rg['nombre_completo']) ?>">
                                                <?= htmlspecialchars($rg['num_regante']) ?> - <?= htmlspecialchars($rg['nombre_completo']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Observaciones y Firma Digital -->
        <div class="row g-3 mb-2">
            <div class="col-md-8">
                <label class="form-label fw-semibold small">Observaciones Oficiales</label>
                <div class="input-group">
                    <input type="text" id="in_observaciones" class="form-control text-uppercase font-monospace" value="" placeholder="Indicar HORTALIZAS o notas de turno..." oninput="actualizarVistaPrevia()">
                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('in_observaciones').value='HORTALIZAS'; actualizarVistaPrevia();">
                        Fijar "HORTALIZAS"
                    </button>
                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('in_observaciones').value=''; actualizarVistaPrevia();">
                        Limpiar
                    </button>
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="w-100">
                    <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="collapse" data-bs-target="#panelFirma">
                        <i class="fa-solid fa-signature me-1"></i> Dibujar Firma en Pantalla
                    </button>
                </div>
            </div>
        </div>

        <!-- Panel de Firma Digital Táctil / Ratón -->
        <div class="collapse mt-3" id="panelFirma">
            <div class="p-3 bg-light rounded-3 border">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold text-muted"><i class="fa-solid fa-pen-nib me-1"></i>Dibuja tu firma a continuación con el ratón o pantalla táctil:</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="limpiarFirma()">Limpiar Firma</button>
                </div>
                <div class="text-center bg-white border rounded" style="touch-action: none;">
                    <canvas id="canvasFirma" width="450" height="130" style="width: 100%; max-width: 450px; cursor: crosshair;"></canvas>
                </div>
            </div>
        </div>

        <!-- Fila de Estado del Parte y Turno Asignado por la Comunidad de Regantes -->
        <div class="mt-3 pt-3 border-top bg-light p-3 rounded-3 border">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-calendar-check me-2 text-primary"></i>Gestión de Turno y Estado del Parte
                </h6>
                <span class="badge bg-secondary">Actualizable por el usuario o administrador</span>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Estado de la Solicitud</label>
                    <select id="in_estado" class="form-select fw-bold">
                        <option value="pendiente" <?= $defEstado === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente de Turno</option>
                        <option value="asignado" <?= $defEstado === 'asignado' ? 'selected' : '' ?>>📅 Turno Asignado por Comunidad</option>
                        <option value="regado" <?= $defEstado === 'regado' ? 'selected' : '' ?>>💧 Regado / Realizado</option>
                        <option value="descartado" <?= $defEstado === 'descartado' ? 'selected' : '' ?>>❌ Descartado / Cancelado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Día Asignado por Comunidad</label>
                    <input type="date" id="in_fecha_asig" class="form-control font-monospace" value="<?= htmlspecialchars($defFechaAsig ?: '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Hora Asignada</label>
                    <input type="time" id="in_hora_asig" class="form-control font-monospace" value="<?= htmlspecialchars($defHoraAsig ?: '') ?>" step="900">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Notas / Instrucciones del Guarda</label>
                    <input type="text" id="in_notas_admin" class="form-control" value="<?= htmlspecialchars($defNotasAdmin ?: '') ?>" placeholder="Ej: Confirmado con el guarda, brazal 3...">
                </div>
            </div>
        </div>
    </div>

    <!-- Valoración Económica y Nombre Normalizado de las 3 Variedades -->
    <div class="row g-3 mb-4">
        <!-- Tarjeta de Coste Estimado -->
        <div class="col-md-6">
            <div class="card card-custom p-3 border-start border-4 border-success h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Valoración Económica del Riego</div>
                        <div class="fs-4 fw-bold text-success" id="box_coste_total">24.00 €</div>
                        <div class="small text-muted" id="box_coste_detalle">2.00 h × 12.00 €/h (Comunitaria)</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="fa-solid fa-euro-sign fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Nombre del Archivo y Buzón Destino -->
        <div class="col-md-6">
            <div class="card card-custom p-3 border-start border-4 border-primary h-100 d-flex justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Nombre de Archivo Normalizado (3 Variedades)</div>
                    <code class="fs-6 fw-bold text-dark bg-light px-2 py-1 rounded d-block mt-1 text-truncate" id="preview_filename">A1188_2026-09-15_PABLO_Comunitaria_A1188_15.09.2026.pdf</code>
                </div>
                <div class="small text-muted mt-2">
                    <i class="fa-solid fa-envelope me-1 text-primary"></i>Destino de envío: <strong id="lbl_email_destino">alharabemoratalla@gmail.com</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Notificación de estado de envío por correo -->
    <div id="alertEmailStatus" class="alert alert-dismissible fade show border-0 shadow-sm mb-4" style="display: none;">
        <span id="alertEmailMsg"></span>
        <button type="button" class="btn-close" onclick="document.getElementById('alertEmailStatus').style.display='none';"></button>
    </div>

</div>

<!-- ============================================================================== -->
<!-- DOCUMENTO OFICIAL A4 PARA VISTA PREVIA, IMPRESIÓN Y GENERACIÓN DE PDF           -->
<!-- ============================================================================== -->
<div class="hoja-preview-container d-flex justify-content-center bg-secondary bg-opacity-10 py-3 py-print-0" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
    <div id="documentoOficial" class="hoja-oficial bg-white shadow-lg">

        <!-- Recuadro Principal del Formulario -->
        <div class="marco-principal mb-2">
            <!-- Fila Superior: Logo + Nº papeleta + Tabla Socio/Tablón/Teléfono -->
            <div class="d-flex justify-content-between align-items-start mb-2 pb-1">
                <!-- Logo Oficial de la Comunidad -->
                <div class="logo-alharabe d-flex align-items-center">
                    <div class="logo-badge px-3 py-2 rounded-2">
                        <div class="logo-sub">Comunidad de Regantes del</div>
                        <div class="logo-main" id="doc_nombre_comunidad">Río Alhárabe</div>
                    </div>
                </div>

                <!-- Indicador de Nº para Plantilla Tanda -->
                <div class="align-self-center px-3" id="doc_indicador_num" style="display: none;">
                    <span class="fs-4 fw-bold font-monospace">Nº</span> <span style="display:inline-block; width: 60px; border-bottom: 1.5px solid #000;"></span>
                </div>

                <!-- Tabla Identificativa Superior Derecha -->
                <table class="tabla-regante">
                    <tr>
                        <td class="lbl-celda">Nº REGANTE</td>
                        <td class="val-celda font-monospace" id="doc_regante"><?= htmlspecialchars($defNumRegante) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl-celda">Nº TABLÓN</td>
                        <td class="val-celda font-monospace" id="doc_tablon"><?= htmlspecialchars($defTablon) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl-celda">TELÉFONO</td>
                        <td class="val-celda font-monospace" id="doc_telefono"><?= htmlspecialchars($defTelefono) ?></td>
                    </tr>
                </table>
            </div>

            <!-- Título Central: AGUA COMUNITARIA / AGUA TANDA -->
            <div class="text-center my-3">
                <h3 class="fw-bold mb-0 text-dark titulo-tipo-agua" id="doc_titulo_tipo">AGUA COMUNITARIA</h3>
            </div>

            <!-- VISTA 1: CUERPO FORMATO AGUA COMUNITARIA (Ordinaria / Hortalizas) -->
            <div id="cuerpoComunitaria">
                <table class="tabla-cuerpo w-100">
                    <tr>
                        <td class="etiqueta" style="width: 120px;">D.</td>
                        <td colspan="3" class="campo-valor" id="doc_titular_com"><?= htmlspecialchars($defNombre) ?></td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Domicilio</td>
                        <td colspan="3" class="campo-valor" id="doc_domicilio_com"><?= htmlspecialchars($defDomicilio) ?></td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Solicita:</td>
                        <td class="campo-valor" style="width: 130px; text-align: center;">
                            <span id="doc_horas_com" class="fw-bold font-monospace fs-5">2.00</span>
                        </td>
                        <td class="etiqueta text-center" style="width: 100px;">Horas</td>
                        <td class="bg-white"></td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Observaciones</td>
                        <td colspan="3" class="campo-valor text-uppercase font-monospace" id="doc_observaciones_com">-</td>
                    </tr>
                </table>

                <table class="tabla-inferior w-100 mt-2">
                    <tr>
                        <td class="etiqueta" style="width: 15%;">DNI</td>
                        <td class="campo-valor font-monospace" style="width: 35%;" id="doc_dni_com"><?= htmlspecialchars($defDni) ?></td>
                        <td class="etiqueta" style="width: 15%;">Banco</td>
                        <td class="campo-valor" style="width: 35%;" id="doc_banco_com"><?= htmlspecialchars($defBanco) ?></td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Parada</td>
                        <td class="campo-valor" id="doc_parada_com"><?= htmlspecialchars($defParada) ?></td>
                        <td class="etiqueta">Paraje</td>
                        <td class="campo-valor" id="doc_paraje_com"><?= htmlspecialchars($defParaje) ?></td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Fecha</td>
                        <td class="campo-valor font-monospace" id="doc_fecha_com"><?= date('d/m/Y') ?></td>
                        <td class="etiqueta">Firma</td>
                        <td class="campo-valor text-center" style="height: 65px; vertical-align: middle;">
                            <img id="doc_firma_img_com" src="" style="max-height: 55px; max-width: 180px; display: none;" alt="Firma">
                            <span id="doc_firma_placeholder_com" class="text-muted fst-italic" style="font-family:'Segoe Script',cursive,sans-serif; font-size:1.15rem; color:#1e293b;"><?= htmlspecialchars($defNombre) ?></span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- VISTA 2: CUERPO FORMATO AGUA TANDA CON CESIONES (Fiel al documento original) -->
            <div id="cuerpoTanda" style="display: none;">
                <table class="tabla-cuerpo w-100">
                    <tr>
                        <td class="etiqueta" style="width: 90px;">D.</td>
                        <td colspan="3" class="campo-valor" id="doc_titular_tanda"><?= htmlspecialchars($defNombre) ?></td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Domicilio</td>
                        <td colspan="3" class="campo-valor" id="doc_domicilio_tanda"><?= htmlspecialchars($defDomicilio) ?></td>
                    </tr>
                </table>

                <!-- Fila de Solicitud de Horas y Encabezado Nº Regante -->
                <div class="d-flex justify-content-between align-items-center my-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fs-5 fw-bold">Solicita:</span>
                        <div class="px-3 py-1 border border-dark text-center fw-bold font-monospace fs-5 bg-white" style="min-width: 90px;" id="doc_horas_tanda">
                            2.0
                        </div>
                        <span class="fs-5 fw-bold">Horas</span>
                    </div>
                    <div>
                        <div class="px-3 py-1 border border-dark fw-bold text-center bg-white font-monospace" style="min-width: 140px;">
                            Nº Regante
                        </div>
                    </div>
                </div>

                <!-- Rejilla Oficial de 6 Filas de Cesiones -->
                <table class="tabla-cesiones-tanda w-100 mb-2">
                    <?php for ($k = 1; $k <= 6; $k++): ?>
                        <tr>
                            <td class="celda-horas font-monospace text-center fw-bold" id="grid_h_<?= $k ?>"><?= ($k === 1) ? '2.0' : '' ?></td>
                            <td class="celda-unidad text-center font-monospace">Hor</td>
                            <td class="celda-nombre ps-2 fw-semibold" id="grid_nom_<?= $k ?>"><?= ($k === 1) ? htmlspecialchars($defNombre) : '' ?></td>
                            <td class="celda-num font-monospace text-center fw-bold" id="grid_num_<?= $k ?>"><?= ($k === 1) ? htmlspecialchars($defNumRegante) : '' ?></td>
                        </tr>
                    <?php endfor; ?>
                </table>

                <!-- Bloque Inferior: Parada, Paraje, Fecha, Firma -->
                <table class="tabla-inferior w-100 mt-2">
                    <tr>
                        <td class="etiqueta" style="width: 15%;">Parada</td>
                        <td class="campo-valor" style="width: 35%;" id="doc_parada_tanda"><?= htmlspecialchars($defParada) ?></td>
                        <td class="etiqueta" style="width: 15%;">Paraje</td>
                        <td class="campo-valor" style="width: 35%;" id="doc_paraje_tanda"><?= htmlspecialchars($defParaje) ?></td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Fecha</td>
                        <td class="campo-valor font-monospace" id="doc_fecha_tanda"><?= date('d/m/Y') ?></td>
                        <td class="etiqueta">Firma</td>
                        <td class="campo-valor text-center" style="height: 65px; vertical-align: middle;">
                            <img id="doc_firma_img_tanda" src="" style="max-height: 55px; max-width: 180px; display: none;" alt="Firma">
                            <span id="doc_firma_placeholder_tanda" class="text-muted fst-italic" style="font-family:'Segoe Script',cursive,sans-serif; font-size:1.15rem; color:#1e293b;"><?= htmlspecialchars($defNombre) ?></span>
                        </td>
                    </tr>
                </table>
            </div>

        </div>

        <!-- Instrucciones Oficiales de la Comunidad de Regantes -->
        <div class="seccion-instrucciones mt-3 pt-1">
            <h6 class="fw-bold text-dark text-decoration-underline mb-2" style="font-size: 0.90rem; letter-spacing: 0.02em;">
                INFORMACIÓN SOBRE CUMPLIMENTACION DE PLANTILLA
            </h6>
            <ol class="lista-instrucciones ps-3 mb-3">
                <li>Todos los campos en blanco son obligatorios, excepto el de observaciones, y deben ser rellenados de forma clara y legible para una mejor identificación de los mismos.</li>
                <li>El campo de observaciones deberá cumplimentarlo en caso de utilizar la plantilla para el riego de hortalizas, en tal situación tendrá que indicar <strong>HORTALIZAS</strong> en dicho campo.</li>
                <li>Una vez hayan cumplimentado la solicitud tienen que guardar el archivo utilizando Nº de Regante mas la fecha del envío, (Ejemplo: A0000 25.05.2012) utilizando puntos en la separación de la fecha para que Word os permita guardarlo.</li>
                <li>Una vez guardado, pueden enviarlo mediante un archivo adjunto a la dirección de e-mail de la Comunidad de Regantes que es la siguiente <strong id="doc_instrucciones_email">alharabemoratalla@gmail.com</strong>.</li>
                <li>Todas las solicitudes informáticas deberán ser enviadas con un mínimo de 3 a 4 días de antelación a los riegos al igual que las papeletas físicas para poder imprimirlas y colocarlas con el resto.</li>
                <li>En caso de dudas pueden ponerse en contacto con la oficina de la Comunidad (<span id="doc_instrucciones_horario">lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00</span>).</li>
            </ol>

            <div class="caja-atencion text-center p-2 border border-dark">
                <div class="fw-bold fs-6 mb-1 text-dark">ATENCIÓN</div>
                <div class="small fw-semibold text-dark">
                    NO ALTEREN LA COMPOSICIÓN DE LA PLANTILLA A LA HORA DE RELLENARLA, YA QUE SE TIENE QUE IMPRIMIR EN LA OFICINA PARA SU POSTERIOR ARCHIVO.
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================================== -->
<!-- ESTILOS CSS PARA DOCUMENTO A4 Y SALIDA POR IMPRESORA                           -->
<!-- ============================================================================== -->
<style>
.hoja-preview-container {
    padding: 12px 0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.hoja-oficial {
    width: 195mm;
    max-width: 100%;
    margin: 0 auto;
    font-family: 'Times New Roman', Times, serif;
    color: #000;
    background: #fff;
    box-sizing: border-box;
    padding: 8px 12px;
}

.marco-principal {
    border: 1.5px solid #000;
    padding: 6px 10px;
    background: #fff;
    margin-bottom: 5px;
}

.logo-badge {
    background: linear-gradient(135deg, #0d5c3a 0%, #177a4f 100%);
    color: #fff;
    border-radius: 6px;
    min-width: 200px;
    padding: 3px 8px;
}
.logo-sub {
    font-size: 0.65rem;
    font-family: 'Arial', sans-serif;
    letter-spacing: 0.04em;
    opacity: 0.9;
}
.logo-main {
    font-size: 1.25rem;
    font-weight: 800;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: #5eead4;
    letter-spacing: -0.02em;
    line-height: 1.1;
}

.tabla-regante {
    border-collapse: collapse;
    width: 215px;
    border: 1.2px solid #000;
}
.tabla-regante td {
    border: 1px solid #000;
    padding: 1.5px 5px;
    font-size: 0.84rem;
}
.tabla-regante .lbl-celda {
    background: #e2e8f0;
    font-weight: bold;
    width: 50%;
    color: #000;
}
.tabla-regante .val-celda {
    text-align: center;
    font-weight: bold;
}

.titulo-tipo-agua {
    font-size: 1.20rem;
    letter-spacing: 0.04em;
    font-weight: 900;
    margin: 3px 0 !important;
}

.tabla-cuerpo, .tabla-inferior {
    border-collapse: collapse;
    font-size: 0.88rem;
}
.tabla-cuerpo td, .tabla-inferior td {
    border: 1px solid #000;
    padding: 2.5px 6px;
    line-height: 1.22;
}
.tabla-cuerpo .etiqueta, .tabla-inferior .etiqueta {
    font-weight: bold;
    color: #000;
    background: #fff;
}

/* Rejilla de Cesiones en Tanda (6 filas oficiales) */
.tabla-cesiones-tanda {
    border-collapse: collapse;
    font-size: 0.84rem;
}
.tabla-cesiones-tanda td {
    border: 1px solid #000;
    padding: 1.5px 4px;
    height: 21px;
}
.tabla-cesiones-tanda .celda-horas {
    width: 80px;
}
.tabla-cesiones-tanda .celda-unidad {
    width: 60px;
    background: #f8fafc;
}
.tabla-cesiones-tanda .celda-num {
    width: 120px;
}

.seccion-instrucciones {
    margin-top: 5px !important;
}
.seccion-instrucciones h6 {
    font-size: 0.78rem !important;
    margin-bottom: 2px !important;
}
.lista-instrucciones {
    margin-bottom: 4px !important;
    padding-left: 1.1rem !important;
}
.lista-instrucciones li {
    font-size: 0.72rem;
    line-height: 1.22;
    margin-bottom: 2px;
    text-align: justify;
}
.caja-atencion {
    border: 1.2px solid #000;
    padding: 3px 6px !important;
    margin-top: 3px;
}
.caja-atencion .fw-bold {
    font-size: 0.80rem !important;
    margin-bottom: 1px !important;
}
.caja-atencion .small {
    font-size: 0.68rem !important;
    line-height: 1.18;
}

@media print {
    body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
    .no-print, .navbar, footer, .btn, .alert { display: none !important; }
    .hoja-preview-container { padding: 0 !important; background: transparent !important; }
    .hoja-oficial { width: 100% !important; max-width: 100% !important; min-height: auto !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; page-break-inside: avoid !important; page-break-after: avoid !important; }
    .marco-principal { border: 1.5px solid #000 !important; margin-bottom: 8px !important; }
    @page { size: A4 portrait; margin: 4mm 4mm 4mm 4mm; }
}
</style>

<!-- Librería html2pdf -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
// Estado de la comunidad activa
let comunidadActiva = {
    nombre: 'Comunidad de Regantes del Río Alhárabe',
    email: 'alharabemoratalla@gmail.com',
    banco: 'CAJAMAR',
    pTanda: 0.00,
    maxTanda: 0.00,
    pCom: 12.00,
    maxCom: 0.00,
    pHort: 15.00,
    maxHort: 2.00,
    derrama: 150.00,
    horario: 'Lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00'
};

// Canvas de firma digital
const canvas = document.getElementById('canvasFirma');
const ctx = canvas.getContext('2d');
let dibujando = false;
let tieneFirma = false;

ctx.lineWidth = 2.5;
ctx.lineCap = 'round';
ctx.strokeStyle = '#091e3a';

function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
        x: (clientX - rect.left) * (canvas.width / rect.width),
        y: (clientY - rect.top) * (canvas.height / rect.height)
    };
}

canvas.addEventListener('mousedown', function(e) { dibujando = true; const pos = getPos(e); ctx.beginPath(); ctx.moveTo(pos.x, pos.y); });
canvas.addEventListener('mousemove', function(e) { if (!dibujando) return; const pos = getPos(e); ctx.lineTo(pos.x, pos.y); ctx.stroke(); tieneFirma = true; actualizarFirma(); });
window.addEventListener('mouseup', function() { dibujando = false; });

canvas.addEventListener('touchstart', function(e) { e.preventDefault(); dibujando = true; const pos = getPos(e); ctx.beginPath(); ctx.moveTo(pos.x, pos.y); }, { passive: false });
canvas.addEventListener('touchmove', function(e) { if (!dibujando) return; e.preventDefault(); const pos = getPos(e); ctx.lineTo(pos.x, pos.y); ctx.stroke(); tieneFirma = true; actualizarFirma(); }, { passive: false });
window.addEventListener('touchend', function() { dibujando = false; });

function limpiarFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    tieneFirma = false;
    document.getElementById('doc_firma_img_com').style.display = 'none';
    document.getElementById('doc_firma_img_tanda').style.display = 'none';
    document.getElementById('doc_firma_placeholder_com').style.display = 'inline-block';
    document.getElementById('doc_firma_placeholder_tanda').style.display = 'inline-block';
}

function actualizarFirma() {
    if (tieneFirma) {
        const dataUrl = canvas.toDataURL('image/png');
        const imgCom = document.getElementById('doc_firma_img_com');
        const imgTanda = document.getElementById('doc_firma_img_tanda');
        imgCom.src = dataUrl;
        imgTanda.src = dataUrl;
        imgCom.style.display = 'inline-block';
        imgTanda.style.display = 'inline-block';
        document.getElementById('doc_firma_placeholder_com').style.display = 'none';
        document.getElementById('doc_firma_placeholder_tanda').style.display = 'none';
    }
}

// Cargar Comunidad Seleccionada
function cargarComunidadSeleccionada() {
    const sel = document.getElementById('sel_comunidad');
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;

    comunidadActiva.nombre   = opt.getAttribute('data-nom') || 'Comunidad de Regantes del Río Alhárabe';
    comunidadActiva.email    = opt.getAttribute('data-email') || 'alharabemoratalla@gmail.com';
    comunidadActiva.banco    = opt.getAttribute('data-banco') || 'CAJAMAR';
    comunidadActiva.pTanda   = parseFloat(opt.getAttribute('data-ptanda')) || 0;
    comunidadActiva.maxTanda = parseFloat(opt.getAttribute('data-maxtanda')) || 0;
    comunidadActiva.pCom     = parseFloat(opt.getAttribute('data-pcom')) || 12;
    comunidadActiva.maxCom   = parseFloat(opt.getAttribute('data-maxcom')) || 0;
    comunidadActiva.pHort    = parseFloat(opt.getAttribute('data-phort')) || 15;
    comunidadActiva.maxHort  = parseFloat(opt.getAttribute('data-maxhort')) || 2;
    comunidadActiva.derrama  = parseFloat(opt.getAttribute('data-derr')) || 150;
    comunidadActiva.horario  = opt.getAttribute('data-horario') || 'Lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00';

    document.getElementById('lbl_email_destino').innerText = comunidadActiva.email;
    document.getElementById('txt_comunidad_email').innerHTML = `<i class="fa-solid fa-envelope me-1 text-primary"></i>Destino: <strong>${comunidadActiva.email}</strong>`;
    document.getElementById('in_banco').value = comunidadActiva.banco;

    // Actualizar nombre en el encabezado del documento oficial
    let shortName = comunidadActiva.nombre.replace(/^Comunidad de Regantes (del|de la|de)\s*/i, '');
    document.getElementById('doc_nombre_comunidad').innerText = shortName;
    document.getElementById('doc_instrucciones_email').innerText = comunidadActiva.email;
    if (comunidadActiva.horario) {
        document.getElementById('doc_instrucciones_horario').innerText = comunidadActiva.horario;
    }

    conmutarTipoRiego();
}

// Cargar datos al seleccionar Regante
function cargarDatosRegante() {
    const sel = document.getElementById('sel_regante');
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;

    if (opt.getAttribute('data-num')) document.getElementById('in_regante').value = opt.getAttribute('data-num');
    if (opt.getAttribute('data-nom')) document.getElementById('in_titular').value = opt.getAttribute('data-nom');
    if (opt.getAttribute('data-dni')) document.getElementById('in_dni').value = opt.getAttribute('data-dni');
    if (opt.getAttribute('data-tel')) document.getElementById('in_telefono').value = opt.getAttribute('data-tel');
    if (opt.getAttribute('data-banco')) document.getElementById('in_banco').value = opt.getAttribute('data-banco');
    if (opt.getAttribute('data-dir')) document.getElementById('in_domicilio').value = opt.getAttribute('data-dir');
    if (opt.getAttribute('data-parada')) document.getElementById('in_parada').value = opt.getAttribute('data-parada');
    if (opt.getAttribute('data-paraje')) document.getElementById('in_paraje').value = opt.getAttribute('data-paraje');
    if (opt.getAttribute('data-tablon')) document.getElementById('in_tablon').value = opt.getAttribute('data-tablon');

    document.getElementById('ces_nom_1').value = opt.getAttribute('data-nom') || '';
    document.getElementById('ces_num_1').value = opt.getAttribute('data-num') || '';

    actualizarCesiones();
    actualizarVistaPrevia();
}

// Cargar datos al seleccionar Finca
function cargarDatosFinca() {
    const sel = document.getElementById('sel_finca');
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;

    if (opt.getAttribute('data-socio')) document.getElementById('in_regante').value = opt.getAttribute('data-socio');
    if (opt.getAttribute('data-tablon')) document.getElementById('in_tablon').value = opt.getAttribute('data-tablon');
    if (opt.getAttribute('data-parada')) document.getElementById('in_parada').value = opt.getAttribute('data-parada');
    if (opt.getAttribute('data-paraje')) document.getElementById('in_paraje').value = opt.getAttribute('data-paraje');
    if (opt.getAttribute('data-titular')) document.getElementById('in_titular').value = opt.getAttribute('data-titular');
    if (opt.getAttribute('data-dni')) document.getElementById('in_dni').value = opt.getAttribute('data-dni');
    if (opt.getAttribute('data-domicilio')) document.getElementById('in_domicilio').value = opt.getAttribute('data-domicilio');
    if (opt.getAttribute('data-tel')) document.getElementById('in_telefono').value = opt.getAttribute('data-tel');

    document.getElementById('ces_nom_1').value = opt.getAttribute('data-titular') || '';
    document.getElementById('ces_num_1').value = opt.getAttribute('data-socio') || '';

    actualizarCesiones();
    actualizarVistaPrevia();
}

// Conmutar entre las 3 variedades: Tanda, Comunitaria, Comunitaria HORTALIZAS
function conmutarTipoRiego() {
    const tipo = document.getElementById('in_tipo_riego').value;
    const panelCesiones = document.getElementById('panelCesionesTanda');
    const cuerpoCom = document.getElementById('cuerpoComunitaria');
    const cuerpoTanda = document.getElementById('cuerpoTanda');
    const numPapeleta = document.getElementById('doc_indicador_num');

    let maxHoras = 0;
    let precio = 0;
    let nombreMod = '';

    if (tipo === 'tanda') {
        panelCesiones.style.display = 'block';
        cuerpoCom.style.display = 'none';
        cuerpoTanda.style.display = 'block';
        numPapeleta.style.display = 'block';
        document.getElementById('doc_titulo_tipo').innerText = 'AGUA TANDA';

        maxHoras = comunidadActiva.maxTanda;
        precio = comunidadActiva.pTanda;
        nombreMod = 'Tanda';
    } else if (tipo === 'comunitaria_hortalizas') {
        panelCesiones.style.display = 'none';
        cuerpoCom.style.display = 'block';
        cuerpoTanda.style.display = 'none';
        numPapeleta.style.display = 'none';
        document.getElementById('doc_titulo_tipo').innerText = 'AGUA COMUNITARIA';
        document.getElementById('in_observaciones').value = 'HORTALIZAS';

        maxHoras = comunidadActiva.maxHort;
        precio = comunidadActiva.pHort;
        nombreMod = 'Comunitaria HORTALIZAS';
    } else { // comunitaria ordinaria
        panelCesiones.style.display = 'none';
        cuerpoCom.style.display = 'block';
        cuerpoTanda.style.display = 'none';
        numPapeleta.style.display = 'none';
        document.getElementById('doc_titulo_tipo').innerText = 'AGUA COMUNITARIA';
        if (document.getElementById('in_observaciones').value === 'HORTALIZAS') {
            document.getElementById('in_observaciones').value = '';
        }

        maxHoras = comunidadActiva.maxCom;
        precio = comunidadActiva.pCom;
        nombreMod = 'Comunitaria Ordinaria';
    }

    // Comprobar si el consumo máximo o fijo está a cero
    const txtInfo = document.getElementById('txtConsumoInfo');
    const badgeTarifa = document.getElementById('badgeTarifaHora');
    badgeTarifa.innerText = precio.toFixed(2) + ' €/h';

    if (maxHoras > 0) {
        txtInfo.innerHTML = `<i class="fa-solid fa-lock me-2 text-warning"></i><strong>Consumo máximo/fijo pautado por la Comunidad:</strong> <span class="badge bg-warning text-dark font-monospace fs-6">${maxHoras.toFixed(2)} horas</span> (${nombreMod}).`;
        // Si las horas actuales superan el tope o están en default, proponer el cupo
        if (parseFloat(document.getElementById('in_horas').value) === 0 || tipo === 'comunitaria_hortalizas') {
            document.getElementById('in_horas').value = maxHoras.toFixed(2);
        }
    } else {
        txtInfo.innerHTML = `<i class="fa-solid fa-scale-balanced me-2 text-primary"></i><strong>Consumo según participaciones:</strong> El cupo depende de las horas asignadas según tierra o derechos del socio (sin tope fijo).`;
    }

    actualizarVistaPrevia();
}

// Asignar socio cedente en la rejilla de tanda
function seleccionarCedente(filaIndex, selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.value) return;

    document.getElementById('ces_num_' + filaIndex).value = opt.value;
    document.getElementById('ces_nom_' + filaIndex).value = opt.getAttribute('data-nom') || '';
    if (!document.getElementById('ces_h_' + filaIndex).value) {
        document.getElementById('ces_h_' + filaIndex).value = '1.0';
    }
    actualizarCesiones();
}

function actualizarCesiones() {
    let totalH = 0;
    for (let i = 1; i <= 6; i++) {
        const hInp = document.getElementById('ces_h_' + i);
        const nomInp = document.getElementById('ces_nom_' + i);
        const numInp = document.getElementById('ces_num_' + i);

        const valH = parseFloat(hInp.value) || 0;
        const valNom = nomInp.value.trim().toUpperCase();
        const valNum = numInp.value.trim().toUpperCase();

        if (valH > 0) totalH += valH;

        document.getElementById('grid_h_' + i).innerText = (valH > 0) ? valH.toFixed(1) : '';
        document.getElementById('grid_nom_' + i).innerText = valNom;
        document.getElementById('grid_num_' + i).innerText = valNum;
    }

    if (document.getElementById('in_tipo_riego').value === 'tanda') {
        document.getElementById('in_horas').value = totalH.toFixed(2);
        document.getElementById('doc_horas_tanda').innerText = totalH.toFixed(1);
    }
    actualizarVistaPrevia();
}

// Generador del nombre de archivo normalizado según las 3 variedades solicitadas
function calcularNombreArchivo() {
    const socio = (document.getElementById('in_regante').value || 'A1188').trim().replace(/[^a-zA-Z0-9]/g, '');
    const fechaRaw = document.getElementById('in_fecha').value || '<?= date("Y-m-d") ?>';
    const partes = fechaRaw.split('-'); // [YYYY, MM, DD]
    const fechaInvertidaPuntos = partes[0] + '.' + partes[1] + '.' + partes[2]; // YYYY.MM.DD
    const fechaInvertidaGuiones = partes[0] + '-' + partes[1] + '-' + partes[2]; // YYYY-MM-DD
    const fechaAlDerechoPuntos = partes[2] + '.' + partes[1] + '.' + partes[0]; // DD.MM.YYYY
    const fechaAlDerechoGuiones = partes[2] + '-' + partes[1] + '-' + partes[0]; // DD-MM-YYYY

    const titularCompleto = document.getElementById('in_titular').value.trim() || 'Pablo';
    const primerNombre = titularCompleto.split(' ')[0].toUpperCase();

    const tipo = document.getElementById('in_tipo_riego').value;
    const paraje = (document.getElementById('in_paraje').value || '').trim().replace(/[^a-zA-Z0-9]/g, '');

    // 1. VARIEDAD TANDA: [Socio]_[YYYY.MM.DD]_[PrimerNombre]_TANDA_[Paraje]_[Socio]_[DD-MM-YYYY].pdf
    if (tipo === 'tanda') {
        const pje = paraje ? ('_' + paraje) : '';
        return `${socio}_${fechaInvertidaPuntos}_${primerNombre}_TANDA${pje}_${socio}_${fechaAlDerechoGuiones}.pdf`;
    }
    // 2. VARIEDAD COMUNITARIA HORTALIZAS: [Socio]_[YYYY-MM-DD]_[PrimerNombre]_Comunitaria_Hortalizas_[Socio]_[DD.MM.YYYY].pdf
    else if (tipo === 'comunitaria_hortalizas') {
        return `${socio}_${fechaInvertidaGuiones}_${primerNombre}_Comunitaria_Hortalizas_${socio}_${fechaAlDerechoPuntos}.pdf`;
    }
    // 3. VARIEDAD COMUNITARIA ORDINARIA: [Socio]_[YYYY-MM-DD]_[PrimerNombre]_Comunitaria_[Socio]_[DD.MM.YYYY].pdf
    else {
        return `${socio}_${fechaInvertidaGuiones}_${primerNombre}_Comunitaria_${socio}_${fechaAlDerechoPuntos}.pdf`;
    }
}

// Actualizar en tiempo real el documento oficial y los cálculos económicos
function actualizarVistaPrevia() {
    const tipo = document.getElementById('in_tipo_riego').value;
    const horas = parseFloat(document.getElementById('in_horas').value || 0);

    const reganteVal = document.getElementById('in_regante').value || 'A1188';
    const tablonVal = document.getElementById('in_tablon').value || '-';
    const telVal = document.getElementById('in_telefono').value || '-';
    const titularVal = document.getElementById('in_titular').value.toUpperCase() || '-';
    const domVal = document.getElementById('in_domicilio').value || '-';
    const dniVal = document.getElementById('in_dni').value.toUpperCase() || '-';
    const bancoVal = document.getElementById('in_banco').value.toUpperCase() || comunidadActiva.banco;
    const paradaVal = document.getElementById('in_parada').value || '-';
    const parajeVal = document.getElementById('in_paraje').value || '-';
    const obsVal = document.getElementById('in_observaciones').value.toUpperCase() || '-';

    const fechaRaw = document.getElementById('in_fecha').value || '<?= date("Y-m-d") ?>';
    const partes = fechaRaw.split('-');
    const fechaFormateada = partes[2] + '/' + partes[1] + '/' + partes[0];

    // Cabecera común
    document.getElementById('doc_regante').innerText = reganteVal;
    document.getElementById('doc_tablon').innerText = tablonVal;
    document.getElementById('doc_telefono').innerText = telVal;

    // VISTA COMUNITARIA
    document.getElementById('doc_titular_com').innerText = titularVal;
    document.getElementById('doc_domicilio_com').innerText = domVal;
    document.getElementById('doc_horas_com').innerText = horas.toFixed(2);
    document.getElementById('doc_observaciones_com').innerText = obsVal;
    document.getElementById('doc_dni_com').innerText = dniVal;
    document.getElementById('doc_banco_com').innerText = bancoVal;
    document.getElementById('doc_parada_com').innerText = paradaVal;
    document.getElementById('doc_paraje_com').innerText = parajeVal;
    document.getElementById('doc_fecha_com').innerText = fechaFormateada;

    // VISTA TANDA
    document.getElementById('doc_titular_tanda').innerText = titularVal;
    document.getElementById('doc_domicilio_tanda').innerText = domVal;
    document.getElementById('doc_horas_tanda').innerText = horas.toFixed(1);
    document.getElementById('doc_parada_tanda').innerText = paradaVal;
    document.getElementById('doc_paraje_tanda').innerText = parajeVal;
    document.getElementById('doc_fecha_tanda').innerText = fechaFormateada;

    // Firmas
    const primerNombre = titularVal.split(' ')[0] || 'Regante';
    document.getElementById('doc_firma_placeholder_com').innerText = primerNombre;
    document.getElementById('doc_firma_placeholder_tanda').innerText = primerNombre;

    // Cálculo Económico según Comunidad Activa
    let coste = 0;
    let detalle = '';

    if (tipo === 'comunitaria') {
        coste = horas * comunidadActiva.pCom;
        detalle = `${horas.toFixed(2)} h × ${comunidadActiva.pCom.toFixed(2)} €/h (Comunitaria Ordinaria • ${comunidadActiva.nombre})`;
    } else if (tipo === 'comunitaria_hortalizas') {
        coste = horas * comunidadActiva.pHort;
        detalle = `${horas.toFixed(2)} h × ${comunidadActiva.pHort.toFixed(2)} €/h (Comunitaria HORTALIZAS • ${comunidadActiva.nombre})`;
    } else { // tanda
        if (comunidadActiva.pTanda > 0) {
            coste = horas * comunidadActiva.pTanda;
            detalle = `${horas.toFixed(2)} h × ${comunidadActiva.pTanda.toFixed(2)} €/h (Tanda • ${comunidadActiva.nombre})`;
        } else {
            coste = 0;
            detalle = `Horas en propiedad cubiertas por derrama comunera (${comunidadActiva.derrama.toFixed(2)} €/año en ${comunidadActiva.nombre})`;
        }
    }

    document.getElementById('box_coste_total').innerText = coste.toFixed(2) + ' €';
    document.getElementById('box_coste_detalle').innerText = detalle;

    // Nombre de archivo en tiempo real
    const fname = calcularNombreArchivo();
    document.getElementById('preview_filename').innerText = fname;
}

// Generación de PDF con html2pdf (A4 en 1 sola página exacta)
function obtenerConfigPdf(filename) {
    return {
        margin:       [4, 4, 4, 4],
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak:    { mode: ['avoid-all'] }
    };
}

// Descargar PDF en navegador (Garantizado en 1 página A4)
function descargarPDF() {
    const el = document.getElementById('documentoOficial');
    const filename = calcularNombreArchivo();
    const opt = obtenerConfigPdf(filename);
    html2pdf().set(opt).from(el).save();
}

// Recopilar datos completos del formulario para persistencia en base de datos
function recopilarFormData(action, filename = '', pdfBase64 = '') {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('id_solicitud', document.getElementById('in_id_solicitud').value || 0);
    formData.append('filename', filename || calcularNombreArchivo());
    if (pdfBase64) formData.append('pdf_base64', pdfBase64);

    formData.append('id_comunidad', document.getElementById('sel_comunidad').value || 0);
    formData.append('id_finca', document.getElementById('sel_finca').value || 0);
    formData.append('num_regante', document.getElementById('in_regante').value || 'A1188');
    formData.append('titular', document.getElementById('in_titular').value || '');
    formData.append('cif_nif', document.getElementById('in_dni').value || '');
    formData.append('telefono', document.getElementById('in_telefono').value || '');
    formData.append('domicilio', document.getElementById('in_domicilio').value || '');
    formData.append('banco', document.getElementById('in_banco').value || '');
    formData.append('tipo_riego', document.getElementById('in_tipo_riego').value || 'comunitaria');
    formData.append('horas', document.getElementById('in_horas').value || '0');
    formData.append('coste_estimado', parseFloat(document.getElementById('box_coste_total').innerText) || 0);
    formData.append('parada', document.getElementById('in_parada').value || '');
    formData.append('paraje', document.getElementById('in_paraje').value || '');
    formData.append('tablon', document.getElementById('in_tablon').value || '');
    formData.append('observaciones', document.getElementById('in_observaciones').value || '');
    formData.append('fecha_solicitud', document.getElementById('in_fecha').value || '<?= date("Y-m-d") ?>');

    formData.append('estado', document.getElementById('in_estado').value || 'pendiente');
    formData.append('fecha_riego_asignado', document.getElementById('in_fecha_asig').value || '');
    formData.append('hora_riego_asignado', document.getElementById('in_hora_asig').value || '');
    formData.append('notas_admin', document.getElementById('in_notas_admin').value || '');

    // Rejilla de Cesiones en Tanda
    const cesiones = [];
    for (let i = 1; i <= 6; i++) {
        cesiones.push({
            fila: i,
            horas: document.getElementById('ces_h_' + i) ? document.getElementById('ces_h_' + i).value : '',
            nombre: document.getElementById('ces_nom_' + i) ? document.getElementById('ces_nom_' + i).value : '',
            num_regante: document.getElementById('ces_num_' + i) ? document.getElementById('ces_num_' + i).value : ''
        });
    }
    formData.append('cesiones_json', JSON.stringify(cesiones));

    return formData;
}

// Guardar Solicitud / Parte en la Base de Datos para posterior consulta
async function guardarSolicitud(silent = false) {
    const statusBox = document.getElementById('alertEmailStatus');
    const msgBox = document.getElementById('alertEmailMsg');
    if (!silent) {
        statusBox.className = 'alert alert-info border-0 shadow-sm mb-4';
        statusBox.style.display = 'block';
        msgBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Guardando solicitud de riego en el sistema...';
    }

    try {
        const formData = recopilarFormData('guardar_solicitud');
        const resp = await fetch('<?= APP_URL ?>/solicitud-riego', {
            method: 'POST',
            body: formData
        });
        const json = await resp.json();

        if (json.success) {
            if (json.id_solicitud) {
                document.getElementById('in_id_solicitud').value = json.id_solicitud;
            }
            if (!silent) {
                statusBox.className = 'alert alert-success border-0 shadow-sm mb-4';
                msgBox.innerHTML = `<strong><i class="fa-solid fa-circle-check me-2"></i>¡Parte guardado con éxito!</strong> La solicitud ha quedado registrada en el sistema (#${json.id_solicitud || ''}). <a href="<?= APP_URL ?>/partes-riego" class="alert-link ms-2">Ver lista de partes</a>.`;
            }
            return json;
        } else {
            if (!silent) {
                statusBox.className = 'alert alert-danger border-0 shadow-sm mb-4';
                msgBox.innerHTML = `<strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Error:</strong> ${json.message}`;
            }
        }
    } catch (err) {
        if (!silent) {
            statusBox.className = 'alert alert-danger border-0 shadow-sm mb-4';
            msgBox.innerHTML = `<strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Fallo de conexión:</strong> ${err.message}`;
        }
    }
}

// Enviar solicitud directamente por correo electrónico a la comunidad seleccionada y confirmar
async function enviarPorCorreo() {
    const statusBox = document.getElementById('alertEmailStatus');
    const msgBox = document.getElementById('alertEmailMsg');
    const btn = document.getElementById('btnEnviarEmail');
    if (btn) btn.disabled = true;

    statusBox.className = 'alert alert-info border-0 shadow-sm mb-4';
    statusBox.style.display = 'block';
    msgBox.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-2"></i>Generando documento oficial (A4) y conectando con el servidor de correo (${comunidadActiva.email})...`;

    try {
        const el = document.getElementById('documentoOficial');
        const filename = calcularNombreArchivo();
        const opt = obtenerConfigPdf(filename);

        // Generar base64 oficial del documento PDF (1 página)
        const pdfBase64 = await html2pdf().set(opt).from(el).outputPdf('datauristring');

        const tipo = document.getElementById('in_tipo_riego').value.toUpperCase();
        const titular = document.getElementById('in_titular').value;
        const socio = document.getElementById('in_regante').value;
        const horas = document.getElementById('in_horas').value;

        const formData = recopilarFormData('enviar_por_correo', filename, pdfBase64);
        formData.append('destinatario', comunidadActiva.email);
        // El asunto del correo es estrictamente el nombre del archivo a enviar
        formData.append('asunto', filename);
        formData.append('cuerpo_html', `
            <h3>${comunidadActiva.nombre}</h3>
            <p>Remitimos adjunta la <strong>papeleta oficial de solicitud de agua para riego</strong> tramitada a través de AgroDietario:</p>
            <ul>
                <li><strong>Asunto / Archivo:</strong> ${filename}</li>
                <li><strong>Socio Regante:</strong> ${socio}</li>
                <li><strong>Titular:</strong> ${titular}</li>
                <li><strong>Modalidad de Riego:</strong> ${tipo}</li>
                <li><strong>Horas Solicitadas:</strong> ${horas} horas</li>
            </ul>
            <p>Rogamos confirmen recepción y asignación de turno de riego.</p>
        `);

        const resp = await fetch('<?= APP_URL ?>/solicitud-riego', {
            method: 'POST',
            body: formData
        });
        const json = await resp.json();

        if (json.success) {
            if (json.id_solicitud) {
                document.getElementById('in_id_solicitud').value = json.id_solicitud;
            }

            statusBox.className = 'alert alert-success border-0 shadow-sm mb-4';
            msgBox.innerHTML = `<strong><i class="fa-solid fa-circle-check me-2"></i>¡Papeleta enviada con éxito!</strong> El documento <code>${filename}</code> fue remitido correctamente a <strong>${comunidadActiva.email}</strong> y guardado en los partes de riego.`;

            // Actualizar indicador permanente de confirmación de envío
            const boxEnv = document.getElementById('boxEstadoEnvio');
            if (boxEnv) {
                boxEnv.style.display = 'flex';
                const detalleEnv = document.getElementById('txtEstadoEnvioDetalle');
                if (detalleEnv) {
                    const ahora = new Date().toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' });
                    detalleEnv.innerHTML = `Enviada con éxito a <strong>${comunidadActiva.email}</strong> el ${ahora} h.`;
                }
            }
        } else {
            statusBox.className = 'alert alert-danger border-0 shadow-sm mb-4';
            msgBox.innerHTML = `<strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Error al enviar:</strong> ${json.message}`;
        }
    } catch (err) {
        statusBox.className = 'alert alert-danger border-0 shadow-sm mb-4';
        msgBox.innerHTML = `<strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Fallo en el proceso:</strong> ${err.message}`;
    } finally {
        if (btn) btn.disabled = false;
    }
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    cargarComunidadSeleccionada();
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
