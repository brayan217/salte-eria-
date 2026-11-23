<?php
// Conexión a la base de datos MySQL
$servidor = "localhost";
$usuario  = "root";
$psw      = "";
$baseDatos = "saltena_sin_aceituna";

$conexion = mysqli_connect($servidor, $usuario, $psw, $baseDatos);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Permitir tildes y la letra ñ
mysqli_set_charset($conexion, "utf8mb4");
?>
