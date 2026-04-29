<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ── AUTO-GENERATE RFQ NUMBER ─────────────────────────────── */
$year   = date('Y');
$month  = date('m');
$prefix = "RFQ-$year-$month";
$stmt   = $conn->prepare("SELECT rfq_number FROM rfqs WHERE rfq_number LIKE CONCAT(?,'%') ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $prefix);
$stmt->execute();
$result  = $stmt->get_result()->fetch_assoc();
$nextNum = $result ? str_pad((int)substr($result['rfq_number'], -3) + 1, 3, '0', STR_PAD_LEFT) : '001';
$rfq_number = "$prefix-$nextNum";

$selected_pr_id = $_GET['pr_id'] ?? null;
$prs = $conn->query("SELECT id, pr_number FROM purchase_requests ORDER BY id DESC");

/* ── SAVE ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfq_number = $_POST['rfq_number'];
    $pr_id      = (int)$_POST['pr_id'];

    $stmt = $conn->prepare("INSERT INTO rfqs (rfq_number, pr_id) VALUES (?,?)");
    $stmt->bind_param("si", $rfq_number, $pr_id);
    $stmt->execute();
    $new_id = $conn->insert_id;

    logAudit('RFQ', 'CREATE', $new_id, $rfq_number, [], ['rfq_number' => $rfq_number, 'pr_id' => $pr_id]);
    header("Location: rfq_view.php?id=$new_id");
    exit;
}
?>
<main class="flex-1 p-6">
<div class="max-w-xl mx-auto bg-white shadow rounded-xl p-6">
  <h2 class="text-xl font-bold text-gray-700 mb-1">New Request for Quotation (RFQ)</h2>
  <p class="text-sm text-gray-500 mb-5">RFQ Date, items, and quotation details are filled in the document view after creation.</p>

  <form method="post" class="space-y-5">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">RFQ Number <span class="text-gray-400 font-normal">(auto-generated)</span></label>
      <input type="text" value="<?php echo htmlspecialchars($rfq_number); ?>" readonly
             class="block w-full border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 font-mono text-sm">
      <input type="hidden" name="rfq_number" value="<?php echo htmlspecialchars($rfq_number); ?>">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Linked Purchase Request</label>
      <select name="pr_id" required class="mt-1 block w-full border rounded-lg px-3 py-2 text-sm">
        <option value="">-- Select Purchase Request --</option>
        <?php while ($pr = $prs->fetch_assoc()): ?>
        <option value="<?php echo $pr['id']; ?>" <?php if($selected_pr_id == $pr['id']) echo 'selected'; ?>>
          <?php echo htmlspecialchars($pr['pr_number']); ?>
        </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="flex justify-end gap-2 pt-2">
      <a href="rfq_list.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">Cancel</a>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-semibold shadow">
        Create &amp; Open Document
      </button>
    </div>
  </form>
</div>
</main>
<?php ob_end_flush(); ?>
