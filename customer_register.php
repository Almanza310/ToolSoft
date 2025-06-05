<?php
require_once 'conexion.php';
session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'");

// Regenerate session ID
session_regenerate_id(true);

// CSRF Token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje = "❌ Error de seguridad: Token CSRF inválido.";
    } else {
        // Sanitize inputs
        $nombre = htmlspecialchars(trim($_POST['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? ''; // No hashing
        $phone = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars(trim($_POST['address'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($nombre && $email && $password) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mensaje = "❌ Correo electrónico inválido.";
            } else {
                // Check if email already exists
                $stmt_check = $conexion->prepare("SELECT id FROM Users WHERE email = ?");
                $stmt_check->bind_param("s", $email);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    $mensaje = "❌ El correo electrónico ya está registrado.";
                    $stmt_check->close();
                } else {
                    $stmt_check->close();
                    $role = 'customer';

                    $stmt = $conexion->prepare("INSERT INTO Users (name, email, password, phone, address, role) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $nombre, $email, $password, $phone, $address, $role);

                    if ($stmt->execute()) {
                        $stmt->close();
                        $conexion->close();

                        // Set success message
                        $mensaje = "✅ Registro exitoso. Redirigiendo al login...";

                        // Use relative path for redirect
                        if (headers_sent()) {
                            // If headers are already sent, use meta refresh as fallback
                            echo '<meta http-equiv="refresh" content="2;url=logeo_del_prototipo.php">';
                        } else {
                            // Use PHP header redirect with relative path
                            header("Location: logeo_del_prototipo.php", true, 302);
                            // Add meta refresh as a fallback
                            echo '<meta http-equiv="refresh" content="2;url=logeo_del_prototipo.php">';
                            // Ensure script stops after redirect
                            exit;
                        }
                    } else {
                        $mensaje = "❌ Error al registrar: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        } else {
            $mensaje = "❗ Los campos nombre, correo y contraseña son obligatorios.";
        }
    }
}
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>🛠 Registro de Cliente - ToolSoft</title>
  <link rel="stylesheet" href="CSS/stylesregister.css">
</head>
<body>
  <header>
    <div class="logo">🛠 ToolSoft</div>
    <nav>
      <a href="interfaz_prototipo.php">Inicio</a>
      <a href="products.php">Productos</a>
      <a href="contacto.php">Contacto</a>
    </nav>
  </header>

  <div class="container">
    <div class="logo">🛠 Registrate</div>
    <div class="description">
      Crea tu cuenta de cliente para comenzar a usar nuestros servicios.
    </div>

    <?php if ($mensaje): ?>
      <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
      <input type="text" name="nombre" placeholder="Nombre completo" required />
      <input type="email" name="email" placeholder="Correo electrónico" required />
      <input type="password" name="password" placeholder="Contraseña" required />
      <input type="tel" name="phone" placeholder="Teléfono (opcional)" />
      <input type="text" name="address" placeholder="Dirección (opcional)" />
      <button type="submit">Registrarse</button>
    </form>

    <div class="login">¿Ya tienes cuenta? <a href="logeo_del_prototipo.php">Inicia sesión aquí</a>.</div>
    <div class="footer">
      ToolSoft © 2025 - Todos los derechos reservados.
    </div>
  </div>
</body>
</html>