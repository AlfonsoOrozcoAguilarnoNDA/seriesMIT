<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
Mistral instant, se identifica como GLM
*/

require_once 'config.php';

/* ---- Tabla ---- */
mysqli_query($link, "CREATE TABLE IF NOT EXISTS series (
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

/* ---- Sesión para mensajes PRG ---- */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$hoy = date('Y-m-d');
$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);

/* ---- Procesamiento POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Eliminar */
    if (isset($_POST['eliminar'])) {
        $idEliminar = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if ($idEliminar !== false && $idEliminar > 0) {
            $stmt = mysqli_prepare($link, "DELETE FROM series WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $idEliminar);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['mensaje'] = 'Serie eliminada correctamente.';
                $_SESSION['tipo'] = 'success';
            } else {
                $_SESSION['mensaje'] = 'Error al eliminar la serie.';
                $_SESSION['tipo'] = 'danger';
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['mensaje'] = 'Serie no válida.';
            $_SESSION['tipo'] = 'danger';
        }
        header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']));
        exit;
    }

    /* Terminada */
    if (isset($_POST['terminar'])) {
        $idTerminar = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if ($idTerminar !== false && $idTerminar > 0) {
            // El servidor ignora cualquier intento si ya tiene fecha_fin
            $stmt = mysqli_prepare($link, "UPDATE series SET fecha_fin = ? WHERE id = ? AND fecha_fin IS NULL");
            mysqli_stmt_bind_param($stmt, 'si', $hoy, $idTerminar);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['mensaje'] = (mysqli_stmt_affected_rows($stmt) > 0)
                    ? 'Serie marcada como terminada.'
                    : 'La serie ya estaba terminada.';
                $_SESSION['tipo'] = 'success';
            } else {
                $_SESSION['mensaje'] = 'Error al marcar la serie.';
                $_SESSION['tipo'] = 'danger';
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['mensaje'] = 'Serie no válida.';
            $_SESSION['tipo'] = 'danger';
        }
        header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']));
        exit;
    }

    /* Agregar */
    if (isset($_POST['agregar'])) {
        $titulo = trim($_POST['titulo'] ?? '');
        $temporada = filter_var(trim($_POST['temporada'] ?? ''), FILTER_VALIDATE_INT);
        $total = filter_var(trim($_POST['total_episodios'] ?? ''), FILTER_VALIDATE_INT);
        $actual = trim($_POST['episodio_actual'] ?? '');
        $actual = ($actual === '') ? 0 : filter_var($actual, FILTER_VALIDATE_INT);
        $enlace = trim($_POST['enlace'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        $errores = [];
        if ($titulo === '' || mb_strlen($titulo) > 150) {
            $errores[] = 'El título es obligatorio (máximo 150 caracteres).';
        }
        if ($temporada === false || $temporada < 1 || $temporada > 5) {
            $errores[] = 'La temporada debe estar entre 1 y 5.';
        }
        if ($total === false || $total < 1) {
            $errores[] = 'El total de episodios debe ser al menos 1.';
        }
        if ($actual === false || $actual < 0 || ($total !== false && $actual > $total)) {
            $errores[] = 'El episodio actual debe estar entre 0 y el total de episodios.';
        }
        if ($enlace !== '' && !preg_match('#^https?://#i', $enlace)) {
            $errores[] = 'El enlace debe empezar con http:// o https://.';
        }
        if (mb_strlen($enlace) > 500) {
            $errores[] = 'El enlace no puede exceder 500 caracteres.';
        }

        if (empty($errores)) {
            $stmt = mysqli_prepare($link, "INSERT INTO series (titulo, temporada, total_episodios, episodio_actual, fecha_inicio, enlace, comentarios) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'siiisss', $titulo, $temporada, $total, $actual, $hoy, $enlace, $comentarios);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['mensaje'] = 'Serie agregada correctamente.';
                $_SESSION['tipo'] = 'success';
            } else {
                $_SESSION['mensaje'] = 'Error al guardar la serie.';
                $_SESSION['tipo'] = 'danger';
            }
            mysqli_stmt_close($stmt);
            header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']));
            exit;
        } else {
            $mensaje = implode(' ', $errores);
            $tipoMensaje = 'danger';
            // Conservar valores del formulario en caso de error
            $formTitulo = $titulo;
            $formTemporada = $temporada === false ? '' : $temporada;
            $formTotal = $total === false ? '' : $total;
            $formActual = $actual === false ? '' : $actual;
            $formEnlace = $enlace;
            $formComentarios = $comentarios;
        }
    }

    /* Editar */
    if (isset($_POST['editar'])) {
        $idEditar = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $titulo = trim($_POST['titulo'] ?? '');
        $temporada = filter_var(trim($_POST['temporada'] ?? ''), FILTER_VALIDATE_INT);
        $total = filter_var(trim($_POST['total_episodios'] ?? ''), FILTER_VALIDATE_INT);
        $actual = trim($_POST['episodio_actual'] ?? '');
        $actual = ($actual === '') ? 0 : filter_var($actual, FILTER_VALIDATE_INT);
        $enlace = trim($_POST['enlace'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        $errores = [];
        if ($idEditar === false || $idEditar <= 0) {
            $errores[] = 'Serie no válida.';
        }
        if ($titulo === '' || mb_strlen($titulo) > 150) {
            $errores[] = 'El título es obligatorio (máximo 150 caracteres).';
        }
        if ($temporada === false || $temporada < 1 || $temporada > 5) {
            $errores[] = 'La temporada debe estar entre 1 y 5.';
        }
        if ($total === false || $total < 1) {
            $errores[] = 'El total de episodios debe ser al menos 1.';
        }
        if ($actual === false || $actual < 0 || ($total !== false && $actual > $total)) {
            $errores[] = 'El episodio actual debe estar entre 0 y el total de episodios.';
        }
        if ($enlace !== '' && !preg_match('#^https?://#i', $enlace)) {
            $errores[] = 'El enlace debe empezar con http:// o https://.';
        }
        if (mb_strlen($enlace) > 500) {
            $errores[] = 'El enlace no puede exceder 500 caracteres.';
        }

        if (empty($errores)) {
            $stmt = mysqli_prepare($link, "UPDATE series SET titulo = ?, temporada = ?, total_episodios = ?, episodio_actual = ?, enlace = ?, comentarios = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'siiissi', $titulo, $temporada, $total, $actual, $enlace, $comentarios, $idEditar);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['mensaje'] = 'Serie actualizada correctamente.';
                $_SESSION['tipo'] = 'success';
            } else {
                $_SESSION['mensaje'] = 'Error al actualizar la serie.';
                $_SESSION['tipo'] = 'danger';
            }
            mysqli_stmt_close($stmt);
            header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF']));
            exit;
        } else {
            $mensaje = implode(' ', $errores);
            $tipoMensaje = 'danger';
        }
    }
}

/* ---- Edición (GET con id) ---- */
$serieEditar = null;
if (isset($_GET['editar_id'])) {
    $idEd = filter_var($_GET['editar_id'], FILTER_VALIDATE_INT);
    if ($idEd !== false && $idEd > 0) {
        $stmt = mysqli_prepare($link, "SELECT id, titulo, temporada, total_episodios, episodio_actual, enlace, comentarios FROM series WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $idEd);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $serieEditar = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

/* ---- Listado ---- */
$series = [];
$result = mysqli_query($link, "SELECT id, titulo, temporada, total_episodios, episodio_actual, fecha_inicio, fecha_fin, enlace, comentarios FROM series ORDER BY created_at DESC, id DESC");
if ($result) {
    while ($fila = mysqli_fetch_assoc($result)) {
        $series[] = $fila;
    }
    mysqli_free_result($result);
}

$self = htmlspecialchars($_SERVER['PHP_SELF']);
$fechaGeneracion = '2026-10-08';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>SerieLog - Control de series</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
<style>
body {
    background-color: #EBEBEB;
    padding-top: 56px;
    padding-bottom: 60px;
    min-height: 100vh;
}
.navbar, .footer {
    z-index: 1030;
}
.footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #343a40;
    color: #ced4da;
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
    text-align: center;
}
.barra-progreso {
    height: 12px;
}
</style>
</head>
<body>

<nav class="navbar navbar-expand navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="<?php echo $self; ?>">
        <i class="fas fa-tv"></i> SerieLog
    </a>
    <span class="navbar-text small ml-auto d-none d-sm-inline">
        Generado por GLM (versión no verificable) el <?php echo $fechaGeneracion; ?>
    </span>
</nav>

<div class="container py-4">

    <?php if ($mensaje !== ''): ?>
    <div class="alert alert-<?php echo htmlspecialchars($tipoMensaje, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($serieEditar !== null): ?>
    <!-- ===================== FORMULARIO DE EDICIÓN ===================== -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-dark text-white">
            <i class="fas fa-edit"></i> Editar serie
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo $self; ?>">
                <input type="hidden" name="id" value="<?php echo (int)$serieEditar['id']; ?>">
                <input type="hidden" name="editar" value="1">
                <div class="form-group">
                    <label for="e_titulo">Título</label>
                    <input type="text" class="form-control" id="e_titulo" name="titulo" maxlength="150" required
                           value="<?php echo htmlspecialchars($serieEditar['titulo'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="e_temporada">Temporada (1 a 5)</label>
                        <select class="form-control" id="e_temporada" name="temporada" required>
                            <?php for ($t = 1; $t <= 5; $t++): ?>
                            <option value="<?php echo $t; ?>"<?php echo ((int)$serieEditar['temporada'] === $t) ? ' selected' : ''; ?>><?php echo $t; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="e_total">Total de episodios</label>
                        <input type="number" class="form-control" id="e_total" name="total_episodios" min="1" max="65535" required
                               value="<?php echo (int)$serieEditar['total_episodios']; ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="e_actual">Episodio actual</label>
                        <input type="number" class="form-control" id="e_actual" name="episodio_actual" min="0" max="65535"
                               value="<?php echo (int)$serieEditar['episodio_actual']; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="e_enlace">Enlace (opcional)</label>
                    <input type="text" class="form-control" id="e_enlace" name="enlace" maxlength="500" placeholder="https://..."
                           value="<?php echo htmlspecialchars($serieEditar['enlace'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="e_comentarios">Comentarios (opcional)</label>
                    <textarea class="form-control" id="e_comentarios" name="comentarios" rows="3"><?php echo htmlspecialchars($serieEditar['comentarios'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
                <a href="<?php echo $self; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- ===================== FORMULARIO DE ALTA ===================== -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-dark text-white">
            <i class="fas fa-plus-circle"></i> Agregar serie
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo $self; ?>">
                <input type="hidden" name="agregar" value="1">
                <div class="form-group">
                    <label for="titulo">Título</label>
                    <input type="text" class="form-control" id="titulo" name="titulo" maxlength="150" required
                           value="<?php echo htmlspecialchars($formTitulo ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="temporada">Temporada (1 a 5)</label>
                        <select class="form-control" id="temporada" name="temporada" required>
                            <?php for ($t = 1; $t <= 5; $t++): ?>
                            <option value="<?php echo $t; ?>"<?php echo (($formTemporada ?? 1) == $t) ? ' selected' : ''; ?>><?php echo $t; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="total_episodios">Total de episodios</label>
                        <input type="number" class="form-control" id="total_episodios" name="total_episodios" min="1" max="65535" required
                               value="<?php echo htmlspecialchars((string)($formTotal ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="episodio_actual">Episodio actual (opcional)</label>
                        <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0" max="65535"
                               value="<?php echo htmlspecialchars((string)($formActual ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="enlace">Enlace (opcional)</label>
                    <input type="text" class="form-control" id="enlace" name="enlace" maxlength="500" placeholder="https://..."
                           value="<?php echo htmlspecialchars($formEnlace ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="comentarios">Comentarios (opcional)</label>
                    <textarea class="form-control" id="comentarios" name="comentarios" rows="3"><?php echo htmlspecialchars($formComentarios ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Agregar</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================== TABLA DE SERIES ===================== -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <i class="fas fa-list"></i> Mis series
        </div>
        <div class="card-body">
            <?php if (empty($series)): ?>
            <p class="text-muted mb-0">No hay series registradas todavía.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>Título</th>
                            <th>Temporada</th>
                            <th>Progreso</th>
                            <th>Fecha inicio</th>
                            <th>Fecha fin</th>
                            <th>Enlace</th>
                            <th>Comentarios</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($series as $s):
                            $total = (int)$s['total_episodios'];
                            $actual = (int)$s['episodio_actual'];
                            $porcentaje = ($total > 0) ? (int)round(($actual / $total) * 100) : 0;
                            if ($porcentaje < 0) { $porcentaje = 0; }
                            if ($porcentaje > 100) { $porcentaje = 100; }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int)$s['temporada']; ?></td>
                            <td style="min-width:140px;">
                                <div class="small mb-1"><?php echo $actual . '/' . $total . ' (' . $porcentaje . '%)'; ?></div>
                                <div class="progress barra-progreso">
                                    <div class="progress-bar<?php echo ($porcentaje >= 100) ? ' bg-success' : ''; ?>"
                                         role="progressbar"
                                         style="width: <?php echo $porcentaje; ?>%;"
                                         aria-valuenow="<?php echo $porcentaje; ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($s['fecha_inicio'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if ($s['fecha_fin'] !== null): ?>
                                    <span class="badge badge-success">Terminada</span>
                                    <?php echo htmlspecialchars($s['fecha_fin'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php else: ?>
                                    <span class="badge badge-info">En curso</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['enlace'])):
                                    $hostEnlace = parse_url($s['enlace'], PHP_URL_HOST);
                                    $hostVisible = $hostEnlace !== false && $hostEnlace !== null ? $hostEnlace : $s['enlace'];
                                ?>
                                <a href="<?php echo htmlspecialchars($s['enlace'], ENT_QUOTES, 'UTF-8'); ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt"></i> <?php echo htmlspecialchars($hostVisible, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars($s['comentarios'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                            <td>
                                <div class="btn-group-vertical btn-group-sm" role="group">
                                    <a href="<?php echo $self . '?editar_id=' . (int)$s['id']; ?>"
                                       class="btn btn-outline-secondary" title="Editar">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <?php if ($s['fecha_fin'] === null): ?>
                                    <form method="post" action="<?php echo $self; ?>" class="mb-1">
                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                        <input type="hidden" name="terminar" value="1">
                                        <button type="submit" class="btn btn-outline-success btn-sm w-100" title="Marcar como terminada">
                                            <i class="fas fa-check"></i> Terminada
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo $self; ?>"
                                          onsubmit="return confirm('¿Eliminar esta serie? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                        <input type="hidden" name="eliminar" value="1">
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100" title="Eliminar">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<footer class="footer">
    SerieLog - Control de series &middot; Licencia MIT &middot;
    Generado por GLM (versión no verificable) el <?php echo $fechaGeneracion; ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
