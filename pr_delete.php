<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

// Check if ID is provided via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Check if PR exists
    $check = $conn->prepare("SELECT id FROM purchase_requests WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        echo "<script>alert('Purchase Request not found.'); window.location='pr_list.php';</script>";
        exit;
    }
    $check->close();

    // Before deleting, check if this PR is linked to any Purchase Orders
    $linked = $conn->prepare("SELECT COUNT(*) as c FROM purchase_orders WHERE pr_id = ?");
    $linked->bind_param("i", $id);
    $linked->execute();
    $link_res = $linked->get_result()->fetch_assoc();
    $linked->close();

    if ($link_res['c'] > 0) {
        echo "<script>alert('Cannot delete this Purchase Request. It is linked to one or more Purchase Orders.'); window.location='pr_list.php';</script>";
        exit;
    }

    // Try to delete PR (and its sub-PRs if any)
    $conn->begin_transaction();
    try {
        // Delete sub-PRs first
        $del_sub = $conn->prepare("DELETE FROM purchase_requests WHERE parent_id = ?");
        $del_sub->bind_param("i", $id);
        $del_sub->execute();
        $del_sub->close();

        // Delete the main PR
        $del_main = $conn->prepare("DELETE FROM purchase_requests WHERE id = ?");
        $del_main->bind_param("i", $id);
        $del_main->execute();
        $del_main->close();

        $conn->commit();
    logAudit('PR', 'DELETE', $id, $id, [], []);
        echo "<script>alert('Purchase Request deleted successfully.'); window.location='pr_list.php';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error deleting Purchase Request: " . addslashes($e->getMessage()) . "'); window.location='pr_list.php';</script>";
    }
} else {
    echo "<script>alert('Invalid request.'); window.location='pr_list.php';</script>";
    exit;
}
?>
