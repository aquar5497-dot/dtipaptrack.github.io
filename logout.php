<?php
session_start();
require_once __DIR__ . '/inc/audit.php';
logAudit('AUTH','LOGOUT',(int)($_SESSION['user_id']??0),($_SESSION['username']??''),[],[]);
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
