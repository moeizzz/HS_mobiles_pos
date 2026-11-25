<?php
/**
 * Bank Entries - every bank deposit / withdrawal across all accounts (or one account), with totals.
 * Source: bank_transactions (written by bank sales, customer payments, purchases and supplier payments).
 */
session_start();
$page_title = 'Cash Book - Bank Entries';
$base_url = '../../';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_common.php';

[$from, $to, $preset] = cb_period($_GET);
$account = (int)($_GET['account'] ?? 0);
$type = $_GET['type'] ?? 'all';                     // all | in | out
if (!in_array($type, ['all', 'in', 'out'], true)) $type = 'all';
$q = trim($_GET['q'] ?? '');

$accounts = $pdo->query("SELECT id, account_name, bank_name, account_no, opening_balance, current_balance, status FROM bank_accounts ORDER BY bank_name, account_name")->fetchAll();
$accById = []; foreach ($accounts as $a) $accById[(int)$a['id']] = $a;
if ($account && !isset($accById[$account])) $account = 0;

$signed = "CASE WHEN t.transaction_type IN ('deposit','transfer_in') THEN t.amount ELSE -t.amount END";

// balance brought forward (only meaningful for one account: opening balance + entries before the range)
$opening = null;
if ($account) {
    $opening = (float)$accById[$account]['opening_balance'];
    if ($from !== '') {
        $st = $pdo->prepare("SELECT COALESCE(SUM($signed), 0) FROM bank_transactions t WHERE t.bank_account_id = ? AND t.transaction_date < ?");
        $st->execute([$account, $from]);
        $opening += (float)$st->fetchColumn();
    }
}

$where = ['1=1']; $params = [];
if ($account)     { $where[] = 't.bank_account_id = ?'; $params[] = $account; }
if ($from !== '') { $where[] = 't.transaction_date >= ?'; $params[] = $from; }
if ($to !== '')   { $where[] = 't.transaction_date <= ?'; $params[] = $to; }
if ($type === 'in')  $where[] = "t.transaction_type IN ('deposit','transfer_in')";
if ($type === 'out') $where[] = "t.transaction_type IN ('withdrawal','transfer_out')";
if ($q !== '') {
    $where[] = '(t.description LIKE ? OR cu.full_name LIKE ? OR su.contact_person LIKE ? OR su.name LIKE ? OR pu.person_name LIKE ? OR sl.invoice_no LIKE ? OR pu.invoice_no LIKE ? OR t.cheque_no LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}
$LIMIT = 2000;
$sql = "SELECT t.*, ba.account_name, ba.bank_name, " . cb_party_cols() . " FROM bank_transactions t JOIN bank_accounts ba ON ba.id = t.bank_account_id " . cb_party_joins('t') .
       " WHERE " . implode(' AND ', $where) . " ORDER BY t.transaction_date ASC, t.id ASC LIMIT " . ($LIMIT + 1);
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
$truncated = count($rows) > $LIMIT;
if ($truncated) array_pop($rows);

$dep = $wd = 0.0;
foreach ($rows as $r) {
    if (in_array($r['transaction_type'], ['deposit', 'transfer_in'], true)) $dep += (float)$r['amount']; else $wd += (float)$r['amount'];
}
$running_ok = ($account && $type === 'all' && $q === '');
$closing = $opening !== null ? $opening + $dep - $wd : null;

require_once '../../includes/header.php';
?>

<div class="card shadow mb-3 no-print">
  <div class="card-body py-3">
    <?php
      $opts = '<select name="account" class="form-control form-control-sm mr-2 mb-1"><option value="0">All accounts</option>';
      foreach ($accounts as $a) { $opts .= '<option value="' . (int)$a['id'] . '"' . ($account === (int)$a['id'] ? ' selected' : '') . '>' . cb_h($a['bank_name'] . ' - ' . $a['account_name']) . '</option>'; }
      $opts .= '</select><select name="type" class="form-control form-control-sm mr-2 mb-1">'
             . '<option value="all"' . ($type === 'all' ? ' selected' : '') . '>Deposits + withdrawals</option>'
             . '<option value="in"' . ($type === 'in' ? ' selected' : '') . '>Deposits only</option>'
             . '<option value="out"' . ($type === 'out' ? ' selected' : '') . '>Withdrawals only</option></select>'
             . '<input type="text" name="q" value="' . cb_h($q) . '" class="form-control form-control-sm mr-2 mb-1" placeholder="Search party / invoice..." style="width:200px">';
      echo cb_filter_bar('bank_entries.php', $from, $to, $opts);
    ?>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-3 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="small text-muted font-weight-bold"><?= $account ? 'OPENING BALANCE' : 'ACCOUNTS' ?></div>
    <div class="h5 mb-0 money"><?= $account ? cb_money($opening) : count($accounts) ?></div></div></div></div>
  <div class="col-md-3 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="small text-muted font-weight-bold">DEPOSITS</div><div class="h5 mb-0 money text-success"><?= cb_money($dep) ?></div></div></div></div>
  <div class="col-md-3 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="small text-muted font-weight-bold">WITHDRAWALS</div><div class="h5 mb-0 money text-danger"><?= cb_money($wd) ?></div></div></div></div>
  <div class="col-md-3 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="small text-muted font-weight-bold"><?= $account ? 'CLOSING BALANCE' : 'NET MOVEMENT' ?></div>
    <?php $v = $account ? $closing : ($dep - $wd); ?><div class="h5 mb-0 money <?= $v < 0 ? 'text-danger' : '' ?>"><?= cb_money($v) ?></div></div></div></div>
</div>

<?php if (!$account && $accounts): ?>
<div class="card shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap">
      <?php foreach ($accounts as $a): ?>
        <a href="bank_entries.php?<?= http_build_query(array_filter(['account' => $a['id'], 'preset' => $preset === 'custom' ? '' : $preset, 'from' => $from, 'to' => $to])) ?>" class="mr-4 small text-decoration-none">
          <span class="text-muted"><?= cb_h($a['bank_name']) ?> / <?= cb_h($a['account_name']) ?>:</span> <b class="money <?= $a['current_balance'] < 0 ? 'text-danger' : '' ?>"><?= cb_money($a['current_balance']) ?></b></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-exchange-alt"></i> Bank Entries
      <span class="text-muted font-weight-normal small">(<?= $account ? cb_h($accById[$account]['bank_name'] . ' - ' . $accById[$account]['account_name']) : 'all accounts' ?>,
        <?= $from === '' && $to === '' ? 'all dates' : cb_h(($from ?: 'start') . ' to ' . ($to ?: 'today')) ?> - <?= count($rows) ?> entries)</span></h6>
  </div>
  <div class="card-body p-0">
    <?php if ($truncated): ?><div class="alert alert-warning m-2 py-2 small">Showing the first <?= $LIMIT ?> entries. Narrow the date range to see the rest.</div><?php endif; ?>
    <div class="table-responsive">
      <table class="table table-bordered table-hover table-sm mb-0">
        <thead class="thead-light">
          <tr><th>#</th><th>Date</th><?php if (!$account): ?><th>Account</th><?php endif; ?><th>Description</th><th>Party</th><th>Type</th><th>Invoice</th>
              <th class="text-right">Deposit</th><th class="text-right">Withdrawal</th><?php if ($running_ok): ?><th class="text-right">Balance</th><?php endif; ?></tr>
        </thead>
        <tbody>
        <?php if ($running_ok): ?>
          <tr class="table-light"><td colspan="6" class="text-right font-weight-bold"><?= $from !== '' ? 'Balance brought forward' : 'Opening balance' ?></td><td></td><td></td><td class="text-right money font-weight-bold"><?= cb_money($opening) ?></td></tr>
        <?php endif; ?>
        <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">No bank entries for this selection.</td></tr><?php endif; ?>
        <?php $bal = (float)$opening; foreach ($rows as $i => $r):
          $isIn = in_array($r['transaction_type'], ['deposit', 'transfer_in'], true);
          $bal += $isIn ? (float)$r['amount'] : -(float)$r['amount']; ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="text-nowrap"><?= cb_h($r['transaction_date']) ?></td>
            <?php if (!$account): ?><td class="small"><?= cb_h($r['bank_name']) ?><div class="text-muted"><?= cb_h($r['account_name']) ?></div></td><?php endif; ?>
            <td><?= cb_h($r['description']) ?><?= $r['cheque_no'] ? ' <span class="badge badge-light border">Chq ' . cb_h($r['cheque_no']) . '</span>' : '' ?></td>
            <td><?php if (!empty($r['customer_id'])): ?><a href="../customers/view.php?id=<?= (int)$r['customer_id'] ?>&amp;tab=ledger"><?= cb_h($r['party_name']) ?></a>
                <?php elseif (!empty($r['supplier_id'])): ?><a href="../inventory/supplier_view.php?id=<?= (int)$r['supplier_id'] ?>&amp;tab=ledger"><?= cb_h($r['party_name']) ?></a>
                <?php else: ?><?= cb_h($r['party_name'] ?? '') ?><?php endif; ?></td>
            <td><span class="badge badge-light border"><?= cb_h(cb_ref_label($r['reference_type'])) ?></span></td>
            <td class="text-mono text-nowrap"><?= cb_h($r['doc_no'] ?? '') ?></td>
            <td class="text-right money text-success"><?= $isIn ? cb_money($r['amount']) : '' ?></td>
            <td class="text-right money text-danger"><?= $isIn ? '' : cb_money($r['amount']) ?></td>
            <?php if ($running_ok): ?><td class="text-right money <?= $bal < 0 ? 'text-danger' : '' ?>"><?= cb_money($bal) ?></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot><tr class="font-weight-bold bg-light">
          <td colspan="<?= $account ? 6 : 7 ?>" class="text-right">Total</td>
          <td class="text-right money text-success"><?= cb_money($dep) ?></td>
          <td class="text-right money text-danger"><?= cb_money($wd) ?></td>
          <?php if ($running_ok): ?><td class="text-right money"><?= cb_money($closing) ?></td><?php endif; ?>
        </tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
