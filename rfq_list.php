<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// Get search and filter values from the URL
$search_query = $_GET['search'] ?? '';
$month_filter = $_GET['month'] ?? '';

// --- PAGINATION SETUP ---
$records_per_page = 13;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// --- DYNAMIC SQL QUERY CONSTRUCTION ---
$sql_base = "FROM rfqs rfq LEFT JOIN purchase_requests pr ON rfq.pr_id = pr.id";
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_query)) {
    $where_clauses[] = "(rfq.rfq_number LIKE ? OR pr.pr_number LIKE ?)";
    $search_term = "%" . $search_query . "%";
    array_push($params, $search_term, $search_term);
    $types .= 'ss';
}

if (!empty($month_filter)) {
    $where_clauses[] = "MONTH(rfq.rfq_date) = ?";
    $params[] = $month_filter;
    $types .= 'i';
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// --- PAGINATION COUNT ---
$sql_count = "SELECT COUNT(rfq.id) as total " . $sql_base . $where_sql;
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result()->fetch_assoc();
$total_records = $count_result['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt_count->close();

// --- FINAL DATA QUERY ---
$sql = "SELECT rfq.*, pr.pr_number " . $sql_base . $where_sql;
$sql .= " ORDER BY rfq.id DESC LIMIT ? OFFSET ?";

$final_params = $params;
$final_params[] = $records_per_page;
$final_params[] = $offset;
$final_types = $types . 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$result = $stmt->get_result();
?>

<main class="flex-1 p-4 md:p-6 bg-blue-100 min-h-screen">
  <div class="bg-white p-4 md:p-6 rounded-xl shadow-lg max-w-full">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
      <h2 class="text-xl font-bold text-gray-800">Quotations (RFQ)</h2>

      <div class="w-full md:w-auto">
        <form method="GET" action="" class="flex flex-wrap items-center gap-2">
          <input type="search" name="search" placeholder="Search RFQ or PR..." 
                 value="<?php echo htmlspecialchars($search_query); ?>" 
                 class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-sm w-full md:w-48">
          
          <select name="month" class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
            <option value="">All Months</option>
            <?php
            for ($m = 1; $m <= 12; $m++) {
                $month_name = date('F', mktime(0, 0, 0, $m, 1));
                $selected = ($month_filter == $m) ? 'selected' : '';
                echo "<option value=\"$m\" $selected>$month_name</option>";
            }
            ?>
          </select>
          
          <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow">Filter</button>
          
          <?php if ($search_query || $month_filter): ?>
              <a href="rfq_list.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg text-sm" title="Reset">🔄</a>
          <?php endif; ?>


        </form>
      </div>
    </div>

    <div class="relative overflow-x-auto shadow-sm border border-gray-200 rounded-lg max-h-[600px] overflow-y-auto">
      <table class="min-w-full table-auto text-sm text-left">
        <thead class="bg-gray-50 text-gray-700 font-semibold uppercase tracking-wider sticky top-0 z-10 shadow-sm">
          <tr>
            <th class="px-4 py-3 border-b bg-gray-50">RFQ Number</th>
            <th class="px-4 py-3 border-b bg-gray-50">RFQ Date</th>
            <th class="px-4 py-3 border-b bg-gray-50">Linked PR</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr class="hover:bg-blue-50 transition">
              <td class="px-4 py-3 font-bold text-blue-600 whitespace-nowrap">
                <?php echo htmlspecialchars($row['rfq_number']); ?>
              </td>
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                <?php echo date('M d, Y', strtotime($row['rfq_date'])); ?>
              </td>
              <td class="px-4 py-3 text-gray-700">
                <span class="bg-gray-100 px-2 py-1 rounded text-xs font-mono border border-gray-200">
                    <?php echo htmlspecialchars($row['pr_number']); ?>
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                    <a href="rfq_edit.php?id=<?php echo $row['id']; ?>" class="w-8 h-8 flex items-center justify-center bg-yellow-500 hover:bg-yellow-600 text-white rounded shadow-sm transition" title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    </a>
                    
                    <a href="rfq_delete.php?id=<?php echo $row['id']; ?>" class="w-8 h-8 flex items-center justify-center bg-gray-500 hover:bg-gray-600 text-white rounded shadow-sm transition" title="Delete" onclick="return confirm('Delete this RFQ?')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="text-center py-10 text-gray-400 italic">No records found matching your criteria.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-4 flex flex-col md:flex-row items-center justify-between mt-4 border-t border-gray-100">
      <div class="flex-1 text-sm text-gray-500 mb-4 md:mb-0">
        Showing page <span class="font-bold"><?= $page ?></span> of <span class="font-bold"><?= $total_pages ?></span>
      </div>

      <div class="flex-none flex items-center justify-center space-x-1">
        <?php
        $query_params = $_GET;
        unset($query_params['page']);
        $base_url = '?' . http_build_query($query_params) . '&';

        if ($page > 1): ?>
          <a href="<?= $base_url ?>page=<?= $page - 1 ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Previous</a>
        <?php endif; ?>

        <?php 
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
          <a href="<?= $base_url ?>page=<?= $i ?>" 
             class="px-3 py-1 border rounded-md transition <?= ($i == $page) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-100 text-gray-700'; ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
          <a href="<?= $base_url ?>page=<?= $page + 1 ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Next</a>
        <?php endif; ?>
      </div>
      <div class="hidden md:block flex-1"></div>
    </div>
    <?php endif; ?>

  </div>
</main>

<?php 
$stmt->close();
include 'inc/footer.php'; 
?>