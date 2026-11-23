<?php
// includes/header.php
// Parte superior común: Bootstrap + barra de navegación + mensajes.
// Antes de incluirlo, cada página define $titulo.

$pagina = basename($_SERVER['PHP_SELF']); // para marcar el menú activo
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'TecnoStock') ?> | TecnoStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="panel.php">TecnoStock</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $pagina === 'panel.php' ? 'active' : '' ?>" href="panel.php">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($pagina, 'producto') === 0 ? 'active' : '' ?>" href="productos.php">Productos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina === 'movimientos.php' ? 'active' : '' ?>" href="movimientos.php">Movimientos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina === 'categorias.php' ? 'active' : '' ?>" href="categorias.php">Categorías</a>
                </li>
            </ul>
            <span class="navbar-text text-white me-3">
                <?= e($_SESSION['nombre']) ?>
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar sesión</a>
        </div>
    </div>
</nav>

<main class="container pb-5">

<?php
// Mensaje "flash": se guarda en la sesión antes de redirigir y se muestra una sola vez
if (!empty($_SESSION['flash'])):
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
?>
    <div class="alert alert-<?= e($flash['tipo']) ?> alert-dismissible fade show">
        <?= e($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
