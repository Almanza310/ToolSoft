<?php
// No debe haber espacios ni salida antes de este PHP
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;

session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Check if user is logged in (customer or admin)
if (!isset($_SESSION['customer_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    $_SESSION['error_message'] = "Debes iniciar sesión para descargar facturas.";
    header('Location: logeo_del_prototipo.php');
    exit;
}

// Si se proporciona un número de factura específico (para administradores)
if (isset($_GET['invoice'])) {
    $invoice_number = $_GET['invoice'];
    
    // Extraer información de la factura
    $parts = explode('-', $invoice_number);
    if (count($parts) >= 3) {
        $date_part = $parts[1];
        $sale_id = intval($parts[2]);
        
        // Obtener datos de la venta
        require_once 'conexion.php';
        $query = "
        SELECT s.*, u.name AS customer_name, u.email AS customer_email, u.address AS customer_address
        FROM sale s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
        ";
        
        $stmt = $conexion->prepare($query);
        $stmt->bind_param('i', $sale_id);
        $stmt->execute();
        $sale_result = $stmt->get_result();
        
        if ($sale_result->num_rows > 0) {
            $sale = $sale_result->fetch_assoc();
            
            // Obtener detalles de la venta
            $details_query = "
            SELECT sd.*, p.name AS product_name, p.price AS product_price
            FROM saledetail sd
            JOIN product p ON sd.product_id = p.id
            WHERE sd.sale_id = ?
            ";
            
            $details_stmt = $conexion->prepare($details_query);
            $details_stmt->bind_param('i', $sale_id);
            $details_stmt->execute();
            $details_result = $details_stmt->get_result();
            
            // Generar HTML de la factura
            $invoice_html = generateInvoiceHTML($sale, $details_result, $invoice_number);
            
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
        }
    }
    
    header('Content-Type: text/plain; charset=utf-8');
    die('Factura no encontrada.');
}

// Obtener el HTML de la factura desde la sesión (para clientes)
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

function generateInvoiceHTML($sale, $details_result, $invoice_number) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Factura ' . $invoice_number . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .company-name { font-size: 24px; font-weight: bold; color: #2ecc71; }
            .invoice-info { margin-bottom: 30px; }
            .customer-info { margin-bottom: 30px; }
            .table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            .table th, .table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
            .table th { background-color: #f8f9fa; }
            .total { text-align: right; font-size: 18px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company-name">ToolSoft</div>
            <div>Ferretería y Herramientas</div>
        </div>
        
        <div class="invoice-info">
            <h2>FACTURA</h2>
            <p><strong>Número:</strong> ' . $invoice_number . '</p>
            <p><strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime($sale['date'])) . '</p>
        </div>
        
        <div class="customer-info">
            <h3>Información del Cliente</h3>
            <p><strong>Nombre:</strong> ' . htmlspecialchars($sale['customer_name']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($sale['customer_email']) . '</p>
            <p><strong>Dirección:</strong> ' . htmlspecialchars($sale['customer_address']) . '</p>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>';
    
    while ($detail = $details_result->fetch_assoc()) {
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($detail['product_name']) . '</td>
                    <td>' . $detail['quantity'] . '</td>
                    <td>$' . number_format($detail['product_price'], 2) . '</td>
                    <td>$' . number_format($detail['quantity'] * $detail['product_price'], 2) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="total">
            <p><strong>Total: $' . number_format($sale['total'], 2) . '</strong></p>
        </div>
        
        <div style="margin-top: 50px; text-align: center; color: #666;">
            <p>Gracias por su compra</p>
            <p>ToolSoft - Ferretería y Herramientas</p>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>