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
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🛠 ToolSoft</title>
  <link rel="stylesheet" href="CSS/stylesinterfaz.css">
</head>
<body>

  <header>
    <div class="logo">🛠 ToolSoft</div>
    <nav>
      <a href="customer_products.php">Productos</a>
      <a href="contacto.php">Contacto</a>
      <?php if (isset($_SESSION['customer_logged_in'])): ?>
        <a href="force_logout.php" class="btn">Cerrar Sesión</a>
      <?php else: ?>
        <a href="logeo_del_prototipo.php" class="btn">Iniciar Sesión</a>
      <?php endif; ?>
    </nav>
  </header>

  <div class="main">
    <div class="text-section">
      <h1>¡Bienvenidos!</h1>
      <h2><?php echo "Soy ToolSoft"; ?></h2>
      <p><?php echo "Un lugar ideal para encontrar todo lo que necesitas. Desde las herramientas más especializadas hasta los accesorios más pequeños, tenemos una extensa gama de productos para fontanería, electricidad, pintura, y mucho más."; ?></p>
      <p><?php echo "Ya seas un profesional experimentado o un amante del bricolaje, aquí encontrarás lo que buscas a precios increíbles y con el respaldo de un equipo experto siempre dispuesto a ayudarte. No importa el tamaño de tu proyecto, en ToolSoft tenemos todo para hacer realidad tus ideas."; ?></p>
      <h3>¡Visítanos y transforma tus proyectos en éxitos!</h3>
      <?php if (!isset($_SESSION['customer_logged_in'])): ?>
        <a href="customer_register.php" class="btn">Registrate</a>
      <?php endif; ?>
    </div>
    <div class="image-section">
      <img src="uploads/ferre.png" alt="ToolSoft Ferretería">
    </div>
  </div>

</body>
</html>