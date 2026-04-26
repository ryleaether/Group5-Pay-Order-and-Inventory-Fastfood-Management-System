<?php
session_start();
require_once __DIR__ . "/helpers/admindashboard_helpers.php";
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$val  = new Validation();

/* CHECK IF ACCOUNT STILL EXISTS */
if (!$val->adminExists($_SESSION['admin_id'])) {
    // Account deleted, show message
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

// Sidebar renderer
$sidebar = new SidebarRenderer($admin_id, $_SESSION['fastfood_name'] ?? '');

/* =========================
   DEVICE COUNT (existing)
========================= */
$device_count = $val->countDevices($admin_id);

/* =========================
   TOTAL MENU ITEMS
========================= */
$total_menu = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM menu_items WHERE admin_id = :id");
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $total_menu = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $total_menu = 0; }

/* =========================
   TOTAL ORDERS
========================= */
$total_orders = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE admin_id = :id");
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $total_orders = 0; }

/* =========================
   PENDING / QUEUED ORDERS
========================= */
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

/* =========================
   TOTAL INCOME
========================= */
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

/* =========================
   TODAY'S INCOME
========================= */
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

/* =========================
   RECENT ORDERS (last 5)
========================= */
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

/* =========================
   LOW STOCK (stock <= 5)
========================= */
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

/* =========================
   TOP SELLING ITEMS (top 5)
========================= */
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
    <link rel="stylesheet" href="../design/admin.css">
    <style>
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #222;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 2px solid #ffcc00;
            display: inline-block;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-top: 25px;
        }
        .dash-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .dash-table th {
            background: linear-gradient(90deg, #ff0000, #ffcc00);
            color: white;
            padding: 9px 12px;
            text-align: left;
            font-weight: 500;
        }
        .dash-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f1f1;
            color: #444;
        }
        .dash-table tr:hover td { background: #fff8e6; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-queued    { background: #fff3cd; color: #856404; }
        .badge-preparing { background: #cff4fc; color: #0c5460; }
        .badge-served    { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled { background: #f8d7da; color: #842029; }
        .stock-low  { color: #dc3545; font-weight: 700; }
        .stock-zero { color: #dc3545; font-weight: 700; }
        .rank-num {
            display: inline-block;
            width: 24px; height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff0000, #ffcc00);
            color: white;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
            font-weight: 700;
            margin-right: 6px;
        }
        .empty-state {
            text-align: center;
            color: #aaa;
            padding: 20px;
            font-size: 13px;
        }
        .card.today {
            background: linear-gradient(135deg, #1a1a1a, #333);
            color: white;
            border: none;
        }
        .card.today h3,
        .card.today p { color: white; }
        @media (max-width: 900px) {
            .content-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <?= $sidebar->render('dashboard') ?>

    <!-- MAIN -->
    <div class="main">

        <!-- TOPBAR -->
        <div class="topbar">
            <h1>Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? '') ?> 👋</h1>
            <p class="subtitle"><?= htmlspecialchars($_SESSION['fastfood_name'] ?? '') ?> · Admin Dashboard</p>
        </div>

        <!-- STATS CARDS -->
        <div class="cards">

            <div class="card">
                <h3>Active Devices</h3>
                <p><?= $device_count ?></p>
            </div>

            <div class="card">
                <h3>Total Menu Items</h3>
                <p><?= $total_menu ?></p>
            </div>

            <div class="card">
                <h3>Total Orders</h3>
                <p><?= $total_orders ?></p>
            </div>

            <div class="card">
                <h3>Pending Orders</h3>
                <p><?= $pending_orders ?></p>
            </div>

            <div class="card income">
                <h3>Total Income</h3>
                <p>&#8369;<?= number_format($total_income, 2) ?></p>
            </div>

            <div class="card today">
                <h3>Today's Income</h3>
                <p>&#8369;<?= number_format($today_income, 2) ?></p>
            </div>

        </div>

        <!-- CONTENT GRID -->
        <div class="content-grid">

            <!-- RECENT ORDERS (spans full width) -->
            <div class="content-box" style="grid-column: 1 / -1;">
                <span class="section-title">🧾 Recent Orders</span>
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
                                <td>&#8369;<?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <span class="badge badge-<?= strtolower(htmlspecialchars($order['order_status'])) ?>">
                                        <?= htmlspecialchars($order['order_status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(date('M d, h:i A', strtotime($order['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">No orders yet. Orders will appear here once customers start placing them.</p>
                <?php endif; ?>
            </div>

            <!-- LOW STOCK ALERT -->
            <div class="content-box">
                <span class="section-title">⚠️ Low Stock Alert</span>
                <?php if (!empty($low_stock)): ?>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($low_stock as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td class="<?= $item['stock_quantity'] == 0 ? 'stock-zero' : 'stock-low' ?>">
                                    <?= $item['stock_quantity'] == 0 ? 'OUT OF STOCK' : $item['stock_quantity'] . ' left' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">&#10003; All items are well-stocked.</p>
                <?php endif; ?>
            </div>

            <!-- TOP SELLING ITEMS -->
            <div class="content-box">
                <span class="section-title">🏆 Top Selling Items</span>
                <?php if (!empty($top_items)): ?>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Units Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($top_items as $i => $item): ?>
                            <tr>
                                <td><span class="rank-num"><?= $i + 1 ?></span></td>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><strong><?= $item['total_sold'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">No sales data yet.</p>
                <?php endif; ?>
            </div>

        </div><!-- end content-grid -->

    </div><!-- end .main -->

</div><!-- end .dashboard -->


<!-- ACCOUNT MODAL -->
<div id="accountModal" class="modal">
    <div class="modal-content" style="max-width:420px; text-align:left;">
        <span class="close" onclick="document.getElementById('accountModal').classList.remove('show')">&times;</span>
        <h2 style="margin-bottom:12px;">👤 Edit Account</h2>
        <p style="color:#555; font-size:14px; margin-bottom:18px;">
            Update your username, email, and password here.
        </p>
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
        <h2 style="margin-bottom:6px;">Switch to User Dashboard</h2>
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
        <p style="color:#888; font-size:13px; margin-bottom:4px;" id="setupPinLabel">Enter a 4-digit PIN to protect the user dashboard</p>
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
            document.getElementById('setupPinLabel').textContent='Enter a 4-digit PIN to protect the user dashboard';
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
        if (result.success) {
            setTimeout(() => location.reload(), 900);
        }
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
document.addEventListener('click',function(e){ ['pinModal','setupPinModal','accountModal'].forEach(id=>{ const m=document.getElementById(id); if(m&&e.target===m)m.classList.remove('show'); }); });

function bindSidebarActions() {
    document.querySelectorAll('a[data-action="open-account"]').forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            openAccountModal();
        });
    });
    document.querySelectorAll('a[data-action="open-pin"]').forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            openPinModal();
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindSidebarActions);
} else {
    bindSidebarActions();
}

// Expose modal functions globally for sidebar anchor callbacks
window.openAccountModal = openAccountModal;
window.openPinModal = openPinModal;
window.submitAccountForm = submitAccountForm;
window.closeModal = closeModal;
</script>

</body>
</html>
