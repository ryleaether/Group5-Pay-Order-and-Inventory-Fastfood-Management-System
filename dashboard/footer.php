<?php
$footer_store = htmlspecialchars($_SESSION['fastfood_name'] ?? 'iPOS');
$footer_time  = date('D, M j Y • g:i A');
?>

<div style="background:#fff;border-top:1.5px solid var(--border-color);padding:32px 40px 0;flex-shrink:0;">

 <div style="display:grid;grid-template-columns:2fr 1fr;gap:48px;padding-bottom:28px;">

        <!-- About -->
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                <div style="width:30px;height:30px;background:linear-gradient(135deg,var(--accent-dark),var(--accent));border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;">iP</div>
                <span style="font-size:15px;font-weight:800;color:var(--text-primary);">iPOS</span>
            </div>
            <p style="font-size:11.5px;color:var(--text-secondary);margin:0 0 14px;line-height:1.7;">A smart point-of-sale system designed for fast food restaurants. Manage orders, menus, staff, and more — all in one place.</p>
            <div style="display:flex;align-items:center;gap:6px;">
                <span style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block;animation:pulse 2s infinite;"></span>
                <span style="font-size:11px;color:#22c55e;font-weight:600;">System Online</span>
            </div>
        </div>

        <!-- Store Info -->
        <div>
            <p style="font-size:12px;font-weight:700;color:var(--text-primary);margin:0 0 12px;text-transform:uppercase;letter-spacing:0.08em;border-bottom:2px solid var(--accent);padding-bottom:6px;display:inline-block;">Store Info</p>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:10px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-store" style="color:var(--accent);font-size:12px;width:14px;"></i>
                    <span style="font-size:12px;color:var(--text-secondary);"><?= $footer_store ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-clock" style="color:var(--accent);font-size:12px;width:14px;"></i>
                    <span style="font-size:12px;color:var(--text-secondary);"><?= $footer_time ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-user-shield" style="color:var(--accent);font-size:12px;width:14px;"></i>
                    <span style="font-size:12px;color:var(--text-secondary);">Administrator</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-code-branch" style="color:var(--accent);font-size:12px;width:14px;"></i>
                    <span style="font-size:12px;color:var(--text-secondary);">iPOS v1.0.0</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Bottom Bar -->
    <div style="border-top:1px solid var(--border-color);padding:14px 0;display:flex;justify-content:center;align-items:center;">
        <span style="font-size:11px;color:var(--text-secondary);">© <?= date('Y') ?> iPOS &nbsp;|&nbsp; Januyan · Lorejas · Sumayang · Vales &nbsp;|&nbsp; All Rights Reserved</span>
    </div>

</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}
</style>