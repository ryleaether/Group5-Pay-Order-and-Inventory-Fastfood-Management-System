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
// GET ALL ACTIVE ORDERS
// ─────────────────────────────────────────────
if ($action === 'get_orders') {
    try {
        $stmt = $conn->prepare("
            SELECT
                o.order_id,
                o.queue_number,
                o.order_status,
                o.total_amount,
                o.created_at,
                c.name         AS customer_name,
                COALESCE(c.table_number, '—') AS table_number,
                a.fullname     AS cashier_name
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.customer_id
            LEFT JOIN admins    a ON o.admin_id     = a.admin_id
            WHERE o.admin_id = :admin_id
              AND (
                  o.order_status IN ('Queued','Preparing')
                  OR (
                      o.order_status IN ('Served','Completed','Cancelled')
                      AND DATE(o.created_at) = CURDATE()
                  )
              )
            ORDER BY o.created_at ASC
        ");
        $stmt->bindParam(':admin_id', $admin_id);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($orders)) {
            echo json_encode(['success' => true, 'orders' => []]);
            exit;
        }

        // Fetch items for all orders
        $orderIds     = array_column($orders, 'order_id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $stmtItems = $conn->prepare("
            SELECT
                oi.order_id,
                oi.quantity,
                oi.item_name
            FROM order_items oi
            WHERE oi.order_id IN ($placeholders)
            ORDER BY oi.order_item_id
        ");
        $stmtItems->execute($orderIds);
        $allItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Group items by order_id
        $byOrder = [];
        foreach ($allItems as $item) {
            $byOrder[$item['order_id']][] = $item;
        }
        foreach ($orders as &$order) {
            $order['items'] = $byOrder[$order['order_id']] ?? [];
        }

        echo json_encode(['success' => true, 'orders' => $orders]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────
// UPDATE ORDER STATUS
// ─────────────────────────────────────────────
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id  = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');

    $allowed = ['Queued', 'Preparing', 'Served', 'Cancelled'];
    if (!$order_id || !in_array($newStatus, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    try {
        $chk = $conn->prepare(
            "SELECT order_id FROM orders WHERE order_id = :id AND admin_id = :admin"
        );
        $chk->execute([':id' => $order_id, ':admin' => $admin_id]);
        if (!$chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        // Try with updated_at first, fall back without it
        try {
            $upd = $conn->prepare(
                "UPDATE orders SET order_status = :status, updated_at = NOW()
                 WHERE order_id = :id AND admin_id = :admin"
            );
            $upd->execute([':status' => $newStatus, ':id' => $order_id, ':admin' => $admin_id]);
        } catch (Exception $ex) {
            // updated_at column may not exist on old installs
            $upd = $conn->prepare(
                "UPDATE orders SET order_status = :status
                 WHERE order_id = :id AND admin_id = :admin"
            );
            $upd->execute([':status' => $newStatus, ':id' => $order_id, ':admin' => $admin_id]);
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
