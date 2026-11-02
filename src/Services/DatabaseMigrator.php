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

            // 3. Columnas en PROPIETARIOS (Explotaciones)
            self::addColumnIfNotExists($pdo, 'propietarios', 'logo', 'VARCHAR(255) NULL');
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

        } catch (Exception $e) {
            // Error silencioso si no hay conexión aún
        }
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
