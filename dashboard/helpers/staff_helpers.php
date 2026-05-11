<?php
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
    fullname         VARCHAR(100) NOT NULL,
    role             ENUM('Cashier','Kitchen') NOT NULL DEFAULT 'Cashier',
    pin              VARCHAR(255) NULL,
    status           ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    shift_start      TIME NULL,
    shift_end        TIME NULL,
    login_fail_count INT NOT NULL DEFAULT 0,
    last_fail_at     TIMESTAMP NULL,
    last_login_at    TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
)");

// Silently add columns if upgrading
foreach ([
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS shift_start TIME NULL",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS shift_end TIME NULL",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS login_fail_count INT NOT NULL DEFAULT 0",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS last_fail_at TIMESTAMP NULL",
    "ALTER TABLE staffs ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL",
] as $sql) { try { $conn->exec($sql); } catch(Exception $e) {} }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Staff login (no admin session needed) ──────────────────────────────────
if ($action === 'staff_login') {
    $admin_id = (int)($_POST['admin_id'] ?? 0);
    $fullname = trim($_POST['fullname'] ?? '');
    $pin      = trim($_POST['pin'] ?? '');
    $role     = trim($_POST['role'] ?? '');

    if (!$admin_id || !$fullname || !$pin || !$role) {
        echo json_encode(['success'=>false,'message'=>'All fields are required.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM staffs WHERE admin_id=:aid AND fullname=:name AND role=:role AND status='Active' LIMIT 1");
    $stmt->execute([':aid'=>$admin_id,':name'=>$fullname,':role'=>$role]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        // Record fail — find closest match by name+admin for tracking
        $find = $conn->prepare("SELECT staff_id FROM staffs WHERE admin_id=:aid AND fullname=:name LIMIT 1");
        $find->execute([':aid'=>$admin_id,':name'=>$fullname]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $conn->prepare("UPDATE staffs SET login_fail_count=login_fail_count+1, last_fail_at=NOW() WHERE staff_id=:id")->execute([':id'=>$row['staff_id']]);
        }
        echo json_encode(['success'=>false,'message'=>'Staff not found or inactive.']);
        exit;
    }

    if (!$staff['pin'] || !password_verify($pin, $staff['pin'])) {
        $conn->prepare("UPDATE staffs SET login_fail_count=login_fail_count+1, last_fail_at=NOW() WHERE staff_id=:id")->execute([':id'=>$staff['staff_id']]);
        echo json_encode(['success'=>false,'message'=>'Incorrect PIN.']);
        exit;
    }

    // Success — set session and reset fail count
    $conn->prepare("UPDATE staffs SET login_fail_count=0, last_fail_at=NULL, last_login_at=NOW() WHERE staff_id=:id")->execute([':id'=>$staff['staff_id']]);

    $_SESSION['staff_id']    = $staff['staff_id'];
    $_SESSION['staff_name']  = $staff['fullname'];
    $_SESSION['staff_role']  = $staff['role'];
    $_SESSION['staff_admin'] = $staff['admin_id'];
    $_SESSION['staff_login_at'] = time();

    $redirect = ($staff['role'] === 'Kitchen') ? 'kitchen_dashboard.php' : 'userdashboard.php';
    echo json_encode(['success'=>true,'redirect'=>$redirect,'role'=>$staff['role']]);
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
        // Clear staff session, keep admin session intact
        unset($_SESSION['staff_id'], $_SESSION['staff_name'], $_SESSION['staff_role'],
              $_SESSION['staff_admin'], $_SESSION['staff_login_at']);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect PIN.']);
    }
    exit;
}

// ── Admin-only actions below ───────────────────────────────────────────────
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}
$admin_id = (int)$_SESSION['admin_id'];

switch ($action) {

    case 'list':
        $search = trim($_GET['search'] ?? '');
        $role   = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $sql    = "SELECT staff_id, fullname, role, status, shift_start, shift_end, login_fail_count, last_fail_at, last_login_at, created_at FROM staffs WHERE admin_id=:aid";
        $params = [':aid'=>$admin_id];
        if ($search !== '') { $sql .= " AND fullname LIKE :s"; $params[':s']='%'.$search.'%'; }
        if ($role   !== '') { $sql .= " AND role=:role"; $params[':role']=$role; }
        if ($status !== '') { $sql .= " AND status=:status"; $params[':status']=$status; }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql); $stmt->execute($params);
        echo json_encode(['success'=>true,'staffs'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'add':
        $fullname    = trim($_POST['fullname'] ?? '');
        $role        = trim($_POST['role'] ?? 'Cashier');
        $pin         = trim($_POST['pin'] ?? '');
        $status      = $_POST['status'] ?? 'Active';
        $shift_start = $_POST['shift_start'] ?? null;
        $shift_end   = $_POST['shift_end'] ?? null;
        if ($fullname === '') { echo json_encode(['success'=>false,'message'=>'Name is required.']); exit; }
        if ($pin === '') { echo json_encode(['success'=>false,'message'=>'PIN is required for staff login.']); exit; }
        if (strlen($pin) !== 4 || !ctype_digit($pin)) { echo json_encode(['success'=>false,'message'=>'PIN must be exactly 4 digits.']); exit; }
        $hashed = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO staffs (admin_id,fullname,role,pin,status,shift_start,shift_end) VALUES (:aid,:name,:role,:pin,:status,:ss,:se)");
        $stmt->execute([':aid'=>$admin_id,':name'=>$fullname,':role'=>$role,':pin'=>$hashed,':status'=>$status,':ss'=>$shift_start ?: null,':se'=>$shift_end ?: null]);
        echo json_encode(['success'=>true,'message'=>'Staff added successfully.','staff_id'=>$conn->lastInsertId()]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT staff_id,fullname,role,status,shift_start,shift_end FROM staffs WHERE staff_id=:id AND admin_id=:aid");
        $stmt->execute([':id'=>$id,':aid'=>$admin_id]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$staff) { echo json_encode(['success'=>false,'message'=>'Not found.']); exit; }
        echo json_encode(['success'=>true,'staff'=>$staff]);
        break;

    case 'edit':
        $id          = (int)($_POST['staff_id'] ?? 0);
        $fullname    = trim($_POST['fullname'] ?? '');
        $role        = trim($_POST['role'] ?? 'Cashier');
        $pin         = trim($_POST['pin'] ?? '');
        $status      = $_POST['status'] ?? 'Active';
        $shift_start = $_POST['shift_start'] ?? null;
        $shift_end   = $_POST['shift_end'] ?? null;
        if (!$id || !$fullname) { echo json_encode(['success'=>false,'message'=>'Invalid data.']); exit; }
        $chk = $conn->prepare("SELECT staff_id FROM staffs WHERE staff_id=:id AND admin_id=:aid");
        $chk->execute([':id'=>$id,':aid'=>$admin_id]);
        if (!$chk->fetch()) { echo json_encode(['success'=>false,'message'=>'Not found.']); exit; }
        if ($pin !== '') {
            if (strlen($pin) !== 4 || !ctype_digit($pin)) { echo json_encode(['success'=>false,'message'=>'PIN must be 4 digits.']); exit; }
            $hashed = password_hash($pin, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE staffs SET fullname=:n,role=:r,pin=:p,status=:s,shift_start=:ss,shift_end=:se WHERE staff_id=:id AND admin_id=:aid")
                 ->execute([':n'=>$fullname,':r'=>$role,':p'=>$hashed,':s'=>$status,':ss'=>$shift_start ?: null,':se'=>$shift_end ?: null,':id'=>$id,':aid'=>$admin_id]);
        } else {
            $conn->prepare("UPDATE staffs SET fullname=:n,role=:r,status=:s,shift_start=:ss,shift_end=:se WHERE staff_id=:id AND admin_id=:aid")
                 ->execute([':n'=>$fullname,':r'=>$role,':s'=>$status,':ss'=>$shift_start ?: null,':se'=>$shift_end ?: null,':id'=>$id,':aid'=>$admin_id]);
        }
        echo json_encode(['success'=>true,'message'=>'Staff updated.']);
        break;

    case 'delete':
        $id = (int)($_POST['staff_id'] ?? $_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); exit; }
        $conn->prepare("DELETE FROM staffs WHERE staff_id=:id AND admin_id=:aid")->execute([':id'=>$id,':aid'=>$admin_id]);
        echo json_encode(['success'=>true,'message'=>'Staff removed.']);
        break;

    case 'toggle_status':
        $id = (int)($_POST['staff_id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); exit; }
        $conn->prepare("UPDATE staffs SET status=IF(status='Active','Inactive','Active') WHERE staff_id=:id AND admin_id=:aid")->execute([':id'=>$id,':aid'=>$admin_id]);
        $row = $conn->prepare("SELECT status FROM staffs WHERE staff_id=:id"); $row->execute([':id'=>$id]);
        echo json_encode(['success'=>true,'new_status'=>$row->fetchColumn()]);
        break;

    case 'reset_fails':
        $id = (int)($_POST['staff_id'] ?? 0);
        $conn->prepare("UPDATE staffs SET login_fail_count=0,last_fail_at=NULL WHERE staff_id=:id AND admin_id=:aid")->execute([':id'=>$id,':aid'=>$admin_id]);
        echo json_encode(['success'=>true,'message'=>'Login attempts cleared.']);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Invalid action.']);
}
exit;