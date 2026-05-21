<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal: ' . $err['message']]);
    }
});
session_start();
ob_end_clean();
header('Content-Type: application/json');

// FIX: DB role is 'owner' or 'superadmin' — not 'admin'
$isAdmin = isset($_SESSION['admin_id']) && (
    !isset($_SESSION['role']) ||
    in_array($_SESSION['role'], ['owner','admin','superadmin'])
);
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/config/database.php';
$__ah = $root . '/config/audit_helper.php';
if (file_exists($__ah)) { require_once $__ah; }
elseif (!function_exists('audit_log')) { function audit_log() {} }

$db       = new Database();
$conn     = $db->connect();
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$action   = $_POST['action'] ?? $_GET['action'] ?? '';
$type     = $_POST['type']   ?? $_GET['type']   ?? '';

ensureTables($conn);
autoPurge($conn);

// ══════════════════════════════════════════════════════════
//  SOFT DELETE
// ══════════════════════════════════════════════════════════
if ($action === 'soft_delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id || !in_array($type, ['item','staff','inactive_staff'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
    }

    try {
        if ($type === 'item') {
            $row = $conn->prepare("SELECT * FROM menu_items WHERE menu_item_id=:id AND admin_id=:aid");
            $row->execute([':id'=>$id,':aid'=>$admin_id]);
            $row = $row->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Item not found.']); exit; }

            $conn->prepare("INSERT INTO deleted_menu_items (item_id, admin_id, item_name, description, price, stock_quantity, category, is_available, image_url, original_created_at, deleted_by) VALUES (:item_id,:admin_id,:item_name,:description,:price,:stock,:category,:avail,:img,:created,:by)")
                ->execute([':item_id'=>$row['menu_item_id'],':admin_id'=>$row['admin_id'],':item_name'=>$row['item_name'],':description'=>$row['description']??null,':price'=>$row['price'],':stock'=>$row['stock_quantity'],':category'=>$row['category']??null,':avail'=>$row['is_available'],':img'=>$row['image_url']??null,':created'=>$row['created_at'],':by'=>$_SESSION['username']??'admin']);
            $conn->prepare("DELETE FROM menu_items WHERE menu_item_id=:id")->execute([':id'=>$id]);
            audit_log($conn, $_SESSION, 'item_soft_deleted', 'menu_item', $id, $row['item_name'], 'Moved to trash');
            echo json_encode(['success'=>true,'message'=>'"'.$row['item_name'].'" moved to trash. Recoverable for 30 days.']);

        } elseif ($type === 'staff' || $type === 'inactive_staff') {
            $row = $conn->prepare("SELECT * FROM staffs WHERE staff_id=:id AND admin_id=:aid");
            $row->execute([':id'=>$id,':aid'=>$admin_id]);
            $row = $row->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Staff not found.']); exit; }

            $conn->prepare("INSERT INTO deleted_staffs (staff_id, admin_id, fullname, role, status, shift_start, shift_end, original_created_at, deleted_by) VALUES (:staff_id,:admin_id,:fullname,:role,:status,:ss,:se,:created,:by)")
                ->execute([':staff_id'=>$row['staff_id'],':admin_id'=>$row['admin_id'],':fullname'=>$row['fullname'],':role'=>$row['role'],':status'=>$row['status'],':ss'=>$row['shift_start']??null,':se'=>$row['shift_end']??null,':created'=>$row['created_at'],':by'=>$_SESSION['username']??'admin']);
            $conn->prepare("DELETE FROM staffs WHERE staff_id=:id")->execute([':id'=>$id]);
            audit_log($conn, $_SESSION, 'staff_soft_deleted', 'staff', $id, $row['fullname'], 'Moved to trash');
            echo json_encode(['success'=>true,'message'=>'"'.$row['fullname'].'" moved to trash. Recoverable for 30 days.']);
        }
    } catch(Exception $e) { echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]); }
    exit;
}

// ══════════════════════════════════════════════════════════
//  RESTORE
// ══════════════════════════════════════════════════════════
if ($action === 'restore') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id || !in_array($type, ['item','staff'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
    }
    try {
        if ($type === 'item') {
            $row = $conn->prepare("SELECT * FROM deleted_menu_items WHERE id=:id AND admin_id=:aid AND deleted_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)");
            $row->execute([':id'=>$id,':aid'=>$admin_id]);
            $row = $row->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Item not found or recovery window expired.']); exit; }

            // Check if original ID still free
            $conflict = $conn->prepare("SELECT menu_item_id FROM menu_items WHERE menu_item_id=:id");
            $conflict->execute([':id'=>$row['item_id']]);
            if ($conflict->rowCount() > 0) {
                $conn->prepare("INSERT INTO menu_items (admin_id,item_name,description,price,stock_quantity,category,is_available,image_url,created_at) VALUES (:aid,:name,:desc,:price,:stock,:cat,:avail,:img,:created)")
                    ->execute([':aid'=>$row['admin_id'],':name'=>$row['item_name'],':desc'=>$row['description'],':price'=>$row['price'],':stock'=>$row['stock_quantity'],':cat'=>$row['category'],':avail'=>$row['is_available'],':img'=>$row['image_url'],':created'=>$row['original_created_at']]);
            } else {
                $conn->prepare("INSERT INTO menu_items (menu_item_id,admin_id,item_name,description,price,stock_quantity,category,is_available,image_url,created_at) VALUES (:id,:aid,:name,:desc,:price,:stock,:cat,:avail,:img,:created)")
                    ->execute([':id'=>$row['item_id'],':aid'=>$row['admin_id'],':name'=>$row['item_name'],':desc'=>$row['description'],':price'=>$row['price'],':stock'=>$row['stock_quantity'],':cat'=>$row['category'],':avail'=>$row['is_available'],':img'=>$row['image_url'],':created'=>$row['original_created_at']]);
            }
            $conn->prepare("DELETE FROM deleted_menu_items WHERE id=:id AND admin_id=:aid")->execute([':id'=>$id,':aid'=>$admin_id]);
            audit_log($conn, $_SESSION, 'item_restored', 'menu_item', $row['item_id'], $row['item_name'], 'Restored from trash');
            echo json_encode(['success'=>true,'message'=>'"'.$row['item_name'].'" restored successfully.']);

        } elseif ($type === 'staff') {
            $row = $conn->prepare("SELECT * FROM deleted_staffs WHERE id=:id AND admin_id=:aid AND deleted_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)");
            $row->execute([':id'=>$id,':aid'=>$admin_id]);
            $row = $row->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Staff not found or recovery window expired.']); exit; }

            $conflict = $conn->prepare("SELECT staff_id FROM staffs WHERE staff_id=:id");
            $conflict->execute([':id'=>$row['staff_id']]);
            $placeholderPin = password_hash('000000', PASSWORD_DEFAULT);
            if ($conflict->rowCount() > 0) {
                $conn->prepare("INSERT INTO staffs (admin_id,fullname,role,pin,status,shift_start,shift_end,created_at) VALUES (:aid,:name,:role,:pin,'Inactive',:ss,:se,:created)")
                    ->execute([':aid'=>$row['admin_id'],':name'=>$row['fullname'],':role'=>$row['role'],':pin'=>$placeholderPin,':ss'=>$row['shift_start'],':se'=>$row['shift_end'],':created'=>$row['original_created_at']]);
            } else {
                $conn->prepare("INSERT INTO staffs (staff_id,admin_id,fullname,role,pin,status,shift_start,shift_end,created_at) VALUES (:id,:aid,:name,:role,:pin,'Inactive',:ss,:se,:created)")
                    ->execute([':id'=>$row['staff_id'],':aid'=>$row['admin_id'],':name'=>$row['fullname'],':role'=>$row['role'],':pin'=>$placeholderPin,':ss'=>$row['shift_start'],':se'=>$row['shift_end'],':created'=>$row['original_created_at']]);
            }
            $conn->prepare("DELETE FROM deleted_staffs WHERE id=:id AND admin_id=:aid")->execute([':id'=>$id,':aid'=>$admin_id]);
            audit_log($conn, $_SESSION, 'staff_restored', 'staff', $row['staff_id'], $row['fullname'], 'Restored as Inactive. PIN reset to 000000');
            echo json_encode(['success'=>true,'message'=>'"'.$row['fullname'].'" restored as Inactive. PIN reset to 000000 — please update it.']);
        }
    } catch(Exception $e) { echo json_encode(['success'=>false,'message'=>'Restore failed: '.$e->getMessage()]); }
    exit;
}

// ══════════════════════════════════════════════════════════
//  FORCE DELETE (permanent)
// ══════════════════════════════════════════════════════════
if ($action === 'force_delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id || !in_array($type, ['item','staff'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
    }
    try {
        if ($type === 'item') {
            $row = $conn->prepare("SELECT item_name FROM deleted_menu_items WHERE id=:id AND admin_id=:aid");
            $row->execute([':id'=>$id,':aid'=>$admin_id]);
            $row = $row->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found in trash.']); exit; }
            $conn->prepare("DELETE FROM deleted_menu_items WHERE id=:id AND admin_id=:aid")->execute([':id'=>$id,':aid'=>$admin_id]);
            audit_log($conn, $_SESSION, 'item_force_deleted', 'menu_item', $id, $row['item_name'], 'Permanently deleted');
            echo json_encode(['success'=>true,'message'=>'"'.$row['item_name'].'" permanently deleted.']);
        } elseif ($type === 'staff') {
            $row = $conn->prepare("SELECT fullname FROM deleted_staffs WHERE id=:id AND admin_id=:aid");
            $row->execute([':id'=>$id,':aid'=>$admin_id]);
            $row = $row->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found in trash.']); exit; }
            $conn->prepare("DELETE FROM deleted_staffs WHERE id=:id AND admin_id=:aid")->execute([':id'=>$id,':aid'=>$admin_id]);
            audit_log($conn, $_SESSION, 'staff_force_deleted', 'staff', $id, $row['fullname'], 'Permanently deleted');
            echo json_encode(['success'=>true,'message'=>'"'.$row['fullname'].'" permanently deleted.']);
        }
    } catch(Exception $e) { echo json_encode(['success'=>false,'message'=>'Delete failed: '.$e->getMessage()]); }
    exit;
}

// ══════════════════════════════════════════════════════════
//  LIST TRASH
// ══════════════════════════════════════════════════════════
if ($action === 'list') {
    try {
        $items = $staff = [];
        if ($type === 'item' || $type === '') {
            $items = $conn->prepare("SELECT id, item_id, item_name, category, price, stock_quantity, deleted_at, deleted_by, GREATEST(0, DATEDIFF(DATE_ADD(deleted_at, INTERVAL 30 DAY), NOW())) AS days_left, (deleted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS recoverable FROM deleted_menu_items WHERE admin_id=:aid ORDER BY deleted_at DESC");
            $items->execute([':aid'=>$admin_id]);
            $items = $items->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($type === 'staff' || $type === '') {
            $staff = $conn->prepare("SELECT id, staff_id, fullname, role, status, deleted_at, deleted_by, GREATEST(0, DATEDIFF(DATE_ADD(deleted_at, INTERVAL 30 DAY), NOW())) AS days_left, (deleted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS recoverable FROM deleted_staffs WHERE admin_id=:aid ORDER BY deleted_at DESC");
            $staff->execute([':aid'=>$admin_id]);
            $staff = $staff->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success'=>true,'items'=>$items,'staff'=>$staff]);
    } catch(Exception $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

http_response_code(400);
echo json_encode(['success'=>false,'message'=>"Unknown action: \"{$action}\"."]);

function ensureTables(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS deleted_menu_items (id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL, admin_id INT NOT NULL, item_name VARCHAR(255) NOT NULL, description TEXT, price DECIMAL(10,2) NOT NULL DEFAULT 0, stock_quantity INT NOT NULL DEFAULT 0, category VARCHAR(100), is_available TINYINT(1) DEFAULT 1, image_url VARCHAR(500), original_created_at DATETIME, deleted_by VARCHAR(100), deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_adm (admin_id, deleted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->exec("CREATE TABLE IF NOT EXISTS deleted_staffs (id INT AUTO_INCREMENT PRIMARY KEY, staff_id INT NOT NULL, admin_id INT NOT NULL, fullname VARCHAR(255) NOT NULL, role VARCHAR(50), status VARCHAR(50), shift_start TIME, shift_end TIME, original_created_at DATETIME, deleted_by VARCHAR(100), deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_adm (admin_id, deleted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function autoPurge(PDO $conn): void {
    try {
        $conn->exec("DELETE FROM deleted_menu_items WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $conn->exec("DELETE FROM deleted_staffs WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    } catch(Exception $e) {}
}
