<?php
/**
 * theme_loader.php — Universal theme injector.
 * Include AFTER the main CSS file in <head>.
 * Works on ALL pages (with or without $admin_id / $conn).
 * Session-cached so zero extra DB latency after first load.
 */

$_tl_id = null;
if (isset($admin_id) && $admin_id)   $_tl_id = (int)$admin_id;
elseif (isset($_SESSION['staff_admin'])) $_tl_id = (int)$_SESSION['staff_admin'];
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

$sb  = $_tl['sidebarBg']   ?? '#2D0B22';
$ac  = $_tl['accent']      ?? '#be185d';
$acd = $_tl['accentDark']  ?? '#9B2C52';
$bg  = $_tl['bodyBg']      ?? '#F0EBF4';
$txt = $_tl['text']        ?? '#1A0A14';
$acl = $_tl['accentLight'] ?? '#F5E6EC';
$brd = $_tl['borderColor'] ?? '#EAE0EE';
$ts  = $_tl['textSec']     ?? '#8C6E82';

// Fix old default colors for existing registered users
if ($sb  === '#5C0A2E' || $sb  === '#2d0a1f') $sb  = '#2D0B22';
if ($acd === '#5C0A2E' || $acd === '#7e1545') $acd = '#9B2C52';
if ($bg  === '#f5eef4' || $bg  === '#FDF2F8') $bg  = '#F0EBF4';
if ($txt === '#2d0a1f')                        $txt = '#1A0A14';
if ($acl === '#fde8f0')                        $acl = '#F5E6EC';
if ($brd === '#ead5e4')                        $brd = '#EAE0EE';
if ($ts  === '#9e6080')                        $ts  = '#8C6E82';
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
    --accent-light:             <?= htmlspecialchars($acl) ?>;
    --accent-dark:              <?= htmlspecialchars($acd) ?>;
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
