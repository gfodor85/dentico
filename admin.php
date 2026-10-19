<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/app/database.php';

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
$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);
$requests = [];
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

        if (password_verify($password, $adminConfig['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: admin.php');
            exit;
        }

        // Avoid revealing whether a particular password was close to correct.
        $errors[] = 'The password was incorrect.';
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

                // Lock requests for this date while checking and changing confirmation status.
                $dateLock = $pdo->prepare(
                    'SELECT id FROM appointment_requests WHERE requested_date = (
                        SELECT requested_date FROM appointment_requests WHERE id = :id
                    ) FOR UPDATE'
                );
                $dateLock->execute(['id' => $requestId]);
                $dateLock->fetchAll();

                $currentStatement = $pdo->prepare(
                    'SELECT ar.id, ar.requested_date, ar.requested_time, ar.status,
                            s.duration_minutes
                     FROM appointment_requests ar
                     INNER JOIN services s ON s.id = ar.service_id
                     WHERE ar.id = :id'
                );
                $currentStatement->execute(['id' => $requestId]);
                $current = $currentStatement->fetch();

                if (!$current) {
                    throw new RuntimeException('Appointment request not found.');
                }

                if ($status === 'confirmed') {
                    $appointmentDate = new DateTimeImmutable($current['requested_date']);
                    $startMinutes = ((int) substr((string) $current['requested_time'], 0, 2) * 60)
                        + (int) substr((string) $current['requested_time'], 3, 2);
                    $endMinutes = $startMinutes + (int) $current['duration_minutes'];

                    if ((int) $appointmentDate->format('N') > 5
                        || $startMinutes < 8 * 60
                        || $endMinutes > 17 * 60) {
                        throw new DomainException(
                            'This request falls outside the clinic opening hours (Monday–Friday, 08:00–17:00). It cannot be confirmed.'
                        );
                    }

                    $confirmedStatement = $pdo->prepare(
                        'SELECT ar.id, ar.requested_time, s.duration_minutes
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

                $_SESSION['admin_flash'] = $update->rowCount() > 0
                    ? 'Appointment request status updated.'
                    : 'No changes were made to that request.';
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
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: admin.php');
        exit;
    }
}

if (!empty($_SESSION['admin_authenticated']) && $hasPasswordHash) {
    try {
        $pdo = denticoDatabase();
        $statement = $pdo->query(
            'SELECT ar.id, ar.patient_name, ar.email, ar.phone, ar.requested_date,
                    ar.requested_time, ar.message, ar.status, ar.created_at,
                    s.name AS service_name, s.duration_minutes
             FROM appointment_requests ar
             INNER JOIN services s ON s.id = ar.service_id
             ORDER BY
                CASE ar.status WHEN "pending" THEN 0 WHEN "confirmed" THEN 1 ELSE 2 END,
                ar.requested_date ASC, ar.requested_time ASC, ar.id DESC'
        );
        $requests = $statement->fetchAll();

        foreach ($requests as $request) {
            if ($request['status'] === 'confirmed'
                && $request['requested_date'] >= $weekStart->format('Y-m-d')
                && $request['requested_date'] <= $weekEnd->format('Y-m-d')) {
                $confirmedByDate[$request['requested_date']][] = $request;
            }
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
    <style>
        /* Keep the admin area independent from the site's white-text hero styles. */
        html {
            min-height: 100%;
            background: #f3f6fa;
        }
        body.admin-body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            background: #f3f6fa url("assets/img/bg_2_blurred.jpg") center center / cover fixed no-repeat !important;
            color: #212529 !important;
            font-family: Arial, sans-serif !important;
            overflow-x: hidden;
        }
        body.admin-body h1,
        body.admin-body h2,
        body.admin-body h3,
        body.admin-body h4,
        body.admin-body h5,
        body.admin-body h6,
        body.admin-body p,
        body.admin-body label,
        body.admin-body strong,
        body.admin-body .small,
        body.admin-body .request-meta {
            color: #212529;
        }
        body.admin-body a { color: #0056b3; }
        body.admin-body a:hover { color: #003d80; }
        main.admin-page {
            display: block;
            width: 100%;
            max-width: none;
            min-height: 100vh;
            margin: 0;
            padding: 3rem 24px;
            background: rgba(243, 246, 250, 0.78);
            color: #212529;
        }
        .admin-panel {
            padding: 1.5rem;
            border: 1px solid #d7dee7;
            border-radius: .5rem;
            background: #fff;
            color: #212529;
        }
        .request-card {
            border: 1px solid #d7dee7;
            border-radius: .4rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fff;
            color: #212529;
        }
        .request-meta { color: #495057 !important; font-size: .9rem; }
        .admin-login { max-width: 460px; }
        .request-note { white-space: pre-wrap; overflow-wrap: anywhere; }
        body.admin-body .text-muted { color: #5b6570 !important; }
        body.admin-body .alert-success { color: #155724; }
        @media (max-width: 576px) {
            main.admin-page { padding: 1.5rem 12px; }
        }
    </style>
</head>
<body class="admin-body">
<main class="container-fluid admin-page">
    <p><a href="index.php">&larr; Back to Dentico homepage</a></p>
    <header class="mb-4">
        <h1>Dentico Admin</h1>
        <p>Appointment request management</p>
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
                <p class="mb-0">Requests: <strong><?= count($requests) ?></strong></p>
                <form method="post" action="admin.php">
                    <input type="hidden" name="csrf_token" value="<?= adminEscape($_SESSION['admin_csrf_token']) ?>">
                    <button class="btn btn-outline-secondary btn-sm" type="submit" name="logout" value="1">Sign out</button>
                </form>
            </div>

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
                <div class="row">
                    <?php for ($dayOffset = 0; $dayOffset < 5; $dayOffset++): ?>
                        <?php $calendarDay = $weekStart->modify('+' . $dayOffset . ' days'); ?>
                        <?php $calendarDate = $calendarDay->format('Y-m-d'); ?>
                        <div class="col-md-6 col-lg mb-3">
                            <div class="border rounded p-2 h-100">
                                <h3 class="h6 mb-2"><?= $calendarDay->format('D, M j') ?></h3>
                                <?php if (empty($confirmedByDate[$calendarDate])): ?>
                                    <p class="small text-muted mb-0">No confirmed appointments</p>
                                <?php else: ?>
                                    <?php foreach ($confirmedByDate[$calendarDate] as $calendarRequest): ?>
                                        <div class="alert alert-success p-2 mb-2">
                                            <strong><?= substr((string) $calendarRequest['requested_time'], 0, 5) ?></strong>
                                            <div class="small"><?= adminEscape($calendarRequest['patient_name']) ?></div>
                                            <div class="small"><?= adminEscape($calendarRequest['service_name']) ?> · <?= (int) $calendarRequest['duration_minutes'] ?> min</div>
                                            <a class="small" href="#request-<?= (int) $calendarRequest['id'] ?>">View request</a>
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
                                <option value="pending" <?= $request['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $request['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="cancelled" <?= $request['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <button class="btn btn-primary btn-sm mb-2" type="submit">Save status</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
