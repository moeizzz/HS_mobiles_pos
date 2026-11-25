<?php
session_start();
$page_title = 'Purchases';
$base_url = '../../';
require_once '../../includes/functions.php';

// Handle delete (safe: blocked when units were sold; reverses payments; all-or-nothing)
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        $pu = $pdo->prepare("SELECT * FROM purchases WHERE id = ? FOR UPDATE");
        $pu->execute([$pid]);
        $purchase_row = $pu->fetch();
        if (!$purchase_row) throw new RuntimeException('Purchase not found.');

        // Sold / returned units cannot be removed - it would erase the sale's IMEI history
        $sold = $pdo->prepare("SELECT COUNT(*) FROM product_serials WHERE purchase_id = ? AND status <> 'available'");
        $sold->execute([$pid]);
        if ($sold->fetchColumn() > 0) throw new RuntimeException('Cannot delete: some units of this purchase were already sold/returned.');

        // Stock must still cover what this purchase added (general items too)
        $li = $pdo->prepare("SELECT pi.product_id, SUM(pi.quantity) q, p.stock_quantity, p.name FROM purchase_items pi JOIN products p ON p.id = pi.product_id WHERE pi.purchase_id = ? GROUP BY pi.product_id");
        $li->execute([$pid]);
        foreach ($li->fetchAll() as $row) {
            if ((int)$row['stock_quantity'] < (int)$row['q']) {
                throw new RuntimeException("Cannot delete: '{$row['name']}' stock is lower than this purchase quantity (some units were sold).");
            }
        }

        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - COALESCE((SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = ? AND product_id = products.id), 0) WHERE id IN (SELECT product_id FROM purchase_items WHERE purchase_id = ?)")->execute([$pid, $pid]);
        // cash/bank/supplier-payment entries made for this purchase
        reversePurchaseOutflows($pdo, $pid, $purchase_row['supplier_id'], $purchase_row['invoice_no'], $purchase_row['purchase_date']);
        $pdo->prepare("DELETE FROM product_serials WHERE purchase_id = ?")->execute([$pid]);
        $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$pid]);
        $pdo->prepare("DELETE FROM purchases WHERE id = ?")->execute([$pid]);
        $pdo->commit();
        $_SESSION['success'] = 'Purchase deleted. Stock and payments reversed.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Purchase delete failed: ' . $e->getMessage());
        $_SESSION['error'] = $e instanceof RuntimeException ? $e->getMessage() : 'Delete failed. Nothing was changed.';
    }
    header("Location: view_purchase.php");
    exit;
}

$suppliers = getAll('suppliers', 'name ASC');
$bank_accounts = getAll('bank_accounts', 'bank_name ASC, account_name ASC');
$brands = getAll('brands', 'name ASC');
$general_categories = array_values(array_filter(getAll('categories', 'name ASC'), function ($c) {
    return ($c['product_type'] ?? '') === 'general';
}));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_date = $_POST['purchase_date'];
    $supplier_type = $_POST['supplier_type'] ?? 'supplier';
    $supplier_id   = (int)($_POST['supplier_id'] ?? 0);
    $person_name   = trim($_POST['person_name'] ?? '');
    $person_phone  = trim($_POST['person_phone'] ?? '');
    $person_cnic   = trim($_POST['person_cnic'] ?? '');
    $person_address = trim($_POST['person_address'] ?? '');
    $invoice_no    = trim($_POST['invoice_no'] ?? '') ?: null;
    $product_id    = (int)($_POST['product_id'] ?? 0);
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $product_type  = $_POST['product_type'] ?? 'general';
    if (!in_array($product_type, ['mobile', 'general'], true)) {
        $_SESSION['error'] = 'Invalid product type. Only Mobile and General items can be purchased.';
        header("Location: view_purchase.php");
        exit;
    }
    $notes         = $_POST['notes'] ?? '';
    $paid_amount    = (float)($_POST['paid_amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);

    $arr = function ($v) { return is_array($v) ? $v : ($v === null || $v === '' ? [] : [$v]); };

    if ($supplier_type === 'person') {
        $supplier_id = 0;
        if (!$person_name) {
            $_SESSION['error'] = 'Enter the person name for this purchase.';
            header("Location: view_purchase.php");
            exit;
        }
    }

    // Determine quantity based on product type
    if ($product_type === 'mobile') {
        $imei1s = $arr($_POST['mobile_imei1'] ?? []);
        $mobile_prices = $arr($_POST['mobile_price'] ?? []);
        $quantity = max(count($imei1s), 1);
    } else {
        $mobile_prices = [];
        $quantity = (int)($_POST['quantity'] ?? 1);
    }

    // Compute unit prices BEFORE any product creation so validation happens first.
    // Mobile: per-row prices (mobile_price[]) are the only source.
    $unit_prices = [];
    if ($product_type === 'mobile') {
        for ($i = 0; $i < $quantity; $i++) {
            $unit_prices[] = (float)($mobile_prices[$i] ?? 0);
        }
        foreach ($unit_prices as $up) {
            if ($up <= 0) {
                $_SESSION['error'] = 'Enter a purchase price for every ' . $product_type . ' unit (neechay table me).';
                header("Location: view_purchase.php");
                exit;
            }
        }
        $subtotal = array_sum($unit_prices);
        $avg_price = $quantity > 0 ? round($subtotal / $quantity, 2) : 0;
        $purchase_price = $avg_price;
    } else {
        $subtotal = $quantity * $purchase_price;
        $avg_price = (float)$purchase_price;
    }

    // Inline product creation (when "New ..." is selected)
    $is_new = (int)($_POST['is_new'] ?? 0);
    if ($is_new) {
        $new_name = trim($_POST['new_name'] ?? '');
        $new_brand_id = (int)($_POST['new_brand_id'] ?? 0) ?: null;
        $new_sale_price = (float)($_POST['new_sale_price'] ?? 0);
        $new_category_id = (int)($_POST['new_category_id'] ?? 0) ?: null;
        $new_storage = $_POST['new_storage'] ?? '';
        $new_ram = $_POST['new_ram'] ?? '';
        $new_color = $_POST['new_color'] ?? '';
        $product_condition = $_POST['mobile_condition'] ?? ($_POST['product_condition'] ?? 'New');

        if (!$new_name) {
            $_SESSION['error'] = 'Enter the product model/name.';
            header("Location: view_purchase.php");
            exit;
        }
        if ($new_sale_price <= 0) {
            $_SESSION['error'] = 'Enter a sale price for the new product.';
            header("Location: view_purchase.php");
            exit;
        }
        if ($product_type === 'mobile') {
            $ms = $arr($_POST['mobile_storage'] ?? []);
            $mr = $arr($_POST['mobile_ram'] ?? []);
            $mc = $arr($_POST['mobile_color'] ?? []);
            $new_storage = trim($ms[0] ?? '');
            $new_ram = trim($mr[0] ?? '');
            $new_color = trim($mc[0] ?? '');
        }
        if (!$new_category_id && $product_type === 'mobile') {
            $autoCat = $pdo->prepare("SELECT id FROM categories WHERE product_type = ? AND status = 1 ORDER BY id LIMIT 1");
            $autoCat->execute([$product_type]);
            $new_category_id = $autoCat->fetchColumn() ?: null;
        }

        // Merge into existing product with same name + type instead of creating a duplicate row
        $existing = findProductByNameType($new_name, $product_type);
        if ($existing) {
            $product_id = (int)$existing['id'];
            $merged_product = true;
            $fill = [];
            if (empty($existing['color']))  $fill['color'] = $new_color;
            if (empty($existing['storage'])) $fill['storage'] = $new_storage;
            if (empty($existing['ram']))     $fill['ram'] = $new_ram;
            if (!$existing['category_id'] && $new_category_id) $fill['category_id'] = $new_category_id;
            if (!$existing['brand_id'] && $new_brand_id) $fill['brand_id'] = $new_brand_id;
            if ((float)$existing['sale_price'] <= 0 && $new_sale_price > 0) $fill['sale_price'] = $new_sale_price;
            if ($fill) {
                $fill['updated_at'] = date('Y-m-d');
                update('products', $fill, $product_id);
            }
        } else {
            $merged_product = false;
            $product_id = insert('products', [
                'code' => generateProductCode($product_type),
                'name' => $new_name,
                'description' => '',
                'category_id' => $new_category_id,
                'brand_id' => $new_brand_id,
                'supplier_id' => null,
                'color' => $new_color,
                'imei_no_1' => '',
                'imei_no_2' => '',
                'storage' => $new_storage,
                'ram' => $new_ram,
                'warranty_months' => null,
                'product_condition' => $product_condition,
                'purchase_price' => $purchase_price,
                'sale_price' => $new_sale_price,
                'stock_quantity' => 0,
                'min_stock_level' => 0,
                'unit' => 'pcs',
                'product_type' => $product_type,
                'has_serial' => $product_type === 'mobile' ? 1 : 0,
                'status' => 1,
                'created_at' => date('Y-m-d'),
                'updated_at' => date('Y-m-d'),
            ]);
        }
    } else {
        $merged_product = false;
    }

    $paid_amount = max(0, min($paid_amount, $subtotal));
    $due_amount = $subtotal - $paid_amount;

    // Uniqueness checks for mobile identifiers
    if ($product_type === 'mobile') {
        $imei1s = $arr($_POST['mobile_imei1'] ?? []);
        $imei2s = $arr($_POST['mobile_imei2'] ?? []);
        $all_imeis = array_merge(array_filter($imei1s), array_filter($imei2s));

        // IMEI must be exactly 15 digits
        foreach ($all_imeis as $i) {
            if (!preg_match('/^\d{15}$/', $i)) {
                $_SESSION['error'] = "IMEI '$i' must be exactly 15 digits.";
                header("Location: view_purchase.php");
                exit;
            }
        }

        // Reject duplicates within the same entry form
        $seen = [];
        foreach ($all_imeis as $i) {
            if (isset($seen[$i])) {
                $_SESSION['error'] = "IMEI '$i' is repeated in this entry.";
                header("Location: view_purchase.php");
                exit;
            }
            $seen[$i] = true;
        }

        // Check against both IMEI1 and IMEI2 columns in existing records
        foreach ($all_imeis as $i) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM product_serials WHERE imei_number = ? OR serial_number = ?");
            $chk->execute([$i, $i]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['error'] = "IMEI '$i' already exists in system.";
                header("Location: view_purchase.php");
                exit;
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO purchases (supplier_id, person_name, person_phone, person_cnic, person_address, invoice_no, purchase_date, total_amount, paid_amount, due_amount, status, notes, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'received', ?, ?, CURDATE(), CURDATE())");
        $stmt->execute([$supplier_id ?: null, $person_name ?: null, $person_phone ?: null, $person_cnic ?: null, $person_address ?: null, $invoice_no, $purchase_date, $subtotal, $paid_amount, $due_amount, $notes, $_SESSION['user_id'] ?? 1]);
        $purchase_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$purchase_id, $product_id, $quantity, $avg_price, $subtotal]);

        // Update product stock
        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$quantity, $product_id]);

        // Record individual unit identifiers
        if ($product_type === 'mobile') {
            $imei1s = $arr($_POST['mobile_imei1'] ?? []);
            $imei2s = $arr($_POST['mobile_imei2'] ?? []);
            $storages = $arr($_POST['mobile_storage'] ?? []);
            $rams = $arr($_POST['mobile_ram'] ?? []);
            $colors = $arr($_POST['mobile_color'] ?? []);
            $cond = $_POST['mobile_condition'] ?? 'New';
            for ($i = 0; $i < $quantity; $i++) {
                $i1 = trim($imei1s[$i] ?? '');
                $i2 = trim($imei2s[$i] ?? '');
                $st = $storages[$i] ?? '';
                $ra = $rams[$i] ?? '';
                $co = $colors[$i] ?? '';
                if ($i1 || $i2) {
                    $stmt = $pdo->prepare("INSERT INTO product_serials (product_id, imei_number, serial_number, purchase_id, status, purchase_price, notes, created_at, updated_at) VALUES (?, ?, ?, ?, 'available', ?, ?, CURDATE(), CURDATE())");
                    $stmt->execute([$product_id, $i1 ?: null, $i2 ?: null, $purchase_id, $unit_prices[$i] ?? 0, trim("Mobile - $co - $st/$ra - $cond", ' -')]);
                }
            }
        }

        if ($paid_amount > 0) {
            $user_id = $_SESSION['user_id'] ?? 1;
            if ($supplier_id > 0) {
                $payment_id = insert('supplier_payments', [
                    'supplier_id' => $supplier_id,
                    'purchase_id' => $purchase_id,
                    'amount' => $paid_amount,
                    'payment_method' => $payment_method,
                    'bank_account_id' => $bank_account_id ?: null,
                    'description' => 'Purchase payment' . ($invoice_no ? " - Invoice $invoice_no" : ''),
                    'payment_date' => $purchase_date,
                    'created_by' => $user_id,
                    'created_at' => date('Y-m-d'),
                ]);
                $sup = getById('suppliers', $supplier_id);
                $ref_desc = 'Supplier payment - ' . htmlspecialchars(($sup['contact_person'] ?? '') ?: ($sup['name'] ?? 'Supplier #' . $supplier_id));
                if ($payment_method === 'bank') {
                    recordBankOutflow($pdo, $purchase_date, $paid_amount, $ref_desc, 'supplier_payment', $payment_id, $user_id, $bank_account_id);
                } else {
                    recordCashOutflow($pdo, $purchase_date, $paid_amount, $ref_desc, 'supplier_payment', $payment_id, $user_id);
                }
            } else {
                $ref_desc = ($person_name ? 'Payment to ' . $person_name : 'Purchase payment') . ($invoice_no ? " - Invoice $invoice_no" : '');
                if ($payment_method === 'bank') {
                    recordBankOutflow($pdo, $purchase_date, $paid_amount, $ref_desc, 'purchase', $purchase_id, $user_id, $bank_account_id);
                } else {
                    recordCashOutflow($pdo, $purchase_date, $paid_amount, $ref_desc, 'purchase', $purchase_id, $user_id);
                }
            }
        }

        $pdo->commit();
        if (!empty($merged_product)) {
            $_SESSION['success'] = "Purchase recorded successfully. '" . htmlspecialchars($existing['name'] ?? $new_name) . "' already exists — stock added to same product.";
        } else {
            $_SESSION['success'] = 'Purchase recorded successfully.';
        }
        header("Location: view_purchase.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

// Fetch purchases with product info (filtered)
$f_search = trim($_GET['search'] ?? '');
$f_from = $_GET['date_from'] ?? '';
$f_to = $_GET['date_to'] ?? '';
$f_status = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];
if ($f_search !== '') {
    $where[] = "(p.invoice_no LIKE ? OR p.person_name LIKE ? OR p.person_phone LIKE ? OR p.person_address LIKE ? OR s.name LIKE ? OR s.contact_person LIKE ? OR pr.name LIKE ? OR pr.code LIKE ?)";
    $like = "%$f_search%";
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}
if ($f_from !== '') { $where[] = "p.purchase_date >= ?"; $params[] = $f_from; }
if ($f_to !== '')   { $where[] = "p.purchase_date <= ?"; $params[] = $f_to; }
if ($f_status !== '') { $where[] = "p.status = ?"; $params[] = $f_status; }

$sql = "
    SELECT p.*, s.name AS supplier_name, s.contact_person AS supplier_contact,
        GROUP_CONCAT(DISTINCT pr.name SEPARATOR ', ') AS product_names,
        (SELECT COALESCE(SUM(quantity), 0) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS item_count
    FROM purchases p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN purchase_items pi ON pi.purchase_id = p.id
    LEFT JOIN products pr ON pr.id = pi.product_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id
    ORDER BY p.purchase_date DESC, p.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recent = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<style>
  .type-radio-group { display: flex; gap: 20px; margin-bottom: 20px; }
  .type-radio-group label { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 20px; border: 2px solid #e2e8f0; border-radius: 10px; transition: all .2s; }
  .type-radio-group label:hover { border-color: #a0aec0; }
  .type-radio-group input:checked + label { border-color: #4e73df; background: #eef2ff; font-weight: 600; }
  .type-radio-group input { display: none; }
  .type-section { display: none; }
  .type-section.active { display: block; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-truck"></i> Purchases</h5>
  <button class="btn btn-primary btn-sm" onclick="togglePurchaseForm()">
    <i class="fas fa-plus" id="toggleBtnIcon"></i> New Purchase
  </button>
</div>

<!-- Purchase List -->
<div class="card shadow mb-4" id="purchaseHistoryCard">
  <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> All Purchases</h6>
    <span class="text-muted small"><?= count($recent) ?> records</span>
  </div>
  <div class="card-body">
    <form method="get" class="mb-3">
      <div class="row">
        <div class="col-md-4">
          <input type="text" name="search" class="form-control" placeholder="Search invoice, product, person, supplier..." value="<?=htmlspecialchars($f_search)?>">
        </div>
        <div class="col-md-2">
          <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($f_from)?>" title="From date">
        </div>
        <div class="col-md-2">
          <input type="date" name="date_to" class="form-control" value="<?=htmlspecialchars($f_to)?>" title="To date">
        </div>
        <div class="col-md-2">
          <select name="status" class="form-control">
            <option value="">All Status</option>
            <option value="received" <?=$f_status==='received'?'selected':''?>>Received</option>
            <option value="pending" <?=$f_status==='pending'?'selected':''?>>Pending</option>
            <option value="cancelled" <?=$f_status==='cancelled'?'selected':''?>>Cancelled</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
          <a href="view_purchase.php" class="btn btn-secondary">Reset</a>
        </div>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="thead-light">
          <tr><th>Date</th><th>Supplier</th><th>Invoice</th><th>Product</th><th class="text-right">Qty</th><th class="text-right">Total</th><th>Status</th><th class="text-center">Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($recent)): ?>
            <tr><td colspan="8" class="text-center text-muted">No purchases found</td></tr>
          <?php else: foreach ($recent as $r): ?>
            <tr>
              <td><?=formatDate($r['purchase_date'])?></td>
              <td><?php if ($r['supplier_name']): $_contact = $r['supplier_contact'] ?? ''; echo htmlspecialchars($_contact ? "$_contact ({$r['supplier_name']})" : $r['supplier_name']); elseif (!empty($r['person_name'])): echo htmlspecialchars($r['person_name']); else: echo '-'; endif; ?></td>
              <td><?=htmlspecialchars($r['invoice_no']??'-')?></td>
              <td><?=htmlspecialchars($r['product_names']??'-')?></td>
              <td class="text-right"><?=$r['item_count']?></td>
              <td class="text-right"><?=formatCurrency($r['total_amount'])?></td>
              <td><span class="badge badge-<?=$r['status']==='received'?'success':($r['status']==='cancelled'?'danger':'warning')?>"><?=ucfirst($r['status'])?></span></td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-info" title="View" data-id="<?=$r['id']?>" onclick="viewPurchase(this)"><i class="fas fa-eye"></i></button>
                <a href="purchase_edit.php?id=<?=$r['id']?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-pen"></i></a>
                <a href="javascript:void(0)" onclick="window.open('purchase_print.php?id=<?=$r['id']?>','popup','width=900,height=600')" class="btn btn-sm btn-secondary" title="Print"><i class="fas fa-print"></i></a>
                <a href="view_purchase.php?delete=<?=$r['id']?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this purchase?')"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card shadow mb-4" id="newPurchaseCard" style="display:none;">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle"></i> New Purchase Entry</h6>
  </div>
  <div class="card-body">
    <form method="post" id="purchaseForm">
      <div class="row mb-3">
        <div class="col-md-4 form-group">
          <label class="font-weight-bold small">Purchase Date</label>
          <input type="text" name="purchase_date" class="form-control datepicker" value="<?=date('Y-m-d')?>" required autocomplete="off">
        </div>
        <div class="col-md-4 form-group">
          <label class="font-weight-bold small">Supplier / Person</label>
          <select name="supplier_type" id="supplierType" class="form-control" onchange="toggleSupplierType(this.value)">
            <option value="supplier">Supplier</option>
            <option value="person">Random Person</option>
          </select>
          <div id="supplierSelectWrap" class="mt-2">
            <select name="supplier_id" class="form-control">
              <option value="">Select Supplier</option>
              <?php foreach ($suppliers as $s): ?>
                <option value="<?=$s['id']?>"><?=htmlspecialchars(($s['contact_person'] ? $s['contact_person'] . ' (' . $s['name'] . ')' : $s['name']))?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="personFieldsWrap" style="display:none;">
            <input type="text" name="person_name" class="form-control mb-2" placeholder="Person Name">
            <input type="text" name="person_phone" class="form-control mb-2" placeholder="Mobile Number">
            <input type="text" name="person_cnic" class="form-control mb-2" placeholder="CNIC">
            <input type="text" name="person_address" class="form-control" placeholder="Address">
          </div>
        </div>
        <div class="col-md-4 form-group">
          <label class="font-weight-bold small">Invoice No.</label>
          <input type="text" name="invoice_no" class="form-control" placeholder="Supplier invoice #">
        </div>
      </div>
      <hr>
      <label class="font-weight-bold small">Product Type</label>
      <div class="type-radio-group">
        <input type="radio" name="product_type" value="mobile" id="typeMobile" checked>
        <label for="typeMobile"><i class="fas fa-mobile-alt fa-lg"></i> Mobile</label>

        <input type="radio" name="product_type" value="general" id="typeGeneral">
        <label for="typeGeneral"><i class="fas fa-box fa-lg"></i> General</label>
        
      </div>

      <!-- Mobile Section -->
      <div class="type-section active" id="sectionMobile">
        <div class="row mb-3">
          <div class="col-md-6 form-group">
            <label class="font-weight-bold small">Model</label>
            <select class="form-control" id="mobileModel" onchange="fillMobileDetails(this)">
              <option value="">Select Model</option>
              <option value="new">++ Add New Mobile</option>
              <?php
              $mobiles = $pdo->query("SELECT id, code, name, purchase_price, storage, ram, color, product_condition FROM products WHERE product_type='mobile' AND status=1")->fetchAll();
              foreach ($mobiles as $m): ?>
              <option value="<?=$m['id']?>" data-price="<?=$m['purchase_price']?>" data-storage="<?=htmlspecialchars($m['storage']??'')?>" data-ram="<?=htmlspecialchars($m['ram']??'')?>" data-color="<?=htmlspecialchars($m['color']??'')?>" data-condition="<?=htmlspecialchars($m['product_condition']??'')?>"><?=htmlspecialchars($m['name'])?> (<?=htmlspecialchars($m['code'])?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 form-group" id="mobileConditionWrap">
            <label class="font-weight-bold small">Condition</label>
            <select class="form-control" id="mobileCondition">
              <option value="New">New</option>
              <option value="Used">Used</option>
              <option value="Refurbished">Refurbished</option>
            </select>
          </div>
        </div>
        <div id="newMobileWrap" class="bg-light rounded p-3 border mb-3" style="display:none;">
          <div class="row mb-2">
            <div class="col-md-6 form-group mb-0">
              <label class="small font-weight-bold">Product Name *</label>
              <input type="text" name="new_name" id="newMobileName" class="form-control" placeholder="e.g. iPhone 14">
            </div>
            <div class="col-md-6 form-group mb-0">
              <label class="small font-weight-bold">Company (Brand)</label>
              <select name="new_brand_id" id="newMobileBrand" class="form-control">
                <option value="">Select Company</option>
                <?php foreach ($brands as $b): ?>
                  <option value="<?=$b['id']?>"><?=htmlspecialchars($b['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 form-group mb-0">
              <label class="small font-weight-bold">Condition</label>
              <select name="new_mobile_condition" id="newMobileCondition" class="form-control" onchange="document.getElementById('mobileConditionHidden').value = this.value">
                <option value="New">New</option>
                <option value="Used">Used</option>
                <option value="Refurbished">Refurbished</option>
              </select>
            </div>
            <div class="col-md-4 form-group mb-0">
              <label class="small font-weight-bold">Sale Price *</label>
              <input type="number" name="new_sale_price" id="newMobileSale" class="form-control" step="0.01" min="0" placeholder="Selling price">
            </div>
            <div class="col-md-4 form-group mb-0">
              <small class="text-muted">Har mobile ki purchase price neechay table me likhein.</small>
            </div>
          </div>
        </div>
        <input type="hidden" name="is_new" id="mobileIsNew" value="0">
        <label class="font-weight-bold small">Enter Mobile Details</label>
        <span class="badge badge-primary ml-2" id="mobileCount">Quantity: 1</span>
        <span class="badge badge-success ml-2" id="mobileSubtotal">Total: 0.00</span>
        <div class="table-responsive mb-2">
          <table class="table table-bordered table-sm" id="mobileTable">
            <thead class="thead-light">
              <tr><th style="width:40px;">#</th><th>IMEI No. 1</th><th>IMEI No. 2</th><th>Storage</th><th>RAM</th><th>Color</th><th style="width:110px;">Price</th><th style="width:50px;"></th></tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center">1</td>
                <td><input type="text" name="mobile_imei1[]" class="form-control form-control-sm imei-input" placeholder="IMEI 1" maxlength="15" oninput="this.value=this.value.replace(/\D/g,'')"></td>
                <td><input type="text" name="mobile_imei2[]" class="form-control form-control-sm imei-input" placeholder="IMEI 2" maxlength="15" oninput="this.value=this.value.replace(/\D/g,'')"></td>
                <td><input type="text" name="mobile_storage[]" class="form-control form-control-sm mobileStorageFill" placeholder="Storage"></td>
                <td><input type="text" name="mobile_ram[]" class="form-control form-control-sm mobileRamFill" placeholder="RAM"></td>
                <td><input type="text" name="mobile_color[]" class="form-control form-control-sm mobileColorFill" placeholder="Color"></td>
                <td><input type="number" name="mobile_price[]" class="form-control form-control-sm mobilePriceFill" step="0.01" min="0" placeholder="Price" oninput="updateMobileTotal()"></td>
                <td class="text-center"></td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-success" onclick="addMobileRow()"><i class="fas fa-plus"></i> Add Mobile</button>
        <input type="hidden" name="product_id" id="mobileProductId" value="">
        <input type="hidden" name="mobile_condition" id="mobileConditionHidden" value="">
      </div>

      <!-- General Section -->
      <div class="type-section" id="sectionGeneral">
        <div class="row">
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Product</label>
            <select name="product_id" class="form-control" id="generalProduct" onchange="fillGeneralPrice(this)">
              <option value="">Select Product</option>
              <option value="new">++ Add New Product</option>
              <?php
              $generals = $pdo->query("SELECT id, code, name, purchase_price FROM products WHERE product_type='general' AND status=1")->fetchAll();
              foreach ($generals as $g): ?>
              <option value="<?=$g['id']?>" data-price="<?=$g['purchase_price']?>"><?=htmlspecialchars($g['name'])?> (<?=htmlspecialchars($g['code'])?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 form-group" id="generalPriceWrap">
            <label class="font-weight-bold small">Purchase Price</label>
            <input type="number" name="purchase_price" class="form-control" id="generalPrice" step="0.01" required>
          </div>
          <div class="col-md-4 form-group" id="generalQtyWrap">
            <label class="font-weight-bold small">Quantity</label>
            <input type="number" name="quantity" class="form-control" id="generalQty" value="1" min="1" required>
          </div>
        </div>
        <div id="newGeneralWrap" class="bg-light rounded p-3 border mb-3" style="display:none;">
          <div class="row mb-2">
            <div class="col-md-12 form-group mb-0">
              <label class="small font-weight-bold">Product Name *</label>
              <input type="text" name="new_name" id="newGeneralName" class="form-control" placeholder="e.g. LED Bulb">
            </div>
          </div>
          <div class="row">
            <div class="col-md-3 form-group mb-0">
              <label class="small font-weight-bold">Category</label>
              <select name="new_category_id" id="newGeneralCategory" class="form-control">
                <option value="">Select Category</option>
                <?php foreach ($general_categories as $c): ?>
                  <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 form-group mb-0">
              <label class="small font-weight-bold">Quantity *</label>
              <input type="number" name="quantity" id="newGeneralQty" class="form-control" value="1" min="1" required>
            </div>
            <div class="col-md-3 form-group mb-0">
              <label class="small font-weight-bold">Purchase Price</label>
              <input type="number" name="purchase_price" id="newGeneralPrice" class="form-control" step="0.01" required>
            </div>
            <div class="col-md-3 form-group mb-0">
              <label class="small font-weight-bold">Sale Price *</label>
              <input type="number" name="new_sale_price" id="newGeneralSale" class="form-control" step="0.01" min="0" placeholder="Selling price">
            </div>
          </div>
        </div>
        <input type="hidden" name="is_new" id="generalIsNew" value="0">
      </div>

      <div class="row">
        <div class="col-md-12 form-group">
          <label class="font-weight-bold small">Description</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional Description..."></textarea>
        </div>
      </div>

      <hr>
      <label class="font-weight-bold small">Payment</label>
      <div class="row">
        <div class="col-md-4 form-group">
          <label class="small">Paid Amount</label>
          <input type="number" name="paid_amount" class="form-control" step="0.01" min="0" value="0">
        </div>
        <div class="col-md-4 form-group">
          <label class="small">Payment Method</label>
          <select name="payment_method" class="form-control" onchange="toggleBankAccount(this.value)">
            <option value="cash">Cash</option>
            <option value="bank">Bank Transfer</option>
          </select>
        </div>
        <div class="col-md-4 form-group" id="bankAccountGroup" style="display:none;">
          <label class="small">Bank Account</label>
          <select name="bank_account_id" class="form-control">
            <option value="">Select Account</option>
            <?php foreach ($bank_accounts as $ba): ?>
              <option value="<?=$ba['id']?>"><?=htmlspecialchars($ba['bank_name'] . ' - ' . $ba['account_name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr>
      <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save"></i> Make Entry</button>
    </form>
  </div>
</div>

<script>
function switchType(type) {
  document.querySelectorAll('.type-section').forEach(function(s) {
    s.classList.remove('active');
    s.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = true; });
  });
  var active = document.getElementById('section' + type.charAt(0).toUpperCase() + type.slice(1));
  active.classList.add('active');
  active.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = false; });
  syncSectionInputs(type);
}

function syncSectionInputs(type) {
  var sets = {
    mobile: {
      sel: 'mobileModel', isNew: 'mobileIsNew', wrap: 'newMobileWrap',
      wrapFields: ['newMobileName', 'newMobileBrand', 'newMobileCondition', 'newMobileSale'],
      topFields: ['mobileCondition'], topWraps: ['mobileConditionWrap']
    },
    general: {
      sel: 'generalProduct', isNew: 'generalIsNew', wrap: 'newGeneralWrap',
      wrapFields: ['newGeneralName', 'newGeneralCategory', 'newGeneralQty', 'newGeneralPrice', 'newGeneralSale'],
      topFields: ['generalPrice', 'generalQty'], topWraps: ['generalPriceWrap', 'generalQtyWrap']
    }
  };
  var cfg = sets[type];
  if (!cfg) return;
  var isNew = document.getElementById(cfg.sel).value === 'new';
  document.getElementById(cfg.wrap).style.display = isNew ? '' : 'none';
  document.getElementById(cfg.isNew).value = isNew ? '1' : '0';
  cfg.wrapFields.forEach(function(id) { document.getElementById(id).disabled = !isNew; });
  cfg.topFields.forEach(function(id) { document.getElementById(id).disabled = isNew; });
  cfg.topWraps.forEach(function(id) { document.getElementById(id).style.display = isNew ? 'none' : ''; });
}

document.querySelectorAll('input[name="product_type"]').forEach(function(r) {
  r.addEventListener('change', function() { switchType(this.value); });
});

function fillMobileDetails(sel) {
  var opt = sel.options[sel.selectedIndex];
  syncSectionInputs('mobile');
  if (opt.value === 'new') {
    document.getElementById('mobileProductId').value = '';
    document.getElementById('newMobileCondition').value = 'New';
    document.getElementById('mobileConditionHidden').value = 'New';
    document.querySelectorAll('.mobileStorageFill').forEach(function(e) { if (!e.value) e.value = ''; });
    document.querySelectorAll('.mobileRamFill').forEach(function(e) { if (!e.value) e.value = ''; });
    document.querySelectorAll('.mobileColorFill').forEach(function(e) { if (!e.value) e.value = ''; });
    document.querySelectorAll('.mobilePriceFill').forEach(function(e) { e.value = ''; });
    updateMobileTotal();
    return;
  }
  document.getElementById('mobileProductId').value = opt.value || '';
  document.getElementById('mobileCondition').value = opt.dataset.condition || 'New';
  document.getElementById('mobileConditionHidden').value = opt.dataset.condition || 'New';
  var storage = opt.dataset.storage || '';
  var ram = opt.dataset.ram || '';
  var color = opt.dataset.color || '';
  document.querySelectorAll('.mobileStorageFill').forEach(function(e) { if (!e.value) e.value = storage; });
  document.querySelectorAll('.mobileRamFill').forEach(function(e) { if (!e.value) e.value = ram; });
  document.querySelectorAll('.mobileColorFill').forEach(function(e) { if (!e.value) e.value = color; });
  document.querySelectorAll('.mobilePriceFill').forEach(function(e) { if (!e.value) e.value = opt.dataset.price || ''; });
  updateMobileTotal();
}

function updateMobileTotal() {
  var total = 0;
  document.querySelectorAll('.mobilePriceFill').forEach(function(e) {
    var v = parseFloat(e.value);
    if (!v || v <= 0) return;
    total += v;
  });
  document.getElementById('mobileSubtotal').textContent = 'Total: ' + total.toFixed(2);
}

function fillGeneralPrice(sel) {
  syncSectionInputs('general');
  var opt = sel.options[sel.selectedIndex];
  if (opt.value === 'new') {
    document.getElementById('generalPrice').value = '';
    document.getElementById('generalQty').value = 1;
    document.getElementById('newGeneralPrice').value = '';
    document.getElementById('newGeneralQty').value = 1;
    return;
  }
  document.getElementById('generalPrice').value = opt.dataset.price || '';
  document.getElementById('generalQty').value = 1;
}

function toggleSupplierType(val) {
  var supWrap = document.getElementById('supplierSelectWrap');
  var perWrap = document.getElementById('personFieldsWrap');
  if (val === 'person') {
    supWrap.style.display = 'none';
    perWrap.style.display = '';
  } else {
    supWrap.style.display = '';
    perWrap.style.display = 'none';
  }
}

function addMobileRow() {
  var tbody = document.querySelector('#mobileTable tbody');
  var rows = tbody.querySelectorAll('tr');
  var num = rows.length + 1;
  var storage = document.getElementById('mobileModel').options[document.getElementById('mobileModel').selectedIndex]?.dataset?.storage || '';
  var ram = document.getElementById('mobileModel').options[document.getElementById('mobileModel').selectedIndex]?.dataset?.ram || '';
  var color = document.getElementById('mobileModel').options[document.getElementById('mobileModel').selectedIndex]?.dataset?.color || '';
  var price = document.getElementById('mobileModel').value === 'new' ? '' : (document.getElementById('mobileModel').options[document.getElementById('mobileModel').selectedIndex]?.dataset?.price || '');
  var tr = document.createElement('tr');
  tr.innerHTML = '<td class="text-center">'+num+'</td><td><input type="text" name="mobile_imei1[]" class="form-control form-control-sm imei-input" placeholder="IMEI 1" maxlength="15" oninput="this.value=this.value.replace(/\D/g,\'\')"></td><td><input type="text" name="mobile_imei2[]" class="form-control form-control-sm imei-input" placeholder="IMEI 2" maxlength="15" oninput="this.value=this.value.replace(/\D/g,\'\')"></td><td><input type="text" name="mobile_storage[]" class="form-control form-control-sm mobileStorageFill" value="'+storage+'" placeholder="Storage"></td><td><input type="text" name="mobile_ram[]" class="form-control form-control-sm mobileRamFill" value="'+ram+'" placeholder="RAM"></td><td><input type="text" name="mobile_color[]" class="form-control form-control-sm mobileColorFill" value="'+color+'" placeholder="Color"></td><td><input type="number" name="mobile_price[]" class="form-control form-control-sm mobilePriceFill" step="0.01" min="0" placeholder="Price" value="'+price+'" oninput="updateMobileTotal()"></td><td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeMobileRow(this)"><i class="fas fa-times"></i></button></td>';
  tbody.appendChild(tr);
  updateMobileCount();
}

function removeMobileRow(btn) {
  btn.closest('tr').remove();
  renumberMobileRows();
  updateMobileCount();
}

function renumberMobileRows() {
  var rows = document.querySelectorAll('#mobileTable tbody tr');
  rows.forEach(function(r, i) { r.cells[0].textContent = i + 1; });
}

function updateMobileCount() {
  var count = document.querySelectorAll('#mobileTable tbody tr').length;
  document.getElementById('mobileCount').textContent = 'Quantity: ' + count;
  updateMobileTotal();
}

switchType('mobile');
updateMobileCount();

function toggleBankAccount(method) {
  document.getElementById('bankAccountGroup').style.display = method === 'bank' ? '' : 'none';
}

document.getElementById('purchaseForm').addEventListener('submit', function(e) {
  var errors = [];
  var anyFilled = false;
  this.querySelectorAll('.imei-input').forEach(function(inp) {
    inp.classList.remove('is-invalid');
    var v = inp.value.trim();
    if (v) {
      anyFilled = true;
      if (v.length !== 15) {
        inp.classList.add('is-invalid');
        errors.push('IMEI must be exactly 15 digits: "' + v + '" (' + v.length + ' digits entered)');
      }
    }
  });
  if (errors.length) {
    e.preventDefault();
    alert(errors.join('\n'));
  }
});

function togglePurchaseForm() {
  var form = document.getElementById('newPurchaseCard');
  var history = document.getElementById('purchaseHistoryCard');
  var icon = document.getElementById('toggleBtnIcon');
  if (form.style.display === 'none') {
    form.style.display = '';
    history.style.display = 'none';
    icon.classList.remove('fa-plus');
    icon.classList.add('fa-minus');
  } else {
    form.style.display = 'none';
    history.style.display = '';
    icon.classList.remove('fa-minus');
    icon.classList.add('fa-plus');
  }
}
</script>

<!-- Purchase Detail Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" role="dialog" aria-labelledby="purchaseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="purchaseModalLabel"><i class="fas fa-truck"></i> Purchase Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="purchaseModalBody">
        <div class="text-center py-4">
          <i class="fas fa-spinner fa-spin fa-2x"></i>
          <p class="mt-2 text-muted">Loading...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <a href="#" id="purchaseEditLink" class="btn btn-primary"><i class="fas fa-pen"></i> Edit</a>
      </div>
    </div>
  </div>
</div>

<script>
function viewPurchase(btn) {
    var id = btn.getAttribute('data-id');
    document.getElementById('purchaseEditLink').href = 'purchase_edit.php?id=' + id;
    document.getElementById('purchaseModalBody').innerHTML =
        '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2 text-muted">Loading...</p></div>';
    $('#purchaseModal').modal('show');

    fetch('purchase_details.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.error) {
                document.getElementById('purchaseModalBody').innerHTML = '<div class="alert alert-danger">' + d.error + '</div>';
                return;
            }
            var badge = d.status === 'received' ? 'success' : (d.status === 'cancelled' ? 'danger' : 'warning');
            var html = '';

            // Header info
            html += '<div class="row mb-3">';
            html += '<div class="col-md-3"><small class="text-muted">Date</small><p class="font-weight-bold mb-0">' + d.purchase_date + '</p></div>';
            var supDisplay;
            if (d.supplier_name && d.supplier_name !== '-') {
                var supContact = d.supplier_contact || '';
                supDisplay = escapeHtml(supContact ? supContact + ' (' + d.supplier_name + ')' : d.supplier_name);
            } else if (d.person_name) {
                supDisplay = escapeHtml(d.person_name);
                if (d.person_phone || d.person_cnic || d.person_address) {
                    var personExtras = [];
                    if (d.person_phone) personExtras.push(escapeHtml(d.person_phone));
                    if (d.person_cnic) personExtras.push('CNIC: ' + escapeHtml(d.person_cnic));
                    if (d.person_address) personExtras.push(escapeHtml(d.person_address));
                    supDisplay += '<br><small class="text-muted">' + personExtras.join(' | ') + '</small>';
                }
            } else {
                supDisplay = '-';
            }
            html += '<div class="col-md-3"><small class="text-muted">Supplier</small><p class="font-weight-bold mb-0">' + supDisplay + '</p></div>';
            html += '<div class="col-md-3"><small class="text-muted">Invoice No.</small><p class="font-weight-bold mb-0">' + escapeHtml(d.invoice_no || '-') + '</p></div>';
            html += '<div class="col-md-3"><small class="text-muted">Status</small><p class="mb-0"><span class="badge badge-' + badge + '">' + d.status.charAt(0).toUpperCase() + d.status.slice(1) + '</span></p></div>';
            html += '</div>';

            if (d.notes) {
                html += '<div class="mb-3"><small class="text-muted">Notes</small><p class="mb-0">' + escapeHtml(d.notes) + '</p></div>';
            }

            // Items
            html += '<h6 class="font-weight-bold" style="color:#0f172a;">Items</h6>';
            html += '<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="thead-light"><tr><th>Product</th><th>Type</th><th class="text-center">Qty</th><th class="text-right">Price</th><th class="text-right">Subtotal</th></tr></thead><tbody>';
            for (var i = 0; i < d.items.length; i++) {
                var it = d.items[i];
                html += '<tr><td>' + escapeHtml(it.product_name) + '<br><small class="text-muted">' + escapeHtml(it.product_code) + '</small></td>';
                html += '<td><span class="badge badge-info">' + escapeHtml(it.product_type) + '</span></td>';
                html += '<td class="text-center">' + it.quantity + '</td>';
                html += '<td class="text-right">' + formatNum(it.purchase_price) + '</td>';
                html += '<td class="text-right">' + formatNum(it.subtotal) + '</td></tr>';
            }
            html += '<tfoot><tr class="font-weight-bold"><td colspan="4" class="text-right">Total</td><td class="text-right">' + formatNum(d.total_amount) + '</td></tr></tfoot>';
            html += '</tbody></table></div>';

            // Serials (units)
            if (d.serials && d.serials.length > 0) {
                var byProduct = {};
                d.serials.forEach(function(sn) {
                    if (!byProduct[sn.product_id]) byProduct[sn.product_id] = { type: sn.product_type || 'general', units: [] };
                    byProduct[sn.product_id].units.push(sn);
                });
                var prodNameMap = {};
                d.items.forEach(function(it) {
                    if (it.product_id) prodNameMap[it.product_id] = it.product_name;
                });
                for (var pid in byProduct) {
                    var grp = byProduct[pid];
                    var isMobile = grp.type === 'mobile';
                    var pname = prodNameMap[pid] || 'Product';
                    html += '<h6 class="font-weight-bold" style="color:#0f172a;">' + (grp.type === 'mobile' ? 'Mobile Units' : 'Serial Numbers') + ' - ' + escapeHtml(pname) + ' <span class="badge badge-primary">' + grp.units.length + ' units</span></h6>';
                    html += '<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="thead-light"><tr><th>#</th>';
                    if (isMobile) { html += '<th>IMEI 1</th><th>IMEI 2</th><th>Details</th>'; }
                    else { html += '<th>Serial / IMEI</th>'; }
                    html += '<th class="text-right">Price</th><th>Status</th></tr></thead><tbody>';
                    for (var j = 0; j < grp.units.length; j++) {
                        var sn = grp.units[j];
                        var snBadge = sn.status === 'available' ? 'success' : (sn.status === 'sold' ? 'secondary' : 'warning');
                        html += '<tr><td>' + (j + 1) + '</td>';
                        if (isMobile) {
                            html += '<td><code>' + escapeHtml(sn.imei_number || '-') + '</code></td>';
                            html += '<td><code>' + escapeHtml(sn.imei2 || '-') + '</code></td>';
                            html += '<td>' + escapeHtml(sn.notes || '-') + '</td>';
                        } else {
                            html += '<td><code>' + escapeHtml(sn.serial_number || sn.imei_number || sn.notes || '-') + '</code></td>';
                        }
                        html += '<td class="text-right">' + formatNum(sn.purchase_price) + '</td>';
                        html += '<td><span class="badge badge-' + snBadge + '">' + sn.status + '</span></td></tr>';
                    }
                    html += '</tbody></table></div>';
                }
            }

            html += '<p class="text-muted small mb-0">Created: ' + d.created_at + '</p>';
            document.getElementById('purchaseModalBody').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('purchaseModalBody').innerHTML = '<div class="alert alert-danger">Failed to load purchase details.</div>';
        });
}

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function formatNum(n) {
    return parseFloat(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
