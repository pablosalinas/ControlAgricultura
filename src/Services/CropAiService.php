<?php
/**
 * Servicio de Inteligencia Artificial y Taxonomía Agronómica para Cultivos
 * AgroDietario
 * 
 * Permite buscar cultivos, variedades botánicas y autocompletar toda su ficha técnica:
 * - Taxonomía botánica y nombre científico en latín
 * - Descripción agronómica
 * - Formato de siembra / marcos de plantación
 * - Época de siembra, trasplante y recolección
 * - Tratamientos recomendados (100% ecológicos si se marca es_ecologico = 1)
 * - Requerimientos técnicos (suelo, riego, clima)
 * 
 * Compatible con PHP 5.6, 7.x, 8.x
 */

class CropAiService {

    /**
     * Base de conocimiento agronómico de referencia para el sureste español y clima mediterráneo
     */
    private static $catalogoBase = array(
        'almendro' => array(
            'comun' => 'Almendro',
            'cientifico' => 'Prunus dulcis',
            'tipo' => 'frutal',
            'variedades' => array('Guara', 'Lauranne', 'Vairo', 'Constantí', 'Marcona', 'Ferragnes', 'Antoñeta', 'Penta', 'Makako'),
            'ciclo' => 240,
            'formato' => 'Marco de plantación 6x6 m o 6x5 m (aprox. 270-330 árboles/ha). En superintensivo 3.5x1.2 m.',
            'siembra' => 'Noviembre a Febrero (Plantación a raíz desnuda o cepellón)',
            'trasplante' => 'Enero - Febrero (Parada invernal antes del desborre)',
            'recoleccion' => 'Agosto - Septiembre (Cuando el caparazón se abre)',
            'desc' => 'Árbol frutal caducifolio muy resistente a la sequía y bien adaptado a suelos calizos. Floración temprana muy vistosa (febrero-marzo) y fruto en drupa cuya semilla es el fruto seco comercial.',
            'req' => 'Suelos sueltos, profundos y bien drenados. Tolera pH alcalino (calizo). Necesidad de 300-500 horas frío según variedad. Sensible a heladas tardías de primavera y asfixia radicular.',
            'trat_conv' => "• Caída de hoja / Post-cosecha: Oxicloruro de cobre al 50% (300 g/hl) para prevención de monilia, cribado y chancro.\n• Salida de invierno (botón rosa): Fungicida preventivo contra abolladura y monilia (Difenoconazol o Boscalida).\n• Primavera (abril-mayo): Control de pulgón verde y mosquito verde con insecticida específico (ej. Flonicamid o Acetamiprid).\n• Verano: Monitoreo de oruga de la almendra (Anarsia) y araña roja (Acaricida específico si supera umbral).",
            'trat_eco' => "• Cuidado 100% Ecológico (Reglamento UE 2018/848):\n• Otoño / Caída de hojas: 2 aplicaciones de Caldo Bordelés o Cobre tribásico (máx. 4 kg Cu/ha/año) para cicatrizar heridas y prevenir monilia y cribado.\n• Salida de invierno (botón blanco): Azufre mojable micronizado (80%) contra oídio y ácaros.\n• Primavera: Jabón potásico al 1.5% + Extracto puro de Neem (Azadiractina) ante los primeros focos de pulgón. Bacillus thuringiensis kurstaki contra orugas defoliadoras.\n• Bioestimulación: Purín de ortiga y extracto de algas marinas (Ascophyllum nodosum) para reforzar las defensas naturales."
        ),
        'olivo' => array(
            'comun' => 'Olivo',
            'cientifico' => 'Olea europaea',
            'tipo' => 'olivar',
            'variedades' => array('Picual', 'Arbequina', 'Hojiblanca', 'Cornicabra', 'Manzanilla', 'Cuquillo', 'Gordal'),
            'ciclo' => 300,
            'formato' => 'Marco tradicional 8x8 m o 7x7 m (150-200 árboles/ha). Intensivo 7x4 m o seto 4x1.5 m.',
            'siembra' => 'Otoño (Octubre-Noviembre) o Primavera temprana (Marzo)',
            'trasplante' => 'Noviembre a Marzo (Planta de 1-2 años con cepellón)',
            'recoleccion' => 'Noviembre a Enero (Para almazara) / Septiembre-Octubre (Verdeo)',
            'desc' => 'Árbol perennifolio emblemático mediterráneo de extraordinaria longevidad y resistencia al estrés hídrico. Excelente adaptación a secanos y riegos de apoyo.',
            'req' => 'Gran resistencia a la sequía y calor estival. Prefiere suelos permeables, aireados y no tolera encharcamientos prolongados. Riego deficitario controlado (RDC) mejora la calidad del aceite.',
            'trat_conv' => "• Otoño (tras cosecha) y Primavera (antes de floración): Cobre contra Repilo (Spilocaea oleagina) y tuberculosis.\n• Verano (junio y septiembre): Tratamiento contra Mosca del olivo (Bactrocera oleae) con cebo o pulverización según conteo en mosqueros.\n• Polilla del olivo (Prays oleae): Tratamiento en generación antófaga si es necesario.",
            'trat_eco' => "• Cuidado 100% Ecológico (Norma CAERM / UE):\n• Preventivo de hongos: Oxicloruro de cobre al 0.3% en otoño e inicio de primavera contra Repilo.\n• Mosca del olivo: Trampeo masivo con mosqueros Olipe con fosfato diamónico al 3% y barrera mineral de caolín (arcilla micronizada al 3%) que blanquea el árbol y disuade la puesta.\n• Prays oleae: Bacillus thuringiensis kurstaki durante la floración.\n• Suelo: Cubierta vegetal de leguminosas en calles con desbroce mecánico."
        ),
        'tomate' => array(
            'comun' => 'Tomate',
            'cientifico' => 'Solanum lycopersicum',
            'tipo' => 'horticola',
            'variedades' => array('Muchamiel', 'Raf', 'Óptima', 'Rosa de Barbastro', 'Cherry', 'Corazón de Buey', 'Valenciano', 'Pera'),
            'ciclo' => 110,
            'formato' => 'En caballones con marco de 0.8-1 m entre líneas y 0.4-0.5 m entre plantas (20.000-25.000 plantas/ha). Entutorado.',
            'siembra' => 'Febrero - Marzo en semillero protegido',
            'trasplante' => 'Abril - Mayo (cuando cesa el riesgo de heladas)',
            'recoleccion' => 'Julio a Octubre (Escalonada según maduración)',
            'desc' => 'Planta herbácea anual de la familia solanácea. Gran demanda en fresco y conserva. Crecimiento indeterminado en tutores o determinado.',
            'req' => 'Exige alta luminosidad, temperaturas cálidas (20-28ºC) y riego frecuente y regular por goteo para evitar podredumbre apical (peseta). Suelos ricos en materia orgánica bien descompuesta.',
            'trat_conv' => "• Tratamientos preventivos contra Mildiu y Alternaria con fungicidas registrados (ej. Mancozeb, Azoxistrobin).\n• Control de Tutas (Tuta absoluta) y Mosca blanca con insecticidas específicos y alternancia de materias activas.\n• Aporte de Calcio foliar para prevenir 'peseta'.",
            'trat_eco' => "• Cuidado 100% Ecológico (Huerta Viva Bio):\n• Tuta absoluta: Trampas de feromonas de confusión sexual + Bacillus thuringiensis kurstaki cada 7-10 días + Suelta de fauna auxiliar (Nesidiocoris tenuis).\n• Mildiu y Alternaria: Cobre a dosis bajas (hidróxido de cobre) y decocción de cola de caballo (Equisetum).\n• Mosca blanca y Pulgón: Jabón potásico al 2% mezclado con aceite de parafina o extracto de Neem.\n• Oídio: Bicarbonato potásico (0.5%) y azufre micronizado."
        ),
        'vid' => array(
            'comun' => 'Vid',
            'cientifico' => 'Vitis vinifera',
            'tipo' => 'vinedo',
            'variedades' => array('Monastrell', 'Tempranillo', 'Syrah', 'Garnacha', 'Cabernet Sauvignon', 'Airén', 'Macabeo'),
            'ciclo' => 210,
            'formato' => 'Espaldera: 2.5-3 m entre filas x 1-1.2 m entre cepas. En vaso tradicional: 2.5x2.5 m.',
            'siembra' => 'Invierno (Diciembre a Marzo) en reposo vegetativo',
            'trasplante' => 'Enero - Marzo (Injerto sobre portainjerto resistente a filoxera)',
            'recoleccion' => 'Septiembre - Octubre (Vendimia según grado Baumé y acidez)',
            'desc' => 'Planta leñosa trepadora de gran valor vitivinícola. Muy adaptada a terruños áridos del interior peninsular, con racimos de bayas ricas en azúcares y taninos.',
            'req' => 'Gran rusticidad. Suelos francos o calizos con buen drenaje. Clima soleado con noches frescas en maduración favorece la concentración aromática y polifenoles.',
            'trat_conv' => "• Tratamientos de brotación contra Oídio y Mildiu (Cobre + Azufre / Tebuconazol).\n• Control de Polilla del racimo (Lobesia botrana) en 2ª y 3ª generación.\n• Control de Botrytis cinerea en envero antes de vendimia.",
            'trat_eco' => "• Cuidado 100% Ecológico (Vino Ecológico DOP):\n• Oídio (ceniza): Azufre en espolvoreo (polvo para espolvorear) tras lluvias o en brotación activa.\n• Mildiu: Cobre fijado con dosis reducidas y fitofortificantes.\n• Polilla del racimo: Confusión sexual mediante difusores de feromonas (Isonet-L) y aplicaciones de Bacillus thuringiensis.\n• Botrytis: Deshojado alrededor de racimos para ventilación y pulverización de extracto de semilla de cítricos."
        ),
        'pimiento' => array(
            'comun' => 'Pimiento',
            'cientifico' => 'Capsicum annuum',
            'tipo' => 'horticola',
            'variedades' => array('Lamuyo', 'Padrón', 'Gernika', 'Morrón', 'Italiano dulce', 'Piquillo', 'Bola'),
            'ciclo' => 120,
            'formato' => 'Marco de 0.8-1 m entre líneas y 0.35-0.45 m entre plantas en caballón con acolchado plástico o mulching.',
            'siembra' => 'Enero - Febrero en semillero con calor',
            'trasplante' => 'Abril - Mayo',
            'recoleccion' => 'Julio a Noviembre',
            'desc' => 'Hortícola solanácea anual de fruto carnoso, hueco y dulce o picante. Excelente fuente de vitamina C y antioxidantes.',
            'req' => 'Temperaturas óptimas 22-28ºC. Muy sensible a bajas temperaturas y encharcamientos. Riego por goteo dosificado.',
            'trat_conv' => "• Control de Trips (Frankliniella occidentalis) vector de TSWV con insecticidas autorizados.\n• Oidiopsis (Leveillula taurica) con fungicidas sistémicos.\n• Pulgón y Orugas con tratamientos dirigidos.",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Trips: Suelta masiva del chinche depredador Orius laevigatus y trampas adhesivas azules.\n• Oidiopsis: Azufre mojable micronizado o bicarbonato potásico.\n• Pulgones: Jabón potásico + Azadiractina.\n• Orugas (Spodoptera): Bacillus thuringiensis al atardecer."
        ),
        'lechuga' => array(
            'comun' => 'Lechuga',
            'cientifico' => 'Lactuca sativa',
            'tipo' => 'horticola',
            'variedades' => array('Iceberg', 'Romana', 'Batavia', 'Hoja de Roble', 'Lollo Rosso', 'Trocadero'),
            'ciclo' => 65,
            'formato' => 'Mesetas con 3-4 líneas, marco 0.25x0.25 m o 0.30x0.30 m (aprox. 60.000-80.000 plantas/ha).',
            'siembra' => 'Agosto a Febrero en bandejas de alvéolos',
            'trasplante' => 'Septiembre a Marzo',
            'recoleccion' => 'Noviembre a Mayo',
            'desc' => 'Hortícola de hoja de ciclo rápido muy cultivada en otoño e invierno en el sureste peninsular para exportación.',
            'req' => 'Clima templado a fresco. Requiere humedad constante y uniforme sin encharcamiento. Sensible a la salinidad excesiva y espigado con calor.',
            'trat_conv' => "• Prevención de Mildiu (Bremia lactucae) y Esclerotinia.\n• Control de orugas cogolleras y pulgón verde de la lechuga.",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Mildiu: Cobre a dosis mínimas autorizadas y extracto de equiseto.\n• Orugas: Bacillus thuringiensis kurstaki antes del acogollado.\n• Pulgón: Jabón potásico preventivo y fomento de mariquitas (coccinélidos)."
        ),
        'limonero' => array(
            'comun' => 'Limonero',
            'cientifico' => 'Citrus x limon',
            'tipo' => 'citrico',
            'variedades' => array('Fino / Primofiori', 'Verna', 'Eureka', 'Lisbon'),
            'ciclo' => 365,
            'formato' => 'Marco de 6x4 m o 5x4 m (400-500 árboles/ha). En caballones amplios.',
            'siembra' => 'Primavera (Marzo a Mayo)',
            'trasplante' => 'Abril - Junio (Planta en maceta/cepellón injertada)',
            'recoleccion' => 'Octubre a Mayo (Fino) / Febrero a Julio (Verna con rodrejos)',
            'desc' => 'Cítrico perennifolio muy productivo y floreciente varias veces al año en climas subtropicales y mediterráneos costeros y vegas fértiles.',
            'req' => 'Muy sensible a las heladas (daños a -2ºC). Demanda riegos copiosos en verano y suelos profundos y permeables con bajo contenido en caliza activa.',
            'trat_conv' => "• Tratamientos contra Mosca blanca algodonosa, Cochinillas y Piojo rojo de California con aceites minerales e insecticidas autorizados.\n• Aguado y Alternaria con fungicidas cúpricos en otoño.",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Piojo rojo y cochinillas: Aceite de parafina de alta pureza (1%) en verano + Suelta de parasitoides (Aphytis melinus).\n• Mosca blanca: Jabón potásico y fauna auxiliar (Cales noacki).\n• Aguado (Phytophthora): Tratamiento foliar con cobre en faldas tras lluvias intensas."
        ),
        'melocotonero' => array(
            'comun' => 'Melocotonero',
            'cientifico' => 'Prunus persica',
            'tipo' => 'frutal',
            'variedades' => array('Paraguayo', 'Platerina', 'Nectarina', 'Pavía amarillo', 'Babygold', 'Catherine'),
            'ciclo' => 150,
            'formato' => 'Marco en vaso 5x4 m o 5x3.5 m (500-600 árboles/ha). Eje central 4.5x2.5 m.',
            'siembra' => 'Noviembre a Febrero (raíz desnuda)',
            'trasplante' => 'Enero - Febrero',
            'recoleccion' => 'Mayo a Septiembre (según precocidad)',
            'desc' => 'Frutal de hueso de extraordinaria importancia económica en las vegas de regadío. Fruto carnoso, dulce y jugoso.',
            'req' => 'Demanda suficiente frío invernal (400-800 horas) y veranos cálidos para maduración. Requiere aclareo de frutos para buen calibre.',
            'trat_conv' => "• Abolladura (Taphrina deformans): Cobre en caída de hoja y desborre.\n• Mosca de la fruta (Ceratitis capitata): Trampeo y tratamientos con cebo.\n• Oídio y Monilia con fungicidas específicos.",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Abolladura y Cribado: Tratamientos oportunos con oxicloruro de cobre al 50% en caída de hoja y botón rosa.\n• Mosca de la fruta: Trampeo masivo con atrayente proteico (tipo Ceranock o Conetrap).\n• Pulgón verde: Jabón potásico + Azadiractina.\n• Oídio: Azufre mojable."
        ),
        'cebada' => array(
            'comun' => 'Cebada',
            'cientifico' => 'Hordeum vulgare',
            'tipo' => 'cereal',
            'variedades' => array('Pewter', 'Planet', 'Hispanic', 'Shakira', 'Cervezara'),
            'ciclo' => 170,
            'formato' => 'Siembra a voleo o chorrillo con sembradora mecánica a 12-15 cm entre líneas (160-200 kg semilla/ha). Profundidad 3-4 cm.',
            'siembra' => 'Noviembre - Diciembre (Siembra de invierno)',
            'trasplante' => 'No aplica (Siembra directa)',
            'recoleccion' => 'Mayo - Junio (Cuando el grano tiene < 13% de humedad)',
            'desc' => 'Cereal de invierno rústico, base de piensos y malta cervecera. Muy tolerante a la salinidad y a secanos semiáridos.',
            'req' => 'Bajo requerimiento hídrico. Tolera suelos pobres mejor que el trigo, pero no tolera encharcamientos.',
            'trat_conv' => "• Control de malas hierbas de hoja ancha y estrecha en post-emergencia precoz.\n• Fungicida contra Helmintosporiosis y Rincosporiosis si la primavera es húmeda.",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Control mecánico de adventicias con grada de púas o escardador en estado precoz.\n• Rotación de cultivos con leguminosas (veza o yeros) para fijación biológica de nitrógeno.\n• Tratamientos con azufre si aparece oídio."
        ),
        'ajo' => array(
            'comun' => 'Ajo',
            'cientifico' => 'Allium sativum',
            'tipo' => 'horticola',
            'variedades' => array('Morado de Las Pedroñeras', 'Blanco', 'Spring Blanco', 'Spring Violeta'),
            'ciclo' => 180,
            'formato' => 'En lomos o caballones, líneas separadas 40 cm y 10-12 cm entre dientes (unos 200.000 dientes/ha).',
            'siembra' => 'Noviembre a Enero (Ajo morado) / Septiembre-Octubre (Spring)',
            'trasplante' => 'No aplica (Dientes de siembra directa)',
            'recoleccion' => 'Junio - Julio (Cuando las hojas basales se secan)',
            'desc' => 'Bulbo condimentario y medicinal muy apreciado en gastronomía. Gran conservación en seco.',
            'req' => 'Suelos francos o franco-arenosos, bien drenados y sin exceso de humedad para evitar pudriciones.',
            'trat_conv' => "• Desinfección de semilla antes de siembra.\n• Control de Roya y Helmintosporiosis con fungicidas registrados.",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Desinfección natural de dientes con maceración en purín de ortiga o extracto de cola de caballo.\n• Roya del ajo: Caldo bordelés a dosis bajas y cola de caballo preventiva.\n• Aporte de compost maduro y ceniza de leña."
        )
    );

    /**
     * Consulta agronómica inteligente para un cultivo
     * @param string $query Nombre común o variedad buscada
     * @param bool $isOrganic Si se requiere protocolo 100% ecológico
     * @param string $preferredType Tipo de cultivo preferido (opcional)
     * @return array Datos agronómicos normalizados
     */
    public static function searchCrop($query, $isOrganic = false, $preferredType = '') {
        $q = trim(strtolower($query));

        // 1. Intentar llamar a API externa de IA (Gemini o OpenAI) si está configurada
        $aiResult = self::queryExternalAi($query, $isOrganic, $preferredType);
        if ($aiResult !== null && !empty($aiResult['nombre_comun'])) {
            return $aiResult;
        }

        // 2. Motor Heurístico Agronómico Experto
        // Buscar coincidencia en el catálogo base de referencia
        $matchedKey = null;
        foreach (self::$catalogoBase as $key => $data) {
            if (strpos($q, $key) !== false || strpos($key, $q) !== false) {
                $matchedKey = $key;
                break;
            }
            foreach ($data['variedades'] as $v) {
                if (strpos($q, strtolower($v)) !== false) {
                    $matchedKey = $key;
                    break 2;
                }
            }
        }

        if ($matchedKey !== null) {
            $base = self::$catalogoBase[$matchedKey];
            
            // Detectar si el usuario especificó una variedad particular en la búsqueda
            $detectedVar = '';
            foreach ($base['variedades'] as $v) {
                if (strpos($q, strtolower($v)) !== false) {
                    $detectedVar = $v;
                    break;
                }
            }
            if (empty($detectedVar) && !empty($base['variedades'])) {
                $detectedVar = $base['variedades'][0];
            }

            $tratamientos = $isOrganic ? $base['trat_eco'] : $base['trat_conv'];

            return array(
                'success'                  => true,
                'nombre_comun'             => ucfirst($base['comun']),
                'nombre_cientifico'        => $base['cientifico'],
                'variedad'                 => $detectedVar,
                'tipo_cultivo'             => !empty($preferredType) ? $preferredType : $base['tipo'],
                'dias_ciclo_aprox'         => (int)$base['ciclo'],
                'descripcion'              => $base['desc'],
                'formato_siembra'          => $base['formato'],
                'epoca_siembra'            => $base['siembra'],
                'epoca_trasplante'         => $base['trasplante'],
                'epoca_recoleccion'        => $base['recoleccion'],
                'es_ecologico'             => $isOrganic ? 1 : 0,
                'tratamientos_recomendados' => $tratamientos,
                'requerimientos_tecnicos'  => $base['req'],
                'url_consulta_botanica'    => 'https://es.wikipedia.org/wiki/' . urlencode(str_replace(' ', '_', $base['cientifico'])),
                'fuente'                   => 'Motor Agronómico Experto AgroDietario'
            );
        }

        // 3. Generador Agronómico Universal para cultivos libres no catalogados
        return self::generateGenericAgronomicProfile($query, $isOrganic, $preferredType);
    }

    /**
     * Generador de perfiles agronómicos universales para cualquier especie vegetal
     */
    private static function generateGenericAgronomicProfile($query, $isOrganic, $preferredType) {
        $nombre = ucwords(trim($query));
        $tipo = !empty($preferredType) ? $preferredType : 'horticola';

        // Inferir tipo de cultivo si no se indicó
        $q = strtolower($query);
        if (strpos($q, 'arbol') !== false || strpos($q, 'frutal') !== false || strpos($q, 'ciruelo') !== false || strpos($q, 'manzano') !== false || strpos($q, 'peral') !== false || strpos($q, 'cerezo') !== false) {
            $tipo = 'frutal';
        } elseif (strpos($q, 'naranj') !== false || strpos($q, 'mandarin') !== false || strpos($q, 'pomelo') !== false) {
            $tipo = 'citrico';
        } elseif (strpos($q, 'trigo') !== false || strpos($q, 'avena') !== false || strpos($q, 'centeno') !== false || strpos($q, 'maiz') !== false) {
            $tipo = 'cereal';
        } elseif (strpos($q, 'olivo') !== false) {
            $tipo = 'olivar';
        }

        // Taxonomía estimada
        $cientifico = 'Especie botánica de ' . $nombre;
        if ($tipo === 'frutal') {
            $formato = 'Marco de plantación 5x4 m o 6x5 m (aprox. 330-500 árboles/ha). Profundidad de hoyo 50-60 cm.';
            $siembra = 'Diciembre a Febrero (raíz desnuda)';
            $trasplante = 'Enero - Marzo';
            $recoleccion = 'Verano - Otoño (según maduración del fruto)';
            $ciclo = 210;
        } elseif ($tipo === 'citrico') {
            $formato = 'Marco 6x4 m en caballón ancho (400 árboles/ha).';
            $siembra = 'Marzo - Mayo';
            $trasplante = 'Abril - Junio con cepellón';
            $recoleccion = 'Otoño - Invierno';
            $ciclo = 300;
        } elseif ($tipo === 'cereal') {
            $formato = 'Siembra a chorrillo en líneas a 12-15 cm. Dosis 150-180 kg/ha. Profundidad 3-4 cm.';
            $siembra = 'Octubre a Diciembre';
            $trasplante = 'No aplica (siembra directa)';
            $recoleccion = 'Mayo - Junio';
            $ciclo = 160;
        } else {
            $formato = 'En líneas o caballones separados 70-90 cm y 30-40 cm entre plantas.';
            $siembra = 'Primavera / Otoño según ciclo';
            $trasplante = '3-4 semanas tras la siembra en semillero';
            $recoleccion = 'A los 70-120 días del trasplante';
            $ciclo = 90;
        }

        $desc = "Cultivo agronómico de {$nombre}. Planta clasificada en la categoría {$tipo}, orientada a la producción agrícola sostenible y adaptada a condiciones agronómicas locales.";
        $req = "Exposición soleada, suelos bien aireados con buen drenaje. Evitar encharcamientos radiculares. Riego regular ajustado al estado fenológico.";

        if ($isOrganic) {
            $tratamientos = "• PROTOCOLO DE CUIDADO 100% ECOLÓGICO (Reglamento UE 2018/848):\n" .
                            "1. Prevención Fúngica (Oídio/Mildiu): Tratamientos con Azufre mojable micronizado (80%) y preparados de Cobre a dosis moderadas en parada vegetativa o tras lluvias.\n" .
                            "2. Plagas chupadoras (Pulgón, Mosca blanca): Jabón potásico diluido al 1.5-2% + Aceite de Neem (Azadiractina) cada 10-15 días ante primeros focos.\n" .
                            "3. Orugas defoliadoras y lepidópteros: Pulverización de Bacillus thuringiensis (kurstaki) al atardecer cada 7-10 días.\n" .
                            "4. Fertilidad y suelo: Incorporación de compost vegetal maduro, abonos verdes y bioestimulantes a base de extracto de algas y purín de ortiga.";
        } else {
            $tratamientos = "• PROTOCOLO DE PRODUCCIÓN CONVENCIONAL INTEGRADA:\n" .
                            "1. Fungicidas preventivos autorizados en Registro MAPA al inicio de ciclo y en condiciones de alta humedad relativa.\n" .
                            "2. Insecticidas específicos respetando rigurosamente los plazos de seguridad (PS) y rotación de materias activas para evitar resistencias.\n" .
                            "3. Plan de abonado N-P-K equilibrado mediante fertirrigación según curvas de extracción del cultivo.";
        }

        return array(
            'success'                  => true,
            'nombre_comun'             => $nombre,
            'nombre_cientifico'        => $cientifico,
            'variedad'                 => 'Autóctona / Seleccionada',
            'tipo_cultivo'             => $tipo,
            'dias_ciclo_aprox'         => $ciclo,
            'descripcion'              => $desc,
            'formato_siembra'          => $formato,
            'epoca_siembra'            => $siembra,
            'epoca_trasplante'         => $trasplante,
            'epoca_recoleccion'        => $recoleccion,
            'es_ecologico'             => $isOrganic ? 1 : 0,
            'tratamientos_recomendados' => $tratamientos,
            'requerimientos_tecnicos'  => $req,
            'url_consulta_botanica'    => 'https://es.wikipedia.org/wiki/Especial:Buscar?search=' . urlencode($nombre),
            'fuente'                   => 'Generador Agronómico AgroDietario IA'
        );
    }

    /**
     * Intenta consultar una API externa de IA (Gemini / OpenAI) si está disponible
     */
    private static function queryExternalAi($query, $isOrganic, $preferredType) {
        $apiKey = getenv('GEMINI_API_KEY');
        if (empty($apiKey) && function_exists('env')) {
            $apiKey = env('GEMINI_API_KEY', '');
        }

        if (empty($apiKey)) {
            return null; // Usar el motor heurístico local
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

            $tipoInfo = !empty($preferredType) ? "Tipo sugerido: {$preferredType}." : "";
            $ecoInfo = $isOrganic 
                ? "IMPORTANTE: El cultivo es 100% ECOLÓGICO (Reglamento UE 2018/848). Los tratamientos recomendados y cuidados deben ser exclusivamente aptos para agricultura ecológica (cobre, azufre, jabón potásico, Bacillus thuringiensis, trampas, extracto de neem, abonos orgánicos), sin químicos de síntesis." 
                : "El cultivo es de agricultura convencional/integrada.";

            $prompt = "Eres un ingeniero agrónomo y botánico experto. Proporciona una ficha técnica agronómica completa para el cultivo: '{$query}'. {$tipoInfo} {$ecoInfo}\n" .
                      "Responde ÚNICAMENTE con un objeto JSON válido (sin markdown ni rodeado de backticks) con los siguientes campos exactos:\n" .
                      "{\n" .
                      '  "nombre_comun": "Nombre común en español",' . "\n" .
                      '  "nombre_cientifico": "Nombre científico botánico en latín (ej: Prunus dulcis)",' . "\n" .
                      '  "variedad": "Variedad más extendida o la especificada",' . "\n" .
                      '  "tipo_cultivo": "uno de: frutal, citrico, olivar, vinedo, horticola, cereal, otro",' . "\n" .
                      '  "dias_ciclo_aprox": 180,' . "\n" .
                      '  "descripcion": "Descripción botánica y agronómica apropiada del cultivo...",' . "\n" .
                      '  "formato_siembra": "Formato de siembra, marcos de plantación (ej. 6x6m), profundidad y densidad...",' . "\n" .
                      '  "epoca_siembra": "Meses o estación adecuada para la siembra",' . "\n" .
                      '  "epoca_trasplante": "Meses adecuados para el trasplante o plantación",' . "\n" .
                      '  "epoca_recoleccion": "Meses adecuados para la recolección o cosecha",' . "\n" .
                      '  "es_ecologico": ' . ($isOrganic ? 1 : 0) . ",\n" .
                      '  "tratamientos_recomendados": "Detalle de tratamientos recomendados con su periodicidad y aplicaciones...",' . "\n" .
                      '  "requerimientos_tecnicos": "Requerimientos de suelo, clima y riego...",' . "\n" .
                      '  "url_consulta_botanica": "https://es.wikipedia.org/wiki/..."' . "\n" .
                      "}";

            $payload = json_encode(array(
                'contents' => array(
                    array('parts' => array(array('text' => $prompt)))
                ),
                'generationConfig' => array(
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1200
                )
            ));

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            curl_close($ch);

            if ($response) {
                $resData = json_decode($response, true);
                if (!empty($resData['candidates'][0]['content']['parts'][0]['text'])) {
                    $rawText = trim($resData['candidates'][0]['content']['parts'][0]['text']);
                    $rawText = preg_replace('/^```(?:json)?/i', '', $rawText);
                    $rawText = preg_replace('/```$/', '', $rawText);
                    $parsed = json_decode(trim($rawText), true);
                    if ($parsed && is_array($parsed) && !empty($parsed['nombre_comun'])) {
                        $parsed['success'] = true;
                        $parsed['fuente'] = 'Gemini AI Agronómico';
                        return $parsed;
                    }
                }
            }
        } catch (Exception $e) {
            // Fallback a motor heurístico
        }

        return null;
    }
}
