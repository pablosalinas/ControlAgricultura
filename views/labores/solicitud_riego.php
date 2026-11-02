<?php
$pageTitle = "Papeleta Oficial de Solicitud de Riego - " . APP_NAME;
require_once SRC_PATH . '/Services/Mailer.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

// Procesar envío directo por correo electrónico mediante AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enviar_por_correo') {
    header('Content-Type: application/json');
    $pdfBase64 = $_POST['pdf_base64'] ?? '';
    $filename = $_POST['filename'] ?? 'solicitud_riego.pdf';
    $destinatario = trim($_POST['destinatario'] ?? '');
    $asunto = $_POST['asunto'] ?? ("Solicitud Oficial de Riego - " . $filename);
    $cuerpoHtml = $_POST['cuerpo_html'] ?? ("<p>Adjuntamos la papeleta oficial de solicitud de riego para la Comunidad de Regantes del Río Alhárabe.</p>");

    if (empty($destinatario)) {
        // Cargar email de la comunidad de regantes configurado
        $cfgMail = $db->query("SELECT email_comunidad FROM configuracion_web LIMIT 1")->fetch();
        $destinatario = $cfgMail['email_comunidad'] ?? 'alharabemoratalla@gmail.com';
    }

    if (empty($pdfBase64)) {
        echo json_encode(['success' => false, 'message' => 'No se recibió el documento PDF generado.']);
        exit;
    }

    $cleanBase64 = preg_replace('#^data:application/pdf;base64,#', '', $pdfBase64);
    $pdfBytes = base64_decode($cleanBase64);
    if (!$pdfBytes) {
        echo json_encode(['success' => false, 'message' => 'Error al decodificar el archivo PDF adjunto.']);
        exit;
    }

    $tempFile = sys_get_temp_dir() . '/' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
    file_put_contents($tempFile, $pdfBytes);

    $res = Mailer::send($destinatario, $asunto, $cuerpoHtml, [$tempFile]);
    @unlink($tempFile);

    echo json_encode($res);
    exit;
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

// Cargar precios y tarifas activas
$cfgWeb = $db->query("SELECT * FROM configuracion_web LIMIT 1")->fetch();
$precioHoraComunitaria = (float)($cfgWeb['precio_hora_comunitaria'] ?? 12.00);
$precioHoraHortalizas  = (float)($cfgWeb['precio_hora_hortalizas'] ?? 15.00);
$derramaAnualComunidad = (float)($cfgWeb['derrama_anual_comunidad'] ?? 150.00);
$emailComunidadOficial = $cfgWeb['email_comunidad'] ?? 'alharabemoratalla@gmail.com';

// Si la explotación actual tiene tarifas personalizadas, sobreescribir
if ($tenantId) {
    $stmtProp = $db->prepare("SELECT * FROM propietarios WHERE id_propietario = :id");
    $stmtProp->execute([':id' => (int)$tenantId]);
    if ($pRow = $stmtProp->fetch()) {
        if (!empty($pRow['precio_hora_comunitaria'])) $precioHoraComunitaria = (float)$pRow['precio_hora_comunitaria'];
        if (!empty($pRow['precio_hora_hortalizas']))  $precioHoraHortalizas  = (float)$pRow['precio_hora_hortalizas'];
        if (!empty($pRow['derrama_anual_comunidad'])) $derramaAnualComunidad = (float)$pRow['derrama_anual_comunidad'];
    }
}

// Datos predeterminados del socio inicial
$reganteInicial = !empty($regantes) ? $regantes[0] : null;
$preReganteId = (int)($_GET['regante_id'] ?? 0);
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
$defBanco = $reganteInicial ? ($reganteInicial['banco_habitual'] ?: 'CAJAMAR') : 'CAJAMAR';
?>

<div class="container py-4 no-print">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-file-invoice fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Papeleta Oficial de Solicitud de Riego</h2>
                <span class="text-muted small">Comunidad de Regantes del Río Alhárabe (Moratalla) • Formatos Oficiales: Comunitaria y Tanda con Cesiones</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= APP_URL ?>/regantes" class="btn btn-outline-secondary">
                <i class="fa-solid fa-address-book me-1"></i> Censo de Socios
            </a>
            <button type="button" class="btn btn-primary fw-bold shadow-sm" onclick="descargarPDF()">
                <i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF
            </button>
            <button type="button" class="btn btn-success fw-bold shadow-sm" onclick="enviarPorCorreo()">
                <i class="fa-solid fa-paper-plane me-1"></i> Enviar por Email a la Comunidad
            </button>
            <button type="button" class="btn btn-ag fw-bold shadow-sm" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- Panel de Cumplimentación Simplificado -->
    <div class="card card-custom p-4 mb-4 border-top border-4 border-primary shadow-sm">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-sliders me-2"></i>Cumplimentación Ultra Rápida
            </h5>
            <span class="badge bg-primary bg-opacity-10 text-primary">Selecciona Socio o Finca para auto-rellenar</span>
        </div>

        <!-- Fila de Autorellenado Inteligente -->
        <div class="row g-3 mb-3 bg-light p-3 rounded-3 border">
            <div class="col-md-6">
                <label class="form-label fw-bold small text-primary"><i class="fa-solid fa-user-check me-1"></i>Seleccionar Socio Regante (Censo Oficial)</label>
                <select id="sel_regante" class="form-select font-monospace" onchange="cargarDatosRegante()">
                    <option value="">-- Buscar socio regante por Nº o Nombre --</option>
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
                            <?= htmlspecialchars($rg['num_regante']) ?> - <?= htmlspecialchars($rg['nombre_completo']) ?> (Tanda: <?= $rg['horas_tanda_disponibles'] ?>h | Com: <?= $rg['horas_comunitaria_disponibles'] ?>h)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-primary"><i class="fa-solid fa-layer-group me-1"></i>O Seleccionar desde una de tus Fincas</label>
                <select id="sel_finca" class="form-select" onchange="cargarDatosFinca()">
                    <option value="">-- Cargar Finca para fijar Parada y Paraje --</option>
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
                            <?= htmlspecialchars($f['nombre_finca']) ?> (Socio: <?= htmlspecialchars($f['num_socio_comunidad'] ?: 'A1188') ?> | Parada: <?= htmlspecialchars($f['parada'] ?: '-') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Tipo de Riego y Modalidad -->
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold small">1. Formato de Papeleta / Tipo de Riego *</label>
                <select id="in_tipo_riego" class="form-select fw-bold text-primary" onchange="conmutarTipoRiego()">
                    <option value="comunitaria" selected>💧 AGUA COMUNITARIA (Por horas según tierra)</option>
                    <option value="tanda">⏳ AGUA TANDA (Con cesiones de otros socios)</option>
                </select>
            </div>

            <div class="col-md-3" id="col_subtipo_comunitaria">
                <label class="form-label fw-semibold small">Modalidad Comunitaria *</label>
                <select id="in_subtipo_comunitaria" class="form-select fw-semibold" onchange="conmutarSubtipoComunitaria()">
                    <option value="normal" selected>Comunitaria Ordinaria (<?= number_format($precioHoraComunitaria, 2) ?> €/h)</option>
                    <option value="hortalizas">⭐ COMUNITARIA HORTALIZAS (<?= number_format($precioHoraHortalizas, 2) ?> €/h)</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold small">Horas Totales *</label>
                <input type="number" step="0.25" id="in_horas" class="form-control font-monospace fw-bold text-primary fs-5" value="2.00" oninput="actualizarVistaPrevia()">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold small">Fecha de Riego *</label>
                <input type="date" id="in_fecha" class="form-control font-monospace" value="<?= date('Y-m-d') ?>" oninput="actualizarVistaPrevia()">
            </div>
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
                <label class="form-label fw-semibold small">Teléfono de Contacto</label>
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
                    <i class="fa-solid fa-users me-1"></i>Distribución de Horas y Cesiones de Otros Socios (Plantilla Tanda)
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
                        <!-- Filas 2 a 6: Cesiones -->
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
                <div class="form-text small">Obligatorio indicar HORTALIZAS si se solicita riego de fin de semana en verano.</div>
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
    </div>

    <!-- Valoración Económica y Nombre Oficial del Archivo -->
    <div class="row g-3 mb-4">
        <!-- Tarjeta de Coste Estimado -->
        <div class="col-md-6">
            <div class="card card-custom p-3 border-start border-4 border-success h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Valoración Económica del Riego</div>
                        <div class="fs-4 fw-bold text-success" id="box_coste_total">24.00 €</div>
                        <div class="small text-muted" id="box_coste_detalle">2.00 h × <?= number_format($precioHoraComunitaria, 2) ?> €/h (Comunitaria)</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="fa-solid fa-euro-sign fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Nombre del Archivo y Envío -->
        <div class="col-md-6">
            <div class="card card-custom p-3 border-start border-4 border-primary h-100 d-flex justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Nombre de Archivo Normalizado (Río Alhárabe)</div>
                    <code class="fs-6 fw-bold text-dark bg-light px-2 py-1 rounded d-block mt-1 text-truncate" id="preview_filename">A1188_2026-09-15_PABLO_Comunitaria_15.09.2026.pdf</code>
                </div>
                <div class="small text-muted mt-2">
                    <i class="fa-solid fa-envelope me-1 text-primary"></i>Destino: <strong><?= htmlspecialchars($emailComunidadOficial) ?></strong>
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
<div class="d-flex justify-content-center bg-secondary bg-opacity-10 py-4 py-print-0">
    <div id="documentoOficial" class="hoja-oficial bg-white shadow-lg p-5">

        <!-- Recuadro Principal del Formulario -->
        <div class="marco-principal mb-4">
            <!-- Fila Superior: Logo + Nº papeleta + Tabla Socio/Tablón/Teléfono -->
            <div class="d-flex justify-content-between align-items-start mb-2 pb-1">
                <!-- Logo Oficial Río Alhárabe -->
                <div class="logo-alharabe d-flex align-items-center">
                    <div class="logo-badge px-3 py-2 rounded-2">
                        <div class="logo-sub">Comunidad de Regantes del</div>
                        <div class="logo-main">Río Alhárabe</div>
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

            <!-- VISTA 1: CUERPO FORMATO AGUA COMUNITARIA -->
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

            <!-- VISTA 2: CUERPO FORMATO AGUA TANDA CON CESIONES (Exacto imagen de usuario) -->
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
                <li>Una vez guardado, pueden enviarlo mediante un archivo adjunto a la dirección de e-mail de la Comunidad de Regantes que es la siguiente <strong><?= htmlspecialchars($emailComunidadOficial) ?></strong>.</li>
                <li>Todas las solicitudes informáticas deberán ser enviadas con un mínimo de 3 a 4 días de antelación a los riegos al igual que las papeletas físicas para poder imprimirlas y colocarlas con el resto.</li>
                <li>En caso de dudas pueden ponerse en contacto con la oficina de la Comunidad de lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00.</li>
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
<!-- ESTILOS EXACTOS PARA DOCUMENTO Y SALIDA DE IMPRESIÓN A4                         -->
<!-- ============================================================================== -->
<style>
.hoja-oficial {
    width: 210mm;
    min-height: 297mm;
    margin: auto;
    font-family: 'Times New Roman', Times, serif;
    color: #000;
    box-sizing: border-box;
}

.marco-principal {
    border: 1.5px solid #000;
    padding: 14px 18px;
    background: #fff;
}

.logo-badge {
    background: linear-gradient(135deg, #0d5c3a 0%, #177a4f 100%);
    color: #fff;
    border-radius: 6px;
    min-width: 230px;
}
.logo-sub {
    font-size: 0.72rem;
    font-family: 'Arial', sans-serif;
    letter-spacing: 0.05em;
    opacity: 0.9;
}
.logo-main {
    font-size: 1.55rem;
    font-weight: 800;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: #5eead4;
    letter-spacing: -0.02em;
}

.tabla-regante {
    border-collapse: collapse;
    width: 260px;
    border: 1.2px solid #000;
}
.tabla-regante td {
    border: 1px solid #000;
    padding: 3px 8px;
    font-size: 0.95rem;
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
    font-size: 1.45rem;
    letter-spacing: 0.04em;
    font-weight: 900;
}

.tabla-cuerpo, .tabla-inferior {
    border-collapse: collapse;
    font-size: 1.05rem;
}
.tabla-cuerpo td, .tabla-inferior td {
    border: 1px solid #000;
    padding: 5px 10px;
}
.tabla-cuerpo .etiqueta, .tabla-inferior .etiqueta {
    font-weight: bold;
    color: #000;
    background: #fff;
}

/* Tabla de Cesiones de Horas en Tanda (Rejilla de 6 filas) */
.tabla-cesiones-tanda {
    border-collapse: collapse;
    font-size: 1.02rem;
}
.tabla-cesiones-tanda td {
    border: 1px solid #000;
    padding: 4px 6px;
    height: 32px;
}
.tabla-cesiones-tanda .celda-horas {
    width: 90px;
}
.tabla-cesiones-tanda .celda-unidad {
    width: 70px;
    background: #f8fafc;
}
.tabla-cesiones-tanda .celda-num {
    width: 140px;
}

.lista-instrucciones li {
    font-size: 0.92rem;
    line-height: 1.4;
    margin-bottom: 5px;
    text-align: justify;
}
.caja-atencion {
    border: 1.2px solid #000;
}

@media print {
    body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
    .no-print, .navbar, footer, .btn, .alert { display: none !important; }
    .hoja-oficial { width: 100% !important; min-height: auto !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; }
    .marco-principal { border: 1.5px solid #000 !important; margin-bottom: 15px !important; }
    @page { size: A4 portrait; margin: 12mm 12mm 12mm 12mm; }
}
</style>

<!-- Librería html2pdf para generación fiel en cliente -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
// Precios configurados
const PRECIO_COMUNITARIA = <?= $precioHoraComunitaria ?>;
const PRECIO_HORTALIZAS  = <?= $precioHoraHortalizas ?>;
const DERRAMA_ANUAL      = <?= $derramaAnualComunidad ?>;
const EMAIL_DESTINO      = '<?= $emailComunidadOficial ?>';

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

    // Asignar primera fila de cesión
    document.getElementById('ces_nom_1').value = opt.getAttribute('data-nom') || '';
    document.getElementById('ces_num_1').value = opt.getAttribute('data-num') || '';

    // Horas disponibles según tipo
    const tipo = document.getElementById('in_tipo_riego').value;
    if (tipo === 'tanda' && opt.getAttribute('data-ht')) {
        document.getElementById('in_horas').value = opt.getAttribute('data-ht');
    } else if (tipo === 'comunitaria' && opt.getAttribute('data-hc')) {
        document.getElementById('in_horas').value = opt.getAttribute('data-hc');
    }

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

// Conmutar entre formato Comunitaria y formato Tanda
function conmutarTipoRiego() {
    const tipo = document.getElementById('in_tipo_riego').value;
    const colSub = document.getElementById('col_subtipo_comunitaria');
    const panelCesiones = document.getElementById('panelCesionesTanda');
    const cuerpoCom = document.getElementById('cuerpoComunitaria');
    const cuerpoTanda = document.getElementById('cuerpoTanda');
    const numPapeleta = document.getElementById('doc_indicador_num');

    if (tipo === 'tanda') {
        colSub.style.display = 'none';
        panelCesiones.style.display = 'block';
        cuerpoCom.style.display = 'none';
        cuerpoTanda.style.display = 'block';
        numPapeleta.style.display = 'block';
        document.getElementById('doc_titulo_tipo').innerText = 'AGUA TANDA';
    } else {
        colSub.style.display = 'block';
        panelCesiones.style.display = 'none';
        cuerpoCom.style.display = 'block';
        cuerpoTanda.style.display = 'none';
        numPapeleta.style.display = 'none';
        conmutarSubtipoComunitaria();
    }
    actualizarVistaPrevia();
}

function conmutarSubtipoComunitaria() {
    const sub = document.getElementById('in_subtipo_comunitaria').value;
    if (sub === 'hortalizas') {
        document.getElementById('in_observaciones').value = 'HORTALIZAS';
        document.getElementById('doc_titulo_tipo').innerText = 'AGUA COMUNITARIA';
    } else {
        if (document.getElementById('in_observaciones').value === 'HORTALIZAS') {
            document.getElementById('in_observaciones').value = '';
        }
        document.getElementById('doc_titulo_tipo').innerText = 'AGUA COMUNITARIA';
    }
    actualizarVistaPrevia();
}

// Asignar datos de socio cedente desde el selector
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

// Sincronizar cesiones con el documento y calcular total
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

// Nombre oficial exacto según instrucción del usuario
function calcularNombreArchivo() {
    const socio = (document.getElementById('in_regante').value || 'A1188').trim().replace(/[^a-zA-Z0-9]/g, '');
    const fechaRaw = document.getElementById('in_fecha').value || '<?= date("Y-m-d") ?>';
    const partes = fechaRaw.split('-'); // [YYYY, MM, DD]
    const fechaInvertida = partes[0] + '.' + partes[1] + '.' + partes[2]; // YYYY.MM.DD
    const fechaAlDerechoPuntos = partes[2] + '.' + partes[1] + '.' + partes[0]; // DD.MM.YYYY
    const fechaAlDerechoGuiones = partes[2] + '-' + partes[1] + '-' + partes[0]; // DD-MM-YYYY

    const titularCompleto = document.getElementById('in_titular').value.trim() || 'Pablo';
    const primerNombre = titularCompleto.split(' ')[0].toUpperCase();

    const tipo = document.getElementById('in_tipo_riego').value;
    const paraje = (document.getElementById('in_paraje').value || '').trim().replace(/[^a-zA-Z0-9]/g, '');

    if (tipo === 'tanda') {
        // Formato exacto del adjunto de tanda: A0694_2026.06.12_PACO_TANDA_Perales_A0694_12-06-2026.pdf
        const pje = paraje ? ('_' + paraje) : '';
        return `${socio}_${fechaInvertida}_${primerNombre}_TANDA${pje}_${socio}_${fechaAlDerechoGuiones}.pdf`;
    } else {
        const sub = document.getElementById('in_subtipo_comunitaria').value;
        const tipoCom = (sub === 'hortalizas') ? 'Comunitaria_Hortalizas' : 'Comunitaria';
        // Formato exacto del adjunto comunitaria: A1188_2026-09-15_PABLO_Comunitaria_Hortalizas_A1188_15.09.2026.pdf
        const fInvGuiones = partes[0] + '-' + partes[1] + '-' + partes[2];
        return `${socio}_${fInvGuiones}_${primerNombre}_${tipoCom}_${socio}_${fechaAlDerechoPuntos}.pdf`;
    }
}

// Actualizar en tiempo real
function actualizarVistaPrevia() {
    const tipo = document.getElementById('in_tipo_riego').value;
    const sub = document.getElementById('in_subtipo_comunitaria').value;
    const horas = parseFloat(document.getElementById('in_horas').value || 0);

    const reganteVal = document.getElementById('in_regante').value || 'A1188';
    const tablonVal = document.getElementById('in_tablon').value || '-';
    const telVal = document.getElementById('in_telefono').value || '-';
    const titularVal = document.getElementById('in_titular').value.toUpperCase() || '-';
    const domVal = document.getElementById('in_domicilio').value || '-';
    const dniVal = document.getElementById('in_dni').value.toUpperCase() || '-';
    const bancoVal = document.getElementById('in_banco').value.toUpperCase() || 'CAJAMAR';
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

    // Valoración Económica
    let coste = 0;
    let detalle = '';
    if (tipo === 'comunitaria') {
        if (sub === 'hortalizas') {
            coste = horas * PRECIO_HORTALIZAS;
            detalle = `${horas.toFixed(2)} h × ${PRECIO_HORTALIZAS.toFixed(2)} €/h (Comunitaria HORTALIZAS Fin de Semana)`;
        } else {
            coste = horas * PRECIO_COMUNITARIA;
            detalle = `${horas.toFixed(2)} h × ${PRECIO_COMUNITARIA.toFixed(2)} €/h (Comunitaria Ordinaria)`;
        }
    } else {
        coste = 0;
        detalle = `Horas en propiedad cubiertas por derrama comunera (${DERRAMA_ANUAL.toFixed(2)} €/año)`;
    }
    document.getElementById('box_coste_total').innerText = coste.toFixed(2) + ' €';
    document.getElementById('box_coste_detalle').innerText = detalle;

    // Nombre archivo
    const fname = calcularNombreArchivo();
    document.getElementById('preview_filename').innerText = fname;
}

// Generación de PDF con html2pdf
function generarPdfPromise() {
    const el = document.getElementById('documentoOficial');
    const fname = calcularNombreArchivo();
    const opt = {
        margin:       [10, 10, 10, 10],
        filename:     fname,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    return html2pdf().set(opt).from(el);
}

// Descargar PDF en cliente
function descargarPDF() {
    generarPdfPromise().save();
}

// Enviar solicitud por correo directamente al servidor de la Comunidad
async function enviarPorCorreo() {
    const statusBox = document.getElementById('alertEmailStatus');
    const msgBox = document.getElementById('alertEmailMsg');
    statusBox.className = 'alert alert-info border-0 shadow-sm mb-4';
    statusBox.style.display = 'block';
    msgBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Generando documento oficial y conectando con el servidor de correo IONOS...';

    try {
        const worker = generarPdfPromise();
        const pdfBase64 = await worker.outputPdf('datauristring');
        const filename = calcularNombreArchivo();
        const tipo = document.getElementById('in_tipo_riego').value;
        const titular = document.getElementById('in_titular').value;
        const socio = document.getElementById('in_regante').value;
        const horas = document.getElementById('in_horas').value;

        const formData = new FormData();
        formData.append('action', 'enviar_por_correo');
        formData.append('filename', filename);
        formData.append('pdf_base64', pdfBase64);
        formData.append('destinatario', EMAIL_DESTINO);
        formData.append('asunto', `Solicitud de Riego (${tipo.toUpperCase()}) - Socio ${socio} (${titular})`);
        formData.append('cuerpo_html', `
            <h3>Comunidad de Regantes del Río Alhárabe</h3>
            <p>Remitimos adjunta la <strong>papeleta oficial de solicitud de agua para riego</strong> tramitada a través de AgroDietario:</p>
            <ul>
                <li><strong>Socio Regante:</strong> ${socio}</li>
                <li><strong>Titular:</strong> ${titular}</li>
                <li><strong>Tipo de Riego:</strong> ${tipo.toUpperCase()}</li>
                <li><strong>Horas Solicitadas:</strong> ${horas} horas</li>
                <li><strong>Archivo Adjunto:</strong> ${filename}</li>
            </ul>
            <p>Rogamos confirmen recepción y asignación de turno.</p>
        `);

        const resp = await fetch('<?= APP_URL ?>/solicitud-riego', {
            method: 'POST',
            body: formData
        });
        const json = await resp.json();

        if (json.success) {
            statusBox.className = 'alert alert-success border-0 shadow-sm mb-4';
            msgBox.innerHTML = `<strong><i class="fa-solid fa-circle-check me-2"></i>¡Papeleta enviada con éxito!</strong> El documento <code>${filename}</code> fue remitido correctamente a <strong>${EMAIL_DESTINO}</strong>.`;
        } else {
            statusBox.className = 'alert alert-danger border-0 shadow-sm mb-4';
            msgBox.innerHTML = `<strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Error al enviar:</strong> ${json.message}`;
        }
    } catch (err) {
        statusBox.className = 'alert alert-danger border-0 shadow-sm mb-4';
        msgBox.innerHTML = `<strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Fallo en el proceso:</strong> ${err.message}`;
    }
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    conmutarTipoRiego();
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
