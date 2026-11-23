<?php
// categorias.php
// Administración de categorías: registrar, listar, editar, desactivar y reactivar.
// (El caso indica que el encargado "administra productos, categorías y movimientos".)
//
// Reglas aplicadas:
//  - El nombre de la categoría no se repite (UNIQUE en la BD + validación en PHP).
//  - Las categorías no se borran, se desactivan.
//  - No se puede desactivar una categoría que tenga productos activos.

require 'includes/auth.php';
require 'conexion.php';

$titulo  = 'Categorías';
$errores = [];

// ¿Estamos editando una categoría? (?id=3)
$idEditar = (int)($_GET['id'] ?? 0);
$form     = ['nombre' => '', 'descripcion' => ''];

if ($idEditar > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categoria WHERE id_categoria = ?');
    $stmt->execute([$idEditar]);
    $cat = $stmt->fetch();
    if (!$cat) {
        redirigir('categorias.php', 'warning', 'La categoría no existe.');
    }
    $form = $cat;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // ---- Cambiar estado (desactivar / reactivar) ----
    if ($accion === 'estado') {
        $id     = (int)($_POST['id'] ?? 0);
        $estado = (int)($_POST['estado'] ?? -1);

        if ($id <= 0 || !in_array($estado, [0, 1], true)) {
            redirigir('categorias.php', 'danger', 'Datos inválidos.');
        }

        if ($estado === 0) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM producto WHERE id_categoria = ? AND estado = 1');
            $stmt->execute([$id]);
            $activos = (int)$stmt->fetchColumn();
            if ($activos > 0) {
                redirigir('categorias.php', 'danger',
                    "No se puede desactivar: la categoría tiene $activos producto(s) activo(s).");
            }
        }

        $stmt = $pdo->prepare('UPDATE categoria SET estado = ? WHERE id_categoria = ?');
        $stmt->execute([$estado, $id]);
        redirigir('categorias.php', 'success',
            $estado === 0 ? 'Categoría desactivada.' : 'Categoría reactivada.');
    }

    // ---- Guardar (registrar o editar) ----
    if ($accion === 'guardar') {
        $form['nombre']      = trim($_POST['nombre'] ?? '');
        $form['descripcion'] = trim($_POST['descripcion'] ?? '');

        if ($form['nombre'] === '' || mb_strlen($form['nombre']) > 60) {
            $errores['nombre'] = 'El nombre es obligatorio (máx. 60 caracteres).';
        }
        if (mb_strlen($form['descripcion']) > 255) {
            $errores['descripcion'] = 'La descripción no puede superar 255 caracteres.';
        }

        // Nombre repetido (sin contar la misma categoría que se edita)
        if (!isset($errores['nombre'])) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categoria WHERE nombre = ? AND id_categoria <> ?');
            $stmt->execute([$form['nombre'], $idEditar]);
            if ($stmt->fetchColumn() > 0) {
                $errores['nombre'] = 'Ya existe una categoría con ese nombre.';
            }
        }

        if (!$errores) {
            try {
                if ($idEditar > 0) {
                    $stmt = $pdo->prepare('UPDATE categoria SET nombre = ?, descripcion = ? WHERE id_categoria = ?');
                    $stmt->execute([$form['nombre'], $form['descripcion'] ?: null, $idEditar]);
                    redirigir('categorias.php', 'success', 'Categoría actualizada correctamente.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO categoria (nombre, descripcion) VALUES (?, ?)');
                    $stmt->execute([$form['nombre'], $form['descripcion'] ?: null]);
                    redirigir('categorias.php', 'success', 'Categoría registrada correctamente.');
                }
            } catch (PDOException $ex) {
                if ($ex->getCode() == 23000) {
                    $errores['nombre'] = 'Ya existe una categoría con ese nombre.';
                } else {
                    error_log($ex->getMessage());
                    $errores['general'] = 'No se pudo guardar la categoría. Intente nuevamente.';
                }
            }
        }
    }
}

// Listado con la cantidad de productos activos de cada categoría
$categorias = $pdo->query(
    'SELECT c.*, COUNT(p.id_producto) AS productos
     FROM categoria c
     LEFT JOIN producto p ON p.id_categoria = c.id_categoria AND p.estado = 1
     GROUP BY c.id_categoria
     ORDER BY c.estado DESC, c.nombre'
)->fetchAll();

function claseError($campo, $errores) {
    return isset($errores[$campo]) ? 'is-invalid' : '';
}

require 'includes/header.php';
?>

<h1 class="h3 mb-3">Categorías</h1>

<div class="row g-4">
    <!-- Formulario -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= $idEditar > 0 ? 'Editar categoría' : 'Nueva categoría' ?></h2>

                <?php if ($errores): ?>
                    <div class="alert alert-danger">
                        <?= isset($errores['general']) ? e($errores['general']) : 'Revise los campos marcados en rojo.' ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="accion" value="guardar">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" maxlength="60" required
                               class="form-control <?= claseError('nombre', $errores) ?>"
                               value="<?= e($form['nombre']) ?>">
                        <div class="invalid-feedback"><?= e($errores['nombre'] ?? 'Ingrese el nombre.') ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="2" maxlength="255"
                                  class="form-control <?= claseError('descripcion', $errores) ?>"><?= e($form['descripcion']) ?></textarea>
                        <div class="invalid-feedback"><?= e($errores['descripcion'] ?? 'Máximo 255 caracteres.') ?></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?= $idEditar > 0 ? 'Guardar cambios' : 'Registrar' ?>
                        </button>
                        <?php if ($idEditar > 0): ?>
                            <a href="categorias.php" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="col-lg-8">
        <div class="table-responsive card shadow-sm">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="text-center">Productos activos</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categorias as $c): ?>
                    <tr class="<?= $c['estado'] == 0 ? 'text-muted' : '' ?>">
                        <td class="fw-semibold"><?= e($c['nombre']) ?></td>
                        <td class="small"><?= e($c['descripcion']) ?></td>
                        <td class="text-center"><?= (int)$c['productos'] ?></td>
                        <td>
                            <?= $c['estado'] == 1
                                ? '<span class="badge bg-success">Activa</span>'
                                : '<span class="badge bg-secondary">Inactiva</span>' ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="categorias.php?id=<?= $c['id_categoria'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form method="post" class="d-inline"
                                  <?= $c['estado'] == 1 ? 'data-confirmar="¿Desactivar la categoría ' . e($c['nombre']) . '?"' : '' ?>>
                                <input type="hidden" name="accion" value="estado">
                                <input type="hidden" name="id" value="<?= $c['id_categoria'] ?>">
                                <input type="hidden" name="estado" value="<?= $c['estado'] == 1 ? 0 : 1 ?>">
                                <?php if ($c['estado'] == 1): ?>
                                    <button class="btn btn-sm btn-outline-danger">Desactivar</button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-success">Reactivar</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
