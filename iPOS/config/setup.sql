create database ipos_db;
use ipos_db;
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    fastfood_name VARCHAR(100) DEFAULT NULL,
    role ENUM('superadmin','owner') DEFAULT 'owner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

CREATE TABLE admin_sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    admin_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT 1,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id)
    ON DELETE CASCADE
);

INSERT INTO admins (username, email, password, fullname, fastfood_name, role) VALUES 
('superadmin','ipossuperadmin@gmail.com', '$2y$12$s1jhaekLFC/MqgQf6l6LvO1jLqIyS5mlWZpLxGyejtlFTtWV2HzuS','System Administrator',
NULL,'superadmin');

ALTER TABLE admins ADD max_devices INT DEFAULT 1;
ALTER TABLE admins ADD dashboard_pin VARCHAR(255) NULL;
select * from admins;

/* ================================================================
   ADDITIONAL TABLES FOR ADMINDASHBOARD.PHP
   Add these after your existing admins + admin_sessions tables
   ================================================================ */

/* menu_items — belongs to an admin (owner) */
CREATE TABLE IF NOT EXISTS menu_items (
    menu_item_id    INT AUTO_INCREMENT PRIMARY KEY,
    admin_id        INT NOT NULL,
    item_name       VARCHAR(120) NOT NULL,
    description     TEXT NULL,
    price           DECIMAL(10,2) NOT NULL,
    stock_quantity  INT NOT NULL DEFAULT 0,
    category        VARCHAR(60) NOT NULL DEFAULT 'Uncategorized',
    is_available    TINYINT(1) NOT NULL DEFAULT 1,
    image_url       VARCHAR(500) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
);

/* customers — walk-in, no login needed */
CREATE TABLE IF NOT EXISTS customers (
    customer_id     INT AUTO_INCREMENT PRIMARY KEY,
    order_number    VARCHAR(20) NULL,
    name            VARCHAR(100) NULL,
    table_number    VARCHAR(20) NULL,
    session_start   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end     TIMESTAMP NULL
);

/* orders — placed by a customer, belongs to an admin's store */
CREATE TABLE IF NOT EXISTS orders (
    order_id        INT AUTO_INCREMENT PRIMARY KEY,
    admin_id        INT NOT NULL,
    customer_id     INT NOT NULL,
    order_status    ENUM('Queued','Preparing','Served','Cancelled') NOT NULL DEFAULT 'Queued',
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    queue_number    INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id)    REFERENCES admins(admin_id)       ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

/* order_items — snapshot of items when order was placed */
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id   INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL,
    menu_item_id    INT NULL,
    item_name       VARCHAR(120) NOT NULL,
    price           DECIMAL(10,2) NOT NULL,
    quantity        INT NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)     REFERENCES orders(order_id)          ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(menu_item_id)  ON DELETE SET NULL
);

/* carts — temporary before checkout */
CREATE TABLE IF NOT EXISTS carts (
    cart_id         INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    menu_item_id    INT NOT NULL,
    quantity        INT NOT NULL DEFAULT 1,
    subtotal        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    date_added      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id)  REFERENCES customers(customer_id)    ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(menu_item_id)  ON DELETE CASCADE
);

/* payments — one per order */
CREATE TABLE IF NOT EXISTS payments (
    payment_id      INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL UNIQUE,
    payment_method  ENUM('Cash','GCash','Credit Card') NOT NULL DEFAULT 'Cash',
    amount_paid     DECIMAL(10,2) NOT NULL,
    change_given    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    receipt_number  VARCHAR(30) NOT NULL UNIQUE,
    payment_status  ENUM('Completed','Pending','Failed') NOT NULL DEFAULT 'Pending',
    payment_date    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

