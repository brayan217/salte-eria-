
<?php
// Iniciar la sesión.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vaciar los datos de la sesión.
$_SESSION = [];

// Eliminar la cookie de sesión.
if (ini_get("session.use_cookies")) {

    $datos = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 3600,
        $datos["path"],
        $datos["domain"],
        $datos["secure"],
        $datos["httponly"]
    );
}

// Destruir la sesión.
session_destroy();

// Regresar al login.
header("Location: login.php");
exit;
?>
