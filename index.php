<?php
session_start();
$page_title = 'Dashboard';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Date range filter (default: today)
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');

// Period summary stats
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE sale_date BETWEEN ? AND ? AND status != 'cancelled'");
$stmt->execute([$from, $to]);
$period_sales = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$period_expense = (float)$stmt->fetchColumn();

// COGS - cost basis from serial (mobile) or product purchase price
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(COALESCE(ps.purchase_price, p.purchase_price, 0) * si.quantity), 0)
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id AND s.status != 'cancelled' AND s.sale_date BETWEEN ? AND ?
    LEFT JOIN product_serials ps ON ps.id = si.serial_id
    LEFT JOIN products p ON p.id = si.product_id
");
$stmt->execute([$from, $to]);
$period_cogs = (float)$stmt->fetchColumn();
$period_profit = $period_sales - $period_cogs;

// Cash in hand as of end of period
$stmt = $pdo->prepare("SELECT closing_balance FROM cash_book_daily WHERE date <= ? ORDER BY date DESC LIMIT 1");
$stmt->execute([$to]);
$cash_in_hand = (float)$stmt->fetchColumn();

// Current stock value (at cost) - non-serialized by qty x cost, serialized by actual unit cost
$stock_value = (float)$pdo->query("SELECT COALESCE(SUM(stock_quantity * purchase_price), 0) FROM products WHERE status = 1 AND has_serial = 0")->fetchColumn();
$stock_value += (float)$pdo->query("SELECT COALESCE(SUM(ps.purchase_price), 0) FROM product_serials ps JOIN products p ON p.id = ps.product_id WHERE ps.status = 'available' AND p.status = 1")->fetchColumn();

// Khata / credit balances (customers owing money)
$khatas = $pdo->query("
    SELECT s.id, s.invoice_no, s.sale_date, s.total_amount, s.paid_amount, s.due_amount,
           c.full_name, c.id as cid, c.phone
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    WHERE s.due_amount > 0 AND s.status != 'cancelled'
    ORDER BY s.due_amount DESC LIMIT 10
")->fetchAll();

// Recent sales
$recent_sales = $pdo->query("
    SELECT s.id, s.invoice_no, s.total_amount, s.payment_status, s.sale_date,
           c.full_name, c.id as cid
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    ORDER BY s.id DESC LIMIT 5
")->fetchAll();

// Recent customers
$recent_customers = $pdo->query("SELECT id, full_name, phone, cnic, created_at FROM customers ORDER BY created_at DESC LIMIT 5")->fetchAll();


// ---- Extra KPI data ----
// previous period of the same length, for the trend label
$days_len = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
$prev_to = date('Y-m-d', strtotime($from . ' -1 day'));
$prev_from = date('Y-m-d', strtotime($prev_to . ' -' . ($days_len - 1) . ' day'));
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE sale_date BETWEEN ? AND ? AND status != 'cancelled'");
$stmt->execute([$prev_from, $prev_to]);
$prev_sales = (float)$stmt->fetchColumn();

// Net profit = sales - cost of goods - expenses (rejected expenses excluded)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ? AND COALESCE(approval_status, '') <> 'rejected'");
$stmt->execute([$from, $to]);
$period_expense = (float)$stmt->fetchColumn();
$net_profit = $period_profit - $period_expense;

// Pending khata across all customers (sales-level dues)
$khata_row = $pdo->query("SELECT COALESCE(SUM(due_amount), 0) AS t, COUNT(DISTINCT customer_id) AS c FROM sales WHERE due_amount > 0 AND status != 'cancelled'")->fetch();
$pending_khata = (float)$khata_row['t'];
$khata_customers = (int)$khata_row['c'];

// Stock counts
$mobiles_in_stock = (int)$pdo->query("SELECT COUNT(*) FROM product_serials ps JOIN products p ON p.id = ps.product_id WHERE ps.status = 'available' AND p.status = 1")->fetchColumn();
$accessory_units = (int)$pdo->query("SELECT COALESCE(SUM(stock_quantity), 0) FROM products WHERE status = 1 AND has_serial = 0")->fetchColumn();

require_once 'includes/header.php';

// "Rs. 145,000" style for KPI tiles (full precision stays in tables)
function kpi_rs($n) { return 'Rs. ' . number_format((float)$n, 0); }
function kpi_trend($now, $prev) {
    if ($prev <= 0) return $now > 0 ? '<span class="trend-up"><i class="fas fa-arrow-up"></i> new</span> vs previous period' : 'No sales in previous period';
    $pct = ($now - $prev) / $prev * 100;
    $cls = $pct >= 0 ? 'trend-up' : 'trend-down';
    return '<span class="' . $cls . '"><i class="fas fa-arrow-' . ($pct >= 0 ? 'up' : 'down') . '"></i> ' . number_format(abs($pct), 1) . '%</span> vs previous period';
}
$days = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
$is_single_day = ($from === $to);
?>

<!-- Range filter (date presets are added by pos-ui.js) -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <form method="get" class="form-inline mb-2 mb-md-0">
    <label class="mr-1 text-muted small">From</label>
    <input type="text" name="from" class="form-control form-control-sm mr-2 datepicker" style="width:125px" value="<?= htmlspecialchars($from) ?>" autocomplete="off">
    <label class="mr-1 text-muted small">To</label>
    <input type="text" name="to" class="form-control form-control-sm mr-2 datepicker" style="width:125px" value="<?= htmlspecialchars($to) ?>" autocomplete="off">
    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> View</button>
  </form>
  <div>
    <a href="modules/sales/new_sale.php" class="btn btn-sm btn-primary"><i class="fas fa-cash-register"></i> New Sale</a>
    <a href="modules/purchase/add_purchase.php" class="btn btn-sm btn-info text-white"><i class="fas fa-mobile-alt"></i> Add Mobile</a>
  </div>
</div>
<div class="text-muted small mb-3"><i class="far fa-calendar-alt"></i> Showing <b><?= formatDate($from) ?></b><?= $is_single_day ? '' : ' &rarr; <b>' . formatDate($to) . '</b>' ?> (<?= $days ?> day<?= $days > 1 ? 's' : '' ?>)</div>

<!-- KPI tiles -->
<div class="row">
  <div class="col-xl-4 col-md-6 mb-4">
    <div class="kpi kpi-success">
      <div class="kpi-icon"><i class="fas fa-shopping-cart"></i></div>
      <div>
        <div class="kpi-label">Sales</div>
        <div class="kpi-value"><?= kpi_rs($period_sales) ?></div>
        <div class="kpi-sub"><?= kpi_trend($period_sales, $prev_sales) ?></div>
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-md-6 mb-4">
    <div class="kpi kpi-indigo">
      <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
      <div>
        <div class="kpi-label">Net Profit</div>
        <div class="kpi-value <?= $net_profit < 0 ? 'text-debit' : '' ?>"><?= kpi_rs($net_profit) ?></div>
        <div class="kpi-sub">Gross <?= kpi_rs($period_profit) ?> &minus; Expenses <?= kpi_rs($period_expense) ?></div>
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-md-6 mb-4">
    <div class="kpi kpi-warning">
      <div class="kpi-icon"><i class="fas fa-money-bill-wave"></i></div>
      <div>
        <div class="kpi-label"><?= $to === date('Y-m-d') ? "Today's Cash" : 'Cash in Hand' ?></div>
        <div class="kpi-value"><?= kpi_rs($cash_in_hand) ?></div>
        <div class="kpi-sub">Closing balance as of <?= formatDate($to) ?></div>
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-md-6 mb-4">
    <div class="kpi kpi-danger">
      <div class="kpi-icon"><i class="fas fa-book"></i></div>
      <div>
        <div class="kpi-label">Pending Khata</div>
        <div class="kpi-value text-debit"><?= kpi_rs($pending_khata) ?></div>
        <div class="kpi-sub"><?= (int)$khata_customers ?> customer<?= $khata_customers == 1 ? '' : 's' ?> owe money</div>
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-md-6 mb-4">
    <div class="kpi kpi-info">
      <div class="kpi-icon"><i class="fas fa-boxes"></i></div>
      <div>
        <div class="kpi-label">Stock Value (at cost)</div>
        <div class="kpi-value"><?= kpi_rs($stock_value) ?></div>
        <div class="kpi-sub"><?= (int)$mobiles_in_stock ?> mobile<?= $mobiles_in_stock == 1 ? '' : 's' ?> in stock &middot; <?= (int)$accessory_units ?> accessory units</div>
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-md-6 mb-4">
    <div class="kpi kpi-danger" style="--kpi:#be123c">
      <div class="kpi-icon"><i class="fas fa-file-invoice-dollar"></i></div>
      <div>
        <div class="kpi-label">Expenses</div>
        <div class="kpi-value"><?= kpi_rs($period_expense) ?></div>
        <div class="kpi-sub"><?= $period_sales > 0 ? number_format($period_expense / $period_sales * 100, 1) . '% of sales' : 'No sales in this period' ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Khata / Credit Balances -->
  <div class="col-lg-12 mb-4">
    <div class="card">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0"><i class="fas fa-book"></i> Khata / Credit Balances <span class="text-muted font-weight-normal small">(top 10 by amount due)</span></h6>
      </div>
      <div class="card-body p-0">
        <?php if (count($khatas)): ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>Customer</th><th>Phone</th><th>Invoice</th><th>Date</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Due</th></tr>
            </thead>
            <tbody>
              <?php foreach ($khatas as $d): ?>
              <tr>
                <td class="font-weight-bold"><a href="modules/customers/view.php?id=<?=$d['cid']?>"><?=htmlspecialchars($d['full_name'])?></a></td>
                <td class="text-mono"><?=htmlspecialchars($d['phone'])?></td>
                <td><a class="inv-no" href="modules/sales/invoice.php?id=<?=$d['id']?>"><?=htmlspecialchars($d['invoice_no'])?></a></td>
                <td><?=formatDate($d['sale_date'])?></td>
                <td class="money">Rs. <?=formatCurrency($d['total_amount'])?></td>
                <td class="money amt-in">Rs. <?=formatCurrency($d['paid_amount'])?></td>
                <td class="money amt-out">Rs. <?=formatCurrency($d['due_amount'])?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="empty-state">
            <svg viewBox="0 0 96 96" fill="none" stroke="#a7f3d0" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="48" cy="48" r="30" fill="#ecfdf5"/><path d="M34 49l10 10 19-21" stroke="#10b981"/></svg>
            <div class="t">No outstanding khata</div><div class="small">Every customer is clear.</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Recent Sales -->
  <div class="col-lg-7 mb-4">
    <div class="card h-100">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0"><i class="fas fa-file-invoice"></i> Recent Sales</h6>
        <a href="modules/sales/invoices.php" class="btn btn-outline-primary btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (count($recent_sales)): ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>Invoice</th><th>Customer</th><th class="text-right">Amount</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent_sales as $s): ?>
              <tr>
                <td><a class="inv-no" href="modules/sales/invoice.php?id=<?=$s['id']?>"><?=htmlspecialchars($s['invoice_no'])?></a></td>
                <td><a href="modules/customers/view.php?id=<?=$s['cid']?>"><?=htmlspecialchars($s['full_name'])?></a></td>
                <td class="money">Rs. <?=formatCurrency($s['total_amount'])?></td>
                <td><span class="status-pill <?= $s['payment_status']==='paid' ? 'status-in' : ($s['payment_status']==='partial' ? 'status-ret' : 'status-sold') ?>"><?=ucfirst($s['payment_status'])?></span></td>
                <td><?=formatDate($s['sale_date'])?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="empty-state">
            <svg viewBox="0 0 96 96" fill="none" stroke="#cbd5e1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="20" y="14" width="56" height="68" rx="8" fill="#f8fafc"/><path d="M32 34h32M32 46h32M32 58h18"/></svg>
            <div class="t">No sales yet</div>
            <a href="modules/sales/new_sale.php" class="btn btn-primary btn-sm mt-2"><i class="fas fa-plus"></i> Make the first sale</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Customers -->
  <div class="col-lg-5 mb-4">
    <div class="card h-100">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0"><i class="fas fa-users"></i> Recent Customers</h6>
        <a href="modules/customers/index.php" class="btn btn-outline-primary btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (count($recent_customers)): ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>Name</th><th>Phone</th><th>CNIC</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent_customers as $c): ?>
              <tr>
                <td class="font-weight-bold"><a href="modules/customers/view.php?id=<?=$c['id']?>"><?=htmlspecialchars($c['full_name'])?></a></td>
                <td class="text-mono"><?=htmlspecialchars($c['phone'])?></td>
                <td class="cnic"><?=htmlspecialchars((string)$c['cnic'])?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="empty-state">
            <svg viewBox="0 0 96 96" fill="none" stroke="#cbd5e1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="48" cy="36" r="14" fill="#f8fafc"/><path d="M20 80c4-16 14-22 28-22s24 6 28 22" fill="#f8fafc"/></svg>
            <div class="t">No customers yet</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
