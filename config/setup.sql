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
drop table admins, admin_sessions;
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
select * from admins;