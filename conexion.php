<?php
// conexion.php
// Crea UNA conexión PDO a MySQL que usan todas las páginas del sistema.

$host     = 'localhost';
$base     = 'tecnostock';
$usuario  = 'root';   // usuario por defecto de XAMPP
$clave    = '';       // XAMPP viene sin contraseña para root

$dsn = "mysql:host=$host;dbname=$base;charset=utf8mb4";

$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // los errores lanzan excepciones
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // resultados como arreglo asociativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // consultas preparadas reales
];

try {
    $pdo = new PDO($dsn, $usuario, $clave, $opciones);
} catch (PDOException $e) {
    // No mostramos el detalle técnico al usuario final, solo un mensaje claro
    error_log('Error de conexión: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo conectar a la base de datos. Verifique que MySQL esté encendido en XAMPP.');
}
