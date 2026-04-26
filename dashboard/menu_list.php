<?php
session_start();
require_once __DIR__ . "/helpers/admindashboard_helpers.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$dashboard = new MenuDashboardHelper($admin_id);
$menu_handler = $dashboard->getMenuItemHandler();

$db = new Database();
$conn = $db->connect();
$adminProfile = [];
try {
    $stmt = $conn->prepare("SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id = :id");
    $stmt->bindParam(':id', $admin_id);
    $stmt->execute();
    $adminProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $adminProfile = [];
}

// Handle search and filter
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

$items = $menu_handler->searchAndFilter($search, $category, $status);
$categories = $menu_handler->getCategories();
$allItems = $menu_handler->getAll();
$hasAnyItems = !empty($allItems);

$noResultsMessage = '';
if (empty($items)) {
    if (!$hasAnyItems) {
        $noResultsMessage = 'No menu items yet. Add your first item!';
    } else {
        if ($search !== '' && ($category !== '' || $status !== '')) {
            $noResultsMessage = 'No menu items match your search and selected filters.';
        } elseif ($search !== '') {
            $noResultsMessage = 'No items found matching "' . htmlspecialchars($search) . '". Try another search.';
        } elseif ($category !== '' && $status !== '') {
            $statusText = $status === '1' ? 'available' : 'unavailable';
            $noResultsMessage = 'No ' . $statusText . ' items found in "' . htmlspecialchars($category) . '".';
        } elseif ($category !== '') {
            $noResultsMessage = 'No items found in "' . htmlspecialchars($category) . '". Try another category.';
        } elseif ($status !== '') {
            $noResultsMessage = $status === '1'
                ? 'No available items match your current selection.'
                : 'No unavailable items match your current selection.';
        } else {
            $noResultsMessage = 'No menu items match your current filters.';
        }
    }
}

// Sidebar renderer
$sidebar = new SidebarRenderer($admin_id, $_SESSION['fastfood_name'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Menu List</title>
    <link rel="stylesheet" href="../design/admin.css">
</head>
<body>

<div class="dashboard">

    <?= $sidebar->render('menu') ?>

    <!-- MAIN -->
    <div class="main">

        <div class="topbar">
            <h1>🍔 Menu Items</h1>
            <p class="subtitle">Manage your food items</p>

            <!-- SEARCH AND FILTER -->
            <div class="search-filter">
                <form method="GET" class="filter-form">
                    <input type="text" name="search" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    <select name="category" autocomplete="off">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" autocomplete="off">
                        <option value="">All Status</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Available</option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Unavailable</option>
                    </select>
                    <button type="submit">🔍 Search</button>
                    <a href="menu_list.php" class="clear-btn">Clear</a>
                </form>
            </div>

            <a href="#" class="btn-add" onclick="openModal('add')">+ Add New Item</a>
        </div>

        <!-- MENU GRID -->
        <div class="menu-grid">
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $item): ?>
                    <div class="menu-card">

                        <!-- ITEM IMAGE -->
                        <div class="menu-img-wrap">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?= htmlspecialchars($item['image_url']) ?>"
                                     alt="<?= htmlspecialchars($item['item_name']) ?>"
                                     class="menu-img">
                            <?php else: ?>
                                <div class="menu-img-placeholder">🍽️</div>
                            <?php endif; ?>
                        </div>

                        <!-- HEADER -->
                        <div class="menu-header">
                            <h3><?= htmlspecialchars($item['item_name']) ?></h3>
                            <span class="status <?= $item['is_available'] ? 'available' : 'unavailable' ?>">
                                <?= $item['is_available'] ? 'Available' : 'Unavailable' ?>
                            </span>
                        </div>

                        <div class="category"><?= htmlspecialchars($item['category']) ?></div>
                        <div class="price">₱<?= number_format($item['price'], 2) ?></div>
                        <div class="stock">Stock: <?= $item['stock_quantity'] ?></div>

                        <!-- ACTIONS -->
                        <div class="actions">
                            <a class="btn edit"
                               href="#"
                               onclick="openEditModal(<?= $item['menu_item_id'] ?>); return false;">
                                ✏️ Edit
                            </a>
                            <a class="btn delete"
                               href="helpers/admindashboard_helpers.php?action=delete_menu&id=<?= $item['menu_item_id'] ?>"
                               onclick="return confirm('Delete this item?')">
                                🗑️ Delete
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty-state" style="grid-column:1/-1;">
                    <?= htmlspecialchars($noResultsMessage) ?>
                </p>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ================= ADD MODAL ================= -->
<div id="menuModal" class="modal">
    <div class="modal-content modal-wide">
        <span class="close" onclick="closeModal('add')">&times;</span>
        <h2>🍔 Add New Menu Item</h2>

        <form action="helpers/admindashboard_helpers.php?action=add_menu" method="POST" id="addForm">
            <input type="hidden" name="image_url" id="add_image_url">

            <div class="modal-two-col">

                <!-- LEFT: Image upload -->
                <div class="img-upload-area" id="addDropZone" onclick="document.getElementById('addImageInput').click()">
                    <div class="img-upload-placeholder" id="addImgPlaceholder">
                        <span>📷</span>
                        <p>Click or drag to upload image</p>
                        <small>JPG, PNG, WEBP only · Max 2MB</small>
                        <small style="display:block; margin-top:4px; color:#bbb;">
                            📐 Recommended: 500×500px (square)<br>
                            Minimum: 300×300px<br>
                            Images will be cropped to fit
                        </small>
                    </div>
                    <img id="addImgPreview" class="img-preview" style="display:none;">
                    <input type="file" id="addImageInput" accept="image/*" style="display:none;"
                           onchange="handleImageUpload(this, 'add')">
                </div>

                <!-- RIGHT: Fields -->
                <div class="modal-fields">
                    <input type="text"   name="item_name"     placeholder="Item Name"     required autocomplete="off">
                    <textarea            name="description"   placeholder="Description (optional)" autocomplete="off"></textarea>
                    <input type="number" name="price"         placeholder="Price (₱)"     step="0.01" min="0" required autocomplete="off">
                    <input type="number" name="stock_quantity"placeholder="Stock Quantity" min="0"    required autocomplete="off">
                    <input type="text"   name="category"      placeholder="Category"       required autocomplete="off">

                    <label class="check-label">
                        <input type="checkbox" name="is_available" value="1" checked>
                        Available (visible to customers)
                    </label>
                </div>
            </div>

            <div id="addUploadStatus" class="upload-status"></div>
            <button type="submit" class="btn-save" id="addSubmitBtn">💾 Save Item</button>
        </form>
    </div>
</div>

<!-- ================= EDIT MODAL ================= -->
<div id="editModal" class="modal">
    <div class="modal-content modal-wide">
        <span class="close" onclick="closeModal('edit')">&times;</span>
        <h2>✏️ Edit Menu Item</h2>

        <form action="/dashboard/helpers/admindashboard_helpers.php?action=edit_menu" method="POST" id="editForm">
            <input type="hidden" name="menu_item_id" id="edit_id">
            <input type="hidden" name="image_url"    id="edit_image_url">

            <div class="modal-two-col">

                <!-- LEFT: Image -->
                <div class="img-upload-area" id="editDropZone" onclick="document.getElementById('editImageInput').click()">
                    <div class="img-upload-placeholder" id="editImgPlaceholder" style="display:none;">
                        <span>📷</span>
                        <p>Click to change image</p>
                        <small>JPG, PNG, WEBP only · Max 2MB</small>
                        <small style="display:block; margin-top:4px; color:#bbb;">
                            📐 Recommended: 500×500px (square)<br>
                            Minimum: 300×300px<br>
                            Images will be cropped to fit
                        </small>
                    </div>
                    <img id="editImgPreview" class="img-preview" style="display:none;">
                    <input type="file" id="editImageInput" accept="image/*" style="display:none;"
                        onchange="handleImageUpload(this, 'edit')">
                </div>

    <!-- RIGHT: Fields -->
    <div class="modal-fields">

                <!-- RIGHT: Fields -->
                <div class="modal-fields">
                    <input type="text"   name="item_name"      id="edit_name"     placeholder="Item Name"      required autocomplete="off">
                    <textarea            name="description"    id="edit_desc"     placeholder="Description" autocomplete="off"></textarea>
                    <input type="number" name="price"          id="edit_price"    placeholder="Price (₱)"      step="0.01" min="0" required autocomplete="off">
                    <input type="number" name="stock_quantity" id="edit_stock"    placeholder="Stock Quantity"  min="0"    required autocomplete="off">
                    <input type="text"   name="category"       id="edit_category" placeholder="Category"        required autocomplete="off">

                    <label class="check-label">
                        <input type="checkbox" name="is_available" id="edit_available" value="1">
                        Available (visible to customers)
                    </label>
                </div>
            </div>

            <div id="editUploadStatus" class="upload-status"></div>
            <button type="submit" class="btn-save">💾 Update Item</button>
        </form>
    </div>
</div>

<!-- ================= ACCOUNT MODAL ================= -->
<div id="accountModal" class="modal">
    <div class="modal-content" style="max-width:420px; text-align:left;">
        <span class="close" onclick="document.getElementById('accountModal').classList.remove('show')">&times;</span>
        <h2 style="margin-bottom:12px;">👤 Edit Account</h2>
        <p style="color:#555; font-size:14px; margin-bottom:18px;">Update the account information you use to sign in.</p>
        <form id="accountForm" onsubmit="submitAccountForm(event)">
            <div style="display:grid; gap:12px;">
                <input type="text" name="fullname" placeholder="Full Name" value="<?= htmlspecialchars($adminProfile['fullname'] ?? '') ?>" required autocomplete="name">
                <input type="text" name="fastfood_name" placeholder="Fastfood Name" value="<?= htmlspecialchars($adminProfile['fastfood_name'] ?? '') ?>" required autocomplete="organization">
                <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($adminProfile['username'] ?? '') ?>" required autocomplete="username">
                <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($adminProfile['email'] ?? '') ?>" required autocomplete="email">
                <input type="password" name="new_password" placeholder="New Password (leave blank to keep current)" autocomplete="new-password">
                <input type="password" name="confirm_password" placeholder="Confirm New Password" autocomplete="new-password">
                <div id="accountMessage" style="display:none; padding:12px; border-radius:10px; font-size:13px;"></div>
                <button type="submit" class="btn-save" style="width:100%;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= PIN MODAL ================= -->
<div id="pinModal" class="modal">
    <div class="modal-content" style="max-width:340px; text-align:center;">
        <span class="close" onclick="closeModal('pin')">&times;</span>
        <div style="font-size:36px; margin-bottom:8px;">🔐</div>
        <h2 style="margin-bottom:6px;">Switch to User Dashboard</h2>
        <p style="color:#888; font-size:13px; margin-bottom:20px;">
            Enter your dashboard PIN to continue
        </p>
        <div id="pinDots" style="display:flex; justify-content:center; gap:12px; margin-bottom:20px;">
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
        </div>
        <div class="pin-pad">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                <button type="button" class="pin-key"
                    onclick="<?= $k === '⌫' ? 'pinBackspace()' : ($k === '' ? '' : "pinPress($k)") ?>">
                    <?= $k ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="pinError" style="color:#dc3545; font-size:13px; margin-top:10px; display:none;">
            Incorrect PIN. Try again.
        </p>
    </div>
</div>

<!-- ================= SETUP PIN MODAL ================= -->
<div id="setupPinModal" class="modal">
    <div class="modal-content" style="max-width:340px; text-align:center;">
        <span class="close" onclick="closeModal('setupPin')">&times;</span>
        <div style="font-size:36px; margin-bottom:8px;">🔑</div>
        <h2 style="margin-bottom:6px;">Set Up Dashboard PIN</h2>
        <p style="color:#888; font-size:13px; margin-bottom:4px;" id="setupPinLabel">
            Enter a 4-digit PIN to protect the user dashboard
        </p>
        <div id="setupPinDots" style="display:flex; justify-content:center; gap:12px; margin-bottom:20px; margin-top:14px;">
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
        </div>
        <div class="pin-pad">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                <button type="button" class="pin-key"
                    onclick="<?= $k === '⌫' ? 'setupPinBackspace()' : ($k === '' ? '' : "setupPinPress($k)") ?>">
                    <?= $k ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="setupPinError" style="color:#dc3545; font-size:13px; margin-top:10px; display:none;"></p>
    </div>
</div>

<script>
/* ===================================================
   MODAL OPEN / CLOSE
=================================================== */
function openModal(type) {
    document.getElementById(type === 'add' ? 'menuModal' : type + 'Modal').classList.add('show');
}
function closeModal(type) {
    const map = { add: 'menuModal', edit: 'editModal', pin: 'pinModal', setupPin: 'setupPinModal' };
    const modal = document.getElementById(map[type]);
    if (!modal) return;
    modal.classList.remove('show');
}
document.addEventListener('click', function(e) {
    ['menuModal','editModal','pinModal','setupPinModal','accountModal'].forEach(id => {
        const m = document.getElementById(id);
        if (m && e.target === m) m.classList.remove('show');
    });
});

/* ===================================================
   IMAGE UPLOAD
=================================================== */
function handleImageUpload(input, prefix) {
    const file = input.files[0];
    if (!file) return;

    const statusEl  = document.getElementById(prefix + 'UploadStatus');
    const previewEl = document.getElementById(prefix + 'ImgPreview');
    const placeholderEl = document.getElementById(prefix + 'ImgPlaceholder');
    const submitBtn = document.getElementById(prefix === 'add' ? 'addSubmitBtn' : null);

    statusEl.textContent  = '⏳ Uploading...';
    statusEl.style.color  = '#888';

    const formData = new FormData();
    formData.append('image', file);

    fetch('helpers/admindashboard_helpers.php?action=upload_image', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById(prefix + '_image_url').value = data.url;
                previewEl.src          = data.url;
                previewEl.style.display = 'block';
                if (placeholderEl) placeholderEl.style.display = 'none';
                statusEl.textContent   = '✅ Image uploaded!';
                statusEl.style.color   = '#28a745';
            } else {
                statusEl.textContent   = '❌ ' + data.message;
                statusEl.style.color   = '#dc3545';
            }
        })
        .catch(() => {
            statusEl.textContent = '❌ Upload failed. Try again.';
            statusEl.style.color = '#dc3545';
        });
}

/* Drag and drop */
['addDropZone','editDropZone'].forEach(zoneId => {
    const zone = document.getElementById(zoneId);
    if (!zone) return;
    const prefix = zoneId.replace('DropZone','');
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const input = document.getElementById(prefix + 'ImageInput');
        input.files = e.dataTransfer.files;
        handleImageUpload(input, prefix);
    });
});

/* ===================================================
   EDIT MODAL — fetch item data
=================================================== */
function openEditModal(id) {
    fetch('helpers/admindashboard_helpers.php?action=edit_menu&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert('Could not load item.'); return; }
            const item = data.item;

            document.getElementById('edit_id').value       = item.menu_item_id;
            document.getElementById('edit_name').value     = item.item_name;
            document.getElementById('edit_desc').value     = item.description   || '';
            document.getElementById('edit_price').value    = item.price;
            document.getElementById('edit_stock').value    = item.stock_quantity;
            document.getElementById('edit_category').value = item.category;
            document.getElementById('edit_available').checked = item.is_available == 1;
            document.getElementById('edit_image_url').value   = item.image_url || '';

            const preview     = document.getElementById('editImgPreview');
            const placeholder = document.getElementById('editImgPlaceholder');
            if (item.image_url) {
                preview.src           = item.image_url;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                preview.style.display     = 'none';
                placeholder.style.display = 'flex';
            }

            document.getElementById('editUploadStatus').textContent = '';
            document.getElementById('editModal').classList.add('show');
        });
}

/* ===================================================
   PIN SYSTEM
=================================================== */
let pinValue    = '';
const PIN_HAS   = <?= json_encode(!empty($_SESSION['dashboard_pin_set'] ?? false)) ?>;

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

function openPinModal() {
    fetch('helpers/admindashboard_helpers.php?action=check_pin')
        .then(r => r.json())
        .then(data => {
            if (data.has_pin) {
                pinValue = '';
                updatePinDots('pinDots', 0);
                document.getElementById('pinError').style.display = 'none';
                document.getElementById('pinModal').classList.add('show');
            } else {
                setupPinStep   = 1;
                setupPinFirst  = '';
                setupPinCurrent = '';
                updatePinDots('setupPinDots', 0);
                document.getElementById('setupPinLabel').textContent = 'Enter a 4-digit PIN to protect the user dashboard';
                document.getElementById('setupPinError').style.display = 'none';
                document.getElementById('setupPinModal').classList.add('show');
            }
        });
}

/* -- Verify PIN modal -- */
function pinPress(num) {
    if (pinValue.length >= 4) return;
    pinValue += num;
    updatePinDots('pinDots', pinValue.length);
    if (pinValue.length === 4) verifyPin();
}
function pinBackspace() {
    pinValue = pinValue.slice(0, -1);
    updatePinDots('pinDots', pinValue.length);
}
function verifyPin() {
    fetch('helpers/admindashboard_helpers.php?action=check_pin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'pin=' + encodeURIComponent(pinValue)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'userdashboard.php';
        } else {
            document.getElementById('pinError').style.display = 'block';
            pinValue = '';
            updatePinDots('pinDots', 0);
            /* Shake animation */
            const dots = document.getElementById('pinDots');
            dots.classList.add('pin-shake');
            setTimeout(() => dots.classList.remove('pin-shake'), 500);
        }
    });
}

/* -- Setup PIN modal -- */
let setupPinStep    = 1;
let setupPinFirst   = '';
let setupPinCurrent = '';

function setupPinPress(num) {
    if (setupPinCurrent.length >= 4) return;
    setupPinCurrent += num;
    updatePinDots('setupPinDots', setupPinCurrent.length);
    if (setupPinCurrent.length === 4) {
        setTimeout(() => {
            if (setupPinStep === 1) {
                setupPinFirst   = setupPinCurrent;
                setupPinCurrent = '';
                setupPinStep    = 2;
                document.getElementById('setupPinLabel').textContent = 'Confirm your PIN';
                updatePinDots('setupPinDots', 0);
            } else {
                if (setupPinCurrent === setupPinFirst) {
                    savePin(setupPinCurrent);
                } else {
                    document.getElementById('setupPinError').textContent = "PINs don't match. Try again.";
                    document.getElementById('setupPinError').style.display = 'block';
                    setupPinCurrent = '';
                    setupPinFirst   = '';
                    setupPinStep    = 1;
                    document.getElementById('setupPinLabel').textContent = 'Enter a 4-digit PIN to protect the user dashboard';
                    updatePinDots('setupPinDots', 0);
                }
            }
        }, 150);
    }
}
function setupPinBackspace() {
    setupPinCurrent = setupPinCurrent.slice(0, -1);
    updatePinDots('setupPinDots', setupPinCurrent.length);
}
function savePin(pin) {
    fetch('helpers/admindashboard_helpers.php?action=save_pin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'pin=' + encodeURIComponent(pin)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('setupPinModal').classList.remove('show');
            window.location.href = 'userdashboard.php';
        } else {
            document.getElementById('setupPinError').textContent = 'Failed to save PIN. Try again.';
            document.getElementById('setupPinError').style.display = 'block';
        }
    });
}

/* -- Shared helpers -- */
function updatePinDots(containerId, count) {
    const dots = document.querySelectorAll('#' + containerId + ' .pin-dot');
    dots.forEach((dot, i) => {
        dot.classList.toggle('filled', i < count);
    });
}

// Expose modal functions so sidebar actions work consistently across pages
window.openAccountModal = openAccountModal;
window.openPinModal = openPinModal;
window.submitAccountForm = submitAccountForm;
window.closeModal = closeModal;

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
</script>

</body>
</html>
