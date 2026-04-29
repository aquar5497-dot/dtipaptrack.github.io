<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

if (!isset($_GET['id'], $_GET['parent_id'])) {
    echo "<main class='flex-1 p-6 text-red-600 font-semibold'>Invalid request.</main>";
    include 'inc/footer.php';
    exit;
}

$id        = (int)$_GET['id'];
$parent_id = (int)$_GET['parent_id'];

/* ===============================
   FETCH INSERTED PR (LATE ONLY)
================================ */
$stmt = $conn->prepare("
    SELECT *
    FROM purchase_requests
    WHERE id = ? AND pr_type = 'LATE'
");
$stmt->bind_param("i", $id);
$stmt->execute();
$pr = $stmt->get_result()->fetch_assoc();

if (!$pr) {
    echo "<main class='flex-1 p-6 text-red-600 font-semibold'>Inserted PR not found.</main>";
    include 'inc/footer.php';
    exit;
}

/* ===============================
   UPDATE
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pr_date      = $_POST['pr_date'];
    $entity       = $_POST['entity_name'];
    $fund_cluster = $_POST['fund_cluster'];
    $purpose      = $_POST['purpose'];
    $amount       = (float)$_POST['total_amount'];

    $update = $conn->prepare("
        UPDATE purchase_requests
        SET pr_date = ?, entity_name = ?, fund_cluster = ?, purpose = ?, total_amount = ?
        WHERE id = ? AND pr_type = 'LATE'
    ");
    $update->bind_param("ssssdi", $pr_date, $entity, $fund_cluster, $purpose, $amount, $id);

    if ($update->execute()) {
        logAudit('LATE_PR','UPDATE',$id,$pr['pr_number'],['pr_date'=>$pr['pr_date'],'total_amount'=>$pr['total_amount'],'purpose'=>$pr['purpose']],['pr_date'=>$pr_date,'total_amount'=>$amount,'purpose'=>$purpose]);
        header("Location: pr_inserted_list.php?parent_id=$parent_id");
        exit;
    }
}
?>

<main class="flex-1 p-6">
  <div class="max-w-2xl mx-auto bg-white shadow rounded-xl p-6">

    <h2 class="text-xl font-bold text-purple-700 mb-4">
      Edit Inserted (Late) PR
    </h2>

    <form method="post" class="space-y-4">

      <div>
        <label class="block text-sm font-medium">PR Number</label>
        <input type="text" value="<?= htmlspecialchars($pr['pr_number']) ?>" readonly
               class="w-full border px-3 py-2 bg-gray-100">
      </div>

      <div>
        <label class="block text-sm font-medium">PR Date</label>
        <input type="date" name="pr_date" required
               value="<?= htmlspecialchars($pr['pr_date']) ?>"
               class="w-full border px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Entity</label>
        <input type="text" name="entity_name" required
               value="<?= htmlspecialchars($pr['entity_name']) ?>"
               class="w-full border px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Fund Cluster</label>
        <input type="text" name="fund_cluster" required
               value="<?= htmlspecialchars($pr['fund_cluster']) ?>"
               class="w-full border px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Purpose</label>
        <textarea name="purpose" required
                  class="w-full border px-3 py-2"><?= htmlspecialchars($pr['purpose']) ?></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium">Total Amount</label>
        <input type="number" step="0.01" name="total_amount" required
               value="<?= $pr['total_amount'] ?>"
               class="w-full border px-3 py-2">
      </div>

      <div class="flex justify-end gap-2">
        <a href="pr_inserted_list.php?parent_id=<?= $parent_id ?>"
           class="bg-gray-200 px-4 py-2 rounded">
          Cancel
        </a>
        <button class="bg-purple-600 text-white px-4 py-2 rounded">
          Update
        </button>
      </div>

    </form>
  </div>
</main>

<?php
include 'inc/footer.php';
ob_end_flush();
?>