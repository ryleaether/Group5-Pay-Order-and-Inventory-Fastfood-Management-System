<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/validation.php";
require_once __DIR__ . "/config/audit_helper.php";

$val = new Validation();
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $result = $val->login($username, $password);

    if (is_array($result)) {

        $_SESSION['admin_id']      = $result['admin_id'];
$_SESSION['username']      = $result['username'];
$_SESSION['role']          = $result['role'];
$_SESSION['fastfood_name'] = $result['fastfood_name'];
$_SESSION['fullname']      = $result['fullname'] ?? $result['username'];

        // ── Audit: successful superadmin login ──
        if ($result['role'] === 'superadmin') {
            try {
                $db   = new Database();
                $conn = $db->connect();
                audit_log($conn, $_SESSION, 'superadmin_login', 'superadmin', $result['admin_id'], $result['username'], 'Superadmin logged in successfully');
            } catch (Exception $e) {}
        }

        $redirect = ($result['role'] === 'superadmin')
            ? "dashboard/superadmin.php"
            : "dashboard/admindashboard.php";

        header("Location: " . $redirect);
        exit;

    } else {
        // ── Audit: failed login attempt ──
        try {
            $db   = new Database();
            $conn = $db->connect();
            audit_log($conn, ['admin_id' => null, 'username' => $username], 'failed_login_attempt', null, null, $username, "Failed login attempt for username: {$username}");
        } catch (Exception $e) {}

        $_SESSION['error']        = "Invalid username or password";
        $_SESSION['old_username'] = $username;
        header("Location: login.php");
        exit;
    }
}

$message = "";
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}

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

   <link rel="stylesheet" href="../design/mainstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php include __DIR__ . '/dashboard/helpers/theme_loader.php'; ?>
</head>
<body>

<div class="login-wrapper">

    <!-- ===== LEFT: Brand Panel ===== -->
    <div class="login-left">

       <div class="logo-box">
    <?php $logo_size = 42; $logo_show_text = true; include __DIR__ . '/dashboard/helpers/ipos_logo.php'; ?>
</div>

        <div class="login-brand">
            <div class="eyebrow">✦ Point of Sale</div>
            <h1>Welcome to <span>iPOS</span></h1>
            <p>
                A modern POS system built for fastfood businesses.
                Manage orders, monitor sales, and control your store in real-time.
            </p>

            <div class="features">
                <span><i class="fa-solid fa-bolt"></i> Real-time Order Tracking</span>
                <span><i class="fa-solid fa-chart-line"></i> Sales Analytics Dashboard</span>
                <span><i class="fa-solid fa-burger"></i> Product &amp; Menu Control</span>
                <span><i class="fa-solid fa-users"></i> Multi-Owner System</span>
            </div>
        </div>

    </div>

    <!-- ===== RIGHT: Form Panel ===== -->
    <div class="login-right">

        <form method="POST" action="login.php">

            <h3>Sign in to your account</h3>
            <p class="form-subtitle">Enter your credentials to continue</p>

            <?php if (!empty($message)): ?>
                <?php
                $is_error   = stripos($message, 'invalid') !== false || stripos($message, 'error') !== false || stripos($message, 'wrong') !== false;
                $msg_class  = $is_error ? 'error' : 'message';
                ?>
                <div class="<?= $msg_class ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <label for="username"><i class="fa-solid fa-user"></i> Username</label>
            <div class="input-icon-wrap">
                <i class="fa-solid fa-user input-icon"></i>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($old_username) ?>"
                    autocomplete="username"
                    required
                >
            </div>

            <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
            <div class="input-icon-wrap">
                <i class="fa-solid fa-lock input-icon"></i>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
                <button type="button" class="toggle-pw" onclick="togglePw()">
                    <i class="fa-solid fa-eye" id="pwEyeIcon"></i>
                </button>
            </div>

            <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> Login</button>

            <a href="registration.php?clear_old=1">Don't have an account? Create one</a>

        </form>

    </div>

</div><!-- /login-wrapper -->

<style>
.input-icon-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.input-icon-wrap input {
    width: 100%;
    padding-left: 40px !important;
    padding-right: 44px !important;
    margin-bottom: 16px;
}
.input-icon {
    position: absolute;
    left: 13px;
    bottom: 16px; /* matches input margin-bottom */
    height: calc(100% - 16px);
    display: flex;
    align-items: center;
    color: var(--accent-dark);
    font-size: 13px;
    pointer-events: none;
    z-index: 1;
}
.toggle-pw {
    position: absolute;
    right: 12px;
    bottom: 16px; /* matches input margin-bottom */
    height: calc(100% - 16px);
    display: flex;
    align-items: center;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 13px;
    padding: 0;
    line-height: 1;
    z-index: 1;
    box-shadow: none !important;
    width: auto !important;
    margin: 0 !important;
}
.toggle-pw:hover {
    color: var(--accent-dark);
    box-shadow: none !important;
}
</style>

<script>
function togglePw() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('pwEyeIcon');
    if (input.type === 'password') {
        input.type     = 'text';
        icon.className = 'fa-solid fa-eye-slash';
    } else {
        input.type     = 'password';
        icon.className = 'fa-solid fa-eye';
    }
}
</script>

</body>
</html>