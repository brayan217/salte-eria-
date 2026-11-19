<?php
// Login - Inicio de sesión
session_start();
require_once 'conexion.php';
require_once 'funciones.php';

$error = '';

if ($_POST) {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    // Buscar usuario en la tabla usuarios de la base de datos
    $consulta = "SELECT u.id_usuario, u.nombre, u.usuario, u.password, r.nombre AS rol
                 FROM usuarios u
                 JOIN roles r ON u.id_rol = r.id_rol
                 WHERE u.usuario = ? AND u.estado = 'activo'";

    $stmt = mysqli_prepare($conexion, $consulta);
    mysqli_stmt_bind_param($stmt, "s", $usuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    if ($fila) {
        $clave_correcta = false;

        // Si la contraseña esta hasheada (password_hash)
        if (password_verify($password, $fila['password'])) {
            $clave_correcta = true;
        }
        // Si la contraseña esta en texto plano (para facilitar el aprendizaje)
        elseif ($password === $fila['password']) {
            $clave_correcta = true;
        }

        if ($clave_correcta) {
            // Iniciar sesion correctamente
            $_SESSION['id_usuario'] = $fila['id_usuario'];
            $_SESSION['nombre']     = $fila['nombre'];
            $_SESSION['usuario']    = $fila['usuario'];
            $_SESSION['rol']        = $fila['rol'];
            redirigir('index.php');
        } else {
            $error = "Usuario o contraseña incorrectos";
        }
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - El Sin Aceituna</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="login-wrap">
    <div class="login-box">
        <div class="logo"></div>
        <h1>EL SIN ACEITUNA</h1>
        <div class="sub">Salteñería · Sistema de Ventas</div>

        <?php if ($error): ?>
            <div class="alert danger"><?= h($error) ?></div>
        <?php endif; ?>

        <?php $fl = obtener_flash(); if ($fl): ?>
            <div class="alert <?= $fl['t'] ?>"><?= h($fl['m']) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label>Usuario</label>
            <input type="text" name="usuario" required autofocus>

            <label>Contraseña</label>
            <input type="password" name="password" required>

            <button type="submit" class="btn p btn-block">Ingresar →</button>
        </form>
    </div>
</div>

</body>
</html>
