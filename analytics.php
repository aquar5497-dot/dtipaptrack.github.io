<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require_once 'config/db.php';

/* ═══════════════════════════════════════════════════════════════════
   DESCRIPTIVE ANALYTICS — Data Layer
   All queries use aggregation only; no raw user data exposed.
═══════════════════════════════════════════════════════════════════ */

// ── Date range filter ─────────────────────────────────────────────
$range  = $_GET['range']  ?? '12'; // months back
$custom_from = trim($_GET['from'] ?? '');
$custom_to   = trim($_GET['to']   ?? '');

if ($custom_from && $custom_to) {
    $date_from = $custom_from;
    $date_to   = $custom_to;
    $range_label = date('M d, Y', strtotime($date_from)) . ' – ' . date('M d, Y', strtotime($date_to));
} else {
    $months = max(1, min(60, (int)$range));
    $date_from = date('Y-m-d', strtotime("-{$months} months"));
    $date_to   = date('Y-m-d');
    $range_label = "Last {$months} month" . ($months > 1 ? 's' : '');
}

// ── 1. KPIs — ALL-TIME totals (NO date filter), identical to index.php dashboard
// The date range only affects chart/trend data below, NOT the KPI cards.

// PR: parent PRs only, excluding cancelled — exact match with dashboard
$kpi_pr = $conn->query("
    SELECT COUNT(CASE WHEN parent_id IS NULL THEN 1 END) AS c,
           IFNULL(SUM(total_amount),0) AS s
    FROM purchase_requests
    WHERE (status IS NULL OR status != 'Cancelled')
")->fetch_assoc();

// PO: all purchase orders — same as dashboard
$kpi_po = $conn->query("
    SELECT COUNT(*) AS c, IFNULL(SUM(total_amount),0) AS s
    FROM purchase_orders
")->fetch_assoc();

// IAR: all IARs — same as dashboard
$kpi_iar = $conn->query("
    SELECT COUNT(*) AS c FROM iars
")->fetch_assoc();

// DV gross (regular, non-cancelled PRs) — same as dashboard $total_dvs_regular
$kpi_dv_reg = $conn->query("
    SELECT COUNT(dv.id) AS c,
           IFNULL(SUM(dv.gross_amount),0) AS s,
           IFNULL(SUM(dv.tax_amount),0)   AS tax,
           IFNULL(SUM(dv.net_amount),0)   AS net
    FROM disbursement_vouchers dv
    INNER JOIN purchase_requests pr ON dv.pr_id = pr.id
    WHERE (pr.status IS NULL OR pr.status != 'Cancelled')
")->fetch_assoc();

// DV payroll — same as dashboard $total_dvs_payroll
$kpi_dv_pay = $conn->query("
    SELECT COUNT(*) AS c, IFNULL(SUM(gross_amount),0) AS s
    FROM payroll_dvs
")->fetch_assoc();

// Cancelled PRs — all time, parent PRs only
$kpi_cancelled = $conn->query("
    SELECT COUNT(*) AS c
    FROM purchase_requests
    WHERE status = 'Cancelled' AND parent_id IS NULL
")->fetch_assoc();

// RFQs — all time
$kpi_rfq = $conn->query("SELECT COUNT(*) AS c FROM rfqs")->fetch_assoc();

// Payroll records — all time
$kpi_payroll = $conn->query("
    SELECT COUNT(*) AS c, IFNULL(SUM(salary_amount),0) AS s
    FROM payroll_requests
")->fetch_assoc();

$kpi = [
    'total_pr'      => (int)($kpi_pr['c']      ?? 0),
    'total_pr_amt'  => (float)($kpi_pr['s']     ?? 0),
    'total_po'      => (int)($kpi_po['c']      ?? 0),
    'total_po_amt'  => (float)($kpi_po['s']     ?? 0),
    'total_iar'     => (int)($kpi_iar['c']     ?? 0),
    'total_rfq'     => (int)($kpi_rfq['c']     ?? 0),
    'total_dv'      => (int)($kpi_dv_reg['c']  ?? 0) + (int)($kpi_dv_pay['c'] ?? 0),
    'total_dv_gross'=> (float)($kpi_dv_reg['s'] ?? 0) + (float)($kpi_dv_pay['s'] ?? 0),
    'total_dv_net'  => (float)($kpi_dv_reg['net'] ?? 0),
    'total_tax'     => (float)($kpi_dv_reg['tax'] ?? 0),
    'cancelled_pr'  => (int)($kpi_cancelled['c'] ?? 0),
];

$payroll_summary = [
    'total_payroll' => (int)($kpi_payroll['c']   ?? 0),
    'total_salary'  => (float)($kpi_payroll['s'] ?? 0),
];

// ── 2. Monthly PR trend (count + amount) ─────────────────────────
$monthly = $conn->query("
    SELECT DATE_FORMAT(pr_date,'%Y-%m') AS ym,
           DATE_FORMAT(pr_date,'%b %Y') AS label,
           COUNT(*) AS cnt,
           SUM(total_amount) AS amt
    FROM purchase_requests
    WHERE pr_date BETWEEN '$date_from' AND '$date_to'
      AND parent_id IS NULL
    GROUP BY ym ORDER BY ym ASC
")->fetch_all(MYSQLI_ASSOC);

// ── 3. Top suppliers by spend ─────────────────────────────────────
$top_suppliers = $conn->query("
    SELECT po.supplier,
           COUNT(*) AS po_count,
           SUM(po.total_amount) AS total_spend
    FROM purchase_orders po
    INNER JOIN purchase_requests pr ON po.pr_id = pr.id
    WHERE pr.pr_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY po.supplier
    ORDER BY total_spend DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── 4. Fund cluster distribution ─────────────────────────────────
$fund_dist = $conn->query("
    SELECT fund_cluster,
           COUNT(*) AS cnt,
           SUM(total_amount) AS amt
    FROM purchase_requests
    WHERE pr_date BETWEEN '$date_from' AND '$date_to'
      AND parent_id IS NULL
      AND (status IS NULL OR status != 'Cancelled')
    GROUP BY fund_cluster ORDER BY amt DESC
")->fetch_all(MYSQLI_ASSOC);

// ── 5. Entity activity ────────────────────────────────────────────
$entity_dist = $conn->query("
    SELECT entity_name,
           COUNT(*) AS cnt,
           SUM(total_amount) AS amt
    FROM purchase_requests
    WHERE pr_date BETWEEN '$date_from' AND '$date_to'
      AND parent_id IS NULL
    GROUP BY entity_name ORDER BY cnt DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// ── 6. Procurement Funnel — ALL-TIME, same logic as proceedings.php ─
// proceedings.php counts all PRs with no date filter for the step badges
$funnel_pr  = (int)$conn->query("
    SELECT COUNT(*) AS c FROM purchase_requests
    WHERE parent_id IS NULL
")->fetch_assoc()['c'];

$funnel_rfq = (int)$conn->query("
    SELECT COUNT(DISTINCT r.pr_id) AS c
    FROM rfqs r
    INNER JOIN purchase_requests pr ON r.pr_id = pr.id
    WHERE pr.parent_id IS NULL
")->fetch_assoc()['c'];

$funnel_po  = (int)$conn->query("
    SELECT COUNT(DISTINCT po.pr_id) AS c
    FROM purchase_orders po
    INNER JOIN purchase_requests pr ON po.pr_id = pr.id
    WHERE pr.parent_id IS NULL
")->fetch_assoc()['c'];

$funnel_iar = (int)$conn->query("
    SELECT COUNT(DISTINCT po.pr_id) AS c
    FROM iars i
    INNER JOIN purchase_orders po ON i.po_id = po.id
    INNER JOIN purchase_requests pr ON po.pr_id = pr.id
    WHERE pr.parent_id IS NULL
")->fetch_assoc()['c'];

$funnel_dv  = (int)$conn->query("
    SELECT COUNT(DISTINCT dv.pr_id) AS c
    FROM disbursement_vouchers dv
    INNER JOIN purchase_requests pr ON dv.pr_id = pr.id
    WHERE pr.parent_id IS NULL
")->fetch_assoc()['c'];

$funnel = [
    'has_pr'  => $funnel_pr,
    'has_rfq' => $funnel_rfq,
    'has_po'  => $funnel_po,
    'has_iar' => $funnel_iar,
    'has_dv'  => $funnel_dv,
];

// ── 7. Average cycle times — same method as progress_overview.php ─
// Uses pr_date/po_date/dv_date in SECONDS (same as stats_query in progress_overview)
$cycle = $conn->query("
    SELECT
      ROUND(AVG(TIMESTAMPDIFF(SECOND, pr.pr_date, po.po_date)) / 3600, 1) AS avg_pr_to_po_h,
      ROUND(AVG(TIMESTAMPDIFF(SECOND, po.po_date, dv.dv_date)) / 3600, 1) AS avg_po_to_dv_h,
      ROUND((AVG(TIMESTAMPDIFF(SECOND, pr.pr_date, po.po_date)) +
             AVG(TIMESTAMPDIFF(SECOND, po.po_date, dv.dv_date))) / 3600, 1) AS avg_total_h,
      MIN(TIMESTAMPDIFF(HOUR, pr.pr_date, dv.dv_date))                    AS min_total_h,
      MAX(TIMESTAMPDIFF(HOUR, pr.pr_date, dv.dv_date))                    AS max_total_h
    FROM purchase_requests pr
    LEFT JOIN purchase_orders po ON po.pr_id = pr.id
    LEFT JOIN disbursement_vouchers dv ON dv.pr_id = pr.id
    WHERE pr.parent_id IS NULL
")->fetch_assoc();

// ── 8. Tax breakdown ─────────────────────────────────────────────
$tax_types = $conn->query("
    SELECT CASE
             WHEN tax_type LIKE 'Tax Based%'  THEN 'Tax Based Classification'
             WHEN tax_type LIKE 'Goods%'       THEN 'Goods Withholding (1%)'
             WHEN tax_type LIKE 'Services%'    THEN 'Services Withholding (2%)'
             WHEN tax_type LIKE 'Rent%'        THEN 'Rent Withholding (5%)'
             WHEN tax_type LIKE 'Professional%'THEN 'Professional Withholding (10%)'
             ELSE IFNULL(tax_type,'Other')
           END AS type_label,
           COUNT(*) AS cnt,
           SUM(tax_amount) AS total_tax
    FROM disbursement_vouchers
    GROUP BY type_label ORDER BY total_tax DESC
")->fetch_all(MYSQLI_ASSOC);

// ── 9. IAR receipt status ────────────────────────────────────────
$iar_receipt = $conn->query("
    SELECT receipt_status, COUNT(*) AS cnt
    FROM iars GROUP BY receipt_status
")->fetch_all(MYSQLI_ASSOC);

// ── 10. DV status split ──────────────────────────────────────────
$dv_status = $conn->query("
    SELECT status, COUNT(*) AS cnt, SUM(gross_amount) AS amt
    FROM disbursement_vouchers GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

// ── 10b. Monthly DV trend (count + gross amount) ─────────────────
$monthly_dv_raw = $conn->query("
    SELECT DATE_FORMAT(dv_date,'%Y-%m') AS ym,
           DATE_FORMAT(dv_date,'%b %Y') AS label,
           COUNT(*) AS cnt,
           SUM(gross_amount) AS amt
    FROM disbursement_vouchers
    WHERE dv_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY ym ORDER BY ym ASC
")->fetch_all(MYSQLI_ASSOC);

// ── 11. Payroll summary is already in $payroll_summary above ──

// ── Helper: format hours ──────────────────────────────────────────
function fmtHours(?float $h): string {
    if ($h === null || $h <= 0) return '—';
    if ($h < 24) return round($h, 1) . 'h';
    $d = floor($h / 24); $rem = round(fmod($h, 24));
    return $d . 'd' . ($rem ? ' ' . $rem . 'h' : '');
}
function fmtAmt(float $v): string {
    if ($v >= 1000000) return '₱' . number_format($v/1000000,2) . 'M';
    if ($v >= 1000)    return '₱' . number_format($v/1000,1)    . 'K';
    return '₱' . number_format($v, 0);
}

// Prepare JSON for charts
$monthly_labels  = json_encode(array_column($monthly, 'label'));
$monthly_counts  = json_encode(array_column($monthly, 'cnt'));
$monthly_amounts = json_encode(array_map(fn($r) => (float)$r['amt'], $monthly));

$supplier_labels  = json_encode(array_column($top_suppliers, 'supplier'));
$supplier_amounts = json_encode(array_map(fn($r) => (float)$r['total_spend'], $top_suppliers));

$fund_labels  = json_encode(array_column($fund_dist, 'fund_cluster'));
$fund_amounts = json_encode(array_map(fn($r) => (float)$r['amt'], $fund_dist));

$entity_labels = json_encode(array_column($entity_dist, 'entity_name'));
$entity_counts = json_encode(array_column($entity_dist, 'cnt'));

$funnel_data = json_encode([
    (int)($funnel['has_pr']??0),
    (int)($funnel['has_rfq']??0),
    (int)($funnel['has_po']??0),
    (int)($funnel['has_iar']??0),
    (int)($funnel['has_dv']??0),
]);

$tax_labels = json_encode(array_column($tax_types, 'type_label'));
$tax_values = json_encode(array_map(fn($r) => (float)$r['total_tax'], $tax_types));

$monthly_dv_labels  = json_encode(array_column($monthly_dv_raw, 'label'));
$monthly_dv_counts  = json_encode(array_column($monthly_dv_raw, 'cnt'));
$monthly_dv_amounts = json_encode(array_map(fn($r) => (float)$r['amt'], $monthly_dv_raw));

include 'inc/header.php';
include 'inc/sidebar.php';
?>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{--navy:#0f172a;--navy2:#1e3a5f;--blue:#2563eb;--border:#e2e8f0;--surface:#f1f5f9;--card:#fff;--text:#0f172a;--muted:#64748b}
body{font-family:'DM Sans',sans-serif;background:var(--surface)}
.an-content{padding:1.5rem 2rem;max-width:1440px;margin:0 auto;box-sizing:border-box;}
/* Filter bar */
.filter-bar{background:var(--card);border:1px solid var(--border);border-radius:.75rem;padding:.875rem 1.25rem;display:flex;flex-wrap:wrap;gap:.625rem;align-items:flex-end;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.filter-bar label{font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;display:block;margin-bottom:.25rem;}
.filter-bar select,.filter-bar input[type=date]{border:1.5px solid var(--border);border-radius:.4rem;padding:.4rem .7rem;font-size:.8rem;outline:none;font-family:inherit;transition:.15s;background:#fff;}
.filter-bar select:focus,.filter-bar input:focus{border-color:var(--blue);}
.btn-apply{display:inline-flex;align-items:center;gap:.4rem;background:linear-gradient(135deg,var(--blue),#1d4ed8);color:#fff;font-weight:600;font-size:.8rem;padding:.45rem 1rem;border-radius:.4rem;border:none;cursor:pointer;font-family:inherit;box-shadow:0 3px 8px rgba(37,99,235,.3);}
.btn-apply:hover{transform:translateY(-1px);}
/* KPI grid */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:.75rem;margin-bottom:1.25rem;}
@media(max-width:1200px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.kpi-grid{grid-template-columns:1fr 1fr}}
.kpi-card{background:var(--card);border:1px solid var(--border);border-radius:.875rem;padding:1.1rem;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04);transition:transform .15s,box-shadow .15s;}
.kpi-card:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08);}
.kpi-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;}
.kpi-blue::before{background:var(--blue)}.kpi-purple::before{background:#7c3aed}.kpi-green::before{background:#059669}.kpi-amber::before{background:#d97706}.kpi-red::before{background:#dc2626}.kpi-teal::before{background:#0891b2}
.kpi-icon{width:34px;height:34px;border-radius:.5rem;display:flex;align-items:center;justify-content:center;margin-bottom:.625rem;font-size:.85rem;}
.kpi-blue .kpi-icon{background:#dbeafe;color:var(--blue)}.kpi-purple .kpi-icon{background:#ede9fe;color:#7c3aed}.kpi-green .kpi-icon{background:#d1fae5;color:#059669}.kpi-amber .kpi-icon{background:#fef3c7;color:#d97706}.kpi-red .kpi-icon{background:#fee2e2;color:#dc2626}.kpi-teal .kpi-icon{background:#e0f2fe;color:#0891b2}
.kpi-val{font-size:1.2rem;font-weight:700;color:var(--text);line-height:1.1;margin-bottom:.15rem;}
.kpi-lbl{font-size:.7rem;color:var(--muted);font-weight:500;}
.kpi-sub{font-size:.68rem;color:var(--muted);margin-top:.2rem;}
/* Chart grid */
.chart-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;}
.chart-grid-3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:1rem;margin-bottom:1rem;}
@media(max-width:1024px){.chart-grid-2,.chart-grid-3{grid-template-columns:1fr;}}
/* Chart card */
.chart-card{background:var(--card);border:1px solid var(--border);border-radius:.875rem;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.chart-head{padding:.875rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.5rem;}
.chart-head h3{font-family:'Space Grotesk',sans-serif;font-size:.875rem;font-weight:700;color:var(--text);margin:0;}
.chart-head .ch-spacer{flex:1;}
.chart-head span{font-size:.7rem;color:var(--muted);}
.chart-body{padding:1.125rem;position:relative;}
/* Cycle time card */
.cycle-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0;}
.cycle-cell{padding:1rem 1.25rem;border-right:1px solid var(--border);text-align:center;}
.cycle-cell:last-child{border-right:none;}
.cycle-val{font-size:1.4rem;font-weight:700;color:var(--text);font-family:'Space Grotesk',sans-serif;}
.cycle-lbl{font-size:.7rem;color:var(--muted);text-transform:uppercase;font-weight:600;margin-top:.2rem;}
/* Funnel */
.funnel-wrap{display:flex;flex-direction:column;gap:.5rem;padding:1rem 1.25rem;}
.funnel-row{display:flex;align-items:center;gap:.75rem;}
.funnel-label{width:40px;text-align:right;font-size:.7rem;font-weight:700;color:var(--muted);}
.funnel-bar-wrap{flex:1;background:#f1f5f9;border-radius:4px;overflow:hidden;height:24px;position:relative;}
.funnel-bar{height:100%;border-radius:4px;display:flex;align-items:center;padding-left:.5rem;font-size:.72rem;font-weight:700;color:#fff;transition:width .5s ease;}
.funnel-cnt{width:50px;text-align:right;font-size:.78rem;font-weight:700;color:var(--text);}
/* Table */
.an-table{width:100%;border-collapse:collapse;font-size:.8rem;}
.an-table th{background:#f8fafc;padding:.6rem .875rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;border-bottom:2px solid var(--border);}
.an-table td{padding:.65rem .875rem;border-bottom:1px solid var(--border);vertical-align:middle;}
.an-table tr:last-child td{border-bottom:none;}
.an-table tr:hover td{background:#f8fafc;}
.rank-badge{width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;color:#fff;}
.rb-1{background:#f59e0b}.rb-2{background:#94a3b8}.rb-3{background:#b45309}
.spend-bar{height:6px;background:linear-gradient(90deg,#2563eb,#7c3aed);border-radius:3px;margin-top:.25rem;}
@media(max-width:640px){.an-content{padding:1rem;}.cycle-grid{grid-template-columns:1fr;}}

/* ── Maximize overlay ──────────────────────────────────── */
.maximize-btn {
  flex-shrink:0; background:none; border:1.5px solid var(--border);
  border-radius:.375rem; width:28px; height:28px; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  color:var(--muted); transition:all .15s; pointer-events:all;
  position:relative; z-index:2;
}
.maximize-btn:hover { background:#eff6ff; color:var(--blue); border-color:var(--blue); }
.chart-overlay {
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(15,23,42,.65); backdrop-filter:blur(6px);
  align-items:center; justify-content:center; padding:1.25rem;
}
.chart-overlay.active { display:flex; animation:overlayIn .2s ease; }
@keyframes overlayIn { from{opacity:0} to{opacity:1} }
.chart-overlay-box {
  background:#fff; border-radius:1rem; width:100%; max-width:1100px;
  max-height:90vh; display:flex; flex-direction:column;
  box-shadow:0 30px 80px rgba(0,0,0,.3); overflow:hidden;
  animation:boxIn .2s ease;
}
@keyframes boxIn { from{transform:scale(.95);opacity:0} to{transform:scale(1);opacity:1} }
.chart-overlay-head {
  padding:1rem 1.5rem; border-bottom:1px solid var(--border);
  display:flex; align-items:center; gap:.625rem;
  background:#f8fafc; flex-shrink:0;
}
.chart-overlay-head h3 { font-family:'Space Grotesk',sans-serif; font-size:1rem; font-weight:700; color:var(--text); margin:0; }
.overlay-close-btn {
  margin-left:auto; background:none; border:none; cursor:pointer;
  color:var(--muted); font-size:1rem; padding:.25rem; transition:.15s;
}
.overlay-close-btn:hover { color:#dc2626; }
.chart-overlay-body { flex:1; padding:1.5rem; overflow:hidden; position:relative; }
</style>

<main class="flex-1 overflow-y-auto" style="background:var(--surface);">
<div class="an-content">

  <!-- Page Title Bar -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;">
    <div style="display:flex;align-items:center;gap:.75rem;">
      <div style="background:#1e3a5f;color:#fff;width:36px;height:36px;border-radius:.5rem;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.15);">
        <i class="fas fa-chart-line" style="font-size:.85rem;"></i>
      </div>
      <div>
        <h1 style="font-family:'Space Grotesk',sans-serif;font-size:1.35rem;font-weight:700;color:#0f172a;margin:0;line-height:1.2;">Descriptive Analytics</h1>
        <p style="font-size:.75rem;color:#64748b;margin:.1rem 0 0;">Data-driven insights across the full procurement lifecycle &middot; <?= htmlspecialchars($range_label) ?></p>
      </div>
    </div>
    <a href="index.php" style="display:inline-flex;align-items:center;gap:.4rem;background:#1e3a5f;color:#fff;font-weight:600;font-size:.8rem;padding:.45rem .9rem;border-radius:.5rem;text-decoration:none;transition:.15s;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#1e3a5f'">
      <i class="fas fa-home fa-xs"></i> Dashboard
    </a>
  </div>

  <!-- Filter Bar -->
  <form method="GET" class="filter-bar">
    <div>
      <label>Quick Range</label>
      <select name="range" onchange="this.form.submit()">
        <?php foreach ([1=>'Last Month',3=>'Last 3 Months',6=>'Last 6 Months',12=>'Last 12 Months',24=>'Last 2 Years',60=>'All Time (5Y)'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= (int)$range===$v&&!$custom_from?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Custom From</label>
      <input type="date" name="from" value="<?= htmlspecialchars($custom_from) ?>">
    </div>
    <div>
      <label>Custom To</label>
      <input type="date" name="to" value="<?= htmlspecialchars($custom_to) ?>">
    </div>
    <button type="submit" class="btn-apply"><i class="fas fa-filter fa-xs"></i> Apply</button>
    <span style="margin-left:auto;font-size:.78rem;color:var(--muted);">
      <i class="fas fa-calendar-alt fa-xs mr-1"></i>
      <?= date('M d, Y',strtotime($date_from)) ?> — <?= date('M d, Y',strtotime($date_to)) ?>
    </span>
  </form>

  <!-- KPI Row: PR · PO · IAR · Cancelled PR · Payroll · Total DV Gross (ALL-TIME) -->
  <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
    <span style="font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;">System-Wide Totals</span>
    <span style="font-size:.68rem;background:#f1f5f9;color:#94a3b8;padding:.15rem .5rem;border-radius:9999px;border:1px solid #e2e8f0;">All-time · not affected by date range filter</span>
  </div>
  <div class="kpi-grid">
    <div class="kpi-card kpi-blue">
      <div class="kpi-icon"><i class="fas fa-file-alt"></i></div>
      <div class="kpi-val"><?= number_format($kpi['total_pr']) ?></div>
      <div class="kpi-lbl">Purchase Requests</div>
      <div class="kpi-sub"><?= fmtAmt((float)$kpi['total_pr_amt']) ?> total value</div>
    </div>
    <div class="kpi-card kpi-purple">
      <div class="kpi-icon"><i class="fas fa-file-signature"></i></div>
      <div class="kpi-val"><?= number_format($kpi['total_po']) ?></div>
      <div class="kpi-lbl">Purchase Orders</div>
      <div class="kpi-sub"><?= fmtAmt((float)$kpi['total_po_amt']) ?> awarded</div>
    </div>
    <div class="kpi-card kpi-amber">
      <div class="kpi-icon"><i class="fas fa-clipboard-check"></i></div>
      <div class="kpi-val"><?= number_format($kpi['total_iar']) ?></div>
      <div class="kpi-lbl">IARs Completed</div>
      <div class="kpi-sub"><?= number_format($kpi['total_rfq']) ?> RFQs issued</div>
    </div>
    <div class="kpi-card kpi-red">
      <div class="kpi-icon"><i class="fas fa-ban"></i></div>
      <div class="kpi-val"><?= number_format($kpi['cancelled_pr']) ?></div>
      <div class="kpi-lbl">Cancelled PRs</div>
      <?php $cancel_rate = ($kpi['total_pr'] + $kpi['cancelled_pr']) > 0 ? round($kpi['cancelled_pr'] / ($kpi['total_pr'] + $kpi['cancelled_pr']) * 100, 1) : 0; ?>
      <div class="kpi-sub"><?= $cancel_rate ?>% cancellation rate</div>
    </div>
    <div class="kpi-card kpi-teal">
      <div class="kpi-icon"><i class="fas fa-users"></i></div>
      <div class="kpi-val"><?= number_format($payroll_summary['total_payroll'] ?? 0) ?></div>
      <div class="kpi-lbl">Payroll Records</div>
      <div class="kpi-sub"><?= fmtAmt((float)($payroll_summary['total_salary'] ?? 0)) ?> gross salary</div>
    </div>
    <div class="kpi-card kpi-green">
      <div class="kpi-icon"><i class="fas fa-money-check-alt"></i></div>
      <div class="kpi-val" style="font-size:.95rem;">₱<?= number_format((float)$kpi['total_dv_gross'], 2) ?></div>
      <div class="kpi-lbl">Total DV Gross</div>
      <div class="kpi-sub"><?= fmtAmt((float)$kpi['total_tax']) ?> tax withheld</div>
    </div>
  </div>

  <!-- Cycle Times -->
  <div class="chart-card" style="margin-bottom:1rem;">
    <div class="chart-head">
      <i class="fas fa-stopwatch" style="color:#7c3aed;font-size:1rem;"></i>
      <h3>Average Processing Cycle Times</h3>
      <div class="ch-spacer"></div><span>All-time averages · aligned to Progress Overview</span>
      <div class="ch-spacer"></div><span>From document creation timestamps</span>
    </div>
    <div class="cycle-grid">
      <div class="cycle-cell">
        <div class="cycle-val" style="color:var(--blue);"><?= fmtHours((float)($cycle['avg_pr_to_po_h']??0)) ?></div>
        <div class="cycle-lbl">PR → Purchase Order</div>
      </div>
      <div class="cycle-cell">
        <div class="cycle-val" style="color:#7c3aed;"><?= fmtHours((float)($cycle['avg_po_to_dv_h']??0)) ?></div>
        <div class="cycle-lbl">PO → Disbursement</div>
      </div>
      <div class="cycle-cell">
        <div class="cycle-val" style="color:#059669;"><?= fmtHours((float)($cycle['avg_total_h']??0)) ?></div>
        <div class="cycle-lbl">End-to-End Total</div>
      </div>
    </div>
    <div style="padding:.5rem 1.25rem .875rem;display:flex;gap:2rem;font-size:.75rem;color:var(--muted);">
      <span><i class="fas fa-arrow-down mr-1" style="color:#059669;"></i>Fastest: <strong><?= fmtHours((float)($cycle['min_total_h']??0)) ?></strong></span>
      <span><i class="fas fa-arrow-up mr-1" style="color:#dc2626;"></i>Slowest: <strong><?= fmtHours((float)($cycle['max_total_h']??0)) ?></strong></span>
    </div>
  </div>

  <!-- Monthly PR Volume & Spend + Monthly DV Trend -->
  <div class="chart-grid-2">
    <div class="chart-card">
      <div class="chart-head">
        <i class="fas fa-chart-area" style="color:var(--blue);font-size:1rem;"></i>
        <h3>Monthly PR Volume & Spend</h3>
        <div class="ch-spacer"></div><span>count (bars) + amount (line)</span>
        <button class="maximize-btn" onclick="maximizeChart('monthlyChart','Monthly PR Volume &amp; Spend')" title="Maximize"><i class="fas fa-expand-alt fa-xs"></i></button>
      </div>
      <div class="chart-body" style="height:280px;"><canvas id="monthlyChart"></canvas></div>
    </div>
    <div class="chart-card">
      <div class="chart-head">
        <i class="fas fa-chart-line" style="color:#dc2626;font-size:1rem;"></i>
        <h3>Monthly DV Trend</h3>
        <div class="ch-spacer"></div><span>count (bars) + gross amount (line)</span>
        <button class="maximize-btn" onclick="maximizeChart('dvTrendChart','Monthly DV Trend')" title="Maximize"><i class="fas fa-expand-alt fa-xs"></i></button>
      </div>
      <div class="chart-body" style="height:280px;"><canvas id="dvTrendChart"></canvas></div>
    </div>
  </div>

  <!-- Suppliers + Fund Cluster -->
  <div class="chart-grid-2">
    <div class="chart-card">
      <div class="chart-head">
        <i class="fas fa-store" style="color:#059669;font-size:1rem;"></i>
        <h3>Top 10 Suppliers by Spend</h3>
        <div class="ch-spacer"></div><span>total PO value awarded</span>
        <button class="maximize-btn" onclick="maximizeChart('supplierChart','Top 10 Suppliers by Spend')" title="Maximize"><i class="fas fa-expand-alt fa-xs"></i></button>
      </div>
      <div class="chart-body" style="height:280px;"><canvas id="supplierChart"></canvas></div>
    </div>
    <div class="chart-card">
      <div class="chart-head">
        <i class="fas fa-layer-group" style="color:#0891b2;font-size:1rem;"></i>
        <h3>Fund Cluster Distribution</h3>
        <div class="ch-spacer"></div><span>PR amount by fund cluster</span>
        <button class="maximize-btn" onclick="maximizeChart('fundChart','Fund Cluster Distribution')" title="Maximize"><i class="fas fa-expand-alt fa-xs"></i></button>
      </div>
      <div class="chart-body" style="height:280px;"><canvas id="fundChart"></canvas></div>
    </div>
  </div>

  <!-- Most Active Entities + Procurement Funnel + Tax Type Mix -->
  <div class="chart-grid-3">
    <div class="chart-card">
      <div class="chart-head">
        <i class="fas fa-building" style="color:#7c3aed;font-size:1rem;"></i>
        <h3>Most Active Entities (by PR count)</h3>
        <div class="ch-spacer"></div><span>top 8 entities</span>
        <button class="maximize-btn" onclick="maximizeChart('entityChart','Most Active Entities (by PR count)')" title="Maximize"><i class="fas fa-expand-alt fa-xs"></i></button>
      </div>
      <div class="chart-body" style="height:280px;"><canvas id="entityChart"></canvas></div>
    </div>
    <div class="chart-card">
      <div class="chart-head">
        <i class="fas fa-filter" style="color:#7c3aed;font-size:1rem;"></i>
        <h3>Procurement Funnel</h3>
        <div class="ch-spacer"></div><span>All-time · aligned to Proceedings</span>
        <button class="maximize-btn" onclick="maximizeFunnel()" title="Maximize"><i class="fas fa-expand-alt fa-xs"></i></button>
      </div>
      <div class="funnel-wrap">
        <?php
        $funnel_steps = [
          'PR'  => [(int)($funnel['has_pr']??0),  '#2563eb'],
          'RFQ' => [(int)($funnel['has_rfq']??0), '#059669'],
          'PO'  => [(int)($funnel['has_po']??0),  '#7c3aed'],
          'IAR' => [(int)($funnel['has_iar']??0), '#d97706'],
          'DV'  => [(int)($funnel['has_dv']??0),  '#dc2626'],
        ];
        $max_val = max(array_column($funnel_steps, 0)) ?: 1;
        foreach ($funnel_steps as $step => [$cnt, $color]):
          $pct = round($cnt / $max_val * 100);
          $drop = $step !== 'PR' ? round((1 - $cnt / max(1,$funnel_steps['PR'][0]))*100,1) : 0;
        ?>
        <div class="funnel-row">
          <div class="funnel-label"><?= $step ?></div>
          <div class="funnel-bar-wrap">
            <div class="funnel-bar" style="width:<?= $pct ?>%;background:<?= $color ?>;min-width:<?= $cnt>0?'24px':'0' ?>;">
              <?php if ($pct > 20): ?><span><?= $pct ?>%</span><?php endif; ?>
            </div>
          </div>
          <div class="funnel-cnt"><?= number_format($cnt) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="chart-card">
      <div class="chart-head">
        <i class="fas fa-percent" style="color:#d97706;font-size:1rem;"></i>
        <h3>Tax Type Mix</h3>
        <div class="ch-spacer"></div><span>by total deducted</span>
        <button class="maximize-btn" onclick="maximizeChart('taxPieChart','Tax Type Mix')" title="Maximize"><i class="fas fa-expand-alt fa-xs"></i></button>
      </div>
      <div class="chart-body" style="height:240px;"><canvas id="taxPieChart"></canvas></div>
    </div>
  </div>

  <!-- Supplier Leaderboard Table -->
  <div class="chart-card" style="margin-bottom:1.5rem;">
    <div class="chart-head">
      <i class="fas fa-trophy" style="color:#f59e0b;font-size:1rem;"></i>
      <h3>Supplier Leaderboard</h3>
      <div class="ch-spacer"></div><span>ranked by total procurement spend</span>
    </div>
    <!-- Search bar -->
    <div class="supplier-search-bar" style="padding:.625rem 1.25rem;border-bottom:1px solid var(--border);background:#f8fafc;">
      <div class="supplier-search-inner" style="display:flex;align-items:center;gap:.5rem;background:#fff;border:1.5px solid var(--border);border-radius:.5rem;padding:.4rem .75rem;max-width:300px;">
        <i class="fas fa-search" style="color:#94a3b8;font-size:.75rem;flex-shrink:0;"></i>
        <input type="text" id="supplierSearch" placeholder="Search supplier…"
               oninput="filterSuppliers(this.value)"
               class="supplier-search-input"
               style="border:none;outline:none;font-size:.8rem;width:100%;font-family:inherit;">
      </div>
    </div>
    <div style="overflow-x:auto;overflow-y:auto;max-height:336px;" id="leaderTableWrap">
      <table class="an-table" id="leaderTable">
        <thead>
          <tr>
            <th width="50">Rank</th>
            <th>Supplier Name</th>
            <th>PO Count</th>
            <th>Total Spend</th>
            <th>Spend Share</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $grand_spend = array_sum(array_column($top_suppliers,'total_spend')) ?: 1;
          foreach ($top_suppliers as $i => $sup):
            $share = round($sup['total_spend'] / $grand_spend * 100, 1);
            $rank_class = $i === 0 ? 'rb-1' : ($i === 1 ? 'rb-2' : ($i === 2 ? 'rb-3' : ''));
          ?>
          <tr>
            <td>
              <?php if ($rank_class): ?>
                <span class="rank-badge <?= $rank_class ?>"><?= $i+1 ?></span>
              <?php else: ?>
                <span style="font-size:.78rem;color:var(--muted);font-weight:600;"><?= $i+1 ?></span>
              <?php endif; ?>
            </td>
            <td style="font-weight:600;color:var(--text);"><?= htmlspecialchars($sup['supplier']) ?></td>
            <td style="color:var(--muted);"><?= number_format($sup['po_count']) ?></td>
            <td style="font-weight:700;color:#059669;">₱<?= number_format($sup['total_spend'],2) ?></td>
            <td>
              <div style="font-size:.75rem;font-weight:600;color:var(--muted);margin-bottom:.25rem;"><?= $share ?>%</div>
              <div class="spend-bar" style="width:<?= min(100,$share*2) ?>%;"></div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($top_suppliers)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);font-style:italic;padding:2rem;">No data in selected period.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div id="noSupplierResult" style="display:none;padding:1.5rem;text-align:center;color:#94a3b8;font-style:italic;font-size:.8rem;">No matching supplier found.</div>
  </div>

</div><!-- /an-content -->
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#64748b';

// ── Must be declared FIRST — used by registerChart called below ──
const chartRegistry = {};
let overlayChartInstance = null;
function registerChart(id, config) { chartRegistry[id] = config; }

const COLORS = ['#2563eb','#7c3aed','#059669','#d97706','#dc2626','#0891b2','#db2777','#65a30d','#ea580c','#7c3aed'];

// ── Monthly trend (dual axis) ────────────────────────────────────
const monthly_labels  = <?= $monthly_labels ?>;
const monthly_counts  = <?= $monthly_counts ?>;
const monthly_amounts = <?= $monthly_amounts ?>;

const ctx1 = document.getElementById('monthlyChart');
if (ctx1 && monthly_labels.length) {
  const _cfg1 = {
    type: 'bar',
    data: {
      labels: monthly_labels,
      datasets: [
        { type:'bar', label:'PR Count', data:monthly_counts, backgroundColor:'rgba(37,99,235,.2)', borderColor:'#2563eb', borderWidth:1.5, borderRadius:4, yAxisID:'y' },
        { type:'line', label:'Total Amount', data:monthly_amounts, borderColor:'#7c3aed', backgroundColor:'rgba(124,58,237,.08)', fill:true, tension:.4, pointRadius:3, pointBackgroundColor:'#7c3aed', yAxisID:'y2' }
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      interaction:{mode:'index',intersect:false},
      plugins:{legend:{position:'top',labels:{usePointStyle:true,padding:12,font:{size:11}}}},
      scales:{
        y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},
        y2:{beginAtZero:true,position:'right',grid:{display:false},ticks:{callback:v=>'₱'+(v>=1e6?(v/1e6).toFixed(1)+'M':v>=1e3?(v/1e3).toFixed(0)+'K':v)}}
      }
    }
  };
  new Chart(ctx1, _cfg1);
  registerChart('monthlyChart', _cfg1);
} else if (ctx1) {
  ctx1.closest('.chart-body').innerHTML = '<p style="text-align:center;color:#94a3b8;padding:3rem;font-style:italic;">No data in selected period.</p>';
}

// ── Tax pie ──────────────────────────────────────────────────────
const taxLabels = <?= $tax_labels ?>;
const taxValues = <?= $tax_values ?>;
const ctx2 = document.getElementById('taxPieChart');
if (ctx2 && taxValues.length) {
  const _cfg2 = {
    type:'doughnut',
    data:{ labels:taxLabels, datasets:[{data:taxValues, backgroundColor:COLORS, borderWidth:0, hoverOffset:4}] },
    options:{
      responsive:true, maintainAspectRatio:false, cutout:'58%',
      plugins:{legend:{position:'right',labels:{usePointStyle:true,padding:10,font:{size:10}}},
               tooltip:{callbacks:{label:c=>'₱'+c.parsed.toLocaleString('en-PH')}}}
    }
  };
  new Chart(ctx2, _cfg2);
  registerChart('taxPieChart', _cfg2);
}

// ── Supplier horizontal bar ──────────────────────────────────────
const supLabels  = <?= $supplier_labels ?>;
const supAmounts = <?= $supplier_amounts ?>;
const ctx3 = document.getElementById('supplierChart');
if (ctx3 && supLabels.length) {
  const _cfg3 = {
    type:'bar',
    data:{ labels:supLabels.map(l=>l.length>22?l.substring(0,22)+'…':l), datasets:[{label:'Total Spend',data:supAmounts,backgroundColor:COLORS,borderRadius:4,borderSkipped:false}] },
    options:{
      indexAxis:'y', responsive:true, maintainAspectRatio:false,
      plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>'₱'+c.parsed.x.toLocaleString('en-PH')}}},
      scales:{x:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{callback:v=>'₱'+(v>=1e6?(v/1e6).toFixed(1)+'M':v>=1e3?(v/1e3).toFixed(0)+'K':v)}},y:{grid:{display:false}}}
    }
  };
  new Chart(ctx3, _cfg3);
  registerChart('supplierChart', _cfg3);
}

// ── Fund cluster pie ─────────────────────────────────────────────
const fundLabels  = <?= $fund_labels ?>;
const fundAmounts = <?= $fund_amounts ?>;
const ctx4 = document.getElementById('fundChart');
if (ctx4 && fundLabels.length) {
  const _cfg4 = {
    type:'pie',
    data:{ labels:fundLabels, datasets:[{data:fundAmounts, backgroundColor:COLORS, borderWidth:2, borderColor:'#fff', hoverOffset:6}] },
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{legend:{position:'right',labels:{usePointStyle:true,padding:10,font:{size:11}}},
               tooltip:{callbacks:{label:c=>'₱'+c.parsed.toLocaleString('en-PH')}}}
    }
  };
  new Chart(ctx4, _cfg4);
  registerChart('fundChart', _cfg4);
}

// ── Entity bar ───────────────────────────────────────────────────
const entityLabels = <?= $entity_labels ?>;
const entityCounts = <?= $entity_counts ?>;
const ctx5 = document.getElementById('entityChart');
if (ctx5 && entityLabels.length) {
  const _cfg5 = {
    type:'bar',
    data:{
      labels: entityLabels.map(l => l.length > 22 ? l.substring(0,22)+'…' : l),
      datasets:[{label:'PR Count',data:entityCounts,backgroundColor:COLORS,borderRadius:4,borderSkipped:false}]
    },
    options:{
      indexAxis:'y', responsive:true, maintainAspectRatio:false,
      plugins:{
        legend:{display:false},
        tooltip:{
          callbacks:{
            title: (items) => {
              // Show the FULL label (not truncated) in the tooltip title
              return entityLabels[items[0].dataIndex];
            },
            label: (c) => ' ' + c.parsed.x + ' PR' + (c.parsed.x !== 1 ? 's' : '')
          }
        }
      },
      scales:{x:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},y:{grid:{display:false}}}
    }
  };
  new Chart(ctx5, _cfg5);
  registerChart('entityChart', _cfg5);
}

// ── Monthly DV trend (dual axis) ─────────────────────────────────
const monthly_dv_labels  = <?= $monthly_dv_labels ?>;
const monthly_dv_counts  = <?= $monthly_dv_counts ?>;
const monthly_dv_amounts = <?= $monthly_dv_amounts ?>;

const ctx6 = document.getElementById('dvTrendChart');
if (ctx6 && monthly_dv_labels.length) {
  const _cfg6 = {
    type: 'bar',
    data: {
      labels: monthly_dv_labels,
      datasets: [
        { type:'bar',  label:'DV Count',    data:monthly_dv_counts,  backgroundColor:'rgba(220,38,38,.18)', borderColor:'#dc2626', borderWidth:1.5, borderRadius:4, yAxisID:'y' },
        { type:'line', label:'Gross Amount', data:monthly_dv_amounts, borderColor:'#059669', backgroundColor:'rgba(5,150,105,.08)', fill:true, tension:.4, pointRadius:3, pointBackgroundColor:'#059669', yAxisID:'y2' }
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      interaction:{mode:'index',intersect:false},
      plugins:{legend:{position:'top',labels:{usePointStyle:true,padding:12,font:{size:11}}}},
      scales:{
        y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},
        y2:{beginAtZero:true,position:'right',grid:{display:false},ticks:{callback:v=>'₱'+(v>=1e6?(v/1e6).toFixed(1)+'M':v>=1e3?(v/1e3).toFixed(0)+'K':v)}}
      }
    }
  };
  new Chart(ctx6, _cfg6);
  registerChart('dvTrendChart', _cfg6);
} else if (ctx6) {
  ctx6.closest('.chart-body').innerHTML = '<p style="text-align:center;color:#94a3b8;padding:3rem;font-style:italic;">No data in selected period.</p>';
}


// ── Supplier leaderboard search ──────────────────────────────────
function filterSuppliers(q) {
  const rows = document.querySelectorAll('#leaderTable tbody tr');
  const term = q.trim().toLowerCase();
  let visible = 0;
  rows.forEach(row => {
    const name = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
    const show = !term || name.includes(term);
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('noSupplierResult').style.display = visible === 0 ? 'block' : 'none';
}

// ── Supplier leaderboard search ──────────────────────────────────
function maximizeChart(canvasId, title) {
  const overlay = document.getElementById('chartOverlay');
  const canvas  = document.getElementById('overlayCanvas');
  const funnel  = document.getElementById('overlayFunnel');
  if (!overlay || !canvas) return;

  document.getElementById('overlayTitle').textContent = title;
  funnel.style.display = 'none';
  canvas.style.display = 'block';
  if (overlayChartInstance) { overlayChartInstance.destroy(); overlayChartInstance = null; }

  // Build a fresh config per chart using the data already in scope
  // (JSON.stringify strips functions, so we rebuild instead of cloning)
  let freshCfg = null;

  if (canvasId === 'monthlyChart') {
    freshCfg = {
      type: 'bar',
      data: {
        labels: monthly_labels,
        datasets: [
          { type:'bar',  label:'PR Count',     data:monthly_counts,  backgroundColor:'rgba(37,99,235,.2)', borderColor:'#2563eb', borderWidth:1.5, borderRadius:4, yAxisID:'y' },
          { type:'line', label:'Total Amount',  data:monthly_amounts, borderColor:'#7c3aed', backgroundColor:'rgba(124,58,237,.08)', fill:true, tension:.4, pointRadius:4, pointBackgroundColor:'#7c3aed', yAxisID:'y2' }
        ]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        interaction:{mode:'index',intersect:false},
        plugins:{legend:{position:'top',labels:{usePointStyle:true,padding:14,font:{size:13}}}},
        scales:{
          y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1,font:{size:12}}},
          y2:{beginAtZero:true,position:'right',grid:{display:false},ticks:{font:{size:12},callback:v=>'₱'+(v>=1e6?(v/1e6).toFixed(1)+'M':v>=1e3?(v/1e3).toFixed(0)+'K':v)}}
        }
      }
    };
  } else if (canvasId === 'taxPieChart') {
    freshCfg = {
      type:'doughnut',
      data:{ labels:taxLabels, datasets:[{data:taxValues, backgroundColor:COLORS, borderWidth:0, hoverOffset:6}] },
      options:{
        responsive:true, maintainAspectRatio:false, cutout:'55%',
        plugins:{legend:{position:'right',labels:{usePointStyle:true,padding:14,font:{size:13}}},
                 tooltip:{callbacks:{label:c=>'₱'+c.parsed.toLocaleString('en-PH')}}}
      }
    };
  } else if (canvasId === 'supplierChart') {
    freshCfg = {
      type:'bar',
      data:{ labels:supLabels, datasets:[{label:'Total Spend',data:supAmounts,backgroundColor:COLORS,borderRadius:6,borderSkipped:false}] },
      options:{
        indexAxis:'y', responsive:true, maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>'₱'+c.parsed.x.toLocaleString('en-PH')}}},
        scales:{
          x:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{font:{size:12},callback:v=>'₱'+(v>=1e6?(v/1e6).toFixed(1)+'M':v>=1e3?(v/1e3).toFixed(0)+'K':v)}},
          y:{grid:{display:false},ticks:{font:{size:12}}}
        }
      }
    };
  } else if (canvasId === 'fundChart') {
    freshCfg = {
      type:'pie',
      data:{ labels:fundLabels, datasets:[{data:fundAmounts, backgroundColor:COLORS, borderWidth:2, borderColor:'#fff', hoverOffset:8}] },
      options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{position:'right',labels:{usePointStyle:true,padding:14,font:{size:13}}},
                 tooltip:{callbacks:{label:c=>'₱'+c.parsed.toLocaleString('en-PH')}}}
      }
    };
  } else if (canvasId === 'entityChart') {
    freshCfg = {
      type:'bar',
      data:{
        labels: entityLabels,  // full labels in overlay
        datasets:[{label:'PR Count',data:entityCounts,backgroundColor:COLORS,borderRadius:6,borderSkipped:false}]
      },
      options:{
        indexAxis:'y', responsive:true, maintainAspectRatio:false,
        plugins:{
          legend:{display:false},
          tooltip:{callbacks:{
            title: items => entityLabels[items[0].dataIndex],
            label: c => ' ' + c.parsed.x + ' PR' + (c.parsed.x !== 1 ? 's' : '')
          }}
        },
        scales:{x:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1,font:{size:12}}},y:{grid:{display:false},ticks:{font:{size:12}}}}
      }
    };
  } else if (canvasId === 'dvTrendChart') {
    freshCfg = {
      type: 'bar',
      data: {
        labels: monthly_dv_labels,
        datasets: [
          { type:'bar',  label:'DV Count',    data:monthly_dv_counts,  backgroundColor:'rgba(220,38,38,.18)', borderColor:'#dc2626', borderWidth:1.5, borderRadius:4, yAxisID:'y' },
          { type:'line', label:'Gross Amount', data:monthly_dv_amounts, borderColor:'#059669', backgroundColor:'rgba(5,150,105,.08)', fill:true, tension:.4, pointRadius:4, pointBackgroundColor:'#059669', yAxisID:'y2' }
        ]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        interaction:{mode:'index',intersect:false},
        plugins:{legend:{position:'top',labels:{usePointStyle:true,padding:14,font:{size:13}}}},
        scales:{
          y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1,font:{size:12}}},
          y2:{beginAtZero:true,position:'right',grid:{display:false},ticks:{font:{size:12},callback:v=>'₱'+(v>=1e6?(v/1e6).toFixed(1)+'M':v>=1e3?(v/1e3).toFixed(0)+'K':v)}}
        }
      }
    };
  }

  if (!freshCfg) { console.warn('No config built for', canvasId); return; }

  overlayChartInstance = new Chart(canvas.getContext('2d'), freshCfg);
  overlay.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function maximizeFunnel() {
  const overlay = document.getElementById('chartOverlay');
  const canvas  = document.getElementById('overlayCanvas');
  const funnel  = document.getElementById('overlayFunnel');
  if (!overlay) return;
  document.getElementById('overlayTitle').textContent = 'Procurement Funnel';
  if (overlayChartInstance) { overlayChartInstance.destroy(); overlayChartInstance = null; }
  canvas.style.display = 'none';
  const src = document.querySelector('.funnel-wrap');
  funnel.style.display = 'block';
  funnel.innerHTML = src ? src.outerHTML : '<p style="text-align:center;color:#94a3b8;">No funnel data.</p>';
  overlay.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeMaximize() {
  const overlay = document.getElementById('chartOverlay');
  if (overlay) overlay.classList.remove('active');
  document.body.style.overflow = '';
  if (overlayChartInstance) { overlayChartInstance.destroy(); overlayChartInstance = null; }
}

function closeOverlay(e) {
  if (e.target === document.getElementById('chartOverlay')) closeMaximize();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMaximize(); });
</script>


<!-- ── Maximize Overlay ───────────────────────────────────────── -->
<div class="chart-overlay" id="chartOverlay" onclick="closeOverlay(event)">
  <div class="chart-overlay-box">
    <div class="chart-overlay-head">
      <i class="fas fa-chart-bar" id="overlayIcon" style="font-size:.95rem;color:#2563eb;"></i>
      <h3 id="overlayTitle">Chart</h3>
      <button class="overlay-close-btn" onclick="closeMaximize()" title="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="chart-overlay-body">
      <canvas id="overlayCanvas" style="max-height:65vh;"></canvas>
      <div id="overlayFunnel" style="display:none;"></div>
    </div>
  </div>
</div>



<?php include 'inc/footer.php'; ?>
