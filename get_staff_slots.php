
<?php
session_start();
require_once __DIR__ . '/db.php';

date_default_timezone_set('Africa/Lagos');
header('Content-Type: application/json; charset=utf-8');

function respond($success, $message = '', $slots = [], $staff = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'slots' => $slots,
        'staff' => $staff
    ]);
    exit;
}

if (!isset($_SESSION['matric'])) {
    http_response_code(401);
    respond(false, 'Please log in again.');
}

$staffId = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT);
$date = $_GET['date'] ?? '';

$dateObject = DateTime::createFromFormat('!Y-m-d', $date);

if (!$staffId || !$dateObject || $dateObject->format('Y-m-d') !== $date) {
    http_response_code(400);
    respond(false, 'Select a valid staff member and date.');
}

if ($date < date('Y-m-d')) {
    http_response_code(400);
    respond(false, 'Past dates are not allowed.');
}

try {
    // Get staff details
    $stmt = $conn->prepare(
        "SELECT id, fullname, role, faculty, department, building, office
         FROM lecturers WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$staff) {
        http_response_code(404);
        respond(false, 'Staff member not found.');
    }

    // Get the selected day's working hours
    $day = $dateObject->format('l');

    $stmt = $conn->prepare(
        "SELECT start_time, end_time
         FROM staff_availability
         WHERE lecturer_id = ?
         AND LOWER(day_of_week) = LOWER(?)
         LIMIT 1"
    );
    $stmt->bind_param('is', $staffId, $day);
    $stmt->execute();
    $availability = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$availability) {
        respond(true, "This staff member is not available on $day.", [], $staff);
    }

    $start = strtotime($availability['start_time']);
    $end = strtotime($availability['end_time']);

    if ($start === false || $end === false || $end <= $start) {
        throw new Exception('Invalid working hours.');
    }

    // Find existing pending and approved appointments
    $stmt = $conn->prepare(
        "SELECT appointment_time
         FROM appointments
         WHERE lecturer = ?
         AND appointment_date = ?
         AND status IN ('Pending', 'Approved')"
    );
    $stmt->bind_param('ss', $staff['fullname'], $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $booked = [];

    while ($row = $result->fetch_assoc()) {
        $booked[] = substr($row['appointment_time'], 0, 5);
    }
    $stmt->close();

    // Generate one-hour appointment slots
    $slots = [];

    for ($time = $start; $time + 3600 <= $end; $time += 3600) {
        $value = date('H:i', $time);

        if ($date === date('Y-m-d') && $time <= time()) {
            continue;
        }

        if (in_array($value, $booked, true)) {
            continue;
        }

        $slots[] = [
            'value' => $value,
            'label' => date('g:i A', $time) . ' - ' .
                       date('g:i A', $time + 3600)
        ];
    }

    $message = count($slots)
        ? 'Available appointment slots loaded successfully.'
        : 'No available time slots remain for this date.';

    respond(true, $message, $slots, $staff);

} catch (Throwable $e) {
    http_response_code(500);
    respond(false, 'Unable to load availability: ' . $e->getMessage());
}
