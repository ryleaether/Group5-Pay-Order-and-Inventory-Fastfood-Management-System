<?php
session_start();
require_once __DIR__ . "/helpers/admindashboard_helpers.php";
require_once __DIR__ . "/../validation.php";
require_once __DIR__ . "/../config/database.php";
$__ah = __DIR__ . '/../config/audit_helper.php';
if (file_exists($__ah)) { require_once $__ah; }
elseif (!function_exists('audit_log')) { function audit_log() {} }

if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
$val = new Validation();
if (!$val->adminExists($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }

$db       = new Database();
$conn     = $db->connect();
$admin_id = (int)$_SESSION['admin_id'];

// Ensure soft-delete tables
foreach (["CREATE TABLE IF NOT EXISTS deleted_menu_items (id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL, admin_id INT NOT NULL, item_name VARCHAR(255) NOT NULL, description TEXT, price DECIMAL(10,2) NOT NULL DEFAULT 0, stock_quantity INT NOT NULL DEFAULT 0, category VARCHAR(100), is_available TINYINT(1) DEFAULT 1, image_url VARCHAR(500), original_created_at DATETIME, deleted_by VARCHAR(100), deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_adm (admin_id, deleted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
          "CREATE TABLE IF NOT EXISTS deleted_staffs (id INT AUTO_INCREMENT PRIMARY KEY, staff_id INT NOT NULL, admin_id INT NOT NULL, fullname VARCHAR(255) NOT NULL, role VARCHAR(50), status VARCHAR(50), shift_start TIME, shift_end TIME, employment_type VARCHAR(50) NOT NULL DEFAULT 'Full-time', original_created_at DATETIME, deleted_by VARCHAR(100), deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_adm (admin_id, deleted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"] as $sql) {
    try { $conn->exec($sql); } catch(Exception $e) {}
}

// Counts
$trashed_items = $trashed_staff = $menu_count = $staff_count = 0;
try { $trashed_items = (int)$conn->query("SELECT COUNT(*) FROM deleted_menu_items WHERE admin_id={$admin_id}")->fetchColumn(); } catch(Exception $e){}
try { $trashed_staff = (int)$conn->query("SELECT COUNT(*) FROM deleted_staffs WHERE admin_id={$admin_id}")->fetchColumn(); } catch(Exception $e){}
try { $menu_count = (int)$conn->query("SELECT COUNT(*) FROM menu_items WHERE admin_id={$admin_id}")->fetchColumn(); } catch(Exception $e){}
try { $staff_count = (int)$conn->query("SELECT COUNT(*) FROM staffs WHERE admin_id={$admin_id}")->fetchColumn(); } catch(Exception $e){}

$adminProfile = [];
try {
    $s = $conn->prepare("SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id=:id");
    $s->execute([':id'=>$admin_id]);
    $adminProfile = $s->fetch(PDO::FETCH_ASSOC) ?: [];
} catch(Exception $e){}

$sidebar   = new SidebarRenderer($admin_id,
    $_SESSION['fastfood_name']    ?? '',
    $adminProfile['fullname']     ?? $_SESSION['username'] ?? '',
    $adminProfile['username']     ?? $_SESSION['username'] ?? '',
    $adminProfile['email']        ?? '');
$storeName = htmlspecialchars($adminProfile['fastfood_name'] ?? $_SESSION['fastfood_name'] ?? 'Store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Backup &amp; Restore — iPOS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../design/admin.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php $__tl = __DIR__.'/helpers/theme_loader.php'; if(file_exists($__tl)) include $__tl; ?>
<style>
.br-wrap { width:100%; padding:0 0 60px; box-sizing:border-box; }

@media (max-width: 1200px) {
    .br-wrap { padding:0 16px 60px; }
    .stat-grid { grid-template-columns:repeat(2,1fr); }
    .check-grid { grid-template-columns:repeat(3,1fr); }
}
@media (max-width: 900px) {
    .br-hero { flex-direction:column; align-items:flex-start; gap:10px; padding:20px; }
    .stat-grid { grid-template-columns:repeat(2,1fr); }
    .check-grid { grid-template-columns:repeat(2,1fr); }
    .rev-grid { grid-template-columns:repeat(2,1fr); }
    .br-tabs { overflow-x:auto; white-space:nowrap; }
    .br-tab { padding:10px 16px; font-size:12px; }
}
@media (max-width: 600px) {
    .stat-grid { grid-template-columns:1fr 1fr; }
    .check-grid { grid-template-columns:1fr 1fr; }
    .rev-grid { grid-template-columns:1fr 1fr; }
    .br-hero h1 { font-size:16px; }
    .pill { padding:5px 10px; font-size:11px; }
    .btn { padding:8px 12px; font-size:12px; }
    .trash-tabs { flex-direction:column; }
}

/* Hero */
.br-hero { background:linear-gradient(135deg,var(--accent-dark),var(--accent)); border-radius:16px; padding:28px 32px; margin-bottom:22px; color:#fff; display:flex; align-items:center; gap:16px; }
.br-hero-icon { font-size:32px; opacity:.9; }
.br-hero h1 { font-size:20px; font-weight:800; margin:0 0 3px; }
.br-hero p  { font-size:12px; opacity:.8; margin:0; }

/* Tabs */
.br-tabs { display:flex; border-bottom:2px solid var(--border-color); margin-bottom:24px; gap:0; }
.br-tab  { padding:12px 24px; border:none; background:none; color:var(--text-secondary); font-size:13px; font-weight:600; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; display:flex; align-items:center; gap:8px; transition:all .2s; }
.br-tab:hover  { color:var(--accent); }
.br-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.tab-badge { background:var(--accent); color:#fff; font-size:10px; padding:2px 7px; border-radius:99px; font-weight:700; }

/* Cards */
.br-card { background:var(--card-bg); border:1px solid var(--border-color); border-radius:14px; padding:24px; margin-bottom:16px; }
.br-card-title { font-size:15px; font-weight:700; color:var(--text-primary); margin-bottom:5px; display:flex; align-items:center; gap:8px; }
.br-card-sub   { font-size:12px; color:var(--text-secondary); margin-bottom:16px; }

/* Stats grid */
.stat-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:12px; margin-bottom:18px; }
.stat-card { background:var(--card-bg); border:1.5px solid var(--border-color); border-radius:12px; padding:18px 20px; display:flex; align-items:center; gap:14px; }
.stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.stat-val  { font-size:22px; font-weight:800; color:var(--text-primary); line-height:1; }
.stat-lbl  { font-size:11px; color:var(--text-secondary); margin-top:2px; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }

/* Buttons */
.btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; border:none; transition:all .2s; white-space:nowrap; }
.btn:disabled { opacity:.55; cursor:not-allowed; }
.btn-primary { background:var(--accent); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--accent-dark); }
.btn-green   { background:#16a34a; color:#fff; }
.btn-green:hover:not(:disabled) { background:#15803d; }
.btn-outline { background:transparent; border:1.5px solid var(--border-color); color:var(--text-primary); }
.btn-outline:hover:not(:disabled) { border-color:var(--accent); color:var(--accent); }
.btn-danger  { background:#dc2626; color:#fff; }
.btn-danger:hover:not(:disabled) { background:#b91c1c; }
.btn-sm { padding:6px 11px; font-size:11px; border-radius:7px; }

/* Notices */
.notice { padding:11px 16px; border-radius:10px; font-size:12px; margin-bottom:14px; display:flex; align-items:center; gap:10px; }
.notice-green { background:#f0fdf4; border:1px solid #bbf7d0; color:#14532d; }
.notice-amber { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
.notice-blue  { background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; }

/* Filter pills */
.pill-row { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:12px; align-items:center; }
.pill { padding:6px 14px; border-radius:20px; border:1.5px solid var(--border-color); background:var(--card-bg); color:var(--text-secondary); font-size:12px; font-weight:600; cursor:pointer; transition:all .2s; }
.pill:hover,.pill.active { background:var(--accent); color:#fff; border-color:var(--accent); }

/* Date input */
.date-input { border:1.5px solid var(--border-color); border-radius:8px; padding:7px 11px; font-size:12px; color:var(--text-primary); background:var(--card-bg); outline:none; }
.date-input:focus { border-color:var(--accent); }

/* Backup table */
.br-table-wrap { overflow-x:auto; }
.br-table { width:100%; border-collapse:collapse; font-size:12px; table-layout:fixed; }
.br-table colgroup col:nth-child(1) { width:8%;  }
.br-table colgroup col:nth-child(2) { width:27%; }
.br-table colgroup col:nth-child(3) { width:11%; }
.br-table colgroup col:nth-child(4) { width:7%;  }
.br-table colgroup col:nth-child(5) { width:6%;  }
.br-table colgroup col:nth-child(6) { width:14%; }
.br-table colgroup col:nth-child(7) { width:27%; }
.br-table th { background:linear-gradient(90deg,var(--accent-dark),var(--accent)); color:#fff; padding:11px 12px; text-align:left; font-weight:700; font-size:11px; white-space:nowrap; }
.br-table td { padding:11px 12px; border-bottom:1px solid var(--border-color); color:var(--text-primary); vertical-align:middle; overflow:hidden; }
.br-table tbody tr:hover { background:var(--accent-light); }
.br-empty,.br-loading { text-align:center; color:var(--text-secondary); padding:36px; font-size:13px; }

/* Type badge */
.type-badge { display:inline-block; padding:3px 9px; border-radius:99px; font-size:10px; font-weight:700; white-space:nowrap; }

/* Check items */
.check-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(175px,1fr)); gap:10px; margin-bottom:14px; }
.check-item { display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px 12px; border-radius:10px; border:1.5px solid var(--border-color); background:var(--card-bg); transition:all .2s; user-select:none; }
.check-item:hover { border-color:var(--accent); }
.check-item:has(input:checked) { border-color:var(--accent); background:var(--accent-light); }
.check-item input { display:none; }
.check-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
.check-text { font-size:13px; color:var(--text-secondary); }
.check-item:has(input:checked) .check-text { font-weight:700; color:var(--text-primary); }
.toggle-row { display:flex; align-items:center; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
.toggle-btn { font-size:11px; padding:5px 12px; border-radius:6px; border:1.5px solid var(--border-color); background:var(--card-bg); color:var(--text-secondary); cursor:pointer; transition:all .2s; }
.toggle-btn:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
.count-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:99px; background:var(--accent-light); color:var(--accent); }

/* Revenue mini cards */
.rev-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:10px; margin-bottom:16px; }
.rev-card { background:var(--card-bg); border:1px solid var(--border-color); border-radius:10px; padding:14px 16px; }
.rev-val  { font-size:18px; font-weight:800; color:var(--accent); }
.rev-lbl  { font-size:10px; color:var(--text-secondary); margin-top:3px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }

/* Top items */
.top-row { display:flex; align-items:center; gap:12px; padding:9px 0; border-bottom:1px solid var(--border-color); }
.top-row:last-child { border:none; }
.top-rank { width:24px; height:24px; border-radius:50%; background:var(--accent); color:#fff; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.top-name { flex:1; font-size:13px; font-weight:600; }
.top-num  { font-size:12px; color:var(--text-secondary); }

/* Trash tabs */
.trash-tabs { display:flex; gap:8px; margin-bottom:14px; }
.trash-tab  { padding:8px 18px; border-radius:8px; border:1.5px solid var(--border-color); background:var(--card-bg); color:var(--text-secondary); font-size:13px; font-weight:600; cursor:pointer; transition:all .2s; }
.trash-tab.active { background:var(--accent); color:#fff; border-color:var(--accent); }
.days-ok   { background:#d1fae5; color:#065f46; }
.days-warn { background:#fef3c7; color:#92400e; }
.days-crit { background:#fee2e2; color:#991b1b; }

/* Modal */
.modal-ov  { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; }
.modal-ov.show { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:28px; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.modal-box h3 { margin:0 0 8px; font-size:17px; font-weight:800; }
.modal-box p  { color:#6b7280; font-size:13px; margin:0 0 20px; }
.modal-acts   { display:flex; gap:10px; justify-content:flex-end; }
.modal-cancel { padding:8px 18px; border-radius:8px; border:1.5px solid #e5e7eb; background:#fff; cursor:pointer; font-weight:600; font-size:13px; }
.modal-ok     { padding:8px 18px; border-radius:8px; border:none; background:#dc2626; color:#fff; cursor:pointer; font-weight:700; font-size:13px; }
</style>
</head>
<body>
<div class="dashboard">
<?= $sidebar->render('backup') ?>
<div class="br-wrap">

<!-- Hero -->
<div class="br-hero">
    <div class="br-hero-icon"><i class="fa-solid fa-cloud-arrow-down"></i></div>
    <div>
        <h1>Backup &amp; Restore</h1>
        <p>Readable Excel backups for this store account only &middot; Sales reports &middot; Recover deleted records</p>
    </div>
</div>

<!-- Tabs -->
<div class="br-tabs">
    <button class="br-tab active" id="tab-backup" onclick="showTab('backup',this)">
        <i class="fa-solid fa-database"></i> Database Backup
    </button>
    <button class="br-tab" id="tab-sales" onclick="showTab('sales',this)">
        <i class="fa-solid fa-chart-line"></i> Sales Summary
    </button>
    <button class="br-tab" id="tab-trash" onclick="showTab('trash',this)">
        <i class="fa-solid fa-trash-can-arrow-up"></i> Trash &amp; Restore
        <?php if($trashed_items+$trashed_staff>0): ?>
        <span class="tab-badge"><?= $trashed_items+$trashed_staff ?></span>
        <?php endif; ?>
    </button>
</div>

<!-- ═══════════════════════════════ TAB 1: DATABASE BACKUP ═══ -->
<div id="section-backup">
    <div id="auto-notice" class="notice notice-amber" style="display:none;"></div>

    <!-- Stats -->
    <div class="stat-grid" id="backup-stats">
        <div class="stat-card"><div class="stat-icon" style="background:#ede9fe;"><i class="fa-solid fa-database" style="color:#7c3aed;"></i></div><div><div class="stat-val" id="s-total">—</div><div class="stat-lbl">Total Backups</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:#dbeafe;"><i class="fa-solid fa-hard-drive" style="color:#2563eb;"></i></div><div><div class="stat-val" id="s-size">—</div><div class="stat-lbl">Total Size</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:#dcfce7;"><i class="fa-solid fa-calendar-check" style="color:#16a34a;"></i></div><div><div class="stat-val" id="s-latest" style="font-size:13px;line-height:1.3;">—</div><div class="stat-lbl">Latest Backup</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;"><i class="fa-solid fa-robot" style="color:#d97706;"></i></div><div><div class="stat-val" id="s-next" style="font-size:13px;line-height:1.3;">—</div><div class="stat-lbl">Next Auto-Backup</div></div></div>
    </div>

    <!-- Create Backup (full width) -->
    <div class="br-card">
        <div class="br-card-title"><i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Create Store Account Excel Backup</div>
        <p class="br-card-sub">Owner-friendly Excel backup for this store only: profile, staff, attendance, menu inventory, orders, payments, revenue, deleted records, and activity logs.</p>
        <div style="display:flex;gap:10px;margin-bottom:12px;">
            <input type="text" id="backup-label" placeholder="Label (e.g. before-update)"
                style="flex:1;border:1.5px solid var(--border-color);border-radius:8px;padding:9px 13px;font-size:13px;background:var(--card-bg);color:var(--text-primary);">
            <button class="btn btn-green" id="btn-create" onclick="createBackup()">
                <i class="fa-solid fa-download"></i> Create Backup
            </button>
        </div>
        <div class="notice notice-blue" style="margin-bottom:0;">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span><strong>Auto-backup</strong> runs every 24 hours automatically in the background.</span>
        </div><button class="pill"        data-period="today"   onclick="setBkPeriod(this)">Today</button>
    </div>

    <!-- Backup History -->
    <div class="br-card">
        <div class="br-card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--accent);"></i> Backup History</div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px;">
            <div class="pill-row" id="bk-pills" style="margin-bottom:0;flex:1;">
                <button class="pill active" data-period="all"     onclick="setBkPeriod(this)">All Time</button>
                <button class="pill"        data-period="today"   onclick="setBkPeriod(this)">Today</button>
                <button class="pill"        data-period="week"    onclick="setBkPeriod(this)">This Week</button>
                <button class="pill"        data-period="month"   onclick="setBkPeriod(this)">This Month</button>
                <button class="pill"        data-period="6months" onclick="setBkPeriod(this)">6 Months</button>
                <button class="pill"        data-period="year"    onclick="setBkPeriod(this)">1 Year</button>
            </div>
            <button class="btn btn-outline btn-sm" onclick="loadBackups()"><i class="fa-solid fa-rotate"></i> Refresh</button>
            <input type="text" id="bk-search" placeholder="Search…" oninput="filterBk(this.value)"
                style="padding:6px 12px;border:1.5px solid var(--border-color);border-radius:20px;font-size:12px;background:var(--card-bg);color:var(--text-primary);">
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
            <span style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;">Date Range:</span>
            <input type="date" id="bk-from" class="date-input" onchange="setBkCustom()">
            <span style="font-size:11px;color:var(--text-secondary);">to</span>
            <input type="date" id="bk-to" class="date-input" onchange="setBkCustom()">
            <button class="toggle-btn" onclick="clearBkRange()"><i class="fa-solid fa-xmark"></i> Clear</button>
        </div>
        <div id="bk-list"><div class="br-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div></div>
    </div>
</div>

<!-- ═══════════════════════════════ TAB 2: SALES SUMMARY ═══ -->
<div id="section-sales" style="display:none;">

    <!-- Stats Card -->
    <div class="br-card">
        <div class="br-card-title"><i class="fa-solid fa-chart-line" style="color:#16a34a;"></i> Sales &amp; Revenue Summary — <?= $storeName ?></div>
        <p class="br-card-sub">Aggregated order data. Includes completed, cancelled, and in-progress orders.</p>
        <div class="pill-row" id="sl-pills">
            <button class="pill active" data-period="all"     onclick="setSalesPeriod(this)">All Time</button>
            <button class="pill"        data-period="today"   onclick="setSalesPeriod(this)">Today</button>
            <button class="pill"        data-period="week"    onclick="setSalesPeriod(this)">This Week</button>
            <button class="pill"        data-period="month"   onclick="setSalesPeriod(this)">This Month</button>
            <button class="pill"        data-period="6months" onclick="setSalesPeriod(this)">6 Months</button>
            <button class="pill"        data-period="year"    onclick="setSalesPeriod(this)">1 Year</button>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
            <span style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;">Date Range:</span>
            <input type="date" id="sl-from" class="date-input" onchange="setSalesCustom()">
            <span style="font-size:11px;color:var(--text-secondary);">to</span>
            <input type="date" id="sl-to" class="date-input" onchange="setSalesCustom()">
            <button class="toggle-btn" onclick="clearSalesRange()"><i class="fa-solid fa-xmark"></i> Clear</button>
        </div>
        <div id="sales-stats"><div class="br-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div></div>
    </div>

    <!-- Download Excel Card -->
    <div class="br-card">
        <div class="br-card-title"><i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Download Excel Backup</div>
        <p class="br-card-sub">Choose which sections to include. The spreadsheet includes this store account only and uses readable business labels.</p>
        <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;">INCLUDE SECTIONS</div>
        <div class="check-grid">
            <label class="check-item"><input type="checkbox" class="sl-cb" value="store_info" checked><span class="check-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fa-solid fa-store"></i></span><span class="check-text">Store Info</span></label>
            <label class="check-item"><input type="checkbox" class="sl-cb" value="staff" checked><span class="check-icon" style="background:#dbeafe;color:#1e40af;"><i class="fa-solid fa-users"></i></span><span class="check-text">Staff</span></label>
            <label class="check-item"><input type="checkbox" class="sl-cb" value="inventory" checked><span class="check-icon" style="background:#dcfce7;color:#15803d;"><i class="fa-solid fa-boxes-stacked"></i></span><span class="check-text">Inventory</span></label>
            <label class="check-item"><input type="checkbox" class="sl-cb" value="orders" checked><span class="check-icon" style="background:#fef9c3;color:#a16207;"><i class="fa-solid fa-receipt"></i></span><span class="check-text">Orders</span></label>
            <label class="check-item"><input type="checkbox" class="sl-cb" value="revenue" checked><span class="check-icon" style="background:#fce7f3;color:#9d174d;"><i class="fa-solid fa-chart-line"></i></span><span class="check-text">Revenue</span></label>
            <label class="check-item"><input type="checkbox" class="sl-cb" value="deleted_items"><span class="check-icon" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-trash"></i></span><span class="check-text">Deleted Items</span></label>
        </div>
        <div class="toggle-row">
            <button class="pill active" id="sl-period-today"   data-slp="today"   onclick="setSlPeriod(this)">Today</button>
            <button class="pill"        id="sl-period-week"    data-slp="week"    onclick="setSlPeriod(this)">This Week</button>
            <button class="pill"        id="sl-period-month"   data-slp="month"   onclick="setSlPeriod(this)">This Month</button>
            <button class="pill"        id="sl-period-year"    data-slp="year"    onclick="setSlPeriod(this)">This Year</button>
            <button class="pill"        id="sl-period-all"     data-slp="all"     onclick="setSlPeriod(this)">All Time</button>
        </div>
        <div style="display:flex;align-items:center;gap:12px;margin-top:8px;">
            <button class="btn btn-green" id="btn-sl-dl" onclick="downloadSales()">
                <i class="fa-solid fa-file-excel"></i> Download Excel (.xlsx)
            </button>
            <span class="count-badge" id="sl-count">5 selected</span>
        </div>
        <div id="sl-err" style="color:#dc2626;font-size:12px;margin-top:8px;display:none;"></div>
    </div>
</div>

<!-- ═══════════════════════════════ TAB 3: TRASH & RESTORE ═══ -->
<div id="section-trash" style="display:none;">
    <div class="stat-grid" style="margin-bottom:18px;">
        <div class="stat-card"><div class="stat-icon" style="background:#dcfce7;"><i class="fa-solid fa-utensils" style="color:#16a34a;"></i></div><div><div class="stat-val"><?= $menu_count ?></div><div class="stat-lbl">Active Menu Items</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:#dbeafe;"><i class="fa-solid fa-users" style="color:#2563eb;"></i></div><div><div class="stat-val"><?= $staff_count ?></div><div class="stat-lbl">Active Staff</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:#fee2e2;"><i class="fa-solid fa-box-archive" style="color:#dc2626;"></i></div><div><div class="stat-val"><?= $trashed_items ?></div><div class="stat-lbl">Items in Trash</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;"><i class="fa-solid fa-user-slash" style="color:#d97706;"></i></div><div><div class="stat-val"><?= $trashed_staff ?></div><div class="stat-lbl">Staff in Trash</div></div></div>
    </div>

    <div class="br-card">
        <div class="br-card-title"><i class="fa-solid fa-trash-can-arrow-up" style="color:var(--accent);"></i> Deleted Records
            <span style="font-size:11px;font-weight:500;color:var(--text-secondary);margin-left:6px;">30-day recovery window · auto-purged after</span>
        </div>
        <p class="br-card-sub">Restore deleted menu items and removed/inactive staff within 30 days.</p>
        <div class="trash-tabs">
            <button class="trash-tab active" onclick="switchTrash('items',this)">
                <i class="fa-solid fa-utensils"></i> Menu Items
                <?php if($trashed_items>0): ?><span class="tab-badge"><?= $trashed_items ?></span><?php endif; ?>
            </button>
            <button class="trash-tab" onclick="switchTrash('staff',this)">
                <i class="fa-solid fa-users"></i> Staff
                <?php if($trashed_staff>0): ?><span class="tab-badge"><?= $trashed_staff ?></span><?php endif; ?>
            </button>
        </div>
        <div id="trash-items"></div>
        <div id="trash-staff" style="display:none;"></div>
    </div>
</div>

</div><!-- .br-wrap -->
<?= $sidebar->renderClose() ?>
</div><!-- .dashboard -->

<!-- Confirm Modal -->
<div class="modal-ov" id="modal">
    <div class="modal-box">
        <h3 id="modal-title"></h3>
        <p id="modal-msg"></p>
        <div class="modal-acts">
            <button class="modal-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-ok" id="modal-ok">Confirm</button>
        </div>
    </div>
</div>

<?php $pm = __DIR__ . '/helpers/pin_modals.php'; if(file_exists($pm)) include $pm; ?>

<script>
const H = 'backup_handler.php';
const T = 'soft_delete_handler.php';
const cssVar = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const themeAccent = () => cssVar('--accent') || '#be185d';
let bkPeriod='all', bkFrom=null, bkTo=null;
let slPeriod='all', slFrom=null, slTo=null;
let slDownloadPeriod='today';
let allBk=[], pendingFn=null;

// ── Helpers ───────────────────────────────────────────────
const fmt   = n  => '₱' + parseFloat(n||0).toLocaleString('en-PH',{minimumFractionDigits:2});
const fmtSz = b  => !b?'0B': b<1024?b+'B': b<1048576?(b/1024).toFixed(1)+'KB': (b/1048576).toFixed(2)+'MB';
const fmtDt = s  => { if(!s) return '—'; const d=new Date(s); return d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})+' '+d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}); };

// ── Tabs ──────────────────────────────────────────────────
function showTab(tab,el) {
    ['backup','sales','trash'].forEach(id=>{
        document.getElementById('section-'+id).style.display='none';
        document.getElementById('tab-'+id).classList.remove('active');
    });
    document.getElementById('section-'+tab).style.display='';
    el.classList.add('active');
    if (tab==='sales' && document.getElementById('sales-stats').innerHTML.includes('fa-spin')) loadSalesStats();
    if (tab==='trash') { loadTrash('items'); }
    if (tab==='backup') loadBackups();
}

// ── Modal ─────────────────────────────────────────────────
function openModal(title,msg,fn,danger=true) {
    document.getElementById('modal-title').textContent=title;
    document.getElementById('modal-msg').textContent=msg;
    document.getElementById('modal-ok').style.background=danger?'#dc2626':'var(--accent)';
    document.getElementById('modal').classList.add('show');
    pendingFn=fn;
}
function closeModal() { document.getElementById('modal').classList.remove('show'); pendingFn=null; }
document.getElementById('modal-ok').onclick=()=>{ const fn=pendingFn; closeModal(); if(fn) fn(); };

// ══════════════════════════════════════════════════════════
//  DATABASE BACKUP TAB
// ══════════════════════════════════════════════════════════
async function loadBackups() {
    const area = document.getElementById('bk-list');
    area.innerHTML='<div class="br-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>';
    try {
        let url = H+'?action=list&period='+bkPeriod;
        if (bkFrom && bkTo) url += '&date_from='+bkFrom+'&date_to='+bkTo;
        const d = await (await fetch(url)).json();
        if (!d.success) { area.innerHTML='<div class="br-empty">Failed to load.</div>'; return; }
        document.getElementById('s-total').textContent  = d.total ?? 0;
        document.getElementById('s-size').textContent   = fmtSz(d.total_size ?? 0);
        document.getElementById('s-latest').textContent = d.latest ? fmtDt(d.latest) : 'None yet';
        document.getElementById('s-next').textContent   = d.next_auto ? fmtDt(d.next_auto) : 'Soon';
        // Auto-notice
        const notice = document.getElementById('auto-notice');
        if (d.last_auto) {
            notice.className='notice notice-green'; notice.style.display='flex';
            notice.innerHTML='<i class="fa-solid fa-circle-check"></i><span><strong>Auto-backup active.</strong> Last: '+fmtDt(d.last_auto)+' · Next: '+fmtDt(d.next_auto)+'</span>';
        } else {
            notice.className='notice notice-amber'; notice.style.display='flex';
            notice.innerHTML='<i class="fa-solid fa-circle-exclamation"></i><span><strong>Auto-backup not yet run.</strong> Create your first backup above.</span>';
        }
        allBk = d.backups || [];
        renderBkTable(allBk);
    } catch(e) { area.innerHTML='<div class="br-empty">Network error.</div>'; }
}
function renderBkTable(list) {
    const area = document.getElementById('bk-list');
    if (!list.length) { area.innerHTML='<div class="br-empty"><i class="fa-solid fa-inbox" style="font-size:28px;display:block;margin-bottom:8px;"></i>No backups found.</div>'; return; }
    const typeCfg = { auto:{bg:'#dbeafe',fg:'#1e40af'}, manual:{bg:'#dcfce7',fg:'#065f46'}, excel:{bg:'#dcfce7',fg:'#065f46'} };
    let h=`<div class="br-table-wrap"><table class="br-table"><colgroup><col><col><col><col><col><col><col></colgroup>
        <thead><tr><th>Type</th><th>Label / Filename</th><th>Created By</th><th>Size</th><th>Sheets</th><th>Date</th><th>Actions</th></tr></thead><tbody>`;
    list.forEach(b=>{
        const t=(b.type||'manual').toLowerCase();
        const c=typeCfg[t]||{bg:'#f3f4f6',fg:'#374151'};
        const exists=b.exists!==false;
        const ext = (b.filename||'').split('.').pop().toLowerCase();
        const dlUrl = H+'?action=download&file='+encodeURIComponent(b.filename);
        h+=`<tr data-name="${(b.filename||'').toLowerCase()}">
            <td><span class="type-badge" style="background:${c.bg};color:${c.fg};">${t.toUpperCase()}</span></td>
            <td style="overflow:hidden;"><div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${b.label||'—'}</div><div style="font-size:10px;color:var(--text-secondary);font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${b.filename||''}">${b.filename||''}</div></td>
            <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${b.created_by||'—'}</td>
            <td style="white-space:nowrap;">${fmtSz(b.size||0)}</td>
            <td style="text-align:center;">${b.tables||'—'}</td>
            <td style="font-size:11px;white-space:nowrap;">${fmtDt(b.created_at)}</td>
            <td><div style="display:flex;gap:4px;flex-wrap:nowrap;">
                ${exists?`<a href="${dlUrl}" class="btn btn-outline btn-sm" download><i class="fa-solid fa-download"></i> Download</a>`:'<span style="color:#dc2626;font-size:10px;">Missing</span>'}
                <button class="btn btn-danger btn-sm" onclick='confirmDelete("${b.filename}")'><i class="fa-solid fa-trash"></i></button>
            </div></td>
        </tr>`;
    });
    h+='</tbody></table></div>';
    area.innerHTML=h;
}
function filterBk(q) {
    q=q.toLowerCase();
    renderBkTable(q ? allBk.filter(b=>(b.filename||'').toLowerCase().includes(q)||(b.label||'').toLowerCase().includes(q)) : allBk);
}
function setBkPeriod(el) {
    document.querySelectorAll('#bk-pills .pill').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    bkPeriod=el.dataset.period; bkFrom=bkTo=null;
    document.getElementById('bk-from').value='';
    document.getElementById('bk-to').value='';
    loadBackups();
}
function setBkCustom() {
    bkFrom=document.getElementById('bk-from').value;
    bkTo=document.getElementById('bk-to').value;
    if (!bkFrom||!bkTo) return;
    document.querySelectorAll('#bk-pills .pill').forEach(p=>p.classList.remove('active'));
    bkPeriod='custom'; loadBackups();
}
function clearBkRange() {
    document.getElementById('bk-from').value='';
    document.getElementById('bk-to').value='';
    bkFrom=bkTo=null; bkPeriod='all';
    document.querySelector('#bk-pills .pill').classList.add('active');
    loadBackups();
}
async function createBackup() {
    const btn=document.getElementById('btn-create');
    const label=document.getElementById('backup-label').value.trim()||'manual';
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Creating…';
    try {
        const fd=new FormData(); fd.append('label',label);
        const d=await (await fetch(H+'?action=create',{method:'POST',body:fd})).json();
        if (d.success) { Swal.fire({icon:'success',title:'Backup Created',text:d.message,timer:3000,showConfirmButton:false}); loadBackups(); }
        else Swal.fire({icon:'error',title:'Failed',text:d.message||'Backup failed.'});
    } catch(e) { Swal.fire({icon:'error',title:'Error',text:'Network error.'}); }
    btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-download"></i> Create Backup';
}
function confirmDelete(filename) {
    openModal('Delete Backup?','Permanently delete "'+filename+'"?',async()=>{
        const fd=new FormData(); fd.append('file',filename);
        await fetch(H+'?action=delete',{method:'POST',body:fd});
        loadBackups();
    });
}

// ══════════════════════════════════════════════════════════
//  SALES SUMMARY TAB
// ══════════════════════════════════════════════════════════
function setSalesPeriod(el) {
    document.querySelectorAll('#sl-pills .pill').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    slPeriod=el.dataset.period; slFrom=slTo=null;
    document.getElementById('sl-from').value='';
    document.getElementById('sl-to').value='';
    loadSalesStats();
}
function setSalesCustom() {
    slFrom=document.getElementById('sl-from').value;
    slTo=document.getElementById('sl-to').value;
    if (!slFrom||!slTo) return;
    document.querySelectorAll('#sl-pills .pill').forEach(p=>p.classList.remove('active'));
    slPeriod='custom'; loadSalesStats();
}
function clearSalesRange() {
    document.getElementById('sl-from').value='';
    document.getElementById('sl-to').value='';
    slFrom=slTo=null; slPeriod='all';
    document.querySelector('#sl-pills .pill').classList.add('active');
    loadSalesStats();
}
async function loadSalesStats() {
    const area=document.getElementById('sales-stats');
    area.innerHTML='<div class="br-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>';
    try {
        let url=H+'?action=order_stats&period='+slPeriod;
        if (slFrom&&slTo) url+='&date_from='+slFrom+'&date_to='+slTo;
        const d=await (await fetch(url)).json();
        if (!d.success) { area.innerHTML='<div class="br-empty">Failed to load stats.</div>'; return; }
        const s=d.stats||{};
        let html=`<div class="rev-grid">
            <div class="rev-card"><div class="rev-val">${fmt(s.gross_revenue)}</div><div class="rev-lbl">Gross Revenue</div></div>
            <div class="rev-card"><div class="rev-val">${fmt(s.net_sales)}</div><div class="rev-lbl">Net Sales</div></div>
            <div class="rev-card"><div class="rev-val">${fmt(s.avg_order)}</div><div class="rev-lbl">Avg Order Value</div></div>
            <div class="rev-card"><div class="rev-val">${parseInt(s.total_orders||0)}</div><div class="rev-lbl">Total Orders</div></div>
            <div class="rev-card"><div class="rev-val" style="color:#16a34a;">${parseInt(s.completed||0)}</div><div class="rev-lbl">Completed</div></div>
            <div class="rev-card"><div class="rev-val" style="color:#dc2626;">${parseInt(s.cancelled||0)}</div><div class="rev-lbl">Cancelled</div></div>
            <div class="rev-card"><div class="rev-val">${fmt(s.total_change)}</div><div class="rev-lbl">Total Change Given</div></div>
        </div>`;
        if (d.top_items?.length) {
            html+='<div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;margin:4px 0 10px;">Top Selling Items</div>';
            d.top_items.forEach((it,i)=>{ html+=`<div class="top-row"><div class="top-rank">${i+1}</div><div class="top-name">${it.item_name}</div><div class="top-num">${it.qty} sold · ${fmt(it.revenue)}</div></div>`; });
        }
        if (d.by_day?.length) {
            html+='<div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;margin:16px 0 8px;">Revenue by Day</div>';
            html+='<div class="br-table-wrap"><table class="br-table" style="table-layout:auto;"><thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>';
            d.by_day.forEach(r=>{ html+=`<tr><td>${r.day}</td><td>${r.orders}</td><td>${fmt(r.revenue)}</td></tr>`; });
            html+='</tbody></table></div>';
        } else { html+='<div style="text-align:center;color:var(--text-secondary);padding:20px;font-size:13px;">No sales data for this period.</div>'; }
        area.innerHTML=html;
    } catch(e) { area.innerHTML='<div class="br-empty">Error loading stats.</div>'; }
}

// Download period pills
function setSlPeriod(el) {
    document.querySelectorAll('.toggle-row .pill').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    slDownloadPeriod=el.dataset.slp;
}

// Section checkboxes count
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.sl-cb').forEach(cb=>cb.addEventListener('change',updateSlCount));
    updateSlCount();
    // Init first download period pill
    document.querySelector('.toggle-row .pill').classList.add('active');
});
function updateSlCount() {
    const n=document.querySelectorAll('.sl-cb:checked').length;
    document.getElementById('sl-count').textContent=n+' selected';
}
function downloadSales() {
    const errEl=document.getElementById('sl-err'); errEl.style.display='none';
    const types=Array.from(document.querySelectorAll('.sl-cb:checked')).map(cb=>cb.value);
    if (!types.length) { errEl.textContent='Select at least one section.'; errEl.style.display='block'; return; }
    let from='2000-01-01', to=new Date().toISOString().slice(0,10);
    const fmtD=d=>d.toISOString().slice(0,10);
    const now=new Date();
    if      (slDownloadPeriod==='today')   { from=fmtD(now); }
    else if (slDownloadPeriod==='week')    { const d=new Date(now);d.setDate(d.getDate()-6);from=fmtD(d); }
    else if (slDownloadPeriod==='month')   { const d=new Date(now);d.setMonth(d.getMonth()-1);from=fmtD(d); }
    else if (slDownloadPeriod==='year')    { const d=new Date(now);d.setFullYear(d.getFullYear()-1);from=fmtD(d); }
    const btn=document.getElementById('btn-sl-dl');
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Generating…';
    const link=document.createElement('a');
    link.href=H+'?action=export_sales&types='+types.join(',')+'&date_from='+from+'&date_to='+to;
    link.click();
    setTimeout(()=>{ btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-file-excel"></i> Download Excel (.xlsx)'; },3500);
}

// ══════════════════════════════════════════════════════════
//  TRASH & RESTORE TAB
// ══════════════════════════════════════════════════════════
function switchTrash(tab,el) {
    document.querySelectorAll('.trash-tab').forEach(t=>t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('trash-items').style.display=tab==='items'?'':'none';
    document.getElementById('trash-staff').style.display=tab==='staff'?'':'none';
    loadTrash(tab);
}
async function loadTrash(tab) {
    const area=document.getElementById('trash-'+tab);
    area.innerHTML='<div class="br-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>';
    const type=tab==='items'?'item':'staff';
    try {
        const d=await (await fetch(T+'?action=list&type='+type)).json();
        if (!d.success) { area.innerHTML='<div class="br-empty">Failed to load.</div>'; return; }
        const list=tab==='items'?d.items:d.staff;
        if (!list?.length) { area.innerHTML='<div class="br-empty"><i class="fa-solid fa-trash-can" style="font-size:28px;display:block;margin-bottom:8px;"></i>Trash is empty.</div>'; return; }
        area.innerHTML = tab==='items' ? buildItemsTable(list) : buildStaffTable(list);
    } catch(e) { area.innerHTML='<div class="br-empty">Network error.</div>'; }
}
function dayBadge(days,rec) {
    if (!parseInt(rec)) return '<span class="type-badge days-crit">Expired</span>';
    const d=Math.max(0,parseInt(days)||0);
    const c=d>14?'days-ok':d>5?'days-warn':'days-crit';
    return `<span class="type-badge ${c}">${d}d left</span>`;
}
function buildItemsTable(list) {
    let h=`<div class="br-table-wrap"><table class="br-table" style="table-layout:auto;">
        <thead><tr><th>Item Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Deleted At</th><th>By</th><th>Window</th><th>Actions</th></tr></thead><tbody>`;
    list.forEach(it=>{
        h+=`<tr><td><strong>${it.item_name||'—'}</strong></td><td>${it.category||'—'}</td><td>₱${parseFloat(it.price||0).toFixed(2)}</td><td>${it.stock_quantity??'—'}</td><td style="font-size:11px;">${fmtDt(it.deleted_at)}</td><td style="font-size:11px;">${it.deleted_by||'—'}</td><td>${dayBadge(it.days_left,it.recoverable)}</td>
        <td><div style="display:flex;gap:4px;"><button class="btn btn-outline btn-sm" onclick="trashAct('restore','item',${it.id})"><i class="fa-solid fa-rotate-left"></i> Restore</button><button class="btn btn-danger btn-sm" onclick="trashAct('force_delete','item',${it.id})"><i class="fa-solid fa-trash"></i></button></div></td></tr>`;
    });
    return h+'</tbody></table></div>';
}
function buildStaffTable(list) {
    let h=`<div class="br-table-wrap"><table class="br-table" style="table-layout:auto;">
        <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Deleted At</th><th>By</th><th>Window</th><th>Actions</th></tr></thead><tbody>`;
    list.forEach(st=>{
        h+=`<tr><td><strong>${st.fullname||'—'}</strong></td><td>${st.role||'—'}</td><td>${st.status||'—'}</td><td style="font-size:11px;">${fmtDt(st.deleted_at)}</td><td style="font-size:11px;">${st.deleted_by||'—'}</td><td>${dayBadge(st.days_left,st.recoverable)}</td>
        <td><div style="display:flex;gap:4px;"><button class="btn btn-outline btn-sm" onclick="trashAct('restore','staff',${st.id})"><i class="fa-solid fa-rotate-left"></i> Restore</button><button class="btn btn-danger btn-sm" onclick="trashAct('force_delete','staff',${st.id})"><i class="fa-solid fa-trash"></i></button></div></td></tr>`;
    });
    return h+'</tbody></table></div>';
}
async function trashAct(action,type,id) {
    const isRestore=action==='restore';
    const msg=isRestore?'Restore this record back to active?':'Permanently delete? This cannot be undone.';
    openModal(isRestore?'Restore?':'Delete Permanently?',msg,async()=>{
        const fd=new FormData(); fd.append('action',action); fd.append('type',type); fd.append('id',id);
        const d=await (await fetch(T,{method:'POST',body:fd})).json();
        if (d.success) {
            if (isRestore && type==='item') {
                Swal.fire({
                    icon:'success',title:'Restored!',text:d.message,
                    showConfirmButton:true,confirmButtonText:'Go to Menu',
                    confirmButtonColor:themeAccent(),
                    showDenyButton:true,denyButtonText:'Stay here',denyButtonColor:'#6b7280',
                    timer:8000
                }).then(result=>{ if(result.isConfirmed) window.location.href='menu_list.php'; else loadTrash('items'); });
            } else if (isRestore && type==='staff') {
                Swal.fire({
                    icon:'success',title:'Restored!',text:d.message,
                    showConfirmButton:true,confirmButtonText:'Go to Manage Staff',
                    confirmButtonColor:themeAccent(),
                    showDenyButton:true,denyButtonText:'Stay here',denyButtonColor:'#6b7280',
                    timer:8000
                }).then(result=>{ if(result.isConfirmed) window.location.href='manage_staffs.php'; else loadTrash('staff'); });
            } else {
                Swal.fire({icon:'success',title:'Done',text:d.message,timer:3000,showConfirmButton:false});
                loadTrash(type==='item'?'items':'staff');
            }
        } else Swal.fire({icon:'error',title:'Failed',text:d.message});
    }, !isRestore);
}

// ── Init ──────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded',()=>{
    loadBackups();
    fetch(H+'?action=check_auto').catch(()=>{});
    updateSlCount();
});
</script>
</body>
</html>
