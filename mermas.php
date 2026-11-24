
<?php
session_start();
require_once "conexion.php";

// Verificar sesión y permisos.
if (!isset($_SESSION["id_usuario"], $_SESSION["id_rol"])) {
    header("Location: login.php");
    exit;
}

if (!in_array((int)$_SESSION["id_rol"], [1, 2, 4])) {
    exit("Acceso denegado.");
}

if (!isset($_SESSION["token_mermas"])) {
    $_SESSION["token_mermas"] = bin2hex(random_bytes(32));
}

function limpiar($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

$mensaje = "";

// Procesar formulario.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["token_mermas"], $_POST["token"] ?? "")) {
        exit("Formulario no válido.");
    }

    $accion = $_POST["accion"] ?? "";
    $id = filter_var($_POST["id"] ?? 0, FILTER_VALIDATE_INT);
    $saltena = filter_var($_POST["saltena"] ?? 0, FILTER_VALIDATE_INT);
    $cantidad = filter_var($_POST["cantidad"] ?? 0, FILTER_VALIDATE_INT);
    $motivo = $_POST["motivo"] ?? "";

    try {
        if ($accion === "registrar" && $saltena > 0 &&
            $cantidad > 0 &&
            in_array($motivo, ["quemada", "caida", "cortesia", "otro"])) {

            // Verificar stock.
            $stmt = $conexion->prepare(
                "SELECT stock_disponible FROM v_stock_hoy
                 WHERE id_saltena = ?"
            );
            $stmt->bind_param("i", $saltena);
            $stmt->execute();
            $stock = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$stock || $cantidad > $stock["stock_disponible"]) {
                throw new Exception("Stock insuficiente.");
            }

            // Registrar merma.
            $stmt = $conexion->prepare(
                "INSERT INTO mermas
                 (id_saltena, id_usuario, cantidad, motivo)
                 VALUES (?, ?, ?, ?)"
            );

            $usuario = (int)$_SESSION["id_usuario"];
            $stmt->bind_param("iiis", $saltena, $usuario, $cantidad, $motivo);
            $stmt->execute();
            $stmt->close();

            $mensaje = "Merma registrada.";

        } elseif ($accion === "editar" && $id > 0 &&
                  $cantidad > 0 && $cantidad <= 65535) {

            // Consultar merma y stock.
            $stmt = $conexion->prepare(
                "SELECT m.cantidad, s.stock_disponible
                 FROM mermas m
                 JOIN v_stock_hoy s ON s.id_saltena = m.id_saltena
                 WHERE m.id_merma = ?
                   AND DATE(m.fecha) = CURDATE()"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $actual = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$actual ||
                $cantidad - $actual["cantidad"] > $actual["stock_disponible"]) {
                throw new Exception("Stock insuficiente.");
            }

            // Actualizar cantidad.
            $stmt = $conexion->prepare(
                "UPDATE mermas SET cantidad = ?
                 WHERE id_merma = ?
                   AND DATE(fecha) = CURDATE()"
            );
            $stmt->bind_param("ii", $cantidad, $id);
            $stmt->execute();
            $stmt->close();

            $mensaje = "Merma actualizada.";

        } elseif ($accion === "eliminar" && $id > 0) {

            // Eliminar merma del día.
            $stmt = $conexion->prepare(
                "DELETE FROM mermas
                 WHERE id_merma = ?
                   AND DATE(fecha) = CURDATE()"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $mensaje = "Merma eliminada.";

        } else {
            $mensaje = "Datos incorrectos.";
        }

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensaje = "No se pudo realizar la operación.";
    }
}

// Consultar salteñas y stock.
$productos = $conexion->query(
    "SELECT t.id_saltena, t.nombre, s.stock_disponible
     FROM tipos_saltena t
     JOIN v_stock_hoy s ON t.id_saltena = s.id_saltena
     WHERE t.estado = 'activo'
     ORDER BY t.nombre"
);

// Consultar mermas del día.
$mermas = $conexion->query(
    "SELECT m.id_merma, t.nombre, m.cantidad, m.motivo, m.fecha
     FROM mermas m
     JOIN tipos_saltena t ON m.id_saltena = t.id_saltena
     WHERE DATE(m.fecha) = CURDATE()
     ORDER BY m.id_merma DESC"  
);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Mermas</title>
    <style>
        body { font-family: Arial; margin: 30px; }
        input, select, button { padding: 8px; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #aaa; padding: 8px; }
    </style>
</head>
<body>

<h2>🥟 Registro de Mermas</h2>
<a href="index.php">Volver al inicio</a> |
<a href="stock.php">Ver stock</a>

<p><?= limpiar($mensaje) ?></p>

<!-- Registrar merma -->
<h3>Nueva Merma</h3>

<form method="POST">
    <input type="hidden" name="token"
           value="<?= limpiar($_SESSION["token_mermas"]) ?>">
    <input type="hidden" name="accion" value="registrar">

    <label>Salteña:</label>
    <select name="saltena" required>
        <option value="">Seleccionar</option>

        <?php while ($p = $productos->fetch_assoc()): ?>
            <option value="<?= $p["id_saltena"] ?>">
                <?= limpiar($p["nombre"]) ?>
                (Stock: <?= $p["stock_disponible"] ?>)
            </option>
        <?php endwhile; ?>
    </select>

    <label>Cantidad:</label>
    <input type="number" name="cantidad"
           min="1" max="65535" required>

    <label>Motivo:</label>
    <select name="motivo" required>
        <option value="quemada">Quemada</option>
        <option value="caida">Caída</option>
        <option value="cortesia">Cortesía</option>
        <option value="otro">Otro</option>
    </select>

    <button type="submit">Registrar</button>
</form>

<!-- Lista de mermas -->
<h3>Mermas de Hoy</h3>

<table>
    <tr>
        <th>ID</th>
        <th>Salteña</th>
        <th>Cantidad</th>
        <th>Motivo</th>
        <th>Fecha</th>
        <th>Acciones</th>
    </tr>

    <?php while ($fila = $mermas->fetch_assoc()): ?>
        <tr>
            <td><?= $fila["id_merma"] ?></td>
            <td><?= limpiar($fila["nombre"]) ?></td>
            <td><?= $fila["cantidad"] ?></td>
            <td><?= limpiar($fila["motivo"]) ?></td>
            <td><?= limpiar($fila["fecha"]) ?></td>
            <td>
                <!-- Editar cantidad -->
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="token"
                           value="<?= limpiar($_SESSION["token_mermas"]) ?>">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id"
                           value="<?= $fila["id_merma"] ?>">

                    <input type="number" name="cantidad"
                           value="<?= $fila["cantidad"] ?>"
                           min="1" max="65535" required>

                    <button type="submit">Editar</button>
                </form>

                <!-- Eliminar -->
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('¿Eliminar merma?')">
                    <input type="hidden" name="token"
                           value="<?= limpiar($_SESSION["token_mermas"]) ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id"
                           value="<?= $fila["id_merma"] ?>">
                    <button type="submit">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
