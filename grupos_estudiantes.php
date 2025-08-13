<?php
session_start();
include 'funciones.php';
require_once 'Conexion.php';
global $conexion;

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

// --- CARGAR GRUPOS DE JURADOS ---
if (!isset($_SESSION['grupos_jurados']) || empty($_SESSION['grupos_jurados'])) {
    $_SESSION['grupos_jurados'] = [];
    $result = $conexion->query("SELECT * FROM grupos_jurados");
    while ($grupo = $result->fetch_assoc()) {
        $jurados_asignados = [];
        for ($i = 1; $i <= 3; $i++) {
            $key = 'jurado'.$i.'_id';
            if (!empty($grupo[$key])) $jurados_asignados[] = $grupo[$key];
        }
        $_SESSION['grupos_jurados'][$grupo['id']] = [
            'grupo_nombre' => $grupo['grupo_nombre'] ?? "Grupo ".$grupo['id'],
            'jurados' => $jurados_asignados,
            'grupos_asignados' => 0,
        ];
    }
}

// --- INICIALIZAR GRUPOS DE ESTUDIANTES ---
if (!isset($_SESSION['grupos_estudiantes'])) $_SESSION['grupos_estudiantes'] = [];

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

// --- ERROR ---
$error = null;

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
    $edit_id = $_POST['edit_id'] ?? null;

    if (count($estudiantes_ids) < 2 || count($estudiantes_ids) > 3) $error = "Un grupo de estudiantes debe tener entre 2 y 3 miembros.";
    else {
        foreach ($estudiantes_ids as $id) {
            if (!estudianteDisponible($id, $edit_id)) { $error = "Un estudiante ya está asignado a otro grupo."; break; }
        }
    }
    if (!$error && !preEspecialidadDisponible($pre_especialidad, $edit_id)) $error = "Ya existe un grupo con la misma Pre especialidad.";
    if (!$error && !grupoNumeroDisponible($grupo, $edit_id)) $error = "Ya existe un grupo con ese número asignado.";
    if (!$error && !horaAulaDisponible($dia, $hora, $aula, $edit_id)) $error = "Ya existe un grupo asignado a ese día, hora y aula.";

    if (!$error) {
        if (isset($_POST['add_grupo_estudiantes'])) {
            if ($_SESSION['grupos_jurados'][$grupo_jurado_id]['grupos_asignados'] < 3) {
                $_SESSION['grupos_estudiantes'][] = [
                    'grupo_jurado_id' => $grupo_jurado_id,
                    'estudiantes' => $estudiantes_ids,
                    'pre_especialidad' => $pre_especialidad,
                    'dia' => $dia,
                    'hora' => $hora,
                    'aula' => $aula,
                    'grupo' => $grupo
                ];
                $_SESSION['grupos_jurados'][$grupo_jurado_id]['grupos_asignados']++;
            } else $error = "Este grupo de jurados ya tiene 3 grupos de estudiantes asignados.";
        } elseif (isset($_POST['edit_grupo_estudiantes'])) {
            $old_id = $_SESSION['grupos_estudiantes'][$edit_id]['grupo_jurado_id'];
            $_SESSION['grupos_jurados'][$old_id]['grupos_asignados']--;
            $_SESSION['grupos_estudiantes'][$edit_id] = [
                'grupo_jurado_id' => $grupo_jurado_id,
                'estudiantes' => $estudiantes_ids,
                'pre_especialidad' => $pre_especialidad,
                'dia' => $dia,
                'hora' => $hora,
                'aula' => $aula,
                'grupo' => $grupo
            ];
            $_SESSION['grupos_jurados'][$grupo_jurado_id]['grupos_asignados']++;
        }
    }
}

// --- ELIMINAR ---
if (isset($_GET['del_grupo_estudiante'])) {
    $id = $_GET['del_grupo_estudiante'];
    $gid = $_SESSION['grupos_estudiantes'][$id]['grupo_jurado_id'] ?? null;
    if ($gid !== null) $_SESSION['grupos_jurados'][$gid]['grupos_asignados']--;
    unset($_SESSION['grupos_estudiantes'][$id]);
    $_SESSION['grupos_estudiantes'] = array_values($_SESSION['grupos_estudiantes']);
}

// --- EDITAR ---
if (isset($_GET['edit_grupo_estudiante'])) {
    $edit_id = $_GET['edit_grupo_estudiante'];
    $edit_group = $_SESSION['grupos_estudiantes'][$edit_id] ?? null;
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

<!-- FORMULARIO CREAR / EDITAR -->
<form method="POST" class="card p-3 mb-4">
<input type="hidden" name="edit_id" value="<?= $edit_group['id'] ?? '' ?>">
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

<label>Día</label>
<input type="date" name="dia" class="form-control mb-2" required value="<?= $edit_group['dia'] ?? '' ?>">

<label>Hora</label>
<input type="time" name="hora" class="form-control mb-2" required value="<?= $edit_group['hora'] ?? '' ?>">

<label>AULA</label>
<input type="text" name="aula" class="form-control mb-2" required value="<?= $edit_group['aula'] ?? '' ?>">

<label>Grupo</label>
<input type="text" name="grupo" class="form-control mb-2" required value="<?= $edit_group['grupo'] ?? '' ?>">

<label>Estudiantes (2-3)</label>
<div class="mb-2" style="max-height:150px; overflow-y:auto;">
<?php foreach ($_SESSION['estudiantes'] as $id => $e): ?>
<?php $checked = $edit_group && in_array($id,$edit_group['estudiantes']) ? 'checked' : ''; ?>
<?php if (!$edit_group && !estudianteDisponible($id)) continue; ?>
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
<table>
<thead>
<tr>
<th>Pre especialidad</th>
<th>Día</th>
<th>Hora</th>
<th>AULA</th>
<th>Grupo</th>
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
<td>
<strong>Grupo Jurado #<?= $contador ?></strong>
<ul class="mb-0">
<?php 
$jurados = $_SESSION['grupos_jurados'][$g['grupo_jurado_id']]['jurados'] ?? [];
foreach ($jurados as $jid) {
$j = $_SESSION['jurados'][$jid] ?? null;
if ($j) echo "<li>".htmlspecialchars($j['nombre'])." <br><small>".htmlspecialchars($j['correo'])." | ".htmlspecialchars($j['rol'])."</small></li>";
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
<a href="?del_grupo_estudiante=<?= $id ?>" class="btn btn-sm btn-gray">Eliminar</a>
</td>
</tr>
<?php $contador++; endforeach; ?>
<?php if (empty($_SESSION['grupos_estudiantes'])): ?>
<tr><td colspan="8" class="text-center"><em>No hay grupos de estudiantes registrados.</em></td></tr>
<?php endif; ?>
</tbody>
</table>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



