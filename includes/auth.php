<?php
// includes/auth.php
// Se incluye al inicio de cada página privada.
// Si no hay sesión iniciada, manda al usuario al login.

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

// Muestra texto de forma segura en el HTML (evita XSS)
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

// Guarda un mensaje para mostrarlo en la siguiente página y redirige
function redirigir($url, $tipo, $msg) {
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $msg];
    header("Location: $url");
    exit;
}

// Formato de pesos chilenos: 19990 -> $19.990
function pesos($valor) {
    return '$' . number_format((float)$valor, 0, ',', '.');
}
