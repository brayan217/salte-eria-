<?php
// Pedidos - tomar pedidos de mesas (mesero, cajero, admin, gerente)
require_once __DIR__ . '/../conexion.php';

$id_usuario = $_SESSION['id_usuario'] ?? 0;

// Registrar pedido
if ($_POST && isset($_POST['registrar_pedido'])) {
    $id_saltena = (int)($_POST['id_saltena'] ?? 0);
    $cantidad   = (int)($_POST['cantidad'] ?? 0);
    $mesa       = mysqli_real_escape_string($conexion, $_POST['mesa'] ?? '');

    if ($id_saltena > 0 && $cantidad > 0 && $mesa !== '') {
        $prod = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT precio FROM tipos_saltena WHERE id_saltena = $id_saltena"));
        if ($prod) {
            $total = (float)$prod['precio'] * (float)$cantidad;
            mysqli_query($conexion, "INSERT INTO ventas (id_turno, id_usuario, id_cliente, fecha, total, metodo, estado) VALUES (0, $id_usuario, NULL, NOW(), $total, 'efectivo', 'pendiente')");
            $id_venta = mysqli_insert_id($conexion);
            mysqli_query($conexion, "INSERT INTO detalle_ventas (id_venta, id_saltena, cantidad, precio_unitario, subtotal) VALUES ($id_venta, $id_saltena, $cantidad, " . (float)$prod['precio'] . ", $total)");
            flash("Pedido enviado a caja ✔", 'success');
        }
    } else {
        flash("Datos inválidos", 'danger');
    }
    redirigir('index.php?p=pedidos');
}

// Productos disponibles
$productos = mysqli_query($conexion, "SELECT id_saltena, nombre, precio FROM tipos_saltena WHERE estado = 'activo' ORDER BY nombre");

// Pedidos pendientes
$pendientes = mysqli_query($conexion,
    "SELECT v.id_venta, v.fecha, v.total, t.nombre AS saltena, d.cantidad, u.nombre AS mesero
     FROM ventas v
     JOIN detalle_ventas d ON d.id_venta = v.id_venta
     JOIN tipos_saltena t ON t.id_saltena = d.id_saltena
     JOIN usuarios u ON u.id_usuario = v.id_usuario
     WHERE v.estado = 'pendiente'
     ORDER BY v.fecha DESC");
?>
<h1 class="pt">📝 Pedidos de mesa</h1>

<div class="split">
    <div class="panel">
        <div class="hd">Nuevo pedido</div>
        <form method="POST" action="index.php?p=pedidos" class="form-inline">
            <div>
                <label>Salteña</label>
                <select name="id_saltena" required>
                    <option value="">Seleccione</option>
                    <?php while ($p = mysqli_fetch_assoc($productos)): ?>
                        <option value="<?= $p['id_saltena'] ?>"><?= h($p['nombre']) ?> - Bs <?= money($p['precio']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label>Cantidad</label>
                <input type="number" name="cantidad" min="1" value="1" required>
            </div>
            <div>
                <label>Mesa</label>
                <input type="text" name="mesa" placeholder="Ej: 3" required>
            </div>
            <button type="submit" name="registrar_pedido" class="btn p">📤 Enviar a caja</button>
        </form>
    </div>

    <div class="panel">
        <div class="hd">Pedidos enviados esperando cobro</div>
        <?php if (mysqli_num_rows($pendientes) > 0): ?>
        <table>
            <tr><th>#</th><th>Mesa/Mesero</th><th>Salteña × Cant</th><th>Total</th><th>Hora</th></tr>
            <?php while ($pd = mysqli_fetch_assoc($pendientes)): ?>
                <tr>
                    <td><?= $pd['id_venta'] ?></td>
                    <td><?= h($pd['mesero']) ?></td>
                    <td><?= h($pd['saltena']) ?> × <?= (int)$pd['cantidad'] ?></td>
                    <td><b>Bs <?= money($pd['total']) ?></b></td>
                    <td><?= date('H:i', strtotime($pd['fecha'])) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
            <p style="text-align:center;color:#aaa;padding:20px">No hay pedidos pendientes</p>
        <?php endif; ?>
    </div>
</div>
