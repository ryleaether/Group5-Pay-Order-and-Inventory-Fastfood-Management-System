<?php
session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/helpers/admindashboard_helpers.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$db       = new Database();
$conn     = $db->connect();

// Always clear any previous gate unlock — force re-auth every visit
unset($_SESSION['staff_gate_unlocked']);

// Handle AJAX login check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    header('Content-Type: application/json');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        echo json_encode(['success' => false, 'message' => 'Please enter username and password.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT admin_id, password FROM admins WHERE (username = :u OR email = :u2) AND admin_id = :id LIMIT 1");
        $stmt->execute([':u' => $username, ':u2' => $username, ':id' => $admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['staff_gate_unlocked'] = time();
            echo json_encode(['success' => true, 'redirect' => 'staff_login.php']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect username or password.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Authentication error.']);
    }
    exit;
}

$stmt = $conn->prepare("SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id = :id");
$stmt->execute([':id' => $admin_id]);
$adminProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$sidebar = new SidebarRenderer(
    $admin_id,
    $_SESSION['fastfood_name'] ?? '',
    $adminProfile['fullname'] ?? 'Admin',
    $adminProfile['username'] ?? '',
    $adminProfile['email'] ?? ''
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard — iPOS</title>
    <link rel="stylesheet" href="../design/admin.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
</head>
<body>
<div class="dashboard">
    <?= $sidebar->render('staff_gate') ?>
    <main class="main" style="filter:blur(3px); pointer-events:none; user-select:none;">
        <div class="topbar">
            <div><h1>👥 Staff Dashboard</h1><p class="subtitle">Cashier &amp; Kitchen access</p></div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <div style="background:var(--card-bg); border-radius:var(--radius-lg); height:280px; border:1px solid var(--border-color);"></div>
            <div style="background:var(--card-bg); border-radius:var(--radius-lg); height:280px; border:1px solid var(--border-color);"></div>
        </div>
    </main>
</div>

<!-- Login Overlay -->
<div id="gateOverlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.55);
     backdrop-filter:blur(4px); z-index:9000; display:flex; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:24px; padding:36px 32px; width:380px; max-width:95vw;
         box-shadow:0 24px 80px rgba(0,0,0,0.3); animation:gatePop 0.3s ease;">

        <div style="text-align:center; margin-bottom:24px;">
            <div style="font-size:44px; margin-bottom:8px;">👥</div>
            <h2 style="font-size:1.2rem; font-weight:800; color:#1a1a2e; margin-bottom:6px;">Switch to Staff Dashboard</h2>
            <p style="font-size:13px; color:#888;">Verify your admin credentials to continue.</p>
        </div>

        <div style="display:flex; flex-direction:column; gap:14px;">
            <div>
                <label style="font-size:11px; font-weight:700; color:#888; text-transform:uppercase;
                              letter-spacing:0.5px; display:block; margin-bottom:6px;">
                    <i class="fa-solid fa-user"></i> Username or Email
                </label>
                <input type="text" id="gateUsername" placeholder="Enter your username or email"
                    style="width:100%; padding:11px 14px; border:1px solid #e5e7eb; border-radius:10px;
                           font-size:14px; font-family:inherit; box-sizing:border-box; outline:none;
                           transition:border-color 0.2s;"
                    onfocus="this.style.borderColor='var(--accent)'"
                    onblur="this.style.borderColor='#e5e7eb'"
                    onkeydown="if(event.key==='Enter') document.getElementById('gatePassword').focus()">
            </div>
            <div style="position:relative;">
                <label style="font-size:11px; font-weight:700; color:#888; text-transform:uppercase;
                              letter-spacing:0.5px; display:block; margin-bottom:6px;">
                    <i class="fa-solid fa-lock"></i> Password
                </label>
                <input type="password" id="gatePassword" placeholder="Enter your password"
                    style="width:100%; padding:11px 14px; border:1px solid #e5e7eb; border-radius:10px;
                           font-size:14px; font-family:inherit; box-sizing:border-box; outline:none;
                           transition:border-color 0.2s; padding-right:44px;"
                    onfocus="this.style.borderColor='var(--accent)'"
                    onblur="this.style.borderColor='#e5e7eb'"
                    onkeydown="if(event.key==='Enter') gateVerify()">
                <button onclick="toggleGatePwd()" type="button"
                    style="position:absolute; right:12px; bottom:11px; background:none; border:none;
                           cursor:pointer; color:#888; font-size:14px; padding:0;">
                    <i class="fa-solid fa-eye" id="gatePwdEye"></i>
                </button>
            </div>

            <p id="gateError" style="color:#dc2626; font-size:13px; display:none; margin:0;
               background:#fef2f2; padding:8px 12px; border-radius:8px; text-align:center;">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="gateErrorMsg">Incorrect credentials.</span>
            </p>

            <button onclick="gateVerify()" id="gateBtn"
                style="width:100%; padding:12px; background:linear-gradient(135deg, var(--accent-dark), var(--accent));
                       color:white; border:none; border-radius:10px; font-size:14px; font-weight:700;
                       cursor:pointer; transition:all 0.2s; display:flex; align-items:center;
                       justify-content:center; gap:8px;">
                <i class="fa-solid fa-right-to-bracket"></i> Verify & Continue
            </button>

            <a href="admindashboard.php"
               style="display:block; text-align:center; font-size:13px; color:var(--accent);
                      font-weight:600; text-decoration:none; margin-top:4px;">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<style>
    @keyframes gatePop { from{transform:scale(0.88);opacity:0} to{transform:scale(1);opacity:1} }
</style>

<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

<script>
function toggleGatePwd() {
    const input = document.getElementById('gatePassword');
    const eye   = document.getElementById('gatePwdEye');
    if (input.type === 'password') { input.type = 'text'; eye.className = 'fa-solid fa-eye-slash'; }
    else { input.type = 'password'; eye.className = 'fa-solid fa-eye'; }
}

function gateVerify() {
    const username = document.getElementById('gateUsername').value.trim();
    const password = document.getElementById('gatePassword').value;
    const errEl    = document.getElementById('gateError');
    const errMsg   = document.getElementById('gateErrorMsg');
    const btn      = document.getElementById('gateBtn');

    if (!username || !password) {
        errMsg.textContent = 'Please enter both username and password.';
        errEl.style.display = 'block'; return;
    }

    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying…';
    btn.disabled  = true;

    fetch('staff_gate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Verified!';
            setTimeout(() => { window.location.href = data.redirect || 'staff_login.php'; }, 400);
        } else {
            errMsg.textContent = data.message || 'Incorrect credentials.';
            errEl.style.display = 'block';
            btn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Verify & Continue';
            btn.disabled  = false;
            document.getElementById('gatePassword').value = '';
            document.getElementById('gatePassword').focus();
        }
    })
    .catch(() => {
        errMsg.textContent = 'Connection error. Please try again.';
        errEl.style.display = 'block';
        btn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Verify & Continue';
        btn.disabled  = false;
    });
}

// Auto-focus username on load
document.getElementById('gateUsername').focus();
</script>
</body>
</html>
