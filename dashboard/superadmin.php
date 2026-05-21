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
    // Log session expiry due to deleted account
    try {
        $db_tmp   = new Database();
        $conn_tmp = $db_tmp->connect();
        require_once __DIR__ . "/../config/audit_helper.php";
        audit_log($conn_tmp, $_SESSION, 'superadmin_session_expired', 'superadmin', $_SESSION['admin_id'] ?? null, $_SESSION['username'] ?? null, 'Session terminated — account no longer exists');
    } catch (Exception $e) {}
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

require_once __DIR__ . "/../config/audit_helper.php";

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
        $pw_note = $update_pw ? ' (password changed)' : '';
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
        audit_log($conn, $_SESSION, 'superadmin_credentials_updated', 'superadmin', $admin_id, $new_username, $update_pw ? 'Username, email and password updated' : 'Username and email updated');
        $_SESSION['cred_success'] = "Credentials updated successfully!";
        header("Location: superadmin.php?page=account");
        exit;
    } else {
        $update_message = implode(" ", $errors);
        $update_type    = "error";
    }
}

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

$edit_error = "";
$edit_old   = [];
if (isset($_SESSION['edit_error'])) {
    $edit_error = $_SESSION['edit_error'];
    $edit_old   = $_SESSION['edit_old'] ?? [];
    unset($_SESSION['edit_error'], $_SESSION['edit_old']);
}

/* ── DELETE ADMIN ── */
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Grab username before deletion for the log
    $del_stmt = $conn->prepare("SELECT username, fastfood_name FROM admins WHERE admin_id = :id");
    $del_stmt->execute([':id' => $del_id]);
    $del_row = $del_stmt->fetch(PDO::FETCH_ASSOC);
    $val->deleteAdmin($del_id);
    audit_log($conn, $_SESSION, 'owner_deleted', 'owner', $del_id, $del_row['username'] ?? "ID:{$del_id}", "Deleted owner: " . ($del_row['username'] ?? '') . " (" . ($del_row['fastfood_name'] ?? '') . ")");
    $_SESSION['flash_success'] = "Owner account deleted.";
    header("Location: superadmin.php?page=owners");
    exit;
}

/* ── UPDATE MAX DEVICES ── */
if (isset($_POST['update_devices'])) {
    $dev_admin_id = (int)$_POST['admin_id'];
    $dev_max      = (int)$_POST['max_devices'];
    $val->updateMaxDevices($dev_admin_id, $dev_max);
    $dev_stmt = $conn->prepare("SELECT username FROM admins WHERE admin_id = :id");
    $dev_stmt->execute([':id' => $dev_admin_id]);
    $dev_row = $dev_stmt->fetch(PDO::FETCH_ASSOC);
    audit_log($conn, $_SESSION, 'owner_max_devices_updated', 'owner', $dev_admin_id, $dev_row['username'] ?? "ID:{$dev_admin_id}", "Max devices set to {$dev_max}");
    header("Location: superadmin.php");
    exit;
}

$total_admins         = $val->countAdmins();
$total_active_devices = $val->countActiveSessions();
$admins               = $val->getAllOwners();

$stmt = $conn->prepare("SELECT admin_id, username, email, fullname, fastfood_name, last_login FROM admins WHERE role='owner' ORDER BY admin_id DESC");
$stmt->execute();
$admins_full = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_admins  = count($admins_full);

// Build JSON of all owners for JS search (used by the enhanced search)
$owners_for_search = [];
foreach ($admins_full as $a) {
    $owners_for_search[] = [
        'id'       => (int)$a['admin_id'],
        'username' => $a['username'],
        'fullname' => $a['fullname'] ?? '',
        'email'    => $a['email'],
        'fastfood' => $a['fastfood_name'],
        'online'   => (bool)$val->isAdminOnline($a['admin_id']),
    ];
}

$open_page    = $_GET['page']  ?? 'dashboard';
$open_modal   = $_GET['modal'] ?? '';
$open_edit_id = (int)($_GET['id'] ?? 0);

/* ── AUDIT LOG: fetch latest 200 entries ── */
$audit_logs = [];
try {
    $al_stmt = $conn->query(
        "SELECT l.log_id, l.actor_name, l.action, l.target_type, l.target_id, l.target_label,
                l.detail, l.ip_address, l.created_at
         FROM audit_log l
         ORDER BY l.log_id DESC
         LIMIT 200"
    );
    $audit_logs = $al_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table may not exist yet — handled gracefully in the view
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iPOS — Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <style>
        .nav-svg { width:16px; height:16px; display:inline-block; vertical-align:middle; fill:currentColor; flex-shrink:0; }
        .search-svg { width:14px; height:14px; display:inline-block; vertical-align:middle; fill:currentColor; }
    </style>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-active: var(--accent);
            --sidebar-hover:  rgba(255,255,255,0.07);
            --sidebar-text:   rgba(255,255,255,0.55);
            --sidebar-border: rgba(255,255,255,0.07);

            --bg:             var(--body-bg);
            --bg-card:        var(--card-bg);
            --bg-input:       var(--accent-light);

            --rose:           var(--accent);
            --rose-hover:     var(--accent-dark);
            --rose-muted:     var(--accent-light);
            --rose-border:    var(--border-color);

            --text-h:         var(--text-primary);
            --text-body:      var(--text-primary);
            --text-muted:     var(--text-secondary);

            --border:         var(--border-color);

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
            transition: transform .25s ease;
        }
        .sidebar.collapsed { transform: translateX(-100%); }

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
        .main { margin-left: 283px; flex: 1; display: flex; flex-direction: column; transition: margin-left .25s ease; }
        .main.sidebar-collapsed { margin-left: 0; }

        .topbar {
            background: var(--bg-card); border-bottom: 1px solid var(--border);
            padding: 0 28px;
            height: 58px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 50; gap: 16px;
        }

        .topbar-left { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }

        .sidebar-toggle-btn {
            background: none; border: none; cursor: pointer;
            display: flex; flex-direction: column; gap: 5px; padding: 6px;
            flex-shrink: 0;
        }
        .sidebar-toggle-btn span {
            display: block; width: 22px; height: 2px;
            background: var(--text-h); border-radius: 2px;
            transition: .2s;
        }

        /* ════ SEARCH WRAPPER (relative for dropdown) ════ */
        .topbar-search {
            flex: 1; display: flex; justify-content: center;
            position: relative;
        }
        .search-inner {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg); border: 1.5px solid var(--border);
            border-radius: 24px; padding: 0 16px;
            max-width: 420px; width: 100%;
            transition: border-color .18s, box-shadow .18s;
        }
        .search-inner.focused {
            border-color: var(--rose);
            box-shadow: 0 0 0 3px rgba(155,44,82,0.09);
        }
        .search-icon { font-size: 14px; flex-shrink: 0; }
        .topbar-search input {
            border: none; background: none; outline: none;
            font-size: 13px; font-family: 'Poppins', sans-serif;
            color: var(--text-h); padding: 9px 0; width: 100%;
        }
        .search-kbd {
            font-size: 10px; color: var(--text-muted);
            background: var(--border); padding: 2px 7px;
            border-radius: 5px; white-space: nowrap; flex-shrink: 0;
        }

        /* ════ SEARCH DROPDOWN ════ */
        #search-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            left: 50%; transform: translateX(-50%);
            width: 420px; max-width: 96vw;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 16px 48px rgba(45,11,34,0.16);
            z-index: 999;
            overflow: hidden;
            animation: dropIn .15s ease;
        }
        #search-dropdown.open { display: block; }
        @keyframes dropIn { from { opacity:0; transform: translateX(-50%) translateY(-6px); } to { opacity:1; transform: translateX(-50%) translateY(0); } }

        /* Filter chips row */
        .sd-chips {
            display: flex; gap: 6px; align-items: center;
            padding: 10px 14px 8px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .sd-chip {
            padding: 4px 12px; border-radius: 50px; font-size: 11px; font-weight: 600;
            cursor: pointer; border: 1px solid var(--border);
            background: var(--bg); color: var(--text-muted);
            transition: all .15s; user-select: none;
            display: flex; align-items: center; gap: 4px;
        }
        .sd-chip:hover { border-color: var(--rose-border); color: var(--rose); background: var(--rose-muted); }
        .sd-chip.active { background: var(--rose); color: #fff; border-color: var(--rose); }
        .sd-chip-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* Section header inside dropdown */
        .sd-section-label {
            font-size: 10px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.9px; color: var(--text-muted);
            padding: 10px 14px 4px;
        }

        /* Page results */
        .sd-page-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 14px; cursor: pointer;
            transition: background .12s;
        }
        .sd-page-item:hover, .sd-page-item.focused { background: var(--rose-muted); }
        .sd-page-icon { font-size: 14px; width: 28px; height: 28px; background: var(--bg); border: 1px solid var(--border); border-radius: 7px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .sd-page-name { font-size: 13px; font-weight: 600; color: var(--text-h); }
        .sd-page-desc { font-size: 11px; color: var(--text-muted); margin-top: 1px; }

        /* Owner results */
        .sd-owner-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 14px; cursor: pointer;
            transition: background .12s;
        }
        .sd-owner-item:hover, .sd-owner-item.focused { background: var(--rose-muted); }

        .sd-owner-dot {
            width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 12px; color: var(--rose);
            background: var(--rose-muted); border: 1px solid var(--rose-border);
            position: relative;
        }
        .sd-owner-dot.online { background: var(--green-bg); color: var(--green); border-color: var(--green-border); }
        .sd-owner-dot .online-pip {
            position: absolute; bottom: -2px; right: -2px;
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--green); border: 1.5px solid #fff;
        }

        .sd-owner-meta { flex: 1; min-width: 0; }
        .sd-owner-name { font-size: 13px; font-weight: 600; color: var(--text-h); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sd-owner-sub  { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .sd-status-badge {
            font-size: 10px; font-weight: 600; padding: 3px 9px; border-radius: 50px;
            white-space: nowrap; flex-shrink: 0;
        }
        .sd-status-badge.online  { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-border); }
        .sd-status-badge.offline { background: #F3EEF5; color: var(--text-muted); border: 1px solid var(--border); }

        /* Highlight mark */
        mark.sh { background: #FFE082; color: var(--text-h); border-radius: 3px; padding: 0 1px; }

        /* Footer / count line */
        .sd-footer {
            padding: 8px 14px;
            font-size: 11px; color: var(--text-muted);
            border-top: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .sd-footer kbd {
            background: var(--border); border-radius: 4px;
            padding: 1px 6px; font-size: 10px; font-family: 'Poppins', sans-serif;
        }

        .sd-empty {
            padding: 28px 14px; text-align: center;
            font-size: 13px; color: var(--text-muted);
        }
        .sd-divider { border: none; border-top: 1px solid var(--border); margin: 0; }

        /* Row highlight flash */
        @keyframes rowFlash {
            0%   { background: rgba(155,44,82,0.18); }
            100% { background: transparent; }
        }
        .row-highlight { animation: rowFlash 2s ease forwards; }

        .topbar-title { font-size: 17px; font-weight: 800; color: var(--text-h); }
        .topbar-right { display: flex; align-items: center; gap: 11px; flex-shrink: 0; }

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

        /* ════ MODALS ════ */
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

        .modal-icon  { font-size: 40px; margin-bottom: 12px; }
        .modal-title { font-size: 18px; font-weight: 700; color: var(--text-h); margin-bottom: 8px; }
        .modal-desc  { color: var(--text-muted); font-size: 13.5px; margin-bottom: 26px; line-height: 1.6; }
        .modal-actions { display: flex; gap: 11px; }

        .btn-confirm        { flex: 1; padding: 12px; background: var(--rose); color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.18s; font-family: 'Poppins', sans-serif; }
        .btn-confirm:hover  { background: var(--rose-hover); }
        .btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-cancel-modal       { flex: 1; padding: 12px; background: var(--bg); color: var(--text-body); border: 1px solid var(--border); border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.18s; font-family: 'Poppins', sans-serif; }
        .btn-cancel-modal:hover { background: var(--border); }

        .btn-modal-submit { padding: 10px 22px; background: var(--rose); color: #fff; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; transition: background 0.18s; }
        .btn-modal-submit:hover { background: var(--rose-hover); }
        .btn-modal-cancel { padding: 10px 18px; background: var(--bg); color: var(--text-muted); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; transition: background 0.18s; }
        .btn-modal-cancel:hover { background: var(--border); }

        .pw-hint-modal { font-size: 11px; color: var(--text-muted); margin-top: 5px; }

        @media (max-width: 900px) { .stats-row { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 768px) {
            .sidebar { width: 180px; }
            .main    { margin-left: 180px; }
            .stats-row, .account-grid { grid-template-columns: 1fr; }
            .content { padding: 20px 16px; }
            .modal-form-grid { grid-template-columns: 1fr; }
            .modal-form-grid .full { grid-column: 1; }
            #search-dropdown { width: 96vw; }
        }

        /* ════ BACKUP & RECOVERY ════ */
        .br-top-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .br-action-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .br-create-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; }
        .br-box-title  { font-size: 13.5px; font-weight: 700; color: var(--text-h); margin-bottom: 12px; }
        .br-box-body   { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .upload-row    { flex-wrap: nowrap; }
        .br-box-note   { margin-top: 10px; font-size: 11.5px; color: var(--text-muted); }

        /* Summary & Info cards */
        .br-summary-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; }
        .br-summary-card .br-box-title { margin-bottom: 14px; }
        .br-info-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid var(--border); }
        .br-info-row:last-child { border-bottom: none; }
        .br-ir-lbl { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .br-ir-val { font-size: 13px; font-weight: 700; color: var(--text-h); }
        .br-about-text { font-size: 13px; color: var(--text-muted); line-height: 1.75; }

        .br-input { flex: 1; min-width: 0; padding: 9px 13px; border: 1.5px solid var(--border); border-radius: 9px; font-size: 13px; font-family: 'Poppins', sans-serif; background: var(--bg); color: var(--text-h); outline: none; transition: border-color .15s; }
        .br-input:focus { border-color: var(--rose); }
        .br-file-label { flex: 1; min-width: 0; display: block; padding: 9px 13px; border: 1.5px dashed var(--border); border-radius: 9px; font-size: 12.5px; color: var(--text-muted); background: var(--bg); cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: border-color .15s; }
        .br-file-label:hover { border-color: var(--rose); }
        .br-btn { padding: 9px 18px; border: none; border-radius: 9px; font-size: 13px; font-family: 'Poppins', sans-serif; font-weight: 600; cursor: pointer; transition: opacity .15s, transform .1s; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
        .br-btn:hover:not(:disabled) { opacity: .87; transform: translateY(-1px); }
        .br-btn:disabled { opacity: .55; cursor: default; }
        .br-btn.primary { background: var(--sidebar-active); color: #fff; }

        /* ── Announcement & Maintenance styles ── */
        .am-toggle-wrap { position:relative; display:inline-block; width:44px; height:24px; cursor:pointer; }
        .am-toggle-wrap input { opacity:0; width:0; height:0; }
        .am-slider { position:absolute; inset:0; background:#ccc; border-radius:24px; transition:.3s; }
        .am-slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.3s; }
        .am-toggle-wrap input:checked + .am-slider { background:var(--sidebar-active); }
        .am-toggle-wrap input:checked + .am-slider::before { transform:translateX(20px); }

        .am-toast { padding:10px 14px; border-radius:8px; font-size:13px; font-weight:500; margin-bottom:14px; }
        .am-toast.success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
        .am-toast.error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

        .am-warning-note { background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:11px 14px; font-size:12.5px; color:#92400e; margin-bottom:16px; line-height:1.6; }

        .am-banner { padding:11px 16px; border-radius:9px; font-size:13.5px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .am-banner-info    { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
        .am-banner-warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
        .am-banner-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
        .am-banner-danger  { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }

        /* Announcement banner shown in admin dashboard */
        .global-ann-bar { padding:12px 20px; font-size:13.5px; font-weight:500; display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .global-ann-bar .ann-dismiss { background:none; border:none; cursor:pointer; opacity:.6; font-size:16px; padding:0 4px; }
        .global-ann-bar .ann-dismiss:hover { opacity:1; }

        @media (max-width: 768px) {
            #page-announcements .stats-row { grid-template-columns: 1fr; }
            #page-announcements > div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
        }
        .br-btn.danger  { background: #c0392b; color: #fff; }
        .br-btn.success { background: #1d8a4f; color: #fff; }
        .br-btn.sm      { padding: 6px 12px; font-size: 12px; border-radius: 7px; }
        .br-list-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .br-loading, .br-empty { text-align: center; padding: 36px; color: var(--text-muted); font-size: 13.5px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; }

        /* Enhanced backup table — wraps in a card */
        .br-section-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
        .br-section-hdr  { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--border); }
        .br-section-hdr-title { font-size: 14px; font-weight: 700; color: var(--text-h); }
        .br-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .br-table thead tr { background: var(--sidebar-bg); }
        .br-table th { padding: 11px 16px; text-align: left; font-weight: 600; font-size: 11px; color: rgba(255,255,255,.8); letter-spacing: .5px; text-transform: uppercase; }
        .br-table td { padding: 12px 16px; border-top: 1px solid var(--border); color: var(--text-body); vertical-align: middle; }
        .br-table tr:hover td { background: rgba(155,44,82,.035); }
        .br-filename { font-family: monospace; font-size: 12px; color: var(--text-muted); }
        .br-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        /* Inline label rename */
        .br-label-wrap { display: flex; align-items: center; gap: 6px; }
        .br-label-text { font-weight: 600; color: var(--text-h); }
        .br-label-input { display: none; padding: 4px 9px; border: 1.5px solid var(--rose-border); border-radius: 7px; font-size: 13px; font-family: 'Poppins', sans-serif; color: var(--text-h); outline: none; width: 160px; }
        .br-label-input:focus { border-color: var(--rose); box-shadow: 0 0 0 3px rgba(155,44,82,0.08); }
        .br-rename-btn  { background: none; border: none; cursor: pointer; font-size: 13px; padding: 2px 5px; border-radius: 5px; color: var(--text-muted); transition: background .12s, color .12s; }
        .br-rename-btn:hover { background: var(--rose-muted); color: var(--rose); }
        .br-save-btn, .br-cancel-btn { display: none; padding: 3px 9px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; border: 1px solid; transition: all .15s; }
        .br-save-btn   { background: var(--rose); color: #fff; border-color: var(--rose); }
        .br-save-btn:hover { background: var(--rose-hover); }
        .br-cancel-btn { background: var(--bg); color: var(--text-muted); border-color: var(--border); }
        .br-cancel-btn:hover { background: var(--border); }
        @media (max-width: 768px) { .br-top-grid { grid-template-columns: 1fr; } .br-action-row { grid-template-columns: 1fr; } .br-actions { gap: 4px; } .br-btn.sm { font-size: 11px; padding: 5px 8px; } }
    </style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <div class="sidebar-logo">
    <?php $logo_size = 38; $logo_show_text = false; include __DIR__ . '/../dashboard/helpers/ipos_logo.php'; ?>
    <div class="logo-label">
        <h2>iPOS</h2>
        <span>Super Admin</span>
    </div>
</div>

    <nav class="sidebar-nav">
        <button class="nav-item <?= $open_page === 'dashboard' ? 'active' : '' ?>" onclick="showPage('dashboard', this)">
            <span class="nav-icon"><svg class="nav-svg" viewBox="0 0 512 512"><path d="M0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256zm320 96c0-26.9-16.5-49.9-40-59.3V88c0-13.3-10.7-24-24-24s-24 10.7-24 24v204.7c-23.5 9.5-40 32.5-40 59.3c0 35.3 28.7 64 64 64s64-28.7 64-64zM144 176a32 32 0 1 0 0-64 32 32 0 1 0 0 64zm-16 80a32 32 0 1 0 -64 0 32 32 0 1 0 64 0zm288 32a32 32 0 1 0 0-64 32 32 0 1 0 0 64zM400 144a32 32 0 1 0 -64 0 32 32 0 1 0 64 0z"/></svg></span> Dashboard
        </button>
        <button class="nav-item <?= $open_page === 'owners' ? 'active' : '' ?>" onclick="showPage('owners', this)">
            <span class="nav-icon"><svg class="nav-svg" viewBox="0 0 640 512"><path d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM609.3 512H471.4c5.4-9.4 8.6-20.3 8.6-32v-8c0-60.7-27.1-115.2-69.8-151.8c2.4-.1 4.7-.2 7.1-.2h61.4C567.8 320 640 392.2 640 481.3c0 17-13.8 30.7-30.7 30.7zM432 256c-31 0-59-12.6-79.3-32.9C372.4 196.5 384 163.6 384 128c0-26.8-6.3-52.1-17.4-74.5C384.3 40.1 407.2 32 432 32c61.9 0 112 50.1 112 112s-50.1 112-112 112z"/></svg></span> Fastfood Owners
        </button>
        <button class="nav-item <?= $open_page === 'account' ? 'active' : '' ?>" onclick="showPage('account', this)">
            <span class="nav-icon"><svg class="nav-svg" viewBox="0 0 512 512"><path d="M399 384.2C376.9 345.8 335.4 320 288 320H224c-47.4 0-88.9 25.8-111 64.2c35.2 39.2 86.2 63.8 143 63.8s107.8-24.7 143-63.8zM0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256zm256 16a72 72 0 1 0 0-144 72 72 0 1 0 0 144z"/></svg></span> My Account
        </button>
        <button class="nav-item <?= $open_page === 'backup' ? 'active' : '' ?>" onclick="showPage('backup', this)">
            <span class="nav-icon"><svg class="nav-svg" viewBox="0 0 448 512"><path d="M448 80v48c0 44.2-100.3 80-224 80S0 172.2 0 128V80C0 35.8 100.3 0 224 0S448 35.8 448 80zM393.2 214.7c20.8-7.4 39.9-16.9 54.8-28.6V288c0 44.2-100.3 80-224 80S0 332.2 0 288V186.1c14.9 11.8 34 21.2 54.8 28.6C99.7 230.7 159.5 240 224 240s124.3-9.3 169.2-25.3zM0 346.1c14.9 11.8 34 21.2 54.8 28.6C99.7 390.7 159.5 400 224 400s124.3-9.3 169.2-25.3c20.8-7.4 39.9-16.9 54.8-28.6V432c0 44.2-100.3 80-224 80S0 476.2 0 432V346.1z"/></svg></span> Backup & Recovery
        </button>
        <button class="nav-item <?= $open_page === 'auditlog' ? 'active' : '' ?>" onclick="showPage('auditlog', this)">
            <span class="nav-icon"><svg class="nav-svg" viewBox="0 0 512 512"><path d="M152.1 38.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.4 4.9-10.6 7.8-17.2 7.9s-12.9-2.4-17.6-7L7 113C-2.3 103.6-2.3 88.4 7 79s24.6-9.4 33.9 0l22.1 22.1 55.1-61.2c8.9-9.9 24-10.7 33.9-1.8zm0 160c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.4 4.9-10.6 7.8-17.2 7.9s-12.9-2.4-17.6-7L7 273c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l22.1 22.1 55.1-61.2c8.9-9.9 24-10.7 33.9-1.8zM224 96c0-17.7 14.3-32 32-32H480c17.7 0 32 14.3 32 32s-14.3 32-32 32H256c-17.7 0-32-14.3-32-32zm0 160c0-17.7 14.3-32 32-32H480c17.7 0 32 14.3 32 32s-14.3 32-32 32H256c-17.7 0-32-14.3-32-32zM160 416c0-17.7 14.3-32 32-32H480c17.7 0 32 14.3 32 32s-14.3 32-32 32H192c-17.7 0-32-14.3-32-32zM48 368a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></svg></span> Audit Log
        </button>
        <button class="nav-item <?= $open_page === 'announcements' ? 'active' : '' ?>" onclick="showPage('announcements', this)">
            <span class="nav-icon"><svg class="nav-svg" viewBox="0 0 512 512"><path d="M480 32c0-12.9-7.8-24.6-19.8-29.6s-25.7-2.2-34.9 6.9L381.7 53c-48 48-113.1 75-181 75H128c-53 0-96 43-96 96v96c0 53 43 96 96 96h5.8c-2.4 12.7-3.8 25.8-3.8 39.2V512h96v-56.8c0-13.4-1.4-26.5-3.8-39.2H200.7c67.9 0 133 27 181 75l43.6 43.6c9.2 9.2 22.9 11.9 34.9 6.9S480 524.9 480 512V320c18.6-6.6 32-24.4 32-45.3v-85.4C512 168.4 498.6 150.6 480 128V32z"/></svg></span> Announcements
        </button>
    </nav>

    <div class="sidebar-bottom">
        <a class="logout-nav" href="../logout.php">
            <span class="nav-icon"><svg class="nav-svg" viewBox="0 0 512 512"><path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"/></svg></span> Logout
        </a>
    </div>
</aside>

<!-- ════ MAIN ════ -->
<div class="main">

    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle sidebar">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar-title" id="topbar-title">
                <?= $open_page === 'account' ? 'My Account' : ($open_page === 'owners' ? 'Fastfood Owners' : ($open_page === 'backup' ? 'Backup & Recovery' : ($open_page === 'auditlog' ? 'Audit Log' : ($open_page === 'announcements' ? 'Announcements & Maintenance' : 'Dashboard')))) ?>
            </div>
        </div>

        <!-- ── Enhanced Search ── -->
        <div class="topbar-search" id="search-container">
            <div class="search-inner" id="search-inner">
                <span class="search-icon"><svg class="search-svg" viewBox="0 0 512 512"><path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"/></svg></span>
                <input type="text" id="superadminSearch" placeholder="Search owners, pages…" autocomplete="off">
                <span class="search-kbd" id="search-kbd-hint">Ctrl K</span>
            </div>

            <!-- Dropdown -->
            <div id="search-dropdown">
                <!-- Filter chips -->
                <div class="sd-chips" id="sd-chips">
                    <span class="sd-chip active" data-filter="all" onclick="setChip('all')">All</span>
                    <span class="sd-chip" data-filter="online" onclick="setChip('online')">
                        <span class="sd-chip-dot" style="background:var(--green);"></span>Online
                    </span>
                    <span class="sd-chip" data-filter="offline" onclick="setChip('offline')">
                        <span class="sd-chip-dot" style="background:var(--text-muted);"></span>Offline
                    </span>
                </div>
                <div id="sd-body"></div>
                <div class="sd-footer" id="sd-footer">
                    <span id="sd-count"></span>
                    <span><kbd>↑↓</kbd> navigate &nbsp;<kbd>Enter</kbd> go &nbsp;<kbd>Esc</kbd> close</span>
                </div>
            </div>
        </div>

        <div class="topbar-right">
            <div class="admin-badge">
                <div class="admin-avatar">SA</div>
                <div class="admin-info">
                    <small>Logged in as</small>
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                </div>
            </div>
            <span class="role-chip"><svg class="nav-svg" viewBox="0 0 576 512" style="width:13px;height:13px;margin-right:4px;"><path d="M309 106c11.4-7.7 19-20.6 19-35c0-22.1-17.9-40-40-40s-40 17.9-40 40c0 14.4 7.6 27.3 19 35L209.7 220.6c-9.1 18.2-32.7 23.4-48.6 10.7L72 160c5-6.7 8-15 8-24C80 117.9 62.1 100 40 100S0 117.9 0 140s17.9 40 40 40c.2 0 .5 0 .7 0L86.4 426.4C93.3 468.4 129.5 500 172.8 500H403.2c43.3 0 79.5-31.6 86.4-73.6L535.3 180c.2 0 .5 0 .7 0c22.1 0 40-17.9 40-40s-17.9-40-40-40s-40 17.9-40 40c0 9 3 17.3 8 24l-89.1 71.3c-15.9 12.7-39.5 7.5-48.6-10.7L309 106z"/></svg> Superadmin</span>
        </div>
    </header>

    <div class="content">

        <!-- ═══ DASHBOARD ═══ -->
        <div class="page <?= $open_page === 'dashboard' ? 'active' : '' ?>" id="page-dashboard">
            <div class="page-heading">
                <h1>Welcome back, Your Grace! <svg class="nav-svg" viewBox="0 0 576 512" style="width:22px;height:22px;color:var(--rose);"><path d="M309 106c11.4-7.7 19-20.6 19-35c0-22.1-17.9-40-40-40s-40 17.9-40 40c0 14.4 7.6 27.3 19 35L209.7 220.6c-9.1 18.2-32.7 23.4-48.6 10.7L72 160c5-6.7 8-15 8-24C80 117.9 62.1 100 40 100S0 117.9 0 140s17.9 40 40 40c.2 0 .5 0 .7 0L86.4 426.4C93.3 468.4 129.5 500 172.8 500H403.2c43.3 0 79.5-31.6 86.4-73.6L535.3 180c.2 0 .5 0 .7 0c22.1 0 40-17.9 40-40s-17.9-40-40-40s-40 17.9-40 40c0 9 3 17.3 8 24l-89.1 71.3c-15.9 12.7-39.5 7.5-48.6-10.7L309 106z"/></svg></h1>
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
                            <tr class="empty-row"><td colspan="5">No fastfood owners registered yet.</td></tr>
                        <?php else: $i=1; foreach (array_slice($admins_full,0,5) as $a):
                            $on = $val->isAdminOnline($a['admin_id']);
                        ?>
                            <tr>
                                <td class="num-cell"><?= $i++ ?></td>
                                <td><div class="username-cell"><div class="user-dot"><?= strtoupper(substr($a['username'],0,1)) ?></div><?= htmlspecialchars($a['username']) ?></div></td>
                                <td class="email-cell"><?= htmlspecialchars($a['email']) ?></td>
                                <td><?= htmlspecialchars($a['fastfood_name']) ?></td>
                                <td><span class="status-badge <?= $on ? 'online' : 'offline' ?>"><?= $on ? 'Online' : 'Offline' ?></span></td>
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
                <table id="owners-table">
                    <thead><tr><th>No.</th><th>Username</th><th>Full Name</th><th>Email</th><th>Fastfood</th><th>Online</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($admins_full)): ?>
                            <tr class="empty-row"><td colspan="7">No fastfood owners registered yet.</td></tr>
                        <?php else: $i=1; foreach ($admins_full as $a):
                            $on = $val->isAdminOnline($a['admin_id']);
                        ?>
                            <tr data-owner-id="<?= $a['admin_id'] ?>">
                                <td class="num-cell"><?= $i++ ?></td>
                                <td><div class="username-cell"><div class="user-dot"><?= strtoupper(substr($a['username'],0,1)) ?></div><?= htmlspecialchars($a['username']) ?></div></td>
                                <td><?= htmlspecialchars($a['fullname'] ?? '—') ?></td>
                                <td class="email-cell"><?= htmlspecialchars($a['email']) ?></td>
                                <td><?= htmlspecialchars($a['fastfood_name']) ?></td>
                                <td><span class="status-badge <?= $on ? 'online' : 'offline' ?>"><?= $on ? 'Online' : 'Offline' ?></span></td>
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

        <!-- ═══ BACKUP & RECOVERY ═══ -->
        <div class="page <?= $open_page === 'backup' ? 'active' : '' ?>" id="page-backup">
            <div class="page-heading">
                <h1>Backup & Recovery 💾</h1>
                <p>Create, manage, and restore database backups to keep your data safe.</p>
            </div>

            <div id="br-alert" style="display:none;" class="alert"></div>

            <!-- ── Summary + About row ── -->
            <div class="br-top-grid" style="margin-bottom:20px">
                <div class="br-summary-card">
                    <div class="br-box-title">💾 Backup Summary</div>
                    <div class="br-info-row"><span class="br-ir-lbl">Total Backups</span><span class="br-ir-val" id="br-total-count">—</span></div>
                    <div class="br-info-row"><span class="br-ir-lbl">Total Size</span><span class="br-ir-val" id="br-total-size">—</span></div>
                    <div class="br-info-row"><span class="br-ir-lbl">Latest Backup</span><span class="br-ir-val" id="br-latest-date">—</span></div>
                </div>
                <div class="br-summary-card">
                    <div class="br-box-title">ℹ️ About Backups</div>
                    <p class="br-about-text">Full SQL dumps of the entire database are saved on the server. You can download any backup, restore from a saved backup, or upload a <code>.sql</code> file directly. Restoring will overwrite all current data — always back up first.</p>
                </div>
            </div>

            <!-- ── Action row ── -->
            <div class="br-action-row">
                <div class="br-create-box">
                    <div class="br-box-title">📦 Create New Backup</div>
                    <div class="br-box-body">
                        <input type="text" id="backup-label" placeholder="Label (e.g. before-update)" maxlength="60" class="br-input">
                        <button class="br-btn primary" id="btn-create-backup" onclick="createBackup()">💾 Create Backup</button>
                    </div>
                    <div class="br-box-note">A full SQL dump of all tables will be saved on the server.</div>
                </div>

                <div class="br-create-box">
                    <div class="br-box-title">📤 Restore from File</div>
                    <div class="br-box-body upload-row">
                        <label class="br-file-label" for="restore-file-input">
                            <span id="restore-file-name">Choose .sql file…</span>
                        </label>
                        <input type="file" id="restore-file-input" accept=".sql" style="display:none" onchange="previewFile(this)">
                        <button class="br-btn danger" id="btn-restore-upload" onclick="restoreFromUpload()" disabled>🔄 Restore Upload</button>
                    </div>
                    <div class="br-box-note">⚠️ Restoring will overwrite all current data.</div>
                </div>
            </div>

            <!-- ── Saved Backups table (card wrapper) ── -->
            <div class="br-section-card">
                <div class="br-section-hdr">
                    <div class="br-section-hdr-title">🗂️ Saved Backups (<span id="br-count-inline">…</span>)</div>
                    <button class="view-all-btn" onclick="loadBackupList()">↻ Refresh</button>
                </div>
                <div id="backup-table-wrap">
                    <div class="br-loading">Loading backups…</div>
                </div>
            </div>
        </div>

        <!-- ═══ AUDIT LOG ═══ -->
        <div class="page <?= $open_page === 'auditlog' ? 'active' : '' ?>" id="page-auditlog">
            <div class="page-heading">
                <h1>Audit Log 🗒️</h1>
                <p>A complete history of all superadmin actions performed on this system.</p>
            </div>

            <!-- Stats row -->
            <?php
                $total_entries   = count($audit_logs);
                $unique_actions  = count(array_unique(array_column($audit_logs, 'action')));
                $latest_date     = $audit_logs ? date('M d, Y', strtotime($audit_logs[0]['created_at'])) : '—';
                $failed_logins   = count(array_filter($audit_logs, fn($l) => $l['action'] === 'failed_login_attempt'));
                $owners_deleted  = count(array_filter($audit_logs, fn($l) => $l['action'] === 'owner_deleted'));
                $backups_created = count(array_filter($audit_logs, fn($l) => $l['action'] === 'backup_created'));
            ?>
            <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:26px;">

                <div style="flex:1; min-width:140px; background:#fff; border:1px solid #EAE0EE; border-top:3px solid #9B2C52; border-radius:14px; padding:20px 24px; position:relative; overflow:hidden;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#8C6E82; font-weight:600; margin-bottom:8px;">Total Entries</div>
                    <div style="font-size:38px; font-weight:800; color:#1A0A14; line-height:1;"><?= $total_entries ?></div>
                    <div style="position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:34px; opacity:0.08;">📋</div>
                </div>

                <div style="flex:1; min-width:140px; background:#fff; border:1px solid #EAE0EE; border-top:3px solid #7C3AED; border-radius:14px; padding:20px 24px; position:relative; overflow:hidden;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#8C6E82; font-weight:600; margin-bottom:8px;">Unique Actions</div>
                    <div style="font-size:38px; font-weight:800; color:#1A0A14; line-height:1;"><?= $unique_actions ?></div>
                    <div style="position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:34px; opacity:0.08;">⚡</div>
                </div>

                <div style="flex:1; min-width:140px; background:#fff; border:1px solid #EAE0EE; border-top:3px solid #DC2626; border-radius:14px; padding:20px 24px; position:relative; overflow:hidden;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#8C6E82; font-weight:600; margin-bottom:8px;">Failed Logins</div>
                    <div style="font-size:38px; font-weight:800; color:#1A0A14; line-height:1;"><?= $failed_logins ?></div>
                    <div style="position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:34px; opacity:0.08;">⚠️</div>
                </div>

                <div style="flex:1; min-width:140px; background:#fff; border:1px solid #EAE0EE; border-top:3px solid #EA580C; border-radius:14px; padding:20px 24px; position:relative; overflow:hidden;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#8C6E82; font-weight:600; margin-bottom:8px;">Owners Deleted</div>
                    <div style="font-size:38px; font-weight:800; color:#1A0A14; line-height:1;"><?= $owners_deleted ?></div>
                    <div style="position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:34px; opacity:0.08;">🗑️</div>
                </div>

                <div style="flex:1; min-width:140px; background:#fff; border:1px solid #EAE0EE; border-top:3px solid #16A34A; border-radius:14px; padding:20px 24px; position:relative; overflow:hidden;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#8C6E82; font-weight:600; margin-bottom:8px;">Backups Created</div>
                    <div style="font-size:38px; font-weight:800; color:#1A0A14; line-height:1;"><?= $backups_created ?></div>
                    <div style="position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:34px; opacity:0.08;">💾</div>
                </div>

                <div style="flex:1; min-width:140px; background:#fff; border:1px solid #EAE0EE; border-top:3px solid #6B7280; border-radius:14px; padding:20px 24px; position:relative; overflow:hidden;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#8C6E82; font-weight:600; margin-bottom:8px;">Latest Entry</div>
                    <div style="font-size:18px; font-weight:800; color:#1A0A14; line-height:1.3;"><?= $latest_date ?></div>
                    <div style="position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:34px; opacity:0.08;">🕐</div>
                </div>

            </div>

            <!-- Filter + Search bar -->
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
                <div class="search-inner" style="max-width:360px; width:100%;">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="al-search" placeholder="Search by action, user, target…" oninput="filterAuditLog()" style="border:none; background:none; outline:none; font-size:13px; font-family:'Poppins',sans-serif; padding:9px 0; width:100%;">
                </div>
                <select id="al-filter-action" onchange="filterAuditLog()" style="padding:9px 14px; border:1.5px solid var(--border,#EAE0EE); border-radius:24px; font-size:13px; font-family:'Poppins',sans-serif; background:var(--bg,#F8F4FA); color:var(--text-h,#1A0A14); outline:none; cursor:pointer;">
                    <option value="">All Actions</option>
                    <?php
                        $actions = array_unique(array_column($audit_logs, 'action'));
                        sort($actions);
                        foreach ($actions as $act): ?>
                        <option value="<?= htmlspecialchars($act) ?>"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($act))) ?></option>
                    <?php endforeach; ?>
                </select>
                <button onclick="clearAuditFilters()" style="padding:9px 18px; border:1.5px solid var(--border,#EAE0EE); border-radius:24px; font-size:12px; font-weight:600; background:#fff; color:var(--text-muted,#8C6E82); cursor:pointer; font-family:'Poppins',sans-serif;">✕ Clear</button>
            </div>

            <!-- Log table -->
            <div class="al-table-wrap">
                <?php if (empty($audit_logs)): ?>
                    <div class="al-empty">
                        <div class="al-empty-icon">📋</div>
                        <div class="al-empty-title">No audit entries yet</div>
                        <div class="al-empty-sub">Actions you take (editing owners, updating credentials, etc.) will be recorded here.<br>Make sure you have run the <code>audit_log_migration.sql</code> on your database.</div>
                    </div>
                <?php else: ?>
                <table class="al-table" id="al-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date &amp; Time</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Detail</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit_logs as $i => $log):
                            $action_slug = htmlspecialchars($log['action']);
                            $badge_class = 'al-badge';
                            if (str_contains($log['action'], 'deleted'))        $badge_class .= ' al-badge-danger';
                            elseif (str_contains($log['action'], 'updated') || str_contains($log['action'], 'credentials')) $badge_class .= ' al-badge-warning';
                            elseif (str_contains($log['action'], 'created'))    $badge_class .= ' al-badge-success';
                            else $badge_class .= ' al-badge-info';
                        ?>
                        <tr class="al-row" data-action="<?= $action_slug ?>" data-text="<?= htmlspecialchars(strtolower($log['actor_name'] . ' ' . $log['action'] . ' ' . ($log['target_label'] ?? '') . ' ' . ($log['detail'] ?? ''))) ?>">
                            <td class="al-id"><?= htmlspecialchars($log['log_id']) ?></td>
                            <td class="al-date" title="<?= htmlspecialchars($log['created_at']) ?>">
                                <?= htmlspecialchars(date('M d, Y', strtotime($log['created_at']))) ?><br>
                                <span class="al-time"><?= htmlspecialchars(date('h:i:s A', strtotime($log['created_at']))) ?></span>
                            </td>
                            <td class="al-actor">
                                <span class="al-actor-chip"><?= htmlspecialchars($log['actor_name']) ?></span>
                            </td>
                            <td>
                                <span class="<?= $badge_class ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', ucwords($log['action'], '_'))) ?>
                                </span>
                            </td>
                            <td class="al-target">
                                <?php if ($log['target_label']): ?>
                                    <span class="al-target-type"><?= htmlspecialchars(ucfirst($log['target_type'] ?? '')) ?></span>
                                    <?= htmlspecialchars($log['target_label']) ?>
                                <?php else: ?>
                                    <span class="al-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="al-detail">
                                <?= htmlspecialchars($log['detail'] ?? '—') ?>
                            </td>
                            <td class="al-ip">
                                <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div class="al-footer-note">Showing the most recent 200 entries. Older entries are stored in the database.</div>
        </div>


        <!-- ═══ ANNOUNCEMENTS & MAINTENANCE ═══ -->
        <div class="page <?= $open_page === 'announcements' ? 'active' : '' ?>" id="page-announcements">
            <div class="page-heading">
                <h1>📢 Announcements &amp; Maintenance</h1>
                <p>Control system-wide announcements and maintenance mode for all owners.</p>
            </div>

            <!-- STATUS CARDS ROW -->
            <div class="stats-row" style="margin-bottom:24px;">
                <div class="stat-card" id="ann-status-card" style="cursor:default;">
                    <div class="stat-label">Announcement</div>
                    <div class="stat-value" id="ann-status-badge" style="font-size:15px;font-weight:700;">Loading…</div>
                    <div class="stat-icon">📢</div>
                </div>
                <div class="stat-card" id="maint-status-card" style="cursor:default;">
                    <div class="stat-label">Maintenance Mode</div>
                    <div class="stat-value" id="maint-status-badge" style="font-size:15px;font-weight:700;">Loading…</div>
                    <div class="stat-icon">🔧</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

                <!-- ── ANNOUNCEMENT CARD ── -->
                <div class="br-section-card">
                    <div class="br-section-hdr">
                        <div class="br-section-hdr-title">📢 Global Announcement</div>
                        <label class="am-toggle-wrap" title="Enable / Disable">
                            <input type="checkbox" id="ann-toggle" onchange="quickToggle('announcement')">
                            <span class="am-slider"></span>
                        </label>
                    </div>
                    <div style="padding:20px 22px;">
                        <div id="ann-toast" class="am-toast" style="display:none;"></div>

                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="font-size:12.5px;font-weight:600;color:var(--text-h);display:block;margin-bottom:6px;">Banner Type</label>
                            <select id="ann-type" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;background:var(--bg);color:var(--text-h);outline:none;">
                                <option value="info">ℹ️ Info (Blue)</option>
                                <option value="warning">⚠️ Warning (Orange)</option>
                                <option value="success">✅ Success (Green)</option>
                                <option value="danger">🚨 Danger (Red)</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom:18px;">
                            <label style="font-size:12.5px;font-weight:600;color:var(--text-h);display:block;margin-bottom:6px;">Message</label>
                            <textarea id="ann-message" rows="4" placeholder="Enter the announcement message visible to all owners…" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;background:var(--bg);color:var(--text-h);outline:none;resize:vertical;line-height:1.5;"></textarea>
                        </div>

                        <!-- Live Preview -->
                        <div id="ann-preview-wrap" style="margin-bottom:18px; display:none;">
                            <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:6px;letter-spacing:0.5px;text-transform:uppercase;">Preview</div>
                            <div id="ann-preview" class="am-banner am-banner-info"></div>
                        </div>

                        <button onclick="saveAnnouncement()" class="br-btn primary" style="width:100%;padding:11px;">💾 Save Announcement</button>
                    </div>
                </div>

                <!-- ── MAINTENANCE CARD ── -->
                <div class="br-section-card">
                    <div class="br-section-hdr">
                        <div class="br-section-hdr-title">🔧 Maintenance Mode</div>
                        <label class="am-toggle-wrap" title="Enable / Disable">
                            <input type="checkbox" id="maint-toggle" onchange="quickToggle('maintenance')">
                            <span class="am-slider"></span>
                        </label>
                    </div>
                    <div style="padding:20px 22px;">
                        <div id="maint-toast" class="am-toast" style="display:none;"></div>

                        <div class="am-warning-note">
                            ⚠️ <strong>Caution:</strong> Enabling maintenance mode will block all owner logins and redirect users to the maintenance page. Superadmin access is unaffected.
                        </div>

                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="font-size:12.5px;font-weight:600;color:var(--text-h);display:block;margin-bottom:6px;">Maintenance Message</label>
                            <textarea id="maint-message" rows="4" placeholder="Message shown on the maintenance page…" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;background:var(--bg);color:var(--text-h);outline:none;resize:vertical;line-height:1.5;"></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom:18px;">
                            <label style="font-size:12.5px;font-weight:600;color:var(--text-h);display:block;margin-bottom:6px;">Estimated End Time <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                            <input type="datetime-local" id="maint-end-time" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;background:var(--bg);color:var(--text-h);outline:none;">
                        </div>

                        <button onclick="saveMaintenance()" class="br-btn primary" style="width:100%;padding:11px;">💾 Save Maintenance Settings</button>
                    </div>
                </div>

            </div><!-- /grid -->
        </div><!-- /page-announcements -->

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
/* ══════════════════════════════════════════════
   OWNER DATA (from PHP, used by enhanced search)
══════════════════════════════════════════════ */
const OWNERS_DATA = <?= json_encode($owners_for_search, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const BACKUP_URL = 'backup_handler.php'; // this was changed

/* ══════════════════════════════════════════════
   PAGE SWITCH
══════════════════════════════════════════════ */
function showPage(id, btn) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('page-' + id).classList.add('active');
    if (btn) btn.classList.add('active');
    const titles = { dashboard:'Dashboard', owners:'Fastfood Owners', account:'My Account', backup:'Backup & Recovery' };
    document.getElementById('topbar-title').textContent = titles[id] || id;
    if (id === 'backup') loadBackupList();
}

/* ══════════════════════════════════════════════
   MODAL HELPERS
══════════════════════════════════════════════ */
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('show'); });
});

/* ══════════════════════════════════════════════
   DELETE MODAL
══════════════════════════════════════════════ */
let deleteId = null;
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

/* ══════════════════════════════════════════════
   ACCOUNT INLINE EDIT
══════════════════════════════════════════════ */
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

/* ══════════════════════════════════════════════
   ENHANCED SEARCH
══════════════════════════════════════════════ */
(function() {
    const searchInput = document.getElementById('superadminSearch');
    const searchInner = document.getElementById('search-inner');
    const dropdown    = document.getElementById('search-dropdown');
    const sdBody      = document.getElementById('sd-body');
    const sdCount     = document.getElementById('sd-count');
    const kbdHint     = document.getElementById('search-kbd-hint');

    // Pages registry
    const PAGES = [
        { id: 'dashboard', icon: '⚡', name: 'Dashboard',         desc: 'System overview & stats' },
        { id: 'owners',    icon: '👥', name: 'Fastfood Owners',   desc: 'Manage owner accounts' },
        { id: 'account',   icon: '🔑', name: 'My Account',        desc: 'Update credentials' },
        { id: 'backup',    icon: '🗄️', name: 'Backup & Recovery', desc: 'Database backups' },
    ];

    let activeChip   = 'all';   // 'all' | 'online' | 'offline'
    let focusedIndex = -1;      // keyboard nav
    let allItems     = [];      // flat list of rendered items for keyboard nav

    /* ── Chip filter ── */
    window.setChip = function(chip) {
        activeChip = chip;
        document.querySelectorAll('.sd-chip').forEach(c => c.classList.toggle('active', c.dataset.filter === chip));
        renderDropdown(searchInput.value);
    };

    /* ── Highlight matching text ── */
    function highlight(text, q) {
        if (!q) return escHtml(text);
        const re = new RegExp('(' + escRe(q) + ')', 'gi');
        return escHtml(text).replace(re, '<mark class="sh">$1</mark>');
    }
    function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function escRe(s)   { return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }

    /* ── Main render ── */
    function renderDropdown(raw) {
        const q = raw.trim().toLowerCase();
        focusedIndex = -1;
        allItems = [];
        let html = '';

        // ── Page results (only when no chip filter forcing owner-only) ──
        if (activeChip === 'all') {
            const matchedPages = PAGES.filter(p =>
                !q || p.name.toLowerCase().includes(q) || p.desc.toLowerCase().includes(q)
            );
            if (matchedPages.length) {
                html += `<div class="sd-section-label">Pages</div>`;
                matchedPages.forEach(p => {
                    html += `<div class="sd-page-item" data-action="page" data-page="${p.id}" tabindex="-1">
                        <div class="sd-page-icon">${p.icon}</div>
                        <div>
                            <div class="sd-page-name">${highlight(p.name, q)}</div>
                            <div class="sd-page-desc">${highlight(p.desc, q)}</div>
                        </div>
                    </div>`;
                    allItems.push({ type: 'page', id: p.id });
                });
            }
        }

        // ── Owner results ──
        const matchedOwners = OWNERS_DATA.filter(o => {
            const statusOk = activeChip === 'all'
                ? true
                : activeChip === 'online'  ? o.online
                : /* offline */              !o.online;
            if (!statusOk) return false;
            if (!q) return true;
            return [o.username, o.fullname, o.email, o.fastfood]
                .some(v => v && v.toLowerCase().includes(q));
        });

        const MAX_SHOWN = 8;
        const shown = matchedOwners.slice(0, MAX_SHOWN);

        if (shown.length) {
            const label = activeChip === 'online'  ? '🟢 Online Owners'
                        : activeChip === 'offline' ? '⚫ Offline Owners'
                        : 'Owners';
            const countSuffix = matchedOwners.length > MAX_SHOWN
                ? ` <span style="font-weight:400">(showing ${MAX_SHOWN} of ${matchedOwners.length})</span>`
                : ` (${shown.length})`;
            html += `<hr class="sd-divider"><div class="sd-section-label">${label}${countSuffix}</div>`;

            shown.forEach(o => {
                const initial   = (o.username || '?')[0].toUpperCase();
                const dotClass  = o.online ? 'online' : '';
                const pip       = o.online ? '<span class="online-pip"></span>' : '';
                const badgeClass = o.online ? 'online' : 'offline';
                const badgeText  = o.online ? 'Online' : 'Offline';
                html += `<div class="sd-owner-item" data-action="owner" data-id="${o.id}" tabindex="-1">
                    <div class="sd-owner-dot ${dotClass}">${initial}${pip}</div>
                    <div class="sd-owner-meta">
                        <div class="sd-owner-name">${highlight(o.username, q)}${o.fullname ? ' <span style="font-weight:400;color:var(--text-muted);">— ' + highlight(o.fullname, q) + '</span>' : ''}</div>
                        <div class="sd-owner-sub">${highlight(o.fastfood, q)} &nbsp;·&nbsp; ${highlight(o.email, q)}</div>
                    </div>
                    <span class="sd-status-badge ${badgeClass}">${badgeText}</span>
                </div>`;
                allItems.push({ type: 'owner', id: o.id });
            });

            if (matchedOwners.length > MAX_SHOWN) {
                html += `<div style="padding:8px 14px;font-size:11px;color:var(--text-muted);">
                    Refine your search to see more results…</div>`;
            }
        }

        // ── Empty state ──
        if (!html) {
            html = `<div class="sd-empty">No results found for "<strong>${escHtml(raw)}</strong>"</div>`;
        }

        sdBody.innerHTML = html;

        // Count line
        const ownerWord = matchedOwners.length === 1 ? 'owner' : 'owners';
        sdCount.textContent = q
            ? `${matchedOwners.length} ${ownerWord} matched`
            : `${OWNERS_DATA.length} ${ownerWord} total`;

        // Attach click handlers to newly rendered items
        sdBody.querySelectorAll('.sd-page-item, .sd-owner-item').forEach((el, idx) => {
            el.addEventListener('click', () => activateItem(allItems[idx]));
        });
    }

    /* ── Activate an item (navigate to it) ── */
    function activateItem(item) {
        if (!item) return;
        closeDropdown();
        if (item.type === 'page') {
            const navBtns = document.querySelectorAll('.nav-item');
            const map = { dashboard:0, owners:1, account:2, backup:3, auditlog:4 };
            showPage(item.id, navBtns[map[item.id]]);
        } else if (item.type === 'owner') {
            // Navigate to owners, then scroll & highlight
            const navBtns = document.querySelectorAll('.nav-item');
            showPage('owners', navBtns[1]);
            requestAnimationFrame(() => {
                const row = document.querySelector(`#owners-table tr[data-owner-id="${item.id}"]`);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('row-highlight');
                    row.addEventListener('animationend', () => row.classList.remove('row-highlight'), { once: true });
                }
            });
        }
    }

    /* ── Keyboard navigation ── */
    function moveFocus(dir) {
        const items = sdBody.querySelectorAll('.sd-page-item, .sd-owner-item');
        if (!items.length) return;
        if (focusedIndex >= 0) items[focusedIndex].classList.remove('focused');
        focusedIndex = Math.max(0, Math.min(items.length - 1, focusedIndex + dir));
        items[focusedIndex].classList.add('focused');
        items[focusedIndex].scrollIntoView({ block: 'nearest' });
    }

    searchInput.addEventListener('keydown', e => {
        if (!dropdown.classList.contains('open')) return;
        if (e.key === 'ArrowDown')  { e.preventDefault(); moveFocus(+1); }
        if (e.key === 'ArrowUp')    { e.preventDefault(); moveFocus(-1); }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (focusedIndex >= 0 && allItems[focusedIndex]) activateItem(allItems[focusedIndex]);
        }
        if (e.key === 'Escape') closeDropdown();
    });

    /* ── Open / close dropdown ── */
    function openDropdown() {
        renderDropdown(searchInput.value);
        dropdown.classList.add('open');
        searchInner.classList.add('focused');
        kbdHint.style.display = 'none';
    }

    function closeDropdown() {
        dropdown.classList.remove('open');
        searchInner.classList.remove('focused');
        kbdHint.style.display = '';
        focusedIndex = -1;
    }

    searchInput.addEventListener('focus', openDropdown);
    searchInput.addEventListener('input', () => {
        if (!dropdown.classList.contains('open')) dropdown.classList.add('open');
        renderDropdown(searchInput.value);
    });

    // Close when clicking outside
    document.addEventListener('click', e => {
        if (!document.getElementById('search-container').contains(e.target)) closeDropdown();
    });

    /* ── Ctrl+K shortcut ── */
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput.focus();
        }
    });
})();

/* ══════════════════════════════════════════════
   SIDEBAR TOGGLE
══════════════════════════════════════════════ */
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('collapsed');
    document.querySelector('.main').classList.toggle('sidebar-collapsed');
}

/* ══════════════════════════════════════════════
   DOMContentLoaded
══════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    // Live profile preview
    const usernameInput = document.getElementById('edit-username');
    if (usernameInput) {
        usernameInput.addEventListener('input', () => {
            const val = usernameInput.value || 'superadmin';
            document.getElementById('profile-display-name').textContent   = val;
            document.getElementById('profile-display-handle').textContent = '@' + val;
            document.getElementById('profile-avatar-initials').textContent = val.substring(0,2).toUpperCase();
        });
    }

    // Auto-open correct page + modal from URL
    const params   = new URLSearchParams(window.location.search);
    const urlPage  = params.get('page');
    const urlModal = params.get('modal');
    const urlId    = params.get('id');

    if (urlPage) {
        const btns = document.querySelectorAll('.nav-item');
        const map  = { dashboard:0, owners:1, account:2, backup:3 };
        if (map[urlPage] !== undefined) showPage(urlPage, btns[map[urlPage]]);
    }

    if (urlModal === 'edit' && urlId) {
        const editBtn = document.querySelector(`.open-edit-modal[data-id="${urlId}"]`);
        if (editBtn) editBtn.click();
    }

    if (urlPage === 'backup') loadBackupList();
});

/* ══════════════════════════════════════════════
   BACKUP & RECOVERY
══════════════════════════════════════════════ */
function brAlert(msg, type = 'success') {
    const el = document.getElementById('br-alert');
    el.className = 'alert ' + type;
    el.innerHTML = (type === 'success' ? '✅ ' : '❌ ') + msg;
    el.style.display = 'block';
    clearTimeout(brAlert._t);
    brAlert._t = setTimeout(() => el.style.display = 'none', 5000);
}

function formatBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(2) + ' MB';
}
function formatDate(s) {
    const d = new Date(s.replace(' ', 'T'));
    return d.toLocaleString();
}

async function createBackup() {
    const label = document.getElementById('backup-label').value.trim();
    const btn   = document.getElementById('btn-create-backup');
    btn.disabled = true; btn.textContent = '⏳ Creating…';
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('label', label || 'manual');
    try {
        const res  = await fetch(BACKUP_URL, { method:'POST', body:fd });
        const data = await res.json();
        brAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) { document.getElementById('backup-label').value = ''; loadBackupList(); }
    } catch(e) { brAlert('Network error: ' + e.message, 'error'); }
    btn.disabled = false; btn.textContent = '💾 Create Backup';
}

async function loadBackupList() {
    const wrap = document.getElementById('backup-table-wrap');
    wrap.innerHTML = '<div class="br-loading">Loading backups…</div>';
    try {
        const res  = await fetch(BACKUP_URL + '?action=list');
        const data = await res.json();
        if (!data.success) { wrap.innerHTML = '<div class="br-empty" style="border:none;">Failed to load backups.</div>'; return; }
        const backups = data.backups;

        // ── Update summary cards ──
        const totalSize = backups.reduce((s, b) => s + (b.size || 0), 0);
        document.getElementById('br-total-count').textContent = backups.length;
        document.getElementById('br-total-size').textContent  = formatBytes(totalSize);
        document.getElementById('br-latest-date').textContent = backups.length ? formatDate(backups[0].created_at) : 'Never';
        document.getElementById('br-count-inline').textContent = backups.length;

        if (!backups.length) { wrap.innerHTML = '<div class="br-empty" style="border:none;border-radius:0;">No backups yet. Create one above!</div>'; return; }

        let html = `<table class="br-table"><thead><tr>
            <th>Label</th><th>Filename</th><th>Created At</th><th>Size</th><th>Tables</th><th>Created By</th><th>Actions</th>
        </tr></thead><tbody>`;
        backups.forEach(b => {
            const rid = 'ren_' + b.filename.replace(/[^a-z0-9]/gi, '_');
            const baseName = b.filename.replace(/\.sql$/i, '');
            // Store data on window so onclick strings don't need quoting inside HTML attrs
            window['_br_' + rid] = { filename: b.filename, label: b.label };
            html += `<tr id="row_${rid}">
                <td><strong>${escHtmlBr(b.label)}</strong></td>
                <td>
                    <div class="br-label-wrap" id="wrap_${rid}">
                        <span class="br-filename br-label-text" id="txt_${rid}">${escHtmlBr(b.filename)}</span>
                        <input class="br-label-input" id="inp_${rid}" value="${escHtmlBr(baseName)}" maxlength="80" placeholder="new_filename">
                        <button class="br-rename-btn" id="editbtn_${rid}" title="Rename file" onclick="startRename('${rid}')">✏️</button>
                        <button class="br-save-btn"   id="savebtn_${rid}"   onclick="saveRename('${rid}')">Save</button>
                        <button class="br-cancel-btn" id="cancelbtn_${rid}" onclick="cancelRename('${rid}')">Cancel</button>
                    </div>
                </td>
                <td style="white-space:nowrap;color:var(--text-muted)">${formatDate(b.created_at)}</td>
                <td>${formatBytes(b.size)}</td>
                <td>${b.tables}</td>
                <td>${escHtmlBr(b.created_by)}</td>
                <td class="br-actions" id="actions_${rid}">
                    <a href="${BACKUP_URL}?action=download&file=${encodeURIComponent(b.filename)}" class="br-btn sm success" download>⬇ Download</a>
                    <button class="br-btn sm primary" onclick="confirmRestore('${rid}')">🔄 Restore</button>
                    <button class="br-btn sm danger"  onclick="confirmDeleteBackup('${rid}')">🗑 Delete</button>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        wrap.innerHTML = html;
    } catch(e) { wrap.innerHTML = '<div class="br-empty" style="border:none;border-radius:0;">Error loading backups.</div>'; }
}

function escHtmlBr(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function startRename(rid) {
    document.getElementById('txt_' + rid).style.display       = 'none';
    document.getElementById('editbtn_' + rid).style.display   = 'none';
    document.getElementById('inp_' + rid).style.display       = 'inline-block';
    document.getElementById('savebtn_' + rid).style.display   = 'inline-block';
    document.getElementById('cancelbtn_' + rid).style.display = 'inline-block';
    const inp = document.getElementById('inp_' + rid);
    inp.focus(); inp.select();
    inp.onkeydown = function(e) {
        if (e.key === 'Enter')  { e.preventDefault(); saveRename(rid); }
        if (e.key === 'Escape') { cancelRename(rid); }
    };
}

function cancelRename(rid) {
    document.getElementById('txt_' + rid).style.display       = 'inline';
    document.getElementById('editbtn_' + rid).style.display   = 'inline-block';
    document.getElementById('inp_' + rid).style.display       = 'none';
    document.getElementById('savebtn_' + rid).style.display   = 'none';
    document.getElementById('cancelbtn_' + rid).style.display = 'none';
}

async function saveRename(rid) {
    const d = window['_br_' + rid];
    if (!d) return;
    const inp     = document.getElementById('inp_' + rid);
    const newName = inp.value.trim();
    if (!newName) { brAlert('Filename cannot be empty.', 'error'); return; }

    const savebtn = document.getElementById('savebtn_' + rid);
    savebtn.textContent = '…'; savebtn.disabled = true;

    const fd = new FormData();
    fd.append('action',   'rename');
    fd.append('file',     d.filename);
    fd.append('new_name', newName);
    try {
        const res  = await fetch(BACKUP_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // Update data store with new filename/label
            window['_br_' + rid] = { filename: data.new_filename, label: data.new_label };
            // Update displayed filename
            document.getElementById('txt_' + rid).textContent = data.new_filename;
            inp.value = data.new_filename.replace(/\.sql$/i, '');
            // Update download link
            const actionsCell = document.getElementById('actions_' + rid);
            if (actionsCell) {
                actionsCell.querySelector('a').href = `${BACKUP_URL}?action=download&file=${encodeURIComponent(data.new_filename)}`;
            }
            brAlert(data.message, 'success');
        } else {
            brAlert(data.message, 'error');
        }
    } catch(e) { brAlert('Network error: ' + e.message, 'error'); }

    savebtn.textContent = 'Save'; savebtn.disabled = false;
    cancelRename(rid);
}

function previewFile(input) {
    const name = input.files[0] ? input.files[0].name : 'Choose .sql file…';
    document.getElementById('restore-file-name').textContent = name;
    document.getElementById('btn-restore-upload').disabled = !input.files[0];
}

async function restoreFromUpload() {
    const fileInput = document.getElementById('restore-file-input');
    if (!fileInput.files[0]) return;
    if (!confirm('⚠️ Restoring will OVERWRITE all current data. This cannot be undone.\n\nAre you absolutely sure?')) return;
    const btn = document.getElementById('btn-restore-upload');
    btn.disabled = true; btn.textContent = '⏳ Restoring…';
    const fd = new FormData(); fd.append('action', 'restore'); fd.append('backup_file', fileInput.files[0]);
    try {
        const res  = await fetch(BACKUP_URL, { method:'POST', body:fd });
        const data = await res.json();
        brAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) { fileInput.value = ''; document.getElementById('restore-file-name').textContent = 'Choose .sql file…'; }
    } catch(e) { brAlert('Network error: ' + e.message, 'error'); }
    btn.disabled = false; btn.textContent = '🔄 Restore Upload';
}

function confirmRestore(rid) {
    const d = window['_br_' + rid];
    if (!d) return;
    if (!confirm(`⚠️ Restore from backup "${d.label}"?\n\nThis will OVERWRITE all current data. This cannot be undone.`)) return;
    restoreFromServer(d.filename);
}
async function restoreFromServer(filename) {
    const fd = new FormData(); fd.append('action', 'restore'); fd.append('file', filename);
    try {
        const res  = await fetch(BACKUP_URL, { method:'POST', body:fd });
        const data = await res.json();
        brAlert(data.message, data.success ? 'success' : 'error');
    } catch(e) { brAlert('Network error: ' + e.message, 'error'); }
}

function confirmDeleteBackup(rid) {
    const d = window['_br_' + rid];
    if (!d) return;
    if (!confirm(`Delete backup "${d.label}"? This cannot be undone.`)) return;
    deleteBackup(rid, d.filename);
}
async function deleteBackup(rid, filename) {
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('file', filename);
    try {
        const res  = await fetch(BACKUP_URL, { method:'POST', body:fd });
        const data = await res.json();
        brAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) { delete window['_br_' + rid]; loadBackupList(); }
    } catch(e) { brAlert('Network error: ' + e.message, 'error'); }
}

/* ── AUDIT LOG FILTER ── */
function filterAuditLog() {
    const search = document.getElementById('al-search').value.toLowerCase();
    const action = document.getElementById('al-filter-action').value;
    document.querySelectorAll('#al-table .al-row').forEach(row => {
        const matchText   = !search || row.dataset.text.includes(search);
        const matchAction = !action || row.dataset.action === action;
        row.style.display = (matchText && matchAction) ? '' : 'none';
    });
}
function clearAuditFilters() {
    document.getElementById('al-search').value = '';
    document.getElementById('al-filter-action').value = '';
    filterAuditLog();
}

/* ══════════════════════════════════════════════
   ANNOUNCEMENTS & MAINTENANCE
══════════════════════════════════════════════ */
const AM_URL = 'announcement_handler.php';

async function loadAnnouncementSettings() {
    try {
        const res  = await fetch(AM_URL, { method:'POST', body: new URLSearchParams({ action:'get_settings' }) });
        const data = await res.json();
        if (!data.success) return;

        const a = data.announcement;
        const m = data.maintenance;

        // Announcement
        document.getElementById('ann-toggle').checked  = a.enabled === '1';
        document.getElementById('ann-type').value      = a.type    || 'info';
        document.getElementById('ann-message').value   = a.message || '';
        updateAnnStatusBadge(a.enabled === '1');
        updateAnnPreview();

        // Maintenance
        document.getElementById('maint-toggle').checked  = m.enabled === '1';
        document.getElementById('maint-message').value   = m.message || '';
        document.getElementById('maint-end-time').value  = m.end_time ? m.end_time.replace(' ','T').substring(0,16) : '';
        updateMaintStatusBadge(m.enabled === '1');
    } catch(e) { console.error('AM load error', e); }
}

function updateAnnStatusBadge(on) {
    const el = document.getElementById('ann-status-badge');
    if (!el) return;
    el.textContent = on ? '🟢 Active' : '⭕ Inactive';
    el.style.color = on ? '#16a34a' : '#9ca3af';
}
function updateMaintStatusBadge(on) {
    const el = document.getElementById('maint-status-badge');
    if (!el) return;
    el.textContent = on ? '🔴 Enabled' : '⭕ Disabled';
    el.style.color = on ? '#dc2626' : '#9ca3af';
}

function updateAnnPreview() {
    const msg  = document.getElementById('ann-message')?.value.trim();
    const type = document.getElementById('ann-type')?.value || 'info';
    const wrap = document.getElementById('ann-preview-wrap');
    const prev = document.getElementById('ann-preview');
    if (!wrap || !prev) return;
    if (!msg) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';
    prev.className = `am-banner am-banner-${type}`;
    const icons = { info:'ℹ️', warning:'⚠️', success:'✅', danger:'🚨' };
    prev.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${msg.replace(/</g,'&lt;')}</span>`;
}

document.addEventListener('DOMContentLoaded', () => {
    const annMsg  = document.getElementById('ann-message');
    const annType = document.getElementById('ann-type');
    if (annMsg)  annMsg.addEventListener('input', updateAnnPreview);
    if (annType) annType.addEventListener('change', updateAnnPreview);
    loadAnnouncementSettings();
});

function showAmToast(id, msg, type) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent  = msg;
    el.className    = `am-toast ${type}`;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 3500);
}

async function saveAnnouncement() {
    const enabled = document.getElementById('ann-toggle').checked ? '1' : '0';
    const message = document.getElementById('ann-message').value.trim();
    const type    = document.getElementById('ann-type').value;
    const fd = new FormData();
    fd.append('action','save_announcement');
    fd.append('enabled', enabled);
    fd.append('message', message);
    fd.append('type', type);
    try {
        const res  = await fetch(AM_URL, { method:'POST', body:fd });
        const data = await res.json();
        showAmToast('ann-toast', data.message, data.success ? 'success' : 'error');
        if (data.success) updateAnnStatusBadge(enabled === '1');
    } catch(e) { showAmToast('ann-toast', 'Network error.', 'error'); }
}

async function saveMaintenance() {
    const enabled  = document.getElementById('maint-toggle').checked ? '1' : '0';
    const message  = document.getElementById('maint-message').value.trim();
    const end_time = document.getElementById('maint-end-time').value;
    const fd = new FormData();
    fd.append('action','save_maintenance');
    fd.append('enabled', enabled);
    fd.append('message', message);
    fd.append('end_time', end_time ? end_time.replace('T',' ') : '');

    if (enabled === '1' && !confirm('⚠️ Enabling maintenance mode will block all owner logins immediately.\n\nContinue?')) {
        document.getElementById('maint-toggle').checked = false;
        return;
    }

    try {
        const res  = await fetch(AM_URL, { method:'POST', body:fd });
        const data = await res.json();
        showAmToast('maint-toast', data.message, data.success ? 'success' : 'error');
        if (data.success) updateMaintStatusBadge(enabled === '1');
    } catch(e) { showAmToast('maint-toast', 'Network error.', 'error'); }
}

async function quickToggle(which) {
    const action = which === 'announcement' ? 'toggle_announcement' : 'toggle_maintenance';
    const toggleId = which === 'announcement' ? 'ann-toggle' : 'maint-toggle';
    const checkbox = document.getElementById(toggleId);

    if (which === 'maintenance' && checkbox.checked) {
        if (!confirm('⚠️ Enabling maintenance mode will block all owner logins immediately.\n\nContinue?')) {
            checkbox.checked = false;
            return;
        }
    }

    const fd = new FormData();
    fd.append('action', action);
    try {
        const res  = await fetch(AM_URL, { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            const on = data.enabled === '1';
            checkbox.checked = on;
            if (which === 'announcement') updateAnnStatusBadge(on);
            else updateMaintStatusBadge(on);
        }
    } catch(e) { console.error('Toggle error', e); }
}
</script>
</body>
</html>