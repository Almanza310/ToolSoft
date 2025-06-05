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

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// Get customer email from database
$customer_id = $_SESSION['customer_id'] ?? 0;
error_log("Customer ID: $customer_id");
if ($customer_id) {
    $stmt = $conexion->prepare("SELECT email, name FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $customer_email = $customer['email'] ?? '';
    $customer_name = $customer['name'] ?? 'Cliente';
    error_log("Customer data: " . print_r($customer, true));
    $stmt->close();
} else {
    $customer_email = '';
    $customer_name = 'Cliente';
    error_log("No customer ID found");
}

if (empty($customer_email)) {
    error_log("Customer email not found, redirecting to cart.php");
    $_SESSION['error_message'] = "No se pudo obtener el correo del cliente. Por favor, actualiza tu perfil.";
    header('Location: cart.php');
    exit;
}
error_log("Customer email found: $customer_email");

// Calculate total
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['quantity'];
}
error_log("Cart total: $total");

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // Check if PHPMailer files exist
    $phpmailer_path = 'vendor/PHPMailer/src/';
    if (!file_exists($phpmailer_path . 'Exception.php') || !file_exists($phpmailer_path . 'PHPMailer.php') || !file_exists($phpmailer_path . 'SMTP.php')) {
        error_log("PHPMailer files not found");
        $_SESSION['error_message'] = "Error: Los archivos de PHPMailer no están instalados. Por favor, instálalos en vendor/PHPMailer/src.";
        header('Location: cart.php');
        exit;
    }

    // Include PHPMailer
    require $phpmailer_path . 'Exception.php';
    require $phpmailer_path . 'PHPMailer.php';
    require $phpmailer_path . 'SMTP.php';

    // Generate invoice content
    $invoice = "<h2>Factura de Compra - ToolSoft</h2>";
    $invoice .= "<p>Gracias por tu compra, {$customer_name}!</p>";
    $invoice .= "<p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>";
    $invoice .= "<h3>Detalles del Pedido</h3>";
    $invoice .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    $invoice .= "<thead><tr><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Subtotal</th></tr></thead>";
    $invoice .= "<tbody>";
    foreach ($_SESSION['cart'] as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $invoice .= "<tr>";
        $invoice .= "<td>" . htmlspecialchars($item['name']) . "</td>";
        $invoice .= "<td>$" . number_format($item['price'], 2) . "</td>";
        $invoice .= "<td>" . $item['quantity'] . "</td>";
        $invoice .= "<td>$" . number_format($subtotal, 2) . "</td>";
        $invoice .= "</tr>";
    }
    $invoice .= "</tbody>";
    $invoice .= "</table>";
    $invoice .= "<p><strong>Total:</strong> $" . number_format($total, 2) . "</p>";
    $invoice .= "<p>Si tienes alguna pregunta, contáctanos en soporte@toolsoft.com.</p>";

    // Send email with invoice
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tu_correo@gmail.com'; // Reemplaza con tu correo de Gmail
        $mail->Password = 'tu_contraseña_o_app_password'; // Reemplaza con tu contraseña o contraseña de aplicación
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('tu_correo@gmail.com', 'ToolSoft');
        $mail->addAddress($customer_email, $customer_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Factura de tu Compra - ToolSoft';
        $mail->Body = $invoice;
        $mail->AltBody = strip_tags($invoice);

        $mail->send();
        error_log("Factura enviada al correo: $customer_email");
    } catch (Exception $e) {
        error_log("Error al enviar factura: {$mail->ErrorInfo}");
        $_SESSION['error_message'] = "No se pudo enviar la factura al correo. Error: {$mail->ErrorInfo}";
    }

    // Clear the cart after successful payment
    $_SESSION['success_message'] = "¡Pago confirmado exitosamente! Se ha enviado una factura a tu correo.";
    $_SESSION['cart'] = [];
    header('Location: order_success.php');
    exit;
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
                <a href="force_logout.php">Cerrar Sesión</a>
            <?php else: ?>
                <a href="logeo_del_prototipo.php">Inicia Sesión</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="checkout-container">
        <h2 class="checkout-title">Proceder al Pago</h2>
        <div class="order-summary">
            <h3>Resumen del Pedido</h3>
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
                            <td><img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"></td>
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
        <div class="payment-section">
            <h3>Detalles de Pago</h3>
            <p>Por ahora, este es un proceso simulado. Haz clic en "Confirmar Pago" para completar tu pedido.</p>
            <form method="POST" action="checkout.php">
                <button type="submit" name="confirm_payment" class="confirm-btn">Confirmar Pago</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conexion->close(); ?>