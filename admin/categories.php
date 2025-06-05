<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Use __DIR__ to get the directory of the current file and adjust the path to conexion.php
require_once __DIR__ . '/../conexion.php';

// Initialize session messages
$_SESSION['success_message'] = '';
$_SESSION['error_message'] = '';

// Handle Add Category
if (isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if (empty($name)) {
        $_SESSION['error_message'] = "El nombre de la categoría es obligatorio.";
    } else {
        $stmt = $conexion->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        if ($stmt === false) {
            $_SESSION['error_message'] = "Error al preparar la inserción: " . $conexion->error;
        } else {
            $stmt->bind_param("ss", $name, $description);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Categoría agregada exitosamente.";
            } else {
                $_SESSION['error_message'] = "Error al agregar la categoría: " . $conexion->error;
            }
            $stmt->close();
        }
    }
    header('Location: admin_dashboard.php?tab=categorias');
    exit;
}

// Handle Edit Category
if (isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    $id = $_POST['category_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if (empty($name)) {
        $_SESSION['error_message'] = "El nombre de la categoría es obligatorio.";
    } else {
        $stmt = $conexion->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        if ($stmt === false) {
            $_SESSION['error_message'] = "Error al preparar la actualización: " . $conexion->error;
        } else {
            $stmt->bind_param("ssi", $name, $description, $id);
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                if ($affected_rows > 0) {
                    $_SESSION['success_message'] = "Categoría actualizada exitosamente.";
                } else {
                    $_SESSION['error_message'] = "No se encontró la categoría con ID: " . $id . " o no hubo cambios.";
                }
            } else {
                $_SESSION['error_message'] = "Error al actualizar la categoría: " . $conexion->error;
            }
            $stmt->close();
        }
    }
    header('Location: admin_dashboard.php?tab=categorias');
    exit;
}

// Handle Delete Category
if (isset($_GET['delete_category'])) {
    $id = $_GET['delete_category'];

    // Check if the category is used by any product
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM product WHERE category_id = ?");
    if ($stmt === false) {
        $_SESSION['error_message'] = "Error al verificar productos asociados: " . $conexion->error;
    } else {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_row();
        $stmt->close();

        if ($result[0] > 0) {
            $_SESSION['error_message'] = "No se puede eliminar la categoría porque está asociada a productos.";
        } else {
            // Check if the category is used by any supplier
            $stmt = $conexion->prepare("SELECT COUNT(*) FROM suppliers WHERE category_id = ?");
            if ($stmt === false) {
                $_SESSION['error_message'] = "Error al verificar proveedores asociados: " . $conexion->error;
            } else {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_row();
                $stmt->close();

                if ($result[0] > 0) {
                    $_SESSION['error_message'] = "No se puede eliminar la categoría porque está asociada a proveedores.";
                } else {
                    // Proceed with deletion
                    $stmt = $conexion->prepare("DELETE FROM categories WHERE id = ?");
                    if ($stmt === false) {
                        $_SESSION['error_message'] = "Error al preparar la eliminación: " . $conexion->error;
                    } else {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            $affected_rows = $stmt->affected_rows;
                            if ($affected_rows > 0) {
                                $_SESSION['success_message'] = "Categoría eliminada exitosamente.";
                            } else {
                                $_SESSION['error_message'] = "No se encontró la categoría con ID: " . $id;
                            }
                        } else {
                            $_SESSION['error_message'] = "Error al eliminar la categoría: " . $conexion->error;
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
    header('Location: admin_dashboard.php?tab=categorias');
    exit;
}

// Fetch all categories
$result_categories = $conexion->query("SELECT id, name, description, created_at FROM categories");
if ($result_categories === false) {
    die("Error al obtener las categorías: " . $conexion->error);
}
$categories = $result_categories->fetch_all(MYSQLI_ASSOC);

// Fetch category data for editing
$edit_category = null;
if (isset($_GET['edit_category'])) {
    $id = $_GET['edit_category'];
    $stmt = $conexion->prepare("SELECT id, name, description FROM categories WHERE id = ?");
    if ($stmt === false) {
        $_SESSION['error_message'] = "Error al preparar la consulta de edición: " . $conexion->error;
    } else {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $edit_category = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>

<h2>Gestión de Categorías</h2>

<!-- Add/Edit Category Form -->
<h3><?php echo isset($edit_category) ? 'Editar Categoría' : 'Agregar Categoría'; ?></h3>
<form method="POST" class="user-form">
    <input type="hidden" name="category_id" value="<?php echo isset($edit_category) ? htmlspecialchars($edit_category['id']) : ''; ?>">
    <input type="text" name="name" placeholder="Nombre de la Categoría" value="<?php echo isset($edit_category) ? htmlspecialchars($edit_category['name']) : ''; ?>" required />
    <textarea name="description" placeholder="Descripción (opcional)"><?php echo isset($edit_category) ? htmlspecialchars($edit_category['description']) : ''; ?></textarea>
    <button type="submit" name="action" value="<?php echo isset($edit_category) ? 'edit_category' : 'add_category'; ?>">
        <?php echo isset($edit_category) ? 'Actualizar' : 'Agregar'; ?>
    </button>
</form>

<!-- Categories Table -->
<h3>Inventario de Categorías</h3>
<?php if (!empty($categories)): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Fecha de Creación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
            <tr>
                <td><?php echo htmlspecialchars($category['id']); ?></td>
                <td><?php echo htmlspecialchars($category['name']); ?></td>
                <td><?php echo htmlspecialchars($category['description'] ?? 'Sin descripción'); ?></td>
                <td><?php echo htmlspecialchars($category['created_at']); ?></td>
                <td class="actions">
                    <a href="admin_dashboard.php?tab=categorias&edit_category=<?php echo $category['id']; ?>" class="edit-btn">Editar</a>
                    <a href="admin_dashboard.php?tab=categorias&delete_category=<?php echo $category['id']; ?>" class="delete-btn" onclick="return confirm('¿Seguro que desea eliminar esta categoría?')">Eliminar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No hay categorías registradas.</p>
<?php endif; ?>