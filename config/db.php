<?php
date_default_timezone_set('Asia/Karachi');

// ============================================================
// Database Credentials (Local XAMPP defaults shown below).
// For InfinityFree live hosting, replace with your panel values:
//   $host     = 'sqlXXX.infinityfree.com';
//   $username = 'if0_XXXXXXXX';
//   $password = 'YOUR_VPANEL_PASSWORD';
//   $dbname   = 'if0_XXXXXXXX_hs_mobile';
// ============================================================
$host     = 'localhost';
$username = 'root';
$password = '';

// ============================================================
// DB MODE: change this to switch between Client and Test DB
//   'client' - production database (hs_mobile)
//   'test'   - testing/development database (hs_mobile)
// ============================================================
$db_mode = 'client';

$databases = [
    'client' => 'hs_mobile',
    'test'   => 'hs_mobile',
];

$dbname = $databases[$db_mode] ?? $databases['client'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
