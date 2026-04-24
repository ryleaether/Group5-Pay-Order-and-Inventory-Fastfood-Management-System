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
        $message = $result;
    }
}
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

            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>

            <button type="submit">Login</button>

            <p class="message"><?= $message ?? '' ?></p>

            <a href="registration.php">Create account</a>
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

</body>
</html>