<?php
$pageTitle = "Añadir Cultivo con Asistente IA - " . APP_NAME;

$db = Database::getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreComun = trim(isset($_POST['nombre_comun']) ? $_POST['nombre_comun'] : '');
    $nombreCientifico = trim(isset($_POST['nombre_cientifico']) ? $_POST['nombre_cientifico'] : '');
    $variedad = trim(isset($_POST['variedad']) ? $_POST['variedad'] : '');
    $diasCiclo = !empty($_POST['dias_ciclo_aprox']) ? (int)$_POST['dias_ciclo_aprox'] : null;
    $tipoCultivo = isset($_POST['tipo_cultivo']) ? $_POST['tipo_cultivo'] : 'frutal';
    $esEcologico = (!empty($_POST['es_ecologico']) && $_POST['es_ecologico'] == '1') ? 1 : 0;
    $descripcion = trim(isset($_POST['descripcion']) ? $_POST['descripcion'] : '');
    $formatoSiembra = trim(isset($_POST['formato_siembra']) ? $_POST['formato_siembra'] : '');
    $epocaSiembra = trim(isset($_POST['epoca_siembra']) ? $_POST['epoca_siembra'] : '');
    $epocaTrasplante = trim(isset($_POST['epoca_trasplante']) ? $_POST['epoca_trasplante'] : '');
    $epocaRecoleccion = trim(isset($_POST['epoca_recoleccion']) ? $_POST['epoca_recoleccion'] : '');
    $tratamientosRecomendados = trim(isset($_POST['tratamientos_recomendados']) ? $_POST['tratamientos_recomendados'] : '');
    $requerimientos = trim(isset($_POST['requerimientos_tecnicos']) ? $_POST['requerimientos_tecnicos'] : '');
    $urlConsulta = trim(isset($_POST['url_consulta_botanica']) ? $_POST['url_consulta_botanica'] : '');

    if (empty($nombreComun)) {
        $error = 'El nombre común del cultivo es obligatorio.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO cultivos (
                    nombre_comun, nombre_cientifico, variedad, dias_ciclo_aprox,
                    tipo_cultivo, es_ecologico, descripcion, formato_siembra,
                    epoca_siembra, epoca_trasplante, epoca_recoleccion,
                    tratamientos_recomendados, url_consulta_botanica, requerimientos_tecnicos, activo
                ) VALUES (
                    :comun, :cien, :var, :ciclo,
                    :tipo, :eco, :desc, :formato,
                    :siem, :trasp, :recol,
                    :trat, :url, :req, 1
                )
            ");
            $stmt->execute(array(
                ':comun'    => $nombreComun,
                ':cien'     => $nombreCientifico ?: null,
                ':var'      => $variedad ?: null,
                ':ciclo'    => $diasCiclo,
                ':tipo'     => $tipoCultivo,
                ':eco'      => $esEcologico,
                ':desc'     => $descripcion ?: null,
                ':formato'  => $formatoSiembra ?: null,
                ':siem'     => $epocaSiembra ?: null,
                ':trasp'    => $epocaTrasplante ?: null,
                ':recol'    => $epocaRecoleccion ?: null,
                ':trat'     => $tratamientosRecomendados ?: null,
                ':url'      => $urlConsulta ?: null,
                ':req'      => $requerimientos ?: null
            ));

            if (!headers_sent()) {
                header("Location: " . APP_URL . "/cultivos");
            } else {
                echo "<script>window.location.href='" . APP_URL . "/cultivos';</script>";
            }
            exit;
        } catch (Exception $e) {
            $error = 'Error al registrar el cultivo: ' . $e->getMessage();
        }
    }
}

// Cargar la cabecera HTML una vez procesado el formulario
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <!-- Cabecera -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #16a34a);">
                        <i class="fa-solid fa-seedling fs-3 text-warning"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0 text-primary">Alta de Cultivo con Asistente IA</h2>
                        <p class="text-muted mb-0 small">Búsqueda agronómica inteligente, taxonomía botánica, marcos de plantación y tratamientos ecológicos</p>
                    </div>
                </div>
                <a href="<?= APP_URL ?>/cultivos" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Volver al Catálogo
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- CAJA PRINCIPAL DE BÚSQUEDA Y ASISTENTE IA -->
            <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #091e3a 0%, #1e3a8a 50%, #065f46 100%);">
                <div class="card-body p-4 text-white">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-warning text-dark font-monospace fw-bold px-3 py-1">
                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i>ASISTENTE IA AGRONÓMICO
                        </span>
                        <span class="small text-light opacity-75">Investigación Botánica y Protocolos de Cultivo</span>
                    </div>
                    <h4 class="fw-bold mb-2">¿Qué cultivo o variedad deseas dar de alta?</h4>
                    <p class="small text-light opacity-90 mb-3">
                        Escribe el nombre del cultivo (ej: <em>Almendro Guara, Tomate Muchamiel, Olivo Picual, Pimiento Lamuyo, Brócoli, Vid Monastrell, Naranjo Navelina...</em>). La IA buscará automáticamente su taxonomía científica, marcos de siembra, calendario agrícola y tratamientos adaptados.
                    </p>

                    <div class="row g-2 align-items-center">
                        <div class="col-md-7">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-0 text-success">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </span>
                                <input type="text" id="aiQuery" class="form-control border-0 fw-semibold" placeholder="Escribe el cultivo o variedad..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex gap-2">
                                <button type="button" id="btnBuscarIA" class="btn btn-warning btn-lg fw-bold flex-grow-1 shadow">
                                    <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Buscar con IA
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Interruptor de Cultivo Ecológico -->
                    <div class="mt-3 pt-3 border-top border-white border-opacity-25 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="form-check form-switch fs-6 mb-0">
                            <input class="form-check-input" type="checkbox" id="aiEsEcologico" role="switch" checked>
                            <label class="form-check-label text-white fw-bold ms-2" for="aiEsEcologico">
                                <i class="fa-solid fa-leaf text-warning me-1"></i>Cultivo Ecológico (Cuidado 100% Bio / Sin químicos de síntesis)
                            </label>
                        </div>
                        <span class="small text-light opacity-75">
                            <i class="fa-solid fa-shield-halved me-1 text-warning"></i>Aplica alternativas biológicas (Bacillus thuringiensis, cobre, azufre, neem...)
                        </span>
                    </div>

                    <!-- Alerta de estado de la IA -->
                    <div id="aiStatus" class="mt-3 d-none">
                        <div class="alert alert-info py-2 px-3 mb-0 small d-flex align-items-center gap-2 border-0 bg-white text-dark shadow-sm">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span id="aiStatusText">Consultando base agronómica y taxonomía botánica...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORMULARIO DE ALTA DE CULTIVO -->
            <div class="card card-custom p-4 p-md-5 shadow-sm border-0">
                <form action="<?= APP_URL ?>/cultivos/nuevo" method="POST" id="formCultivo">
                    
                    <!-- Sección 1: Identificación Botánica -->
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-dna me-2"></i>1. Identificación y Taxonomía Botánica
                    </h5>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Nombre Común <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_comun" id="nombre_comun" class="form-control fw-bold" required placeholder="Ej: Melocotonero, Almendro, Tomate...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Variedad / Clon</label>
                            <input type="text" name="variedad" id="variedad" class="form-control" placeholder="Ej: Guara, Picual, Muchamiel...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Tipo de Cultivo <span class="text-danger">*</span></label>
                            <select name="tipo_cultivo" id="tipo_cultivo" class="form-select fw-semibold" required>
                                <option value="frutal">Frutal (hueso / pepita)</option>
                                <option value="citrico">Cítrico</option>
                                <option value="olivar">Olivar</option>
                                <option value="vinedo">Viñedo</option>
                                <option value="horticola">Hortícola</option>
                                <option value="cereal">Cereal / Extensivo</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Nombre Científico (Latín)</label>
                            <div class="input-group">
                                <input type="text" name="nombre_cientifico" id="nombre_cientifico" class="form-control fst-italic font-monospace" placeholder="Ej: Prunus dulcis, Solanum lycopersicum...">
                                <button type="button" class="btn btn-outline-primary" onclick="abrirFichaBotanica()">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Contrastar en Red
                                </button>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Régimen Agrícola</label>
                            <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="es_ecologico" value="1" id="es_ecologico" checked>
                                    <label class="form-check-label fw-bold small text-success" for="es_ecologico">
                                        <i class="fa-solid fa-leaf me-1"></i>Cultivo Ecológico Certificado
                                    </label>
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" id="badgeEco">Bio / Eco</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Descripción y Formato de Siembra -->
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-align-left me-2"></i>2. Descripción Agronómica y Formato de Siembra
                    </h5>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Descripción apropiada del cultivo</label>
                        <textarea name="descripcion" id="descripcion" class="form-control" rows="3" placeholder="Descripción botánica y agronómica de la planta, características del fruto, porte, rusticidad..."></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Formato de Siembra / Marcos de Plantación</label>
                            <input type="text" name="formato_siembra" id="formato_siembra" class="form-control" placeholder="Ej: Marco 6x5 m (330 árboles/ha) / Líneas a 80 cm x 40 cm entre plantas, profundidad 3 cm...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Días Aproximados de Ciclo</label>
                            <div class="input-group">
                                <input type="number" name="dias_ciclo_aprox" id="dias_ciclo_aprox" class="form-control font-monospace" placeholder="Ej: 180">
                                <span class="input-group-text">días</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Calendario Agrícola -->
                    <h5 class="fw-bold text-warning border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-calendar-days me-2"></i>3. Calendario Agrícola Recomendado
                    </h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">
                                <i class="fa-solid fa-seedling text-success me-1"></i>Época Adecuada para la Siembra
                            </label>
                            <input type="text" name="epoca_siembra" id="epoca_siembra" class="form-control" placeholder="Ej: Noviembre a Febrero / Febrero-Marzo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">
                                <i class="fa-solid fa-plant-wilt text-info me-1"></i>Época Adecuada para el Trasplante
                            </label>
                            <input type="text" name="epoca_trasplante" id="epoca_trasplante" class="form-control" placeholder="Ej: Enero - Marzo (o No aplica / Siembra directa)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">
                                <i class="fa-solid fa-basket-shopping text-warning me-1"></i>Época Adecuada para Recolección
                            </label>
                            <input type="text" name="epoca_recoleccion" id="epoca_recoleccion" class="form-control" placeholder="Ej: Agosto - Septiembre / Verano">
                        </div>
                    </div>

                    <!-- Sección 4: Tratamientos y Requerimientos -->
                    <h5 class="fw-bold text-danger border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-shield-virus me-2"></i>4. Tratamientos Recomendados y Periodicidad
                    </h5>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold small mb-0">Tratamientos Fitoterapéuticos y su Periodicidad</label>
                            <span class="badge bg-light text-muted border small" id="lblTipoTrat">Alineado con el régimen ecológico</span>
                        </div>
                        <textarea name="tratamientos_recomendados" id="tratamientos_recomendados" class="form-control font-monospace small" rows="5" placeholder="Detalle de productos o materias activas (cobre, azufre, Bacillus thuringiensis, jabón potásico en ecológico), época de aplicación y periodicidad..."></textarea>
                        <div class="form-text small">
                            Si el cultivo es ecológico, se detallan exclusivamente alternativas biológicas y preventivas para no perjudicar su certificación.
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Requerimientos Técnicos (Suelo, Riego y Clima)</label>
                            <textarea name="requerimientos_tecnicos" id="requerimientos_tecnicos" class="form-control" rows="2" placeholder="Tipo de suelo calizo/franco, horas de frío, resistencia a sequía, drenaje..."></textarea>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">URL Ficha Botánica / Referencia</label>
                            <input type="url" name="url_consulta_botanica" id="url_consulta_botanica" class="form-control" placeholder="https://es.wikipedia.org/wiki/...">
                            <div class="form-text small">Enlace a Flora Ibérica, MAPA o Wikipedia.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= APP_URL ?>/cultivos" class="btn btn-light border px-4">Cancelar</a>
                        <button type="submit" class="btn btn-ag px-5 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar y Dar de Alta Cultivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Sincronizar interruptores de ecológico
document.getElementById('aiEsEcologico').addEventListener('change', function() {
    document.getElementById('es_ecologico').checked = this.checked;
    actualizarBadgeEco();
});

document.getElementById('es_ecologico').addEventListener('change', function() {
    document.getElementById('aiEsEcologico').checked = this.checked;
    actualizarBadgeEco();
});

function actualizarBadgeEco() {
    const isEco = document.getElementById('es_ecologico').checked;
    const badge = document.getElementById('badgeEco');
    const lblTrat = document.getElementById('lblTipoTrat');
    if (isEco) {
        badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
        badge.textContent = 'Bio / Ecológico';
        lblTrat.textContent = 'Protocolo 100% Ecológico (Reglamento UE 2018/848)';
    } else {
        badge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle';
        badge.textContent = 'Convencional';
        lblTrat.textContent = 'Producción Convencional / Integrada';
    }
}

// Búsqueda inteligente mediante API IA
document.getElementById('btnBuscarIA').addEventListener('click', ejecutarConsultaIA);
document.getElementById('aiQuery').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        ejecutarConsultaIA();
    }
});

async function ejecutarConsultaIA() {
    const queryEl = document.getElementById('aiQuery');
    const query = queryEl ? queryEl.value.trim() : '';
    if (!query) {
        alert('Por favor, introduce el nombre del cultivo o variedad que deseas buscar (ej: Pinus halepensis, Lechuga baby, Almendro Guara...).');
        if (queryEl) queryEl.focus();
        return;
    }

    const btn = document.getElementById('btnBuscarIA');
    const originalText = btn ? btn.innerHTML : 'Buscar con IA';
    const statusBox = document.getElementById('aiStatus');
    const isOrganic = document.getElementById('aiEsEcologico') && document.getElementById('aiEsEcologico').checked ? 1 : 0;

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Investigando...';
    }

    if (statusBox) {
        statusBox.classList.remove('d-none');
        statusBox.innerHTML = `
            <div class="alert alert-info py-2 px-3 mb-0 small d-flex align-items-center gap-2 border-0 bg-white text-dark shadow-sm">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <span>Consultando taxonomía y base botánica contrastada para '<strong>${escapeHtml(query)}</strong>'...</span>
            </div>
        `;
    }

    try {
        const formData = new FormData();
        formData.append('query', query);
        formData.append('es_ecologico', isOrganic);

        // Control de tiempo límite preventivo (máximo 10 segundos)
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);

        const response = await fetch('<?= APP_URL ?>/api/cultivos/consultar-ia', {
            method: 'POST',
            body: formData,
            signal: controller.signal
        });
        clearTimeout(timeoutId);

        const res = await response.json();

        if (res.success && res.data) {
            const d = res.data;
            if (document.getElementById('nombre_comun')) document.getElementById('nombre_comun').value = d.nombre_comun || query;
            if (document.getElementById('nombre_cientifico')) document.getElementById('nombre_cientifico').value = d.nombre_cientifico || '';
            if (document.getElementById('variedad')) document.getElementById('variedad').value = d.variedad || '';
            if (d.tipo_cultivo && document.getElementById('tipo_cultivo')) {
                document.getElementById('tipo_cultivo').value = d.tipo_cultivo;
            }
            if (document.getElementById('es_ecologico')) document.getElementById('es_ecologico').checked = (d.es_ecologico == 1);
            if (document.getElementById('aiEsEcologico')) document.getElementById('aiEsEcologico').checked = (d.es_ecologico == 1);
            actualizarBadgeEco();

            if (d.dias_ciclo_aprox && document.getElementById('dias_ciclo_aprox')) {
                document.getElementById('dias_ciclo_aprox').value = d.dias_ciclo_aprox;
            }
            if (document.getElementById('descripcion')) document.getElementById('descripcion').value = d.descripcion || '';
            if (document.getElementById('formato_siembra')) document.getElementById('formato_siembra').value = d.formato_siembra || '';
            if (document.getElementById('epoca_siembra')) document.getElementById('epoca_siembra').value = d.epoca_siembra || '';
            if (document.getElementById('epoca_trasplante')) document.getElementById('epoca_trasplante').value = d.epoca_trasplante || '';
            if (document.getElementById('epoca_recoleccion')) document.getElementById('epoca_recoleccion').value = d.epoca_recoleccion || '';
            if (document.getElementById('tratamientos_recomendados')) document.getElementById('tratamientos_recomendados').value = d.tratamientos_recomendados || '';
            if (document.getElementById('requerimientos_tecnicos')) document.getElementById('requerimientos_tecnicos').value = d.requerimientos_tecnicos || '';
            if (document.getElementById('url_consulta_botanica')) document.getElementById('url_consulta_botanica').value = d.url_consulta_botanica || '';

            if (statusBox) {
                statusBox.innerHTML = `
                    <div class="alert alert-success py-2 px-3 mb-0 small d-flex align-items-center justify-content-between border-0 bg-white text-success shadow-sm">
                        <div>
                            <i class="fa-solid fa-circle-check me-2"></i>
                            <strong>¡Cultivo contrastado con éxito!</strong> Ficha técnica cargada (${escapeHtml(d.fuente || 'Base Botánica')}). Revisa los datos y pulsa 'Guardar y Dar de Alta Cultivo'.
                        </div>
                        <button type="button" class="btn-close btn-close-sm" onclick="this.closest('#aiStatus').classList.add('d-none')"></button>
                    </div>
                `;
            }

            const formEl = document.getElementById('formCultivo');
            if (formEl) formEl.scrollIntoView({ behavior: 'smooth' });
        } else {
            throw new Error(res.message || 'No se encontró información agronómica contrastada para esta búsqueda.');
        }

    } catch (err) {
        let msg = (err.name === 'AbortError') 
            ? 'La consulta ha excedido el tiempo de espera. Inténtalo de nuevo o introduce los datos manualmente.' 
            : err.message;

        if (statusBox) {
            statusBox.innerHTML = `
                <div class="alert alert-warning py-2 px-3 mb-0 small d-flex align-items-center justify-content-between border-0 bg-white text-dark shadow-sm">
                    <div>
                        <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>
                        <span>${escapeHtml(msg)}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-sm" onclick="this.closest('#aiStatus').classList.add('d-none')"></button>
                </div>
            `;
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function abrirFichaBotanica() {
    const cient = document.getElementById('nombre_cientifico').value.trim();
    const comun = document.getElementById('nombre_comun').value.trim();
    const term = cient || comun;
    if (!term) {
        alert('Escribe el nombre común o científico para contrastar la taxonomía.');
        return;
    }
    window.open('https://www.google.com/search?q=' + encodeURIComponent(term + ' cultivo taxonomía agronomía'), '_blank');
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
