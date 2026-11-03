<?php
$pageTitle = "Añadir Cultivo al Catálogo - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreComun = trim(isset($_POST['nombre_comun']) ? $_POST['nombre_comun'] : '');
    $nombreCientifico = trim(isset($_POST['nombre_cientifico']) ? $_POST['nombre_cientifico'] : '');
    $variedad = trim(isset($_POST['variedad']) ? $_POST['variedad'] : '');
    $diasCiclo = !empty($_POST['dias_ciclo_aprox']) ? (int)$_POST['dias_ciclo_aprox'] : null;
    $tipoCultivo = isset($_POST['tipo_cultivo']) ? $_POST['tipo_cultivo'] : 'frutal';
    $urlConsulta = trim(isset($_POST['url_consulta_botanica']) ? $_POST['url_consulta_botanica'] : '');
    $requerimientos = trim(isset($_POST['requerimientos_tecnicos']) ? $_POST['requerimientos_tecnicos'] : '');

    if (empty($nombreComun)) {
        $error = 'El nombre común del cultivo es obligatorio.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO cultivos (
                    nombre_comun, nombre_cientifico, variedad, dias_ciclo_aprox,
                    tipo_cultivo, url_consulta_botanica, requerimientos_tecnicos, activo
                ) VALUES (
                    :comun, :cientifico, :variedad, :ciclo,
                    :tipo, :url, :req, 1
                )
            ");
            $stmt->execute([
                ':comun'       => $nombreComun,
                ':cientifico'  => $nombreCientifico,
                ':variedad'    => $variedad,
                ':ciclo'       => $diasCiclo,
                ':tipo'        => $tipoCultivo,
                ':url'         => $urlConsulta,
                ':req'         => $requerimientos
            ]);

            header("Location: " . APP_URL . "/cultivos");
            exit;
        } catch (Exception $e) {
            $error = 'Error al registrar el cultivo: ' . $e->getMessage();
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-leaf text-success me-2"></i>Nuevo Cultivo Botánico</h2>
                    <p class="text-muted mb-0">Registra una especie o variedad con soporte de búsqueda botánica</p>
                </div>
                <a href="<?= APP_URL ?>/cultivos" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Volver al Catálogo
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="card card-custom p-4 p-md-5">
                <form action="<?= APP_URL ?>/cultivos/nuevo" method="POST">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Nombre Común <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_comun" class="form-control" required placeholder="Ej: Melocotonero, Vid, Olivo...">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Tipo de Cultivo</label>
                            <select name="tipo_cultivo" class="form-select">
                                <option value="frutal">Frutal de hueso/pepita</option>
                                <option value="citrico">Cítrico</option>
                                <option value="olivar">Olivar</option>
                                <option value="vinedo">Viñedo</option>
                                <option value="horticola">Hortícola</option>
                                <option value="cereal">Cereal / Extensivo</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Nombre Científico (Latín)</label>
                            <div class="input-group">
                                <input type="text" name="nombre_cientifico" id="nombre_cientifico" class="form-control fst-italic" placeholder="Ej: Prunus persica">
                                <button type="button" class="btn btn-outline-primary" onclick="buscarBotanica()">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar en Internet
                                </button>
                            </div>
                            <div class="form-text">Puedes pulsar 'Buscar en Internet' para contrastar la taxonomía botánica.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Variedad / Clon</label>
                            <input type="text" name="variedad" class="form-control" placeholder="Ej: Romea, Picual, Monastrell...">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Días Aproximados de Ciclo (Siembra a Cosecha)</label>
                            <input type="number" name="dias_ciclo_aprox" class="form-control" placeholder="Ej: 150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">URL de Ficha Botánica Externa (opcional)</label>
                            <input type="url" name="url_consulta_botanica" class="form-control" placeholder="https://es.wikipedia.org/wiki/...">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Requerimientos agronómicos y notas</label>
                        <textarea name="requerimientos_tecnicos" class="form-control" rows="3" placeholder="Marco de plantación recomendado, horas de frío, sensibilidad a plagas..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= APP_URL ?>/cultivos" class="btn btn-light border px-4">Cancelar</a>
                        <button type="submit" class="btn btn-ag px-4 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Cultivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function buscarBotanica() {
    const val = document.getElementById('nombre_cientifico').value.trim();
    if (!val) {
        alert('Escribe primero el nombre científico o común para buscarlo.');
        return;
    }
    const url = 'https://www.google.com/search?q=' + encodeURIComponent(val + ' cultivo agronomia taxonomica');
    window.open(url, '_blank');
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
