<?php
// Conectar con MySQL.
require_once "conexion.php";

// Consultar y ordenar clientes.
$resultado = $conexion->query("SELECT * FROM clientes ORDER BY nombre");

// Proteger los datos al mostrarlos.
function limpiar($dato) {
    return htmlspecialchars((string)($dato ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Clientes</title>

    <!-- Cargar Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-5">

    <!-- Titulo principal -->
    <h2 class="text-center">Sistema de Clientes</h2>

    <!-- Mensaje de registro -->
    <?php if (isset($_GET["registrado"])): ?>
        <div class="alert alert-success">Cliente registrado correctamente.</div>
    <?php endif; ?>

    <!--Mensaje de actualizacion-->
    <?php if (isset($_GET["actualizado"])): ?>
        <div class="alert alert-success">Cliente actualizado correctamente.</div>
    <?php endif; ?>

    <!-- Mensaje de error -->
    <?php if (isset($_GET["error"])): ?>
        <div class="alert alert-danger">Error al guardar los datos.</div>
    <?php endif; ?>

    <!-- FORMULARIO DE REGISTRO -->
    <div class="card my-4">
        <div class="card-header bg-primary text-white">Registrar Cliente</div>
        <div class="card-body">

            <!-- Enviar datos por POST -->
            <form action="clientes_registrar.php" method="POST">
                <div class="row g-3">

                    <?php
                    // Campos del formulario.
                    $campos = [
                        "nombre" => "Nombre",
                        "apellido_p" => "Apellido Paterno",
                        "apellido_m" => "Apellido Materno",
                        "celular" => "Celular",
                        "direccion" => "Dirección"
                    ];

                    // Maximo de caracteres.
                    $limites = [
                        "nombre" => 60,
                        "apellido_p" => 40,
                        "apellido_m" => 40,
                        "celular" => 15,
                        "direccion" => 150
                    ];

                    // Crear los campos automaticamente.
                    foreach ($campos as $campo => $texto):
                    ?>
                        <div class="col-md-4">
                            <label class="form-label"><?= $texto ?></label>

                            <input type="text"
                                   name="<?= $campo ?>"
                                   maxlength="<?= $limites[$campo] ?>"
                                   class="form-control"
                                   <?= in_array($campo, ["nombre", "apellido_p"]) ? "required" : "" ?>>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- Boton para registrar -->
                <button class="btn btn-primary mt-3">
                    Registrar Cliente
                </button>
            </form>

        </div>
    </div>

    <!-- LISTADO DE CLIENTES -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">Lista de Clientes</div>
        <div class="card-body table-responsive">

            <!-- Tabla de clientes -->
            <table class="table table-bordered table-striped">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellido Paterno</th>
                    <th>Apellido Materno</th>
                    <th>Celular</th>
                    <th>Dirección</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>

                <!-- Recorrer los clientes -->
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                    <tr>
                        <!-- Mostrar los datos -->
                        <?php foreach ([
                            "id_cliente",
                            "nombre",
                            "apellido_p",
                            "apellido_m",
                            "celular",
                            "direccion",
                            "estado"
                        ] as $campo): ?>

                            <td><?= limpiar($fila[$campo]) ?></td>

                        <?php endforeach; ?>

                        <!-- Boton para editar -->
                        <td>
                            <a class="btn btn-warning btn-sm"
                               href="clientes_editar.php?id=<?= (int)$fila['id_cliente'] ?>">
                                Editar
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>

            </table>

        </div>
    </div>

</div>
</body>
</html>
