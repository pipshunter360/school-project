
<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['lecturer_id'])) {
    header("Location: staff_login.php");
    exit;
}

$staffId = (int) $_SESSION['lecturer_id'];
$staffName = $_SESSION['lecturer_name'] ?? 'NSUK Staff';

$days = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday'
];

if (empty($_SESSION['availability_csrf'])) {
    $_SESSION['availability_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

$schedule = [];

try {
    $stmt = $conn->prepare(
        "SELECT day_of_week, start_time, end_time
         FROM staff_availability
         WHERE lecturer_id = ?"
    );

    $stmt->bind_param("i", $staffId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $schedule[$row['day_of_week']] = [
            'start' => substr($row['start_time'], 0, 5),
            'end' => substr($row['end_time'], 0, 5)
        ];
    }

    $stmt->close();

} catch (Throwable $e) {
    $message = 'Unable to load your working hours. Please check the availability table.';
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['availability_csrf'], $_POST['csrf_token'])
    ) {
        $message = 'Security verification failed. Refresh and try again.';
        $messageType = 'error';

    } else {

        $submitted = $_POST['availability'] ?? [];
        $validSchedule = [];
        $error = '';

        foreach ($days as $day) {

            if (!isset($submitted[$day]['enabled'])) {
                continue;
            }

            $start = $submitted[$day]['start'] ?? '';
            $end = $submitted[$day]['end'] ?? '';

            if (
                !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) ||
                !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
            ) {
                $error = "Enter valid working hours for $day.";
                break;
            }

            if ($end <= $start) {
                $error = "The closing time must be later than the starting time on $day.";
                break;
            }

            $validSchedule[$day] = [
                'start' => $start,
                'end' => $end
            ];
        }

        if ($error !== '') {
            $message = $error;
            $messageType = 'error';

        } elseif (count($validSchedule) === 0) {
            $message = 'Please select at least one working day.';
            $messageType = 'error';

        } else {

            try {
                $conn->begin_transaction();

                // Replace only this staff member's working schedule.
                $delete = $conn->prepare(
                    "DELETE FROM staff_availability WHERE lecturer_id = ?"
                );

                $delete->bind_param("i", $staffId);
                $delete->execute();
                $delete->close();

                $insert = $conn->prepare(
                    "INSERT INTO staff_availability
                    (lecturer_id, day_of_week, start_time, end_time)
                    VALUES (?, ?, ?, ?)"
                );

                foreach ($validSchedule as $day => $hours) {
                    $insert->bind_param(
                        "isss",
                        $staffId,
                        $day,
                        $hours['start'],
                        $hours['end']
                    );

                    $insert->execute();
                }

                $insert->close();
                $conn->commit();

                $schedule = $validSchedule;

                $message = 'Your working hours have been saved successfully.';
                $messageType = 'success';

            } catch (Throwable $e) {
                $conn->rollback();

                $message = 'Could not save your working hours. Please check the database table structure.';
                $messageType = 'error';
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

<title>Staff Availability | NSUK</title>

<style>
:root {
    --green: #14532d;
    --dark: #0b3520;
    --light: #e8f5ec;
    --background: #f4f7f5;
    --text: #24352b;
    --muted: #718078;
    --border: #e4ebe6;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--background);
    color: var(--text);
    font-family: "Segoe UI", Arial, sans-serif;
}

header {
    background: var(--dark);
    color: white;
    padding: 20px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

header h2 {
    margin: 0;
    font-size: 19px;
}

header p {
    margin: 5px 0 0;
    color: #c8dfcf;
    font-size: 12px;
}

.back {
    background: white;
    color: var(--dark);
    padding: 11px 16px;
    border-radius: 7px;
    font-weight: bold;
    font-size: 13px;
    text-decoration: none;
}

.container {
    max-width: 900px;
    margin: 35px auto;
    padding: 0 18px;
}

.card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 28px;
    box-shadow: 0 4px 18px rgba(0,0,0,.04);
}

h1 {
    margin: 0 0 10px;
    font-size: 25px;
}

.description {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.7;
    margin-bottom: 25px;
}

.day-row {
    display: grid;
    grid-template-columns: 150px 1fr 1fr;
    align-items: center;
    gap: 15px;
    padding: 17px 0;
    border-bottom: 1px solid var(--border);
}

.day-name {
    font-weight: 700;
    font-size: 14px;
}

.day-check {
    display: flex;
    align-items: center;
    gap: 9px;
}

.day-check input {
    width: 18px;
    height: 18px;
    accent-color: var(--green);
}

label.time-label {
    display: block;
    margin-bottom: 6px;
    color: var(--muted);
    font-size: 11px;
}

input[type="time"] {
    width: 100%;
    padding: 11px;
    border: 1px solid var(--border);
    border-radius: 7px;
    font: inherit;
}

.day-row.disabled .time-fields {
    opacity: .35;
    pointer-events: none;
}

button {
    width: 100%;
    margin-top: 25px;
    padding: 15px;
    border: 0;
    border-radius: 8px;
    background: var(--green);
    color: white;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

button:hover {
    background: var(--dark);
}

.message {
    padding: 14px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-size: 13px;
}

.success {
    background: #e4f7e9;
    color: #166534;
}

.error {
    background: #feecec;
    color: #991b1b;
}

.note {
    margin-top: 22px;
    padding: 15px;
    border-radius: 8px;
    background: #fff8e7;
    color: #795600;
    font-size: 12px;
    line-height: 1.7;
}

@media(max-width: 600px) {
    .card {
        padding: 18px;
    }

    .day-row {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .day-name {
        grid-column: 1 / -1;
    }

    header h2 {
        font-size: 16px;
    }
}
</style>
</head>

<body>

<header>
    <div>
        <h2>NSUK Staff Portal</h2>
        <p>Working Hours and Appointment Availability</p>
    </div>

    <a href="staff_dashboard.php" class="back">← Dashboard</a>
</header>

<main class="container">
    <section class="card">

        <h1>Manage Your Availability</h1>

        <p class="description">
            Welcome, <?= h($staffName) ?>.
            Select the days you are available to receive student appointments
            and enter your working hours.
        </p>

        <?php if ($message !== ''): ?>
            <div class="message <?= h($messageType) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['availability_csrf']) ?>"
            >

            <?php foreach ($days as $day): ?>

                <?php
                $enabled = isset($schedule[$day]);
                $start = $schedule[$day]['start'] ?? '09:00';
                $end = $schedule[$day]['end'] ?? '16:00';
                ?>

                <div class="day-row <?= $enabled ? '' : 'disabled' ?>">

                    <div class="day-name">
                        <?= h($day) ?>
                    </div>

                    <label class="day-check">
                        <input
                            type="checkbox"
                            name="availability[<?= h($day) ?>][enabled]"
                            value="1"
                            <?= $enabled ? 'checked' : '' ?>
                            onchange="toggleDay(this)"
                        >
                        Available
                    </label>

                    <div class="time-fields">
                        <label class="time-label">Starting time</label>
                        <input
                            type="time"
                            name="availability[<?= h($day) ?>][start]"
                            value="<?= h($start) ?>"
                        >
                    </div>

                    <div class="time-fields">
                        <label class="time-label">Closing time</label>
                        <input
                            type="time"
                            name="availability[<?= h($day) ?>][end]"
                            value="<?= h($end) ?>"
                        >
                    </div>

                </div>

            <?php endforeach; ?>

            <button type="submit">Save Working Hours</button>

        </form>

        <div class="note">
            <strong>Important:</strong> Students will only see appointment
            slots within the working hours you set. Existing pending and
            approved bookings will be excluded from the available slots.
        </div>

    </section>
</main>

<script>
function toggleDay(checkbox) {
    const row = checkbox.closest('.day-row');

    if (checkbox.checked) {
        row.classList.remove('disabled');
    } else {
        row.classList.add('disabled');
    }
}

document.querySelectorAll('.day-row').forEach(row => {
    const checkbox = row.querySelector('input[type="checkbox"]');

    if (checkbox) {
        toggleDay(checkbox);
    }
});
</script>

</body>
</html>
