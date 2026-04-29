<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
$id = intval($_GET['id'] ?? 0);
if($id) $conn->query("DELETE FROM purchase_orders WHERE id = $id");
    logAudit('PO', 'DELETE', $id, $id, [], []);
header('Location: po_list.php'); exit;
