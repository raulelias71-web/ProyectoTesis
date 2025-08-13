<?php
// Conexión a base de datos
$host = 'localhost';
$usuario = 'root';
$contrasena = '';
$baseDatos = 'gestion_grupos';

$conn = new mysqli($host, $usuario, $contrasena, $baseDatos);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// =====================
// Función: verificar si un jurado ya está asignado a otro grupo distinto
// =====================
function juradoAsignadoDistintoGrupo($conn, $idJurado, $grupoEditandoId = null) {
    $count = 0;

    if ($grupoEditandoId === null) {
        $sql = "SELECT COUNT(*) FROM grupos_jurados WHERE jurado1_id = ? OR jurado2_id = ? OR jurado3_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $idJurado, $idJurado, $idJurado);
    } else {
        $sql = "SELECT COUNT(*) FROM grupos_jurados WHERE id != ? AND (jurado1_id = ? OR jurado2_id = ? OR jurado3_id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $grupoEditandoId, $idJurado, $idJurado, $idJurado);
    }
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

// =====================
// Función: crear grupo de estudiantes y actualizar grupos_asignados automáticamente
// =====================
function crearGrupoEstudiantes($conn, $grupo, $pre_especialidad, $dia, $hora, $aula, $estudiantes_ids, $grupo_jurado_id) {
    $stmt = $conn->prepare("INSERT INTO grupos_estudiantes (grupo, pre_especialidad, dia, hora, aula, grupo_jurado_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $grupo, $pre_especialidad, $dia, $hora, $aula, $grupo_jurado_id);

    if ($stmt->execute()) {
        $grupo_id = $conn->insert_id;
        $stmt->close();

        foreach ($estudiantes_ids as $est_id) {
            $stmt2 = $conn->prepare("INSERT INTO grupo_estudiante_detalle (grupo_id, estudiante_id) VALUES (?, ?)");
            $stmt2->bind_param("ii", $grupo_id, $est_id);
            $stmt2->execute();
            $stmt2->close();
        }

        // Incrementar grupos_asignados
        $stmt3 = $conn->prepare("UPDATE grupos_jurados SET grupos_asignados = grupos_asignados + 1 WHERE id = ?");
        $stmt3->bind_param("i", $grupo_jurado_id);
        $stmt3->execute();
        $stmt3->close();

        return true;
    }
    $stmt->close();
    return false;
}

session_start();

$error = '';
$mensaje = '';

$grupoEditandoId = isset($_GET['edit_grupo']) ? (int)$_GET['edit_grupo'] : null;
$grupoEditando = null;

// =====================
// Obtener grupo a editar
// =====================
if ($grupoEditandoId !== null) {
    $stmt = $conn->prepare("SELECT id, jurado1_id, jurado2_id, jurado3_id FROM grupos_jurados WHERE id = ?");
    $stmt->bind_param("i", $grupoEditandoId);
    $stmt->execute();
    $stmt->bind_result($id, $jurado1_id, $jurado2_id, $jurado3_id);
    if ($stmt->fetch()) {
        $grupoEditando = [
            'id' => $id,
            'jurados' => [$jurado1_id, $jurado2_id, $jurado3_id],
        ];
    }
    $stmt->close();
}

// =====================
// Crear o actualizar grupo de jurados
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jurado_ids = $_POST['jurado_ids'] ?? [];
    $editandoIdPost = isset($_POST['editando_id']) ? (int)$_POST['editando_id'] : null;

    if (count($jurado_ids) !== 3) {
        $error = "Debes seleccionar exactamente 3 jurados.";
    } else {
        $valid = true;
        foreach ($jurado_ids as $id) {
            if (juradoAsignadoDistintoGrupo($conn, $id, $editandoIdPost)) {
                $valid = false;
                $error = "Un jurado ya está asignado a otro grupo.";
                break;
            }
        }
        if ($valid) {
            list($jurado1, $jurado2, $jurado3) = array_map('intval', $jurado_ids);

            if ($editandoIdPost !== null) {
                $stmt = $conn->prepare("UPDATE grupos_jurados SET jurado1_id = ?, jurado2_id = ?, jurado3_id = ? WHERE id = ?");
                $stmt->bind_param("iiii", $jurado1, $jurado2, $jurado3, $editandoIdPost);
                if ($stmt->execute()) {
                    $mensaje = "Grupo actualizado correctamente.";
                } else {
                    $error = "Error al actualizar grupo: " . $conn->error;
                }
                $stmt->close();
            } else {
                $grupos_asignados = 0;
                $stmt = $conn->prepare("INSERT INTO grupos_jurados (jurado1_id, jurado2_id, jurado3_id, grupos_asignados) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiii", $jurado1, $jurado2, $jurado3, $grupos_asignados);
                if ($stmt->execute()) {
                    $mensaje = "Grupo creado correctamente.";
                } else {
                    $error = "Error al crear grupo: " . $conn->error;
                }
                $stmt->close();
            }
            $grupoEditando = null;
            $grupoEditandoId = null;
        }
    }
}

// =====================
// Eliminar grupo de jurados
// =====================
if (isset($_GET['del_grupo_jurado'])) {
    $delId = (int)$_GET['del_grupo_jurado'];
    $stmt = $conn->prepare("DELETE FROM grupos_jurados WHERE id = ?");
    $stmt->bind_param("i", $delId);
    if ($stmt->execute()) {
        $mensaje = "Grupo eliminado correctamente.";
        if ($grupoEditandoId === $delId) {
            $grupoEditandoId = null;
            $grupoEditando = null;
        }
    } else {
        $error = "Error al eliminar grupo: " . $conn->error;
    }
    $stmt->close();
}

// =====================
// Obtener grupos y jurados
// =====================
$grupos_jurados = [];
$result = $conn->query("SELECT id, jurado1_id, jurado2_id, jurado3_id, grupos_asignados FROM grupos_jurados ORDER BY id ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $grupos_jurados[] = $row;
    }
    $result->free();
}

$jurados = [];
$result = $conn->query("SELECT id, nombre, rol FROM jurados ORDER BY nombre ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $jurados[$row['id']] = $row;
    }
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Grupos de Jurados - Crear/Editar</title>
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
table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px; color: #222; margin-top: 1rem; }
th, td { border: 1px solid #611010ff; padding: 8px 12px; text-align: left; vertical-align: middle; }
thead { background-color: #611010ff; color: white; }
tbody tr:nth-child(even) { background-color: #f9f9f9; }
tbody tr:hover { background-color: #f1d4d4; }
.jurado-checkbox { margin-bottom: 4px; }
</style>
</head>
<body class="bg-light">

<!-- BARRA DE MENÚ CON ORDEN EXACTO -->
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
<h2><?= $grupoEditandoId !== null ? "Editar Grupo #".$grupoEditandoId : "Crear Grupo de Jurados" ?></h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php elseif ($mensaje): ?>
<div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form method="POST" class="card p-3 mb-4">
<?php if ($grupoEditandoId !== null): ?>
<input type="hidden" name="editando_id" value="<?= $grupoEditandoId ?>">
<?php endif; ?>

<div class="mb-2" style="max-height:150px; overflow-y:auto;">
<?php foreach ($jurados as $id => $j): ?>
<?php
$mostrar = true;
if (juradoAsignadoDistintoGrupo($conn, $id, $grupoEditandoId)) $mostrar = false;
if ($mostrar):
$checked = ($grupoEditando && in_array($id, $grupoEditando['jurados'])) ? 'checked' : '';
?>
<div class="form-check jurado-checkbox">
<input class="form-check-input" type="checkbox" name="jurado_ids[]" id="jurado_<?= $id ?>" value="<?= $id ?>" <?= $checked ?> />
<label class="form-check-label" for="jurado_<?= $id ?>"><?= htmlspecialchars($j['nombre']) ?> (<?= htmlspecialchars($j['rol']) ?>)</label>
</div>
<?php endif; ?>
<?php endforeach; ?>
</div>
<div class="text-start">
<button type="submit" class="btn btn-ocre"><?= $grupoEditandoId !== null ? "Guardar Cambios" : "Crear Grupo" ?></button>
</div>
</form>

<h3>Grupos Existentes</h3>
<table>
<thead>
<tr>
<th># Grupo</th>
<th>Jurado 1</th>
<th>Jurado 2</th>
<th>Jurado 3</th>
<th>Grupos Asignados</th>
<th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($grupos_jurados as $g): ?>
<tr>
<td><?= $g['id'] ?></td>
<?php
for ($i=1; $i<=3; $i++) {
    $jid = $g["jurado{$i}_id"];
    if (isset($jurados[$jid])) {
        $j = $jurados[$jid];
        echo "<td>" . htmlspecialchars($j['nombre']) . " (" . htmlspecialchars($j['rol']) . ")</td>";
    } else echo "<td><em>Sin asignar</em></td>";
}
?>
<td><?= $g['grupos_asignados'] ?? 0 ?></td>
<td>
<a href="?edit_grupo=<?= $g['id'] ?>" class="btn btn-sm btn-ocre">Editar</a>
<a href="?del_grupo_jurado=<?= $g['id'] ?>" class="btn btn-sm btn-gray" onclick="return confirm('¿Eliminar este grupo?')">Eliminar</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (count($grupos_jurados) === 0): ?>
<tr><td colspan="6" class="text-center"><em>No hay grupos registrados.</em></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
