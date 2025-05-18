<?php
require_once 'conexion.php';

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conexion->prepare("SELECT password, name FROM Customer WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        if ($password === $fila['password']) { // Direct comparison for plaintext
            $mensaje = "✅ Bienvenido, " . htmlspecialchars($fila['name']);
        } else {
            $mensaje = "❌ Contraseña incorrecta.";
        }
    } else {
        $mensaje = "❌ Usuario no encontrado.";
    }

    $stmt->close();
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>🛠 ToolSoft</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 0;
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

    .container {
      text-align: center;
      padding: 2.5rem;
      background-color: white;
      border-radius: 20px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
      margin: 60px auto;
      min-height: 80vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .logo {
      font-size: 2.2rem;
      font-weight: bold;
      color: #2ecc71;
      margin-bottom: 1rem;
    }

    .description {
      font-size: 1rem;
      color: #333;
      margin-bottom: 2rem;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 12px;
      margin-bottom: 1rem;
      border: 1px solid #ccc;
      border-radius: 10px;
      font-size: 1rem;
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

    .register {
      margin-top: 1rem;
      font-size: 0.9rem;
      color: #2ecc71;
      cursor: pointer;
    }

    .footer {
      font-size: 0.8rem;
      color: #666;
      margin-top: 1.5rem;
    }

    @media (max-width: 768px) {
      .container {
        margin: 40px 20px;
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

  <div class="container">
    <div class="logo">🛠 Login</div>
    <div class="description">
      Bienvenido a ToolSoft, tu ferretería de confianza.<br/>
      Inicia sesión para acceder a tu cuenta.
    </div>

    <?php if ($mensaje): ?>
      <div class="message"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <form action="" method="post">
      <input type="email" name="email" placeholder="Correo electrónico" required />
      <input type="password" name="password" placeholder="Contraseña" required />
      <button type="submit">Iniciar sesión</button>
    </form>

    <div class="register">¿No tienes cuenta? <a href="customer_register.php">Regístrate aquí</a>.</div>
    <div class="footer">
      ToolSoft © 2025 - Todos los derechos reservados.
    </div>
  </div>
</body>
</html>