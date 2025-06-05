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

// Check if the user is logged in
if (!isset($_SESSION['customer_logged_in'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: logeo_del_prototipo.php');
    exit;
}

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Debug session state
error_log("Session state in cart.php before action: " . print_r($_SESSION, true));

// Handle cart actions (add/remove)
if (isset($_GET['action'])) {
    $action = $_GET['action'] ?? '';
    $name = isset($_GET['name']) ? urldecode($_GET['name']) : '';
    $price = isset($_GET['price']) ? floatval(urldecode($_GET['price'])) : 0;
    $image = isset($_GET['image']) ? urldecode($_GET['image']) : 'imagenes/placeholder.jpg';

    error_log("Cart action: $action, name: $name, price: $price, image: $image");

    if ($action === 'add') {
        // Add product to cart or increment quantity
        if (!empty($name) && $price > 0) {
            if (isset($_SESSION['cart'][$name])) {
                $_SESSION['cart'][$name]['quantity']++;
                error_log("Incremented quantity for product: $name");
            } else {
                $_SESSION['cart'][$name] = [
                    'name' => $name,
                    'price' => $price,
                    'quantity' => 1,
                    'image' => $image,
                ];
                error_log("Added new product to cart: $name");
            }
        } else {
            error_log("Failed to add product to cart: name or price invalid (name: $name, price: $price)");
        }
    } elseif ($action === 'remove') {
        // Remove product from cart
        if (isset($_SESSION['cart'][$name])) {
            unset($_SESSION['cart'][$name]);
            error_log("Removed product from cart: $name");
        }
    }
    // Debug session state after action
    error_log("Session state in cart.php after action: " . print_r($_SESSION, true));
    // Redirect to avoid form resubmission
    header('Location: cart.php');
    exit;
}

$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛒 Carrito de Compras - ToolSoft</title>
    <link rel="stylesheet" href="CSS/stylescart.css">
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

    <div class="cart-container">
        <h2 class="cart-title">Mi Carrito</h2>
        <?php if (!empty($_SESSION['cart'])): ?>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Imagen</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
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
                            <td>
                                <a href="cart.php?action=remove&name=<?php echo urlencode($name); ?>" class="remove-btn">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">
                Total: $<?php echo number_format($total, 2); ?>
            </div>
            <div class="cart-actions">
                <a href="checkout.php" class="checkout-btn">Proceder a Pago</a>
            </div>
        <?php else: ?>
            <p class="empty-cart">Tu carrito está vacío.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conexion->close(); ?>