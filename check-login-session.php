<?php
// Script para verificar y corregir el problema de sesión de login

session_start();
require_once 'conexion.php';

echo "<h2>Diagnóstico de Sesión de Login</h2>";

// Mostrar datos actuales de sesión
echo "<h3>Datos actuales de sesión:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Verificar tabla de customers
echo "<h3>Verificar tabla customers:</h3>";
$result = $conexion->query("SELECT id, name, email, address, phone FROM customers LIMIT 5");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Address</th><th>Phone</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['email'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['address'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['phone'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conexion->error;
}

// Si hay customer_id en sesión, verificar ese registro específico
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];
    echo "<h3>Datos del customer_id actual ($customer_id):</h3>";
    
    $stmt = $conexion->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        echo "<pre>";
        print_r($customer);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>No se encontró customer con ID: $customer_id</p>";
    }
    $stmt->close();
}

$conexion->close();
?>