<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Budapest');
ini_set('session.use_strict_mode', '1');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
if (empty($_SESSION['contact_csrf_token'])) {
    $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore Dentico dental services and request an appointment online.">
    <title>Dentico | Thoughtful dental care</title>
    <link rel="icon" href="favicon.ico?v=2">
    <link rel="stylesheet" href="assets/css/dentico-ui.css">
</head>
<body class="dentico-home">
    <?php include __DIR__ . '/partials/ui-header.php'; ?>

    <main>
        <section class="dc-hero" id="home" aria-labelledby="home-heading">
            <div class="dc-container dc-hero__center">
                <div class="dc-hero__mark" aria-hidden="true">
                    <svg viewBox="0 0 240 240" focusable="false"><use xlink:href="assets/img/svg/symbols.svg#hero-img"></use></svg>
                </div>
                <p class="dc-hero__eyebrow">Welcome to</p>
                <h1 id="home-heading">Dentico</h1>
                <p class="dc-hero__tagline">Your Smile. Our Passion.</p>
                <p class="dc-hero__intro">Explore dental treatments and request a convenient appointment in just a few steps.</p>
                <div class="dc-hero__actions">
                    <a class="dc-btn" href="booking.php">Request an appointment <span aria-hidden="true">&rarr;</span></a>
                    <a class="dc-btn dc-btn--light" href="services.php">Explore treatments</a>
                </div>
                <p class="dc-hero__note">Requests are reviewed by the clinic before confirmation.</p>
            </div>
            <a href="#treatments" class="dc-hero__scroll">Explore Dentico <span aria-hidden="true">&darr;</span></a>
        </section>

        <section class="dc-section" id="treatments" aria-labelledby="services-heading">
            <div class="dc-container">
                <div class="dc-section-heading">
                    <div>
                        <span class="dc-kicker">Explore care</span>
                        <h2 id="services-heading">Discover your options</h2>
                    </div>
                    <p>Browse the services available through our online appointment request system.</p>
                </div>
                <div class="dc-grid-3">
                    <article class="dc-feature">
                        <img class="dc-feature__art" src="assets/img/general_care.png" alt="" loading="lazy" width="154" height="154">
                        <h3>General care</h3>
                        <p>Start with the essentials and explore everyday dental-care options.</p>
                        <a class="dc-inline-link" href="services.php">View treatments &rarr;</a>
                    </article>
                    <article class="dc-feature">
                        <img class="dc-feature__art" src="assets/img/cosmetic_dentistry.png" alt="" loading="lazy" width="154" height="154">
                        <h3>Cosmetic dentistry</h3>
                        <p>Learn about treatment choices focused on the appearance of your smile.</p>
                        <a class="dc-inline-link" href="services.php">View treatments &rarr;</a>
                    </article>
                    <article class="dc-feature">
                        <img class="dc-feature__art" src="assets/img/restorative_dentistry.png" alt="" loading="lazy" width="154" height="154">
                        <h3>Restorative dentistry</h3>
                        <p>Explore options for maintaining or restoring dental function.</p>
                        <a class="dc-inline-link" href="services.php">View treatments &rarr;</a>
                    </article>
                </div>
            </div>
        </section>

        <section class="dc-section dc-about" id="about" aria-labelledby="about-heading">
            <div class="dc-container dc-about__layout">
                <div class="dc-about__art" role="img" aria-label="Dental care setting"></div>
                <div>
                    <span class="dc-kicker">Simple from the start</span>
                    <h2 id="about-heading">An easier way to arrange your visit</h2>
                    <p class="dc-lead">Dentico brings treatment information and appointment requests together in one place. Your selected time is a request until the clinic confirms it.</p>
                    <ol class="dc-steps">
                        <li><b aria-hidden="true">1</b><div><strong>Select a treatment</strong><div>See the services and their planned durations.</div></div></li>
                        <li><b aria-hidden="true">2</b><div><strong>Choose a suitable time</strong><div>Available times appear in 15-minute increments.</div></div></li>
                        <li><b aria-hidden="true">3</b><div><strong>Wait for confirmation</strong><div>The clinic reviews your request before confirming the appointment.</div></div></li>
                    </ol>
                </div>
            </div>
        </section>

        <section class="dc-cta" aria-labelledby="ready-heading">
            <div class="dc-container dc-cta__inner">
                <div>
                    <h2 id="ready-heading">Ready to request your next visit?</h2>
                    <p>Monday–Friday, 08:00–17:00. Browse availability online.</p>
                </div>
                <a class="dc-btn dc-btn--light" href="booking.php">Find an appointment time <span aria-hidden="true">&rarr;</span></a>
            </div>
        </section>

        <section class="dc-section dc-contact" id="contact" aria-labelledby="contact-heading">
            <div class="dc-container dc-contact__layout">
                <div class="dc-contact__info">
                    <span class="dc-kicker">Get in touch</span>
                    <h2 id="contact-heading">Have a question?</h2>
                    <p class="dc-lead">Use the form to send a general enquiry. For a visit, please use the dedicated appointment request page.</p>
                    <div class="dc-contact__hours">
                        <strong>Appointment hours</strong>
                        <p>Monday–Friday<br>08:00–17:00</p>
                        <a class="dc-inline-link" href="booking.php">Request an appointment &rarr;</a>
                    </div>
                </div>
                <?php include __DIR__ . '/partials/form.php'; ?>
            </div>
        </section>

        <?php
        // Public browser keys must be restricted by Google Cloud API and HTTP referrer.
        $mapsApiKey = trim((string) (getenv('GOOGLE_MAPS_API_KEY') ?: ''));
        $mapsConfigPath = __DIR__ . '/config/maps.php';
        if (is_file($mapsConfigPath)) {
            $mapsConfig = require $mapsConfigPath;
            if (is_array($mapsConfig) && is_string($mapsConfig['api_key'] ?? null)) {
                $localMapsKey = trim($mapsConfig['api_key']);
                if ($localMapsKey !== '') {
                    $mapsApiKey = $localMapsKey;
                }
            }
        }
        ?>
        <?php if ($mapsApiKey !== ''): ?>
            <section class="dc-map" aria-label="Clinic location map">
                <div class="dc-container">
                    <h2>Map preview</h2>
                    <p>Demo map location. Configure the correct clinic coordinates before deploying this website.</p>
                    <div id="map"></div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="dc-footer">
        <div class="dc-container dc-footer__inner">
            <span>&copy; <?= date('Y') ?> Dentico. Demo website.</span>
            <span><a href="services.php">Treatments</a> &nbsp;·&nbsp; <a href="booking.php">Appointments</a></span>
        </div>
    </footer>

    <?php if ($mapsApiKey !== ''): ?>
        <script src="assets/js/map.js"></script>
        <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($mapsApiKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>&amp;callback=initMap"></script>
    <?php endif; ?>
</body>
</html>
