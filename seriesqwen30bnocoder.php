<?php

/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
generado por qwen/qwen3-30b-a3b-2507 no es el coder */

require_once 'config.php';

// Identificación del modelo (no verificable)
$identificacion_modelo = 'no verificable';

// Manejo de formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación y procesamiento de agregar serie
    if (isset($_POST['agregar_serie'])) {
        $titulo = trim($_POST['titulo'] ?? '');
        $temporada = (int)($_POST['temporada'] ?? 1);
        $total_episodios = (int)($_POST['total_episodios'] ?? 1);
        $episodio_actual = (int)($_POST['episodio_actual'] ?? 0);
        $enlace = trim($_POST['enlace'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        // Validaciones
        $errores = [];
        if (empty($titulo)) {
            $errores[] = 'El título es obligatorio.';
        }
        if ($temporada < 1 || $temporada > 5) {
            $errores[] = 'La temporada debe ser un número entre 1 y 5.';
        }
        if ($total_episodios < 1) {
            $errores[] = 'El total de episodios debe ser al menos 1.';
        }
        if ($episodio_actual < 0 || $episodio_actual > $total_episodios) {
            $errores[] = 'El episodio actual debe estar entre 0 y el total de episodios.';
        }
        if (!empty($enlace) && !preg_match('/^https?:\/\//', $enlace)) {
            $errores[] = 'El enlace debe comenzar con http:// o https://.';
        }

        if (empty($errores)) {
            // Preparar y ejecutar inserción
            $stmt = $link->prepare("INSERT INTO series (titulo, temporada, total_episodios, episodio_actual, enlace, comentarios) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("siiiss", $titulo, $temporada, $total_episodios, $episodio_actual, $enlace, $comentarios);
                if ($stmt->execute()) {
                    // Redirigir con mensaje de éxito
                    header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']) . '?mensaje=serie_agregada');
                    exit;
                }
                $errores[] = 'Error al guardar la serie.';
            } else {
                $errores[] = 'Error en la preparación de la consulta.';
            }
        }

        // Si hay errores, los mostramos (no redirigimos)
        $mensaje_error = implode('<br>', $errores);
    }

    // Manejo de "Terminada"
    if (isset($_POST['terminada'])) {
        $id = (int)$_POST['id'];
        $stmt = $link->prepare("UPDATE series SET fecha_fin = CURDATE() WHERE id = ? AND fecha_fin IS NULL");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']) . '?mensaje=terminada');
                exit;
            }
        }
    }

    // Manejo de edición
    if (isset($_POST['editar_serie'])) {
        $id = (int)$_POST['id'];
        $titulo = trim($_POST['titulo'] ?? '');
        $temporada = (int)($_POST['temporada'] ?? 1);
        $total_episodios = (int)($_POST['total_episodios'] ?? 1);
        $episodio_actual = (int)($_POST['episodio_actual'] ?? 0);
        $enlace = trim($_POST['enlace'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        $errores = [];
        if (empty($titulo)) {
            $errores[] = 'El título es obligatorio.';
        }
        if ($temporada < 1 || $temporada > 5) {
            $errores[] = 'La temporada debe ser un número entre 1 y 5.';
        }
        if ($total_episodios < 1) {
            $errores[] = 'El total de episodios debe ser al menos 1.';
        }
        if ($episodio_actual < 0 || $episodio_actual > $total_episodios) {
            $errores[] = 'El episodio actual debe estar entre 0 y el total de episodios.';
        }
        if (!empty($enlace) && !preg_match('/^https?:\/\//', $enlace)) {
            $errores[] = 'El enlace debe comenzar con http:// o https://.';
        }

        if (empty($errores)) {
            $stmt = $link->prepare("UPDATE series SET titulo = ?, temporada = ?, total_episodios = ?, episodio_actual = ?, enlace = ?, comentarios = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("siiissi", $titulo, $temporada, $total_episodios, $episodio_actual, $enlace, $comentarios, $id);
                if ($stmt->execute()) {
                    header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']) . '?mensaje=editada');
                    exit;
                }
            }
        }

        $mensaje_error = implode('<br>', $errores);
    }

    // Manejo de eliminación
    if (isset($_POST['eliminar_serie'])) {
        $id = (int)$_POST['id'];
        $stmt = $link->prepare("DELETE FROM series WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']) . '?mensaje=eliminada');
                exit;
            }
        }
    }
}

// Manejo de mensajes de éxito
$mensaje = $_GET['mensaje'] ?? '';
if ($mensaje === 'serie_agregada') {
    $mensaje_html = '<div class="alert alert-success alert-dismissible fade show" role="alert">Serie agregada exitosamente.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
} elseif ($mensaje === 'editada') {
    $mensaje_html = '<div class="alert alert-success alert-dismissible fade show" role="alert">Serie actualizada exitosamente.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
} elseif ($mensaje === 'terminada') {
    $mensaje_html = '<div class="alert alert-success alert-dismissible fade show" role="alert">Serie marcada como terminada.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
} elseif ($mensaje === 'eliminada') {
    $mensaje_html = '<div class="alert alert-success alert-dismissible fade show" role="alert">Serie eliminada.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
} else {
    $mensaje_html = '';
}

// Crear tabla si no existe
$link->query("CREATE TABLE IF NOT EXISTS series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    temporada TINYINT UNSIGNED NOT NULL DEFAULT 1,
    total_episodios SMALLINT UNSIGNED NOT NULL,
    episodio_actual SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    enlace VARCHAR(500) NULL,
    comentarios TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Obtener todas las series
$result = $link->query("SELECT * FROM series ORDER BY created_at DESC");
$series = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SerieLog</title>
    <!-- Bootstrap 4.6.2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #EBEBEB;
            padding-top: 56px;
            padding-bottom: 56px;
        }
        .navbar {
            background-color: #343a40;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .navbar-brand {
            color: #ffffff !important;
        }
        .footer {
            background-color: #343a40;
            color: #ffffff;
            padding: 1rem 0;
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
        }
        .table-responsive {
            margin-top: 1rem;
        }
        .progress {
            height: 10px;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .enlace-btn {
            font-size: 0.85rem;
        }
        .enlace-btn i {
            margin-right: 0.25rem;
        }
        .enlace-btn:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tv mr-2"></i>SerieLog
            </a>
            <span class="navbar-text text-light small">
                Generado por <?php echo htmlspecialchars($identificacion_modelo); ?>
            </span>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="container mt-4">
        <h1 class="text-center mb-4">SerieLog</h1>

        <!-- Mensaje de éxito -->
        <?php echo $mensaje_html; ?>

        <!-- Formulario de agregar serie -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Agregar nueva serie</h5>
            </div>
            <div class="card-body">
                <?php if (isset($mensaje_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $mensaje_error; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="agregar_serie" value="1">
                    <div class="form-group">
                        <label for="titulo">Título *</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="temporada">Temporada</label>
                        <select class="form-control" id="temporada" name="temporada">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i === (int)($_POST['temporada'] ?? 1)) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="total_episodios">Total de episodios *</label>
                        <input type="number" class="form-control" id="total_episodios" name="total_episodios" min="1" value="<?php echo htmlspecialchars($_POST['total_episodios'] ?? 1); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="episodio_actual">Episodio actual (opcional)</label>
                        <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0" max="<?php echo htmlspecialchars($_POST['total_episodios'] ?? 1); ?>" value="<?php echo htmlspecialchars($_POST['episodio_actual'] ?? 0); ?>">
                    </div>
                    <div class="form-group">
                        <label for="enlace">Enlace (opcional)</label>
                        <input type="url" class="form-control" id="enlace" name="enlace" placeholder="https://www.ejemplo.com" value="<?php echo htmlspecialchars($_POST['enlace'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="comentarios">Comentarios (opcional)</label>
                        <textarea class="form-control" id="comentarios" name="comentarios" rows="2"><?php echo htmlspecialchars($_POST['comentarios'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Agregar serie</button>
                </form>
            </div>
        </div>

        <!-- Tabla de series -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>Título</th>
                        <th>Progreso</th>
                        <th>Fecha inicio</th>
                        <th>Fecha fin</th>
                        <th>Enlace</th>
                        <th>Comentarios</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($series)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay series registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($series as $serie): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($serie['titulo']); ?></td>
                                <td>
                                    <?php
                                    $progreso = $serie['total_episodios'] > 0 ? ($serie['episodio_actual'] / $serie['total_episodios']) * 100 : 0;
                                    ?>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $progreso; ?>%;" aria-valuenow="<?php echo $progreso; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $serie['episodio_actual']; ?>/<?php echo $serie['total_episodios']; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($serie['fecha_inicio']); ?></td>
                                <td>
                                    <?php if ($serie['fecha_fin']): ?>
                                        <?php echo htmlspecialchars($serie['fecha_fin']); ?>
                                    <?php else: ?>
                                        <span class="text-warning">En curso</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($serie['enlace']): ?>
                                        <?php
                                        $dominio = parse_url($serie['enlace'], PHP_URL_HOST);
                                        $dominio = htmlspecialchars($dominio);
                                        ?>
                                        <a href="<?php echo htmlspecialchars($serie['enlace']); ?>" class="btn btn-sm enlace-btn btn-outline-primary" target="_blank" rel="noopener noreferrer">
                                            <i class="fas fa-external-link-alt"></i> <?php echo $dominio; ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($serie['comentarios'] ?: '-'); ?></td>
                                <td>
                                    <!-- Botón "Terminada" -->
                                    <?php if (!$serie['fecha_fin']): ?>
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display: inline;">
                                            <input type="hidden" name="id" value="<?php echo $serie['id']; ?>">
                                            <input type="hidden" name="terminada" value="1">
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('¿Marcar esta serie como terminada?');">
                                                <i class="fas fa-check-circle"></i> Terminada
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Botón Editar -->
                                    <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editarModal<?php echo $serie['id']; ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>

                                    <!-- Botón Eliminar -->
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $serie['id']; ?>">
                                        <input type="hidden" name="eliminar_serie" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar esta serie?');">
                                            <i class="fas fa-trash-alt"></i> Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Modal de edición -->
                            <div class="modal fade" id="editarModal<?php echo $serie['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editarModalLabel<?php echo $serie['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editarModalLabel<?php echo $serie['id']; ?>">Editar serie</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                            <input type="hidden" name="id" value="<?php echo $serie['id']; ?>">
                                            <input type="hidden" name="editar_serie" value="1">
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <label for="titulo_edit_<?php echo $serie['id']; ?>">Título *</label>
                                                    <input type="text" class="form-control" id="titulo_edit_<?php echo $serie['id']; ?>" name="titulo" value="<?php echo htmlspecialchars($serie['titulo']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="temporada_edit_<?php echo $serie['id']; ?>">Temporada</label>
                                                    <select class="form-control" id="temporada_edit_<?php echo $serie['id']; ?>" name="temporada">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <option value="<?php echo $i; ?>" <?php echo ($i === (int)$serie['temporada']) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="total_episodios_edit_<?php echo $serie['id']; ?>">Total de episodios *</label>
                                                    <input type="number" class="form-control" id="total_episodios_edit_<?php echo $serie['id']; ?>" name="total_episodios" min="1" value="<?php echo htmlspecialchars($serie['total_episodios']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="episodio_actual_edit_<?php echo $serie['id']; ?>">Episodio actual</label>
                                                    <input type="number" class="form-control" id="episodio_actual_edit_<?php echo $serie['id']; ?>" name="episodio_actual" min="0" max="<?php echo htmlspecialchars($serie['total_episodios']); ?>" value="<?php echo htmlspecialchars($serie['episodio_actual']); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label for="enlace_edit_<?php echo $serie['id']; ?>">Enlace</label>
                                                    <input type="url" class="form-control" id="enlace_edit_<?php echo $serie['id']; ?>" name="enlace" placeholder="https://www.ejemplo.com" value="<?php echo htmlspecialchars($serie['enlace'] ?? ''); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label for="comentarios_edit_<?php echo $serie['id']; ?>">Comentarios</label>
                                                    <textarea class="form-control" id="comentarios_edit_<?php echo $serie['id']; ?>" name="comentarios" rows="2"><?php echo htmlspecialchars($serie['comentarios'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                                                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <span>SerieLog – Control de series</span><br>
            <small>Licencia MIT | Generado por <?php echo htmlspecialchars($identificacion_modelo); ?></small>
        </div>
    </footer>

    <!-- Bootstrap JS y Popper.js desde jsDelivr -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
