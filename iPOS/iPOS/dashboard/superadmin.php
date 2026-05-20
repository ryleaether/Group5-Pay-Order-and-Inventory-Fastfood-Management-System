<?php
session_start();
require_once __DIR__ . "/../validation.php";
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit;
}

$val = new Validation();

if (!$val->adminExists($_SESSION['admin_id'])) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Account Deleted</title>
        <style>
            body { font-family: "Poppins", sans-serif; background: #F0EBF4; }
            .overlay { position: fixed; inset: 0; background: rgba(45,11,34,0.45); display: flex; align-items: center; justify-content: center; backdrop-filter: blur(6px); }
            .box { background: #fff; padding: 36px; border-radius: 16px; border-top: 4px solid #9B2C52; box-shadow: 0 20px 50px rgba(45,11,34,0.15); text-align: center; max-width: 360px; width: 90%; }
            h2 { font-size: 18px; color: #1A0A14; margin-bottom: 8px; }
            p  { font-size: 14px; color: #8C6E82; margin-bottom: 24px; }
            button { padding: 11px 28px; background: #9B2C52; color: #fff; border: none; border-radius: 9px; cursor: pointer; font-size: 14px; font-weight: 600; }
            button:hover { background: #7a1f3e; }
        </style>
    </head>
    <body>
        <div class="overlay">
            <div class="box">
                <h2>Your account has been deleted</h2>
                <p>You will be redirected to the login page.</p>
                <button onclick="window.location.href=\'../login.php\'">OK</button>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

$db   = new Database();
$conn = $db->connect();

/* ── EDIT OWNER ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_owner'])) {
    $edit_id       = (int)$_POST['edit_owner_id'];
    $edit_username = trim($_POST['edit_owner_username']);
    $edit_fullname = trim($_POST['edit_owner_fullname']);
    $edit_email    = trim($_POST['edit_owner_email']);
    $edit_fastfood = trim($_POST['edit_owner_fastfood']);
    $edit_password = trim($_POST['edit_owner_password']);
    $edit_confirm  = trim($_POST['edit_owner_confirm']);
    $errors = [];

    if (empty($edit_username)) $errors[] = "Username is required.";
    if (empty($edit_fullname)) $errors[] = "Full name is required.";
    if (empty($edit_email) || !filter_var($edit_email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (empty($edit_fastfood)) $errors[] = "Fastfood name is required.";

    // Uniqueness checks excluding self
    if (!empty($edit_username)) {
        $chk = $conn->prepare("SELECT admin_id FROM admins WHERE username = :u AND admin_id != :id");
        $chk->execute([':u' => $edit_username, ':id' => $edit_id]);
        if ($chk->rowCount() > 0) $errors[] = "Username already taken.";
    }
    if (!empty($edit_email)) {
        $chk2 = $conn->prepare("SELECT admin_id FROM admins WHERE email = :e AND admin_id != :id");
        $chk2->execute([':e' => $edit_email, ':id' => $edit_id]);
        if ($chk2->rowCount() > 0) $errors[] = "Email already in use.";
    }

    $update_pw = false;
    if (!empty($edit_password)) {
        if (strlen($edit_password) < 6)      $errors[] = "Password must be at least 6 characters.";
        elseif ($edit_password !== $edit_confirm) $errors[] = "Passwords do not match.";
        else $update_pw = true;
    }

    if (empty($errors)) {
        if ($update_pw) {
            $hashed = password_hash($edit_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET username=:u, email=:e, fullname=:f, fastfood_name=:ff, password=:p WHERE admin_id=:id");
            $stmt->execute([':u'=>$edit_username,':e'=>$edit_email,':f'=>$edit_fullname,':ff'=>$edit_fastfood,':p'=>$hashed,':id'=>$edit_id]);
        } else {
            $stmt = $conn->prepare("UPDATE admins SET username=:u, email=:e, fullname=:f, fastfood_name=:ff WHERE admin_id=:id");
            $stmt->execute([':u'=>$edit_username,':e'=>$edit_email,':f'=>$edit_fullname,':ff'=>$edit_fastfood,':id'=>$edit_id]);
        }
        $_SESSION['flash_success'] = "Owner <strong>" . htmlspecialchars($edit_username) . "</strong> updated successfully!";
        header("Location: superadmin.php?page=owners");
        exit;
    } else {
        $_SESSION['edit_error'] = implode(" ", $errors);
        $_SESSION['edit_old']   = $_POST;
        header("Location: superadmin.php?page=owners&modal=edit&id=" . $edit_id);
        exit;
    }
}


/* ── UPDATE SUPERADMIN CREDENTIALS ── */
$update_message = "";
$update_type    = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_credentials'])) {
    $new_username = trim($_POST['new_username']);
    $new_email    = trim($_POST['new_email']);
    $new_password = trim($_POST['new_password']);
    $confirm_pw   = trim($_POST['confirm_password']);
    $admin_id     = $_SESSION['admin_id'];
    $errors = [];

    if (empty($new_username)) $errors[] = "Username cannot be empty.";
    if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";

    if (!empty($new_username)) {
        $chk = $conn->prepare("SELECT admin_id FROM admins WHERE username = :u AND admin_id != :id");
        $chk->execute([':u' => $new_username, ':id' => $admin_id]);
        if ($chk->rowCount() > 0) $errors[] = "Username already taken.";
    }
    if (!empty($new_email)) {
        $chk2 = $conn->prepare("SELECT admin_id FROM admins WHERE email = :e AND admin_id != :id");
        $chk2->execute([':e' => $new_email, ':id' => $admin_id]);
        if ($chk2->rowCount() > 0) $errors[] = "Email already in use.";
    }

    $update_pw = false;
    if (!empty($new_password)) {
        if (strlen($new_password) < 6)      $errors[] = "Password must be at least 6 characters.";
        elseif ($new_password !== $confirm_pw) $errors[] = "Passwords do not match.";
        else $update_pw = true;
    }

    if (empty($errors)) {
        if ($update_pw) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET username=:u, email=:e, password=:p WHERE admin_id=:id");
            $stmt->execute([':u'=>$new_username,':e'=>$new_email,':p'=>$hashed,':id'=>$admin_id]);
        } else {
            $stmt = $conn->prepare("UPDATE admins SET username=:u, email=:e WHERE admin_id=:id");
            $stmt->execute([':u'=>$new_username,':e'=>$new_email,':id'=>$admin_id]);
        }
        $_SESSION['username'] = $new_username;
        $_SESSION['email']    = $new_email;
        $_SESSION['cred_success'] = "Credentials updated successfully!";
        header("Location: superadmin.php?page=account");
        exit;
    } else {
        $update_message = implode(" ", $errors);
        $update_type    = "error";
    }
}

// Flash messages
if (isset($_SESSION['cred_success'])) {
    $update_message = $_SESSION['cred_success'];
    $update_type    = "success";
    unset($_SESSION['cred_success']);
}

$flash_success = "";
if (isset($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Edit modal error carry-over
$edit_error = "";
$edit_old   = [];
if (isset($_SESSION['edit_error'])) {
    $edit_error = $_SESSION['edit_error'];
    $edit_old   = $_SESSION['edit_old'] ?? [];
    unset($_SESSION['edit_error'], $_SESSION['edit_old']);
}

/* ── DELETE ADMIN ── */
if (isset($_GET['delete'])) {
    $val->deleteAdmin($_GET['delete']);
    $_SESSION['flash_success'] = "Owner account deleted.";
    header("Location: superadmin.php?page=owners");
    exit;
}

/* ── UPDATE MAX DEVICES ── */
if (isset($_POST['update_devices'])) {
    $val->updateMaxDevices($_POST['admin_id'], $_POST['max_devices']);
    header("Location: superadmin.php");
    exit;
}

$total_admins         = $val->countAdmins();
$total_active_devices = $val->countActiveSessions();
$admins               = $val->getAllOwners();

// Fetch full owners list
$stmt = $conn->prepare("SELECT admin_id, username, email, fullname, fastfood_name, last_login FROM admins WHERE role='owner' ORDER BY admin_id DESC");
$stmt->execute();
$admins_full = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_admins  = count($admins_full);

$open_page = $_GET['page'] ?? 'dashboard';
$open_modal = $_GET['modal'] ?? '';
$open_edit_id = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iPOS — Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-bg:     #2D0B22;
            --sidebar-hover:  rgba(255,255,255,0.07);
            --sidebar-active: #9B2C52;
            --sidebar-text:   rgba(255,255,255,0.55);
            --sidebar-border: rgba(255,255,255,0.07);

            --bg:             #F0EBF4;
            --bg-card:        #FFFFFF;
            --bg-input:       #F8F4FA;

            --rose:           #9B2C52;
            --rose-hover:     #7A1F3E;
            --rose-muted:     #F5E6EC;
            --rose-border:    #E8C0CC;

            --text-h:         #1A0A14;
            --text-body:      #3D1A30;
            --text-muted:     #8C6E82;

            --border:         #EAE0EE;

            --green:          #1A7F4E;
            --green-bg:       #E8F7F0;
            --green-border:   rgba(26,127,78,0.2);


            --radius:         14px;
            --radius-sm:      9px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text-body);
            display: flex;
            min-height: 100vh;
        }

        /* ════ SIDEBAR ════ */
        .sidebar {
            width: 283px; min-height: 100vh;
            background: var(--sidebar-bg);
            display: flex; flex-direction: column;
            padding: 24px 0;
            position: fixed; top: 0; left: 0;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-logo {
            display: flex; align-items: center; gap: 12px;
            padding: 0 20px 22px;
            border-bottom: 1px solid var(--sidebar-border);
        }

        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #f87171, #9B2C52);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 14px; color: #fff; flex-shrink: 0;
        }

        .logo-label h2 { font-size: 17px; font-weight: 800; color: #fff; line-height: 1; }
        .logo-label span { font-size: 10px; color: var(--sidebar-text); letter-spacing: 0.7px; }

        .sidebar-nav {
            flex: 1; padding: 18px 8px;
            display: flex; flex-direction: column; gap: 2px;
        }

        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 0.6rem 0.9rem; border-radius: 9px;
            color: #ffffff; font-size: 0.92rem; font-weight: 400;
            transition: all 0.22s ease; cursor: pointer;
            border: 1px solid transparent; background: none;
            width: 100%; text-align: left;
            font-family: 'Poppins', sans-serif; text-decoration: none;
        }

        .nav-item:hover {
            background: rgba(190,24,93,0.10);
            border-color: rgba(190,24,93,0.28);
            color: #fff;
            font-weight: 700;
            transform: translateX(5px);
            box-shadow: 0 0 12px rgba(190,24,93,0.35);
        }
        .nav-item.active {
            background: var(--sidebar-active);
            color: #fff;
            font-weight: 700;
            border-color: rgba(155,44,82,0.5);
            box-shadow: 0 0 12px rgba(155,44,82,0.30);
        }
        .nav-item.active:hover {
            background: var(--rose-hover);
            transform: translateX(5px);
        }
        .nav-icon { font-size: 15px; width: 18px; text-align: center; flex-shrink: 0; }

        .sidebar-bottom { padding: 10px; border-top: 1px solid var(--sidebar-border); }

        a.logout-nav {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 13px; border-radius: 9px;
            color: var(--sidebar-text); font-size: 13.5px; font-weight: 500;
            text-decoration: none; transition: all 0.22s ease;
            border: 1px solid transparent;
        }
        a.logout-nav:hover {
            background: rgba(190,24,93,0.10);
            border-color: rgba(190,24,93,0.28);
            color: #E8C0CC;
            font-weight: 700;
            transform: translateX(5px);
            box-shadow: 0 0 12px rgba(190,24,93,0.35);
        }

        /* ════ MAIN ════ */
        .main { margin-left: 283px; flex: 1; display: flex; flex-direction: column; }

        .topbar {
            background: var(--bg-card); border-bottom: 1px solid var(--border);
            padding: 15px 32px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }

        .topbar-title { font-size: 17px; font-weight: 800; color: var(--text-h); }
        .topbar-right { display: flex; align-items: center; gap: 11px; }

        .admin-badge {
            display: flex; align-items: center; gap: 9px;
            background: var(--bg); border: 1px solid var(--border);
            padding: 6px 14px; border-radius: 50px;
        }

        .admin-avatar {
            width: 28px; height: 28px; background: var(--rose);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 11px; color: #fff;
        }

        .admin-info small { display: block; font-size: 9px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; }
        .admin-info span  { font-size: 12.5px; font-weight: 600; color: var(--text-body); }

        .role-chip {
            background: var(--rose-muted); color: var(--rose);
            font-size: 10px; font-weight: 700;
            padding: 4px 12px; border-radius: 50px;
            text-transform: uppercase; letter-spacing: 0.7px;
            border: 1px solid var(--rose-border);
        }

        /* ════ CONTENT ════ */
        .content { padding: 30px 32px; flex: 1; }
        .page { display: none; }
        .page.active { display: block; }

        .page-heading { margin-bottom: 24px; }
        .page-heading h1 { font-size: 22px; font-weight: 800; color: var(--text-h); }
        .page-heading p  { color: var(--text-muted); font-size: 13.5px; margin-top: 4px; }

        /* ── Stat Cards ── */
        .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 26px; }

        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-top: 3px solid var(--rose); border-radius: var(--radius);
            padding: 22px 26px; position: relative; overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(155,44,82,0.10); }
        .stat-card::after { content: ''; position: absolute; top: -30px; right: -30px; width: 110px; height: 110px; background: radial-gradient(circle, rgba(155,44,82,0.05), transparent 70%); border-radius: 50%; }
        .stat-card.green { border-top-color: var(--green); }
        .stat-card.green::after { background: radial-gradient(circle, rgba(26,127,78,0.05), transparent 70%); }

        .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 10px; font-weight: 500; }
        .stat-value { font-size: 42px; font-weight: 800; color: var(--text-h); line-height: 1; }
        .stat-icon  { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 38px; opacity: 0.07; }

        /* ── Section header ── */
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .section-title  { font-size: 15px; font-weight: 700; color: var(--text-h); }

        .view-all-btn {
            background: var(--bg); border: 1px solid var(--border);
            color: var(--text-muted); padding: 5px 15px; border-radius: 50px;
            font-size: 12px; cursor: pointer; transition: all 0.18s;
            font-family: 'Poppins', sans-serif;
        }
        .view-all-btn:hover { color: var(--rose); border-color: var(--rose-border); background: var(--rose-muted); }
        .section-count { background: var(--bg); border: 1px solid var(--border); padding: 4px 13px; border-radius: 50px; font-size: 12px; color: var(--text-muted); }

        /* ── Table ── */
        .table-wrap { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: var(--sidebar-bg); }
        th { padding: 13px 20px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.9px; color: rgba(255,255,255,0.7); text-align: left; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.12s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #FAF5FC; }
        td { padding: 13px 20px; font-size: 13.5px; vertical-align: middle; color: var(--text-body); }
        .num-cell { color: var(--text-muted); font-size: 12px; }

        .username-cell { display: flex; align-items: center; gap: 10px; font-weight: 600; color: var(--text-h); }
        .user-dot { width: 30px; height: 30px; background: var(--rose-muted); border: 1px solid var(--rose-border); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--rose); flex-shrink: 0; }
        .email-cell { color: var(--text-muted); font-size: 13px; }

        .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border-radius: 50px; font-size: 11px; font-weight: 600; }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
        .status-badge.online    { background: var(--green-bg);  color: var(--green); border: 1px solid var(--green-border); }
        .status-badge.offline   { background: #F3EEF5; color: var(--text-muted); border: 1px solid var(--border); }
        .status-badge.active    { background: var(--green-bg);  color: var(--green); border: 1px solid var(--green-border); }

        /* ── Action Buttons ── */
        .actions-cell { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }

        .btn-action {
            padding: 6px 13px; border-radius: var(--radius-sm);
            font-size: 12px; font-weight: 600; cursor: pointer;
            transition: all 0.18s; font-family: 'Poppins', sans-serif;
            border: 1px solid;
        }
        .btn-action.edit     { background: #EEF2FF; color: #3730A3; border-color: #C7D2FE; }
        .btn-action.edit:hover { background: #3730A3; color: #fff; border-color: #3730A3; }


        .btn-action.delete { background: var(--rose-muted); color: var(--rose); border-color: var(--rose-border); }
        .btn-action.delete:hover { background: var(--rose); color: #fff; border-color: var(--rose); }

        .empty-row td { text-align: center; padding: 48px; color: var(--text-muted); font-size: 14px; }

        /* ════ ACCOUNT PAGE ════ */
        .account-grid { display: grid; grid-template-columns: 260px 1fr; gap: 20px; }

        .profile-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0 22px 26px;
            display: flex; flex-direction: column;
            align-items: center; gap: 10px; text-align: center; overflow: hidden;
        }

        .profile-banner { width: calc(100% + 44px); margin: 0 -22px; height: 68px; background: var(--sidebar-bg); flex-shrink: 0; }
        .profile-avatar {
            width: 74px; height: 74px; background: var(--rose);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 26px; color: #fff;
            border: 4px solid var(--bg-card); margin-top: -37px;
        }
        .profile-name { font-size: 18px; font-weight: 700; color: var(--text-h); }
        .profile-role { background: var(--rose-muted); color: var(--rose); font-size: 10px; font-weight: 700; padding: 4px 13px; border-radius: 50px; text-transform: uppercase; letter-spacing: 1px; border: 1px solid var(--rose-border); }
        .profile-username { color: var(--text-muted); font-size: 13px; }

        .credentials-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 26px; }
        .cred-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
        .cred-title { font-size: 15px; font-weight: 700; color: var(--text-h); }

        .btn-edit { display: flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; transition: all 0.18s; border: 1px solid var(--rose-border); background: var(--rose-muted); color: var(--rose); }
        .btn-edit:hover { background: var(--rose); color: #fff; border-color: var(--rose); }

        .btn-save { display: flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; transition: all 0.18s; background: var(--rose); color: #fff; border: 1px solid var(--rose); }
        .btn-save:hover { background: var(--rose-hover); }

        .btn-cancel-edit { display: flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; transition: all 0.18s; background: var(--bg); color: var(--text-muted); border: 1px solid var(--border); }
        .btn-cancel-edit:hover { background: var(--border); }

        .header-actions { display: flex; gap: 8px; }
        .cred-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .cred-item label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 7px; font-weight: 500; }
        .cred-value { background: var(--bg-input); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 11px 14px; font-size: 13.5px; color: var(--text-body); width: 100%; }

        .cred-input { background: #fff; border: 1.5px solid var(--rose-border); border-radius: var(--radius-sm); padding: 11px 14px; font-size: 13.5px; color: var(--text-h); width: 100%; font-family: 'Poppins', sans-serif; transition: border-color 0.18s; display: none; }
        .cred-input:focus { outline: none; border-color: var(--rose); box-shadow: 0 0 0 3px rgba(155,44,82,0.08); }
        .cred-item.full { grid-column: 1 / -1; }
        .pw-hint { font-size: 11px; color: var(--text-muted); margin-top: 5px; display: none; }

        .alert { padding: 12px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
        .alert.success { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-border); }
        .alert.error   { background: #FEF0F0; color: #C0392B; border: 1px solid rgba(192,57,43,0.2); }

        /* ════ MODALS (shared base) ════ */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(45,11,34,0.5);
            backdrop-filter: blur(6px);
            display: none; align-items: center; justify-content: center;
            z-index: 999;
            padding: 20px;
        }
        .modal-overlay.show { display: flex; }

        .modal-box {
            background: var(--bg-card); border: 1px solid var(--border);
            border-top: 4px solid var(--rose); border-radius: 18px;
            padding: 32px; width: 500px; max-width: 100%;
            box-shadow: 0 24px 60px rgba(45,11,34,0.18);
            animation: popIn 0.22s ease;
            max-height: 90vh; overflow-y: auto;
        }
        .modal-box.sm { width: 380px; text-align: center; }

        @keyframes popIn { from { transform: scale(0.88); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
        .modal-title-text { font-size: 17px; font-weight: 700; color: var(--text-h); }
        .modal-close { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; line-height: 1; padding: 2px 6px; border-radius: 6px; transition: background 0.15s; }
        .modal-close:hover { background: var(--border); }

        /* Form inside modal */
        .modal-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .modal-form-grid .full { grid-column: 1 / -1; }

        .form-group label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 7px; font-weight: 500; }
        .form-group input {
            width: 100%; padding: 11px 14px;
            background: #fff; border: 1.5px solid var(--border);
            border-radius: var(--radius-sm); font-size: 13.5px;
            color: var(--text-h); font-family: 'Poppins', sans-serif;
            transition: border-color 0.18s;
        }
        .form-group input:focus { outline: none; border-color: var(--rose); box-shadow: 0 0 0 3px rgba(155,44,82,0.08); }
        .form-group input::placeholder { color: var(--text-muted); }

        .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--border); }

        /* Confirm modal internals */
        .modal-icon  { font-size: 40px; margin-bottom: 12px; }
        .modal-title { font-size: 18px; font-weight: 700; color: var(--text-h); margin-bottom: 8px; }
        .modal-desc  { color: var(--text-muted); font-size: 13.5px; margin-bottom: 26px; line-height: 1.6; }
        .modal-actions { display: flex; gap: 11px; }

        .btn-confirm        { flex: 1; padding: 12px; background: var(--rose); color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.18s; font-family: 'Poppins', sans-serif; }
        .btn-confirm:hover  { background: var(--rose-hover); }
        .btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-confirm.green        { background: var(--green); }
        .btn-confirm.green:hover  { background: #155C39; }

        .btn-cancel-modal       { flex: 1; padding: 12px; background: var(--bg); color: var(--text-body); border: 1px solid var(--border); border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.18s; font-family: 'Poppins', sans-serif; }
        .btn-cancel-modal:hover { background: var(--border); }

        /* Submit / cancel in form modals */
        .btn-modal-submit { padding: 10px 22px; background: var(--rose); color: #fff; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; transition: background 0.18s; }
        .btn-modal-submit:hover { background: var(--rose-hover); }
        .btn-modal-cancel { padding: 10px 18px; background: var(--bg); color: var(--text-muted); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; transition: background 0.18s; }
        .btn-modal-cancel:hover { background: var(--border); }

        .pw-hint-modal { font-size: 11px; color: var(--text-muted); margin-top: 5px; }

        @media (max-width: 900px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 180px; }
            .main    { margin-left: 180px; }
            .stats-row, .account-grid { grid-template-columns: 1fr; }
            .content { padding: 20px 16px; }
            .modal-form-grid { grid-template-columns: 1fr; }
            .modal-form-grid .full { grid-column: 1; }
        }
    </style>
</head>
<body>

<!-- ════════════════ SIDEBAR ════════════════ -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">iP</div>
        <div class="logo-label">
            <h2>iPOS</h2>
            <span>Super Admin</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <button class="nav-item <?= $open_page === 'dashboard' ? 'active' : '' ?>" onclick="showPage('dashboard', this)">
            <span class="nav-icon">📊</span> Dashboard
        </button>
        <button class="nav-item <?= $open_page === 'owners' ? 'active' : '' ?>" onclick="showPage('owners', this)">
            <span class="nav-icon">🍔</span> Fastfood Owners
        </button>
        <button class="nav-item <?= $open_page === 'account' ? 'active' : '' ?>" onclick="showPage('account', this)">
            <span class="nav-icon">👤</span> My Account
        </button>
    </nav>

    <div class="sidebar-bottom">
        <a class="logout-nav" href="../logout.php">
            <span class="nav-icon">🚪</span> Logout
        </a>
    </div>
</aside>

<!-- ════════════════ MAIN ════════════════ -->
<div class="main">

    <header class="topbar">
        <div class="topbar-title" id="topbar-title">
            <?= $open_page === 'account' ? 'My Account' : ($open_page === 'owners' ? 'Fastfood Owners' : 'Dashboard') ?>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">
                <div class="admin-avatar">SA</div>
                <div class="admin-info">
                    <small>Logged in as</small>
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                </div>
            </div>
            <span class="role-chip">👑 Superadmin</span>
        </div>
    </header>

    <div class="content">

        <!-- ═══ DASHBOARD ═══ -->
        <div class="page <?= $open_page === 'dashboard' ? 'active' : '' ?>" id="page-dashboard">
            <div class="page-heading">
                <h1>Welcome back, Your Grace! 👑</h1>
                <p>Here's an overview of the iPOS system.</p>
            </div>

            <?php if ($flash_success): ?>
                <div class="alert success">✅ <?= $flash_success ?></div>
            <?php endif; ?>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-label">Total Fastfood Owners</div>
                    <div class="stat-value"><?= $total_admins ?></div>
                    <div class="stat-icon">🍔</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active Logged-in Devices</div>
                    <div class="stat-value"><?= $total_active_devices ?></div>
                    <div class="stat-icon">📱</div>
                </div>
            </div>

            <div class="section-header">
                <div class="section-title">Recent Owners</div>
                <button class="view-all-btn" onclick="showPage('owners', document.querySelectorAll('.nav-item')[1])">View All →</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead><tr><th>No.</th><th>Username</th><th>Email</th><th>Fastfood</th><th>Online</th></tr></thead>
                    <tbody>
                        <?php if (empty($admins_full)): ?>
                            <tr class="empty-row"><td colspan="6">No fastfood owners registered yet.</td></tr>
                        <?php else: $i=1; foreach (array_slice($admins_full,0,5) as $a):
                            $on = $val->isAdminOnline($a['admin_id']);
                        ?>
                            <tr>
                                <td class="num-cell"><?= $i++ ?></td>
                                <td><div class="username-cell"><div class="user-dot"><?= strtoupper(substr($a['username'],0,1)) ?></div><?= htmlspecialchars($a['username']) ?></div></td>
                                <td class="email-cell"><?= htmlspecialchars($a['email']) ?></td>
                                <td><?= htmlspecialchars($a['fastfood_name']) ?></td>
                                <td><span class=\"status-badge <?= $on ? 'online' : 'offline' ?>"><?= $on ? 'Online' : 'Offline' ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ OWNERS ═══ -->
        <div class="page <?= $open_page === 'owners' ? 'active' : '' ?>" id="page-owners">
            <div class="page-heading">
                <h1>Fastfood Owners</h1>
                <p>Manage all registered fastfood owner accounts.</p>
            </div>

            <?php if ($flash_success && $open_page === 'owners'): ?>
                <div class="alert success">✅ <?= $flash_success ?></div>
            <?php endif; ?>
            <?php if ($edit_error): ?>
                <div class="alert error">⚠️ <?= htmlspecialchars($edit_error) ?></div>
            <?php endif; ?>

            <div class="section-header">
                <div class="section-title">All Owners</div>
                
            </div>

            <div class="table-wrap">
                <table>
                    <thead><tr><th>No.</th><th>Username</th><th>Full Name</th><th>Email</th><th>Fastfood</th><th>Online</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($admins_full)): ?>
                            <tr class="empty-row"><td colspan="8">No fastfood owners registered yet.</td></tr>
                        <?php else: $i=1; foreach ($admins_full as $a):
                            $on     = $val->isAdminOnline($a['admin_id']);
                        ?>
                            <tr>
                                <td class="num-cell"><?= $i++ ?></td>
                                <td><div class="username-cell"><div class="user-dot"><?= strtoupper(substr($a['username'],0,1)) ?></div><?= htmlspecialchars($a['username']) ?></div></td>
                                <td><?= htmlspecialchars($a['fullname'] ?? '—') ?></td>
                                <td class="email-cell"><?= htmlspecialchars($a['email']) ?></td>
                                <td><?= htmlspecialchars($a['fastfood_name']) ?></td>
                                <td><span class=\"status-badge <?= $on ? 'online' : 'offline' ?>"><?= $on ? 'Online' : 'Offline' ?></span></td>
                                <td>
                                    <div class="actions-cell">
                                        <button class="btn-action delete open-delete-modal"
                                            data-id="<?= $a['admin_id'] ?>"
                                            data-name="<?= htmlspecialchars($a['username']) ?>">
                                            🗑 Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ ACCOUNT ═══ -->
        <div class="page <?= $open_page === 'account' ? 'active' : '' ?>" id="page-account">
            <div class="page-heading">
                <h1>My Account</h1>
                <p>Your superadmin credentials and profile.</p>
            </div>

            <?php if (!empty($update_message)): ?>
                <div class="alert <?= $update_type ?>">
                    <?= $update_type === 'success' ? '✅' : '⚠️' ?>
                    <?= htmlspecialchars($update_message) ?>
                </div>
            <?php endif; ?>

            <div class="account-grid">
                <div class="profile-card">
                    <div class="profile-banner"></div>
                    <div class="profile-avatar" id="profile-avatar-initials">SA</div>
                    <div class="profile-name" id="profile-display-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                    <span class="profile-role">👑 Superadmin</span>
                    <div class="profile-username" id="profile-display-handle">@<?= htmlspecialchars($_SESSION['username']) ?></div>
                </div>

                <div class="credentials-card">
                    <div class="cred-header">
                        <div class="cred-title">Account Credentials</div>
                        <div class="header-actions">
                            <button class="btn-edit" id="btn-edit" onclick="enableEdit()">✏️ Edit</button>
                            <button class="btn-save"        id="btn-save"   onclick="submitForm()" style="display:none;">💾 Save Changes</button>
                            <button class="btn-cancel-edit" id="btn-cancel" onclick="cancelEdit()" style="display:none;">✕ Cancel</button>
                        </div>
                    </div>

                    <form method="POST" id="cred-form">
                        <input type="hidden" name="update_credentials" value="1">
                        <div class="cred-grid">
                            <div class="cred-item">
                                <label>Username</label>
                                <div class="cred-value" id="view-username"><?= htmlspecialchars($_SESSION['username']) ?></div>
                                <input class="cred-input" id="edit-username" type="text" name="new_username" value="<?= htmlspecialchars($_SESSION['username']) ?>" autocomplete="off">
                            </div>
                            <div class="cred-item">
                                <label>Role</label>
                                <div class="cred-value">Super Administrator</div>
                            </div>
                            <div class="cred-item full">
                                <label>Email Address</label>
                                <div class="cred-value" id="view-email"><?= htmlspecialchars($_SESSION['email'] ?? 'ipossuperadmin@gmail.com') ?></div>
                                <input class="cred-input" id="edit-email" type="email" name="new_email" value="<?= htmlspecialchars($_SESSION['email'] ?? 'ipossuperadmin@gmail.com') ?>" autocomplete="off">
                            </div>
                            <div class="cred-item">
                                <label>New Password</label>
                                <div class="cred-value" id="view-password" style="letter-spacing:5px;color:var(--text-muted);">••••••••••</div>
                                <input class="cred-input" id="edit-password" type="password" name="new_password" placeholder="Leave blank to keep current" autocomplete="new-password">
                                <div class="pw-hint" id="pw-hint">Leave blank to keep your current password unchanged.</div>
                            </div>
                            <div class="cred-item">
                                <label>Confirm New Password</label>
                                <div class="cred-value" id="view-confirm" style="letter-spacing:5px;color:var(--text-muted);">••••••••••</div>
                                <input class="cred-input" id="edit-confirm" type="password" name="confirm_password" placeholder="Repeat new password" autocomplete="new-password">
                            </div>
                            <div class="cred-item full">
                                <label>Account Status</label>
                                <div class="cred-value"><span class="status-badge active">Active</span></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->


<!-- ════ EDIT OWNER MODAL ════ -->
<div class="modal-overlay" id="editOwnerModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title-text">✏️ Edit Owner Account</div>
            <button class="modal-close" onclick="closeModal('editOwnerModal')">✕</button>
        </div>

        <form method="POST" action="superadmin.php?page=owners">
            <input type="hidden" name="edit_owner" value="1">
            <input type="hidden" name="edit_owner_id" id="edit_owner_id" value="">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="edit_owner_username" id="edit_owner_username" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="edit_owner_fullname" id="edit_owner_fullname" placeholder="Full name" required>
                </div>
                <div class="form-group full">
                    <label>Email Address *</label>
                    <input type="email" name="edit_owner_email" id="edit_owner_email" placeholder="Email" required>
                </div>
                <div class="form-group full">
                    <label>Fastfood / Store Name *</label>
                    <input type="text" name="edit_owner_fastfood" id="edit_owner_fastfood" placeholder="Store name" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="edit_owner_password" id="edit_owner_password" placeholder="Leave blank to keep current">
                    <div class="pw-hint-modal">Leave blank to keep their current password.</div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="edit_owner_confirm" id="edit_owner_confirm" placeholder="Repeat new password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('editOwnerModal')">Cancel</button>
                <button type="submit" class="btn-modal-submit">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>


<!-- ════ DELETE MODAL ════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box sm">
        <div class="modal-icon">🗑️</div>
        <div class="modal-title">Delete Owner Account</div>
        <div class="modal-desc" id="deleteDesc">Are you sure you want to permanently delete this account? This action cannot be undone.</div>
        <div class="modal-actions">
            <button class="btn-confirm" id="confirmDelete">Yes, Delete</button>
            <button class="btn-cancel-modal" id="cancelDelete">Cancel</button>
        </div>
    </div>
</div>

<script>
/* ── PAGE SWITCH ── */
function showPage(id, btn) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('page-' + id).classList.add('active');
    if (btn) btn.classList.add('active');
    const titles = { dashboard:'Dashboard', owners:'Fastfood Owners', account:'My Account' };
    document.getElementById('topbar-title').textContent = titles[id] || id;
}

/* ── MODAL HELPERS ── */
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

// Close on backdrop click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('show'); });
});


/* ── EDIT OWNER MODAL ── */
document.querySelectorAll('.open-edit-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_owner_id').value         = btn.dataset.id;
        document.getElementById('edit_owner_username').value   = btn.dataset.username;
        document.getElementById('edit_owner_fullname').value   = btn.dataset.fullname;
        document.getElementById('edit_owner_email').value      = btn.dataset.email;
        document.getElementById('edit_owner_fastfood').value   = btn.dataset.fastfood;
        document.getElementById('edit_owner_password').value   = '';
        document.getElementById('edit_owner_confirm').value    = '';
        openModal('editOwnerModal');
    });
});




/* ── DELETE MODAL ── */
let deleteId = null;
const deleteModal = document.getElementById('deleteModal');
const confirmDeleteBtn = document.getElementById('confirmDelete');

document.querySelectorAll('.open-delete-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        deleteId = btn.dataset.id;
        const name = btn.dataset.name;
        document.getElementById('deleteDesc').textContent = `Are you sure you want to permanently delete "${name}"? This action cannot be undone.`;
        openModal('deleteModal');
    });
});

confirmDeleteBtn.addEventListener('click', () => {
    confirmDeleteBtn.textContent = 'Deleting…';
    confirmDeleteBtn.disabled = true;
    window.location.href = 'superadmin.php?page=owners&delete=' + deleteId;
});

document.getElementById('cancelDelete').addEventListener('click', () => { closeModal('deleteModal'); deleteId = null; });

/* ── ACCOUNT INLINE EDIT ── */
const FIELDS = ['username','email','password','confirm'];

function enableEdit() {
    FIELDS.forEach(f => {
        const view  = document.getElementById('view-'  + f);
        const input = document.getElementById('edit-'  + f);
        if (view)  view.style.display  = 'none';
        if (input) input.style.display = 'block';
    });
    document.getElementById('pw-hint').style.display = 'block';
    document.getElementById('btn-edit').style.display   = 'none';
    document.getElementById('btn-save').style.display   = 'flex';
    document.getElementById('btn-cancel').style.display = 'flex';
}

function cancelEdit() {
    FIELDS.forEach(f => {
        const view  = document.getElementById('view-'  + f);
        const input = document.getElementById('edit-'  + f);
        if (view)  view.style.display  = 'block';
        if (input) { input.style.display = 'none'; if (f === 'password' || f === 'confirm') input.value = ''; }
    });
    document.getElementById('pw-hint').style.display = 'none';
    document.getElementById('btn-edit').style.display   = 'flex';
    document.getElementById('btn-save').style.display   = 'none';
    document.getElementById('btn-cancel').style.display = 'none';
}

function submitForm() { document.getElementById('cred-form').submit(); }

/* Live profile preview while typing */
document.addEventListener('DOMContentLoaded', () => {
    const usernameInput = document.getElementById('edit-username');
    if (usernameInput) {
        usernameInput.addEventListener('input', () => {
            const val = usernameInput.value || 'superadmin';
            document.getElementById('profile-display-name').textContent   = val;
            document.getElementById('profile-display-handle').textContent = '@' + val;
            document.getElementById('profile-avatar-initials').textContent = val.substring(0,2).toUpperCase();
        });
    }

    /* Auto-open correct page + modal from URL */
    const params  = new URLSearchParams(window.location.search);
    const urlPage = params.get('page');
    const urlModal = params.get('modal');
    const urlId   = params.get('id');

    if (urlPage) {
        const btns = document.querySelectorAll('.nav-item');
        const map  = { dashboard:0, owners:1, account:2 };
        if (map[urlPage] !== undefined) showPage(urlPage, btns[map[urlPage]]);
    }


    // Auto-open edit modal if there was a validation error
    if (urlModal === 'edit' && urlId) {
        const editBtn = document.querySelector(`.open-edit-modal[data-id="${urlId}"]`);
        if (editBtn) editBtn.click();
    }
});
</script>

</body>
</html>