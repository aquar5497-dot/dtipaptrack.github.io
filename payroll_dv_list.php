<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// --- Pagination Settings ---
$records_per_page = 9; 
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get search and filter values from the URL
$search_query = $_GET['search'] ?? '';
$month_filter = $_GET['month'] ?? '';

// --- Build WHERE conditions ---
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_query)) {
    $where_clauses[] = "(pd.dv_number LIKE ? OR pd.payee LIKE ? OR pr.payroll_number LIKE ?)";
    $search_term = "%{$search_query}%";
    array_push($params, $search_term, $search_term, $search_term);
    $types .= 'sss';
}

if (!empty($month_filter)) {
    $where_clauses[] = "MONTH(pd.dv_date) = ?";
    $params[] = $month_filter;
    $types .= 'i';
}

$where_sql = $where_clauses ? " WHERE " . implode(" AND ", $where_clauses) : "";

// --- 1. Get Total Count ---
$count_sql = "SELECT COUNT(*) as total FROM payroll_dvs pd LEFT JOIN payroll_requests pr ON pr.id = pd.payroll_id $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();

$current_page = max(1, min($current_page, $total_pages == 0 ? 1 : $total_pages));
$offset = ($current_page - 1) * $records_per_page;

// --- 2. Fetch Paginated Records (Ordered by ID DESC for Priority) ---
$sql = "
    SELECT 
        pd.id, pd.dv_number, pd.dv_date, pd.payee, pd.gross_amount, 
        pd.tax_percentage, pd.tax_amount, pd.net_amount, pd.remarks, pr.payroll_number
    FROM payroll_dvs pd
    LEFT JOIN payroll_requests pr ON pr.id = pd.payroll_id
    $where_sql
    ORDER BY pd.id DESC
    LIMIT ? OFFSET ?
";

$final_params = $params;
$final_params[] = $records_per_page;
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

$success = $_GET['success'] ?? '';
?>

<main class="flex-1 p-4 md:p-6 bg-blue-100 min-h-screen">
  <div class="bg-white p-4 md:p-6 rounded-xl shadow-lg max-w-full">

    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
      <h2 class="text-xl font-bold text-gray-800">Payroll Disbursement Vouchers</h2>

      <div class="w-full md:w-auto">
        <form method="GET" class="flex flex-wrap items-center gap-2">
          <input type="search" name="search" placeholder="Search DV, Payee, Payroll..."
                 value="<?= htmlspecialchars($search_query) ?>"
                 class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-full md:w-56">

          <select name="month" class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
            <option value="">All Months</option>
            <?php for ($m=1;$m<=12;$m++): ?>
              <option value="<?= $m ?>" <?= ($month_filter==$m?'selected':'') ?>>
                <?= date('F', mktime(0,0,0,$m,1)) ?>
              </option>
            <?php endfor; ?>
          </select>

          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow">Filter</button>
          <a href="payroll_list.php" class="bg-green-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow">Go to Payroll</a>
        </form>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="bg-green-100 border border-green-300 text-green-800 p-3 rounded mb-4 text-sm">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <div class="relative overflow-x-auto shadow-sm border border-gray-200 rounded-lg max-h-[600px] overflow-y-auto">
      <table class="min-w-full table-auto text-sm text-left">
        <thead class="bg-gray-50 text-gray-700 font-semibold uppercase tracking-wider sticky top-0 z-10 shadow-sm">
          <tr>
            <th class="px-4 py-3 border-b bg-gray-50">DV Number</th>
            <th class="px-4 py-3 border-b bg-gray-50">Linked Payroll</th>
            <th class="px-4 py-3 border-b bg-gray-50">DV Date</th>
            <th class="px-4 py-3 border-b bg-gray-50">Payee</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-right">Gross</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-right">Tax</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-right">Net</th>
            <th class="px-4 py-3 border-b bg-gray-50">Remarks</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-center">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200 bg-white">
          <?php if ($res && $res->num_rows): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
              <tr class="hover:bg-blue-50 transition">
                <td class="px-4 py-3 font-bold text-blue-700 whitespace-nowrap">
                  <?= htmlspecialchars($row['dv_number']) ?>
                </td>
                <td class="px-4 py-3">
                  <span class="text-xs font-mono font-bold text-gray-900 bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200">
                    <?= htmlspecialchars($row['payroll_number'] ?? '—') ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                   <?= !empty($row['dv_date']) ? date('M d, Y', strtotime($row['dv_date'])) : '—'; ?>
                </td>
                <td class="px-4 py-3 text-gray-700 truncate max-w-[150px]" title="<?= htmlspecialchars($row['payee']) ?>">
                  <?= htmlspecialchars($row['payee']) ?>
                </td>
                <td class="px-4 py-3 text-right font-medium">₱<?= number_format($row['gross_amount'],2) ?></td>
                <td class="px-4 py-3 text-right text-red-600">
                  <div class="flex flex-col">
                    <span>₱<?= number_format($row['tax_amount'],2) ?></span>
                    <span class="text-[10px] text-gray-400"><?= number_format($row['tax_percentage'],2) ?>%</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-right text-green-700 font-bold">₱<?= number_format($row['net_amount'],2) ?></td>
                <td class="px-4 py-3 max-w-xs truncate text-gray-500 italic text-xs" title="<?= htmlspecialchars($row['remarks']) ?>">
                  <?= htmlspecialchars($row['remarks']) ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <a href="payroll_dv_edit.php?id=<?= $row['id'] ?>" class="w-8 h-8 flex items-center justify-center bg-yellow-500 hover:bg-yellow-600 text-white rounded shadow-sm transition" title="Edit">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        </a>
                        <a href="payroll_dv_delete.php?id=<?= $row['id'] ?>" class="w-8 h-8 flex items-center justify-center bg-gray-500 hover:bg-gray-600 text-white rounded shadow-sm transition" title="Delete" onclick="return confirm('Delete this DV?')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </a>
                    </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="text-center py-10 text-gray-400 italic">No Payroll DVs found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-4 flex flex-col md:flex-row items-center justify-between mt-4 border-t border-gray-100">
      <div class="flex-1 text-sm text-gray-500 mb-4 md:mb-0">
        Showing <span class="font-bold"><?= $offset + 1 ?></span> to 
        <span class="font-bold"><?= min($offset + $records_per_page, $total_records) ?></span> of 
        <span class="font-bold"><?= $total_records ?></span> results
      </div>

      <div class="flex-none flex items-center justify-center space-x-1">
        <?php if ($current_page > 1): ?>
          <a href="<?= getPaginationUrl($current_page - 1) ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Previous</a>
        <?php endif; ?>

        <?php 
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        for ($p = $start; $p <= $end; $p++): ?>
          <a href="<?= getPaginationUrl($p) ?>" 
             class="px-3 py-1 border rounded-md transition <?= ($p == $current_page) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-100 text-gray-700' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
          <a href="<?= getPaginationUrl($current_page + 1) ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Next</a>
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