<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/validation.php";

$val = new Validation();
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $result = $val->login($username, $password);

    if (is_array($result)) {

        // STORE SESSION DATA
        $_SESSION['admin_id']      = $result['admin_id'];
        $_SESSION['username']      = $result['username'];
        $_SESSION['role']          = $result['role'];
        $_SESSION['fastfood_name'] = $result['fastfood_name'];

        // ROLE-BASED REDIRECT
        $redirect = ($result['role'] === 'superadmin')
            ? "dashboard/superadmin.php"
            : "dashboard/admindashboard.php";

        header("Location: " . $redirect);
        exit;

    } else {
        $_SESSION['error']        = "Invalid username or password";
        $_SESSION['old_username'] = $username;
        header("Location: login.php");
        exit;
    }
}

// Pull messages from session
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Clear saved username when arriving from another page
if (isset($_GET['clear_old'])) {
    unset($_SESSION['old_username']);
}

$old_username = $_SESSION['old_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iPOS — Sign In</title>
    <link rel="stylesheet" href="../design/mainstyle.css">
</head>
<body>

<div class="login-wrapper">

    <!-- ===== LEFT: Brand Panel ===== -->
    <div class="login-left">

        <!-- Logo -->
        <div class="logo-box">
            <div class="logo-circle">iP</div>
            <div class="logo-text">
                <h2>iPOS</h2>
                <p>I Pay, I Order, I Serve</p>
            </div>
        </div>

        <!-- Brand content -->
        <div class="login-brand">
            <span class="eyebrow">✦ Point of Sale</span>

            <h1>Welcome to <span>iPOS</span></h1>

            <p>
                A modern POS system built for fastfood businesses.
                Manage orders, monitor sales, and control your store
                in real-time.
            </p>

            <div class="features">
                <span>⚡ Real-time Order Tracking</span>
                <span>📊 Sales Analytics Dashboard</span>
                <span>🍔 Product &amp; Menu Control</span>
                <span>👨‍💼 Multi-Owner System</span>
            </div>
        </div>

    </div>

    <!-- ===== RIGHT: Form Panel ===== -->
    <div class="login-right">

        <form method="POST" action="login.php">

            <h3>Sign in to your account</h3>
            <span class="form-subtitle">Enter your credentials to continue</span>

            <?php if (!empty($message)): ?>
                <div class="error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                placeholder="Enter your username"
                value="<?= htmlspecialchars($old_username) ?>"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>
            <div class="password-container">
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
                <button type="button" class="toggle-password" onclick="togglePassword()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>

            <button type="submit">Login</button>

            <a href="registration.php?clear_old=1">Don't have an account? <span>Create one</span></a>

        </form>

    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>