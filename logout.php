<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/audit_helper.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent any page from being cached / restored from bfcache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$db = new Database();
$conn = $db->connect();

/* Get session + admin info BEFORE destroying */
$session_id = session_id();
$admin_id   = $_SESSION['admin_id'] ?? null;

/* ── Audit: superadmin logout ── */
if (!empty($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
    audit_log($conn, $_SESSION, 'superadmin_logout', 'superadmin', $admin_id, $_SESSION['username'] ?? null, 'Superadmin logged out');
}

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

/* Wipe session data */
$_SESSION = [];

/* Explicitly expire the session cookie so the browser removes it */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

/* Redirect — use REPLACE so back-button cannot return to this script */
header("Location: login.php");
exit();