<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
 Generado por qwen/qwen3-coder-30b

*/
require_once 'config.php';

// Crear tabla si no existe
$createTableSQL = "CREATE TABLE IF NOT EXISTS series (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

mysqli_query($link, $createTableSQL);

// Manejo de acciones POST
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'agregar':
                $titulo = trim($_POST['titulo']);
                $temporada = (int)$_POST['temporada'];
                $total_episodios = (int)$_POST['total_episodios'];
                $episodio_actual = (int)$_POST['episodio_actual'];
                $enlace = trim($_POST['enlace']);
                $comentarios = trim($_POST['comentarios']);

                // Validaciones
                if (empty($titulo)) {
                    $message = 'El título es obligatorio.';
                    $message_type = 'danger';
                    break;
                }
                if ($temporada < 1 || $temporada > 5) {
                    $message = 'La temporada debe ser entre 1 y 5.';
                    $message_type = 'danger';
                    break;
                }
                if ($total_episodios < 1) {
                    $message = 'El total de episodios debe ser al menos 1.';
                    $message_type = 'danger';
                    break;
                }
                if ($episodio_actual < 0 || $episodio_actual > $total_episodios) {
                    $message = 'El episodio actual debe estar entre 0 y el total de episodios.';
                    $message_type = 'danger';
                    break;
                }
                if (!empty($enlace) && !preg_match('/^https?:\/\//', $enlace)) {
                    $message = 'El enlace debe comenzar con http:// o https://.';
                    $message_type = 'danger';
                    break;
                }

                // Insertar serie
                $stmt = mysqli_prepare($link, "INSERT INTO series (titulo, temporada, total_episodios, episodio_actual, fecha_inicio, enlace, comentarios) VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssiiiis", $titulo, $temporada, $total_episodios, $episodio_actual, $enlace, $comentarios);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    $message = 'Serie agregada correctamente.';
                    $message_type = 'success';
                    header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']) . "?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
                    exit;
                } else {
                    mysqli_stmt_close($stmt);
                    $message = 'Error al agregar la serie.';
                    $message_type = 'danger';
                }
                break;

            case 'editar':
                $id = (int)$_POST['id'];
                $titulo = trim($_POST['titulo']);
                $temporada = (int)$_POST['temporada'];
                $total_episodios = (int)$_POST['total_episodios'];
                $episodio_actual = (int)$_POST['episodio_actual'];
                $enlace = trim($_POST['enlace']);
                $comentarios = trim($_POST['comentarios']);

                // Validaciones
                if (empty($titulo)) {
                    $message = 'El título es obligatorio.';
                    $message_type = 'danger';
                    break;
                }
                if ($temporada < 1 || $temporada > 5) {
                    $message = 'La temporada debe ser entre 1 y 5.';
                    $message_type = 'danger';
                    break;
                }
                if ($total_episodios < 1) {
                    $message = 'El total de episodios debe ser al menos 1.';
                    $message_type = 'danger';
                    break;
                }
                if ($episodio_actual < 0 || $episodio_actual > $total_episodios) {
                    $message = 'El episodio actual debe estar entre 0 y el total de episodios.';
                    $message_type = 'danger';
                    break;
                }
                if (!empty($enlace) && !preg_match('/^https?:\/\//', $enlace)) {
                    $message = 'El enlace debe comenzar con http:// o https://.';
                    $message_type = 'danger';
                    break;
                }

                // Actualizar serie
                $stmt = mysqli_prepare($link, "UPDATE series SET titulo=?, temporada=?, total_episodios=?, episodio_actual=?, enlace=?, comentarios=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, "ssiiiisi", $titulo, $temporada, $total_episodios, $episodio_actual, $enlace, $comentarios, $id);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    $message = 'Serie actualizada correctamente.';
                    $message_type = 'success';
                    header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']) . "?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
                    exit;
                } else {
                    mysqli_stmt_close($stmt);
                    $message = 'Error al actualizar la serie.';
                    $message_type = 'danger';
                }
                break;

            case 'eliminar':
                $id = (int)$_POST['id'];
                $stmt = mysqli_prepare($link, "DELETE FROM series WHERE id=?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    $message = 'Serie eliminada correctamente.';
                    $message_type = 'success';
                    header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']) . "?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
                    exit;
                } else {
                    mysqli_stmt_close($stmt);
                    $message = 'Error al eliminar la serie.';
                    $message_type = 'danger';
                }
                break;

            case 'terminada':
                $id = (int)$_POST['id'];
                $stmt = mysqli_prepare($link, "UPDATE series SET fecha_fin=CURDATE() WHERE id=? AND fecha_fin IS NULL");
                mysqli_stmt_bind_param($stmt, "i", $id);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    $message = 'Serie marcada como terminada.';
                    $message_type = 'success';
                    header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']) . "?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
                    exit;
                } else {
                    mysqli_stmt_close($stmt);
                    $message = 'Error al marcar la serie como terminada.';
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Obtener todas las series ordenadas por fecha de inicio descendente
$series = [];
$result = mysqli_query($link, "SELECT * FROM series ORDER BY fecha_inicio DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $series[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SerieLog</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #EBEBEB;
            padding-top: 70px;
            padding-bottom: 60px;
        }
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            height: 60px;
            background-color: #f5f5f5;
            padding-top: 10px;
            text-align: center;
            font-size: 0.9em;
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="#"><i class="fas fa-tv"></i> SerieLog</a>
    <span class="navbar-text">Generado por modelo no verificable el no verificable</span>
</nav>

<div class="container mt-4">
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <h2>Agregar Nueva Serie</h2>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <input type="hidden" name="accion" value="agregar">
        <div class="form-group">
            <label for="titulo">Título *</label>
            <input type="text" class="form-control" id="titulo" name="titulo" required>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="temporada">Temporada (1-5) *</label>
                <input type="number" class="form-control" id="temporada" name="temporada" min="1" max="5" value="1" required>
            </div>
            <div class="form-group col-md-6">
                <label for="total_episodios">Total Episodios *</label>
                <input type="number" class="form-control" id="total_episodios" name="total_episodios" min="1" value="1" required>
            </div>
        </div>
        <div class="form-group">
            <label for="episodio_actual">Episodio Actual</label>
            <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0" value="0">
        </div>
        <div class="form-group">
            <label for="enlace">Enlace (opcional)</label>
            <input type="url" class="form-control" id="enlace" name="enlace">
        </div>
        <div class="form-group">
            <label for="comentarios">Comentarios (opcional)</label>
            <textarea class="form-control" id="comentarios" name="comentarios" rows="3"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Agregar Serie</button>
    </form>

    <hr>

    <h2>Mis Series</h2>
    <?php if (empty($series)): ?>
        <p>No hay series registradas.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>Título</th>
                        <th>Temporada</th>
                        <th>Progreso</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Enlace</th>
                        <th>Comentarios</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($series as $serie): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($serie['titulo']); ?></td>
                            <td><?php echo (int)$serie['temporada']; ?></td>
                            <td>
                                <?php
                                $porcentaje = $serie['total_episodios'] > 0 ? round(($serie['episodio_actual'] / $serie['total_episodios']) * 100, 2) : 0;
                                echo "{$serie['episodio_actual']}/{$serie['total_episodios']} ({$porcentaje}%)";
                                ?>
                                <div class="progress mt-1">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $porcentaje; ?>%"></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($serie['fecha_inicio']); ?></td>
                            <td><?php echo $serie['fecha_fin'] ? htmlspecialchars($serie['fecha_fin']) : 'En curso'; ?></td>
                            <td>
                                <?php if (!empty($serie['enlace'])): ?>
                                    <a href="<?php echo htmlspecialchars($serie['enlace']); ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-external-link-alt"></i> <?php echo htmlspecialchars(parse_url($serie['enlace'], PHP_URL_HOST)); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($serie['comentarios']) ? htmlspecialchars(substr($serie['comentarios'], 0, 50)) . (strlen($serie['comentarios']) > 50 ? '...' : '') : ''; ?></td>
                            <td>
                                <a href="#editModal<?php echo $serie['id']; ?>" class="btn btn-sm btn-warning" data-toggle="modal">Editar</a>
                                <?php if (!$serie['fecha_fin']): ?>
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display:inline;">
                                        <input type="hidden" name="accion" value="terminada">
                                        <input type="hidden" name="id" value="<?php echo $serie['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Terminada</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta serie?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?php echo $serie['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal Editar -->
                        <div class="modal fade" id="editModal<?php echo $serie['id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                        <input type="hidden" name="accion" value="editar">
                                        <input type="hidden" name="id" value="<?php echo $serie['id']; ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Editar Serie</h5>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label for="edit_titulo<?php echo $serie['id']; ?>">Título *</label>
                                                <input type="text" class="form-control" id="edit_titulo<?php echo $serie['id']; ?>" name="titulo" value="<?php echo htmlspecialchars($serie['titulo']); ?>" required>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label for="edit_temporada<?php echo $serie['id']; ?>">Temporada (1-5) *</label>
                                                    <input type="number" class="form-control" id="edit_temporada<?php echo $serie['id']; ?>" name="temporada" min="1" max="5" value="<?php echo (int)$serie['temporada']; ?>" required>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label for="edit_total_episodios<?php echo $serie['id']; ?>">Total Episodios *</label>
                                                    <input type="number" class="form-control" id="edit_total_episodios<?php echo $serie['id']; ?>" name="total_episodios" min="1" value="<?php echo (int)$serie['total_episodios']; ?>" required>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="edit_episodio_actual<?php echo $serie['id']; ?>">Episodio Actual</label>
                                                <input type="number" class="form-control" id="edit_episodio_actual<?php echo $serie['id']; ?>" name="episodio_actual" min="0" value="<?php echo (int)$serie['episodio_actual']; ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="edit_enlace<?php echo $serie['id']; ?>">Enlace (opcional)</label>
                                                <input type="url" class="form-control" id="edit_enlace<?php echo $serie['id']; ?>" name="enlace" value="<?php echo htmlspecialchars($serie['enlace']); ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="edit_comentarios<?php echo $serie['id']; ?>">Comentarios (opcional)</label>
                                                <textarea class="form-control" id="edit_comentarios<?php echo $serie['id']; ?>" name="comentarios" rows="3"><?php echo htmlspecialchars($serie['comentarios']); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="footer">
    <p>SerieLog - Control de series<br>Licencia MIT<br>Generado por modelo no verificable el no verificable</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
