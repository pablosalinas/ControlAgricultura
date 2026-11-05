<!-- ========================================================================= -->
<!-- MODAL: ALTA RÁPIDA EN EL CATÁLOGO BOTÁNICO SIN SALIR DE LA VENTANA       -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalNuevoCultivoRapido" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1080;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-leaf me-2 text-warning"></i>Nueva Planta en Catálogo Botánico</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevoCultivoRapido" onsubmit="enviarGuardarCultivoRapido(event)">
                <input type="hidden" id="cr_contexto_origen" value="">
                <div class="modal-body p-4">
                    <div class="alert alert-light border py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-circle-info text-primary fs-5"></i>
                        <div>Esta nueva planta se añadirá inmediatamente al <strong>Catálogo Botánico</strong> y quedará seleccionada en tu plantación sin perder ningún dato.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nombre Común <span class="text-danger">*</span></label>
                        <input type="text" id="cr_nombre_comun" class="form-control fw-bold" placeholder="Ej: Tomate Rosa, Olivo Picual, Almendro Guara" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nombre Científico (Botánico)</label>
                            <input type="text" id="cr_nombre_cientifico" class="form-control fst-italic" placeholder="Ej: Solanum lycopersicum">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Variedad Agrícola</label>
                            <input type="text" id="cr_variedad" class="form-control" placeholder="Ej: Barbastro, Picual, Guara">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tipo de Cultivo</label>
                            <select id="cr_tipo_cultivo" class="form-select fw-semibold">
                                <option value="frutal">🍎 Frutal</option>
                                <option value="horticola" selected>🥬 Hortícola / Hortaliza</option>
                                <option value="olivar">🫒 Olivar</option>
                                <option value="citrico">🍋 Cítrico</option>
                                <option value="vinedo">🍇 Viñedo</option>
                                <option value="cereal">🌾 Cereal / Grano</option>
                                <option value="leguminosa">🫘 Leguminosa</option>
                                <option value="forraje">🌱 Forraje / Pradera</option>
                                <option value="aromatica">🌿 Aromática / Medicinal</option>
                                <option value="otro">📦 Otro Cultivo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Días de Ciclo Aprox.</label>
                            <input type="number" id="cr_dias_ciclo" class="form-control" placeholder="Ej: 90" min="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cr_es_ecologico" value="1">
                            <label class="form-check-label small fw-semibold" for="cr_es_ecologico">Cultivo apto para producción ecológica / orgánica</label>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold small">Descripción agronómica o requerimientos técnicos</label>
                        <textarea id="cr_descripcion" class="form-control" rows="2" placeholder="Requerimientos de suelo, marcos recomendados, poda..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="btnSubmitCultivoRapido">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar en Catálogo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalNuevoCultivoRapido(contexto) {
    document.getElementById('formNuevoCultivoRapido').reset();
    document.getElementById('cr_contexto_origen').value = contexto || 'general';
    document.getElementById('btnSubmitCultivoRapido').disabled = false;
    document.getElementById('btnSubmitCultivoRapido').innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar en Catálogo';

    const modalEl = document.getElementById('modalNuevoCultivoRapido');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function enviarGuardarCultivoRapido(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitCultivoRapido');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

    const contexto = document.getElementById('cr_contexto_origen').value;
    const nombreComun = document.getElementById('cr_nombre_comun').value.trim();
    const nombreCientifico = document.getElementById('cr_nombre_cientifico').value.trim();
    const variedad = document.getElementById('cr_variedad').value.trim();
    const tipoCultivo = document.getElementById('cr_tipo_cultivo').value;
    const diasCiclo = document.getElementById('cr_dias_ciclo').value;
    const esEcologico = document.getElementById('cr_es_ecologico').checked ? 1 : 0;
    const descripcion = document.getElementById('cr_descripcion').value.trim();

    const payload = {
        nombre_comun: nombreComun,
        nombre_cientifico: nombreCientifico,
        variedad: variedad,
        tipo_cultivo: tipoCultivo,
        dias_ciclo_aprox: diasCiclo ? parseInt(diasCiclo, 10) : null,
        es_ecologico: esEcologico,
        descripcion: descripcion,
        requerimientos_tecnicos: descripcion
    };

    const targetUrl = (window.APP_URL || '') + '/api/mapas?action=guardar_cultivo';

    fetch(targetUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar en Catálogo';

        if (!data.success) {
            alert(data.message || 'Error al guardar la nueva planta en el catálogo.');
            return;
        }

        const cultivo = data.cultivo;
        const textoOpt = cultivo.nombre_comun + (cultivo.variedad ? ' - ' + cultivo.variedad : '');

        // 1. Insertar y preseleccionar en selectores de la página según el contexto
        const selectores = [];
        if (contexto === 'mapa' || document.getElementById('rapida_id_cultivo')) {
            selectores.push(document.getElementById('rapida_id_cultivo'));
        }
        if (contexto === 'form_plantacion' || document.getElementById('select_id_cultivo')) {
            selectores.push(document.getElementById('select_id_cultivo'));
        }
        if (contexto === 'edit_plantacion' || document.getElementById('edit_id_cultivo')) {
            selectores.push(document.getElementById('edit_id_cultivo'));
        }

        // Además cualquier select con la clase .selector-cultivos-dinamico
        document.querySelectorAll('.selector-cultivos-dinamico').forEach(s => {
            if (!selectores.includes(s)) selectores.push(s);
        });

        selectores.forEach(sel => {
            if (sel) {
                const opt = document.createElement('option');
                opt.value = cultivo.id_cultivo;
                opt.textContent = textoOpt;
                opt.setAttribute('data-tipo', cultivo.tipo_cultivo);
                sel.appendChild(opt);
                sel.value = cultivo.id_cultivo;
                // Disparar evento change si tiene listener
                sel.dispatchEvent(new Event('change'));
            }
        });

        // 2. Si existe callback global, llamarlo
        if (typeof window.onNuevoCultivoCreado === 'function') {
            window.onNuevoCultivoCreado(cultivo);
        }

        // 3. Cerrar el modal de alta rápida de cultivo
        const modalEl = document.getElementById('modalNuevoCultivoRapido');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        // 4. Mostrar feedback al usuario
        if (typeof mostrarToast === 'function') {
            mostrarToast(`🌿 ¡"${cultivo.nombre_comun}" añadida al catálogo botánico y seleccionada!`, false, 3500);
        } else {
            // Toast o alerta temporal
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-4 shadow-lg';
            alertDiv.style.zIndex = '99999';
            alertDiv.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i>¡Planta <strong>${cultivo.nombre_comun}</strong> añadida al catálogo botánico y seleccionada con éxito!<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            document.body.appendChild(alertDiv);
            setTimeout(() => { alertDiv.remove(); }, 3500);
        }
    })
    .catch(err => {
        console.error(err);
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar en Catálogo';
        alert('Error de conexión al guardar en el catálogo botánico.');
    });
}
</script>
