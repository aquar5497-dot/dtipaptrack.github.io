<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ===============================
   VALIDATE PARENT PR
================================ */
if (!isset($_GET['parent_id']) || empty($_GET['parent_id'])) {
  echo "<main class='flex-1 p-6 text-red-600 font-semibold'>
          Parent PR not specified.
        </main>";
  include 'inc/footer.php';
  exit;
}

$parent_id = (int)$_GET['parent_id'];

/* ===============================
   FETCH PARENT PR (MAIN ONLY)
================================ */
$parentStmt = $conn->prepare("
  SELECT pr_number, pr_date
  FROM purchase_requests
  WHERE id = ? AND pr_type = 'MAIN'
");
$parentStmt->bind_param("i", $parent_id);
$parentStmt->execute();
$parent = $parentStmt->get_result()->fetch_assoc();

if (!$parent) {
  echo "<main class='flex-1 p-6 text-red-600 font-semibold'>
          Parent PR not found.
        </main>";
  include 'inc/footer.php';
  exit;
}

/* ===============================
   FETCH INSERTED / LATE PRs ONLY
================================ */
$insertedStmt = $conn->prepare("
  SELECT id, pr_number, pr_date, purpose, entity_name, fund_cluster, total_amount
  FROM purchase_requests
  WHERE parent_id = ?
    AND pr_type = 'LATE'
  ORDER BY pr_number ASC
");
$insertedStmt->bind_param("i", $parent_id);
$insertedStmt->execute();
$insertedRes = $insertedStmt->get_result();

// --- INITIALIZE TOTAL VARIABLE ---
$grand_total = 0; 
?>

<main class="flex-1 p-6">

  <h2 class="text-xl font-bold text-purple-700 mb-4">
    Inserted (Late) Purchase Requests
  </h2>

  <div class="bg-purple-100 border border-purple-300 rounded p-4 mb-6">
    <p class="text-sm">
      <strong>Parent PR:</strong>
      <span class="font-bold text-purple-700">
        <?= htmlspecialchars($parent['pr_number']) ?>
      </span>
    </p>
    <p class="text-sm">
      <strong>Parent PR Date:</strong>
      <?= htmlspecialchars($parent['pr_date']) ?>
    </p>
  </div>

  <?php if ($insertedRes->num_rows === 0): ?>
    <div class="text-gray-600 italic">
      No inserted (late) PRs for this Parent PR.
    </div>
  <?php else: ?>

    <table class="w-full border text-sm rounded overflow-hidden">
      <thead class="bg-purple-200">
        <tr>
          <th class="p-2 text-left">Inserted PR Number</th>
          <th class="p-2 text-left">PR Date</th>
          <th class="p-2 text-left">Entity</th>
          <th class="p-2 text-left">Fund Cluster</th>
          <th class="p-2 text-left">Purpose</th>
          <th class="p-2 text-right">Amount</th>
          <th class="p-2 text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $insertedRes->fetch_assoc()): 
            $grand_total += $row['total_amount']; 
        ?>
          <tr class="border-t bg-purple-50 hover:bg-purple-100">
            <td class="p-2 font-bold text-purple-700">
              <?= htmlspecialchars($row['pr_number']) ?>
            </td>
            <td class="p-2"><?= htmlspecialchars($row['pr_date']) ?></td>
            <td class="p-2"><?= htmlspecialchars($row['entity_name']) ?></td>
            <td class="p-2"><?= htmlspecialchars($row['fund_cluster']) ?></td>
            <td class="p-2"><?= htmlspecialchars($row['purpose']) ?></td>
            <td class="p-2 text-right">
              <?= number_format($row['total_amount'], 2) ?>
            </td>
            <td class="p-2 text-center whitespace-nowrap">
              <a href="pr_inserted_edit.php?id=<?= $row['id'] ?>&parent_id=<?= $parent_id ?>"
                 class="text-blue-600 hover:underline mr-3">
                Edit
              </a>
              <a href="pr_inserted_delete.php?id=<?= $row['id'] ?>&parent_id=<?= $parent_id ?>"
                 class="text-red-600 hover:underline"
                 onclick="return confirm('Are you sure you want to delete this inserted PR?')">
                Delete
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
      
      <tfoot class="bg-purple-200 font-bold text-purple-900">
          <tr>
              <td colspan="6" class="p-2 text-right">TOTAL INSERTED AMOUNT:</td>
              <td class="p-2 text-center">₱<?= number_format($grand_total, 2) ?></td>
          </tr>
      </tfoot>
    </table>

  <?php endif; ?>

  <div class="mt-6">
    <a href="pr_list.php"
       class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">
      Back to PR List
    </a>
  </div>

</main>

<?php
include 'inc/footer.php';
ob_end_flush();
?>