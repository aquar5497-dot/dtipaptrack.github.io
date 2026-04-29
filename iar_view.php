<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$id=isset($_GET['id'])?(int)$_GET['id']:0;
if(!$id){echo "<main class='flex-1 p-6 text-red-600'>Invalid IAR ID.</main>";include 'inc/footer.php';exit;}
$iar=$conn->query("SELECT * FROM iars WHERE id=$id")->fetch_assoc();
if(!$iar){echo "<main class='flex-1 p-6 text-red-600'>IAR not found.</main>";include 'inc/footer.php';exit;}
$po_id=(int)($iar['po_id']??0);
$po  =$po_id?$conn->query("SELECT * FROM purchase_orders WHERE id=$po_id")->fetch_assoc():null;
$pr_id=(int)($iar['pr_id']??($po?$po['pr_id']:0));
$pr  =$pr_id?$conn->query("SELECT * FROM purchase_requests WHERE id=$pr_id")->fetch_assoc():null;
$rfq =$pr_id?$conn->query("SELECT * FROM rfqs WHERE pr_id=$pr_id ORDER BY id DESC LIMIT 1")->fetch_assoc():null;
$dv  =$pr_id?$conn->query("SELECT * FROM disbursement_vouchers WHERE pr_id=$pr_id ORDER BY id DESC LIMIT 1")->fetch_assoc():null;
function safe($v){return htmlspecialchars($v??'',ENT_QUOTES);}
function fmt_date($d){return $d&&$d!='0000-00-00'?date('F d, Y',strtotime($d)):'';}
function val_date($d){return $d&&$d!='0000-00-00'?date('Y-m-d',strtotime($d)):'';}
$canEdit = hasPermission(PERM_IAR);

// 1-to-many: pr_document is ALWAYS the source of truth for items
$displayIAR = [];
if ($pr_id) {
    $prd_res = $conn->query("SELECT * FROM pr_document WHERE pr_id=$pr_id ORDER BY sort_order ASC");
    while ($row = $prd_res->fetch_assoc()) $displayIAR[] = $row;
}
// Fallback: if no pr_document rows, use iar_document
if (empty($displayIAR)) {
    $iard_res = $conn->query("SELECT * FROM iar_document WHERE iar_id=$id ORDER BY sort_order ASC");
    while ($row = $iard_res->fetch_assoc()) $displayIAR[] = $row;
}

$receipt_status=$iar['receipt_status']??'';
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
.tbtn-clear{background:#475569;color:#cbd5e1;}.tbtn-clear:hover{background:#64748b;color:#fff;}
#draftBar{font-size:.7rem;padding:.22rem .55rem;border-radius:.25rem;background:#0f172a;color:#64748b;font-style:italic;}
#draftBar.saved{color:#fbbf24;} #draftBar.final{color:#4ade80;}
.doc-page-bg{background:#cbd5e1;min-height:calc(100vh - 56px);padding:1.5rem;display:flex;justify-content:center;}
.doc-page{background:#fff;width:816px;max-width:100%;box-shadow:0 4px 32px rgba(0,0,0,.18);font-family:'Times New Roman',Times,serif;font-size:11pt;padding:18pt 22pt;}
.doc-title{text-align:center;font-weight:bold;font-size:13pt;letter-spacing:.03em;margin-bottom:5pt;}
.doc-outer{border:1.5px solid #000;}
.doc-row{display:flex;border-bottom:1px solid #000;}
.doc-row:last-child{border-bottom:none;}
.doc-cell{padding:3pt 5pt;border-right:1px solid #000;}
.doc-cell:last-child{border-right:none;}
.doc-label{font-weight:bold;font-size:9.5pt;white-space:nowrap;}
.doc-field{border:none;outline:none;width:100%;font-family:inherit;font-size:11pt;background:transparent;padding:0;min-height:14pt;display:inline-block;}
.doc-field:focus{background:#fffde7;outline:none;border-radius:2px;}
.doc-field[contenteditable="true"]:empty:before{content:attr(data-ph);color:#bbb;font-style:italic;}
.underline-field{border-bottom:1px solid #000;display:inline-block;min-width:80pt;vertical-align:bottom;}
.items-table{width:100%;border-collapse:collapse;}
.items-table th{background:#f0f0f0;font-weight:bold;font-size:9pt;padding:3pt 5pt;border:1px solid #888;text-align:center;font-style:italic;}
.items-table td{font-size:10.5pt;padding:3pt 5pt;border:1px solid #ccc;min-height:17pt;vertical-align:top;}
.items-table td[contenteditable]:focus{background:#fffde7;outline:none;}
.section-header{text-align:center;font-weight:bold;font-size:12pt;font-style:italic;padding:4pt;letter-spacing:.05em;}
.checkbox-item{display:flex;align-items:flex-start;gap:6pt;margin:4pt 0;font-size:10.5pt;cursor:pointer;}
.checkbox-box{width:14pt;height:14pt;border:1.5px solid #000;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;font-size:11pt;line-height:1;cursor:pointer;user-select:none;}
.checkbox-box:hover{background:#fffde7;}
.sig-name{border-bottom:1px solid #555;display:block;width:100%;font-family:inherit;font-size:11pt;background:transparent;outline:none;min-height:14pt;padding:1pt 2pt;margin-top:1pt;}
.sig-name:focus{background:#fffde7;}
.sig-name[contenteditable]:empty:before{content:attr(data-ph);color:#bbb;font-style:italic;}
.no-print-note{font-size:8pt;color:#999;font-style:italic;text-align:center;padding:2pt;margin-bottom:4pt;}
@media print{
  body,html{margin:0!important;padding:0!important;background:#fff!important;}
  body{padding-top:0!important;}
  .doc-toolbar{display:none!important;}
  .doc-page-bg{background:transparent!important;padding:0!important;min-height:auto!important;display:block!important;}
  .doc-page{box-shadow:none!important;padding:8pt 12pt!important;width:100%!important;max-width:none!important;font-size:10pt!important;margin:0!important;}
  [contenteditable]{outline:none!important;background:transparent!important;}
  .no-print-note,.linked-docs{display:none!important;}
  #sidebar,header.dti-header{display:none!important;}
  .sb-content-wrap{margin-left:0!important;}
  .checkbox-box{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  /* ── Suppress placeholder hint text on print ── */
  .doc-field:empty:before,
  .doc-field:empty::before,
  .doc-field[contenteditable]:empty:before,
  .doc-field[contenteditable]:empty::before,
  .doc-field[contenteditable="true"]:empty:before,
  .doc-field[contenteditable="true"]:empty::before,
  .sig-name:empty:before,
  .sig-name:empty::before,
  .sig-name[contenteditable]:empty:before,
  .sig-name[contenteditable]:empty::before,
  [contenteditable]:empty:before,
  [contenteditable]:empty::before { content: none !important; color: transparent !important; }
}

/* ── Read-only mode ── */
body.doc-readonly-mode .doc-field[contenteditable="false"]{
  cursor:default!important;background:transparent!important;
}
body.doc-readonly-mode .checkbox-box{
  cursor:not-allowed!important;opacity:.75;
}
body.doc-readonly-mode .sig-name[contenteditable="false"]{
  cursor:default!important;background:transparent!important;
}
body.doc-readonly-mode .items-table td[contenteditable="false"]{
  cursor:default!important;background:transparent!important;
}
input.sd-doc-input {
  border:none; border-bottom:1px solid #000; outline:none;
  font-family:'Times New Roman',Times,serif; font-size:11pt;
  background:transparent; padding:0; min-height:14pt; width:100%;
  vertical-align:bottom; display:inline-block;
}
input.sd-doc-input:focus { background:#fffde7; border-radius:2px; }
input[type="date"].doc-field {
  font-family:'Times New Roman',Times,serif; font-size:11pt;
  background:transparent; padding:0; min-height:14pt; cursor:pointer;
}
input[type="date"].doc-field::-webkit-calendar-picker-indicator { cursor:pointer; opacity:.6; }
@media print {
  input.sd-doc-input, input[type="date"].doc-field { border:none!important; background:transparent!important; }
}
</style>
<main class="flex-1 overflow-y-auto">
<div class="doc-toolbar">
  <a href="proceedings.php" class="tbtn tbtn-back"><i class="fas fa-arrow-left"></i> Back</a>
  <h2><i class="fas fa-clipboard-check" style="color:#fcd34d;margin-right:4px;"></i>Inspection &amp; Acceptance Report — <?php echo safe($iar['iar_number']); ?></h2>
  <span id="draftBar">No draft saved</span>
  <button onclick="saveDraft(false)" class="tbtn tbtn-draft"><i class="fas fa-save"></i> Save Draft</button>
  <button onclick="saveDocToDB()" class="tbtn tbtn-save"><i class="fas fa-check-circle"></i> Save</button>
  <button onclick="window.print()" class="tbtn tbtn-print"><i class="fas fa-print"></i> Print</button>
  <button onclick="exportExcelDoc()" class="tbtn tbtn-excel"><i class="fas fa-file-excel"></i> Export Excel</button>
  <?php if($pr): ?>
  <a href="report.php?search_pr=<?php echo urlencode($pr['pr_number']); ?>" class="tbtn tbtn-report"><i class="fas fa-chart-bar"></i> View Report</a>
  <?php endif; ?>
</div>
<div class="doc-page-bg">
<div id="docContent" class="doc-page">
  <div class="no-print-note">✏️ Click any field to edit &bull; Click checkboxes to mark/unmark &bull; <strong>Save Draft</strong> preserves your edits</div>
  <div class="doc-title">INSPECTION AND ACCEPTANCE REPORT</div>
  <div class="doc-outer">
    <div class="doc-row" style="height:6pt;"><div class="doc-cell" style="width:100%;"></div></div>
    <div class="doc-row">
      <div class="doc-cell" style="width:55%;display:flex;align-items:center;gap:4pt;">
        <span class="doc-label">Entity Name:</span>
        <input type="text" id="sd_iar_entity" data-key="entity_name" class="sd-doc-input"
          value="<?php echo $pr?safe($pr['entity_name']):''; ?>" autocomplete="off" placeholder="Agency Name...">
      </div>
      <div class="doc-cell" style="width:45%;display:flex;align-items:center;gap:4pt;">
        <span class="doc-label">Fund Cluster:</span>
        <input type="text" id="sd_iar_fund" data-key="fund_cluster" class="sd-doc-input"
          value="Regular Agency Fund" autocomplete="off" placeholder="Fund Cluster...">
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:55%;padding:3pt 6pt;">
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:2pt;">
          <span class="doc-label">Supplier:</span>
          <span style="flex:1;">
            <input type="text" id="sd_iar_supplier" data-key="supplier" class="sd-doc-input"
              value="<?php echo $po?safe($po['supplier']):''; ?>" autocomplete="off" placeholder="Supplier name...">
          </span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:2pt;">
          <span class="doc-label">PO No./Date:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="po_no_date" style="flex:1;"><?php echo $po?safe($po['po_number']).' / '.fmt_date($po['po_date']):''; ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:2pt;">
          <span class="doc-label">Requisitioning Office/Dept.:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="req_office" data-ph="< Name of Agency >" style="flex:1;"><?php echo $pr?safe($pr['entity_name']):''; ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;">
          <span class="doc-label">Responsibility Center Code:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="resp_center" style="flex:1;min-width:60pt;"></span>
        </div>
      </div>
      <div class="doc-cell" style="width:45%;padding:3pt 6pt;">
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:2pt;">
          <span class="doc-label" style="white-space:nowrap;">IAR No.:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="iar_number" style="flex:1;font-weight:bold;color:#1a3a6b;"><?php echo safe($iar['iar_number']); ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:2pt;">
          <span class="doc-label">Date:</span>
          <input type="date" data-key="iar_date" class="doc-field" style="flex:1;border:none;border-bottom:1px solid #000;"
            value="<?php echo val_date($iar['iar_date']); ?>">
        </div>
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:2pt;">
          <span class="doc-label" style="white-space:nowrap;">Invoice No:</span>
          <span style="flex:1;">
            <input type="text" id="sd_iar_invoice" data-key="invoice_no" class="sd-doc-input"
              value="<?php echo safe($iar['invoice_number']); ?>" autocomplete="off" placeholder="Invoice No...">
          </span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;">
          <span class="doc-label">Date:</span>
          <input type="date" data-key="invoice_date" class="doc-field" style="flex:1;border:none;border-bottom:1px solid #000;"
            value="<?php echo val_date($iar['invoice_date']); ?>">
        </div>
      </div>
    </div>
    <div class="doc-row" style="border-bottom:none;">
      <table class="items-table" style="margin:0;border:none;">
        <thead>
          <tr>
            <th style="width:9%;font-style:italic;">Stock/<br>Property<br>No.</th>
            <th style="width:63%;text-align:left;padding-left:8pt;font-style:italic;">Description</th>
            <th style="width:14%;font-style:italic;">Unit</th>
            <th style="width:14%;font-style:italic;">Quantity</th>
          </tr>
        </thead>
        <tbody id="itemsBody" data-table="itemsBody">
          <?php
          $iarCount = max(count($displayIAR), 17);
          $iarStockCount = 1;
          for ($i = 0; $i < $iarCount; $i++):
              $irow = $displayIAR[$i] ?? [];
              $desc = $irow['item_description'] ?? '';
              $unit = $irow['unit']             ?? '';
              $qty  = (isset($irow['quantity']) && $irow['quantity'] !== null && $irow['quantity'] !== '') ? $irow['quantity'] : '';
              $hasVal = (trim($desc) !== '' || trim($unit) !== '' || $qty !== '');
              $autoSno = $hasVal ? $iarStockCount++ : '';
          ?>
          <tr style="<?php echo $hasVal?'':'height:18pt;'; ?>">
            <td class="sno-cell" contenteditable="true" style="text-align:center;vertical-align:middle;"><?php echo htmlspecialchars((string)$autoSno, ENT_QUOTES); ?></td>
            <td contenteditable="true" style="vertical-align:top;"><?php echo safe($desc); ?></td>
            <td contenteditable="true" style="text-align:center;vertical-align:middle;"><?php echo safe($unit); ?></td>
            <td contenteditable="true" style="text-align:center;vertical-align:middle;"><?php echo $qty!==''?safe((string)(float)$qty):''; ?></td>
          </tr>
          <?php endfor; ?>
          <tr>
            <td></td>
            <td style="font-size:9.5pt;"><strong>PR#</strong> <span contenteditable="true"><?php echo $pr?safe($pr['pr_number']):''; ?></span></td>
            <td></td><td></td>
          </tr>
          <tr>
            <td></td>
            <td style="font-size:9.5pt;"><strong>RFQ#</strong> <span contenteditable="true"><?php echo $rfq?safe($rfq['rfq_number']):''; ?></span></td>
            <td></td><td></td>
          </tr>
          <tr style="height:8pt;"><td colspan="4"></td></tr>
        </tbody>
      </table>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:50%;"><div class="section-header">INSPECTION</div></div>
      <div class="doc-cell" style="width:50%;"><div class="section-header">ACCEPTANCE</div></div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:50%;padding:5pt 8pt;">
        <div style="display:flex;align-items:center;gap:4pt;">
          <span class="doc-label">Date Inspected:</span>
          <input type="date" data-key="date_inspected" class="doc-field" style="flex:1;border:none;border-bottom:1px solid #000;"
            value="<?php echo val_date($iar['date_inspected']); ?>">
        </div>
      </div>
      <div class="doc-cell" style="width:50%;padding:5pt 8pt;">
        <div style="display:flex;align-items:center;gap:4pt;">
          <span class="doc-label">Date Received:</span>
          <input type="date" data-key="date_received" class="doc-field" style="flex:1;border:none;border-bottom:1px solid #000;"
            value="<?php echo val_date($iar['date_received']); ?>">
        </div>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:50%;padding:5pt 8pt;">
        <div class="checkbox-item" onclick="toggleCheck(this.querySelector('.checkbox-box'))">
          <span class="checkbox-box" data-key="chk_inspected" data-checked="0"></span>
          <span>Inspected, verified and found in order as to quantity and specifications</span>
        </div>
      </div>
      <div class="doc-cell" style="width:50%;padding:5pt 8pt;">
        <div class="checkbox-item" style="margin-bottom:6pt;" onclick="toggleCheck(this.querySelector('.checkbox-box'))">
          <span class="checkbox-box" data-key="chk_complete" data-checked="<?php echo $receipt_status==='Complete'?'1':'0'; ?>"><?php echo $receipt_status==='Complete'?'&#10003;':''; ?></span>
          <span>Complete</span>
        </div>
        <div class="checkbox-item" onclick="toggleCheck(this.querySelector('.checkbox-box'))">
          <span class="checkbox-box" data-key="chk_partial" data-checked="<?php echo $receipt_status==='Partial'?'1':'0'; ?>"><?php echo $receipt_status==='Partial'?'&#10003;':''; ?></span>
          <span>Partial (pls. specify quantity): <span class="underline-field doc-field" contenteditable="true" data-key="partial_qty" data-ph="qty" style="min-width:60pt;"></span></span>
        </div>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:50%;padding:5pt 8pt;text-align:center;">
        <div style="margin-top:16pt;border-top:1.5px solid #000;padding-top:2pt;">
          <strong>Signature over Printed Name — Designate Inspector:</strong>
          <span class="sig-name" contenteditable="true" data-key="inspector_name" data-ph="Printed Name of Property Inspector"></span>
        </div>
        <div style="font-size:9pt;text-decoration:underline;font-weight:bold;margin-top:2pt;">Designate – Property Inspector</div>
      </div>
      <div class="doc-cell" style="width:50%;padding:5pt 8pt;text-align:center;">
        <div style="margin-top:16pt;border-top:1.5px solid #000;padding-top:2pt;">
          <strong>Signature over Printed Name — Supply Officer:</strong>
          <span class="sig-name" contenteditable="true" data-key="supply_officer_name" data-ph="Printed Name of Supply Officer"></span>
        </div>
        <div style="font-size:9pt;text-decoration:underline;font-weight:bold;margin-top:2pt;">Supply Officer</div>
      </div>
    </div>
  </div><!-- /doc-outer -->

  <?php if($pr||$rfq||$po||$dv): ?>
  <div class="linked-docs" style="margin-top:8pt;padding:4pt 6pt;background:#fffbeb;border-radius:4px;font-size:8.5pt;border:1px solid #fde68a;">
    <strong style="color:#d97706;">Linked Documents:</strong>
    <?php if($pr): ?>&nbsp;<a href="pr_view.php?id=<?php echo $pr['id']; ?>" style="color:#2563eb;"><?php echo safe($pr['pr_number']); ?></a><?php endif; ?>
    <?php if($rfq): ?>&nbsp;&bull;&nbsp;<a href="rfq_view.php?id=<?php echo $rfq['id']; ?>" style="color:#059669;"><?php echo safe($rfq['rfq_number']); ?></a><?php endif; ?>
    <?php if($po): ?>&nbsp;&bull;&nbsp;<a href="po_view.php?id=<?php echo $po['id']; ?>" style="color:#7c3aed;"><?php echo safe($po['po_number']); ?></a><?php endif; ?>
    <?php if($dv): ?>&nbsp;&bull;&nbsp;<a href="dv_view.php?id=<?php echo $dv['id']; ?>" style="color:#dc2626;"><?php echo safe($dv['dv_number']); ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</div></div>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
window.DOC_READONLY = <?php echo $canEdit ? 'false' : 'true'; ?>;
window.IAR_ID = <?php echo $id; ?>;
</script>
<script src="assets/doc_utils.js"></script>
<script>
/* ── Auto-recompute Stock/Property No. ── */
function recomputeIarStockNos() {
  var n = 1;
  document.querySelectorAll('#itemsBody tr').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length < 4 || !tds[0].classList.contains('sno-cell')) return;
    var desc = (tds[1].innerText || '').trim();
    var unit = (tds[2].innerText || '').trim();
    var qty  = (tds[3].innerText || '').trim();
    var hasVal = desc || unit || qty;
    tds[0].innerText = hasVal ? n++ : '';
  });
}

document.addEventListener('DOMContentLoaded', function() {
  recomputeIarStockNos();
});

document.getElementById('itemsBody').addEventListener('input', function() {
  recomputeIarStockNos();
  autoSaveDraft();
});

function saveDocToDB() {
  if (window.DOC_READONLY) return;
  var btn = document.querySelector('.tbtn-save');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  // Helper: read from input[data-key] or contenteditable[data-key]
  function gVal(key) {
    var el = document.querySelector('[data-key="' + key + '"]');
    if (!el) return '';
    if (el.tagName === 'INPUT' || el.tagName === 'SELECT') return (el.value || '').trim();
    return (el.innerText || '').trim();
  }

  var items = [];
  document.querySelectorAll('#itemsBody tr').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length < 4 || !tds[0].classList.contains('sno-cell')) return;
    var stock = (tds[0].innerText||'').trim();
    var desc  = (tds[1].innerText||'').trim();
    var unit  = (tds[2].innerText||'').trim();
    var qty   = (tds[3].innerText||'').replace(/,/g,'').trim();
    if (!desc && !unit && !qty) return;
    items.push({stock:stock,description:desc,unit:unit,quantity:qty,unit_cost:'',total_cost:''});
  });

  var fd = new FormData();
  fd.append('iar_id',         IAR_ID);
  fd.append('iar_date',       gVal('iar_date'));
  fd.append('invoice_number', gVal('invoice_no'));
  fd.append('invoice_date',   gVal('invoice_date'));
  fd.append('date_inspected', gVal('date_inspected'));
  fd.append('date_received',  gVal('date_received'));
  fd.append('status',         'Complete');
  fd.append('items',          JSON.stringify(items));

  fetch('save_iar_doc.php', {method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(data){
      btn.disabled=false; btn.innerHTML='<i class="fas fa-check-circle"></i> Save';
      if(data.success){showToast('IAR saved ✓','success');saveDraft(true);}
      else showToast('Error: '+data.message,'error');
    })
    .catch(function(){btn.disabled=false;btn.innerHTML='<i class="fas fa-check-circle"></i> Save';showToast('Network error','error');});
}
function showToast(msg,type){
  var t=document.getElementById('docToast');
  if(!t){t=document.createElement('div');t.id='docToast';document.body.appendChild(t);
    t.style.cssText='position:fixed;bottom:1.5rem;right:1.5rem;padding:.7rem 1.2rem;border-radius:.5rem;font-size:.85rem;font-weight:600;z-index:9999;opacity:0;transition:opacity .3s;pointer-events:none;';}
  t.textContent=msg;t.style.background=type==='success'?'#16a34a':'#dc2626';t.style.color='#fff';t.style.opacity='1';
  setTimeout(function(){t.style.opacity='0';},3500);
}
function exportExcelDoc(){
  var entity     = g('entity_name');
  var fundCluster= g('fund_cluster');
  var supplier   = g('supplier');
  var poNoDate   = g('po_no_date');
  var reqOffice  = g('req_office');
  var respCenter = g('resp_center');
  var iarNumber  = g('iar_number');
  var iarDate    = g('iar_date');
  var invoiceNo  = g('invoice_no');
  var invoiceDate= g('invoice_date');
  var dateInsp   = g('date_inspected');
  var dateRecv   = g('date_received');
  var partialQty = g('partial_qty');
  var inspName   = g('inspector_name');
  var supplyName = g('supply_officer_name');

  var chkInspected = chk('chk_inspected') ? '[✓]' : '[ ]';
  var chkComplete  = chk('chk_complete')  ? '[✓]' : '[ ]';
  var chkPartial   = chk('chk_partial')   ? '[✓]' : '[ ]';

  var itemRows = [];
  document.querySelectorAll('#itemsBody tr').forEach(function(tr){
    var tds = [...tr.querySelectorAll('td')];
    if(tds.length === 4 && tds[0].classList.contains('sno-cell')){
      itemRows.push(tds.map(function(td){ return (td.innerText||'').trim(); }));
    }
  });

  var rows = [];
  rows.push(['INSPECTION AND ACCEPTANCE REPORT','','','']);
  rows.push(['','','','']);
  rows.push(['Entity Name:',entity,'Fund Cluster:',fundCluster]);
  rows.push(['Supplier:',supplier,'IAR No.:',iarNumber]);
  rows.push(['PO No./Date:',poNoDate,'Date:',iarDate]);
  rows.push(['Requisitioning Office:',reqOffice,'Invoice No.:',invoiceNo]);
  rows.push(['Responsibility Center Code:',respCenter,'Invoice Date:',invoiceDate]);
  rows.push(['','','','']);
  rows.push(['Stock/Property No.','Description','Unit','Quantity']); // header
  itemRows.forEach(function(r){ rows.push(r); });
  var sc = rows.length;
  rows.push(['','','','']);
  rows.push(['— INSPECTION —','','— ACCEPTANCE —','']);
  rows.push(['Date Inspected:',dateInsp,'Date Received:',dateRecv]);
  rows.push([chkInspected+' Inspected, verified and found in order as to quantity and specifications',
             '',chkComplete+' Complete',chkPartial+' Partial (qty: '+partialQty+')']);
  rows.push(['','','','']);
  rows.push(['','Signature over Printed Name of Inspector:','','Signature over Printed Name of Supply Officer:']);
  rows.push(['',inspName,'',supplyName]);
  rows.push(['','Designate – Property Inspector','','Supply Officer']);

  var merges = [
    {s:{r:0,c:0},e:{r:0,c:3}},
    {s:{r:2,c:1},e:{r:2,c:1}},
    {s:{r:3,c:1},e:{r:3,c:1}},
    {s:{r:4,c:1},e:{r:4,c:1}},
    {s:{r:5,c:1},e:{r:5,c:1}},
    {s:{r:6,c:1},e:{r:6,c:1}},
  ];
  var r = sc+2;
  merges.push({s:{r:r+1,c:0},e:{r:r+1,c:1}});
  merges.push({s:{r:r+2,c:0},e:{r:r+2,c:1}});
  merges.push({s:{r:r+3,c:1},e:{r:r+3,c:1}});
  merges.push({s:{r:r+4,c:1},e:{r:r+4,c:1}});
  merges.push({s:{r:r+5,c:1},e:{r:r+5,c:1}});

  var cols = [{wch:14},{wch:40},{wch:12},{wch:14}];

  var opts = {
    titleRows:    [0],
    headerRows:   [8],
    noBorderRows: [1, 7],
    labelCols:    [0, 2],
    cellStyles:   {}
  };
  // Inspection / Acceptance section headers
  opts.cellStyles[(sc+1)+'_0'] = 'font-weight:bold;background:#EFF6FF;color:#1E40AF;text-align:center;';
  opts.cellStyles[(sc+1)+'_2'] = 'font-weight:bold;background:#ECFDF5;color:#065F46;text-align:center;';
  // Date Inspected / Received
  opts.cellStyles[(sc+2)+'_0'] = 'font-weight:bold;background:#F3F4F6;';
  opts.cellStyles[(sc+2)+'_2'] = 'font-weight:bold;background:#F3F4F6;';
  // Checkbox row
  opts.cellStyles[(sc+3)+'_0'] = 'font-size:9pt;background:#FFFBEB;';
  opts.cellStyles[(sc+3)+'_2'] = 'font-size:9pt;background:#FFFBEB;';
  // Sig label rows
  opts.cellStyles[(r+1)+'_1'] = 'font-weight:bold;background:#F3F4F6;text-align:center;';
  opts.cellStyles[(r+1)+'_3'] = 'font-weight:bold;background:#F3F4F6;text-align:center;';
  opts.cellStyles[(r+3)+'_1'] = 'font-size:9pt;color:#6B7280;text-align:center;';
  opts.cellStyles[(r+3)+'_3'] = 'font-size:9pt;color:#6B7280;text-align:center;';

  buildXlsx(rows, merges, cols, '<?php echo safe($iar["iar_number"]); ?>_IAR', opts);
}
</script>
<script src="assets/smart-dropdown.js"></script>
<script>
<?php if($canEdit): ?>
SmartDropdown.init('#sd_iar_entity',   'entity_name');
SmartDropdown.init('#sd_iar_fund',     'fund_cluster');
SmartDropdown.init('#sd_iar_supplier', 'supplier');
SmartDropdown.init('#sd_iar_invoice',  'invoice_prefix');
<?php endif; ?>
</script>
<?php include 'inc/footer.php'; ?>
