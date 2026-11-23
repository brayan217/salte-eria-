<?php
// Punto de venta - carrito y cobro (cajero, admin, gerente)
require_once __DIR__ . '/../conexion.php';

// Usuario que está logueado (se toma automáticamente de la sesión)
$id_usuario   = $_SESSION['id_usuario'] ?? 0;
$nombre_usuario = $_SESSION['nombre'] ?? '';

// Verificar caja abierta
$caja = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT id_turno FROM caja_turnos WHERE estado = 'abierto' LIMIT 1"));

// Inicializar carrito en sesión
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Agregar al carrito (respetando la cantidad indicada y validando stock)
if (isset($_GET['action']) && $_GET['action'] === 'add' && $caja) {
    $id_saltena = (int)($_GET['sal'] ?? 0);
    $cantidad   = (int)($_GET['cantidad'] ?? 1);
    if ($cantidad < 1) $cantidad = 1;

    if ($id_saltena > 0) {
        $p = mysqli_fetch_assoc(mysqli_query($conexion,
            "SELECT t.nombre, COALESCE(v.stock_disponible,0) AS stock
             FROM tipos_saltena t
             LEFT JOIN v_stock_hoy v ON v.id_saltena = t.id_saltena
             WHERE t.id_saltena = $id_saltena"));
        if ($p) {
            $ya     = (int)($_SESSION['cart'][$id_saltena] ?? 0);
            $nueva  = $ya + $cantidad;
            $stock  = (int)$p['stock'];
            if ($nueva > $stock) {
                flash("Stock insuficiente de " . $p['nombre'] . ". Quedan $stock unidad(es).", 'danger');
            } else {
                $_SESSION['cart'][$id_saltena] = $nueva;
            }
        }
    }
    header("Location: index.php?p=pos");
    exit();
}

// Quitar del carrito
if (isset($_GET['action']) && $_GET['action'] === 'remove') {
    $id_saltena = (int)($_GET['sal'] ?? 0);
    unset($_SESSION['cart'][$id_saltena]);
    header("Location: index.php?p=pos");
    exit();
}

// Vaciar carrito
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $_SESSION['cart'] = [];
    header("Location: index.php?p=pos");
    exit();
}

// Cobrar pedido pendiente de mesero
if (isset($_GET['action']) && $_GET['action'] === 'cobrar_pendiente' && $caja) {
    $id_venta = (int)($_GET['id'] ?? 0);
    $metodo   = $_GET['metodo'] ?? 'efectivo';

    if (!in_array($metodo, ['efectivo', 'qr'], true)) {
        $metodo = 'efectivo';
    }

    // Validar que el pedido exista y siga pendiente
    $venta = mysqli_fetch_assoc(mysqli_query($conexion,
        "SELECT id_venta, total FROM ventas WHERE id_venta = $id_venta AND estado = 'pendiente' LIMIT 1"));

    if ($venta) {
        $total = (float)$venta['total'];

        // Marcar como pagada y guardar el método de pago (aquí se descuenta el stock
        // porque la vista de stock resta las ventas en estado 'pagada')
        mysqli_query($conexion,
            "UPDATE ventas SET estado = 'pagada' WHERE id_venta = $id_venta");

        // Registrar el pago para que aparezca en el corte de caja
        mysqli_query($conexion,
            "INSERT INTO ventas_pagos (id_venta, metodo, monto) VALUES ($id_venta, '$metodo', $total)");

        flash("Pedido #$id_venta cobrado ($metodo) — Bs " . money($total) . ". El stock ya se había descontado al tomar el pedido.", 'success');
    } else {
        flash("No se encontró el pedido o ya fue cobrado", 'danger');
    }

    header("Location: index.php?p=pos");
    exit();
}

// Cobrar venta
if ($_POST && isset($_POST['cobrar'])) {
    if (!$caja) {
        flash("No hay caja abierta", 'danger');
    } elseif (empty($_SESSION['cart'])) {
        flash("El carrito está vacío", 'danger');
    } else {
        $total = 0;
        foreach ($_SESSION['cart'] as $id => $qty) {
            $prod = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT precio FROM tipos_saltena WHERE id_saltena = $id"));
            $total += (float)$prod['precio'] * (float)$qty;
        }

        $efectivo = (float)($_POST['efectivo'] ?? 0);
        $qr       = (float)($_POST['qr'] ?? 0);
        $metodo   = ($efectivo > 0 && $qr > 0) ? 'mixto' : (($qr > 0) ? 'qr' : 'efectivo');

        if (abs(($efectivo + $qr) - $total) > 0.005) {
            flash("El pago no cuadra con el total (Bs " . money($total) . ")", 'danger');
        } else {
            // Registrar venta con el USUARIO LOGUEADO automáticamente
            mysqli_query($conexion, "INSERT INTO ventas (id_turno, id_usuario, id_cliente, fecha, total, estado) 
                VALUES (" . $caja['id_turno'] . ", $id_usuario, NULL, NOW(), $total, 'pagada')");
            $id_venta = mysqli_insert_id($conexion);

            foreach ($_SESSION['cart'] as $id_saltena => $cantidad) {
                $prod = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT precio FROM tipos_saltena WHERE id_saltena = $id_saltena"));
                $precio = (float)$prod['precio'];
                $subtotal = $precio * (float)$cantidad;
                mysqli_query($conexion, "INSERT INTO detalle_ventas (id_venta, id_saltena, cantidad, precio_unitario, subtotal) 
                    VALUES ($id_venta, $id_saltena, $cantidad, $precio, $subtotal)");
            }

            if ($efectivo > 0) {
                mysqli_query($conexion, "INSERT INTO ventas_pagos (id_venta, metodo, monto) VALUES ($id_venta, 'efectivo', $efectivo)");
            }
            if ($qr > 0) {
                mysqli_query($conexion, "INSERT INTO ventas_pagos (id_venta, metodo, monto) VALUES ($id_venta, 'qr', $qr)");
            }

            $_SESSION['cart'] = [];
            flash("Venta #$id_venta registrada — Bs " . money($total), 'success');
        }
    }
    header("Location: index.php?p=pos");
    exit();
}

// Calcular total del carrito
$total_carrito = 0;
$items_carrito = [];
foreach ($_SESSION['cart'] as $id => $qty) {
    $prod = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT nombre, precio FROM tipos_saltena WHERE id_saltena = $id"));
    if ($prod) {
        $sub = (float)$prod['precio'] * (float)$qty;
        $total_carrito += $sub;
        $items_carrito[] = ['nombre' => $prod['nombre'], 'cantidad' => $qty, 'subtotal' => $sub, 'id' => $id];
    }
}

// Clientes para el select
$clientes = mysqli_query($conexion, "SELECT id_cliente, nombre, apellido_p FROM clientes WHERE estado = 'activo' ORDER BY nombre");

// Pedidos pendientes para cobrar (desde mesero)
$pendientes = mysqli_query($conexion,
    "SELECT v.id_venta, v.total, t.nombre AS saltena, d.cantidad, u.nombre AS mesero
     FROM ventas v
     JOIN detalle_ventas d ON d.id_venta = v.id_venta
     JOIN tipos_saltena t ON t.id_saltena = d.id_saltena
     JOIN usuarios u ON u.id_usuario = v.id_usuario
     WHERE v.estado = 'pendiente'
     ORDER BY v.fecha ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS / Caja - El Sin Aceituna</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>

<main class="contenido">
    <h1> Punto de venta - Mostrador</h1>

    <?php if (!$caja): ?>
        <div class="alerta danger">⚠ No hay caja abierta. Abra la caja antes de vender.</div>
    <?php endif; ?>

    <!-- Mensajes flash -->
    <?php $fl = obtener_flash(); if ($fl): ?>
        <div class="alerta <?= $fl['t'] ?>"><?= h($fl['m']) ?></div>
    <?php endif; ?>

    <!-- Pedidos de meseros esperando cobro -->
    <?php if (mysqli_num_rows($pendientes) > 0): ?>
    <div class="card">
        <h2> Pedidos de meseros esperando cobro</h2>
        <table class="tabla">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Mesero</th>
                    <th>Producto × Cant</th>
                    <th>Total</th>
                    <th>Cobrar</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($pd = mysqli_fetch_assoc($pendientes)): ?>
                    <tr>
                        <td><?= $pd['id_venta'] ?></td>
                        <td><?= h($pd['mesero']) ?></td>
                        <td><?= h($pd['saltena']) ?> × <?= (int)$pd['cantidad'] ?></td>
                        <td><b>Bs <?= money($pd['total']) ?></b></td>
                        <td style="white-space:nowrap">
                            <a href="index.php?p=pos&action=cobrar_pendiente&id=<?= $pd['id_venta'] ?>&metodo=efectivo" class="btn btn-naranja">Efectivo</a>
                            <a href="index.php?p=pos&action=cobrar_pendiente&id=<?= $pd['id_venta'] ?>&metodo=qr" class="btn btn-naranja">QR</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Formulario agregar productos -->
    <form method="GET" action="index.php?p=pos">
        <input type="hidden" name="p" value="pos">
        <div class="card">
            <h2>Productos</h2>
            <div class="productos-form">
                <select name="sal" required>
                    <option value="">Seleccione un producto</option>
                    <?php
                    $menu = mysqli_query($conexion,
                        "SELECT t.id_saltena, t.nombre, t.precio, COALESCE(v.stock_disponible,0) AS stock
                         FROM tipos_saltena t
                         LEFT JOIN v_stock_hoy v ON v.id_saltena = t.id_saltena
                         WHERE t.estado = 'activo'
                         ORDER BY t.nombre");
                    while ($m = mysqli_fetch_assoc($menu)):
                        $stock_actual = (int)$m['stock'];
                        $clase = (!$caja || $stock_actual <= 0) ? 'disabled' : '';
                    ?>
                        <option value="<?= $m['id_saltena'] ?>" <?= $clase ?>>
                            <?= h($m['nombre']) ?> - Bs <?= money($m['precio']) ?> (Quedan: <?= $stock_actual ?>)
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="number" name="cantidad" value="1" min="1" placeholder="Cant." <?= !$caja ? 'disabled' : '' ?>>

                <button type="submit" name="action" value="add" class="btn btn-naranja" <?= !$caja ? 'disabled' : '' ?>>Añadir</button>
            </div>
        </div>
    </form>

    <!-- Carrito -->
    <div class="card">
        <h2>Productos elegidos</h2>
        <div class="carrito">
            <?php if (empty($items_carrito)): ?>
                <p class="nota">Aún no hay productos. Elija uno arriba.</p>
            <?php else: ?>
                <?php foreach ($items_carrito as $item): ?>
                    <div class="mini-card">
                        <strong><?= h($item['nombre']) ?></strong>
                        <span>Cantidad: <?= (int)$item['cantidad'] ?></span>
                        <span>Subtotal: Bs <?= money($item['subtotal']) ?></span>
                        <a href="index.php?p=pos&action=remove&sal=<?= $item['id'] ?>" class="btn btn-rojo">Quitar</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulario registrar venta -->
    <form method="POST" action="index.php?p=pos">
        <input type="hidden" name="p" value="pos">
        <div class="card">
            <h2>Registrar venta</h2>

            <div class="campos">
                <div>
                    <label>Vendedor</label>
                    <input type="hidden" name="id_usuario" value="<?= $id_usuario ?>">
                    <input type="text" value="<?= h($nombre_usuario) ?>" readonly style="background:#eee;">
                </div>

                <div>
                    <label>Cliente</label>
                    <select name="cliente">
                        <option value="" selected>Cliente de mostrador</option>
                        <?php while ($c = mysqli_fetch_assoc($clientes)): ?>
                            <option value="<?= $c['id_cliente'] ?>">
                                <?= h($c['nombre'] . ' ' . $c['apellido_p']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label>Total a pagar</label>
                    <input type="number" name="total" step="0.01" value="<?= number_format($total_carrito, 2) ?>" readonly>
                </div>

                <div>
                    <label>Efectivo (Bs)</label>
                    <input type="number" name="efectivo" step="0.01" min="0" value="<?= number_format($total_carrito, 2) ?>">
                </div>

                <div>
                    <label>QR (Bs)</label>
                    <input type="number" name="qr" step="0.01" min="0" value="0">
                </div>
            </div>

            <div class="acciones-form">
                <button type="submit" name="cobrar" class="btn btn-naranja" <?= (empty($items_carrito) || !$caja) ? 'disabled' : '' ?>>
                     Cobrar venta
                </button>
                <?php if (!empty($items_carrito)): ?>
                    <a href="index.php?p=pos&action=clear" class="btn btn-rojo">Vaciar carrito</a>
                <?php endif; ?>
            </div>

            <p class="nota">El vendedor se registra automáticamente según el usuario logueado.</p>
        </div>
    </form>
</main>

</body>
</html>
