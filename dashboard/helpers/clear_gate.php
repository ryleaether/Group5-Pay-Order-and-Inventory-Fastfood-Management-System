<?php
session_start();
$gate     = $_GET['gate'] ?? '';
$redirect = $_GET['redirect'] ?? 'admindashboard.php';

$map = [
    'account' => 'account_pin_unlocked_at',
    'staff'   => 'staff_gate_unlocked',
    'kitchen' => 'kitchen_pin_unlocked_at',
];

if (isset($map[$gate])) {
    unset($_SESSION[$map[$gate]]);
}

$redirect = basename($redirect);

// If going back to admin dashboard, flag it to show the PIN prompt
$query = ($redirect === 'admindashboard.php') ? '?require_pin=1' : '';

header("Location: ../" . $redirect . $query);
exit;