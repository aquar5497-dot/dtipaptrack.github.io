<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: payroll_dv_list.php?error=No Payroll DV specified.");
    exit;
}

$errors = [];
$success = '';
$dv = null;

// Fetch existing record
$stmt = $conn->prepare("SELECT * FROM payroll_dvs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header("Location: payroll_dv_list.php?error=Payroll DV not found.");
    exit;
}
$dv = $res->fetch_assoc();
$stmt->close();

// Get linked payroll info if any
$linked_payroll = null;
if (!empty($dv['payroll_id'])) {
    $p = $conn->prepare("SELECT payroll_number, employee_name, salary_amount FROM payroll_requests WHERE id = ?");
    $p->bind_param("i", $dv['payroll_id']);
    $p->execute();
    $linked_res = $p->get_result();
    if ($linked_res->num_rows > 0) {
        $linked_payroll = $linked_res->fetch_assoc();
    }
    $p->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dv_date = trim($_POST['dv_date'] ?? '');
    $payee = trim($_POST['payee'] ?? '');
    $gross_amount = (float)str_replace(',', '', $_POST['gross_amount'] ?? '0');
    $tax_percentage = (float)$_POST['tax_percentage'] ?? 0;
    
    // Calculate values (Aligning with add logic)
    $tax_amount = $gross_amount * ($tax_percentage / 100);
    $net_amount = $gross_amount - $tax_amount;
    $remarks = trim($_POST['remarks'] ?? '');

    if ($dv_date === '') $errors[] = "DV Date is required.";
    if ($payee === '') $errors[] = "Payee name is required.";
    if ($gross_amount <= 0) $errors[] = "Gross amount must be a valid positive number.";

    if (empty($errors)) {
        // Added tax_percentage, tax_amount, and net_amount to the UPDATE statement
        $upd = $conn->prepare("UPDATE payroll_dvs SET 
            dv_date=?, 
            payee=?, 
            gross_amount=?, 
            tax_percentage=?, 
            tax_amount=?, 
            net_amount=?, 
            remarks=? 
            WHERE id=?");
        
        $upd->bind_param("ssddddsi", 
            $dv_date, 
            $payee, 
            $gross_amount, 
            $tax_percentage, 
            $tax_amount, 
            $net_amount, 
            $remarks, 
            $id
        );

        if ($upd->execute()) {
            header("Location: payroll_dv_list.php?success=Payroll DV updated successfully.");
            exit;
        } else {
            $errors[] = "Failed to update Payroll DV: " . $upd->error;
        }
        $upd->close();
    }
}
?>

<main class="flex-1 p-6 overflow-y-auto bg-gray-50" style="min-height: calc(100vh - 70px);">
  <div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-4">Edit Payroll Disbursement Voucher</h1>

    <?php if (!empty($errors)): ?>
      <div class="bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4">
        <ul class="list-disc pl-5">
          <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars($e); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="bg-white p-6 rounded shadow space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">DV Number</label>
        <input type="text" value="<?= htmlspecialchars($dv['dv_number']); ?>" readonly class="w-full p-2 border rounded bg-gray-100">
      </div>

      <?php if ($linked_payroll): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Linked Payroll</label>
          <input type="text" value="<?= htmlspecialchars($linked_payroll['payroll_number']); ?>" readonly class="w-full p-2 border rounded bg-gray-100 text-gray-700">
        </div>
      <?php endif; ?>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">DV Date</label>
        <input type="date" name="dv_date" value="<?= htmlspecialchars($dv['dv_date']); ?>" required class="w-full p-2 border rounded">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Payee Name</label>
        <input type="text" name="payee" value="<?= htmlspecialchars($dv['payee']); ?>" required class="w-full p-2 border rounded">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Gross Amount</label>
        <input type="number" step="0.01" id="gross" name="gross_amount" value="<?= htmlspecialchars($dv['gross_amount']); ?>" required class="w-full p-2 border rounded">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Custom Tax (%)</label>
        <input type="number" step="0.01" id="tax_pct" name="tax_percentage" value="<?= htmlspecialchars($dv['tax_percentage']); ?>" class="w-full p-2 border rounded">
      </div>

      <div>
        <label class="block text-sm font-medium text-red-600 mb-1">Tax Amount</label>
        <input type="text" id="tax_amt" value="<?= number_format($dv['tax_amount'], 2); ?>" readonly class="w-full p-2 border rounded bg-gray-100">
      </div>

      <div>
        <label class="block text-sm font-medium text-green-700 mb-1">Net Amount</label>
        <input type="text" id="net_amt" value="<?= number_format($dv['net_amount'], 2); ?>" readonly class="w-full p-2 border rounded bg-gray-100 font-semibold">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
        <textarea name="remarks" class="w-full p-2 border rounded" rows="3"><?= htmlspecialchars($dv['remarks']); ?></textarea>
      </div>

      <div class="flex items-center space-x-3 pt-2">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Update Payroll DV</button>
        <a href="payroll_dv_list.php" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</a>
      </div>
    </form>
  </div>
</main>

<script>
const gross = document.getElementById('gross');
const pct   = document.getElementById('tax_pct');
const tax   = document.getElementById('tax_amt');
const net   = document.getElementById('net_amt');

function compute() {
    const g = parseFloat(gross.value) || 0;
    const p = parseFloat(pct.value) || 0;
    const t = g * (p / 100);
    tax.value = t.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    net.value = (g - t).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Attach listeners so the UI updates as you type
gross.addEventListener('input', compute);
pct.addEventListener('input', compute);

// Run once on load to populate the read-only fields correctly
window.addEventListener('DOMContentLoaded', compute);
</script>

<?php include 'inc/footer.php'; ob_end_flush(); ?>