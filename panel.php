<?php
// panel.php
// Página de inicio después del login: resumen del inventario y alerta de stock bajo (Funcionalidad 8).

require 'includes/auth.php';
require 'conexion.php';

$titulo = 'Inicio';

$total       = $pdo->query('SELECT COUNT(*) FROM producto WHERE estado = 1')->fetchColumn();
$movHoy      = $pdo->query('SELECT COUNT(*) FROM movimiento WHERE DATE(fecha_hora) = CURDATE()')->fetchColumn();
$productosBajos = $pdo->query(
    'SELECT p.id_producto, p.codigo, p.nombre, p.stock_actual, p.stock_minimo, c.nombre AS categoria
     FROM producto p
     JOIN categoria c ON c.id_categoria = p.id_categoria
     WHERE p.estado = 1 AND p.stock_actual < p.stock_minimo
     ORDER BY p.stock_actual'
)->fetchAll();
$bajos = count($productosBajos);

require 'includes/header.php';
?>

<h1 class="h3 mb-4">Bienvenido/a, <?= e($_SESSION['nombre']) ?></h1>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted">Productos activos</h2>
                <p class="display-6 mb-2"><?= (int)$total ?></p>
                <a href="productos.php" class="btn btn-sm btn-outline-primary">Ver productos</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100 <?= $bajos > 0 ? 'border-danger' : '' ?>">
            <div class="card-body">
                <h2 class="h6 text-muted">Productos con stock bajo</h2>
                <p class="display-6 mb-2 <?= $bajos > 0 ? 'text-danger' : '' ?>"><?= $bajos ?></p>
                <?php if ($bajos > 0): ?>
                    <a href="productos.php?bajo=1" class="btn btn-sm btn-outline-danger">Ver detalle</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted">Movimientos de hoy</h2>
                <p class="display-6 mb-2"><?= (int)$movHoy ?></p>
                <a href="movimientos.php" class="btn btn-sm btn-outline-primary">Registrar movimiento</a>
            </div>
        </div>
    </div>
</div>

<?php if ($bajos > 0): ?>
    <div class="alert alert-danger shadow-sm">
        <h2 class="h5 alert-heading">⚠ Alerta de stock bajo</h2>
        <p class="mb-2">Estos productos tienen menos unidades que su stock mínimo y conviene reponerlos:</p>
        <ul class="mb-0">
            <?php foreach ($productosBajos as $p): ?>
                <li>
                    <strong><?= e($p['codigo']) ?></strong> <?= e($p['nombre']) ?> (<?= e($p['categoria']) ?>):
                    <?= (int)$p['stock_actual'] ?> de <?= (int)$p['stock_minimo'] ?> mínimo
                    — <a href="movimientos.php?producto=<?= $p['id_producto'] ?>&tipo=ENTRADA" class="alert-link">registrar entrada</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php else: ?>
    <div class="alert alert-success shadow-sm">Todo en orden: ningún producto está bajo su stock mínimo.</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
