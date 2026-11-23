<?php
// Pedidos - tomar pedidos de mesas (mesero, cajero, admin, gerente)
require_once __DIR__ . '/../conexion.php';

$id_usuario = $_SESSION['id_usuario'] ?? 0;
$rol        = $_SESSION['rol'] ?? '';

// Verificar si hay caja abierta
$caja = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT id_turno FROM caja_turnos WHERE estado = 'abierto' LIMIT 1"));

// Registrar pedido (el Administrador solo visualiza)
if ($_POST && isset($_POST['registrar_pedido']) && $rol !== 'Administrador') {
    if (!$caja) {
        flash("No hay caja abierta. No se puede enviar el pedido.", 'danger');
    } else {
        $id_saltena = (int)($_POST['id_saltena'] ?? 0);
        $cantidad   = (int)($_POST['cantidad'] ?? 0);
        $mesa       = mysqli_real_escape_string($conexion, $_POST['mesa'] ?? '');

        if ($id_saltena > 0 && $cantidad > 0 && $mesa !== '') {
            $prod = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT precio FROM tipos_saltena WHERE id_saltena = $id_saltena"));
            if ($prod) {
                $total = (float)$prod['precio'] * (float)$cantidad;
                // Guardar con el id_turno de la caja abierta
                mysqli_query($conexion, "INSERT INTO ventas (id_turno, id_usuario, id_cliente, fecha, total, estado) VALUES (" . $caja['id_turno'] . ", $id_usuario, NULL, NOW(), $total, 'pendiente')");
                $id_venta = mysqli_insert_id($conexion);
                mysqli_query($conexion, "INSERT INTO detalle_ventas (id_venta, id_saltena, cantidad, precio_unitario, subtotal) VALUES ($id_venta, $id_saltena, $cantidad, " . (float)$prod['precio'] . ", $total)");
                flash("Pedido enviado a caja ✔", 'success');
            }
        } else {
            flash("Datos inválidos", 'danger');
        }
    }
    redirigir('index.php?p=pedidos');
    exit();
}

// Productos disponibles
$productos = mysqli_query($conexion, "SELECT id_saltena, nombre, precio FROM tipos_saltena WHERE estado = 'activo' ORDER BY nombre");

// Consultas según el rol
if ($rol === 'Administrador') {
    // El Administrador ve solo el listado de pedidos que los meseros mandaron a caja
    $pendientes = mysqli_query($conexion,
        "SELECT v.id_venta, v.fecha, v.total, v.estado, t.nombre AS saltena, d.cantidad, u.nombre AS mesero
         FROM ventas v
         JOIN detalle_ventas d ON d.id_venta = v.id_venta
         JOIN tipos_saltena t ON t.id_saltena = d.id_saltena
         JOIN usuarios u ON u.id_usuario = v.id_usuario
         JOIN roles r ON r.id_rol = u.id_rol
         WHERE r.nombre = 'Mesero'
         ORDER BY v.fecha DESC");
} elseif ($rol === 'Mesero') {
    $pendientes = mysqli_query($conexion,
        "SELECT v.id_venta, v.fecha, v.total, t.nombre AS saltena, d.cantidad, u.nombre AS mesero
         FROM ventas v
         JOIN detalle_ventas d ON d.id_venta = v.id_venta
         JOIN tipos_saltena t ON t.id_saltena = d.id_saltena
         JOIN usuarios u ON u.id_usuario = v.id_usuario
         WHERE v.estado = 'pendiente' AND v.id_usuario = $id_usuario
         ORDER BY v.fecha DESC");
} else {
    $pendientes = mysqli_query($conexion,
        "SELECT v.id_venta, v.fecha, v.total, t.nombre AS saltena, d.cantidad, u.nombre AS mesero
         FROM ventas v
         JOIN detalle_ventas d ON d.id_venta = v.id_venta
         JOIN tipos_saltena t ON t.id_saltena = d.id_saltena
         JOIN usuarios u ON u.id_usuario = v.id_usuario
         WHERE v.estado = 'pendiente'
         ORDER BY v.fecha DESC");
}
?>
<h1 class="pt">📝 Pedidos de mesa</h1>

<?php if ($rol === 'Administrador'): ?>
    <!-- Vista exclusiva para Administrador: solo listado de pedidos enviados por meseros a caja -->
    <div class="panel">
        <div class="hd">Listado de pedidos enviados por meseros a caja</div>
        <?php if (mysqli_num_rows($pendientes) > 0): ?>
        <table>
            <tr>
                <th>#</th>
                <th>Mesero</th>
                <th>Salteña × Cant</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Fecha y Hora</th>
            </tr>
            <?php while ($pd = mysqli_fetch_assoc($pendientes)): ?>
                <tr>
                    <td><?= $pd['id_venta'] ?></td>
                    <td><b><?= h($pd['mesero']) ?></b></td>
                    <td><?= h($pd['saltena']) ?> × <?= (int)$pd['cantidad'] ?></td>
                    <td><b>Bs <?= money($pd['total']) ?></b></td>
                    <td>
                        <span class="pill" style="background:<?= $pd['estado'] === 'pagada' ? 'var(--oliva)' : 'var(--naranja)' ?>">
                            <?= h($pd['estado']) ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($pd['fecha'])) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
            <p style="text-align:center;color:#aaa;padding:20px">No hay pedidos enviados por meseros todavía</p>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- Vista normal para Mesero, Cajero y Gerente -->
    <div class="panel">
        <div class="hd">🧾 Estado de la caja</div>
        <?php if ($caja): ?>
            <div style="padding:15px">
                <span class="badge" style="background:var(--oliva)">🟢 CAJA ABIERTA</span>
                <span style="margin-left:10px">Turno #<?= $caja['id_turno'] ?> — Puedes enviar pedidos</span>
            </div>
        <?php else: ?>
            <div style="padding:15px">
                <span class="badge" style="background:var(--rojo)">🔴 CAJA CERRADA</span>
                <span style="margin-left:10px">No se pueden enviar pedidos hasta que se abra la caja</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="split">
        <div class="panel">
            <div class="hd">Nuevo pedido</div>
            <form method="POST" action="index.php?p=pedidos" class="form-inline">
                <div>
                    <label>Salteña</label>
                    <select name="id_saltena" required <?= !$caja ? 'disabled' : '' ?>>
                        <option value="">Seleccione</option>
                        <?php while ($p = mysqli_fetch_assoc($productos)): ?>
                            <option value="<?= $p['id_saltena'] ?>"><?= h($p['nombre']) ?> - Bs <?= money($p['precio']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label>Cantidad</label>
                    <input type="number" name="cantidad" min="1" value="1" required <?= !$caja ? 'disabled' : '' ?>>
                </div>
                <div>
                    <label>Mesa</label>
                    <input type="text" name="mesa" placeholder="Ej: 3" required <?= !$caja ? 'disabled' : '' ?>>
                </div>
                <button type="submit" name="registrar_pedido" class="btn p" <?= !$caja ? 'disabled' : '' ?>>
                    📤 Enviar a caja
                </button>
            </form>
            <?php if (!$caja): ?>
                <p class="nota" style="margin-top:10px">Primero el cajero debe abrir la caja desde la pestaña <b>Cierre de Caja</b>.</p>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="hd"><?= $rol === 'Mesero' ? 'Mis pedidos enviados' : 'Pedidos enviados esperando cobro' ?></div>
            <?php if (mysqli_num_rows($pendientes) > 0): ?>
            <table>
                <tr>
                    <th>#</th>
                    <th>Mesero</th>
                    <th>Salteña × Cant</th>
                    <th>Total</th>
                    <th>Hora</th>
                    <th>Acciones</th>
                </tr>
                <?php while ($pd = mysqli_fetch_assoc($pendientes)): ?>
                    <tr>
                        <td><?= $pd['id_venta'] ?></td>
                        <td><?= h($pd['mesero']) ?></td>
                        <td><?= h($pd['saltena']) ?> × <?= (int)$pd['cantidad'] ?></td>
                        <td><b>Bs <?= money($pd['total']) ?></b></td>
                        <td><?= date('H:i', strtotime($pd['fecha'])) ?></td>
                        <td style="white-space:nowrap">
                            <?php if ($rol === 'Mesero'): ?>
                                <a href="index.php?p=pedido_editar&id=<?= $pd['id_venta'] ?>" class="btn btn-naranja sm">Editar</a>
                                <a href="index.php?p=pedido_editar&id=<?= $pd['id_venta'] ?>&anular=1" class="btn btn-rojo sm">Anular</a>
                            <?php else: ?>
                                <a href="index.php?p=pos&action=cobrar_pendiente&id=<?= $pd['id_venta'] ?>&metodo=efectivo" class="btn btn-naranja sm">Efectivo</a>
                                <a href="index.php?p=pos&action=cobrar_pendiente&id=<?= $pd['id_venta'] ?>&metodo=qr" class="btn btn-naranja sm">QR</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <?php else: ?>
                <p style="text-align:center;color:#aaa;padding:20px">No hay pedidos pendientes</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>