<?php include 'funciones.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Grupos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .bg-ocre { background-color: #611010ff !important; }
        .btn-ocre {
            background-color: #611010ff !important;
            border-color: #611010ff !important;
            color: #fff !important;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn-ocre:hover {
            background-color: #4a0c0c !important;
            border-color: #4a0c0c !important;
            transform: scale(1.05);
        }
        .option-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .option-card:hover {
            transform: scale(1.05);
        }
        .option-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="bg-light">

<!-- Navbar solo con el nombre del sistema -->
<nav class="navbar navbar-expand-lg navbar-dark bg-ocre mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Gestión de Grupos</a>
  </div>
</nav>

<div class="container text-center">
    <h1 class="mb-4">Bienvenido al sistema de gestión</h1>
    <p>Selecciona una opción para administrar estudiantes, jurados o grupos.</p>

    <div class="row mt-4 justify-content-center g-4">
        <div class="col-md-3">
            <a href="estudiantes.php" class="text-decoration-none">
                <div class="card btn-ocre option-card p-4">
                    <i class="bi bi-person-fill option-icon"></i>
                    <h5>Estudiantes</h5>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="jurados.php" class="text-decoration-none">
                <div class="card btn-ocre option-card p-4">
                    <i class="bi bi-person-badge-fill option-icon"></i>
                    <h5>Jurados</h5>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="grupos_jurados.php" class="text-decoration-none">
                <div class="card btn-ocre option-card p-4">
                    <i class="bi bi-people-fill option-icon"></i>
                    <h5>Grupos de Jurados</h5>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="grupos_estudiantes.php" class="text-decoration-none">
                <div class="card btn-ocre option-card p-4">
                    <i class="bi bi-journal-text option-icon"></i>
                    <h5>Grupos de Estudiantes</h5>
                </div>
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
