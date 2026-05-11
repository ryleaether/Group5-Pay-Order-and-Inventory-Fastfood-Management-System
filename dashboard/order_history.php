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
           GROUP_CONCAT(oi.item_name, ' ×', oi.quantity ORDER BY oi.item_name SEPARATOR ', ') AS items_summary
    FROM orders o
    LEFT JOIN customers c    ON o.customer_id  = c.customer_id
    LEFT JOIN payments p     ON p.order_id     = o.order_id
    LEFT JOIN order_items oi ON oi.order_id    = o.order_id
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

$sidebar = new SidebarRenderer($admin_id, $_SESSION['fastfood_name'] ?? '');
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

    <div class="main">

        <div class="topbar">
            <h1>🧾 Orders History</h1>
            <p class="subtitle">All orders — including cancelled</p>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="cards" style="margin-top:20px; grid-template-columns: repeat(3, 1fr);">
            <div class="card income" style="min-height:unset; background:linear-gradient(135deg,var(--accent-dark),var(--accent)); border:none;">
                <div class="card-header">
                    <h3 style="color:rgba(255,255,255,0.75);">Total Income</h3>
                    <div class="card-icon">💰</div>
                </div>
                <div class="card-value">
                    <p style="color:#fff;">₱<?= number_format($summary['total_income'], 2) ?></p>
                </div>
            </div>
            <div class="card" style="min-height:unset; background:linear-gradient(135deg,var(--accent-dark),var(--accent)); border:none;">
                <div class="card-header">
                    <h3 style="color:rgba(255,255,255,0.75);">Completed Orders</h3>
                    <div class="card-icon">✅</div>
                </div>
                <div class="card-value">
                    <p style="color:#fff;"><?= $summary['total_orders'] ?></p>
                </div>
            </div>
            <div class="card" style="min-height:unset; background:linear-gradient(135deg,var(--accent-dark),var(--accent)); border:none;">
                <div class="card-header">
                    <h3 style="color:rgba(255,255,255,0.75);">Orders Shown</h3>
                    <div class="card-icon">📋</div>
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
                    <span class="filter-label">📅 Period</span>
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
                    <span class="filter-label">🔍 Status</span>
                    <?php $statuses = ['all' => 'All Orders', 'completed' => '✅ Completed', 'cancelled' => '✕ Cancelled']; ?>
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
                <p class="empty-state">📭 No orders found for the selected filters.</p>
            <?php else: ?>
                <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="dash-table" style="table-layout:auto; min-width:820px;">
                    <thead>
                        <tr>
                            <th style="white-space:nowrap;">Queue #</th>
                            <th style="min-width:180px;">Items</th>
                            <th style="white-space:nowrap;">Total</th>
                            <th style="white-space:nowrap;">Cash Paid</th>
                            <th style="white-space:nowrap;">Change</th>
                            <th style="white-space:nowrap;">Receipt</th>
                            <th style="white-space:nowrap;">Status</th>
                            <th style="white-space:nowrap;">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?= htmlspecialchars($order['queue_number']) ?></strong></td>
                            <td style="font-size:12px; color: var(--text-secondary); max-width:200px; word-break:break-word; white-space:normal;">
                                <?= htmlspecialchars($order['items_summary'] ?? '—') ?>
                            </td>
                            <td style="white-space:nowrap;"><strong>₱<?= number_format($order['total_amount'], 2) ?></strong></td>
                            <td style="white-space:nowrap;">₱<?= number_format($order['amount_paid'] ?? 0, 2) ?></td>
                            <td style="white-space:nowrap;">₱<?= number_format($order['change_given'] ?? 0, 2) ?></td>
                            <td style="font-size:11px; color: var(--text-secondary); white-space:nowrap;">
                                <?= htmlspecialchars($order['receipt_number'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= strtolower($order['order_status']) ?>">
                                    <?= htmlspecialchars($order['order_status']) ?>
                                </span>
                            </td>
                            <td style="font-size:12px; color: var(--text-secondary); white-space:nowrap;">
                                <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ================= PIN MODALS (required by sidebar) ================= -->
<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

</body>
</html>