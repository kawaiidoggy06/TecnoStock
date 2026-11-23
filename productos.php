<?php
// productos.php
// Listado de productos con búsqueda por nombre, código o categoría
// y alerta visual de stock bajo (Funcionalidades 3, 4 y 8).

require 'includes/auth.php';
require 'conexion.php';

$titulo = 'Productos';

$q          = trim($_GET['q'] ?? '');
$categoria  = (int)($_GET['categoria'] ?? 0);
$verInactivos = isset($_GET['inactivos']);
$soloBajo     = isset($_GET['bajo']);

// Armamos la consulta según los filtros. Los valores van como parámetros (?),
// nunca pegados al SQL, así se evita la inyección SQL.
$sql = 'SELECT p.*, c.nombre AS categoria
        FROM producto p
        JOIN categoria c ON c.id_categoria = p.id_categoria
        WHERE 1 = 1';
$params = [];

if (!$verInactivos) {
    $sql .= ' AND p.estado = 1';
}
if ($q !== '') {
    $sql .= ' AND (p.nombre LIKE ? OR p.codigo LIKE ? OR c.nombre LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($categoria > 0) {
    $sql .= ' AND p.id_categoria = ?';
    $params[] = $categoria;
}
if ($soloBajo) {
    $sql .= ' AND p.estado = 1 AND p.stock_actual < p.stock_minimo';
}
$sql .= ' ORDER BY p.estado DESC, p.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$categorias = $pdo->query('SELECT id_categoria, nombre FROM categoria WHERE estado = 1 ORDER BY nombre')->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Productos</h1>
    <a href="producto_form.php" class="btn btn-success">+ Nuevo producto</a>
</div>

<!-- Buscador -->
<form method="get" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label for="q" class="form-label">Buscar</label>
            <input type="text" id="q" name="q" class="form-control"
                   placeholder="Nombre, código o categoría" value="<?= e($q) ?>">
        </div>
        <div class="col-md-3">
            <label for="categoria" class="form-label">Categoría</label>
            <select id="categoria" name="categoria" class="form-select">
                <option value="0">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= $c['id_categoria'] ?>" <?= $categoria == $c['id_categoria'] ? 'selected' : '' ?>>
                        <?= e($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="bajo" name="bajo"
                       <?= $soloBajo ? 'checked' : '' ?>>
                <label class="form-check-label" for="bajo">Solo stock bajo</label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="inactivos" name="inactivos"
                       <?= $verInactivos ? 'checked' : '' ?>>
                <label class="form-check-label" for="inactivos">Ver inactivos</label>
            </div>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary w-100">Buscar</button>
            <a href="productos.php" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </div>
</form>

<?php if (!$productos): ?>
    <div class="alert alert-info">No se encontraron productos con esos filtros.</div>
<?php else: ?>
<div class="table-responsive card shadow-sm">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th class="text-end">Precio</th>
                <th class="text-center">Stock</th>
                <th class="text-center">Mínimo</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($productos as $p):
            $bajo = $p['estado'] == 1 && $p['stock_actual'] < $p['stock_minimo'];
        ?>
            <tr class="<?= $bajo ? 'table-danger' : '' ?> <?= $p['estado'] == 0 ? 'text-muted' : '' ?>">
                <td><code><?= e($p['codigo']) ?></code></td>
                <td><?= e($p['nombre']) ?></td>
                <td><?= e($p['categoria']) ?></td>
                <td class="text-end"><?= pesos($p['precio']) ?></td>
                <td class="text-center fw-bold">
                    <?= (int)$p['stock_actual'] ?>
                    <?php if ($bajo): ?>
                        <span class="badge bg-danger ms-1" title="Stock bajo el mínimo">⚠ Bajo</span>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= (int)$p['stock_minimo'] ?></td>
                <td>
                    <?= $p['estado'] == 1
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-secondary">Inactivo</span>' ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php if ($p['estado'] == 1): ?>
                        <a href="movimientos.php?producto=<?= $p['id_producto'] ?>" class="btn btn-sm btn-outline-secondary"
                           title="Registrar entrada o salida">± Stock</a>
                        <a href="producto_form.php?id=<?= $p['id_producto'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form method="post" action="producto_estado.php" class="d-inline"
                              data-confirmar="¿Desactivar el producto <?= e($p['nombre']) ?>? Dejará de aparecer en el listado.">
                            <input type="hidden" name="id" value="<?= $p['id_producto'] ?>">
                            <input type="hidden" name="estado" value="0">
                            <button class="btn btn-sm btn-outline-danger">Desactivar</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="producto_estado.php" class="d-inline">
                            <input type="hidden" name="id" value="<?= $p['id_producto'] ?>">
                            <input type="hidden" name="estado" value="1">
                            <button class="btn btn-sm btn-outline-success">Reactivar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="text-muted small mt-2">
    <?= count($productos) ?> producto(s). Las filas en rojo tienen stock bajo el mínimo.
</p>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
