<?php
/**
 * Barra de Herramientas Universal para Listados e Informes
 * Opciones: Ver en Pantalla, Imprimir, Descargar PDF y Enviar por Email al usuario autenticado
 * 
 * Variables esperadas:
 * - $reportTitle (string) Título legible del informe
 * - $reportTargetId (string) ID del contenedor HTML de la tabla/informe a imprimir/exportar
 * - $reportFilename (string) Nombre del archivo PDF (ej: listado_parajes.pdf)
 */

$userEmail = '';
$userName = '';
if (class_exists('Auth') && Auth::check()) {
    $u = Auth::user();
    $userEmail = !empty($u['email']) ? $u['email'] : '';
    $userName = !empty($u['nombre_completo']) ? $u['nombre_completo'] : '';
}
$reportTitle = isset($reportTitle) ? $reportTitle : 'Informe AgroDietario';
$reportTargetId = isset($reportTargetId) ? $reportTargetId : 'areaImprimible';
$reportFilename = isset($reportFilename) ? $reportFilename : 'informe_' . date('Y-m-d') . '.pdf';
?>

<div class="d-flex flex-wrap align-items-center gap-2 no-print my-2" data-report-toolbar>
    <!-- Botón 1: Imprimir -->
    <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm" onclick="ReportExport.print('<?= htmlspecialchars($reportTargetId) ?>')" title="Imprimir listado en papel o vista previa del navegador">
        <i class="fa-solid fa-print me-1 text-secondary"></i> Imprimir
    </button>

    <!-- Botón 2: Descargar PDF -->
    <button type="button" class="btn btn-sm btn-outline-danger shadow-sm" onclick="ReportExport.downloadPdf('<?= htmlspecialchars($reportTargetId) ?>', '<?= htmlspecialchars($reportFilename) ?>', '<?= htmlspecialchars(addslashes($reportTitle)) ?>')" title="Generar y descargar documento oficial en formato PDF">
        <i class="fa-solid fa-file-pdf me-1 text-danger"></i> Descargar PDF
    </button>

    <!-- Botón 3: Enviar por Email -->
    <?php if (!empty($userEmail)): ?>
        <button type="button" class="btn btn-sm btn-outline-primary shadow-sm" onclick="ReportExport.sendEmail('<?= htmlspecialchars($reportTargetId) ?>', '<?= htmlspecialchars(addslashes($reportTitle)) ?>', '<?= htmlspecialchars($reportFilename) ?>', '<?= htmlspecialchars($userEmail) ?>', this)" title="Enviar copia oficial a <?= htmlspecialchars($userEmail) ?>">
            <i class="fa-solid fa-envelope me-1 text-primary"></i> Enviar a mi Email
            <span class="badge bg-primary bg-opacity-10 text-primary ms-1 font-monospace" style="font-size: 0.75rem;"><?= htmlspecialchars($userEmail) ?></span>
        </button>
    <?php endif; ?>
</div>
