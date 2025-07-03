<?php
require_once __DIR__ . '/../conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Procesar eliminación antes de cualquier salida
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $del = $conexion->prepare("DELETE FROM contact WHERE id = ?");
    $del->bind_param('i', $delete_id);
    $del->execute();
    $_SESSION['success_message'] = 'Mensaje eliminado correctamente.';
    header('Location: ../admin_dashboard.php?tab=contactanos');
    exit;
}

// Seguridad: solo admins
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../logeo_del_prototipo.php');
    exit;
}

// Procesar respuesta del admin
if (isset($_POST['reply_id']) && isset($_POST['admin_reply'])) {
    $reply_id = intval($_POST['reply_id']);
    $admin_reply = trim($_POST['admin_reply']);
    if ($admin_reply !== '') {
        $update = $conexion->prepare("UPDATE contact SET admin_reply = ? WHERE id = ?");
        $update->bind_param('si', $admin_reply, $reply_id);
        $update->execute();
        $_SESSION['success_message'] = 'Respuesta enviada correctamente.';
    }
    header('Location: ../admin_dashboard.php?tab=contactanos');
    exit;
}

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ssss';
}
if ($date_from !== '') {
    $where[] = 'created_at >= ?';
    $params[] = $date_from . ' 00:00:00';
    $types .= 's';
}
if ($date_to !== '') {
    $where[] = 'created_at <= ?';
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$query = "
SELECT id, name, email, subject, message, created_at, admin_reply
FROM contact
$where_sql
ORDER BY created_at DESC
LIMIT 100
";

$stmt = $conexion->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<link rel="stylesheet" href="../CSS/stylescontactadmin.css">
<div class="main-content">
    <div class="main-title">Quejas y Contacto de Clientes</div>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-message"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <form class="filters-form" method="get" action="../admin_dashboard.php">
        <input type="hidden" name="tab" value="contactanos">
        <label>Buscar nombre/email/asunto
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar...">
        </label>
        <label>Desde
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
        </label>
        <label>Hasta
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
        </label>
        <div style="display: flex; gap: 10px; align-items: flex-end;">
            <button type="submit">Filtrar</button>
            <a href="../admin_dashboard.php?tab=contactanos" class="clear-link">Limpiar</a>
        </div>
    </form>
    <div class="table-responsive">
        <table class="contact-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Asunto</th>
                    <th>Mensaje</th>
                    <th>Fecha</th>
                    <th>Respuesta</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td style="max-width:300px; white-space:pre-line; word-break:break-word;"><?php echo nl2br(htmlspecialchars($row['message'])); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <?php if ($row['admin_reply']): ?>
                                <div style="color:#27ae60; font-weight:500; white-space:pre-line;">Respuesta: <?php echo nl2br(htmlspecialchars($row['admin_reply'])); ?></div>
                            <?php else: ?>
                                <form method="post" class="reply-form">
                                    <textarea name="admin_reply" rows="2" placeholder="Escribe tu respuesta..."></textarea>
                                    <input type="hidden" name="reply_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit">Responder</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['admin_reply']): ?>
                                <span class="estado-respondido">Respondido</span>
                            <?php else: ?>
                                <span class="estado-pendiente">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" onsubmit="return confirm('¿Seguro que deseas eliminar este mensaje?');" style="display:inline;">
                                <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn-eliminar">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No hay mensajes registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $conexion->close(); ?> 