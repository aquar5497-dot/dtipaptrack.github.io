<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
require 'inc/helpers.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$id = $_GET['id'] ?? 0;
$res = $conn->query("SELECT * FROM purchase_orders WHERE id=$id");
$po = $res->fetch_assoc();

// Fetch PRs for dropdown
$prs = $conn->query("SELECT id, pr_number FROM purchase_requests WHERE is_cancelled=0 ORDER BY id DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = $_POST['po_date'];
  $supplier = $_POST['supplier'];
  $award = $_POST['date_of_award'];
  $amount = $_POST['total_amount'];
  $pr_id = $_POST['pr_id'];

  $stmt = $conn->prepare("UPDATE purchase_orders SET po_date=?, supplier=?, date_of_award=?, total_amount=?, pr_id=? WHERE id=?");
  $stmt->bind_param("sssdis",$date,$supplier,$award,$amount,$pr_id,$id);
  $stmt->execute();

  header("Location: po_list.php");
  logAudit('PO','UPDATE',$id,$po['po_number'],['po_date'=>$po['po_date'],'supplier'=>$po['supplier'],'total_amount'=>$po['total_amount']],['po_date'=>$date,'supplier'=>$supplier,'total_amount'=>$amount]);
  exit;
}
?>

<main class="flex-1 p-6">
  <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6">
    <h2 class="text-xl font-bold text-gray-700 mb-4">Edit Purchase Order</h2>
    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm font-medium">PO Number</label>
        <input type="text" value="<?php echo $po['po_number']; ?>" disabled class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100">
      </div>
      <div>
        <label class="block text-sm font-medium">PO Date</label>
        <input type="date" name="po_date" value="<?php echo $po['po_date']; ?>" required class="mt-1 block w-full border rounded px-3 py-2 focus:ring-purple-500 focus:border-purple-500">
      </div>
      <div>
        <label class="block text-sm font-medium">Supplier</label>
        <input type="text" name="supplier" value="<?php echo $po['supplier']; ?>" required class="mt-1 block w-full border rounded px-3 py-2 focus:ring-purple-500 focus:border-purple-500">
      </div>
      <div>
        <label class="block text-sm font-medium">Date of Award</label>
        <input type="date" name="date_of_award" value="<?php echo $po['date_of_award']; ?>" required class="mt-1 block w-full border rounded px-3 py-2 focus:ring-purple-500 focus:border-purple-500">
      </div>
      <div>
        <label class="block text-sm font-medium">Total Amount</label>
        <input type="number" step="0.01" name="total_amount" value="<?php echo $po['total_amount']; ?>" required class="mt-1 block w-full border rounded px-3 py-2 focus:ring-purple-500 focus:border-purple-500">
      </div>
      <div>
        <label class="block text-sm font-medium">Linked PR</label>
        <select name="pr_id" required class="mt-1 block w-full border rounded px-3 py-2 focus:ring-purple-500 focus:border-purple-500">
          <?php while($pr=$prs->fetch_assoc()): ?>
            <option value="<?php echo $pr['id']; ?>" <?php echo $pr['id']==$po['pr_id']?'selected':''; ?>>
              <?php echo $pr['pr_number']; ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="flex justify-end space-x-2">
        <a href="po_list.php" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Cancel</a>
        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded shadow">Update</button>
      </div>
    </form>
  </div>
</main>

<?php ob_end_flush(); ?>
