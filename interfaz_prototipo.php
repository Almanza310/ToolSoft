<?php
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
  <title>🛠 ToolSoft</title>
  <link rel="stylesheet" href="CSS/stylesinterfaz.css">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
</head>
<body>

  <header>
    <div class="logo">🛠 ToolSoft</div>
    <nav>
      <a href="interfaz_prototipo.php" class="active">Inicio</a>
      <a href="customer_products.php">Productos</a>
      <a href="contacto.php">Contacto</a>
      <a href="cart.php" class="cart-link">
        <span class="cart-icon">🛒</span> Carrito
      </a>
      <?php if (isset($_SESSION['customer_logged_in'])): ?>
        <!-- Nuevo enlace al dashboard del cliente -->
        <a href="customer_dashboard.php">Mi Perfil</a>
        <a href="force_logout.php" class="btn">Cerrar Sesión</a>
      <?php else: ?>
        <a href="logeo_del_prototipo.php" class="btn">Iniciar Sesión</a>
      <?php endif; ?>
    </nav>
  </header>

  <!-- Display Success/Error Messages -->
  <?php if ($success_message): ?>
    <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
  <?php endif; ?>
  <?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>

  <div class="main">
    <div class="text-section">
      <h1>¡Bienvenidos!</h1>
      <h2><?php echo "Soy ToolSoft"; ?></h2>
      <p><?php echo "Un lugar ideal para encontrar todo lo que necesitas. Desde las herramientas más especializadas hasta los accesorios más pequeños, tenemos una extensa gama de productos para fontanería, electricidad, pintura, y mucho más."; ?></p>
      <p><?php echo "Ya seas un profesional experimentado o un amante del bricolaje, aquí encontrarás lo que buscas a precios increíbles y con el respaldo de un equipo experto siempre dispuesto a ayudarte. No importa el tamaño de tu proyecto, en ToolSoft tenemos todo para hacer realidad tus ideas."; ?></p>
      <h3>¡Visítanos y transforma tus proyectos en éxitos!</h3>
      
      <!-- Botones de acción según el estado del usuario -->
      <div class="action-buttons">
        <?php if (!isset($_SESSION['customer_logged_in'])): ?>
          <a href="customer_register.php" class="btn">Registrate</a>
          <a href="logeo_del_prototipo.php" class="btn btn-secondary">Iniciar Sesión</a>
        <?php else: ?>
          <a href="customer_products.php" class="btn">Ver Productos</a>
          <a href="customer_dashboard.php" class="btn btn-secondary">Mi Perfil</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="image-section">
      <img src="uploads/ferre.png" alt="ToolSoft Ferretería">
    </div>
  </div>

  <!-- Sección de acciones rápidas para usuarios logueados -->
  <?php if (isset($_SESSION['customer_logged_in'])): ?>
    <div class="quick-actions">
      <h3>Acceso Rápido</h3>
      <div class="action-buttons">
        <a href="customer_products.php" class="btn">🛍️ Explorar Productos</a>
        <a href="cart.php" class="btn btn-secondary">🛒 Ver Carrito</a>
        <a href="customer_dashboard.php?tab=orders" class="btn btn-secondary">📋 Mis Pedidos</a>
        <a href="customer_dashboard.php" class="btn btn-secondary">👤 Mi Perfil</a>
      </div>
    </div>
  <?php endif; ?>

</body>
</html>
