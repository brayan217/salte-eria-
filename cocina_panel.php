
<?php
session_start();
require_once "conexion.php";

// Seguridad: token para los formularios.
if (empty($_SESSION["csrf_cocina"])) {
    $_SESSION["csrf_cocina"] = bin2hex(random_bytes(32));
}

function limpiar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Consultar tipos de salteñas.
$sql = "SELECT
            t.id_saltena,
            t.nombre,
            t.precio,
            COALESCE(SUM(
                CASE
                    WHEN DATE(h.fecha_hornea) = CURDATE()
                    THEN h.cantidad
                    ELSE 0
                END
            ), 0) AS horneadas_hoy
        FROM tipos_saltena t
        LEFT JOIN horneadas h
            ON t.id_saltena = h.id_saltena
        WHERE t.estado = 'activo'
        GROUP BY
            t.id_saltena,
            t.nombre,
            t.precio
        ORDER BY t.nombre ASC";

$resultado = $conexion->query($sql);

// Consultar pedidos si existen las tablas.
$pedidos = [];
$mensaje_pedidos = "";

$tablas = $conexion->query(
    "SELECT TABLE_NAME
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME IN ('pedidos', 'detalle_pedidos')"
);

if ($tablas->num_rows === 2) {

    try {
        $sql_pedidos = "
            SELECT
                p.id_pedido,
                p.numero_mesa,
                p.fecha_pedido,
                p.estado,
                t.nombre AS saltena,
                dp.cantidad
            FROM pedidos p
            INNER JOIN detalle_pedidos dp
                ON p.id_pedido = dp.id_pedido
            INNER JOIN tipos_saltena t
                ON dp.id_saltena = t.id_saltena
            WHERE p.estado IN ('pendiente', 'preparando')
            ORDER BY p.fecha_pedido ASC, p.id_pedido ASC
        ";

        $consulta_pedidos = $conexion->query($sql_pedidos);
        $pedidos = $consulta_pedidos->fetch_all(MYSQLI_ASSOC);

    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
        $mensaje_pedidos =
            "Revisa la estructura de las tablas de pedidos.";
    }

} else {
    $mensaje_pedidos =
        "El módulo de pedidos todavía no está conectado.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Panel de Cocina</title>

    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5 mb-5">

    <h2 class="text-center mb-2">
        Panel de Cocina
    </h2>

    <p class="text-center text-muted mb-4">
        Salteñería El Sin Aceituna
    </p>

    <?php if (isset($_GET["registrado"])): ?>

        <div class="alert alert-success">
            Tanda horneada registrada correctamente.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger">
            No se pudo registrar la tanda horneada.
        </div>

    <?php endif; ?>

    <!-- REGISTRAR HORNEADAS -->

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">
            Registrar tandas horneadas
        </div>

        <div class="card-body">

            <p>
                Seleccione una salteña y registre
                rápidamente la cantidad horneada.
            </p>

            <div class="row g-3">

                <?php while (
                    $fila = $resultado->fetch_assoc()
                ): ?>

                <div class="col-md-4">

                    <div class="card h-100 shadow-sm">

                        <div class="card-body text-center">

                            <h5 class="card-title">
                                <?= limpiar($fila["nombre"]) ?>
                            </h5>

                            <p class="text-muted">
                                Precio: Bs
                                <?= number_format(
                                    $fila["precio"], 2
                                ) ?>
                            </p>

                            <p>
                                Horneadas hoy:
                                <strong>
                                <?= (int)$fila["horneadas_hoy"] ?>
                                </strong>
                            </p>

                            <form action="registrar_horneada.php"
                                  method="POST">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= limpiar(
                                           $_SESSION["csrf_cocina"]
                                       ) ?>">

                                <input type="hidden"
                                       name="id_saltena"
                                       value="<?= (int)$fila["id_saltena"] ?>">

                                <div class="d-flex gap-2">

                                    <button type="submit"
                                            name="cantidad"
                                            value="10"
                                            class="btn btn-success w-50">
                                        +10
                                    </button>

                                    <button type="submit"
                                            name="cantidad"
                                            value="15"
                                            class="btn btn-primary w-50">
                                        +15
                                    </button>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

                <?php endwhile; ?>

            </div>

        </div>
    </div>

    <!-- PEDIDOS ENTRANTES -->

    <div class="card">

        <div class="card-header bg-dark text-white">
            Pedidos entrantes del salón
        </div>

        <div class="card-body">

            <?php if ($mensaje_pedidos !== ""): ?>

                <div class="alert alert-warning">
                    <?= limpiar($mensaje_pedidos) ?>
                </div>

            <?php endif; ?>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>N.º Pedido</th>
                            <th>Mesa</th>
                            <th>Salteña</th>
                            <th>Cantidad</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($pedidos) > 0): ?>

                        <?php foreach ($pedidos as $pedido): ?>

                        <tr>
                            <td>
                                <?= (int)$pedido["id_pedido"] ?>
                            </td>

                            <td>
                                <?= limpiar($pedido["numero_mesa"]) ?>
                            </td>

                            <td>
                                <?= limpiar($pedido["saltena"]) ?>
                            </td>

                            <td>
                                <?= (int)$pedido["cantidad"] ?>
                            </td>

                            <td>
                                <span class="badge bg-warning text-dark">
                                    <?= limpiar($pedido["estado"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= limpiar($pedido["fecha_pedido"]) ?>
                            </td>
                        </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6"
                                class="text-center">
                                No hay pedidos pendientes.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>

            <button type="button"
                    class="btn btn-secondary"
                    onclick="location.reload()">
                Actualizar pedidos
            </button>

        </div>

    </div>

</div>

</body>
</html>

<?php
$resultado->free();
$conexion->close();
?>
