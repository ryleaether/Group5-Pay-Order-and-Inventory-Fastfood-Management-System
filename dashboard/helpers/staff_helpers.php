<?php

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

/**
 * staff_helpers.php — CRUD + Auth API for Staffs
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$db   = new Database();
$conn = $db->connect();

// Ensure staffs table exists with all columns
$conn->exec("CREATE TABLE IF NOT EXISTS staffs (
    staff_id         INT AUTO_INCREMENT PRIMARY KEY,
    admin_id         INT NOT NULL,
    staff_code       VARCHAR(10) NULL,
    fullname         VARCHAR(100) NOT NULL,
    role             ENUM('Cashier','Kitchen') NOT NULL DEFAULT 'Cashier',
    pin              VARCHAR(255) NULL,
    status           ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    shift_start      TIME NULL,
    shift_end        TIME NULL,
    login_fail_count INT NOT NULL DEFAULT 0,
    last_fail_at     TIMESTAMP NULL,
    last_login_at    TIMESTAMP NULL,
    is_online        TINYINT(1) NOT NULL DEFAULT 0,
    employment_type  ENUM('Full-time','Part-time') NOT NULL DEFAULT 'Full-time',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
)");

// Staff sessions log table
$conn->exec("CREATE TABLE IF NOT EXISTS staff_sessions (
    session_id       INT AUTO_INCREMENT PRIMARY KEY,
    staff_id         INT NOT NULL,
    admin_id         INT NOT NULL,
    login_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_at        TIMESTAMP NULL,
    duration_minutes INT NULL,
    FOREIGN KEY (staff_id) REFERENCES staffs(staff_id) ON DELETE CASCADE
)");

// Silently add/migrate columns for existing installations
$migrations = [
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS staff_code VARCHAR(10) NULL",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS shift_start TIME NULL",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS shift_end TIME NULL",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS login_fail_count INT NOT NULL DEFAULT 0",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS last_fail_at TIMESTAMP NULL",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS is_online TINYINT(1) NOT NULL DEFAULT 0",
];
foreach ($migrations as $sql) {
    try { $conn->exec($sql); } catch (Exception $e) {}
}

// Add employment_type safely via information_schema (works on all MySQL versions)
try {
    $chkEt = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staffs' AND COLUMN_NAME = 'employment_type'");
    if ((int)$chkEt->fetchColumn() === 0) {
        $conn->exec("ALTER TABLE staffs ADD COLUMN employment_type ENUM('Full-time','Part-time') NOT NULL DEFAULT 'Full-time'");
    }
} catch (Exception $e) {}

// Fallback: add is_online via information_schema check
try {
    $chk = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staffs' AND COLUMN_NAME = 'is_online'");
    if ((int)$chk->fetchColumn() === 0) {
        $conn->exec("ALTER TABLE staffs ADD COLUMN is_online TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Exception $e) {}

// Fallback: add staff_code via information_schema check
try {
    $chkSc = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staffs' AND COLUMN_NAME = 'staff_code'");
    if ((int)$chkSc->fetchColumn() === 0) {
        $conn->exec("ALTER TABLE staffs ADD COLUMN staff_code VARCHAR(10) NULL");
    }
} catch (Exception $e) {}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Staff logout (no admin session needed) ─────────────────────────────────
if ($action === 'staff_logout') {
    if (!empty($_SESSION['staff_id'])) {
        $conn->prepare("UPDATE staffs SET is_online=0 WHERE staff_id=:id")
             ->execute([':id' => (int)$_SESSION['staff_id']]);
        if (!empty($_SESSION['staff_session_log_id'])) {
            $conn->prepare("UPDATE staff_sessions SET logout_at=NOW(), duration_minutes=TIMESTAMPDIFF(MINUTE,login_at,NOW()) WHERE session_id=:sid")
                 ->execute([':sid' => (int)$_SESSION['staff_session_log_id']]);
        }
    }
    unset(
        $_SESSION['staff_id'],
        $_SESSION['staff_name'],
        $_SESSION['staff_role'],
        $_SESSION['staff_admin'],
        $_SESSION['staff_login_at'],
        $_SESSION['staff_session_log_id'],
        $_SESSION['staff_gate_unlocked'],
        $_SESSION['staff_login_active']
    );
    $_SESSION['staff_reentry'] = time();
    header('Content-Type: text/html');
    header('Location: ../staff_login.php');
    exit;
}

// ── Staff login (no admin session needed) ──────────────────────────────────
if ($action === 'staff_login') {
    $admin_id   = (int)($_POST['admin_id'] ?? 0);
    $staff_code = strtoupper(trim($_POST['staff_code'] ?? ''));
    $pin        = trim($_POST['pin'] ?? '');
    $role       = trim($_POST['role'] ?? '');

    if (!$admin_id || !$staff_code || !$pin || !$role) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM staffs WHERE admin_id=:aid AND staff_code=:code AND role=:role AND status='Active' LIMIT 1");
    $stmt->execute([':aid' => $admin_id, ':code' => $staff_code, ':role' => $role]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Staff not found or inactive.']);
        exit;
    }

    if (!$staff['pin'] || !password_verify($pin, $staff['pin'])) {
        $conn->prepare("UPDATE staffs SET login_fail_count=login_fail_count+1, last_fail_at=NOW() WHERE staff_id=:id")
             ->execute([':id' => $staff['staff_id']]);
        echo json_encode(['success' => false, 'message' => 'Incorrect PIN.']);
        exit;
    }

    // Block login if outside shift hours
    if (!empty($staff['shift_start']) && !empty($staff['shift_end'])) {
        $now        = new DateTime('now');
        $shiftStart = new DateTime(date('Y-m-d') . ' ' . $staff['shift_start']);
        $shiftEnd   = new DateTime(date('Y-m-d') . ' ' . $staff['shift_end']);

        // Overnight shift: end time is earlier than start (e.g. 11:20 PM – 1:30 AM)
        if ($shiftEnd <= $shiftStart) {
            $shiftStartYesterday = clone $shiftStart;
            $shiftStartYesterday->modify('-1 day');
            $inWindowA = ($now >= $shiftStartYesterday && $now < $shiftEnd);

            $shiftEndTomorrow = clone $shiftEnd;
            $shiftEndTomorrow->modify('+1 day');
            $inWindowB = ($now >= $shiftStart && $now < $shiftEndTomorrow);

            $blocked = !($inWindowA || $inWindowB);
        } else {
            $blocked = !($now >= $shiftStart && $now < $shiftEnd);
        }

        if ($blocked) {
            $startLabel = date('g:i A', strtotime($staff['shift_start']));
            $endLabel   = date('g:i A', strtotime($staff['shift_end']));
            echo json_encode([
                'success' => false,
                'message' => "Your shift is from {$startLabel} to {$endLabel}. You cannot login outside your shift hours."
            ]);
            exit;
        }
    }

    // Calculate late minutes
    $lateMinutes = 0;
    if (!empty($staff['shift_start'])) {
        $now        = new DateTime('now');
        $nowMins    = (int)$now->format('H') * 60 + (int)$now->format('i');
        $startParts = explode(':', $staff['shift_start']);
        $startMins  = (int)$startParts[0] * 60 + (int)$startParts[1];
        if ($nowMins > $startMins) {
            $lateMinutes = $nowMins - $startMins;
        }
    }

    // Success — reset fail count, mark online
    $conn->prepare("UPDATE staffs SET login_fail_count=0, last_fail_at=NULL, last_login_at=NOW(), is_online=1 WHERE staff_id=:id")
         ->execute([':id' => $staff['staff_id']]);

    // Ensure late_minutes column exists
    try {
        $chkLate = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_sessions' AND COLUMN_NAME = 'late_minutes'");
        if ((int)$chkLate->fetchColumn() === 0) {
            $conn->exec("ALTER TABLE staff_sessions ADD COLUMN late_minutes INT NOT NULL DEFAULT 0");
        }
    } catch (Exception $e) {}

    // Log the session
    try {
        $conn->prepare("INSERT INTO staff_sessions (staff_id, admin_id, login_at, late_minutes) VALUES (:sid, :aid, NOW(), :late)")
             ->execute([':sid' => $staff['staff_id'], ':aid' => $staff['admin_id'], ':late' => $lateMinutes]);
    } catch (Exception $e) {
        $conn->prepare("INSERT INTO staff_sessions (staff_id, admin_id, login_at) VALUES (:sid, :aid, NOW())")
             ->execute([':sid' => $staff['staff_id'], ':aid' => $staff['admin_id']]);
    }
    $sessionLogId = $conn->lastInsertId();

    $_SESSION['staff_id']             = $staff['staff_id'];
    $_SESSION['staff_name']           = $staff['fullname'];
    $_SESSION['staff_role']           = $staff['role'];
    $_SESSION['staff_admin']          = $staff['admin_id'];
    $_SESSION['staff_login_at']       = time();
    $_SESSION['staff_session_log_id'] = $sessionLogId;

    $redirect = ($staff['role'] === 'Kitchen') ? 'kitchen_dashboard.php' : 'userdashboard.php';
    echo json_encode(['success' => true, 'redirect' => $redirect, 'role' => $staff['role']]);
    exit;
}

// ── Verify staff's own PIN (for self-logout) ─────────────────────────────
if ($action === 'verify_own_pin') {
    $pin = trim($_POST['pin'] ?? '');
    if (empty($_SESSION['staff_id']) || !$pin) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }
    $stmt = $conn->prepare("SELECT pin FROM staffs WHERE staff_id = :id LIMIT 1");
    $stmt->execute([':id' => (int)$_SESSION['staff_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['pin'] && password_verify($pin, $row['pin'])) {
        $conn->prepare("UPDATE staffs SET is_online=0 WHERE staff_id=:id")
             ->execute([':id' => (int)$_SESSION['staff_id']]);
        if (!empty($_SESSION['staff_session_log_id'])) {
            $conn->prepare("UPDATE staff_sessions SET logout_at=NOW(), duration_minutes=TIMESTAMPDIFF(MINUTE,login_at,NOW()) WHERE session_id=:sid")
                 ->execute([':sid' => (int)$_SESSION['staff_session_log_id']]);
        }
        // Safety net: close any other open sessions for this staff
        $conn->prepare("UPDATE staff_sessions SET logout_at=NOW(), duration_minutes=TIMESTAMPDIFF(MINUTE,login_at,NOW()) WHERE staff_id=:sid AND logout_at IS NULL")
             ->execute([':sid' => (int)$_SESSION['staff_id']]);

        unset(
            $_SESSION['staff_id'], $_SESSION['staff_name'], $_SESSION['staff_role'],
            $_SESSION['staff_admin'], $_SESSION['staff_login_at'], $_SESSION['staff_session_log_id']
        );
        $_SESSION['staff_reentry'] = time();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect PIN.']);
    }
    exit;
}

// ── Admin-only actions below ───────────────────────────────────────────────
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$admin_id = (int)$_SESSION['admin_id'];

function normalize_staff_name($name) {
    return preg_replace('/\s+/', ' ', trim($name));
}

function staff_name_exists($conn, $admin_id, $fullname, $exclude_id = 0) {
    $sql = "SELECT staff_id FROM staffs WHERE admin_id=:aid AND LOWER(TRIM(fullname)) = LOWER(:name)";
    $params = [':aid' => $admin_id, ':name' => normalize_staff_name($fullname)];
    if ($exclude_id > 0) {
        $sql .= " AND staff_id<>:exclude_id";
        $params[':exclude_id'] = $exclude_id;
    }
    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function staff_code_exists($conn, $admin_id, $staff_code, $exclude_id = 0) {
    $sql = "SELECT staff_id FROM staffs WHERE admin_id=:aid AND staff_code=:code";
    $params = [':aid' => $admin_id, ':code' => strtoupper(trim($staff_code))];
    if ($exclude_id > 0) {
        $sql .= " AND staff_id<>:exclude_id";
        $params[':exclude_id'] = $exclude_id;
    }
    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function is_valid_shift_time($time) {
    return is_string($time) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time);
}

switch ($action) {

    case 'list':
        $search = trim($_GET['search'] ?? '');
        $role   = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $sql    = "SELECT staff_id, staff_code, fullname, role, status, shift_start, shift_end,
                          login_fail_count, last_fail_at, last_login_at, is_online, employment_type, created_at
                   FROM staffs WHERE admin_id=:aid";
        $params = [':aid' => $admin_id];
        if ($search !== '') { $sql .= " AND fullname LIKE :s"; $params[':s'] = '%' . $search . '%'; }
        if ($role   !== '') { $sql .= " AND role=:role"; $params[':role'] = $role; }
        if ($status !== '') { $sql .= " AND status=:status"; $params[':status'] = $status; }
        $sql .= " ORDER BY created_at DESC";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'staffs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            // Retry without columns that may not exist yet (very old installs)
            $sql2 = str_replace(', is_online,', ',', $sql);
            $sql2 = str_replace(', employment_type,', ',', $sql2);
            $sql2 = str_replace('staff_code, ', '', $sql2);
            $stmt2 = $conn->prepare($sql2);
            $stmt2->execute($params);
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['is_online']       = 0;
                $r['employment_type'] = 'Full-time';
                $r['staff_code']      = null;
            }
            echo json_encode(['success' => true, 'staffs' => $rows]);
        }
        break;

    case 'add':
        $staff_code      = strtoupper(trim($_POST['staff_code'] ?? ''));
        $fullname        = trim($_POST['fullname'] ?? '');
        $role            = trim($_POST['role'] ?? 'Cashier');
        $pin             = trim($_POST['pin'] ?? '');
        $status          = $_POST['status'] ?? 'Active';
        $shift_start     = $_POST['shift_start'] ?? null;
        $shift_end       = $_POST['shift_end'] ?? null;
        $employment_type = $_POST['employment_type'] ?? 'Full-time';
        $fullname        = normalize_staff_name($fullname);

        if ($staff_code === '') {
            echo json_encode(['success' => false, 'message' => 'Staff ID is required.']); exit;
        }
        if (!preg_match('/^[A-Z0-9]{1,10}$/', $staff_code)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID must be letters and numbers only, max 10 characters.']); exit;
        }
        if (staff_code_exists($conn, $admin_id, $staff_code)) {
            echo json_encode(['success' => false, 'message' => 'This Staff ID is already in use.']); exit;
        }
        if ($fullname === '') {
            echo json_encode(['success' => false, 'message' => 'Name is required.']); exit;
        }
        if (staff_name_exists($conn, $admin_id, $fullname)) {
            echo json_encode(['success' => false, 'message' => 'A staff member with this name already exists.']); exit;
        }
        if (!is_valid_shift_time($shift_start) || !is_valid_shift_time($shift_end)) {
            echo json_encode(['success' => false, 'message' => 'Shift start and end are required.']); exit;
        }
        if ($shift_start === $shift_end) {
            echo json_encode(['success' => false, 'message' => 'Shift start and end cannot be the same.']); exit;
        }
        if ($pin === '') {
            echo json_encode(['success' => false, 'message' => 'PIN is required for staff login.']); exit;
        }
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            echo json_encode(['success' => false, 'message' => 'PIN must be exactly 4 digits.']); exit;
        }

        $hashed = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO staffs (admin_id, staff_code, fullname, role, pin, status, shift_start, shift_end, employment_type)
                                VALUES (:aid, :code, :name, :role, :pin, :status, :ss, :se, :et)");
        $stmt->execute([
            ':aid'    => $admin_id,
            ':code'   => $staff_code,
            ':name'   => $fullname,
            ':role'   => $role,
            ':pin'    => $hashed,
            ':status' => $status,
            ':ss'     => $shift_start ?: null,
            ':se'     => $shift_end ?: null,
            ':et'     => $employment_type,
        ]);
        echo json_encode(['success' => true, 'message' => 'Staff added successfully.', 'staff_id' => $conn->lastInsertId()]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT staff_id, staff_code, fullname, role, status, shift_start, shift_end
                                FROM staffs WHERE staff_id=:id AND admin_id=:aid");
        $stmt->execute([':id' => $id, ':aid' => $admin_id]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$staff) { echo json_encode(['success' => false, 'message' => 'Not found.']); exit; }
        echo json_encode(['success' => true, 'staff' => $staff]);
        break;

    case 'edit':
        $id              = (int)($_POST['staff_id'] ?? 0);
        $staff_code      = strtoupper(trim($_POST['staff_code'] ?? ''));
        $fullname        = trim($_POST['fullname'] ?? '');
        $role            = trim($_POST['role'] ?? 'Cashier');
        $pin             = trim($_POST['pin'] ?? '');
        $status          = $_POST['status'] ?? 'Active';
        $shift_start     = $_POST['shift_start'] ?? null;
        $shift_end       = $_POST['shift_end'] ?? null;
        $employment_type = $_POST['employment_type'] ?? 'Full-time';
        $fullname        = normalize_staff_name($fullname);

        if (!$id || !$fullname) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']); exit;
        }

        // Verify staff belongs to this admin
        $chk = $conn->prepare("SELECT staff_id FROM staffs WHERE staff_id=:id AND admin_id=:aid");
        $chk->execute([':id' => $id, ':aid' => $admin_id]);
        if (!$chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Not found.']); exit;
        }

        if ($staff_code === '') {
            echo json_encode(['success' => false, 'message' => 'Staff ID is required.']); exit;
        }
        if (!preg_match('/^[A-Z0-9]{1,10}$/', $staff_code)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID must be letters and numbers only, max 10 characters.']); exit;
        }
        if (staff_code_exists($conn, $admin_id, $staff_code, $id)) {
            echo json_encode(['success' => false, 'message' => 'This Staff ID is already in use.']); exit;
        }
        if (staff_name_exists($conn, $admin_id, $fullname, $id)) {
            echo json_encode(['success' => false, 'message' => 'A staff member with this name already exists.']); exit;
        }
        if (!is_valid_shift_time($shift_start) || !is_valid_shift_time($shift_end)) {
            echo json_encode(['success' => false, 'message' => 'Shift start and end are required.']); exit;
        }
        if ($shift_start === $shift_end) {
            echo json_encode(['success' => false, 'message' => 'Shift start and end cannot be the same.']); exit;
        }

        if ($pin !== '') {
            if (strlen($pin) !== 4 || !ctype_digit($pin)) {
                echo json_encode(['success' => false, 'message' => 'PIN must be 4 digits.']); exit;
            }
            $hashed = password_hash($pin, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE staffs SET staff_code=:code, fullname=:n, role=:r, pin=:p, status=:s,
                                shift_start=:ss, shift_end=:se, employment_type=:et
                            WHERE staff_id=:id AND admin_id=:aid")
                 ->execute([
                     ':code' => $staff_code, ':n' => $fullname, ':r' => $role, ':p' => $hashed,
                     ':s' => $status, ':ss' => $shift_start ?: null, ':se' => $shift_end ?: null,
                     ':et' => $employment_type, ':id' => $id, ':aid' => $admin_id,
                 ]);
        } else {
            $conn->prepare("UPDATE staffs SET staff_code=:code, fullname=:n, role=:r, status=:s,
                                shift_start=:ss, shift_end=:se, employment_type=:et
                            WHERE staff_id=:id AND admin_id=:aid")
                 ->execute([
                     ':code' => $staff_code, ':n' => $fullname, ':r' => $role,
                     ':s' => $status, ':ss' => $shift_start ?: null, ':se' => $shift_end ?: null,
                     ':et' => $employment_type, ':id' => $id, ':aid' => $admin_id,
                 ]);
        }
        echo json_encode(['success' => true, 'message' => 'Staff updated.']);
        break;

    case 'delete':
        $id = (int)($_POST['staff_id'] ?? $_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit; }
        $conn->prepare("DELETE FROM staffs WHERE staff_id=:id AND admin_id=:aid")
             ->execute([':id' => $id, ':aid' => $admin_id]);
        echo json_encode(['success' => true, 'message' => 'Staff removed.']);
        break;

    case 'toggle_status':
        $id = (int)($_POST['staff_id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit; }
        $conn->prepare("UPDATE staffs SET status=IF(status='Active','Inactive','Active') WHERE staff_id=:id AND admin_id=:aid")
             ->execute([':id' => $id, ':aid' => $admin_id]);
        $row = $conn->prepare("SELECT status FROM staffs WHERE staff_id=:id");
        $row->execute([':id' => $id]);
        echo json_encode(['success' => true, 'new_status' => $row->fetchColumn()]);
        break;

    case 'get_logs':
        try {
            // Ensure late_minutes column exists
            try {
                $chkLate2 = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_sessions' AND COLUMN_NAME = 'late_minutes'");
                if ((int)$chkLate2->fetchColumn() === 0) {
                    $conn->exec("ALTER TABLE staff_sessions ADD COLUMN late_minutes INT NOT NULL DEFAULT 0");
                }
            } catch (Exception $ex) {}

            $staffId = (int)($_GET['staff_id'] ?? 0);
            $date    = $_GET['date'] ?? '';

            $colCheck = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_sessions' AND COLUMN_NAME = 'late_minutes'");
            $hasLateCol = (int)$colCheck->fetchColumn() > 0;
            $lateCol    = $hasLateCol ? 'ss.late_minutes,' : '0 as late_minutes,';

            $sql = "SELECT ss.session_id, ss.staff_id, ss.login_at, ss.logout_at,
                           ss.duration_minutes, {$lateCol}
                           s.fullname, s.role, s.shift_start, s.shift_end
                    FROM staff_sessions ss
                    JOIN staffs s ON ss.staff_id = s.staff_id
                    WHERE ss.admin_id = :aid";
            $params = [':aid' => $admin_id];
            if ($staffId) { $sql .= " AND ss.staff_id = :sid"; $params[':sid'] = $staffId; }
            if ($date)    { $sql .= " AND DATE(ss.login_at) = :date"; $params[':date'] = $date; }
            $sql .= " ORDER BY ss.login_at DESC LIMIT 200";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
exit;