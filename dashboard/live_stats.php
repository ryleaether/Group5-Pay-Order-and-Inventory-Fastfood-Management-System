<?php
require_once "validation.php";

$val = new Validation();

$data = [
    "admins" => $val->countAdmins(),
    "active_devices" => $val->countActiveSessions()
];

echo json_encode($data);