<?php
/**
 * ipos_logo.php — Reusable iPOS SVG logo
 *
 * Usage:
 *   Sidebar (default, 38×38):
 *     <?php include 'path/to/ipos_logo.php'; ?>
 *
 *   Custom size:
 *     <?php $logo_size = 56; include 'path/to/ipos_logo.php'; ?>
 *
 *   Login page (larger, with text):
 *     <?php $logo_size = 64; $logo_show_text = true; include 'path/to/ipos_logo.php'; ?>
 *
 * The logo automatically reads --accent and --sidebar-bg CSS variables
 * set by theme_loader.php, so it always matches the active theme.
 */

$logo_size      = $logo_size      ?? 38;   // px — width & height of the hex icon
$logo_show_text = $logo_show_text ?? false; // show "iPOS / I Pay, I Order, I Serve" beside icon
$logo_id        = 'ipos-logo-' . substr(md5(uniqid()), 0, 6); // unique id per instance
?>

<div class="ipos-logo-wrap" id="<?= $logo_id ?>"
     style="display:flex;align-items:center;gap:<?= $logo_show_text ? '10px' : '0' ?>;">

    <!-- SVG Icon: hexagon shell (copper fixed) + i-person (theme accent) -->
    <svg width="<?= $logo_size ?>" height="<?= $logo_size ?>"
         viewBox="0 0 52 52"
         xmlns="http://www.w3.org/2000/svg"
         aria-label="iPOS logo"
         style="flex-shrink:0;display:block;">

        <defs>
            <!-- Person body gradient: light accent → accent (follows theme) -->
            <linearGradient id="<?= $logo_id ?>-pg" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%"   class="ipos-logo-grad-top"/>
                <stop offset="100%" class="ipos-logo-grad-bot"/>
            </linearGradient>
        </defs>

        <!-- Outer hexagon (copper — fixed brand color) -->
        <polygon
            points="26,2 47,13.5 47,38.5 26,50 5,38.5 5,13.5"
            fill="none"
            stroke="#B87333"
            stroke-width="2.5"
            stroke-linejoin="round"/>

        <!-- Inner honeycomb ring (lighter copper) -->
        <polygon
            points="26,8 41,16.5 41,35.5 26,44 11,35.5 11,16.5"
            fill="none"
            stroke="#B87333"
            stroke-width="1"
            stroke-linejoin="round"
            opacity="0.35"/>

        <!-- i-person: head circle -->
        <circle cx="26" cy="17" r="5.5" fill="url(#<?= $logo_id ?>-pg)"/>

        <!-- i-person: signal arcs (left & right) -->
        <path d="M18.5,14.5 Q14,17 18.5,21.5"
              fill="none"
              stroke="url(#<?= $logo_id ?>-pg)"
              stroke-width="2"
              stroke-linecap="round"/>
        <path d="M33.5,14.5 Q38,17 33.5,21.5"
              fill="none"
              stroke="url(#<?= $logo_id ?>-pg)"
              stroke-width="2"
              stroke-linecap="round"/>

        <!-- i-person: body pill -->
        <rect x="21.5" y="25" width="9" height="18" rx="4.5"
              fill="url(#<?= $logo_id ?>-pg)"/>
    </svg>

    <?php if ($logo_show_text): ?>
    <!-- Text lockup (for login/registration pages) -->
    <div class="ipos-logo-text" style="line-height:1.2;">
        <div style="font-size:<?= round($logo_size * 0.45) ?>px;font-weight:800;letter-spacing:-0.5px;color:var(--accent);">
            iPOS
        </div>
        <div style="font-size:<?= round($logo_size * 0.18) ?>px;font-weight:500;color:var(--text-secondary,rgba(255,255,255,0.6));white-space:nowrap;letter-spacing:0.04em;">
            I Pay, I Order, I Serve
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- CSS: wires SVG gradient stops to theme CSS variables -->
<style>
/* Gradient top stop = light version of accent */
#<?= $logo_id ?> .ipos-logo-grad-top {
    stop-color: var(--accent-light, #fde8f0);
}
/* Gradient bottom stop = accent color */
#<?= $logo_id ?> .ipos-logo-grad-bot {
    stop-color: var(--accent, #be185d);
}
</style>