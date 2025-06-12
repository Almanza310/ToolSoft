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

// GENERAR TOKEN CSRF PARA PREVENIR ENVÍO MÚLTIPLE
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

// Fetch all categories for the form
$result_categories = $conexion->query("SELECT id, name FROM categories");
if ($result_categories === false) {
    die("Error al obtener las categorías: " . $conexion->error);
}
$categories = $result_categories->fetch_all(MYSQLI_ASSOC);

// Fetch all suppliers for the form
$result_suppliers = $conexion->query("SELECT s.id, s.name, c.name as category_name FROM suppliers s LEFT JOIN categories c ON s.category_id = c.id ORDER BY s.name");
if ($result_suppliers === false) {
    die("Error al obtener los proveedores: " . $conexion->error);
}
$suppliers = $result_suppliers->fetch_all(MYSQLI_ASSOC);

// Handle Add Product (Purchase) - MEJORADO CON PROTECCIÓN CSRF
if (isset($_POST['action']) && $_POST['action'] === 'add_product') {
    // VERIFICAR TOKEN CSRF
    if (!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']) {
        $_SESSION['error_message'] = "Token de seguridad inválido. Intente nuevamente.";
        header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
        exit;
    }
    
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'];
    $supplier_id = $_POST['supplier_id'] ?? null;
    $description = trim($_POST['description']);
    $purchase_price = trim($_POST['purchase_price']);
    $price = trim($_POST['price']);
    $stock = trim($_POST['stock']);
    $image_path = 'imagenes/placeholder.jpg'; // Default image path

    // VERIFICACIÓN MEJORADA DE DUPLICADOS - Incluir más campos para mayor precisión
    $check_stmt = $conexion->prepare("
        SELECT id FROM product 
        WHERE name = ? AND category_id = ? AND price = ? AND purchase_price = ?
    ");
    $check_stmt->bind_param("sidd", $name, $category_id, $price, $purchase_price);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Este producto con las mismas características ya existe en la base de datos.";
        header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
        exit;
    }
    $check_stmt->close();

    // REGENERAR TOKEN DESPUÉS DE VERIFICACIÓN EXITOSA
    $_SESSION['form_token'] = bin2hex(random_bytes(32));

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/';
        
        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
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
        // Verificar si la tabla product tiene la columna supplier_id
        $check_column = $conexion->query("SHOW COLUMNS FROM product LIKE 'supplier_id'");
        $has_supplier_column = $check_column->num_rows > 0;
        
        if ($has_supplier_column) {
            $stmt = $conexion->prepare("INSERT INTO product (name, category_id, supplier_id, description, purchase_price, price, stock, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                $_SESSION['error_message'] = "Error al preparar la inserción: " . $conexion->error;
                error_log("Error al preparar inserción: " . $conexion->error);
            } else {
                $stmt->bind_param("siisddis", $name, $category_id, $supplier_id, $description, $purchase_price, $price, $stock, $image_path);
            }
        } else {
            // Fallback para tablas sin columna supplier_id
            $stmt = $conexion->prepare("INSERT INTO product (name, category_id, description, purchase_price, price, stock, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                $_SESSION['error_message'] = "Error al preparar la inserción: " . $conexion->error;
                error_log("Error al preparar inserción: " . $conexion->error);
            } else {
                $stmt->bind_param("sisddis", $name, $category_id, $description, $purchase_price, $price, $stock, $image_path);
            }
        }
        
        if ($stmt && $stmt->execute()) {
            $new_id = $conexion->insert_id;
            
            // VERIFICAR QUE EL PRODUCTO SE INSERTÓ CORRECTAMENTE
            $verify_query = $conexion->query("SELECT * FROM product WHERE id = $new_id");
            $verify_result = $verify_query->fetch_assoc();
            error_log("Producto verificado después de inserción: " . print_r($verify_result, true));
            
            error_log("Producto agregado exitosamente: ID $new_id, Nombre: $name, Stock: $stock, Imagen: $image_path");
            $_SESSION['success_message'] = "Producto agregado exitosamente.";
        } else {
            $_SESSION['error_message'] = "Error al agregar el producto: " . $conexion->error;
            error_log("Error al ejecutar inserción: " . $conexion->error . " - Datos: name=$name, category_id=$category_id, stock=$stock");
        }
        if ($stmt) $stmt->close();
    }
    header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
    exit;
}

// Handle Edit Product - MEJORADO CON PROTECCIÓN CSRF
if (isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    // VERIFICAR TOKEN CSRF
    if (!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']) {
        $_SESSION['error_message'] = "Token de seguridad inválido. Intente nuevamente.";
        header('Location: http://' . $_SERVER['HTTP_HOST'] . '/admin_dashboard.php?tab=sales');
        exit;
    }
    
    $id = $_POST['product_id'];
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'];
    $supplier_id = $_POST['supplier_id'] ?? null;
    $description = trim($_POST['description']);
    $purchase_price = trim($_POST['purchase_price']);
    $price = trim($_POST['price']);
    $stock = trim($_POST['stock']);
    $image_path = $_POST['existing_image']; // Keep the existing image by default

    // REGENERAR TOKEN
    $_SESSION['form_token'] = bin2hex(random_bytes(32));

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
        // Verificar si la tabla product tiene la columna supplier_id
        $check_column = $conexion->query("SHOW COLUMNS FROM product LIKE 'supplier_id'");
        $has_supplier_column = $check_column->num_rows > 0;
        
        if ($has_supplier_column) {
            $stmt = $conexion->prepare("UPDATE product SET name = ?, category_id = ?, supplier_id = ?, description = ?, purchase_price = ?, price = ?, stock = ?, image = ? WHERE id = ?");
            if ($stmt === false) {
                $_SESSION['error_message'] = "Error al preparar la actualización: " . $conexion->error;
            } else {
                $stmt->bind_param("siisddisi", $name, $category_id, $supplier_id, $description, $purchase_price, $price, $stock, $image_path, $id);
            }
        } else {
            // Fallback para tablas sin columna supplier_id
            $stmt = $conexion->prepare("UPDATE product SET name = ?, category_id = ?, description = ?, purchase_price = ?, price = ?, stock = ?, image = ? WHERE id = ?");
            if ($stmt === false) {
                $_SESSION['error_message'] = "Error al preparar la actualización: " . $conexion->error;
            } else {
                $stmt->bind_param("sisddisi", $name, $category_id, $description, $purchase_price, $price, $stock, $image_path, $id);
            }
        }
        
        if ($stmt && $stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            if ($affected_rows > 0) {
                $_SESSION['success_message'] = "Producto actualizado exitosamente.";
            } else {
                $_SESSION['error_message'] = "No se encontró el producto con ID: " . $id . " o no hubo cambios.";
            }
        } else {
            $_SESSION['error_message'] = "Error al actualizar el producto: " . $conexion->error;
        }
        if ($stmt) $stmt->close();
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

// CONSULTA MEJORADA: Fetch all products with DISTINCT to avoid duplicates
// Verificar si la tabla product tiene la columna supplier_id
$check_column = $conexion->query("SHOW COLUMNS FROM product LIKE 'supplier_id'");
$has_supplier_column = $check_column->num_rows > 0;

if ($has_supplier_column) {
    $result_products = $conexion->query("
        SELECT DISTINCT p.id, p.name, p.description, p.purchase_price, p.price, p.stock, p.image, 
               c.name as category_name, s.name as supplier_name 
        FROM product p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        ORDER BY p.id DESC
    ");
} else {
    $result_products = $conexion->query("
        SELECT DISTINCT p.id, p.name, p.description, p.purchase_price, p.price, p.stock, p.image, 
               c.name as category_name 
        FROM product p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.id DESC
    ");
}

if ($result_products === false) {
    die("Error al obtener los productos: " . $conexion->error);
}
$products = $result_products->fetch_all(MYSQLI_ASSOC);

// Fetch product data for editing
$edit_product = null;
if (isset($_GET['edit_product'])) {
    $id = $_GET['edit_product'];
    if ($has_supplier_column) {
        $stmt = $conexion->prepare("SELECT id, name, category_id, supplier_id, description, purchase_price, price, stock, image FROM product WHERE id = ?");
    } else {
        $stmt = $conexion->prepare("SELECT id, name, category_id, description, purchase_price, price, stock, image FROM product WHERE id = ?");
    }
    
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

<div class="products-management">
    <h2>🛍️ Gestión de Productos</h2>

    <!-- Add/Edit Product Form (Purchase) - MEJORADO CON TOKEN CSRF -->
    <div class="form-section">
        <h3><?php echo isset($edit_product) ? '✏️ Editar Producto' : '➕ Agregar Nuevo Producto'; ?></h3>
        <form method="POST" class="user-form" enctype="multipart/form-data" id="productForm">
            <!-- TOKEN CSRF PARA PREVENIR ENVÍO MÚLTIPLE -->
            <input type="hidden" name="form_token" value="<?php echo $_SESSION['form_token']; ?>">
            <input type="hidden" name="product_id" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['id']) : ''; ?>">
            <input type="hidden" name="existing_image" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['image']) : 'imagenes/placeholder.jpg'; ?>">
            
            <div class="form-row">
                <input type="text" name="name" placeholder="Nombre del Producto" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['name']) : ''; ?>" required />
                <select name="category_id" required>
                    <option value="" disabled <?php echo !isset($edit_product) ? 'selected' : ''; ?>>Seleccione una categoría</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo isset($edit_product) && $edit_product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <select name="supplier_id">
                    <option value="">Seleccione un proveedor (opcional)</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>" 
                                <?php echo isset($edit_product) && isset($edit_product['supplier_id']) && $edit_product['supplier_id'] == $supplier['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($supplier['name']); ?>
                            <?php if ($supplier['category_name']): ?>
                                - <?php echo htmlspecialchars($supplier['category_name']); ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($suppliers)): ?>
                    <small style="color: #e74c3c;">
                        No hay proveedores disponibles. 
                        <a href="admin_dashboard.php?tab=proveedores" style="color: #3498db;">Agregar proveedor</a>
                    </small>
                <?php endif; ?>
            </div>
            
            <textarea name="description" placeholder="Descripción (opcional)" rows="3"><?php echo isset($edit_product) ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
            
            <div class="form-row">
                <input type="number" name="purchase_price" step="0.01" placeholder="Precio de Compra" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['purchase_price']) : ''; ?>" required />
                <input type="number" name="price" step="0.01" placeholder="Precio de Venta" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['price']) : ''; ?>" required />
                <input type="number" name="stock" placeholder="Cantidad en Stock" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['stock']) : ''; ?>" required />
            </div>
            
            <div class="image-upload">
                <label for="image">📷 Imagen del Producto (opcional):</label>
                <?php if (isset($edit_product) && $edit_product['image'] !== 'imagenes/placeholder.jpg'): ?>
                    <div class="current-image">
                        <p>Imagen actual:</p>
                        <img src="../<?php echo htmlspecialchars($edit_product['image']); ?>" alt="Producto" style="max-width: 100px; max-height: 100px; border-radius: 8px; border: 2px solid #ddd;">
                    </div>
                <?php endif; ?>
                <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif">
                <small>Formatos permitidos: JPEG, PNG, GIF. Tamaño máximo: 2MB</small>
            </div>
            
            <button type="submit" name="action" value="<?php echo isset($edit_product) ? 'edit_product' : 'add_product'; ?>" id="submitBtn" class="submit-btn">
                <?php echo isset($edit_product) ? '✅ Actualizar Producto' : '➕ Agregar Producto'; ?>
            </button>
            
            <?php if (isset($edit_product)): ?>
                <a href="admin_dashboard.php?tab=sales" class="cancel-btn">❌ Cancelar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Products Table -->
    <div class="table-section">
        <h3>📦 Inventario de Productos</h3>
        <?php if (!empty($products)): ?>
            <div class="table-responsive">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <?php if ($has_supplier_column): ?>
                                <th>Proveedor</th>
                            <?php endif; ?>
                            <th>Descripción</th>
                            <th>Precio Compra</th>
                            <th>Precio Venta</th>
                            <th>Stock</th>
                            <th>Margen</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['id']); ?></td>
                            <td class="image-cell">
                                <?php
                                $image_path = '../' . htmlspecialchars($product['image']);
                                if (file_exists($image_path) && is_readable($image_path)) {
                                    echo '<img src="' . $image_path . '" alt="' . htmlspecialchars($product['name']) . '" class="product-thumbnail">';
                                } else {
                                    echo '<div class="no-image">📷</div>';
                                    error_log("Imagen no encontrada en: $image_path");
                                }
                                ?>
                            </td>
                            <td class="product-name"><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></td>
                            <?php if ($has_supplier_column): ?>
                                <td class="supplier-name">
                                    <?php if (isset($product['supplier_name']) && $product['supplier_name']): ?>
                                        <span class="supplier-badge">🏢 <?php echo htmlspecialchars($product['supplier_name']); ?></span>
                                    <?php else: ?>
                                        <span class="no-supplier">Sin proveedor</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="description"><?php echo htmlspecialchars(substr($product['description'] ?? 'Sin descripción', 0, 50)) . (strlen($product['description'] ?? '') > 50 ? '...' : ''); ?></td>
                            <td class="price">$<?php echo number_format($product['purchase_price'], 2); ?></td>
                            <td class="price">$<?php echo number_format($product['price'], 2); ?></td>
                            <td class="stock <?php echo $product['stock'] < 10 ? 'low-stock' : ''; ?>">
                                <?php echo htmlspecialchars($product['stock']); ?>
                                <?php if ($product['stock'] < 10): ?>
                                    <span class="stock-warning">⚠️</span>
                                <?php endif; ?>
                            </td>
                            <td class="margin">
                                <?php 
                                $margin = (($product['price'] - $product['purchase_price']) / $product['purchase_price']) * 100;
                                echo number_format($margin, 1) . '%';
                                ?>
                            </td>
                            <td class="actions">
                                <a href="admin_dashboard.php?tab=sales&edit_product=<?php echo $product['id']; ?>" class="edit-btn" title="Editar producto">✏️</a>
                                <a href="admin_dashboard.php?tab=sales&delete_product=<?php echo $product['id']; ?>" class="delete-btn" onclick="return confirm('¿Seguro que desea eliminar este producto?')" title="Eliminar producto">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>📦 No hay productos registrados.</p>
                <p>Comience agregando su primer producto usando el formulario de arriba.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SQL para agregar la columna supplier_id si no existe -->
<?php if (!$has_supplier_column): ?>
    <div class="alert-section" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 8px;">
        <h4 style="color: #856404; margin: 0 0 10px 0;">⚠️ Actualización de Base de Datos Requerida</h4>
        <p style="color: #856404; margin: 0;">
            Para usar la funcionalidad de proveedores, necesitas ejecutar este comando SQL en tu base de datos:
        </p>
        <code style="background: #f8f9fa; padding: 10px; display: block; margin: 10px 0; border-radius: 4px; color: #495057;">
            ALTER TABLE product ADD COLUMN supplier_id INT NULL, ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;
        </code>
        <p style="color: #856404; margin: 0; font-size: 14px;">
            Después de ejecutar este comando, recarga la página para ver la funcionalidad completa de proveedores.
        </p>
    </div>
<?php endif; ?>

<style>
.products-management {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.form-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-section h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 3px solid #2ecc71;
    padding-bottom: 10px;
}

.user-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.user-form input,
.user-form select,
.user-form textarea {
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.user-form input:focus,
.user-form select:focus,
.user-form textarea:focus {
    outline: none;
    border-color: #2ecc71;
    box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
}

.image-upload {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.current-image {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    text-align: center;
}

.submit-btn {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.submit-btn:hover {
    background: linear-gradient(135deg, #27ae60, #229954);
    transform: translateY(-2px);
}

.submit-btn:disabled {
    background: #95a5a6;
    cursor: not-allowed;
    transform: none;
}

.cancel-btn {
    background: #e74c3c;
    color: white;
    padding: 15px 30px;
    border-radius: 8px;
    text-decoration: none;
    text-align: center;
    font-weight: 600;
    transition: background 0.3s ease;
}

.cancel-btn:hover {
    background: #c0392b;
}

.table-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.table-section h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 3px solid #3498db;
    padding-bottom: 10px;
}

.table-responsive {
    overflow-x: auto;
}

.products-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.products-table th,
.products-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.products-table th {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
}

.products-table tr:hover {
    background-color: #f8f9fa;
}

.image-cell {
    text-align: center;
}

.product-thumbnail {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #ddd;
}

.no-image {
    width: 50px;
    height: 50px;
    background: #f0f0f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #999;
}

.product-name {
    font-weight: 600;
    color: #2c3e50;
}

.supplier-name {
    text-align: center;
}

.supplier-badge {
    background: linear-gradient(135deg, #8e44ad, #9b59b6);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.no-supplier {
    color: #95a5a6;
    font-style: italic;
    font-size: 12px;
}

.description {
    max-width: 150px;
    font-size: 14px;
    color: #666;
}

.price {
    font-weight: 600;
    color: #27ae60;
}

.stock {
    font-weight: 600;
    text-align: center;
}

.low-stock {
    color: #e74c3c;
}

.stock-warning {
    margin-left: 5px;
}

.margin {
    font-weight: 600;
    color: #8e44ad;
}

.actions {
    text-align: center;
}

.edit-btn,
.delete-btn {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 2px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 16px;
    transition: all 0.3s ease;
}

.edit-btn {
    background: #f39c12;
    color: white;
}

.edit-btn:hover {
    background: #e67e22;
    transform: scale(1.1);
}

.delete-btn {
    background: #e74c3c;
    color: white;
}

.delete-btn:hover {
    background: #c0392b;
    transform: scale(1.1);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state p {
    font-size: 18px;
    margin: 10px 0;
}

@media (max-width: 768px) {
    .products-management {
        padding: 10px;
    }
    
    .form-section,
    .table-section {
        padding: 20px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .products-table {
        font-size: 14px;
    }
    
    .products-table th,
    .products-table td {
        padding: 8px;
    }
    
    .description {
        max-width: 100px;
    }
    
    .supplier-badge {
        font-size: 10px;
        padding: 2px 6px;
    }
}
</style>

<!-- JAVASCRIPT MEJORADO PARA PREVENIR ENVÍO MÚLTIPLE -->
<script>
let formSubmitted = false;

document.getElementById('productForm').addEventListener('submit', function(e) {
    if (formSubmitted) {
        e.preventDefault();
        alert('El formulario ya está siendo procesado. Por favor espere.');
        return false;
    }
    
    formSubmitted = true;
    var submitBtn = document.getElementById('submitBtn');
    var originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Procesando...';
    submitBtn.style.opacity = '0.6';
    
    // Reactivar después de 15 segundos en caso de error de red
    setTimeout(function() {
        formSubmitted = false;
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        submitBtn.style.opacity = '1';
    }, 15000);
});

// Prevenir envío con Enter múltiple
document.getElementById('productForm').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && formSubmitted) {
        e.preventDefault();
        return false;
    }
});

// Validación en tiempo real
document.querySelector('input[name="purchase_price"]').addEventListener('input', function() {
    const purchasePrice = parseFloat(this.value);
    const salePrice = parseFloat(document.querySelector('input[name="price"]').value);
    
    if (purchasePrice && salePrice && purchasePrice >= salePrice) {
        this.style.borderColor = '#e74c3c';
        document.querySelector('input[name="price"]').style.borderColor = '#e74c3c';
    } else {
        this.style.borderColor = '#e0e0e0';
        document.querySelector('input[name="price"]').style.borderColor = '#e0e0e0';
    }
});

document.querySelector('input[name="price"]').addEventListener('input', function() {
    const purchasePrice = parseFloat(document.querySelector('input[name="purchase_price"]').value);
    const salePrice = parseFloat(this.value);
    
    if (purchasePrice && salePrice && purchasePrice >= salePrice) {
        this.style.borderColor = '#e74c3c';
        document.querySelector('input[name="purchase_price"]').style.borderColor = '#e74c3c';
    } else {
        this.style.borderColor = '#e0e0e0';
        document.querySelector('input[name="purchase_price"]').style.borderColor = '#e0e0e0';
    }
});
</script>