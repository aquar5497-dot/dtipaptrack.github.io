<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';

// --- 1. Handle Filters (Excluding SUBPR and A,B,C labeling per instructions) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$month_filter = isset($_GET['month']) ? $_GET['month'] : '';

$where = " WHERE 1=1 AND pr.pr_number NOT LIKE 'SUBPR%' AND pr.pr_number NOT REGEXP '-[A-Z]$' ";

if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (pr.pr_number LIKE '%$s%' OR po.po_number LIKE '%$s%' OR rfq.rfq_number LIKE '%$s%' OR dv.dv_number LIKE '%$s%' OR pr.purpose LIKE '%$s%') ";
}
if ($month_filter !== '') {
    $where .= " AND DATE_FORMAT(pr.pr_date, '%Y-%m') = '$month_filter' ";
}

// --- 2. Get Data ---
$sql = "SELECT 
            pr.pr_number, pr.pr_date, pr.total_amount as pr_amount, pr.purpose,
            rfq.rfq_number, rfq.rfq_date, 
            po.po_number, po.po_date, po.total_amount as po_amount,
            iar.iar_number, iar.iar_date, iar.status as iar_status,
            dv.dv_number, dv.dv_date, dv.status as dv_status 
        FROM purchase_requests pr
        LEFT JOIN rfqs rfq ON pr.id = rfq.pr_id
        LEFT JOIN purchase_orders po ON pr.id = po.pr_id
        LEFT JOIN iars iar ON po.id = iar.po_id
        LEFT JOIN disbursement_vouchers dv ON pr.id = dv.pr_id " 
        . $where . " ORDER BY pr.id DESC";

$res = $conn->query($sql);

// --- 3. Pre-process for Summary Averages & Record Count ---
$data = [];
$grand_total_pr = 0;
$grand_total_po = 0;
$total_days_acc = 0;
$total_to_po_acc = 0;
$total_to_pay_acc = 0;
$pay_count = 0;
$rows_count = 0;

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
        $rows_count++;
        
        $prDate = new DateTime($row['pr_date']);
        $today = new DateTime();
        $poDate = (!empty($row['po_date'])) ? new DateTime($row['po_date']) : null;
        $dvDate = (!empty($row['dv_date'])) ? new DateTime($row['dv_date']) : null;

        // Cumulative stats
        $daysToPO = $poDate ? $prDate->diff($poDate)->format('%a') : $prDate->diff($today)->format('%a');
        $total_to_po_acc += (int)$daysToPO;

        if ($poDate && $dvDate) {
            $total_to_pay_acc += (int)$poDate->diff($dvDate)->format('%a');
            $pay_count++;
        }

        $finalEnd = $dvDate ?: $today;
        $total_days_acc += (int)$prDate->diff($finalEnd)->format('%a');
    }
}

$avg_total = ($rows_count > 0) ? round($total_days_acc / $rows_count) : 0;
$avg_to_po = ($rows_count > 0) ? round($total_to_po_acc / $rows_count) : 0;
$avg_to_pay = ($pay_count > 0) ? round($total_to_pay_acc / $pay_count) : 0;

$filename = "Procurement_Master_Tracker_" . date('Y-m-d') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        table { border-collapse: collapse; }
        .title { font-size: 16pt; font-weight: bold; text-align: center; }
        td, th { text-align: center; vertical-align: middle; border: 1px solid #94a3b8; font-family: Arial, sans-serif; }
        
        .bg-pr { background-color: #2563eb; color: #ffffff; font-weight: bold; }
        .bg-rfq { background-color: #059669; color: #ffffff; font-weight: bold; }
        .bg-po { background-color: #d97706; color: #ffffff; font-weight: bold; }
        .bg-iar { background-color: #7c3aed; color: #ffffff; font-weight: bold; }
        .bg-dv { background-color: #e11d48; color: #ffffff; font-weight: bold; }
        .bg-tracking { background-color: #475569; color: #ffffff; font-weight: bold; }
        
        .hdr-sub { background-color: #f1f5f9; color: #475569; font-weight: bold; font-size: 9pt; height: 25px; }
        .cell-data { padding: 5px; font-size: 9pt; }
        .money { mso-number-format:"\#\,\#\#0"; }
        .summary-label { background-color: #f8fafc; font-weight: bold; font-size: 10pt; text-align: right; border:none; }
        .summary-val { font-weight: bold; font-size: 10pt; color: #1e40af; border: 0.5pt solid #cbd5e1; }
        .legend-box { width: 15pt; border: 0.5pt solid #000; }
    </style>
</head>
<body>
    <table>
        <tr><th colspan="18" class="title" style="border:none;">PROCUREMENT MASTER LIST & BOTTLENECK TRACKER</th></tr>
        <tr><th colspan="18" style="border:none; text-align:center;">Report Generated: <?= date('M d, Y') ?></th></tr>
        
        <tr><td colspan="18" style="border:none; height:20px;"></td></tr>
        <tr>
            <td colspan="1" class="summary-label">Total Records:</td>
            <td class="summary-val" style="color: #475569;"><?= $rows_count ?></td>
            <td colspan="1" class="summary-label">Avg. PR to PO:</td>
            <td class="summary-val"><?= $avg_to_po ?> Days</td>
            <td colspan="1" class="summary-label">Avg. PO to Pay:</td>
            <td class="summary-val"><?= $avg_to_pay ?> Days</td>
            <td colspan="1" class="summary-label">Avg. Total:</td>
            <td class="summary-val" style="color: #ef4444;"><?= $avg_total ?> Days</td>
            <td colspan="10" style="border:none;"></td>
        </tr>
        <tr><td colspan="18" style="border:none; height:15px;"></td></tr>

        <thead>
            <tr>
                <th colspan="4" class="bg-pr">PURCHASE REQUEST</th>
                <th colspan="2" class="bg-rfq">RFQ</th>
                <th colspan="3" class="bg-po">PURCHASE ORDER</th>
                <th colspan="3" class="bg-iar">INSPECTION</th>
                <th colspan="3" class="bg-dv">DISBURSEMENT</th>
                <th colspan="3" class="bg-tracking">TIMELINE ANALYSIS</th>
            </tr>
            <tr class="hdr-sub">
                <th style="width:100pt;">PR Number</th>
                <th style="width:90pt;">PR Date</th>
                <th style="width:180pt;">Purpose</th>
                <th style="width:90pt;">Amount</th>
                <th style="width:90pt;">RFQ No.</th>
                <th style="width:90pt;">RFQ Date</th>
                <th style="width:90pt;">PO Number</th>
                <th style="width:90pt;">PO Date</th>
                <th style="width:90pt;">Amount</th>
                <th style="width:90pt;">IAR No.</th>
                <th style="width:90pt;">IAR Date</th>
                <th style="width:80pt;">Status</th>
                <th style="width:90pt;">DV No.</th>
                <th style="width:90pt;">DV Date</th>
                <th style="width:80pt;">Status</th>
                <th style="background-color:#fffbeb;">PR to PO</th>
                <th style="background-color:#fff1f2;">PO to Pay</th>
                <th style="background-color:#f1f5f9;">Total Days</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $r): 
                $grand_total_pr += $r['pr_amount'];
                $grand_total_po += $r['po_amount'];

                $prDate = new DateTime($r['pr_date']);
                $today = new DateTime();
                $poDate = (!empty($r['po_date'])) ? new DateTime($r['po_date']) : null;
                $dvDate = (!empty($r['dv_date'])) ? new DateTime($r['dv_date']) : null;

                $daysToPO = $poDate ? $prDate->diff($poDate)->format('%a') : $prDate->diff($today)->format('%a');
                $daysToPay = ($poDate && $dvDate) ? $poDate->diff($dvDate)->format('%a') : '—';
                $finalEnd = $dvDate ?: $today;
                $totalDays = $prDate->diff($finalEnd)->format('%a');
            ?>
                <tr>
                    <td class="cell-data" style="background-color:#eff6ff; color:#1e40af; font-weight:bold;"><?= $r['pr_number'] ?></td>
                    <td class="cell-data" style="background-color:#eff6ff;"><?= $r['pr_date'] ? date('M d, Y', strtotime($r['pr_date'])) : '—' ?></td>
                    <td class="cell-data" style="background-color:#eff6ff; text-align:left;"><?= htmlspecialchars($r['purpose']) ?></td>
                    <td class="cell-data money" style="background-color:#eff6ff;"><?= $r['pr_amount'] ?></td>
                    <td class="cell-data" style="background-color:#ecfdf5;"><?= $r['rfq_number'] ?: '—' ?></td>
                    <td class="cell-data" style="background-color:#ecfdf5;"><?= $r['rfq_date'] ? date('M d, Y', strtotime($r['rfq_date'])) : '—' ?></td>
                    <td class="cell-data" style="background-color:#fffbeb; color:#92400e; font-weight:bold;"><?= $r['po_number'] ?: '—' ?></td>
                    <td class="cell-data" style="background-color:#fffbeb;"><?= $r['po_date'] ? date('M d, Y', strtotime($r['po_date'])) : '—' ?></td>
                    <td class="cell-data money" style="background-color:#fffbeb;"><?= $r['po_amount'] ?: 0 ?></td>
                    <td class="cell-data" style="background-color:#f5f3ff;"><?= $r['iar_number'] ?: '—' ?></td>
                    <td class="cell-data" style="background-color:#f5f3ff;"><?= $r['iar_date'] ? date('M d, Y', strtotime($r['iar_date'])) : '—' ?></td>
                    <td class="cell-data" style="background-color:#f5f3ff;"><?= $r['iar_status'] ?: '—' ?></td>
                    <td class="cell-data" style="background-color:#fff1f2; color:#be123c; font-weight:bold;"><?= $r['dv_number'] ?: '—' ?></td>
                    <td class="cell-data" style="background-color:#fff1f2;"><?= $r['dv_date'] ? date('M d, Y', strtotime($r['dv_date'])) : '—' ?></td>
                    <td class="cell-data" style="background-color:#fff1f2;"><?= $r['dv_status'] ?: '—' ?></td>
                    <td class="cell-data" style="background-color:#fffbeb;"><?= $daysToPO ?> d</td>
                    <td class="cell-data" style="background-color:#fff1f2;"><?= $daysToPay ?> <?= is_numeric($daysToPay) ? 'd' : '' ?></td>
                    <td class="cell-data" style="font-weight:bold; color:<?= ($totalDays > 30) ? '#e11d48' : '#475569' ?>;"><?= $totalDays ?> Days</td>
                </tr>
            <?php endforeach; ?>
            
            <tr><td colspan="18" style="border:none; height:10px;"></td></tr>
            <tr>
                <td colspan="3" style="font-weight:bold; text-align:right; border:none;">GRAND TOTAL PR:</td>
                <td class="cell-data" style="font-weight:bold;"><?= number_format($grand_total_pr, 2) ?></td>
                <td colspan="5" style="border:none;"></td>
                <td class="cell-data" style="font-weight:bold; text-align:right; border:none;">PO TOTAL:</td>
                <td class="cell-data" style="font-weight:bold;"><?= number_format($grand_total_po, 2) ?></td>
                <td colspan="9" style="border:none;"></td>
            </tr>
        </tbody>
    </table>

    <br>
    <table>
        <tr><th colspan="2" style="background-color:#f8fafc; font-size:10pt;">REPORT LEGEND</th></tr>
        <tr><td class="legend-box" style="background-color:#eff6ff;"></td><td style="text-align:left; border:none; padding-left:5px; font-size:9pt;">Purchase Request Phase</td></tr>
        <tr><td class="legend-box" style="background-color:#ecfdf5;"></td><td style="text-align:left; border:none; padding-left:5px; font-size:9pt;">RFQ Phase</td></tr>
        <tr><td class="legend-box" style="background-color:#fffbeb;"></td><td style="text-align:left; border:none; padding-left:5px; font-size:9pt;">Purchase Order Issued</td></tr>
        <tr><td class="legend-box" style="background-color:#f5f3ff;"></td><td style="text-align:left; border:none; padding-left:5px; font-size:9pt;">Inspection Phase</td></tr>
        <tr><td class="legend-box" style="background-color:#fff1f2;"></td><td style="text-align:left; border:none; padding-left:5px; font-size:9pt;">Disbursement / Payment Phase</td></tr>
    </table>
</body>
</html>