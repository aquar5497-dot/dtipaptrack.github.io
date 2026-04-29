<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ===============================
   HANDLE DELETE (SAFE)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $id = intval($_POST['id']);
    $conn->begin_transaction();

    try {
        $stmt_get = $conn->prepare(
            "SELECT total_amount, parent_id 
             FROM purchase_requests 
             WHERE id = ? AND pr_type = 'SUB'"
        );
        $stmt_get->bind_param('i', $id);
        $stmt_get->execute();
        $amount_data = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if ($amount_data && $amount_data['parent_id']) {
            $stmt_update = $conn->prepare(
                "UPDATE purchase_requests 
                 SET total_amount = total_amount - ? 
                 WHERE id = ?"
            );
            $stmt_update->bind_param('di', $amount_data['total_amount'], $amount_data['parent_id']);
            $stmt_update->execute();
            $stmt_update->close();
        }

        $stmt_delete = $conn->prepare("DELETE FROM purchase_requests WHERE id = ? AND pr_type = 'SUB'");
        $stmt_delete->bind_param('i', $id);
        $stmt_delete->execute();
        $stmt_delete->close();

        $conn->commit();
        echo "<script>alert('Sub-PR deleted successfully.'); window.location='subpr_list.php';</script>";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        echo "<script>alert('Error deleting Sub-PR.');</script>";
    }
    exit;
}

/* ===============================
   PAGINATION & FILTERS
================================ */
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$search_query = $_GET['search'] ?? '';
$month_filter = $_GET['month'] ?? '';

$base_where = "WHERE sub.pr_type = 'SUB'";
$params = [];
$types = '';

if (!empty($search_query)) {
    $base_where .= " AND (sub.pr_number LIKE ? OR parent.pr_number LIKE ? OR sub.purpose LIKE ? OR sub.entity_name LIKE ? OR sub.fund_cluster LIKE ?)";
    $search_term = '%' . $search_query . '%';
    array_push($params, $search_term, $search_term, $search_term, $search_term, $search_term);
    $types .= 'sssss';
}

if (!empty($month_filter)) {
    $base_where .= " AND MONTH(sub.pr_date) = ?";
    $params[] = $month_filter;
    $types .= 'i';
}

// 1. Get Total Count
$count_sql = "SELECT COUNT(sub.id) AS total_records FROM purchase_requests sub LEFT JOIN purchase_requests parent ON sub.parent_id = parent.id $base_where";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total_records'];
$count_stmt->close();

$total_pages = ceil($total_records / $records_per_page);
$current_page = max(1, min($current_page, $total_pages == 0 ? 1 : $total_pages));
$offset = ($current_page - 1) * $records_per_page;

// 2. Build Data Query
$sql = "
    SELECT sub.*, parent.pr_number AS parent_pr_number
    FROM purchase_requests sub
    LEFT JOIN purchase_requests parent ON sub.parent_id = parent.id
    $base_where
    ORDER BY sub.id DESC LIMIT ? OFFSET ?
";

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
        <h2 class="text-xl font-bold text-gray-800">Sub-Purchase Requests</h2>
        <p class="text-xs text-gray-500">Linked additional requests.</p>
      </div>

      <div class="w-full md:w-auto">
        <form method="GET" class="flex flex-wrap items-center gap-2">
          <input type="search" name="search" placeholder="Search Sub-PR, Parent, Fund..." 
                 value="<?= htmlspecialchars($search_query) ?>" 
                 class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-full md:w-56">
          
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow">Filter</button>
          
          <?php if ($search_query || $month_filter): ?>
              <a href="subpr_list.php" class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-lg text-sm" title="Reset">🔄</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="relative overflow-x-auto shadow-sm border border-gray-200 rounded-lg max-h-[600px] overflow-y-auto">
      <table class="min-w-full table-auto text-sm text-left">
        <thead class="bg-gray-50 text-gray-700 font-semibold uppercase tracking-wider sticky top-0 z-10 shadow-sm">
          <tr>
            <th class="px-4 py-3 border-b bg-gray-50">Sub-PR Number</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-blue-800">Parent PR</th>
            <th class="px-4 py-3 border-b bg-gray-50">Entity</th>
            <th class="px-4 py-3 border-b bg-gray-50">Fund</th>
            <th class="px-4 py-3 border-b bg-gray-50">Purpose</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-right">Amount</th>
            <th class="px-4 py-3 border-b bg-gray-50">Date</th>
            <th class="px-4 py-3 border-b bg-gray-50 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <?php if ($result->num_rows > 0): ?>
            <?php while ($r = $result->fetch_assoc()): ?>
            <tr class="hover:bg-blue-50 transition">
              <td class="px-4 py-3 font-bold text-gray-900 whitespace-nowrap"><?= htmlspecialchars($r['pr_number']) ?></td>
              <td class="px-4 py-3">
                <span class="text-xs font-mono font-bold text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200">
                    <?= htmlspecialchars($r['parent_pr_number'] ?? '—') ?>
                </span>
              </td>
              <td class="px-4 py-3 text-gray-700 truncate max-w-[120px]" title="<?= htmlspecialchars($r['entity_name']) ?>"><?= htmlspecialchars($r['entity_name']) ?></td>
              <td class="px-4 py-3 text-gray-700 font-medium"><?= htmlspecialchars($r['fund_cluster'] ?? '—') ?></td>
              <td class="px-4 py-3 text-gray-600 italic text-xs truncate max-w-[150px]" title="<?= htmlspecialchars($r['purpose']) ?>"><?= htmlspecialchars($r['purpose']) ?></td>
              <td class="px-4 py-3 text-right font-bold text-blue-700 whitespace-nowrap">₱<?= number_format($r['total_amount'], 2) ?></td>
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= !empty($r['pr_date']) ? date('M d, Y', strtotime($r['pr_date'])) : '—'; ?></td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                    <a href="subpr_edit.php?id=<?= $r['id'] ?>" class="w-8 h-8 flex items-center justify-center bg-yellow-500 hover:bg-yellow-600 text-white rounded shadow-sm transition" title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    </a>
                    <form method="post" class="inline" onsubmit="return confirm('Delete this Sub-PR?');">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" name="delete" class="w-8 h-8 flex items-center justify-center bg-red-600 hover:bg-red-700 text-white rounded shadow-sm transition" title="Delete">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </form>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center py-10 text-gray-400 italic">No Sub-Purchase Requests found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-4 flex flex-col md:flex-row items-center justify-between mt-4 border-t border-gray-100">
      <div class="flex-1 text-sm text-gray-500">
        Showing <span class="font-bold"><?= $offset + 1 ?></span> to <span class="font-bold"><?= min($offset + $records_per_page, $total_records) ?></span> of <span class="font-bold"><?= $total_records ?></span>
      </div>
      <div class="flex-none flex items-center justify-center space-x-1">
        <?php if ($current_page > 1): ?><a href="<?= build_pagination_url($current_page - 1) ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700">Previous</a><?php endif; ?>
        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
          <a href="<?= build_pagination_url($i) ?>" class="px-3 py-1 border rounded-md transition <?= ($i == $current_page) ? 'bg-blue-600 text-white border-blue-600 font-bold' : 'bg-white hover:bg-gray-100 text-gray-700'; ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($current_page < $total_pages): ?><a href="<?= build_pagination_url($current_page + 1) ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700">Next</a><?php endif; ?>
      </div>
      <div class="hidden md:block flex-1"></div>
    </div>
    <?php endif; ?>
  </div>
</main>

<?php $stmt->close(); include 'inc/footer.php'; ?>