
<?php
session_start();
require_once "conexion.php";

// Permitir solamente solicitudes POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cocina_panel.php");
    exit;
}

// Verificar el token de seguridad.
$token = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_cocina"]) ||
    !is_string($token) ||
    !hash_equals($_SESSION["csrf_cocina"], $token)
) {
    http_response_code(403);
    exit("Solicitud no autorizada.");
}

// Recibir los datos del formulario.
$id_saltena = filter_input(
    INPUT_POST,
    "id_saltena",
    FILTER_VALIDATE_INT
);

$cantidad = filter_input(
    INPUT_POST,
    "cantidad",
    FILTER_VALIDATE_INT
);

// Validar los datos.
if (
    !$id_saltena ||
    !in_array($cantidad, [10, 15], true)
) {
    header("Location: cocina_panel.php?error=1");
    exit;
}

try {
    // Verificar que la salteña exista y esté activa.
    $consulta = $conexion->prepare(
        "SELECT id_saltena
         FROM tipos_saltena
         WHERE id_saltena = ?
           AND estado = 'activo'"
    );

    $consulta->bind_param("i", $id_saltena);
    $consulta->execute();
    $consulta->store_result();

    if ($consulta->num_rows === 0) {
        $consulta->close();
        header("Location: cocina_panel.php?error=1");
        exit;
    }

    $consulta->close();

    // Registrar la nueva tanda horneada.
    $sql = "INSERT INTO horneadas
            (id_saltena, cantidad, turno, observacion)
            VALUES (?, ?, ?, ?)";

    $stmt = $conexion->prepare($sql);

    // Seleccionar turno según la hora.
    $hora = (int)date("H");

    $turno = ($hora < 12) ? "mañana" : "tarde";

    $observacion = "Registro rápido desde cocina";

    $stmt->bind_param(
        "iiss",
        $id_saltena,
        $cantidad,
        $turno,
        $observacion
    );

    $stmt->execute();
    $stmt->close();
    $conexion->close();

    // Volver al panel del cocinero.
    header("Location: cocina_panel.php?registrado=1");
    exit;

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());

    header("Location: cocina_panel.php?error=1");
    exit;
}
?>
