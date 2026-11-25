<?php
/**
 * AJAX product / unit detail fetcher for the New Sale window.
 *
 *   ?imei=356938035643809   exact scan (IMEI 1, IMEI 2 or manufacturer serial)  -> {found, unit}
 *   ?serial_id=12           one unit by id                                         -> {found, unit}
 *   ?product_id=5           a general (non-serial) item                           -> {found, unit}
 *   ?list=1[&q=text]        searchable in-stock list (max 40)                     -> {items:[...]}
 *
 * "unit" carries every saved attribute of that unit. Auth: includes/functions.php answers 401 JSON
 * when logged out. Read-only endpoint (no writes).
 */
session_start();
require_once '../../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$is_admin = (($_SESSION['user_role'] ?? '') === 'admin');

$SELECT = "SELECT s.id AS serial_id, s.product_id, s.imei_number, s.serial_number AS imei2, s.device_serial_no, s.status,
        s.phone_condition, s.pta_status, s.network_note, s.battery_health, s.warranty_status, s.accessories, s.fault_notes,
        s.purchase_price AS unit_cost, s.sale_price AS unit_price, s.notes,
        p.name, p.model_name, p.ram, p.storage, p.color, p.product_type, p.code,
        p.sale_price AS product_price, p.purchase_price AS product_cost, p.stock_quantity, b.name AS brand
    FROM product_serials s
    JOIN products p ON p.id = s.product_id
    LEFT JOIN brands b ON b.id = p.brand_id";

/** Shape one DB row into the JSON the UI renders. */
function unit_payload(array $r) {
    $price = (float)($r['unit_price'] ?? 0) > 0 ? (float)$r['unit_price'] : (float)$r['product_price'];
    $cost  = (float)($r['unit_cost'] ?? 0) > 0 ? (float)$r['unit_cost'] : (float)$r['product_cost'];
    $acc = array_values(array_filter(array_map('trim', explode(',', (string)($r['accessories'] ?? '')))));
    return [
        'kind'        => 'serial',
        'serial_id'   => (int)$r['serial_id'],
        'product_id'  => (int)$r['product_id'],
        'status'      => $r['status'],                       // available | sold | returned
        'in_stock'    => $r['status'] === 'available',
        'brand'       => $r['brand'],
        'name'        => $r['name'],
        'model'       => $r['model_name'] ?: $r['name'],
        'ram'         => $r['ram'], 'storage' => $r['storage'], 'color' => $r['color'],
        'type'        => $r['product_type'],
        'imei1'       => $r['imei_number'],
        'imei2'       => $r['imei2'],
        'serial_no'   => $r['device_serial_no'],
        'condition'   => $r['phone_condition'],
        'pta'         => $r['pta_status'],
        'network'     => $r['network_note'],
        'battery'     => $r['battery_health'],
        'warranty'    => $r['warranty_status'],
        'accessories' => $acc,
        'fault_notes' => $r['fault_notes'],
        'cost'        => $cost,                              // shown to admin only in the UI
        'price'       => $price,
    ];
}

// ---------- IMEI suffix lookup: last 4+ digits of IMEI 1 or IMEI 2 -> matching saved units ----------
if (isset($_GET['last'])) {
    $d = preg_replace('/\D/', '', (string)$_GET['last']);
    if (strlen($d) < 4 || strlen($d) > 15) { echo json_encode(['items' => []]); exit; }
    $st = $pdo->prepare("$SELECT WHERE s.imei_number LIKE ? OR s.serial_number LIKE ?
                         ORDER BY s.status = 'available' DESC, s.id DESC LIMIT 15");
    $st->execute(['%' . $d, '%' . $d]);
    $items = [];
    foreach ($st->fetchAll() as $r) {
        $u = unit_payload($r);
        $hit2 = $d !== '' && substr((string)$u['imei2'], -strlen($d)) === $d && substr((string)$u['imei1'], -strlen($d)) !== $d;
        $items[] = ['serial_id' => $u['serial_id'], 'product_id' => $u['product_id'],
                    'title' => trim(($u['brand'] ? $u['brand'] . ' ' : '') . $u['model']),
                    'sub' => implode(' / ', array_filter([$u['storage'], $u['ram'], $u['color']])),
                    'imei' => $u['imei1'], 'imei2' => $u['imei2'], 'matched' => $hit2 ? 'imei2' : 'imei1',
                    'status' => $u['status'], 'in_stock' => $u['in_stock'], 'pta' => $u['pta'], 'price' => $u['price']];
    }
    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- exact IMEI / serial scan ----------
if (isset($_GET['imei'])) {
    $v = preg_replace('/[^0-9A-Za-z]/', '', (string)$_GET['imei']);
    if ($v === '') { echo json_encode(['found' => false]); exit; }
    $st = $pdo->prepare("$SELECT WHERE s.imei_number = ? OR s.serial_number = ? OR s.device_serial_no = ?
                         ORDER BY s.status = 'available' DESC, s.id DESC LIMIT 1");
    $st->execute([$v, $v, $v]);
    $r = $st->fetch();
    echo json_encode($r ? ['found' => true, 'unit' => unit_payload($r)] : ['found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- one unit by id ----------
if (isset($_GET['serial_id'])) {
    $st = $pdo->prepare("$SELECT WHERE s.id = ? LIMIT 1");
    $st->execute([(int)$_GET['serial_id']]);
    $r = $st->fetch();
    echo json_encode($r ? ['found' => true, 'unit' => unit_payload($r)] : ['found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- general (non-serial) product ----------
if (isset($_GET['product_id'])) {
    $st = $pdo->prepare("SELECT p.*, b.name AS brand FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE p.id = ? AND p.status = 1");
    $st->execute([(int)$_GET['product_id']]);
    $p = $st->fetch();
    if (!$p) { echo json_encode(['found' => false]); exit; }
    echo json_encode(['found' => true, 'unit' => [
        'kind' => 'product', 'serial_id' => 0, 'product_id' => (int)$p['id'], 'status' => 'available',
        'in_stock' => (int)$p['stock_quantity'] > 0, 'brand' => $p['brand'], 'name' => $p['name'], 'model' => $p['model_name'] ?: $p['name'],
        'ram' => $p['ram'], 'storage' => $p['storage'], 'color' => $p['color'], 'type' => $p['product_type'], 'code' => $p['code'],
        'stock' => (int)$p['stock_quantity'], 'cost' => (float)$p['purchase_price'], 'price' => (float)$p['sale_price'],
        'accessories' => [], 'warranty' => $p['warranty_months'] ? $p['warranty_months'] . ' month(s)' : null,
    ]], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- searchable in-stock list ----------
if (isset($_GET['list'])) {
    $q = trim($_GET['q'] ?? '');
    $params = [];
    $where = "s.status = 'available'";
    if ($q !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $where .= " AND (s.imei_number LIKE ? OR s.serial_number LIKE ? OR p.name LIKE ? OR p.model_name LIKE ? OR b.name LIKE ?)";
        array_push($params, $like, $like, $like, $like, $like);
    }
    $st = $pdo->prepare("$SELECT WHERE $where ORDER BY p.name, s.id LIMIT 40");
    $st->execute($params);
    $items = [];
    foreach ($st->fetchAll() as $r) {
        $u = unit_payload($r);
        $items[] = ['kind' => 'serial', 'serial_id' => $u['serial_id'], 'product_id' => $u['product_id'],
                    'title' => trim(($u['brand'] ? $u['brand'] . ' ' : '') . $u['model']),
                    'sub' => implode(' / ', array_filter([$u['storage'], $u['ram'], $u['color']])),
                    'imei' => $u['imei1'], 'pta' => $u['pta'], 'condition' => $u['condition'], 'price' => $u['price']];
    }
    // general accessories with stock
    $gp = []; $gw = "p.status = 1 AND p.has_serial = 0 AND p.stock_quantity > 0";
    if ($q !== '') { $gw .= " AND (p.name LIKE ? OR p.code LIKE ?)"; $gp = [$like, $like]; }
    $st = $pdo->prepare("SELECT p.id, p.name, p.code, p.sale_price, p.stock_quantity FROM products p WHERE $gw ORDER BY p.name LIMIT 15");
    $st->execute($gp);
    foreach ($st->fetchAll() as $p) {
        $items[] = ['kind' => 'product', 'serial_id' => 0, 'product_id' => (int)$p['id'], 'title' => $p['name'],
                    'sub' => $p['code'] . ' - stock ' . (int)$p['stock_quantity'], 'imei' => null, 'pta' => null, 'condition' => null, 'price' => (float)$p['sale_price']];
    }
    echo json_encode(['items' => $items, 'admin' => $is_admin], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Specify imei, serial_id, product_id or list']);
