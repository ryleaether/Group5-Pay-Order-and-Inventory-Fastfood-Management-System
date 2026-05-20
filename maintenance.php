<?php
/**
 * maintenance.php
 * Shown to owners/staff when maintenance mode is ON.
 * Superadmin can still log in and access the system.
 */
require_once __DIR__ . '/config/database.php';

$message  = 'We are currently performing scheduled maintenance. We will be back shortly. Thank you for your patience.';
$end_time = null;

try {
    $db   = new Database();
    $conn = $db->connect();
    $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('maintenance_message','maintenance_end_time')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'maintenance_message' && !empty($row['setting_value'])) {
            $message = $row['setting_value'];
        }
        if ($row['setting_key'] === 'maintenance_end_time') {
            $end_time = $row['setting_value'];
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Under Maintenance – iPOS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #2D0B22 0%, #1a0614 50%, #2D0B22 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        overflow: hidden;
    }

    /* Animated background circles */
    body::before, body::after {
        content: '';
        position: fixed;
        border-radius: 50%;
        opacity: 0.07;
        animation: float 8s ease-in-out infinite;
    }
    body::before {
        width: 600px; height: 600px;
        background: #9B2C52;
        top: -200px; left: -200px;
    }
    body::after {
        width: 400px; height: 400px;
        background: #9B2C52;
        bottom: -100px; right: -100px;
        animation-delay: 4s;
    }
    @keyframes float {
        0%, 100% { transform: translateY(0) scale(1); }
        50%       { transform: translateY(-30px) scale(1.05); }
    }

    .card {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 24px;
        padding: 52px 44px;
        max-width: 520px;
        width: 100%;
        text-align: center;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        box-shadow: 0 40px 80px rgba(0,0,0,0.5);
        position: relative;
        z-index: 1;
    }

    .gear-wrap {
        width: 90px; height: 90px;
        background: linear-gradient(135deg, #9B2C52, #c23b6a);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 28px;
        box-shadow: 0 0 40px rgba(155,44,82,0.5);
        animation: spin-slow 6s linear infinite;
    }
    @keyframes spin-slow {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }
    .gear-icon {
        width: 44px; height: 44px;
        fill: #fff;
        animation: spin-slow 6s linear infinite reverse;
    }

    h1 {
        font-size: 26px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 12px;
        letter-spacing: -0.3px;
    }

    .msg {
        font-size: 14.5px;
        color: rgba(255,255,255,0.65);
        line-height: 1.7;
        margin-bottom: 28px;
    }

    .eta-box {
        background: rgba(155,44,82,0.15);
        border: 1px solid rgba(155,44,82,0.35);
        border-radius: 12px;
        padding: 14px 20px;
        margin-bottom: 28px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .eta-box .clock-icon { font-size: 18px; flex-shrink: 0; }
    .eta-box .eta-text   { font-size: 13px; color: rgba(255,255,255,0.75); text-align: left; }
    .eta-box .eta-time   { font-size: 15px; font-weight: 600; color: #e87aa0; margin-top: 2px; }

    .divider {
        border: none;
        border-top: 1px solid rgba(255,255,255,0.08);
        margin: 24px 0;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 13.5px;
        color: rgba(255,255,255,0.45);
        text-decoration: none;
        transition: color 0.2s;
    }
    .back-link:hover { color: rgba(255,255,255,0.8); }

    .brand {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 12px;
        color: rgba(255,255,255,0.2);
        letter-spacing: 0.8px;
        z-index: 10;
    }
</style>
</head>
<body>

<div class="card">
    <div class="gear-wrap">
        <svg class="gear-icon" viewBox="0 0 512 512">
            <path d="M495.9 166.6c3.2 8.7 .5 18.4-6.4 24.6l-43.3 39.4c1.1 8.3 1.7 16.8 1.7 25.4s-.6 17.1-1.7 25.4l43.3 39.4c6.9 6.2 9.6 15.9 6.4 24.6c-4.4 11.9-9.7 23.3-15.8 34.3l-4.7 8.1c-6.6 11-14 21.4-22.1 31.2c-5.9 7.2-15.7 9.6-24.5 6.8l-55.7-17.7c-13.4 10.3-28.2 18.9-44 25.4l-12.5 57.1c-2 9.1-9 16.3-18.2 17.8c-13.8 2.3-28 3.5-42.5 3.5s-28.7-1.2-42.5-3.5c-9.2-1.5-16.2-8.7-18.2-17.8l-12.5-57.1c-15.8-6.5-30.6-15.1-44-25.4L83.1 425.9c-8.8 2.8-18.6 .3-24.5-6.8c-8.1-9.8-15.5-20.2-22.1-31.2l-4.7-8.1c-6.1-11-11.4-22.4-15.8-34.3c-3.2-8.7-.5-18.4 6.4-24.6l43.3-39.4C64.6 273.1 64 264.6 64 256s.6-17.1 1.7-25.4L22.4 191.2c-6.9-6.2-9.6-15.9-6.4-24.6c4.4-11.9 9.7-23.3 15.8-34.3l4.7-8.1c6.6-11 14-21.4 22.1-31.2c5.9-7.2 15.7-9.6 24.5-6.8l55.7 17.7c13.4-10.3 28.2-18.9 44-25.4l12.5-57.1c2-9.1 9-16.3 18.2-17.8C227.3 1.2 241.5 0 256 0s28.7 1.2 42.5 3.5c9.2 1.5 16.2 8.7 18.2 17.8l12.5 57.1c15.8 6.5 30.6 15.1 44 25.4l55.7-17.7c8.8-2.8 18.6-.3 24.5 6.8c8.1 9.8 15.5 20.2 22.1 31.2l4.7 8.1c6.1 11 11.4 22.4 15.8 34.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z"/>
        </svg>
    </div>

    <h1>Under Maintenance</h1>
    <p class="msg"><?= htmlspecialchars($message) ?></p>

    <?php if (!empty($end_time)): ?>
    <div class="eta-box">
        <span class="clock-icon">🕐</span>
        <div>
            <div class="eta-text">Estimated back online</div>
            <div class="eta-time"><?= htmlspecialchars(date('F j, Y – g:i A', strtotime($end_time))) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <hr class="divider">

    <a href="login.php?superadmin=1" class="back-link">
        <svg width="14" height="14" viewBox="0 0 448 512" fill="currentColor">
            <path d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L109.2 288 416 288c17.7 0 32-14.3 32-32s-14.3-32-32-32l-306.7 0 105.4-105.4c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-160 160z"/>
        </svg>
        Back to Login
    </a>
</div>

<div class="brand">iPOS SYSTEM</div>

</body>
</html>