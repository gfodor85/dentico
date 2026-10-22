<?php
declare(strict_types=1);

require_once __DIR__ . '/app/database.php';

$services = [];
$loadError = false;

try {
    $pdo = denticoDatabase();
    $statement = $pdo->query(
        'SELECT id, name, description, duration_minutes, price
         FROM services
         WHERE is_active = 1
         ORDER BY display_order ASC, name ASC'
    );
    $services = $statement->fetchAll();
} catch (Throwable $exception) {
    // Keep technical database details out of the public page.
    error_log('Dentico services page error: ' . $exception->getMessage());
    $loadError = true;
}

function escapeHtml(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dental Services | Dentico</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/dentico-ui.css">
</head>
<body class="dentico-services">
    <?php include __DIR__ . '/partials/ui-header.php'; ?>
    <main class="container services-page">
        <header class="dc-page-header mb-4">
            <span class="dc-kicker">Treatment catalogue</span>
            <h1>Explore our services</h1>
            <p class="dc-lead">Find out which treatments are available and how much time to allow. Appointments are confirmed after a request is reviewed.</p>
        </header>

        <?php if ($loadError): ?>
            <div class="alert alert-danger service-alert" role="alert">
                We couldn't load the services right now. Please check the local database configuration.
            </div>
        <?php elseif ($services === []): ?>
            <div class="alert alert-info service-alert" role="status">
                No active services are available yet.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($services as $service): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <article class="service-card">
                            <h2><?= escapeHtml($service['name']) ?></h2>

                            <?php if (!empty($service['description'])): ?>
                                <p><?= nl2br(escapeHtml($service['description'])) ?></p>
                            <?php endif; ?>

                            <p class="service-meta mb-1">
                                Duration: <?= (int) $service['duration_minutes'] ?> minutes
                            </p>

                            <p class="service-price">
                                <?php if ($service['price'] !== null): ?>
                                    Price: <?= number_format((float) $service['price'], 2, '.', ',') ?>
                                <?php else: ?>
                                    Price: To be confirmed
                                <?php endif; ?>
                            </p>
                            <div class="service-card__cta"><a class="dc-btn dc-btn--outline" href="booking.php">Request an appointment &rarr;</a></div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
