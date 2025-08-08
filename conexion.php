<?php
$host = 'localhost';
$usuario = 'root';
$contrasena = '';
$baseDatos = 'gestion_grupos';

$conexion = new mysqli($host, $usuario, $contrasena, $baseDatos);

if ($conexion->connect_error) {
    die("❌ Error de conexión: " . $conexion->connect_error);
} else {
    echo "✅ Conexión exitosa a la base de datos.";
}
?>


