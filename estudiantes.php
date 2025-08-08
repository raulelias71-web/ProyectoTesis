<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Función para descargar plantilla Excel
function descargarPlantillaExcel() {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Encabezados
    $sheet->setCellValue('A1', 'Nombre');
    $sheet->setCellValue('B1', 'Correo');
    $sheet->setCellValue('C1', 'Carnet');

    // Estilo simple para encabezados
    $sheet->getStyle('A1:C1')->getFont()->setBold(true);

    // Preparar archivo para descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="plantilla_estudiantes.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Función para importar estudiantes desde Excel a $_SESSION['estudiantes']
function importarEstudiantesDesdeExcel($rutaArchivo) {
    $reader = IOFactory::createReader('Xlsx');
    $spreadsheet = $reader->load($rutaArchivo);
    $hoja = $spreadsheet->getActiveSheet();

    if (!isset($_SESSION['estudiantes'])) {
        $_SESSION['estudiantes'] = [];
    }

    $importados = 0;
    $errores = 0;

    foreach ($hoja->getRowIterator(2) as $fila) {
        $rowIndex = $fila->getRowIndex();

        $nombre = trim($hoja->getCell('A' . $rowIndex)->getValue());
        $correo = trim($hoja->getCell('B' . $rowIndex)->getValue());
        $carnet = trim($hoja->getCell('C' . $rowIndex)->getValue());

        if ($nombre !== '' && $correo !== '' && $carnet !== '' && ctype_digit($carnet)) {
            // Evitar duplicados en sesión
            $existe = false;
            foreach ($_SESSION['estudiantes'] as $e) {
                if (strtolower($e['correo']) === strtolower($correo) || $e['carnet'] === $carnet) {
                    $existe = true;
                    break;
                }
            }

            if (!$existe) {
                $_SESSION['estudiantes'][] = [
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'carnet' => $carnet
                ];
                $importados++;
            } else {
                $errores++;
            }
        } else {
            $errores++;
        }
    }

    return ['importados' => $importados, 'errores' => $errores];
}

// Manejo de acciones
$error = '';
$mensajeImportacion = '';

// Descargar plantilla
if (isset($_GET['descargar_plantilla'])) {
    descargarPlantillaExcel();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Agregar estudiante
    if (isset($_POST['add_estudiante'])) {
        $nombre = trim($_POST['nombre_estudiante']);
        $correo = trim($_POST['correo_estudiante']);
        $carnet = trim($_POST['carnet_estudiante']);

        if ($nombre === '' || $correo === '' || $carnet === '' || !ctype_digit($carnet)) {
            $error = "Debes completar todos los campos correctamente. El número de carné debe contener solo números.";
        } else {
            // Evitar duplicados en sesión
            $existe = false;
            if (isset($_SESSION['estudiantes'])) {
                foreach ($_SESSION['estudiantes'] as $e) {
                    if (strtolower($e['correo']) === strtolower($correo) || $e['carnet'] === $carnet) {
                        $existe = true;
                        break;
                    }
                }
            } else {
                $_SESSION['estudiantes'] = [];
            }

            if ($existe) {
                $error = "Ya existe un estudiante con ese correo o carné.";
            } else {
                $_SESSION['estudiantes'][] = [
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'carnet' => $carnet
                ];
            }
        }
    }

    // Editar estudiante
    if (isset($_POST['edit_estudiante'])) {
        $id = (int)$_POST['id_estudiante'];
        $nombre = trim($_POST['nombre_estudiante']);
        $correo = trim($_POST['correo_estudiante']);
        $carnet = trim($_POST['carnet_estudiante']);

        if ($nombre === '' || $correo === '' || $carnet === '' || !ctype_digit($carnet)) {
            $error = "Todos los campos son obligatorios y el carné debe contener solo números.";
        } else {
            if (!isset($_SESSION['estudiantes'][$id])) {
                $error = "Estudiante no encontrado para editar.";
            } else {
                // Validar duplicados excepto el mismo índice
                $existe = false;
                foreach ($_SESSION['estudiantes'] as $key => $e) {
                    if ($key !== $id && (strtolower($e['correo']) === strtolower($correo) || $e['carnet'] === $carnet)) {
                        $existe = true;
                        break;
                    }
                }

                if ($existe) {
                    $error = "Ya existe otro estudiante con ese correo o carné.";
                } else {
                    $_SESSION['estudiantes'][$id] = [
                        'nombre' => $nombre,
                        'correo' => $correo,
                        'carnet' => $carnet
                    ];
                }
            }
        }
    }

    // Importar desde Excel
    if (isset($_POST['importar_excel']) && isset($_FILES['archivo_excel'])) {
        $archivoTmp = $_FILES['archivo_excel']['tmp_name'];
        if (is_uploaded_file($archivoTmp)) {
            $resultado = importarEstudiantesDesdeExcel($archivoTmp);
            $mensajeImportacion = "Importación completada: {$resultado['importados']} importados, {$resultado['errores']} errores.";
        } else {
            $error = "Error al subir el archivo.";
        }
    }
}

// Eliminar estudiante
if (isset($_GET['del_estudiante'])) {
    $id = (int)$_GET['del_estudiante'];
    if (isset($_SESSION['estudiantes'][$id])) {
        unset($_SESSION['estudiantes'][$id]);
        $_SESSION['estudiantes'] = array_values($_SESSION['estudiantes']); // Reindexar array
    } else {
        $error = "Estudiante no encontrado para eliminar.";
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Gestión de Estudiantes</title>
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
    <h2 class="mb-4">Gestión de Estudiantes</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($mensajeImportacion !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensajeImportacion) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card p-3 mb-4">
                <h5>Registrar Estudiante Manualmente</h5>
                <form method="POST" novalidate>
                    <input type="text" name="nombre_estudiante" class="form-control mb-2" placeholder="Nombre" required>
                    <input type="email" name="correo_estudiante" class="form-control mb-2" placeholder="Correo institucional" required>
                    <input type="text" name="carnet_estudiante" class="form-control mb-2" placeholder="Número de Carné (solo números)" required pattern="\d+">
                    <button type="submit" name="add_estudiante" class="btn btn-ocre w-100">Agregar</button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-3 mb-4">
                <h5>Importar Estudiantes desde Excel</h5>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="archivo_excel" class="form-control mb-2" accept=".xlsx" required>
                    <button type="submit" name="importar_excel" class="btn btn-success w-100">Importar</button>
                </form>
                <a href="?descargar_plantilla=1" class="btn btn-link mt-2">Descargar plantilla</a>
            </div>
        </div>
    </div>

    <h5>Lista de Estudiantes</h5>
    <ul class="list-group">
        <?php if (!empty($_SESSION['estudiantes'])): ?>
            <?php foreach ($_SESSION['estudiantes'] as $id => $e): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= htmlspecialchars($e['nombre']) ?> - <?= htmlspecialchars($e['correo']) ?> - Carné: <?= htmlspecialchars($e['carnet']) ?>
                    <div>
                        <button class="btn btn-sm btn-gray me-2"
                                data-bs-toggle="modal"
                                data-bs-target="#editModal"
                                data-id="<?= $id ?>"
                                data-nombre="<?= htmlspecialchars($e['nombre']) ?>"
                                data-correo="<?= htmlspecialchars($e['correo']) ?>"
                                data-carnet="<?= htmlspecialchars($e['carnet']) ?>">
                            Editar
                        </button>
                        <a href="?del_estudiante=<?= $id ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Seguro que deseas eliminar este estudiante?');">X</a>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li class="list-group-item">No hay estudiantes registrados.</li>
        <?php endif; ?>
    </ul>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Editar Estudiante</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id_estudiante" id="edit-id">
          <input type="text" name="nombre_estudiante" id="edit-nombre" class="form-control mb-2" placeholder="Nombre" required>
          <input type="email" name="correo_estudiante" id="edit-correo" class="form-control mb-2" placeholder="Correo institucional" required>
          <input type="text" name="carnet_estudiante" id="edit-carnet" class="form-control mb-2" placeholder="Número de Carné (solo números)" required pattern="\d+">
        </div>
        <div class="modal-footer">
          <button type="submit" name="edit_estudiante" class="btn btn-ocre">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('edit-id').value = button.getAttribute('data-id');
    document.getElementById('edit-nombre').value = button.getAttribute('data-nombre');
    document.getElementById('edit-correo').value = button.getAttribute('data-correo');
    document.getElementById('edit-carnet').value = button.getAttribute('data-carnet');
});
</script>
</body>
</html>



