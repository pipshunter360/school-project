
<?php
require 'db.php';
session_start();

if (!isset($_SESSION['matric'])) {
    header("Location: login.php");
    exit;
}

$matric = $_SESSION['matric'];

$stmt = $conn->prepare(
    "SELECT fullname, matric, department FROM student WHERE matric = ?"
);
$stmt->bind_param("s", $matric);
$stmt->execute();
$stmt->bind_result($fullname, $studentMatric, $department);

if (!$stmt->fetch()) {
    $stmt->close();
    session_destroy();
    header("Location: login.php");
    exit;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | NSUK</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef3ee;
            color: #222;
        }

        header {
            background: #07552d;
            color: white;
            padding: 20px;
        }

        .header-content {
            max-width: 850px;
            margin: auto;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .school-logo {
            width: 75px;
            height: 75px;
            object-fit: contain;
            background: white;
            padding: 5px;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .header-text h1 {
            margin: 0 0 8px;
            font-size: 25px;
        }

        .header-text p {
            margin: 0;
        }

        main {
            max-width: 850px;
            margin: 30px auto;
            padding: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px #0001;
        }

        h2 {
            color: #07552d;
        }

        .button {
            display: inline-block;
            background: #07552d;
            color: white;
            padding: 12px 18px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 10px;
        }

        @media (max-width: 500px) {
            .header-content {
                align-items: flex-start;
                gap: 12px;
            }

            .school-logo {
                width: 55px;
                height: 55px;
            }

            .header-text h1 {
                font-size: 20px;
            }

            main {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="header-content">

    <div style="display:flex; align-items:center; gap:15px;">
        <img src="logo-new.png"
             alt="NSUK Logo"
             style="width:75px; height:75px; object-fit:contain;
            background:white; padding:5px; border-radius:10px;">

        <div>
            <h2 style="color:white; margin:0 0 8px;">
                NSUK Student Portal
            </h2>
            <p style="margin:0;">Student Dashboard</p>
        </div>
    </div>
</header>
            >

            <div class="header-text">
                <h1>NSUK Student Portal</h1>
                <p>Student Dashboard</p>
            </div>
        </div>
    </header>

    <main>
        <div class="card">
            <h2>Welcome, <?= htmlspecialchars($fullname) ?>!</h2>
            <p>You are logged in to your student portal.</p>
        </div>

        <div class="card">
            <h2>My Student Details</h2>

            <p><strong>Full Name:</strong>
                <?= htmlspecialchars($fullname) ?></p>

            <p><strong>Matric Number:</strong>
                <?= htmlspecialchars($studentMatric) ?></p>

            <p><strong>Department:</strong>
                <?= htmlspecialchars($department) ?></p>
        </div>

        <div class="card">
            <h2>Appointments</h2>
            <p>Book and manage your appointments with university staff here.</p>
            <p>Appointment booking will be connected in our next step.</p>
        </div>
    </main>
</body>
</html>

<?php
require 'db.php';
session_start();

if (!isset($_SESSION['matric'])) {
    header("Location: login.php");
    exit;
}

$matric = $_SESSION['matric'];

$stmt = $conn->prepare(
    "SELECT fullname, matric, department FROM student WHERE matric = ?"
);
$stmt->bind_param("s", $matric);
$stmt->execute();
$stmt->bind_result($fullname, $studentMatric, $department);

if (!$stmt->fetch()) {
    $stmt->close();
    session_destroy();
    header("Location: login.php");
    exit;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | NSUK</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef3ee;
            color: #222;
        }

        header {
            background: #07552d;
            color: white;
            padding: 20px;
        }

        .header-content {
            max-width: 850px;
            margin: auto;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .school-logo {
            width: 75px;
            height: 75px;
            object-fit: contain;
            background: white;
            padding: 5px;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .header-text h1 {
            margin: 0 0 8px;
            font-size: 25px;
        }

        .header-text p {
            margin: 0;
        }

        main {
            max-width: 850px;
            margin: 30px auto;
            padding: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px #0001;
        }

        h2 {
            color: #07552d;
        }

        .button {
            display: inline-block;
            background: #07552d;
            color: white;
            padding: 12px 18px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 10px;
        }

        @media (max-width: 500px) {
            .header-content {
                align-items: flex-start;
                gap: 12px;
            }

            .school-logo {
                width: 55px;
                height: 55px;
            }

            .header-text h1 {
                font-size: 20px;
            }

            main {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="header-content">
            <img
                src="nsuk_logo.png"
                alt="Nasarawa State University, Keffi logo"
                class="school-logo"
            >

            <div class="header-text">
                <h1>NSUK Student Portal</h1>
                <p>Student Dashboard</p>
            </div>
        </div>
    </header>

    <main>
        <div class="card">
            <h2>Welcome, <?= htmlspecialchars($fullname) ?>!</h2>
            <p>You are logged in to your student portal.</p>
        </div>

        <div class="card">
            <h2>My Student Details</h2>

            <p><strong>Full Name:</strong>
                <?= htmlspecialchars($fullname) ?></p>

            <p><strong>Matric Number:</strong>
                <?= htmlspecialchars($studentMatric) ?></p>

            <p><strong>Department:</strong>
                <?= htmlspecialchars($department) ?></p>
        </div>

        <div class="card">
            <h2>Appointments</h2>
            <p>Book and manage your appointments with university staff here.</p>
            <p>Appointment booking will be connected in our next step.</p>
        </div>
    </main>
</body>
</html>
