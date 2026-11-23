<?php
// movimientos.php
// Registrar entradas y salidas de existencias (Funcionalidad 7)
// y ver el historial de movimientos.
//
// Reglas que se cumplen aquí:
//  - Regla 2: la cantidad debe ser mayor que 0.
//  - Regla 3: se guarda fecha, tipo, cantidad, producto y usuario responsable.
//  - Regla 4: una salida no puede dejar el stock bajo cero.
//  - Regla 6: el stock se actualiza automáticamente.

require 'includes/auth.php';
require 'conexion.php';

$titulo  = 'Movimientos';
$errores = [];

// Valores del formulario (si viene ?producto=3 desde el listado, se deja seleccionado)
$form = [
    'id_producto' => (int)($_GET['producto'] ?? 0),
    'tipo'        => $_GET['tipo'] ?? 'ENTRADA',
    'cantidad'    => '',
    'observacion' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['id_producto'] = (int)($_POST['id_producto'] ?? 0);
    $form['tipo']        = $_POST['tipo'] ?? '';
    $form['cantidad']    = trim($_POST['cantidad'] ?? '');
    $form['observacion'] = trim($_POST['observacion'] ?? '');

    // 1) Validaciones en el SERVIDOR
    if ($form['id_producto'] <= 0) {
        $errores['id_producto'] = 'Seleccione un producto.';
    }
    if (!in_array($form['tipo'], ['ENTRADA', 'SALIDA'], true)) {
        $errores['tipo'] = 'Seleccione si es entrada o salida.';
    }
    if (!ctype_digit($form['cantidad']) || (int)$form['cantidad'] <= 0) {
        $errores['cantidad'] = 'La cantidad debe ser un número entero mayor que 0.';
    }
    if (mb_strlen($form['observacion']) > 255) {
        $errores['observacion'] = 'La observación no puede superar 255 caracteres.';
    }

    // 2) Guardar dentro de una transacción
    if (!$errores) {
        $cantidad = (int)$form['cantidad'];
        try {
            $pdo->beginTransaction();

            // FOR UPDATE "bloquea" la fila del producto mientras dura la transacción,
            // así dos salidas al mismo tiempo no pueden dejar el stock negativo.
            $stmt = $pdo->prepare(
                'SELECT nombre, stock_actual, stock_minimo FROM producto
                 WHERE id_producto = ? AND estado = 1 FOR UPDATE'
            );
            $stmt->execute([$form['id_producto']]);
            $producto = $stmt->fetch();

            if (!$producto) {
                $errores['id_producto'] = 'El producto no existe o está desactivado.';
            } elseif ($form['tipo'] === 'SALIDA' && $cantidad > $producto['stock_actual']) {
                // Regla 4
                $errores['cantidad'] = "Stock insuficiente: solo hay {$producto['stock_actual']} unidad(es) disponibles.";
            }

            if ($errores) {
                $pdo->rollBack();
            } else {
                // Regla 6: actualizar el stock automáticamente
                $nuevoStock = $form['tipo'] === 'ENTRADA'
                    ? $producto['stock_actual'] + $cantidad
                    : $producto['stock_actual'] - $cantidad;

                $stmt = $pdo->prepare('UPDATE producto SET stock_actual = ? WHERE id_producto = ?');
                $stmt->execute([$nuevoStock, $form['id_producto']]);

                // Regla 3: registrar el movimiento con el usuario que lo hizo
                $stmt = $pdo->prepare(
                    'INSERT INTO movimiento (tipo, cantidad, observacion, id_producto, id_usuario)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $form['tipo'], $cantidad, $form['observacion'] ?: null,
                    $form['id_producto'], $_SESSION['id_usuario'],
                ]);

                $pdo->commit();

                $msg = ($form['tipo'] === 'ENTRADA' ? 'Entrada' : 'Salida')
                     . " registrada. Stock de {$producto['nombre']}: $nuevoStock.";
                $tipoMsg = 'success';
                if ($nuevoStock < $producto['stock_minimo']) {
                    $msg .= ' ⚠ Quedó bajo el stock mínimo (' . $producto['stock_minimo'] . ').';
                    $tipoMsg = 'warning';
                }
                redirigir('movimientos.php', $tipoMsg, $msg);
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($ex->getMessage());
            $errores['general'] = 'No se pudo registrar el movimiento. Intente nuevamente.';
        }
    }
}

// Productos activos para el selector (con su stock, para mostrarlo)
$productos = $pdo->query(
    'SELECT id_producto, codigo, nombre, stock_actual FROM producto WHERE estado = 1 ORDER BY nombre'
)->fetchAll();

// Historial (filtros opcionales por producto y tipo)
$filtroProducto = (int)($_GET['f_producto'] ?? 0);
$filtroTipo     = $_GET['f_tipo'] ?? '';

$sql = 'SELECT m.*, p.codigo, p.nombre AS producto, u.nombre AS usuario
        FROM movimiento m
        JOIN producto p ON p.id_producto = m.id_producto
        JOIN usuario  u ON u.id_usuario  = m.id_usuario
        WHERE 1 = 1';
$params = [];
if ($filtroProducto > 0) {
    $sql .= ' AND m.id_producto = ?';
    $params[] = $filtroProducto;
}
if (in_array($filtroTipo, ['ENTRADA', 'SALIDA'], true)) {
    $sql .= ' AND m.tipo = ?';
    $params[] = $filtroTipo;
}
$sql .= ' ORDER BY m.fecha_hora DESC, m.id_movimiento DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historial = $stmt->fetchAll();

function claseError($campo, $errores) {
    return isset($errores[$campo]) ? 'is-invalid' : '';
}

require 'includes/header.php';
?>

<h1 class="h3 mb-3">Entradas y salidas</h1>

<div class="row g-4">
    <!-- Formulario de registro -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Registrar movimiento</h2>

                <?php if ($errores): ?>
                    <div class="alert alert-danger">
                        <?= isset($errores['general']) ? e($errores['general']) : 'Revise los campos marcados en rojo.' ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="movimientos.php" class="needs-validation" novalidate id="formMovimiento">
                    <div class="mb-3">
                        <label class="form-label d-block">Tipo *</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="tipo" id="tipoEntrada" value="ENTRADA"
                                   <?= $form['tipo'] !== 'SALIDA' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-success" for="tipoEntrada">↓ Entrada</label>
                            <input type="radio" class="btn-check" name="tipo" id="tipoSalida" value="SALIDA"
                                   <?= $form['tipo'] === 'SALIDA' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-danger" for="tipoSalida">↑ Salida</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="id_producto" class="form-label">Producto *</label>
                        <select id="id_producto" name="id_producto" required
                                class="form-select <?= claseError('id_producto', $errores) ?>">
                            <option value="">Seleccione...</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= $p['id_producto'] ?>" data-stock="<?= (int)$p['stock_actual'] ?>"
                                    <?= $form['id_producto'] == $p['id_producto'] ? 'selected' : '' ?>>
                                    <?= e($p['codigo'] . ' - ' . $p['nombre']) ?> (stock: <?= (int)$p['stock_actual'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= e($errores['id_producto'] ?? 'Seleccione un producto.') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="cantidad" class="form-label">Cantidad *</label>
                        <input type="number" id="cantidad" name="cantidad" min="1" step="1" required
                               class="form-control <?= claseError('cantidad', $errores) ?>"
                               value="<?= e($form['cantidad']) ?>">
                        <div class="invalid-feedback" id="cantidadError">
                            <?= e($errores['cantidad'] ?? 'Ingrese un entero mayor que 0.') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="observacion" class="form-label">Observación</label>
                        <input type="text" id="observacion" name="observacion" maxlength="255"
                               class="form-control <?= claseError('observacion', $errores) ?>"
                               placeholder="Ej.: compra a proveedor, venta, devolución"
                               value="<?= e($form['observacion']) ?>">
                        <div class="invalid-feedback"><?= e($errores['observacion'] ?? 'Máximo 255 caracteres.') ?></div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Registrar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Historial -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Historial</h2>

                <form method="get" class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <select name="f_producto" class="form-select form-select-sm">
                            <option value="0">Todos los productos</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= $p['id_producto'] ?>" <?= $filtroProducto == $p['id_producto'] ? 'selected' : '' ?>>
                                    <?= e($p['codigo'] . ' - ' . $p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <select name="f_tipo" class="form-select form-select-sm">
                            <option value="">Entradas y salidas</option>
                            <option value="ENTRADA" <?= $filtroTipo === 'ENTRADA' ? 'selected' : '' ?>>Solo entradas</option>
                            <option value="SALIDA"  <?= $filtroTipo === 'SALIDA'  ? 'selected' : '' ?>>Solo salidas</option>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <button class="btn btn-sm btn-outline-primary w-100">Filtrar</button>
                    </div>
                </form>

                <?php if (!$historial): ?>
                    <div class="alert alert-info mb-0">No hay movimientos para mostrar.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Tipo</th>
                                <th>Producto</th>
                                <th class="text-end">Cantidad</th>
                                <th>Usuario</th>
                                <th>Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($historial as $m): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d-m-Y H:i', strtotime($m['fecha_hora'])) ?></td>
                                <td>
                                    <?= $m['tipo'] === 'ENTRADA'
                                        ? '<span class="badge bg-success">Entrada</span>'
                                        : '<span class="badge bg-danger">Salida</span>' ?>
                                </td>
                                <td><code><?= e($m['codigo']) ?></code> <?= e($m['producto']) ?></td>
                                <td class="text-end fw-bold"><?= $m['tipo'] === 'ENTRADA' ? '+' : '−' ?><?= (int)$m['cantidad'] ?></td>
                                <td><?= e($m['usuario']) ?></td>
                                <td class="small text-muted"><?= e($m['observacion']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Validación extra en el CLIENTE: una salida no puede superar el stock disponible.
// (PHP vuelve a revisarlo con el valor real de la base de datos.)
(function () {
    const form     = document.getElementById('formMovimiento');
    const producto = document.getElementById('id_producto');
    const cantidad = document.getElementById('cantidad');
    const msgError = document.getElementById('cantidadError');

    function revisarStock() {
        cantidad.setCustomValidity('');
        const opcion = producto.selectedOptions[0];
        const esSalida = document.getElementById('tipoSalida').checked;
        if (!opcion || !opcion.dataset.stock || !esSalida) return;

        const stock = parseInt(opcion.dataset.stock, 10);
        if (parseInt(cantidad.value, 10) > stock) {
            cantidad.setCustomValidity('stock');
            msgError.textContent = 'Stock insuficiente: solo hay ' + stock + ' unidad(es).';
        } else {
            msgError.textContent = 'Ingrese un entero mayor que 0.';
        }
    }

    [producto, cantidad, document.getElementById('tipoEntrada'), document.getElementById('tipoSalida')]
        .forEach(el => el.addEventListener('input', revisarStock));
    form.addEventListener('submit', revisarStock, true); // se ejecuta antes que la validación general
})();
</script>

<?php require 'includes/footer.php'; ?>
