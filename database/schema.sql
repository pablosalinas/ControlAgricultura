-- ==============================================================================
-- SISTEMA DE GESTIÓN AGRÍCOLA Y CUADERNO DE CAMPO (AGRODIETARIO MULTI-PROPIETARIO)
-- Esquema de Base de Datos Relacional para MySQL / MariaDB (XAMPP y Producción IONOS)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS recolecciones;
DROP TABLE IF EXISTS riegos;
DROP TABLE IF EXISTS podas_talas;
DROP TABLE IF EXISTS labores_suelo;
DROP TABLE IF EXISTS tratamientos;
DROP TABLE IF EXISTS diario_evolucion;
DROP TABLE IF EXISTS plantaciones;
DROP TABLE IF EXISTS cultivos;
DROP TABLE IF EXISTS fincas;
DROP TABLE IF EXISTS parajes;
DROP TABLE IF EXISTS configuracion_web;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS propietarios;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. PROPIETARIOS (Multi-tenant: entidad principal de aislamiento)
-- ------------------------------------------------------------------------------
CREATE TABLE propietarios (
    id_propietario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_razon_social VARCHAR(150) NOT NULL,
    cif_nif VARCHAR(20) NULL,
    telefono VARCHAR(30) NULL,
    email VARCHAR(100) NULL,
    direccion VARCHAR(200) NULL,
    municipio VARCHAR(100) NULL,
    provincia VARCHAR(100) NULL,
    codigo_postal VARCHAR(10) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_propietario_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. USUARIOS (Superadmin, Propietarios, Técnicos, Lectores con flujo de aprobación)
-- ------------------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_propietario INT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) NULL,
    rol ENUM('superadmin', 'propietario', 'tecnico', 'lector') NOT NULL DEFAULT 'propietario',
    estado ENUM('pendiente', 'aprobado', 'rechazado', 'suspendido') NOT NULL DEFAULT 'pendiente',
    motivo_solicitud TEXT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME NULL,
    aprobado_por_id INT NULL,
    ultimo_acceso DATETIME NULL,
    FOREIGN KEY (id_propietario) REFERENCES propietarios(id_propietario) ON DELETE SET NULL,
    FOREIGN KEY (aprobado_por_id) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_usuario_estado (estado),
    INDEX idx_usuario_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. CONFIGURACIÓN DEL PORTAL WEB PÚBLICO (Escaparate configurable por Superadmin)
-- ------------------------------------------------------------------------------
CREATE TABLE configuracion_web (
    id_config INT AUTO_INCREMENT PRIMARY KEY,
    titulo_portal VARCHAR(150) NOT NULL DEFAULT 'AgroDietario - Gestión Agrícola',
    subtitulo_portal VARCHAR(255) NULL DEFAULT 'Control agronómico, trazabilidad de fincas y cuaderno de explotación digital',
    descripcion_bienvenida TEXT NULL,
    telefono_soporte VARCHAR(50) NULL,
    email_contacto VARCHAR(120) NULL,
    permitir_registro_publico TINYINT(1) NOT NULL DEFAULT 1,
    usar_captcha TINYINT(1) NOT NULL DEFAULT 1,
    mensaje_pendiente_aprobacion TEXT NULL,
    color_primario VARCHAR(20) DEFAULT '#2e7d32',
    actualizado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. PARAJES (Tabla maestra geográfica de parajes locales)
-- ------------------------------------------------------------------------------
CREATE TABLE parajes (
    id_paraje INT AUTO_INCREMENT PRIMARY KEY,
    id_propietario INT NULL,
    nombre_paraje VARCHAR(120) NOT NULL,
    termino_municipal VARCHAR(100) NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    FOREIGN KEY (id_propietario) REFERENCES propietarios(id_propietario) ON DELETE CASCADE,
    INDEX idx_paraje_municipio (termino_municipal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. FINCAS (Datos registrales, catastrales y derechos/tandas de riego)
-- ------------------------------------------------------------------------------
CREATE TABLE fincas (
    id_finca INT AUTO_INCREMENT PRIMARY KEY,
    id_propietario INT NOT NULL,
    id_paraje INT NULL,
    nombre_finca VARCHAR(150) NOT NULL,
    referencia_catastral VARCHAR(30) NULL,
    poligono VARCHAR(20) NULL,
    parcela VARCHAR(20) NULL,
    superficie_ha DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    zona_riego VARCHAR(100) NULL,
    parada VARCHAR(50) NULL,
    tablon VARCHAR(50) NULL,
    num_socio_comunidad VARCHAR(50) NULL,
    horas_propiedad_tanda DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
    horas_disponibles_tanda DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
    datos_registrales TEXT NULL,
    observaciones TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_propietario) REFERENCES propietarios(id_propietario) ON DELETE CASCADE,
    FOREIGN KEY (id_paraje) REFERENCES parajes(id_paraje) ON DELETE SET NULL,
    INDEX idx_finca_propietario (id_propietario),
    INDEX idx_finca_catastro (referencia_catastral)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. CULTIVOS (Tabla maestra botánica con enlaces a búsqueda botánica en internet)
-- ------------------------------------------------------------------------------
CREATE TABLE cultivos (
    id_cultivo INT AUTO_INCREMENT PRIMARY KEY,
    id_propietario INT NULL,
    nombre_comun VARCHAR(100) NOT NULL,
    nombre_cientifico VARCHAR(150) NULL,
    variedad VARCHAR(100) NULL,
    dias_ciclo_aprox INT NULL,
    tipo_cultivo ENUM('leñoso', 'horticola', 'cereal', 'frutal', 'citrico', 'olivar', 'vinedo', 'otro') DEFAULT 'frutal',
    url_consulta_botanica VARCHAR(255) NULL,
    requerimientos_tecnicos TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (id_propietario) REFERENCES propietarios(id_propietario) ON DELETE CASCADE,
    INDEX idx_cultivo_nombre (nombre_comun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. PLANTACIONES (Asociación Finca + Cultivo + Campaña / Temporada)
-- ------------------------------------------------------------------------------
CREATE TABLE plantaciones (
    id_plantacion INT AUTO_INCREMENT PRIMARY KEY,
    id_finca INT NOT NULL,
    id_cultivo INT NOT NULL,
    identificador_cuadro VARCHAR(80) NOT NULL DEFAULT 'Cuadro 1',
    campana_ano INT NOT NULL,
    superficie_ocupada_ha DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    num_arboles_o_plantas INT NULL,
    marco_plantacion VARCHAR(50) NULL,
    fecha_siembra DATE NULL,
    fecha_transplante DATE NULL,
    fecha_estimada_fin DATE NULL,
    fecha_arranque_o_fin DATE NULL,
    estado ENUM('en_crecimiento', 'produccion', 'en_descanso', 'arrancado') NOT NULL DEFAULT 'en_crecimiento',
    notas TEXT NULL,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_finca) REFERENCES fincas(id_finca) ON DELETE CASCADE,
    FOREIGN KEY (id_cultivo) REFERENCES cultivos(id_cultivo) ON DELETE RESTRICT,
    INDEX idx_plantacion_finca (id_finca),
    INDEX idx_plantacion_campana (campana_ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. DIARIO DE EVOLUCIÓN DEL CULTIVO (Seguimiento fenológico y fotográfico)
-- ------------------------------------------------------------------------------
CREATE TABLE diario_evolucion (
    id_diario INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacion INT NOT NULL,
    fecha DATE NOT NULL,
    estado_fenologico VARCHAR(100) NULL,
    temperatura_clima VARCHAR(80) NULL,
    observacion TEXT NOT NULL,
    foto_evidencia_url VARCHAR(255) NULL,
    creado_por_id INT NULL,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_plantacion) REFERENCES plantaciones(id_plantacion) ON DELETE CASCADE,
    FOREIGN KEY (creado_por_id) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_diario_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. TRATAMIENTOS FITOSANITARIOS Y ECOLÓGICOS (Convencional vs Ecológico certificado)
-- ------------------------------------------------------------------------------
CREATE TABLE tratamientos (
    id_tratamiento INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacion INT NOT NULL,
    fecha_tratamiento DATE NOT NULL,
    es_ecologico TINYINT(1) NOT NULL DEFAULT 0,
    nombre_comercial_producto VARCHAR(150) NOT NULL,
    materia_activa VARCHAR(150) NULL,
    num_registro_oficial VARCHAR(50) NULL,
    dosis_por_hectarea DECIMAL(8, 3) NOT NULL,
    unidad_dosis VARCHAR(20) NOT NULL DEFAULT 'l/ha',
    caldo_total_litros DECIMAL(8, 2) NULL,
    plazo_seguridad_dias INT NOT NULL DEFAULT 0,
    fecha_fin_plazo_seguridad DATE NULL,
    plaga_o_enfermedad_objetivo VARCHAR(150) NULL,
    justificacion_tecnica TEXT NULL,
    aplicador_responsable VARCHAR(120) NULL,
    observaciones TEXT NULL,
    creado_por_id INT NULL,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_plantacion) REFERENCES plantaciones(id_plantacion) ON DELETE CASCADE,
    FOREIGN KEY (creado_por_id) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_trat_fecha (fecha_tratamiento),
    INDEX idx_trat_eco (es_ecologico)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. LABORES DE SUELO (Arado, subsolado, grada, desbroce, eliminación malas hierbas)
-- ------------------------------------------------------------------------------
CREATE TABLE labores_suelo (
    id_labor INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacion INT NOT NULL,
    fecha_labor DATE NOT NULL,
    tipo_labor ENUM('arado', 'subsolado', 'fresado', 'grada', 'cultivador', 'desbroce', 'eliminacion_malas_hierbas', 'otro') NOT NULL,
    apero_maquinaria VARCHAR(120) NULL,
    horas_trabajo DECIMAL(6, 2) NULL,
    operario VARCHAR(100) NULL,
    observaciones TEXT NULL,
    creado_por_id INT NULL,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_plantacion) REFERENCES plantaciones(id_plantacion) ON DELETE CASCADE,
    FOREIGN KEY (creado_por_id) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_labor_fecha (fecha_labor),
    INDEX idx_labor_tipo (tipo_labor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 11. PODAS Y TALAS (Con gestión y trazabilidad del reciclaje/destino de restos)
-- ------------------------------------------------------------------------------
CREATE TABLE podas_talas (
    id_poda INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacion INT NOT NULL,
    fecha DATE NOT NULL,
    tipo_operacion ENUM('poda_formacion', 'poda_mantenimiento', 'poda_rejuvenecimiento', 'poda_verde', 'tala') NOT NULL,
    destino_residuos ENUM('triturado_acolchado', 'retirada_reciclaje_biomasa', 'quema_autorizada', 'compostaje', 'otro') NOT NULL,
    numero_permiso_quema VARCHAR(80) NULL,
    kilos_o_volumen_estimado DECIMAL(8, 2) NULL,
    observaciones TEXT NULL,
    creado_por_id INT NULL,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_plantacion) REFERENCES plantaciones(id_plantacion) ON DELETE CASCADE,
    FOREIGN KEY (creado_por_id) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_poda_fecha (fecha),
    INDEX idx_poda_operacion (tipo_operacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 12. RIEGOS (Horas, m3, tandas, parada, tablón y fertirrigación)
-- ------------------------------------------------------------------------------
CREATE TABLE riegos (
    id_riego INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacion INT NOT NULL,
    fecha_riego DATE NOT NULL,
    hora_inicio TIME NULL,
    hora_fin TIME NULL,
    duracion_horas DECIMAL(6, 2) NOT NULL,
    volumen_m3 DECIMAL(10, 2) NULL,
    parada_utilizada VARCHAR(50) NULL,
    tablon_utilizado VARCHAR(50) NULL,
    tipo_riego ENUM('goteo', 'inundacion_pie', 'aspersion', 'microaspersion') NOT NULL DEFAULT 'goteo',
    con_fertirrigacion TINYINT(1) NOT NULL DEFAULT 0,
    productos_abono TEXT NULL,
    observaciones TEXT NULL,
    creado_por_id INT NULL,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_plantacion) REFERENCES plantaciones(id_plantacion) ON DELETE CASCADE,
    FOREIGN KEY (creado_por_id) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_riego_fecha (fecha_riego)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 13. RECOLECCIONES (Cosecha, kilos, calidades, precios y liquidación)
-- ------------------------------------------------------------------------------
CREATE TABLE recolecciones (
    id_recoleccion INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacion INT NOT NULL,
    fecha_recoleccion DATE NOT NULL,
    producto_cosechado VARCHAR(120) NOT NULL,
    kilos_totales DECIMAL(10, 2) NOT NULL,
    calidad ENUM('primera', 'segunda', 'tercera', 'destrio', 'industria') NOT NULL DEFAULT 'primera',
    precio_kilo_estimado DECIMAL(8, 4) NULL,
    importe_total DECIMAL(12, 2) NULL,
    albaran_o_destino VARCHAR(150) NULL,
    observaciones TEXT NULL,
    creado_por_id INT NULL,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_plantacion) REFERENCES plantaciones(id_plantacion) ON DELETE CASCADE,
    FOREIGN KEY (creado_por_id) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_recol_fecha (fecha_recoleccion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- DATOS INICIALES (SEMILLAS)
-- ==============================================================================

-- 1. Propietario inicial
INSERT INTO propietarios (id_propietario, nombre_razon_social, cif_nif, telefono, email, activo)
VALUES (1, 'Explotación Agrícola Modelo', 'B12345678', '600000000', 'contacto@agrodietario.local', 1);

-- 2. Usuario Superadministrador por defecto (Contraseña: Admin2026@)
-- Hash bcrypt para 'Admin2026@'
INSERT INTO usuarios (id_usuario, id_propietario, nombre, apellidos, email, password_hash, rol, estado, fecha_aprobacion)
VALUES (1, 1, 'Administrador', 'Principal', 'admin@agrodietario.local', '$2y$10$wK2V7WwT7.2Z3L6n1U8WKe2OsqqH7H0eJv6oU8iA1hZ0sV9A1Wp3K', 'superadmin', 'aprobado', NOW());

-- 3. Configuración inicial del portal
INSERT INTO configuracion_web (id_config, titulo_portal, subtitulo_portal, descripcion_bienvenida, telefono_soporte, email_contacto, permitir_registro_publico, usar_captcha)
VALUES (1, 'AgroDietario', 'Sistema Integral de Gestión y Control Agrícola', 'Bienvenido a la plataforma centralizada de gestión de fincas, cuaderno de campo digital y seguimiento de labores agronómicas.', '968 000 000', 'info@agrodietario.local', 1, 1);

-- 4. Parajes de demostración
INSERT INTO parajes (id_paraje, id_propietario, nombre_paraje, termino_municipal, provincia, descripcion) VALUES
(1, 1, 'Hoya del Pozo', 'Cieza', 'Murcia', 'Zona de vega con suelo aluvial fértil y riego tradicional'),
(2, 1, 'Los Llanos de Arriba', 'Jumilla', 'Murcia', 'Meseta de secano y regadío por goteo, idónea para viñedo y olivar'),
(3, 1, 'Barranco Blanco', 'Hellín', 'Albacete', 'Terreno calizo con excelente insolación');

-- 5. Catálogo inicial de Cultivos con enlaces botánicos
INSERT INTO cultivos (id_cultivo, id_propietario, nombre_comun, nombre_cientifico, variedad, dias_ciclo_aprox, tipo_cultivo, url_consulta_botanica, requerimientos_tecnicos) VALUES
(1, NULL, 'Melocotonero', 'Prunus persica', 'Romea', 150, 'frutal', 'https://es.wikipedia.org/wiki/Prunus_persica', 'Necesidad de horas frío invernales (600-800h). Sensible a heladas tardías en floración.'),
(2, NULL, 'Almendro', 'Prunus dulcis', 'Guara (Autofertil)', 240, 'frutal', 'https://es.wikipedia.org/wiki/Prunus_dulcis', 'Floración tardía, resistente a sequía relativa pero responde con alta producción a riegos de apoyo.'),
(3, NULL, 'Olivo', 'Olea europaea', 'Picual', 300, 'olivar', 'https://es.wikipedia.org/wiki/Olea_europaea', 'Alta resistencia a suelos calizos. Muy productivo para aceite de alta estabilidad.'),
(4, NULL, 'Vid vinífera', 'Vitis vinifera', 'Monastrell', 210, 'vinedo', 'https://es.wikipedia.org/wiki/Vitis_vinifera', 'Variedad rústica perfectamente adaptada al clima semiárido. Poda en vaso tradicional o espaldera.'),
(5, NULL, 'Limonero', 'Citrus x limon', 'Fino 49', 365, 'citrico', 'https://es.wikipedia.org/wiki/Citrus_%C3%97_limon', 'Sensible a heladas por debajo de -2ºC. Exigente en riegos regulares y aportes de microelementos (Hierro/Zinc).'),
(6, NULL, 'Lechuga', 'Lactuca sativa', 'Romana', 75, 'horticola', 'https://es.wikipedia.org/wiki/Lactuca_sativa', 'Ciclo corto. Suelos bien drenados con buen nivel de materia orgánica. Riego continuo por goteo o aspersión.');
