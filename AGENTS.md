# Dentico — instructions for coding agents

## Project and environment
- Dentico is a PHP 8.0+ and MySQL dental-clinic website with a public appointment request flow and a password-protected administrative interface. It also uses HTML, CSS, JavaScript and PHPMailer via Composer.
- The developer uses Windows/XAMPP. Typical local project path: `D:\WEB_DEV\xampp\htdocs\Dentico`; URL: `http://localhost/Dentico/`. The local database is `dentico`, managed via phpMyAdmin.
- Read `README.md`, `database/schema.sql`, `composer.json`, and `docs/CODEX_HANDOFF.md` before making changes.
- As of 2026-10-22, PR #12 has merged the PHP/MySQL booking system and security hardening into `master`. Current unmerged UI work is on `feature/ui-refresh-2026` (draft PR #13, base `master`). Start UI tasks on that feature branch; do not merge PR #13 without owner approval. Ignore obsolete branch guidance further down in older documents where it conflicts with this date.

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
- The initial Codex read-only audit and security-hardening cycle already happened. For the current UI-design task, inspect the relevant files and make focused code changes on `feature/ui-refresh-2026`; avoid redundant audits unless requested.
- For subsequent tasks, create dedicated branches and reviewable pull requests; never merge or deploy without owner approval.
- After PHP changes, run `php -l` on every changed PHP file if PHP is available. Run other relevant automated checks when available and report exact commands/results.
- Local XAMPP/MySQL and SMTP tests require a configured environment and should not be claimed as passed if not actually performed. Provide a practical manual test checklist for the owner.
- Summarize what changed, why, tests performed/not performed, security implications, and any required local configuration or schema migration.

## Current UI design direction and preview workflow (2026-10-22)
- Keep the **original Dentico** identity prominent: turquoise `#00bbae`, Quicksand typography, original SVG mark, large photo-backed hero, original "Your Smile. Our Passion." slogan, translucent panels, and existing service artwork. Do not replace it with generic blue/white clinic branding.
- User-approved refinements: no "Welcome to" label; fixed desktop hero photo background; on smaller screens allow scrolling; treatment cards have artwork occupying roughly half their height.
- Relevant pages: `index.php`, `partials/ui-header.php`, `partials/form.php`, `services.php`, `booking.php`, `admin.php`; modernized styling is in `assets/css/dentico-ui.css`. Preserve the existing PHP/CSRF/SQL/availability/approval logic while changing presentation.
- Local Git clone: `D:\WEB_DEV\Dentico-GitHub`; **original XAMPP application**: `D:\WEB_DEV\xampp\htdocs\Dentico`; disposable UI preview: `D:\WEB_DEV\xampp\htdocs\Dentico-Preview`.
- Keep private configuration local and out of Git. Prefer changing the clone, then the existing `tools/Refresh-Dentico-Preview.cmd` / `tools/refresh-preview.ps1` updater; do not overwrite the original XAMPP site. Note that the preview may share the original local MySQL `dentico` DB; avoid mutating database records just to check visuals.
- User prefers conversational design requests and automatic local edits, not long PowerShell snippets to paste. If the local Codex process has access, carry out edits, necessary checks and preview updates directly.
