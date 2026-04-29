<?php
require_once __DIR__ . '/inc/permissions.php';
require_once __DIR__ . '/inc/audit.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: login.php");
    exit();
}
