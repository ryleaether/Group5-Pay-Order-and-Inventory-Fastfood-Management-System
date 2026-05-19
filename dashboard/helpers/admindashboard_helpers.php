<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../validation.php";

/**
 * ============================================
 * MenuItem Class - Handle Menu Item Operations
 * ============================================
 */
class MenuItem {
    private $conn;
    private $table = "menu_items";
    private $admin_id;

    public function __construct($admin_id) {
        $db = new Database();
        $this->conn = $db->connect();
        $this->admin_id = $admin_id;
    }

    public function getAll() {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE admin_id = :id ORDER BY created_at DESC");
        $stmt->bindParam(":id", $this->admin_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($menu_item_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE menu_item_id = :id AND admin_id = :admin_id");
        $stmt->bindParam(":id", $menu_item_id);
        $stmt->bindParam(":admin_id", $this->admin_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($item_name, $description, $price, $stock_quantity, $category, $is_available, $image_url = null) {
        $is_available = $is_available ? 1 : 0;
        $sql = "INSERT INTO {$this->table}
                (admin_id, item_name, description, price, stock_quantity, category, is_available, image_url)
                VALUES (:admin_id, :name, :desc, :price, :stock_quantity, :category, :is_available, :image_url)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":admin_id",      $this->admin_id);
        $stmt->bindParam(":name",          $item_name);
        $stmt->bindParam(":desc",          $description);
        $stmt->bindParam(":price",         $price);
        $stmt->bindParam(":stock_quantity", $stock_quantity);
        $stmt->bindParam(":category",      $category);
        $stmt->bindParam(":is_available",  $is_available);
        $stmt->bindParam(":image_url",     $image_url);
        return $stmt->execute();
    }

    public function update($menu_item_id, $item_name, $description, $price, $stock_quantity, $category, $is_available, $image_url = null) {
        $is_available = $is_available ? 1 : 0;
        if (empty($image_url)) {
            $existing = $this->getById($menu_item_id);
            $image_url = $existing['image_url'] ?? null;
        }
        $sql = "UPDATE {$this->table} SET
                item_name      = :name,
                description    = :desc,
                price          = :price,
                stock_quantity = :stock_quantity,
                category       = :category,
                is_available   = :is_available,
                image_url      = :image_url
                WHERE menu_item_id = :id AND admin_id = :admin_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":name",           $item_name);
        $stmt->bindParam(":desc",           $description);
        $stmt->bindParam(":price",          $price);
        $stmt->bindParam(":stock_quantity", $stock_quantity);
        $stmt->bindParam(":category",       $category);
        $stmt->bindParam(":is_available",   $is_available);
        $stmt->bindParam(":image_url",      $image_url);
        $stmt->bindParam(":id",             $menu_item_id);
        $stmt->bindParam(":admin_id",       $this->admin_id);
        return $stmt->execute();
    }

    public function delete($menu_item_id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE menu_item_id = :id AND admin_id = :admin_id");
        $stmt->bindParam(":id", $menu_item_id);
        $stmt->bindParam(":admin_id", $this->admin_id);
        return $stmt->execute();
    }

    public function searchAndFilter($search = '', $category = '', $status = '') {
        $sql = "SELECT * FROM {$this->table} WHERE admin_id = :admin_id";
        $params = [':admin_id' => $this->admin_id];
        if (!empty($search)) {
            $sql .= " AND (item_name LIKE :search OR description LIKE :search OR category LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        if (!empty($category)) {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }
        if ($status !== '') {
            $sql .= " AND is_available = :status";
            $params[':status'] = (int)$status;
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories() {
        $stmt = $this->conn->prepare("SELECT DISTINCT category FROM {$this->table} WHERE admin_id = :admin_id ORDER BY category");
        $stmt->bindParam(":admin_id", $this->admin_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

/**
 * ==========================================
 * ImageUploader Class - Handle Image Uploads
 * ==========================================
 */
class ImageUploader {
    private $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    private $max_file_size = 2 * 1024 * 1024;
    private $upload_dir;
    private $admin_id;

    public function __construct($admin_id) {
        $this->admin_id = $admin_id;
        $this->upload_dir = __DIR__ . "/../uploads/";
    }

    private function validateFile($file) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error.'];
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowed_extensions)) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, WEBP allowed.'];
        }
        if ($file['size'] > $this->max_file_size) {
            return ['success' => false, 'message' => 'File too large. Max 2MB.'];
        }
        return ['success' => true];
    }

    public function upload($file) {
        $validation = $this->validateFile($file);
        if (!$validation['success']) return $validation;
        if (!is_dir($this->upload_dir)) mkdir($this->upload_dir, 0755, true);
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'item_' . $this->admin_id . '_' . time() . '.' . $ext;
        $filepath = $this->upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => true, 'filename' => $filename, 'url' => '../uploads/' . $filename];
        }
        return ['success' => false, 'message' => 'Failed to save file.'];
    }
}

/**
 * ========================================
 * PINManager Class - Handle PIN Operations
 * ========================================
 */
class PINManager {
    private $conn;
    private $table = "admins";
    private $admin_id;

    public function __construct($admin_id) {
        $db = new Database();
        $this->conn = $db->connect();
        $this->admin_id = $admin_id;
    }

    public function hasPIN() {
        $stmt = $this->conn->prepare("SELECT dashboard_pin FROM {$this->table} WHERE admin_id = :id");
        $stmt->bindParam(":id", $this->admin_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['dashboard_pin']);
    }

    public function verifyPIN($entered_pin) {
        if (strlen($entered_pin) !== 4 || !ctype_digit($entered_pin)) return false;
        $stmt = $this->conn->prepare("SELECT dashboard_pin FROM {$this->table} WHERE admin_id = :id");
        $stmt->bindParam(":id", $this->admin_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($entered_pin, $row['dashboard_pin'])) return true;
        return false;
    }

    public function savePIN($pin) {
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            return ['success' => false, 'message' => 'PIN must be exactly 4 digits.'];
        }
        $hashed = password_hash($pin, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET dashboard_pin = :pin WHERE admin_id = :id");
        $stmt->bindParam(":pin", $hashed);
        $stmt->bindParam(":id",  $this->admin_id);
        if ($stmt->execute()) return ['success' => true];
        return ['success' => false, 'message' => 'Failed to save PIN.'];
    }
}

/**
 * =============================================
 * MenuDashboardHelper - Main Coordinator Class
 * =============================================
 */
class MenuDashboardHelper {
    private $menu_item;
    private $image_uploader;
    private $pin_manager;
    private $admin_id;

    public function __construct($admin_id) {
        $this->admin_id = $admin_id;
        $this->menu_item = new MenuItem($admin_id);
        $this->image_uploader = new ImageUploader($admin_id);
        $this->pin_manager = new PINManager($admin_id);
    }

    public function getMenuItemHandler()  { return $this->menu_item; }
    public function getImageUploader()    { return $this->image_uploader; }
    public function getPINManager()       { return $this->pin_manager; }

    public function createMenuItem($post_data, $file_data = null) {
        $image_url = null;
        if ($file_data) {
            $upload_result = $this->image_uploader->upload($file_data);
            if ($upload_result['success']) $image_url = $upload_result['url'];
        }
        $is_available = isset($post_data['is_available']) ? 1 : 0;
        return $this->menu_item->create(
            $post_data['item_name'] ?? '', $post_data['description'] ?? '',
            $post_data['price'] ?? 0, $post_data['stock_quantity'] ?? 0,
            $post_data['category'] ?? '', $is_available, $image_url
        );
    }

    public function updateMenuItem($post_data, $file_data = null) {
        $menu_item_id = $post_data['menu_item_id'] ?? null;
        $image_url = null;
        if ($file_data) {
            $upload_result = $this->image_uploader->upload($file_data);
            if ($upload_result['success']) $image_url = $upload_result['url'];
        } elseif (!empty($post_data['image_url'])) {
            $image_url = $post_data['image_url'];
        }
        $is_available = isset($post_data['is_available']) ? 1 : 0;
        return $this->menu_item->update(
            $menu_item_id, $post_data['item_name'] ?? '', $post_data['description'] ?? '',
            $post_data['price'] ?? 0, $post_data['stock_quantity'] ?? 0,
            $post_data['category'] ?? '', $is_available, $image_url
        );
    }
}

/**
 * ===============================================
 * SidebarRenderer - Render dashboard sidebar
 * ===============================================
 */
class SidebarRenderer {
    private $admin_id;
    private $fastfood_name;
    private $admin_name;
    private $username;
    private $email;
    private $logo_url;
    private $profile_photo; // ← FIXED: was missing, caused deprecation warning

    public function __construct($admin_id, $fastfood_name = '', $admin_name = '', $username = '', $email = '') {
        $this->admin_id      = $admin_id;
        $this->fastfood_name = $fastfood_name;
        $this->admin_name    = $admin_name;
        $this->username      = $username;
        $this->email         = $email;

        try {
            static $extCache = [];
            if (!isset($extCache[$admin_id])) {
                $db   = new Database();
                $conn = $db->connect();
                $stmt = $conn->prepare("SELECT logo_url, profile_photo FROM admin_extended WHERE admin_id = :id");
                $stmt->execute([':id' => $admin_id]);
                $extCache[$admin_id] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }
            $this->logo_url      = $extCache[$admin_id]['logo_url'] ?? '';
            $this->profile_photo = $extCache[$admin_id]['profile_photo'] ?? '';
        } catch (Exception $e) {
            $this->logo_url      = '';
            $this->profile_photo = '';
        }
    }

    public function render($active_page = 'dashboard') {
        $is        = fn($page) => $active_page === $page ? 'active' : '';
        $name      = htmlspecialchars($this->fastfood_name ?? '');
        $adminName = htmlspecialchars($this->admin_name ?? 'Admin');
        $username  = htmlspecialchars($this->username ?? '');
        $email     = htmlspecialchars($this->email ?? '');
        $initial   = strtoupper(substr(strip_tags($adminName), 0, 1)) ?: 'A';

        ob_start(); ?>
        <!-- ===== SIDEBAR ===== -->
        <div class="sidebar">

            <!-- Brand / Logo -->
            <div class="logo">
                <?php if (!empty($this->logo_url)): ?>
                    <div class="logo-icon-box" style="background:none;padding:0;overflow:hidden;border-radius:8px;flex-shrink:0;">
                        <img src="<?= htmlspecialchars('../' . ltrim($this->logo_url, './')) ?>"
                             alt="Logo" style="width:38px;height:38px;object-fit:cover;border-radius:8px;display:block;">
                    </div>
                <?php else: ?>
    <?php include __DIR__ . '/../helpers/ipos_logo.php'; ?>
<?php endif; ?>

                <div class="logo-text">
                    <h2><?= $name ?: 'iPOS' ?></h2>
                    <p>I Pay, I Order, I Serve</p>
                </div>
            </div>


            <!-- User Profile -->
            <div class="sidebar-profile" onclick="window.location.href='account_pin_gate.php'" title="Click to manage account" style="cursor:pointer;">
                <div class="user-avatar" style="<?= !empty($this->profile_photo) ? 'background:none;padding:0;overflow:hidden;' : '' ?>">
                    <?php if (!empty($this->profile_photo)): ?>
                        <img src="<?= htmlspecialchars('../' . ltrim($this->profile_photo, './')) ?>"
                             alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                    <?php else: ?>
                        <?= $initial ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?= $adminName ?></div>
                    <div class="user-role">Administrator</div>
                </div>
                <span class="profile-edit-btn" title="Account Dashboard">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                </span>
            </div>

            <!-- Navigation -->
            <div class="sidebar-nav">

                <div class="sidebar-section-label">Main</div>
                <ul>
                    <li class="<?= $is('dashboard') ?>">
                        <a href="admindashboard.php">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span> Dashboard
                        </a>
                    </li>
                </ul>

                <div class="sidebar-section-label">Management</div>
                <ul>
                    <li class="<?= $is('menu') ?>">
                        <a href="menu_list.php">
                            <span class="nav-icon"><i class="fa-solid fa-utensils"></i></span> Manage Menu
                        </a>
                    </li>
                    <li class="<?= $is('history') ?>">
                        <a href="order_history.php">
                           <span class="nav-icon"><i class="fa-solid fa-receipt"></i></span> Order History
                        </a>
                    </li>
                    <li class="<?= $is('staffs') ?>">
                        <a href="manage_staffs.php">
                           <span class="nav-icon"><i class="fa-solid fa-users"></i></span> Manage Staffs
                        </a>
                    </li>
                </ul>

                <div class="sidebar-section-label">Activity</div>
                <ul>
                    <li class="<?= $is('account') ?>">
                        <a href="account_pin_gate.php">
                            <span class="nav-icon"><i class="fa-solid fa-circle-user"></i></span>
 Account
                        </a>
                    </li>
                    <li class="<?= $is('staff_gate') ?>">
                        <a href="staff_gate.php">
                            <span class="nav-icon"><i class="fa-solid fa-arrows-rotate"></i></span> Switch to Staff Dashboard
                        </a>
                    </li>
                </ul>

                <div class="sidebar-divider"></div>

                <ul>
                    <li>
                        <a href="#" class="logout-link" onclick="confirmLogout()">
                            <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Profile Edit Modal -->
        <div id="profileModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeProfileModal()">
            <div class="modal-box">
                <div class="modal-header">
                    <div class="modal-avatar"><?= $initial ?></div>
                    <div>
                        <h3>Edit Profile</h3>
                        <p class="modal-subtitle">Update your account information</p>
                    </div>
                    <button class="modal-close" onclick="closeProfileModal()">✕</button>
                </div>
                <form id="profileForm" method="POST" action="update_profile.php">
                    <input type="hidden" name="admin_id" value="<?= (int)$this->admin_id ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?= $adminName ?>" placeholder="Enter full name" required>
                        </div>
                        <div class="form-group">
                            <label>Restaurant Name</label>
                            <input type="text" name="fastfood_name" value="<?= $name ?>" placeholder="Restaurant name" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" value="<?= $username ?>" placeholder="Enter username" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= $email ?>" placeholder="Enter email">
                        </div>
                    </div>
                    <div class="form-divider">Change Password <span>(leave blank to keep current)</span></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" placeholder="New password">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm new password">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Current Password <span class="required">* required to save</span></label>
                        <input type="password" name="current_password" placeholder="Enter current password to confirm changes" required>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeProfileModal()">Cancel</button>
                   <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
        /* ===== SIDEBAR — uses CSS vars, changed by theme ===== */
        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            font-family: 'Segoe UI', sans-serif;
            position: fixed;
            top: 0; left: 0;
            overflow-y: auto;
            z-index: 100;
        }
        .logo { display: flex; align-items: center; gap: 10px; padding: 1.2rem 1.2rem 1rem; }
        .logo-icon-box {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--accent-light, #f87171), var(--accent));
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 0.85rem; letter-spacing: -0.5px;
        }
        .logo-text h2 { color: #fff; font-size: 1.05rem; font-weight: 700; margin: 0; }
        .logo-text p  { color: rgba(255,255,255,0.45); font-size: 0.65rem; margin: 0; }

        /* USER PROFILE CARD */
        .sidebar-profile {
            display: flex; align-items: center; gap: 10px;
            margin: 0.5rem 0.8rem 0.8rem; padding: 0.75rem 0.9rem;
            background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; cursor: pointer; transition: background 0.2s, border-color 0.2s;
            text-decoration: none;
        }
        .sidebar-profile:hover { background: rgba(255,255,255,0.13); border-color: rgba(255,255,255,0.2); }
        .user-avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--accent-light, #f87171), var(--accent));
            color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem; flex-shrink: 0;
        }
        .user-info { flex: 1; overflow: hidden; }
        .user-name { color: #fff; font-size: 0.88rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { color: rgba(255,255,255,0.45); font-size: 0.68rem; }
        .profile-edit-btn {
            color: rgba(255,255,255,0.45); flex-shrink: 0;
            background: rgba(255,255,255,0.08); border-radius: 7px; padding: 5px;
            display: flex; transition: background 0.2s, color 0.2s;
        }
        .sidebar-profile:hover .profile-edit-btn {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }

        /* NAV */
        .sidebar-nav { flex: 1; padding: 0 0.5rem; }
        .sidebar-nav ul { list-style: none; margin: 0 0 0.4rem; padding: 0; }
        .sidebar-nav ul li a {
            display: flex; align-items: center; gap: 10px;
            padding: 0.6rem 0.9rem;
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            border-radius: 9px;
            font-size: 0.85rem;
            font-weight: 400;
            border: 1px solid transparent;
            transition: all 0.22s ease;
        }
        .sidebar-nav ul li a:hover {
            background: color-mix(in srgb, var(--accent) 10%, transparent);
            border-color: color-mix(in srgb, var(--accent) 28%, transparent);
            color: #fff;
            font-weight: 600;
            transform: translateX(5px);
            box-shadow: 0 0 12px color-mix(in srgb, var(--accent) 35%, transparent);
        }
        .sidebar-nav ul li.active a {
            background: var(--sidebar-active-bg);
            color: #fff;
            font-weight: 700;
            border-color: color-mix(in srgb, var(--accent-dark) 50%, transparent);
            box-shadow: 0 0 12px color-mix(in srgb, var(--accent-dark) 30%, transparent);
        }
        .sidebar-nav ul li.active a:hover {
            background: var(--accent-dark);
            transform: translateX(5px);
        }
    .nav-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 7px;
    font-size: 0.85rem;
    flex-shrink: 0;
    transition: background 0.2s, width 0.2s, height 0.2s;
}

.sidebar-nav ul li a:hover .nav-icon,
.sidebar-nav ul li.active a .nav-icon {
    background: rgba(255, 255, 255, 0.18);
}

.logout-link .nav-icon {
    background: rgba(248, 113, 113, 0.15);
}

.logout-link:hover .nav-icon {
    background: rgba(248, 113, 113, 0.25) !important;
}

/* ── Collapsed: remove box, center icon ── */
.sidebar.collapsed .nav-icon {
    background: transparent !important;
    border-radius: 0;
    width: auto;
    height: auto;
    justify-content: center;
}

.sidebar.collapsed .sidebar-nav ul li a {
    justify-content: center;
    padding: 0.6rem 0;
}

.sidebar.collapsed .logout-link .nav-icon {
    background: transparent !important;
}
        .activity-link { color: rgba(255,255,255,0.6) !important; }
        .activity-link:hover { background: rgba(255,255,255,0.1) !important; color: #fff !important; }
        .logout-link { color: rgba(255,100,100,0.8) !important; }
        .logout-link:hover { background: rgba(248,113,113,0.12) !important; color: #f87171 !important; }

        .sidebar-section-label {
            color: rgba(255,255,255,0.3); font-size: 0.63rem; font-weight: 700;
            letter-spacing: 0.1em; text-transform: uppercase; padding: 0.9rem 1rem 0.3rem;
        }
        .sidebar-divider { border: none; border-top: 1px solid rgba(255,255,255,0.07); margin: 0.5rem 0.9rem; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; }
        .modal-box { background: #fff; border-radius: 16px; width: 500px; max-width: 95vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,0.35); }
        .modal-header { display: flex; align-items: center; gap: 12px; padding: 1.3rem 1.5rem 1rem; border-bottom: 1px solid var(--border-color); }
        .modal-avatar { width: 44px; height: 44px; background: linear-gradient(135deg, var(--accent-light, #f87171), var(--accent)); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }
        .modal-header h3 { margin: 0; font-size: 1rem; color: var(--text-primary); font-weight: 700; }
        .modal-subtitle  { margin: 2px 0 0; font-size: 0.72rem; color: #999; }
        .modal-close { margin-left: auto; background: none; border: none; font-size: 1rem; cursor: pointer; color: #aaa; padding: 6px 8px; border-radius: 8px; }
        .modal-close:hover { background: var(--accent-light); color: var(--text-primary); }
        #profileForm { padding: 1.2rem 1.5rem 1.5rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.3rem; }
        .form-group .required { color: var(--accent); font-weight: 400; }
        .form-group input { width: 100%; box-sizing: border-box; padding: 0.55rem 0.8rem; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.84rem; color: var(--text-primary); outline: none; transition: border-color 0.2s; background: var(--body-bg); }
        .form-group input:focus { border-color: var(--accent); background: #fff; }
        .form-group input::placeholder { color: var(--text-secondary); }
        .form-divider { font-size: 0.75rem; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.07em; border-top: 1.5px solid var(--border-color); padding-top: 0.8rem; margin-bottom: 0.8rem; }
        .form-divider span { color: #aaa; font-weight: 400; text-transform: none; letter-spacing: 0; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 1.2rem; }
        .btn-cancel { padding: 0.55rem 1.2rem; border: 1.5px solid var(--border-color); background: #fff; color: var(--text-primary); border-radius: 8px; font-size: 0.84rem; cursor: pointer; }
        .btn-cancel:hover { background: var(--accent-light); }
        .btn-save { padding: 0.55rem 1.4rem; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: #fff; border: none; border-radius: 8px; font-size: 0.84rem; font-weight: 600; cursor: pointer; }
        .btn-save:hover { opacity: 0.9; }
        </style>

        <script>
        function openProfileModal()  { const m = document.getElementById('profileModal'); m.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
        function closeProfileModal() { const m = document.getElementById('profileModal'); m.style.display = 'none'; document.body.style.overflow = ''; }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeProfileModal(); });

        function confirmLogout() {
            Swal.fire({
                title: 'Logging out?',
                text: 'Are you sure you want to log out?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-right-from-bracket"></i> Yes, logout',
                cancelButtonText: '<i class="fa-solid fa-xmark]]\\"></i> Cancel',
                confirmButtonColor: '#be185d',
                cancelButtonColor: '#6b3055',
                reverseButtons: true,
                customClass: {
                    popup:         'swal-logout-popup',
                    title:         'swal-logout-title',
                    confirmButton: 'swal-logout-confirm',
                    cancelButton:  'swal-logout-cancel',
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../logout.php';
                }
            });
        }
        </script>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
        .swal-logout-popup {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif !important;
            border-radius: 16px !important;
            padding: 28px !important;
        }
        .swal-logout-title {
            font-size: 18px !important;
            font-weight: 800 !important;
            color: #2d0a1f !important;
        }
        .swal2-html-container {
            font-size: 13.5px !important;
            color: #9e6080 !important;
        }
        .swal-logout-confirm,
        .swal-logout-cancel {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            border-radius: 10px !important;
            padding: 10px 20px !important;
        }
        </style>
        <?php
       $sidebar_html = ob_get_clean();

        $adminProfile = [];
        try {
            $db2   = new Database();
            $conn2 = $db2->connect();
            $stmt2 = $conn2->prepare("SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id = :id");
            $stmt2->bindParam(':id', $this->admin_id);
            $stmt2->execute();
            $adminProfile = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) { $adminProfile = []; }

        ob_start();
        echo $sidebar_html;
        ?>
        <div class="main" id="mainContent">
            <?php include __DIR__ . '/../header.php'; ?>
            <div class="page-content">
        <?php
        return ob_get_clean();
    }
    public function renderClose() {
        ob_start(); ?>
        </div><!-- end page-content -->
        <?php include __DIR__ . '/../footer.php'; ?>
        </div><!-- end main -->
        <?php
        return ob_get_clean();
    }
}

/**
 * ===============================================
 * API Handler - Route all requests through here
 * ===============================================
 */
class APIHandler {
    private $dashboard;
    private $admin_id;

    public function __construct($admin_id) {
        $this->admin_id = $admin_id;
        $this->dashboard = new MenuDashboardHelper($admin_id);
    }

    public function handleRequest() {
        $action = $_GET['action'] ?? $_POST['action'] ?? null;
        if (!$action) { $this->sendError('No action specified'); return; }
        switch ($action) {
            case 'update_order':   $this->handleUpdateOrder();   break;
            case 'search_menu':    $this->handleSearchMenu();    break;
            case 'add_menu':       $this->handleAddMenu();       break;
            case 'edit_menu':      $this->handleEditMenu();      break;
            case 'delete_menu':    $this->handleDeleteMenu();    break;
            case 'upload_image':   $this->handleUploadImage();   break;
            case 'check_pin':      $this->handleCheckPin();      break;
            case 'save_pin':       $this->handleSavePin();       break;
            case 'update_account': $this->handleUpdateAccount(); break;
            default:               $this->sendError('Invalid action');
        }
    }

    private function handleUpdateOrder() {
        header('Content-Type: application/json');
        $order_id = intval($_POST['order_id'] ?? 0);
        $status   = $_POST['status'] ?? '';
        $allowed  = ['Preparing', 'Served', 'Cancelled'];
        if (!$order_id || !in_array($status, $allowed)) { echo json_encode(['success' => false, 'message' => 'Invalid data.']); exit; }
        $db = new Database(); $conn = $db->connect();
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = :id AND admin_id = :admin_id");
            $stmt->bindParam(":id", $order_id); $stmt->bindParam(":admin_id", $this->admin_id); $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new Exception('Order not found.');
            if ($status === 'Cancelled') {
                $stmt = $conn->prepare("SELECT menu_item_id, quantity FROM order_items WHERE order_id = :id");
                $stmt->bindParam(":id", $order_id); $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($items as $item) {
                    $stmt = $conn->prepare("UPDATE menu_items SET stock_quantity = stock_quantity + :qty WHERE menu_item_id = :id AND admin_id = :admin_id");
                    $stmt->bindParam(":qty", $item['quantity']); $stmt->bindParam(":id", $item['menu_item_id']); $stmt->bindParam(":admin_id", $this->admin_id); $stmt->execute();
                }
                $stmt = $conn->prepare("UPDATE payments SET payment_status = 'Failed' WHERE order_id = :id");
                $stmt->bindParam(":id", $order_id); $stmt->execute();
            }
            $stmt = $conn->prepare("UPDATE orders SET order_status = :status WHERE order_id = :id AND admin_id = :admin_id");
            $stmt->bindParam(":status", $status); $stmt->bindParam(":id", $order_id); $stmt->bindParam(":admin_id", $this->admin_id); $stmt->execute();
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) { $conn->rollBack(); echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit;
    }

    private function handleSearchMenu() {
        header('Content-Type: application/json');
        $dashboard = new MenuDashboardHelper($this->admin_id);
        $menuHandler = $dashboard->getMenuItemHandler();
        $items    = $menuHandler->searchAndFilter($_GET['search'] ?? '', $_GET['category'] ?? '', $_GET['status'] ?? '');
        $allItems = $menuHandler->getAll();
        echo json_encode(['success' => true, 'items' => $items, 'has_any' => !empty($allItems)]);
        exit;
    }

    private function handleAddMenu() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->sendError('Invalid request method'); return; }
        $this->dashboard->getMenuItemHandler()->create(
            $_POST['item_name'] ?? '', $_POST['description'] ?? '', $_POST['price'] ?? 0,
            $_POST['stock_quantity'] ?? 0, $_POST['category'] ?? '',
            isset($_POST['is_available']) ? 1 : 0,
            !empty($_POST['image_url']) ? trim($_POST['image_url']) : null
        );
        header("Location: ../menu_list.php"); exit;
    }

    private function handleEditMenu() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            header('Content-Type: application/json');
            $item = $this->dashboard->getMenuItemHandler()->getById($_GET['id']);
            echo json_encode($item ? ['success' => true, 'item' => $item] : ['success' => false, 'message' => 'Item not found or access denied.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->dashboard->getMenuItemHandler()->update(
                $_POST['menu_item_id'] ?? null, $_POST['item_name'] ?? '', $_POST['description'] ?? '',
                $_POST['price'] ?? 0, $_POST['stock_quantity'] ?? 0, $_POST['category'] ?? '',
                isset($_POST['is_available']) ? 1 : 0,
                !empty($_POST['image_url']) ? trim($_POST['image_url']) : null
            );
            header("Location: ../menu_list.php"); exit;
        }
    }

    private function handleDeleteMenu() {
        $id = $_GET['id'] ?? null;
        if ($id) $this->dashboard->getMenuItemHandler()->delete($id);
        header("Location: ../menu_list.php"); exit;
    }

    private function handleUploadImage() {
        header('Content-Type: application/json');
        if (!isset($_FILES['image'])) { echo json_encode(['success' => false, 'message' => 'No file uploaded.']); exit; }
        echo json_encode($this->dashboard->getImageUploader()->upload($_FILES['image']));
        exit;
    }

    private function handleCheckPin() {
        header('Content-Type: application/json');
        $pin_manager = $this->dashboard->getPINManager();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') { echo json_encode(['has_pin' => $pin_manager->hasPIN()]); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { echo json_encode(['success' => $pin_manager->verifyPIN(trim($_POST['pin'] ?? ''))]); exit; }
    }

    private function handleSavePin() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit; }
        echo json_encode($this->dashboard->getPINManager()->savePIN(trim($_POST['pin'] ?? '')));
        exit;
    }

    private function handleUpdateAccount() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit; }
        $username      = trim($_POST['username'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $fullname      = trim($_POST['fullname'] ?? '');
        $fastfood_name = trim($_POST['fastfood_name'] ?? '');
        $new_password  = trim($_POST['new_password'] ?? '');
        $confirm_pass  = trim($_POST['confirm_password'] ?? '');
        if (!$username || !$email || !$fullname || !$fastfood_name) { echo json_encode(['success' => false, 'message' => 'Please complete all account fields.']); exit; }
        if ($new_password !== '' && $new_password !== $confirm_pass) { echo json_encode(['success' => false, 'message' => 'Passwords do not match.']); exit; }
        $db = new Database(); $conn = $db->connect();
        $check = $conn->prepare('SELECT admin_id FROM admins WHERE (username = :username OR email = :email) AND admin_id != :id');
        $check->bindParam(':username', $username); $check->bindParam(':email', $email); $check->bindParam(':id', $this->admin_id); $check->execute();
        if ($check->fetch()) { echo json_encode(['success' => false, 'message' => 'Username or email already in use.']); exit; }
        $updateFields = 'username = :username, email = :email, fullname = :fullname, fastfood_name = :fastfood_name';
        if ($new_password !== '') { $hashed = password_hash($new_password, PASSWORD_BCRYPT); $updateFields .= ', password = :password'; }
        $stmt = $conn->prepare("UPDATE admins SET {$updateFields} WHERE admin_id = :id");
        $stmt->bindParam(':username', $username); $stmt->bindParam(':email', $email);
        $stmt->bindParam(':fullname', $fullname); $stmt->bindParam(':fastfood_name', $fastfood_name);
        $stmt->bindParam(':id', $this->admin_id);
        if ($new_password !== '') $stmt->bindParam(':password', $hashed);
        if ($stmt->execute()) {
            $_SESSION['username'] = $username; $_SESSION['fastfood_name'] = $fastfood_name;
            echo json_encode(['success' => true, 'message' => 'Account updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unable to update account.']);
        }
        exit;
    }

    private function sendError($message) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

/**
 * Execute API if accessed directly
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_GET['action']) || (isset($_POST['action']) && strpos($_POST['action'], 'menu') === 0)) {
    if (!isset($_SESSION['admin_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $handler = new APIHandler($_SESSION['admin_id']);
    $handler->handleRequest();
}