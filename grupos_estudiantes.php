<?php
session_start();
include 'funciones.php';
require_once 'Conexion.php';
global $conexion;

// Cargar jurados en sesión
if (!isset($_SESSION['jurados']) || empty($_SESSION['jurados'])) {
    $_SESSION['jurados'] = [];
    $result = $conexion->query("SELECT * FROM jurados");
    while ($row = $result->fetch_assoc()) {
        $_SESSION['jurados'][$row['id']] = $row;
    }
}

// Cargar estudiantes en sesión
if (!isset($_SESSION['estudiantes']) || empty($_SESSION['estudiantes'])) {
    $_SESSION['estudiantes'] = [];
    $result = $conexion->query("SELECT * FROM estudiantes");
    while ($row = $result->fetch_assoc()) {
        $_SESSION['estudiantes'][$row['id']] = $row;
    }
}

// Cargar grupos de jurados con jurados asignados
if (!isset($_SESSION['grupos_jurados']) || empty($_SESSION['grupos_jurados'])) {
    $_SESSION['grupos_jurados'] = [];
    $result = $conexion->query("SELECT * FROM grupos_jurados");
    while ($grupo = $result->fetch_assoc()) {
        $jurados_asignados = [];
        $stmt = $conexion->prepare("SELECT jurado_id FROM grupo_jurado_detalle WHERE grupo_id = ?");
        $stmt->bind_param("i", $grupo['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $jurados_asignados[] = $row['jurado_id'];
        }
        $stmt->close();

        $_SESSION['grupos_jurados'][$grupo['id']] = [
            'grupo_nombre' => $grupo['grupo_nombre'],
            'jurados' => $jurados_asignados,
            'grupos_asignados' => 0,
        ];
    }
}

// Inicializar grupos de estudiantes en sesión
if (!isset($_SESSION['grupos_estudiantes'])) {
    $_SESSION['grupos_estudiantes'] = [];
}

// Actualizar contador de grupos asignados a jurados
foreach ($_SESSION['grupos_jurados'] as $id => &$grupo_jurado) {
    $grupo_jurado['grupos_asignados'] = 0;
}
unset($grupo_jurado);

foreach ($_SESSION['grupos_estudiantes'] as $grupo_estudiante) {
    $gid = $grupo_estudiante['grupo_jurado_id'];
    if (isset($_SESSION['grupos_jurados'][$gid])) {
        $_SESSION['grupos_jurados'][$gid]['grupos_asignados']++;
    }
}

// Funciones para validar disponibilidad

function estudianteDisponible($id) {
    if (!isset($_SESSION['grupos_estudiantes'])) return true;
    foreach ($_SESSION['grupos_estudiantes'] as $grupo) {
        if (in_array($id, $grupo['estudiantes'])) {
            return false;
        }
    }
    return true;
}

function preEspecialidadDisponible($pre_especialidad) {
    if (!isset($_SESSION['grupos_estudiantes'])) return true;
    foreach ($_SESSION['grupos_estudiantes'] as $grupo) {
        if (strcasecmp($grupo['pre_especialidad'], $pre_especialidad) === 0) {
            return false;
        }
    }
    return true;
}

function grupoNumeroDisponible($grupo_num) {
    if (!isset($_SESSION['grupos_estudiantes'])) return true;
    foreach ($_SESSION['grupos_estudiantes'] as $grupo) {
        if (strcasecmp($grupo['grupo'], $grupo_num) === 0) {
            return false;
        }
    }
    return true;
}

function horaAulaDisponible($dia, $hora, $aula) {
    if (!isset($_SESSION['grupos_estudiantes'])) return true;
    foreach ($_SESSION['grupos_estudiantes'] as $grupo) {
        if ($grupo['dia'] === $dia && $grupo['hora'] === $hora && strcasecmp($grupo['aula'], $aula) === 0) {
            return false;
        }
    }
    return true;
}

$error = null;

// Crear nuevo grupo de estudiantes
if (isset($_POST['add_grupo_estudiantes'])) {
    $grupo_jurado_id = $_POST['grupo_jurado_id'];
    $estudiantes_ids = $_POST['estudiantes_ids'] ?? [];
    $pre_especialidad = trim($_POST['pre_especialidad']);
    $dia = trim($_POST['dia']);
    $hora = trim($_POST['hora']);
    $aula = trim($_POST['aula']);
    $grupo = trim($_POST['grupo']);

    if (count($estudiantes_ids) < 2 || count($estudiantes_ids) > 3) {
        $error = "Un grupo de estudiantes debe tener entre 2 y 3 miembros.";
    } else {
        foreach ($estudiantes_ids as $id) {
            if (!estudianteDisponible($id)) {
                $error = "Un estudiante ya está asignado a otro grupo.";
                break;
            }
        }
    }

    if (!$error && !preEspecialidadDisponible($pre_especialidad)) {
        $error = "Ya existe un grupo con la misma Pre especialidad.";
    }
    if (!$error && !grupoNumeroDisponible($grupo)) {
        $error = "Ya existe un grupo con ese número asignado.";
    }
    if (!$error && !horaAulaDisponible($dia, $hora, $aula)) {
        $error = "Ya existe un grupo asignado a ese día, hora y aula.";
    }
    if (!$error) {
        if (isset($_SESSION['grupos_jurados'][$grupo_jurado_id]) && $_SESSION['grupos_jurados'][$grupo_jurado_id]['grupos_asignados'] < 3) {
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
        } else {
            $error = "Este grupo de jurados ya tiene 3 grupos de estudiantes asignados.";
        }
    }
}

// Eliminar grupo de estudiantes
if (isset($_GET['del_grupo_estudiante'])) {
    $id = $_GET['del_grupo_estudiante'];
    $grupo_jurado_id = $_SESSION['grupos_estudiantes'][$id]['grupo_jurado_id'] ?? null;
    if ($grupo_jurado_id !== null && isset($_SESSION['grupos_jurados'][$grupo_jurado_id])) {
        $_SESSION['grupos_jurados'][$grupo_jurado_id]['grupos_asignados']--;
    }
    unset($_SESSION['grupos_estudiantes'][$id]);
    $_SESSION['grupos_estudiantes'] = array_values($_SESSION['grupos_estudiantes']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Grupos de Estudiantes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #222;
        }
        thead tr {
            background-color: #611010ff;
            color: white;
            text-align: left;
            font-weight: bold;
        }
        th, td {
            border: 1px solid #611010ff;
            padding: 8px 12px;
            vertical-align: top;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tbody tr:hover {
            background-color: #f1d4d4;
        }
        a.btn-sm.btn-danger {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
        .btn-ocre {
            background-color: #611010ff !important;
            border-color: #611010ff !important;
            color: #fff !important;
            transition: background-color 0.3s ease, transform 0.2s ease;
            padding: 4px 10px;
            font-size: 0.875rem;
        }
        .btn-ocre:hover {
            background-color: #4a0c0c !important;
            border-color: #4a0c0c !important;
            transform: scale(1.03);
        }
    </style>
</head>
<body class="bg-light">
<?php include 'index.php'; ?>

<div class="container mt-4">
    <h2>Grupos de Estudiantes</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-3 mb-4">
        <label>Grupo de Jurados</label>
        <select name="grupo_jurado_id" class="form-select mb-2" required>
            <option value="">Seleccione...</option>
            <?php foreach ($_SESSION['grupos_jurados'] as $id => $g): ?>
                <option value="<?= $id ?>">Grupo Jurado #<?= $id + 1 ?> (<?= $g['grupos_asignados'] ?>/3)</option>
            <?php endforeach; ?>
        </select>

        <label>Pre especialidad</label>
        <input type="text" name="pre_especialidad" class="form-control mb-2" required>

        <label>Día</label>
        <input type="date" name="dia" class="form-control mb-2" required>

        <label>Hora</label>
        <input type="time" name="hora" class="form-control mb-2" required>

        <label>AULA</label>
        <input type="text" name="aula" class="form-control mb-2" required>

        <label>Grupo</label>
        <input type="text" name="grupo" class="form-control mb-2" required>

        <label>Estudiantes (2-3)</label>
        <div class="mb-2" style="max-height:150px; overflow-y:auto;">
            <?php foreach ($_SESSION['estudiantes'] as $id => $e): ?>
                <?php if (estudianteDisponible($id)): ?>
                    <div>
                        <input type="checkbox" name="estudiantes_ids[]" value="<?= $id ?>"> 
                        <?= htmlspecialchars($e['nombre']) ?> (Carné: <?= htmlspecialchars($e['carnet']) ?>)
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="text-start">
            <button type="submit" name="add_grupo_estudiantes" class="btn btn-ocre">Crear Grupo</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Pre especialidad</th>
                <th>Día</th>
                <th>Hora</th>
                <th>AULA</th>
                <th>Grupo</th>
                <th>Grupo Jurado</th>
                <th>Estudiantes</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($_SESSION['grupos_estudiantes'] as $id => $g): ?>
                <tr>
                    <td><?= htmlspecialchars($g['pre_especialidad']) ?></td>
                    <td><?= htmlspecialchars($g['dia']) ?></td>
                    <td><?= htmlspecialchars($g['hora']) ?></td>
                    <td><?= htmlspecialchars($g['aula']) ?></td>
                    <td><?= htmlspecialchars($g['grupo']) ?></td>
                    <td>
                        Grupo Jurado #<?= $g['grupo_jurado_id'] + 1 ?><br>
                        <?php 
                            $jurados = $_SESSION['grupos_jurados'][$g['grupo_jurado_id']]['jurados'] ?? [];
                            foreach ($jurados as $jid) {
                                $j = $_SESSION['jurados'][$jid] ?? null;
                                if ($j) {
                                    echo htmlspecialchars($j['nombre']) . " (" . htmlspecialchars($j['correo']) . ", " . htmlspecialchars($j['rol']) . ")<br>";
                                }
                            }
                        ?>
                    </td>
                    <td>
                        <?php
                            foreach ($g['estudiantes'] as $eid) {
                                $e = $_SESSION['estudiantes'][$eid] ?? null;
                                if ($e) {
                                    echo "Carné: " . htmlspecialchars($e['carnet']) . "<br>" .
                                         htmlspecialchars($e['nombre']) . "<br>" .
                                         htmlspecialchars($e['correo']) . "<br><hr style='margin:4px 0;'>";
                                }
                            }
                        ?>
                    </td>
                    <td>
                        <a href="?del_grupo_estudiante=<?= $id ?>" class="btn btn-sm btn-danger">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($_SESSION['grupos_estudiantes'])): ?>
                <tr><td colspan="8" class="text-center"><em>No hay grupos de estudiantes registrados.</em></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>




