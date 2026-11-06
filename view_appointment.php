<?php
session_start();
require_once __DIR__ . '/db.php';

// Allow only logged-in staff.
if (!isset($_SESSION['lecturer_id'])) {
    header("Location: staff_login.php");
    exit;
}

$staffName = trim($_SESSION['lecturer_name'] ?? 'NSUK Staff');
$message = '';

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Approve or reject only appointments assigned to this staff member.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';

    if (!$id || !in_array($status, ['Approved', 'Rejected'], true)) {
        $message = 'Invalid appointment request.';
    } else {
        $update = $conn->prepare(
            "UPDATE appointments
             SET status = ?
             WHERE id = ?
             AND LOWER(TRIM(lecturer)) = LOWER(?) 
             AND LOWER(TRIM(status)) = 'pending'"
        );

        if ($update) {
            $update->bind_param('sis', $status, $id, $staffName);

            if ($update->execute() && $update->affected_rows > 0) {
                $message = 'Appointment status updated successfully.';

                // --- NEW ADDITION: Insert a notification for the student ---
                $notifStmt = $conn->prepare("SELECT student_matric, appointment_date FROM appointments WHERE id = ?");
                if ($notifStmt) {
                    $notifStmt->bind_param('i', $id);
                    $notifStmt->execute();
                    $notifResult = $notifStmt->get_result();
                    if ($row = $notifResult->fetch_assoc()) {
                        $studentMatric = $row['student_matric'];
                        $apptDate = $row['appointment_date'];
                        
                        $notifMessage = "Your appointment for " . $apptDate . " has been " . strtolower($status) . ".";
                        
                        $insertNotif = $conn->prepare(
                            "INSERT INTO notifications (student_matric, message, is_read, created_at) VALUES (?, ?, 0, NOW())"
                        );
                        if ($insertNotif) {
                            $insertNotif->bind_param('ss', $studentMatric, $notifMessage);
                            $insertNotif->execute();
                            $insertNotif->close();
                        }
                    }
                    $notifStmt->close();
                }
                // -----------------------------------------------------------

            } else {
                $message = 'No appointment was changed. It may already have a status or may not be assigned to you.';
            }

            $update->close();
        } else {
            $message = 'Unable to update the appointment.';
        }
    }
}

// Fetch only appointments assigned to the logged-in staff member.
$appointments = [];

$stmt = $conn->prepare(
    "SELECT id, student_matric, lecturer, appointment_date,
            appointment_time, reason, status
     FROM appointments
     WHERE LOWER(TRIM(lecturer)) = LOWER(?)
     ORDER BY appointment_date DESC, appointment_time DESC"
);

$loadError = false;

if ($stmt) {
    $stmt->bind_param('s', $staffName);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
    } else {
        $loadError = true;
    }

    $stmt->close();
} else {
    $loadError = true;
}

// Calculate summary counts.
$total = count($appointments);
$pending = 0;
$approved = 0;
$rejected = 0;

foreach ($appointments as $appointment) {
    $status = strtolower(trim($appointment['status'] ?? ''));

    if ($status === 'pending') {
        $pending++;
    } elseif ($status === 'approved') {
        $approved++;
    } elseif ($status === 'rejected') {
        $rejected++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointments | NSUK</title>

    <style>
        :root {
            --green: #075b36;
            --dark-green: #043d25;
            --light-green: #eaf5ee;
            --gold: #d6ad45;
            --text: #23352b;
            --muted: #718078;
            --border: #e3eae5;
            --background: #f4f7f5;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        .topbar {
            background: var(--dark-green);
            color: white;
            border-bottom: 4px solid var(--gold);
            padding: 20px 5%;
        }

        .topbar-inner {
            max-width: 1450px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: var(--gold);
            color: var(--dark-green);
            font-size: 23px;
            font-weight: bold;
        }

        .brand h1 {
            font-size: 19px;
            margin: 0 0 5px;
        }

        .brand p {
            color: #d5e6da;
            font-size: 12px;
            margin: 0;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            border: 1px solid #628b74;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: bold;
        }

        .nav-links a:hover {
            background: #ffffff18;
        }

        .page {
            max-width: 1450px;
            margin: 30px auto;
            padding: 0 5%;
        }

        .welcome {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .breadcrumb {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 10px;
        }

        .breadcrumb span {
            color: var(--green);
            font-weight: bold;
        }

        .welcome h2 {
            color: var(--dark-green);
            font-size: clamp(24px, 3vw, 30px);
            margin: 0 0 9px;
        }

        .welcome p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .staff-label {
            background: white;
            border: 1px solid var(--border);
            padding: 13px 17px;
            border-radius: 12px;
            font-size: 13px;
            box-shadow: 0 3px 12px #163e2410;
        }

        .staff-label strong {
            color: var(--green);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 27px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            background: white;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 4px 15px #173b2410;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--green);
        }

        .pending-card::before {
            background: #d49a20;
        }

        .approved-card::before {
            background: #18834b;
        }

        .rejected-card::before {
            background: #c74747;
        }

        .stat-heading {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 13px;
        }

        .stat-number {
            color: var(--dark-green);
            font-size: 32px;
            font-weight: bold;
        }

        .stat-note {
            color: var(--muted);
            font-size: 12px;
            margin-top: 8px;
        }

        .panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: 0 4px 18px #173b240b;
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            padding: 23px 24px;
            border-bottom: 1px solid var(--border);
        }

        .panel-header h3 {
            color: var(--dark-green);
            font-size: 19px;
            margin: 0 0 7px;
        }

        .panel-header p {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .record-count {
            background: var(--light-green);
            color: var(--green);
            border-radius: 20px;
            padding: 9px 13px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .search-box {
            flex: 1;
            min-width: 220px;
        }

        .search-box input,
        .filters select {
            width: 100%;
            min-height: 44px;
            border: 1px solid #d9e3dc;
            border-radius: 9px;
            padding: 11px 13px;
            background: white;
            color: var(--text);
            font-size: 13px;
            outline: none;
        }

        .search-box input:focus,
        .filters select:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px #075b3614;
        }

        .filters select {
            width: 180px;
        }

        .table-wrap {
            overflow-x: auto;
            width: 100%;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 1050px;
        }

        thead {
            background: #f0f6f2;
        }

        th {
            color: #496254;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            text-align: left;
            padding: 16px 15px;
            white-space: nowrap;
        }

        td {
            padding: 17px 15px;
            border-top: 1px solid #edf1ee;
            font-size: 13px;
            line-height: 1.6;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8fbf9;
        }

        .id-cell {
            color: var(--muted);
            font-weight: bold;
        }

        .matric {
            color: var(--green);
            font-weight: bold;
        }

        .reason {
            min-width: 180px;
            max-width: 280px;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            border-radius: 20px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status.pending {
            background: #fff4d7;
            color: #936400;
        }

        .status.approved {
            background: #e2f6e9;
            color: #166534;
        }

        .status.rejected {
            background: #fee8e8;
            color: #b42323;
        }

        .status.other {
            background: #edf0ee;
            color: #56635b;
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
            min-width: 160px;
        }

        .actions form {
            margin: 0;
        }

        .action-btn {
            border: 0;
            border-radius: 7px;
            padding: 9px 11px;
            color: white;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            transition: .2s;
        }

        .approve {
            background: #167344;
        }

        .approve:hover {
            background: #0d5631;
        }

        .reject {
            background: #c13d3d;
        }

        .reject:hover {
            background: #9f2525;
        }

        .no-action {
            color: var(--muted);
            font-size: 12px;
        }

        .message {
            margin: 20px 24px 0;
            padding: 14px 16px;
            border-radius: 9px;
            background: #e6f7eb;
            border: 1px solid #c6e9d0;
            color: #166534;
            font-size: 13px;
            line-height: 1.5;
        }

        .empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--muted);
        }

        .empty strong {
            display: block;
            color: var(--dark-green);
            font-size: 16px;
            margin-bottom: 8px;
        }

        .panel-footer {
            border-top: 1px solid var(--border);
            padding: 16px 24px;
            color: var(--muted);
            font-size: 12px;
        }

        footer {
            text-align: center;
            padding: 28px 10px;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 900px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .topbar {
                padding: 18px 5%;
            }

            .brand h1 {
                font-size: 16px;
            }

            .page {
                margin-top: 22px;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-number {
                font-size: 27px;
            }

            .filters {
                padding: 15px;
            }

            .filters select {
                width: 100%;
            }

            .panel-header {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <div class="logo"><img src="logo-new.png" width= "50px" alt="school logo"></div>
            <div>
                <h1>NSUK Staff Portal</h1>
                <p>Student Appointment management System</p>
            </div>
        </div>

        <nav class="nav-links">
            <a href="staff_dashboard.php">Dashboard</a>
            <a href="manage_appointment.php">Manage Appointments</a>
            <a href="index.html">Home Page</a>
        </nav>
    </div>
</header>

<main class="page">

    <section class="welcome">
        <div>
            <div class="breadcrumb">
                Dashboard / <span>View Appointments</span>
            </div>

            <h2>My Appointment Requests</h2>

            <p>
                Welcome, <?= h($staffName) ?>!
                View and manage appointment requests assigned to you.
            </p>
        </div>

        <div class="staff-label">
            Signed in as <strong><?= h($staffName) ?></strong>
        </div>
    </section>

    <section class="stats">
        <div class="stat-card">
            <div class="stat-heading">Total Appointments</div>
            <div class="stat-number"><?= $total ?></div>
            <div class="stat-note">Requests assigned to you</div>
        </div>

        <div class="stat-card pending-card">
            <div class="stat-heading">Pending Requests</div>
            <div class="stat-number"><?= $pending ?></div>
            <div class="stat-note">Awaiting your decision</div>
        </div>

        <div class="stat-card approved-card">
            <div class="stat-heading">Approved</div>
            <div class="stat-number"><?= $approved ?></div>
            <div class="stat-note">Accepted appointments</div>
        </div>

        <div class="stat-card rejected-card">
            <div class="stat-heading">Rejected</div>
            <div class="stat-number"><?= $rejected ?></div>
            <div class="stat-note">Declined appointments</div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Appointment Records</h3>
                <p>Search records, filter by status, and review student requests.</p>
            </div>

            <div class="record-count">
                <?= $total ?> record<?= $total === 1 ? '' : 's' ?>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message"><?= h($message) ?></div>
        <?php endif; ?>

        <div class="filters">
            <div class="search-box">
                <input
                    type="search"
                    id="appointmentSearch"
                    placeholder="Search matric number, reason or date..."
                    aria-label="Search appointment records"
                >
            </div>

            <select id="statusFilter" aria-label="Filter appointment status">
                <option value="all">All statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <?php if ($loadError): ?>

            <div class="empty">
                <strong>Unable to load appointment records</strong>
                Please check your database connection and try again.
            </div>

        <?php elseif ($total === 0): ?>

            <div class="empty">
                <strong>No appointment requests found</strong>
                Appointment requests assigned to your account will appear here.
            </div>

        <?php else: ?>

            <div class="table-wrap">
                <table id="appointmentsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student Matric Number</th>
                            <th>Staff</th>
                            <th>Appointment Date</th>
                            <th>Appointment Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                            $currentStatus = trim(
                                $appointment['status'] ?? 'Pending'
                            );

                            $normalizedStatus = strtolower($currentStatus);

                            $statusClass = in_array(
                                $normalizedStatus,
                                ['pending', 'approved', 'rejected'],
                                true
                            ) ? $normalizedStatus : 'other';
                        ?>

                        <tr data-status="<?= h($normalizedStatus) ?>">
                            <td class="id-cell">
                                #<?= (int)$appointment['id'] ?>
                            </td>

                            <td class="matric">
                                <?= h($appointment['student_matric']) ?>
                            </td>

                            <td><?= h($appointment['lecturer']) ?></td>

                            <td><?= h($appointment['appointment_date']) ?></td>

                            <td><?= h($appointment['appointment_time']) ?></td>

                            <td class="reason">
                                <?= h($appointment['reason']) ?>
                            </td>

                            <td>
                                <span class="status <?= h($statusClass) ?>">
                                    <?= h($currentStatus) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($normalizedStatus === 'pending'): ?>
                                    <div class="actions">
                                        <form method="post" action="view_appointment.php">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$appointment['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-btn approve"
                                                name="status"
                                                value="Approved"
                                                onclick="return confirm('Approve this appointment?');"
                                            >Approve</button>
                                        </form>

                                        <form method="post" action="view_appointment.php">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$appointment['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-btn reject"
                                                name="status"
                                                value="Rejected"
                                                onclick="return confirm('Reject this appointment?');"
                                            >Reject</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="no-action">No action needed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                        <tr id="noSearchResults" style="display:none">
                            <td colspan="8">
                                <div class="empty">
                                    <strong>No matching appointments</strong>
                                    Try a different search term or status.
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

        <div class="panel-footer">
            Only appointment requests assigned to your staff account are shown.
        </div>
    </section>

    <footer>
        NSUK Student Appointment Booking System &copy; <?= date('Y') ?>
    </footer>

</main>

<script>
    const searchInput = document.getElementById('appointmentSearch');
    const statusFilter = document.getElementById('statusFilter');
    const table = document.getElementById('appointmentsTable');
    const noResults = document.getElementById('noSearchResults');

    function filterAppointments() {
        if (!table) return;

        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedStatus = statusFilter.value;
        const rows = table.querySelectorAll('tbody tr[data-status]');

        let visibleCount = 0;

        rows.forEach(function(row) {
            const rowText = row.textContent.toLowerCase();
            const rowStatus = row.dataset.status;

            const matchesSearch = rowText.includes(searchTerm);
            const matchesStatus =
                selectedStatus === 'all' || rowStatus === selectedStatus;

            const showRow = matchesSearch && matchesStatus;

            row.style.display = showRow ? '' : 'none';

            if (showRow) {
                visibleCount++;
            }
        });

        if (noResults) {
            noResults.style.display = visibleCount === 0 ? '' : 'none';
        }
    }

    if (searchInput && statusFilter) {
        searchInput.addEventListener('input', filterAppointments);
        statusFilter.addEventListener('change', filterAppointments);
    }
</script>

</body>
</html>

<?php
$conn->close();
?>