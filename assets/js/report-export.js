/**
 * ReportExport - Gestor Universal de Impresión, Exportación a PDF y Envío por Email
 * AgroDietario
 */

const ReportExport = (function() {

    function getPdfConfig(filename) {
        return {
            margin: [10, 10, 10, 10],
            filename: filename || 'informe.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
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

    function createPrintableClone(targetId, title) {
        const source = document.getElementById(targetId);
        if (!source) return null;

        const clone = source.cloneNode(true);
        // Ocultar botones y acciones dentro del clon
        clone.querySelectorAll('.no-print, .btn, .btn-group, [data-report-toolbar]').forEach(el => el.remove());

        // Crear contenedor imprimible con cabecera corporativa
        const container = document.createElement('div');
        container.style.padding = '15px';
        container.style.background = '#ffffff';
        container.style.color = '#111827';
        container.style.fontFamily = 'Segoe UI, Arial, sans-serif';

        const headerHtml = `
            <div style="border-bottom: 2px solid #091e3a; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <h3 style="margin: 0; color: #091e3a; font-weight: bold; font-size: 18px;">${title || 'AgroDietario - Informe Oficial'}</h3>
                    <p style="margin: 3px 0 0; color: #6b7280; font-size: 12px;">Control agronómico y gestión de explotaciones agrícolas</p>
                </div>
                <div style="text-align: right; font-size: 11px; color: #6b7280;">
                    <div>Fecha de emisión: <strong>${new Date().toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</strong></div>
                </div>
            </div>
        `;
        container.innerHTML = headerHtml;
        container.appendChild(clone);

        const footerHtml = `
            <div style="border-top: 1px solid #e5e7eb; margin-top: 20px; padding-top: 8px; font-size: 10px; color: #9ca3af; text-align: center;">
                Documento oficial generado electrónicamente por AgroDietario • Plataforma de Control Agrícola
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
            container.style.zIndex = '9999';
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
         * Imprime el informe o listado
         */
        print: function(targetId) {
            window.print();
        },

        /**
         * Descarga el informe en formato PDF
         */
        downloadPdf: async function(targetId, filename, title) {
            try {
                await ensureHtml2Pdf();
                const printable = createPrintableClone(targetId, title);
                if (!printable) {
                    alert('No se encontró el contenido a exportar.');
                    return;
                }
                const opt = getPdfConfig(filename);
                html2pdf().set(opt).from(printable).save();
            } catch (err) {
                alert('Error al generar PDF: ' + err.message);
            }
        },

        /**
         * Envía el informe por email al usuario autenticado
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
                btnElement.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Generando PDF y enviando...';
            }

            try {
                await ensureHtml2Pdf();
                const printable = createPrintableClone(targetId, title);
                if (!printable) throw new Error('No se pudo capturar el contenido del informe.');

                // Generar PDF en Base64
                const opt = getPdfConfig(filename);
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
                    showToast(json.message || `Informe enviado con éxito a ${userEmail}`, 'success');
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
