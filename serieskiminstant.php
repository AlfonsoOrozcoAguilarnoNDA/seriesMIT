<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
Kimi instant, probablemente una version de 2.8 o 3
*/
declare(strict_types=1);

require_once 'config.php';
/** @var mysqli $link Conexión MySQLi activa definida en config.php */

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$self = e($_SERVER['PHP_SELF']);
$errores = [];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$old = ['titulo' => '', 'temporada' => '1', 'total_episodios' => '', 'episodio_actual' => '0', 'enlace' => '', 'comentarios' => ''];

/**
 * Valida los campos comunes de agregar/editar. Devuelve array limpio o false si hay errores.
 */
function validar_serie(array $in, array &$errores): ?array
{
    $d = [
        'titulo'          => trim((string) ($in['titulo'] ?? '')),
        'temporada'       => (int) ($in['temporada'] ?? 0),
        'total_episodios' => (int) ($in['total_episodios'] ?? 0),
        'episodio_actual' => (int) ($in['episodio_actual'] ?? 0),
        'enlace'          => trim((string) ($in['enlace'] ?? '')),
        'comentarios'     => trim((string) ($in['comentarios'] ?? '')),
    ];

    if ($d['titulo'] === '' || mb_strlen($d['titulo']) > 150) {
        $errores[] = 'El título es obligatorio (máximo 150 caracteres).';
    }
    if ($d['temporada'] < 1 || $d['temporada'] > 5) {
        $errores[] = 'La temporada debe estar entre 1 y 5.';
    }
    if ($d['total_episodios'] < 1) {
        $errores[] = 'El total de episodios debe ser al menos 1.';
    }
    if ($d['episodio_actual'] < 0 || $d['episodio_actual'] > $d['total_episodios']) {
        $errores[] = 'El episodio actual debe estar entre 0 y el total de episodios.';
    }
    if ($d['enlace'] !== '') {
        if (!preg_match('#^https?://#i', $d['enlace']) || mb_strlen($d['enlace']) > 500) {
            $errores[] = 'El enlace debe empezar con http:// o https:// (máximo 500 caracteres).';
        }
    } else {
        $d['enlace'] = null;
    }
    if ($d['comentarios'] === '') {
        $d['comentarios'] = null;
    }

    return $errores === [] ? $d : null;
}

function redirigir(string $msg): void
{
    $_SESSION['flash'] = $msg;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* ============ PROCESAMIENTO POST ============ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        case 'add':
            $datos = validar_serie($_POST, $errores);
            foreach ($old as $k => $v) {
                if (isset($_POST[$k])) {
                    $old[$k] = (string) $_POST[$k];
                }
            }
            if ($datos !== null) {
                $stmt = mysqli_prepare($link, "INSERT INTO `series`
                    (`titulo`, `temporada`, `total_episodios`, `episodio_actual`, `fecha_inicio`, `enlace`, `comentarios`)
                    VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
                mysqli_stmt_bind_param($stmt, 'siiiss',
                    $datos['titulo'], $datos['temporada'], $datos['total_episodios'],
                    $datos['episodio_actual'], $datos['enlace'], $datos['comentarios']);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    redirigir('Serie agregada correctamente.');
                }
                $errores[] = 'Error al guardar: ' . mysqli_error($link);
                mysqli_stmt_close($stmt);
            }
            break;

        case 'edit':
            $id = (int) ($_POST['id'] ?? 0);
            $datos = validar_serie($_POST, $errores);
            if ($datos !== null && $id > 0) {
                $stmt = mysqli_prepare($link, "UPDATE `series` SET
                    `titulo` = ?, `temporada` = ?, `total_episodios` = ?, `episodio_actual` = ?,
                    `enlace` = ?, `comentarios` = ?
                    WHERE `id` = ?");
                mysqli_stmt_bind_param($stmt, 'siiissi',
                    $datos['titulo'], $datos['temporada'], $datos['total_episodios'],
                    $datos['episodio_actual'], $datos['enlace'], $datos['comentarios'], $id);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    redirigir('Serie actualizada correctamente.');
                }
                $errores[] = 'Error al actualizar: ' . mysqli_error($link);
                mysqli_stmt_close($stmt);
            }
            break;

        case 'terminar':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($link, "UPDATE `series` SET `fecha_fin` = CURDATE() WHERE `id` = ? AND `fecha_fin` IS NULL");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            redirigir('Serie marcada como terminada.');
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($link, "DELETE FROM `series` WHERE `id` = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            redirigir('Serie eliminada.');
            break;
    }
}

/* ============ CONSULTA ============ */
$series = [];
$res = mysqli_query($link, "SELECT * FROM `series` ORDER BY `id` DESC");
if ($res instanceof mysqli_result) {
    while ($fila = mysqli_fetch_assoc($res)) {
        $series[] = $fila;
    }
    mysqli_free_result($res);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SerieLog</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
<style>
    body { background-color: #EBEBEB; padding-top: 76px; padding-bottom: 66px; }
    .navbar-brand { font-weight: 700; }
    .model-id { font-size: .72rem; opacity: .75; }
    footer { line-height: 1.25; }
    .progress { background-color: #d5d5d5; }
    details.edit-row summary { list-style: none; cursor: pointer; display: inline-block; }
    details.edit-row summary::-webkit-details-marker { display: none; }
    details.edit-row[open] summary { display: none; }
</style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="<?= $self ?>"><i class="fas fa-tv mr-2"></i>SerieLog</a>
        <span class="navbar-text model-id text-white">Generado por Kimi Chat (versión no verificable) el 2026-10-07</span>
    </div>
</nav>

<main class="container">

    <?php if ($flash !== null): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-1"></i><?= e($flash) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        </div>
    <?php endif; ?>

    <?php if ($errores !== []): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle mr-1"></i><strong>No se pudo guardar:</strong>
            <ul class="mb-0">
                <?php foreach ($errores as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white"><i class="fas fa-plus-circle mr-2"></i>Agregar serie</div>
        <div class="card-body">
            <form method="post" action="<?= $self ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group col-md-5">
                        <label for="titulo">Título *</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" maxlength="150" required value="<?= e($old['titulo']) ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="temporada">Temporada *</label>
                        <select class="form-control" id="temporada" name="temporada">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= (int) $old['temporada'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="total_episodios">Total episodios *</label>
                        <input type="number" class="form-control" id="total_episodios" name="total_episodios" min="1" required value="<?= e($old['total_episodios']) ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="episodio_actual">Episodio actual</label>
                        <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0" value="<?= e($old['episodio_actual']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="enlace">Enlace (opcional)</label>
                        <input type="url" class="form-control" id="enlace" name="enlace" maxlength="500" placeholder="https://..." value="<?= e($old['enlace']) ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="comentarios">Comentarios (opcional)</label>
                        <input type="text" class="form-control" id="comentarios" name="comentarios" value="<?= e($old['comentarios']) ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-dark"><i class="fas fa-save mr-1"></i>Guardar</button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white"><i class="fas fa-list mr-2"></i>Mis series (<?= count($series) ?>)</div>
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
                <?php if ($series === []): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay series registradas todavía.</td></tr>
                <?php endif; ?>

                <?php foreach ($series as $s):
                    $pct = ((int) $s['total_episodios'] > 0)
                        ? (int) round(((int) $s['episodio_actual'] / (int) $s['total_episodios']) * 100)
                        : 0;
                    $host = null;
                    if ($s['enlace'] !== null) {
                        $host = parse_url((string) $s['enlace'], PHP_URL_HOST) ?: (string) $s['enlace'];
                    }
                ?>
                    <tr>
                        <td class="align-middle"><?= e($s['titulo']) ?></td>
                        <td class="align-middle text-center"><?= (int) $s['temporada'] ?></td>
                        <td class="align-middle" style="min-width:180px">
                            <small><?= (int) $s['episodio_actual'] ?>/<?= (int) $s['total_episodios'] ?></small>
                            <div class="progress" style="height:10px">
                                <div class="progress-bar bg-dark" role="progressbar" style="width: <?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="text-muted"><?= $pct ?>%</small>
                        </td>
                        <td class="align-middle"><?= e($s['fecha_inicio']) ?></td>
                        <td class="align-middle">
                            <?= $s['fecha_fin'] !== null ? e($s['fecha_fin']) : '<span class="badge badge-info">En curso</span>' ?>
                        </td>
                        <td class="align-middle">
                            <?php if ($s['enlace'] !== null): ?>
                                <a class="btn btn-sm btn-outline-dark" href="<?= e($s['enlace']) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fas fa-external-link-alt mr-1"></i><?= e($host) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle"><?= $s['comentarios'] !== null ? e($s['comentarios']) : '<span class="text-muted">—</span>' ?></td>
                        <td class="align-middle" style="white-space:nowrap">
                            <details class="edit-row d-inline-block">
                                <summary class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Editar</summary>
                                <form method="post" action="<?= $self ?>" class="border rounded p-3 bg-white shadow" style="position:absolute; z-index:1050; width:340px; white-space:normal">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <div class="form-group">
                                        <label>Título *</label>
                                        <input type="text" class="form-control form-control-sm" name="titulo" maxlength="150" required value="<?= e($s['titulo']) ?>">
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-4">
                                            <label>Temporada *</label>
                                            <select class="form-control form-control-sm" name="temporada">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <option value="<?= $i ?>" <?= (int) $s['temporada'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="form-group col-4">
                                            <label>Total *</label>
                                            <input type="number" class="form-control form-control-sm" name="total_episodios" min="1" required value="<?= (int) $s['total_episodios'] ?>">
                                        </div>
                                        <div class="form-group col-4">
                                            <label>Actual</label>
                                            <input type="number" class="form-control form-control-sm" name="episodio_actual" min="0" value="<?= (int) $s['episodio_actual'] ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Enlace</label>
                                        <input type="url" class="form-control form-control-sm" name="enlace" maxlength="500" placeholder="https://..." value="<?= e((string) $s['enlace']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Comentarios</label>
                                        <input type="text" class="form-control form-control-sm" name="comentarios" value="<?= e((string) $s['comentarios']) ?>">
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-dark"><i class="fas fa-save mr-1"></i>Guardar</button>
                                </form>
                            </details>

                            <?php if ($s['fecha_fin'] === null): ?>
                                <form method="post" action="<?= $self ?>" class="d-inline">
                                    <input type="hidden" name="action" value="terminar">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check mr-1"></i>Terminada</button>
                                </form>
                            <?php endif; ?>

                            <form method="post" action="<?= $self ?>" class="d-inline" onsubmit="return confirm('¿Eliminar esta serie?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt mr-1"></i>Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<footer class="bg-dark text-white fixed-bottom py-2">
    <div class="container text-center">
        <div>SerieLog - Control de series</div>
        <small class="text-white-50">Licencia MIT &mdash; Generado por Kimi Chat (versión no verificable) el 2026-10-07</small>
    </div>
</footer>

</body>
</html>
