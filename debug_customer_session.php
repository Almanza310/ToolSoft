<?php
// Script para diagnosticar el problema de datos del cliente
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';
session_start();

echo "<h2>🔍 Diagnóstico de Sesión de Cliente</h2>";

// 1. Verificar datos de sesión
echo "<h3>1. Datos actuales de sesión:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// 2. Verificar si el usuario está logueado
echo "<h3>2. Estado de login:</h3>";
if (isset($_SESSION['customer_logged_in'])) {
    echo "✅ Usuario logueado: SÍ<br>";
    echo "Customer ID: " . ($_SESSION['customer_id'] ?? 'NO DEFINIDO') . "<br>";
    echo "Customer Email: " . ($_SESSION['customer_email'] ?? 'NO DEFINIDO') . "<br>";
} else {
    echo "❌ Usuario logueado: NO<br>";
}

// 3. Verificar estructura de tabla customers
echo "<h3>3. Estructura de tabla customers:</h3>";
$result = $conexion->query("DESCRIBE customers");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Error al obtener estructura: " . $conexion->error;
}

// 4. Verificar todos los registros de customers
echo "<h3>4. Todos los registros de customers:</h3>";
$result = $conexion->query("SELECT * FROM customers ORDER BY id DESC LIMIT 10");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Address</th><th>Phone</th><th>Password</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['email'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['address'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['phone'] ?? 'NULL') . "</td>";
        echo "<td>" . (empty($row['password']) ? 'VACÍO' : 'TIENE PASSWORD') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Error al obtener registros: " . $conexion->error;
}

// 5. Si hay customer_id en sesión, verificar ese registro específico
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];
    echo "<h3>5. Datos del customer_id actual ($customer_id):</h3>";
    
    $stmt = $conexion->prepare("SELECT * FROM customers WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $customer = $result->fetch_assoc();
            echo "<table border='1' style='border-collapse: collapse;'>";
            foreach ($customer as $key => $value) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>❌ No se encontró customer con ID: $customer_id</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>❌ Error preparando consulta: " . $conexion->error . "</p>";
    }
}

// 6. Verificar último login
echo "<h3>6. Verificar proceso de login:</h3>";
echo "<p>Para verificar el login, necesitamos revisar el archivo de login. ¿Cuál es el nombre de tu archivo de login?</p>";
echo "<p>Archivos comunes: logeo_del_prototipo.php, login.php, customer_login.php</p>";

$conexion->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; }
h2 { color: #333; }
h3 { color: #666; margin-top: 30px; }
</style>