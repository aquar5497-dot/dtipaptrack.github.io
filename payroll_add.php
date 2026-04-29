<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ==========================================================
   AUTO-GENERATE PAYROLL NUMBER (NEW FORMAT)
   Format: PYR-YYYY-MMM-001
   Example: PYR-2026-Jan-001
   ========================================================== */

$year  = date('Y');
$month = date('m'); // 01, 02, 03...

$prefix = "PYR-$year-$month";

// Get last payroll number for the same year & month
$stmt = $conn->prepare("
    SELECT payroll_number
    FROM payroll_requests
    WHERE payroll_number LIKE CONCAT(?, '%')
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param("s", $prefix);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    $lastNumber = intval(substr($result['payroll_number'], -3));
    $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
} else {
    $nextNumber = '001';
}

$payroll_number = "$prefix-$nextNumber";

/* ========================================================== */

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_name = trim($_POST['employee_name'] ?? '');
    $salary_amount = str_replace(',', '', trim($_POST['salary_amount'] ?? '0'));

    if ($employee_name === '') $errors[] = "Employee/Payee name is required.";
    if (!is_numeric($salary_amount) || floatval($salary_amount) < 0)
        $errors[] = "Salary amount must be a valid non-negative number.";

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO payroll_requests 
            (payroll_number, employee_name, salary_amount, status) 
            VALUES (?, ?, ?, 'Pending')
        ");
        $stmt->bind_param("ssd", $payroll_number, $employee_name, $salary_amount);

        if ($stmt->execute()) {
            $success = "Payroll request created successfully.";

            /* Regenerate next payroll number after save */
            $stmt = $conn->prepare("
                SELECT payroll_number
                FROM payroll_requests
                WHERE payroll_number LIKE CONCAT(?, '%')
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->bind_param("s", $prefix);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result) {
                $lastNumber = intval(substr($result['payroll_number'], -3));
                $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $nextNumber = '001';
            }

            $payroll_number = "$prefix-$nextNumber";

        } else {
            $errors[] = "Failed to save payroll request: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<main class="flex-1 p-6 overflow-y-auto bg-gray-50" style="min-height: calc(100vh - 70px);">
  <div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-4">Payroll Disbursement Request Form</h1>

    <?php if (!empty($errors)): ?>
      <div class="bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4">
        <ul class="list-disc pl-5">
          <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars($e); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="bg-green-100 border border-green-300 text-green-800 p-3 rounded mb-4">
        <?php echo htmlspecialchars($success); ?>
        <a href="payroll_list.php" class="underline">View Payroll List</a>
      </div>
    <?php endif; ?>

    <form method="post" class="bg-white p-6 rounded shadow">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Payroll Number</label>
        <input type="text" name="payroll_number"
               value="<?php echo htmlspecialchars($payroll_number); ?>"
               readonly class="w-full p-2 border rounded bg-gray-100">
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Employee / Payee Name</label>
        <input type="text" name="employee_name" id="sd_payee_name"
               value="<?php echo htmlspecialchars($_POST['employee_name'] ?? ''); ?>"
               required class="w-full p-2 border rounded" placeholder="Type or select..." autocomplete="off">
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Amount</label>
        <input type="text" name="salary_amount"
               value="<?php echo htmlspecialchars($_POST['salary_amount'] ?? '0.00'); ?>"
               required class="w-full p-2 border rounded" placeholder="0.00">
        <p class="text-xs text-gray-500 mt-1">Enter numeric value (e.g. 15000.00)</p>
      </div>

      <div class="flex items-center space-x-3">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
          Save Payroll Request
        </button>
        <a href="payroll_list.php" class="px-4 py-2 bg-gray-200 rounded">
          Back to Payroll List
        </a>
      </div>
    </form>
  </div>
</main>

<script src="assets/smart-dropdown.js"></script>
<script>SmartDropdown.init('#sd_payee_name', 'payee_name');</script>
<?php include 'inc/footer.php'; ?>
