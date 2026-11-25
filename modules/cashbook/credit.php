<?php
/**
 * Credit (udhaar) - everything on credit in one place.
 *   Receivable: customers who owe us    (opening + sales - returns - payments received)
 *   Payable   : suppliers we owe        (opening + adjustment + purchases - payments made)
 * Same formulas as the Customer Ledger / Supplier Ledger pages, plus the pending bills behind them.
 */
session_start();
$page_title = 'Cash Book - Credit';
$base_url = '../../';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_common.php';

$view = $_GET['view'] ?? 'both';                    // both | receivable | payable
if (!in_array($view, ['both', 'receivable', 'payable'], true)) $view = 'both';
$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';

/* ---------------- receivable: customers ---------------- */
$sqlC = "SELECT c.id, c.customer_no, c.full_name, c.phone,
           (c.opening_due - c.opening_paid) AS opening,
           COALESCE((SELECT SUM(s.total_amount) FROM sales s WHERE s.customer_id = c.id AND s.status <> 'cancelled'), 0) AS credit_given,
           COALESCE((SELECT SUM(r.amount) FROM sale_returns r WHERE r.customer_id = c.id), 0)
         + COALESCE((SELECT SUM(p.amount) FROM payments p JOIN sales s2 ON s2.id = p.sale_id WHERE s2.customer_id = c.id AND s2.status <> 'cancelled'), 0) AS settled,
           (SELECT MIN(s3.sale_date) FROM sales s3 WHERE s3.customer_id = c.id AND s3.status <> 'cancelled' AND s3.due_amount > 0) AS oldest_due
         FROM customers c";
$pc = [];
if ($q !== '') { $sqlC .= " WHERE (c.full_name LIKE ? OR c.phone LIKE ? OR c.customer_no LIKE ?)"; $pc = [$like, $like, $like]; }
$st = $pdo->prepare($sqlC . " ORDER BY c.full_name");
$st->execute($pc);
$cust = []; $recv_total = 0.0;
foreach ($st->fetchAll() as $r) {
    $r['balance'] = round((float)$r['opening'] + (float)$r['credit_given'] - (float)$r['settled'], 2);
    if ($r['balance'] <= 0) continue;
    $cust[] = $r; $recv_total += $r['balance'];
}
usort($cust, function ($a, $b) { return $b['balance'] <=> $a['balance']; });

// pending customer bills
$sqlB = "SELECT s.id, s.invoice_no, s.sale_date, s.total_amount, s.paid_amount, s.due_amount, c.id AS cid, c.full_name, DATEDIFF(CURDATE(), s.sale_date) AS age
         FROM sales s JOIN customers c ON c.id = s.customer_id
         WHERE s.status <> 'cancelled' AND s.due_amount > 0";
$pb = [];
if ($q !== '') { $sqlB .= " AND (c.full_name LIKE ? OR c.phone LIKE ? OR s.invoice_no LIKE ?)"; $pb = [$like, $like, $like]; }
$st = $pdo->prepare($sqlB . " ORDER BY s.sale_date ASC, s.id ASC");
$st->execute($pb);
$cbills = $st->fetchAll();

/* ---------------- payable: suppliers ---------------- */
$sqlS = "SELECT s.id, s.name, s.contact_person, s.phone,
           (COALESCE(s.opening_balance, 0) + COALESCE(s.adjustment, 0)) AS opening,
           COALESCE((SELECT SUM(p.total_amount) FROM purchases p WHERE p.supplier_id = s.id AND p.status <> 'cancelled'), 0) AS credit_taken,
           COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) AS settled,
           (SELECT MIN(p2.purchase_date) FROM purchases p2 WHERE p2.supplier_id = s.id AND p2.status <> 'cancelled' AND p2.due_amount > 0) AS oldest_due
         FROM suppliers s";
$ps = [];
if ($q !== '') { $sqlS .= " WHERE (s.name LIKE ? OR s.contact_person LIKE ? OR s.phone LIKE ?)"; $ps = [$like, $like, $like]; }
$st = $pdo->prepare($sqlS);
$st->execute($ps);
$sup = []; $pay_total = 0.0;
foreach ($st->fetchAll() as $r) {
    $r['balance'] = round((float)$r['opening'] + (float)$r['credit_taken'] - (float)$r['settled'], 2);
    if ($r['balance'] <= 0) continue;
    $r['label'] = trim((string)($r['contact_person'] ?: $r['name']));
    $sup[] = $r; $pay_total += $r['balance'];
}
usort($sup, function ($a, $b) { return $b['balance'] <=> $a['balance']; });

$sqlP = "SELECT p.id, p.invoice_no, p.purchase_date, p.total_amount, p.paid_amount, p.due_amount, s.id AS sid,
                COALESCE(NULLIF(s.contact_person, ''), s.name) AS sname, DATEDIFF(CURDATE(), p.purchase_date) AS age
         FROM purchases p JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.status <> 'cancelled' AND p.due_amount > 0";
$pp = [];
if ($q !== '') { $sqlP .= " AND (s.name LIKE ? OR s.contact_person LIKE ? OR p.invoice_no LIKE ?)"; $pp = [$like, $like, $like]; }
$st = $pdo->prepare($sqlP . " ORDER BY p.purchase_date ASC, p.id ASC");
$st->execute($pp);
$pbills = $st->fetchAll();

require_once '../../includes/header.php';
$ageBadge = function ($d) { $d = (int)$d; $c = $d > 60 ? 'danger' : ($d > 30 ? 'warning' : 'secondary'); return '<span class="badge badge-' . $c . '">' . $d . ' d</span>'; };
?>

<div class="card shadow mb-3 no-print">
  <div class="card-body py-3">
    <form method="get" class="form-inline">
      <select name="view" class="form-control form-control-sm mr-2">
        <option value="both"       <?= $view === 'both' ? 'selected' : '' ?>>Receivable + Payable</option>
        <option value="receivable" <?= $view === 'receivable' ? 'selected' : '' ?>>Receivable only (customers owe us)</option>
        <option value="payable"    <?= $view === 'payable' ? 'selected' : '' ?>>Payable only (we owe suppliers)</option>
      </select>
      <input type="text" name="q" value="<?= cb_h($q) ?>" class="form-control form-control-sm mr-2" placeholder="Search name / phone / invoice..." style="min-width:240px">
      <button class="btn btn-sm btn-primary mr-2"><i class="fas fa-search"></i> Show</button>
      <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </form>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-4 mb-2"><div class="card shadow-sm h-100 border-left-danger"><div class="card-body py-3">
    <div class="small text-muted font-weight-bold">TO RECEIVE (customers owe us)</div><div class="h4 mb-0 money text-danger"><?= cb_money($recv_total) ?></div>
    <div class="small text-muted"><?= count($cust) ?> customers</div></div></div></div>
  <div class="col-md-4 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3">
    <div class="small text-muted font-weight-bold">TO PAY (we owe suppliers)</div><div class="h4 mb-0 money text-warning"><?= cb_money($pay_total) ?></div>
    <div class="small text-muted"><?= count($sup) ?> suppliers</div></div></div></div>
  <div class="col-md-4 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3">
    <div class="small text-muted font-weight-bold">NET CREDIT POSITION</div><div class="h4 mb-0 money <?= ($recv_total - $pay_total) < 0 ? 'text-danger' : 'text-success' ?>"><?= cb_money($recv_total - $pay_total) ?></div>
    <div class="small text-muted">receivable - payable</div></div></div></div>
</div>

<?php if ($view !== 'payable'): ?>
<div class="card shadow mb-4">
  <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-hand-holding-usd"></i> Receivable - customers on credit (udhaar)</h6></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-hover table-sm mb-0">
        <thead class="thead-light"><tr><th>#</th><th>Customer</th><th>Phone</th><th class="text-right">Opening</th><th class="text-right">Credit given</th>
          <th class="text-right">Received + returns</th><th class="text-right">Balance due</th><th>Oldest unpaid</th><th class="text-center no-print">Ledger</th></tr></thead>
        <tbody>
        <?php if (!$cust): ?><tr><td colspan="9" class="text-center text-muted py-4">No customer owes anything.</td></tr><?php endif; ?>
        <?php foreach ($cust as $i => $r): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="font-weight-bold"><a href="../customers/view.php?id=<?= (int)$r['id'] ?>&amp;tab=ledger"><?= cb_h($r['full_name']) ?></a></td>
            <td><?= cb_h($r['phone']) ?></td>
            <td class="text-right money"><?= cb_money($r['opening']) ?></td>
            <td class="text-right money"><?= cb_money($r['credit_given']) ?></td>
            <td class="text-right money text-success"><?= cb_money($r['settled']) ?></td>
            <td class="text-right money font-weight-bold text-danger"><?= cb_money($r['balance']) ?></td>
            <td><?= cb_h($r['oldest_due'] ?? '-') ?></td>
            <td class="text-center no-print text-nowrap">
              <a href="../customers/view.php?id=<?= (int)$r['id'] ?>&amp;tab=payments" class="btn btn-sm btn-outline-success" title="Receive payment"><i class="fas fa-plus"></i> Receive</a>
              <a href="../customers/ledger_print.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($cust): ?><tfoot><tr class="font-weight-bold bg-light"><td colspan="6" class="text-right">Total receivable</td><td class="text-right money text-danger"><?= cb_money($recv_total) ?></td><td colspan="2"></td></tr></tfoot><?php endif; ?>
      </table>
    </div>

    <?php if ($cbills): ?>
    <div class="px-3 pt-3 pb-1 font-weight-bold small text-muted">PENDING SALE BILLS (<?= count($cbills) ?>)</div>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Due</th><th>Age</th></tr></thead>
        <tbody>
        <?php foreach ($cbills as $b): ?>
          <tr><td class="text-mono"><a href="../sales/invoice.php?id=<?= (int)$b['id'] ?>" target="_blank"><?= cb_h($b['invoice_no']) ?></a></td>
              <td><?= cb_h($b['sale_date']) ?></td>
              <td><a href="../customers/view.php?id=<?= (int)$b['cid'] ?>&amp;tab=ledger"><?= cb_h($b['full_name']) ?></a></td>
              <td class="text-right money"><?= cb_money($b['total_amount']) ?></td>
              <td class="text-right money text-success"><?= cb_money($b['paid_amount']) ?></td>
              <td class="text-right money text-danger font-weight-bold"><?= cb_money($b['due_amount']) ?></td>
              <td><?= $ageBadge($b['age']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($view !== 'receivable'): ?>
<div class="card shadow mb-4">
  <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-truck-loading"></i> Payable - suppliers on credit (we owe)</h6></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-hover table-sm mb-0">
        <thead class="thead-light"><tr><th>#</th><th>Supplier</th><th>Phone</th><th class="text-right">Opening</th><th class="text-right">Credit taken</th>
          <th class="text-right">Paid</th><th class="text-right">Balance payable</th><th>Oldest unpaid</th><th class="text-center no-print">Ledger</th></tr></thead>
        <tbody>
        <?php if (!$sup): ?><tr><td colspan="9" class="text-center text-muted py-4">We owe no supplier anything.</td></tr><?php endif; ?>
        <?php foreach ($sup as $i => $r): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="font-weight-bold"><a href="../inventory/supplier_view.php?id=<?= (int)$r['id'] ?>&amp;tab=ledger"><?= cb_h($r['label']) ?></a></td>
            <td><?= cb_h($r['phone']) ?></td>
            <td class="text-right money"><?= cb_money($r['opening']) ?></td>
            <td class="text-right money"><?= cb_money($r['credit_taken']) ?></td>
            <td class="text-right money text-success"><?= cb_money($r['settled']) ?></td>
            <td class="text-right money font-weight-bold text-danger"><?= cb_money($r['balance']) ?></td>
            <td><?= cb_h($r['oldest_due'] ?? '-') ?></td>
            <td class="text-center no-print text-nowrap">
              <a href="../inventory/supplier_view.php?id=<?= (int)$r['id'] ?>&amp;tab=payments" class="btn btn-sm btn-outline-success" title="Make payment"><i class="fas fa-plus"></i> Pay</a>
              <a href="../inventory/supplier_ledger_print.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($sup): ?><tfoot><tr class="font-weight-bold bg-light"><td colspan="6" class="text-right">Total payable</td><td class="text-right money text-danger"><?= cb_money($pay_total) ?></td><td colspan="2"></td></tr></tfoot><?php endif; ?>
      </table>
    </div>

    <?php if ($pbills): ?>
    <div class="px-3 pt-3 pb-1 font-weight-bold small text-muted">PENDING PURCHASE BILLS (<?= count($pbills) ?>) <span class="font-weight-normal">- before any lump-sum payments shown in the supplier balance above</span></div>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead><tr><th>Invoice</th><th>Date</th><th>Supplier</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Due</th><th>Age</th></tr></thead>
        <tbody>
        <?php foreach ($pbills as $b): ?>
          <tr><td class="text-mono"><a href="../purchase/purchase_print.php?id=<?= (int)$b['id'] ?>" target="_blank"><?= cb_h($b['invoice_no']) ?></a></td>
              <td><?= cb_h($b['purchase_date']) ?></td>
              <td><a href="../inventory/supplier_view.php?id=<?= (int)$b['sid'] ?>&amp;tab=ledger"><?= cb_h($b['sname']) ?></a></td>
              <td class="text-right money"><?= cb_money($b['total_amount']) ?></td>
              <td class="text-right money text-success"><?= cb_money($b['paid_amount']) ?></td>
              <td class="text-right money text-danger font-weight-bold"><?= cb_money($b['due_amount']) ?></td>
              <td><?= $ageBadge($b['age']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
