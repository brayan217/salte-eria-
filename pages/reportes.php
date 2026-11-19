<?php
// Reportes - solo Gerente y Administrador
require_once __DIR__ . '/../conexion.php';

if (!tiene_rol(['Gerente', 'Administrador'])) {
    flash("Acceso denegado: solo Gerente y Administrador pueden ver reportes", 'danger');
    redirigir('index.php');
}

// Ventas del día
$hoy = date('Y-m-d');
$resumen = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COUNT(*) AS total_ventas, COALESCE(SUM(total),0) AS monto_total
     FROM ventas WHERE DATE(fecha) = '$hoy' AND estado = 'pagada'"));

// Ticket promedio
$ticket_promedio = $resumen['total_ventas'] > 0 ? $resumen['monto_total'] / $resumen['total_ventas'] : 0;

// Total mermas
$total_mermas = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COALESCE(SUM(cantidad),0) AS total FROM mermas WHERE DATE(fecha) = '$hoy'"));
?>
<h1 class="pt">📈 Reportes</h1>

<div class="grid">
    <div class="card dorado">
        <h3>Ingresos hoy</h3>
        <div class="v">Bs <?= money($resumen['monto_total']) ?></div>
    </div>
    <div class="card">
        <h3>N° de ventas</h3>
        <div class="v"><?= (int)$resumen['total_ventas'] ?></div>
    </div>
    <div class="card oliva">
        <h3>Ticket promedio</h3>
        <div class="v">Bs <?= money($ticket_promedio) ?></div>
    </div>
    <div class="card rojo">
        <h3>Mermas hoy</h3>
        <div class="v"><?= (int)$total_mermas['total'] ?></div>
        <div class="s">unidades perdidas</div>
    </div>
</div>

<div class="split">
    <div class="panel">
        <div class="hd">Detalle de ventas</div>
        <?php
        $ventas = mysqli_query($conexion,
            "SELECT v.id_venta, v.fecha, v.total, v.metodo,
                    c.nombre AS cliente_nombre, c.apellido_p AS cliente_apellido,
                    u.nombre AS vendedor
             FROM ventas v
             LEFT JOIN clientes c ON c.id_cliente = v.id_cliente
             JOIN usuarios u ON u.id_usuario = v.id_usuario
             WHERE v.estado = 'pagada'
             ORDER BY v.fecha DESC");
        ?>
        <table>
            <tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Total</th><th>Pago</th></tr>
            <?php while ($v = mysqli_fetch_assoc($ventas)): ?>
                <tr>
                    <td><?= $v['id_venta'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                    <td><?= $v['cliente_nombre'] ? h($v['cliente_nombre'] . ' ' . $v['cliente_apellido']) : 'Mostrador' ?></td>
                    <td><?= h($v['vendedor']) ?></td>
                    <td><b>Bs <?= money($v['total']) ?></b></td>
                    <td><span class="pill" style="background:var(--naranja)"><?= h($v['metodo']) ?></span></td>
                </tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($ventas) == 0): ?>
                <tr><td colspan="6" style="text-align:center;color:#aaa">Sin ventas</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="panel">
        <div class="hd">🏆 Salteñas más vendidas (ranking)</div>
        <?php
        $ranking = mysqli_query($conexion,
            "SELECT t.nombre, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS ingresos
             FROM detalle_ventas d
             JOIN ventas v ON v.id_venta = d.id_venta
             JOIN tipos_saltena t ON t.id_saltena = d.id_saltena
             WHERE v.estado = 'pagada'
             GROUP BY d.id_saltena, t.nombre
             ORDER BY unidades DESC");
        ?>
        <table>
            <tr><th>Salteña</th><th>Unidades</th><th>Ingresos aprox.</th></tr>
            <?php while ($r = mysqli_fetch_assoc($ranking)): ?>
                <tr>
                    <td><b><?= h($r['nombre']) ?></b></td>
                    <td><?= (int)$r['unidades'] ?></td>
                    <td>Bs <?= money($r['ingresos']) ?></td>
                </tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($ranking) == 0): ?>
                <tr><td colspan="3" style="text-align:center;color:#aaa">—</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
