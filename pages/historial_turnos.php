<?php
require_once __DIR__ . '/../conexion.php';

// Verificar acceso
if (!tiene_rol(['Gerente', 'Administrador', 'Cajero'])) {
    flash("Acceso denegado: solo Gerente, Administrador y Cajero pueden ver este reporte", 'danger');
    redirigir('index.php');
}

$id_usuario = $_SESSION['id_usuario'] ?? 0;
$rol_usuario = $_SESSION['rol'] ?? '';

// Parámetros de filtro
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$id_turno_filtro = (int)($_GET['id_turno'] ?? 0);
$id_usuario_filtro = (int)($_GET['id_usuario'] ?? 0);

// Usuarios para el filtro (solo cajeros, admin y gerente que han abierto caja)
$usuarios_caja = mysqli_query(
    $conexion,
    "SELECT DISTINCT u.id_usuario, u.nombre, r.nombre AS rol
     FROM usuarios u
     JOIN caja_turnos c ON c.id_usuario_apertura = u.id_usuario
     JOIN roles r ON r.id_rol = u.id_rol
     WHERE u.estado = 'activo'
     ORDER BY u.nombre"
);

// Construir consulta base para turnos
$where_turnos = "WHERE c.estado = 'cerrado'";

if ($fecha_desde) {
    $fecha_desde_esc = mysqli_real_escape_string($conexion, $fecha_desde);
    $where_turnos .= " AND DATE(c.fecha_cierre) >= '$fecha_desde_esc'";
}
if ($fecha_hasta) {
    $fecha_hasta_esc = mysqli_real_escape_string($conexion, $fecha_hasta);
    $where_turnos .= " AND DATE(c.fecha_cierre) <= '$fecha_hasta_esc'";
}
if ($id_turno_filtro > 0) {
    $where_turnos .= " AND c.id_turno = $id_turno_filtro";
}
if ($id_usuario_filtro > 0) {
    $where_turnos .= " AND c.id_usuario_apertura = $id_usuario_filtro";
}

// Si es Cajero (no admin/gerente), solo ver sus propios turnos
if ($rol_usuario === 'Cajero') {
    $where_turnos .= " AND c.id_usuario_apertura = $id_usuario";
}

$where_turnos .= " ORDER BY c.id_turno DESC";

// Consulta para obtener turnos cerrados
$turnos_query = "SELECT c.id_turno, c.fecha_apertura, c.fecha_cierre, 
        c.monto_inicial, c.total_efectivo, c.total_qr, c.efectivo_contado, 
        c.diferencia, c.estado,
        u.nombre AS quien_abrio, r.nombre AS rol_abrio
     FROM caja_turnos c
     JOIN usuarios u ON u.id_usuario = c.id_usuario_apertura
     JOIN roles r ON r.id_rol = u.id_rol
     $where_turnos";

$historial = mysqli_query($conexion, $turnos_query);

// Consulta para obtener detalle por sabor de turno_sobrantes
function get_turno_sobrantes($conexion, $id_turno)
{
    $query = "SELECT 
        ts.id_saltena,
        t.nombre AS sabor,
        COALESCE(ts.horneadas, 0) AS horneadas,
        COALESCE(ts.vendidas, 0) AS vendidas,
        COALESCE(ts.mermas, 0) AS mermas,
        COALESCE(ts.sobrantes, 0) AS sobrantes
    FROM turno_sobrantes ts
    JOIN tipos_saltena t ON ts.id_saltena = t.id_saltena
    WHERE ts.id_turno = $id_turno
    ORDER BY t.nombre";

    $result = mysqli_query($conexion, $query);
    $sobrantes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $sobrantes[] = $row;
    }
    return $sobrantes;
}

// Resumen general
$where_resumen = "WHERE c.estado = 'cerrado'";
if ($rol_usuario === 'Cajero') {
    $where_resumen .= " AND c.id_usuario_apertura = $id_usuario";
}

$resumen_query = "SELECT 
    COUNT(*) AS total_turnos,
    COALESCE(SUM(c.total_efectivo), 0) AS total_efectivo,
    COALESCE(SUM(c.total_qr), 0) AS total_qr,
    COALESCE(SUM(c.diferencia), 0) AS total_diferencia
FROM caja_turnos c
$where_resumen";

$resumen = mysqli_fetch_assoc(mysqli_query($conexion, $resumen_query));

// Si se solicita ver detalle de un turno específico
$ver_detalle = isset($_GET['detalle']) && $id_turno_filtro > 0;
$detalle_sobrantes = [];
if ($ver_detalle) {
    $detalle_sobrantes = get_turno_sobrantes($conexion, $id_turno_filtro);
}

// Obtener totales por sabor para el reporte
$where_sabor = "WHERE c.estado = 'cerrado'";
if ($fecha_desde) {
    $fecha_desde_esc = mysqli_real_escape_string($conexion, $fecha_desde);
    $where_sabor .= " AND DATE(c.fecha_cierre) >= '$fecha_desde_esc'";
}
if ($fecha_hasta) {
    $fecha_hasta_esc = mysqli_real_escape_string($conexion, $fecha_hasta);
    $where_sabor .= " AND DATE(c.fecha_cierre) <= '$fecha_hasta_esc'";
}
if ($id_turno_filtro > 0) {
    $where_sabor .= " AND c.id_turno = $id_turno_filtro";
}
if ($id_usuario_filtro > 0) {
    $where_sabor .= " AND c.id_usuario_apertura = $id_usuario_filtro";
}
if ($rol_usuario === 'Cajero') {
    $where_sabor .= " AND c.id_usuario_apertura = $id_usuario";
}

$totales_por_sabor_query = "SELECT 
    t.id_saltena,
    t.nombre AS sabor,
    COALESCE(SUM(ts.horneadas), 0) AS total_horneadas,
    COALESCE(SUM(ts.vendidas), 0) AS total_vendidas,
    COALESCE(SUM(ts.mermas), 0) AS total_mermas,
    COALESCE(SUM(ts.sobrantes), 0) AS total_sobrantes
FROM turno_sobrantes ts
JOIN tipos_saltena t ON ts.id_saltena = t.id_saltena
JOIN caja_turnos c ON ts.id_turno = c.id_turno
$where_sabor
GROUP BY t.id_saltena, t.nombre
ORDER BY t.nombre";

$totales_por_sabor = [];
$result_sabor = mysqli_query($conexion, $totales_por_sabor_query);
while ($row = mysqli_fetch_assoc($result_sabor)) {
    $totales_por_sabor[] = $row;
}

// Calcular totales generales de los sobrantes
$total_horneadas_all = 0;
$total_vendidas_all = 0;
$total_mermas_all = 0;
$total_sobrantes_all = 0;
foreach ($totales_por_sabor as $item) {
    $total_horneadas_all += (int)$item['total_horneadas'];
    $total_vendidas_all += (int)$item['total_vendidas'];
    $total_mermas_all += (int)$item['total_mermas'];
    $total_sobrantes_all += (int)$item['total_sobrantes'];
}

// Obtener el turno específico si se solicita detalle
$turno_detalle = null;
if ($ver_detalle && $id_turno_filtro > 0) {
    $turno_detalle = mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT c.*, u.nombre AS quien_abrio, r.nombre AS rol_abrio
         FROM caja_turnos c
         JOIN usuarios u ON u.id_usuario = c.id_usuario_apertura
         JOIN roles r ON r.id_rol = u.id_rol
         WHERE c.id_turno = $id_turno_filtro AND c.estado = 'cerrado'"
    ));
}

?>
<h1 class="pt">Historial de Cierres de Caja</h1>

<!-- Filtros -->
<div class="panel">
    <div class="hd">Filtros de búsqueda</div>
    <form method="GET" action="index.php?p=historial_turnos" class="form-inline">
        <input type="hidden" name="p" value="historial_turnos">

        <div>
            <label>Fecha desde</label>
            <input type="date" name="fecha_desde" value="<?= h($fecha_desde) ?>">
        </div>
        <div>
            <label>Fecha hasta</label>
            <input type="date" name="fecha_hasta" value="<?= h($fecha_hasta) ?>">
        </div>
        <div>
            <label>N° Turno</label>
            <input type="number" name="id_turno" value="<?= h($id_turno_filtro) ?>" min="1" placeholder="# Turno">
        </div>
        <?php if ($rol_usuario !== 'Cajero'): ?>
            <div>
                <label>Usuario</label>
                <select name="id_usuario">
                    <option value="">Todos los usuarios</option>
                    <?php
                    // Resetear el puntero del resultado
                    mysqli_data_seek($usuarios_caja, 0);
                    while ($u = mysqli_fetch_assoc($usuarios_caja)): ?>
                        <option value="<?= $u['id_usuario'] ?>" <?= $u['id_usuario'] == $id_usuario_filtro ? 'selected' : '' ?>>
                            <?= h($u['nombre']) ?> (<?= h($u['rol']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn o">Filtrar</button>
        <a href="index.php?p=historial_turnos" class="btn sec">Limpiar</a>
    </form>
</div>

<!-- Resumen general -->
<div class="grid">
    <div class="card dorado">
        <h3>Total Turnos</h3>
        <div class="v"><?= (int)$resumen['total_turnos'] ?></div>
    </div>
    <div class="card dorado">
        <h3>Total Efectivo</h3>
        <div class="v">Bs <?= money($resumen['total_efectivo'] ?? 0) ?></div>
    </div>
    <div class="card dorado">
        <h3>Total QR</h3>
        <div class="v">Bs <?= money($resumen['total_qr'] ?? 0) ?></div>
    </div>
    <div class="card <?= ($resumen['total_diferencia'] == 0) ? 'oliva' : 'rojo' ?>">
        <h3>Total Diferencia</h3>
        <div class="v">Bs <?= money($resumen['total_diferencia'] ?? 0) ?></div>
    </div>
</div>

<?php if ($ver_detalle && $turno_detalle): ?>
    <!-- Vista de detalle de un turno específico -->
    <div class="panel">
        <div class="hd">
            Detalle del Turno #<?= $turno_detalle['id_turno'] ?>
            <a href="index.php?p=historial_turnos" class="btn sec sm" style="float: right;">Volver al listado</a>
        </div>

        <div class="grid">
            <div class="card">
                <h4>Información del Turno</h4>
                <p><strong>Apertura:</strong> <?= date('d/m/Y H:i', strtotime($turno_detalle['fecha_apertura'])) ?></p>
                <p><strong>Cierre:</strong> <?= date('d/m/Y H:i', strtotime($turno_detalle['fecha_cierre'])) ?></p>
                <p><strong>Cajero:</strong> <?= h($turno_detalle["quien_abrio"]) ?> (<?= h($turno_detalle["rol_abrio"]) ?>)</p>
            </div>
            <div class="card">
                <h4>Resumen Financiero</h4>
                <p><strong>Monto Inicial:</strong> Bs <?= money($turno_detalle['monto_inicial'] ?? 0) ?></p>
                <p><strong>Efectivo Ventas:</strong> Bs <?= money($turno_detalle['total_efectivo'] ?? 0) ?></p>
                <p><strong>QR Ventas:</strong> Bs <?= money($turno_detalle['total_qr'] ?? 0) ?></p>
                <p><strong>Total Vendido:</strong> Bs <?= money(($turno_detalle['total_efectivo'] ?? 0) + ($turno_detalle['total_qr'] ?? 0)) ?></p>
            </div>
            <div class="card">
                <h4>Cierre de Caja</h4>
                <p><strong>Efectivo Contado:</strong> Bs <?= money($turno_detalle['efectivo_contado'] ?? 0) ?></p>
                <p><strong>Diferencia:</strong>
                    <span style="color: <?= ($turno_detalle['diferencia'] == 0) ? 'var(--oliva)' : 'var(--rojo)' ?>; font-weight: bold;">
                        Bs <?= money($turno_detalle['diferencia'] ?? 0) ?>
                    </span>
                </p>
                <p><strong>Estado:</strong> <span class="pill" style="background: <?= $turno_detalle['estado'] === 'cerrado' ? 'var(--oliva)' : 'var(--naranja)' ?>"><?= h($turno_detalle['estado']) ?></span></p>
            </div>
        </div>

        <!-- Detalle por sabor -->
        <div class="panel">
            <div class="hd">Detalle por Sabor - Horneadas, Vendidas, Mermas y Sobrantes</div>
            <table>
                <thead>
                    <tr>
                        <th>Sabor</th>
                        <th>Horneadas</th>
                        <th>Vendidas</th>
                        <th>Mermas</th>
                        <th>Sobrantes</th>
                        <th>% Vendido</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_horneadas = 0;
                    $total_vendidas = 0;
                    $total_mermas = 0;
                    $total_sobrantes_detalle = 0;

                    foreach ($detalle_sobrantes as $item):
                        $horneadas = (int)$item['horneadas'];
                        $vendidas = (int)$item['vendidas'];
                        $mermas = (int)$item['mermas'];
                        $sobrantes = (int)$item['sobrantes'];

                        $total_horneadas += $horneadas;
                        $total_vendidas += $vendidas;
                        $total_mermas += $mermas;
                        $total_sobrantes_detalle += $sobrantes;

                        $porcentaje_vendido = ($horneadas > 0) ? round(($vendidas / $horneadas) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><strong><?= h($item['sabor']) ?></strong></td>
                            <td><?= $horneadas ?></td>
                            <td><?= $vendidas ?></td>
                            <td><?= $mermas ?></td>
                            <td><?= $sobrantes ?></td>
                            <td><span class="pill" style="background: <?= ($porcentaje_vendido >= 90) ? 'var(--oliva)' : (($porcentaje_vendido >= 70) ? 'var(--naranja)' : 'var(--rojo)') ?>"><?= $porcentaje_vendido ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($detalle_sobrantes)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;color:#aaa">No hay datos de sobrantes para este turno</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f5f5f5; font-weight: bold;">
                        <td><strong>TOTALES</strong></td>
                        <td><?= $total_horneadas ?></td>
                        <td><?= $total_vendidas ?></td>
                        <td><?= $total_mermas ?></td>
                        <td><?= $total_sobrantes_detalle ?></td>
                        <td><?= ($total_horneadas > 0) ? round(($total_vendidas / $total_horneadas) * 100, 1) : 0 ?>%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- Vista de listado de turnos -->

    <!-- Totales por sabor (resumen) -->
    <div class="panel">
        <div class="hd">Resumen por Sabor - Todos los Turnos Filtrados</div>
        <table>
            <thead>
                <tr>
                    <th>Sabor</th>
                    <th>Horneadas</th>
                    <th>Vendidas</th>
                    <th>Mermas</th>
                    <th>Sobrantes</th>
                    <th>% Vendido</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($totales_por_sabor as $item):
                    $horneadas = (int)$item['total_horneadas'];
                    $vendidas = (int)$item['total_vendidas'];
                    $mermas = (int)$item['total_mermas'];
                    $sobrantes = (int)$item['total_sobrantes'];
                    $porcentaje_vendido = ($horneadas > 0) ? round(($vendidas / $horneadas) * 100, 1) : 0;
                ?>
                    <tr>
                        <td><strong><?= h($item['sabor']) ?></strong></td>
                        <td><?= $horneadas ?></td>
                        <td><?= $vendidas ?></td>
                        <td><?= $mermas ?></td>
                        <td><?= $sobrantes ?></td>
                        <td><span class="pill" style="background: <?= ($porcentaje_vendido >= 90) ? 'var(--oliva)' : (($porcentaje_vendido >= 70) ? 'var(--naranja)' : 'var(--rojo)') ?>"><?= $porcentaje_vendido ?>%</span></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($totales_por_sabor)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#aaa">No hay datos de sobrantes para los filtros aplicados</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f5f5f5; font-weight: bold;">
                    <td><strong>TOTALES</strong></td>
                    <td><?= $total_horneadas_all ?></td>
                    <td><?= $total_vendidas_all ?></td>
                    <td><?= $total_mermas_all ?></td>
                    <td><?= $total_sobrantes_all ?></td>
                    <td><?= ($total_horneadas_all > 0) ? round(($total_vendidas_all / $total_horneadas_all) * 100, 1) : 0 ?>%</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Lista de turnos cerrados -->
    <div class="panel">
        <div class="hd">Historial de Turnos Cerrados</div>
        <table>
            <thead>
                <tr>
                    <th># Turno</th>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th>Cajero</th>
                    <th>Inicial</th>
                    <th>Efectivo</th>
                    <th>QR</th>
                    <th>Total</th>
                    <th>Diferencia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Resetear puntero para volver a iterar
                mysqli_data_seek($historial, 0);
                while ($t = mysqli_fetch_assoc($historial)): ?>
                    <tr>
                        <td><strong>#<?= $t['id_turno'] ?></strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($t['fecha_apertura'])) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($t['fecha_cierre'])) ?></td>
                        <td><?= h($t['quien_abrio']) ?> (<span class="pill sm" style="background: var(--gris)"><?= h($t['rol_abrio']) ?></span>)</td>
                        <td>Bs <?= money($t['monto_inicial']) ?></td>
                        <td>Bs <?= money($t['total_efectivo'] ?? 0) ?></td>
                        <td>Bs <?= money($t['total_qr'] ?? 0) ?></td>
                        <td><b>Bs <?= money(($t['total_efectivo'] ?? 0) + ($t['total_qr'] ?? 0)) ?></b></td>
                        <td style="color: <?= ($t['diferencia'] ?? 0) == 0 ? 'var(--oliva)' : 'var(--rojo)' ?>; font-weight: 700;">
                            Bs <?= money($t['diferencia'] ?? 0) ?>
                        </td>
                        <td>
                            <a href="index.php?p=historial_turnos&detalle=1&id_turno=<?= $t['id_turno'] ?>"
                                class="btn sec sm">Ver detalle</a>
                        </td>
                    </tr>
                <?php endwhile; ?>

                <?php if (mysqli_num_rows($historial) == 0): ?>
                    <tr>
                        <td colspan="10" style="text-align:center;color:#aaa">No hay turnos cerrados con los filtros aplicados</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f9f9f9;">
                    <td colspan="4"><strong>TOTALES</strong></td>
                    <td><b>Bs <?= money($resumen['monto_inicial'] ?? 0) ?></b></td>
                    <td><b>Bs <?= money($resumen['total_efectivo'] ?? 0) ?></b></td>
                    <td><b>Bs <?= money($resumen['total_qr'] ?? 0) ?></b></td>
                    <td><b>Bs <?= money(($resumen['total_efectivo'] ?? 0) + ($resumen['total_qr'] ?? 0)) ?></b></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

<?php endif; ?>
