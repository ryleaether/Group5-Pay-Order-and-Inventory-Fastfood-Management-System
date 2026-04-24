<?php
session_start();
require_once __DIR__ . "/validation.php";

$val = new Validation();
$message = "";
$messageType = "";

/* =========================
   HANDLE FORM SUBMIT
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    /* CONFIRM PASSWORD CHECK (before hitting Validation class) */
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $message = "Passwords do not match!";
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
        $message = "Admin registered successfully!";
        $messageType = "success";

        unset($_SESSION['old']);

    } else {
        $message = $result;
        $messageType = "error";

        $_SESSION['old'] = [
            'fullname'      => $_POST['fullname'],
            'fastfood_name' => $_POST['fastfood_name'],
            'email'         => $_POST['email'],
            'username'      => $_POST['username']
        ];
    }

    }
    } // end confirm_password check
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Register - iPOS</title>
    <link rel="stylesheet" href="../design/mainstyle.css">
</head>

<body>

<form method="POST">

    <!-- 🔥 TOP MESSAGE (SUCCESS / ERROR) -->
    <?php if (!empty($message)): ?>
        <div class="<?= $messageType ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- BRAND -->
    <div class="brand">
        <h1>iPOS</h1>
        <p>I Pay, I Order, I Serve</p>
    </div>

    <!-- TITLE -->
    <div class="form-title">
        <h2>Create Admin Account</h2>
        <span>Fastfood Owner Registration</span>
    </div>

    <!-- INPUTS -->
    <input type="text" name="fullname" placeholder="Full Name"
        value="<?= $_SESSION['old']['fullname'] ?? '' ?>" required>

    <input type="text" name="fastfood_name" placeholder="Fast Food Name"
        value="<?= $_SESSION['old']['fastfood_name'] ?? '' ?>" required>

    <input type="email" name="email" placeholder="Email"
        value="<?= $_SESSION['old']['email'] ?? '' ?>" required>

    <input type="text" name="username" placeholder="Username"
        value="<?= $_SESSION['old']['username'] ?? '' ?>" required>

    <input type="password" name="password" placeholder="Password" required>

    <input type="password" name="confirm_password" placeholder="Confirm Password" required>

    <button type="submit">Create Account</button>

    <a href="login.php">Already have an account?</a>

</form>

</body>
</html>