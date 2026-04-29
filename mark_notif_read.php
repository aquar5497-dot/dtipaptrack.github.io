<?php
/**
 * mark_notif_read.php
 * Marks all current notifications as "seen" for this user only.
 * Uses NOW() to match the same timezone as audit_logs.created_at.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false]);
    exit;
}

require_once __DIR__ . '/config/db.php';

$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_seen_at DATETIME DEFAULT NULL");

$stmt = $conn->prepare(
    "UPDATE users SET notif_seen_at = NOW() WHERE id = ?"
);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
