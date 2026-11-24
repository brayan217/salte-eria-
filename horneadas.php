
<?php
session_start();
require_once "conexion.php";

// Verificar usuario y rol.
if (!isset($_SESSION["id_usuario"], $_SESSION["id_rol"])) {
    header("Location: login.php");
    exit;
}

if (!in_array((int)$_SESSION["id_rol"], [1, 2, 4])) {
    exit("Acceso denegado.");
}

// Proteger el formulario.
if (!isset($_SESSION["token_horneadas"])) {
    $_SESSION["token_horneadas"] = bin2hex(random_bytes(32));
}

function limpiar($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

$mensaje = "";

// Guardar, editar o eliminar.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["token_horneadas"], $_POST["token"] ?? "")) {
        exit("Formulario no válido.");
    }

    $accion = $_POST["accion"] ?? "";
    $id = filter_var($_POST["id"] ?? 0, FILTER_VALIDATE_INT);
    $cantidad = filter_var($_POST["cantidad"] ?? 0, FILTER_VALIDATE_INT);
    $saltena = filter_var($_POST["saltena"] ?? 0, FILTER_VALIDATE_INT);

    try {
        if ($accion === "registrar" && $saltena > 0 && $cantidad > 0) {

            // Registrar nueva horneada.
            $sql = "INSERT INTO horneadas
                    (id_saltena, id_usuario, cantidad)
                    VALUES (?, ?, ?)";

            $stmt = $conexion->prepare($sql);
            $usuario = (int)$_SESSION["id_usuario"];
            $stmt->bind_param("iii", $saltena, $usuario, $cantidad);
            $stmt->execute();
            $stmt->close();

            $mensaje = "Horneada registrada.";

        } elseif ($accion === "editar" && $id > 0 && $cantidad > 0) {

            // Comprobar el stock antes de reducir la horneada.
            $stmt = $conexion->prepare(
                "SELECT h.id_saltena, h.cantidad, s.stock_disponible
                 FROM horneadas h
                 JOIN v_stock_hoy s ON s.id_saltena = h.id_saltena
                 WHERE h.id_horneada = ?
                   AND DATE(h.fecha_hornea) = CURDATE()"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $actual = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$actual || $cantidad - $actual["cantidad"] +
                $actual["stock_disponible"] < 0) {
                throw new Exception("No puedes reducir la horneada por debajo del stock utilizado.");
            }

            // Actualizar cantidad.
            $stmt = $conexion->prepare(
                "UPDATE horneadas SET cantidad = ?
                 WHERE id_horneada = ?
                   AND DATE(fecha_hornea) = CURDATE()"
            );
            $stmt->bind_param("ii", $cantidad, $id);
            $stmt->execute();
            $stmt->close();

            $mensaje = "Horneada actualizada.";

        } elseif ($accion === "eliminar" && $id > 0) {

            // Consultar antes de eliminar.
            $stmt = $conexion->prepare(
                "SELECT h.cantidad, s.stock_disponible
                 FROM horneadas h
                 JOIN v_stock_hoy s ON s.id_saltena = h.id_saltena
                 WHERE h.id_horneada = ?
                   AND DATE(h.fecha_hornea) = CURDATE()"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $actual = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$actual || $actual["stock_disponible"] < $actual["cantidad"]) {
                throw new Exception("No se puede eliminar: parte de esta cantidad ya fue utilizada.");
            }

            // Eliminar horneada.
            $stmt = $conexion->prepare(
                "DELETE FROM horneadas
                 WHERE id_horneada = ?
                   AND DATE(fecha_hornea) = CURDATE()"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $mensaje = "Horneada eliminada.";

        } else {
            $mensaje = "Datos incorrectos.";
        }

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensaje = "No se pudo realizar la operación.";
    }
}

// Consultar tipos de salteña.
$productos = $conexion->query(
    "SELECT id_saltena, nombre
     FROM tipos_saltena
     WHERE estado = 'activo'
     ORDER BY nombre"
);

// Consultar horneadas del día.
$horneadas = $conexion->query(
    "SELECT h.id_horneada, t.nombre, h.cantidad, h.fecha_hornea
     FROM horneadas h
     JOIN tipos_saltena t ON h.id_saltena = t.id_saltena
     WHERE DATE(h.fecha_hornea) = CURDATE()
     ORDER BY h.id_horneada DESC"
);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Horneadas</title>
    <style>
        body { font-family: Arial; margin: 30px; }
        input, select, button { padding: 8px; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #aaa; padding: 8px; }
    </style>
</head>
<body>

<h2>🥟 Registro de Horneadas</h2>
<a href="index.php">Volver al inicio</a> |
<a href="stock.php">Ver stock</a>

<p><?= limpiar($mensaje) ?></p>

<!-- Registrar horneada -->
<h3>Nueva Horneada</h3>

<form method="POST">
    <input type="hidden" name="token"
           value="<?= limpiar($_SESSION["token_horneadas"]) ?>">
    <input type="hidden" name="accion" value="registrar">

    <label>Salteña:</label>
    <select name="saltena" required>
        <option value="">Seleccionar</option>

        <?php while ($p = $productos->fetch_assoc()): ?>
            <option value="<?= $p["id_saltena"] ?>">
                <?= limpiar($p["nombre"]) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Cantidad:</label>
    <input type="number" name="cantidad" min="1" max="65535" required>

    <button type="submit">Registrar</button>
</form>

<!-- Lista de horneadas -->
<h3>Horneadas de Hoy</h3>

<table>
    <tr>
        <th>ID</th>
        <th>Salteña</th>
        <th>Cantidad</th>
        <th>Fecha</th>
        <th>Acciones</th>
    </tr>

    <?php while ($fila = $horneadas->fetch_assoc()): ?>
        <tr>
            <td><?= $fila["id_horneada"] ?></td>
            <td><?= limpiar($fila["nombre"]) ?></td>
            <td><?= $fila["cantidad"] ?></td>
            <td><?= limpiar($fila["fecha_hornea"]) ?></td>
            <td>
                <!-- Editar cantidad -->
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="token"
                           value="<?= limpiar($_SESSION["token_horneadas"]) ?>">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id"
                           value="<?= $fila["id_horneada"] ?>">

                    <input type="number" name="cantidad"
                           value="<?= $fila["cantidad"] ?>"
                           min="1" max="65535" required>

                    <button type="submit">Editar</button>
                </form>

                <!-- Eliminar -->
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('¿Eliminar horneada?')">
                    <input type="hidden" name="token"
                           value="<?= limpiar($_SESSION["token_horneadas"]) ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id"
                           value="<?= $fila["id_horneada"] ?>">
                    <button type="submit">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
