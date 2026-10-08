<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
Deepseek
*/
require_once 'config.php';

// Crear tabla si no existe
$sql = "CREATE TABLE IF NOT EXISTS `series` (
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
mysqli_query($link, $sql);

$mensaje = '';
$tipo_mensaje = '';

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // AGREGAR
    if ($accion === 'agregar') {
        $titulo = trim($_POST['titulo'] ?? '');
        $temporada = (int)($_POST['temporada'] ?? 0);
        $total_episodios = (int)($_POST['total_episodios'] ?? 0);
        $episodio_actual = (int)($_POST['episodio_actual'] ?? 0);
        $enlace = trim($_POST['enlace'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        $errores = [];
        if ($titulo === '') $errores[] = 'El título es obligatorio.';
        if ($temporada < 1 || $temporada > 5) $errores[] = 'La temporada debe estar entre 1 y 5.';
        if ($total_episodios < 1) $errores[] = 'El total de episodios debe ser al menos 1.';
        if ($episodio_actual < 0 || $episodio_actual > $total_episodios) $errores[] = 'El episodio actual debe estar entre 0 y el total de episodios.';
        if ($enlace !== '' && !preg_match('#^https?://#i', $enlace)) $errores[] = 'El enlace debe empezar con http:// o https://.';

        if (empty($errores)) {
            $stmt = mysqli_prepare($link, "INSERT INTO series (titulo, temporada, total_episodios, episodio_actual, fecha_inicio, enlace, comentarios) VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
            $enlace_val = $enlace === '' ? null : $enlace;
            $comentarios_val = $comentarios === '' ? null : $comentarios;
            mysqli_stmt_bind_param($stmt, 'siiiss', $titulo, $temporada, $total_episodios, $episodio_actual, $enlace_val, $comentarios_val);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=agregado');
                exit;
            } else {
                $mensaje = 'Error al guardar: ' . mysqli_error($link);
                $tipo_mensaje = 'danger';
            }
            mysqli_stmt_close($stmt);
        } else {
            $mensaje = implode(' ', $errores);
            $tipo_mensaje = 'danger';
        }
    }

    // EDITAR
    if ($accion === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $temporada = (int)($_POST['temporada'] ?? 0);
        $total_episodios = (int)($_POST['total_episodios'] ?? 0);
        $episodio_actual = (int)($_POST['episodio_actual'] ?? 0);
        $enlace = trim($_POST['enlace'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        $errores = [];
        if ($id < 1) $errores[] = 'ID inválido.';
        if ($titulo === '') $errores[] = 'El título es obligatorio.';
        if ($temporada < 1 || $temporada > 5) $errores[] = 'La temporada debe estar entre 1 y 5.';
        if ($total_episodios < 1) $errores[] = 'El total de episodios debe ser al menos 1.';
        if ($episodio_actual < 0 || $episodio_actual > $total_episodios) $errores[] = 'El episodio actual debe estar entre 0 y el total de episodios.';
        if ($enlace !== '' && !preg_match('#^https?://#i', $enlace)) $errores[] = 'El enlace debe empezar con http:// o https://.';

        if (empty($errores)) {
            $stmt = mysqli_prepare($link, "UPDATE series SET titulo=?, temporada=?, total_episodios=?, episodio_actual=?, enlace=?, comentarios=? WHERE id=?");
            $enlace_val = $enlace === '' ? null : $enlace;
            $comentarios_val = $comentarios === '' ? null : $comentarios;
            mysqli_stmt_bind_param($stmt, 'siiissi', $titulo, $temporada, $total_episodios, $episodio_actual, $enlace_val, $comentarios_val, $id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=editado');
                exit;
            } else {
                $mensaje = 'Error al editar: ' . mysqli_error($link);
                $tipo_mensaje = 'danger';
            }
            mysqli_stmt_close($stmt);
        } else {
            $mensaje = implode(' ', $errores);
            $tipo_mensaje = 'danger';
        }
    }

    // TERMINADA
    if ($accion === 'terminada') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($link, "UPDATE series SET fecha_fin = CURDATE() WHERE id = ? AND fecha_fin IS NULL");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=terminada');
            exit;
        }
    }

    // ELIMINAR
    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($link, "DELETE FROM series WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=eliminado');
            exit;
        }
    }
}

// Mensajes por GET
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'agregado': $mensaje = 'Serie agregada correctamente.'; $tipo_mensaje = 'success'; break;
        case 'editado': $mensaje = 'Serie actualizada correctamente.'; $tipo_mensaje = 'success'; break;
        case 'terminada': $mensaje = 'Serie marcada como terminada.'; $tipo_mensaje = 'success'; break;
        case 'eliminado': $mensaje = 'Serie eliminada correctamente.'; $tipo_mensaje = 'success'; break;
    }
}

// Cargar serie para editar
$serie_editar = null;
if (isset($_GET['editar'])) {
    $id_editar = (int)$_GET['editar'];
    if ($id_editar > 0) {
        $stmt = mysqli_prepare($link, "SELECT * FROM series WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id_editar);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $serie_editar = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

// Listar series
$series = [];
$res = mysqli_query($link, "SELECT * FROM series ORDER BY id DESC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $series[] = $row;
    }
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
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
        body { background-color: #EBEBEB; padding-top: 70px; padding-bottom: 70px; }
        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1030; }
        footer { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1030; }
        .progress { height: 20px; min-width: 100px; }
        .table td, .table th { vertical-align: middle; }
        .btn-xs { padding: 0.15rem 0.4rem; font-size: 0.75rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <span class="navbar-brand mb-0 h1"><i class="fas fa-tv"></i> SerieLog</span>
    <span class="navbar-text text-light small">Generado por DeepSeek no verificable el no verificable</span>
</nav>

<div class="container">

    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo h($tipo_mensaje); ?> alert-dismissible fade show" role="alert">
            <?php echo h($mensaje); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Formulario agregar/editar -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-<?php echo $serie_editar ? 'edit' : 'plus'; ?>"></i>
            <?php echo $serie_editar ? 'Editar serie' : 'Agregar serie'; ?>
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="accion" value="<?php echo $serie_editar ? 'editar' : 'agregar'; ?>">
                <?php if ($serie_editar): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$serie_editar['id']; ?>">
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="titulo">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="150" value="<?php echo h($serie_editar['titulo'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="temporada">Temporada <span class="text-danger">*</span></label>
                        <select class="form-control" id="temporada" name="temporada" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (isset($serie_editar['temporada']) && (int)$serie_editar['temporada'] === $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="total_episodios">Total eps. <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="total_episodios" name="total_episodios" required min="1" max="65535" value="<?php echo h($serie_editar['total_episodios'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="episodio_actual">Ep. actual</label>
                        <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0" max="65535" value="<?php echo h($serie_editar['episodio_actual'] ?? '0'); ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="enlace">Enlace</label>
                        <input type="url" class="form-control" id="enlace" name="enlace" maxlength="500" placeholder="https://..." value="<?php echo h($serie_editar['enlace'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="comentarios">Comentarios</label>
                    <textarea class="form-control" id="comentarios" name="comentarios" rows="2"><?php echo h($serie_editar['comentarios'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $serie_editar ? 'Guardar cambios' : 'Agregar'; ?>
                </button>
                <?php if ($serie_editar): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i> Mis series (<?php echo count($series); ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
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
                            <tr><td colspan="8" class="text-center text-muted py-4">No hay series registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($series as $s): ?>
                                <?php
                                    $total = (int)$s['total_episodios'];
                                    $actual = (int)$s['episodio_actual'];
                                    $pct = $total > 0 ? round(($actual / $total) * 100) : 0;
                                    $pct = min(100, max(0, $pct));
                                    $bar_class = $pct >= 100 ? 'bg-success' : ($pct >= 50 ? 'bg-info' : 'bg-warning');
                                ?>
                                <tr>
                                    <td><?php echo h($s['titulo']); ?></td>
                                    <td><?php echo (int)$s['temporada']; ?></td>
                                    <td style="min-width:160px;">
                                        <small><?php echo $actual; ?>/<?php echo $total; ?> (<?php echo $pct; ?>%)</small>
                                        <div class="progress">
                                            <div class="progress-bar <?php echo $bar_class; ?>" role="progressbar" style="width: <?php echo $pct; ?>%;" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </td>
                                    <td><?php echo h($s['fecha_inicio']); ?></td>
                                    <td>
                                        <?php if ($s['fecha_fin']): ?>
                                            <?php echo h($s['fecha_fin']); ?>
                                        <?php else: ?>
                                            <span class="badge badge-warning">En curso</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['enlace'])): ?>
                                            <?php
                                                $dominio = parse_url($s['enlace'], PHP_URL_HOST);
                                                $dominio = $dominio ?: $s['enlace'];
                                            ?>
                                            <a href="<?php echo h($s['enlace']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt"></i> <?php echo h($dominio); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['comentarios'])): ?>
                                            <span title="<?php echo h($s['comentarios']); ?>">
                                                <?php echo h(mb_strimwidth($s['comentarios'], 0, 40, '…')); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>?editar=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-info" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (!$s['fecha_fin']): ?>
                                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" class="d-inline">
                                                <input type="hidden" name="accion" value="terminada">
                                                <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Terminada">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" class="d-inline" onsubmit="return confirm('¿Eliminar esta serie?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                <i class="fas fa-trash"></i>
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
    </div>

</div>

<footer class="bg-dark text-light text-center py-2">
    <small>
        SerieLog - Control de series · Licencia MIT · Generado por DeepSeek no verificable el no verificable
    </small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
