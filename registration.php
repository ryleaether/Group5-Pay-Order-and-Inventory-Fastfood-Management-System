<?php
session_start();
require_once __DIR__ . "/validation.php";

$val         = new Validation();
$message     = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if ($_POST['password'] !== $_POST['confirm_password']) {
        $_SESSION['old'] = [
            'fullname'      => $_POST['fullname'],
            'fastfood_name' => $_POST['fastfood_name'],
            'email'         => $_POST['email'],
            'username'      => $_POST['username']
        ];
        $_SESSION['error'] = "Passwords do not match!";
        $_SESSION['error_type'] = "error";
        header("Location: registration.php");
        exit;
    } else {
        $result = $val->register(
            $_POST['username'],
            $_POST['password'],
            $_POST['fullname'],
            $_POST['fastfood_name'],
            $_POST['email']
        );

        if ($result === true) {
            unset($_SESSION['old']);
            $_SESSION['success'] = "Account created successfully! Please login.";
            header("Location: login.php");
            exit;
        } else {
            $_SESSION['old'] = [
                'fullname'      => $_POST['fullname'],
                'fastfood_name' => $_POST['fastfood_name'],
                'email'         => $_POST['email'],
                'username'      => $_POST['username']
            ];
            $_SESSION['error'] = "Registration failed. Please try again.";
            $_SESSION['error_type'] = "error";
            header("Location: registration.php");
            exit;
        }
    }
}

$message     = "";
$messageType = "";
if (isset($_SESSION['error'])) {
    $message     = $_SESSION['error'];
    $messageType = $_SESSION['error_type'] ?? "error";
    unset($_SESSION['error'], $_SESSION['error_type']);
}
if (isset($_SESSION['success'])) {
    $message     = $_SESSION['success'];
    $messageType = "success";
    unset($_SESSION['success']);
}

if (isset($_GET['clear_old'])) {
    unset($_SESSION['old']);
}

$old = $_SESSION['old'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Register - iPOS</title>
    <link rel="stylesheet" href="design/register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <?php include __DIR__ . '/dashboard/helpers/theme_loader.php'; ?>
</head>
<body>

<div class="reg-wrapper">

    <!-- ===== LEFT PANEL (dark) ===== -->
    <div class="reg-left">

        <div class="logo-box">
            <div class="logo-circle">iP</div>
            <div class="logo-text">
                <h2>iPOS</h2>
                <p>I Pay, I Order, I Serve</p>
            </div>
        </div>

        <div class="eyebrow-pill">+ Point of Sale</div>

        <h1>Start Managing<br>Your <span>Store Today</span></h1>

        <p>Join iPOS and take full control of your fast food business —
           from menu management to real-time order tracking, all in one place.</p>

        <div class="reg-steps">

            <div class="step">
                <div class="step-icon">🍔</div>
                <div class="step-text">
                    <strong>Build Your Menu</strong>
                    <span>Add items, set prices, and manage stock levels easily.</span>
                </div>
            </div>

            <div class="step">
                <div class="step-icon">⚡</div>
                <div class="step-text">
                    <strong>Real-time Order Queue</strong>
                    <span>See incoming orders instantly and serve faster.</span>
                </div>
            </div>

            <div class="step">
                <div class="step-icon">📊</div>
                <div class="step-text">
                    <strong>Track Your Sales</strong>
                    <span>Monitor income, top items, and daily performance.</span>
                </div>
            </div>

            <div class="step">
                <div class="step-icon">🔒</div>
                <div class="step-text">
                    <strong>Secure &amp; Private</strong>
                    <span>Your data is isolated — only you can see your store.</span>
                </div>
            </div>

        </div>

    </div>

    <!-- ===== RIGHT PANEL (white form) ===== -->
    <div class="reg-right">
        <div class="reg-card">

            <h3>Create Admin Account</h3>
            <span class="subtitle">Fastfood Owner Registration</span>

            <?php if (!empty($message)): ?>
                <div class="msg <?= $messageType ?>">
                    <?= $messageType === 'error' ? '⚠️' : '✅' ?>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="form-row">
                    <div>
                        <div class="input-group">
                            <span>👤</span>
                            <input type="text" name="fullname" placeholder="Full Name"
                                   value="<?= htmlspecialchars($old['fullname'] ?? '') ?>"
                                   autocomplete="name" required>
                        </div>
                    </div>
                    <div>
                        <div class="input-group">
                            <span>🍔</span>
                            <input type="text" name="fastfood_name" placeholder="Fast Food Name"
                                   value="<?= htmlspecialchars($old['fastfood_name'] ?? '') ?>"
                                   autocomplete="organization" required>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <span>✉️</span>
                    <input type="email" name="email" placeholder="Email Address"
                           value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                           autocomplete="email" required>
                </div>

                <div class="divider">Account Credentials</div>

                <div class="input-group">
                    <span>🔖</span>
                    <input type="text" name="username" placeholder="Username"
                           value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                           autocomplete="username" required>
                </div>

                <div class="form-row">
                    <div>
                        <div class="input-group">
                            <span>🔒</span>
                            <input type="password" name="password" id="password"
                                   placeholder="Password" autocomplete="new-password" required>
                            <button type="button" class="toggle-password"
                                    onclick="togglePassword('password')">👁</button>
                        </div>
                    </div>
                    <div>
                        <div class="input-group">
                            <span>🔒</span>
                            <input type="password" name="confirm_password" id="confirm_password"
                                   placeholder="Confirm Password" autocomplete="new-password" required>
                            <button type="button" class="toggle-password"
                                    onclick="togglePassword('confirm_password')">👁</button>
                        </div>
                    </div>
                </div>

                <button type="submit">Create Account</button>

            </form>

            <a href="login.php?clear_old=1" class="bottom-link">
                Already have an account? <span>Sign in</span>
            </a>

        </div>
    </div>

</div>

<script>
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>