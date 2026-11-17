
<?php
// Iniciar la sesión.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Evitar que las páginas protegidas se guarden en caché.
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// Comprobar si el usuario inició sesión.
if (
    !isset($_SESSION["id_usuario"]) ||
    !isset($_SESSION["id_rol"])
) {
    header("Location: login.php");
    exit;
}

// Función para verificar los roles permitidos.
function verificarRol($rolesPermitidos) {

    // Obtener el rol del usuario.
    $rol = (int) $_SESSION["id_rol"];

    // Verificar si tiene permiso.
    if (!in_array($rol, $rolesPermitidos, true)) {
        http_response_code(403);
        die("Acceso denegado. No tienes permiso.");
    }
}
?>
