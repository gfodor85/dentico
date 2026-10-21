<?php
declare(strict_types=1);

/**
 * Copy this file to admin.php and set a password hash.
 * Keep config/admin.php local and never commit it.
 *
 * Generate a hash in the XAMPP Shell with:
 * php -r "echo password_hash('CHOOSE_A_LONG_UNIQUE_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * Replace the placeholder below with the generated hash, not the plain password.
 */
return [
    'password_hash' => 'REPLACE_WITH_GENERATED_PASSWORD_HASH',
];
