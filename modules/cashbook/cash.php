<?php
/**
 * Cash Book - every cash movement (cash in / cash out) with a running balance.
 * Source: cash_book (written by sales, customer payments, purchases and supplier payments).
 */
session_start();
$page_title = 'Cash Book - Cash';
$base_url = '../../';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_common.php';

[$from, $to, $preset] = cb_period($_GET);
$type = $_GET['type'] ?? 'all';                     // all | in | out
if (!in_array($type, ['all', 'in', 'out'], true)) $type = 'all';
$q = trim($_GET['q'] ?? '');

// signed amount: inflow / opening_balance add, outflow subtracts, closing_balance rows are markers (ignored)
$signed = "CASE WHEN t.transaction_type IN ('inflow','opening_balance') THEN t.amount
                WHEN t.transaction_type = 'outflow' THEN -t.amount ELSE 0 END";

// balance brought forward = everything before the range
$opening = 0.0;
if ($from !== '') {
    $st = $pdo->prepare("SELECT COALESCE(SUM($signed), 0) FROM cash_book t WHERE t.transaction_date < ?");
    $st->execute([$from]);
    $opening = (float)$st->fetchColumn();
}

$where = ["t.transaction_type IN ('inflow','outflow','opening_balance')"];
$params = [];
if ($from !== '') { $where[] = 't.transaction_date >= ?'; $params[] = $from; }
if ($to !== '')   { $where[] = 't.transaction_date <= ?'; $params[] = $to; }
if ($type === 'in')  $where[] = "t.transaction_type IN ('inflow','opening_balance')";
if ($type === 'out') $where[] = "t.transaction_type = 'outflow'";
if ($q !== '') {
    $where[] = '(t.description LIKE ? OR cu.full_name LIKE ? OR su.contact_person LIKE ? OR su.name LIKE ? OR pu.person_name LIKE ? OR sl.invoice_no LIKE ? OR pu.invoice_no LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
$LIMIT = 2000;
$sql = "SELECT t.*, " . cb_party_cols() . " FROM cash_book t " . cb_party_joins('t') .
       " WHERE " . implode(' AND ', $where) . " ORDER BY t.transaction_date ASC, t.id ASC LIMIT " . ($LIMIT + 1);
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
$truncated = count($rows) > $LIMIT;
if ($truncated) array_pop($rows);

$tin = $tout = 0.0;
foreach ($rows as $r) {
    if ($r['transaction_type'] === 'outflow') $tout += (float)$r['amount']; else $tin += (float)$r['amount'];
}
// the running balance only makes sense when no type/search filter hides rows
$running_ok = ($type === 'all' && $q === '');
$closing = $opening + $tin - $tout;

require_once '../../includes/header.php';
?>

<div class="card shadow mb-3 no-print">
  <div class="card-body py-3">
    <?= cb_filter_bar('cash.php', $from, $to,
      '<select name="type" class="form-control form-control-sm mr-2 mb-1">'
      . '<option value="all"' . ($type === 'all' ? ' selected' : '') . '>Cash in + out</option>'
      . '<option value="in"' . ($type === 'in' ? ' selected' : '') . '>Cash in only</option>'
      . '<option value="out"' . ($type === 'out' ? ' selected' : '') . '>Cash out only</option></select>'
      . '<input type="text" name="q" value="' . cb_h($q) . '" class="form-control form-control-sm mr-2 mb-1" placeholder="Search party / invoice..." style="width:210px">') ?>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-3 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="small text-muted font-weight-bold">OPENING BALANCE</div><div class="h5 mb-0 money"><?= cb_money($opening) ?></div></div></div></div>
  <div class="col-md-3 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="small text-muted font-weight-bold">CASH IN</div><div class="h5 mb-0 money text-success"><?= cb_money($tin) ?></div></div></div></div>
  <div class="col-md-3 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="small text-muted font-weight-bold">CASH OUT</div><div class="h5 mb-0 money text-danger"><?= cb_money($tout) ?></div></div></div></div>
  <div class="col-md-3 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="small text-muted font-weight-bold">CLOSING BALANCE</div><div class="h5 mb-0 money <?= $closing < 0 ? 'text-danger' : '' ?>"><?= cb_money($closing) ?></div></div></div></div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-money-bill-wave"></i> Cash Book
      <span class="text-muted font-weight-normal small">
        (<?= $from === '' && $to === '' ? 'all dates' : cb_h(($from ?: 'start') . ' to ' . ($to ?: 'today')) ?> - <?= count($rows) ?> entries)</span></h6>
  </div>
  <div class="card-body p-0">
    <?php if ($truncated): ?><div class="alert alert-warning m-2 py-2 small">Showing the first <?= $LIMIT ?> entries. Narrow the date range to see the rest.</div><?php endif; ?>
    <div class="table-responsive">
      <table class="table table-bordered table-hover table-sm mb-0">
        <thead class="thead-light">
          <tr><th>#</th><th>Date</th><th>Description</th><th>Party</th><th>Type</th><th>Invoice</th>
              <th class="text-right">Cash In</th><th class="text-right">Cash Out</th><th class="text-right"><?= $running_ok ? 'Balance' : '' ?></th></tr>
        </thead>
        <tbody>
        <?php if ($running_ok && $from !== ''): ?>
          <tr class="table-light"><td colspan="6" class="text-right font-weight-bold">Balance brought forward</td><td></td><td></td><td class="text-right money font-weight-bold"><?= cb_money($opening) ?></td></tr>
        <?php endif; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No cash entries for this selection.</td></tr>
        <?php endif; ?>
        <?php $bal = $opening; foreach ($rows as $i => $r):
          $isOut = $r['transaction_type'] === 'outflow';
          $bal += $isOut ? -(float)$r['amount'] : (float)$r['amount']; ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="text-nowrap"><?= cb_h($r['transaction_date']) ?></td>
            <td><?= cb_h($r['description']) ?></td>
            <td><?php if (!empty($r['customer_id'])): ?><a href="../customers/view.php?id=<?= (int)$r['customer_id'] ?>&amp;tab=ledger"><?= cb_h($r['party_name']) ?></a>
                <?php elseif (!empty($r['supplier_id'])): ?><a href="../inventory/supplier_view.php?id=<?= (int)$r['supplier_id'] ?>&amp;tab=ledger"><?= cb_h($r['party_name']) ?></a>
                <?php else: ?><?= cb_h($r['party_name'] ?? '') ?><?php endif; ?></td>
            <td><span class="badge badge-light border"><?= cb_h(cb_ref_label($r['reference_type'])) ?></span></td>
            <td class="text-mono text-nowrap"><?= cb_h($r['doc_no'] ?? '') ?></td>
            <td class="text-right money text-success"><?= $isOut ? '' : cb_money($r['amount']) ?></td>
            <td class="text-right money text-danger"><?= $isOut ? cb_money($r['amount']) : '' ?></td>
            <td class="text-right money <?= $bal < 0 ? 'text-danger' : '' ?>"><?= $running_ok ? cb_money($bal) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot><tr class="font-weight-bold bg-light">
          <td colspan="6" class="text-right">Total</td>
          <td class="text-right money text-success"><?= cb_money($tin) ?></td>
          <td class="text-right money text-danger"><?= cb_money($tout) ?></td>
          <td class="text-right money"><?= $running_ok ? cb_money($closing) : '' ?></td>
        </tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
