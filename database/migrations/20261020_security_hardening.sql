-- Apply once to an existing Dentico database before deploying this branch.
-- Backfill each appointment's duration so later service edits do not change old bookings.
ALTER TABLE appointment_requests
    ADD COLUMN duration_minutes SMALLINT UNSIGNED NULL AFTER service_id;

UPDATE appointment_requests ar
INNER JOIN services s ON s.id = ar.service_id
SET ar.duration_minutes = s.duration_minutes
WHERE ar.duration_minutes IS NULL;

ALTER TABLE appointment_requests
    MODIFY duration_minutes SMALLINT UNSIGNED NOT NULL;

CREATE TABLE request_rate_limits (
    scope VARCHAR(32) NOT NULL,
    client_hash CHAR(64) NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (scope, client_hash),
    KEY idx_request_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointment_day_locks (
    appointment_date DATE NOT NULL,
    PRIMARY KEY (appointment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
