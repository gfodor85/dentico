# Dentico

Dentico is a dental-clinic website being developed into a PHP and MySQL application.

## Local development with XAMPP

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Place or clone this repository inside XAMPP's `htdocs` directory (for example, `D:\\WEB_DEV\\xampp\\htdocs\\Dentico`).
3. Open phpMyAdmin at `http://localhost/phpmyadmin` and create a database named `dentico` using the `utf8mb4_unicode_ci` collation.
4. Copy `config/database.example.php` to `config/database.php`.
5. Update `config/database.php` with your local MySQL username and password. A default XAMPP installation often uses `root` with an empty password, but use the credentials configured on your machine.
6. Open the site at `http://localhost/Dentico/` (or the folder name you chose).

## Database connection

The reusable PDO connection is in `app/database.php`. Include it from a PHP endpoint and call `denticoDatabase()` when a database connection is needed.

The local file `config/database.php` is intentionally excluded from Git because it contains machine-specific settings and may contain credentials. Do not commit production credentials.

## Initial booking database

The initial schema is in `database/schema.sql`. To apply it locally:

1. Open `http://localhost/phpmyadmin`.
2. Select the `dentico` database in the left sidebar.
3. Open the **Import** tab.
4. Choose `database/schema.sql` from this project and run the import.
5. Open the `services` table and confirm that the three example services were added.
6. Confirm that the `appointment_requests` table exists.

The example services are placeholders. Edit them to match the actual clinic before using the booking form publicly. The current sample opening hours are Monday–Friday, 08:00–17:00. New requests outside these hours are rejected, including requests whose service duration would extend beyond 17:00. Requests are initially saved with a `pending` status. The admin page checks opening hours and prevents overlapping confirmed appointments when a request is confirmed. This is an initial local-development implementation; review concurrency and operational requirements before public production use.

## Applying the security hardening migration

For an existing local database, import `database/migrations/20261020_security_hardening.sql` once before deploying this branch. It snapshots each appointment's current service duration and creates the rate-limit and appointment-day lock tables. New installations should import the updated `database/schema.sql`. The application does not run migrations automatically. The migration also enables IP-based throttling (five sign-in attempts, booking requests, and contact messages per 15-minute window), serializes admin confirmations by date, and preserves each appointment's originally requested service duration.

## Development workflow

- Make changes on a feature branch.
- Test PHP pages locally with XAMPP.
- Open a pull request for review before merging into `master`.

## Current scope

The existing website and contact form are retained. The PDO connection and initial booking schema provide the foundation for adding appointment booking and an administrative interface.

## Google Maps configuration

The Google Maps JavaScript API is optional.

Configure a restricted browser key using either:
- An untracked config/maps.php file, copied from config/maps.example.php.
- The GOOGLE_MAPS_API_KEY Apache environment variable.

For Apache/XAMPP, the environment variable can be configured with:

    SetEnv GOOGLE_MAPS_API_KEY "your-restricted-key"

Restart Apache after updating its configuration.

A non-empty key in config/maps.php takes precedence over the
environment variable. Without a configured key, the map script
is not loaded.

Restrict the key in Google Cloud to the Maps JavaScript API
and approved HTTP referrers. Revoke previously exposed keys.
Do not commit credentials or API keys.

## 2026 UI refresh (preview branch)

The first responsive visual refresh uses `assets/css/dentico-ui.css` and a shared
`partials/ui-header.php` for the home, treatment catalogue, booking and admin pages.
The home page is a **demo**; replace the sample content, photography, map coordinates
and clinic information before production.

The layout-only updates to `booking.php` and `admin.php` deliberately leave POST
field names, CSRF tokens, PHP validation, booking availability logic and database
transactions unchanged. The contact form retains its existing POST action and token.

### Local XAMPP smoke test before merge

1. Check out the UI feature branch in a separate worktree or directory.
2. Run `php -l` against `index.php`, `booking.php`, `admin.php`,
   `services.php`, `partials/form.php`, and `partials/ui-header.php`.
3. Open the home, services, booking and admin routes at mobile and desktop widths.
4. Check that the booking form loads slots and submits successfully using **synthetic**
   test data; verify confirmation still needs admin approval.
5. Check the login form, admin calendar, request cards and status update forms.
6. Check the contact CSRF field. Do not send mail unless an approved test SMTP sink
   is configured. Do not copy private `config/*.php` files into the repository.
7. Ensure Maps stays optional when no restricted API key is configured.

This stylesheet does not require a CSS build step.
