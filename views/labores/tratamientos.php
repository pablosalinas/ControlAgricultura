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

            $stmt = $db->prepare("
                INSERT INTO tratamientos (
                    id_plantacion, fecha_tratamiento, es_ecologico, nombre_comercial_producto,
                    materia_activa, num_registro_oficial, dosis_por_hectarea, unidad_dosis,
                    caldo_total_litros, plazo_seguridad_dias, fecha_fin_plazo_seguridad,
                    plaga_o_enfermedad_objetivo, aplicador_responsable, observaciones, creado_por_id
                ) VALUES (
                    :plantacion, :fecha, :eco, :prod,
                    :mat, :reg, :dosis, :unidad,
                    :caldo, :plazo, :finplazo,
                    :plaga, :aplicador, :obs, :user
                )
            ");
            $stmt->execute(array(
                ':plantacion' => $idPlantacion,
                ':fecha'      => $fecha,
                ':eco'        => $esEco,
                ':prod'       => $producto,
                ':mat'        => $materiaActiva,
                ':reg'        => $registro,
                ':dosis'      => $dosisHaCalculada,
                ':unidad'     => $unidadFinal,
                ':caldo'      => $caldoTotal,
                ':plazo'      => $plazo,
                ':finplazo'   => $fechaFinPlazo,
                ':plaga'      => $plaga,
                ':aplicador'  => $aplicador,
                ':obs'        => $obs,
                ':user'       => $currentUser['id']
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
                SET fecha_tratamiento = :fecha, es_ecologico = :eco, nombre_comercial_producto = :prod,
                    materia_activa = :mat, num_registro_oficial = :reg, dosis_por_hectarea = :dosis,
                    unidad_dosis = :unidad, caldo_total_litros = :caldo, plazo_seguridad_dias = :plazo,
                    fecha_fin_plazo_seguridad = :finplazo, plaga_o_enfermedad_objetivo = :plaga,
                    aplicador_responsable = :aplicador, observaciones = :obs
                WHERE id_tratamiento = :id
            ");
            $stmtUpd->execute(array(
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

    // 3. ELIMINAR / BORRAR TRATAMIENTO
    elseif ($action === 'borrar_tratamiento') {
        try {
            $idTrat = (int)$_POST['id_tratamiento'];
            $stmtDel = $db->prepare("DELETE FROM tratamientos WHERE id_tratamiento = :id");
            $stmtDel->execute(array(':id' => $idTrat));
            $mensaje = "Tratamiento eliminado correctamente del cuaderno.";
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

// Histórico de tratamientos con filtros
$sqlTrat = "
    SELECT t.*, f.id_finca, f.nombre_finca, c.nombre_comun, p.identificador_cuadro, p.superficie_ocupada_ha
    FROM tratamientos t
    JOIN plantaciones p ON t.id_plantacion = p.id_plantacion
    JOIN fincas f ON p.id_finca = f.id_finca
    JOIN cultivos c ON p.id_cultivo = c.id_cultivo
    WHERE 1=1
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

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Nombre Comercial del Producto <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_comercial_producto" class="form-control" required placeholder="Ej: Cobre Nordox, Spintor 480, Bacillus thuringiensis...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Materia Activa</label>
                        <input type="text" name="materia_activa" class="form-control" placeholder="Ej: Óxido cuproso 75%, Spinosad...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Nº Registro Oficial (MAPA)</label>
                        <input type="text" name="num_registro_oficial" class="form-control" placeholder="Ej: 22.145">
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
                            <select name="capacidad_remolque" id="capacidad_remolque" class="form-select" onchange="calcularCaldoYDosis()">
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
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Plazo de Seguridad (Días) <span class="text-danger">*</span></label>
                        <input type="number" name="plazo_seguridad_dias" class="form-control" value="0" required>
                        <div class="form-text small">Indicar 0 si está exento de plazo.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Plaga o Enfermedad Objetivo</label>
                        <input type="text" name="plaga_o_enfermedad_objetivo" class="form-control" placeholder="Ej: Oídio, Mosca de la fruta, Pulgón...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Aplicador / Responsable</label>
                        <input type="text" name="aplicador_responsable" class="form-control" value="<?= htmlspecialchars($currentUser['nombre_completo']) ?>">
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

    <!-- Histórico de Tratamientos Registrados (CRUD) -->
    <div class="card card-ag shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-book-bookmark me-2"></i>Tratamientos Registrados en el Cuaderno (<?= count($tratamientos) ?>)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Finca / Cuadro</th>
                        <th>Tipo</th>
                        <th>Producto / Materia Activa</th>
                        <th>Dosis y Caldo</th>
                        <th>Plaga Objetivo</th>
                        <th>Plazo Seg. (Fin)</th>
                        <th>Aplicador</th>
                        <th class="text-end pe-4">Acciones</th>
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
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($t['nombre_comercial_producto']) ?></div>
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
                                <td class="small text-muted"><?= htmlspecialchars($t['aplicador_responsable'] ?: '-') ?></td>
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

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    cambiarModoDosificacion();
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
