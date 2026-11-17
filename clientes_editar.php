
<?php
require_once "conexion.php";

// Obtener ID y buscar cliente.
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id) die("ID inválido");

$stmt = $conexion->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cliente) die("Cliente no encontrado");

// Actualizar cliente.
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $paterno = trim($_POST["apellido_p"] ?? "");
    $materno = trim($_POST["apellido_m"] ?? "");
    $celular = trim($_POST["celular"] ?? "");
    $direccion = trim($_POST["direccion"] ?? "");

    if ($nombre === "" || $paterno === "" ||
        mb_strlen($nombre) > 60 || mb_strlen($paterno) > 40 ||
        mb_strlen($materno) > 40 || mb_strlen($celular) > 15 ||
        mb_strlen($direccion) > 150) {
        $error = "Verifica los datos ingresados.";
    } else {
        try {
            $stmt = $conexion->prepare(
                "UPDATE clientes SET nombre=?, apellido_p=?,
                 apellido_m=?, celular=?, direccion=?
                 WHERE id_cliente=?"
            );

            $stmt->bind_param(
                "sssssi",
                $nombre, $paterno, $materno,
                $celular, $direccion, $id
            );

            $stmt->execute();
            $stmt->close();

            header("Location: clientes.php?actualizado=1");
            exit;

        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = "No se pudo actualizar el cliente.";
        }
    }

    $cliente = array_merge($cliente, [
        "nombre" => $nombre,
        "apellido_p" => $paterno,
        "apellido_m" => $materno,
        "celular" => $celular,
        "direccion" => $direccion
    ]);
}

function limpiar($valor) {
    return htmlspecialchars((string)($valor ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-5">

    <h2>Editar Cliente</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= limpiar($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-4">

        <?php
        $campos = [
            "nombre" => ["Nombre", 60],
            "apellido_p" => ["Apellido Paterno", 40],
            "apellido_m" => ["Apellido Materno", 40],
            "celular" => ["Celular", 15],
            "direccion" => ["Dirección", 150]
        ];

        foreach ($campos as $campo => [$texto, $limite]):
        ?>
            <div class="mb-3">
                <label class="form-label"><?= $texto ?></label>

                <input type="text" name="<?= $campo ?>"
                       maxlength="<?= $limite ?>"
                       value="<?= limpiar($cliente[$campo]) ?>"
                       class="form-control"
                       <?= in_array($campo, ["nombre", "apellido_p"]) ? "required" : "" ?>>
            </div>
        <?php endforeach; ?>

        <div>
            <button class="btn btn-success">Guardar Cambios</button>
            <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
        </div>

    </form>

</div>
</body>
</html>
