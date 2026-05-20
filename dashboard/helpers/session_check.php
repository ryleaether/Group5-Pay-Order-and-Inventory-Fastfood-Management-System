<?php
session_start();

// Prevent caching so browsers don't show stale pages from bfcache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

$resp = [
    'admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
    'staff_id' => isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : null,
    'staff_role' => isset($_SESSION['staff_role']) ? $_SESSION['staff_role'] : null,
    'staff_admin' => isset($_SESSION['staff_admin']) ? (int)$_SESSION['staff_admin'] : null,
    'kitchen_pin_unlocked_at' => isset($_SESSION['kitchen_pin_unlocked_at']) ? $_SESSION['kitchen_pin_unlocked_at'] : null,
    'staff_gate_unlocked' => isset($_SESSION['staff_gate_unlocked']) ? $_SESSION['staff_gate_unlocked'] : null,
    'staff_login_active' => isset($_SESSION['staff_login_active']) ? $_SESSION['staff_login_active'] : null,
];

echo json_encode($resp);