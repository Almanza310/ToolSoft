<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['customer_logged_in'])) {
    header('Location: logeo_del_prototipo.php?redirect=customer_messages.php');
    exit;
}
require_once 'conexion.php';

$customer_email = $_SESSION['customer_email'];

$query = "SELECT subject, message, created_at, admin_reply FROM contact WHERE email = ? ORDER BY created_at DESC LIMIT 100";
$stmt = $conexion->prepare($query);
$stmt->bind_param('s', $customer_email);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Mensajes - ToolSoft</title>
    <link rel="stylesheet" href="CSS/stylesmessages.css">
</head>
<body>
    <div class="main-messages">
        <h2>Mis Mensajes y Respuestas</h2>
        <table class="messages-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Asunto</th>
                    <th style="width: 32%;">Mensaje</th>
                    <th style="width: 18%; text-align:center;">Fecha</th>
                    <th style="width: 32%; text-align:center;">Respuesta del Administrador</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td style="white-space:pre-line; word-break:break-word;">
                            <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                        </td>
                        <td style="text-align:center;">
                            <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($row['admin_reply']): ?>
                                <span class="admin-reply"><?php echo nl2br(htmlspecialchars($row['admin_reply'])); ?></span>
                            <?php else: ?>
                                <span class="no-reply">Pendiente de respuesta</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No tienes mensajes enviados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 