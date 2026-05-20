<?php
/**
 * audit_log() — writes one entry to the audit_log table.
 * Safe to call from any file that already has a PDO $conn.
 * Never throws — audit failure must not break the main flow.
 */
if (!function_exists('audit_log')) {
    function audit_log(PDO $conn, array $session, string $action,
        ?string $target_type  = null,
        ?int    $target_id    = null,
        ?string $target_label = null,
        ?string $detail       = null
    ): void {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO audit_log
                    (admin_id, actor_name, action, target_type, target_id, target_label, detail, ip_address)
                 VALUES
                    (:aid, :aname, :action, :ttype, :tid, :tlabel, :detail, :ip)"
            );
            $stmt->execute([
                ':aid'    => $session['admin_id'] ?? null,
                ':aname'  => $session['username']  ?? 'superadmin',
                ':action' => $action,
                ':ttype'  => $target_type,
                ':tid'    => $target_id,
                ':tlabel' => $target_label,
                ':detail' => $detail,
                ':ip'     => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Exception $e) {
            // silent — audit log failure must never block the user
        }
    }
}