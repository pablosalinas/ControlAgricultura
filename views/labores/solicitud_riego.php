<?php
$pageTitle = "Solicitud Oficial de Agua para Riego - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

// Consultar fincas del usuario para auto-rellenado inteligente
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

// Datos por defecto del usuario
$defNombre = $currentUser['nombre_completo'];
$defDni = '';
$defDomicilio = '';
$defTelefono = '';

if ($tenantId) {
    $stmtProp = $db->prepare("SELECT * FROM propietarios WHERE id_propietario = :id");
    $stmtProp->execute(array(':id' => (int)$tenantId));
    if ($pRow = $stmtProp->fetch()) {
        $defDni = $pRow['cif_nif'] ?: '';
        $defDomicilio = trim($pRow['direccion'] . ($pRow['municipio'] ? ' - ' . $pRow['municipio'] : ''));
        $defTelefono = $pRow['telefono'] ?: '';
    }
}
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
                <span class="text-muted small">Comunidad de Regantes del Río Alhárabe (Moratalla) • Agua Comunitaria y Agua de Tanda</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/riegos" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Volver a Riegos
            </a>
            <button type="button" class="btn btn-primary fw-bold shadow-sm" onclick="descargarPDF()">
                <i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF Oficial
            </button>
            <button type="button" class="btn btn-ag fw-bold shadow-sm" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Imprimir Papeleta
            </button>
        </div>
    </div>

    <!-- Panel de Cumplimentación Rápida -->
    <div class="card card-ag p-4 mb-4 border-top border-4 border-primary shadow-sm">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-sliders me-2"></i>Cumplimentar Datos de la Solicitud
            </h5>
            <span class="badge bg-primary bg-opacity-10 text-primary">Pre-rellenado automático desde tus fincas</span>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Cargar datos desde una Finca</label>
                <select id="sel_finca" class="form-select" onchange="cargarDatosFinca()">
                    <option value="">-- Seleccionar Finca para autorellenar --</option>
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

            <div class="col-md-4">
                <label class="form-label fw-semibold small">Tipo de Riego *</label>
                <select id="in_tipo_riego" class="form-select fw-bold text-primary" onchange="actualizarVistaPrevia()">
                    <option value="comunitaria" selected>💧 AGUA COMUNITARIA (Por horas disponibles según tierra)</option>
                    <option value="tanda">⏳ AGUA DE TANDA (Horas en propiedad)</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold small">Horas a Solicitar *</label>
                <input type="number" step="0.05" id="in_horas" class="form-control font-monospace fw-bold text-primary" value="0.25" oninput="actualizarVistaPrevia()">
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold small">Fecha Solicitud *</label>
                <input type="date" id="in_fecha" class="form-control font-monospace" value="<?= date('Y-m-d') ?>" oninput="actualizarVistaPrevia()">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Nº Regante (Socio)</label>
                <input type="text" id="in_regante" class="form-control font-monospace fw-bold" value="A1188" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Nº Tablón</label>
                <input type="text" id="in_tablon" class="form-control font-monospace" value="6" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Teléfono de Contacto</label>
                <input type="text" id="in_telefono" class="form-control font-monospace" value="<?= htmlspecialchars($defTelefono ?: '679873616') ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Parada</label>
                <input type="text" id="in_parada" class="form-control" value="Balsa Perales (67)" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Paraje</label>
                <input type="text" id="in_paraje" class="form-control" value="Hornillos" oninput="actualizarVistaPrevia()">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Titular (D.)</label>
                <input type="text" id="in_titular" class="form-control" value="<?= htmlspecialchars($defNombre ?: 'PABLO SALINAS MARÍN') ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Domicilio</label>
                <input type="text" id="in_domicilio" class="form-control" value="<?= htmlspecialchars($defDomicilio ?: 'C/ Virgen de Montserrat, 31 1ºB - Totana') ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">DNI / NIF</label>
                <input type="text" id="in_dni" class="form-control font-monospace" value="<?= htmlspecialchars($defDni ?: '52810184E') ?>" oninput="actualizarVistaPrevia()">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Entidad Bancaria</label>
                <input type="text" id="in_banco" class="form-control" value="CAJAMAR" oninput="actualizarVistaPrevia()">
            </div>
        </div>

        <div class="row g-3 mb-2">
            <div class="col-md-8">
                <label class="form-label fw-semibold small">Observaciones</label>
                <div class="input-group">
                    <input type="text" id="in_observaciones" class="form-control" value="HORTALIZAS" placeholder="Indicar HORTALIZAS si corresponde o notas..." oninput="actualizarVistaPrevia()">
                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('in_observaciones').value='HORTALIZAS'; actualizarVistaPrevia();">
                        Fijar "HORTALIZAS"
                    </button>
                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('in_observaciones').value=''; actualizarVistaPrevia();">
                        Borrar
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
    </div>

    <!-- Nombre del archivo generado en tiempo real -->
    <div class="alert alert-info py-2 px-3 mb-4 small d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <i class="fa-solid fa-file-export me-2"></i><strong>Nombre oficial de archivo generado:</strong>
            <code class="ms-1 fs-6 fw-bold text-dark bg-white px-2 py-1 rounded" id="preview_filename">A1188_20260915_Pablo_Comunitaria_15.09.2026.pdf</code>
        </div>
        <a id="btnMailto" href="mailto:alharabemoratalla@gmail.com" target="_blank" class="btn btn-sm btn-outline-primary fw-semibold mt-1 mt-md-0">
            <i class="fa-solid fa-paper-plane me-1"></i> Preparar Correo a la Comunidad
        </a>
    </div>

</div>

<!-- ============================================================================== -->
<!-- DOCUMENTO OFICIAL A4 PARA VISTA PREVIA, IMPRESIÓN Y GENERACIÓN DE PDF           -->
<!-- ============================================================================== -->
<div class="d-flex justify-content-center bg-secondary bg-opacity-10 py-4 py-print-0">
    <div id="documentoOficial" class="hoja-oficial bg-white shadow-lg p-5">

        <!-- Recuadro Principal del Formulario -->
        <div class="marco-principal mb-4">
            <!-- Fila Superior: Logo + Tabla Socio/Tablón/Teléfono -->
            <div class="d-flex justify-content-between align-items-start mb-2 pb-1">
                <!-- Logo Oficial Río Alhárabe -->
                <div class="logo-alharabe d-flex align-items-center">
                    <div class="logo-badge px-3 py-2 rounded-2">
                        <div class="logo-sub">Comunidad de Regantes del</div>
                        <div class="logo-main">Río Alhárabe</div>
                    </div>
                </div>

                <!-- Tabla Identificativa Superior Derecha -->
                <table class="tabla-regante">
                    <tr>
                        <td class="lbl-celda">Nº REGANTE</td>
                        <td class="val-celda font-monospace" id="doc_regante">A1188</td>
                    </tr>
                    <tr>
                        <td class="lbl-celda">Nº TABLÓN</td>
                        <td class="val-celda font-monospace" id="doc_tablon">6</td>
                    </tr>
                    <tr>
                        <td class="lbl-celda">TELÉFONO</td>
                        <td class="val-celda font-monospace" id="doc_telefono">679873616</td>
                    </tr>
                </table>
            </div>

            <!-- Título Central: AGUA COMUNITARIA / AGUA DE TANDA -->
            <div class="text-center my-2">
                <h3 class="fw-bold mb-0 text-dark titulo-tipo-agua" id="doc_titulo_tipo">AGUA COMUNITARIA</h3>
            </div>

            <!-- Datos del Solicitante -->
            <table class="tabla-cuerpo w-100">
                <tr>
                    <td class="etiqueta" style="width: 120px;">D.</td>
                    <td colspan="3" class="campo-valor" id="doc_titular">PABLO SALINAS MARÍN</td>
                </tr>
                <tr>
                    <td class="etiqueta">Domicilio</td>
                    <td colspan="3" class="campo-valor" id="doc_domicilio">C/ Virgen de Montserrat, 31 1ºB - Totana</td>
                </tr>
                <tr>
                    <td class="etiqueta">Solicita</td>
                    <td class="campo-valor" style="width: 130px; text-align: center;">
                        <span id="doc_horas" class="fw-bold font-monospace fs-5">0.25</span>
                    </td>
                    <td class="etiqueta text-center" style="width: 100px;">Horas</td>
                    <td class="bg-white"></td>
                </tr>
                <tr>
                    <td class="etiqueta">Observaciones</td>
                    <td colspan="3" class="campo-valor text-uppercase" id="doc_observaciones">HORTALIZAS</td>
                </tr>
            </table>

            <!-- Tabla Inferior: DNI, Banco, Parada, Paraje, Fecha, Firma -->
            <table class="tabla-inferior w-100">
                <tr>
                    <td class="etiqueta" style="width: 15%;">DNI</td>
                    <td class="campo-valor font-monospace" style="width: 35%;" id="doc_dni">52810184E</td>
                    <td class="etiqueta" style="width: 15%;">Banco</td>
                    <td class="campo-valor" style="width: 35%;" id="doc_banco">CAJAMAR</td>
                </tr>
                <tr>
                    <td class="etiqueta">Parada</td>
                    <td class="campo-valor" id="doc_parada">Balsa Perales (67)</td>
                    <td class="etiqueta">Paraje</td>
                    <td class="campo-valor" id="doc_paraje">Hornillos</td>
                </tr>
                <tr>
                    <td class="etiqueta">Fecha</td>
                    <td class="campo-valor font-monospace" id="doc_fecha">15/09/2026</td>
                    <td class="etiqueta">Firma</td>
                    <td class="campo-valor text-center" style="height: 65px; vertical-align: middle;">
                        <img id="doc_firma_img" src="" style="max-height: 55px; max-width: 180px; display: none;" alt="Firma">
                        <span id="doc_firma_placeholder" class="text-muted fst-italic" style="font-family:'Segoe Script',cursive,sans-serif; font-size:1.1rem; color:#1e293b;">Pablo Salinas</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Instrucciones Oficiales de la Comunidad de Regantes -->
        <div class="seccion-instrucciones mt-4 pt-2">
            <h6 class="fw-bold text-dark text-decoration-underline mb-3" style="font-size: 0.92rem; letter-spacing: 0.03em;">
                INFORMACIÓN SOBRE CUMPLIMENTACION DE PLANTILLA
            </h6>
            <ol class="lista-instrucciones ps-3 mb-4">
                <li>Todos los campos en blanco son obligatorios, excepto el de observaciones, y deben ser rellenados de forma clara y legible para una mejor identificación de los mismos.</li>
                <li>El campo de observaciones deberá cumplimentarlo en caso de utilizar la plantilla para el riego de hortalizas, en tal situación tendrá que indicar <strong>HORTALIZAS</strong> en dicho campo.</li>
                <li>Una vez hayan cumplimentado la solicitud tienen que guardar el archivo utilizando su Nº de Regante mas la fecha del envío, (Ejemplo: A0000 25.05.2012) utilizando puntos en la separación de la fecha para que Word os permita guardarlo.</li>
                <li>Una vez guardado, pueden enviarlo mediante un archivo adjunto a la dirección de e-mail de la Comunidad de Regantes que es la siguiente <strong>alharabemoratalla@gmail.com</strong></li>
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
<!-- ESTILOS CSS EXACTOS PARA EL DOCUMENTO OFICIAL A4 Y SALIDA POR IMPRESORA        -->
<!-- ============================================================================== -->
<style>
/* Hoja Oficial en pantalla: Proporción A4 exacta */
.hoja-oficial {
    width: 210mm;
    min-height: 297mm;
    margin: auto;
    font-family: 'Times New Roman', Times, serif;
    color: #000;
    box-sizing: border-box;
}

/* Marco Principal del Recuadro */
.marco-principal {
    border: 1.5px solid #000;
    padding: 14px 18px;
    background: #fff;
}

/* Logo Comunidad del Río Alhárabe */
.logo-badge {
    background: linear-gradient(135deg, #0d5c3a 0%, #177a4f 100%);
    color: #fff;
    border-radius: 6px;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.2);
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

/* Tabla Superior Derecha (Socio, Tablón, Teléfono) */
.tabla-regante {
    border-collapse: collapse;
    width: 260px;
    border: 1.2px solid #000;
}
.tabla-regante td {
    border: 1px solid #000;
    padding: 4px 8px;
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

/* Título Central de Tipo de Agua */
.titulo-tipo-agua {
    font-size: 1.45rem;
    letter-spacing: 0.05em;
    text-decoration: underline;
    font-weight: 900;
}

/* Tablas del Formulario */
.tabla-cuerpo, .tabla-inferior {
    border-collapse: collapse;
    margin-top: 6px;
    font-size: 1.05rem;
}
.tabla-cuerpo td, .tabla-inferior td {
    border: 1px solid #000;
    padding: 6px 10px;
}
.tabla-cuerpo .etiqueta, .tabla-inferior .etiqueta {
    font-weight: bold;
    color: #000;
    background: #fff;
}
.tabla-cuerpo .campo-valor, .tabla-inferior .campo-valor {
    color: #000;
    background: #fff;
}

/* Instrucciones Oficiales Inferiores */
.lista-instrucciones li {
    font-size: 0.94rem;
    line-height: 1.45;
    margin-bottom: 6px;
    text-align: justify;
}
.caja-atencion {
    border: 1.2px solid #000;
}

/* REGLAS ESTRICTAS DE IMPRESIÓN A4 */
@media print {
    body {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .no-print, .navbar, footer, .btn, .alert {
        display: none !important;
    }
    .hoja-oficial {
        width: 100% !important;
        min-height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
    }
    .marco-principal {
        border: 1.5px solid #000 !important;
        margin-bottom: 20px !important;
    }
    @page {
        size: A4 portrait;
        margin: 15mm 15mm 15mm 15mm;
    }
}
</style>

<!-- Librería para generar y descargar PDF directamente -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
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

canvas.addEventListener('mousedown', function(e) {
    dibujando = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
});

canvas.addEventListener('mousemove', function(e) {
    if (!dibujando) return;
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    tieneFirma = true;
    actualizarFirmaEnDocumento();
});

window.addEventListener('mouseup', function() { dibujando = false; });

canvas.addEventListener('touchstart', function(e) {
    e.preventDefault();
    dibujando = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
}, { passive: false });

canvas.addEventListener('touchmove', function(e) {
    if (!dibujando) return;
    e.preventDefault();
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    tieneFirma = true;
    actualizarFirmaEnDocumento();
}, { passive: false });

window.addEventListener('touchend', function() { dibujando = false; });

function limpiarFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    tieneFirma = false;
    document.getElementById('doc_firma_img').style.display = 'none';
    document.getElementById('doc_firma_placeholder').style.display = 'inline-block';
}

function actualizarFirmaEnDocumento() {
    if (tieneFirma) {
        const dataUrl = canvas.toDataURL('image/png');
        const img = document.getElementById('doc_firma_img');
        img.src = dataUrl;
        img.style.display = 'inline-block';
        document.getElementById('doc_firma_placeholder').style.display = 'none';
    }
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

    // Horas sugeridas
    const tipo = document.getElementById('in_tipo_riego').value;
    if (tipo === 'tanda' && opt.getAttribute('data-horas-prop')) {
        document.getElementById('in_horas').value = opt.getAttribute('data-horas-prop');
    } else if (tipo === 'comunitaria' && opt.getAttribute('data-horas-disp')) {
        document.getElementById('in_horas').value = opt.getAttribute('data-horas-disp');
    }

    actualizarVistaPrevia();
}

// Función para calcular el nombre del archivo según la regla solicitada:
// "identificacion del socio (A1188) + fecha invertida, nombre del socio (sólo nombre) si es tanda o comuntaria, fecha al derecho"
function calcularNombreArchivo() {
    const socio = (document.getElementById('in_regante').value.trim() || 'A1188').replace(/\s+/g, '');
    const fechaRaw = document.getElementById('in_fecha').value || '<?= date("Y-m-d") ?>';
    const partes = fechaRaw.split('-'); // [YYYY, MM, DD]
    const fechaInvertida = partes[0] + partes[1] + partes[2]; // 20260915
    const fechaAlDerecho = partes[2] + '.' + partes[1] + '.' + partes[0]; // 15.09.2026

    // Sólo el primer nombre
    const titularCompleto = document.getElementById('in_titular').value.trim() || 'Pablo';
    const primerNombre = titularCompleto.split(' ')[0] || 'Socio';

    const tipoRiego = document.getElementById('in_tipo_riego').value;
    const tipoTxt = (tipoRiego === 'tanda') ? 'Tanda' : 'Comunitaria';

    return `${socio}_${fechaInvertida}_${primerNombre}_${tipoTxt}_${fechaAlDerecho}.pdf`;
}

// Actualizar en tiempo real el documento oficial
function actualizarVistaPrevia() {
    const tipo = document.getElementById('in_tipo_riego').value;
    const tituloDoc = (tipo === 'tanda') ? 'AGUA DE TANDA' : 'AGUA COMUNITARIA';
    document.getElementById('doc_titulo_tipo').innerText = tituloDoc;

    document.getElementById('doc_regante').innerText = document.getElementById('in_regante').value || 'A1188';
    document.getElementById('doc_tablon').innerText = document.getElementById('in_tablon').value || '-';
    document.getElementById('doc_telefono').innerText = document.getElementById('in_telefono').value || '-';

    document.getElementById('doc_titular').innerText = document.getElementById('in_titular').value.toUpperCase() || '-';
    document.getElementById('doc_domicilio').innerText = document.getElementById('in_domicilio').value || '-';
    document.getElementById('doc_horas').innerText = parseFloat(document.getElementById('in_horas').value || 0).toFixed(2);
    document.getElementById('doc_observaciones').innerText = document.getElementById('in_observaciones').value.toUpperCase() || '';

    document.getElementById('doc_dni').innerText = document.getElementById('in_dni').value.toUpperCase() || '-';
    document.getElementById('doc_banco').innerText = document.getElementById('in_banco').value.toUpperCase() || 'CAJAMAR';
    document.getElementById('doc_parada').innerText = document.getElementById('in_parada').value || '-';
    document.getElementById('doc_paraje').innerText = document.getElementById('in_paraje').value || '-';

    // Fecha en formato DD/MM/AAAA
    const fechaRaw = document.getElementById('in_fecha').value || '<?= date("Y-m-d") ?>';
    const partes = fechaRaw.split('-');
    const fechaFormateada = partes[2] + '/' + partes[1] + '/' + partes[0];
    document.getElementById('doc_fecha').innerText = fechaFormateada;

    // Nombre de firma sugerida
    const primerNombre = (document.getElementById('in_titular').value.trim() || 'Pablo').split(' ')[0];
    document.getElementById('doc_firma_placeholder').innerText = primerNombre + ' Salinas';

    // Nombre de archivo calculado
    const nombreArchivo = calcularNombreArchivo();
    document.getElementById('preview_filename').innerText = nombreArchivo;

    // Actualizar enlace mailto
    const subject = encodeURIComponent(`Solicitud Riego ${tituloDoc} - Socio ${document.getElementById('in_regante').value} (${fechaFormateada})`);
    const body = encodeURIComponent(
        `Estimada Comunidad de Regantes del Río Alhárabe,\n\n` +
        `Adjunto remito solicitud oficial de riego para la finca:\n` +
        `- Tipo: ${tituloDoc}\n` +
        `- Socio: ${document.getElementById('in_regante').value}\n` +
        `- Titular: ${document.getElementById('in_titular').value}\n` +
        `- Tablón: ${document.getElementById('in_tablon').value} | Parada: ${document.getElementById('in_parada').value}\n` +
        `- Horas solicitadas: ${document.getElementById('in_horas').value} horas\n` +
        `- Fecha de riego: ${fechaFormateada}\n` +
        `- Observaciones: ${document.getElementById('in_observaciones').value}\n\n` +
        `Ruego confirmen recepción y tramitación de la papeleta.\n\n` +
        `Atentamente,\n${document.getElementById('in_titular').value}\nTel: ${document.getElementById('in_telefono').value}`
    );
    document.getElementById('btnMailto').href = `mailto:alharabemoratalla@gmail.com?subject=${subject}&body=${body}`;
}

// Descargar el PDF directamente en el navegador con el nombre exacto
function descargarPDF() {
    const elemento = document.getElementById('documentoOficial');
    const filename = calcularNombreArchivo();

    const opt = {
        margin:       [10, 10, 10, 10],
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(elemento).save();
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    actualizarVistaPrevia();
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
