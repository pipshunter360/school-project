
<?php
session_start();
require_once __DIR__ . '/db.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullname === '' || $username === '' || $password === '') {
        $message = 'Please fill in all fields.';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must contain at least 6 characters.';
        $messageType = 'error';
    } else {
        $check = $conn->prepare(
            'SELECT id FROM lecturers WHERE username = ?'
        );
        $check->bind_param('s', $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = 'This username already exists. Please choose another.';
            $messageType = 'error';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                'INSERT INTO lecturers (username, password, fullname)
                 VALUES (?, ?, ?)'
            );
            $stmt->bind_param('sss', $username, $hashedPassword, $fullname);

            if ($stmt->execute()) {
                $message = 'Staff account created successfully. You can now log in.';
                $messageType = 'success';
            } else {
                $message = 'Registration failed. Please try again.';
                $messageType = 'error';
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration | NSUK Portal</title>

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --green: #123d2a;
            --green-dark: #0b2b1d;
            --gold: #c5a45a;
            --text: #f5fffa;
            --muted: #262727;
            --border: #dce3dd;
            --background: #f3f5f3;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #07673C;
            color: var(--text);
            min-height: 100vh;
        }

        .topbar {
            background: #ffffff;
            border-bottom: 4px solid var(--gold);
            color: #ffffff;
            padding: 17px 7%;
        }

        .topbar-inner {
            max-width: 1120px;
            margin: auto;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 55px;
            height: 55px;
            object-fit: contain;
            /* background: #ffffff; */
            padding: 4px;
            border-radius: 3px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 1px;
            margin: 0 0 5px;
            color: black;
        }

        .brand-subtitle {
            margin: 0;
            font-size: 12px;
            color: #000000;
            letter-spacing: 0.4px;
        }

        .page {
            max-width: 1120px;
            margin: 48px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 65px;
            align-items: center;
        }

        .intro {
            padding: 15px 0;
        }

        .eyebrow {
            color: #8c7135;
            text-transform: uppercase;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 16px;
        }

        .intro h1 {
            color: white;
            font-size: clamp(30px, 4vw, 43px);
            line-height: 1.18;
            margin: 0 0 20px;
            font-weight: 700;
        }

        .intro p {
            max-width: 480px;
            font-size: 15px;
            line-height: 1.9;
            color: var(--muted);
            margin: 0;
        }

        .intro-line {
            width: 62px;
            height: 4px;
            background: var(--gold);
            margin-top: 27px;
        }

        .form-panel {
            background: #ffffff;
            border: 1px solid var(--border);
            border-top: 4px solid var(--green);
            padding: 34px;
            box-shadow: 0 8px 28px rgba(25, 48, 34, 0.07);
        }

        .form-heading {
            font-size: 23px;
            color: var(--green-dark);
            margin: 0 0 8px;
        }

        .form-description {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
            margin: 0 0 25px;
        }

        .message {
            padding: 12px 13px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
            border: 1px solid;
        }

        .message.success {
            background: #edf7ef;
            border-color: #b9d9bf;
            color: #205c30;
        }

        .message.error {
            background: #fff2f0;
            border-color: #edc7c1;
            color: #963d32;
        }

        .field {
            margin-bottom: 19px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #34443a;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            height: 46px;
            border: 1px solid #cbd5cd;
            background: #ffffff;
            padding: 0 12px;
            font-size: 14px;
            color: black;
            border-radius: 3px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(18, 61, 42, 0.09);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 70px;
        }

        .show-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--green);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            padding: 5px;
        }

        .submit-button {
            width: 100%;
            min-height: 47px;
            background: var(--green);
            color: #ffffff;
            border: 1px solid var(--green);
            border-radius: 3px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.2px;
            cursor: pointer;
            margin-top: 3px;
            transition: background 0.2s;
        }

        .submit-button:hover {
            background: var(--green-dark);
        }

        .links {
            text-align: center;
            border-top: 1px solid #e7ebe7;
            margin-top: 24px;
            padding-top: 20px;
            font-size: 13px;
            line-height: 2.2;
            color: var(--muted);
        }

        .links a {
            color: var(--green);
            font-weight: 700;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #78827b;
        }

        @media (max-width: 800px) {
            .page {
                grid-template-columns: 1fr;
                gap: 25px;
                margin: 30px auto;
                max-width: 500px;
            }

            .intro {
                padding: 0;
            }

            .intro h1 {
                font-size: 30px;
            }

            .intro p {
                font-size: 14px;
            }

            .intro-line {
                margin-top: 18px;
            }

            .form-panel {
                padding: 25px 22px;
            }

            .topbar {
                padding: 14px 20px;
            }

            .brand-name {
                font-size: 17px;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <div class="topbar-inner">
        <img
            src="logo-new.png"
            alt="NSUK Logo"
            class="logo"
            onerror="this.style.display='none'"
        >

        <div>
            <p class="brand-name">NSUK PORTAL</p>
            <p class="brand-subtitle">
                Student Appointment Management System
            </p>
        </div>
    </div>
</header>

<main class="page">

    <section class="intro">
        <div class="eyebrow">Staff Administration</div>

        <h1>Staff Account<br>Registration</h1>

        <p>
            Create your staff account to access the NSUK Student
            Appointment Management System. Once registered, you can
            sign in to manage student appointment requests.
        </p>

        <div class="intro-line"></div>
    </section>

    <section class="form-panel">
        <h2 class="form-heading">Create an Account</h2>

        <p class="form-description">
            Enter your details below to register as a member of staff.
        </p>

        <?php if ($message !== ''): ?>
            <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label for="fullname">Full Name</label>
                <input
                    type="text"
                    id="fullname"
                    name="fullname"
                    autocomplete="name"
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars($_POST['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="username">Staff Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    placeholder="Choose a username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="password">Password</label>

                <div class="password-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        placeholder="Minimum 6 characters"
                        minlength="6"
                        required
                    >

                    <button
                        type="button"
                        class="show-password"
                        id="togglePassword"
                        aria-label="Show password"
                    >Show</button>
                </div>
            </div>

            <button type="submit" class="submit-button">
                Create Staff Account
            </button>
        </form>

        <div class="links">
            Already registered?
            <a href="staff_login.php">Sign in to Staff Portal</a>
            <br>
            <a href="index.html">Return to Home Page</a>
        </div>
    </section>

</main>

<footer class="footer">
    NSUK Student Appointment Management System
</footer>

<script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', function () {
        const isHidden = passwordInput.type === 'password';

        passwordInput.type = isHidden ? 'text' : 'password';
        togglePassword.textContent = isHidden ? 'Hide' : 'Show';
        togglePassword.setAttribute(
            'aria-label',
            isHidden ? 'Hide password' : 'Show password'
        );
    });
</script>

</body>
</html>
