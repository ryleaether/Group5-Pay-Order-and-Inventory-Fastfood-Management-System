<?php
$header_username = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$header_fastfood = htmlspecialchars($_SESSION['fastfood_name'] ?? '');
$header_initials = strtoupper(substr($adminProfile['fullname'] ?? $_SESSION['username'] ?? 'A', 0, 1));
$header_pending  = $pending_orders ?? 0;
$header_fullname = htmlspecialchars($adminProfile['fullname'] ?? $_SESSION['username'] ?? '');
$header_email    = htmlspecialchars($adminProfile['email'] ?? '');

// Pull profile photo directly from DB to stay in sync with sidebar
$header_photo = '';
try {
    $db_h   = new Database();
    $conn_h = $db_h->connect();
    $stmt_h = $conn_h->prepare("SELECT profile_photo FROM admin_extended WHERE admin_id = :id");
    $stmt_h->execute([':id' => $_SESSION['admin_id']]);
    $row_h  = $stmt_h->fetch(PDO::FETCH_ASSOC);
    $header_photo = $row_h['profile_photo'] ?? '';
} catch (Exception $e) {
    $header_photo = '';
}
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
    <div style="flex:1;display:flex;justify-content:center;position:relative;">
        <div style="display:flex;align-items:center;gap:8px;background:var(--body-bg);border:1.5px solid var(--border-color);border-radius:24px;padding:0 16px;max-width:420px;width:100%;">
            <i class="fa-solid fa-magnifying-glass" style="font-size:14px;color:var(--text-secondary);"></i>
            <input type="text" placeholder="Search orders, menu, staff…" id="headerSearch" autocomplete="off"
                style="border:none;background:none;outline:none;font-size:13px;font-family:inherit;color:var(--text-primary);padding:9px 0;width:100%;">
            <span style="font-size:10px;color:var(--text-secondary);background:var(--border-color);padding:2px 7px;border-radius:5px;white-space:nowrap;">Ctrl K</span>
        </div>

        <!-- Search Results Dropdown -->
        <div id="searchDropdown" style="display:none;position:absolute;top:calc(100% + 8px);left:50%;transform:translateX(-50%);width:480px;max-width:95vw;background:#fff;border:1px solid var(--border-color);border-radius:14px;box-shadow:0 8px 32px rgba(45,10,31,0.13);z-index:9999;overflow:hidden;max-height:420px;overflow-y:auto;">
            <div id="searchLoading" style="display:none;padding:20px;text-align:center;color:var(--text-secondary);font-size:13px;">
                <i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Searching...
            </div>
            <div id="searchEmpty" style="display:none;padding:20px;text-align:center;color:var(--text-secondary);font-size:13px;">
                <i class="fa-solid fa-face-frown" style="margin-right:6px;"></i> No results found.
            </div>
            <div id="searchResults"></div>
        </div>
    </div>

    <!-- RIGHT: Bell + Avatar -->
    <div style="display:flex;align-items:center;gap:14px;flex-shrink:0;position:relative;">

        <!-- Notification Bell -->
        <div style="position:relative;">
            <button onclick="toggleNotifPanel()" id="notifBellBtn" style="background:none;border:none;cursor:pointer;padding:4px;display:flex;align-items:center;position:relative;">
                <i class="fa-solid fa-bell" id="notifBellIcon" style="font-size:18px;color:var(--text-primary);transition:color 0.2s;"></i>
                <span id="notifBadge" style="display:none;position:absolute;top:0;right:0;min-width:16px;height:16px;background:var(--accent);color:white;font-size:9px;font-weight:800;border-radius:50%;visibility:hidden;align-items:center;justify-content:center;border:2px solid white;padding:0 2px;"></span>
            </button>

            <!-- Notification Dropdown -->
            <div id="notifDropdown" style="display:none;position:absolute;top:calc(100% + 10px);right:0;width:320px;background:#fff;border:1px solid var(--border-color);border-radius:14px;box-shadow:0 8px 32px rgba(45,10,31,0.13);z-index:999;overflow:hidden;">

                <!-- Header -->
                <div style="display:flex;justify-content:space-between;align-items:center;padding:13px 16px;border-bottom:1px solid var(--border-color);">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="font-weight:700;font-size:13px;">Notifications</span>
                        <span id="notifHeaderBadge" style="display:none;background:var(--accent);color:#fff;font-size:10px;font-weight:700;border-radius:20px;padding:1px 7px;"></span>
                    </div>
                    <button onclick="markAllRead()" style="background:none;border:none;color:var(--accent);font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;">Mark all read</button>
                </div>

                <!-- List -->
                <div id="notifList" style="max-height:340px;overflow-y:auto;">
                    <div id="notifSkeleton" style="padding:16px;display:flex;flex-direction:column;gap:12px;">
                        <!-- skeleton rows -->
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <div style="display:flex;gap:10px;align-items:center;opacity:0.4;animation:pulse 1.4s infinite;">
                            <div style="width:36px;height:36px;border-radius:50%;background:var(--border-color);flex-shrink:0;"></div>
                            <div style="flex:1;display:flex;flex-direction:column;gap:5px;">
                                <div style="height:11px;background:var(--border-color);border-radius:4px;width:70%;"></div>
                                <div style="height:10px;background:var(--border-color);border-radius:4px;width:50%;"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div id="notifItems" style="display:none;"></div>
                    <div id="notifEmpty" style="display:none;padding:28px 16px;text-align:center;color:var(--text-secondary);font-size:13px;">
                        <i class="fa-solid fa-check-circle" style="font-size:24px;margin-bottom:8px;display:block;color:#10b981;"></i>
                        All caught up — no new notifications!
                    </div>
                </div>

                <!-- Footer -->
                <div style="padding:10px 16px;border-top:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
                    <span id="notifLastUpdated" style="font-size:10px;color:var(--text-secondary);"></span>
                    <a href="order_history.php" style="font-size:12px;color:var(--accent-dark);font-weight:600;text-decoration:none;">View all orders →</a>
                </div>
            </div>
        </div>

        <!-- Avatar -->
        <div style="position:relative;">
            <div onclick="toggleProfilePopup()"
                 style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent-dark),var(--accent));
                        color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;
                        justify-content:center;cursor:pointer;overflow:hidden;flex-shrink:0;" title="View Profile">
                <?php if (!empty($header_photo)): ?>
                    <img src="/<?= htmlspecialchars(ltrim($header_photo, '/')) ?>"
                         alt="Profile"
                         style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                <?php else: ?>
                    <?= $header_initials ?>
                <?php endif; ?>
            </div>

            <!-- Profile Info Popup -->
            <div id="profilePopup" style="display:none;position:absolute;top:calc(100% + 10px);right:0;width:260px;background:#fff;border:1px solid var(--border-color);border-radius:14px;box-shadow:0 8px 32px rgba(45,10,31,0.13);z-index:999;overflow:hidden;">

                <!-- Header strip -->
                <div style="background:linear-gradient(135deg,var(--accent-dark),var(--accent));padding:20px 16px 14px;text-align:center;">
                    <div style="width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,0.25);color:#fff;
                                font-size:20px;font-weight:800;display:flex;align-items:center;justify-content:center;
                                margin:0 auto 8px;border:2px solid rgba(255,255,255,0.4);overflow:hidden;">
                        <?php if (!empty($header_photo)): ?>
                            <img src="/<?= htmlspecialchars(ltrim($header_photo, '/')) ?>"
                                 alt="Profile"
                                 style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                        <?php else: ?>
                            <?= $header_initials ?>
                        <?php endif; ?>
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

                <!-- Footer -->
                <div style="padding:10px 16px;border-top:1px solid var(--border-color);text-align:center;">
                    <p style="font-size:11px;color:var(--text-secondary);margin:0 0 10px;">To edit your info, go to <strong>Account</strong> in the sidebar.</p>
                    <a href="#" onclick="confirmLogout()" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:8px 14px;background:#fff0f3;border:1.5px solid #fca5a5;border-radius:8px;color:#dc2626;font-size:12px;font-weight:700;text-decoration:none;transition:background 0.2s;"
                       onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff0f3'">
                        <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                    </a>
                </div>

            </div>
        </div>

    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 0.4; }
    50%       { opacity: 0.8; }
}
@keyframes bellShake {
    0%,100% { transform: rotate(0); }
    20%     { transform: rotate(-18deg); }
    40%     { transform: rotate(18deg); }
    60%     { transform: rotate(-10deg); }
    80%     { transform: rotate(10deg); }
}
.notif-item-new {
    background: #fef9f0 !important;
    border-left: 3px solid var(--accent) !important;
}
.notif-item-new:hover {
    background: #fef3e2 !important;
}
</style>

<script>
// ── SEARCH (unchanged) ──────────────────────────────────────────────────────
let searchTimer = null;

document.getElementById('headerSearch').addEventListener('input', function () {
    const q = this.value.trim();
    clearTimeout(searchTimer);

    if (q.length < 2) {
        document.getElementById('searchDropdown').style.display = 'none';
        return;
    }

    document.getElementById('searchDropdown').style.display = 'block';
    document.getElementById('searchLoading').style.display  = 'block';
    document.getElementById('searchEmpty').style.display    = 'none';
    document.getElementById('searchResults').innerHTML      = '';

    searchTimer = setTimeout(() => {
        fetch('helpers/search.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                document.getElementById('searchLoading').style.display = 'none';
                const results = document.getElementById('searchResults');
                results.innerHTML = '';
                let hasResults = false;

                if (data.orders && data.orders.length > 0) {
                    hasResults = true;
                    results.innerHTML += `<div style="padding:8px 16px 4px;font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.08em;background:var(--body-bg);"><i class="fa-solid fa-receipt" style="margin-right:5px;"></i>Orders</div>`;
                    data.orders.forEach(o => {
                        results.innerHTML += `
                        <a href="order_history.php" style="display:flex;align-items:center;gap:12px;padding:10px 16px;text-decoration:none;border-bottom:1px solid var(--border-color);background:#fff;transition:background 0.15s;"
                           onmouseover="this.style.background='var(--body-bg)'" onmouseout="this.style.background='#fff'">
                            <div style="width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--accent-dark),var(--accent));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fa-solid fa-receipt" style="color:#fff;font-size:13px;"></i>
                            </div>
                            <div>
                                <p style="margin:0;font-size:13px;font-weight:600;color:var(--text-primary);">Order #${o.queue_number} — ${o.customer_name || 'Guest'}</p>
                                <p style="margin:0;font-size:11px;color:var(--text-secondary);">₱${parseFloat(o.total_amount).toLocaleString()} · <strong>${o.order_status}</strong></p>
                            </div>
                        </a>`;
                    });
                }

                if (data.menu && data.menu.length > 0) {
                    hasResults = true;
                    results.innerHTML += `<div style="padding:8px 16px 4px;font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.08em;background:var(--body-bg);"><i class="fa-solid fa-utensils" style="margin-right:5px;"></i>Menu Items</div>`;
                    data.menu.forEach(m => {
                        results.innerHTML += `
                        <a href="menu_list.php" style="display:flex;align-items:center;gap:12px;padding:10px 16px;text-decoration:none;border-bottom:1px solid var(--border-color);background:#fff;transition:background 0.15s;"
                           onmouseover="this.style.background='var(--body-bg)'" onmouseout="this.style.background='#fff'">
                            <div style="width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,#f59e0b,#f97316);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fa-solid fa-utensils" style="color:#fff;font-size:13px;"></i>
                            </div>
                            <div>
                                <p style="margin:0;font-size:13px;font-weight:600;color:var(--text-primary);">${m.item_name}</p>
                                <p style="margin:0;font-size:11px;color:var(--text-secondary);">₱${parseFloat(m.price).toLocaleString()} · ${m.category} · Stock: ${m.stock_quantity}</p>
                            </div>
                        </a>`;
                    });
                }

                if (data.staff && data.staff.length > 0) {
                    hasResults = true;
                    results.innerHTML += `<div style="padding:8px 16px 4px;font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.08em;background:var(--body-bg);"><i class="fa-solid fa-users" style="margin-right:5px;"></i>Staff</div>`;
                    data.staff.forEach(s => {
                        const initials = s.fullname.charAt(0).toUpperCase();
                        results.innerHTML += `
                        <a href="manage_staffs.php" style="display:flex;align-items:center;gap:12px;padding:10px 16px;text-decoration:none;border-bottom:1px solid var(--border-color);background:#fff;transition:background 0.15s;"
                           onmouseover="this.style.background='var(--body-bg)'" onmouseout="this.style.background='#fff'">
                            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;font-weight:700;font-size:13px;">${initials}</div>
                            <div>
                                <p style="margin:0;font-size:13px;font-weight:600;color:var(--text-primary);">${s.fullname}</p>
                                <p style="margin:0;font-size:11px;color:var(--text-secondary);">${s.role} · ${s.status}</p>
                            </div>
                        </a>`;
                    });
                }

                if (!hasResults) document.getElementById('searchEmpty').style.display = 'block';
            })
            .catch(() => {
                document.getElementById('searchLoading').style.display = 'none';
                document.getElementById('searchEmpty').style.display   = 'block';
            });
    }, 300);
});

document.addEventListener('click', function (e) {
    const box = document.getElementById('searchDropdown');
    const inp = document.getElementById('headerSearch');
    if (box && !box.contains(e.target) && e.target !== inp) box.style.display = 'none';
});

// ── NOTIFICATIONS ───────────────────────────────────────────────────────────
const NOTIF_INTERVAL = 15000; // poll every 15 seconds
let   notifPollTimer = null;
let   prevUnreadCount = 0;

const iconColorMap = {
    order:     '#ef4444',
    stock:     '#f59e0b',
    staff:     '#6366f1',
    milestone: '#10b981',
};

function renderNotifications(data) {
    currentNotifications = data.notifications || [];
    const items     = data.notifications || [];
    const unread    = data.unread_count  || 0;
    const badge     = document.getElementById('notifBadge');
    const hBadge    = document.getElementById('notifHeaderBadge');
    const bellIcon  = document.getElementById('notifBellIcon');
    const skeleton  = document.getElementById('notifSkeleton');
    const notifItems= document.getElementById('notifItems');
    const emptyEl   = document.getElementById('notifEmpty');
    const lastUpd   = document.getElementById('notifLastUpdated');

    // ── Badge ──
    if (unread > 0) {
        badge.style.display = 'flex';
badge.style.visibility = 'visible';
        badge.textContent   = unread > 99 ? '99+' : unread;
        hBadge.style.display = 'inline';
        hBadge.textContent  = unread + ' new';
        bellIcon.style.color = 'var(--accent)';

        // Shake bell only when count goes up
        if (unread > prevUnreadCount) {
            bellIcon.style.animation = 'none';
            requestAnimationFrame(() => {
                bellIcon.style.animation = 'bellShake 0.5s ease';
            });
        }
    } else {
        badge.style.display  = 'none';
badge.style.visibility = 'hidden';
        hBadge.style.display = 'none';
        bellIcon.style.color = 'var(--text-primary)';
    }
    prevUnreadCount = unread;

    // ── List ──
    skeleton.style.display   = 'none';
    notifItems.style.display = 'block';

    if (items.length === 0) {
        notifItems.style.display = 'none';
        emptyEl.style.display    = 'block';
    } else {
        emptyEl.style.display    = 'none';
        notifItems.innerHTML = items.map(n => `
            <a href="${n.link}" class="${n.is_new ? 'notif-item-new' : ''}"
               style="display:flex;align-items:flex-start;gap:11px;padding:11px 16px;text-decoration:none;border-bottom:1px solid var(--border-color);background:#fff;transition:background 0.15s;border-left:3px solid transparent;"
               onmouseover="this.style.background='var(--body-bg)'" onmouseout="this.style.background='${n.is_new ? '#fef9f0' : '#fff'}'">
                <div style="width:36px;height:36px;border-radius:50%;background:${n.color}18;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
                    <i class="fa-solid ${n.icon}" style="font-size:14px;color:${n.color};"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="margin:0 0 2px;font-size:12.5px;font-weight:700;color:var(--text-primary);line-height:1.3;">${n.title}</p>
                    <p style="margin:0;font-size:11px;color:var(--text-secondary);line-height:1.4;">${n.body}</p>
                </div>
                ${n.is_new ? '<span style="width:7px;height:7px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px;"></span>' : ''}
            </a>
        `).join('');
    }

    // ── Last updated ──
    const now = new Date();
    lastUpd.textContent = 'Updated ' + now.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
}

function fetchNotifications() {
    fetch('helpers/notifications.php')
        .then(r => r.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                renderNotifications(data);
            } catch(e) {
                console.error('Notifications parse error:', text);
                renderNotifications({notifications: [], unread_count: 0});
            }
        })
        .catch(e => {
            console.error('Notifications fetch error:', e);
            renderNotifications({notifications: [], unread_count: 0});
        });
}

let currentNotifications = [];

function markAllRead() {
    const keys = currentNotifications.map(n => n.key).filter(Boolean);
    const params = new URLSearchParams({ action: 'mark_read' });
    keys.forEach(k => params.append('keys[]', k));

    fetch('helpers/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(r => r.json())
    .then(() => fetchNotifications())
    .catch(() => {});
}

function toggleNotifPanel() {
    const d = document.getElementById('notifDropdown');
    const p = document.getElementById('profilePopup');
    if (p) p.style.display = 'none';
    const isOpen = d.style.display !== 'none';
    d.style.display = isOpen ? 'none' : 'block';
}

// ── Start polling ──
fetchNotifications(); // immediate on load
notifPollTimer = setInterval(fetchNotifications, NOTIF_INTERVAL);

// ── PROFILE ─────────────────────────────────────────────────────────────────
function toggleProfilePopup() {
    const p = document.getElementById('profilePopup');
    const d = document.getElementById('notifDropdown');
    if (d) d.style.display = 'none';
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    ['notifDropdown','profilePopup'].forEach(id => {
        const el = document.getElementById(id);
        if (el && !el.contains(e.target) &&
            !e.target.closest('[onclick*="' + (id === 'notifDropdown' ? 'Notif' : 'Profile') + '"]')) {
            el.style.display = 'none';
        }
    });
});

document.addEventListener('scroll', function() {
    document.getElementById('notifDropdown').style.display = 'none';
    document.getElementById('profilePopup').style.display  = 'none';
}, true);

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('headerSearch').focus();
    }
});
</script>