<?php
declare(strict_types=1);

require_once __DIR__ . '/app/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function sendSlotsResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$serviceId = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$dateValue = isset($_GET['date']) && is_string($_GET['date']) ? $_GET['date'] : '';

$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$dateErrors = DateTimeImmutable::getLastErrors();
$validDate = $date !== false
    && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
    && $date->format('Y-m-d') === $dateValue;

if ($serviceId === false || !$validDate || $dateValue < date('Y-m-d')) {
    sendSlotsResponse(['slots' => [], 'message' => 'Choose a valid service and a date today or later.'], 400);
}

if ((int) $date->format('N') > 5) {
    sendSlotsResponse(['slots' => [], 'message' => 'Appointments are available Monday through Friday only.']);
}

try {
    $pdo = denticoDatabase();

    $serviceStatement = $pdo->prepare(
        'SELECT duration_minutes FROM services WHERE id = :id AND is_active = 1'
    );
    $serviceStatement->execute(['id' => $serviceId]);
    $service = $serviceStatement->fetch();

    if (!$service) {
        sendSlotsResponse(['slots' => [], 'message' => 'This service is not currently available.'], 404);
    }

    $appointmentsStatement = $pdo->prepare(
        'SELECT ar.requested_time, s.duration_minutes
         FROM appointment_requests ar
         INNER JOIN services s ON s.id = ar.service_id
         WHERE ar.requested_date = :requested_date
           AND ar.status = "confirmed"'
    );
    $appointmentsStatement->execute(['requested_date' => $dateValue]);
    $appointments = $appointmentsStatement->fetchAll();

    $busyIntervals = [];
    foreach ($appointments as $appointment) {
        $busyStart = ((int) substr((string) $appointment['requested_time'], 0, 2) * 60)
            + (int) substr((string) $appointment['requested_time'], 3, 2);
        $busyIntervals[] = [
            'start' => $busyStart,
            'end' => $busyStart + (int) $appointment['duration_minutes'],
        ];
    }

    $duration = (int) $service['duration_minutes'];
    $slots = [];

    // Offer start times every 15 minutes, provided the full service fits before 17:00.
    for ($start = 8 * 60; $start + $duration <= 17 * 60; $start += 15) {
        $overlaps = false;

        foreach ($busyIntervals as $busy) {
            if ($start < $busy['end'] && $busy['start'] < $start + $duration) {
                $overlaps = true;
                break;
            }
        }

        if (!$overlaps) {
            $slots[] = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
        }
    }

    sendSlotsResponse([
        'slots' => $slots,
        'message' => $slots === [] ? 'No available times for this date and service. Please choose another date.' : '',
    ]);
} catch (Throwable $exception) {
    error_log('Dentico available slots error: ' . $exception->getMessage());
    sendSlotsResponse(['slots' => [], 'message' => 'Available times could not be loaded. Please try again.'], 500);
}
