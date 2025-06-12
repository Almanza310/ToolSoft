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

// Get customer data with improved error handling
$customer_id = $_SESSION['customer_id'] ?? 0;
$customer = null;

error_log("Dashboard - Customer ID from session: $customer_id");

if ($customer_id > 0) {
    // Try different approaches to get customer data
    $stmt = $conexion->prepare("SELECT id, name, email, address, phone FROM customers WHERE id = ?");
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
                
                $stmt2 = $conexion->prepare("SELECT id, name, email, address, phone FROM customers WHERE email = ?");
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
                error_log("Dashboard - Customer not found, redirecting to debug");
                // Don't redirect, show debug info instead
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

// Get order history
$orders = [];
if ($customer_id > 0) {
    $query = "SELECT s.id, s.sale_date, s.total, 
              COUNT(sd.id) as item_count 
              FROM sale s 
              LEFT JOIN saledetail sd ON s.id = sd.sale_id 
              WHERE s.customer_id = ? 
              GROUP BY s.id 
              ORDER BY s.sale_date DESC";
    
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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #6c757d;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        header {
            background-color: #333;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        nav {
            display: flex;
            gap: 1rem;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        nav a:hover {
            background-color: #555;
        }
        
        .cart-link {
            position: relative;
        }
        
        .cart-icon {
            margin-right: 0.25rem;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .dashboard-title {
            font-size: 2rem;
            color: #333;
            margin: 0;
        }
        
        .dashboard-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .dashboard-tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: var(--secondary-color);
            text-decoration: none;
        }
        
        .dashboard-tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .dashboard-tab:hover:not(.active) {
            border-bottom-color: var(--border-color);
            color: var(--dark-color);
        }
        
        .dashboard-content {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .profile-section {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .profile-info {
            background-color: var(--light-color);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .profile-info h3 {
            margin-top: 0;
            color: var(--dark-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
        }
        
        .profile-detail {
            margin-bottom: 1rem;
        }
        
        .profile-label {
            font-weight: bold;
            color: var(--secondary-color);
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .profile-value {
            color: var(--dark-color);
        }
        
        .profile-form {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #218838;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .debug-info {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .orders-table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .orders-table tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-align: center;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .order-detail-btn {
            background-color: var(--info-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .order-detail-btn:hover {
            background-color: #138496;
        }
        
        @media (max-width: 768px) {
            .profile-section {
                grid-template-columns: 1fr;
            }
            
            .dashboard-tabs {
                flex-wrap: wrap;
            }
            
            .dashboard-tab {
                padding: 0.75rem 1rem;
            }
        }
    </style>
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
                <a href="debug_customer_session.php" target="_blank">🔍 Ver diagnóstico completo</a>
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