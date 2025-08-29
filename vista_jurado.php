<?php
session_start();
include 'funciones.php';
require_once 'Conexion.php';
global $conexion;

// --- CARGAR TODOS LOS GRUPOS DESDE BD ---
$grupos_estudiantes = [];
$res_grupos = $conexion->query("SELECT * FROM grupos_estudiantes ORDER BY grupo ASC");
while ($grupo = $res_grupos->fetch_assoc()) {

    // Traer estudiantes
    $estudiantes = [];
    $res_det = $conexion->query("SELECT e.* 
                                 FROM grupos_estudiantes_detalle gd
                                 INNER JOIN estudiantes e ON gd.estudiante_id = e.id
                                 WHERE gd.grupo_estudiante_id=".$grupo['id']);
    while ($est = $res_det->fetch_assoc()) {
        $estudiantes[] = $est;
    }

    // Traer jurados asignados al grupo de jurados
    $jurados_asignados = [];
    $grupo_jurado_id = $grupo['grupo_jurado_id'] ?? null;
    if ($grupo_jurado_id) {
        $stmtJ = $conexion->prepare("SELECT jurado1_id, jurado2_id, jurado3_id FROM grupos_jurados WHERE id=?");
        $stmtJ->bind_param("i", $grupo_jurado_id);
        $stmtJ->execute();
        $resJ = $stmtJ->get_result();
        if ($resJ && $rowJ = $resJ->fetch_assoc()) {
            for ($i=1; $i<=3; $i++) {
                $jid = $rowJ["jurado{$i}_id"];
                if ($jid) {
                    $stmt = $conexion->prepare("SELECT nombre, correo, rol FROM jurados WHERE id=?");
                    $stmt->bind_param("i", $jid);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $juradoData = $res->fetch_assoc()) {
                        $jurados_asignados[] = $juradoData;
                    }
                    $stmt->close();
                }
            }
        }
        $stmtJ->close();
    }

    $grupos_estudiantes[$grupo['id']] = [
        'pre_especialidad' => $grupo['pre_especialidad'],
        'dia' => $grupo['dia'],
        'hora' => $grupo['hora'],
        'aula' => $grupo['aula'],
        'grupo' => $grupo['grupo'],
        'tema' => $grupo['tema'], 
        'estudiantes' => $estudiantes,
        'jurados' => $jurados_asignados
    ];
}

// --- SELECCIONAR GRUPO PARA VER DETALLE ---
$info_grupo = [];
$selected_id = null;
if (isset($_GET['grupo_id']) && isset($grupos_estudiantes[$_GET['grupo_id']])) {
    $selected_id = $_GET['grupo_id'];
    $info_grupo = $grupos_estudiantes[$selected_id];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Detalle Grupo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card-ocre {
    background-color:#fdf2f2;
    border:2px solid #611010ff;
    padding:20px;
    margin-top:20px;
    border-radius:8px;
}
.card-ocre h4 {
    color:#611010ff;
    margin-bottom:15px;
}
.card-ocre table { width:100%; border-collapse:collapse; margin-top:10px; }
.card-ocre th, .card-ocre td { border:1px solid #611010ff; padding:8px 12px; text-align:left; }
.card-ocre thead { background-color:#611010ff; color:white; }
.card-ocre ul { padding-left:20px; }
</style>
</head>
<body class="bg-light">
<div class="container mt-4">

<?php if($info_grupo): ?>
<div class="card card-ocre">
<h4>Detalle Grupo <?= htmlspecialchars($info_grupo['grupo']) ?></h4>
<p><strong>Pre especialidad:</strong> <?= htmlspecialchars($info_grupo['pre_especialidad']) ?></p>
<p><strong>Tema:</strong> <?= htmlspecialchars($info_grupo['tema']) ?></p>
<p><strong>Día:</strong> <?= htmlspecialchars($info_grupo['dia']) ?> | <strong>Hora:</strong> <?= htmlspecialchars($info_grupo['hora']) ?> | <strong>Aula:</strong> <?= htmlspecialchars($info_grupo['aula']) ?></p>

<h5>Jurados</h5>
<?php if(!empty($info_grupo['jurados'])): ?>
<ul>
<?php foreach($info_grupo['jurados'] as $j): ?>
<li><?= htmlspecialchars($j['nombre']) ?> | <?= htmlspecialchars($j['correo']) ?> | <?= htmlspecialchars($j['rol']) ?></li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p><em>No hay jurados asignados.</em></p>
<?php endif; ?>

<h5>Estudiantes</h5>
<?php if(!empty($info_grupo['estudiantes'])): ?>
<table>
<thead>
<tr>
<th>Carné</th>
<th>Nombre</th>
<th>Correo</th>
</tr>
</thead>
<tbody>
<?php foreach ($info_grupo['estudiantes'] as $e): ?>
<tr>
<td><?= htmlspecialchars($e['carnet']) ?></td>
<td><?= htmlspecialchars($e['nombre']) ?></td>
<td><?= htmlspecialchars($e['correo']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<p><em>No hay estudiantes asignados.</em></p>
<?php endif; ?>
</div>

<!-- SECCIÓN DE EVALUACIÓN -->
<div class="card card-ocre mt-4" id="evaluacion-container">
<h4>Evaluación de Documento Final</h4>
<table class="table table-bordered">
<thead class="table-warning">
<tr>
<th>CRITERIOS</th>
<th>PORCENTAJE</th>
<th>NOTA</th>
</tr>
</thead>
<tbody>
<tr>
<td>Formulación del problema</td>
<td>10%</td>
<td><input type="number" class="form-control nota-doc" value="0" max="10" step="0.1"></td>
</tr>
<tr>
<td>Objetivos y justificación de la investigación</td>
<td>10%</td>
<td><input type="number" class="form-control nota-doc" value="0" max="10" step="0.1"></td>
</tr>
<tr>
<td>Marco de referencia</td>
<td>20%</td>
<td><input type="number" class="form-control nota-doc" value="0" max="10" step="0.1"></td>
</tr>
<tr>
<td>Diseño de la solución</td>
<td>20%</td>
<td><input type="number" class="form-control nota-doc" value="0" max="10" step="0.1"></td>
</tr>
<tr>
<td>Análisis e interpretación de datos</td>
<td>20%</td>
<td><input type="number" class="form-control nota-doc" value="0" max="10" step="0.1"></td>
</tr>
<tr>
<td>Conclusiones y recomendaciones propuestas</td>
<td>20%</td>
<td><input type="number" class="form-control nota-doc" value="0" max="10" step="0.1"></td>
</tr>
</tbody>
<tfoot class="table-light">
<tr>
<th colspan="2">TOTAL</th>
<th><input type="text" class="form-control" id="total-doc" readonly></th>
</tr>
</tfoot>
</table>

<h4 class="mt-4">Evaluación de Defensa Oral</h4>
<table class="table table-bordered">
<thead class="table-warning">
<tr>
<th>CRITERIOS</th>
<th>PORCENTAJE</th>
<th>NOTA</th>
</tr>
</thead>
<tbody>
<tr>
<td>Dominio de la temática</td>
<td>20%</td>
<td><input type="number" class="form-control nota-oral" value="0" max="10" step="0.1"></td>
</tr>
<tr>
<td>Claridad</td>
<td>20%</td>
<td><input type="number" class="form-control nota-oral" value="0" max="10" step="0.1"></td>
</tr>
<tr>
<td>Síntesis</td>
<td>20%</td>
<td><input type="number" class="form-control nota-oral" value="0" max="10" step="0.1"></td>
</tr>
<tr>
<td>Seguridad en respuestas</td>
<td>20%</td>
<td><input type="number" class="form-control nota-oral" value="0" max="10" step="0.1"></td>
</tr>
<tr>
<td>Proyección de la universidad</td>
<td>20%</td>
<td><input type="number" class="form-control nota-oral" value="0" max="10" step="0.1"></td>
</tr>
</tbody>
<tfoot class="table-light">
<tr>
<th colspan="2">TOTAL</th>
<th><input type="text" class="form-control" id="total-oral" readonly></th>
</tr>
</tfoot>
</table>
</div>

<?php else: ?>
<p class="text-center"><em>Seleccione un grupo para ver el detalle.</em></p>
<?php endif; ?>

<!-- SELECTOR DE GRUPO -->
<div class="mt-3">
<form method="get">
<select name="grupo_id" class="form-select" onchange="this.form.submit()">
<option value="">Seleccione un grupo...</option>
<?php foreach($grupos_estudiantes as $id => $g): ?>
<option value="<?= $id ?>" <?= ($selected_id == $id)?'selected':'' ?>>
<?= htmlspecialchars($g['grupo']) ?> - <?= htmlspecialchars($g['pre_especialidad']) ?>
</option>
<?php endforeach; ?>
</select>
</form>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Calcular totales ponderados
const porcentajesDoc = [10,10,20,20,20,20];
const porcentajesOral = [20,20,20,20,20];

function calcularTotal(selector, porcentajes, totalId){
    const notas = document.querySelectorAll(selector);
    let total = 0;
    notas.forEach((input,i)=>{
        const valor = parseFloat(input.value)||0;
        total += (valor*porcentajes[i]/100);
    });
    document.getElementById(totalId).value = total.toFixed(2);
}

document.querySelectorAll('.nota-doc').forEach(input=>{
    input.addEventListener('input',()=>calcularTotal('.nota-doc',porcentajesDoc,'total-doc'));
});
document.querySelectorAll('.nota-oral').forEach(input=>{
    input.addEventListener('input',()=>calcularTotal('.nota-oral',porcentajesOral,'total-oral'));
});
</script>
</body>
</html>
