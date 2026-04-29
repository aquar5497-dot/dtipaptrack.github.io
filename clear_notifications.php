<?php
/**
 * clear_notifications.php
 * Clears notifications ONLY for the currently logged-in user.
 * Uses NOW() (MySQL server local time) to match audit_logs.created_at
 * which is also inserted via NOW() in logAudit().
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config/db.php';

// Ensure columns exist
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_cleared_at DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_seen_at    DATETIME DEFAULT NULL");

// Use NOW() — same timezone as audit_logs.created_at (both use MySQL server time).
// This is the critical fix: UTC_TIMESTAMP() would return UTC while audit_logs
// stores local time via NOW(), causing cleared_at to always be behind created_at.
$stmt = $conn->prepare(
    "UPDATE users SET notif_cleared_at = NOW(), notif_seen_at = NOW() WHERE id = ?"
);
$stmt->bind_param("i", $_SESSION['user_id']);
$ok = $stmt->execute();
$stmt->close();

// Clear the column-check session flag so it re-checks next load
unset($_SESSION['_notif_cols_ok']);

header('Content-Type: application/json');
echo json_encode(['ok' => $ok]);
