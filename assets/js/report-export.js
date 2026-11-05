/**
 * ReportExport - Gestor Universal de Impresión, Visualización, Exportación a PDF y Envío por Email
 * Optimizado para formato A4 con Cabecera y Pie Repetidos en TODOS los Saltos de Página
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
            margin: [12, 8, 12, 8], // Espacio reservado para encabezado y pie de página en cada hoja
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
     * Clona el elemento destino y lo maqueta con cabecera y pie repetidos en saltos de página (A4)
     */
    function createPrintableClone(targetId, title) {
        const source = document.getElementById(targetId);
        if (!source) return null;

        const clone = source.cloneNode(true);

        // 1. Ocultar botones, modales, formularios de filtrado, barras de herramientas y membretes estáticos previos
        clone.querySelectorAll('.no-print, [data-report-toolbar], .btn, .btn-group, .pagination, .dropdown, form:not(.imprimible), .modal, .print-only').forEach(el => el.remove());

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

            // Aplicar estilos legibles para A4 y asegurar que THEAD se repita en saltos de página
            table.style.width = '100%';
            table.style.borderCollapse = 'collapse';
            table.style.fontSize = '8pt';
            table.style.color = '#000000';
            table.style.tableLayout = 'auto';
            table.style.margin = '0 0 10px 0';

            const thGroup = table.querySelector('thead');
            if (thGroup) {
                thGroup.style.display = 'table-header-group';
            }

            const tfGroup = table.querySelector('tfoot');
            if (tfGroup) {
                tfGroup.style.display = 'table-footer-group';
            }

            table.querySelectorAll('tr').forEach(tr => {
                tr.style.pageBreakInside = 'avoid';
                tr.style.breakInside = 'avoid';
            });

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

        // 5. Envolver en contenedor oficial A4 con cabecera THEAD exterior repetitiva
        const container = document.createElement('div');
        container.className = 'a4-document-container';
        container.style.backgroundColor = '#ffffff';
        container.style.color = '#000000';
        container.style.fontFamily = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
        container.style.boxSizing = 'border-box';
        container.style.width = '100%';

        const fechaEmision = new Date().toLocaleDateString('es-ES', { 
            day: '2-digit', month: '2-digit', year: 'numeric', 
            hour: '2-digit', minute: '2-digit' 
        });

        // Detectar si hay nombre de explotación activa
        let tenantTxt = '';
        const tenantEl = document.querySelector('.badge-tenant, #nav-tenant-name');
        if (tenantEl) {
            const cleanT = tenantEl.textContent.trim().replace(/^[\s\S]*?:/, '').trim();
            if (cleanT) {
                tenantTxt = `<span style="display: inline-block; background: #e0f2fe; color: #0369a1; padding: 1px 6px; border-radius: 3px; font-weight: 600; font-size: 8pt; margin-left: 6px;">Explotación: ${cleanT}</span>`;
            }
        }

        // Estructura oficial con THEAD para que el navegador repita la cabecera en CADA salto de página
        const layoutTable = document.createElement('table');
        layoutTable.className = 'report-outer-layout';
        layoutTable.style.width = '100%';
        layoutTable.style.border = 'none';
        layoutTable.style.borderCollapse = 'collapse';
        layoutTable.style.margin = '0';
        layoutTable.style.padding = '0';

        const thead = document.createElement('thead');
        thead.style.display = 'table-header-group';
        thead.innerHTML = `
            <tr>
                <th style="border: none; padding: 0 0 10px 0; font-weight: normal; text-align: left; background: transparent;">
                    <div class="report-running-header" style="border-bottom: 2.5px solid #091e3a; padding-bottom: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: flex-end;">
                        <div>
                            <div style="display: flex; align-items: baseline; gap: 4px;">
                                <span style="font-size: 15pt; font-weight: 900; color: #091e3a; letter-spacing: -0.02em;">AgroDietario</span>
                                ${tenantTxt}
                            </div>
                            <div style="font-size: 10.5pt; font-weight: 700; color: #1e3a8a; margin-top: 2px;">${title || 'Listado Oficial Agronómico'}</div>
                        </div>
                        <div style="text-align: right; font-size: 7.5pt; color: #475569; line-height: 1.35;">
                            <div>Fecha emisión: <strong>${fechaEmision}</strong></div>
                            <div>Plataforma Oficial de Gestión Agrícola</div>
                        </div>
                    </div>
                </th>
            </tr>
        `;

        const tfoot = document.createElement('tfoot');
        tfoot.style.display = 'table-footer-group';
        tfoot.innerHTML = `
            <tr>
                <td style="border: none; padding: 8px 0 0 0; font-size: 7pt; color: #64748b; background: transparent;">
                    <div class="report-running-footer" style="border-top: 1px solid #cbd5e1; padding-top: 4px; display: flex; justify-content: space-between; align-items: center;">
                        <span>Documento oficial generado electrónicamente por <strong>AgroDietario</strong></span>
                        <span>Documento Oficial A4</span>
                    </div>
                </td>
            </tr>
        `;

        const tbody = document.createElement('tbody');
        tbody.style.display = 'table-row-group';
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.style.border = 'none';
        td.style.padding = '0';
        td.appendChild(clone);
        tr.appendChild(td);
        tbody.appendChild(tr);

        layoutTable.appendChild(thead);
        layoutTable.appendChild(tfoot);
        layoutTable.appendChild(tbody);
        container.appendChild(layoutTable);

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
                                    <button type="button" class="btn btn-sm btn-outline-light" onclick="ReportExport.print('${targetId}', '${title}')">
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
                                    padding: 10mm;
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
         * garantizando que la cabecera se repite en TODOS los saltos de página
         */
        print: function(targetId, title) {
            const sourceEl = document.getElementById(targetId);
            if (!sourceEl) {
                alert('No se encontró el elemento a imprimir.');
                return;
            }

            title = title || document.title || 'Informe Oficial AgroDietario';
            const isLandscape = isLandscapeRecommended(sourceEl);
            const printable = createPrintableClone(targetId, title);
            if (!printable) {
                alert('No se pudo preparar el documento para impresión.');
                return;
            }

            // Usar un iframe invisible para imprimir exclusivamente el documento maquetado A4
            const printFrame = document.createElement('iframe');
            printFrame.style.position = 'fixed';
            printFrame.style.right = '0';
            printFrame.style.bottom = '0';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            printFrame.style.border = '0';
            document.body.appendChild(printFrame);

            const frameDoc = printFrame.contentWindow.document;
            frameDoc.open();
            frameDoc.write(`
                <!DOCTYPE html>
                <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <title>${title}</title>
                    <style>
                        @page {
                            size: ${isLandscape ? 'A4 landscape' : 'A4 portrait'};
                            margin: 8mm 6mm;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                            background: #ffffff;
                            color: #000000;
                            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        }
                        table.report-outer-layout {
                            width: 100% !important;
                            border: none !important;
                            border-collapse: collapse !important;
                        }
                        table.report-outer-layout > thead {
                            display: table-header-group !important;
                        }
                        table.report-outer-layout > tfoot {
                            display: table-footer-group !important;
                        }
                        table.report-outer-layout > thead > tr > th,
                        table.report-outer-layout > tfoot > tr > td,
                        table.report-outer-layout > tbody > tr > td {
                            border: none !important;
                            padding: 0 !important;
                        }
                        table {
                            width: 100% !important;
                            border-collapse: collapse !important;
                            font-size: 8pt !important;
                        }
                        thead {
                            display: table-header-group !important;
                        }
                        tfoot {
                            display: table-footer-group !important;
                        }
                        tr {
                            page-break-inside: avoid !important;
                            break-inside: avoid !important;
                        }
                        th {
                            background-color: #f1f5f9 !important;
                            color: #0f172a !important;
                            border: 1px solid #94a3b8 !important;
                            padding: 4px 6px !important;
                            font-weight: bold !important;
                        }
                        td {
                            border: 1px solid #cbd5e1 !important;
                            padding: 4px 6px !important;
                        }
                        .badge {
                            border: 1px solid #94a3b8 !important;
                            color: #0f172a !important;
                            background: #f8fafc !important;
                            padding: 2px 4px !important;
                            font-size: 7.5pt !important;
                        }
                        .no-print {
                            display: none !important;
                        }
                    </style>
                </head>
                <body>
                    ${printable.outerHTML}
                </body>
                </html>
            `);
            frameDoc.close();

            setTimeout(() => {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
                setTimeout(() => {
                    printFrame.remove();
                }, 3000);
            }, 300);
        },

        /**
         * 3. DESCARGAR PDF: Genera el archivo en tamaño A4 con cabecera y pie repetidos en cada salto de página
         */
        downloadPdf: async function(targetId, filename, title) {
            try {
                await ensureHtml2Pdf();
                const sourceEl = document.getElementById(targetId);
                const isLandscape = isLandscapeRecommended(sourceEl);
                title = title || document.title || 'Informe Oficial Agronómico';
                const printable = createPrintableClone(targetId, title);
                if (!printable) {
                    alert('No se encontró el contenido a exportar.');
                    return;
                }
                const opt = getPdfConfig(filename, isLandscape);

                const fechaEmision = new Date().toLocaleDateString('es-ES', { 
                    day: '2-digit', month: '2-digit', year: 'numeric', 
                    hour: '2-digit', minute: '2-digit' 
                });

                showToast('Generando documento PDF optimizado en A4 con cabeceras...', 'success');

                await html2pdf().set(opt).from(printable).toPdf().get('pdf').then(function(pdf) {
                    const totalPages = pdf.internal.getNumberOfPages();
                    const pageWidth = pdf.internal.pageSize.getWidth();
                    const pageHeight = pdf.internal.pageSize.getHeight();

                    for (let i = 1; i <= totalPages; i++) {
                        pdf.setPage(i);

                        // Cabecera repetida en cada salto de página (a partir de la página 2)
                        if (i > 1) {
                            pdf.setFont('helvetica', 'bold');
                            pdf.setFontSize(8);
                            pdf.setTextColor(9, 30, 58);
                            pdf.text('AgroDietario', 8, 6.5);

                            pdf.setFont('helvetica', 'normal');
                            pdf.setFontSize(7.5);
                            pdf.setTextColor(30, 58, 138);
                            const cleanTitle = title.substring(0, 65);
                            pdf.text(' | ' + cleanTitle, 26, 6.5);

                            pdf.setTextColor(71, 85, 105);
                            pdf.setFontSize(7);
                            pdf.text(fechaEmision + '  |  Pág. ' + i + ' de ' + totalPages, pageWidth - 8, 6.5, { align: 'right' });

                            pdf.setDrawColor(9, 30, 58);
                            pdf.setLineWidth(0.35);
                            pdf.line(8, 8, pageWidth - 8, 8);
                        }

                        // Pie de página repetido en todas las páginas con numeración
                        pdf.setDrawColor(203, 213, 225);
                        pdf.setLineWidth(0.2);
                        pdf.line(8, pageHeight - 8, pageWidth - 8, pageHeight - 8);

                        pdf.setFont('helvetica', 'normal');
                        pdf.setFontSize(6.5);
                        pdf.setTextColor(100, 116, 139);
                        pdf.text('Documento oficial generado electrónicamente por AgroDietario', 8, pageHeight - 4.5);
                        pdf.text('Página ' + i + ' de ' + totalPages, pageWidth - 8, pageHeight - 4.5, { align: 'right' });
                    }
                }).save();
            } catch (err) {
                alert('Error al generar PDF: ' + err.message);
            }
        },

        /**
         * 4. ENVIAR POR EMAIL: Genera el PDF A4 con cabeceras y lo remite al correo del usuario autenticado
         */
        sendEmail: async function(targetId, title, filename, userEmail, btnElement) {
            if (!userEmail) {
                alert('No se detectó un correo electrónico válido para tu usuario.');
                return;
            }

            title = title || document.title || 'Informe Oficial Agronómico';
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

                const opt = getPdfConfig(filename, isLandscape);

                const fechaEmision = new Date().toLocaleDateString('es-ES', { 
                    day: '2-digit', month: '2-digit', year: 'numeric', 
                    hour: '2-digit', minute: '2-digit' 
                });

                // Generar PDF en Base64 con cabeceras y pies en todos los saltos de página
                const pdfBase64 = await html2pdf().set(opt).from(printable).toPdf().get('pdf').then(function(pdf) {
                    const totalPages = pdf.internal.getNumberOfPages();
                    const pageWidth = pdf.internal.pageSize.getWidth();
                    const pageHeight = pdf.internal.pageSize.getHeight();

                    for (let i = 1; i <= totalPages; i++) {
                        pdf.setPage(i);

                        if (i > 1) {
                            pdf.setFont('helvetica', 'bold');
                            pdf.setFontSize(8);
                            pdf.setTextColor(9, 30, 58);
                            pdf.text('AgroDietario', 8, 6.5);

                            pdf.setFont('helvetica', 'normal');
                            pdf.setFontSize(7.5);
                            pdf.setTextColor(30, 58, 138);
                            const cleanTitle = title.substring(0, 65);
                            pdf.text(' | ' + cleanTitle, 26, 6.5);

                            pdf.setTextColor(71, 85, 105);
                            pdf.setFontSize(7);
                            pdf.text(fechaEmision + '  |  Pág. ' + i + ' de ' + totalPages, pageWidth - 8, 6.5, { align: 'right' });

                            pdf.setDrawColor(9, 30, 58);
                            pdf.setLineWidth(0.35);
                            pdf.line(8, 8, pageWidth - 8, 8);
                        }

                        pdf.setDrawColor(203, 213, 225);
                        pdf.setLineWidth(0.2);
                        pdf.line(8, pageHeight - 8, pageWidth - 8, pageHeight - 8);

                        pdf.setFont('helvetica', 'normal');
                        pdf.setFontSize(6.5);
                        pdf.setTextColor(100, 116, 139);
                        pdf.text('Documento oficial generado electrónicamente por AgroDietario', 8, pageHeight - 4.5);
                        pdf.text('Página ' + i + ' de ' + totalPages, pageWidth - 8, pageHeight - 4.5, { align: 'right' });
                    }
                }).outputPdf('datauristring');

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
