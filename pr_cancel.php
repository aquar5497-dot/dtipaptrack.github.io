<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
// File: pr_cancel.php
require 'config/db.php';
$id = intval($_GET['id'] ?? 0);
if($id){
  $r = $conn->query("SELECT is_cancelled FROM purchase_requests WHERE id = $id");
  if($r && $r->num_rows){
    $v = $r->fetch_assoc()['is_cancelled'];
    $nv = $v ? 0 : 1;
    $stmt = $conn->prepare("UPDATE purchase_requests SET is_cancelled = ? WHERE id = ?");
    $stmt->bind_param('ii', $nv, $id);
    $stmt->execute();
  }
}
header('Location: pr_list.php');
exit;
