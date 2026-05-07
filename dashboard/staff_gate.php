<?php
session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/helpers/admindashboard_helpers.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id    = $_SESSION['admin_id'];
$db          = new Database();
$conn        = $db->connect();
$pin_manager = new PINManager($admin_id);

if (!$pin_manager->hasPIN()) {
    $pin_manager->savePIN('0000');
}

if (!empty($_SESSION['staff_gate_unlocked']) && (time() - $_SESSION['staff_gate_unlocked']) < 1800) {
    header("Location: staff_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    header('Content-Type: application/json');
    if ($pin_manager->verifyPIN(trim($_POST['pin']))) {
        $_SESSION['staff_gate_unlocked'] = time();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
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
</head>
<body>
<div class="dashboard">
    <?= $sidebar->render('staff_gate') ?>
    <main class="main" style="filter:blur(3px);pointer-events:none;user-select:none;">
        <div class="topbar">
            <div><h1>👥 Staff Dashboard</h1><p class="subtitle">Cashier &amp; Kitchen access</p></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
            <div style="background:var(--card-bg);border-radius:var(--radius-lg);height:280px;border:1px solid var(--border-color);"></div>
            <div style="background:var(--card-bg);border-radius:var(--radius-lg);height:280px;border:1px solid var(--border-color);"></div>
        </div>
    </main>
</div>

<div id="pinOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);z-index:9000;display:flex;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:24px;padding:36px 32px;width:340px;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,0.3);animation:pinPop 0.3s ease;">
        <div style="font-size:44px;margin-bottom:10px;">👥</div>
        <h2 style="font-size:1.2rem;font-weight:800;color:#1a1a2e;margin-bottom:6px;">Switch to Staff Dashboard</h2>
        <p style="font-size:13px;color:#888;margin-bottom:22px;">Enter your admin PIN to continue.<br>Default PIN is <strong>0000</strong>.</p>
        <div id="gateDots" style="display:flex;justify-content:center;gap:14px;margin-bottom:22px;">
            <div class="pin-dot"></div><div class="pin-dot"></div>
            <div class="pin-dot"></div><div class="pin-dot"></div>
        </div>
        <div class="pin-pad">
            <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k): ?>
                <button type="button" class="pin-key"
                    onclick="<?= $k==='⌫' ? 'gBackspace()' : ($k==='' ? '' : "gPress($k)") ?>">
                    <?= $k ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="gateError" style="color:#dc2626;font-size:13px;margin-top:12px;display:none;">Incorrect PIN. Try again.</p>
        <a href="helpers/clear_gate.php?gate=staff&redirect=admindashboard.php" style="display:inline-block;margin-top:16px;font-size:12px;color:var(--accent);font-weight:600;text-decoration:none;">&#8592; Back to Dashboard</a>
    </div>
</div>

<style>@keyframes pinPop{from{transform:scale(0.85);opacity:0}to{transform:scale(1);opacity:1}}</style>
<?php include __DIR__ . '/helpers/pin_modals.php'; ?>
<script>
let gPin='';
function gPress(num){if(gPin.length>=4)return;gPin+=String(num);updDots(gPin.length);if(gPin.length===4)setTimeout(gVerify,100);}
function gBackspace(){gPin=gPin.slice(0,-1);updDots(gPin.length);}
function updDots(n){document.querySelectorAll('#gateDots .pin-dot').forEach((d,i)=>d.classList.toggle('filled',i<n));}
function gVerify(){
    fetch('staff_gate.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'pin='+encodeURIComponent(gPin)})
    .then(r=>r.json()).then(data=>{
        if(data.success){window.location.href='staff_login.php';}
        else{
            document.getElementById('gateError').style.display='block';
            gPin='';updDots(0);
            const d=document.getElementById('gateDots');
            d.classList.add('pin-shake');setTimeout(()=>d.classList.remove('pin-shake'),500);
        }
    });
}
document.addEventListener('keydown',e=>{
    if(e.key>='0'&&e.key<='9')gPress(parseInt(e.key));
    if(e.key==='Backspace')gBackspace();
});
</script>
</body>
</html>