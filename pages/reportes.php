<?php
// Reportes - solo Gerente y Administrador
date_default_timezone_set('America/La_Paz');
require_once __DIR__ . '/../conexion.php';

if (!tiene_rol(['Gerente', 'Administrador'])) {
    flash("Acceso denegado: solo Gerente y Administrador pueden ver reportes", 'danger');
    redirigir('index.php');
}

// Filtro por rango de fechas (Hoy, Semana, Mes)
$rango = $_GET['rango'] ?? 'hoy';
if (!in_array($rango, ['hoy', 'semana', 'mes'])) {
    $rango = 'hoy';
}

$fecha_fin = date('Y-m-d');
if ($rango === 'semana') {
    $fecha_inicio = date('Y-m-d', strtotime('-6 days'));
    $titulo_rango = 'Últimos 7 días (Semana)';
} elseif ($rango === 'mes') {
    $fecha_inicio = date('Y-m-01');
    $titulo_rango = 'Mes actual';
} else {
    $fecha_inicio = $fecha_fin;
    $titulo_rango = 'Hoy (' . date('d/m/Y') . ')';
}

// Resumen de ventas en el rango seleccionado
$resumen = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COUNT(*) AS total_ventas, COALESCE(SUM(total),0) AS monto_total
     FROM ventas
     WHERE DATE(fecha) BETWEEN '$fecha_inicio' AND '$fecha_fin'
       AND estado = 'pagada'"));

$ticket_promedio = $resumen['total_ventas'] > 0 ? $resumen['monto_total'] / $resumen['total_ventas'] : 0;

// Total mermas en el rango seleccionado
$total_mermas = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COALESCE(SUM(cantidad),0) AS total
     FROM mermas
     WHERE DATE(fecha) BETWEEN '$fecha_inicio' AND '$fecha_fin'"));

// Consulta a vista 1: v_saltenas_vendidas_por_dia (filtrada por fecha)
$por_dia = mysqli_query($conexion,
    "SELECT dia, nombre, unidades_vendidas, ingresos
     FROM v_saltenas_vendidas_por_dia
     WHERE dia BETWEEN '$fecha_inicio' AND '$fecha_fin'
     ORDER BY dia DESC, unidades_vendidas DESC");

// Consulta a vista 2: v_saltenas_mas_vendidas (ranking general)
$ranking = mysqli_query($conexion,
    "SELECT nombre, categoria, unidades_vendidas, ingresos
     FROM v_saltenas_mas_vendidas
     ORDER BY unidades_vendidas DESC");

// Detalle de ventas en el rango seleccionado
$ventas = mysqli_query($conexion,
    "SELECT v.id_venta, v.fecha, v.total,
            (SELECT p.metodo FROM ventas_pagos p WHERE p.id_venta = v.id_venta LIMIT 1) AS metodo,
            c.nombre AS cliente_nombre, c.apellido_p AS cliente_apellido,
            u.nombre AS vendedor
     FROM ventas v
     LEFT JOIN clientes c ON c.id_cliente = v.id_cliente
     JOIN usuarios u ON u.id_usuario = v.id_usuario
     WHERE v.estado = 'pagada'
       AND DATE(v.fecha) BETWEEN '$fecha_inicio' AND '$fecha_fin'
     ORDER BY v.fecha DESC");
?>

<style>
.barra-filtros {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}
.filtros-btn {
    display: flex;
    gap: 8px;
}
.btn-filtro {
    padding: 8px 14px;
    border-radius: 6px;
    text-decoration: none;
    background: #e9ecef;
    color: #333;
    font-weight: 600;
    font-size: 14px;
}
.btn-filtro.activo {
    background: var(--naranja, #d97706);
    color: #fff;
}
.btn-imprimir {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    background: #1f2937;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
}
.solo-impresion {
    display: none;
}

/* Vista de impresión limpia */
@media print {
    header, nav, footer, .navbar, .topbar, .no-print {
        display: none !important;
    }
    body {
        background: #fff !important;
        color: #000 !important;
        margin: 0;
        padding: 10px;
    }
    .solo-impresion {
        display: block;
        margin-bottom: 16px;
        border-bottom: 2px solid #000;
        padding-bottom: 8px;
    }
    .grid {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 10px !important;
    }
    .split {
        display: block !important;
    }
    .card, .panel {
        box-shadow: none !important;
        border: 1px solid #ccc !important;
        break-inside: avoid;
        margin-bottom: 14px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 6px;
    }
}
</style>

<div class="solo-impresion">
    <h2 style="margin:0;">Salteñería - Balance de Reportes</h2>
    <p style="margin:4px 0 0 0;">
        Periodo: <b><?= h($titulo_rango) ?></b> (<?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?>)
        | Generado el: <?= date('d/m/Y H:i') ?>
    </p>
</div>

<div class="barra-filtros no-print">
    <h1 class="pt" style="margin:0;">Reportes — <?= h($titulo_rango) ?></h1>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <div class="filtros-btn">
            <a href="index.php?p=reportes&rango=hoy" class="btn-filtro <?= $rango === 'hoy' ? 'activo' : '' ?>">Hoy</a>
            <a href="index.php?p=reportes&rango=semana" class="btn-filtro <?= $rango === 'semana' ? 'activo' : '' ?>">Semana</a>
            <a href="index.php?p=reportes&rango=mes" class="btn-filtro <?= $rango === 'mes' ? 'activo' : '' ?>">Mes</a>
        </div>
        <button type="button" class="btn-imprimir" onclick="window.print()">Imprimir Balance</button>
    </div>
</div>

<div class="grid">
    <div class="card dorado">
        <h3>Ingresos (<?= h($rango) ?>)</h3>
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
        <h3>Mermas (<?= h($rango) ?>)</h3>
        <div class="v"><?= (int)$total_mermas['total'] ?></div>
        <div class="s">unidades perdidas</div>
    </div>
</div>

<div class="split">
    <div class="panel">
        <div class="hd">Salteñas vendidas por día (<?= h($titulo_rango) ?>)</div>
        <table>
            <tr><th>Día</th><th>Salteña</th><th>Unidades</th><th>Ingresos</th></tr>
            <?php while ($d = mysqli_fetch_assoc($por_dia)): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($d['dia'])) ?></td>
                    <td><b><?= h($d['nombre']) ?></b></td>
                    <td><?= (int)$d['unidades_vendidas'] ?></td>
                    <td>Bs <?= money($d['ingresos']) ?></td>
                </tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($por_dia) == 0): ?>
                <tr><td colspan="4" style="text-align:center;color:#aaa">Sin ventas en este periodo</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="panel">
        <div class="hd">Salteñas más vendidas (ranking histórico)</div>
        <table>
            <tr><th>Salteña</th><th>Categoría</th><th>Unidades</th><th>Ingresos</th></tr>
            <?php while ($r = mysqli_fetch_assoc($ranking)): ?>
                <tr>
                    <td><b><?= h($r['nombre']) ?></b></td>
                    <td><?= h($r['categoria']) ?></td>
                    <td><?= (int)$r['unidades_vendidas'] ?></td>
                    <td>Bs <?= money($r['ingresos']) ?></td>
                </tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($ranking) == 0): ?>
                <tr><td colspan="4" style="text-align:center;color:#aaa">Sin datos</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="panel" style="margin-top:16px;">
    <div class="hd">Detalle de ventas (<?= h($titulo_rango) ?>)</div>
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
            <tr><td colspan="6" style="text-align:center;color:#aaa">Sin ventas en este periodo</td></tr>
        <?php endif; ?>
    </table>
</div>