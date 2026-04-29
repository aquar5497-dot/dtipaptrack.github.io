<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ===============================
   VALIDATE PARENT
=============================== */
if (!isset($_GET['parent_id']) || !is_numeric($_GET['parent_id'])) {
  echo "<div class='p-6 text-red-700 font-semibold'>Parent PR not specified.</div>";
  include 'inc/footer.php';
  exit;
}

$parent_id = (int)$_GET['parent_id'];

$parent = $conn->query("
  SELECT * FROM purchase_requests WHERE id = $parent_id
")->fetch_assoc();

if (!$parent) {
  echo "<div class='p-6 text-red-700 font-semibold'>Parent PR not found.</div>";
  include 'inc/footer.php';
  exit;
}

/* ===============================
   GENERATE NEXT LETTER
=============================== */
$base = $parent['pr_number'];

$res = $conn->query("
  SELECT pr_number FROM purchase_requests
  WHERE parent_id = $parent_id
  ORDER BY pr_number DESC
  LIMIT 1
");

$letter = 'A';
if ($res && $res->num_rows) {
  $last = $res->fetch_assoc()['pr_number'];
  $letter = chr(ord(substr($last, -1)) + 1);
}

$new_pr_number = $base . '-' . $letter;

/* ===============================
   SAVE
=============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $conn->prepare("
    INSERT INTO purchase_requests
(pr_number, pr_date, entity_name, fund_cluster, total_amount, purpose, parent_id, pr_type)
VALUES (?, ?, ?, ?, ?, ?, ?, 'LATE')

  ");

  $stmt->bind_param(
    "ssssisi",
    $new_pr_number,
    $_POST['pr_date'],
    $_POST['entity_name'],
    $_POST['fund_cluster'],
    $_POST['total_amount'],
    $_POST['purpose'],
    $parent_id
  );

  $stmt->execute();
  logAudit('LATE_PR','CREATE',$conn->insert_id,$new_pr_number,[],['pr_number'=>$new_pr_number,'pr_date'=>$_POST['pr_date'],'total_amount'=>$_POST['total_amount'],'purpose'=>$_POST['purpose'],'parent_id'=>$parent_id]);
  header("Location: pr_inserted_list.php?parent_id=$parent_id");
  exit;
}
?>

<main class="flex-1 p-6 bg-gray-50">
  <div class="bg-white p-6 rounded shadow max-w-2xl">

    <h2 class="text-xl font-bold text-purple-700 mb-4">
      Insert New PR (<?= $new_pr_number ?>)
    </h2>

    <form method="post" class="space-y-4">

      <div>
        <label class="block text-sm font-semibold">PR Date</label>
        <input type="date" name="pr_date"
               value="<?= $parent['pr_date'] ?>"
               class="w-full border px-3 py-2 rounded" required>
      </div>

      <div>
        <label class="block text-sm font-semibold">Entity Name</label>
        <input type="text" name="entity_name"
               value="<?= $parent['entity_name'] ?>"
               class="w-full border px-3 py-2 rounded" required>
      </div>

      <div>
        <label class="block text-sm font-semibold">Fund Cluster</label>
        <input type="text" name="fund_cluster"
               value="<?= $parent['fund_cluster'] ?>"
               class="w-full border px-3 py-2 rounded" required>
      </div>

      <div>
        <label class="block text-sm font-semibold">Total Amount</label>
        <input type="number" step="0.01" name="total_amount"
               class="w-full border px-3 py-2 rounded" required>
      </div>

      <div>
        <label class="block text-sm font-semibold">Purpose</label>
        <textarea name="purpose"
                  class="w-full border px-3 py-2 rounded" required></textarea>
      </div>

      <div class="flex gap-3">
        <button class="bg-purple-600 text-white px-4 py-2 rounded">
          Save Inserted PR
        </button>

        <a href="pr_edit.php?id=<?= $parent_id ?>"
           class="bg-gray-300 px-4 py-2 rounded">
          Cancel
        </a>
      </div>

    </form>
  </div>
</main>

<?php include 'inc/footer.php'; ?>
