<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$id=isset($_GET['id'])?(int)$_GET['id']:0;
if(!$id){echo "<main class='flex-1 p-6 text-red-600'>Invalid PO ID.</main>";include 'inc/footer.php';exit;}
$po=$conn->query("SELECT * FROM purchase_orders WHERE id=$id")->fetch_assoc();
if(!$po){echo "<main class='flex-1 p-6 text-red-600'>Purchase Order not found.</main>";include 'inc/footer.php';exit;}
$pr_id=(int)($po['pr_id']??0);
$pr  =$pr_id?$conn->query("SELECT * FROM purchase_requests WHERE id=$pr_id")->fetch_assoc():null;
$rfq =$pr_id?$conn->query("SELECT * FROM rfqs WHERE pr_id=$pr_id ORDER BY id DESC LIMIT 1")->fetch_assoc():null;
$iar =$conn->query("SELECT * FROM iars WHERE po_id=$id ORDER BY id DESC LIMIT 1")->fetch_assoc();
$dv  =$pr_id?$conn->query("SELECT * FROM disbursement_vouchers WHERE pr_id=$pr_id ORDER BY id DESC LIMIT 1")->fetch_assoc():null;

// 1-to-many: pr_document is ALWAYS the source of truth for items
$displayPO = [];
if ($pr_id) {
    $prd_res = $conn->query("SELECT * FROM pr_document WHERE pr_id=$pr_id ORDER BY sort_order ASC");
    while ($row = $prd_res->fetch_assoc()) $displayPO[] = $row;
}
// Fallback: if no pr_document rows exist, use po_document
if (empty($displayPO)) {
    $pod_res = $conn->query("SELECT * FROM po_document WHERE po_id=$id ORDER BY sort_order ASC");
    while ($row = $pod_res->fetch_assoc()) $displayPO[] = $row;
}

function safe($v){return htmlspecialchars($v??'',ENT_QUOTES);}
function fmt_date($d){return $d&&$d!='0000-00-00'?date('F d, Y',strtotime($d)):'';}
function val_date($d){return $d&&$d!='0000-00-00'?date('Y-m-d',strtotime($d)):'';}
$canEdit = hasPermission(PERM_PURCHASE_ORDERS);

function numberToWords($number){
    $ones=['','ONE','TWO','THREE','FOUR','FIVE','SIX','SEVEN','EIGHT','NINE','TEN','ELEVEN','TWELVE','THIRTEEN','FOURTEEN','FIFTEEN','SIXTEEN','SEVENTEEN','EIGHTEEN','NINETEEN'];
    $tens=['','','TWENTY','THIRTY','FORTY','FIFTY','SIXTY','SEVENTY','EIGHTY','NINETY'];
    $number=(float)$number;$intPart=(int)$number;
    if($intPart==0)return 'ZERO';$result='';
    if($intPart>=1000000){$result.=numberToWords(floor($intPart/1000000)).' MILLION ';$intPart%=1000000;}
    if($intPart>=1000){$result.=numberToWords(floor($intPart/1000)).' THOUSAND ';$intPart%=1000;}
    if($intPart>=100){$result.=$ones[floor($intPart/100)].' HUNDRED ';$intPart%=100;}
    if($intPart>=20){$result.=$tens[floor($intPart/10)].' ';$intPart%=10;}
    if($intPart>0)$result.=$ones[$intPart].' ';
    return trim($result);
}
$total_words=numberToWords($po['total_amount']??0).' PESOS';
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
.doc-title{text-align:center;font-weight:bold;font-size:14pt;letter-spacing:.03em;margin-bottom:2pt;}
.doc-subtitle{text-align:center;font-weight:bold;font-size:11pt;text-decoration:underline;margin-bottom:6pt;}
.doc-outer{border:1.5px solid #000;}
.doc-row{display:flex;border-bottom:1px solid #000;}
.doc-row:last-child{border-bottom:none;}
.doc-cell{padding:3pt 5pt;border-right:1px solid #000;}
.doc-cell:last-child{border-right:none;}
.doc-label{font-weight:bold;font-size:9.5pt;white-space:nowrap;}
.doc-field{border:none;outline:none;width:100%;font-family:inherit;font-size:11pt;background:transparent;padding:0;min-height:14pt;display:inline-block;}
.doc-field:focus{background:#fffde7;border-radius:2px;outline:none;}
.doc-field[contenteditable="true"]:empty:before{content:attr(data-ph);color:#bbb;font-style:italic;}
.underline-field{border-bottom:1px solid #000;display:inline-block;min-width:100pt;vertical-align:bottom;}
.items-table{width:100%;border-collapse:collapse;}
.items-table th{background:#f0f0f0;font-weight:bold;font-size:9.5pt;padding:3pt 5pt;border:1px solid #888;text-align:center;}
.items-table td{font-size:10.5pt;padding:3pt 5pt;border:1px solid #ccc;min-height:17pt;vertical-align:top;}
.items-table td[contenteditable]:focus{background:#fffde7;outline:none;}
.total-row td{font-weight:bold;border-top:2px solid #000;background:#f9f9f9;}
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

/* ── Input fields styled as doc fields ── */
input.doc-field, input.doc-input-field {
  border:none; outline:none; font-family:'Times New Roman',Times,serif; font-size:11pt;
  background:transparent; padding:0; min-height:14pt; width:100%;
  border-bottom:1px solid #000; display:inline-block; vertical-align:bottom;
}
input.doc-field:focus, input.doc-input-field:focus { background:#fffde7; border-radius:2px; outline:none; }
input[type="date"].doc-field, input[type="date"].doc-input-field {
  font-family:'Times New Roman',Times,serif; font-size:11pt;
  border:none; border-bottom:1px solid #000; background:transparent;
  padding:0; cursor:pointer; max-width:140pt;
}
input[type="date"].doc-field::-webkit-calendar-picker-indicator { cursor:pointer; opacity:.6; }
input.sd-doc-input {
  border:none; border-bottom:1px solid #000; outline:none;
  font-family:'Times New Roman',Times,serif; font-size:11pt;
  background:transparent; padding:0; min-height:14pt; width:100%;
  vertical-align:bottom; display:inline-block;
}
input.sd-doc-input:focus { background:#fffde7; border-radius:2px; }
@media print { input.doc-field, input.doc-input-field, input.sd-doc-input { border:none!important; background:transparent!important; } }

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
</style>
<main class="flex-1 overflow-y-auto">
<div class="doc-toolbar">
  <a href="proceedings.php" class="tbtn tbtn-back"><i class="fas fa-arrow-left"></i> Back</a>
  <h2><i class="fas fa-file-contract" style="color:#a78bfa;margin-right:4px;"></i>Purchase Order — <?php echo safe($po['po_number']); ?></h2>
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
  <div class="doc-title">PURCHASE ORDER</div>
  <div class="doc-subtitle">Republic of the Philippines</div>
  <div class="doc-outer">
    <div class="doc-row">
      <div class="doc-cell" style="width:55%;padding:4pt 6pt;">
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label">Supplier:</span>
          <span style="flex:1;position:relative;">
            <input type="text" id="sd_po_supplier" data-key="supplier" class="sd-doc-input"
              value="<?php echo safe($po['supplier']); ?>" autocomplete="off" placeholder="Type or select supplier...">
          </span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label">Address:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="supp_addr" data-ph="Supplier Address" style="flex:1;"></span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label">TIN:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="supp_tin" data-ph="TIN Number" style="flex:1;"></span>
        </div>
      </div>
      <div class="doc-cell" style="width:45%;padding:4pt 6pt;">
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label" style="min-width:60pt;">P.O. No.:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="po_number" style="flex:1;font-weight:bold;color:#1a3a6b;"><?php echo safe($po['po_number']); ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:2pt;">
          <span class="doc-label">Date:</span>
          <input type="date" data-key="po_date" class="doc-field" style="flex:1;"
            value="<?php echo val_date($po['po_date']); ?>">
        </div>
        <div style="font-size:9.5pt;"><strong>Mode of Procurement:</strong> <span class="doc-field" contenteditable="true" data-key="mode_proc" style="display:inline;">Small Value</span></div>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:4pt 8pt;">
        <div><strong>Gentlemen:</strong></div>
        <div style="margin-left:20pt;font-size:10pt;">Please furnish this Office the following articles subject to the terms and conditions contained herein:</div>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:55%;padding:4pt 6pt;">
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label">Place of Delivery:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="place_del" data-ph="Delivery Location" style="flex:1;"></span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;">
          <span class="doc-label">Date of Delivery:</span>
          <input type="date" data-key="date_del" class="doc-field" style="flex:1;"
            value="<?php echo $po['date_of_award'] ? val_date($po['date_of_award']) : ''; ?>">
        </div>
      </div>
      <div class="doc-cell" style="width:45%;padding:4pt 6pt;">
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label">Delivery Term:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="del_term" data-ph="e.g. 30 days" style="flex:1;"></span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;">
          <span class="doc-label">Payment Term:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="pay_term" data-ph="e.g. 30 days" style="flex:1;"></span>
        </div>
      </div>
    </div>
    <div class="doc-row" style="border-bottom:none;">
      <table class="items-table" style="margin:0;border:none;">
        <thead>
          <tr>
            <th style="width:8%;font-size:8.5pt;">Stock/<br>Property<br>No.</th>
            <th style="width:9%;">Unit</th>
            <th style="width:47%;text-align:left;padding-left:6pt;">Description</th>
            <th style="width:10%;">Quantity</th>
            <th style="width:13%;">Unit Cost</th>
            <th style="width:13%;">Amount</th>
          </tr>
        </thead>
        <tbody id="itemsBody" data-table="itemsBody">
          <?php
          $poItemCount  = max(count($displayPO), 15);
          $poStockCount = 1;
          for ($i = 0; $i < $poItemCount; $i++):
              $prow = $displayPO[$i] ?? [];
              $unit = $prow['unit']             ?? '';
              $desc = $prow['item_description'] ?? '';
              $qty  = (isset($prow['quantity'])  && $prow['quantity']  !== null && $prow['quantity']  !== '') ? $prow['quantity']  : '';
              $uc   = (isset($prow['unit_cost']) && $prow['unit_cost'] !== null && $prow['unit_cost'] !== '') ? $prow['unit_cost'] : '';
              $tc   = (isset($prow['total_cost'])&& $prow['total_cost']!== null && $prow['total_cost']!== '') ? $prow['total_cost']: '';
              $hasVal = (trim($unit)!=='' || trim($desc)!=='' || $qty!=='' || $uc!=='' || $tc!=='');
              $autoSno = $hasVal ? $poStockCount++ : '';
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
          <tr>
            <td></td><td></td>
            <td style="font-size:9.5pt;"><strong>PR#</strong> <span contenteditable="true"><?php echo $pr?safe($pr['pr_number']):''; ?></span></td>
            <td></td><td></td><td></td>
          </tr>
          <tr>
            <td></td><td></td>
            <td style="font-size:9.5pt;"><strong>RFQ#</strong> <span contenteditable="true"><?php echo $rfq?safe($rfq['rfq_number']):''; ?></span></td>
            <td></td><td></td><td></td>
          </tr>
          <tr class="total-row">
            <td colspan="5" style="text-align:right;font-size:9.5pt;padding-right:5pt;">TOTAL AMOUNT:</td>
            <td id="poTotalAmtCell" style="text-align:right;font-weight:bold;font-size:11pt;">
              <?php
              $poDbTotal = 0;
              foreach ($displayPO as $r) $poDbTotal += (float)($r['total_cost'] ?? 0);
              echo $poDbTotal > 0 ? number_format($poDbTotal, 2) : (($po['total_amount'] && $po['total_amount'] > 0) ? number_format((float)$po['total_amount'], 2) : '');
              ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:3pt 6pt;">
        <div style="display:flex;align-items:center;gap:4pt;">
          <span style="font-size:9.5pt;white-space:nowrap;">(Total Amount: </span>
          <span class="doc-field" contenteditable="true" data-key="total_words" style="flex:1;font-size:9.5pt;font-weight:bold;"><?php echo $total_words; ?></span>
          <span style="font-size:9.5pt;">)</span>
        </div>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:4pt 8pt;font-size:9.5pt;">
        In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed on the undelivered item/s.
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:50%;padding:4pt 8pt;">
        <div style="font-size:10pt;font-weight:bold;margin-bottom:20pt;">Conforme:</div>
        <div style="border-bottom:1.5px solid #000;width:220pt;margin-bottom:3pt;min-height:22pt;"></div>
        <div style="font-size:9.5pt;margin-bottom:3pt;"><strong>Signature over Printed Name of Supplier:</strong>
          <span class="sig-name" contenteditable="true" data-key="supplier_printed_name" data-ph="Printed Name of Supplier"></span>
        </div>
        <div style="border-bottom:1.5px solid #000;width:160pt;margin-top:14pt;margin-bottom:3pt;"></div>
        <div style="font-size:9.5pt;">Date: <span class="sig-name" contenteditable="true" data-key="supplier_date" data-ph="Date"></span></div>
      </div>
      <div class="doc-cell" style="width:50%;padding:4pt 8pt;">
        <div style="font-size:10pt;margin-bottom:20pt;">Very truly yours,</div>
        <div style="border-bottom:1.5px solid #000;width:220pt;margin-bottom:3pt;min-height:22pt;"></div>
        <div style="font-size:9pt;margin-bottom:3pt;"><strong>Signature over Printed Name of Authorized Official:</strong>
          <span class="sig-name" contenteditable="true" data-key="auth_printed_name" data-ph="Printed Name of Authorized Official"></span>
        </div>
        <div style="text-align:left;width:220pt;font-weight:bold;margin-top:4pt;">
          <span class="doc-field" contenteditable="true" data-key="auth_title" style="font-size:10pt;">City Director</span>
        </div>
        <div style="text-align:left;width:220pt;font-size:9pt;">
          <span class="doc-field" contenteditable="true" data-key="auth_desig" data-ph="Designation">Designation</span>
        </div>
      </div>
    </div>
    <div class="doc-row">
      <div class="doc-cell" style="width:55%;padding:4pt 6pt;">
        <div style="margin-bottom:3pt;"><span class="doc-label">Fund Cluster:</span>
          <span class="doc-field" contenteditable="true" data-key="fund_cluster" style="margin-left:4pt;">Regular Agency Fund</span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label">Funds Available:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="funds_avail" style="flex:1;"></span>
        </div>
        <div style="margin-top:20pt;border-top:1.5px solid #000;padding-top:3pt;font-size:9pt;">
          <strong>Signature over Printed Name of Chief Accountant/Head of Accounting Division/Unit:</strong>
          <span class="sig-name" contenteditable="true" data-key="chief_acct_name" data-ph="Printed Name of Chief Accountant"></span>
        </div>
      </div>
      <div class="doc-cell" style="width:45%;padding:4pt 6pt;">
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label" style="white-space:nowrap;">ORS/BURS No.:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="ors_no" style="flex:1;"></span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;margin-bottom:3pt;">
          <span class="doc-label" style="white-space:nowrap;">Date of the ORS/BURS:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="ors_date" style="flex:1;"></span>
        </div>
        <div style="display:flex;align-items:center;gap:4pt;">
          <span class="doc-label">Amount:</span>
          <span class="underline-field doc-field" contenteditable="true" data-key="ors_amount" style="flex:1;"></span>
        </div>
      </div>
    </div>
  </div><!-- /doc-outer -->

  <?php if($pr||$rfq||$iar||$dv): ?>
  <div class="linked-docs" style="margin-top:8pt;padding:4pt 6pt;background:#faf5ff;border-radius:4px;font-size:8.5pt;border:1px solid #e9d5ff;">
    <strong style="color:#7c3aed;">Linked Documents:</strong>
    <?php if($pr): ?>&nbsp;<a href="pr_view.php?id=<?php echo $pr['id']; ?>" style="color:#2563eb;"><?php echo safe($pr['pr_number']); ?></a><?php endif; ?>
    <?php if($rfq): ?>&nbsp;&bull;&nbsp;<a href="rfq_view.php?id=<?php echo $rfq['id']; ?>" style="color:#059669;"><?php echo safe($rfq['rfq_number']); ?></a><?php endif; ?>
    <?php if($iar): ?>&nbsp;&bull;&nbsp;<a href="iar_view.php?id=<?php echo $iar['id']; ?>" style="color:#d97706;"><?php echo safe($iar['iar_number']); ?></a><?php endif; ?>
    <?php if($dv): ?>&nbsp;&bull;&nbsp;<a href="dv_view.php?id=<?php echo $dv['id']; ?>" style="color:#dc2626;"><?php echo safe($dv['dv_number']); ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</div></div>
</main>
<script>
window.DOC_READONLY = <?php echo $canEdit ? 'false' : 'true'; ?>;
window.PO_ID = <?php echo $id; ?>;
</script>
<script src="assets/doc_utils.js"></script>
<script>
/* ── Auto-recompute Stock/Property No. ── */
function recomputePoStockNos() {
  var n = 1;
  document.querySelectorAll('#itemsBody tr').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length < 6 || !tds[2].hasAttribute('contenteditable')) return;
    var unit = (tds[1].innerText || '').trim();
    var desc = (tds[2].innerText || '').trim();
    var qty  = (tds[3].innerText || '').trim();
    var uc   = (tds[4].innerText || '').trim();
    var tc   = (tds[5].innerText || '').trim();
    if (!tds[0].classList.contains('sno-cell')) return;
    var hasVal = unit || desc || qty || uc || tc;
    tds[0].innerText = hasVal ? n++ : '';
  });
}

/* ── Auto-calc Amount (qty * unit cost) and update total ── */
function updatePoTotal() {
  var total = 0;
  document.querySelectorAll('#itemsBody tr').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length < 6 || !tds[2].hasAttribute('contenteditable')) return;
    var qty = parseFloat((tds[3].innerText||'').replace(/,/g,'')) || 0;
    var uc  = parseFloat((tds[4].innerText||'').replace(/,/g,'')) || 0;
    if (qty > 0 && uc > 0) {
      var amt = qty * uc;
      tds[5].innerText = amt.toFixed(2);
      total += amt;
    } else {
      total += parseFloat((tds[5].innerText||'').replace(/,/g,'')) || 0;
    }
  });
  var totalCell = document.getElementById('poTotalAmtCell');
  if (totalCell) totalCell.innerText = total > 0 ? total.toFixed(2) : '';
}

document.addEventListener('DOMContentLoaded', function() {
  recomputePoStockNos();
  updatePoTotal();
});

document.getElementById('itemsBody').addEventListener('input', function(e) {
  var td = e.target;
  if (td && td.tagName === 'TD') {
    var tr = td.closest('tr');
    if (tr) {
      var tds = tr.querySelectorAll('td');
      if (tds.length >= 6 && tds[2].hasAttribute('contenteditable')) {
        var qty = parseFloat((tds[3].innerText||'').replace(/,/g,'')) || 0;
        var uc  = parseFloat((tds[4].innerText||'').replace(/,/g,'')) || 0;
        if (qty > 0 && uc > 0) {
          tds[5].innerText = (qty * uc).toFixed(2);
        }
      }
    }
    recomputePoStockNos();
    updatePoTotal();
  }
  autoSaveDraft();
});

function saveDocToDB() {
  if (window.DOC_READONLY) return;
  var btn = document.querySelector('.tbtn-save');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  function gv(key) {
    var el = document.querySelector('[data-key="' + key + '"]');
    if (!el) return '';
    return (el.value !== undefined ? el.value : (el.innerText || '')).trim();
  }
  var items = [];
  var total = 0;
  document.querySelectorAll('#itemsBody tr').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    if (tds.length < 6 || !tds[2].hasAttribute('contenteditable')) return;
    if (!tds[0].classList.contains('sno-cell')) return;
    var stock = (tds[0].innerText||'').trim();
    var unit  = (tds[1].innerText||'').trim();
    var desc  = (tds[2].innerText||'').trim();
    var qty   = (tds[3].innerText||'').replace(/,/g,'').trim();
    var uc    = (tds[4].innerText||'').replace(/,/g,'').trim();
    var tc    = (tds[5].innerText||'').replace(/,/g,'').trim();
    if (!unit && !desc && !qty) return;
    total += parseFloat(tc) || 0;
    items.push({stock:stock,unit:unit,description:desc,quantity:qty,unit_cost:uc,total_cost:tc});
  });
  var fd = new FormData();
  fd.append('po_id',         PO_ID);
  fd.append('po_date',       gv('po_date') || (document.querySelector('[data-key="po_date"]')||{}).value || '');
  fd.append('supplier',      gv('supplier'));
  fd.append('date_of_award', gv('date_del') || '');
  fd.append('total_amount',  total);
  fd.append('items',         JSON.stringify(items));
  fetch('save_po_doc.php', {method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(data){
      btn.disabled=false; btn.innerHTML='<i class="fas fa-check-circle"></i> Save';
      if(data.success){showToast('PO saved ✓','success');saveDraft(true);}
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
  var supplier  = g('supplier');
  var suppAddr  = g('supp_addr');
  var suppTin   = g('supp_tin');
  var poNumber  = g('po_number');
  var poDate    = g('po_date');
  var modeProc  = g('mode_proc');
  var placeDel  = g('place_del');
  var dateDel   = g('date_del');
  var delTerm   = g('del_term');
  var payTerm   = g('pay_term');
  var totalWords= g('total_words');
  var fundCluster=g('fund_cluster');
  var fundsAvail= g('funds_avail');
  var orsNo     = g('ors_no');
  var orsDate   = g('ors_date');
  var orsAmt    = g('ors_amount');
  var suppName  = g('supplier_printed_name');
  var authName  = g('auth_printed_name');
  var authTitle = g('auth_title');
  var authDesig = g('auth_desig');
  var chiefName = g('chief_acct_name');

  var itemRows = [];
  document.querySelectorAll('#itemsBody tr').forEach(function(tr){
    var tds = [...tr.querySelectorAll('td')];
    if(tds.length === 6){
      itemRows.push(tds.map(function(td){ return (td.innerText||'').trim(); }));
    }
  });

  var rows = [];
  rows.push(['PURCHASE ORDER','','','','','']);
  rows.push(['Republic of the Philippines','','','','','']);
  rows.push(['','','','','','']);
  rows.push(['Supplier:',supplier,'','P.O. No.:',poNumber,'']);
  rows.push(['Address:',suppAddr,'','Date:',poDate,'']);
  rows.push(['TIN:',suppTin,'','Mode of Procurement:',modeProc,'']);
  rows.push(['Place of Delivery:',placeDel,'','Delivery Term:',delTerm,'']);
  rows.push(['Date of Delivery:',dateDel,'','Payment Term:',payTerm,'']);
  rows.push(['','','','','','']);
  rows.push(['Gentlemen: Please furnish this Office the following articles subject to the terms and conditions contained herein:','','','','','']);
  rows.push(['','','','','','']);
  rows.push(['Stock/Property No.','Unit','Description','Quantity','Unit Cost','Amount']); // header
  itemRows.forEach(function(r){ rows.push(r); });
  var tr1 = rows.length;
  rows.push(['Total Amount in Words:',totalWords,totalWords,totalWords,totalWords,totalWords]);
  rows.push(['In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed.','','','','','']);
  rows.push(['','','','','','']); // spacer
  rows.push(['CONFORME:','','','VERY TRULY YOURS:','','']);
  rows.push(['','','','','','']); // sig space
  rows.push(['','','','','','']); // sig space
  rows.push(['Signature over Printed Name of Supplier:','','','Signature over Printed Name of Authorized Official:','','']);
  rows.push([suppName,'','',authName,'','']);
  rows.push(['Date:','','',authTitle,'','']);
  rows.push(['','','',authDesig,'','']);
  rows.push(['','','','','','']); // spacer
  rows.push(['Fund Cluster:',fundCluster,'','ORS/BURS No.:',orsNo,'']);
  rows.push(['Funds Available:',fundsAvail,'','Date of ORS/BURS:',orsDate,'']);
  rows.push(['','','','Amount:',orsAmt,'']);
  var lr = rows.length;
  rows.push(['Signature over Printed Name of Chief Accountant / Head of Accounting Division/Unit:','','','','','']);
  rows.push([chiefName,'','','','','']);

  var merges = [
    {s:{r:0,c:0},e:{r:0,c:5}},
    {s:{r:1,c:0},e:{r:1,c:5}},
    {s:{r:3,c:1},e:{r:3,c:2}},{s:{r:3,c:4},e:{r:3,c:5}},
    {s:{r:4,c:1},e:{r:4,c:2}},{s:{r:4,c:4},e:{r:4,c:5}},
    {s:{r:5,c:1},e:{r:5,c:2}},{s:{r:5,c:4},e:{r:5,c:5}},
    {s:{r:6,c:1},e:{r:6,c:2}},{s:{r:6,c:4},e:{r:6,c:5}},
    {s:{r:7,c:1},e:{r:7,c:2}},{s:{r:7,c:4},e:{r:7,c:5}},
    {s:{r:9,c:0},e:{r:9,c:5}},
    {s:{r:tr1,c:1},e:{r:tr1,c:5}},
    {s:{r:tr1+1,c:0},e:{r:tr1+1,c:5}},
    {s:{r:tr1+3,c:0},e:{r:tr1+3,c:2}},{s:{r:tr1+3,c:3},e:{r:tr1+3,c:5}},
    {s:{r:tr1+4,c:0},e:{r:tr1+4,c:2}},{s:{r:tr1+4,c:3},e:{r:tr1+4,c:5}},
    {s:{r:tr1+5,c:0},e:{r:tr1+5,c:2}},{s:{r:tr1+5,c:3},e:{r:tr1+5,c:5}},
    {s:{r:tr1+6,c:0},e:{r:tr1+6,c:2}},{s:{r:tr1+6,c:3},e:{r:tr1+6,c:5}},
    {s:{r:tr1+7,c:0},e:{r:tr1+7,c:2}},{s:{r:tr1+7,c:3},e:{r:tr1+7,c:5}},
    {s:{r:tr1+8,c:0},e:{r:tr1+8,c:2}},{s:{r:tr1+8,c:3},e:{r:tr1+8,c:5}},
    {s:{r:lr,c:0},e:{r:lr,c:5}},
    {s:{r:lr+1,c:0},e:{r:lr+1,c:5}},
  ];

  var cols = [{wch:18},{wch:10},{wch:28},{wch:10},{wch:14},{wch:14}];

  var opts = {
    titleRows:    [0],
    subTitleRows: [1],
    headerRows:   [11],
    noBorderRows: [2, 8, 10],
    labelCols:    [0, 3],
    amountCols:   [5],
    cellStyles:   {}
  };
  // Instruction row
  opts.cellStyles['9_0'] = 'font-style:italic;font-size:9pt;background:#FFFBEB;';
  // Total words row
  opts.cellStyles[tr1+'_0'] = 'font-weight:bold;background:#F3F4F6;';
  // Fine print row
  opts.cellStyles[(tr1+1)+'_0'] = 'font-size:8.5pt;color:#555;font-style:italic;background:#F9F9F9;';
  // CONFORME / VERY TRULY YOURS
  opts.cellStyles[(tr1+3)+'_0'] = 'font-weight:bold;text-align:center;background:#F0F4FF;';
  opts.cellStyles[(tr1+3)+'_3'] = 'font-weight:bold;text-align:center;background:#F0F4FF;';
  // Sig label rows
  opts.cellStyles[(tr1+6)+'_0'] = 'font-weight:bold;background:#F3F4F6;font-size:9pt;';
  opts.cellStyles[(tr1+6)+'_3'] = 'font-weight:bold;background:#F3F4F6;font-size:9pt;';
  // Chief accountant label
  opts.cellStyles[lr+'_0'] = 'font-weight:bold;background:#F3F4F6;font-size:9pt;';

  buildXlsx(rows, merges, cols, '<?php echo safe($po["po_number"]); ?>_PurchaseOrder', opts);
}
</script>
<script src="assets/smart-dropdown.js"></script>
<script>
<?php if($canEdit): ?>
SmartDropdown.init('#sd_po_supplier', 'supplier');
<?php endif; ?>
</script>
<?php include 'inc/footer.php'; ?>
