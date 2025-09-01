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
#mensaje-final {
    font-weight: bold;
    font-size: 1.2em;
    margin-top: 15px;
}
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
<tr><td>Formulación del problema</td><td>10%</td><td><input type="number" class="form-control nota-doc" max="10" step="0.1"></td></tr>
<tr><td>Objetivos y justificación de la investigación</td><td>10%</td><td><input type="number" class="form-control nota-doc" max="10" step="0.1"></td></tr>
<tr><td>Marco de referencia</td><td>20%</td><td><input type="number" class="form-control nota-doc" max="10" step="0.1"></td></tr>
<tr><td>Diseño de la solución</td><td>20%</td><td><input type="number" class="form-control nota-doc" max="10" step="0.1"></td></tr>
<tr><td>Análisis e interpretación de datos</td><td>20%</td><td><input type="number" class="form-control nota-doc" max="10" step="0.1"></td></tr>
<tr><td>Conclusiones y recomendaciones propuestas</td><td>20%</td><td><input type="number" class="form-control nota-doc" max="10" step="0.1"></td></tr>
</tbody>
<tfoot class="table-light">
<tr><th colspan="2">TOTAL</th><th><input type="text" class="form-control" id="total-doc" readonly></th></tr>
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
<tr><td><strong>1. DOMINIO DE LA TEMÁTICA:</strong> Denota que conoce la temática a tratar; orden y claridad en la exposición de ideas y una secuencia lógica en sus planteamiento; trascendentales de lo expuesto en el documento; complementándolo y enriqueciéndolo.</td><td>20%</td><td><input type="number" class="form-control nota-oral" max="10" step="0.1"></td></tr>
<tr><td><strong>2. CLARIDAD:</strong> Que la exposición sea dada en forma coherente y ordenada. Conceptos dados en forma sencilla, con lenguaje técnico apropiado y de preferencia ejemplificados.</td><td>20%</td><td><input type="number" class="form-control nota-oral" max="10" step="0.1"></td></tr>
<tr><td><strong>3. SÍNTESIS:</strong> Capacidad de plantear los aspectos más relevantes acerca de lo que se cuestiona. Utilización del tiempo de manera apropiada sin ser demasiado breve, ni muy extenso en sus respuestas.</td><td>20%</td><td><input type="number" class="form-control nota-oral" max="10" step="0.1"></td></tr>
<tr><td><strong>4. SEGURIDAD EN RESPUESTAS:</strong> Capacidad de dar respuestas a las interrogantes planteadas sin titubeos en un lapso de tiempo apropiado.</td><td>20%</td><td><input type="number" class="form-control nota-oral" max="10" step="0.1"></td></tr>
<tr><td><strong>5. PROYECCIÓN DE LA UNIVERSIDAD:</strong> Planteamiento de soluciones factibles de aplicarse que contribuyan al bienestar económico y social de la comunidad o que se refiere al trabajo. Aporte o sugerencias a la Universidad para mejorar su calidad académica, así como una mayor y mejor vinculación de esta con la realidad salvadoreña.</td><td>20%</td><td><input type="number" class="form-control nota-oral" max="10" step="0.1"></td></tr>
</tbody>
<tfoot class="table-light">
<tr><th colspan="2">TOTAL</th><th><input type="text" class="form-control" id="total-oral" readonly></th></tr>
</tfoot>
</table>

<div id="mensaje-final"></div> 
Para aprobar el trabajo de Graduación, el dictamen debe ser unánime por todos los miembros del Jurado. Con uno de los Jurados que haya dictaminado como Reprobado, de acuerdo a su calificación del dictamen final, será Reprobado.
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
const porcentajesDoc = [10,10,20,20,20,20];
const porcentajesOral = [20,20,20,20,20];

function calcularTotales(){
    let totalDoc = 0, totalOral = 0;
    let valid = true;

    document.querySelectorAll('.nota-doc').forEach((input,i)=>{
        const valor = input.value.trim();
        if(valor===''){ 
            valid = false; 
        } else {
            totalDoc += parseFloat(valor)*(porcentajesDoc[i]/100);
        }
    });

    document.querySelectorAll('.nota-oral').forEach((input,i)=>{
        const valor = input.value.trim();
        if(valor===''){ 
            valid = false; 
        } else {
            totalOral += parseFloat(valor)*(porcentajesOral[i]/100);
        }
    });

    const mensaje = document.getElementById('mensaje-final');

    if(valid){
        document.getElementById('total-doc').value = totalDoc.toFixed(2);
        document.getElementById('total-oral').value = totalOral.toFixed(2);
        const final = (totalDoc + totalOral)/2;
        if(final>=7){
            mensaje.innerHTML = `<div class="alert alert-success">✅ Aprobado con nota final: ${final.toFixed(2)}</div>`;
        } else {
            mensaje.innerHTML = `<div class="alert alert-danger">❌ Reprobado con nota final: ${final.toFixed(2)}</div>`;
        }
    } else {
        document.getElementById('total-doc').value = '';
        document.getElementById('total-oral').value = '';
        mensaje.innerHTML = `<div class="alert alert-warning">⚠️ Todas las casillas deben completarse antes de calcular.</div>`;
    }
}

// Mover foco al siguiente input con Enter solo si tiene valor
function handleEnterMove(e){
    if(e.key==='Enter'){
        e.preventDefault();
        if(e.target.value.trim()==='') return; // no permite pasar si está vacía
        const inputs = Array.from(document.querySelectorAll('.nota-doc, .nota-oral'));
        const index = inputs.indexOf(e.target);
        if(index >= 0 && index < inputs.length-1){
            inputs[index+1].focus();
        }
    }
}

// Limpiar todos los inputs al cargar
document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('.nota-doc, .nota-oral').forEach(input=>{
        input.value = '';
    });
});

document.querySelectorAll('.nota-doc, .nota-oral').forEach(input=>{
    input.addEventListener('input', calcularTotales);
    input.addEventListener('keypress', handleEnterMove);
});
</script>
</body>
</html>
