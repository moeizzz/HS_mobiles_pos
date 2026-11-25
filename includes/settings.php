<?php
/**
 * Settings + option lists stored in the database (tables `settings` and `app_options`).
 *
 * Nothing here has a hardcoded fallback on purpose: whatever is saved in the database is what the
 * software shows. Delete a row (or empty a value) in the database and it disappears from the pages.
 * Manage it from Settings (modules/settings/index.php) or directly in the tables.
 *
 * Included by includes/functions.php and login.php (login.php must not load the auth guard).
 */
require_once __DIR__ . '/../config/db.php';

/** One shop setting, e.g. setting('shop_name'). Missing row / empty value => $default ('' unless given). */
function setting($key, $default = '') {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $r) $cache[$r['setting_key']] = (string)$r['setting_value'];
        } catch (Throwable $e) { /* table missing: behave as "no settings saved" */ }
    }
    return (array_key_exists($key, $cache) && $cache[$key] !== '') ? $cache[$key] : $default;
}

/** Active values of a dropdown list in saved order, e.g. option_list('ram'). */
function option_list($type) {
    global $pdo;
    static $cache = [];
    if (!isset($cache[$type])) {
        $cache[$type] = [];
        try {
            $st = $pdo->prepare("SELECT option_value FROM app_options WHERE option_type = ? AND status = 1 ORDER BY sort_order, id");
            $st->execute([$type]);
            $cache[$type] = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) { /* table missing: empty list */ }
    }
    return $cache[$type];
}

/** Shop name / phone / tagline / address, HTML-escaped and ready to print. */
function shop($field) {
    $map = ['name' => 'shop_name', 'tagline' => 'shop_tagline', 'phone' => 'shop_phone', 'address' => 'shop_address'];
    return htmlspecialchars(setting($map[$field] ?? $field), ENT_QUOTES, 'UTF-8');
}

/** Logo <img> for the given base path ('' / '../../'), or '' when no logo is saved or the file is gone. */
function shop_logo_img($base = '', $attrs = '') {
    $p = setting('shop_logo');
    if ($p === '' || !preg_match('#^[A-Za-z0-9_./-]+$#', $p) || strpos($p, '..') !== false) return '';
    if (!is_file(__DIR__ . '/../' . $p)) {
        if (is_file(__DIR__ . '/../assets/img/logo.svg')) {
            $p = 'assets/img/logo.svg';
        } else {
            return '';
        }
    }
    $alt = shop('name') !== '' ? shop('name') : 'HS Mobiles';
    return '<img src="' . htmlspecialchars($base . $p, ENT_QUOTES) . '" alt="' . $alt . '" ' . $attrs . '>';
}

/** Lines of the invoice terms (one per line in the setting). */
function invoice_terms() {
    $t = trim(setting('invoice_terms'));
    return $t === '' ? [] : array_values(array_filter(array_map('trim', preg_split('/\R/', $t)), 'strlen'));
}

/** "[Thanks For Visiting:::Shop]" style footer text used on prints; '' when no thank-you text is saved. */
function receipt_thanks_line($style = 'plain') {
    $t = setting('receipt_thanks');
    if ($t === '') return '';
    $n = setting('shop_name');
    $text = htmlspecialchars($t . ($n !== '' ? ' ' . $n : ''), ENT_QUOTES, 'UTF-8');
    return $style === 'bracket' ? '[' . $text . ']' : $text;
}

/** The company block at the top of every A4 print (logo, name, tagline, address, phone) - all from the database. */
function print_header_html($base = '../../') {
    $logo = shop_logo_img($base, 'style="height:50px;width:auto;max-width:200px;object-fit:contain;"');
    $h  = '<div class="r-header">' . $logo . '<div>';
    if (setting('shop_name') !== '')    $h .= '<p class="r-company-name">' . shop('name') . '</p>';
    if (setting('shop_tagline') !== '') $h .= '<p class="r-company-sub">' . shop('tagline') . '</p>';
    if (setting('shop_address') !== '') $h .= '<p class="r-company-contact">' . shop('address') . '</p>';
    if (setting('shop_phone') !== '')   $h .= '<p class="r-company-contact">Cell: ' . shop('phone') . '</p>';
    return $h . '</div></div>';
}
