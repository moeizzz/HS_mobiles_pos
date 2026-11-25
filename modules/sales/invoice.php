<?php
/**
 * Invoice print: A4 and 80mm thermal receipt in one file.
 *   invoice.php?id=12               -> screen preview (toggle A4 / 80mm, choice remembered)
 *   invoice.php?id=12&print=1       -> opens the print dialog immediately
 *   invoice.php?id=12&fmt=80        -> start in thermal mode
 */
session_start();
$id = (int)($_GET['id'] ?? 0);
$is_print = isset($_GET['print']);
$fmt = ($_GET['fmt'] ?? '') === '80' ? '80' : 'a4';
require_once '../../includes/functions.php';

// ---- Shop details & terms: stored in the database (Settings page / tables settings) ----
$SHOP = ['name' => setting('shop_name', 'HS Mobiles'), 'address' => setting('shop_address'), 'phones' => setting('shop_phone')];
$TERMS = invoice_terms();

$sale = getById('sales', $id);
if (!$sale) {
    $_SESSION['error'] = 'Invoice not found.';
    header("Location: invoices.php");
    exit;
}
$cid = (int)$sale['customer_id'];
$customer = getById('customers', $cid);
$items = getWhere('sale_items', 'sale_id', $id, 'id ASC');

// Units sold on this invoice (IMEI, PTA, warranty)
$st = $pdo->prepare("SELECT s.* FROM product_serials s WHERE s.sale_id = ? ORDER BY s.id");
$st->execute([$id]);
$serial_by_id = $serial_by_product = [];
foreach ($st->fetchAll() as $sn) {
    $serial_by_id[$sn['id']] = $sn;
    $serial_by_product[$sn['product_id']][] = $sn;
}

$pay_stmt = $pdo->prepare("SELECT * FROM payments WHERE sale_id = ? ORDER BY payment_date ASC, id ASC");
$pay_stmt->execute([$id]);
$payments = $pay_stmt->fetchAll();
$total_paid = array_sum(array_map(fn($p) => (float)$p['amount'], $payments));

// ---- Khata at the time of this invoice (point-in-time, prepared statements) ----
// Previous khata = opening balance + earlier invoices - what was paid on them - returns against them
$q = $pdo->prepare("SELECT opening_due - opening_paid FROM customers WHERE id = ?");
$q->execute([$cid]);
$opening = (float)$q->fetchColumn();
$q = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE customer_id = ? AND id < ? AND status <> 'cancelled'");
$q->execute([$cid, $id]);
$prev_sales = (float)$q->fetchColumn();
$q = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN sales s ON s.id = p.sale_id WHERE s.customer_id = ? AND s.id < ? AND s.status <> 'cancelled'");
$q->execute([$cid, $id]);
$prev_paid = (float)$q->fetchColumn();
$q = $pdo->prepare("SELECT COALESCE(SUM(r.amount),0) FROM sale_returns r JOIN sales s ON s.id = r.sale_id WHERE r.customer_id = ? AND s.id < ?");
$q->execute([$cid, $id]);
$prev_returns = (float)$q->fetchColumn();
$previous_khata = round($opening + $prev_sales - $prev_paid - $prev_returns, 2);

$q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM sale_returns WHERE sale_id = ?");
$q->execute([$id]);
$this_returns = (float)$q->fetchColumn();
$remaining = round($previous_khata + (float)$sale['total_amount'] - $total_paid - $this_returns, 2);

$discount = (float)($sale['discount_amount'] ?? 0);

// Build print rows once, used by both layouts
$rows = [];
foreach ($items as $it) {
    $prod = !empty($it['product_id']) ? getById('products', $it['product_id']) : null;
    $name = preg_replace('/\s*\[IMEI:[^\]]*\]\s*/', '', $it['item_description'] ?: ($prod['name'] ?? 'Item'));
    $sn = null;
    if (!empty($it['serial_id']) && isset($serial_by_id[$it['serial_id']])) $sn = $serial_by_id[$it['serial_id']];
    elseif ($prod && !empty($serial_by_product[$prod['id']])) $sn = array_shift($serial_by_product[$prod['id']]);   // older invoices without serial_id
    $rows[] = ['name' => $name, 'qty' => (int)$it['quantity'], 'price' => (float)$it['price'], 'total' => (float)$it['subtotal'], 'sn' => $sn];
}

function pta_class($s) {
    if (preg_match('/jv|locked|patch|cpid/i', (string)$s)) return 'pta-jv';
    if (preg_match('/non/i', (string)$s)) return 'pta-non';
    return 'pta-ok';
}
function pta_short($s) {
    if (preg_match('/jv|locked/i', (string)$s)) return 'JV/Locked';
    if (preg_match('/patch|cpid/i', (string)$s)) return 'Patch/CPID';
    if (preg_match('/non/i', (string)$s)) return 'Non-PTA';
    return 'PTA Approved';
}
function rs($n) { return number_format((float)$n, 2); }
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$title = 'Invoice ' . $sale['invoice_no'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($title) ?> | <?= $h($SHOP['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/pos-modern.css">
<style>
  body { background: #e8ecf1; }
  .toolbar { text-align: center; margin: 16px 0; }

  /* ================= A4 invoice ================= */
  .receipt-a4 { max-width: 794px; margin: 0 auto 30px; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 26px 30px; box-shadow: 0 8px 30px rgba(15,23,42,.1); color: #000; }
  .a4-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #000; padding-bottom: 12px; margin-bottom: 14px; }
  .a4-logo { display: block; height: 44px; width: auto; max-width: 190px; object-fit: contain; margin-bottom: 4px; }
  .a4-shop { font-size: 1.35rem; font-weight: 800; letter-spacing: .01em; line-height: 1.15; }
  .a4-sub { font-size: .82rem; color: #334155; }
  .a4-inv { text-align: right; }
  .a4-inv .lbl { font-size: .66rem; letter-spacing: .12em; font-weight: 800; text-transform: uppercase; }
  .a4-inv .no { font-family: var(--font-mono); font-size: 1.15rem; font-weight: 800; }
  .a4-two { display: flex; gap: 16px; margin-bottom: 14px; }
  .a4-box { flex: 1; border: 1px solid #000; border-radius: 6px; padding: 8px 12px; font-size: .84rem; }
  .a4-box .t { font-size: .64rem; letter-spacing: .12em; font-weight: 800; text-transform: uppercase; color: #475569; margin-bottom: 3px; }
  .a4-box .r { display: flex; gap: 6px; }
  .a4-box .r b { min-width: 62px; }
  table.items { width: 100%; border-collapse: collapse; font-size: .82rem; }
  table.items th { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 6px; text-transform: uppercase; font-size: .66rem; letter-spacing: .06em; text-align: left; background: #f1f5f9; }
  table.items td { border-bottom: 1px solid #94a3b8; padding: 7px 6px; vertical-align: top; }
  table.items .n { text-align: right; font-family: var(--font-mono); white-space: nowrap; }
  .unit-line { font-size: .74rem; margin-top: 3px; color: #1e293b; }
  .unit-line .imei { font-weight: 700; }
  .a4-bottom { display: flex; gap: 16px; margin-top: 14px; align-items: flex-start; }
  .a4-bottom .terms { flex: 1; font-size: .72rem; color: #334155; }
  .a4-bottom .terms ol { padding-left: 16px; margin: 4px 0 0; }
  .totals { width: 290px; border: 1px solid #000; border-radius: 6px; padding: 6px 12px; font-size: .86rem; }
  .totals .row2 { display: flex; justify-content: space-between; padding: 2px 0; }
  .totals .row2 .v { font-family: var(--font-mono); font-weight: 700; }
  .totals .grand { border-top: 2px solid #000; margin-top: 4px; padding-top: 4px; font-size: 1rem; font-weight: 800; }
  .khata-strip { display: flex; justify-content: space-between; border: 2px solid #000; border-radius: 6px; margin-top: 12px; padding: 8px 14px; font-size: .86rem; font-weight: 700; }
  .khata-strip .v { font-family: var(--font-mono); }
  .sign-row { display: flex; justify-content: space-between; margin-top: 46px; font-size: .74rem; }
  .sign-row span { border-top: 1px solid #000; padding-top: 4px; width: 30%; text-align: center; }
  .thanks { text-align: center; margin-top: 16px; font-size: .78rem; font-weight: 700; }

  /* ================= 80mm thermal receipt ================= */
  .receipt-80 { width: 80mm; max-width: 100%; margin: 0 auto 30px; background: #fff; padding: 3mm 3.5mm; font-family: var(--font-mono); font-size: 9.5pt; line-height: 1.32; color: #000; box-shadow: 0 8px 30px rgba(15,23,42,.12); }
  .receipt-80 .c { text-align: center; }
  .r80-logo { display: block; max-width: 46mm; max-height: 14mm; width: auto; height: auto; margin: 0 auto 4px; }
  .receipt-80 .shop { font-size: 11.5pt; font-weight: 800; line-height: 1.15; }
  .receipt-80 .sm { font-size: 8pt; }
  .receipt-80 hr { border: 0; border-top: 1px dashed #000; margin: 4px 0; }
  .receipt-80 .kv { display: flex; justify-content: space-between; gap: 6px; }
  .receipt-80 .kv.b { font-weight: 800; }
  .receipt-80 .it { margin: 3px 0; }
  .receipt-80 .it .nm { font-weight: 700; word-break: break-word; }
  .receipt-80 .it .ln { display: flex; justify-content: space-between; }
  .receipt-80 .it .sub { font-size: 8pt; word-break: break-all; }
  .receipt-80 .tag { border: 1px solid #000; padding: 0 3px; font-size: 7.5pt; font-weight: 800; border-radius: 2px; }
  .receipt-80 .terms { font-size: 7.5pt; }

  /* screen: show only the active format; print: same, plus page setup */
  body.fmt-a4 .receipt-80 { display: none; }
  body.fmt-80 .receipt-a4 { display: none; }

  @media print {
    body { background: #fff !important; }
    .toolbar { display: none !important; }
    .receipt-a4, .receipt-80 { box-shadow: none !important; margin: 0 auto !important; border-radius: 0 !important; }
    body.fmt-a4 .receipt-a4 { border: 0 !important; max-width: 100%; padding: 0; }
    .a4-head, .totals, .khata-strip, .a4-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    table.items th { background: #fff !important; }
    table.items { page-break-inside: auto; }
    table.items tr { page-break-inside: avoid; }
    table.items thead { display: table-header-group; }
    td, th { word-break: break-word; overflow-wrap: anywhere; }
    body.fmt-80 { width: 80mm; }
    .r80-logo { filter: grayscale(100%) contrast(150%); }
  }
</style>
<style id="pageSize">@media print { @page { size: A4; margin: 10mm; } }</style>
</head>
<body class="fmt-<?= $fmt ?>">

<div class="toolbar no-print">
  <div class="btn-group mr-2" role="group">
    <button type="button" class="btn btn-sm btn-outline-primary" data-fmt="a4"><i class="fas fa-file-alt"></i> A4</button>
    <button type="button" class="btn btn-sm btn-outline-primary" data-fmt="80"><i class="fas fa-receipt"></i> 80mm Thermal</button>
  </div>
  <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  <a href="invoices.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<!-- =============== A4 =============== -->
<div class="receipt-a4">
  <div class="a4-head">
    <div>
      <?= shop_logo_img('../../', 'class="a4-logo"') ?>
      <div class="a4-shop"><?= $h($SHOP['name']) ?></div>
      <?php if ($SHOP['address'] !== ''): ?><div class="a4-sub"><?= $h($SHOP['address']) ?></div><?php endif; ?>
      <?php if ($SHOP['phones'] !== ''): ?><div class="a4-sub">Cell: <?= $h($SHOP['phones']) ?></div><?php endif; ?>
    </div>
    <div class="a4-inv">
      <div class="lbl">Sales Invoice</div>
      <div class="no"><?= $h($sale['invoice_no']) ?></div>
      <div class="a4-sub"><?= date('d M Y', strtotime($sale['sale_date'])) ?></div>
    </div>
  </div>

  <div class="a4-two">
    <div class="a4-box">
      <div class="t">Customer</div>
      <div class="r"><b>Name</b><span><?= $h($customer['full_name'] ?? 'N/A') ?></span></div>
      <div class="r"><b>Phone</b><span class="mono"><?= $h($customer['phone'] ?? '') ?></span></div>
      <div class="r"><b>CNIC</b><span class="mono"><?= $h($customer['cnic'] ?? 'N/A') ?></span></div>
      <?php if (!empty($customer['address'])): ?><div class="r"><b>Address</b><span><?= $h($customer['address']) ?></span></div><?php endif; ?>
    </div>
    <div class="a4-box" style="max-width:240px;">
      <div class="t">Payment</div>
      <div class="r"><b>Status</b><span><?= $h(ucfirst($sale['payment_status'])) ?></span></div>
      <div class="r"><b>Method</b><span><?= $h(ucfirst((string)($sale['payment_method'] ?? '-'))) ?></span></div>
      <?php if (!empty($sale['notes'])): ?><div class="r"><b>Note</b><span><?= $h($sale['notes']) ?></span></div><?php endif; ?>
    </div>
  </div>

  <table class="items">
    <thead><tr><th style="width:4%">#</th><th>Item / IMEI / PTA / Warranty</th><th class="n" style="width:7%">Qty</th><th class="n" style="width:15%">Price</th><th class="n" style="width:16%">Total</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): $sn = $r['sn']; ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td>
          <b><?= $h($r['name']) ?></b>
          <?php if ($sn): ?>
            <div class="unit-line">
              IMEI 1: <span class="imei mono"><?= $h($sn['imei_number'] ?: '-') ?></span>
              <?php if (!empty($sn['serial_number'])): ?> &nbsp;IMEI 2: <span class="imei mono"><?= $h($sn['serial_number']) ?></span><?php endif; ?>
            </div>
            <div class="unit-line">
              <?php if (!empty($sn['pta_status'])): ?><span class="pta-badge <?= pta_class($sn['pta_status']) ?>"><?= pta_short($sn['pta_status']) ?></span> <?php endif; ?>
              <?php if (!empty($sn['phone_condition'])): ?><?= $h($sn['phone_condition']) ?> &middot; <?php endif; ?>
              Warranty: <b><?= $h($sn['warranty_status'] ?: 'As per shop policy') ?></b>
            </div>
          <?php endif; ?>
        </td>
        <td class="n"><?= $r['qty'] ?></td>
        <td class="n"><?= rs($r['price']) ?></td>
        <td class="n"><b><?= rs($r['total']) ?></b></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="a4-bottom">
    <?php if ($TERMS): ?><div class="terms">
      <b>Terms &amp; Warranty</b>
      <ol><?php foreach ($TERMS as $t): ?><li><?= $h($t) ?></li><?php endforeach; ?></ol>
    </div><?php endif; ?>
    <div class="totals">
      <div class="row2"><span>Subtotal</span><span class="v"><?= rs($sale['subtotal']) ?></span></div>
      <?php if ($discount > 0): ?><div class="row2"><span>Discount</span><span class="v">-<?= rs($discount) ?></span></div><?php endif; ?>
      <div class="row2 grand"><span>Total</span><span class="v"><?= rs($sale['total_amount']) ?></span></div>
      <div class="row2"><span>Paid</span><span class="v"><?= rs($total_paid) ?></span></div>
      <div class="row2"><span>Balance on this bill</span><span class="v"><?= rs(max(0, $sale['total_amount'] - $total_paid)) ?></span></div>
    </div>
  </div>

  <div class="khata-strip">
    <span>Previous Khata: <span class="v"><?= rs($previous_khata) ?></span></span>
    <span>Paid Now: <span class="v"><?= rs($total_paid) ?></span></span>
    <span>Remaining Balance: <span class="v"><?= rs($remaining) ?></span></span>
  </div>

  <div class="sign-row"><span>Customer Signature</span><span>Salesman</span><span>Manager Signature / Stamp</span></div>
  <?php if (receipt_thanks_line() !== ''): ?><div class="thanks"><?= receipt_thanks_line() ?></div><?php endif; ?>
</div>

<!-- =============== 80mm thermal =============== -->
<div class="receipt-80">
  <?= shop_logo_img('../../', 'class="r80-logo"') ?>
  <div class="c shop"><?= $h($SHOP['name']) ?></div>
  <?php if ($SHOP['address'] !== ''): ?><div class="c sm"><?= $h($SHOP['address']) ?></div><?php endif; ?>
  <?php if ($SHOP['phones'] !== ''): ?><div class="c sm"><?= $h($SHOP['phones']) ?></div><?php endif; ?>
  <hr>
  <div class="kv"><span>Inv:</span><b><?= $h($sale['invoice_no']) ?></b></div>
  <div class="kv"><span>Date:</span><span><?= date('d/m/Y', strtotime($sale['sale_date'])) ?></span></div>
  <div class="kv"><span>Cust:</span><span><?= $h($customer['full_name'] ?? 'N/A') ?></span></div>
  <?php if (!empty($customer['phone'])): ?><div class="kv"><span>Ph:</span><span><?= $h($customer['phone']) ?></span></div><?php endif; ?>
  <hr>
  <?php foreach ($rows as $r): $sn = $r['sn']; ?>
    <div class="it">
      <div class="nm"><?= $h($r['name']) ?></div>
      <?php if ($sn): ?>
        <div class="sub">IMEI1: <?= $h($sn['imei_number'] ?: '-') ?></div>
        <?php if (!empty($sn['serial_number'])): ?><div class="sub">IMEI2: <?= $h($sn['serial_number']) ?></div><?php endif; ?>
        <div class="sub">
          <?php if (!empty($sn['pta_status'])): ?><span class="tag"><?= pta_short($sn['pta_status']) ?></span> <?php endif; ?>
          Warranty: <?= $h($sn['warranty_status'] ?: 'Shop policy') ?>
        </div>
      <?php endif; ?>
      <div class="ln"><span><?= $r['qty'] ?> x <?= rs($r['price']) ?></span><b><?= rs($r['total']) ?></b></div>
    </div>
  <?php endforeach; ?>
  <hr>
  <div class="kv"><span>Subtotal</span><span><?= rs($sale['subtotal']) ?></span></div>
  <?php if ($discount > 0): ?><div class="kv"><span>Discount</span><span>-<?= rs($discount) ?></span></div><?php endif; ?>
  <div class="kv b"><span>TOTAL</span><span><?= rs($sale['total_amount']) ?></span></div>
  <div class="kv"><span>Paid</span><span><?= rs($total_paid) ?></span></div>
  <hr>
  <div class="kv"><span>Previous Khata</span><span><?= rs($previous_khata) ?></span></div>
  <div class="kv"><span>This Bill</span><span><?= rs($sale['total_amount']) ?></span></div>
  <div class="kv"><span>Paid</span><span>-<?= rs($total_paid) ?></span></div>
  <div class="kv b"><span>REMAINING</span><span><?= rs($remaining) ?></span></div>
  <hr>
  <div class="terms"><?php foreach ($TERMS as $t): ?>* <?= $h($t) ?><br><?php endforeach; ?></div>
  <hr>
  <div class="c sm">Thank you! Visit again.</div>
</div>

<script>
(function () {
  var KEY = 'alnoor_invoice_fmt', body = document.body;
  function apply(f) {
    body.classList.remove('fmt-a4', 'fmt-80'); body.classList.add('fmt-' + f);
    // thermal paper: 80mm wide, auto length; A4 otherwise
    document.getElementById('pageSize').textContent = f === '80'
      ? '@media print { @page { size: 80mm auto; margin: 2mm; } }'
      : '@media print { @page { size: A4; margin: 10mm; } }';
    document.querySelectorAll('[data-fmt]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-fmt') === f); });
    try { localStorage.setItem(KEY, f); } catch (e) {}
  }
  var explicit = <?= json_encode(isset($_GET['fmt'])) ?>, start = <?= json_encode($fmt) ?>;
  if (!explicit) { try { start = localStorage.getItem(KEY) || start; } catch (e) {} }
  apply(start);
  document.querySelectorAll('[data-fmt]').forEach(function (b) { b.addEventListener('click', function () { apply(b.getAttribute('data-fmt')); }); });
  <?php if ($is_print): ?>window.addEventListener('load', function () { window.print(); });<?php endif; ?>
})();
</script>
</body>
</html>
