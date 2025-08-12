<?php
// Asegúrate que Conexion.php se incluye solo una vez y la variable $conexion está disponible
require_once 'Conexion.php'; 
global $conexion; // La conexión global

// =====================
// AGREGAR ESTUDIANTE
// =====================
if (!function_exists('agregarEstudiante')) {
    function agregarEstudiante($nombre, $correo, $carnet) {
        global $conexion;

        // Validar que no exista correo o carnet duplicado
        $check = $conexion->prepare("SELECT id FROM estudiantes WHERE correo = ? OR carnet = ?");
        $check->bind_param("ss", $correo, $carnet);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            return false; // Ya existe
        }
        $check->close();

        $stmt = $conexion->prepare("INSERT INTO estudiantes (nombre, correo, carnet) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $correo, $carnet);

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

// =====================
// AGREGAR JURADO
// =====================
if (!function_exists('agregarJurado')) {
    function agregarJurado($nombre, $correo, $rol) {
        global $conexion;

        $check = $conexion->prepare("SELECT id FROM jurados WHERE correo = ?");
        $check->bind_param("s", $correo);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            return false; // Ya existe
        }
        $check->close();

        $stmt = $conexion->prepare("INSERT INTO jurados (nombre, correo, rol) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $correo, $rol);

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

// =====================
// CREAR GRUPO DE ESTUDIANTES
// =====================
if (!function_exists('crearGrupoEstudiantes')) {
    function crearGrupoEstudiantes($grupo, $pre_especialidad, $dia, $hora, $aula, $estudiantes_ids) {
        global $conexion;

        // Validar que no se repita pre_especialidad
        $check = $conexion->prepare("SELECT id FROM grupos_estudiantes WHERE pre_especialidad = ?");
        $check->bind_param("s", $pre_especialidad);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $check->close();
            return false;
        }
        $check->close();

        // Validar número de grupo
        $check2 = $conexion->prepare("SELECT id FROM grupos_estudiantes WHERE grupo = ?");
        $check2->bind_param("s", $grupo);
        $check2->execute();
        $check2->store_result();
        if ($check2->num_rows > 0) {
            $check2->close();
            return false;
        }
        $check2->close();

        // Validar hora, día y aula
        $check3 = $conexion->prepare("SELECT id FROM grupos_estudiantes WHERE dia = ? AND hora = ? AND aula = ?");
        $check3->bind_param("sss", $dia, $hora, $aula);
        $check3->execute();
        $check3->store_result();
        if ($check3->num_rows > 0) {
            $check3->close();
            return false;
        }
        $check3->close();

        // Insertar grupo
        $stmt = $conexion->prepare("INSERT INTO grupos_estudiantes (grupo, pre_especialidad, dia, hora, aula) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $grupo, $pre_especialidad, $dia, $hora, $aula);
        if ($stmt->execute()) {
            $grupo_id = $conexion->insert_id;
            $stmt->close();

            // Insertar estudiantes al grupo
            foreach ($estudiantes_ids as $est_id) {
                $stmt2 = $conexion->prepare("INSERT INTO grupo_estudiante_detalle (grupo_id, estudiante_id) VALUES (?, ?)");
                $stmt2->bind_param("ii", $grupo_id, $est_id);
                $stmt2->execute();
                $stmt2->close();
            }
            return true;
        }
        $stmt->close();
        return false;
    }
}

// =====================
// CREAR GRUPO DE JURADOS
// =====================
if (!function_exists('crearGrupoJurados')) {
    function crearGrupoJurados($grupo_nombre, $jurados_ids) {
        global $conexion;

        $stmt = $conexion->prepare("INSERT INTO grupos_jurados (grupo_nombre) VALUES (?)");
        $stmt->bind_param("s", $grupo_nombre);

        if ($stmt->execute()) {
            $grupo_id = $conexion->insert_id;
            $stmt->close();

            foreach ($jurados_ids as $jur_id) {
                $stmt2 = $conexion->prepare("INSERT INTO grupo_jurado_detalle (grupo_id, jurado_id) VALUES (?, ?)");
                $stmt2->bind_param("ii", $grupo_id, $jur_id);
                $stmt2->execute();
                $stmt2->close();
            }
            return true;
        }
        $stmt->close();
        return false;
    }
}

// =====================
// IMPORTAR ESTUDIANTES DESDE EXCEL
// =====================
if (!function_exists('importarEstudiantesDesdeExcel')) {
    function importarEstudiantesDesdeExcel($rutaArchivo) {
        require_once 'vendor/autoload.php';
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($rutaArchivo);
        $hoja = $spreadsheet->getActiveSheet();

        foreach ($hoja->getRowIterator(2) as $fila) {
            $nombre = trim($hoja->getCell('A' . $fila->getRowIndex())->getValue());
            $correo = trim($hoja->getCell('B' . $fila->getRowIndex())->getValue());
            $carnet = trim($hoja->getCell('C' . $fila->getRowIndex())->getValue());

            if (!empty($nombre) && !empty($correo) && !empty($carnet) && ctype_digit($carnet)) {
                agregarEstudiante($nombre, $correo, $carnet);
            }
        }
    }
}


