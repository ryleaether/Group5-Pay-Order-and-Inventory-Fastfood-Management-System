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

$val  = new Validation();

/* CHECK IF ACCOUNT STILL EXISTS */
if (!$val->adminExists($_SESSION['admin_id'])) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Account Deleted</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; }
            .modal-content { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
            button { padding: 10px 20px; background: #ff0000; color: white; border: none; border-radius: 5px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="modal">
            <div class="modal-content">
                <h2>Your account has been deleted</h2>
                <p>You will be redirected to the login page.</p>
                <button onclick="redirectToLogin()">OK</button>
            </div>
        </div>
        <script>
            function redirectToLogin() {
                window.location.href = "../login.php";
            }
        </script>
    </body>
    </html>';
    exit;
}

$db   = new Database();
$conn = $db->connect();

/* ── Maintenance mode check ── */
try {
    $maint_stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('maintenance_enabled','maintenance_end_time','maintenance_message')");
    $maint_rows = $maint_stmt->fetchAll(PDO::FETCH_ASSOC);
    $maint_map  = array_column($maint_rows, 'setting_value', 'setting_key');
} catch (Exception $e) { $maint_map = []; }

if (($maint_map['maintenance_enabled'] ?? '0') === '1') {
    session_write_close();
    header('Location: ../maintenance.php');
    exit;
}

/* ── Global announcement ── */
$ann_enabled = '0'; $ann_message = ''; $ann_type = 'info';
try {
    $ann_stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('announcement_enabled','announcement_message','announcement_type')");
    $ann_rows  = $ann_stmt->fetchAll(PDO::FETCH_ASSOC);
    $ann_map   = array_column($ann_rows, 'setting_value', 'setting_key');
    $ann_enabled = $ann_map['announcement_enabled'] ?? '0';
    $ann_message = $ann_map['announcement_message'] ?? '';
    $ann_type    = $ann_map['announcement_type']    ?? 'info';
} catch (Exception $e) {}

$admin_id = $_SESSION['admin_id'];

$adminProfile = [];
try {
    $stmt = $conn->prepare("SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id = :id");
    $stmt->bindParam(':id', $admin_id);
    $stmt->execute();
    $adminProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $adminProfile = [];
}

$sidebar = new SidebarRenderer(
    $admin_id,
    $_SESSION['fastfood_name'] ?? '',
    $adminProfile['fullname'] ?? $_SESSION['username'] ?? '',
    $adminProfile['username'] ?? $_SESSION['username'] ?? '',
    $adminProfile['email'] ?? ''
);

$device_count = $val->countDevices($admin_id);

$total_menu = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM menu_items WHERE admin_id = :id");
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $total_menu = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $total_menu = 0; }

$total_orders = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE admin_id = :id");
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $total_orders = 0; }

$pending_orders = 0;
try {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as total FROM orders
         WHERE admin_id = :id AND order_status IN ('Queued','Preparing')"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $pending_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $pending_orders = 0; }

$total_income = 0.00;
try {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(p.amount_paid), 0) as total
         FROM payments p
         JOIN orders o ON p.order_id = o.order_id
         WHERE o.admin_id = :id AND p.payment_status = 'Completed'"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $total_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00;
} catch (Exception $e) { $total_income = 0.00; }

$today_income = 0.00;
try {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(p.amount_paid), 0) as total
         FROM payments p
         JOIN orders o ON p.order_id = o.order_id
         WHERE o.admin_id = :id
           AND p.payment_status = 'Completed'
           AND DATE(p.payment_date) = CURDATE()"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $today_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00;
} catch (Exception $e) { $today_income = 0.00; }

$recent_orders = [];
try {
    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_status, o.total_amount,
                o.queue_number, o.created_at,
                c.name AS customer_name, c.table_number
         FROM orders o
         LEFT JOIN customers c ON o.customer_id = c.customer_id
         WHERE o.admin_id = :id
         ORDER BY o.created_at DESC
         LIMIT 5"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_orders = []; }

$low_stock = [];
try {
    $stmt = $conn->prepare(
        "SELECT item_name, stock_quantity, category
         FROM menu_items
         WHERE admin_id = :id AND stock_quantity <= 5 AND is_available = 1
         ORDER BY stock_quantity ASC
         LIMIT 5"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $low_stock = []; }

$top_items = [];
try {
    $stmt = $conn->prepare(
        "SELECT oi.item_name, SUM(oi.quantity) AS total_sold
         FROM order_items oi
         JOIN orders o ON oi.order_id = o.order_id
         WHERE o.admin_id = :id AND o.order_status != 'Cancelled'
         GROUP BY oi.item_name
         ORDER BY total_sold DESC
         LIMIT 5"
    );
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $top_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $top_items = []; }
?>

<!DOCTYPE html>
<html>
<head>
    <title>iPOS Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../design/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
</head>

<body>

<?php
// Show announcement modal once per login — track with PHP session key tied to message content
$ann_session_key = 'ann_seen_' . md5($ann_message);
$show_ann_modal  = ($ann_enabled === '1' && !empty($ann_message) && empty($_SESSION[$ann_session_key]));
if ($show_ann_modal) {
    $_SESSION[$ann_session_key] = true; // mark as seen for this login session
}
?>
<?php if ($show_ann_modal): ?>
<?php
$ann_colors = [
    'info'    => ['accent'=>'#3b82f6','icon'=>'ℹ️','label'=>'Information'],
    'warning' => ['accent'=>'#f59e0b','icon'=>'⚠️','label'=>'Important Notice'],
    'success' => ['accent'=>'#22c55e','icon'=>'✅','label'=>'Good News'],
    'danger'  => ['accent'=>'#ef4444','icon'=>'🚨','label'=>'Urgent Notice'],
];
$ac = $ann_colors[$ann_type] ?? $ann_colors['info'];
?>
<!-- ── Global Announcement Modal ── -->
<div id="ann-modal-overlay" style="
    position:fixed;inset:0;z-index:99999;
    background:rgba(0,0,0,0.6);
    backdrop-filter:blur(6px);
    -webkit-backdrop-filter:blur(6px);
    display:flex;align-items:center;justify-content:center;
    padding:20px;
    animation:annFadeIn 0.25s ease;">

    <div id="ann-modal-box" style="
        background:#fff;
        border-radius:22px;
        max-width:480px;width:100%;
        box-shadow:0 32px 80px rgba(0,0,0,0.35);
        overflow:hidden;
        animation:annSlideUp 0.35s cubic-bezier(0.34,1.56,0.64,1);">

        <!-- Colored header -->
        <div style="background:<?= $ac['accent'] ?>;padding:28px 24px 22px;text-align:center;position:relative;">
            <div style="font-size:44px;margin-bottom:8px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.15));"><?= $ac['icon'] ?></div>
            <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.9);"><?= $ac['label'] ?></div>
            <div style="font-size:18px;font-weight:700;color:#fff;margin-top:4px;">System Announcement</div>
        </div>

        <!-- Body -->
        <div style="padding:28px 30px 30px;text-align:center;">
            <p style="font-size:15px;font-weight:500;color:#1a1a2e;line-height:1.75;margin:0 0 26px;">
                <?= htmlspecialchars($ann_message) ?>
            </p>

            <!-- Dismiss button -->
            <button onclick="dismissAnnModal()" style="
                background:<?= $ac['accent'] ?>;
                color:#fff;border:none;border-radius:12px;
                padding:13px 0;font-size:14px;font-weight:600;
                cursor:pointer;font-family:'Poppins',sans-serif;
                width:100%;letter-spacing:0.3px;
                transition:transform 0.15s,opacity 0.15s;"
                onmouseover="this.style.opacity='0.88';this.style.transform='scale(1.01)'"
                onmouseout="this.style.opacity='1';this.style.transform='scale(1)'">
                Got it, thanks!
            </button>

            <p style="font-size:11.5px;color:#aaa;margin-top:12px;margin-bottom:0;">
                This message is from your system administrator.
            </p>
        </div>
    </div>
</div>

<style>
@keyframes annFadeIn  { from{opacity:0} to{opacity:1} }
@keyframes annSlideUp { from{transform:translateY(40px) scale(0.96);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }
</style>

<script>
function dismissAnnModal() {
    const overlay = document.getElementById('ann-modal-overlay');
    if (!overlay) return;
    overlay.style.transition = 'opacity 0.2s';
    overlay.style.opacity = '0';
    setTimeout(() => overlay.remove(), 220);
}
// Allow clicking the dark backdrop to dismiss too
document.getElementById('ann-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) dismissAnnModal();
});
</script>
<?php endif; ?>

<div class="dashboard" id="dashboardRoot">

    <!-- ═══════════════════════════════
         SIDEBAR
    ═══════════════════════════════ -->
    <?= $sidebar->render('dashboard') ?>
<?php echo '<!-- logo_url: ' . ($_SESSION['logo_url'] ?? 'NOT SET') . ' -->'; ?>
    <!-- ═══════════════════════════════
         MAIN WRAPPER
    ═══════════════════════════════ -->
   

        <!-- TOPBAR / WELCOME BANNER -->
<div class="topbar">
  <div style="display:flex; align-items:center; gap:18px;">
    <?php if (!empty($_SESSION['logo_url'])): ?>
      <?php $logo_src = '/' . ltrim($_SESSION['logo_url'], '/'); ?>
      <?php $radius = ($_SESSION['logo_shape'] ?? 'circle') === 'circle' ? '50%' : (($_SESSION['logo_shape'] ?? '') === 'rounded' ? '14px' : '4px'); ?>
      <img src="<?= htmlspecialchars($logo_src) ?>" alt="logo"
           style="width:80px;height:80px;object-fit:cover;border-radius:<?= $radius ?>;border:3px solid rgba(255,255,255,0.5);flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,0.2);">
    <?php endif; ?>
    <div>
      <h1><?= htmlspecialchars($adminProfile['fullname'] ?? $_SESSION['username'] ?? '') ?> <i class="fa-solid fa-hands-clapping" style="color:#f59e0b;"></i></h1>
      <p class="subtitle"><?= htmlspecialchars($_SESSION['fastfood_name'] ?? '') ?> · Admin Dashboard</p>
      <span style="font-size:0.72rem;color:rgba(255,255,255,0.6);background:rgba(255,255,255,0.12);padding:2px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:0.07em;margin-top:4px;display:inline-block;">
        <?= htmlspecialchars($_SESSION['business_type'] ?? 'Fast Food Store') ?>
      </span>
    </div>
  </div>
</div>

            <!-- STATS CARDS -->
            <div class="cards">

                <div class="card">
                    <div class="card-header">
                        <h3>Total Orders</h3>
                        <div class="card-icon"><i class="fa-solid fa-box-open"></i></div>
                    </div>
                    <div class="card-value">
                        <p><?= $total_orders ?></p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Total Income</h3>
                    <div class="card-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                    </div>
                    <div class="card-value">
                        <p>₱<?= number_format($total_income, 0) ?></p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Menu Items</h3>
                        <div class="card-icon"><i class="fa-solid fa-utensils"></i></div>
                    </div>
                    <div class="card-value">
                        <p><?= $total_menu ?></p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Pending Orders</h3>
                        <div class="card-icon"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <div class="card-value">
                        <p><?= $pending_orders ?></p>
                    </div>
                </div>

            </div>

            <!-- CONTENT GRID -->
            <div class="content-grid">

                <div class="content-box">
                  <span class="section-title"><i class="fa-solid fa-receipt"></i> Recent Orders</span>
                    <?php if (!empty($recent_orders)): ?>
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Queue #</th>
                                    <th>Customer</th>
                                    <th>Table</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($order['queue_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($order['customer_name'] ?: 'Guest') ?></td>
                                    <td><?= htmlspecialchars($order['table_number'] ?: '—') ?></td>
                                    <td><strong>₱<?= number_format($order['total_amount'], 2) ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower(htmlspecialchars($order['order_status'])) ?>">
                                            <?= htmlspecialchars($order['order_status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(date('M d • h:i A', strtotime($order['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php for ($i = count($recent_orders); $i < 5; $i++): ?>
                                <tr class="empty-row"><td colspan="6">&nbsp;</td></tr>
                            <?php endfor; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                  <p class="empty-state"><i class="fa-solid fa-inbox" style="margin-right:6px;"></i>No orders yet. Orders will appear here once customers start placing them.</p>
                    <?php endif; ?>
                </div>

                <div class="content-grid-row2">

                    <div class="content-box">
                       <span class="section-title"><i class="fa-solid fa-trophy"></i> Top Items</span>
                        <?php if (!empty($top_items)): ?>
                            <table class="dash-table">
                                <thead>
                                    <tr><th>#</th><th>Item</th><th>Sold</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($top_items as $i => $item): ?>
                                    <tr>
                                        <td><span class="rank-num"><?= $i + 1 ?></span></td>
                                        <td><?= htmlspecialchars(substr($item['item_name'], 0, 18)) ?></td>
                                        <td><strong><?= $item['total_sold'] ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                       <p class="empty-state"><i class="fa-solid fa-chart-simple" style="margin-right:6px;"></i>No data yet</p>
                        <?php endif; ?>
                    </div>

                    <div class="content-box">
                        <span class="section-title"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</span>
                        <?php if (!empty($low_stock)): ?>
                            <table class="dash-table">
                                <thead>
                                    <tr><th>Item</th><th>Qty</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($low_stock as $item): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars(substr($item['item_name'], 0, 20)) ?></strong></td>
                                        <td class="<?= $item['stock_quantity'] == 0 ? 'stock-zero' : 'stock-low' ?>">
                                            <?= $item['stock_quantity'] == 0 ? '0' : $item['stock_quantity'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                     <p class="empty-state"><i class="fa-solid fa-circle-check" style="margin-right:6px;"></i>Well stocked</p>
                        <?php endif; ?>
                    </div>

                </div>

            </div><!-- end content-grid -->

<?= $sidebar->renderClose() ?>
</div><!-- end .dashboard -->


<!-- ACCOUNT MODAL -->
<div id="accountModal" class="modal">
    <div class="modal-content" style="max-width:420px; text-align:left;">
        <span class="close" onclick="document.getElementById('accountModal').classList.remove('show')">&times;</span>
        <h2 style="margin-bottom:12px;">👤 Edit Account</h2>
        <p style="color:#555; font-size:14px; margin-bottom:18px;">Update your username, email, and password here.</p>
        <form id="accountForm" onsubmit="submitAccountForm(event)">
            <div style="display:grid; gap:12px;">
                <input type="text" name="fullname" placeholder="Full Name" value="<?= htmlspecialchars($adminProfile['fullname'] ?? '') ?>" required>
                <input type="text" name="fastfood_name" placeholder="Fastfood Name" value="<?= htmlspecialchars($adminProfile['fastfood_name'] ?? '') ?>" required>
                <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($adminProfile['username'] ?? '') ?>" required>
                <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($adminProfile['email'] ?? '') ?>" required>
                <input type="password" name="new_password" placeholder="New Password (leave blank to keep current)">
                <input type="password" name="confirm_password" placeholder="Confirm New Password">
                <div id="accountMessage" style="display:none; padding:12px; border-radius:10px; font-size:13px;"></div>
                <button type="submit" class="btn-save" style="width:100%;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- PIN MODAL -->
<div id="pinModal" class="modal">
    <div class="modal-content" style="max-width:340px; text-align:center;">
        <span class="close" onclick="document.getElementById('pinModal').classList.remove('show')">&times;</span>
        <div style="font-size:36px; margin-bottom:8px;">🔐</div>
        <h2 style="margin-bottom:6px;">Switch to Cashier Dashboard</h2>
        <p style="color:#888; font-size:13px; margin-bottom:20px;">Enter your dashboard PIN to continue</p>
        <div id="pinDots" style="display:flex; justify-content:center; gap:12px; margin-bottom:20px;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
        </div>
        <div class="pin-pad">
            <button type="button" class="pin-key" onclick="pinPress(1)">1</button>
            <button type="button" class="pin-key" onclick="pinPress(2)">2</button>
            <button type="button" class="pin-key" onclick="pinPress(3)">3</button>
            <button type="button" class="pin-key" onclick="pinPress(4)">4</button>
            <button type="button" class="pin-key" onclick="pinPress(5)">5</button>
            <button type="button" class="pin-key" onclick="pinPress(6)">6</button>
            <button type="button" class="pin-key" onclick="pinPress(7)">7</button>
            <button type="button" class="pin-key" onclick="pinPress(8)">8</button>
            <button type="button" class="pin-key" onclick="pinPress(9)">9</button>
            <button type="button" class="pin-key" style="visibility:hidden;"></button>
            <button type="button" class="pin-key" onclick="pinPress(0)">0</button>
            <button type="button" class="pin-key" onclick="pinBackspace()">⌫</button>
        </div>
        <p id="pinError" style="color:#dc3545; font-size:13px; margin-top:10px; display:none;">Incorrect PIN. Try again.</p>
    </div>
</div>

<!-- SETUP PIN MODAL -->
<div id="setupPinModal" class="modal">
    <div class="modal-content" style="max-width:340px; text-align:center;">
        <span class="close" onclick="document.getElementById('setupPinModal').classList.remove('show')">&times;</span>
        <div style="font-size:36px; margin-bottom:8px;">🔑</div>
        <h2 style="margin-bottom:6px;">Set Up Dashboard PIN</h2>
        <p style="color:#888; font-size:13px; margin-bottom:4px;" id="setupPinLabel">Enter a 4-digit PIN to protect the Cashier Dashboard</p>
        <div id="setupPinDots" style="display:flex; justify-content:center; gap:12px; margin-bottom:20px; margin-top:14px;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
        </div>
        <div class="pin-pad">
            <button type="button" class="pin-key" onclick="setupPinPress(1)">1</button>
            <button type="button" class="pin-key" onclick="setupPinPress(2)">2</button>
            <button type="button" class="pin-key" onclick="setupPinPress(3)">3</button>
            <button type="button" class="pin-key" onclick="setupPinPress(4)">4</button>
            <button type="button" class="pin-key" onclick="setupPinPress(5)">5</button>
            <button type="button" class="pin-key" onclick="setupPinPress(6)">6</button>
            <button type="button" class="pin-key" onclick="setupPinPress(7)">7</button>
            <button type="button" class="pin-key" onclick="setupPinPress(8)">8</button>
            <button type="button" class="pin-key" onclick="setupPinPress(9)">9</button>
            <button type="button" class="pin-key" style="visibility:hidden;"></button>
            <button type="button" class="pin-key" onclick="setupPinPress(0)">0</button>
            <button type="button" class="pin-key" onclick="setupPinBackspace()">⌫</button>
        </div>
        <p id="setupPinError" style="color:#dc3545; font-size:13px; margin-top:10px; display:none;"></p>
    </div>
</div>

<script>
let pinValue = '';
function openPinModal() {
    fetch('helpers/admindashboard_helpers.php?action=check_pin').then(r=>r.json()).then(data=>{
        if(data.has_pin){
            pinValue=''; updatePinDots('pinDots',0);
            document.getElementById('pinError').style.display='none';
            document.getElementById('pinModal').classList.add('show');
        } else {
            setupPinStep=1; setupPinFirst=''; setupPinCurrent='';
            updatePinDots('setupPinDots',0);
            document.getElementById('setupPinLabel').textContent='Enter a 4-digit PIN to protect the Cashier Dashboard';
            document.getElementById('setupPinError').style.display='none';
            document.getElementById('setupPinModal').classList.add('show');
        }
    });
}
function openAccountModal() {
    const form = document.getElementById('accountForm');
    form.reset();
    document.getElementById('accountMessage').style.display = 'none';
    form.querySelector('[name="fullname"]').value = <?= json_encode($adminProfile['fullname'] ?? '') ?>;
    form.querySelector('[name="fastfood_name"]').value = <?= json_encode($adminProfile['fastfood_name'] ?? '') ?>;
    form.querySelector('[name="username"]').value = <?= json_encode($adminProfile['username'] ?? '') ?>;
    form.querySelector('[name="email"]').value = <?= json_encode($adminProfile['email'] ?? '') ?>;
    document.getElementById('accountModal').classList.add('show');
}
function submitAccountForm(event) {
    event.preventDefault();
    const form = document.getElementById('accountForm');
    const message = document.getElementById('accountMessage');
    const data = new URLSearchParams(new FormData(form));
    fetch('helpers/admindashboard_helpers.php?action=update_account', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
    })
    .then(r => r.json())
    .then(result => {
        message.style.display = 'block';
        message.textContent = result.message;
        message.style.background = result.success ? '#e6ffed' : '#ffe6e6';
        message.style.color = result.success ? '#1f7a3c' : '#9b1f1f';
        message.style.border = result.success ? '1px solid #8cd19e' : '1px solid #ea9a9a';
        if (result.success) { setTimeout(() => location.reload(), 900); }
    });
}
function pinPress(num){ if(pinValue.length>=4)return; pinValue+=num; updatePinDots('pinDots',pinValue.length); if(pinValue.length===4)verifyPin(); }
function pinBackspace(){ pinValue=pinValue.slice(0,-1); updatePinDots('pinDots',pinValue.length); }
function verifyPin(){
    fetch('helpers/admindashboard_helpers.php?action=check_pin',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'pin='+encodeURIComponent(pinValue)})
    .then(r=>r.json()).then(data=>{
        if(data.success){ window.location.href='userdashboard.php'; }
        else { document.getElementById('pinError').style.display='block'; pinValue=''; updatePinDots('pinDots',0); const d=document.getElementById('pinDots'); d.classList.add('pin-shake'); setTimeout(()=>d.classList.remove('pin-shake'),500); }
    });
}
let setupPinStep=1, setupPinFirst='', setupPinCurrent='';
function setupPinPress(num){ if(setupPinCurrent.length>=4)return; setupPinCurrent+=num; updatePinDots('setupPinDots',setupPinCurrent.length); if(setupPinCurrent.length===4){ setTimeout(()=>{ if(setupPinStep===1){ setupPinFirst=setupPinCurrent; setupPinCurrent=''; setupPinStep=2; document.getElementById('setupPinLabel').textContent='Confirm your PIN'; updatePinDots('setupPinDots',0); } else { if(setupPinCurrent===setupPinFirst){ savePin(setupPinCurrent); } else { document.getElementById('setupPinError').textContent="PINs don\'t match. Try again."; document.getElementById('setupPinError').style.display='block'; setupPinCurrent=''; setupPinFirst=''; setupPinStep=1; document.getElementById('setupPinLabel').textContent='Enter a 4-digit PIN'; updatePinDots('setupPinDots',0); } } },150); } }
function setupPinBackspace(){ setupPinCurrent=setupPinCurrent.slice(0,-1); updatePinDots('setupPinDots',setupPinCurrent.length); }
function savePin(pin){ fetch('helpers/admindashboard_helpers.php?action=save_pin',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'pin='+encodeURIComponent(pin)}).then(r=>r.json()).then(data=>{ if(data.success){ document.getElementById('setupPinModal').classList.remove('show'); window.location.href='userdashboard.php'; } }); }
function updatePinDots(id,count){ document.querySelectorAll('#'+id+' .pin-dot').forEach((d,i)=>d.classList.toggle('filled',i<count)); }

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
document.addEventListener('DOMContentLoaded', function() {
    const collapsed = localStorage.getItem('ipos_sidebar_collapsed') === '1';
    if (collapsed) {
        const sidebar = document.querySelector('.sidebar');
        const main    = document.getElementById('mainContent');
        if (sidebar) sidebar.classList.add('sidebar-collapsed');
        if (main)    main.classList.add('main-expanded');
    }
});

document.addEventListener('click',function(e){ ['pinModal','setupPinModal','accountModal'].forEach(id=>{ const m=document.getElementById(id); if(m&&e.target===m)m.classList.remove('show'); }); });

function bindSidebarActions() {
    document.querySelectorAll('a[data-action="open-account"]').forEach(link => {
        link.addEventListener('click', function(event) { event.preventDefault(); window.location.href = 'account_pin_gate.php'; });
    });
    document.querySelectorAll('a[data-action="open-pin"]').forEach(link => {
        link.addEventListener('click', function(event) { event.preventDefault(); openPinModal(); });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindSidebarActions);
} else {
    bindSidebarActions();
}

window.openAccountModal = openAccountModal;
window.openPinModal = openPinModal;
window.submitAccountForm = submitAccountForm;

// Welcome back SweetAlert2 toast
document.addEventListener('DOMContentLoaded', function() {
    const shown = sessionStorage.getItem('welcome_shown');
    if (!shown) {
        sessionStorage.setItem('welcome_shown', '1');
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Welcome back, <?= addslashes(htmlspecialchars($_SESSION['username'] ?? '')) ?>!',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
    }
});

/* ================================================================
   BACK-BUTTON PREVENTION
   Push a dummy state so the browser back button triggers popstate
   instead of actually navigating away. When triggered, show the
   PIN gate overlay so the user must authenticate first.
================================================================ */
history.pushState({ page: 'admin' }, '', window.location.href);
window.addEventListener('pageshow', function() {
    if (window.__iposLoggingOut || sessionStorage.getItem('ipos_logging_out') === '1') {
        window.location.replace('../login.php');
    }
});
window.addEventListener('popstate', function(e) {
    if (window.__iposLoggingOut || sessionStorage.getItem('ipos_logging_out') === '1') {
        return;
    }
    // Re-push so back keeps being intercepted
    history.pushState({ page: 'admin' }, '', window.location.href);
    // Show the back button PIN gate overlay
    document.getElementById('backButtonPinGate').style.display = 'flex';
    backButtonPin = '';
    updateBackButtonPinDots(0);
    document.getElementById('backButtonPinError').style.display = 'none';
});

let backButtonPin = '';
function backButtonPinPress(num) {
    if (backButtonPin.length >= 4) return;
    backButtonPin += String(num);
    updateBackButtonPinDots(backButtonPin.length);
    if (backButtonPin.length === 4) setTimeout(verifyBackButtonPin, 100);
}
function backButtonPinBackspace() {
    backButtonPin = backButtonPin.slice(0, -1);
    updateBackButtonPinDots(backButtonPin.length);
}
function updateBackButtonPinDots(n) {
    document.querySelectorAll('#backButtonPinDots .pin-dot').forEach((d, i) => d.classList.toggle('filled', i < n));
}
function verifyBackButtonPin() {
    fetch('helpers/admindashboard_helpers.php?action=check_pin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'pin=' + encodeURIComponent(backButtonPin)
    }).then(r => r.json()).then(data => {
        if (data.success) {
            document.getElementById('backButtonPinGate').style.display = 'none';
            backButtonPin = '';
        } else {
            document.getElementById('backButtonPinError').style.display = 'block';
            backButtonPin = '';
            updateBackButtonPinDots(0);
            const dots = document.getElementById('backButtonPinDots');
            dots.classList.add('pin-shake');
            setTimeout(() => dots.classList.remove('pin-shake'), 500);
        }
    });
}

document.addEventListener('keydown', function(e) {
    if (document.getElementById('backButtonPinGate').style.display === 'flex') {
        if (e.key >= '0' && e.key <= '9') backButtonPinPress(parseInt(e.key));
        if (e.key === 'Backspace') backButtonPinBackspace();
    }
});
</script>

<!-- BACK BUTTON PIN GATE MODAL -->
<div id="backButtonPinGate" style="position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); z-index:9999; display:none; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:24px; padding:36px 32px; width:340px; text-align:center; box-shadow:0 24px 80px rgba(0,0,0,0.3); animation:pinPop 0.3s ease;">
        <div style="font-size:44px; margin-bottom:10px;">🔐</div>
        <h2 style="font-size:1.2rem; font-weight:800; color:#2d0a1f; margin-bottom:6px;">Admin Access Required</h2>
        <p style="font-size:13px; color:#888; margin-bottom:22px;">Enter your 4-digit PIN to continue.<br>Default PIN is <strong>0000</strong>.</p>
        <div id="backButtonPinDots" style="display:flex; justify-content:center; gap:14px; margin-bottom:22px;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
        </div>
        <div class="pin-pad">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                <button type="button" class="pin-key"
                    onclick="<?= $k==='⌫' ? 'backButtonPinBackspace()' : ($k==='' ? '' : "backButtonPinPress($k)") ?>">
                    <?= $k ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="backButtonPinError" style="color:#dc2626; font-size:13px; margin-top:12px; display:none;">Incorrect PIN. Try again.</p>
        <button onclick="document.getElementById('backButtonPinGate').style.display='none'; backButtonPin=''; updateBackButtonPinDots(0);"
                style="margin-top:14px; background:none; border:1.5px solid var(--border-color); padding:8px 24px; border-radius:8px; cursor:pointer; font-size:13px; color:var(--text-secondary); font-family:inherit;">
            Cancel
        </button>
    </div>
</div>

</body>
</html>
