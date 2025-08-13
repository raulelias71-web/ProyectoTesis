<?php
include 'funciones.php'; 
session_start();

// Incluir conexión
require_once 'Conexion.php'; 
$conn = $conexion; 

// Agregar jurado
if (isset($_POST['add_jurado'])) {
    $nombre = trim($_POST['nombre_jurado']);
    $correo = trim($_POST['correo_jurado']);
    $rol    = trim($_POST['rol_jurado']);

    // Verificar si el correo ya existe
    $stmt = $conn->prepare("SELECT id FROM jurados WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "<div class='alert alert-warning text-center'>⚠ El correo ya está registrado.</div>";
    } else {
        // Insertar nuevo jurado
        $stmt = $conn->prepare("INSERT INTO jurados (nombre, correo, rol) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $correo, $rol);
        if ($stmt->execute()) {
            echo "<div class='alert alert-success text-center'>✅ Jurado agregado correctamente.</div>";
        } else {
            echo "<div class='alert alert-danger text-center'>❌ Error al guardar: " . $conn->error . "</div>";
        }
    }
    $stmt->close();
}

// Eliminar jurado
if (isset($_GET['del_jurado'])) {
    $id = intval($_GET['del_jurado']);
    $stmt = $conn->prepare("DELETE FROM jurados WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    echo "<div class='alert alert-info text-center'>🗑 Jurado eliminado.</div>";
}

// Obtener lista de jurados
$result = $conn->query("SELECT * FROM jurados ORDER BY nombre ASC");
$jurados = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Jurados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .bg-ocre { background-color: #611010ff !important; }
        .btn-ocre {
            background-color: #611010ff !important;
            border-color: #611010ff !important;
            color: #fff !important;
        }
        .btn-ocre:hover {
            background-color: #4a0c0c !important;
            border-color: #4a0c0c !important;
        }
        .btn-gray {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }
        .btn-gray:hover {
            background-color: #5a6268 !important;
            border-color: #545b62 !important;
        }
    </style>
</head>
<body class="bg-light">

<!-- Barra de navegación -->
<nav class="navbar navbar-expand-lg navbar-dark bg-ocre mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Gestión de Grupos</a>
    <div>
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="estudiantes.php">Estudiantes</a></li>
        <li class="nav-item"><a class="nav-link" href="jurados.php">Jurados</a></li>
        <li class="nav-item"><a class="nav-link" href="grupos_jurados.php">Grupos de Jurados</a></li>
        <li class="nav-item"><a class="nav-link" href="grupos_estudiantes.php">Grupos de Estudiantes</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4">
    <h2>Gestión de Jurados</h2>
    <div class="card p-3 mb-4">
        <form method="POST">
            <input type="text" name="nombre_jurado" class="form-control mb-2" placeholder="Nombre" required>
            <input type="email" name="correo_jurado" class="form-control mb-2" placeholder="Correo" required>
            <select name="rol_jurado" class="form-control mb-2" required>
                <option value="">Seleccione el rol del jurado</option>
                <option value="Docente">Docente</option>
                <option value="Especialista">Especialista</option>
                <option value="Investigador">Investigador</option>
            </select>
            <button type="submit" name="add_jurado" class="btn btn-ocre w-100">Agregar</button>
        </form>
    </div>

    <ul class="list-group">
        <?php if (!empty($jurados)): ?>
            <?php foreach ($jurados as $j): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($j['nombre']) ?></strong> - 
                        <?= htmlspecialchars($j['correo']) ?> 
                        <span class="badge bg-secondary"><?= htmlspecialchars($j['rol']) ?></span>
                    </div>
                    <a href="?del_jurado=<?= $j['id'] ?>" class="btn btn-sm btn-danger">Eliminar</a>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li class="list-group-item">No hay jurados registrados.</li>
        <?php endif; ?>
    </ul>
</div>
</body>
</html>



