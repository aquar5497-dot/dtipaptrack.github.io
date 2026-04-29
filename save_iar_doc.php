<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$iar_id = (int)($_POST['iar_id'] ?? 0);
if (!$iar_id) { echo json_encode(['success'=>false,'message'=>'Invalid IAR ID']); exit; }

// IAR header fields that can be updated from document view
$iar_date       = $_POST['iar_date']       ?? '';
$invoice_number = trim($_POST['invoice_number'] ?? '');
$invoice_date   = $_POST['invoice_date']   ?? null;
$date_inspected = $_POST['date_inspected'] ?? null;
$date_received  = $_POST['date_received']  ?? null;
$status         = trim($_POST['status']    ?? '');

$stmt = $conn->prepare(
    "UPDATE iars SET iar_date=?, invoice_number=?, invoice_date=?, date_inspected=?, date_received=?, status=? WHERE id=?"
);
$stmt->bind_param("ssssssi", $iar_date, $invoice_number, $invoice_date, $date_inspected, $date_received, $status, $iar_id);
$stmt->execute();

$conn->query("DELETE FROM iar_document WHERE iar_id=$iar_id");

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$ins = $conn->prepare(
    "INSERT INTO iar_document (iar_id, stock_property_no, unit, item_description, quantity, unit_cost, total_cost, sort_order)
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
    $ins->bind_param("isssdddi", $iar_id, $stock, $unit, $desc, $qty, $uc, $tc, $sort);
    $ins->execute();
    $saved++;
}

echo json_encode(['success'=>true,'message'=>'IAR saved successfully','rows_saved'=>$saved]);
