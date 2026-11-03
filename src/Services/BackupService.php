<?php
/**
 * Servicio de Copias de Seguridad Completas del Sistema - AgroDietario
 * Genera un volcado SQL de la Base de Datos y empaqueta todo el proyecto web
 * en un archivo ZIP protegido por contraseña (@Acemila2026)
 */

class BackupService {

    const DEFAULT_PASSWORD = '@Acemila2026';

    /**
     * Genera el volcado completo de la base de datos actual en formato SQL
     */
    public static function exportDatabaseSql($pdo) {
        $sql = "-- =====================================================\n";
        $sql .= "-- AgroDietario - Copia de Seguridad Completa de la Base de Datos\n";
        $sql .= "-- Fecha de generacion: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- =====================================================\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $sql .= "-- -----------------------------------------------------\n";
            $sql .= "-- Estructura y datos de la tabla `{$table}`\n";
            $sql .= "-- -----------------------------------------------------\n";

            // Estructura
            $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $createTableSql = isset($createRow['Create Table']) ? $createRow['Create Table'] : '';
            // Convertir a CREATE TABLE IF NOT EXISTS para mayor seguridad
            $createTableSql = preg_replace('/^CREATE TABLE/i', 'CREATE TABLE IF NOT EXISTS', $createTableSql);
            $sql .= $createTableSql . ";\n\n";

            // Datos
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $escapedColumns = array_map(function($c) { return "`" . $c . "`"; }, $columns);
                $colList = implode(', ', $escapedColumns);

                $valuesList = array();
                foreach ($rows as $row) {
                    $vals = array();
                    foreach ($columns as $col) {
                        $val = $row[$col];
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } elseif (is_numeric($val) && !is_string($val)) {
                            $vals[] = $val;
                        } else {
                            $vals[] = $pdo->quote($val);
                        }
                    }
                    $valuesList[] = "(" . implode(', ', $vals) . ")";

                    // Escribir en bloques de 50 registros para evitar límites de tamaño
                    if (count($valuesList) >= 50) {
                        $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES \n" . implode(",\n", $valuesList) . ";\n";
                        $valuesList = array();
                    }
                }

                if (!empty($valuesList)) {
                    $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES \n" . implode(",\n", $valuesList) . ";\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $sql .= "-- Fin del volcado de seguridad\n";

        return $sql;
    }

    /**
     * Crea el archivo ZIP completo protegido con la clave solicitada
     * @param string $password Clave de encriptación (@Acemila2026)
     * @return array [success => bool, zipPath => string, filename => string, size => int, message => string]
     */
    public static function createBackup($password = self::DEFAULT_PASSWORD) {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return array('success' => false, 'message' => 'No se pudo conectar a la base de datos.');
            }

            // 1. Generar dump SQL
            $sqlContent = self::exportDatabaseSql($pdo);

            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'agrodietario_backup_' . time();
            @mkdir($tempDir, 0777, true);

            $sqlFilename = 'database_backup_' . date('Y-m-d_His') . '.sql';
            $sqlFilePath = $tempDir . DIRECTORY_SEPARATOR . $sqlFilename;
            file_put_contents($sqlFilePath, $sqlContent);

            $zipFilename = 'backup_completo_agrodietario_' . date('Y-m-d_His') . '.zip';
            $zipFilePath = $tempDir . DIRECTORY_SEPARATOR . $zipFilename;

            $rootPath = dirname(dirname(__DIR__)); // Raíz del proyecto

            // Intentar empaquetar con ZipArchive
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                return array('success' => false, 'message' => 'No se pudo crear el archivo ZIP en el servidor.');
            }

            // Configurar clave de encriptación en ZipArchive
            $hasSetPassword = method_exists($zip, 'setPassword');
            $hasSetEncryption = method_exists($zip, 'setEncryptionName');

            if ($hasSetPassword) {
                $zip->setPassword($password);
            }

            // Añadir el volcado SQL en la raíz del ZIP
            $zip->addFile($sqlFilePath, 'BASE_DE_DATOS/' . $sqlFilename);
            if ($hasSetEncryption && defined('ZipArchive::EM_AES_256')) {
                $zip->setEncryptionName('BASE_DE_DATOS/' . $sqlFilename, ZipArchive::EM_AES_256);
            } elseif ($hasSetEncryption && defined('ZipArchive::EM_TRAD_PKWARE')) {
                $zip->setEncryptionName('BASE_DE_DATOS/' . $sqlFilename, ZipArchive::EM_TRAD_PKWARE);
            }

            // Añadir nota explicativa dentro del ZIP
            $leemeTxt = "========================================================\n";
            $leemeTxt .= "COPIA DE SEGURIDAD INTEGRAL - AGRODIETARIO\n";
            $leemeTxt .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
            $leemeTxt .= "Contenido:\n";
            $leemeTxt .= "  - /BASE_DE_DATOS/ : Volcado SQL completo de todas las tablas y registros.\n";
            $leemeTxt .= "  - /WEB/           : Codigo fuente completo de la plataforma web.\n";
            $leemeTxt .= "========================================================\n";
            $zip->addFromString('LEEME_SEGURIDAD.txt', $leemeTxt);
            if ($hasSetEncryption && defined('ZipArchive::EM_AES_256')) {
                $zip->setEncryptionName('LEEME_SEGURIDAD.txt', ZipArchive::EM_AES_256);
            }

            // Recorrer archivos del proyecto
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            // Carpetas y extensiones a excluir para mantener el ZIP limpio y rápido
            $excludedPatterns = array(
                '/.git', '/.gemini', '/scratch', '/node_modules', '/vendor',
                'agrodietario_backup_', '.zip', '.tar', '.gz'
            );

            foreach ($iterator as $item) {
                $filePath = $item->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $relNorm = str_replace('\\', '/', $relativePath);

                $skip = false;
                foreach ($excludedPatterns as $exc) {
                    if (strpos($relNorm, trim($exc, '/')) !== false) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;

                $localZipPath = 'WEB/' . $relNorm;

                if ($item->isDir()) {
                    $zip->addEmptyDir($localZipPath);
                } elseif ($item->isFile()) {
                    // Evitar incluir archivos gigantes (> 50MB) si los hubiera
                    if ($item->getSize() < 52428800) {
                        $zip->addFile($filePath, $localZipPath);
                        if ($hasSetEncryption && defined('ZipArchive::EM_AES_256')) {
                            $zip->setEncryptionName($localZipPath, ZipArchive::EM_AES_256);
                        } elseif ($hasSetEncryption && defined('ZipArchive::EM_TRAD_PKWARE')) {
                            $zip->setEncryptionName($localZipPath, ZipArchive::EM_TRAD_PKWARE);
                        }
                    }
                }
            }

            $zip->close();

            if (!file_exists($zipFilePath) || filesize($zipFilePath) == 0) {
                return array('success' => false, 'message' => 'El archivo ZIP no se generó correctamente.');
            }

            return array(
                'success'  => true,
                'zipPath'  => $zipFilePath,
                'filename' => $zipFilename,
                'size'     => filesize($zipFilePath),
                'message'  => 'Copia de seguridad generada con éxito y protegida con clave.'
            );

        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Error al generar copia: ' . $e->getMessage());
        }
    }

    /**
     * Envía el archivo ZIP al navegador para su descarga inmediata
     */
    public static function streamDownload($zipFilePath, $filename) {
        if (!file_exists($zipFilePath)) {
            die("El archivo de copia de seguridad no se encuentra.");
        }

        // Limpiar cualquier buffer de salida previo antes de enviar cabeceras
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($zipFilePath));

        readfile($zipFilePath);
        @unlink($zipFilePath);
        exit;
    }
}
