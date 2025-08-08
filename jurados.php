<?php
include 'funciones.php';

// Iniciar sesión si aún no ha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Agregar jurado
if (isset($_POST['add_jurado'])) {
    $_SESSION['jurados'][] = [
        'nombre' => $_POST['nombre_jurado'],
        'correo' => $_POST['correo_jurado'],
        'rol' => $_POST['rol_jurado'] // Nuevo campo
    ];
}

// Eliminar jurado
if (isset($_GET['del_jurado'])) {
    unset($_SESSION['jurados'][$_GET['del_jurado']]);
    $_SESSION['jurados'] = array_values($_SESSION['jurados']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Jurados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'index.php'; ?>

<div class="container mt-4">
    <h2>Gestión de Jurados</h2>
    <div class="card p-3 mb-4">
        <form method="POST">
            <input type="text" name="nombre_jurado" class="form-control mb-2" placeholder="Nombre" required>
            <input type="email" name="correo_jurado" class="form-control mb-2" placeholder="Correo" required>

            <!-- NUEVO: Selector de Rol -->
            <select name="rol_jurado" class="form-control mb-2" required>
                <option value="">Seleccione el rol del jurado</option>
                <option value="Docente">Docente</option>
                <option value="Especialista">Especialista</option>
                <option value="Investigador">Investigador</option>
            </select>

            <button type="submit" name="add_jurado" class="btn btn-success">Agregar</button>
        </form>
    </div>

    <ul class="list-group">
        <?php if (!empty($_SESSION['jurados'])): ?>
            <?php foreach ($_SESSION['jurados'] as $id => $j): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($j['nombre']) ?></strong> - 
                        <?= htmlspecialchars($j['correo']) ?> 
                        <span class="badge bg-secondary"><?= htmlspecialchars($j['rol']) ?></span>
                    </div>
                    <a href="?del_jurado=<?= $id ?>" class="btn btn-sm btn-danger">Eliminar</a>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li class="list-group-item">No hay jurados registrados.</li>
        <?php endif; ?>
    </ul>
</div>
</body>
</html>


