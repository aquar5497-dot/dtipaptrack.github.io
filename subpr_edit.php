<?php
require_once 'inc/permissions.php';
require_once 'session_check.php';
require 'config/db.php';
include 'inc/header.php';
include 'inc/sidebar.php';

// Ensure an ID is provided
if (!isset($_GET['id'])) {
    echo "<script>alert('No Sub-PR selected.');window.location='subpr_list.php';</script>";
    exit;
}

$id = intval($_GET['id']);

// ✅ SECURE: Use a prepared statement to fetch the Sub-PR details
$stmt = $conn->prepare("SELECT * FROM purchase_requests WHERE id = ? AND parent_id IS NOT NULL");
$stmt->bind_param("i", $id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) {
    echo "<script>alert('Sub-PR not found.');window.location='subpr_list.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Begin a transaction to ensure data integrity
    $conn->begin_transaction();

    try {
        // Get form data
        $pr_date = $_POST['pr_date'];
        $entity_name = $_POST['entity_name'];
        $fund_cluster = $_POST['fund_cluster'];
        $purpose = $_POST['purpose']; // The purpose text that might have issues
        $total_amount = floatval($_POST['total_amount']);

        // 1. Get old amount and parent_id to calculate the difference
        $stmt_get = $conn->prepare("SELECT total_amount, parent_id FROM purchase_requests WHERE id = ?");
        $stmt_get->bind_param('i', $id);
        $stmt_get->execute();
        $old_data = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if ($old_data && $old_data['parent_id']) {
            $diff = $total_amount - $old_data['total_amount'];
            
            // 2. ✅ SECURE: Update parent PR's total amount
            $stmt_update_parent = $conn->prepare("UPDATE purchase_requests SET total_amount = total_amount + ? WHERE id = ?");
            $stmt_update_parent->bind_param('di', $diff, $old_data['parent_id']);
            $stmt_update_parent->execute();
            $stmt_update_parent->close();
        }

        // 3. ✅ SECURE: Update the Sub-PR itself, including the purpose
        $stmt_update_sub = $conn->prepare(
            "UPDATE purchase_requests 
             SET pr_date = ?, entity_name = ?, fund_cluster = ?, purpose = ?, total_amount = ?
             WHERE id = ?"
        );
        $stmt_update_sub->bind_param("ssssdi", $pr_date, $entity_name, $fund_cluster, $purpose, $total_amount, $id);
        $stmt_update_sub->execute();
        $stmt_update_sub->close();

        // If everything was successful, commit the changes
        $conn->commit();

        echo "<script>alert('Sub-PR updated successfully!');window.location='subpr_list.php';</script>";
        exit;

    } catch (mysqli_sql_exception $exception) {
        // If any query failed, roll back all changes
        $conn->rollback();
        // Display a detailed error message
        echo "<script>alert('Error updating Sub-PR: " . addslashes($exception->getMessage()) . "');</script>";
    }
}
?>

<main class="flex-1 p-6 overflow-y-auto">
    <div class="bg-white p-6 rounded-xl shadow max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-4">
             <h2 class="text-xl font-bold text-gray-800">Edit Sub-PR for <?= htmlspecialchars($sub['pr_number']) ?></h2>
             <a href="subpr_list.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded-md text-sm">
                &larr; Back to List
            </a>
        </div>

        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PR Date</label>
                <input type="date" name="pr_date" value="<?= htmlspecialchars($sub['pr_date']) ?>" required class="w-full border rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Entity Name</label>
                <input type="text" name="entity_name" value="<?= htmlspecialchars($sub['entity_name']) ?>" required class="w-full border rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fund Cluster</label>
                <input type="text" name="fund_cluster" value="<?= htmlspecialchars($sub['fund_cluster']) ?>" required class="w-full border rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount</label>
                <input type="number" step="0.01" name="total_amount" value="<?= htmlspecialchars($sub['total_amount']) ?>" required class="w-full border rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                <textarea name="purpose" rows="3" required class="w-full border rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none"><?= htmlspecialchars($sub['purpose']) ?></textarea>
            </div>
            <div class="md:col-span-2 text-right mt-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md font-medium shadow-md transition-transform transform hover:scale-105">Save Changes</button>
            </div>
        </form>
    </div>
</main>

<?php include 'inc/footer.php'; ?>