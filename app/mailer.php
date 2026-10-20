<?php
declare(strict_types=1);

/**
 * Sends an appointment notification. Email errors are deliberately contained:
 * callers must never roll back a saved booking because mail delivery failed.
 *
 * Supported events: request_received, confirmed, cancelled.
 */
function denticoSendAppointmentEmail(array $appointment, string $event): bool
{
    try {
        return denticoSendAppointmentEmailInternal($appointment, $event);
    } catch (Throwable $exception) {
        error_log('Dentico mail notification failed: ' . get_class($exception));
        return false;
    }
}

function denticoSendAppointmentEmailInternal(array $appointment, string $event): bool
{
    if (!in_array($event, ['request_received', 'confirmed', 'cancelled'], true)) {
        error_log('Dentico mail: unsupported notification event.');
        return false;
    }

    $configPath = dirname(__DIR__) . '/config/mail.php';
    if (!is_file($configPath)) {
        return false;
    }

    $config = require $configPath;
    if (!is_array($config) || empty($config['enabled'])) {
        return false;
    }

    foreach (['host', 'port', 'encryption', 'username', 'password', 'from_email', 'from_name'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) && !is_int($config[$key])) {
            error_log('Dentico mail: invalid SMTP configuration.');
            return false;
        }
    }

    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        error_log('Dentico mail: Composer dependencies are missing; run composer install.');
        return false;
    }

    $recipientEmail = isset($appointment['email']) && is_string($appointment['email'])
        ? trim($appointment['email'])
        : '';
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('Dentico mail: appointment recipient email is invalid.');
        return false;
    }

    require_once $autoloadPath;

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        error_log('Dentico mail: PHPMailer class is unavailable.');
        return false;
    }

    $patientName = (string) ($appointment['patient_name'] ?? 'there');
    $serviceName = (string) ($appointment['service_name'] ?? 'Appointment');
    $date = (string) ($appointment['requested_date'] ?? '');
    $time = substr((string) ($appointment['requested_time'] ?? ''), 0, 5);

    try {
        $formattedDate = (new DateTimeImmutable($date))->format('F j, Y');
    } catch (Throwable $exception) {
        error_log('Dentico mail: appointment date is invalid.');
        return false;
    }

    $eventDetails = [
        'request_received' => [
            'subject' => 'We received your appointment request',
            'heading' => 'Appointment request received',
            'message' => 'Your request has been received. This is not yet a confirmed appointment; the clinic will contact you.',
        ],
        'confirmed' => [
            'subject' => 'Your Dentico appointment is confirmed',
            'heading' => 'Appointment confirmed',
            'message' => 'Your appointment has been confirmed. We look forward to seeing you.',
        ],
        'cancelled' => [
            'subject' => 'Update about your Dentico appointment',
            'heading' => 'Appointment cancelled',
            'message' => 'Your appointment request has been marked as cancelled. Please contact the clinic if you have questions.',
        ],
    ][$event];

    $safeName = htmlspecialchars($patientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeService = htmlspecialchars($serviceName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeDate = htmlspecialchars($formattedDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeTime = htmlspecialchars($time, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeHeading = htmlspecialchars($eventDetails['heading'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = htmlspecialchars($eventDetails['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $htmlBody = '<!doctype html><html lang="en"><head><meta charset="utf-8"></head><body>'
        . '<h1>' . $safeHeading . '</h1>'
        . '<p>Hello ' . $safeName . ',</p>'
        . '<p>' . $safeMessage . '</p>'
        . '<p><strong>Service:</strong> ' . $safeService . '<br>'
        . '<strong>Date:</strong> ' . $safeDate . '<br>'
        . '<strong>Time:</strong> ' . $safeTime . '</p>'
        . '<p>Dentico</p></body></html>';

    $plainBody = $eventDetails['heading'] . "\n\n"
        . 'Hello ' . $patientName . ',' . "\n\n"
        . $eventDetails['message'] . "\n\n"
        . 'Service: ' . $serviceName . "\n"
        . 'Date: ' . $formattedDate . "\n"
        . 'Time: ' . $time . "\n\n"
        . 'Dentico';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $config['username'];
        $mail->Password = (string) $config['password'];
        $mail->Port = (int) $config['port'];
        $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->Timeout = 10;

        if ($config['encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($config['encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['encryption'] === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            error_log('Dentico mail: unsupported SMTP encryption setting.');
            return false;
        }

        $mail->setFrom((string) $config['from_email'], (string) $config['from_name']);
        $mail->addAddress($recipientEmail, $patientName);
        $mail->isHTML(true);
        $mail->Subject = $eventDetails['subject'];
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody;
        $mail->send();

        return true;
    } catch (Throwable $exception) {
        // Avoid logging SMTP credentials or full message contents.
        error_log('Dentico mail delivery failed: ' . get_class($exception));
        return false;
    }
}
