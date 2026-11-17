
<?php
// Datos de conexion a MySQL.
$servidor = "127.0.0.1";
$usuario = "root";
$contrasena = "12345";
$base_datos = "saltena_sin_aceituna";
$puerto = 3306;

try {
    // Establecer conexion con MySQL.
    $conexion = new mysqli(
        $servidor,
        $usuario,
        $contrasena,
        $base_datos,
        $puerto
    );

    // Permitir tildes y la letra ñ.
    $conexion->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {

    // Registrar el error en el servidor.
    error_log($e->getMessage());

    // Mostrar mensaje si falla la conexion.
    die("Error al conectar con MySQL.");
}
?>
