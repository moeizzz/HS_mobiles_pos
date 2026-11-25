<?php
// Receipt page used after a sale is saved: opens the invoice and the print dialog at once.
// (invoice.php renders A4 and 80mm thermal; its toolbar lets the cashier switch.)
$_GET['print'] = '1';
require __DIR__ . '/invoice.php';
