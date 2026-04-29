<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
require 'inc/helpers.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$id = $_GET['id'] ?? 0;
$res = $conn->query("SELECT * FROM rfqs WHERE id=$id");
$rfq = $res->fetch_assoc();

// Fetch PRs for dropdown
$prs = $conn->query("SELECT id, pr_number FROM purchase_requests WHERE is_cancelled=0 ORDER BY id DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = $_POST['rfq_date'];
  $pr_id = $_POST['pr_id'];

  $stmt = $conn->prepare("UPDATE rfqs SET rfq_date=?, pr_id=? WHERE id=?");
  $stmt->bind_param("sii",$date,$pr_id,$id);
  $stmt->execute();

  header("Location: rfq_list.php");
  logAudit('RFQ','UPDATE',$id,$rfq['rfq_number'],['rfq_date'=>$rfq['rfq_date'],'pr_id'=>$rfq['pr_id']],['rfq_date'=>$date,'pr_id'=>$pr_id]);
  exit;
}
?>

<main class="flex-1 p-6">
  <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6">
    <h2 class="text-xl font-bold text-gray-700 mb-4">Edit Request for Quotation</h2>
    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm font-medium">RFQ Number</label>
        <input type="text" value="<?php echo $rfq['rfq_number']; ?>" disabled class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100">
      </div>
      <div>
        <label class="block text-sm font-medium">RFQ Date</label>
        <input type="date" name="rfq_date" value="<?php echo $rfq['rfq_date']; ?>" required class="mt-1 block w-full border rounded px-3 py-2 focus:ring-green-500 focus:border-green-500">
      </div>
      <div>
        <label class="block text-sm font-medium">Linked PR</label>
        <select name="pr_id" required class="mt-1 block w-full border rounded px-3 py-2 focus:ring-green-500 focus:border-green-500">
          <?php while($pr=$prs->fetch_assoc()): ?>
            <option value="<?php echo $pr['id']; ?>" <?php echo $pr['id']==$rfq['pr_id']?'selected':''; ?>>
              <?php echo $pr['pr_number']; ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="flex justify-end space-x-2">
        <a href="rfq_list.php" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Cancel</a>
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">Update</button>
      </div>
    </form>
  </div>
</main>

<?php include 'inc/footer.php'; ?>
