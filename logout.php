<?php
// Cerrar sesión y volver al login
session_start();
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();
header("Location: login.php");
exit;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0; url=login.php">
    <title>Cerrando sesión...</title>
</head>
<body>
    <p>Cerrando sesión. Redirigiendo...</p>
    <a href="login.php">Ir al login</a>
</body>
</html>
