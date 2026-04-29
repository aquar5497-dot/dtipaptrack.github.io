<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// === HANDLE FILTERS & PAGINATION ===
$limit = 4; // 4 items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get filter values
$search = $_GET['search'] ?? '';
$month = $_GET['month'] ?? '';

// Build WHERE clause
$where_sql = "WHERE pr.parent_id IS NULL";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $where_sql .= " AND pr.pr_number LIKE '%$s%'";
}
if (!empty($month)) {
    $m = $conn->real_escape_string($month);
    $where_sql .= " AND MONTH(pr.pr_date) = '$m'";
}

// --- Get total number of PRs for pagination ---
$count_query = "SELECT COUNT(*) AS total FROM purchase_requests pr $where_sql";
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Build query string for pagination links
$pagination_params = http_build_query(['search' => $search, 'month' => $month]);


// === FETCH STATUS TOTALS (MODIFIED with WHERE) ===
$totals_query = "
  SELECT
    COUNT(CASE
      WHEN rfq_count > 0 AND po_count > 0 AND iar_count > 0 AND dv_count > 0 THEN 1
    END) AS completed_count,
    COUNT(CASE
      WHEN (rfq_count > 0) AND NOT (rfq_count > 0 AND po_count > 0 AND iar_count > 0 AND dv_count > 0) THEN 1
    END) AS ongoing_count,
    COUNT(CASE
      WHEN rfq_count = 0 THEN 1
    END) AS pending_count
  FROM (
    SELECT
      pr.id,
      (SELECT COUNT(*) FROM rfqs rfq WHERE rfq.pr_id = pr.id) AS rfq_count,
      (SELECT COUNT(*) FROM purchase_orders po WHERE po.pr_id = pr.id) AS po_count,
      (SELECT COUNT(*) FROM iars i INNER JOIN purchase_orders po ON i.po_id=po.id WHERE po.pr_id=pr.id) AS iar_count,
      (SELECT COUNT(*) FROM disbursement_vouchers dv WHERE dv.pr_id = pr.id) AS dv_count
    FROM purchase_requests pr
    $where_sql
  ) AS subquery;
";
$totals_result = $conn->query($totals_query);
$totals = $totals_result->fetch_assoc();

$pending_total = $totals['pending_count'] ?? 0;
$ongoing_total = $totals['ongoing_count'] ?? 0;
$completed_total = $totals['completed_count'] ?? 0;


// === FETCH TIMELINE DATA (MODIFIED with WHERE and LIMIT) ===
$timeline_query = "
  SELECT
    pr.pr_number,
    pr.pr_date AS pr_created_date,
    (SELECT MIN(rfq.rfq_date) FROM rfqs rfq WHERE rfq.pr_id = pr.id) AS quotation_date,
    (SELECT MIN(po.po_date) FROM purchase_orders po WHERE po.pr_id = pr.id) AS po_date,
    (SELECT MIN(i.iar_date) FROM iars i INNER JOIN purchase_orders po ON i.po_id = po.id WHERE po.pr_id = pr.id) AS iar_date,
    (SELECT MIN(dv.dv_date) FROM disbursement_vouchers dv WHERE dv.pr_id = pr.id) AS dv_date
  FROM
    purchase_requests pr
  $where_sql
  ORDER BY
    pr.id DESC
  LIMIT $limit OFFSET $offset;
";
$timeline_rows = $conn->query($timeline_query);

/**
 * Helper function to render a timeline step.
 */
function renderTimelineStep($title, $date, $color) {
    $date_display = $date ? date("Y-m-d", strtotime($date)) : 'pending';
    
    $badge_class = '';
    $badge_text_color = '';
    $date_text_class = $date ? 'text-xs text-gray-700' : 'text-xs text-gray-500 italic';

    switch ($color) {
        case 'blue':
            $badge_class = $date ? 'bg-blue-600' : 'border border-blue-600 text-blue-600';
            break;
        case 'orange':
            $badge_class = $date ? 'bg-orange-500' : 'border border-orange-500 text-orange-500';
            break;
        case 'green':
            $badge_class = $date ? 'bg-green-600' : 'border border-green-600 text-green-600';
            break;
        case 'pink':
            $badge_class = $date ? 'bg-pink-600' : 'border border-pink-600 text-pink-600';
            break;
        case 'purple':
            $badge_class = $date ? 'bg-purple-600' : 'border border-purple-600 text-purple-600';
            break;
    }
    
    if ($date) {
        $badge_text_color = 'text-white';
    }

    echo "
    <div class='flex flex-col items-center flex-1 min-w-[70px]'>
      <div class='px-2.5 py-0.5 rounded-full text-xs font-medium $badge_class $badge_text_color'>
        $title
      </div>
      <span class='$date_text_class mt-1'>$date_display</span>
    </div>
    ";
}
?>

<main class="flex-1 p-4 overflow-y-auto" style="min-height: calc(100vh - 76px);">
  
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
    <h2 class="text-xl font-bold text-gray-800">Timeline Dashboard</h2>
    
    <div class="flex flex-col md:flex-row items-center gap-3 w-full md:w-auto">
      
      <form method="get" class="flex flex-col sm:flex-row gap-2 items-center w-full md:w-auto">
        <input 
          type="text" 
          name="search" 
          value="<?= htmlspecialchars($search) ?>" 
          placeholder="Search PR Number..." 
          class="border rounded px-3 py-2 text-sm w-full sm:w-64 bg-white"
        >
        <select name="month" class="border rounded px-3 py-2 text-sm w-full sm:w-auto bg-white">
          <option value="">All Months</option>
          <?php
          for ($m = 1; $m <= 12; $m++):
            $monthName = date("F", mktime(0, 0, 0, $m, 1));
          ?>
            <option value="<?= $m ?>" <?= ($month == $m ? 'selected' : '') ?>><?= $monthName ?></option>
          <?php endfor; ?>
        </select>
        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm w-full sm:w-auto">Filter</button>
      </form>
      
      <a href="progress_overview.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded text-sm font-medium w-full md:w-auto text-center">
        &larr; Back to Overview
      </a>
      
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4" style="margin-top: 15px;">
    <div class="bg-white p-4 rounded-xl shadow">
      <h4 class="text-xs font-semibold text-gray-500 uppercase" style="text-align: center;">Pending</h4>
      <p class="text-3xl font-bold text-gray-800 mt-1" style="text-align: center;"><?= $pending_total ?></p>
      <p class="text-xs text-gray-400 mt-1" style="text-align: center;">PRs with no RFQ yet.</p>
    </div>
    
    <div class="bg-white p-4 rounded-xl shadow">
      <h4 class="text-xs font-semibold text-yellow-500 uppercase" style="text-align: center;">Ongoing</h4>
      <p class="text-3xl font-bold text-yellow-600 mt-1" style="text-align: center;"><?= $ongoing_total ?></p>
      <p class="text-xs text-gray-400 mt-1" style="text-align: center;">PRs in progress.</p>
    </div>
    
    <div class="bg-white p-4 rounded-xl shadow">
      <h4 class="text-xs font-semibold text-green-500 uppercase" style="text-align: center;">Completed</h4>
      <p class="text-3xl font-bold text-green-600 mt-1" style="text-align: center;"><?= $completed_total ?></p>
      <p class="text-xs text-gray-400 mt-1" style="text-align: center;">PRs with all documents.</p>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow">
    <h3 class="text-lg font-bold text-gray-800 mb-3">Purchase Request Timelines</h3>
    
    <div class="space-y-4">
      <?php if ($timeline_rows && $timeline_rows->num_rows > 0): ?>
        <?php while($row = $timeline_rows->fetch_assoc()): ?>
          
          <div class="bg-gray-50 p-3 rounded-lg shadow-inner">
            <h4 class="text-base font-semibold text-gray-700 mb-3"><?= htmlspecialchars($row['pr_number']) ?> Timeline</h4>
            
            <div class="flex flex-col sm:flex-row justify-between items-center sm:space-x-4 space-y-4 sm:space-y-0">
              <?php
                renderTimelineStep('PR Created', $row['pr_created_date'], 'blue');
                renderTimelineStep('Quotation', $row['quotation_date'], 'orange');
                renderTimelineStep('PO', $row['po_date'], 'green');
                renderTimelineStep('IAR', $row['iar_date'], 'pink');
                renderTimelineStep('DV', $row['dv_date'], 'purple');
              ?>
            </div>
          </div>
          
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-center text-gray-500">
          No purchase request timelines found
          <?= (!empty($search) || !empty($month)) ? ' for the selected filters.' : '.' ?>
        </p>
      <?php endif; ?>
    </div>

    <nav class="mt-6 flex justify-center items-center" aria-label="Pagination">
      <div class="flex items-center space-x-2">
        
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>&<?= $pagination_params ?>" class="px-3 py-1 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50">
            Previous
          </a>
        <?php else: ?>
          <span class="px-3 py-1 text-sm font-medium text-gray-400 bg-gray-100 rounded-md border border-gray-200 cursor-not-allowed">
            Previous
          </span>
        <?php endif; ?>

        <?php
          $start = max(1, $page - 2);
          $end = min($total_pages, $page + 2);

          if ($page < 3) {
            $end = min(5, $total_pages);
          }
          if ($page > $total_pages - 2) {
            $start = max(1, $total_pages - 4);
          }
        ?>

        <?php if ($start > 1): ?>
          <a href="?page=1&<?= $pagination_params ?>" class="px-3 py-1 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50">1</a>
          <span class="px-3 py-1 text-sm font-m
edium text-gray-500">...</span>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
          <a href="?page=<?= $i ?>&<?= $pagination_params ?>" 
             class="px-3 py-1 text-sm font-medium rounded-md border 
                    <?= $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-700 bg-white border-gray-300 hover:bg-gray-50' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
        
        <?php if ($end < $total_pages): ?>
          <span class="px-3 py-1 text-sm font-medium text-gray-500">...</span>
          <a href="?page=<?= $total_pages ?>&<?= $pagination_params ?>" class="px-3 py-1 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50"><?= $total_pages ?></a>
        <?php endif; ?>
        
        <?php if ($page < $total_pages): ?>
          <a href="?page=<?= $page + 1 ?>&<?= $pagination_params ?>" class="px-3 py-1 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50">
            Next
          </a>
        <?php else: ?>
          <span class="px-3 py-1 text-sm font-medium text-gray-400 bg-gray-100 rounded-md border border-gray-200 cursor-not-allowed">
            Next
          </span>
        <?php endif; ?>
        
      </div>
    </nav>
    
  </div>
  
</main>

<?php include 'inc/footer.php'; ?>