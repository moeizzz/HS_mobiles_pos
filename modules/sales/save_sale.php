<?php
/**
 * Sale processor for the New Sale window (new_sale.php). POST only.
 *
 * Everything below runs in ONE transaction: sale header, items, IMEI -> sold, stock, payment,
 * cash/bank book. Any failure rolls the whole sale back.
 *
 * Notes on this schema:
 *  - Stock column is products.stock_quantity; serial status enum is 'available'|'sold'|'returned'.
 *  - The exact IMEI of every item is stored through sale_items.serial_id (-> product_serials.imei_number).
 *  - A customer's khata balance is DERIVED (opening + sales - payments - returns), so recording the
 *    sale with its due_amount IS the khata/ledger entry; there is no balance column to update.
 *  - For serial items the product, IMEI and description are read from the database here, never from
 *    what the browser sent. Only the price (cashier may negotiate) and qty (accessories) are taken.
 */
session_start();
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: new_sale.php'); exit; }
csrf_check();

function back($msg) { $_SESSION['error'] = $msg; header('Location: new_sale.php'); exit; }

$user_id = $_SESSION['user_id'] ?? null;

// ---- branch (FK-safe) ----
$branch_id = $_SESSION['branch_id'] ?? null;
if ($branch_id !== null) {
    $c = $pdo->prepare("SELECT id FROM branches WHERE id = ?");
    $c->execute([$branch_id]);
    if (!$c->fetch()) $branch_id = null;
}

// ---- header fields ----
$sale_date = $_POST['sale_date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sale_date)) $sale_date = date('Y-m-d');
$notes = trim($_POST['notes'] ?? '');

$customer_raw = $_POST['customer_id'] ?? '';
$is_walkin = ($customer_raw === 'walkin');
$customer_id = $is_walkin ? 0 : (int)$customer_raw;
$walk_name = trim($_POST['walkin_name'] ?? '');
$walk_phone = trim($_POST['walkin_phone'] ?? '');
$walk_cnic = trim($_POST['walkin_cnic'] ?? '') ?: null;

if ($is_walkin) {
    if ($walk_name === '') back('Enter the walk-in customer name.');
    if ($walk_cnic) {
        $c = $pdo->prepare("SELECT full_name FROM customers WHERE cnic = ?");
        $c->execute([$walk_cnic]);
        if ($dup = $c->fetchColumn()) back("This CNIC already belongs to customer: $dup");
    }
} else {
    $c = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
    $c->execute([$customer_id]);
    if (!$c->fetch()) back('Select a registered customer or choose Walk-in.');
}

// ---- payment ----
$pm = $_POST['payment_method'] ?? 'cash';
if (!in_array($pm, ['cash', 'bank', 'khata'], true)) $pm = 'cash';
$bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
$received = $pm === 'khata' ? 0.0 : (float)($_POST['received_amount'] ?? 0);
if ($pm === 'bank' && !$bank_account_id) back('Select the bank account for the bank transfer.');

// ---- items ----
$descs = $_POST['item_description'] ?? [];     // only used as a label fallback for non-serial lines
$pids  = $_POST['product_ids'] ?? [];
$sids  = $_POST['serial_ids'] ?? [];
$qtys  = $_POST['quantity'] ?? [];
$prices = $_POST['price'] ?? [];
$lines = [];
$seen = [];
foreach ($sids as $i => $sid_raw) {
    $sid = (int)$sid_raw;
    $pid = (int)($pids[$i] ?? 0);
    $price = round((float)($prices[$i] ?? 0), 2);
    if ($price <= 0) back('Every item needs a sale price greater than zero.');
    if ($sid) {
        if (isset($seen[$sid])) back('The same IMEI was added to the bill twice.');
        $seen[$sid] = true;
        $qty = 1;
    } else {
        if (!$pid) continue;
        $qty = max(1, (int)($qtys[$i] ?? 1));
    }
    $lines[] = ['serial_id' => $sid, 'product_id' => $pid, 'qty' => $qty, 'price' => $price];
}
if (!$lines) back('Add at least one item to the bill.');

$subtotal = 0.0;
foreach ($lines as $l) $subtotal = round($subtotal + round($l['qty'] * $l['price'], 2), 2);
$discount_in = round(max(0, (float)($_POST['discount_amount'] ?? 0)), 2);
// A discount equal to / above the bill would hand the phone over for free - reject it instead of silently clamping.
if ($discount_in >= $subtotal) back('Discount cannot be equal to or more than the bill amount (Rs. ' . number_format($subtotal, 2) . ').');
$discount = $discount_in;
$total = round($subtotal - $discount, 2);
$received = round(max(0, min($received, $total)), 2);
$due = round($total - $received, 2);
$status = $due <= 0 ? 'paid' : ($received > 0 ? 'partial' : 'pending');

// Khata needs an identifiable (registered) customer
if ($is_walkin && $due > 0) {
    back('Walk-in customers must pay in full. Register the customer first to sell on khata.');
}

try {
    $pdo->beginTransaction();

    if ($is_walkin) {
        $customer_id = (int)insert('customers', [
            'customer_no' => generateCustomerNo(), 'full_name' => $walk_name, 'phone' => $walk_phone, 'cnic' => $walk_cnic,
            'opening_due' => 0, 'opening_paid' => 0, 'branch_id' => $branch_id,
            'created_by' => $user_id, 'created_at' => date('Y-m-d'), 'updated_at' => date('Y-m-d'),
        ]);
    }

    $invoice_no = generateInvoiceNo();
    $sale_id = (int)insert('sales', [
        'invoice_no' => $invoice_no, 'customer_id' => $customer_id, 'sale_date' => $sale_date,
        'subtotal' => $subtotal, 'discount_amount' => $discount, 'total_amount' => $total,
        'paid_amount' => $received, 'due_amount' => $due,
        'payment_method' => $received > 0 ? ($pm === 'bank' ? 'bank' : 'cash') : null,
        'payment_status' => $status, 'status' => 'active', 'notes' => $notes ?: null,
        'branch_id' => $branch_id, 'created_by' => $user_id, 'created_at' => date('Y-m-d'), 'updated_at' => date('Y-m-d'),
    ]);

    foreach ($lines as $l) {
        if ($l['serial_id']) {
            // Lock the unit, read what it really is, then claim it atomically
            $u = $pdo->prepare("SELECT s.id, s.product_id, s.imei_number, s.serial_number, s.status, p.name, p.model_name, b.name AS brand
                                FROM product_serials s JOIN products p ON p.id = s.product_id LEFT JOIN brands b ON b.id = p.brand_id
                                WHERE s.id = ? FOR UPDATE");
            $u->execute([$l['serial_id']]);
            $unit = $u->fetch();
            if (!$unit) throw new RuntimeException('One of the selected units no longer exists.');
            if ($unit['status'] !== 'available') throw new RuntimeException("IMEI {$unit['imei_number']} is already {$unit['status']}.");
            $claim = $pdo->prepare("UPDATE product_serials SET status = 'sold', sale_id = ?, updated_at = CURDATE() WHERE id = ? AND status = 'available'");
            $claim->execute([$sale_id, $unit['id']]);
            if ($claim->rowCount() !== 1) throw new RuntimeException("IMEI {$unit['imei_number']} was just sold on another bill.");
            $product_id = (int)$unit['product_id'];
            $label = trim(($unit['brand'] ? $unit['brand'] . ' ' : '') . ($unit['model_name'] ?: $unit['name']));
            $desc = $label . ' [IMEI: ' . $unit['imei_number'] . ($unit['serial_number'] ? ' / ' . $unit['serial_number'] : '') . ']';
            $serial_id = (int)$unit['id'];
        } else {
            $p = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND status = 1");
            $p->execute([$l['product_id']]);
            $prod = $p->fetch();
            if (!$prod) throw new RuntimeException('One of the selected products is no longer available.');
            $product_id = (int)$prod['id'];
            $desc = $prod['name'];
            $serial_id = null;
        }

        // stock never goes negative
        $s = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
        $s->execute([$l['qty'], $product_id, $l['qty']]);
        if ($s->rowCount() !== 1) throw new RuntimeException("Insufficient stock for '$desc'.");

        insert('sale_items', [
            'sale_id' => $sale_id, 'product_id' => $product_id, 'serial_id' => $serial_id,
            'item_description' => $desc, 'quantity' => $l['qty'], 'price' => $l['price'],
            'subtotal' => round($l['qty'] * $l['price'], 2),
        ]);
    }

    if ($received > 0) {
        $method = $pm === 'bank' ? 'bank' : 'cash';
        $pay_id = (int)insert('payments', [
            'sale_id' => $sale_id, 'payment_date' => $sale_date, 'amount' => $received, 'payment_type' => 'sale',
            'payment_method' => $method, 'notes' => 'Payment for ' . $invoice_no, 'branch_id' => $branch_id,
            'received_by' => $user_id, 'created_at' => date('Y-m-d'),
        ]);
        if ($method === 'cash') {
            recordCashInflow($pdo, $sale_date, $received, 'Sale - ' . $invoice_no, 'payment', $pay_id, $user_id);
        } else {
            recordBankInflow($pdo, $sale_date, $received, 'Sale (bank transfer) - ' . $invoice_no, 'payment', $pay_id, $user_id, $bank_account_id);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('save_sale failed: ' . $e->getMessage());
    back($e instanceof RuntimeException ? $e->getMessage() : 'Could not save the sale. Nothing was saved - please try again.');
}

$_SESSION['success'] = "Sale saved. Invoice $invoice_no" . ($due > 0 ? ' - khata balance on this bill: Rs. ' . number_format($due, 2) : '');
header('Location: invoice_print.php?id=' . $sale_id);
exit;
