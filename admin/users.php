<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Use __DIR__ to get the directory of the current file and adjust the path to conexion.php
require_once __DIR__ . '/../conexion.php';

// Initialize message variables
$success_message = '';
$error_message = '';

// Handle Add User
if (isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']); // Store password as plain text
    $role = $_POST['role'];

    if (empty($name) || empty($username) || empty($email) || empty($password) || empty($role)) {
        $error_message = "Todos los campos son obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Correo electrónico inválido.";
    } else {
        $stmt = $conexion->prepare("SELECT id FROM Users WHERE email = ? OR name = ?");
        if ($stmt === false) {
            $error_message = "Error preparing statement: " . $conexion->error;
        } else {
            $stmt->bind_param("ss", $email, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error_message = "El correo o nombre de usuario ya está registrado.";
            } else {
                // Store the password as plain text (no hashing)
                $stmt = $conexion->prepare("INSERT INTO Users (name, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt === false) {
                    $error_message = "Error preparing insert statement: " . $conexion->error;
                } else {
                    $stmt->bind_param("ssss", $name, $email, $password, $role);
                    if ($stmt->execute()) {
                        $success_message = "Usuario agregado exitosamente.";
                    } else {
                        $error_message = "Error al agregar el usuario: " . $conexion->error;
                    }
                }
            }
            $stmt->close();
        }
    }
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $id = $_POST['user_id'];
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']); // Store password as plain text
    $role = $_POST['role'];

    if (empty($name) || empty($username) || empty($email) || empty($role)) {
        $error_message = "Todos los campos (excepto la contraseña) son obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Correo electrónico inválido.";
    } else {
        $stmt = $conexion->prepare("SELECT id FROM Users WHERE (email = ? OR name = ?) AND id != ?");
        if ($stmt === false) {
            $error_message = "Error preparing statement: " . $conexion->error;
        } else {
            $stmt->bind_param("ssi", $email, $username, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error_message = "El correo o nombre de usuario ya está registrado por otro usuario.";
            } else {
                if (empty($password)) {
                    $stmt = $conexion->prepare("UPDATE Users SET name = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $name, $email, $role, $id);
                } else {
                    // Update with plain text password (no hashing)
                    $stmt = $conexion->prepare("UPDATE Users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $name, $email, $password, $role, $id);
                }
                if ($stmt->execute()) {
                    $success_message = "Usuario actualizado exitosamente.";
                } else {
                    $error_message = "Error al actualizar el usuario: " . $conexion->error;
                }
            }
            $stmt->close();
        }
    }
}

// Handle Delete User
if (isset($_GET['delete_user'])) {
    $id = $_GET['delete_user'];
    $stmt = $conexion->prepare("DELETE FROM Users WHERE id = ?");
    if ($stmt === false) {
        $error_message = "Error preparing delete statement: " . $conexion->error;
    } else {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Usuario eliminado exitosamente.";
        } else {
            $error_message = "Error al eliminar el usuario: " . $conexion->error;
        }
        $stmt->close();
    }
}

// Fetch all users from the Users table
$result_users = $conexion->query("SELECT id, name, email, password, role AS rol FROM Users");
if ($result_users === false) {
    error_log("Database query failed: " . $conexion->error);
    die("Error fetching users: " . $conexion->error);
}
$users = $result_users->fetch_all(MYSQLI_ASSOC);

// Fetch user data for editing
$edit_user = null;
if (isset($_GET['edit_user'])) {
    $id = $_GET['edit_user'];
    $stmt = $conexion->prepare("SELECT id, name, email, role FROM Users WHERE id = ?");
    if ($stmt === false) {
        $error_message = "Error preparing edit statement: " . $conexion->error;
    } else {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $edit_user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>

<h2>Gestión de Usuarios</h2>

<!-- Display Success/Error Messages -->
<?php if ($success_message): ?>
    <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- Add/Edit User Form -->
<form method="POST" class="user-form">
    <input type="hidden" name="user_id" value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['id']) : ''; ?>">
    <input type="text" name="name" placeholder="Nombre" value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['name']) : ''; ?>" required />
    <input type="text" name="username" placeholder="Usuario" value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['name']) : ''; ?>" required />
    <input type="email" name="email" placeholder="Correo" value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['email']) : ''; ?>" required />
    <input type="password" name="password" placeholder="Contraseña" <?php echo !isset($edit_user) ? 'required' : ''; ?> />
    <select name="role" required>
        <option value="administrator" <?php echo isset($edit_user) && $edit_user['role'] == 'administrator' ? 'selected' : ''; ?>>Admin</option>
        <option value="customer" <?php echo isset($edit_user) && $edit_user['role'] == 'customer' ? 'selected' : ''; ?>>Cliente</option>
    </select>
    <button type="submit" name="<?php echo isset($edit_user) ? 'edit_user' : 'add_user'; ?>">
        <?php echo isset($edit_user) ? 'Actualizar' : 'Registrar'; ?>
    </button>
</form>

<!-- Users Table -->
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Usuario</th>
            <th>Correo</th>
            <th>Contraseña</th>
            <th>Rol</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo htmlspecialchars($user['id']); ?></td>
            <td><?php echo htmlspecialchars($user['name']); ?></td>
            <td><?php echo htmlspecialchars($user['name']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><?php echo htmlspecialchars($user['password']); // Display plain text password ?></td>
            <td><?php echo htmlspecialchars($user['rol']); ?></td>
            <td class="actions">
                <a href="admin_dashboard.php?tab=usuarios&edit_user=<?php echo $user['id']; ?>" class="edit-btn">Editar</a>
                <a href="admin_dashboard.php?tab=usuarios&delete_user=<?php echo $user['id']; ?>" class="delete-btn" onclick="return confirm('¿Seguro que desea eliminar este usuario?')">Eliminar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>