<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ===============================
   VALIDATE PARENT PR
================================ */
if (!isset($_GET['parent_id']) || !is_numeric($_GET['parent_id'])) {
    die('Parent PR not specified.');
}

$parent_id = (int)$_GET['parent_id'];

/* ===============================
   FETCH PARENT PR (MAIN ONLY)
================================ */
$stmt = $conn->prepare("
    SELECT id, pr_number, pr_date, entity_name, fund_cluster
    FROM purchase_requests
    WHERE id = ? AND pr_type = 'MAIN'
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$parent = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$parent) {
    die('Invalid parent PR.');
}

/* ===============================
   AUTO-GENERATE SUB-PR NUMBER
   FORMAT: SUBPR-YYYY-MMM-001
================================ */
$year      = date('Y', strtotime($parent['pr_date']));
$month_txt = date('m', strtotime($parent['pr_date']));
$month_num = (int)date('n', strtotime($parent['pr_date']));

$countStmt = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM purchase_requests
    WHERE pr_type = 'SUB'
      AND YEAR(pr_date) = ?
      AND MONTH(pr_date) = ?
");
$countStmt->bind_param("ii", $year, $month_num);
$countStmt->execute();
$cnt = $countStmt->get_result()->fetch_assoc();
$countStmt->close();

$next = $cnt['cnt'] + 1;

$subpr_number = sprintf(
    "SUBPR-%s-%s-%03d",
    $year,
    $month_txt,
    $next
);

/* ===============================
   HANDLE FORM SUBMIT
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pr_number    = $_POST['pr_number'];
    $pr_date      = $_POST['pr_date'];
    $entity_name  = trim($_POST['entity_name']);
    $fund_cluster = trim($_POST['fund_cluster']);
    $purpose      = trim($_POST['purpose']);
    $total_amount = floatval($_POST['total_amount']);

    if ($purpose === '') {
        echo "<script>alert('Purpose is required.');</script>";
    } else {

        $insert = $conn->prepare("
            INSERT INTO purchase_requests (
                pr_number,
                pr_date,
                entity_name,
                fund_cluster,
                purpose,
                total_amount,
                parent_id,
                pr_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'SUB')
        ");

        $insert->bind_param(
    "sssssdi", // Corrected: 5 strings, 1 double, 1 integer
    $pr_number,
    $pr_date,
    $entity_name,
    $fund_cluster,
    $purpose,
    $total_amount,
    $parent_id
);

        if ($insert->execute()) {
            echo "<script>
                alert('Sub-PR successfully added.');
                window.location = 'subpr_list.php';
            </script>";
            exit;
        } else {
            echo "<script>alert('Error adding Sub-PR');</script>";
        }
    }
}
?>

<main class="flex-1 p-6 bg-blue-100">
  <div class="bg-white p-6 rounded-xl shadow max-w-3xl mx-auto">

    <h2 class="text-xl font-bold text-gray-800 mb-4">
      Add Sub-Purchase Request
    </h2>

    <!-- Parent PR Info -->
    <div class="bg-gray-100 border rounded p-4 mb-6">
      <p><strong>Parent PR:</strong> <?= htmlspecialchars($parent['pr_number']) ?></p>
      <p><strong>PR Date:</strong> <?= htmlspecialchars($parent['pr_date']) ?></p>
    </div>

    <form method="post" class="space-y-4">

      <div>
        <label class="block text-sm font-medium">Sub-PR Number</label>
        <input type="text" name="pr_number"
               value="<?= htmlspecialchars($subpr_number) ?>"
               readonly
               class="w-full border px-3 py-2 rounded bg-gray-100">
      </div>

      <div>
        <label class="block text-sm font-medium">PR Date</label>
        <input type="date" name="pr_date"
               value="<?= htmlspecialchars($parent['pr_date']) ?>"
               required
               class="w-full border px-3 py-2 rounded">
      </div>

      <div>
        <label class="block text-sm font-medium">Entity Name</label>
        <input type="text" name="entity_name"
               value="<?= htmlspecialchars($parent['entity_name']) ?>"
               class="w-full border px-3 py-2 rounded">
      </div>

      <div>
        <label class="block text-sm font-medium">Fund Cluster</label>
        <input type="text" name="fund_cluster"
               value="<?= htmlspecialchars($parent['fund_cluster']) ?>"
               class="w-full border px-3 py-2 rounded">
      </div>

      <div>
        <label class="block text-sm font-medium">Total Amount</label>
        <input type="number" step="0.01" name="total_amount" required
               class="w-full border px-3 py-2 rounded">
      </div>

      <div>
        <label class="block text-sm font-medium">Purpose</label>
        <textarea name="purpose" rows="3" required
                  class="w-full border px-3 py-2 rounded"></textarea>
      </div>

      <div class="flex gap-2">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
          Save Sub-PR
        </button>
        <a href="pr_list.php?parent_id=<?= $parent_id ?>"
           class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded">
          Cancel
        </a>
      </div>

    </form>

  </div>
</main>

<?php include 'inc/footer.php'; ?>
