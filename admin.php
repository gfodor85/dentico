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
                $update = $pdo->prepare(
                    'UPDATE appointment_requests SET status = :status WHERE id = :id'
                );
                $update->execute(['status' => $status, 'id' => $requestId]);
                $_SESSION['admin_flash'] = $update->rowCount() > 0
                    ? 'Appointment request status updated.'
                    : 'No changes were made to that request.';
                header('Location: admin.php');
                exit;
            } catch (Throwable $exception) {
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
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <style>
        .admin-page { padding: 3rem 0; }
        .admin-panel { padding: 1.5rem; border: 1px solid #e5e7eb; border-radius: .5rem; background: #fff; }
        .request-card { border: 1px solid #e5e7eb; border-radius: .4rem; padding: 1rem; margin-bottom: 1rem; }
        .request-meta { color: #555; font-size: .9rem; }
        .admin-login { max-width: 460px; }
        .request-note { white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main class="container admin-page">
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

            <?php if ($databaseError): ?>
                <div class="alert alert-danger" role="alert">The requests could not be loaded. Check the local database connection.</div>
            <?php elseif ($requests === []): ?>
                <div class="alert alert-info" role="status">There are no appointment requests yet.</div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <article class="request-card">
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
