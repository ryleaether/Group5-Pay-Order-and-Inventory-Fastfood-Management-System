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

    /**
     * Get all menu items for the admin
     */
    public function getAll() {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE admin_id = :id ORDER BY created_at DESC");
        $stmt->bindParam(":id", $this->admin_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single menu item by ID
     */
    public function getById($menu_item_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE menu_item_id = :id AND admin_id = :admin_id");
        $stmt->bindParam(":id", $menu_item_id);
        $stmt->bindParam(":admin_id", $this->admin_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new menu item
     */
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

    /**
     * Update an existing menu item
     */
    public function update($menu_item_id, $item_name, $description, $price, $stock_quantity, $category, $is_available, $image_url = null) {
        $is_available = $is_available ? 1 : 0;

        // If no new image provided, keep the existing one
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

    /**
     * Delete a menu item
     */
    public function delete($menu_item_id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE menu_item_id = :id AND admin_id = :admin_id");
        $stmt->bindParam(":id", $menu_item_id);
        $stmt->bindParam(":admin_id", $this->admin_id);
        return $stmt->execute();
    }

    /**
     * Search and filter menu items
     */
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

    /**
     * Get unique categories for filter dropdown
     */
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
    private $max_file_size = 2 * 1024 * 1024; // 2MB
    private $upload_dir;
    private $admin_id;

    public function __construct($admin_id) {
        $this->admin_id = $admin_id;
        $this->upload_dir = __DIR__ . "/../uploads/";
    }

    /**
     * Validate uploaded file
     */
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

    /**
     * Upload an image file
     */
    public function upload($file) {
        $validation = $this->validateFile($file);
        if (!$validation['success']) {
            return $validation;
        }

        // Create upload directory if it doesn't exist
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }

        $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename  = 'item_' . $this->admin_id . '_' . time() . '.' . $ext;
        $filepath  = $this->upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success'  => true,
                'filename' => $filename,
                'url'      => '../uploads/' . $filename
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to save file.'];
        }
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

    /**
     * Check if admin has a PIN set
     */
    public function hasPIN() {
        $stmt = $this->conn->prepare("SELECT dashboard_pin FROM {$this->table} WHERE admin_id = :id");
        $stmt->bindParam(":id", $this->admin_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['dashboard_pin']);
    }

    /**
     * Verify PIN
     */
    public function verifyPIN($entered_pin) {
        if (strlen($entered_pin) !== 4 || !ctype_digit($entered_pin)) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT dashboard_pin FROM {$this->table} WHERE admin_id = :id");
        $stmt->bindParam(":id", $this->admin_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($entered_pin, $row['dashboard_pin'])) {
            return true;
        }

        return false;
    }

    /**
     * Save a new PIN
     */
    public function savePIN($pin) {
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            return ['success' => false, 'message' => 'PIN must be exactly 4 digits.'];
        }

        $hashed = password_hash($pin, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare("UPDATE {$this->table} SET dashboard_pin = :pin WHERE admin_id = :id");
        $stmt->bindParam(":pin", $hashed);
        $stmt->bindParam(":id",  $this->admin_id);

        if ($stmt->execute()) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Failed to save PIN.'];
        }
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

    /**
     * Get MenuItem handler
     */
    public function getMenuItemHandler() {
        return $this->menu_item;
    }

    /**
     * Get ImageUploader handler
     */
    public function getImageUploader() {
        return $this->image_uploader;
    }

    /**
     * Get PINManager handler
     */
    public function getPINManager() {
        return $this->pin_manager;
    }

    /**
     * Handle menu item creation
     */
    public function createMenuItem($post_data, $file_data = null) {
        $image_url = null;

        if ($file_data) {
            $upload_result = $this->image_uploader->upload($file_data);
            if ($upload_result['success']) {
                $image_url = $upload_result['url'];
            }
        }

        $is_available = isset($post_data['is_available']) ? 1 : 0;

        return $this->menu_item->create(
            $post_data['item_name'] ?? '',
            $post_data['description'] ?? '',
            $post_data['price'] ?? 0,
            $post_data['stock_quantity'] ?? 0,
            $post_data['category'] ?? '',
            $is_available,
            $image_url
        );
    }

    /**
     * Handle menu item update
     */
    public function updateMenuItem($post_data, $file_data = null) {
        $menu_item_id = $post_data['menu_item_id'] ?? null;
        $image_url = null;

        if ($file_data) {
            $upload_result = $this->image_uploader->upload($file_data);
            if ($upload_result['success']) {
                $image_url = $upload_result['url'];
            }
        } elseif (!empty($post_data['image_url'])) {
            $image_url = $post_data['image_url'];
        }

        $is_available = isset($post_data['is_available']) ? 1 : 0;

        return $this->menu_item->update(
            $menu_item_id,
            $post_data['item_name'] ?? '',
            $post_data['description'] ?? '',
            $post_data['price'] ?? 0,
            $post_data['stock_quantity'] ?? 0,
            $post_data['category'] ?? '',
            $is_available,
            $image_url
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

    public function __construct($admin_id, $fastfood_name = '') {
        $this->admin_id = $admin_id;
        $this->fastfood_name = $fastfood_name;
    }

    /**
     * Render the sidebar HTML
     * @param string $active_page The active page: 'dashboard' or 'menu'
     */
    public function render($active_page = 'dashboard') {
        $menu_active = $active_page === 'menu' ? 'active' : '';
        $dashboard_active = $active_page === 'dashboard' ? 'active' : '';
        
        ob_start();
        ?>
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo">
                <h2>iPOS</h2>
                <p><?= htmlspecialchars($this->fastfood_name ?? '') ?></p>
            </div>
            <ul>
                <li class="<?= $dashboard_active ?>">
                    <a href="admindashboard.php" style="text-decoration:none; color:inherit;">
                        📊 Dashboard
                    </a>
                </li>
                <li class="<?= $menu_active ?>">
                    <a href="menu_list.php" style="text-decoration:none; color:inherit;">
                        🍔 Menu List
                    </a>
                </li>
                <li>
                    <a href="#" onclick="openAccountModal(); return false;" data-action="open-account" style="text-decoration:none; color:inherit; display:block; width:100%;">
                        👤 Account
                    </a>
                </li>
                <li class="<?= $active_page === 'history' ? 'active' : '' ?>">
                    <a href="order_history.php" style="text-decoration:none; color:inherit; display:block; width:100%;">
                        🧾 Orders History
                    </a>
                </li>
                <li class="<?= $active_page === 'queue' ? 'active' : '' ?>">
                    <a href="order_queue.php" style="text-decoration:none; color:inherit; display:block; width:100%;">
                        ⏳ Order Queue
                    </a>
                </li>
                <li>
                    <a href="#" onclick="openPinModal(); return false;" data-action="open-pin" style="text-decoration:none; color:inherit; display:block; width:100%;">
                        🔄 Switch to User Dashboard
                    </a>
                </li>
            </ul>
            <a class="logout" href="../logout.php">Logout</a>
        </div>
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

    /**
     * Route and handle API requests
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? $_POST['action'] ?? null;

        if (!$action) {
            $this->sendError('No action specified');
            return;
        }

        switch ($action) {
            case 'update_order':
                $this->handleUpdateOrder();
                break;

            // Menu Item Operations
            case 'add_menu':
                $this->handleAddMenu();
                break;
            case 'edit_menu':
                $this->handleEditMenu();
                break;
            case 'delete_menu':
                $this->handleDeleteMenu();
                break;

            // Image Upload
            case 'upload_image':
                $this->handleUploadImage();
                break;

            // PIN Operations
            case 'check_pin':
                $this->handleCheckPin();
                break;
            case 'save_pin':
                $this->handleSavePin();
                break;
            case 'update_account':
                $this->handleUpdateAccount();
                break;

            default:
                $this->sendError('Invalid action');
        }
    }

    /**
     * Handle updating order status (serve / cancel)
     */
    private function handleUpdateOrder() {
        header('Content-Type: application/json');

        $order_id = intval($_POST['order_id'] ?? 0);
        $status   = $_POST['status'] ?? '';

        $allowed = ['Preparing', 'Served', 'Cancelled'];
        if (!$order_id || !in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
            exit;
        }

        $db   = new Database();
        $conn = $db->connect();

        try {
            $conn->beginTransaction();

            /* Verify order belongs to this admin */
            $stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = :id AND admin_id = :admin_id");
            $stmt->bindParam(":id",       $order_id);
            $stmt->bindParam(":admin_id", $this->admin_id);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) throw new Exception('Order not found.');

            /* If cancelling, restore stock */
            if ($status === 'Cancelled') {
                $stmt = $conn->prepare("SELECT menu_item_id, quantity FROM order_items WHERE order_id = :id");
                $stmt->bindParam(":id", $order_id);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($items as $item) {
                    $stmt = $conn->prepare("
                        UPDATE menu_items SET stock_quantity = stock_quantity + :qty
                        WHERE menu_item_id = :id AND admin_id = :admin_id
                    ");
                    $stmt->bindParam(":qty",      $item['quantity']);
                    $stmt->bindParam(":id",       $item['menu_item_id']);
                    $stmt->bindParam(":admin_id", $this->admin_id);
                    $stmt->execute();
                }

                /* Update payment status */
                $stmt = $conn->prepare("UPDATE payments SET payment_status = 'Failed' WHERE order_id = :id");
                $stmt->bindParam(":id", $order_id);
                $stmt->execute();
            }

            /* Update order status */
            $stmt = $conn->prepare("UPDATE orders SET order_status = :status WHERE order_id = :id AND admin_id = :admin_id");
            $stmt->bindParam(":status",   $status);
            $stmt->bindParam(":id",       $order_id);
            $stmt->bindParam(":admin_id", $this->admin_id);
            $stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Handle adding a menu item
     */
    private function handleAddMenu() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Invalid request method');
            return;
        }

        $menu_handler = $this->dashboard->getMenuItemHandler();
        $menu_handler->create(
            $_POST['item_name'] ?? '',
            $_POST['description'] ?? '',
            $_POST['price'] ?? 0,
            $_POST['stock_quantity'] ?? 0,
            $_POST['category'] ?? '',
            isset($_POST['is_available']) ? 1 : 0,
            !empty($_POST['image_url']) ? trim($_POST['image_url']) : null
        );

        header("Location: ../menu_list.php");
        exit;
    }

    /**
     * Handle editing a menu item
     */
    private function handleEditMenu() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            header('Content-Type: application/json');
            $menu_handler = $this->dashboard->getMenuItemHandler();
            $item = $menu_handler->getById($_GET['id']);

            if ($item) {
                echo json_encode(['success' => true, 'item' => $item]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Item not found or access denied.']);
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $menu_handler = $this->dashboard->getMenuItemHandler();
            $id = $_POST['menu_item_id'] ?? null;
            $image_url = !empty($_POST['image_url']) ? trim($_POST['image_url']) : null;

            $menu_handler->update(
                $id,
                $_POST['item_name'] ?? '',
                $_POST['description'] ?? '',
                $_POST['price'] ?? 0,
                $_POST['stock_quantity'] ?? 0,
                $_POST['category'] ?? '',
                isset($_POST['is_available']) ? 1 : 0,
                $image_url
            );

            header("Location: ../menu_list.php");
            exit;
        }
    }

    /**
     * Handle deleting a menu item
     */
    private function handleDeleteMenu() {
        $id = $_GET['id'] ?? null;

        if ($id) {
            $menu_handler = $this->dashboard->getMenuItemHandler();
            $menu_handler->delete($id);
        }

       header("Location: ../menu_list.php");
        exit;
    }

    /**
     * Handle image upload
     */
    private function handleUploadImage() {
        header('Content-Type: application/json');

        if (!isset($_FILES['image'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
            exit;
        }

        $uploader = $this->dashboard->getImageUploader();
        $result = $uploader->upload($_FILES['image']);
        echo json_encode($result);
        exit;
    }

    /**
     * Handle PIN check
     */
    private function handleCheckPin() {
        header('Content-Type: application/json');

        $pin_manager = $this->dashboard->getPINManager();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode(['has_pin' => $pin_manager->hasPIN()]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $entered = trim($_POST['pin'] ?? '');
            $is_valid = $pin_manager->verifyPIN($entered);
            echo json_encode(['success' => $is_valid]);
            exit;
        }
    }

    /**
     * Handle PIN saving
     */
    private function handleSavePin() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }

        $pin = trim($_POST['pin'] ?? '');
        $pin_manager = $this->dashboard->getPINManager();
        $result = $pin_manager->savePIN($pin);
        echo json_encode($result);
        exit;
    }

    /**
     * Handle account update
     */
    private function handleUpdateAccount() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }

        $username      = trim($_POST['username'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $fullname      = trim($_POST['fullname'] ?? '');
        $fastfood_name = trim($_POST['fastfood_name'] ?? '');
        $new_password  = trim($_POST['new_password'] ?? '');
        $confirm_pass  = trim($_POST['confirm_password'] ?? '');

        if ($username === '' || $email === '' || $fullname === '' || $fastfood_name === '') {
            echo json_encode(['success' => false, 'message' => 'Please complete all account fields.']);
            exit;
        }

        if ($new_password !== '' && $new_password !== $confirm_pass) {
            echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
            exit;
        }

        $db = new Database();
        $conn = $db->connect();

        $check = $conn->prepare(
            'SELECT admin_id FROM admins WHERE (username = :username OR email = :email) AND admin_id != :id'
        );
        $check->bindParam(':username', $username);
        $check->bindParam(':email', $email);
        $check->bindParam(':id', $this->admin_id);
        $check->execute();

        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username or email is already in use by another account.']);
            exit;
        }

        $updateFields = 'username = :username, email = :email, fullname = :fullname, fastfood_name = :fastfood_name';
        if ($new_password !== '') {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $updateFields .= ', password = :password';
        }

        $sql = "UPDATE admins SET {$updateFields} WHERE admin_id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':fullname', $fullname);
        $stmt->bindParam(':fastfood_name', $fastfood_name);
        $stmt->bindParam(':id', $this->admin_id);
        if ($new_password !== '') {
            $stmt->bindParam(':password', $hashed);
        }

        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            $_SESSION['fastfood_name'] = $fastfood_name;
            echo json_encode(['success' => true, 'message' => 'Account details updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unable to update account. Please try again.']);
        }
        exit;
    }

    /**
     * Send JSON error response
     */
    private function sendError($message) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

/**
 * ============================================
 * Execute API if accessed directly
 * ============================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if this file is being accessed as an API endpoint
if (isset($_GET['action']) || (isset($_POST['action']) && strpos($_POST['action'], 'menu') === 0)) {
    if (!isset($_SESSION['admin_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $handler = new APIHandler($_SESSION['admin_id']);
    $handler->handleRequest();
}
