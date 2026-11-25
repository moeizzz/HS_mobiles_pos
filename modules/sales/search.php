<?php
/**
 * JSON search endpoint used by the POS scanner bar and the top-bar global search.
 *   ?q=<text>[&scope=pos]   -> serials (IMEI/model), customers, general products
 *   ?imei=<digits>          -> exact IMEI lookup (scanner Enter), returns one serial or null
 * Auth is enforced by includes/functions.php (401 JSON when logged out).
 */
session_start();
require_once '../../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

// Customer khata balance, same formula as customers/view.php
$bal_sql = "(c.opening_due - c.opening_paid
    + COALESCE((SELECT SUM(s.total_amount) FROM sales s WHERE s.customer_id = c.id AND s.status <> 'cancelled'), 0)
    - COALESCE((SELECT SUM(r.amount) FROM sale_returns r WHERE r.customer_id = c.id), 0)
    - COALESCE((SELECT SUM(p.amount) FROM payments p JOIN sales s2 ON s2.id = p.sale_id WHERE s2.customer_id = c.id AND s2.status <> 'cancelled'), 0))";

$serial_cols = "s.id, s.product_id, s.imei_number AS imei, s.serial_number AS imei2, s.status, s.pta_status AS pta,
    s.phone_condition AS cond, s.warranty_status AS warranty, s.purchase_price AS cost, COALESCE(NULLIF(s.sale_price,0), p.sale_price) AS price,
    p.name, p.storage, p.ram, p.color, p.product_type";

// ---- exact IMEI (scanner) ----
if (isset($_GET['imei'])) {
    $v = preg_replace('/\D/', '', $_GET['imei']);
    $st = $pdo->prepare("SELECT $serial_cols FROM product_serials s JOIN products p ON p.id = s.product_id
                         WHERE s.imei_number = ? OR s.serial_number = ? ORDER BY s.status = 'available' DESC LIMIT 1");
    $st->execute([$v, $v]);
    echo json_encode(['serial' => $st->fetch() ?: null]);
    exit;
}

$q = trim($_GET['q'] ?? '');
$scope = ($_GET['scope'] ?? '') === 'pos' ? 'pos' : 'global';
if (mb_strlen($q) < 2) { echo json_encode(['serials' => [], 'customers' => [], 'products' => []]); exit; }
$like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

// serials: POS shows only sellable units; global search also shows sold ones (warranty / dispute lookups)
$status_sql = $scope === 'pos' ? "AND s.status = 'available'" : '';
$st = $pdo->prepare("SELECT $serial_cols FROM product_serials s JOIN products p ON p.id = s.product_id
    WHERE (s.imei_number LIKE ? OR s.serial_number LIKE ? OR s.device_serial_no LIKE ? OR p.name LIKE ?) $status_sql
    ORDER BY s.status = 'available' DESC, s.id DESC LIMIT 12");
$st->execute([$like, $like, $like, $like]);
$serials = $st->fetchAll();

$products = [];
if ($scope === 'pos') {
    $st = $pdo->prepare("SELECT p.id, p.code, p.name, p.sale_price AS price, p.purchase_price AS cost, p.stock_quantity AS stock
        FROM products p WHERE p.status = 1 AND p.has_serial = 0 AND p.stock_quantity > 0 AND (p.name LIKE ? OR p.code LIKE ?)
        ORDER BY p.name LIMIT 8");
    $st->execute([$like, $like]);
    $products = $st->fetchAll();
}

$customers = [];
if ($scope === 'global') {
    $st = $pdo->prepare("SELECT c.id, c.full_name AS name, c.phone, c.cnic, $bal_sql AS balance FROM customers c
        WHERE c.full_name LIKE ? OR c.phone LIKE ? OR c.cnic LIKE ? OR c.customer_no LIKE ? ORDER BY c.full_name LIMIT 8");
    $st->execute([$like, $like, $like, $like]);
    $customers = $st->fetchAll();
}

echo json_encode(['serials' => $serials, 'customers' => $customers, 'products' => $products], JSON_UNESCAPED_UNICODE);
