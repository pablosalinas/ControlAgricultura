<?php
/**
 * Migrador Automático y Auto-reparador de Esquema de Base de Datos
 * Asegura que todas las columnas nuevas y tablas existan tanto en local como en IONOS producción
 */

class DatabaseMigrator {

    public static function run() {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) return;

            // 1. Tabla REGANTES
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS regantes (
                    id_regante INT AUTO_INCREMENT PRIMARY KEY,
                    id_propietario INT NULL,
                    num_regante VARCHAR(50) NOT NULL,
                    nombre_completo VARCHAR(150) NOT NULL,
                    cif_nif VARCHAR(20) NULL,
                    direccion VARCHAR(255) NULL,
                    municipio VARCHAR(100) NULL,
                    telefono VARCHAR(50) NULL,
                    banco_habitual VARCHAR(100) NULL DEFAULT 'CAJAMAR',
                    horas_tanda_disponibles DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                    volumen_m3_tanda DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    horas_comunitaria_disponibles DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                    volumen_m3_comunitaria DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    parada_habitual VARCHAR(100) NULL,
                    paraje_habitual VARCHAR(100) NULL,
                    tablon_habitual VARCHAR(50) NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    notas TEXT NULL,
                    fecha_alta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_regante_num (num_regante),
                    INDEX idx_regante_nombre (nombre_completo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Semillas de regantes iniciales si la tabla está vacía
            $countReg = $pdo->query("SELECT COUNT(*) FROM regantes")->fetchColumn();
            if ($countReg == 0) {
                $stmtInsReg = $pdo->prepare("
                    INSERT INTO regantes (
                        num_regante, nombre_completo, cif_nif, direccion, municipio,
                        telefono, banco_habitual, horas_tanda_disponibles, horas_comunitaria_disponibles,
                        parada_habitual, paraje_habitual, tablon_habitual, activo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmtInsReg->execute([
                    'A1188', 'Pablo Salinas Marín', '52810184E', 'C/ Virgen de Montserrat, 31 1ºB', 'Totana',
                    '679873616', 'CAJAMAR', 2.00, 4.50, 'Balsa Perales (67)', 'Hornillos', '6'
                ]);
                $stmtInsReg->execute([
                    'A0694', 'Francisco Rodríguez Carrasco', '27481923A', 'CRTA. De Calasparra, 20', 'Moratalla',
                    '679873616', 'CAJAMAR', 2.00, 3.00, 'Perales (067)', 'Perales', '2'
                ]);
                $stmtInsReg->execute([
                    'A0420', 'Antonio Martínez García', '48392019B', 'C/ Mayor, 14', 'Moratalla',
                    '612345678', 'CAJAMAR', 1.50, 2.00, 'Perales (067)', 'Perales', '2'
                ]);
            }

            // 2. Columnas en FINCAS
            self::addColumnIfNotExists($pdo, 'fincas', 'coste_anual_ibi', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
            self::addColumnIfNotExists($pdo, 'fincas', 'coste_anual_derrama', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
            self::addColumnIfNotExists($pdo, 'fincas', 'id_comunidad', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'fincas', 'descripcion', 'TEXT NULL');
            self::addColumnIfNotExists($pdo, 'fincas', 'latitud_centro', 'DECIMAL(11,8) NULL');
            self::addColumnIfNotExists($pdo, 'fincas', 'longitud_centro', 'DECIMAL(11,8) NULL');
            self::addColumnIfNotExists($pdo, 'fincas', 'zoom_mapa', 'INT NULL DEFAULT 16');
            self::addColumnIfNotExists($pdo, 'fincas', 'geojson_perimetro', 'LONGTEXT NULL');

            // 2.1 Tablas para Mapeo SIG, Zonas Delimitadas y Árboles Individuales
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS plantaciones_geometria (
                    id_geometria INT AUTO_INCREMENT PRIMARY KEY,
                    id_plantacion INT NOT NULL,
                    id_finca INT NOT NULL,
                    nombre_zona VARCHAR(150) NULL,
                    tipo_geometria VARCHAR(50) NOT NULL DEFAULT 'polygon',
                    geojson LONGTEXT NOT NULL,
                    color_hex VARCHAR(20) NOT NULL DEFAULT '#10b981',
                    superficie_m2 DECIMAL(12,2) NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    actualizado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_geom_plantacion (id_plantacion),
                    INDEX idx_geom_finca (id_finca),
                    INDEX idx_geom_activo (activo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS arboles_individuales (
                    id_arbol INT AUTO_INCREMENT PRIMARY KEY,
                    id_finca INT NOT NULL,
                    id_plantacion INT NULL,
                    id_cultivo INT NULL,
                    codigo_arbol VARCHAR(50) NOT NULL,
                    especie_variedad VARCHAR(150) NULL,
                    latitud DECIMAL(11,8) NOT NULL,
                    longitud DECIMAL(11,8) NOT NULL,
                    ano_plantacion INT NULL,
                    estado_salud ENUM('vigoroso', 'bueno', 'regular', 'enfermo', 'seco', 'arrancado') NOT NULL DEFAULT 'bueno',
                    patron_portainjerto VARCHAR(100) NULL,
                    notas TEXT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    actualizado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_arbol_finca (id_finca),
                    INDEX idx_arbol_plantacion (id_plantacion),
                    INDEX idx_arbol_cultivo (id_cultivo),
                    INDEX idx_arbol_activo (activo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 2b. Columnas agronómicas e IA en CULTIVOS
            self::addColumnIfNotExists($pdo, 'cultivos', 'descripcion', 'TEXT NULL');
            self::addColumnIfNotExists($pdo, 'cultivos', 'formato_siembra', 'VARCHAR(255) NULL');
            self::addColumnIfNotExists($pdo, 'cultivos', 'epoca_siembra', 'VARCHAR(255) NULL');
            self::addColumnIfNotExists($pdo, 'cultivos', 'epoca_trasplante', 'VARCHAR(255) NULL');
            self::addColumnIfNotExists($pdo, 'cultivos', 'epoca_recoleccion', 'VARCHAR(255) NULL');
            self::addColumnIfNotExists($pdo, 'cultivos', 'tratamientos_recomendados', 'TEXT NULL');
            self::addColumnIfNotExists($pdo, 'cultivos', 'es_ecologico', 'TINYINT(1) NOT NULL DEFAULT 0');

            // 2c. Columnas y borrado lógico en TRATAMIENTOS
            self::addColumnIfNotExists($pdo, 'tratamientos', 'activo', 'TINYINT(1) NOT NULL DEFAULT 1');
            self::addColumnIfNotExists($pdo, 'tratamientos', 'modo_accion', 'VARCHAR(150) NULL');
            self::addColumnIfNotExists($pdo, 'tratamientos', 'id_herramienta', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'tratamientos', 'maquinaria_empleada', 'VARCHAR(150) NULL');

            // 2d. Columnas en RIEGOS (Comunidad vs Depósito Goteo y soft delete)
            self::addColumnIfNotExists($pdo, 'riegos', 'origen_agua', "VARCHAR(50) NOT NULL DEFAULT 'comunidad'");
            self::addColumnIfNotExists($pdo, 'riegos', 'id_deposito', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'riegos', 'litros_consumidos', 'DECIMAL(12,2) NULL');
            self::addColumnIfNotExists($pdo, 'riegos', 'id_herramienta', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'riegos', 'equipo_empleado', 'VARCHAR(150) NULL');
            self::addColumnIfNotExists($pdo, 'riegos', 'activo', 'TINYINT(1) NOT NULL DEFAULT 1');

            // 2e. Columnas en LABORES_SUELO (Asociación a Finca y soft delete)
            self::addColumnIfNotExists($pdo, 'labores_suelo', 'id_finca', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'labores_suelo', 'id_herramienta', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'labores_suelo', 'activo', 'TINYINT(1) NOT NULL DEFAULT 1');

            // 2f. Columnas en PODAS_TALAS y RECOLECCIONES (Soft delete y herramientas)
            self::addColumnIfNotExists($pdo, 'podas_talas', 'id_herramienta', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'podas_talas', 'herramienta_usada', 'VARCHAR(150) NULL');
            self::addColumnIfNotExists($pdo, 'podas_talas', 'activo', 'TINYINT(1) NOT NULL DEFAULT 1');
            self::addColumnIfNotExists($pdo, 'recolecciones', 'id_herramienta', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'recolecciones', 'herramientas_transporte', 'VARCHAR(150) NULL');
            self::addColumnIfNotExists($pdo, 'recolecciones', 'activo', 'TINYINT(1) NOT NULL DEFAULT 1');

            // 2g. Columnas en PLANTACIONES (Soft delete, tipo fija/temporada y fecha recolección adaptable)
            self::addColumnIfNotExists($pdo, 'plantaciones', 'activo', 'TINYINT(1) NOT NULL DEFAULT 1');
            self::addColumnIfNotExists($pdo, 'plantaciones', 'notas', 'TEXT NULL');
            self::addColumnIfNotExists($pdo, 'plantaciones', 'notas_cuaderno', 'TEXT NULL');
            self::addColumnIfNotExists($pdo, 'plantaciones', 'tipo_plantacion', "VARCHAR(30) NOT NULL DEFAULT 'temporada'");
            self::addColumnIfNotExists($pdo, 'plantaciones', 'dia_mes_recoleccion', "VARCHAR(10) NULL");

            // 3. Columnas en PROPIETARIOS (Explotaciones)
            self::addColumnIfNotExists($pdo, 'propietarios', 'logo', 'VARCHAR(255) NULL');
            self::addColumnIfNotExists($pdo, 'propietarios', 'fanegas_por_hectarea', 'DECIMAL(8,4) NOT NULL DEFAULT 3.0000');
            self::addColumnIfNotExists($pdo, 'propietarios', 'precio_hora_comunitaria', 'DECIMAL(8,2) NOT NULL DEFAULT 12.00');
            self::addColumnIfNotExists($pdo, 'propietarios', 'precio_hora_hortalizas', 'DECIMAL(8,2) NOT NULL DEFAULT 15.00');
            self::addColumnIfNotExists($pdo, 'propietarios', 'derrama_anual_comunidad', 'DECIMAL(10,2) NOT NULL DEFAULT 150.00');
            self::addColumnIfNotExists($pdo, 'propietarios', 'smtp_host', 'VARCHAR(150) NULL');
            self::addColumnIfNotExists($pdo, 'propietarios', 'smtp_port', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'propietarios', 'smtp_encryption', 'VARCHAR(20) NULL');
            self::addColumnIfNotExists($pdo, 'propietarios', 'smtp_user_enc', 'TEXT NULL');
            self::addColumnIfNotExists($pdo, 'propietarios', 'smtp_pass_enc', 'TEXT NULL');
            self::addColumnIfNotExists($pdo, 'propietarios', 'email_remitente', 'VARCHAR(150) NULL');
            self::addColumnIfNotExists($pdo, 'propietarios', 'email_comunidad', "VARCHAR(150) NULL DEFAULT 'alharabemoratalla@gmail.com'");

            // 4. Columnas en CONFIGURACION_WEB
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'id_regante_defecto', 'INT NULL');
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'fanegas_por_hectarea', 'DECIMAL(8,4) NOT NULL DEFAULT 3.0000');
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'precio_hora_comunitaria', 'DECIMAL(8,2) NOT NULL DEFAULT 12.00');
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'precio_hora_hortalizas', 'DECIMAL(8,2) NOT NULL DEFAULT 15.00');
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'derrama_anual_comunidad', 'DECIMAL(10,2) NOT NULL DEFAULT 150.00');
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'smtp_host', "VARCHAR(150) NOT NULL DEFAULT 'smtp.ionos.es'");
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'smtp_port', "INT NOT NULL DEFAULT 587");
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'smtp_encryption', "VARCHAR(20) NOT NULL DEFAULT 'tls'");
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'smtp_user_enc', "TEXT NULL");
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'smtp_pass_enc', "TEXT NULL");
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'email_remitente', "VARCHAR(150) NOT NULL DEFAULT 'info@moratalla-murcia.com'");
            self::addColumnIfNotExists($pdo, 'configuracion_web', 'email_comunidad', "VARCHAR(150) NOT NULL DEFAULT 'alharabemoratalla@gmail.com'");

            // Cargar credenciales SMTP iniciales si están vacías
            $cfgRow = $pdo->query("SELECT * FROM configuracion_web LIMIT 1")->fetch();
            if ($cfgRow && empty($cfgRow['smtp_user_enc'])) {
                $updCfg = $pdo->prepare("
                    UPDATE configuracion_web 
                    SET smtp_user_enc = :u, smtp_pass_enc = :p, email_remitente = 'info@moratalla-murcia.com',
                        email_comunidad = 'alharabemoratalla@gmail.com'
                    WHERE id_config = :id
                ");
                $updCfg->execute([
                    ':u' => AG_DEFAULT_SMTP_USER_ENC,
                    ':p' => AG_DEFAULT_SMTP_PASS_ENC,
                    ':id' => $cfgRow['id_config']
                ]);
            }

            // 5. Tabla COMUNIDADES_REGANTES (Maestro de Comunidades de Riego)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS comunidades_regantes (
                    id_comunidad INT AUTO_INCREMENT PRIMARY KEY,
                    id_propietario INT NULL,
                    nombre_comunidad VARCHAR(150) NOT NULL,
                    cif VARCHAR(20) NULL,
                    email_solicitudes VARCHAR(150) NOT NULL DEFAULT 'alharabemoratalla@gmail.com',
                    telefono VARCHAR(50) NULL,
                    direccion VARCHAR(255) NULL,
                    municipio VARCHAR(100) NULL DEFAULT 'Moratalla',
                    provincia VARCHAR(100) NULL DEFAULT 'Murcia',
                    banco_habitual VARCHAR(100) NULL DEFAULT 'CAJAMAR',
                    derrama_anual DECIMAL(10,2) NOT NULL DEFAULT 150.00,
                    precio_hora_tanda DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                    consumo_max_tanda_horas DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                    precio_hora_comunitaria DECIMAL(8,2) NOT NULL DEFAULT 12.00,
                    consumo_max_comunitaria_horas DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                    precio_hora_hortalizas DECIMAL(8,2) NOT NULL DEFAULT 15.00,
                    consumo_max_hortalizas_horas DECIMAL(8,2) NOT NULL DEFAULT 2.00,
                    es_predeterminada TINYINT(1) NOT NULL DEFAULT 0,
                    horario_atencion VARCHAR(255) NULL DEFAULT 'Lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00',
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    notas TEXT NULL,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_comunidad_nombre (nombre_comunidad),
                    INDEX idx_comunidad_predet (es_predeterminada)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Semilla inicial Río Alhárabe si está vacía
            $countCom = $pdo->query("SELECT COUNT(*) FROM comunidades_regantes")->fetchColumn();
            if ($countCom == 0) {
                $stmtInsCom = $pdo->prepare("
                    INSERT INTO comunidades_regantes (
                        nombre_comunidad, cif, email_solicitudes, telefono, direccion, municipio,
                        banco_habitual, derrama_anual, precio_hora_tanda, consumo_max_tanda_horas,
                        precio_hora_comunitaria, consumo_max_comunitaria_horas,
                        precio_hora_hortalizas, consumo_max_hortalizas_horas,
                        es_predeterminada, horario_atencion, activo
                    ) VALUES (
                        'Comunidad de Regantes del Río Alhárabe', 'G30029381', 'alharabemoratalla@gmail.com',
                        '968 73 00 00', 'Calle Mayor, s/n', 'Moratalla',
                        'CAJAMAR', 150.00, 0.00, 0.00,
                        12.00, 0.00,
                        15.00, 2.00,
                        1, 'Lunes a viernes de 11:30 a 14:00 y de 16:00 a 19:00', 1
                    )
                ");
                $stmtInsCom->execute();
            }

            // Si hay sólo una activa, asegurar que sea la predeterminada
            $totalActivas = (int)$pdo->query("SELECT COUNT(*) FROM comunidades_regantes WHERE activo = 1")->fetchColumn();
            if ($totalActivas === 1) {
                $pdo->exec("UPDATE comunidades_regantes SET es_predeterminada = 1 WHERE activo = 1");
            }

            self::addColumnIfNotExists($pdo, 'comunidades_regantes', 'id_regante_defecto', 'INT NULL');

            // 6. Tabla SOLICITUDES_RIEGO (Historial, Partes, Asignación de Turnos y Estados)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS solicitudes_riego (
                    id_solicitud INT AUTO_INCREMENT PRIMARY KEY,
                    id_propietario INT NULL,
                    id_finca INT NULL,
                    id_comunidad INT NULL,
                    id_regante INT NULL,
                    num_regante VARCHAR(50) NOT NULL,
                    titular VARCHAR(150) NOT NULL,
                    cif_nif VARCHAR(30) NULL,
                    telefono VARCHAR(50) NULL,
                    domicilio VARCHAR(255) NULL,
                    banco VARCHAR(100) NULL,
                    tipo_riego VARCHAR(50) NOT NULL,
                    horas_solicitadas DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                    coste_estimado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    parada VARCHAR(100) NULL,
                    paraje VARCHAR(150) NULL,
                    tablon VARCHAR(50) NULL,
                    observaciones TEXT NULL,
                    cesiones_json TEXT NULL,
                    fecha_solicitud DATE NOT NULL,
                    nombre_archivo_pdf VARCHAR(255) NULL,
                    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
                    fecha_riego_asignado DATE NULL,
                    hora_riego_asignado TIME NULL,
                    enviado_email TINYINT(1) NOT NULL DEFAULT 0,
                    fecha_envio_email DATETIME NULL,
                    email_destino VARCHAR(150) NULL,
                    notas_admin TEXT NULL,
                    creado_el DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                    actualizado_el DATETIME NULL,
                    INDEX idx_sol_regante (num_regante),
                    INDEX idx_sol_fecha (fecha_solicitud),
                    INDEX idx_sol_estado (estado),
                    INDEX idx_sol_tipo (tipo_riego)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 7. Tabla USUARIOS_EXPLOTACIONES (Relación N:M para asignar usuarios a una o varias explotaciones)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS usuarios_explotaciones (
                    id_usuario INT NOT NULL,
                    id_propietario INT NOT NULL,
                    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_usuario, id_propietario),
                    INDEX idx_ue_user (id_usuario),
                    INDEX idx_ue_prop (id_propietario)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Migrar usuarios existentes que tengan id_propietario asignado a la tabla intermedia
            $pdo->exec("
                INSERT IGNORE INTO usuarios_explotaciones (id_usuario, id_propietario)
                SELECT id_usuario, id_propietario
                FROM usuarios
                WHERE id_propietario IS NOT NULL AND id_propietario > 0;
            ");

            // 8. Tabla LOGS_ACCESOS (Contador de visitas, auditoría y control de accesos al sistema y administración)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS logs_accesos (
                    id_log BIGINT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent VARCHAR(255) NULL,
                    metodo VARCHAR(10) NOT NULL DEFAULT 'GET',
                    url_solicitada VARCHAR(255) NOT NULL,
                    ruta VARCHAR(100) NULL,
                    id_usuario INT NULL,
                    nombre_usuario VARCHAR(150) NULL,
                    rol_usuario VARCHAR(50) NULL,
                    id_propietario INT NULL,
                    es_admin TINYINT(1) NOT NULL DEFAULT 0,
                    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_log_fecha (fecha_hora),
                    INDEX idx_log_ip (ip_address),
                    INDEX idx_log_admin (es_admin),
                    INDEX idx_log_user (id_usuario)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 9. Columna id_comunidad en REGANTES
            self::addColumnIfNotExists($pdo, 'regantes', 'id_comunidad', 'INT NULL');
            // Vincular socios existentes a la primera comunidad de regantes si no tienen
            $primeraComId = $pdo->query("SELECT id_comunidad FROM comunidades_regantes WHERE activo = 1 ORDER BY es_predeterminada DESC, id_comunidad ASC LIMIT 1")->fetchColumn();
            if ($primeraComId) {
                $pdo->exec("UPDATE regantes SET id_comunidad = " . (int)$primeraComId . " WHERE id_comunidad IS NULL OR id_comunidad = 0");
            }

            // 10. Tabla EXPLOTACIONES_COMUNIDADES (Relación N:M entre Explotaciones y Comunidades de Regantes compartidas)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS explotaciones_comunidades (
                    id_propietario INT NOT NULL,
                    id_comunidad INT NOT NULL,
                    fecha_vinculacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_propietario, id_comunidad),
                    INDEX idx_ec_prop (id_propietario),
                    INDEX idx_ec_com (id_comunidad)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Si está vacía, asociar de inicio las comunidades existentes con los propietarios existentes
            $countEC = $pdo->query("SELECT COUNT(*) FROM explotaciones_comunidades")->fetchColumn();
            if ($countEC == 0) {
                $pdo->exec("
                    INSERT IGNORE INTO explotaciones_comunidades (id_propietario, id_comunidad)
                    SELECT p.id_propietario, c.id_comunidad
                    FROM propietarios p
                    CROSS JOIN comunidades_regantes c
                    WHERE p.activo = 1 AND c.activo = 1;
                ");
            }

            // 11. Tabla TIPOS_GASTOS_INGRESOS (Maestro configurable de conceptos económicos)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tipos_gastos_ingresos (
                    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
                    id_propietario INT NULL,
                    nombre_tipo VARCHAR(100) NOT NULL,
                    naturaleza ENUM('gasto', 'ingreso', 'ambos') NOT NULL DEFAULT 'gasto',
                    color VARCHAR(30) NULL DEFAULT '#0284c7',
                    descripcion VARCHAR(255) NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_tgi_prop (id_propietario),
                    INDEX idx_tgi_nombre (nombre_tipo),
                    INDEX idx_tgi_nat (naturaleza)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Semillas iniciales del maestro de tipos de gastos e ingresos
            $countTipos = $pdo->query("SELECT COUNT(*) FROM tipos_gastos_ingresos")->fetchColumn();
            if ($countTipos == 0) {
                $stmtInsTipo = $pdo->prepare("
                    INSERT INTO tipos_gastos_ingresos (nombre_tipo, naturaleza, color, descripcion, activo)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $semillasTipos = array(
                    array('Fitosanitarios y Tratamientos', 'gasto', '#dc2626', 'Herbicidas, insecticidas, fungicidas y caldos'),
                    array('Fertilizantes y Abonos', 'gasto', '#16a34a', 'Abonos de fondo, cobertera, fertirrigación y enmiendas'),
                    array('Riego y Cuotas de Agua', 'gasto', '#0284c7', 'Consumos por tanda, horas comunitarias y canon'),
                    array('Derramas Comunidad de Regantes', 'gasto', '#0369a1', 'Derramas anuales y extraordinarias de comunidades'),
                    array('IBI y Tributos Rústicos', 'gasto', '#d97706', 'Impuesto de Bienes Inmuebles rústico y tasas locales'),
                    array('Maquinaria, Aperos y Gasóleo', 'gasto', '#475569', 'Combustible agrícola, reparaciones, filtros y aperos'),
                    array('Mano de Obra y Cuadrillas', 'gasto', '#7c3aed', 'Jornales de recolección, aclareo y trabajos de campo'),
                    array('Poda, Injerto y Triturado', 'gasto', '#059669', 'Mano de obra y servicios de podadores y picado de ramas'),
                    array('Semillas, Plantones y Vivero', 'gasto', '#65a30d', 'Adquisición de árboles nuevos, plantones y semillas'),
                    array('Mantenimiento y Estructuras', 'gasto', '#64748b', 'Reparación de vallas, cabezal de riego, gomas y balsas'),
                    array('Venta de Cosechas / Producción', 'ingreso', '#10b981', 'Liquidaciones de cooperativas, fruta, cereal o madera'),
                    array('Subvenciones PAC y Ayudas Agrarias', 'ingreso', '#059669', 'Pago básico, eco-regímenes y ayudas agroambientales'),
                    array('Otros Gastos de Explotación', 'gasto', '#94a3b8', 'Seguros agrarios, gestoría, análisis y varios')
                );
                foreach ($semillasTipos as $st) {
                    $stmtInsTipo->execute($st);
                }
            }

            // 12. Tabla GASTOS_EXPLOTACION (Gastos e ingresos imputados a cada finca)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS gastos_explotacion (
                    id_gasto INT AUTO_INCREMENT PRIMARY KEY,
                    id_propietario INT NOT NULL,
                    id_finca INT NULL,
                    id_tipo INT NULL,
                    tipo_movimiento ENUM('gasto', 'ingreso') NOT NULL DEFAULT 'gasto',
                    fecha_gasto DATE NOT NULL,
                    concepto VARCHAR(200) NOT NULL,
                    importe DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    proveedor_acreedor VARCHAR(150) NULL,
                    num_factura VARCHAR(80) NULL,
                    confirmado TINYINT(1) NOT NULL DEFAULT 1,
                    notas TEXT NULL,
                    archivo_adjunto VARCHAR(255) NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    creado_por INT NULL,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    actualizado_el DATETIME NULL,
                    INDEX idx_ge_prop (id_propietario),
                    INDEX idx_ge_finca (id_finca),
                    INDEX idx_ge_fecha (fecha_gasto),
                    INDEX idx_ge_confirmado (confirmado),
                    INDEX idx_ge_tipo (id_tipo),
                    INDEX idx_ge_mov (tipo_movimiento)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 11. TABLA DE DEPÓSITOS Y BALSAS DE AGUA PARA RIEGO POR GOTEO
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS depositos_agua (
                    id_deposito INT AUTO_INCREMENT PRIMARY KEY,
                    id_propietario INT NOT NULL,
                    id_finca INT NULL,
                    nombre_deposito VARCHAR(150) NOT NULL,
                    capacidad_litros DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    litros_actuales DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    tipo_deposito VARCHAR(50) DEFAULT 'deposito',
                    ubicacion VARCHAR(255) NULL,
                    observaciones TEXT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_dep_prop (id_propietario),
                    INDEX idx_dep_finca (id_finca)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 12. TABLA DE REGISTROS DE LLENADO DE DEPÓSITOS
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS depositos_llenados (
                    id_llenado INT AUTO_INCREMENT PRIMARY KEY,
                    id_deposito INT NOT NULL,
                    fecha_llenado DATE NOT NULL,
                    litros_llenados DECIMAL(12, 2) NOT NULL,
                    procedencia_agua VARCHAR(150) NOT NULL,
                    coste_llenado DECIMAL(10, 2) NULL DEFAULT 0.00,
                    observaciones TEXT NULL,
                    creado_por_id INT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_llen_dep (id_deposito),
                    INDEX idx_llen_fecha (fecha_llenado)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 13. Columnas en PARAJES y Semillero de Paradas Oficiales
            self::addColumnIfNotExists($pdo, 'parajes', 'codigo', 'VARCHAR(20) NULL AFTER id_paraje');
            self::addColumnIfNotExists($pdo, 'parajes', 'activo', 'TINYINT(1) NOT NULL DEFAULT 1');

            self::seedParadasOficiales($pdo);

            // 14. TABLA DE MAQUINARIA Y HERRAMIENTAS (Manuales y Mecánicas)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS maquinaria_herramientas (
                    id_herramienta INT AUTO_INCREMENT PRIMARY KEY,
                    id_propietario INT NOT NULL,
                    nombre VARCHAR(150) NOT NULL,
                    tipo VARCHAR(50) NOT NULL DEFAULT 'mecanica',
                    categoria VARCHAR(80) NULL,
                    marca VARCHAR(100) NULL,
                    modelo VARCHAR(100) NULL,
                    matricula_o_serie VARCHAR(100) NULL,
                    ano_adquisicion INT NULL,
                    estado VARCHAR(50) NOT NULL DEFAULT 'operativa',
                    fecha_revision DATE NULL,
                    horas_o_km_uso DECIMAL(10,2) NULL DEFAULT 0.00,
                    observaciones TEXT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_mh_prop (id_propietario),
                    INDEX idx_mh_tipo (tipo),
                    INDEX idx_mh_activo (activo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            self::seedMaquinariaHerramientas($pdo);

        } catch (Exception $e) {
            // Error silencioso si no hay conexión aún
        }
    }

    private static function seedMaquinariaHerramientas($pdo) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM maquinaria_herramientas")->fetchColumn();
            if ($count > 0) {
                return;
            }

            $stmtProp = $pdo->query("SELECT id_propietario FROM propietarios WHERE activo = 1");
            $props = $stmtProp ? $stmtProp->fetchAll(PDO::FETCH_COLUMN) : array();
            if (empty($props)) {
                $props = array(1);
            }

            $equipos = array(
                // Maquinaria Mecánica y Tractores
                array('Tractor Frutero John Deere 5075E', 'tractor', 'Tractor Agrícola', 'John Deere', '5075E 4WD', 'E-4819-BHG', 2021, 'operativa', 'ITV en vigor. Mantenimiento de aceite y filtros cada 250h. Doble tracción.'),
                array('Atomizador arrastrado 2000L Fitosa', 'mecanica', 'Tratamientos Fitosanitarios', 'Fitosa', 'Agro-2000 Turbo', 'ITEAF-30/2025', 2020, 'operativa', 'Turbina doble de 900mm con boquillas cerámicas antigota. ITEAF aprobada.'),
                array('Pulverizador suspendido 600L con barras', 'mecanica', 'Tratamientos Fitosanitarios', 'Gaysa', 'Titan 600', 'SN-GY600-44', 2019, 'operativa', 'Barras hidráulicas de 10 metros para hortalizas y aplicación de herbicida en suelo.'),
                array('Desbrozadora de gasolina Stihl FS 460', 'mecanica', 'Desbroce y Limpieza', 'Stihl', 'FS 460 C-EM', 'SN-STH-4609', 2022, 'operativa', 'Cabezal de hilo AutoCut y disco de 3 puntas para matorral y bordeado de parcelas.'),
                array('Motocultor Pasquali 14CV con Rotovator', 'mecanica', 'Laboreo de Huerta', 'Pasquali', 'Especial 14CV', 'SN-PAS-1402', 2018, 'operativa', 'Fresadora de 80 cm para preparación de camas de siembra y cuadros de hortalizas.'),
                array('Motosierra de poda con espada carving', 'mecanica', 'Podas y Talas', 'Stihl', 'MS 194 T', 'SN-STH-1941', 2023, 'operativa', 'Ligera (3.3 kg), ideal para poda en altura y desrame fino de frutales y olivos.'),

                // Aperos e Implementos de Tractor
                array('Cultivador agrícola 7 brazos con muelles', 'apero', 'Laboreo del Suelo', 'Noli', 'C-7B Reforzado', NULL, 2018, 'operativa', 'Rejas con golondrinas nuevas. Chasis tubular reforzado de alta resistencia.'),
                array('Trituradora de sarmientos y poda Serrat', 'apero', 'Podas y Reciclaje Biomasa', 'Serrat', 'Agri-180', 'SN-SRT-180', 2021, 'operativa', 'Martillos reversibles para triturado fino e incorporación in situ de restos vegetales.'),
                array('Remolque basculante agrícola 4500kg', 'apero', 'Transporte y Cosecha', 'Beguer', 'R-4500B', 'R-1982-BCR', 2017, 'operativa', 'Laterales suplementados desmontables para recolección de fruta y transporte de abono.'),

                // Herramientas Manuales
                array('Tijeras de poda dos manos con multiplicador', 'manual', 'Podas y Talas', 'Bellota', 'Pro Cut 800', NULL, 2023, 'operativa', 'Mangos ligeros de aluminio elíptico y hojas de acero templado reafilables.'),
                array('Tijeras de recolección y vendimia curva', 'manual', 'Recolección', 'Kamikaze', 'KM-1', NULL, 2024, 'operativa', 'Hoja corta curva con punta redondeada para cortar el pedúnculo sin dañar el fruto.'),
                array('Mochila pulverizadora manual Matabi 16L', 'manual', 'Tratamientos Fitosanitarios', 'Matabi', 'Super Agro 16', NULL, 2023, 'operativa', 'Regulador de presión a 1.5 y 3 bar. Tratamientos focalizados y respetuosos.'),
                array('Azada y legón forjado de ojo redondo', 'manual', 'Laboreo y Huertos', 'Bellota', 'Ref. 120-B', NULL, 2022, 'operativa', 'Acero forjado de una sola pieza. Para caballones, regueras y limpieza de alcorques.'),
                array('Carretilla manual reforzada 100L', 'manual', 'Transporte y Cosecha', 'Theca', 'C1-100', NULL, 2023, 'operativa', 'Bandeja embutida de chapa galvanizada y rueda neumática con llanta de acero.')
            );

            $stmtIns = $pdo->prepare("
                INSERT INTO maquinaria_herramientas (
                    id_propietario, nombre, tipo, categoria, marca, modelo,
                    matricula_o_serie, ano_adquisicion, estado, observaciones, activo
                ) VALUES (
                    :prop, :nom, :tipo, :cat, :marca, :mod,
                    :serie, :ano, :est, :obs, 1
                )
            ");

            foreach ($props as $propId) {
                foreach ($equipos as $eq) {
                    $stmtIns->execute(array(
                        ':prop'  => (int)$propId,
                        ':nom'   => $eq[0],
                        ':tipo'  => $eq[1],
                        ':cat'   => $eq[2],
                        ':marca' => $eq[3],
                        ':mod'   => $eq[4],
                        ':serie' => $eq[5],
                        ':ano'   => $eq[6],
                        ':est'   => $eq[7],
                        ':obs'   => $eq[8]
                    ));
                }
            }
        } catch (Exception $e) {}
    }

    private static function seedParadasOficiales($pdo) {
        try {
            $paradas = array(
                array('001', 'CORTIJO DON RAFFAEL'),
                array('002', 'JAVIER SÁNCHEZ DE AMORAGA'),
                array('003', 'JOSÉ CARCÍA MARÍN'),
                array('004', 'JOSÉ CARCÍA MARÍN'),
                array('005', 'JAVIER SANCHEZ DE AMORAGA'),
                array('006', 'JULÍA VÉLEZ GONZÁLEZ'),
                array('007', 'JULÍA VÉLEZ GONZÁLEZ'),
                array('008', 'JAVIER SÁNCHEZ DE AMORAGA'),
                array('009', 'JAVIER SÁNCHEZ DE AMORAGA'),
                array('010', 'JAVIER SÁNCHEZ DE AMORAGA'),
                array('011', 'JAVIER SÁNCHEZ DE AMORAGA'),
                array('012', 'JAVIER SÁNCHEZ DE AMORAGA'),
                array('013', 'ANTONIO CARRIDO'),
                array('014', 'MOLINO DEL CAMINO'),
                array('015', 'LA RAMBLA'),
                array('016', 'JAVIER SÁNCHEZ DE AMORAGA'),
                array('016A', 'JUAN DE DIOS LÓPEZ LÓPEZ'),
                array('017', 'JAVIER SÁNCHEZ DE AMORAGA'),
                array('018', 'ROSILLO'),
                array('019', 'FRANCISCO MARTÍNEZ OLIVA AGUILERA'),
                array('020', 'RAMÓN LÓPEZ GARCÍA'),
                array('021', 'RAMÓN LÓPEZ GARCÍA CORTIJO'),
                array('022', 'MARTÍN CUERRERO ABELLÁN'),
                array('023', 'MARTÍN GUERRERO ABELLÁN'),
                array('024', 'PARADA EL BOLSAS'),
                array('025', 'FRANCISCO MARTÍNEZ OLIVA AGUILERA'),
                array('026', 'RAMÓN LÓPEZ GARCÍA'),
                array('027', 'VEREDA DE PROVENCIO'),
                array('028', 'FRANCISCO ELADIO GARCÍA LOZANO'),
                array('029', 'LA TERESA'),
                array('030', 'PICHI 1'),
                array('031', 'PICHI2'),
                array('032', 'PICHI 3'),
                array('033', 'PICHI 4'),
                array('034', 'PICHI 5'),
                array('035', 'LAS TORERAS'),
                array('036', 'EL BALADRE'),
                array('037', 'LA TEJERA'),
                array('038', 'JESÚS MELLINAS SÁNCHEZ'),
                array('039', 'JESÚS MELLINAS SÁNCHEZ'),
                array('040', 'PEDRO MARTÍNEZ CARRASCO'),
                array('041', 'EL BEBEDOR'),
                array('042', 'PARADA EL ZORRA'),
                array('043', 'PARADA ZANCAJO'),
                array('044', 'PARADA HONDA'),
                array('045', 'LA OLIVERA'),
                array('046', 'BANCAL DEL PANTANO OLIVERA'),
                array('047', 'TAHONA ISABEL SÁNCHEZ CARCÍA'),
                array('048', 'TAHONA HEREDEROS PEPE EL MOLINO'),
                array('049', 'EL CARCABO'),
                array('050', 'LO ANCHO'),
                array('051', 'LA CASICA CEFERINO SÁNCHEZ VALERO'),
                array('052', 'LA CASICA JOSÉ MARTÍNEZ MARTÍNEZ'),
                array('053', 'LA CASICA'),
                array('054', 'LA CASICA SALTA JOSÉ MARTÍNEZ MARTÍNEZ'),
                array('055', 'LA CASICA MOLINO JUAN MANUEL ANDREU GUE'),
                array('056', 'LA CASICA MOLINO DESAGÜE A LA RAMBLA'),
                array('057', 'MOLINO LA LANA JUAN SOLER NAVARRO'),
                array('058', 'MOLINO LA LANA RAMIRO GALLEGO MARTÍNEZ'),
                array('059', 'LA LAGUENA'),
                array('060', 'EL ABREVADOR'),
                array('061', 'EL ABREVADOR FRANCISCO NAVARRO SÁNCHEZ'),
                array('062', 'LAS PISCINAS JOSÉ ANTONIO MARTÍNEZ RUBIO'),
                array('063', 'LAS PISCINAS'),
                array('064', 'LAS PISCINAS MANUEL MARTÍNEZ RUBIO'),
                array('065', 'LAS PISCINAS JOSÉ LÓPEZ MARÍN'),
                array('066', 'LAS PISCINAS JOSÉ LÓPEZ MARÍN'),
                array('067', 'BALSAS LOS PERALES'),
                array('068', 'LOS PERALES JESÚS MARTÍNEZ PASTOR'),
                array('069', 'PARADA NUEVA'),
                array('070', 'CUBO MOLINILLO ANTONIO SÁNCHEZ VALERO'),
                array('071', 'CUBO MOLINILLO ANTONIO SÁNCHEZ VALERO'),
                array('072', 'CUBO MOLINILLO DOLORES RODRÍGUEZ MARTÍN'),
                array('073', 'CUBO MOLINILLO DOLORES RODRÍGUEZ MARTÍN'),
                array('074', 'CUBO MOLINILLO FRANCISCO LÓPEZ MARTÍNEZ'),
                array('075', 'CUBO MOLINILLO'),
                array('076', 'CUBO MOLINILLO VITÁLICO SÁNCHEZ MARTÍNEZ'),
                array('077', 'SARRIÓN JUAN SÁNCHEZ MARTÍNEZ'),
                array('078', 'S C ABAJO'),
                array('079', 'S.A. ADELANTE 1'),
                array('080', 'SANTAQUITERIA'),
                array('081', 'S.A. ADELANTE 2'),
                array('082', 'MALANCÓN PUENTE ALTO'),
                array('083', 'S.A. ADELANTE 3'),
                array('084', 'TIO TORRES'),
                array('085', 'SA ADELANTE 4'),
                array('086', 'LAS MARGARITAS'),
                array('087', 'S.A. ADELANTE 5'),
                array('088', 'LAS BALSICAS')
            );

            $stmtChk = $pdo->prepare("SELECT id_paraje FROM parajes WHERE codigo = :cod LIMIT 1");
            $stmtIns = $pdo->prepare("
                INSERT INTO parajes (codigo, nombre_paraje, termino_municipal, provincia, descripcion, activo)
                VALUES (:cod, :nom, 'Moratalla', 'Murcia', :desc, 1)
            ");

            foreach ($paradas as $p) {
                $cod = $p[0];
                $nom = $p[1];
                $stmtChk->execute(array(':cod' => $cod));
                if (!$stmtChk->fetchColumn()) {
                    $stmtIns->execute(array(
                        ':cod'  => $cod,
                        ':nom'  => $nom,
                        ':desc' => "Parada / Paraje de riego oficial nº {$cod} (Moratalla)"
                    ));
                }
            }
        } catch (Exception $e) {}
    }

    private static function addColumnIfNotExists($pdo, $table, $column, $definition) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = :tbl 
                  AND COLUMN_NAME = :col
            ");
            $stmt->execute([':tbl' => $table, ':col' => $column]);
            if ($stmt->fetchColumn() == 0) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Exception $e) {}
    }
}
