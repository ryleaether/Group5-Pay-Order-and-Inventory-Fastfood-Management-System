<?php
require_once __DIR__ . "/config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Validation {

    private $conn;
    private $table = "admins";

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    /* =========================
        REGISTER (WITH EMAIL READY)
    ========================= */
    public function register($username, $password, $fullname, $fastfood_name, $email) {

        if (empty($username) || empty($password) || empty($fullname) || empty($fastfood_name)) {
            return "All fields are required!";
        }

        if ($this->usernameExists($username)) {
            return "Username already exists!";
        }

        if (strlen($password) < 6) {
            return "Password must be at least 6 characters!";
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // If email exists in DB, include it automatically
        if ($email !== null) {

            $sql = "INSERT INTO {$this->table}
                    (username, password, fullname, fastfood_name, email, role)
                    VALUES (:username, :password, :fullname, :fastfood_name, :email, 'owner')";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":password", $hashed);
            $stmt->bindParam(":fullname", $fullname);
            $stmt->bindParam(":fastfood_name", $fastfood_name);
            $stmt->bindParam(":email", $email);

        } else {

            $sql = "INSERT INTO {$this->table}
                    (username, password, fullname, fastfood_name, role)
                    VALUES (:username, :password, :fullname, :fastfood_name, 'owner')";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":password", $hashed);
            $stmt->bindParam(":fullname", $fullname);
            $stmt->bindParam(":fastfood_name", $fastfood_name);
        }

        return $stmt->execute() ? true : "Registration failed!";
    }

    /* =========================
        LOGIN
    ========================= */
    public function login($username, $password) {

    if (empty($username) || empty($password)) {
        return "All fields are required!";
    }

    $sql = "SELECT * FROM {$this->table} WHERE username = :username LIMIT 1";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(":username", $username);
    $stmt->execute();

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    /* =========================
       ACCOUNT + PASSWORD CHECK
       (GENERIC MESSAGE FOR SECURITY)
    ========================= */
    if (!$admin || !password_verify($password, $admin['password'])) {
        return "Invalid username or password!";
    }

    /* =========================
       DEVICE LIMIT CHECK
    ========================= */
    // CLEAN OLD SESSIONS (PREVENT FALSE LIMIT)
    $cleanup = "DELETE FROM admin_sessions WHERE admin_id = :admin_id";
    $stmt = $this->conn->prepare($cleanup);
    $stmt->bindParam(":admin_id", $admin['admin_id']);
    $stmt->execute();

    // NOW CHECK DEVICE COUNT
    $currentDevices = $this->countDevices($admin['admin_id']);

    if ($currentDevices >= ($admin['max_devices'] ?? 1)) {
        return "Device limit reached!";
    }

    /* =========================
       START SESSION
    ========================= */
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['admin_id'] = $admin['admin_id'];
    $_SESSION['username'] = $admin['username'];
    $_SESSION['fastfood_name'] = $admin['fastfood_name'];
    $_SESSION['role'] = $admin['role'];

    if (isset($admin['email'])) {
        $_SESSION['email'] = $admin['email'];
    }

    $session_id = session_id();

    /* REMOVE OLD SESSION */
    $delete = "DELETE FROM admin_sessions WHERE session_id = :session_id";
    $stmt = $this->conn->prepare($delete);
    $stmt->bindParam(":session_id", $session_id);
    $stmt->execute();

    /* INSERT NEW SESSION */
    $sql = "INSERT INTO admin_sessions (session_id, admin_id, is_active, login_time)
            VALUES (:session_id, :admin_id, 1, NOW())";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(":session_id", $session_id);
    $stmt->bindParam(":admin_id", $admin['admin_id']);
    $stmt->execute();

    /* UPDATE LAST LOGIN */
    $update = "UPDATE {$this->table}
               SET last_login = NOW()
               WHERE admin_id = :id";

    $stmt = $this->conn->prepare($update);
    $stmt->bindParam(":id", $admin['admin_id']);
    $stmt->execute();

    return $admin;
}

    /* =========================
        HELPERS
    ========================= */

    private function usernameExists($username) {
        $sql = "SELECT admin_id FROM {$this->table} WHERE username = :username";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /* =========================
        STATS
    ========================= */

    public function countDevices($admin_id) {

        $sql = "SELECT COUNT(*) as total
                FROM admin_sessions
                WHERE admin_id = :admin_id
                AND is_active = 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":admin_id", $admin_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    }

    public function countAdmins() {
        $sql = "SELECT COUNT(*) as total FROM admins WHERE role = 'owner'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    }

    public function countActiveSessions() {

        $sql = "SELECT COUNT(*) as total
                FROM admin_sessions
                WHERE is_active = 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    }

    public function getAllOwners() {

        $sql = "SELECT admin_id, username, email, fastfood_name, last_login
                FROM admins
                WHERE role = 'owner'";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isAdminOnline($admin_id) {

        $sql = "SELECT COUNT(*) as total
                FROM admin_sessions
                WHERE admin_id = :admin_id
                AND is_active = 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":admin_id", $admin_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }

    public function deleteAdmin($admin_id) {

    // delete sessions first (important)
    $sql = "DELETE FROM admin_sessions WHERE admin_id = :id";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();

    // delete admin
    $sql = "DELETE FROM admins WHERE admin_id = :id";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(":id", $admin_id);

    return $stmt->execute();
}

public function updateMaxDevices($admin_id, $max) {
    $sql = "UPDATE admins SET max_devices = :max WHERE admin_id = :id";
    $stmt = $this->conn->prepare($sql);

    $stmt->bindParam(":max", $max);
    $stmt->bindParam(":id", $admin_id);

    return $stmt->execute();
}

}