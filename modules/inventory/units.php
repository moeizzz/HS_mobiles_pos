<?php
/**
 * IMEI Stock - one row per physical unit (product_serials).
 * Filter pills: All / In Stock / Sold / PTA Approved / Non-PTA. Search by IMEI, model or seller.
 * Copy-to-clipboard buttons on IMEIs are added automatically by assets/js/pos-ui.js.
 */
session_start();
$page_title = 'IMEI Stock';
$base_url = '../../';
require_once '../../includes/functions.php';

$f = $_GET['f'] ?? 'all';
if (!in_array($f, ['all', 'in', 'sold', 'pta', 'nonpta'], true)) $f = 'all';
$q = trim($_GET['q'] ?? '');

$where = ["p.product_type = 'mobile'"];
$params = [];
if ($f === 'in')     $where[] = "s.status = 'available'";
if ($f === 'sold')   $where[] = "s.status = 'sold'";
if ($f === 'pta')    $where[] = "s.pta_status LIKE 'PTA Approved%'";
if ($f === 'nonpta') $where[] = "s.pta_status = 'Non-PTA'";
if ($q !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $where[] = "(s.imei_number LIKE ? OR s.serial_number LIKE ? OR s.device_serial_no LIKE ? OR p.name LIKE ? OR s.seller_name LIKE ? OR s.seller_cnic LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like, $like);
}
$wsql = implode(' AND ', $where);

$st = $pdo->prepare("SELECT s.*, p.name AS pname, p.storage, p.ram, p.color, p.sale_price AS psale, sa.invoice_no AS sale_invoice
    FROM product_serials s
    JOIN products p ON p.id = s.product_id
    LEFT JOIN sales sa ON sa.id = s.sale_id
    WHERE $wsql ORDER BY s.id DESC LIMIT 300");
$st->execute($params);
$units = $st->fetchAll();

// pill counts (ignore the active filter, respect nothing but type)
$cnt = $pdo->query("SELECT
    COUNT(*) AS a,
    SUM(s.status = 'available') AS i,
    SUM(s.status = 'sold') AS so,
    SUM(s.pta_status LIKE 'PTA Approved%') AS pt,
    SUM(s.pta_status = 'Non-PTA') AS np
  FROM product_serials s JOIN products p ON p.id = s.product_id WHERE p.product_type = 'mobile'")->fetch();

$is_admin = (($_SESSION['user_role'] ?? '') === 'admin');
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
function pta_cls($s) {
    if (preg_match('/jv|locked|patch|cpid/i', (string)$s)) return ['pta-jv', preg_match('/patch|cpid/i', (string)$s) ? 'Patch/CPID' : 'JV/Locked'];
    if (preg_match('/non/i', (string)$s)) return ['pta-non', 'Non-PTA'];
    return ['pta-ok', 'PTA Approved'];
}
$pills = ['all' => ['All', $cnt['a']], 'in' => ['In Stock', $cnt['i']], 'sold' => ['Sold', $cnt['so']], 'pta' => ['PTA Approved', $cnt['pt']], 'nonpta' => ['Non-PTA', $cnt['np']]];

require_once '../../includes/header.php';
?>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="form-row align-items-center">
      <div class="col-md-5 mb-2 mb-md-0">
        <input type="search" name="q" value="<?= $h($q) ?>" class="form-control" placeholder="Search IMEI, model, seller name or CNIC...">
      </div>
      <input type="hidden" name="f" value="<?= $h($f) ?>">
      <div class="col-auto"><button class="btn btn-primary"><i class="fas fa-search"></i> Search</button></div>
      <?php if ($q !== ''): ?><div class="col-auto"><a href="units.php?f=<?= $h($f) ?>" class="btn btn-outline-secondary">Clear</a></div><?php endif; ?>
      <div class="col text-right"><a href="../purchase/add_purchase.php" class="btn btn-info text-white"><i class="fas fa-plus"></i> Add Mobile</a></div>
    </form>
    <div class="filter-pills mt-3 btn-group flex-wrap" role="group">
      <?php foreach ($pills as $key => [$label, $n]): ?>
        <a href="units.php?f=<?= $key ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="btn btn-sm btn-outline-secondary <?= $f === $key ? 'active' : '' ?>"><?= $label ?> <span class="badge badge-light"><?= (int)$n ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>IMEI 1 / IMEI 2</th><th>Device</th><th>Condition</th><th>PTA</th>
            <?php if ($is_admin): ?><th class="text-right">Cost</th><?php endif; ?>
            <th class="text-right">Sale Price</th><th>Status</th><th>Source</th><th>Added</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$units): ?>
          <tr><td colspan="<?= $is_admin ? 9 : 8 ?>">No units match this filter.</td></tr>
        <?php endif; ?>
        <?php foreach ($units as $u): [$pc, $pl] = pta_cls($u['pta_status']); ?>
          <tr>
            <td>
              <div class="imei"><?= $h($u['imei_number'] ?: ($u['device_serial_no'] ?: '-')) ?></div>
              <?php if (!empty($u['serial_number'])): ?><div class="imei text-muted small"><?= $h($u['serial_number']) ?></div><?php endif; ?>
            </td>
            <td>
              <b><?= $h($u['pname']) ?></b>
              <div class="small text-muted"><?= $h(implode(' / ', array_filter([$u['storage'], $u['ram'], $u['color']]))) ?></div>
            </td>
            <td><?= $h($u['phone_condition'] ?: '-') ?>
              <?php if (!empty($u['battery_health'])): ?><div class="small text-muted">Battery <?= $h($u['battery_health']) ?></div><?php endif; ?></td>
            <td><?php if (!empty($u['pta_status'])): ?><span class="pta-badge <?= $pc ?>"><?= $pl ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
            <?php if ($is_admin): ?><td class="money">Rs. <?= formatCurrency($u['purchase_price']) ?></td><?php endif; ?>
            <td class="money">Rs. <?= formatCurrency($u['sale_price'] ?: $u['psale']) ?></td>
            <td>
              <?php if ($u['status'] === 'available'): ?><span class="status-pill status-in">In Stock</span>
              <?php elseif ($u['status'] === 'sold'): ?><span class="status-pill status-sold">Sold</span>
                <?php if ($u['sale_invoice']): ?><div class="small"><a class="inv-no" href="../sales/invoice.php?id=<?= (int)$u['sale_id'] ?>"><?= $h($u['sale_invoice']) ?></a></div><?php endif; ?>
              <?php else: ?><span class="status-pill status-ret"><?= $h(ucfirst($u['status'])) ?></span><?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($u['seller_name'])): ?><?= $h($u['seller_name']) ?><div class="cnic text-muted"><?= $h($u['seller_cnic']) ?></div>
              <?php elseif (!empty($u['supplier_id'])): ?>Supplier #<?= (int)$u['supplier_id'] ?>
              <?php else: ?><span class="text-muted">-</span><?php endif; ?>
            </td>
            <td class="small text-muted"><?= $h(formatDate($u['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if (count($units) >= 300): ?><div class="card-footer small text-muted">Showing the latest 300 matches - narrow the search to see older units.</div><?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
