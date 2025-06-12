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

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: logeo_del_prototipo.php');
    exit;
}
$_SESSION['last_activity'] = time();

// CSRF Token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje = "❌ Error de seguridad: Token CSRF inválido.";
        error_log("CSRF token validation failed");
    } else {
        // Sanitize email input
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "❌ Correo electrónico inválido.";
            error_log("Invalid email: $email");
        } else {
            $password = trim($_POST['password'] ?? '');
            error_log("Login attempt: Email = $email");

            // Check Users table for both administrators and customers
            $stmt = $conexion->prepare("SELECT id, name, password, role, email, address, phone FROM Users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                error_log("User found in Users table: Name = " . $user['name'] . ", Role = " . $user['role']);

                // Simple plain text password comparison
                $password_match = ($password === $user['password']);

                if ($password_match) {
                    error_log("Login successful: Role = " . $user['role'] . ", Name = " . $user['name']);
                    
                    if (strtolower($user['role']) === 'administrator') {
                        // Administrator login
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_name'] = $user['name'];
                        $_SESSION['admin_id'] = $user['id'];
                        $redirect = 'admin_dashboard.php';
                        error_log("Setting admin session: admin_logged_in = true, admin_name = " . $user['name'] . ", admin_id = " . $user['id']);
                    } else {
                        // Customer login (any role that's not administrator)
                        $_SESSION['customer_logged_in'] = true;
                        $_SESSION['customer_name'] = $user['name'];
                        $_SESSION['customer_id'] = $user['id'];
                        $_SESSION['customer_email'] = $user['email'];
                        
                        if (isset($_SESSION['redirect_after_login'])) {
                            $redirect = $_SESSION['redirect_after_login'];
                            unset($_SESSION['redirect_after_login']);
                        } else {
                            $redirect = 'customer_dashboard.php';
                        }
                        error_log("Setting customer session: customer_logged_in = true, customer_name = " . $user['name'] . ", customer_id = " . $user['id']);
                    }
                    
                    error_log("Session state before redirect: " . print_r($_SESSION, true));
                    session_regenerate_id(true);
                    $stmt->close();
                    
                    if (headers_sent()) {
                        error_log("Headers already sent, using JavaScript redirect to $redirect");
                        echo "<script>window.location.href='$redirect';</script>";
                        exit;
                    }
                    header("Location: $redirect");
                    exit;
                } else {
                    $mensaje = "❌ Contraseña incorrecta.";
                    error_log("Password mismatch for user: " . $user['name']);
                }
                $stmt->close();
            } else {
                $mensaje = "❌ Usuario no encontrado.";
                error_log("No user found in Users table for email: $email");
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>🛠 ToolSoft - Iniciar Sesión</title>
  <link rel="stylesheet" href="CSS/styleslogeo.css">
</head>
<body>
  <header>
    <div class="header-logo">🛠 ToolSoft</div>
    <nav>
      <a href="interfaz_prototipo.php">Inicio</a>
      <a href="customer_products.php">Productos</a>
      <a href="contacto.php">Contacto</a>
    </nav>
  </header>

  <div class="container">
    <div class="login-box">
      <div class="logo">🛠 Login</div>
      <div class="description">
        Bienvenido a ToolSoft, tu ferretería de confianza.<br/>
        Inicia sesión para acceder a tu cuenta.
      </div>

      <?php if ($mensaje): ?>
        <div class="message"><?php echo htmlspecialchars($mensaje); ?></div>
      <?php endif; ?>

      <form action="" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
        <input type="email" name="email" placeholder="📧 Correo electrónico" required />
        <input type="password" name="password" placeholder="🔒 Contraseña" required />
        <button type="submit">🚀 Iniciar sesión</button>
      </form>

      <div class="register">
        ¿No tienes cuenta? <a href="customer_register.php">Regístrate aquí</a>.
      </div>
      <div class="footer">
        ToolSoft © 2025 - Todos los derechos reservados.
      </div>
    </div>
  </div>

  <script>
    // Restore redirect URL from sessionStorage if it exists
    window.addEventListener('load', function() {
        const redirectUrl = sessionStorage.getItem('redirect_after_login');
        if (redirectUrl) {
            <?php
            // Set the redirect URL in the session if it came from sessionStorage
            if (!isset($_SESSION['redirect_after_login'])) {
                echo "sessionStorage.removeItem('redirect_after_login');";
                echo "window.location.href = redirectUrl;";
            }
            ?>
        }
    });

    // Add loading state to button
    document.querySelector('form').addEventListener('submit', function() {
        const button = document.querySelector('button[type="submit"]');
        button.textContent = '⏳ Iniciando sesión...';
        button.disabled = true;
    });

    // Clear form messages after 5 seconds
    setTimeout(function() {
        const message = document.querySelector('.message');
        if (message) {
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 300);
        }
    }, 5000);
  </script>
</body>
</html>
<?php $conexion->close(); ?>