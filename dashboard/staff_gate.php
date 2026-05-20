<?php
session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/helpers/admindashboard_helpers.php";

// Prevent caching so browsers don't show stale pages after logout
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$db       = new Database();
$conn     = $db->connect();

// Always clear any previous gate unlock — force re-auth every visit
unset($_SESSION['staff_gate_unlocked'], $_SESSION['staff_login_active']);

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
            <div><h1><i class="fa-solid fa-users" style="margin-right:8px;"></i>Staff Dashboard</h1><p class="subtitle">Cashier &amp; Kitchen access</p></div>
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
            <div style="font-size:44px; margin-bottom:8px;"><i class="fa-solid fa-users" style="color:var(--accent);"></i></div>
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

            <button type="button" onclick="openGateBackModal()"
               style="display:block; width:100%; text-align:center; font-size:13px;
                      color:var(--accent); font-weight:600; text-decoration:none;
                      margin-top:4px; background:none; border:none; cursor:pointer;
                      font-family:inherit;">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </button>
        </div>
    </div>
</div>

<style>
    @keyframes gatePop { from{transform:scale(0.88);opacity:0} to{transform:scale(1);opacity:1} }
</style>

<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

<!-- Admin PIN modal for gate back-button protection -->
<div id="gateAdminPinModal" style="position:fixed;inset:0;background:rgba(0,0,0,0.6);
     backdrop-filter:blur(6px);z-index:10000;display:none;
     align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:24px;padding:36px 32px;width:340px;
         text-align:center;box-shadow:0 24px 80px rgba(0,0,0,0.25);
         border-top:4px solid var(--accent);">
        <div style="font-size:40px;margin-bottom:10px;">&#128274;</div>
        <h2 style="font-size:17px;font-weight:800;color:#1a1a2e;margin-bottom:6px;">Admin Access Required</h2>
        <p style="font-size:13px;color:#888;margin-bottom:22px;">Enter your admin PIN to return to the dashboard.</p>
        <div id="gapDots" style="display:flex;justify-content:center;gap:14px;margin-bottom:22px;">
            <div class="pin-dot" style="width:14px;height:14px;border-radius:50%;border:2px solid var(--accent);background:transparent;transition:background .15s;"></div>
            <div class="pin-dot" style="width:14px;height:14px;border-radius:50%;border:2px solid var(--accent);background:transparent;transition:background .15s;"></div>
            <div class="pin-dot" style="width:14px;height:14px;border-radius:50%;border:2px solid var(--accent);background:transparent;transition:background .15s;"></div>
            <div class="pin-dot" style="width:14px;height:14px;border-radius:50%;border:2px solid var(--accent);background:transparent;transition:background .15s;"></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;max-width:220px;margin:0 auto 18px;">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'back'] as $gk): ?>
            <button type="button"
                onclick="<?= $gk==='back' ? 'gapBack()' : ($gk==='' ? '' : "gapPress($gk)") ?>"
                style="padding:14px;font-size:18px;font-weight:700;border:1.5px solid var(--border-color);
                       border-radius:12px;background:var(--body-bg);color:var(--text-primary);
                       cursor:pointer;font-family:inherit;transition:all .15s;"
                onmouseenter="this.style.background='var(--accent)';this.style.color='#fff';"
                onmouseleave="this.style.background='var(--body-bg)';this.style.color='var(--text-primary)';"
            ><?= $gk === 'back' ? '&#9003;' : $gk ?></button>
            <?php endforeach; ?>
        </div>
        <p id="gapError" style="color:#dc2626;font-size:13px;margin-bottom:14px;display:none;">Incorrect PIN. Try again.</p>
        <button type="button" onclick="closeGateBackModal()"
            style="background:none;border:1.5px solid var(--border-color);padding:9px 24px;
                   border-radius:8px;cursor:pointer;font-size:13px;color:var(--text-secondary);
                   font-family:inherit;">Cancel</button>
    </div>
</div>

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

// Push 50 sentinel entries so back button cannot leave this page.
(function lockHistory() {
    for (let i = 0; i < 50; i++) {
        history.pushState({ gateGuard: true, i: i }, '', window.location.href);
    }
}());

window.addEventListener('popstate', function() {
    for (let i = 0; i < 50; i++) {
        history.pushState({ gateGuard: true, i: i }, '', window.location.href);
    }
    openGateBackModal();
});

let gapPin = '';

function openGateBackModal() {
    gapPin = '';
    gapUpdateDots(0);
    document.getElementById('gapError').style.display = 'none';
    document.getElementById('gateAdminPinModal').style.display = 'flex';
}

function closeGateBackModal() {
    document.getElementById('gateAdminPinModal').style.display = 'none';
    gapPin = '';
    gapUpdateDots(0);
}

function gapPress(num) {
    if (gapPin.length >= 4) return;
    gapPin += String(num);
    gapUpdateDots(gapPin.length);
    if (gapPin.length === 4) setTimeout(gapVerify, 100);
}

function gapBack() { gapPin = gapPin.slice(0, -1); gapUpdateDots(gapPin.length); }

function gapUpdateDots(n) {
    document.querySelectorAll('#gapDots .pin-dot')
        .forEach(function(d, i) { d.style.background = i < n ? 'var(--accent)' : 'transparent'; });
}

function gapVerify() {
    fetch('helpers/admindashboard_helpers.php?action=check_pin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'pin=' + encodeURIComponent(gapPin)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.replace('admindashboard.php');
        } else {
            document.getElementById('gapError').style.display = 'block';
            gapPin = ''; gapUpdateDots(0);
        }
    })
    .catch(function() {
        document.getElementById('gapError').textContent = 'Connection error. Try again.';
        document.getElementById('gapError').style.display = 'block';
        gapPin = ''; gapUpdateDots(0);
    });
}

document.addEventListener('keydown', function(e) {
    if (document.getElementById('gateAdminPinModal').style.display !== 'flex') return;
    if (e.key >= '0' && e.key <= '9') gapPress(parseInt(e.key));
    if (e.key === 'Backspace') gapBack();
    if (e.key === 'Escape') closeGateBackModal();
});

// On pageshow (including bfcache restore) verify server-side session state.
window.addEventListener('pageshow', function() {
    fetch('helpers/session_check.php', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (!data.admin_id) {
                // No admin session — redirect to login
                // Use replace() so this page is removed from history
                window.location.replace('../login.php');
                return;
            }
            // Clear any lingering staff session before showing gate
            if (data.staff_id) {
                fetch('helpers/clear_gate.php', { method: 'POST', cache: 'no-store' }).catch(() => {});
            }
        })
        .catch(() => {
            window.location.replace('../login.php');
        });
});
</script>
</body>
</html>