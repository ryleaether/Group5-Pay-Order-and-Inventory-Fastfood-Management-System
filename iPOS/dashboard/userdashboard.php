<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$db       = new Database();
$conn     = $db->connect();
$admin_id = $_SESSION['admin_id'];

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
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($_SESSION['fastfood_name'] ?? 'iPOS') ?> — Order</title>
    <link rel="stylesheet" href="../design/user.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<!-- ================= HEADER ================= -->
<div class="u-header">
    <div class="u-header-left">
        <!-- Triple-click brand triggers secret admin toggle -->
        <div class="u-brand" id="brandLogo" onclick="handleBrandClick()" style="cursor:pointer;">
            <?= htmlspecialchars($_SESSION['fastfood_name'] ?? 'iPOS') ?>
        </div>
    </div>
    <div class="u-header-right">
        <div class="u-search-wrap">
            <span class="u-search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="Search menu..." oninput="handleSearch()">
        </div>
        <button class="u-cart-btn" onclick="toggleCart()">
            🛒 Cart
            <span class="u-cart-count" id="cartCount">0</span>
        </button>
    </div>
</div>

<!-- ================= CATEGORY TABS ================= -->
<div class="u-tabs-wrap">
    <div class="u-tabs" id="categoryTabs">
        <button class="u-tab active" onclick="filterCategory('all', this)">All</button>
        <?php foreach ($categories as $cat): ?>
            <button class="u-tab" onclick="filterCategory('<?= htmlspecialchars($cat) ?>', this)">
                <?= htmlspecialchars($cat) ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ================= MENU GRID ================= -->
<div class="u-main">
    <div class="u-menu-grid" id="menuGrid">
        <?php if (!empty($all_items)): ?>
            <?php foreach ($all_items as $item): ?>
                <div class="u-item-card"
                     data-category="<?= htmlspecialchars($item['category']) ?>"
                     data-name="<?= strtolower(htmlspecialchars($item['item_name'])) ?>">
                    <div class="u-item-img-wrap">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= htmlspecialchars($item['image_url']) ?>"
                                 alt="<?= htmlspecialchars($item['item_name']) ?>"
                                 class="u-item-img">
                        <?php else: ?>
                            <div class="u-item-img-placeholder">🍽️</div>
                        <?php endif; ?>
                        <?php if ($item['stock_quantity'] <= 5): ?>
                            <div class="u-stock-badge">Only <?= $item['stock_quantity'] ?> left!</div>
                        <?php endif; ?>
                    </div>
                    <div class="u-item-info">
                        <div class="u-item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                        <?php if (!empty($item['description'])): ?>
                            <div class="u-item-desc"><?= htmlspecialchars($item['description']) ?></div>
                        <?php endif; ?>
                        <div class="u-item-bottom">
                            <div class="u-item-price">₱<?= number_format($item['price'], 2) ?></div>
                            <button class="u-add-btn"
                                    onclick="addToCart(<?= $item['menu_item_id'] ?>, '<?= addslashes($item['item_name']) ?>', <?= $item['price'] ?>, <?= $item['stock_quantity'] ?>)">
                                + Add
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="u-empty">No menu items available right now.</div>
        <?php endif; ?>
    </div>
    <div class="u-empty" id="searchEmpty" style="display:none;">No items found for your search.</div>
</div>

<!-- ================= CART DRAWER ================= -->
<div class="u-cart-backdrop" id="cartBackdrop" onclick="toggleCart()"></div>
<div class="u-cart-drawer" id="cartDrawer">
    <div class="u-cart-header">
        <h3>🛒 Your Order</h3>
        <button class="u-cart-close" onclick="toggleCart()">✕</button>
    </div>
    <div class="u-cart-items" id="cartItems">
        <div class="u-cart-empty" id="cartEmpty">
            <div style="font-size:40px;">🛒</div>
            <p>Your cart is empty</p>
            <small>Add items from the menu to get started</small>
        </div>
    </div>
    <div class="u-cart-footer">
        <div class="u-cart-total">
            <span>Total</span>
            <span id="cartTotal">₱0.00</span>
        </div>
        <button class="u-btn-primary" onclick="openCheckout()" id="checkoutBtn" disabled>
            Proceed to Checkout
        </button>
    </div>
</div>

<!-- ================= CHECKOUT MODAL ================= -->
<div id="checkoutModal" class="u-overlay">
    <div class="u-modal">
        <button class="u-modal-back" onclick="closeCheckout()">← Back to Cart</button>
        <h2 style="margin-bottom:4px;">💳 Checkout</h2>
        <p style="color:#888; font-size:13px; margin-bottom:16px;">Review your order and pay</p>
        <div class="u-order-summary" id="checkoutSummary"></div>
        <div class="u-checkout-total">
            <span>Order Total</span>
            <span id="checkoutTotal">₱0.00</span>
        </div>
        <div class="u-cash-section">
            <label>Cash Amount (₱)</label>
            <input type="number" id="cashInput" placeholder="Enter amount paid"
                   min="0" step="0.01" oninput="calculateChange()">
            <div class="u-change-row" id="changeRow" style="display:none;">
                <span>Change</span>
                <span id="changeAmount" class="u-change-val">₱0.00</span>
            </div>
            <p id="cashError" class="u-error" style="display:none;">
                Amount must be at least equal to the total.
            </p>
        </div>
        <button class="u-btn-primary" onclick="placeOrder()" id="placeOrderBtn" disabled>
            ✅ Place Order
        </button>
        <button class="u-btn-cancel" onclick="cancelCart()">
            ✕ Cancel Order
        </button>
    </div>
</div>

<!-- ================= RECEIPT MODAL ================= -->
<div id="receiptModal" class="u-overlay">
    <div class="u-modal u-receipt">
        <div style="font-size:56px; margin-bottom:8px;">🎉</div>
        <h2>Thank you for ordering!</h2>
        <p style="color:#888; font-size:13px;">Your order has been sent to the kitchen</p>

        <div class="u-queue-box">
            <div class="u-queue-label">Your Order Number</div>
            <div class="u-queue-number" id="queueNumber">—</div>
            <div class="u-queue-label" style="margin-top:8px;">Assigned Table Number</div>
            <div class="u-table-number" id="assignedTable">—</div>
        </div>

        <div class="u-receipt-details" id="receiptDetails"></div>

        <div class="u-receipt-total">
            <div class="u-receipt-row">
                <span>Total</span><span id="receiptTotal">₱0.00</span>
            </div>
            <div class="u-receipt-row">
                <span>Cash</span><span id="receiptCash">₱0.00</span>
            </div>
            <div class="u-receipt-row u-receipt-change">
                <span>Change</span><span id="receiptChange">₱0.00</span>
            </div>
        </div>

        <button class="u-btn-primary" onclick="newOrder()" style="margin-top:20px;">
            🍔 Order Again
        </button>
        <button class="u-btn-cancel-order" id="cancelOrderBtn" onclick="cancelCurrentOrder()" style="margin-top:8px;">
            ✕ Cancel This Order
        </button>
    </div>
</div>

<!-- ================= SECRET ADMIN TOGGLE ================= -->
<div id="adminToggleOverlay" class="u-admin-overlay" style="display:none;">
    <div class="u-admin-modal">
        <div style="font-size:30px; margin-bottom:8px;">🔒</div>
        <h3>Admin Access</h3>
        <p>Enter your admin PIN to switch back</p>
        <div id="adminPinDots" style="display:flex; justify-content:center; gap:10px; margin:16px 0;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
        </div>
        <div class="pin-pad" style="max-width:200px; margin:0 auto;">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                <button type="button" class="pin-key"
                    onclick="<?= $k === '⌫' ? 'adminPinBackspace()' : ($k === '' ? '' : "adminPinPress($k)") ?>">
                    <?= $k ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="adminPinError" style="color:#dc3545; font-size:12px; margin-top:8px; display:none;">
            Incorrect PIN.
        </p>
        <button onclick="hideAdminToggle()" style="margin-top:12px; background:none; border:1px solid #ddd; padding:8px 20px; border-radius:8px; cursor:pointer; font-size:13px;">
            Cancel
        </button>
    </div>
</div>

<script>
/* ============================================================
   STATE
============================================================ */
let cart           = {};
let activeCategory = 'all';
let currentOrderId = null;

/* ============================================================
   CATEGORY FILTER & SEARCH
============================================================ */
function filterCategory(cat, btn) {
    activeCategory = cat;
    document.querySelectorAll('.u-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('searchInput').value = '';
    applyFilters();
}

function handleSearch() {
    if (document.getElementById('searchInput').value.trim() !== '') {
        document.querySelectorAll('.u-tab').forEach(t => t.classList.remove('active'));
        document.querySelector('.u-tab').classList.add('active');
        activeCategory = 'all';
    }
    applyFilters();
}

function applyFilters() {
    const query   = document.getElementById('searchInput').value.trim().toLowerCase();
    const cards   = document.querySelectorAll('.u-item-card');
    let   visible = 0;

    cards.forEach(card => {
        const matchCat  = activeCategory === 'all' || card.dataset.category === activeCategory;
        const matchName = query === '' || card.dataset.name.includes(query);
        card.style.display = (matchCat && matchName) ? '' : 'none';
        if (matchCat && matchName) visible++;
    });

    document.getElementById('searchEmpty').style.display = visible === 0 ? 'block' : 'none';
    document.getElementById('menuGrid').style.display    = visible === 0 ? 'none'  : '';
}

/* ============================================================
   CART
============================================================ */
function addToCart(id, name, price, stock) {
    if (cart[id]) {
        if (cart[id].qty >= stock) { showToast('Max stock reached for ' + name, 'error'); return; }
        cart[id].qty++;
    } else {
        cart[id] = { name, price, qty: 1, stock };
    }
    renderCart();
    showToast(name + ' added!', 'success');
}

function removeFromCart(id) {
    delete cart[id];
    renderCart();
}

function changeQty(id, delta) {
    if (!cart[id]) return;
    cart[id].qty += delta;
    if (cart[id].qty <= 0)            { delete cart[id]; }
    else if (cart[id].qty > cart[id].stock) { cart[id].qty = cart[id].stock; showToast('Max stock reached!', 'error'); }
    renderCart();
}

function renderCart() {
    const itemsEl     = document.getElementById('cartItems');
    const emptyEl     = document.getElementById('cartEmpty');
    const countEl     = document.getElementById('cartCount');
    const totalEl     = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const ids         = Object.keys(cart);

    let totalQty = 0, totalAmount = 0;
    ids.forEach(id => { totalQty += cart[id].qty; totalAmount += cart[id].price * cart[id].qty; });

    countEl.textContent  = totalQty;
    totalEl.textContent  = '₱' + totalAmount.toFixed(2);
    checkoutBtn.disabled = ids.length === 0;

    document.querySelectorAll('.u-cart-row').forEach(r => r.remove());

    if (ids.length === 0) { emptyEl.style.display = 'flex'; return; }
    emptyEl.style.display = 'none';

    ids.forEach(id => {
        const item = cart[id];
        const row  = document.createElement('div');
        row.className = 'u-cart-row';
        row.innerHTML = `
            <div class="u-cart-row-name">${item.name}</div>
            <div class="u-cart-row-controls">
                <button class="u-qty-btn" onclick="changeQty(${id}, -1)">−</button>
                <span class="u-qty-val">${item.qty}</span>
                <button class="u-qty-btn" onclick="changeQty(${id}, 1)">+</button>
            </div>
            <div class="u-cart-row-price">₱${(item.price * item.qty).toFixed(2)}</div>
            <button class="u-cart-remove" onclick="removeFromCart(${id})">✕</button>
        `;
        itemsEl.appendChild(row);
    });
}

function toggleCart() {
    document.getElementById('cartDrawer').classList.toggle('open');
    document.getElementById('cartBackdrop').classList.toggle('active');
}

/* ============================================================
   CHECKOUT
============================================================ */
function openCheckout() {
    const ids = Object.keys(cart);
    if (ids.length === 0) return;

    let summaryHtml = '', total = 0;
    ids.forEach(id => {
        const item = cart[id];
        const sub  = item.price * item.qty;
        total += sub;
        summaryHtml += `<div class="u-summary-row"><span>${item.name} × ${item.qty}</span><span>₱${sub.toFixed(2)}</span></div>`;
    });

    document.getElementById('checkoutSummary').innerHTML  = summaryHtml;
    document.getElementById('checkoutTotal').textContent  = '₱' + total.toFixed(2);
    document.getElementById('cashInput').value            = '';
    document.getElementById('changeRow').style.display   = 'none';
    document.getElementById('cashError').style.display   = 'none';
    document.getElementById('placeOrderBtn').disabled    = true;
    document.getElementById('placeOrderBtn').textContent = '✅ Place Order';

    document.getElementById('cartDrawer').classList.remove('open');
    document.getElementById('cartBackdrop').classList.remove('active');
    document.getElementById('checkoutModal').classList.add('active');
}

function closeCheckout() {
    document.getElementById('checkoutModal').classList.remove('active');
    toggleCart();
}

function cancelCart() {
    if (!confirm('Cancel your order and clear the cart?')) return;
    cart = {};
    renderCart();
    document.getElementById('checkoutModal').classList.remove('active');
    showToast('Order cancelled.', 'info');
}

function calculateChange() {
    const total    = parseFloat(document.getElementById('checkoutTotal').textContent.replace('₱',''));
    const cash     = parseFloat(document.getElementById('cashInput').value);
    const errorEl  = document.getElementById('cashError');
    const changeRow= document.getElementById('changeRow');
    const placeBtn = document.getElementById('placeOrderBtn');

    if (isNaN(cash) || cash < total) {
        errorEl.style.display = 'block'; changeRow.style.display = 'none'; placeBtn.disabled = true;
        return;
    }
    errorEl.style.display = 'none'; changeRow.style.display = 'flex'; placeBtn.disabled = false;
    document.getElementById('changeAmount').textContent = '₱' + (cash - total).toFixed(2);
}

/* ============================================================
   PLACE ORDER
============================================================ */
function placeOrder() {
    const total   = parseFloat(document.getElementById('checkoutTotal').textContent.replace('₱',''));
    const cash    = parseFloat(document.getElementById('cashInput').value);
    const change  = cash - total;
    const placeBtn= document.getElementById('placeOrderBtn');

    placeBtn.disabled    = true;
    placeBtn.textContent = '⏳ Placing order...';

    const payload = {
        cash_paid:    cash,
        change_given: change,
        total:        total,
        items: Object.keys(cart).map(id => ({
            menu_item_id: id,
            name:         cart[id].name,
            price:        cart[id].price,
            qty:          cart[id].qty,
            subtotal:     cart[id].price * cart[id].qty
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
            placeBtn.disabled    = false;
            placeBtn.textContent = '✅ Place Order';
        }
    })
    .catch(() => {
        showToast('Connection error. Please try again.', 'error');
        placeBtn.disabled    = false;
        placeBtn.textContent = '✅ Place Order';
    });
}

/* ============================================================
   RECEIPT
============================================================ */
function showReceipt(data, total, cash, change) {
    document.getElementById('checkoutModal').classList.remove('active');

    document.getElementById('queueNumber').textContent   = '#' + data.queue_number;
    document.getElementById('assignedTable').textContent = data.table_number;
    document.getElementById('receiptTotal').textContent  = '₱' + total.toFixed(2);
    document.getElementById('receiptCash').textContent   = '₱' + cash.toFixed(2);
    document.getElementById('receiptChange').textContent = '₱' + change.toFixed(2);

    let detailsHtml = '';
    Object.keys(cart).forEach(id => {
        const item = cart[id];
        detailsHtml += `
            <div class="u-receipt-item">
                <span>${item.name} × ${item.qty}</span>
                <span>₱${(item.price * item.qty).toFixed(2)}</span>
            </div>
        `;
    });
    document.getElementById('receiptDetails').innerHTML = detailsHtml;

    /* Show cancel button only right after ordering */
    document.getElementById('cancelOrderBtn').style.display = 'block';

    document.getElementById('receiptModal').classList.add('active');
}

function newOrder() {
    cart = {};
    currentOrderId = null;
    renderCart();
    document.getElementById('receiptModal').classList.remove('active');
    document.getElementById('cashInput').value = '';
}

/* ── Cancel PLACED order ── */
function cancelCurrentOrder() {
    if (!currentOrderId) return;
    if (!confirm('Are you sure you want to cancel this order?')) return;

    fetch('helpers/user_order_handler.php?action=cancel_order', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ order_id: currentOrderId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Order cancelled successfully.', 'info');
            document.getElementById('cancelOrderBtn').style.display = 'none';
            document.getElementById('receiptModal').classList.remove('active');
            cart = {};
            currentOrderId = null;
            renderCart();
        } else {
            showToast('Could not cancel: ' + data.message, 'error');
        }
    });
}

/* ============================================================
   SECRET ADMIN TOGGLE — click brand 5 times fast
============================================================ */
let brandClickCount = 0;
let brandClickTimer = null;

function handleBrandClick() {
    brandClickCount++;
    clearTimeout(brandClickTimer);

    if (brandClickCount >= 5) {
        brandClickCount = 0;
        showAdminToggle();
        return;
    }

    brandClickTimer = setTimeout(() => { brandClickCount = 0; }, 2000);
}

function showAdminToggle() {
    adminPinValue = '';
    updateAdminPinDots(0);
    document.getElementById('adminPinError').style.display = 'none';
    document.getElementById('adminToggleOverlay').style.display = 'flex';
}

function hideAdminToggle() {
    document.getElementById('adminToggleOverlay').style.display = 'none';
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
    fetch('helpers/admindashboard_helpers.php?action=check_pin', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'pin=' + encodeURIComponent(adminPinValue)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'admindashboard.php';
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

/* ============================================================
   TOAST
============================================================ */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className   = 'u-toast u-toast-' + type;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 2500);
}
</script>

</body>
</html>
