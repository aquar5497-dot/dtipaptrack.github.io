<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

if (isset($_POST['id'])) {
  $id = intval($_POST['id']);

  // Ensure columns exist
  $conn->query("ALTER TABLE purchase_requests ADD COLUMN IF NOT EXISTS status VARCHAR(50) NULL");
  $conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS status VARCHAR(50) NULL");

  // Mark PR as Cancelled
  $conn->query("UPDATE purchase_requests SET status='Cancelled' WHERE id=$id");
    logAudit('PR', 'CANCEL', $id, $id, [], []);

  // Mark linked POs as Cancelled
  $conn->query("UPDATE purchase_orders SET status='Cancelled' WHERE pr_id=$id");

  echo "<script>alert('Purchase Request successfully cancelled.');window.location.href='pr_list.php';</script>";
}
