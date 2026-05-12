<?php
$header_username = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$header_fastfood = htmlspecialchars($_SESSION['fastfood_name'] ?? '');
$header_initials = strtoupper(substr($adminProfile['fullname'] ?? $_SESSION['username'] ?? 'A', 0, 1));
$header_pending  = $pending_orders ?? 0;
$header_fullname = htmlspecialchars($adminProfile['fullname'] ?? $_SESSION['username'] ?? '');
$header_email    = htmlspecialchars($adminProfile['email'] ?? '');
?>

<div style="display:flex;flex-direction:row;align-items:center;height:58px;padding:0 24px;background:#fff;border-bottom:1px solid var(--border-color);position:sticky;top:0;z-index:100;gap:16px;">

    <!-- LEFT: Hamburger + Store Name -->
    <div style="display:flex;align-items:center;gap:12px;flex-shrink:0;">
      <button type="button" onclick="toggleSidebar()" style="background:none;border:none;cursor:pointer;padding:6px;display:flex;flex-direction:column;gap:5px;">
            <span style="display:block;width:22px;height:2px;background:var(--text-primary);border-radius:2px;"></span>
            <span style="display:block;width:22px;height:2px;background:var(--text-primary);border-radius:2px;"></span>
            <span style="display:block;width:22px;height:2px;background:var(--text-primary);border-radius:2px;"></span>
        </button>
        <span style="font-weight:700;font-size:15px;color:var(--text-primary);"><?= $header_fastfood ?></span>
    </div>

    <!-- CENTER: Search -->
    <div style="flex:1;display:flex;justify-content:center;">
        <div style="display:flex;align-items:center;gap:8px;background:var(--body-bg);border:1.5px solid var(--border-color);border-radius:24px;padding:0 16px;max-width:420px;width:100%;">
            <i class="fa-solid fa-magnifying-glass" style="font-size:14px;color:var(--text-secondary);"></i>
            <input type="text" placeholder="Search orders, menu, staff…" id="headerSearch"
                style="border:none;background:none;outline:none;font-size:13px;font-family:inherit;color:var(--text-primary);padding:9px 0;width:100%;">
            <span style="font-size:10px;color:var(--text-secondary);background:var(--border-color);padding:2px 7px;border-radius:5px;white-space:nowrap;">Ctrl K</span>
        </div>
    </div>

    <!-- RIGHT: Bell + Avatar -->
    <div style="display:flex;align-items:center;gap:14px;flex-shrink:0;position:relative;">

        <!-- Notification Bell -->
        <div style="position:relative;">
            <button onclick="toggleNotifPanel()" style="background:none;border:none;cursor:pointer;font-size:20px;padding:4px;display:flex;align-items:center;position:relative;">
               <i class="fa-solid fa-bell" style="font-size:18px;color:var(--text-primary);"></i>
                <?php if ($header_pending > 0): ?>
                <span style="position:absolute;top:0;right:0;width:16px;height:16px;background:var(--accent);color:white;font-size:9px;font-weight:800;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid white;"><?= $header_pending ?></span>
                <?php endif; ?>
            </button>

            <!-- Notification Dropdown -->
            <div id="notifDropdown" style="display:none;position:absolute;top:calc(100% + 10px);right:0;width:290px;background:#fff;border:1px solid var(--border-color);border-radius:12px;box-shadow:0 8px 32px rgba(45,10,31,0.13);z-index:999;overflow:hidden;">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--border-color);">
                    <span style="font-weight:700;font-size:13px;">Notifications</span>
                    <button onclick="markAllRead()" style="background:none;border:none;color:var(--accent);font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;">Mark all read</button>
                </div>
                <div style="padding:14px 16px;font-size:12.5px;">
                    <?php if ($header_pending > 0): ?>
                        <p style="color:var(--text-primary);margin:0;">🔔 <strong><?= $header_pending ?> pending order<?= $header_pending > 1 ? 's' : '' ?></strong> need attention.</p>
                    <?php else: ?>
                        <p style="color:var(--text-secondary);text-align:center;margin:0;">🎉 All caught up! No new notifications.</p>
                    <?php endif; ?>
                </div>
                <div style="padding:10px 16px;border-top:1px solid var(--border-color);text-align:center;">
                    <a href="order_history.php" style="font-size:12px;color:var(--accent-dark);font-weight:600;text-decoration:none;display:inline;">View all orders →</a>
                </div>
            </div>
        </div>

        <!-- Avatar — view only profile popup -->
        <div style="position:relative;">
            <div onclick="toggleProfilePopup()" style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent-dark),var(--accent));color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;cursor:pointer;" title="View Profile">
                <?= $header_initials ?>
            </div>

            <!-- Profile Info Popup (view only) -->
            <div id="profilePopup" style="display:none;position:absolute;top:calc(100% + 10px);right:0;width:260px;background:#fff;border:1px solid var(--border-color);border-radius:14px;box-shadow:0 8px 32px rgba(45,10,31,0.13);z-index:999;overflow:hidden;">

                <!-- Header strip -->
                <div style="background:linear-gradient(135deg,var(--accent-dark),var(--accent));padding:20px 16px 14px;text-align:center;">
                    <div style="width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,0.25);color:#fff;font-size:20px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;border:2px solid rgba(255,255,255,0.4);">
                        <?= $header_initials ?>
                    </div>
                    <p style="color:#fff;font-weight:700;font-size:14px;margin:0;"><?= $header_fullname ?: $header_username ?></p>
                    <p style="color:rgba(255,255,255,0.75);font-size:11px;margin:2px 0 0;">Administrator</p>
                </div>

                <!-- Info rows -->
                <div style="padding:12px 16px;display:flex;flex-direction:column;gap:10px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <i class="fa-solid fa-user" style="font-size:15px;color:var(--accent);width:20px;text-align:center;"></i>
                        <div>
                            <p style="font-size:10px;color:var(--text-secondary);margin:0;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Username</p>
                            <p style="font-size:13px;color:var(--text-primary);margin:0;font-weight:600;"><?= $header_username ?></p>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                       <i class="fa-solid fa-envelope" style="font-size:15px;color:var(--accent);width:20px;text-align:center;"></i>
                        <div>
                            <p style="font-size:10px;color:var(--text-secondary);margin:0;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Email</p>
                            <p style="font-size:13px;color:var(--text-primary);margin:0;font-weight:600;"><?= $header_email ?: '—' ?></p>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                     <i class="fa-solid fa-store" style="font-size:15px;color:var(--accent);width:20px;text-align:center;"></i>
                        <div>
                            <p style="font-size:10px;color:var(--text-secondary);margin:0;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Store</p>
                            <p style="font-size:13px;color:var(--text-primary);margin:0;font-weight:600;"><?= $header_fastfood ?></p>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <i class="fa-solid fa-shield-halved" style="font-size:15px;color:var(--accent);width:20px;text-align:center;"></i>
                        <div>
                            <p style="font-size:10px;color:var(--text-secondary);margin:0;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Role</p>
                            <p style="font-size:13px;color:var(--text-primary);margin:0;font-weight:600;">Administrator</p>
                        </div>
                    </div>
                </div>

               <!-- Footer note + Sign Out -->
                <div style="padding:10px 16px;border-top:1px solid var(--border-color);text-align:center;">
                    <p style="font-size:11px;color:var(--text-secondary);margin:0 0 10px;">To edit your info, go to <strong>Account</strong> in the sidebar.</p>
                    <a href="../logout.php" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:8px 14px;background:#fff0f3;border:1.5px solid #fca5a5;border-radius:8px;color:#dc2626;font-size:12px;font-weight:700;text-decoration:none;transition:background 0.2s;"
                       onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff0f3'">
                        <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                    </a>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
function toggleNotifPanel() {
    const d = document.getElementById('notifDropdown');
    const p = document.getElementById('profilePopup');
    if (p) p.style.display = 'none';
    d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
function toggleProfilePopup() {
    const p = document.getElementById('profilePopup');
    const d = document.getElementById('notifDropdown');
    if (d) d.style.display = 'none';
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
function markAllRead() {
    document.querySelectorAll('[style*="background:var(--accent)"]').forEach(el => el.remove());
}
document.addEventListener('click', function(e) {
    ['notifDropdown','profilePopup'].forEach(id => {
        const el = document.getElementById(id);
        if (el && !el.contains(e.target) && !e.target.closest('[onclick*="' + (id === 'notifDropdown' ? 'Notif' : 'Profile') + '"]')) {
            el.style.display = 'none';
        }
    });
});
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('headerSearch').focus();
    }
});
</script>