<?php
// Editar o anular un pedido - solo el mesero que lo creó
require_once __DIR__ . '/../conexion.php';

$id_usuario = $_SESSION['id_usuario'] ?? 0;
$rol        = $_SESSION['rol'] ?? '';

$id_venta = (int)($_GET['id'] ?? 0);

// Verificar que el pedido exista y pertenezca al usuario logueado
$res = mysqli_query($conexion,
    "SELECT v.id_venta, v.total, v.estado, v.fecha,
            d.id_saltena, d.cantidad, d.precio_unitario, d.subtotal,
            t.nombre AS saltena
     FROM ventas v
     JOIN detalle_ventas d ON d.id_venta = v.id_venta
     JOIN tipos_saltena t ON t.id_saltena = d.id_saltena
     WHERE v.id_venta = $id_venta AND v.id_usuario = $id_usuario
     LIMIT 1");
$pedido = mysqli_fetch_assoc($res);

if (!$pedido) {
    flash("Pedido no encontrado o no te pertenece", 'danger');
    header("Location: index.php?p=pedidos");
    exit();
}

// Solo se pueden editar pedidos pendientes
$puede_editar = ($pedido['estado'] === 'pendiente');

// ---------- ANULAR PEDIDO ----------
if (isset($_GET['anular']) && $puede_editar) {
    if (!isset($_GET['confirmar'])) {
        // Mostrar confirmación
    } else {
        mysqli_query($conexion, "UPDATE ventas SET estado = 'anulada' WHERE id_venta = $id_venta");
        flash("Pedido #$id_venta anulado", 'warning');
        header("Location: index.php?p=pedidos");
        exit();
    }
}

// ---------- MODIFICAR PEDIDO ----------
if ($_POST && isset($_POST['actualizar_pedido']) && $puede_editar) {
    $id_saltena = (int)($_POST['id_saltena'] ?? 0);
    $cantidad   = (int)($_POST['cantidad'] ?? 0);
    $estado     = mysqli_real_escape_string($conexion, $_POST['estado'] ?? 'pendiente');

    // El mesero NO puede marcar un pedido como pagado: solo el cajero al cobrar.
    if (!in_array($estado, ['pendiente', 'anulada'], true)) {
        $estado = 'pendiente';
    }

    if ($id_saltena > 0 && $cantidad > 0) {
        $prod = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT precio FROM tipos_saltena WHERE id_saltena = $id_saltena"));
        if ($prod) {
            $precio = (float)$prod['precio'];
            $subtotal = $precio * (float)$cantidad;
            $total = $subtotal;

            mysqli_query($conexion, "UPDATE ventas SET total = $total, estado = '$estado' WHERE id_venta = $id_venta");
            mysqli_query($conexion, "UPDATE detalle_ventas SET id_saltena = $id_saltena, cantidad = $cantidad, precio_unitario = $precio, subtotal = $subtotal WHERE id_venta = $id_venta");
            flash("Pedido #$id_venta actualizado", 'success');
            header("Location: index.php?p=pedidos");
            exit();
        }
    } else {
        flash("Datos inválidos", 'danger');
    }
}

// Productos para el select
$productos = mysqli_query($conexion, "SELECT id_saltena, nombre, precio FROM tipos_saltena WHERE estado = 'activo' ORDER BY nombre");
?>
<h1 class="pt">Editar pedido #<?= $id_venta ?></h1>

<?php $fl = obtener_flash(); if ($fl): ?>
    <div class="alert <?= $fl['t'] ?>"><?= h($fl['m']) ?></div>
<?php endif; ?>

<!-- Confirmación de anulación (sin JavaScript) -->
<?php if (isset($_GET['anular']) && $puede_editar && !isset($_GET['confirmar'])): ?>
<div class="panel">
    <div class="hd">Confirmar anulación</div>
    <p>¿Estás seguro de anular el pedido #<?= $id_venta ?> (<?= h($pedido['saltena']) ?> × <?= (int)$pedido['cantidad'] ?>)?</p>
    <div style="margin-top:16px">
        <a href="index.php?p=pedido_editar&id=<?= $id_venta ?>&anular=1&confirmar=1" class="btn btn-rojo">Sí, anular</a>
        <a href="index.php?p=pedido_editar&id=<?= $id_venta ?>" class="btn g sm" style="margin-left:10px">Cancelar</a>
    </div>
</div>
<?php endif; ?>

<?php if ($puede_editar): ?>
<div class="panel">
    <div class="hd">Modificar pedido</div>
    <form method="POST" action="index.php?p=pedido_editar&id=<?= $id_venta ?>" class="form-inline">
        <div>
            <label>Salteña</label>
            <select name="id_saltena" required>
                <option value="">Seleccione</option>
                <?php while ($p = mysqli_fetch_assoc($productos)): ?>
                    <option value="<?= $p['id_saltena'] ?>" <?= ($p['id_saltena'] == $pedido['id_saltena']) ? 'selected' : '' ?>>
                        <?= h($p['nombre']) ?> - Bs <?= money($p['precio']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label>Cantidad</label>
            <input type="number" name="cantidad" min="1" value="<?= (int)$pedido['cantidad'] ?>" required>
        </div>
        <div>
            <label>Estado</label>
            <select name="estado" required>
                <option value="pendiente" <?= ($pedido['estado'] === 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                <option value="anulada" <?= ($pedido['estado'] === 'anulada') ? 'selected' : '' ?>>Anulada</option>
            </select>
        </div>
        <button type="submit" name="actualizar_pedido" class="btn p">Guardar cambios</button>
        <a href="index.php?p=pedidos" class="btn g sm" style="margin-left:8px">Cancelar</a>
    </form>
</div>
<?php else: ?>
<div class="alert info">Este pedido ya no se puede modificar porque su estado es: <b><?= h($pedido['estado']) ?></b></div>
<?php endif; ?>

<p><a href="index.php?p=pedidos" class="btn btn-naranja sm">← Volver a mis pedidos</a></p>
