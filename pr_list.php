<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// Ensure status columns exist
$conn->query("ALTER TABLE purchase_requests ADD COLUMN IF NOT EXISTS status VARCHAR(50) NULL");
$conn->query("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS status VARCHAR(50) NULL");

// --- FILTER HANDLING ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$month_filter = isset($_GET['month']) ? $_GET['month'] : '';
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;
$parent_pr = null;

// --- PAGINATION LOGIC ---
$limit = 8; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- QUERY ---
if ($parent_id > 0) {
    $where = "WHERE parent_id = $parent_id AND pr_type = 'SUB'";
    $parent_res = $conn->query("SELECT pr_number FROM purchase_requests WHERE id = $parent_id");
    if ($parent_res && $parent_res->num_rows > 0) {
        $parent_pr = $parent_res->fetch_assoc();
    }
} else {
    $where = "WHERE parent_id IS NULL";
}

if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (pr_number LIKE '%$s%' OR purpose LIKE '%$s%' OR entity_name LIKE '%$s%')";
}
if ($month_filter !== '') {
    $where .= " AND DATE_FORMAT(pr_date, '%Y-%m') = '$month_filter'";
}

// FETCH TOTAL ROW COUNT
$count_query = "SELECT COUNT(*) AS total FROM purchase_requests $where";
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$pagination_params_array = [];
if ($parent_id > 0) $pagination_params_array['parent_id'] = $parent_id;
if ($search !== '') $pagination_params_array['search'] = $search;
if ($month_filter !== '') $pagination_params_array['month'] = $month_filter;
$pagination_params = http_build_query($pagination_params_array);

$res = $conn->query("SELECT * FROM purchase_requests $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
?>

<main class="flex-1 p-4 md:p-6 bg-blue-100 min-h-screen">
    <div class="bg-white p-4 md:p-6 rounded-xl shadow-lg max-w-full">
        
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-bold text-gray-800">
                    <?= $parent_pr ? "Sub-PRs for " . htmlspecialchars($parent_pr['pr_number']) : "Purchase Requests" ?>
                </h2>
                <?php if ($parent_pr): ?>
                    <a href="pr_list.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded-md text-sm transition">&larr; Back</a>
                <?php endif; ?>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2">
                <?php if ($parent_id > 0): ?>
                    <input type="hidden" name="parent_id" value="<?= $parent_id ?>">
                <?php endif; ?>
                
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" class="border rounded-md px-3 py-2 text-sm w-full md:w-48 focus:ring-2 focus:ring-blue-500 outline-none">
                <input type="month" name="month" value="<?= htmlspecialchars($month_filter) ?>" class="border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition">Filter</button>

                <?php if ($search || $month_filter): ?>
                    <a href="pr_list.php<?= $parent_id > 0 ? '?parent_id='.$parent_id : '' ?>" class="bg-gray-200 hover:bg-gray-300 px-3 py-2 rounded-md text-sm" title="Reset">🔄</a>
                <?php endif; ?>


            </form>
        </div>

        <div class="relative overflow-x-auto shadow-sm border border-gray-200 rounded-lg max-h-[600px] overflow-y-auto">
            <table class="min-w-full table-auto text-sm text-left">
                <thead class="bg-gray-50 text-gray-700 font-semibold uppercase tracking-wider sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="px-4 py-3 border-b bg-gray-50">PR Number</th>
                        <th class="px-4 py-3 border-b bg-gray-50">Date</th>
                        <th class="px-4 py-3 border-b bg-gray-50">Purpose</th>
                        <th class="px-4 py-3 border-b bg-gray-50">Entity</th>
                        <th class="px-4 py-3 border-b bg-gray-50">Fund</th>
                        <th class="px-4 py-3 border-b bg-gray-50 text-right">Amount</th>
                        <th class="px-4 py-3 border-b bg-gray-50 text-center">Status</th>
                        <th class="px-4 py-3 border-b bg-gray-50 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if ($res && $res->num_rows > 0): ?>
                        <?php while ($r = $res->fetch_assoc()): 
                            $status = $r['status'] ?? 'Active';
                            $badgeColor = $status === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';
                            
                            $has_sub = $conn->query("SELECT COUNT(*) AS c FROM purchase_requests WHERE parent_id = {$r['id']} AND pr_type = 'SUB'")->fetch_assoc()['c'] > 0;
                            // Note: Per your instructions, SUBPR and specific PR labeling are handled in overall_list, but checking for flag here for UI badges
                            $has_late = $conn->query("SELECT COUNT(*) c FROM purchase_requests WHERE parent_id = {$r['id']} AND pr_number REGEXP '-[A-Z]$'")->fetch_assoc()['c'] > 0;
                        ?>
                        <tr class="hover:bg-blue-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">
                                <?= htmlspecialchars($r['pr_number']) ?>
                                <div class="mt-1 flex gap-1">
                                    <?php if ($has_sub): ?>
                                        <a href="pr_list.php?parent_id=<?= $r['id'] ?>" class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] uppercase font-bold">Sub-PR</a>
                                    <?php endif; ?>
                                    <?php if ($has_late): ?>
                                        <a href="pr_inserted_list.php?parent_id=<?= $r['id'] ?>" class="bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded text-[10px] uppercase font-bold">Late-PR</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($r['pr_date'])) ?></td>
                            <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($r['purpose']) ?>"><?= htmlspecialchars($r['purpose']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($r['entity_name']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($r['fund_cluster']) ?></td>
                            <td class="px-4 py-3 text-right text-blue-700 font-bold whitespace-nowrap">₱<?= number_format($r['total_amount'], 2) ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $badgeColor ?>">
                                    <?= $status ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="pr_edit.php?id=<?= $r['id'] ?>" class="w-8 h-8 flex items-center justify-center bg-yellow-500 hover:bg-yellow-600 text-white rounded shadow-sm transition" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </a>
                                    
                                    <a href="report.php?pr_id=<?= $r['id'] ?>" class="w-8 h-8 flex items-center justify-center bg-indigo-500 hover:bg-indigo-600 text-white rounded shadow-sm transition" title="View Report">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    </a>

                                    <?php if ($parent_id == 0 && !$has_sub && $status != 'Cancelled'): ?>
                                        <a href="subpr_add.php?parent_id=<?= $r['id'] ?>" class="w-8 h-8 flex items-center justify-center bg-teal-500 hover:bg-teal-600 text-white rounded shadow-sm transition" title="Add Sub-PR">
                                            <span class="text-[10px] font-bold">SUB</span>
                                        </a>
                                    <?php endif; ?>

                                    <form action="pr_delete.php" method="post" class="inline" onsubmit="return confirm('Delete this PR?');">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <button class="w-8 h-8 flex items-center justify-center bg-gray-500 hover:bg-gray-600 text-white rounded shadow-sm transition" title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </form>

                                    <?php if ($status != 'Cancelled'): ?>
                                        <form action="cancel_pr.php" method="post" class="inline" onsubmit="return confirm('Cancel this PR?');">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded shadow-sm transition" title="Cancel">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-10 text-gray-400 italic">No Purchase Requests found.</td>
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
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= $pagination_params ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Previous</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=<?= $i ?>&<?= $pagination_params ?>" 
                       class="px-3 py-1 border rounded-md transition <?= ($i == $page) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-100 text-gray-700'; ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= $pagination_params ?>" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100 text-gray-700 transition">Next</a>
                <?php endif; ?>
            </div>
            <div class="hidden md:block flex-1"></div>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'inc/footer.php'; ?>