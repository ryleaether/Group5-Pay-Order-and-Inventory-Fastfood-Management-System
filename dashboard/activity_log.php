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
    header("Location: ../login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$admin_id = (int)$_SESSION['admin_id'];

function al_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function al_col_exists(PDO $conn, string $table, string $column): bool {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function al_rows(PDO $conn, string $sql, array $params = []): array {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function al_action_label(string $action): string {
    return ucwords(str_replace('_', ' ', $action));
}

$adminProfile = al_rows(
    $conn,
    "SELECT username, email, fullname, fastfood_name FROM admins WHERE admin_id = :id LIMIT 1",
    [':id' => $admin_id]
)[0] ?? [];

$sidebar = new SidebarRenderer(
    $admin_id,
    $_SESSION['fastfood_name'] ?? ($adminProfile['fastfood_name'] ?? ''),
    $adminProfile['fullname'] ?? $_SESSION['username'] ?? '',
    $adminProfile['username'] ?? $_SESSION['username'] ?? '',
    $adminProfile['email'] ?? ''
);

$actor = al_col_exists($conn, 'audit_log', 'actor_name') ? 'actor_name' : 'username';
$targetType = al_col_exists($conn, 'audit_log', 'target_type') ? 'target_type' : 'target';
$targetLabel = al_col_exists($conn, 'audit_log', 'target_label') ? 'target_label' : 'target_name';
$detail = al_col_exists($conn, 'audit_log', 'detail') ? 'detail' : 'details';

$q = trim((string)($_GET['q'] ?? ''));
$actionFilter = trim((string)($_GET['action_filter'] ?? ''));
$params = [':admin_id' => $admin_id];
$where = "WHERE admin_id = :admin_id";

if ($q !== '') {
    $where .= " AND (action LIKE :q OR {$actor} LIKE :q OR {$targetLabel} LIKE :q OR {$detail} LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

if ($actionFilter !== '') {
    $where .= " AND action = :action_filter";
    $params[':action_filter'] = $actionFilter;
}

$activities = al_rows($conn, "
    SELECT log_id, {$actor} AS actor_name, action, {$targetType} AS target_type,
           {$targetLabel} AS target_label, {$detail} AS detail, ip_address, created_at
    FROM audit_log
    {$where}
    ORDER BY created_at DESC, log_id DESC
    LIMIT 200
", $params);

$actions = al_rows($conn, "
    SELECT DISTINCT action
    FROM audit_log
    WHERE admin_id = :admin_id
    ORDER BY action
", [':admin_id' => $admin_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - iPOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../design/admin.css">
    <?php include __DIR__ . '/helpers/theme_loader.php'; ?>
    <style>
        .page-content { padding: 24px 32px 40px; }
        .al-page { display: flex; flex-direction: column; gap: 16px; }
        .al-hero {
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: #fff;
            padding: 24px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .al-hero h1 { margin: 0; color: #fff; font-size: 24px; font-weight: 900; }
        .al-hero p { margin: 6px 0 0; color: rgba(255,255,255,0.82); font-size: 13px; }
        .al-hero-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: rgba(255,255,255,0.16);
            display: grid;
            place-items: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .al-toolbar {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .al-toolbar input,
        .al-toolbar select {
            height: 36px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            background: #fff;
            color: var(--text-primary);
            padding: 0 12px;
            font: inherit;
            font-size: 13px;
            outline: none;
        }
        .al-toolbar input { flex: 1; min-width: 220px; }
        .al-toolbar input:focus,
        .al-toolbar select:focus { border-color: var(--accent); }
        .al-btn {
            height: 36px;
            border: 0;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            padding: 0 14px;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .al-btn.secondary {
            background: transparent;
            border: 1.5px solid var(--border-color);
            color: var(--text-primary);
        }
        .al-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 14px rgba(45,10,31,0.05);
        }
        .al-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .al-table th {
            background: linear-gradient(90deg, var(--accent-dark), var(--accent));
            color: #fff;
            text-align: left;
            padding: 12px 14px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .al-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: top;
        }
        .al-table tr:last-child td { border-bottom: 0; }
        .al-action {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 10px;
            border-radius: 99px;
            background: var(--accent-light);
            color: var(--accent);
            font-size: 11px;
            font-weight: 850;
            white-space: nowrap;
        }
        .al-target { font-weight: 800; }
        .al-detail { color: var(--text-secondary); font-size: 12px; margin-top: 3px; line-height: 1.45; }
        .al-muted { color: var(--text-secondary); font-size: 12px; }
        .al-empty { padding: 44px 20px; text-align: center; color: var(--text-secondary); font-size: 13px; }
        @media (max-width: 820px) {
            .page-content { padding: 16px; }
            .al-hero { align-items: flex-start; flex-direction: column; }
            .al-table { min-width: 780px; }
            .al-card { overflow-x: auto; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?= $sidebar->render('activity_log') ?>
    <main class="al-page">
        <section class="al-hero">
            <div style="display:flex;align-items:center;gap:16px;">
                <div class="al-hero-icon"><i class="fa-solid fa-list-check"></i></div>
                <div>
                    <h1>Activity Log</h1>
                    <p>Review recent admin actions, backup events, restores, and account activity for this store.</p>
                </div>
            </div>
            <a class="al-btn secondary" href="admindashboard.php"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </section>

        <form class="al-toolbar" method="get">
            <input type="text" name="q" value="<?= al_h($q) ?>" placeholder="Search actor, action, record, or detail...">
            <select name="action_filter" aria-label="Filter by action">
                <option value="">All actions</option>
                <?php foreach ($actions as $row): ?>
                    <option value="<?= al_h($row['action']) ?>" <?= $actionFilter === $row['action'] ? 'selected' : '' ?>>
                        <?= al_h(al_action_label($row['action'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="al-btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
            <a class="al-btn secondary" href="activity_log.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
        </form>

        <section class="al-card">
            <?php if ($activities): ?>
                <table class="al-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Record</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td>
                                <strong><?= al_h(date('M d, Y', strtotime($activity['created_at']))) ?></strong><br>
                                <span class="al-muted"><?= al_h(date('h:i:s A', strtotime($activity['created_at']))) ?></span>
                            </td>
                            <td><?= al_h($activity['actor_name'] ?: 'System') ?></td>
                            <td><span class="al-action"><i class="fa-solid fa-circle-dot"></i><?= al_h(al_action_label($activity['action'])) ?></span></td>
                            <td>
                                <span class="al-target"><?= al_h($activity['target_label'] ?: '-') ?></span><br>
                                <span class="al-muted"><?= al_h($activity['target_type'] ?: '-') ?></span>
                            </td>
                            <td><div class="al-detail"><?= al_h($activity['detail'] ?: '-') ?></div></td>
                            <td><span class="al-muted"><?= al_h($activity['ip_address'] ?: '-') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="al-empty">
                    <i class="fa-solid fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;"></i>
                    No activity entries found.
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?= $sidebar->renderClose() ?>
</div>
<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const main = document.getElementById('mainContent');
    if (!sidebar) return;
    const collapsed = sidebar.classList.toggle('sidebar-collapsed');
    if (main) main.classList.toggle('main-expanded', collapsed);
    localStorage.setItem('ipos_sidebar_collapsed', collapsed ? '1' : '0');
}
document.addEventListener('DOMContentLoaded', function () {
    const collapsed = localStorage.getItem('ipos_sidebar_collapsed') === '1';
    if (collapsed) {
        const sidebar = document.querySelector('.sidebar');
        const main = document.getElementById('mainContent');
        if (sidebar) sidebar.classList.add('sidebar-collapsed');
        if (main) main.classList.add('main-expanded');
    }
});
</script>
</body>
</html>
