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
     * Localiza e incluye inc/mailer.php de moratalla-murcia-2026 si está disponible.
     */
    public static function loadWebMailer()
    {
        if (function_exists('getMailConfig') && function_exists('smtpSendRaw')) {
            return true;
        }
        $candidates = array(
            dirname(dirname(__DIR__)) . '/inc/mailer.php',
            dirname(__DIR__) . '/../inc/mailer.php',
            'C:/xampp/htdocs/Moratalla-Murcia-2026/inc/mailer.php',
        );
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
        if (!self::loadWebMailer()) {
            $errorDetail = 'No se encontró inc/mailer.php de moratalla-murcia.com.';
            return false;
        }
        $cfg = getMailConfig($pdo);
        if (empty($toEmail)) {
            $errorDetail = 'No hay dirección de correo electrónico configurada para el usuario ni admin_email.';
            return false;
        }

        $fromEmail = $cfg['smtp_user'];
        $fromName  = 'Control de Combustible - moratalla-murcia.com';

        try {
            smtpSendRaw(
                $cfg['smtp_host'],
                $cfg['smtp_port'],
                $cfg['smtp_encryption'],
                $cfg['smtp_user'],
                $cfg['smtp_pass'],
                $fromEmail,
                $fromName,
                $toEmail,
                $fromEmail,
                $fromName,
                $subject,
                $plainBody,
                $htmlBody
            );
            return true;
        } catch (Exception $e1) {
            try {
                $altPort = ($cfg['smtp_port'] === 465) ? 587 : 465;
                $altEnc  = ($altPort === 465) ? 'ssl' : 'tls';
                smtpSendRaw(
                    $cfg['smtp_host'],
                    $altPort,
                    $altEnc,
                    $cfg['smtp_user'],
                    $cfg['smtp_pass'],
                    $fromEmail,
                    $fromName,
                    $toEmail,
                    $fromEmail,
                    $fromName,
                    $subject,
                    $plainBody,
                    $htmlBody
                );
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
        if (!self::loadWebMailer()) {
            $errorDetail = 'Módulo mailer no disponible.';
            return false;
        }
        $cfg = getMailConfig($pdo);
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
        if (!self::loadWebMailer()) {
            $errorDetail = 'Módulo mailer no disponible.';
            return false;
        }
        $cfg = getMailConfig($pdo);
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
