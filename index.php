<?php
// index.php
// Pantalla de inicio de sesión (Funcionalidad mínima 1).

session_start();
require 'conexion.php';

// Si ya inició sesión, no tiene sentido mostrar el login otra vez
if (isset($_SESSION['id_usuario'])) {
    header('Location: panel.php');
    exit;
}

$error  = '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $clave  = $_POST['clave'] ?? '';

    // Validación en el SERVIDOR (se repite aunque JavaScript ya validó)
    if ($correo === '' || $clave === '') {
        $error = 'Debe ingresar correo y contraseña.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo no es válido.';
    } else {
        // Consulta preparada: el correo viaja separado del SQL (evita inyección SQL)
        $sql  = 'SELECT id_usuario, nombre, clave FROM usuario WHERE correo = ? AND estado = 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        // password_verify compara la clave escrita con el hash guardado en la BD
        if ($usuario && password_verify($clave, $usuario['clave'])) {
            session_regenerate_id(true); // nueva id de sesión por seguridad
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre']     = $usuario['nombre'];
            header('Location: panel.php');
            exit;
        }
        // Mensaje genérico: no revelamos si falló el correo o la clave
        $error = 'Correo o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | TecnoStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/estilo.css" rel="stylesheet"> <!-- estilos propios -->
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-11 col-sm-8 col-md-5 col-lg-4">
            <div class="login-caja mt-5">
                <div>
                    <h1 class="login-titulo">TecnoStock v1.0</h1>
                    <p class="text-center mb-3">Sistema de Inventario - Iniciar sesión</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form id="formLogin" method="post" novalidate>
                        <div class="mb-3">
                            <label for="correo" class="form-label">Correo</label>
                            <input type="email" class="form-control" id="correo" name="correo"
                                   value="<?= htmlspecialchars($correo) ?>" required>
                            <div class="invalid-feedback">Ingrese un correo válido.</div>
                        </div>
                        <div class="mb-3">
                            <label for="clave" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="clave" name="clave" required>
                            <div class="invalid-feedback">Ingrese su contraseña.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                    </form>
                </div>
            </div>
            <p class="pie">TecnoStock v1.0 - Sistema de Inventario</p>
        </div>
    </div>
</div>

<script>
// Validación en el CLIENTE: avisa al instante sin recargar la página
document.getElementById('formLogin').addEventListener('submit', function (ev) {
    if (!this.checkValidity()) {
        ev.preventDefault();          // no se envía el formulario
    }
    this.classList.add('was-validated'); // Bootstrap pinta en rojo los campos malos
});
</script>
</body>
</html>
