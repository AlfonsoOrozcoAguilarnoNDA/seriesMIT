<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
Grok 4.5  Generado por Grok 4.5 (xAI) el 2026-10-07
*/
require_once 'config.php';

// Crear tabla si no existe
$sql_create = "CREATE TABLE IF NOT EXISTS `series` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($link, $sql_create);

$mensaje = '';
$error = '';

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

    if ($accion === 'agregar' || $accion === 'editar') {
        $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
        $temporada = isset($_POST['temporada']) ? (int)$_POST['temporada'] : 0;
        $total_episodios = isset($_POST['total_episodios']) ? (int)$_POST['total_episodios'] : 0;
        $episodio_actual = isset($_POST['episodio_actual']) ? (int)$_POST['episodio_actual'] : 0;
        $enlace = isset($_POST['enlace']) ? trim($_POST['enlace']) : '';
        $comentarios = isset($_POST['comentarios']) ? trim($_POST['comentarios']) : '';

        // Validaciones
        if ($titulo === '') {
            $error = 'El título es obligatorio.';
        } elseif ($temporada < 1 || $temporada > 5) {
            $error = 'La temporada debe estar entre 1 y 5.';
        } elseif ($total_episodios < 1) {
            $error = 'El total de episodios debe ser al menos 1.';
        } elseif ($episodio_actual < 0 || $episodio_actual > $total_episodios) {
            $error = 'El episodio actual debe estar entre 0 y el total de episodios.';
        } elseif ($enlace !== '' && !preg_match('#^https?://#i', $enlace)) {
            $error = 'El enlace debe comenzar con http:// o https://.';
        } else {
            if ($accion === 'agregar') {
                $fecha_inicio = date('Y-m-d');
                $stmt = mysqli_prepare($link, "INSERT INTO series (titulo, temporada, total_episodios, episodio_actual, fecha_inicio, enlace, comentarios) VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'siiisss', $titulo, $temporada, $total_episodios, $episodio_actual, $fecha_inicio, $enlace, $comentarios);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']) . '?msg=agregada');
                    exit;
                } else {
                    $error = 'Error al guardar la serie.';
                    mysqli_stmt_close($stmt);
                }
            } else {
                // Editar
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                if ($id < 1) {
                    $error = 'ID inválido.';
                } else {
                    $stmt = mysqli_prepare($link, "UPDATE series SET titulo = ?, temporada = ?, total_episodios = ?, episodio_actual = ?, enlace = ?, comentarios = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'siiissi', $titulo, $temporada, $total_episodios, $episodio_actual, $enlace, $comentarios, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']) . '?msg=actualizada');
                        exit;
                    } else {
                        $error = 'Error al actualizar la serie.';
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    } elseif ($accion === 'terminada') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            // Solo actualiza si fecha_fin es NULL
            $stmt = mysqli_prepare($link, "UPDATE series SET fecha_fin = CURDATE() WHERE id = ? AND fecha_fin IS NULL");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']) . '?msg=terminada');
            exit;
        }
    } elseif ($accion === 'eliminar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $stmt = mysqli_prepare($link, "DELETE FROM series WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']) . '?msg=eliminada');
            exit;
        }
    }
}

// Mensajes de confirmación
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'agregada':
            $mensaje = 'Serie agregada correctamente.';
            break;
        case 'actualizada':
            $mensaje = 'Serie actualizada correctamente.';
            break;
        case 'terminada':
            $mensaje = 'Serie marcada como terminada.';
            break;
        case 'eliminada':
            $mensaje = 'Serie eliminada correctamente.';
            break;
    }
}

// Cargar serie a editar si corresponde
$editar = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $stmt = mysqli_prepare($link, "SELECT id, titulo, temporada, total_episodios, episodio_actual, enlace, comentarios FROM series WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $edit_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $editar = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

// Obtener todas las series (más recientes primero)
$series = [];
$result = mysqli_query($link, "SELECT id, titulo, temporada, total_episodios, episodio_actual, fecha_inicio, fecha_fin, enlace, comentarios FROM series ORDER BY created_at DESC, id DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $series[] = $row;
    }
    mysqli_free_result($result);
}

$self = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>SerieLog</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #EBEBEB;
            padding-top: 70px;
            padding-bottom: 70px;
        }
        .navbar-brand i {
            margin-right: 6px;
        }
        .progress {
            height: 18px;
            margin-top: 4px;
        }
        .progress-text {
            font-size: 0.85rem;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .comentarios-cell {
            max-width: 200px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            background-color: #343a40;
            color: #fff;
            font-size: 0.85rem;
            z-index: 1030;
        }
        .form-section {
            background: #fff;
            border-radius: 0.25rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand navbar-dark bg-dark fixed-top">
        <a class="navbar-brand" href="<?php echo $self; ?>">
            <i class="fas fa-tv"></i> SerieLog
        </a>
        <span class="navbar-text text-light ml-auto small">
            Generado por Grok 4.5 (xAI) el 2026-10-07
        </span>
    </nav>

    <div class="container">
        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar" onclick="this.parentElement.style.display='none';">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar" onclick="this.parentElement.style.display='none';">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h5 class="mb-3">
                <?php if ($editar): ?>
                    <i class="fas fa-edit"></i> Editar serie
                <?php else: ?>
                    <i class="fas fa-plus-circle"></i> Agregar serie
                <?php endif; ?>
            </h5>
            <form method="post" action="<?php echo $self; ?>">
                <input type="hidden" name="accion" value="<?php echo $editar ? 'editar' : 'agregar'; ?>">
                <?php if ($editar): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$editar['id']; ?>">
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="titulo">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" maxlength="150" required
                               value="<?php echo $editar ? htmlspecialchars($editar['titulo'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="temporada">Temporada <span class="text-danger">*</span></label>
                        <select class="form-control" id="temporada" name="temporada" required>
                            <?php for ($t = 1; $t <= 5; $t++): ?>
                                <option value="<?php echo $t; ?>" <?php echo ($editar && (int)$editar['temporada'] === $t) ? 'selected' : ''; ?>>
                                    <?php echo $t; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="total_episodios">Total episodios <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="total_episodios" name="total_episodios" min="1" required
                               value="<?php echo $editar ? (int)$editar['total_episodios'] : ''; ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="episodio_actual">Episodio actual</label>
                        <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0"
                               value="<?php echo $editar ? (int)$editar['episodio_actual'] : '0'; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="enlace">Enlace</label>
                        <input type="url" class="form-control" id="enlace" name="enlace" maxlength="500" placeholder="https://..."
                               value="<?php echo $editar ? htmlspecialchars($editar['enlace'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="comentarios">Comentarios</label>
                        <textarea class="form-control" id="comentarios" name="comentarios" rows="2"><?php echo $editar ? htmlspecialchars($editar['comentarios'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <?php if ($editar): ?>
                        <i class="fas fa-save"></i> Guardar cambios
                    <?php else: ?>
                        <i class="fas fa-plus"></i> Agregar
                    <?php endif; ?>
                </button>
                <?php if ($editar): ?>
                    <a href="<?php echo $self; ?>" class="btn btn-secondary ml-2">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover bg-white">
                <thead class="thead-dark">
                    <tr>
                        <th>Título</th>
                        <th>Temp.</th>
                        <th>Progreso</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Enlace</th>
                        <th>Comentarios</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($series)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No hay series registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($series as $s): ?>
                            <?php
                            $pct = $s['total_episodios'] > 0 ? round(($s['episodio_actual'] / $s['total_episodios']) * 100) : 0;
                            $dominio = '';
                            if (!empty($s['enlace'])) {
                                $parsed = parse_url($s['enlace']);
                                $dominio = isset($parsed['host']) ? $parsed['host'] : $s['enlace'];
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-center"><?php echo (int)$s['temporada']; ?></td>
                                <td>
                                    <div class="progress-text"><?php echo (int)$s['episodio_actual']; ?>/<?php echo (int)$s['total_episodios']; ?></div>
                                    <div class="progress">
                                        <div class="progress-bar <?php echo $pct >= 100 ? 'bg-success' : 'bg-info'; ?>" role="progressbar"
                                             style="width: <?php echo $pct; ?>%;" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $pct; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($s['fecha_inicio'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($s['fecha_fin'] === null): ?>
                                        <span class="badge badge-warning">En curso</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($s['fecha_fin'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($s['enlace'])): ?>
                                        <a href="<?php echo htmlspecialchars($s['enlace'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i> <?php echo htmlspecialchars($dominio, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="comentarios-cell"><?php echo $s['comentarios'] !== null && $s['comentarios'] !== '' ? htmlspecialchars($s['comentarios'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                <td class="text-nowrap">
                                    <a href="<?php echo $self; ?>?edit=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($s['fecha_fin'] === null): ?>
                                        <form method="post" action="<?php echo $self; ?>" class="d-inline">
                                            <input type="hidden" name="accion" value="terminada">
                                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Marcar como terminada">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo $self; ?>" class="d-inline" onsubmit="return confirm('¿Eliminar esta serie?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="text-center py-2">
        SerieLog - Control de series &nbsp;|&nbsp; Licencia MIT &nbsp;|&nbsp; Generado por Grok 4.5 (xAI) el 2026-10-07
    </footer>
</body>
</html>
