<?php
/**
 * New Sale / POS billing window - 3 cards, same step-card layout as Add Product.
 *   1  Customer & invoice details     (registered khata customer or walk-in)
 *   2  Select product / scan IMEI     (AJAX: ajax_get_product.php -> full unit detail preview)
 *   3  Invoice items & payment        (live totals, then POST -> save_sale.php)
 */
session_start();
$page_title = 'New Sale';
$base_url = '../../';
require_once '../../includes/functions.php';

$is_admin = (($_SESSION['user_role'] ?? '') === 'admin');

// Registered customers with their current khata balance (opening + sales - payments - returns)
$customers = $pdo->query("SELECT c.id, c.full_name, c.phone, c.cnic,
    (c.opening_due - c.opening_paid
      + COALESCE((SELECT SUM(s.total_amount) FROM sales s WHERE s.customer_id = c.id AND s.status <> 'cancelled'), 0)
      - COALESCE((SELECT SUM(r.amount) FROM sale_returns r WHERE r.customer_id = c.id), 0)
      - COALESCE((SELECT SUM(p.amount) FROM payments p JOIN sales s2 ON s2.id = p.sale_id WHERE s2.customer_id = c.id AND s2.status <> 'cancelled'), 0)) AS bal
    FROM customers c ORDER BY c.full_name")->fetchAll();
$cust_map = [];
foreach ($customers as $c) {
    $cust_map[$c['id']] = ['phone' => $c['phone'], 'cnic' => $c['cnic'], 'bal' => round((float)$c['bal'], 2)];
}
$bank_accounts = $pdo->query("SELECT id, bank_name, account_name FROM bank_accounts WHERE status = 1 ORDER BY bank_name, account_name")->fetchAll();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once '../../includes/header.php';
?>

<form method="post" action="save_sale.php" id="saleForm" autocomplete="off">
<?= csrf_field() ?>

<!-- ===================== CARD 1: customer & invoice ===================== -->
<div class="card shadow mb-4 step-card">
  <div class="card-header py-3"><span class="step-badge">1</span><h6 class="m-0"><i class="fas fa-user"></i> Customer &amp; Invoice Details</h6></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group col-lg-4">
        <label>Customer <span class="text-danger">*</span></label>
        <select name="customer_id" id="customerSelect" class="form-control" required>
          <option value="">-- Select registered (khata) customer --</option>
          <option value="walkin">+ Walk-in customer (pays in full)</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= $h($c['full_name']) ?><?= $c['phone'] ? ' (' . $h($c['phone']) . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>

        <div id="walkinFields" style="display:none;" class="mt-2">
          <input type="text" name="walkin_name" class="form-control mb-2" placeholder="Walk-in name *">
          <input type="text" name="walkin_phone" class="form-control mb-2" placeholder="Phone">
          <input type="text" name="walkin_cnic" class="form-control cnic" placeholder="CNIC (optional) XXXXX-XXXXXXX-X" maxlength="15">
        </div>
      </div>

      <div class="form-group col-lg-4">
        <label>Customer Details</label>
        <div id="custInfo" class="border rounded p-2 bg-light small" style="min-height:76px;">
          <span class="text-muted">Select a customer to see phone, CNIC and previous khata.</span>
        </div>
      </div>

      <div class="form-group col-lg-2 col-6">
        <label>Invoice Date</label>
        <input type="text" name="sale_date" class="form-control datepicker" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group col-lg-2 col-6">
        <label>Notes</label>
        <input type="text" name="notes" class="form-control" placeholder="Optional">
      </div>
    </div>
  </div>
</div>

<!-- ===================== CARD 2: pick / scan + auto-fetched details ===================== -->
<div class="card shadow mb-4 step-card">
  <div class="card-header py-3"><span class="step-badge">2</span><h6 class="m-0"><i class="fas fa-barcode"></i> Select Product / Scan IMEI</h6></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group col-lg-5" style="position:relative;">
        <label>Scan IMEI <small class="text-muted">(15 digits + Enter, or type the last 5 digits)</small></label>
        <div class="scan-bar">
          <i class="fas fa-barcode"></i>
          <input type="text" id="imeiScan" class="form-control scan-input imei-input" maxlength="15" inputmode="numeric" placeholder="Scan IMEI, or type last 5 digits" autocomplete="off" autofocus>
        </div>
        <div class="scan-results" id="imeiList" style="top:calc(100% - 2px);"></div>
      </div>
      <div class="form-group col-lg-7" style="position:relative;">
        <label>Or search in-stock mobiles / accessories</label>
        <input type="text" id="unitSearch" class="form-control" style="height:3.2rem;" placeholder="Type model, brand, IMEI or accessory name...">
        <div class="scan-results" id="unitList" style="top:calc(100% - 2px);"></div>
      </div>
    </div>

    <div id="unitMsg" class="alert alert-warning py-2" style="display:none;"></div>
    <div class="text-muted small" id="previewHint"><i class="fas fa-info-circle"></i> Scan an IMEI or pick an item - all saved details of that unit appear here before you add it to the bill.</div>

    <!-- Auto-fetched detail card -->
    <div id="unitPreview" class="border rounded p-3 mt-2" style="display:none;background:#fcfdff;">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
          <span class="h5 font-weight-bold mb-0" id="pvTitle"></span>
          <span id="pvStatus" class="ml-2"></span>
        </div>
        <div id="pvBadges"></div>
      </div>
      <div class="row" id="pvGrid"></div>
      <div class="mt-2" id="pvFault" style="display:none;"><span class="text-muted small font-weight-bold">CONDITION / FAULT NOTES</span><div id="pvFaultText" class="small"></div></div>

      <hr class="my-3">
      <div class="form-row align-items-end">
        <?php if ($is_admin): ?>
        <div class="form-group col-6 col-md-3">
          <label>Purchase Cost (PKR) <small class="text-muted">admin</small></label>
          <input type="text" id="pvCost" class="form-control text-mono" readonly>
        </div>
        <?php endif; ?>
        <div class="form-group col-6 col-md-3">
          <label>Final Sale Price (PKR) <span class="text-danger">*</span></label>
          <input type="number" id="finalPrice" class="form-control text-mono font-weight-bold" min="0" step="0.01">
        </div>
        <div class="form-group col-6 col-md-2" id="qtyGroup" style="display:none;">
          <label>Qty <small class="text-muted" id="stockNote"></small></label>
          <input type="number" id="unitQty" class="form-control" min="1" value="1">
        </div>
        <div class="form-group col-12 col-md-4">
          <button type="button" id="addToBill" class="btn btn-success btn-block btn-lg"><i class="fas fa-cart-plus"></i> Add to Bill</button>
        </div>
      </div>
      <div id="belowCost" class="text-danger small font-weight-bold" style="display:none;"><i class="fas fa-exclamation-triangle"></i> <span></span></div>
    </div>
  </div>
</div>

<!-- ===================== CARD 3: items + payment ===================== -->
<div class="card shadow mb-4 step-card">
  <div class="card-header py-3"><span class="step-badge">3</span><h6 class="m-0"><i class="fas fa-file-invoice"></i> Invoice Items &amp; Payment <span class="badge badge-primary ml-1" id="itemCount">0</span></h6></div>
  <div class="card-body">
    <div class="table-responsive" style="max-height:none;">
      <table class="table table-sm table-hover" id="itemsTable">
        <thead>
          <tr><th>#</th><th>Item</th><th>IMEI 1 / IMEI 2</th><th>PTA</th><th>Condition</th><th>Warranty / Accessories</th><th style="width:130px;" class="text-right">Price</th><th style="width:36px;"></th></tr>
        </thead>
        <tbody id="itemsBody"></tbody>
      </table>
      <div class="empty-state" id="emptyItems" style="padding:1.5rem;">
        <svg viewBox="0 0 96 96" fill="none" stroke="#cbd5e1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 20h10l8 38h36l8-28H28"/><circle cx="38" cy="72" r="5"/><circle cx="64" cy="72" r="5"/></svg>
        <div class="t">No items on this bill yet</div>
      </div>
    </div>

    <div class="row mt-3">
      <div class="col-lg-6 ml-auto">
        <div class="p-3 rounded" style="background:#f8fafc;border:1px solid var(--line);">
          <div class="sum-row"><span class="text-muted">Subtotal</span><span class="v" id="subtotalVal">0.00</span></div>
          <div class="sum-row"><span class="text-muted">Discount (PKR)</span>
            <input type="number" name="discount_amount" id="discountAmount" class="form-control form-control-sm text-right text-mono" style="width:140px;" min="0" step="0.01" value="0"></div>
          <div class="total-box my-2"><div class="lbl">Grand Total (PKR)</div><div class="big" id="grandTotal">0.00</div></div>

          <div class="form-row">
            <div class="form-group col-sm-6 mb-2">
              <label class="small">Payment Method</label>
              <select name="payment_method" id="paymentMethod" class="form-control">
                <option value="cash">Cash</option>
                <option value="bank">Bank Transfer</option>
                <option value="khata" id="optKhata">Khata (pay later)</option>
              </select>
            </div>
            <div class="form-group col-sm-6 mb-2">
              <label class="small">Cash Received (PKR)</label>
              <input type="number" name="received_amount" id="receivedAmount" class="form-control text-mono font-weight-bold" min="0" step="0.01" value="0">
            </div>
          </div>
          <div class="form-group mb-2" id="bankRow" style="display:none;">
            <label class="small">Bank Account</label>
            <select name="bank_account_id" id="bankAccount" class="form-control">
              <option value="">-- Select account --</option>
              <?php foreach ($bank_accounts as $b): ?><option value="<?= (int)$b['id'] ?>"><?= $h($b['bank_name'] . ' - ' . $b['account_name']) ?></option><?php endforeach; ?>
            </select>
          </div>

          <div class="sum-row"><span class="font-weight-bold">Balance Due (Khata)</span><span class="v" style="font-size:1.2rem;" id="dueVal">0.00</span></div>
          <div class="sum-row small" id="afterBillRow" style="display:none;"><span class="text-muted">Customer's khata after this bill</span><span class="v" id="afterBillVal">0.00</span></div>
          <div id="walkinWarn" class="text-danger small mt-1" style="display:none;"><i class="fas fa-exclamation-circle"></i> Walk-in customers must pay in full. Register the customer to sell on khata.</div>

          <button type="submit" id="submitSale" class="btn btn-primary btn-block btn-lg mt-3" disabled><i class="fas fa-save"></i> Save &amp; Print Invoice</button>
        </div>
      </div>
    </div>
  </div>
</div>
</form>

<script>
var CUST = <?= json_encode($cust_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
var IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;
var AJAX_URL = 'ajax_get_product.php';

$(function () {
  var esc = PosUI.esc, money = PosUI.money, toast = PosUI.toast;
  var current = null;                         // unit currently shown in the preview
  var receivedTouched = false;
  function n(v) { return parseFloat(v) || 0; }
  function f2(v) { return (Math.round(v * 100) / 100).toFixed(2); }
  function label(u) { return $.trim((u.brand ? u.brand + ' ' : '') + (u.model || u.name || '')); }

  /* ---------- card 1: customer ---------- */
  function showCustomer() {
    var v = $('#customerSelect').val(), $i = $('#custInfo');
    $('#walkinFields').toggle(v === 'walkin');
    $('#optKhata').prop('disabled', v === 'walkin');
    if (v === 'walkin' && $('#paymentMethod').val() === 'khata') $('#paymentMethod').val('cash');
    if (!v) { $i.html('<span class="text-muted">Select a customer to see phone, CNIC and previous khata.</span>'); }
    else if (v === 'walkin') { $i.html('<span class="text-muted">Walk-in customer - must pay in full. Enter name and phone on the left.</span>'); }
    else {
      var c = CUST[v] || {}, b = parseFloat(c.bal) || 0;
      $i.empty().append(
        $('<div>').append('<b>Phone:</b> ', $('<span class="text-mono">').text(c.phone || '-')),
        $('<div>').append('<b>CNIC:</b> ', $('<span class="text-mono">').text(c.cnic || '-')),
        $('<div class="mt-1">').append('<b>Previous Khata:</b> ',
          $('<span class="khata-badge ' + (b > 0 ? 'due' : 'clear') + '">').text(b > 0 ? money(b) + ' due' : 'Clear')));
    }
    recalc();
  }
  $('#customerSelect').on('change', showCustomer);

  /* ---------- card 2: fetch + preview ---------- */
  function condBadge(c) {
    if (!c) return '';
    var cls = /box pack|open box/i.test(c) ? 'pta-ok' : /kit/i.test(c) ? 'pta-jv' : 'pta-non';
    return '<span class="pta-badge ' + cls + '">' + esc(c) + '</span>';
  }
  function msg(t, type) { $('#unitMsg').attr('class', 'alert alert-' + (type || 'warning') + ' py-2').text(t).show(); }
  function clearMsg() { $('#unitMsg').hide(); }

  function field(lbl, valHtml, mono) {
    if (valHtml === null || valHtml === undefined || valHtml === '') return '';
    return '<div class="col-6 col-md-3 mb-2"><div class="text-muted small font-weight-bold" style="letter-spacing:.06em;">' + lbl.toUpperCase() +
           '</div><div class="' + (mono ? 'text-mono imei' : '') + '">' + valHtml + '</div></div>';
  }
  function renderPreview(u) {
    current = u;
    var inCart = u.serial_id && $('#itemsBody input[name="serial_ids[]"]').filter(function () { return this.value == u.serial_id; }).length > 0;
    $('#pvTitle').text(label(u));
    var st = u.in_stock ? '<span class="status-pill status-in">In Stock</span>' : '<span class="status-pill status-sold">' + esc(u.status || 'Unavailable') + '</span>';
    $('#pvStatus').html(st);
    $('#pvBadges').html((u.pta ? PosUI.pta(u.pta) + ' ' : '') + condBadge(u.condition));
    var acc = (u.accessories || []).map(function (a) { return '<span class="badge badge-light border mr-1">' + esc(a) + '</span>'; }).join('');
    var g = '';
    g += field('Brand', esc(u.brand));
    g += field('Model', esc(u.model));
    g += field('RAM', esc(u.ram));
    g += field('Storage', esc(u.storage));
    g += field('Color', esc(u.color));
    g += field('IMEI 1', esc(u.imei1), true);
    g += field('IMEI 2', esc(u.imei2), true);
    g += field('Serial Number', esc(u.serial_no), true);
    g += field('Phone Condition', condBadge(u.condition));
    g += field('PTA Status', u.pta ? PosUI.pta(u.pta) + ' <span class="small text-muted">' + esc(u.pta) + '</span>' : '');
    g += field('SIM / Network', esc(u.network));
    g += field('Battery Health', esc(u.battery));
    g += field('Warranty', esc(u.warranty));
    g += field('Accessories', acc);
    if (u.kind === 'product') { g += field('Item Code', esc(u.code), true); g += field('In Stock', esc(u.stock)); }
    $('#pvGrid').html(g);
    $('#pvFault').toggle(!!u.fault_notes); $('#pvFaultText').text(u.fault_notes || '');
    if (IS_ADMIN) $('#pvCost').val(u.cost > 0 ? f2(u.cost) : 'not recorded');
    $('#finalPrice').val(u.price > 0 ? f2(u.price) : '');
    $('#qtyGroup').toggle(u.kind === 'product'); $('#unitQty').val(1);
    $('#stockNote').text(u.kind === 'product' ? '(max ' + u.stock + ')' : '');
    $('#belowCost').hide();
    $('#previewHint').hide(); $('#unitPreview').show();
    if (!u.in_stock) { msg('This unit is not available: ' + (u.status || 'out of stock') + '. It cannot be added to a bill.', 'danger'); }
    else if (inCart) { msg('This IMEI is already on the bill.', 'warning'); }
    else clearMsg();
    $('#addToBill').prop('disabled', !u.in_stock || inCart);
    checkBelowCost();
  }
  function checkBelowCost() {
    if (!current) return;
    var p = n($('#finalPrice').val()), c = current.cost || 0;
    if (c > 0 && p > 0 && p < c) { $('#belowCost span').text('Sale price is Rs. ' + f2(c - p) + ' BELOW purchase cost (' + (IS_ADMIN ? money(c) : 'cost') + ').'); $('#belowCost').show(); }
    else $('#belowCost').hide();
  }
  $('#finalPrice').on('input', checkBelowCost);

  function fetchUnit(params) {
    clearMsg();
    $.getJSON(AJAX_URL, params, function (d) {
      if (!d.found) { msg('Nothing found for that selection.'); return; }
      renderPreview(d.unit);
    }).fail(function () { msg('Could not load details - check your connection / login.', 'danger'); });
  }

  // scanner: Enter fetches, never submits
  $('#imeiScan').on('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 15); });
  $('#imeiScan').on('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var v = this.value;
    if (v.length === 15) { $imeiList.removeClass('show'); fetchUnit({ imei: v }); return; }
    // partial IMEI: Enter picks the highlighted (or first) suggestion
    if (v.length >= 5 && imeiItems.length) { pickImei(imeiItems[imeiActive >= 0 ? imeiActive : 0]); return; }
    toast(v.length >= 5 ? 'No saved IMEI ends with ' + v : 'Type the full 15-digit IMEI or at least the last 5 digits (' + v.length + '/15)', 'warn');
  });

  // last-digits lookup: type the last 5+ digits -> every saved IMEI 1 / IMEI 2 ending with them
  var $imeiList = $('#imeiList'), imeiItems = [], imeiActive = -1, imeiReq = 0;
  function renderImei(d, digits) {
    imeiItems = d.items || []; imeiActive = -1;
    var h = '';
    imeiItems.forEach(function (it, i) {
      var shown = it.matched === 'imei2' ? it.imei2 : it.imei, other = it.matched === 'imei2' ? it.imei : it.imei2;
      var head = shown ? esc(shown.slice(0, shown.length - digits.length)) + '<b style="color:var(--primary)">' + esc(shown.slice(-digits.length)) + '</b>' : '';
      h += '<div class="sr-item" data-i="' + i + '"><div><div class="sr-title">' + esc(it.title) + '</div><div class="sr-meta"><span>' + esc(it.sub || '') + '</span>' +
           (it.pta ? PosUI.pta(it.pta) : '') + '<span class="imei">' + head + (it.matched === 'imei2' ? ' (IMEI 2)' : '') + '</span>' +
           (other ? '<span class="imei text-muted">' + esc(other) + '</span>' : '') +
           (it.in_stock ? '' : '<span class="status-pill status-sold">' + esc(it.status) + '</span>') + '</div></div><div class="sr-price">' + money(it.price) + '</div></div>';
    });
    $imeiList.html(h || '<div class="sr-empty">No saved IMEI ends with ' + esc(digits) + '</div>').addClass('show');
  }
  var loadImei = PosUI.debounce(function () {
    var digits = $('#imeiScan').val();
    if (digits.length < 5 || digits.length >= 15) { $imeiList.removeClass('show'); imeiItems = []; return; }
    var my = ++imeiReq;
    $.getJSON(AJAX_URL, { last: digits }, function (d) { if (my === imeiReq) renderImei(d, digits); });
  }, 150);
  $('#imeiScan').on('input', loadImei);
  function pickImei(it) {
    if (!it) return;
    $imeiList.removeClass('show');
    $('#imeiScan').val(it.matched === 'imei2' ? it.imei2 : it.imei);
    fetchUnit({ serial_id: it.serial_id });
  }
  $imeiList.on('click', '.sr-item', function () { pickImei(imeiItems[$(this).data('i')]); });
  $('#imeiScan').on('keydown', function (e) {
    var $r = $imeiList.find('.sr-item');
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && $r.length) {
      e.preventDefault();
      imeiActive = (imeiActive + (e.key === 'ArrowDown' ? 1 : -1) + $r.length) % $r.length;
      var el = $r.removeClass('active').eq(imeiActive).addClass('active')[0]; if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Escape') { $imeiList.removeClass('show'); }
  });
  $(document).on('click', function (e) { if (!$(e.target).closest('#imeiScan, #imeiList').length) $imeiList.removeClass('show'); });

  // searchable in-stock list
  var $list = $('#unitList'), items = [], listReq = 0, activeIdx = -1;
  function renderList(d) {
    items = d.items || []; activeIdx = -1;
    var h = '';
    items.forEach(function (it, i) {
      h += '<div class="sr-item" data-i="' + i + '"><div><div class="sr-title">' + esc(it.title) + '</div><div class="sr-meta"><span>' + esc(it.sub || '') + '</span>' +
           (it.pta ? PosUI.pta(it.pta) : '') + (it.imei ? '<span class="imei">' + esc(it.imei) + '</span>' : '') + '</div></div><div class="sr-price">' + money(it.price) + '</div></div>';
    });
    $list.html(h || '<div class="sr-empty">No in-stock item matches</div>').addClass('show');
  }
  var loadList = PosUI.debounce(function () {
    var my = ++listReq;
    $.getJSON(AJAX_URL, { list: 1, q: $.trim($('#unitSearch').val()) }, function (d) { if (my === listReq) renderList(d); });
  }, 180);
  $('#unitSearch').on('focus input', loadList);
  function pickItem(it) {
    if (!it) return;
    $list.removeClass('show');
    $('#unitSearch').val(it.title);
    fetchUnit(it.kind === 'serial' ? { serial_id: it.serial_id } : { product_id: it.product_id });
  }
  $list.on('click', '.sr-item', function () { pickItem(items[$(this).data('i')]); });
  $('#unitSearch').on('keydown', function (e) {
    var $r = $list.find('.sr-item');
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault(); if (!$r.length) return;
      activeIdx = (activeIdx + (e.key === 'ArrowDown' ? 1 : -1) + $r.length) % $r.length;
      var el = $r.removeClass('active').eq(activeIdx).addClass('active')[0]; if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      e.preventDefault(); pickItem(items[activeIdx >= 0 ? activeIdx : 0]);
    } else if (e.key === 'Escape') { $list.removeClass('show'); }
  });
  $(document).on('click', function (e) { if (!$(e.target).closest('#unitSearch, #unitList').length) $list.removeClass('show'); });

  /* ---------- add to bill ---------- */
  function addToBill() {
    var u = current; if (!u || !u.in_stock) return;
    var price = n($('#finalPrice').val()), qty = u.kind === 'product' ? Math.max(1, parseInt($('#unitQty').val()) || 1) : 1;
    if (!(price > 0)) { toast('Enter a final sale price', 'warn'); $('#finalPrice').focus(); return; }
    if (u.kind === 'product' && qty > u.stock) { toast('Only ' + u.stock + ' in stock', 'warn'); return; }
    if (u.serial_id && $('#itemsBody input[name="serial_ids[]"]').filter(function () { return this.value == u.serial_id; }).length) {
      toast('This IMEI is already on the bill', 'warn'); return;
    }
    if (u.cost > 0 && price < u.cost && !confirm('Sale price Rs. ' + f2(price) + ' is LOWER than the purchase cost' + (IS_ADMIN ? ' Rs. ' + f2(u.cost) : '') + '.\nSell at a loss of Rs. ' + f2((u.cost - price) * qty) + '?')) return;

    var $tr = $('<tr class="item-row">').attr('data-cost', u.cost || 0);
    var $item = $('<td>');
    $item.append($('<div class="font-weight-bold">').text(label(u)),
                 $('<div class="small text-muted">').text([u.storage, u.ram, u.color].filter(Boolean).join(' / ')));
    $item.append($('<input type="hidden" name="item_description[]">').val(label(u)),
                 $('<input type="hidden" name="product_ids[]">').val(u.product_id),
                 $('<input type="hidden" name="serial_ids[]">').val(u.serial_id || 0));
    if (u.kind === 'product') {
      $item.append($('<input type="number" name="quantity[]" class="form-control form-control-sm qty-input mt-1" style="width:80px;" min="1">').attr('max', u.stock).val(qty));
    } else { $item.append($('<input type="hidden" name="quantity[]">').val(1)); }
    $tr.append('<td class="row-no align-middle"></td>', $item);
    $tr.append($('<td class="align-middle">').html(u.imei1 ? '<div class="imei">' + esc(u.imei1) + '</div>' + (u.imei2 ? '<div class="imei text-muted small">' + esc(u.imei2) + '</div>' : '') : '<span class="text-muted">-</span>'));
    $tr.append($('<td class="align-middle">').html(u.pta ? PosUI.pta(u.pta) : '<span class="text-muted">-</span>'));
    $tr.append($('<td class="align-middle">').html(condBadge(u.condition) || '<span class="text-muted">-</span>'));
    var wa = $('<td class="align-middle small">');
    wa.append($('<div>').text(u.warranty || 'No warranty noted'));
    if ((u.accessories || []).length) wa.append($('<div class="text-muted">').text(u.accessories.join(', ')));
    $tr.append(wa);
    $tr.append($('<td class="align-middle text-right">').append($('<input type="number" name="price[]" class="form-control form-control-sm price-input text-right text-mono" min="0" step="0.01">').val(f2(price))));
    $tr.append('<td class="align-middle text-center"><span class="remove-item" title="Remove"><i class="fas fa-times"></i></span></td>');
    $('#itemsBody').append($tr);

    current = null; $('#unitPreview').hide(); $('#previewHint').show(); clearMsg();
    $('#imeiScan, #unitSearch').val(''); $('#imeiScan').focus();
    toast('Added: ' + label(u));
    recalc();
  }
  $('#addToBill').on('click', addToBill);
  $('#finalPrice, #unitQty').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addToBill(); } });

  /* ---------- card 3: totals ---------- */
  function recalc() {
    var sub = 0, cnt = 0;
    $('#itemsBody .item-row').each(function (i) {
      var q = $(this).find('.qty-input').length ? n($(this).find('.qty-input').val()) : 1, p = n($(this).find('.price-input').val());
      sub += q * p; cnt++; $(this).find('.row-no').text(i + 1);
    });
    var disc = Math.min(Math.max(0, n($('#discountAmount').val())), sub), total = Math.max(0, sub - disc);
    var method = $('#paymentMethod').val();
    $('#bankRow').toggle(method === 'bank');
    if (method === 'khata') { $('#receivedAmount').val('0').prop('readonly', true); }
    else {
      $('#receivedAmount').prop('readonly', false);
      if (!receivedTouched) $('#receivedAmount').val(f2(total));
    }
    var rec = Math.max(0, n($('#receivedAmount').val()));
    if (rec > total) { rec = total; $('#receivedAmount').val(f2(rec)); }
    var due = Math.max(0, total - rec);
    $('#subtotalVal').text(f2(sub)); $('#grandTotal').text(f2(total));
    $('#dueVal').text(f2(due)).css('color', due > 0 ? 'var(--danger)' : 'var(--success)');
    $('#itemCount').text(cnt); $('#emptyItems').toggle(cnt === 0);

    var cust = $('#customerSelect').val(), walk = cust === 'walkin';
    if (cust && !walk && CUST[cust]) { $('#afterBillRow').show(); $('#afterBillVal').text(f2((parseFloat(CUST[cust].bal) || 0) + due)); } else { $('#afterBillRow').hide(); }
    var walkBlocked = walk && due > 0.004;
    $('#walkinWarn').toggle(walkBlocked);
    $('#submitSale').prop('disabled', cnt === 0 || walkBlocked);
  }
  $('#itemsBody').on('input', '.qty-input, .price-input', recalc);
  $('#itemsBody').on('click', '.remove-item', function () { $(this).closest('tr').remove(); recalc(); });
  $('#discountAmount').on('input', recalc);
  $('#receivedAmount').on('input', function () { receivedTouched = true; recalc(); });
  $('#paymentMethod').on('change', function () { receivedTouched = false; recalc(); });

  /* ---------- form guards ---------- */
  $('#saleForm').on('keydown', 'input', function (e) { if (e.key === 'Enter') e.preventDefault(); });   // scanner Enter never submits the bill
  $('#saleForm').on('submit', function (e) {
    var cust = $('#customerSelect').val();
    if (!$('#itemsBody .item-row').length) { e.preventDefault(); toast('Add at least one item', 'warn'); return; }
    if (!cust) { e.preventDefault(); toast('Select a customer', 'warn'); $('#customerSelect').focus(); return; }
    if (cust === 'walkin' && !$.trim($('[name=walkin_name]').val())) { e.preventDefault(); toast('Enter the walk-in name', 'warn'); return; }
    if ($('#paymentMethod').val() === 'bank' && !$('#bankAccount').val()) { e.preventDefault(); toast('Select the bank account', 'warn'); return; }
    $('#submitSale').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');   // no double invoice
  });

  showCustomer(); recalc();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
