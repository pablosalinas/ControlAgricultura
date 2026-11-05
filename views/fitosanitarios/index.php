<?php
$pageTitle = "Gestión de Productos Fitosanitarios y Dosis Oficiales - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR FORMULARIOS (CREAR, EDITAR, BORRADO LÓGICO SEGURO)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. GUARDAR O EDITAR PRODUCTO FITOSANITARIO
    if ($action === 'guardar_producto') {
        try {
            $idProd = !empty($_POST['id_producto']) ? (int)$_POST['id_producto'] : 0;
            $nombre = trim($_POST['nombre_comercial']);
            $materia = trim(isset($_POST['materia_activa']) ? $_POST['materia_activa'] : '');
            $registro = trim(isset($_POST['num_registro_oficial']) ? $_POST['num_registro_oficial'] : '');
            $tipo = trim(isset($_POST['tipo_producto']) ? $_POST['tipo_producto'] : 'Fungicida');
            $esEco = isset($_POST['es_ecologico']) ? 1 : 0;
            $cultivos = trim(isset($_POST['cultivos_autorizados']) ? $_POST['cultivos_autorizados'] : '');
            $plagas = trim(isset($_POST['plagas_objetivo']) ? $_POST['plagas_objetivo'] : '');
            $modo = trim(isset($_POST['modo_accion_uso']) ? $_POST['modo_accion_uso'] : '');
            
            $dosis100l = (float)(isset($_POST['dosis_etiqueta_100l']) ? $_POST['dosis_etiqueta_100l'] : 0);
            $unidad = isset($_POST['unidad_dosis']) ? trim($_POST['unidad_dosis']) : 'cc/hl';
            $dosisHa = (float)(isset($_POST['dosis_por_hectarea']) ? $_POST['dosis_por_hectarea'] : 0);
            
            $plazoSeguridad = (int)(isset($_POST['plazo_seguridad_dias']) ? $_POST['plazo_seguridad_dias'] : 0);
            $intervaloRep = (int)(isset($_POST['intervalo_repeticion_dias']) ? $_POST['intervalo_repeticion_dias'] : 14);
            
            // Volúmenes y Dosis por Recipientes
            $volMochila = (float)(isset($_POST['volumen_mochila_l']) ? $_POST['volumen_mochila_l'] : 16.0);
            $dosisMochila = (float)(isset($_POST['dosis_calculada_mochila']) ? $_POST['dosis_calculada_mochila'] : ($dosis100l * ($volMochila / 100.0)));
            
            $volCuba = (float)(isset($_POST['volumen_cuba_l']) ? $_POST['volumen_cuba_l'] : 1000.0);
            $dosisCuba = (float)(isset($_POST['dosis_calculada_cuba']) ? $_POST['dosis_calculada_cuba'] : ($dosis100l * ($volCuba / 100.0)));
            
            $volPulv = (float)(isset($_POST['volumen_pulverizador_l']) ? $_POST['volumen_pulverizador_l'] : 100.0);
            $dosisPulv = (float)(isset($_POST['dosis_calculada_pulv']) ? $_POST['dosis_calculada_pulv'] : ($dosis100l * ($volPulv / 100.0)));
            
            $advertencias = trim(isset($_POST['descripcion_advertencias']) ? $_POST['descripcion_advertencias'] : '');

            if (empty($nombre)) {
                throw new Exception("El nombre comercial del producto es obligatorio.");
            }

            if ($idProd > 0) {
                // Actualizar registro existente
                $stmtUpd = $db->prepare("
                    UPDATE productos_fitosanitarios
                    SET nombre_comercial = :nom, materia_activa = :mat, num_registro_oficial = :reg,
                        tipo_producto = :tipo, es_ecologico = :eco, cultivos_autorizados = :cult,
                        plagas_objetivo = :plagas, modo_accion_uso = :modo, dosis_etiqueta_100l = :d100,
                        unidad_dosis = :uni, dosis_por_hectarea = :dha, plazo_seguridad_dias = :plazo,
                        intervalo_repeticion_dias = :inter, volumen_mochila_l = :vmoch,
                        dosis_calculada_mochila = :dmoch, volumen_cuba_l = :vcuba,
                        dosis_calculada_cuba = :dcuba, volumen_pulverizador_l = :vpulv,
                        dosis_calculada_pulv = :dpulv, descripcion_advertencias = :adv
                    WHERE id_producto = :id
                ");
                $stmtUpd->execute(array(
                    ':nom'   => $nombre,
                    ':mat'   => $materia,
                    ':reg'   => $registro,
                    ':tipo'  => $tipo,
                    ':eco'   => $esEco,
                    ':cult'  => $cultivos,
                    ':plagas'=> $plagas,
                    ':modo'  => $modo,
                    ':d100'  => $dosis100l,
                    ':uni'   => $unidad,
                    ':dha'   => $dosisHa,
                    ':plazo' => $plazoSeguridad,
                    ':inter' => $intervaloRep,
                    ':vmoch' => $volMochila,
                    ':dmoch' => $dosisMochila,
                    ':vcuba' => $volCuba,
                    ':dcuba' => $dosisCuba,
                    ':vpulv' => $volPulv,
                    ':dpulv' => $dosisPulv,
                    ':adv'   => $advertencias,
                    ':id'    => $idProd
                ));
                $mensaje = "Ficha de producto fitosanitario actualizada correctamente.";
            } else {
                // Insertar nuevo producto
                $stmtIns = $db->prepare("
                    INSERT INTO productos_fitosanitarios (
                        id_propietario, nombre_comercial, materia_activa, num_registro_oficial,
                        tipo_producto, es_ecologico, cultivos_autorizados, plagas_objetivo,
                        modo_accion_uso, dosis_etiqueta_100l, unidad_dosis, dosis_por_hectarea,
                        plazo_seguridad_dias, intervalo_repeticion_dias, volumen_mochila_l,
                        dosis_calculada_mochila, volumen_cuba_l, dosis_calculada_cuba,
                        volumen_pulverizador_l, dosis_calculada_pulv, descripcion_advertencias, activo
                    ) VALUES (
                        :prop, :nom, :mat, :reg,
                        :tipo, :eco, :cult, :plagas,
                        :modo, :d100, :uni, :dha,
                        :plazo, :inter, :vmoch,
                        :dmoch, :vcuba, :dcuba,
                        :vpulv, :dpulv, :adv, 1
                    )
                ");
                $stmtIns->execute(array(
                    ':prop'  => $tenantId ?: null,
                    ':nom'   => $nombre,
                    ':mat'   => $materia,
                    ':reg'   => $registro,
                    ':tipo'  => $tipo,
                    ':eco'   => $esEco,
                    ':cult'  => $cultivos,
                    ':plagas'=> $plagas,
                    ':modo'  => $modo,
                    ':d100'  => $dosis100l,
                    ':uni'   => $unidad,
                    ':dha'   => $dosisHa,
                    ':plazo' => $plazoSeguridad,
                    ':inter' => $intervaloRep,
                    ':vmoch' => $volMochila,
                    ':dmoch' => $dosisMochila,
                    ':vcuba' => $volCuba,
                    ':dcuba' => $dosisCuba,
                    ':vpulv' => $volPulv,
                    ':dpulv' => $dosisPulv,
                    ':adv'   => $advertencias
                ));
                $mensaje = "Nuevo producto fitosanitario registrado en el inventario oficial.";
            }
        } catch (Exception $e) {
            $error = "Error al guardar el producto: " . $e->getMessage();
        }
    }

    // 2. BORRADO LÓGICO SEGURO (GEMINI.md: activo = 0)
    elseif ($action === 'borrar_producto') {
        try {
            $idProd = (int)$_POST['id_producto'];
            $stmtDel = $db->prepare("UPDATE productos_fitosanitarios SET activo = 0 WHERE id_producto = :id");
            $stmtDel->execute(array(':id' => $idProd));
            $mensaje = "Producto archivado y descatalogado correctamente (borrado lógico seguro).";
        } catch (Exception $e) {
            $error = "Error al archivar el producto: " . $e->getMessage();
        }
    }
}

// Filtros de búsqueda
$filtroTxt = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroEco = isset($_GET['eco']) ? trim($_GET['eco']) : '';
$filtroTipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';

// Consulta de productos fitosanitarios activos
$sql = "SELECT * FROM productos_fitosanitarios WHERE activo = 1";
$params = array();

if ($filtroEco === '1') {
    $sql .= " AND es_ecologico = 1";
} elseif ($filtroEco === '0') {
    $sql .= " AND es_ecologico = 0";
}

if (!empty($filtroTipo)) {
    $sql .= " AND tipo_producto LIKE :tipo";
    $params[':tipo'] = '%' . $filtroTipo . '%';
}

if (!empty($filtroTxt)) {
    $sql .= " AND (nombre_comercial LIKE :txt OR materia_activa LIKE :txt OR plagas_objetivo LIKE :txt OR cultivos_autorizados LIKE :txt OR num_registro_oficial LIKE :txt)";
    $params[':txt'] = '%' . $filtroTxt . '%';
}

$sql .= " ORDER BY nombre_comercial ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Estadísticas de catálogo
$totalProductos = count($productos);
$totalEco = 0;
$totalConv = 0;
foreach ($productos as $p) {
    if ($p['es_ecologico']) $totalEco++;
    else $totalConv++;
}
?>

<div class="container py-4">

    <!-- Mensajes de Estado -->
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Cabecera Oficial y Toolbar Universal A4 -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3 no-print">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-bottle-droplet fs-2 text-warning"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Gestión Oficial de Productos Fitosanitarios</h2>
                <p class="text-muted mb-0 small">Control técnico de materias activas, dosis por recipientes (mochila, carretilla, cuba, hectárea) e Inteligencia Artificial agronómica</p>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-primary fw-bold shadow-sm" onclick="abrirModalNuevoProducto()">
                <i class="fa-solid fa-plus me-1 text-warning"></i> Nuevo Fitosanitario
            </button>
            <button type="button" class="btn btn-outline-primary fw-semibold shadow-sm" onclick="abrirModalIaRapida()">
                <i class="fa-solid fa-bolt me-1 text-warning"></i> Localizar con IA
            </button>
            <?php
            $reportTitle = 'Catálogo Oficial de Productos Fitosanitarios y Calculadora de Dosis - ' . APP_NAME;
            $reportTargetId = 'catalogoFitosanitariosImprimible';
            $reportFilename = 'catalogo_fitosanitarios_' . date('Y-m-d') . '.pdf';
            require VIEWS_PATH . '/layouts/toolbar_informes.php';
            ?>
        </div>
    </div>

    <!-- Tarjetas de Métricas Rápidas -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-4">
            <div class="card card-custom p-3 border-0 bg-primary bg-opacity-10 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Total Fitosanitarios Registrados</div>
                        <div class="fs-4 fw-bold text-primary"><?= $totalProductos ?> <span class="fs-6 fw-normal text-muted">productos</span></div>
                    </div>
                    <i class="fa-solid fa-boxes-stacked fs-2 text-primary opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 border-0 bg-success bg-opacity-10 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Aptos en Agricultura Ecológica (UE)</div>
                        <div class="fs-4 fw-bold text-success"><?= $totalEco ?> <span class="fs-6 fw-normal text-muted">productos</span></div>
                    </div>
                    <i class="fa-solid fa-leaf fs-2 text-success opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 border-0 bg-secondary bg-opacity-10 border-start border-4 border-secondary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Tratamientos Convencionales</div>
                        <div class="fs-4 fw-bold text-secondary"><?= $totalConv ?> <span class="fs-6 fw-normal text-muted">productos</span></div>
                    </div>
                    <i class="fa-solid fa-flask-vial fs-2 text-secondary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="card card-custom p-3 mb-4 bg-light border-0 shadow-sm no-print">
        <form action="<?= APP_URL ?>/fitosanitarios" method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-solid fa-magnifying-glass me-1 text-primary"></i>Buscar Producto, Materia, Plaga o Cultivo:</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                    <input type="text" name="q" value="<?= htmlspecialchars($filtroTxt) ?>" class="form-control" placeholder="Ej: Cobre, Spinosad, Bacillus, Repilo, Olivo, Almendro...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-solid fa-leaf me-1 text-success"></i>Régimen:</label>
                <select name="eco" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los Regímenes --</option>
                    <option value="1" <?= $filtroEco === '1' ? 'selected' : '' ?>>🌱 Ecológico Certificado</option>
                    <option value="0" <?= $filtroEco === '0' ? 'selected' : '' ?>>🌾 Convencional</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1 text-muted"><i class="fa-solid fa-tags me-1 text-info"></i>Tipo de Fitosanitario:</label>
                <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los Tipos --</option>
                    <option value="Fungicida" <?= $filtroTipo === 'Fungicida' ? 'selected' : '' ?>>🍄 Fungicidas / Bactericidas</option>
                    <option value="Insecticida" <?= $filtroTipo === 'Insecticida' ? 'selected' : '' ?>>🐛 Insecticidas / Acaricidas</option>
                    <option value="Herbicida" <?= $filtroTipo === 'Herbicida' ? 'selected' : '' ?>>🚜 Herbicidas</option>
                    <option value="Bioestimulante" <?= $filtroTipo === 'Bioestimulante' ? 'selected' : '' ?>>🌿 Bioestimulantes / Nutrición</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Buscar">
                    <i class="fa-solid fa-filter"></i>
                </button>
                <a href="<?= APP_URL ?>/fitosanitarios" class="btn btn-sm btn-outline-secondary" title="Limpiar Filtros">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- DOCUMENTO IMPRIMIBLE CON EL CATÁLOGO OFICIAL -->
    <div id="catalogoFitosanitariosImprimible">

        <!-- Encabezado Imprimible A4 -->
        <div class="card card-custom p-4 mb-4 border shadow-sm print-only">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold text-primary mb-1">Catálogo Oficial de Productos Fitosanitarios</h4>
                    <div class="small text-muted">Explotación Agrícola | Registro y Dosificación Homologada | Campaña <?= date('Y') ?></div>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary fs-6 px-3 py-2">Documento Técnico Oficial</span>
                    <div class="small text-muted mt-1">Fecha: <?= date('d/m/Y') ?></div>
                </div>
            </div>
        </div>

        <?php if (empty($productos)): ?>
            <div class="card card-custom p-5 text-center shadow-sm">
                <i class="fa-solid fa-bottle-droplet text-muted fs-1 mb-3 opacity-25"></i>
                <h5 class="fw-bold text-muted">No se encontraron productos fitosanitarios con los criterios seleccionados</h5>
                <p class="small text-muted mb-3">Puedes dar de alta un producto manualmente o consultar la base de datos de fitosanitarios mediante IA.</p>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalNuevoProducto()">
                        <i class="fa-solid fa-plus me-1"></i> Añadir Primer Producto
                    </button>
                </div>
            </div>
        <?php else: ?>

            <div class="row g-4">
                <?php foreach ($productos as $p): ?>
                    <?php
                    $esEco = (bool)$p['es_ecologico'];
                    $unidad = $p['unidad_dosis'] ?: 'g/hl';
                    $dosis100 = (float)$p['dosis_etiqueta_100l'];
                    $dMoch = (float)$p['dosis_calculada_mochila'];
                    $dPulv = (float)$p['dosis_calculada_pulv'];
                    $dCuba = (float)$p['dosis_calculada_cuba'];
                    $dHa = (float)$p['dosis_por_hectarea'];
                    $plazo = (int)$p['plazo_seguridad_dias'];
                    $intervalo = (int)$p['intervalo_repeticion_dias'];

                    // Determinar si es líquido o sólido para las unidades visuales
                    $materiaL = mb_strtolower($p['materia_activa'] . ' ' . $p['tipo_producto'] . ' ' . $p['nombre_comercial'] . ' ' . $unidad, 'UTF-8');
                    $esLiquido = (strpos($materiaL, '[ec]') !== false || strpos($materiaL, '[sc]') !== false || strpos($materiaL, '[sl]') !== false || strpos($materiaL, '[ew]') !== false || strpos($materiaL, '[od]') !== false || strpos($materiaL, 'líquid') !== false || strpos($materiaL, 'aceite') !== false || strpos($unidad, 'l') !== false || strpos($unidad, 'cc') !== false || strpos($unidad, 'ml') !== false);

                    $uPequena = $esLiquido ? 'ml' : 'g';
                    $uGrande = $esLiquido ? 'L' : 'kg';

                    // Normalizar dosis base por cada 100L a gramos o ml
                    $gPor100 = 0;
                    if ($dosis100 > 0 && $dosis100 < 10) {
                        $gPor100 = $dosis100 * 1000.0; // ej: 0.300 kg/hl => 300 g
                    } else {
                        $gPor100 = $dosis100;
                    }

                    // Cuba 1.000L: debe mostrarse en Kilos o Litros (ej: 3.00 kg para 1000L, nunca 3000 kg)
                    if ($dCuba >= 50.0) {
                        $dCuba = $dCuba / 1000.0;
                    } elseif ($dCuba <= 0 && $gPor100 > 0) {
                        $dCuba = ($gPor100 * 10.0) / 1000.0;
                    }

                    // Mochila 16L: en gramos o ml (ej: 48 g o 32 ml)
                    if ($dMoch <= 0 && $gPor100 > 0) {
                        $dMoch = $gPor100 * 0.16;
                    } elseif ($dMoch > 0 && $dMoch < 1.0) {
                        $dMoch = $dMoch * 1000.0;
                    }

                    // Pulverizador 100L: en gramos o ml (ej: 300 g o 200 ml)
                    if ($dPulv <= 0 && $gPor100 > 0) {
                        $dPulv = $gPor100;
                    } elseif ($dPulv > 0 && $dPulv < 1.0) {
                        $dPulv = $dPulv * 1000.0;
                    }

                    if ($dHa <= 0 && $dCuba > 0) {
                        $dHa = $dCuba;
                    }
                    ?>

                    <div class="col-lg-6" style="page-break-inside: avoid;">
                        <div class="card card-custom h-100 border shadow-sm">
                            
                            <!-- Cabecera de Tarjeta -->
                            <div class="card-header bg-light py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <h5 class="fw-bold text-primary mb-0">
                                        <i class="fa-solid fa-bottle-droplet me-2 text-danger"></i><?= htmlspecialchars($p['nombre_comercial']) ?>
                                    </h5>
                                    <div class="small text-muted font-monospace mt-1">
                                        <strong>Materia Activa:</strong> <?= htmlspecialchars($p['materia_activa'] ?: 'No especificada') ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <?php if ($esEco): ?>
                                        <span class="badge bg-success text-white py-1 px-2">
                                            <i class="fa-solid fa-leaf me-1"></i>ECOLÓGICO (UE)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-white py-1 px-2">
                                            <i class="fa-solid fa-wheat-awn me-1"></i>CONVENCIONAL
                                        </span>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-1">
                                        Reg. MAPA: <strong><?= htmlspecialchars($p['num_registro_oficial'] ?: 'S/N') ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body p-3">
                                
                                <!-- Tipo y Diana -->
                                <div class="row g-2 mb-3 small">
                                    <div class="col-sm-6">
                                        <span class="text-muted fw-semibold"><i class="fa-solid fa-tag me-1 text-primary"></i>Tipo:</span>
                                        <span class="badge bg-light text-primary border"><?= htmlspecialchars($p['tipo_producto'] ?: 'Tratamiento') ?></span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted fw-semibold"><i class="fa-solid fa-clock-rotate-left me-1 text-warning"></i>Repetir en:</span>
                                        <strong><?= $intervalo > 0 ? $intervalo . ' días' : 'Según necesidad' ?></strong>
                                    </div>
                                    <div class="col-12">
                                        <span class="text-muted fw-semibold"><i class="fa-solid fa-seedling me-1 text-success"></i>Cultivos Autorizados:</span>
                                        <span><?= htmlspecialchars($p['cultivos_autorizados'] ?: 'Olivo, Frutales, Cítricos, Hortícolas') ?></span>
                                    </div>
                                    <div class="col-12">
                                        <span class="text-muted fw-semibold"><i class="fa-solid fa-bug me-1 text-danger"></i>Plagas / Diana:</span>
                                        <span><?= htmlspecialchars($p['plagas_objetivo'] ?: 'Control fúngico y de plagas') ?></span>
                                    </div>
                                </div>

                                <!-- CALCULADORA DE DOSIS POR RECIPIENTES (ESENCIAL) -->
                                <div class="p-3 rounded-3 mb-3 border" style="background: #f8fafc;">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="fw-bold text-dark small">
                                            <i class="fa-solid fa-calculator me-1 text-primary"></i>Dosis Real por Tipo de Recipiente:
                                        </span>
                                        <span class="badge bg-primary bg-opacity-10 text-primary small">
                                            Etiqueta: <?= ($dosis100 < 10 ? number_format($dosis100, 3) . ' kg/hl (' . number_format($gPor100, 0) . ' ' . $uPequena . '/100L)' : number_format($dosis100, 0) . ' ' . $uPequena . '/100L') ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row g-2 text-center" style="font-size: 0.82rem;">
                                        <!-- Mochila 16L -->
                                        <div class="col-6 col-sm-3">
                                            <div class="p-2 bg-white rounded border h-100">
                                                <div class="text-muted small fw-semibold">🎒 Mochila 16L</div>
                                                <div class="fw-bold text-primary fs-6 mt-1"><?= number_format($dMoch, 1, ',', '.') ?></div>
                                                <div class="text-primary fw-semibold" style="font-size: 0.72rem;"><?= $uPequena ?></div>
                                            </div>
                                        </div>

                                        <!-- Pulverizador 100L -->
                                        <div class="col-6 col-sm-3">
                                            <div class="p-2 bg-white rounded border h-100">
                                                <div class="text-muted small fw-semibold">🧴 Pulv. 100L</div>
                                                <div class="fw-bold text-success fs-6 mt-1"><?= number_format($dPulv, 0, ',', '.') ?></div>
                                                <div class="text-success fw-semibold" style="font-size: 0.72rem;"><?= $uPequena ?></div>
                                            </div>
                                        </div>

                                        <!-- Cuba 1.000L -->
                                        <div class="col-6 col-sm-3">
                                            <div class="p-2 bg-white rounded border h-100">
                                                <div class="text-muted small fw-semibold">🚜 Cuba 1000L</div>
                                                <div class="fw-bold text-danger fs-6 mt-1"><?= number_format($dCuba, 2, ',', '.') ?></div>
                                                <div class="text-danger fw-semibold" style="font-size: 0.72rem;"><?= $uGrande ?> (cuba entera)</div>
                                            </div>
                                        </div>

                                        <!-- Por Hectárea -->
                                        <div class="col-6 col-sm-3">
                                            <div class="p-2 bg-white rounded border h-100">
                                                <div class="text-muted small fw-semibold">🌾 Por Ha</div>
                                                <div class="fw-bold text-dark fs-6 mt-1"><?= number_format($dHa, 2, ',', '.') ?></div>
                                                <div class="text-dark fw-semibold" style="font-size: 0.72rem;"><?= $uGrande ?>/ha</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Días de espera para recolectar y Modo de Acción -->
                                <div class="d-flex flex-wrap justify-content-between align-items-center p-2 rounded-2 mb-2 bg-light border small">
                                    <div>
                                        <span class="text-muted fw-semibold"><i class="fa-regular fa-calendar-xmark me-1 text-danger"></i>Días de espera para recolectar:</span>
                                        <span class="badge <?= $plazo === 0 ? 'bg-success' : 'bg-danger' ?> text-white fs-6 ms-1">
                                            <?= $plazo ?> días (Plazo Seg.)
                                        </span>
                                    </div>
                                    <div class="text-muted small">
                                        <?= $plazo === 0 ? 'Exento / Recolección inmediata' : 'Respetar plazo antes de cosecha' ?>
                                    </div>
                                </div>

                                <?php if (!empty($p['modo_accion_uso'])): ?>
                                    <div class="text-muted small mb-2" style="font-size: 0.8rem;">
                                        <strong>Modo de Acción:</strong> <?= htmlspecialchars($p['modo_accion_uso']) ?>
                                    </div>
                                <?php endif; ?>

                            </div>

                            <!-- Botonera de Acciones (No imprimible) -->
                            <div class="card-footer bg-white border-top py-2 d-flex justify-content-between align-items-center no-print">
                                <a href="<?= APP_URL ?>/tratamientos?producto_id=<?= $p['id_producto'] ?>" class="btn btn-sm btn-outline-primary" title="Aplicar en Tratamiento">
                                    <i class="fa-solid fa-spray-can me-1"></i> Aplicar Tratamiento
                                </a>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick='abrirModalEditar(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Editar Ficha">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form action="<?= APP_URL ?>/fitosanitarios" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas archivar este producto? (Se conservará en histórico sin borrar datos)');">
                                        <input type="hidden" name="action" value="borrar_producto">
                                        <input type="hidden" name="id_producto" value="<?= $p['id_producto'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Archivar (Borrado Lógico Seguro)">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>

</div>

<!-- ============================================================== -->
<!-- MODAL DE ALTA / EDICIÓN DE PRODUCTO CON ASISTENTE IA INTEGRADO -->
<!-- ============================================================== -->
<div class="modal fade" id="modalProductoFitosanitario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-primary" id="modalProductoTitulo">
                    <i class="fa-solid fa-bottle-droplet me-2 text-danger"></i>Ficha de Producto Fitosanitario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form action="<?= APP_URL ?>/fitosanitarios" method="POST" id="formProductoFito">
                <input type="hidden" name="action" value="guardar_producto">
                <input type="hidden" name="id_producto" id="fito_id_producto" value="0">

                <div class="modal-body p-4">

                    <!-- SECCIÓN ASISTENTE IA PARA AUTOCOMPLETAR -->
                    <div class="card p-3 mb-4 border border-primary border-opacity-25" style="background: linear-gradient(135deg, #f0f7ff, #ffffff);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-primary small">
                                <i class="fa-solid fa-wand-magic-sparkles me-1 text-warning"></i> Asistente IA de Localización Fitosanitaria
                            </span>
                            <span class="badge bg-primary bg-opacity-10 text-primary small">Registro MAPA y Dosis</span>
                        </div>
                        <div class="input-group">
                            <input type="text" id="ia_fito_busqueda" class="form-control" placeholder="Escribe el nombre del producto (ej: Nordox, Spintor, Score, Bacillus, Cobre, Jabón...)">
                            <button type="button" class="btn btn-primary fw-bold" onclick="consultarIaFitosanitario()">
                                <i class="fa-solid fa-bolt me-1 text-warning"></i> Localizar con IA
                            </button>
                        </div>
                        <div id="ia_fito_spinner" class="text-center py-2 d-none">
                            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                            <span class="small text-primary fw-semibold">Consultando registro agronómico oficial...</span>
                        </div>
                        <div id="ia_fito_feedback" class="small mt-2 text-success fw-semibold d-none"></div>
                    </div>

                    <!-- Datos Generales -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nombre Comercial <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_comercial" id="fito_nombre_comercial" class="form-control" required placeholder="Ej: Nordox 75 WG">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Materia Activa</label>
                            <input type="text" name="materia_activa" id="fito_materia_activa" class="form-control" placeholder="Ej: Óxido cuproso 75%">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Nº Registro Oficial (MAPA)</label>
                            <input type="text" name="num_registro_oficial" id="fito_num_registro" class="form-control font-monospace" placeholder="Ej: ES-00145 o 22.145">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Tipo de Producto</label>
                            <select name="tipo_producto" id="fito_tipo_producto" class="form-select">
                                <option value="Fungicida">🍄 Fungicida</option>
                                <option value="Insecticida">🐛 Insecticida</option>
                                <option value="Acaricida">🕷️ Acaricida</option>
                                <option value="Herbicida">🚜 Herbicida</option>
                                <option value="Bactericida">🧫 Bactericida</option>
                                <option value="Bioestimulante">🌿 Bioestimulante Foliar</option>
                                <option value="Sustancia Básica">🧼 Sustancia Básica</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-center pt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="es_ecologico" id="fito_es_ecologico" value="1">
                                <label class="form-check-label fw-bold text-success small" for="fito_es_ecologico">
                                    <i class="fa-solid fa-leaf me-1"></i>Apto Agricultura Ecológica
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Cultivos y Plagas -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Cultivos Autorizados</label>
                            <input type="text" name="cultivos_autorizados" id="fito_cultivos" class="form-control" placeholder="Ej: Olivo, Almendro, Vid, Nogal, Cítricos, Tomate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Plagas / Enfermedades Diana</label>
                            <input type="text" name="plagas_objetivo" id="fito_plagas" class="form-control" placeholder="Ej: Repilo, Oídio, Mosca, Barrenador, Cochinilla">
                        </div>
                    </div>

                    <!-- CALCULADORA DE DOSIS POR RECIPIENTES -->
                    <div class="card p-3 mb-3 border border-info border-opacity-50" style="background: #f8fafc;">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="fa-solid fa-calculator me-1"></i>Calculadora Inteligente de Dosis por Recipientes
                        </h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Dosis Etiqueta por 100L (hl) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.001" name="dosis_etiqueta_100l" id="fito_dosis_100l" class="form-control font-monospace" placeholder="0.300 o 300" required oninput="calcularDosisRecipientes()">
                                    <select name="unidad_dosis" id="fito_unidad_dosis" class="form-select" style="max-width: 140px;" onchange="calcularDosisRecipientes()">
                                        <option value="kg/hl">kg/hl (o %)</option>
                                        <option value="l/hl">l/hl (o %)</option>
                                        <option value="g/hl">g/hl</option>
                                        <option value="cc/hl">cc/hl</option>
                                        <option value="ml/100L">ml/100L</option>
                                        <option value="g/100L">g/100L</option>
                                        <option value="%">% caldo</option>
                                        <option value="kg">kg</option>
                                        <option value="l">L</option>
                                    </select>
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.72rem;">Ej: 0.300 kg/hl (0.3%) ó 300 g/hl</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Dosis Estimada por Hectárea (ha)</label>
                                <div class="input-group">
                                    <input type="number" step="0.001" name="dosis_por_hectarea" id="fito_dosis_ha" class="form-control font-monospace" placeholder="3.000">
                                    <span class="input-group-text small bg-white text-dark fw-semibold" id="lbl_unidad_ha">kg o L/ha</span>
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.72rem;">Calculada para gasto medio de 1.000 L de caldo/ha</div>
                            </div>
                        </div>

                        <!-- Recipientes calculados automáticamente -->
                        <div class="row g-2 small">
                            <div class="col-md-4">
                                <label class="form-label text-muted small mb-1 fw-semibold">🎒 Mochila 16 Litros:</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="dosis_calculada_mochila" id="fito_dosis_mochila" class="form-control text-center font-monospace fw-bold text-primary bg-white">
                                    <span class="input-group-text small bg-white text-primary fw-bold" id="lbl_unidad_mochila">g</span>
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Dosis para mochila de 16L</div>
                                <input type="hidden" name="volumen_mochila_l" value="16.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small mb-1 fw-semibold">🧴 Pulverizador 100 Litros:</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="dosis_calculada_pulv" id="fito_dosis_pulv" class="form-control text-center font-monospace fw-bold text-success bg-white">
                                    <span class="input-group-text small bg-white text-success fw-bold" id="lbl_unidad_pulv">g</span>
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Dosis para carretilla 100L</div>
                                <input type="hidden" name="volumen_pulverizador_l" value="100.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small mb-1 fw-semibold">🚜 Cuba 1.000 Litros:</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="dosis_calculada_cuba" id="fito_dosis_cuba" class="form-control text-center font-monospace fw-bold text-danger bg-white">
                                    <span class="input-group-text small bg-white text-danger fw-bold" id="lbl_unidad_cuba">kg</span>
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Dosis para cuba entera 1.000L</div>
                                <input type="hidden" name="volumen_cuba_l" value="1000.00">
                            </div>
                        </div>
                    </div>

                    <!-- Plazos de Seguridad e Intervalo de Repetición -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-danger">
                                <i class="fa-regular fa-calendar-xmark me-1"></i>Días de espera para recolectar (Plazo Seguridad)
                            </label>
                            <div class="input-group">
                                <input type="number" name="plazo_seguridad_dias" id="fito_plazo_seguridad" class="form-control font-monospace" value="14" min="0">
                                <span class="input-group-text small bg-white">días</span>
                            </div>
                            <div class="form-text" style="font-size: 0.75rem;">Indicar 0 si está exento de plazo previo a cosecha.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-warning">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i>Intervalo para repetir tratamiento
                            </label>
                            <div class="input-group">
                                <input type="number" name="intervalo_repeticion_dias" id="fito_intervalo_rep" class="form-control font-monospace" value="14" min="0">
                                <span class="input-group-text small bg-white">días</span>
                            </div>
                            <div class="form-text" style="font-size: 0.75rem;">Tiempo de espera recomendado antes de aplicar una segunda pasada.</div>
                        </div>
                    </div>

                    <!-- Modo de Acción y Advertencias -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Modo de Acción y Pautas de Aplicación</label>
                        <textarea name="modo_accion_uso" id="fito_modo_accion" rows="2" class="form-control small" placeholder="Ej: Contacto preventivo superficial multisitio. Tratar con vegetación seca y sin viento."></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Advertencias Técnicas, Toxicología y Compatibilidad</label>
                        <textarea name="descripcion_advertencias" id="fito_advertencias" rows="2" class="form-control small" placeholder="Ej: No mezclar con aceites minerales. Inocuo para polinizadores una vez seco."></textarea>
                    </div>

                </div>

                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Ficha
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
// Apertura de modal en modo nuevo
function abrirModalNuevoProducto() {
    document.getElementById('formProductoFito').reset();
    document.getElementById('fito_id_producto').value = '0';
    document.getElementById('modalProductoTitulo').innerHTML = '<i class="fa-solid fa-bottle-droplet me-2 text-danger"></i>Nuevo Producto Fitosanitario';
    document.getElementById('ia_fito_feedback').classList.add('d-none');
    actualizarBadgesUnidades();
    var modal = new bootstrap.Modal(document.getElementById('modalProductoFitosanitario'));
    modal.show();
}

// Apertura rápida desde botón IA
function abrirModalIaRapida() {
    abrirModalNuevoProducto();
    document.getElementById('ia_fito_busqueda').focus();
}

// Apertura de modal en modo edición
function abrirModalEditar(p) {
    document.getElementById('formProductoFito').reset();
    document.getElementById('fito_id_producto').value = p.id_producto;
    document.getElementById('modalProductoTitulo').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Editar Fitosanitario: ' + p.nombre_comercial;
    
    document.getElementById('fito_nombre_comercial').value = p.nombre_comercial || '';
    document.getElementById('fito_materia_activa').value = p.materia_activa || '';
    document.getElementById('fito_num_registro').value = p.num_registro_oficial || '';
    document.getElementById('fito_tipo_producto').value = p.tipo_producto || 'Fungicida';
    document.getElementById('fito_es_ecologico').checked = (parseInt(p.es_ecologico) === 1);
    document.getElementById('fito_cultivos').value = p.cultivos_autorizados || '';
    document.getElementById('fito_plagas').value = p.plagas_objetivo || '';
    document.getElementById('fito_modo_accion').value = p.modo_accion_uso || '';
    
    document.getElementById('fito_dosis_100l').value = p.dosis_etiqueta_100l || '';
    
    var u = (p.unidad_dosis || '').toLowerCase().trim();
    var uniSel = document.getElementById('fito_unidad_dosis');
    var matched = false;
    for (var i = 0; i < uniSel.options.length; i++) {
        if (uniSel.options[i].value.toLowerCase() === u) {
            uniSel.selectedIndex = i;
            matched = true;
            break;
        }
    }
    if (!matched) {
        if (u.indexOf('kg') !== -1) uniSel.value = 'kg/hl';
        else if (u.indexOf('l') !== -1) uniSel.value = 'l/hl';
        else if (u.indexOf('cc') !== -1 || u.indexOf('ml') !== -1) uniSel.value = 'cc/hl';
        else if (u.indexOf('%') !== -1) uniSel.value = '%';
        else uniSel.value = 'g/hl';
    }

    document.getElementById('fito_dosis_ha').value = p.dosis_por_hectarea || '';
    
    // Normalizar dosis de cuba: si está guardada en gramos (>= 50), convertir a kg/L
    var dCubaVal = parseFloat(p.dosis_calculada_cuba) || 0;
    if (dCubaVal >= 50.0) {
        dCubaVal = dCubaVal / 1000.0;
    }
    document.getElementById('fito_dosis_cuba').value = dCubaVal > 0 ? dCubaVal.toFixed(2) : '';

    // Normalizar dosis de mochila y pulverizador si estaban en kg (< 1.0)
    var dMochVal = parseFloat(p.dosis_calculada_mochila) || 0;
    if (dMochVal > 0 && dMochVal < 1.0) dMochVal = dMochVal * 1000.0;
    document.getElementById('fito_dosis_mochila').value = dMochVal > 0 ? dMochVal.toFixed(1) : '';

    var dPulvVal = parseFloat(p.dosis_calculada_pulv) || 0;
    if (dPulvVal > 0 && dPulvVal < 1.0) dPulvVal = dPulvVal * 1000.0;
    document.getElementById('fito_dosis_pulv').value = dPulvVal > 0 ? dPulvVal.toFixed(0) : '';
    
    document.getElementById('fito_plazo_seguridad').value = p.plazo_seguridad_dias || '0';
    document.getElementById('fito_intervalo_rep').value = p.intervalo_repeticion_dias || '14';
    document.getElementById('fito_advertencias').value = p.descripcion_advertencias || '';
    
    document.getElementById('ia_fito_feedback').classList.add('d-none');
    actualizarBadgesUnidades();

    var modal = new bootstrap.Modal(document.getElementById('modalProductoFitosanitario'));
    modal.show();
}

// Actualiza los badges de unidad (g vs ml, kg vs L) según el tipo de producto
function actualizarBadgesUnidades() {
    var u = (document.getElementById('fito_unidad_dosis').value || '').toLowerCase();
    var mat = (document.getElementById('fito_materia_activa').value || '').toLowerCase();
    var nom = (document.getElementById('fito_nombre_comercial').value || '').toLowerCase();

    var esLiq = (u.indexOf('l') !== -1 || u.indexOf('cc') !== -1 || u.indexOf('ml') !== -1 ||
                 mat.indexOf('[ec]') !== -1 || mat.indexOf('[sc]') !== -1 || mat.indexOf('[sl]') !== -1 ||
                 mat.indexOf('líquid') !== -1 || mat.indexOf('aceite') !== -1 || nom.indexOf('aceite') !== -1);
    
    var uPequena = esLiq ? 'ml' : 'g';
    var uGrande = esLiq ? 'L' : 'kg';

    var lblMoch = document.getElementById('lbl_unidad_mochila');
    var lblPulv = document.getElementById('lbl_unidad_pulv');
    var lblCuba = document.getElementById('lbl_unidad_cuba');
    var lblHa = document.getElementById('lbl_unidad_ha');

    if (lblMoch) lblMoch.textContent = uPequena;
    if (lblPulv) lblPulv.textContent = uPequena;
    if (lblCuba) lblCuba.textContent = uGrande;
    if (lblHa) lblHa.textContent = uGrande + ' o L/ha';

    return { uPequena: uPequena, uGrande: uGrande, esLiq: esLiq };
}

// Cálculo en vivo de dosis por recipientes
function calcularDosisRecipientes() {
    var units = actualizarBadgesUnidades();
    var raw100 = parseFloat(document.getElementById('fito_dosis_100l').value) || 0;
    var u = (document.getElementById('fito_unidad_dosis').value || '').toLowerCase();

    if (raw100 <= 0) {
        document.getElementById('fito_dosis_mochila').value = '';
        document.getElementById('fito_dosis_pulv').value = '';
        document.getElementById('fito_dosis_cuba').value = '';
        return;
    }

    // Normalizar a gramos o ml por 100L de caldo
    // Si la unidad es kg/hl, l/hl, %, o si el valor introducido es < 10 (ej: 0.300 o 1.5):
    // 0.300 kg/hl = 300 g/hl
    var gPor100 = 0;
    var esUnidadMayor = (u.indexOf('kg') !== -1 || u.indexOf('l/hl') !== -1 || u.indexOf('%') !== -1 || u === 'l');
    if (esUnidadMayor || raw100 < 10.0) {
        gPor100 = raw100 * 1000.0;
    } else {
        gPor100 = raw100;
    }

    // Mochila 16L = (gPor100 * 16) / 100 en g o ml (ej: 300 * 0.16 = 48.0 g)
    var dMoch = gPor100 * 0.16;
    // Pulverizador 100L = gPor100 en g o ml (ej: 300 g)
    var dPulv = gPor100;
    // Cuba 1.000L = (gPor100 * 10) / 1000 en KG o LITROS (ej: (300 * 10)/1000 = 3.00 kg)
    var dCuba = (gPor100 * 10.0) / 1000.0;

    document.getElementById('fito_dosis_mochila').value = dMoch > 0 ? dMoch.toFixed(1) : '';
    document.getElementById('fito_dosis_pulv').value = dPulv > 0 ? dPulv.toFixed(0) : '';
    document.getElementById('fito_dosis_cuba').value = dCuba > 0 ? dCuba.toFixed(2) : '';

    // Sugerencia para dosis por hectárea si está vacía (asumiendo 1.000 L de caldo por ha)
    var txtHa = document.getElementById('fito_dosis_ha');
    if (!txtHa.value || parseFloat(txtHa.value) === 0) {
        txtHa.value = dCuba.toFixed(3);
    }
}

// Consulta de producto con IA
function consultarIaFitosanitario() {
    var q = document.getElementById('ia_fito_busqueda').value.trim();
    if (!q) {
        alert("Por favor, introduce el nombre del producto, materia activa o plaga a consultar.");
        return;
    }

    var spinner = document.getElementById('ia_fito_spinner');
    var feedback = document.getElementById('ia_fito_feedback');

    spinner.classList.remove('d-none');
    feedback.classList.add('d-none');

    fetch('<?= APP_URL ?>/api/fitosanitarios/consultar-ia?q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(res) {
            spinner.classList.add('d-none');
            if (res && res.success && res.data) {
                var d = res.data;
                document.getElementById('fito_nombre_comercial').value = d.nombre_comercial_producto || q;
                document.getElementById('fito_materia_activa').value = d.materia_activa || '';
                document.getElementById('fito_num_registro').value = d.num_registro_oficial || '';
                
                if (d.tipo_producto) {
                    var tipoSelect = document.getElementById('fito_tipo_producto');
                    for (var i = 0; i < tipoSelect.options.length; i++) {
                        if (d.tipo_producto.toLowerCase().indexOf(tipoSelect.options[i].value.toLowerCase()) !== -1) {
                            tipoSelect.selectedIndex = i;
                            break;
                        }
                    }
                }

                document.getElementById('fito_es_ecologico').checked = (parseInt(d.es_ecologico) === 1);
                document.getElementById('fito_cultivos').value = d.cultivos_autorizados || 'Olivo, Almendro, Frutales, Vid';
                document.getElementById('fito_plagas').value = d.plaga_o_enfermedad_objetivo || d.plagas_objetivo || '';
                document.getElementById('fito_modo_accion').value = d.modo_accion || '';
                document.getElementById('fito_plazo_seguridad').value = d.plazo_seguridad_dias || 0;
                document.getElementById('fito_intervalo_rep').value = d.intervalo_repeticion_dias || 14;
                document.getElementById('fito_advertencias').value = d.observaciones_tecnicas || '';

                // Dosis
                if (d.dosis_etiqueta_100l) {
                    document.getElementById('fito_dosis_100l').value = d.dosis_etiqueta_100l;
                } else if (d.dosis_recomendada_ha) {
                    document.getElementById('fito_dosis_ha').value = d.dosis_recomendada_ha;
                    // Estimar dosis por 100L (asumiendo 1000L caldo/ha)
                    var d100Est = (parseFloat(d.dosis_recomendada_ha) * 1000) / 10;
                    document.getElementById('fito_dosis_100l').value = d100Est.toFixed(1);
                }

                if (d.unidad_dosis) {
                    var uniSel = document.getElementById('fito_unidad_dosis');
                    var uNorm = d.unidad_dosis.toLowerCase();
                    if (uNorm.indexOf('kg') !== -1 || uNorm.indexOf('%') !== -1) {
                        uniSel.value = 'kg/hl';
                    } else if (uNorm.indexOf('g') !== -1) {
                        uniSel.value = 'g/hl';
                    } else if (uNorm.indexOf('l') !== -1) {
                        uniSel.value = 'l/hl';
                    } else {
                        uniSel.value = 'cc/hl';
                    }
                }

                calcularDosisRecipientes();

                feedback.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Ficha autocompletada por IA desde ' + (d.fuente || 'Registro Oficial');
                feedback.classList.remove('d-none');
            } else {
                alert("No se encontró información técnica para este término. Puedes rellenar la ficha manualmente.");
            }
        })
        .catch(function(err) {
            spinner.classList.add('d-none');
            alert("Error al conectar con el servicio IA: " + err.message);
        });
}

                feedback.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Ficha autocompletada por IA desde ' + (d.fuente || 'Registro Oficial');
                feedback.classList.remove('d-none');
            } else {
                alert("No se encontró información técnica para este término. Puedes rellenar la ficha manualmente.");
            }
        })
        .catch(function(err) {
            spinner.classList.add('d-none');
            alert("Error al conectar con el servicio IA: " + err.message);
        });
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>