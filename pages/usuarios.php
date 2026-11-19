<?php
require_once __DIR__ . '/../conexion.php';

$rol = $_SESSION['rol'] ?? '';

// Solo Administrador y Gerente pueden acceder a esta página
if (!in_array($rol, ['Administrador', 'Gerente'], true)) {
    flash("Acceso denegado: solo Administrador y Gerente pueden gestionar usuarios", 'danger');
    header("Location: index.php");
    exit();
}

// ---------- REGISTRAR USUARIO ----------
if ($_POST && isset($_POST['registrar_usuario'])) {
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido_p'] ?? '');
    $usuario  = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $id_rol   = (int)($_POST['id_rol'] ?? 0);

    if ($nombre && $apellido && $usuario && $password && $id_rol > 0) {
        // Gerente no puede crear Gerente ni Administrador
        if ($rol === 'Gerente' && in_array($id_rol, [1, 2], true)) {
            flash("No tienes permiso para crear ese rol", 'danger');
        } else {
            $existe = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT id_usuario FROM usuarios WHERE usuario = '$usuario' LIMIT 1"));
            if ($existe) {
                flash("El usuario ya existe", 'danger');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conexion, "INSERT INTO usuarios (nombre, apellido_p, usuario, password, id_rol) VALUES ('$nombre', '$apellido', '$usuario', '$hash', $id_rol)");
                $rol_nombre = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT nombre FROM roles WHERE id_rol = $id_rol"));
                flash("Usuario creado: $usuario / rol: " . h($rol_nombre['nombre']), 'success');
            }
        }
    } else {
        flash("Todos los campos son obligatorios", 'danger');
    }
    header("Location: index.php?p=usuarios");
    exit();
}

// ---------- MODIFICAR USUARIO (solo Administrador) ----------
if ($_POST && isset($_POST['actualizar_usuario']) && $rol === 'Administrador') {
    $id       = (int)($_POST['id'] ?? 0);
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido_p'] ?? '');
    $usuario  = trim($_POST['usuario'] ?? '');
    $id_rol   = (int)($_POST['id_rol'] ?? 0);

    if ($id > 0 && $nombre && $apellido && $usuario && $id_rol > 0) {
        mysqli_query($conexion, "UPDATE usuarios SET nombre='$nombre', apellido_p='$apellido', usuario='$usuario', id_rol=$id_rol WHERE id_usuario=$id");
        flash("Usuario actualizado correctamente", 'success');
    } else {
        flash("Datos inválidos", 'danger');
    }
    header("Location: index.php?p=usuarios");
    exit();
}

// ---------- ELIMINAR USUARIO (solo Administrador) ----------
if (isset($_GET['eliminar']) && $rol === 'Administrador') {
    $id = (int)($_GET['eliminar'] ?? 0);

    if (!isset($_GET['confirmar']) && $id > 0) {
        $res = mysqli_query($conexion, "SELECT nombre, usuario FROM usuarios WHERE id_usuario=$id LIMIT 1");
        $usuario_borrar = mysqli_fetch_assoc($res);
    }

    if (isset($_GET['confirmar']) && $id > 0) {
        // No permitir eliminarse a sí mismo
        if ($id == $_SESSION['id_usuario']) {
            flash("No puedes eliminar tu propio usuario", 'danger');
        } else {
            mysqli_query($conexion, "DELETE FROM usuarios WHERE id_usuario=$id");
            flash("Usuario eliminado correctamente", 'success');
        }
        header("Location: index.php?p=usuarios");
        exit();
    }
}

// Cargar datos para editar (solo Administrador)
$usuario_editar = null;
if ($rol === 'Administrador' && isset($_GET['editar'])) {
    $id = (int)($_GET['editar'] ?? 0);
    $res = mysqli_query($conexion, "SELECT * FROM usuarios WHERE id_usuario=$id LIMIT 1");
    $usuario_editar = mysqli_fetch_assoc($res);
}

// Listar roles para el select
$roles = mysqli_query($conexion, "SELECT id_rol, nombre FROM roles ORDER BY nombre");

// Listar usuarios (solo Administrador ve la lista completa)
$usuarios = null;
if ($rol === 'Administrador') {
    $usuarios = mysqli_query($conexion,
        "SELECT u.id_usuario, u.nombre, u.apellido_p, u.usuario, u.estado, r.nombre AS rol
         FROM usuarios u
         JOIN roles r ON r.id_rol = u.id_rol
         ORDER BY u.id_usuario");
}
?>
<h1 class="pt">👥 Gestión de personal</h1>

<?php $fl = obtener_flash(); if ($fl): ?>
    <div class="alert <?= $fl['t'] ?>"><?= h($fl['m']) ?></div>
<?php endif; ?>

<!-- Pantalla de confirmación de eliminación (solo Administrador) -->
<?php if ($rol === 'Administrador' && isset($usuario_borrar) && !isset($_GET['confirmar'])): ?>
<div class="panel">
    <div class="hd">⚠ Confirmar eliminación</div>
    <p>¿Estás seguro de eliminar al usuario <b><?= h($usuario_borrar['nombre'] . ' ' . $usuario_borrar['usuario']) ?></b>?</p>
    <div style="margin-top:16px">
        <a href="index.php?p=usuarios&eliminar=<?= $usuario_borrar['id_usuario'] ?? 0 ?>&confirmar=1" class="btn btn-rojo">Sí, eliminar</a>
        <a href="index.php?p=usuarios" class="btn g sm" style="margin-left:10px">Cancelar</a>
    </div>
</div>
<?php endif; ?>

<?php if ($rol === 'Administrador'): ?>
<!-- Administrador: puede registrar, modificar y eliminar -->
<div class="panel">
    <div class="hd">➕ Nuevo usuario</div>
    <form method="POST" action="index.php?p=usuarios" class="form-inline">
        <?php if ($usuario_editar): ?>
            <input type="hidden" name="id" value="<?= $usuario_editar['id_usuario'] ?>">
        <?php endif; ?>
        <div>
            <label>Nombre completo</label>
            <input type="text" name="nombre" required
                   value="<?= h($usuario_editar['nombre'] ?? '') ?>">
        </div>
        <div>
            <label>Apellido paterno</label>
            <input type="text" name="apellido_p" required
                   value="<?= h($usuario_editar['apellido_p'] ?? '') ?>">
        </div>
        <div>
            <label>Usuario (login)</label>
            <input type="text" name="usuario" required
                   value="<?= h($usuario_editar['usuario'] ?? '') ?>">
        </div>
        <div>
            <label>Contraseña</label>
            <input type="password" name="password" required
                   <?= $usuario_editar ? '' : 'required' ?>>
        </div>
        <div>
            <label>Rol</label>
            <select name="id_rol" required>
                <option value="">Seleccione</option>
                <?php
                mysqli_data_seek($roles, 0);
                while ($r = mysqli_fetch_assoc($roles)):
                ?>
                    <option value="<?= $r['id_rol'] ?>" <?= ($usuario_editar && $usuario_editar['id_rol'] == $r['id_rol']) ? 'selected' : '' ?>>
                        <?= h($r['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php if ($usuario_editar): ?>
            <button type="submit" name="actualizar_usuario" class="btn p">Guardar cambios</button>
            <a href="index.php?p=usuarios" class="btn g sm" style="margin-left:8px">Cancelar</a>
        <?php else: ?>
            <button type="submit" name="registrar_usuario" class="btn p">Crear</button>
        <?php endif; ?>
    </form>
</div>

<div class="panel">
    <div class="hd">Personal registrado</div>
    <table>
        <tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
        <?php while ($u = mysqli_fetch_assoc($usuarios)): ?>
            <tr>
                <td><?= $u['id_usuario'] ?></td>
                <td><b><?= h($u['nombre'] . ' ' . $u['apellido_p']) ?></b></td>
                <td><?= h($u['usuario']) ?></td>
                <td><span class="pill" style="background:var(--naranja)"><?= h($u['rol']) ?></span></td>
                <td><?= h($u['estado']) ?></td>
                <td style="white-space:nowrap">
                    <?php if ($u['id_usuario'] != $_SESSION['id_usuario']): ?>
                        <a href="index.php?p=usuarios&editar=<?= $u['id_usuario'] ?>" class="btn btn-naranja sm">Editar</a>
                        <a href="index.php?p=usuarios&eliminar=<?= $u['id_usuario'] ?>" class="btn btn-rojo sm">Eliminar</a>
                    <?php else: ?>
                        <span style="color:#999">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        <?php if (mysqli_num_rows($usuarios) == 0): ?>
            <tr><td colspan="6" style="text-align:center;color:#aaa">Sin usuarios registrados</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php elseif ($rol === 'Gerente'): ?>
<!-- Gerente: solo puede registrar Cocinero, Cajero, Mesero -->
<div class="panel">
    <div class="hd">➕ Registrar personal (solo Cocinero, Cajero, Mesero)</div>
    <form method="POST" action="index.php?p=usuarios" class="form-inline">
        <div>
            <label>Nombre completo</label>
            <input type="text" name="nombre" required>
        </div>
        <div>
            <label>Apellido paterno</label>
            <input type="text" name="apellido_p" required>
        </div>
        <div>
            <label>Usuario (login)</label>
            <input type="text" name="usuario" required>
        </div>
        <div>
            <label>Contraseña</label>
            <input type="password" name="password" required>
        </div>
        <div>
            <label>Rol</label>
            <select name="id_rol" required>
                <option value="">Seleccione</option>
                <?php
                mysqli_data_seek($roles, 0);
                while ($r = mysqli_fetch_assoc($roles)):
                    // Gerente solo puede crear estos roles
                    if (in_array($r['nombre'], ['Cocinero', 'Cajero', 'Mesero'], true)):
                ?>
                    <option value="<?= $r['id_rol'] ?>"><?= h($r['nombre']) ?></option>
                <?php endif; endwhile; ?>
            </select>
        </div>
        <button type="submit" name="registrar_usuario" class="btn p">Crear</button>
    </form>
    <p class="nota" style="margin-top:10px">Como Gerente solo puedes registrar Cocinero, Cajero o Mesero. No puedes modificar ni eliminar usuarios.</p>
</div>
<?php endif; ?>
