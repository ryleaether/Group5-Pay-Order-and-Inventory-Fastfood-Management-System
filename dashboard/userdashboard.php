<?php
session_start();
require_once __DIR__ . "/../config/database.php";

// Prevent caching so browsers don't show stale pages after logout
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Allow access from admin session OR staff session (Cashier role)
if (isset($_SESSION['staff_id']) && $_SESSION['staff_role'] === 'Cashier' && isset($_SESSION['staff_admin'])) {
    $admin_id = (int)$_SESSION['staff_admin'];
} elseif (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    if (empty($_SESSION['staff_gate_unlocked'])) {
        header("Location: staff_gate.php");
        exit;
    }
} else {
    header("Location: ../login.php");
    exit;
}

$db   = new Database();
$conn = $db->connect();

// Fetch shift info for staff sessions
$shiftInfo    = null;
$staffLoginAt = null;
if (isset($_SESSION['staff_id'])) {
    $sStmt = $conn->prepare("SELECT fullname, role, shift_start, shift_end, last_login_at FROM staffs WHERE staff_id = :id");
    $sStmt->execute([':id' => (int)$_SESSION['staff_id']]);
    $shiftInfo = $sStmt->fetch(PDO::FETCH_ASSOC);
    // Prefer session timestamp; fall back to DB last_login_at (always set on login)
    $staffLoginAt = $_SESSION['staff_login_at'] ?? null;
    if (!$staffLoginAt && !empty($shiftInfo['last_login_at'])) {
        $staffLoginAt = strtotime($shiftInfo['last_login_at']);
    }
}

$stmt = $conn->prepare("
    SELECT * FROM menu_items
    WHERE admin_id = :admin_id AND is_available = 1 AND stock_quantity > 0
    ORDER BY category, item_name
");
$stmt->bindParam(":admin_id", $admin_id);
$stmt->execute();
$all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($all_items as $item) {
    $cat = $item['category'];
    if (!in_array($cat, $categories)) $categories[] = $cat;
}

// Helper: convert #rrggbb to rgba(r,g,b,alpha) for theme bridge
function hexToRgba($hex, $alpha) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    [$r,$g,$b] = array_map('hexdec', str_split($hex, 2));
    return "rgba($r,$g,$b,$alpha)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($_SESSION['fastfood_name'] ?? 'iPOS') ?> — Cashier</title>
    <link rel="stylesheet" href="../design/user.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <style id="ipos-cashier-theme-bridge">
    /* Bridge admin theme vars into cashier-specific vars */
    :root {
        --accent2:    <?= htmlspecialchars($ac) ?>;
        --accent-dim: <?= htmlspecialchars(hexToRgba($ac, 0.15)) ?>;
        /* Remap dark surface vars to admin's light theme palette */
        --bg:       <?= htmlspecialchars($bg) ?>;
        --surface:  #ffffff;
        --surface2: <?= htmlspecialchars($acl) ?>;
        --surface3: <?= htmlspecialchars(hexToRgba($ac, 0.08)) ?>;
        --text:     <?= htmlspecialchars($txt) ?>;
        --text2:    <?= htmlspecialchars(hexToRgba($txt, 0.65)) ?>;
        --text3:    <?= htmlspecialchars(hexToRgba($txt, 0.40)) ?>;
        --border:   <?= htmlspecialchars(hexToRgba($acd, 0.15)) ?>;
    }
    /* Admin modal specifically — use admin card colors not dark overlay */
    .pos-admin-modal {
        background: #ffffff;
        border-color: <?= htmlspecialchars(hexToRgba($acd, 0.15)) ?>;
        color: <?= htmlspecialchars($txt) ?>;
    }
    .pos-admin-modal h3 { color: <?= htmlspecialchars($txt) ?>; }
    .pos-admin-modal p  { color: <?= htmlspecialchars(hexToRgba($txt, 0.6)) ?>; }

    /* ── Shift Info Banner ── */
    .pos-shell { grid-template-rows: 52px auto 1fr; }
    .shift-banner {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        gap: 0;
        background: linear-gradient(90deg, var(--accent-dim) 0%, transparent 100%);
        border-bottom: 1px solid var(--border);
        padding: 6px 20px;
        font-size: 12.5px;
        flex-wrap: wrap;
        overflow: hidden;
    }
    .shift-banner-name {
        display: flex;
        align-items: center;
        gap: 7px;
        font-weight: 700;
        color: var(--text);
        flex-shrink: 0;
    }
    .shift-banner-name .role-chip {
        background: var(--accent-dim);
        border: 1px solid rgba(249,115,22,0.3);
        color: var(--accent);
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }
    .shift-banner-sep {
        width: 1px;
        height: 14px;
        background: var(--border);
        margin: 0 14px;
        flex-shrink: 0;
    }
    .shift-banner-item {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--text2);
        flex-shrink: 0;
    }
    .shift-banner-item i { color: var(--accent); font-size: 11px; }
    .shift-banner-item strong {
        color: var(--text);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        font-family: 'DM Mono', monospace;
    }
    .shift-banner-item.warn strong { color: #f59e0b; }
    .shift-banner-item.danger strong { color: #ef4444; }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="pos-shell">

    <!-- ======== TOP BAR ======== -->
    <header class="pos-topbar">
        <div class="pos-brand" id="brandLogo">
            <?= htmlspecialchars($_SESSION['fastfood_name'] ?? 'iPOS') ?>
        </div>
        <div class="pos-topbar-divider"></div>
        <div class="pos-cashier-badge">Cashier Mode</div>

        <div class="pos-search-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input type="text" id="searchInput" placeholder="Search items…" oninput="handleSearch()">
        </div>

        <div class="pos-clock" id="posClock">--:--:--</div>

        <button onclick="showAdminOverlay()"
                style="margin-left:12px; padding:7px 16px; background:#ef4444;
                       border:none; border-radius:8px; color:white;
                       font-size:12px; font-weight:700; cursor:pointer; display:flex;
                       align-items:center; gap:6px; transition:background 0.2s; flex-shrink:0;"
                onmouseover="this.style.background='#dc2626'"
                onmouseout="this.style.background='#ef4444'">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
    </header>

    <?php if ($shiftInfo): ?>
    <!-- ======== SHIFT INFO BANNER ======== -->
    <div class="shift-banner" id="shiftBanner">
        <div class="shift-banner-name">
            <i class="fa-solid fa-circle-user" style="color:var(--accent);font-size:14px;"></i>
            <?= htmlspecialchars($shiftInfo['fullname']) ?>
            <span class="role-chip"><?= htmlspecialchars($shiftInfo['role']) ?></span>
        </div>

        <?php if (!empty($shiftInfo['shift_start']) && !empty($shiftInfo['shift_end'])): ?>
        <div class="shift-banner-sep"></div>
        <div class="shift-banner-item">
            <i class="fa-regular fa-calendar-clock"></i>
            Shift:&nbsp;<strong>
                <?= date('h:i A', strtotime($shiftInfo['shift_start'])) ?>
                &ndash;
                <?= date('h:i A', strtotime($shiftInfo['shift_end'])) ?>
            </strong>
        </div>
        <?php endif; ?>

        <div class="shift-banner-sep"></div>
        <div class="shift-banner-item" id="elapsedWrap">
            <i class="fa-regular fa-stopwatch"></i>
            Logged in:&nbsp;<strong id="shiftElapsed">—</strong>
        </div>

        <?php if (!empty($shiftInfo['shift_end'])): ?>
        <div class="shift-banner-sep"></div>
        <div class="shift-banner-item" id="remainWrap">
            <i class="fa-regular fa-hourglass-half"></i>
            Remaining:&nbsp;<strong id="shiftRemaining">—</strong>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ======== LEFT: MENU PANEL ======== -->
    <main class="pos-menu-panel">

        <!-- Category Tabs -->
        <div class="pos-cats" id="posCats">
            <button class="pos-cat-btn active" onclick="filterCategory('all', this)">All</button>
            <?php foreach ($categories as $cat): ?>
                <button class="pos-cat-btn" onclick="filterCategory('<?= htmlspecialchars($cat) ?>', this)">
                    <?= htmlspecialchars($cat) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Item Grid -->
        <div class="pos-items-grid" id="itemsGrid">
            <?php if (!empty($all_items)): ?>
                <?php foreach ($all_items as $item): ?>
                    <div class="pos-item-card"
                         data-category="<?= htmlspecialchars($item['category']) ?>"
                         data-name="<?= strtolower(htmlspecialchars($item['item_name'])) ?>"
                         onclick="addToOrder(<?= $item['menu_item_id'] ?>, '<?= addslashes($item['item_name']) ?>', <?= $item['price'] ?>, <?= $item['stock_quantity'] ?>)">

                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= htmlspecialchars($item['image_url']) ?>"
                                 alt="<?= htmlspecialchars($item['item_name']) ?>"
                                 class="pos-item-img">
                        <?php else: ?>
                            <div class="pos-item-img-placeholder">🍽</div>
                        <?php endif; ?>

                        <?php if ($item['stock_quantity'] <= 5): ?>
                            <div class="pos-stock-pill">Only <?= $item['stock_quantity'] ?> left</div>
                        <?php endif; ?>

                        <div class="pos-item-info">
                            <div class="pos-item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                            <?php if (!empty($item['description'])): ?>
                                <div class="pos-item-desc"><?= htmlspecialchars($item['description']) ?></div>
                            <?php endif; ?>
                            <div class="pos-item-bottom">
                                <div class="pos-item-price">₱<?= number_format($item['price'], 2) ?></div>
                                <div class="pos-item-add-icon">+</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="pos-empty">No menu items available right now.</div>
            <?php endif; ?>
        </div>

        <div class="pos-empty" id="searchEmpty" style="display:none;">No items match your search.</div>
    </main>

    <!-- ======== RIGHT: ORDER PANEL ======== -->
    <aside class="pos-order-panel">

        <!-- Order Header -->
        <div class="pos-order-header">
            <div class="pos-order-title">Current Order</div>
            <div class="pos-order-meta">
                <span class="pos-order-num" id="orderNumDisplay">#—</span>
                <div class="pos-order-actions">
                    <button class="pos-icon-btn danger" title="Clear Order" onclick="clearOrder()">✕</button>
                </div>
            </div>
        </div>

        <!-- Order Lines -->
        <div class="pos-lines" id="orderLines">
            <div class="pos-order-empty" id="orderEmpty">
                <div class="pos-order-empty-icon">🧾</div>
                <p>Tap an item to add it to the order</p>
            </div>
        </div>

        <!-- Totals -->
        <div class="pos-totals" id="orderTotals" style="display:none;">
            <div class="pos-total-row">
                <span>Items</span>
                <span class="val" id="totalItems">0</span>
            </div>
            <div class="pos-total-row main">
                <span>Total</span>
                <span class="val" id="orderTotal">₱0.00</span>
            </div>
        </div>

        <!-- Payment -->
        <div class="pos-payment" id="paymentSection" style="display:none;">
            <div class="pos-cash-row">
                <span class="pos-cash-label">Cash</span>
                <input type="number" id="cashInput" class="pos-cash-input"
                       placeholder="0.00" min="0" step="0.01" oninput="calcChange()">
            </div>

            <div class="pos-quick-cash" id="quickCash">
                <!-- filled by JS based on total -->
            </div>

            <div class="pos-change-box" id="changeBox" style="display:none;">
                <span class="lbl">Change</span>
                <span class="amt" id="changeAmt">₱0.00</span>
            </div>

            <p class="pos-error-msg" id="cashError">Cash must be at least equal to the total.</p>

            <button class="pos-charge-btn" id="chargeBtn" onclick="placeOrder()" disabled>
                Charge — <span id="chargeTotalLabel">₱0.00</span>
            </button>
        </div>

    </aside>
</div>

<!-- ======== RECEIPT MODAL ======== -->
<div class="pos-overlay" id="receiptOverlay">
    <div class="pos-receipt-modal">
        <div class="pos-receipt-badge">Order Placed</div>
        <h2>Payment Received</h2>
        <p>Order sent to kitchen</p>

        <div class="pos-queue-card">
            <div class="pos-queue-cell">
                <div class="pos-queue-cell-label">Queue #</div>
                <div class="pos-queue-cell-val" id="receiptQueue">—</div>
            </div>
            <div class="pos-queue-card-divider"></div>
            <div class="pos-queue-cell">
                <div class="pos-queue-cell-label">Table</div>
                <div class="pos-queue-cell-val" id="receiptTable">—</div>
            </div>
        </div>

        <div class="pos-receipt-items" id="receiptItems"></div>

        <div class="pos-receipt-totals">
            <div class="pos-receipt-total-row big">
                <span>Total</span>
                <span class="val" id="receiptTotal">₱0.00</span>
            </div>
            <div class="pos-receipt-total-row">
                <span>Cash Paid</span>
                <span class="val" id="receiptCash">₱0.00</span>
            </div>
            <div class="pos-receipt-total-row change">
                <span>Change</span>
                <span class="val" id="receiptChange">₱0.00</span>
            </div>
        </div>

        <button class="pos-new-order-btn" onclick="newOrder()">+ New Order</button>
        <button class="pos-cancel-order-btn" id="cancelOrderBtn" onclick="cancelCurrentOrder()">
            Cancel This Order
        </button>
    </div>
</div>

<!-- ======== ADMIN TOGGLE MODAL ======== -->
<div class="pos-admin-overlay" id="adminOverlay">
    <div class="pos-admin-modal">
        <div style="font-size:28px; margin-bottom:8px;">🔐</div>
        <h3>Log Out</h3>
        <p>Enter your PIN to log out and return to Staff Login</p>
        <div class="pin-dots" id="adminPinDots">
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
        </div>
        <div class="pin-pad">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                <button type="button" class="pin-key<?= $k === '' ? ' ' : '' ?>"
                    onclick="<?= $k === '⌫' ? 'adminPinBackspace()' : ($k === '' ? '' : "adminPinPress($k)") ?>">
                    <?= $k ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="adminPinError" style="color:#ef4444; font-size:12px; margin-top:10px; display:none;">Incorrect PIN. Try again.</p>
        <button onclick="hideAdminOverlay()"
                style="margin-top:14px; background:transparent; border:1px solid rgba(255,255,255,0.15); padding:8px 20px; border-radius:8px; cursor:pointer; font-size:13px; color:var(--text2); font-family:inherit;">
            ← Back to Cashier
        </button>
    </div>
</div>

<script>
/* ================================================================
   STATE
================================================================ */
let order          = {};    // { id: { name, price, qty, stock } }
let activeCategory = 'all';
let currentOrderId = null;
let orderCounter   = 1;

/* ================================================================
   CLOCK
================================================================ */
function tickClock() {
    const now = new Date();
    document.getElementById('posClock').textContent =
        now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
setInterval(tickClock, 1000);
tickClock();

/* ================================================================
   SHIFT TIMER
================================================================ */
<?php if ($shiftInfo && $staffLoginAt): ?>
(function () {
    const loginAt  = <?= (int)$staffLoginAt ?>;
    const shiftEnd = <?= !empty($shiftInfo['shift_end']) ? json_encode($shiftInfo['shift_end']) : 'null' ?>;

    function fmtDuration(secs) {
        if (secs < 0) secs = 0;
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        const s = secs % 60;
        if (h > 0) return h + 'h ' + String(m).padStart(2,'0') + 'm ' + String(s).padStart(2,'0') + 's';
        return m + 'm ' + String(s).padStart(2,'0') + 's';
    }

    function getShiftEndTimestamp() {
        if (!shiftEnd) return null;
        const now    = new Date();
        const [hh, mm, ss] = shiftEnd.split(':').map(Number);
        const end    = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hh, mm, ss || 0);
        if (end.getTime() < Date.now()) end.setDate(end.getDate() + 1);
        return Math.floor(end.getTime() / 1000);
    }

    const elapsedEl   = document.getElementById('shiftElapsed');
    const remainEl    = document.getElementById('shiftRemaining');
    const remainWrap  = document.getElementById('remainWrap');

    function tickShift() {
        const nowSec  = Math.floor(Date.now() / 1000);
        const elapsed = nowSec - loginAt;
        if (elapsedEl) elapsedEl.textContent = fmtDuration(elapsed);

        if (remainEl && remainWrap) {
            const endTs = getShiftEndTimestamp();
            if (endTs) {
                const remain = endTs - nowSec;
                remainEl.textContent = remain <= 0 ? 'Shift ended' : fmtDuration(remain);
                remainWrap.classList.remove('warn', 'danger');
                if (remain <= 0)          remainWrap.classList.add('danger');
                else if (remain <= 1800)  remainWrap.classList.add('danger');
                else if (remain <= 3600)  remainWrap.classList.add('warn');
            }
        }
    }

    tickShift();
    setInterval(tickShift, 1000);
})();
<?php endif; ?>

/* ================================================================
   CATEGORY FILTER & SEARCH
================================================================ */
function filterCategory(cat, btn) {
    activeCategory = cat;
    document.querySelectorAll('.pos-cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('searchInput').value = '';
    applyFilters();
}

function handleSearch() {
    const q = document.getElementById('searchInput').value.trim();
    if (q !== '') {
        document.querySelectorAll('.pos-cat-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.pos-cat-btn').classList.add('active');
        activeCategory = 'all';
    }
    applyFilters();
}

function applyFilters() {
    const query   = document.getElementById('searchInput').value.trim().toLowerCase();
    const cards   = document.querySelectorAll('.pos-item-card');
    let   visible = 0;

    cards.forEach(card => {
        const matchCat  = activeCategory === 'all' || card.dataset.category === activeCategory;
        const matchName = query === '' || card.dataset.name.includes(query);
        card.style.display = (matchCat && matchName) ? '' : 'none';
        if (matchCat && matchName) visible++;
    });

    document.getElementById('searchEmpty').style.display = visible === 0 ? 'block' : 'none';
    document.getElementById('itemsGrid').style.display    = visible === 0 ? 'none' : '';
}

/* ================================================================
   ORDER MANAGEMENT
================================================================ */
function addToOrder(id, name, price, stock) {
    if (order[id]) {
        if (order[id].qty >= stock) { showToast('Max stock reached for ' + name, 'error'); return; }
        order[id].qty++;
    } else {
        order[id] = { name, price, qty: 1, stock };
    }
    renderOrder();
    showToast(name + ' added', 'success');
}

function changeQty(id, delta) {
    if (!order[id]) return;
    order[id].qty += delta;
    if (order[id].qty <= 0) {
        delete order[id];
    } else if (order[id].qty > order[id].stock) {
        order[id].qty = order[id].stock;
        showToast('Max stock reached!', 'error');
    }
    renderOrder();
}

function removeLine(id) {
    delete order[id];
    renderOrder();
}

function clearOrder() {
    if (Object.keys(order).length === 0) return;
    if (!confirm('Clear the current order?')) return;
    order = {};
    renderOrder();
}

function renderOrder() {
    const linesEl   = document.getElementById('orderLines');
    const emptyEl   = document.getElementById('orderEmpty');
    const totalsEl  = document.getElementById('orderTotals');
    const payEl     = document.getElementById('paymentSection');
    const ids       = Object.keys(order);

    let totalQty = 0, totalAmt = 0;
    ids.forEach(id => { totalQty += order[id].qty; totalAmt += order[id].price * order[id].qty; });

    // Clear existing rows
    linesEl.querySelectorAll('.pos-line').forEach(r => r.remove());

    if (ids.length === 0) {
        emptyEl.style.display = 'flex';
        totalsEl.style.display = 'none';
        payEl.style.display    = 'none';
        return;
    }

    emptyEl.style.display  = 'none';
    totalsEl.style.display = 'block';
    payEl.style.display    = 'flex';

    ids.forEach(id => {
        const item = order[id];
        const sub  = item.price * item.qty;
        const row  = document.createElement('div');
        row.className = 'pos-line';
        row.innerHTML = `
            <div>
                <div class="pos-line-name">${item.name}</div>
                <div class="pos-line-sub">₱${item.price.toFixed(2)} each</div>
            </div>
            <div class="pos-line-qty">
                <button class="pos-qty-btn" onclick="changeQty(${id}, -1)">−</button>
                <span class="pos-qty-val">${item.qty}</span>
                <button class="pos-qty-btn" onclick="changeQty(${id}, 1)">+</button>
            </div>
            <div class="pos-line-price">₱${sub.toFixed(2)}</div>
        `;
        row.querySelector('.pos-line-qty').insertAdjacentHTML('afterend',
            `<button class="pos-icon-btn danger pos-line-del" onclick="removeLine(${id})" title="Remove">✕</button>`);
        linesEl.appendChild(row);
    });

    document.getElementById('totalItems').textContent  = totalQty;
    document.getElementById('orderTotal').textContent  = '₱' + totalAmt.toFixed(2);
    document.getElementById('chargeTotalLabel').textContent = '₱' + totalAmt.toFixed(2);

    // Update quick cash buttons
    renderQuickCash(totalAmt);

    // Reset cash input & recalc
    document.getElementById('cashInput').value = '';
    document.getElementById('changeBox').style.display  = 'none';
    document.getElementById('cashError').style.display  = 'none';
    document.getElementById('chargeBtn').disabled       = true;
}

function renderQuickCash(total) {
    const suggestions = [
        Math.ceil(total / 50) * 50,
        Math.ceil(total / 100) * 100,
        Math.ceil(total / 500) * 500,
        1000
    ].filter((v, i, a) => v >= total && a.indexOf(v) === i).slice(0, 4);

    const wrap = document.getElementById('quickCash');
    wrap.innerHTML = '';
    suggestions.forEach(amt => {
        const btn = document.createElement('button');
        btn.className = 'pos-quick-btn';
        btn.textContent = '₱' + amt.toLocaleString();
        btn.onclick = () => { document.getElementById('cashInput').value = amt; calcChange(); };
        wrap.appendChild(btn);
    });

    // Exact button
    const exactBtn = document.createElement('button');
    exactBtn.className = 'pos-quick-btn';
    exactBtn.textContent = 'Exact';
    exactBtn.onclick = () => {
        document.getElementById('cashInput').value = total.toFixed(2);
        calcChange();
    };
    wrap.appendChild(exactBtn);
}

/* ================================================================
   CHANGE CALCULATION
================================================================ */
function calcChange() {
    const totalStr = document.getElementById('orderTotal').textContent.replace('₱','');
    const total    = parseFloat(totalStr) || 0;
    const cash     = parseFloat(document.getElementById('cashInput').value);
    const errorEl  = document.getElementById('cashError');
    const changeBox= document.getElementById('changeBox');
    const chargeBtn= document.getElementById('chargeBtn');

    if (isNaN(cash) || cash < total) {
        errorEl.style.display   = 'block';
        changeBox.style.display = 'none';
        chargeBtn.disabled      = true;
        return;
    }

    errorEl.style.display   = 'none';
    changeBox.style.display = 'flex';
    chargeBtn.disabled      = false;
    document.getElementById('changeAmt').textContent = '₱' + (cash - total).toFixed(2);
}

/* ================================================================
   PLACE ORDER
================================================================ */
function placeOrder() {
    const totalStr = document.getElementById('orderTotal').textContent.replace('₱','');
    const total    = parseFloat(totalStr) || 0;
    const cash     = parseFloat(document.getElementById('cashInput').value);
    const change   = cash - total;
    const chargeBtn= document.getElementById('chargeBtn');

    chargeBtn.disabled   = true;
    chargeBtn.textContent = 'Processing…';

    const payload = {
        cash_paid:    cash,
        change_given: change,
        total:        total,
        items: Object.keys(order).map(id => ({
            menu_item_id: id,
            name:         order[id].name,
            price:        order[id].price,
            qty:          order[id].qty,
            subtotal:     order[id].price * order[id].qty
        }))
    };

    fetch('helpers/user_order_handler.php?action=place_order', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            currentOrderId = data.order_id;
            showReceipt(data, total, cash, change);
        } else {
            showToast('Order failed: ' + data.message, 'error');
            chargeBtn.disabled   = false;
            chargeBtn.textContent = 'Charge — ₱' + total.toFixed(2);
        }
    })
    .catch(() => {
        showToast('Connection error. Please try again.', 'error');
        chargeBtn.disabled   = false;
        chargeBtn.textContent = 'Charge — ₱' + total.toFixed(2);
    });
}

/* ================================================================
   RECEIPT
================================================================ */
function showReceipt(data, total, cash, change) {
    document.getElementById('receiptQueue').textContent  = '#' + data.queue_number;
    document.getElementById('receiptTable').textContent  = data.table_number;
    document.getElementById('receiptTotal').textContent  = '₱' + total.toFixed(2);
    document.getElementById('receiptCash').textContent   = '₱' + cash.toFixed(2);
    document.getElementById('receiptChange').textContent = '₱' + change.toFixed(2);

    let html = '';
    Object.keys(order).forEach(id => {
        const item = order[id];
        html += `<div class="pos-receipt-line">
            <span>${item.name} × ${item.qty}</span>
            <span>₱${(item.price * item.qty).toFixed(2)}</span>
        </div>`;
    });
    document.getElementById('receiptItems').innerHTML = html;

    document.getElementById('cancelOrderBtn').style.display = 'block';
    document.getElementById('receiptOverlay').classList.add('active');
}

function newOrder() {
    order = {};
    currentOrderId = null;
    orderCounter++;
    document.getElementById('orderNumDisplay').textContent = '#' + orderCounter;
    renderOrder();
    document.getElementById('receiptOverlay').classList.remove('active');
}

function cancelCurrentOrder() {
    if (!currentOrderId) return;
    if (!confirm('Cancel this order?')) return;

    fetch('helpers/user_order_handler.php?action=cancel_order', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ order_id: currentOrderId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Order cancelled.', 'info');
            document.getElementById('cancelOrderBtn').style.display = 'none';
            document.getElementById('receiptOverlay').classList.remove('active');
            order = {};
            currentOrderId = null;
            renderOrder();
        } else {
            showToast('Could not cancel: ' + data.message, 'error');
        }
    });
}

/* ================================================================
   ADMIN TOGGLE
================================================================ */
function showAdminOverlay() {
    adminPinValue = '';
    updateAdminPinDots(0);
    document.getElementById('adminPinError').style.display = 'none';
    document.getElementById('adminOverlay').classList.add('show');
}

function hideAdminOverlay() {
    document.getElementById('adminOverlay').classList.remove('show');
    adminPinValue = '';
    updateAdminPinDots(0);
}

let adminPinValue = '';

function adminPinPress(num) {
    if (adminPinValue.length >= 4) return;
    adminPinValue += String(num);
    updateAdminPinDots(adminPinValue.length);
    if (adminPinValue.length === 4) verifyAdminPin();
}

function adminPinBackspace() {
    adminPinValue = adminPinValue.slice(0, -1);
    updateAdminPinDots(adminPinValue.length);
}

function verifyAdminPin() {
    fetch('helpers/staff_helpers.php?action=verify_own_pin', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'pin=' + encodeURIComponent(adminPinValue)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.replace('staff_login.php');
        } else {
            document.getElementById('adminPinError').style.display = 'block';
            adminPinValue = '';
            updateAdminPinDots(0);
        }
    });
}

function updateAdminPinDots(count) {
    document.querySelectorAll('#adminPinDots .pin-dot')
        .forEach((dot, i) => dot.classList.toggle('filled', i < count));
}

/* ================================================================
   TOAST
================================================================ */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className   = 'pos-toast pos-toast-' + type;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 2400);
}

/* Init display */
document.getElementById('orderNumDisplay').textContent = '#' + orderCounter;

// On pageshow (including bfcache restore) verify server-side session state.
window.addEventListener('pageshow', function() {
    fetch('helpers/session_check.php', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            // Allow if admin OR staff cashier assigned to same admin and gate unlocked when required
            const isAllowed = data.admin_id || (data.staff_id && data.staff_role === 'Cashier' && data.staff_admin);
            if (!isAllowed) {
                window.location.replace('../login.php');
            }
        })
        .catch(() => { window.location.replace('../login.php'); });
});

/* ================================================================
   BACK-BUTTON PREVENTION
   Push a dummy state so the browser back button triggers popstate
   instead of actually navigating away. When triggered, show the
   logout/PIN overlay so the user must log out first.
================================================================ */
// Push 50 sentinel entries so rapid/repeated back presses cannot
// escape this page. Every popstate immediately refills the stack.
(function lockHistory() {
    for (let i = 0; i < 50; i++) {
        history.pushState({ page: 'cashier', i: i }, '', window.location.href);
    }
}());
window.addEventListener('popstate', function() {
    for (let i = 0; i < 50; i++) {
        history.pushState({ page: 'cashier', i: i }, '', window.location.href);
    }
});
</script>

</body>
</html>