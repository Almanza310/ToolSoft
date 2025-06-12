<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'");

// Regenerate session ID
session_regenerate_id(true);

// Check if the user is logged in
if (!isset($_SESSION['customer_logged_in'])) {
    $_SESSION['error_message'] = "Debes iniciar sesión para ver esta página.";
    header('Location: logeo_del_prototipo.php');
    exit;
}

// Check if success message exists
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '¡Gracias por tu compra!';
$warning_message = isset($_SESSION['warning_message']) ? $_SESSION['warning_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$invoice_html = isset($_SESSION['invoice_html']) ? $_SESSION['invoice_html'] : '';
$invoice_number = isset($_SESSION['invoice_number']) ? $_SESSION['invoice_number'] : '';

// Get customer info
$customer_name = $_SESSION['customer_name'] ?? 'Cliente';
$customer_email = $_SESSION['customer_email'] ?? '';

// Clear the messages after displaying them
$_SESSION['success_message'] = '';
$_SESSION['warning_message'] = '';
$_SESSION['error_message'] = '';

// Don't clear invoice_html and invoice_number yet, in case user wants to download
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✅ Pago Exitoso - ToolSoft</title>
    <link rel="stylesheet" href="CSS/stylesorder.css">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <header>
        <div class="logo">🛠 ToolSoft</div>
        <nav>
            <a href="interfaz_prototipo.php">Inicio</a>
            <a href="contacto.php">Contacto</a>
            <a href="customer_products.php">Productos</a>
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

    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">🎉</div>
            <h1 class="success-title">¡Pago Completado!</h1>
            <p class="success-subtitle">Tu pedido ha sido procesado exitosamente</p>
        </div>
        
        <!-- Display Messages -->
        <?php if ($success_message): ?>
            <div class="message success-message">
                <strong>✅ Éxito:</strong> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($warning_message): ?>
            <div class="message warning-message">
                <strong>⚠️ Advertencia:</strong> <?php echo htmlspecialchars($warning_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error-message">
                <strong>❌ Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Customer Information -->
        <?php if ($customer_name || $customer_email): ?>
            <div class="customer-info">
                <h4>👤 Información del Cliente</h4>
                <?php if ($customer_name): ?>
                    <div class="customer-detail">
                        <strong>Nombre:</strong> <?php echo htmlspecialchars($customer_name); ?>
                    </div>
                <?php endif; ?>
                <?php if ($customer_email): ?>
                    <div class="customer-detail">
                        <strong>Email:</strong> <?php echo htmlspecialchars($customer_email); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Order Summary -->
        <?php if ($invoice_number): ?>
            <div class="order-summary">
                <h3>📋 Resumen del Pedido</h3>
                <div class="order-details">
                    <div class="order-detail">
                        <span class="order-label">Número de Factura</span>
                        <span class="order-value"><?php echo htmlspecialchars($invoice_number); ?></span>
                    </div>
                    <div class="order-detail">
                        <span class="order-label">Fecha y Hora</span>
                        <span class="order-value"><?php echo date('d/m/Y H:i:s'); ?></span>
                    </div>
                    <div class="order-detail">
                        <span class="order-label">Estado</span>
                        <span class="order-value" style="color: var(--success-color); font-weight: bold;">✅ Completado</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="button-container">
            <?php if ($invoice_html && $invoice_number): ?>
                <a href="download_invoice.php?invoice=<?php echo urlencode($invoice_number); ?>" class="btn download-btn">
                    📄 Descargar Factura
                </a>
            <?php endif; ?>
            <a href="customer_dashboard.php?tab=orders" class="btn dashboard-btn">
                📊 Ver Mis Pedidos
            </a>
            <a href="customer_products.php" class="btn continue-btn">
                🛍️ Seguir Comprando
            </a>
        </div>
        
        <!-- Next Steps -->
        <div class="next-steps">
            <h4>📌 Próximos Pasos</h4>
            <ul>
                <li>Tu factura ha sido generada y está disponible para descargar</li>
                <li>Puedes revisar el estado de tu pedido en "Mis Pedidos"</li>
                <li>Si tienes alguna pregunta, no dudes en contactarnos</li>
                <li>¡Gracias por confiar en ToolSoft para tus proyectos!</li>
            </ul>
        </div>
        
        <!-- Invoice Preview -->
        <?php if ($invoice_html): ?>
            <div class="invoice-preview">
                <h3>📄 Vista Previa de la Factura</h3>
                <iframe srcdoc="<?php echo htmlspecialchars($invoice_html); ?>"></iframe>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-scroll to top when page loads
        window.addEventListener('load', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Add interactivity to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
        
        // Enhanced confetti effect
        function createConfetti() {
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7', '#fd79a8', '#6c5ce7'];
            const confettiCount = 100;
            
            for (let i = 0; i < confettiCount; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.animationDelay = Math.random() * 3 + 's';
                    confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                    
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => {
                        if (confetti.parentNode) {
                            confetti.parentNode.removeChild(confetti);
                        }
                    }, 5000);
                }, i * 50);
            }
        }
        
        // Trigger confetti on page load
        window.addEventListener('load', function() {
            setTimeout(createConfetti, 800);
        });
        
        // Add hover effects to order details
        document.querySelectorAll('.order-detail').forEach(detail => {
            detail.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            detail.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
        
        // Smooth reveal animation for elements
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Observe elements for animation
        document.querySelectorAll('.order-summary, .customer-info, .next-steps, .invoice-preview').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>