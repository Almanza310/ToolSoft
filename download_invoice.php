<?php
session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Check if user is logged in
if (!isset($_SESSION['customer_logged_in'])) {
    $_SESSION['error_message'] = "Debes iniciar sesión para descargar facturas.";
    header('Location: logeo_del_prototipo.php');
    exit;
}

// Get invoice number from URL
$invoice_number = isset($_GET['invoice']) ? $_GET['invoice'] : '';

if (empty($invoice_number)) {
    $_SESSION['error_message'] = "Número de factura no válido.";
    header('Location: customer_dashboard.php');
    exit;
}

// Sanitize invoice number
$invoice_number = preg_replace('/[^a-zA-Z0-9\-_]/', '', $invoice_number);

// Check if invoice file exists
$facturas_dir = __DIR__ . '/facturas/';
$file_path = $facturas_dir . 'factura_' . $invoice_number . '.html';

if (!file_exists($file_path)) {
    $_SESSION['error_message'] = "Factura no encontrada: " . $invoice_number;
    header('Location: customer_dashboard.php');
    exit;
}

// Set headers for download
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="Factura_' . $invoice_number . '.html"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output the file
readfile($file_path);
exit;
?>