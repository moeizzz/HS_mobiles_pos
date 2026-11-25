<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
  <title><?= htmlspecialchars($page_title ?? 'Dashboard') ?> | <?= shop('name') ?: 'HS Mobiles' ?></title>

  <!-- Fonts: Plus Jakarta Sans (UI) + JetBrains Mono (IMEI, invoice IDs, CNIC, PKR) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="<?= $base_url ?? '' ?>assets/css/style.css">
  <!-- Modern theme: must load after style.css so it can override it -->
  <link rel="stylesheet" href="<?= $base_url ?? '' ?>assets/css/pos-modern.css">
  <script>window.POS_BASE = <?= json_encode($base_url ?? '') ?>;</script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body id="page-top" class="<?= htmlspecialchars($body_class ?? '') ?>">
<?php
  $self = $_SERVER['PHP_SELF'];
  $B = $base_url ?? '';
  $is = function ($needle) use ($self) { return str_contains($self, $needle); };
?>

<!-- Sidebar Backdrop (mobile) -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div id="wrapper">

  <!-- ===== SIDEBAR ===== -->
  <nav class="sidebar" id="sidebar">

    <a class="sidebar-brand" href="<?= $B ?>index.php">
      <?php echo shop_logo_img($B, 'class="sidebar-logo"') ?: '<span class="sidebar-brand-text">' . (shop('name') ?: 'HS Mobiles') . '</span>'; ?>
      <div class="sidebar-brand-icon sidebar-brand-icon-mini"><i class="fas fa-mobile-alt"></i></div>
    </a>

    <hr class="sidebar-divider">

    <!-- Dashboard -->
    <div class="nav-item <?= basename($self) == 'index.php' && !$is('modules/') ? 'active' : '' ?>">
      <a class="nav-link" href="<?= $B ?>index.php">
        <i class="fas fa-fw fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
    </div>

    <!-- ===== SALES ===== -->
    <div class="sidebar-heading"><span>Sales</span></div>

    <div class="nav-item <?= $is('sales/new_sale.php') || $is('sales/index.php') ? 'active' : '' ?>">
      <a class="nav-link" href="<?= $B ?>modules/sales/new_sale.php">
        <i class="fas fa-fw fa-cash-register"></i>
        <span>New Sale</span><span class="kbd-hint">F2</span>
      </a>
    </div>
    <div class="nav-item <?= $is('sales/invoices') || $is('sales/invoice') || $is('sales/sale_') ? 'active' : '' ?>">
      <a class="nav-link" href="<?= $B ?>modules/sales/invoices.php">
        <i class="fas fa-fw fa-file-invoice"></i>
        <span>Invoices</span>
      </a>
    </div>

    <div class="nav-item">
      <?php $on_customer_page = $is('customers/'); ?>
      <a class="nav-link <?= $on_customer_page ? '' : 'collapsed' ?>" data-toggle="collapse" href="#collapseCustomer" role="button" aria-expanded="<?= $on_customer_page ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-users"></i>
        <span>Customers (Khata)</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse <?= $on_customer_page ? 'show' : '' ?>" id="collapseCustomer">
        <div class="collapse-inner">
          <a class="collapse-item <?= $is('customers/create.php') ? 'active' : '' ?>" href="<?= $B ?>modules/customers/create.php"><i class="fas fa-user-plus"></i> Add Customer</a>
          <a class="collapse-item <?= $is('customers/index.php') || $is('customers/edit.php') || $is('customers/view.php') ? 'active' : '' ?>" href="<?= $B ?>modules/customers/index.php"><i class="fas fa-address-card"></i> View Customers</a>
          <a class="collapse-item <?= $is('customers/ledger') ? 'active' : '' ?>" href="<?= $B ?>modules/customers/ledger.php"><i class="fas fa-balance-scale"></i> Customer Ledger</a>
        </div>
      </div>
    </div>

    <!-- ===== PURCHASE & STOCK ===== -->
    <div class="sidebar-heading"><span>Purchase &amp; Stock</span></div>

    <div class="nav-item">
      <?php $on_pur = $is('modules/purchase/'); ?>
      <a class="nav-link <?= $on_pur ? '' : 'collapsed' ?>" data-toggle="collapse" href="#collapsePurchase" role="button" aria-expanded="<?= $on_pur ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-truck"></i>
        <span>Purchase</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse <?= $on_pur ? 'show' : '' ?>" id="collapsePurchase">
        <div class="collapse-inner">
          <a class="collapse-item <?= $is('purchase/add_purchase') ? 'active' : '' ?>" href="<?= $B ?>modules/purchase/add_purchase.php"><i class="fas fa-mobile-alt"></i> Add Purchase <span class="kbd-hint">F3</span></a>
          <a class="collapse-item <?= $is('purchase/view_purchase') || $is('purchase/purchase_edit') ? 'active' : '' ?>" href="<?= $B ?>modules/purchase/view_purchase.php"><i class="fas fa-list"></i> View Purchase</a>
        </div>
      </div>
    </div>
    <div class="nav-item <?= $is('inventory/units') ? 'active' : '' ?>">
      <a class="nav-link" href="<?= $B ?>modules/inventory/units.php">
        <i class="fas fa-fw fa-barcode"></i>
        <span>IMEI Stock</span>
      </a>
    </div>

    <div class="nav-item">
      <?php $on_supplier_page = $is('inventory/supplier'); ?>
      <a class="nav-link <?= $on_supplier_page ? '' : 'collapsed' ?>" data-toggle="collapse" href="#collapseSupplier" role="button" aria-expanded="<?= $on_supplier_page ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-truck-loading"></i>
        <span>Suppliers</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse <?= $on_supplier_page ? 'show' : '' ?>" id="collapseSupplier">
        <div class="collapse-inner">
          <a class="collapse-item <?= $is('inventory/supplier_create') ? 'active' : '' ?>" href="<?= $B ?>modules/inventory/supplier_create.php"><i class="fas fa-plus-circle"></i> Add Supplier</a>
          <a class="collapse-item <?= $is('inventory/suppliers') || $is('inventory/supplier_view') || $is('inventory/supplier_edit') ? 'active' : '' ?>" href="<?= $B ?>modules/inventory/suppliers.php"><i class="fas fa-list"></i> View Suppliers</a>
          <a class="collapse-item <?= $is('inventory/supplier_ledger') ? 'active' : '' ?>" href="<?= $B ?>modules/inventory/supplier_ledger.php"><i class="fas fa-balance-scale"></i> Supplier Ledger</a>
        </div>
      </div>
    </div>

    <!-- ===== CASH BOOK ===== -->
    <div class="sidebar-heading"><span>Accounts</span></div>
    <div class="nav-item">
      <?php $on_cb = $is('modules/cashbook/'); ?>
      <a class="nav-link <?= $on_cb ? '' : 'collapsed' ?>" data-toggle="collapse" href="#collapseCashbook" role="button" aria-expanded="<?= $on_cb ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-money-bill-wave"></i>
        <span>Cash Book</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse <?= $on_cb ? 'show' : '' ?>" id="collapseCashbook">
        <div class="collapse-inner">
          <a class="collapse-item <?= $is('cashbook/cash.php') ? 'active' : '' ?>" href="<?= $B ?>modules/cashbook/cash.php"><i class="fas fa-coins"></i> Cash</a>
          <a class="collapse-item <?= $is('cashbook/credit.php') ? 'active' : '' ?>" href="<?= $B ?>modules/cashbook/credit.php"><i class="fas fa-hand-holding-usd"></i> Credit (Udhaar)</a>
          <a class="collapse-item <?= $is('cashbook/bank_accounts.php') ? 'active' : '' ?>" href="<?= $B ?>modules/cashbook/bank_accounts.php"><i class="fas fa-university"></i> Bank Accounts</a>
          <a class="collapse-item <?= $is('cashbook/bank_entries.php') ? 'active' : '' ?>" href="<?= $B ?>modules/cashbook/bank_entries.php"><i class="fas fa-exchange-alt"></i> Bank Entries</a>
        </div>
      </div>
    </div>

    <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
    <div class="sidebar-heading"><span>System</span></div>
    <div class="nav-item <?= $is('modules/settings/') ? 'active' : '' ?>">
      <a class="nav-link" href="<?= $B ?>modules/settings/index.php">
        <i class="fas fa-fw fa-cog"></i>
        <span>Settings</span>
      </a>
    </div>
    <?php endif; ?>

  </nav>
  <!-- End Sidebar -->
  <script>
    // Restore the saved sidebar state before first paint (desktop only) so there is no flash.
    (function () {
      try {
        if (window.innerWidth >= 992 && localStorage.getItem('alnoor_sidebar') === 'collapsed') {
          document.getElementById('sidebar').classList.add('collapsed');
          document.body.classList.add('sidebar-collapsed');
        }
      } catch (e) {}
    })();
  </script>

  <!-- ===== CONTENT WRAPPER ===== -->
  <div id="content-wrapper">

    <!-- Topbar -->
    <header class="topbar">
      <button type="button" id="sidebarToggle" class="btn btn-link text-dark p-2 mr-2" aria-label="Toggle sidebar" aria-expanded="true" title="Toggle menu">
        <i class="fas fa-bars fa-lg"></i>
      </button>
      <div class="page-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></div>

      <!-- Quick actions (F2 / F3 / F4) -->
      <div class="quick-actions">
        <a class="qa-btn qa-sale"   href="<?= $B ?>modules/sales/new_sale.php" title="New Sale (F2)"><i class="fas fa-plus"></i><span class="lbl-long">New Sale</span> <span class="key">F2</span></a>
        <a class="qa-btn qa-mobile" href="<?= $B ?>modules/purchase/add_purchase.php" title="Add Mobile (F3)"><i class="fas fa-plus"></i><span class="lbl-long">Add Mobile</span> <span class="key">F3</span></a>
        <a class="qa-btn qa-pay"    href="<?= $B ?>modules/customers/index.php" title="Receive Payment (F4) - open a customer, then Credit Received"><i class="fas fa-plus"></i><span class="lbl-long">Receive Payment</span> <span class="key">F4</span></a>
      </div>

      <!-- Global IMEI / customer search (Ctrl+K or /) -->
      <div class="global-search">
        <i class="fas fa-search"></i>
        <input type="search" id="globalSearch" placeholder="Search IMEI, model or customer...  ( / )" autocomplete="off" aria-label="Global search">
        <div class="search-pop" id="globalSearchPop"></div>
      </div>

      <div class="shop-clock" id="shopClock"><div class="d"></div><div class="t"></div></div>

      <div class="user-area">
        <div class="dropdown">
          <button class="btn btn-link text-muted dropdown-toggle p-0" data-toggle="dropdown">
            <i class="fas fa-user-circle fa-lg"></i>
            <span class="ml-1 d-none d-sm-inline"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
          </button>
          <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="<?= $B ?>login.php?logout=1">
              <i class="fas fa-sign-out-alt fa-sm mr-2 text-danger"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- Page Content -->
    <div class="content">

      <!-- Page Heading -->
      <?php if (empty($hide_page_heading)): ?>
      <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 font-weight-bold"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
      </div>
      <?php endif; ?>

      <!-- Flash Messages -->
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars((string)$_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars((string)$_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php endif; ?>
