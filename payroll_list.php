<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// --- Pagination Settings ---
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get search and filter values from the URL
$search_query = $_GET['search'] ?? '';
$month_filter = $_GET['month'] ?? '';

// --- Build the SQL Query Logic ---
$conditions = [];
$params = [];
$types = '';

if (!empty($search_query)) {
    $conditions[] = "pr.employee_name LIKE ?";
    $params[] = "%" . $search_query . "%";
    $types .= 's';
}

if (!empty($month_filter)) {
    $conditions[] = "MONTH(pr.created_at) = ?";
    $params[] = $month_filter;
    $types .= 'i';
}

$where_clause = !empty($conditions) ? " WHERE " . implode(' AND ', $conditions) : "";

// --- 1. Get Total Count for Pagination ---
$count_sql = "SELECT COUNT(*) as total FROM payroll_requests pr $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);
$count_stmt->close();

// --- 2. Fetch Paginated Records (Ordered by ID DESC for Priority) ---
$sql = "
  SELECT 
    pr.*, 
    (SELECT COUNT(*) FROM payroll_dvs pd WHERE pd.payroll_id = pr.id) AS dv_count
  FROM payroll_requests pr
  $where_clause
  ORDER BY pr.id DESC
  LIMIT ? OFFSET ?
";

$final_params = $params;
$final_params[] = $limit;
$final_params[] = $offset;
$final_types = $types . 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$res = $stmt->get_result();

function getPaginationUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}
?>

<main class="flex-1 p-4 md:p-6 bg-blue-100 min-h-screen">
  <div class="bg-white p-4 md:p-6 rounded-xl shadow-lg max-w-full">

    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-6 gap-4">
      <h1 class="text-2xl font-bold text-gray-800">Payroll Requests</h1>

      <form method="GET" action="" class="flex flex-wrap items-center gap-2">
        <input type="search" name="search" placeholder="Search by Employee..." 
               value="<?php echo htmlspecialchars($search_query); ?>" 
               class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-full md:w-48">
        
        <select name="month" class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
          <option value="">All Months</option>
          <?php
          for ($m = 1; $m <= 12; $m++) {
              $month_name = date('F', mktime(0, 0, 0, $m, 1));
              $selected = ($month_filter == $m) ? 'selected' : '';
              echo "<option value=\"$m\" $selected>$month_name</option>";
          }
          ?>
        </select>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow">Filter</button>
        
        <?php if ($search_query || $month_filter): ?>
            <a href="payroll_list.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg text-sm" title="Reset">🔄</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="relative overflow-x-auto shadow-sm border border-gray-200 rounded-lg max-h-[650px] overflow-y-auto">
      <table class="min-w-full table-auto text-sm text-left">
        <thead class="bg-gray-50 text-gray-700 font-semibold uppercase tracking-wider sticky top-0 z-10 shadow-sm">
          <tr>
            <th class="px-4 py-3 border-b bg-gray-50">#</th>
            <th class="px-4 py-3 border-b bg-gray-50">Payroll No.</th>
            <th class="px-4 py-3 border-b bg-gray-50">Employee / Payee</th>
            <th class="px-4 py-3 border-b bg-gray-50">Salary Amount</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-center">Status</th>
            <th class="px-4 py-3 border-b bg-gray-50">Created At</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <?php if ($res && $res->num_rows > 0): 
            $i = $offset + 1;
            while ($row = $res->fetch_assoc()): ?>
              <tr class="hover:bg-blue-50 transition">
                <td class="px-4 py-3 text-gray-500"><?php echo $i++; ?></td>
                <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap"><?php echo htmlspecialchars($row['payroll_number']); ?></td>
                <td class="px-4 py-3 text-gray-700"><?php echo htmlspecialchars($row['employee_name']); ?></td>
                <td class="px-4 py-3 font-bold text-blue-700 whitespace-nowrap">₱<?php echo number_format($row['salary_amount'], 2); ?></td>
                
                <td class="px-4 py-3 text-center">
                  <?php if ($row['dv_count'] > 0): ?>
                    <span class="px-2.5 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">Complete</span>
                  <?php else: ?>
                    <span class="px-2.5 py-1 text-xs font-semibold bg-red-100 text-red-700 rounded-full">No DV</span>
                  <?php endif; ?>
                </td>

                <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>

                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <a href="payroll_edit.php?id=<?php echo $row['id']; ?>" class="w-8 h-8 flex items-center justify-center bg-yellow-500 hover:bg-yellow-600 text-white rounded shadow-sm transition" title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    </a>
                    
                    <a href="payroll_delete.php?id=<?php echo $row['id']; ?>" class="w-8 h-8 flex items-center justify-center bg-gray-500 hover:bg-gray-600 text-white rounded shadow-sm transition" title="Delete" onclick="return confirm('Delete this payroll request?')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </a>

                    <?php if ($row['dv_count'] == 0): ?>
                      <a href="payroll_dv_add.php?payroll_id=<?php echo $row['id']; ?>" class="w-8 h-8 flex items-center justify-center bg-green-600 hover:bg-green-700 text-white rounded shadow-sm transition" title="Create DV">
                          <span class="text-[10px] font-bold">DV</span>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td class="px-4 py-10 text-center text-gray-400 italic" colspan="7">No payroll requests found matching your criteria.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-4 flex flex-col md:flex-row items-center justify-between mt-4 border-t border-gray-100">
      <div class="flex-1 text-sm text-gray-500 mb-4 md:mb-0">
        Showing <span class="font-bold"><?php echo $offset + 1; ?></span> to 
        <span class="font-bold"><?php echo min($offset + $limit, $total_results); ?></span> of 
        <span class="font-bold"><?php echo $total_results; ?></span> results
      </div>

      <div class="flex-none flex items-center justify-center space-x-1">
        <?php if ($page > 1): ?>
          <a href="<?php echo getPaginationUrl($page - 1); ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Previous</a>
        <?php endif; ?>

        <?php 
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        for ($p = $start; $p <= $end; $p++): ?>
          <a href="<?php echo getPaginationUrl($p); ?>" 
             class="px-3 py-1 border rounded-md transition <?php echo ($p == $page) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-100 text-gray-700'; ?>">
            <?php echo $p; ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
          <a href="<?php echo getPaginationUrl($page + 1); ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Next</a>
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