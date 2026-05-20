<?php
session_start();
require_once __DIR__ . "/helpers/admindashboard_helpers.php";
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
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
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; }
            .modal-content { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
            button { padding: 10px 20px; background: #ff0000; color: white; border: none; border-radius: 5px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="modal">
            <div class="modal-content">
                <h2>Your account has been deleted</h2>
                <p>You will be redirected to the login page.</p>
                <button onclick="window.location.href=\'../login.php\'">OK</button>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

$db       = new Database();
$conn     = $db->connect();
$admin_id = $_SESSION['admin_id'];

// Fetch admin profile for sidebar
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
    $adminProfile['fullname']  ?? '',
    $adminProfile['username']  ?? '',
    $adminProfile['email']     ?? ''
);

// Count saved backups from index
$backupDir  = dirname(__DIR__) . '/backups/admin_' . $admin_id;
$indexFile  = $backupDir . '/index.json';
$backupList = [];
if (file_exists($indexFile)) {
    $backupList = json_decode(file_get_contents($indexFile), true) ?? [];
    $backupList = array_values(array_filter($backupList, fn($b) => file_exists($backupDir . '/' . $b['filename'])));
    usort($backupList, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
}
$totalBackups = count($backupList);
$latestDate   = $totalBackups ? date('M d, Y', strtotime($backupList[0]['created_at'])) : 'Never';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Recovery — iPOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../design/admin.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <style>
        /* ── Page layout ── */
        .br-page { padding: 28px 32px; max-width: 1100px; }
        .br-hero  { margin-bottom: 28px; }
        .br-hero h1 { font-size: 1.55rem; font-weight: 700; color: var(--text-primary); margin: 0 0 4px; }
        .br-hero p  { font-size: .875rem; color: var(--text-secondary); margin: 0; }

        /* ── Summary cards ── */
        .br-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .br-stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px 22px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .br-stat-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .07em; color: var(--text-secondary); font-weight: 600; margin-bottom: 6px; }
        .br-stat-val   { font-size: 2rem; font-weight: 800; color: var(--text-primary); line-height: 1; }
        .br-stat-sub   { font-size: .78rem; color: var(--text-secondary); margin-top: 4px; }

        /* ── Section card ── */
        .br-card {
            background: var(--card-bg);
            border-radius: 14px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 22px;
            overflow: hidden;
        }
        .br-card-hdr {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .br-card-hdr h3 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary); }
        .br-card-body { padding: 22px; }

        /* ── Create form ── */
        .br-input-row { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; flex-wrap: wrap; }
        .br-input {
            flex: 1;
            min-width: 180px;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: .875rem;
            outline: none;
            transition: border-color .2s;
            background: var(--card-bg);
            color: var(--text-primary);
        }
        .br-input:focus { border-color: var(--accent); }

        .br-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, opacity .2s;
            white-space: nowrap;
        }
        .br-btn.primary   { background: var(--accent); color: #fff; }
        .br-btn.primary:hover { background: var(--accent-dark); }
        .br-btn.secondary { background: var(--accent-light); color: var(--accent-dark); }
        .br-btn.secondary:hover { filter: brightness(0.94); }
        .br-btn.danger    { background: #fee2e2; color: #dc2626; }
        .br-btn.danger:hover { background: #fecaca; }
        .br-btn:disabled  { opacity: .55; cursor: not-allowed; }

        .br-about {
            font-size: .84rem;
            color: var(--text-secondary);
            line-height: 1.7;
            background: var(--accent-light);
            border-radius: 8px;
            padding: 14px 16px;
            border-left: 3px solid var(--accent);
        }

        /* ── Table ── */
        .br-tbl-wrap { overflow-x: auto; }
        .br-tbl { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .br-tbl thead th {
            background: var(--accent-light);
            color: var(--accent-dark);
            font-weight: 700;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .br-tbl tbody tr { border-bottom: 1px solid var(--border-color); transition: background .15s; }
        .br-tbl tbody tr:hover { background: var(--accent-light); }
        .br-tbl tbody td { padding: 10px 14px; color: var(--text-primary); vertical-align: middle; }
        .br-tbl .mono { font-family: monospace; font-size: .78rem; }
        .br-tbl .muted { color: var(--text-secondary); font-size: .8rem; }

        .br-badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: .73rem;
            font-weight: 600;
            background: var(--accent-light);
            color: var(--accent-dark);
        }

        .br-actions { display: flex; gap: 6px; flex-wrap: wrap; }

        /* ── Upload restore area ── */
        .br-dropzone {
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            padding: 28px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            margin-top: 14px;
        }
        .br-dropzone:hover, .br-dropzone.drag-over { border-color: var(--accent); background: var(--accent-light); }
        .br-dropzone i { font-size: 2rem; color: var(--accent); margin-bottom: 10px; display: block; }
        .br-dropzone p { margin: 0 0 6px; font-size: .875rem; color: var(--text-secondary); }
        .br-dropzone small { color: var(--text-secondary); font-size: .78rem; }
        #upload-filename { font-size: .8rem; color: var(--accent); margin-top: 8px; font-weight: 600; }

        /* ── Toast ── */
        #br-toast {
            position: fixed;
            bottom: 28px;
            right: 28px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .toast-msg {
            padding: 12px 18px;
            border-radius: 10px;
            font-size: .875rem;
            font-weight: 500;
            color: #fff;
            box-shadow: var(--shadow-md);
            animation: slideUp .25s ease;
            max-width: 340px;
        }
        .toast-msg.success { background: #15803d; }
        .toast-msg.error   { background: #dc2626; }
        @keyframes slideUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }

        /* ── Empty state ── */
        .br-empty {
            text-align: center;
            padding: 44px 20px;
            color: var(--text-secondary);
        }
        .br-empty i { font-size: 2.5rem; color: var(--border-color); margin-bottom: 12px; display: block; }
        .br-empty h4 { margin: 0 0 6px; color: var(--text-primary); font-size: 1rem; }
        .br-empty p  { margin: 0; font-size: .84rem; }

        /* ── Loading ── */
        .br-loading { text-align: center; padding: 32px; color: var(--text-secondary); font-size: .875rem; }
        .br-loading i { margin-right: 6px; }

        /* ── Rename inline ── */
        .rename-input {
            padding: 4px 8px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: .8rem;
            width: 140px;
            background: var(--card-bg);
            color: var(--text-primary);
        }

        /* ── Confirm overlay ── */
        .confirm-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 8000;
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .confirm-box {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 32px 28px;
            max-width: 380px;
            width: 90%;
            text-align: center;
            border-top: 4px solid var(--accent);
            box-shadow: var(--shadow-lg);
        }
        .confirm-box i   { font-size: 2.2rem; color: var(--accent); margin-bottom: 14px; display: block; }
        .confirm-box h3  { margin: 0 0 8px; font-size: 1.1rem; color: var(--text-primary); }
        .confirm-box p   { font-size: .875rem; color: var(--text-secondary); margin: 0 0 22px; }
        .confirm-btns    { display: flex; gap: 10px; justify-content: center; }

        @media (max-width: 640px) {
            .br-page { padding: 16px; }
            .br-input-row { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<?php echo $sidebar->render('backup'); ?>

<div class="main">
    <div class="br-page">

        <!-- Hero -->
        <div class="br-hero">
            <h1><i class="fa-solid fa-database" style="color:var(--accent);margin-right:10px;"></i>Backup & Recovery</h1>
            <p>Create SQL backups of your restaurant data and restore them whenever needed.</p>
        </div>

        <!-- Stats -->
        <div class="br-grid">
            <div class="br-stat-card">
                <div class="br-stat-label">Total Backups</div>
                <div class="br-stat-val" id="stat-total"><?= $totalBackups ?></div>
                <div class="br-stat-sub">saved on server</div>
            </div>
            <div class="br-stat-card">
                <div class="br-stat-label">Latest Backup</div>
                <div class="br-stat-val" style="font-size:1.25rem;padding-top:6px;" id="stat-latest"><?= htmlspecialchars($latestDate) ?></div>
                <div class="br-stat-sub">most recent snapshot</div>
            </div>
            <div class="br-stat-card">
                <div class="br-stat-label">Format</div>
                <div class="br-stat-val" style="font-size:1.25rem;padding-top:6px;">SQL</div>
                <div class="br-stat-sub">compatible with MySQL / MariaDB</div>
            </div>
            <div class="br-stat-card">
                <div class="br-stat-label">Total Restores</div>
                <div class="br-stat-val" id="stat-total-restores">—</div>
                <div class="br-stat-sub" id="stat-last-restore-src">no restore yet</div>
            </div>
        </div>

        <!-- Create Backup -->
        <div class="br-card">
            <div class="br-card-hdr">
                <h3><i class="fa-solid fa-floppy-disk" style="color:var(--accent);margin-right:8px;"></i>Create New Backup</h3>
            </div>
            <div class="br-card-body">
                <div class="br-input-row">
                    <input type="text" id="backup-label" class="br-input" placeholder="Label (e.g. before-update, weekly)" maxlength="60">
                    <button class="br-btn primary" id="btn-create" onclick="createBackup()">
                        <i class="fa-solid fa-floppy-disk"></i> Create Backup
                    </button>
                </div>
                <div class="br-about">
                    <strong>What gets backed up:</strong> Your menu items, staff records, orders, order items, payments, and audit log entries — scoped only to your account. The backup is saved as a <code>.sql</code> file on the server and can be downloaded or restored at any time.
                </div>
            </div>
        </div>

        <!-- Saved Backups -->
        <div class="br-card">
            <div class="br-card-hdr">
                <h3><i class="fa-solid fa-folder-open" style="color:var(--accent);margin-right:8px;"></i>Saved Backups (<span id="count-inline"><?= $totalBackups ?></span>)</h3>
                <button class="br-btn secondary" onclick="loadBackupList()" style="padding:7px 14px;font-size:.8rem;">
                    <i class="fa-solid fa-rotate"></i> Refresh
                </button>
            </div>
            <div id="backup-table-wrap">
                <?php if ($totalBackups === 0): ?>
                <div class="br-empty">
                    <i class="fa-solid fa-database"></i>
                    <h4>No backups yet</h4>
                    <p>Create your first backup above to get started.</p>
                </div>
                <?php else: ?>
                <div class="br-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading backups…</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Restore History -->
        <div class="br-card">
            <div class="br-card-hdr">
                <h3><i class="fa-solid fa-clock-rotate-left" style="color:var(--accent);margin-right:8px;"></i>Restore History (<span id="restore-count-inline">0</span>)</h3>
                <button class="br-btn secondary" onclick="loadRestoreLogs()" style="padding:7px 14px;font-size:.8rem;">
                    <i class="fa-solid fa-rotate"></i> Refresh
                </button>
            </div>
            <div id="restore-table-wrap">
                <div class="br-empty">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <h4>No restores yet</h4>
                    <p>Restore history will appear here.</p>
                </div>
            </div>
        </div>

        <!-- Restore from Upload -->
        <div class="br-card">
            <div class="br-card-hdr">
                <h3><i class="fa-solid fa-file-import" style="color:var(--accent);margin-right:8px;"></i>Restore from File Upload</h3>
            </div>
            <div class="br-card-body">
                <p style="font-size:.875rem;color:var(--text-secondary);margin:0 0 4px;">
                    Upload a previously downloaded <code>.sql</code> backup file. This will overwrite your current data — use with caution.
                </p>
                <div class="br-dropzone" id="dropzone" onclick="document.getElementById('upload-input').click()"
                     ondragover="event.preventDefault();this.classList.add('drag-over')"
                     ondragleave="this.classList.remove('drag-over')"
                     ondrop="handleDrop(event)">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p>Click to browse or drag & drop your <code>.sql</code> file here</p>
                    <small>Only <strong>.sql</strong> files are accepted</small>
                    <div id="upload-filename"></div>
                </div>
                <input type="file" id="upload-input" accept=".sql" style="display:none" onchange="fileSelected(this)">
                <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="br-btn primary" id="btn-upload-restore" onclick="uploadRestore()" disabled>
                        <i class="fa-solid fa-rotate-left"></i> Restore from Upload
                    </button>
                    <button class="br-btn secondary" onclick="clearUpload()">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </button>
                </div>
            </div>
        </div>

    </div><!-- .br-page -->
</div><!-- .main -->

<!-- Restore success banner -->
<div id="restore-success-banner" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0;
     border-radius:12px; padding:18px 22px; margin-bottom:22px;
     align-items:flex-start; gap:14px;">
    <span style="font-size:1.8rem; line-height:1;">✅</span>
    <div>
        <div style="font-weight:700; color:#166534; font-size:.95rem;">Restore Completed Successfully</div>
        <div style="color:#15803d; font-size:.85rem; margin-top:4px;" id="restore-banner-detail"></div>
        <div style="color:#6b7280; font-size:.78rem; margin-top:6px;">
            This action has been recorded in the audit log. Please verify your data before continuing.
        </div>
    </div>
    <button onclick="document.getElementById('restore-success-banner').style.display='none'"
            style="margin-left:auto; background:none; border:none; cursor:pointer; color:#6b7280; font-size:1.1rem;">✕</button>
</div>

<!-- Toast container -->
<div id="br-toast"></div>

<!-- Confirm overlay -->
<div class="confirm-overlay" id="confirm-overlay" style="display:none;">
    <div class="confirm-box">
        <i class="fa-solid fa-triangle-exclamation" id="confirm-icon"></i>
        <h3 id="confirm-title">Are you sure?</h3>
        <p id="confirm-body">This action cannot be undone.</p>
        <div class="confirm-btns">
            <button class="br-btn secondary" onclick="closeConfirm()">Cancel</button>
            <button class="br-btn danger"    id="confirm-ok">Confirm</button>
        </div>
    </div>
</div>

<script>
const HANDLER = 'admin_backup_handler.php';
let uploadedFile = null;

// ── Toast ──────────────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
    const wrap = document.getElementById('br-toast');
    const el   = document.createElement('div');
    el.className = `toast-msg ${type}`;
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(() => el.remove(), 4500);
}

// ── Confirm dialog ────────────────────────────────────────────────────────
let confirmCallback = null;
function showConfirm(title, body, icon = 'fa-triangle-exclamation', cb) {
    document.getElementById('confirm-title').textContent = title;
    document.getElementById('confirm-body').textContent  = body;
    document.getElementById('confirm-icon').className    = `fa-solid ${icon}`;
    confirmCallback = cb;
    document.getElementById('confirm-overlay').style.display = 'flex';
}
function closeConfirm() {
    document.getElementById('confirm-overlay').style.display = 'none';
    confirmCallback = null;
}
document.getElementById('confirm-ok').addEventListener('click', () => {
    const cb = confirmCallback;
    closeConfirm();
    if (cb) cb();
});

// ── Format helpers ────────────────────────────────────────────────────────
function fmtSize(bytes) {
    if (!bytes) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(2) + ' MB';
}
function fmtDate(str) {
    if (!str) return '—';
    const d = new Date(str.replace(' ', 'T'));
    return d.toLocaleString('en-PH', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
}

// ── Load backup list ──────────────────────────────────────────────────────
async function loadBackupList() {
    const wrap = document.getElementById('backup-table-wrap');
    wrap.innerHTML = '<div class="br-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading backups…</div>';
    try {
        const res  = await fetch(HANDLER + '?action=list');
        const data = await res.json();
        if (!data.success) { wrap.innerHTML = `<div class="br-empty"><i class="fa-solid fa-circle-exclamation"></i><h4>Error</h4><p>${data.message}</p></div>`; return; }

        const backups = data.backups || [];
        document.getElementById('stat-total').textContent   = backups.length;
        document.getElementById('count-inline').textContent = backups.length;
        if (backups.length > 0) {
            const d = new Date(backups[0].created_at.replace(' ','T'));
            document.getElementById('stat-latest').textContent = d.toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
        } else {
            document.getElementById('stat-latest').textContent = 'Never';
        }

        if (backups.length === 0) {
            wrap.innerHTML = `<div class="br-empty"><i class="fa-solid fa-database"></i><h4>No backups yet</h4><p>Create your first backup above.</p></div>`;
            return;
        }

        let rows = backups.map((b, i) => `
            <tr id="row-${i}">
                <td class="mono">${escHtml(b.filename)}</td>
                <td>
                    <span class="br-badge" id="lbl-${i}">${escHtml(b.label || '—')}</span>
                    <input class="rename-input" id="rename-inp-${i}" value="${escHtml(b.label||'')}" style="display:none"
                           onkeydown="if(event.key==='Enter')confirmRename('${escHtml(b.filename)}',${i});if(event.key==='Escape')cancelRename(${i})">
                </td>
                <td>${fmtSize(b.size)}</td>
                <td class="muted">${fmtDate(b.created_at)}</td>
                <td>${escHtml(b.created_by || '—')}</td>
                <td>
                    <div class="br-actions">
                        <a href="${HANDLER}?action=download&file=${encodeURIComponent(b.filename)}" class="br-btn secondary" style="padding:6px 11px;font-size:.78rem;text-decoration:none;">
                            <i class="fa-solid fa-download"></i>
                        </a>
                        <button class="br-btn secondary" style="padding:6px 11px;font-size:.78rem;" onclick="startRename(${i})" id="btn-rename-${i}" title="Rename label">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="br-btn secondary" style="padding:6px 11px;font-size:.78rem;" onclick="restoreBackup('${escHtml(b.filename)}')" title="Restore this backup">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                        <button class="br-btn danger" style="padding:6px 11px;font-size:.78rem;" onclick="deleteBackup('${escHtml(b.filename)}','${escHtml(b.label||b.filename)}')" title="Delete backup">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`).join('');

        wrap.innerHTML = `
            <div class="br-tbl-wrap">
                <table class="br-tbl">
                    <thead><tr>
                        <th>Filename</th><th>Label</th><th>Size</th>
                        <th>Created</th><th>Created By</th><th>Actions</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    } catch(e) {
        wrap.innerHTML = `<div class="br-empty"><i class="fa-solid fa-circle-exclamation"></i><h4>Failed to load</h4><p>${e.message}</p></div>`;
    }
}

// ── Create backup ─────────────────────────────────────────────────────────
async function createBackup() {
    const label = document.getElementById('backup-label').value.trim();
    const btn   = document.getElementById('btn-create');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating…';
    try {
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('label',  label || 'manual');
        const res  = await fetch(HANDLER, { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            toast(data.message, 'success');
            document.getElementById('backup-label').value = '';
            loadBackupList();
        } else {
            toast(data.message, 'error');
        }
    } catch(e) {
        toast('Network error: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Create Backup';
}

// ── Restore from saved file ───────────────────────────────────────────────
function restoreBackup(filename) {
    showConfirm(
        'Restore this backup?',
        `"${filename}" will overwrite your current menu items, staff, orders, and payments. This cannot be undone.`,
        'fa-rotate-left',
        async () => {
            toast('Restoring… please wait.', 'success');
            try {
                const fd = new FormData();
                fd.append('action', 'restore');
                fd.append('file',   filename);
                const res  = await fetch(HANDLER, { method:'POST', body:fd });
                const data = await res.json();
                toast(data.message, data.success ? 'success' : 'error');
                if (data.success) { loadBackupList(); showRestoreBanner(data.restore_source, data.restored_at); }
            } catch(e) {
                toast('Network error: ' + e.message, 'error');
            }
        }
    );
}

// ── Delete backup ─────────────────────────────────────────────────────────
function deleteBackup(filename, label) {
    showConfirm(
        'Delete this backup?',
        `"${label}" will be permanently removed from the server.`,
        'fa-trash',
        async () => {
            try {
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('file',   filename);
                const res  = await fetch(HANDLER, { method:'POST', body:fd });
                const data = await res.json();
                toast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadBackupList();
            } catch(e) {
                toast('Network error: ' + e.message, 'error');
            }
        }
    );
}

// ── Rename label ──────────────────────────────────────────────────────────
function startRename(i) {
    document.getElementById(`lbl-${i}`).style.display = 'none';
    document.getElementById(`rename-inp-${i}`).style.display = 'inline-block';
    document.getElementById(`btn-rename-${i}`).innerHTML = '<i class="fa-solid fa-check"></i>';
    document.getElementById(`btn-rename-${i}`).onclick = () => confirmRename(
        document.getElementById(`rename-inp-${i}`).closest('tr').querySelector('.mono').textContent,
        i
    );
    document.getElementById(`rename-inp-${i}`).focus();
}
function cancelRename(i) {
    document.getElementById(`lbl-${i}`).style.display = '';
    document.getElementById(`rename-inp-${i}`).style.display = 'none';
    document.getElementById(`btn-rename-${i}`).innerHTML = '<i class="fa-solid fa-pen-to-square"></i>';
}
async function confirmRename(filename, i) {
    const newLabel = document.getElementById(`rename-inp-${i}`).value.trim();
    if (!newLabel) { toast('Label cannot be empty.', 'error'); return; }
    try {
        const fd = new FormData();
        fd.append('action', 'rename');
        fd.append('file',   filename);
        fd.append('label',  newLabel);
        const res  = await fetch(HANDLER, { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById(`lbl-${i}`).textContent = data.label;
            cancelRename(i);
            toast('Label updated.', 'success');
        } else {
            toast(data.message, 'error');
        }
    } catch(e) {
        toast('Network error: ' + e.message, 'error');
    }
}

// ── File upload restore ───────────────────────────────────────────────────
function fileSelected(input) {
    uploadedFile = input.files[0] || null;
    document.getElementById('upload-filename').textContent = uploadedFile ? uploadedFile.name : '';
    document.getElementById('btn-upload-restore').disabled = !uploadedFile;
}
function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropzone').classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (!file) return;
    if (!file.name.endsWith('.sql')) { toast('Only .sql files are accepted.', 'error'); return; }
    uploadedFile = file;
    document.getElementById('upload-filename').textContent = file.name;
    document.getElementById('btn-upload-restore').disabled = false;
}
function clearUpload() {
    uploadedFile = null;
    document.getElementById('upload-input').value = '';
    document.getElementById('upload-filename').textContent = '';
    document.getElementById('btn-upload-restore').disabled = true;
}
function uploadRestore() {
    if (!uploadedFile) return;
    showConfirm(
        'Restore from uploaded file?',
        `"${uploadedFile.name}" will overwrite your current data. This cannot be undone.`,
        'fa-file-import',
        async () => {
            const btn = document.getElementById('btn-upload-restore');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Restoring…';
            toast('Restoring… please wait.', 'success');
            try {
                const fd = new FormData();
                fd.append('action',      'restore');
                fd.append('backup_file', uploadedFile);
                const res  = await fetch(HANDLER, { method:'POST', body:fd });
                const data = await res.json();
                toast(data.message, data.success ? 'success' : 'error');
                if (data.success) { clearUpload(); loadBackupList(); showRestoreBanner(data.restore_source, data.restored_at); }
            } catch(e) {
                toast('Network error: ' + e.message, 'error');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Restore from Upload';
        }
    );
}

// ── Load restore logs ─────────────────────────────────────────────────────
async function loadRestoreLogs() {
    const wrap = document.getElementById('restore-table-wrap');
    wrap.innerHTML = '<div class="br-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading restore history…</div>';
    try {
        const res  = await fetch(HANDLER + '?action=restore_logs');
        const data = await res.json();
        const logs = data.logs || [];

        document.getElementById('restore-count-inline').textContent  = logs.length;
        document.getElementById('stat-total-restores').textContent    = logs.length;
        document.getElementById('stat-last-restore-src').textContent  = logs.length
            ? 'last: ' + fmtDate(logs[0].restored_at)
            : 'no restore yet';

        if (logs.length === 0) {
            wrap.innerHTML = `<div class="br-empty"><i class="fa-solid fa-clock-rotate-left"></i><h4>No restores yet</h4><p>Restore history will appear here.</p></div>`;
            return;
        }

        const rows = logs.map(l => `
            <tr>
                <td class="mono">${escHtml(l.filename)}</td>
                <td>${escHtml(l.restored_by)}</td>
                <td class="muted">${fmtDate(l.restored_at)}</td>
                <td><span class="br-badge" style="${l.source === 'upload' ? 'background:#fef3c7;color:#92400e;' : ''}">${l.source}</span></td>
            </tr>`).join('');

        wrap.innerHTML = `
            <div class="br-tbl-wrap">
                <table class="br-tbl">
                    <thead><tr>
                        <th>Filename</th><th>Restored By</th><th>Date & Time</th><th>Source</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    } catch(e) {
        wrap.innerHTML = `<div class="br-empty"><i class="fa-solid fa-circle-exclamation"></i><h4>Failed to load</h4><p>${e.message}</p></div>`;
    }
}

// ── Restore success banner ────────────────────────────────────────────────
function showRestoreBanner(source, restoredAt) {
    const banner  = document.getElementById('restore-success-banner');
    const detail  = document.getElementById('restore-banner-detail');

    const d = new Date(restoredAt.replace(' ', 'T'));
    const formatted = d.toLocaleString('en-PH', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });

    detail.textContent = `Restored from "${source}" on ${formatted}.`;

    // Move banner to top of page content so it's immediately visible
    const page = document.querySelector('.br-page');
    const grid = document.querySelector('.br-grid');
    banner.style.display = 'flex';
    page.insertBefore(banner, grid);

    // Refresh restore history table
    loadRestoreLogs();
}

// ── HTML escape helper ────────────────────────────────────────────────────
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($totalBackups > 0): ?>
    loadBackupList();
    <?php endif; ?>
    loadRestoreLogs();
});
</script>

</body>
</html>