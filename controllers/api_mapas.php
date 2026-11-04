<?php
/**
 * API REST para el Visor Cartográfico SIG de Cultivos y Árboles
 * Gestiona polígonos, círculos, rectángulos, árboles individuales y geolocalización
 */

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'No autorizado. Debe iniciar sesión.'));
    exit;
}

$db = Database::getConnection();
$tenantId = Auth::tenantId();
$isSuperAdmin = Auth::isSuperAdmin();

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

try {
    switch ($action) {

        // =========================================================================
        // 1. OBTENER DATOS DE LA FINCA (GEOMETRÍAS, PLANTACIONES, ÁRBOLES Y LABORES)
        // =========================================================================
        case 'obtener_finca':
            $idFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
            if ($idFinca <= 0) {
                echo json_encode(array('success' => false, 'message' => 'ID de finca no válido.'));
                exit;
            }

            // Datos de la finca con datos de paraje y municipio
            $sqlFinca = "SELECT f.id_finca, f.id_propietario, f.nombre_finca, f.referencia_catastral, f.poligono, f.parcela, f.superficie_ha,
                                f.latitud_centro, f.longitud_centro, f.zoom_mapa, f.geojson_perimetro, f.observaciones,
                                pa.nombre_paraje, pa.termino_municipal, pa.provincia
                         FROM fincas f
                         LEFT JOIN parajes pa ON f.id_paraje = pa.id_paraje
                         WHERE f.id_finca = :id AND f.activo = 1";
            if ($tenantId && !$isSuperAdmin) {
                $sqlFinca .= " AND (f.id_propietario = " . (int)$tenantId . " OR f.id_propietario IS NULL)";
            }
            $stmtF = $db->prepare($sqlFinca);
            $stmtF->execute(array(':id' => $idFinca));
            $finca = $stmtF->fetch();

            if (!$finca) {
                echo json_encode(array('success' => false, 'message' => 'Finca no encontrada o sin permisos de acceso.'));
                exit;
            }

            // Plantaciones activas en esta finca con detección de tipo fija / anual y fecha adaptada
            $sqlPlant = "
                SELECT p.id_plantacion, p.identificador_cuadro, p.campana_ano, p.superficie_ocupada_ha,
                       p.num_arboles_o_plantas, p.marco_plantacion, p.fecha_siembra, p.fecha_transplante,
                       p.fecha_estimada_fin, p.estado, p.notas,
                       COALESCE(p.tipo_plantacion, CASE 
                           WHEN c.tipo_cultivo IN ('olivar', 'frutal', 'citrico', 'vinedo', 'leñoso') THEN 'fija' 
                           ELSE 'temporada' 
                       END) AS tipo_plantacion,
                       p.dia_mes_recoleccion,
                       c.id_cultivo, c.nombre_comun, c.nombre_cientifico, c.variedad, c.tipo_cultivo,
                       c.es_ecologico
                FROM plantaciones p
                JOIN cultivos c ON p.id_cultivo = c.id_cultivo
                WHERE p.id_finca = :finca AND p.activo = 1
                ORDER BY p.identificador_cuadro ASC, p.id_plantacion DESC
            ";
            $stmtP = $db->prepare($sqlPlant);
            $stmtP->execute(array(':finca' => $idFinca));
            $plantaciones = $stmtP->fetchAll();

            $anoActual = (int)date('Y');

            // Mapa asociativo de plantaciones con resumen de labores y fechas adaptadas al año en vigor
            $plantacionesMap = array();
            $idsPlantacion = array();
            foreach ($plantaciones as $pl) {
                $pId = (int)$pl['id_plantacion'];
                $idsPlantacion[] = $pId;

                // Determinar si es plantación fija anual / plurianual
                $esFija = ($pl['tipo_plantacion'] === 'fija') || in_array($pl['tipo_cultivo'], array('olivar', 'frutal', 'citrico', 'vinedo', 'leñoso'));
                $pl['es_fija_anual'] = $esFija;

                // Adaptar fecha estimada de recolección al año en vigor si es fija
                $fechaFin = $pl['fecha_estimada_fin'];
                $diaMes = !empty($pl['dia_mes_recoleccion']) ? $pl['dia_mes_recoleccion'] : (!empty($fechaFin) ? date('m-d', strtotime($fechaFin)) : null);

                if ($esFija && $diaMes) {
                    $pl['fecha_recoleccion_adaptada'] = $anoActual . '-' . $diaMes;
                    $pl['fecha_recoleccion_formateada'] = date('d/m/', strtotime($anoActual . '-' . $diaMes)) . $anoActual;
                    $pl['es_adaptada_ano_vigor'] = true;
                } else {
                    $pl['fecha_recoleccion_adaptada'] = $fechaFin;
                    $pl['fecha_recoleccion_formateada'] = $fechaFin ? date('d/m/Y', strtotime($fechaFin)) : 'No especificada';
                    $pl['es_adaptada_ano_vigor'] = false;
                }

                $pl['ultimas_labores'] = array(
                    'riego' => null,
                    'tratamiento' => null,
                    'poda' => null,
                    'recoleccion' => null,
                    'diario_reciente' => null
                );
                $plantacionesMap[$pId] = $pl;
            }

            // Si hay plantaciones, cargamos el resumen de labores recientes de cada una
            if (!empty($idsPlantacion)) {
                $inQuery = implode(',', array_map('intval', $idsPlantacion));

                // Último riego
                try {
                    $sqlR = "SELECT id_plantacion, fecha_riego, horas_riego, caudal_m3, tipo_riego 
                             FROM riegos WHERE id_plantacion IN ($inQuery) AND activo = 1 ORDER BY fecha_riego DESC";
                    $riegos = $db->query($sqlR)->fetchAll();
                    foreach ($riegos as $r) {
                        $pId = (int)$r['id_plantacion'];
                        if (isset($plantacionesMap[$pId]) && $plantacionesMap[$pId]['ultimas_labores']['riego'] === null) {
                            $plantacionesMap[$pId]['ultimas_labores']['riego'] = $r;
                        }
                    }
                } catch (Exception $eR) {}

                // Último tratamiento
                try {
                    $sqlT = "SELECT id_plantacion, fecha_tratamiento, producto_comercial, materia_activa, dosis_por_hl, tipo_plaga_u_objetivo 
                             FROM tratamientos WHERE id_plantacion IN ($inQuery) AND activo = 1 ORDER BY fecha_tratamiento DESC";
                    $tratamientos = $db->query($sqlT)->fetchAll();
                    foreach ($tratamientos as $t) {
                        $pId = (int)$t['id_plantacion'];
                        if (isset($plantacionesMap[$pId]) && $plantacionesMap[$pId]['ultimas_labores']['tratamiento'] === null) {
                            $plantacionesMap[$pId]['ultimas_labores']['tratamiento'] = $t;
                        }
                    }
                } catch (Exception $eT) {}

                // Última entrada de diario fenológico con foto
                try {
                    $sqlD = "SELECT id_plantacion, fecha, estado_fenologico, observacion, foto_evidencia_url 
                             FROM diario_evolucion WHERE id_plantacion IN ($inQuery) ORDER BY fecha DESC, id_diario DESC";
                    $diarios = $db->query($sqlD)->fetchAll();
                    foreach ($diarios as $d) {
                        $pId = (int)$d['id_plantacion'];
                        if (isset($plantacionesMap[$pId]) && $plantacionesMap[$pId]['ultimas_labores']['diario_reciente'] === null) {
                            $plantacionesMap[$pId]['ultimas_labores']['diario_reciente'] = $d;
                        }
                    }
                } catch (Exception $eD) {}
            }

            // Geometrías delimitadas de zonas (polígonos, círculos, rectángulos)
            $stmtG = $db->prepare("
                SELECT g.id_geometria, g.id_plantacion, g.id_finca, g.nombre_zona,
                       g.tipo_geometria, g.geojson, g.color_hex, g.superficie_m2, g.actualizado_el
                FROM plantaciones_geometria g
                WHERE g.id_finca = :finca AND g.activo = 1
                ORDER BY g.id_geometria ASC
            ");
            $stmtG->execute(array(':finca' => $idFinca));
            $geometrias = $stmtG->fetchAll();

            // Árboles individuales geolocalizados
            $stmtA = $db->prepare("
                SELECT a.id_arbol, a.id_finca, a.id_plantacion, a.id_cultivo,
                       a.codigo_arbol, a.especie_variedad, a.latitud, a.longitud,
                       a.ano_plantacion, a.estado_salud, a.patron_portainjerto,
                       a.notas, a.actualizado_el,
                       c.nombre_comun AS nombre_cultivo, c.tipo_cultivo,
                       p.identificador_cuadro
                FROM arboles_individuales a
                LEFT JOIN cultivos c ON a.id_cultivo = c.id_cultivo
                LEFT JOIN plantaciones p ON a.id_plantacion = p.id_plantacion
                WHERE a.id_finca = :finca AND a.activo = 1
                ORDER BY a.codigo_arbol ASC, a.id_arbol ASC
            ");
            $stmtA->execute(array(':finca' => $idFinca));
            $arboles = $stmtA->fetchAll();

            echo json_encode(array(
                'success' => true,
                'finca' => $finca,
                'plantaciones' => array_values($plantacionesMap),
                'geometrias' => $geometrias,
                'arboles' => $arboles
            ));
            break;

        // =========================================================================
        // 2. GUARDAR O ACTUALIZAR ZONA DELIMITADA (POLÍGONO, CÍRCULO, RECTÁNGULO)
        // =========================================================================
        case 'guardar_zona':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $idGeom = !empty($input['id_geometria']) ? (int)$input['id_geometria'] : 0;
            $idFinca = (int)(isset($input['id_finca']) ? $input['id_finca'] : 0);
            $idPlantacion = (int)(isset($input['id_plantacion']) ? $input['id_plantacion'] : 0);
            $nombreZona = trim(isset($input['nombre_zona']) ? $input['nombre_zona'] : '');
            $tipoGeom = trim(isset($input['tipo_geometria']) ? $input['tipo_geometria'] : 'polygon');
            $geojson = trim(isset($input['geojson']) ? $input['geojson'] : '');
            $colorHex = trim(isset($input['color_hex']) ? $input['color_hex'] : '#10b981');
            $superficieM2 = (float)(isset($input['superficie_m2']) ? $input['superficie_m2'] : 0);

            if ($idFinca <= 0 || $idPlantacion <= 0 || empty($geojson)) {
                echo json_encode(array('success' => false, 'message' => 'Faltan datos requeridos (Finca, Plantación o Geometría GeoJSON).'));
                exit;
            }

            if ($idGeom > 0) {
                // Actualizar zona existente
                $stmtUpd = $db->prepare("
                    UPDATE plantaciones_geometria
                    SET id_plantacion = :plant, nombre_zona = :nom, tipo_geometria = :tipo,
                        geojson = :geo, color_hex = :col, superficie_m2 = :sup
                    WHERE id_geometria = :id AND activo = 1
                ");
                $stmtUpd->execute(array(
                    ':plant' => $idPlantacion,
                    ':nom'   => $nombreZona ?: null,
                    ':tipo'  => $tipoGeom,
                    ':geo'   => $geojson,
                    ':col'   => $colorHex,
                    ':sup'   => $superficieM2,
                    ':id'    => $idGeom
                ));
                $savedId = $idGeom;
                $msg = "Zona delimitada actualizada correctamente.";
            } else {
                // Insertar nueva zona
                $stmtIns = $db->prepare("
                    INSERT INTO plantaciones_geometria (
                        id_plantacion, id_finca, nombre_zona, tipo_geometria, geojson, color_hex, superficie_m2, activo
                    ) VALUES (
                        :plant, :finca, :nom, :tipo, :geo, :col, :sup, 1
                    )
                ");
                $stmtIns->execute(array(
                    ':plant' => $idPlantacion,
                    ':finca' => $idFinca,
                    ':nom'   => $nombreZona ?: null,
                    ':tipo'  => $tipoGeom,
                    ':geo'   => $geojson,
                    ':col'   => $colorHex,
                    ':sup'   => $superficieM2
                ));
                $savedId = (int)$db->lastInsertId();
                $msg = "Zona de cultivo delimitada y guardada con éxito en el mapa.";
            }

            echo json_encode(array(
                'success' => true,
                'message' => $msg,
                'id_geometria' => $savedId
            ));
            break;

        // =========================================================================
        // 3. BORRAR ZONA DELIMITADA (SOFT DELETE SEGURO)
        // =========================================================================
        case 'borrar_zona':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            $idGeom = !empty($input['id_geometria']) ? (int)$input['id_geometria'] : 0;
            if ($idGeom <= 0) {
                echo json_encode(array('success' => false, 'message' => 'ID de geometría no válido.'));
                exit;
            }

            $stmtDel = $db->prepare("UPDATE plantaciones_geometria SET activo = 0 WHERE id_geometria = :id");
            $stmtDel->execute(array(':id' => $idGeom));

            echo json_encode(array(
                'success' => true,
                'message' => 'Zona delimitada archivada/eliminada del mapa (borrado lógico seguro).'
            ));
            break;

        // =========================================================================
        // 4. GUARDAR O ACTUALIZAR ÁRBOL INDIVIDUAL
        // =========================================================================
        case 'guardar_arbol':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $idArbol = !empty($input['id_arbol']) ? (int)$input['id_arbol'] : 0;
            $idFinca = (int)(isset($input['id_finca']) ? $input['id_finca'] : 0);
            $idPlantacion = !empty($input['id_plantacion']) ? (int)$input['id_plantacion'] : null;
            $idCultivo = !empty($input['id_cultivo']) ? (int)$input['id_cultivo'] : null;
            $codigo = trim(isset($input['codigo_arbol']) ? $input['codigo_arbol'] : '');
            $especie = trim(isset($input['especie_variedad']) ? $input['especie_variedad'] : '');
            $lat = (float)(isset($input['latitud']) ? $input['latitud'] : 0);
            $lng = (float)(isset($input['longitud']) ? $input['longitud'] : 0);
            $ano = !empty($input['ano_plantacion']) ? (int)$input['ano_plantacion'] : null;
            $estado = isset($input['estado_salud']) ? $input['estado_salud'] : 'bueno';
            $patron = trim(isset($input['patron_portainjerto']) ? $input['patron_portainjerto'] : '');
            $notas = trim(isset($input['notas']) ? $input['notas'] : '');

            if ($idFinca <= 0 || empty($codigo) || $lat == 0 || $lng == 0) {
                echo json_encode(array('success' => false, 'message' => 'Faltan datos obligatorios (Finca, Código del Árbol y Coordenadas GPS).'));
                exit;
            }

            // Si no se proporcionó id_cultivo pero sí id_plantacion, heredamos el cultivo
            if (empty($idCultivo) && !empty($idPlantacion)) {
                $cId = $db->query("SELECT id_cultivo FROM plantaciones WHERE id_plantacion = " . (int)$idPlantacion)->fetchColumn();
                if ($cId) $idCultivo = (int)$cId;
            }

            if ($idArbol > 0) {
                // Actualizar árbol existente
                $stmtUpd = $db->prepare("
                    UPDATE arboles_individuales
                    SET id_plantacion = :plant, id_cultivo = :cult, codigo_arbol = :cod,
                        especie_variedad = :esp, latitud = :lat, longitud = :lng,
                        ano_plantacion = :ano, estado_salud = :est, patron_portainjerto = :pat,
                        notas = :notas
                    WHERE id_arbol = :id AND activo = 1
                ");
                $stmtUpd->execute(array(
                    ':plant' => $idPlantacion,
                    ':cult'  => $idCultivo,
                    ':cod'   => $codigo,
                    ':esp'   => $especie ?: null,
                    ':lat'   => $lat,
                    ':lng'   => $lng,
                    ':ano'   => $ano,
                    ':est'   => $estado,
                    ':pat'   => $patron ?: null,
                    ':notas' => $notas ?: null,
                    ':id'    => $idArbol
                ));
                $savedId = $idArbol;
                $msg = "Árbol <strong>" . htmlspecialchars($codigo) . "</strong> actualizado correctamente.";
            } else {
                // Insertar nuevo árbol
                $stmtIns = $db->prepare("
                    INSERT INTO arboles_individuales (
                        id_finca, id_plantacion, id_cultivo, codigo_arbol, especie_variedad,
                        latitud, longitud, ano_plantacion, estado_salud, patron_portainjerto, notas, activo
                    ) VALUES (
                        :finca, :plant, :cult, :cod, :esp,
                        :lat, :lng, :ano, :est, :pat, :notas, 1
                    )
                ");
                $stmtIns->execute(array(
                    ':finca' => $idFinca,
                    ':plant' => $idPlantacion,
                    ':cult'  => $idCultivo,
                    ':cod'   => $codigo,
                    ':esp'   => $especie ?: null,
                    ':lat'   => $lat,
                    ':lng'   => $lng,
                    ':ano'   => $ano,
                    ':est'   => $estado,
                    ':pat'   => $patron ?: null,
                    ':notas' => $notas ?: null
                ));
                $savedId = (int)$db->lastInsertId();
                $msg = "Árbol <strong>" . htmlspecialchars($codigo) . "</strong> geolocalizado y guardado con éxito.";
            }

            echo json_encode(array(
                'success' => true,
                'message' => $msg,
                'id_arbol' => $savedId
            ));
            break;

        // =========================================================================
        // 5. BORRAR ÁRBOL INDIVIDUAL (SOFT DELETE SEGURO)
        // =========================================================================
        case 'borrar_arbol':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            $idArbol = !empty($input['id_arbol']) ? (int)$input['id_arbol'] : 0;
            if ($idArbol <= 0) {
                echo json_encode(array('success' => false, 'message' => 'ID de árbol no válido.'));
                exit;
            }

            $stmtDel = $db->prepare("UPDATE arboles_individuales SET activo = 0 WHERE id_arbol = :id");
            $stmtDel->execute(array(':id' => $idArbol));

            echo json_encode(array(
                'success' => true,
                'message' => 'Árbol eliminado del mapa (borrado lógico seguro).'
            ));
            break;

        // =========================================================================
        // 5b. MOVER / ARRASTRAR ÁRBOL (SCRUB / DRAG & DROP EN MAPA)
        // =========================================================================
        case 'mover_arbol':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;

            $idArbol = !empty($input['id_arbol']) ? (int)$input['id_arbol'] : 0;
            $lat = (float)(isset($input['latitud']) ? $input['latitud'] : 0);
            $lng = (float)(isset($input['longitud']) ? $input['longitud'] : 0);

            if ($idArbol <= 0 || $lat == 0 || $lng == 0) {
                echo json_encode(array('success' => false, 'message' => 'Coordenadas no válidas para mover el árbol.'));
                exit;
            }

            $stmtMv = $db->prepare("UPDATE arboles_individuales SET latitud = :lat, longitud = :lng WHERE id_arbol = :id AND activo = 1");
            $stmtMv->execute(array(':lat' => $lat, ':lng' => $lng, ':id' => $idArbol));

            echo json_encode(array('success' => true, 'message' => 'Posición del árbol actualizada.'));
            break;

        // =========================================================================
        // 5c. MOVER O EDITAR VÉRTICES DE ZONA (SCRUB / GEOMETRÍA EN MAPA)
        // =========================================================================
        case 'mover_zona':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;

            $idGeom = !empty($input['id_geometria']) ? (int)$input['id_geometria'] : 0;
            $geojson = trim(isset($input['geojson']) ? $input['geojson'] : '');
            $supM2 = (float)(isset($input['superficie_m2']) ? $input['superficie_m2'] : 0);

            if ($idGeom <= 0 || empty($geojson)) {
                echo json_encode(array('success' => false, 'message' => 'Datos de geometría no válidos para actualizar.'));
                exit;
            }

            $stmtMvG = $db->prepare("UPDATE plantaciones_geometria SET geojson = :geo, superficie_m2 = :sup WHERE id_geometria = :id AND activo = 1");
            $stmtMvG->execute(array(':geo' => $geojson, ':sup' => $supM2, ':id' => $idGeom));

            echo json_encode(array('success' => true, 'message' => 'Zona y dimensiones actualizadas en el mapa.'));
            break;

        // =========================================================================
        // 6. GUARDAR COORDENADAS CENTRO / ZOOM DE LA FINCA
        // =========================================================================
        case 'guardar_centro_finca':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $idFinca = (int)(isset($input['id_finca']) ? $input['id_finca'] : 0);
            $lat = (float)(isset($input['latitud_centro']) ? $input['latitud_centro'] : 0);
            $lng = (float)(isset($input['longitud_centro']) ? $input['longitud_centro'] : 0);
            $zoom = (int)(isset($input['zoom_mapa']) ? $input['zoom_mapa'] : 17);
            $perimetro = isset($input['geojson_perimetro']) ? $input['geojson_perimetro'] : null;

            if ($idFinca <= 0 || $lat == 0 || $lng == 0) {
                echo json_encode(array('success' => false, 'message' => 'Coordenadas no válidas para la finca.'));
                exit;
            }

            $stmtUpdF = $db->prepare("
                UPDATE fincas
                SET latitud_centro = :lat, longitud_centro = :lng, zoom_mapa = :zoom,
                    geojson_perimetro = COALESCE(:perim, geojson_perimetro)
                WHERE id_finca = :id
            ");
            $stmtUpdF->execute(array(
                ':lat'   => $lat,
                ':lng'   => $lng,
                ':zoom'  => $zoom,
                ':perim' => $perimetro,
                ':id'    => $idFinca
            ));

            echo json_encode(array(
                'success' => true,
                'message' => 'Centro y zoom de la finca guardados para futuras aperturas.'
            ));
            break;

        // =========================================================================
        // 7. LOCALIZAR PARCELA CATASTRAL (COORDENADAS Y POLÍGONO DE LINDEROS)
        // =========================================================================
        case 'localizar_catastro':
            $idFinca = isset($_GET['finca_id']) ? (int)$_GET['finca_id'] : 0;
            if ($idFinca <= 0) {
                echo json_encode(array('success' => false, 'message' => 'ID de finca no válido.'));
                exit;
            }

            $stmtF = $db->prepare("
                SELECT f.*, pa.nombre_paraje, pa.termino_municipal, pa.provincia
                FROM fincas f
                LEFT JOIN parajes pa ON f.id_paraje = pa.id_paraje
                WHERE f.id_finca = :id AND f.activo = 1
            ");
            $stmtF->execute(array(':id' => $idFinca));
            $finca = $stmtF->fetch();

            if (!$finca) {
                echo json_encode(array('success' => false, 'message' => 'Finca no encontrada.'));
                exit;
            }

            $refCat = trim($finca['referencia_catastral'] ?: '');
            $pol = trim($finca['poligono'] ?: '');
            $parc = trim($finca['parcela'] ?: '');
            $municipio = trim($finca['termino_municipal'] ?: 'MORATALLA');
            $provincia = trim($finca['provincia'] ?: 'MURCIA');

            // Construir referencia catastral rústica si no viene explícita
            if (empty($refCat) && !empty($pol) && !empty($parc)) {
                // Código municipio Moratalla = 028, Murcia = 30
                $refCat = '30028A' . str_pad($pol, 3, '0', STR_PAD_LEFT) . str_pad($parc, 5, '0', STR_PAD_LEFT);
            }

            $lat = !empty($finca['latitud_centro']) ? (float)$finca['latitud_centro'] : null;
            $lng = !empty($finca['longitud_centro']) ? (float)$finca['longitud_centro'] : null;
            $geojson = !empty($finca['geojson_perimetro']) ? $finca['geojson_perimetro'] : null;

            // Intentar obtener geometría WFS INSPIRE de Catastro desde el servidor si no la tiene
            if (empty($geojson) && !empty($refCat)) {
                $cleanRef = substr(preg_replace('/[^A-Za-z0-9]/', '', $refCat), 0, 14);
                $wfsUrl = "https://ovc.catastro.meh.es/INSPIRE/wfsCP.aspx?service=wfs&version=2&request=getfeature&STOREDQUERIE_ID=GetParcel&refcat=" . urlencode($cleanRef) . "&srsname=EPSG:4326";

                $ctx = stream_context_create(array(
                    'http' => array('timeout' => 4, 'user_agent' => 'AgroDietario/1.0'),
                    'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)
                ));
                $wfsXml = @file_get_contents($wfsUrl, false, $ctx);

                if (!empty($wfsXml)) {
                    // Buscar etiquetas de coordenadas posList en el GML
                    if (preg_match('/<gml:posList[^>]*>(.*?)<\/gml:posList>/is', $wfsXml, $mPos)) {
                        $rawCoords = preg_split('/\s+/', trim($mPos[1]));
                        $puntos = array();
                        $sumLat = 0;
                        $sumLng = 0;
                        $count = 0;

                        for ($i = 0; $i < count($rawCoords) - 1; $i += 2) {
                            $v1 = (float)$rawCoords[$i];
                            $v2 = (float)$rawCoords[$i + 1];

                            // En WFS Catastro INSPIRE EPSG:4326, v1 suele ser latitud y v2 longitud (o viceversa)
                            // En España latitud ~ 36 a 43, longitud ~ -9 a 4
                            if ($v1 > 20 && $v1 < 50) {
                                $cLat = $v1;
                                $cLng = $v2;
                            } else {
                                $cLat = $v2;
                                $cLng = $v1;
                            }

                            $puntos[] = array($cLng, $cLat); // Formato GeoJSON [lng, lat]
                            $sumLat += $cLat;
                            $sumLng += $cLng;
                            $count++;
                        }

                        if ($count >= 3) {
                            $lat = round($sumLat / $count, 7);
                            $lng = round($sumLng / $count, 7);
                            $geojsonArray = array(
                                'type' => 'Polygon',
                                'coordinates' => array($puntos)
                            );
                            $geojson = json_encode($geojsonArray);

                            // Guardar en base de datos para no tener que consultar cada vez
                            $db->prepare("UPDATE fincas SET latitud_centro = :lat, longitud_centro = :lng, geojson_perimetro = :geo WHERE id_finca = :id")
                               ->execute(array(':lat' => $lat, ':lng' => $lng, ':geo' => $geojson, ':id' => $idFinca));
                        }
                    }
                }
            }

            // Si aún no tenemos coordenadas pero tenemos polígono y parcela, consultar API Coordenadas Catastro
            if ((empty($lat) || empty($lng)) && !empty($pol) && !empty($parc)) {
                $coorUrl = "https://ovc.catastro.meh.es/ovcservweb/OVCSWLocalizacionRC/OVCCoordenadas.asmx/Consulta_CPMRC?Provincia=" . urlencode($provincia) . "&Municipio=" . urlencode($municipio) . "&Poligono=" . urlencode($pol) . "&Parcela=" . urlencode($parc) . "&SRS=EPSG:4326";

                $ctx = stream_context_create(array(
                    'http' => array('timeout' => 4, 'user_agent' => 'AgroDietario/1.0'),
                    'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)
                ));
                $coorXml = @file_get_contents($coorUrl, false, $ctx);

                if (!empty($coorXml)) {
                    if (preg_match('/<xcen>(.*?)<\/xcen>/i', $coorXml, $mX) && preg_match('/<ycen>(.*?)<\/ycen>/i', $coorXml, $mY)) {
                        $lng = (float)$mX[1];
                        $lat = (float)$mY[1];

                        if ($lat != 0 && $lng != 0) {
                            $db->prepare("UPDATE fincas SET latitud_centro = :lat, longitud_centro = :lng WHERE id_finca = :id")
                               ->execute(array(':lat' => $lat, ':lng' => $lng, ':id' => $idFinca));
                        }
                    }
                }
            }

            echo json_encode(array(
                'success' => true,
                'id_finca' => $idFinca,
                'nombre_finca' => $finca['nombre_finca'],
                'referencia_catastral' => $refCat,
                'poligono' => $pol,
                'parcela' => $parc,
                'municipio' => $municipio,
                'provincia' => $provincia,
                'nombre_paraje' => $finca['nombre_paraje'],
                'latitud' => $lat,
                'longitud' => $lng,
                'geojson_perimetro' => $geojson
            ));
            break;

        default:
            echo json_encode(array('success' => false, 'message' => 'Acción no reconocida en la API de mapas.'));
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ));
}
