<?php
// Horneadas - registrar tandas de horneado (cocinero, cajero, admin, gerente)
require_once __DIR__ . '/../conexion.php';

if ($_POST && isset($_POST['registrar_horneada'])) {
    $id_saltena = (int)($_POST['id_saltena'] ?? 0);
    $cantidad   = (int)($_POST['cantidad'] ?? 0);
    $turno      = mysqli_real_escape_string($conexion, $_POST['turno'] ?? 'mañana');
    $id_usuario = $_SESSION['id_usuario'] ?? 0;

    if ($id_saltena > 0 && $cantidad > 0) {
        mysqli_query($conexion, "INSERT INTO horneadas (id_saltena, id_usuario, cantidad, turno) VALUES ($id_saltena, $id_usuario, $cantidad, '$turno')");
        flash("Tanda registrada: +$cantidad salteña(s)", 'success');
    } else {
        flash("Datos inválidos", 'danger');
    }
    redirigir('index.php?p=horneadas');
}

$saltenas = mysqli_query($conexion, "SELECT id_saltena, nombre FROM tipos_saltena WHERE estado = 'activo' ORDER BY nombre");
$horneadas = mysqli_query($conexion,
    "SELECT h.id_horneada, h.fecha_hornea, h.cantidad, h.turno, t.nombre AS saltena, u.nombre AS quien
     FROM horneadas h
     JOIN tipos_saltena t ON t.id_saltena = h.id_saltena
     JOIN usuarios u ON u.id_usuario = h.id_usuario
     ORDER BY h.fecha_hornea DESC, h.id_horneada DESC");
?>
<h1 class="pt">🔥 Registro de horneadas</h1>

<div class="panel">
    <div class="hd">Nueva tanda de horneado</div>
    <form method="POST" action="index.php?p=horneadas" class="form-inline">
        <div>
            <label>Salteña</label>
            <select name="id_saltena" required>
                <option value="">Seleccione</option>
                <?php while ($s = mysqli_fetch_assoc($saltenas)): ?>
                    <option value="<?= $s['id_saltena'] ?>"><?= h($s['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label>Cantidad</label>
            <input type="number" name="cantidad" min="1" value="30" required>
        </div>
        <div>
            <label>Turno</label>
            <select name="turno">
                <option value="mañana">Mañana</option>
                <option value="tarde">Tarde</option>
            </select>
        </div>
        <button type="submit" name="registrar_horneada" class="btn p">➕ Registrar horneada</button>
    </form>
</div>

<div class="panel">
    <div class="hd">Bitácora de horneadas</div>
    <table>
        <tr><th>Fecha</th><th>Salteña</th><th>Cantidad</th><th>Turno</th><th>Registró</th></tr>
        <?php while ($h = mysqli_fetch_assoc($horneadas)): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($h['fecha_hornea'])) ?></td>
                <td><?= h($h['saltena']) ?></td>
                <td><?= (int)$h['cantidad'] ?></td>
                <td><?= h($h['turno']) ?></td>
                <td><?= h($h['quien']) ?></td>
            </tr>
        <?php endwhile; ?>
        <?php if (mysqli_num_rows($horneadas) == 0): ?>
            <tr><td colspan="5" style="text-align:center;color:#aaa">Sin horneadas registradas</td></tr>
        <?php endif; ?>
    </table>
</div>
