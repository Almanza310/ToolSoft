<?php
require_once __DIR__ . '/../conexion.php';
// session_start solo si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguridad: solo admins
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../logeo_del_prototipo.php');
    exit;
}

// Procesar eliminación de pago
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $sale_id = (int)$_GET['delete'];
    
    // Verificar que la venta existe
    $check_query = "SELECT id FROM sale WHERE id = ?";
    $check_stmt = $conexion->prepare($check_query);
    $check_stmt->bind_param('i', $sale_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Eliminar detalles de la venta primero
        $delete_details_query = "DELETE FROM saledetail WHERE sale_id = ?";
        $delete_details_stmt = $conexion->prepare($delete_details_query);
        $delete_details_stmt->bind_param('i', $sale_id);
        $delete_details_stmt->execute();
        
        // Eliminar la venta
        $delete_sale_query = "DELETE FROM sale WHERE id = ?";
        $delete_sale_stmt = $conexion->prepare($delete_sale_query);
        $delete_sale_stmt->bind_param('i', $sale_id);
        
        if ($delete_sale_stmt->execute()) {
            $_SESSION['success_message'] = "Pago eliminado exitosamente.";
        } else {
            $_SESSION['error_message'] = "Error al eliminar el pago.";
        }
    } else {
        $_SESSION['error_message'] = "Pago no encontrado.";
    }
    
    // Redirigir para evitar reenvío del formulario
    header('Location: ../admin_dashboard.php?tab=pagos');
    exit;
}

// Procesar exportación a Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Configurar headers para descarga de Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="pagos_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Obtener datos sin límite para exportación
    $export_query = "
    SELECT s.id AS sale_id, s.date, s.total, s.user_id, s.admin_id,
           u.name AS customer_name, u.email AS customer_email
    FROM sale s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.date DESC
    ";
    
    $export_stmt = $conexion->prepare($export_query);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    
    // Crear contenido del Excel
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th># Factura</th>";
    echo "<th>Cliente</th>";
    echo "<th>Email</th>";
    echo "<th>Fecha</th>";
    echo "<th>Total</th>";
    echo "</tr>";
    
    while ($row = $export_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>INV-" . date('Ymd', strtotime($row['date'])) . "-" . str_pad($row['sale_id'], 4, '0', STR_PAD_LEFT) . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_email']) . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($row['date'])) . "</td>";
        echo "<td>$" . number_format($row['total'], 2) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
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
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}
if ($date_from !== '') {
    $where[] = 's.date >= ?';
    $params[] = $date_from . ' 00:00:00';
    $types .= 's';
}
if ($date_to !== '') {
    $where[] = 's.date <= ?';
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$query = "
SELECT s.id AS sale_id, s.date, s.total, s.user_id, s.admin_id, s.total,
       u.name AS customer_name, u.email AS customer_email
FROM sale s
JOIN users u ON s.user_id = u.id
$where_sql
ORDER BY s.date DESC
LIMIT 100
";

$stmt = $conexion->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos de Clientes - Admin | ToolSoft</title>
    <link rel="stylesheet" href="../CSS/stylesadmin.css">
    <link rel="stylesheet" href="../CSS/stylespayments.css">
</head>
<body>
    <div class="main-content">
        <div class="main-title">Pagos de Clientes</div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <div style="display:flex; justify-content: flex-end; margin-bottom: 18px;">
            <a href="../admin_dashboard.php?tab=pagos&export=excel" class="export-btn">Exportar a Excel</a>
        </div>
        <form class="filters-form" method="get" action="../admin_dashboard.php">
            <input type="hidden" name="tab" value="pagos">
            <label>Buscar cliente/email
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre o email">
            </label>
            <label>Desde
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </label>
            <label>Hasta
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </label>
            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit">Filtrar</button>
                <a href="../admin_dashboard.php?tab=pagos" class="clear-link">Limpiar</a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="payments-table">
                <thead>
                    <tr>
                        <th># Factura</th>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Ver Detalle</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>INV-<?php echo date('Ymd', strtotime($row['date'])); ?>-<?php echo str_pad($row['sale_id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_email']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['date'])); ?></td>
                            <td>$<?php echo number_format($row['total'], 2); ?></td>
                            <td>
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                    <a href="../download_invoice.php?invoice=INV-<?php echo date('Ymd', strtotime($row['date'])); ?>-<?php echo str_pad($row['sale_id'], 4, '0', STR_PAD_LEFT); ?>" class="view-btn" target="_blank">
                                        <span style="font-size:1.2em;">📄</span> Ver Factura
                                    </a>
                                    <a href="../admin_dashboard.php?tab=pagos&delete=<?php echo $row['sale_id']; ?>" class="delete-btn" onclick="return confirm('¿Seguro que deseas eliminar este pago? Esta acción no se puede deshacer.');">
                                        <span style="font-size:1.2em;">🗑️</span> Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6">No hay pagos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php $conexion->close(); ?>