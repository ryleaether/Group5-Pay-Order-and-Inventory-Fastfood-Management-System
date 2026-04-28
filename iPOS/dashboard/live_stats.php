<?php
require_once __DIR__ . "/../validation.php";

$val = new Validation();

$data = [
    "admins" => $val->countAdmins(),
    "active_devices" => $val->countActiveSessions()
];

echo json_encode($data);