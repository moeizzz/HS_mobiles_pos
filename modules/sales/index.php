<?php
session_start();
$page_title = 'New Sale';
$base_url = '../../';
require_once '../../includes/functions.php';

$customers = getAll('customers', 'full_name ASC');
$all_products = $pdo->query("
    SELECT p.id, p.code, p.name, p.sale_price, p.purchase_price, p.stock_quantity, p.product_type
    FROM products p
    WHERE p.status = 1
      AND (p.stock_quantity > 0
           OR (p.product_type = 'mobile'
               AND EXISTS (SELECT 1 FROM product_serials s WHERE s.product_id = p.id AND s.status = 'available')))
    ORDER BY p.name
")->fetchAll();
$bank_accounts = getAll('bank_accounts', 'bank_name ASC, account_name ASC');

// Khata balance per customer (same formula as customers/view.php) for the live badge
$cust_bal = [];
foreach ($pdo->query("SELECT c.id, (c.opening_due - c.opening_paid
        + COALESCE((SELECT SUM(s.total_amount) FROM sales s WHERE s.customer_id = c.id AND s.status <> 'cancelled'), 0)
        - COALESCE((SELECT SUM(r.amount) FROM sale_returns r WHERE r.customer_id = c.id), 0)
        - COALESCE((SELECT SUM(p.amount) FROM payments p JOIN sales s2 ON s2.id = p.sale_id WHERE s2.customer_id = c.id AND s2.status <> 'cancelled'), 0)) AS bal
    FROM customers c") as $cb) {
    $cust_bal[$cb['id']] = round((float)$cb['bal'], 2);
}

$mobile_serials = $pdo->query("SELECT s.id, s.product_id, s.imei_number, s.serial_number AS imei2, s.notes, s.purchase_price AS cost, s.pta_status AS pta FROM product_serials s JOIN products p ON p.id = s.product_id WHERE p.product_type = 'mobile' AND s.status = 'available' ORDER BY s.product_id, s.imei_number")->fetchAll();
$serials_by_product = [];
foreach ($mobile_serials as $ms) {
    $serials_by_product[$ms['product_id']][] = $ms;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---------- Validate branch_id (fix FK error) ----------
    $branch_id = $_SESSION['branch_id'] ?? null;
    if ($branch_id !== null) {
        $check = $pdo->prepare("SELECT id FROM branches WHERE id = ?");
        $check->execute([$branch_id]);
        if (!$check->fetch()) {
            $branch_id = null;   // fallback to NULL – allowed because FK is ON DELETE SET NULL
        }
    }
    // -------------------------------------------------------

    $customer_id_raw = $_POST['customer_id'] ?? '';
    $sale_date = $_POST['sale_date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sale_date)) $sale_date = date('Y-m-d');

    $is_walkin = ($customer_id_raw === 'walkin');
    $customer_id = $is_walkin ? 0 : (int)$customer_id_raw;
    if ($is_walkin) {
        $walk_name = trim($_POST['walkin_name'] ?? '');
        $walk_phone = trim($_POST['walkin_phone'] ?? '');
        $walk_cnic = trim($_POST['walkin_cnic'] ?? '') ?: null;
        $walk_address = trim($_POST['walkin_address'] ?? '');
        if (!$walk_name) {
            $_SESSION['error'] = 'Enter the walk-in customer name.';
            header("Location: index.php"); exit;
        }
        if ($walk_cnic) {
            $chk = $pdo->prepare("SELECT id, full_name FROM customers WHERE cnic = ?");
            $chk->execute([$walk_cnic]);
            if ($dup = $chk->fetch()) {
                $_SESSION['error'] = "This CNIC already belongs to customer: {$dup['full_name']}";
                header("Location: index.php"); exit;
            }
        }
    }

    $descriptions = $_POST['item_description'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    $received_amount = (float)($_POST['received_amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    if (!in_array($payment_method, ['cash', 'bank'], true)) $payment_method = 'cash';
    $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);

    if ((!$is_walkin && empty($customer_id)) || empty($descriptions)) {
        $_SESSION['error'] = 'Please select a customer and add at least one item.';
        header("Location: index.php"); exit;
    }
    if ($payment_method === 'bank' && $received_amount > 0 && !$bank_account_id) {
        $_SESSION['error'] = 'Select the bank account for the bank payment.';
        header("Location: index.php"); exit;
    }

    $product_ids = $_POST['product_ids'] ?? [];
    $serial_ids = $_POST['serial_ids'] ?? [];
    $total_amount = 0;
    $items = [];
    $seen_serials = [];
    foreach ($descriptions as $i => $desc) {
        $desc = trim($desc);
        if ($desc === '') continue;
        $qty = max(1, (int)($quantities[$i] ?? 1));
        $price = round(max(0, (float)($prices[$i] ?? 0)), 2);
        if ($price <= 0) {
            $_SESSION['error'] = "Price must be greater than zero for item: $desc";
            header("Location: index.php"); exit;
        }
        $pid = !empty($product_ids[$i]) ? (int)$product_ids[$i] : null;
        $sid = !empty($serial_ids[$i]) ? (int)$serial_ids[$i] : 0;
        if ($sid) {
            if (isset($seen_serials[$sid])) {
                $_SESSION['error'] = 'The same IMEI/serial was added twice in this sale.';
                header("Location: index.php"); exit;
            }
            $seen_serials[$sid] = true;
            $qty = 1;   // a serial/IMEI is exactly one unit
        }
        $line_total = round($qty * $price, 2);
        $total_amount = round($total_amount + $line_total, 2);
        if (!$pid) {
            $resolved = resolveProductFromDescription($pdo, $desc);
            if ($resolved) $pid = $resolved;
        }
        $items[] = ['description' => $desc, 'quantity' => $qty, 'price' => $price, 'subtotal' => $line_total, 'product_id' => $pid, 'serial_id' => $sid];
    }

    if (empty($items)) {
        $_SESSION['error'] = 'Please add at least one valid item.';
        header("Location: index.php"); exit;
    }

    // Discount: clamped to 0..subtotal; total = subtotal - discount
    $subtotal_amount = $total_amount;
    $discount_amount = round(min(max(0, (float)($_POST['discount_amount'] ?? 0)), $subtotal_amount), 2);
    $total_amount = round($subtotal_amount - $discount_amount, 2);
    $received_amount = round(max(0, min($received_amount, $total_amount)), 2);
    $due_amount = round($total_amount - $received_amount, 2);
    $payment_status = $due_amount <= 0 ? 'paid' : ($received_amount > 0 ? 'partial' : 'pending');
    $user_id = $_SESSION['user_id'] ?? null;

    // ---- All writes below are atomic: any failure rolls everything back ----
    try {
        $pdo->beginTransaction();

        if ($is_walkin) {
            $customer_id = insert('customers', [
                'customer_no' => generateCustomerNo(),
                'full_name'   => $walk_name,
                'phone'       => $walk_phone,
                'cnic'        => $walk_cnic,
                'address'     => $walk_address ?: null,
                'opening_due' => 0,
                'opening_paid' => 0,
                'branch_id'   => $branch_id,
                'created_by'  => $user_id,
                'created_at'  => date('Y-m-d'),
                'updated_at'  => date('Y-m-d'),
            ]);
        }

        $invoice_no = generateInvoiceNo();
        $sale_id = insert('sales', [
            'invoice_no' => $invoice_no,
            'customer_id' => $customer_id,
            'sale_date' => $sale_date,
            'subtotal' => $subtotal_amount,
            'discount_amount' => $discount_amount,
            'total_amount' => $total_amount,
            'paid_amount' => $received_amount,
            'due_amount' => $due_amount,
            'payment_method' => $received_amount > 0 ? $payment_method : null,
            'payment_status' => $payment_status,
            'status' => 'active',
            'notes' => $notes ?: null,
            'branch_id' => $branch_id,
            'created_by' => $user_id,
            'created_at' => date('Y-m-d'),
            'updated_at' => date('Y-m-d'),
        ]);

        foreach ($items as $item) {
            // 1) Serial/IMEI: must belong to this product and still be available (atomic claim)
            if ($item['serial_id']) {
                $u = $pdo->prepare("UPDATE product_serials SET status = 'sold', sale_id = ?, updated_at = CURDATE() WHERE id = ? AND status = 'available' AND (? IS NULL OR product_id = ?)");
                $u->execute([$sale_id, $item['serial_id'], $item['product_id'], $item['product_id']]);
                if ($u->rowCount() !== 1) {
                    throw new RuntimeException("IMEI/serial for '{$item['description']}' is no longer available (already sold or invalid).");
                }
            }
            // 2) Stock: never sell more than we have
            if ($item['product_id']) {
                $u = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
                $u->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                if ($u->rowCount() !== 1) {
                    throw new RuntimeException("Insufficient stock for '{$item['description']}'.");
                }
            }
            insert('sale_items', [
                'sale_id' => $sale_id,
                'product_id' => $item['product_id'],
                'serial_id' => $item['serial_id'] ?: null,
                'item_description' => $item['description'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
            ]);
        }

        if ($received_amount > 0) {
            $pay_id = insert('payments', [
                'sale_id' => $sale_id,
                'payment_date' => $sale_date,
                'amount' => $received_amount,
                'payment_type' => 'sale',
                'payment_method' => $payment_method,
                'notes' => 'Payment for ' . $invoice_no,
                'branch_id' => $branch_id,
                'received_by' => $user_id,
                'created_at' => date('Y-m-d'),
            ]);
            if ($payment_method === 'cash') {
                recordCashInflow($pdo, $sale_date, $received_amount, 'Sale - ' . $invoice_no, 'payment', $pay_id, $user_id);
            } else {
                recordBankInflow($pdo, $sale_date, $received_amount, 'Sale (bank) - ' . $invoice_no, 'payment', $pay_id, $user_id, $bank_account_id);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Sale failed: ' . $e->getMessage());
        $_SESSION['error'] = $e instanceof RuntimeException ? $e->getMessage() : 'Could not save the sale. Nothing was saved - please try again.';
        header("Location: index.php"); exit;
    }

    $msg = "Sale created successfully. Invoice #$invoice_no";
    if ($due_amount > 0) $msg .= " (Balance due: " . formatCurrency($due_amount) . ")";
    $_SESSION['success'] = $msg;
    header("Location: invoices.php");
    exit;
}

$hide_page_heading = true;
$body_class = 'pos-page';
require_once '../../includes/header.php';
$is_admin = (($_SESSION['user_role'] ?? '') === 'admin');
?>

<style>
/* POS is a full-width billing screen: hide the sidebar, keep the top bar (F-key shortcuts, search) */
#wrapper { padding-left: 0 !important; }
.sidebar { display: none !important; }
#content-wrapper { margin-left: 0 !important; width: 100% !important; }
.content { padding-top: 1rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0 font-weight-bold"><i class="fas fa-cash-register text-primary"></i> New Sale
    <small class="text-muted font-weight-normal ml-2">Scan an IMEI and press Enter to add it to the cart</small></h5>
  <div>
    <a href="../../index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-home"></i> Dashboard</a>
    <a href="invoices.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-invoice"></i> Invoices</a>
  </div>
</div>

<form method="post" id="saleForm" autocomplete="off">
<div class="pos-wrap">

  <!-- ================= LEFT 65%: scanner + cart ================= -->
  <div class="pos-left">

    <div class="card">
      <div class="card-body">
        <div class="scan-bar">
          <i class="fas fa-barcode"></i>
          <input type="text" id="imeiScan" class="form-control scan-input" placeholder="Scan IMEI or type model / accessory name, then Enter" autofocus aria-label="IMEI scanner">
          <div class="scan-results" id="scanResults"></div>
        </div>
        <div class="small text-muted mt-2"><i class="fas fa-info-circle"></i> Enter adds the first match. Results show <b>Brand + Model + Storage + PTA + IMEI + Price</b>. Only in-stock units are listed.</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h6 class="m-0"><i class="fas fa-shopping-basket"></i> Cart <span class="badge badge-primary ml-1" id="cartCount">0</span></h6>
        <small class="text-muted">Qty / price are editable. Rows below cost turn red.</small>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height:46vh;">
          <table class="table table-sm cart-table mb-0" id="cartTable">
            <thead>
              <tr>
                <th>Item</th>
                <th style="width:70px;" class="text-center">Qty</th>
                <th style="width:120px;" class="text-right">Price</th>
                <th style="width:120px;" class="text-right">Amount</th>
                <th style="width:34px;"></th>
              </tr>
            </thead>
            <tbody id="cartBody"></tbody>
          </table>
        </div>
        <div class="empty-state" id="emptyCart">
          <svg viewBox="0 0 96 96" fill="none" stroke="#cbd5e1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 20h10l8 38h36l8-28H28"/><circle cx="38" cy="72" r="5"/><circle cx="64" cy="72" r="5"/></svg>
          <div class="t">Cart is empty</div>
          <div class="small">Scan an IMEI to start the bill</div>
        </div>
      </div>
    </div>

    <!-- Manual / accessory entry (original fields, ids unchanged) -->
    <div class="card">
      <div class="card-header py-2 d-flex justify-content-between align-items-center" role="button" data-toggle="collapse" data-target="#manualEntry" aria-expanded="false">
        <h6 class="m-0"><i class="fas fa-keyboard"></i> Manual / accessory entry</h6>
        <i class="fas fa-chevron-down text-muted"></i>
      </div>
      <div class="collapse" id="manualEntry">
        <div class="card-body">
          <div class="form-group mb-2">
            <label class="small">Description <span class="text-danger">*</span></label>
            <input type="text" id="entryDesc" class="form-control" list="productList" placeholder="Type or select product...">
            <input type="hidden" id="entryProductId" value="0">
            <datalist id="productList">
              <?php foreach ($all_products as $p): ?>
              <option value="<?= htmlspecialchars($p['name'].' ('.$p['code'].')') ?>" data-price="<?= $p['sale_price'] ?>" data-cost="<?= $p['purchase_price'] ?>" data-product-id="<?= $p['id'] ?>" data-stock="<?= (int)$p['stock_quantity'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-mobile="<?= ($p['product_type'] ?? 'general') === 'mobile' ? 1 : 0 ?>"><?= htmlspecialchars($p['name'].' - '.$p['code'])?></option>
              <?php endforeach; ?>
            </datalist>
            <small class="text-muted" id="stockInfo" style="display:none;"></small>
          </div>
          <div class="form-group mb-2" id="serialGroup" style="display:none;">
            <label class="small">Mobile (IMEI) <span class="text-danger">*</span></label>
            <select id="entrySerial" class="form-control"><option value="">Select IMEI / Unit</option></select>
            <small class="text-muted" id="serialInfo"></small>
          </div>
          <div class="form-row">
            <div class="col-6 form-group mb-2"><label class="small">Qty</label><input type="number" id="entryQty" class="form-control" value="1" min="1"></div>
            <div class="col-6 form-group mb-2"><label class="small">Unit Price</label><input type="number" id="entryPrice" class="form-control" step="0.01" min="0" placeholder="Enter price"></div>
          </div>
          <div id="entryPreview" class="small text-muted" style="display:none;">Line amount: <b class="mono" id="entryAmountPreview">0.00</b></div>
          <button type="button" id="addToCartBtn" class="btn btn-primary btn-sm mt-2"><i class="fas fa-cart-plus"></i> Add to Cart</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ================= RIGHT 35%: sticky checkout ================= -->
  <div class="pos-checkout">
    <div class="card">
      <div class="card-body">

        <!-- customer -->
        <div class="form-group mb-2">
          <label class="small d-flex justify-content-between"><span>Customer <span class="text-danger">*</span></span><span id="khataBadge"></span></label>
          <select name="customer_id" id="customerSelect" class="form-control" required onchange="toggleWalkin(this.value)">
            <option value="">Select Customer</option>
            <option value="walkin">+ Walk-in Customer (New)</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <div id="walkinFields" style="display:none;" class="mt-2">
            <input type="text" name="walkin_name" class="form-control mb-2" placeholder="Customer Name *">
            <div class="form-row">
              <div class="col-6"><input type="text" name="walkin_phone" class="form-control mb-2" placeholder="Mobile Number"></div>
              <div class="col-6"><input type="text" name="walkin_cnic" class="form-control mb-2 cnic" placeholder="CNIC" maxlength="15"></div>
            </div>
            <input type="text" name="walkin_address" class="form-control" placeholder="Address">
          </div>
        </div>
        <div class="form-row">
          <div class="col-6 form-group mb-2"><label class="small">Date</label><input type="text" name="sale_date" class="form-control datepicker" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-6 form-group mb-2"><label class="small">Notes</label><input type="text" name="notes" class="form-control" placeholder="Optional"></div>
        </div>

        <hr class="my-2">

        <!-- totals -->
        <div class="sum-row"><span class="text-muted">Subtotal</span><span class="v" id="subtotalDisplay">0.00</span></div>
        <div class="sum-row">
          <span class="text-muted">Discount</span>
          <input type="number" name="discount_amount" id="discountAmount" class="form-control form-control-sm text-right mono" style="width:130px;" step="0.01" min="0" value="0">
        </div>
        <div class="total-box my-2">
          <div class="lbl">Grand Total (PKR)</div>
          <div class="big" id="totalDisplay">0.00</div>
        </div>

        <!-- payment -->
        <div class="form-group mb-2">
          <label class="small">Cash Received</label>
          <input type="number" name="received_amount" id="receivedAmount" class="form-control form-control-lg font-weight-bold mono" step="0.01" min="0" value="0" placeholder="0.00 = full khata">
        </div>
        <div class="quick-cash mb-2">
          <button type="button" class="btn btn-outline-success" id="qcExact"><i class="fas fa-equals"></i> Exact Cash</button>
          <button type="button" class="btn btn-outline-secondary" id="qcRound"><i class="fas fa-cut"></i> Round Off</button>
          <button type="button" class="btn btn-outline-danger" id="qcKhata"><i class="fas fa-book"></i> Full Khata</button>
        </div>
        <div class="form-group mb-2">
          <label class="small">Payment Method</label>
          <select name="payment_method" id="paymentMethod" class="form-control">
            <option value="cash">Cash</option>
            <option value="bank">Bank</option>
          </select>
        </div>
        <div class="form-group mb-2" id="bankAccountRow" style="display:none;">
          <label class="small">Bank Account</label>
          <select name="bank_account_id" class="form-control">
            <option value="">Select Account</option>
            <?php foreach ($bank_accounts as $ba): ?>
              <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['bank_name'] . ' - ' . $ba['account_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="balanceBox" class="p-2 rounded" style="background:#f8fafc;border:1px solid var(--line);">
          <div class="sum-row"><span class="font-weight-bold">Balance Due (Khata)</span><span class="v" style="font-size:1.15rem;" id="dueDisplay">0.00</span></div>
          <div class="sum-row small"><span class="text-muted">Status</span><span class="font-weight-bold" id="statusDisplay">Full Payment (Paid)</span></div>
          <div class="sum-row small"><span class="text-muted">Received</span><span class="v text-success" id="receivedDisplay">0.00</span></div>
        </div>

        <?php if ($is_admin): ?>
        <div class="margin-box mt-2" id="marginBox">
          <div class="d-flex justify-content-between"><span><i class="fas fa-chart-line"></i> Profit <small class="text-muted">(admin)</small></span><span class="v" id="profitValue">-</span></div>
          <div class="d-flex justify-content-between small text-muted"><span id="marginNote">Margin</span><span class="mono" id="marginPct">-</span></div>
        </div>
        <?php endif; ?>

        <button type="submit" id="submitSale" class="btn btn-primary btn-block btn-lg mt-3" disabled><i class="fas fa-file-invoice"></i> Generate Invoice</button>
        <small class="text-muted d-block text-center mt-1" id="submitHint">Add items to the cart first</small>
      </div>
    </div>
  </div>

</div>
</form>

<script>
var SERIALS_BY_PRODUCT = <?= json_encode($serials_by_product, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
var CUST_BAL = <?= json_encode($cust_bal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
function toggleWalkin(val) {
    $('#walkinFields').toggle(val === 'walkin');
    $('#khataBadge').empty();
    if (val && val !== 'walkin') {
        var b = parseFloat(CUST_BAL[val]) || 0;
        $('#khataBadge').html(b > 0
            ? '<span class="khata-badge due"><i class="fas fa-book"></i> Khata ' + PosUI.money(b) + '</span>'
            : '<span class="khata-badge clear"><i class="fas fa-check"></i> Clear</span>');
    }
}
$(document).ready(function () {
    var esc = PosUI.esc, money = PosUI.money, toast = PosUI.toast;
    var IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;

    /* ---------- cart core ---------- */
    function n(v) { return parseFloat(v) || 0; }
    function f2(v) { return (Math.round(v * 100) / 100).toFixed(2); }

    function addItem(o) {
        if (o.serialId && $('#cartBody input[name="serial_ids[]"]').filter(function () { return this.value == o.serialId; }).length) {
            toast('This IMEI is already in the cart', 'warn'); return false;
        }
        if (!(o.price > 0)) { toast('Enter a price greater than zero', 'warn'); return false; }
        var qty = o.serialId ? 1 : (o.qty || 1);
        var $tr = $('<tr class="item-row">').attr('data-cost', o.cost || 0);
        var $d = $('<td>');
        $d.append($('<input type="hidden" name="item_description[]">').val(o.desc),
                  $('<input type="hidden" name="product_ids[]">').val(o.productId || 0),
                  $('<input type="hidden" name="serial_ids[]">').val(o.serialId || 0));
        $d.append($('<div class="font-weight-bold">').text(o.title || o.desc));
        if (o.metaHtml) $d.append($('<div class="small text-muted">').html(o.metaHtml));
        if (o.stock !== undefined && qty > o.stock && !o.serialId) $d.append('<div class="small text-danger">Only ' + o.stock + ' in stock</div>');
        $tr.append($d);
        $tr.append($('<td class="text-center p-1">').append($('<input type="number" name="quantity[]" class="form-control form-control-sm qty-input text-center">').attr({ min: 1 }).prop('readonly', !!o.serialId).val(qty)));
        $tr.append($('<td class="text-right p-1">').append($('<input type="number" name="price[]" class="form-control form-control-sm price-input text-right mono" step="0.01" min="0">').val(f2(o.price))));
        $tr.append($('<td class="text-right line-total align-middle">').text(f2(qty * o.price)));
        $tr.append('<td class="text-center align-middle p-1"><span class="remove-item" title="Remove"><i class="fas fa-times"></i></span></td>');
        $('#cartBody').append($tr);
        flagBelowCost($tr);
        recalc();
        return true;
    }

    function flagBelowCost($tr) {
        var cost = n($tr.attr('data-cost')), price = n($tr.find('.price-input').val());
        var below = cost > 0 && price > 0 && price < cost;
        $tr.toggleClass('table-danger', below);
        $tr.find('.below-cost').remove();
        if (below) $tr.find('td:first').append('<div class="small text-danger below-cost"><i class="fas fa-exclamation-triangle"></i> Below cost - loss of ' + money((cost - price) * n($tr.find('.qty-input').val())) + '</div>');
        return below;
    }

    function recalc() {
        var sub = 0, cost = 0, cnt = 0, unknown = false;
        $('#cartBody .item-row').each(function () {
            var q = n($(this).find('.qty-input').val()), p = n($(this).find('.price-input').val()), c = n($(this).attr('data-cost'));
            var line = q * p;
            $(this).find('.line-total').text(f2(line));
            sub += line; cnt++;
            if (c > 0) cost += c * q; else unknown = true;
        });
        var disc = Math.min(Math.max(0, n($('#discountAmount').val())), sub);
        var total = Math.max(0, sub - disc);
        $('#subtotalDisplay').text(f2(sub));
        $('#totalDisplay').text(f2(total));
        $('#cartCount').text(cnt);
        $('#emptyCart').toggle(cnt === 0);
        $('#submitSale').prop('disabled', cnt === 0);
        $('#submitHint').toggle(cnt === 0);

        var rec = Math.max(0, n($('#receivedAmount').val()));
        if (rec > total) { rec = total; $('#receivedAmount').val(f2(rec)); }
        var due = Math.max(0, total - rec);
        $('#dueDisplay').text(f2(due)).css('color', due > 0 ? 'var(--danger)' : 'var(--success)');
        $('#receivedDisplay').text(f2(rec));
        if (due <= 0) $('#statusDisplay').text('Full Payment (Paid)').attr('class', 'font-weight-bold text-success');
        else if (rec > 0) $('#statusDisplay').text('Partial Payment').attr('class', 'font-weight-bold text-warning');
        else $('#statusDisplay').text('Credit Sale (Khata)').attr('class', 'font-weight-bold text-danger');

        if (IS_ADMIN) {
            var $mb = $('#marginBox');
            if (cnt === 0) { $('#profitValue').text('-'); $('#marginPct').text('-'); $mb.removeClass('loss'); }
            else {
                var profit = total - cost, pct = total > 0 ? profit / total * 100 : 0;
                $('#profitValue').text((profit >= 0 ? '+' : '-') + money(Math.abs(profit)));
                $('#marginPct').text(pct.toFixed(1) + '%');
                $('#marginNote').text(unknown ? 'Margin (some items have no cost)' : 'Margin');
                $mb.toggleClass('loss', profit < 0);
            }
        }
    }

    $(document).on('input', '.qty-input, .price-input', function () { flagBelowCost($(this).closest('.item-row')); recalc(); });
    $(document).on('click', '.remove-item', function () { $(this).closest('.item-row').remove(); recalc(); $('#imeiScan').focus(); });
    $('#discountAmount, #receivedAmount').on('input', recalc);
    $('#paymentMethod').on('change', function () { $('#bankAccountRow').toggle($(this).val() === 'bank'); });

    /* ---------- quick cash buttons ---------- */
    $('#qcExact').on('click', function () { $('#receivedAmount').val($('#totalDisplay').text()); recalc(); });
    $('#qcKhata').on('click', function () { $('#receivedAmount').val('0'); recalc(); });
    $('#qcRound').on('click', function () {
        var total = n($('#totalDisplay').text()), drop = total - Math.floor(total / 10) * 10;
        if (drop > 0) { $('#discountAmount').val(f2(n($('#discountAmount').val()) + drop)); toast('Rounded off by ' + money(drop)); }
        recalc();
    });

    /* ---------- scanner / search ---------- */
    var $scan = $('#imeiScan'), $res = $('#scanResults'), results = [], reqId = 0;

    function serialToItem(s) {
        var title = s.name + (s.storage ? ' ' + s.storage : '');
        var imeiTxt = 'IMEI: ' + s.imei + (s.imei2 ? ' / ' + s.imei2 : '');
        return { kind: 'serial', desc: s.name + ' [' + imeiTxt + ']', title: title, productId: s.product_id, serialId: s.id, price: n(s.price), cost: n(s.cost),
                 metaHtml: '<span class="imei">' + esc(s.imei) + '</span> ' + PosUI.pta(s.pta) + (s.cond ? ' <span class="text-muted">' + esc(s.cond) + '</span>' : '') +
                           (s.warranty ? ' <span class="text-muted">&middot; ' + esc(s.warranty) + '</span>' : '') };
    }
    function productToItem(p) {
        return { kind: 'product', desc: p.name, title: p.name, productId: p.id, serialId: 0, price: n(p.price), cost: n(p.cost), qty: 1, stock: parseInt(p.stock) || 0,
                 metaHtml: '<span class="text-muted">' + esc(p.code) + ' &middot; Stock ' + (parseInt(p.stock) || 0) + '</span>' };
    }
    function renderResults(d) {
        results = [];
        var h = '';
        (d.serials || []).forEach(function (s) {
            var it = serialToItem(s); results.push(it);
            h += '<div class="sr-item" data-i="' + (results.length - 1) + '"><div><div class="sr-title">' + esc(s.name) + '</div>' +
                 '<div class="sr-meta"><span>' + esc([s.storage, s.ram, s.color].filter(Boolean).join(' / ')) + '</span>' + PosUI.pta(s.pta) + '<span class="imei">' + esc(s.imei) + '</span></div></div>' +
                 '<div class="sr-price">' + money(s.price) + '</div></div>';
        });
        (d.products || []).forEach(function (p) {
            var it = productToItem(p); results.push(it);
            h += '<div class="sr-item" data-i="' + (results.length - 1) + '"><div><div class="sr-title">' + esc(p.name) + '</div>' +
                 '<div class="sr-meta"><span>' + esc(p.code) + '</span><span>Stock ' + (parseInt(p.stock) || 0) + '</span></div></div><div class="sr-price">' + money(p.price) + '</div></div>';
        });
        $res.html(h || '<div class="sr-empty">No in-stock item matches</div>').addClass('show');
    }
    var search = PosUI.debounce(function () {
        var q = $.trim($scan.val());
        if (q.length < 2) { $res.removeClass('show'); results = []; return; }
        var my = ++reqId;
        $.getJSON(PosUI.searchUrl, { q: q, scope: 'pos' }, function (d) { if (my === reqId) renderResults(d); });
    }, 200);
    $scan.on('input', search);

    function pick(it) {
        if (!it) return;
        if (addItem(it)) { toast('Added: ' + it.title); }
        $scan.val('').focus(); $res.removeClass('show'); results = [];
    }
    $res.on('click', '.sr-item', function () { pick(results[$(this).data('i')]); });
    $(document).on('click', function (e) { if (!$(e.target).closest('.scan-bar').length) $res.removeClass('show'); });

    $scan.on('keydown', function (e) {
        if (e.key === 'Escape') { $res.removeClass('show'); return; }
        if (e.key !== 'Enter') return;
        e.preventDefault();                      // scanner Enter must never submit the form
        var v = $.trim($scan.val());
        if (/^\d{15}$/.test(v)) {                // exact IMEI from a scanner
            $.getJSON(PosUI.searchUrl, { imei: v }, function (d) {
                var s = d.serial;
                if (!s) { toast('IMEI not found in stock', 'warn'); $scan.select(); return; }
                if (s.status !== 'available') { toast('This IMEI is already ' + s.status, 'warn'); $scan.select(); return; }
                pick(serialToItem(s));
            });
        } else if (results.length) {
            pick(results[0]);
        } else if (v) {
            toast('No match - wait for results or check the IMEI', 'warn');
        }
    });

    /* ---------- manual / accessory entry (original behaviour, same ids) ---------- */
    function updateEntryPreview() {
        var desc = $('#entryDesc').val().trim(), qty = n($('#entryQty').val()) || 1, price = n($('#entryPrice').val());
        if (desc && price > 0) { $('#entryPreview').show(); $('#entryAmountPreview').text(f2(qty * price)); } else { $('#entryPreview').hide(); }
    }
    function resetSerials() { $('#entrySerial').find('option:not(:first)').remove(); $('#serialInfo').text('').hide(); $('#serialGroup').hide(); }
    function populateSerials(productId, isMobile) {
        var $sel = $('#entrySerial'); $sel.find('option:not(:first)').remove(); $('#serialInfo').text('').hide();
        if (!isMobile) { $('#serialGroup').hide(); return; }
        var list = SERIALS_BY_PRODUCT[productId] || [];
        if (!list.length) { $sel.append('<option value="">No IMEI / unit available</option>'); $('#serialInfo').text('No available IMEI for this mobile (check purchase record).').show(); }
        else list.forEach(function (s) { $sel.append($('<option>').val(s.id).text((s.imei_number ? 'IMEI: ' + s.imei_number : '') + (s.imei2 ? ' / ' + s.imei2 : '') + (s.notes ? ' - ' + s.notes : ''))); });
        $('#serialGroup').show();
    }
    $('#entryDesc, #entryQty, #entryPrice').on('input', updateEntryPreview);
    $('#entryDesc').on('input', function () {
        var val = $(this).val(), matched = false;
        $('#productList option').each(function () {
            var lv = (val || '').toLowerCase().trim();
            if ($(this).attr('value') && ($(this).attr('value') === val || String($(this).data('name') || '').toLowerCase() === lv)) {
                var pr = $(this).data('price'), stock = $(this).data('stock');
                if (pr) { $('#entryPrice').val(pr); updateEntryPreview(); }
                $('#entryProductId').val($(this).data('product-id') || 0);
                $('#stockInfo').text(stock > 0 ? 'In Stock: ' + stock : 'Out of Stock!').attr('class', stock > 0 ? 'text-muted' : 'text-danger').show();
                populateSerials($(this).data('product-id'), parseInt($(this).data('mobile')) === 1);
                matched = true; return false;
            }
        });
        if (!matched) { $('#entryProductId').val(0); $('#stockInfo').hide(); resetSerials(); if (!val) { $('#entryPrice').val(''); updateEntryPreview(); } }
    });
    function addManual() {
        var desc = $('#entryDesc').val().trim();
        if (!desc) { toast('Enter a description first', 'warn'); return; }
        var qty = parseInt($('#entryQty').val()) || 1, price = n($('#entryPrice').val());
        var pid = parseInt($('#entryProductId').val()) || 0, isMobile = $('#serialGroup').is(':visible');
        var sid = isMobile ? (parseInt($('#entrySerial').val()) || 0) : 0, cost = 0, stock;
        if (isMobile && !sid) { toast('Select an IMEI / unit for the mobile', 'warn'); return; }
        $('#productList option').each(function () { if ($(this).data('product-id') == pid) { stock = parseInt($(this).data('stock')) || 0; cost = n($(this).data('cost')); return false; } });
        var meta = '';
        if (isMobile) {
            var sn = null; (SERIALS_BY_PRODUCT[pid] || []).forEach(function (s) { if (String(s.id) === String(sid)) sn = s; });
            if (sn) { desc += ' [IMEI: ' + sn.imei_number + (sn.imei2 ? ' / ' + sn.imei2 : '') + ']'; cost = n(sn.cost) || cost; meta = '<span class="imei">' + esc(sn.imei_number) + '</span> ' + PosUI.pta(sn.pta); }
        }
        if (addItem({ desc: desc, title: desc.replace(/\s*\[IMEI.*$/, ''), productId: pid, serialId: sid, price: price, cost: cost, qty: qty, stock: stock, metaHtml: meta })) {
            $('#entryDesc, #entryPrice').val(''); $('#entryQty').val(1); $('#entryProductId').val(0); $('#stockInfo, #entryPreview').hide(); resetSerials(); $('#imeiScan').focus();
        }
    }
    $('#addToCartBtn').on('click', addManual);

    /* ---------- form guards ---------- */
    // Enter never submits the sale (scanner-safe); only the Generate Invoice button does.
    $('#saleForm').on('keydown', 'input', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        if (this.id === 'entryDesc' || this.id === 'entryQty' || this.id === 'entryPrice') addManual();
    });
    $('#saleForm').on('submit', function (e) {
        if ($('#cartBody .item-row').length === 0) { e.preventDefault(); toast('Cart is empty', 'warn'); return; }
        if (!$('#customerSelect').val()) { e.preventDefault(); toast('Select a customer', 'warn'); $('#customerSelect').focus(); return; }
        if ($('#customerSelect').val() === 'walkin' && $.trim($('#walkinFields input[name="walkin_name"]').val()) === '') {
            e.preventDefault(); toast('Walk-in customer name is required', 'warn'); return;
        }
        var below = $('#cartBody .item-row.table-danger').length;
        if (below && !confirm(below + ' item(s) are priced BELOW cost. Continue?')) { e.preventDefault(); return; }
        $('#submitSale').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');   // no double invoice
    });

    recalc();
    $scan.focus();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
