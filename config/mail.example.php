<?php
declare(strict_types=1);

/**
 * Copy this file to config/mail.php and fill in your Gmail address and
 * Google App Password. Never commit config/mail.php or real credentials.
 *
 * Gmail SMTP uses smtp.gmail.com on port 587 with STARTTLS.
 * Use a Google App Password, not your normal Google account password.
 */
return [
    'enabled' => false,
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your-address@gmail.com',
    'password' => 'PASTE_YOUR_GOOGLE_APP_PASSWORD_HERE',
    'from_email' => 'your-address@gmail.com',
    'from_name' => 'Dentico',
];
