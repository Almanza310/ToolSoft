<?php
require_once __DIR__ . '/../conexion.php';
session_start();

// Seguridad: solo admins
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../logeo_del_prototipo.php');
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
    <style>
        body { background: linear-gradient(to right, #dffcf3, #f1fff8); }
        .main-content {
            background: white;
            padding: 30px;
            margin: 30px auto;
            border-radius: 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            min-height: calc(100vh - 140px);
            width: 95vw;
            max-width: 1200px;
            box-sizing: border-box;
            overflow-x: auto;
        }
        .main-title {
            color: #2ecc71;
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 18px;
        }
        .filters-form {
            display: flex;
            gap: 18px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .filters-form label {
            font-weight: 500;
            color: #2ecc71;
            display: flex;
            flex-direction: column;
            font-size: 1rem;
        }
        .filters-form input[type="text"], .filters-form input[type="date"] {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1rem;
            margin-top: 4px;
        }
        .filters-form button {
            background: #2ecc71;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .filters-form button:hover { background: #27ae60; }
        .filters-form .clear-link {
            background: #fff;
            color: #2ecc71;
            border: 2px solid #2ecc71;
            border-radius: 8px;
            padding: 10px 22px;
            font-weight: 500;
            font-size: 1rem;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
            margin-left: 10px;
            cursor: pointer;
            display: inline-block;
        }
        .filters-form .clear-link:hover {
            background: #2ecc71;
            color: #fff;
            text-decoration: none;
        }
        .payments-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 18px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(46,204,113,0.08);
            overflow: hidden;
        }
        .payments-table th, .payments-table td {
            padding: 14px 10px;
            border-bottom: 1px solid #e0e0e0;
            text-align: center;
        }
        .payments-table th {
            background: #2ecc71;
            color: #fff;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .payments-table tr:last-child td { border-bottom: none; }
        .view-btn {
            background: #2ecc71;
            color: #fff !important;
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            text-decoration: none !important;
            font-weight: 500;
            font-size: 1rem;
            transition: background 0.2s;
            margin: 6px 0;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
        .view-btn:hover {
            background: #27ae60;
            color: #fff !important;
            text-decoration: none !important;
        }
        .delete-btn {
            background: #e74c3c;
            color: #fff;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: background 0.2s;
            border: none;
            display: inline-block;
        }
        .delete-btn:hover {
            background: #c0392b;
            color: #fff;
        }
        @media (max-width: 900px) {
            .main-content { padding: 16px 4px 10px 4px; }
            .filters-form { flex-direction: column; gap: 10px; }
            .payments-table th, .payments-table td { font-size: 0.97rem; padding: 8px 4px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3>Admin</h3>
        <a href="../admin_dashboard.php">Dashboard</a>
        <a href="../admin_dashboard.php?tab=usuarios">Usuarios</a>
        <a href="../admin_dashboard.php?tab=categorias">Categorías</a>
        <a href="../admin_dashboard.php?tab=proveedores">Proveedores</a>
        <a href="../admin_dashboard.php?tab=sales">Productos</a>
        <a href="../admin_dashboard.php?tab=ventas">Historial Ventas</a>
        <a href="payments.php" class="active">Pagos</a>
        <a href="#">Facturas</a>
        <a href="#">Cooperativas</a>
        <a href="#">Análisis de Precios</a>
        <a href="../force_logout.php">Cerrar Sesión</a>
    </div>
    <div class="main-content">
        <div class="main-title">Pagos de Clientes</div>
        <div style="display:flex; justify-content: flex-end; margin-bottom: 18px;">
            <a href="payments.php?export=excel" class="export-btn">Exportar a Excel</a>
        </div>
        <form class="filters-form" method="get">
            <label>Buscar cliente/email
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre o email">
            </label>
            <label>Desde
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </label>
            <label>Hasta
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </label>
            <button type="submit">Filtrar</button>
            <a href="payments.php" class="clear-link">Limpiar</a>
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
                                    <a href="payments.php?delete=<?php echo $row['sale_id']; ?>" class="delete-btn" onclick="return confirm('¿Seguro que deseas eliminar este pago? Esta acción no se puede deshacer.');">
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
        <div style="margin-top:40px; text-align:center; color:#888; font-size:0.98rem;">
            ToolSoft © 2025 - Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
<?php $conexion->close(); ?>