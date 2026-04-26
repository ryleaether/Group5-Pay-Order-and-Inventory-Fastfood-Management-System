<?php
session_start();
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$val = new Validation();

/* CHECK IF ACCOUNT STILL EXISTS */
if (!$val->adminExists($_SESSION['admin_id'])) {
    // Account deleted, show message
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Account Deleted</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; }
            .modal-content { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
            button { padding: 10px 20px; background: #ff0000; color: white; border: none; border-radius: 5px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="modal">
            <div class="modal-content">
                <h2>Your account has been deleted</h2>
                <p>You will be redirected to the login page.</p>
                <button onclick="redirectToLogin()">OK</button>
            </div>
        </div>
        <script>
            function redirectToLogin() {
                window.location.href = "../login.php";
            }
        </script>
    </body>
    </html>';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../design/mainstyle.css">
</head>
<body>

<h1>Welcome to Menu</h1>

<!-- display menu items here -->

</body>
</html>