<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
// export_excel.php — standalone, no Composer required
// Exports selected PR with linked documents and overall procurement summary
// Professionally styled Excel-compatible .xls file using HTML + CSS

require 'config/db.php';

// Get PR ID
$pr_id = isset($_POST['pr_id']) ? intval($_POST['pr_id']) : (isset($_GET['pr_id']) ? intval($_GET['pr_id']) : 0);
if ($pr_id <= 0) {
    die("Invalid PR ID");
}

// Fetch PR details with prepared statement
$stmt = $conn->prepare("SELECT * FROM purchase_requests WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $pr_id);
$stmt->execute();
$pr = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pr) {
    die("PR not found.");
}

// Fetch Sub-PRs for additional context
$subprs = [];
$sub_stmt = $conn->prepare("SELECT * FROM purchase_requests WHERE parent_id = ? ORDER BY id ASC");
$sub_stmt->bind_param("i", $pr_id);
$sub_stmt->execute();
$sub_res = $sub_stmt->get_result();
while($r = $sub_res->fetch_assoc()) $subprs[] = $r;
$sub_stmt->close();

// Calculate totals
$sub_sum = 0;
foreach($subprs as $s) $sub_sum += floatval($s['total_amount']);
$base_amount = floatval($pr['total_amount']) - $sub_sum;

// Linked documents with prepared statements
$rfq_stmt = $conn->prepare("SELECT rfq_number, rfq_date FROM rfqs WHERE pr_id = ? ORDER BY id ASC");
$rfq_stmt->bind_param("i", $pr_id);
$rfq_stmt->execute();
$rfqs = $rfq_stmt->get_result();

$po_stmt = $conn->prepare("SELECT po_number, po_date, supplier, date_of_award, total_amount FROM purchase_orders WHERE pr_id = ? ORDER BY id ASC");
$po_stmt->bind_param("i", $pr_id);
$po_stmt->execute();
$pos = $po_stmt->get_result();

$iar_stmt = $conn->prepare("SELECT i.iar_number, i.invoice_number, i.date_inspected, i.date_received, i.status, p.po_number
                      FROM iars i
                      LEFT JOIN purchase_orders p ON i.po_id = p.id
                      WHERE p.pr_id = ?
                      ORDER BY i.id ASC");
$iar_stmt->bind_param("i", $pr_id);
$iar_stmt->execute();
$iars = $iar_stmt->get_result();

$dv_stmt = $conn->prepare("SELECT dv_number, dv_date, supplier, tax_type, gross_amount, tax_amount, net_amount, status 
                           FROM disbursement_vouchers 
                           WHERE pr_id = ? ORDER BY id ASC");
$dv_stmt->bind_param("i", $pr_id);
$dv_stmt->execute();
$dvs = $dv_stmt->get_result();

// Calculate PO total for this PR
$po_total_stmt = $conn->prepare("SELECT IFNULL(SUM(total_amount),0) as total FROM purchase_orders WHERE pr_id = ?");
$po_total_stmt->bind_param("i", $pr_id);
$po_total_stmt->execute();
$po_total_for_pr = floatval($po_total_stmt->get_result()->fetch_assoc()['total']);
$po_total_stmt->close();

$variance = floatval($pr['total_amount']) - $po_total_for_pr;
$variance_pct = floatval($pr['total_amount']) != 0 ? ($variance / floatval($pr['total_amount'])) * 100 : 0;

// Overall summary
$summary = $conn->query("
  SELECT 
    IFNULL((SELECT SUM(total_amount) FROM purchase_requests WHERE parent_id IS NULL AND (status IS NULL OR status != 'Cancelled')),0) AS total_pr,
    IFNULL((SELECT SUM(po.total_amount) FROM purchase_orders po INNER JOIN purchase_requests pr ON po.pr_id=pr.id WHERE (pr.status IS NULL OR pr.status != 'Cancelled')),0) AS total_po,
    IFNULL((SELECT SUM(dv.gross_amount) FROM disbursement_vouchers dv INNER JOIN purchase_requests pr ON dv.pr_id=pr.id WHERE (pr.status IS NULL OR pr.status != 'Cancelled')),0) AS total_dv_gross,
    IFNULL((SELECT SUM(dv.tax_amount) FROM disbursement_vouchers dv INNER JOIN purchase_requests pr ON dv.pr_id=pr.id WHERE (pr.status IS NULL OR pr.status != 'Cancelled')),0) AS total_dv_tax,
    IFNULL((SELECT SUM(dv.net_amount) FROM disbursement_vouchers dv INNER JOIN purchase_requests pr ON dv.pr_id=pr.id WHERE (pr.status IS NULL OR pr.status != 'Cancelled')),0) AS total_dv_net
")->fetch_assoc();

// Filename
$filename = "Procurement_Report_{$pr['pr_number']}.xls";

// Force Excel download
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Pragma: no-cache");
header("Expires: 0");

function safeVal($v) { return htmlspecialchars($v ?? ''); }
function formatMoney($v) { return '₱ ' . number_format(floatval($v), 2); }

echo "<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<style>
/* Reset for Excel compatibility */
* { margin: 0; padding: 0; box-sizing: border-box; }

body { 
    font-family: Calibri, Arial, sans-serif; 
    font-size: 11px; 
    color: #000000;
    background-color: #FFFFFF;
    padding: 10px;
}

/* Main Container Table */
.main-container {
    width: 100%;
    border-collapse: collapse;
    background-color: #FFFFFF;
}

/* Header Section - Dark Blue */
.header-row {
    background-color: #1E3A8A;
    color: #FFFFFF;
}

.header-title {
    font-size: 16px;
    font-weight: bold;
    padding: 15px;
    text-align: center;
    border: 1px solid #1E3A8A;
}

.header-subtitle {
    font-size: 11px;
    padding: 5px 15px;
    text-align: center;
    border: 1px solid #1E3A8A;
    background-color: #1E40AF;
}

/* Info Bar */
.info-row {
    background-color: #DBEAFE;
}

.info-cell {
    padding: 8px 12px;
    border: 1px solid #93C5FD;
    font-size: 10px;
    color: #1E40AF;
    font-weight: 600;
}

/* Section Headers */
.section-header-row {
    background-color: #059669;
    color: #FFFFFF;
}

.section-header-cell {
    padding: 10px 12px;
    font-weight: bold;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid #047857;
}

/* Alternate section colors */
.section-blue { background-color: #2563EB; border-color: #1D4ED8; }
.section-purple { background-color: #7C3AED; border-color: #6D28D9; }
.section-orange { background-color: #EA580C; border-color: #C2410C; }
.section-red { background-color: #DC2626; border-color: #B91C1C; }
.section-gray { background-color: #4B5563; border-color: #374151; }
.section-dark { background-color: #1F2937; border-color: #111827; }

/* Data Tables */
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
    background-color: #FFFFFF;
}

.data-table th {
    background-color: #F3F4F6;
    color: #374151;
    font-weight: bold;
    padding: 8px;
    border: 1px solid #6B7280;
    text-align: center;
    font-size: 10px;
    text-transform: uppercase;
}

.data-table td {
    padding: 8px;
    border: 1px solid #6B7280;
    text-align: center;
    vertical-align: middle;
    color: #1F2937;
}

.data-table tr:nth-child(even) {
    background-color: #F9FAFB;
}

/* Specific alignments */
.text-left { text-align: left !important; }
.text-right { text-align: right !important; }
.text-center { text-align: center !important; }

/* Amount styling */
.amount {
    font-weight: bold;
    color: #059669;
    font-family: 'Courier New', monospace;
    text-align: right !important;
}

.amount.negative { color: #DC2626; }
.amount.neutral { color: #4B5563; }

/* Status badges */
.status-cell {
    font-weight: bold;
    text-transform: uppercase;
    font-size: 9px;
}

.status-complete { color: #059669; background-color: #D1FAE5; }
.status-pending { color: #D97706; background-color: #FEF3C7; }
.status-processing { color: #2563EB; background-color: #DBEAFE; }
.status-cancelled { color: #DC2626; background-color: #FEE2E2; }

/* Document type colors */
.doc-pr { color: #1D4ED8; font-weight: bold; }
.doc-po { color: #059669; font-weight: bold; }
.doc-rfq { color: #7C3AED; font-weight: bold; }
.doc-iar { color: #EA580C; font-weight: bold; }
.doc-dv { color: #DC2626; font-weight: bold; }

/* Summary Cards Row */
.summary-row {
    background-color: #FFFFFF;
}

.summary-cell {
    border: 1px solid #6B7280;
    padding: 10px;
    text-align: center;
    background-color: #F9FAFB;
}

.summary-label {
    font-size: 9px;
    color: #6B7280;
    text-transform: uppercase;
    font-weight: bold;
    margin-bottom: 5px;
}

.summary-value {
    font-size: 14px;
    font-weight: bold;
    color: #111827;
    font-family: 'Courier New', monospace;
}

.summary-blue { border-top: 4px solid #2563EB; background-color: #EFF6FF; }
.summary-green { border-top: 4px solid #059669; background-color: #ECFDF5; }
.summary-purple { border-top: 4px solid #7C3AED; background-color: #F5F3FF; }
.summary-orange { border-top: 4px solid #EA580C; background-color: #FFF7ED; }

/* Variance Box */
.variance-row {
    background-color: #FFFBEB;
}

.variance-cell {
    border: 2px solid #F59E0B;
    padding: 12px;
    text-align: center;
    background-color: #FEF3C7;
}

.variance-title {
    font-size: 10px;
    color: #92400E;
    font-weight: bold;
    text-transform: uppercase;
}

.variance-amount {
    font-size: 16px;
    font-weight: bold;
    color: #92400E;
    font-family: 'Courier New', monospace;
    margin: 5px 0;
}

.variance-pct {
    font-size: 10px;
    color: #B45309;
}

/* Details Table */
.details-table {
    width: 100%;
    border-collapse: collapse;
}

.details-table td {
    padding: 8px 12px;
    border: 1px solid #6B7280;
}

.details-label {
    background-color: #F3F4F6;
    font-weight: bold;
    color: #374151;
    width: 30%;
    text-align: left;
}

.details-value {
    background-color: #FFFFFF;
    color: #111827;
    text-align: left;
}

/* Empty state */
.empty-cell {
    text-align: center;
    color: #9CA3AF;
    font-style: italic;
    padding: 15px;
    background-color: #F9FAFB;
}

/* Footer */
.footer-row {
    background-color: #F3F4F6;
    color: #6B7280;
    font-size: 9px;
}

.footer-cell {
    padding: 10px;
    text-align: center;
    border: 1px solid #6B7280;
}

/* Spacing rows */
.spacer-row { height: 10px; }
.spacer-row-tall { height: 20px; }

/* Overall Summary Special Styling */
.overall-metric-cell {
    text-align: left;
    padding: 10px 15px;
    font-weight: 600;
    border: 1px solid #6B7280;
}

.overall-amount-cell {
    text-align: right;
    padding: 10px 15px;
    font-weight: bold;
    border: 1px solid #6B7280;
    font-family: 'Courier New', monospace;
}

.metric-pr { background-color: #DBEAFE; color: #1E40AF; }
.metric-po { background-color: #D1FAE5; color: #065F46; }
.metric-gross { background-color: #E9D5FF; color: #6B21A8; }
.metric-tax { background-color: #FEE2E2; color: #991B1B; }
.metric-net { background-color: #FEF3C7; color: #92400E; font-size: 12px; }

/* Border helpers */
.border-top-thick { border-top: 3px solid #1F2937; }
</style>
</head>
<body>

<table class='main-container'>";

// HEADER SECTION
echo "<tr class='header-row'>
        <td colspan='3' class='header-title'>DEPARTMENT OF TRADE AND INDUSTRY<br>Procurement Management System</td>
      </tr>
      <tr class='header-row'>
        <td colspan='3' class='header-subtitle'>Detailed Procurement Report</td>
      </tr>";

// INFO BAR
echo "<tr class='info-row'>
        <td class='info-cell'>PR Number: <strong>" . safeVal($pr['pr_number']) . "</strong></td>
        <td class='info-cell'>Entity: <strong>" . safeVal($pr['entity_name']) . "</strong></td>
        <td class='info-cell'>Generated: <strong>" . date('F d, Y h:i A') . "</strong></td>
      </tr>";

echo "<tr class='spacer-row'><td colspan='3'></td></tr>";

// FINANCIAL SUMMARY CARDS
echo "<tr>
        <td class='summary-cell summary-blue'>
            <div class='summary-label'>Total PR Amount</div>
            <div class='summary-value'>" . formatMoney($pr['total_amount']) . "</div>
        </td>
        <td class='summary-cell summary-green'>
            <div class='summary-label'>Total PO Amount</div>
            <div class='summary-value'>" . formatMoney($po_total_for_pr) . "</div>
        </td>
        <td class='summary-cell summary-purple'>
            <div class='summary-label'>Base Amount</div>
            <div class='summary-value'>" . formatMoney($base_amount) . "</div>
        </td>
      </tr>";

echo "<tr class='spacer-row'><td colspan='3'></td></tr>";

// VARIANCE BOX
$variance_class = $variance < 0 ? 'negative' : ($variance > 0 ? 'neutral' : '');
echo "<tr class='variance-row'>
        <td colspan='3' class='variance-cell'>
            <div class='variance-title'>Budget Variance (PR - PO)</div>
            <div class='variance-amount " . $variance_class . "'>" . formatMoney($variance) . "</div>
            <div class='variance-pct'>" . number_format($variance_pct, 2) . "% of PR Total</div>
        </td>
      </tr>";

echo "<tr class='spacer-row-tall'><td colspan='3'></td></tr>";

// PURCHASE REQUEST DETAILS
echo "<tr class='section-header-row'>
        <td colspan='3' class='section-header-cell section-blue'>PURCHASE REQUEST DETAILS</td>
      </tr>
      <tr>
        <td colspan='3'>
            <table class='details-table'>
                <tr>
                    <td class='details-label'>PR Number</td>
                    <td class='details-value doc-pr'>" . safeVal($pr['pr_number']) . "</td>
                    <td class='details-label'>PR Date</td>
                    <td class='details-value'>" . safeVal($pr['pr_date']) . "</td>
                </tr>
                <tr>
                    <td class='details-label'>Entity Name</td>
                    <td class='details-value'>" . safeVal($pr['entity_name']) . "</td>
                    <td class='details-label'>Fund Cluster</td>
                    <td class='details-value'>" . safeVal($pr['fund_cluster']) . "</td>
                </tr>
                <tr>
                    <td class='details-label'>Purpose</td>
                    <td class='details-value' colspan='3'>" . safeVal($pr['purpose']) . "</td>
                </tr>
                <tr>
                    <td class='details-label'>Status</td>
                    <td class='details-value'>";
                    $status = $pr['status'] ?? 'Pending';
                    $status_class = 'status-pending';
                    if (stripos($status, 'complete') !== false) $status_class = 'status-complete';
                    elseif (stripos($status, 'cancel') !== false) $status_class = 'status-cancelled';
                    elseif (stripos($status, 'process') !== false) $status_class = 'status-processing';
                    echo "<span class='status-cell " . $status_class . "'>" . strtoupper(safeVal($status)) . "</span>";
echo "          </td>
                    <td class='details-label'>Total Amount</td>
                    <td class='details-value amount'>" . formatMoney($pr['total_amount']) . "</td>
                </tr>
            </table>
        </td>
      </tr>";

echo "<tr class='spacer-row-tall'><td colspan='3'></td></tr>";

// SUB-PRs SECTION
if (count($subprs) > 0) {
    echo "<tr class='section-header-row'>
            <td colspan='3' class='section-header-cell section-gray'>LINKED SUB-PRs</td>
          </tr>
          <tr>
            <td colspan='3'>
                <table class='data-table'>
                    <thead>
                        <tr>
                            <th>Sub-PR Number</th>
                            <th>Date</th>
                            <th class='text-left'>Purpose</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>";
    foreach ($subprs as $s) {
        echo "<tr>
                <td class='doc-pr'>" . safeVal($s['pr_number']) . "</td>
                <td>" . safeVal($s['pr_date']) . "</td>
                <td class='text-left'>" . safeVal($s['purpose']) . "</td>
                <td class='amount'>" . formatMoney($s['total_amount']) . "</td>
              </tr>";
    }
    echo "      </tbody>
                </table>
            </td>
          </tr>
          <tr class='spacer-row-tall'><td colspan='3'></td></tr>";
}

// RFQ SECTION
echo "<tr class='section-header-row'>
        <td colspan='3' class='section-header-cell section-purple'>REQUEST FOR QUOTATION (RFQ)</td>
      </tr>
      <tr>
        <td colspan='3'>
            <table class='data-table'>
                <thead>
                    <tr>
                        <th style='width: 40%'>RFQ Number</th>
                        <th style='width: 30%'>RFQ Date</th>
                        <th style='width: 30%'>Linked PR</th>
                    </tr>
                </thead>
                <tbody>";
if ($rfqs && $rfqs->num_rows > 0) {
    while ($row = $rfqs->fetch_assoc()) {
        echo "<tr>
                <td class='doc-rfq'>" . safeVal($row['rfq_number']) . "</td>
                <td>" . safeVal($row['rfq_date']) . "</td>
                <td class='doc-pr'>" . safeVal($pr['pr_number']) . "</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='3' class='empty-cell'>No RFQ records found for this PR</td></tr>";
}
echo "      </tbody>
            </table>
        </td>
      </tr>";

echo "<tr class='spacer-row-tall'><td colspan='3'></td></tr>";

// PO SECTION
echo "<tr class='section-header-row'>
        <td colspan='3' class='section-header-cell section-green'>PURCHASE ORDERS (PO)</td>
      </tr>
      <tr>
        <td colspan='3'>
            <table class='data-table'>
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>PO Date</th>
                        <th class='text-left'>Supplier</th>
                        <th>Date of Award</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>";
if ($pos && $pos->num_rows > 0) {
    while ($row = $pos->fetch_assoc()) {
        echo "<tr>
                <td class='doc-po'>" . safeVal($row['po_number']) . "</td>
                <td>" . safeVal($row['po_date']) . "</td>
                <td class='text-left'>" . safeVal($row['supplier']) . "</td>
                <td>" . safeVal($row['date_of_award']) . "</td>
                <td class='amount'>" . formatMoney($row['total_amount']) . "</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='5' class='empty-cell'>No Purchase Order records found</td></tr>";
}
echo "      </tbody>
            </table>
        </td>
      </tr>";

echo "<tr class='spacer-row-tall'><td colspan='3'></td></tr>";

// IAR SECTION
echo "<tr class='section-header-row'>
        <td colspan='3' class='section-header-cell section-orange'>INSPECTION & ACCEPTANCE REPORTS (IAR)</td>
      </tr>
      <tr>
        <td colspan='3'>
            <table class='data-table'>
                <thead>
                    <tr>
                        <th>IAR Number</th>
                        <th>Linked PO</th>
                        <th>Invoice Number</th>
                        <th>Date Inspected</th>
                        <th>Date Received</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>";
if ($iars && $iars->num_rows > 0) {
    while ($row = $iars->fetch_assoc()) {
        $status = $row['status'] ?? 'Pending';
        $status_class = 'status-pending';
        if (stripos($status, 'complete') !== false) $status_class = 'status-complete';
        elseif (stripos($status, 'cancel') !== false) $status_class = 'status-cancelled';
        
        echo "<tr>
                <td class='doc-iar'>" . safeVal($row['iar_number']) . "</td>
                <td class='doc-po'>" . safeVal($row['po_number']) . "</td>
                <td>" . safeVal($row['invoice_number']) . "</td>
                <td>" . safeVal($row['date_inspected']) . "</td>
                <td>" . safeVal($row['date_received']) . "</td>
                <td class='status-cell " . $status_class . "'>" . strtoupper(safeVal($status)) . "</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='6' class='empty-cell'>No IAR records found</td></tr>";
}
echo "      </tbody>
            </table>
        </td>
      </tr>";

echo "<tr class='spacer-row-tall'><td colspan='3'></td></tr>";

// DV SECTION
echo "<tr class='section-header-row'>
        <td colspan='3' class='section-header-cell section-red'>DISBURSEMENT VOUCHERS (DV)</td>
      </tr>
      <tr>
        <td colspan='3'>
            <table class='data-table'>
                <thead>
                    <tr>
                        <th>DV Number</th>
                        <th>DV Date</th>
                        <th class='text-left'>Supplier</th>
                        <th>Tax Type</th>
                        <th>Gross Amount</th>
                        <th>Tax Amount</th>
                        <th>Net Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>";
if ($dvs && $dvs->num_rows > 0) {
    while ($row = $dvs->fetch_assoc()) {
        $status = $row['status'] ?? 'Pending';
        $status_class = 'status-pending';
        if (stripos($status, 'complete') !== false) $status_class = 'status-complete';
        elseif (stripos($status, 'cancel') !== false) $status_class = 'status-cancelled';
        
        echo "<tr>
                <td class='doc-dv'>" . safeVal($row['dv_number']) . "</td>
                <td>" . safeVal($row['dv_date']) . "</td>
                <td class='text-left'>" . safeVal($row['supplier']) . "</td>
                <td>" . safeVal($row['tax_type']) . "</td>
                <td class='amount neutral'>" . formatMoney($row['gross_amount']) . "</td>
                <td class='amount negative'>" . formatMoney($row['tax_amount']) . "</td>
                <td class='amount'>" . formatMoney($row['net_amount']) . "</td>
                <td class='status-cell " . $status_class . "'>" . strtoupper(safeVal($status)) . "</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='8' class='empty-cell'>No Disbursement Voucher records found</td></tr>";
}
echo "      </tbody>
            </table>
        </td>
      </tr>";

echo "<tr class='spacer-row-tall'><td colspan='3'></td></tr>";

// OVERALL PROCUREMENT SUMMARY
echo "<tr class='section-header-row'>
        <td colspan='3' class='section-header-cell section-dark'>OVERALL PROCUREMENT SUMMARY (SYSTEM WIDE)</td>
      </tr>
      <tr>
        <td colspan='3'>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td class='overall-metric-cell metric-pr' style='width: 60%;'>
                        ■ Total PR Amount (All Active)
                    </td>
                    <td class='overall-amount-cell metric-pr' style='width: 40%;'>
                        " . formatMoney($summary['total_pr']) . "
                    </td>
                </tr>
                <tr>
                    <td class='overall-metric-cell metric-po'>
                        ■ Total PO Amount (All Active)
                    </td>
                    <td class='overall-amount-cell metric-po'>
                        " . formatMoney($summary['total_po']) . "
                    </td>
                </tr>
                <tr>
                    <td class='overall-metric-cell metric-gross'>
                        ■ Total DV Gross Amount
                    </td>
                    <td class='overall-amount-cell metric-gross'>
                        " . formatMoney($summary['total_dv_gross']) . "
                    </td>
                </tr>
                <tr>
                    <td class='overall-metric-cell metric-tax'>
                        ■ Total DV Tax Amount
                    </td>
                    <td class='overall-amount-cell metric-tax'>
                        " . formatMoney($summary['total_dv_tax']) . "
                    </td>
                </tr>
                <tr style='border-top: 3px solid #1F2937;'>
                    <td class='overall-metric-cell metric-net' style='font-size: 11px;'>
                        ■ TOTAL DV NET AMOUNT
                    </td>
                    <td class='overall-amount-cell metric-net' style='font-size: 13px;'>
                        " . formatMoney($summary['total_dv_net']) . "
                    </td>
                </tr>
            </table>
        </td>
      </tr>";

echo "<tr class='spacer-row'><td colspan='3'></td></tr>";

// FOOTER
echo "<tr class='footer-row'>
        <td colspan='3' class='footer-cell'>
            <strong>DTI Procurement Report</strong> | Page 1 of 1<br>
            This document is system-generated on " . date('F d, Y \a\t h:i A') . " | All amounts are in Philippine Peso (PHP)<br>
            <em>Confidential - For internal use only</em>
        </td>
      </tr>";

echo "</table>
</body>
</html>";

// Close statements
$rfq_stmt->close();
$po_stmt->close();
$iar_stmt->close();
$dv_stmt->close();
$conn->close();

exit;
?>