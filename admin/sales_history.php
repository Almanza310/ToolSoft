<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Use __DIR__ to get the directory of the current file and adjust the path to conexion.php
require_once __DIR__ . '/../conexion.php';

// Initialize session messages
$_SESSION['success_message'] = '';
$_SESSION['error_message'] = '';

// GENERAR TOKEN CSRF PARA PREVENIR ENVÍO MÚLTIPLE
if (!isset($_SESSION['purchase_form_token'])) {
    $_SESSION['purchase_form_token'] = bin2hex(random_bytes(32));
}

// Verificar si la tabla supplier_purchases existe
$check_table = $conexion->query("SHOW TABLES LIKE 'supplier_purchases'");
$table_exists = $check_table->num_rows > 0;

// Si la tabla no existe, crearla con los tipos de datos correctos
if (!$table_exists) {
    $create_table_query = "
    CREATE TABLE supplier_purchases (
        id INT NOT NULL AUTO_INCREMENT,
        supplier_id INT NOT NULL,
        product_id BIGINT NOT NULL,
        quantity INT NOT NULL,
        purchase_price DECIMAL(10,2) NOT NULL,
        total_cost DECIMAL(10,2) NOT NULL,
        purchase_date DATETIME NOT NULL,
        admin_id INT DEFAULT 1,
        PRIMARY KEY (id),
        INDEX idx_supplier_id (supplier_id),
        INDEX idx_product_id (product_id),
        CONSTRAINT fk_supplier_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
        CONSTRAINT fk_supplier_purchases_product FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE
    )";
    
    if (!$conexion->query($create_table_query)) {
        error_log("Error creating supplier_purchases table: " . $conexion->error);
    } else {
        error_log("supplier_purchases table created successfully");
    }
} else {
    // Si la tabla existe pero tiene un tipo de datos incorrecto, intentar modificarla
    $check_column = $conexion->query("SHOW COLUMNS FROM supplier_purchases LIKE 'product_id'");
    if ($check_column->num_rows > 0) {
        $column_info = $check_column->fetch_assoc();
        if (strpos(strtolower($column_info['Type']), 'int') !== false && strpos(strtolower($column_info['Type']), 'bigint') === false) {
            // Intentar eliminar la clave foránea si existe
            $conexion->query("ALTER TABLE supplier_purchases DROP FOREIGN KEY fk_supplier_purchases_product");
            
            // Modificar el tipo de columna
            $alter_query = "ALTER TABLE supplier_purchases MODIFY COLUMN product_id BIGINT NOT NULL";
            if (!$conexion->query($alter_query)) {
                error_log("Error modifying product_id column: " . $conexion->error);
            } else {
                error_log("product_id column modified successfully");
                
                // Intentar recrear la clave foránea
                $add_fk_query = "ALTER TABLE supplier_purchases ADD CONSTRAINT fk_supplier_purchases_product FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE";
                $conexion->query($add_fk_query);
            }
        }
    }
}

// Handle Purchase from Supplier
if (isset($_POST['action']) && $_POST['action'] === 'purchase_from_supplier') {
    // VERIFICAR TOKEN CSRF
    if (!isset($_POST['purchase_form_token']) || $_POST['purchase_form_token'] !== $_SESSION['purchase_form_token']) {
        $_SESSION['error_message'] = "Token de seguridad inválido. Intente nuevamente.";
        header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=ventas');
        exit;
    }
    
    $product_id = $_POST['product_id'];
    $supplier_id = $_POST['supplier_id'];
    $quantity = $_POST['quantity'];
    $purchase_price = $_POST['purchase_price'];
    
    // REGENERAR TOKEN
    $_SESSION['purchase_form_token'] = bin2hex(random_bytes(32));
    
    if (empty($product_id) || empty($supplier_id) || empty($quantity) || empty($purchase_price) || $quantity <= 0 || $purchase_price <= 0) {
        $_SESSION['error_message'] = "Todos los campos son obligatorios y deben ser valores válidos.";
    } else {
        // Iniciar transacción
        $conexion->begin_transaction();
        
        try {
            // Crear compra a proveedor
            $purchase_date = date('Y-m-d H:i:s'); // Set current date and time
            $total_cost = $purchase_price * $quantity;
            $admin_id = $_SESSION['admin_id'] ?? 1; // Fallback to 1 if not set
            
            $stmt = $conexion->prepare("INSERT INTO supplier_purchases (supplier_id, product_id, quantity, purchase_price, total_cost, purchase_date, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiddsi", $supplier_id, $product_id, $quantity, $purchase_price, $total_cost, $purchase_date, $admin_id);
            $stmt->execute();
            $purchase_id = $conexion->insert_id;
            $stmt->close();
            
            // Actualizar stock del producto
            $stmt = $conexion->prepare("UPDATE product SET stock = stock + ?, purchase_price = ? WHERE id = ?");
            $stmt->bind_param("idi", $quantity, $purchase_price, $product_id);
            $stmt->execute();
            $stmt->close();
            
            // Confirmar transacción
            $conexion->commit();
            
            $_SESSION['success_message'] = "Compra registrada exitosamente. ID: $purchase_id, Cantidad: $quantity, Total: $" . number_format($total_cost, 2);
            error_log("Compra a proveedor registrada: ID $purchase_id, Producto ID: $product_id, Cantidad: $quantity");
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conexion->rollback();
            $_SESSION['error_message'] = "Error al procesar la compra: " . $e->getMessage();
            error_log("Error en compra a proveedor: " . $e->getMessage());
        }
    }
    header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=ventas');
    exit;
}

// Fetch products for purchase form
$result_products = $conexion->query("
    SELECT p.id, p.name, p.purchase_price, p.stock, c.name as category_name 
    FROM product p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.name ASC
");
$products = $result_products ? $result_products->fetch_all(MYSQLI_ASSOC) : [];

// Fetch suppliers for purchase form
$result_suppliers = $conexion->query("
    SELECT s.id, s.name, s.contact, s.email, c.name as category_name 
    FROM suppliers s 
    LEFT JOIN categories c ON s.category_id = c.id 
    ORDER BY s.name ASC
");
$suppliers = $result_suppliers ? $result_suppliers->fetch_all(MYSQLI_ASSOC) : [];

// Fetch customer sales history with profit calculation
$customer_sales_query = "
    SELECT 
        s.id as sale_id,
        s.date as sale_date,
        s.total as sale_total,
        u.name as customer_name,
        u.email as customer_email,
        GROUP_CONCAT(CONCAT(p.name, ' (', sd.quantity, ')') SEPARATOR ', ') as products,
        SUM(sd.quantity * p.purchase_price) as total_cost,
        (s.total - SUM(sd.quantity * p.purchase_price)) as profit
    FROM sale s 
    LEFT JOIN users u ON s.user_id = u.id 
    LEFT JOIN saledetail sd ON s.id = sd.sale_id 
    LEFT JOIN product p ON sd.product_id = p.id
    GROUP BY s.id 
    ORDER BY s.date DESC 
    LIMIT 50
";
$result_customer_sales = $conexion->query($customer_sales_query);
$customer_sales = $result_customer_sales ? $result_customer_sales->fetch_all(MYSQLI_ASSOC) : [];

// Fetch supplier purchases history
$supplier_purchases_query = "
    SELECT 
        sp.id as purchase_id,
        sp.purchase_date,
        sp.quantity,
        sp.purchase_price,
        sp.total_cost,
        s.name as supplier_name,
        s.contact as supplier_contact,
        p.name as product_name,
        p.price as current_sale_price,
        (p.price - sp.purchase_price) as unit_profit_potential,
        (sp.quantity * (p.price - sp.purchase_price)) as total_profit_potential
    FROM supplier_purchases sp
    LEFT JOIN suppliers s ON sp.supplier_id = s.id
    LEFT JOIN product p ON sp.product_id = p.id
    ORDER BY sp.purchase_date DESC
    LIMIT 50
";
$result_supplier_purchases = $conexion->query($supplier_purchases_query);
$supplier_purchases = $result_supplier_purchases ? $result_supplier_purchases->fetch_all(MYSQLI_ASSOC) : [];

// Calculate totals for customer sales
$total_customer_sales = 0;
$total_customer_profit = 0;
foreach ($customer_sales as $sale) {
    $total_customer_sales += $sale['sale_total'];
    $total_customer_profit += $sale['profit'];
}

// Calculate totals for supplier purchases
$total_supplier_purchases = 0;
$total_potential_profit = 0;
foreach ($supplier_purchases as $purchase) {
    $total_supplier_purchases += $purchase['total_cost'];
    $total_potential_profit += $purchase['total_profit_potential'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas y Compras</title>
    <link rel="stylesheet" href="CSS/stylessales_history.css">
</head>
<body>
<div class="sales-history-management">
    <h2>📊 Historial de Ventas y Compras</h2>

    <!-- Statistics Dashboard -->
    <div class="stats-dashboard">
        <div class="stat-card customer-sales">
            <div class="stat-icon">🛒</div>
            <div class="stat-info">
                <h3>Ventas a Clientes</h3>
                <p class="stat-value">$<?php echo number_format($total_customer_sales, 2); ?></p>
                <small>Total vendido</small>
            </div>
        </div>
        <div class="stat-card customer-profit">
            <div class="stat-icon">💰</div>
            <div class="stat-info">
                <h3>Ganancia Realizada</h3>
                <p class="stat-value profit">$<?php echo number_format($total_customer_profit, 2); ?></p>
                <small>Ganancia de ventas</small>
            </div>
        </div>
        <div class="stat-card supplier-purchases">
            <div class="stat-icon">🏭</div>
            <div class="stat-info">
                <h3>Compras a Proveedores</h3>
                <p class="stat-value cost">$<?php echo number_format($total_supplier_purchases, 2); ?></p>
                <small>Total invertido</small>
            </div>
        </div>
        <div class="stat-card potential-profit">
            <div class="stat-icon">📈</div>
            <div class="stat-info">
                <h3>Ganancia Potencial</h3>
                <p class="stat-value potential">$<?php echo number_format($total_potential_profit, 2); ?></p>
                <small>Si se vende todo</small>
            </div>
        </div>
    </div>

    <!-- Purchase from Supplier Form -->
    <div class="form-section">
        <h3>🏭 Registrar Compra a Proveedor</h3>
        <form method="POST" class="purchase-form" id="purchaseForm">
            <input type="hidden" name="purchase_form_token" value="<?php echo $_SESSION['purchase_form_token']; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="supplier_id">Proveedor:</label>
                    <select name="supplier_id" id="supplier_id" required>
                        <option value="" disabled selected>Seleccione un proveedor</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>">
                                <?php echo htmlspecialchars($supplier['name']) . " - " . htmlspecialchars($supplier['contact']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="product_id">Producto:</label>
                    <select name="product_id" id="product_id" required>
                        <option value="" disabled selected>Seleccione un producto</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" data-current-price="<?php echo $product['purchase_price']; ?>">
                                <?php echo htmlspecialchars($product['name']) . " (Stock actual: {$product['stock']})"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Cantidad a Comprar:</label>
                    <input type="number" name="quantity" id="quantity" min="1" placeholder="Cantidad" required />
                </div>
                
                <div class="form-group">
                    <label for="purchase_price">Precio de Compra Unitario:</label>
                    <input type="number" name="purchase_price" id="purchase_price" step="0.01" min="0.01" placeholder="0.00" required />
                </div>
            </div>
            
            <div class="form-group">
                <label>Total de la Compra:</label>
                <div id="total-cost-preview" class="total-preview">$0.00</div>
            </div>
            
            <button type="submit" name="action" value="purchase_from_supplier" id="purchaseBtn" class="purchase-btn">
                🏭 Registrar Compra
            </button>
        </form>
    </div>

    <!-- Customer Sales History -->
    <div class="table-section">
        <h3>🛒 Historial de Ventas a Clientes</h3>
        <?php if (!empty($customer_sales)): ?>
            <div class="table-responsive">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>ID Venta</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Productos</th>
                            <th>Total Venta</th>
                            <th>Costo</th>
                            <th>Ganancia</th>
                            <th>Margen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customer_sales as $sale): ?>
                        <tr>
                            <td class="sale-id">#<?php echo htmlspecialchars($sale['sale_id']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                            <td>
                                <div class="customer-info">
                                    <strong><?php echo htmlspecialchars($sale['customer_name'] ?? 'Cliente General'); ?></strong>
                                    <?php if ($sale['customer_email']): ?>
                                        <br><small><?php echo htmlspecialchars($sale['customer_email']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="products-list"><?php echo htmlspecialchars($sale['products'] ?? 'N/A'); ?></td>
                            <td class="sale-total">$<?php echo number_format($sale['sale_total'], 2); ?></td>
                            <td class="cost">$<?php echo number_format($sale['total_cost'], 2); ?></td>
                            <td class="profit">$<?php echo number_format($sale['profit'], 2); ?></td>
                            <td class="margin">
                                <?php 
                                $margin = $sale['total_cost'] > 0 ? (($sale['profit'] / $sale['total_cost']) * 100) : 0;
                                echo number_format($margin, 1) . '%';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>🛒 No hay ventas registradas.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Supplier Purchases History -->
    <div class="table-section">
        <h3>🏭 Historial de Compras a Proveedores</h3>
        <?php if (!empty($supplier_purchases)): ?>
            <div class="table-responsive">
                <table class="purchases-table">
                    <thead>
                        <tr>
                            <th>ID Compra</th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Total Compra</th>
                            <th>Precio Venta</th>
                            <th>Ganancia Potencial</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supplier_purchases as $purchase): ?>
                        <tr>
                            <td class="purchase-id">#<?php echo htmlspecialchars($purchase['purchase_id']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($purchase['purchase_date'])); ?></td>
                            <td>
                                <div class="supplier-info">
                                    <strong><?php echo htmlspecialchars($purchase['supplier_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($purchase['supplier_contact']); ?></small>
                                </div>
                            </td>
                            <td class="product-name"><?php echo htmlspecialchars($purchase['product_name']); ?></td>
                            <td class="quantity"><?php echo htmlspecialchars($purchase['quantity']); ?></td>
                            <td class="unit-price">$<?php echo number_format($purchase['purchase_price'], 2); ?></td>
                            <td class="total-cost">$<?php echo number_format($purchase['total_cost'], 2); ?></td>
                            <td class="sale-price">$<?php echo number_format($purchase['current_sale_price'], 2); ?></td>
                            <td class="potential-profit">$<?php echo number_format($purchase['total_profit_potential'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>🏭 No hay compras registradas.</p>
                <p>Registre su primera compra a proveedor usando el formulario de arriba.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
let formSubmitted = false;

// Prevent multiple form submissions
document.getElementById('purchaseForm').addEventListener('submit', function(e) {
    if (formSubmitted) {
        e.preventDefault();
        alert('La compra ya está siendo procesada. Por favor espere.');
        return false;
    }
    
    formSubmitted = true;
    var purchaseBtn = document.getElementById('purchaseBtn');
    var originalText = purchaseBtn.textContent;
    purchaseBtn.disabled = true;
    purchaseBtn.textContent = '⏳ Procesando compra...';
    purchaseBtn.style.opacity = '0.6';
    
    // Reactivate after 15 seconds in case of network error
    setTimeout(function() {
        formSubmitted = false;
        purchaseBtn.disabled = false;
        purchaseBtn.textContent = originalText;
        purchaseBtn.style.opacity = '1';
    }, 15000);
});

// Update total cost preview when quantity or price changes
function updateCostPreview() {
    const quantityInput = document.getElementById('quantity');
    const priceInput = document.getElementById('purchase_price');
    const totalPreview = document.getElementById('total-cost-preview');
    
    const quantity = parseInt(quantityInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    
    const total = quantity * price;
    totalPreview.textContent = `$${total.toFixed(2)}`;
}

document.getElementById('quantity').addEventListener('input', updateCostPreview);
document.getElementById('purchase_price').addEventListener('input', updateCostPreview);

// Auto-fill current purchase price when product is selected
document.getElementById('product_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const currentPrice = selectedOption.dataset.currentPrice;
    
    if (currentPrice) {
        document.getElementById('purchase_price').value = currentPrice;
        updateCostPreview();
    }
});

// Initialize preview
updateCostPreview();
</script>
</body>
</html>