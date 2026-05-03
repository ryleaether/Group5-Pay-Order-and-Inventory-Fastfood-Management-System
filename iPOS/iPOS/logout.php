<?php
require_once __DIR__ . "/config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();
$conn = $db->connect();

/* Get session + admin info BEFORE destroying */
$session_id = session_id();
$admin_id = $_SESSION['admin_id'] ?? null;

/* Mark session as inactive in DB */
if ($admin_id) {
    $sql = "UPDATE admin_sessions 
            SET is_active = 0 
            WHERE session_id = :session_id 
            AND admin_id = :admin_id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":session_id", $session_id);
    $stmt->bindParam(":admin_id", $admin_id);
    $stmt->execute();
}

/* Destroy PHP session */
$_SESSION = [];

session_destroy();

/* Redirect to login page */
header("Location: login.php");
exit();