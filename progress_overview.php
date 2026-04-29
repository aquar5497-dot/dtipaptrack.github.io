<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// --- Logic for Filters ---
$search = $_GET['search'] ?? '';
$month  = $_GET['month']  ?? '';
$status_filter = $_GET['status'] ?? '';

// Build filter conditions - Excluding SUBPR and labeled PRs
$where = "WHERE pr.parent_id IS NULL AND pr.pr_number NOT REGEXP '-[A-Z]$|SUBPR'";

if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (pr.pr_number LIKE '%$s%' OR pr.entity_name LIKE '%$s%')";
}
if (!empty($month)) {
    $where .= " AND MONTH(pr.pr_date) = '$month'";
}

// === PAGINATION SETTINGS ===
$limit  = 8;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// === ANALYTICS CALCULATIONS ===
$stats_query = "
    SELECT
        COUNT(pr.id) as total_records,
        AVG(TIMESTAMPDIFF(SECOND, pr.pr_date, po.po_date)) as avg_pr_po,
        AVG(TIMESTAMPDIFF(SECOND, po.po_date, dv.dv_date)) as avg_po_pay,
        SUM(CASE WHEN rfq.id IS NOT NULL AND po.id IS NOT NULL AND i.id IS NOT NULL AND dv.id IS NOT NULL THEN 1 ELSE 0 END) as fully_complete,
        SUM(CASE WHEN rfq.id IS NULL AND po.id IS NULL AND i.id IS NULL AND dv.id IS NULL THEN 1 ELSE 0 END) as no_docs
    FROM purchase_requests pr
    LEFT JOIN purchase_orders po ON pr.id = po.pr_id
    LEFT JOIN disbursement_vouchers dv ON pr.id = dv.pr_id
    LEFT JOIN rfqs rfq ON pr.id = rfq.pr_id
    LEFT JOIN iars i ON pr.id = i.pr_id
    $where
";
$stats = $conn->query($stats_query)->fetch_assoc();

function formatInterval($seconds) {
    if (!$seconds) return "N/A";
    $days  = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    return "{$days}d {$hours}h";
}

function secToFull(int $s): string {
    if ($s <= 0) return '—';
    $d = floor($s / 86400);
    $h = floor(($s % 86400) / 3600);
    $m = floor(($s % 3600) / 60);
    $parts = [];
    if ($d) $parts[] = "{$d}d";
    if ($h) $parts[] = "{$h}h";
    if ($m && !$d) $parts[] = "{$m}m";
    return implode(' ', $parts) ?: '< 1m';
}

// === MAIN DATA FETCHING ===
$query = "
    SELECT
        pr.id, pr.pr_number, pr.entity_name, pr.pr_date, pr.total_amount, pr.status AS pr_status,
        pr.created_at  AS pr_created_at,
        po.po_date,
        po.po_number,
        po.created_at  AS po_created_at,
        dv.dv_date,
        dv.created_at  AS dv_created_at,
        (SELECT COUNT(*) FROM rfqs rfq WHERE rfq.pr_id = pr.id) AS rfq_count,
        (SELECT COUNT(*) FROM purchase_orders po_c WHERE po_c.pr_id = pr.id) AS po_count,
        (SELECT COUNT(*) FROM iars i INNER JOIN purchase_orders po_i ON i.po_id=po_i.id WHERE po_i.pr_id=pr.id) AS iar_count,
        (SELECT COUNT(*) FROM disbursement_vouchers dv_c WHERE dv_c.pr_id = pr.id) AS dv_count
    FROM purchase_requests pr
    LEFT JOIN purchase_orders po ON pr.id = po.pr_id
    LEFT JOIN disbursement_vouchers dv ON pr.id = dv.pr_id
    $where
    ORDER BY pr.id DESC
    LIMIT $limit OFFSET $offset
";
$progress_rows = $conn->query($query);

$total_rows_query = $conn->query("SELECT COUNT(*) as total FROM purchase_requests pr $where");
$total_rows       = $total_rows_query ? $total_rows_query->fetch_assoc()['total'] : 0;
$total_pages      = ceil($total_rows / $limit);
?>

<style>
:root {
  --po-bg: #f8fafc;
  --po-card: #ffffff;
  --po-border: #e2e8f0;
  --po-text: #0f172a;
  --po-muted: #64748b;
  --po-blue: #2563eb;
  --po-navy: #1e3a5f;
  --po-green: #059669;
  --po-amber: #d97706;
  --po-red: #dc2626;
  --po-purple: #7c3aed;
}

/* ── Page shell ─────────────────────────────── */
.po-page { background: var(--po-bg); min-height: 100vh; padding: 1.5rem 1.75rem 2rem; box-sizing: border-box; }

/* ── Page header ────────────────────────────── */
.po-page-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; }
.po-page-title  { display:flex; align-items:center; gap:.875rem; }
.po-title-icon  { width:42px; height:42px; border-radius:.625rem; background:var(--po-navy); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1rem; flex-shrink:0; box-shadow:0 3px 10px rgba(30,58,95,.3); }
.po-title-text h1 { font-size:1.35rem; font-weight:800; color:var(--po-text); margin:0; letter-spacing:-.01em; }
.po-title-text p  { font-size:.78rem; color:var(--po-muted); margin:.1rem 0 0; }

/* ── Filter bar ─────────────────────────────── */
.po-filter { background:var(--po-card); border:1px solid var(--po-border); border-radius:.875rem; padding:.875rem 1.25rem; display:flex; flex-wrap:wrap; gap:.625rem; align-items:flex-end; margin-bottom:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,.04); }
.po-filter label { display:block; font-size:.65rem; font-weight:700; color:var(--po-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.25rem; }
.po-filter input, .po-filter select { border:1.5px solid var(--po-border); border-radius:.45rem; padding:.42rem .75rem; font-size:.8rem; outline:none; background:#fff; font-family:inherit; transition:.15s; }
.po-filter input:focus, .po-filter select:focus { border-color:var(--po-blue); box-shadow:0 0 0 3px rgba(37,99,235,.08); }
.po-btn-search { background:var(--po-navy); color:#fff; border:none; border-radius:.45rem; padding:.45rem 1.1rem; font-size:.8rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; transition:.15s; font-family:inherit; }
.po-btn-search:hover { background:var(--po-blue); }
.po-btn-reset { font-size:.78rem; color:var(--po-muted); text-decoration:none; padding:.4rem .6rem; border-radius:.375rem; transition:.15s; }
.po-btn-reset:hover { color:var(--po-red); background:#fef2f2; }

/* ── KPI strip ──────────────────────────────── */
.po-kpi-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:.875rem; margin-bottom:1.25rem; }
@media(max-width:900px) { .po-kpi-strip { grid-template-columns:repeat(2,1fr); } }
.po-kpi { background:var(--po-card); border:1px solid var(--po-border); border-radius:.875rem; padding:1.1rem 1.25rem; display:flex; align-items:center; gap:.875rem; box-shadow:0 1px 3px rgba(0,0,0,.04); transition:transform .15s, box-shadow .15s; }
.po-kpi:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,.08); }
.po-kpi-icon { width:42px; height:42px; border-radius:.6rem; display:flex; align-items:center; justify-content:center; font-size:.95rem; flex-shrink:0; }
.po-kpi-body {}
.po-kpi-val { font-size:1.4rem; font-weight:800; color:var(--po-text); line-height:1; }
.po-kpi-lbl { font-size:.7rem; color:var(--po-muted); font-weight:600; margin-top:.2rem; text-transform:uppercase; letter-spacing:.04em; }

/* ── Main card ──────────────────────────────── */
.po-card { background:var(--po-card); border:1px solid var(--po-border); border-radius:1rem; box-shadow:0 1px 4px rgba(0,0,0,.05); overflow:hidden; }

/* ── Table ──────────────────────────────────── */
.po-table-wrap { overflow-x:auto; }
.po-table { width:100%; border-collapse:collapse; min-width:900px; }
.po-table thead th { background:#f8fafc; padding:.875rem 1.25rem; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--po-muted); border-bottom:2px solid var(--po-border); white-space:nowrap; }
.po-table thead th:first-child { border-radius:0; }
.po-table tbody tr { transition:background .12s; border-bottom:1px solid #f1f5f9; }
.po-table tbody tr:last-child { border-bottom:none; }
.po-table tbody tr:hover { background:#f8fbff; }
.po-table td { padding:.9rem 1.25rem; vertical-align:middle; }

/* ── PR Identity cell ────────────────────────── */
.pr-num { font-size:.85rem; font-weight:800; color:var(--po-blue); }
.pr-entity { font-size:.75rem; color:var(--po-muted); margin-top:.15rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px; }
.pr-amount { font-size:.78rem; font-weight:700; color:var(--po-green); margin-top:.25rem; }
.pr-date-badge { display:inline-block; font-size:.65rem; background:#eff6ff; color:var(--po-blue); border:1px solid #bfdbfe; border-radius:9999px; padding:.1rem .5rem; margin-top:.3rem; font-weight:600; }

/* ── Timeline bar cell ───────────────────────── */
.tl-bar-wrap { display:flex; align-items:center; gap:.5rem; margin-bottom:.5rem; }
.tl-bar { flex:1; height:8px; background:#f1f5f9; border-radius:9999px; overflow:hidden; display:flex; }
.tl-seg-pr  { background:#6366f1; height:100%; }
.tl-seg-po  { background:#059669; height:100%; border-left:1px solid #fff; }
.tl-total-badge { font-size:.72rem; font-weight:800; color:var(--po-text); white-space:nowrap; }
.tl-details { display:grid; grid-template-columns:1fr 1fr 1fr; gap:.25rem; }
.tl-seg-label { font-size:.68rem; color:var(--po-muted); font-weight:600; text-transform:uppercase; }
.tl-seg-val { font-size:.75rem; font-weight:700; color:var(--po-text); }

/* ── Document Progress ───────────────────────── */
.doc-progress-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:.45rem; }
.doc-step { display:flex; align-items:center; gap:.5rem; border-radius:.5rem; padding:.45rem .6rem; border:1px solid; transition:.15s; }
.doc-step.done     { background:#f0fdf4; border-color:#bbf7d0; }
.doc-step.process  { background:#fffbeb; border-color:#fde68a; }
.doc-step-icon { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.65rem; flex-shrink:0; }
.doc-step.done    .doc-step-icon { background:#059669; color:#fff; }
.doc-step.process .doc-step-icon { background:#6366f1; color:#fff; }
.doc-step-label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.doc-step.done    .doc-step-label { color:#065f46; }
.doc-step.process .doc-step-label { color:#3730a3; }
.doc-step-status { font-size:.6rem; font-weight:600; }
.doc-step.done    .doc-step-status { color:#059669; }
.doc-step.process .doc-step-status { color:#d97706; }

/* ── Efficiency badge ────────────────────────── */
.eff-badge { display:inline-flex; align-items:center; gap:.3rem; padding:.35rem .75rem; border-radius:9999px; font-size:.7rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; }
.eff-finished    { background:#dcfce7; color:#15803d; }
.eff-process     { background:#ede9fe; color:#4338ca; }
.eff-ongoing     { background:#dbeafe; color:#1d4ed8; }
.eff-bottleneck  { background:#ffedd5; color:#c2410c; }
.eff-review      { background:#fee2e2; color:#b91c1c; animation:pulseBadge 1.5s infinite; }
@keyframes pulseBadge { 0%,100%{opacity:1} 50%{opacity:.65} }

/* ── Pagination ──────────────────────────────── */
.po-pagination { display:flex; align-items:center; justify-content:space-between; padding:.875rem 1.25rem; background:#f8fafc; border-top:1px solid var(--po-border); flex-wrap:wrap; gap:.5rem; }
.po-page-info { font-size:.78rem; color:var(--po-muted); }
.po-page-info strong { color:var(--po-text); }
.po-page-btns { display:flex; align-items:center; gap:.3rem; }
.po-page-btn { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 .6rem; border:1.5px solid var(--po-border); border-radius:.45rem; font-size:.78rem; font-weight:600; color:var(--po-muted); background:#fff; text-decoration:none; transition:.15s; }
.po-page-btn:hover:not(.disabled):not(.active) { border-color:var(--po-blue); color:var(--po-blue); background:#eff6ff; }
.po-page-btn.active  { background:var(--po-navy); border-color:var(--po-navy); color:#fff; }
.po-page-btn.disabled { background:#f8fafc; color:#cbd5e1; pointer-events:none; }
</style>

<main class="flex-1 overflow-y-auto" style="background:var(--po-bg, #f8fafc);">
<div class="po-page">

  <!-- Page Header -->
  <div class="po-page-header">
    <div class="po-page-title">
      <div class="po-title-icon"><i class="fas fa-tasks"></i></div>
      <div class="po-title-text">
        <h1>Procurement Progress Overview</h1>
        <p>Real-time cycle time analysis &amp; document progress tracking per Purchase Request</p>
      </div>
    </div>
    <a href="index.php" style="display:inline-flex;align-items:center;gap:.4rem;background:var(--po-navy);color:#fff;font-weight:600;font-size:.8rem;padding:.5rem 1rem;border-radius:.5rem;text-decoration:none;transition:.15s;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='var(--po-navy)'">
      <i class="fas fa-home fa-xs"></i> Dashboard
    </a>
  </div>

  <!-- Filter Bar -->
  <form method="get" class="po-filter">
    <div>
      <label>Search</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="PR number or entity…" style="width:200px;">
    </div>
    <div>
      <label>Month</label>
      <select name="month">
        <option value="">All Months</option>
        <?php for($m=1;$m<=12;$m++) echo "<option value='$m' ".($month==$m?'selected':'').">".date("F", mktime(0,0,0,$m,1))."</option>"; ?>
      </select>
    </div>
    <div style="display:flex;align-items:flex-end;gap:.4rem;">
      <button type="submit" class="po-btn-search"><i class="fas fa-search fa-xs"></i> Search</button>
      <?php if(!empty($search)||!empty($month)): ?>
        <a href="progress_overview.php" class="po-btn-reset"><i class="fas fa-times fa-xs"></i> Reset</a>
      <?php endif; ?>
    </div>
    <div style="margin-left:auto;display:flex;align-items:flex-end;">
      <span style="font-size:.75rem;color:var(--po-muted);"><i class="fas fa-list-ol fa-xs" style="margin-right:.25rem;"></i><?= number_format($total_rows) ?> record<?= $total_rows!==1?'s':'' ?> found</span>
    </div>
  </form>

  <!-- KPI Strip -->
  <div class="po-kpi-strip">
    <div class="po-kpi">
      <div class="po-kpi-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-file-alt"></i></div>
      <div class="po-kpi-body">
        <div class="po-kpi-val"><?= number_format($total_rows) ?></div>
        <div class="po-kpi-lbl">Total PR Records</div>
      </div>
    </div>
    <div class="po-kpi">
      <div class="po-kpi-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-hourglass-half"></i></div>
      <div class="po-kpi-body">
        <div class="po-kpi-val" style="font-size:1.1rem;"><?= formatInterval($stats['avg_pr_po']) ?></div>
        <div class="po-kpi-lbl">Avg. PR → PO</div>
      </div>
    </div>
    <div class="po-kpi">
      <div class="po-kpi-icon" style="background:#d1fae5;color:#059669;"><i class="fas fa-money-check-alt"></i></div>
      <div class="po-kpi-body">
        <div class="po-kpi-val" style="font-size:1.1rem;"><?= formatInterval($stats['avg_po_pay']) ?></div>
        <div class="po-kpi-lbl">Avg. PO → Payment</div>
      </div>
    </div>
    <div class="po-kpi">
      <div class="po-kpi-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-stopwatch"></i></div>
      <div class="po-kpi-body">
        <div class="po-kpi-val" style="font-size:1.1rem;"><?= formatInterval(($stats['avg_pr_po'] ?? 0) + ($stats['avg_po_pay'] ?? 0)) ?></div>
        <div class="po-kpi-lbl">Avg. Total Cycle</div>
      </div>
    </div>
  </div>

  <!-- Legend strip -->
  <div style="display:flex;align-items:center;gap:1.25rem;margin-bottom:.875rem;flex-wrap:wrap;">
    <span style="font-size:.7rem;font-weight:700;color:var(--po-muted);text-transform:uppercase;letter-spacing:.05em;">Document Progress Legend:</span>
    <span style="display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;color:#065f46;font-weight:600;"><span style="width:16px;height:16px;border-radius:50%;background:#059669;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.55rem;"><i class="fas fa-check"></i></span> Done</span>
    <span style="display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;color:#3730a3;font-weight:600;"><span style="width:16px;height:16px;border-radius:50%;background:#6366f1;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.55rem;"><i class="fas fa-spinner"></i></span> On Process</span>
    <span style="display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;color:#6366f1;font-weight:600;">■ Timeline: <span style="color:#6366f1;">■</span> PR→PO &nbsp; <span style="color:#059669;">■</span> PO→Pay</span>
  </div>

  <!-- Main Table Card -->
  <div class="po-card">
    <div class="po-table-wrap">
      <table class="po-table">
        <thead>
          <tr>
            <th style="width:200px;">PR Identification</th>
            <th style="width:220px;">Timeline Analysis</th>
            <th style="min-width:260px;text-align:center;">Document Progress</th>
            <th style="width:130px;text-align:center;">Efficiency Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $row_count = 0;
          while($r = $progress_rows->fetch_assoc()):
            $row_count++;
            $pr_ts  = strtotime($r['pr_created_at']);
            $po_ts  = !empty($r['po_created_at']) ? strtotime($r['po_created_at']) : null;
            $dv_ts  = !empty($r['dv_created_at']) ? strtotime($r['dv_created_at']) : null;

            $pr_to_po_sec  = ($po_ts && $po_ts > $pr_ts) ? ($po_ts - $pr_ts) : 0;
            $po_to_pay_sec = ($po_ts && $dv_ts && $dv_ts > $po_ts) ? ($dv_ts - $po_ts) : 0;
            $total_sec     = $pr_to_po_sec + $po_to_pay_sec;
            $total_days    = $total_sec > 0 ? max(1, (int)ceil($total_sec / 86400)) : 0;

            $pr_to_po_days  = $pr_to_po_sec  / 86400;
            $po_to_pay_days = $po_to_pay_sec / 86400;

            $has_duplicate   = ($r['rfq_count'] > 1 || $r['po_count'] > 1 || $r['iar_count'] > 1 || $r['dv_count'] > 1);
            $is_bottleneck   = ($pr_to_po_days > 14 || $po_to_pay_days > 14);
            $is_ongoing      = ($r['rfq_count'] == 0 && $r['po_count'] == 0 && $r['iar_count'] == 0 && $r['dv_count'] == 0);
            $is_under_process= ($r['rfq_count'] == 0 || $r['po_count'] == 0 || $r['iar_count'] == 0 || $r['dv_count'] == 0);
            $is_complete     = ($r['rfq_count'] >= 1 && $r['po_count'] >= 1 && $r['iar_count'] >= 1 && $r['dv_count'] >= 1);

            // Timeline bar widths
            $tl_total = $pr_to_po_sec + $po_to_pay_sec;
            $w_pr = $tl_total > 0 ? round($pr_to_po_sec / $tl_total * 100) : 0;
            $w_po = $tl_total > 0 ? round($po_to_pay_sec / $tl_total * 100) : 0;
            if ($pr_to_po_sec > 0 && $w_pr < 5) $w_pr = 5;
            if ($po_to_pay_sec > 0 && $w_po < 5) $w_po = 5;

            // Document steps
            $doc_steps = [
              'RFQ' => ['count' => $r['rfq_count'], 'icon' => 'fa-quote-right',      'color_done'=>'#059669','color_proc'=>'#6366f1'],
              'PO'  => ['count' => $r['po_count'],  'icon' => 'fa-file-signature',   'color_done'=>'#059669','color_proc'=>'#6366f1'],
              'IAR' => ['count' => $r['iar_count'], 'icon' => 'fa-clipboard-check',  'color_done'=>'#059669','color_proc'=>'#6366f1'],
              'DV'  => ['count' => $r['dv_count'],  'icon' => 'fa-money-check-alt',  'color_done'=>'#059669','color_proc'=>'#6366f1'],
            ];
          ?>
          <tr>
            <!-- PR Identity -->
            <td>
              <div class="pr-num"><?= htmlspecialchars($r['pr_number']) ?></div>
              <div class="pr-entity" title="<?= htmlspecialchars($r['entity_name']) ?>"><?= htmlspecialchars($r['entity_name']) ?></div>
              <div class="pr-amount">₱<?= number_format($r['total_amount'], 2) ?></div>
              <div class="pr-date-badge"><i class="fas fa-calendar-alt fa-xs" style="margin-right:.25rem;"></i><?= date('M d, Y', strtotime($r['pr_date'])) ?></div>
            </td>

            <!-- Timeline Analysis -->
            <td>
              <div class="tl-bar-wrap">
                <div class="tl-bar">
                  <?php if($pr_to_po_sec > 0): ?><div class="tl-seg-pr" style="width:<?= $w_pr ?>%;"></div><?php endif; ?>
                  <?php if($po_to_pay_sec > 0): ?><div class="tl-seg-po" style="width:<?= $w_po ?>%;"></div><?php endif; ?>
                  <?php if($tl_total == 0): ?><div style="width:100%;background:#f1f5f9;"></div><?php endif; ?>
                </div>
                <span class="tl-total-badge">
                  <?php if($total_days >= 1): ?>
                    <?= $total_days ?>d
                  <?php elseif($total_sec > 0): ?>
                    <?= secToFull($total_sec) ?>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </span>
              </div>
              <div class="tl-details">
                <div>
                  <div class="tl-seg-label" style="color:#6366f1;">PR → PO</div>
                  <div class="tl-seg-val"><?= secToFull($pr_to_po_sec) ?></div>
                </div>
                <div>
                  <div class="tl-seg-label" style="color:#059669;">PO → Pay</div>
                  <div class="tl-seg-val"><?= secToFull($po_to_pay_sec) ?></div>
                </div>
                <div>
                  <div class="tl-seg-label">Total</div>
                  <div class="tl-seg-val"><?= secToFull($total_sec) ?></div>
                </div>
              </div>
            </td>

            <!-- Document Progress -->
            <td>
              <div class="doc-progress-grid">
                <?php foreach($doc_steps as $label => $step):
                  $done  = $step['count'] >= 1;
                  $cls   = $done ? 'done' : 'process';
                  $icon  = $done ? 'fa-check' : 'fa-spinner fa-spin';
                  $stxt  = $done ? 'Done' : 'On Process';
                ?>
                <div class="doc-step <?= $cls ?>">
                  <div class="doc-step-icon"><i class="fas <?= $icon ?>" style="font-size:.6rem;"></i></div>
                  <div>
                    <div class="doc-step-label"><?= $label ?></div>
                    <div class="doc-step-status"><?= $stxt ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </td>

            <!-- Efficiency Status -->
            <td style="text-align:center;">
              <?php if($has_duplicate): ?>
                <span class="eff-badge eff-review"><i class="fas fa-exclamation-triangle fa-xs"></i> Under Review</span>
              <?php elseif($is_ongoing): ?>
                <span class="eff-badge eff-ongoing"><i class="fas fa-circle-notch fa-xs fa-spin"></i> Ongoing</span>
              <?php elseif($is_complete && !$is_bottleneck): ?>
                <span class="eff-badge eff-finished"><i class="fas fa-check-circle fa-xs"></i> Finished</span>
              <?php elseif($is_bottleneck): ?>
                <span class="eff-badge eff-bottleneck"><i class="fas fa-exclamation fa-xs"></i> Bottleneck</span>
              <?php else: ?>
                <span class="eff-badge eff-process"><i class="fas fa-hourglass-half fa-xs"></i> In Process</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>

          <?php if($row_count === 0): ?>
          <tr>
            <td colspan="4" style="text-align:center;padding:3rem;color:var(--po-muted);">
              <i class="fas fa-search" style="font-size:1.5rem;margin-bottom:.75rem;display:block;opacity:.4;"></i>
              No records found matching your filter.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="po-pagination">
      <div class="po-page-info">
        Showing <strong><?= min($offset+1, $total_rows) ?>–<?= min($offset+$limit, $total_rows) ?></strong> of <strong><?= number_format($total_rows) ?></strong> records &nbsp;·&nbsp; Page <strong><?= $page ?></strong> of <strong><?= max(1,$total_pages) ?></strong>
      </div>
      <div class="po-page-btns">
        <a href="?page=1&search=<?= urlencode($search) ?>&month=<?= $month ?>" class="po-page-btn <?= $page<=1?'disabled':'' ?>" title="First"><i class="fas fa-angle-double-left fa-xs"></i></a>
        <a href="?page=<?= max(1,$page-1) ?>&search=<?= urlencode($search) ?>&month=<?= $month ?>" class="po-page-btn <?= $page<=1?'disabled':'' ?>"><i class="fas fa-angle-left fa-xs"></i> Prev</a>
        <?php
        $num_visible = 5; $start = max(1,$page-2); $end = min($total_pages,$start+$num_visible-1);
        if($end-$start < $num_visible-1) $start = max(1,$end-$num_visible+1);
        for($i=$start;$i<=$end;$i++):
        ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&month=<?= $month ?>" class="po-page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="?page=<?= min($total_pages,$page+1) ?>&search=<?= urlencode($search) ?>&month=<?= $month ?>" class="po-page-btn <?= $page>=$total_pages?'disabled':'' ?>">Next <i class="fas fa-angle-right fa-xs"></i></a>
        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&month=<?= $month ?>" class="po-page-btn <?= $page>=$total_pages?'disabled':'' ?>" title="Last"><i class="fas fa-angle-double-right fa-xs"></i></a>
      </div>
    </div>
  </div><!-- /po-card -->

</div><!-- /po-page -->
</main>

<?php include 'inc/footer.php'; ?>
