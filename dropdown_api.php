<?php
/**
 * Smart Dropdown API
 * GET  ?field=entity_name          → returns JSON array of values
 * POST {field, value}               → saves a new value, returns {ok, id, value}
 */
require_once 'inc/permissions.php';
require_once 'session_check.php';
require_once 'config/db.php';
require_once 'inc/dropdown_setup.php';

ensureDropdownTable($conn);

header('Content-Type: application/json');

$allowed_fields = [
    'entity_name', 'fund_cluster', 'supplier',
    'invoice_prefix', 'payee_name', 'payee', 'tin', 'payee_address'
];

$method = $_SERVER['REQUEST_METHOD'];

// Special action: get PO ID by PR ID
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_po_by_pr') {
    $pr_id = (int)($_GET['pr_id'] ?? 0);
    $row = $conn->query("SELECT id FROM purchase_orders WHERE pr_id=$pr_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
    echo json_encode(['po_id' => $row ? (int)$row['id'] : null]);
    exit;
}

if ($method === 'GET') {
    $field = trim($_GET['field'] ?? '');
    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['error' => 'Invalid field']); exit;
    }
    $stmt = $conn->prepare(
        "SELECT id, value_text, use_count FROM dropdown_values
         WHERE field_name = ?
         ORDER BY use_count DESC, value_text ASC"
    );
    $stmt->bind_param("s", $field);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode($rows);
    exit;
}

if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $field = trim($body['field'] ?? '');
    $value = trim($body['value'] ?? '');

    if (!in_array($field, $allowed_fields) || $value === '') {
        echo json_encode(['error' => 'Invalid input']); exit;
    }

    $username = $_SESSION['username'] ?? 'system';

    // Insert or increment use_count
    $stmt = $conn->prepare(
        "INSERT INTO dropdown_values (field_name, value_text, use_count, created_by)
         VALUES (?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE use_count = use_count + 1"
    );
    $stmt->bind_param("sss", $field, $value, $username);
    $stmt->execute();
    $id = $conn->insert_id ?: 0;
    $stmt->close();

    echo json_encode(['ok' => true, 'id' => $id, 'value' => $value]);
    exit;
}

if ($method === 'DELETE') {
    $body  = json_decode(file_get_contents('php://input'), true) ?: [];
    $field = trim($body['field'] ?? '');
    $value = trim($body['value'] ?? '');

    if (!in_array($field, $allowed_fields) || $value === '') {
        echo json_encode(['error' => 'Invalid input']); exit;
    }
    $stmt = $conn->prepare("DELETE FROM dropdown_values WHERE field_name=? AND value_text=?");
    $stmt->bind_param("ss", $field, $value);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Method not allowed']);
