<?php

// Escapar texto para mostrar en HTML (evita XSS básico)
function h($texto) {
    return htmlspecialchars((string)($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

// Formatear número como dinero (Bs)
function money($numero) {
    return number_format((float)$numero, 2);
}

// Obtener el rol del usuario logueado
function rol_usuario() {
    return $_SESSION['rol'] ?? '';
}

// Verificar si el usuario tiene un rol permitido
function tiene_rol($roles_permitidos) {
    $rol = rol_usuario();
    return in_array($rol, $roles_permitidos, true);
}

// Redirigir a otra página
function redirigir($url) {
    header("Location: " . $url);
    exit;
}

// Mostrar un mensaje flash (se muestra una vez y se borra)
function flash($mensaje, $tipo = 'info') {
    $_SESSION['flash'] = ['m' => $mensaje, 't' => $tipo];
}

function obtener_flash() {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
?>
