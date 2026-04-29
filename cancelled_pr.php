<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// --- Handle Uncancel (Restoring a PR) ---
if (isset($_POST['uncancel'])) {
    $id = intval($_POST['id']);
    $stmt_pr = $conn->prepare("UPDATE purchase_requests SET status=NULL WHERE id=?");
    $stmt_pr->bind_param('i', $id);
    $stmt_pr->execute();
    $stmt_pr->close();

    $stmt_po = $conn->prepare("UPDATE purchase_orders SET status=NULL WHERE pr_id=?");
    $stmt_po->bind_param('i', $id);
    $stmt_po->execute();
    $stmt_po->close();

    echo "<script>alert('PR successfully restored.');location.href='cancelled_pr.php';</script>";
    exit;
}

// --- Handle Permanent Delete ---
if (isset($_POST['delete'])) {
    $id = intval($_POST['id']);
    $stmt_po = $conn->prepare("DELETE FROM purchase_orders WHERE pr_id=?");
    $stmt_po->bind_param('i', $id);
    $stmt_po->execute();
    $stmt_po->close();

    $stmt_pr = $conn->prepare("DELETE FROM purchase_requests WHERE id=?");
    $stmt_pr->bind_param('i', $id);
    $stmt_pr->execute();
    $stmt_pr->close();
    
    echo "<script>alert('PR permanently deleted.');location.href='cancelled_pr.php';</script>";
    exit;
}

// --- Pagination Setup ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get search value
$search_query = $_GET['search'] ?? '';

// --- 1. Build Base WHERE for Count and Data ---
$base_where = "WHERE status='Cancelled'";
$params = [];
$types = '';

if (!empty($search_query)) {
    $base_where .= " AND (pr_number LIKE ? OR purpose LIKE ? OR entity_name LIKE ?)";
    $search_term = "%" . $search_query . "%";
    array_push($params, $search_term, $search_term, $search_term);
    $types .= 'sss';
}

// --- 2. Get Total Count ---
$count_sql = "SELECT COUNT(id) AS total_records FROM purchase_requests $base_where";
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

// --- 3. Build Final Query ---
$sql = "SELECT * FROM purchase_requests $base_where ORDER BY id DESC LIMIT ? OFFSET ?";
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
      <div>
        <h2 class="text-xl font-bold text-gray-800">Cancelled Purchase Requests</h2>
        <p class="text-xs text-gray-500">Restore or permanently remove items from the archive.</p>
      </div>

      <div class="w-full md:w-auto">
        <form method="GET" action="" class="flex items-center gap-2">
          <input type="search" name="search" placeholder="Search PR No, Purpose..." 
                 value="<?php echo htmlspecialchars($search_query); ?>" 
                 class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm w-full md:w-64">
          
          <button type="submit" class="bg-gray-800 hover:bg-black text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow">Search</button>
          
          <?php if ($search_query): ?>
              <a href="cancelled_pr.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg text-sm" title="Reset">🔄</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="relative overflow-x-auto shadow-sm border border-gray-200 rounded-lg max-h-[600px] overflow-y-auto">
      <table class="min-w-full table-auto text-sm text-left">
        <thead class="bg-gray-50 text-gray-700 font-semibold uppercase tracking-wider sticky top-0 z-10 shadow-sm">
          <tr>
            <th class="px-4 py-3 border-b bg-gray-50">PR Number</th>
            <th class="px-4 py-3 border-b bg-gray-50">Purpose</th>
            <th class="px-4 py-3 border-b bg-gray-50">Entity Name</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-right">Total Amount</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr class="hover:bg-red-50 transition">
              <td class="px-4 py-3 font-bold text-red-700 whitespace-nowrap">
                <?php echo htmlspecialchars($row['pr_number']); ?>
              </td>
              <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="<?php echo htmlspecialchars($row['purpose']); ?>">
                <?php echo htmlspecialchars($row['purpose']); ?>
              </td>
              <td class="px-4 py-3 text-gray-700">
                <?php echo htmlspecialchars($row['entity_name']); ?>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">
                ₱<?php echo number_format($row['total_amount'], 2); ?>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                    <form method="post" class="inline">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <button type="submit" name="uncancel" class="w-8 h-8 flex items-center justify-center bg-green-500 hover:bg-green-600 text-white rounded shadow-sm transition" title="Restore / Uncancel">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        </button>
                    </form>
                    
                    <form method="post" class="inline" onsubmit="return confirm('Permanently delete this PR? This cannot be undone.');">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <button type="submit" name="delete" class="w-8 h-8 flex items-center justify-center bg-red-600 hover:bg-red-700 text-white rounded shadow-sm transition" title="Permanent Delete">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </form>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center py-10 text-gray-400 italic">No cancelled purchase requests found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-4 flex flex-col md:flex-row items-center justify-between mt-4 border-t border-gray-100">
      <div class="flex-1 text-sm text-gray-500">
        Showing <span class="font-bold"><?php echo $offset + 1; ?></span> to 
        <span class="font-bold"><?php echo min($offset + $records_per_page, $total_records); ?></span> of 
        <span class="font-bold"><?php echo $total_records; ?></span> results
      </div>

      <div class="flex-none flex items-center justify-center space-x-1">
        <?php if ($current_page > 1): ?>
          <a href="<?php echo build_pagination_url($current_page - 1); ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700">Previous</a>
        <?php endif; ?>

        <?php 
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
          <a href="<?php echo build_pagination_url($i); ?>" 
             class="px-3 py-1 border rounded-md transition <?php echo ($i == $current_page) ? 'bg-red-600 text-white border-red-600 font-bold' : 'bg-white hover:bg-gray-100 text-gray-700'; ?>">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
          <a href="<?php echo build_pagination_url($current_page + 1); ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700">Next</a>
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