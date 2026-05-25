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

   // ── Required fields ──────────────────────────────────────────────
    if (empty(trim($fullname)))
        return "Full name is required.";

    if (empty(trim($username)))
        return "Username is required.";

    if (empty(trim($email)))
        return "Email address is required.";

    if (empty(trim($fastfood_name)))
        return "Business name is required.";

    if (empty(trim($address)))
        return "Business address is required.";

    if (empty(trim($city)))
        return "City / Municipality is required.";

    if (empty(trim($province)))
        return "Province is required.";

   if (empty(trim($password)))
        return "Password is required.";

    if (empty(trim($business_type)))
        return "Please select a business type.";

    // ── Normalize casing ─────────────────────────────────────────────
    $fullname = ucwords(strtolower(trim($fullname)));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return "Please enter a valid email address.";

    if (strlen($username) < 4 || strlen($username) > 20)
        return "Username must be between 4 and 20 characters.";

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username))
        return "Username may only contain letters, numbers, and underscores.";

    if (strlen($password) < 8)
        return "Password must be at least 8 characters.";

    if ($phone_number && !preg_match('/^09\d{9}$/', $phone_number))
        return "Contact number must start with 09 and be 11 digits (e.g. 09XXXXXXXXX).";

    if ($zip_code && !preg_match('/^\d{4}$/', $zip_code))
        return "ZIP code must be exactly 4 digits.";

    if ($tin_number && !preg_match('/^\d{3}-\d{3}-\d{3}(-\d{1,5})?$/', $tin_number))
        return "TIN must follow the format 000-000-000 or 000-000-000-00000.";

    // ── Uniqueness checks ────────────────────────────────────────────
    if ($this->usernameExists($username))
        return "Username is already taken. Please choose another.";

    if ($this->emailExists($email))
        return "An account with this email already exists.";

    if ($phone_number && $this->fieldExists('phone_number', $phone_number))
        return "This contact number is already registered to another account.";

    if ($this->fieldExists('fastfood_name', $fastfood_name))
        return "A business with this name is already registered.";

    if ($tin_number && $this->fieldExists('tin_number', $tin_number))
        return "This BIR TIN is already registered. If this is a branch, please contact support.";

    if ($dti_sec_number && $this->fieldExists('dti_sec_number', $dti_sec_number))
        return "This DTI/SEC Registration Number is already in use.";

    if ($business_permit && $this->fieldExists('business_permit', $business_permit))
        return "This Business Permit number is already registered.";

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

    private function fieldExists($field, $value) {
        $allowed = ['phone_number', 'fastfood_name', 'tin_number', 'dti_sec_number', 'business_permit'];
        if (!in_array($field, $allowed)) return false;

        $sql  = "SELECT admin_id FROM {$this->table} WHERE {$field} = :value";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":value", $value);
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