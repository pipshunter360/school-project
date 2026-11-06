
<?php
session_start();
require_once __DIR__ . '/db.php';

$message = '';
$messageType = '';

$fullname = '';
$matric = '';
$email = '';
$department = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $matric = trim($_POST['matric'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $fullname === '' ||
        $matric === '' ||
        $email === '' ||
        $department === '' ||
        $password === ''
    ) {
        $message = 'Please fill in every field.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Your password must contain at least 8 characters.';
        $messageType = 'error';
    } else {
        $check = $conn->prepare(
            'SELECT id FROM student WHERE matric = ? OR email = ?'
        );

        if (!$check) {
            $message = 'Unable to process registration right now.';
            $messageType = 'error';
        } else {
            $check->bind_param('ss', $matric, $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $message = 'This matric number or email is already registered.';
                $messageType = 'error';
            } else {
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    'INSERT INTO student
                    (fullname, matric, email, department, password)
                    VALUES (?, ?, ?, ?, ?)'
                );

                if (!$stmt) {
                    $message = 'Unable to create your account. Please try again.';
                    $messageType = 'error';
                } else {
                    $stmt->bind_param(
                        'sssss',
                        $fullname,
                        $matric,
                        $email,
                        $department,
                        $hashedPassword
                    );

                    if ($stmt->execute()) {
                        $message = 'Registration successful! You can now sign in.';
                        $messageType = 'success';

                        $fullname = '';
                        $matric = '';
                        $email = '';
                        $department = '';
                    } else {
                        $message = 'Registration failed. Please try again.';
                        $messageType = 'error';
                    }

                    $stmt->close();
                }
            }

            $check->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Registration | NSUK</title>

    <style>
        :root {
            --green: #07552d;
            --green-dark: #063d22;
            --green-light: #244d38;
            --text: #cbd4ce;
            --muted: #dee7e1;
            --border: #dce4de;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background:#07673C;
            color: var(--text);
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 16px 6%;
        }

        .brand {
            max-width: 1120px;
            margin: auto;
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand img {
            width: 54px;
            height: 54px;
            object-fit: contain;
        }

        .brand-name {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: 1.2px;
            color: var(--green);
        }

        .brand-subtitle {
            margin: 4px 0 0;
            color: black;
            font-size: 12px;
        }

        .main {
            width: 100%;
            max-width: 1120px;
            margin: auto;
            padding: 44px 20px;
            display: grid;
            grid-template-columns: 1fr 440px;
            align-items: center;
            gap: 65px; 
        }

        .intro {
            padding: 10px 0;
        }

        .eyebrow {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 5px;
            background: #e4eee7;
            color: var(--green);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .intro h1 {
            margin: 22px 0 16px;
            font-size: clamp(30px, 4vw, 45px);
            line-height: 1.15;
            letter-spacing: -1px;
            color: #ffffff;
        }

        .intro > p {
            max-width: 490px;
            color: #fcfffd;
            font-size: 15px;
            line-height: 1.9;
        }

        .feature {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-top: 26px;
        }

        .feature-icon {
            width: 37px;
            height: 37px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: #e2eee5;
            color: var(--green);
            font-weight: 800;
        }

        .feature strong {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .feature span {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .form-card {
            background: var(--white);
            padding: 34px;
            border: 1px solid #e3e9e4;
            border-radius: 12px;
            box-shadow: 0 15px 45px rgba(25, 55, 35, 0.07);
        }

        .form-card h2 {
            margin: 0 0 9px;
            color: var(--green-dark);
            font-size: 25px;
        }

        .form-description {
            margin: 0 0 25px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .message {
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }

        .message.error {
            color: #8b2525;
            background: #fff0f0;
            border: 1px solid #f2cccc;
        }

        .message.success {
            color: #14532d;
            background: #eaf7ee;
            border: 1px solid #c5e5ce;
        }

        .field {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #26352b;
            font-size: 13px;
            font-weight: 650;
        }

        input, select {
            width: 100%;
            min-height: 46px;
            padding: 12px 13px;
            border: 1px solid #d5ded7;
            border-radius: 6px;
            background: #fff;
            color: #000000;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        input::placeholder {
            color: #9aa59d;
        }

        input:focus, select:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(7, 85, 45, .09);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 65px;
        }

        .show-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--green);
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .submit-btn {
            width: 100%;
            min-height: 48px;
            margin-top: 5px;
            padding: 13px;
            border: none;
            border-radius: 6px;
            background: var(--green);
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .submit-btn:hover {
            background: var(--green-dark);
        }

        .submit-btn:active {
            transform: scale(.99);
        }

        .signin {
            margin: 23px 0 0;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        a {
            color: var(--green);
            font-weight: 650;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .home-link {
            display: block;
            margin-top: 17px;
            text-align: center;
            font-size: 13px;
        }

        footer {
            padding: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: #7a867d;
            font-size: 12px;
            background: #fff;
        }

        @media (max-width: 850px) {
            .main {
                max-width: 560px;
                grid-template-columns: 1fr;
                gap: 28px;
                padding: 30px 18px;
            }

            .intro {
                text-align: center;
            }

            .intro > p {
                margin-left: auto;
                margin-right: auto;
            }

            .feature {
                text-align: left;
                max-width: 370px;
                margin: 20px auto 0;
            }

            .form-card {
                padding: 27px 22px;
            }
        }

        @media (max-width: 420px) {
            .topbar {
                padding: 14px 18px;
            }

            .brand img {
                width: 44px;
                height: 44px;
            }

            .brand-name {
                font-size: 18px;
            }

            .brand-subtitle {
                font-size: 11px;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <div class="brand">
        <img
            src="logo-new.png"
            alt="NSUK Logo"
            onerror="this.style.display='none'"
        >

        <div>
            <p class="brand-name">NSUK PORTAL</p>
            <p class="brand-subtitle">
                Student Appointment Booking System
            </p>
        </div>
    </div>
</header>

<main class="main">

    <section class="intro">
        <span class="eyebrow">Student Administration</span>

        <h1>Your academic journey, better connected.</h1>

        <p>
            Create your student account to access the NSUK Student
            Appointment Booking System. Once registered, you can
            sign in to book and manage appointments with university staff.
        </p>

        <div class="feature">
            <div class="feature-icon">&#10003;</div>
            <div>
                <strong>Easy Appointment Booking</strong>
                <span>
                    Request appointments with staff through your student portal.
                </span>
            </div>
        </div>

        <div class="feature">
            <div class="feature-icon">&#9673;</div>
            <div>
                <strong>Track Appointment Status</strong>
                <span>
                    View your appointment requests and check their latest status.
                </span>
            </div>
        </div>
    </section>

    <section class="form-card">
        <h2>Create an Account</h2>

        <p class="form-description">
            Enter your details below to register as a student.
        </p>

        <?php if ($message !== ''): ?>
            <div
                class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"
                role="status"
            >
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>

                <?php if ($messageType === 'success'): ?>
                    <a href="login.php">Sign in now</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="field">
                <label for="fullname">Full Name</label>
                <input
                    type="text"
                    id="fullname"
                    name="fullname"
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="field">
                <label for="matric">Matric Number</label>
                <input
                    type="text"
                    id="matric"
                    name="matric"
                    placeholder="Enter your matric number"
                    value="<?= htmlspecialchars($matric, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="field">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="field">
                <label for="department">Department</label>
                <select id="department" name="department" required>
                    <option value="">Select your department</option>

                    <?php
                    $departments = [
                        'Computer Science',
                        'Mathematics',
                        'Physics',
                        'Statistics',
                        'Other'
                    ];

                    foreach ($departments as $item):
                    ?>
                        <option
                            value="<?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $department === $item ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="password">Password</label>

                <div class="password-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create a secure password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        class="show-password"
                        type="button"
                        id="togglePassword"
                        aria-controls="password"
                        aria-pressed="false"
                    >
                        Show
                    </button>
                </div>
            </div>

            <button class="submit-btn" type="submit">
                Create Student Account
            </button>

        </form>

        <p class="signin">
            Already registered?
            <a href="login.php">Sign in to Student Portal</a>
        </p>

        <a class="home-link" href="index.html">
            &larr; Return to Home Page
        </a>
    </section>

</main>

<footer>
    &copy; <?= date('Y') ?> NSUK Student Appointment Booking System.
    All rights reserved.
</footer>

<script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', function () {
        const isHidden = passwordInput.type === 'password';

        passwordInput.type = isHidden ? 'text' : 'password';
        togglePassword.textContent = isHidden ? 'Hide' : 'Show';
        togglePassword.setAttribute('aria-pressed', String(isHidden));
    });
</script>

</body>
</html>
