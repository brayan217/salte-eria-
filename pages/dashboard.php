<?php
// Dashboard - resumen rápido (visible para todos los roles)
date_default_timezone_set('America/La_Paz');
require_once __DIR__ . '/../conexion.php';

// Contar ventas del día
$hoy = date('Y-m-d');
$ventas_hoy = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COUNT(*) AS total, COALESCE(SUM(total),0) AS monto
     FROM ventas WHERE DATE(fecha) = '$hoy' AND estado = 'pagada'"));

// Unidades vendidas hoy usando la vista v_saltenas_vendidas_por_dia
$unidades_hoy = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COALESCE(SUM(unidades_vendidas),0) AS total
     FROM v_saltenas_vendidas_por_dia WHERE dia = '$hoy'"));

// Caja abierta o cerrada
$caja = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT * FROM caja_turnos WHERE estado = 'abierto' LIMIT 1"));
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
    <h1 class="pt" style="margin:0;">Dashboard</h1>

    <?php if (tiene_rol(['Gerente', 'Administrador'])): ?>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="index.php?p=reportes&rango=hoy" style="padding:7px 12px; border-radius:6px; text-decoration:none; background:#e9ecef; color:#333; font-weight:600; font-size:13px;">Reporte Hoy</a>
            <a href="index.php?p=reportes&rango=semana" style="padding:7px 12px; border-radius:6px; text-decoration:none; background:#e9ecef; color:#333; font-weight:600; font-size:13px;">Reporte Semana</a>
            <a href="index.php?p=reportes&rango=mes" style="padding:7px 12px; border-radius:6px; text-decoration:none; background:var(--naranja, #d97706); color:#fff; font-weight:600; font-size:13px;">Reporte Mes / Imprimir</a>
        </div>
    <?php endif; ?>
</div>

<div class="grid">
    <div class="card oliva">
        <h3>Ventas hoy</h3>
        <div class="v"><?= (int)$ventas_hoy['total'] ?></div>
        <div class="s">transacciones pagadas</div>
    </div>
    <div class="card">
        <h3>Salteñas hoy</h3>
        <div class="v"><?= (int)$unidades_hoy['total'] ?></div>
        <div class="s">unidades vendidas</div>
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
        <div class="hd">Salteñas más vendidas hoy</div>
        <?php
        $top = mysqli_query($conexion,
            "SELECT nombre, unidades_vendidas
             FROM v_saltenas_vendidas_por_dia
             WHERE dia = '$hoy'
             ORDER BY unidades_vendidas DESC LIMIT 5");
        ?>
        <table>
            <tr><th>#</th><th>Salteña</th><th>Unidades</th></tr>
            <?php $i = 0; while ($f = mysqli_fetch_assoc($top)): $i++; ?>
                <tr><td><?= $i ?></td><td><?= h($f['nombre']) ?></td><td><?= (int)$f['unidades_vendidas'] ?></td></tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($top) == 0): ?>
                <tr><td colspan="3" style="text-align:center;color:#aaa">Sin ventas aún</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="panel">
        <div class="hd">Últimas ventas</div>
        <?php
        $recientes = mysqli_query($conexion,
            "SELECT v.id_venta, v.fecha, v.total, v.metodo, u.nombre AS vendedor
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