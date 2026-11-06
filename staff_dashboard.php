
<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['lecturer_id'])) {
    header("Location: staff_login.php");
    exit;
}

$staffName = trim($_SESSION['lecturer_name'] ?? 'NSUK Staff');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$total = 0;
$pending = 0;
$approved = 0;
$rejected = 0;
$recentAppointments = [];
$errorMessage = '';

/* Appointment statistics for the logged-in staff member. */
$sql = "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(status)) = 'pending'
                THEN 1 ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(status)) = 'approved'
                THEN 1 ELSE 0 END), 0) AS approved,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(status)) = 'rejected'
                THEN 1 ELSE 0 END), 0) AS rejected
        FROM appointments
        WHERE LOWER(TRIM(lecturer)) = LOWER(TRIM(?))";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $staffName);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        if ($counts = $result->fetch_assoc()) {
            $total = (int)$counts['total'];
            $pending = (int)$counts['pending'];
            $approved = (int)$counts['approved'];
            $rejected = (int)$counts['rejected'];
        }
    } else {
        $errorMessage = 'Could not load appointment statistics.';
    }

    $stmt->close();
} else {
    $errorMessage = 'Could not prepare the appointment statistics query.';
}

/* Load recent appointments. */
$listSql = "SELECT *
            FROM appointments
            WHERE LOWER(TRIM(lecturer)) = LOWER(TRIM(?))
            ORDER BY id DESC
            LIMIT 5";

$listStmt = $conn->prepare($listSql);

if ($listStmt) {
    $listStmt->bind_param("s", $staffName);

    if ($listStmt->execute()) {
        $listResult = $listStmt->get_result();

        while ($row = $listResult->fetch_assoc()) {
            $recentAppointments[] = $row;
        }
    }

    $listStmt->close();
}

/* Detect the student identifier stored in each appointment. */
$appointmentColumns = [];

$columnResult = $conn->query("SHOW COLUMNS FROM appointments");

if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) {
        $appointmentColumns[] = $column['Field'];
    }
}

$studentKey = null;

foreach (['matric', 'matric_number', 'student_matric', 'student_id'] as $candidate) {
    if (in_array($candidate, $appointmentColumns, true)) {
        $studentKey = $candidate;
        break;
    }
}

$studentLookup = null;

if ($studentKey !== null && $studentKey !== 'student_id') {
    $studentLookup = $conn->prepare(
        "SELECT fullname FROM student WHERE matric = ? LIMIT 1"
    );
}

$studentIdLookup = null;

if ($studentKey === 'student_id') {
    $studentIdLookup = $conn->prepare(
        "SELECT fullname FROM student WHERE id = ? LIMIT 1"
    );
}

/* Return the student's real name where a matching record exists. */
function getStudentName($appointment, $studentKey, $studentLookup, $studentIdLookup) {

    if ($studentKey !== null) {
        $studentValue = $appointment[$studentKey] ?? '';

        if ($studentValue !== '' && $studentKey === 'student_id' && $studentIdLookup) {
            $studentIdLookup->bind_param("i", $studentValue);
            $studentIdLookup->execute();

            $result = $studentIdLookup->get_result();

            if ($row = $result->fetch_assoc()) {
                return $row['fullname'];
            }

        } elseif ($studentValue !== '' && $studentLookup) {
            $studentLookup->bind_param("s", $studentValue);
            $studentLookup->execute();

            $result = $studentLookup->get_result();

            if ($row = $result->fetch_assoc()) {
                return $row['fullname'];
            }
        }
    }

    foreach (['student_name', 'fullname', 'name'] as $nameKey) {
        if (!empty($appointment[$nameKey])) {
            return $appointment[$nameKey];
        }
    }

    return 'Student';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Staff Dashboard | NSUK</title>

<style>
:root {
    --green: #14532d;
    --dark-green: #0b3520;
    --light-green: #e8f5ec;
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
    font-family: "Segoe UI", Arial, sans-serif;
    background: var(--background);
    color: var(--text);
}

a {
    text-decoration: none;
}

.topbar {
    background: var(--dark-green);
    color: white;
    padding: 18px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

.brand {
    display: flex;
    align-items: center;
    gap: 13px;
}

.logo {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo img {
    max-width: 50px;
    max-height: 50px;
    object-fit: contain;
}

.brand h2 {
    font-size: 18px;
    margin: 0 0 5px;
}

.brand p {
    color: #c8dfcf;
    font-size: 12px;
    margin: 0;
}

.logout {
    background: white;
    color: var(--dark-green);
    padding: 11px 17px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 14px;
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 32px 24px;
}

.welcome {
    background: linear-gradient(120deg, #14532d, #247447);
    color: white;
    padding: 32px;
    border-radius: 17px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.eyebrow {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #d4ead9;
    font-weight: bold;
}

.welcome h1 {
    font-size: 32px;
    margin: 12px 0;
    overflow-wrap: anywhere;
}

.welcome p {
    color: #e4f2e7;
    line-height: 1.7;
    margin: 0;
}

.welcome-icon {
    font-size: 60px;
}

.section-title {
    font-size: 21px;
    margin: 0 0 17px;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}

.stat-card,
.content-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 23px;
    box-shadow: 0 4px 15px #183c2410;
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.stat-label {
    font-size: 14px;
    color: var(--muted);
}

.stat-icon {
    width: 39px;
    height: 39px;
    border-radius: 11px;
    background: var(--light-green);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
}

.stat-number {
    font-size: 32px;
    font-weight: 750;
    margin-top: 14px;
    color: var(--dark-green);
}

.content-card {
    margin-bottom: 25px;
}

.card-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.card-heading h2 {
    font-size: 19px;
    margin: 0;
}

.card-heading p {
    color: var(--muted);
    font-size: 13px;
    margin: 6px 0 0;
}

.quick-links {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.quick-link {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    color: var(--text);
    display: block;
    transition: .2s;
}

.quick-link:hover {
    border-color: #8bb99a;
    background: #f8fcf9;
    transform: translateY(-2px);
}

.quick-link strong {
    display: block;
    color: var(--green);
    margin-bottom: 8px;
}

.quick-link span {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.5;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 560px;
}

th, td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

th {
    color: var(--muted);
    background: #f8faf8;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}

.status-pending {
    background: #fff4d6;
    color: #8a5b00;
}

.status-approved {
    background: #dcfce7;
    color: #166534;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-other {
    background: #e5e7eb;
    color: #374151;
}

.empty {
    padding: 30px 10px;
    text-align: center;
    color: var(--muted);
    line-height: 1.7;
}

.notice {
    background: #fff4d6;
    color: #795600;
    border-radius: 9px;
    padding: 13px 16px;
    margin-bottom: 20px;
}

footer {
    text-align: center;
    padding: 25px 15px;
    color: var(--muted);
    font-size: 12px;
}

@media (max-width: 850px) {
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .welcome-icon {
        display: none;
    }
}

@media (max-width: 500px) {
    .container {
        padding: 22px 14px;
    }

    .welcome {
        padding: 23px;
    }

    .welcome h1 {
        font-size: 25px;
    }

    .stats {
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-number {
        font-size: 27px;
    }

    .content-card {
        padding: 16px;
    }

    .quick-links {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<header class="topbar">

    <div class="brand">
        <div class="logo">
            <img src="logo-new.png" alt="NSUK school logo">
        </div>

        <div>
            <h2>NSUK Staff Portal</h2>
            <p>Student Appointment Management System</p>
        </div>
    </div>

    <a class="logout" href="staff_logout.php">Logout</a>

</header>

<main class="container">

    <?php if ($errorMessage !== ''): ?>
        <div class="notice"><?= h($errorMessage) ?></div>
    <?php endif; ?>

    <section class="welcome">

        <div>
            <div class="eyebrow">Staff Dashboard</div>

            <h1>Welcome, <?= h($staffName) ?>!</h1>

            <p>
                Monitor student appointment requests assigned to you,
                review booking details, and manage appointments from one place.
            </p>
        </div>

        <div class="welcome-icon" aria-hidden="true">📅</div>

    </section>

    <h2 class="section-title">Appointment Overview</h2>

    <section class="stats">

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Total Requests</span>
                <span class="stat-icon">📋</span>
            </div>
            <div class="stat-number"><?= $total ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Pending</span>
                <span class="stat-icon">⏳</span>
            </div>
            <div class="stat-number"><?= $pending ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Approved</span>
                <span class="stat-icon">✅</span>
            </div>
            <div class="stat-number"><?= $approved ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Rejected</span>
                <span class="stat-icon">❌</span>
            </div>
            <div class="stat-number"><?= $rejected ?></div>
        </div>

    </section>

    <section class="content-card">

        <div class="card-heading">
            <div>
                <h2>Quick Actions</h2>
                <p>Choose what you want to do next.</p>
            </div>
        </div>

        <div class="quick-links">

            <a class="quick-link" href="view_appointment.php">
                <strong>View Appointments →</strong>
                <span>
                    Open appointment records and review student requests.
                </span>
            </a>

            <a class="quick-link" href="manage_appointment.php">
                <strong>Manage Appointments →</strong>
                <span>
                    Open the appointment management page.
                </span>
            </a>

            <a class="quick-link" href="index.html">
                <strong>Portal Home →</strong>
                <span>
                    Return to the NSUK appointment portal home page.
                </span>
            </a>

            <a class="quick-link" href="staff_availability.php">
                <strong>Manage Availability →</strong>
                <span>
                    Set your working days and hours for student appointments.
                </span>
            </a>

        </div>

    </section>

    <section class="content-card">

        <div class="card-heading">
            <div>
                <h2>Recent Appointments</h2>
                <p>Your five most recent assigned appointment requests.</p>
            </div>

            <a href="view_appointment.php"
               style="color:#14532d;font-weight:bold;font-size:13px;">
                View All
            </a>
        </div>

        <?php if (count($recentAppointments) > 0): ?>

            <div class="table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>Appointment ID</th>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($recentAppointments as $appointment): ?>

                        <?php
                        $studentName = getStudentName(
                            $appointment,
                            $studentKey,
                            $studentLookup,
                            $studentIdLookup
                        );

                        $appointmentDate = $appointment['appointment_date']
                            ?? $appointment['date']
                            ?? 'Not specified';

                        $status = strtolower(trim(
                            (string)($appointment['status'] ?? 'pending')
                        ));

                        $statusClass = in_array(
                            $status,
                            ['pending', 'approved', 'rejected'],
                            true
                        ) ? 'status-' . $status : 'status-other';
                        ?>

                        <tr>
                            <td><?= h($appointment['id'] ?? '—') ?></td>
                            <td><?= h($studentName) ?></td>
                            <td><?= h($appointmentDate) ?></td>
                            <td>
                                <span class="status <?= h($statusClass) ?>">
                                    <?= h($status) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php else: ?>

            <div class="empty">
                <div style="font-size:35px;margin-bottom:10px;">📭</div>

                <strong>No appointments to display yet.</strong>

                <p>
                    New requests assigned to your account will appear here.
                </p>
            </div>

        <?php endif; ?>

    </section>

</main>

<footer>
    Nasarawa State University, Keffi · Student Appointment Booking System
</footer>

</body>
</html>
