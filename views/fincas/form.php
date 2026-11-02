<?php
$pageTitle = "Añadir Nueva Finca - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();

// Cargar parajes maestros
$parajes = $db->query("SELECT * FROM parajes ORDER BY nombre_paraje ASC")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_finca'] ?? '');
    $idParaje = !empty($_POST['id_paraje']) ? (int)$_POST['id_paraje'] : null;
    $refCatastral = trim($_POST['referencia_catastral'] ?? '');
    $poligono = trim($_POST['poligono'] ?? '');
    $parcela = trim($_POST['parcela'] ?? '');
    $superficie = (float)($_POST['superficie_ha'] ?? 0);
    $costeIbi = (float)($_POST['coste_anual_ibi'] ?? 0);
    $zonaRiego = trim($_POST['zona_riego'] ?? '');
    $parada = trim($_POST['parada'] ?? '');
    $tablon = trim($_POST['tablon'] ?? '');
    $numSocio = trim($_POST['num_socio_comunidad'] ?? '');
    $horasProp = (float)($_POST['horas_propiedad_tanda'] ?? 0);
    $horasDisp = (float)($_POST['horas_disponibles_tanda'] ?? 0);
    $datosRegistrales = trim($_POST['datos_registrales'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (empty($nombre)) {
        $error = 'El nombre de la finca es obligatorio.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO fincas (
                    id_propietario, id_paraje, nombre_finca, referencia_catastral, poligono, parcela,
                    superficie_ha, coste_anual_ibi, zona_riego, parada, tablon, num_socio_comunidad,
                    horas_propiedad_tanda, horas_disponibles_tanda, datos_registrales, observaciones
                ) VALUES (
                    :prop, :paraje, :nombre, :catastro, :pol, :parc,
                    :sup, :ibi, :zona, :parada, :tablon, :socio,
                    :hprop, :hdisp, :reg, :obs
                )
            ");
            $stmt->execute([
                ':prop'    => $tenantId ?: 1,
                ':paraje'  => $idParaje,
                ':nombre'  => $nombre,
                ':catastro'=> $refCatastral,
                ':pol'     => $poligono,
                ':parc'    => $parcela,
                ':sup'     => $superficie,
                ':ibi'     => $costeIbi,
                ':zona'    => $zonaRiego,
                ':parada'  => $parada,
                ':tablon'  => $tablon,
                ':socio'   => $numSocio,
                ':hprop'   => $horasProp,
                ':hdisp'   => $horasDisp,
                ':reg'     => $datosRegistrales,
                ':obs'     => $observaciones
            ]);

            header("Location: " . APP_URL . "/fincas");
            exit;
        } catch (Exception $e) {
            $error = 'Error al guardar la finca: ' . $e->getMessage();
        }
    }
}
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
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Nombre de la Finca / Parcela <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_finca" class="form-control" required placeholder="Ej: Finca Las Moreras - Parcela Norte">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Paraje</label>
                            <select name="id_paraje" class="form-select">
                                <option value="">-- Seleccionar Paraje --</option>
                                <?php foreach ($parajes as $p): ?>
                                    <option value="<?= $p['id_paraje'] ?>">
                                        <?= htmlspecialchars($p['nombre_paraje'] . ' (' . $p['termino_municipal'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Sección 2: Catastro y Registro -->
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-landmark me-2"></i>2. Datos Catastrales y Registrales
                    </h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Referencia Catastral (20 dígitos)</label>
                            <input type="text" name="referencia_catastral" class="form-control" placeholder="Ej: 30019A012000450000AB" maxlength="30">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Polígono</label>
                            <input type="text" name="poligono" class="form-control" placeholder="Ej: 12">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Parcela</label>
                            <input type="text" name="parcela" class="form-control" placeholder="Ej: 45">
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Superficie Total (Hectáreas)</label>
                            <div class="input-group">
                                <input type="number" step="0.0001" name="superficie_ha" class="form-control" placeholder="0.0000" required>
                                <span class="input-group-text">ha</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Coste Anual IBI (€/año)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="coste_anual_ibi" class="form-control font-monospace" placeholder="0.00" value="0.00">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Datos Registrales</label>
                            <input type="text" name="datos_registrales" class="form-control" placeholder="Registro de la Propiedad, Tomo, Folio...">
                        </div>
                    </div>

                    <!-- Sección 3: Datos de Regadío y Turnos por Tanda -->
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-water me-2"></i>3. Régimen de Riegos y Tanda
                    </h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Comunidad de Regantes / Zona de Riego</label>
                            <input type="text" name="zona_riego" class="form-control" placeholder="Ej: C.R. Vega Alta / Sector V">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Parada</label>
                            <input type="text" name="parada" class="form-control" placeholder="Ej: Parada 4">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tablón</label>
                            <input type="text" name="tablon" class="form-control" placeholder="Ej: Tablón B">
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Número de Socio / Comunero</label>
                            <input type="text" name="num_socio_comunidad" class="form-control" placeholder="Ej: 1420">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Horas en Propiedad (por tanda)</label>
                            <div class="input-group">
                                <input type="number" step="0.25" name="horas_propiedad_tanda" class="form-control" value="0.00">
                                <span class="input-group-text">horas</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Horas Disponibles (por tanda)</label>
                            <div class="input-group">
                                <input type="number" step="0.25" name="horas_disponibles_tanda" class="form-control" value="0.00">
                                <span class="input-group-text">horas</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Observaciones o notas</label>
                        <textarea name="observaciones" class="form-control" rows="2" placeholder="Accesos, lindes, peculiaridades del terreno..."></textarea>
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

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
