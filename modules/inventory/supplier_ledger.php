<?php
/**
 * Supplier Ledger (all suppliers at a glance).
 * Same formula as supplier_view.php and the ledger PDF:
 *   payable = opening balance + adjustment + purchases - payments made
 * Each row opens that supplier's full running-balance ledger (supplier_view.php?tab=ledger) or its PDF.
 */
session_start();
$page_title = 'Supplier Ledger';
$base_url = '../../';
require_once '../../includes/functions.php';

$q      = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';             // all | due | advance | clear
if (!in_array($filter, ['all', 'due', 'advance', 'clear'], true)) $filter = 'all';

$sql = "SELECT s.id, s.name, s.contact_person, s.phone, s.city,
          (COALESCE(s.opening_balance, 0) + COALESCE(s.adjustment, 0)) AS opening,
          COALESCE((SELECT SUM(p.total_amount) FROM purchases p WHERE p.supplier_id = s.id AND p.status <> 'cancelled'), 0) AS purchases_total,
          COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) AS paid_total,
          (SELECT MAX(p2.purchase_date) FROM purchases p2 WHERE p2.supplier_id = s.id AND p2.status <> 'cancelled') AS last_purchase
        FROM suppliers s";
$params = [];
if ($q !== '') {
    $sql .= " WHERE (s.name LIKE ? OR s.contact_person LIKE ? OR s.phone LIKE ? OR s.city LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY COALESCE(NULLIF(s.contact_person, ''), s.name) ASC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = [];
$tot = ['opening' => 0, 'purchases' => 0, 'paid' => 0, 'balance' => 0];
foreach ($st->fetchAll() as $r) {
    $r['balance'] = round((float)$r['opening'] + (float)$r['purchases_total'] - (float)$r['paid_total'], 2);
    if ($filter === 'due' && $r['balance'] <= 0) continue;
    if ($filter === 'advance' && $r['balance'] >= 0) continue;
    if ($filter === 'clear' && abs($r['balance']) > 0.004) continue;
    $rows[] = $r;
    $tot['opening']   += (float)$r['opening'];
    $tot['purchases'] += (float)$r['purchases_total'];
    $tot['paid']      += (float)$r['paid_total'];
    $tot['balance']   += $r['balance'];
}
require_once '../../includes/header.php';
$money = function ($v) { return number_format((float)$v, 2); };
?>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-balance-scale"></i> Supplier Ledger</h6>
    <form method="get" class="form-inline mt-2 mt-md-0">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm mr-2" placeholder="Name, phone, city...">
      <select name="filter" class="form-control form-control-sm mr-2">
        <option value="all"     <?= $filter === 'all' ? 'selected' : '' ?>>All suppliers</option>
        <option value="due"     <?= $filter === 'due' ? 'selected' : '' ?>>We owe (payable)</option>
        <option value="advance" <?= $filter === 'advance' ? 'selected' : '' ?>>Advance paid</option>
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
            <th>#</th><th>Supplier</th><th>Phone</th>
            <th class="text-right">Opening</th><th class="text-right">Purchases (Dr)</th>
            <th class="text-right">Paid (Cr)</th><th class="text-right">Balance</th>
            <th>Last Purchase</th><th class="text-center no-print">Ledger</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No suppliers found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $i => $r):
          $label = trim((string)($r['contact_person'] ?: $r['name'])); ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="font-weight-bold"><a href="supplier_view.php?id=<?= (int)$r['id'] ?>&amp;tab=ledger"><?= htmlspecialchars($label) ?></a>
              <?php if (!empty($r['name']) && $r['name'] !== $label): ?><div class="small text-muted"><?= htmlspecialchars((string)$r['name']) ?></div><?php endif; ?></td>
            <td><?= htmlspecialchars((string)$r['phone']) ?></td>
            <td class="text-right money"><?= $money($r['opening']) ?></td>
            <td class="text-right money"><?= $money($r['purchases_total']) ?></td>
            <td class="text-right money text-success"><?= $money($r['paid_total']) ?></td>
            <td class="text-right money font-weight-bold <?= $r['balance'] > 0 ? 'text-danger' : ($r['balance'] < 0 ? 'text-success' : '') ?>">
              <?= $money(abs($r['balance'])) ?> <small><?= $r['balance'] > 0 ? 'Payable' : ($r['balance'] < 0 ? 'Advance' : '') ?></small></td>
            <td><?= htmlspecialchars((string)($r['last_purchase'] ?? '-')) ?></td>
            <td class="text-center no-print text-nowrap">
              <a href="supplier_view.php?id=<?= (int)$r['id'] ?>&amp;tab=ledger" class="btn btn-sm btn-outline-primary" title="Open ledger"><i class="fas fa-book-open"></i> Ledger</a>
              <a href="supplier_ledger_print.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF / Print"><i class="fas fa-file-pdf"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr class="font-weight-bold bg-light">
            <td colspan="3" class="text-right">Total (<?= count($rows) ?> suppliers)</td>
            <td class="text-right money"><?= $money($tot['opening']) ?></td>
            <td class="text-right money"><?= $money($tot['purchases']) ?></td>
            <td class="text-right money"><?= $money($tot['paid']) ?></td>
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
