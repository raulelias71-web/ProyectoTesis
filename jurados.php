<?php
include 'funciones.php';
session_start();

// --- CONEXIÓN A BASE DE DATOS ---
require_once 'Conexion.php';
$conn = $conexion;

// Cargar PhpSpreadsheet
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Variables de mensajes
$error = '';
$mensajeImportacion = '';

// Función para descargar plantilla Excel de jurados
function descargarPlantillaJurados() {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Encabezados
    $sheet->setCellValue('A1', 'Nombre');
    $sheet->setCellValue('B1', 'Correo');
    $sheet->setCellValue('C1', 'Rol');

    // Negrita encabezados
    $sheet->getStyle('A1:C1')->getFont()->setBold(true);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="plantilla_jurados.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Descargar plantilla
if (isset($_GET['descargar_plantilla'])) {
    descargarPlantillaJurados();
}

// --- EDITAR JURADO ---
$edit_jurado = null;
if (isset($_GET['edit_jurado'])) {
    $id = intval($_GET['edit_jurado']);
    $stmt = $conn->prepare("SELECT * FROM jurados WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $edit_jurado = $res->fetch_assoc();
    $stmt->close();
}

// Agregar o editar jurado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Evitar warnings usando isset
    $nombre = isset($_POST['nombre_jurado']) ? trim($_POST['nombre_jurado']) : '';
    $correo = isset($_POST['correo_jurado']) ? trim($_POST['correo_jurado']) : '';
    $rol    = isset($_POST['rol_jurado']) ? trim($_POST['rol_jurado']) : '';
    $id     = isset($_POST['id_jurado']) ? intval($_POST['id_jurado']) : 0;

    if ($nombre === '' || $correo === '' || $rol === '') {
        $error = "Todos los campos son obligatorios.";
    } else {
        if ($id > 0) {
            // Editar jurado
            $stmtCheck = $conn->prepare("SELECT id FROM jurados WHERE correo = ? AND id != ?");
            $stmtCheck->bind_param("si", $correo, $id);
            $stmtCheck->execute();
            $stmtCheck->store_result();

            if ($stmtCheck->num_rows > 0) {
                $error = "El correo ya está registrado en otro jurado.";
            } else {
                $stmtUpdate = $conn->prepare("UPDATE jurados SET nombre = ?, correo = ?, rol = ? WHERE id = ?");
                $stmtUpdate->bind_param("sssi", $nombre, $correo, $rol, $id);
                if ($stmtUpdate->execute()) {
                    $mensajeImportacion = "Jurado actualizado correctamente.";
                    // Redirigir para limpiar la variable $edit_jurado y evitar reenvío
                    header("Location: jurados.php");
                    exit;
                } else {
                    $error = "Error al actualizar jurado: " . $stmtUpdate->error;
                }
                $stmtUpdate->close();
            }
            $stmtCheck->close();
        } else {
            // Agregar jurado
            $stmtCheck = $conn->prepare("SELECT id FROM jurados WHERE correo = ?");
            $stmtCheck->bind_param("s", $correo);
            $stmtCheck->execute();
            $stmtCheck->store_result();

            if ($stmtCheck->num_rows > 0) {
                $error = "El correo ya está registrado.";
            } else {
                $stmtInsert = $conn->prepare("INSERT INTO jurados (nombre, correo, rol) VALUES (?, ?, ?)");
                $stmtInsert->bind_param("sss", $nombre, $correo, $rol);
                if ($stmtInsert->execute()) {
                    $mensajeImportacion = "Jurado agregado correctamente.";
                } else {
                    $error = "Error al agregar jurado: " . $stmtInsert->error;
                }
                $stmtInsert->close();
            }
            $stmtCheck->close();
        }
    }
}

// Eliminar jurado
if (isset($_GET['del_jurado'])) {
    $id = (int)$_GET['del_jurado'];
    $stmtDel = $conn->prepare("DELETE FROM jurados WHERE id = ?");
    $stmtDel->bind_param("i", $id);
    if ($stmtDel->execute()) {
        $mensajeImportacion = "Jurado eliminado correctamente.";
    } else {
        $error = "Error al eliminar jurado: " . $stmtDel->error;
    }
    $stmtDel->close();
}

// Obtener lista de jurados
$result = $conn->query("SELECT * FROM jurados ORDER BY nombre ASC");

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestión de Jurados</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
    .bg-ocre { background-color: #611010ff !important; }
    .btn-ocre { background-color: #611010ff !important; border-color: #611010ff !important; color: #fff !important; }
    .btn-ocre:hover { background-color: #4a0c0c !important; border-color: #4a0c0c !important; }
    .btn-gray { background-color: #6c757d !important; border-color: #6c757d !important; color: #fff !important; }
    .btn-gray:hover { background-color: #5a6268 !important; border-color: #545b62 !important; }
</style>
</head>
<body class="bg-light">

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

<div class="container">
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($mensajeImportacion !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($mensajeImportacion) ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card p-3 mb-4">
                <h5><?= $edit_jurado ? "Editar Jurado" : "Registrar Jurado" ?></h5>
                <form method="POST" novalidate>
                    <?php if ($edit_jurado): ?>
                        <input type="hidden" name="id_jurado" value="<?= htmlspecialchars($edit_jurado['id']) ?>">
                    <?php endif; ?>
                    <input type="text" name="nombre_jurado" class="form-control mb-2" placeholder="Nombre" required value="<?= htmlspecialchars($edit_jurado['nombre'] ?? '') ?>">
                    <input type="email" name="correo_jurado" class="form-control mb-2" placeholder="Correo institucional" required value="<?= htmlspecialchars($edit_jurado['correo'] ?? '') ?>">
                    <select name="rol_jurado" class="form-control mb-2" required>
                        <option value="">Seleccione el rol del jurado</option>
                        <option value="Docente" <?= (isset($edit_jurado) && $edit_jurado['rol']=='Docente')?'selected':'' ?>>Docente</option>
                        <option value="Especialista" <?= (isset($edit_jurado) && $edit_jurado['rol']=='Especialista')?'selected':'' ?>>Especialista</option>
                        <option value="Investigador" <?= (isset($edit_jurado) && $edit_jurado['rol']=='Investigador')?'selected':'' ?>>Investigador</option>
                    </select>
                    <button type="submit" class="btn btn-ocre w-100"><?= $edit_jurado ? "Actualizar" : "Agregar" ?></button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-3 mb-4">
                <h5>Importar Jurados desde Excel</h5>
                <form method="POST" enctype="multipart/form-data" novalidate>
                    <input type="file" name="archivo_excel" class="form-control mb-2" accept=".xlsx" required>
                    <button type="submit" name="importar_excel" class="btn btn-success w-100">Importar</button>
                </form>
                <a href="?descargar_plantilla=1" class="btn btn-link mt-2">Descargar plantilla</a>
            </div>
        </div>
    </div>

    <h5>Lista de Jurados</h5>
    <ul class="list-group">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($j = $result->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong><?= htmlspecialchars($j['nombre']) ?></strong> - <?= htmlspecialchars($j['correo']) ?> 
                    <span class="badge bg-secondary"><?= htmlspecialchars($j['rol']) ?></span>
                    <div>
                        <a href="?edit_jurado=<?= $j['id'] ?>" class="btn btn-sm btn-ocre me-1">Editar</a>
                        <a href="?del_jurado=<?= $j['id'] ?>" class="btn btn-sm btn-gray" onclick="return confirm('¿Seguro que deseas eliminar este jurado?');">Eliminar</a>
                    </div>
                </li>
            <?php endwhile; ?>
        <?php else: ?>
            <li class="list-group-item">No hay jurados registrados.</li>
        <?php endif; ?>
    </ul>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

