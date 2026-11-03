<?php
$pageTitle = "Añadir Nueva Finca - " . APP_NAME;

$db = Database::getConnection();
$tenantId = Auth::tenantId();

// Cargar parajes maestros y paradas oficiales
$parajes = $db->query("SELECT * FROM parajes WHERE (activo = 1 OR activo IS NULL) ORDER BY CASE WHEN codigo IS NOT NULL AND codigo != '' THEN 0 ELSE 1 END, codigo ASC, nombre_paraje ASC")->fetchAll();

// Cargar explotaciones agrarias
$explotaciones = $db->query("SELECT id_propietario, nombre_razon_social FROM propietarios WHERE activo = 1 ORDER BY nombre_razon_social ASC")->fetchAll();

// Cargar equivalencia de Fanegas por Hectárea desde configuración (por defecto 3.00)
$cfgFan = $db->query("SELECT fanegas_por_hectarea FROM configuracion_web LIMIT 1")->fetch();
$fanegasPorHa = (!empty($cfgFan['fanegas_por_hectarea'])) ? (float)$cfgFan['fanegas_por_hectarea'] : 3.0000;
if ($fanegasPorHa <= 0) {
    $fanegasPorHa = 3.0000;
}

// Cargar comunidades de regantes asociadas a esta explotación activa
$comunidades = array();
if ($tenantId) {
    $stmtCom = $db->prepare("
        SELECT c.id_comunidad, c.nombre_comunidad, c.es_predeterminada, c.derrama_anual
        FROM comunidades_regantes c
        JOIN explotaciones_comunidades ec ON c.id_comunidad = ec.id_comunidad
        WHERE ec.id_propietario = :prop AND c.activo = 1
        ORDER BY c.es_predeterminada DESC, c.nombre_comunidad ASC
    ");
    $stmtCom->execute(array(':prop' => $tenantId));
    $comunidades = $stmtCom->fetchAll();
}
// Fallback si no tiene vinculadas todavía o es superadmin
if (empty($comunidades)) {
    $comunidades = $db->query("SELECT id_comunidad, nombre_comunidad, es_predeterminada, derrama_anual FROM comunidades_regantes WHERE activo = 1 ORDER BY es_predeterminada DESC, nombre_comunidad ASC")->fetchAll();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim(isset($_POST['nombre_finca']) ? $_POST['nombre_finca'] : '');
    $idParaje = !empty($_POST['id_paraje']) ? (int)$_POST['id_paraje'] : null;
    $idComunidad = !empty($_POST['id_comunidad']) ? (int)$_POST['id_comunidad'] : null;
    $refCatastral = trim(isset($_POST['referencia_catastral']) ? $_POST['referencia_catastral'] : '');
    $poligono = trim(isset($_POST['poligono']) ? $_POST['poligono'] : '');
    $parcela = trim(isset($_POST['parcela']) ? $_POST['parcela'] : '');
    $superficie = (float)(isset($_POST['superficie_ha']) ? $_POST['superficie_ha'] : 0);
    $costeIbi = (float)(isset($_POST['coste_anual_ibi']) ? $_POST['coste_anual_ibi'] : 0);
    $costeDerrama = (float)(isset($_POST['coste_anual_derrama']) ? str_replace(',', '.', $_POST['coste_anual_derrama']) : 0);
    $parada = trim(isset($_POST['parada']) ? $_POST['parada'] : '');
    $tablon = trim(isset($_POST['tablon']) ? $_POST['tablon'] : '');
    $numSocio = trim(isset($_POST['num_socio_comunidad']) ? $_POST['num_socio_comunidad'] : '');
    $horasProp = (float)(isset($_POST['horas_propiedad_tanda']) ? $_POST['horas_propiedad_tanda'] : 0);
    $horasDisp = (float)(isset($_POST['horas_disponibles_tanda']) ? $_POST['horas_disponibles_tanda'] : 0);
    $datosRegistrales = trim(isset($_POST['datos_registrales']) ? $_POST['datos_registrales'] : '');
    $observaciones = trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : '');

    // Extraer el nombre de la comunidad para retrocompatibilidad con zona_riego
    $zonaRiego = '';
    if ($idComunidad) {
        foreach ($comunidades as $cr) {
            if ((int)$cr['id_comunidad'] === $idComunidad) {
                $zonaRiego = $cr['nombre_comunidad'];
                break;
            }
        }
    }
    if (empty($zonaRiego) && isset($_POST['zona_riego'])) {
        $zonaRiego = trim($_POST['zona_riego']);
    }

    // Validar propietario (explotación)
    $propietarioId = !empty($_POST['id_propietario']) ? (int)$_POST['id_propietario'] : ($tenantId ? (int)$tenantId : null);
    if (!$propietarioId) {
        $primerProp = $db->query("SELECT id_propietario FROM propietarios WHERE activo = 1 ORDER BY id_propietario ASC LIMIT 1")->fetchColumn();
        $propietarioId = $primerProp ? (int)$primerProp : 1;
    }

    if (empty($nombre)) {
        $error = 'El nombre de la finca es obligatorio.';
    } elseif ($superficie <= 0) {
        $error = 'Debes indicar una superficie válida mayor que cero en hectáreas o fanegas.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO fincas (
                    id_propietario, id_paraje, id_comunidad, nombre_finca, referencia_catastral, poligono, parcela,
                    superficie_ha, coste_anual_ibi, coste_anual_derrama, zona_riego, parada, tablon, num_socio_comunidad,
                    horas_propiedad_tanda, horas_disponibles_tanda, datos_registrales, observaciones, activo
                ) VALUES (
                    :prop, :paraje, :comunidad, :nombre, :catastro, :pol, :parc,
                    :sup, :ibi, :derrama, :zona, :parada, :tablon, :socio,
                    :hprop, :hdisp, :reg, :obs, 1
                )
            ");
            $stmt->execute(array(
                ':prop'      => $propietarioId,
                ':paraje'    => $idParaje,
                ':comunidad' => $idComunidad,
                ':nombre'    => $nombre,
                ':catastro'  => $refCatastral ?: null,
                ':pol'       => $poligono ?: null,
                ':parc'      => $parcela ?: null,
                ':sup'       => $superficie,
                ':ibi'       => $costeIbi,
                ':derrama'   => $costeDerrama,
                ':zona'      => $zonaRiego ?: null,
                ':parada'    => $parada ?: null,
                ':tablon'    => $tablon ?: null,
                ':socio'     => $numSocio ?: null,
                ':hprop'     => $horasProp,
                ':hdisp'     => $horasDisp,
                ':reg'       => $datosRegistrales ?: null,
                ':obs'       => $observaciones ?: null
            ));

            if (!headers_sent()) {
                header("Location: " . APP_URL . "/fincas");
            } else {
                echo "<script>window.location.href='" . APP_URL . "/fincas';</script>";
            }
            exit;
        } catch (Exception $e) {
            $error = 'Error al registrar la finca: ' . $e->getMessage();
        }
    }
}

require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-square-plus text-success me-2"></i>Registrar Nueva Finca</h2>
                    <p class="text-muted mb-0">Introduce los datos catastrales, registrales y derechos de agua de la parcela</p>
                </div>
                <a href="<?= APP_URL ?>/fincas" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Volver al listado
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="card card-custom p-4 p-md-5">
                <form action="<?= APP_URL ?>/fincas/nueva" method="POST">
                    
                    <!-- Sección 1: Identificación y Paraje -->
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-tag me-2"></i>1. Identificación y Localización
                    </h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Nombre de la Finca / Parcela <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_finca" class="form-control" required placeholder="Ej: Finca Las Moreras - Parcela Norte" value="<?= htmlspecialchars(isset($_POST['nombre_finca']) ? $_POST['nombre_finca'] : '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Explotación Agraria <span class="text-danger">*</span></label>
                            <select name="id_propietario" class="form-select fw-semibold" required>
                                <?php foreach ($explotaciones as $exp): ?>
                                    <option value="<?= $exp['id_propietario'] ?>" <?= ((isset($_POST['id_propietario']) && (int)$_POST['id_propietario'] === (int)$exp['id_propietario']) || (!isset($_POST['id_propietario']) && (int)$tenantId === (int)$exp['id_propietario'])) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($exp['nombre_razon_social']) ?> <?= ((int)$tenantId === (int)$exp['id_propietario']) ? '(Activa)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Paraje / Parada</label>
                            <select name="id_paraje" id="select_paraje_finca" class="form-select">
                                <option value="" data-codigo="">-- Seleccionar Paraje --</option>
                                <?php foreach ($parajes as $p): ?>
                                    <option value="<?= $p['id_paraje'] ?>" data-codigo="<?= htmlspecialchars($p['codigo'] ?: '') ?>" <?= (isset($_POST['id_paraje']) && (int)$_POST['id_paraje'] === (int)$p['id_paraje']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($p['codigo'] ? '[' . $p['codigo'] . '] ' : '') . $p['nombre_paraje'] . ' (' . $p['termino_municipal'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Sección 2: Catastro, Superficie en Hectáreas / Fanegas y Registro -->
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-landmark me-2"></i>2. Datos Catastrales y Superficie (ha / fanegas)
                    </h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Referencia Catastral (20 dígitos)</label>
                            <input type="text" name="referencia_catastral" class="form-control font-monospace" placeholder="Ej: 30019A012000450000AB" maxlength="30" value="<?= htmlspecialchars(isset($_POST['referencia_catastral']) ? $_POST['referencia_catastral'] : '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Polígono</label>
                            <input type="text" name="poligono" class="form-control font-monospace" placeholder="Ej: 12" value="<?= htmlspecialchars(isset($_POST['poligono']) ? $_POST['poligono'] : '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Parcela</label>
                            <input type="text" name="parcela" class="form-control font-monospace" placeholder="Ej: 45" value="<?= htmlspecialchars(isset($_POST['parcela']) ? $_POST['parcela'] : '') ?>">
                        </div>
                    </div>

                    <!-- Conversión bidireccional Hectáreas <-> Fanegas -->
                    <div class="card p-3 mb-3 bg-light border-0 shadow-sm">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-draw-polygon text-success me-1"></i>Superficie en Hectáreas <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" step="0.0001" min="0" name="superficie_ha" id="finca_ha" class="form-control font-monospace fs-6 fw-bold text-primary" placeholder="0.0000" value="<?= htmlspecialchars(isset($_POST['superficie_ha']) ? $_POST['superficie_ha'] : '') ?>" required oninput="syncHaToFanegas()">
                                    <span class="input-group-text fw-bold">ha</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-wheat-awn text-warning me-1"></i>Superficie en Fanegas
                                </label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" id="finca_fanegas" class="form-control font-monospace fs-6 fw-bold text-dark" placeholder="0.00" oninput="syncFanegasToHa()">
                                    <span class="input-group-text fw-bold">fanegas</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top small text-muted">
                            <div>
                                <i class="fa-solid fa-calculator text-primary me-1"></i>
                                Equivalencia configurada: <strong>1 ha = <?= number_format($fanegasPorHa, 2, ',', '.') ?> fanegas</strong> 
                                <span class="ms-1">(1 fanega &approx; <?= number_format(10000 / $fanegasPorHa, 2, ',', '.') ?> m²)</span>.
                            </div>
                            <?php if (Auth::isAdmin()): ?>
                                <a href="<?= APP_URL ?>/admin/configuracion" target="_blank" class="text-decoration-none small">
                                    <i class="fa-solid fa-gear me-1"></i>Ajustar equivalencia
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bloque Costes Fijos Anuales: IBI y Derramas Juntos -->
                    <div class="card p-3 mb-3 bg-light border-0 shadow-sm">
                        <div class="border-bottom pb-2 mb-3">
                            <span class="fw-bold text-dark"><i class="fa-solid fa-coins text-warning me-2"></i>Costes Fijos Anuales de la Finca (IBI y Derramas)</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold mb-1">
                                    <i class="fa-solid fa-receipt text-warning me-1"></i>Coste Anual IBI (€/año)
                                </label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" name="coste_anual_ibi" class="form-control font-monospace fw-bold" placeholder="0.00" value="<?= htmlspecialchars(isset($_POST['coste_anual_ibi']) ? $_POST['coste_anual_ibi'] : '0.00') ?>">
                                    <span class="input-group-text">€/año</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold mb-1">
                                    <i class="fa-solid fa-file-invoice-dollar text-success me-1"></i>Coste Anual Derrama (€/año)
                                </label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" name="coste_anual_derrama" class="form-control font-monospace fw-bold" placeholder="0.00" value="<?= htmlspecialchars(isset($_POST['coste_anual_derrama']) ? $_POST['coste_anual_derrama'] : '0.00') ?>">
                                    <span class="input-group-text">€/año</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Datos Registrales</label>
                        <input type="text" name="datos_registrales" class="form-control" placeholder="Registro de la Propiedad, Tomo, Libro, Folio, Finca registral nº..." value="<?= htmlspecialchars(isset($_POST['datos_registrales']) ? $_POST['datos_registrales'] : '') ?>">
                    </div>

                    <!-- Sección 3: Datos de Regadío y Turnos por Tanda -->
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-water me-2"></i>3. Régimen de Riegos y Comunidad
                    </h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Comunidad de Regantes de la Explotación <span class="text-danger">*</span></label>
                            <select name="id_comunidad" class="form-select" id="select_comunidad" required>
                                <option value="">-- Seleccionar Comunidad de Regantes --</option>
                                <?php foreach ($comunidades as $cr): ?>
                                    <option value="<?= $cr['id_comunidad'] ?>" <?= ((isset($_POST['id_comunidad']) && (int)$_POST['id_comunidad'] === (int)$cr['id_comunidad']) || (!isset($_POST['id_comunidad']) && !empty($cr['es_predeterminada']))) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cr['nombre_comunidad']) ?> <?= !empty($cr['es_predeterminada']) ? '(Predeterminada)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">
                                Comunidades vinculadas a tu explotación. Para asociar más, ve a <a href="<?= APP_URL ?>/explotaciones">Explotaciones</a>.
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Parada (Nº / Código)</label>
                            <input type="text" name="parada" id="in_parada" class="form-control" placeholder="Ej: 067" value="<?= htmlspecialchars(isset($_POST['parada']) ? $_POST['parada'] : '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tablón</label>
                            <input type="text" name="tablon" class="form-control" placeholder="Ej: Tablón B" value="<?= htmlspecialchars(isset($_POST['tablon']) ? $_POST['tablon'] : '') ?>">
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Número de Socio / Comunero</label>
                            <input type="text" name="num_socio_comunidad" class="form-control font-monospace" placeholder="Ej: A1188" value="<?= htmlspecialchars(isset($_POST['num_socio_comunidad']) ? $_POST['num_socio_comunidad'] : '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Horas Propiedad (Tanda)</label>
                            <div class="input-group">
                                <input type="number" step="0.25" min="0" name="horas_propiedad_tanda" class="form-control font-monospace" value="<?= htmlspecialchars(isset($_POST['horas_propiedad_tanda']) ? $_POST['horas_propiedad_tanda'] : '0.00') ?>">
                                <span class="input-group-text">horas</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Agua disponible Comunitaria</label>
                            <div class="input-group">
                                <input type="number" step="0.25" min="0" name="horas_disponibles_tanda" class="form-control font-monospace" value="<?= htmlspecialchars(isset($_POST['horas_disponibles_tanda']) ? $_POST['horas_disponibles_tanda'] : '0.00') ?>">
                                <span class="input-group-text">horas</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Observaciones o notas</label>
                        <textarea name="observaciones" class="form-control" rows="2" placeholder="Accesos, lindes, peculiaridades del terreno..."><?= htmlspecialchars(isset($_POST['observaciones']) ? $_POST['observaciones'] : '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= APP_URL ?>/fincas" class="btn btn-light border px-4">Cancelar</a>
                        <button type="submit" class="btn btn-ag px-4 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Finca
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var FANEGAS_POR_HA = <?= (float)$fanegasPorHa ?>;

function syncHaToFanegas() {
    var haVal = parseFloat(document.getElementById('finca_ha').value);
    if (!isNaN(haVal) && haVal >= 0 && FANEGAS_POR_HA > 0) {
        var fan = haVal * FANEGAS_POR_HA;
        document.getElementById('finca_fanegas').value = parseFloat(fan.toFixed(2));
    } else {
        document.getElementById('finca_fanegas').value = '';
    }
}

function syncFanegasToHa() {
    var fanVal = parseFloat(document.getElementById('finca_fanegas').value);
    if (!isNaN(fanVal) && fanVal >= 0 && FANEGAS_POR_HA > 0) {
        var ha = fanVal / FANEGAS_POR_HA;
        document.getElementById('finca_ha').value = parseFloat(ha.toFixed(4));
    } else {
        document.getElementById('finca_ha').value = '';
    }
}

// Inicializar si ya venía un valor
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('finca_ha').value) {
        syncHaToFanegas();
    }
    var selPar = document.getElementById('select_paraje_finca');
    var inPar = document.getElementById('in_parada');
    if (selPar && inPar) {
        selPar.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            var cod = opt ? opt.getAttribute('data-codigo') : '';
            if (cod && !inPar.value) {
                inPar.value = cod;
            }
        });
    }
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>