<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
 Generado por Claude Sonnet 5.5 el 07/10/2026
*/

require_once 'config.php';

const ID_MODELO = 'Generado por Claude Sonnet 5.5 el 07/10/2026';

// ---------------------------------------------------------------------------
// Tabla
// ---------------------------------------------------------------------------
mysqli_query($link, "CREATE TABLE IF NOT EXISTS `series` (
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
mysqli_set_charset($link, 'utf8mb4');

// ---------------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------------
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function campo(array $in, string $k): string
{
    return (isset($in[$k]) && is_string($in[$k])) ? trim($in[$k]) : '';
}

function validar_serie(array $in): array
{
    $errores = [];
    $titulo = campo($in, 'titulo');
    $temp_s = campo($in, 'temporada');
    $total_s = campo($in, 'total_episodios');
    $ep_s = campo($in, 'episodio_actual');
    $enlace = campo($in, 'enlace');
    $coment = campo($in, 'comentarios');

    if ($titulo === '') {
        $errores[] = 'El título es obligatorio.';
    } elseif (mb_strlen($titulo, 'UTF-8') > 150) {
        $errores[] = 'El título no puede exceder 150 caracteres.';
    }

    $temporada = 0;
    if (!preg_match('/^\d+$/', $temp_s) || ($temporada = (int)$temp_s) < 1 || $temporada > 5) {
        $errores[] = 'La temporada debe ser un número entre 1 y 5.';
    }

    $total = 0;
    if (!preg_match('/^\d+$/', $total_s) || ($total = (int)$total_s) < 1 || $total > 65535) {
        $errores[] = 'El total de episodios debe ser un número entero mayor o igual a 1.';
        $total = 0;
    }

    $ep = 0;
    if ($ep_s !== '') {
        if (!preg_match('/^\d+$/', $ep_s) || ($ep = (int)$ep_s) > 65535) {
            $errores[] = 'El episodio actual debe ser un número entero mayor o igual a 0.';
            $ep = 0;
        } elseif ($total >= 1 && $ep > $total) {
            $errores[] = 'El episodio actual no puede ser mayor que el total de episodios.';
        }
    }

    if ($enlace !== '') {
        if (!preg_match('#^https?://#i', $enlace) || mb_strlen($enlace, 'UTF-8') > 500
            || !is_string(parse_url($enlace, PHP_URL_HOST))) {
            $errores[] = 'El enlace debe comenzar con http:// o https:// y ser una dirección válida (máx. 500 caracteres).';
        }
    }

    $datos = [
        'titulo' => $titulo,
        'temporada' => $temporada,
        'total_episodios' => $total,
        'episodio_actual' => $ep,
        'enlace' => $enlace,
        'comentarios' => $coment,
        // valores crudos para repoblar el formulario si hay errores
        'raw_temporada' => $temp_s,
        'raw_total' => $total_s,
        'raw_episodio' => $ep_s,
    ];
    return [$errores, $datos];
}

function dominio(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return 'enlace';
    }
    return preg_replace('/^www\./i', '', $host);
}

function fecha_corta(string $f): string
{
    $t = strtotime($f);
    return $t === false ? $f : date('d/m/Y', $t);
}

function render_form(string $self, array $v, ?int $edit_id): void
{
    $es_edicion = $edit_id !== null;
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <strong>
                <i class="fas <?= $es_edicion ? 'fa-edit' : 'fa-plus-circle' ?> mr-1"></i>
                <?= $es_edicion ? 'Editar serie' : 'Agregar serie' ?>
            </strong>
        </div>
        <div class="card-body">
            <form method="post" action="<?= $self ?>">
                <input type="hidden" name="accion" value="<?= $es_edicion ? 'editar' : 'agregar' ?>">
                <?php if ($es_edicion): ?>
                    <input type="hidden" name="id" value="<?= (int)$edit_id ?>">
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="titulo">Título</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" maxlength="150" required
                               value="<?= h($v['titulo']) ?>">
                    </div>
                    <div class="form-group col-6 col-md-2">
                        <label for="temporada">Temporada</label>
                        <select class="form-control" id="temporada" name="temporada" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"<?= ((string)$v['temporada'] === (string)$i) ? ' selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group col-6 col-md-2">
                        <label for="total_episodios">Total de episodios</label>
                        <input type="number" class="form-control" id="total_episodios" name="total_episodios"
                               min="1" max="65535" required value="<?= h((string)$v['total_episodios']) ?>">
                    </div>
                    <div class="form-group col-6 col-md-2">
                        <label for="episodio_actual">Episodio actual</label>
                        <input type="number" class="form-control" id="episodio_actual" name="episodio_actual"
                               min="0" max="65535" placeholder="0" value="<?= h((string)$v['episodio_actual']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="enlace">Enlace (opcional)</label>
                    <input type="text" class="form-control" id="enlace" name="enlace" maxlength="500"
                           placeholder="https://..." value="<?= h($v['enlace']) ?>">
                </div>
                <div class="form-group">
                    <label for="comentarios">Comentarios (opcional)</label>
                    <textarea class="form-control" id="comentarios" name="comentarios" rows="3"><?= h($v['comentarios']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i><?= $es_edicion ? 'Guardar cambios' : 'Agregar' ?>
                </button>
                <?php if ($es_edicion): ?>
                    <a href="<?= $self ?>" class="btn btn-outline-secondary ml-1">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Estado
// ---------------------------------------------------------------------------
$self = h($_SERVER['PHP_SELF']);
$base = preg_replace('/[\x00-\x1F\x7F?#].*$/s', '', (string)$_SERVER['PHP_SELF']);

$errores = [];
$edit_id = null;
$valores_form = [
    'titulo' => '', 'temporada' => 1, 'total_episodios' => '', 'episodio_actual' => '',
    'enlace' => '', 'comentarios' => '',
];
$mensajes = [
    'agregada'      => ['success', 'Serie agregada correctamente.'],
    'editada'       => ['success', 'Serie actualizada correctamente.'],
    'terminada'     => ['success', 'Serie marcada como terminada.'],
    'ya_terminada'  => ['info', 'Esa serie ya estaba marcada como terminada; no se modificó la fecha.'],
    'eliminada'     => ['success', 'Serie eliminada correctamente.'],
    'no_encontrada' => ['warning', 'La serie solicitada no existe.'],
    'error_bd'      => ['danger', 'Ocurrió un error al guardar en la base de datos.'],
];

// ---------------------------------------------------------------------------
// Acciones POST
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = campo($_POST, 'accion');
    $id = (preg_match('/^\d+$/', campo($_POST, 'id'))) ? (int)campo($_POST, 'id') : 0;
    $msg = null;

    try {
        if ($accion === 'agregar') {
            [$errores, $d] = validar_serie($_POST);
            if ($errores) {
                $valores_form = [
                    'titulo' => $d['titulo'], 'temporada' => $d['raw_temporada'],
                    'total_episodios' => $d['raw_total'], 'episodio_actual' => $d['raw_episodio'],
                    'enlace' => $d['enlace'], 'comentarios' => $d['comentarios'],
                ];
            } else {
                $enlace = $d['enlace'] === '' ? null : $d['enlace'];
                $coment = $d['comentarios'] === '' ? null : $d['comentarios'];
                $stmt = mysqli_prepare($link,
                    'INSERT INTO series (titulo, temporada, total_episodios, episodio_actual, fecha_inicio, enlace, comentarios)
                     VALUES (?, ?, ?, ?, CURDATE(), ?, ?)');
                mysqli_stmt_bind_param($stmt, 'siiiss', $d['titulo'], $d['temporada'],
                    $d['total_episodios'], $d['episodio_actual'], $enlace, $coment);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = 'agregada';
            }
        } elseif ($accion === 'editar') {
            [$errores, $d] = validar_serie($_POST);
            if ($errores) {
                $edit_id = $id;
                $valores_form = [
                    'titulo' => $d['titulo'], 'temporada' => $d['raw_temporada'],
                    'total_episodios' => $d['raw_total'], 'episodio_actual' => $d['raw_episodio'],
                    'enlace' => $d['enlace'], 'comentarios' => $d['comentarios'],
                ];
            } else {
                $enlace = $d['enlace'] === '' ? null : $d['enlace'];
                $coment = $d['comentarios'] === '' ? null : $d['comentarios'];
                $stmt = mysqli_prepare($link,
                    'UPDATE series SET titulo = ?, temporada = ?, total_episodios = ?, episodio_actual = ?,
                            enlace = ?, comentarios = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'siiissi', $d['titulo'], $d['temporada'],
                    $d['total_episodios'], $d['episodio_actual'], $enlace, $coment, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // Verificar existencia (affected_rows es 0 si no hubo cambios)
                $stmt = mysqli_prepare($link, 'SELECT id FROM series WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $existe = mysqli_fetch_assoc($res) !== null;
                mysqli_stmt_close($stmt);
                $msg = $existe ? 'editada' : 'no_encontrada';
            }
        } elseif ($accion === 'terminar') {
            $stmt = mysqli_prepare($link, 'UPDATE series SET fecha_fin = CURDATE() WHERE id = ? AND fecha_fin IS NULL');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $afectadas = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            $msg = $afectadas > 0 ? 'terminada' : 'ya_terminada';
        } elseif ($accion === 'eliminar') {
            $stmt = mysqli_prepare($link, 'DELETE FROM series WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $afectadas = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            $msg = $afectadas > 0 ? 'eliminada' : 'no_encontrada';
        }
    } catch (Throwable $e) {
        $msg = 'error_bd';
    }

    if ($msg !== null) {
        header('Location: ' . $base . '?msg=' . $msg);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Edición (GET ?editar=ID)
// ---------------------------------------------------------------------------
$flash = null;
$gm = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : '';
if (isset($mensajes[$gm])) {
    $flash = $mensajes[$gm];
}

if ($edit_id === null && isset($_GET['editar']) && is_string($_GET['editar']) && preg_match('/^\d+$/', $_GET['editar'])) {
    $eid = (int)$_GET['editar'];
    $stmt = mysqli_prepare($link, 'SELECT titulo, temporada, total_episodios, episodio_actual, enlace, comentarios FROM series WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $eid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if ($fila) {
        $edit_id = $eid;
        $valores_form = [
            'titulo' => $fila['titulo'], 'temporada' => $fila['temporada'],
            'total_episodios' => $fila['total_episodios'], 'episodio_actual' => $fila['episodio_actual'],
            'enlace' => (string)$fila['enlace'], 'comentarios' => (string)$fila['comentarios'],
        ];
    } else {
        $flash = $mensajes['no_encontrada'];
    }
}

// ---------------------------------------------------------------------------
// Listado
// ---------------------------------------------------------------------------
$series = [];
$resultado = mysqli_query($link, 'SELECT id, titulo, temporada, total_episodios, episodio_actual, fecha_inicio, fecha_fin, enlace, comentarios
                                  FROM series ORDER BY created_at DESC, id DESC');
if ($resultado) {
    while ($f = mysqli_fetch_assoc($resultado)) {
        $series[] = $f;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SerieLog - Control de series</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body { background-color: #EBEBEB; padding-top: 80px; padding-bottom: 90px; }
        .comentarios-celda { min-width: 180px; max-width: 320px; white-space: normal; }
        .progreso-celda { min-width: 150px; }
        .acciones-celda { white-space: nowrap; }
        .acciones-celda form { display: inline-block; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark fixed-top">
    <span class="navbar-brand mb-0"><i class="fas fa-tv mr-2"></i>SerieLog</span>
    <span class="navbar-text small py-0"><?= h(ID_MODELO) ?></span>
</nav>

<main class="container">

    <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash[0]) ?>" role="alert"><?= h($flash[1]) ?></div>
    <?php endif; ?>

    <?php if ($errores): ?>
        <div class="alert alert-danger" role="alert">
            <strong>No se guardó la serie:</strong>
            <ul class="mb-0">
                <?php foreach ($errores as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php render_form($self, $valores_form, $edit_id); ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <strong><i class="fas fa-list mr-1"></i>Mis series (<?= count($series) ?>)</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-light">
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
                    <?php if (!$series): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Aún no hay series registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($series as $s):
                        $total = (int)$s['total_episodios'];
                        $actual = (int)$s['episodio_actual'];
                        $pct = $total > 0 ? (int)round($actual / $total * 100) : 0;
                        $pct = max(0, min(100, $pct));
                        $terminada = $s['fecha_fin'] !== null;
                    ?>
                        <tr>
                            <td class="font-weight-bold"><?= h($s['titulo']) ?></td>
                            <td><?= (int)$s['temporada'] ?></td>
                            <td class="progreso-celda">
                                <div class="small mb-1"><?= $actual ?>/<?= $total ?></div>
                                <div class="progress" style="height: 18px;">
                                    <div class="progress-bar<?= $pct >= 100 ? ' bg-success' : '' ?>" role="progressbar"
                                         style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>"
                                         aria-valuemin="0" aria-valuemax="100"><?= $pct ?>%</div>
                                </div>
                            </td>
                            <td><?= h(fecha_corta($s['fecha_inicio'])) ?></td>
                            <td>
                                <?php if ($terminada): ?>
                                    <?= h(fecha_corta($s['fecha_fin'])) ?>
                                <?php else: ?>
                                    <span class="badge badge-info">En curso</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['enlace'] !== null && $s['enlace'] !== ''): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= h($s['enlace']) ?>"
                                       target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-external-link-alt mr-1"></i><?= h(dominio($s['enlace'])) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="comentarios-celda">
                                <?= ($s['comentarios'] !== null && $s['comentarios'] !== '')
                                    ? nl2br(h($s['comentarios'])) : '<span class="text-muted">—</span>' ?>
                            </td>
                            <td class="acciones-celda">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= $self ?>?editar=<?= (int)$s['id'] ?>">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <?php if (!$terminada): ?>
                                    <form method="post" action="<?= $self ?>">
                                        <input type="hidden" name="accion" value="terminar">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Terminada
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= $self ?>" onsubmit="return confirm('¿Eliminar esta serie?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<footer class="fixed-bottom bg-dark text-light text-center small py-2">
    SerieLog - Control de series · Licencia MIT<br>
    <?= h(ID_MODELO) ?>
</footer>

</body>
</html>
