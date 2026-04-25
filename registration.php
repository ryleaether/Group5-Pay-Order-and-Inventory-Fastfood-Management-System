<?php
session_start();
require_once __DIR__ . "/validation.php";

$val         = new Validation();
$message     = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if ($_POST['password'] !== $_POST['confirm_password']) {
        $message     = "Passwords do not match!";
        $messageType = "error";
        $_SESSION['old'] = [
            'fullname'      => $_POST['fullname'],
            'fastfood_name' => $_POST['fastfood_name'],
            'email'         => $_POST['email'],
            'username'      => $_POST['username']
        ];
    } else {
        $result = $val->register(
            $_POST['username'],
            $_POST['password'],
            $_POST['fullname'],
            $_POST['fastfood_name'],
            $_POST['email']
        );

        if ($result === true) {
            $message     = "Account created successfully!";
            $messageType = "success";
            unset($_SESSION['old']);
        } else {
            $message     = $result;
            $messageType = "error";
            $_SESSION['old'] = [
                'fullname'      => $_POST['fullname'],
                'fastfood_name' => $_POST['fastfood_name'],
                'email'         => $_POST['email'],
                'username'      => $_POST['username']
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - iPOS</title>
    <link rel="stylesheet" href="design/register.css">
</head>
<body>

<div class="reg-wrapper">

    <!-- LEFT SIDE -->
    <div class="reg-left">

        <!-- LOGO -->
        <div class="logo-box">
            <div class="logo-circle">iP</div>
            <div class="logo-text">
                <h2>iPOS</h2>
                <p>I Pay, Order, then Serve</p>
            </div>
        </div>

        <!-- FORM CARD -->
        <div class="reg-card">

            <h3>Create Admin Account</h3>
            <span class="subtitle">Fastfood Owner Registration</span>

            <!-- MESSAGE -->
            <?php if (!empty($message)): ?>
                <div class="msg <?= $messageType ?>">
                    <?= $messageType === 'error' ? '⚠️' : '✅' ?>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="input-group">
                    <span>👤</span>
                    <input type="text" name="fullname" placeholder="Full Name"
                           value="<?= htmlspecialchars($_SESSION['old']['fullname'] ?? '') ?>" required>
                </div>

                <div class="input-group">
                    <span>🍔</span>
                    <input type="text" name="fastfood_name" placeholder="Fast Food Name"
                           value="<?= htmlspecialchars($_SESSION['old']['fastfood_name'] ?? '') ?>" required>
                </div>

                <div class="input-group">
                    <span>✉️</span>
                    <input type="email" name="email" placeholder="Email Address"
                           value="<?= htmlspecialchars($_SESSION['old']['email'] ?? '') ?>" required>
                </div>

                <div class="divider">Account Credentials</div>

                <div class="input-group">
                    <span>🔖</span>
                    <input type="text" name="username" placeholder="Username"
                           value="<?= htmlspecialchars($_SESSION['old']['username'] ?? '') ?>" required>
                </div>

                <div class="input-group">
                    <span>🔒</span>
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <div class="input-group">
                    <span>🔒</span>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>

                <button type="submit">Create Account</button>

            </form>

            <a href="login.php" class="bottom-link">
                Already have an account? <span>Sign in</span>
            </a>

        </div>

    </div>

    <!-- RIGHT SIDE -->
    <div class="reg-right">

        <h1>Start Managing<br>Your Store Today</h1>

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
                    <strong>Secure & Private</strong>
                    <span>Your data is isolated — only you can see your store.</span>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>