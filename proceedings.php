<?php
require_once 'inc/permissions.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';
include 'session_check.php';

// ═══════════════════════════════════════════════════════════
//  FILTERS
// ═══════════════════════════════════════════════════════════
$search        = trim($_GET['search'] ?? '');
$month_filter  = $_GET['month']  ?? '';
$status_filter = $_GET['status'] ?? '';

// ═══════════════════════════════════════════════════════════
//  PAGINATION
// ═══════════════════════════════════════════════════════════
$limit = 2;
$page  = max(1, (int)($_GET['page'] ?? 1));

// ═══════════════════════════════════════════════════════════
//  WHERE CLAUSE
// ═══════════════════════════════════════════════════════════
$parts = [
    "pr.parent_id IS NULL",
    "(pr.is_cancelled = 0 OR pr.is_cancelled IS NULL)"
];
if ($search !== '') {
    $s       = $conn->real_escape_string($search);
    $parts[] = "(pr.pr_number LIKE '%$s%' OR pr.purpose LIKE '%$s%' OR pr.entity_name LIKE '%$s%')";
}
if ($month_filter !== '') {
    $m       = $conn->real_escape_string($month_filter);
    $parts[] = "DATE_FORMAT(pr.pr_date,'%Y-%m')='$m'";
}
if ($status_filter !== '') {
    $sf      = $conn->real_escape_string($status_filter);
    $parts[] = "pr.status='$sf'";
}
$where = "WHERE " . implode(" AND ", $parts);

// ═══════════════════════════════════════════════════════════
//  COUNT & PAGINATION MATH
// ═══════════════════════════════════════════════════════════
$total_rows  = (int)$conn->query("SELECT COUNT(*) AS c FROM purchase_requests pr $where")->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total_rows / $limit));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $limit;

// ═══════════════════════════════════════════════════════════
//  OVERVIEW STATS
// ═══════════════════════════════════════════════════════════
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(pr.status='Approved') AS approved,
        SUM(pr.status='Pending')  AS pending
    FROM purchase_requests pr $where
")->fetch_assoc();

$rfq_cnt = (int)$conn->query("
    SELECT COUNT(DISTINCT r.pr_id) AS c
    FROM rfqs r INNER JOIN purchase_requests pr ON r.pr_id=pr.id $where
")->fetch_assoc()['c'];

$po_cnt = (int)$conn->query("
    SELECT COUNT(DISTINCT po.pr_id) AS c
    FROM purchase_orders po INNER JOIN purchase_requests pr ON po.pr_id=pr.id $where
")->fetch_assoc()['c'];

$iar_cnt = (int)$conn->query("
    SELECT COUNT(DISTINCT po.pr_id) AS c
    FROM iars i
    INNER JOIN purchase_orders po ON i.po_id=po.id
    INNER JOIN purchase_requests pr ON po.pr_id=pr.id $where
")->fetch_assoc()['c'];

$dv_cnt = (int)$conn->query("
    SELECT COUNT(DISTINCT dv.pr_id) AS c
    FROM disbursement_vouchers dv INNER JOIN purchase_requests pr ON dv.pr_id=pr.id $where
")->fetch_assoc()['c'];

// ═══════════════════════════════════════════════════════════
//  FETCH PRs WITH LINKED DOCUMENTS
// ═══════════════════════════════════════════════════════════
$res = $conn->query("
    SELECT pr.id, pr.pr_number, pr.pr_date, pr.entity_name,
           pr.purpose, pr.total_amount, pr.status, pr.fund_cluster
    FROM purchase_requests pr
    $where
    ORDER BY pr.id DESC
    LIMIT $limit OFFSET $offset
");

$prs = [];
while ($r = $res->fetch_assoc()) {
    $pid = (int)$r['id'];

    $rfq = $conn->query("
        SELECT id, rfq_number, rfq_date FROM rfqs
        WHERE pr_id=$pid ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();

    $po = $conn->query("
        SELECT id, po_number, po_date, supplier FROM purchase_orders
        WHERE pr_id=$pid ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();

    $iar = null;
    if ($po) {
        $poid = (int)$po['id'];
        $iar  = $conn->query("
            SELECT id, iar_number, iar_date FROM iars
            WHERE po_id=$poid ORDER BY id DESC LIMIT 1
        ")->fetch_assoc();
    }

    $dv = $conn->query("
        SELECT id, dv_number, dv_date FROM disbursement_vouchers
        WHERE pr_id=$pid ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();

    $r['rfq'] = $rfq;
    $r['po']  = $po;
    $r['iar'] = $iar;
    $r['dv']  = $dv;
    $prs[]    = $r;
}

// ═══════════════════════════════════════════════════════════
//  PAGINATION QUERY STRING
// ═══════════════════════════════════════════════════════════
$pag_q = http_build_query(array_filter([
    'search' => $search,
    'month'  => $month_filter,
    'status' => $status_filter,
]));
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  /* Step connector animations */
  .step-connector-done   { background: linear-gradient(90deg, #22c55e, #16a34a); }
  .step-connector-active { background: linear-gradient(90deg, #22c55e, #3b82f6); background-size: 200% 100%; }
  .step-connector-locked { background: #e5e7eb; }

  /* Pulse for available step */
  .step-available .step-circle {
    animation: step-pulse 2s infinite;
  }
  @keyframes step-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.4); }
    50%       { box-shadow: 0 0 0 6px rgba(59,130,246,0); }
  }

  /* Card hover */
  .proc-card { transition: box-shadow 0.2s, transform 0.15s; }
  .proc-card:hover { box-shadow: 0 8px 32px rgba(30,58,138,0.13); transform: translateY(-1px); }

  /* Tooltip */
  .tooltip-wrap { position: relative; }
  .tooltip-box {
    display: none; position: absolute; bottom: calc(100% + 6px); left: 50%;
    transform: translateX(-50%); white-space: nowrap;
    background: #1e3a8a; color: #fff; font-size: 11px;
    padding: 4px 10px; border-radius: 6px; z-index: 99;
    pointer-events: none;
  }
  .tooltip-wrap:hover .tooltip-box { display: block; }
</style>

<main class="flex-1 p-4 lg:p-6 overflow-y-auto bg-blue-100" style="min-height:calc(100vh - 70px);">
<div class="max-w-7xl mx-auto">

  <!-- ══ PAGE HEADER ══════════════════════════════════════ -->
  <div class="flex items-center justify-between mb-5">
    <div class="flex items-center gap-3">
      <div class="bg-blue-950 text-white w-9 h-9 rounded-lg flex items-center justify-center shadow">
        <i class="fas fa-list-ol text-sm"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-gray-800 leading-tight">Proceedings</h1>
        <p class="text-xs text-gray-500">Track procurement progress from PR to Disbursement</p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <?php if(hasPermission(PERM_PURCHASE_REQUEST)): ?>
      <a href="pr_add.php"
         class="inline-flex items-center gap-2 bg-blue-700 hover:bg-blue-800 text-white font-semibold text-sm px-4 py-2 rounded-lg shadow-md transition-all hover:shadow-lg hover:-translate-y-0.5">
        <i class="fas fa-plus text-xs"></i> Add PR
      </a>
      <?php endif; ?>
      <div id="dateTimeDisplay" class="bg-white text-gray-700 px-3 py-1 rounded-lg shadow text-xs font-medium">
        <span id="currentDate">--</span> | <span id="currentTime">--</span>
      </div>
    </div>
  </div>

  <!-- ══ STEP LEGEND BANNER ═══════════════════════════════ -->
  <div class="bg-white rounded-xl shadow mb-5 px-5 py-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-600">
    <span class="font-semibold text-gray-700 mr-1">Step Legend:</span>
    <span class="flex items-center gap-1.5">
      <span class="w-5 h-5 rounded-full bg-green-500 flex items-center justify-center text-white"><i class="fas fa-check text-[9px]"></i></span>
      <span class="text-green-700 font-medium">Completed</span>
    </span>
    <span class="flex items-center gap-1.5">
      <span class="w-5 h-5 rounded-full bg-blue-600 flex items-center justify-center text-white"><i class="fas fa-plus text-[9px]"></i></span>
      <span class="text-blue-700 font-medium">Available — Click to Add</span>
    </span>
    <span class="flex items-center gap-1.5">
      <span class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-gray-500"><i class="fas fa-lock text-[9px]"></i></span>
      <span class="text-gray-500 font-medium">Locked — Complete previous step first</span>
    </span>
    <span class="flex items-center gap-1.5 ml-auto">
      <i class="fas fa-info-circle text-blue-400"></i>
      <span>Steps must be completed in order: <strong>PR → RFQ → PO → IAR → DV</strong></span>
    </span>
  </div>

  <!-- ══ STATS OVERVIEW CARDS ═════════════════════════════ -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">

    <!-- Total PRs -->
    <div class="bg-white rounded-xl p-3 shadow flex flex-col items-center text-center">
      <div class="w-9 h-9 bg-blue-100 rounded-full flex items-center justify-center mb-1">
        <i class="fas fa-file-alt text-blue-600 text-sm"></i>
      </div>
      <div class="text-2xl font-extrabold text-gray-800"><?= number_format($stats['total']) ?></div>
      <div class="text-xs text-gray-500 font-medium">Total PRs</div>
    </div>

    <!-- With RFQ -->
    <div class="bg-white rounded-xl p-3 shadow flex flex-col items-center text-center">
      <div class="w-9 h-9 bg-teal-100 rounded-full flex items-center justify-center mb-1">
        <i class="fas fa-quote-right text-teal-600 text-sm"></i>
      </div>
      <div class="text-2xl font-extrabold text-gray-800"><?= number_format($rfq_cnt) ?></div>
      <div class="text-xs text-gray-500 font-medium">With RFQ</div>
    </div>

    <!-- With PO -->
    <div class="bg-white rounded-xl p-3 shadow flex flex-col items-center text-center">
      <div class="w-9 h-9 bg-indigo-100 rounded-full flex items-center justify-center mb-1">
        <i class="fas fa-file-signature text-indigo-600 text-sm"></i>
      </div>
      <div class="text-2xl font-extrabold text-gray-800"><?= number_format($po_cnt) ?></div>
      <div class="text-xs text-gray-500 font-medium">With PO</div>
    </div>

    <!-- With IAR -->
    <div class="bg-white rounded-xl p-3 shadow flex flex-col items-center text-center">
      <div class="w-9 h-9 bg-yellow-100 rounded-full flex items-center justify-center mb-1">
        <i class="fas fa-clipboard-check text-yellow-600 text-sm"></i>
      </div>
      <div class="text-2xl font-extrabold text-gray-800"><?= number_format($iar_cnt) ?></div>
      <div class="text-xs text-gray-500 font-medium">With IAR</div>
    </div>

    <!-- With DV -->
    <div class="bg-white rounded-xl p-3 shadow flex flex-col items-center text-center">
      <div class="w-9 h-9 bg-purple-100 rounded-full flex items-center justify-center mb-1">
        <i class="fas fa-money-check-alt text-purple-600 text-sm"></i>
      </div>
      <div class="text-2xl font-extrabold text-gray-800"><?= number_format($dv_cnt) ?></div>
      <div class="text-xs text-gray-500 font-medium">With DV</div>
    </div>

    <!-- Fully Completed (all 4 steps done) -->
    <?php
    $fully_done = (int)$conn->query("
        SELECT COUNT(DISTINCT pr.id) AS c
        FROM purchase_requests pr
        INNER JOIN rfqs r          ON r.pr_id  = pr.id
        INNER JOIN purchase_orders po ON po.pr_id = pr.id
        INNER JOIN iars i          ON i.po_id  = po.id
        INNER JOIN disbursement_vouchers dv ON dv.pr_id = pr.id
        $where
    ")->fetch_assoc()['c'];
    ?>
    <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-3 shadow flex flex-col items-center text-center text-white">
      <div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center mb-1">
        <i class="fas fa-check-double text-white text-sm"></i>
      </div>
      <div class="text-2xl font-extrabold"><?= number_format($fully_done) ?></div>
      <div class="text-xs font-medium opacity-90">Fully Done</div>
    </div>
  </div>

  <!-- ══ FILTER BAR ════════════════════════════════════════ -->
  <div class="bg-white rounded-xl shadow px-4 py-3 mb-5">
    <form method="GET" class="flex flex-wrap items-end gap-3">
      <div class="flex flex-col gap-1 flex-1 min-w-[180px]">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Search</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"><i class="fas fa-search"></i></span>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                 placeholder="PR No., Purpose, Entity…"
                 class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Month</label>
        <input type="month" name="month" value="<?= htmlspecialchars($month_filter) ?>"
               class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</label>
        <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
          <option value="">All Status</option>
          <option value="Approved" <?= $status_filter==='Approved'?'selected':'' ?>>Approved</option>
          <option value="Pending"  <?= $status_filter==='Pending' ?'selected':'' ?>>Pending</option>
        </select>
      </div>

      <div class="flex items-end gap-2">
        <button type="submit"
                class="bg-blue-700 hover:bg-blue-800 text-white px-5 py-2 rounded-lg text-sm font-semibold shadow transition flex items-center gap-2">
          <i class="fas fa-filter text-xs"></i> Filter
        </button>
        <?php if ($search || $month_filter || $status_filter): ?>
          <a href="proceedings.php"
             class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1">
            <i class="fas fa-times text-xs"></i> Reset
          </a>
        <?php endif; ?>
      </div>

      <div class="ml-auto flex items-end">
        <span class="text-sm text-gray-500">
          Showing <strong><?= number_format(min($offset + $limit, $total_rows)) ?></strong>
          of <strong><?= number_format($total_rows) ?></strong> records
        </span>
      </div>
    </form>
  </div>

  <!-- ══ PR PROCEEDING CARDS ═══════════════════════════════ -->
  <?php if (empty($prs)): ?>
    <div class="bg-white rounded-xl shadow p-12 text-center text-gray-400">
      <i class="fas fa-folder-open text-5xl mb-4 block"></i>
      <p class="text-lg font-semibold">No Purchase Requests Found</p>
      <p class="text-sm mt-1">Try adjusting your filters or <a href="pr_add.php" class="text-blue-600 hover:underline font-medium">add a new PR</a>.</p>
    </div>

  <?php else: ?>
  <div class="space-y-4">
    <?php foreach ($prs as $pr):
      $pid        = (int)$pr['id'];
      $rfq        = $pr['rfq'];
      $po         = $pr['po'];
      $iar        = $pr['iar'];
      $dv         = $pr['dv'];

      // ── Determine step states ──────────────────
      // PR: always done (we're listing existing PRs)
      $pr_done  = true;
      $rfq_done = !empty($rfq);
      $po_done  = !empty($po);
      $iar_done = !empty($iar);
      $dv_done  = !empty($dv);

      // Available logic (strict sequential)
      $rfq_avail = !$rfq_done; // RFQ available if PR exists and no RFQ yet
      $po_avail  = $rfq_done && !$po_done;
      $iar_avail = $po_done  && !$iar_done;
      $dv_avail  = $iar_done && !$dv_done;

      // Locked logic
      $rfq_locked = false; // RFQ can always be added after PR
      $po_locked  = !$rfq_done;
      $iar_locked = !$po_done;
      $dv_locked  = !$iar_done;

      // ── Connector states (between steps) ──────
      // Connector is green if BOTH connected steps are done
      // Connector is blue-dashed if left=done, right=available
      // Connector is gray if right=locked
      $conn1 = $rfq_done ? 'done' : ($rfq_avail ? 'active' : 'locked'); // PR→RFQ
      $conn2 = $po_done  ? 'done' : ($po_avail  ? 'active' : 'locked'); // RFQ→PO
      $conn3 = $iar_done ? 'done' : ($iar_avail ? 'active' : 'locked'); // PO→IAR
      $conn4 = $dv_done  ? 'done' : ($dv_avail  ? 'active' : 'locked'); // IAR→DV

      // ── Status badge ─────────────────────────
      $status_text  = htmlspecialchars($pr['status'] ?? 'N/A');
      $status_class = match($pr['status'] ?? '') {
        'Approved'  => 'bg-green-100 text-green-700 border border-green-200',
        'Pending'   => 'bg-yellow-100 text-yellow-700 border border-yellow-200',
        'Cancelled' => 'bg-red-100 text-red-700 border border-red-200',
        default     => 'bg-gray-100 text-gray-600 border border-gray-200',
      };

      // ── Completion percentage ─────────────────
      $steps_done = (int)$pr_done + (int)$rfq_done + (int)$po_done + (int)$iar_done + (int)$dv_done;
      $pct        = round(($steps_done / 5) * 100);
      $pct_color  = $pct === 100 ? 'bg-green-500' : ($pct >= 60 ? 'bg-blue-500' : ($pct >= 40 ? 'bg-yellow-500' : 'bg-gray-400'));
    ?>

    <!-- ┌── PR CARD ─────────────────────────────────────┐ -->
    <div class="proc-card bg-white rounded-xl shadow overflow-hidden">

      <!-- Card Header -->
      <div class="flex flex-wrap items-center justify-between px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-blue-950 to-blue-900">
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-white font-bold text-base tracking-wide font-mono"><?= htmlspecialchars($pr['pr_number']) ?></span>
          <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold <?= $status_class ?>"><?= $status_text ?></span>
          <span class="text-blue-300 text-xs border border-blue-700 rounded px-2 py-0.5">
            <i class="fas fa-layer-group text-[10px] mr-1"></i>Fund: <?= htmlspecialchars($pr['fund_cluster'] ?? '—') ?>
          </span>
        </div>
        <div class="flex items-center gap-4">
          <!-- Progress pill -->
          <div class="flex items-center gap-2">
            <span class="text-blue-300 text-xs font-medium"><?= $steps_done ?>/5 steps</span>
            <div class="w-20 h-1.5 bg-blue-800 rounded-full overflow-hidden">
              <div class="h-full <?= $pct_color ?> rounded-full transition-all" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="text-blue-200 text-xs font-bold"><?= $pct ?>%</span>
          </div>
          <span class="text-blue-300 text-xs">
            <i class="fas fa-calendar-alt mr-1 text-[10px]"></i>
            <?= !empty($pr['pr_date']) ? date('M d, Y', strtotime($pr['pr_date'])) : '—' ?>
          </span>
        </div>
      </div>

      <!-- Card Body -->
      <div class="px-5 pt-3 pb-1">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="flex-1 min-w-0">
            <div class="text-xs font-semibold text-blue-800 uppercase tracking-wide mb-0.5">
              <i class="fas fa-building mr-1"></i><?= htmlspecialchars($pr['entity_name'] ?? '—') ?>
            </div>
            <p class="text-sm text-gray-600 leading-snug line-clamp-2">
              <?= htmlspecialchars(mb_substr($pr['purpose'] ?? '', 0, 120)) . (mb_strlen($pr['purpose'] ?? '') > 120 ? '…' : '') ?>
            </p>
          </div>
          <div class="text-right shrink-0">
            <div class="text-xs text-gray-400 mb-0.5">Total Amount</div>
            <div class="text-lg font-extrabold text-gray-800">₱<?= number_format((float)$pr['total_amount'], 2) ?></div>
          </div>
        </div>
      </div>

      <!-- ── STEP STEPPER ───────────────────────────────── -->
      <div class="px-5 py-4">
        <div class="flex items-start w-full overflow-x-auto pb-1">

          <!-- ████ STEP 1: PURCHASE REQUEST ████ -->
          <div class="flex flex-col items-center shrink-0" style="min-width:90px;">
            <div class="w-11 h-11 rounded-full bg-green-500 shadow-md flex items-center justify-center text-white">
              <i class="fas fa-file-alt text-base"></i>
            </div>
            <div class="text-xs font-bold text-green-700 mt-1.5">PR</div>
            <div class="text-[10px] text-gray-400 mt-0.5 font-mono text-center leading-tight">
              <?= htmlspecialchars($pr['pr_number']) ?>
            </div>
            <?php if(hasPermission(PERM_PURCHASE_REQUEST)): ?><a href="pr_view.php?id=<?= $pr['id'] ?>" class="mt-1 text-[10px] text-blue-500 hover:underline flex items-center gap-0.5"><i class="fas fa-eye text-[9px]"></i> View</a><?php else: ?><span class="mt-1 text-[10px] text-red-400 italic cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-[9px]"></i> No Access</span><?php endif; ?>
          </div>

          <!-- Connector PR → RFQ -->
          <div class="flex items-center flex-1 min-w-[30px] mt-5 mx-1">
            <?php if ($conn1 === 'done'): ?>
              <div class="w-full h-1 rounded-full step-connector-done"></div>
              <i class="fas fa-chevron-right text-green-500 text-[10px] -ml-1"></i>
            <?php elseif ($conn1 === 'active'): ?>
              <div class="w-full h-1 rounded-full bg-gradient-to-r from-green-400 to-blue-400" style="background-size:200%"></div>
              <i class="fas fa-chevron-right text-blue-400 text-[10px] -ml-1"></i>
            <?php else: ?>
              <div class="w-full h-0.5 border-t-2 border-dashed border-gray-300"></div>
              <i class="fas fa-chevron-right text-gray-300 text-[10px] -ml-1"></i>
            <?php endif; ?>
          </div>

          <!-- ████ STEP 2: RFQ ████ -->
          <div class="flex flex-col items-center shrink-0" style="min-width:90px;">
            <?php if ($rfq_done): ?>
              <!-- DONE -->
              <div class="w-11 h-11 rounded-full bg-teal-500 shadow-md flex items-center justify-center text-white">
                <i class="fas fa-check text-base"></i>
              </div>
              <div class="text-xs font-bold text-teal-700 mt-1.5">RFQ</div>
              <div class="text-[10px] text-gray-400 mt-0.5 font-mono text-center leading-tight">
                <?= htmlspecialchars($rfq['rfq_number']) ?>
              </div>
              <?php if(hasPermission(PERM_QUOTATIONS)): ?><a href="rfq_view.php?id=<?= $rfq['id'] ?>" class="mt-1 text-[10px] text-blue-500 hover:underline flex items-center gap-0.5"><i class="fas fa-eye text-[9px]"></i> View</a><?php else: ?><span class="mt-1 text-[10px] text-red-400 italic cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-[9px]"></i> No Access</span><?php endif; ?>

            <?php elseif ($rfq_avail): ?>
              <!-- AVAILABLE -->
              <div class="step-available tooltip-wrap">
                <?php if(hasPermission(PERM_QUOTATIONS)): ?><a href="rfq_add.php?pr_id=<?= $pid ?>" class="step-circle w-11 h-11 rounded-full bg-blue-600 shadow-md flex items-center justify-center text-white hover:bg-blue-700 transition"><i class="fas fa-plus text-base"></i></a><?php else: ?><span class="step-circle w-11 h-11 rounded-full bg-gray-300 shadow-md flex items-center justify-center text-gray-400 cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-base"></i></span><?php endif; ?>
                <div class="tooltip-box">Add RFQ for <?= htmlspecialchars($pr['pr_number']) ?></div>
              </div>
              <div class="text-xs font-bold text-blue-700 mt-1.5">RFQ</div>
              <?php if(hasPermission(PERM_QUOTATIONS)): ?><a href="rfq_add.php?pr_id=<?= $pid ?>" class="mt-1 text-[11px] font-semibold text-white bg-blue-600 hover:bg-blue-700 px-3 py-0.5 rounded-full transition shadow-sm flex items-center gap-1"><i class="fas fa-plus text-[9px]"></i> Add</a><?php else: ?><span class="mt-1 text-[11px] font-semibold text-red-400 bg-red-50 border border-red-200 px-3 py-0.5 rounded-full flex items-center gap-1 cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-[9px]"></i> No Access</span><?php endif; ?>

            <?php else: ?>
              <!-- LOCKED -->
              <div class="tooltip-wrap">
                <div class="w-11 h-11 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                  <i class="fas fa-lock text-sm"></i>
                </div>
                <div class="tooltip-box">Complete PR step first</div>
              </div>
              <div class="text-xs font-bold text-gray-400 mt-1.5">RFQ</div>
              <div class="text-[10px] text-gray-300 mt-0.5">Locked</div>
            <?php endif; ?>
          </div>

          <!-- Connector RFQ → PO -->
          <div class="flex items-center flex-1 min-w-[30px] mt-5 mx-1">
            <?php if ($conn2 === 'done'): ?>
              <div class="w-full h-1 rounded-full step-connector-done"></div>
              <i class="fas fa-chevron-right text-green-500 text-[10px] -ml-1"></i>
            <?php elseif ($conn2 === 'active'): ?>
              <div class="w-full h-1 rounded-full bg-gradient-to-r from-green-400 to-blue-400"></div>
              <i class="fas fa-chevron-right text-blue-400 text-[10px] -ml-1"></i>
            <?php else: ?>
              <div class="w-full h-0.5 border-t-2 border-dashed border-gray-300"></div>
              <i class="fas fa-chevron-right text-gray-300 text-[10px] -ml-1"></i>
            <?php endif; ?>
          </div>

          <!-- ████ STEP 3: PURCHASE ORDER ████ -->
          <div class="flex flex-col items-center shrink-0" style="min-width:90px;">
            <?php if ($po_done): ?>
              <!-- DONE -->
              <div class="w-11 h-11 rounded-full bg-indigo-500 shadow-md flex items-center justify-center text-white">
                <i class="fas fa-check text-base"></i>
              </div>
              <div class="text-xs font-bold text-indigo-700 mt-1.5">PO</div>
              <div class="text-[10px] text-gray-400 mt-0.5 font-mono text-center leading-tight">
                <?= htmlspecialchars($po['po_number']) ?>
              </div>
              <?php if(hasPermission(PERM_PURCHASE_ORDERS)): ?><a href="po_view.php?id=<?= $po['id'] ?>" class="mt-1 text-[10px] text-blue-500 hover:underline flex items-center gap-0.5"><i class="fas fa-eye text-[9px]"></i> View</a><?php else: ?><span class="mt-1 text-[10px] text-red-400 italic cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-[9px]"></i> No Access</span><?php endif; ?>

            <?php elseif ($po_avail): ?>
              <!-- AVAILABLE -->
              <div class="step-available tooltip-wrap">
                <?php if(hasPermission(PERM_PURCHASE_ORDERS)): ?><a href="po_add.php?pr_id=<?= $pid ?>" class="step-circle w-11 h-11 rounded-full bg-blue-600 shadow-md flex items-center justify-center text-white hover:bg-blue-700 transition"><i class="fas fa-plus text-base"></i></a><?php else: ?><span class="step-circle w-11 h-11 rounded-full bg-gray-300 shadow-md flex items-center justify-center text-gray-400 cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-base"></i></span><?php endif; ?>
                <div class="tooltip-box">Add PO for <?= htmlspecialchars($pr['pr_number']) ?></div>
              </div>
              <div class="text-xs font-bold text-blue-700 mt-1.5">PO</div>
              <?php if(hasPermission(PERM_PURCHASE_ORDERS)): ?><a href="po_add.php?pr_id=<?= $pid ?>" class="mt-1 text-[11px] font-semibold text-white bg-blue-600 hover:bg-blue-700 px-3 py-0.5 rounded-full transition shadow-sm flex items-center gap-1"><i class="fas fa-plus text-[9px]"></i> Add</a><?php else: ?><span class="mt-1 text-[11px] font-semibold text-red-400 bg-red-50 border border-red-200 px-3 py-0.5 rounded-full flex items-center gap-1 cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-[9px]"></i> No Access</span><?php endif; ?>

            <?php else: ?>
              <!-- LOCKED -->
              <div class="tooltip-wrap">
                <div class="w-11 h-11 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                  <i class="fas fa-lock text-sm"></i>
                </div>
                <div class="tooltip-box">Add RFQ first before PO</div>
              </div>
              <div class="text-xs font-bold text-gray-400 mt-1.5">PO</div>
              <div class="text-[10px] text-gray-300 mt-0.5">Locked</div>
            <?php endif; ?>
          </div>

          <!-- Connector PO → IAR -->
          <div class="flex items-center flex-1 min-w-[30px] mt-5 mx-1">
            <?php if ($conn3 === 'done'): ?>
              <div class="w-full h-1 rounded-full step-connector-done"></div>
              <i class="fas fa-chevron-right text-green-500 text-[10px] -ml-1"></i>
            <?php elseif ($conn3 === 'active'): ?>
              <div class="w-full h-1 rounded-full bg-gradient-to-r from-green-400 to-blue-400"></div>
              <i class="fas fa-chevron-right text-blue-400 text-[10px] -ml-1"></i>
            <?php else: ?>
              <div class="w-full h-0.5 border-t-2 border-dashed border-gray-300"></div>
              <i class="fas fa-chevron-right text-gray-300 text-[10px] -ml-1"></i>
            <?php endif; ?>
          </div>

          <!-- ████ STEP 4: IAR ████ -->
          <div class="flex flex-col items-center shrink-0" style="min-width:90px;">
            <?php if ($iar_done): ?>
              <!-- DONE -->
              <div class="w-11 h-11 rounded-full bg-yellow-500 shadow-md flex items-center justify-center text-white">
                <i class="fas fa-check text-base"></i>
              </div>
              <div class="text-xs font-bold text-yellow-700 mt-1.5">IAR</div>
              <div class="text-[10px] text-gray-400 mt-0.5 font-mono text-center leading-tight">
                <?= htmlspecialchars($iar['iar_number']) ?>
              </div>
              <?php if(hasPermission(PERM_IAR)): ?><a href="iar_view.php?id=<?= $iar['id'] ?>" class="mt-1 text-[10px] text-blue-500 hover:underline flex items-center gap-0.5"><i class="fas fa-eye text-[9px]"></i> View</a><?php else: ?><span class="mt-1 text-[10px] text-red-400 italic cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-[9px]"></i> No Access</span><?php endif; ?>

            <?php elseif ($iar_avail): ?>
              <!-- AVAILABLE — links to po_id for this PR -->
              <?php $link_po_id = (int)($po['id'] ?? 0); ?>
              <div class="step-available tooltip-wrap">
                <?php if(hasPermission(PERM_IAR)): ?><a href="iar_add.php?po_id=<?= $link_po_id ?>&pr_id=<?= $pid ?>" class="step-circle w-11 h-11 rounded-full bg-blue-600 shadow-md flex items-center justify-center text-white hover:bg-blue-700 transition"><i class="fas fa-plus text-base"></i></a><?php else: ?><span class="step-circle w-11 h-11 rounded-full bg-gray-300 shadow-md flex items-center justify-center text-gray-400 cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-base"></i></span><?php endif; ?>
                <div class="tooltip-box">Add IAR for <?= htmlspecialchars($po['po_number'] ?? '') ?></div>
              </div>
              <div class="text-xs font-bold text-blue-700 mt-1.5">IAR</div>
              <?php if(hasPermission(PERM_IAR)): ?><a href="iar_add.php?po_id=<?= $link_po_id ?>&pr_id=<?= $pid ?>" class="mt-1 text-[11px] font-semibold text-white bg-blue-600 hover:bg-blue-700 px-3 py-0.5 rounded-full transition shadow-sm flex items-center gap-1"><i class="fas fa-plus text-[9px]"></i> Add</a><?php else: ?><span class="mt-1 text-[11px] font-semibold text-red-400 bg-red-50 border border-red-200 px-3 py-0.5 rounded-full flex items-center gap-1 cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-[9px]"></i> No Access</span><?php endif; ?>

            <?php else: ?>
              <!-- LOCKED -->
              <div class="tooltip-wrap">
                <div class="w-11 h-11 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                  <i class="fas fa-lock text-sm"></i>
                </div>
                <div class="tooltip-box">Add PO first before IAR</div>
              </div>
              <div class="text-xs font-bold text-gray-400 mt-1.5">IAR</div>
              <div class="text-[10px] text-gray-300 mt-0.5">Locked</div>
            <?php endif; ?>
          </div>

          <!-- Connector IAR → DV -->
          <div class="flex items-center flex-1 min-w-[30px] mt-5 mx-1">
            <?php if ($conn4 === 'done'): ?>
              <div class="w-full h-1 rounded-full step-connector-done"></div>
              <i class="fas fa-chevron-right text-green-500 text-[10px] -ml-1"></i>
            <?php elseif ($conn4 === 'active'): ?>
              <div class="w-full h-1 rounded-full bg-gradient-to-r from-green-400 to-blue-400"></div>
              <i class="fas fa-chevron-right text-blue-400 text-[10px] -ml-1"></i>
            <?php else: ?>
              <div class="w-full h-0.5 border-t-2 border-dashed border-gray-300"></div>
              <i class="fas fa-chevron-right text-gray-300 text-[10px] -ml-1"></i>
            <?php endif; ?>
          </div>

          <!-- ████ STEP 5: DISBURSEMENT VOUCHER ████ -->
          <div class="flex flex-col items-center shrink-0" style="min-width:90px;">
            <?php if ($dv_done): ?>
              <!-- DONE -->
              <div class="w-11 h-11 rounded-full bg-purple-500 shadow-md flex items-center justify-center text-white">
                <i class="fas fa-check text-base"></i>
              </div>
              <div class="text-xs font-bold text-purple-700 mt-1.5">DV</div>
              <div class="text-[10px] text-gray-400 mt-0.5 font-mono text-center leading-tight">
                <?= htmlspecialchars($dv['dv_number']) ?>
              </div>
              <?php if(hasPermission(PERM_DISBURSEMENT)): ?><a href="dv_view.php?id=<?= $dv['id'] ?>" class="mt-1 text-[10px] text-blue-500 hover:underline flex items-center gap-0.5"><i class="fas fa-eye text-[9px]"></i> View</a><?php else: ?><span class="mt-1 text-[10px] text-red-400 italic cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-[9px]"></i> No Access</span><?php endif; ?>

            <?php elseif ($dv_avail): ?>
              <!-- AVAILABLE -->
              <div class="step-available tooltip-wrap">
                <?php if(hasPermission(PERM_DISBURSEMENT)): ?><a href="dv_add.php?pr_id=<?= $pid ?>" class="step-circle w-11 h-11 rounded-full bg-blue-600 shadow-md flex items-center justify-center text-white hover:bg-blue-700 transition"><i class="fas fa-plus text-base"></i></a><?php else: ?><span class="step-circle w-11 h-11 rounded-full bg-gray-300 shadow-md flex items-center justify-center text-gray-400 cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-base"></i></span><?php endif; ?>
                <div class="tooltip-box">Add DV for <?= htmlspecialchars($pr['pr_number']) ?></div>
              </div>
              <div class="text-xs font-bold text-blue-700 mt-1.5">DV</div>
              <?php if(hasPermission(PERM_DISBURSEMENT)): ?><a href="dv_add.php?pr_id=<?= $pid ?>" class="mt-1 text-[11px] font-semibold text-white bg-blue-600 hover:bg-blue-700 px-3 py-0.5 rounded-full transition shadow-sm flex items-center gap-1"><i class="fas fa-plus text-[9px]"></i> Add</a><?php else: ?><span class="mt-1 text-[11px] font-semibold text-red-400 bg-red-50 border border-red-200 px-3 py-0.5 rounded-full flex items-center gap-1 cursor-not-allowed" title="You have no authority to access"><i class="fas fa-lock text-[9px]"></i> No Access</span><?php endif; ?>

            <?php else: ?>
              <!-- LOCKED -->
              <div class="tooltip-wrap">
                <div class="w-11 h-11 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                  <i class="fas fa-lock text-sm"></i>
                </div>
                <div class="tooltip-box">Add IAR first before DV</div>
              </div>
              <div class="text-xs font-bold text-gray-400 mt-1.5">DV</div>
              <div class="text-[10px] text-gray-300 mt-0.5">Locked</div>
            <?php endif; ?>
          </div>

        </div><!-- end flex stepper -->
      </div><!-- end step section -->

      <!-- Card Footer (fully complete banner) -->
      <?php if ($dv_done): ?>
        <div class="px-5 py-2 bg-gradient-to-r from-green-50 to-emerald-50 border-t border-green-100 flex items-center gap-2">
          <i class="fas fa-check-double text-green-500 text-sm"></i>
          <span class="text-xs font-semibold text-green-700">All procurement steps completed for this Purchase Request.</span>
        </div>
      <?php endif; ?>

    </div><!-- end proc-card -->
    <?php endforeach; ?>
  </div><!-- end space-y-4 -->
  <?php endif; ?>

  <!-- ══ PAGINATION ════════════════════════════════════════ -->
  <?php if ($total_pages > 1): ?>
  <div class="mt-6 flex items-center justify-between flex-wrap gap-3">
    <div class="text-sm text-gray-500">
      Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
    </div>
    <div class="flex items-center gap-1">
      <!-- First -->
      <?php if ($page > 1): ?>
        <a href="?page=1<?= $pag_q ? '&'.$pag_q : '' ?>"
           class="w-8 h-8 rounded-lg bg-white shadow text-gray-500 hover:bg-blue-50 flex items-center justify-center text-sm transition">
          <i class="fas fa-angle-double-left text-xs"></i>
        </a>
        <a href="?page=<?= $page-1 ?><?= $pag_q ? '&'.$pag_q : '' ?>"
           class="w-8 h-8 rounded-lg bg-white shadow text-gray-500 hover:bg-blue-50 flex items-center justify-center text-sm transition">
          <i class="fas fa-angle-left text-xs"></i>
        </a>
      <?php endif; ?>

      <?php
        $start_p = max(1, $page - 2);
        $end_p   = min($total_pages, $page + 2);
        for ($i = $start_p; $i <= $end_p; $i++):
      ?>
        <a href="?page=<?= $i ?><?= $pag_q ? '&'.$pag_q : '' ?>"
           class="w-8 h-8 rounded-lg shadow flex items-center justify-center text-sm font-medium transition
                  <?= $i === $page ? 'bg-blue-700 text-white' : 'bg-white text-gray-600 hover:bg-blue-50' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page+1 ?><?= $pag_q ? '&'.$pag_q : '' ?>"
           class="w-8 h-8 rounded-lg bg-white shadow text-gray-500 hover:bg-blue-50 flex items-center justify-center text-sm transition">
          <i class="fas fa-angle-right text-xs"></i>
        </a>
        <a href="?page=<?= $total_pages ?><?= $pag_q ? '&'.$pag_q : '' ?>"
           class="w-8 h-8 rounded-lg bg-white shadow text-gray-500 hover:bg-blue-50 flex items-center justify-center text-sm transition">
          <i class="fas fa-angle-double-right text-xs"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- max-w-7xl -->
</main>

<script>
// Live Date & Time
function updateDT() {
  const n = new Date();
  document.getElementById('currentDate').textContent =
    n.toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'});
  document.getElementById('currentTime').textContent =
    n.toLocaleTimeString('en-US', {hour12:true});
}
updateDT();
setInterval(updateDT, 1000);
</script>

<?php include 'inc/footer.php'; ?>