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
        $_SESSION['admin_id'] = $result['admin_id'];
        $_SESSION['username'] = $result['username'];
        $_SESSION['role'] = $result['role'];
        $_SESSION['fastfood_name'] = $result['fastfood_name'];

        // ROLE-BASED REDIRECT (CLEAN)
        $redirect = ($result['role'] === 'superadmin')
            ? "dashboard/superadmin.php"
            : "dashboard/admindashboard.php";

            header("Location: " . $redirect);
            exit;

    } else {
        $_SESSION['error'] = "Invalid username or password";
        $_SESSION['old_username'] = $username;
        header("Location: login.php");
        exit;
    }
}

// Handle error messages from session
$message = "";
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Clear the saved username when arriving explicitly from another page
if (isset($_GET['clear_old'])) {
    unset($_SESSION['old_username']);
}

// Capture old username without clearing it, so it stays on refresh
$old_username = $_SESSION['old_username'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>iPOS Login</title>
    <link rel="stylesheet" href="../design/mainstyle.css">
</head>
<body>

<div class="login-wrapper">

    <!-- LEFT SIDE -->
    <div class="login-left">

        <!-- LOGO -->
        <div class="logo-box">
            <div class="logo-circle">iP</div>
            <div class="logo-text">
                <h2>iPOS</h2>
                <p>I Pay, I Order, I Serve</p>
            </div>
        </div>

        <form method="POST">
            <h3>Sign in to your account</h3>

            <?php if (!empty($message)): ?>
                <p class="message"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>

            <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($old_username) ?>" autocomplete="username" required>
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Password" autocomplete="current-password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">👁</button>
            </div>

            <button type="submit">Login</button>

            <a href="registration.php?clear_old=1">Create account</a>
        </form>

    </div>

    <!-- RIGHT SIDE -->
    <div class="login-right">

        <h1>Welcome to iPOS</h1>

        <p>
            A modern Point-of-Sale system built for fastfood businesses.
            Manage orders, monitor sales, track employees, and control
            your entire store in real-time with one powerful system.
        </p>

        <div class="features">
            <span>⚡ Real-time Order Tracking</span>
            <span>📊 Sales Analytics Dashboard</span>
            <span>🍔 Product & Menu Control</span>
            <span>👨‍💼 Multi-Owner System</span>
        </div>

    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}
</script>

</body>
</html>