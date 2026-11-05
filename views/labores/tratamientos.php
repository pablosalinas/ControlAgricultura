<?php
$pageTitle = "Tratamientos Fitosanitarios y Ecológicos - " . APP_NAME;
require_once VIEWS_PATH . '/layouts/header.php';

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$currentUser = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();

$mensaje = '';
$error = '';

// PROCESAR ACCIONES (CREAR, EDITAR, BORRAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. GUARDAR NUEVO TRATAMIENTO
    if ($action === 'guardar_tratamiento') {
        try {
            $idPlantacion = (int)$_POST['id_plantacion'];
            $fecha = $_POST['fecha_tratamiento'];
            $esEco = isset($_POST['es_ecologico']) ? 1 : 0;
            $producto = trim($_POST['nombre_comercial_producto']);
            $materiaActiva = trim(isset($_POST['materia_activa']) ? $_POST['materia_activa'] : '');
            $registro = trim(isset($_POST['num_registro_oficial']) ? $_POST['num_registro_oficial'] : '');
            
            // Tipo de dosificación y cálculo
            $modoDosis = isset($_POST['modo_dosificacion']) ? $_POST['modo_dosificacion'] : 'ha';
            $dosisEntrada = (float)(isset($_POST['dosis_entrada']) ? $_POST['dosis_entrada'] : 0);
            $unidadEntrada = isset($_POST['unidad_entrada']) ? $_POST['unidad_entrada'] : 'l/ha';
            
            $caldoTotal = !empty($_POST['caldo_total_litros']) ? (float)$_POST['caldo_total_litros'] : null;
            $dosisHaCalculada = (float)(isset($_POST['dosis_por_hectarea']) ? $_POST['dosis_por_hectarea'] : $dosisEntrada);
            $unidadFinal = isset($_POST['unidad_dosis']) ? $_POST['unidad_dosis'] : $unidadEntrada;

            $plazo = (int)(isset($_POST['plazo_seguridad_dias']) ? $_POST['plazo_seguridad_dias'] : 0);
            $fechaFinPlazo = $plazo > 0 ? date('Y-m-d', strtotime($fecha . " + {$plazo} days")) : null;
            $plaga = trim(isset($_POST['plaga_o_enfermedad_objetivo']) ? $_POST['plaga_o_enfermedad_objetivo'] : '');
            $aplicador = trim(isset($_POST['aplicador_responsable']) ? $_POST['aplicador_responsable'] : $currentUser['nombre_completo']);
            $obs = trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : '');

            // Añadir nota de modo de dosificación en observaciones si fue por mochila/m2/remolque
            $notaModo = "";
            if ($modoDosis === 'mochila_17') {
                $numMoch = isset($_POST['num_mochilas']) ? (float)$_POST['num_mochilas'] : 1;
                $notaModo = "[Aplicación: Mochila 17L | Dosis: {$dosisEntrada} {$unidadEntrada}/mochila | {$numMoch} mochilas]";
            } elseif ($modoDosis === 'mochila_5') {
                $numMoch = isset($_POST['num_mochilas']) ? (float)$_POST['num_mochilas'] : 1;
                $notaModo = "[Aplicación: Mochila 5L | Dosis: {$dosisEntrada} {$unidadEntrada}/mochila | {$numMoch} mochilas]";
            } elseif ($modoDosis === 'mochila_2') {
                $numMoch = isset($_POST['num_mochilas']) ? (float)$_POST['num_mochilas'] : 1;
                $notaModo = "[Aplicación: Pulverizador 2L | Dosis: {$dosisEntrada} {$unidadEntrada}/carga | {$numMoch} cargas]";
            } elseif ($modoDosis === 'remolque') {
                $capRem = isset($_POST['capacidad_remolque']) ? (float)$_POST['capacidad_remolque'] : 1000;
                $notaModo = "[Aplicación: Remolque/Cuba {$capRem}L | Dosis: {$dosisEntrada} {$unidadEntrada}]";
            } elseif ($modoDosis === 'm2') {
                $notaModo = "[Aplicación por m²: {$dosisEntrada} {$unidadEntrada}]";
            }

            if (!empty($notaModo)) {
                $obs = trim($notaModo . ($obs ? " - " . $obs : ""));
            }

            $idHerramienta = !empty($_POST['id_herramienta']) ? (int)$_POST['id_herramienta'] : null;
            $maquinaria = trim(isset($_POST['maquinaria_empleada']) ? $_POST['maquinaria_empleada'] : '');
            $idProducto = !empty($_POST['id_producto']) ? (int)$_POST['id_producto'] : null;

            // Si no se envió id_producto explícito, buscar coincidencia por nombre comercial en catálogo
            if (empty($idProducto) && !empty($producto)) {
                $stmtCheck = $db->prepare("SELECT id_producto FROM productos_fitosanitarios WHERE nombre_comercial = :nom AND activo = 1 LIMIT 1");
                $stmtCheck->execute(array(':nom' => $producto));
                $foundP = $stmtCheck->fetch();
                if ($foundP) {
                    $idProducto = (int)$foundP['id_producto'];
                }
            }

            $stmt = $db->prepare("
                INSERT INTO tratamientos (
                    id_plantacion, id_producto, id_herramienta, maquinaria_empleada, fecha_tratamiento, es_ecologico, nombre_comercial_producto,
                    materia_activa, num_registro_oficial, dosis_por_hectarea, unidad_dosis,
                    caldo_total_litros, plazo_seguridad_dias, fecha_fin_plazo_seguridad,
                    plaga_o_enfermedad_objetivo, aplicador_responsable, observaciones, creado_por_id
                ) VALUES (
                    :plantacion, :producto_id, :herramienta, :maquinaria, :fecha, :eco, :prod,
                    :mat, :reg, :dosis, :unidad,
                    :caldo, :plazo, :finplazo,
                    :plaga, :aplicador, :obs, :user
                )
            ");
            $stmt->execute(array(
                ':plantacion'  => $idPlantacion,
                ':producto_id' => $idProducto,
                ':herramienta' => $idHerramienta,
                ':maquinaria'  => $maquinaria ?: null,
                ':fecha'       => $fecha,
                ':eco'         => $esEco,
                ':prod'        => $producto,
                ':mat'         => $materiaActiva,
                ':reg'         => $registro,
                ':dosis'       => $dosisHaCalculada,
                ':unidad'      => $unidadFinal,
                ':caldo'       => $caldoTotal,
                ':plazo'       => $plazo,
                ':finplazo'    => $fechaFinPlazo,
                ':plaga'       => $plaga,
                ':aplicador'   => $aplicador,
                ':obs'         => $obs,
                ':user'        => $currentUser['id']
            ));
            $mensaje = "Tratamiento registrado correctamente con cálculo de dosis y caldo.";
        } catch (Exception $e) {
            $error = "Error al registrar el tratamiento: " . $e->getMessage();
        }
    }

    // 2. MODIFICAR / EDITAR TRATAMIENTO EXISTENTE
    elseif ($action === 'editar_tratamiento') {
        try {
            $idTrat = (int)$_POST['id_tratamiento'];
            $fecha = $_POST['fecha_tratamiento'];
            $esEco = isset($_POST['es_ecologico']) ? 1 : 0;
            $producto = trim($_POST['nombre_comercial_producto']);
            $idProducto = !empty($_POST['id_producto']) ? (int)$_POST['id_producto'] : null;

            if (empty($idProducto) && !empty($producto)) {
                $stmtCheck = $db->prepare("SELECT id_producto FROM productos_fitosanitarios WHERE nombre_comercial = :nom AND activo = 1 LIMIT 1");
                $stmtCheck->execute(array(':nom' => $producto));
                $foundP = $stmtCheck->fetch();
                if ($foundP) {
                    $idProducto = (int)$foundP['id_producto'];
                }
            }

            $materiaActiva = trim(isset($_POST['materia_activa']) ? $_POST['materia_activa'] : '');
            $registro = trim(isset($_POST['num_registro_oficial']) ? $_POST['num_registro_oficial'] : '');
            $dosisHa = (float)$_POST['dosis_por_hectarea'];
            $unidad = isset($_POST['unidad_dosis']) ? $_POST['unidad_dosis'] : 'l/ha';
            $caldo = !empty($_POST['caldo_total_litros']) ? (float)$_POST['caldo_total_litros'] : null;
            $plazo = (int)(isset($_POST['plazo_seguridad_dias']) ? $_POST['plazo_seguridad_dias'] : 0);
            $fechaFinPlazo = $plazo > 0 ? date('Y-m-d', strtotime($fecha . " + {$plazo} days")) : null;
            $plaga = trim(isset($_POST['plaga_o_enfermedad_objetivo']) ? $_POST['plaga_o_enfermedad_objetivo'] : '');
            $aplicador = trim(isset($_POST['aplicador_responsable']) ? $_POST['aplicador_responsable'] : '');
            $obs = trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : '');

            $stmtUpd = $db->prepare("
                UPDATE tratamientos
                SET id_producto = :prod_id, fecha_tratamiento = :fecha, es_ecologico = :eco, nombre_comercial_producto = :prod,
                    materia_activa = :mat, num_registro_oficial = :reg, dosis_por_hectarea = :dosis,
                    unidad_dosis = :unidad, caldo_total_litros = :caldo, plazo_seguridad_dias = :plazo,
                    fecha_fin_plazo_seguridad = :finplazo, plaga_o_enfermedad_objetivo = :plaga,
                    aplicador_responsable = :aplicador, observaciones = :obs
                WHERE id_tratamiento = :id
            ");
            $stmtUpd->execute(array(
                ':prod_id'   => $idProducto,
                ':fecha'     => $fecha,
                ':eco'       => $esEco,
                ':prod'      => $producto,
                ':mat'       => $materiaActiva,
                ':reg'       => $registro,
                ':dosis'     => $dosisHa,
                ':unidad'    => $unidad,
                ':caldo'     => $caldo,
                ':plazo'     => $plazo,
                ':finplazo'  => $fechaFinPlazo,
                ':plaga'     => $plaga,
                ':aplicador' => $aplicador,
                ':obs'       => $obs,
                ':id'        => $idTrat
            ));
            $mensaje = "Tratamiento actualizado correctamente.";
        } catch (Exception $e) {
            $error = "Error al actualizar tratamiento: " . $e->getMessage();
        }
    }

    // 3. ELIMINAR / BORRAR TRATAMIENTO (Borrado lógico estricto GEMINI.md)
    elseif ($action === 'borrar_tratamiento') {
        try {
            $idTrat = (int)$_POST['id_tratamiento'];
            $stmtDel = $db->prepare("UPDATE tratamientos SET activo = 0 WHERE id_tratamiento = :id");
            $stmtDel->execute(array(':id' => $idTrat));
            $mensaje = "Tratamiento archivado y descatalogado correctamente (borrado lógico seguro).";
        } catch (Exception $e) {
            $error = "Error al eliminar tratamiento: " . $e->getMessage();
        }
    }
}

// Filtros de búsqueda
$filtroFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
$filtroEco = isset($_GET['eco']) ? trim($_GET['eco']) : '';
$filtroTxt = isset($_GET['q']) ? trim($_GET['q']) : '';

// Plantaciones disponibles para formulario
$sqlPlant = "
    SELECT p.id_plantacion, p.identificador_cuadro, p.superficie_ocupada_ha, f.id_finca, f.nombre_finca, c.nombre_comun
    FROM plantaciones p
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE 1=1
";
if ($tenantId && !$isSuperAdmin) {
    $sqlPlant .= " AND f.id_propietario = " . (int)$tenantId;
}
$sqlPlant .= " ORDER BY f.nombre_finca ASC, p.identificador_cuadro ASC";
$plantaciones = $db->query($sqlPlant)->fetchAll();

// Listado de fincas para filtro
$sqlFincas = "SELECT id_finca, nombre_finca FROM fincas WHERE activo = 1";
if ($tenantId && !$isSuperAdmin) {
    $sqlFincas .= " AND id_propietario = " . (int)$tenantId;
}
$sqlFincas .= " ORDER BY nombre_finca ASC";
$fincasList = $db->query($sqlFincas)->fetchAll();

// Cargar catálogo de herramientas y maquinaria activa de la explotación
$sqlMaq = "SELECT id_herramienta, nombre, tipo, categoria, marca, modelo FROM maquinaria_herramientas WHERE activo = 1";
if ($tenantId && !$isSuperAdmin) {
    $sqlMaq .= " AND (id_propietario = " . (int)$tenantId . " OR id_propietario IS NULL)";
}
$sqlMaq .= " ORDER BY tipo ASC, nombre ASC";
$maquinariaList = $db->query($sqlMaq)->fetchAll();

// Cargar catálogo de productos fitosanitarios registrados oficialmente
$sqlProdFito = "SELECT * FROM productos_fitosanitarios WHERE activo = 1 ORDER BY nombre_comercial ASC";
$productosCatalogo = $db->query($sqlProdFito)->fetchAll();

// Histórico de tratamientos con filtros
$sqlTrat = "
    SELECT t.*, f.id_finca, f.nombre_finca, c.nombre_comun, p.identificador_cuadro, p.superficie_ocupada_ha,
           mh.nombre AS nombre_herramienta,
           pf.nombre_comercial AS pf_nombre_comercial, pf.dosis_calculada_mochila, pf.dosis_calculada_cuba, pf.intervalo_repeticion_dias
    FROM tratamientos t
    JOIN plantaciones p ON t.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    LEFT JOIN maquinaria_herramientas mh ON t.id_herramienta = mh.id_herramienta
    LEFT JOIN productos_fitosanitarios pf ON t.id_producto = pf.id_producto
    WHERE (t.activo = 1 OR t.activo IS NULL)
";
$params = array();

if ($tenantId && !$isSuperAdmin) {
    $sqlTrat .= " AND f.id_propietario = :tenant";
    $params[':tenant'] = (int)$tenantId;
}

if ($filtroFinca > 0) {
    $sqlTrat .= " AND f.id_finca = :finca";
    $params[':finca'] = $filtroFinca;
}

if ($filtroEco === '1') {
    $sqlTrat .= " AND t.es_ecologico = 1";
} elseif ($filtroEco === '0') {
    $sqlTrat .= " AND t.es_ecologico = 0";
}

if (!empty($filtroTxt)) {
    $sqlTrat .= " AND (t.nombre_comercial_producto LIKE :txt OR t.materia_activa LIKE :txt OR t.plaga_o_enfermedad_objetivo LIKE :txt)";
    $params[':txt'] = '%' . $filtroTxt . '%';
}

$sqlTrat .= " ORDER BY t.fecha_tratamiento DESC, t.id_tratamiento DESC";
$stmtTrat = $db->prepare($sqlTrat);
$stmtTrat->execute($params);
$tratamientos = $stmtTrat->fetchAll();
?>

<div class="container py-4">

    <!-- Cabecera Corporativa -->
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                <i class="fa-solid fa-shield-virus fs-3 text-info"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -0.02em;">Tratamientos Fitosanitarios y Ecológicos</h2>
                <p class="text-muted mb-0 small">Control agronómico de plagas, dosificación por hectárea, m² o mochilas (17L, 5L, 2L, remolque) y trazabilidad oficial</p>
            </div>
        </div>
        <button class="btn btn-ag shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formNuevoTratamiento">
            <i class="fa-solid fa-plus-circle me-1"></i> Registrar Tratamiento
        </button>
    </div>

    <!-- Alertas -->
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Formulario Desplegable para Registrar Tratamiento con Selector de Dosificación -->
    <div class="collapse mb-4" id="formNuevoTratamiento">
        <div class="card card-ag p-4 p-md-5 border-top border-4 border-primary">
            <h4 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-calculator text-info me-2"></i>Nuevo Registro de Tratamiento con Dosificador</h4>
            <form action="<?= APP_URL ?>/tratamientos" method="POST" id="formTratamiento">
                <input type="hidden" name="action" value="guardar_tratamiento">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Finca y Plantación Objetivo <span class="text-danger">*</span></label>
                        <select name="id_plantacion" id="sel_plantacion" class="form-select" required onchange="actualizarSuperficie()">
                            <option value="" data-superficie="1">-- Seleccionar Plantación --</option>
                            <?php foreach ($plantaciones as $pl): ?>
                                <option value="<?= $pl['id_plantacion'] ?>" data-superficie="<?= $pl['superficie_ocupada_ha'] ?>">
                                    <?= htmlspecialchars($pl['nombre_finca'] . ' - ' . $pl['nombre_comun'] . ' (' . $pl['identificador_cuadro'] . ' - ' . number_format($pl['superficie_ocupada_ha'], 2) . ' ha)') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Fecha de Aplicación <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_tratamiento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-center pt-3">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="es_ecologico" name="es_ecologico" value="1">
                            <label class="form-check-label fw-bold text-success fs-6" for="es_ecologico">
                                <i class="fa-solid fa-leaf me-1"></i> Certificado Ecológico
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ASISTENTE INTELIGENTE IA: LOCALIZADOR DE PRODUCTOS FITOSANITARIOS Y REGISTRO OFICIAL -->
                <div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #f0f7ff 0%, #e0f2fe 100%); border-left: 4px solid #0284c7 !important;">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary p-2 rounded-circle"><i class="fa-solid fa-wand-magic-sparkles text-warning fs-6"></i></span>
                                <div>
                                    <h6 class="fw-bold mb-0 text-primary">Localizador Inteligente de Fitosanitarios (IA & Registro MAPA)</h6>
                                    <span class="text-muted small">Busca cualquier producto comercial, materia activa o plaga para obtener su funcionalidad oficial y autocompletar la ficha</span>
                                </div>
                            </div>
                            <span class="badge bg-white text-primary border border-primary-subtle px-3 py-1 rounded-pill small">
                                <i class="fa-solid fa-shield-halved me-1 text-success"></i>Registro Oficial UE / España
                            </span>
                        </div>

                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-md-9">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                                    <input type="text" id="ia_producto_busqueda" class="form-control border-start-0" placeholder="Escribe el nombre del producto (ej: Nordox, Spintor, Cobre, Bacillus, Karate, Score, Roundup...) o plaga (ej: Repilo, Oídio, Pulgón)..." autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="btnConsultarIaProducto" class="btn btn-primary fw-bold w-100 shadow-sm" onclick="consultarProductoIa()">
                                    <i class="fa-solid fa-bolt me-1 text-warning"></i> Consultar con IA
                                </button>
                            </div>
                        </div>

                        <!-- Píldoras de búsqueda rápida -->
                        <div class="d-flex flex-wrap align-items-center gap-1 mt-2">
                            <span class="small text-muted me-1 fw-semibold"><i class="fa-solid fa-tags me-1"></i>Frecuentes:</span>
                            <button type="button" class="btn btn-sm btn-white bg-white border py-0 px-2 rounded-pill small" onclick="seleccionarPildora('Nordox 75 WG')">🌿 Nordox 75 (Cobre Bio)</button>
                            <button type="button" class="btn btn-sm btn-white bg-white border py-0 px-2 rounded-pill small" onclick="seleccionarPildora('Spintor 480 SC')">🐛 Spintor (Spinosad Bio)</button>
                            <button type="button" class="btn btn-sm btn-white bg-white border py-0 px-2 rounded-pill small" onclick="seleccionarPildora('Bacillus thuringiensis')">🌱 Bacillus (Orugas Bio)</button>
                            <button type="button" class="btn btn-sm btn-white bg-white border py-0 px-2 rounded-pill small" onclick="seleccionarPildora('Kumulus DF')">🌾 Kumulus (Azufre Bio)</button>
                            <button type="button" class="btn btn-sm btn-white bg-white border py-0 px-2 rounded-pill small" onclick="seleccionarPildora('Jabón Potásico')">🧼 Jabón Potásico (Bio)</button>
                            <button type="button" class="btn btn-sm btn-white bg-white border py-0 px-2 rounded-pill small" onclick="seleccionarPildora('Karate Zeon')">⚡ Karate Zeon (Piretroide)</button>
                            <button type="button" class="btn btn-sm btn-white bg-white border py-0 px-2 rounded-pill small" onclick="seleccionarPildora('Score 25 EC')">🔬 Score (Difenoconazol)</button>
                            <button type="button" class="btn btn-sm btn-white bg-white border py-0 px-2 rounded-pill small" onclick="seleccionarPildora('Roundup Ultra')">🚜 Roundup (Glifosato)</button>
                        </div>

                        <!-- Indicador de carga -->
                        <div id="ia_producto_spinner" class="mt-3 text-center py-2 d-none">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            <span class="text-primary fw-semibold small">Consultando Registro Oficial Fitosanitario, Trampas y Biorreguladores...</span>
                        </div>

                        <!-- Opciones múltiples encontradas para seleccionar -->
                        <div id="ia_producto_opciones_wrapper" class="mt-3 d-none">
                            <div class="small fw-bold text-dark mb-2" id="ia_producto_opciones_count">
                                <i class="fa-solid fa-list-check me-1 text-primary"></i>Selecciona la opción más adecuada para este tratamiento:
                            </div>
                            <div id="ia_producto_opciones_list" class="d-flex flex-column gap-2 mb-2" style="max-height: 250px; overflow-y: auto;"></div>
                        </div>

                        <!-- Tarjeta con Resultado Encontrado y Botón de Relleno Automático -->
                        <div id="ia_producto_resultado" class="mt-3 card border-success bg-white shadow-sm p-3 rounded-3 d-none">
                            <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-2 gap-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-circle-check text-success"></i>
                                        <span id="res_ia_nombre" class="text-primary"></span>
                                        <span id="res_ia_badge_eco"></span>
                                    </h6>
                                    <span id="res_ia_fuente" class="text-muted" style="font-size: 0.75rem;"></span>
                                </div>
                                <button type="button" class="btn btn-success fw-bold btn-sm shadow-sm px-3" onclick="aplicarDatosIaEnFormulario()">
                                    <i class="fa-solid fa-file-circle-check me-1"></i> Rellenar Ficha Automáticamente
                                </button>
                            </div>
                            <div class="row g-2 small">
                                <div class="col-md-6">
                                    <div><strong>Materia Activa:</strong> <span id="res_ia_materia" class="text-dark"></span></div>
                                    <div><strong>Nº Registro MAPA:</strong> <span id="res_ia_registro" class="badge bg-light text-dark border font-monospace"></span></div>
                                    <div><strong>Funcionalidad:</strong> <span id="res_ia_tipo" class="text-primary fw-semibold"></span></div>
                                </div>
                                <div class="col-md-6">
                                    <div><strong>Plaga / Diana:</strong> <span id="res_ia_plaga" class="text-dark"></span></div>
                                    <div><strong>Dosis Recomendada:</strong> <span id="res_ia_dosis" class="fw-bold text-success font-monospace"></span></div>
                                    <div><strong>Plazo Seguridad:</strong> <span id="res_ia_plazo" class="fw-bold text-danger"></span> días</div>
                                </div>
                                <div class="col-12 mt-2 pt-2 border-top">
                                    <div class="text-muted" style="font-size:0.8rem;"><i class="fa-solid fa-circle-info text-info me-1"></i><span id="res_ia_obs"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="id_producto" id="trat_id_producto" value="0">
                
                <!-- Selector Inteligente desde el Catálogo Oficial de Fitosanitarios -->
                <div class="p-3 mb-3 rounded-3 border bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-bold small text-primary mb-0">
                            <i class="fa-solid fa-bottle-droplet me-1 text-danger"></i>Seleccionar desde Catálogo Oficial de Fitosanitarios Registrados:
                        </label>
                        <a href="<?= APP_URL ?>/fitosanitarios" target="_blank" class="small text-decoration-none fw-semibold">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Gestionar Catálogo y Recipientes
                        </a>
                    </div>
                    <select id="selector_fito_catalogo" class="form-select form-select-sm fw-semibold text-dark" onchange="cargarProductoDesdeCatalogo(this)">
                        <option value="">-- Seleccionar producto registrado para rellenar automáticamente la ficha y dosis --</option>
                        <?php foreach ($productosCatalogo as $pc): ?>
                            <option value="<?= $pc['id_producto'] ?>" data-json='<?= json_encode($pc, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                <?= htmlspecialchars($pc['nombre_comercial']) ?> (<?= $pc['es_ecologico'] ? '🌱 Eco' : '🌾 Conv' ?>) - Dosis: <?= $pc['dosis_etiqueta_100l'] ?> <?= $pc['unidad_dosis'] ?> | P.Seg: <?= $pc['plazo_seguridad_dias'] ?>d
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Nombre Comercial del Producto <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_comercial_producto" id="nombre_comercial_producto" class="form-control" required placeholder="Ej: Cobre Nordox, Spintor 480, Bacillus thuringiensis...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Materia Activa</label>
                        <input type="text" name="materia_activa" id="materia_activa" class="form-control" placeholder="Ej: Óxido cuproso 75%, Spinosad...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Nº Registro Oficial (MAPA)</label>
                        <input type="text" name="num_registro_oficial" id="num_registro_oficial" class="form-control" placeholder="Ej: 22.145">
                    </div>
                </div>

                <!-- SECCIÓN DE DOSIFICACIÓN AVANZADA (Por Ha, m², Mochilas 17L, 5L, 2L, Remolque) -->
                <div class="card p-3 p-md-4 mb-4 border border-info border-opacity-50" style="background: #f0f7ff;">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h6 class="fw-bold mb-0 text-primary">
                            <i class="fa-solid fa-spray-can me-2"></i>Método de Dosificación y Equipo Utilizado
                        </h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary small">Cálculo de caldo inteligente</span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Equipo / Modo de Dosificación</label>
                            <select name="modo_dosificacion" id="modo_dosificacion" class="form-select fw-semibold" onchange="cambiarModoDosificacion()">
                                <option value="ha">🚜 Dosis por Hectárea (ha)</option>
                                <option value="m2">📐 Dosis por Metro Cuadrado (m²)</option>
                                <option value="mochila_17">🎒 Mochila de 17 Litros</option>
                                <option value="mochila_5">🎒 Mochila de 5 Litros</option>
                                <option value="mochila_2">🧴 Pulverizador manual de 2 Litros</option>
                                <option value="remolque">🚜 Remolque / Cuba de Tratamiento</option>
                            </select>
                        </div>

                        <!-- Campos dinámicos según el modo -->
                        <div class="col-md-4" id="grp_dosis_entrada">
                            <label class="form-label fw-semibold small" id="lbl_dosis_entrada">Dosis</label>
                            <div class="input-group">
                                <input type="number" step="0.001" name="dosis_entrada" id="dosis_entrada" class="form-control font-monospace" placeholder="1.500" required oninput="calcularCaldoYDosis()">
                                <select name="unidad_entrada" id="unidad_entrada" class="form-select" style="max-width: 110px;" onchange="calcularCaldoYDosis()">
                                    <option value="l/ha">L/ha</option>
                                    <option value="kg/ha">kg/ha</option>
                                    <option value="cc/hl">cc/hl</option>
                                    <option value="g/hl">g/hl</option>
                                    <option value="ml">ml</option>
                                    <option value="g">g</option>
                                </select>
                            </div>
                        </div>

                        <!-- Número de mochilas o capacidad de remolque -->
                        <div class="col-md-4" id="grp_cantidad_equipo" style="display: none;">
                            <label class="form-label fw-semibold small" id="lbl_cantidad_equipo">Nº de Mochilas / Cargas gastadas</label>
                            <input type="number" step="0.5" name="num_mochilas" id="num_mochilas" class="form-control font-monospace" value="1" oninput="calcularCaldoYDosis()">
                        </div>

                        <div class="col-md-4" id="grp_capacidad_remolque" style="display: none;">
                            <label class="form-label fw-semibold small">Capacidad Cuba / Remolque (Litros)</label>
                            <select name="capacidad_remolque" id="capacidad_remolque" class="form-select" onchange="aplicarDosisSegunDispositivo(); calcularCaldoYDosis();">
                                <option value="600">Cuba 600 Litros</option>
                                <option value="1000" selected>Cuba 1.000 Litros</option>
                                <option value="1500">Cuba 1.500 Litros</option>
                                <option value="2000">Cuba 2.000 Litros</option>
                                <option value="3000">Cuba 3.000 Litros</option>
                            </select>
                        </div>
                    </div>

                    <!-- Resultados calculados automáticamente para el Cuaderno Oficial -->
                    <div class="row g-3 p-3 bg-white rounded-3 border">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">Dosis por Hectárea (Equivalente Cuaderno) *</label>
                            <input type="number" step="0.001" name="dosis_por_hectarea" id="dosis_por_hectarea" class="form-control font-monospace fw-bold text-primary" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">Unidad Final Cuaderno</label>
                            <select name="unidad_dosis" id="unidad_dosis" class="form-select font-monospace">
                                <option value="l/ha">Litros / ha</option>
                                <option value="kg/ha">Kilos / ha</option>
                                <option value="cc/hl">cc / 100 litros (hl)</option>
                                <option value="g/hl">gramos / 100 litros (hl)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">Volumen Caldo Total (Litros)</label>
                            <input type="number" step="0.1" name="caldo_total_litros" id="caldo_total_litros" class="form-control font-monospace fw-bold text-success" placeholder="Ej: 1000">
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Plazo Seguridad (Días) <span class="text-danger">*</span></label>
                        <input type="number" name="plazo_seguridad_dias" class="form-control" value="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Plaga o Enfermedad</label>
                        <input type="text" name="plaga_o_enfermedad_objetivo" class="form-control" placeholder="Ej: Oídio, Mosca fruta...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Aplicador Responsable</label>
                        <input type="text" name="aplicador_responsable" class="form-control" value="<?= htmlspecialchars($currentUser['nombre_completo']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small text-primary"><i class="fa-solid fa-wrench me-1"></i>Maquinaria Empleada</label>
                        <select name="id_herramienta" class="form-select" onchange="if(this.value){ document.getElementById('inputMaqTrat').value = this.options[this.selectedIndex].getAttribute('data-nombre'); }">
                            <option value="">-- De Inventario (Opcional) --</option>
                            <?php foreach ($maquinariaList as $eq): ?>
                                <option value="<?= $eq['id_herramienta'] ?>" data-nombre="<?= htmlspecialchars($eq['nombre']) ?>">
                                    <?= htmlspecialchars($eq['nombre'] . ($eq['marca'] ? ' (' . $eq['marca'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="maquinaria_empleada" id="inputMaqTrat" value="">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Observaciones Técnicas o Condiciones Meteorológicas</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Velocidad del viento, temperatura, tipo de boquillas..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-toggle="collapse" data-bs-target="#formNuevoTratamiento">Cancelar</button>
                    <button type="submit" class="btn btn-ag px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar en Dietario Oficial
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="card card-ag p-3 mb-4 bg-light border-0 shadow-sm">
        <form action="<?= APP_URL ?>/tratamientos" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar producto, materia activa o plaga..." value="<?= htmlspecialchars($filtroTxt) ?>">
            </div>
            <div class="col-md-3">
                <select name="finca_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Todas las Fincas --</option>
                    <?php foreach ($fincasList as $fl): ?>
                        <option value="<?= $fl['id_finca'] ?>" <?= ($filtroFinca === (int)$fl['id_finca']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fl['nombre_finca']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="eco" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los tipos (Eco/Conv) --</option>
                    <option value="1" <?= $filtroEco === '1' ? 'selected' : '' ?>>🌱 Solo Ecológicos Certificados</option>
                    <option value="0" <?= $filtroEco === '0' ? 'selected' : '' ?>>🧪 Solo Convencionales</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Filtrar</button>
                <?php if ($filtroTxt || $filtroFinca || $filtroEco !== ''): ?>
                    <a href="<?= APP_URL ?>/tratamientos" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Histórico de Tratamientos Registrados (ÁREA DE INFORME MAQUETADA PARA A4) -->
    <div class="card card-ag shadow-sm border-0 mb-4" id="area-informe-tratamientos">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 d-print-none">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-shield text-danger fs-5"></i>
                <h6 class="fw-bold mb-0 text-primary">Informe Oficial de Tratamientos Fitosanitarios (<?= count($tratamientos) ?> aplicaciones)</h6>
            </div>
            <div>
                <?php 
                    $reportTitle = "Informe Oficial de Tratamientos Fitosanitarios y Ecologicos";
                    $reportFileName = "informe_tratamientos_fitosanitarios_" . date('Ymd_His');
                    $reportContainerId = "area-informe-tratamientos";
                    require VIEWS_PATH . '/layouts/toolbar_informes.php';
                ?>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- Encabezado A4 Oficial -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div>
                    <h4 class="fw-bold text-primary mb-1">
                        <i class="fa-solid fa-shield-virus me-2 text-danger"></i><?= htmlspecialchars(Auth::user()['nombre_propietario'] ?: APP_NAME) ?>
                    </h4>
                    <div class="small text-muted">Cuaderno de Explotación Agraria - Registro Oficial de Tratamientos Fitosanitarios y Ecológicos</div>
                    <div class="small text-secondary">Fecha de emisión: <strong><?= date('d/m/Y H:i') ?></strong> | Responsable: <?= htmlspecialchars($currentUser['nombre_completo']) ?></div>
                </div>
                <div class="text-end">
                    <span class="badge bg-danger px-3 py-2 fs-6 rounded-pill">SANIDAD VEGETAL</span>
                    <div class="small text-muted mt-1 font-monospace">Real Decreto 1311/2012 / Uso Sostenible</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light small text-uppercase fw-bold text-secondary">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Finca / Parcela</th>
                            <th>Manejo</th>
                            <th>Producto / Materia Activa / Reg. MAPA</th>
                            <th class="text-center">Dosis y Caldo</th>
                            <th>Plaga Objetivo</th>
                            <th class="text-center">Plazo Seg.</th>
                            <th>Aplicador</th>
                            <th class="text-end pe-3 d-print-none">Acciones</th>
                        </tr>
                    </thead>
                <tbody>
                    <?php if (empty($tratamientos)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-shield-virus fa-2x mb-3 text-secondary"></i>
                                <p class="mb-0">No se encontraron tratamientos fitosanitarios registrados.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tratamientos as $t): ?>
                            <tr>
                                <td class="ps-4 fw-bold small text-dark">
                                    <i class="fa-solid fa-calendar-day text-primary me-1"></i><?= date('d/m/Y', strtotime($t['fecha_tratamiento'])) ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($t['nombre_finca']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($t['nombre_comun']) ?> (<?= htmlspecialchars($t['identificador_cuadro']) ?>)</div>
                                </td>
                                <td>
                                    <?php if ($t['es_ecologico']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle fw-bold"><i class="fa-solid fa-leaf me-1"></i>Ecológico</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle">Convencional</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <?= htmlspecialchars($t['nombre_comercial_producto']) ?>
                                        <?php if (!empty($t['id_producto'])): ?>
                                            <a href="<?= APP_URL ?>/fitosanitarios?q=<?= urlencode($t['nombre_comercial_producto']) ?>" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1 text-decoration-none" style="font-size: 0.72rem;" title="Ver ficha en catálogo de fitosanitarios">
                                                <i class="fa-solid fa-bottle-droplet me-1 text-danger"></i>Catálogo
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted"><?= htmlspecialchars($t['materia_activa'] ?: '-') ?></div>
                                    <?php if ($t['num_registro_oficial']): ?>
                                        <span class="badge bg-light text-muted border font-monospace" style="font-size:0.7rem;">Reg: <?= htmlspecialchars($t['num_registro_oficial']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary font-monospace"><?= $t['dosis_por_hectarea'] ?> <?= htmlspecialchars($t['unidad_dosis']) ?></div>
                                    <?php if ($t['caldo_total_litros']): ?>
                                        <div class="small text-success"><i class="fa-solid fa-droplet me-1"></i><?= number_format($t['caldo_total_litros'], 0, ',', '.') ?> L caldo</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($t['plaga_o_enfermedad_objetivo'] ?: 'General') ?></span>
                                </td>
                                <td>
                                    <?php if ($t['plazo_seguridad_dias'] > 0): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i><?= $t['plazo_seguridad_dias'] ?> días</span>
                                        <div class="small text-danger fw-semibold mt-1">Hasta: <?= date('d/m/Y', strtotime($t['fecha_fin_plazo_seguridad'])) ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">0 días (Exento)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <div><?= htmlspecialchars($t['aplicador_responsable'] ?: '-') ?></div>
                                    <?php if (!empty($t['maquinaria_empleada']) || !empty($t['nombre_herramienta'])): ?>
                                        <div class="small text-primary"><i class="fa-solid fa-wrench me-1"></i><?= htmlspecialchars($t['maquinaria_empleada'] ?: $t['nombre_herramienta']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Modificar -->
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditar_<?= $t['id_tratamiento'] ?>" title="Modificar tratamiento">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Borrar -->
                                        <form action="<?= APP_URL ?>/tratamientos" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este tratamiento fitosanitario del dietario?');">
                                            <input type="hidden" name="action" value="borrar_tratamiento">
                                            <input type="hidden" name="id_tratamiento" value="<?= $t['id_tratamiento'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Eliminar registro">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Modificar Tratamiento -->
                                    <div class="modal fade text-start" id="modalEditar_<?= $t['id_tratamiento'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header text-white" style="background: linear-gradient(135deg, #091e3a, #1d4ed8);">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-pen-to-square me-2"></i> Modificar Tratamiento: <?= htmlspecialchars($t['nombre_comercial_producto']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="<?= APP_URL ?>/tratamientos" method="POST">
                                                    <input type="hidden" name="action" value="editar_tratamiento">
                                                    <input type="hidden" name="id_tratamiento" value="<?= $t['id_tratamiento'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Finca / Plantación</label>
                                                                <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($t['nombre_finca'] . ' - ' . $t['nombre_comun'] . ' (' . $t['identificador_cuadro'] . ')') ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Fecha Aplicación *</label>
                                                                <input type="date" name="fecha_tratamiento" class="form-control" value="<?= $t['fecha_tratamiento'] ?>" required>
                                                            </div>
                                                            <div class="col-md-3 d-flex align-items-center pt-3">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" name="es_ecologico" id="eco_<?= $t['id_tratamiento'] ?>" value="1" <?= $t['es_ecologico'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label fw-bold text-success small" for="eco_<?= $t['id_tratamiento'] ?>">Ecológico</label>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold small">Producto Comercial *</label>
                                                                <input type="text" name="nombre_comercial_producto" class="form-control" value="<?= htmlspecialchars($t['nombre_comercial_producto']) ?>" required>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Materia Activa</label>
                                                                <input type="text" name="materia_activa" class="form-control" value="<?= htmlspecialchars($t['materia_activa'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold small">Nº Registro MAPA</label>
                                                                <input type="text" name="num_registro_oficial" class="form-control" value="<?= htmlspecialchars($t['num_registro_oficial'] ?: '') ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Dosis por Hectárea *</label>
                                                                <input type="number" step="0.001" name="dosis_por_hectarea" class="form-control font-monospace" value="<?= $t['dosis_por_hectarea'] ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Unidad de Dosis</label>
                                                                <input type="text" name="unidad_dosis" class="form-control" value="<?= htmlspecialchars($t['unidad_dosis']) ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Caldo Total (Litros)</label>
                                                                <input type="number" step="0.1" name="caldo_total_litros" class="form-control font-monospace" value="<?= $t['caldo_total_litros'] ?: '' ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Plazo de Seguridad (Días)</label>
                                                                <input type="number" name="plazo_seguridad_dias" class="form-control" value="<?= $t['plazo_seguridad_dias'] ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Plaga o Enfermedad</label>
                                                                <input type="text" name="plaga_o_enfermedad_objetivo" class="form-control" value="<?= htmlspecialchars($t['plaga_o_enfermedad_objetivo'] ?: '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold small">Aplicador Responsable</label>
                                                                <input type="text" name="aplicador_responsable" class="form-control" value="<?= htmlspecialchars($t['aplicador_responsable'] ?: '') ?>">
                                                            </div>
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold small">Observaciones</label>
                                                            <textarea name="observaciones" class="form-control" rows="2"><?= htmlspecialchars($t['observaciones'] ?: '') ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-ag">
                                                            <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Script Calculador de Dosificación Dinámica en Tratamientos -->
<script>
let superficeHaActual = 1.0;

function actualizarSuperficie() {
    const sel = document.getElementById('sel_plantacion');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.getAttribute('data-superficie')) {
        superficeHaActual = parseFloat(opt.getAttribute('data-superficie')) || 1.0;
        calcularCaldoYDosis();
    }
}

function cambiarModoDosificacion() {
    const modo = document.getElementById('modo_dosificacion').value;
    const grpCantidad = document.getElementById('grp_cantidad_equipo');
    const grpRemolque = document.getElementById('grp_capacidad_remolque');
    const lblDosis = document.getElementById('lbl_dosis_entrada');
    const unidadEntrada = document.getElementById('unidad_entrada');
    const lblCantidad = document.getElementById('lbl_cantidad_equipo');

    // Resetear visibilidad
    grpCantidad.style.display = 'none';
    grpRemolque.style.display = 'none';

    if (modo === 'ha') {
        lblDosis.innerText = "Dosis por Hectárea";
        unidadEntrada.innerHTML = `
            <option value="l/ha">L/ha</option>
            <option value="kg/ha">kg/ha</option>
            <option value="cc/hl">cc/hl</option>
            <option value="g/hl">g/hl</option>
        `;
    } else if (modo === 'm2') {
        lblDosis.innerText = "Dosis por Metro Cuadrado (m²)";
        unidadEntrada.innerHTML = `
            <option value="ml/m2">ml/m²</option>
            <option value="g/m2">g/m²</option>
        `;
    } else if (modo === 'mochila_17') {
        lblDosis.innerText = "Dosis por Mochila (17 Litros)";
        unidadEntrada.innerHTML = `
            <option value="ml">ml / 17L</option>
            <option value="g">g / 17L</option>
        `;
        lblCantidad.innerText = "Nº de Mochilas gastadas (17L c/u)";
        grpCantidad.style.display = 'block';
    } else if (modo === 'mochila_5') {
        lblDosis.innerText = "Dosis por Mochila (5 Litros)";
        unidadEntrada.innerHTML = `
            <option value="ml">ml / 5L</option>
            <option value="g">g / 5L</option>
        `;
        lblCantidad.innerText = "Nº de Mochilas gastadas (5L c/u)";
        grpCantidad.style.display = 'block';
    } else if (modo === 'mochila_2') {
        lblDosis.innerText = "Dosis por Carga (2 Litros)";
        unidadEntrada.innerHTML = `
            <option value="ml">ml / 2L</option>
            <option value="g">g / 2L</option>
        `;
        lblCantidad.innerText = "Nº de Cargas gastadas (2L c/u)";
        grpCantidad.style.display = 'block';
    } else if (modo === 'remolque') {
        lblDosis.innerText = "Dosis para Cuba o por 100L";
        unidadEntrada.innerHTML = `
            <option value="l/cuba">L / Cuba entera</option>
            <option value="kg/cuba">kg / Cuba entera</option>
            <option value="cc/hl">cc / 100L (hl)</option>
            <option value="g/hl">g / 100L (hl)</option>
        `;
        grpRemolque.style.display = 'block';
    }

    aplicarDosisSegunDispositivo();
    calcularCaldoYDosis();
}

function calcularCaldoYDosis() {
    const modo = document.getElementById('modo_dosificacion').value;
    const dosisEntrada = parseFloat(document.getElementById('dosis_entrada').value) || 0;
    const unidadEntrada = document.getElementById('unidad_entrada').value;
    const txtDosisHa = document.getElementById('dosis_por_hectarea');
    const txtCaldo = document.getElementById('caldo_total_litros');
    const selUnidadFinal = document.getElementById('unidad_dosis');

    let caldoLitros = 0;
    let dosisPorHa = 0;
    let unidadFinal = 'l/ha';

    if (modo === 'ha') {
        dosisPorHa = dosisEntrada;
        unidadFinal = (unidadEntrada.includes('kg') || unidadEntrada.includes('g')) ? 'kg/ha' : 'l/ha';
        // Caldo estándar referencial si no se introduce manualmente: 800 - 1000 L/ha
        caldoLitros = Math.round(superficeHaActual * 1000);
    } else if (modo === 'm2') {
        // 1 ha = 10,000 m2
        // ml/m2 * 10,000 / 1000 = L/ha
        dosisPorHa = (dosisEntrada * 10.0);
        unidadFinal = unidadEntrada.includes('g') ? 'kg/ha' : 'l/ha';
        caldoLitros = Math.round(superficeHaActual * 1000);
    } else if (modo === 'mochila_17') {
        const mochilas = parseFloat(document.getElementById('num_mochilas').value) || 1;
        caldoLitros = Math.round(mochilas * 17);
        // Cantidad total aplicada = mochilas * dosis
        const totalProducto = mochilas * dosisEntrada;
        dosisPorHa = superficeHaActual > 0 ? (totalProducto / 1000.0 / superficeHaActual) : (totalProducto / 1000.0);
        unidadFinal = unidadEntrada.includes('g') ? 'kg/ha' : 'l/ha';
    } else if (modo === 'mochila_5') {
        const mochilas = parseFloat(document.getElementById('num_mochilas').value) || 1;
        caldoLitros = Math.round(mochilas * 5);
        const totalProducto = mochilas * dosisEntrada;
        dosisPorHa = superficeHaActual > 0 ? (totalProducto / 1000.0 / superficeHaActual) : (totalProducto / 1000.0);
        unidadFinal = unidadEntrada.includes('g') ? 'kg/ha' : 'l/ha';
    } else if (modo === 'mochila_2') {
        const mochilas = parseFloat(document.getElementById('num_mochilas').value) || 1;
        caldoLitros = Math.round(mochilas * 2);
        const totalProducto = mochilas * dosisEntrada;
        dosisPorHa = superficeHaActual > 0 ? (totalProducto / 1000.0 / superficeHaActual) : (totalProducto / 1000.0);
        unidadFinal = unidadEntrada.includes('g') ? 'kg/ha' : 'l/ha';
    } else if (modo === 'remolque') {
        const cap = parseFloat(document.getElementById('capacidad_remolque').value) || 1000;
        caldoLitros = cap;
        if (unidadEntrada.includes('cuba')) {
            dosisPorHa = superficeHaActual > 0 ? (dosisEntrada / superficeHaActual) : dosisEntrada;
        } else {
            // cc/hl o g/hl (por cada 100 L)
            const hl = cap / 100.0;
            const totalProd = (dosisEntrada * hl) / 1000.0;
            dosisPorHa = superficeHaActual > 0 ? (totalProd / superficeHaActual) : totalProd;
        }
        unidadFinal = (unidadEntrada.includes('kg') || unidadEntrada.includes('g')) ? 'kg/ha' : 'l/ha';
    }

    txtDosisHa.value = dosisPorHa > 0 ? dosisPorHa.toFixed(3) : '';
    if (caldoLitros > 0) {
        txtCaldo.value = caldoLitros;
    }
    selUnidadFinal.value = unidadFinal;
}

// ASISTENTE INTELIGENTE IA PARA FITOSANITARIOS
let datosIaCargados = null;

function seleccionarPildora(nombre) {
    const inp = document.getElementById('ia_producto_busqueda');
    if (inp) {
        inp.value = nombre;
        consultarProductoIa();
    }
}

let opcionesTratamientoIa = [];

function mostrarFichaDetalleIa(d) {
    datosIaCargados = d;
    const resBox = document.getElementById('ia_producto_resultado');

    document.getElementById('res_ia_nombre').textContent = d.nombre_comercial_producto || '';
    const badgeEco = document.getElementById('res_ia_badge_eco');
    if (d.es_ecologico) {
        badgeEco.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill';
        badgeEco.innerHTML = '<i class="fa-solid fa-leaf me-1"></i>Apto Ecológico (Bio)';
    } else {
        badgeEco.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill';
        badgeEco.innerHTML = '<i class="fa-solid fa-flask me-1"></i>Convencional';
    }

    document.getElementById('res_ia_fuente').textContent = d.fuente || 'Registro Fitosanitario';
    document.getElementById('res_ia_materia').textContent = d.materia_activa || 'No especificada';
    document.getElementById('res_ia_registro').textContent = d.num_registro_oficial || 'En trámite / Básico';
    document.getElementById('res_ia_tipo').textContent = d.tipo_producto || '-';
    document.getElementById('res_ia_plaga').textContent = d.plaga_o_enfermedad_objetivo || d.plagas_objetivo || '-';
    document.getElementById('res_ia_dosis').textContent = (d.dosis_recomendada_ha || '1.5') + ' ' + (d.unidad_dosis || 'l/ha');
    document.getElementById('res_ia_plazo').textContent = (d.plazo_seguridad_dias !== undefined) ? d.plazo_seguridad_dias : '0';
    document.getElementById('res_ia_obs').textContent = d.observaciones_tecnicas || (d.modo_accion || '');

    resBox.classList.remove('d-none');
}

function seleccionarOpcionTratamientoIa(idx) {
    if (!opcionesTratamientoIa || !opcionesTratamientoIa[idx]) return;
    mostrarFichaDetalleIa(opcionesTratamientoIa[idx]);

    // Resaltar tarjeta seleccionada
    opcionesTratamientoIa.forEach((_, i) => {
        const c = document.getElementById('trat_opt_' + i);
        if (c) {
            c.classList.remove('border-primary', 'bg-primary-subtle');
            c.classList.add('bg-white');
        }
    });
    const sel = document.getElementById('trat_opt_' + idx);
    if (sel) {
        sel.classList.remove('bg-white');
        sel.classList.add('border-primary', 'bg-primary-subtle');
    }
}

function consultarProductoIa() {
    const qInp = document.getElementById('ia_producto_busqueda');
    if (!qInp) return;
    const q = qInp.value.trim();
    if (!q) {
        alert("Por favor, introduce el nombre del producto, trampa, hormona, materia activa o plaga a consultar.");
        return;
    }

    const spinner = document.getElementById('ia_producto_spinner');
    const resBox = document.getElementById('ia_producto_resultado');
    const btn = document.getElementById('btnConsultarIaProducto');
    const wrapper = document.getElementById('ia_producto_opciones_wrapper');
    const list = document.getElementById('ia_producto_opciones_list');
    const countLbl = document.getElementById('ia_producto_opciones_count');

    spinner.classList.remove('d-none');
    resBox.classList.add('d-none');
    if (wrapper) wrapper.classList.add('d-none');
    btn.disabled = true;

    fetch('<?= APP_URL ?>/api/fitosanitarios/consultar-ia?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            spinner.classList.add('d-none');
            btn.disabled = false;

            if (res.success && res.opciones && res.opciones.length > 0) {
                opcionesTratamientoIa = res.opciones;
                
                if (wrapper && list && res.opciones.length > 1) {
                    list.innerHTML = '';
                    countLbl.innerHTML = '<i class="fa-solid fa-list-check me-1 text-primary"></i>Se han encontrado <strong>' + res.opciones.length + ' opciones</strong>. Selecciona la más adecuada:';
                    
                    res.opciones.forEach((opt, idx) => {
                        const card = document.createElement('div');
                        card.id = 'trat_opt_' + idx;
                        card.className = 'card p-2 border bg-white shadow-sm cursor-pointer';
                        card.style.cursor = 'pointer';
                        
                        const esEco = parseInt(opt.es_ecologico) === 1;
                        const ecoBadge = esEco ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small">Ecológico</span>' : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill small">Convencional</span>';
                        
                        card.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-primary">${opt.nombre_comercial_producto}</strong> ${ecoBadge}
                                    <div class="small text-muted">${opt.materia_activa || ''} | <span class="text-dark">${opt.plagas_objetivo || opt.plaga_o_enfermedad_objetivo || ''}</span></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="event.stopPropagation(); seleccionarOpcionTratamientoIa(${idx});">Elegir</button>
                            </div>
                        `;
                        card.onclick = () => seleccionarOpcionTratamientoIa(idx);
                        list.appendChild(card);
                    });
                    wrapper.classList.remove('d-none');
                }
                
                // Mostrar la primera opción en la ficha
                seleccionarOpcionTratamientoIa(0);
            } else {
                alert(res.message || "No se encontró ningún producto fitosanitario, trampa u hormona registrada para este término.");
            }
        })
        .catch(err => {
            spinner.classList.add('d-none');
            btn.disabled = false;
            console.error(err);
            alert("Error al conectar con el asistente de fitosanitarios: " + err.message);
        });
}

function aplicarDatosIaEnFormulario() {
    if (!datosIaCargados) return;
    const d = datosIaCargados;

    if (document.getElementById('nombre_comercial_producto')) {
        document.getElementById('nombre_comercial_producto').value = d.nombre_comercial_producto || '';
    }
    if (document.getElementById('trat_id_producto') && d.id_producto) {
        document.getElementById('trat_id_producto').value = d.id_producto;
    }
    if (document.getElementById('materia_activa')) {
        document.getElementById('materia_activa').value = d.materia_activa || '';
    }
    if (document.getElementById('num_registro_oficial')) {
        document.getElementById('num_registro_oficial').value = d.num_registro_oficial || '';
    }
    if (document.getElementById('es_ecologico')) {
        document.getElementById('es_ecologico').checked = (d.es_ecologico == 1);
    }
    if (document.querySelector('input[name="plazo_seguridad_dias"]')) {
        document.querySelector('input[name="plazo_seguridad_dias"]').value = d.plazo_seguridad_dias !== undefined ? d.plazo_seguridad_dias : 0;
    }
    if (document.querySelector('input[name="plaga_o_enfermedad_objetivo"]')) {
        document.querySelector('input[name="plaga_o_enfermedad_objetivo"]').value = d.plaga_o_enfermedad_objetivo || '';
    }
    if (document.querySelector('textarea[name="observaciones"]')) {
        const obsAct = document.querySelector('textarea[name="observaciones"]').value;
        const textoIa = (d.modo_accion ? '[' + d.modo_accion + '] ' : '') + (d.observaciones_tecnicas || '');
        document.querySelector('textarea[name="observaciones"]').value = obsAct ? (obsAct + ' | ' + textoIa) : textoIa;
    }

    // Guardar referencia activa para recálculos al cambiar equipo/modo
    window.productoActivoTratamiento = d;
    aplicarDosisSegunDispositivo();
    calcularCaldoYDosis();

    // Scroll suave hacia los campos
    const targetEl = document.getElementById('nombre_comercial_producto');
    if (targetEl) {
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        targetEl.focus();
    }
}

// Calcula y aplica la dosis exacta según el recipiente o equipo seleccionado
function aplicarDosisSegunDispositivo() {
    if (!window.productoActivoTratamiento) return;
    const p = window.productoActivoTratamiento;
    const modo = document.getElementById('modo_dosificacion').value;
    const dEntrada = document.getElementById('dosis_entrada');
    const uEntrada = document.getElementById('unidad_entrada');
    if (!dEntrada) return;

    // Determinar si es líquido o sólido
    const u = (p.unidad_dosis || '').toLowerCase();
    const nom = ((p.nombre_comercial || p.nombre_comercial_producto || '') + ' ' + (p.materia_activa || '')).toLowerCase();
    const esLiq = (u.indexOf('l') !== -1 || u.indexOf('cc') !== -1 || u.indexOf('ml') !== -1 ||
                   nom.indexOf('[ec]') !== -1 || nom.indexOf('[sc]') !== -1 || nom.indexOf('[sl]') !== -1 ||
                   nom.indexOf('líquid') !== -1 || nom.indexOf('aceite') !== -1);

    // Dosis base en g o ml por cada 100L de caldo
    let g100 = 0;
    const d100 = parseFloat(p.dosis_etiqueta_100l) || 0;
    const esPequena = (u === 'g/hl' || u === 'cc/hl' || u === 'ml/100l' || u === 'g/100l');
    const esMayor = (u.indexOf('kg') !== -1 || u.indexOf('l/hl') !== -1 || u.indexOf('%') !== -1 || u === 'l');
    if (esPequena) {
        g100 = d100;
    } else if (esMayor) {
        g100 = d100 * 1000.0;
    } else {
        g100 = (d100 > 0 && d100 < 10.0) ? (d100 * 1000.0) : d100;
    }

    // Dosis de referencia para cuba estándar de 1.000L en kg o L
    let dCuba1000 = parseFloat(p.dosis_calculada_cuba) || 0;
    if (dCuba1000 >= 50.0) dCuba1000 = dCuba1000 / 1000.0;
    if (dCuba1000 <= 0 && g100 > 0) dCuba1000 = (g100 * 10.0) / 1000.0;

    // Dosis de referencia para mochila estándar de 16L en g o ml
    let dMoch16 = parseFloat(p.dosis_calculada_mochila) || 0;
    if (dMoch16 > 0 && dMoch16 < 1.0) dMoch16 = dMoch16 * 1000.0;
    if (dMoch16 <= 0 && g100 > 0) dMoch16 = g100 * 0.16;

    if (modo === 'ha') {
        const dHa = parseFloat(p.dosis_por_hectarea) || parseFloat(p.dosis_recomendada_ha) || dCuba1000;
        dEntrada.value = dHa > 0 ? dHa.toFixed(3) : '';
        if (uEntrada) uEntrada.value = esLiq ? 'l/ha' : 'kg/ha';
    } else if (modo === 'm2') {
        const dHa = parseFloat(p.dosis_por_hectarea) || parseFloat(p.dosis_recomendada_ha) || dCuba1000;
        const dM2 = (dHa * 1000.0) / 10000.0;
        dEntrada.value = dM2 > 0 ? dM2.toFixed(3) : '';
        if (uEntrada) uEntrada.value = esLiq ? 'ml/m2' : 'g/m2';
    } else if (modo === 'mochila_17') {
        const calc17 = g100 > 0 ? (g100 * 0.17) : (dMoch16 * 17.0 / 16.0);
        dEntrada.value = calc17 > 0 ? (calc17 < 10 ? calc17.toFixed(2) : calc17.toFixed(1)) : '';
        if (uEntrada) uEntrada.value = esLiq ? 'ml' : 'g';
    } else if (modo === 'mochila_5') {
        const calc5 = g100 > 0 ? (g100 * 0.05) : (dMoch16 * 5.0 / 16.0);
        dEntrada.value = calc5 > 0 ? (calc5 < 10 ? calc5.toFixed(2) : calc5.toFixed(1)) : '';
        if (uEntrada) uEntrada.value = esLiq ? 'ml' : 'g';
    } else if (modo === 'mochila_2') {
        const calc2 = g100 > 0 ? (g100 * 0.02) : (dMoch16 * 2.0 / 16.0);
        dEntrada.value = calc2 > 0 ? (calc2 < 10 ? calc2.toFixed(2) : calc2.toFixed(1)) : '';
        if (uEntrada) uEntrada.value = esLiq ? 'ml' : 'g';
    } else if (modo === 'remolque') {
        const cap = parseFloat(document.getElementById('capacidad_remolque').value) || 1000;
        const calcCuba = dCuba1000 * (cap / 1000.0);
        dEntrada.value = calcCuba > 0 ? calcCuba.toFixed(2) : '';
        if (uEntrada) uEntrada.value = esLiq ? 'l/cuba' : 'kg/cuba';
    }
}

// Carga automática al seleccionar un producto del catálogo oficial
function cargarProductoDesdeCatalogo(sel) {
    if (!sel || !sel.value) {
        if (document.getElementById('trat_id_producto')) {
            document.getElementById('trat_id_producto').value = '0';
        }
        window.productoActivoTratamiento = null;
        return;
    }
    const opt = sel.options[sel.selectedIndex];
    const jsonStr = opt.getAttribute('data-json');
    if (!jsonStr) return;
    
    try {
        const p = JSON.parse(jsonStr);
        window.productoActivoTratamiento = p;

        if (document.getElementById('trat_id_producto')) {
            document.getElementById('trat_id_producto').value = p.id_producto || '0';
        }
        if (document.getElementById('nombre_comercial_producto')) {
            document.getElementById('nombre_comercial_producto').value = p.nombre_comercial || '';
        }
        if (document.getElementById('materia_activa')) {
            document.getElementById('materia_activa').value = p.materia_activa || '';
        }
        if (document.getElementById('num_registro_oficial')) {
            document.getElementById('num_registro_oficial').value = p.num_registro_oficial || '';
        }
        if (document.getElementById('es_ecologico')) {
            document.getElementById('es_ecologico').checked = (parseInt(p.es_ecologico) === 1);
        }
        
        const inPlazo = document.querySelector('input[name="plazo_seguridad_dias"]');
        if (inPlazo) inPlazo.value = p.plazo_seguridad_dias !== undefined ? p.plazo_seguridad_dias : 0;
        
        const inPlaga = document.querySelector('input[name="plaga_o_enfermedad_objetivo"]');
        if (inPlaga) inPlaga.value = p.plagas_objetivo || '';
        
        const txtObs = document.querySelector('textarea[name="observaciones"]');
        if (txtObs) {
            const nota = (p.modo_accion_uso ? '[' + p.modo_accion_uso + '] ' : '') + (p.descripcion_advertencias || '');
            if (nota && !txtObs.value.includes(nota)) {
                txtObs.value = txtObs.value ? (txtObs.value + ' | ' + nota) : nota;
            }
        }

        // Ajustar dosis automáticamente según el dispositivo o modo activo
        aplicarDosisSegunDispositivo();
        calcularCaldoYDosis();
    } catch (e) {
        console.error("Error al cargar fitosanitario del catálogo:", e);
    }
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    cambiarModoDosificacion();

    const busqInp = document.getElementById('ia_producto_busqueda');
    if (busqInp) {
        busqInp.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                consultarProductoIa();
            }
        });
    }
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
