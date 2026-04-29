<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
// File: rfq_delete.php
require 'config/db.php';
$id = intval($_GET['id'] ?? 0);
if($id) $conn->query("DELETE FROM rfqs WHERE id = $id");
    logAudit('RFQ', 'DELETE', $id, $id, [], []);
header('Location: rfq_list.php');
exit;
?>
