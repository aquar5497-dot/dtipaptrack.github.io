<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// Get IAR ID
if (!isset($_GET['id'])) {
  header("Location: iar_list.php");
  exit;
}
$id = intval($_GET['id']);

// Fetch existing IAR record
$stmt = $conn->prepare("SELECT * FROM iars WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$iar = $result->fetch_assoc();

if (!$iar) {
  echo "<script>alert('IAR not found.'); window.location='iar_list.php';</script>";
  exit;
}

// Fetch POs for linking
$pos = $conn->query("SELECT id, po_number, supplier FROM purchase_orders ORDER BY id DESC");

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $iar_date = $_POST['iar_date'];
  $invoice_number = $_POST['invoice_number'];
  $invoice_date = $_POST['invoice_date'];
  $date_inspected = $_POST['date_inspected'];
  $date_received = $_POST['date_received'];
  $status = $_POST['status'];
  $po_id = $_POST['po_id'];

  $stmt = $conn->prepare("UPDATE iars SET 
      iar_date = ?, 
      invoice_number = ?, 
      invoice_date = ?, 
      date_inspected = ?, 
      date_received = ?, 
      status = ?, 
      po_id = ?
      WHERE id = ?");
  $stmt->bind_param("ssssssii", $iar_date, $invoice_number, $invoice_date, $date_inspected, $date_received, $status, $po_id, $id);
  $stmt->execute();

  echo "<script>alert('IAR updated successfully!')
  logAudit('IAR','UPDATE',$id,$iar['iar_number'],['iar_date'=>$iar['iar_date'],'status'=>$iar['status'],'invoice_number'=>$iar['invoice_number']],['iar_date'=>$iar_date,'status'=>$status,'invoice_number'=>$invoice_number]);
  echo "<script>alert('IAR updated successfully!'); window.location='iar_list.php';</script>";
  exit;
}
?>

<main class="flex-1 p-6">
  <div class="max-w-2xl mx-auto bg-white shadow rounded-xl p-6">
    <h2 class="text-xl font-bold text-gray-700 mb-4">Edit Inspection & Acceptance Report (IAR)</h2>
    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm font-medium">IAR Number</label>
        <input type="text" value="<?php echo htmlspecialchars($iar['iar_number']); ?>" readonly class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100">
      </div>
      <div>
        <label class="block text-sm font-medium">IAR Date</label>
        <input type="date" name="iar_date" value="<?php echo htmlspecialchars($iar['iar_date']); ?>" required class="mt-1 block w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium">Invoice Number</label>
        <input type="text" name="invoice_number" value="<?php echo htmlspecialchars($iar['invoice_number']); ?>" required class="mt-1 block w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium">Invoice Date</label>
        <input type="date" name="invoice_date" value="<?php echo htmlspecialchars($iar['invoice_date']); ?>" required class="mt-1 block w-full border rounded px-3 py-2">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium">Date Inspected</label>
          <input type="date" name="date_inspected" value="<?php echo htmlspecialchars($iar['date_inspected']); ?>" required class="mt-1 block w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block text-sm font-medium">Date Received</label>
          <input type="date" name="date_received" value="<?php echo htmlspecialchars($iar['date_received']); ?>" required class="mt-1 block w-full border rounded px-3 py-2">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium">Status</label>
        <select name="status" required class="mt-1 block w-full border rounded px-3 py-2">
          <option value="Complete" <?php if($iar['status'] == 'Complete') echo 'selected'; ?>>Complete</option>
          <option value="Lacking" <?php if($iar['status'] == 'Lacking') echo 'selected'; ?>>Lacking</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium">Linked PO</label>
        <select name="po_id" required class="mt-1 block w-full border rounded px-3 py-2">
          <option value="">-- Select Purchase Order --</option>
          <?php while($po=$pos->fetch_assoc()): ?>
            <option value="<?php echo $po['id']; ?>" <?php if($iar['po_id'] == $po['id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($po['po_number']." - ".$po['supplier']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="flex justify-end space-x-2">
        <a href="iar_list.php" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Cancel</a>
        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded shadow">Update</button>
      </div>
    </form>
  </div>
</main>

<?php include 'inc/footer.php'; ?>
