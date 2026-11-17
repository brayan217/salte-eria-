
<?php
// Conectar con MySQL.
require_once "conexion.php";

// Verificar que los datos lleguen por POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: clientes.php");
    exit;
}

// Recibir y limpiar espacios de los datos.
$nombre = trim($_POST["nombre"] ?? "");
$apellido_p = trim($_POST["apellido_p"] ?? "");
$apellido_m = trim($_POST["apellido_m"] ?? "");
$celular = trim($_POST["celular"] ?? "");
$direccion = trim($_POST["direccion"] ?? "");

// Validar campos obligatorios y longitudes.
if (
    $nombre === "" ||
    $apellido_p === "" ||
    mb_strlen($nombre) > 60 ||
    mb_strlen($apellido_p) > 40 ||
    mb_strlen($apellido_m) > 40 ||
    mb_strlen($celular) > 15 ||
    mb_strlen($direccion) > 150
) {
    // Regresar si hay datos incorrectos.
    header("Location: clientes.php?error=1");
    exit;
}

try {
    // Consulta SQL para registrar al cliente.
    $sql = "INSERT INTO clientes
            (nombre, apellido_p, apellido_m, celular, direccion)
            VALUES (?, ?, ?, ?, ?)";

    // Preparar la consulta SQL.
    $stmt = $conexion->prepare($sql);

    // Asociar los datos a la consulta.

    $stmt->bind_param(
        "sssss",
        $nombre,
        $apellido_p,
        $apellido_m,
        $celular,
        $direccion
    );

    // Ejecutar y cerrar la consulta.
    $stmt->execute();
    $stmt->close();

    // Regresar con mensaje de éxito.
    header("Location: clientes.php?registrado=1");
    exit;

} catch (mysqli_sql_exception $e) {

    // Registrar el error.
    error_log($e->getMessage());

    // Regresar con mensaje de error.
    header("Location: clientes.php?error=1");
    exit;
}
?>
