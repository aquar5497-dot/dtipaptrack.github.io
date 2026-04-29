<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// --- Pagination Setup ---
$records_per_page = 9;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get search and filter values from the URL
$search_query = $_GET['search'] ?? '';
$month_filter = $_GET['month'] ?? '';

// --- 1. Build the Base SQL Query and WHERE clauses ---
$base_sql = "FROM iars iar LEFT JOIN purchase_orders po ON iar.po_id = po.id";

$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_query)) {
    $where_clauses[] = "(iar.iar_number LIKE ? OR iar.invoice_number LIKE ? OR po.po_number LIKE ? OR po.supplier LIKE ?)";
    $search_term = "%" . $search_query . "%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
    $types .= 'ssss';
}

if (!empty($month_filter)) {
    $where_clauses[] = "MONTH(iar.iar_date) = ?";
    $params[] = $month_filter;
    $types .= 'i';
}

$where_clause_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

// --- 2. Get Total Number of Records ---
$count_sql = "SELECT COUNT(iar.id) AS total_records " . $base_sql . $where_clause_sql;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total_records'];
$count_stmt->close();

$total_pages = ceil($total_records / $records_per_page);
$current_page = max(1, min($current_page, $total_pages == 0 ? 1 : $total_pages));
$offset = ($current_page - 1) * $records_per_page;

// --- 3. Build Final Data Query ---
$sql = "SELECT iar.*, po.po_number, po.supplier " . $base_sql . $where_clause_sql;
$sql .= " ORDER BY iar.id DESC LIMIT ? OFFSET ?";

$final_params = $params;
$final_params[] = $records_per_page;
$final_params[] = $offset;
$final_types = $types . 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$result = $stmt->get_result();

function build_pagination_url($page_number) {
    $query_params = $_GET;
    $query_params['page'] = $page_number;
    return '?' . http_build_query($query_params);
}
?>

<main class="flex-1 p-4 md:p-6 bg-blue-100 min-h-screen">
  <div class="bg-white p-4 md:p-6 rounded-xl shadow-lg max-w-full">

    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
      <h2 class="text-xl font-bold text-gray-800">Inspection & Acceptance (IAR)</h2>

      <div class="w-full md:w-auto">
        <form method="GET" action="" class="flex flex-wrap items-center gap-2">
          <input type="search" name="search" placeholder="Search..." 
                 value="<?php echo htmlspecialchars($search_query); ?>" 
                 class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm w-full md:w-56">
          
          <select name="month" class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm">
            <option value="">All Months</option>
            <?php
            for ($m = 1; $m <= 12; $m++) {
                $month_name = date('F', mktime(0, 0, 0, $m, 1));
                $selected = ($month_filter == $m) ? 'selected' : '';
                echo "<option value=\"$m\" $selected>$month_name</option>";
            }
            ?>
          </select>
          
          <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow">Filter</button>
          
          <?php if ($search_query || $month_filter): ?>
              <a href="iar_list.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg text-sm" title="Reset">🔄</a>
          <?php endif; ?>


        </form>
      </div>
    </div>

    <div class="relative overflow-x-auto shadow-sm border border-gray-200 rounded-lg max-h-[600px] overflow-y-auto">
      <table class="min-w-full table-auto text-sm text-left">
        <thead class="bg-gray-50 text-gray-700 font-semibold uppercase tracking-wider sticky top-0 z-10 shadow-sm">
          <tr>
            <th class="px-4 py-3 border-b bg-gray-50">IAR Number</th>
            <th class="px-4 py-3 border-b bg-gray-50">IAR Date</th>
            <th class="px-4 py-3 border-b bg-gray-50">Invoice No.</th>
            <th class="px-4 py-3 border-b bg-gray-50">Invoice Date</th>
            <th class="px-4 py-3 border-b bg-gray-50">Date Inspected</th>
            <th class="px-4 py-3 border-b bg-gray-50">Date Received</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-center">Status</th>
            <th class="px-4 py-3 border-b bg-gray-50">Linked PO</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr class="hover:bg-blue-50 transition">
              <td class="px-4 py-3 font-bold text-purple-700 whitespace-nowrap">
                <?php echo htmlspecialchars($row['iar_number']); ?>
              </td>
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                <?php echo !empty($row['iar_date']) ? date('M d, Y', strtotime($row['iar_date'])) : '—'; ?>
              </td>
              <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                <?php echo htmlspecialchars($row['invoice_number']); ?>
              </td>
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                <?php echo !empty($row['invoice_date']) ? date('M d, Y', strtotime($row['invoice_date'])) : '—'; ?>
              </td>
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                <?php echo !empty($row['date_inspected']) ? date('M d, Y', strtotime($row['date_inspected'])) : '—'; ?>
              </td>
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                <?php echo !empty($row['date_received']) ? date('M d, Y', strtotime($row['date_received'])) : '—'; ?>
              </td>
              <td class="px-4 py-3 text-center">
                <?php if($row['status'] === 'Complete'): ?>
                  <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 border border-green-200">Complete</span>
                <?php else: ?>
                  <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700 border border-yellow-200">Lacking</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3">
                <?php if ($row['po_number']): ?>
                    <div class="flex flex-col">
                        <span class="text-xs font-mono font-bold text-gray-900 bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200 w-fit">
                            <?php echo htmlspecialchars($row['po_number']); ?>
                        </span>
                        <span class="text-[11px] text-gray-500 truncate max-w-[120px]">
                            <?php echo htmlspecialchars($row['supplier']); ?>
                        </span>
                    </div>
                <?php else: ?>
                    <span class="text-gray-400">—</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                    <a href="iar_edit.php?id=<?php echo $row['id']; ?>" class="w-8 h-8 flex items-center justify-center bg-yellow-500 hover:bg-yellow-600 text-white rounded shadow-sm transition" title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    </a>
                    
                    <a href="iar_delete.php?id=<?php echo $row['id']; ?>" class="w-8 h-8 flex items-center justify-center bg-gray-500 hover:bg-gray-600 text-white rounded shadow-sm transition" title="Delete" onclick="return confirm('Delete this IAR?')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="text-center py-10 text-gray-400 italic">No inspection reports found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-4 flex flex-col md:flex-row items-center justify-between mt-4 border-t border-gray-100">
      <div class="flex-1 text-sm text-gray-500 mb-4 md:mb-0">
        Showing <span class="font-bold"><?php echo $offset + 1; ?></span> to 
        <span class="font-bold"><?php echo min($offset + $records_per_page, $total_records); ?></span> of 
        <span class="font-bold"><?php echo $total_records; ?></span> results
      </div>

      <div class="flex-none flex items-center justify-center space-x-1">
        <?php if ($current_page > 1): ?>
          <a href="<?php echo build_pagination_url($current_page - 1); ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Previous</a>
        <?php endif; ?>

        <?php 
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
          <a href="<?php echo build_pagination_url($i); ?>" 
             class="px-3 py-1 border rounded-md transition <?php echo ($i == $current_page) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-100 text-gray-700'; ?>">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
          <a href="<?php echo build_pagination_url($current_page + 1); ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Next</a>
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