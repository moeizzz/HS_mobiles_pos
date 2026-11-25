<?php
/**
 * Add Mobile / Product  (single-unit stock-in for the Pakistani mobile market)
 *
 * - Category toggle: Mobile (default) / General accessory
 * - Mobile: one physical unit per submit, tracked in product_serials (IMEI)
 * - General: quantity-based, no serial tracking
 * - Every submit also creates a `purchases` + `purchase_items` row so that supplier
 *   ledgers, cash book and bank book stay consistent with the Purchases module.
 *
 * Requires: migrations/2026_11_20_mobile_market_fields.sql
 *
 * Notes on existing conventions that are deliberately preserved:
 *   products.stock_quantity           -> stock counter
 *   product_serials.imei_number       -> IMEI 1
 *   product_serials.serial_number     -> IMEI 2 (mobiles)
 *   product_serials.status 'available'-> "In Stock"
 */
session_start();
$page_title = 'Add Mobile / Product';
$base_url   = '../../';
require_once '../../includes/functions.php';

// ---------------------------------------------------------------
// AJAX: live duplicate-IMEI check  (add_purchase.php?check_imei=XXXXXXXXXXXXXXX)
// ---------------------------------------------------------------
if (isset($_GET['check_imei'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'auth']); exit; }
    $v = preg_replace('/\D/', '', $_GET['check_imei']);
    $st = $pdo->prepare("SELECT ps.id, p.name FROM product_serials ps JOIN products p ON p.id = ps.product_id
                         WHERE ps.status = 'available' AND (ps.imei_number = ? OR ps.serial_number = ?) LIMIT 1");
    $st->execute([$v, $v]);
    $row = $st->fetch();
    echo json_encode(['exists' => (bool)$row, 'product' => $row['name'] ?? null]);
    exit;
}

// ---------------------------------------------------------------
// Reference data
// ---------------------------------------------------------------
// Dropdown lists (condition, PTA, warranty, accessories, RAM, storage, network notes) live in the database
// table app_options and are managed from Settings - nothing is hardcoded here.

$brands     = $pdo->query("SELECT id, name FROM brands WHERE status = 1 ORDER BY name = 'Other', name")->fetchAll();
$suppliers  = $pdo->query("SELECT id, name, contact_person FROM suppliers WHERE status = 1 ORDER BY name")->fetchAll();
$banks      = $pdo->query("SELECT id, account_name, bank_name FROM bank_accounts WHERE status = 1 ORDER BY id")->fetchAll();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function old($k, $d = '') { return $_POST[$k] ?? $d; }
function in_list($v, array $list) { return in_array($v, $list, true); }

$errors = [];

// ---------------------------------------------------------------
// POST processing
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['product_type'] ?? 'mobile';
    // Mobile-only mode: 'general' items are disabled (remove the next line to re-enable)
    $type = 'mobile';

    $brand_id = (int)($_POST['brand_id'] ?? 0) ?: null;
    $brand_name_input = trim($_POST['brand_name'] ?? '');
    $brand_name = '';

    if ($brand_name_input !== '') {
        $st_b = $pdo->prepare("SELECT id, name FROM brands WHERE LOWER(TRIM(name)) = ? LIMIT 1");
        $st_b->execute([strtolower($brand_name_input)]);
        $foundBrand = $st_b->fetch();
        if ($foundBrand) {
            $brand_id = (int)$foundBrand['id'];
            $brand_name = $foundBrand['name'];
        } else {
            // Brand does not exist yet -> auto insert new brand into brands table
            $ins_b = $pdo->prepare("INSERT INTO brands (name, status, created_at) VALUES (?, 1, CURDATE())");
            $ins_b->execute([$brand_name_input]);
            $brand_id = (int)$pdo->lastInsertId();
            $brand_name = $brand_name_input;
        }
    } elseif ($brand_id) {
        $st_b = $pdo->prepare("SELECT name FROM brands WHERE id = ?");
        $st_b->execute([$brand_id]);
        $brand_name = (string)$st_b->fetchColumn();
    }
    $model    = trim($_POST['model_name'] ?? '');
    $ram      = trim($_POST['ram'] ?? '');
    $storage  = trim($_POST['storage'] ?? '');
    $color    = trim($_POST['color'] ?? '');
    $qty      = $type === 'general' ? max(1, (int)($_POST['quantity'] ?? 1)) : 1;
    $buy      = (float)($_POST['purchase_price'] ?? 0);
    $sell     = (float)($_POST['sale_price'] ?? 0);

    // Mobile-specific
    $imei1  = preg_replace('/\D/', '', $_POST['imei_1'] ?? '');
    $imei2  = preg_replace('/\D/', '', $_POST['imei_2'] ?? '');
    $serial = trim($_POST['device_serial_no'] ?? '');
    $cond   = $_POST['phone_condition'] ?? '';
    $pta    = $_POST['pta_status'] ?? '';
    $netnote = trim($_POST['network_note'] ?? '');
    $battery = trim($_POST['battery_health'] ?? '');
    $warranty = $_POST['warranty_status'] ?? '';
    $accs   = array_values(array_intersect(option_list('accessory'), (array)($_POST['accessories'] ?? [])));
    $fault  = trim($_POST['fault_notes'] ?? '');

    // Purchase source
    $source      = ($_POST['source_type'] ?? 'supplier') === 'walkin' ? 'walkin' : 'supplier';
    $supplier_id = $source === 'supplier' ? ((int)($_POST['supplier_id'] ?? 0) ?: null) : null;
    $s_name  = $source === 'walkin' ? trim($_POST['seller_name'] ?? '') : '';
    $s_cnic  = $source === 'walkin' ? trim($_POST['seller_cnic'] ?? '') : '';
    $s_phone = $source === 'walkin' ? trim($_POST['seller_phone'] ?? '') : '';
    $pay_mode = $_POST['payment_mode'] ?? 'cash';
    if (!in_array($pay_mode, ['cash', 'bank', 'credit'], true)) $pay_mode = 'cash';
    $bank_id = (int)($_POST['bank_account_id'] ?? 0) ?: null;
    $p_date  = $_POST['purchase_date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $p_date)) $p_date = date('Y-m-d');

    // ---- Validation ----
    if ($model === '') $errors[] = $type === 'general' ? 'Enter the item name.' : 'Enter the model name.';
    if ($type !== 'general' && !$brand_id) $errors[] = 'Enter or select a brand.';
    if ($buy <= 0) $errors[] = 'Purchase price must be greater than zero.';
    if ($sell <= 0) $errors[] = 'Sale price must be greater than zero.';
    if ($buy > 10000000 || $sell > 10000000) $errors[] = 'Price looks too large (maximum Rs. 10,000,000 per unit). Check for an extra zero.';
    if ($source === 'supplier' && !$supplier_id) $errors[] = 'Select the supplier (or switch the source to Walk-in seller).';
    if ($sell > 0 && $buy > 0 && $sell < $buy) $warn_below_cost = true;   // allowed, but flagged after save

    if ($type === 'mobile') {
        if (!preg_match('/^\d{15}$/', $imei1)) $errors[] = 'IMEI 1 is required and must be exactly 15 digits.';
        if ($imei2 !== '' && !preg_match('/^\d{15}$/', $imei2)) $errors[] = 'IMEI 2 must be exactly 15 digits (or leave it empty).';
        if ($imei2 !== '' && $imei1 === $imei2) $errors[] = 'IMEI 1 and IMEI 2 cannot be the same.';
        if (option_list('phone_condition') && !in_list($cond, option_list('phone_condition'))) $errors[] = 'Select the phone condition.';
        if (option_list('pta_status') && !in_list($pta, option_list('pta_status'))) $errors[] = 'Select the PTA status.';
        if ($warranty !== '' && !in_list($warranty, option_list('warranty'))) $warranty = '';
        if ($battery !== '' && !preg_match('/^(N\/A|\d{1,3}\s?%?)$/i', $battery)) $errors[] = 'Battery health must be like 95% or N/A.';
        if ($battery !== '' && preg_match('/^\d/', $battery)) $battery = (int)$battery . '%';
    }

    if ($source === 'supplier' && $pay_mode === 'credit' && !$supplier_id) {
        $errors[] = 'Credit / Khata purchase needs a supplier selected (walk-in sellers cannot be put on credit).';
    }
    if ($source === 'walkin') {
        if ($s_name === '') $errors[] = 'Enter the seller name.';
        if (!preg_match('/^\d{5}-\d{7}-\d$/', $s_cnic)) $errors[] = 'Seller CNIC is required in the format XXXXX-XXXXXXX-X.';
        if ($s_phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $s_phone)) $errors[] = 'Seller phone number looks invalid.';
    }
    if ($pay_mode === 'bank' && !$bank_id) $errors[] = 'Select the bank account for the transfer.';

    // ---- Save ----
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1) Duplicate IMEI / serial guard (row-locked so two terminals can't both pass)
            $dupVals = $type === 'mobile' ? array_values(array_filter([$imei1, $imei2])) : [];
            // Any status is checked. An 'available' match is a hard duplicate. A previously
            // sold/returned unit being bought back (used-phone market) re-uses its old row,
            // because IMEI columns are UNIQUE.
            $reuse_id = null;
            foreach ($dupVals as $val) {
                $st = $pdo->prepare("SELECT ps.id, ps.status, ps.imei_number, p.name FROM product_serials ps JOIN products p ON p.id = ps.product_id
                                     WHERE ps.imei_number = ? OR ps.serial_number = ? FOR UPDATE");
                $st->execute([$val, $val]);
                foreach ($st->fetchAll() as $dup) {
                    if ($dup['status'] === 'available') {
                        throw new RuntimeException("Duplicate: IMEI/Serial $val is already In Stock ({$dup['name']}).");
                    }
                    if (!$reuse_id && $dup['imei_number'] === $val && $val === $imei1) {
                        $reuse_id = (int)$dup['id'];
                    } else {
                        throw new RuntimeException("IMEI/Serial $val already exists in an older record ({$dup['name']}). Check IMEI 1 / IMEI 2.");
                    }
                }
            }

            // 2) Resolve product (smart stock grouping)
            if ($brand_id && empty($brand_name)) {
                $b = $pdo->prepare("SELECT name FROM brands WHERE id = ?");
                $b->execute([$brand_id]);
                $brand_name = (string)$b->fetchColumn();
            }
            $full_name = trim(($type === 'general' ? '' : $brand_name . ' ') . $model);

            if ($type === 'general') {
                $g = $pdo->prepare("SELECT * FROM products WHERE product_type = 'general' AND status = 1 AND TRIM(LOWER(name)) = ? LIMIT 1 FOR UPDATE");
                $g->execute([strtolower($full_name)]);
            } else { // mobile: group by brand + model + RAM + storage + color
                $g = $pdo->prepare("SELECT * FROM products WHERE product_type = 'mobile' AND status = 1 AND brand_id <=> ?
                                    AND TRIM(LOWER(model_name)) = ? AND COALESCE(ram,'') = ? AND COALESCE(storage,'') = ? AND TRIM(LOWER(COALESCE(color,''))) = ?
                                    LIMIT 1 FOR UPDATE");
                $g->execute([$brand_id, strtolower($model), $ram, $storage, strtolower($color)]);
            }
            $product = $g->fetch();

            if ($product) {
                $product_id = (int)$product['id'];
                $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, purchase_price = ?, sale_price = ?,
                               supplier_id = COALESCE(?, supplier_id), updated_at = CURDATE() WHERE id = ?")
                    ->execute([$qty, $buy, $sell, $supplier_id, $product_id]);
                $merged = true;
            } else {
                $c = $pdo->prepare("SELECT id FROM categories WHERE product_type = ? ORDER BY id LIMIT 1");
                $c->execute([$type]);
                $product_id = (int)insert('products', [
                    'code'              => generateProductCode($type),
                    'name'              => $full_name,
                    'model_name'        => $type === 'general' ? null : $model,
                    'category_id'       => $c->fetchColumn() ?: null,
                    'brand_id'          => $brand_id,
                    'supplier_id'       => $supplier_id,
                    'color'             => $color ?: null,
                    'storage'           => $storage ?: null,
                    'ram'               => $ram ?: null,
                    'product_condition' => $type === 'mobile' ? (str_starts_with($cond, 'Used') || str_starts_with($cond, 'Kit') ? 'Used' : 'New') : 'New',
                    'purchase_price'    => $buy,
                    'sale_price'        => $sell,
                    'stock_quantity'    => $qty,
                    'product_type'      => $type,
                    'has_serial'        => $type === 'general' ? 0 : 1,
                    'status'            => 1,
                    'created_at'        => date('Y-m-d'),
                    'updated_at'        => date('Y-m-d'),
                ]);
                $merged = false;
            }

            // 3) Purchase header + line (keeps supplier ledger / reports consistent)
            $total = round($buy * $qty, 2);
            $paid  = $pay_mode === 'credit' ? 0 : $total;
            $user_id = $_SESSION['user_id'] ?? 1;
            // MAX-based (COUNT+1 collides after any delete); UNIQUE(invoice_no) is the final guard
            $pfx = 'PUR-' . date('ymd') . '-';
            $mx = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no, ?) AS UNSIGNED)), 0) FROM purchases WHERE invoice_no LIKE ? FOR UPDATE");
            $mx->execute([strlen($pfx) + 1, $pfx . '%']);
            $invoice_no = $pfx . str_pad((int)$mx->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
            $purchase_id = (int)insert('purchases', [
                'supplier_id'    => $supplier_id,
                'person_name'    => $s_name ?: null,
                'person_phone'   => $s_phone ?: null,
                'person_cnic'    => $s_cnic ?: null,
                'invoice_no'     => $invoice_no,
                'purchase_date'  => $p_date,
                'total_amount'   => $total,
                'paid_amount'    => $paid,
                'due_amount'     => $total - $paid,
                'status'         => 'received',
                'notes'          => 'Added via Add Mobile / Product',
                'created_by'     => $user_id,
                'created_at'     => date('Y-m-d'),
            ]);
            insert('purchase_items', [
                'purchase_id' => $purchase_id, 'product_id' => $product_id,
                'quantity' => $qty, 'purchase_price' => $buy, 'subtotal' => $total,
            ]);

            // 4) Unit record (mobile IMEI unit)
            if ($type !== 'general') {
                $notes = trim("Mobile - $color - $storage/$ram - $cond", ' -/');
                $serial_row = [
                    'product_id'       => $product_id,
                    'imei_number'      => $imei1,
                    'serial_number'    => $imei2 ?: null,
                    'device_serial_no' => $serial ?: null,
                    'phone_condition'  => $cond,
                    'pta_status'       => $pta,
                    'network_note'     => $netnote ?: null,
                    'battery_health'   => $battery ?: null,
                    'warranty_status'  => $warranty ?: null,
                    'accessories'      => $accs ? implode(', ', $accs) : null,
                    'fault_notes'      => $fault ?: null,
                    'supplier_id'      => $supplier_id,
                    'seller_name'      => $s_name ?: null,
                    'seller_cnic'      => $s_cnic ?: null,
                    'seller_phone'     => $s_phone ?: null,
                    'purchase_price'   => $buy,
                    'sale_price'       => $sell,
                    'purchase_id'      => $purchase_id,
                    'status'           => 'available',
                    'notes'            => $notes,
                    'created_at'       => date('Y-m-d'),
                    'updated_at'       => date('Y-m-d'),
                ];
                if ($reuse_id) {
                    // Bought-back unit: revive the old row (keeps its id/history, clears the old sale link)
                    unset($serial_row['created_at']);
                    $serial_row['sale_id'] = null;
                    update('product_serials', $serial_row, $reuse_id);
                } else {
                    insert('product_serials', $serial_row);
                }
            }

            // 5) Money movement (same pattern as view_purchase.php)
            if ($paid > 0) {
                if ($supplier_id) {
                    $sup = getById('suppliers', $supplier_id);
                    $pay_id = insert('supplier_payments', [
                        'supplier_id' => $supplier_id, 'purchase_id' => $purchase_id, 'amount' => $paid,
                        'payment_method' => $pay_mode === 'bank' ? 'bank' : 'cash',
                        'bank_account_id' => $pay_mode === 'bank' ? $bank_id : null,
                        // exact pattern purchase_edit.php uses to find/reverse this payment
                        'description' => "Purchase payment - Invoice $invoice_no",
                        'payment_date' => $p_date, 'created_by' => $user_id, 'created_at' => date('Y-m-d'),
                    ]);
                    $desc = 'Supplier payment - ' . (($sup['contact_person'] ?? '') ?: ($sup['name'] ?? "#$supplier_id")) . " - $full_name";
                    $ref_type = 'supplier_payment'; $ref_id = $pay_id;
                } else {
                    $desc = "Purchase from $s_name - $full_name ($invoice_no)";
                    $ref_type = 'purchase'; $ref_id = $purchase_id;
                }
                if ($pay_mode === 'bank') {
                    recordBankOutflow($pdo, $p_date, $paid, $desc, $ref_type, $ref_id, $user_id, $bank_id);
                } else {
                    recordCashOutflow($pdo, $p_date, $paid, $desc, $ref_type, $ref_id, $user_id);
                }
            }
            // Credit: nothing to post - the purchase total is the supplier payable (see supplier_view.php)

            $pdo->commit();
            $_SESSION['success'] = ($merged
                ? "Stock updated: '$full_name' already existed, quantity +$qty and prices refreshed."
                : "New product '$full_name' created.") . " Invoice $invoice_no."
                . (!empty($warn_below_cost) ? ' WARNING: sale price is below the purchase price.' : '');
            header('Location: add_purchase.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Could not save: ' . $e->getMessage();
        }
    }
}

$cur_type = old('product_type', 'mobile');
$cur_src  = old('source_type', 'supplier');
$cur_pay  = old('payment_mode', 'cash');
$cur_accs = (array)old('accessories', []);

$selected_brand_name = old('brand_name');
if (!$selected_brand_name && old('brand_id')) {
    foreach ($brands as $b) {
        if ((int)$b['id'] === (int)old('brand_id')) {
            $selected_brand_name = $b['name'];
            break;
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-xl-10">

    <?php if ($errors): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <strong><i class="fas fa-exclamation-triangle"></i> Please fix the following:</strong>
        <ul class="mb-0 pl-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
      </div>
    <?php endif; ?>

    <form method="post" id="addProductForm" autocomplete="off" novalidate>

      <!-- ===== Category (mobile only; General toggle disabled) ===== -->
      <?php /* To re-enable General / Accessory items, restore the card header + toggle below:
                    <div class="btn-group btn-group-toggle mb-2" data-toggle="buttons">
            <?php foreach (['mobile' => ['Mobile', 'fa-mobile-alt'], 'general' => ['General / Accessory', 'fa-headphones']] as $k => [$lbl, $ic]): ?>
              <label class="btn btn-outline-primary <?= $cur_type === $k ? 'active' : '' ?>">
                <input type="radio" name="product_type" value="<?= $k ?>" <?= $cur_type === $k ? 'checked' : '' ?>>
                <i class="fas <?= $ic ?>"></i> <?= $lbl ?>
              </label>
            <?php endforeach; ?>
          </div>
          <?php /* Mobile only: type is fixed. The JS below reads input[name=product_type]:checked, so keep a checked radio (hidden). */ ?>
      */ ?>
      <div style="display:none"><input type="radio" name="product_type" value="mobile" checked></div>

      <!-- ===== Basic specs ===== -->
      <div class="card shadow mb-4 step-card">
        <div class="card-header py-3"><span class="step-badge">1</span><h6 class="m-0"><i class="fas fa-mobile-alt"></i> Device Specs</h6></div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group col-md-4 t-mobile">
              <label for="brand_name">Brand <span class="text-danger">*</span></label>
              <div class="brand-chips" id="brandChips" style="display:none">
                <?php foreach ($brands as $b): ?>
                  <button type="button" class="brand-chip" data-id="<?= $b['id'] ?>" data-name="<?= h($b['name']) ?>"><?= h($b['name']) ?></button>
                <?php endforeach; ?>
              </div>
              <input type="text" 
                     name="brand_name" 
                     id="brand_name" 
                     class="form-control" 
                     list="brand_list" 
                     placeholder="Type brand name..." 
                     value="<?= h($selected_brand_name ?? '') ?>" 
                     autocomplete="off">
              <datalist id="brand_list">
                <?php foreach ($brands as $b): ?>
                  <option value="<?= h($b['name']) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <input type="hidden" name="brand_id" id="brand_id" value="<?= h(old('brand_id')) ?>">
            </div>
            <div class="form-group col-md-4">
              <label><span class="lbl-model">Model Name</span> <span class="text-danger">*</span></label>
              <input type="text" name="model_name" class="form-control" value="<?= h(old('model_name')) ?>" placeholder="e.g. iPhone 13 Pro / Galaxy A15">
            </div>
            <div class="form-group col-md-4 t-mobile">
              <label>Color</label>
              <input type="text" name="color" class="form-control" value="<?= h(old('color')) ?>" placeholder="e.g. Sierra Blue">
            </div>
          </div>
          <div class="form-row t-mobile">
            <div class="form-group col-md-4">
              <label>RAM</label>
              <select name="ram" class="form-control"><option value="">-- RAM --</option>
                <?php foreach (option_list('ram') as $r): ?><option <?= old('ram') === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-4">
              <label>Storage</label>
              <select name="storage" class="form-control"><option value="">-- Storage --</option>
                <?php foreach (option_list('storage') as $s): ?><option <?= old('storage') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row t-general">
            <div class="form-group col-md-4">
              <label>Quantity <span class="text-danger">*</span></label>
              <input type="number" name="quantity" min="1" class="form-control" value="<?= h(old('quantity', 1)) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- ===== Unit details (mobile) ===== -->
      <div class="card shadow mb-4 step-card t-mobile">
        <div class="card-header py-3"><span class="step-badge">2</span><h6 class="m-0"><i class="fas fa-barcode"></i> IMEI, PTA &amp; Condition</h6></div>
        <div class="card-body">
          <div class="form-row t-mobile">
            <div class="form-group col-md-4">
              <label>IMEI 1 <span class="text-danger">*</span> <small class="text-muted">(scan or type)</small></label>
              <input type="text" name="imei_1" id="imei_1" class="form-control imei-input" maxlength="15" inputmode="numeric" value="<?= h(old('imei_1')) ?>" placeholder="15 digits" autofocus>
              <small class="form-text" id="imei1_msg"></small>
            </div>
            <div class="form-group col-md-4">
              <label>IMEI 2 <small class="text-muted">(dual SIM / eSIM)</small></label>
              <input type="text" name="imei_2" id="imei_2" class="form-control imei-input" maxlength="15" inputmode="numeric" value="<?= h(old('imei_2')) ?>" placeholder="optional">
              <small class="form-text" id="imei2_msg"></small>
            </div>
            <div class="form-group col-md-4">
              <label>Serial Number</label>
              <input type="text" name="device_serial_no" class="form-control" id="serial_field" value="<?= h(old('device_serial_no')) ?>" placeholder="optional">
            </div>
          </div>
          <div class="form-row t-mobile">
            <div class="form-group col-12">
              <label class="d-block">Phone Condition <span class="text-danger">*</span></label>
              <div class="pill-group">
                <?php foreach (option_list('phone_condition') as $c): ?>
                  <label class="pill-radio"><input type="radio" name="phone_condition" value="<?= h($c) ?>" <?= old('phone_condition') === $c ? 'checked' : '' ?>><span><?= h($c) ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="form-group col-12">
              <label class="d-block">PTA Status <span class="text-danger">*</span></label>
              <div class="pill-group">
                <?php foreach (option_list('pta_status') as $c): ?>
                  <label class="pill-radio"><input type="radio" name="pta_status" value="<?= h($c) ?>" <?= old('pta_status') === $c ? 'checked' : '' ?>><span><?= h($c) ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label>SIM / Network Note</label>
              <input type="text" name="network_note" class="form-control" list="netNotes" value="<?= h(old('network_note')) ?>" placeholder="SIM / network status">
              <datalist id="netNotes"><?php foreach (option_list('network_note') as $nn): ?><option value="<?= h($nn) ?>"><?php endforeach; ?></datalist>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4 t-mobile" id="battery_group">
              <label>Battery Health % <small class="text-muted">(iPhone)</small></label>
              <input type="text" name="battery_health" class="form-control" value="<?= h(old('battery_health')) ?>" placeholder="95% or N/A">
            </div>
            <div class="form-group col-md-4">
              <label>Warranty Status</label>
              <select name="warranty_status" class="form-control">
                <option value="">-- Select --</option>
                <?php foreach (option_list('warranty') as $w): ?><option <?= old('warranty_status') === $w ? 'selected' : '' ?>><?= h($w) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-4">
              <label class="d-block">Accessories Included</label>
              <?php foreach (option_list('accessory') as $i => $a): ?>
                <div class="custom-control custom-checkbox custom-control-inline">
                  <input type="checkbox" class="custom-control-input" id="acc<?= $i ?>" name="accessories[]" value="<?= h($a) ?>" <?= in_array($a, $cur_accs, true) ? 'checked' : '' ?>>
                  <label class="custom-control-label" for="acc<?= $i ?>"><?= h($a) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group mb-0">
            <label>Condition / Fault Notes</label>
            <textarea name="fault_notes" rows="2" class="form-control" placeholder="e.g. TrueTone OK, Face ID OK, back glass cracked"><?= h(old('fault_notes')) ?></textarea>
          </div>
        </div>
      </div>

      <!-- ===== Pricing & purchase source ===== -->
      <div class="card shadow mb-4 step-card">
        <div class="card-header py-3"><span class="step-badge">3</span><h6 class="m-0"><i class="fas fa-rupee-sign"></i> Pricing &amp; Seller Security</h6></div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Purchase Price (PKR) <span class="text-danger">*</span> <small class="text-muted t-general">per unit</small></label>
              <input type="number" name="purchase_price" class="form-control" min="0" step="0.01" value="<?= h(old('purchase_price')) ?>">
            </div>
            <div class="form-group col-md-4">
              <label>Sale Price (PKR) <span class="text-danger">*</span></label>
              <input type="number" name="sale_price" class="form-control" min="0" step="0.01" value="<?= h(old('sale_price')) ?>">
              <div class="mt-2"><span id="profitBadge" class="profit-badge"><i class="fas fa-calculator"></i> Profit: -</span></div>
            </div>
            <div class="form-group col-md-4">
              <label>Purchase Date</label>
              <input type="text" name="purchase_date" class="form-control datepicker" value="<?= h(old('purchase_date', date('Y-m-d'))) ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="d-block">Purchase Source</label>
            <div class="custom-control custom-radio custom-control-inline">
              <input type="radio" id="src_sup" name="source_type" value="supplier" class="custom-control-input" <?= $cur_src === 'supplier' ? 'checked' : '' ?>>
              <label class="custom-control-label" for="src_sup">Existing Supplier</label>
            </div>
            <div class="custom-control custom-radio custom-control-inline">
              <input type="radio" id="src_walk" name="source_type" value="walkin" class="custom-control-input" <?= $cur_src === 'walkin' ? 'checked' : '' ?>>
              <label class="custom-control-label" for="src_walk">Walk-in / Individual Seller</label>
            </div>
          </div>

          <div class="form-row" id="supplier_box">
            <div class="form-group col-md-6">
              <label>Supplier</label>
              <select name="supplier_id" id="supplier_id" class="form-control">
                <option value="">-- Select Supplier --</option>
                <?php foreach ($suppliers as $s): ?>
                  <option value="<?= $s['id'] ?>" <?= (int)old('supplier_id') === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['name'] ?: $s['contact_person']) ?><?= $s['name'] && $s['contact_person'] ? ' (' . h($s['contact_person']) . ')' : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row" id="walkin_box">
            <div class="form-group col-md-4"><label>Seller Name <span class="text-danger">*</span></label><input type="text" name="seller_name" class="form-control" value="<?= h(old('seller_name')) ?>"></div>
            <div class="form-group col-md-4"><label>Seller CNIC <span class="text-danger">*</span></label><input type="text" name="seller_cnic" id="seller_cnic" class="form-control" maxlength="15" inputmode="numeric" value="<?= h(old('seller_cnic')) ?>" placeholder="XXXXX-XXXXXXX-X"></div>
            <div class="form-group col-md-4"><label>Seller Phone</label><input type="text" name="seller_phone" class="form-control" value="<?= h(old('seller_phone')) ?>" placeholder="03XX-XXXXXXX"></div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Payment Mode for Purchase</label>
              <select name="payment_mode" id="payment_mode" class="form-control">
                <option value="cash" <?= $cur_pay === 'cash' ? 'selected' : '' ?>>Cash (deduct from Cash Book)</option>
                <option value="bank" <?= $cur_pay === 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                <option value="credit" <?= $cur_pay === 'credit' ? 'selected' : '' ?>>Credit / Khata (supplier payable)</option>
              </select>
            </div>
            <div class="form-group col-md-4" id="bank_box">
              <label>Bank Account</label>
              <select name="bank_account_id" class="form-control">
                <option value="">-- Select Account --</option>
                <?php foreach ($banks as $b): ?>
                  <option value="<?= $b['id'] ?>" <?= (int)old('bank_account_id') === (int)$b['id'] ? 'selected' : '' ?>><?= h($b['account_name'] . ' - ' . $b['bank_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!$banks): ?><small class="text-muted">No bank account yet - a default one is created automatically.</small><?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="text-right mb-4">
        <button type="reset" class="btn btn-light" onclick="location.href='add_purchase.php';return false;">Clear</button>
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save to Stock</button>
      </div>
    </form>
  </div>
</div>

<script>
$(function () {
  var $form = $('#addProductForm');

  // ---- Category toggle: show only blocks for the chosen type ----
  function applyType() {
    var t = $('input[name=product_type]:checked').val();
    $('.t-mobile, .t-general').hide();
    $('.t-' + t).show();
    $('.lbl-model').text(t === 'general' ? 'Item Name' : 'Model Name');
    applyBattery();
  }
  // Battery health only matters for iPhones / Apple
  function applyBattery() {
    var isMobile = $('input[name=product_type]:checked').val() === 'mobile';
    var brand = ($('#brand_name').val() || '').trim().toLowerCase();
    var apple = (brand === 'apple');
    $('#battery_group').toggle(isMobile && apple);
  }

  var $brandName = $('#brand_name');
  var $brandId   = $('#brand_id');

  // Clicking a brand chip fills the brand name input
  $('#brandChips').on('click', '.brand-chip', function () {
    var val = $(this).attr('data-name') || $(this).text().trim();
    var bid = $(this).attr('data-id') || '';
    $brandName.val(val).trigger('input').trigger('change');
    $brandId.val(bid);
  });

  // Sync active chip when brand_name is typed or changed
  function syncChips() {
    var current = ($brandName.val() || '').trim().toLowerCase();
    var matchedId = '';
    $('#brandChips .brand-chip').each(function () {
      var chipName = ($(this).attr('data-name') || $(this).text()).trim().toLowerCase();
      var isMatch = (chipName === current);
      $(this).toggleClass('active', isMatch);
      if (isMatch) {
        matchedId = $(this).attr('data-id') || '';
      }
    });
    if (matchedId) {
      $brandId.val(matchedId);
    }
    applyBattery();
  }

  $brandName.on('input change keyup', syncChips);
  syncChips();

  // Number the visible step cards 1..n (Device Specs is 1, IMEI card hides for general items)
  function renumberSteps() { $('.step-card').filter(function () { return $(this).css('display') !== 'none'; }).find('.step-badge').each(function (i) { $(this).text(i + 1); }); }
  $('input[name=product_type]').on('change', function () { setTimeout(renumberSteps, 0); });

  $('input[name=product_type]').on('change', applyType);
  $brandName.on('change', applyBattery);

  // ---- Purchase source / payment mode ----
  function applySource() {
    var walk = $('input[name=source_type]:checked').val() === 'walkin';
    $('#walkin_box').toggle(walk);
    $('#supplier_box').toggle(!walk);
    // Credit only makes sense for a supplier
    $('#payment_mode option[value=credit]').prop('disabled', walk);
    if (walk && $('#payment_mode').val() === 'credit') $('#payment_mode').val('cash');
    applyPay();
  }
  function applyPay() { $('#bank_box').toggle($('#payment_mode').val() === 'bank'); }
  $('input[name=source_type]').on('change', applySource);
  $('#payment_mode').on('change', applyPay);

  // ---- IMEI: digits only + live duplicate check ----
  function checkImei($inp, $msg) {
    var v = $inp.val();
    $msg.text('').removeClass('text-danger text-success');
    if (v.length !== 15) { if (v.length) $msg.text(v.length + '/15 digits').addClass('text-muted'); return; }
    $.getJSON('add_purchase.php', { check_imei: v }, function (r) {
      if (r.exists) $msg.text('Already In Stock: ' + (r.product || '')).removeClass('text-muted').addClass('text-danger');
      else $msg.text('IMEI available').removeClass('text-muted').addClass('text-success');
    });
  }
  $('.imei-input').on('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 15);
    checkImei($(this), $(this).siblings('small'));
  });

  // ---- CNIC auto-format XXXXX-XXXXXXX-X ----
  $('#seller_cnic').on('input', function () {
    var d = this.value.replace(/\D/g, '').slice(0, 13), o = d;
    if (d.length > 12) o = d.slice(0, 5) + '-' + d.slice(5, 12) + '-' + d.slice(12);
    else if (d.length > 5) o = d.slice(0, 5) + '-' + d.slice(5);
    this.value = o;
  });

  // ---- Barcode-scanner friendly: Enter never submits ----
  // (scanners send Enter after the code; IMEI 1 -> jump to IMEI 2 -> then Sale Price)
  $form.on('keydown', 'input', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    if (this.id === 'imei_1') $('#imei_2').focus();
    else if (this.id === 'imei_2') $form.find('[name=purchase_price]').focus();
  });

  applyType();
  applySource();
  renumberSteps();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
