<?php
session_start();
require_once __DIR__ . "/helpers/admindashboard_helpers.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$db       = new Database();
$conn     = $db->connect();

function ensureOrderStaffColumns(PDO $conn): void {
    try {
        $chk = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'cashier_staff_id'");
        if ((int)$chk->fetchColumn() === 0) {
            $conn->exec("ALTER TABLE orders ADD COLUMN cashier_staff_id INT NULL AFTER queue_number");
        }

        $chk = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'cashier_name'");
        if ((int)$chk->fetchColumn() === 0) {
            $conn->exec("ALTER TABLE orders ADD COLUMN cashier_name VARCHAR(100) NULL AFTER cashier_staff_id");
        }

        $chk = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'kitchen_staff_id'");
        if ((int)$chk->fetchColumn() === 0) {
            $conn->exec("ALTER TABLE orders ADD COLUMN kitchen_staff_id INT NULL AFTER cashier_name");
        }

        $chk = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'kitchen_name'");
        if ((int)$chk->fetchColumn() === 0) {
            $conn->exec("ALTER TABLE orders ADD COLUMN kitchen_name VARCHAR(100) NULL AFTER kitchen_staff_id");
        }
    } catch (Exception $e) {}
}
ensureOrderStaffColumns($conn);

/* ── FILTERS ── */
$period = $_GET['period'] ?? 'all';
$status = $_GET['status'] ?? 'all';

$date_condition = '';
switch ($period) {
    case 'today': $date_condition = "AND DATE(o.created_at) = CURDATE()"; break;
    case 'week':  $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
    case 'month': $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
}

$status_condition = '';
if ($status === 'completed')  $status_condition = "AND o.order_status = 'Served'";
if ($status === 'cancelled')  $status_condition = "AND o.order_status = 'Cancelled'";

/* ── FETCH ORDERS ── */
$stmt = $conn->prepare("
    SELECT o.order_id, o.queue_number, o.order_status, o.total_amount, o.created_at,
           c.table_number, c.name AS customer_name,
           p.receipt_number, p.amount_paid, p.change_given, p.payment_status,
           COALESCE(o.cashier_name, s.fullname) AS cashier_name,
           COALESCE(o.kitchen_name, ks.fullname) AS kitchen_name,
           GROUP_CONCAT(oi.item_name, ' ×', oi.quantity ORDER BY oi.item_name SEPARATOR ', ') AS items_summary
    FROM orders o
    LEFT JOIN customers c    ON o.customer_id  = c.customer_id
    LEFT JOIN payments p     ON p.order_id     = o.order_id
    LEFT JOIN order_items oi ON oi.order_id    = o.order_id
    LEFT JOIN staffs s       ON s.staff_id      = o.cashier_staff_id
    LEFT JOIN staffs ks      ON ks.staff_id     = o.kitchen_staff_id
    WHERE o.admin_id = :admin_id
    $date_condition
    $status_condition
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
$stmt->bindParam(":admin_id", $admin_id);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── TOTAL INCOME (served orders only, filtered period) ── */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(p.amount_paid), 0) AS total_income,
           COUNT(o.order_id) AS total_orders
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.order_id
    WHERE o.admin_id = :admin_id
      AND o.order_status = 'Served'
      AND p.payment_status = 'Completed'
    $date_condition
");
$stmt->bindParam(":admin_id", $admin_id);
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

try {
    $db_p  = new Database();
    $conn_p = $db_p->connect();
    $stmt_p = $conn_p->prepare("SELECT fullname FROM admins WHERE admin_id = :id");
    $stmt_p->execute([':id' => $admin_id]);
    $adminProfile = $stmt_p->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $adminProfile = []; }

$sidebar = new SidebarRenderer($admin_id, $_SESSION['fastfood_name'] ?? '', $adminProfile['fullname'] ?? $_SESSION['username'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Orders History</title>
    <link rel="stylesheet" href="../design/admin.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <style>
        .filter-tab {
            background: var(--card-bg);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .filter-tab:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .filter-tab.active {
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: white;
            border-color: var(--accent);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        .dash-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .dash-table th {
            background: linear-gradient(90deg, var(--accent-dark), var(--accent));
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .dash-table td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .dash-table tbody tr {
            transition: all 0.2s ease;
        }
        .dash-table tbody tr:hover {
            background: var(--accent-light);
            box-shadow: inset 0 0 0 1px var(--border-color);
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .badge-queued    { background: #fff3cd; color: #856404; }
        .badge-preparing { background: #cff4fc; color: #0c5460; }
        .badge-served    { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled { background: #f8d7da; color: #842029; }
        .empty-state {
            text-align: center;
            color: var(--text-secondary);
            padding: 50px 20px;
            font-size: 14px;
        }
        .content-box {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: box-shadow 0.3s ease;
        }
        .content-box:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .filter-section {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            padding: 18px 22px;
        }
        .filter-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-right: 4px;
        }
        .card.income {
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: white;
            border: none;
        }
        .card.income h3,
        .card.income p { color: white; }
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
    </style>
</head>
<body>

<div class="dashboard">

    <?= $sidebar->render('history') ?>

        <div class="topbar">
            <h1><i class="fa-solid fa-receipt" style="margin-right:8px;"></i>Orders History</h1>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="cards" style="margin-top:20px; grid-template-columns: repeat(3, 1fr);">
            <div class="card income" style="min-height:unset; background:linear-gradient(135deg,var(--accent-dark),var(--accent)); border:none;">
                <div class="card-header">
                    <h3 style="color:rgba(255,255,255,0.75);">Total Income</h3>
<div class="card-icon" style="background:rgba(255,255,255,0.15);border-radius:12px;width:44px;height:44px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-peso-sign" style="color:#fff;font-size:20px;"></i></div>
                </div>
                <div class="card-value">
                    <p style="color:#fff;">₱<?= number_format($summary['total_income'], 2) ?></p>
                </div>
            </div>
            <div class="card" style="min-height:unset; background:linear-gradient(135deg,var(--accent-dark),var(--accent)); border:none;">
                <div class="card-header">
                    <h3 style="color:rgba(255,255,255,0.75);">Completed Orders</h3>
<div class="card-icon" style="background:rgba(255,255,255,0.15);border-radius:12px;width:44px;height:44px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-circle-check" style="color:#fff;font-size:20px;"></i></div>
                </div>
                <div class="card-value">
                    <p style="color:#fff;"><?= $summary['total_orders'] ?></p>
                </div>
            </div>
            <div class="card" style="min-height:unset; background:linear-gradient(135deg,var(--accent-dark),var(--accent)); border:none;">
                <div class="card-header">
                    <h3 style="color:rgba(255,255,255,0.75);">Orders Shown</h3>
                   <div class="card-icon" style="background:rgba(255,255,255,0.15);border-radius:12px;width:44px;height:44px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-clipboard-list" style="color:#fff;font-size:20px;"></i></div>
                </div>
                <div class="card-value">
                    <p style="color:#fff;"><?= count($orders) ?></p>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="content-box" style="margin-top:20px;">
            <div class="filter-section">

                <div class="filter-group">
                    <span class="filter-label"><i class="fa-solid fa-calendar" style="margin-right:4px;"></i>Period</span>
                    <?php $periods = ['all' => 'All Time', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month']; ?>
                    <?php foreach ($periods as $val => $label): ?>
                        <a href="?period=<?= $val ?>&status=<?= htmlspecialchars($status) ?>"
                            class="filter-tab <?= $period === $val ? 'active' : '' ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div style="width: 1px; height: 24px; background: var(--border-color); margin: 0 6px;"></div>

                <div class="filter-group">
                    <span class="filter-label"><i class="fa-solid fa-magnifying-glass" style="margin-right:4px;"></i>Status</span>
                    <?php $statuses = ['all' => 'All Orders', 'completed' => '<i class="fa-solid fa-circle-check" style="margin-right:4px;"></i>Completed','cancelled' => '<i class="fa-solid fa-xmark" style="margin-right:4px;"></i>Cancelled']; ?>
                    <?php foreach ($statuses as $val => $label): ?>
                        <a href="?period=<?= htmlspecialchars($period) ?>&status=<?= $val ?>"
                            class="filter-tab <?= $status === $val ? 'active' : '' ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>

        <!-- ORDERS TABLE -->
        <div class="content-box" style="margin-top:16px; overflow:hidden;">
            <?php if (empty($orders)): ?>
                <p class="empty-state"><i class="fa-solid fa-box-open" style="margin-right:6px;"></i>No orders found for the selected filters.</p>
            <?php else: ?>
                <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="dash-table" style="table-layout:auto; min-width:1040px;">
                    <thead>
                        <tr>
                            <th style="white-space:nowrap;text-align:center;">Queue #</th>
                            <th style="min-width:180px;text-align:center;">Items</th>
                            <th style="white-space:nowrap;text-align:center;">Total</th>
                            <th style="white-space:nowrap;text-align:center;">Cash Paid</th>
                            <th style="white-space:nowrap;text-align:center;">Change</th>
                            <th style="white-space:nowrap;text-align:center;">Receipt</th>
                            <th style="white-space:nowrap;text-align:center;">Cashier</th>
                            <th style="white-space:nowrap;text-align:center;">Kitchen Manager</th>
                            <th style="white-space:nowrap;text-align:center;">Status</th>
                            <th style="white-space:nowrap;text-align:center;">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td style="text-align:center;"><strong>#<?= htmlspecialchars($order['queue_number']) ?></strong></td>
                            <td style="font-size:12px; color: var(--text-secondary); max-width:200px; word-break:break-word; white-space:normal;text-align:center;">
                                <?= htmlspecialchars($order['items_summary'] ?? '—') ?>
                            </td>
                            <td style="white-space:nowrap;text-align:center;"><strong>₱<?= number_format($order['total_amount'], 2) ?></strong></td>
                            <td style="white-space:nowrap;text-align:center;">₱<?= number_format($order['amount_paid'] ?? 0, 2) ?></td>
                            <td style="white-space:nowrap;text-align:center;">₱<?= number_format($order['change_given'] ?? 0, 2) ?></td>
                            <td style="font-size:11px; color: var(--text-secondary); white-space:nowrap;text-align:center;">
                                <?= htmlspecialchars($order['receipt_number'] ?? '—') ?>
                            </td>
                            <td style="font-size:12px; color: var(--text-secondary); white-space:nowrap;text-align:center;">
                                <?= htmlspecialchars($order['cashier_name'] ?: 'Before tracking') ?>
                            </td>
                            <td style="font-size:12px; color: var(--text-secondary); white-space:nowrap;text-align:center;">
                                <?= in_array($order['order_status'], ['Served', 'Completed'], true) ? htmlspecialchars($order['kitchen_name'] ?: 'Before tracking') : 'Not served yet' ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-<?= strtolower($order['order_status']) ?>">
                                    <?= htmlspecialchars($order['order_status']) ?>
                                </span>
                            </td>
                            <td style="font-size:12px; color: var(--text-secondary); white-space:nowrap;text-align:center;">
                                <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

   <?= $sidebar->renderClose() ?>
</div><!-- end .dashboard -->

<!-- ================= PIN MODALS (required by sidebar) ================= -->
<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

<script>
/* ── SIDEBAR TOGGLE ── */
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const main    = document.getElementById('mainContent');
    if (!sidebar) return;
    const collapsed = sidebar.classList.toggle('sidebar-collapsed');
    if (main) main.classList.toggle('main-expanded', collapsed);
    localStorage.setItem('ipos_sidebar_collapsed', collapsed ? '1' : '0');
}

/* Restore sidebar state on page load */
document.addEventListener('DOMContentLoaded', function () {
    const collapsed = localStorage.getItem('ipos_sidebar_collapsed') === '1';
    if (collapsed) {
        const sidebar = document.querySelector('.sidebar');
        const main    = document.getElementById('mainContent');
        if (sidebar) sidebar.classList.add('sidebar-collapsed');
        if (main)    main.classList.add('main-expanded');
    }
});
</script>

</body>
</html>
