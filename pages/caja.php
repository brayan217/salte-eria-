<?php
// Cierre de caja - abrir y cerrar turno de caja (cajero, admin, gerente)
require_once __DIR__ . '/../conexion.php';

$id_usuario = $_SESSION['id_usuario'] ?? 0;
$nombre_usuario = $_SESSION['nombre'] ?? '';

// Abrir caja
if ($_POST && isset($_POST['abrir_caja'])) {
    $inicial = (float)($_POST['monto_inicial'] ?? 0);
    $ya_abierta = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT id_turno FROM caja_turnos WHERE estado = 'abierto' LIMIT 1"));
    if ($ya_abierta) {
        flash("Ya existe una caja abierta", 'danger');
    } else {
        mysqli_query($conexion, "INSERT INTO caja_turnos (id_usuario_apertura, monto_inicial, estado) VALUES ($id_usuario, $inicial, 'abierto')");
        flash("Caja abierta — Turno #" . mysqli_insert_id($conexion), 'success');
    }
    redirigir('index.php?p=caja');
}

// Cerrar caja
if ($_POST && isset($_POST['cerrar_caja'])) {
    $turno = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT id_turno, monto_inicial FROM caja_turnos WHERE estado = 'abierto' LIMIT 1"));
    if (!$turno) {
        flash("No hay caja abierta", 'danger');
    } else {
        $contado = (float)($_POST['efectivo_contado'] ?? 0);

        // Calcular efectivo de ventas del turno
        $res = mysqli_fetch_assoc(mysqli_query($conexion,
            "SELECT COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto END),0) AS efectivo_ventas,
                    COALESCE(SUM(CASE WHEN p.metodo = 'qr' THEN p.monto END),0) AS qr_ventas,
                    COALESCE(SUM(p.monto),0) AS total_vendido
             FROM ventas v
             JOIN ventas_pagos p ON p.id_venta = v.id_venta
             WHERE v.id_turno = " . $turno['id_turno'] . " AND v.estado = 'pagada'"));

        $efectivo_ventas = (float)$res['efectivo_ventas'];
        $qr_ventas       = (float)$res['qr_ventas'];
        $total_vendido   = (float)$res['total_vendido'];
        $esperado        = (float)$turno['monto_inicial'] + $efectivo_ventas;
        $diferencia      = $contado - $esperado;

        mysqli_query($conexion,
            "UPDATE caja_turnos
             SET fecha_cierre = NOW(),
                 total_efectivo = $efectivo_ventas,
                 total_qr = $qr_ventas,
                 efectivo_contado = $contado,
                 diferencia = $diferencia,
                 estado = 'cerrado'
             WHERE id_turno = " . $turno['id_turno']);

        flash("Caja cerrada. Diferencia: Bs " . money($diferencia) . " (" . ($diferencia == 0 ? "cuadrada ✔" : "descuadre ⚠") . ")", $diferencia == 0 ? 'success' : 'warning');
    }
    redirigir('index.php?p=caja');
}

$caja = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM caja_turnos WHERE estado = 'abierto' LIMIT 1"));

// Efectivo y QR REALES del turno abierto (en vivo, desde ventas_pagos).
// total_efectivo/total_qr de caja_turnos solo se llenan al CERRAR, por eso se calculan aparte.
$efectivo_ventas_actual = 0;
$qr_ventas_actual       = 0;
if ($caja) {
    $res_actual = mysqli_fetch_assoc(mysqli_query($conexion,
        "SELECT COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto END),0) AS efectivo,
                COALESCE(SUM(CASE WHEN p.metodo = 'qr' THEN p.monto END),0) AS qr
         FROM ventas v
         JOIN ventas_pagos p ON p.id_venta = v.id_venta
         WHERE v.id_turno = " . (int)$caja['id_turno'] . " AND v.estado = 'pagada'"));
    $efectivo_ventas_actual = (float)($res_actual['efectivo'] ?? 0);
    $qr_ventas_actual       = (float)($res_actual['qr'] ?? 0);
}

// Historial de turnos
$historial = mysqli_query($conexion,
    "SELECT c.id_turno, c.fecha_apertura, c.fecha_cierre, c.monto_inicial,
            c.total_efectivo, c.total_qr, c.efectivo_contado, c.diferencia, c.estado,
            u.nombre AS quien_abrio
     FROM caja_turnos c
     JOIN usuarios u ON u.id_usuario = c.id_usuario_apertura
     ORDER BY c.id_turno DESC");
?>
<h1 class="pt">Apertura y cierre de caja</h1>

<?php if (!$caja): ?>
<div class="panel">
    <div class="hd"> Abrir caja</div>
    <form method="POST" action="index.php?p=caja" class="form-inline">
        <div>
            <label>Monto inicial para cambio (Bs)</label>
            <input type="number" step="0.01" name="monto_inicial" value="100.00" min="0" required>
        </div>
        <button type="submit" name="abrir_caja" class="btn o">Abrir caja</button>
    </form>
</div>
<?php else: ?>
<div class="grid">
    <div class="card">
        <h3>Turno</h3>
        <div class="v">#<?= $caja['id_turno'] ?></div>
        <div class="s">desde <?= date('d/m/Y H:i', strtotime($caja['fecha_apertura'])) ?></div>
    </div>
    <div class="card dorado">
        <h3>Efectivo en ventas</h3>
        <div class="v">Bs <?= money($efectivo_ventas_actual) ?></div>
    </div>
    <div class="card oliva">
        <h3>QR en ventas</h3>
        <div class="v">Bs <?= money($qr_ventas_actual) ?></div>
    </div>
    <div class="card">
        <h3>Efectivo esperado</h3>
        <div class="v">Bs <?= money((float)($caja['monto_inicial'] ?? 0) + $efectivo_ventas_actual) ?></div>
        <div class="s">inicial + efectivo</div>
    </div>
</div>

<div class="panel">
    <div class="hd"> Cerrar caja</div>
    <form method="POST" action="index.php?p=caja" class="form-inline">
        <div>
            <label>Efectivo contado en el cajón (Bs)</label>
            <input type="number" step="0.01" name="efectivo_contado" value="<?= money((float)($caja['monto_inicial'] ?? 0) + $efectivo_ventas_actual) ?>" required>
        </div>
        <button type="submit" name="cerrar_caja" class="btn r">Cerrar caja</button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <div class="hd">Historial de turnos</div>
    <table>
        <tr><th>#</th><th>Apertura</th><th>Cierre</th><th>Inicial</th><th>Efectivo</th><th>QR</th><th>Total</th><th>Contado</th><th>Diferencia</th><th>Estado</th></tr>
        <?php while ($t = mysqli_fetch_assoc($historial)): ?>
            <tr>
                <td><?= $t['id_turno'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($t['fecha_apertura'])) ?></td>
                <td><?= $t['fecha_cierre'] ? date('d/m/Y H:i', strtotime($t['fecha_cierre'])) : '—' ?></td>
                <td><?= money($t['monto_inicial']) ?></td>
                <td><?= money($t['total_efectivo'] ?? 0) ?></td>
                <td><?= money($t['total_qr'] ?? 0) ?></td>
                <td><b><?= money(($t['total_efectivo'] ?? 0) + ($t['total_qr'] ?? 0)) ?></b></td>
                <td><?= money($t['efectivo_contado'] ?? 0) ?></td>
                <td style="color:<?= ($t['diferencia'] ?? 0) == 0 ? 'var(--oliva)' : 'var(--rojo)' ?>;font-weight:700"><?= money($t['diferencia'] ?? 0) ?></td>
                <td><span class="pill" style="background:<?= $t['estado'] === 'cerrado' ? 'var(--oliva)' : 'var(--naranja)' ?>"><?= h($t['estado']) ?></span></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
