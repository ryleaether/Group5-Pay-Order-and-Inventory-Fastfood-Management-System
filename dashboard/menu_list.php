<?php
session_start();
require_once __DIR__ . "/helpers/admindashboard_helpers.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id  = $_SESSION['admin_id'];
$dashboard = new MenuDashboardHelper($admin_id);
$menu_handler = $dashboard->getMenuItemHandler();

$db   = new Database();
$conn = $db->connect();
$adminProfile = [];
try {
    $stmt = $conn->prepare("SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id = :id");
    $stmt->bindParam(':id', $admin_id);
    $stmt->execute();
    $adminProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $adminProfile = []; }

$search   = $_GET['search']   ?? '';
$category = $_GET['category'] ?? '';
$status   = $_GET['status']   ?? '';

$items      = $menu_handler->searchAndFilter($search, $category, $status);
$categories = $menu_handler->getCategories();
$allItems   = $menu_handler->getAll();
$hasAnyItems= !empty($allItems);

$noResultsMessage = '';
if (empty($items)) {
    if (!$hasAnyItems) {
        $noResultsMessage = 'No menu items yet. Add your first item!';
    } else {
        $noResultsMessage = 'No items match your current filters.';
    }
}

function menu_item_image_src(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (preg_match('/^(?:https?:)?\/\//i', $url) || preg_match('/^data:image\//i', $url)) return $url;

    $url = str_replace('\\', '/', $url);
    $url = preg_replace('#^\./#', '', $url);
    while (strpos($url, '../') === 0) {
        $url = substr($url, 3);
    }
    if (strpos($url, 'dashboard/uploads/') === 0) {
        $url = substr($url, strlen('dashboard/'));
    }
    return $url;
}

$sidebar = new SidebarRenderer($admin_id, $_SESSION['fastfood_name'] ?? '', $adminProfile['fullname'] ?? $_SESSION['username'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Menu List</title>
    <link rel="stylesheet" href="../design/admin.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
</head>
<body>

<div class="dashboard">

    <?= $sidebar->render('menu') ?>

       <div class="topbar">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;background:rgba(255,255,255,0.15);border-radius:8px;flex-shrink:0;"><i class="fa-solid fa-utensils" style="font-size:1.5em;color:#fff;"></i></span>
                <div style="display:flex;flex-direction:column;justify-content:center;">
                    <h1 style="margin:0;line-height:1;">Menu Items</h1>
                    <p class="subtitle" style="margin:2px 0 0 0;">Manage your food items</p>
                </div>
            </div>

            <div class="search-filter">
                <div class="filter-form">
                    <div style="position:relative; flex:1; min-width:200px;">
                        <input type="text" id="liveSearch" placeholder="Search items..." autocomplete="off"
                               value="<?= htmlspecialchars($search) ?>"
                               style="width:100%; padding-right:36px;">
<span id="searchSpinner" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);display:none;font-size:14px;"><i class="fa-solid fa-spinner fa-spin"></i></span>                    </div>
                    <select id="liveCategory" autocomplete="off">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="liveStatus" autocomplete="off">
                        <option value="">All Status</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Available</option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Unavailable</option>
                    </select>
                    <button type="button" id="clearFiltersBtn" class="clear-btn">Clear</button>
                </div>
            </div>

            <a href="#" class="btn-add" onclick="openAddModal(); return false;">+ Add New Item</a>
        </div>

        <div class="menu-grid" id="menuGrid">
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $item): ?>
                    <div class="menu-card">
                        <div class="menu-img-wrap">
                            <?php if (!empty($item['image_url'])): ?>
                               <img src="<?= htmlspecialchars(menu_item_image_src($item['image_url'])) ?>"
                                     alt="<?= htmlspecialchars($item['item_name']) ?>"
                                     class="menu-img">
                            <?php else: ?>
                                <div class="menu-img-placeholder"><i class="fa-solid fa-utensils"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="menu-header">
                            <h3><?= htmlspecialchars($item['item_name']) ?></h3>
                            <span class="status <?= $item['is_available'] ? 'available' : 'unavailable' ?>">
                                <?= $item['is_available'] ? 'Available' : 'Unavailable' ?>
                            </span>
                        </div>
                        <div class="category"><?= htmlspecialchars($item['category']) ?></div>
                        <div class="price">₱<?= number_format($item['price'], 2) ?></div>
                        <div class="stock">Stock: <?= $item['stock_quantity'] ?></div>
                        <div class="actions">
                            <a class="btn edit" href="#" onclick="openEditModal(<?= $item['menu_item_id'] ?>); return false;"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                            <button class="btn delete js-delete-menu-item" data-item-id="<?= (int)$item['menu_item_id'] ?>" data-item-name="<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>"><i class="fa-solid fa-trash"></i> Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty-state" style="grid-column:1/-1;">
                    <?= htmlspecialchars($noResultsMessage) ?>
                </p>
            <?php endif; ?>
        </div>

 <?= $sidebar->renderClose() ?>
</div><!-- end .dashboard -->
</div>

<!-- ================= ADD MODAL ================= -->
<div id="menuModal" class="modal">
    <div class="modal-content modal-wide">
        <span class="close" onclick="closeMenuModal()">&times;</span>
        <h2><i class="fa-solid fa-burger" style="margin-right:6px;"></i> Add New Menu Item</h2>
        <form action="helpers/admindashboard_helpers.php?action=add_menu" method="POST" id="addForm">
            <input type="hidden" name="image_url" id="add_image_url">
            <div class="modal-two-col">
                <div class="img-upload-area" id="addDropZone"
                     onclick="document.getElementById('addImageInput').click()">
                    <div class="img-upload-placeholder" id="addImgPlaceholder">
                        <i class="fa-solid fa-camera" style="font-size:28px;color:#be185d;margin-bottom:6px;"></i>
                        <p>Click or drag to upload image</p>
                        <small>JPG, PNG, WEBP only · Max 2MB</small>
                        <small style="display:block; margin-top:4px; color:#bbb;">
                            <i class="fa-solid fa-ruler-combined"></i> Recommended: 500×500px<br>
                            Minimum: 300×300px<br>
                            Images will be cropped to fit
                        </small>
                    </div>
                    <img id="addImgPreview" class="img-preview" style="display:none;">
                    <input type="file" id="addImageInput" accept="image/*" style="display:none;"
                           onchange="handleImageUpload(this, 'add')">
                </div>
                <div class="modal-fields">
                    <input type="text"   name="item_name"      placeholder="Item Name"      required autocomplete="off">
                    <textarea           name="description"     placeholder="Description (optional)" autocomplete="off"></textarea>
                    <input type="number" name="price"          placeholder="Price (₱)" step="0.01" min="0" required autocomplete="off">
                    <input type="number" name="stock_quantity" placeholder="Stock Quantity" min="0" required autocomplete="off">
                    <input type="text"   name="category"       placeholder="Category" required autocomplete="off">
                    <label class="check-label">
                        <input type="checkbox" name="is_available" value="1" checked>
                        Available (visible to customers)
                    </label>
                </div>
            </div>
            <div id="addUploadStatus" class="upload-status"></div>
            <button type="submit" class="btn-save" id="addSubmitBtn"><i class="fa-solid fa-floppy-disk"></i> Save Item</button>
        </form>
    </div>
</div>

<!-- ================= EDIT MODAL ================= -->
<div id="editModal" class="modal">
    <div class="modal-content modal-wide">
        <span class="close" onclick="closeEditModal()">&times;</span>
       <h2><i class="fa-solid fa-pen-to-square" style="margin-right:6px;"></i>Edit Menu Item</h2>
        <form action="helpers/admindashboard_helpers.php?action=edit_menu" method="POST" id="editForm">
            <input type="hidden" name="menu_item_id" id="edit_id">
            <input type="hidden" name="image_url"    id="edit_image_url">
            <div class="modal-two-col">
                <!-- LEFT: Image -->
                <div class="img-upload-area" id="editDropZone"
                     onclick="document.getElementById('editImageInput').click()">
                    <div class="img-upload-placeholder" id="editImgPlaceholder" style="display:none;">
                        <i class="fa-solid fa-camera" style="font-size:28px;color:#be185d;margin-bottom:6px;"></i>
                        <p>Click to change image</p>
                        <small>JPG, PNG, WEBP only · Max 2MB</small>
                        <small style="display:block; margin-top:4px; color:#bbb;">
                            <i class="fa-solid fa-ruler-combined"></i> Recommended: 500×500px<br>
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
                    <input type="text"   name="item_name"      id="edit_name"     placeholder="Item Name"      required autocomplete="off">
                    <textarea           name="description"     id="edit_desc"     placeholder="Description" autocomplete="off"></textarea>
                    <input type="number" name="price"          id="edit_price"    placeholder="Price (₱)" step="0.01" min="0" required autocomplete="off">
                    <input type="number" name="stock_quantity" id="edit_stock"    placeholder="Stock Quantity" min="0" required autocomplete="off">
                    <input type="text"   name="category"       id="edit_category" placeholder="Category" required autocomplete="off">
                    <label class="check-label">
                        <input type="checkbox" name="is_available" id="edit_available" value="1">
                        Available (visible to customers)
                    </label>
                </div>
            </div>
            <div id="editUploadStatus" class="upload-status"></div>
            <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Update Item</button>
        </form>
    </div>
</div>

<!-- ================= DELETE MODAL ================= -->
<div id="deleteMenuModal" class="modal">
    <div class="modal-content" style="max-width:380px; text-align:center;">
        <span class="close" onclick="closeDeleteMenuModal()">&times;</span>
        <div style="font-size:42px; margin-bottom:10px;"><i class="fa-solid fa-trash" style="color:#be185d;"></i></div>
        <h2 style="font-size:18px; margin-bottom:8px;">Remove Menu Item?</h2>
        <p style="font-size:14px; line-height:1.5; margin-bottom:22px;">
            This will move <strong id="deleteMenuItemName"></strong> to trash. You can restore this item within 30 days.
        </p>
        <input type="hidden" id="deleteMenuItemId">
        <div style="display:flex; gap:10px;">
            <button type="button" class="btn" style="flex:1; justify-content:center; background:#f3f4f6; color:#4b5563;" onclick="closeDeleteMenuModal()">Cancel</button>
            <button type="button" class="btn delete" style="flex:1; justify-content:center;" onclick="confirmSoftDeleteItem()"><i class="fa-solid fa-trash"></i> Remove</button>
        </div>
    </div>
</div>

<!-- ================= ACCOUNT MODAL ================= -->
<div id="accountModal" class="modal">
    <div class="modal-content" style="max-width:420px; text-align:left;">
        <span class="close" onclick="closeAccountModal()">&times;</span>
        <h2 style="margin-bottom:12px;"><i class="fa-solid fa-circle-user" style="margin-right:6px;"></i> Edit Account</h2>
        <p style="color:#555; font-size:14px; margin-bottom:18px;">Update your account information.</p>
        <form id="accountForm" onsubmit="submitAccountForm(event)">
            <div style="display:grid; gap:12px;">
                <input type="text"     name="fullname"         placeholder="Full Name"     value="<?= htmlspecialchars($adminProfile['fullname']      ?? '') ?>" required autocomplete="name">
                <input type="text"     name="fastfood_name"    placeholder="Fastfood Name" value="<?= htmlspecialchars($adminProfile['fastfood_name']  ?? '') ?>" required autocomplete="organization">
                <input type="text"     name="username"         placeholder="Username"      value="<?= htmlspecialchars($adminProfile['username']       ?? '') ?>" required autocomplete="username">
                <input type="email"    name="email"            placeholder="Email"         value="<?= htmlspecialchars($adminProfile['email']          ?? '') ?>" required autocomplete="email">
                <input type="password" name="new_password"     placeholder="New Password (leave blank to keep)" autocomplete="new-password">
                <input type="password" name="confirm_password" placeholder="Confirm New Password"              autocomplete="new-password">
                <div id="accountMessage" style="display:none; padding:12px; border-radius:10px; font-size:13px;"></div>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= PIN MODAL ================= -->
<div id="pinModal" class="modal">
    <div class="modal-content" style="max-width:340px; text-align:center;">
        <span class="close" onclick="closePinModal()">&times;</span>
        <div style="font-size:36px; margin-bottom:8px;"><i class="fa-solid fa-lock" style="color:#be185d;"></i></div>
        <h2 style="margin-bottom:6px;">Switch to User Dashboard</h2>
        <p style="color:#888; font-size:13px; margin-bottom:20px;">Enter your dashboard PIN to continue</p>
        <div id="pinDots" style="display:flex; justify-content:center; gap:12px; margin-bottom:20px;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
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
        <span class="close" onclick="closeSetupPinModal()">&times;</span>
        <div style="font-size:36px; margin-bottom:8px;"><i class="fa-solid fa-key" style="color:#be185d;"></i></div>
        <h2 style="margin-bottom:6px;">Set Up Dashboard PIN</h2>
        <p style="color:#888; font-size:13px; margin-bottom:4px;" id="setupPinLabel">
            Enter a 4-digit PIN to protect the user dashboard
        </p>
        <div id="setupPinDots" style="display:flex; justify-content:center; gap:12px; margin-bottom:20px; margin-top:14px;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
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
/* ── MODAL OPEN/CLOSE ── */
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const main = document.getElementById('mainContent');
    if (!sidebar) return;
    const collapsed = sidebar.classList.toggle('sidebar-collapsed');
    if (main) main.classList.toggle('main-expanded', collapsed);
    localStorage.setItem('ipos_sidebar_collapsed', collapsed ? '1' : '0');
}
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('ipos_sidebar_collapsed') === '1') {
        document.querySelector('.sidebar')?.classList.add('sidebar-collapsed');
        document.getElementById('mainContent')?.classList.add('main-expanded');
    }
});

function openAddModal()       { document.getElementById('menuModal').classList.add('show'); }
function closeMenuModal()     { document.getElementById('menuModal').classList.remove('show'); }
function closeEditModal()     { document.getElementById('editModal').classList.remove('show'); }
function closeDeleteMenuModal() { document.getElementById('deleteMenuModal').classList.remove('show'); }
function closeAccountModal()  { document.getElementById('accountModal').classList.remove('show'); }
function closePinModal()      { document.getElementById('pinModal').classList.remove('show'); }
function closeSetupPinModal() { document.getElementById('setupPinModal').classList.remove('show'); }

function menuImageSrc(url) {
    url = String(url || '').trim();
    if (!url) return '';
    if (/^(https?:)?\/\//i.test(url) || /^data:image\//i.test(url)) return url;
    url = url.replace(/\\/g, '/').replace(/^\.\//, '');
    while (url.startsWith('../')) url = url.slice(3);
    if (url.startsWith('dashboard/uploads/')) url = url.slice('dashboard/'.length);
    return url;
}

/* Expose globally for sidebar onclick */
window.openAccountModal = function() {
    document.getElementById('accountMessage').style.display = 'none';
    document.getElementById('accountModal').classList.add('show');
};
window.openPinModal = function() {
    fetch('helpers/admindashboard_helpers.php?action=check_pin')
        .then(r => r.json())
        .then(data => {
            if (data.has_pin) {
                pinValue = '';
                updatePinDots('pinDots', 0);
                document.getElementById('pinError').style.display = 'none';
                document.getElementById('pinModal').classList.add('show');
            } else {
                setupPinStep = 1; setupPinFirst = ''; setupPinCurrent = '';
                updatePinDots('setupPinDots', 0);
                document.getElementById('setupPinLabel').textContent = 'Enter a 4-digit PIN to protect the user dashboard';
                document.getElementById('setupPinError').style.display = 'none';
                document.getElementById('setupPinModal').classList.add('show');
            }
        });
};

/* Close on backdrop click */
document.addEventListener('click', function(e) {
    [
        ['menuModal',     closeMenuModal],
        ['editModal',     closeEditModal],
        ['deleteMenuModal', closeDeleteMenuModal],
        ['accountModal',  closeAccountModal],
        ['pinModal',      closePinModal],
        ['setupPinModal', closeSetupPinModal]
    ].forEach(([id, fn]) => {
        const m = document.getElementById(id);
        if (m && e.target === m) fn();
    });
});

/* ── EDIT MODAL ── */
function openEditModal(id) {
    fetch('helpers/admindashboard_helpers.php?action=edit_menu&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert('Could not load item.'); return; }
            const item = data.item;

            document.getElementById('edit_id').value          = item.menu_item_id;
            document.getElementById('edit_name').value        = item.item_name;
            document.getElementById('edit_desc').value        = item.description || '';
            document.getElementById('edit_price').value       = item.price;
            document.getElementById('edit_stock').value       = item.stock_quantity;
            document.getElementById('edit_category').value    = item.category;
            document.getElementById('edit_available').checked = item.is_available == 1;
            document.getElementById('edit_image_url').value   = item.image_url || '';
            document.getElementById('editUploadStatus').textContent = '';

            const preview     = document.getElementById('editImgPreview');
            const placeholder = document.getElementById('editImgPlaceholder');
            if (item.image_url) {
                preview.src               = menuImageSrc(item.image_url);
                preview.style.display     = 'block';
                placeholder.style.display = 'none';
            } else {
                preview.style.display     = 'none';
                placeholder.style.display = 'flex';
            }
            document.getElementById('editModal').classList.add('show');
        })
        .catch(() => alert('Failed to load item.'));
}

/* ── IMAGE UPLOAD ── */
function handleImageUpload(input, prefix) {
    const file = input.files[0];
    if (!file) return;

    const statusEl     = document.getElementById(prefix + 'UploadStatus');
    const previewEl    = document.getElementById(prefix + 'ImgPreview');
    const placeholderEl= document.getElementById(prefix + 'ImgPlaceholder');

    statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
    statusEl.style.color = '#888';

    const formData = new FormData();
    formData.append('image', file);

    fetch('helpers/admindashboard_helpers.php?action=upload_image', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById(prefix + '_image_url').value = data.url;
                previewEl.src               = menuImageSrc(data.url);
                previewEl.style.display     = 'block';
                if (placeholderEl) placeholderEl.style.display = 'none';
                statusEl.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#28a745;"></i> Image uploaded!';
                statusEl.style.color = '#28a745';
            } else {
                statusEl.innerHTML = '<i class="fa-solid fa-circle-xmark" style="color:#dc3545;"></i> ' + data.message;
                statusEl.style.color = '#dc3545';
            }
        })
        .catch(() => {
            statusEl.innerHTML = '<i class="fa-solid fa-circle-xmark" style="color:#dc3545;"></i> Upload failed. Try again.';
            statusEl.style.color = '#dc3545';
        });
}

/* Drag and drop */
['addDropZone','editDropZone'].forEach(zoneId => {
    const zone = document.getElementById(zoneId);
    if (!zone) return;
    const prefix = zoneId.replace('DropZone','');
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const input = document.getElementById(prefix + 'ImageInput');
        input.files = e.dataTransfer.files;
        handleImageUpload(input, prefix);
    });
});

/* ── ACCOUNT FORM ── */
function submitAccountForm(event) {
    event.preventDefault();
    const form    = document.getElementById('accountForm');
    const message = document.getElementById('accountMessage');
    const data    = new URLSearchParams(new FormData(form));

    fetch('helpers/admindashboard_helpers.php?action=update_account', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    data.toString()
    })
    .then(r => r.json())
    .then(result => {
        message.style.display    = 'block';
        message.textContent      = result.message;
        message.style.background = result.success ? '#e6ffed' : '#ffe6e6';
        message.style.color      = result.success ? '#1f7a3c' : '#9b1f1f';
        message.style.border     = result.success ? '1px solid #8cd19e' : '1px solid #ea9a9a';
        if (result.success) setTimeout(() => location.reload(), 900);
    });
}

/* ── PIN VERIFY ── */
let pinValue = '';
function pinPress(num) {
    if (pinValue.length >= 4) return;
    pinValue += String(num);
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
            const dots = document.getElementById('pinDots');
            dots.classList.add('pin-shake');
            setTimeout(() => dots.classList.remove('pin-shake'), 500);
        }
    });
}

/* ── PIN SETUP ── */
let setupPinStep = 1, setupPinFirst = '', setupPinCurrent = '';
function setupPinPress(num) {
    if (setupPinCurrent.length >= 4) return;
    setupPinCurrent += String(num);
    updatePinDots('setupPinDots', setupPinCurrent.length);
    if (setupPinCurrent.length === 4) {
        setTimeout(() => {
            if (setupPinStep === 1) {
                setupPinFirst = setupPinCurrent; setupPinCurrent = ''; setupPinStep = 2;
                document.getElementById('setupPinLabel').textContent = 'Confirm your PIN';
                updatePinDots('setupPinDots', 0);
            } else {
                if (setupPinCurrent === setupPinFirst) {
                    savePin(setupPinCurrent);
                } else {
                    document.getElementById('setupPinError').textContent = "PINs don't match. Try again.";
                    document.getElementById('setupPinError').style.display = 'block';
                    setupPinCurrent = ''; setupPinFirst = ''; setupPinStep = 1;
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
            closeSetupPinModal();
            window.location.href = 'userdashboard.php';
        } else {
            document.getElementById('setupPinError').textContent = 'Failed to save PIN. Try again.';
            document.getElementById('setupPinError').style.display = 'block';
        }
    });
}

/* ── LIVE SEARCH ── */
function fetchMenu() {
    const searchInput  = document.getElementById('liveSearch');
    const categorySel  = document.getElementById('liveCategory');
    const statusSel    = document.getElementById('liveStatus');
    const grid         = document.getElementById('menuGrid');
    const spinner      = document.getElementById('searchSpinner');

    const params = new URLSearchParams({
        search:   searchInput.value,
        category: categorySel.value,
        status:   statusSel.value,
        ajax:     '1'
    });

    spinner.style.display = 'inline';

    fetch('helpers/admindashboard_helpers.php?action=search_menu&' + params.toString())
        .then(r => r.json())
        .then(data => {
            spinner.style.display = 'none';
            if (!data.success) { grid.innerHTML = '<p class="empty-state" style="grid-column:1/-1;"><i class="fa-solid fa-triangle-exclamation"></i> Error loading items.</p>'; return; }

            if (data.items.length === 0) {
                grid.innerHTML = '<p class="empty-state" style="grid-column:1/-1;">' + (data.has_any ? 'No items match your current filters.' : 'No menu items yet. Add your first item!') + '</p>';
                return;
            }

            grid.innerHTML = data.items.map(item => `
                <div class="menu-card">
                    <div class="menu-img-wrap">
                        ${item.image_url
                            ? `<img src="${escHtml(menuImageSrc(item.image_url))}" alt="${escHtml(item.item_name)}" class="menu-img">`
                            : `<div class="menu-img-placeholder"><i class="fa-solid fa-utensils"></i></div>`}
                    </div>
                    <div class="menu-header">
                        <h3>${escHtml(item.item_name)}</h3>
                        <span class="status ${item.is_available == 1 ? 'available' : 'unavailable'}">
                            ${item.is_available == 1 ? 'Available' : 'Unavailable'}
                        </span>
                    </div>
                    <div class="category">${escHtml(item.category)}</div>
                    <div class="price">₱${parseFloat(item.price).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
                    <div class="stock">Stock: ${item.stock_quantity}</div>
                    <div class="actions">
                        <a class="btn edit" href="#" onclick="openEditModal(${item.menu_item_id}); return false;"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                        <button class="btn delete js-delete-menu-item" data-item-id="${item.menu_item_id}" data-item-name="${escHtml(item.item_name)}"><i class="fa-solid fa-trash"></i> Delete</button>
                    </div>
                </div>
            `).join('');
            bindMenuDeleteButtons();
        })
        .catch(() => {
            spinner.style.display = 'none';
            grid.innerHTML = '<p class="empty-state" style="grid-column:1/-1;"><i class="fa-solid fa-triangle-exclamation"></i> Network error. Please try again.</p>';
        });
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── LIVE SEARCH EVENT LISTENERS ── */
(function() {
    const searchInput  = document.getElementById('liveSearch');
    const categorySel  = document.getElementById('liveCategory');
    const statusSel    = document.getElementById('liveStatus');
    const clearBtn     = document.getElementById('clearFiltersBtn');
    let   debounceTimer;

    /* Debounce text input (300ms), instant on dropdowns */
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchMenu, 300);
    });
    categorySel.addEventListener('change', fetchMenu);
    statusSel.addEventListener('change', fetchMenu);

    clearBtn.addEventListener('click', () => {
        searchInput.value  = '';
        categorySel.value  = '';
        statusSel.value    = '';
        fetchMenu();
    });
})();

    // ── SOFT DELETE ITEM ─────────────────────────────────────────────
function bindMenuDeleteButtons() {
    document.querySelectorAll('.js-delete-menu-item').forEach(btn => {
        btn.onclick = () => openDeleteMenuModal(btn.dataset.itemId, btn.dataset.itemName);
    });
}

function openDeleteMenuModal(itemId, itemName) {
    document.getElementById('deleteMenuItemId').value = itemId;
    document.getElementById('deleteMenuItemName').textContent = itemName;
    document.getElementById('deleteMenuModal').classList.add('show');
}

function showMenuNotice(message, type = 'info') {
    const notice = document.createElement('div');
    notice.textContent = message;
    notice.style.position = 'fixed';
    notice.style.right = '24px';
    notice.style.bottom = '24px';
    notice.style.zIndex = '10000';
    notice.style.padding = '12px 16px';
    notice.style.borderRadius = '10px';
    notice.style.color = '#fff';
    notice.style.fontSize = '13px';
    notice.style.fontWeight = '700';
    notice.style.boxShadow = '0 12px 30px rgba(0,0,0,0.18)';
    notice.style.background = type === 'success' ? '#16a34a' : (type === 'error' ? '#dc2626' : '#2563eb');
    document.body.appendChild(notice);
    setTimeout(() => notice.remove(), 2600);
}

document.addEventListener('DOMContentLoaded', bindMenuDeleteButtons);

    async function confirmSoftDeleteItem() {
        const itemId = document.getElementById('deleteMenuItemId').value;
        try {
            const fd = new FormData();
            fd.append('action', 'soft_delete');
            fd.append('type', 'item');
            fd.append('id', itemId);
            const res  = await fetch('soft_delete_handler.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showMenuNotice(data.message || 'Menu item moved to trash', 'success');
                closeDeleteMenuModal();
                fetchMenu();
            } else {
                showMenuNotice(data.message || 'Failed to delete item.', 'error');
            }
        } catch(e) {
            showMenuNotice('Connection error. Please try again.', 'error');
        }
    }

</script>

</body>
</html>
