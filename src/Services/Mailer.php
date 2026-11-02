<?php
/**
 * Servicio de Envío de Correos Electrónicos con Cifrado AES-256-CBC + HMAC-SHA256
 * Cliente SMTP Autenticado Nativo (Compatible con IONOS, Gmail, etc.)
 */

if (!defined('MM_CRYPTO_SECRET')) {
    $envKey = function_exists('env') ? env('APP_CRYPTO_KEY', '') : '';
    define('MM_CRYPTO_SECRET', $envKey ?: 'MM2026_Moratalla_CryptoKey_#9821_SecureVault');
}

// Credenciales por defecto pre-encriptadas con AES-256-CBC + HMAC-SHA256 (Ionos info@moratalla-murcia.com)
define('AG_DEFAULT_SMTP_USER_ENC', 'ENCv1:uUbSu/e83SQAXs6GOGscogvq22TPx8ZTkII0gYm8BhKIGT/NlcUv4xvpsISxCyTcPtK4O3Nc9zBkur0U3A4BNvI9QgsvpuMpYk+VyM8/UgI=');
define('AG_DEFAULT_SMTP_PASS_ENC', 'ENCv1:YTXXzOvpW6mbCB/ur824nHy/8S9YDRoEMocyVivDSOEGdBMFabHCEzSkvCZepozRXBYPTZA5L7AJ/Bxdq1BE+RVuijqbaI7pCH74r/hrz80=');

class Mailer {

    /**
     * Encripta texto sensible usando AES-256-CBC + HMAC-SHA256
     */
    public static function encrypt($plainText) {
        if ($plainText === null || $plainText === '') return '';
        if (strpos($plainText, 'ENCv1:') === 0) return $plainText;

        $key = hash('sha256', MM_CRYPTO_SECRET, true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $mac = hash_hmac('sha256', $iv . $cipher, $key, true);
        return 'ENCv1:' . base64_encode($iv . $mac . $cipher);
    }

    /**
     * Desencripta un valor previamente encriptado
     */
    public static function decrypt($cipherText) {
        if ($cipherText === null || $cipherText === '') return '';
        if (strpos($cipherText, 'ENCv1:') !== 0) return $cipherText;

        $raw = base64_decode(substr($cipherText, 6), true);
        if ($raw === false || strlen($raw) <= 48) return '';

        $key = hash('sha256', MM_CRYPTO_SECRET, true);
        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $cipher = substr($raw, 48);

        $calcMac = hash_hmac('sha256', $iv . $cipher, $key, true);
        if (!hash_equals($mac, $calcMac)) return '';

        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return ($plain !== false) ? $plain : '';
    }

    /**
     * Obtiene la configuración de correo activa
     */
    public static function getConfig() {
        $db = null;
        try {
            $db = Database::getConnection();
        } catch (Exception $e) {}

        $config = array(
            'smtp_host'       => 'smtp.ionos.es',
            'smtp_port'       => 587,
            'smtp_encryption' => 'tls',
            'smtp_user'       => self::decrypt(AG_DEFAULT_SMTP_USER_ENC),
            'smtp_pass'       => self::decrypt(AG_DEFAULT_SMTP_PASS_ENC),
            'email_remitente' => 'info@moratalla-murcia.com',
            'nombre_remitente'=> 'AgroDietario - Gestión Agrícola',
            'email_comunidad' => 'alharabemoratalla@gmail.com'
        );

        if ($db) {
            try {
                $stmt = $db->query("SELECT * FROM configuracion_web LIMIT 1");
                if ($row = $stmt->fetch()) {
                    if (!empty($row['smtp_host'])) $config['smtp_host'] = $row['smtp_host'];
                    if (!empty($row['smtp_port'])) $config['smtp_port'] = (int)$row['smtp_port'];
                    if (!empty($row['smtp_encryption'])) $config['smtp_encryption'] = strtolower($row['smtp_encryption']);
                    if (!empty($row['smtp_user_enc'])) {
                        $decUser = self::decrypt($row['smtp_user_enc']);
                        if ($decUser) $config['smtp_user'] = $decUser;
                    }
                    if (!empty($row['smtp_pass_enc'])) {
                        $decPass = self::decrypt($row['smtp_pass_enc']);
                        if ($decPass) $config['smtp_pass'] = $decPass;
                    }
                    if (!empty($row['email_remitente'])) $config['email_remitente'] = $row['email_remitente'];
                    if (!empty($row['email_comunidad'])) $config['email_comunidad'] = $row['email_comunidad'];
                }
            } catch (Exception $e) {}
        }

        return $config;
    }

    /**
     * Lee la respuesta de un servidor SMTP
     */
    private static function smtpRead($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    }

    /**
     * Envía comando SMTP y verifica código
     */
    private static function smtpCmd($socket, $cmd, $expected = array(250)) {
        if ($cmd !== null) {
            fwrite($socket, $cmd . "\r\n");
        }
        $resp = self::smtpRead($socket);
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, (array)$expected, true)) {
            throw new Exception("Error SMTP (esperado " . implode('/', (array)$expected) . ", recibido $code): " . trim($resp));
        }
        return $resp;
    }

    /**
     * Envía un correo electrónico autenticado con soporte de adjuntos
     */
    public static function send($toEmail, $subject, $htmlBody, $attachments = array(), $replyToEmail = null, $replyToName = null) {
        $cfg = self::getConfig();
        $host = $cfg['smtp_host'];
        $port = $cfg['smtp_port'];
        $enc  = $cfg['smtp_encryption'];
        $user = $cfg['smtp_user'];
        $pass = $cfg['smtp_pass'];
        $fromEmail = $cfg['email_remitente'] ?: 'info@moratalla-murcia.com';
        $fromName  = $cfg['nombre_remitente'] ?: 'AgroDietario';

        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create(array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            )
        ));

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new Exception("No se pudo conectar al servidor SMTP $remote ($errno: $errstr)");
        }

        stream_set_timeout($socket, 15);

        try {
            self::smtpCmd($socket, null, array(220));
            $ehloHost = isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : 'moratalla-murcia.com';
            self::smtpCmd($socket, "EHLO " . $ehloHost, array(250));

            if ($enc === 'tls') {
                self::smtpCmd($socket, "STARTTLS", array(220));
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
                    throw new Exception("Fallo al negociar cifrado STARTTLS con el servidor SMTP.");
                }
                self::smtpCmd($socket, "EHLO " . $ehloHost, array(250));
            }

            self::smtpCmd($socket, "AUTH LOGIN", array(334));
            self::smtpCmd($socket, base64_encode($user), array(334));
            self::smtpCmd($socket, base64_encode($pass), array(235));

            self::smtpCmd($socket, "MAIL FROM:<" . $fromEmail . ">", array(250));

            $recipients = array_unique(array_filter(array_map('trim', explode(',', $toEmail))));
            foreach ($recipients as $rcpt) {
                self::smtpCmd($socket, "RCPT TO:<" . $rcpt . ">", array(250, 251));
            }

            self::smtpCmd($socket, "DATA", array(354));

            $boundary = "=_AG_Bound_" . md5(uniqid((string)mt_rand(), true));
            $encSub = "=?UTF-8?B?" . base64_encode($subject) . "?=";
            $encFrom = "=?UTF-8?B?" . base64_encode($fromName) . "?=";

            $headers = array();
            $headers[] = "Date: " . date('r');
            $headers[] = "From: {$encFrom} <{$fromEmail}>";
            $headers[] = "To: <" . implode('>, <', $recipients) . ">";
            if (!empty($replyToEmail)) {
                $encRepName = $replyToName ? "=?UTF-8?B?" . base64_encode($replyToName) . "?=" : $encFrom;
                $headers[] = "Reply-To: {$encRepName} <{$replyToEmail}>";
            }
            $headers[] = "Subject: {$encSub}";
            $headers[] = "Message-ID: <" . uniqid('ag_', true) . "@moratalla-murcia.com>";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
            $headers[] = "X-Mailer: AgroDietario-SMTP/2.0";

            $messageData = implode("\r\n", $headers) . "\r\n\r\n";

            // Parte HTML
            $messageData .= "--{$boundary}\r\n";
            $messageData .= "Content-Type: text/html; charset=UTF-8\r\n";
            $messageData .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $messageData .= chunk_split(base64_encode($htmlBody)) . "\r\n";

            // Adjuntos (PDF, imágenes, documentos, etc.)
            if (!empty($attachments)) {
                foreach ($attachments as $att) {
                    $attName = 'adjunto.pdf';
                    $attData = '';
                    $attMime = 'application/pdf';

                    if (is_string($att)) {
                        if (file_exists($att)) {
                            $attName = basename($att);
                            $attData = file_get_contents($att);
                            if (function_exists('mime_content_type')) {
                                $detected = @mime_content_type($att);
                                if ($detected) $attMime = $detected;
                            }
                        }
                    } elseif (is_array($att)) {
                        if (!empty($att['name'])) {
                            $attName = $att['name'];
                        }
                        if (isset($att['path']) && file_exists($att['path'])) {
                            $attData = file_get_contents($att['path']);
                            if (empty($att['name'])) {
                                $attName = basename($att['path']);
                            }
                        } elseif (isset($att['data'])) {
                            $attData = $att['data'];
                        }
                        if (!empty($att['mime'])) {
                            $attMime = $att['mime'];
                        }
                    }

                    if (!empty($attData)) {
                        $encAttName = "=?UTF-8?B?" . base64_encode($attName) . "?=";
                        $messageData .= "--{$boundary}\r\n";
                        $messageData .= "Content-Type: {$attMime}; name=\"{$encAttName}\"\r\n";
                        $messageData .= "Content-Disposition: attachment; filename=\"{$attName}\"\r\n";
                        $messageData .= "Content-Transfer-Encoding: base64\r\n\r\n";
                        $messageData .= chunk_split(base64_encode($attData)) . "\r\n";
                    }
                }
            }

            $messageData .= "--{$boundary}--\r\n";
            $messageData = str_replace("\r\n.", "\r\n..", $messageData);
            self::smtpCmd($socket, $messageData . "\r\n.", array(250));

            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            return array('success' => true, 'message' => 'Correo enviado exitosamente.');

        } catch (Exception $e) {
            @fclose($socket);
            return array('success' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Prueba la conexión SMTP
     */
    public static function testConnection() {
        $cfg = self::getConfig();
        $testTo = $cfg['email_remitente'];
        $html = "<h3>Prueba de Conexión SMTP - AgroDietario</h3><p>Este correo confirma que el servidor SMTP autenticado (<strong>{$cfg['smtp_host']}</strong>) con la cuenta <strong>{$cfg['email_remitente']}</strong> está funcionando correctamente.</p><p>Fecha: " . date('d/m/Y H:i:s') . "</p>";
        return self::send($testTo, "Prueba SMTP - AgroDietario (" . date('d/m/Y H:i') . ")", $html);
    }
}
