<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

// do deletion via GET id with confirmation on the list (simple approach)
// If you prefer POST-only deletion, adjust accordingly.
$id = intval($_GET['id'] ?? 0);
if ($id) {
    $stmt = $conn->prepare("DELETE FROM payroll_requests WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: payroll_list.php");
exit;
