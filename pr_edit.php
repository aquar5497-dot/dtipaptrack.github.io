<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ===============================
   FETCH PR
=============================== */
if (!isset($_GET['id'])) {
  die("PR ID missing.");
}

$id = intval($_GET['id']);
$res = $conn->query("SELECT * FROM purchase_requests WHERE id = $id");
$pr = $res->fetch_assoc();

if (!$pr) {
  die("PR not found.");
}

/* ===============================
   HANDLE FORM SUBMISSION
=============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $action = $_POST['action'];

  $pr_date = $_POST['pr_date'];
  $entity_name = $_POST['entity_name'];
  $fund_cluster = $_POST['fund_cluster'];
  $total_amount = $_POST['total_amount'];
  $purpose = $_POST['purpose'];

  /* ===============================
     INSERT NEW (LATE) PR
  =============================== */
  if ($action === 'insert') {

    $parent_id = $pr['id'];
    $base_pr_number = $pr['pr_number'];

    // Count ALL existing inserted PRs for same parent (regardless of date) to maintain A,B,C sequence
    $stmt = $conn->prepare("
      SELECT COUNT(*) AS total
      FROM purchase_requests
      WHERE parent_id = ?
        AND pr_type = 'LATE'
    ");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Generate next letter: A=0, B=1, C=2...
    $letter = chr(65 + $count); // 65 is ASCII for 'A'
    $new_pr_number = $base_pr_number . '-' . $letter;

    // Insert PR with customizable pr_date
    $stmt = $conn->prepare("
      INSERT INTO purchase_requests
      (pr_number, pr_date, entity_name, fund_cluster, total_amount, purpose, parent_id, pr_type)
      VALUES (?, ?, ?, ?, ?, ?, ?, 'LATE')
    ");
    
    $stmt->bind_param(
      "ssssdsi",
      $new_pr_number,
      $pr_date,
      $entity_name,
      $fund_cluster,
      $total_amount,
      $purpose,
      $parent_id
    );
    
    // Execute with error handling
    if (!$stmt->execute()) {
      die("Error inserting PR: " . $stmt->error);
    }
    
    $new_id = $conn->insert_id;
    logAudit('LATE_PR','CREATE',$new_id,$new_pr_number,[],['pr_number'=>$new_pr_number,'pr_date'=>$pr_date,'entity_name'=>$entity_name,'fund_cluster'=>$fund_cluster,'total_amount'=>$total_amount,'purpose'=>$purpose,'parent_id'=>$parent_id]);
    
    $stmt->close();

    header("Location: pr_inserted_list.php?parent_id=$parent_id");
    exit;
  }

  /* ===============================
     NORMAL UPDATE PR
  =============================== */
  if ($action === 'update') {

    $stmt = $conn->prepare("
      UPDATE purchase_requests
      SET pr_date=?, entity_name=?, fund_cluster=?, total_amount=?, purpose=?
      WHERE id=?
    ");
    $stmt->bind_param(
      "sssdsi",
      $pr_date,
      $entity_name,
      $fund_cluster,
      $total_amount,
      $purpose,
      $id
    );
    $stmt->execute();
    $stmt->close();

    logAudit('PR', 'UPDATE', $id, $pr['pr_number'], ['pr_date'=>$pr['pr_date'],'entity_name'=>$pr['entity_name'],'fund_cluster'=>$pr['fund_cluster'],'total_amount'=>$pr['total_amount'],'purpose'=>$pr['purpose']], ['pr_date'=>$pr_date,'entity_name'=>$entity_name,'fund_cluster'=>$fund_cluster,'total_amount'=>$total_amount,'purpose'=>$purpose]);
    header("Location: pr_list.php");
    exit;
  }
}
?>

<main class="flex-1 p-6">
  <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6">

    <h2 class="text-xl font-bold text-gray-700 mb-4">Edit Purchase Request</h2>

    <form method="post" class="space-y-4">

      <div>
        <label class="block text-sm font-medium">PR Number</label>
        <input type="text"
               value="<?= htmlspecialchars($pr['pr_number']) ?>"
               readonly
               class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100">
      </div>

      <div>
        <label class="block text-sm font-medium">PR Date</label>
        <input type="date"
               name="pr_date"
               value="<?= htmlspecialchars($pr['pr_date']) ?>"
               required
               class="mt-1 block w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Entity Name</label>
        <input type="text"
               name="entity_name"
               value="<?= htmlspecialchars($pr['entity_name']) ?>"
               required
               class="mt-1 block w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Fund Cluster</label>
        <input type="text"
               name="fund_cluster"
               value="<?= htmlspecialchars($pr['fund_cluster']) ?>"
               required
               class="mt-1 block w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Total Amount</label>
        <input type="number"
               step="0.01"
               name="total_amount"
               value="<?= htmlspecialchars($pr['total_amount']) ?>"
               required
               class="mt-1 block w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Purpose</label>
        <textarea name="purpose"
                  required
                  class="mt-1 block w-full border rounded px-3 py-2"><?= htmlspecialchars($pr['purpose']) ?></textarea>
      </div>

      <!-- ACTION BUTTONS -->
      <div class="flex justify-between items-center pt-4">

        <!-- INSERT NEW PR -->
        <button type="submit"
                name="action"
                value="insert"
                class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded shadow">
          ➕ Insert New PR
        </button>

        <!-- NORMAL ACTIONS -->
        <div class="space-x-2">
          <a href="pr_list.php"
             class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">
            Cancel
          </a>

          <button type="submit"
                  name="action"
                  value="update"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow">
            Update
          </button>
        </div>

      </div>

    </form>
  </div>
</main>

<?php ob_end_flush(); ?>