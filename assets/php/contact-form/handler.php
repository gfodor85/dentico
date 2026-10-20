<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Budapest');
ini_set('session.use_strict_mode', '1');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$postedToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['contact_csrf_token'])
    || !hash_equals($_SESSION['contact_csrf_token'], $postedToken)) {
    http_response_code(400);
    exit('The form expired or could not be verified. Reload the page and try again.');
}
?>
<!-- HANDLING -->
<script type="text/javascript">
    function countdown() {
        var i = document.getElementById("counter");
        if (parseInt(i.innerHTML)<=0) {
            location.href = "../../../index.php";
        }
        i.innerHTML = parseInt(i.innerHTML)-1;
    }
    setInterval(function(){ countdown(); },1000);
</script>';

<style>
<?php include '../../css/main.css'; ?>
</style>

<?php

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//Load composer's autoloader
require_once __DIR__ . '/../../../app/database.php';
require_once __DIR__ . '/../../../app/rate_limit.php';

try {
    $autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
    $mailConfigPath = __DIR__ . '/../../../config/mail.php';
    if (!is_file($autoloadPath) || !is_file($mailConfigPath)) {
        throw new RuntimeException('Contact mail configuration is unavailable.');
    }

    $mailConfig = require $mailConfigPath;
    if (!is_array($mailConfig) || empty($mailConfig['enabled'])) {
        throw new RuntimeException('Contact mail is disabled.');
    }

    require_once $autoloadPath;
    $mail = new PHPMailer(true);

    if(isset($_POST['send'])){
            
        $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
        $subject = isset($_POST['subject']) && is_string($_POST['subject']) ? trim($_POST['subject']) : '';
        $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
        $message = isset($_POST['message']) && is_string($_POST['message']) ? trim($_POST['message']) : '';

        if ($name === '' || mb_strlen($name) > 120
            || $subject === '' || mb_strlen($subject) > 200
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
            || $message === '' || mb_strlen($message) > 5000) {
            http_response_code(400);
            echo '<div class="form-alert-body"><div class="form-alert-text"><p>Please provide valid contact details and a message.</p></div></div>';
            exit;
        }

        $pdo = denticoDatabase();
        if (!denticoRateLimitConsume($pdo, 'contact', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 900)) {
            http_response_code(429);
            echo '<div class="form-alert-body"><div class="form-alert-text"><p>Too many messages were sent. Please wait and try again.</p></div></div>';
            exit;
        }

        foreach (['host', 'username', 'password', 'from_email', 'from_name'] as $key) {
            if (!isset($mailConfig[$key]) || !is_string($mailConfig[$key])) {
                throw new RuntimeException('Contact mail configuration is invalid.');
            }
        }
        $port = filter_var($mailConfig['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false || !filter_var($mailConfig['from_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Contact mail configuration is invalid.');
        }

        $mail->isSMTP();
        $mail->Host = $mailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig['username'];
        $mail->Password = $mailConfig['password'];
        $mail->Port = $port;
        $mail->Timeout = 10;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        if (($mailConfig['encryption'] ?? '') === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (($mailConfig['encryption'] ?? '') === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            throw new RuntimeException('Contact mail encryption is invalid.');
        }

        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addAddress($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addReplyTo($email, $name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $mail->Body = '<p>Contact form message from ' . $safeName . '</p><p>' . $safeMessage . '</p>';
        $mail->AltBody = "Contact form message from {$name}\n\n{$message}";

        $mail->send();
        $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
        echo
            '<div class="form-alert-body">
            <div class="form-alert-text">
                <p>Thank You! Your message has been sent. I will get back to you soon!</p><br>
                <svg class="mail_sent">
                    <use xlink:href="../../img/svg/symbols.svg#mail_sent"></use>
                </svg><br><br>
                <p>You will be redirected back to my website in <span id="counter">10</span> seconds</p><br>
            </div>
        </div>';
    }
    
} catch (Throwable $e) {
    error_log('Dentico legacy contact mail failed: ' . get_class($e));
    echo 
        '<div class="form-alert-body">
            <div class="form-alert-text">
                <p>Ooops! It seems something went wrong.</p><br>
                <p>Message could not be sent.</p><br>
                <svg class="error_msg">
                    <use xlink:href="../../img/svg/symbols.svg#error_msg"></use>
                </svg><br><br>
                <p>You will be redirected back to my website in <span id="counter">10</span> seconds</p><br>
            </div>
        </div>';
}
?>