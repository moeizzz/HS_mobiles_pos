<?php
// ---------------------------------------------------------------
// Central authentication guard: every page that loads functions.php
// (all module pages) requires a logged-in session. login.php does not
// load this file.
// ---------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user_id'])) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $is_json = in_array(basename($script), ['sale_view.php', 'search.php', 'ajax_get_product.php', 'purchase_details.php'], true)
        || isset($_GET['check_imei'])
        || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    if ($is_json) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    $pos = strpos($script, '/modules/');
    $app_root = $pos !== false ? substr($script, 0, $pos) : rtrim(dirname($script), '/');
    header('Location: ' . $app_root . '/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/settings.php';

// ---------------------------------------------------------------
// Global CSRF guard. Every state-changing request must carry the session token:
//   * any POST                     -> hidden field "csrf" (added to every form by the footer script)
//   * any GET with a del*= param   -> "&csrf=..." (added to delete links by the footer script)
// ---------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
} else {
    foreach (array_keys($_GET) as $__k) {
        if (preg_match('/^del/i', (string)$__k)) { csrf_check(); break; }
    }
}

// Get all records from a table
function getAll($table, $order = 'id DESC') {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM $table ORDER BY $order");
    return $stmt->fetchAll();
}

// Get single record by ID
function getById($table, $id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get records with a condition
function getWhere($table, $column, $value, $order = 'id DESC') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE $column = ? ORDER BY $order");
    $stmt->execute([$value]);
    return $stmt->fetchAll();
}

// Insert and return last ID
function insert($table, $data) {
    global $pdo;
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
    $stmt->execute(array_values($data));
    return $pdo->lastInsertId();
}

// Update record
function update($table, $data, $id) {
    global $pdo;
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    $stmt = $pdo->prepare("UPDATE $table SET $sets WHERE id = ?");
    $stmt->execute([...array_values($data), $id]);
    return $stmt->rowCount();
}

// Delete record
function delete($table, $id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount();
}

// Count records
function countRows($table, $column = null, $value = null) {
    global $pdo;
    if ($column && $value !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column = ?");
        $stmt->execute([$value]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
    }
    return $stmt->fetchColumn();
}

// Generate invoice number
function generateInvoiceNo() {
    global $pdo;
    $prefix = 'INV-' . date('ymd') . '-';
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no, ?) AS UNSIGNED)), 0) FROM sales WHERE invoice_no LIKE ?");
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
    return $prefix . str_pad((int)$stmt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
}

// Generate customer number
function generateCustomerNo() {
    global $pdo;
    $prefix = 'CUS-' . date('ym') . '-';
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(customer_no, ?) AS UNSIGNED)), 0) FROM customers WHERE customer_no LIKE ?");
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
    return $prefix . str_pad((int)$stmt->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
}

// Generate product code (e.g. MOB-0001, GEN-0001)
function generateProductCode($type = 'general') {
    global $pdo;
    $prefix = ($type === 'mobile' ? 'MOB' : 'GEN') . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE code LIKE ?");
    $stmt->execute(["$prefix%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    $code = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
    while (getWhere('products', 'code', $code)) {
        $count++;
        $code = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
    return $code;
}

// Find an active product by exact name + type (case-insensitive, trimmed) — for merge
function findProductByNameType($name, $type = 'general') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_type = ? AND TRIM(LOWER(name)) = ? AND status = 1 ORDER BY id LIMIT 1");
    $stmt->execute([$type, strtolower(trim($name))]);
    return $stmt->fetch() ?: null;
}

// Resolve a product from a free-text sale description (e.g. "Apple iPhone 13 (MOB-0001) [IMEI: x]")
function resolveProductFromDescription($pdo, $desc) {
    $d = strtolower(trim($desc));
    $d = preg_replace('/\[[^\]]*imei[^\]]*\]/i', '', $d);
    $d = preg_replace('/\((.*?)\)/', '', $d);
    $d = trim(preg_replace('/\s+/', ' ', $d));
    if ($d === '') return null;
    $stmt = $pdo->prepare("SELECT id FROM products WHERE TRIM(LOWER(name)) = ? AND status = 1 ORDER BY id LIMIT 1");
    $stmt->execute([$d]);
    if ($id = $stmt->fetchColumn()) return (int)$id;
    $stmt = $pdo->prepare("SELECT id FROM products WHERE TRIM(LOWER(code)) = ? AND status = 1 ORDER BY id LIMIT 1");
    $stmt->execute([$d]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

// Format currency
function formatCurrency($amount) {
    return number_format($amount, 2);
}

// Format date
function formatDate($date) {
    return $date ? date('d-m-Y', strtotime($date)) : '-';
}

// Get customer full name with ID
function customerName($id) {
    $c = getById('customers', $id);
    return $c ? $c['full_name'] : 'N/A';
}

// Record a cash inflow in cash book
function recordCashInflow($pdo, $date, $amount, $description, $reference_type = null, $reference_id = null, $created_by = null) {
    $today = ($date && preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) ? substr($date, 0, 10) : date('Y-m-d');
    $daily = $pdo->prepare("SELECT * FROM cash_book_daily WHERE date = ?");
    $daily->execute([$today]);
    $daily_rec = $daily->fetch();

    if ($daily_rec) {
        $daily_id = $daily_rec['id'];
    } else {
        $prev_st = $pdo->prepare("SELECT closing_balance FROM cash_book_daily WHERE date < ? ORDER BY date DESC LIMIT 1");
        $prev_st->execute([$today]);
        $prev = $prev_st->fetch();
        $opening = $prev ? (float)$prev['closing_balance'] : 0;
        $daily_id = insert('cash_book_daily', [
            'date' => $today,
            'opening_balance' => $opening,
            'total_inflow' => 0,
            'total_outflow' => 0,
            'closing_balance' => $opening,
            'status' => 'open',
            'created_by' => $created_by,
            'created_at' => date('Y-m-d'),
        ]);
    }

    insert('cash_book', [
        'daily_id' => $daily_id,
        'transaction_date' => $today,
        'transaction_type' => 'inflow',
        'amount' => $amount,
        'description' => $description,
        'reference_type' => $reference_type,
        'reference_id' => $reference_id,
        'created_by' => $created_by,
        'created_at' => date('Y-m-d'),
    ]);

    $update = $pdo->prepare("UPDATE cash_book_daily SET total_inflow = total_inflow + ?, closing_balance = opening_balance + total_inflow - total_outflow WHERE id = ?");
    $update->execute([$amount, $daily_id]);
}

// Record bank inflow
function recordBankInflow($pdo, $date, $amount, $description, $reference_type = null, $reference_id = null, $created_by = null, $bank_account_id = null) {
    if ($bank_account_id) {
        $stmt = $pdo->prepare("SELECT id, current_balance FROM bank_accounts WHERE id = ? AND status = 1");
        $stmt->execute([$bank_account_id]);
        $account = $stmt->fetch();
    } else {
        $stmt = $pdo->query("SELECT id, current_balance FROM bank_accounts WHERE status = 1 ORDER BY id ASC LIMIT 1");
        $account = $stmt->fetch();
    }
    if (!$account) {
        $account_id = insert('bank_accounts', [
            'account_name' => 'Default Account',
            'bank_name' => 'Default Bank',
            'account_no' => 'AUTO-' . date('YmdHis'),
            'account_type' => 'current',
            'opening_balance' => 0,
            'current_balance' => 0,
            'status' => 1,
            'created_at' => $date,
        ]);
        $account = ['id' => $account_id, 'current_balance' => 0];
    }

    insert('bank_transactions', [
        'bank_account_id' => $account['id'],
        'transaction_date' => $date,
        'transaction_type' => 'deposit',
        'amount' => $amount,
        'description' => $description,
        'reference_type' => $reference_type,
        'reference_id' => $reference_id,
        'created_by' => $created_by ?: 1,
        'created_at' => date('Y-m-d'),
    ]);

    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$amount, $account['id']]);
    return $account['id'];
}

// Record a cash outflow
function recordCashOutflow($pdo, $date, $amount, $description, $reference_type = null, $reference_id = null, $created_by = null) {
    $today = ($date && preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) ? substr($date, 0, 10) : date('Y-m-d');
    $daily = $pdo->prepare("SELECT * FROM cash_book_daily WHERE date = ?");
    $daily->execute([$today]);
    $daily_rec = $daily->fetch();

    if ($daily_rec) {
        $daily_id = $daily_rec['id'];
    } else {
        $prev_st = $pdo->prepare("SELECT closing_balance FROM cash_book_daily WHERE date < ? ORDER BY date DESC LIMIT 1");
        $prev_st->execute([$today]);
        $prev = $prev_st->fetch();
        $opening = $prev ? (float)$prev['closing_balance'] : 0;
        $daily_id = insert('cash_book_daily', [
            'date' => $today,
            'opening_balance' => $opening,
            'total_inflow' => 0,
            'total_outflow' => 0,
            'closing_balance' => $opening,
            'status' => 'open',
            'created_by' => $created_by,
            'created_at' => date('Y-m-d'),
        ]);
    }

    insert('cash_book', [
        'daily_id' => $daily_id,
        'transaction_date' => $today,
        'transaction_type' => 'outflow',
        'amount' => $amount,
        'description' => $description,
        'reference_type' => $reference_type,
        'reference_id' => $reference_id,
        'created_by' => $created_by,
        'created_at' => date('Y-m-d'),
    ]);

    $update = $pdo->prepare("UPDATE cash_book_daily SET total_outflow = total_outflow + ?, closing_balance = opening_balance + total_inflow - total_outflow WHERE id = ?");
    $update->execute([$amount, $daily_id]);
}

// Record bank outflow
function recordBankOutflow($pdo, $date, $amount, $description, $reference_type = null, $reference_id = null, $created_by = null, $bank_account_id = null) {
    if ($bank_account_id) {
        $stmt = $pdo->prepare("SELECT id, current_balance FROM bank_accounts WHERE id = ? AND status = 1");
        $stmt->execute([$bank_account_id]);
        $account = $stmt->fetch();
    } else {
        $stmt = $pdo->query("SELECT id, current_balance FROM bank_accounts WHERE status = 1 ORDER BY id ASC LIMIT 1");
        $account = $stmt->fetch();
    }
    if (!$account) {
        $account_id = insert('bank_accounts', [
            'account_name' => 'Default Account',
            'bank_name' => 'Default Bank',
            'account_no' => 'AUTO-' . date('YmdHis'),
            'account_type' => 'current',
            'opening_balance' => 0,
            'current_balance' => 0,
            'status' => 1,
            'created_at' => $date,
        ]);
        $account = ['id' => $account_id, 'current_balance' => 0];
    }

    insert('bank_transactions', [
        'bank_account_id' => $account['id'],
        'transaction_date' => $date,
        'transaction_type' => 'withdrawal',
        'amount' => $amount,
        'description' => $description,
        'reference_type' => $reference_type,
        'reference_id' => $reference_id,
        'created_by' => $created_by ?: 1,
        'created_at' => date('Y-m-d'),
    ]);

    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $account['id']]);
    return $account['id'];
}

// Redirect with message
function redirect($url, $msg = null, $type = 'success') {
    if ($msg) {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION[$type] = $msg;
    }
    header("Location: $url");
    exit;
}

// ---- Reverse cash/bank book entries (moved here from purchase_edit.php; shared with view_purchase.php) ----
function reverseRef($pdo, $type, $refId) {
    $st = $pdo->prepare("SELECT * FROM cash_book WHERE reference_type = ? AND reference_id = ? AND transaction_type = 'outflow'");
    $st->execute([$type, $refId]);
    foreach ($st->fetchAll() as $r) {
        $pdo->prepare("UPDATE cash_book_daily SET total_outflow = GREATEST(0, total_outflow - ?), closing_balance = opening_balance + total_inflow - total_outflow WHERE id = ?")->execute([$r['amount'], $r['daily_id']]);
        $pdo->prepare("DELETE FROM cash_book WHERE id = ?")->execute([$r['id']]);
    }
    $st = $pdo->prepare("SELECT * FROM bank_transactions WHERE reference_type = ? AND reference_id = ? AND transaction_type = 'withdrawal'");
    $st->execute([$type, $refId]);
    foreach ($st->fetchAll() as $r) {
        $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$r['amount'], $r['bank_account_id']]);
        $pdo->prepare("DELETE FROM bank_transactions WHERE id = ?")->execute([$r['id']]);
    }
}

function reversePurchaseOutflows($pdo, $purchaseId, $supplierId, $invoiceNo, $purchaseDate) {
    reverseRef($pdo, 'purchase', $purchaseId);
    if ($supplierId) {
        $descs = ['Purchase payment'];
        if ($invoiceNo) $descs[] = "Purchase payment - Invoice $invoiceNo";
        $ph = implode(',', array_fill(0, count($descs), '?'));
        $st = $pdo->prepare("SELECT id FROM supplier_payments WHERE supplier_id = ? AND (purchase_id = ? OR (payment_date = ? AND description IN ($ph)))");
        $st->execute(array_merge([$supplierId, $purchaseId, $purchaseDate], $descs));
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            reverseRef($pdo, 'supplier_payment', (int)$pid);
            $pdo->prepare("DELETE FROM supplier_payments WHERE id = ?")->execute([(int)$pid]);
        }
    }
}

// ---- CSRF protection for state-changing POST forms ----
function csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}
function csrf_check() {
    $sent = (string)($_POST['csrf'] ?? $_GET['csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Security token expired. Go back, refresh the page and try again.');
    }
}

/**
 * Validate the customer form (create + edit). Returns an error string, or '' when the input is fine.
 * Keeps the customer ledger clean: a name is required, the CNIC must look like XXXXX-XXXXXXX-X,
 * the phone must look like a phone number and the opening balance cannot be negative.
 */
function validate_customer_input(array $in) {
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') return 'Customer name is required.';
    if (mb_strlen($name) > 100) return 'Customer name is too long (max 100 characters).';
    $cnic = trim((string)($in['cnic'] ?? ''));
    if ($cnic !== '' && !preg_match('/^\d{5}-\d{7}-\d$/', $cnic)) return 'CNIC must be in the format XXXXX-XXXXXXX-X.';
    $phone = trim((string)($in['phone'] ?? ''));
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) return 'Phone number looks invalid.';
    if ((float)($in['opening_balance'] ?? 0) < 0) return 'Opening balance cannot be negative.';
    return '';
}

/** Validate the supplier form (create + edit). Returns an error string, or '' when fine. */
function validate_supplier_input(array $in) {
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') return 'Supplier name is required.';
    if (mb_strlen($name) > 100) return 'Supplier name is too long (max 100 characters).';
    $phone = trim((string)($in['phone'] ?? ''));
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) return 'Phone number looks invalid.';
    $email = trim((string)($in['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Email address looks invalid.';
    return '';
}
