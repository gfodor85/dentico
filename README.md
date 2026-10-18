# Dentico

Dentico is a dental-clinic website being developed into a PHP and MySQL application.

## Local development with XAMPP

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Place or clone this repository inside XAMPP's `htdocs` directory (for example, `C:\\xampp\\htdocs\\dentico`).
3. Open phpMyAdmin at `http://localhost/phpmyadmin` and create a database named `dentico` using the `utf8mb4_unicode_ci` collation.
4. Copy `config/database.example.php` to `config/database.php`.
5. Update `config/database.php` with your local MySQL username and password. A default XAMPP installation often uses `root` with an empty password, but use the credentials configured on your machine.
6. Open the site at `http://localhost/dentico/`.

## Database connection

The reusable PDO connection is in `app/database.php`. Include it from a PHP endpoint and call `denticoDatabase()` when a database connection is needed.

The local file `config/database.php` is intentionally excluded from Git because it contains machine-specific settings and may contain credentials. Do not commit production credentials.

## Development workflow

- Make changes on a feature branch.
- Test PHP pages locally with XAMPP.
- Open a pull request for review before merging into `master`.

## Current scope

The existing website and contact form are retained. The database layer is the first foundation for adding clinic services, appointment booking, and an administrative interface in later steps.
