<?php
require_once __DIR__ . '/../conexion.php';

$rol = $_SESSION['rol'] ?? '';

// ---------- REGISTRAR CLIENTE ----------
if ($_POST && isset($_POST['registrar_cliente'])) {
    $nombre     = trim($_POST['nombre'] ?? '');
    $apellido_p = trim($_POST['apellido_p'] ?? '');
    $apellido_m = trim($_POST['apellido_m'] ?? '');
    $celular    = trim($_POST['celular'] ?? '');
    $direccion  = trim($_POST['direccion'] ?? '');

    if ($nombre !== '' && $apellido_p !== '') {
        mysqli_query($conexion,
            "INSERT INTO clientes (nombre, apellido_p, apellido_m, celular, direccion)
             VALUES ('$nombre', '$apellido_p', '$apellido_m', '$celular', '$direccion')");
        flash("Cliente registrado correctamente", 'success');
    } else {
        flash("Nombre y apellido paterno son obligatorios", 'danger');
    }
    header("Location: index.php?p=clientes");
    exit();
}

// ---------- MODIFICAR CLIENTE ----------
if ($_POST && isset($_POST['actualizar_cliente'])) {
    $id         = (int)($_POST['id'] ?? 0);
    $nombre     = trim($_POST['nombre'] ?? '');
    $apellido_p = trim($_POST['apellido_p'] ?? '');
    $apellido_m = trim($_POST['apellido_m'] ?? '');
    $celular    = trim($_POST['celular'] ?? '');
    $direccion  = trim($_POST['direccion'] ?? '');

    if ($id > 0 && $nombre !== '' && $apellido_p !== '') {
        mysqli_query($conexion,
            "UPDATE clientes SET nombre='$nombre', apellido_p='$apellido_p',
             apellido_m='$apellido_m', celular='$celular', direccion='$direccion'
             WHERE id_cliente=$id");
        flash("Cliente actualizado correctamente", 'success');
    } else {
        flash("Datos inválidos", 'danger');
    }
    header("Location: index.php?p=clientes");
    exit();
}

// ---------- ELIMINAR CLIENTE (solo Gerente y Administrador) ----------
if (isset($_GET['eliminar']) && in_array($rol, ['Gerente', 'Administrador'], true)) {
    $id = (int)($_GET['eliminar'] ?? 0);

    // Si no hay confirmar, mostrar pregunta de confirmación (sin JavaScript)
    if (!isset($_GET['confirmar']) && $id > 0) {
        $res = mysqli_query($conexion, "SELECT nombre, apellido_p FROM clientes WHERE id_cliente=$id LIMIT 1");
        $cliente_borrar = mysqli_fetch_assoc($res);
    }

    // Si confirma, borrar
    if (isset($_GET['confirmar']) && $id > 0) {
        mysqli_query($conexion, "DELETE FROM clientes WHERE id_cliente=$id");
        flash("Cliente eliminado correctamente", 'success');
        header("Location: index.php?p=clientes");
        exit();
    }
}

// Cargar datos para editar (si viene ?editar=ID)
$cliente_editar = null;
if (isset($_GET['editar'])) {
    $id = (int)($_GET['editar'] ?? 0);
    $res = mysqli_query($conexion, "SELECT * FROM clientes WHERE id_cliente=$id LIMIT 1");
    $cliente_editar = mysqli_fetch_assoc($res);
}

// Listar clientes
$clientes = mysqli_query($conexion, "SELECT * FROM clientes ORDER BY nombre");
?>
<h1 class="pt"> Clientes</h1>

<?php $fl = obtener_flash(); if ($fl): ?>
    <div class="alert <?= $fl['t'] ?>"><?= h($fl['m']) ?></div>
<?php endif; ?>

<!-- Pantalla de confirmación de eliminación (sin JavaScript) -->
<?php if (isset($cliente_borrar) && !isset($_GET['confirmar'])): ?>
<div class="panel">
    <div class="hd">⚠ Confirmar eliminación</div>
    <p>¿Estás seguro de eliminar al cliente <b><?= h($cliente_borrar['nombre'] . ' ' . $cliente_borrar['apellido_p']) ?></b>?</p>
    <div style="margin-top:16px">
        <a href="index.php?p=clientes&eliminar=<?= $cliente_borrar['id_cliente'] ?? 0 ?>&confirmar=1" class="btn btn-rojo">Sí, eliminar</a>
        <a href="index.php?p=clientes" class="btn g sm" style="margin-left:10px">Cancelar</a>
    </div>
</div>
<?php endif; ?>

<!-- Formulario registrar / editar -->
<div class="panel">
    <div class="hd"><?= $cliente_editar ? '✏ Modificar cliente' : '➕ Registrar cliente' ?></div>
    <form method="POST" action="index.php?p=clientes" class="form-inline">
        <?php if ($cliente_editar): ?>
            <input type="hidden" name="id" value="<?= $cliente_editar['id_cliente'] ?>">
        <?php endif; ?>
        <div>
            <label>Nombre</label>
            <input type="text" name="nombre" required
                   value="<?= h($cliente_editar['nombre'] ?? '') ?>">
        </div>
        <div>
            <label>Apellido Paterno</label>
            <input type="text" name="apellido_p" required
                   value="<?= h($cliente_editar['apellido_p'] ?? '') ?>">
        </div>
        <div>
            <label>Apellido Materno</label>
            <input type="text" name="apellido_m"
                   value="<?= h($cliente_editar['apellido_m'] ?? '') ?>">
        </div>
        <div>
            <label>Celular</label>
            <input type="text" name="celular"
                   value="<?= h($cliente_editar['celular'] ?? '') ?>">
        </div>
        <div>
            <label>Dirección</label>
            <input type="text" name="direccion"
                   value="<?= h($cliente_editar['direccion'] ?? '') ?>">
        </div>
        <?php if ($cliente_editar): ?>
            <button type="submit" name="actualizar_cliente" class="btn p">Guardar cambios</button>
            <a href="index.php?p=clientes" class="btn g sm" style="margin-left:8px">Cancelar</a>
        <?php else: ?>
            <button type="submit" name="registrar_cliente" class="btn p">Registrar cliente</button>
        <?php endif; ?>
    </form>
</div>

<!-- Listado de clientes -->
<div class="panel">
    <div class="hd">Lista de clientes</div>
    <table>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Celular</th>
            <th>Dirección</th>
            <th>Acciones</th>
        </tr>
        <?php while ($c = mysqli_fetch_assoc($clientes)): ?>
            <tr>
                <td><?= $c['id_cliente'] ?></td>
                <td><b><?= h($c['nombre'] . ' ' . $c['apellido_p'] . ' ' . $c['apellido_m']) ?></b></td>
                <td><?= h($c['celular'] ?? '-') ?></td>
                <td><?= h($c['direccion'] ?? '-') ?></td>
                <td style="white-space:nowrap">
                    <a href="index.php?p=clientes&editar=<?= $c['id_cliente'] ?>" class="btn btn-naranja sm">Editar</a>
                    <?php if (in_array($rol, ['Gerente', 'Administrador'], true)): ?>
                        <a href="index.php?p=clientes&eliminar=<?= $c['id_cliente'] ?>" class="btn btn-rojo sm">Eliminar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        <?php if (mysqli_num_rows($clientes) == 0): ?>
            <tr><td colspan="5" style="text-align:center;color:#aaa">Sin clientes registrados</td></tr>
        <?php endif; ?>
    </table>
</div>
