<?php
/**
 * announcement_handler.php
 * Handles saving / toggling Global Announcement & Maintenance mode.
 * Only accessible by superadmin.
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/audit_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db   = new Database();
$conn = $db->connect();

$action = $_POST['action'] ?? '';

/**
 * Helper: upsert a key/value in system_settings
 */
function setSetting(PDO $conn, string $key, ?string $value, int $admin_id): void {
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_by)
        VALUES (:k, :v, :id)
        ON DUPLICATE KEY UPDATE setting_value = :v, updated_by = :id, updated_at = NOW()
    ");
    $stmt->execute([':k' => $key, ':v' => $value, ':id' => $admin_id]);
}

/**
 * Helper: read a setting value
 */
function getSetting(PDO $conn, string $key): ?string {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :k");
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['setting_value'] : null;
}

$admin_id = (int)$_SESSION['admin_id'];

switch ($action) {

    // ── Save announcement settings ──────────────────────────────────
    case 'save_announcement':
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? '1' : '0';
        $message = trim($_POST['message'] ?? '');
        $type    = in_array($_POST['type'] ?? '', ['info','warning','success','danger'])
                   ? $_POST['type'] : 'info';

        if ($enabled === '1' && empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Announcement message cannot be empty.']);
            exit;
        }

        setSetting($conn, 'announcement_enabled', $enabled, $admin_id);
        setSetting($conn, 'announcement_message', $message, $admin_id);
        setSetting($conn, 'announcement_type',    $type,    $admin_id);

        audit_log($conn, $_SESSION, 'announcement_updated', 'superadmin', $admin_id,
            $_SESSION['username'] ?? null,
            "Announcement set to " . ($enabled === '1' ? "ENABLED (type: $type)" : "DISABLED"));

        echo json_encode(['success' => true, 'message' => 'Announcement settings saved.']);
        break;

    // ── Save maintenance settings ───────────────────────────────────
    case 'save_maintenance':
        $enabled  = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? '1' : '0';
        $message  = trim($_POST['message'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');

        if (empty($message)) {
            $message = 'We are currently performing scheduled maintenance. We will be back shortly.';
        }

        setSetting($conn, 'maintenance_enabled', $enabled,              $admin_id);
        setSetting($conn, 'maintenance_message', $message,              $admin_id);
        setSetting($conn, 'maintenance_end_time', $end_time ?: null,    $admin_id);

        audit_log($conn, $_SESSION, 'maintenance_updated', 'superadmin', $admin_id,
            $_SESSION['username'] ?? null,
            "Maintenance mode " . ($enabled === '1' ? "ENABLED" : "DISABLED"));

        echo json_encode(['success' => true, 'message' => 'Maintenance settings saved.']);
        break;

    // ── Quick toggle announcement on/off ───────────────────────────
    case 'toggle_announcement':
        $current = getSetting($conn, 'announcement_enabled') ?? '0';
        $new     = $current === '1' ? '0' : '1';
        setSetting($conn, 'announcement_enabled', $new, $admin_id);
        audit_log($conn, $_SESSION, 'announcement_toggled', 'superadmin', $admin_id,
            $_SESSION['username'] ?? null, "Announcement toggled to " . ($new === '1' ? 'ON' : 'OFF'));
        echo json_encode(['success' => true, 'enabled' => $new]);
        break;

    // ── Quick toggle maintenance on/off ────────────────────────────
    case 'toggle_maintenance':
        $current = getSetting($conn, 'maintenance_enabled') ?? '0';
        $new     = $current === '1' ? '0' : '1';
        setSetting($conn, 'maintenance_enabled', $new, $admin_id);
        audit_log($conn, $_SESSION, 'maintenance_toggled', 'superadmin', $admin_id,
            $_SESSION['username'] ?? null, "Maintenance mode toggled to " . ($new === '1' ? 'ON' : 'OFF'));
        echo json_encode(['success' => true, 'enabled' => $new]);
        break;

    // ── Get current settings (used on page load) ───────────────────
    case 'get_settings':
        echo json_encode([
            'success' => true,
            'announcement' => [
                'enabled' => getSetting($conn, 'announcement_enabled') ?? '0',
                'message' => getSetting($conn, 'announcement_message') ?? '',
                'type'    => getSetting($conn, 'announcement_type')    ?? 'info',
            ],
            'maintenance' => [
                'enabled'  => getSetting($conn, 'maintenance_enabled')  ?? '0',
                'message'  => getSetting($conn, 'maintenance_message')  ?? '',
                'end_time' => getSetting($conn, 'maintenance_end_time') ?? '',
            ],
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}
