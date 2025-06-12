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
    <style>
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .cart-title {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .cart-table th,
        .cart-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .cart-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .cart-table img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            background: #007bff;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .quantity-btn:hover {
            background: #0056b3;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
        
        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .remove-btn:hover {
            background: #c82333;
        }
        
        .cart-summary {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .cart-total {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-align: right;
            margin-bottom: 20px;
        }
        
        .cart-actions {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
        }
        
        .btn-primary:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-cart h3 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .cart-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
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
        <h2 class="cart-title">🛒 Tu Carrito de Compras</h2>
        
        <!-- Display Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($_SESSION['cart'])): ?>
            <div class="empty-cart">
                <h3>Tu carrito está vacío</h3>
                <p>¡Agrega algunos productos para comenzar!</p>
                <a href="customer_products.php" class="btn btn-primary">Ver Productos</a>
            </div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Imagen</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th>Acciones</th>
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
                            <td>
                                <div class="quantity-controls">
                                    <a href="cart.php?action=update&name=<?php echo urlencode($name); ?>&quantity=<?php echo $item['quantity'] - 1; ?>" 
                                       class="quantity-btn">-</a>
                                    <input type="number" 
                                           value="<?php echo $item['quantity']; ?>" 
                                           min="1" 
                                           class="quantity-input"
                                           onchange="updateQuantity('<?php echo htmlspecialchars($name); ?>', this.value)">
                                    <a href="cart.php?action=update&name=<?php echo urlencode($name); ?>&quantity=<?php echo $item['quantity'] + 1; ?>" 
                                       class="quantity-btn">+</a>
                                </div>
                            </td>
                            <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                            <td>
                                <a href="cart.php?action=remove&name=<?php echo urlencode($name); ?>" 
                                   class="remove-btn"
                                   onclick="return confirm('¿Estás seguro de que quieres eliminar este producto?')">
                                   Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cart-summary">
                <div class="cart-total">
                    Total: $<?php echo number_format($total, 2); ?>
                </div>
                
                <div class="cart-actions">
                    <a href="customer_products.php" class="btn btn-secondary">Seguir Comprando</a>
                    <a href="cart.php?action=clear" 
                       class="btn btn-danger"
                       onclick="return confirm('¿Estás seguro de que quieres vaciar el carrito?')">
                       Vaciar Carrito
                    </a>
                    
                    <?php if (isset($_SESSION['customer_logged_in'])): ?>
                        <!-- ESTE ES EL ENLACE CORREGIDO PARA PROCEDER AL PAGO -->
                        <a href="checkout.php" class="btn btn-primary">Proceder al Pago</a>
                    <?php else: ?>
                        <a href="logeo_del_prototipo.php" class="btn btn-primary">Iniciar Sesión para Comprar</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateQuantity(productName, quantity) {
            if (quantity < 1) {
                if (confirm('¿Quieres eliminar este producto del carrito?')) {
                    window.location.href = 'cart.php?action=remove&name=' + encodeURIComponent(productName);
                }
                return;
            }
            
            window.location.href = 'cart.php?action=update&name=' + encodeURIComponent(productName) + '&quantity=' + quantity;
        }
        
        // Debug: Mostrar estado del carrito en consola
        console.log('Carrito cargado. Total de productos:', <?php echo count($_SESSION['cart']); ?>);
        console.log('Usuario logueado:', <?php echo isset($_SESSION['customer_logged_in']) ? 'true' : 'false'; ?>);
    </script>
</body>
</html>
<?php $conexion->close(); ?>