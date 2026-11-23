<?php
// Página principal - Dashboard y despachador de secciones
session_start();
require_once 'conexion.php';
require_once 'funciones.php';

// Si no hay sesión, ir al login
if (!isset($_SESSION['id_usuario'])) {
    redirigir('login.php');
}

$rol = $_SESSION['rol'] ?? '';
$nombre = $_SESSION['nombre'] ?? '';

// Menú por rol: [icono, etiqueta, roles_permitidos]
$MENU = [
    'dashboard' => ['', 'Dashboard', ['Gerente','Administrador','Cajero','Cocinero','Mesero']],
    'stock'     => ['', 'Stock', ['Gerente','Cajero','Cocinero','Mesero']],
    'horneadas' => ['', 'Horneadas', ['Gerente','Cajero','Cocinero']],
    'pedidos'   => ['', 'Pedidos', ['Gerente','Administrador','Cajero','Mesero']],
    'pos'       => ['', 'POS / Caja', ['Gerente','Cajero']],
    'caja'      => ['', 'Cierre de Caja', ['Gerente','Cajero']],
    'reportes'  => ['', 'Reportes', ['Gerente','Administrador']],
    'usuarios'  => ['', 'Usuarios', ['Gerente','Administrador']],
    'clientes'  => ['', 'Clientes', ['Gerente','Cajero']],
];

$pagina = $_GET['p'] ?? 'dashboard';

// Páginas accedidas por enlace (no se muestran en el menú de navegación)
$PAGINAS_EXTRA = [
    'pedido_editar' => ['Gerente', 'Administrador', 'Cajero', 'Mesero'],
];

// Si la página solicitada no existe o no tiene permiso, ir al dashboard
if (isset($MENU[$pagina])) {
    if (!tiene_rol($MENU[$pagina][2])) {
        $pagina = 'dashboard';
    }
} elseif (isset($PAGINAS_EXTRA[$pagina])) {
    if (!tiene_rol($PAGINAS_EXTRA[$pagina])) {
        $pagina = 'dashboard';
    }
} else {
    $pagina = 'dashboard';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>El Sin Aceituna - <?= h($pagina) ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php $flash = obtener_flash(); if ($flash): ?>
    <div class="wrap">
        <div class="alert <?= $flash['t'] ?>"><?= h($flash['m']) ?></div>
    </div>
<?php endif; ?>

<!-- BARRA DE NAVEGACIÓN -->
<nav class="nav">
    <div class="brand">
        <span class="dot"></span> EL SIN ACEITUNA
    </div>
    <div class="menu">
        <?php foreach ($MENU as $clave => $datos): [$icono, $etiqueta, $roles] = $datos; ?>
            <?php if (tiene_rol($roles)): ?>
                <a href="index.php?p=<?= h($clave) ?>" class="<?= $pagina === $clave ? 'on' : '' ?>">
                    <?= h($icono) ?> <?= h($etiqueta) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="userbox">
        <span class="badge"><?= h($rol) ?></span>
        <span><?= h($nombre) ?></span>
        <a href="logout.php" class="btn r sm">Salir</a>
    </div>
</nav>

<!-- CONTENIDO -->
<div class="wrap">
    <?php
    switch ($pagina):
        case 'dashboard':     include 'pages/dashboard.php'; break;
        case 'stock':         include 'pages/stock.php'; break;
        case 'horneadas':     include 'pages/horneadas.php'; break;
        case 'pedidos':       include 'pages/pedidos.php'; break;
        case 'pos':           include 'pages/pos.php'; break;
        case 'caja':          include 'pages/caja.php'; break;
        case 'reportes':      include 'pages/reportes.php'; break;
        case 'usuarios':      include 'pages/usuarios.php'; break;
        case 'clientes':      include 'pages/clientes.php'; break;
        case 'pedido_editar': include 'pages/pedido_editar.php'; break;
        default:              include 'pages/dashboard.php'; break;
    endswitch;
    ?>
</div>

<footer>© 2026 Salteñería "El Sin Aceituna" · Sistema de Ventas · <a href="logout.php">Cerrar sesión</a></footer>

</body>
</html>