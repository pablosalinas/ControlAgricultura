<?php
$pageTitle = "Catálogo Botánico de Cultivos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$cultivos = $db->query("SELECT * FROM cultivos WHERE activo = 1 ORDER BY nombre_comun ASC")->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-leaf text-success me-2"></i>Catálogo Botánico de Cultivos</h2>
            <p class="text-muted mb-0">Gestión de especies, variedades y consulta botánica técnica</p>
        </div>
        <div>
            <a href="<?= APP_URL ?>/cultivos/nuevo" class="btn btn-ag shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Añadir Cultivo
            </a>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($cultivos as $c): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom h-100 p-4 border-start border-4 border-success">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge bg-success bg-opacity-10 text-success text-uppercase fw-bold" style="font-size:0.75rem;">
                                <?= htmlspecialchars($c['tipo_cultivo'] ?? 'Cultivo') ?>
                            </span>
                            <h4 class="fw-bold my-1 text-dark"><?= htmlspecialchars($c['nombre_comun']) ?></h4>
                        </div>
                        <div class="fs-4 text-success opacity-50">
                            <i class="fa-solid fa-seedling"></i>
                        </div>
                    </div>

                    <?php if ($c['nombre_cientifico']): ?>
                        <div class="mb-3">
                            <span class="fst-italic text-muted fw-semibold">
                                <?= htmlspecialchars($c['nombre_cientifico']) ?>
                            </span>
                            <!-- Botón de Búsqueda Botánica en Internet -->
                            <a href="https://www.google.com/search?q=<?= urlencode($c['nombre_cientifico'] . ' cultivo agronomia') ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-1 py-0 px-2 rounded-pill" title="Buscar ficha botánica en Google">
                                <i class="fa-solid fa-magnifying-glass" style="font-size:0.7rem;"></i> Buscar
                            </a>
                            <?php if ($c['url_consulta_botanica']): ?>
                                <a href="<?= htmlspecialchars($c['url_consulta_botanica']) ?>" target="_blank" class="btn btn-sm btn-outline-info ms-1 py-0 px-2 rounded-pill" title="Enlace botánico directo">
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.7rem;"></i> Wiki
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="small text-muted mb-3">
                        <div><strong>Variedad:</strong> <?= htmlspecialchars($c['variedad'] ?? 'Estándar') ?></div>
                        <div><strong>Ciclo estimado:</strong> <?= $c['dias_ciclo_aprox'] ? $c['dias_ciclo_aprox'] . ' días' : 'No especificado' ?></div>
                    </div>

                    <?php if ($c['requerimientos_tecnicos']): ?>
                        <div class="p-2 bg-light rounded small text-secondary mb-3 border">
                            <i class="fa-solid fa-circle-info text-primary me-1"></i>
                            <?= nl2br(htmlspecialchars($c['requerimientos_tecnicos'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
