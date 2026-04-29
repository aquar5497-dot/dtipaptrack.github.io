<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo "<main class='flex-1 p-6 text-red-600'>Invalid PR ID.</main>"; include 'inc/footer.php'; exit; }
$pr = $conn->query("SELECT * FROM purchase_requests WHERE id=$id")->fetch_assoc();
if (!$pr) { echo "<main class='flex-1 p-6 text-red-600'>Purchase Request not found.</main>"; include 'inc/footer.php'; exit; }

$rfq = $conn->query("SELECT * FROM rfqs WHERE pr_id=$id ORDER BY id DESC LIMIT 1")->fetch_assoc();
$po  = $conn->query("SELECT * FROM purchase_orders WHERE pr_id=$id ORDER BY id DESC LIMIT 1")->fetch_assoc();
$iar = null;
if ($po) { $poid=(int)$po['id']; $iar=$conn->query("SELECT * FROM iars WHERE po_id=$poid ORDER BY id DESC LIMIT 1")->fetch_assoc(); }
$dv  = $conn->query("SELECT * FROM disbursement_vouchers WHERE pr_id=$id ORDER BY id DESC LIMIT 1")->fetch_assoc();

// Load existing pr_document rows
$pr_items = [];
$res = $conn->query("SELECT * FROM pr_document WHERE pr_id=$id ORDER BY sort_order ASC");
while ($row = $res->fetch_assoc()) $pr_items[] = $row;

function safe($v){return htmlspecialchars($v??'',ENT_QUOTES);}
function fmt_date($d){return $d&&$d!='0000-00-00'?date('F d, Y',strtotime($d)):'';}
function val_date($d){return $d&&$d!='0000-00-00'?date('Y-m-d',strtotime($d)):'';}
$canEdit = hasPermission(PERM_PURCHASE_REQUEST);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;}
.doc-toolbar{display:flex;align-items:center;gap:.5rem;padding:.65rem 1.25rem;background:#1e293b;border-bottom:1px solid #334155;flex-wrap:wrap;}
.doc-toolbar h2{color:#e2e8f0;font-size:.88rem;font-weight:700;margin:0;flex:1;min-width:130px;}
.tbtn{display:inline-flex;align-items:center;gap:.35rem;padding:.38rem .8rem;border-radius:.375rem;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;font-family:inherit;white-space:nowrap;}
.tbtn-back{background:#334155;color:#cbd5e1;}.tbtn-back:hover{background:#475569;color:#fff;}
.tbtn-print{background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;}.tbtn-print:hover{filter:brightness(1.1);}
.tbtn-excel{background:linear-gradient(135deg,#059669,#047857);color:#fff;}.tbtn-excel:hover{filter:brightness(1.1);}
.tbtn-report{background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;}.tbtn-report:hover{filter:brightness(1.1);}
.tbtn-draft{background:linear-gradient(135deg,#d97706,#b45309);color:#fff;}.tbtn-draft:hover{filter:brightness(1.1);}
.tbtn-save{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;}.tbtn-save:hover{filter:brightness(1.1);}
#draftBar{font-size:.7rem;padding:.22rem .55rem;border-radius:.25rem;background:#0f172a;color:#64748b;font-style:italic;}
#draftBar.saved{color:#fbbf24;} #draftBar.final{color:#4ade80;}
.doc-page-bg{background:#cbd5e1;min-height:calc(100vh - 56px);padding:1.5rem;display:flex;justify-content:center;}
.doc-page{background:#fff;width:816px;max-width:100%;box-shadow:0 4px 32px rgba(0,0,0,.18);font-family:'Times New Roman',Times,serif;font-size:11.5pt;padding:18pt 22pt;}
.doc-title{text-align:center;font-weight:bold;font-size:14pt;letter-spacing:.03em;margin-bottom:4pt;}
.doc-outer{border:1.5px solid #000;width:100%;}
.doc-row{display:flex;border-bottom:1px solid #000;}
.doc-row:last-child{border-bottom:none;}
.doc-cell{padding:3pt 5pt;border-right:1px solid #000;}
.doc-cell:last-child{border-right:none;}
.doc-label{font-weight:bold;font-size:9.5pt;white-space:nowrap;}
/* Contenteditable fields */
.doc-field{border:none;outline:none;width:100%;font-family:inherit;font-size:11pt;background:transparent;padding:0;min-height:14pt;display:inline-block;}
.doc-field:focus{background:#fffde7;border-radius:2px;}
.doc-field[contenteditable="true"]:empty:before{content:attr(data-ph);color:#bbb;font-style:italic;}
/* Input fields that look like doc-field (for dropdowns) */
.doc-input-field{border:none;outline:none;font-family:'Times New Roman',Times,serif;font-size:11pt;background:transparent;padding:0;min-height:14pt;width:100%;display:block;}
.doc-input-field:focus{background:#fffde7;border-radius:2px;}
.doc-input-field::placeholder{color:#bbb;font-style:italic;}
.doc-date-field{border:none;outline:none;font-family:'Times New Roman',Times,serif;font-size:11pt;background:transparent;padding:0;min-height:14pt;display:inline-block;color:inherit;}
.doc-date-field:focus{background:#fffde7;border-radius:2px;}
.items-table{width:100%;border-collapse:collapse;}
.items-table th{background:#f0f0f0;font-weight:bold;font-size:9pt;padding:3pt 5pt;border:1px solid #aaa;text-align:center;}
.items-table td{font-size:10pt;padding:3pt 5pt;border:1px solid #ddd;min-height:16pt;}
.items-table td[contenteditable]:focus{background:#fffde7;outline:none;}
.total-row td{font-weight:bold;border-top:2px solid #000;background:#f9f9f9;}
.sig-name{border-bottom:1px solid #555;display:block;width:100%;font-family:inherit;font-size:11pt;background:transparent;outline:none;min-height:14pt;padding:1pt 2pt;margin-top:1pt;}
.sig-name:focus{background:#fffde7;}
.sig-name[contenteditable]:empty:before{content:attr(data-ph);color:#bbb;font-style:italic;}
.no-print-note{font-size:8pt;color:#999;font-style:italic;text-align:center;padding:2pt;margin-bottom:4pt;}
/* Toast notification */
#docToast{position:fixed;bottom:1.5rem;right:1.5rem;padding:.7rem 1.2rem;border-radius:.5rem;font-size:.85rem;font-weight:600;z-index:9999;opacity:0;transition:opacity .3s;pointer-events:none;}
#docToast.show{opacity:1;}
#docToast.success{background:#16a34a;color:#fff;}
#docToast.error{background:#dc2626;color:#fff;}
@media print{
  body,html{margin:0!important;padding:0!important;background:#fff!important;}
  .doc-toolbar{display:none!important;}
  .doc-page-bg{background:transparent!important;padding:0!important;min-height:auto!important;display:block!important;}
  .doc-page{box-shadow:none!important;padding:8pt 12pt!important;width:100%!important;max-width:none!important;font-size:10pt!important;margin:0!important;}
  [contenteditable]{outline:none!important;background:transparent!important;}
  .doc-input-field,.doc-date-field{background:transparent!important;border:none!important;}
  .no-print-note,.linked-docs{display:none!important;}
  #sidebar,header.dti-header{display:none!important;}
  .sb-content-wrap{margin-left:0!important;}
  [contenteditable]:empty::before,.doc-field:empty::before,.sig-name:empty::before{content:none!important;display:none!important;}
}
body.doc-readonly-mode .doc-field[contenteditable="false"]{cursor:default!important;background:transparent!important;}
body.doc-readonly-mode .sig-name[contenteditable="false"]{cursor:default!important;background:transparent!important;}
body.doc-readonly-mode .items-table td[contenteditable="false"]{cursor:default!important;background:transparent!important;}
body.doc-readonly-mode .doc-input-field{pointer-events:none!important;background:transparent!important;}
body.doc-readonly-mode .doc-date-field{pointer-events:none!important;background:transparent!important;}
</style>
<main class="flex-1 overflow-y-auto">
<div class="doc-toolbar">
  <a href="proceedings.php" class="tbtn tbtn-back"><i class="fas fa-arrow-left"></i> Back</a>
  <h2><i class="fas fa-file-alt" style="color:#60a5fa;margin-right:4px;"></i>Purchase Request — <?php echo safe($pr['pr_number']); ?></h2>
  <span id="draftBar">No draft saved</span>
  <button onclick="saveDraft(false)" class="tbtn tbtn-draft"><i class="fas fa-save"></i> Save Draft</button>
  <button onclick="saveDocToDB()" class="tbtn tbtn-save"><i class="fas fa-check-circle"></i> Save</button>
  <button onclick="window.print()" class="tbtn tbtn-print"><i class="fas fa-print"></i> Print</button>
  <button onclick="exportExcelDoc()" class="tbtn tbtn-excel"><i class="fas fa-file-excel"></i> Export Excel</button>
  <a href="report.php?search_pr=<?php echo urlencode($pr['pr_number']); ?>" class="tbtn tbtn-report"><i class="fas fa-chart-bar"></i> View Report</a>
</div>
<div class="doc-page-bg">
<div id="docContent" class="doc-page">
  <div class="no-print-note">✏️ Click any field to edit &bull; <strong>Save Draft</strong> for local backup &bull; <strong>Save</strong> to write to database</div>
  <div class="doc-title">PURCHASE REQUEST</div>
  <div class="doc-outer">
    <!-- Row 1: Entity Name | Fund Cluster -->
    <div class="doc-row">
      <div class="doc-cell" style="width:55%;display:flex;align-items:center;gap:4pt;">
        <span class="doc-label">Entity Name:</span>
        <input type="text" id="sd_entity_name" data-key="entity_name"
               class="doc-input-field" autocomplete="off"
               value="<?php echo safe($pr['entity_name']); ?>"
               placeholder="Entity Name">
      </div>
      <div class="doc-cell" style="width:45%;display:flex;align-items:center;gap:4pt;">
        <span class="doc-label">Fund Cluster:</span>
        <input type="text" id="sd_fund_cluster" data-key="fund_cluster"
               class="doc-input-field" autocomplete="off"
               value="<?php echo safe($pr['fund_cluster']); ?>"
               placeholder="Fund Cluster">
      </div>
    </div>
    <!-- Row 2: Office/Section | PR No. | Date | Resp Center -->
    <div class="doc-row">
      <div class="doc-cell" style="width:22%;display:flex;flex-direction:column;justify-content:flex-start;padding-top:3pt;">
        <span class="doc-label">Office/Section:</span>
        <input type="text" data-key="office_section"
               class="doc-input-field" style="margin-top:3pt;"
               value="<?php echo safe($pr['office_section'] ?? ''); ?>"
               placeholder="Office / Section">
      </div>
      <div class="doc-cell" style="width:78%;">
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label">PR No.:</span>
          <span style="font-weight:bold;color:#1a3a6b;font-size:11pt;"><?php echo safe($pr['pr_number']); ?></span>
          <span style="margin-left:auto;display:flex;align-items:center;gap:4pt;">
            <span class="doc-label">Date:</span>
            <input type="date" data-key="pr_date" class="doc-date-field"
                   value="<?php echo val_date($pr['pr_date']); ?>">
          </span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;">
          <span class="doc-label">Responsibility Center Code:</span>
          <span class="doc-field" contenteditable="true" data-key="resp_center" data-ph="__________" style="min-width:100pt;border-bottom:1px solid #555;"></span>
        </div>
      </div>
    </div>
    <!-- Items Table -->
    <div class="doc-row" style="border-bottom:none;">
      <table class="items-table" style="margin:0;border:none;">
        <thead>
          <tr>
            <th style="width:8%;font-size:8.5pt;">Stock/<br>Property<br>No.</th>
            <th style="width:9%;">Unit</th>
            <th style="width:49%;text-align:left;">Item Description</th>
            <th style="width:9%;">Quantity</th>
            <th style="width:12.5%;">Unit Cost</th>
            <th style="width:12.5%;">Total Cost</th>
          </tr>
        </thead>
        <tbody id="itemsBody" data-table="itemsBody">
          <?php
          $prRowCount = max(count($pr_items), 17);
          $prStockCounter = 1;
          for ($i = 0; $i < $prRowCount; $i++):
            $row  = $pr_items[$i] ?? [];
            $unit = $row['unit']             ?? '';
            $desc = $row['item_description'] ?? '';
            $qty  = (isset($row['quantity'])   && $row['quantity']  !== null && $row['quantity']  !== '') ? $row['quantity']  : '';
            $uc   = (isset($row['unit_cost'])  && $row['unit_cost'] !== null && $row['unit_cost'] !== '') ? $row['unit_cost'] : '';
            $tc   = (isset($row['total_cost']) && $row['total_cost']!== null && $row['total_cost']!== '') ? $row['total_cost']: '';
            $hasVal = (trim($unit)!=='' || trim($desc)!=='' || $qty!=='' || $uc!=='' || $tc!=='');
            $autoSno = $hasVal ? $prStockCounter++ : '';
          ?>
          <tr style="<?php echo $hasVal?'':'height:18pt;'; ?>">
            <td class="sno-cell" contenteditable="true" style="text-align:center;vertical-align:middle;"><?php echo htmlspecialchars((string)$autoSno,ENT_QUOTES); ?></td>
            <td contenteditable="true" style="vertical-align:middle;"><?php echo safe($unit); ?></td>
            <td contenteditable="true"><?php echo safe($desc); ?></td>
            <td contenteditable="true" style="text-align:center;vertical-align:middle;"><?php echo $qty!==''?safe((string)(float)$qty):''; ?></td>
            <td contenteditable="true" style="text-align:right;vertical-align:middle;"><?php echo $uc!==''?number_format((float)$uc,2):''; ?></td>
            <td contenteditable="true" style="text-align:right;vertical-align:middle;"><?php echo $tc!==''?number_format((float)$tc,2):''; ?></td>
          </tr>
          <?php endfor; ?>
          <tr class="total-row">
            <td colspan="5" style="text-align:right;font-size:9.5pt;padding-right:5pt;">TOTAL AMOUNT:</td>
            <td id="totalAmtCell" style="text-align:right;font-weight:bold;font-size:11pt;">
              <?php
              $dbTotal = 0;
              foreach ($pr_items as $r) $dbTotal += (float)$r['total_cost'];
              echo $dbTotal > 0 ? number_format($dbTotal, 2) : (($pr['total_amount'] && $pr['total_amount'] > 0) ? number_format((float)$pr['total_amount'], 2) : '');
              ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <!-- Purpose -->
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;display:flex;align-items:center;gap:4pt;">
        <span class="doc-label">Purpose:</span>
        <span class="doc-field" contenteditable="true" data-key="purpose" data-ph="State purpose here" style="flex:1;"><?php echo safe($pr['purpose']); ?></span>
      </div>
    </div>
    <!-- PAP Code -->
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:4pt 5pt;">
        <div style="display:flex;align-items:center;gap:4pt;flex-wrap:wrap;">
          <span style="font-size:9.5pt;margin-left:40pt;">PAP code:</span>
          <span class="doc-field" contenteditable="true" data-key="pap_code" data-ph="< >" style="min-width:60pt;border-bottom:1px solid #555;"></span>
          <span style="font-size:9.5pt;">, Fund cluster:</span>
          <span class="doc-field" contenteditable="true" data-key="pap_fund" data-ph="< >" style="min-width:60pt;border-bottom:1px solid #555;"><?php echo safe($pr['fund_cluster']); ?></span>
        </div>
      </div>
    </div>
    <div class="doc-row" style="height:8pt;"></div>
    <!-- Sig Headers -->
    <div class="doc-row">
      <div class="doc-cell" style="width:50%;text-align:center;padding:3pt 5pt;"><span style="font-size:9.5pt;font-weight:600;">Requested by:</span></div>
      <div class="doc-cell" style="width:50%;text-align:center;padding:3pt 5pt;"><span style="font-size:9.5pt;font-weight:600;">Approved by:</span></div>
    </div>
    <!-- Sig Block -->
    <div class="doc-row">
      <div class="doc-cell" style="width:50%;padding:4pt 10pt;">
        <div style="display:flex;align-items:flex-start;gap:6pt;margin-bottom:6pt;">
          <span class="doc-label" style="white-space:nowrap;padding-top:1pt;">Signature:</span>
          <span style="flex:1;border-bottom:1.5px solid #000;min-height:26pt;display:block;"></span>
        </div>
        <div style="margin-bottom:3pt;"><span class="doc-label">Printed Name:</span>
          <span class="sig-name" contenteditable="true" data-key="req_name" data-ph="Full Name of Requestor"></span>
        </div>
        <div><span class="doc-label">Designation:</span>
          <span class="sig-name" contenteditable="true" data-key="req_desig" data-ph="Designation">Division Chief, BDD</span>
        </div>
      </div>
      <div class="doc-cell" style="width:50%;padding:4pt 10pt;">
        <div style="min-height:26pt;border-bottom:1.5px solid #000;margin-bottom:6pt;"></div>
        <div style="margin-bottom:3pt;"><span class="doc-label">Printed Name:</span>
          <span class="sig-name" contenteditable="true" data-key="appr_name" data-ph="Full Name of Approver"></span>
        </div>
        <div><span class="doc-label">Designation:</span>
          <span class="sig-name" contenteditable="true" data-key="appr_desig" data-ph="Designation">City Director</span>
        </div>
      </div>
    </div>
  </div><!-- /doc-outer -->

  <?php if($rfq||$po||$iar||$dv): ?>
  <div class="linked-docs" style="margin-top:8pt;padding:4pt 6pt;background:#eff6ff;border-radius:4px;font-size:8.5pt;border:1px solid #bfdbfe;">
    <strong style="color:#1d4ed8;">Linked Documents:</strong>
    <?php if($rfq): ?>&nbsp;<a href="rfq_view.php?id=<?php echo $rfq['id']; ?>" style="color:#2563eb;"><?php echo safe($rfq['rfq_number']); ?></a><?php endif; ?>
    <?php if($po): ?>&nbsp;&bull;&nbsp;<a href="po_view.php?id=<?php echo $po['id']; ?>" style="color:#7c3aed;"><?php echo safe($po['po_number']); ?></a><?php endif; ?>
    <?php if($iar): ?>&nbsp;&bull;&nbsp;<a href="iar_view.php?id=<?php echo $iar['id']; ?>" style="color:#d97706;"><?php echo safe($iar['iar_number']); ?></a><?php endif; ?>
    <?php if($dv): ?>&nbsp;&bull;&nbsp;<a href="dv_view.php?id=<?php echo $dv['id']; ?>" style="color:#dc2626;"><?php echo safe($dv['dv_number']); ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</div></div>
</main>

<div id="docToast"></div>

<script>
window.DOC_READONLY = <?php echo $canEdit ? 'false' : 'true'; ?>;
window.PR_ID = <?php echo $id; ?>;
</script>
<script src="assets/doc_utils.js"></script>
<script src="assets/smart-dropdown.js"></script>
<script>
/* ── Smart Dropdowns for Entity Name & Fund Cluster ── */
SmartDropdown.init('#sd_entity_name', 'entity_name');
SmartDropdown.init('#sd_fund_cluster', 'fund_cluster');

/* ── Auto-recompute Stock/Property No. ── */
function recomputeStockNos() {
  var n = 1;
  document.querySelectorAll('#itemsBody tr:not(.total-row)').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length < 6) return;
    var unit = (tds[1].innerText || '').trim();
    var desc = (tds[2].innerText || '').trim();
    var qty  = (tds[3].innerText || '').trim();
    var uc   = (tds[4].innerText || '').trim();
    var tc   = (tds[5].innerText || '').trim();
    var hasVal = unit || desc || qty || uc || tc;
    tds[0].innerText = hasVal ? n++ : '';
  });
}

/* ── Auto-calculate Total Cost ── */
document.getElementById('itemsBody').addEventListener('input', function(e) {
  var td = e.target;
  if (!td || td.tagName !== 'TD') return;
  var tr = td.closest('tr');
  if (!tr || tr.classList.contains('total-row')) return;
  var tds = tr.querySelectorAll('td');
  if (tds.length < 6) return;
  var qty = parseFloat((tds[3].innerText||'').replace(/,/g,'')) || 0;
  var uc  = parseFloat((tds[4].innerText||'').replace(/,/g,'')) || 0;
  if (qty > 0 && uc > 0) {
    tds[5].innerText = (qty * uc).toFixed(2);
  }
  recomputeStockNos();
  updatePrTotal();
  autoSaveDraft();
});

function updatePrTotal() {
  var total = 0;
  document.querySelectorAll('#itemsBody tr:not(.total-row)').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length >= 6) total += parseFloat((tds[5].innerText||'').replace(/,/g,'')) || 0;
  });
  document.getElementById('totalAmtCell').innerText = total > 0 ? total.toFixed(2) : '';
}

/* ── Re-run after draft load (doc_utils DOMContentLoaded runs first) ── */
document.addEventListener('DOMContentLoaded', function() {
  recomputeStockNos();
  updatePrTotal();
});

/* ── Save to Database ── */
function saveDocToDB() {
  if (window.DOC_READONLY) return;
  var btn = document.querySelector('.tbtn-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  // Collect header fields
  function gInput(key, def) {
    var el = document.querySelector('[data-key="' + key + '"]');
    if (!el) return def || '';
    return (el.value !== undefined ? el.value : (el.innerText || '')).trim();
  }

  // Collect item rows
  var items = [];
  document.querySelectorAll('#itemsBody tr:not(.total-row)').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length < 6) return;
    items.push({
      stock:       (tds[0].innerText||'').trim(),
      unit:        (tds[1].innerText||'').trim(),
      description: (tds[2].innerText||'').trim(),
      quantity:    (tds[3].innerText||'').replace(/,/g,'').trim(),
      unit_cost:   (tds[4].innerText||'').replace(/,/g,'').trim(),
      total_cost:  (tds[5].innerText||'').replace(/,/g,'').trim()
    });
  });

  // Calculate total from items
  var totalAmt = 0;
  items.forEach(function(it) { totalAmt += parseFloat(it.total_cost) || 0; });

  var fd = new FormData();
  fd.append('pr_id',          PR_ID);
  fd.append('pr_date',        gInput('pr_date'));
  fd.append('entity_name',    gInput('entity_name'));
  fd.append('fund_cluster',   gInput('fund_cluster'));
  fd.append('office_section', gInput('office_section'));
  fd.append('total_amount',   totalAmt);
  fd.append('purpose',        gInput('purpose'));
  fd.append('items',          JSON.stringify(items));

  fetch('save_pr_doc.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Save';
      if (data.success) {
        showToast('PR saved to database ✓', 'success');
        saveDraft(true); // also update localStorage
      } else {
        showToast('Error: ' + data.message, 'error');
      }
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Save';
      showToast('Network error. Try again.', 'error');
    });
}

function showToast(msg, type) {
  var t = document.getElementById('docToast');
  t.textContent = msg;
  t.className = 'show ' + type;
  setTimeout(function() { t.className = ''; }, 3500);
}

/* ── Excel Export ── */
function exportExcelDoc() {
  function gv(key) {
    var el = document.querySelector('[data-key="' + key + '"]');
    if (!el) return '';
    return (el.value !== undefined ? el.value : (el.innerText || '')).trim();
  }
  var entity   = gv('entity_name');
  var fund     = gv('fund_cluster');
  var prno     = '<?php echo safe($pr["pr_number"]); ?>';
  var prdate   = document.querySelector('[data-key="pr_date"]').value;
  var purpose  = gv('purpose');
  var office   = gv('office_section');
  var reqName  = gv('req_name');
  var reqDesig = gv('req_desig');
  var apprName = gv('appr_name');
  var apprDesig= gv('appr_desig');

  var itemRows = [];
  document.querySelectorAll('#itemsBody tr:not(.total-row)').forEach(function(tr) {
    var tds = [...tr.querySelectorAll('td')];
    if (tds.length === 6) itemRows.push(tds.map(function(td){ return (td.innerText||'').trim(); }));
  });
  var totalAmt = document.getElementById('totalAmtCell').innerText.trim();

  var rows = [];
  rows.push(['PURCHASE REQUEST','','','','','']);
  rows.push(['','','','','','']);
  rows.push(['Entity Name:',entity,'','Fund Cluster:',fund,'']);
  rows.push(['PR No.:',prno,'','Date:',prdate,'']);
  rows.push(['Office/Section:',office,'','','','']);
  rows.push(['Purpose:',purpose,purpose,purpose,purpose,purpose]);
  rows.push(['','','','','','']);
  rows.push(['Stock/Property No.','Unit','Item Description','Quantity','Unit Cost','Total Cost']);
  itemRows.forEach(function(r){ rows.push(r); });
  var totalRow = rows.length;
  rows.push(['','','','','TOTAL AMOUNT:',totalAmt]);
  var sigRow = rows.length;
  rows.push(['','','','','','']);
  rows.push(['REQUESTED BY:','','','APPROVED BY:','','']);
  rows.push(['Printed Name:',reqName,'','Printed Name:',apprName,'']);
  rows.push(['Designation:',reqDesig,'','Designation:',apprDesig,'']);

  var merges = [
    {s:{r:0,c:0},e:{r:0,c:5}},{s:{r:2,c:1},e:{r:2,c:2}},{s:{r:2,c:4},e:{r:2,c:5}},
    {s:{r:3,c:1},e:{r:3,c:2}},{s:{r:3,c:4},e:{r:3,c:5}},{s:{r:5,c:1},e:{r:5,c:5}},
    {s:{r:sigRow+1,c:0},e:{r:sigRow+1,c:2}},{s:{r:sigRow+1,c:3},e:{r:sigRow+1,c:5}},
    {s:{r:sigRow+2,c:1},e:{r:sigRow+2,c:2}},{s:{r:sigRow+2,c:4},e:{r:sigRow+2,c:5}},
    {s:{r:sigRow+3,c:1},e:{r:sigRow+3,c:2}},{s:{r:sigRow+3,c:4},e:{r:sigRow+3,c:5}},
  ];
  var cols = [{wch:18},{wch:10},{wch:38},{wch:10},{wch:14},{wch:14}];
  var opts = {
    titleRows:[0], headerRows:[7], noBorderRows:[1,6],
    labelCols:[0,3], amountCols:[5], cellStyles:{}
  };
  opts.cellStyles[totalRow+'_4'] = 'font-weight:bold;background:#F9F9F9;text-align:right;';
  opts.cellStyles[totalRow+'_5'] = 'font-weight:bold;background:#F9F9F9;text-align:right;';
  buildXlsx(rows, merges, cols, '<?php echo safe($pr["pr_number"]); ?>_PurchaseRequest', opts);
}
</script>
<?php include 'inc/footer.php'; ?>
