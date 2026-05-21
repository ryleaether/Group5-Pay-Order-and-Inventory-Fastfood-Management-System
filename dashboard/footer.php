<?php
$footer_store = htmlspecialchars($_SESSION['fastfood_name'] ?? 'iPOS');
$footer_time  = date('D, M j Y • g:i A');

// Pull store info from DB
$footer_email   = '';
$footer_phone   = '';
$footer_address = '';

try {
    $db_f   = new Database();
    $conn_f = $db_f->connect();
    $stmt_f = $conn_f->prepare("
        SELECT email, phone_number, address, city, province, zip_code
        FROM admins
        WHERE admin_id = :id
    ");
    $stmt_f->execute([':id' => $_SESSION['admin_id']]);
    $row_f = $stmt_f->fetch(PDO::FETCH_ASSOC);

    $footer_email = htmlspecialchars($row_f['email'] ?? '');
    $footer_phone = htmlspecialchars($row_f['phone_number'] ?? '');

    $addr_parts = array_filter([
        $row_f['address']  ?? '',
        $row_f['city']     ?? '',
        $row_f['province'] ?? '',
        $row_f['zip_code'] ?? '',
    ]);
    $footer_address = htmlspecialchars(implode(', ', $addr_parts));

} catch (Exception $e) {
    $footer_email   = '';
    $footer_phone   = '';
    $footer_address = '';
}
?>

<div style="background:#fff;border-top:1.5px solid var(--border-color);padding:32px 40px 0;flex-shrink:0;">

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:48px;padding-bottom:28px;">

        <!-- About -->
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
    <?php $logo_size = 30; $logo_show_text = false; include __DIR__ . '/helpers/ipos_logo.php'; ?>
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

                <!-- Business Name -->
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-store" style="color:var(--accent);font-size:12px;width:14px;"></i>
                    <span style="font-size:12px;color:var(--text-secondary);"><?= $footer_store ?></span>
                </div>

                <!-- Contact Number -->
                <?php if (!empty($footer_phone)): ?>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-phone" style="color:var(--accent);font-size:12px;width:14px;"></i>
                    <span style="font-size:12px;color:var(--text-secondary);"><?= $footer_phone ?></span>
                </div>
                <?php endif; ?>

                <!-- Email -->
                <?php if (!empty($footer_email)): ?>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-envelope" style="color:var(--accent);font-size:12px;width:14px;"></i>
                    <span style="font-size:12px;color:var(--text-secondary);"><?= $footer_email ?></span>
                </div>
                <?php endif; ?>

                <!-- Address -->
                <?php if (!empty($footer_address)): ?>
                <div style="display:flex;align-items:flex-start;gap:8px;">
                    <i class="fa-solid fa-location-dot" style="color:var(--accent);font-size:12px;width:14px;margin-top:2px;"></i>
                    <span style="font-size:12px;color:var(--text-secondary);line-height:1.5;"><?= $footer_address ?></span>
                </div>
                <?php endif; ?>

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