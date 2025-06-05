<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Use __DIR__ to get the directory of the current file and adjust the path to conexion.php
require_once __DIR__ . '/../conexion.php';

// Initialize session messages (no need for session_start() here)
$_SESSION['success_message'] = '';
$_SESSION['error_message'] = '';

// Fetch all categories for the dropdown
$result_categories = $conexion->query("SELECT id, name FROM categories");
if ($result_categories === false) {
    $categories = [];
} else {
    $categories = $result_categories->fetch_all(MYSQLI_ASSOC);
}

// Handle Add Supplier
if (isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $category_id = $_POST['category_id'];  // Corregido de categories_id a category_id

    if (empty($name) || empty($contact) || empty($email) || empty($category_id)) {
        $_SESSION['error_message'] = "Todos los campos son obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Correo electrónico inválido.";
    } else {
        $stmt = $conexion->prepare("SELECT id FROM suppliers WHERE email = ?");
        if ($stmt === false) {
            $_SESSION['error_message'] = "Error al preparar la consulta: " . $conexion->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $_SESSION['error_message'] = "El correo ya está registrado.";
            } else {
                $stmt = $conexion->prepare("INSERT INTO suppliers (name, contact, email, category_id) VALUES (?, ?, ?, ?)");  // Corregido a category_id
                if ($stmt === false) {
                    $_SESSION['error_message'] = "Error al preparar la inserción: " . $conexion->error;
                } else {
                    $stmt->bind_param("sssi", $name, $contact, $email, $category_id);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Proveedor agregado exitosamente.";
                    } else {
                        $_SESSION['error_message'] = "Error al agregar el proveedor: " . $conexion->error;
                    }
                }
            }
            $stmt->close();
        }
    }
    header('Location: admin_dashboard.php?tab=proveedores');
    exit;
}

// Handle Edit Supplier
if (isset($_POST['action']) && $_POST['action'] === 'edit_supplier') {
    $id = $_POST['supplier_id'];
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $category_id = $_POST['category_id'];  // Corregido de categories_id a category_id

    if (empty($name) || empty($contact) || empty($email) || empty($category_id)) {
        $_SESSION['error_message'] = "Todos los campos son obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Correo electrónico inválido.";
    } else {
        $stmt = $conexion->prepare("SELECT id FROM suppliers WHERE email = ? AND id != ?");
        if ($stmt === false) {
            $_SESSION['error_message'] = "Error al preparar la consulta: " . $conexion->error;
        } else {
            $stmt->bind_param("si", $email, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $_SESSION['error_message'] = "El correo ya está registrado por otro proveedor.";
            } else {
                $stmt = $conexion->prepare("UPDATE suppliers SET name = ?, contact = ?, email = ?, category_id = ? WHERE id = ?");  // Corregido a category_id
                if ($stmt === false) {
                    $_SESSION['error_message'] = "Error al preparar la actualización: " . $conexion->error;
                } else {
                    $stmt->bind_param("sssii", $name, $contact, $email, $category_id, $id);
                    if ($stmt->execute()) {
                        $affected_rows = $stmt->affected_rows;
                        if ($affected_rows > 0) {
                            $_SESSION['success_message'] = "Proveedor actualizado exitosamente.";
                        } else {
                            $_SESSION['error_message'] = "No se encontró el proveedor con ID: " . $id . " o no hubo cambios.";
                        }
                    } else {
                        $_SESSION['error_message'] = "Error al actualizar el proveedor: " . $conexion->error;
                    }
                }
            }
            $stmt->close();
        }
    }
    header('Location: admin_dashboard.php?tab=proveedores');
    exit;
}

// Handle Delete Supplier
if (isset($_GET['delete_supplier'])) {
    $id = $_GET['delete_supplier'];
    $stmt = $conexion->prepare("DELETE FROM suppliers WHERE id = ?");
    if ($stmt === false) {
        $_SESSION['error_message'] = "Error al preparar la eliminación: " . $conexion->error;
    } else {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            if ($affected_rows > 0) {
                $_SESSION['success_message'] = "Proveedor eliminado exitosamente.";
            } else {
                $_SESSION['error_message'] = "No se encontró el proveedor con ID: " . $id;
            }
        } else {
            $_SESSION['error_message'] = "Error al eliminar el proveedor: " . $conexion->error;
        }
        $stmt->close();
    }
    header('Location: admin_dashboard.php?tab=proveedores');
    exit;
}

// Fetch all suppliers with their category names
$result_suppliers = $conexion->query("SELECT s.id, s.name, s.contact, s.email, c.name AS category FROM suppliers s LEFT JOIN categories c ON s.category_id = c.id");  // Corregido a category_id
if ($result_suppliers === false) {
    $suppliers = [];
} else {
    $suppliers = $result_suppliers->fetch_all(MYSQLI_ASSOC);
}

// Fetch supplier data for editing
$edit_supplier = null;
if (isset($_GET['edit_supplier'])) {
    $id = $_GET['edit_supplier'];
    $stmt = $conexion->prepare("SELECT id, name, contact, email, category_id FROM suppliers WHERE id = ?");  // Corregido a category_id
    if ($stmt === false) {
        $_SESSION['error_message'] = "Error al preparar la consulta de edición: " . $conexion->error;
    } else {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $edit_supplier = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>

<h2>Gestión de Proveedores</h2>

<!-- Add/Edit Supplier Form -->
<h3><?php echo isset($edit_supplier) ? 'Editar Proveedor' : 'Agregar Proveedor'; ?></h3>
<?php if (empty($categories)): ?>
    <p>No hay categorías disponibles. Por favor, agregue una categoría desde la pestaña <a href="admin_dashboard.php?tab=categorias">Categorías</a>.</p>
<?php else: ?>
    <form method="POST" class="user-form">
        <input type="hidden" name="supplier_id" value="<?php echo isset($edit_supplier) ? htmlspecialchars($edit_supplier['id']) : ''; ?>">
        <input type="text" name="name" placeholder="Nombre" value="<?php echo isset($edit_supplier) ? htmlspecialchars($edit_supplier['name']) : ''; ?>" required />
        <input type="text" name="contact" placeholder="Contacto" value="<?php echo isset($edit_supplier) ? htmlspecialchars($edit_supplier['contact']) : ''; ?>" required />
        <input type="email" name="email" placeholder="Correo" value="<?php echo isset($edit_supplier) ? htmlspecialchars($edit_supplier['email']) : ''; ?>" required />
        <select name="category_id" required>  <!-- Corregido de categories_id a category_id -->
            <option value="" disabled <?php echo !isset($edit_supplier) ? 'selected' : ''; ?>>Seleccione una categoría</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['id']; ?>" <?php echo isset($edit_supplier) && $edit_supplier['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="action" value="<?php echo isset($edit_supplier) ? 'edit_supplier' : 'add_supplier'; ?>">
            <?php echo isset($edit_supplier) ? 'Actualizar' : 'Registrar'; ?>
        </button>
    </form>
<?php endif; ?>

<!-- Suppliers Table -->
<?php if (!empty($suppliers)): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Contacto</th>
                <th>Correo</th>
                <th>Categoría</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($suppliers as $supplier): ?>
            <tr>
                <td><?php echo htmlspecialchars($supplier['id']); ?></td>
                <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                <td><?php echo htmlspecialchars($supplier['contact']); ?></td>
                <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                <td><?php echo htmlspecialchars($supplier['category'] ?? 'N/A'); ?></td>
                <td class="actions">
                    <a href="admin_dashboard.php?tab=proveedores&edit_supplier=<?php echo $supplier['id']; ?>" class="edit-btn">Editar</a>
                    <a href="admin_dashboard.php?tab=proveedores&delete_supplier=<?php echo $supplier['id']; ?>" class="delete-btn" onclick="return confirm('¿Seguro que desea eliminar este proveedor?')">Eliminar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No hay proveedores registrados.</p>
<?php endif; ?>