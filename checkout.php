<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';
session_start();

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'");

// Regenerate session ID
session_regenerate_id(true);

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: logeo_del_prototipo.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Debug session state
error_log("Session state in checkout.php: " . print_r($_SESSION, true));

// Check if the user is logged in
if (!isset($_SESSION['customer_logged_in'])) {
    error_log("User not logged in, redirecting to logeo_del_prototipo.php");
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: logeo_del_prototipo.php');
    exit;
}
error_log("User is logged in");

// Initialize cart if not set
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    error_log("Cart is empty, redirecting to cart.php");
    header('Location: cart.php');
    exit;
}
error_log("Cart is not empty");

// Get customer email from database - VERSIÓN CORREGIDA PARA USAR TABLA USERS
$customer_id = $_SESSION['customer_id'] ?? 0;
$customer_email = '';
$customer_name = 'Cliente';
$customer_address = 'No disponible';
$customer_phone = 'No disponible';

error_log("Customer ID from session: $customer_id");

if ($customer_id > 0) {
    $stmt = $conexion->prepare("SELECT email, name, address, phone FROM users WHERE id = ? AND role = 'customer'");
    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $customer = $result->fetch_assoc();
            $customer_email = $customer['email'] ?? '';
            $customer_name = $customer['name'] ?? 'Cliente';
            $customer_address = $customer['address'] ?? 'No disponible';
            $customer_phone = $customer['phone'] ?? 'No disponible';
            error_log("Customer data found: " . print_r($customer, true));
        } else {
            error_log("No customer found with ID: $customer_id");
        }
        $stmt->close();
    } else {
        error_log("Error preparing customer query: " . $conexion->error);
    }
}

// Si no se encuentra email, usar email de la sesión como fallback
if (empty($customer_email) && isset($_SESSION['customer_email'])) {
    $customer_email = $_SESSION['customer_email'];
    error_log("Using email from session: $customer_email");
}

// Si aún no hay email, usar un email por defecto para testing
if (empty($customer_email)) {
    $customer_email = 'cliente@ejemplo.com'; // Email por defecto para testing
    error_log("Using default email for testing: $customer_email");
    $_SESSION['warning_message'] = "Se está usando un email por defecto. Por favor actualiza tu perfil.";
}

error_log("Final customer email: $customer_email");

// Calculate total
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['quantity'];
}
error_log("Cart total: $total");

// Fetch a valid admin_id (any admin user)
$admin_id = null;
$stmt = $conexion->prepare("SELECT id FROM users WHERE role = 'administrator' LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $admin_id = $admin['id']; // Will pick the first admin (e.g., 8 in your current data)
        error_log("Valid admin_id found: $admin_id");
    } else {
        error_log("No admin found in users table. Please add an admin user.");
        $_SESSION['error_message'] = "No se encontró un administrador en el sistema. Contacte al soporte.";
        header('Location: cart.php');
        exit;
    }
    $stmt->close();
} else {
    error_log("Error preparing admin query: " . $conexion->error);
    $_SESSION['error_message'] = "Error al procesar la consulta de administrador.";
    header('Location: cart.php');
    exit;
}

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // Start transaction
    $conexion->begin_transaction();
    
    try {
        // 1. Create sale record
        $stmt = $conexion->prepare("INSERT INTO sale (user_id, admin_id, date, total) VALUES (?, ?, NOW(), ?)");
        $stmt->bind_param("iid", $customer_id, $admin_id, $total);
        $stmt->execute();
        $sale_id = $conexion->insert_id;
        $stmt->close();
        
        // 2. Create sale details and update inventory
        foreach ($_SESSION['cart'] as $item) {
            // Get product ID from name
            $stmt = $conexion->prepare("SELECT id, stock FROM product WHERE name = ?");
            $stmt->bind_param("s", $item['name']);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();
            
            if (!$product) {
                throw new Exception("Producto no encontrado: " . $item['name']);
            }
            
            $product_id = $product['id'];
            $quantity = $item['quantity'];
            $subtotal = $item['price'] * $quantity;
            
            // Check if enough stock
            if ($product['stock'] < $quantity) {
                throw new Exception("Stock insuficiente para: " . $item['name']);
            }
            
            // Insert sale detail
            $stmt = $conexion->prepare("INSERT INTO saledetail (sale_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $sale_id, $product_id, $quantity, $subtotal);
            $stmt->execute();
            $stmt->close();
            
            // Update product stock
            $new_stock = $product['stock'] - $quantity;
            $stmt = $conexion->prepare("UPDATE product SET stock = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_stock, $product_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // 3. Generate invoice number (format: INV-YYYYMMDD-XXXX)
        $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($sale_id, 4, '0', STR_PAD_LEFT);
        
        // 4. Generate invoice HTML
        $invoice_html = generateInvoiceHTML($invoice_number, $customer_name, $customer_email, $customer_address, $customer_phone, $_SESSION['cart'], $total);
        
        // 5. Save invoice to file (instead of sending email)
        $invoice_saved = saveInvoiceToFile($invoice_number, $invoice_html);
        
        // Commit transaction
        $conexion->commit();
        
        // Store invoice in session for display on success page
        $_SESSION['invoice_html'] = $invoice_html;
        $_SESSION['invoice_number'] = $invoice_number;
        
        // Clear the cart after successful payment
        $_SESSION['success_message'] = "¡Pago confirmado exitosamente! " . 
            ($invoice_saved ? "Tu factura ha sido generada y está disponible para descargar." : "La factura está disponible para visualizar.");
        $_SESSION['cart'] = [];
        header('Location: order_success.php');
        exit;
        
    } catch (Exception $e) {
        // Roll back transaction on error
        $conexion->rollback();
        error_log("Error en el proceso de pago: " . $e->getMessage());
        $_SESSION['error_message'] = "Error en el proceso de pago: " . $e->getMessage();
        header('Location: cart.php');
        exit;
    }
}

/**
 * Generate HTML for invoice
 */
function generateInvoiceHTML($invoice_number, $customer_name, $customer_email, $customer_address, $customer_phone, $cart_items, $total) {
    $date = date('d/m/Y H:i:s');
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Factura #' . $invoice_number . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
            .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); font-size: 16px; line-height: 24px; }
            .invoice-box table { width: 100%; line-height: inherit; text-align: left; }
            .invoice-box table td { padding: 5px; vertical-align: top; }
            .invoice-box table tr td:nth-child(2) { text-align: right; }
            .invoice-box table tr.top table td { padding-bottom: 20px; }
            .invoice-box table tr.top table td.title { font-size: 45px; line-height: 45px; color: #333; }
            .invoice-box table tr.information table td { padding-bottom: 40px; }
            .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
            .invoice-box table tr.details td { padding-bottom: 20px; }
            .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
            .invoice-box table tr.item.last td { border-bottom: none; }
            .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
            @media only screen and (max-width: 600px) {
                .invoice-box table tr.top table td { width: 100%; display: block; text-align: center; }
                .invoice-box table tr.information table td { width: 100%; display: block; text-align: center; }
            }
        </style>
    </head>
    <body>
        <div class="invoice-box">
            <table cellpadding="0" cellspacing="0">
                <tr class="top">
                    <td colspan="4">
                        <table>
                            <tr>
                                <td class="title">
                                    <h1>🛠 ToolSoft</h1>
                                </td>
                                <td>
                                    Factura #: ' . $invoice_number . '<br>
                                    Fecha: ' . $date . '<br>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <tr class="information">
                    <td colspan="4">
                        <table>
                            <tr>
                                <td>
                                    ToolSoft, Inc.<br>
                                    Calle Principal #123<br>
                                    Ciudad, CP 12345<br>
                                    Tel: (123) 456-7890<br>
                                    Email: info@toolsoft.com
                                </td>
                                <td>
                                    ' . htmlspecialchars($customer_name) . '<br>
                                    ' . htmlspecialchars($customer_email) . '<br>
                                    ' . htmlspecialchars($customer_address) . '<br>
                                    ' . htmlspecialchars($customer_phone) . '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <tr class="heading">
                    <td>Producto</td>
                    <td>Precio</td>
                    <td>Cantidad</td>
                    <td>Subtotal</td>
                </tr>';
    
    foreach ($cart_items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $html .= '
                <tr class="item">
                    <td>' . htmlspecialchars($item['name']) . '</td>
                    <td>$' . number_format($item['price'], 2) . '</td>
                    <td>' . $item['quantity'] . '</td>
                    <td>$' . number_format($subtotal, 2) . '</td>
                </tr>';
    }
    
    $html .= '
                <tr class="total">
                    <td colspan="3"></td>
                    <td>Total: $' . number_format($total, 2) . '</td>
                </tr>
            </table>
            <div style="margin-top: 30px; text-align: center; color: #777;">
                <p>Gracias por tu compra en ToolSoft. Si tienes alguna pregunta, contáctanos.</p>
                <p>Esta factura fue generada automáticamente el ' . $date . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Save invoice to file instead of sending email
 */
function saveInvoiceToFile($invoice_number, $invoice_html) {
    try {
        $facturas_dir = __DIR__ . '/facturas/';
        if (!is_dir($facturas_dir)) {
            if (!mkdir($facturas_dir, 0755, true)) {
                error_log("No se pudo crear el directorio de facturas: $facturas_dir");
                return false;
            }
        }
        
        $file_name = $facturas_dir . 'factura_' . $invoice_number . '.html';
        $bytes_written = file_put_contents($file_name, $invoice_html);
        
        if ($bytes_written !== false) {
            error_log("Factura guardada exitosamente en: $file_name");
            return true;
        } else {
            error_log("Error al guardar la factura en: $file_name");
            return false;
        }
    } catch (Exception $e) {
        error_log("Error al guardar factura: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💳 Proceder al Pago - ToolSoft</title>
    <link rel="stylesheet" href="CSS/stylescheckout.css">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <header>
        <div class="logo">🛠 ToolSoft</div>
        <nav>
            <a href="interfaz_prototipo.php">Inicio</a>
            <a href="contacto.php">Contacto</a>
            <a href="customer_products.php">Productos</a>
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

    <div class="checkout-container">
        <h2 class="checkout-title">💳 Proceder al Pago</h2>
        
        <div class="order-summary">
            <h3>📋 Resumen del Pedido</h3>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Imagen</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['cart'] as $name => $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     onerror="this.src='imagenes/placeholder.jpg'">
                            </td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">
                Total: $<?php echo number_format($total, 2); ?>
            </div>
        </div>
        
        <div class="customer-info">
            <h3>👤 Información del Cliente</h3>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($customer_name); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($customer_email); ?></p>
            <p><strong>Dirección:</strong> <?php echo htmlspecialchars($customer_address); ?></p>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($customer_phone); ?></p>
            <p class="info-note">* La factura será generada y estará disponible para descargar</p>
        </div>
        
        <div class="payment-section">
            <h3>💰 Confirmar Pago</h3>
            <p>Este es un proceso de pago simulado. Haz clic en "Confirmar Pago" para completar tu pedido y generar la factura.</p>
            <form method="POST" action="checkout.php" id="payment-form">
                <button type="submit" name="confirm_payment" class="confirm-btn" id="confirm-btn">
                    ✅ Confirmar Pago
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Prevenir envío múltiple del formulario
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            var submitBtn = document.getElementById('confirm-btn');
            
            // Verificar si el botón ya está deshabilitado
            if (submitBtn.disabled) {
                e.preventDefault();
                return false;
            }
            
            // Deshabilitar el botón y cambiar el texto
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Procesando...';
            submitBtn.style.opacity = '0.7';
            
            // Mostrar mensaje de procesamiento
            setTimeout(function() {
                if (submitBtn.disabled) {
                    submitBtn.textContent = '🔄 Generando factura...';
                }
            }, 1000);
        });
        
        // Agregar efecto hover a los elementos de la tabla
        document.querySelectorAll('.order-summary tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    </script>
</body>
</html>
<?php $conexion->close(); ?>