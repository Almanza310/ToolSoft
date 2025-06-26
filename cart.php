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

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'add' && isset($_GET['name']) && isset($_GET['price'])) {
        $name = $_GET['name'];
        $price = floatval($_GET['price']);
        $image = $_GET['image'] ?? 'imagenes/placeholder.jpg';
        
        // Add or update item in cart
        if (isset($_SESSION['cart'][$name])) {
            $_SESSION['cart'][$name]['quantity']++;
        } else {
            $_SESSION['cart'][$name] = [
                'name' => $name,
                'price' => $price,
                'quantity' => 1,
                'image' => $image
            ];
        }
        $_SESSION['success_message'] = "Producto agregado al carrito.";
        header('Location: cart.php');
        exit;
    }
    
    if ($action === 'remove' && isset($_GET['name'])) {
        $name = $_GET['name'];
        if (isset($_SESSION['cart'][$name])) {
            unset($_SESSION['cart'][$name]);
            $_SESSION['success_message'] = "Producto eliminado del carrito.";
        }
        header('Location: cart.php');
        exit;
    }
    
    if ($action === 'update' && isset($_GET['name']) && isset($_GET['quantity'])) {
        $name = $_GET['name'];
        $quantity = intval($_GET['quantity']);
        
        if ($quantity > 0 && isset($_SESSION['cart'][$name])) {
            $_SESSION['cart'][$name]['quantity'] = $quantity;
            $_SESSION['success_message'] = "Cantidad actualizada.";
        } elseif ($quantity <= 0 && isset($_SESSION['cart'][$name])) {
            unset($_SESSION['cart'][$name]);
            $_SESSION['success_message'] = "Producto eliminado del carrito.";
        }
        header('Location: cart.php');
        exit;
    }
    
    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        $_SESSION['success_message'] = "Carrito vaciado.";
        header('Location: cart.php');
        exit;
    }
}

// Calculate total
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Get messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Clear messages after displaying
$_SESSION['success_message'] = '';
$_SESSION['error_message'] = '';
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
        <a href="customer_products.php">Productos</a>
        <a href="contacto.php">Contacto</a>
        <a href="cart.php" class="cart-link active"><span class="cart-icon">🛒</span> Carrito</a>
    </nav>
</header>
<div class="cart-container">
    <h1 class="cart-title">🛒 Tu Carrito de Compras</h1>
    <?php if ($success_message): ?>
        <div class="cart-message success"> <?php echo htmlspecialchars($success_message); ?> </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="cart-message error"> <?php echo htmlspecialchars($error_message); ?> </div>
    <?php endif; ?>

    <?php if (empty($_SESSION['cart'])): ?>
        <div class="empty-cart">
            <span style="font-size:2.5rem;">🛒</span><br>
            ¡Tu carrito está vacío!<br>
            <a href="customer_products.php" class="continue-btn">Seguir comprando</a>
        </div>
    <?php else: ?>
        <table class="cart-table" id="cart-table">
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
        <div class="cart-summary">
            <div class="total"><span style="font-size:1.3em;">💵</span> Total: <span id="cart-total">$<?php echo number_format($total, 2); ?></span></div>
            <div class="cart-actions">
                <button class="clear-cart-btn" type="button">Vaciar carrito</button>
                <a href="customer_products.php" class="continue-btn">Seguir comprando</a>
                <a href="checkout.php" class="checkout-btn">Finalizar compra</a>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="cart.js"></script>
</body>
</html>
<?php $conexion->close(); ?>