<?php
session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/helpers/admindashboard_helpers.php";
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$db       = new Database();
$conn     = $db->connect();
$admin_id = $_SESSION['admin_id'];

$adminProfile = [];
try {
    $stmt = $conn->prepare("SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id = :id");
    $stmt->bindParam(':id', $admin_id);
    $stmt->execute();
    $adminProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $adminProfile = [];
}

$sidebar = new SidebarRenderer(
    $admin_id,
    $_SESSION['fastfood_name'] ?? '',
    $adminProfile['fullname'] ?? 'Admin',
    $adminProfile['username'] ?? '',
    $adminProfile['email'] ?? ''
);

$adminName = $adminProfile['fullname'] ?? 'Admin';
$username  = $adminProfile['username'] ?? '';
$email     = $adminProfile['email'] ?? '';
$fastfood  = $adminProfile['fastfood_name'] ?? '';
$initial   = strtoupper(substr($adminName, 0, 1)) ?: 'A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Dashboard — iPOS</title>
    <link rel="stylesheet" href="../design/admin.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== ACCOUNT DASHBOARD STYLES ===== */
        .acct-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            max-width: 1000px;
        }
        .acct-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }
        .acct-card.full-width { grid-column: 1 / -1; }
        .acct-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .acct-card-title .icon {
            width: 32px; height: 32px;
            background: var(--accent-light);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }

        /* Profile Hero */
        .profile-hero {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 24px;
            background: linear-gradient(135deg, var(--accent-dark) 0%, var(--accent) 100%);
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            color: white;
        }
        .profile-hero-avatar {
            width: 72px; height: 72px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; font-weight: 800;
            border: 3px solid rgba(255,255,255,0.4);
            flex-shrink: 0;
        }
        .profile-hero-info h2 {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .profile-hero-info p { opacity: 0.8; font-size: 13px; }
        .profile-hero-badge {
            margin-left: auto;
            background: rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.3);
        }

        /* Credential Fields */
        .cred-field {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .cred-field:last-child { border-bottom: none; }
        .cred-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cred-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            text-align: right;
        }
        .cred-value.masked {
            font-family: monospace;
            letter-spacing: 4px;
            color: var(--text-secondary);
        }
        .cred-edit-btn {
            background: var(--accent-light);
            color: var(--accent);
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-left: 12px;
            flex-shrink: 0;
        }
        .cred-edit-btn:hover { background: var(--accent); color: white; }

        /* Color Theme */
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .theme-swatch {
            border-radius: 12px;
            padding: 12px 8px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.2s;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .theme-swatch:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .theme-swatch.active { border-color: var(--accent-dark); box-shadow: 0 0 0 1px var(--accent-dark); }
        .theme-swatch .swatch-preview {
            height: 32px;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .theme-swatch .swatch-name {
            font-size: 11px;
            font-weight: 600;
            color: #444;
        }
        .theme-swatch .check-mark {
            position: absolute;
            top: 6px; right: 6px;
            width: 18px; height: 18px;
            background: var(--accent-dark);
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 10px;
        }
        .theme-swatch.active .check-mark { display: flex; }

        /* Custom Palette */
        .palette-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .palette-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            width: 120px;
            flex-shrink: 0;
        }
        .palette-color-input {
            width: 40px; height: 40px;
            border: none;
            border-radius: 8px;
            padding: 2px;
            cursor: pointer;
            background: none;
        }
        .palette-hex {
            font-size: 12px;
            font-family: monospace;
            color: var(--text-primary);
            background: var(--body-bg);
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            width: 90px;
        }

        .apply-theme-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .apply-theme-btn:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Password Change Section */
        .pwd-form { display: flex; flex-direction: column; gap: 14px; }
        .pwd-form input {
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text-primary);
            background: var(--body-bg);
            transition: border-color 0.2s;
        }
        .pwd-form input:focus {
            outline: none;
            border-color: var(--accent);
            background: white;
        }
        .pwd-form label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
            display: block;
        }
        .pwd-strength {
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
            margin-top: -8px;
        }
        .pwd-strength-bar {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
            width: 0%;
        }
        .save-pwd-btn {
            padding: 12px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .save-pwd-btn:hover { background: var(--accent-dark); }

        /* Info Section */
        .acct-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .info-tile {
            background: var(--body-bg);
            border-radius: 10px;
            padding: 14px;
            border: 1px solid var(--border-color);
        }
        .info-tile-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .info-tile-value {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .toast-acct {
            position: fixed;
            bottom: 24px; right: 24px;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            z-index: 9999;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s;
            color: white;
        }
        .toast-acct.show { opacity: 1; transform: translateY(0); }
        .toast-acct.success { background: var(--success); }
        .toast-acct.error   { background: var(--danger); }
        .toast-acct.info    { background: var(--info); }
    </style>
</head>
<body>
<div class="dashboard">

    <?php echo $sidebar->render('account'); ?>

    <main class="main">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h1>👤 Account Dashboard</h1>
                <p class="subtitle">Manage your credentials, preferences, and appearance</p>
            </div>
        </div>

        <!-- Profile Hero -->
        <div class="profile-hero">
            <div class="profile-hero-avatar"><?= htmlspecialchars($initial) ?></div>
            <div class="profile-hero-info">
                <h2><?= htmlspecialchars($adminName) ?></h2>
                <p>@<?= htmlspecialchars($username) ?> · <?= htmlspecialchars($email) ?></p>
                <p style="margin-top:4px;">🏪 <?= htmlspecialchars($fastfood) ?></p>
            </div>
            <div class="profile-hero-badge">Administrator</div>
        </div>

        <div class="acct-wrapper">

            <!-- Credentials Card -->
            <div class="acct-card">
                <div class="acct-card-title">
                    <div class="icon">🔑</div>
                    Account Credentials
                </div>

                <div class="cred-field">
                    <div>
                        <div class="cred-label">Full Name</div>
                        <div class="cred-value" id="disp-fullname"><?= htmlspecialchars($adminName) ?></div>
                    </div>
                    <button class="cred-edit-btn" onclick="openEditField('fullname','Full Name','<?= addslashes($adminName) ?>')">Edit</button>
                </div>
                <div class="cred-field">
                    <div>
                        <div class="cred-label">Username</div>
                        <div class="cred-value" id="disp-username"><?= htmlspecialchars($username) ?></div>
                    </div>
                    <button class="cred-edit-btn" onclick="openEditField('username','Username','<?= addslashes($username) ?>')">Edit</button>
                </div>
                <div class="cred-field">
                    <div>
                        <div class="cred-label">Email Address</div>
                        <div class="cred-value" id="disp-email"><?= htmlspecialchars($email) ?></div>
                    </div>
                    <button class="cred-edit-btn" onclick="openEditField('email','Email Address','<?= addslashes($email) ?>')">Edit</button>
                </div>
                <div class="cred-field">
                    <div>
                        <div class="cred-label">Restaurant Name</div>
                        <div class="cred-value" id="disp-fastfood"><?= htmlspecialchars($fastfood) ?></div>
                    </div>
                    <button class="cred-edit-btn" onclick="openEditField('fastfood_name','Restaurant Name','<?= addslashes($fastfood) ?>')">Edit</button>
                </div>
                <div class="cred-field">
                    <div>
                        <div class="cred-label">Password</div>
                        <div class="cred-value masked">••••••••</div>
                    </div>
                    <button class="cred-edit-btn" onclick="document.getElementById('pwdCard').scrollIntoView({behavior:'smooth'})">Change</button>
                </div>
            </div>

            <!-- Account Info Tiles -->
            <div class="acct-card">
                <div class="acct-card-title">
                    <div class="icon">📋</div>
                    Account Info
                </div>
                <div class="acct-info-grid">
                    <div class="info-tile">
                        <div class="info-tile-label">Account ID</div>
                        <div class="info-tile-value">#<?= (int)$admin_id ?></div>
                    </div>
                    <div class="info-tile">
                        <div class="info-tile-label">Role</div>
                        <div class="info-tile-value">Administrator</div>
                    </div>
                    <div class="info-tile">
                        <div class="info-tile-label">Username</div>
                        <div class="info-tile-value">@<?= htmlspecialchars($username) ?></div>
                    </div>
                    <div class="info-tile">
                        <div class="info-tile-label">Restaurant</div>
                        <div class="info-tile-value"><?= htmlspecialchars($fastfood ?: '—') ?></div>
                    </div>
                    <div class="info-tile" style="grid-column: 1/-1;">
                        <div class="info-tile-label">Email</div>
                        <div class="info-tile-value"><?= htmlspecialchars($email ?: '—') ?></div>
                    </div>
                </div>
            </div>

            <!-- Color Theme Card -->
            <div class="acct-card full-width">
                <div class="acct-card-title">
                    <div class="icon">🎨</div>
                    Color Theme & Palette
                </div>

                <p style="font-size:13px; color:var(--text-secondary); margin-bottom:18px;">Choose a preset theme or customize your own color palette for the dashboard.</p>

                <!-- Preset Themes -->
                <?php
                // Map preset themes by their accent color so we can mark the saved one as active
                $presets = [
                    'rose'    => ['dark'=>'#7e1545','accent'=>'#be185d'],
                    'ocean'   => ['dark'=>'#0c4a6e','accent'=>'#0284c7'],
                    'forest'  => ['dark'=>'#14532d','accent'=>'#16a34a'],
                    'amber'   => ['dark'=>'#92400e','accent'=>'#d97706'],
                    'violet'  => ['dark'=>'#4c1d95','accent'=>'#7c3aed'],
                    'slate'   => ['dark'=>'#1e293b','accent'=>'#475569'],
                    'crimson' => ['dark'=>'#7f1d1d','accent'=>'#dc2626'],
                    'teal'    => ['dark'=>'#134e4a','accent'=>'#0d9488'],
                ];
                // Detect which preset matches the saved accent color (case-insensitive)
                $savedAccent = strtolower($acd ?? '#7e1545');
                $activeTheme = 'rose'; // default fallback
                foreach ($presets as $name => $p) {
                    if (strtolower($p['dark']) === $savedAccent || strtolower($p['accent']) === strtolower($ac ?? '')) {
                        $activeTheme = $name;
                        break;
                    }
                }
                ?>
                <div class="theme-grid">
                    <?php foreach ($presets as $name => $p):
                        $isActive = ($name === $activeTheme);
                        $labels = ['rose'=>'Rose (Default)','ocean'=>'Ocean Blue','forest'=>'Forest Green','amber'=>'Amber Gold','violet'=>'Royal Violet','slate'=>'Slate Gray','crimson'=>'Crimson Red','teal'=>'Teal Mint'];
                        $gradients = ['rose'=>'#7e1545,#be185d','ocean'=>'#0c4a6e,#0284c7','forest'=>'#14532d,#16a34a','amber'=>'#92400e,#d97706','violet'=>'#4c1d95,#7c3aed','slate'=>'#1e293b,#475569','crimson'=>'#7f1d1d,#dc2626','teal'=>'#134e4a,#0d9488'];
                    ?>
                    <div class="theme-swatch <?= $isActive ? 'active' : '' ?>" data-theme="<?= $name ?>"
                         onclick="selectTheme(this,'<?= $p['dark'] ?>','<?= $p['accent'] ?>','<?php
                            $bgs = ['rose'=>'#f5eef4','ocean'=>'#eff8ff','forest'=>'#f0fdf4','amber'=>'#fffbeb','violet'=>'#f5f3ff','slate'=>'#f1f5f9','crimson'=>'#fef2f2','teal'=>'#f0fdfa'];
                            $txts = ['rose'=>'#2d0a1f','ocean'=>'#0c2340','forest'=>'#052e16','amber'=>'#451a03','violet'=>'#1e0050','slate'=>'#0f172a','crimson'=>'#3b0000','teal'=>'#042f2e'];
                            echo $bgs[$name];
                         ?>','<?= $txts[$name] ?>')">
                        <div class="check-mark" style="background:<?= $p['dark'] ?>;">✓</div>
                        <div class="swatch-preview" style="background: linear-gradient(135deg,<?= $gradients[$name] ?>);"></div>
                        <div class="swatch-name"><?= $labels[$name] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Custom Palette -->
                <div style="border-top: 1px solid var(--border-color); padding-top: 20px; margin-top: 4px;">
                    <div style="font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:14px;">✏️ Custom Palette</div>
                    <div class="palette-row">
                        <span class="palette-label">Sidebar Dark</span>
                        <input type="color" class="palette-color-input" id="c-sidebar-bg" value="#2d0a1f" oninput="syncHex('c-sidebar-bg','h-sidebar-bg')">
                        <input type="text" class="palette-hex" id="h-sidebar-bg" value="#2d0a1f" oninput="syncColor('h-sidebar-bg','c-sidebar-bg')">
                    </div>
                    <div class="palette-row">
                        <span class="palette-label">Accent Color</span>
                        <input type="color" class="palette-color-input" id="c-accent" value="#be185d" oninput="syncHex('c-accent','h-accent')">
                        <input type="text" class="palette-hex" id="h-accent" value="#be185d" oninput="syncColor('h-accent','c-accent')">
                    </div>
                    <div class="palette-row">
                        <span class="palette-label">Accent Dark</span>
                        <input type="color" class="palette-color-input" id="c-accent-dark" value="#7e1545" oninput="syncHex('c-accent-dark','h-accent-dark')">
                        <input type="text" class="palette-hex" id="h-accent-dark" value="#7e1545" oninput="syncColor('h-accent-dark','c-accent-dark')">
                    </div>
                    <div class="palette-row">
                        <span class="palette-label">Body Background</span>
                        <input type="color" class="palette-color-input" id="c-body-bg" value="#f5eef4" oninput="syncHex('c-body-bg','h-body-bg')">
                        <input type="text" class="palette-hex" id="h-body-bg" value="#f5eef4" oninput="syncColor('h-body-bg','c-body-bg')">
                    </div>
                    <div class="palette-row">
                        <span class="palette-label">Text Primary</span>
                        <input type="color" class="palette-color-input" id="c-text" value="#2d0a1f" oninput="syncHex('c-text','h-text')">
                        <input type="text" class="palette-hex" id="h-text" value="#2d0a1f" oninput="syncColor('h-text','c-text')">
                    </div>
                </div>
                <button class="apply-theme-btn" onclick="applyTheme()">🎨 Apply Theme</button>
            </div>

            <!-- Password Change Card -->
            <div class="acct-card full-width" id="pwdCard">
                <div class="acct-card-title">
                    <div class="icon">🔒</div>
                    Change Password
                </div>
                <div class="pwd-form">
                    <div>
                        <label>Current Password</label>
                        <input type="password" id="current-pwd" placeholder="Enter current password">
                    </div>
                    <div>
                        <label>New Password</label>
                        <input type="password" id="new-pwd" placeholder="Enter new password" oninput="checkStrength(this.value)">
                        <div class="pwd-strength" style="margin-top:8px;">
                            <div class="pwd-strength-bar" id="strengthBar"></div>
                        </div>
                        <div style="font-size:11px; color:var(--text-secondary); margin-top:4px;" id="strengthLabel"></div>
                    </div>
                    <div>
                        <label>Confirm New Password</label>
                        <input type="password" id="confirm-pwd" placeholder="Confirm new password">
                    </div>
                    <button class="save-pwd-btn" onclick="changePassword()">🔒 Update Password</button>
                </div>
            </div>

            <!-- Change Dashboard PIN Card -->
            <div class="acct-card full-width">
                <div class="acct-card-title">
                    <div class="icon">🔐</div>
                    Dashboard PIN
                </div>
                <p style="font-size:13px; color:var(--text-secondary); margin-bottom:16px;">
                    This PIN protects access to the Account &amp; Kitchen Manager dashboards. Default is <strong>0000</strong>.
                </p>
                <div class="pwd-form">
                    <div>
                        <label>Current PIN</label>
                        <input type="password" id="cur-pin" maxlength="4" inputmode="numeric" pattern="[0-9]*" placeholder="Enter current 4-digit PIN">
                    </div>
                    <div>
                        <label>New PIN</label>
                        <input type="password" id="new-pin" maxlength="4" inputmode="numeric" pattern="[0-9]*" placeholder="Enter new 4-digit PIN">
                    </div>
                    <div>
                        <label>Confirm New PIN</label>
                        <input type="password" id="confirm-pin" maxlength="4" inputmode="numeric" pattern="[0-9]*" placeholder="Confirm new 4-digit PIN">
                    </div>
                    <button class="save-pwd-btn" onclick="changeDashboardPin()">🔐 Update Dashboard PIN</button>
                </div>
            </div>

        </div><!-- end acct-wrapper -->
    </main>
</div>

<!-- Edit Field Modal -->
<div id="editFieldModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:5000; align-items:center; justify-content:center;" onclick="if(event.target===this)closeEditModal()">
    <div style="background:white; border-radius:16px; padding:28px; width:360px; box-shadow: 0 20px 60px rgba(0,0,0,0.2);">
        <h3 style="margin-bottom:6px; font-size:1rem; font-weight:700;" id="editModalTitle">Edit Field</h3>
        <p style="font-size:12px; color:#888; margin-bottom:20px;">Update your account information</p>
        <input type="text" id="editFieldValue" style="width:100%; padding:12px 14px; border:1px solid #e0d5e0; border-radius:10px; font-size:14px; font-family:inherit; margin-bottom:16px;" placeholder="">
        <input type="hidden" id="editFieldName">
        <div style="display:flex; gap:10px;">
            <button onclick="closeEditModal()" style="flex:1; padding:11px; background:#f5eef4; color:#666; border:none; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">Cancel</button>
            <button onclick="saveField()" style="flex:1; padding:11px; background:#be185d; color:white; border:none; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">Save</button>
        </div>
    </div>
</div>

<!-- Pin Modals (required by sidebar) -->
<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

<script>
// ===== EDIT FIELD MODAL =====
function openEditField(field, label, currentVal) {
    document.getElementById('editModalTitle').textContent = 'Edit ' + label;
    document.getElementById('editFieldName').value = field;
    document.getElementById('editFieldValue').value = currentVal;
    document.getElementById('editFieldModal').style.display = 'flex';
    document.getElementById('editFieldValue').focus();
}
function closeEditModal() {
    document.getElementById('editFieldModal').style.display = 'none';
}
function saveField() {
    const field = document.getElementById('editFieldName').value;
    const value = document.getElementById('editFieldValue').value.trim();
    if (!value) { showToastAcct('Field cannot be empty', 'error'); return; }

    const form = new FormData();
    form.append(field, value);
    form.append('_action', 'update_field');

    fetch('helpers/account_helpers.php?action=update_field', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `field=${encodeURIComponent(field)}&value=${encodeURIComponent(value)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeEditModal();
            showToastAcct('Updated successfully!', 'success');
            // Update display
            const dispMap = {
                fullname: 'disp-fullname',
                username: 'disp-username',
                email: 'disp-email',
                fastfood_name: 'disp-fastfood'
            };
            if (dispMap[field]) document.getElementById(dispMap[field]).textContent = value;
        } else {
            showToastAcct(data.message || 'Update failed', 'error');
        }
    })
    .catch(() => showToastAcct('Connection error', 'error'));
}

// ===== THEME SELECTION =====
function selectTheme(el, dark, accent, bodyBg, text) {
    document.querySelectorAll('.theme-swatch').forEach(s => {
        s.classList.remove('active');
        s.style.borderColor = '';
    });
    el.classList.add('active');
    el.style.borderColor = dark;
    // Update checkmark color to this swatch's own dark color immediately
    el.querySelector('.check-mark').style.background = dark;
    // Update custom palette inputs to match
    document.getElementById('c-sidebar-bg').value  = dark;
    document.getElementById('h-sidebar-bg').value  = dark;
    document.getElementById('c-accent').value      = accent;
    document.getElementById('h-accent').value      = accent;
    document.getElementById('c-accent-dark').value = dark;
    document.getElementById('h-accent-dark').value = dark;
    document.getElementById('c-body-bg').value     = bodyBg;
    document.getElementById('h-body-bg').value     = bodyBg;
    document.getElementById('c-text').value        = text;
    document.getElementById('h-text').value        = text;
    // Live preview
    livePreview({ sidebarBg: dark, accent, accentDark: dark, bodyBg, text });
}

function livePreview(p) {
    const r = document.documentElement;
    r.style.setProperty('--sidebar-bg',       p.sidebarBg);
    r.style.setProperty('--accent',           p.accent);
    r.style.setProperty('--accent-dark',      p.accentDark);
    r.style.setProperty('--accent-light',     p.accentLight || p.bodyBg);
    r.style.setProperty('--body-bg',          p.bodyBg);
    r.style.setProperty('--text-primary',     p.text);
    r.style.setProperty('--sidebar-active-bg',p.accentDark);
    if (p.borderColor) r.style.setProperty('--border-color', p.borderColor);
    if (p.textSec)     r.style.setProperty('--text-secondary', p.textSec);
}

function applyTheme() {
    const sidebarBg  = document.getElementById('c-sidebar-bg').value;
    const accent     = document.getElementById('c-accent').value;
    const accentDark = document.getElementById('c-accent-dark').value;
    const bodyBg     = document.getElementById('c-body-bg').value;
    const text       = document.getElementById('c-text').value;

    // Derive accent-light (lighten bodyBg) and border/textSec from accentDark
    const accentLight = bodyBg;
    const borderColor = lightenHex(accentDark, 0.85);
    const textSec     = lightenHex(accentDark, 0.4);

    const palette = { sidebarBg, accent, accentDark, bodyBg, text, accentLight, borderColor, textSec };

    livePreview(palette);

    // Save to DB (server-side, affects all pages)
    fetch('helpers/account_helpers.php?action=save_theme', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(palette)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToastAcct('Theme saved — applies to all pages! 🎨', 'success');
        } else {
            showToastAcct('Theme applied locally. DB: ' + (data.message || 'error'), 'info');
        }
    })
    .catch(() => showToastAcct('Theme applied locally only', 'info'));
}

// Lighten a hex color by mixing with white
function lightenHex(hex, amount) {
    try {
        let r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
        r = Math.round(r + (255-r)*amount);
        g = Math.round(g + (255-g)*amount);
        b = Math.round(b + (255-b)*amount);
        return '#' + [r,g,b].map(x=>x.toString(16).padStart(2,'0')).join('');
    } catch(e) { return hex; }
}

function syncHex(colorId, hexId) {
    document.getElementById(hexId).value = document.getElementById(colorId).value;
}
function syncColor(hexId, colorId) {
    const val = document.getElementById(hexId).value;
    if (/^#[0-9a-f]{6}$/i.test(val)) document.getElementById(colorId).value = val;
}

// Theme is loaded server-side via theme_loader.php — no localStorage needed
// Sync palette inputs to currently applied CSS vars on load
(function syncInputsToCurrentTheme() {
    const cs = getComputedStyle(document.documentElement);
    const get = (v) => cs.getPropertyValue(v).trim();
    const sidebar = get('--sidebar-bg');
    const accent  = get('--accent');
    const dark    = get('--accent-dark');
    const bg      = get('--body-bg');
    const text    = get('--text-primary');
    if (sidebar) { document.getElementById('c-sidebar-bg').value = sidebar; document.getElementById('h-sidebar-bg').value = sidebar; }
    if (accent)  { document.getElementById('c-accent').value = accent;      document.getElementById('h-accent').value = accent; }
    if (dark)    { document.getElementById('c-accent-dark').value = dark;   document.getElementById('h-accent-dark').value = dark; }
    if (bg)      { document.getElementById('c-body-bg').value = bg;         document.getElementById('h-body-bg').value = bg; }
    if (text)    { document.getElementById('c-text').value = text;          document.getElementById('h-text').value = text; }
})();

// ===== PASSWORD CHANGE =====
function checkStrength(val) {
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { w: '0%',   bg: 'transparent', lbl: '' },
        { w: '25%',  bg: '#dc2626', lbl: 'Weak' },
        { w: '50%',  bg: '#d97706', lbl: 'Fair' },
        { w: '75%',  bg: '#2563eb', lbl: 'Good' },
        { w: '100%', bg: '#16a34a', lbl: 'Strong' },
    ];
    bar.style.width = levels[score].w;
    bar.style.background = levels[score].bg;
    lbl.textContent = levels[score].lbl;
    lbl.style.color = levels[score].bg;
}

function changePassword() {
    const curr    = document.getElementById('current-pwd').value;
    const newPwd  = document.getElementById('new-pwd').value;
    const confirm = document.getElementById('confirm-pwd').value;
    if (!curr || !newPwd || !confirm) { showToastAcct('All fields required', 'error'); return; }
    if (newPwd !== confirm) { showToastAcct('Passwords do not match', 'error'); return; }
    if (newPwd.length < 6) { showToastAcct('Password too short (min 6)', 'error'); return; }

    fetch('helpers/account_helpers.php?action=change_password', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `current_password=${encodeURIComponent(curr)}&new_password=${encodeURIComponent(newPwd)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToastAcct('Password updated! 🔒', 'success');
            document.getElementById('current-pwd').value = '';
            document.getElementById('new-pwd').value = '';
            document.getElementById('confirm-pwd').value = '';
            document.getElementById('strengthBar').style.width = '0';
            document.getElementById('strengthLabel').textContent = '';
        } else {
            showToastAcct(data.message || 'Failed to update', 'error');
        }
    })
    .catch(() => showToastAcct('Connection error', 'error'));
}

// ===== TOAST =====
function showToastAcct(msg, type) {
    const el = document.createElement('div');
    el.className = 'toast-acct ' + type;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add('show'), 10);
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 2800);
}

// ===== DASHBOARD PIN CHANGE =====
function changeDashboardPin() {
    const cur  = document.getElementById('cur-pin').value.trim();
    const np   = document.getElementById('new-pin').value.trim();
    const conf = document.getElementById('confirm-pin').value.trim();
    if (!cur || !np || !conf) { showToastAcct('All PIN fields required', 'error'); return; }
    if (!/^\d{4}$/.test(np))  { showToastAcct('PIN must be exactly 4 digits', 'error'); return; }
    if (np !== conf)           { showToastAcct('PINs do not match', 'error'); return; }

    fetch('helpers/account_helpers.php?action=change_pin', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `current_pin=${encodeURIComponent(cur)}&new_pin=${encodeURIComponent(np)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToastAcct('Dashboard PIN updated! 🔐', 'success');
            document.getElementById('cur-pin').value = '';
            document.getElementById('new-pin').value = '';
            document.getElementById('confirm-pin').value = '';
        } else {
            showToastAcct(data.message || 'Failed to update PIN', 'error');
        }
    })
    .catch(() => showToastAcct('Connection error', 'error'));
}

// Open PIN modal based on type  
window.openPinModal = function(type) {
    sessionStorage.setItem('pinTarget', type);
    fetch('helpers/admindashboard_helpers.php?action=check_pin')
        .then(r => r.json())
        .then(data => {
            if (data.has_pin) {
                pinValue = '';
                updatePinDots('pinDots', 0);
                document.getElementById('pinError').style.display = 'none';
                document.getElementById('pinModal').classList.add('show');
            } else {
                setupPinStep = 1; setupPinFirst = ''; setupPinCurrent = '';
                updatePinDots('setupPinDots', 0);
                document.getElementById('setupPinLabel').textContent = 'Enter a 4-digit PIN to protect the user dashboard';
                document.getElementById('setupPinError').style.display = 'none';
                document.getElementById('setupPinModal').classList.add('show');
            }
        });
};
</script>
</body>
</html>