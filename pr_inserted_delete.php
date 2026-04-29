<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

if (!isset($_GET['id'], $_GET['parent_id'])) {
    die("Invalid request.");
}

$id        = (int)$_GET['id'];
$parent_id = (int)$_GET['parent_id'];

/* ===============================
   FETCH PR INFO BEFORE DELETE (for audit)
================================ */
$pr_info = $conn->prepare("SELECT pr_number, entity_name, fund_cluster, total_amount, purpose FROM purchase_requests WHERE id = ? AND pr_type = 'LATE'");
$pr_info->bind_param("i", $id);
$pr_info->execute();
$pr_before = $pr_info->get_result()->fetch_assoc();
$pr_info->close();

/* ===============================
   DELETE INSERTED PR (LATE ONLY)
================================ */
$stmt = $conn->prepare("
    DELETE FROM purchase_requests
    WHERE id = ? AND pr_type = 'LATE'
");
$stmt->bind_param("i", $id);
$stmt->execute();

/* ===============================
   AUDIT LOG
================================ */
if ($pr_before) {
    logAudit('LATE_PR','DELETE',$id,$pr_before['pr_number'],['pr_number'=>$pr_before['pr_number'],'entity_name'=>$pr_before['entity_name'],'total_amount'=>$pr_before['total_amount'],'purpose'=>$pr_before['purpose']],[]);
}

/* ===============================
   REDIRECT BACK
================================ */
header("Location: pr_inserted_list.php?parent_id=$parent_id");
exit;
