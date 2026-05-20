<?php
session_start();
require_once __DIR__ . "/../../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['admin_id'];
$action   = $_GET['action'] ?? 'place_order';

/* ================================================================
   CANCEL ORDER
================================================================ */
if ($action === 'cancel_order') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $order_id = intval($input['order_id'] ?? 0);

    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid order.']);
        exit;
    }

    $db   = new Database();
    $conn = $db->connect();

    try {
        $conn->beginTransaction();

        /* Only cancel if still Queued */
        $stmt = $conn->prepare("
            SELECT o.order_id, o.order_status
            FROM orders o
            WHERE o.order_id = :order_id AND o.admin_id = :admin_id
        ");
        $stmt->bindParam(":order_id", $order_id);
        $stmt->bindParam(":admin_id", $admin_id);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception('Order not found.');
        }
        if ($order['order_status'] !== 'Queued') {
            throw new Exception('Order cannot be cancelled — it is already ' . $order['order_status'] . '.');
        }

        /* Restore stock */
        $stmt = $conn->prepare("SELECT menu_item_id, quantity FROM order_items WHERE order_id = :order_id");
        $stmt->bindParam(":order_id", $order_id);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $stmt = $conn->prepare("
                UPDATE menu_items SET stock_quantity = stock_quantity + :qty
                WHERE menu_item_id = :id AND admin_id = :admin_id
            ");
            $stmt->bindParam(":qty",      $item['quantity']);
            $stmt->bindParam(":id",       $item['menu_item_id']);
            $stmt->bindParam(":admin_id", $admin_id);
            $stmt->execute();
        }

        /* Update order status */
        $stmt = $conn->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE order_id = :id");
        $stmt->bindParam(":id", $order_id);
        $stmt->execute();

        /* Update payment status */
        $stmt = $conn->prepare("UPDATE payments SET payment_status = 'Failed' WHERE order_id = :id");
        $stmt->bindParam(":id", $order_id);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ================================================================
   PLACE ORDER
================================================================ */
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$cash_paid    = floatval($input['cash_paid']    ?? 0);
$change_given = floatval($input['change_given'] ?? 0);
$total        = floatval($input['total']        ?? 0);
$items        = $input['items'] ?? [];

if (empty($items) || $total <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing order data.']);
    exit;
}
if ($cash_paid < $total) {
    echo json_encode(['success' => false, 'message' => 'Insufficient cash amount.']);
    exit;
}

$db   = new Database();
$conn = $db->connect();

try {
    $conn->beginTransaction();

    /* ── 1. Final stock check ── */
    foreach ($items as $item) {
        $stmt = $conn->prepare("
            SELECT stock_quantity FROM menu_items
            WHERE menu_item_id = :id AND admin_id = :admin_id AND is_available = 1
        ");
        $stmt->bindParam(":id",       $item['menu_item_id']);
        $stmt->bindParam(":admin_id", $admin_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception($item['name'] . ' is no longer available.');
        if ($row['stock_quantity'] < $item['qty'])
            throw new Exception('Not enough stock for ' . $item['name'] . '. Only ' . $row['stock_quantity'] . ' left.');
    }

    /* ── 2. Auto-assign table number ── */
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(CAST(table_number AS UNSIGNED)), 0) + 1 AS next_table
        FROM customers
        WHERE session_start >= CURDATE()
    ");
    $stmt->execute();
    $table_number = $stmt->fetch(PDO::FETCH_ASSOC)['next_table'];

    /* ── 3. Create customer record ── */
    $stmt = $conn->prepare("
        INSERT INTO customers (table_number, session_start)
        VALUES (:table_number, NOW())
    ");
    $stmt->bindParam(":table_number", $table_number);
    $stmt->execute();
    $customer_id = $conn->lastInsertId();

    /* ── 4. Get next queue number ── */
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(queue_number), 0) + 1 AS next_queue
        FROM orders WHERE admin_id = :admin_id AND DATE(created_at) = CURDATE()
    ");
    $stmt->bindParam(":admin_id", $admin_id);
    $stmt->execute();
    $queue_number = $stmt->fetch(PDO::FETCH_ASSOC)['next_queue'];

    /* ── 5. Create order ── */
    $stmt = $conn->prepare("
        INSERT INTO orders (admin_id, customer_id, order_status, total_amount, queue_number, created_at)
        VALUES (:admin_id, :customer_id, 'Queued', :total, :queue_number, NOW())
    ");
    $stmt->bindParam(":admin_id",     $admin_id);
    $stmt->bindParam(":customer_id",  $customer_id);
    $stmt->bindParam(":total",        $total);
    $stmt->bindParam(":queue_number", $queue_number);
    $stmt->execute();
    $order_id = $conn->lastInsertId();

    /* ── 6. Insert order items + deduct stock ── */
    foreach ($items as $item) {
        $stmt = $conn->prepare("
            INSERT INTO order_items (order_id, menu_item_id, item_name, price, quantity, subtotal)
            VALUES (:order_id, :menu_item_id, :name, :price, :qty, :subtotal)
        ");
        $stmt->bindParam(":order_id",     $order_id);
        $stmt->bindParam(":menu_item_id", $item['menu_item_id']);
        $stmt->bindParam(":name",         $item['name']);
        $stmt->bindParam(":price",        $item['price']);
        $stmt->bindParam(":qty",          $item['qty']);
        $stmt->bindParam(":subtotal",     $item['subtotal']);
        $stmt->execute();

        $stmt = $conn->prepare("
            UPDATE menu_items SET stock_quantity = stock_quantity - :qty
            WHERE menu_item_id = :id AND admin_id = :admin_id
        ");
        $stmt->bindParam(":qty",      $item['qty']);
        $stmt->bindParam(":id",       $item['menu_item_id']);
        $stmt->bindParam(":admin_id", $admin_id);
        $stmt->execute();
    }

    /* ── 7. Create payment record ── */
    $receipt_number = 'RCP-' . strtoupper(uniqid());
    $stmt = $conn->prepare("
        INSERT INTO payments (order_id, payment_method, amount_paid, change_given, receipt_number, payment_status, payment_date)
        VALUES (:order_id, 'Cash', :amount_paid, :change_given, :receipt_number, 'Completed', NOW())
    ");
    $stmt->bindParam(":order_id",       $order_id);
    $stmt->bindParam(":amount_paid",    $cash_paid);
    $stmt->bindParam(":change_given",   $change_given);
    $stmt->bindParam(":receipt_number", $receipt_number);
    $stmt->execute();

    /* ── 8. Update customer order number ── */
    $order_number = 'ORD-' . str_pad($order_id, 5, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("UPDATE customers SET order_number = :order_number WHERE customer_id = :id");
    $stmt->bindParam(":order_number", $order_number);
    $stmt->bindParam(":id",           $customer_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        'success'        => true,
        'queue_number'   => $queue_number,
        'order_id'       => $order_id,
        'table_number'   => $table_number,
        'receipt_number' => $receipt_number
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
