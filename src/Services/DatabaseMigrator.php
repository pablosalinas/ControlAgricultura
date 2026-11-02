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

            // 3. Columnas en PROPIETARIOS
            self::addColumnIfNotExists($pdo, 'propietarios', 'precio_hora_comunitaria', 'DECIMAL(8,2) NOT NULL DEFAULT 12.00');
            self::addColumnIfNotExists($pdo, 'propietarios', 'precio_hora_hortalizas', 'DECIMAL(8,2) NOT NULL DEFAULT 15.00');
            self::addColumnIfNotExists($pdo, 'propietarios', 'derrama_anual_comunidad', 'DECIMAL(10,2) NOT NULL DEFAULT 150.00');

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
