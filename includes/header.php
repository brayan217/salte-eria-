<?php
// Cabecera compartida (no se usa directamente, index.php ya incluye la barra)
session_start();
require_once 'conexion.php';
require_once 'funciones.php';

if (!isset($_SESSION['id_usuario'])) {
    redirigir('login.php');
}
?>
