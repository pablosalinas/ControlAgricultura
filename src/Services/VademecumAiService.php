<?php
/**
 * Servicio de Inteligencia Artificial Multimodal y Taxonomía Botánica para el Vademécum
 * AgroDietario
 *
 * Capacidades:
 * 1. Identificación visual de plantas mediante 1, 2 o 3 fotografías:
 *    - Planta completa
 *    - Zona de tallo y hojas
 *    - Flor
 * 2. Búsqueda y completado de ficha técnica por texto (nombre común, científico, especie, variedad).
 * 3. Re-análisis incremental: conforme el usuario añade nuevas fotos o datos, vuelve a consultar
 *    combinando toda la información acumulada.
 * 4. Taxonomía botánica contrastada (Reino, División, Clase, Orden, Familia, Género, Especie, Subespecie)
 *    mediante Gemini Vision/Text + respaldo automático con GBIF API, Wikipedia ES y CropAiService.
 *
 * Compatible con PHP 5.6, 7.x, 8.x
 */

require_once SRC_PATH . '/Services/CropAiService.php';

class VademecumAiService {

    /**
     * Analiza los datos y/o fotografías disponibles para identificar la planta/semilla
     * y completar su ficha técnica en el Vademécum.
     *
     * @param array $params Datos de texto actuales del formulario
     * @param array $photos Array asociativo ['planta_completa' => ['path'=>..., 'mime'=>...], 'tallo_hojas' => ..., 'flor' => ...]
     * @return array
     */
    public static function analyze($params, $photos = array()) {
        $queryTexto = isset($params['query']) ? trim($params['query']) : '';
        $nombreComun = isset($params['nombre_comun']) ? trim($params['nombre_comun']) : '';
        $nombreCientifico = isset($params['nombre_cientifico']) ? trim($params['nombre_cientifico']) : '';
        $variedad = isset($params['variedad']) ? trim($params['variedad']) : '';
        $tipoRegistro = isset($params['tipo_registro']) ? trim($params['tipo_registro']) : 'semilla';
        $esTransgenico = (!empty($params['es_transgenico']) && $params['es_transgenico'] == '1') ? 1 : 0;
        $abonoEco = (!isset($params['abono_es_ecologico']) || $params['abono_es_ecologico'] == '1') ? 1 : 0;
        $tratEco = (!isset($params['tratamiento_es_ecologico']) || $params['tratamiento_es_ecologico'] == '1') ? 1 : 0;
        $notasUsuario = isset($params['notas']) ? trim($params['notas']) : '';

        // Construir término principal de búsqueda si no viene explícito en query
        $searchTerm = $queryTexto;
        if (empty($searchTerm)) {
            if (!empty($nombreCientifico)) {
                $searchTerm = $nombreCientifico;
            } elseif (!empty($nombreComun)) {
                $searchTerm = $nombreComun . (!empty($variedad) ? ' ' . $variedad : '');
            }
        }

        // Contar fotos válidas disponibles
        $validPhotos = array();
        $photoLabels = array(
            'planta_completa' => 'Foto 1: Planta completa (porte general)',
            'tallo_hojas'     => 'Foto 2: Zona de tallo y hojas',
            'flor'            => 'Foto 3: Flor / inflorescencia'
        );

        foreach ($photoLabels as $key => $label) {
            if (!empty($photos[$key]['path']) && file_exists($photos[$key]['path'])) {
                $validPhotos[$key] = array(
                    'path'  => $photos[$key]['path'],
                    'mime'  => !empty($photos[$key]['mime']) ? $photos[$key]['mime'] : 'image/jpeg',
                    'label' => $label
                );
            }
        }

        if (empty($searchTerm) && empty($validPhotos)) {
            return array(
                'success' => false,
                'message' => 'Introduce al menos un nombre/especie o sube una fotografía (planta completa, tallo/hojas o flor) para que la IA pueda analizarla.'
            );
        }

        // 1. Intentar con Gemini Multimodal (si hay API Key configurada)
        $apiKey = self::getGeminiApiKey();
        $geminiError = '';

        if (!empty($apiKey)) {
            $geminiResult = self::queryGeminiMultimodal($apiKey, $searchTerm, $params, $validPhotos);
            if ($geminiResult && !empty($geminiResult['success'])) {
                // Enriquecer taxonomía con GBIF si faltara algún nivel taxonómico
                if (!empty($geminiResult['nombre_cientifico'])) {
                    $gbif = self::queryGbifTaxonomy($geminiResult['nombre_cientifico']);
                    if ($gbif) {
                        $taxFields = array('tax_reino', 'tax_division', 'tax_clase', 'tax_orden', 'tax_familia', 'tax_genero', 'tax_especie');
                        foreach ($taxFields as $tf) {
                            if (empty($geminiResult[$tf]) && !empty($gbif[$tf])) {
                                $geminiResult[$tf] = $gbif[$tf];
                            }
                        }
                    }
                }
                return $geminiResult;
            } elseif ($geminiResult && !empty($geminiResult['error_detail'])) {
                $geminiError = $geminiResult['error_detail'];
            }
        }

        // 2. Si hay fotos pero NO hay API Key de Gemini, avisar claramente
        $avisoFotos = '';
        if (!empty($validPhotos) && empty($apiKey)) {
            $avisoFotos = 'Has adjuntado ' . count($validPhotos) . ' fotografía(s). Para la identificación visual automática por imagen se requiere configurar una clave GEMINI_API_KEY en el archivo .env o en Configuración del Sistema.';
        } elseif (!empty($validPhotos) && !empty($geminiError)) {
            $avisoFotos = 'No se pudo completar el análisis visual con Gemini (' . $geminiError . ').';
        }

        // Si solo había fotos y no hay texto, no podemos usar el respaldo textual
        if (empty($searchTerm)) {
            return array(
                'success' => false,
                'message' => !empty($avisoFotos)
                    ? $avisoFotos . ' Mientras tanto, escribe el nombre común o científico para buscar su taxonomía y ficha técnica.'
                    : 'No se pudo identificar la planta únicamente con las imágenes proporcionadas. Añade un nombre o una foto adicional e inténtalo de nuevo.'
            );
        }

        // 3. Respaldo contrastado por texto: GBIF (Taxonomía) + Wikipedia ES + CropAiService
        $fallbackResult = self::queryBotanicalFallback($searchTerm, $abonoEco, $tratEco, $tipoRegistro, $esTransgenico);
        if ($fallbackResult && !empty($fallbackResult['success'])) {
            if (!empty($avisoFotos)) {
                $fallbackResult['identificacion_notas'] = $avisoFotos . (!empty($fallbackResult['identificacion_notas']) ? ' ' . $fallbackResult['identificacion_notas'] : '');
            }
            return $fallbackResult;
        }

        return array(
            'success' => false,
            'message' => !empty($avisoFotos)
                ? $avisoFotos . " Tampoco se encontraron resultados contrastados para '{$searchTerm}'."
                : "No se encontró información botánica contrastada para '{$searchTerm}'. Puedes completar los campos manualmente o aportar más datos/fotos."
        );
    }

    /**
     * Obtiene la clave de Gemini desde constantes, .env o tabla configuracion_web
     */
    public static function getGeminiApiKey() {
        if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
            return trim(GEMINI_API_KEY);
        }
        if (function_exists('env')) {
            $envKey = env('GEMINI_API_KEY', '');
            if (!empty($envKey)) {
                return trim($envKey);
            }
        }
        $gKey = getenv('GEMINI_API_KEY');
        if (!empty($gKey)) {
            return trim($gKey);
        }

        // Buscar en configuracion_web
        try {
            $db = Database::getConnection();
            if ($db) {
                $stmt = $db->query("SELECT gemini_api_key FROM configuracion_web LIMIT 1");
                $val = $stmt ? $stmt->fetchColumn() : '';
                if (!empty($val)) {
                    return trim($val);
                }
            }
        } catch (Exception $e) {
            // Ignorar si la columna no existe aún
        }

        return '';
    }

    /**
     * Consulta multimodal a Gemini Vision / Text
     */
    private static function queryGeminiMultimodal($apiKey, $searchTerm, $params, $validPhotos) {
        $abonoEco = (!isset($params['abono_es_ecologico']) || $params['abono_es_ecologico'] == '1');
        $tratEco = (!isset($params['tratamiento_es_ecologico']) || $params['tratamiento_es_ecologico'] == '1');

        $contextLines = array();
        if (!empty($searchTerm)) {
            $contextLines[] = "- Búsqueda / nombre indicado por el usuario: \"{$searchTerm}\"";
        }
        if (!empty($params['nombre_comun'])) {
            $contextLines[] = "- Nombre común actual: \"" . trim($params['nombre_comun']) . "\"";
        }
        if (!empty($params['nombre_cientifico'])) {
            $contextLines[] = "- Nombre científico actual: \"" . trim($params['nombre_cientifico']) . "\"";
        }
        if (!empty($params['variedad'])) {
            $contextLines[] = "- Variedad / cultivar actual: \"" . trim($params['variedad']) . "\"";
        }
        if (!empty($params['tax_familia'])) {
            $contextLines[] = "- Familia botánica conocida: \"" . trim($params['tax_familia']) . "\"";
        }
        if (!empty($params['notas'])) {
            $contextLines[] = "- Notas adicionales del usuario: \"" . trim($params['notas']) . "\"";
        }
        if (!empty($validPhotos)) {
            $contextLines[] = "- Se adjuntan " . count($validPhotos) . " fotografía(s) para identificación morfológica.";
        }

        $contextBlock = implode("\n", $contextLines);
        $abonoPref = $abonoEco
            ? "El usuario prefiere ABONO ECOLÓGICO (compost, estiércol, humus, biofertilizantes autorizados en agricultura ecológica)."
            : "El usuario solicita ABONO CONVENCIONAL / mineral (NPK, quelatos, etc., indicando también pautas prácticas).";
        $tratPref = $tratEco
            ? "El usuario prefiere TRATAMIENTOS ECOLÓGICOS homologados (cobre, azufre, jabón potásico, neem, Bacillus thuringiensis, fauna auxiliar)."
            : "El usuario solicita TRATAMIENTOS CONVENCIONALES / integrados (materias activas autorizadas y medidas preventivas).";

        $prompt = "Eres un botánico taxónomo e ingeniero agrónomo experto. Analiza los datos y/o fotografías proporcionadas para identificar la especie vegetal o semilla y elaborar su ficha completa de Vademécum Agrícola.\n\n" .
            "DATOS APORTADOS POR EL USUARIO:\n{$contextBlock}\n\n" .
            "PREFERENCIAS DEL CULTIVO:\n- {$abonoPref}\n- {$tratPref}\n\n" .
            "INSTRUCCIONES ESTRICTAS:\n" .
            "1. Si hay fotografías, analiza la morfología visible (porte en planta completa, filotaxis/nervios/borde en tallo y hojas, corola/sépalos/estambres en flor) y compárala con los datos de texto si los hubiera.\n" .
            "2. Si no es posible identificar ninguna especie real o los datos no corresponden a una planta/semilla real, responde ÚNICAMENTE: {\"no_contrastado\": true, \"motivo\": \"Explicación breve\"}\n" .
            "3. Si la identificas, responde ÚNICAMENTE con un JSON válido (sin texto adicional fuera del JSON) con estas claves exactas:\n" .
            "{\n" .
            '  "identificacion_confianza": "alta | media | baja",' . "\n" .
            '  "identificacion_notas": "Explicación breve de los rasgos observados en las fotos o fuentes usadas",' . "\n" .
            '  "nombre_comun": "Nombre común en español",' . "\n" .
            '  "nombre_cientifico": "Nombre científico completo en latín (ej: Solanum lycopersicum L.)",' . "\n" .
            '  "variedad": "Variedad o cultivar si aplica, o vacío",' . "\n" .
            '  "tax_reino": "Plantae",' . "\n" .
            '  "tax_division": "Ej: Magnoliophyta / Tracheophyta",' . "\n" .
            '  "tax_clase": "Ej: Magnoliopsida",' . "\n" .
            '  "tax_orden": "Ej: Solanales",' . "\n" .
            '  "tax_familia": "Ej: Solanaceae",' . "\n" .
            '  "tax_genero": "Ej: Solanum",' . "\n" .
            '  "tax_especie": "Ej: Solanum lycopersicum",' . "\n" .
            '  "tax_subespecie": "Subespecie o variedad botánica si existe, o vacío",' . "\n" .
            '  "es_transgenico": 0,' . "\n" .
            '  "detalle_transgenico": "Indicar si es variedad tradicional/híbrida o si existen eventos OMG conocidos en esta especie/variedad",' . "\n" .
            '  "origen_semilla": "Ej: Tradicional / Autóctona / Híbrida / Comercial",' . "\n" .
            '  "caracteristicas_semilla": "Morfología de la semilla, tamaño, peso de 1000 semillas, temperatura y % de germinación, profundidad de siembra y conservación",' . "\n" .
            '  "caracteristicas_planta": "Porte, altura, sistema radicular, tallo, hojas, flor y fruto",' . "\n" .
            '  "fecha_siembra": "Meses y condiciones ideales de siembra o plantación",' . "\n" .
            '  "fecha_crecimiento": "Duración y época de desarrollo vegetativo y floración",' . "\n" .
            '  "fecha_recoleccion": "Meses e indicadores de madurez para recolección",' . "\n" .
            '  "estado_tierra": "Textura, pH óptimo, drenaje, humedad y preparación del terreno",' . "\n" .
            '  "tipo_abono": "Plan de abonado detallado adaptado a la preferencia ecológica/convencional",' . "\n" .
            '  "enfermedades_plagas": "Principales enfermedades (hongos, bacterias, virus) y plagas de esta especie",' . "\n" .
            '  "tratamiento_enfermedades": "Protocolo de prevención y tratamiento adaptado a la preferencia ecológica/convencional",' . "\n" .
            '  "url_referencia": "https://es.wikipedia.org/wiki/..."' . "\n" .
            "}";

        $parts = array();
        $parts[] = array('text' => $prompt);

        foreach ($validPhotos as $key => $photo) {
            $rawBytes = @file_get_contents($photo['path']);
            if ($rawBytes !== false && strlen($rawBytes) > 0) {
                $parts[] = array('text' => $photo['label']);
                $parts[] = array(
                    'inline_data' => array(
                        'mime_type' => $photo['mime'],
                        'data'      => base64_encode($rawBytes)
                    )
                );
            }
        }

        $payload = json_encode(array(
            'contents' => array(
                array('parts' => $parts)
            ),
            'generationConfig' => array(
                'temperature' => 0.15,
                'maxOutputTokens' => 2048
            )
        ));

        $models = array('gemini-2.0-flash', 'gemini-1.5-flash');
        $lastError = '';

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $lastError = $curlErr;
                continue;
            }

            if ($httpCode >= 400 || !$response) {
                $errJson = json_decode($response, true);
                $lastError = !empty($errJson['error']['message']) ? $errJson['error']['message'] : "HTTP {$httpCode}";
                continue;
            }

            $resData = json_decode($response, true);
            if (!empty($resData['candidates'][0]['content']['parts'][0]['text'])) {
                $rawText = trim($resData['candidates'][0]['content']['parts'][0]['text']);
                $rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
                $rawText = preg_replace('/\s*```$/', '', $rawText);
                $parsed = json_decode(trim($rawText), true);

                if ($parsed && is_array($parsed)) {
                    if (!empty($parsed['no_contrastado'])) {
                        return array(
                            'success' => false,
                            'error_detail' => !empty($parsed['motivo']) ? $parsed['motivo'] : 'No se pudo contrastar la especie.'
                        );
                    }
                    if (!empty($parsed['nombre_comun']) || !empty($parsed['nombre_cientifico'])) {
                        $parsed['success'] = true;
                        $parsed['abono_es_ecologico'] = $abonoEco ? 1 : 0;
                        $parsed['tratamiento_es_ecologico'] = $tratEco ? 1 : 0;
                        $parsed['fuente_ia'] = !empty($validPhotos)
                            ? 'Gemini Vision Multimodal (' . count($validPhotos) . ' foto/s)'
                            : 'Gemini AI Botánico';
                        return $parsed;
                    }
                }
            }
        }

        return array('success' => false, 'error_detail' => $lastError);
    }

    /**
     * Consulta la taxonomía oficial en GBIF (Global Biodiversity Information Facility)
     */
    public static function queryGbifTaxonomy($scientificOrCommon) {
        $url = "https://api.gbif.org/v1/species/match?verbose=false&name=" . urlencode($scientificOrCommon);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AgroDietarioBot/2.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $resp = curl_exec($ch);
        curl_close($ch);

        if (!$resp) {
            return null;
        }

        $json = json_decode($resp, true);
        if (empty($json) || (isset($json['matchType']) && $json['matchType'] === 'NONE')) {
            return null;
        }

        // Solo aceptar reino Plantae
        if (!empty($json['kingdom']) && strtolower($json['kingdom']) !== 'plantae') {
            return null;
        }

        return array(
            'nombre_cientifico' => !empty($json['scientificName']) ? $json['scientificName'] : (!empty($json['canonicalName']) ? $json['canonicalName'] : ''),
            'tax_reino'         => !empty($json['kingdom']) ? $json['kingdom'] : 'Plantae',
            'tax_division'      => !empty($json['phylum']) ? $json['phylum'] : '',
            'tax_clase'         => !empty($json['class']) ? $json['class'] : '',
            'tax_orden'         => !empty($json['order']) ? $json['order'] : '',
            'tax_familia'       => !empty($json['family']) ? $json['family'] : '',
            'tax_genero'        => !empty($json['genus']) ? $json['genus'] : '',
            'tax_especie'       => !empty($json['species']) ? $json['species'] : (!empty($json['canonicalName']) ? $json['canonicalName'] : '')
        );
    }

    /**
     * Respaldo botánico sin API key: combina CropAiService + Wikipedia ES + GBIF
     */
    private static function queryBotanicalFallback($query, $abonoEco, $tratEco, $tipoRegistro, $esTransgenico) {
        $cropData = CropAiService::searchCrop($query, $tratEco ? true : false, '');
        $gbifDirect = self::queryGbifTaxonomy($query);

        // Si CropAiService encontró datos (catálogo local o Wikipedia), enriquecemos con GBIF
        if (!empty($cropData['success'])) {
            $cientifico = !empty($cropData['nombre_cientifico']) ? $cropData['nombre_cientifico'] : '';
            $gbif = $gbifDirect;
            if (!$gbif && !empty($cientifico)) {
                $gbif = self::queryGbifTaxonomy($cientifico);
            }

            $abonoTexto = $abonoEco
                ? "Abonado 100% Ecológico: Incorporación de compost maduro, estiércol compostado o humus de lombriz en fondo. Aporte de biofertilizantes, purines vegetales y algas en crecimiento."
                : "Abonado Convencional: Fertilización equilibrada NPK adaptada a análisis de suelo, con abonado de fondo y cobertera en fase de crecimiento activo.";

            return array(
                'success'                  => true,
                'identificacion_confianza' => 'media',
                'identificacion_notas'     => 'Datos obtenidos mediante consulta botánica contrastada (' . (isset($cropData['fuente']) ? $cropData['fuente'] : 'Catálogo + GBIF') . ').',
                'nombre_comun'             => isset($cropData['nombre_comun']) ? $cropData['nombre_comun'] : ucfirst($query),
                'nombre_cientifico'        => !empty($cientifico) ? $cientifico : (!empty($gbif['nombre_cientifico']) ? $gbif['nombre_cientifico'] : ''),
                'variedad'                 => isset($cropData['variedad']) ? $cropData['variedad'] : '',
                'tax_reino'                => !empty($gbif['tax_reino']) ? $gbif['tax_reino'] : 'Plantae',
                'tax_division'             => !empty($gbif['tax_division']) ? $gbif['tax_division'] : '',
                'tax_clase'                => !empty($gbif['tax_clase']) ? $gbif['tax_clase'] : '',
                'tax_orden'                => !empty($gbif['tax_orden']) ? $gbif['tax_orden'] : '',
                'tax_familia'              => !empty($gbif['tax_familia']) ? $gbif['tax_familia'] : '',
                'tax_genero'               => !empty($gbif['tax_genero']) ? $gbif['tax_genero'] : '',
                'tax_especie'              => !empty($gbif['tax_especie']) ? $gbif['tax_especie'] : $cientifico,
                'tax_subespecie'           => '',
                'es_transgenico'           => $esTransgenico,
                'detalle_transgenico'      => $esTransgenico ? 'Marcado como OMG / Transgénico por el usuario.' : 'Especie/variedad convencional o tradicional (no transgénica por defecto).',
                'origen_semilla'           => '',
                'caracteristicas_semilla'  => !empty($cropData['formato_siembra']) ? 'Marco / Siembra: ' . $cropData['formato_siembra'] : '',
                'caracteristicas_planta'   => isset($cropData['descripcion']) ? $cropData['descripcion'] : '',
                'fecha_siembra'            => isset($cropData['epoca_siembra']) ? $cropData['epoca_siembra'] : '',
                'fecha_crecimiento'        => !empty($cropData['dias_ciclo_aprox']) ? 'Ciclo aproximado de ' . $cropData['dias_ciclo_aprox'] . ' días. ' . (!empty($cropData['epoca_trasplante']) ? 'Trasplante/desarrollo: ' . $cropData['epoca_trasplante'] : '') : '',
                'fecha_recoleccion'        => isset($cropData['epoca_recoleccion']) ? $cropData['epoca_recoleccion'] : '',
                'estado_tierra'            => isset($cropData['requerimientos_tecnicos']) ? $cropData['requerimientos_tecnicos'] : '',
                'abono_es_ecologico'       => $abonoEco ? 1 : 0,
                'tipo_abono'               => $abonoTexto,
                'enfermedades_plagas'      => 'Revisar plagas y micosis habituales de la especie (hongos foliares, pulgones, ácaros u orugas según época).',
                'tratamiento_es_ecologico' => $tratEco ? 1 : 0,
                'tratamiento_enfermedades' => isset($cropData['tratamientos_recomendados']) ? $cropData['tratamientos_recomendados'] : '',
                'url_referencia'           => isset($cropData['url_consulta_botanica']) ? $cropData['url_consulta_botanica'] : '',
                'fuente_ia'                => 'Botánica Contrastada (GBIF + Wikipedia/Catálogo)'
            );
        }

        // Si solo GBIF devolvió resultado (por ejemplo, nombre científico en latín no hallado en Wikipedia ES)
        if ($gbifDirect) {
            return array(
                'success'                  => true,
                'identificacion_confianza' => 'media',
                'identificacion_notas'     => 'Taxonomía botánica oficial obtenida desde GBIF.',
                'nombre_comun'             => ucfirst($query),
                'nombre_cientifico'        => $gbifDirect['nombre_cientifico'],
                'variedad'                 => '',
                'tax_reino'                => $gbifDirect['tax_reino'],
                'tax_division'             => $gbifDirect['tax_division'],
                'tax_clase'                => $gbifDirect['tax_clase'],
                'tax_orden'                => $gbifDirect['tax_orden'],
                'tax_familia'              => $gbifDirect['tax_familia'],
                'tax_genero'               => $gbifDirect['tax_genero'],
                'tax_especie'              => $gbifDirect['tax_especie'],
                'tax_subespecie'           => '',
                'es_transgenico'           => $esTransgenico,
                'detalle_transgenico'      => '',
                'origen_semilla'           => '',
                'caracteristicas_semilla'  => '',
                'caracteristicas_planta'   => '',
                'fecha_siembra'            => '',
                'fecha_crecimiento'        => '',
                'fecha_recoleccion'        => '',
                'estado_tierra'            => '',
                'abono_es_ecologico'       => $abonoEco ? 1 : 0,
                'tipo_abono'               => '',
                'enfermedades_plagas'      => '',
                'tratamiento_es_ecologico' => $tratEco ? 1 : 0,
                'tratamiento_enfermedades' => '',
                'url_referencia'           => 'https://www.gbif.org/species/search?q=' . urlencode($gbifDirect['nombre_cientifico']),
                'fuente_ia'                => 'Taxonomía Oficial GBIF'
            );
        }

        return null;
    }
}
