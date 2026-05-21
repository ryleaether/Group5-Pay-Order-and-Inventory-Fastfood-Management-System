<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/helpers/admindashboard_helpers.php";
require_once __DIR__ . "/../validation.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$db   = new Database();
$conn = $db->connect();

$adminProfile = [];
try {
    $stmt = $conn->prepare("SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id = :id");
    $stmt->bindParam(':id', $admin_id);
    $stmt->execute();
    $adminProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $adminProfile = []; }

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
    <title>Manage Staffs — iPOS</title>
    <link rel="stylesheet" href="../design/admin.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <style>
        /* ===== MANAGE STAFFS ===== */
        .staff-toprow {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .staff-search {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 8px 14px;
            min-width: 260px;
        }
        .staff-search input {
            border: none; outline: none; background: transparent;
            font-size: 14px; color: var(--text-primary); font-family: inherit; width: 100%;
        }
        .staff-search i { color: var(--text-secondary); }

        .btn-add-staff {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: white; border: none; border-radius: 10px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }
        .btn-add-staff:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Stats */
        .staff-stats {
            display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 20px;
        }
        .staff-stat {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            display: flex; align-items: center; gap: 14px;
            min-width: 140px; flex: 1;
            box-shadow: var(--shadow-sm);
        }
        .staff-stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .staff-stat-icon.total    { background: #ede9fe; color: #7c3aed; }
        .staff-stat-icon.active   { background: #dcfce7; color: #16a34a; }
        .staff-stat-icon.cashier  { background: #dbeafe; color: #2563eb; }
        .staff-stat-icon.kitchen  { background: #fef3c7; color: #d97706; }
        .staff-stat-val   { font-size: 26px; font-weight: 800; color: var(--text-primary); line-height: 1; }
        .staff-stat-label { font-size: 11px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; }

        /* Table */
        .staff-table-wrap {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .staff-table {
            width: 100%; border-collapse: collapse;
        }
        .staff-table thead th {
            padding: 13px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            background: var(--body-bg);
            border-bottom: 1px solid var(--border-color);
        }
        .staff-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background 0.15s;
        }
        .staff-table tbody tr:last-child { border-bottom: none; }
        .staff-table tbody tr:hover { background: var(--accent-light); }
        .staff-table td {
            padding: 14px 16px;
            font-size: 14px;
            color: var(--text-primary);
            vertical-align: middle;
            text-align: center;
        }

        .staff-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 800; color: white;
            flex-shrink: 0;
        }
        .staff-name-cell { display: flex; align-items: center; gap: 10px; }
        .staff-name-info .name { font-weight: 700; font-size: 14px; }
        .staff-name-info .sub  { font-size: 12px; color: var(--text-secondary); }

        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .role-cashier { background: #dbeafe; color: #1e40af; }
        .role-kitchen { background: #fef3c7; color: #92400e; }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-active   { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        .shift-text { font-size: 13px; color: var(--text-secondary); }

        .staff-action-btns { display: flex; gap: 6px; }
        .btn-icon {
            width: 32px; height: 32px;
            border: none; border-radius: 8px;
            cursor: pointer; font-size: 13px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .btn-edit   { background: #dbeafe; color: #2563eb; }
        .btn-edit:hover { background: #2563eb; color: white; }
        .btn-delete { background: #fee2e2; color: #dc2626; }
        .btn-delete:hover { background: #dc2626; color: white; }
        .btn-toggle { background: #fef3c7; color: #d97706; }
        .btn-toggle:hover { background: #d97706; color: white; }

        /* Empty */
        .staff-empty {
            text-align: center; padding: 60px 20px;
            color: var(--text-secondary);
        }
        .staff-empty i { font-size: 48px; margin-bottom: 12px; opacity: 0.3; display: block; }
        .staff-empty p { font-size: 15px; font-weight: 600; margin-bottom: 4px; }

        /* Filter tabs */
        .staff-filter-tabs {
            display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap;
        }
        .filter-tab {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px; font-weight: 600;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-secondary);
            cursor: pointer; transition: all 0.2s;
        }
        .filter-tab.active {
            background: var(--accent);
            color: white; border-color: var(--accent);
        }

        /* ===== MODAL ===== */
        .staff-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 5000;
            display: none;
            align-items: center; justify-content: center;
        }
        .staff-modal-overlay.show { display: flex; }
        .staff-modal {
            background: white;
            border-radius: 20px;
            padding: 32px;
            width: 480px;
            max-width: 95vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
            animation: modalPop 0.25s ease;
        }
        @keyframes modalPop { from{transform:scale(0.92);opacity:0} to{transform:scale(1);opacity:1} }
        @keyframes pulse-online {
            0%   { box-shadow: 0 0 0 0 rgba(34,197,94,0.7); }
            70%  { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }
        .staff-modal-title {
            font-size: 18px; font-weight: 800;
            color: var(--text-primary); margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .modal-field { margin-bottom: 14px; }
        .modal-label {
            font-size: 12px; font-weight: 700;
            color: var(--text-secondary); text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 6px; display: block;
        }
        .modal-input {
            width: 100%; padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 10px; font-size: 14px;
            font-family: inherit; color: var(--text-primary);
            background: var(--body-bg);
            box-sizing: border-box; transition: border-color 0.2s;
        }
        .modal-input:focus { outline: none; border-color: var(--accent); background: white; }
        .modal-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .modal-btn {
            flex: 1; padding: 12px;
            border: none; border-radius: 10px;
            font-size: 14px; font-weight: 700;
            cursor: pointer; transition: all 0.2s;
        }
        .modal-btn-save {
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: white;
        }
        .modal-btn-save:hover { opacity: 0.9; }
        .modal-btn-cancel {
            background: var(--body-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .modal-btn-cancel:hover { background: var(--border-color); }

        /* PIN dots in modal */
        .pin-hint { font-size: 12px; color: var(--text-secondary); margin-top: 6px; }
        .pin-show-toggle { cursor:pointer; color:var(--accent); font-size:12px; margin-top:4px; display:inline-block; }

        .toast-staff {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 20px; border-radius: 10px;
            font-size: 13px; font-weight: 600; z-index: 9999;
            color: white; opacity: 0; transform: translateY(20px);
            transition: all 0.3s;
        }
        .toast-staff.show { opacity: 1; transform: translateY(0); }
        .toast-staff.success { background: #16a34a; }
        .toast-staff.error   { background: #dc2626; }
        .toast-staff.info    { background: #2563eb; }
    </style>
</head>
<body>
<div class="dashboard">

    <?php echo $sidebar->render('staffs'); ?>

        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h1><i class="fa-solid fa-users" style="margin-right:8px;"></i>Manage Staffs</h1>
                <p class="subtitle">Add, edit, and manage your team members</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="staff-stats" id="staffStats">
            <div class="staff-stat">
                <div class="staff-stat-icon total"><i class="fa-solid fa-users"></i></div>
                <div><div class="staff-stat-val" id="stat-total">—</div><div class="staff-stat-label">Total Staff</div></div>
            </div>
            <div class="staff-stat">
                <div class="staff-stat-icon active"><i class="fa-solid fa-circle-check"></i></div>
                <div><div class="staff-stat-val" id="stat-active">—</div><div class="staff-stat-label">Active</div></div>
            </div>
            <div class="staff-stat">
                <div class="staff-stat-icon cashier"><i class="fa-solid fa-cash-register"></i></div>
                <div><div class="staff-stat-val" id="stat-cashier">—</div><div class="staff-stat-label">Cashiers</div></div>
            </div>
            <div class="staff-stat">
                <div class="staff-stat-icon kitchen"><i class="fa-solid fa-kitchen-set"></i></div>
                <div><div class="staff-stat-val" id="stat-kitchen">—</div><div class="staff-stat-label">Kitchen</div></div>
            </div>
        </div>

        <!-- Top Row -->
        <div class="staff-toprow">
            <div class="staff-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search by name or role…" oninput="filterStaffs()">
            </div>
            <button class="btn-add-staff" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Add Staff
            </button>
        </div>

        <!-- Filter Tabs -->
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
            <div class="staff-filter-tabs" id="staffFilterTabs">
                <div class="filter-tab active" data-filter="All"      onclick="setFilter('All', this)">All</div>
                <div class="filter-tab"        data-filter="Active"   onclick="setFilter('Active', this)">Active</div>
                <div class="filter-tab"        data-filter="Inactive" onclick="setFilter('Inactive', this)">Inactive</div>
                <div class="filter-tab"        data-filter="Cashier"  onclick="setFilter('Cashier', this)">Cashier</div>
                <div class="filter-tab"        data-filter="Kitchen"  onclick="setFilter('Kitchen', this)">Kitchen</div>
            </div>
            <button onclick="toggleLogPanel()" id="logToggleBtn"
                    style="display:inline-flex; align-items:center; gap:7px; padding:8px 16px;
                           background:var(--card-bg); border:1px solid var(--border-color);
                           border-radius:8px; font-size:13px; font-weight:700; color:var(--text-primary);
                           cursor:pointer; transition:all 0.2s;">
                <i class="fa-solid fa-clipboard-list"></i> Staff Log
            </button>
        </div>

        <!-- Table -->
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th style="text-align:center;">Staff Member</th>
                        <th style="text-align:center;">Role</th>
                        <th style="text-align:center;">Shift</th>
                        <th style="text-align:center;">Attendance</th>
                        <th style="text-align:center;">Employment</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody">
                    <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-secondary);">
                        <i class="fa-solid fa-spinner fa-spin"></i> Loading staffs…
                    </td></tr>
                </tbody>
            </table>
</div>

        <!-- Staff Log Panel -->
        <div id="staffLogPanel" style="display:none; margin-top:24px;">
            <div style="background:var(--card-bg); border:1px solid var(--border-color); border-radius:16px; overflow:hidden;">
                <div style="padding:18px 20px; border-bottom:1px solid var(--border-color);
                            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <div>
                        <div style="font-weight:800; font-size:16px; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-clipboard-list" style="color:#6366f1;"></i> Staff Login Log
                        </div>
                        <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">History of staff login and logout sessions</div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <select id="logFilterStaff" onchange="loadLogs()"
                                style="padding:7px 12px; border:1px solid var(--border-color); border-radius:8px;
                                       font-size:13px; background:var(--card-bg); color:var(--text-primary); cursor:pointer;">
                            <option value="">All Staff</option>
                        </select>
                        <input type="date" id="logFilterDate" onchange="loadLogs()"
                               style="padding:7px 12px; border:1px solid var(--border-color); border-radius:8px;
                                      font-size:13px; background:var(--card-bg); color:var(--text-primary); cursor:pointer;">
                        <button onclick="document.getElementById('logFilterDate').value=''; document.getElementById('logFilterStaff').value=''; loadLogs();"
                                style="padding:7px 12px; border:1px solid var(--border-color); border-radius:8px;
                                       font-size:13px; background:transparent; color:var(--text-secondary); cursor:pointer;">
                            Clear
                        </button>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:var(--accent-light);">
                                <th style="padding:12px 16px; text-align:center; font-size:11px; font-weight:700;
                                           color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Staff</th>
                                <th style="padding:12px 16px; text-align:center; font-size:11px; font-weight:700;
                                           color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Role</th>
                                <th style="padding:12px 16px; text-align:center; font-size:11px; font-weight:700;
                                           color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Login</th>
                                <th style="padding:12px 16px; text-align:center; font-size:11px; font-weight:700;
                                           color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Logout</th>
                                <th style="padding:12px 16px; text-align:center; font-size:11px; font-weight:700;
                                           color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Duration</th>
                                <th style="padding:12px 16px; text-align:center; font-size:11px; font-weight:700;
                                           color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Shift</th>
                                <th style="padding:12px 16px; text-align:center; font-size:11px; font-weight:700;
                                           color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="logTableBody">
                            <tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-secondary);">
                                <i class="fa-solid fa-spinner fa-spin"></i> Loading log…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?= $sidebar->renderClose() ?>
</div>

<!-- Add / Edit Staff Modal -->
<div class="staff-modal-overlay" id="staffModal">
    <div class="staff-modal">
        <div class="staff-modal-title">
            <i class="fa-solid fa-user-plus" id="modalIcon"></i>
            <span id="modalTitle">Add Staff</span>
        </div>
        <input type="hidden" id="editStaffId" value="">

        <div class="modal-field">
            <label class="modal-label"><i class="fa-solid fa-id-card"></i> Full Name *</label>
            <input class="modal-input" type="text" id="mFullname" placeholder="Staff full name">
        </div>
        <div class="modal-row">
            <div class="modal-field">
                <label class="modal-label"><i class="fa-solid fa-tag"></i> Role *</label>
                <select class="modal-input" id="mRole">
                    <option value="Cashier">Cashier</option>
                    <option value="Kitchen">Kitchen</option>
                </select>
            </div>
            <div class="modal-field">
                <label class="modal-label"><i class="fa-solid fa-toggle-on"></i> Status</label>
                <select class="modal-input" id="mStatus">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
        </div>
        <div class="modal-row">
            <div class="modal-field">
                <label class="modal-label"><i class="fa-solid fa-briefcase"></i> Employment Type *</label>
                <select class="modal-input" id="mEmploymentType">
                    <option value="Full-time">Full-time / Regular</option>
                    <option value="Part-time">Part-time</option>
                </select>
            </div>
        </div>
        <div class="modal-row">
            <div class="modal-field">
                <label class="modal-label"><i class="fa-solid fa-clock"></i> Shift Start</label>
                <input class="modal-input" type="time" id="mShiftStart">
            </div>
            <div class="modal-field">
                <label class="modal-label"><i class="fa-solid fa-clock"></i> Shift End</label>
                <input class="modal-input" type="time" id="mShiftEnd">
            </div>
        </div>
        <div class="modal-field">
            <label class="modal-label"><i class="fa-solid fa-key"></i> PIN <span id="pinLabel">(4 digits, required)</span></label>
            <input class="modal-input" type="password" id="mPin" maxlength="4" inputmode="numeric" pattern="[0-9]*" placeholder="4-digit PIN">
            <span class="pin-show-toggle" onclick="togglePinVisibility()"><i class="fa-solid fa-eye" id="pinEyeIcon"></i> Show PIN</span>
            <div class="pin-hint" id="pinHint">Staff will use this PIN to log in.</div>
        </div>

        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal()">
                <i class="fa-solid fa-xmark"></i> Cancel
            </button>
            <button class="modal-btn modal-btn-save" onclick="saveStaff()">
                <i class="fa-solid fa-floppy-disk"></i> Save Staff
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="staff-modal-overlay" id="deleteModal">
    <div class="staff-modal" style="max-width:380px; text-align:center;">
        <div style="font-size:48px; margin-bottom:12px;">🗑️</div>
        <h3 style="font-size:18px; font-weight:800; margin-bottom:8px;">Move to Trash?</h3>
        <p style="color:var(--text-secondary); font-size:14px; margin-bottom:24px;">
            This will move <strong id="deleteStaffName"></strong> to the trash. You can restore them within 30 days.
        </p>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="document.getElementById('deleteModal').classList.remove('show')">
                Cancel
            </button>
            <button class="modal-btn" style="background:#dc2626;color:white;" onclick="confirmDelete()">
                <i class="fa-solid fa-trash"></i> Move to Trash
            </button>
        </div>
        <input type="hidden" id="deleteStaffId">
    </div>
</div>

<?php include __DIR__ . '/helpers/pin_modals.php'; ?>

<script>
let allStaffs = [];
let currentFilter = 'All';
let deleteTarget = null;

// ===== LOAD STAFFS =====
async function loadStaffs() {
    try {
        const res  = await fetch('helpers/staff_helpers.php?action=list');
        const data = await res.json();
        if (!data.success) { showStaffToast('Failed to load staffs', 'error'); return; }
        allStaffs = data.staffs || [];
        updateStats();
        renderTable();
        populateLogStaffFilter(allStaffs);
    } catch(e) {
        showStaffToast('Connection error', 'error');
    }
}

function updateStats() {
    const active  = allStaffs.filter(s => s.status === 'Active');
    const cashier = allStaffs.filter(s => s.role === 'Cashier');
    const kitchen = allStaffs.filter(s => s.role === 'Kitchen');
    document.getElementById('stat-total').textContent   = allStaffs.length;
    document.getElementById('stat-active').textContent  = active.length;
    document.getElementById('stat-cashier').textContent = cashier.length;
    document.getElementById('stat-kitchen').textContent = kitchen.length;
}

function setFilter(filter, el) {
    currentFilter = filter;
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    renderTable();
}

function filterStaffs() { renderTable(); }

function renderTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    let filtered = allStaffs.filter(s => {
        const matchFilter = currentFilter === 'All' || s.status === currentFilter || s.role === currentFilter;
        const matchSearch = !q || s.fullname.toLowerCase().includes(q) || s.role.toLowerCase().includes(q);
        return matchFilter && matchSearch;
    });

    const tbody = document.getElementById('staffTableBody');
    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6">
            <div class="staff-empty">
                <i class="fa-solid fa-users-slash"></i>
                <p>No staffs found</p>
                <span style="font-size:13px;">Try a different search or add a new staff member.</span>
            </div>
        </td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(s => {
        const initial = s.fullname.charAt(0).toUpperCase();
        const isOnline = s.is_online == 1;
        const roleBadge = s.role === 'Cashier'
            ? `<span class="role-badge role-cashier"><i class="fa-solid fa-cash-register"></i> Cashier</span>`
            : `<span class="role-badge role-kitchen"><i class="fa-solid fa-kitchen-set"></i> Kitchen</span>`;
        const shift = (s.shift_start && s.shift_end)
            ? `<span class="shift-text">${fmtTime(s.shift_start)} – ${fmtTime(s.shift_end)}</span>`
            : `<span class="shift-text" style="opacity:0.4;">—</span>`;
        const employmentBadge = s.employment_type === 'Part-time'
            ? `<span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:#fef3c7;color:#92400e;">
                   <i class="fa-solid fa-clock"></i> Part-time
               </span>`
            : `<span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:#ede9fe;color:#6d28d9;">
                   <i class="fa-solid fa-briefcase"></i> Full-time
               </span>`;

        const onlineDot = isOnline
            ? `<span style="position:absolute;bottom:1px;right:1px;width:11px;height:11px;border-radius:50%;
                            background:#22c55e;border:2px solid white;
                            box-shadow:0 0 0 0 rgba(34,197,94,0.7);animation:pulse-online 1.5s infinite;"></span>`
            : '';

        return `<tr>
            <td>
                <div class="staff-name-cell">
                    <div class="staff-avatar" style="position:relative;">${initial}${onlineDot}</div>
                    <div class="staff-name-info">
                        <div class="name">${escHtml(s.fullname)}</div>
                    </div>
                </div>
            </td>
            <td>${roleBadge}</td>
            <td>${shift}</td>
            <td>${getShiftStatus(s)}</td>
            <td>${employmentBadge}</td>
            <td>
                <div class="staff-action-btns">
                    <button class="btn-icon btn-edit" onclick="openEditModal(${s.staff_id})" title="Edit">
    <i class="fa-solid fa-pen"></i>
</button>
            
                    <button class="btn-icon btn-delete" onclick="openDeleteModal(${s.staff_id}, '${escHtml(s.fullname)}')" title="Remove">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ===== ADD / EDIT MODAL =====
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Staff';
    document.getElementById('modalIcon').className = 'fa-solid fa-user-plus';
    document.getElementById('editStaffId').value = '';
    document.getElementById('mFullname').value = '';
    document.getElementById('mRole').value = 'Cashier';
    document.getElementById('mStatus').value = 'Active';
    document.getElementById('mShiftStart').value = '';
    document.getElementById('mShiftEnd').value = '';
    document.getElementById('mEmploymentType').value = 'Full-time';
    document.getElementById('mPin').value = '';
    document.getElementById('pinLabel').textContent = '(4 digits, required)';
    document.getElementById('pinHint').textContent = 'Staff will use this PIN to log in.';
    document.getElementById('staffModal').classList.add('show');
}

function openEditModal(staffId) {
    const s = allStaffs.find(x => x.staff_id == staffId);
    if (!s) return;
    document.getElementById('modalTitle').textContent = 'Edit Staff';
    document.getElementById('modalIcon').className = 'fa-solid fa-user-pen';
    document.getElementById('editStaffId').value = staffId;
    document.getElementById('mFullname').value = s.fullname;
    document.getElementById('mRole').value = s.role;
    document.getElementById('mStatus').value = s.status;
    document.getElementById('mShiftStart').value = s.shift_start || '';
    document.getElementById('mShiftEnd').value = s.shift_end || '';
    document.getElementById('mEmploymentType').value = s.employment_type || 'Full-time';
    document.getElementById('mPin').value = '';
    document.getElementById('pinLabel').textContent = '(leave blank to keep current PIN)';
    document.getElementById('pinHint').textContent = 'Only fill if you want to change the PIN.';
    document.getElementById('staffModal').classList.add('show');
}

function closeModal() {
    document.getElementById('staffModal').classList.remove('show');
}

function togglePinVisibility() {
    const input = document.getElementById('mPin');
    const icon  = document.getElementById('pinEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-solid fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa-solid fa-eye';
    }
}

async function saveStaff() {
    const staffId    = document.getElementById('editStaffId').value;
    const fullname   = document.getElementById('mFullname').value.trim();
    const role       = document.getElementById('mRole').value;
    const status     = document.getElementById('mStatus').value;
    const shiftStart = document.getElementById('mShiftStart').value;
    const shiftEnd   = document.getElementById('mShiftEnd').value;
    const pin        = document.getElementById('mPin').value.trim();
    const isEdit     = !!staffId;

    if (!fullname) { showStaffToast('Full name is required', 'error'); return; }
    if (!isEdit && (!pin || !/^\d{4}$/.test(pin))) {
        showStaffToast('PIN must be exactly 4 digits', 'error'); return;
    }
    if (pin && !/^\d{4}$/.test(pin)) {
        showStaffToast('PIN must be exactly 4 digits', 'error'); return;
    }

   const employmentType = document.getElementById('mEmploymentType').value;
    const action = isEdit ? 'edit' : 'add';
    const params = new URLSearchParams({ action, fullname, role, status, employment_type: employmentType, shift_start: shiftStart || '', shift_end: shiftEnd || '' });
    console.log('Saving staff with employment_type:', employmentType); // debug
    if (pin) params.append('pin', pin);
    if (isEdit) params.append('staff_id', staffId);

    try {
        const res  = await fetch('helpers/staff_helpers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        });
        const data = await res.json();
        if (data.success) {
            showStaffToast(isEdit ? 'Staff updated! ✅' : 'Staff added! 🎉', 'success');
            closeModal();
            loadStaffs();
        } else {
            showStaffToast(data.message || 'Failed to save', 'error');
        }
    } catch(e) {
        showStaffToast('Connection error', 'error');
    }
}


// ===== DELETE =====
function openDeleteModal(staffId, name) {
    document.getElementById('deleteStaffId').value = staffId;
    document.getElementById('deleteStaffName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
}

async function confirmDelete() {
    const staffId = document.getElementById('deleteStaffId').value;
    try {
        const fd = new FormData();
        fd.append('action', 'soft_delete');
        fd.append('type', 'staff');
        fd.append('id', staffId);
        const res  = await fetch('soft_delete_handler.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            showStaffToast(data.message || 'Staff moved to trash', 'success');
            document.getElementById('deleteModal').classList.remove('show');
            loadStaffs();
        } else {
            showStaffToast(data.message || 'Failed to delete', 'error');
        }
    } catch(e) { showStaffToast('Connection error', 'error'); }
}

// ===== HELPERS =====
function getShiftStatus(s) {
    const today = new Date().toLocaleDateString('en-CA');
    const lastLogin = s.last_login_at ? s.last_login_at.slice(0, 10) : null;
    const loggedInToday = lastLogin === today;

    if (s.is_online == 1 || loggedInToday) {
        return `<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#16a34a;">
                    <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;flex-shrink:0;"></span>
                    Present
                </span>`;
    }

    return `<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#dc2626;">
                <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;flex-shrink:0;"></span>
                Absent
            </span>`;
}

function fmtTime(t) {
    if (!t) return '—';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    return `${hr % 12 || 12}:${m} ${hr < 12 ? 'AM' : 'PM'}`;
}
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showStaffToast(msg, type) {
    const el = document.createElement('div');
    el.className = 'toast-staff ' + type;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add('show'), 10);
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 2800);
}

// Close modals on overlay click
['staffModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('show');
    });
});

// Init
loadStaffs();
// Auto-refresh every 60s so attendance status (On Shift / Absent / Late) stays current
setInterval(loadStaffs, 60000);

/* ================================================================
   STAFF LOG
================================================================ */
let logVisible = false;

function toggleLogPanel() {
    logVisible = !logVisible;
    const panel = document.getElementById('staffLogPanel');
    const btn   = document.getElementById('logToggleBtn');
    panel.style.display = logVisible ? 'block' : 'none';
    btn.style.background = logVisible ? '#6366f1' : 'var(--card-bg)';
    btn.style.color      = logVisible ? 'white'   : 'var(--text-primary)';
    btn.style.borderColor= logVisible ? '#6366f1' : 'var(--border-color)';
    if (logVisible) loadLogs();
}

async function loadLogs() {
    const staffId = document.getElementById('logFilterStaff').value;
    const date    = document.getElementById('logFilterDate').value;
    const tbody   = document.getElementById('logTableBody');
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">
        <i class="fa-solid fa-spinner fa-spin"></i> Loading…</td></tr>`;

    let url = `helpers/staff_helpers.php?action=get_logs`;
    if (staffId) url += `&staff_id=${staffId}`;
    if (date)    url += `&date=${date}`;

    const res  = await fetch(url);
    const text = await res.text();
    console.log('Staff log raw response:', text);
    let data;
    try { data = JSON.parse(text); } catch(e) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:30px;color:red;">
            Error: ${text}</td></tr>`;
        return;
    }

    if (!data.success || !data.logs.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-secondary);">
            No log entries found.</td></tr>`;
        return;
    }

    tbody.innerHTML = data.logs.map(log => {
        const loginTime  = new Date(log.login_at);
        const logoutTime = log.logout_at ? new Date(log.logout_at) : null;

        const fmtDT = d => d.toLocaleString('en-PH', {
            month:'short', day:'numeric', hour:'numeric', minute:'2-digit', hour12:true
        });

        const duration = log.duration_minutes != null
            ? (log.duration_minutes >= 60
                ? `${Math.floor(log.duration_minutes/60)}h ${log.duration_minutes%60}m`
                : `${log.duration_minutes}m`)
            : '—';

        const logoutCell = logoutTime
            ? `<span style="color:var(--text-primary);">${fmtDT(logoutTime)}</span>`
            : `<span style="display:inline-flex;align-items:center;gap:5px;color:#16a34a;font-weight:700;">
                   <span style="width:7px;height:7px;border-radius:50%;background:#22c55e;animation:pulse-online 1.5s infinite;display:inline-block;"></span>
                   Active
               </span>`;

        const shiftCell = (log.shift_start && log.shift_end)
            ? `<span style="font-size:12px;color:var(--text-secondary);">${fmtTime(log.shift_start)} – ${fmtTime(log.shift_end)}</span>`
            : `<span style="opacity:0.4;">—</span>`;

        // Shift compliance — use server-calculated late_minutes
        let statusBadge = '';
        const lateMin = parseInt(log.late_minutes) || 0;
        if (!log.shift_start || !log.shift_end) {
            statusBadge = `<span style="opacity:0.4;font-size:12px;">— No Shift Set</span>`;
        } else if (lateMin <= 0) {
            statusBadge = `<span style="padding:3px 10px;border-radius:20px;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;">✅ On Time</span>`;
        } else if (lateMin <= 15) {
            statusBadge = `<span style="padding:3px 10px;border-radius:20px;background:#fef9c3;color:#854d0e;font-size:11px;font-weight:700;">⚠️ Late ${lateMin}m</span>`;
        } else {
            statusBadge = `<span style="padding:3px 10px;border-radius:20px;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;">🔴 Late ${lateMin}m</span>`;
        }

        const roleBadge = log.role === 'Cashier'
            ? `<span class="role-badge role-cashier"><i class="fa-solid fa-cash-register"></i> Cashier</span>`
            : `<span class="role-badge role-kitchen"><i class="fa-solid fa-kitchen-set"></i> Kitchen</span>`;

        return `<tr style="border-bottom:1px solid var(--border-color);">
            <td style="padding:12px 16px;text-align:center;">
                <div style="font-weight:700;">${escHtml(log.fullname)}</div>
            </td>
            <td style="padding:12px 16px;text-align:center;">${roleBadge}</td>
            <td style="padding:12px 16px;text-align:center;color:var(--text-primary);">${fmtDT(loginTime)}</td>
            <td style="padding:12px 16px;text-align:center;">${logoutCell}</td>
            <td style="padding:12px 16px;text-align:center;font-weight:600;">${duration}</td>
            <td style="padding:12px 16px;text-align:center;">${shiftCell}</td>
            <td style="padding:12px 16px;text-align:center;">${statusBadge}</td>
        </tr>`;
    }).join('');
}

function populateLogStaffFilter(staffs) {
    const sel = document.getElementById('logFilterStaff');
    const cur = sel.value;
    sel.innerHTML = '<option value="">All Staff</option>' +
        staffs.map(s => `<option value="${s.staff_id}" ${s.staff_id == cur ? 'selected' : ''}>${escHtml(s.fullname)}</option>`).join('');
}
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