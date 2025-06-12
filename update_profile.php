<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';
session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'");

// Check if the user is logged in
if (!isset($_SESSION['customer_logged_in'])) {
    $_SESSION['error_message'] = "Debes iniciar sesión para actualizar tu perfil.";
    header('Location: logeo_del_prototipo.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_SESSION['customer_id'] ?? 0;
    
    if ($customer_id <= 0) {
        $_SESSION['error_message'] = "ID de cliente no válido.";
        header('Location: customer_dashboard.php');
        exit;
    }
    
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validate data
    if (empty($name)) {
        $_SESSION['error_message'] = "El nombre es obligatorio.";
        header('Location: customer_dashboard.php');
        exit;
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Email no válido.";
        header('Location: customer_dashboard.php');
        exit;
    }
    
    // Check if email already exists (for another user)
    $stmt = $conexion->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND role = 'customer'");
    $stmt->bind_param("si", $email, $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error_message'] = "Este email ya está en uso por otro usuario.";
        header('Location: customer_dashboard.php');
        exit;
    }
    $stmt->close();
    
    // Update customer data in users table
    $stmt = $conexion->prepare("UPDATE users SET name = ?, email = ?, address = ?, phone = ? WHERE id = ? AND role = 'customer'");
    $stmt->bind_param("ssssi", $name, $email, $address, $phone, $customer_id);
    
    if ($stmt->execute()) {
        // Update session data
        $_SESSION['customer_email'] = $email;
        $_SESSION['customer_name'] = $name;
        
        $_SESSION['success_message'] = "Perfil actualizado exitosamente.";
    } else {
        $_SESSION['error_message'] = "Error al actualizar el perfil: " . $conexion->error;
    }
    
    $stmt->close();
    
    // Redirect back to dashboard
    header('Location: customer_dashboard.php');
    exit;
} else {
    // If not POST request, redirect to dashboard
    header('Location: customer_dashboard.php');
    exit;
}
?>