<?php
if (session_status() === PHP_SESSION_NONE) session_start();

session_start();
require_once '../config/database.php'; // adjust path if needed

header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'No admin_id in session', 'notifications' => [], 'unread_count' => 0]);
    exit;
}

$aid = $_SESSION['admin_id'];

if (!isset($_SESSION['dismissed_notifs'])) {
    $_SESSION['dismissed_notifs'] = [];
}
$dismissed = &$_SESSION['dismissed_notifs'];

function is_dismissed(array &$dismissed, string $key): bool {
    return in_array($key, $dismissed);
}

function dismiss_key(array &$dismissed, string $key): void {
    if (!in_array($key, $dismissed)) {
        $dismissed[] = $key;
    }
}

try {
    $db   = new Database();
    $conn = $db->connect();

    // ── Dismiss single notif ──────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dismiss') {
        $key = $_POST['notif_key'] ?? '';
        if ($key) dismiss_key($dismissed, $key);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Mark all read ─────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
        $keys = $_POST['keys'] ?? [];
        foreach ($keys as $key) dismiss_key($dismissed, $key);
        echo json_encode(['ok' => true]);
        exit;
    }

    $notifications = [];
    $unread_count  = 0;

    $add = function(string $key, array $notif) use (&$notifications, &$unread_count, &$dismissed) {
        if (is_dismissed($dismissed, $key)) return;
        $unread_count++;
        $notifications[] = array_merge(['key' => $key], $notif);
    };

    // ── 1. NEW ORDERS (Queued in last 30 min) ─────────────────────────────
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM orders
        WHERE order_status = 'Queued' AND admin_id = :aid
          AND created_at >= NOW() - INTERVAL 30 MINUTE
    ");
    $stmt->execute([':aid' => $aid]);
    $new_orders = (int) $stmt->fetchColumn();
    if ($new_orders > 0) {
        $add('order_queued', [
            'type' => 'order', 'icon' => 'fa-bell-concierge', 'color' => '#ef4444',
            'title' => $new_orders . ' New Order' . ($new_orders > 1 ? 's' : '') . ' Placed',
            'body' => 'Waiting to be prepared.', 'link' => 'order_history.php', 'ts' => time(),
        ]);
    }

    // ── 2. ORDERS BEING PREPARED ──────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM orders
        WHERE order_status = 'Preparing' AND admin_id = :aid
    ");
    $stmt->execute([':aid' => $aid]);
    $preparing = (int) $stmt->fetchColumn();
    if ($preparing > 0) {
        $add('order_preparing', [
            'type' => 'order', 'icon' => 'fa-fire-burner', 'color' => '#f97316',
            'title' => $preparing . ' Order' . ($preparing > 1 ? 's' : '') . ' Being Prepared',
            'body' => 'Currently in the kitchen.', 'link' => 'order_history.php', 'ts' => time(),
        ]);
    }

    // ── 3. COMPLETED ORDERS TODAY ─────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM orders
        WHERE order_status = 'Completed' AND admin_id = :aid
          AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([':aid' => $aid]);
    $completed = (int) $stmt->fetchColumn();
    if ($completed > 0) {
        $add('order_completed', [
            'type' => 'order', 'icon' => 'fa-circle-check', 'color' => '#10b981',
            'title' => $completed . ' Order' . ($completed > 1 ? 's' : '') . ' Completed Today',
            'body' => 'Successfully served today.', 'link' => 'order_history.php', 'ts' => time(),
        ]);
    }

    // ── 4. CANCELLED ORDERS TODAY ─────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM orders
        WHERE order_status = 'Cancelled' AND admin_id = :aid
          AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([':aid' => $aid]);
    $cancelled = (int) $stmt->fetchColumn();
    if ($cancelled > 0) {
        $add('order_cancelled', [
            'type' => 'order', 'icon' => 'fa-circle-xmark', 'color' => '#dc2626',
            'title' => $cancelled . ' Order' . ($cancelled > 1 ? 's' : '') . ' Cancelled Today',
            'body' => 'Review cancelled orders.', 'link' => 'order_history.php', 'ts' => time(),
        ]);
    }

    // ── 5. OUT OF STOCK ───────────────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT item_name FROM menu_items
        WHERE admin_id = :aid AND stock_quantity = 0 AND is_available = 1
        ORDER BY item_name ASC LIMIT 5
    ");
    $stmt->execute([':aid' => $aid]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $key = 'stock_out_' . md5($item['item_name']);
        $add($key, [
            'type' => 'stock', 'icon' => 'fa-ban', 'color' => '#dc2626',
            'title' => 'Out of Stock: ' . htmlspecialchars($item['item_name']),
            'body' => 'This item is no longer available.', 'link' => 'menu_list.php', 'ts' => time(),
        ]);
    }

    // ── 6. LOW STOCK ──────────────────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT item_name, stock_quantity FROM menu_items
        WHERE admin_id = :aid AND stock_quantity > 0 AND stock_quantity <= 10 AND is_available = 1
        ORDER BY stock_quantity ASC LIMIT 5
    ");
    $stmt->execute([':aid' => $aid]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $key = 'stock_low_' . md5($item['item_name']);
        $add($key, [
            'type' => 'stock', 'icon' => 'fa-box-open', 'color' => '#f59e0b',
            'title' => 'Low Stock: ' . htmlspecialchars($item['item_name']),
            'body' => 'Only ' . $item['stock_quantity'] . ' left in stock.', 'link' => 'menu_list.php', 'ts' => time(),
        ]);
    }

    // ── 7. NEW STAFF (last 48h) ───────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT fullname, created_at FROM staffs
        WHERE admin_id = :aid AND created_at >= NOW() - INTERVAL 48 HOUR
        ORDER BY created_at DESC LIMIT 3
    ");
    $stmt->execute([':aid' => $aid]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $ts  = strtotime($s['created_at']);
        $key = 'staff_new_' . md5($s['fullname'] . $s['created_at']);
        $add($key, [
            'type' => 'staff', 'icon' => 'fa-user-plus', 'color' => '#6366f1',
            'title' => 'New Staff: ' . htmlspecialchars($s['fullname']),
            'body' => 'Added ' . human_time_diff($ts) . ' ago.', 'link' => 'manage_staffs.php', 'ts' => $ts,
        ]);
    }

    // ── 8. STAFF LOGIN (last 30 min) ──────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT fullname, last_login_at FROM staffs
        WHERE admin_id = :aid AND last_login_at >= NOW() - INTERVAL 30 MINUTE
        ORDER BY last_login_at DESC LIMIT 3
    ");
    $stmt->execute([':aid' => $aid]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $ts  = strtotime($s['last_login_at']);
        $key = 'staff_login_' . md5($s['fullname'] . $s['last_login_at']);
        $add($key, [
            'type' => 'staff', 'icon' => 'fa-right-to-bracket', 'color' => '#8b5cf6',
            'title' => htmlspecialchars($s['fullname']) . ' Logged In',
            'body' => 'Staff logged in ' . human_time_diff($ts) . ' ago.', 'link' => 'manage_staffs.php', 'ts' => $ts,
        ]);
    }

    // ── 9. SALES MILESTONES ───────────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_amount), 0) FROM orders
        WHERE admin_id = :aid AND order_status IN ('Completed','Served')
          AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([':aid' => $aid]);
    $today_sales = (float) $stmt->fetchColumn();
    foreach (array_reverse([1000,5000,10000,25000,50000,100000]) as $milestone) {
        if ($today_sales >= $milestone) {
            $key = 'milestone_' . $milestone . '_' . date('Y-m-d');
            $add($key, [
                'type' => 'milestone', 'icon' => 'fa-trophy', 'color' => '#10b981',
                'title' => '🎉 ₱' . number_format($milestone) . ' Milestone Hit!',
                'body' => 'Today\'s sales reached ₱' . number_format($today_sales, 2) . '.',
                'link' => 'order_history.php', 'ts' => time(),
            ]);
            break;
        }
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'notifications' => [], 'unread_count' => 0]);
    exit;
}

usort($notifications, fn($a, $b) => $b['ts'] - $a['ts']);
echo json_encode(['notifications' => $notifications, 'unread_count' => $unread_count]);

function human_time_diff(int $ts): string {
    $diff = time() - $ts;
    if ($diff < 60)    return $diff . 's';
    if ($diff < 3600)  return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    return floor($diff / 86400) . 'd';
}