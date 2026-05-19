<?php
session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/helpers/admindashboard_helpers.php";
require_once __DIR__ . "/../validation.php";

// Allow access from admin session OR staff session (Kitchen role)
$via_staff = false;
if (isset($_SESSION['staff_id']) && $_SESSION['staff_role'] === 'Kitchen' && isset($_SESSION['staff_admin'])) {
    $via_staff = true;
    $admin_id  = (int)$_SESSION['staff_admin'];
} elseif (isset($_SESSION['admin_id'])) {
    $admin_id  = $_SESSION['admin_id'];
    if (empty($_SESSION['kitchen_pin_unlocked_at']) && empty($_SESSION['staff_gate_unlocked'])) {
        header("Location: staff_gate.php");
        exit;
    }
} else {
    header("Location: ../login.php");
    exit;
}

$db   = new Database();
$conn = $db->connect();

$adminProfile = [];
try {
    $stmt = $conn->prepare("SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id = :id");
    $stmt->bindParam(':id', $admin_id);
    $stmt->execute();
    $adminProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $adminProfile = []; }

$sidebar = new SidebarRenderer(
    $admin_id,
    $_SESSION['fastfood_name'] ?? '',
    $adminProfile['fullname'] ?? 'Admin',
    $adminProfile['username'] ?? '',
    $adminProfile['email'] ?? ''
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Manager — iPOS</title>
    <link rel="stylesheet" href="../design/admin.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .km-back-btn {
            padding: 7px 16px;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            color: white;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .km-back-btn:hover { background: rgba(255,255,255,0.32); }
    </style>
    <style>
        /* ===== KITCHEN DASHBOARD STYLES ===== */
        .km-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .km-stats-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .km-stat {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 150px;
            box-shadow: var(--shadow-sm);
        }
        .km-stat-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .km-stat-icon.pending { background: #fef3c7; }
        .km-stat-icon.preparing { background: #dbeafe; }
        .km-stat-icon.done { background: #dcfce7; }
        .km-stat-icon.cancelled { background: #fee2e2; }
        .km-stat-val { font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1; }
        .km-stat-label { font-size: 11px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Columns layout */
        .km-columns {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }
        .km-column {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .km-column-header {
            padding: 14px 18px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .km-col-pending .km-column-header   { background: #fef3c7; color: #92400e; border-bottom: 2px solid #fcd34d; }
        .km-col-preparing .km-column-header { background: #dbeafe; color: #1e40af; border-bottom: 2px solid #93c5fd; }
        .km-col-done .km-column-header      { background: #dcfce7; color: #166534; border-bottom: 2px solid #86efac; }

        .km-col-count {
            background: rgba(0,0,0,0.1);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 12px;
            font-weight: 800;
        }
        .km-col-body {
            flex: 1;
            padding: 12px;
            overflow-y: auto;
            max-height: 65vh;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .km-col-body::-webkit-scrollbar { width: 4px; }
        .km-col-body::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 2px; }

        /* Order Card */
        .km-order-card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px;
            background: white;
            position: relative;
            transition: box-shadow 0.2s;
        }
        .km-order-card:hover { box-shadow: var(--shadow-md); }
        .km-order-card.urgent { border-left: 4px solid #dc2626; }
        .km-order-card.fresh  { border-left: 4px solid #f59e0b; }

        .km-order-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .km-order-num {
            font-size: 18px;
            font-weight: 800;
            color: var(--accent);
            line-height: 1;
        }
        .km-order-time {
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .km-order-table {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--accent-light);
            color: var(--accent-dark);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 8px;
        }
        .km-order-cashier {
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Order Items */
        .km-item-list { border-top: 1px solid var(--border-color); padding-top: 10px; }
        .km-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px dashed #f0e8ee;
            font-size: 13px;
        }
        .km-item:last-child { border-bottom: none; }
        .km-item-name { font-weight: 600; color: var(--text-primary); }
        .km-item-qty {
            background: var(--accent);
            color: white;
            font-weight: 800;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 20px;
            min-width: 28px;
            text-align: center;
        }

        /* Order Footer */
        .km-order-footer {
            margin-top: 10px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .km-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 80px;
        }
        .km-btn-prepare { background: #dbeafe; color: #1e40af; }
        .km-btn-prepare:hover { background: #2563eb; color: white; }
        .km-btn-done    { background: #dcfce7; color: #166534; }
        .km-btn-done:hover { background: #16a34a; color: white; }
        .km-btn-cancel  { background: #fee2e2; color: #991b1b; }
        .km-btn-cancel:hover { background: #dc2626; color: white; }

        /* Total row */
        .km-order-total {
            font-size: 12px;
            font-weight: 700;
            color: var(--accent-dark);
            text-align: right;
            margin-top: 8px;
            border-top: 1px solid var(--border-color);
            padding-top: 8px;
        }

        /* Empty state */
        .km-empty {
            text-align: center;
            padding: 32px 16px;
            color: var(--text-secondary);
            font-size: 13px;
        }
        .km-empty .km-empty-icon { font-size: 32px; margin-bottom: 8px; }

        /* Elapsed time badge */
        .elapsed-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .elapsed-ok     { background: #dcfce7; color: #166534; }
        .elapsed-warn   { background: #fef3c7; color: #92400e; }
        .elapsed-urgent { background: #fee2e2; color: #991b1b; }

        /* Refresh button */
        .km-refresh-btn {
            padding: 8px 16px;
            background: var(--accent-light);
            color: var(--accent);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .km-refresh-btn:hover { background: var(--accent); color: white; }

        .km-live-dot {
            width: 8px; height: 8px;
            background: #16a34a;
            border-radius: 50%;
            display: inline-block;
            animation: pulse-dot 1.5s infinite;
        }
        @keyframes pulse-dot {
            0%,100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.5; transform: scale(0.8); }
        }

        .km-toast {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 20px; border-radius: 10px;
            font-size: 13px; font-weight: 600; z-index: 9999;
            color: white; opacity: 0; transform: translateY(20px);
            transition: all 0.3s;
        }
        .km-toast.show { opacity: 1; transform: translateY(0); }
        .km-toast.success { background: #16a34a; }
        .km-toast.error   { background: #dc2626; }
        .km-toast.info    { background: #2563eb; }

        @media (max-width: 900px) {
            .km-columns { grid-template-columns: 1fr; }
        }

        /* ===== FULLSCREEN KITCHEN MODE ===== */
        body.km-fullscreen { overflow: hidden; }
        .km-full-wrap {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: var(--body-bg);
        }
        .km-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
            background: var(--sidebar-bg);
            color: white;
            flex-shrink: 0;
        }
        .km-brand {
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            user-select: none;
            letter-spacing: 0.5px;
            transition: opacity 0.2s;
        }
        .km-brand:active { opacity: 0.7; }
        .km-topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            opacity: 0.85;
        }
        .km-topbar-refresh {
            padding: 6px 14px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 8px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .km-topbar-refresh:hover { background: rgba(255,255,255,0.25); }
        .km-full-body {
            flex: 1;
            overflow: hidden;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .km-stats-row {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        /* End kitchen styles */
    </style>
</head>
<body class="km-fullscreen">
<div class="km-full-wrap">

    <!-- Top bar -->
    <div class="km-topbar">
        <div class="km-brand" id="kmBrand">
            <i class="fa-solid fa-kitchen-set" style="margin-right:6px;"></i>
            <?= htmlspecialchars($_SESSION['fastfood_name'] ?? 'iPOS') ?> — Kitchen
        </div>
        <div class="km-topbar-right">
            <span class="km-live-dot"></span>
            <span id="lastRefresh">—</span>
            <button class="km-topbar-refresh" onclick="loadOrders()"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
            <button class="km-back-btn" onclick="showKitchenPinModal()">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
        </div>
    </div>

    <!-- Kitchen Back — PIN Modal -->
    <div id="kitchenPinOverlay" style="display:none; position:fixed; inset:0;
         background:rgba(0,0,0,0.6); backdrop-filter:blur(6px); z-index:9000;
         align-items:center; justify-content:center;">
        <div style="background:white; border-radius:24px; padding:36px 32px; width:340px;
             text-align:center; box-shadow:0 24px 80px rgba(0,0,0,0.3); animation:kmPinPop 0.25s ease;">
            <div style="font-size:44px; margin-bottom:10px;">🔒</div>
            <h2 style="font-size:1.2rem; font-weight:800; color:#1a1a2e; margin-bottom:6px;">Kitchen Manager PIN</h2>
            <p style="font-size:13px; color:#888; margin-bottom:22px;">
                Enter your 4-digit PIN to log out and return to Staff Login.
            </p>
            <div id="kmPinDots" style="display:flex; justify-content:center; gap:14px; margin-bottom:22px;">
                <div class="pin-dot"></div><div class="pin-dot"></div>
                <div class="pin-dot"></div><div class="pin-dot"></div>
            </div>
            <div class="pin-pad" style="max-width:240px; margin:0 auto;">
                <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                    <button type="button" class="pin-key"
                        onclick="<?= $k==='⌫' ? 'kmPinBack()' : ($k==='' ? '' : "kmPinPress($k)") ?>">
                        <?= $k ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <p id="kmPinError" style="color:#dc2626; font-size:13px; margin-top:12px; display:none;">Incorrect PIN. Try again.</p>
            <button onclick="hideKitchenPinModal()"
                style="margin-top:16px; background:none; border:1px solid #ddd; padding:8px 24px;
                border-radius:8px; cursor:pointer; font-size:13px; color:#666;">
                Cancel
            </button>
        </div>
    </div>
    <style>@keyframes kmPinPop { from{transform:scale(0.85);opacity:0} to{transform:scale(1);opacity:1} }</style>

    <div class="km-full-body">
        <!-- Stats -->
        <div class="km-stats-row">
            <div class="km-stat"><div class="km-stat-icon pending">⏳</div><div><div class="km-stat-val" id="stat-queued">0</div><div class="km-stat-label">Queued</div></div></div>
            <div class="km-stat"><div class="km-stat-icon preparing">🔥</div><div><div class="km-stat-val" id="stat-preparing">0</div><div class="km-stat-label">Preparing</div></div></div>
            <div class="km-stat"><div class="km-stat-icon done">✅</div><div><div class="km-stat-val" id="stat-done">0</div><div class="km-stat-label">Done Today</div></div></div>
            <div class="km-stat"><div class="km-stat-icon cancelled">❌</div><div><div class="km-stat-val" id="stat-cancelled">0</div><div class="km-stat-label">Cancelled</div></div></div>
        </div>

        <!-- Kanban Columns -->
        <div class="km-columns" style="flex:1; overflow:hidden;">
            <div class="km-column km-col-pending">
                <div class="km-column-header">
                    ⏳ Queued / Pending
                    <span class="km-col-count" id="count-queued">0</span>
                </div>
                <div class="km-col-body" id="col-queued">
                    <div class="km-empty"><div class="km-empty-icon">🍽️</div>No pending orders</div>
                </div>
            </div>
            <div class="km-column km-col-preparing">
                <div class="km-column-header">
                    🔥 Preparing
                    <span class="km-col-count" id="count-preparing">0</span>
                </div>
                <div class="km-col-body" id="col-preparing">
                    <div class="km-empty"><div class="km-empty-icon">🧑‍🍳</div>Nothing cooking yet</div>
                </div>
            </div>
            <div class="km-column km-col-done">
                <div class="km-column-header">
                    ✅ Done / Ready
                    <span class="km-col-count" id="count-done">0</span>
                </div>
                <div class="km-col-body" id="col-done">
                    <div class="km-empty"><div class="km-empty-icon">🎉</div>No completed orders yet</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pin Modals (required) -->
<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

<script>
let autoRefreshTimer;

// ===== LOAD ORDERS =====
async function loadOrders() {
    try {
        const res  = await fetch('helpers/kitchen_helpers.php?action=get_orders');
        const data = await res.json();
        if (!data.success) return;

        const orders = data.orders;
        const now    = Date.now();

        // Stats
        const queued    = orders.filter(o => o.order_status === 'Queued');
        const preparing = orders.filter(o => o.order_status === 'Preparing');
        const done      = orders.filter(o => o.order_status === 'Served' || o.order_status === 'Completed');
        const cancelled = orders.filter(o => o.order_status === 'Cancelled');

        document.getElementById('stat-queued').textContent    = queued.length;
        document.getElementById('stat-preparing').textContent = preparing.length;
        document.getElementById('stat-done').textContent      = done.length;
        document.getElementById('stat-cancelled').textContent = cancelled.length;

        document.getElementById('count-queued').textContent    = queued.length;
        document.getElementById('count-preparing').textContent = preparing.length;
        document.getElementById('count-done').textContent      = done.length;

        renderColumn('col-queued',    queued,    'queued');
        renderColumn('col-preparing', preparing, 'preparing');
        renderColumn('col-done',      done,      'done');

        document.getElementById('lastRefresh').textContent = 'Updated: ' + new Date().toLocaleTimeString();
    } catch (e) {
        showKmToast('Failed to load orders', 'error');
    }
}

function renderColumn(colId, orders, type) {
    const col = document.getElementById(colId);
    if (orders.length === 0) {
        const empties = {
            queued:    '<div class="km-empty"><div class="km-empty-icon">🍽️</div>No pending orders</div>',
            preparing: '<div class="km-empty"><div class="km-empty-icon">🧑‍🍳</div>Nothing cooking yet</div>',
            done:      '<div class="km-empty"><div class="km-empty-icon">🎉</div>No completed orders yet</div>',
        };
        col.innerHTML = empties[type] || '';
        return;
    }
    col.innerHTML = orders.map(o => buildOrderCard(o, type)).join('');
}

function buildOrderCard(o, type) {
    const created  = new Date(o.created_at);
    const now      = new Date();
    const mins     = Math.floor((now - created) / 60000);
    let   elapsed  = mins < 60 ? `${mins}m ago` : `${Math.floor(mins/60)}h ${mins%60}m ago`;
    let   elClass  = mins < 10 ? 'elapsed-ok' : mins < 20 ? 'elapsed-warn' : 'elapsed-urgent';
    let   cardClass= mins >= 20 && type !== 'done' ? 'urgent' : mins < 5 ? 'fresh' : '';

    // Items
    let itemsHtml = '';
    if (o.items && o.items.length) {
        itemsHtml = `<div class="km-item-list">` +
            o.items.map(it => `
                <div class="km-item">
                    <span class="km-item-name">${escHtml(it.item_name || it.name)}</span>
                    <span class="km-item-qty">×${it.quantity || it.qty || 1}</span>
                </div>
            `).join('') +
        `</div>`;
    }

    // Buttons
    let btns = '';
    if (type === 'queued') {
        btns = `<button class="km-btn km-btn-prepare" onclick="updateStatus(${o.order_id},'Preparing')">🔥 Start Cooking</button>
                <button class="km-btn km-btn-cancel"  onclick="updateStatus(${o.order_id},'Cancelled')">✕ Cancel</button>`;
    } else if (type === 'preparing') {
        btns = `<button class="km-btn km-btn-done"   onclick="updateStatus(${o.order_id},'Served')">✅ Mark Done</button>
                <button class="km-btn km-btn-cancel"  onclick="updateStatus(${o.order_id},'Cancelled')">✕ Cancel</button>`;
    } else {
        btns = `<button class="km-btn km-btn-cancel" onclick="updateStatus(${o.order_id},'Queued')" style="background:#fef3c7;color:#92400e;">↩ Re-queue</button>`;
    }

    return `
    <div class="km-order-card ${cardClass}" id="order-${o.order_id}">
        <div class="km-order-top">
            <div class="km-order-num">#${o.queue_number || o.order_id}</div>
            <div>
                <span class="elapsed-badge ${elClass}">${elapsed}</span>
                <div class="km-order-time">${created.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</div>
            </div>
        </div>
        <div class="km-order-table">🪑 Table ${escHtml(String(o.table_number || '—'))}</div>
        <div class="km-order-cashier">👤 ${escHtml(o.cashier_name || o.customer_name || 'Customer')}
            &nbsp;·&nbsp; Order #${o.order_id}
        </div>
        ${itemsHtml}
        <div class="km-order-total">Total: ₱${parseFloat(o.total_amount || 0).toFixed(2)}</div>
        <div class="km-order-footer">${btns}</div>
    </div>`;
}

async function updateStatus(orderId, newStatus) {
    try {
        const res  = await fetch('helpers/kitchen_helpers.php?action=update_status', {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:    `order_id=${encodeURIComponent(orderId)}&status=${encodeURIComponent(newStatus)}`
        });
        const data = await res.json();
        if (data.success) {
            const labels = { Preparing:'🔥 Now cooking', Served:'✅ Marked done', Cancelled:'❌ Cancelled', Queued:'↩ Re-queued' };
            showKmToast(labels[newStatus] || 'Updated', 'success');
            loadOrders();
        } else {
            showKmToast(data.message || 'Failed', 'error');
        }
    } catch (e) {
        showKmToast('Connection error', 'error');
    }
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showKmToast(msg, type) {
    const el = document.createElement('div');
    el.className = 'km-toast ' + type;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add('show'), 10);
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 2800);
}

// Auto-refresh every 15 seconds
function startAutoRefresh() {
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(loadOrders, 6000);
}

// Open PIN modal — not needed in fullscreen kitchen mode, stub it
window.openPinModal = function(type) {};

// ===== KITCHEN BACK PIN =====
let kmPin = '';
function showKitchenPinModal() {
    kmPin = '';
    updKmDots(0);
    document.getElementById('kmPinError').style.display = 'none';
    document.getElementById('kitchenPinOverlay').style.display = 'flex';
}
function hideKitchenPinModal() {
    document.getElementById('kitchenPinOverlay').style.display = 'none';
    kmPin = '';
    updKmDots(0);
}
function kmPinPress(num) {
    if (kmPin.length >= 4) return;
    kmPin += String(num);
    updKmDots(kmPin.length);
    if (kmPin.length === 4) setTimeout(kmPinVerify, 100);
}
function kmPinBack() { kmPin = kmPin.slice(0,-1); updKmDots(kmPin.length); }
function updKmDots(n) {
    document.querySelectorAll('#kmPinDots .pin-dot').forEach((d,i) => d.classList.toggle('filled', i < n));
}
function kmPinVerify() {
    const isStaff = <?= $via_staff ? 'true' : 'false' ?>;
    const url     = isStaff
        ? 'helpers/staff_helpers.php?action=verify_own_pin'
        : 'helpers/admindashboard_helpers.php?action=check_pin';

    fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'pin=' + encodeURIComponent(kmPin)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = isStaff ? 'staff_login.php' : 'admindashboard.php';
        } else {
            document.getElementById('kmPinError').style.display = 'block';
            kmPin = ''; updKmDots(0);
            const dots = document.getElementById('kmPinDots');
            dots.classList.add('pin-shake');
            setTimeout(() => dots.classList.remove('pin-shake'), 500);
        }
    })
    .catch(() => { document.getElementById('kmPinError').textContent = 'Connection error.'; document.getElementById('kmPinError').style.display = 'block'; });
}
// Keyboard support
document.addEventListener('keydown', e => {
    if (document.getElementById('kitchenPinOverlay').style.display === 'flex') {
        if (e.key >= '0' && e.key <= '9') kmPinPress(parseInt(e.key));
        if (e.key === 'Backspace') kmPinBack();
        if (e.key === 'Escape') hideKitchenPinModal();
    }
});

// Init
loadOrders();
startAutoRefresh();

/* ================================================================
   BACK-BUTTON PREVENTION
   Push a dummy state so the browser back button triggers popstate
   instead of navigating away. When triggered, show the PIN logout
   overlay so the kitchen manager must log out first.
================================================================ */
history.pushState({ page: 'kitchen' }, '', window.location.href);
window.addEventListener('popstate', function(e) {
    history.pushState({ page: 'kitchen' }, '', window.location.href);
    showKitchenPinModal();
});

</script>
</body>
</html>