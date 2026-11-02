<?php
/**
 * Servicio de Generación y Validación de Captcha Nativo
 * Compatible con PHP 5.6, 7.x, 8.x
 */

class Captcha {
    /**
     * Genera un código aleatorio alfanumérico legible
     */
    public static function generateCode($length = 5) {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[mt_rand(0, $max)];
        }
        $_SESSION['captcha_code'] = $code;
        return $code;
    }

    /**
     * Genera la imagen del Captcha codificada en Data-URI (base64)
     */
    public static function generateBase64Image() {
        $code = self::generateCode(5);

        $width = 150;
        $height = 45;

        // Crear lienzo si GD está disponible
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor($width, $height);

            $bgColor = imagecolorallocate($image, 240, 244, 248);
            $textColor = imagecolorallocate($image, 34, 112, 61);
            $noiseColor = imagecolorallocate($image, 170, 190, 180);
            $lineColor = imagecolorallocate($image, 130, 160, 140);

            imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

            // Líneas de ruido de seguridad
            for ($i = 0; $i < 5; $i++) {
                imageline($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $lineColor);
            }

            // Puntos de ruido
            for ($i = 0; $i < 100; $i++) {
                imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $noiseColor);
            }

            // Dibujar caracteres
            $len = strlen($code);
            for ($i = 0; $i < $len; $i++) {
                $char = $code[$i];
                $x = 20 + ($i * 24);
                $y = mt_rand(10, 16);
                imagechar($image, 5, $x, $y, $char, $textColor);
            }

            ob_start();
            imagepng($image);
            $imageData = ob_get_clean();
            imagedestroy($image);

            return 'data:image/png;base64,' . base64_encode($imageData);
        } else {
            // Fallback SVG si GD no estuviese habilitada
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="45" style="background:#f0f4f8;border-radius:4px;">';
            $svg .= '<text x="25" y="30" font-family="monospace" font-size="24" font-weight="bold" fill="#22703d" letter-spacing="4">' . $code . '</text>';
            $svg .= '</svg>';
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        }
    }

    /**
     * Valida el código captcha enviado por el usuario
     */
    public static function validate($input) {
        if (empty($input) || empty($_SESSION['captcha_code'])) {
            return false;
        }

        $isValid = (strtoupper(trim($input)) === strtoupper($_SESSION['captcha_code']));
        // Destruir código para un solo uso
        unset($_SESSION['captcha_code']);
        return $isValid;
    }
}
