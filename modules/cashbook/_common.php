<?php
/**
 * Shared helpers for the Cash Book folder (cash.php, credit.php, bank_accounts.php, bank_entries.php).
 * Not a page - included by the four pages.
 */

/** Date range from ?preset=today|yesterday|week|month|all or ?from=&to= . Default: all. */
function cb_period(array $get) {
    $today = date('Y-m-d');
    $preset = $get['preset'] ?? '';
    $from = $get['from'] ?? '';
    $to   = $get['to'] ?? '';
    $okDate = function ($d) { return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; };
    if ($preset === 'today')          { $from = $to = $today; }
    elseif ($preset === 'yesterday')  { $from = $to = date('Y-m-d', strtotime('-1 day')); }
    elseif ($preset === 'week')       { $from = date('Y-m-d', strtotime('monday this week')); $to = $today; }
    elseif ($preset === 'month')      { $from = date('Y-m-01'); $to = $today; }
    elseif ($preset === 'all' || ($from === '' && $to === '')) { $from = $to = ''; $preset = 'all'; }
    else {
        $preset = 'custom';
        if (!$okDate($from)) $from = '';
        if (!$okDate($to))   $to = '';
        if ($from !== '' && $to !== '' && $from > $to) { $t = $from; $from = $to; $to = $t; }
    }
    return [$from, $to, $preset];
}

function cb_money($v) { return number_format((float)$v, 2); }
function cb_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Friendly label for cash_book / bank_transactions.reference_type */
function cb_ref_label($type) {
    $map = [
        'payment'          => 'Customer payment',
        'supplier_payment' => 'Supplier payment',
        'purchase'         => 'Purchase',
        'sale'             => 'Sale',
        'opening_balance'  => 'Opening balance',
    ];
    return $map[$type] ?? ($type ? ucfirst(str_replace('_', ' ', $type)) : 'Manual entry');
}

/**
 * LEFT JOINs that resolve the party (customer / supplier / walk-in seller) and the invoice
 * number of a cash_book / bank_transactions row. $t = alias of that table.
 * Provides: party_name, doc_no, customer_id, supplier_id.
 */
function cb_party_joins($t) {
    return " LEFT JOIN payments pm ON $t.reference_type = 'payment' AND pm.id = $t.reference_id
             LEFT JOIN sales sl ON sl.id = pm.sale_id
             LEFT JOIN customers cu ON cu.id = sl.customer_id
             LEFT JOIN supplier_payments sp ON $t.reference_type = 'supplier_payment' AND sp.id = $t.reference_id
             LEFT JOIN suppliers su ON su.id = sp.supplier_id
             LEFT JOIN purchases pu ON pu.id = (CASE WHEN $t.reference_type = 'purchase' THEN $t.reference_id
                                                     WHEN $t.reference_type = 'supplier_payment' THEN sp.purchase_id END) ";
}
function cb_party_cols() {
    return "COALESCE(cu.full_name, NULLIF(su.contact_person, ''), su.name, pu.person_name) AS party_name,
            COALESCE(sl.invoice_no, pu.invoice_no) AS doc_no, cu.id AS customer_id, su.id AS supplier_id";
}

/**
 * One simple filter form: From / To (+ the Today/Yesterday/Week/Month chips that pos-ui.js adds by itself),
 * then the page's own fields ($extraHtml), then Show / Clear / Print.
 */
function cb_filter_bar($file, $from, $to, $extraHtml = '') {
    $h  = '<form method="get" class="form-inline">';
    $h .= '<label class="mr-1 small text-muted">From</label><input type="text" name="from" value="' . cb_h($from) . '" class="form-control form-control-sm datepicker mr-2 mb-1" style="width:115px" autocomplete="off">';
    $h .= '<label class="mr-1 small text-muted">To</label><input type="text" name="to" value="' . cb_h($to) . '" class="form-control form-control-sm datepicker mr-2 mb-1" style="width:115px" autocomplete="off">';
    $h .= $extraHtml;
    $h .= '<button class="btn btn-sm btn-primary mr-1 mb-1"><i class="fas fa-filter"></i> Show</button>';
    $h .= '<a href="' . $file . '" class="btn btn-sm btn-outline-secondary mr-1 mb-1" title="Show everything">Clear</a>';
    $h .= '<button type="button" class="btn btn-sm btn-outline-dark mb-1" onclick="window.print()"><i class="fas fa-print"></i> Print</button>';
    $h .= '</form>';
    return $h;
}
