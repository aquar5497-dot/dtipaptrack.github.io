<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

$id=isset($_GET['id'])?(int)$_GET['id']:0;
if(!$id){echo "<main class='flex-1 p-6 text-red-600'>Invalid DV ID.</main>";include 'inc/footer.php';exit;}
$dv=$conn->query("SELECT * FROM disbursement_vouchers WHERE id=$id")->fetch_assoc();
if(!$dv){echo "<main class='flex-1 p-6 text-red-600'>Disbursement Voucher not found.</main>";include 'inc/footer.php';exit;}
$pr_id=(int)($dv['pr_id']??0);
$pr  =$pr_id?$conn->query("SELECT * FROM purchase_requests WHERE id=$pr_id")->fetch_assoc():null;
$po  =$pr_id?$conn->query("SELECT * FROM purchase_orders WHERE pr_id=$pr_id ORDER BY id DESC LIMIT 1")->fetch_assoc():null;
$rfq =$pr_id?$conn->query("SELECT * FROM rfqs WHERE pr_id=$pr_id ORDER BY id DESC LIMIT 1")->fetch_assoc():null;
$iar =null;
if($po){$poid=(int)$po['id'];$iar=$conn->query("SELECT * FROM iars WHERE po_id=$poid ORDER BY id DESC LIMIT 1")->fetch_assoc();}
function safe($v){return htmlspecialchars($v??'',ENT_QUOTES);}
function fmt_date($d){return $d?date('F d, Y',strtotime($d)):'';}
$canEdit = hasPermission(PERM_DISBURSEMENT);

// Load dv_document accounting entries
$dv_acct_items = [];
$dvd_res = $conn->query("SELECT * FROM dv_document WHERE dv_id=$id ORDER BY sort_order ASC");
while ($row = $dvd_res->fetch_assoc()) $dv_acct_items[] = $row;

$gross=(float)($dv['gross_amount']??0);
$tax_amt=(float)($dv['tax_amount']??0);
$net_amount=(float)($dv['net_amount']??0);
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
.doc-page{background:#fff;width:816px;max-width:100%;box-shadow:0 4px 32px rgba(0,0,0,.18);font-family:'Times New Roman',Times,serif;font-size:10.5pt;padding:16pt 20pt;}
.doc-outer{border:1.5px solid #000;}
.doc-row{display:flex;border-bottom:1px solid #000;}
.doc-row:last-child{border-bottom:none;}
.doc-cell{padding:2pt 4pt;border-right:1px solid #000;}
.doc-cell:last-child{border-right:none;}
.doc-label{font-weight:bold;font-size:9pt;white-space:nowrap;}
.doc-field{border:none;outline:none;width:100%;font-family:inherit;font-size:10.5pt;background:transparent;padding:0;display:inline-block;}
.doc-field:focus{background:#fffde7;outline:none;border-radius:2px;}
.doc-field[contenteditable="true"]:empty:before{content:attr(data-ph);color:#bbb;font-style:italic;}
.underline-field{border-bottom:1px solid #000;display:inline-block;min-width:80pt;vertical-align:bottom;}
.checkbox-box{display:inline-flex;align-items:center;justify-content:center;width:12pt;height:12pt;border:1.5px solid #000;vertical-align:middle;margin-right:3pt;cursor:pointer;font-size:10pt;line-height:1;user-select:none;}
.checkbox-box:hover{background:#fffde7;}
.sig-name{border-bottom:1px solid #555;display:block;width:100%;font-family:inherit;font-size:10.5pt;background:transparent;outline:none;min-height:14pt;padding:1pt 2pt;margin-top:1pt;}
.sig-name:focus{background:#fffde7;}
.sig-name[contenteditable]:empty:before{content:attr(data-ph);color:#bbb;font-style:italic;}
.no-print-note{font-size:8pt;color:#999;font-style:italic;text-align:center;padding:2pt;margin-bottom:4pt;}
@media print{
  body,html{margin:0!important;padding:0!important;background:#fff!important;}
  body{padding-top:0!important;}
  .doc-toolbar{display:none!important;}
  .doc-page-bg{background:transparent!important;padding:0!important;min-height:auto!important;display:block!important;}
  .doc-page{box-shadow:none!important;padding:6pt 10pt!important;width:100%!important;max-width:none!important;font-size:9.5pt!important;margin:0!important;}
  [contenteditable]{outline:none!important;background:transparent!important;}
  .no-print-note,.linked-docs{display:none!important;}
  #sidebar,header.dti-header{display:none!important;}
  .sb-content-wrap{margin-left:0!important;}
  .checkbox-box{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  img{max-width:70px!important;max-height:70px!important;}
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
  border:none; border-bottom:1px solid #999; outline:none;
  font-family:'Times New Roman',Times,serif; font-size:11pt;
  background:transparent; padding:0; min-height:14pt; width:100%;
  vertical-align:bottom; display:inline-block;
}
input.sd-doc-input:focus { background:#fffde7; border-radius:2px; }
input.sd-doc-input.small { font-size:9pt; border-bottom:1px solid #ccc; }
@media print { input.sd-doc-input { border:none!important; background:transparent!important; } }
</style>
<main class="flex-1 overflow-y-auto">
<div class="doc-toolbar">
  <a href="proceedings.php" class="tbtn tbtn-back"><i class="fas fa-arrow-left"></i> Back</a>
  <h2><i class="fas fa-money-check-alt" style="color:#f87171;margin-right:4px;"></i>Disbursement Voucher — <?php echo safe($dv['dv_number']); ?></h2>
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
  <div class="doc-outer">

    <!-- HEADER: Logo | Title | Fund Cluster / DV Info -->
    <div class="doc-row">
      <div class="doc-cell" style="width:13%;text-align:center;padding:4pt;display:flex;align-items:center;justify-content:center;">
        <img src="dti.jpg" alt="DTI Logo" style="max-width:72px;max-height:72px;object-fit:contain;">
      </div>
      <div class="doc-cell" style="width:63%;text-align:center;padding:6pt 4pt;display:flex;flex-direction:column;align-items:center;justify-content:center;">
        <div style="font-weight:bold;font-size:11pt;margin-bottom:1pt;">Department of Trade and Industry</div>
        <div class="doc-field" contenteditable="true" data-key="dti_field_office" style="font-size:10pt;font-weight:bold;text-align:center;">DTI Field Office</div>
        <div style="font-weight:bold;font-size:13pt;letter-spacing:.05em;margin-top:4pt;">DISBURSEMENT VOUCHER</div>
      </div>
      <div class="doc-cell" style="width:24%;padding:4pt 6pt;">
        <div style="font-size:9pt;font-weight:bold;margin-bottom:2pt;">Fund Cluster:</div>
        <input type="text" id="sd_dv_fund" data-key="fund_cluster" class="sd-doc-input small"
          value="Regular Agency Fund" autocomplete="off" placeholder="Fund Cluster..."
          style="font-size:9pt;font-weight:bold;border-bottom:1px solid #ccc;padding-bottom:2pt;margin-bottom:3pt;">
        <div style="display:flex;align-items:center;gap:3pt;margin-bottom:2pt;">
          <span style="font-size:8.5pt;font-weight:bold;">Date:</span>
          <span class="doc-field" contenteditable="true" data-key="dv_date" style="font-size:9pt;"><?php echo fmt_date($dv['dv_date']); ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:3pt;">
          <span style="font-size:8.5pt;font-weight:bold;">DV No.:</span>
          <span class="doc-field" contenteditable="true" data-key="dv_number" style="font-size:9pt;font-weight:bold;color:#1a3a6b;"><?php echo safe($dv['dv_number']); ?></span>
        </div>
      </div>
    </div>

    <!-- Mode of Payment -->
    <div class="doc-row">
      <div class="doc-cell" style="width:15%;padding:3pt 4pt;"><span class="doc-label">Mode of<br>Payment</span></div>
      <div class="doc-cell" style="width:85%;padding:3pt 6pt;display:flex;align-items:center;gap:12pt;flex-wrap:wrap;font-size:9.5pt;">
        <label style="display:flex;align-items:center;gap:3pt;cursor:pointer;">
          <span class="checkbox-box" data-key="chk_mds" data-checked="0" onclick="toggleCheck(this)"></span> MDS Check
        </label>
        <label style="display:flex;align-items:center;gap:3pt;cursor:pointer;">
          <span class="checkbox-box" data-key="chk_comm" data-checked="1" onclick="toggleCheck(this)">&#10003;</span> Commercial Check
        </label>
        <label style="display:flex;align-items:center;gap:3pt;cursor:pointer;">
          <span class="checkbox-box" data-key="chk_ada" data-checked="0" onclick="toggleCheck(this)"></span> ADA
        </label>
        <label style="display:flex;align-items:center;gap:3pt;cursor:pointer;flex:1;">
          <span class="checkbox-box" data-key="chk_others" data-checked="0" onclick="toggleCheck(this)"></span>
          Others: <span class="underline-field doc-field" contenteditable="true" data-key="mode_others" data-ph="specify" style="min-width:100pt;margin-left:4pt;"></span>
        </label>
      </div>
    </div>

    <!-- Payee -->
    <div class="doc-row">
      <div class="doc-cell" style="width:15%;padding:3pt 4pt;"><span class="doc-label">Payee</span></div>
      <div class="doc-cell" style="width:45%;display:flex;align-items:center;">
        <input type="text" id="sd_dv_payee" data-key="payee" class="sd-doc-input"
          value="<?php echo safe($dv['supplier']); ?>" autocomplete="off" placeholder="Payee Name..."
          style="font-size:10.5pt;">
      </div>
      <div class="doc-cell" style="width:20%;padding:3pt 4pt;">
        <div style="font-size:8.5pt;font-weight:bold;">TIN/Employee No.:</div>
        <input type="text" id="sd_dv_tin" data-key="tin" class="sd-doc-input small"
          value="" autocomplete="off" placeholder="TIN...">
      </div>
      <div class="doc-cell" style="width:20%;padding:3pt 4pt;">
        <div style="font-size:8.5pt;font-weight:bold;">ORS/BURS No.:</div>
        <span class="doc-field" contenteditable="true" data-key="ors_no"></span>
      </div>
    </div>

    <!-- Address -->
    <div class="doc-row">
      <div class="doc-cell" style="width:15%;padding:3pt 4pt;"><span class="doc-label">Address</span></div>
      <div class="doc-cell" style="width:85%;">
        <input type="text" id="sd_dv_addr" data-key="payee_address" class="sd-doc-input"
          value="" autocomplete="off" placeholder="Payee Address...">
      </div>
    </div>

    <!-- Particulars header -->
    <div class="doc-row">
      <div class="doc-cell" style="width:45%;text-align:center;padding:2pt;"><strong>Particulars</strong></div>
      <div class="doc-cell" style="width:18%;text-align:center;padding:2pt;"><strong style="font-size:9pt;">Responsibility<br>Center</strong></div>
      <div class="doc-cell" style="width:17%;text-align:center;padding:2pt;"><strong style="font-size:9pt;">MFO/PAP</strong></div>
      <div class="doc-cell" style="width:20%;text-align:center;padding:2pt;"><strong style="font-size:9pt;">Amount</strong></div>
    </div>

    <!-- Particulars Body -->
    <div class="doc-row">
      <div class="doc-cell" style="width:45%;padding:4pt 6pt;min-height:55pt;">
        <span class="doc-field" contenteditable="true" data-key="particulars" data-ph="Payment details" style="display:block;min-height:40pt;"><?php echo $pr?'Payment for '.safe($pr['purpose']):safe($dv['supplier']); ?></span>
        <div style="margin-top:6pt;font-size:9.5pt;">
          <div style="display:flex;gap:4pt;"><span style="font-weight:bold;">Gross Amount</span>&nbsp;&nbsp;&nbsp;<span class="doc-field" contenteditable="true" data-key="gross_amt" style="min-width:60pt;text-align:right;"><?php echo number_format($gross,2); ?></span></div>
          <div style="font-size:9pt;margin-top:2pt;margin-left:6pt;">Less tax:</div>
          <div style="display:flex;gap:4pt;margin-left:10pt;font-size:9pt;">
            <span class="doc-field" contenteditable="true" data-key="tax_type" style="flex:1;"><?php echo $dv['tax_type']?safe($dv['tax_type']):''; ?></span>
            <span class="doc-field" contenteditable="true" data-key="tax_amt" style="min-width:50pt;text-align:right;"><?php echo $tax_amt>0?number_format($tax_amt,2):''; ?></span>
          </div>
          <div style="display:flex;gap:4pt;border-top:1px solid #ccc;padding-top:1pt;margin-top:2pt;font-weight:bold;font-size:10pt;">
            <span style="flex:1;"></span>
            <span class="doc-field" contenteditable="true" data-key="net_amt" style="min-width:60pt;text-align:right;"><?php echo number_format($net_amount,2); ?></span>
          </div>
        </div>
      </div>
      <div class="doc-cell" style="width:18%;padding:3pt 4pt;">
        <span class="doc-field" contenteditable="true" data-key="resp_center" data-ph="< Fund >" style="display:block;font-size:9.5pt;">&lt; Fund &gt;</span>
      </div>
      <div class="doc-cell" style="width:17%;padding:3pt 4pt;">
        <span class="doc-field" contenteditable="true" data-key="mfo_pap" style="display:block;"></span>
      </div>
      <div class="doc-cell" style="width:20%;padding:3pt 4pt;text-align:right;font-weight:bold;vertical-align:bottom;font-size:11pt;">
        <span class="doc-field" contenteditable="true" data-key="amount_col" style="text-align:right;"><?php echo number_format($net_amount,2); ?></span>
      </div>
    </div>

    <!-- Amount Due -->
    <div class="doc-row">
      <div class="doc-cell" style="width:45%;text-align:right;padding:3pt 6pt;font-weight:bold;font-size:9.5pt;">Amount Due</div>
      <div class="doc-cell" style="width:18%;"></div>
      <div class="doc-cell" style="width:17%;"></div>
      <div class="doc-cell" style="width:20%;text-align:right;padding:3pt 4pt;font-weight:bold;font-size:12pt;border-top:2px solid #000;">
        <span class="doc-field" contenteditable="true" data-key="amount_due"><?php echo number_format($net_amount,2); ?></span>
      </div>
    </div>

    <!-- Section A: Certified -->
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:3pt 6pt;">
        <div style="display:flex;align-items:flex-start;gap:4pt;">
          <span class="doc-label" style="font-size:9.5pt;">A.</span>
          <span style="font-size:9.5pt;">Certified: Expenses/Cash Advance necessary, lawful and incurred under my direct supervision.</span>
        </div>
        <div style="margin-top:14pt;text-align:center;border-top:1.5px solid #000;width:300pt;margin-left:auto;margin-right:auto;padding-top:2pt;">
          <strong style="font-size:9pt;">Printed Name, Designation and Signature of Supervisor:</strong>
          <span class="sig-name" contenteditable="true" data-key="supervisor_name" data-ph="Printed Name / Designation of Supervisor"></span>
        </div>
      </div>
    </div>

    <!-- Section B: Accounting Entry -->
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:3pt 6pt;">
        <div><span class="doc-label" style="font-size:9.5pt;">B.</span> <span style="font-size:9.5pt;font-weight:bold;">Accounting Entry:</span></div>
        <table style="width:100%;border-collapse:collapse;margin-top:4pt;font-size:9.5pt;">
          <thead>
            <tr>
              <th style="text-align:left;padding:2pt 4pt;border-bottom:1px solid #ccc;width:40%;">Account Title</th>
              <th style="padding:2pt 4pt;border-bottom:1px solid #ccc;width:20%;text-align:center;">UACS Code</th>
              <th style="padding:2pt 4pt;border-bottom:1px solid #ccc;width:20%;text-align:right;">Debit</th>
              <th style="padding:2pt 4pt;border-bottom:1px solid #ccc;width:20%;text-align:right;">Credit</th>
            </tr>
          </thead>
          <tbody id="acctBody" data-table="acctBody">
            <?php if (!empty($dv_acct_items)): ?>
              <?php foreach ($dv_acct_items as $ai): ?>
              <tr>
                <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;"><?php echo safe($ai['account_title']); ?></td>
                <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;text-align:center;"><?php echo safe($ai['uacs_code']); ?></td>
                <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;text-align:right;"><?php echo $ai['debit']?number_format((float)$ai['debit'],2):''; ?></td>
                <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;text-align:right;"><?php echo $ai['credit']?number_format((float)$ai['credit'],2):''; ?></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;">Office supplies</td>
              <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;text-align:center;">50203010</td>
              <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;text-align:right;"><?php echo number_format($gross,2); ?></td>
              <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;text-align:right;"></td>
            </tr>
            <tr>
              <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;">Due to BIR</td>
              <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;text-align:center;">20201010</td>
              <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;text-align:right;"></td>
              <td contenteditable="true" style="padding:2pt 4pt;border-bottom:1px solid #eee;text-align:right;"><?php echo number_format($tax_amt,2); ?></td>
            </tr>
            <tr>
              <td contenteditable="true" style="padding:2pt 4pt;">Cash in Bank</td>
              <td contenteditable="true" style="padding:2pt 4pt;text-align:center;">10102020</td>
              <td contenteditable="true" style="padding:2pt 4pt;text-align:right;"></td>
              <td contenteditable="true" style="padding:2pt 4pt;text-align:right;"><?php echo number_format($net_amount,2); ?></td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- C + D Row -->
    <div class="doc-row">
      <div class="doc-cell" style="width:50%;padding:3pt 6pt;">
        <div><span class="doc-label" style="font-size:9.5pt;">C.</span> <span style="font-size:9.5pt;font-weight:bold;">Certified:</span></div>
        <div style="margin-left:10pt;font-size:9.5pt;margin-top:3pt;">
          <div style="margin-bottom:3pt;" onclick="toggleCheck(this.querySelector('.checkbox-box'))" style="cursor:pointer;">
            <span class="checkbox-box" data-key="chk_cash_avail" data-checked="0" onclick="toggleCheck(this);event.stopPropagation();"></span> Cash available
          </div>
          <div style="margin-bottom:3pt;cursor:pointer;" onclick="toggleCheck(this.querySelector('.checkbox-box'))">
            <span class="checkbox-box" data-key="chk_authority" data-checked="0" onclick="toggleCheck(this);event.stopPropagation();"></span> Subject to Authority to Debit Account (when applicable)
          </div>
          <div style="cursor:pointer;" onclick="toggleCheck(this.querySelector('.checkbox-box'))">
            <span class="checkbox-box" data-key="chk_docs" data-checked="0" onclick="toggleCheck(this);event.stopPropagation();"></span> Supporting documents complete and amount claimed proper
          </div>
        </div>
        <div style="margin-top:6pt;">
          <div style="display:flex;align-items:center;gap:4pt;">
            <span style="font-size:8.5pt;width:55pt;">Signature</span>
            <span class="underline-field" style="flex:1;"></span>
          </div>
          <div style="display:flex;align-items:center;gap:4pt;margin-top:3pt;">
            <span style="font-size:8.5pt;width:55pt;">Printed Name</span>
            <span class="sig-name" contenteditable="true" data-key="c_printed_name" data-ph="Printed Name of Chief Accountant" style="flex:1;"></span>
          </div>
          <div style="display:flex;align-items:flex-start;gap:4pt;margin-top:2pt;">
            <span style="font-size:8.5pt;width:55pt;">Position</span>
            <div style="flex:1;">
              <div class="doc-field" contenteditable="true" data-key="c_position1" style="font-size:8.5pt;display:block;border-bottom:1px solid #ccc;">Acting Accountant</div>
              <div class="doc-field" contenteditable="true" data-key="c_position2" style="font-size:8.5pt;display:block;">Head, Accounting Unit/Authorized Representative</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:4pt;margin-top:3pt;">
            <span style="font-size:8.5pt;width:55pt;">Date</span>
            <span class="underline-field doc-field" contenteditable="true" data-key="c_date" style="flex:1;"></span>
          </div>
        </div>
      </div>
      <div class="doc-cell" style="width:50%;padding:3pt 6pt;">
        <div><span class="doc-label" style="font-size:9.5pt;">D.</span> <span style="font-size:9.5pt;font-weight:bold;">Approved for Payment</span></div>
        <div style="margin-top:6pt;">
          <div style="display:flex;align-items:center;gap:4pt;">
            <span style="font-size:8.5pt;width:55pt;">Signature</span>
            <span class="underline-field" style="flex:1;"></span>
          </div>
          <div style="display:flex;align-items:center;gap:4pt;margin-top:3pt;">
            <span style="font-size:8.5pt;width:55pt;">Printed Name</span>
            <span class="sig-name" contenteditable="true" data-key="d_printed_name" data-ph="Printed Name of Authorized Official" style="flex:1;"></span>
          </div>
          <div style="display:flex;align-items:flex-start;gap:4pt;margin-top:2pt;">
            <span style="font-size:8.5pt;width:55pt;">Position</span>
            <div style="flex:1;">
              <div class="doc-field" contenteditable="true" data-key="d_position1" style="font-size:8.5pt;display:block;border-bottom:1px solid #ccc;font-weight:bold;">City Director</div>
              <div class="doc-field" contenteditable="true" data-key="d_position2" style="font-size:8.5pt;display:block;">Agency Head/Authorized Representative</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:4pt;margin-top:3pt;">
            <span style="font-size:8.5pt;width:55pt;">Date</span>
            <span class="underline-field doc-field" contenteditable="true" data-key="d_date" style="flex:1;"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- E: Receipt of Payment -->
    <div class="doc-row">
      <div class="doc-cell" style="width:100%;padding:3pt 6pt;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span><span class="doc-label" style="font-size:9.5pt;">E.</span> <span style="font-size:9.5pt;font-weight:bold;">Receipt of Payment</span></span>
          <span style="font-size:9pt;">JEV No. <span class="doc-field underline-field" contenteditable="true" data-key="jev_no" style="min-width:80pt;"></span></span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6pt;margin-top:4pt;font-size:9pt;">
          <div>
            <div style="display:flex;align-items:center;gap:3pt;margin-bottom:3pt;">
              <span style="font-size:8.5pt;min-width:55pt;">Check/ADA No.:</span>
              <span class="underline-field doc-field" contenteditable="true" data-key="check_no" style="flex:1;"></span>
            </div>
            <div style="display:flex;align-items:center;gap:3pt;margin-bottom:3pt;">
              <span style="font-size:8.5pt;min-width:55pt;white-space:nowrap;">Date:</span>
              <span class="underline-field doc-field" contenteditable="true" data-key="check_date" style="flex:1;"></span>
            </div>
            <div style="display:flex;align-items:center;gap:3pt;">
              <span style="font-size:8.5pt;min-width:55pt;">Signature:</span>
              <span class="underline-field" style="flex:1;"></span>
            </div>
          </div>
          <div>
            <div style="display:flex;align-items:center;gap:3pt;margin-bottom:3pt;">
              <span style="font-size:8.5pt;min-width:90pt;white-space:nowrap;">Bank Name &amp; Account No.:</span>
              <span class="underline-field doc-field" contenteditable="true" data-key="bank_acct" style="flex:1;"></span>
            </div>
            <div style="display:flex;align-items:center;gap:3pt;margin-bottom:3pt;">
              <span style="font-size:8.5pt;min-width:90pt;white-space:nowrap;">Printed Name:</span>
              <span class="sig-name" contenteditable="true" data-key="receipt_printed_name" data-ph="Printed Name of Payee" style="flex:1;display:inline-block;border-bottom:1px solid #555;min-height:14pt;"></span>
            </div>
            <div style="display:flex;align-items:center;gap:3pt;">
              <span style="font-size:8.5pt;min-width:90pt;white-space:nowrap;">Date:</span>
              <span class="underline-field doc-field" contenteditable="true" data-key="receipt_date" style="flex:1;"></span>
            </div>
          </div>
        </div>
        <div style="margin-top:4pt;font-size:8.5pt;">
          <span class="doc-field" contenteditable="true" data-key="official_receipt" data-ph="Official Receipt No. &amp; Date/Other Documents">Official Receipt No. &amp; Date/Other Documents</span>
        </div>
      </div>
    </div>

  </div><!-- /doc-outer -->

  <?php if($pr||$rfq||$po||$iar): ?>
  <div class="linked-docs" style="margin-top:8pt;padding:4pt 6pt;background:#fef2f2;border-radius:4px;font-size:8.5pt;border:1px solid #fecaca;">
    <strong style="color:#dc2626;">Linked Documents:</strong>
    <?php if($pr): ?>&nbsp;<a href="pr_view.php?id=<?php echo $pr['id']; ?>" style="color:#2563eb;"><?php echo safe($pr['pr_number']); ?></a><?php endif; ?>
    <?php if($rfq): ?>&nbsp;&bull;&nbsp;<a href="rfq_view.php?id=<?php echo $rfq['id']; ?>" style="color:#059669;"><?php echo safe($rfq['rfq_number']); ?></a><?php endif; ?>
    <?php if($po): ?>&nbsp;&bull;&nbsp;<a href="po_view.php?id=<?php echo $po['id']; ?>" style="color:#7c3aed;"><?php echo safe($po['po_number']); ?></a><?php endif; ?>
    <?php if($iar): ?>&nbsp;&bull;&nbsp;<a href="iar_view.php?id=<?php echo $iar['id']; ?>" style="color:#d97706;"><?php echo safe($iar['iar_number']); ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</div></div>
</main>
<script>
window.DOC_READONLY = <?php echo $canEdit ? 'false' : 'true'; ?>;
window.DV_ID = <?php echo $id; ?>;
</script>
<script src="assets/doc_utils.js"></script>
<script>
function saveDocToDB() {
  if (window.DOC_READONLY) return;
  var btn = document.querySelector('.tbtn-save');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  function gv(key) {
    var el = document.querySelector('[data-key="' + key + '"]');
    if (!el) return '';
    return (el.value !== undefined ? el.value : (el.innerText || '')).trim();
  }
  var gross = parseFloat(gv('gross_amt').replace(/,/g,'')) || 0;
  var taxAmt= parseFloat(gv('tax_amt').replace(/,/g,'')) || 0;
  var net   = parseFloat(gv('net_amt').replace(/,/g,'')) || 0;
  // Collect accounting entries
  var items = [];
  document.querySelectorAll('#acctBody tr').forEach(function(tr) {
    var tds = tr.querySelectorAll('td[contenteditable]');
    if (tds.length < 4) return;
    var title  = (tds[0].innerText||'').trim();
    var uacs   = (tds[1].innerText||'').trim();
    var debit  = (tds[2].innerText||'').replace(/,/g,'').trim();
    var credit = (tds[3].innerText||'').replace(/,/g,'').trim();
    if (!title && !uacs && !debit && !credit) return;
    items.push({account_title:title, uacs_code:uacs, debit:debit, credit:credit});
  });
  var fd = new FormData();
  fd.append('dv_id',        DV_ID);
  fd.append('dv_date',      gv('dv_date'));
  fd.append('supplier',     gv('payee'));
  fd.append('gross_amount', gross);
  fd.append('tax_amount',   taxAmt);
  fd.append('net_amount',   net);
  fd.append('tax_type',     gv('tax_type'));
  fd.append('status',       '');
  fd.append('items',        JSON.stringify(items));
  fetch('save_dv_doc.php', {method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(data){
      btn.disabled=false; btn.innerHTML='<i class="fas fa-check-circle"></i> Save';
      if(data.success){showToast('DV saved \u2713','success');saveDraft(true);}
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
  var dvNo       = g('dv_number');
  var dvDate     = g('dv_date');
  var fieldOffice= g('dti_field_office');
  var fundCluster= g('fund_cluster');
  var payee      = g('payee');
  var tin        = g('tin');
  var orsNo      = g('ors_no');
  var address    = g('payee_address');
  var particulars= g('particulars');
  var grossAmt   = g('gross_amt');
  var taxType    = g('tax_type');
  var taxAmt     = g('tax_amt');
  var netAmt     = g('net_amt');
  var respCenter = g('resp_center');
  var mfoPap     = g('mfo_pap');
  var amtDue     = g('amount_due');
  var supvName   = g('supervisor_name');
  var cName      = g('c_printed_name');
  var cPos1      = g('c_position1');
  var cPos2      = g('c_position2');
  var cDate      = g('c_date');
  var dName      = g('d_printed_name');
  var dPos1      = g('d_position1');
  var dPos2      = g('d_position2');
  var dDate      = g('d_date');
  var jevNo      = g('jev_no');
  var checkNo    = g('check_no');
  var bankAcct   = g('bank_acct');
  var recptName  = g('receipt_printed_name');
  var officialRcpt=g('official_receipt');

  var chkMds     = chk('chk_mds')   ? '[✓] MDS Check'  : '[ ] MDS Check';
  var chkComm    = chk('chk_comm')  ? '[✓] Commercial Check' : '[ ] Commercial Check';
  var chkAda     = chk('chk_ada')   ? '[✓] ADA'         : '[ ] ADA';
  var chkCashAv  = chk('chk_cash_avail') ? '[✓] Cash available' : '[ ] Cash available';
  var chkAuth    = chk('chk_authority')  ? '[✓] Subject to Authority to Debit Account' : '[ ] Subject to Authority to Debit Account';
  var chkDocs    = chk('chk_docs')       ? '[✓] Supporting documents complete and amount claimed proper' : '[ ] Supporting documents complete and amount claimed proper';

  var acctRows = [];
  document.querySelectorAll('#acctBody tr').forEach(function(tr){
    var tds = [...tr.querySelectorAll('td')];
    if(tds.length === 4){
      acctRows.push(tds.map(function(td){ return (td.innerText||'').trim(); }));
    }
  });

  var rows = [];
  // Header
  rows.push(['Department of Trade and Industry','','','']);
  rows.push([fieldOffice,'','','']);
  rows.push(['DISBURSEMENT VOUCHER','','','']);
  rows.push(['Fund Cluster:',fundCluster,'Date:',dvDate]);
  rows.push(['','','DV No.:',dvNo]);
  rows.push(['','','','']);
  // Mode of Payment
  rows.push(['MODE OF PAYMENT:',chkMds,chkComm,chkAda]);
  rows.push(['','','','']);
  // Payee
  rows.push(['Payee:',payee,'TIN/Employee No.:',tin]);
  rows.push(['Address:',address,'ORS/BURS No.:',orsNo]);
  rows.push(['','','','']);
  // Particulars header
  rows.push(['Particulars','Responsibility Center','MFO/PAP','Amount']);
  // Particulars body
  rows.push([particulars,respCenter,mfoPap,'']);
  rows.push(['Gross Amount: '+grossAmt,'','','']);
  rows.push(['Less Tax ('+taxType+'): '+taxAmt,'','','']);
  rows.push(['Net Amount: '+netAmt,'','','']);
  rows.push(['','','Amount Due:',amtDue]);
  rows.push(['','','','']);
  // Section A
  rows.push(['A. Certified: Expenses/Cash Advance necessary, lawful and incurred under my direct supervision.','','','']);
  rows.push(['Printed Name, Designation and Signature of Supervisor:',supvName,'','']);
  rows.push(['','','','']);
  // Section B: Accounting
  rows.push(['B. ACCOUNTING ENTRY:','','','']);
  rows.push(['Account Title','UACS Code','Debit','Credit']);
  acctRows.forEach(function(r){ rows.push(r); });
  rows.push(['','','','']);
  // Section C & D
  rows.push(['C. Certified:','','D. Approved for Payment:','']);
  rows.push([chkCashAv,'','Signature:','']);
  rows.push([chkAuth,'','Printed Name:',dName]);
  rows.push([chkDocs,'','Position:',dPos1+' / '+dPos2]);
  rows.push(['Signature:','','Date:',dDate]);
  rows.push(['Printed Name:',cName,'','']);
  rows.push(['Position:',cPos1+' / '+cPos2,'','']);
  rows.push(['Date:',cDate,'','']);
  rows.push(['','','','']);
  // Section E
  rows.push(['E. Receipt of Payment','','JEV No.:',jevNo]);
  rows.push(['Check/ADA No.:',checkNo,'Bank Name & Account No.:',bankAcct]);
  rows.push(['Printed Name (Payee):',recptName,'','']);
  rows.push(['Official Receipt / Other Documents:',officialRcpt,'','']);

  var merges = [
    {s:{r:0,c:0},e:{r:0,c:3}},
    {s:{r:1,c:0},e:{r:1,c:3}},
    {s:{r:2,c:0},e:{r:2,c:3}},
    {s:{r:6,c:0},e:{r:6,c:0}},
    {s:{r:9,c:1},e:{r:9,c:1}},
    {s:{r:12,c:0},e:{r:12,c:0}},
    {s:{r:13,c:0},e:{r:13,c:3}},
    {s:{r:14,c:0},e:{r:14,c:3}},
    {s:{r:15,c:0},e:{r:15,c:3}},
    {s:{r:17,c:0},e:{r:17,c:3}},
    {s:{r:18,c:0},e:{r:18,c:3}},
    {s:{r:19,c:1},e:{r:19,c:3}},
    {s:{r:20,c:0},e:{r:20,c:3}},
  ];

  var cols = [{wch:36},{wch:22},{wch:22},{wch:16}];

  // Derive dynamic row indices based on acctRows length
  var acctHeaderRow = 21;
  var secCDRow = acctHeaderRow + 1 + acctRows.length + 1; // after acct rows + spacer

  var opts = {
    titleRows:    [2],
    subTitleRows: [0],
    headerRows:   [11, acctHeaderRow],
    noBorderRows: [5, 7, 10, 17],
    labelCols:    [0, 2],
    amountCols:   [3],
    cellStyles:   {}
  };
  // Dept / Field office rows
  opts.cellStyles['0_0'] = 'font-weight:bold;text-align:center;font-size:12pt;background:#1E3A8A;color:#fff;';
  opts.cellStyles['1_0'] = 'text-align:center;background:#2563EB;color:#fff;';
  // Fund cluster row
  opts.cellStyles['3_0'] = 'font-weight:bold;background:#F3F4F6;';
  opts.cellStyles['3_2'] = 'font-weight:bold;background:#F3F4F6;';
  // Mode of payment label
  opts.cellStyles['6_0'] = 'font-weight:bold;background:#F0F4FF;';
  // Payee labels
  opts.cellStyles['8_0'] = 'font-weight:bold;background:#F3F4F6;';
  opts.cellStyles['8_2'] = 'font-weight:bold;background:#F3F4F6;';
  opts.cellStyles['9_0'] = 'font-weight:bold;background:#F3F4F6;';
  opts.cellStyles['9_2'] = 'font-weight:bold;background:#F3F4F6;';
  // Gross/Tax/Net amount rows
  [13,14,15].forEach(function(rr){
    opts.cellStyles[rr+'_0'] = 'font-weight:bold;background:#FFFBEB;';
  });
  // Amount due row
  opts.cellStyles['16_2'] = 'font-weight:bold;background:#F3F4F6;';
  opts.cellStyles['16_3'] = 'font-weight:bold;text-align:right;color:#1E3A8A;background:#EFF6FF;';
  // Section A label
  opts.cellStyles['18_0'] = 'font-weight:bold;background:#F0F4FF;color:#1E40AF;';
  // Section B label
  opts.cellStyles['20_0'] = 'font-weight:bold;background:#F0F4FF;color:#1E40AF;';

  buildXlsx(rows, merges, cols, '<?php echo safe($dv["dv_number"]); ?>_DisbursementVoucher', opts);
}
</script>
<script src="assets/smart-dropdown.js"></script>
<script>
<?php if($canEdit): ?>
SmartDropdown.init('#sd_dv_fund',   'fund_cluster');
SmartDropdown.init('#sd_dv_payee',  'payee');
SmartDropdown.init('#sd_dv_tin',    'tin');
SmartDropdown.init('#sd_dv_addr',   'payee_address');
<?php endif; ?>
</script>
<?php include 'inc/footer.php'; ?>
