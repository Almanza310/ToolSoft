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
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to right, #dffcf3, #f1fff8);
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 40px;
      background-color: #ffffff;
      border-bottom: 1px solid #ddd;
    }

    .logo {
      font-weight: bold;
      font-size: 1.5rem;
      color: #2ecc71;
    }

    nav a {
      margin-left: 20px;
      text-decoration: none;
      color: #333;
      font-weight: 500;
    }

    nav a:hover {
      color: #2ecc71;
    }

    .main {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 60px 80px;
      background-color: #f5f5f5;
      min-height: 80vh;
    }

    .contact-form {
      background-color: white;
      padding: 2.5rem;
      border-radius: 20px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 500px;
      text-align: center;
    }

    .contact-form h2 {
      font-size: 2rem;
      color: #2ecc71;
      margin-bottom: 1rem;
    }

    .contact-form p {
      font-size: 1rem;
      color: #333;
      margin-bottom: 2rem;
    }

    input[type="text"],
    input[type="email"],
    textarea {
      width: 100%;
      padding: 12px;
      margin-bottom: 1rem;
      border: 1px solid #ccc;
      border-radius: 10px;
      font-size: 1rem;
      box-sizing: border-box;
    }

    textarea {
      height: 150px;
      resize: vertical;
    }

    button {
      width: 100%;
      padding: 12px;
      background-color: #2ecc71;
      color: white;
      font-size: 1rem;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    button:hover {
      background-color: #27ae60;
    }

    .message {
      font-size: 0.95rem;
      margin-bottom: 1rem;
      color: #d63031;
    }

    .footer {
      font-size: 0.8rem;
      color: #666;
      margin-top: 1.5rem;
      text-align: center;
    }

    @media (max-width: 768px) {
      .main {
        padding: 40px 20px;
      }

      .contact-form {
        padding: 2rem;
      }
    }
  </style>
</head>
<body>

  <header>
    <div class="logo">🛠 ToolSoft</div>
    <nav>
      <a href="interfaz_prototipo.php">Inicio</a>
      <a href="#">Productos</a>
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