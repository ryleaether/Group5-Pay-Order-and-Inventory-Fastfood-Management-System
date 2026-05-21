<?php

class Database {
    private $host     = "localhost";
    private $port     = "3308";
    private $dbname   = "ipos_db";
    private $username = "root";
    private $password = "root";   // ← set your MySQL root password here if needed

    public $conn;

    public function connect() {
        try {
            // Step 1: Connect WITHOUT selecting a database
            $pdo = new PDO(
                "mysql:host={$this->host};port={$this->port};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Step 2: Create the database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$this->dbname}`");

            $this->conn = $pdo;

            // Step 3: Auto-setup tables and superadmin if this is a fresh database
            $this->autoSetup();

        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }

        return $this->conn;
    }

    private function autoSetup() {
        // Check if admins table exists — if not, this is a fresh DB
        $res = $this->conn->query("SHOW TABLES LIKE 'admins'");
        if ($res->rowCount() > 0) {
            // Tables already exist — nothing to do
            return;
        }

        // ── Create all core tables ────────────────────────────────────────
        $this->conn->exec("SET FOREIGN_KEY_CHECKS=0");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS admins (
            admin_id       INT AUTO_INCREMENT PRIMARY KEY,
            username       VARCHAR(50) UNIQUE NOT NULL,
            email          VARCHAR(100) UNIQUE NOT NULL,
            password       VARCHAR(255) NOT NULL,
            fullname       VARCHAR(100) NOT NULL,
            fastfood_name  VARCHAR(100) DEFAULT NULL,
            role           ENUM('superadmin','owner') DEFAULT 'owner',
            max_devices    INT DEFAULT 1,
            dashboard_pin  VARCHAR(255) NULL,
            theme_data     TEXT NULL,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login     TIMESTAMP NULL
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS admin_sessions (
            session_id  VARCHAR(255) PRIMARY KEY,
            admin_id    INT NOT NULL,
            login_time  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            logout_time TIMESTAMP NULL,
            is_active   BOOLEAN DEFAULT 1,
            FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS menu_items (
            menu_item_id   INT AUTO_INCREMENT PRIMARY KEY,
            admin_id       INT NOT NULL,
            item_name      VARCHAR(120) NOT NULL,
            description    TEXT NULL,
            price          DECIMAL(10,2) NOT NULL,
            stock_quantity INT NOT NULL DEFAULT 0,
            category       VARCHAR(60) NOT NULL DEFAULT 'Uncategorized',
            is_available   TINYINT(1) NOT NULL DEFAULT 1,
            image_url      VARCHAR(500) NULL,
            deleted_at     TIMESTAMP NULL DEFAULT NULL,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS customers (
            customer_id  INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(20) NULL,
            name         VARCHAR(100) NULL,
            table_number VARCHAR(20) NULL,
            session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            session_end  TIMESTAMP NULL
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS orders (
            order_id     INT AUTO_INCREMENT PRIMARY KEY,
            admin_id     INT NOT NULL,
            customer_id  INT NOT NULL,
            order_status ENUM('Queued','Preparing','Served','Completed','Cancelled') NOT NULL DEFAULT 'Queued',
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            queue_number INT NOT NULL DEFAULT 0,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id)    REFERENCES admins(admin_id)       ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS order_items (
            order_item_id INT AUTO_INCREMENT PRIMARY KEY,
            order_id      INT NOT NULL,
            menu_item_id  INT NULL,
            item_name     VARCHAR(120) NOT NULL,
            price         DECIMAL(10,2) NOT NULL,
            quantity      INT NOT NULL,
            subtotal      DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id)     REFERENCES orders(order_id)         ON DELETE CASCADE,
            FOREIGN KEY (menu_item_id) REFERENCES menu_items(menu_item_id) ON DELETE SET NULL
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS carts (
            cart_id      INT AUTO_INCREMENT PRIMARY KEY,
            customer_id  INT NOT NULL,
            menu_item_id INT NOT NULL,
            quantity     INT NOT NULL DEFAULT 1,
            subtotal     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            date_added   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id)  REFERENCES customers(customer_id)   ON DELETE CASCADE,
            FOREIGN KEY (menu_item_id) REFERENCES menu_items(menu_item_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS payments (
            payment_id     INT AUTO_INCREMENT PRIMARY KEY,
            order_id       INT NOT NULL UNIQUE,
            payment_method ENUM('Cash','GCash','Credit Card') NOT NULL DEFAULT 'Cash',
            amount_paid    DECIMAL(10,2) NOT NULL,
            change_given   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            receipt_number VARCHAR(30) NOT NULL UNIQUE,
            payment_status ENUM('Completed','Pending','Failed') NOT NULL DEFAULT 'Pending',
            payment_date   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS staffs (
            staff_id         INT AUTO_INCREMENT PRIMARY KEY,
            admin_id         INT NOT NULL,
            fullname         VARCHAR(100) NOT NULL,
            role             ENUM('Cashier','Kitchen') NOT NULL DEFAULT 'Cashier',
            pin              VARCHAR(255) NULL,
            status           ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            shift_start      TIME NULL,
            shift_end        TIME NULL,
            login_fail_count INT NOT NULL DEFAULT 0,
            last_fail_at     TIMESTAMP NULL,
            last_login_at    TIMESTAMP NULL,
            is_online        TINYINT(1) NOT NULL DEFAULT 0,
            created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS staff_sessions (
            session_id       INT AUTO_INCREMENT PRIMARY KEY,
            staff_id         INT NOT NULL,
            admin_id         INT NOT NULL,
            login_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            logout_at        TIMESTAMP NULL,
            duration_minutes INT NULL,
            FOREIGN KEY (staff_id) REFERENCES staffs(staff_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS audit_log (
                    log_id       INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id     INT DEFAULT NULL,
                    actor_name   VARCHAR(100) NOT NULL DEFAULT '',
                    action       VARCHAR(80) NOT NULL,
                    target_type  VARCHAR(50) DEFAULT NULL,
                    target_id    INT DEFAULT NULL,
                    target_label VARCHAR(200) DEFAULT NULL,
                    detail       TEXT,
                    ip_address   VARCHAR(45) DEFAULT NULL,
                    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL
                )");

                /* =========================
                SYSTEM SETTINGS
                ========================= */
                $this->conn->exec("CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key   VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT NULL,
                    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    updated_by    INT NULL,
                    FOREIGN KEY (updated_by) REFERENCES admins(admin_id) ON DELETE SET NULL
                )");

                $this->conn->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
                    ('announcement_enabled', '0'),
                    ('announcement_message', ''),
                    ('announcement_type', 'info'),
                    ('maintenance_enabled', '0'),
                    ('maintenance_message', 'We are currently performing scheduled maintenance. We will be back shortly. Thank you for your patience.'),
                    ('maintenance_end_time', NULL)
                ");

                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");

        // ── Seed superadmin ───────────────────────────────────────────────
        // Password: superadmin123 (bcrypt hashed)
        $this->conn->exec("INSERT INTO admins (username, email, password, fullname, role) VALUES (
            'superadmin',
            'ipossuperadmin@gmail.com',
            '\$2y\$12\$s1jhaekLFC/MqgQf6l6LvO1jLqIyS5mlWZpLxGyejtlFTtWV2HzuS',
            'System Administrator',
            'superadmin'
        )");
    }
}

/**<?php

class Database {
    private $host     = "localhost";
    private $dbname   = "ipos_db";
    private $username = "root";
    private $password = "root";   // ← set your MySQL root password here if needed

    public $conn;

    public function connect() {
        try {
            // Step 1: Connect WITHOUT selecting a database
            $pdo = new PDO(
                "mysql:host={$this->host};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Step 2: Create the database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$this->dbname}`");

            $this->conn = $pdo;

            // Step 3: Auto-setup tables and superadmin if this is a fresh database
            $this->autoSetup();

        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }

        return $this->conn;
    }

    private function autoSetup() {
        // Check if admins table exists — if not, this is a fresh DB
        $res = $this->conn->query("SHOW TABLES LIKE 'admins'");
        if ($res->rowCount() > 0) {
            // Tables already exist — nothing to do
            return;
        }

        // ── Create all core tables ────────────────────────────────────────
        $this->conn->exec("SET FOREIGN_KEY_CHECKS=0");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS admins (
            admin_id       INT AUTO_INCREMENT PRIMARY KEY,
            username       VARCHAR(50) UNIQUE NOT NULL,
            email          VARCHAR(100) UNIQUE NOT NULL,
            password       VARCHAR(255) NOT NULL,
            fullname       VARCHAR(100) NOT NULL,
            fastfood_name  VARCHAR(100) DEFAULT NULL,
            role           ENUM('superadmin','owner') DEFAULT 'owner',
            max_devices    INT DEFAULT 1,
            dashboard_pin  VARCHAR(255) NULL,
            theme_data     TEXT NULL,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login     TIMESTAMP NULL
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS admin_sessions (
            session_id  VARCHAR(255) PRIMARY KEY,
            admin_id    INT NOT NULL,
            login_time  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            logout_time TIMESTAMP NULL,
            is_active   BOOLEAN DEFAULT 1,
            FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS menu_items (
            menu_item_id   INT AUTO_INCREMENT PRIMARY KEY,
            admin_id       INT NOT NULL,
            item_name      VARCHAR(120) NOT NULL,
            description    TEXT NULL,
            price          DECIMAL(10,2) NOT NULL,
            stock_quantity INT NOT NULL DEFAULT 0,
            category       VARCHAR(60) NOT NULL DEFAULT 'Uncategorized',
            is_available   TINYINT(1) NOT NULL DEFAULT 1,
            image_url      VARCHAR(500) NULL,
            deleted_at     TIMESTAMP NULL DEFAULT NULL,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS customers (
            customer_id  INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(20) NULL,
            name         VARCHAR(100) NULL,
            table_number VARCHAR(20) NULL,
            session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            session_end  TIMESTAMP NULL
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS orders (
            order_id     INT AUTO_INCREMENT PRIMARY KEY,
            admin_id     INT NOT NULL,
            customer_id  INT NOT NULL,
            order_status ENUM('Queued','Preparing','Served','Completed','Cancelled') NOT NULL DEFAULT 'Queued',
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            queue_number INT NOT NULL DEFAULT 0,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id)    REFERENCES admins(admin_id)       ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS order_items (
            order_item_id INT AUTO_INCREMENT PRIMARY KEY,
            order_id      INT NOT NULL,
            menu_item_id  INT NULL,
            item_name     VARCHAR(120) NOT NULL,
            price         DECIMAL(10,2) NOT NULL,
            quantity      INT NOT NULL,
            subtotal      DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id)     REFERENCES orders(order_id)         ON DELETE CASCADE,
            FOREIGN KEY (menu_item_id) REFERENCES menu_items(menu_item_id) ON DELETE SET NULL
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS carts (
            cart_id      INT AUTO_INCREMENT PRIMARY KEY,
            customer_id  INT NOT NULL,
            menu_item_id INT NOT NULL,
            quantity     INT NOT NULL DEFAULT 1,
            subtotal     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            date_added   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id)  REFERENCES customers(customer_id)   ON DELETE CASCADE,
            FOREIGN KEY (menu_item_id) REFERENCES menu_items(menu_item_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS payments (
            payment_id     INT AUTO_INCREMENT PRIMARY KEY,
            order_id       INT NOT NULL UNIQUE,
            payment_method ENUM('Cash','GCash','Credit Card') NOT NULL DEFAULT 'Cash',
            amount_paid    DECIMAL(10,2) NOT NULL,
            change_given   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            receipt_number VARCHAR(30) NOT NULL UNIQUE,
            payment_status ENUM('Completed','Pending','Failed') NOT NULL DEFAULT 'Pending',
            payment_date   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS staffs (
            staff_id         INT AUTO_INCREMENT PRIMARY KEY,
            admin_id         INT NOT NULL,
            fullname         VARCHAR(100) NOT NULL,
            role             ENUM('Cashier','Kitchen') NOT NULL DEFAULT 'Cashier',
            pin              VARCHAR(255) NULL,
            status           ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            shift_start      TIME NULL,
            shift_end        TIME NULL,
            login_fail_count INT NOT NULL DEFAULT 0,
            last_fail_at     TIMESTAMP NULL,
            last_login_at    TIMESTAMP NULL,
            is_online        TINYINT(1) NOT NULL DEFAULT 0,
            created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS staff_sessions (
            session_id       INT AUTO_INCREMENT PRIMARY KEY,
            staff_id         INT NOT NULL,
            admin_id         INT NOT NULL,
            login_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            logout_at        TIMESTAMP NULL,
            duration_minutes INT NULL,
            FOREIGN KEY (staff_id) REFERENCES staffs(staff_id) ON DELETE CASCADE
        )");

        $this->conn->exec("CREATE TABLE IF NOT EXISTS audit_log (
                    log_id       INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id     INT DEFAULT NULL,
                    actor_name   VARCHAR(100) NOT NULL DEFAULT '',
                    action       VARCHAR(80) NOT NULL,
                    target_type  VARCHAR(50) DEFAULT NULL,
                    target_id    INT DEFAULT NULL,
                    target_label VARCHAR(200) DEFAULT NULL,
                    detail       TEXT,
                    ip_address   VARCHAR(45) DEFAULT NULL,
                    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL
                )");

                /* =========================
                SYSTEM SETTINGS
                ========================= */
               /*$this->conn->exec("CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key   VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT NULL,
                    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    updated_by    INT NULL,
                    FOREIGN KEY (updated_by) REFERENCES admins(admin_id) ON DELETE SET NULL
                )");

                $this->conn->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
                    ('announcement_enabled', '0'),
                    ('announcement_message', ''),
                    ('announcement_type', 'info'),
                    ('maintenance_enabled', '0'),
                    ('maintenance_message', 'We are currently performing scheduled maintenance. We will be back shortly. Thank you for your patience.'),
                    ('maintenance_end_time', NULL)
                ");

                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");

        // ── Seed superadmin ───────────────────────────────────────────────
        // Password: superadmin123 (bcrypt hashed)
        $this->conn->exec("INSERT INTO admins (username, email, password, fullname, role) VALUES (
            'superadmin',
            'ipossuperadmin@gmail.com',
            '\$2y\$12\$s1jhaekLFC/MqgQf6l6LvO1jLqIyS5mlWZpLxGyejtlFTtWV2HzuS',
            'System Administrator',
            'superadmin'
        )");
    }
} */