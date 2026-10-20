# Local Gmail SMTP setup

This project uses PHPMailer through Composer to send appointment notifications. SMTP credentials must remain in a local-only config file.

## 1. Install the PHP dependency

Open Command Prompt or PowerShell and run:

```powershell
cd D:\WEB_DEV\xampp\htdocs\Dentico
composer install
```

If Composer is not installed, install Composer for Windows from the official Composer website, then reopen the terminal. Ensure Composer uses the PHP installation that comes with your XAMPP setup if your PATH contains multiple PHP versions.

Composer creates the local `vendor/` directory. It is intentionally excluded from Git.

## 2. Create the local mail configuration

Copy:

```text
config/mail.example.php
```

to:

```text
config/mail.php
```

Edit `config/mail.php` and set:

- `enabled` to `true`
- `username` and `from_email` to the same Gmail address
- `password` to a Google App Password
- Keep host as `smtp.gmail.com`, port `587`, and encryption `tls`

Do not commit or share `config/mail.php`. It is listed in `.gitignore`.

## 3. Create a Google App Password

1. Sign in to the Google Account that will send Dentico notifications.
2. Enable 2-Step Verification if it is not already enabled.
3. Open the Google Account's App Passwords page and create an app password.
4. Paste that app password into the local `config/mail.php` file.

Google may not offer App Passwords for some account types or security configurations. Use a suitable SMTP provider if the option is unavailable. Never use your normal Google account password for SMTP.

## 4. Test locally

1. Restart Apache if you changed PHP configuration; Composer installation itself does not normally require an Apache restart.
2. Submit a test appointment using an email address you can access.
3. Confirm that the booking is saved even if email delivery fails.
4. In the admin page, confirm the request. A notification should be sent after the status update has been committed.
5. Change a request to cancelled and confirm that a cancellation email is sent.

The initial booking email states that the request is received but is not yet confirmed. Confirmation and cancellation notifications are sent only when the status actually changes to one of those states.

If an email cannot be sent, the appointment data remains saved. Check the PHP error log for a generic mailer error, verify the local SMTP configuration, and ensure Composer dependencies are installed.

## Notifications included

- New appointment request: receipt message to the patient.
- Status changed to `confirmed`: confirmation email to the patient.
- Status changed to `cancelled`: cancellation email to the patient.

The clinic's internal/admin notification email is not included in this first version.
