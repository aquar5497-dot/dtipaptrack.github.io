<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// Parent PR required
if (!isset($_GET['parent_id'])) {
    die('Parent PR is required.');
}

$parent_id = (int)$_GET['parent_id'];
$parent = $conn->query("SELECT * FROM purchase_requests WHERE id = $parent_id")->fetch_assoc();

if (!$parent) {
    die('Parent PR not found.');
}

// ---------- GENERATE A, B, C ----------
$parent_pr_number = $parent['pr_number'];
$pr_date = $parent['pr_date'];

// Count existing inserted PRs for this parent
$res = $conn->query("
    SELECT COUNT(*) AS c 
    FROM purchase_requests 
    WHERE parent_pr_id = $parent_id AND is_inserted = 1
");
$count = $res->fetch_assoc()['c'];

// Generate suffix letter
$suffix = chr(65 + $count); // A, B, C...

$new_pr_number = $parent_pr_number . '-' . $suffix;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entity_name = $_POST['entity_name'];
    $fund_cluster = $_POST['fund_cluster'];
    $purpose = $_POST['purpose'];
    $total_amount = $_POST['total_amount'];

    $stmt = $conn->prepare("
        INSERT INTO purchase_requests
        (pr_number, pr_date, entity_name, fund_cluster, purpose, total_amount, is_inserted, parent_pr_id)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $stmt->bind_param(
        "ssssssi",
        $new_pr_number,
        $pr_date,
        $entity_name,
        $fund_cluster,
        $purpose,
        $total_amount,
        $parent_id
    );
    $stmt->execute();

    header("Location: pr_inserted_list.php");
    exit;
}
?>

<main class="flex-1 p-6 bg-blue-100">
  <div class="max-w-3xl mx-auto bg-white p-6 rounded-xl shadow">
    <h2 class="text-xl font-bold mb-2">Inserted PR</h2>

    <div class="mb-4 p-3 bg-yellow-50 border rounded">
      <strong>Parent PR:</strong> <?= htmlspecialchars($parent_pr_number) ?><br>
      <strong>PR Date:</strong> <?= htmlspecialchars($pr_date) ?><br>
      <strong>New PR Number:</strong> <span class="text-blue-700"><?= $new_pr_number ?></span>
    </div>

    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm font-medium">Entity Name</label>
        <input type="text" name="entity_name" required class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Fund Cluster</label>
        <input type="text" name="fund_cluster" required class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Purpose</label>
        <textarea name="purpose" required class="w-full border rounded px-3 py-2"></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium">Total Amount</label>
        <input type="number" step="0.01" name="total_amount" required class="w-full border rounded px-3 py-2">
      </div>

      <div class="flex justify-end gap-2">
        <a href="pr_list.php" class="bg-gray-200 px-4 py-2 rounded">Cancel</a>
        <button class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
          Save Inserted PR
        </button>
      </div>
    </form>
  </div>
</main>

<?php include 'inc/footer.php'; ?>
