<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// Get DV ID
if (!isset($_GET['id'])) {
    header("Location: dv_list.php");
    exit;
}
$id = intval($_GET['id']);

// Fetch existing DV record
$stmt = $conn->prepare("SELECT * FROM disbursement_vouchers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$dv = $stmt->get_result()->fetch_assoc();

if (!$dv) {
    echo "<script>alert('DV not found.'); window.location='dv_list.php';</script>";
    exit;
}

// Fetch PRs for linking
$prs = $conn->query("SELECT id, pr_number, purpose FROM purchase_requests ORDER BY id DESC");

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We use the hidden fields calculated by JavaScript, same as dv_add.php
    $stmt = $conn->prepare("UPDATE disbursement_vouchers SET 
        dv_date = ?, 
        supplier = ?, 
        tax_type = ?, 
        gross_amount = ?, 
        tax_amount = ?, 
        net_amount = ?, 
        status = ?, 
        pr_id = ?
        WHERE id = ?");

    $stmt->bind_param(
        "sssdddssi",
        $_POST['dv_date'],
        $_POST['supplier'],
        $_POST['tax_label'], // The descriptive label from JS
        $_POST['gross_amount'],
        $_POST['tax_amount'],
        $_POST['net_amount'],
        $_POST['status'],
        $_POST['pr_id'],
        $id
    );

    if ($stmt->execute()) {
        echo "<script>alert('Disbursement Voucher updated successfully!'); window.location='dv_list.php';</script>";
        exit;
    }
}
?>

<main class="flex-1 p-6">
  <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6">
    <h2 class="text-xl font-bold text-gray-700 mb-4">Edit Disbursement Voucher (DV)</h2>

    <form method="post" class="space-y-4">
      <input type="hidden" name="tax_amount" id="tax_amount" value="<?= $dv['tax_amount'] ?>">
      <input type="hidden" name="net_amount" id="net_amount" value="<?= $dv['net_amount'] ?>">
      <input type="hidden" name="tax_label" id="tax_label" value="<?= htmlspecialchars($dv['tax_type']) ?>">

      <div>
        <label class="block text-sm font-medium">DV Number</label>
        <input type="text" value="<?= htmlspecialchars($dv['dv_number']) ?>" readonly 
               class="w-full border rounded px-3 py-2 bg-gray-100">
      </div>

      <div>
        <label class="block text-sm font-medium">DV Date</label>
        <input type="date" name="dv_date" value="<?= $dv['dv_date'] ?>" required 
               class="w-full border px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Supplier</label>
        <input type="text" name="supplier" value="<?= htmlspecialchars($dv['supplier']) ?>" required 
               class="w-full border px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Tax Category</label>
        <select id="tax_category_selector" required class="w-full border px-3 py-2">
          <option value="">-- Select Category --</option>
          <option value="classification">Tax based Classification</option>
          <option value="withholding">Withholding Tax</option>
          <option value="custom">Custom Percentage</option>
        </select>
      </div>

      <!-- Classification Section - Shows both VAT and Non-VAT calculations -->
      <div id="classification" class="hidden space-y-3">
        <div class="bg-blue-50 p-3 rounded border border-blue-200">
          <p class="text-sm font-semibold text-blue-800 mb-2">Tax Based Classification (Computed Automatically)</p>
          
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-600">VAT Registered (5%)</label>
              <input type="text" id="vat_registered_tax" readonly
                     class="w-full border px-3 py-2 bg-white text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600">Non-VAT Registered (3%)</label>
              <input type="text" id="nonvat_registered_tax" readonly
                     class="w-full border px-3 py-2 bg-white text-sm">
            </div>
          </div>
          
          <div class="mt-2 pt-2 border-t border-blue-200">
            <label class="block text-xs font-bold text-blue-900">Combined Base Tax Total</label>
            <input type="text" id="combined_tax_display" readonly
                   class="w-full border px-3 py-2 bg-blue-100 font-bold text-blue-900">
          </div>
        </div>
      </div>

      <div id="withholding" class="hidden">
        <label class="block text-xs text-gray-500">Withholding Rate</label>
        <select class="w-full border px-3 py-2">
          <option value="goods">Goods 1%</option>
          <option value="services">Services 2%</option>
          <option value="rent">Rent 5%</option>
          <option value="professional">Professional Services 10%</option>
        </select>
      </div>

      <div id="custom" class="hidden">
        <label class="block text-xs text-gray-500">Custom Rate (%)</label>
        <input type="number" step="0.01" placeholder="Enter %" class="w-full border px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Gross Amount</label>
        <input type="number" step="0.01" id="gross" name="gross_amount" 
               value="<?= $dv['gross_amount'] ?>" required class="w-full border px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium text-red-600">Tax Deduction</label>
        <input type="text" id="tax_display" readonly 
               value="<?= number_format($dv['tax_amount'], 2) ?>"
               class="w-full border px-3 py-2 bg-gray-100">
      </div>

      <div>
        <label class="block text-sm font-medium text-green-700">Net Amount</label>
        <input type="text" id="net_display" readonly 
               value="<?= number_format($dv['net_amount'], 2) ?>"
               class="w-full border px-3 py-2 bg-gray-100 font-semibold">
      </div>

      <div>
        <label class="block text-sm font-medium">Linked PR</label>
        <select name="pr_id" required class="w-full border px-3 py-2">
          <?php while ($pr = $prs->fetch_assoc()): ?>
            <option value="<?= $pr['id'] ?>" <?= ($dv['pr_id'] == $pr['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($pr['pr_number'].' - '.$pr['purpose']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium">Status</label>
        <select name="status" class="w-full border px-3 py-2">
          <option value="Complete" <?= ($dv['status'] == 'Complete') ? 'selected' : '' ?>>Complete</option>
          <option value="Lacking" <?= ($dv['status'] == 'Lacking') ? 'selected' : '' ?>>Lacking</option>
        </select>
      </div>

      <div class="flex justify-end gap-2">
        <a href="dv_list.php" class="bg-gray-200 px-4 py-2 rounded">Cancel</a>
        <button class="bg-blue-600 text-white px-4 py-2 rounded">Update</button>
      </div>
    </form>
  </div>
</main>

<script>
const taxCatSelector = document.getElementById('tax_category_selector');
const gross = document.getElementById('gross');
const taxOut = document.getElementById('tax_display');
const netOut = document.getElementById('net_display');

const taxH = document.getElementById('tax_amount');
const netH = document.getElementById('net_amount');
const lblH = document.getElementById('tax_label');

// Classification specific elements
const vatTaxDisplay = document.getElementById('vat_registered_tax');
const nonVatTaxDisplay = document.getElementById('nonvat_registered_tax');
const combinedTaxDisplay = document.getElementById('combined_tax_display');

// Existing values from DB to help JS "detect" the state
const existingLabel = "<?= addslashes($dv['tax_type']) ?>";
const existingGross = parseFloat("<?= $dv['gross_amount'] ?>") || 0;
const existingTax = parseFloat("<?= $dv['tax_amount'] ?>") || 0;

function hideAll() {
    ['classification','withholding','custom'].forEach(id => 
        document.getElementById(id).classList.add('hidden')
    );
}

function calculate() {
    let g = parseFloat(gross.value) || 0;
    let tax = 0, label = '';

    if (taxCatSelector.value === 'classification') {
        // Calculate both VAT and Non-VAT simultaneously
        let vatTax = (g / 1.12) * 0.05;      // VAT Registered: 5% of net
        let nonVatTax = g * 0.03;             // Non-VAT Registered: 3% of gross
        
        // Display individual calculations
        vatTaxDisplay.value = vatTax.toFixed(2);
        nonVatTaxDisplay.value = nonVatTax.toFixed(2);
        
        // Combined total becomes the base tax
        tax = vatTax + nonVatTax;
        
        // Display combined total in the breakdown section
        combinedTaxDisplay.value = tax.toFixed(2);
        
        label = `Tax Based Classification (VAT: ${vatTax.toFixed(2)} + Non-VAT: ${nonVatTax.toFixed(2)} = ${tax.toFixed(2)})`;
    }

    if (taxCatSelector.value === 'withholding') {
        const rates = {goods:0.01, services:0.02, rent:0.05, professional:0.10};
        let v = document.querySelector('#withholding select').value;
        tax = g * (rates[v] || 0);
        label = v.charAt(0).toUpperCase()+v.slice(1)+' Withholding Tax';
    }

    if (taxCatSelector.value === 'custom') {
        let p = parseFloat(document.querySelector('#custom input').value) || 0;
        tax = g * (p / 100);
        label = `Custom Tax - ${p}%`;
    }

    taxOut.value = tax.toFixed(2);
    netOut.value = (g - tax).toFixed(2);
    taxH.value = tax;
    netH.value = g - tax;
    lblH.value = label;
}

// Logic to determine initial state based on the string stored in the database
function initializeForm() {
    // Check for Tax Based Classification (new format with breakdown or old format)
    if (existingLabel.includes('Tax Based Classification')) {
        taxCatSelector.value = 'classification';
        document.getElementById('classification').classList.remove('hidden');
        
        // Calculate and display both taxes based on stored gross amount
        let vatTax = (existingGross / 1.12) * 0.05;
        let nonVatTax = existingGross * 0.03;
        
        vatTaxDisplay.value = vatTax.toFixed(2);
        nonVatTaxDisplay.value = nonVatTax.toFixed(2);
        combinedTaxDisplay.value = existingTax.toFixed(2);
    } 
    else if (existingLabel.includes('VAT Registered') || existingLabel.includes('Non-VAT')) {
        // Handle old format records - convert to new classification logic
        taxCatSelector.value = 'classification';
        document.getElementById('classification').classList.remove('hidden');
        
        let vatTax = (existingGross / 1.12) * 0.05;
        let nonVatTax = existingGross * 0.03;
        
        vatTaxDisplay.value = vatTax.toFixed(2);
        nonVatTaxDisplay.value = nonVatTax.toFixed(2);
        combinedTaxDisplay.value = existingTax.toFixed(2);
    } 
    else if (existingLabel.includes('Withholding')) {
        taxCatSelector.value = 'withholding';
        document.getElementById('withholding').classList.remove('hidden');
        const type = existingLabel.split(' ')[0].toLowerCase();
        document.querySelector('#withholding select').value = type;
    } 
    else if (existingLabel.includes('Custom')) {
        taxCatSelector.value = 'custom';
        document.getElementById('custom').classList.remove('hidden');
        const percent = existingLabel.match(/\d+(\.\d+)?/);
        if(percent) document.querySelector('#custom input').value = percent[0];
    }
    
    calculate();
}

taxCatSelector.addEventListener('change', () => {
    hideAll();
    if (taxCatSelector.value) document.getElementById(taxCatSelector.value).classList.remove('hidden');
    calculate();
});

gross.addEventListener('input', calculate);
document.querySelectorAll('#withholding select, #custom input')
    .forEach(el => el.addEventListener('input', calculate));

// Initialize on page load
window.onload = initializeForm;
</script>

<?php 
include 'inc/footer.php';
ob_end_flush(); 
?>