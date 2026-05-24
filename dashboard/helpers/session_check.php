<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Prevent caching so browsers don't show stale pages from bfcache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

$resp = [
    'admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
    'staff_id' => isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : null,
    'staff_role' => isset($_SESSION['staff_role']) ? $_SESSION['staff_role'] : null,
    'staff_admin' => isset($_SESSION['staff_admin']) ? (int)$_SESSION['staff_admin'] : null,
    'kitchen_pin_unlocked_at' => isset($_SESSION['kitchen_pin_unlocked_at']) ? $_SESSION['kitchen_pin_unlocked_at'] : null,
    'staff_gate_unlocked' => isset($_SESSION['staff_gate_unlocked']) ? $_SESSION['staff_gate_unlocked'] : null,
    'staff_login_active' => isset($_SESSION['staff_login_active']) ? $_SESSION['staff_login_active'] : null,
    'staff_session_valid' => null,
    'auto_logged_out' => false,
    'auto_logout_reason' => null,
];

function staff_shift_has_ended(array $staff, ?string $loginAt): bool {
    if (empty($staff['shift_start']) || empty($staff['shift_end'])) {
        return false;
    }

    try {
        $now = new DateTime('now');
        $startTime = $staff['shift_start'];
        $endTime = $staff['shift_end'];

        $todayStart = new DateTime(date('Y-m-d') . ' ' . $startTime);
        $todayEnd = new DateTime(date('Y-m-d') . ' ' . $endTime);

        if ($todayEnd > $todayStart) {
            return $now >= $todayEnd;
        }

        // Overnight shift. Use the session login time to choose the matching shift window.
        $login = $loginAt ? new DateTime($loginAt) : null;
        if ($login && $login < $todayEnd) {
            $shiftEnd = clone $todayEnd;
        } else {
            $shiftEnd = clone $todayEnd;
            $shiftEnd->modify('+1 day');
        }

        return $now >= $shiftEnd;
    } catch (Exception $e) {
        return false;
    }
}

function clear_staff_session_after_shift(PDO $conn, int $staffId, ?int $sessionLogId): void {
    $conn->prepare("UPDATE staffs SET is_online = 0 WHERE staff_id = :sid")
         ->execute([':sid' => $staffId]);

    if ($sessionLogId) {
        $conn->prepare("UPDATE staff_sessions
                        SET logout_at = NOW(),
                            duration_minutes = TIMESTAMPDIFF(MINUTE, login_at, NOW())
                        WHERE session_id = :session_id
                          AND staff_id = :sid
                          AND logout_at IS NULL")
             ->execute([':session_id' => $sessionLogId, ':sid' => $staffId]);
    }

    $conn->prepare("UPDATE staff_sessions
                    SET logout_at = NOW(),
                        duration_minutes = TIMESTAMPDIFF(MINUTE, login_at, NOW())
                    WHERE staff_id = :sid
                      AND logout_at IS NULL")
         ->execute([':sid' => $staffId]);

    unset(
        $_SESSION['staff_id'],
        $_SESSION['staff_name'],
        $_SESSION['staff_role'],
        $_SESSION['staff_admin'],
        $_SESSION['staff_login_at'],
        $_SESSION['staff_session_log_id'],
        $_SESSION['staff_gate_unlocked'],
        $_SESSION['staff_login_active'],
        $_SESSION['kitchen_pin_unlocked_at']
    );
    $_SESSION['staff_reentry'] = time();
}

if (!empty($_SESSION['staff_id'])) {
    $resp['staff_session_valid'] = false;

    try {
        $db = new Database();
        $conn = $db->connect();

        $staffId = (int)$_SESSION['staff_id'];
        $sessionLogId = !empty($_SESSION['staff_session_log_id']) ? (int)$_SESSION['staff_session_log_id'] : null;

        $stmt = $conn->prepare("SELECT is_online, shift_start, shift_end FROM staffs WHERE staff_id=:sid LIMIT 1");
        $stmt->execute([':sid' => (int)$_SESSION['staff_id']]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        $sessionOpen = false;
        $loginAt = null;
        if (!empty($_SESSION['staff_session_log_id'])) {
            $sess = $conn->prepare("SELECT session_id, login_at
                                    FROM staff_sessions
                                    WHERE staff_id=:sid AND logout_at IS NULL
                                    ORDER BY login_at DESC, session_id DESC
                                    LIMIT 1");
            $sess->execute([
                ':sid' => (int)$_SESSION['staff_id'],
            ]);
            $latestSession = $sess->fetch(PDO::FETCH_ASSOC);
            $latestSessionId = (int)($latestSession['session_id'] ?? 0);
            $loginAt = $latestSession['login_at'] ?? null;
            $sessionOpen = $latestSessionId === $sessionLogId;
        }

        if ($staff && (int)$staff['is_online'] === 1 && $sessionOpen && staff_shift_has_ended($staff, $loginAt)) {
            clear_staff_session_after_shift($conn, $staffId, $sessionLogId);
            $resp['staff_session_valid'] = false;
            $resp['auto_logged_out'] = true;
            $resp['auto_logout_reason'] = 'shift_ended';
            echo json_encode($resp);
            exit;
        }

        $resp['staff_session_valid'] = $staff && (int)$staff['is_online'] === 1 && $sessionOpen;
    } catch (Exception $e) {
        $resp['staff_session_valid'] = false;
    }
}

echo json_encode($resp);
