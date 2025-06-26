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
    // Ruta absoluta para Dompdf (requiere ruta absoluta en servidor local)
    $logo_path = __DIR__ . '/uploads/ferre.png';
    $logo_base64 = '';
    if (file_exists($logo_path)) {
        $logo_data = file_get_contents($logo_path);
        $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
    }
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Factura #' . $invoice_number . '</title>
        <style>
            body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; background: #f6f8fa; }
            .invoice-box { max-width: 750px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(46,204,113,0.10); padding: 36px 32px 28px 32px; }
            .header {
                background: #2ecc71;
                color: #fff;
                padding: 18px 32px;
                border-radius: 10px 10px 0 0;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .header-left {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .logo-img {
                height: 48px;
                margin-right: 10px;
                vertical-align: middle;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 2px 8px rgba(46,204,113,0.10);
            }
            .header .logo-text {
                font-size: 2.1rem;
                font-weight: bold;
                letter-spacing: 1px;
                font-family: Arial Black, Arial, sans-serif;
            }
            .header .invoice-info {
                text-align: right;
                font-size: 1rem;
            }
            .info-section {
                display: flex;
                justify-content: space-between;
                margin: 30px 0 18px 0;
                font-size: 1rem;
            }
            .info-box {
                width: 48%;
                background: #f8f9fa;
                border-radius: 8px;
                padding: 16px 18px;
                color: #333;
            }
            .info-title {
                font-weight: bold;
                color: #2ecc71;
                margin-bottom: 6px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 18px;
            }
            th {
                background: #2ecc71;
                color: #fff;
                font-weight: 600;
                padding: 12px 8px;
                border-radius: 4px 4px 0 0;
                font-size: 1rem;
            }
            td {
                padding: 10px 8px;
                border-bottom: 1px solid #e0e0e0;
                font-size: 1rem;
            }
            tr.item-row:nth-child(even) td {
                background: #f8f9fa;
            }
            .total-row td {
                font-weight: bold;
                background: #eafaf1;
                color: #27ae60;
                font-size: 1.1rem;
                border-top: 2px solid #2ecc71;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                color: #888;
                font-size: 0.98rem;
            }
        </style>
    </head>
    <body>
        <div class="invoice-box">
            <div class="header">
                <div class="header-left">
                    ' . ($logo_base64 ? '<img src="' . $logo_base64 . '" class="logo-img" alt="Logo ToolSoft">' : '') . '
                    <span class="logo-text">ToolSoft</span>
                </div>
                <div class="invoice-info">
                    Factura #: ' . $invoice_number . '<br>
                    Fecha: ' . $date . '
                </div>
            </div>
            <div class="info-section">
                <div class="info-box">
                    <div class="info-title">Datos de la empresa</div>
                    ToolSoft, Inc.<br>
                    Calle Principal #123<br>
                    Ciudad, CP 12345<br>
                    Tel: (123) 456-7890<br>
                    Email: info@toolsoft.com
                </div>
                <div class="info-box">
                    <div class="info-title">Datos del cliente</div>
                    ' . htmlspecialchars($customer_name) . '<br>
                    ' . htmlspecialchars($customer_email) . '<br>
                    ' . htmlspecialchars($customer_address) . '<br>
                    ' . htmlspecialchars($customer_phone) . '
                </div>
            </div>
            <table>
                <tr>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Cantidad</th>
                    <th>Subtotal</th>
                </tr>';
    foreach ($cart_items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $html .= '
                <tr class="item-row">
                    <td>' . htmlspecialchars($item['name']) . '</td>
                    <td>$' . number_format($item['price'], 2) . '</td>
                    <td>' . $item['quantity'] . '</td>
                    <td>$' . number_format($subtotal, 2) . '</td>
                </tr>';
    }
    $html .= '
                <tr class="total-row">
                    <td colspan="3" style="text-align:right;">Total:</td>
                    <td>$' . number_format($total, 2) . '</td>
                </tr>
            </table>
            <div class="footer">
                Gracias por tu compra en ToolSoft.<br>
                Si tienes alguna pregunta, contáctanos.<br>
                <span style="color:#bbb;">Esta factura fue generada automáticamente el ' . $date . '</span>
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
    <title>🛒 Checkout - ToolSoft</title>
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
            <a href="customer_products.php">Productos</a>
            <a href="contacto.php">Contacto</a>
            <a href="cart.php" class="cart-link"><span class="cart-icon">🛒</span> Carrito</a>
        </nav>
    </header>
    <div class="checkout-container">
        <h1 class="checkout-title">🧾 Confirmar Pedido</h1>
        <div class="order-summary">
            <h3>Resumen de tu pedido</h3>
            <table class="cart-table" id="checkout-table">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($_SESSION['cart'] as $item): ?>
                    <tr data-name="<?php echo htmlspecialchars($item['name']); ?>">
                        <td><img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-img"></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td>
                            <div class="quantity-controls">
                                <button class="quantity-btn minus" type="button">-</button>
                                <input type="number" class="quantity-input" min="1" value="<?php echo $item['quantity']; ?>">
                                <button class="quantity-btn plus" type="button">+</button>
                            </div>
                        </td>
                        <td class="item-subtotal">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                        <td>
                            <button class="remove-btn" type="button" title="Eliminar"><span style="font-size:1.2em;">🗑️</span></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total"><span style="font-size:1.3em;">💵</span> Total: <span id="checkout-total">$<?php echo number_format($total, 2); ?></span></div>
        </div>
        <div class="customer-info">
            <h3>Datos de envío</h3>
            <ul>
                <li><b>Nombre:</b> <?php echo htmlspecialchars($customer_name); ?></li>
                <li><b>Email:</b> <?php echo htmlspecialchars($customer_email); ?></li>
                <li><b>Dirección:</b> <?php echo htmlspecialchars($customer_address); ?></li>
                <li><b>Teléfono:</b> <?php echo htmlspecialchars($customer_phone); ?></li>
            </ul>
        </div>
        <div class="payment-section">
            <form id="checkout-form" method="post">
                <input type="hidden" name="confirm_payment" value="1">
                <button type="submit" class="confirm-btn">Confirmar compra</button>
            </form>
            <a href="cart.php" class="continue-btn">Volver al carrito</a>
        </div>
        <div id="checkout-message"></div>
    </div>
    <script src="checkout.js"></script>
</body>
</html>
<?php $conexion->close(); ?>