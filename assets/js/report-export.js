/**
 * ReportExport - Gestor Universal de Impresión, Visualización, Exportación a PDF y Envío por Email
 * Optimizado para formato A4 (aprovechando al máximo el espacio y mostrando toda la información disponible)
 * AgroDietario
 */

const ReportExport = (function() {

    /**
     * Determina automáticamente si el contenido es ancho (>= 5 columnas) para usar orientación A4 horizontal (Landscape)
     */
    function isLandscapeRecommended(targetElement) {
        if (!targetElement) return true;
        const tables = targetElement.querySelectorAll('table');
        for (let i = 0; i < tables.length; i++) {
            const thCount = tables[i].querySelectorAll('thead tr:first-child th').length;
            if (thCount >= 5) return true;
        }
        return false;
    }

    /**
     * Opciones para html2pdf ajustadas milimétricamente para estándar A4
     */
    function getPdfConfig(filename, isLandscape) {
        return {
            margin: [8, 8, 8, 8],
            filename: filename || 'informe.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: isLandscape ? 'landscape' : 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
    }

    function ensureHtml2Pdf() {
        if (typeof html2pdf === 'undefined') {
            return new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
                s.onload = resolve;
                s.onerror = reject;
                document.head.appendChild(s);
            });
        }
        return Promise.resolve();
    }

    /**
     * Clona el elemento destino y lo maqueta estrictamente para formato de impresión A4
     */
    function createPrintableClone(targetId, title) {
        const source = document.getElementById(targetId);
        if (!source) return null;

        const clone = source.cloneNode(true);

        // 1. Ocultar botones, modales, formularios de filtrado y barras de herramientas
        clone.querySelectorAll('.no-print, [data-report-toolbar], .btn, .btn-group, .pagination, .dropdown, form:not(.imprimible), .modal').forEach(el => el.remove());

        // 2. Limpieza de tablas: suprimir columnas de "Acciones" para aprovechar el 100% del ancho del A4
        clone.querySelectorAll('table').forEach(table => {
            let actionColIndex = -1;
            const headerRow = table.querySelector('thead tr');
            if (headerRow) {
                const headers = headerRow.querySelectorAll('th');
                headers.forEach((th, idx) => {
                    const txt = th.textContent.trim().toLowerCase();
                    if (txt === 'acciones' || txt === 'acción' || th.classList.contains('text-end')) {
                        actionColIndex = idx;
                        th.remove();
                    }
                });
            }

            if (actionColIndex >= 0) {
                table.querySelectorAll('tbody tr').forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length > actionColIndex) {
                        cells[actionColIndex].remove();
                    }
                });
            }

            // Aplicar estilos ultracompactos y legibles para A4
            table.style.width = '100%';
            table.style.borderCollapse = 'collapse';
            table.style.fontSize = '8pt';
            table.style.color = '#000000';
            table.style.tableLayout = 'auto';
            table.style.margin = '0 0 10px 0';

            table.querySelectorAll('th').forEach(th => {
                th.style.backgroundColor = '#f1f5f9';
                th.style.color = '#0f172a';
                th.style.border = '1px solid #94a3b8';
                th.style.padding = '4px 6px';
                th.style.fontWeight = 'bold';
                th.style.fontSize = '8pt';
                th.style.textAlign = 'left';
            });

            table.querySelectorAll('td').forEach(td => {
                td.style.border = '1px solid #cbd5e1';
                td.style.padding = '4px 6px';
                td.style.fontSize = '8pt';
                td.style.verticalAlign = 'middle';
            });
        });

        // 3. Normalizar badges para que sean nítidos en papel y PDF
        clone.querySelectorAll('.badge').forEach(b => {
            b.style.border = '1px solid #94a3b8';
            b.style.color = '#0f172a';
            b.style.backgroundColor = '#f8fafc';
            b.style.padding = '2px 4px';
            b.style.fontSize = '7.5pt';
            b.style.fontWeight = '600';
            b.style.borderRadius = '3px';
        });

        // 4. Normalizar enlaces para que no salgan azules ni subrayados
        clone.querySelectorAll('a').forEach(a => {
            a.style.color = '#000000';
            a.style.textDecoration = 'none';
        });

        // 5. Envolver en contenedor oficial A4 con membrete
        const container = document.createElement('div');
        container.className = 'a4-document-container';
        container.style.padding = '8mm';
        container.style.backgroundColor = '#ffffff';
        container.style.color = '#000000';
        container.style.fontFamily = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
        container.style.boxSizing = 'border-box';
        container.style.width = '100%';

        const fechaEmision = new Date().toLocaleDateString('es-ES', { 
            day: '2-digit', month: '2-digit', year: 'numeric', 
            hour: '2-digit', minute: '2-digit' 
        });

        const headerHtml = `
            <div style="border-bottom: 2.5px solid #091e3a; padding-bottom: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <div style="font-size: 16pt; font-weight: 800; color: #091e3a; letter-spacing: -0.02em;">AgroDietario</div>
                    <div style="font-size: 11pt; font-weight: bold; color: #1e3a8a; margin-top: 2px;">${title || 'Listado Oficial Agronómico'}</div>
                </div>
                <div style="text-align: right; font-size: 8pt; color: #475569; line-height: 1.3;">
                    <div>Fecha de emisión: <strong>${fechaEmision}</strong></div>
                    <div>Plataforma Oficial de Gestión Agrícola</div>
                </div>
            </div>
        `;
        container.innerHTML = headerHtml;
        container.appendChild(clone);

        const footerHtml = `
            <div style="border-top: 1px solid #cbd5e1; margin-top: 15px; padding-top: 6px; font-size: 7.5pt; color: #64748b; display: flex; justify-content: space-between; align-items: center;">
                <span>Documento oficial generado electrónicamente por <strong>AgroDietario</strong></span>
                <span>Página generada para formato estándar A4</span>
            </div>
        `;
        const footerDiv = document.createElement('div');
        footerDiv.innerHTML = footerHtml;
        container.appendChild(footerDiv);

        return container;
    }

    function showToast(message, type) {
        type = type || 'success';
        const bgClass = type === 'success' ? 'bg-success text-white' : 'bg-danger text-white';
        const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';

        let container = document.getElementById('report-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'report-toast-container';
            container.style.position = 'fixed';
            container.style.top = '20px';
            container.style.right = '20px';
            container.style.zIndex = '99999';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast show align-items-center ${bgClass} border-0 shadow-lg mb-2`;
        toast.style.minWidth = '320px';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body fs-6 py-2 px-3">
                    <i class="fa-solid ${icon} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
            </div>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 6000);
    }

    return {
        /**
         * 1. VISUALIZAR: Muestra en pantalla el documento maquetado tal y como saldrá en la hoja A4
         */
        preview: function(targetId, title, filename, userEmail) {
            const printable = createPrintableClone(targetId, title);
            if (!printable) {
                alert('No se encontró el elemento a visualizar.');
                return;
            }

            const isLandscape = isLandscapeRecommended(document.getElementById(targetId));

            // Eliminar modal previo si existiera
            const oldModal = document.getElementById('modalReportPreview');
            if (oldModal) oldModal.remove();

            const modalHtml = `
                <div class="modal fade" id="modalReportPreview" tabindex="-1" aria-labelledby="modalReportPreviewLabel" aria-hidden="true">
                    <div class="modal-dialog modal-fullscreen">
                        <div class="modal-content" style="background-color: #334155;">
                            <!-- Barra superior de acciones de la vista previa -->
                            <div class="modal-header py-2 px-3 text-white border-0" style="background-color: #0f172a;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning text-dark font-monospace fw-bold"><i class="fa-solid fa-file-contract me-1"></i>VISTA PREVIA A4</span>
                                    <h6 class="modal-title mb-0 fw-bold">${title || 'Informe Oficial'}</h6>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-light" onclick="ReportExport.print('${targetId}')">
                                        <i class="fa-solid fa-print me-1"></i> Imprimir
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="ReportExport.downloadPdf('${targetId}', '${filename}', '${title}')">
                                        <i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF
                                    </button>
                                    ${userEmail ? `
                                        <button type="button" class="btn btn-sm btn-primary" onclick="ReportExport.sendEmail('${targetId}', '${title}', '${filename}', '${userEmail}', this)">
                                            <i class="fa-solid fa-envelope me-1"></i> Enviar a mi Email
                                        </button>
                                    ` : ''}
                                    <button type="button" class="btn btn-sm btn-secondary ms-2" data-bs-dismiss="modal">
                                        <i class="fa-solid fa-xmark"></i> Cerrar
                                    </button>
                                </div>
                            </div>
                            <!-- Área de visualización estilo hoja A4 -->
                            <div class="modal-body p-4" style="overflow-y: auto;">
                                <div id="previewA4Sheet" style="
                                    background: #ffffff;
                                    color: #000000;
                                    width: 100%;
                                    max-width: ${isLandscape ? '1120px' : '820px'};
                                    margin: 0 auto;
                                    box-shadow: 0 10px 35px rgba(0,0,0,0.4);
                                    border-radius: 4px;
                                    min-height: 600px;
                                ">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            document.getElementById('previewA4Sheet').appendChild(printable);

            const bsModal = new bootstrap.Modal(document.getElementById('modalReportPreview'));
            bsModal.show();
        },

        /**
         * 2. IMPRIMIR: Abre el cuadro de diálogo de impresión directa en formato A4
         */
        print: function(targetId) {
            window.print();
        },

        /**
         * 3. DESCARGAR PDF: Genera el archivo en tamaño A4 aprovechando todo el ancho
         */
        downloadPdf: async function(targetId, filename, title) {
            try {
                await ensureHtml2Pdf();
                const sourceEl = document.getElementById(targetId);
                const isLandscape = isLandscapeRecommended(sourceEl);
                const printable = createPrintableClone(targetId, title);
                if (!printable) {
                    alert('No se encontró el contenido a exportar.');
                    return;
                }
                const opt = getPdfConfig(filename, isLandscape);
                showToast('Generando documento PDF optimizado en A4...', 'success');
                html2pdf().set(opt).from(printable).save();
            } catch (err) {
                alert('Error al generar PDF: ' + err.message);
            }
        },

        /**
         * 4. ENVIAR POR EMAIL: Genera el PDF A4 y lo remite al correo del usuario autenticado
         */
        sendEmail: async function(targetId, title, filename, userEmail, btnElement) {
            if (!userEmail) {
                alert('No se detectó un correo electrónico válido para tu usuario.');
                return;
            }

            let originalHtml = '';
            if (btnElement) {
                originalHtml = btnElement.innerHTML;
                btnElement.disabled = true;
                btnElement.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Generando PDF A4 y enviando...';
            }

            try {
                await ensureHtml2Pdf();
                const sourceEl = document.getElementById(targetId);
                const isLandscape = isLandscapeRecommended(sourceEl);
                const printable = createPrintableClone(targetId, title);
                if (!printable) throw new Error('No se pudo capturar el contenido del informe.');

                // Generar PDF en Base64 con dimensiones exactas A4
                const opt = getPdfConfig(filename, isLandscape);
                const pdfBase64 = await html2pdf().set(opt).from(printable).outputPdf('datauristring');

                // Enviar al backend vía API
                const formData = new FormData();
                formData.append('titulo', title);
                formData.append('nombre_archivo', filename || 'informe.pdf');
                formData.append('pdf_base64', pdfBase64);
                formData.append('contenido_html', printable.innerHTML);

                const appUrl = (typeof window.APP_URL !== 'undefined') ? window.APP_URL : '';
                const endpoint = (appUrl ? appUrl : '') + '/api/informe/enviar-email';

                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                let json;
                try {
                    json = JSON.parse(text);
                } catch (e) {
                    throw new Error('Respuesta del servidor no válida: ' + text.substring(0, 150));
                }

                if (json.success) {
                    showToast(json.message || `Informe oficial A4 enviado con éxito a ${userEmail}`, 'success');
                } else {
                    showToast(json.message || 'Error al enviar el correo.', 'danger');
                }

            } catch (err) {
                showToast('Error: ' + err.message, 'danger');
            } finally {
                if (btnElement) {
                    btnElement.disabled = false;
                    btnElement.innerHTML = originalHtml;
                }
            }
        }
    };
})();
