# Dentico — instructions for coding agents

## Project and environment
- Dentico is a PHP 8.0+ and MySQL dental-clinic website with a public appointment request flow and a password-protected administrative interface. It also uses HTML, CSS, JavaScript and PHPMailer via Composer.
- The developer uses Windows/XAMPP. Typical local project path: `D:\WEB_DEV\xampp\htdocs\Dentico`; URL: `http://localhost/Dentico/`. The local database is `dentico`, managed via phpMyAdmin.
- Read `README.md`, `database/schema.sql`, `composer.json`, and `docs/CODEX_HANDOFF.md` before making changes.
- The GitHub default branch `master` is not the newest PHP development baseline. `feature/php-pdo-foundation` contains the PHP/MySQL work, and `feature/admin-logout-security` additionally contains the latest logout fix. This handoff branch (`feature/codex-handoff`) is based on that latest fix. Verify branch ancestry and the desired PR target before proposing a merge.

## Architecture and behavior to preserve
- `index.php`, `services.php`: public site; `booking.php`: appointment requests; `available-slots.php`: availability endpoint; `admin.php`: administrative authentication, calendar, and status updates.
- `app/database.php`: PDO connection from local `config/database.php`; `app/mailer.php`: PHPMailer integration; `database/schema.sql`: initial schema.
- Appointment requests start as `pending`; confirmations must respect business hours and cannot overlap other confirmed appointments. Current initial hours: Mon–Fri, 08:00–17:00; available starts use 15-minute increments.
- Sending a notification may fail without undoing a successfully committed booking or status update.
- A fresh unauthenticated PHPSESSID after admin logout/redirect is normal because the login page starts a new session for CSRF protection; the previous authenticated session must be invalidated.

## Safety and implementation rules
- Do not commit secrets, local configs, SMTP passwords, real patient information, or database dumps. Local-only `config/database.php`, `config/admin.php`, `config/mail.php`, `.env*`, and `/vendor/` are gitignored.
- Do not disable CSRF protection, session-cookie protections, access checks, output escaping, or prepared SQL statements. Consider both authorization and concurrency when changing appointment logic.
- Never run destructive SQL, overwrite a local database, send real customer emails, or deploy to production without explicit owner approval. Use synthetic fixtures for tests.
- Do not rewrite the application to a different framework or language without explicit approval. Prefer small, reviewable, backwards-compatible changes.
- Do not modify bundled third-party code under `assets/php/contact-form/vendor/` unless the owner specifically authorizes a dependency migration; flag security/maintenance concerns separately.
- Avoid unrelated formatting and refactoring. Keep existing public pages and booking/admin flows working.

## Workflow and validation
- First task: read-only architectural, security, and quality audit; report prioritized, actionable findings and their file locations. Do not edit application code during that first audit.
- For subsequent tasks, create dedicated branches and reviewable pull requests; never merge or deploy without owner approval.
- After PHP changes, run `php -l` on every changed PHP file if PHP is available. Run other relevant automated checks when available and report exact commands/results.
- Local XAMPP/MySQL and SMTP tests require a configured environment and should not be claimed as passed if not actually performed. Provide a practical manual test checklist for the owner.
- Summarize what changed, why, tests performed/not performed, security implications, and any required local configuration or schema migration.
