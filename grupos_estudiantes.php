<?php
session_start();
include 'funciones.php';
require_once 'Conexion.php';
global $conexion;

// --- FUNCIONES BD: GUARDAR, ACTUALIZAR Y ELIMINAR ---
function guardarGrupoBD($grupo_jurado_id, $pre_especialidad, $dia, $hora, $aula, $grupo, $estudiantes, $tema) {
    global $conexion;
    $stmt = $conexion->prepare("INSERT INTO grupos_estudiantes (grupo_jurado_id, pre_especialidad, dia, hora, aula, grupo, tema) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("issssss", $grupo_jurado_id, $pre_especialidad, $dia, $hora, $aula, $grupo, $tema);

    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt_det = $conexion->prepare("INSERT INTO grupos_estudiantes_detalle (grupo_estudiante_id, estudiante_id) VALUES (?, ?)");
        foreach ($estudiantes as $eid) {
            $stmt_det->bind_param("ii", $id, $eid);
            $stmt_det->execute();
        }
        $stmt_det->close();
        return $id;
    }
    return false;
}

function actualizarGrupoBD($id, $grupo_jurado_id, $pre_especialidad, $dia, $hora, $aula, $grupo, $estudiantes, $tema) {
    global $conexion;

    $id = intval($id); // <-- Convertir a entero

    // Actualizar grupo principal
    $stmt = $conexion->prepare("UPDATE grupos_estudiantes SET grupo_jurado_id=?, pre_especialidad=?, dia=?, hora=?, aula=?, grupo=?, tema=? WHERE id=?");
    $stmt->bind_param("issssssi", $grupo_jurado_id, $pre_especialidad, $dia, $hora, $aula, $grupo, $tema, $id);

    if ($stmt->execute()) {
        // Primero eliminamos los detalles antiguos
        $conexion->query("DELETE FROM grupos_estudiantes_detalle WHERE grupo_estudiante_id=".$id);

        // Insertamos los estudiantes nuevos
        $stmt_det = $conexion->prepare("INSERT INTO grupos_estudiantes_detalle (grupo_estudiante_id, estudiante_id) VALUES (?, ?)");
        foreach ($estudiantes as $eid) {
            $eid = intval($eid); // <-- Convertir a entero
            $stmt_det->bind_param("ii", $id, $eid);
            $stmt_det->execute();
        }
        $stmt_det->close();
        return true;
    }
    return false;
}

function eliminarGrupoBD($id) {
    global $conexion;
    $conexion->query("DELETE FROM grupos_estudiantes_detalle WHERE grupo_estudiante_id=".$id);
    $conexion->query("DELETE FROM grupos_estudiantes WHERE id=".$id);
}

// --- CARGAR JURADOS ---
if (!isset($_SESSION['jurados']) || empty($_SESSION['jurados'])) {
    $_SESSION['jurados'] = [];
    $result = $conexion->query("SELECT * FROM jurados");
    while ($row = $result->fetch_assoc()) {
        $_SESSION['jurados'][$row['id']] = $row;
    }
} 

// --- CARGAR ESTUDIANTES ---
if (!isset($_SESSION['estudiantes']) || empty($_SESSION['estudiantes'])) {
    $_SESSION['estudiantes'] = [];
    $result = $conexion->query("SELECT * FROM estudiantes");
    while ($row = $result->fetch_assoc()) {
        $_SESSION['estudiantes'][$row['id']] = $row;
    }
}

// --- CARGAR GRUPOS DE JURADOS (SIEMPRE ACTUALIZADO DESDE BD) ---
$_SESSION['grupos_jurados'] = [];
$result = $conexion->query("SELECT * FROM grupos_jurados");
while ($grupo = $result->fetch_assoc()) {
    $jurados_asignados = [];
    for ($i = 1; $i <= 3; $i++) {
        $key = 'jurado'.$i.'_id';
        if (!empty($grupo[$key])) {
            $stmtJ = $conexion->prepare("SELECT nombre, correo, rol FROM jurados WHERE id = ?");
            $stmtJ->bind_param("i", $grupo[$key]);
            $stmtJ->execute();
            $resJ = $stmtJ->get_result();
            if ($resJ && $juradoData = $resJ->fetch_assoc()) {
                $jurados_asignados[] = [
                    'id' => $grupo[$key],
                    'nombre' => $juradoData['nombre'],
                    'correo' => $juradoData['correo'],
                    'rol' => $juradoData['rol']
                ];
            }
            $stmtJ->close();
        }
    }

    $_SESSION['grupos_jurados'][(int)$grupo['id']] = [
        'grupo_nombre' => $grupo['grupo_nombre'] ?? "Grupo ".(int)$grupo['id'],
        'jurados' => $jurados_asignados,
        'grupos_asignados' => 0,
    ];
}

// --- CARGAR GRUPOS DE ESTUDIANTES DESDE BD ---
if (!isset($_SESSION['grupos_estudiantes']) || empty($_SESSION['grupos_estudiantes'])) {
    $_SESSION['grupos_estudiantes'] = [];
    $result = $conexion->query("SELECT * FROM grupos_estudiantes");
    while ($grupo = $result->fetch_assoc()) {
        $estudiantes = [];
        $res_det = $conexion->query("SELECT estudiante_id FROM grupos_estudiantes_detalle WHERE grupo_estudiante_id=".$grupo['id']);
        while ($det = $res_det->fetch_assoc()) {
            $estudiantes[] = $det['estudiante_id'];
        }
        $_SESSION['grupos_estudiantes'][$grupo['id']] = [
            'grupo_jurado_id' => $grupo['grupo_jurado_id'],
            'pre_especialidad' => $grupo['pre_especialidad'],
            'dia' => $grupo['dia'],
            'hora' => $grupo['hora'],
            'aula' => $grupo['aula'],
            'grupo' => $grupo['grupo'], 
            'tema' => $grupo['tema'],
            'estudiantes' => $estudiantes
        ];
    }
}

// --- FUNCIONES DE VALIDACIÓN ---
function estudianteDisponible($id, $edit_id = null) {
    foreach ($_SESSION['grupos_estudiantes'] as $gid => $grupo) {
        if ($edit_id !== null && $gid == $edit_id) continue;
        if (in_array($id, $grupo['estudiantes'])) return false;
    }
    return true;
}

function preEspecialidadDisponible($pre_especialidad, $edit_id = null) {
    foreach ($_SESSION['grupos_estudiantes'] as $gid => $grupo) {
        if ($edit_id !== null && $gid == $edit_id) continue;
        if (strcasecmp($grupo['pre_especialidad'], $pre_especialidad) === 0) return false;
    }
    return true;
}

function grupoNumeroDisponible($grupo_num, $edit_id = null) {
    foreach ($_SESSION['grupos_estudiantes'] as $gid => $grupo) {
        if ($edit_id !== null && $gid == $edit_id) continue;
        if (strcasecmp($grupo['grupo'], $grupo_num) === 0) return false;
    }
    return true;
}

function horaAulaDisponible($dia, $hora, $aula, $edit_id = null) {
    foreach ($_SESSION['grupos_estudiantes'] as $gid => $grupo) {
        if ($edit_id !== null && $gid == $edit_id) continue;
        if ($grupo['dia'] === $dia && $grupo['hora'] === $hora && strcasecmp($grupo['aula'], $aula) === 0) return false;
    }
    return true;
}

// --- CONTAR GRUPOS ASIGNADOS ---
foreach ($_SESSION['grupos_jurados'] as $id => &$grupo_jurado) $grupo_jurado['grupos_asignados'] = 0;
unset($grupo_jurado);
foreach ($_SESSION['grupos_estudiantes'] as $grupo_estudiante) {
    $gid = $grupo_estudiante['grupo_jurado_id'];
    if (isset($_SESSION['grupos_jurados'][$gid])) $_SESSION['grupos_jurados'][$gid]['grupos_asignados']++;
}

// --- ERROR Y MENSAJE DE ÉXITO ---
$error = null;
$mensaje_exito = null;

// --- CREAR O EDITAR GRUPO ---
$edit_group = null;
$edit_id = null;

if (isset($_POST['add_grupo_estudiantes']) || isset($_POST['edit_grupo_estudiantes'])) {
    $grupo_jurado_id = $_POST['grupo_jurado_id'];
    $estudiantes_ids = $_POST['estudiantes_ids'] ?? [];
    $pre_especialidad = trim($_POST['pre_especialidad']);
    $dia = trim($_POST['dia']);
    $hora = trim($_POST['hora']);
    $aula = trim($_POST['aula']);
    $grupo = trim($_POST['grupo']); 
    $tema = trim($_POST['tema'] ?? '');

    $edit_id = intval($_POST['edit_id'] ?? 0); // <-- asegura que sea entero

    // Validaciones comunes
    if (count($estudiantes_ids) < 1 || count($estudiantes_ids) > 3) {
        $error = "Un grupo de estudiantes debe tener entre 1 y 3 miembros.";
    } else {
        foreach ($estudiantes_ids as $id) {
            if (!estudianteDisponible($id, $edit_id)) {
                $error = "Un estudiante ya está asignado a otro grupo.";
                break;
            }
        }
    }
    if (!$error && !preEspecialidadDisponible($pre_especialidad, $edit_id)) $error = "Ya existe un grupo con la misma Pre especialidad.";
    if (!$error && !grupoNumeroDisponible($grupo, $edit_id)) $error = "Ya existe un grupo con ese número asignado.";
    if (!$error && !horaAulaDisponible($dia, $hora, $aula, $edit_id)) $error = "Ya existe un grupo asignado a ese día, hora y aula.";

    if (!$error) {
        if (isset($_POST['add_grupo_estudiantes'])) {
            // CREAR NUEVO GRUPO
            if ($_SESSION['grupos_jurados'][$grupo_jurado_id]['grupos_asignados'] < 3) {
                $nuevo_id = guardarGrupoBD($grupo_jurado_id, $pre_especialidad, $dia, $hora, $aula, $grupo, $estudiantes_ids, $tema);
                if ($nuevo_id) {
                    $_SESSION['grupos_estudiantes'][$nuevo_id] = [
                        'grupo_jurado_id' => $grupo_jurado_id,
                        'estudiantes' => $estudiantes_ids,
                        'pre_especialidad' => $pre_especialidad,
                        'dia' => $dia,
                        'hora' => $hora,
                        'aula' => $aula,
                        'grupo' => $grupo, 
                        'tema' => $tema 
                    ];
                    $_SESSION['grupos_jurados'][$grupo_jurado_id]['grupos_asignados']++;
                    $_SESSION['mensaje_exito'] = "Grupo creado exitosamente.";
                    header("Location: grupos_estudiantes.php");
                    exit();
                }
            } else $error = "Este grupo de jurados ya tiene 3 grupos de estudiantes asignados.";

        } elseif (isset($_POST['edit_grupo_estudiantes'])) {
            // EDITAR GRUPO EXISTENTE
            if ($edit_id && isset($_SESSION['grupos_estudiantes'][$edit_id])) {
                if (actualizarGrupoBD($edit_id, $grupo_jurado_id, $pre_especialidad, $dia, $hora, $aula, $grupo, $estudiantes_ids, $tema)) {
                    // Actualiza la sesión
                    $old_id = $_SESSION['grupos_estudiantes'][$edit_id]['grupo_jurado_id'];
                    $_SESSION['grupos_jurados'][$old_id]['grupos_asignados']--;

                    $_SESSION['grupos_estudiantes'][$edit_id] = [
                        'grupo_jurado_id' => $grupo_jurado_id,
                        'estudiantes' => $estudiantes_ids,
                        'pre_especialidad' => $pre_especialidad,
                        'dia' => $dia,
                        'hora' => $hora,
                        'aula' => $aula,
                        'grupo' => $grupo,
                        'tema' => $tema
                    ];
                    $_SESSION['grupos_jurados'][$grupo_jurado_id]['grupos_asignados']++;

                    $_SESSION['mensaje_exito'] = "Grupo actualizado exitosamente.";
                    header("Location: grupos_estudiantes.php");
                    exit();
                } else {
                    $error = "Error al actualizar el grupo.";
                }
            } else {
                $error = "El grupo que intentas actualizar no existe.";
            }
        }
    }

    // Si hay error, conservar datos en formulario
    if ($error) {
        $edit_group = [
            'grupo_jurado_id' => $_POST['grupo_jurado_id'] ?? '',
            'pre_especialidad' => $_POST['pre_especialidad'] ?? '',
            'dia' => $_POST['dia'] ?? '',
            'hora' => $_POST['hora'] ?? '',
            'aula' => $_POST['aula'] ?? '',
            'grupo' => $_POST['grupo'] ?? '', 
            'tema' => $_POST['tema'] ?? '',
            'estudiantes' => $_POST['estudiantes_ids'] ?? []
        ];
    }
}

if (isset($_GET['del_grupo_estudiante'])) {
    $id = $_GET['del_grupo_estudiante'];
    eliminarGrupoBD($id);
    $gid = $_SESSION['grupos_estudiantes'][$id]['grupo_jurado_id'] ?? null;
    if ($gid !== null && isset($_SESSION['grupos_jurados'][$gid])) {
        $_SESSION['grupos_jurados'][$gid]['grupos_asignados'] = max(0, $_SESSION['grupos_jurados'][$gid]['grupos_asignados'] - 1);
    }
    unset($_SESSION['grupos_estudiantes'][$id]);
    $_SESSION['grupos_estudiantes'] = array_values($_SESSION['grupos_estudiantes']);
    $mensaje_exito = "Grupo eliminado exitosamente.";
}

if (isset($_GET['edit_grupo_estudiante'])) {
    $edit_id = intval($_GET['edit_grupo_estudiante']); // convierte a entero

    // Consultar directamente en BD
    $stmt = $conexion->prepare("SELECT * FROM grupos_estudiantes WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $grupo = $result->fetch_assoc();
    $stmt->close();

    if ($grupo) {
        $edit_group = [
            'grupo_jurado_id' => $grupo['grupo_jurado_id'],
            'pre_especialidad' => $grupo['pre_especialidad'],
            'dia' => $grupo['dia'],
            'hora' => $grupo['hora'],
            'aula' => $grupo['aula'],
            'grupo' => $grupo['grupo'], 
            'tema' => $grupo['tema'],
            'estudiantes' => []
        ];

        // Traer los estudiantes del grupo
        $res_det = $conexion->query("SELECT estudiante_id FROM grupos_estudiantes_detalle WHERE grupo_estudiante_id=".$edit_id);
        while ($det = $res_det->fetch_assoc()) {
            $edit_group['estudiantes'][] = $det['estudiante_id'];
        }
    } else {
        $edit_id = null;
        $edit_group = null;
        $error = "El grupo que intentas editar no existe.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Grupos de Estudiantes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.bg-ocre { background-color:#611010ff !important; }
.btn-ocre { background-color:#611010ff !important; border-color:#611010ff !important; color:#fff !important; }
.btn-ocre:hover { background-color:#4a0c0c !important; border-color:#4a0c0c !important; }
.btn-gray { background-color:#6c757d !important; border-color:#6c757d !important; color:#fff !important; }
.btn-gray:hover { background-color:#5a6268 !important; border-color:#545b62 !important; }
table { width:100%; border-collapse:collapse; margin-top:1rem; }
th, td { border:1px solid #611010ff; padding:8px 12px; text-align:left; vertical-align:middle; }
thead { background-color:#611010ff; color:white; }
tbody tr:nth-child(even){ background-color:#f9f9f9; }
tbody tr:hover { background-color:#f1d4d4; }
</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-ocre mb-4">
<div class="container-fluid">
<a class="navbar-brand" href="index.php">Gestión de Grupos</a>
<ul class="navbar-nav">
<li class="nav-item"><a class="nav-link" href="estudiantes.php">Estudiantes</a></li>
<li class="nav-item"><a class="nav-link" href="jurados.php">Jurados</a></li>
<li class="nav-item"><a class="nav-link" href="grupos_jurados.php">Grupos de Jurados</a></li>
<li class="nav-item"><a class="nav-link" href="grupos_estudiantes.php">Grupos de Estudiantes</a></li>
</ul>
</div>
</nav>

<div class="container mt-4">
<h2>Grupos de Estudiantes</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['mensaje_exito'])): ?>
<div class="alert alert-success" id="msgExito"><?= htmlspecialchars($_SESSION['mensaje_exito']) ?></div>
<script>
setTimeout(()=>{ document.getElementById('msgExito').style.display='none'; }, 4000);
</script>
<?php unset($_SESSION['mensaje_exito']); endif; ?>


<!-- FORMULARIO CREAR / EDITAR -->
<form method="POST" class="card p-3 mb-4">
<input type="hidden" name="edit_id" value="<?= $edit_id ?? '' ?>">
<label>Grupo de Jurados</label>
<select name="grupo_jurado_id" class="form-select mb-2" required>
<option value="">Seleccione...</option>
<?php foreach ($_SESSION['grupos_jurados'] as $id => $g): ?>
<option value="<?= $id ?>" <?= ($edit_group && $edit_group['grupo_jurado_id']==$id)?"selected":"" ?>>
Grupo Jurado <?= $g['grupo_nombre'] ?> (<?= $g['grupos_asignados'] ?>/3)
</option>
<?php endforeach; ?>
</select>

<label>Pre especialidad</label>
<input type="text" name="pre_especialidad" class="form-control mb-2" required value="<?= $edit_group['pre_especialidad'] ?? '' ?>">

<label>Tema</label>
<input type="text" name="tema" class="form-control mb-2" value="<?= $edit_group['tema'] ?? '' ?>">

<label>Día</label>
<input type="date" name="dia" class="form-control mb-2" required value="<?= $edit_group['dia'] ?? '' ?>">

<label>Hora</label>
<input type="time" name="hora" class="form-control mb-2" required value="<?= $edit_group['hora'] ?? '' ?>">

<label>AULA</label>
<input type="text" name="aula" class="form-control mb-2" required value="<?= $edit_group['aula'] ?? '' ?>">

<label>Grupo</label>
<input type="text" name="grupo" class="form-control mb-2" required value="<?= $edit_group['grupo'] ?? '' ?>">

<label>Estudiantes (1-3)</label>
<div class="mb-2" style="max-height:150px; overflow-y:auto;">
<?php foreach ($_SESSION['estudiantes'] as $id => $e): ?>
<?php 
$checked = $edit_group && in_array($id, $edit_group['estudiantes']) ? 'checked' : '';
if (!$edit_group && !estudianteDisponible($id)) continue;
if ($edit_group && !in_array($id, $edit_group['estudiantes']) && !estudianteDisponible($id)) continue;
?>
<div>
<input type="checkbox" name="estudiantes_ids[]" value="<?= $id ?>" <?= $checked ?>>
<?= htmlspecialchars($e['nombre']) ?> (Carné: <?= htmlspecialchars($e['carnet']) ?>)
</div>
<?php endforeach; ?>
</div>

<div class="text-start">
<button type="submit" name="<?= $edit_group?'edit_grupo_estudiantes':'add_grupo_estudiantes' ?>" class="btn btn-ocre">
<?= $edit_group?'Actualizar Grupo':'Crear Grupo' ?>
</button>
</div>
</form>

<!-- TABLA GRUPOS -->
<div class="table-responsive">
<table>
<thead>
<tr>
<th>Pre especialidad</th>
<th>Día</th>
<th>Hora</th>
<th>AULA</th>
<th>Grupo</th>
<th>Tema</th>
<th>Jurados</th>
<th>Estudiantes</th>
<th>Acciones</th>
</tr>

</thead>
<tbody>
<?php $contador = 1; ?>
<?php foreach ($_SESSION['grupos_estudiantes'] as $id => $g): ?>
<tr>
<td><?= htmlspecialchars($g['pre_especialidad']) ?></td>
<td><?= htmlspecialchars($g['dia']) ?></td>
<td><?= htmlspecialchars($g['hora']) ?></td>
<td><?= htmlspecialchars($g['aula']) ?></td>
<td><?= htmlspecialchars($g['grupo']) ?></td>
<td><?= htmlspecialchars($g['tema'] ?? '') ?></td>

<td>
<strong>Grupo Jurado #<?= $contador ?></strong>
<ul class="mb-0">
<?php 
$jurados = $_SESSION['grupos_jurados'][$g['grupo_jurado_id']]['jurados'] ?? [];
foreach ($jurados as $j) {
    echo "<li>".htmlspecialchars($j['nombre'])." <br><small>".htmlspecialchars($j['correo'])." | ".htmlspecialchars($j['rol'])."</small></li>";
}

?>
</ul>
</td>
<td>
<?php
foreach ($g['estudiantes'] as $eid) {
$e = $_SESSION['estudiantes'][$eid] ?? null;
if ($e) echo "<strong>Carné:</strong> ".htmlspecialchars($e['carnet'])."<br>".htmlspecialchars($e['nombre'])."<br><small>".htmlspecialchars($e['correo'])."</small><hr style='margin:4px 0;'>";
}
?>
</td>
<td>
<a href="?edit_grupo_estudiante=<?= $id ?>" class="btn btn-sm btn-ocre mb-1">Editar</a>
<a href="?del_grupo_estudiante=<?= $id ?>" onclick="return confirm('¿Está seguro de eliminar este grupo?');" class="btn btn-sm btn-gray">Eliminar</a>
</td>
</tr>
<?php $contador++; endforeach; ?>
<?php if (empty($_SESSION['grupos_estudiantes'])): ?>
<tr><td colspan="8" class="text-center"><em>No hay grupos de estudiantes registrados.</em></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
