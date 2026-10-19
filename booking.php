<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/app/database.php';

if (empty($_SESSION['booking_csrf_token'])) {
    $_SESSION['booking_csrf_token'] = bin2hex(random_bytes(32));
}

function bookingEscape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$services = [];
$errors = [];
$success = false;
$databaseError = false;

$form = [
    'service_id' => '',
    'patient_name' => '',
    'email' => '',
    'phone' => '',
    'requested_date' => '',
    'requested_time' => '',
    'message' => '',
];

try {
    $pdo = denticoDatabase();
    $statement = $pdo->query(
        'SELECT id, name, duration_minutes
         FROM services
         WHERE is_active = 1
         ORDER BY display_order ASC, name ASC'
    );
    $services = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Dentico booking page error: ' . $exception->getMessage());
    $databaseError = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$databaseError) {
    foreach ($form as $key => $unused) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $form[$key] = trim($_POST[$key]);
        }
    }

    $postedToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';

    if (!hash_equals($_SESSION['booking_csrf_token'], $postedToken)) {
        $errors[] = 'The form expired or could not be verified. Please reload the page and try again.';
    }

    if ($form['patient_name'] === '' || mb_strlen($form['patient_name']) > 120) {
        $errors[] = 'Please enter your name (maximum 120 characters).';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 254) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($form['phone'] === '' || mb_strlen($form['phone']) > 40) {
        $errors[] = 'Please enter your phone number (maximum 40 characters).';
    }

    if (mb_strlen($form['message']) > 1000) {
        $errors[] = 'The optional note must be 1000 characters or fewer.';
    }

    $serviceId = filter_var($form['service_id'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($serviceId === false) {
        $errors[] = 'Please select a service.';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $form['requested_date']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validDate = $date !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $date->format('Y-m-d') === $form['requested_date'];

    if (!$validDate || $form['requested_date'] < date('Y-m-d')) {
        $errors[] = 'Please select a valid date that is today or in the future.';
    } elseif ((int) $date->format('N') > 5) {
        $errors[] = 'Appointment requests are accepted Monday through Friday only.';
    }

    if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $form['requested_time'])) {
        $errors[] = 'Please select a valid time.';
    } else {
        $requestedStart = (int) substr($form['requested_time'], 0, 2) * 60
            + (int) substr($form['requested_time'], 3, 2);

        if ($requestedStart < 8 * 60 || $requestedStart >= 17 * 60) {
            $errors[] = 'Please choose a start time between 08:00 and 16:59, Monday to Friday.';
        } elseif ($requestedStart % 15 !== 0) {
            $errors[] = 'Please choose one of the available 15-minute start times.';
        } elseif (
            $form['requested_date'] === date('Y-m-d')
            && $requestedStart <= (((int) date('H') * 60) + (int) date('i'))
        ) {
            $errors[] = 'Please choose a start time that is still in the future.';
        }
    }

    if ($errors === []) {
        try {
            $serviceCheck = $pdo->prepare(
                'SELECT id, duration_minutes FROM services WHERE id = :id AND is_active = 1'
            );
            $serviceCheck->execute(['id' => $serviceId]);
            $selectedService = $serviceCheck->fetch();

            if (!$selectedService) {
                $errors[] = 'That service is no longer available. Please select another service.';
            } else {
                $startMinutes = (int) substr($form['requested_time'], 0, 2) * 60
                    + (int) substr($form['requested_time'], 3, 2);
                $endMinutes = $startMinutes + (int) $selectedService['duration_minutes'];

                if ($startMinutes < 8 * 60 || $endMinutes > 17 * 60) {
                    $errors[] = 'The selected service must start at or after 08:00 and finish by 17:00.';
                } else {
                    // Recheck availability on submission in case another appointment was confirmed
                    // after the visitor loaded the available-times list.
                    $busyStatement = $pdo->prepare(
                        'SELECT ar.requested_time, s.duration_minutes
                         FROM appointment_requests ar
                         INNER JOIN services s ON s.id = ar.service_id
                         WHERE ar.requested_date = :requested_date
                           AND ar.status = "confirmed"'
                    );
                    $busyStatement->execute(['requested_date' => $form['requested_date']]);

                    foreach ($busyStatement->fetchAll() as $busyAppointment) {
                        $busyStart = (int) substr((string) $busyAppointment['requested_time'], 0, 2) * 60
                            + (int) substr((string) $busyAppointment['requested_time'], 3, 2);
                        $busyEnd = $busyStart + (int) $busyAppointment['duration_minutes'];

                        if ($startMinutes < $busyEnd && $busyStart < $endMinutes) {
                            $errors[] = 'That time is no longer available because it overlaps with a confirmed appointment. Please choose another time.';
                            break;
                        }
                    }
                }
            }

            if ($errors === []) {
                $insert = $pdo->prepare(
                    'INSERT INTO appointment_requests
                        (service_id, patient_name, email, phone, requested_date, requested_time, message)
                     VALUES
                        (:service_id, :patient_name, :email, :phone, :requested_date, :requested_time, :message)'
                );

                $insert->execute([
                    'service_id' => $serviceId,
                    'patient_name' => $form['patient_name'],
                    'email' => $form['email'],
                    'phone' => $form['phone'],
                    'requested_date' => $form['requested_date'],
                    'requested_time' => $form['requested_time'],
                    'message' => $form['message'] !== '' ? $form['message'] : null,
                ]);

                $success = true;
                $form = [
                    'service_id' => '',
                    'patient_name' => '',
                    'email' => '',
                    'phone' => '',
                    'requested_date' => '',
                    'requested_time' => '',
                    'message' => '',
                ];
                $_SESSION['booking_csrf_token'] = bin2hex(random_bytes(32));
            }
        } catch (Throwable $exception) {
            error_log('Dentico appointment request error: ' . $exception->getMessage());
            $errors[] = 'We could not save your request. Please try again later.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request an Appointment | Dentico</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <style>
        html { min-height: 100%; }

        body.booking-body {
            min-height: 100vh;
            margin: 0;
            background-color: #f3f6fa !important;
            background-image: url("assets/img/bg_2_blurred.jpg") !important;
            background-position: center center;
            background-size: cover;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }

        body.booking-body .booking-page {
            padding: 4rem 0;
            min-height: 100vh;
        }

        body.booking-body .booking-form {
            max-width: 760px;
            padding: 2rem;
            border: 1px solid rgba(229, 231, 235, 0.75);
            border-radius: .5rem;
            background: rgba(255, 255, 255, 0.60) !important;
        }

        body.booking-body .booking-form label,
        body.booking-body .booking-form .form-text,
        body.booking-body header h1,
        body.booking-body header p {
            color: #212529;
        }

        .booking-note { font-size: .95rem; }

        @media (max-width: 576px) {
            body.booking-body .booking-form { padding: 1.25rem; }
            body.booking-body .booking-page { padding: 2rem 15px; }
        }
    </style>
</head>
<body class="booking-body">
    <main class="container booking-page">
        <p><a href="index.php">&larr; Back to Dentico homepage</a></p>
        <header class="mb-4">
            <h1>Request an Appointment</h1>
            <p class="booking-note">Opening hours: Monday–Friday, 08:00–17:00. The clinic will contact you to confirm the appointment.</p>
        </header>

        <?php if ($databaseError): ?>
            <div class="alert alert-danger" role="alert">
                We couldn't load the booking form. Please check the local database configuration.
            </div>
        <?php elseif ($success): ?>
            <div class="alert alert-success" role="status">
                Your appointment request has been received. This is not yet a confirmed appointment; the clinic will contact you.
            </div>
        <?php endif; ?>

        <?php if (!$databaseError && $errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <p class="mb-2">Please check the following:</p>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= bookingEscape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$databaseError && $services === []): ?>
            <div class="alert alert-info" role="status">There are no active services available for booking requests.</div>
        <?php elseif (!$databaseError): ?>
            <form class="booking-form" method="post" action="booking.php">
                <input type="hidden" name="csrf_token" value="<?= bookingEscape($_SESSION['booking_csrf_token']) ?>">

                <div class="form-group">
                    <label for="service_id">Service</label>
                    <select class="form-control" id="service_id" name="service_id" required>
                        <option value="">Choose a service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service['id'] ?>" <?= $form['service_id'] === (string) $service['id'] ? 'selected' : '' ?>>
                                <?= bookingEscape($service['name']) ?> (<?= (int) $service['duration_minutes'] ?> min)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="patient_name">Full name</label>
                    <input class="form-control" id="patient_name" name="patient_name" type="text" maxlength="120" value="<?= bookingEscape($form['patient_name']) ?>" autocomplete="name" required>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="email">Email address</label>
                        <input class="form-control" id="email" name="email" type="email" maxlength="254" value="<?= bookingEscape($form['email']) ?>" autocomplete="email" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="phone">Phone number</label>
                        <input class="form-control" id="phone" name="phone" type="tel" maxlength="40" value="<?= bookingEscape($form['phone']) ?>" autocomplete="tel" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="requested_date">Preferred date</label>
                        <input class="form-control" id="requested_date" name="requested_date" type="date" min="<?= date('Y-m-d') ?>" value="<?= bookingEscape($form['requested_date']) ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="requested_time">Available time</label>
                        <select class="form-control" id="requested_time" name="requested_time" required disabled>
                            <option value="">Choose a service and date first</option>
                        </select>
                        <small id="availability_message" class="form-text text-muted" aria-live="polite">Choose a service and date to see available times. Times are shown in 15-minute increments.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="message">Optional note</label>
                    <textarea class="form-control" id="message" name="message" rows="3" maxlength="1000"><?= bookingEscape($form['message']) ?></textarea>
                    <small class="form-text text-muted">Please do not include sensitive medical information in this field.</small>
                </div>

                <button class="btn btn-primary" type="submit">Send appointment request</button>
            </form>
        <?php endif; ?>
    </main>
    <?php if (!$databaseError && $services !== []): ?>
    <script>
    (() => {
        const serviceSelect = document.getElementById('service_id');
        const dateInput = document.getElementById('requested_date');
        const timeSelect = document.getElementById('requested_time');
        const availabilityMessage = document.getElementById('availability_message');
        const initiallySelectedTime = <?= json_encode($form['requested_time'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function setPlaceholder(message) {
            timeSelect.replaceChildren();
            const option = document.createElement('option');
            option.value = '';
            option.textContent = message;
            timeSelect.appendChild(option);
            timeSelect.disabled = true;
        }

        async function loadAvailableTimes(preserveSelection = false) {
            const serviceId = serviceSelect.value;
            const date = dateInput.value;
            const previousTime = preserveSelection ? initiallySelectedTime : '';

            if (!serviceId || !date) {
                setPlaceholder('Choose a service and date first');
                availabilityMessage.textContent = 'Choose a service and date to see available times.';
                return;
            }

            setPlaceholder('Loading available times…');
            availabilityMessage.textContent = 'Checking availability…';

            try {
                const params = new URLSearchParams({ service_id: serviceId, date });
                const response = await fetch('available-slots.php?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Available times could not be loaded.');
                }

                timeSelect.replaceChildren();
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = result.slots.length
                    ? 'Choose an available time'
                    : 'No available times';
                timeSelect.appendChild(placeholder);

                result.slots.forEach((slot) => {
                    const option = document.createElement('option');
                    option.value = slot;
                    option.textContent = slot;
                    if (previousTime && previousTime === slot) {
                        option.selected = true;
                    }
                    timeSelect.appendChild(option);
                });

                timeSelect.disabled = result.slots.length === 0;
                availabilityMessage.textContent = result.message
                    || 'The full service duration must fit before 17:00. Only confirmed appointments block a time.';
            } catch (error) {
                setPlaceholder('Availability unavailable');
                availabilityMessage.textContent = error.message || 'Available times could not be loaded. Please reload the page.';
            }
        }

        serviceSelect.addEventListener('change', () => loadAvailableTimes());
        dateInput.addEventListener('change', () => loadAvailableTimes());
        loadAvailableTimes(true);
    })();
    </script>
    <?php endif; ?>
</body>
</html>
