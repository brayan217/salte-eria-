
<?php
session_start();
require_once "conexion.php";

// Verificar sesión.
if (!isset($_SESSION["id_usuario"], $_SESSION["id_rol"])) {
    header("Location: login.php");
    exit;
}

// Consultar stock del día.
$sql = "SELECT * FROM v_stock_hoy ORDER BY nombre";
$resultado = mysqli_query($conexion, $sql);

// Proteger datos HTML.
function limpiar($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Stock de Salteñas</title>
    <style>
        body { font-family: Arial; margin: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #aaa; padding: 10px; }
        th { background: #eeeeee; }
    </style>
</head>
<body>

<h2>🥟 Stock Disponible de Hoy</h2>

<a href="index.php">Volver al inicio</a> |
<a href="horneadas.php">Horneadas</a> |
<a href="mermas.php">Mermas</a>

<p>Actualiza la página para consultar las cantidades más recientes.</p>

<!-- Tabla de stock -->
<table>
    <tr>
        <th>Salteña</th>
        <th>Horneadas</th>
        <th>Vendidas</th>
        <th>Mermas</th>
        <th>Disponibles</th>
    </tr>

    <?php while ($fila = mysqli_fetch_assoc($resultado)): ?>
        <tr>
            <td><?= limpiar($fila["nombre"]) ?></td>
            <td><?= $fila["horneadas"] ?></td>
            <td><?= $fila["vendidas"] ?></td>
            <td><?= $fila["mermas"] ?></td>
            <td><strong><?= $fila["stock_disponible"] ?></strong></td>
        </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
