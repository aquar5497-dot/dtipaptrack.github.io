<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ── AUTO-GENERATE PR NUMBER ─────────────────────────────────── */
$year   = date('Y');
$month  = date('m');
$prefix = "PR-$year-$month";

$stmt = $conn->prepare("SELECT pr_number FROM purchase_requests WHERE pr_number LIKE CONCAT(?,'%') ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $prefix);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$nextNum = $result ? str_pad((int)substr($result['pr_number'], -3) + 1, 3, '0', STR_PAD_LEFT) : '001';
$pr_number = "$prefix-$nextNum";

/* ── SAVE ────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pr_number = $_POST['pr_number'];
    $pr_type   = $_POST['pr_type'] ?? 'regular';

    $stmt = $conn->prepare("INSERT INTO purchase_requests (pr_number, pr_type, status) VALUES (?,?,'draft')");
    $stmt->bind_param("ss", $pr_number, $pr_type);
    $stmt->execute();
    $new_id = $conn->insert_id;

    logAudit('PR', 'CREATE', $new_id, $pr_number, [], ['pr_number' => $pr_number]);
    header("Location: pr_view.php?id=$new_id");
    exit;
}
?>

<main class="flex-1 p-6">
  <div class="max-w-xl mx-auto bg-white shadow rounded-xl p-6">
    <h2 class="text-xl font-bold text-gray-700 mb-1">New Purchase Request</h2>
    <p class="text-sm text-gray-500 mb-5">A PR number will be generated. All details are filled in the document view after creation.</p>

    <form method="post" class="space-y-5">

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Request Type</label>
        <div class="flex gap-6">
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="radio" name="pr_type" value="regular" checked onchange="handleRequestType(this.value)">
            <span class="text-sm">Regular Purchase Request</span>
          </label>
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="radio" name="pr_type" value="payroll" onchange="handleRequestType(this.value)">
            <span class="text-sm">Payroll Action Request</span>
          </label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">PR Number <span class="text-gray-400 font-normal">(auto-generated)</span></label>
        <input type="text" value="<?php echo htmlspecialchars($pr_number); ?>" readonly
               class="block w-full border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 text-gray-700 font-mono text-sm">
        <input type="hidden" name="pr_number" value="<?php echo htmlspecialchars($pr_number); ?>">
        <p class="text-xs text-gray-400 mt-1">PR Date, Entity Name, Fund Cluster, Items and other details are filled in the document view.</p>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <a href="pr_list.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">Cancel</a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-semibold shadow">
          Create &amp; Open Document
        </button>
      </div>
    </form>
  </div>
</main>

<script>
function handleRequestType(val) {
  if (val === 'payroll') window.location.href = 'payroll_add.php';
}
</script>
<?php ob_end_flush(); ?>
