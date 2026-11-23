<?php
// producto_estado.php
// Desactiva o reactiva un producto (Funcionalidad 6 / Regla 5).
// Nunca se borra físicamente: solo cambia el campo estado.

require 'includes/auth.php';
require 'conexion.php';

// Solo se acepta POST, así un simple link no puede desactivar productos
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('productos.php', 'warning', 'Acción no permitida.');
}

$id     = (int)($_POST['id'] ?? 0);
$estado = (int)($_POST['estado'] ?? -1);

if ($id <= 0 || !in_array($estado, [0, 1], true)) {
    redirigir('productos.php', 'danger', 'Datos inválidos.');
}

$stmt = $pdo->prepare('UPDATE producto SET estado = ? WHERE id_producto = ?');
$stmt->execute([$estado, $id]);

if ($stmt->rowCount() === 0) {
    redirigir('productos.php', 'warning', 'El producto no existe o ya tenía ese estado.');
}

$texto = $estado === 0 ? 'desactivado' : 'reactivado';
redirigir('productos.php', 'success', "Producto $texto correctamente.");
