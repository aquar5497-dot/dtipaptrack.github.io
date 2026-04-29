<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
require 'inc/helpers.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/**
 * REPORT.PHP
 * - Overall procurement summary (always shown)
 * - Optional specific PR report (when ?pr_id=### or searched)
 * - Linked documents for the selected PR: RFQ, PO, IAR, DV
 * - Charts: overall comparison (bar & pie) and PR vs PO (bar)
 */

// safe output helper
function safe($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// === Determine which PR to view ===
$pr_id = 0;
$search_query = trim($_GET['search_pr'] ?? '');

// Priority: 1. Search Box, 2. Dropdown Select
if (!empty($search_query)) {
    // Find PR ID from the search query (PR Number)
    $stmt = $conn->prepare("SELECT id FROM purchase_requests WHERE pr_number = ? AND (parent_id IS NULL OR parent_id = 0) LIMIT 1");
    $stmt->bind_param("s", $search_query);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $pr_id = (int)$res->fetch_assoc()['id'];
    }
} else if (isset($_GET['pr_id']) && is_numeric($_GET['pr_id'])) {
    // Fallback to dropdown value
    $pr_id = intval($_GET['pr_id']);
}


// === Fetch specific PR details (only if a valid pr_id was found) ===
$pr = null;
$subprs = [];
$pr_total = 0.0;
$sub_sum = 0.0;
$base_amount = 0.0;
$po_total_for_pr = 0.0;
$variance = 0.0;
$variance_pct = 0.0;
$rfqs = false; $pos = false; $iars = false; $dvs = false;

if ($pr_id > 0) {
    // Fetch parent PR (only parent entries) - NOW SECURE
    $stmt = $conn->prepare("SELECT * FROM purchase_requests WHERE id = ? AND (parent_id IS NULL OR parent_id = 0)");
    $stmt->bind_param("i", $pr_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $pr = $res->fetch_assoc();

        // Sub-PRs - NOW SECURE
        $s_stmt = $conn->prepare("SELECT * FROM purchase_requests WHERE parent_id = ? ORDER BY id ASC");
        $s_stmt->bind_param("i", $pr_id);
        $s_stmt->execute();
        $sres = $s_stmt->get_result();
        if ($sres) while($r = $sres->fetch_assoc()) $subprs[] = $r;
        $s_stmt->close();

        // Totals - NOW SECURE
        $sub_sum_stmt = $conn->prepare("SELECT IFNULL(SUM(total_amount),0) AS s FROM purchase_requests WHERE parent_id = ?");
        $sub_sum_stmt->bind_param("i", $pr_id);
        $sub_sum_stmt->execute();
        $sub_sum = (float)$sub_sum_stmt->get_result()->fetch_assoc()['s'];
        $sub_sum_stmt->close();

        $pr_total = (float)($pr['total_amount'] ?? 0);
        $base_amount = $pr_total - $sub_sum; // Assumes parent total includes sub-PRs

        $po_stmt = $conn->prepare("SELECT IFNULL(SUM(total_amount),0) AS s FROM purchase_orders WHERE pr_id = ?");
        $po_stmt->bind_param("i", $pr_id);
        $po_stmt->execute();
        $po_total_for_pr = (float)$po_stmt->get_result()->fetch_assoc()['s'];
        $po_stmt->close();

        $variance = $pr_total - $po_total_for_pr;
        $variance_pct = $pr_total != 0 ? ($variance / $pr_total) * 100 : 0;

        // Linked documents - NOW SECURE
        $rfqs_stmt = $conn->prepare("SELECT * FROM rfqs WHERE pr_id = ? ORDER BY id ASC");
        $rfqs_stmt->bind_param("i", $pr_id);
        $rfqs_stmt->execute();
        $rfqs = $rfqs_stmt->get_result();

        $pos_stmt = $conn->prepare("SELECT * FROM purchase_orders WHERE pr_id = ? ORDER BY id ASC");
        $pos_stmt->bind_param("i", $pr_id);
        $pos_stmt->execute();
        $pos = $pos_stmt->get_result();
        
        $iars_sql = "SELECT iars.*, purchase_orders.po_number FROM iars JOIN purchase_orders ON iars.po_id = purchase_orders.id WHERE purchase_orders.pr_id = ? ORDER BY iars.id ASC";
        $iars_stmt = $conn->prepare($iars_sql);
        $iars_stmt->bind_param("i", $pr_id);
        $iars_stmt->execute();
        $iars = $iars_stmt->get_result();

        $dvs_stmt = $conn->prepare("SELECT * FROM disbursement_vouchers WHERE pr_id = ? ORDER BY id ASC");
        $dvs_stmt->bind_param("i", $pr_id);
        $dvs_stmt->execute();
        $dvs = $dvs_stmt->get_result();

    } else {
        // invalid id -> reset
        $pr_id = 0;
    }
}

// === Overall procurement summary (EXCLUDE Cancelled PRs) ===
// These queries don't use user input, so they are safe as they are.
$overall_pr_total = (float)$conn->query("
    SELECT IFNULL(SUM(total_amount),0) AS s 
    FROM purchase_requests 
    WHERE parent_id IS NULL AND (status IS NULL OR status != 'Cancelled')
")->fetch_assoc()['s'];

$overall_po_total = (float)$conn->query("
    SELECT IFNULL(SUM(po.total_amount),0) AS s 
    FROM purchase_orders po
    INNER JOIN purchase_requests pr ON po.pr_id = pr.id
    WHERE (pr.status IS NULL OR pr.status != 'Cancelled')
")->fetch_assoc()['s'];

$overall_dv_gross_regular = (float)$conn->query("
    SELECT IFNULL(SUM(dv.gross_amount),0) AS s 
    FROM disbursement_vouchers dv
    INNER JOIN purchase_requests pr ON dv.pr_id = pr.id
    WHERE (pr.status IS NULL OR pr.status != 'Cancelled')
")->fetch_assoc()['s'];

$overall_dv_gross_payroll = (float)$conn->query("
    SELECT IFNULL(SUM(gross_amount),0) AS s
    FROM payroll_dvs
")->fetch_assoc()['s'];

$overall_dv_gross = $overall_dv_gross_regular + $overall_dv_gross_payroll;


$overall_dv_tax = (float)$conn->query("
    SELECT IFNULL(SUM(dv.tax_amount),0) AS s 
    FROM disbursement_vouchers dv
    INNER JOIN purchase_requests pr ON dv.pr_id = pr.id
    WHERE (pr.status IS NULL OR pr.status != 'Cancelled')
")->fetch_assoc()['s'];

$overall_dv_net = (float)$conn->query("
    SELECT IFNULL(SUM(dv.net_amount),0) AS s 
    FROM disbursement_vouchers dv
    INNER JOIN purchase_requests pr ON dv.pr_id = pr.id
    WHERE (pr.status IS NULL OR pr.status != 'Cancelled')
")->fetch_assoc()['s'];

// Chart data
$chart_labels = json_encode(['PR Total','PO Total','DV Gross','DV Tax','DV Net']);
$chart_values = json_encode([
    round($overall_pr_total,2),
    round($overall_po_total,2),
    round($overall_dv_gross,2),
    round($overall_dv_tax,2),
    round($overall_dv_net,2),
]);

$pr_vs_po_labels = json_encode(['PR Amount','PO Amount']);
$pr_vs_po_values = json_encode([ round($pr_total,2), round($po_total_for_pr,2) ]);
?>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--navy:#0f172a;--blue:#2563eb;--border:#e2e8f0;--surface:#f1f5f9;--card:#fff;--text:#0f172a;--muted:#64748b}
body{font-family:'DM Sans',sans-serif;background:var(--surface)}
.rpt-wrap{padding:1.5rem 2rem;max-width:1300px;margin:0 auto;width:100%;box-sizing:border-box;}
/* Search bar */
.rpt-search-card{background:var(--card);border-radius:.875rem;border:1px solid var(--border);overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05);margin-bottom:1.5rem;}
.rpt-search-head{background:linear-gradient(135deg,var(--navy),#1e40af);padding:1.25rem 1.5rem;display:flex;align-items:center;gap:.875rem;}
.rpt-search-head-icon{background:rgba(255,255,255,.15);width:44px;height:44px;border-radius:.625rem;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#93c5fd;}
.rpt-search-head h2{font-family:'Space Grotesk',sans-serif;font-size:1.1rem;font-weight:700;color:#fff;margin:0;}
.rpt-search-head p{font-size:.75rem;color:rgba(255,255,255,.6);margin:.15rem 0 0;}
.rpt-search-body{padding:1.25rem 1.5rem;display:flex;flex-wrap:wrap;gap:.875rem;align-items:flex-end;}
.rpt-fg{display:flex;flex-direction:column;gap:.3rem;flex:1;min-width:160px;}
.rpt-label{font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;}
.rpt-input{border:1.5px solid var(--border);border-radius:.5rem;padding:.55rem .875rem;font-size:.875rem;outline:none;font-family:inherit;transition:.2s;width:100%;box-sizing:border-box;background:#fff;}
.rpt-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.rpt-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%2364748b' d='M7 10l5 5 5-5H7z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .6rem center;padding-right:1.75rem;cursor:pointer;}
.btn-view{display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,var(--blue),#1d4ed8);color:#fff;font-weight:600;font-size:.875rem;padding:.6rem 1.25rem;border-radius:.5rem;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(37,99,235,.3);transition:all .2s;font-family:inherit;}
.btn-view:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(37,99,235,.4);}
.btn-outline{display:inline-flex;align-items:center;gap:.5rem;background:#f1f5f9;color:var(--muted);font-weight:600;font-size:.875rem;padding:.6rem 1.1rem;border-radius:.5rem;border:1px solid var(--border);cursor:pointer;font-family:inherit;text-decoration:none;transition:.15s;}
.btn-outline:hover{background:#e2e8f0;}
.btn-green{background:linear-gradient(135deg,#059669,#047857);box-shadow:0 4px 12px rgba(5,150,105,.3);border:none;cursor:pointer;}
.btn-green:hover{transform:translateY(-1px);}
/* Summary metrics row */
.metrics-row{display:grid;grid-template-columns:repeat(5,1fr);gap:.875rem;margin-bottom:1.5rem;}
@media(max-width:1100px){.metrics-row{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.metrics-row{grid-template-columns:1fr 1fr}}
.metric-card{background:var(--card);border-radius:.875rem;padding:1.125rem;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,.04);position:relative;overflow:hidden;}
.metric-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;}
.mc-pr::before{background:var(--blue)}
.mc-po::before{background:#7c3aed}
.mc-dv::before{background:#059669}
.mc-tax::before{background:#dc2626}
.mc-net::before{background:#0891b2}
.metric-icon{width:36px;height:36px;border-radius:.5rem;display:flex;align-items:center;justify-content:center;margin-bottom:.625rem;font-size:.9rem;}
.mc-pr .metric-icon{background:#dbeafe;color:var(--blue)}
.mc-po .metric-icon{background:#ede9fe;color:#7c3aed}
.mc-dv .metric-icon{background:#d1fae5;color:#059669}
.mc-tax .metric-icon{background:#fee2e2;color:#dc2626}
.mc-net .metric-icon{background:#e0f2fe;color:#0891b2}
.metric-val{font-size:1.25rem;font-weight:700;color:var(--text);line-height:1;margin-bottom:.2rem;font-variant-numeric:tabular-nums;}
.metric-lbl{font-size:.72rem;color:var(--muted);font-weight:500;}
/* Charts row */
.charts-row{display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.5rem;}
@media(max-width:900px){.charts-row{grid-template-columns:1fr;}}
.chart-card{background:var(--card);border-radius:.875rem;padding:1.25rem;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,.04);}
.chart-title{font-family:'Space Grotesk',sans-serif;font-size:.875rem;font-weight:700;color:var(--text);margin-bottom:.875rem;display:flex;align-items:center;gap:.4rem;}
/* PR Detail Card */
.pr-detail-card{background:var(--card);border-radius:.875rem;border:1px solid var(--border);overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:1.25rem;}
.pr-detail-head{background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;}
.pr-detail-head-left h3{font-family:'Space Grotesk',sans-serif;font-size:1rem;font-weight:700;color:#fff;margin:0;}
.pr-detail-head-left p{font-size:.78rem;color:rgba(255,255,255,.65);margin:.2rem 0 0;}
.pr-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .75rem;border-radius:9999px;font-size:.72rem;font-weight:700;}
.pr-badge-approved{background:rgba(16,185,129,.2);color:#6ee7b7;border:1px solid rgba(16,185,129,.3);}
.pr-badge-pending{background:rgba(245,158,11,.2);color:#fcd34d;border:1px solid rgba(245,158,11,.3);}
.pr-badge-cancelled{background:rgba(239,68,68,.2);color:#fca5a5;border:1px solid rgba(239,68,68,.3);}
/* PR info grid */
.pr-info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-bottom:1px solid var(--border);}
@media(max-width:768px){.pr-info-grid{grid-template-columns:1fr;}}
.pr-info-cell{padding:1rem 1.5rem;border-right:1px solid var(--border);}
.pr-info-cell:last-child{border-right:none;}
.pr-info-lbl{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:.3rem;}
.pr-info-val{font-size:.875rem;font-weight:600;color:var(--text);}
/* Variance banner */
.variance-banner{padding:.875rem 1.5rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;border-bottom:1px solid var(--border);}
.var-item{display:flex;flex-direction:column;gap:.15rem;}
.var-item-lbl{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.var-item-val{font-size:1.05rem;font-weight:700;font-variant-numeric:tabular-nums;}
/* Document section */
.doc-section{margin:0;}
.doc-section-head{padding:.875rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.625rem;background:#f8fafc;}
.doc-section-head h4{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text);margin:0;}
.doc-section-body{padding:0 1.5rem 1rem;}
/* Document table */
.doc-table{width:100%;border-collapse:collapse;font-size:.8rem;margin-top:.75rem;}
.doc-table th{padding:.6rem .875rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700;border-bottom:2px solid var(--border);background:#f8fafc;}
.doc-table td{padding:.7rem .875rem;border-bottom:1px solid var(--border);vertical-align:middle;}
.doc-table tr:last-child td{border-bottom:none;}
.doc-table tr:hover td{background:#f8fafc;}
.doc-ref{font-weight:700;font-family:monospace;}
.doc-ref-pr{color:var(--blue)}.doc-ref-rfq{color:#059669}.doc-ref-po{color:#7c3aed}.doc-ref-iar{color:#d97706}.doc-ref-dv{color:#dc2626}
.status-pill{display:inline-flex;align-items:center;gap:.25rem;padding:.18rem .55rem;border-radius:9999px;font-size:.68rem;font-weight:700;}
.sp-complete{background:#d1fae5;color:#065f46}.sp-lacking{background:#fef3c7;color:#92400e}
.amount-green{color:#059669;font-weight:700;font-variant-numeric:tabular-nums;}
.amount-red{color:#dc2626;font-variant-numeric:tabular-nums;}
.amount-blue{color:var(--blue);font-weight:700;font-variant-numeric:tabular-nums;}
.empty-doc{padding:1.5rem;text-align:center;color:var(--muted);font-style:italic;font-size:.8rem;}
/* Sub-PR table */
.sub-pr-row{background:#f5f3ff;}
@media(max-width:768px){.rpt-wrap{padding:1rem;}}
</style>

<main class="flex-1 overflow-y-auto" style="background:var(--surface);min-height:calc(100vh - 76px);">
<div class="rpt-wrap">

  <!-- Search / Select Card -->
  <div class="rpt-search-card">
    <div class="rpt-search-head">
      <div class="rpt-search-head-icon"><i class="fas fa-chart-line"></i></div>
      <div>
        <h2>Procurement Reports</h2>
        <p>Search by PR Number or select from the dropdown to generate a detailed procurement report</p>
      </div>
      <?php if ($pr_id && $pr): ?>
      <div style="margin-left:auto;display:flex;gap:.5rem;">
        <form action="export_excel.php" method="post" target="_blank" style="display:inline;">
          <input type="hidden" name="pr_id" value="<?= $pr_id ?>">
          <button type="submit" class="btn-outline btn-green" style="color:#fff;">
            <i class="fas fa-file-excel"></i> Export Excel
          </button>
        </form>
        <button onclick="window.print()" class="btn-outline"><i class="fas fa-print"></i> Print</button>
      </div>
      <?php endif; ?>
    </div>
    <div class="rpt-search-body">
      <form method="get" style="display:contents;">
        <div class="rpt-fg" style="max-width:260px;">
          <span class="rpt-label">Search PR Number</span>
          <input type="search" name="search_pr" value="<?= safe($search_query) ?>"
                 placeholder="e.g. PR-2026-03-001" class="rpt-input">
        </div>
        <div style="display:flex;align-items:center;padding:0 .5rem;color:var(--muted);font-size:.78rem;font-weight:500;align-self:flex-end;padding-bottom:.6rem;">OR</div>
        <div class="rpt-fg" style="max-width:320px;">
          <span class="rpt-label">Select from List</span>
          <select name="pr_id" class="rpt-input rpt-select">
            <option value="">— Select Purchase Request —</option>
            <?php
            $prs_list = $conn->query("SELECT id, pr_number FROM purchase_requests WHERE parent_id IS NULL ORDER BY id DESC");
            while($p = $prs_list->fetch_assoc()):
            ?>
            <option value="<?= $p['id'] ?>" <?= $p['id']==$pr_id?'selected':'' ?>><?= safe($p['pr_number']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div style="display:flex;gap:.5rem;align-self:flex-end;">
          <button type="submit" class="btn-view"><i class="fas fa-search fa-sm"></i> View Report</button>
          <a href="report.php" class="btn-outline"><i class="fas fa-undo fa-sm"></i> Reset</a>
          <a href="overall_list.php" class="btn-outline" style="background:linear-gradient(135deg,#059669,#047857);color:#fff;border-color:#047857;box-shadow:0 4px 12px rgba(5,150,105,.3);">
            <i class="fas fa-list-alt fa-sm"></i> Overall Data List
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Overall Metrics -->
  <div class="metrics-row" id="overallSummarySection">
    <div class="metric-card mc-pr">
      <div class="metric-icon"><i class="fas fa-file-alt"></i></div>
      <div class="metric-val">₱<?= number_format($overall_pr_total,2) ?></div>
      <div class="metric-lbl">Total PR Amount</div>
    </div>
    <div class="metric-card mc-po">
      <div class="metric-icon"><i class="fas fa-file-signature"></i></div>
      <div class="metric-val">₱<?= number_format($overall_po_total,2) ?></div>
      <div class="metric-lbl">Total PO Amount</div>
    </div>
    <div class="metric-card mc-dv">
      <div class="metric-icon"><i class="fas fa-money-check-alt"></i></div>
      <div class="metric-val">₱<?= number_format($overall_dv_gross,2) ?></div>
      <div class="metric-lbl">Total DV Gross</div>
    </div>
    <div class="metric-card mc-tax">
      <div class="metric-icon"><i class="fas fa-percent"></i></div>
      <div class="metric-val">₱<?= number_format($overall_dv_tax,2) ?></div>
      <div class="metric-lbl">Total DV Tax</div>
    </div>
    <div class="metric-card mc-net">
      <div class="metric-icon"><i class="fas fa-wallet"></i></div>
      <div class="metric-val">₱<?= number_format($overall_dv_net,2) ?></div>
      <div class="metric-lbl">Total DV Net</div>
    </div>
  </div>

  <!-- Charts Row (Overall) -->
  <div class="charts-row">
    <div class="chart-card">
      <div class="chart-title"><i class="fas fa-chart-bar" style="color:var(--blue)"></i> Overall Procurement Comparison</div>
      <div style="position:relative;height:230px;"><canvas id="summaryBar"></canvas></div>
    </div>
    <div class="chart-card">
      <div class="chart-title"><i class="fas fa-chart-pie" style="color:#7c3aed"></i> Distribution</div>
      <div style="position:relative;height:230px;"><canvas id="summaryPie"></canvas></div>
    </div>
  </div>

  <?php if ($search_query && !$pr): ?>
  <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:1rem 1.5rem;border-radius:.875rem;margin-bottom:1.25rem;font-size:.875rem;">
    <i class="fas fa-exclamation-circle mr-2"></i>No Purchase Request found with number "<strong><?= safe($search_query) ?></strong>".
  </div>
  <?php endif; ?>

  <?php if ($pr_id && $pr): ?>
  <!-- PR Detail Card -->
  <div class="pr-detail-card" id="report-content">

    <!-- Header -->
    <div class="pr-detail-head">
      <div class="pr-detail-head-left">
        <h3><i class="fas fa-file-alt mr-2" style="color:#93c5fd;"></i><?= safe($pr['pr_number']) ?></h3>
        <p><?= safe($pr['purpose']) ?></p>
      </div>
      <div style="display:flex;gap:.625rem;flex-wrap:wrap;align-items:center;">
        <?php
        $st = $pr['status'] ?? 'Pending';
        $stClass = match(strtolower($st)) {
          'approved' => 'pr-badge-approved',
          'cancelled' => 'pr-badge-cancelled',
          default => 'pr-badge-pending'
        };
        ?>
        <span class="pr-badge <?= $stClass ?>"><i class="fas fa-circle fa-xs"></i><?= strtoupper($st) ?></span>
        <span style="background:rgba(255,255,255,.12);color:rgba(255,255,255,.8);font-size:.72rem;padding:.25rem .75rem;border-radius:9999px;border:1px solid rgba(255,255,255,.2);">
          Fund: <?= safe($pr['fund_cluster']) ?>
        </span>
      </div>
    </div>

    <!-- PR Info Grid -->
    <div class="pr-info-grid">
      <div class="pr-info-cell">
        <div class="pr-info-lbl"><i class="fas fa-building fa-xs mr-1"></i>Entity Name</div>
        <div class="pr-info-val"><?= safe($pr['entity_name']) ?></div>
      </div>
      <div class="pr-info-cell">
        <div class="pr-info-lbl"><i class="fas fa-calendar fa-xs mr-1"></i>PR Date</div>
        <div class="pr-info-val"><?= date('F d, Y', strtotime($pr['pr_date'])) ?></div>
      </div>
      <div class="pr-info-cell">
        <div class="pr-info-lbl"><i class="fas fa-peso-sign fa-xs mr-1"></i>Total Amount</div>
        <div class="pr-info-val amount-blue">₱<?= number_format($pr_total,2) ?></div>
      </div>
    </div>

    <!-- Variance Banner -->
    <div class="variance-banner">
      <div class="var-item">
        <span class="var-item-lbl">PR Amount</span>
        <span class="var-item-val amount-blue">₱<?= number_format($pr_total,2) ?></span>
      </div>
      <div style="color:var(--muted);">→</div>
      <div class="var-item">
        <span class="var-item-lbl">PO Total (linked)</span>
        <span class="var-item-val" style="color:#7c3aed;">₱<?= number_format($po_total_for_pr,2) ?></span>
      </div>
      <div style="color:var(--muted);">→</div>
      <div class="var-item">
        <span class="var-item-lbl">Variance (PR − PO)</span>
        <span class="var-item-val <?= $variance < 0 ? 'amount-red' : 'amount-green' ?>">
          <?= $variance < 0 ? '−' : '+' ?>₱<?= number_format(abs($variance),2) ?>
          <span style="font-size:.72rem;font-weight:400;color:var(--muted);">(<?= number_format(abs($variance_pct),1) ?>%)</span>
        </span>
      </div>
      <?php if ($base_amount != $pr_total): ?>
      <div style="margin-left:auto;" class="var-item">
        <span class="var-item-lbl">Base Amount (excl. Sub-PRs)</span>
        <span class="var-item-val" style="color:var(--text);">₱<?= number_format($base_amount,2) ?></span>
      </div>
      <?php endif; ?>
      <!-- Mini PR vs PO chart -->
      <div style="margin-left:auto;width:140px;height:60px;"><canvas id="prVsPoChart"></canvas></div>
    </div>

    <?php if (!empty($subprs)): ?>
    <!-- Sub-PRs -->
    <div class="doc-section">
      <div class="doc-section-head" style="background:#f5f3ff;">
        <i class="fas fa-sitemap" style="color:#7c3aed;"></i>
        <h4 style="color:#6d28d9;">Linked Sub-PRs & Late Inserted PRs</h4>
        <span style="margin-left:auto;background:#ede9fe;color:#7c3aed;font-size:.68rem;padding:.15rem .5rem;border-radius:9999px;font-weight:700;"><?= count($subprs) ?> records</span>
      </div>
      <div class="doc-section-body">
        <table class="doc-table">
          <thead><tr>
            <th>PR Number</th><th>Date</th><th>Entity</th><th>Fund</th><th>Purpose</th><th class="text-right">Amount</th>
          </tr></thead>
          <tbody>
          <?php foreach($subprs as $s): ?>
          <tr class="sub-pr-row">
            <td><span class="doc-ref doc-ref-pr"><?= safe($s['pr_number']) ?></span></td>
            <td><?= date('M d, Y', strtotime($s['pr_date'])) ?></td>
            <td><?= safe($s['entity_name']) ?></td>
            <td><?= safe($s['fund_cluster']) ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= safe($s['purpose']) ?>"><?= safe($s['purpose']) ?></td>
            <td class="amount-blue" style="text-align:right;">₱<?= number_format($s['total_amount'],2) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- RFQs -->
    <div class="doc-section" style="border-top:1px solid var(--border);">
      <div class="doc-section-head" style="background:#f0fdf4;">
        <i class="fas fa-quote-right" style="color:#059669;"></i>
        <h4 style="color:#065f46;">Request for Quotations (RFQ)</h4>
        <?php $rfq_count = $rfqs ? $rfqs->num_rows : 0; ?>
        <span style="margin-left:auto;background:#d1fae5;color:#059669;font-size:.68rem;padding:.15rem .5rem;border-radius:9999px;font-weight:700;"><?= $rfq_count ?> record<?= $rfq_count!=1?'s':'' ?></span>
      </div>
      <div class="doc-section-body">
        <?php if ($rfqs && $rfqs->num_rows > 0): ?>
        <table class="doc-table">
          <thead><tr><th>RFQ Number</th><th>Date</th><th>Linked PR</th></tr></thead>
          <tbody>
          <?php while($r=$rfqs->fetch_assoc()): ?>
          <tr>
            <td><span class="doc-ref doc-ref-rfq"><?= safe($r['rfq_number']) ?></span></td>
            <td><?= date('M d, Y', strtotime($r['rfq_date'])) ?></td>
            <td><span class="doc-ref doc-ref-pr"><?= safe($pr['pr_number']) ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?><p class="empty-doc"><i class="fas fa-minus-circle mr-1"></i>No RFQ linked to this PR</p><?php endif; ?>
      </div>
    </div>

    <!-- POs -->
    <div class="doc-section" style="border-top:1px solid var(--border);">
      <div class="doc-section-head" style="background:#faf5ff;">
        <i class="fas fa-file-signature" style="color:#7c3aed;"></i>
        <h4 style="color:#6d28d9;">Purchase Orders (PO)</h4>
        <?php $po_count = $pos ? $pos->num_rows : 0; ?>
        <span style="margin-left:auto;background:#ede9fe;color:#7c3aed;font-size:.68rem;padding:.15rem .5rem;border-radius:9999px;font-weight:700;"><?= $po_count ?> record<?= $po_count!=1?'s':'' ?></span>
      </div>
      <div class="doc-section-body">
        <?php if ($pos && $pos->num_rows > 0): ?>
        <table class="doc-table">
          <thead><tr><th>PO Number</th><th>PO Date</th><th>Supplier</th><th>Award Date</th><th class="text-right">Total Amount</th></tr></thead>
          <tbody>
          <?php while($r=$pos->fetch_assoc()): ?>
          <tr>
            <td><span class="doc-ref doc-ref-po"><?= safe($r['po_number']) ?></span></td>
            <td><?= date('M d, Y', strtotime($r['po_date'])) ?></td>
            <td><?= safe($r['supplier']) ?></td>
            <td><?= !empty($r['date_of_award']) ? date('M d, Y', strtotime($r['date_of_award'])) : '—' ?></td>
            <td class="amount-blue" style="text-align:right;">₱<?= number_format($r['total_amount'],2) ?></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?><p class="empty-doc"><i class="fas fa-minus-circle mr-1"></i>No Purchase Orders linked</p><?php endif; ?>
      </div>
    </div>

    <!-- IARs -->
    <div class="doc-section" style="border-top:1px solid var(--border);">
      <div class="doc-section-head" style="background:#fffbeb;">
        <i class="fas fa-clipboard-check" style="color:#d97706;"></i>
        <h4 style="color:#b45309;">Inspection & Acceptance Reports (IAR)</h4>
        <?php $iar_count = $iars ? $iars->num_rows : 0; ?>
        <span style="margin-left:auto;background:#fef3c7;color:#d97706;font-size:.68rem;padding:.15rem .5rem;border-radius:9999px;font-weight:700;"><?= $iar_count ?> record<?= $iar_count!=1?'s':'' ?></span>
      </div>
      <div class="doc-section-body">
        <?php if ($iars && $iars->num_rows > 0): ?>
        <table class="doc-table">
          <thead><tr><th>IAR Number</th><th>Linked PO</th><th>Invoice Number</th><th>Date Inspected</th><th>Date Received</th><th>Status</th></tr></thead>
          <tbody>
          <?php while($r=$iars->fetch_assoc()): ?>
          <tr>
            <td><span class="doc-ref doc-ref-iar"><?= safe($r['iar_number']) ?></span></td>
            <td><span class="doc-ref doc-ref-po"><?= safe($r['po_number']) ?></span></td>
            <td><?= safe($r['invoice_number']) ?></td>
            <td><?= date('M d, Y', strtotime($r['date_inspected'])) ?></td>
            <td><?= !empty($r['date_received']) ? date('M d, Y', strtotime($r['date_received'])) : '—' ?></td>
            <td><span class="status-pill <?= $r['status']==='Complete'?'sp-complete':'sp-lacking' ?>"><?= safe($r['status']) ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?><p class="empty-doc"><i class="fas fa-minus-circle mr-1"></i>No IAR records linked</p><?php endif; ?>
      </div>
    </div>

    <!-- DVs -->
    <div class="doc-section" style="border-top:1px solid var(--border);">
      <div class="doc-section-head" style="background:#fef2f2;">
        <i class="fas fa-money-check-alt" style="color:#dc2626;"></i>
        <h4 style="color:#991b1b;">Disbursement Vouchers (DV)</h4>
        <?php $dv_count = $dvs ? $dvs->num_rows : 0; ?>
        <span style="margin-left:auto;background:#fee2e2;color:#dc2626;font-size:.68rem;padding:.15rem .5rem;border-radius:9999px;font-weight:700;"><?= $dv_count ?> record<?= $dv_count!=1?'s':'' ?></span>
      </div>
      <div class="doc-section-body">
        <?php if ($dvs && $dvs->num_rows > 0): ?>
        <table class="doc-table">
          <thead><tr><th>DV Number</th><th>DV Date</th><th>Supplier</th><th>Tax Type</th><th class="text-right">Gross</th><th class="text-right">Tax</th><th class="text-right">Net</th><th>Status</th></tr></thead>
          <tbody>
          <?php while($r=$dvs->fetch_assoc()): ?>
          <tr>
            <td><span class="doc-ref doc-ref-dv"><?= safe($r['dv_number']) ?></span></td>
            <td><?= date('M d, Y', strtotime($r['dv_date'])) ?></td>
            <td><?= safe($r['supplier']) ?></td>
            <td style="font-size:.75rem;color:var(--muted);"><?= safe($r['tax_type']) ?></td>
            <td class="amount-blue" style="text-align:right;">₱<?= number_format($r['gross_amount'],2) ?></td>
            <td class="amount-red" style="text-align:right;">₱<?= number_format($r['tax_amount'],2) ?></td>
            <td class="amount-green" style="text-align:right;">₱<?= number_format($r['net_amount'],2) ?></td>
            <td><span class="status-pill <?= $r['status']==='Complete'?'sp-complete':'sp-lacking' ?>"><?= safe($r['status']) ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?><p class="empty-doc"><i class="fas fa-minus-circle mr-1"></i>No Disbursement Vouchers linked</p><?php endif; ?>
      </div>
    </div>

  </div><!-- /pr-detail-card -->
  <?php endif; ?>

  <!-- Overall summary print section -->
  <div id="overallPrintSection" style="display:none;"></div>

</div><!-- /rpt-wrap -->
</main>

<style>
@media print {
  body * { visibility:hidden; }
  .no-print { display:none !important; }
  #report-content, #report-content * { visibility:visible; }
  #report-content { position:absolute;left:0;top:0;margin:20px;width:calc(100% - 40px); }
  .rpt-search-card,.metrics-row,.charts-row { display:none; }
  table { font-size:10px; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const summaryLabels = <?= $chart_labels ?>;
const summaryValues = <?= $chart_values ?>;

// Bar chart
const ctxBar = document.getElementById('summaryBar');
if (ctxBar) {
  new Chart(ctxBar.getContext('2d'), {
    type:'bar',
    data:{
      labels: summaryLabels,
      datasets:[{
        label:'Amount (₱)',
        data: summaryValues,
        backgroundColor:['#3b82f6','#7c3aed','#059669','#dc2626','#0891b2'],
        borderRadius:6, borderSkipped:false
      }]
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>'₱'+c.parsed.y.toLocaleString('en-PH')}}},
      scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{callback:v=>'₱'+(v>=1e6?(v/1e6).toFixed(1)+'M':v>=1e3?(v/1e3).toFixed(0)+'K':v)}},x:{grid:{display:false}}}
    }
  });
}

// Pie chart
const ctxPie = document.getElementById('summaryPie');
if (ctxPie) {
  new Chart(ctxPie.getContext('2d'), {
    type:'doughnut',
    data:{
      labels:['PR Total','PO Total','DV Gross'],
      datasets:[{data:[summaryValues[0],summaryValues[1],summaryValues[2]],backgroundColor:['#3b82f6','#7c3aed','#059669'],borderWidth:0,hoverOffset:4}]
    },
    options:{
      responsive:true,maintainAspectRatio:false,cutout:'60%',
      plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:12,font:{size:11}}},
               tooltip:{callbacks:{label:c=>'₱'+c.parsed.toLocaleString('en-PH')}}}
    }
  });
}

<?php if ($pr_id && $pr): ?>
// PR vs PO mini chart
const ctxPrPo = document.getElementById('prVsPoChart');
if (ctxPrPo) {
  new Chart(ctxPrPo.getContext('2d'), {
    type:'bar',
    data:{
      labels:['PR','PO'],
      datasets:[{data:[<?= round($pr_total,2) ?>,<?= round($po_total_for_pr,2) ?>],backgroundColor:['#3b82f6','#7c3aed'],borderRadius:4,borderSkipped:false}]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>'₱'+c.parsed.y.toLocaleString('en-PH')}}},
      scales:{y:{display:false,beginAtZero:true},x:{grid:{display:false},ticks:{font:{size:10}}}}
    }
  });
}
<?php endif; ?>
</script>

<?php include 'inc/footer.php'; ?>
