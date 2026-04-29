<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$year  = date('Y');
$month = date('m');
$prefix = "IAR-$year-$month";
$stmt = $conn->prepare("SELECT iar_number FROM iars WHERE iar_number LIKE CONCAT(?, '%') ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $prefix);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$nextNumber = $row ? str_pad((int)substr($row['iar_number'], -3) + 1, 3, '0', STR_PAD_LEFT) : '001';
$iar_number = "$prefix-$nextNumber";

$selected_po_id = $_GET['po_id'] ?? null;
$pos = $conn->query("SELECT po.id, po.po_number, po.supplier, pr.pr_number FROM purchase_orders po LEFT JOIN purchase_requests pr ON po.pr_id = pr.id ORDER BY po.id DESC");
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $iar_number_post = $_POST['iar_number'];
    $po_id_post      = (int)$_POST['po_id'];
    $pr_id_row = $conn->query("SELECT pr_id FROM purchase_orders WHERE id=$po_id_post")->fetch_assoc();
    $pr_id_post = (int)($pr_id_row['pr_id'] ?? 0);
    $stmt = $conn->prepare("INSERT INTO iars (iar_number, iar_date, invoice_number, invoice_date, date_inspected, date_received, status, po_id, pr_id) VALUES (?, NULL, NULL, NULL, NULL, NULL, 'Pending', ?, ?)");
    $stmt->bind_param("sii", $iar_number_post, $po_id_post, $pr_id_post);
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        header("Location: iar_view.php?id=$new_id");
        exit;
    } else {
        $error_message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<main class="flex-1 p-6">
<div class="max-w-xl mx-auto bg-white shadow rounded-xl p-6">
  <h2 class="text-xl font-bold text-gray-700 mb-1">New Inspection &amp; Acceptance Report (IAR)</h2>
  <p class="text-sm text-gray-500 mb-5">Enter the IAR Number and link a Purchase Order. All details are filled inside the document view.</p>
  <?php if ($error_message): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error_message; ?></div>
  <?php endif; ?>
  <form method="post" class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700">IAR Number</label>
      <input type="text" value="<?php echo $iar_number; ?>" readonly class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100 text-gray-600 font-mono">
      <input type="hidden" name="iar_number" value="<?php echo $iar_number; ?>">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Linked Purchase Order <span class="text-red-500">*</span></label>
      <select name="po_id" required class="mt-1 block w-full border rounded px-3 py-2 focus:ring-2 focus:ring-purple-400">
        <option value="">-- Select Purchase Order --</option>
        <?php while ($po = $pos->fetch_assoc()): ?>
        <option value="<?php echo $po['id']; ?>" <?php if($selected_po_id == $po['id']) echo 'selected'; ?>>
          <?php echo htmlspecialchars($po['po_number'] . ' — ' . $po['supplier'] . ($po['pr_number'] ? ' (PR: '.$po['pr_number'].')' : '')); ?>
        </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="bg-blue-50 border border-blue-200 rounded p-3 text-sm text-blue-800">
      <i class="fas fa-info-circle mr-1"></i> After saving, you will be taken to the IAR document view to fill in all details — dates, invoice, inspection status, and items.
    </div>
    <div class="flex justify-end gap-2 pt-2">
      <a href="iar_list.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">Cancel</a>
      <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded shadow font-semibold">
        <i class="fas fa-plus mr-1"></i> Create IAR
      </button>
    </div>
  </form>
</div>
</main>
<?php ob_end_flush(); ?>
