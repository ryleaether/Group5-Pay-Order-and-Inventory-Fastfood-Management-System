<?php
/**
 * audit_helper.php
 * Provides the audit_log() function used by backup_handler.php,
 * soft_delete_handler.php, and other dashboard files.
 *
 * Creates the audit_log table automatically on first use.
 */

/**
 * Log an action to the audit_log table.
 *
 * @param PDO    $conn     Active PDO connection
 * @param array  $session  $_SESSION array
 * @param string $action   Short action key  e.g. 'backup_created'
 * @param string $target   Entity type       e.g. 'backup', 'menu_item', 'staff'
 * @param int|null $target_id  Row ID of affected record (nullable)
 * @param string $target_name Human-readable name of affected record
 * @param string $details  Extra context string
 */
function audit_log(PDO $conn, array $session, ?string $action, ?string $target, $target_id, ?string $target_name = '', ?string $details = ''): void {
    $action      = $action      ?? '';
    $target      = $target      ?? '';
    $target_name = $target_name ?? '';
    $details     = $details     ?? '';
    try {
        // Create table if it doesn't exist yet
        $conn->exec("
            CREATE TABLE IF NOT EXISTS `audit_log` (
                `log_id`       INT AUTO_INCREMENT PRIMARY KEY,
                `admin_id`     INT NULL,
                `username`     VARCHAR(100) NULL,
                `role`         VARCHAR(50)  NULL,
                `action`       VARCHAR(100) NOT NULL,
                `target`       VARCHAR(100) NOT NULL,
                `target_id`    INT NULL,
                `target_name`  VARCHAR(255) NULL,
                `details`      TEXT NULL,
                `ip_address`   VARCHAR(45)  NULL,
                `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_admin_id  (`admin_id`),
                INDEX idx_action    (`action`),
                INDEX idx_created   (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $stmt = $conn->prepare("
            INSERT INTO `audit_log`
                (`admin_id`, `username`, `role`, `action`, `target`, `target_id`, `target_name`, `details`, `ip_address`)
            VALUES
                (:admin_id, :username, :role, :action, :target, :target_id, :target_name, :details, :ip)
        ");

        $stmt->execute([
            ':admin_id'    => $session['admin_id']  ?? null,
            ':username'    => $session['username']   ?? ($session['role'] === 'cron' ? 'cron' : 'unknown'),
            ':role'        => $session['role']       ?? null,
            ':action'      => $action,
            ':target'      => $target,
            ':target_id'   => $target_id,
            ':target_name' => $target_name,
            ':details'     => $details,
            ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        // Audit logging must never crash the app — silently ignore errors
        error_log('[audit_log] Failed: ' . $e->getMessage());
    }
}