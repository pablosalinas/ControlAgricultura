<?php
/**
 * Servicio de Inteligencia Artificial y Consulta Técnica de Productos Fitosanitarios
 * AgroDietario - Conector con Registro Oficial Fitosanitario y Modelo Agronómico
 *
 * Permite buscar productos por nombre comercial, materia activa o plaga objetivo,
 * extrayendo su funcionalidad, número de registro MAPA, modo de acción,
 * dosis recomendada, plazo de seguridad y compatibilidad ecológica.
 */

class PhytosanitaryAiService {

    /**
     * Catálogo técnico y registro oficial de referencia agronómica (España / UE)
     */
    private static function getBaseCatalog() {
        return array(
            // --- FUNGICIDAS CÚPRICOS Y PREVENTIVOS ---
            'nordox' => array(
                'nombre_comercial_producto' => 'Nordox 75 WG',
                'materia_activa'            => 'Óxido cuproso 75% [WG] p/p',
                'num_registro_oficial'      => 'ES-00145',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Fungicida / Bactericida cúprico de contacto',
                'modo_accion'               => 'Contacto foliar preventivo multisitio. Inhibe la germinación de esporas fúngicas.',
                'plaga_o_enfermedad_objetivo'=> 'Repilo (Venturia oleaginea), Tuberculosis (Pseudomonas savastanoi), Abolladura, Cribado y Monilia',
                'dosis_recomendada_ha'      => 1.500,
                'unidad_dosis'              => 'kg/ha',
                'plazo_seguridad_dias'      => 14,
                'observaciones_tecnicas'    => 'Apto para Agricultura Ecológica (Reglamento UE 2018/848). Aplicar de forma preventiva tras lluvias de otoño/primavera o caída de hojas. Excelente adherencia y resistencia al lavado por lluvia. No mezclar con aceites minerales en brotación tierna.'
            ),
            'cuprocol' => array(
                'nombre_comercial_producto' => 'Cuprocol',
                'materia_activa'            => 'Oxicloruro de cobre 38% [SC] p/v',
                'num_registro_oficial'      => '18.920',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Fungicida cúprico líquido en suspensión concentrada',
                'modo_accion'               => 'Contacto y barrera superficial protectora persistente.',
                'plaga_o_enfermedad_objetivo'=> 'Repilo, Mildiu, Bacteriosis, Cribado y Antracnosis',
                'dosis_recomendada_ha'      => 2.500,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 14,
                'observaciones_tecnicas'    => 'Apto para Agricultura Ecológica. Formulación líquida de fácil disolución sin obturar boquillas. Máximo 4 kg de Cobre metal/ha/año en cómputo plurianual según normativa comunitaria.'
            ),
            'caldo bordeles' => array(
                'nombre_comercial_producto' => 'Caldo Bordelés RSR',
                'materia_activa'            => 'Sulfato cuprocálcico 20% [WP] p/p',
                'num_registro_oficial'      => '12.890',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Fungicida tradicional de choque y cobertura',
                'modo_accion'               => 'Contacto preventivo. Gran persistencia por neutralización con cal.',
                'plaga_o_enfermedad_objetivo'=> 'Repilo del olivo, Mildiu de la vid, Bacteriosis y Abolladura',
                'dosis_recomendada_ha'      => 5.000,
                'unidad_dosis'              => 'kg/ha',
                'plazo_seguridad_dias'      => 14,
                'observaciones_tecnicas'    => 'Autorizado en Agricultura Ecológica. Muy tenaz sobre la cutícula vegetal. Realizar el tratamiento con vegetación seca y sin previsión de heladas inmediatas.'
            ),
            'kumulus' => array(
                'nombre_comercial_producto' => 'Kumulus DF',
                'materia_activa'            => 'Azufre 80% [WG] p/p',
                'num_registro_oficial'      => '16.520',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Fungicida / Acaricida antioídio',
                'modo_accion'               => 'Contacto directo y acción vapor por sublimación sobre micelio y ácaros.',
                'plaga_o_enfermedad_objetivo'=> 'Oídio (Cenicilla), Eriófidos, Ácaro de la roña y Araña roja',
                'dosis_recomendada_ha'      => 4.000,
                'unidad_dosis'              => 'kg/ha',
                'plazo_seguridad_dias'      => 5,
                'observaciones_tecnicas'    => 'Apto para Agricultura Ecológica. No aplicar con temperaturas superiores a 28-30ºC para evitar fitotoxicidad en hojas tiernas. Dejar un intervalo mínimo de 21 días con tratamientos a base de aceites.'
            ),
            'score' => array(
                'nombre_comercial_producto' => 'Score 25 EC',
                'materia_activa'            => 'Difenoconazol 25% [EC] p/v',
                'num_registro_oficial'      => '18.767',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Fungicida sistémico triazol (DMI)',
                'modo_accion'               => 'Sistemia acrópeta y translaminar. Efecto curativo precoz y preventivo prolongado.',
                'plaga_o_enfermedad_objetivo'=> 'Moteado, Repilo, Oidiopsis, Abolladura, Cribado y Monilia',
                'dosis_recomendada_ha'      => 0.400,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 14,
                'observaciones_tecnicas'    => 'Químico de síntesis convencional (NO apto en ecológico). Ideal en tratamientos curativos cuando el hongo ya ha penetrado en el tejido. Alternar con fungicidas de distinto mecanismo para evitar resistencias.'
            ),
            'aliette' => array(
                'nombre_comercial_producto' => 'Aliette WG',
                'materia_activa'            => 'Fosetil-Al 80% [WG] p/p',
                'num_registro_oficial'      => '19.344',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Fungicida sistémico bidireccional (ascendente y descendente)',
                'modo_accion'               => 'Sistemia completa floemática y xilemática. Estimula las defensas naturales (fitoalexinas).',
                'plaga_o_enfermedad_objetivo'=> 'Fitóftora (Phytophthora spp.), Aguado de los cítricos y Gomosis del cuello',
                'dosis_recomendada_ha'      => 3.000,
                'unidad_dosis'              => 'kg/ha',
                'plazo_seguridad_dias'      => 15,
                'observaciones_tecnicas'    => 'Convencional. Especialmente eficaz para podredumbres de raíz y cuello en frutales, cítricos y hortícolas. No mezclar con abonos nitrogenados foliares ni con cobre.'
            ),
            'switch' => array(
                'nombre_comercial_producto' => 'Switch',
                'materia_activa'            => 'Ciprodinil 37.5% + Fludioxonil 25% [WG] p/p',
                'num_registro_oficial'      => '22.459',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Fungicida antibotritis y antimonilia de amplio espectro',
                'modo_accion'               => 'Doble acción complementaria: sistémico translaminar + protector de superficie.',
                'plaga_o_enfermedad_objetivo'=> 'Botrytis cinerea (Podredumbre gris), Monilinia laxa y Monilinia fructigena',
                'dosis_recomendada_ha'      => 0.800,
                'unidad_dosis'              => 'kg/ha',
                'plazo_seguridad_dias'      => 7,
                'observaciones_tecnicas'    => 'Convencional. Muy indicado en floración y pre-recolección de frutales de hueso y viñedo para proteger la fruta.'
            ),

            // --- INSECTICIDAS Y BIOPESTICIDAS ---
            'spintor' => array(
                'nombre_comercial_producto' => 'Spintor 480 SC',
                'materia_activa'            => 'Spinosad 48% [SC] p/v (Spinosina A + Spinosina D)',
                'num_registro_oficial'      => '23.328',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Insecticida biológico natural derivado de Saccharopolyspora spinosa',
                'modo_accion'               => 'Contacto e ingestión. Modulador alostérico del receptor nicotínico de acetilcolina (IRAC Grupo 5). Rápido efecto de choque.',
                'plaga_o_enfermedad_objetivo'=> 'Orugas defoliadoras, Trips (Frankliniella), Mosca de la fruta (Ceratitis capitata), Carpocapsa y Tuta absoluta',
                'dosis_recomendada_ha'      => 0.250,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 3,
                'observaciones_tecnicas'    => 'Apto para Agricultura Ecológica (Certificado Oficial). Respetuoso con insectos auxiliares una vez seco el caldo. Aplicar preferentemente al atardecer para evitar fotodegradación solar rápida.'
            ),
            'bacillus' => array(
                'nombre_comercial_producto' => 'Bacillus thuringiensis (Dipel DF / Belthirul)',
                'materia_activa'            => 'Bacillus thuringiensis kurstaki cepa PB-54 (32 Millones UI/g)',
                'num_registro_oficial'      => '19.643',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Bioinsecticida bacteriano selectivo',
                'modo_accion'               => 'Ingestión estricta. Las toxinas Cry provocan la lisis celular del epitelio digestivo de las orugas en 24-48h.',
                'plaga_o_enfermedad_objetivo'=> 'Orugas, Polilla del racimo (Lobesia botrana), Procesionaria, Tuta y Barrenadores',
                'dosis_recomendada_ha'      => 1.000,
                'unidad_dosis'              => 'kg/ha',
                'plazo_seguridad_dias'      => 1,
                'observaciones_tecnicas'    => 'Apto para Agricultura Ecológica. Inocuo para personas, abejas y fauna auxiliar. Tratar en primeros estadios larvarios (L1-L2) con pH del caldo neutro o ligeramente ácido (pH 6-7).'
            ),
            'jabon potasico' => array(
                'nombre_comercial_producto' => 'Jabón Potásico Agrícola (Oleato Potásico)',
                'materia_activa'            => 'Sales potásicas de ácidos grasos vegetales 50% [SL]',
                'num_registro_oficial'      => 'Sustancia Básica Reg. CE 1107/2009',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Insecticida biológico de contacto y limpiador de melazas',
                'modo_accion'               => 'Contacto mecánico. Reblandece y disuelve el exoesqueleto de quitina cerosa de pulgones y mosca blanca.',
                'plaga_o_enfermedad_objetivo'=> 'Pulgón verde y negro, Mosca blanca, Trips, Cochinillas y hongo Negrilla',
                'dosis_recomendada_ha'      => 5.000,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 0,
                'observaciones_tecnicas'    => 'Apto 100% en Agricultura Ecológica. Plazo de seguridad 0 días. Excelente mojante natural. No aplicar con aguas excesivamente duras (calizas) sin acondicionador previo.'
            ),
            'aceite de parafina' => array(
                'nombre_comercial_producto' => 'Insectoil / Aceite Blanco de Parafina 79%',
                'materia_activa'            => 'Aceite de parafina 79% [EC] p/v',
                'num_registro_oficial'      => '19.458',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Insecticida / Acaricida físico asfixiante',
                'modo_accion'               => 'Acción física por asfixia. Cubre los espiráculos respiratorios de insectos y cáscaras de huevos.',
                'plaga_o_enfermedad_objetivo'=> 'Piojo rojo de California, Cochinillas, Ácaros y formas invernantes de pulgón',
                'dosis_recomendada_ha'      => 8.000,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 0,
                'observaciones_tecnicas'    => 'Apto para Agricultura Ecológica. Mojar profusamente ramas y troncos. No aplicar con estrés hídrico ni en floración.'
            ),
            'neem' => array(
                'nombre_comercial_producto' => 'Oikos / NeemAzal T/S',
                'materia_activa'            => 'Azadiractina 1% [EC] p/v (Extracto de semillas de Neem)',
                'num_registro_oficial'      => '25.223',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Bioinsecticida regulador del crecimiento y repelente',
                'modo_accion'               => 'Ingestión y contacto. Inhibe la síntesis de ecdisona impidiendo la muda larvaria y la ovoposición.',
                'plaga_o_enfermedad_objetivo'=> 'Mosca blanca, Pulgón, Trips, Minador de hojas y Orugas',
                'dosis_recomendada_ha'      => 1.500,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 3,
                'observaciones_tecnicas'    => 'Certificado para Agricultura Ecológica. Efecto sistémico translaminar. Respetuoso con abejas si se aplica fuera de horas de pecoreo.'
            ),
            'karate' => array(
                'nombre_comercial_producto' => 'Karate Zeon +',
                'materia_activa'            => 'Lambda-cihalotrin 10% [CS] p/v (Microencapsulado)',
                'num_registro_oficial'      => '23.957',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Insecticida piretroide sintético microencapsulado',
                'modo_accion'               => 'Choque fulminante y repelencia por contacto e ingestión (canal de sodio).',
                'plaga_o_enfermedad_objetivo'=> 'Pulgones, Orugas, Mosca de la fruta, Trips y Escarabajos',
                'dosis_recomendada_ha'      => 0.200,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 7,
                'observaciones_tecnicas'    => 'Químico convencional (prohibido en ecológico). Muy alta eficacia a dosis reducidas. No aplicar en presencia de abejas en floración.'
            ),
            'decis' => array(
                'nombre_comercial_producto' => 'Decis Protech',
                'materia_activa'            => 'Deltametrina 1.5% [EW] p/v',
                'num_registro_oficial'      => '23.545',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Insecticida piretroide de amplio espectro',
                'modo_accion'               => 'Contacto e ingestión con rápido derribo (knock-down).',
                'plaga_o_enfermedad_objetivo'=> 'Mosca del olivo (Bactrocera oleae), Mosca de la fruta, Pulgones y Gusanos',
                'dosis_recomendada_ha'      => 0.600,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 7,
                'observaciones_tecnicas'    => 'Convencional. Especialmente utilizado en pulverización cebo o parcheo para mosca del olivo y fruta.'
            ),
            'movento' => array(
                'nombre_comercial_producto' => 'Movento 150 O-TEQ',
                'materia_activa'            => 'Spirotetramat 15% [OD] p/v',
                'num_registro_oficial'      => '25.568',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Insecticida sistémico bidireccional de última generación',
                'modo_accion'               => 'Inhibidor de la acetil-CoA carboxilasa (síntesis de lípidos). Sistemia doble ascendente y descendente.',
                'plaga_o_enfermedad_objetivo'=> 'Cochinillas, Piojo rojo de California, Pulgón verde y ceniciento, Psila y Mosca blanca',
                'dosis_recomendada_ha'      => 0.650,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 21,
                'observaciones_tecnicas'    => 'Convencional. Extraordinaria eficacia en plagas chupadoras ocultas en el interior de la madera o raíces gracias a su sistemia bidireccional.'
            ),
            'teppeki' => array(
                'nombre_comercial_producto' => 'Teppeki',
                'materia_activa'            => 'Flonicamid 50% [WG] p/p',
                'num_registro_oficial'      => '24.449',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Aficida / Insecticida sistémico altamente específico',
                'modo_accion'               => 'Bloqueador selectivo de la alimentación de insectos picadores-chupadores en menos de 1 hora.',
                'plaga_o_enfermedad_objetivo'=> 'Pulgones (Myzus persicae, Aphis pomi, etc.) y Mosca blanca',
                'dosis_recomendada_ha'      => 0.140,
                'unidad_dosis'              => 'kg/ha',
                'plazo_seguridad_dias'      => 14,
                'observaciones_tecnicas'    => 'Convencional. Máxima selectividad hacia insectos polinizadores (abejas) y fauna útil auxiliar (crisopas, mariquitas).'
            ),

            // --- HERBICIDAS ---
            'roundup' => array(
                'nombre_comercial_producto' => 'Roundup Ultra / Glifosato 36%',
                'materia_activa'            => 'Glifosato 36% [SL] p/v (Sal potásica / isopropilamina)',
                'num_registro_oficial'      => '24.890',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Herbicida total sistémico no selectivo post-emergencia',
                'modo_accion'               => 'Inhibidor de la enzima EPSP sintasa. Sistemia a través de hojas verdes hasta rizomas y raíces.',
                'plaga_o_enfermedad_objetivo'=> 'Malas hierbas anuales y vivaces (gramíneas, correhuela, juncia, cardos)',
                'dosis_recomendada_ha'      => 4.000,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 7,
                'observaciones_tecnicas'    => 'Convencional (Terminantemente prohibido en parcelas ecológicas). Aplicar dirigido a la base o ruedas sin tocar hojas ni brotes verdes del cultivo leñoso.'
            ),
            'mcpa' => array(
                'nombre_comercial_producto' => 'MCPA 50% SL',
                'materia_activa'            => 'MCPA 50% [SL] p/v (Sal dimetilamina)',
                'num_registro_oficial'      => '18.230',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Herbicida hormonal selectivo para hoja ancha',
                'modo_accion'               => 'Regulador de crecimiento auxínico. Absorción foliar y traslocación.',
                'plaga_o_enfermedad_objetivo'=> 'Malas hierbas dicotiledóneas (hoja ancha) en cereales y ruedos de olivar/frutales adultos',
                'dosis_recomendada_ha'      => 1.500,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 15,
                'observaciones_tecnicas'    => 'Convencional. Tratar con temperaturas suaves y sin viento para evitar deriva hacia cultivos sensibles (viña, almendro).'
            ),

            // --- ABONOS FOLIARES Y AMINOÁCIDOS ---
            'aminoacidos' => array(
                'nombre_comercial_producto' => 'Bioestimulante Aminoácidos Libres 24%',
                'materia_activa'            => 'Aminoácidos libres de hidrólisis enzimática 24% + Nitrógeno orgánico 5%',
                'num_registro_oficial'      => 'Abono CE Clase A',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Bioestimulante vegetal y antiestrés térmico/hídrico',
                'modo_accion'               => 'Absorción directa y ahorro energético en síntesis proteica durante brotación y cuajado.',
                'plaga_o_enfermedad_objetivo'=> 'Estrés por sequía, heladas, parada vegetativa y fomento del engorde de fruto',
                'dosis_recomendada_ha'      => 2.000,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 0,
                'observaciones_tecnicas'    => 'Apto para Agricultura Ecológica. Potencia y facilita la asimilación de otros productos aplicados conjuntamente en el caldo.'
            ),
            'calcio foliar' => array(
                'nombre_comercial_producto' => 'Calcio Foliar Complejado con Lignosulfonato',
                'materia_activa'            => 'Óxido de Calcio (CaO) soluble 15% p/v complejado',
                'num_registro_oficial'      => 'Fertilizante Foliar CE',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Corrector de carencias y endurecedor de pared celular',
                'modo_accion'               => 'Aporte de calcio estructural para firmeza de fruto y resistencia a rajado.',
                'plaga_o_enfermedad_objetivo'=> 'Necrosis apical (peseta en tomate/pimiento), Bitter pit en manzano y rajado de fruto',
                'dosis_recomendada_ha'      => 3.000,
                'unidad_dosis'              => 'l/ha',
                'plazo_seguridad_dias'      => 0,
                'observaciones_tecnicas'    => 'Apto para Agricultura Ecológica. Aplicar desde inicio de engorde del fruto hasta 15 días antes de cosecha.'
            ),

            // --- TRAMPAS, FEROMONAS Y ATRAYENTES PARA FRUTALES ---
            'trampa mosca fruta' => array(
                'nombre_comercial_producto' => 'Trampa Conetrap Ceratitis / Magnet MED',
                'materia_activa'            => 'Atrayente alimenticio específico (Acetato de amonio + Clorhidrato de trimetilamina) + Deltametrina (placa insecticida interior)',
                'num_registro_oficial'      => 'ES-00045 (Homologado Trampeo Masivo MAPA)',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Trampa / Feromona / Atrayente',
                'modo_accion'               => 'Atracción y muerte (Attract & Kill). Difusión continua de atrayentes alimenticios que atraen hembras y machos hacia la trampa.',
                'plaga_o_enfermedad_objetivo'=> 'Mosca de la fruta (Ceratitis capitata) en melocotonero, albaricoquero, ciruelo, peral, manzano, higuera y cítricos',
                'dosis_recomendada_ha'      => 50.000,
                'unidad_dosis'              => 'trampas/ha',
                'dosis_etiqueta_100l'       => 0,
                'dosis_calculada_mochila'   => 0,
                'dosis_calculada_pulv'      => 0,
                'dosis_calculada_cuba'      => 0,
                'plazo_seguridad_dias'      => 0,
                'intervalo_repeticion_dias' => 120,
                'observaciones_tecnicas'    => 'Modo de empleo y colocación: 1) Densidad: 45 a 75 trampas/ha para captura masiva (o 2 a 4 trampas/ha para monitoreo de vuelo). 2) Ubicación: Colgar a media altura del árbol (1,5 a 2 m), en la cara SUR / SURESTE dentro de la copa a la sombra. 3) Momento: Instalar 40-50 días antes del inicio de maduración del fruto (cambio de color) y mantener hasta finalizar cosecha. Duración efectiva: 100-120 días.'
            ),
            'trampa mosca olivo' => array(
                'nombre_comercial_producto' => 'Mosquero Olipe / Conetrap Dacus (Mosca del olivo)',
                'materia_activa'            => 'Fosfato diamónico 4% + Feromona sexual (1,7-dioxaspiro[5.5]undecano)',
                'num_registro_oficial'      => 'BioTrampa MAPA (Producción Ecológica)',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Trampa / Feromona / Atrayente',
                'modo_accion'               => 'Captura masiva y trampeo por atracción alimenticia y feromona sexual específica.',
                'plaga_o_enfermedad_objetivo'=> 'Mosca del olivo (Bactrocera oleae / Dacus oleae)',
                'dosis_recomendada_ha'      => 50.000,
                'unidad_dosis'              => 'trampas/ha',
                'dosis_etiqueta_100l'       => 0,
                'dosis_calculada_mochila'   => 0,
                'dosis_calculada_pulv'      => 0,
                'dosis_calculada_cuba'      => 0,
                'plazo_seguridad_dias'      => 0,
                'intervalo_repeticion_dias' => 90,
                'observaciones_tecnicas'    => 'Modo de empleo y colocación: Instalar a principios de verano (junio-julio), cuando el hueso de la aceituna empieza a endurecer (tamaño > 8-10 mm). Densidad recomendada: 40 a 60 mosqueros/ha en masa arbórea o 1 mosquero por cada 2-3 olivos. Colocar en la cara sur de la copa resguardado del sol cenital. Rellenar o inspeccionar mensualmente.'
            ),
            'proteina hidrolizada' => array(
                'nombre_comercial_producto' => 'Proteína Hidrolizada Cebo (Buminal / CeraTrap)',
                'materia_activa'            => 'Proteína hidrolizada líquida enriquecida 30% [SL]',
                'num_registro_oficial'      => 'Atrayente cebo registrado MAPA',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Trampa / Feromona / Atrayente',
                'modo_accion'               => 'Atracción trófica y alimentaria intensa de dípteros adultos (moscas de fruta y olivo).',
                'plaga_o_enfermedad_objetivo'=> 'Mosca de la fruta (Ceratitis capitata) y Mosca del olivo (Bactrocera oleae)',
                'dosis_recomendada_ha'      => 1.000,
                'unidad_dosis'              => 'l/ha',
                'dosis_etiqueta_100l'       => 1.000,
                'plazo_seguridad_dias'      => 0,
                'intervalo_repeticion_dias' => 14,
                'observaciones_tecnicas'    => 'Modo de empleo: 1) En trampas mosqueros: verter entre 250 y 400 ml de producto por trampa cada 20-30 días. 2) En pulverización cebo con insecticida autorizado (Spintor / Deltametrina): mezclar al 1-2% y pulverizar a bandas o parches tratando sólo 1 o 2 m² de la cara sur de cada árbol con gota gruesa.'
            ),

            // --- HORMONAS Y FITORREGULADORES PARA FRUTALES ---
            'acido giberelico' => array(
                'nombre_comercial_producto' => 'Ácido Giberélico GA3 (Berelex / Gibb Plus)',
                'materia_activa'            => 'Ácido giberélico (GA3) 40% [SG] p/p (Giberelina vegetal)',
                'num_registro_oficial'      => '18.490 (Fitorregulador Homologado MAPA)',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Fitorregulador / Hormona vegetal',
                'modo_accion'               => 'Hormona vegetal de crecimiento. Estimula la división y elongación celular, favorece el cuajado partenocárpico y retrasa el envejecimiento de la piel/corteza.',
                'plaga_o_enfermedad_objetivo'=> 'Mejora del cuajado de fruto, prevención del rajado (cracking) y retención de fruta en hueso, pepita, cítricos y vid',
                'dosis_recomendada_ha'      => 0.050,
                'unidad_dosis'              => 'kg/ha',
                'dosis_etiqueta_100l'       => 0.005,
                'plazo_seguridad_dias'      => 7,
                'intervalo_repeticion_dias' => 14,
                'observaciones_tecnicas'    => 'Modo de empleo: Aplicar en pulverización foliar fina mojando homogéneamente las flores y frutitos. Momento clave: desde plena floración (70-80% caída de pétalos) hasta el cuajado visible. Temperatura óptima de aplicación: entre 15ºC y 25ºC (nunca con calor excesivo). Mantener el caldo a pH ligeramente ácido (5.5 - 6.5).'
            ),
            'acido naftilacetico' => array(
                'nombre_comercial_producto' => 'Ácido 1-Naftilacético ANA 1% (Fruitone / Tre-Hold)',
                'materia_activa'            => 'Ácido 1-naftilacético (ANA / NAA) 1% [SL] p/v (Auxina sintética)',
                'num_registro_oficial'      => '16.732 (Fitorregulador Auxínico)',
                'es_ecologico'              => 0,
                'tipo_producto'             => 'Fitorregulador / Hormona vegetal',
                'modo_accion'               => 'Regulador de auxinas. A dosis baja previene la formación de la capa de abscisión (evita la caída prematura de frutos); a dosis de aclareo promueve la caída de frutos débiles.',
                'plaga_o_enfermedad_objetivo'=> 'Anticaída pre-cosecha de frutos y aclareo químico en manzano, peral y frutales de hueso',
                'dosis_recomendada_ha'      => 0.400,
                'unidad_dosis'              => 'l/ha',
                'dosis_etiqueta_100l'       => 0.040,
                'plazo_seguridad_dias'      => 14,
                'intervalo_repeticion_dias' => 21,
                'observaciones_tecnicas'    => 'Modo de empleo: 1) Para evitar la caída pre-cosecha: tratar 15 a 20 días antes de la fecha estimada de recolección. 2) Para aclareo químico: aplicar cuando el fruto central del corimbo alcance entre 10 y 12 mm de diámetro. Aplicar con abundante agua (800-1.000 L/ha) mojando el interior de la copa.'
            ),
            'citoquininas' => array(
                'nombre_comercial_producto' => 'Bioestimulante Citoquininas y Auxinas Naturales (Kelpak / Cytoplant)',
                'materia_activa'            => 'Citoquininas naturales 100 mg/L + Auxinas naturales de extracto de Ecklonia maxima',
                'num_registro_oficial'      => 'Bioestimulante Fisiológico CE',
                'es_ecologico'              => 1,
                'tipo_producto'             => 'Fitorregulador / Hormona vegetal',
                'modo_accion'               => 'Hormona vegetal de división celular. Acelera el número de células en la etapa post-cuajado aumentando el calibre final y consistencia del fruto.',
                'plaga_o_enfermedad_objetivo'=> 'Aumento de calibre de fruto, división celular post-fecundación, uniformidad de cosecha y superación de estrés térmico',
                'dosis_recomendada_ha'      => 2.000,
                'unidad_dosis'              => 'l/ha',
                'dosis_etiqueta_100l'       => 0.200,
                'plazo_seguridad_dias'      => 0,
                'intervalo_repeticion_dias' => 15,
                'observaciones_tecnicas'    => 'Apto para Agricultura Ecológica. Modo de empleo: Realizar 2 a 3 pases foliares: 1º al 80% de caída de pétalos, 2º a la caída fisiológica de frutos (tamaño avellana), 3º al inicio del engorde activo del fruto.'
            )
        );
    }

    /**
     * Normaliza los datos de un producto fitosanitario para garantizar consistencia y cálculo de dosis
     */
    public static function normalizeProductData(array $p) {
        $nom = isset($p['nombre_comercial_producto']) ? $p['nombre_comercial_producto'] : (isset($p['nombre_comercial']) ? $p['nombre_comercial'] : '');
        $mat = isset($p['materia_activa']) ? $p['materia_activa'] : '';
        $reg = isset($p['num_registro_oficial']) ? $p['num_registro_oficial'] : '';
        $tipo = isset($p['tipo_producto']) ? $p['tipo_producto'] : 'Fungicida';
        $eco = !empty($p['es_ecologico']) ? 1 : 0;
        $cult = isset($p['cultivos_autorizados']) ? $p['cultivos_autorizados'] : 'Olivo, Almendro, Frutales, Cítricos, Tomate, Vid';
        $plagas = isset($p['plagas_objetivo']) ? $p['plagas_objetivo'] : (isset($p['plaga_o_enfermedad_objetivo']) ? $p['plaga_o_enfermedad_objetivo'] : '');
        $modo = isset($p['modo_accion_uso']) ? $p['modo_accion_uso'] : (isset($p['modo_accion']) ? $p['modo_accion'] : '');
        $unid = isset($p['unidad_dosis']) ? trim($p['unidad_dosis']) : 'kg/hl';
        
        $d100 = isset($p['dosis_etiqueta_100l']) ? (float)$p['dosis_etiqueta_100l'] : 0.0;
        $dha = isset($p['dosis_por_hectarea']) ? (float)$p['dosis_por_hectarea'] : (isset($p['dosis_recomendada_ha']) ? (float)$p['dosis_recomendada_ha'] : 0.0);
        
        // Si no se proporcionó dosis por 100L pero sí por hectárea, inferir d100 (asumiendo 1.000L caldo/ha)
        if ($d100 <= 0 && $dha > 0) {
            $d100 = ($dha * 1000.0) / 10.0; // g/hl
            $unid = 'g/hl';
        }

        // Determinar si la unidad de dosis es mayor (kg, L, %) o pequeña (g, cc, ml)
        $uLower = mb_strtolower($unid, 'UTF-8');
        $esPequena = (strpos($uLower, 'g/hl') !== false || strpos($uLower, 'cc/hl') !== false || strpos($uLower, 'ml') !== false || strpos($uLower, 'g/100') !== false);
        $esMayor = (strpos($uLower, 'kg') !== false || strpos($uLower, 'l/hl') !== false || strpos($uLower, '%') !== false || $uLower === 'l');
        
        if ($esPequena) {
            $gPor100 = $d100;
        } elseif ($esMayor) {
            $gPor100 = $d100 * 1000.0;
        } else {
            $gPor100 = ($d100 > 0 && $d100 < 10.0) ? ($d100 * 1000.0) : $d100;
        }

        $dMoch = isset($p['dosis_calculada_mochila']) ? (float)$p['dosis_calculada_mochila'] : 0.0;
        $dPulv = isset($p['dosis_calculada_pulv']) ? (float)$p['dosis_calculada_pulv'] : 0.0;
        $dCuba = isset($p['dosis_calculada_cuba']) ? (float)$p['dosis_calculada_cuba'] : 0.0;

        // Mochila 16L en g o ml
        if ($dMoch <= 0 && $gPor100 > 0) {
            $dMoch = round($gPor100 * 0.16, 1);
        } elseif ($dMoch > 0 && $dMoch < 1.0) {
            $dMoch = round($dMoch * 1000.0, 1);
        }

        // Pulverizador 100L en g o ml
        if ($dPulv <= 0 && $gPor100 > 0) {
            $dPulv = round($gPor100, 0);
        } elseif ($dPulv > 0 && $dPulv < 1.0) {
            $dPulv = round($dPulv * 1000.0, 0);
        }

        // Cuba 1.000L en kg o L (debe ser el total para 1.000L, ej: 3.00 kg)
        if ($dCuba >= 50.0) {
            $dCuba = round($dCuba / 1000.0, 2);
        } elseif ($dCuba <= 0 && $gPor100 > 0) {
            $dCuba = round(($gPor100 * 10.0) / 1000.0, 2);
        }

        if ($dha <= 0 && $dCuba > 0) {
            $dha = $dCuba;
        }

        $pseg = isset($p['plazo_seguridad_dias']) ? (int)$p['plazo_seguridad_dias'] : ($eco ? 0 : 14);
        $intv = isset($p['intervalo_repeticion_dias']) ? (int)$p['intervalo_repeticion_dias'] : 14;
        $obs = isset($p['descripcion_advertencias']) ? $p['descripcion_advertencias'] : (isset($p['observaciones_tecnicas']) ? $p['observaciones_tecnicas'] : '');
        $fuente = isset($p['fuente']) ? $p['fuente'] : 'Registro Oficial de Productos Fitosanitarios';

        return array(
            'id_producto'                 => isset($p['id_producto']) ? (int)$p['id_producto'] : null,
            'nombre_comercial_producto'   => $nom,
            'materia_activa'              => $mat,
            'num_registro_oficial'        => $reg,
            'tipo_producto'               => $tipo,
            'es_ecologico'                => $eco,
            'cultivos_autorizados'        => $cult,
            'plagas_objetivo'             => $plagas,
            'plaga_o_enfermedad_objetivo' => $plagas,
            'modo_accion'                 => $modo,
            'dosis_etiqueta_100l'         => $d100,
            'unidad_dosis'                => $unid,
            'dosis_recomendada_ha'        => $dha,
            'plazo_seguridad_dias'        => $pseg,
            'intervalo_repeticion_dias'   => $intv,
            'dosis_calculada_mochila'     => $dMoch,
            'dosis_calculada_pulv'        => $dPulv,
            'dosis_calculada_cuba'        => $dCuba,
            'observaciones_tecnicas'      => $obs,
            'fuente'                      => $fuente,
            'url_ficha_tecnica'           => isset($p['url_ficha_tecnica']) ? $p['url_ficha_tecnica'] : '',
            'es_mas_recomendable'         => !empty($p['es_mas_recomendable']),
            'motivo_recomendacion'        => isset($p['motivo_recomendacion']) ? $p['motivo_recomendacion'] : ''
        );
    }

    /**
     * Elimina tildes y diacríticos para búsquedas semánticas insensibles a acentos
     */
    private static function cleanAccents($str) {
        $str = (string)$str;
        $unwanted = array(
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C',
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
            'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e',
            'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o',
            'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
        );
        return strtr($str, $unwanted);
    }

    /**
     * Búsqueda en catálogo base local con correspondencia difusa devolviendo múltiples coincidencias
     */
    public static function searchInLocalCatalogMultiple($query, $forceEco = null) {
        $rawQ = trim($query);
        if (empty($rawQ)) {
            return array();
        }

        $q = mb_strtolower(self::cleanAccents($rawQ), 'UTF-8');
        $catalog = self::getBaseCatalog();
        $matches = array();

        // Expansión semántica básica de términos agronómicos
        $synonyms = array();
        if (strpos($q, 'cobre') !== false) {
            $synonyms = array('cuproso', 'cuprocol', 'cuproc', 'cobre');
        } elseif (strpos($q, 'hormona') !== false || strpos($q, 'regulador') !== false) {
            $synonyms = array('hormona', 'fitorregulador', 'giberel', 'auxin', 'citoquin', 'naftil');
        } elseif (strpos($q, 'trampa') !== false || strpos($q, 'feromona') !== false || strpos($q, 'mosquero') !== false) {
            $synonyms = array('trampa', 'conetrap', 'olipe', 'feromona', 'atrayente');
        } elseif (strpos($q, 'mosca') !== false) {
            $synonyms = array('mosca', 'ceratitis', 'bactrocera', 'dacus', 'conetrap', 'olipe');
        }

        // Extracción de palabras clave significativas ignorando palabras de parada
        $stopWords = array('curar', 'tratar', 'tratamiento', 'contra', 'para', 'de', 'del', 'en', 'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'con', 'sin', 'que', 'como', 'eliminar', 'quitar');
        $rawWords = preg_split('/\s+/', $q);
        $significantWords = array();
        foreach ($rawWords as $w) {
            $w = trim($w);
            if (mb_strlen($w, 'UTF-8') >= 3 && !in_array($w, $stopWords)) {
                $significantWords[] = $w;
            }
        }

        foreach ($catalog as $key => $item) {
            if ($forceEco === true && empty($item['es_ecologico'])) {
                continue;
            }

            $keyClean = mb_strtolower(self::cleanAccents($key), 'UTF-8');
            $nom = mb_strtolower(self::cleanAccents($item['nombre_comercial_producto']), 'UTF-8');
            $mat = mb_strtolower(self::cleanAccents($item['materia_activa']), 'UTF-8');
            $pla = mb_strtolower(self::cleanAccents($item['plaga_o_enfermedad_objetivo']), 'UTF-8');
            $fun = mb_strtolower(self::cleanAccents($item['tipo_producto']), 'UTF-8');
            $obs = mb_strtolower(self::cleanAccents($item['observaciones_tecnicas']), 'UTF-8');

            $score = 0;

            if ($keyClean === $q) $score += 100;
            if (strpos($keyClean, $q) !== false) $score += 80;
            if (strpos($nom, $q) !== false) $score += 80;
            if (strpos($pla, $q) !== false) $score += 70;
            if (strpos($mat, $q) !== false) $score += 60;
            if (strpos($fun, $q) !== false) $score += 50;

            if (!empty($synonyms)) {
                foreach ($synonyms as $syn) {
                    if (strpos($nom, $syn) !== false) $score += 45;
                    if (strpos($pla, $syn) !== false) $score += 40;
                    if (strpos($fun, $syn) !== false) $score += 35;
                    if (strpos($mat, $syn) !== false) $score += 30;
                }
            }

            // Coincidencia por palabras clave si la consulta es una frase (ej: "curar repilo", "tratar pulgon en higuera")
            if (!empty($significantWords)) {
                foreach ($significantWords as $word) {
                    if (strpos($nom, $word) !== false) $score += 40;
                    if (strpos($pla, $word) !== false) $score += 35;
                    if (strpos($mat, $word) !== false) $score += 25;
                    if (strpos($fun, $word) !== false) $score += 20;
                    if (strpos($obs, $word) !== false) $score += 5;
                }
            }

            if ($score > 0) {
                $item['_relevance_score'] = $score;
                $matches[] = $item;
            }
        }

        // Ordenar por relevancia descendente
        usort($matches, array('PhytosanitaryAiService', 'sortByScoreDesc'));

        return $matches;
    }

    /**
     * Función auxiliar para ordenar por relevancia en PHP 5.6
     */
    public static function sortByScoreDesc($a, $b) {
        $sa = isset($a['_relevance_score']) ? (int)$a['_relevance_score'] : 0;
        $sb = isset($b['_relevance_score']) ? (int)$b['_relevance_score'] : 0;
        if ($sa == $sb) {
            return 0;
        }
        return ($sa > $sb) ? -1 : 1;
    }

    /**
     * Búsqueda simple de 1 elemento en catálogo local (mantenida por compatibilidad)
     */
    public static function searchInLocalCatalog($query) {
        $multi = self::searchInLocalCatalogMultiple($query);
        return !empty($multi) ? $multi[0] : null;
    }

    /**
     * Devuelve una lista de sugerencias o productos recomendados para búsqueda rápida
     */
    public static function getSuggestedProducts() {
        return array(
            array('nombre' => 'Nordox 75 WG', 'categoria' => 'Fungicida Cúprico (Bio)', 'eco' => 1),
            array('nombre' => 'Trampas Mosca Fruta (Conetrap)', 'categoria' => 'Trampeo Masivo Ceratitis', 'eco' => 1),
            array('nombre' => 'Ácido Giberélico GA3', 'categoria' => 'Hormona / Cuajado Frutal', 'eco' => 0),
            array('nombre' => 'Spintor 480 SC', 'categoria' => 'Insecticida Orugas/Trips (Bio)', 'eco' => 1),
            array('nombre' => 'Bacillus thuringiensis', 'categoria' => 'Bioinsecticida Orugas', 'eco' => 1),
            array('nombre' => 'Mosquero Mosca Olivo (Olipe)', 'categoria' => 'Trampa Masiva Dacus', 'eco' => 1),
            array('nombre' => 'Citoquininas y Auxinas', 'categoria' => 'Hormona / Engorde Fruto (Bio)', 'eco' => 1),
            array('nombre' => 'Kumulus DF (Azufre)', 'categoria' => 'Fungicida / Antioídio (Bio)', 'eco' => 1),
            array('nombre' => 'Jabón Potásico', 'categoria' => 'Insecticida Pulgón/Mosca (Bio)', 'eco' => 1),
            array('nombre' => 'Cuprocol', 'categoria' => 'Cobre Líquido Repilo (Bio)', 'eco' => 1),
            array('nombre' => 'Ácido Naftilacético (ANA)', 'categoria' => 'Hormona / Anticaída Pre-Cosecha', 'eco' => 0),
            array('nombre' => 'Score 25 EC', 'categoria' => 'Fungicida Sistémico Curativo', 'eco' => 0),
            array('nombre' => 'Karate Zeon', 'categoria' => 'Insecticida de Choque', 'eco' => 0),
            array('nombre' => 'Movento 150', 'categoria' => 'Sistémico Cochinillas/Pulgón', 'eco' => 0),
            array('nombre' => 'Roundup Ultra', 'categoria' => 'Herbicida Ruedos/Líneas', 'eco' => 0),
            array('nombre' => 'Aliette WG', 'categoria' => 'Fungicida Antifitóftora', 'eco' => 0),
            array('nombre' => 'Bioestimulante Aminoácidos', 'categoria' => 'Nutrición / Antiestrés (Bio)', 'eco' => 1)
        );
    }

    /**
     * Consulta principal: Resuelve y busca productos fitosanitarios mediante IA y registros oficiales.
     * Si no encuentra nada, informa claramente al usuario sin inventar datos falsos.
     * Si encuentra opciones, devuelve la lista completa de opciones para que el usuario seleccione la más conveniente.
     *
     * @param string $query Nombre del producto comercial, materia activa o plaga
     * @param bool|null $forceEco Si se solicita forzar variante ecológica
     * @return array Resultado con lista de opciones homologadas o mensaje explicativo
     */
    public static function consultProduct($query, $forceEco = null) {
        $query = trim($query);
        if (empty($query)) {
            return array(
                'success'  => false,
                'total'    => 0,
                'opciones' => array(),
                'message'  => 'Por favor, introduce el nombre del producto, materia activa o plaga a consultar.'
            );
        }

        $opciones = array();
        $keysSeen = array();

        // 1. Buscar en la tabla oficial de productos_fitosanitarios en Base de Datos
        if (class_exists('Database')) {
            try {
                $db = Database::getConnection();
                if ($db) {
                    $sql = "
                        SELECT * FROM productos_fitosanitarios 
                        WHERE activo = 1 
                          AND (
                              nombre_comercial LIKE :q1 
                              OR materia_activa LIKE :q2 
                              OR plagas_objetivo LIKE :q3 
                              OR cultivos_autorizados LIKE :q4
                              OR tipo_producto LIKE :q5
                          )
                    ";
                    if ($forceEco === true) {
                        $sql .= " AND es_ecologico = 1 ";
                    }
                    $sql .= " ORDER BY (nombre_comercial LIKE :q_exact) DESC, es_ecologico DESC LIMIT 8";
                    
                    $stmt = $db->prepare($sql);
                    $searchTerm = '%' . $query . '%';
                    $stmt->execute(array(
                        ':q1' => $searchTerm,
                        ':q2' => $searchTerm,
                        ':q3' => $searchTerm,
                        ':q4' => $searchTerm,
                        ':q5' => $searchTerm,
                        ':q_exact' => $query . '%'
                    ));
                    $rows = $stmt->fetchAll();
                    foreach ($rows as $row) {
                        $norm = self::normalizeProductData($row);
                        $norm['fuente'] = 'Registro Oficial de Productos Fitosanitarios (Base de Datos)';
                        $k = mb_strtolower($norm['nombre_comercial_producto'], 'UTF-8');
                        if (!isset($keysSeen[$k])) {
                            $keysSeen[$k] = true;
                            $opciones[] = $norm;
                        }
                    }
                }
            } catch (Exception $e) {}
        }

        // 2. Buscar coincidencias en el catálogo agronómico base de referencia
        $catalogMatches = self::searchInLocalCatalogMultiple($query, $forceEco);
        foreach ($catalogMatches as $item) {
            $norm = self::normalizeProductData($item);
            $norm['fuente'] = 'Registro Oficial Fitosanitario (AgroDietario Expert DB)';
            $k = mb_strtolower($norm['nombre_comercial_producto'], 'UTF-8');
            if (!isset($keysSeen[$k])) {
                $keysSeen[$k] = true;
                $opciones[] = $norm;
            }
        }

        // 3. Si no hay suficientes coincidencias locales y hay API Key de Gemini, consultar IA externa
        $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');
        if (empty($apiKey) && function_exists('env')) {
            $apiKey = env('GEMINI_API_KEY', '');
        }

        if (!empty($apiKey) && count($opciones) < 3) {
            $aiItems = self::queryGeminiApiMultiple($query, $apiKey, $forceEco);
            if (!empty($aiItems)) {
                foreach ($aiItems as $aiItem) {
                    $norm = self::normalizeProductData($aiItem);
                    $norm['fuente'] = 'Inteligencia Artificial Gemini (Análisis Oficial MAPA / UE)';
                    $k = mb_strtolower($norm['nombre_comercial_producto'], 'UTF-8');
                    if (!isset($keysSeen[$k])) {
                        $keysSeen[$k] = true;
                        $opciones[] = $norm;
                    }
                }
            }
        }

        // 4. Si NO se ha encontrado nada: decir claramente que no se encuentra, NO inventar datos
        if (empty($opciones)) {
            return array(
                'success'  => false,
                'total'    => 0,
                'opciones' => array(),
                'message'  => 'No se ha encontrado ningún producto fitosanitario registrado o información técnica oficial para "' . htmlspecialchars($query) . '". Comprueba la ortografía o introduce los datos manualmente en la ficha.'
            );
        }

        // 5. Determinar y destacar la opción más recomendable agronómicamente
        $hasRec = false;
        foreach ($opciones as $opt) {
            if (!empty($opt['es_mas_recomendable'])) {
                $hasRec = true;
                break;
            }
        }
        if (!$hasRec) {
            $opciones[0]['es_mas_recomendable'] = true;
            $esEco = (int)$opciones[0]['es_ecologico'] === 1;
            $opciones[0]['motivo_recomendacion'] = $esEco 
                ? '⭐ Opción más recomendada: Excelente eficacia agronómica contrastada, manejo ecológico certificado (UE) y sin residuos sintéticos en el fruto.'
                : '⭐ Opción más recomendada: Mayor potencia de control específico, rápido derribo curativo y amplio registro técnico oficial.';
        }

        return array(
            'success'  => true,
            'total'    => count($opciones),
            'opciones' => $opciones,
            'data'     => $opciones[0] // Compatibilidad con vistas que consumen res.data
        );
    }

    /**
     * Consulta técnica estructurada a la API de Google Gemini devolviendo múltiples opciones
     */
    private static function queryGeminiApiMultiple($query, $apiKey, $forceEco = null) {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

            $ecoInstruction = "";
            if ($forceEco === true) {
                $ecoInstruction = "IMPORTANTE: El usuario requiere exclusivamente productos APTO PARA AGRICULTURA ECOLÓGICA (Reglamento UE 2018/848). Si el producto buscado es convencional, proporciona las alternativas ecológicas homologadas equivalentes.";
            }

            $prompt = "Actúa como un Ingeniero Agrónomo experto en el Registro Oficial de Productos Fitosanitarios del Ministerio de Agricultura (MAPA) de España y la Unión Europea.\n" .
                      "El usuario busca información fitosanitaria con este término: '{$query}'.\n" .
                      $ecoInstruction . "\n" .
                      "AMPLITUD DE REGISTRO FITOSANITARIO AGRÍCOLA:\n" .
                      "- Considera como fitosanitarios y herramientas homologadas de control tanto fungicidas, insecticidas y herbicidas, como TRAMPAS, FEROMONAS Y ATRAYENTES PARA MOSCAS DE FRUTALES (Ceratitis capitata, Bactrocera oleae, Rhagoletis, trampeo masivo / atrayente y muerte) y HORMONAS VEGETALES / FITORREGULADORES (ácido giberélico GA3, auxinas, citoquininas, ANA para cuajado, engorde y aclareo).\n" .
                      "- Para trampas y hormonas, especifica obligatoriamente en 'observaciones_tecnicas' y 'modo_accion' su MODO DE EMPLEO DETALLADO: densidad de trampas/ha, colocación a media altura en la cara sur/sureste de la copa a la sombra, momento de instalación previo a la maduración y manejo adecuado.\n" .
                      "REGLA CRUCIAL DE VERACIDAD:\n" .
                      "1. Si el término introducido NO corresponde a ningún producto comercial fitosanitario real, ni trampa/hormona agrícola, ni materia activa registrada, ni plaga, hongo, enfermedad o cultivo real (o si son palabras sin sentido), responde ÚNICAMENTE con: {\"no_encontrado\": true}\n" .
                      "2. Si el término sí corresponde a un producto real, trampa, hormona, materia activa o plaga/enfermedad/cultivo, proporciona hasta 4 opciones comerciales u oficiales homologadas y convenientes para tratarlo en España/UE.\n" .
                      "REGLA DE RECOMENDACIÓN:\n" .
                      "- En UNA sola de las opciones (la más adecuada agronómicamente para lo que el usuario busca curar o tratar), marca obligatoriamente: \"es_mas_recomendable\": true, y en \"motivo_recomendacion\" explica en 1 frase concisa por qué es la opción más conveniente (ej: 'Mayor eficacia preventiva y persistencia tras lluvias, autorizado en ecológico'). En las demás opciones pon false y explica su rol como alternativa.\n" .
                      "Responde ÚNICAMENTE con un objeto JSON válido (sin bloques ```json ni texto adicional) con el siguiente formato:\n" .
                      "{\n" .
                      '  "no_encontrado": false,' . "\n" .
                      '  "productos": [' . "\n" .
                      '    {' . "\n" .
                      '      "nombre_comercial_producto": "Nombre comercial oficial en España",' . "\n" .
                      '      "materia_activa": "Materia activa o atrayente con % y formulación (ej: Óxido cuproso 75% [WG] o Acetato de amonio + Deltametrina)",' . "\n" .
                      '      "num_registro_oficial": "Nº registro oficial MAPA (ej: ES-00145 o 22.145)",' . "\n" .
                      '      "es_ecologico": 1 o 0 (1 si está autorizado en agricultura ecológica UE, 0 si es convencional),' . "\n" .
                      '      "es_mas_recomendable": true o false,' . "\n" .
                      '      "motivo_recomendacion": "Explicación breve de por qué es la opción más recomendable o alternativa",' . "\n" .
                      '      "tipo_producto": "Fungicida, Insecticida, Acaricida, Herbicida, Bioestimulante, Trampa / Feromona / Atrayente o Fitorregulador / Hormona",' . "\n" .
                      '      "cultivos_autorizados": "Cultivos autorizados (ej: Olivo, Frutales de hueso, Pepita, Cítricos, Vid, Tomate)",' . "\n" .
                      '      "plagas_objetivo": "Plagas, hongos, enfermedades o fin fisiológico que combate",' . "\n" .
                      '      "modo_accion": "Mecanismo de acción (ej: Trampeo masivo Attract & Kill, Contacto preventivo multisitio, Regulador de crecimiento)",' . "\n" .
                      '      "dosis_etiqueta_100l": número (ej: 0.200 si es kg/hl, 200 si es g/hl, o 0 si es trampa fija),' . "\n" .
                      '      "unidad_dosis": "kg/hl, l/hl, g/hl, cc/hl o trampas/ha",' . "\n" .
                      '      "dosis_recomendada_ha": número decimal por hectárea (ej: 2.000 o 50 para trampas/ha),' . "\n" .
                      '      "plazo_seguridad_dias": número entero de días (0 si exento o trampa),' . "\n" .
                      '      "intervalo_repeticion_dias": número entero de días,' . "\n" .
                      '      "observaciones_tecnicas": "Modo de empleo y colocación detallado, pautas de aplicación y precauciones"' . "\n" .
                      '    }' . "\n" .
                      '  ]' . "\n" .
                      "}";

            $payload = json_encode(array(
                'contents' => array(
                    array('parts' => array(array('text' => $prompt)))
                ),
                'generationConfig' => array(
                    'temperature' => 0.1,
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
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($response)) {
                $resJson = json_decode($response, true);
                if (isset($resJson['candidates'][0]['content']['parts'][0]['text'])) {
                    $rawText = trim($resJson['candidates'][0]['content']['parts'][0]['text']);
                    $rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
                    $rawText = preg_replace('/\s*```$/', '', $rawText);
                    $parsed = json_decode(trim($rawText), true);

                    if (is_array($parsed)) {
                        if (!empty($parsed['no_encontrado'])) {
                            return array();
                        }
                        if (isset($parsed['productos']) && is_array($parsed['productos'])) {
                            return $parsed['productos'];
                        }
                        if (isset($parsed['nombre_comercial_producto'])) {
                            return array($parsed);
                        }
                    }
                }
            }
        } catch (Exception $e) {}

        return array();
    }

    /**
     * Consulta simple para compatibilidad
     */
    private static function queryGeminiApi($query, $apiKey, $forceEco = null) {
        $items = self::queryGeminiApiMultiple($query, $apiKey, $forceEco);
        return !empty($items) ? $items[0] : null;
    }
}
