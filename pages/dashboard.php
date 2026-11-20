<?php
// Dashboard - resumen rápido (visible para todos los roles)
require_once __DIR__ . '/../conexion.php';

// Contar ventas del día
$hoy = date('Y-m-d');
$ventas_hoy = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COUNT(*) AS total, COALESCE(SUM(total),0) AS monto
     FROM ventas WHERE DATE(fecha) = '$hoy' AND estado = 'pagada'"));

// Caja abierta o cerrada
$caja = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT * FROM caja_turnos WHERE estado = 'abierto' LIMIT 1"));
?>
<h1 class="pt"> Dashboard</h1>

<div class="grid">
    <div class="card oliva">
        <h3>Ventas hoy</h3>
        <div class="v"><?= (int)$ventas_hoy['total'] ?></div>
        <div class="s">transacciones pagadas</div>
    </div>
    <div class="card oliva">
        <h3>Ventas hoy</h3>
        <div class="v"><?= (int)$ventas_hoy['total'] ?></div>
        <div class="s">transacciones pagadas</div>
    </div>
    <div class="card dorado">
        <h3>Ingresos hoy</h3>
        <div class="v">Bs <?= money($ventas_hoy['monto']) ?></div>
        <div class="s">ventas del día</div>
    </div>
    <div class="card <?= $caja ? 'oliva' : 'rojo' ?>">
        <h3>Caja</h3>
        <div class="v" style="font-size:20px"><?= $caja ? '🟢 Abierta' : '🔴 Cerrada' ?></div>
        <div class="s"><?= $caja ? 'Turno #' . $caja['id_turno'] : 'Abra caja para vender' ?></div>
    </div>
</div>

<div class="split">
    <div class="panel">
        <div class="hd"> Salteñas más vendidas hoy</div>
        <?php
        $top = mysqli_query($conexion,
            "SELECT t.nombre, SUM(d.cantidad) AS unidades
             FROM detalle_ventas d
             JOIN ventas v ON v.id_venta = d.id_venta
             JOIN tipos_saltena t ON t.id_saltena = d.id_saltena
             WHERE DATE(v.fecha) = '$hoy' AND v.estado = 'pagada'
             GROUP BY d.id_saltena, t.nombre
             ORDER BY unidades DESC LIMIT 5");
        ?>
        <table>
            <tr><th>#</th><th>Salteña</th><th>Unidades</th></tr>
            <?php $i = 0; while ($f = mysqli_fetch_assoc($top)): $i++; ?>
                <tr><td><?= $i ?></td><td><?= h($f['nombre']) ?></td><td><?= (int)$f['unidades'] ?></td></tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($top) == 0): ?>
                <tr><td colspan="3" style="text-align:center;color:#aaa">Sin ventas aún</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="panel">
        <div class="hd"> Últimas ventas</div>
        <?php
        $recientes = mysqli_query($conexion,
            "SELECT v.id_venta, v.fecha, v.total,
                    (SELECT p.metodo FROM ventas_pagos p WHERE p.id_venta = v.id_venta LIMIT 1) AS metodo,
                    u.nombre AS vendedor
             FROM ventas v
             JOIN usuarios u ON u.id_usuario = v.id_usuario
             WHERE v.estado = 'pagada'
             ORDER BY v.fecha DESC LIMIT 6");
        ?>
        <table>
            <tr><th>#</th><th>Hora</th><th>Total</th><th>Pago</th><th>Vendedor</th></tr>
            <?php while ($f = mysqli_fetch_assoc($recientes)): ?>
                <tr>
                    <td><?= $f['id_venta'] ?></td>
                    <td><?= date('H:i', strtotime($f['fecha'])) ?></td>
                    <td><b>Bs <?= money($f['total']) ?></b></td>
                    <td><span class="pill" style="background:var(--oliva)"><?= h($f['metodo']) ?></span></td>
                    <td><?= h($f['vendedor']) ?></td>
                </tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($recientes) == 0): ?>
                <tr><td colspan="5" style="text-align:center;color:#aaa">—</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
