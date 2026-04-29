<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ── AUTO-GENERATE PO NUMBER ──────────────────────────────── */
$year   = date('Y');
$month  = date('m');
$prefix = "PO-$year-$month";
$stmt   = $conn->prepare("SELECT po_number FROM purchase_orders WHERE po_number LIKE CONCAT(?,'%') ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $prefix);
$stmt->execute();
$result  = $stmt->get_result()->fetch_assoc();
$nextNum = $result ? str_pad((int)substr($result['po_number'], -3) + 1, 3, '0', STR_PAD_LEFT) : '001';
$po_number = "$prefix-$nextNum";

$selected_pr_id = $_GET['pr_id'] ?? null;
$prs = $conn->query("SELECT id, pr_number FROM purchase_requests ORDER BY id DESC");

/* ── SAVE ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_number = $_POST['po_number'];
    $pr_id     = (int)$_POST['pr_id'];

    $stmt = $conn->prepare("INSERT INTO purchase_orders (po_number, pr_id, status) VALUES (?,?,'draft')");
    $stmt->bind_param("si", $po_number, $pr_id);
    $stmt->execute();
    $new_id = $conn->insert_id;

    logAudit('PO', 'CREATE', $new_id, $po_number, [], ['po_number' => $po_number, 'pr_id' => $pr_id]);
    header("Location: po_view.php?id=$new_id");
    exit;
}
?>
<main class="flex-1 p-6">
<div class="max-w-xl mx-auto bg-white shadow rounded-xl p-6">
  <h2 class="text-xl font-bold text-gray-700 mb-1">New Purchase Order (PO)</h2>
  <p class="text-sm text-gray-500 mb-5">PO Date, Supplier, items, and other details are filled in the document view after creation.</p>

  <form method="post" class="space-y-5">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">PO Number <span class="text-gray-400 font-normal">(auto-generated)</span></label>
      <input type="text" value="<?php echo htmlspecialchars($po_number); ?>" readonly
             class="block w-full border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 font-mono text-sm">
      <input type="hidden" name="po_number" value="<?php echo htmlspecialchars($po_number); ?>">
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
      <a href="po_list.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">Cancel</a>
      <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-semibold shadow">
        Create &amp; Open Document
      </button>
    </div>
  </form>
</div>
</main>
<?php ob_end_flush(); ?>
