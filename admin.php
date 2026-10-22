<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Budapest');
ini_set('session.use_strict_mode', '1');

$isHttps = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== ''
    && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// Do not allow authenticated admin pages to be served from browser caches.
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

$adminSessionExpired = false;

// Expire inactive admin sessions after 30 minutes.
if (!empty($_SESSION['admin_authenticated'])) {
    $lastActivity = $_SESSION['admin_last_activity'] ?? 0;
    if (!is_int($lastActivity) || $lastActivity <= time() - 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        $adminSessionExpired = true;
    } else {
        $_SESSION['admin_last_activity'] = time();
    }
}

require_once __DIR__ . '/app/database.php';
require_once __DIR__ . '/app/rate_limit.php';
require_once __DIR__ . '/app/mailer.php';

function adminEscape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$configPath = __DIR__ . '/config/admin.php';
$configExists = is_file($configPath);
$adminConfig = $configExists ? require $configPath : [];
$hasPasswordHash = is_array($adminConfig)
    && isset($adminConfig['password_hash'])
    && is_string($adminConfig['password_hash'])
    && str_starts_with($adminConfig['password_hash'], '$');

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
if ($adminSessionExpired) {
    $errors[] = 'Your admin session expired after 30 minutes of inactivity. Please sign in again.';
}
$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);
$requests = [];
$totalRequests = 0;
$requestPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$focusedRequestId = filter_var($_GET['focus'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]) ?: null;
$pageSize = 25;
$databaseError = false;

// The calendar defaults to the current week; ?week=YYYY-MM-DD selects another week.
$requestedWeek = isset($_GET['week']) && is_string($_GET['week']) ? $_GET['week'] : '';
$weekDate = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedWeek);
$weekDateErrors = DateTimeImmutable::getLastErrors();
$validWeekDate = $weekDate !== false
    && ($weekDateErrors === false || ($weekDateErrors['warning_count'] === 0 && $weekDateErrors['error_count'] === 0))
    && $weekDate->format('Y-m-d') === $requestedWeek;
$weekDate = $validWeekDate ? $weekDate : new DateTimeImmutable('today');
$weekStart = $weekDate->modify('monday this week');
$weekEnd = $weekStart->modify('+6 days');
$previousWeek = $weekStart->modify('-7 days')->format('Y-m-d');
$nextWeek = $weekStart->modify('+7 days')->format('Y-m-d');
$confirmedByDate = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasPasswordHash) {
    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';

    if (!hash_equals($_SESSION['admin_csrf_token'], $token)) {
        $errors[] = 'The form expired. Reload the page and try again.';
    } elseif (isset($_POST['login'])) {
        $password = isset($_POST['password']) && is_string($_POST['password'])
            ? $_POST['password']
            : '';

        try {
            $pdo = denticoDatabase();
            $clientAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $canAttemptLogin = denticoRateLimitConsume($pdo, 'admin_login', $clientAddress, 5, 900);

            if ($canAttemptLogin && password_verify($password, $adminConfig['password_hash'])) {
                denticoRateLimitClear($pdo, 'admin_login', $clientAddress);
                session_regenerate_id(true);
                $_SESSION['admin_authenticated'] = true;
                $_SESSION['admin_last_activity'] = time();
                $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
                header('Location: admin.php');
                exit;
            }

            // Keep the response identical for invalid passwords and throttled attempts.
            $errors[] = 'The password was incorrect, or too many attempts were made. Please try again later.';
        } catch (Throwable $exception) {
            error_log('Dentico admin login error: ' . $exception->getMessage());
            $errors[] = 'Sign-in is temporarily unavailable. Please try again later.';
        }
    } elseif (!empty($_SESSION['admin_authenticated']) && isset($_POST['request_id'], $_POST['status'])) {
        $requestId = filter_var($_POST['request_id'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $status = is_string($_POST['status']) ? $_POST['status'] : '';

        if ($requestId === false || !in_array($status, ['confirmed', 'cancelled', 'pending'], true)) {
            $errors[] = 'Invalid status update.';
        } else {
            try {
                $pdo = denticoDatabase();
                $pdo->beginTransaction();

                // Serialize confirmation changes per appointment date, including dates with no rows to lock.
                $dateLock = $pdo->prepare(
                    'SELECT requested_date FROM appointment_requests WHERE id = :id'
                );
                $dateLock->execute(['id' => $requestId]);
                $requestedDateForLock = $dateLock->fetchColumn();
                if ($requestedDateForLock === false) {
                    throw new RuntimeException('Appointment request not found.');
                }

                $ensureDayLock = $pdo->prepare(
                    'INSERT IGNORE INTO appointment_day_locks (appointment_date) VALUES (:requested_date)'
                );
                $ensureDayLock->execute(['requested_date' => $requestedDateForLock]);

                $dayLock = $pdo->prepare(
                    'SELECT appointment_date FROM appointment_day_locks
                     WHERE appointment_date = :requested_date FOR UPDATE'
                );
                $dayLock->execute(['requested_date' => $requestedDateForLock]);
                $dayLock->fetchColumn();

                $currentStatement = $pdo->prepare(
                    'SELECT ar.id, ar.patient_name, ar.email, ar.requested_date, ar.requested_time, ar.status,
                            s.name AS service_name, ar.duration_minutes
                     FROM appointment_requests ar
                     INNER JOIN services s ON s.id = ar.service_id
                     WHERE ar.id = :id'
                );
                $currentStatement->execute(['id' => $requestId]);
                $current = $currentStatement->fetch();

                if (!$current) {
                    throw new RuntimeException('Appointment request not found.');
                }

                $allowedTransitions = [
                    'pending' => ['confirmed', 'cancelled'],
                    'confirmed' => ['cancelled'],
                    'cancelled' => [],
                ];
                if ($current['status'] !== $status
                    && !in_array($status, $allowedTransitions[$current['status']] ?? [], true)) {
                    throw new DomainException('That status change is not allowed.');
                }

                if ($status === 'confirmed' && $current['status'] !== 'confirmed') {
                    // Compare date AND time in the clinic's Europe/Budapest timezone.
                    $appointmentStart = new DateTimeImmutable(
                        (string) $current['requested_date'] . ' ' . (string) $current['requested_time']
                    );
                    $startMinutes = ((int) substr((string) $current['requested_time'], 0, 2) * 60)
                        + (int) substr((string) $current['requested_time'], 3, 2);
                    $endMinutes = $startMinutes + (int) $current['duration_minutes'];

                    if ((int) $current['duration_minutes'] < 1
                        || $appointmentStart <= new DateTimeImmutable('now')
                        || (int) $appointmentStart->format('N') > 5
                        || $startMinutes < 8 * 60
                        || $endMinutes > 17 * 60) {
                        throw new DomainException(
                            'This request is in the past or falls outside clinic opening hours (Monday–Friday, 08:00–17:00). It cannot be confirmed.'
                        );
                    }

                    $confirmedStatement = $pdo->prepare(
                        'SELECT ar.id, ar.requested_time, ar.duration_minutes
                         FROM appointment_requests ar
                         INNER JOIN services s ON s.id = ar.service_id
                         WHERE ar.requested_date = :requested_date
                           AND ar.status = "confirmed"
                           AND ar.id <> :request_id
                         FOR UPDATE'
                    );
                    $confirmedStatement->execute([
                        'requested_date' => $current['requested_date'],
                        'request_id' => $requestId,
                    ]);

                    foreach ($confirmedStatement->fetchAll() as $existing) {
                        $existingStart = ((int) substr((string) $existing['requested_time'], 0, 2) * 60)
                            + (int) substr((string) $existing['requested_time'], 3, 2);
                        $existingEnd = $existingStart + (int) $existing['duration_minutes'];

                        if ($startMinutes < $existingEnd && $existingStart < $endMinutes) {
                            throw new DomainException(
                                'This time overlaps with another confirmed appointment. Choose another time or cancel the conflicting request first.'
                            );
                        }
                    }
                }

                $update = $pdo->prepare(
                    'UPDATE appointment_requests SET status = :status WHERE id = :id'
                );
                $update->execute(['status' => $status, 'id' => $requestId]);
                $pdo->commit();

                $flashMessage = $update->rowCount() > 0
                    ? 'Appointment request status updated.'
                    : 'No changes were made to that request.';

                // Send notifications only after the database transaction has committed.
                // Mail failure must never roll back a successfully saved status change.
                if ($current['status'] !== $status && in_array($status, ['confirmed', 'cancelled'], true)) {
                    $emailSent = denticoSendAppointmentEmail([
                        'patient_name' => (string) $current['patient_name'],
                        'email' => (string) $current['email'],
                        'service_name' => (string) $current['service_name'],
                        'requested_date' => (string) $current['requested_date'],
                        'requested_time' => (string) $current['requested_time'],
                    ], $status);

                    $flashMessage .= $emailSent
                        ? ' Email notification sent.'
                        : ' The status was saved, but the email notification could not be sent. Check the local SMTP configuration.';
                }

                $_SESSION['admin_flash'] = $flashMessage;
                header('Location: admin.php');
                exit;
            } catch (DomainException $exception) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $exception->getMessage();
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Dentico admin update error: ' . $exception->getMessage());
                $errors[] = 'The status could not be updated. Please try again.';
            }
        }
    } elseif (!empty($_SESSION['admin_authenticated']) && isset($_POST['logout'])) {
        // Clear all session data.
        $_SESSION = [];

        // Expire the session cookie in the browser using its original attributes.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        // Invalidate the server-side session.
        session_destroy();

        header('Location: admin.php');
        exit;
    }
}

if (!empty($_SESSION['admin_authenticated']) && $hasPasswordHash) {
    try {
        $pdo = denticoDatabase();
        $totalRequests = (int) $pdo->query('SELECT COUNT(*) FROM appointment_requests')->fetchColumn();
        $lastPage = max(1, (int) ceil($totalRequests / $pageSize));
        $requestPage = min($requestPage, $lastPage);
        $statement = $pdo->prepare(
            'SELECT ar.id, ar.patient_name, ar.email, ar.phone, ar.requested_date,
                    ar.requested_time, ar.message, ar.status, ar.created_at,
                    s.name AS service_name, ar.duration_minutes
             FROM appointment_requests ar
             INNER JOIN services s ON s.id = ar.service_id
             ORDER BY
                CASE ar.status WHEN "pending" THEN 0 WHEN "confirmed" THEN 1 ELSE 2 END,
                ar.requested_date ASC, ar.requested_time ASC, ar.id DESC
             LIMIT :page_size OFFSET :page_offset'
        );
        $statement->bindValue('page_size', $pageSize, PDO::PARAM_INT);
        $statement->bindValue('page_offset', ($requestPage - 1) * $pageSize, PDO::PARAM_INT);
        $statement->execute();
        $requests = $statement->fetchAll();

        // Keep calendar deep links functional even if their request is on another list page.
        if ($focusedRequestId !== null
            && !in_array($focusedRequestId, array_map('intval', array_column($requests, 'id')), true)) {
            $focusedStatement = $pdo->prepare(
                'SELECT ar.id, ar.patient_name, ar.email, ar.phone, ar.requested_date,
                        ar.requested_time, ar.message, ar.status, ar.created_at,
                        s.name AS service_name, ar.duration_minutes
                 FROM appointment_requests ar
                 INNER JOIN services s ON s.id = ar.service_id
                 WHERE ar.id = :id'
            );
            $focusedStatement->execute(['id' => $focusedRequestId]);
            $focusedRequest = $focusedStatement->fetch();
            if ($focusedRequest !== false) {
                array_unshift($requests, $focusedRequest);
            }
        }

        $calendarStatement = $pdo->prepare(
            'SELECT ar.id, ar.patient_name, ar.requested_date, ar.requested_time,
                    s.name AS service_name, ar.duration_minutes
             FROM appointment_requests ar
             INNER JOIN services s ON s.id = ar.service_id
             WHERE ar.status = "confirmed"
               AND ar.requested_date BETWEEN :week_start AND :week_end
             ORDER BY ar.requested_date, ar.requested_time'
        );
        $calendarStatement->execute([
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
        ]);
        foreach ($calendarStatement->fetchAll() as $calendarRequest) {
            $confirmedByDate[$calendarRequest['requested_date']][] = $calendarRequest;
        }
    } catch (Throwable $exception) {
        error_log('Dentico admin list error: ' . $exception->getMessage());
        $databaseError = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment Requests | Dentico Admin</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/dentico-ui.css">
</head>
<body class="admin-body dentico-admin">
<?php include __DIR__ . '/partials/ui-header.php'; ?>
<main class="container-fluid admin-page">
    <header class="mb-4">
        <span class="dc-kicker">Clinic workspace</span>
        <h1>Appointment dashboard</h1>
        <p>Review incoming requests and manage confirmed visits in one place.</p>
    </header>

    <?php if (!$configExists || !$hasPasswordHash): ?>
        <div class="alert alert-warning" role="alert">
            Admin access is not configured yet. Copy <code>config/admin.example.php</code> to
            <code>config/admin.php</code>, then set a password hash in the local config file.
            Do not commit that local file.
        </div>
    <?php else: ?>
        <?php if ($flash !== ''): ?>
            <div class="alert alert-success" role="status"><?= adminEscape($flash) ?></div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= adminEscape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($_SESSION['admin_authenticated'])): ?>
            <section class="admin-panel admin-login">
                <h2 class="h4">Sign in</h2>
                <form method="post" action="admin.php">
                    <input type="hidden" name="csrf_token" value="<?= adminEscape($_SESSION['admin_csrf_token']) ?>">
                    <div class="form-group">
                        <label for="password">Admin password</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary" type="submit" name="login" value="1">Sign in</button>
                </form>
            </section>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="admin-summary mb-0">Total requests <strong><?= $totalRequests ?></strong></p>
                <form method="post" action="admin.php">
                    <input type="hidden" name="csrf_token" value="<?= adminEscape($_SESSION['admin_csrf_token']) ?>">
                    <button class="btn btn-outline-secondary btn-sm" type="submit" name="logout" value="1">Sign out</button>
                </form>
            </div>

            <?php if ($focusedRequestId !== null): ?>
                <p><a href="admin.php?week=<?= adminEscape($weekStart->format('Y-m-d')) ?>">&larr; Back to requests list</a></p>
            <?php endif; ?>

            <section class="admin-panel mb-4" aria-labelledby="weekly-calendar-heading">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h4 mb-1" id="weekly-calendar-heading">Weekly calendar</h2>
                        <p class="text-muted mb-0"><?= $weekStart->format('M j, Y') ?> – <?= $weekEnd->format('M j, Y') ?></p>
                    </div>
                    <nav class="btn-group btn-group-sm mt-2" aria-label="Calendar week navigation">
                        <a class="btn btn-outline-secondary" href="admin.php?week=<?= adminEscape($previousWeek) ?>">&larr; Previous</a>
                        <a class="btn btn-outline-secondary" href="admin.php">This week</a>
                        <a class="btn btn-outline-secondary" href="admin.php?week=<?= adminEscape($nextWeek) ?>">Next &rarr;</a>
                    </nav>
                </div>
                <p class="small text-muted">Confirmed appointments only. Opening hours: Monday–Friday, 08:00–17:00.</p>
                <div class="calendar-days">
                    <?php for ($dayOffset = 0; $dayOffset < 5; $dayOffset++): ?>
                        <?php $calendarDay = $weekStart->modify('+' . $dayOffset . ' days'); ?>
                        <?php $calendarDate = $calendarDay->format('Y-m-d'); ?>
                        <div class="calendar-day">
                            <div class="calendar-day__content">
                                <h3 class="h6 mb-2"><?= $calendarDay->format('D, M j') ?></h3>
                                <?php if (empty($confirmedByDate[$calendarDate])): ?>
                                    <p class="small text-muted mb-0">No confirmed appointments</p>
                                <?php else: ?>
                                    <?php foreach ($confirmedByDate[$calendarDate] as $calendarRequest): ?>
                                        <div class="calendar-appointment">
                                            <strong><?= substr((string) $calendarRequest['requested_time'], 0, 5) ?></strong>
                                            <div class="small"><?= adminEscape($calendarRequest['patient_name']) ?></div>
                                            <div class="small"><?= adminEscape($calendarRequest['service_name']) ?> · <?= (int) $calendarRequest['duration_minutes'] ?> min</div>
                                            <a class="small" href="admin.php?focus=<?= (int) $calendarRequest['id'] ?>&amp;week=<?= adminEscape($weekStart->format('Y-m-d')) ?>#request-<?= (int) $calendarRequest['id'] ?>">View request</a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </section>

            <?php if ($databaseError): ?>
                <div class="alert alert-danger" role="alert">The requests could not be loaded. Check the local database connection.</div>
            <?php elseif ($requests === []): ?>
                <div class="alert alert-info" role="status">There are no appointment requests yet.</div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <?php
                    $statusOptions = match ($request['status']) {
                        'pending' => ['pending', 'confirmed', 'cancelled'],
                        'confirmed' => ['confirmed', 'cancelled'],
                        default => ['cancelled'],
                    };
                    ?>
                    <article class="request-card" id="request-<?= (int) $request['id'] ?>">
                        <div class="d-flex flex-wrap justify-content-between align-items-start">
                            <h2 class="h5">Request #<?= (int) $request['id'] ?> — <?= adminEscape($request['patient_name']) ?></h2>
                            <span class="badge badge-<?= $request['status'] === 'confirmed' ? 'success' : ($request['status'] === 'cancelled' ? 'secondary' : 'warning') ?>">
                                <?= adminEscape(ucfirst($request['status'])) ?>
                            </span>
                        </div>
                        <p class="mb-1"><strong><?= adminEscape($request['service_name']) ?></strong> (<?= (int) $request['duration_minutes'] ?> min)</p>
                        <p class="request-meta mb-2">
                            Preferred: <?= adminEscape($request['requested_date']) ?> at <?= substr((string) $request['requested_time'], 0, 5) ?><br>
                            Email: <a href="mailto:<?= adminEscape($request['email']) ?>"><?= adminEscape($request['email']) ?></a><br>
                            Phone: <?= adminEscape($request['phone']) ?><br>
                            Submitted: <?= adminEscape($request['created_at']) ?>
                        </p>
                        <?php if (!empty($request['message'])): ?>
                            <p class="request-note"><strong>Note:</strong> <?= adminEscape($request['message']) ?></p>
                        <?php endif; ?>
                        <form class="form-inline" method="post" action="admin.php">
                            <input type="hidden" name="csrf_token" value="<?= adminEscape($_SESSION['admin_csrf_token']) ?>">
                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                            <label class="mr-2" for="status-<?= (int) $request['id'] ?>">Update status</label>
                            <select class="form-control form-control-sm mr-2 mb-2" id="status-<?= (int) $request['id'] ?>" name="status">
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <option value="<?= adminEscape($statusOption) ?>" <?= $request['status'] === $statusOption ? 'selected' : '' ?>><?= adminEscape(ucfirst($statusOption)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary btn-sm mb-2" type="submit">Save status</button>
                        </form>
                    </article>
                <?php endforeach; ?>
                <?php
                $lastPage = max(1, (int) ceil($totalRequests / $pageSize));
                if ($lastPage > 1):
                ?>
                    <nav class="mt-3" aria-label="Request pages">
                      <ul class="admin-pagination">
                        <li class="page-item <?= $requestPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="admin.php?page=<?= max(1, $requestPage - 1) ?>&amp;week=<?= adminEscape($weekStart->format('Y-m-d')) ?>">Previous</a>
                        </li>
                        <li class="page-item disabled"><span class="page-link">Page <?= $requestPage ?> of <?= $lastPage ?></span></li>
                        <li class="page-item <?= $requestPage >= $lastPage ? 'disabled' : '' ?>">
                            <a class="page-link" href="admin.php?page=<?= min($lastPage, $requestPage + 1) ?>&amp;week=<?= adminEscape($weekStart->format('Y-m-d')) ?>">Next</a>
                        </li>
                      </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
