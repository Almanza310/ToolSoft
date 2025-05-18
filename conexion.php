<?php
// conexion.php
$host = 'localhost';
$dbname = 'prototipo';
$username = 'root';
$password = '';

$conexion = new mysqli($host, $username, $password, $dbname);

if ($conexion->connect_error) {
    die("Connection failed: " . $conexion->connect_error);
}
$conexion->set_charset("utf8");
?>