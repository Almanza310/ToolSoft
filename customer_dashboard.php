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

// Debug: Log session data
error_log("Dashboard - Session data: " . print_r($_SESSION, true));

// Check if the user is logged in
if (!isset($_SESSION['customer_logged_in'])) {
    $_SESSION['error_message'] = "Debes iniciar sesión para acceder a tu perfil.";
    header('Location: logeo_del_prototipo.php');
    exit;
}

// Get customer data from users table
$customer_id = $_SESSION['customer_id'] ?? 0;
$customer = null;

error_log("Dashboard - Customer ID from session: $customer_id");

if ($customer_id > 0) {
    // Query the users table instead of customers table
    $stmt = $conexion->prepare("SELECT id, name, email, address, phone FROM users WHERE id = ? AND role = 'customer'");
    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $customer = $result->fetch_assoc();
            error_log("Dashboard - Customer data found: " . print_r($customer, true));
            // Actualizar la sesión con los datos más recientes
            $_SESSION['customer_email'] = $customer['email'];
        } else {
            error_log("Dashboard - No customer found with ID: $customer_id");
            
            // Try to find customer by email if available in session
            if (isset($_SESSION['customer_email'])) {
                $email = $_SESSION['customer_email'];
                error_log("Dashboard - Trying to find customer by email: $email");
                
                $stmt2 = $conexion->prepare("SELECT id, name, email, address, phone FROM users WHERE email = ? AND role = 'customer'");
                if ($stmt2) {
                    $stmt2->bind_param("s", $email);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    
                    if ($result2 && $result2->num_rows > 0) {
                        $customer = $result2->fetch_assoc();
                        $_SESSION['customer_id'] = $customer['id']; // Update session with correct ID
                        error_log("Dashboard - Customer found by email: " . print_r($customer, true));
                    }
                    $stmt2->close();
                }
            }
            
            // If still no customer found, show debug info
            if (!$customer) {
                $_SESSION['error_message'] = "No se encontró información del cliente. ID: $customer_id";
                error_log("Dashboard - Customer not found");
            }
        }
        $stmt->close();
    } else {
        error_log("Dashboard - Error preparing customer query: " . $conexion->error);
        $_SESSION['error_message'] = "Error al obtener datos del cliente: " . $conexion->error;
    }
} else {
    error_log("Dashboard - Invalid customer ID: $customer_id");
    $_SESSION['error_message'] = "ID de cliente no válido: $customer_id";
}

// If no customer data, create default structure to avoid errors
if (!$customer) {
    $customer = [
        'id' => $customer_id,
        'name' => '',
        'email' => $_SESSION['customer_email'] ?? '',
        'address' => '',
        'phone' => ''
    ];
    error_log("Dashboard - Using default customer structure");
}

// Get order history - Modified to work with users table
$orders = [];
if ($customer_id > 0) {
    // Note: The sale table references user_id, so we use that
    $query = "SELECT s.id, s.date as sale_date, s.total, 
              COUNT(sd.id) as item_count 
              FROM sale s 
              LEFT JOIN saledetail sd ON s.id = sd.sale_id 
              WHERE s.user_id = ? 
              GROUP BY s.id 
              ORDER BY s.date DESC";
    
    $stmt = $conexion->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
        }
        $stmt->close();
    }
}

// Handle profile update
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// Clear messages after displaying
$_SESSION['success_message'] = '';
$_SESSION['error_message'] = '';

// Active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👤 Mi Perfil - ToolSoft</title>
    <link rel="stylesheet" href="CSS/stylescustomer.css">
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

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2 class="dashboard-title">Mi Cuenta</h2>
        </div>
        
        <!-- Debug info if no customer data -->
        <?php if (empty($customer['name']) && empty($customer['email'])): ?>
            <div class="debug-info">
                <strong>⚠️ Información de Debug:</strong><br>
                Customer ID: <?php echo $customer_id; ?><br>
                Session Email: <?php echo $_SESSION['customer_email'] ?? 'No definido'; ?><br>
                Tabla utilizada: users (role='customer')<br>
            </div>
        <?php endif; ?>
        
        <!-- Display Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <div class="dashboard-tabs">
            <a href="?tab=profile" class="dashboard-tab <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">Mi Perfil</a>
            <a href="?tab=orders" class="dashboard-tab <?php echo $active_tab === 'orders' ? 'active' : ''; ?>">Mis Pedidos</a>
        </div>
        
        <div class="dashboard-content">
            <?php if ($active_tab === 'profile'): ?>
                <div class="profile-section">
                    <div class="profile-info">
                        <h3>Información Personal</h3>
                        <div class="profile-detail">
                            <span class="profile-label">Nombre:</span>
                            <span class="profile-value"><?php echo !empty($customer['name']) ? htmlspecialchars($customer['name']) : 'No disponible'; ?></span>
                        </div>
                        <div class="profile-detail">
                            <span class="profile-label">Email:</span>
                            <span class="profile-value"><?php echo !empty($customer['email']) ? htmlspecialchars($customer['email']) : 'No disponible'; ?></span>
                        </div>
                        <div class="profile-detail">
                            <span class="profile-label">Dirección:</span>
                            <span class="profile-value"><?php echo !empty($customer['address']) ? htmlspecialchars($customer['address']) : 'No disponible'; ?></span>
                        </div>
                        <div class="profile-detail">
                            <span class="profile-label">Teléfono:</span>
                            <span class="profile-value"><?php echo !empty($customer['phone']) ? htmlspecialchars($customer['phone']) : 'No disponible'; ?></span>
                        </div>
                    </div>
                    
                    <div class="profile-form">
                        <h3>Actualizar Perfil</h3>
                        <form action="update_profile.php" method="POST">
                            <div class="form-group">
                                <label for="name" class="form-label">Nombre:</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($customer['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="address" class="form-label">Dirección:</label>
                                <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone" class="form-label">Teléfono:</label>
                                <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
                        </form>
                    </div>
                </div>
            <?php elseif ($active_tab === 'orders'): ?>
                <h3>Historial de Pedidos</h3>
                <?php if (empty($orders)): ?>
                    <p>No tienes pedidos realizados.</p>
                <?php else: ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Pedido #</th>
                                <th>Fecha</th>
                                <th>Productos</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($order['item_count']); ?> productos</td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td><span class="badge badge-success">Completado</span></td>
                                    <td>
                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="order-detail-btn">Ver Detalles</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conexion->close(); ?>