<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo "<main class='flex-1 p-6 text-red-600'>Invalid RFQ ID.</main>"; include 'inc/footer.php'; exit; }
$rfq = $conn->query("SELECT * FROM rfqs WHERE id=$id")->fetch_assoc();
if (!$rfq) { echo "<main class='flex-1 p-6 text-red-600'>RFQ not found.</main>"; include 'inc/footer.php'; exit; }
$pr_id=(int)($rfq['pr_id']??0);
$pr  =$pr_id?$conn->query("SELECT * FROM purchase_requests WHERE id=$pr_id")->fetch_assoc():null;
$po  =$pr_id?$conn->query("SELECT * FROM purchase_orders WHERE pr_id=$pr_id ORDER BY id DESC LIMIT 1")->fetch_assoc():null;
$iar =null;
if($po){$poid=(int)$po['id'];$iar=$conn->query("SELECT * FROM iars WHERE po_id=$poid ORDER BY id DESC LIMIT 1")->fetch_assoc();}
$dv  =$pr_id?$conn->query("SELECT * FROM disbursement_vouchers WHERE pr_id=$pr_id ORDER BY id DESC LIMIT 1")->fetch_assoc():null;

// 1-to-many: ALWAYS load from pr_document (source of truth for items)
// rfq_document only stores the user-entered UNIT PRICE per row
$pr_items = [];
if ($pr_id) {
    $pr_doc_res = $conn->query("SELECT * FROM pr_document WHERE pr_id=$pr_id ORDER BY sort_order ASC");
    while ($row = $pr_doc_res->fetch_assoc()) $pr_items[] = $row;
}

// Load saved unit prices from rfq_document (indexed by position)
$rfq_unit_prices = [];
$rdu = $conn->query("SELECT unit_price, sort_order FROM rfq_document WHERE rfq_id=$id ORDER BY sort_order ASC");
if ($rdu) {
    $pos = 0;
    while ($r = $rdu->fetch_assoc()) {
        $rfq_unit_prices[$pos] = $r['unit_price'];
        $pos++;
    }
}

// Build display items: base from PR + unit prices from rfq_document
$displayItems = [];
if (!empty($pr_items)) {
    foreach ($pr_items as $i => $prow) {
        $displayItems[] = [
            'item_description' => $prow['item_description'],
            'qty'              => $prow['quantity'],
            'unit'             => $prow['unit'],
            'unit_price'       => $rfq_unit_prices[$i] ?? ''
        ];
    }
} else {
    // Fallback: if no PR items, load from rfq_document directly
    $rfq_items_fallback = [];
    $rfq_doc_res = $conn->query("SELECT * FROM rfq_document WHERE rfq_id=$id ORDER BY sort_order ASC");
    while ($row = $rfq_doc_res->fetch_assoc()) $rfq_items_fallback[] = $row;
    foreach ($rfq_items_fallback as $row) {
        $displayItems[] = [
            'item_description' => $row['item_description'],
            'qty'              => $row['qty'],
            'unit'             => $row['unit'],
            'unit_price'       => $row['unit_price']
        ];
    }
}

function safe($v){return htmlspecialchars($v??'',ENT_QUOTES);}
function fmt_date($d){return $d&&$d!='0000-00-00'?date('F d, Y',strtotime($d)):'';}
function val_date($d){return $d&&$d!='0000-00-00'?date('Y-m-d',strtotime($d)):'';}
$canEdit = hasPermission(PERM_QUOTATIONS);

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
.doc-title{text-align:center;font-weight:bold;font-size:14pt;letter-spacing:.03em;margin-bottom:6pt;}
.doc-outer{border:1.5px solid #000;}
.doc-row{display:flex;border-bottom:1px solid #000;}
.doc-row:last-child{border-bottom:none;}
.doc-cell{padding:3pt 5pt;border-right:1px solid #000;}
.doc-cell:last-child{border-right:none;}
.doc-label{font-weight:bold;font-size:9.5pt;white-space:nowrap;}
.doc-field{border:none;outline:none;width:100%;font-family:inherit;font-size:11pt;background:transparent;padding:0;min-height:14pt;display:inline-block;}
.doc-field:focus{background:#fffde7;border-radius:2px;}
.doc-field[contenteditable="true"]:empty:before{content:attr(data-ph);color:#bbb;font-style:italic;}
.items-table{width:100%;border-collapse:collapse;}
.items-table th{background:#f0f0f0;font-weight:bold;font-size:9.5pt;padding:3pt 5pt;border:1px solid #aaa;text-align:center;}
.items-table td{font-size:10.5pt;padding:3pt 5pt;border:1px solid #ccc;min-height:16pt;}
.items-table td[contenteditable]:focus{background:#fffde7;outline:none;}
.note-box{border:1.5px solid #000;padding:5pt 7pt;font-size:9.5pt;}
.underline-field{border-bottom:1px solid #000;display:inline-block;min-width:120pt;vertical-align:bottom;}
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
  /* Hide placeholder text in print */
  [contenteditable]:empty::before,
  [contenteditable="true"]:empty::before,
  .doc-field:empty::before,
  .sig-name:empty::before { content: none !important; display: none !important; }
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
@media print { input.sd-doc-input { border:none!important; background:transparent!important; } }
</style>
<main class="flex-1 overflow-y-auto">
<div class="doc-toolbar">
  <a href="proceedings.php" class="tbtn tbtn-back"><i class="fas fa-arrow-left"></i> Back</a>
  <h2><i class="fas fa-quote-right" style="color:#34d399;margin-right:4px;"></i>Request for Quotation — <?php echo safe($rfq['rfq_number']); ?></h2>
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
  <div class="no-print-note">✏️ Click any field to edit &bull; <strong>Save Draft</strong> preserves your edits</div>
  <div class="doc-title">REQUEST FOR QUOTATION</div>
  <div class="doc-outer">
    <div class="doc-row">
      <div class="doc-cell" style="width:55%;padding:6pt 8pt;">
        <div style="margin-bottom:4pt;">
          <input type="text" id="sd_rfq_agency" data-key="agency_name" class="sd-doc-input"
            style="width:250pt;" autocomplete="off" placeholder="Agency Name..."
            value="<?php echo $pr?safe($pr['entity_name']):''; ?>">
        </div>
        <div style="margin-bottom:4pt;"><span class="underline-field doc-field" contenteditable="true" data-key="agency_addr" data-ph="Address" style="width:250pt;"></span></div>
        <div><span class="underline-field doc-field" contenteditable="true" data-key="agency_city" data-ph="City" style="width:250pt;"></span></div>
      </div>
      <div class="doc-cell" style="width:45%;padding:6pt 8pt;">
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:5pt;">
          <span class="doc-label" style="min-width:80pt;">Date:</span>
          <input type="date" data-key="rfq_date" class="doc-date-field" style="border:none;outline:none;font-family:'Times New Roman',Times,serif;font-size:11pt;background:transparent;padding:0;min-height:14pt;display:inline-block;color:inherit;" value="<?php echo val_date($rfq['rfq_date']); ?>">
        </div>
        <div style="display:flex;align-items:center;gap:4pt;">
          <span class="doc-label" style="min-width:80pt;">Quotation No:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="rfq_number" style="flex:1;"><?php echo safe($rfq['rfq_number']); ?></span>
        </div>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:6pt 8pt;">
        <div class="note-box">
          <p style="margin:0 0 4pt 0;font-size:9.5pt;">Please quote your lowest price on the item/s listed below, subject to the General Conditions on this page, stating the shortest time of delivery and submit your quotation duly signed by your representative not later than <span class="underline-field doc-field" contenteditable="true" data-key="deadline" data-ph="deadline date/time" style="min-width:180pt;"></span> in the return envelope attached herewith.</p>
        </div>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:60%;padding:4pt 8pt;">
        <span class="doc-field" contenteditable="true" data-key="bac_chair" data-ph="BAC Chairperson / Authorized Official" style="display:block;min-height:14pt;"></span>
      </div>
      <div class="doc-cell" style="width:40%;text-align:center;padding:4pt 8pt;font-weight:bold;font-size:10pt;">
        <span class="doc-field" contenteditable="true" data-key="city_dir" data-ph="City Director">City Director</span>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:5pt 8pt;">
        <div style="display:flex;gap:8pt;">
          <span style="font-weight:bold;font-size:9pt;">Note:</span>
          <div style="flex:1;font-size:9pt;line-height:1.5;">
            <div>1. ALL ENTRIES MUST BE TYPEWRITTEN.</div>
            <div>2. DELIVERY PERIOD WITHIN <span class="underline-field doc-field" contenteditable="true" data-key="delivery_days" data-ph="___" style="min-width:60pt;font-size:9pt;"></span> CALENDAR DAYS</div>
            <div>3. WARRANTY SHALL BE FOR A PERIOD OF SIX (6) MONTHS FOR SUPPLIES &amp; MATERIALS, ONE (1) YEAR FOR EQUIPMENT, FROM DATE OF ACCEPTANCE BY THE PROCURING ENTITY.</div>
            <div>4. PRICE VALIDITY SHALL BE FOR A PERIOD OF <span class="underline-field doc-field" contenteditable="true" data-key="price_valid" data-ph="___" style="min-width:100pt;font-size:9pt;"></span> CALENDAR DAYS.</div>
            <div>5. G-EPS REGISTRATION CERTIFICATE SHALL BE ATTACHED UPON SUBMISSION OF THE QUOTATION.</div>
            <div>6. BIDDERS SHALL SUBMIT ORIGINAL BROCHURES SHOWING CERTIFICATIONS OF THE PRODUCT BEING OFFERED.</div>
          </div>
        </div>
      </div>
    </div>
    <div class="doc-row" style="border-bottom:none;">
      <table class="items-table" style="margin:0;border:none;">
        <thead>
          <tr>
            <th style="width:10%;">ITEM NO.</th>
            <th style="width:60%;text-align:left;padding-left:6pt;">ITEM &amp; DESCRIPTION</th>
            <th style="width:15%;">QTY.</th>
            <th style="width:15%;">UNIT PRICE</th>
          </tr>
        </thead>
        <tbody id="itemsBody" data-table="itemsBody">
          <?php
          $itemCount = max(count($displayItems), 19);
          $itemNo = 1;
          for ($i = 0; $i < $itemCount; $i++):
              $row   = $displayItems[$i] ?? [];
              $desc  = safe($row['item_description'] ?? '');
              $qty   = '';
              if (isset($row['qty']) && $row['qty'] !== null && $row['qty'] !== '') {
                  $qty = safe((string)(float)$row['qty']);
              }
              $price = (isset($row['unit_price']) && $row['unit_price'] !== null && $row['unit_price'] !== '')
                       ? number_format((float)$row['unit_price'], 2) : '';
              $autoNo = $desc !== '' ? $itemNo++ : '';
          ?>
          <tr style="height:18pt;">
            <td class="item-no-cell" style="text-align:center;font-weight:bold;vertical-align:middle;"><?php echo $autoNo; ?></td>
            <td style="vertical-align:middle;"><?php echo $desc; ?></td>
            <td style="text-align:center;vertical-align:middle;"><?php echo $qty; ?></td>
            <td contenteditable="true" style="text-align:right;vertical-align:middle;"><?php echo $price; ?></td>
          </tr>
          <?php endfor; ?>
          <tr>
            <td></td>
            <td contenteditable="true" style="font-size:9.5pt;"><strong>PR#</strong> <?php echo $pr?safe($pr['pr_number']):''; ?></td>
            <td></td><td></td>
          </tr>
          <tr>
            <td></td>
            <td contenteditable="true" style="font-size:9.5pt;"><strong>RFQ#</strong> <?php echo safe($rfq['rfq_number']); ?></td>
            <td></td><td></td>
          </tr>
          <tr style="height:10pt;"><td colspan="4"></td></tr>
        </tbody>
      </table>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:5pt 8pt;">
        <table style="width:100%;border-collapse:collapse;font-size:10pt;">
          <tr>
            <td style="width:50%;padding:2pt 0;"><span class="doc-label">Brand and Model:</span> <span class="underline-field doc-field" contenteditable="true" data-key="brand" style="min-width:120pt;"></span></td>
            <td style="width:50%;padding:2pt 0;"></td>
          </tr>
          <tr>
            <td style="padding:2pt 0;"><span class="doc-label">Delivery Period</span>: <span class="underline-field doc-field" contenteditable="true" data-key="del_period" style="min-width:120pt;"></span></td>
            <td></td>
          </tr>
          <tr>
            <td style="padding:2pt 0;"><span class="doc-label">Warranty</span>: <span class="underline-field doc-field" contenteditable="true" data-key="warranty" style="min-width:120pt;"></span></td>
            <td></td>
          </tr>
          <tr>
            <td style="padding:2pt 0;"><span class="doc-label">Price Validity</span>: <span class="underline-field doc-field" contenteditable="true" data-key="price_val2" style="min-width:120pt;"></span></td>
            <td></td>
          </tr>
        </table>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:50%;padding:4pt 8pt;">
        <div class="doc-field" contenteditable="true" data-key="agency_name2" data-ph="< Name of Agency >" style="font-style:italic;display:block;">&lt; Name of Agency &gt;</div>
        <div class="doc-field" contenteditable="true" data-key="agency_addr2" data-ph="< Address of Agency >" style="font-style:italic;display:block;">&lt; Address of Agency &gt;</div>
        <div style="margin-top:5pt;font-size:9.5pt;">After having carefully read and accepted your General Conditions, I/We quote you on the item at prices noted above.</div>
      </div>
      <div class="doc-cell" style="width:50%;padding:4pt 8pt;">
        <div style="min-height:28pt;border-bottom:1.5px solid #000;margin-bottom:3pt;"></div>
        <div style="margin-bottom:3pt;"><span class="doc-label" style="font-size:9.5pt;">Printed Name / Signature:</span>
          <span class="sig-name" contenteditable="true" data-key="supplier_sig_name" data-ph="Printed Name of Supplier's Representative"></span>
        </div>
        <div style="min-height:14pt;border-bottom:1.5px solid #000;margin-top:8pt;margin-bottom:3pt;"></div>
        <div style="font-size:9.5pt;">Tel. No. / Cellphone No./E-mail Address:
          <span class="sig-name" contenteditable="true" data-key="supplier_contact" data-ph="Contact Details"></span>
        </div>
      </div>
    </div>
  </div><!-- /doc-outer -->

  <?php if($pr||$po||$iar||$dv): ?>
  <div class="linked-docs" style="margin-top:8pt;padding:4pt 6pt;background:#f0fdf4;border-radius:4px;font-size:8.5pt;border:1px solid #bbf7d0;">
    <strong style="color:#059669;">Linked Documents:</strong>
    <?php if($pr): ?>&nbsp;<a href="pr_view.php?id=<?php echo $pr['id']; ?>" style="color:#2563eb;"><?php echo safe($pr['pr_number']); ?></a><?php endif; ?>
    <?php if($po): ?>&nbsp;&bull;&nbsp;<a href="po_view.php?id=<?php echo $po['id']; ?>" style="color:#7c3aed;"><?php echo safe($po['po_number']); ?></a><?php endif; ?>
    <?php if($iar): ?>&nbsp;&bull;&nbsp;<a href="iar_view.php?id=<?php echo $iar['id']; ?>" style="color:#d97706;"><?php echo safe($iar['iar_number']); ?></a><?php endif; ?>
    <?php if($dv): ?>&nbsp;&bull;&nbsp;<a href="dv_view.php?id=<?php echo $dv['id']; ?>" style="color:#dc2626;"><?php echo safe($dv['dv_number']); ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</div></div>
</main>
<script>
window.DOC_READONLY = <?php echo $canEdit ? 'false' : 'true'; ?>;
window.RFQ_ID = <?php echo $id; ?>;
</script>
<script src="assets/doc_utils.js"></script>
<script>
/* ── Auto-renumber ITEM NO. when page loads or unit price changes ── */
function renumberItems() {
  var n = 1;
  document.querySelectorAll('#itemsBody tr').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length < 4) return;
    var noCell   = tds[0];
    var descCell = tds[1];
    if (!noCell.classList.contains('item-no-cell')) return;
    var desc = (descCell.innerText || '').trim();
    if (desc !== '') { noCell.innerText = n++; }
    else { noCell.innerText = ''; }
  });
}

/* ── Re-number after draft load and on input ── */
document.addEventListener('DOMContentLoaded', function() {
  renumberItems();
});
document.getElementById('itemsBody').addEventListener('input', function(e) {
  renumberItems();
  autoSaveDraft();
});

/* ── Save to Database — only saves UNIT PRICE per row (desc/qty come from PR) ── */
function saveDocToDB() {
  if (window.DOC_READONLY) return;
  var btn = document.querySelector('.tbtn-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  function gInput(key) {
    var el = document.querySelector('[data-key="' + key + '"]');
    if (!el) return '';
    return (el.value !== undefined ? el.value : (el.innerText || '')).trim();
  }

  // Collect unit prices row by row (aligned to PR item positions)
  var items = [];
  document.querySelectorAll('#itemsBody tr').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length < 4 || !tds[0].classList.contains('item-no-cell')) return;
    var desc  = (tds[1].innerText || '').trim();
    var qty   = (tds[2].innerText || '').trim();
    var price = (tds[3].innerText || '').replace(/,/g,'').trim();
    // Always push a row for each item-no-cell row (even if price empty, to preserve alignment)
    items.push({ description: desc, qty: qty, unit: '', unit_price: price });
  });

  var fd = new FormData();
  fd.append('rfq_id',   RFQ_ID);
  fd.append('rfq_date', gInput('rfq_date'));
  fd.append('items',    JSON.stringify(items));

  fetch('save_rfq_doc.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Save';
      if (data.success) { showToast('RFQ saved ✓', 'success'); saveDraft(true); }
      else showToast('Error: ' + data.message, 'error');
    })
    .catch(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Save';
      showToast('Network error. Try again.', 'error');
    });
}

function showToast(msg, type) {
  var t = document.getElementById('docToast');
  if (!t) { t = document.createElement('div'); t.id='docToast'; document.body.appendChild(t);
    t.style.cssText='position:fixed;bottom:1.5rem;right:1.5rem;padding:.7rem 1.2rem;border-radius:.5rem;font-size:.85rem;font-weight:600;z-index:9999;opacity:0;transition:opacity .3s;pointer-events:none;';
  }
  t.textContent = msg;
  t.style.background = type === 'success' ? '#16a34a' : '#dc2626';
  t.style.color = '#fff';
  t.style.opacity = '1';
  setTimeout(function() { t.style.opacity = '0'; }, 3500);
}
function exportExcelDoc(){
  var agencyName = g('agency_name');
  var agencyAddr = g('agency_addr');
  var agencyCity = g('agency_city');
  var rfqDate    = (document.querySelector('[data-key="rfq_date"]')||{}).value || g('rfq_date');
  var rfqNumber  = '<?php echo safe($rfq["rfq_number"]); ?>';
  var deadline   = g('deadline');
  var delDays    = g('delivery_days');
  var priceValid = g('price_valid');
  var suppName   = g('supplier_sig_name');
  var suppContact= g('supplier_contact');

  var itemRows = [];
  document.querySelectorAll('#itemsBody tr').forEach(function(tr){
    var tds = [...tr.querySelectorAll('td')];
    if(tds.length === 4 && tds[0].classList.contains('item-no-cell')){
      itemRows.push(tds.map(function(td){ return (td.innerText||'').trim(); }));
    }
  });

  var rows = [];
  rows.push(['REQUEST FOR QUOTATION','','','']);            // 0
  rows.push(['','','','']);                                 // 1
  rows.push([agencyName,'','Date:',rfqDate]);               // 2
  rows.push([agencyAddr,'','Quotation No.:',rfqNumber]);    // 3
  rows.push([agencyCity,'','','']);                         // 4
  rows.push(['','','','']);                                 // 5
  rows.push(['Please quote your lowest price on the item/s listed below, not later than: '+deadline,'','','']); // 6
  rows.push(['','','','']);                                 // 7
  rows.push(['NOTE:','','','']);
  rows.push(['1. ALL ENTRIES MUST BE TYPEWRITTEN.','','','']);
  rows.push(['2. DELIVERY PERIOD WITHIN '+delDays+' CALENDAR DAYS','','','']);
  rows.push(['3. WARRANTY: 6 months for supplies/materials, 1 year for equipment from acceptance.','','','']);
  rows.push(['4. PRICE VALIDITY: '+priceValid+' CALENDAR DAYS.','','','']);
  rows.push(['','','','']);                                 // spacer
  rows.push(['ITEM NO.','ITEM & DESCRIPTION','QTY.','UNIT PRICE']); // header
  itemRows.forEach(function(r){ rows.push(r); });
  var sr = rows.length;
  rows.push(['','','','']);
  rows.push(['Printed Name / Signature (Supplier):',suppName,'','']);
  rows.push(['Tel./Cellphone/E-mail:',suppContact,'','']);

  var merges = [
    {s:{r:0,c:0},e:{r:0,c:3}},   // Title
    {s:{r:2,c:0},e:{r:2,c:1}},   // agency name
    {s:{r:3,c:0},e:{r:3,c:1}},   // agency addr
    {s:{r:4,c:0},e:{r:4,c:1}},   // city
    {s:{r:6,c:0},e:{r:6,c:3}},   // instruction
    {s:{r:9,c:0},e:{r:9,c:3}},
    {s:{r:10,c:0},e:{r:10,c:3}},
    {s:{r:11,c:0},e:{r:11,c:3}},
    {s:{r:12,c:0},e:{r:12,c:3}},
  ];
  merges.push({s:{r:sr+1,c:1},e:{r:sr+1,c:3}});
  merges.push({s:{r:sr+2,c:1},e:{r:sr+2,c:3}});

  var cols = [{wch:12},{wch:48},{wch:10},{wch:14}];

  var opts = {
    titleRows:    [0],
    headerRows:   [14],
    noBorderRows: [1, 5, 7, 13],
    labelCols:    [0],
    amountCols:   [3],
    cellStyles: {}
  };
  // Note rows styling
  [9,10,11,12].forEach(function(r){
    opts.cellStyles[r+'_0'] = 'font-size:9pt;background:#FFFBEB;';
  });
  // Sig rows
  opts.cellStyles[(sr+1)+'_0'] = 'font-weight:bold;background:#F3F4F6;';
  opts.cellStyles[(sr+2)+'_0'] = 'font-weight:bold;background:#F3F4F6;';

  buildXlsx(rows, merges, cols, '<?php echo safe($rfq["rfq_number"]); ?>_RFQ', opts);
}
</script>
<script src="assets/smart-dropdown.js"></script>
<script>
<?php if($canEdit): ?>
SmartDropdown.init('#sd_rfq_agency', 'entity_name');
<?php endif; ?>
</script>
<?php include 'inc/footer.php'; ?>
