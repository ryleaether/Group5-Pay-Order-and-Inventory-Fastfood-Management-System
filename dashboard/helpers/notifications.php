<?php
// helpers/notifications.php
// Returns all notification types as JSON.
// Called by the header every N seconds via fetch().

session_start();
require_once '../config/database.php'; // adjust path if needed

header('Content-Type: application/json');

// ── Mark-all-read via POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $_SESSION['notif_read_at'] = time();
    echo json_encode(['ok' => true]);
    exit;
}

// ── Fetch data ──────────────────────────────────────────────────────────────
$notifications = [];
$unread_count  = 0;
$read_at       = $_SESSION['notif_read_at'] ?? 0; // Unix timestamp of last "mark all read"

try {
    $db   = new Database();
    $conn = $db->connect();

    // ── 1. PENDING ORDERS ──────────────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM orders
        WHERE order_status = 'Pending'
          AND admin_id = :aid
    ");
    $stmt->execute([':aid' => $_SESSION['admin_id']]);
    $pending = (int) $stmt->fetchColumn();

    if ($pending > 0) {
        $ts    = time(); // always "now" — pending orders are always live
        $is_new = $ts > $read_at;
        if ($is_new) $unread_count++;

        $notifications[] = [
            'type'    => 'order',
            'icon'    => 'fa-receipt',
            'color'   => '#ef4444',
            'title'   => $pending . ' Pending Order' . ($pending > 1 ? 's' : ''),
            'body'    => 'Need your attention right now.',
            'link'    => 'order_history.php',
            'is_new'  => $is_new,
            'ts'      => $ts,
        ];
    }

    // ── 2. LOW STOCK ALERTS ────────────────────────────────────────────────
    // Threshold: stock_quantity <= 5
    $stmt = $conn->prepare("
        SELECT item_name, stock_quantity
        FROM menu_items
        WHERE admin_id = :aid
          AND stock_quantity <= 5
          AND is_available = 1
        ORDER BY stock_quantity ASC
        LIMIT 5
    ");
    $stmt->execute([':aid' => $_SESSION['admin_id']]);
    $low_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($low_items as $item) {
        // Use a stable pseudo-timestamp so it doesn't flip read state every poll
        $ts     = strtotime('today');
        $is_new = $ts > $read_at;
        if ($is_new) $unread_count++;

        $notifications[] = [
            'type'   => 'stock',
            'icon'   => 'fa-box-open',
            'color'  => '#f59e0b',
            'title'  => 'Low Stock: ' . htmlspecialchars($item['item_name']),
            'body'   => 'Only ' . $item['stock_quantity'] . ' left in stock.',
            'link'   => 'menu_list.php',
            'is_new' => $is_new,
            'ts'     => $ts,
        ];
    }

    // ── 3. NEW STAFF ADDED (last 48 h) ────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT fullname, created_at
        FROM staff
        WHERE admin_id   = :aid
          AND created_at >= NOW() - INTERVAL 48 HOUR
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $stmt->execute([':aid' => $_SESSION['admin_id']]);
    $new_staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($new_staff as $s) {
        $ts     = strtotime($s['created_at']);
        $is_new = $ts > $read_at;
        if ($is_new) $unread_count++;

        $notifications[] = [
            'type'   => 'staff',
            'icon'   => 'fa-user-plus',
            'color'  => '#6366f1',
            'title'  => 'New Staff: ' . htmlspecialchars($s['fullname']),
            'body'   => 'Added ' . human_time_diff($ts) . ' ago.',
            'link'   => 'manage_staffs.php',
            'is_new' => $is_new,
            'ts'     => $ts,
        ];
    }

    // ── 4. SALES MILESTONES ────────────────────────────────────────────────
    // Checks today's revenue against round-number thresholds (₱1k, ₱5k, ₱10k…)
    $thresholds = [1000, 5000, 10000, 25000, 50000, 100000];

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_amount), 0) AS today_sales
        FROM orders
        WHERE admin_id    = :aid
          AND order_status = 'Completed'
          AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([':aid' => $_SESSION['admin_id']]);
    $today_sales = (float) $stmt->fetchColumn();

    foreach (array_reverse($thresholds) as $milestone) {
        if ($today_sales >= $milestone) {
            $ts     = strtotime('today');
            $is_new = $ts > $read_at;
            if ($is_new) $unread_count++;

            $notifications[] = [
                'type'   => 'milestone',
                'icon'   => 'fa-trophy',
                'color'  => '#10b981',
                'title'  => '🎉 ₱' . number_format($milestone) . ' Milestone Hit!',
                'body'   => 'Today\'s sales reached ₱' . number_format($today_sales, 2) . '.',
                'link'   => 'sales_report.php',
                'is_new' => $is_new,
                'ts'     => $ts,
            ];
            break; // only show highest milestone reached
        }
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'notifications' => [], 'unread_count' => 0]);
    exit;
}

// Sort: newest first
usort($notifications, fn($a, $b) => $b['ts'] - $a['ts']);

echo json_encode([
    'notifications' => $notifications,
    'unread_count'  => $unread_count,
]);

// ── Helper ──────────────────────────────────────────────────────────────────
function human_time_diff(int $ts): string {
    $diff = time() - $ts;
    if ($diff < 60)   return $diff . 's';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    return floor($diff / 86400) . 'd';
}