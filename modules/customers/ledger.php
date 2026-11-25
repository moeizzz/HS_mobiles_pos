<?php
/**
 * Customer Ledger (all customers at a glance).
 * Same balance formula as customers/view.php and the ledger PDF:
 *   balance = opening due - opening paid + sales - returns - payments received
 * Each row opens that customer's full running-balance ledger (view.php?tab=ledger) or its PDF.
 */
session_start();
$page_title = 'Customer Ledger';
$base_url = '../../';
require_once '../../includes/functions.php';

$q       = trim($_GET['q'] ?? '');
$filter  = $_GET['filter'] ?? 'all';            // all | due | advance | clear
if (!in_array($filter, ['all', 'due', 'advance', 'clear'], true)) $filter = 'all';

$sql = "SELECT c.id, c.customer_no, c.full_name, c.phone, c.cnic,
          (c.opening_due - c.opening_paid) AS opening,
          COALESCE((SELECT SUM(s.total_amount) FROM sales s WHERE s.customer_id = c.id AND s.status <> 'cancelled'), 0) AS sales_total,
          COALESCE((SELECT SUM(r.amount) FROM sale_returns r WHERE r.customer_id = c.id), 0) AS returns_total,
          COALESCE((SELECT SUM(p.amount) FROM payments p JOIN sales s2 ON s2.id = p.sale_id WHERE s2.customer_id = c.id AND s2.status <> 'cancelled'), 0) AS paid_total,
          (SELECT MAX(s3.sale_date) FROM sales s3 WHERE s3.customer_id = c.id AND s3.status <> 'cancelled') AS last_sale
        FROM customers c";
$params = [];
if ($q !== '') {
    $sql .= " WHERE (c.full_name LIKE ? OR c.phone LIKE ? OR c.cnic LIKE ? OR c.customer_no LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY c.full_name ASC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = [];
$tot = ['opening' => 0, 'sales' => 0, 'credit' => 0, 'balance' => 0];
foreach ($st->fetchAll() as $r) {
    $r['credit']  = (float)$r['returns_total'] + (float)$r['paid_total'];
    $r['balance'] = round((float)$r['opening'] + (float)$r['sales_total'] - $r['credit'], 2);
    if ($filter === 'due' && $r['balance'] <= 0) continue;
    if ($filter === 'advance' && $r['balance'] >= 0) continue;
    if ($filter === 'clear' && abs($r['balance']) > 0.004) continue;
    $rows[] = $r;
    $tot['opening'] += (float)$r['opening'];
    $tot['sales']   += (float)$r['sales_total'];
    $tot['credit']  += $r['credit'];
    $tot['balance'] += $r['balance'];
}
require_once '../../includes/header.php';
$money = function ($v) { return number_format((float)$v, 2); };
?>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-balance-scale"></i> Customer Ledger</h6>
    <form method="get" class="form-inline mt-2 mt-md-0">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm mr-2" placeholder="Name, phone, CNIC...">
      <select name="filter" class="form-control form-control-sm mr-2">
        <option value="all"     <?= $filter === 'all' ? 'selected' : '' ?>>All customers</option>
        <option value="due"     <?= $filter === 'due' ? 'selected' : '' ?>>Balance due (udhaar)</option>
        <option value="advance" <?= $filter === 'advance' ? 'selected' : '' ?>>Advance / credit</option>
        <option value="clear"   <?= $filter === 'clear' ? 'selected' : '' ?>>Cleared</option>
      </select>
      <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Show</button>
    </form>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-hover table-sm mb-0">
        <thead class="thead-light">
          <tr>
            <th>#</th><th>Customer</th><th>Phone</th>
            <th class="text-right">Opening</th><th class="text-right">Sales (Dr)</th>
            <th class="text-right">Received + Returns (Cr)</th><th class="text-right">Balance</th>
            <th>Last Sale</th><th class="text-center no-print">Ledger</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No customers found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="font-weight-bold"><a href="view.php?id=<?= (int)$r['id'] ?>&amp;tab=ledger"><?= htmlspecialchars((string)$r['full_name']) ?></a>
              <div class="small text-muted"><?= htmlspecialchars((string)$r['customer_no']) ?></div></td>
            <td><?= htmlspecialchars((string)$r['phone']) ?></td>
            <td class="text-right money"><?= $money($r['opening']) ?></td>
            <td class="text-right money"><?= $money($r['sales_total']) ?></td>
            <td class="text-right money text-success"><?= $money($r['credit']) ?></td>
            <td class="text-right money font-weight-bold <?= $r['balance'] > 0 ? 'text-danger' : ($r['balance'] < 0 ? 'text-success' : '') ?>">
              <?= $money(abs($r['balance'])) ?> <small><?= $r['balance'] > 0 ? 'Dr (due)' : ($r['balance'] < 0 ? 'Cr (advance)' : '') ?></small></td>
            <td><?= htmlspecialchars((string)($r['last_sale'] ?? '-')) ?></td>
            <td class="text-center no-print text-nowrap">
              <a href="view.php?id=<?= (int)$r['id'] ?>&amp;tab=ledger" class="btn btn-sm btn-outline-primary" title="Open ledger"><i class="fas fa-book-open"></i> Ledger</a>
              <a href="ledger_print.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF / Print"><i class="fas fa-file-pdf"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr class="font-weight-bold bg-light">
            <td colspan="3" class="text-right">Total (<?= count($rows) ?> customers)</td>
            <td class="text-right money"><?= $money($tot['opening']) ?></td>
            <td class="text-right money"><?= $money($tot['sales']) ?></td>
            <td class="text-right money"><?= $money($tot['credit']) ?></td>
            <td class="text-right money"><?= $money($tot['balance']) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
