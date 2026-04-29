<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "<script>location.href='payroll_list.php';</script>";
    exit;
}

$errors = [];
$success = '';

// fetch existing
$stmt = $conn->prepare("SELECT * FROM payroll_requests WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "<script>location.href='payroll_list.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_name = trim($_POST['employee_name'] ?? '');
    $salary_amount = str_replace(',', '', trim($_POST['salary_amount'] ?? '0'));

    if ($employee_name === '') $errors[] = "Employee/Payee name is required.";
    if (!is_numeric($salary_amount) || floatval($salary_amount) < 0) $errors[] = "Salary amount must be valid.";

    if (empty($errors)) {
        $u = $conn->prepare("UPDATE payroll_requests SET employee_name = ?, salary_amount = ? WHERE id = ?");
        $u->bind_param("sdi", $employee_name, $salary_amount, $id);
        if ($u->execute()) {
            $success = "Payroll updated successfully.";
            // refresh row
            $row['employee_name'] = $employee_name;
            $row['salary_amount'] = $salary_amount;
        } else {
            $errors[] = "Failed to update: " . $u->error;
        }
        $u->close();
    }
}
?>

<main class="flex-1 p-6 overflow-y-auto bg-gray-50" style="min-height: calc(100vh - 70px);">
  <div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-4">Edit Payroll Request</h1>

    <?php if (!empty($errors)): ?>
      <div class="bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4">
        <ul class="list-disc pl-5">
          <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="bg-green-100 border border-green-300 text-green-800 p-3 rounded mb-4"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" class="bg-white p-6 rounded shadow">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Payroll Number</label>
        <input type="text" value="<?php echo htmlspecialchars($row['payroll_number']); ?>" readonly class="w-full p-2 border rounded bg-gray-100">
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Employee / Payee Name</label>
        <input type="text" name="employee_name" value="<?php echo htmlspecialchars($row['employee_name']); ?>" required class="w-full p-2 border rounded">
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Amount</label>
        <input type="text" name="salary_amount" value="<?php echo htmlspecialchars(number_format($row['salary_amount'],2)); ?>" required class="w-full p-2 border rounded">
      </div>

      <div class="flex items-center space-x-3">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Update Payroll</button>
        <a href="payroll_list.php" class="px-4 py-2 bg-gray-200 rounded">Back to Payroll List</a>
      </div>
    </form>
  </div>
</main>

<?php include 'inc/footer.php'; ?>
