<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$dv_id = (int)($_POST['dv_id'] ?? 0);
if (!$dv_id) { echo json_encode(['success'=>false,'message'=>'Invalid DV ID']); exit; }

$dv_date      = $_POST['dv_date']      ?? '';
$supplier     = trim($_POST['supplier']     ?? '');
$gross_amount = (float)($_POST['gross_amount'] ?? 0);
$tax_amount   = (float)($_POST['tax_amount']   ?? 0);
$net_amount   = (float)($_POST['net_amount']   ?? 0);
$tax_type     = trim($_POST['tax_type']     ?? '');
$status       = trim($_POST['status']       ?? '');

$stmt = $conn->prepare(
    "UPDATE disbursement_vouchers
     SET dv_date=?, supplier=?, gross_amount=?, tax_amount=?, net_amount=?, tax_type=?, status=?
     WHERE id=?"
);
$stmt->bind_param("ssdddsssi", $dv_date, $supplier, $gross_amount, $tax_amount, $net_amount, $tax_type, $status, $dv_id);
$stmt->execute();

// ── Accounting entries ────────────────────────────────────────
$conn->query("DELETE FROM dv_document WHERE dv_id=$dv_id");

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$ins = $conn->prepare(
    "INSERT INTO dv_document (dv_id, account_title, uacs_code, debit, credit, sort_order)
     VALUES (?,?,?,?,?,?)"
);
$saved = 0;
foreach ($items as $i => $item) {
    $title  = trim($item['account_title'] ?? '');
    $uacs   = trim($item['uacs_code']     ?? '');
    $debit  = (float)($item['debit']      ?? 0);
    $credit = (float)($item['credit']     ?? 0);
    $sort   = $i;
    if ($title === '' && $uacs === '' && $debit == 0 && $credit == 0) continue;
    $ins->bind_param("issddi", $dv_id, $title, $uacs, $debit, $credit, $sort);
    $ins->execute();
    $saved++;
}

echo json_encode(['success'=>true,'message'=>'DV saved successfully','rows_saved'=>$saved]);
