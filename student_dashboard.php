<?php
session_start();
require_once __DIR__ . '/db.php';

// Protect the dashboard.
if (!isset($_SESSION['matric'])) {
    header('Location: login.php');
    exit;
}

$matric = $_SESSION['matric'];
$fullname = $_SESSION['fullname'] ?? 'Student';
$department = $_SESSION['department'] ?? 'Computer Science';

$totalAppointments = 0;
$pendingAppointments = 0;
$approvedAppointments = 0;
$rejectedAppointments = 0;
$databaseError = false;

// Count appointment requests belonging to this student.
try {
    $stmt = $conn->prepare(
        "SELECT status, COUNT(*) AS total
         FROM appointments
         WHERE student_matric = ?
         GROUP BY status"
    );

    $stmt->bind_param("s", $matric);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $count = (int) $row['total'];
        $status = strtolower(trim($row['status'] ?? ''));

        $totalAppointments += $count;

        if ($status === 'pending') {
            $pendingAppointments += $count;
        } elseif ($status === 'approved') {
            $approvedAppointments += $count;
        } elseif ($status === 'rejected') {
            $rejectedAppointments += $count;
        }
    }

    $stmt->close();
} catch (Throwable $e) {
    $databaseError = true;
}

// Count unread notifications.
$unreadNotifications = 0;

try {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM notifications
         WHERE student_matric = ? AND is_read = 0"
    );

    $stmt->bind_param("s", $matric);
    $stmt->execute();

    $notificationResult = $stmt->get_result();
    $notificationRow = $notificationResult->fetch_assoc();

    $unreadNotifications = (int) ($notificationRow['total'] ?? 0);

    $stmt->close();
} catch (Throwable $e) {
    $unreadNotifications = 0;
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$initial = strtoupper(substr(trim($fullname), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard | NSUK</title>

    <style>
        :root {
            --green: #075b3b;
            --green-dark: #06452f;
            --green-light: #eaf5ef;
            --page: #f5f7f6;
            --white: #ffffff;
            --text: #182820;
            --muted: #718078;
            --border: #e5ebe7;
            --shadow: 0 5px 22px rgba(20, 55, 37, .055);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--page);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 255px;
            flex-shrink: 0;
            background: var(--green-dark);
            color: white;
            padding: 28px 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 28px;
            border-bottom: 1px solid rgba(255,255,255,.14);
        }

        .brand-mark {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            font-size: 19px;
            font-weight: 800;
        }

        .brand-mark img {
            max-width: 40px;
            max-height: 40px;
            object-fit: contain;
        }

        .brand strong {
            display: block;
            font-size: 15px;
            letter-spacing: .5px;
        }

        .brand small {
            display: block;
            margin-top: 5px;
            color: #c4dbce;
            font-size: 11px;
        }

        .menu-label {
            margin: 30px 10px 12px;
            color: #a9c7b6;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 7px;
            padding: 13px 14px;
            border-radius: 8px;
            color: #e0eee6;
            font-size: 13px;
            transition: .2s;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,.13);
            color: white;
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar-bottom {
            margin-top: 45px;
            padding: 18px 10px 0;
            border-top: 1px solid rgba(255,255,255,.14);
            color: #b8d0c2;
            font-size: 11px;
            line-height: 1.8;
        }

        .main {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            min-height: 82px;
            padding: 18px 35px;
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 700;
        }

        .topbar p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        /* RED NOTIFICATION BOX AT THE TOP */
        .notification {
            position: relative;
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border: 1px solid black;
            border-radius: 10px;
            background: #ffffff;
            color: #000000;
            font-size: 19px;
        }

        /* RED UNREAD NOTIFICATION COUNT */
        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            display: grid;
            place-items: center;
            border-radius: 20px;
            background: #ff0000;
            color: white;
            font-size: 10px;
            font-weight: bold;
            border: 2px solid white;
        }

        /* SIDEBAR NOTIFICATION COUNT */
        .sidebar-notification-count {
            margin-left: auto;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: #c43c3c;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 41px;
            height: 41px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--green-light);
            color: var(--green);
            font-weight: 800;
        }

        .user-name {
            font-size: 12px;
            font-weight: 700;
        }

        .user-role {
            margin-top: 4px;
            color: var(--muted);
            font-size: 11px;
        }

        .content {
            max-width: 1450px;
            margin: auto;
            padding: 32px 35px;
        }

        .welcome {
            position: relative;
            overflow: hidden;
            padding: 30px;
            border-radius: 13px;
            background: linear-gradient(115deg, #075b3b, #108054);
            color: white;
            box-shadow: var(--shadow);
        }

        .welcome::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            top: -105px;
            right: -45px;
            border: 35px solid rgba(255,255,255,.07);
            border-radius: 50%;
        }

        .eyebrow {
            color: #d0e7d9;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1.8px;
        }

        .welcome h2 {
            margin: 13px 0 10px;
            font-size: 25px;
        }

        .welcome p {
            max-width: 600px;
            margin: 0;
            color: #e0f0e7;
            font-size: 13px;
            line-height: 1.8;
        }

        .primary-btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-top: 23px;
            padding: 12px 17px;
            border-radius: 7px;
            background: white;
            color: var(--green-dark);
            font-size: 12px;
            font-weight: 700;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin: 31px 0 16px;
        }

        .section-heading h3 {
            margin: 0;
            font-size: 16px;
        }

        .section-heading p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .stat-card {
            padding: 21px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: white;
            box-shadow: var(--shadow);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--muted);
            font-size: 12px;
        }

        .stat-icon {
            width: 35px;
            height: 35px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: var(--green-light);
            color: var(--green);
            font-size: 16px;
        }

        .stat-number {
            margin-top: 18px;
            font-size: 29px;
            font-weight: 800;
        }

        .stat-note {
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
        }

        .services {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .service-card {
            padding: 23px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: white;
            box-shadow: var(--shadow);
        }

        .service-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--green-light);
            color: var(--green);
            font-size: 20px;
        }

        .service-card h4 {
            margin: 17px 0 9px;
            font-size: 14px;
        }

        .service-card p {
            min-height: 38px;
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .service-link {
            display: inline-block;
            margin-top: 17px;
            color: var(--green);
            font-size: 12px;
            font-weight: 700;
        }

        .student-info {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-top: 16px;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: white;
        }

        .info-label {
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 11px;
        }

        .info-value {
            overflow-wrap: anywhere;
            font-size: 13px;
            font-weight: 700;
        }

        .warning {
            margin-top: 20px;
            padding: 13px 16px;
            border: 1px solid #f0dfb2;
            border-radius: 8px;
            background: #fff9e9;
            color: #795c1e;
            font-size: 12px;
        }

        footer {
            margin-top: 35px;
            padding: 20px 0 5px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            text-align: center;
            font-size: 11px;
        }

        @media (max-width: 1050px) {
            .sidebar {
                width: 215px;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .content {
                padding: 25px;
            }

            .topbar {
                padding: 18px 25px;
            }
        }

        @media (max-width: 700px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 15px;
            }

            .brand {
                padding-bottom: 14px;
            }

            .menu-label,
            .sidebar-bottom {
                display: none;
            }

            .sidebar nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 12px;
            }

            .nav-link {
                margin: 0;
                padding: 10px;
                font-size: 11px;
            }

            .topbar {
                padding: 17px;
            }

            .topbar h1 {
                font-size: 17px;
            }

            .user-details {
                display: none;
            }

            .content {
                padding: 18px 14px;
            }

            .welcome {
                padding: 23px;
            }

            .welcome h2 {
                font-size: 21px;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-number {
                font-size: 25px;
            }

            .services,
            .student-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">
                <img src="logo-new.png" width="40" alt="School logo">
            </div>

            <div>
                <strong>NSUK PORTAL</strong>
                <small>Student Appointment Booking System</small>
            </div>
        </div>

        <div class="menu-label">Main Menu</div>

        <nav>
            <a class="nav-link active" href="student_dashboard.php">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a class="nav-link" href="book_appointment.php">
                <span class="nav-icon">＋</span>
                Book Appointment
            </a>

            <!-- Updated link pointing to mark_read.php -->
            <a class="nav-link" href="mark_read.php">
                <span class="nav-icon">♧</span>
                <span>Notifications</span>

                <?php if ($unreadNotifications > 0): ?>
                    <span class="sidebar-notification-count">
                        <?= $unreadNotifications ?>
                    </span>
                <?php endif; ?>
            </a>

            <a class="nav-link" href="logout.php">
                <span class="nav-icon">↪</span>
                Logout
            </a>
        </nav>

        <div class="sidebar-bottom">
            Nasarawa State University, Keffi<br>
            Student Appointment Portal
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1>Student Dashboard</h1>
                <p>Manage your appointments and student services.</p>
            </div>

            <div class="top-actions">
                <!-- Updated link pointing to mark_read.php -->
                <a class="notification"
                   href="mark_read.php"
                   title="View notifications"
                   aria-label="View notifications">

                    ♧

                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-count">
                            <?= $unreadNotifications ?>
                        </span>
                    <?php endif; ?>
                </a>

                <div class="user">
                    <div class="avatar">
                        <?= h($initial ?: 'S') ?>
                    </div>

                    <div class="user-details">
                        <div class="user-name">
                            <?= h($fullname) ?>
                        </div>

                        <div class="user-role">NSUK Student</div>
                    </div>
                </div>
            </div>
        </header>

        <div class="content">
            <section class="welcome">
                <div class="eyebrow">NSUK STUDENT PORTAL</div>

                <h2>Welcome back, <?= h($fullname) ?>!</h2>

                <p>
                    Manage your appointments with university staff, check
                    appointment updates, and keep track of your requests
                    from your personal dashboard.
                </p>

                <a class="primary-btn" href="book_appointment.php">
                    <span>＋</span>
                    Book a New Appointment
                </a>
            </section>

            <?php if ($databaseError): ?>
                <div class="warning">
                    Appointment statistics could not be loaded. Your
                    existing database may use different appointment
                    table or column names. Please check the database
                    structure before changing any tables.
                </div>
            <?php endif; ?>

            <div class="section-heading">
                <div>
                    <h3>Appointment Overview</h3>
                    <p>Summary of your appointment requests.</p>
                </div>
            </div>

            <section class="stats">
                <div class="stat-card">
                    <div class="stat-top">
                        Total Appointments
                        <span class="stat-icon">▤</span>
                    </div>

                    <div class="stat-number">
                        <?= $totalAppointments ?>
                    </div>

                    <div class="stat-note">All your requests</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        Pending
                        <span class="stat-icon">◷</span>
                    </div>

                    <div class="stat-number">
                        <?= $pendingAppointments ?>
                    </div>

                    <div class="stat-note">Awaiting staff response</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        Approved
                        <span class="stat-icon">✓</span>
                    </div>

                    <div class="stat-number">
                        <?= $approvedAppointments ?>
                    </div>

                    <div class="stat-note">Approved appointments</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        Rejected
                        <span class="stat-icon">×</span>
                    </div>

                    <div class="stat-number">
                        <?= $rejectedAppointments ?>
                    </div>

                    <div class="stat-note">Requests not approved</div>
                </div>
            </section>

            <div class="section-heading">
                <div>
                    <h3>Student Services</h3>
                    <p>Quick access to your appointment services.</p>
                </div>
            </div>

            <section class="services">
                <article class="service-card">
                    <div class="service-icon">＋</div>

                    <h4>Book an Appointment</h4>

                    <p>
                        Submit a request to meet with a university
                        staff member.
                    </p>

                    <a class="service-link" href="book_appointment.php">
                        Book Appointment →
                    </a>
                </article>

                <article class="service-card">
                    <div class="service-icon">♧</div>

                    <h4>Notifications</h4>

                    <p>
                        Check updates about approved, pending, or
                        rejected appointment requests.
                    </p>

                    <a class="service-link" href="student_notifications.php">
                        View Notifications →
                    </a>
                </article>
            </section>

            <div class="section-heading">
                <div>
                    <h3>Student Information</h3>
                    <p>Your registered student details.</p>
                </div>
            </div>

            <section class="student-info">
                <div>
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?= h($fullname) ?></div>
                </div>

                <div>
                    <div class="info-label">Matric Number</div>
                    <div class="info-value"><?= h($matric) ?></div>
                </div>

                <div>
                    <div class="info-label">Department</div>
                    <div class="info-value"><?= h($department) ?></div>
                </div>
            </section>

            <footer>
                © <?= date('Y') ?> NSUK Student Appointment Booking System.
                All rights reserved.
            </footer>
        </div>
    </main>
</div>
</body>
</html>