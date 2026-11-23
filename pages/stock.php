<?php
// Stock - ver salteñas y registrar merma
require_once __DIR__ . '/../conexion.php';

// Registrar merma
if ($_POST && isset($_POST['registrar_merma'])) {
    $id_saltena = (int)($_POST['id_saltena'] ?? 0);
    $cantidad   = (int)($_POST['cantidad'] ?? 0);
    $motivo     = mysqli_real_escape_string($conexion, $_POST['motivo'] ?? 'otro');
    $id_usuario = $_SESSION['id_usuario'] ?? 0;

    if ($id_saltena > 0 && $cantidad > 0) {
        mysqli_query($conexion, "INSERT INTO mermas (id_saltena, id_usuario, cantidad, motivo) VALUES ($id_saltena, $id_usuario, $cantidad, '$motivo')");
        flash("Merma registrada: -$cantidad unidad(es)", 'warning');
    } else {
        flash("Datos inválidos", 'danger');
    }
    header("Location: index.php?p=stock");
    exit();
}

// Listar salteñas usando la vista oficial de stock (horneadas - vendidas - mermas del día)
$saltenas = mysqli_query($conexion,
    "SELECT t.id_saltena, t.nombre, t.precio, c.nombre AS categoria,
            COALESCE(v.stock_disponible, 0) AS stock_disponible
     FROM tipos_saltena t
     JOIN categorias_saltena c ON c.id_categoria = t.id_categoria
     LEFT JOIN v_stock_hoy v ON v.id_saltena = t.id_saltena
     WHERE t.estado = 'activo'
     ORDER BY t.nombre");

// Mermas de hoy
$hoy = date('Y-m-d');
$mermas = mysqli_query($conexion,
    "SELECT m.id_merma, m.cantidad, m.motivo, m.fecha, t.nombre AS saltena, u.nombre AS quien
     FROM mermas m
     JOIN tipos_saltena t ON t.id_saltena = m.id_saltena
     JOIN usuarios u ON u.id_usuario = m.id_usuario
     WHERE DATE(m.fecha) = '$hoy'
     ORDER BY m.fecha DESC");
?>
<h1 class="pt"> Salteñas y merma</h1>

<div class="tiles">
    <?php while ($s = mysqli_fetch_assoc($saltenas)): ?>
        <div class="tile <?= (int)$s['stock_disponible'] <= 0 ? 'zero' : '' ?>">
            <div class="em" style="font-size:26px; font-weight:800; color:<?= (int)$s['stock_disponible'] <= 0 ? 'var(--rojo)' : 'var(--naranja)' ?>;">
                <?= (int)$s['stock_disponible'] ?>
            </div>
            <div class="st" style="font-weight:700;">unidades disponibles</div>
            <div class="nm"><?= h($s['nombre']) ?></div>
            <div class="pr">Bs <?= money($s['precio']) ?></div>
            <div class="st"><?= h($s['categoria']) ?></div>
        </div>
    <?php endwhile; ?>
</div>

<div class="panel">
    <div class="hd">🗑 Registrar merma (quemada / caída / cortesía)</div>
    <form method="POST" action="index.php?p=stock" class="form-inline">
        <div>
            <label>Salteña</label>
            <select name="id_saltena" required>
                <option value="">Seleccione</option>
                <?php
                $saltenas2 = mysqli_query($conexion, "SELECT id_saltena, nombre FROM tipos_saltena WHERE estado = 'activo' ORDER BY nombre");
                while ($s2 = mysqli_fetch_assoc($saltenas2)):
                ?>
                    <option value="<?= $s2['id_saltena'] ?>"><?= h($s2['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label>Cantidad</label>
            <input type="number" name="cantidad" min="1" value="1" required>
        </div>
        <div>
            <label>Motivo</label>
            <select name="motivo">
                <option value="quemada">Quemada</option>
                <option value="caida">Caída</option>
                <option value="cortesia">Cortesía</option>
                <option value="otro">Otro</option>
            </select>
        </div>
        <button type="submit" name="registrar_merma" class="btn r">Registrar merma</button>
    </form>
</div>

<?php if (mysqli_num_rows($mermas) > 0): ?>
<div class="panel">
    <div class="hd">Mermas de hoy</div>
    <table>
        <tr><th>Hora</th><th>Salteña</th><th>Cantidad</th><th>Motivo</th><th>Registró</th></tr>
        <?php while ($m = mysqli_fetch_assoc($mermas)): ?>
            <tr>
                <td><?= date('H:i', strtotime($m['fecha'])) ?></td>
                <td><?= h($m['saltena']) ?></td>
                <td><?= (int)$m['cantidad'] ?></td>
                <td><?= h($m['motivo']) ?></td>
                <td><?= h($m['quien']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php endif; ?>
