
<?php
session_start();
require_once __DIR__ . '/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = 'Please enter your username and password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, username, password, fullname
             FROM lecturers WHERE username = ?"
        );

        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $valid = password_verify($password, $row['password'])
                    || $password === $row['password'];

                if ($valid) {
                    session_regenerate_id(true);

                    $_SESSION['lecturer_logged_in'] = true;
                    $_SESSION['lecturer_id'] = $row['id'];
                    $_SESSION['lecturer_name'] =
                        strtolower($row['username']) === 'lecturer'
                        ? 'musa audu'
                        : $row['fullname'];

                    $stmt->close();
                    header("Location: staff_dashboard.php");
                    exit;
                }
            }

            $message = 'Invalid username or password.';
            $stmt->close();
        } else {
            $message = 'Unable to process login. Please check the database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Login | NSUK</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", Arial, sans-serif;
    background:
        radial-gradient(circle at 10% 10%, #176442, transparent 35%),
        linear-gradient(135deg, #022c1a, #07552d, #021f14);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 16px;
    color: #19352a;
}

.page {
    width: 100%;
    max-width: 460px;
}

.brand {
    text-align: center;
    color: white;
    margin-bottom: 25px;
}

.logo {
    width: 90px;
    height: 90px;
    margin: 0 auto 12px;
    /* border: 2px solid #d8b65b; */
    border-radius: 50%;
    /* background: white; */
    display: flex;
    align-items: center;
    justify-content: center;
    /* overflow: hidden; */
}

.logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.logo-placeholder {
    color: #07552d;
    font-size: 21px;
    font-weight: 800;
}

.brand h1 {
    font-size: 19px;
    letter-spacing: 1px;
    margin: 0 0 7px;
}

.brand p {
    color: #d9e8df;
    font-size: 12px;
    letter-spacing: 1px;
    margin: 0;
}

.gold-line {
    width: 55px;
    height: 3px;
    background: #d8b65b;
    margin: 17px auto 0;
    border-radius: 5px;
}

.login-box {
    background: white;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 25px 65px rgba(0,0,0,.28);
}

.login-box h2 {
    margin: 0 0 8px;
    font-size: 27px;
    color: #03351f;
}

.subtitle {
    margin: 0 0 25px;
    color: #718078;
    font-size: 14px;
    line-height: 1.6;
}

.badge {
    display: inline-block;
    background: #edf6ef;
    color: #07552d;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 18px;
}

label {
    display: block;
    margin: 18px 0 8px;
    color: #294538;
    font-size: 13px;
    font-weight: 700;
}

.input-wrap {
    display: flex;
    align-items: center;
    border: 1px solid #dbe5de;
    border-radius: 10px;
    background: #fbfdfb;
}

.input-wrap:focus-within {
    border-color: #07552d;
    box-shadow: 0 0 0 3px rgba(7,85,45,.09);
}

.input-icon {
    padding-left: 13px;
    color: #07552d;
    font-size: 17px;
}

input {
    width: 100%;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    padding: 14px 12px;
    font-size: 14px;
    color: #19352a;
}

input::placeholder {
    color: #a1ada5;
}

.toggle-password {
    border: none;
    background: transparent;
    color: #07552d;
    font-weight: 700;
    padding: 10px;
    cursor: pointer;
}

.login-btn {
    width: 100%;
    border: none;
    border-radius: 10px;
    padding: 15px;
    margin-top: 26px;
    background: linear-gradient(135deg, #087441, #034a29);
    color: white;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(7,85,45,.25);
}

.message {
    padding: 12px;
    border-radius: 9px;
    color: #982b2b;
    background: #fff0f0;
    border: 1px solid #f4cccc;
    font-size: 13px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.home-link {
    text-align: center;
    margin-top: 23px;
    font-size: 13px;
}

a {
    color: #07552d;
    font-weight: 700;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

.footer {
    text-align: center;
    color: #d5e5da;
    font-size: 11px;
    margin-top: 24px;
    line-height: 1.7;
}

@media (max-width: 480px) {
    .login-box {
        padding: 27px 22px;
    }

    .login-box h2 {
        font-size: 24px;
    }
}
</style>
</head>

<body>
<div class="page">

    <header class="brand">
        <div class="logo">
                <img src="logo-new.png" width= "50px" alt="school logo">
            <span class="logo-placeholder" style="display:none">NSUK</span>
        </div>

        <h1>NASARAWA STATE UNIVERSITY</h1>
        <p>KEFFI, NASARAWA STATE</p>
        <div class="gold-line"></div>
    </header>

    <main class="login-box">
        <span class="badge">STAFF PORTAL</span>

        <h2>Welcome Back!</h2>
        <p class="subtitle">
            Sign in to manage student appointment requests.
        </p>

        <?php if ($message !== ''): ?>
            <p class="message" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form action="staff_login.php" method="POST">
            <label for="username">Staff Username</label>

            <div class="input-wrap">
                <span class="input-icon" aria-hidden="true">&#128100;</span>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your staff username"
                    autocomplete="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <label for="password">Password</label>

            <div class="input-wrap">
                <span class="input-icon" aria-hidden="true">&#128274;</span>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
                <button
                    type="button"
                    class="toggle-password"
                    id="togglePassword"
                    aria-controls="password"
                    aria-pressed="false"
                >Show</button>
            </div>

            <button type="submit" class="login-btn">
                Sign In to Staff Portal &rarr;
            </button>

            
<p style="text-align: center; margin-top: 20px; color: #555;">
    Don't have a staff account?
    <a href="staff_register.php"
       style="color: #075b35; font-weight: bold; text-decoration: none;">
        Create Staff Account
    </a>
</p>

        </form>

        <div class="home-link">
            <a href="index.html">&larr; Back to Home</a>
        </div>
    </main>

    <footer class="footer">
        Student Appointment Booking System<br>
        &copy; <?= date('Y') ?> NSUK. All rights reserved.
    </footer>

</div>

<script>
const toggleButton = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

toggleButton.addEventListener('click', function () {
    const isHidden = passwordInput.type === 'password';

    passwordInput.type = isHidden ? 'text' : 'password';
    toggleButton.textContent = isHidden ? 'Hide' : 'Show';
    toggleButton.setAttribute('aria-pressed', String(isHidden));
});
</script>
</body>
</html>
