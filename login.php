
<?php
session_start();
require_once __DIR__ . '/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric = trim($_POST['matric'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($matric === '' || $password === '') {
        $message = 'Please enter your matric number and password.';
    } else {
        $sql = "SELECT fullname, password FROM student WHERE matric = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $message = 'Database error. Please check your student table and db.php.';
        } else {
            $stmt->bind_param('s', $matric);

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($fullname, $hashedPassword);
                    $stmt->fetch();

                    if (password_verify($password, $hashedPassword)) {
                        session_regenerate_id(true);

                        $_SESSION['matric'] = $matric;
                        $_SESSION['fullname'] = $fullname;

                        $stmt->close();
                        header('Location: student_dashboard.php');
                        exit;
                    } else {
                        $message = 'Incorrect matric number or password.';
                    }
                } else {
                    $message = 'Incorrect matric number or password.';
                }
            } else {
                $message = 'Unable to check your login. Please try again.';
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login | NSUK</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", Arial, sans-serif;
    background: linear-gradient(135deg, #022c1a, #087443, #021f14);
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
    width: 76px;
    height: 76px;
    margin: 0 auto 12px;
    /* border: 2px solid #d8b65b; */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #d8b65b;
    font-size: 22px;
    font-weight: 800;
}

.brand h1 {
    font-size: 19px;
    letter-spacing: 1px;
    margin-bottom: 7px;
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
}

.card {
    background: white;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 25px 65px rgba(0,0,0,.28);
}

.card h2 {
    margin: 0 0 8px;
    font-size: 27px;
    color: #03351f;
}

.subtitle {
    margin: 0 0 26px;
    color: #718078;
    font-size: 14px;
    line-height: 1.6;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin: 18px 0 8px;
    color: #294538;
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
}

input {
    width: 100%;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    padding: 14px 12px;
    font-size: 14px;
}

.toggle-password {
    border: none;
    background: transparent;
    color: #07552d;
    font-weight: bold;
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
    font-size: 13px;
    overflow-wrap: anywhere;
}

.register {
    text-align: center;
    margin-top: 23px;
    font-size: 13px;
    color: #718078;
}

a {
    color: #07552d;
    font-weight: 700;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

.home-link {
    text-align: center;
    margin-top: 18px;
    font-size: 13px;
}

.footer {
    text-align: center;
    color: #d5e5da;
    font-size: 11px;
    margin-top: 24px;
    line-height: 1.7;
}

@media (max-width: 480px) {
    .card {
        padding: 27px 22px;
    }

    .card h2 {
        font-size: 24px;
    }
}
</style>
</head>

<body>
<div class="page">

    <header class="brand">
        <div class="logo"><img src="logo-new.png" width="80px" alt="school logo"></div>
        <h1>NASARAWA STATE UNIVERSITY</h1>
        <p>KEFFI, NASARAWA STATE</p>
        <div class="gold-line"></div>
    </header>

    <main class="card">
        <h2>Welcome Back!</h2>
        <p class="subtitle">
            Sign in to your student account to manage your appointments.
        </p>

        <?php if ($message !== ''): ?>
            <p class="message" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label for="matric">Matric Number</label>
            <div class="input-wrap">
                <span class="input-icon" aria-hidden="true">&#128100;</span>
                <input
                    type="text"
                    id="matric"
                    name="matric"
                    placeholder="Enter your matric number"
                    autocomplete="username"
                    value="<?= htmlspecialchars($_POST['matric'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
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
                    class="toggle-password"
                    type="button"
                    id="togglePassword"
                >Show</button>
            </div>

            <button type="submit" class="login-btn">
                Sign In to Student Portal &rarr;
            </button>
        </form>

        <p class="register">
            New student?
            <a href="register.php">Create an account</a>
        </p>

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
});
</script>
</body>
</html>
