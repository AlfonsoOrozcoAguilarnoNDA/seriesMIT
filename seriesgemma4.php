<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
 Generado por google/gemma-4-e4b

*/

// series.php - Control de Series Coreanas y Viki

// 1. Incluir configuración de la base de datos (Asumimos que existe)
require_once 'config.php';

// =============================================================================
// 2. Inicialización de la Base de Datos: Creación de la tabla series
// =============================================================================

$sql_create_table = "
CREATE TABLE IF NOT EXISTS series (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($link !== null) {
    if (mysqli_query($link, $sql_create_table)) {
        // Tabla creada o ya existía.
    } else {
        die("Error al crear la tabla series: " . mysqli_error($link));
    }
}

// Variables de estado y mensajes
$message = '';
$is_editing = false;
$edit_series_id = null;

// 3. Manejo de POST Requests (Lógica del Servidor)

/**
 * Función para sanitizar y validar la entrada de texto.
 * @param string $input El dato a limpiar.
 * @return string El dato limpio.
 */
function sanitize_input($input) {
    if (!is_string($input)) return '';
    $input = trim($input);
    // Limpieza básica y escape para evitar XSS en el manejo de datos
    return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige al patrón POST/Redirect/GET después de una operación exitosa.
 * @param string $redirect_url La URL a la que debe redirigir.
 */
function redirect_success($redirect_url) {
    header("Location: " . $redirect_url);
    exit;
}

// --- A. Manejo de Eliminación (DELETE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $series_id = filter_var(trim($_POST['series_id']), FILTER_VALIDATE_INT);

    if ($series_id !== false) {
        // Usamos prepared statements para la eliminación.
        $stmt = mysqli_prepare($link, "DELETE FROM series WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $series_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $message = "<div class='alert alert-success' role='alert'>Serie eliminada exitosamente.</div>";
        } else {
            $message = "<div class='alert alert-warning' role='alert'>No se pudo eliminar la serie o no existía.</div>";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "<div class='alert alert-danger' role='alert'>Error: ID de serie inválido.</div>";
    }
}

// --- B. Manejo de Adición/Edición (INSERT/UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'delete') {
    $title = sanitize_input($_POST['titulo']);
    $season = filter_var(trim($_POST['temporada']), FILTER_VALIDATE_INT);
    $total_episodes = filter_var(trim($_POST['total_episodios']), FILTER_VALIDATE_INT);
    $current_episode = filter_var(trim($_POST['episodio_actual']), FILTER_VALIDATE_INT);
    $link = sanitize_input($_POST['enlace']);
    $comments = sanitize_input($_POST['comentarios']);

    // 1. Validación de datos obligatorios y rangos
    if (empty($title) || $season === false || $total_episodes === false || $total_episodes < 1) {
        $message = "<div class='alert alert-danger' role='alert'>Por favor, complete el Título, Temporada y debe haber al menos un episodio.</div>";
    } elseif ($season < 1 || $season > 5) {
        $message = "<div class='alert alert-danger' role='alert'>La temporada debe estar entre 1 y 5.</div>";
    } elseif ($current_episode === false || $current_episode < 0 || $current_episode > $total_episodes) {
        $message = "<div class='alert alert-danger' role='alert'>El episodio actual debe ser un número entre 0 y el total de episodios.</div>";
    } elseif (!empty($link)) {
        // Validación de esquema del enlace
        if (!preg_match("^(http|https)://[^\s/$.?#]+\\.[^\s/$.?#]+$", $link)) {
            $message = "<div class='alert alert-danger' role='alert'>El enlace debe ser una URL válida que comience con http:// o https://.</div>";
        }
    } else {
        // Si la validación falla, salimos aquí y el mensaje de error se muestra.
        $message = "<div class='alert alert-danger' role='alert'>Por favor, revise los campos obligatorios (Título, Temporada, Episodios).</div>";
    }

    // 2. Procesamiento si la validación es exitosa
    if (empty($message) || strpos($message, 'alert-danger') === false) {
        $action = $_POST['action'];
        $series_id = filter_var(trim($_POST['series_id']), FILTER_VALIDATE_INT);

        // A. Lógica de Actualización (EDIT)
        if ($action === 'edit' && $series_id !== false) {
            // Usamos prepared statements para la actualización.
            $stmt = mysqli_prepare($link, "UPDATE series SET titulo=?, temporada=?, total_episodios=?, episodio_actual=?, enlace=?, comentarios=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "siiisssi", $title, $season, $total_episodes, $current_episode, $link, $comments, $series_id);

            if (mysqli_stmt_execute($stmt)) {
                $message = "<div class='alert alert-success' role='alert'>Serie actualizada exitosamente.</div>";
            } else {
                $message = "<div class='alert alert-danger' role='alert'>Error al actualizar la serie: " . mysqli_error($link) . "</div>";
            }
            mysqli_stmt_close($stmt);

        // B. Lógica de Adición (ADD)
        } elseif ($action === 'add') {
            // Usamos prepared statements para la inserción.
            $fecha_inicio = date('Y-m-d'); // Fecha actual del servidor
            $stmt = mysqli_prepare($link, "INSERT INTO series (titulo, temporada, total_episodios, episodio_actual, fecha_inicio, enlace, comentarios) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "siiiss s", $title, $season, $total_episodes, $current_episode, $fecha_inicio, $link, $comments);

            if (mysqli_stmt_execute($stmt)) {
                $message = "<div class='alert alert-success' role='alert'>Serie agregada exitosamente.</div>";
            } else {
                $message = "<div class='alert alert-danger' role='alert'>Error al agregar la serie: " . mysqli_error($link) . "</div>";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// --- C. Manejo de Terminación (FINISH) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finish') {
    $series_id = filter_var(trim($_POST['series_id']), FILTER_VALIDATE_INT);

    if ($series_id !== false) {
        // Solo actualizamos si fecha_fin es NULL.
        $stmt = mysqli_prepare($link, "UPDATE series SET fecha_fin = ? WHERE id = ? AND fecha_fin IS NULL");
        mysqli_stmt_bind_param($stmt, "si", $fecha_hoy, $series_id);

        if (mysqli_stmt_execute($stmt)) {
            $message = "<div class='alert alert-success' role='alert'>Serie marcada como terminada. ¡Felicidades!</div>";
        } else {
            // Esto puede fallar si ya estaba terminada o por otro error de DB.
            $message = "<div class='alert alert-warning' role='alert'>No se pudo marcar la serie como terminada (quizás ya lo estaba).</div>";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "<div class='alert alert-danger' role='alert'>Error: ID de serie inválido.</div>";
    }
}

// 4. Obtener datos para Edición (GET)
if (isset($_GET['edit_id']) && filter_var(trim($_GET['edit_id']), FILTER_VALIDATE_INT)) {
    $edit_series_id = $_GET['edit_id'];
    $stmt = mysqli_prepare($link, "SELECT * FROM series WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $edit_series_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $series_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($series_data) {
        $is_editing = true;
    } else {
        // Si el ID no existe, lo tratamos como si no estuviéramos editando.
        $is_editing = false;
    }
}


// 5. Obtener todas las series para la tabla (SELECT)
$result = mysqli_query($link, "SELECT * FROM series ORDER BY created_at DESC");
$series_list = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $series_list[] = $row;
    }
    mysqli_free_result($result);
}


// =============================================================================
// 6. Lógica de Renderizado (HTML/PHP)
// =============================================================================

// Determinar el título y la acción del formulario
$form_title = $is_editing ? "Editar Serie" : "Agregar Nueva Serie";
$action_endpoint = $is_editing ? 'edit' : 'add';
$series_id_for_form = $is_editing ? $edit_series_id : '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SerieLog - Control de Series</title>
    <!-- Bootstrap 4.6.2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 5.15.4 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #EBEBEB; /* Fondo de página */
            padding-top: 60px; /* Espacio para la navbar fija */
            padding-bottom: 80px; /* Espacio para el footer fijo */
        }
        .container-content {
            max-width: 95%;
        }
        /* Estilo para hacer que los elementos no se superpongan con fixed headers/footers */
        body * {
            box-sizing: border-box;
        }
    </style>
</head>
<body>

<!-- Navbar Fija -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container-fluid container-content">
        <a class="navbar-brand" href="#">
            <i class="fas fa-book mr-2"></i> SerieLog
        </a>
        <span class="navbar-text small text-white ml-auto">
            Generado por Modelo de Lenguaje el no verificable.
        </span>
    </div>
</nav>

<!-- Contenido Principal -->
<div class="container container-content mt-3 mb-5">

    <?php echo $message; ?>

    <!-- Formulario de Adición/Edición -->
    <h2 class="mb-4"><?= htmlspecialchars($form_title) ?></h2>

    <div class="card shadow p-4 mb-5" id="seriesFormCard">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <!-- Oculto: Acción (add o edit) -->
            <input type="hidden" name="action" value="<?= $action_endpoint ?>">
            <!-- Oculto: ID de la serie si estamos editando -->
            <input type="hidden" name="series_id" value="<?= htmlspecialchars($series_id_for_form) ?>">

            <div class="row">
                <!-- Título (Obligatorio) -->
                <div class="col-md-6 mb-3">
                    <label for="titulo" class="form-control-label">Título <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="titulo" name="titulo" required 
                           value="<?= $is_editing ? htmlspecialchars($series_data['titulo']) : '' ?>">
                </div>

                <!-- Temporada (Obligatorio) -->
                <div class="col-md-3 mb-3">
                    <label for="temporada" class="form-control-label">Temporada <span class="text-danger">*</span></label>
                    <select class="form-control" id="temporada" name="temporada" required>
                        <option value="">Seleccionar...</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= $is_editing && (int)$series_data['temporada'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Total Episodios (Obligatorio) -->
                <div class="col-md-3 mb-3">
                    <label for="total_episodios" class="form-control-label">Total Episodios <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="total_episodios" name="total_episodios" min="1" required 
                           value="<?= $is_editing ? htmlspecialchars($series_data['total_episodios']) : '' ?>">
                </div>
            </div>

            <div class="row mb-3">
                <!-- Episodio Actual (Opcional) -->
                <div class="col-md-4 mb-3">
                    <label for="episodio_actual" class="form-control-label">Episodio Actual</label>
                    <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0" 
                           value="<?= $is_editing ? htmlspecialchars($series_data['episodio_actual']) : '0' ?>">
                </div>

                <!-- Enlace (Opcional) -->
                <div class="col-md-4 mb-3">
                    <label for="enlace" class="form-control-label">Enlace</label>
                    <input type="text" class="form-control" id="enlace" name="enlace" 
                           value="<?= $is_editing ? htmlspecialchars($series_data['enlace']) : '' ?>">
                </div>

                <!-- Comentarios (Opcional) -->
                <div class="col-md-4 mb-3">
                    <label for="comentarios" class="form-control-label">Comentarios</label>
                    <input type="text" class="form-control" id="comentarios" name="comentarios" 
                           value="<?= $is_editing ? htmlspecialchars($series_data['comentarios']) : '' ?>">
                </div>
            </div>

            <!-- Botones de Acción -->
            <button type="submit" class="btn btn-primary mr-2">
                <i class="fas fa-save mr-1"></i> <?= $form_title ?>
            </button>
            <?php if ($is_editing): ?>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Cancelar Edición</a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabla de Series -->
    <h2 class="mt-5 mb-3">Mis Series en Progreso</h2>

    <div class="table-responsive card shadow p-3">
        <table class="table table-striped table-hover" id="seriesTable">
            <thead>
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
                <?php if (empty($series_list)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">Aún no has añadido ninguna serie.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($series_list as $serie): ?>
                        <?php
                            $progress = (int)$serie['episodio_actual'];
                            $total = (int)$serie['total_episodios'];
                            $percentage = ($total > 0) ? min(100, round(($progress / $total) * 100)) : 0;
                            $status_text = $serie['fecha_fin'] ? 'Terminada' : 'En curso';
                            $finish_display = $serie['fecha_fin'] ? htmlspecialchars($serie['fecha_fin']) : '<span class="text-success">En curso</span>';
                        ?>
                        <tr data-id="<?= htmlspecialchars($serie['id']) ?>">
                            <td><?= htmlspecialchars($serie['titulo']) ?></td>
                            <td><?= htmlspecialchars($serie['temporada']) ?></td>
                            <td>
                                <div class="progress" role="progressbar" style="height: 25px;">
                                    <div class="progress-bar bg-info" style="width: <?= $percentage ?>%">
                                        <?= $progress ? "{$progress}/{$total}" : "0/1"; ?>
                                    </div>
                                </div>
                                <small class="d-block text-muted mt-1"><?= htmlspecialchars($status_text) ?></small>
                            </td>
                            <td><?= htmlspecialchars($serie['fecha_inicio']) ?></td>
                            <td><?= $finish_display ?></td>
                            <td>
                                <?php if ($serie['enlace']): ?>
                                    <a href="<?= htmlspecialchars($serie['enlace']) ?>" target="_blank" rel="noopener noreferrer">
                                        <button class="btn btn-sm btn-outline-info"><i class="fas fa-link mr-1"></i> <?= substr(htmlspecialchars($serie['enlace']), 0, strpos($series_data['enlace'], '/') + 1) ?>...</button>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(substr($serie['comentarios'] ?? '', 0, 50)) ?>...</td>
                            <td>
                                <!-- Botón de Edición -->
                                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?edit_id=<?= $serie['id'] ?>" class="btn btn-sm btn-warning mr-1">
                                    <i class="fas fa-pencil-alt"></i> Editar
                                </a>

                                <!-- Botón de Terminación -->
                                <?php if (empty($serie['fecha_fin'])): ?>
                                    <button type="button" class="btn btn-sm btn-success mr-1" data-toggle="modal" data-target="#finishModal<?= $serie['id'] ?>">
                                        <i class="fas fa-flag-checkered"></i> Terminar
                                    </button>
                                <?php endif; ?>

                                <!-- Botón de Eliminación -->
                                <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#deleteModal<?= $serie['id'] ?>">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div> <!-- Fin del contenedor principal -->


<!-- =============================================================================
     MODALES (Terminación y Eliminación)
     ============================================================================= -->

<!-- Modal de Terminación -->
<?php foreach ($series_list as $serie): ?>
<div class="modal fade" id="finishModal<?= $serie['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="finishModalLabel<?= $serie['id'] ?>" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="finishModalLabel<?= $serie['id'] ?>">Confirmar Terminación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <p>¿Estás seguro de que deseas marcar la serie <strong><?= htmlspecialchars($serie['titulo']) ?></strong> como terminada?</p>
                <small class="text-muted">Esta acción registrará la fecha actual en el historial.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display: inline;">
                    <input type="hidden" name="action" value="finish">
                    <input type="hidden" name="series_id" value="<?= $serie['id'] ?>">
                    <button type="submit" class="btn btn-success"><i class="fas fa-flag-checkered mr-1"></i> Confirmar Terminación</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Eliminación -->
<?php foreach ($series_list as $serie): ?>
<div class="modal fade" id="deleteModal<?= $serie['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel<?= $serie['id'] ?>" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel<?= $serie['id'] ?>">Eliminar Serie</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <p>¿Estás seguro de que deseas eliminar permanentemente la serie <strong><?= htmlspecialchars($serie['titulo']) ?></strong>? Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="series_id" value="<?= $serie['id'] ?>">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash mr-1"></i> Sí, Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Footer Fijo -->
<footer class="fixed-bottom bg-dark text-white py-3 mt-auto" style="z-index: 1000;">
    <div class="container container-content">
        SerieLog - Control de series. Licencia MIT. Generado por Modelo de Lenguaje el no verificable.
    </div>
</footer>

<!-- Scripts (Bootstrap y JS necesario) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
<script>
    // Script para manejar la confirmación de eliminación antes del POST
    $(document).ready(function() {
        $('.btn-danger').on('click', function(e) {
            if (!confirm('¿Estás absolutamente seguro de que quieres eliminar esta serie? Esta acción es irreversible.')) {
                e.preventDefault();
            }
        });
    });
</script>

</body>
</html>
<?php
// Cerrar conexión al finalizar el script
mysqli_close($link);
?>
