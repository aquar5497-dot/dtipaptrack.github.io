<?php
/**
 * save_rfq_doc.php — AJAX endpoint: saves RFQ document to database
 */
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$rfq_id = (int)($_POST['rfq_id'] ?? 0);
if (!$rfq_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid RFQ ID']);
    exit;
}

$rfq_date = $_POST['rfq_date'] ?? '';

// ── Update rfqs table ─────────────────────────────────────────
$stmt = $conn->prepare("UPDATE rfqs SET rfq_date=? WHERE id=?");
$stmt->bind_param("si", $rfq_date, $rfq_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
    exit;
}

// ── Replace rfq_document rows ─────────────────────────────────
$conn->query("DELETE FROM rfq_document WHERE rfq_id=$rfq_id");

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$ins = $conn->prepare(
    "INSERT INTO rfq_document (rfq_id, item_description, qty, unit, unit_price, sort_order)
     VALUES (?,?,?,?,?,?)"
);

$saved = 0;
foreach ($items as $i => $item) {
    $desc  = trim($item['description'] ?? '');
    $qty   = (float)($item['qty']        ?? 0);
    $unit  = trim($item['unit']          ?? '');
    $price = (float)($item['unit_price'] ?? 0);
    $sort  = $i;
    // Save all rows that have a description (from PR), price may be empty
    if ($desc === '') continue;
    $ins->bind_param("isdsdi", $rfq_id, $desc, $qty, $unit, $price, $sort);
    $ins->execute();
    $saved++;
}

echo json_encode(['success' => true, 'message' => 'RFQ saved successfully', 'rows_saved' => $saved]);
