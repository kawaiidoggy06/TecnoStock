<?php
// producto_form.php
// Registrar un producto nuevo (Funcionalidad 2) o editar uno existente (Funcionalidad 5).
// - Sin ?id=   -> modo REGISTRAR
// - Con ?id=5  -> modo EDITAR

require 'includes/auth.php';
require 'conexion.php';

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$titulo = $editar ? 'Editar producto' : 'Nuevo producto';

// Valores iniciales del formulario
$p = [
    'codigo' => '', 'nombre' => '', 'descripcion' => '', 'precio' => '',
    'stock_actual' => '0', 'stock_minimo' => '0', 'id_categoria' => '',
];

// En modo editar, cargamos el producto desde la BD
if ($editar) {
    $stmt = $pdo->prepare('SELECT * FROM producto WHERE id_producto = ? AND estado = 1');
    $stmt->execute([$id]);
    $original = $stmt->fetch();
    if (!$original) {
        redirigir('productos.php', 'warning', 'El producto no existe o está desactivado.');
    }
    $p = $original;
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Leer datos del formulario
    $p['codigo']       = $editar ? $original['codigo'] : strtoupper(trim($_POST['codigo'] ?? ''));
    $p['nombre']       = trim($_POST['nombre'] ?? '');
    $p['descripcion']  = trim($_POST['descripcion'] ?? '');
    $p['precio']       = trim($_POST['precio'] ?? '');
    $p['stock_actual'] = trim($_POST['stock_actual'] ?? '');
    $p['stock_minimo'] = trim($_POST['stock_minimo'] ?? '');
    $p['id_categoria'] = (int)($_POST['id_categoria'] ?? 0);

    // 2) Validaciones en el SERVIDOR (Reglas 1 y 2)
    if (!preg_match('/^[A-Z0-9-]{3,20}$/', $p['codigo'])) {
        $errores['codigo'] = 'Código obligatorio: 3 a 20 caracteres (letras, números o guion).';
    }
    if ($p['nombre'] === '' || mb_strlen($p['nombre']) > 100) {
        $errores['nombre'] = 'El nombre es obligatorio (máx. 100 caracteres).';
    }
    if (mb_strlen($p['descripcion']) > 255) {
        $errores['descripcion'] = 'La descripción no puede superar 255 caracteres.';
    }
    if (!ctype_digit($p['precio'])) {
        $errores['precio'] = 'El precio debe ser un número entero mayor o igual a 0.';
    }
    if (!ctype_digit($p['stock_actual'])) {
        $errores['stock_actual'] = 'El stock actual debe ser un número entero mayor o igual a 0.';
    }
    if (!ctype_digit($p['stock_minimo'])) {
        $errores['stock_minimo'] = 'El stock mínimo debe ser un número entero mayor o igual a 0.';
    }

    // La categoría debe existir y estar activa
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categoria WHERE id_categoria = ? AND estado = 1');
    $stmt->execute([$p['id_categoria']]);
    if ($stmt->fetchColumn() == 0) {
        $errores['id_categoria'] = 'Seleccione una categoría válida.';
    }

    // Regla 1: el código no puede repetirse (solo se revisa al registrar)
    if (!$editar && !isset($errores['codigo'])) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM producto WHERE codigo = ?');
        $stmt->execute([$p['codigo']]);
        if ($stmt->fetchColumn() > 0) {
            $errores['codigo'] = 'Ya existe un producto con ese código.';
        }
    }

    // 3) Si no hay errores, guardamos
    if (!$errores) {
        try {
            // Transacción: el producto y su movimiento se guardan juntos o no se guarda nada
            $pdo->beginTransaction();

            if (!$editar) {
                $stmt = $pdo->prepare(
                    'INSERT INTO producto (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, id_categoria)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $p['codigo'], $p['nombre'], $p['descripcion'] ?: null, $p['precio'],
                    $p['stock_actual'], $p['stock_minimo'], $p['id_categoria'],
                ]);
                $idProducto = $pdo->lastInsertId();

                // Si parte con stock, lo dejamos registrado como entrada inicial
                $diferencia = (int)$p['stock_actual'];
                $obs = 'Stock inicial al registrar el producto';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE producto
                     SET nombre = ?, descripcion = ?, precio = ?, stock_actual = ?, stock_minimo = ?, id_categoria = ?
                     WHERE id_producto = ?'
                );
                $stmt->execute([
                    $p['nombre'], $p['descripcion'] ?: null, $p['precio'],
                    $p['stock_actual'], $p['stock_minimo'], $p['id_categoria'], $id,
                ]);
                $idProducto = $id;

                // Si se cambió la cantidad a mano, queda registrado como ajuste
                $diferencia = (int)$p['stock_actual'] - (int)$original['stock_actual'];
                $obs = 'Ajuste manual de stock desde edición del producto';
            }

            // Así el historial de movimientos siempre cuadra con el stock (Regla 3)
            if ($diferencia !== 0) {
                $stmt = $pdo->prepare(
                    'INSERT INTO movimiento (tipo, cantidad, observacion, id_producto, id_usuario)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $diferencia > 0 ? 'ENTRADA' : 'SALIDA',
                    abs($diferencia), $obs, $idProducto, $_SESSION['id_usuario'],
                ]);
            }

            $pdo->commit();
            $msg = $editar ? 'Producto actualizado correctamente.' : 'Producto registrado correctamente.';
            redirigir('productos.php', 'success', $msg);

        } catch (PDOException $ex) {
            $pdo->rollBack();
            // 23000 = violación de restricción (ej.: código duplicado registrado al mismo tiempo)
            if ($ex->getCode() == 23000) {
                $errores['codigo'] = 'Ya existe un producto con ese código.';
            } else {
                error_log($ex->getMessage());
                $errores['general'] = 'No se pudo guardar el producto. Intente nuevamente.';
            }
        }
    }
}

$categorias = $pdo->query('SELECT id_categoria, nombre FROM categoria WHERE estado = 1 ORDER BY nombre')->fetchAll();

// Ayuda para pintar en rojo los campos con error
function claseError($campo, $errores) {
    return isset($errores[$campo]) ? 'is-invalid' : '';
}

require 'includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="productos.php">Productos</a></li>
        <li class="breadcrumb-item active"><?= e($titulo) ?></li>
    </ol>
</nav>

<div class="card shadow-sm">
    <div class="card-body">
        <h1 class="h4 mb-3"><?= e($titulo) ?></h1>

        <?php if ($errores): ?>
            <div class="alert alert-danger">
                <?= isset($errores['general']) ? e($errores['general']) : 'Revise los campos marcados en rojo.' ?>
            </div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="codigo" class="form-label">Código *</label>
                    <input type="text" id="codigo" name="codigo" maxlength="20"
                           class="form-control text-uppercase <?= claseError('codigo', $errores) ?>"
                           value="<?= e($p['codigo']) ?>" pattern="[A-Za-z0-9\-]{3,20}"
                           <?= $editar ? 'readonly' : 'required' ?>>
                    <div class="invalid-feedback">
                        <?= e($errores['codigo'] ?? 'De 3 a 20 caracteres: letras, números o guion.') ?>
                    </div>
                    <?php if ($editar): ?>
                        <div class="form-text">El código no se modifica para conservar el historial.</div>
                    <?php endif; ?>
                </div>

                <div class="col-md-8">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" required
                           class="form-control <?= claseError('nombre', $errores) ?>"
                           value="<?= e($p['nombre']) ?>">
                    <div class="invalid-feedback"><?= e($errores['nombre'] ?? 'Ingrese el nombre.') ?></div>
                </div>

                <div class="col-md-6">
                    <label for="id_categoria" class="form-label">Categoría *</label>
                    <select id="id_categoria" name="id_categoria" required
                            class="form-select <?= claseError('id_categoria', $errores) ?>">
                        <option value="">Seleccione...</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id_categoria'] ?>" <?= $p['id_categoria'] == $c['id_categoria'] ? 'selected' : '' ?>>
                                <?= e($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e($errores['id_categoria'] ?? 'Seleccione una categoría.') ?></div>
                </div>

                <div class="col-md-6">
                    <label for="precio" class="form-label">Precio (CLP) *</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text">$</span>
                        <input type="number" id="precio" name="precio" min="0" step="1" required
                               class="form-control <?= claseError('precio', $errores) ?>"
                               value="<?= e($p['precio']) ?>">
                        <div class="invalid-feedback"><?= e($errores['precio'] ?? 'Ingrese un precio entero mayor o igual a 0.') ?></div>
                    </div>
                </div>

                <div class="col-12">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="2" maxlength="255"
                              class="form-control <?= claseError('descripcion', $errores) ?>"><?= e($p['descripcion']) ?></textarea>
                    <div class="invalid-feedback"><?= e($errores['descripcion'] ?? 'Máximo 255 caracteres.') ?></div>
                </div>

                <div class="col-md-6">
                    <label for="stock_actual" class="form-label">Stock actual *</label>
                    <input type="number" id="stock_actual" name="stock_actual" min="0" step="1" required
                           class="form-control <?= claseError('stock_actual', $errores) ?>"
                           value="<?= e($p['stock_actual']) ?>">
                    <div class="invalid-feedback"><?= e($errores['stock_actual'] ?? 'Debe ser un entero mayor o igual a 0.') ?></div>
                    <?php if ($editar): ?>
                        <div class="form-text">Si cambias esta cantidad, se registra un movimiento de ajuste.</div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="stock_minimo" class="form-label">Stock mínimo *</label>
                    <input type="number" id="stock_minimo" name="stock_minimo" min="0" step="1" required
                           class="form-control <?= claseError('stock_minimo', $errores) ?>"
                           value="<?= e($p['stock_minimo']) ?>">
                    <div class="invalid-feedback"><?= e($errores['stock_minimo'] ?? 'Debe ser un entero mayor o igual a 0.') ?></div>
                    <div class="form-text">Bajo esta cantidad el producto se marca con alerta.</div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $editar ? 'Guardar cambios' : 'Registrar producto' ?></button>
                <a href="productos.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
