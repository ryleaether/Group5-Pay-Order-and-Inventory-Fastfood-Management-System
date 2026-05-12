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
  public function register($username, $password, $fullname, $fastfood_name, $email,
    $phone_number = null, $business_type = null, $tin_number = null,
    $dti_sec_number = null, $business_permit = null, $address = null,
    $city = null, $province = null, $zip_code = null, $logo_url = null,
    $logo_shape = 'circle') {

    if (empty($username) || empty($password) || empty($fullname) || empty($fastfood_name)) {
        return "All fields are required!";
    }

  if ($this->usernameExists($username)) {
    return "Username already exists!";
}

if ($this->emailExists($email)) {
    return "Email address is already registered!";
}

    if (strlen($password) < 6) {
        return "Password must be at least 6 characters!";
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO {$this->table}
        (username, password, fullname, fastfood_name, email, role,
         phone_number, business_type, tin_number, dti_sec_number,
         business_permit, address, city, province, zip_code, logo_url, logo_shape)
        VALUES
        (:username, :password, :fullname, :fastfood_name, :email, 'owner',
         :phone_number, :business_type, :tin_number, :dti_sec_number,
         :business_permit, :address, :city, :province, :zip_code, :logo_url, :logo_shape)";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(":username",       $username);
    $stmt->bindParam(":password",       $hashed);
    $stmt->bindParam(":fullname",       $fullname);
    $stmt->bindParam(":fastfood_name",  $fastfood_name);
    $stmt->bindParam(":email",          $email);
    $stmt->bindParam(":phone_number",   $phone_number);
    $stmt->bindParam(":business_type",  $business_type);
    $stmt->bindParam(":tin_number",     $tin_number);
    $stmt->bindParam(":dti_sec_number", $dti_sec_number);
    $stmt->bindParam(":business_permit",$business_permit);
    $stmt->bindParam(":address",        $address);
    $stmt->bindParam(":city",           $city);
    $stmt->bindParam(":province",       $province);
    $stmt->bindParam(":zip_code",       $zip_code);
    $stmt->bindParam(":logo_url",       $logo_url);
    $stmt->bindParam(":logo_shape",     $logo_shape);

    try {
    return $stmt->execute() ? true : "Registration failed!";
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        if (str_contains($e->getMessage(), 'email')) {
            return "Email address is already registered!";
        }
        if (str_contains($e->getMessage(), 'username')) {
            return "Username already exists!";
        }
        return "An account with those details already exists!";
    }
    throw $e;
}
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

   $_SESSION['admin_id']      = $admin['admin_id'];
    $_SESSION['username']      = $admin['username'];
    $_SESSION['fastfood_name'] = $admin['fastfood_name'];
    $_SESSION['role']          = $admin['role'];
    $_SESSION['logo_url']      = $admin['logo_url'] ?? null;
    $_SESSION['logo_shape']    = $admin['logo_shape'] ?? 'circle';
    $_SESSION['business_type'] = $admin['business_type'] ?? 'Fast Food Store';

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

    private function emailExists($email) {
        $sql = "SELECT admin_id FROM {$this->table} WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":email", $email);
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
                WHERE role = 'owner'
                ORDER BY admin_id DESC";

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

public function adminExists($admin_id) {
    $sql = "SELECT COUNT(*) FROM admins WHERE admin_id = :id";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(":id", $admin_id);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
}


}