<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
Dolphin 24b
https://chat.dphn.ai/

*/
require_once 'config.php';

// Crear tabla si no existe
$link->query("CREATE TABLE IF NOT EXISTS `series` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `titulo` VARCHAR(150) NOT NULL,
    `temporada` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `total_episodios` SMALLINT UNSIGNED NOT NULL,
    `episodio_actual` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NULL,
    `enlace` VARCHAR(500) NULL,
    `comentarios` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Función para agregar serie
function agregarSerie($link) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])) {
        $titulo = trim($_POST['titulo']);
        $temporada = (int)$_POST['temporada'];
        $total_episodios = (int)$_POST['total_episodios'];
        $episodio_actual = (int)$_POST['episodio_actual'];
        $enlace = trim($_POST['enlace']);
        $comentarios = trim($_POST['comentarios']);

        if (empty($titulo) || $temporada < 1 || $temporada > 5 || $total_episodios < 1 || $episodio_actual < 0 || $episodio_actual > $total_episodios) {
            echo "<div class='alert alert-danger'>Datos inválidos. Verifica los campos.</div>";
            return;
        }

        if (!empty($enlace) && !preg_match('/^https?:\/\//', $enlace)) {
            echo "<div class='alert alert-danger'>El enlace debe comenzar con http:// o https://</div>";
            return;
        }

        $stmt = $link->prepare("INSERT INTO `series` (`titulo`, `temporada`, `total_episodios`, `episodio_actual`, `enlace`, `comentarios`, `fecha_inicio`) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
        $stmt->bind_param("siiisss", $titulo, $temporada, $total_episodios, $episodio_actual, $enlace, $comentarios);
        $stmt->execute();
        $stmt->close();

        header("Location: " . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?mensaje=Serie agregada");
        exit();
    }
}

// Función para mostrar tabla de series
function mostrarSeries($link) {
    $result = $link->query("SELECT * FROM `series` ORDER BY `created_at` DESC");
    if ($result->num_rows > 0) {
        echo '<div class="table-responsive"><table class="table table-striped">';
        echo '<thead><tr><th>Título</th><th>Temporada</th><th>Progreso</th><th>Fecha Inicio</th><th>Fecha Fin</th><th>Enlace</th><th>Comentarios</th><th>Acciones</th></tr></thead><tbody>';
        while ($row = $result->fetch_assoc()) {
            $progreso = $row['episodio_actual'] / $row['total_episodios'] * 100;
            $fecha_fin = $row['fecha_fin'] ? htmlspecialchars($row['fecha_fin'], ENT_QUOTES, 'UTF-8') : 'En curso';
            $enlace = !empty($row['enlace']) ? '<a href="' . htmlspecialchars($row['enlace'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" class="btn btn-info btn-sm"><i class="fas fa-link"></i> ' . parse_url($row['enlace'], PHP_URL_HOST) . '</a>' : '';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($row['temporada'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($row['episodio_actual'], ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($row['total_episodios'], ENT_QUOTES, 'UTF-8') . ' <div class="progress"><div class="progress-bar" role="progressbar" style="width: ' . $progreso . '%" aria-valuenow="' . $progreso . '" aria-valuemin="0" aria-valuemax="100"></div></div></td>';
            echo '<td>' . htmlspecialchars($row['fecha_inicio'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . $fecha_fin . '</td>';
            echo '<td>' . $enlace . '</td>';
            echo '<td>' . htmlspecialchars($row['comentarios'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?editar=' . $row['id'] . '" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a> ';
            if (empty($row['fecha_fin'])) {
                echo '<a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?terminada=' . $row['id'] . '" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Terminada</a> ';
            }
            echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" style="display:inline;" onsubmit="return confirm(\'¿Estás seguro de eliminar esta serie?\')"><input type="hidden" name="eliminar" value="' . $row['id'] . '"><button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-info">No hay series registradas.</div>';
    }
}

// Función para editar serie
function editarSerie($link) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
        $id = (int)$_POST['id'];
        $titulo = trim($_POST['titulo']);
        $temporada = (int)$_POST['temporada'];
        $total_episodios = (int)$_POST['total_episodios'];
        $episodio_actual = (int)$_POST['episodio_actual'];
        $enlace = trim($_POST['enlace']);
        $comentarios = trim($_POST['comentarios']);

        if (empty($titulo) || $temporada < 1 || $temporada > 5 || $total_episodios < 1 || $episodio_actual < 0 || $episodio_actual > $total_episodios) {
            echo "<div class='alert alert-danger'>Datos inválidos. Verifica los campos.</div>";
            return;
        }

        if (!empty($enlace) && !preg_match('/^https?:\/\//', $enlace)) {
            echo "<div class='alert alert-danger'>El enlace debe comenzar con http:// o https://</div>";
            return;
        }

        $stmt = $link->prepare("UPDATE `series` SET `titulo` = ?, `temporada` = ?, `total_episodios` = ?, `episodio_actual` = ?, `enlace` = ?, `comentarios` = ? WHERE `id` = ?");
        $stmt->bind_param("siiissi", $titulo, $temporada, $total_episodios, $episodio_actual, $enlace, $comentarios, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: " . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?mensaje=Serie editada");
        exit();
    }
}

// Función para marcar serie como terminada
function marcarTerminada($link) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['terminada'])) {
        $id = (int)$_GET['terminada'];
        $stmt = $link->prepare("UPDATE `series` SET `fecha_fin` = CURDATE() WHERE `id` = ? AND `fecha_fin` IS NULL");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        header("Location: " . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?mensaje=Serie marcada como terminada");
        exit();
    }
}

// Función para eliminar serie
function eliminarSerie($link) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
        $id = (int)$_POST['eliminar'];
        $stmt = $link->prepare("DELETE FROM `series` WHERE `id` = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        header("Location: " . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?mensaje=Serie eliminada");
        exit();
    }
}

// Procesar acciones
agregarSerie($link);
editarSerie($link);
marcarTerminada($link);
eliminarSerie($link);

// Mostrar mensaje de confirmación
if (isset($_GET['mensaje'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_GET['mensaje'], ENT_QUOTES, 'UTF-8') . '</div>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SerieLog</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body { background-color: #EBEBEB; }
        .main { margin-top: 56px; margin-bottom: 56px; }
        .footer { position: fixed; bottom: 0; width: 100%; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <a class="navbar-brand" href="#"><i class="fas fa-film"></i> SerieLog</a>
        <span class="navbar-text">Generado por no verificable</span>
    </nav>

    <div class="container main">
        <h1 class="my-4">Control de Series</h1>

        <!-- Formulario para agregar serie -->
        <div class="card mb-4">
            <div class="card-header">Agregar Serie</div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label for="titulo">Título</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required>
                    </div>
                    <div class="form-group">
                        <label for="temporada">Temporada</label>
                        <select class="form-control" id="temporada" name="temporada" required>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="total_episodios">Total de Episodios</label>
                        <input type="number" class="form-control" id="total_episodios" name="total_episodios" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="episodio_actual">Episodio Actual</label>
                        <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label for="enlace">Enlace</label>
                        <input type="url" class="form-control" id="enlace" name="enlace">
                    </div>
                    <div class="form-group">
                        <label for="comentarios">Comentarios</label>
                        <textarea class="form-control" id="comentarios" name="comentarios" rows="3"></textarea>
                    </div>
                    <button type="submit" name="agregar" class="btn btn-primary">Agregar Serie</button>
                </form>
            </div>
        </div>

        <!-- Tabla de series -->
        <?php mostrarSeries($link); ?>
    </div>

    <footer class="footer bg-dark text-white">
        <div class="container">
            <span>SerieLog - Control de series</span>
            <span class="float-right">Licencia MIT - Generado por no verificable</span>
        </div>
    </footer>
</body>
</html>
