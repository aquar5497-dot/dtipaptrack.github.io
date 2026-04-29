<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
  header("Location: payroll_dv_list.php?error=No Payroll DV specified.");
  exit;
}

$stmt = $conn->prepare("DELETE FROM payroll_dvs WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
  header("Location: payroll_dv_list.php?success=Payroll DV deleted successfully.");
  exit;
} else {
  header("Location: payroll_dv_list.php?error=Failed to delete Payroll DV.");
  exit;
}

$stmt->close();
ob_end_flush();
?>
