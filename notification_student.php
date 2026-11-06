
<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['matric'])) {
    header("Location: login.php");
    exit;
}

$matric = $_SESSION['matric'];
$fullname = $_SESSION['fullname'] ?? 'Student';

$appointments = [];
$errorMessage = '';

$pending = 0;
$approved = 0;
$rejected = 0;

try {
    // Get the actual columns in the appointments table.
    $columns = [];
    $columnResult = $conn->query("SHOW COLUMNS FROM appointments");

    while ($column = $columnResult->fetch_assoc()) {
        $columns[] = $column['Field'];
    }

    // Find the column linking appointments to the logged-in student.
    $studentColumn = null;
    foreach (['student_matric', 'matric', 'student_id'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $studentColumn = $candidate;
            break;
        }
    }

    // Find the available appointment detail columns.
    $statusColumn = in_array('status', $columns, true) ? 'status' : null;
    $idColumn = in_array('id', $columns, true) ? 'id' : null;

    $dateColumn = null;
    foreach (['appointment_date', 'date'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $dateColumn = $candidate;
            break;
        }
    }

    $timeColumn = null;
    foreach (['appointment_time', 'time'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $timeColumn = $candidate;
            break;
        }
    }

    $staffColumn = null;
    foreach (['staff', 'staff_name', 'lecturer', 'lecturer_name'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $staffColumn = $candidate;
            break;
        }
    }

    $reasonColumn = null;
    foreach (['reason', 'purpose', 'description'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $reasonColumn = $candidate;
            break;
        }
    }

    if (!$studentColumn || !$statusColumn) {
        $errorMessage = 'Your appointments table is missing a student reference or status column. Please check the table structure in phpMyAdmin.';
    } else {
        // Use safe column names discovered from the database.
        $selectParts = [
            $idColumn ? "`$idColumn` AS appointment_id" : "0 AS appointment_id",
            "`$statusColumn` AS appointment_status",
            $dateColumn ? "`$dateColumn` AS appointment_date" : "NULL AS appointment_date",
            $timeColumn ? "`$timeColumn` AS appointment_time" : "NULL AS appointment_time",
            $staffColumn ? "`$staffColumn` AS staff_name" : "NULL AS staff_name",
            $reasonColumn ? "`$reasonColumn` AS appointment_reason" : "NULL AS appointment_reason"
        ];

      
$sql = "SELECT " . implode(', ', $selectParts) .
       " FROM appointments WHERE `$studentColumn` = ?";

if ($idColumn) {
    $sql .= " ORDER BY `$idColumn` DESC";
} elseif ($dateColumn) {
    $sql .= " ORDER BY `$dateColumn` DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $matric);
$stmt->execute();


        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;

            $status = strtolower(trim($row['appointment_status'] ?? ''));

            if ($status === 'pending') {
                $pending++;
            } elseif ($status === 'approved') {
                $approved++;
            } elseif ($status === 'rejected') {
                $rejected++;
            }
        }

        $stmt->close();
    }
} catch (Throwable $e) {
    $errorMessage = 'Notifications could not be loaded. Please check your appointments table and database connection.';
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function statusClass($status) {
    $status = strtolower(trim((string)$status));

    if ($status === 'approved') {
        return 'approved';
    }

    if ($status === 'rejected') {
        return 'rejected';
    }

    return 'pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications | NSUK Student Portal</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f6f4;
            color: #24352b;
            line-height: 1.6;
        }

        .topbar {
            background: #064e3b;
            color: white;
            padding: 20px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .brand{
            display: flex;
            align-items: center;
            gap:10px;
        }
        .brand h1 {
            font-size: 23px;
            letter-spacing: 1px;
        }

        .brand p {
            font-size: 13px;
            color: #d1fae5;
        }

        .topbar a {
            color: white;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.6);
            padding: 9px 14px;
            border-radius: 6px;
        }

        .topbar a:hover {
            background: white;
            color: #064e3b;
        }

        .container {
            width: 90%;
            max-width: 1050px;
            margin: 30px auto;
        }

        .welcome {
            margin-bottom: 22px;
        }

        .welcome h2 {
            color: #064e3b;
            font-size: 27px;
            margin-bottom: 5px;
        }

        .welcome p {
            color: #65736a;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
            border-top: 4px solid #064e3b;
        }

        .stat-card span {
            display: block;
            color: #66756b;
            font-size: 14px;
        }

        .stat-card strong {
            display: block;
            font-size: 30px;
            margin-top: 5px;
            color: #064e3b;
        }

        .stat-card.approved-card {
            border-top-color: #15803d;
        }

        .stat-card.rejected-card {
            border-top-color: #dc2626;
        }

        .section-title {
            color: #064e3b;
            font-size: 21px;
            margin-bottom: 14px;
        }

        .notice {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            overflow-wrap: anywhere;
        }

        .notice.success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .notification {
            background: white;
            padding: 20px;
            margin-bottom: 14px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,.04);
            border-left: 5px solid #d97706;
        }

        .notification.approved {
            border-left-color: #15803d;
        }

        .notification.rejected {
            border-left-color: #dc2626;
        }

        .notification.pending {
            border-left-color: #d97706;
        }

        .notification-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .notification h3 {
            color: #064e3b;
            font-size: 17px;
        }

        .badge {
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .badge.approved {
            background: #dcfce7;
            color: #166534;
        }

        .badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .details {
            margin-top: 10px;
            color: #536158;
            font-size: 14px;
        }

        .details p {
            margin-top: 4px;
            overflow-wrap: anywhere;
        }

        .empty {
            text-align: center;
            background: white;
            border-radius: 10px;
            padding: 35px 20px;
            color: #66756b;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn {
            display: inline-block;
            background: #064e3b;
            color: white;
            text-decoration: none;
            padding: 11px 17px;
            border-radius: 7px;
            font-weight: bold;
        }

        .btn:hover {
            background: #047857;
        }

        .btn.secondary {
            background: white;
            color: #064e3b;
            border: 1px solid #064e3b;
        }

        footer {
            text-align: center;
            color: #718078;
            padding: 25px 10px;
            font-size: 13px;
        }

        @media (max-width: 650px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .container {
                width: 92%;
                margin-top: 22px;
            }

            .welcome h2 {
                font-size: 23px;
            }

            .topbar {
                padding: 17px 5%;
            }
        }
        
.status-sign {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 18px;
    margin: -20px -20px 18px -20px;
    border-radius: 10px 10px 0 0;
    font-size: 16px;
    font-weight: 700;
}

.status-sign .status-icon {
    width: 38px;
    height: 38px;
    min-width: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 23px;
    background: rgba(255,255,255,.75);
}

.status-sign.approved {
    background: #dcfce7;
    color: #166534;
}

.status-sign.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-sign.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-sign small {
    display: block;
    font-size: 12px;
    font-weight: 400;
    margin-top: 2px;
}

.notification {
    overflow: hidden;
}

@media (max-width: 480px) {
    .status-sign {
        font-size: 14px;
        padding: 13px;
    }
}

        
    </style>
</head>

<body>
    <header class="topbar">
        <div class="brand">
            <div class="logo"><img src="logo-new.png" width= "50px" alt="school logo"></div>
            <div>
                <h2>NSUK Portal</h2>
                <p>Student Appointment System</p>
            </div>
        </div>
        

        <a href="student_dashboard.php">Back to Dashboard</a>
    </header>

    <main class="container">
        <section class="welcome">
            <h2>My Notifications</h2>
            <p>Hello, <?= h($fullname) ?>. Here are the latest statuses of your appointments.</p>
        </section>

        <?php if ($errorMessage !== ''): ?>
            <div class="notice">
                <?= h($errorMessage) ?>
            </div>
        <?php endif; ?>

        <section class="stats">
            <div class="stat-card">
                <span>Pending</span>
                <strong><?= $pending ?></strong>
            </div>

            <div class="stat-card approved-card">
                <span>Approved</span>
                <strong><?= $approved ?></strong>
            </div>

            <div class="stat-card rejected-card">
                <span>Rejected</span>
                <strong><?= $rejected ?></strong>
            </div>
        </section>

        <h2 class="section-title">Appointment Updates</h2>

        <?php if ($errorMessage === ''): ?>
            <?php if (count($appointments) > 0): ?>

                <?php foreach ($appointments as $appointment): ?>
                    <?php
                        $status = strtolower(trim(
                            (string)($appointment['appointment_status'] ?? 'pending')
                        ));

                        $class = statusClass($status);

                        if ($status === 'approved') {
                            $heading = 'Your appointment was approved';
                            $message = 'Your appointment request has been approved.';
                        } elseif ($status === 'rejected') {
                            $heading = 'Your appointment was rejected';
                            $message = 'Your appointment request was rejected. Please contact the staff member if you need clarification.';
                        } else {
                            $heading = 'Your appointment is pending';
                            $message = 'Your appointment request is awaiting a decision from staff.';
                        }
                    ?>

                     <div class="status-sign <?= h($class) ?>">
    <div class="status-icon">
        <?php if ($status === 'approved'): ?>
            &#10003;
        <?php elseif ($status === 'rejected'): ?>
            &#10007;
        <?php else: ?>
            &#9203;
        <?php endif; ?>
    </div>

    <div>
        <?php if ($status === 'approved'): ?>
            APPOINTMENT APPROVED
            <small>Staff has approved your appointment.</small>
        <?php elseif ($status === 'rejected'): ?>
            APPOINTMENT REJECTED
            <small>Staff has rejected your appointment.</small>
        <?php else: ?>
            AWAITING STAFF APPROVAL
            <small>Your appointment is still pending.</small>
        <?php endif; ?>
    </div>
</div>

<div class="notification-head">
    <h3><?= h($heading) ?></h3>
    <span class="badge <?= h($class) ?>">
        <?= h(ucfirst($status ?: 'pending')) ?>
    </span>
</div>

                        <p><?= h($message) ?></p>

                        <div class="details">
                            <?php if (!empty($appointment['staff_name'])): ?>
                                <p><strong>Staff:</strong> <?= h($appointment['staff_name']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($appointment['appointment_date'])): ?>
                                <p><strong>Date:</strong> <?= h($appointment['appointment_date']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($appointment['appointment_time'])): ?>
                                <p><strong>Time:</strong> <?= h($appointment['appointment_time']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($appointment['appointment_reason'])): ?>
                                <p><strong>Reason:</strong> <?= h($appointment['appointment_reason']) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>

                <?php endforeach; ?>

            <?php else: ?>
                <div class="empty">
                    <h3>No appointment notifications yet</h3>
                    <p>Your appointment status updates will appear here after you submit a booking.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="actions">
            <a class="btn" href="book_appointment.php">Book Appointment</a>
            <a class="btn secondary" href="student_dashboard.php">Back to Dashboard</a>
        </div>
    </main>

    <footer>
        &copy; <?= date('Y') ?> NSUK Student Appointment Booking System
    </footer>
</body>
</html>
