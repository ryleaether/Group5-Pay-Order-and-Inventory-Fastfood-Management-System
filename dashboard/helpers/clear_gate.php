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

header("Location: ../" . $redirect);
exit;