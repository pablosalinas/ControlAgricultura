<?php
/**
 * Servicio de Inteligencia Artificial y Taxonomía Agronómica Contrastada para Cultivos
 * AgroDietario
 * 
 * Permite buscar cultivos, variedades botánicas y autocompletar su ficha técnica oficial y contrastada:
 * - Taxonomía botánica y nombre científico en latín
 * - Descripción agronómica real
 * - Formato de siembra / marcos de plantación
 * - Época de siembra, trasplante y recolección
 * - Tratamientos recomendados (100% ecológicos si se marca es_ecologico = 1)
 * - Requerimientos técnicos (suelo, riego, clima)
 * 
 * Compatible con PHP 5.6, 7.x, 8.x
 */

class CropAiService {

    /**
     * Catálogo botánico y agronómico de referencia para cultivos, frutales, coníferas y arbolado
     */
    private static $catalogoBase = array(
        'pinus_halepensis' => array(
            'keys' => array('pinus halepensis', 'pinus halepensis mill', 'pino carrasco', 'pino de alepo', 'pino carrasqueño', 'pino blanco'),
            'comun' => 'Pino carrasco',
            'cientifico' => 'Pinus halepensis Mill.',
            'tipo' => 'otro',
            'variedades' => array(),
            'ciclo' => 365,
            'formato' => 'Marco forestal 4x4 m a 6x6 m (aprox. 280-600 árboles/ha) o seto perimetral cortavientos a 2-3 m.',
            'siembra' => 'Otoño a finales de invierno (Noviembre a Febrero) en alvéolo forestal',
            'trasplante' => 'Noviembre a Marzo (aprovechando la humedad estacional del suelo)',
            'recoleccion' => 'Aprovechamiento silvícola / Piñas y biomasa (otoño-invierno)',
            'desc' => 'Árbol conífero perennifolio de la familia Pinaceae que alcanza de 10 a 20 m de altura. Muy resistente a la aridez y bien adaptado al piso bioclimático termomediterráneo y mesomediterráneo.',
            'req' => 'Extraordinaria resistencia a la sequía y suelos calizos pobres o erosionados. Gran demanda de insolación directa. No tolera encharcamientos permanentes ni asfixia radicular.',
            'trat_conv' => "• Procesionaria del pino (Thaumetopoea pityocampa): Monitoreo de vuelos con trampas de feromonas en verano y tratamiento con insecticida regulador del crecimiento o piretroide en otoño.\n• Eliminación mecánica y quema controlada de bolsones en copas bajas durante el invierno.\n• Monitoreo de escolítidos (Tomicus piniperda) en madera seca o tras sequías prolongadas.",
            'trat_eco' => "• Manejo 100% Ecológico y Silvícola Sostenible:\n• Procesionaria del pino: Trampeo masivo con feromonas de confusión sexual (Gcolit) durante el vuelo estival (julio-septiembre).\n• Aplicación de Bacillus thuringiensis kurstaki en otoño (estados larvarios iniciales L1-L3).\n• Anillos perimetrales y trampas de bajada en el tronco entre enero y marzo para interceptar las procesiones hacia el suelo.\n• Fomento de biodiversidad: Instalación de cajas nido para aves insectívoras (carboneros, herrerillos) y murciélagos."
        ),
        'pinus_pinea' => array(
            'keys' => array('pinus pinea', 'pino piñonero', 'pino albar', 'pino manso'),
            'comun' => 'Pino piñonero',
            'cientifico' => 'Pinus pinea L.',
            'tipo' => 'frutal',
            'variedades' => array(),
            'ciclo' => 365,
            'formato' => 'Marco amplio para aprovechamiento de piñón: 7x7 m o 8x8 m (150-200 árboles/ha).',
            'siembra' => 'Otoño a Invierno en semillero o cepellón forestal',
            'trasplante' => 'Diciembre a Marzo',
            'recoleccion' => 'Noviembre a Febrero (Recolección de piñas maduras)',
            'desc' => 'Árbol conífero perennifolio caracterizado por su copa en forma de sombrilla o parasol. Muy apreciado por la producción de piñones comestibles de alto valor comercial.',
            'req' => 'Prefiere suelos arenosos, sueltos y bien drenados. Tolera muy bien la sequía y los vientos costeros y continentales.',
            'trat_conv' => "• Control de procesionaria del pino y de Leptoglossus occidentalis (chinche americana que daña los piñones).",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Trampeo con feromonas de procesionaria y aplicaciones de Bacillus thuringiensis kurstaki en otoño.\n• Manejo silvícola mediante podas de formación y aclareo para maximizar insolación de copa."
        ),
        'almendro' => array(
            'keys' => array('almendro', 'almendros', 'prunus dulcis', 'prunus amygdalus'),
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
            'keys' => array('olivo', 'olivar', 'olivos', 'olea europaea'),
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
        'lechuga' => array(
            'keys' => array('lechuga', 'lechugas', 'lactuca sativa', 'cogollos', 'baby leaf'),
            'comun' => 'Lechuga',
            'cientifico' => 'Lactuca sativa',
            'tipo' => 'horticola',
            'variedades' => array('Romana', 'Iceberg', 'Baby', 'Batavia', 'Hoja de Roble', 'Lollo Rosso', 'Trocadero', 'Cogollos'),
            'ciclo' => 65,
            'formato' => 'Mesetas con 3-4 líneas, marco 0.25x0.25 m o 0.30x0.30 m (aprox. 60.000-80.000 plantas/ha). En baby leaf densidad mucho mayor.',
            'siembra' => 'Agosto a Febrero en bandejas de alvéolos',
            'trasplante' => 'Septiembre a Marzo',
            'recoleccion' => 'Noviembre a Mayo (Cosecha escalonada según acogollado o corte baby leaf)',
            'desc' => 'Hortícola de hoja de ciclo rápido muy cultivada en otoño, invierno y primavera en el sureste peninsular.',
            'req' => 'Clima templado a fresco. Requiere humedad constante y uniforme sin encharcamiento. Sensible a la salinidad excesiva y al espigado prematuro por calor.',
            'trat_conv' => "• Prevención de Mildiu (Bremia lactucae) y Esclerotinia con fungicidas registrados.\n• Control de orugas cogolleras (Spodoptera, Helicoverpa) y pulgón verde de la lechuga (Nasonovia ribisnigri).",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Mildiu: Cobre a dosis mínimas autorizadas y extracto de equiseto (cola de caballo).\n• Orugas defoliadoras: Bacillus thuringiensis kurstaki antes del acogollado.\n• Pulgón: Jabón potásico preventivo al 1.5% y fomento de mariquitas (coccinélidos)."
        ),
        'tomate' => array(
            'keys' => array('tomate', 'tomatera', 'tomates', 'solanum lycopersicum'),
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
            'req' => 'Exige alta luminosidad, temperaturas cálidas (20-28ºC) y riego frecuente y regular por goteo para evitar podredumbre apical (peseta). Suelos ricos en materia orgánica.',
            'trat_conv' => "• Tratamientos preventivos contra Mildiu y Alternaria con fungicidas registrados.\n• Control de Tuta absoluta y Mosca blanca con insecticidas específicos.\n• Aporte de Calcio foliar para prevenir necrosis apical.",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Tuta absoluta: Trampas de feromonas de confusión sexual + Bacillus thuringiensis kurstaki cada 7-10 días + Suelta de fauna auxiliar (Nesidiocoris tenuis).\n• Mildiu y Alternaria: Cobre a dosis bajas y decocción de cola de caballo.\n• Mosca blanca y Pulgón: Jabón potásico al 2% con aceite de parafina o extracto de Neem."
        ),
        'pimiento' => array(
            'keys' => array('pimiento', 'pimientos', 'pimentera', 'capsicum annuum'),
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
        'vid' => array(
            'keys' => array('vid', 'vitis vinifera', 'cepa', 'uvas', 'uva', 'viñedo'),
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
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Oídio (ceniza): Azufre en espolvoreo tras lluvias o en brotación activa.\n• Mildiu: Cobre fijado con dosis reducidas y fitofortificantes.\n• Polilla del racimo: Confusión sexual mediante difusores de feromonas (Isonet-L) y aplicaciones de Bacillus thuringiensis.\n• Botrytis: Deshojado alrededor de racimos para ventilación y pulverización de extracto de semilla de cítricos."
        ),
        'limonero' => array(
            'keys' => array('limonero', 'limoneros', 'citrus x limon', 'limon'),
            'comun' => 'Limonero',
            'cientifico' => 'Citrus x limon',
            'tipo' => 'citrico',
            'variedades' => array('Fino / Primofiori', 'Verna', 'Eureka', 'Lisbon'),
            'ciclo' => 365,
            'formato' => 'Marco de 6x4 m o 5x4 m (400-500 árboles/ha). En caballones amplios.',
            'siembra' => 'Primavera (Marzo a Mayo)',
            'trasplante' => 'Abril - Junio (Planta en maceta/cepellón injertada)',
            'recoleccion' => 'Octubre a Mayo (Fino) / Febrero a Julio (Verna)',
            'desc' => 'Cítrico perennifolio muy productivo y floreciente varias veces al año en climas subtropicales y mediterráneos costeros y vegas fértiles.',
            'req' => 'Muy sensible a las heladas (daños a -2ºC). Demanda riegos copiosos en verano y suelos profundos y permeables con bajo contenido en caliza activa.',
            'trat_conv' => "• Tratamientos contra Mosca blanca algodonosa, Cochinillas y Piojo rojo de California con aceites minerales e insecticidas autorizados.\n• Aguado y Alternaria con fungicidas cúpricos en otoño.",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Piojo rojo y cochinillas: Aceite de parafina de alta pureza (1%) en verano + Suelta de parasitoides (Aphytis melinus).\n• Mosca blanca: Jabón potásico y fauna auxiliar (Cales noacki).\n• Aguado (Phytophthora): Tratamiento foliar con cobre en faldas tras lluvias intensas."
        ),
        'naranjo' => array(
            'keys' => array('naranjo', 'naranjos', 'citrus sinensis', 'naranja'),
            'comun' => 'Naranjo',
            'cientifico' => 'Citrus sinensis',
            'tipo' => 'citrico',
            'variedades' => array('Navelina', 'Navel Late', 'Valencia Late', 'Salustiana', 'Lane Late'),
            'ciclo' => 365,
            'formato' => 'Marco 6x4 m o 5x4 m (400-500 árboles/ha).',
            'siembra' => 'Primavera (Abril-Mayo)',
            'trasplante' => 'Abril - Junio con cepellón',
            'recoleccion' => 'Noviembre a Junio (según grupo Navel o Blancas tardías)',
            'desc' => 'Árbol cítrico perennifolio de copa redondeada y aromático azahar. Fruto dulce en hesperidio de alto contenido vitamínico.',
            'req' => 'Exposición soleada, sensible a heladas de -3ºC. Suelos francos, aireados y con agua de riego de baja conductividad.',
            'trat_conv' => "• Control de Mosca de la fruta (Ceratitis capitata) con trampeo y tratamientos con cebo.\n• Piojo rojo de California y Prays citri.",
            'trat_eco' => "• Cuidado 100% Ecológico:\n• Mosca de la fruta: Trampas de captura masiva con atrayente alimentario y caolín foliar.\n• Pulgón y cochinillas: Jabón potásico y aceite de parafina."
        ),
        'mandarino' => array(
            'keys' => array('mandarino', 'mandarinos', 'citrus reticulata', 'mandarina', 'clementina'),
            'comun' => 'Mandarino / Clementino',
            'cientifico' => 'Citrus reticulata',
            'tipo' => 'citrico',
            'variedades' => array('Clemenules', 'Oronules', 'Marisol', 'Tango', 'Nadorcott', 'Hernandina'),
            'ciclo' => 365,
            'formato' => 'Marco de 5x4 m o 5x3.5 m (500-600 árboles/ha).',
            'siembra' => 'Primavera',
            'trasplante' => 'Abril a Junio',
            'recoleccion' => 'Octubre a Febrero (Clementinas) / Enero a Abril (Híbridos tardíos)',
            'desc' => 'Cítrico de porte mediano, muy apreciado por su fruto de fácil pelado y excelente calidad gustativa.',
            'req' => 'Suelos fértiles y permeables. Gran sensibilidad al frío severo.',
            'trat_conv' => "• Mosca de la fruta en maduración y araña roja en verano.",
            'trat_eco' => "• Trampeo masivo con Ceratitis capitata, jabón potásico y azufre contra ácaros."
        ),
        'melocotonero' => array(
            'keys' => array('melocotonero', 'melocotoneros', 'prunus persica', 'melocoton', 'paraguayo', 'nectarina'),
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
        'albaricoquero' => array(
            'keys' => array('albaricoquero', 'albaricoqueros', 'prunus armeniaca', 'albaricoque', 'damasco'),
            'comun' => 'Albaricoquero',
            'cientifico' => 'Prunus armeniaca',
            'tipo' => 'frutal',
            'variedades' => array('Búlida', 'Moniquí', 'Canino', 'Colorado', 'Mirlo Blanco', 'Rojo Pasión'),
            'ciclo' => 120,
            'formato' => 'Marco en vaso 6x5 m o 5x5 m (330-400 árboles/ha).',
            'siembra' => 'Diciembre a Febrero',
            'trasplante' => 'Enero a Marzo a raíz desnuda o cepellón',
            'recoleccion' => 'Mayo a Julio',
            'desc' => 'Frutal de hueso rústico de floración precoz. Fruto anaranjado aterciopelado de extraordinario aroma.',
            'req' => 'Tolera suelos calizos y secanos frescos. Muy sensible a heladas en floración y a asfixia radicular.',
            'trat_conv' => "• Monilia y cribado en floración.\n• Mosca de la fruta y anarsia.",
            'trat_eco' => "• Cobre en parada invernal, trampas de mosca de la fruta y Bacillus thuringiensis."
        ),
        'cebada' => array(
            'keys' => array('cebada', 'hordeum vulgare', 'cebadas'),
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
        'trigo' => array(
            'keys' => array('trigo', 'trigos', 'triticum aestivum', 'triticum durum'),
            'comun' => 'Trigo',
            'cientifico' => 'Triticum aestivum',
            'tipo' => 'cereal',
            'variedades' => array('Berdun', 'Nogal', 'Arthur Nick', 'Camargo', 'García'),
            'ciclo' => 190,
            'formato' => 'Siembra mecánica en líneas separadas 12-16 cm (180-220 kg semilla/ha).',
            'siembra' => 'Noviembre a Diciembre',
            'trasplante' => 'No aplica (Siembra directa)',
            'recoleccion' => 'Junio a Julio',
            'desc' => 'Cereal de invierno esencial para panificación y sémolas. Exigente en abonado nitrogenado y condiciones de espigado.',
            'req' => 'Suelos francos a arcillosos, fértiles y con buena retención hídrica.',
            'trat_conv' => "• Herbicidas selectivos de invierno y fungicida preventivo de Roya y Septoria.",
            'trat_eco' => "• Rotación con barbecho y leguminosas, desherbado mecánico y biofertilización microbiana."
        ),
        'ajo' => array(
            'keys' => array('ajo', 'ajos', 'allium sativum'),
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
        ),
        'cebolla' => array(
            'keys' => array('cebolla', 'cebollas', 'allium cepa'),
            'comun' => 'Cebolla',
            'cientifico' => 'Allium cepa',
            'tipo' => 'horticola',
            'variedades' => array('Babosa', 'Medio Grano', 'Grano de Oro', 'Morada de Amposta', 'Figueres'),
            'ciclo' => 140,
            'formato' => 'Lomos o mesetas a 30-40 cm entre líneas y 10-15 cm entre plantas.',
            'siembra' => 'Septiembre a Noviembre (Temprana) / Enero a Febrero (Tardía)',
            'trasplante' => 'Diciembre a Febrero',
            'recoleccion' => 'Mayo a Agosto',
            'desc' => 'Hortícola bulbosa fundamental en la dieta mediterránea de ciclo bianual cultivada como anual.',
            'req' => 'Suelos fértiles, esponjosos y ricos en potasio. Riego regular por goteo sin encharcamiento.',
            'trat_conv' => "• Mildiu de la cebolla (Peronospora destructor) y trips.",
            'trat_eco' => "• Cobre preventivo, cola de caballo y trampas azules contra trips."
        ),
        'brocoli' => array(
            'keys' => array('brocoli', 'brócoli', 'brassica oleracea var. italica'),
            'comun' => 'Brócoli',
            'cientifico' => 'Brassica oleracea var. italica',
            'tipo' => 'horticola',
            'variedades' => array('Parthenon', 'Naxos', 'Gea', 'Monaco', 'Ironman'),
            'ciclo' => 85,
            'formato' => 'Caballones a 80-100 cm y 35-45 cm entre plantas (28.000-35.000 plantas/ha).',
            'siembra' => 'Julio a Noviembre en semillero',
            'trasplante' => 'Agosto a Diciembre',
            'recoleccion' => 'Noviembre a Abril',
            'desc' => 'Crucífera de gran valor exportador en el sureste ibérico durante el invierno. Inflorescencia verde carnosa rica en vitaminas y sulforafano.',
            'req' => 'Clima templado a fresco. Demanda elevada humedad y nitrógeno asimilable.',
            'trat_conv' => "• Control de orugas de la col (Pieris brassicae, Plutella xylostella) y pulgón ceroso.",
            'trat_eco' => "• Bacillus thuringiensis kurstaki y jabón potásico con aceite de neem."
        ),
        'alcachofa' => array(
            'keys' => array('alcachofa', 'alcachofas', 'cynara cardunculus', 'cynara scolymus'),
            'comun' => 'Alcachofa',
            'cientifico' => 'Cynara cardunculus var. scolymus',
            'tipo' => 'horticola',
            'variedades' => array('Blanca de Tudela', 'Lorca', 'Green Globe', 'Symphony'),
            'ciclo' => 150,
            'formato' => 'Caballones a 1-1.2 m entre líneas y 0.7-0.8 m entre plantas por esquejes (zuecas) o semilla.',
            'siembra' => 'Julio - Agosto (Plantación de zuecas/estaquillas)',
            'trasplante' => 'Agosto - Septiembre',
            'recoleccion' => 'Noviembre a Mayo',
            'desc' => 'Planta perenne cultivada como anual o plurianual. Capítulos florales comestibles carnosos y tónicos hepáticos.',
            'req' => 'Gran demanda de riego y suelos profundos y francos. Sensible a heladas directas.',
            'trat_conv' => "• Oidiopsis (Leveillula) y pulgón negro de la alcachofa.",
            'trat_eco' => "• Azufre mojable contra oidiopsis y jabón potásico con azadiractina contra pulgón."
        ),
        'nogal' => array(
            'keys' => array('nogal', 'nogales', 'juglans regia', 'nuez'),
            'comun' => 'Nogal',
            'cientifico' => 'Juglans regia',
            'tipo' => 'frutal',
            'variedades' => array('Chandler', 'Franquette', 'Howard', 'Serr'),
            'ciclo' => 240,
            'formato' => 'Marco amplio 8x8 m o 10x10 m (100-150 árboles/ha).',
            'siembra' => 'Diciembre a Febrero',
            'trasplante' => 'Enero - Marzo a raíz desnuda',
            'recoleccion' => 'Septiembre a Octubre',
            'desc' => 'Árbol caducifolio de gran porte, valiosa madera y nuez comestible de alto valor cardiosaludable.',
            'req' => 'Requiere 700-1000 horas de frío invernal y suelos fértiles, profundos y sin caliza excesiva.',
            'trat_conv' => "• Bacteriosis (Xanthomonas juglandis) y Carpocapsa (Cydia pomonella).",
            'trat_eco' => "• Cobre en caída de hoja y desborre, y trampas de confusión sexual contra carpocapsa."
        ),
        'pistacho' => array(
            'keys' => array('pistacho', 'pistachero', 'pistacia vera'),
            'comun' => 'Pistachero',
            'cientifico' => 'Pistacia vera',
            'tipo' => 'frutal',
            'variedades' => array('Kerman (hembra)', 'Sirora', 'Peter (macho)', 'C-Especial'),
            'ciclo' => 220,
            'formato' => 'Marco 7x6 m o 7x7 m (aprox. 200-240 árboles/ha) con proporción 1 macho por cada 8-10 hembras.',
            'siembra' => 'Diciembre a Febrero en raíz desnuda o cepellón injertado sobre cornicabra (P. terebinthus)',
            'trasplante' => 'Enero a Marzo',
            'recoleccion' => 'Septiembre a Octubre',
            'desc' => 'Árbol dioico muy longevo de gran rentabilidad en zonas de interior con inviernos fríos y veranos secos y calurosos.',
            'req' => 'Exige 800-1000 horas de frío y veranos con baja humedad para cuajado. Gran tolerancia a la sequía.',
            'trat_conv' => "• Botryosphaeria y Clytra ataphylea.",
            'trat_eco' => "• Cobre preventivo invernal, caolín y trampas contra insectos defoliadores."
        ),
        'higuera' => array(
            'keys' => array('higuera', 'higueras', 'ficus carica', 'higo', 'breva'),
            'comun' => 'Higuera',
            'cientifico' => 'Ficus carica',
            'tipo' => 'frutal',
            'variedades' => array('Cuello de Dama', 'Calabacita', 'De la Reina', 'San Antonio'),
            'ciclo' => 180,
            'formato' => 'Marco 6x6 m o 7x6 m en vaso tradicional.',
            'siembra' => 'Invierno (Enero a Marzo)',
            'trasplante' => 'Febrero - Marzo',
            'recoleccion' => 'Junio - Julio (Brevas) / Agosto - Octubre (Higos)',
            'desc' => 'Árbol rústico de copa extendida y sicono comestible muy dulce y nutritivo.',
            'req' => 'Muy resistente a la sequía y suelos calizos y pedregosos.',
            'trat_conv' => "• Mosca del higo y cochinilla de la higuera.",
            'trat_eco' => "• Trampas de captura masiva contra mosca y aceite de parafina."
        ),
        'granado' => array(
            'keys' => array('granado', 'granados', 'punica granatum', 'granada'),
            'comun' => 'Granado',
            'cientifico' => 'Punica granatum',
            'tipo' => 'frutal',
            'variedades' => array('Mollar de Elche', 'Wonderful', 'Acco', 'Smith'),
            'ciclo' => 210,
            'formato' => 'Marco 5x4 m o 5x3 m (500-660 árboles/ha).',
            'siembra' => 'Diciembre a Febrero',
            'trasplante' => 'Enero a Marzo',
            'recoleccion' => 'Septiembre a Noviembre',
            'desc' => 'Frutal caducifolio mediterráneo adaptado a suelos áridos y aguas moderadamente salinas.',
            'req' => 'Tolera sequía, suelos calizos y aguas salobres. Demanda veranos calurosos.',
            'trat_conv' => "• Barrenador del fruto (Cryptoblabes) y pulgón.",
            'trat_eco' => "• Bacillus thuringiensis y caolín foliar para evitar golpes de sol en frutos."
        )
    );

    /**
     * Consulta agronómica inteligente y contrastada
     * @param string $query Nombre común o variedad buscada
     * @param bool $isOrganic Si se requiere protocolo 100% ecológico
     * @param string $preferredType Tipo de cultivo preferido (opcional)
     * @return array Datos agronómicos normalizados o indicación de no contrastado
     */
    public static function searchCrop($query, $isOrganic = false, $preferredType = '') {
        $cleanQuery = trim($query);
        if (empty($cleanQuery)) {
            return array(
                'success' => false,
                'message' => 'Por favor, indica el nombre de un cultivo o variedad.'
            );
        }

        $normQuery = self::normalizeText($cleanQuery);

        // 1. Intentar llamar a API externa de IA (Gemini) si está configurada
        $aiResult = self::queryExternalAi($cleanQuery, $isOrganic, $preferredType);
        if ($aiResult !== null && !empty($aiResult['nombre_comun']) && empty($aiResult['no_contrastado'])) {
            return $aiResult;
        }

        // 2. Comprobar en el Catálogo Botánico y Agronómico Local Experto (< 1ms)
        $matchedKey = null;
        $matchedKeyword = '';

        foreach (self::$catalogoBase as $key => $data) {
            foreach ($data['keys'] as $k) {
                $normK = self::normalizeText($k);
                // Coincidencia exacta o contenida como término delimitado
                if ($normQuery === $normK || strpos($normQuery, $normK) !== false) {
                    $matchedKey = $key;
                    $matchedKeyword = $k;
                    break 2;
                }
            }
        }

        if ($matchedKey !== null) {
            $base = self::$catalogoBase[$matchedKey];

            // Detección de variedad SIN INVENTAR:
            $detectedVar = '';

            // A) Verificar si coincide con alguna variedad conocida
            if (!empty($base['variedades'])) {
                foreach ($base['variedades'] as $v) {
                    if (strpos($normQuery, self::normalizeText($v)) !== false) {
                        $detectedVar = $v;
                        break;
                    }
                }
            }

            // B) Si el usuario escribió un término adicional que no está en el catálogo
            // Ej: "lechuga baby" -> detecta variedad "Baby"
            if (empty($detectedVar) && !empty($matchedKeyword)) {
                $rem = trim(preg_replace('/\b(' . preg_quote($matchedKeyword, '/') . ')\b/iu', '', $cleanQuery));
                $rem = trim(preg_replace('/\b(de|del|el|la|los|las|cultivo|variedad|planta)\b/iu', '', $rem));
                if (!empty($rem) && strlen($rem) >= 2 && strlen($rem) <= 25) {
                    // Evitar que abreviaturas taxonómicas de autores (Mill, L., etc.) se tomen como variedad comercial
                    if (!preg_match('/^(mill|miller|l|linneo|lam|lamarck|dc|de candolle|batsch|adans|pers|roem|schult)\.?$/i', $rem)) {
                        $detectedVar = ucwords(mb_strtolower($rem, 'UTF-8'));
                    }
                }
            }

            // Ajuste de ciclo y formato para lechuga baby o variedades tempranas
            $ciclo = (int)$base['ciclo'];
            $formato = $base['formato'];
            $desc = $base['desc'];
            if ($matchedKey === 'lechuga' && stripos($detectedVar, 'baby') !== false) {
                $ciclo = 50;
                $formato = 'Mesetas de alta densidad para corte baby leaf (separación entre líneas 15 cm) o marco 0.20x0.20 m.';
                $desc = 'Hortícola de hoja cosechada en estadio juvenil precoz (baby leaf / cogollitos). Hojas muy tiernas y sabrosas con rápido ciclo de cultivo.';
            }

            $tratamientos = $isOrganic ? $base['trat_eco'] : $base['trat_conv'];

            return array(
                'success'                  => true,
                'nombre_comun'             => ucfirst($base['comun']),
                'nombre_cientifico'        => $base['cientifico'],
                'variedad'                 => $detectedVar,
                'tipo_cultivo'             => !empty($preferredType) ? $preferredType : $base['tipo'],
                'dias_ciclo_aprox'         => $ciclo,
                'descripcion'              => $desc,
                'formato_siembra'          => $formato,
                'epoca_siembra'            => $base['siembra'],
                'epoca_trasplante'         => $base['trasplante'],
                'epoca_recoleccion'        => $base['recoleccion'],
                'es_ecologico'             => $isOrganic ? 1 : 0,
                'tratamientos_recomendados' => $tratamientos,
                'requerimientos_tecnicos'  => $base['req'],
                'url_consulta_botanica'    => 'https://es.wikipedia.org/wiki/' . urlencode(str_replace(' ', '_', explode(' ', $base['cientifico'])[0] . '_' . (isset(explode(' ', $base['cientifico'])[1]) ? explode(' ', $base['cientifico'])[1] : ''))),
                'fuente'                   => 'Catálogo Botánico y Agronómico Contrastado'
            );
        }

        // 3. Consulta Botánica en Red Contrastada (Wikipedia / Enciclopedia Botánica Libre)
        $wikiResult = self::queryWikipediaBotanical($cleanQuery, $isOrganic, $preferredType);
        if ($wikiResult !== null && !empty($wikiResult['success'])) {
            return $wikiResult;
        }

        // 4. Si NO se puede contrastar nada con certeza, NO INVENTAR información
        return array(
            'success' => false,
            'message' => "No se ha encontrado información agronómica contrastada para '{$cleanQuery}'. Por favor, introduce los datos de este cultivo manualmente para asegurar la exactitud de tu cuaderno de campo."
        );
    }

    /**
     * Consulta botánica contrastada mediante API de Wikipedia en español
     */
    private static function queryWikipediaBotanical($query, $isOrganic, $preferredType) {
        $ch = curl_init();
        $urlSearch = "https://es.wikipedia.org/w/api.php?action=query&list=search&srsearch=" . urlencode($query) . "&utf8=&format=json&srlimit=3";

        curl_setopt($ch, CURLOPT_URL, $urlSearch);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AgroDietarioBot/2.0 (agronomia@moratalla-murcia.com)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $resp = curl_exec($ch);
        curl_close($ch);

        if (!$resp) {
            return null;
        }

        $json = json_decode($resp, true);
        if (empty($json['query']['search'][0]['title'])) {
            return null;
        }

        $topResult = $json['query']['search'][0];
        $title = $topResult['title'];

        // Obtener resumen estructurado de la página de Wikipedia
        $chSum = curl_init();
        $urlSum = "https://es.wikipedia.org/api/rest_v1/page/summary/" . urlencode(str_replace(' ', '_', $title));
        curl_setopt($chSum, CURLOPT_URL, $urlSum);
        curl_setopt($chSum, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chSum, CURLOPT_TIMEOUT, 3);
        curl_setopt($chSum, CURLOPT_USERAGENT, 'AgroDietarioBot/2.0 (agronomia@moratalla-murcia.com)');
        curl_setopt($chSum, CURLOPT_SSL_VERIFYPEER, false);

        $respSum = curl_exec($chSum);
        curl_close($chSum);

        if (!$respSum) {
            return null;
        }

        $dataSum = json_decode($respSum, true);
        if (empty($dataSum['extract'])) {
            return null;
        }

        $extract = $dataSum['extract'];
        $descSum = isset($dataSum['description']) ? $dataSum['description'] : '';

        // Validar estrictamente que sea una especie botánica, árbol, conífera o vegetal real
        $esBotanica = preg_match('/(planta|árbol|arbol|arbusto|hierba|conífera|conifera|especie de|género de|familia|fruto|hortaliza|cereal|poaceae|pinaceae|solanaceae|rosaceae|fabaceae|lamiaceae)/iu', $extract . ' ' . $descSum);
        if (!$esBotanica) {
            return null; // No es un vegetal o especie botánica real, no inventar
        }

        // Determinar clasificación taxonómica real
        $tipo = !empty($preferredType) ? $preferredType : 'otro';
        if (empty($preferredType)) {
            if (preg_match('/(pino|conífera|conifera|pinaceae|cupressaceae|forestal|cedro|abeto|encina|roble)/iu', $extract . ' ' . $descSum)) {
                $tipo = 'otro'; // Árbol / conífera / forestal
            } elseif (preg_match('/(cereal|poaceae|gramínea|graminea|grano|trigo|cebada|avena|arroz|maíz|maiz)/iu', $extract . ' ' . $descSum)) {
                $tipo = 'cereal';
            } elseif (preg_match('/(cítrico|citrico|citrus|rutaceae|naranj|limon|mandarin)/iu', $extract . ' ' . $descSum)) {
                $tipo = 'citrico';
            } elseif (preg_match('/(olivo|olivar|olea|aceitun)/iu', $extract . ' ' . $descSum)) {
                $tipo = 'olivar';
            } elseif (preg_match('/(vid|vitis|viñedo|uva)/iu', $extract . ' ' . $descSum)) {
                $tipo = 'vinedo';
            } elseif (preg_match('/(frutal|árbol frutal|fruto comestible|rosaceae|drupa|pomo|melocoton|manzan|peral|cerezo|ciruelo|almendr)/iu', $extract . ' ' . $descSum)) {
                $tipo = 'frutal';
            } elseif (preg_match('/(hortaliza|hortícola|horticola|verdura|huerto|cultivada en huertos|lechuga|tomate|pimiento)/iu', $extract . ' ' . $descSum)) {
                $tipo = 'horticola';
            }
        }

        // Extraer nombre común y científico
        $nombreCientifico = $title;
        $nombreComun = $title;
        if (preg_match('/conocid[ao]\s+como\s+([^,\.\(\;]+)/iu', $extract, $mCom)) {
            $nombreComun = ucwords(trim($mCom[1]));
        }

        $ciclo = ($tipo === 'otro' || $tipo === 'frutal' || $tipo === 'citrico' || $tipo === 'olivar' || $tipo === 'vinedo') ? 365 : 120;
        $formato = ($tipo === 'otro') 
            ? 'Marco forestal o de arbolado 4x4 m a 6x6 m (o seto perimetral a 2-3 m).'
            : (($tipo === 'frutal' || $tipo === 'citrico') 
                ? 'Marco de plantación 5x5 m o 6x4 m (aprox. 400 árboles/ha).'
                : (($tipo === 'cereal') 
                    ? 'Siembra mecánica en líneas separadas 12-16 cm.' 
                    : 'En mesetas o caballones con marco adaptado a la densidad de siembra.'));

        $tratamientos = $isOrganic
            ? "• Cuidado 100% Ecológico (Reglamento UE 2018/848):\n- Monitoreo periódico de plagas con trampas cromáticas o de feromonas.\n- Tratamientos preventivos con Azufre mojable micronizado y Cobre a dosis autorizadas.\n- Control de plagas con Jabón potásico y Bacillus thuringiensis al atardecer."
            : "• Protocolo Convencional Integrado:\n- Aplicación de materias activas autorizadas en el Registro Oficial del MAPA según umbrales de tratamiento y respetando rigurosamente los plazos de seguridad.";

        return array(
            'success'                  => true,
            'nombre_comun'             => $nombreComun,
            'nombre_cientifico'        => $nombreCientifico,
            'variedad'                 => '',
            'tipo_cultivo'             => $tipo,
            'dias_ciclo_aprox'         => $ciclo,
            'descripcion'              => $extract,
            'formato_siembra'          => $formato,
            'epoca_siembra'            => 'Según condiciones estacionales de la especie',
            'epoca_trasplante'         => 'En reposo invernal o primavera según porte',
            'epoca_recoleccion'        => 'Al alcanzar la maduración comercial o silvícola',
            'es_ecologico'             => $isOrganic ? 1 : 0,
            'tratamientos_recomendados' => $tratamientos,
            'requerimientos_tecnicos'  => 'Buena aireación, insolación adecuada y drenaje sin encharcamientos prolongados.',
            'url_consulta_botanica'    => isset($dataSum['content_urls']['desktop']['page']) ? $dataSum['content_urls']['desktop']['page'] : 'https://es.wikipedia.org/wiki/' . urlencode($title),
            'fuente'                   => 'Taxonomía Botánica Contrastada (Wikipedia / Flora)'
        );
    }

    /**
     * Intenta consultar una API externa de IA (Gemini) si está disponible con prompt estricto
     */
    private static function queryExternalAi($query, $isOrganic, $preferredType) {
        $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');
        if (empty($apiKey) && function_exists('env')) {
            $apiKey = env('GEMINI_API_KEY', '');
        }

        if (empty($apiKey)) {
            return null;
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

            $tipoInfo = !empty($preferredType) ? "Tipo sugerido por el usuario: {$preferredType}." : "";
            $ecoInfo = $isOrganic 
                ? "IMPORTANTE: El cultivo es 100% ECOLÓGICO (Reglamento UE 2018/848). Los tratamientos deben ser estrictamente ecológicos homologados." 
                : "El cultivo es de producción integrada/convencional.";

            $prompt = "Eres un botánico e ingeniero agrónomo de referencia. Ficha agronómica exacta para: '{$query}'. {$tipoInfo} {$ecoInfo}\n" .
                      "REGLA CRUCIAL: Si '{$query}' es un árbol (pino, conífera, encina, etc.), clasifícalo en 'tipo_cultivo' como 'otro' (o 'frutal' si es de fruto seco), NUNCA como 'horticola'.\n" .
                      "REGLA DE VERACIDAD: Si el término buscado no corresponde a ninguna especie botánica real, responde ÚNICAMENTE con: {\"no_contrastado\": true}\n" .
                      "Si es una especie real, responde ÚNICAMENTE con un JSON válido con estas claves exactas:\n" .
                      "{\n" .
                      '  "nombre_comun": "Nombre común en español",' . "\n" .
                      '  "nombre_cientifico": "Nombre científico botánico en latín (ej: Pinus halepensis Mill.)",' . "\n" .
                      '  "variedad": "Variedad real o dejar vacío si no se especificó",' . "\n" .
                      '  "tipo_cultivo": "uno de: frutal, citrico, olivar, vinedo, horticola, cereal, otro",' . "\n" .
                      '  "dias_ciclo_aprox": 180,' . "\n" .
                      '  "descripcion": "Descripción botánica y agronómica real...",' . "\n" .
                      '  "formato_siembra": "Marco de plantación real...",' . "\n" .
                      '  "epoca_siembra": "Meses o estación...",' . "\n" .
                      '  "epoca_trasplante": "Meses o estación...",' . "\n" .
                      '  "epoca_recoleccion": "Meses o estación...",' . "\n" .
                      '  "es_ecologico": ' . ($isOrganic ? 1 : 0) . ",\n" .
                      '  "tratamientos_recomendados": "Tratamientos específicos y periodicidad...",' . "\n" .
                      '  "requerimientos_tecnicos": "Suelo, riego y clima...",' . "\n" .
                      '  "url_consulta_botanica": "https://es.wikipedia.org/wiki/..."' . "\n" .
                      "}";

            $payload = json_encode(array(
                'contents' => array(
                    array('parts' => array(array('text' => $prompt)))
                ),
                'generationConfig' => array(
                    'temperature' => 0.1,
                    'maxOutputTokens' => 1000
                )
            ));

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
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
                    if ($parsed && is_array($parsed) && empty($parsed['no_contrastado']) && !empty($parsed['nombre_comun'])) {
                        $parsed['success'] = true;
                        $parsed['fuente'] = 'Gemini AI Agronómico';
                        return $parsed;
                    }
                }
            }
        } catch (Exception $e) {
            // Continuar con catálogo local
        }

        return null;
    }

    /**
     * Normalizar texto para comparaciones botánicas (minúsculas y sin acentos)
     */
    private static function normalizeText($str) {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $unwanted = array(
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u',
            'à'=>'a', 'è'=>'e', 'ì'=>'i', 'ò'=>'o', 'ù'=>'u',
            'â'=>'a', 'ê'=>'e', 'î'=>'i', 'ô'=>'o', 'û'=>'u'
        );
        return strtr($str, $unwanted);
    }
}
