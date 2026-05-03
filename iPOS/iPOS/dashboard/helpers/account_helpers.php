<?php
session_start();
require_once __DIR__ . "/../../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db       = new Database();
$conn     = $db->connect();
$admin_id = $_SESSION['admin_id'];
$action   = $_GET['action'] ?? '';

// ─────────────────────────────────────────────
// UPDATE A SINGLE PROFILE FIELD
// ─────────────────────────────────────────────
if ($action === 'update_field' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed = ['fullname', 'username', 'email', 'fastfood_name'];
    $field   = $_POST['field'] ?? '';
    $value   = trim($_POST['value'] ?? '');

    if (!in_array($field, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid field']);
        exit;
    }
    if ($value === '') {
        echo json_encode(['success' => false, 'message' => 'Value cannot be empty']);
        exit;
    }

    // Unique-check for username
    if ($field === 'username') {
        $chk = $conn->prepare("SELECT admin_id FROM admins WHERE username = :u AND admin_id != :id");
        $chk->execute([':u' => $value, ':id' => $admin_id]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username already taken']);
            exit;
        }
    }
    // Unique-check for email
    if ($field === 'email') {
        $chk = $conn->prepare("SELECT admin_id FROM admins WHERE email = :e AND admin_id != :id");
        $chk->execute([':e' => $value, ':id' => $admin_id]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already in use']);
            exit;
        }
    }

    $col_map = [
        'fullname'     => 'fullname',
        'username'     => 'username',
        'email'        => 'email',
        'fastfood_name'=> 'fastfood_name',
    ];
    $col = $col_map[$field];
    $stmt = $conn->prepare("UPDATE admins SET {$col} = :v WHERE admin_id = :id");
    $stmt->execute([':v' => $value, ':id' => $admin_id]);

    // Update session for fastfood_name
    if ($field === 'fastfood_name') {
        $_SESSION['fastfood_name'] = $value;
    }

    echo json_encode(['success' => true]);
    exit;
}

// ─────────────────────────────────────────────
// CHANGE PASSWORD
// ─────────────────────────────────────────────
if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $newPwd  = $_POST['new_password'] ?? '';

    if (empty($current) || empty($newPwd)) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        exit;
    }
    if (strlen($newPwd) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }

    // Fetch current hash
    $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = :id");
    $stmt->execute([':id' => $admin_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($current, $row['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    $newHash = password_hash($newPwd, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE admins SET password = :p WHERE admin_id = :id");
    $upd->execute([':p' => $newHash, ':id' => $admin_id]);

    echo json_encode(['success' => true]);
    exit;
}

// ─────────────────────────────────────────────
// CHANGE DASHBOARD PIN
// ─────────────────────────────────────────────
if ($action === 'change_pin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_pin = trim($_POST['current_pin'] ?? '');
    $new_pin     = trim($_POST['new_pin']     ?? '');

    if (!preg_match('/^\d{4}$/', $new_pin)) {
        echo json_encode(['success' => false, 'message' => 'PIN must be exactly 4 digits']);
        exit;
    }

    // Fetch current stored hash
    $chk = $conn->prepare("SELECT dashboard_pin FROM admins WHERE admin_id = :id");
    $chk->execute([':id' => $admin_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    // If a PIN is set, verify the current one
    if (!empty($row['dashboard_pin'])) {
        if (!password_verify($current_pin, $row['dashboard_pin'])) {
            echo json_encode(['success' => false, 'message' => 'Current PIN is incorrect']);
            exit;
        }
    }

    // Save new PIN
    $hashed = password_hash($new_pin, PASSWORD_BCRYPT);
    $upd = $conn->prepare("UPDATE admins SET dashboard_pin = :pin WHERE admin_id = :id");
    if ($upd->execute([':pin' => $hashed, ':id' => $admin_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save PIN']);
    }
    exit;
}

// ─────────────────────────────────────────────
// SAVE COLOR THEME TO DATABASE
// ─────────────────────────────────────────────
if ($action === 'save_theme' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    // Sanitize — only accept valid hex colors
    $allowed_keys = ['sidebarBg','accent','accentDark','bodyBg','text','accentLight','borderColor','textSec'];
    $clean = [];
    foreach ($allowed_keys as $k) {
        if (isset($data[$k]) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $data[$k])) {
            $clean[$k] = $data[$k];
        }
    }
    $json = json_encode($clean);

    // Try update theme_data column
    try {
        $upd = $conn->prepare("UPDATE admins SET theme_data = :td WHERE admin_id = :id");
        $upd->execute([':td' => $json, ':id' => $admin_id]);
    } catch (Exception $e) {
        // Column may not exist yet — add it
        try {
            $conn->exec("ALTER TABLE admins ADD COLUMN theme_data TEXT NULL");
            $upd = $conn->prepare("UPDATE admins SET theme_data = :td WHERE admin_id = :id");
            $upd->execute([':td' => $json, ':id' => $admin_id]);
        } catch (Exception $e2) {
            echo json_encode(['success' => false, 'message' => 'Could not save theme: ' . $e2->getMessage()]);
            exit;
        }
    }

    // Bust session theme cache so next page load picks up new colors immediately
    $cacheKey = 'ipos_theme_' . $admin_id;
    $_SESSION[$cacheKey] = $clean;

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
