<?php
/**
 * src/FuelMailer.php
 * Envía correos de aviso de repostaje y controles periódicos de vehículos
 * reutilizando la configuración SMTP encriptada de moratalla-murcia.com (inc/mailer.php).
 * Compatible con PHP 5.6+.
 */

class FuelMailer
{
    /**
     * Localiza e incluye inc/mailer.php de moratalla-murcia-2026 si está disponible;
     * si no, carga la configuración directamente desde la tabla `settings`.
     */
    public static function loadWebMailer()
    {
        if (function_exists('getMailConfig') && function_exists('smtpSendRaw')) {
            return true;
        }
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : '';
        $candidates = array(
            dirname(dirname(__DIR__)) . '/inc/mailer.php',
            dirname(dirname(dirname(__DIR__))) . '/inc/mailer.php',
            dirname(__DIR__) . '/../inc/mailer.php',
            'C:/xampp/htdocs/Moratalla-Murcia-2026/inc/mailer.php',
        );
        if ($docRoot !== '') {
            $candidates[] = $docRoot . '/inc/mailer.php';
            $candidates[] = $docRoot . '/moratalla/inc/mailer.php';
            $candidates[] = dirname($docRoot) . '/inc/mailer.php';
        }
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (function_exists('getMailConfig') && function_exists('smtpSendRaw')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Desencripta valores ENCv1 de la tabla `settings` de moratalla-murcia-2026.
     */
    private static function decryptSetting($cipherText)
    {
        if (function_exists('decryptSecret')) {
            return decryptSecret($cipherText);
        }
        if ($cipherText === null || $cipherText === '') return '';
        if (strpos($cipherText, 'ENCv1:') !== 0) return $cipherText;
        $raw = base64_decode(substr($cipherText, 6), true);
        if ($raw === false || strlen($raw) <= 48) return '';
        $secret = defined('MM_CRYPTO_SECRET') ? MM_CRYPTO_SECRET : 'MM2026_Moratalla_CryptoKey_#9821_SecureVault';
        $key = hash('sha256', $secret, true);
        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $cipher = substr($raw, 48);
        $calcMac = hash_hmac('sha256', $iv . $cipher, $key, true);
        if (!hash_equals($mac, $calcMac)) return '';
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return ($plain !== false) ? $plain : '';
    }

    /**
     * Obtiene la configuración SMTP desde inc/mailer.php o directamente de la tabla `settings`.
     */
    public static function getConfig($pdo)
    {
        if (self::loadWebMailer() && function_exists('getMailConfig')) {
            return getMailConfig($pdo);
        }
        $settings = array();
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM `settings`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {}

        $defUserEnc = 'ENCv1:uUbSu/e83SQAXs6GOGscogvq22TPx8ZTkII0gYm8BhKIGT/NlcUv4xvpsISxCyTcPtK4O3Nc9zBkur0U3A4BNvI9QgsvpuMpYk+VyM8/UgI=';
        $defPassEnc = 'ENCv1:YTXXzOvpW6mbCB/ur824nHy/8S9YDRoEMocyVivDSOEGdBMFabHCEzSkvCZepozRXBYPTZA5L7AJ/Bxdq1BE+RVuijqbaI7pCH74r/hrz80=';

        $smtpUser = self::decryptSetting(!empty($settings['smtp_user_enc']) ? $settings['smtp_user_enc'] : $defUserEnc);
        $smtpPass = self::decryptSetting(!empty($settings['smtp_pass_enc']) ? $settings['smtp_pass_enc'] : $defPassEnc);
        $adminEmail = !empty($settings['admin_email']) ? trim($settings['admin_email']) : $smtpUser;

        return array(
            'smtp_host'       => !empty($settings['smtp_host']) ? $settings['smtp_host'] : 'smtp.ionos.es',
            'smtp_port'       => !empty($settings['smtp_port']) ? (int)$settings['smtp_port'] : 587,
            'smtp_encryption' => !empty($settings['smtp_encryption']) ? strtolower($settings['smtp_encryption']) : 'tls',
            'smtp_user'       => $smtpUser,
            'smtp_pass'       => $smtpPass,
            'admin_email'     => $adminEmail,
        );
    }

    private static function smtpFallbackRead($socket)
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    }

    private static function smtpFallbackCmd($socket, $cmd, $expected)
    {
        if ($cmd !== null) fwrite($socket, $cmd . "\r\n");
        $resp = self::smtpFallbackRead($socket);
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, (array)$expected, true)) {
            throw new Exception("Error SMTP ($code): " . trim($resp));
        }
        return $resp;
    }

    private static function smtpFallbackSend($host, $port, $enc, $user, $pass, $fromEmail, $fromName, $toEmail, $subject, $plainBody, $htmlBody)
    {
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)));
        $errno = 0; $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) throw new Exception("No se pudo conectar a $remote ($errno: $errstr)");
        stream_set_timeout($socket, 12);
        try {
            self::smtpFallbackCmd($socket, null, array(220));
            $ehlo = !empty($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : 'moratalla-murcia.com';
            self::smtpFallbackCmd($socket, "EHLO " . $ehlo, array(250));
            if ($enc === 'tls') {
                self::smtpFallbackCmd($socket, "STARTTLS", array(220));
                $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (!@stream_socket_enable_crypto($socket, true, $method)) {
                    throw new Exception("No se pudo activar STARTTLS.");
                }
                self::smtpFallbackCmd($socket, "EHLO " . $ehlo, array(250));
            }
            self::smtpFallbackCmd($socket, "AUTH LOGIN", array(334));
            self::smtpFallbackCmd($socket, base64_encode($user), array(334));
            self::smtpFallbackCmd($socket, base64_encode($pass), array(235));
            self::smtpFallbackCmd($socket, "MAIL FROM:<" . $fromEmail . ">", array(250));
            $rcpts = array_unique(array_filter(array_map('trim', explode(',', $toEmail))));
            foreach ($rcpts as $r) self::smtpFallbackCmd($socket, "RCPT TO:<" . $r . ">", array(250, 251));
            self::smtpFallbackCmd($socket, "DATA", array(354));
            $boundary = "=_MM_Fuel_" . md5(uniqid((string)mt_rand(), true));
            $headers = array(
                "Date: " . date('r'),
                "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
                "To: <" . implode('>, <', $rcpts) . ">",
                "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            );
            $msg = implode("\r\n", $headers) . "\r\n\r\n"
                . "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($plainBody)) . "\r\n"
                . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($htmlBody)) . "\r\n--{$boundary}--\r\n";
            $msg = str_replace("\r\n.", "\r\n..", $msg);
            self::smtpFallbackCmd($socket, $msg . "\r\n.", array(250));
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            return true;
        } catch (Exception $e) {
            @fclose($socket);
            throw $e;
        }
    }

    /**
     * Obtiene el destinatario principal del usuario (o admin_email de settings como respaldo).
     */
    public static function resolveRecipientEmail($pdo, $userRow, $cfg)
    {
        if (is_array($userRow) && !empty($userRow['email'])) {
            $candidate = trim((string)$userRow['email']);
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }
        if (!empty($cfg['admin_email']) && filter_var($cfg['admin_email'], FILTER_VALIDATE_EMAIL)) {
            return $cfg['admin_email'];
        }
        if (!empty($cfg['smtp_user']) && filter_var($cfg['smtp_user'], FILTER_VALIDATE_EMAIL)) {
            return $cfg['smtp_user'];
        }
        return '';
    }

    /**
     * Envía un correo usando smtpSendRaw con respaldo automático a SSL 465 si TLS 587 falla.
     */
    public static function sendHtmlEmail($pdo, $toEmail, $subject, $plainBody, $htmlBody, &$errorDetail = null)
    {
        $cfg = self::getConfig($pdo);
        if (empty($toEmail)) {
            $errorDetail = 'No hay dirección de correo electrónico configurada para el usuario ni admin_email.';
            return false;
        }

        $fromEmail = $cfg['smtp_user'];
        $fromName  = 'Control de Combustible - moratalla-murcia.com';

        $useWebFn = function_exists('smtpSendRaw');
        try {
            if ($useWebFn) {
                smtpSendRaw(
                    $cfg['smtp_host'], $cfg['smtp_port'], $cfg['smtp_encryption'],
                    $cfg['smtp_user'], $cfg['smtp_pass'], $fromEmail, $fromName,
                    $toEmail, $fromEmail, $fromName, $subject, $plainBody, $htmlBody
                );
            } else {
                self::smtpFallbackSend(
                    $cfg['smtp_host'], $cfg['smtp_port'], $cfg['smtp_encryption'],
                    $cfg['smtp_user'], $cfg['smtp_pass'], $fromEmail, $fromName,
                    $toEmail, $subject, $plainBody, $htmlBody
                );
            }
            return true;
        } catch (Exception $e1) {
            try {
                $altPort = ($cfg['smtp_port'] === 465) ? 587 : 465;
                $altEnc  = ($altPort === 465) ? 'ssl' : 'tls';
                if ($useWebFn) {
                    smtpSendRaw(
                        $cfg['smtp_host'], $altPort, $altEnc,
                        $cfg['smtp_user'], $cfg['smtp_pass'], $fromEmail, $fromName,
                        $toEmail, $fromEmail, $fromName, $subject, $plainBody, $htmlBody
                    );
                } else {
                    self::smtpFallbackSend(
                        $cfg['smtp_host'], $altPort, $altEnc,
                        $cfg['smtp_user'], $cfg['smtp_pass'], $fromEmail, $fromName,
                        $toEmail, $subject, $plainBody, $htmlBody
                    );
                }
                return true;
            } catch (Exception $e2) {
                $errorDetail = $e1->getMessage() . ' | Respaldo: ' . $e2->getMessage();
                return false;
            }
        }
    }

    /**
     * Envía correo al registrar un repostaje, incluyendo las tareas en alerta (<1000 km o <=15 días).
     */
    public static function sendRefuelNotification($pdo, $userRow, $vehiculoNombre, $repData, $alertTasks, &$errorDetail = null)
    {
        $cfg = self::getConfig($pdo);
        $toEmail = self::resolveRecipientEmail($pdo, $userRow, $cfg);
        if (empty($toEmail)) {
            $errorDetail = 'Usuario sin correo configurado.';
            return false;
        }

        $username = isset($userRow['username']) ? $userRow['username'] : 'Usuario';
        $fecha    = isset($repData['fecha']) ? $repData['fecha'] : date('Y-m-d');
        $km       = isset($repData['km_odometro']) ? number_format((float)$repData['km_odometro'], 0, ',', '.') : '0';
        $litros   = isset($repData['litros']) ? number_format((float)$repData['litros'], 2, ',', '.') : '0,00';
        $precio   = isset($repData['precio_litro']) ? number_format((float)$repData['precio_litro'], 3, ',', '.') : '0,000';
        $importe  = isset($repData['importe_total']) ? number_format((float)$repData['importe_total'], 2, ',', '.') : '0,00';
        $estacion = !empty($repData['estacion_servicio']) ? $repData['estacion_servicio'] : 'No especificada';

        $hasAlerts = is_array($alertTasks) && count($alertTasks) > 0;
        $subject = $hasAlerts
            ? "Repostaje registrado + AVISO de mantenimiento ({$vehiculoNombre})"
            : "Repostaje registrado: {$vehiculoNombre} ({$fecha})";

        $plain = "Hola {$username},\r\n\r\n"
            . "Se ha registrado un nuevo repostaje en Control de Combustible:\r\n"
            . "- Vehículo: {$vehiculoNombre}\r\n"
            . "- Fecha: {$fecha}\r\n"
            . "- Odómetro: {$km} km\r\n"
            . "- Litros: {$litros} L\r\n"
            . "- Precio/L: {$precio} €\r\n"
            . "- Importe Total: {$importe} €\r\n"
            . "- Estación: {$estacion}\r\n\r\n";

        $alertsHtml = '';
        if ($hasAlerts) {
            $plain .= "ATENCIÓN - TAREAS DE MANTENIMIENTO PRÓXIMAS O VENCIDAS (< 1000 km o <= 15 días):\r\n";
            $alertsHtml .= "<div style='margin-top:18px; padding:14px; background:#fef3c7; border-left:4px solid #d97706; border-radius:6px;'>"
                . "<strong style='color:#92400e; display:block; margin-bottom:8px;'>⚠️ Avisos de Mantenimiento Próximos / Vencidos</strong><ul style='margin:0; padding-left:18px; color:#78350f;'>";
            foreach ($alertTasks as $t) {
                $tDesc = isset($t['descripcion']) ? $t['descripcion'] : '';
                $tVeh  = isset($t['vehiculo_nombre']) ? $t['vehiculo_nombre'] : $vehiculoNombre;
                $tMot  = isset($t['motivo_alerta']) ? $t['motivo_alerta'] : '';
                $plain .= "  * [{$tVeh}] {$tDesc}: {$tMot}\r\n";
                $alertsHtml .= "<li><strong>" . htmlspecialchars($tVeh) . "</strong> — " . htmlspecialchars($tDesc) . ": " . htmlspecialchars($tMot) . "</li>";
            }
            $alertsHtml .= "</ul></div>";
        }

        $html = "<!DOCTYPE html><html lang='es'><body style='font-family:Arial,sans-serif; background:#f4f6f5; padding:20px; color:#1e293b;'>"
            . "<div style='max-width:600px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #cbd5e1;'>"
            . "<div style='background:#0f766e; color:#ffffff; padding:16px 22px;'>"
            . "<h2 style='margin:0; font-size:18px;'>⛽ Repostaje Registrado - Control de Combustible</h2></div>"
            . "<div style='padding:20px;'>"
            . "<p style='margin-top:0;'>Hola <strong>" . htmlspecialchars($username) . "</strong>, se ha guardado el siguiente repostaje:</p>"
            . "<table style='width:100%; border-collapse:collapse; background:#f8fafc; border-radius:8px;'>"
            . "<tr><td style='padding:8px 12px; font-weight:bold;'>Vehículo:</td><td style='padding:8px 12px;'>" . htmlspecialchars($vehiculoNombre) . "</td></tr>"
            . "<tr><td style='padding:8px 12px; font-weight:bold;'>Fecha:</td><td style='padding:8px 12px;'>" . htmlspecialchars($fecha) . "</td></tr>"
            . "<tr><td style='padding:8px 12px; font-weight:bold;'>Odómetro:</td><td style='padding:8px 12px;'>{$km} km</td></tr>"
            . "<tr><td style='padding:8px 12px; font-weight:bold;'>Litros / Precio:</td><td style='padding:8px 12px;'>{$litros} L a {$precio} €/L</td></tr>"
            . "<tr><td style='padding:8px 12px; font-weight:bold;'>Importe Total:</td><td style='padding:8px 12px; font-weight:bold; color:#0f766e;'>{$importe} €</td></tr>"
            . "<tr><td style='padding:8px 12px; font-weight:bold;'>Estación:</td><td style='padding:8px 12px;'>" . htmlspecialchars($estacion) . "</td></tr>"
            . "</table>"
            . $alertsHtml
            . "<p style='font-size:12px; color:#64748b; margin-top:18px; margin-bottom:0;'>Enviado automáticamente desde moratalla-murcia.com</p>"
            . "</div></div></body></html>";

        return self::sendHtmlEmail($pdo, $toEmail, $subject, $plain, $html, $errorDetail);
    }

    /**
     * Envía correo resumen con las tareas pendientes próximas (<1000 km o <=15 días) o vencidas.
     */
    public static function sendTaskAlertsEmail($pdo, $userRow, $alertTasks, &$errorDetail = null)
    {
        $cfg = self::getConfig($pdo);
        $toEmail = self::resolveRecipientEmail($pdo, $userRow, $cfg);
        if (empty($toEmail)) {
            $errorDetail = 'Usuario sin correo configurado.';
            return false;
        }
        if (empty($alertTasks)) {
            $errorDetail = 'No hay tareas próximas ni vencidas para avisar.';
            return false;
        }

        $username = isset($userRow['username']) ? $userRow['username'] : 'Usuario';
        $count = count($alertTasks);
        $subject = "Aviso de Mantenimiento ({$count} tarea(s) próxima(s) o vencida(s)) - moratalla-murcia.com";

        $plain = "Hola {$username},\r\n\r\n"
            . "Tienes {$count} tarea(s) de mantenimiento de vehículos a menos de 1.000 km o 15 días de su vencimiento:\r\n\r\n";

        $rowsHtml = '';
        foreach ($alertTasks as $t) {
            $vName = isset($t['vehiculo_nombre']) ? $t['vehiculo_nombre'] : 'Vehículo';
            $desc  = isset($t['descripcion']) ? $t['descripcion'] : '';
            $mot   = isset($t['motivo_alerta']) ? $t['motivo_alerta'] : '';
            $plain .= "- [{$vName}] {$desc} -> {$mot}\r\n";
            $rowsHtml .= "<tr>"
                . "<td style='padding:8px 10px; border-bottom:1px solid #e2e8f0;'><strong>" . htmlspecialchars($vName) . "</strong></td>"
                . "<td style='padding:8px 10px; border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($desc) . "</td>"
                . "<td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; color:#b45309; font-weight:bold;'>" . htmlspecialchars($mot) . "</td>"
                . "</tr>";
        }

        $html = "<!DOCTYPE html><html lang='es'><body style='font-family:Arial,sans-serif; background:#f4f6f5; padding:20px; color:#1e293b;'>"
            . "<div style='max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #cbd5e1;'>"
            . "<div style='background:#b45309; color:#ffffff; padding:16px 22px;'>"
            . "<h2 style='margin:0; font-size:18px;'>🛠️ Avisos de Mantenimiento Periódico</h2></div>"
            . "<div style='padding:20px;'>"
            . "<p style='margin-top:0;'>Hola <strong>" . htmlspecialchars($username) . "</strong>, las siguientes tareas requieren atención (&lt; 1.000 km o &le; 15 días):</p>"
            . "<table style='width:100%; border-collapse:collapse; font-size:13px;'>"
            . "<thead><tr style='background:#f8fafc; text-align:left;'>"
            . "<th style='padding:8px 10px;'>Vehículo</th><th style='padding:8px 10px;'>Tarea</th><th style='padding:8px 10px;'>Estado / Margen</th>"
            . "</tr></thead><tbody>{$rowsHtml}</tbody></table>"
            . "<p style='font-size:12px; color:#64748b; margin-top:18px; margin-bottom:0;'>Enviado automáticamente desde moratalla-murcia.com</p>"
            . "</div></div></body></html>";

        return self::sendHtmlEmail($pdo, $toEmail, $subject, $plain, $html, $errorDetail);
    }
}
