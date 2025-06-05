<?php
session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'");

// Check if success message exists
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '¡Gracias por tu compra!';
$_SESSION['success_message'] = ''; // Clear the message after displaying it
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✅ Pago Exitoso - ToolSoft</title>
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
                <a href="customer_logout.php">Cerrar Sesión</a>
            <?php else: ?>
                <a href="logeo_del_prototipo.php">Inicia Sesión</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="checkout-container">
        <h2 class="checkout-title">¡Pago Completado!</h2>
        <div class="success-message">
            <p><?php echo htmlspecialchars($success_message); ?></p>
            <a href="customer_products.php" class="continue-btn">Seguir Comprando</a>
        </div>
    </div>
</body>
</html>