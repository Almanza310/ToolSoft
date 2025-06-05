<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_log("Inicio de procesamiento de productos. Método: " . $_SERVER['REQUEST_METHOD']);

// Use __DIR__ to get the directory of the current file and adjust the path to conexion.php
require_once __DIR__ . '/../conexion.php';

// Initialize session messages (no session_start() since it's already started in admin_dashboard.php)
$_SESSION['success_message'] = '';
$_SESSION['error_message'] = '';

// Fetch all categories for the form
$result_categories = $conexion->query("SELECT id, name FROM categories");
if ($result_categories === false) {
    die("Error al obtener las categorías: " . $conexion->error);
}
$categories = $result_categories->fetch_all(MYSQLI_ASSOC);

// Handle Add Product (Purchase)
if (isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'];
    $description = trim($_POST['description']);
    $purchase_price = trim($_POST['purchase_price']);
    $price = trim($_POST['price']);
    $stock = trim($_POST['stock']);
    $image_path = 'imagenes/placeholder.jpg'; // Default image path

    // Verificar si el producto ya existe
    $check_stmt = $conexion->prepare("SELECT id FROM product WHERE name = ? AND category_id = ?");
    $check_stmt->bind_param("si", $name, $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Este producto ya existe en la base de datos.";
        header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
        exit;
    }
    $check_stmt->close();

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/';
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['image']['type'], $allowed_types)) {
            if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                $_SESSION['error_message'] = "La imagen no debe exceder 2MB.";
                header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
                exit;
            }
            if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                $image_path = 'uploads/' . $image_name;
                error_log("Imagen subida exitosamente: $image_path");
            } else {
                $error = error_get_last();
                $_SESSION['error_message'] = "Error al subir la imagen: " . ($error ? $error['message'] : 'Permisos o ruta inválida');
                error_log("Error al mover archivo: " . ($error ? $error['message'] : 'Desconocido'));
                header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
                exit;
            }
        } else {
            $_SESSION['error_message'] = "Solo se permiten imágenes JPEG, PNG o GIF.";
            header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
            exit;
        }
    }

    if (empty($name) || empty($category_id) || empty($purchase_price) || empty($price) || empty($stock)) {
        $_SESSION['error_message'] = "Todos los campos obligatorios deben completarse.";
    } elseif ($purchase_price >= $price) {
        $_SESSION['error_message'] = "El precio de venta debe ser mayor al precio de compra.";
    } else {
        $stmt = $conexion->prepare("INSERT INTO product (name, category_id, description, purchase_price, price, stock, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $_SESSION['error_message'] = "Error al preparar la inserción: " . $conexion->error;
            error_log("Error al preparar inserción: " . $conexion->error);
        } else {
            $stmt->bind_param("sisddis", $name, $category_id, $description, $purchase_price, $price, $stock, $image_path);
            if ($stmt->execute()) {
                $new_id = $conexion->insert_id;
                error_log("Producto agregado exitosamente: ID $new_id, Nombre: $name, Stock: $stock, Imagen: $image_path");
                $_SESSION['success_message'] = "Producto agregado exitosamente.";
            } else {
                $_SESSION['error_message'] = "Error al agregar el producto: " . $conexion->error;
                error_log("Error al ejecutar inserción: " . $conexion->error . " - Datos: name=$name, category_id=$category_id, stock=$stock");
            }
            $stmt->close();
        }
    }
    header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
    exit;
}

// Handle Edit Product
if (isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $id = $_POST['product_id'];
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'];
    $description = trim($_POST['description']);
    $purchase_price = trim($_POST['purchase_price']);
    $price = trim($_POST['price']);
    $stock = trim($_POST['stock']);
    $image_path = $_POST['existing_image']; // Keep the existing image by default

    // Handle image upload if a new image is provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/';
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['image']['type'], $allowed_types)) {
            if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                $_SESSION['error_message'] = "La imagen no debe exceder 2MB.";
                header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
                exit;
            }
            if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                $image_path = 'uploads/' . $image_name;
                if ($_POST['existing_image'] !== 'imagenes/placeholder.jpg') {
                    @unlink(__DIR__ . '/../' . $_POST['existing_image']);
                }
            } else {
                $error = error_get_last();
                $_SESSION['error_message'] = "Error al subir la imagen: " . ($error ? $error['message'] : 'Permisos o ruta inválida');
                error_log("Error al mover archivo: " . ($error ? $error['message'] : 'Desconocido'));
                header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
                exit;
            }
        } else {
            $_SESSION['error_message'] = "Solo se permiten imágenes JPEG, PNG o GIF.";
            header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
            exit;
        }
    }

    if (empty($name) || empty($category_id) || empty($purchase_price) || empty($price) || empty($stock)) {
        $_SESSION['error_message'] = "Todos los campos obligatorios deben completarse.";
    } elseif ($purchase_price >= $price) {
        $_SESSION['error_message'] = "El precio de venta debe ser mayor al precio de compra.";
    } else {
        $stmt = $conexion->prepare("UPDATE product SET name = ?, category_id = ?, description = ?, purchase_price = ?, price = ?, stock = ?, image = ? WHERE id = ?");
        if ($stmt === false) {
            $_SESSION['error_message'] = "Error al preparar la actualización: " . $conexion->error;
        } else {
            $stmt->bind_param("sisddisi", $name, $category_id, $description, $purchase_price, $price, $stock, $image_path, $id);
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                if ($affected_rows > 0) {
                    $_SESSION['success_message'] = "Producto actualizado exitosamente.";
                } else {
                    $_SESSION['error_message'] = "No se encontró el producto con ID: " . $id . " o no hubo cambios.";
                }
            } else {
                $_SESSION['error_message'] = "Error al actualizar el producto: " . $conexion->error;
            }
            $stmt->close();
        }
    }
    header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
    exit;
}

// Handle Delete Product
if (isset($_GET['delete_product'])) {
    $id = $_GET['delete_product'];

    // Fetch the image path before deleting the product
    $stmt = $conexion->prepare("SELECT image FROM product WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $image_path = $result['image'] ?? 'imagenes/placeholder.jpg';

    // Delete the product
    $stmt = $conexion->prepare("DELETE FROM product WHERE id = ?");
    if ($stmt === false) {
        $_SESSION['error_message'] = "Error al preparar la eliminación: " . $conexion->error;
    } else {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            if ($affected_rows > 0) {
                if ($image_path !== 'imagenes/placeholder.jpg') {
                    @unlink(__DIR__ . '/../' . $image_path);
                }
                $_SESSION['success_message'] = "Producto eliminado exitosamente.";
            } else {
                $_SESSION['error_message'] = "No se encontró el producto con ID: " . $id;
            }
        } else {
            $_SESSION['error_message'] = "Error al eliminar el producto: " . $conexion->error;
        }
        $stmt->close();
    }
    header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
    exit;
}

// Fetch all products with DISTINCT to avoid duplicates
$result_products = $conexion->query("SELECT DISTINCT p.id, p.name, p.description, p.purchase_price, p.price, p.stock, p.image, c.name as category_name FROM product p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
if ($result_products === false) {
    die("Error al obtener los productos: " . $conexion->error);
}
$products = $result_products->fetch_all(MYSQLI_ASSOC);

// Fetch product data for editing
$edit_product = null;
if (isset($_GET['edit_product'])) {
    $id = $_GET['edit_product'];
    $stmt = $conexion->prepare("SELECT id, name, category_id, description, purchase_price, price, stock, image FROM product WHERE id = ?");
    if ($stmt === false) {
        $_SESSION['error_message'] = "Error al preparar la consulta de edición: " . $conexion->error;
    } else {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $edit_product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>

<h2>Gestión de Productos y Ventas</h2>

<!-- Add/Edit Product Form (Purchase) -->
<h3><?php echo isset($edit_product) ? 'Editar Producto' : 'Comprar Producto'; ?></h3>
<form method="POST" class="user-form" enctype="multipart/form-data">
    <input type="hidden" name="product_id" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['id']) : ''; ?>">
    <input type="hidden" name="existing_image" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['image']) : 'imagenes/placeholder.jpg'; ?>">
    <input type="text" name="name" placeholder="Nombre del Producto" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['name']) : ''; ?>" required />
    <select name="category_id" required>
        <option value="" disabled <?php echo !isset($edit_product) ? 'selected' : ''; ?>>Seleccione una categoría</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?php echo $category['id']; ?>" <?php echo isset($edit_product) && $edit_product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($category['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <textarea name="description" placeholder="Descripción (opcional)"><?php echo isset($edit_product) ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
    <input type="number" name="purchase_price" step="0.01" placeholder="Precio de Compra" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['purchase_price']) : ''; ?>" required />
    <input type="number" name="price" step="0.01" placeholder="Precio de Venta" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['price']) : ''; ?>" required />
    <input type="number" name="stock" placeholder="Cantidad" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['stock']) : ''; ?>" required />
    <label for="image">Imagen del Producto (opcional):</label>
    <?php if (isset($edit_product) && $edit_product['image'] !== 'imagenes/placeholder.jpg'): ?>
        <p>Imagen actual: <img src="../<?php echo htmlspecialchars($edit_product['image']); ?>" alt="Producto" style="max-width: 100px; max-height: 100px;"></p>
    <?php endif; ?>
    <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif">
    <button type="submit" name="action" value="<?php echo isset($edit_product) ? 'edit_product' : 'add_product'; ?>">
        <?php echo isset($edit_product) ? 'Actualizar' : 'Comprar'; ?>
    </button>
</form>

<script>
document.querySelector('form').addEventListener('submit', function(e) {
    var submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Procesando...';
});
</script>

<!-- Sell Product Form -->
<h3>Vender Producto</h3>
<form method="POST" class="user-form">
    <select name="product_id" required>
        <option value="" disabled selected>Seleccione un producto</option>
        <?php foreach ($products as $product): ?>
            <option value="<?php echo $product['id']; ?>">
                <?php echo htmlspecialchars($product['name']) . " (Stock: {$product['stock']})"; ?>
            </option>
        <?php endforeach; ?>
    </select>
    <input type="number" name="quantity" placeholder="Cantidad" required />
    <button type="submit" name="action" value="sell_product">Vender</button>
</form>

<!-- Products Table -->
<h3>Inventario de Productos</h3>
<?php if (!empty($products)): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Descripción</th>
                <th>Precio de Compra</th>
                <th>Precio de Venta</th>
                <th>Stock</th>
                <th>Imagen</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
            <tr>
                <td><?php echo htmlspecialchars($product['id']); ?></td>
                <td><?php echo htmlspecialchars($product['name']); ?></td>
                <td><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></td>
                <td><?php echo htmlspecialchars($product['description'] ?? 'Sin descripción'); ?></td>
                <td>$<?php echo number_format($product['purchase_price'], 2); ?></td>
                <td>$<?php echo number_format($product['price'], 2); ?></td>
                <td><?php echo htmlspecialchars($product['stock']); ?></td>
                <td>
                    <?php
                    $image_path = '../' . htmlspecialchars($product['image']);
                    if (file_exists($image_path) && is_readable($image_path)) {
                        echo '<img src="' . $image_path . '" alt="' . htmlspecialchars($product['name']) . '" style="max-width: 50px; max-height: 50px; border: 1px solid #ccc; padding: 2px;">';
                    } else {
                        echo '<span>No disponible</span>';
                        error_log("Imagen no encontrada en: $image_path");
                    }
                    ?>
                </td>
                <td class="actions">
                    <a href="admin_dashboard.php?tab=sales&edit_product=<?php echo $product['id']; ?>" class="edit-btn">Editar</a>
                    <a href="admin_dashboard.php?tab=sales&delete_product=<?php echo $product['id']; ?>" class="delete-btn" onclick="return confirm('¿Seguro que desea eliminar este producto?')">Eliminar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No hay productos registrados.</p>
<?php endif; ?>

<!-- Sales History -->
<h3>Historial de Ventas</h3>
<?php
$result_sales = $conexion->query("SELECT sd.id, p.name, sd.quantity, sd.subtotal, (sd.subtotal - (p.purchase_price * sd.quantity)) as profit 
                                 FROM saledetail sd 
                                 JOIN product p ON sd.product_id = p.id 
                                 JOIN sale s ON sd.sale_id = s.id");
if ($result_sales === false) {
    die("Error al obtener el historial de ventas: " . $conexion->error);
}
$sales = $result_sales->fetch_all(MYSQLI_ASSOC);
?>
<?php if (!empty($sales)): ?>
    <table>
        <thead>
            <tr>
                <th>ID Venta</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Subtotal</th>
                <th>Ganancia</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sales as $sale): ?>
            <tr>
                <td><?php echo htmlspecialchars($sale['id']); ?></td>
                <td><?php echo htmlspecialchars($sale['name']); ?></td>
                <td><?php echo htmlspecialchars($sale['quantity']); ?></td>
                <td>$<?php echo number_format($sale['subtotal'], 2); ?></td>
                <td>$<?php echo number_format($sale['profit'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No hay ventas registradas.</p>
<?php endif; ?>