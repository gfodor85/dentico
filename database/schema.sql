-- Dentico initial schema
-- Select the dentico database in phpMyAdmin before importing this file.
-- Example service rows are placeholders and can be edited later.

CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    price DECIMAL(10, 2) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_services_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id INT UNSIGNED NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    patient_name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    requested_date DATE NOT NULL,
    requested_time TIME NOT NULL,
    message VARCHAR(1000) NULL,
    status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_appointments_date_time_status (requested_date, requested_time, status),
    KEY idx_appointments_email (email),
    CONSTRAINT fk_appointment_requests_service
        FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO services (name, description, duration_minutes, price, display_order)
SELECT 'General dental consultation', 'Example service; update this text for the clinic.', 30, NULL, 1
WHERE NOT EXISTS (
    SELECT 1 FROM services WHERE name = 'General dental consultation'
);

INSERT INTO services (name, description, duration_minutes, price, display_order)
SELECT 'Professional cleaning', 'Example service; update this text for the clinic.', 45, NULL, 2
WHERE NOT EXISTS (
    SELECT 1 FROM services WHERE name = 'Professional cleaning'
);

INSERT INTO services (name, description, duration_minutes, price, display_order)
SELECT 'Cosmetic dentistry consultation', 'Example service; update this text for the clinic.', 30, NULL, 3
WHERE NOT EXISTS (
    SELECT 1 FROM services WHERE name = 'Cosmetic dentistry consultation'
);

CREATE TABLE IF NOT EXISTS appointment_day_locks (
    appointment_date DATE NOT NULL,
    PRIMARY KEY (appointment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_rate_limits (
    scope VARCHAR(32) NOT NULL,
    client_hash CHAR(64) NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (scope, client_hash),
    KEY idx_request_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
