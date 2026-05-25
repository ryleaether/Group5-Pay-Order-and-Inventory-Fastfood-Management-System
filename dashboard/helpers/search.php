<?php
session_start();
require_once __DIR__ . "/../../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['admin_id'];
$q        = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(['orders' => [], 'menu' => [], 'staff' => []]);
    exit;
}

$db   = new Database();
$conn = $db->connect();
$like = '%' . $q . '%';

$results = ['orders' => [], 'menu' => [], 'staff' => []];

// ── ORDERS ──
try {
    $stmt = $conn->prepare("
        SELECT o.queue_number, o.order_status, o.total_amount,
               c.name AS customer_name
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.admin_id = :admin_id
          AND (
              o.queue_number LIKE :q
              OR o.order_status LIKE :q
              OR c.name LIKE :q
          )
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([':admin_id' => $admin_id, ':q' => $like]);
    $results['orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $results['orders'] = [];
}

// ── MENU ITEMS ──
try {
    $stmt = $conn->prepare("
        SELECT item_name, price, category, stock_quantity
        FROM menu_items
        WHERE admin_id = :admin_id
          AND (item_name LIKE :q OR category LIKE :q OR description LIKE :q)
        ORDER BY item_name ASC
        LIMIT 5
    ");
    $stmt->execute([':admin_id' => $admin_id, ':q' => $like]);
    $results['menu'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $results['menu'] = [];
}

// ── STAFF ── (table: staffs, columns: fullname, role, status)
try {
    $stmt = $conn->prepare("
        SELECT staff_id, fullname, role, status
        FROM staffs
        WHERE admin_id = :admin_id
          AND (fullname LIKE :q OR role LIKE :q OR status LIKE :q)
        ORDER BY fullname ASC
        LIMIT 5
    ");
    $stmt->execute([':admin_id' => $admin_id, ':q' => $like]);
    $results['staff'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $results['staff'] = [];
}

echo json_encode($results);