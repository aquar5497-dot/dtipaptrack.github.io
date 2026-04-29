<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// --- 1. Filter Handling ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$month_filter = isset($_GET['month']) ? $_GET['month'] : '';

// --- 2. Pagination Setup ---
$limit = 8; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- 3. Shared Joins & Global Where Clause ---
$shared_joins = " FROM purchase_requests pr
                  LEFT JOIN rfqs rfq ON pr.id = rfq.pr_id
                  LEFT JOIN purchase_orders po ON pr.id = po.pr_id
                  LEFT JOIN iars iar ON po.id = iar.po_id
                  LEFT JOIN disbursement_vouchers dv ON pr.id = dv.pr_id ";

// Filter Constraints: Exclude SUBPRs and Suffix labeling (A, B, C)
$where = " WHERE pr.pr_type != 'SUB' 
           AND pr.pr_number NOT LIKE 'SUBPR%' 
           AND pr.pr_number NOT REGEXP '-[A-Z]$' ";

if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (pr.pr_number LIKE '%$s%' 
                OR po.po_number LIKE '%$s%' 
                OR rfq.rfq_number LIKE '%$s%' 
                OR dv.dv_number LIKE '%$s%' 
                OR pr.purpose LIKE '%$s%') ";
}

if ($month_filter !== '') {
    $where .= " AND DATE_FORMAT(pr.pr_date, '%Y-%m') = '$month_filter' ";
}

// --- 4. Fetch Financial Totals for Header Summary ---
$summary_sql = "SELECT SUM(pr.total_amount) as total_pr, SUM(po.total_amount) as total_po " . $shared_joins . $where;
$summary_res = $conn->query($summary_sql);
$totals = $summary_res->fetch_assoc();
$grand_pr = $totals['total_pr'] ?? 0;
$grand_po = $totals['total_po'] ?? 0;
$variance = $grand_pr - $grand_po;

// --- 5. Pagination Calculation ---
$count_sql = "SELECT COUNT(pr.id) as total " . $shared_joins . $where;
$count_result = $conn->query($count_sql);
$total_rows = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);
$page = max(1, min($page, $total_pages == 0 ? 1 : $total_pages));
$offset = ($page - 1) * $limit;

// --- 6. Get Main Table Data ---
$sql = "SELECT 
            pr.id as pr_id, pr.pr_number, pr.pr_date, pr.total_amount as pr_amount,
            rfq.rfq_number, rfq.rfq_date,
            po.po_number, po.po_date, po.total_amount as po_amount,
            iar.iar_number, iar.iar_date, iar.status as iar_status,
            dv.dv_number, dv.dv_date, dv.status as dv_status " 
        . $shared_joins . $where . " ORDER BY pr.id DESC LIMIT $limit OFFSET $offset";

$res = $conn->query($sql);

function build_pagination_url($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return '?' . http_build_query($params);
}
?>

<main class="flex-1 p-6 overflow-y-auto bg-slate-50">
    <div class="max-w-full mx-auto">
        <div class="bg-white p-3 rounded-xl shadow-md mb-4 border border-gray-100">
    <div class="flex flex-col lg:flex-row justify-between items-center gap-3">
        <div>
            <h2 class="text-xl font-extrabold text-gray-800 leading-tight">Procurement Master List</h2>
            <p class="text-[10px] text-gray-500 font-medium uppercase tracking-wider">End-to-End Tracking Data Visualization</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" class="flex items-center gap-1.5">
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" 
                       class="border rounded-lg px-3 py-1.5 text-xs w-40 focus:ring-2 focus:ring-blue-500 outline-none">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-xs font-bold transition">Filter</button>
                <a href="overall_list.php" class="bg-gray-100 p-1.5 rounded-lg hover:bg-gray-200 transition text-xs" title="Reset">🔄</a>
            </form>
            <a href="export_overall.php?search=<?= urlencode($search) ?>&month=<?= $month_filter ?>" 
               class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center shadow-sm">
                📊 Export
            </a>
        </div>
    </div>

    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mt-4 border-t pt-3 gap-3">
        <div class="flex flex-wrap gap-3">
            <div class="flex items-center text-[10px] font-bold text-gray-500"><span class="w-4 h-4 bg-blue-500 rounded-full mr-1.5"></span> 1. PR</div>
            <div class="flex items-center text-[10px] font-bold text-gray-500"><span class="w-4 h-4 bg-emerald-500 rounded-full mr-1.5"></span> 2. RFQ</div>
            <div class="flex items-center text-[10px] font-bold text-gray-500"><span class="w-4 h-4 bg-amber-500 rounded-full mr-1.5"></span> 3. PO</div>
            <div class="flex items-center text-[10px] font-bold text-gray-500"><span class="w-4 h-4 bg-purple-500 rounded-full mr-1.5"></span> 4. IAR</div>
            <div class="flex items-center text-[10px] font-bold text-gray-500"><span class="w-4 h-4 bg-rose-500 rounded-full mr-1.5"></span> 5. DV</div>
        </div>

        <div class="flex flex-wrap gap-2 w-full xl:w-auto">
            <div class="flex-1 md:flex-none bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                <span class="block text-[9px] uppercase font-bold text-blue-500 leading-none mb-1">Total PR</span>
                <span class="text-xs font-black text-blue-800">₱<?= number_format($grand_pr, 2) ?></span>
            </div>
            <div class="flex-1 md:flex-none bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-100">
                <span class="block text-[9px] uppercase font-bold text-amber-600 leading-none mb-1">Total PO</span>
                <span class="text-xs font-black text-amber-900">₱<?= number_format($grand_po, 2) ?></span>
            </div>
            <div class="flex-1 md:flex-none <?= $variance >= 0 ? 'bg-emerald-50 border-emerald-100' : 'bg-red-50 border-red-100' ?> px-3 py-1.5 rounded-lg border">
                <span class="block text-[9px] uppercase font-bold <?= $variance >= 0 ? 'text-emerald-600' : 'text-red-600' ?> leading-none mb-1">Variance</span>
                <span class="text-xs font-black <?= $variance >= 0 ? 'text-emerald-800' : 'text-red-800' ?>">
                    ₱<?= number_format($variance, 2) ?>
                </span>
            </div>
        </div>
    </div>
</div>

        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto overflow-y-auto max-h-[580px] relative">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="sticky top-0 z-20 shadow-sm uppercase tracking-wider text-center text-white font-bold">
                        <tr>
                            <th colspan="2" class="bg-blue-600 py-3 border-r border-blue-500">Purchase Request</th>
                            <th class="bg-emerald-600 py-3 border-r border-emerald-500">RFQ</th>
                            <th colspan="2" class="bg-amber-600 py-3 border-r border-amber-500">Purchase Order</th>
                            <th colspan="2" class="bg-purple-600 py-3 border-r border-purple-500 text-center">IAR</th>
                            <th colspan="2" class="bg-rose-600 py-3 text-center">DV</th>
                        </tr>
                        <tr class="bg-gray-100 text-gray-600 border-b border-gray-200 font-bold sticky top-[44px] z-20 shadow-sm">
                            <th class="px-3 py-2 border-r">PR Number</th>
                            <th class="px-3 py-2 border-r text-right">Amount</th>
                            <th class="px-3 py-2 border-r">RFQ No.</th>
                            <th class="px-3 py-2 border-r">PO Number</th>
                            <th class="px-3 py-2 border-r text-right">Amount</th>
                            <th class="px-3 py-2 border-r">IAR No.</th>
                            <th class="px-3 py-2 border-r text-center">Status</th>
                            <th class="px-3 py-2 border-r">DV No.</th>
                            <th class="px-3 py-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($res && $res->num_rows > 0): ?>
                            <?php while ($r = $res->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 py-4 border-r bg-blue-50/30">
                                        <div class="font-bold text-blue-700"><?= htmlspecialchars($r['pr_number']) ?></div>
                                        <div class="text-[10px] text-gray-400"><?= date('M d, Y', strtotime($r['pr_date'])) ?></div>
                                    </td>
                                    <td class="px-3 py-4 border-r bg-blue-50/30 text-right font-mono font-semibold">
                                        <?= number_format($r['pr_amount'], 2) ?>
                                    </td>
                                    <td class="px-3 py-4 border-r bg-emerald-50/30 font-medium text-emerald-700">
                                        <?php if ($r['rfq_number']): ?>
                                            <div class="font-bold"><?= htmlspecialchars($r['rfq_number']) ?></div>
                                            <div class="text-[10px] text-gray-400"><?= date('M d, Y', strtotime($r['rfq_date'])) ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-300 italic">No RFQ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-4 border-r bg-amber-50/30 font-bold text-amber-800">
                                        <?php if ($r['po_number']): ?>
                                            <div class="font-bold"><?= htmlspecialchars($r['po_number']) ?></div>
                                            <div class="text-[10px] text-gray-400"><?= date('M d, Y', strtotime($r['po_date'])) ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-300 italic">No PO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-4 border-r bg-amber-50/30 text-right font-mono text-amber-900">
                                        <?= $r['po_amount'] ? number_format($r['po_amount'], 2) : '—' ?>
                                    </td>
                                    <td class="px-3 py-4 border-r bg-purple-50/30 font-medium text-purple-700">
                                        <?php if ($r['iar_number']): ?>
                                            <div class="font-bold"><?= htmlspecialchars($r['iar_number']) ?></div>
                                            <div class="text-[10px] text-gray-400"><?= date('M d, Y', strtotime($r['iar_date'])) ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-300 italic">---</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-4 border-r bg-purple-50/30 text-center">
                                        <?php if($r['iar_status']): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $r['iar_status'] == 'Complete' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                                                <?= $r['iar_status'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-4 border-r bg-rose-50/30 font-bold text-rose-700">
                                        <?php if ($r['dv_number']): ?>
                                            <div class="font-bold"><?= htmlspecialchars($r['dv_number']) ?></div>
                                            <div class="text-[10px] text-gray-400"><?= date('M d, Y', strtotime($r['dv_date'])) ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-300 italic">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-4 bg-rose-50/30 text-center">
                                        <?php if($r['dv_status']): ?>
                                            <span class="px-2 py-1 rounded-full text-[10px] font-bold shadow-sm <?= $r['dv_status'] == 'Complete' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600' ?>">
                                                <?= $r['dv_status'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="py-10 text-center text-gray-400 italic bg-white text-sm">No records found matching your criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="p-4 bg-gray-50 border-t flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="text-xs text-gray-500 font-medium">Showing page <?= $page ?> of <?= $total_pages ?></div>
                    <div class="flex items-center space-x-1">
                        <?php if($page > 1): ?>
                            <a href="<?= build_pagination_url($page - 1) ?>" class="px-3 py-1 text-xs font-semibold rounded border bg-white text-gray-700 hover:bg-gray-100 border-gray-300 transition">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="<?= build_pagination_url($i) ?>" 
                               class="px-3 py-1 text-xs font-bold rounded border transition <?= $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if($page < $total_pages): ?>
                            <a href="<?= build_pagination_url($page + 1) ?>" class="px-3 py-1 text-xs font-semibold rounded border bg-white text-gray-700 hover:bg-gray-100 border-gray-300 transition">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'inc/footer.php'; ?>