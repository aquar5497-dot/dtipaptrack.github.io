<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$errors = [];
$dv_date = date('Y-m-d');
$payee = '';
$gross_amount = 0.00;
$tax_percentage = 0;
$tax_amount = 0.00;
$net_amount = 0.00;
$remarks = '';
$payroll_id = null;

/* ================================
   AUTO-GENERATE DV NUMBER
================================ */
$year  = date('Y');
$month = date('m');

$stmt = $conn->prepare("
    SELECT MAX(CAST(SUBSTRING_INDEX(dv_number, '-', -1) AS UNSIGNED)) AS max_num
    FROM payroll_dvs
    WHERE dv_number LIKE ?
");
$like = "DV-$year-$month-%";
$stmt->bind_param("s", $like);
$stmt->execute();
$max = $stmt->get_result()->fetch_assoc()['max_num'] ?? 0;
$stmt->close();

$dv_number = "DV-$year-$month-" . str_pad($max + 1, 3, '0', STR_PAD_LEFT);

/* ================================
   FETCH PAYROLL
================================ */
if (isset($_GET['payroll_id'])) {
    $payroll_id = (int)$_GET['payroll_id'];
    $stmt = $conn->prepare("
        SELECT employee_name, salary_amount
        FROM payroll_requests
        WHERE id = ?
    ");
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $payee = $row['employee_name'];
        $gross_amount = $row['salary_amount'];
    }
    $stmt->close();
}

/* ================================
   SAVE
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $dv_number = $_POST['dv_number'];
    $dv_date = $_POST['dv_date'];
    $payee = $_POST['payee'];
    $gross_amount = (float)$_POST['gross_amount'];
    $tax_percentage = (float)$_POST['tax_percentage'];
    $tax_amount = $gross_amount * ($tax_percentage / 100);
    $net_amount = $gross_amount - $tax_amount;
    $remarks = $_POST['remarks'];
    $payroll_id = (int)$_POST['payroll_id'];

    if (empty($dv_number) || empty($dv_date) || empty($payee)) {
        $errors[] = "Please fill in all required fields.";
    }

    if (!$errors) {
        $stmt = $conn->prepare("
            INSERT INTO payroll_dvs
            (dv_number, dv_date, payee, gross_amount, tax_percentage, tax_amount, net_amount, remarks, payroll_id)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "sssdddssi",
            $dv_number,
            $dv_date,
            $payee,
            $gross_amount,
            $tax_percentage,
            $tax_amount,
            $net_amount,
            $remarks,
            $payroll_id
        );

        if ($stmt->execute()) {
            header("Location: payroll_dv_list.php?success=Payroll DV created successfully");
            exit;
        }
        $errors[] = $stmt->error;
    }
}
?>

<main class="flex-1 p-6 overflow-y-auto bg-gray-50">
<div class="max-w-3xl mx-auto">
<h1 class="text-2xl font-bold mb-4">Payroll Disbursement Voucher</h1>

<form method="post" class="bg-white p-6 rounded shadow space-y-4">
<input type="hidden" name="payroll_id" value="<?= $payroll_id ?>">

<div>
<label class="block text-sm font-medium">DV Number</label>
<input type="text" name="dv_number" value="<?= $dv_number ?>" readonly class="w-full p-2 border rounded bg-gray-100">
</div>

<div>
<label class="block text-sm font-medium">DV Date</label>
<input type="date" name="dv_date" value="<?= $dv_date ?>" class="w-full p-2 border rounded">
</div>

<div>
<label class="block text-sm font-medium">Payee</label>
<input type="text" name="payee" id="sd_pdv_payee" value="<?= htmlspecialchars($payee) ?>" class="w-full p-2 border rounded" placeholder="Type or select..." autocomplete="off">
</div>

<div>
<label class="block text-sm font-medium">Gross Amount</label>
<input type="number" step="0.01" id="gross" name="gross_amount" value="<?= $gross_amount ?>" class="w-full p-2 border rounded">
</div>

<div>
<label class="block text-sm font-medium">Custom Tax (%)</label>
<input type="number" step="0.01" id="tax_pct" name="tax_percentage" class="w-full p-2 border rounded">
</div>

<div>
<label class="block text-sm font-medium text-red-600">Tax Amount</label>
<input type="text" id="tax_amt" readonly class="w-full p-2 border rounded bg-gray-100">
</div>

<div>
<label class="block text-sm font-medium text-green-700">Net Amount</label>
<input type="text" id="net_amt" readonly class="w-full p-2 border rounded bg-gray-100 font-semibold">
</div>

<div>
<label class="block text-sm font-medium">Remarks</label>
<textarea name="remarks" class="w-full p-2 border rounded"><?= htmlspecialchars($remarks) ?></textarea>
</div>

<div class="flex gap-3">
<button class="bg-blue-600 text-white px-4 py-2 rounded">Save Payroll DV</button>
<a href="payroll_dv_list.php" class="bg-gray-200 px-4 py-2 rounded">Cancel</a>
</div>
</form>
</div>
</main>

<script>
const gross = document.getElementById('gross');
const pct = document.getElementById('tax_pct');
const tax = document.getElementById('tax_amt');
const net = document.getElementById('net_amt');

function compute() {
  const g = parseFloat(gross.value) || 0;
  const p = parseFloat(pct.value) || 0;
  const t = g * (p / 100);
  tax.value = t.toFixed(2);
  net.value = (g - t).toFixed(2);
}

gross.addEventListener('input', compute);
pct.addEventListener('input', compute);
</script>

<script src="assets/smart-dropdown.js"></script>
<script>SmartDropdown.init('#sd_pdv_payee', 'payee_name');</script>
<?php ob_end_flush(); ?>
