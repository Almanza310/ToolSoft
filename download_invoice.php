<?php
// No debe haber espacios ni salida antes de este PHP
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;

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

// Obtener el HTML de la factura desde la sesión
if (!isset($_SESSION['invoice_html']) || !isset($_SESSION['invoice_number'])) {
    header('Content-Type: text/plain; charset=utf-8');
    die('No hay factura disponible para descargar.');
}

$invoice_html = $_SESSION['invoice_html'];
$invoice_number = $_SESSION['invoice_number'];

try {
    $dompdf = new Dompdf();
    $dompdf->loadHtml($invoice_html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("Factura_$invoice_number.pdf", ["Attachment" => true]);
    exit;
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error al generar el PDF: " . $e->getMessage();
    exit;
}
?>