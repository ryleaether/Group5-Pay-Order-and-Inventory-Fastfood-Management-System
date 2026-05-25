<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . "/helpers/admindashboard_helpers.php";
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$val = new Validation();
if (!$val->adminExists($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$admin_id = (int)$_SESSION['admin_id'];

try {
    $maint_stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('maintenance_enabled')");
    $maint_rows = $maint_stmt->fetchAll(PDO::FETCH_ASSOC);
    $maint_map = array_column($maint_rows, 'setting_value', 'setting_key');
    if (($maint_map['maintenance_enabled'] ?? '0') === '1') {
        session_write_close();
        header('Location: ../maintenance.php');
        exit;
    }
} catch (Exception $e) {}

function dash_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dash_money($value, int $decimals = 2): string {
    return '&#8369;' . number_format((float)$value, $decimals);
}

function dash_asset_url(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
        return $path;
    }
    $path = ltrim($path, './');
    if (str_starts_with($path, '../')) return $path;
    return '../' . $path;
}

function dash_col_exists(PDO $conn, string $table, string $column): bool {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function dash_scalar(PDO $conn, string $sql, array $params = [], $fallback = 0) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $fallback : $value;
    } catch (Exception $e) {
        return $fallback;
    }
}

function dash_rows(PDO $conn, string $sql, array $params = []): array {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function dash_activity_rows(PDO $conn, int $admin_id, int $limit = 5): array {
    try {
        $actor = dash_col_exists($conn, 'audit_log', 'actor_name') ? 'actor_name' : 'username';
        $targetType = dash_col_exists($conn, 'audit_log', 'target_type') ? 'target_type' : 'target';
        $targetLabel = dash_col_exists($conn, 'audit_log', 'target_label') ? 'target_label' : 'target_name';
        $detail = dash_col_exists($conn, 'audit_log', 'detail') ? 'detail' : 'details';

        $stmt = $conn->prepare("
            SELECT log_id, {$actor} AS actor_name, action, {$targetType} AS target_type,
                   {$targetLabel} AS target_label, {$detail} AS detail, created_at
            FROM audit_log
            WHERE admin_id = :admin_id
            ORDER BY created_at DESC, log_id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([':admin_id' => $admin_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function dash_action_title(array $activity): string {
    $action = ucwords(str_replace('_', ' ', (string)($activity['action'] ?? 'Activity')));
    $target = trim((string)($activity['target_label'] ?? ''));
    return $target !== '' ? "{$action}: {$target}" : $action;
}

$period = strtolower((string)($_GET['period'] ?? 'week'));
if (!in_array($period, ['day', 'week', 'month', 'all'], true)) {
    $period = 'week';
}

$periodLabels = [
    'day' => 'Day',
    'week' => 'Week',
    'month' => 'Month',
    'all' => 'All Time',
];

$orderDateFilter = '';
$paymentDateFilter = '';
if ($period === 'day') {
    $orderDateFilter = ' AND o.created_at >= CURDATE()';
    $paymentDateFilter = ' AND p.payment_date >= CURDATE()';
} elseif ($period === 'week') {
    $orderDateFilter = ' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)';
    $paymentDateFilter = ' AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)';
} elseif ($period === 'month') {
    $orderDateFilter = ' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)';
    $paymentDateFilter = ' AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)';
}

$adminProfile = dash_rows(
    $conn,
    "SELECT username, email, fullname, fastfood_name, business_type FROM admins WHERE admin_id = :id LIMIT 1",
    [':id' => $admin_id]
)[0] ?? [];

$extendedProfile = dash_rows(
    $conn,
    "SELECT logo_url, logo_shape, profile_photo FROM admin_extended WHERE admin_id = :id LIMIT 1",
    [':id' => $admin_id]
)[0] ?? [];

$sidebar = new SidebarRenderer(
    $admin_id,
    $_SESSION['fastfood_name'] ?? ($adminProfile['fastfood_name'] ?? ''),
    $adminProfile['fullname'] ?? $_SESSION['username'] ?? '',
    $adminProfile['username'] ?? $_SESSION['username'] ?? '',
    $adminProfile['email'] ?? ''
);

$storeName = $adminProfile['fastfood_name'] ?? $_SESSION['fastfood_name'] ?? 'Store';
$adminName = $adminProfile['fullname'] ?? $_SESSION['username'] ?? 'Admin';
$businessType = $adminProfile['business_type'] ?: 'Fast Food Store';
$heroLogo = dash_asset_url($extendedProfile['logo_url'] ?? '') ?: dash_asset_url($extendedProfile['profile_photo'] ?? '');
$logoShape = $extendedProfile['logo_shape'] ?? 'rounded';
$logoRadius = $logoShape === 'circle' ? '50%' : ($logoShape === 'square' ? '8px' : '16px');
$initial = strtoupper(substr($adminName, 0, 1)) ?: 'A';

$totalSales = (float)dash_scalar($conn, "
    SELECT COALESCE(SUM(p.amount_paid), 0)
    FROM payments p
    JOIN orders o ON o.order_id = p.order_id
    WHERE o.admin_id = :id AND p.payment_status = 'Completed'
    {$paymentDateFilter}
", [':id' => $admin_id]);

$paidOrders = (int)dash_scalar($conn, "
    SELECT COUNT(*)
    FROM payments p
    JOIN orders o ON o.order_id = p.order_id
    WHERE o.admin_id = :id AND p.payment_status = 'Completed'
    {$paymentDateFilter}
", [':id' => $admin_id]);

$totalOrders = (int)dash_scalar($conn, "SELECT COUNT(*) FROM orders o WHERE o.admin_id = :id {$orderDateFilter}", [':id' => $admin_id]);
$menuItemsSold = (int)dash_scalar($conn, "
    SELECT COALESCE(SUM(oi.quantity), 0)
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    WHERE o.admin_id = :id AND o.order_status != 'Cancelled'
    {$orderDateFilter}
", [':id' => $admin_id]);
$avgOrderValue = $paidOrders > 0 ? $totalSales / $paidOrders : 0;
$activeStaff = (int)dash_scalar($conn, "SELECT COUNT(*) FROM staffs WHERE admin_id = :id AND status = 'Active'", [':id' => $admin_id]);
$pendingOrders = (int)dash_scalar($conn, "SELECT COUNT(*) FROM orders o WHERE o.admin_id = :id AND o.order_status IN ('Queued','Preparing') {$orderDateFilter}", [':id' => $admin_id]);
$cancelledOrders = (int)dash_scalar($conn, "SELECT COUNT(*) FROM orders o WHERE o.admin_id = :id AND o.order_status = 'Cancelled' {$orderDateFilter}", [':id' => $admin_id]);
$totalMenu = (int)dash_scalar($conn, "SELECT COUNT(*) FROM menu_items WHERE admin_id = :id", [':id' => $admin_id]);
$onlineKitchen = (int)dash_scalar($conn, "SELECT COUNT(*) FROM staffs WHERE admin_id = :id AND role = 'Kitchen' AND is_online = 1", [':id' => $admin_id]);
$totalKitchen = (int)dash_scalar($conn, "SELECT COUNT(*) FROM staffs WHERE admin_id = :id AND role = 'Kitchen'", [':id' => $admin_id]);
$onlineCashier = (int)dash_scalar($conn, "SELECT COUNT(*) FROM staffs WHERE admin_id = :id AND role = 'Cashier' AND is_online = 1", [':id' => $admin_id]);
$totalCashier = (int)dash_scalar($conn, "SELECT COUNT(*) FROM staffs WHERE admin_id = :id AND role = 'Cashier'", [':id' => $admin_id]);

$recentOrders = dash_rows($conn, "
    SELECT o.order_id, o.queue_number, o.total_amount, o.order_status, o.created_at,
           COALESCE(c.name, 'Guest') AS customer_name,
           COALESCE(SUM(oi.quantity), 0) AS item_count
    FROM orders o
    LEFT JOIN customers c ON c.customer_id = o.customer_id
    LEFT JOIN order_items oi ON oi.order_id = o.order_id
    WHERE o.admin_id = :id
    {$orderDateFilter}
    GROUP BY o.order_id, o.queue_number, o.total_amount, o.order_status, o.created_at, c.name
    ORDER BY o.created_at DESC
    LIMIT 5
", [':id' => $admin_id]);

$topItems = dash_rows($conn, "
    SELECT oi.item_name, SUM(oi.quantity) AS total_sold
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    WHERE o.admin_id = :id AND o.order_status != 'Cancelled'
    {$orderDateFilter}
    GROUP BY oi.item_name
    ORDER BY total_sold DESC
    LIMIT 5
", [':id' => $admin_id]);

$lowStock = dash_rows($conn, "
    SELECT item_name, category, stock_quantity
    FROM menu_items
    WHERE admin_id = :id
      AND is_available = 1
      AND stock_quantity <= 5
    ORDER BY stock_quantity ASC, item_name ASC
    LIMIT 6
", [':id' => $admin_id]);

$paymentRows = dash_rows($conn, "
    SELECT p.payment_method AS label, COUNT(*) AS total
    FROM payments p
    JOIN orders o ON o.order_id = p.order_id
    WHERE o.admin_id = :id AND p.payment_status = 'Completed'
    {$paymentDateFilter}
    GROUP BY p.payment_method
    ORDER BY total DESC
", [':id' => $admin_id]);

$salesOverview = [];
if ($period === 'day') {
    $revenueRows = dash_rows($conn, "
        SELECT DATE_FORMAT(p.payment_date, '%Y-%m-%d %H:00:00') AS sale_key, COALESCE(SUM(p.amount_paid), 0) AS total
        FROM payments p
        JOIN orders o ON o.order_id = p.order_id
        WHERE o.admin_id = :id AND p.payment_status = 'Completed' {$paymentDateFilter}
        GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m-%d %H:00:00')
    ", [':id' => $admin_id]);
    $revenueMap = array_column($revenueRows, 'total', 'sale_key');
    for ($i = 6; $i >= 0; $i--) {
        $time = strtotime("-{$i} hours");
        $key = date('Y-m-d H:00:00', $time);
        $salesOverview[] = ['label' => date('gA', $time), 'value' => (float)($revenueMap[$key] ?? 0)];
    }
} elseif ($period === 'month') {
    $revenueRows = dash_rows($conn, "
        SELECT YEARWEEK(p.payment_date, 3) AS sale_key, COALESCE(SUM(p.amount_paid), 0) AS total
        FROM payments p
        JOIN orders o ON o.order_id = p.order_id
        WHERE o.admin_id = :id AND p.payment_status = 'Completed' {$paymentDateFilter}
        GROUP BY YEARWEEK(p.payment_date, 3)
    ", [':id' => $admin_id]);
    $revenueMap = array_column($revenueRows, 'total', 'sale_key');
    for ($i = 3; $i >= 0; $i--) {
        $time = strtotime("-{$i} weeks");
        $key = date('oW', $time);
        $salesOverview[] = ['label' => 'W' . date('W', $time), 'value' => (float)($revenueMap[$key] ?? 0)];
    }
} elseif ($period === 'all') {
    $revenueRows = dash_rows($conn, "
        SELECT DATE_FORMAT(p.payment_date, '%Y-%m') AS sale_key, COALESCE(SUM(p.amount_paid), 0) AS total
        FROM payments p
        JOIN orders o ON o.order_id = p.order_id
        WHERE o.admin_id = :id AND p.payment_status = 'Completed'
        GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
    ", [':id' => $admin_id]);
    $revenueMap = array_column($revenueRows, 'total', 'sale_key');
    for ($i = 11; $i >= 0; $i--) {
        $time = strtotime("-{$i} months");
        $key = date('Y-m', $time);
        $salesOverview[] = ['label' => date('M', $time), 'value' => (float)($revenueMap[$key] ?? 0)];
    }
} else {
    $revenueRows = dash_rows($conn, "
        SELECT DATE(p.payment_date) AS sale_key, COALESCE(SUM(p.amount_paid), 0) AS total
        FROM payments p
        JOIN orders o ON o.order_id = p.order_id
        WHERE o.admin_id = :id AND p.payment_status = 'Completed' {$paymentDateFilter}
        GROUP BY DATE(p.payment_date)
    ", [':id' => $admin_id]);
    $revenueMap = array_column($revenueRows, 'total', 'sale_key');
    for ($i = 6; $i >= 0; $i--) {
        $time = strtotime("-{$i} days");
        $key = date('Y-m-d', $time);
        $salesOverview[] = ['label' => date('D', $time), 'value' => (float)($revenueMap[$key] ?? 0)];
    }
}
$maxRevenue = max(array_column($salesOverview, 'value') ?: [0]);
$maxRevenue = $maxRevenue > 0 ? $maxRevenue : 1;
$barCount = max(1, count($salesOverview));
$maxTopSold = max(array_map(fn($item) => (int)$item['total_sold'], $topItems) ?: [1]);
$recentActivities = dash_activity_rows($conn, $admin_id, 5);
$paymentTotal = array_sum(array_map(fn($row) => (int)$row['total'], $paymentRows));
$donutColors = [
    'var(--accent)',
    'color-mix(in srgb, var(--accent) 68%, #fff 32%)',
    'color-mix(in srgb, var(--accent) 42%, #fff 58%)',
];
$donutStops = [];
$donutStart = 0;
foreach (array_slice($paymentRows, 0, 3) as $i => $row) {
    $slice = $paymentTotal > 0 ? (((int)$row['total'] / $paymentTotal) * 100) : 0;
    $donutEnd = $i === min(2, count($paymentRows) - 1) ? 100 : $donutStart + $slice;
    $donutStops[] = $donutColors[$i] . ' ' . round($donutStart, 2) . '% ' . round($donutEnd, 2) . '%';
    $donutStart = $donutEnd;
}
$donutGradient = $donutStops ? 'conic-gradient(' . implode(', ', $donutStops) . ')' : 'conic-gradient(var(--border-color) 0 100%)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iPOS Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../design/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <style>
        .page-content { padding: 24px 32px 34px; }
        .admin-analytics { display: flex; flex-direction: column; gap: 14px; color: var(--text-primary); }
        .ad-hero {
            min-height: 116px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: #fff;
            padding: 24px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            box-shadow: 0 12px 26px color-mix(in srgb, var(--accent) 24%, transparent);
        }
        .ad-hero-profile { display: flex; align-items: center; gap: 18px; min-width: 0; }
        .ad-logo {
            width: 72px;
            height: 72px;
            border-radius: <?= dash_h($logoRadius) ?>;
            background: rgba(255,255,255,0.16);
            border: 3px solid rgba(255,255,255,0.62);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            font-size: 28px;
            font-weight: 900;
            box-shadow: 0 8px 22px rgba(0,0,0,0.16);
        }
        .ad-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .ad-title { margin: 0; color: #fff; font-size: 28px; font-weight: 900; line-height: 1.05; letter-spacing: 0; }
        .ad-subtitle { margin: 5px 0 8px; color: rgba(255,255,255,0.86); font-size: 13px; font-weight: 650; }
        .ad-chip {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 3px 12px;
            border-radius: 99px;
            background: rgba(255,255,255,0.16);
            color: rgba(255,255,255,0.76);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .ad-range { display: flex; gap: 12px; flex-wrap: wrap; justify-content: flex-end; }
        .ad-range a {
            min-width: 78px;
            height: 34px;
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 6px;
            background: rgba(255,255,255,0.96);
            color: var(--text-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font: inherit;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
        }
        .ad-range a.active { background: color-mix(in srgb, var(--accent) 88%, #fff 12%); color: #fff; }
        .ad-metric-grid { display: grid; grid-template-columns: repeat(5, minmax(150px, 1fr)); gap: 14px; }
        .ad-metric {
            min-height: 148px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: #fff;
            padding: 20px 22px 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--accent) 20%, transparent);
            overflow: hidden;
        }
        .ad-metric-head { display: flex; align-items: center; gap: 14px; }
        .ad-metric-icon {
            width: 38px;
            height: 38px;
            border-radius: 7px;
            background: rgba(255,255,255,0.94);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }
        .ad-metric-label { color: #fff; font-size: 11px; font-weight: 850; text-transform: uppercase; line-height: 1.2; }
        .ad-metric-value { margin-top: 14px; font-size: 25px; line-height: 1; font-weight: 900; color: #fff; }
        .ad-metric-note { margin-top: 8px; color: rgba(255,255,255,0.9); font-size: 11px; font-weight: 700; }
        .ad-sparkline { width: 100%; height: 28px; margin-top: 12px; }
        .ad-sparkline polyline { fill: none; stroke: rgba(255,255,255,0.96); stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
        .ad-panel-grid { display: grid; grid-template-columns: 1.1fr 1fr 1.15fr; gap: 14px; }
        .ad-panel, .ad-strip, .ad-table-panel {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 2px 14px rgba(45,10,31,0.05);
        }
        .ad-panel { padding: 18px 20px; min-height: 230px; overflow: hidden; }
        .ad-panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; gap: 12px; }
        .ad-panel-title { margin: 0; font-size: 14px; font-weight: 900; color: var(--text-primary); }
        .ad-period-tag { color: var(--text-secondary); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .ad-bars {
            height: 142px;
            display: grid;
            grid-template-columns: repeat(var(--bar-count, 7), minmax(0, 1fr));
            align-items: end;
            gap: clamp(5px, 1.3vw, 12px);
            padding: 8px 4px 0;
            border-bottom: 1px solid var(--border-color);
            background:
                linear-gradient(to top, color-mix(in srgb, var(--border-color) 55%, transparent) 1px, transparent 1px) 0 0 / 100% 33.333%;
        }
        .ad-bar-wrap { height: 118px; min-width: 0; display: flex; align-items: end; justify-content: center; }
        .ad-bar {
            width: clamp(8px, 45%, 18px);
            min-height: 4px;
            border-radius: 6px 6px 0 0;
            background: linear-gradient(180deg, var(--accent), color-mix(in srgb, var(--accent) 52%, #fff 48%));
            box-shadow: 0 6px 14px color-mix(in srgb, var(--accent) 16%, transparent);
        }
        .ad-bar-labels {
            display: grid;
            grid-template-columns: repeat(var(--bar-count, 7), minmax(0, 1fr));
            gap: clamp(5px, 1.3vw, 12px);
            padding: 8px 4px 0;
            font-size: 10px;
            color: var(--text-secondary);
            text-align: center;
        }
        .ad-bar-labels span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ad-legend { margin-top: 12px; display: flex; align-items: center; gap: 8px; color: var(--text-primary); font-size: 12px; font-weight: 650; }
        .ad-legend-dot { width: 12px; height: 12px; border-radius: 3px; background: var(--accent); }
        .ad-donut-layout { min-height: 158px; display: grid; grid-template-columns: minmax(132px, 156px) minmax(0, 1fr); gap: 22px; align-items: center; }
        .ad-donut {
            width: 138px;
            height: 138px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent) 20%, transparent), 0 12px 26px color-mix(in srgb, var(--accent) 12%, transparent);
        }
        .ad-donut-center {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            background: var(--card-bg);
            display: grid;
            place-items: center;
            text-align: center;
            font-size: 11px;
            color: var(--text-secondary);
            line-height: 1.2;
        }
        .ad-donut-center strong { display: block; font-size: 20px; color: var(--text-primary); line-height: 1.1; }
        .ad-type-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: color-mix(in srgb, var(--body-bg) 62%, #fff 38%);
        }
        .ad-type-row { display: grid; grid-template-columns: 12px minmax(0,1fr) auto; align-items: center; gap: 10px; font-size: 12px; color: var(--text-primary); }
        .ad-type-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); }
        .ad-type-row:nth-child(2) .ad-type-dot { background: color-mix(in srgb, var(--accent) 68%, #fff 32%); }
        .ad-type-row:nth-child(3) .ad-type-dot { background: color-mix(in srgb, var(--accent) 40%, #fff 60%); }
        .ad-type-row span:nth-child(2) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ad-top-list { display: flex; flex-direction: column; gap: 16px; }
        .ad-top-row { display: grid; grid-template-columns: minmax(100px, 1fr) 1.8fr 42px; align-items: center; gap: 14px; font-size: 12px; color: var(--text-primary); }
        .ad-progress { height: 12px; border-radius: 4px; background: var(--accent-light); overflow: hidden; }
        .ad-progress span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--accent-dark), var(--accent)); }
        .ad-strip { display: grid; grid-template-columns: repeat(5, 1fr); padding: 12px 10px; }
        .ad-mini { display: flex; align-items: center; gap: 12px; padding: 4px 14px; border-right: 1px solid var(--border-color); min-width: 0; }
        .ad-mini:last-child { border-right: 0; }
        .ad-mini-icon { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; font-size: 19px; flex-shrink: 0; }
        .ad-mini:nth-child(1) .ad-mini-icon { background: #fce7f3; color: var(--accent); }
        .ad-mini:nth-child(2) .ad-mini-icon { background: #fee2e2; color: #dc2626; }
        .ad-mini:nth-child(3) .ad-mini-icon { background: #ede9fe; color: #7c3aed; }
        .ad-mini:nth-child(4) .ad-mini-icon { background: #dcfce7; color: #16a34a; }
        .ad-mini:nth-child(5) .ad-mini-icon { background: #dbeafe; color: #2563eb; }
        .ad-mini-label { font-size: 11px; font-weight: 850; text-transform: uppercase; color: var(--text-primary); white-space: nowrap; }
        .ad-mini-value { margin-top: 4px; font-size: 19px; font-weight: 900; color: var(--text-primary); }
        .ad-mini-sub { margin-top: 2px; font-size: 11px; color: var(--text-secondary); }
        .ad-orders-row { display: block; }
        .ad-secondary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .ad-table-panel { padding: 16px 20px 18px; min-height: 264px; display: flex; flex-direction: column; }
        .ad-orders-wide { min-height: 300px; }
        .ad-table-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 900; color: var(--text-primary); margin-bottom: 12px; }
        .ad-orders-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .ad-orders-table th {
            background: linear-gradient(90deg, var(--accent-dark), var(--accent));
            color: #fff;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            padding: 9px 12px;
        }
        .ad-orders-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
        .ad-status { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 99px; font-size: 10px; font-weight: 850; }
        .ad-status.served, .ad-status.completed { background: #dcfce7; color: #15803d; }
        .ad-status.queued, .ad-status.preparing { background: #fef3c7; color: #b45309; }
        .ad-status.cancelled { background: #fee2e2; color: #dc2626; }
        .ad-panel-link {
            margin-top: auto;
            align-self: center;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent);
            text-decoration: none;
            font-size: 12px;
            font-weight: 900;
            padding-top: 14px;
        }
        .ad-activity-list { display: flex; flex-direction: column; gap: 14px; }
        .ad-activity { display: grid; grid-template-columns: 28px 1fr auto; gap: 12px; align-items: start; }
        .ad-activity-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: var(--accent-light);
            color: var(--accent);
            display: grid;
            place-items: center;
            font-size: 12px;
        }
        .ad-activity-title { color: var(--text-primary); font-size: 12px; font-weight: 850; line-height: 1.3; }
        .ad-activity-sub { color: var(--text-secondary); font-size: 11px; line-height: 1.4; margin-top: 2px; }
        .ad-activity-time { color: var(--text-secondary); font-size: 11px; white-space: nowrap; }
        .ad-stock-list { display: flex; flex-direction: column; gap: 10px; }
        .ad-stock-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 12px;
            align-items: center;
            padding: 11px 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: color-mix(in srgb, var(--body-bg) 62%, #fff 38%);
        }
        .ad-stock-name { min-width: 0; }
        .ad-stock-name strong { display: block; color: var(--text-primary); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ad-stock-name span { display: block; margin-top: 2px; color: var(--text-secondary); font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ad-stock-count { min-width: 44px; text-align: center; font-size: 18px; font-weight: 900; color: var(--text-primary); }
        .ad-stock-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 70px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 850; }
        .ad-stock-badge.out { background: #fee2e2; color: #b91c1c; }
        .ad-stock-badge.low { background: #fef3c7; color: #92400e; }
        .ad-empty { display: grid; place-items: center; min-height: 120px; color: var(--text-secondary); font-size: 13px; text-align: center; }
        @media (max-width: 1280px) {
            .ad-metric-grid { grid-template-columns: repeat(3, 1fr); }
            .ad-panel-grid { grid-template-columns: 1fr; }
            .ad-strip { grid-template-columns: repeat(2, 1fr); }
            .ad-mini { border-right: 0; border-bottom: 1px solid var(--border-color); }
        }
        @media (max-width: 760px) {
            .page-content { padding: 16px; }
            .ad-hero { align-items: flex-start; flex-direction: column; }
            .ad-range { justify-content: flex-start; }
            .ad-metric-grid, .ad-secondary-grid, .ad-strip { grid-template-columns: 1fr; }
            .ad-donut-layout { grid-template-columns: 1fr; justify-items: center; }
            .ad-title { font-size: 23px; }
        }
    </style>
</head>
<body>
<div class="dashboard" id="dashboardRoot">
    <?= $sidebar->render('dashboard') ?>

    <div class="admin-analytics">
        <section class="ad-hero">
            <div class="ad-hero-profile">
                <div class="ad-logo">
                    <?php if ($heroLogo): ?>
                        <img src="<?= dash_h($heroLogo) ?>" alt="<?= dash_h($storeName) ?> logo">
                    <?php else: ?>
                        <?= dash_h($initial) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="ad-title"><?= dash_h($adminName) ?></h1>
                    <div class="ad-subtitle"><?= dash_h($storeName) ?> &middot; Admin Dashboard</div>
                    <span class="ad-chip"><?= dash_h($businessType) ?></span>
                </div>
            </div>
            <div class="ad-range" aria-label="Dashboard period shortcuts">
                <?php foreach ($periodLabels as $key => $label): ?>
                    <a class="<?= $period === $key ? 'active' : '' ?>" href="?period=<?= dash_h($key) ?>"><?= dash_h($label) ?></a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="ad-metric-grid" aria-label="Key metrics">
            <?php
            $metricNote = 'Filtered by ' . $periodLabels[$period];
            $metrics = [
                ['Total Sales', dash_money($totalSales), $metricNote, 'fa-money-bill-wave'],
                ['Total Orders', number_format($totalOrders), $metricNote, 'fa-bag-shopping'],
                ['Menu Items Sold', number_format($menuItemsSold), $metricNote, 'fa-utensils'],
                ['Average Order Value', dash_money($avgOrderValue), $metricNote, 'fa-chart-line'],
                ['Active Staff', number_format($activeStaff), 'Current active staff', 'fa-users'],
            ];
            foreach ($metrics as $metric): ?>
                <article class="ad-metric">
                    <div>
                        <div class="ad-metric-head">
                            <div class="ad-metric-icon"><i class="fa-solid <?= dash_h($metric[3]) ?>"></i></div>
                            <div class="ad-metric-label"><?= dash_h($metric[0]) ?></div>
                        </div>
                        <div class="ad-metric-value"><?= $metric[1] ?></div>
                        <div class="ad-metric-note"><i class="fa-solid fa-filter"></i> <?= dash_h($metric[2]) ?></div>
                    </div>
                    <svg class="ad-sparkline" viewBox="0 0 160 34" preserveAspectRatio="none" aria-hidden="true">
                        <polyline points="0,25 16,20 32,16 48,22 64,19 80,14 96,24 112,12 128,15 144,23 160,17"></polyline>
                    </svg>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="ad-panel-grid">
            <article class="ad-panel">
                <div class="ad-panel-head">
                    <h2 class="ad-panel-title">Sales Overview</h2>
                    <span class="ad-period-tag"><?= dash_h($periodLabels[$period]) ?></span>
                </div>
                <div class="ad-bars" style="--bar-count: <?= $barCount ?>;">
                    <?php foreach ($salesOverview as $bar): ?>
                        <?php $barValue = (float)$bar['value']; ?>
                        <div class="ad-bar-wrap" title="<?= dash_h($bar['label']) ?> <?= dash_money($bar['value']) ?>">
                            <div class="ad-bar" style="height: <?= max(4, (int)(($barValue / $maxRevenue) * 118)) ?>px; opacity: <?= $barValue > 0 ? '1' : '.38' ?>;"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="ad-bar-labels" style="--bar-count: <?= $barCount ?>;">
                    <?php foreach ($salesOverview as $bar): ?><span><?= dash_h($bar['label']) ?></span><?php endforeach; ?>
                </div>
                <div class="ad-legend"><span class="ad-legend-dot"></span> Sales (PHP)</div>
            </article>

            <article class="ad-panel">
                <div class="ad-panel-head">
                    <h2 class="ad-panel-title">Orders by Type</h2>
                    <span class="ad-period-tag"><?= dash_h($periodLabels[$period]) ?></span>
                </div>
                <div class="ad-donut-layout">
                    <div class="ad-donut" style="background: <?= dash_h($donutGradient) ?>;">
                        <div class="ad-donut-center"><strong><?= number_format($paidOrders) ?></strong>Total Orders</div>
                    </div>
                    <div class="ad-type-list">
                        <?php if ($paymentRows): ?>
                            <?php foreach (array_slice($paymentRows, 0, 3) as $row):
                                $pct = $paymentTotal > 0 ? round(((int)$row['total'] / $paymentTotal) * 100) : 0;
                            ?>
                                <div class="ad-type-row">
                                    <span class="ad-type-dot"></span>
                                    <span><?= dash_h($row['label']) ?></span>
                                    <strong><?= $pct ?>% (<?= (int)$row['total'] ?>)</strong>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="ad-empty">No completed orders yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>

            <article class="ad-panel">
                <div class="ad-panel-head">
                    <h2 class="ad-panel-title">Top Selling Menu Items</h2>
                    <span class="ad-period-tag"><?= dash_h($periodLabels[$period]) ?></span>
                </div>
                <?php if ($topItems): ?>
                    <div class="ad-top-list">
                        <?php foreach ($topItems as $item):
                            $sold = (int)$item['total_sold'];
                            $width = max(8, (int)(($sold / max(1, $maxTopSold)) * 100));
                        ?>
                            <div class="ad-top-row">
                                <span><?= dash_h($item['item_name']) ?></span>
                                <div class="ad-progress"><span style="width: <?= $width ?>%;"></span></div>
                                <strong><?= $sold ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="ad-empty">No item sales yet.</div>
                <?php endif; ?>
            </article>
        </section>

        <section class="ad-strip" aria-label="Operational summary">
            <div class="ad-mini">
                <div class="ad-mini-icon"><i class="fa-solid fa-clock"></i></div>
                <div><div class="ad-mini-label">Pending Orders</div><div class="ad-mini-value"><?= $pendingOrders ?></div><div class="ad-mini-sub">Orders waiting</div></div>
            </div>
            <div class="ad-mini">
                <div class="ad-mini-icon"><i class="fa-solid fa-circle-xmark"></i></div>
                <div><div class="ad-mini-label">Cancelled Orders</div><div class="ad-mini-value"><?= $cancelledOrders ?></div><div class="ad-mini-sub">All time</div></div>
            </div>
            <div class="ad-mini">
                <div class="ad-mini-icon"><i class="fa-solid fa-book-open"></i></div>
                <div><div class="ad-mini-label">Total Menu Items</div><div class="ad-mini-value"><?= $totalMenu ?></div><div class="ad-mini-sub">Active in your menu</div></div>
            </div>
            <div class="ad-mini">
                <div class="ad-mini-icon"><i class="fa-solid fa-kitchen-set"></i></div>
                <div><div class="ad-mini-label">Online Kitchen Staff</div><div class="ad-mini-value"><?= $onlineKitchen ?> / <?= $totalKitchen ?></div><div class="ad-mini-sub">Currently active</div></div>
            </div>
            <div class="ad-mini">
                <div class="ad-mini-icon"><i class="fa-solid fa-cash-register"></i></div>
                <div><div class="ad-mini-label">Online Cashier Staff</div><div class="ad-mini-value"><?= $onlineCashier ?> / <?= $totalCashier ?></div><div class="ad-mini-sub">Currently active</div></div>
            </div>
        </section>

        <section class="ad-orders-row">
            <article class="ad-table-panel ad-orders-wide">
                <div class="ad-table-title"><i class="fa-solid fa-receipt"></i> Recent Orders</div>
                <?php if ($recentOrders): ?>
                    <table class="ad-orders-table">
                        <thead>
                            <tr><th>Queue #</th><th>Customer</th><th>Items</th><th>Amount</th><th>Status</th><th>Time</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentOrders as $order):
                            $statusClass = strtolower((string)$order['order_status']);
                        ?>
                            <tr>
                                <td><strong>#<?= dash_h($order['queue_number']) ?></strong></td>
                                <td><?= dash_h($order['customer_name'] ?: 'Guest') ?></td>
                                <td><?= (int)$order['item_count'] ?></td>
                                <td><strong><?= dash_money($order['total_amount']) ?></strong></td>
                                <td><span class="ad-status <?= dash_h($statusClass) ?>"><?= dash_h($order['order_status']) ?></span></td>
                                <td><?= dash_h(date('M d - h:i A', strtotime($order['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a class="ad-panel-link" href="order_history.php">View all orders <i class="fa-solid fa-arrow-right"></i></a>
                <?php else: ?>
                    <div class="ad-empty">No orders yet.</div>
                <?php endif; ?>
            </article>
        </section>

        <section class="ad-secondary-grid">
            <article class="ad-table-panel">
                <div class="ad-table-title"><i class="fa-solid fa-list-check"></i> Recent Activities</div>
                <?php if ($recentActivities): ?>
                    <div class="ad-activity-list">
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="ad-activity">
                                <div class="ad-activity-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                                <div>
                                    <div class="ad-activity-title"><?= dash_h(dash_action_title($activity)) ?></div>
                                    <div class="ad-activity-sub"><?= dash_h($activity['target_type'] ?: ($activity['detail'] ?: 'System activity')) ?></div>
                                </div>
                                <div class="ad-activity-time"><?= dash_h(date('g:i A', strtotime($activity['created_at']))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a class="ad-panel-link" href="activity_log.php">View all activities <i class="fa-solid fa-arrow-right"></i></a>
                <?php else: ?>
                    <div class="ad-empty">No activities yet.</div>
                    <a class="ad-panel-link" href="activity_log.php">View all activities <i class="fa-solid fa-arrow-right"></i></a>
                <?php endif; ?>
            </article>

            <article class="ad-table-panel">
                <div class="ad-table-title"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</div>
                <?php if ($lowStock): ?>
                    <div class="ad-stock-list">
                        <?php foreach ($lowStock as $item):
                            $qty = (int)$item['stock_quantity'];
                            $isOut = $qty <= 0;
                        ?>
                            <div class="ad-stock-row">
                                <div class="ad-stock-name">
                                    <strong><?= dash_h($item['item_name']) ?></strong>
                                    <span><?= dash_h($item['category'] ?: 'Uncategorized') ?></span>
                                </div>
                                <div class="ad-stock-count"><?= $qty ?></div>
                                <span class="ad-stock-badge <?= $isOut ? 'out' : 'low' ?>">
                                    <?= $isOut ? 'Out' : 'Low' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a class="ad-panel-link" href="menu_list.php">Manage inventory <i class="fa-solid fa-arrow-right"></i></a>
                <?php else: ?>
                    <div class="ad-empty">All available items are stocked.</div>
                    <a class="ad-panel-link" href="menu_list.php">Manage inventory <i class="fa-solid fa-arrow-right"></i></a>
                <?php endif; ?>
            </article>
        </section>
    </div>

    <?= $sidebar->renderClose() ?>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const main = document.getElementById('mainContent');
    if (!sidebar) return;
    const collapsed = sidebar.classList.toggle('sidebar-collapsed');
    if (main) main.classList.toggle('main-expanded', collapsed);
    localStorage.setItem('ipos_sidebar_collapsed', collapsed ? '1' : '0');
}

document.addEventListener('DOMContentLoaded', function () {
    const collapsed = localStorage.getItem('ipos_sidebar_collapsed') === '1';
    if (collapsed) {
        const sidebar = document.querySelector('.sidebar');
        const main = document.getElementById('mainContent');
        if (sidebar) sidebar.classList.add('sidebar-collapsed');
        if (main) main.classList.add('main-expanded');
    }

    const shown = sessionStorage.getItem('welcome_shown');
    if (!shown && window.Swal) {
        sessionStorage.setItem('welcome_shown', '1');
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Welcome back, <?= addslashes(dash_h($_SESSION['username'] ?? 'Admin')) ?>!',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });
    }
});
</script>
</body>
</html>
