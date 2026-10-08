<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
CHatgpt no especifica modelo
*/
require_once 'config.php';

mysqli_query(
    $link,
    "CREATE TABLE IF NOT EXISTS `series` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$self = htmlspecialchars($_SERVER['PHP_SELF'] ?? '', ENT_QUOTES, 'UTF-8');

$mensaje = $_SESSION['serielog_mensaje'] ?? '';
$tipo_mensaje = $_SESSION['serielog_tipo'] ?? 'success';
unset($_SESSION['serielog_mensaje'], $_SESSION['serielog_tipo']);

$error = '';
$editar = null;

function guardar_mensaje(string $mensaje, string $tipo = 'success'): void
{
    $_SESSION['serielog_mensaje'] = $mensaje;
    $_SESSION['serielog_tipo'] = $tipo;
}

function redirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function obtener_texto_post(string $campo): string
{
    return trim((string)($_POST[$campo] ?? ''));
}

function validar_datos_serie(): array
{
    $titulo = obtener_texto_post('titulo');
    $temporada = filter_var($_POST['temporada'] ?? null, FILTER_VALIDATE_INT);
    $total_episodios = filter_var($_POST['total_episodios'] ?? null, FILTER_VALIDATE_INT);
    $episodio_actual = ($_POST['episodio_actual'] ?? '') === ''
        ? 0
        : filter_var($_POST['episodio_actual'], FILTER_VALIDATE_INT);
    $enlace = obtener_texto_post('enlace');
    $comentarios = obtener_texto_post('comentarios');

    if ($titulo === '') {
        return [false, 'El título es obligatorio.', null];
    }

    if (mb_strlen($titulo, 'UTF-8') > 150) {
        return [false, 'El título no puede superar los 150 caracteres.', null];
    }

    if ($temporada === false || $temporada < 1 || $temporada > 5) {
        return [false, 'La temporada debe estar entre 1 y 5.', null];
    }

    if ($total_episodios === false || $total_episodios < 1) {
        return [false, 'El total de episodios debe ser como mínimo 1.', null];
    }

    if ($episodio_actual === false || $episodio_actual < 0 || $episodio_actual > $total_episodios) {
        return [false, 'El episodio actual debe estar entre 0 y el total de episodios.', null];
    }

    if ($enlace !== '') {
        if (mb_strlen($enlace, 'UTF-8') > 500) {
            return [false, 'El enlace no puede superar los 500 caracteres.', null];
        }

        $partes_url = parse_url($enlace);
        $esquema = strtolower((string)($partes_url['scheme'] ?? ''));

        if (!in_array($esquema, ['http', 'https'], true)) {
            return [false, 'El enlace debe comenzar con http:// o https://.', null];
        }
    }

    return [
        true,
        '',
        [
            'titulo' => $titulo,
            'temporada' => $temporada,
            'total_episodios' => $total_episodios,
            'episodio_actual' => $episodio_actual,
            'enlace' => $enlace,
            'comentarios' => $comentarios
        ]
    ];
}

/*
 * Procesamiento de POST
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'agregar' || $accion === 'editar') {
        [$valido, $error_validacion, $datos] = validar_datos_serie();

        if (!$valido) {
            $error = $error_validacion;
        } elseif ($accion === 'agregar') {
            $sql = "INSERT INTO `series`
                    (`titulo`, `temporada`, `total_episodios`, `episodio_actual`, `fecha_inicio`, `enlace`, `comentarios`)
                    VALUES (?, ?, ?, ?, CURDATE(), NULLIF(?, ''), NULLIF(?, ''))";

            $stmt = mysqli_prepare($link, $sql);

            if ($stmt === false) {
                $error = 'No se pudo preparar el registro de la serie.';
            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    'siiiss',
                    $datos['titulo'],
                    $datos['temporada'],
                    $datos['total_episodios'],
                    $datos['episodio_actual'],
                    $datos['enlace'],
                    $datos['comentarios']
                );

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    guardar_mensaje('La serie se agregó correctamente.');
                    redirigir($_SERVER['PHP_SELF'] ?? '');
                }

                $error = 'No se pudo guardar la serie.';
                mysqli_stmt_close($stmt);
            }
        } else {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if ($id === false || $id < 1) {
                $error = 'La serie indicada no es válida.';
            } else {
                $sql = "UPDATE `series`
                        SET `titulo` = ?,
                            `temporada` = ?,
                            `total_episodios` = ?,
                            `episodio_actual` = ?,
                            `enlace` = NULLIF(?, ''),
                            `comentarios` = NULLIF(?, '')
                        WHERE `id` = ?";

                $stmt = mysqli_prepare($link, $sql);

                if ($stmt === false) {
                    $error = 'No se pudo preparar la actualización.';
                } else {
                    mysqli_stmt_bind_param(
                        $stmt,
                        'siiissi',
                        $datos['titulo'],
                        $datos['temporada'],
                        $datos['total_episodios'],
                        $datos['episodio_actual'],
                        $datos['enlace'],
                        $datos['comentarios'],
                        $id
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        guardar_mensaje('La serie se actualizó correctamente.');
                        redirigir($_SERVER['PHP_SELF'] ?? '');
                    }

                    $error = 'No se pudo actualizar la serie.';
                    mysqli_stmt_close($stmt);
                }
            }
        }
    } elseif ($accion === 'terminar') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            $error = 'La serie indicada no es válida.';
        } else {
            /*
             * fecha_fin IS NULL garantiza que una serie ya terminada
             * nunca sea sobrescrita por un intento posterior.
             */
            $sql = "UPDATE `series`
                    SET `fecha_fin` = CURDATE()
                    WHERE `id` = ? AND `fecha_fin` IS NULL";

            $stmt = mysqli_prepare($link, $sql);

            if ($stmt === false) {
                $error = 'No se pudo preparar la operación.';
            } else {
                mysqli_stmt_bind_param($stmt, 'i', $id);

                if (mysqli_stmt_execute($stmt)) {
                    $afectadas = mysqli_stmt_affected_rows($stmt);
                    mysqli_stmt_close($stmt);

                    if ($afectadas > 0) {
                        guardar_mensaje('La serie se marcó como terminada.');
                    } else {
                        guardar_mensaje('La serie ya estaba terminada.');
                    }

                    redirigir($_SERVER['PHP_SELF'] ?? '');
                }

                $error = 'No se pudo marcar la serie como terminada.';
                mysqli_stmt_close($stmt);
            }
        }
    } elseif ($accion === 'eliminar') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            $error = 'La serie indicada no es válida.';
        } else {
            $sql = "DELETE FROM `series` WHERE `id` = ?";

            $stmt = mysqli_prepare($link, $sql);

            if ($stmt === false) {
                $error = 'No se pudo preparar la eliminación.';
            } else {
                mysqli_stmt_bind_param($stmt, 'i', $id);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    guardar_mensaje('La serie se eliminó correctamente.');
                    redirigir($_SERVER['PHP_SELF'] ?? '');
                }

                $error = 'No se pudo eliminar la serie.';
                mysqli_stmt_close($stmt);
            }
        }
    }
}

/*
 * Cargar serie para edición.
 * El GET solamente selecciona qué registro mostrar en el formulario;
 * ninguna modificación se realiza por GET.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['editar'])) {
    $id_editar = filter_var($_GET['editar'], FILTER_VALIDATE_INT);

    if ($id_editar !== false && $id_editar > 0) {
        $sql = "SELECT
                    `id`,
                    `titulo`,
                    `temporada`,
                    `total_episodios`,
                    `episodio_actual`,
                    `enlace`,
                    `comentarios`
                FROM `series`
                WHERE `id` = ?";

        $stmt = mysqli_prepare($link, $sql);

        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, 'i', $id_editar);
            mysqli_stmt_execute($stmt);
            $resultado_editar = mysqli_stmt_get_result($stmt);

            if ($resultado_editar !== false) {
                $editar = mysqli_fetch_assoc($resultado_editar) ?: null;
            }

            mysqli_stmt_close($stmt);
        }

        if ($editar === null) {
            $error = 'La serie que deseas editar no existe.';
        }
    } else {
        $error = 'La serie indicada no es válida.';
    }
}

/*
 * Si hubo un POST con error de validación al agregar/editar,
 * conservar los datos introducidos para no perderlos.
 */
$datos_formulario = [
    'id' => '',
    'titulo' => '',
    'temporada' => 1,
    'total_episodios' => '',
    'episodio_actual' => 0,
    'enlace' => '',
    'comentarios' => ''
];

if ($editar !== null) {
    $datos_formulario = $editar;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
    $datos_formulario['id'] = (string)($_POST['id'] ?? '');
    $datos_formulario['titulo'] = obtener_texto_post('titulo');
    $datos_formulario['temporada'] = $_POST['temporada'] ?? 1;
    $datos_formulario['total_episodios'] = $_POST['total_episodios'] ?? '';
    $datos_formulario['episodio_actual'] = $_POST['episodio_actual'] ?? 0;
    $datos_formulario['enlace'] = obtener_texto_post('enlace');
    $datos_formulario['comentarios'] = obtener_texto_post('comentarios');

    if (($datos_formulario['id'] ?? '') !== '') {
        $id_error = filter_var($datos_formulario['id'], FILTER_VALIDATE_INT);

        if ($id_error !== false && $id_error > 0) {
            $editar = $datos_formulario;
        }
    }
}

/*
 * Obtener todas las series, más recientes primero.
 */
$series = [];

$resultado = mysqli_query(
    $link,
    "SELECT
        `id`,
        `titulo`,
        `temporada`,
        `total_episodios`,
        `episodio_actual`,
        `fecha_inicio`,
        `fecha_fin`,
        `enlace`,
        `comentarios`
     FROM `series`
     ORDER BY `created_at` DESC, `id` DESC"
);

if ($resultado !== false) {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $series[] = $fila;
    }
    mysqli_free_result($resultado);
}

function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function porcentaje_progreso(int $actual, int $total): int
{
    if ($total <= 0) {
        return 0;
    }

    return (int)round(($actual / $total) * 100);
}

function dominio_enlace(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);

    if (!is_string($host) || $host === '') {
        return $url;
    }

    return preg_replace('/^www\./i', '', $host) ?: $host;
}

$es_edicion = $editar !== null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SerieLog - Control de series</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css"
    >

    <style>
        html,
        body {
            min-height: 100%;
        }

        body {
            background: #EBEBEB;
            padding-top: 70px;
            padding-bottom: 80px;
        }

        .navbar {
            min-height: 62px;
        }

        .navbar-brand {
            font-weight: 600;
        }

        .model-info {
            font-size: 0.82rem;
            line-height: 1.25;
            text-align: right;
        }

        .main-container {
            max-width: 1500px;
        }

        .card {
            border: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .progress {
            min-width: 120px;
            height: 20px;
        }

        .progress-bar {
            font-size: 0.75rem;
        }

        .progress-wrapper {
            min-width: 150px;
        }

        .serie-title {
            min-width: 150px;
        }

        .comments-cell {
            min-width: 180px;
            max-width: 300px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .actions-cell {
            min-width: 190px;
        }

        .actions-cell form {
            display: inline-block;
        }

        .link-button {
            white-space: nowrap;
        }

        .fixed-footer {
            min-height: 58px;
            background: #343a40;
            color: #fff;
        }

        .footer-model-info {
            font-size: 0.78rem;
            line-height: 1.2;
        }

        @media (max-width: 767.98px) {
            body {
                padding-top: 105px;
                padding-bottom: 100px;
            }

            .navbar {
                min-height: 96px;
            }

            .model-info {
                text-align: left;
                margin-top: 4px;
            }

            .fixed-footer {
                min-height: 82px;
            }

            .footer-model-info {
                margin-top: 3px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $self; ?>">
            <i class="fas fa-tv mr-2" aria-hidden="true"></i>SerieLog
        </a>

        <div class="model-info text-light">
            Generado por GPT-5.6 Luna el 2026-10-07
        </div>
    </div>
</nav>

<main class="container-fluid main-container py-4">

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-<?php echo e($tipo_mensaje); ?> alert-dismissible fade show" role="alert">
            <?php echo e($mensaje); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle mr-2" aria-hidden="true"></i>
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-white">
            <h1 class="h4 mb-0">
                <i class="fas fa-<?php echo $es_edicion ? 'edit' : 'plus-circle'; ?> mr-2" aria-hidden="true"></i>
                <?php echo $es_edicion ? 'Editar serie' : 'Agregar serie'; ?>
            </h1>
        </div>

        <div class="card-body">
            <form method="post" action="<?php echo $self; ?>" novalidate>
                <input
                    type="hidden"
                    name="accion"
                    value="<?php echo $es_edicion ? 'editar' : 'agregar'; ?>"
                >

                <?php if ($es_edicion): ?>
                    <input
                        type="hidden"
                        name="id"
                        value="<?php echo e((string)$datos_formulario['id']); ?>"
                    >
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group col-12 col-lg-4">
                        <label for="titulo">Título <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="titulo"
                            name="titulo"
                            maxlength="150"
                            required
                            value="<?php echo e((string)$datos_formulario['titulo']); ?>"
                        >
                    </div>

                    <div class="form-group col-6 col-md-3 col-lg-2">
                        <label for="temporada">Temporada</label>
                        <select class="form-control" id="temporada" name="temporada" required>
                            <?php for ($t = 1; $t <= 5; $t++): ?>
                                <option
                                    value="<?php echo $t; ?>"
                                    <?php echo (int)$datos_formulario['temporada'] === $t ? 'selected' : ''; ?>
                                >
                                    <?php echo $t; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group col-6 col-md-3 col-lg-2">
                        <label for="total_episodios">Total de episodios</label>
                        <input
                            type="number"
                            class="form-control"
                            id="total_episodios"
                            name="total_episodios"
                            min="1"
                            required
                            value="<?php echo e((string)$datos_formulario['total_episodios']); ?>"
                        >
                    </div>

                    <div class="form-group col-6 col-md-3 col-lg-2">
                        <label for="episodio_actual">Episodio actual</label>
                        <input
                            type="number"
                            class="form-control"
                            id="episodio_actual"
                            name="episodio_actual"
                            min="0"
                            value="<?php echo e((string)$datos_formulario['episodio_actual']); ?>"
                        >
                        <small class="form-text text-muted">Por defecto: 0</small>
                    </div>

                    <div class="form-group col-12 col-md-6 col-lg-4">
                        <label for="enlace">Enlace</label>
                        <input
                            type="url"
                            class="form-control"
                            id="enlace"
                            name="enlace"
                            maxlength="500"
                            placeholder="https://..."
                            value="<?php echo e((string)$datos_formulario['enlace']); ?>"
                        >
                    </div>

                    <div class="form-group col-12">
                        <label for="comentarios">Comentarios</label>
                        <textarea
                            class="form-control"
                            id="comentarios"
                            name="comentarios"
                            rows="3"
                        ><?php echo e((string)$datos_formulario['comentarios']); ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1" aria-hidden="true"></i>
                    <?php echo $es_edicion ? 'Guardar cambios' : 'Agregar serie'; ?>
                </button>

                <?php if ($es_edicion): ?>
                    <a href="<?php echo $self; ?>" class="btn btn-secondary">
                        <i class="fas fa-times mr-1" aria-hidden="true"></i>
                        Cancelar
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">
                <i class="fas fa-list mr-2" aria-hidden="true"></i>
                Mis series
            </h2>

            <span class="badge badge-secondary">
                <?php echo count($series); ?>
                <?php echo count($series) === 1 ? 'serie' : 'series'; ?>
            </span>
        </div>

        <div class="card-body p-0">
            <?php if (count($series) === 0): ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-tv fa-2x mb-3" aria-hidden="true"></i>
                    <p class="mb-0">Todavía no tienes series registradas.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col">Título</th>
                                <th scope="col">Temporada</th>
                                <th scope="col">Progreso</th>
                                <th scope="col">Fecha de inicio</th>
                                <th scope="col">Fecha de fin</th>
                                <th scope="col">Enlace</th>
                                <th scope="col">Comentarios</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($series as $serie): ?>
                                <?php
                                $actual = (int)$serie['episodio_actual'];
                                $total = (int)$serie['total_episodios'];
                                $porcentaje = porcentaje_progreso($actual, $total);
                                ?>
                                <tr>
                                    <td class="serie-title font-weight-bold">
                                        <?php echo e((string)$serie['titulo']); ?>
                                    </td>

                                    <td>
                                        <?php echo (int)$serie['temporada']; ?>
                                    </td>

                                    <td>
                                        <div class="progress-wrapper">
                                            <div class="mb-1">
                                                <?php echo $actual; ?>/<?php echo $total; ?>
                                            </div>
                                            <div
                                                class="progress"
                                                role="progressbar"
                                                aria-valuenow="<?php echo $porcentaje; ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100"
                                            >
                                                <div
                                                    class="progress-bar"
                                                    style="width: <?php echo $porcentaje; ?>%;"
                                                >
                                                    <?php echo $porcentaje; ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <?php echo e((string)$serie['fecha_inicio']); ?>
                                    </td>

                                    <td>
                                        <?php if ($serie['fecha_fin'] === null): ?>
                                            <span class="badge badge-warning">En curso</span>
                                        <?php else: ?>
                                            <?php echo e((string)$serie['fecha_fin']); ?>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($serie['enlace'] !== null && trim((string)$serie['enlace']) !== ''): ?>
                                            <?php
                                            $url = (string)$serie['enlace'];
                                            $dominio = dominio_enlace($url);
                                            ?>
                                            <a
                                                href="<?php echo e($url); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-sm btn-outline-primary link-button"
                                                title="<?php echo e($url); ?>"
                                            >
                                                <i class="fas fa-external-link-alt mr-1" aria-hidden="true"></i>
                                                <?php echo e($dominio); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="comments-cell">
                                        <?php if ($serie['comentarios'] !== null && trim((string)$serie['comentarios']) !== ''): ?>
                                            <?php echo e((string)$serie['comentarios']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="actions-cell">
                                        <a
                                            href="<?php echo $self; ?>?editar=<?php echo (int)$serie['id']; ?>"
                                            class="btn btn-sm btn-outline-secondary mb-1"
                                        >
                                            <i class="fas fa-edit mr-1" aria-hidden="true"></i>
                                            Editar
                                        </a>

                                        <?php if ($serie['fecha_fin'] === null): ?>
                                            <form method="post" action="<?php echo $self; ?>" class="mb-1">
                                                <input type="hidden" name="accion" value="terminar">
                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo (int)$serie['id']; ?>"
                                                >
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check mr-1" aria-hidden="true"></i>
                                                    Terminada
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form
                                            method="post"
                                            action="<?php echo $self; ?>"
                                            class="mb-1"
                                            onsubmit="return confirm('¿Seguro que deseas eliminar esta serie?');"
                                        >
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int)$serie['id']; ?>"
                                            >
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash-alt mr-1" aria-hidden="true"></i>
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<footer class="fixed-footer fixed-bottom">
    <div class="container-fluid py-2">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div>
                <strong>SerieLog - Control de series</strong>
                <span class="ml-md-2">Licencia MIT</span>
            </div>
            <div class="footer-model-info text-md-right">
                Generado por GPT-5.6 Luna el 2026-10-07
            </div>
        </div>
    </div>
</footer>

</body>
</html>
