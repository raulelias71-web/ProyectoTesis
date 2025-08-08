<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['estudiantes'])) $_SESSION['estudiantes'] = [];
if (!isset($_SESSION['jurados'])) $_SESSION['jurados'] = [];
if (!isset($_SESSION['grupos_jurados'])) $_SESSION['grupos_jurados'] = [];
if (!isset($_SESSION['grupos_estudiantes'])) $_SESSION['grupos_estudiantes'] = [];

// Jurado disponible (por id)
if (!function_exists('juradoDisponible')) {
    function juradoDisponible($id) {
        foreach ($_SESSION['grupos_jurados'] as $grupo) {
            if (in_array($id, $grupo['jurados'])) {
                return false;
            }
        }
        return true;
    }
}

// Estudiante disponible (por id)
if (!function_exists('estudianteDisponible')) {
    function estudianteDisponible($id) {
        foreach ($_SESSION['grupos_estudiantes'] as $grupo) {
            if (in_array($id, $grupo['estudiantes'])) {
                return false;
            }
        }
        return true;
    }
}

// Validar que pre especialidad no esté repetida
if (!function_exists('preEspecialidadDisponible')) {
    function preEspecialidadDisponible($pre_especialidad) {
        foreach ($_SESSION['grupos_estudiantes'] as $grupo) {
            if (strcasecmp($grupo['pre_especialidad'], $pre_especialidad) === 0) {
                return false;
            }
        }
        return true;
    }
}

// Validar que número de grupo no esté repetido
if (!function_exists('grupoNumeroDisponible')) {
    function grupoNumeroDisponible($grupo_numero) {
        foreach ($_SESSION['grupos_estudiantes'] as $grupo) {
            if ($grupo['grupo'] === $grupo_numero) {
                return false;
            }
        }
        return true;
    }
}

// Validar que día, hora y aula no estén repetidos juntos
if (!function_exists('horaAulaDisponible')) {
    function horaAulaDisponible($dia, $hora, $aula) {
        foreach ($_SESSION['grupos_estudiantes'] as $grupo) {
            if ($grupo['dia'] === $dia && $grupo['hora'] === $hora && strcasecmp($grupo['aula'], $aula) === 0) {
                return false;
            }
        }
        return true;
    }
}

// Agregar estudiante manualmente
if (!function_exists('agregarEstudiante')) {
    function agregarEstudiante($nombre, $correo, $carnet) {
        if (!empty($nombre) && !empty($correo) && !empty($carnet) && ctype_digit($carnet)) {
            foreach ($_SESSION['estudiantes'] as $est) {
                if ($est['correo'] === $correo || $est['carnet'] === $carnet) {
                    return false;
                }
            }
            $_SESSION['estudiantes'][] = [
                'nombre' => $nombre,
                'correo' => $correo,
                'carnet' => $carnet
            ];
            return true;
        }
        return false;
    }
}

// Importar estudiantes desde Excel
if (!function_exists('importarEstudiantesDesdeExcel')) {
    function importarEstudiantesDesdeExcel($rutaArchivo) {
        require 'vendor/autoload.php';
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

// Descargar plantilla Excel
if (!function_exists('descargarPlantillaExcel')) {
    function descargarPlantillaExcel() {
        require 'vendor/autoload.php';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Nombre');
        $sheet->setCellValue('B1', 'Correo');
        $sheet->setCellValue('C1', 'Carné');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_estudiantes.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}
