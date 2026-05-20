<?php
/**
 * theme_loader.php — Universal theme injector.
 * Include AFTER the main CSS file in <head>.
 * Works on ALL pages (with or without $admin_id / $conn).
 * Session-cached so zero extra DB latency after first load.
 */

$_tl_id = null;
if (isset($admin_id) && $admin_id)   $_tl_id = (int)$admin_id;
elseif (isset($_SESSION['admin_id'])) $_tl_id = (int)$_SESSION['admin_id'];

$_tl = null;
if ($_tl_id) {
    $cacheKey = 'ipos_theme_' . $_tl_id;
    if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
        $_tl = $_SESSION[$cacheKey];
    } else {
        try {
            if (!class_exists("Database")) { require_once __DIR__ . "/../../config/database.php"; }
            $c = isset($conn) && $conn ? $conn : (new Database())->connect();
            $s = $c->prepare("SELECT theme_data FROM admins WHERE admin_id = :id");
            $s->execute([':id' => $_tl_id]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            if ($r && !empty($r['theme_data'])) {
                $_tl = json_decode($r['theme_data'], true) ?: null;
                if ($_tl) $_SESSION[$cacheKey] = $_tl;
            }
        } catch (Exception $e) { /* theme_data column may not exist yet */ }
    }
}

$sb  = $_tl['sidebarBg']   ?? '#2d0a1f';
$ac  = $_tl['accent']      ?? '#be185d';
$acd = $_tl['accentDark']  ?? '#7e1545';
$bg  = $_tl['bodyBg']      ?? '#f5eef4';
$txt = $_tl['text']        ?? '#2d0a1f';
$acl = $_tl['accentLight'] ?? '#fde8f0';
$brd = $_tl['borderColor'] ?? '#ead5e4';
$ts  = $_tl['textSec']     ?? '#9e6080';
?>
<style id="ipos-theme-vars">
:root {
    --sidebar-bg:               <?= htmlspecialchars($sb)  ?>;
    --sidebar-active-bg:        <?= htmlspecialchars($acd) ?>;
    --sidebar-border:           rgba(255,255,255,0.07);
    --sidebar-section-label:    rgba(255,255,255,0.3);
    --sidebar-item-color:       rgba(255,255,255,0.5);
    --sidebar-item-hover-bg:    rgba(255,255,255,0.08);
    --sidebar-item-hover-color: #ffffff;
    --sidebar-active-color:     #ffffff;
    --accent:                   <?= htmlspecialchars($ac)  ?>;
    --accent-dark:              <?= htmlspecialchars($acd) ?>;
    --accent-light:             <?= htmlspecialchars($acl) ?>;
    --body-bg:                  <?= htmlspecialchars($bg)  ?>;
    --card-bg:                  #ffffff;
    --text-primary:             <?= htmlspecialchars($txt) ?>;
    --text-secondary:           <?= htmlspecialchars($ts)  ?>;
    --border-color:             <?= htmlspecialchars($brd) ?>;
    --shadow-sm:                0 2px 8px rgba(0,0,0,0.07);
    --shadow-md:                0 8px 24px rgba(0,0,0,0.10);
    --shadow-lg:                0 16px 40px rgba(0,0,0,0.14);
    --navy:                     <?= htmlspecialchars($sb)  ?>;
    --navy-mid:                 <?= htmlspecialchars($acd) ?>;
    --navy-light:               <?= htmlspecialchars($acd) ?>;
    --pink-border:              <?= htmlspecialchars($brd) ?>;
}
</style>
