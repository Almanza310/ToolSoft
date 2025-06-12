<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';
session_start();

// Debugging: Log session state on page load
error_log("Session state on admin_dashboard.php load: " . print_r($_SESSION, true));

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'");

// Regenerate session ID (comment out for testing if it causes issues)
// session_regenerate_id(true);

// Initialize session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Preliminary processing of actions
$delete_category = isset($_GET['delete_category']) ? $_GET['delete_category'] : null;
$edit_category = isset($_GET['edit_category']) ? $_GET['edit_category'] : null;
$delete_supplier = isset($_GET['delete_supplier']) ? $_GET['delete_supplier'] : null;
$edit_supplier = isset($_GET['edit_supplier']) ? $_GET['edit_supplier'] : null;

// Session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: logeo_del_prototipo.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Ensure only admins can access this page
if (!isset($_SESSION['admin_logged_in'])) {
    error_log("Access denied: admin_logged_in not set");
    header('Location: logeo_del_prototipo.php');
    exit;
}
error_log("Access granted to admin_dashboard.php for user: " . $_SESSION['admin_name']);

// Rest of the code remains the same...
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Include the appropriate file with parameters
if ($active_tab === 'categorias' && $delete_category) {
    $_GET['delete_category'] = $delete_category;
}
if ($active_tab === 'categorias' && $edit_category) {
    $_GET['edit_category'] = $edit_category;
}
if ($active_tab === 'proveedores' && $delete_supplier) {
    $_GET['delete_supplier'] = $delete_supplier;
}
if ($active_tab === 'proveedores' && $edit_supplier) {
    $_GET['edit_supplier'] = $edit_supplier;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛠 Admin Dashboard - ToolSoft</title>
    <link rel="stylesheet" href="CSS/stylesadmin.css">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <div class="sidebar">
        <h3>Admin</h3>
        <a href="admin_dashboard.php" class="<?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <a href="admin_dashboard.php?tab=usuarios" class="<?php echo $active_tab === 'usuarios' ? 'active' : ''; ?>">Usuarios</a>
        <a href="admin_dashboard.php?tab=categorias" class="<?php echo $active_tab === 'categorias' ? 'active' : ''; ?>">Categorías</a>
        <a href="admin_dashboard.php?tab=proveedores" class="<?php echo $active_tab === 'proveedores' ? 'active' : ''; ?>">Proveedores</a>
        <a href="admin_dashboard.php?tab=sales" class="<?php echo $active_tab === 'sales' ? 'active' : ''; ?>">Productos</a>
        <a href="admin_dashboard.php?tab=ventas" class="<?php echo $active_tab === 'ventas' ? 'active' : ''; ?>">Historial Ventas</a>
        <a href="#">Pagos</a>
        <a href="#">Facturas</a>
        <a href="#">Cooperativas</a>
        <a href="#">Análisis de Precios</a>
        <a href="force_logout.php">Cerrar Sesión</a>
    </div>
    <div class="content">
        <?php if ($active_tab === 'dashboard'): ?>
            <h2>Bienvenido al Panel de Administración</h2>
            <p>Utilice el menú lateral para navegar entre las diferentes secciones.</p>
        <?php elseif ($active_tab === 'usuarios'): ?>
            <?php include 'admin/users.php'; ?>
        <?php elseif ($active_tab === 'categorias'): ?>
            <?php include 'admin/categories.php'; ?>
        <?php elseif ($active_tab === 'proveedores'): ?>
            <?php include 'admin/suppliers.php'; ?>
        <?php elseif ($active_tab === 'sales'): ?>
            <?php include 'admin/products.php'; ?>
        <?php elseif ($active_tab === 'ventas'): ?>
            <?php include 'admin/sales_history.php'; ?>
        <?php endif; ?>
        <!-- Display Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <div class="footer">
            ToolSoft © 2025 - Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
<?php $conexion->close(); ?>