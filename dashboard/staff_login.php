<?php
session_start();
require_once __DIR__ . "/../config/database.php";

// Must come from admin PIN gate (session token set)
if (!isset($_SESSION['admin_id']) || empty($_SESSION['staff_gate_unlocked'])) {
    header("Location: admindashboard.php");
    exit;
}

// If staff already logged in, route them directly
if (!empty($_SESSION['staff_id']) && !empty($_SESSION['staff_role'])) {
    $r = $_SESSION['staff_role'];
    header("Location: " . ($r === 'Kitchen' ? 'kitchen_dashboard.php' : 'userdashboard.php'));
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$db   = new Database();
$conn = $db->connect();

$fastfood = $_SESSION['fastfood_name'] ?? 'iPOS';

// Load theme for this admin
include __DIR__ . '/helpers/theme_loader.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — <?= htmlspecialchars($fastfood) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: var(--body-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-primary);
        }

        .login-wrap { width: 100%; max-width: 420px; padding: 24px 16px; }

        .brand { text-align: center; margin-bottom: 28px; }

        .brand-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            border-radius: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 24px; font-weight: 900; color: #fff;
            margin-bottom: 14px;
            box-shadow: 0 8px 24px color-mix(in srgb, var(--accent) 35%, transparent);
        }

        .brand h1 { font-size: 22px; font-weight: 800; color: var(--text-primary); margin-bottom: 2px; }
        .brand p  { font-size: 13px; color: var(--text-secondary); }

        .card {
            background: #fff;
            border-radius: 20px;
            padding: 28px 28px 24px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.07);
            border: 1px solid var(--border-color);
        }

        /* ── Role picker ── */
        #step-role { display: block; }
        #step-pin  { display: none; }

        #step-role h2 { font-size: 17px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; }
        #step-role > p { font-size: 13px; color: var(--accent-dark); font-weight: 600; margin-bottom: 20px; }

        .role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }

        .role-btn {
            border: 2px solid var(--border-color);
            border-radius: 16px; padding: 24px 12px 18px;
            text-align: center; cursor: pointer;
            background: var(--body-bg);
            transition: all 0.2s; font-family: inherit;
        }

        .role-btn:hover {
            border-color: var(--accent);
            background: var(--accent-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px color-mix(in srgb, var(--accent) 15%, transparent);
        }

        .role-btn .role-icon  { font-size: 34px; margin-bottom: 10px; display: block; }
        .role-btn .role-name  { font-size: 15px; font-weight: 800; color: var(--text-primary); }
        .role-btn .role-desc  { font-size: 11px; color: var(--text-secondary); margin-top: 3px; }

        .role-btn.selected { border-color: var(--accent); background: linear-gradient(135deg, var(--accent-dark), var(--accent)); }
        .role-btn.selected .role-name,
        .role-btn.selected .role-desc { color: #fff; }

        /* ── Credentials step ── */
        #step-pin h2 { font-size: 17px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; }
        .sub { font-size: 13px; color: var(--text-secondary); margin-bottom: 18px; }

        .selected-role-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: #fff; font-size: 12px; font-weight: 700;
            padding: 4px 14px; border-radius: 20px; margin-bottom: 14px;
        }

        .field-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.7px; color: var(--text-secondary);
            margin-bottom: 6px; display: block;
        }

        .text-input {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid var(--border-color); border-radius: 10px;
            font-size: 14px; font-family: inherit;
            background: var(--body-bg); color: var(--text-primary);
            outline: none; margin-bottom: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .text-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 12%, transparent);
            background: #fff;
        }

        /* ── PIN pad ── */
        .pin-dots-row { display: flex; justify-content: center; gap: 14px; margin: 14px 0 18px; }

        .pin-dot {
            width: 14px; height: 14px; border-radius: 50%;
            border: 2px solid var(--accent); background: transparent; transition: background 0.15s;
        }
        .pin-dot.filled { background: var(--accent); }

        .pin-pad { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; max-width: 240px; margin: 0 auto 18px; }

        .pin-key {
            padding: 14px; font-size: 18px; font-weight: 700;
            border: 1.5px solid var(--border-color); border-radius: 12px;
            background: var(--body-bg); color: var(--text-primary);
            cursor: pointer; font-family: inherit; transition: all 0.15s;
        }
        .pin-key:hover, .pin-key:active {
            background: var(--accent); color: #fff;
            border-color: var(--accent); transform: scale(0.95);
        }

        .error-msg {
            background: #fef2f2; color: #b91c1c;
            border: 1px solid #fca5a5; border-radius: 10px;
            padding: 10px 14px; font-size: 13px; margin-bottom: 14px; display: none;
        }

        .btn-back {
            width: 100%; padding: 11px; background: none;
            border: 1.5px solid var(--border-color); border-radius: 10px;
            font-size: 13px; font-family: inherit; color: var(--text-secondary);
            cursor: pointer; transition: all 0.2s;
        }
        .btn-back:hover { border-color: var(--accent); color: var(--accent-dark); background: var(--accent-light); }

        /* ── Admin PIN overlay ── */
        #adminPinModal {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            backdrop-filter: blur(6px); z-index: 9999;
            display: none; align-items: center; justify-content: center;
        }

        .admin-modal-card {
            background: #fff; border-radius: 24px; padding: 36px 32px;
            width: 340px; text-align: center;
            box-shadow: 0 24px 80px rgba(0,0,0,0.2);
            border-top: 4px solid var(--accent);
        }
        .admin-modal-card h2 { font-size: 17px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px; }
        .admin-modal-card p  { font-size: 13px; color: var(--text-secondary); margin-bottom: 22px; }

        @keyframes shake {
            0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-6px)} 40%,80%{transform:translateX(6px)}
        }
        .pin-shake { animation: shake 0.4s ease; }
    </style>
</head>
<body>

<div class="login-wrap">

    <div class="brand">
        <div class="brand-icon">iP</div>
        <h1><?= htmlspecialchars($fastfood) ?></h1>
        <p>Staff Login</p>
    </div>

    <div class="card">

        <!-- ── STEP 1: Pick Role ── -->
        <div id="step-role">
            <h2>Select your role</h2>
            <p>Choose what kind of staff you are to continue.</p>
            <div class="role-grid">
                <button class="role-btn" onclick="selectRole('Cashier', this)">
                    <div class="role-icon">🧾</div>
                    <div class="role-name">Cashier</div>
                    <div class="role-desc">POS & Orders</div>
                </button>
                <button class="role-btn" onclick="selectRole('Kitchen', this)">
                    <div class="role-icon">🍳</div>
                    <div class="role-name">Kitchen</div>
                    <div class="role-desc">Order Queue</div>
                </button>
            </div>
            <button class="btn-back" style="margin-top:20px; width:100%;" onclick="openAdminModal()">← Back to Admin Dashboard</button>
        </div>

        <!-- ── STEP 2: Name + PIN ── -->
        <div id="step-pin">
            <div id="role-badge" class="selected-role-badge"></div>
            <h2>Enter your credentials</h2>
            <p class="sub">Use the name and PIN assigned by your admin.</p>

            <label class="field-label">Your Full Name</label>
            <input type="text" id="staff-name" class="text-input" placeholder="e.g. Juan Dela Cruz" autocomplete="off">

            <label class="field-label" style="text-align:center; display:block;">PIN</label>
            <div class="pin-dots-row" id="pinDots">
                <div class="pin-dot"></div><div class="pin-dot"></div>
                <div class="pin-dot"></div><div class="pin-dot"></div>
            </div>
            <div class="pin-pad">
                <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                    <button type="button" class="pin-key"
                        onclick="<?= $k==='⌫' ? 'pinBack()' : ($k==='' ? '' : "pinPress($k)") ?>">
                        <?= $k ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="error-msg" id="errMsg"></div>
            <button class="btn-back" onclick="goBack()">← Back to Staff Login</button>
        </div>

    </div>

</div>

<!-- ── Admin PIN Modal (no X, staff cannot bypass) ── -->
<div id="adminPinModal" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(6px); z-index:9999; display:none; align-items:center; justify-content:center;">
    <div class="admin-modal-card">
        <div style="font-size:40px; margin-bottom:10px;">🔐</div>
        <h2>Admin Access Required</h2>
        <p>Enter the admin PIN to return to the dashboard.</p>
        <div id="adminPinDots" style="display:flex; justify-content:center; gap:14px; margin-bottom:22px;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
        </div>
        <div class="pin-pad">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                <button type="button" class="pin-key"
                    onclick="<?= $k==='⌫' ? 'aBack()' : ($k==='' ? '' : "aPress($k)") ?>">
                    <?= $k ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="adminPinError" style="color:#dc2626; font-size:13px; margin-top:12px; display:none;">Incorrect PIN. Try again.</p>
        <button class="btn-back" style="margin-top:16px;" onclick="closeAdminModal()">← Back to Staff Login</button>
    </div>
</div>

<script>
const ADMIN_ID = <?= $admin_id ?>;
let selectedRole = '';
let pinValue     = '';

// Always reset to step 1 on page load (handles bfcache / back navigation)
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('step-role').style.display = 'block';
    document.getElementById('step-pin').style.display  = 'none';
});
window.addEventListener('pageshow', () => {
    document.getElementById('step-role').style.display = 'block';
    document.getElementById('step-pin').style.display  = 'none';
    pinValue = ''; selectedRole = '';
    document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('selected'));
});

function selectRole(role, el) {
    selectedRole = role;
    document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    setTimeout(() => {
        document.getElementById('step-role').style.display = 'none';
        document.getElementById('step-pin').style.display  = 'block';
        const icons = { Cashier: '🧾', Kitchen: '🍳' };
        document.getElementById('role-badge').textContent = icons[role] + ' ' + role;
        document.getElementById('staff-name').focus();
        pinValue = ''; updateDots();
        document.getElementById('errMsg').style.display = 'none';
    }, 160);
}

function goBack() {
    document.getElementById('step-pin').style.display  = 'none';
    document.getElementById('step-role').style.display = 'block';
    pinValue = ''; updateDots();
    document.getElementById('errMsg').style.display = 'none';
}

/* ── Admin PIN modal (back to admin dashboard) ── */
let aPin = '';
function openAdminModal() {
    aPin = ''; updAdminDots(0);
    document.getElementById('adminPinError').style.display = 'none';
    document.getElementById('adminPinModal').style.display = 'flex';
}
function closeAdminModal() {
    document.getElementById('adminPinModal').style.display = 'none';
    aPin = ''; updAdminDots(0);
}
function aPress(num) {
    if (aPin.length >= 4) return;
    aPin += String(num);
    updAdminDots(aPin.length);
    if (aPin.length === 4) setTimeout(verifyAdminPin, 100);
}
function aBack() { aPin = aPin.slice(0,-1); updAdminDots(aPin.length); }
function updAdminDots(n) {
    document.querySelectorAll('#adminPinDots .pin-dot').forEach((d,i) => d.classList.toggle('filled', i < n));
}
function verifyAdminPin() {
    fetch('helpers/admindashboard_helpers.php?action=check_pin', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'pin=' + encodeURIComponent(aPin)
    }).then(r => r.json()).then(data => {
        if (data.success) {
            window.location.href = 'admindashboard.php';
        } else {
            document.getElementById('adminPinError').style.display = 'block';
            aPin = ''; updAdminDots(0);
            const d = document.getElementById('adminPinDots');
            d.classList.add('pin-shake'); setTimeout(() => d.classList.remove('pin-shake'), 450);
        }
    });
}

function pinPress(num) {
    if (pinValue.length >= 4) return;
    pinValue += String(num);
    updateDots();
    if (pinValue.length === 4) setTimeout(attemptLogin, 120);
}

function pinBack() {
    pinValue = pinValue.slice(0, -1);
    updateDots();
}

function updateDots() {
    document.querySelectorAll('#pinDots .pin-dot').forEach((d, i) => d.classList.toggle('filled', i < pinValue.length));
}

document.addEventListener('keydown', e => {
    const adminOpen = document.getElementById('adminPinModal').style.display === 'flex';
    if (adminOpen) {
        if (e.key >= '0' && e.key <= '9') aPress(parseInt(e.key));
        if (e.key === 'Backspace') aBack();
        if (e.key === 'Escape') closeAdminModal();
        return;
    }
    if (document.getElementById('step-pin').style.display === 'none') return;
    if (e.key >= '0' && e.key <= '9') pinPress(parseInt(e.key));
    if (e.key === 'Backspace') pinBack();
    if (e.key === 'Enter') {
        const name = document.getElementById('staff-name').value.trim();
        if (name && pinValue.length === 4) attemptLogin();
    }
});

function showError(msg) {
    const el = document.getElementById('errMsg');
    el.textContent = msg;
    el.style.display = 'block';
    const d = document.getElementById('pinDots');
    d.classList.add('pin-shake');
    setTimeout(() => d.classList.remove('pin-shake'), 450);
    pinValue = ''; updateDots();
}

function attemptLogin() {
    const name = document.getElementById('staff-name').value.trim();
    if (!name) { showError('Please enter your full name.'); return; }

    const body = new URLSearchParams({
        admin_id: ADMIN_ID,
        fullname: name,
        pin:      pinValue,
        role:     selectedRole,
    });

    fetch('helpers/staff_helpers.php?action=staff_login', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                showError(data.message || 'Login failed. Check your name and PIN.');
            }
        })
        .catch(() => showError('Network error. Please try again.'));
}
</script>

</body>
</html>