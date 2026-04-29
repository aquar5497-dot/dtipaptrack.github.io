<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
$id = intval($_GET['id'] ?? 0);
if($id) $conn->query("DELETE FROM iars WHERE id = $id");
    logAudit('IAR', 'DELETE', $id, $id, [], []);
header('Location: iar_list.php'); exit;
