
<?php
session_start();
require_once __DIR__ . '/db.php';

date_default_timezone_set('Africa/Lagos');

if (!isset($_SESSION['matric'])) {
    header('Location: student_login.php');
    exit;
}

$matric = $_SESSION['matric'];
$message = '';
$messageType = '';
$selectedStaff = '';
$selectedDate = '';
$selectedTime = '';
$reason = '';

if (empty($_SESSION['booking_csrf'])) {
    $_SESSION['booking_csrf'] = bin2hex(random_bytes(32));
}

function jsonResponse($success, $message, $slots = [], $staff = null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'slots' => $slots,
        'staff' => $staff
    ]);
    exit;
}

function getAvailableSlots($conn, $staffId, $staffName, $date) {
    $day = date('l', strtotime($date));

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
        return [];
    }

    $start = strtotime($availability['start_time']);
    $end = strtotime($availability['end_time']);

    if ($start === false || $end === false || $end <= $start) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT appointment_time
         FROM appointments
         WHERE lecturer = ?
         AND appointment_date = ?
         AND status IN ('Pending', 'Approved')"
    );

    $stmt->bind_param('ss', $staffName, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $booked = [];

    while ($row = $result->fetch_assoc()) {
        $booked[] = substr($row['appointment_time'], 0, 5);
    }

    $stmt->close();

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

    return $slots;
}

/*
|--------------------------------------------------------------------------
| AJAX: LOAD AVAILABLE TIME SLOTS
|--------------------------------------------------------------------------
*/

if (isset($_GET['action']) && $_GET['action'] === 'slots') {
    $staffId = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT);
    $date = $_GET['date'] ?? '';

    $dateObject = DateTime::createFromFormat('!Y-m-d', $date);

    if (!$staffId || !$dateObject ||
        $dateObject->format('Y-m-d') !== $date) {
        jsonResponse(false, 'Please select a valid staff member and date.');
    }

    if ($date < date('Y-m-d')) {
        jsonResponse(false, 'Past dates cannot be selected.');
    }

    $stmt = $conn->prepare(
        "SELECT id, fullname, role, faculty, department, building, office
         FROM lecturers
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$staff) {
        jsonResponse(false, 'The selected staff member was not found.');
    }

    $slots = getAvailableSlots(
        $conn,
        $staffId,
        $staff['fullname'],
        $date
    );

    if (!$slots) {
        $day = date('l', strtotime($date));
        jsonResponse(
            true,
            "No available appointment slots for this date ($day).",
            [],
            $staff
        );
    }

    jsonResponse(
        true,
        'Available appointment times loaded.',
        $slots,
        $staff
    );
}

/*
|--------------------------------------------------------------------------
| LOAD STAFF MEMBERS
|--------------------------------------------------------------------------
*/

$staffMembers = [];

$result = $conn->query(
    "SELECT id, fullname, role, faculty, department, building, office
     FROM lecturers
     ORDER BY fullname ASC"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffMembers[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| PROCESS APPOINTMENT BOOKING
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selectedStaff = $_POST['staff_id'] ?? '';
    $selectedDate = $_POST['appointment_date'] ?? '';
    $selectedTime = $_POST['appointment_time'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['booking_csrf'], $csrf)) {
        $message = 'Your session expired. Please refresh the page and try again.';
        $messageType = 'error';

    } elseif (
        !ctype_digit((string)$selectedStaff) ||
        empty($selectedDate) ||
        empty($selectedTime) ||
        empty($reason)
    ) {
        $message = 'Please complete all the required fields.';
        $messageType = 'error';

    } elseif (strlen($reason) > 1000) {
        $message = 'Your appointment reason is too long.';
        $messageType = 'error';

    } else {

        $dateObject = DateTime::createFromFormat(
            '!Y-m-d',
            $selectedDate
        );

        $timeObject = DateTime::createFromFormat(
            '!H:i',
            $selectedTime
        );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $selectedDate ||
            !$timeObject ||
            $timeObject->format('H:i') !== $selectedTime
        ) {
            $message = 'Please select a valid date and appointment time.';
            $messageType = 'error';

        } elseif ($selectedDate < date('Y-m-d')) {
            $message = 'You cannot book an appointment for a past date.';
            $messageType = 'error';

        } else {

            $staffId = (int)$selectedStaff;

            $stmt = $conn->prepare(
                "SELECT fullname
                 FROM lecturers
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->bind_param('i', $staffId);
            $stmt->execute();
            $staff = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$staff) {
                $message = 'Selected staff member does not exist.';
                $messageType = 'error';

            } else {

                $slots = getAvailableSlots(
                    $conn,
                    $staffId,
                    $staff['fullname'],
                    $selectedDate
                );

                $validSlot = false;

                foreach ($slots as $slot) {
                    if ($slot['value'] === $selectedTime) {
                        $validSlot = true;
                        break;
                    }
                }

                if (!$validSlot) {
                    $message = 'That appointment time is no longer available. Please select another time.';
                    $messageType = 'error';

                } else {

                    try {
                        $conn->begin_transaction();

                        // Check again before inserting.
                        $stmt = $conn->prepare(
                            "SELECT id
                             FROM appointments
                             WHERE lecturer = ?
                             AND appointment_date = ?
                             AND appointment_time = ?
                             AND status IN ('Pending', 'Approved')
                             LIMIT 1
                             FOR UPDATE"
                        );

                        $stmt->bind_param(
                            'sss',
                            $staff['fullname'],
                            $selectedDate,
                            $selectedTime
                        );

                        $stmt->execute();
                        $conflict = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($conflict) {
                            $conn->rollback();

                            $message = 'Sorry, another student has already booked that time. Please choose another slot.';
                            $messageType = 'error';

                        } else {

                            $status = 'Pending';

                            $stmt = $conn->prepare(
                                "INSERT INTO appointments
                                (student_matric, lecturer, appointment_date,
                                 appointment_time, reason, status)
                                 VALUES (?, ?, ?, ?, ?, ?)"
                            );

                            $stmt->bind_param(
                                'ssssss',
                                $matric,
                                $staff['fullname'],
                                $selectedDate,
                                $selectedTime,
                                $reason,
                                $status
                            );

                            $stmt->execute();
                            $stmt->close();

                            $conn->commit();

                            $_SESSION['booking_csrf'] =
                                bin2hex(random_bytes(32));

                            header(
                                'Location: student_dashboard.php?booking=success'
                            );
                            exit;
                        }

                    } catch (Throwable $e) {
                        try {
                            $conn->rollback();
                        } catch (Throwable $ignored) {
                        }

                        $message = 'Unable to complete your booking. Please try again.';
                        $messageType = 'error';
                    }
                }
            }
        }
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Book Appointment | NSUK Student Portal</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f7f5;
    color: #1d2930;
}

header {
    background: #063d2d;
    color: white;
    padding: 18px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand img {
    width: 48px;
    height: 48px;
    object-fit: contain;
    background: #063d2d;
    border-radius: 8px;
    padding: 3px;
}

.brand h2 {
    font-size: 18px;
}

.brand p {
    font-size: 12px;
    color: #e5d39a;
    margin-top: 4px;
}

nav {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
}

nav a {
    color: white;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
}

nav a:hover {
    color: #e4c56b;
}

.container {
    width: 92%;
    max-width: 850px;
    margin: 35px auto;
}

.breadcrumb {
    color: #68766e;
    font-size: 13px;
    margin-bottom: 18px;
}

.card {
    background: white;
    border-radius: 13px;
    padding: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.06);
    border: 1px solid #e7eee9;
}

.card h1 {
    color: #064b35;
    font-size: 25px;
    margin-bottom: 8px;
}

.subtitle {
    color: #718078;
    font-size: 14px;
    margin-bottom: 25px;
}

label {
    display: block;
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 8px;
    color: #263a30;
}

.form-group {
    margin-bottom: 21px;
}

input, select, textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #d5dfd8;
    border-radius: 7px;
    font-size: 14px;
    background: #fff;
    outline: none;
}

input:focus, select:focus, textarea:focus {
    border-color: #08764d;
    box-shadow: 0 0 0 3px rgba(8,118,77,0.09);
}

textarea {
    resize: vertical;
    min-height: 110px;
}

.staff-info {
    display: none;
    background: #f1f7f3;
    border-left: 4px solid #c7a64b;
    padding: 17px;
    border-radius: 7px;
    margin-bottom: 22px;
    line-height: 1.8;
    font-size: 13px;
}

.staff-info strong {
    color: #064b35;
}

.slot-message {
    font-size: 13px;
    margin-top: 9px;
    color: #64736a;
}

.alert {
    padding: 14px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert.error {
    background: #fff0ef;
    color: #a52c24;
    border: 1px solid #f1c5c1;
}

.alert.success {
    background: #eaf7ef;
    color: #17613a;
    border: 1px solid #b9dfc6;
}

button {
    width: 100%;
    padding: 15px;
    background: #07543a;
    color: white;
    border: none;
    border-radius: 7px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.2s;
}

button:hover {
    background: #063d2d;
}

button:disabled {
    background: #9aa99f;
    cursor: not-allowed;
}

.back-link {
    display: inline-block;
    margin-top: 20px;
    color: #07543a;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
}

footer {
    text-align: center;
    padding: 25px;
    color: #718078;
    font-size: 12px;
}

@media(max-width: 600px) {
    header {
        padding: 15px 5%;
    }

    nav {
        gap: 12px;
    }

    .container {
        margin: 22px auto;
    }

    .card {
        padding: 21px;
    }

    .card h1 {
        font-size: 21px;
    }
}
</style>
</head>

<body>

<header>
    <div class="brand">
        <img src="logo-new.png" alt="NSUK Logo">
        <div>
            <h2>NSUK Student Portal</h2>
            <p>Nasarawa State University, Keffi</p>
        </div>
    </div>

    <nav>
        <a href="student_dashboard.php">Dashboard</a>
        <a href="book_appointment.php">Book Appointment</a>
        <a href="index.html">Home</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <div class="breadcrumb">
        Student Portal / Appointments / New Request
    </div>

    <section class="card">

        <h1>Book an Appointment</h1>

        <p class="subtitle">
            Select a staff member, choose an available date and time,
            and submit your appointment request.
        </p>

        <?php if ($message): ?>
            <div class="alert <?= h($messageType) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="book_appointment.php" id="bookingForm">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['booking_csrf']) ?>"
            >

            <div class="form-group">
                <label for="staff_id">Select Staff Member</label>

                <select name="staff_id" id="staff_id" required>
                    <option value="">Choose a staff member</option>

                    <?php foreach ($staffMembers as $staff): ?>
                        <option
                            value="<?= (int)$staff['id'] ?>"
                            <?= (string)$selectedStaff === (string)$staff['id'] ? 'selected' : '' ?>
                        >
                            <?= h($staff['fullname']) ?>
                            <?= !empty($staff['department']) ? ' - ' . h($staff['department']) : '' ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="staff-info" id="staffInfo"></div>

            <div class="form-group">
                <label for="appointment_date">Appointment Date</label>

                <input
                    type="date"
                    name="appointment_date"
                    id="appointment_date"
                    min="<?= date('Y-m-d') ?>"
                    value="<?= h($selectedDate) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="appointment_time">Available Appointment Time</label>

                <select
                    name="appointment_time"
                    id="appointment_time"
                    required
                    disabled
                >
                    <option value="">Select staff and date first</option>
                </select>

                <p class="slot-message" id="slotMessage">
                    Available time slots will appear here.
                </p>
            </div>

            <div class="form-group">
                <label for="reason">Reason for Appointment</label>

                <textarea
                    name="reason"
                    id="reason"
                    maxlength="1000"
                    placeholder="Briefly explain why you want to meet the staff member..."
                    required
                ><?= h($reason) ?></textarea>
            </div>

            <button type="submit" id="submitButton" disabled>
                Submit Appointment Request
            </button>

        </form>

        <a href="student_dashboard.php" class="back-link">
            &larr; Back to Dashboard
        </a>

    </section>

</main>

<footer>
    &copy; <?= date('Y') ?> Nasarawa State University, Keffi.
    Student Appointment Booking System.
</footer>

<script>
const staffSelect = document.getElementById('staff_id');
const dateInput = document.getElementById('appointment_date');
const timeSelect = document.getElementById('appointment_time');
const slotMessage = document.getElementById('slotMessage');
const staffInfo = document.getElementById('staffInfo');
const submitButton = document.getElementById('submitButton');

const previousTime = <?= json_encode($selectedTime) ?>;

async function loadSlots() {
    const staffId = staffSelect.value;
    const date = dateInput.value;

    timeSelect.innerHTML = '<option value="">Loading available times...</option>';
    timeSelect.disabled = true;
    submitButton.disabled = true;
    staffInfo.style.display = 'none';

    if (!staffId || !date) {
        timeSelect.innerHTML =
            '<option value="">Select staff and date first</option>';

        slotMessage.textContent =
            'Available time slots will appear here.';
        return;
    }

    slotMessage.textContent = 'Checking staff availability...';

    try {
        const response = await fetch(
            'book_appointment.php?action=slots' +
            '&staff_id=' + encodeURIComponent(staffId) +
            '&date=' + encodeURIComponent(date),
            {
                headers: {
                    'Accept': 'application/json'
                }
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to load time slots.');
        }

        if (data.staff) {
            const s = data.staff;

            staffInfo.innerHTML = `
                <strong>Staff Information</strong><br>
                <strong>Name:</strong> ${escapeHTML(s.fullname || 'Not provided')}<br>
                <strong>Role:</strong> ${escapeHTML(s.role || 'Not provided')}<br>
                <strong>Faculty:</strong> ${escapeHTML(s.faculty || 'Not provided')}<br>
                <strong>Department:</strong> ${escapeHTML(s.department || 'Not provided')}<br>
                <strong>Building:</strong> ${escapeHTML(s.building || 'Not provided')}<br>
                <strong>Office:</strong> ${escapeHTML(s.office || 'Not provided')}
            `;

            staffInfo.style.display = 'block';
        }

        timeSelect.innerHTML =
            '<option value="">Choose an available time</option>';

        if (!data.slots || data.slots.length === 0) {
            timeSelect.innerHTML =
                '<option value="">No available times</option>';

            slotMessage.textContent = data.message;
            return;
        }

        data.slots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.value;
            option.textContent = slot.label;

            if (slot.value === previousTime) {
                option.selected = true;
            }

            timeSelect.appendChild(option);
        });

        timeSelect.disabled = false;
        submitButton.disabled = false;

        slotMessage.textContent = data.message;

    } catch (error) {
        timeSelect.innerHTML =
            '<option value="">Unable to load times</option>';

        slotMessage.textContent = error.message;
    }
}

function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, function(character) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[character];
    });
}

staffSelect.addEventListener('change', loadSlots);
dateInput.addEventListener('change', loadSlots);

document.getElementById('bookingForm').addEventListener('submit', function(event) {
    if (
        !staffSelect.value ||
        !dateInput.value ||
        !timeSelect.value
    ) {
        event.preventDefault();
        alert('Please select a staff member, date and available time.');
    }
});

if (staffSelect.value && dateInput.value) {
    loadSlots();
}
</script>

</body>
</html>
