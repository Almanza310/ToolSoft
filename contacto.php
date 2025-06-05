<?php
require_once 'conexion.php';

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';

    if ($name && $email && $subject && $message) {
        // Basic email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "❌ Correo electrónico inválido.";
        } else {
            $stmt = $conexion->prepare("INSERT INTO Contact (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);

            if ($stmt->execute()) {
                $mensaje = "✅ Mensaje enviado con éxito. ¡Gracias por contactarnos!";
            } else {
                $mensaje = "❌ Error al enviar el mensaje: " . $stmt->error;
            }

            $stmt->close();
        }
    } else {
        $mensaje = "❗ Todos los campos son obligatorios.";
    }
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🛠 Contacto - ToolSoft</title>
  <link rel="stylesheet" href="CSS/stylescontacto.css">
</head>
<body>

  <header>
    <div class="logo">🛠 ToolSoft</div>
    <nav>
      <a href="interfaz_prototipo.php">Inicio</a>
      <a href="customer_products.php">Productos</a>
      <a href="contacto.php">Contacto</a>
    </nav>
  </header>

  <div class="main">
    <div class="contact-form">
      <h2>Contáctanos</h2>
      <p>¿Tienes alguna pregunta o necesitas ayuda? Envíanos un mensaje.</p>

      <?php if ($mensaje): ?>
        <div class="message"><?php echo htmlspecialchars($mensaje); ?></div>
      <?php endif; ?>

      <form method="post">
        <input type="text" name="name" placeholder="Nombre completo" required />
        <input type="email" name="email" placeholder="Correo electrónico" required />
        <input type="text" name="subject" placeholder="Asunto" required />
        <textarea name="message" placeholder="Tu mensaje" required></textarea>
        <button type="submit">Enviar Mensaje</button>
      </form>

      <div class="footer">
        ToolSoft © 2025 - Todos los derechos reservados.
      </div>
    </div>
  </div>

</body>
</html>