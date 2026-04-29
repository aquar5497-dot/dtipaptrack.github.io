<?php
require_once __DIR__ . '/inc/permissions.php';
require_once __DIR__ . '/inc/audit.php';
require_once __DIR__ . '/inc/dropdown_setup.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. Must be logged in ─────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ── 2. Load $conn and run one-time setups ────────────────────────────────────
require_once __DIR__ . '/config/db.php';

// Ensure dropdown table exists (runs once per session to avoid repeated ALTERs)
if (empty($_SESSION['_dropdown_init'])) {
    ensureDropdownTable($conn);
    $_SESSION['_dropdown_init'] = true;
}

// ── 3. Load permissions into session if not yet loaded ───────────────────────
if (!isset($_SESSION['permissions']) && ($_SESSION['role'] ?? '') !== 'Administrator') {
    $stmt = $conn->prepare("SELECT permissions FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $_SESSION['permissions'] = json_decode($row['permissions'] ?? '[]', true) ?: [];
}

// ── 4. Enforce page-level permissions ────────────────────────────────────────
// Clean up legacy session keys from old notification system
if (isset($_SESSION['notif_last_seen'])) unset($_SESSION['notif_last_seen']);
if (isset($_SESSION['notif_cleared_at'])) unset($_SESSION['notif_cleared_at']);
$current_file = basename($_SERVER['PHP_SELF']);
$page_map = getPagePermissionMap();

if (isset($page_map[$current_file])) {
    $required = $page_map[$current_file];
    if (!hasPermission($required)) {
        header("Location: unauthorized.php");
        exit();
    }
}
