
<?php
// CONEXIÓN Y SESIÓN
require_once "conexion.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario inició sesión.
if (!isset($_SESSION["id_usuario"], $_SESSION["id_rol"])) {
    header("Location: login.php");
    exit;
}

$idMesero = (int) $_SESSION["id_usuario"];
$idRol = (int) $_SESSION["id_rol"];

// Verificar rol Mesero.
$stmt = $conexion->prepare(
    "SELECT nombre FROM roles WHERE id_rol = ?"
);
$stmt->bind_param("i", $idRol);
$stmt->execute();
$rol = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rol || strtolower(trim($rol["nombre"])) !== "mesero") {
    http_response_code(403);
    exit("Acceso no autorizado.");
}

// Proteger datos HTML.
function limpiar($dato) {
    return htmlspecialchars(
        (string)($dato ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Token de seguridad.
if (empty($_SESSION["token_pedido"])) {
    $_SESSION["token_pedido"] = bin2hex(random_bytes(32));
}

$error = "";
$productos = [];
$pedidos = [];

// REGISTRAR PEDIDO
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["token"] ?? "";

    $idTipo = filter_input(
        INPUT_POST, "id_tipo", FILTER_VALIDATE_INT
    );

    $cantidad = filter_input(
        INPUT_POST, "cantidad", FILTER_VALIDATE_INT
    );

    if (!hash_equals($_SESSION["token_pedido"], $token)) {
        $error = "Solicitud no válida.";

    } elseif (!$idTipo || !$cantidad || $cantidad < 1) {
        $error = "Verifica el producto y la cantidad.";

    } else {

        try {
            // Iniciar transacción.
            $conexion->begin_transaction();

            // Obtener precio del producto.
            $stmt = $conexion->prepare(
                "SELECT precio FROM tipos_saltena
                 WHERE id_tipo_saltena = ?"
            );
            $stmt->bind_param("i", $idTipo);
            $stmt->execute();
            $producto = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$producto) {
                throw new Exception("Producto no encontrado.");
            }

            // Calcular total.
            $precio = (float) $producto["precio"];
            $total = round($precio * $cantidad, 2);

            // Registrar venta pendiente.
            $stmt = $conexion->prepare(
                "INSERT INTO ventas
                 (id_usuario, fecha, total, estado)
                 VALUES (?, NOW(), ?, 'pendiente')"
            );
            $stmt->bind_param("id", $idMesero, $total);
            $stmt->execute();
            $idVenta = $conexion->insert_id;
            $stmt->close();

            // Guardar detalle de venta.
            $stmt = $conexion->prepare(
                "INSERT INTO detalle_ventas
                 (id_venta, id_tipo_saltena,
                  cantidad, precio_unitario, subtotal)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iiidd",
                $idVenta,
                $idTipo,
                $cantidad,
                $precio,
                $total
            );
            $stmt->execute();
            $stmt->close();

            // Confirmar pedido.
            $conexion->commit();

            // Renovar token.
            $_SESSION["token_pedido"] = bin2hex(random_bytes(32));

            header("Location: mesero_pedidos.php?registrado=1");
            exit;

        } catch (Throwable $e) {
            $conexion->rollback();
            error_log($e->getMessage());
            $error = "No se pudo registrar el pedido. Revisa las columnas de MySQL.";
        }
    }
}

// CONSULTAR PRODUCTOS Y PEDIDOS
try {
    // Cargar tipos de salteñas.
    $resultado = $conexion->query(
        "SELECT id_tipo_saltena, nombre, precio
         FROM tipos_saltena ORDER BY nombre"
    );
    $productos = $resultado->fetch_all(MYSQLI_ASSOC);

    // Mostrar solo pedidos del mesero conectado.
    $stmt = $conexion->prepare(
        "SELECT v.id_venta, v.fecha, v.total,
                v.estado, t.nombre AS producto,
                d.cantidad
         FROM ventas v
         INNER JOIN detalle_ventas d
            ON v.id_venta = d.id_venta
         INNER JOIN tipos_saltena t
            ON d.id_tipo_saltena = t.id_tipo_saltena
         WHERE v.id_usuario = ?
         ORDER BY v.fecha DESC"
    );

    $stmt->bind_param("i", $idMesero);
    $stmt->execute();
    $pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    $error = "No se pudieron cargar los datos. Verifica la estructura de las tablas.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Vista Mesero - El Sin Aceituna</title>

    <!-- Bootstrap 5 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Estilos de la plantilla -->
    <link rel="stylesheet" href="styles.css">

    <!-- Estilos de navegación -->
    <style>
        .nav {
            background: #222;
            color: white;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .brand {
            font-weight: bold;
            font-size: 20px;
        }

        .menu, .userbox {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .nav a {
            color: white;
            text-decoration: none;
        }

        .nav a:hover {
            color: #ffc107;
        }

        @media (max-width: 768px) {
            .nav {
                flex-direction: column;
            }
        }
    </style>
</head>

<body class="bg-light">

<!-- BARRA DE NAVEGACIÓN -->
<nav class="nav">

    <div class="brand">
        🥟 EL SIN ACEITUNA
    </div>

    <div class="menu">
        <a href="ventas.php">📄 Ventas</a>
        <a href="clientes.php">👥 Clientes</a>
        <a href="usuarios.php">👤 Usuarios</a>
        <a href="mesero_pedidos.php">📋 Mis Pedidos</a>
    </div>

    <div class="userbox">
        <span class="badge bg-success">Mesero</span>
        <span>Usuario #<?= $idMesero ?></span>
        <a href="logout.php">Salir</a>
    </div>

</nav>

<!-- CONTENIDO DEL MESERO -->
<div class="container mt-5 mb-5">

    <h2 class="text-center mb-4">
        Gestión de Pedidos - Mesero
    </h2>

    <!-- Mensajes -->
    <?php if (isset($_GET["registrado"])): ?>
        <div class="alert alert-success">
            Pedido registrado correctamente.
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <?= limpiar($error) ?>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO DE PEDIDOS -->
    <div class="card mb-4">

        <div class="card-header bg-primary text-white">
            Registrar Nuevo Pedido
        </div>

        <div class="card-body">

            <form method="POST">

                <input type="hidden"
                       name="token"
                       value="<?= limpiar($_SESSION["token_pedido"]) ?>">

                <div class="row g-3">

                    <!-- Seleccionar salteña -->
                    <div class="col-md-6">
                        <label class="form-label">
                            Tipo de Salteña
                        </label>

                        <select name="id_tipo"
                                class="form-select"
                                required>

                            <option value="">
                                Seleccionar producto
                            </option>

                            <?php foreach ($productos as $p): ?>
                                <option value="<?= (int)$p["id_tipo_saltena"] ?>">
                                    <?= limpiar($p["nombre"]) ?>
                                    - Bs <?= number_format((float)$p["precio"], 2) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <!-- Cantidad -->
                    <div class="col-md-6">
                        <label class="form-label">
                            Cantidad
                        </label>

                        <input type="number"
                               name="cantidad"
                               class="form-control"
                               min="1"
                               max="999"
                               value="1"
                               required>
                    </div>

                </div>

                <!-- Botón registrar -->
                <button type="submit"
                        class="btn btn-primary mt-3"
                        <?= empty($productos) ? "disabled" : "" ?>>
                    Registrar Pedido
                </button>

            </form>

        </div>
    </div>

    <!-- LISTA DE PEDIDOS -->
    <div class="card">

        <div class="card-header bg-dark text-white">
            Mis Pedidos Registrados
        </div>

        <div class="card-body table-responsive">

            <table class="table table-bordered table-striped">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>

                    <!-- Recorrer pedidos -->
                    <?php foreach ($pedidos as $fila): ?>

                        <tr>
                            <td><?= (int)$fila["id_venta"] ?></td>
                            <td><?= limpiar($fila["fecha"]) ?></td>
                            <td><?= limpiar($fila["producto"]) ?></td>
                            <td><?= (int)$fila["cantidad"] ?></td>

                            <td>
                                Bs <?= number_format((float)$fila["total"], 2) ?>
                            </td>

                            <td>
                                <span class="badge bg-secondary">
                                    <?= limpiar($fila["estado"]) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <!-- Sin pedidos -->
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                No hay pedidos para mostrar.
                            </td>
                        </tr>
                    <?php endif; ?>

                </tbody>

            </table>

        </div>
    </div>

</div>

</body>
</html>
