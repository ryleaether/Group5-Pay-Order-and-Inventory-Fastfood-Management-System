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

// Fetch extended profile fields (may not exist yet — use try/catch)
$extProfile = [];
try {
   $stmt2 = $conn->prepare("SELECT age, contact_number, home_address, store_address, store_location, store_contact, bir_permit, biz_type, bir_tin, dti_sec, biz_permit, province, zip_code, logo_shape, logo_url, profile_photo FROM admin_extended WHERE admin_id = :id");
    $stmt2->bindParam(':id', $admin_id);
    $stmt2->execute();
    $extProfile = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $extProfile = [];
}

// Handle logo upload via AJAX (checked before rendering)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'upload_logo') {
    header('Content-Type: application/json');
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== 0) {
        echo json_encode(['success'=>false,'message'=>'No file uploaded']); exit;
    }
    $file    = $_FILES['logo'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit;
    }
    if ($file['size'] > 2*1024*1024) {
        echo json_encode(['success'=>false,'message'=>'File too large (max 2MB)']); exit;
    }
    $applyTo  = $_POST['apply_to'] ?? 'logo'; // avatar | logo | both
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fname    = 'logo_' . $admin_id . '_' . time() . '.' . $ext;
    $dest     = __DIR__ . '/../uploads/' . $fname;
    if (!is_dir(__DIR__ . '/../uploads/')) mkdir(__DIR__ . '/../uploads/', 0755, true);
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $url = '../uploads/' . $fname;
        try {
            // Ensure table + columns exist
            $conn->exec("CREATE TABLE IF NOT EXISTS admin_extended (
                admin_id INT PRIMARY KEY, age INT NULL, contact_number VARCHAR(30) NULL,
                home_address TEXT NULL, store_address TEXT NULL, store_location VARCHAR(100) NULL,
                store_contact VARCHAR(30) NULL, bir_permit VARCHAR(50) NULL,
                logo_shape ENUM('circle','square','rounded') DEFAULT 'circle',
                logo_url VARCHAR(500) NULL, profile_photo VARCHAR(500) NULL
            )");
            try { $conn->exec("ALTER TABLE admin_extended ADD COLUMN profile_photo VARCHAR(500) NULL"); } catch(Exception $ex) {}

            if ($applyTo === 'avatar') {
                $s = $conn->prepare("INSERT INTO admin_extended (admin_id, profile_photo) VALUES (:id,:url) ON DUPLICATE KEY UPDATE profile_photo=:url2");
                $s->execute([':id'=>$admin_id,':url'=>$url,':url2'=>$url]);
            } elseif ($applyTo === 'logo') {
                $s = $conn->prepare("INSERT INTO admin_extended (admin_id, logo_url) VALUES (:id,:url) ON DUPLICATE KEY UPDATE logo_url=:url2");
                $s->execute([':id'=>$admin_id,':url'=>$url,':url2'=>$url]);
            } else { // both
                $s = $conn->prepare("INSERT INTO admin_extended (admin_id, logo_url, profile_photo) VALUES (:id,:url,:url2) ON DUPLICATE KEY UPDATE logo_url=:url3, profile_photo=:url4");
                $s->execute([':id'=>$admin_id,':url'=>$url,':url2'=>$url,':url3'=>$url,':url4'=>$url]);
            }
            echo json_encode(['success'=>true,'url'=>$url,'apply_to'=>$applyTo]);
        } catch(Exception $e) {
            echo json_encode(['success'=>true,'url'=>$url,'db'=>$e->getMessage()]);
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'Upload failed']);
    }
    exit;
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

// Extended
$age          = $extProfile['age'] ?? '';
$contactNum   = $extProfile['contact_number'] ?? '';
$homeAddr     = $extProfile['home_address'] ?? '';
$storeAddr    = $extProfile['store_address'] ?? '';
$storeLoc     = $extProfile['store_location'] ?? '';
$storeContact = $extProfile['store_contact'] ?? '';
$birPermit    = $extProfile['bir_permit'] ?? '';
$logoShape    = $extProfile['logo_shape'] ?? 'circle';
$logoUrl      = $extProfile['logo_url'] ?? '';
$profilePhoto = $extProfile['profile_photo'] ?? '';
// TEMPORARY DEBUG — remove after fixing
echo '<!-- DEBUG: profilePhoto=[' . $profilePhoto . '] logoUrl=[' . $logoUrl . '] -->';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account — iPOS</title>
    <link rel="stylesheet" href="../design/admin.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* ===== ACCOUNT DASHBOARD STYLES ===== */
        .acct-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            max-width: 1060px;
            margin: 0 auto;
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
            font-size: 15px;
            color: var(--accent);
        }

        /* Profile Hero */
        .profile-hero {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 24px;
            background: linear-gradient(135deg, var(--accent-dark) 0%, var(--accent) 100%);
            border-radius: var(--radius-lg);
            margin: 0 auto 24px;
            color: white;
            max-width: 1060px;
        }
        .profile-hero-avatar {
            width: 80px; height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; font-weight: 800;
            border: 3px solid rgba(255,255,255,0.4);
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }
        .profile-hero-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-camera-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,0.45);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
            border-radius: 50%;
            font-size: 18px;
            color: white;
            gap: 2px;
        }
        .avatar-camera-overlay span { font-size: 9px; font-weight: 700; letter-spacing: 0.3px; }
        .profile-hero-avatar:hover .avatar-camera-overlay { opacity: 1; }
        .profile-hero-info h2 { font-size: 1.3rem; font-weight: 800; margin-bottom: 4px; }
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

        .store-section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--accent);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        select.acct-input { cursor: pointer; }

        /* Form fields in cards */
        .acct-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .acct-form-grid .full { grid-column: 1/-1; }
        .acct-field { display: flex; flex-direction: column; gap: 5px; }
        .acct-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .acct-label i { font-size: 11px; }
        .acct-input {
            padding: 10px 13px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text-primary);
            background: var(--body-bg);
            transition: border-color 0.2s;
            width: 100%;
            box-sizing: border-box;
        }
        .acct-input:focus {
            outline: none;
            border-color: var(--accent);
            background: white;
        }
        .acct-save-btn {
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
            margin-top: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .acct-save-btn:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Logo Upload */
        .logo-shape-row {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
        }
        .shape-opt {
            flex: 1;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            background: var(--body-bg);
        }
        .shape-opt:hover { border-color: var(--accent); }
        .shape-opt.selected { border-color: var(--accent); background: var(--accent-light); }
        .shape-preview {
            width: 44px; height: 44px;
            background: var(--accent);
            margin: 0 auto 8px;
        }
        .shape-preview.circle  { border-radius: 50%; }
        .shape-preview.square  { border-radius: 4px; }
        .shape-preview.rounded { border-radius: 14px; }
        .shape-opt-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
        .shape-opt.selected .shape-opt-label { color: var(--accent); }

        .logo-upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 28px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--body-bg);
            position: relative;
        }
        .logo-upload-zone:hover { border-color: var(--accent); background: var(--accent-light); }
        .logo-upload-zone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .logo-upload-preview {
            width: 80px; height: 80px;
            margin: 0 auto 12px;
            background: var(--accent-light);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }
        .logo-upload-preview img { width: 100%; height: 100%; object-fit: cover; }
        .logo-upload-preview .placeholder { font-size: 28px; }
        .logo-upload-text { font-size: 13px; color: var(--text-secondary); }
        .logo-upload-text strong { color: var(--accent); }

        /* Color Theme */
        .theme-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .theme-swatch {
            border-radius: 12px;
            padding: 10px 8px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.2s;
            text-align: center;
            position: relative;
            width: 80px;
        }
        .theme-swatch:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .theme-swatch.active { border-color: #333; box-shadow: 0 0 0 1px #333; }
        .theme-swatch .swatch-preview {
            height: 30px;
            border-radius: 6px;
            margin-bottom: 6px;
        }
        .theme-swatch .swatch-name { font-size: 10px; font-weight: 600; color: #444; }
        .theme-swatch .check-mark {
            position: absolute;
            top: 5px; right: 5px;
            width: 16px; height: 16px;
            background: #333;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 9px;
        }
        .theme-swatch.active .check-mark { display: flex; }
        .theme-swatch[data-theme="ipos"] .swatch-preview {
    background: linear-gradient(135deg, #5C0A2E, #BE185D);
}

        /* Theme grid */
        .theme-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .apply-theme-btn:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Password Change */
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
            width: 100%;
            box-sizing: border-box;
        }
        .pwd-form input:focus { outline: none; border-color: var(--accent); background: white; }
        .pwd-form label { font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; display: block; }
        .pwd-strength { height: 4px; background: var(--border-color); border-radius: 2px; overflow: hidden; margin-top: -8px; }
        .pwd-strength-bar { height: 100%; border-radius: 2px; transition: width 0.3s, background 0.3s; width: 0%; }
        .save-pwd-btn {
            padding: 12px; background: var(--accent); color: white; border: none;
            border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .save-pwd-btn:hover { background: var(--accent-dark); }

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

        /* Account Info tiles */
        .acct-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .info-tile { background: var(--body-bg); border-radius: 10px; padding: 14px; border: 1px solid var(--border-color); }
        .info-tile-label { font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .info-tile-value { font-size: 15px; font-weight: 700; color: var(--text-primary); }
    </style>
</head>
<body>
<div class="dashboard">

    <?php echo $sidebar->render('account'); ?>

        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h1><i class="fa-solid fa-user-circle" style="margin-right:8px;"></i>Account</h1>
                <p class="subtitle">Manage your profile, store info, and security settings</p>
            </div>
        </div>

        <!-- Profile Hero -->
        <div class="profile-hero">
            <div class="profile-hero-avatar" id="heroAvatar" onclick="document.getElementById('heroFileInput').click()" title="Click to change photo">
             <?php 
$heroPhoto = $profilePhoto ?: $logoUrl;
$heroPhotoUrl = $heroPhoto ? '../' . ltrim($heroPhoto, './') : '';
?>
<?php if ($heroPhotoUrl): ?>
  <img src="<?= htmlspecialchars($heroPhotoUrl) ?>" alt="Profile" id="heroAvatarImg" style="width:100%;height:100%;object-fit:cover;">
<?php else: ?>
    <span id="heroAvatarInitial"><?= htmlspecialchars($initial) ?></span>
<?php endif; ?>
                <div class="avatar-camera-overlay">
                    <i class="fa-solid fa-camera"></i>
                    <span>CHANGE</span>
                </div>
            </div>
            <input type="file" id="heroFileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" onchange="heroAvatarChange(this)">
            <div class="profile-hero-info">
                <h2><?= htmlspecialchars($adminName) ?></h2>
                <p><i class="fa-solid fa-at"></i> <?= htmlspecialchars($username) ?> &middot; <?= htmlspecialchars($email) ?></p>
                <p style="margin-top:4px;"><i class="fa-solid fa-store"></i> <?= htmlspecialchars($fastfood ?: 'No store set') ?></p>
            </div>
            <div class="profile-hero-badge"><i class="fa-solid fa-shield-halved"></i> Administrator</div>
        </div>

        <div class="acct-wrapper">

            <!-- Profile Information Card — photo LEFT, form RIGHT -->
            <div class="acct-card full-width">
                <div class="acct-card-title">
                    <div class="icon"><i class="fa-solid fa-user"></i></div>
                    Profile Information
                </div>
                <div style="display:flex; gap:28px; align-items:flex-start;">

                    <!-- LEFT: Photo Upload -->
                    <div style="display:flex; flex-direction:column; align-items:center; gap:10px; flex-shrink:0; width:130px;">
                        <div id="profileAvatarCircle" onclick="document.getElementById('profilePhotoInput').click()"
                             style="width:100px; height:100px; border-radius:50%;
                                    background:linear-gradient(135deg,var(--accent-dark),var(--accent));
                                    display:flex; align-items:center; justify-content:center;
                                    font-size:36px; font-weight:800; color:white;
                                    border:3px solid var(--border-color); overflow:hidden;
                                    cursor:pointer; position:relative;
                                    box-shadow:0 4px 16px rgba(0,0,0,0.12);">
                            <?php
                          $profilePhoto = $extProfile['profile_photo'] ?? '';
$displayPhoto = $profilePhoto ?: ($extProfile['logo_url'] ?? '');
$displayPhotoUrl = $displayPhoto ? '../' . ltrim($displayPhoto, './') : '';
if ($displayPhotoUrl): ?>
  <img src="<?= htmlspecialchars($displayPhotoUrl) ?>"
                                     style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <span id="profileAvatarInitial"><?= htmlspecialchars($initial) ?></span>
                            <?php endif; ?>
                            <div style="position:absolute;inset:0;background:rgba(0,0,0,0.45);
                                        border-radius:50%;display:flex;flex-direction:column;
                                        align-items:center;justify-content:center;color:white;
                                        font-size:20px;gap:2px;opacity:0;transition:opacity 0.2s;"
                                 onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=0">
                                <i class="fa-solid fa-camera"></i>
                                <span style="font-size:9px;font-weight:700;">CHANGE</span>
                            </div>
                        </div>
                        <input type="file" id="profilePhotoInput"
                               accept="image/jpeg,image/png,image/gif,image/webp"
                               style="display:none;" onchange="profilePhotoChange(this)">
                        <button onclick="document.getElementById('profilePhotoInput').click()"
                            style="width:100%;padding:7px 10px;background:var(--accent-light);
                                   color:var(--accent);border:1px dashed var(--accent);
                                   border-radius:8px;font-size:12px;font-weight:700;
                                   cursor:pointer;display:flex;align-items:center;
                                   justify-content:center;gap:5px;">
                            <i class="fa-solid fa-camera"></i> Change Photo
                        </button>
                        <span style="font-size:10px;color:var(--text-secondary);text-align:center;line-height:1.4;">
                            JPG, PNG, GIF<br>Max 2MB
                        </span>
                    </div>

                    <!-- RIGHT: Form Fields -->
                    <div style="flex:1; min-width:0;">
                        <div class="acct-form-grid">
                            <div class="acct-field full">
                                <label class="acct-label"><i class="fa-solid fa-id-card"></i> Full Name *</label>
                                <input class="acct-input" type="text" id="prof-fullname" value="<?= htmlspecialchars($adminName) ?>" placeholder="Full name">
                            </div>
                            <div class="acct-field">
                                <label class="acct-label"><i class="fa-solid fa-cake-candles"></i> Age</label>
                                <input class="acct-input" type="number" id="prof-age" value="<?= htmlspecialchars($age) ?>" placeholder="Age" min="1" max="120">
                            </div>
                            <div class="acct-field">
                                <label class="acct-label"><i class="fa-solid fa-phone"></i> Contact Number</label>
                                <input class="acct-input" type="text" id="prof-contact" value="<?= htmlspecialchars($contactNum) ?>" placeholder="09XXXXXXXXX">
                            </div>
                            <div class="acct-field full">
                                <label class="acct-label"><i class="fa-solid fa-envelope"></i> Email Address</label>
                                <input class="acct-input" type="email" id="prof-email" value="<?= htmlspecialchars($email) ?>" placeholder="Email address">
                            </div>
                            <div class="acct-field full">
                                <label class="acct-label"><i class="fa-solid fa-house"></i> Home Address</label>
                                <input class="acct-input" type="text" id="prof-homeaddr" value="<?= htmlspecialchars($homeAddr) ?>" placeholder="Home address">
                            </div>
                            <div class="acct-field full">
                                <label class="acct-label"><i class="fa-solid fa-tag"></i> Role</label>
                                <input class="acct-input" type="text" value="ADMIN" disabled style="opacity:0.6;cursor:not-allowed;">
                            </div>
                        </div>
                        <button class="acct-save-btn" onclick="saveProfileInfo()">
                            <i class="fa-solid fa-floppy-disk"></i> Save Profile
                        </button>
                    </div>

                </div>
            </div>

            <!-- Store Information Card -->
            <div class="acct-card full-width">
                <div class="acct-card-title">
                    <div class="icon"><i class="fa-solid fa-store"></i></div>
                    Store / Business Information
                </div>

                <!-- Business Info Section -->
                <div class="store-section-label"><i class="fa-solid fa-briefcase"></i> Business Information</div>
                <div class="acct-form-grid">
                    <div class="acct-field">
                        <label class="acct-label"><i class="fa-solid fa-store"></i> Business Name *</label>
                        <input class="acct-input" type="text" id="store-name" value="<?= htmlspecialchars($fastfood) ?>" placeholder="Business / Store name">
                    </div>
                    <div class="acct-field">
                        <label class="acct-label"><i class="fa-solid fa-tag"></i> Business Type</label>
                        <select class="acct-input" id="store-biz-type">
                            <?php
                            $bizTypes = ['Fast Food','Restaurant','Cafe','Bakery','Food Stall','Carinderia','Bar & Grill','Pizza','Milk Tea / Beverages','Other'];
                            $savedBizType = $extProfile['biz_type'] ?? '';
                            foreach ($bizTypes as $bt) {
                                $sel = ($savedBizType === $bt) ? 'selected' : '';
                                echo "<option value=\"$bt\" $sel>$bt</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="acct-field">
                        <label class="acct-label"><i class="fa-solid fa-file-invoice-dollar"></i> BIR TIN Number</label>
                        <input class="acct-input" type="text" id="store-bir-tin" value="<?= htmlspecialchars($extProfile['bir_tin'] ?? '') ?>" placeholder="000-000-000-0000">
                    </div>
                    <div class="acct-field">
                        <label class="acct-label"><i class="fa-solid fa-registered"></i> DTI / SEC Reg. Number</label>
                        <input class="acct-input" type="text" id="store-dti-sec" value="<?= htmlspecialchars($extProfile['dti_sec'] ?? '') ?>" placeholder="DTI or SEC Reg. Number">
                    </div>
                    <div class="acct-field full">
                        <label class="acct-label"><i class="fa-solid fa-file-shield"></i> Business Permit Number</label>
                        <input class="acct-input" type="text" id="store-biz-permit" value="<?= htmlspecialchars($extProfile['biz_permit'] ?? '') ?>" placeholder="Business Permit Number">
                    </div>
                </div>

                <!-- Business Location Section -->
                <div class="store-section-label" style="margin-top:20px;"><i class="fa-solid fa-location-dot"></i> Business Location</div>
                <div class="acct-form-grid">
                    <div class="acct-field full">
                        <label class="acct-label"><i class="fa-solid fa-map-pin"></i> Complete Address (Street, Barangay)</label>
                        <input class="acct-input" type="text" id="store-address" value="<?= htmlspecialchars($storeAddr) ?>" placeholder="Street, Barangay">
                    </div>
                    <div class="acct-field">
                        <label class="acct-label"><i class="fa-solid fa-map"></i> Province</label>
                        <input class="acct-input" type="text" id="store-province" value="<?= htmlspecialchars($extProfile['province'] ?? '') ?>" placeholder="Province">
                    </div>
                    <div class="acct-field">
                        <label class="acct-label"><i class="fa-solid fa-city"></i> City / Municipality</label>
                        <input class="acct-input" type="text" id="store-city" value="<?= htmlspecialchars($storeLoc) ?>" placeholder="City / Municipality">
                    </div>
                    <div class="acct-field">
                        <label class="acct-label"><i class="fa-solid fa-envelopes-bulk"></i> ZIP Code</label>
                        <input class="acct-input" type="text" id="store-zip" value="<?= htmlspecialchars($extProfile['zip_code'] ?? '') ?>" placeholder="ZIP Code" maxlength="10">
                    </div>
                    <div class="acct-field">
                        <label class="acct-label"><i class="fa-solid fa-phone-flip"></i> Contact Number</label>
                        <input class="acct-input" type="text" id="store-contact" value="<?= htmlspecialchars($storeContact) ?>" placeholder="Store contact number">
                    </div>
                </div>

                <!-- Store ID & Registered -->
                <div style="border-top: 1px solid var(--border-color); margin: 20px 0 14px; padding-top:14px; display:flex; gap:32px; font-size:13px; flex-wrap:wrap;">
                    <span><span style="color:var(--text-secondary); font-weight:600;">Store ID</span> &nbsp;<strong style="color:var(--accent);">#<?= (int)$admin_id ?></strong></span>
                    <span><span style="color:var(--text-secondary); font-weight:600;">Registered</span> &nbsp;<strong>May 10, 2026</strong></span>
                </div>

                <button class="acct-save-btn" onclick="saveStoreInfo()">
                    <i class="fa-solid fa-floppy-disk"></i> Update Store
                </button>
            </div>

            <!-- Logo / Profile Picture Card -->
            <div class="acct-card">
                <div class="acct-card-title">
                    <div class="icon"><i class="fa-solid fa-image"></i></div>
                    Business Logo / Profile Picture
                </div>

                <p style="font-size:12px; color:var(--text-secondary); margin-bottom:14px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px;">Step 1: Choose a shape for your logo</p>
                <div class="logo-shape-row">
                    <div class="shape-opt <?= $logoShape==='circle'?'selected':'' ?>" onclick="selectShape('circle', this)">
                        <div class="shape-preview circle"></div>
                        <div class="shape-opt-label">Circle</div>
                    </div>
                    <div class="shape-opt <?= $logoShape==='square'?'selected':'' ?>" onclick="selectShape('square', this)">
                        <div class="shape-preview square"></div>
                        <div class="shape-opt-label">Square</div>
                    </div>
                    <div class="shape-opt <?= $logoShape==='rounded'?'selected':'' ?>" onclick="selectShape('rounded', this)">
                        <div class="shape-preview rounded"></div>
                        <div class="shape-opt-label">Rounded</div>
                    </div>
                </div>

                <p style="font-size:12px; color:var(--text-secondary); margin-bottom:10px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px;">Step 2: Upload your logo</p>
                <div class="logo-upload-zone" id="uploadZone" onclick="document.getElementById('logoFileInput').click()">
                    <div class="logo-upload-preview" id="logoPreview"
                         style="border-radius: <?= $logoShape==='circle'?'50%':($logoShape==='rounded'?'14px':'4px') ?>;">
                        <?php if ($logoUrl): ?>
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" id="logoPreviewImg">
                        <?php else: ?>
                            <div class="placeholder"><i class="fa-solid fa-camera fa-lg" style="color:var(--text-secondary);"></i></div>
                        <?php endif; ?>
                    </div>
                    <input type="file" id="logoFileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" onchange="previewLogo(this)">
                    <div class="logo-upload-text">
                        <strong>Click to upload</strong> your business logo<br>
                        <small style="color:var(--text-secondary);">JPG, PNG, GIF, WEBP · Max 2MB · Recommended 500×500px</small>
                    </div>
                </div>

                <button class="acct-save-btn" style="margin-top:14px;" onclick="uploadLogo()">
                    <i class="fa-solid fa-upload"></i> Upload Photo
                </button>
            </div>

            <!-- Color Theme Card -->
            <div class="acct-card">
                <div class="acct-card-title">
                    <div class="icon"><i class="fa-solid fa-palette"></i></div>
                    Theme Color
                </div>
                <p style="font-size:13px; color:var(--text-secondary); margin-bottom:18px;">This color applies to your store's dashboard.</p>

                <?php
                $presets = [
                     'ipos'    => ['dark'=>'#5C0A2E','accent'=>'#BE185D','bg'=>'#FDF2F8','text'=>'#1a0010','label'=>'iPOS Default'],
                    'black'   => ['dark'=>'#1f2937','accent'=>'#374151','bg'=>'#f9fafb','text'=>'#111827','label'=>'Black'],
                    'gold'    => ['dark'=>'#92400e','accent'=>'#d97706','bg'=>'#fffbeb','text'=>'#451a03','label'=>'Gold'],
                    'silver'  => ['dark'=>'#475569','accent'=>'#64748b','bg'=>'#f8fafc','text'=>'#1e293b','label'=>'Silver'],
                    'red'     => ['dark'=>'#991b1b','accent'=>'#ef4444','bg'=>'#fef2f2','text'=>'#450a0a','label'=>'Red'],
                    'orange'  => ['dark'=>'#9a3412','accent'=>'#f97316','bg'=>'#fff7ed','text'=>'#431407','label'=>'Orange'],
                    'yellow'  => ['dark'=>'#854d0e','accent'=>'#eab308','bg'=>'#fefce8','text'=>'#422006','label'=>'Yellow'],
                    'green'   => ['dark'=>'#166534','accent'=>'#22c55e','bg'=>'#f0fdf4','text'=>'#052e16','label'=>'Green'],
                    'blue'    => ['dark'=>'#1e40af','accent'=>'#3b82f6','bg'=>'#eff6ff','text'=>'#172554','label'=>'Blue'],
                    'violet'  => ['dark'=>'#5b21b6','accent'=>'#8b5cf6','bg'=>'#f5f3ff','text'=>'#2e1065','label'=>'Violet'],
                ];
              $savedAccent = strtolower($ac ?? '');
$activeTheme = 'ipos';
                foreach ($presets as $name => $p) {
                    if (strtolower($p['accent']) === $savedAccent || strtolower($p['dark']) === $savedAccent) {
                        $activeTheme = $name;
                        break;
                    }
                }
                ?>
                <div class="theme-grid">
                    <?php foreach ($presets as $name => $p):
                        $isActive = ($name === $activeTheme);
                        $gradStyle = "background: linear-gradient(135deg,{$p['dark']},{$p['accent']});";
                    ?>
                    <div class="theme-swatch <?= $isActive?'active':'' ?>" data-theme="<?= $name ?>"
                         onclick="selectTheme(this,'<?= $p['dark'] ?>','<?= $p['accent'] ?>','<?= $p['bg'] ?>','<?= $p['text'] ?>')">
                        <div class="check-mark">✓</div>
                        <div class="swatch-preview" style="<?= $gradStyle ?>"></div>
                        <div class="swatch-name"><?= $p['label'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button class="apply-theme-btn" onclick="applyTheme()">
                    <i class="fa-solid fa-paint-roller"></i> Apply Theme
                </button>
            </div>

            <!-- Password Change Card -->
            <!-- Password & PIN — side by side -->
            <div class="acct-card full-width">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:32px;">

                    <!-- Change Password -->
                    <div>
                        <div class="acct-card-title" style="margin-bottom:18px;">
                            <div class="icon"><i class="fa-solid fa-lock"></i></div>
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
                            <button class="save-pwd-btn" onclick="changePassword()">
                                <i class="fa-solid fa-lock"></i> Update Password
                            </button>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div style="border-left:1px solid var(--border-color); padding-left:32px;">
                        <div class="acct-card-title" style="margin-bottom:8px;">
                            <div class="icon"><i class="fa-solid fa-key"></i></div>
                            Dashboard PIN
                        </div>
                        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:16px;">
                            Protects access to Account &amp; Kitchen dashboards. Default is <strong>0000</strong>.
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
                            <button class="save-pwd-btn" onclick="changeDashboardPin()">
                                <i class="fa-solid fa-shield-halved"></i> Update Dashboard PIN
                            </button>
                        </div>
                    </div>

                </div>
            </div>

       </div><!-- end acct-wrapper -->

    <?php echo $sidebar->renderClose(); ?>
</div>

<!-- WHERE TO APPLY PHOTO MODAL -->
<div id="whereModal" style="
    position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px);
    z-index:9000; display:none; align-items:center; justify-content:center;">
    <div style="
        background:white; border-radius:20px; padding:28px; width:360px; max-width:95vw;
        box-shadow:0 24px 60px rgba(0,0,0,0.2); animation:modalPop 0.2s ease; text-align:center;">
        <div style="font-size:40px; margin-bottom:8px;">📸</div>
        <h3 style="font-size:17px; font-weight:800; margin-bottom:6px; color:#111;">Apply photo to…</h3>
        <p style="font-size:13px; color:#888; margin-bottom:20px;">Where should this image be used?</p>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <button onclick="applyHeroPhoto('avatar')" style="
                padding:12px; background:linear-gradient(135deg,var(--accent-dark),var(--accent));
                color:white; border:none; border-radius:10px; font-size:14px; font-weight:700;
                cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
                <i class='fa-solid fa-circle-user'></i> Profile Avatar only
            </button>
            <button onclick="applyHeroPhoto('logo')" style="
                padding:12px; background:linear-gradient(135deg,#1e40af,#3b82f6);
                color:white; border:none; border-radius:10px; font-size:14px; font-weight:700;
                cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
                <i class='fa-solid fa-store'></i> Business Logo only
            </button>
            <button onclick="applyHeroPhoto('both')" style="
                padding:12px; background:linear-gradient(135deg,#166534,#22c55e);
                color:white; border:none; border-radius:10px; font-size:14px; font-weight:700;
                cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
                <i class='fa-solid fa-check-double'></i> Both
            </button>
            <button onclick="document.getElementById('whereModal').style.display='none'" style="
                padding:10px; background:#f3f4f6; color:#666; border:none; border-radius:10px;
                font-size:13px; font-weight:600; cursor:pointer; margin-top:4px;">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Pin Modals (required by sidebar) -->
<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

<script>
// ===== HERO AVATAR CLICK — CHOOSE WHERE =====
let heroPendingFile = null;
let heroPendingDataUrl = null;

function heroAvatarChange(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (file.size > 2*1024*1024) { showToastAcct('File too large (max 2MB)', 'error'); return; }
    heroPendingFile = file;
    const reader = new FileReader();
    reader.onload = e => {
        heroPendingDataUrl = e.target.result;
        // Show the where-to-apply modal
        document.getElementById('whereModal').style.display = 'flex';
    };
    reader.readAsDataURL(file);
    // Reset so same file can be re-selected
    input.value = '';
}

function applyHeroPhoto(target) {
    document.getElementById('whereModal').style.display = 'none';
    if (!heroPendingDataUrl) return;

    // Apply preview immediately
    if (target === 'avatar' || target === 'both') {
        const av = document.getElementById('heroAvatar');
        const existing = av.querySelector('img');
        if (existing) { existing.src = heroPendingDataUrl; }
        else {
            const initial = av.querySelector('#heroAvatarInitial');
            if (initial) initial.remove();
            const img = document.createElement('img');
            img.src = heroPendingDataUrl;
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
            av.insertBefore(img, av.querySelector('.avatar-camera-overlay'));
        }
    }
    if (target === 'logo' || target === 'both') {
        const prev = document.getElementById('logoPreview');
        if (prev) {
            prev.innerHTML = `<img src="${heroPendingDataUrl}" style="width:100%;height:100%;object-fit:cover;">`;
        }
    }

    // Upload to server
    const fd = new FormData();
    fd.append('logo', heroPendingFile);
    fd.append('_action', 'upload_logo');
    fd.append('logo_shape', currentShape || 'circle');
    fd.append('apply_to', target);

    fetch('account_dashboard.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) showToastAcct('Photo saved! 🖼️', 'success');
            else showToastAcct(data.message || 'Upload failed', 'error');
        })
        .catch(() => showToastAcct('Upload failed', 'error'));
}

// ===== PROFILE PHOTO CHANGE (left side of Profile card) =====
function profilePhotoChange(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (file.size > 2*1024*1024) { showToastAcct('File too large (max 2MB)', 'error'); return; }
    const reader = new FileReader();
    reader.onload = e => {
        // Update circle preview
        const circle = document.getElementById('profileAvatarCircle');
        const existing = circle.querySelector('img');
        if (existing) { existing.src = e.target.result; }
        else {
            const span = circle.querySelector('span');
            if (span) span.remove();
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;position:relative;z-index:1;';
            circle.insertBefore(img, circle.firstChild);
        }

        // Update hero avatar in banner
        const heroAv = document.getElementById('heroAvatar');
        if (heroAv) {
            const hImg = heroAv.querySelector('img');
            if (hImg) hImg.src = e.target.result;
            else {
                const hSpan = heroAv.querySelector('span');
                if (hSpan) hSpan.remove();
                const ni = document.createElement('img');
                ni.src = e.target.result;
                ni.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                heroAv.insertBefore(ni, heroAv.querySelector('.avatar-camera-overlay'));
            }
        }

        // Update sidebar profile photo
        const sidebarAvatarImg = document.querySelector('.sidebar .user-avatar img, .sidebar .admin-avatar img');
        const sidebarAvatarWrap = document.querySelector('.sidebar .user-avatar, .sidebar .admin-avatar');
        if (sidebarAvatarImg) {
            sidebarAvatarImg.src = e.target.result;
        } else if (sidebarAvatarWrap) {
            sidebarAvatarWrap.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
        }

        // Update topbar/header photo
        const topbarAvatarImg = document.querySelector('.topbar .user-avatar img, .topbar img[alt="logo"]');
        if (topbarAvatarImg) {
            topbarAvatarImg.src = e.target.result;
        }

        // Upload to server then reload so sidebar refreshes properly
        const fd = new FormData();
        fd.append('logo', file);
        fd.append('_action', 'upload_logo');
        fd.append('apply_to', 'avatar');
        fd.append('logo_shape', currentShape || 'circle');
        fetch('account_dashboard.php', { method:'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToastAcct('Profile photo updated! 📸', 'success');
                    setTimeout(() => location.reload(), 1200);
                }
                else showToastAcct(data.message || 'Upload failed', 'error');
            })
            .catch(() => showToastAcct('Upload failed', 'error'));
    };
    reader.readAsDataURL(file);
    input.value = '';
}
// ===== LOGO SHAPE =====
let currentShape = '<?= $logoShape ?>';
function selectShape(shape, el) {
    currentShape = shape;
    document.querySelectorAll('.shape-opt').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    const radii = { circle: '50%', square: '4px', rounded: '14px' };
    document.getElementById('logoPreview').style.borderRadius = radii[shape];
}

// ===== LOGO PREVIEW =====
let selectedLogoFile = null;
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    selectedLogoFile = input.files[0];
    if (selectedLogoFile.size > 2*1024*1024) {
        showToastAcct('File too large (max 2MB)', 'error');
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        const prev = document.getElementById('logoPreview');
        prev.innerHTML = `<img src="${e.target.result}" alt="Logo" style="width:100%;height:100%;object-fit:cover;">`;
        // Update hero avatar
        document.getElementById('heroAvatar').innerHTML = `<img src="${e.target.result}" alt="Logo" style="width:100%;height:100%;object-fit:cover;">`;
    };
    reader.readAsDataURL(selectedLogoFile);
}

function uploadLogo() {
    if (!selectedLogoFile) {
        showToastAcct('Please select an image first', 'info');
        return;
    }
    const fd = new FormData();
    fd.append('logo', selectedLogoFile);
    fd.append('_action', 'upload_logo');
    fd.append('logo_shape', currentShape);
    fetch('account_dashboard.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) showToastAcct('Logo uploaded! 🖼️', 'success');
            else showToastAcct(data.message || 'Upload failed', 'error');
        })
        .catch(() => showToastAcct('Upload failed', 'error'));
}

// ===== SAVE PROFILE =====
function saveProfileInfo() {
    const fullname    = document.getElementById('prof-fullname').value.trim();
    const age         = document.getElementById('prof-age').value.trim();
    const contact     = document.getElementById('prof-contact').value.trim();
    const email       = document.getElementById('prof-email').value.trim();
    const homeaddr    = document.getElementById('prof-homeaddr').value.trim();
    if (!fullname) { showToastAcct('Full name is required', 'error'); return; }

    const params = new URLSearchParams({ fullname, age, contact_number: contact, email, home_address: homeaddr });
    fetch('helpers/account_helpers.php?action=save_profile_ext', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) showToastAcct('Profile saved! ✅', 'success');
        else showToastAcct(data.message || 'Save failed', 'error');
    })
    .catch(() => showToastAcct('Connection error', 'error'));
}

// ===== SAVE STORE =====
function saveStoreInfo() {
    const storeName   = document.getElementById('store-name').value.trim();
    const bizType     = document.getElementById('store-biz-type').value;
    const birTin      = document.getElementById('store-bir-tin').value.trim();
    const dtiSec      = document.getElementById('store-dti-sec').value.trim();
    const bizPermit   = document.getElementById('store-biz-permit').value.trim();
    const storeAddr   = document.getElementById('store-address').value.trim();
    const province    = document.getElementById('store-province').value.trim();
    const city        = document.getElementById('store-city').value.trim();
    const zipCode     = document.getElementById('store-zip').value.trim();
    const storeContact= document.getElementById('store-contact').value.trim();
    if (!storeName) { showToastAcct('Business name is required', 'error'); return; }

    const params = new URLSearchParams({
        fastfood_name: storeName,
        biz_type: bizType,
        bir_tin: birTin,
        dti_sec: dtiSec,
        biz_permit: bizPermit,
        store_address: storeAddr,
        province: province,
        store_location: city,
        zip_code: zipCode,
        store_contact: storeContact
    });
    fetch('helpers/account_helpers.php?action=save_store_ext', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) showToastAcct('Store updated! 🏪', 'success');
        else showToastAcct(data.message || 'Save failed', 'error');
    })
    .catch(() => showToastAcct('Connection error', 'error'));
}

// ===== THEME SELECTION =====
function selectTheme(el, dark, accent, bodyBg, text) {
    document.querySelectorAll('.theme-swatch').forEach(s => s.classList.remove('active'));
    el.classList.add('active');
    const sidebarDark = darkenHex(dark, 0.3);
    livePreview({ sidebarBg: sidebarDark, accent, accentDark: dark, bodyBg, text });
}

function livePreview(p) {
    const r = document.documentElement;
    r.style.setProperty('--sidebar-bg',        p.sidebarBg);
    r.style.setProperty('--accent',            p.accent);
    r.style.setProperty('--accent-dark',       p.accentDark);
    r.style.setProperty('--accent-light',      p.accentLight || p.bodyBg);
    r.style.setProperty('--body-bg',           p.bodyBg);
    r.style.setProperty('--text-primary',      p.text);
    r.style.setProperty('--sidebar-active-bg', p.accentDark);
}

function applyTheme() {
    const active = document.querySelector('.theme-swatch.active');
    if (!active) { showToastAcct('Select a theme first', 'info'); return; }
   const dark        = getComputedStyle(document.documentElement).getPropertyValue('--accent-dark').trim();
const sidebarBg   = getComputedStyle(document.documentElement).getPropertyValue('--sidebar-bg').trim();
const accent      = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim();
const bodyBg      = getComputedStyle(document.documentElement).getPropertyValue('--body-bg').trim();
const text        = getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim();
const accentLight = bodyBg;
const borderColor = lightenHex(dark, 0.85);
const textSec     = lightenHex(dark, 0.4);
const palette = { sidebarBg, accent, accentDark: dark, bodyBg, text, accentLight, borderColor, textSec };
    fetch('helpers/account_helpers.php?action=save_theme', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(palette)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) showToastAcct('Theme saved! 🎨', 'success');
        else showToastAcct('Theme applied locally', 'info');
    })
    .catch(() => showToastAcct('Theme applied locally only', 'info'));
}

function lightenHex(hex, amount) {
    try {
        let r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
        r = Math.round(r + (255-r)*amount);
        g = Math.round(g + (255-g)*amount);
        b = Math.round(b + (255-b)*amount);
        return '#' + [r,g,b].map(x=>x.toString(16).padStart(2,'0')).join('');
    } catch(e) { return hex; }
}
function darkenHex(hex, amount) {
    try {
        let r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
        r = Math.round(r * (1 - amount));
        g = Math.round(g * (1 - amount));
        b = Math.round(b * (1 - amount));
        return '#' + [r,g,b].map(x=>x.toString(16).padStart(2,'0')).join('');
    } catch(e) { return hex; }
}

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
    if (newPwd.length < 6)  { showToastAcct('Password too short (min 6)', 'error'); return; }
    fetch('helpers/account_helpers.php?action=change_password', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `current_password=${encodeURIComponent(curr)}&new_password=${encodeURIComponent(newPwd)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToastAcct('Password updated! 🔒', 'success');
            ['current-pwd','new-pwd','confirm-pwd'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('strengthBar').style.width = '0';
            document.getElementById('strengthLabel').textContent = '';
        } else {
            showToastAcct(data.message || 'Failed to update', 'error');
        }
    })
    .catch(() => showToastAcct('Connection error', 'error'));
}

// ===== DASHBOARD PIN =====
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
            ['cur-pin','new-pin','confirm-pin'].forEach(id => document.getElementById(id).value = '');
        } else {
            showToastAcct(data.message || 'Failed to update PIN', 'error');
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

// PIN modal support
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
<script>
/* ── SIDEBAR TOGGLE ── */
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const main    = document.getElementById('mainContent');
    if (!sidebar) return;
    const collapsed = sidebar.classList.toggle('sidebar-collapsed');
    if (main) main.classList.toggle('main-expanded', collapsed);
    localStorage.setItem('ipos_sidebar_collapsed', collapsed ? '1' : '0');
}

/* Restore sidebar state on page load */
document.addEventListener('DOMContentLoaded', function () {
    const collapsed = localStorage.getItem('ipos_sidebar_collapsed') === '1';
    if (collapsed) {
        const sidebar = document.querySelector('.sidebar');
        const main    = document.getElementById('mainContent');
        if (sidebar) sidebar.classList.add('sidebar-collapsed');
        if (main)    main.classList.add('main-expanded');
    }
});
</script>

</body>
</html>
