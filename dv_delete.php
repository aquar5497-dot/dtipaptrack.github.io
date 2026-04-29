<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
$id = intval($_GET['id'] ?? 0);
if($id) $conn->query("DELETE FROM disbursement_vouchers WHERE id = $id");
    logAudit('DV', 'DELETE', $id, $id, [], []);
header('Location: dv_list.php'); exit;
