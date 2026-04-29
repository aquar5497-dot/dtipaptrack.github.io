<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
ob_start();
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

/* ================= DV NUMBER ================= */
$year  = date('Y');
$month = date('m');
$prefix = "DV-$year-$month";

$stmt = $conn->prepare("
    SELECT dv_number FROM disbursement_vouchers
    WHERE dv_number LIKE CONCAT(?, '%')
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $prefix);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$next = $row ? str_pad((int)substr($row['dv_number'], -3) + 1, 3, '0', STR_PAD_LEFT) : '001';
$dv_number = "$prefix-$next";

/* ================= GET PR FROM PROCEEDINGS ================= */
$selected_pr_id = $_GET['pr_id'] ?? null;

/* ================= PR LIST ================= */
$prs = $conn->query("SELECT id, pr_number, purpose FROM purchase_requests ORDER BY id DESC");

/* ================= SUBMIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $conn->prepare("
        INSERT INTO disbursement_vouchers
        (dv_number, dv_date, supplier, tax_type,
         gross_amount, tax_amount, net_amount, status, pr_id)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "ssssdddsi",
        $_POST['dv_number'],
        $_POST['dv_date'],
        $_POST['supplier'],
        $_POST['tax_label'],
        $_POST['gross_amount'],
        $_POST['tax_amount'],
        $_POST['net_amount'],
        $_POST['status'],
        $_POST['pr_id']
    );

    if ($stmt->execute()) {
        logAudit('DV','CREATE',$conn->insert_id,$_POST['dv_number'],[],['dv_number'=>$_POST['dv_number'],'dv_date'=>$_POST['dv_date'],'supplier'=>$_POST['supplier'],'gross_amount'=>$_POST['gross_amount'],'tax_amount'=>$_POST['tax_amount'],'net_amount'=>$_POST['net_amount'],'status'=>$_POST['status'],'pr_id'=>$_POST['pr_id']]);
        header("Location: dv_list.php");
        exit;
    }
}
?>

<main class="flex-1 p-6">
  <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6">

    <h2 class="text-xl font-bold text-gray-700 mb-4">
      New Disbursement Voucher (DV)
    </h2>

    <form method="post" class="space-y-4">

      <!-- REQUIRED HIDDEN VALUES -->
      <input type="hidden" name="dv_number" value="<?= $dv_number ?>">
      <input type="hidden" name="tax_amount" id="tax_amount">
      <input type="hidden" name="net_amount" id="net_amount">
      <input type="hidden" name="tax_label" id="tax_label">

      <div>
        <label class="block text-sm font-medium">DV Number</label>
        <input type="text" value="<?= $dv_number ?>" readonly
               class="w-full border rounded px-3 py-2 bg-gray-100">
      </div>

      <div>
        <label class="block text-sm font-medium">DV Date</label>
        <input type="date" name="dv_date" required
               class="w-full border px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">Supplier</label>
        <input type="text" name="supplier" id="sd_dv_supplier" required
               class="w-full border px-3 py-2" placeholder="Type or select..." autocomplete="off">
      </div>

      <div>
        <label class="block text-sm font-medium">Tax Type</label>
        <select id="tax_type" required
                class="w-full border px-3 py-2">
          <option value="">-- Select Tax Type --</option>
          <option value="classification">Tax based Classification</option>
          <option value="withholding">Withholding Tax</option>
          <option value="custom">Custom Percentage</option>
        </select>
      </div>

      <!-- Classification Section -->
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
        <select class="w-full border px-3 py-2">
          <option value="goods">Goods 1%</option>
          <option value="services">Services 2%</option>
          <option value="rent">Rent 5%</option>
          <option value="professional">Professional Services 10%</option>
        </select>
      </div>

      <div id="custom" class="hidden">
        <input type="number" step="0.01"
               placeholder="Enter %"
               class="w-full border px-3 py-2">
      </div>

      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="block text-sm font-medium">Gross Amount</label>
          <div id="refBtns" class="flex gap-2" style="display:none!important;">
            <button type="button" onclick="openDocModal('pr')"
              class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-3 py-1 rounded shadow">
              <i class="fas fa-eye"></i> View PR
            </button>
            <button type="button" onclick="openDocModal('po')"
              class="inline-flex items-center gap-1 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold px-3 py-1 rounded shadow">
              <i class="fas fa-eye"></i> View PO
            </button>
          </div>
        </div>
        <input type="number" step="0.01" id="gross"
               name="gross_amount"
               class="w-full border px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium text-red-600">Tax Deduction</label>
        <input type="text" id="tax_display" readonly
               class="w-full border px-3 py-2 bg-gray-100">
      </div>

      <div>
        <label class="block text-sm font-medium text-green-700">Net Amount</label>
        <input type="text" id="net_display" readonly
               class="w-full border px-3 py-2 bg-gray-100 font-semibold">
      </div>

      <div>
        <label class="block text-sm font-medium">Linked PR</label>
        <select name="pr_id" required class="w-full border px-3 py-2">
          <option value="">-- Select Purchase Request --</option>
          <?php while ($pr = $prs->fetch_assoc()): ?>
            <option value="<?= $pr['id'] ?>" <?= ($selected_pr_id == $pr['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($pr['pr_number'].' - '.$pr['purpose']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium">Status</label>
        <select name="status" class="w-full border px-3 py-2">
          <option value="Complete">Complete</option>
          <option value="Lacking">Lacking</option>
        </select>
      </div>

      <div class="flex justify-end gap-2">
        <a href="dv_list.php" class="bg-gray-200 px-4 py-2 rounded">Cancel</a>
        <button class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
      </div>

    </form>
  </div>
</main>

<!-- Document Preview Modal -->
<div id="docModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:.75rem;width:92vw;max-width:1100px;height:88vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.35);overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;background:#1e293b;border-radius:.75rem .75rem 0 0;">
      <span id="modalTitle" style="color:#e2e8f0;font-weight:700;font-size:.95rem;"></span>
      <button onclick="closeDocModal()" style="background:#475569;color:#fff;border:none;border-radius:.375rem;padding:.35rem .75rem;cursor:pointer;font-size:.85rem;">
        <i class="fas fa-times"></i> Close
      </button>
    </div>
    <iframe id="docFrame" src="" style="flex:1;border:none;width:100%;"></iframe>
  </div>
</div>

<script>
const taxType = document.getElementById('tax_type');
const gross   = document.getElementById('gross');
const taxOut  = document.getElementById('tax_display');
const netOut  = document.getElementById('net_display');

const taxH = document.getElementById('tax_amount');
const netH = document.getElementById('net_amount');
const lblH = document.getElementById('tax_label');

const vatTaxDisplay = document.getElementById('vat_registered_tax');
const nonVatTaxDisplay = document.getElementById('nonvat_registered_tax');
const combinedTaxDisplay = document.getElementById('combined_tax_display');

function hideAll() {
  ['classification','withholding','custom'].forEach(id =>
    document.getElementById(id).classList.add('hidden')
  );
}

function calculate() {
  let g = parseFloat(gross.value) || 0;
  let tax = 0, label = '';

  if (taxType.value === 'classification') {

    let vatTax = (g / 1.12) * 0.05;
    let nonVatTax = g * 0.03;

    vatTaxDisplay.value = vatTax.toFixed(2);
    nonVatTaxDisplay.value = nonVatTax.toFixed(2);

    tax = vatTax + nonVatTax;

    combinedTaxDisplay.value = tax.toFixed(2);

    label = `Tax Based Classification (VAT: ${vatTax.toFixed(2)} + Non-VAT: ${nonVatTax.toFixed(2)} = ${tax.toFixed(2)})`;
  }

  if (taxType.value === 'withholding') {
    const rates = {goods:0.01, services:0.02, rent:0.05, professional:0.10};
    let v = document.querySelector('#withholding select').value;
    tax = g * (rates[v] || 0);
    label = v.charAt(0).toUpperCase()+v.slice(1)+' Withholding Tax';
  }

  if (taxType.value === 'custom') {
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

taxType.addEventListener('change', () => {
  hideAll();
  if (taxType.value) document.getElementById(taxType.value).classList.remove('hidden');
  calculate();
});

gross.addEventListener('input', calculate);
document.querySelectorAll('#withholding select, #custom input')
  .forEach(el => el.addEventListener('input', calculate));

// ── PR/PO Reference Modal ──
const prSelect   = document.querySelector('select[name="pr_id"]');
const refBtns    = document.getElementById('refBtns');
const docModal   = document.getElementById('docModal');
const docFrame   = document.getElementById('docFrame');
const modalTitle = document.getElementById('modalTitle');

// PR id → PO id mapping fetched dynamically
const poCache = {};

async function getPoId(prId) {
  if (poCache[prId] !== undefined) return poCache[prId];
  try {
    const r = await fetch(`dropdown_api.php?action=get_po_by_pr&pr_id=${prId}`);
    const d = await r.json();
    poCache[prId] = d.po_id || null;
    return poCache[prId];
  } catch(e) { return null; }
}

function updateRefButtons() {
  if (prSelect.value) {
    refBtns.style.display = 'flex';
  } else {
    refBtns.style.display = 'none';
  }
}

prSelect.addEventListener('change', updateRefButtons);
updateRefButtons();

async function openDocModal(type) {
  const prId = prSelect.value;
  if (!prId) { alert('Please select a Linked PR first.'); return; }
  if (type === 'pr') {
    modalTitle.textContent = '📄 Purchase Request — Read Only';
    docFrame.src = `pr_view.php?id=${prId}&readonly=1`;
  } else {
    const poId = await getPoId(prId);
    if (!poId) { alert('No Purchase Order found for this PR yet.'); return; }
    modalTitle.textContent = '📄 Purchase Order — Read Only';
    docFrame.src = `po_view.php?id=${poId}&readonly=1`;
  }
  docModal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeDocModal() {
  docModal.style.display = 'none';
  docFrame.src = '';
  document.body.style.overflow = '';
}

docModal.addEventListener('click', function(e) {
  if (e.target === docModal) closeDocModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeDocModal();
});
</script>

<script src="assets/smart-dropdown.js"></script>
<script>SmartDropdown.init('#sd_dv_supplier', 'supplier');</script>
<?php ob_end_flush(); ?>