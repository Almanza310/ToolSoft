<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';
session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'");

// Check if the user is logged in
if (!isset($_SESSION['customer_logged_in'])) {
    $_SESSION['error_message'] = "Debes iniciar sesión para ver los detalles del pedido.";
    header('Location: logeo_del_prototipo.php');
    exit;
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    $_SESSION['error_message'] = "ID de pedido no válido.";
    header('Location: customer_dashboard.php?tab=orders');
    exit;
}

// Get customer ID from session
$customer_id = $_SESSION['customer_id'] ?? 0;

// Get order details
$order = null;
$order_items = [];

// Get order header - CORREGIDO: usar 'date' en lugar de 'sale_date' y 'user_id' en lugar de 'customer_id'
$stmt = $conexion->prepare("
    SELECT s.id, s.date as sale_date, s.total 
    FROM sale s 
    WHERE s.id = ? AND s.user_id = ?
");
$stmt->bind_param("ii", $order_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Pedido no encontrado o no tienes permiso para verlo.";
    header('Location: customer_dashboard.php?tab=orders');
    exit;
}

$order = $result->fetch_assoc();
$stmt->close();

// Get order items
$stmt = $conexion->prepare("
    SELECT sd.id, sd.quantity, sd.subtotal, p.name, p.price, p.image 
    FROM saledetail sd 
    JOIN product p ON sd.product_id = p.id 
    WHERE sd.sale_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();

// Generate invoice number (format: INV-YYYYMMDD-XXXX)
$invoice_number = 'INV-' . date('Ymd', strtotime($order['sale_date'])) . '-' . str_pad($order_id, 4, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧾 Detalle de Pedido #<?php echo $order_id; ?> - ToolSoft</title>
    <link rel="stylesheet" href="CSS/stylesorder_detail.css">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <header>
        <div class="logo">🛠 ToolSoft</div>
        <nav>
            <a href="interfaz_prototipo.php">Inicio</a>
            <a href="customer_products.php">Productos</a>
            <a href="contacto.php">Contacto</a>
            <a href="cart.php" class="cart-link">
                <span class="cart-icon">🛒</span> Carrito
            </a>
            <?php if (isset($_SESSION['customer_logged_in'])): ?>
                <a href="customer_dashboard.php">Mi Perfil</a>
                <a href="force_logout.php">Cerrar Sesión</a>
            <?php else: ?>
                <a href="logeo_del_prototipo.php">Inicia Sesión</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="order-container">
        <div class="order-header">
            <h2 class="order-title">🧾 Detalle de Pedido #<?php echo $order_id; ?></h2>
            <div class="order-actions">
                <a href="download_invoice.php?invoice=<?php echo urlencode($invoice_number); ?>" class="btn btn-primary">
                    📄 Descargar Factura
                </a>
            </div>
        </div>
        
        <div class="order-content">
            <div class="order-info">
                <div class="order-info-item">
                    <div class="order-info-label">Número de Pedido</div>
                    <div class="order-info-value">#<?php echo $order_id; ?></div>
                </div>
                <div class="order-info-item">
                    <div class="order-info-label">Fecha del Pedido</div>
                    <div class="order-info-value"><?php echo date('d/m/Y H:i', strtotime($order['sale_date'])); ?></div>
                </div>
                <div class="order-info-item">
                    <div class="order-info-label">Estado del Pedido</div>
                    <div class="order-info-value" style="color: var(--success-color);">✅ Completado</div>
                </div>
                <div class="order-info-item">
                    <div class="order-info-label">Número de Factura</div>
                    <div class="order-info-value"><?php echo $invoice_number; ?></div>
                </div>
            </div>
            
            <div class="order-items">
                <h3 class="order-items-title">📦 Productos Pedidos</h3>
                <?php if (empty($order_items)): ?>
                    <p style="text-align: center; color: var(--secondary-color); font-size: 1.1rem; padding: 2rem;">
                        No se encontraron productos para este pedido.
                    </p>
                <?php else: ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Imagen</th>
                                <th>Precio Unitario</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             onerror="this.src='imagenes/placeholder.jpg'">
                                    </td>
                                    <td style="font-weight: 600; color: var(--primary-color);">$<?php echo number_format($item['price'], 2); ?></td>
                                    <td style="text-align: center; font-weight: 600;"><?php echo $item['quantity']; ?></td>
                                    <td style="font-weight: 700; color: var(--success-color);">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="order-summary">
                <div class="order-total">
                    <span>💰 Total del Pedido:</span>
                    <span style="color: var(--success-color);">$<?php echo number_format($order['total'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <a href="customer_dashboard.php?tab=orders" class="back-link">
            ← Volver a Mis Pedidos
        </a>
    </div>

    <script>
        // Auto-scroll to top when page loads
        window.addEventListener('load', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Add hover effects to table rows
        document.querySelectorAll('.items-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.02)';
                this.style.zIndex = '10';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.zIndex = '1';
            });
        });
        
        // Add hover effects to info items
        document.querySelectorAll('.order-info-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>
<?php $conexion->close(); ?>