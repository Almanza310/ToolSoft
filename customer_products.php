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

// Debug session state
error_log("Session state in customer_products.php: " . print_r($_SESSION, true));

// Initialize session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Test database connection
if ($conexion->connect_error) {
    error_log("Error de conexión a la base de datos: " . $conexion->connect_error);
    die("Error de conexión a la base de datos: " . $conexion->connect_error);
} else {
    error_log("Conexión a la base de datos exitosa");
}

// Fetch available products (stock > 0), including the image field
$result_products = $conexion->query("SELECT id, name, description, price, stock, image FROM product ORDER BY stock > 0 DESC, name ASC");
if ($result_products === false) {
    error_log("Error al ejecutar la consulta: " . $conexion->error);
    die("Error al ejecutar la consulta: " . $conexion->error);
}
$products = $result_products->fetch_all(MYSQLI_ASSOC);
error_log("Productos recuperados: " . print_r($products, true));

// Set default image if the 'image' column is null
foreach ($products as &$product) {
    $product['image'] = !empty($product['image']) ? $product['image'] : 'imagenes/placeholder.jpg';
    error_log("Producto procesado: " . print_r($product, true));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛠 Productos Disponibles - ToolSoft</title>
    <link rel="stylesheet" href="CSS/stylesproducts.css">
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

    <div class="products-panel">
        <div class="panel-title">Productos Disponibles</div>
        <div class="products-grid">
            <?php
            $product_count = 0;
            if (empty($products)) {
                echo '<p>No se encontraron productos con stock mayor a 0.</p>';
                error_log("No se encontraron productos para renderizar.");
            } else {
                foreach ($products as $index => $product) {
                    $product_id = $product['id'];
                    error_log("Producto en índice $index - ID $product_id: " . print_r($product, true));
                    $add_to_cart_url = "cart.php?action=add&name=" . urlencode($product['name']) . "&price=" . urlencode($product['price']) . "&image=" . urlencode($product['image']);
                    error_log("Add to cart URL for product {$product['name']}: $add_to_cart_url");
                    $is_logged_in = isset($_SESSION['customer_logged_in']);
                    error_log("User logged in: " . ($is_logged_in ? "Yes" : "No"));
            ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" />
                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                    <div class="product-stock">Stock: <?php echo htmlspecialchars($product['stock']); ?></div>
                    <div class="product-description"><?php echo htmlspecialchars($product['description'] ?? 'Sin descripción'); ?></div>
                    <?php if (isset($_SESSION['customer_logged_in'])): ?>
                        <a href="<?php echo $add_to_cart_url; ?>" class="product-action">Añadir al Carrito</a>
                    <?php else: ?>
                        <a href="logeo_del_prototipo.php" class="product-action">Añadir al Carrito</a>
                    <?php endif; ?>
                </div>
            <?php
                    $product_count++;
                }
            }
            error_log("Total productos renderizados: $product_count");
            ?>
        </div>
        <?php if (empty($products)): ?>
            <p class="no-products">No hay productos disponibles en este momento.</p>
        <?php endif; ?>

        <!-- Display Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php $_SESSION['success_message'] = ''; ?>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php $_SESSION['error_message'] = ''; ?>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conexion->close(); ?>