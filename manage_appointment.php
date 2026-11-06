
<?php
session_start();
require_once __DIR__ . '/db.php';

/* Protect the staff management page. */
if (!isset($_SESSION['lecturer_id'])) {
    header("Location: staff_login.php");
    exit;
}

$staffName = trim($_SESSION['lecturer_name'] ?? 'NSUK Staff');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Create a CSRF token for secure form submissions. */
if (empty($_SESSION['manage_appointment_token'])) {
    $_SESSION['manage_appointment_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['manage_appointment_token'];
$message = '';
$messageType = 'success';

/* Process approval or rejection requests. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $newStatus = $_POST['new_status'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        $message = 'Your session token is invalid. Refresh the page and try again.';
        $messageType = 'error';
    } elseif (!$appointmentId || $appointmentId < 1) {
        $message = 'Invalid appointment record.';
        $messageType = 'error';
    } elseif (!in_array($newStatus, ['Approved', 'Rejected'], true)) {
        $message = 'Please select a valid appointment action.';
        $messageType = 'error';
    } else {
        /*
         * Update only a pending appointment assigned to this staff member.
         * The staff name is checked against the logged-in account.
         */
        $updateSql = "UPDATE appointments
                      SET status = ?
                      WHERE id = ?
                        AND LOWER(TRIM(lecturer)) = LOWER(TRIM(?))
                        AND LOWER(TRIM(status)) = 'pending'";

        $updateStmt = $conn->prepare($updateSql);

        if (!$updateStmt) {
            $message = 'Unable to prepare the appointment update.';
            $messageType = 'error';
        } else {
            $updateStmt->bind_param(
                'sis',
                $newStatus,
                $appointmentId,
                $staffName
            );

            if ($updateStmt->execute()) {
                if ($updateStmt->affected_rows > 0) {
                    $message = 'Appointment ' . strtolower($newStatus) . ' successfully.';
                } else {
                    $message = 'This appointment was not found under your account, or it has already been processed.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Could not update the appointment. Please try again.';
                $messageType = 'error';
            }

            $updateStmt->close();
        }
    }
}

/* Choose which appointments to display. */
$filter = strtolower(trim($_GET['status'] ?? 'all'));

$allowedFilters = ['all', 'pending', 'approved', 'rejected'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$appointments = [];
$pageError = '';

/* Load appointments assigned to the logged-in staff member only. */
$sql = "SELECT
            a.id,
            a.student_matric,
            a.lecturer,
            a.appointment_date,
            a.appointment_time,
            a.reason,
            a.status,
            s.fullname AS student_name,
            s.department AS student_department
        FROM appointments a
        LEFT JOIN student s
            ON s.matric = a.student_matric
        WHERE LOWER(TRIM(a.lecturer)) = LOWER(TRIM(?))";

if ($filter !== 'all') {
    $sql .= " AND LOWER(TRIM(a.status)) = ?";
}

$sql .= " ORDER BY
            CASE WHEN LOWER(TRIM(a.status)) = 'pending'
                 THEN 0 ELSE 1 END,
            a.appointment_date DESC,
            a.appointment_time DESC,
            a.id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $pageError = 'Unable to load appointments. Please check the database configuration.';
} else {
    if ($filter === 'all') {
        $stmt->bind_param('s', $staffName);
    } else {
        $stmt->bind_param('ss', $staffName, $filter);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
    } else {
        $pageError = 'An error occurred while retrieving appointment records.';
    }

    $stmt->close();
}

/* Calculate totals for the logged-in staff member. */
$counts = [
    'all' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

$countSql = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(
                    CASE WHEN LOWER(TRIM(status)) = 'pending'
                    THEN 1 ELSE 0 END
                ), 0) AS pending,
                COALESCE(SUM(
                    CASE WHEN LOWER(TRIM(status)) = 'approved'
                    THEN 1 ELSE 0 END
                ), 0) AS approved,
                COALESCE(SUM(
                    CASE WHEN LOWER(TRIM(status)) = 'rejected'
                    THEN 1 ELSE 0 END
                ), 0) AS rejected
             FROM appointments
             WHERE LOWER(TRIM(lecturer)) = LOWER(TRIM(?))";

$countStmt = $conn->prepare($countSql);

if ($countStmt) {
    $countStmt->bind_param('s', $staffName);

    if ($countStmt->execute()) {
        $countResult = $countStmt->get_result();
        $countRow = $countResult->fetch_assoc();

        if ($countRow) {
            $counts['all'] = (int)$countRow['total'];
            $counts['pending'] = (int)$countRow['pending'];
            $counts['approved'] = (int)$countRow['approved'];
            $counts['rejected'] = (int)$countRow['rejected'];
        }
    }

    $countStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Appointments | NSUK Staff Portal</title>

<style>
:root {
    --green: #14532d;
    --dark-green: #0b3520;
    --light-green: #e8f5ec;
    --background: #f4f7f5;
    --text: #24352b;
    --muted: #718078;
    --border: #e1e9e3;
    --white: #ffffff;
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
    padding: 17px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
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
    object-fit: contain;
    border-radius: 9px;
}

.brand h1 {
    font-size: 18px;
    margin: 0 0 5px;
}

.brand p {
    margin: 0;
    font-size: 12px;
    color: #c8dfcf;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.header-link {
    padding: 10px 14px;
    border-radius: 7px;
    color: white;
    border: 1px solid #ffffff55;
    font-size: 13px;
    font-weight: 600;
}

.header-link:hover {
    background: #ffffff15;
}

.container {
    max-width: 1250px;
    margin: 0 auto;
    padding: 32px 24px 45px;
}

.breadcrumb {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 15px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 26px;
    flex-wrap: wrap;
}

.page-heading h2 {
    color: var(--dark-green);
    font-size: 29px;
    margin: 0 0 9px;
}

.page-heading p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.7;
    margin: 0;
}

.staff-label {
    display: inline-block;
    background: var(--light-green);
    color: var(--green);
    border-radius: 8px;
    padding: 11px 15px;
    font-size: 13px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.notice {
    padding: 14px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.5;
}

.notice.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.notice.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
}

.stat-label {
    color: var(--muted);
    font-size: 13px;
}

.stat-number {
    color: var(--dark-green);
    font-size: 30px;
    font-weight: 750;
    margin-top: 10px;
}

.panel {
    background: white;
    border: 1px solid var(--border);
    border-radius: 13px;
    overflow: hidden;
    box-shadow: 0 4px 15px #183c2408;
}

.panel-heading {
    padding: 22px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.panel-heading h3 {
    color: var(--dark-green);
    font-size: 18px;
    margin: 0 0 6px;
}

.panel-heading p {
    color: var(--muted);
    font-size: 13px;
    margin: 0;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
}

.filter-link {
    color: #526258;
    border: 1px solid var(--border);
    padding: 9px 13px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
}

.filter-link.active,
.filter-link:hover {
    background: var(--green);
    color: white;
    border-color: var(--green);
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th, td {
    padding: 16px 14px;
    text-align: left;
    vertical-align: top;
    border-bottom: 1px solid #edf1ee;
    font-size: 13px;
}

th {
    background: #f8faf8;
    color: #64746a;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
}

td {
    line-height: 1.6;
}

.student-name {
    font-weight: 700;
    color: var(--dark-green);
}

.secondary {
    color: var(--muted);
    font-size: 12px;
    overflow-wrap: anywhere;
}

.reason {
    max-width: 250px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    border-radius: 20px;
    padding: 6px 10px;
    font-size: 11px;
    font-weight: 800;
    text-transform: capitalize;
    white-space: nowrap;
}

.status-pending {
    color: #8a5b00;
    background: #fff4d6;
}

.status-approved {
    color: #166534;
    background: #dcfce7;
}

.status-rejected {
    color: #991b1b;
    background: #fee2e2;
}

.status-other {
    color: #374151;
    background: #e5e7eb;
}

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    min-width: 145px;
}

.action-form {
    margin: 0;
}

.action-btn {
    border: none;
    border-radius: 6px;
    padding: 9px 11px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.approve-btn {
    background: #dcfce7;
    color: #166534;
}

.approve-btn:hover {
    background: #bbf7d0;
}

.reject-btn {
    background: #fee2e2;
    color: #991b1b;
}

.reject-btn:hover {
    background: #fecaca;
}

.processed {
    color: var(--muted);
    font-size: 12px;
}

.empty {
    padding: 55px 20px;
    text-align: center;
    color: var(--muted);
    line-height: 1.8;
}

.empty-icon {
    font-size: 38px;
    margin-bottom: 12px;
}

.bottom-link {
    display: inline-block;
    margin-top: 22px;
    color: var(--green);
    font-size: 14px;
    font-weight: 700;
}

footer {
    text-align: center;
    padding: 24px 15px;
    color: var(--muted);
    background: white;
    border-top: 1px solid var(--border);
    font-size: 12px;
}

@media (max-width: 850px) {
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .container {
        padding: 25px 15px 35px;
    }
}

@media (max-width: 480px) {
    .stats {
        gap: 9px;
    }

    .stat-card {
        padding: 14px;
    }

    .stat-number {
        font-size: 25px;
    }

    .page-heading h2 {
        font-size: 24px;
    }

    .panel-heading {
        padding: 18px;
    }

    .filters {
        padding: 15px;
    }
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand">
        <img class="logo" src="logo-new.png" alt="NSUK logo">
        <div>
            <h1>NSUK Staff Portal</h1>
            <p>Student Appointment Management System</p>
        </div>
    </div>

    <div class="header-actions">
        <a class="header-link" href="staff_dashboard.php">Dashboard</a>
        <a class="header-link" href="staff_logout.php">Logout</a>
    </div>
</header>

<main class="container">

    <div class="breadcrumb">Staff Portal / Appointments / Manage Appointments</div>

    <section class="page-heading">
        <div>
            <h2>Manage Appointments</h2>
            <p>Review student requests and approve or reject pending appointments assigned to you.</p>
        </div>

        <div class="staff-label">
            Signed in as: <?= h($staffName) ?>
        </div>
    </section>

    <?php if ($message !== ''): ?>
        <div class="notice <?= h($messageType) ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($pageError !== ''): ?>
        <div class="notice error"><?= h($pageError) ?></div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat-card">
            <div class="stat-label">Total Appointments</div>
            <div class="stat-number"><?= $counts['all'] ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-number"><?= $counts['pending'] ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Approved</div>
            <div class="stat-number"><?= $counts['approved'] ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Rejected</div>
            <div class="stat-number"><?= $counts['rejected'] ?></div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h3>Appointment Requests</h3>
                <p>Only requests matching your staff account are displayed.</p>
            </div>
        </div>

        <nav class="filters">
            <a class="filter-link <?= $filter === 'all' ? 'active' : '' ?>"
               href="manage_appointment.php?status=all">
                All (<?= $counts['all'] ?>)
            </a>

            <a class="filter-link <?= $filter === 'pending' ? 'active' : '' ?>"
               href="manage_appointment.php?status=pending">
                Pending (<?= $counts['pending'] ?>)
            </a>

            <a class="filter-link <?= $filter === 'approved' ? 'active' : '' ?>"
               href="manage_appointment.php?status=approved">
                Approved (<?= $counts['approved'] ?>)
            </a>

            <a class="filter-link <?= $filter === 'rejected' ? 'active' : '' ?>"
               href="manage_appointment.php?status=rejected">
                Rejected (<?= $counts['rejected'] ?>)
            </a>
        </nav>

        <?php if (count($appointments) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Department</th>
                            <th>Appointment Date</th>
                            <th>Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                        $status = strtolower(trim((string)$appointment['status']));

                        $statusClass = in_array(
                            $status,
                            ['pending', 'approved', 'rejected'],
                            true
                        ) ? 'status-' . $status : 'status-other';

                        $studentName = $appointment['student_name']
                            ?: 'Student record unavailable';

                        $department = $appointment['student_department']
                            ?: 'Not provided';
                        ?>

                        <tr>
                            <td><?= h($appointment['id']) ?></td>

                            <td>
                                <div class="student-name"><?= h($studentName) ?></div>
                                <div class="secondary">
                                    Matric: <?= h($appointment['student_matric']) ?>
                                </div>
                            </td>

                            <td><?= h($department) ?></td>

                            <td><?= h($appointment['appointment_date']) ?></td>

                            <td>
                                <?= h(date(
                                    'g:i A',
                                    strtotime($appointment['appointment_time'])
                                )) ?>
                            </td>

                            <td>
                                <div class="reason"><?= h($appointment['reason']) ?></div>
                            </td>

                            <td>
                                <span class="status <?= h($statusClass) ?>">
                                    <?= h($status) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($status === 'pending'): ?>
                                    <div class="actions">
                                        <form class="action-form" method="POST"
                                              onsubmit="return confirm('Approve this appointment?');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="appointment_id"
                                                   value="<?= (int)$appointment['id'] ?>">
                                            <input type="hidden" name="new_status"
                                                   value="Approved">
                                            <button class="action-btn approve-btn" type="submit">
                                                Approve
                                            </button>
                                        </form>

                                        <form class="action-form" method="POST"
                                              onsubmit="return confirm('Reject this appointment?');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="appointment_id"
                                                   value="<?= (int)$appointment['id'] ?>">
                                            <input type="hidden" name="new_status"
                                                   value="Rejected">
                                            <button class="action-btn reject-btn" type="submit">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="processed">Already processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($pageError === ''): ?>
            <div class="empty">
                <div class="empty-icon">📭</div>
                <strong>No appointments found.</strong>
                <div>
                    Appointments assigned to your account will appear here.
                    Try selecting another status filter if necessary.
                </div>
            </div>
        <?php endif; ?>
    </section>

    <a class="bottom-link" href="staff_dashboard.php">
        ← Back to Staff Dashboard
    </a>

</main>

<footer>
    Nasarawa State University, Keffi · Student Appointment Management System
</footer>

</body>
</html>
