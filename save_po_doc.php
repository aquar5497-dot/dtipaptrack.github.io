<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$po_id = (int)($_POST['po_id'] ?? 0);
if (!$po_id) { echo json_encode(['success'=>false,'message'=>'Invalid PO ID']); exit; }

$po_date       = $_POST['po_date']       ?? '';
$supplier      = trim($_POST['supplier']       ?? '');
$date_of_award = $_POST['date_of_award'] ?? null;
$total_amount  = (float)($_POST['total_amount'] ?? 0);

$stmt = $conn->prepare("UPDATE purchase_orders SET po_date=?,supplier=?,date_of_award=?,total_amount=? WHERE id=?");
$stmt->bind_param("sssdi", $po_date, $supplier, $date_of_award, $total_amount, $po_id);
if (!$stmt->execute()) { echo json_encode(['success'=>false,'message'=>$stmt->error]); exit; }

$conn->query("DELETE FROM po_document WHERE po_id=$po_id");

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$ins = $conn->prepare(
    "INSERT INTO po_document (po_id, stock_property_no, unit, item_description, quantity, unit_cost, total_cost, sort_order)
     VALUES (?,?,?,?,?,?,?,?)"
);
$saved = 0;
foreach ($items as $i => $item) {
    $stock = trim($item['stock']       ?? '');
    $unit  = trim($item['unit']        ?? '');
    $desc  = trim($item['description'] ?? '');
    $qty   = (float)($item['quantity']  ?? 0);
    $uc    = (float)($item['unit_cost'] ?? 0);
    $tc    = (float)($item['total_cost']?? ($qty * $uc));
    $sort  = $i;
    if ($stock==='' && $unit==='' && $desc==='' && $qty==0 && $uc==0) continue;
    $ins->bind_param("isssdddi", $po_id, $stock, $unit, $desc, $qty, $uc, $tc, $sort);
    $ins->execute();
    $saved++;
}

echo json_encode(['success'=>true,'message'=>'PO saved successfully','rows_saved'=>$saved]);
