<?php
declare(strict_types=1);

/**
 * Fixed-window request throttling backed by MySQL.
 * Client addresses are stored as SHA-256 hashes rather than in clear text.
 */
function denticoRateLimitConsume(PDO $pdo, string $scope, string $clientAddress, int $limit, int $windowSeconds): bool
{
    $packedAddress = @inet_pton($clientAddress);
    if ($packedAddress === false || $limit < 1 || $windowSeconds < 1) {
        return false;
    }

    $clientHash = hash('sha256', $packedAddress);
    $windowStart = intdiv(time(), $windowSeconds) * $windowSeconds;

    $statement = $pdo->prepare(
        'INSERT INTO request_rate_limits (scope, client_hash, window_start, attempt_count)
         VALUES (:scope, :client_hash, :window_start, 1)
         ON DUPLICATE KEY UPDATE
            attempt_count = IF(window_start = :previous_window, attempt_count + 1, 1),
            window_start = :current_window'
    );
    $statement->execute([
        'scope' => $scope,
        'client_hash' => $clientHash,
        'previous_window' => $windowStart,
        'current_window' => $windowStart,
    ]);

    $count = $pdo->prepare(
        'SELECT attempt_count FROM request_rate_limits
         WHERE scope = :scope AND client_hash = :client_hash'
    );
    $count->execute(['scope' => $scope, 'client_hash' => $clientHash]);
    $attempts = (int) $count->fetchColumn();

    // Opportunistically remove old fingerprints without adding a scheduled job.
    if (random_int(1, 100) === 1) {
        $cleanup = $pdo->prepare(
            'DELETE FROM request_rate_limits WHERE window_start < :cutoff'
        );
        $cleanup->execute(['cutoff' => time() - max(86400, $windowSeconds * 4)]);
    }

    return $attempts <= $limit;
}

function denticoRateLimitClear(PDO $pdo, string $scope, string $clientAddress): void
{
    $packedAddress = @inet_pton($clientAddress);
    if ($packedAddress === false) {
        return;
    }

    $statement = $pdo->prepare(
        'DELETE FROM request_rate_limits WHERE scope = :scope AND client_hash = :client_hash'
    );
    $statement->execute([
        'scope' => $scope,
        'client_hash' => hash('sha256', $packedAddress),
    ]);
}
