<?php
/**
 * save_pr_doc.php — AJAX endpoint: saves PR document to database
 * Called from pr_view.php Save button
 */
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$pr_id = (int)($_POST['pr_id'] ?? 0);
if (!$pr_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid PR ID']);
    exit;
}

// Verify PR exists
$check = $conn->prepare("SELECT id FROM purchase_requests WHERE id=?");
$check->bind_param("i", $pr_id);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'PR not found']);
    exit;
}

$pr_date       = $_POST['pr_date']       ?? '';
$entity_name   = trim($_POST['entity_name']   ?? '');
$fund_cluster  = trim($_POST['fund_cluster']  ?? '');
$total_amount  = (float)($_POST['total_amount'] ?? 0);
$purpose       = trim($_POST['purpose']       ?? '');
$office_section= trim($_POST['office_section'] ?? '');

// ── Update purchase_requests ──────────────────────────────────
// Use INSERT ... ON DUPLICATE KEY or just UPDATE
$stmt = $conn->prepare(
    "UPDATE purchase_requests
     SET pr_date=?, entity_name=?, fund_cluster=?, total_amount=?, purpose=?, office_section=?
     WHERE id=?"
);
$stmt->bind_param("sssdssi", $pr_date, $entity_name, $fund_cluster, $total_amount, $purpose, $office_section, $pr_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'DB error updating PR: ' . $stmt->error]);
    exit;
}

// ── Replace pr_document rows ──────────────────────────────────
$conn->query("DELETE FROM pr_document WHERE pr_id=$pr_id");

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$ins = $conn->prepare(
    "INSERT INTO pr_document
     (pr_id, stock_property_no, unit, item_description, quantity, unit_cost, total_cost, sort_order)
     VALUES (?,?,?,?,?,?,?,?)"
);

$saved = 0;
foreach ($items as $i => $item) {
    $stock = trim($item['stock'] ?? '');
    $unit  = trim($item['unit']  ?? '');
    $desc  = trim($item['description'] ?? '');
    $qty   = (float)($item['quantity']  ?? 0);
    $uc    = (float)($item['unit_cost'] ?? 0);
    $tc    = (float)($item['total_cost'] ?? ($qty * $uc));
    $sort  = $i;
    // Skip completely empty rows
    if ($stock === '' && $unit === '' && $desc === '' && $qty == 0 && $uc == 0) continue;
    $ins->bind_param("isssdddi", $pr_id, $stock, $unit, $desc, $qty, $uc, $tc, $sort);
    $ins->execute();
    $saved++;
}

echo json_encode([
    'success'    => true,
    'message'    => 'PR saved successfully',
    'rows_saved' => $saved
]);
