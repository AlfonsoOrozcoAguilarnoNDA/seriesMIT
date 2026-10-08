<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
Qwen 3.7 Plus Modo Chat
*/
session_start();
require_once 'config.php';

$self = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
$modelId = "Generado por Qwen 3.7 el 08 de octubre de 2026";

$createTableSql = "CREATE TABLE IF NOT EXISTS series (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($link, $createTableSql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $titulo = trim($_POST['titulo'] ?? '');
        $temporada = (int)($_POST['temporada'] ?? 1);
        $total_episodios = (int)($_POST['total_episodios'] ?? 1);
        $episodio_actual = (int)($_POST['episodio_actual'] ?? 0);
        $enlace = trim($_POST['enlace'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        $errors = [];
        if ($titulo === '') $errors[] = "El título es obligatorio.";
        if ($temporada < 1 || $temporada > 5) $errors[] = "La temporada debe estar entre 1 y 5.";
        if ($total_episodios < 1) $errors[] = "El total de episodios debe ser al menos 1.";
        if ($episodio_actual < 0 || $episodio_actual > $total_episodios) {
            $errors[] = "El episodio actual debe estar entre 0 y el total de episodios.";
        }
        if ($enlace !== '' && !preg_match('/^https?:\/\//i', $enlace)) {
            $errors[] = "El enlace debe comenzar con http:// o https://";
        }

        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = mysqli_prepare($link, "INSERT INTO series (titulo, temporada, total_episodios, episodio_actual, fecha_inicio, enlace, comentarios) VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
                mysqli_stmt_bind_param($stmt, "siisss", $titulo, $temporada, $total_episodios, $episodio_actual, $enlace, $comentarios);
                $success = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['success'] = $success ? "Serie agregada correctamente." : "Error al agregar la serie.";
            } else {
                $stmt = mysqli_prepare($link, "UPDATE series SET titulo = ?, temporada = ?, total_episodios = ?, episodio_actual = ?, enlace = ?, comentarios = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "siissii", $titulo, $temporada, $total_episodios, $episodio_actual, $enlace, $comentarios, $id);
                $success = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['success'] = $success ? "Serie actualizada correctamente." : "Error al actualizar la serie.";
            }
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['old_input'] = $_POST;
            $redirectUrl = $_SERVER['PHP_SELF'];
            if ($action === 'edit' && $id > 0) {
                $redirectUrl .= "?edit=" . $id;
            }
            header("Location: " . $redirectUrl);
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($link, "DELETE FROM series WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['success'] = $success ? "Serie eliminada correctamente." : "Error al eliminar la serie.";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($action === 'finish') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($link, "UPDATE series SET fecha_fin = CURDATE() WHERE id = ? AND fecha_fin IS NULL");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($affected > 0) {
                $_SESSION['success'] = "Serie marcada como terminada.";
            } else {
                $_SESSION['info'] = "La serie ya estaba marcada como terminada.";
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editData = null;
if ($editId > 0) {
    $stmt = mysqli_prepare($link, "SELECT * FROM series WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $editId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $editData = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

$series = [];
$result = mysqli_query($link, "SELECT * FROM series ORDER BY created_at DESC, id DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $series[] = $row;
    }
}

$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['errors'], $_SESSION['old_input']);

$formData = $editData ?: [];
if (!empty($old)) {
    $formData = array_merge($formData, $old);
}
$val = function($key, $default = '') use ($formData) {
    return htmlspecialchars($formData[$key] ?? $default, ENT_QUOTES, 'UTF-8');
};

$isEdit = (bool)$editData;
$actionVal = $isEdit ? 'edit' : 'add';
$btnText = $isEdit ? 'Actualizar Serie' : 'Agregar Serie';
$cancelBtn = $isEdit ? '<a href="' . $self . '" class="btn btn-secondary">Cancelar</a>' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SerieLog - Control de series</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #EBEBEB;
            padding-top: 76px;
            padding-bottom: 70px;
        }
        .footer {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top border-bottom">
    <div class="container">
        <a class="navbar-brand font-weight-bold" href="<?= $self ?>">
            <i class="fas fa-tv text-primary"></i> SerieLog
        </a>
        <span class="navbar-text small text-muted ml-auto">
            <?= $modelId ?>
        </span>
    </div>
</nav>

<div class="container mt-4">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info" role="alert">
            <?= htmlspecialchars($_SESSION['info'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><?= $isEdit ? 'Editar Serie' : 'Agregar Nueva Serie' ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= $self ?>">
                <input type="hidden" name="action" value="<?= $actionVal ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= (int)$editData['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="titulo">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?= $val('titulo') ?>" required maxlength="150">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="temporada">Temporada <span class="text-danger">*</span></label>
                        <select class="form-control" id="temporada" name="temporada" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= $val('temporada', 1) == $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="total_episodios">Total Episodios <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="total_episodios" name="total_episodios" min="1" value="<?= $val('total_episodios', 1) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="episodio_actual">Episodio Actual</label>
                        <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0" value="<?= $val('episodio_actual', 0) ?>" required>
                    </div>
                    <div class="form-group col-md-8">
                        <label for="enlace">Enlace</label>
                        <input type="url" class="form-control" id="enlace" name="enlace" value="<?= $val('enlace') ?>" placeholder="https://..." maxlength="500">
                    </div>
                </div>

                <div class="form-group">
                    <label for="comentarios">Comentarios</label>
                    <textarea class="form-control" id="comentarios" name="comentarios" rows="3" maxlength="65535"><?= $val('comentarios') ?></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <div><?= $cancelBtn ?></div>
                    <button type="submit" class="btn btn-primary"><?= $btnText ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Mis Series</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>Título</th>
                            <th>Temp.</th>
                            <th>Progreso</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Enlace</th>
                            <th>Comentarios</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($series)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No hay series registradas aún.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($series as $row): ?>
                                <tr>
                                    <td class="font-weight-bold"><?= htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int)$row['temporada'] ?></td>
                                    <td style="min-width: 150px;">
                                        <div class="text-muted small mb-1">
                                            <?= (int)$row['episodio_actual'] ?>/<?= (int)$row['total_episodios'] ?> eps
                                        </div>
                                        <?php 
                                            $total = (int)$row['total_episodios'];
                                            $actual = (int)$row['episodio_actual'];
                                            $percentage = $total > 0 ? round(($actual / $total) * 100) : 0;
                                        ?>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percentage ?>%;" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['fecha_inicio'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if (empty($row['fecha_fin'])): ?>
                                            <span class="badge badge-info">En curso</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($row['fecha_fin'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['enlace'])): ?>
                                            <?php $domain = parse_url($row['enlace'], PHP_URL_HOST); ?>
                                            <a href="<?= htmlspecialchars($row['enlace'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars($domain ?: 'Enlace', ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small" style="max-width: 200px;">
                                        <?= nl2br(htmlspecialchars($row['comentarios'], ENT_QUOTES, 'UTF-8')) ?: '-' ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-1">
                                            <a href="<?= $self ?>?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning mb-1 mb-sm-0 mr-sm-1" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if (empty($row['fecha_fin'])): ?>
                                                <form method="POST" action="<?= $self ?>" style="display:inline;" onsubmit="return confirm('¿Marcar esta serie como terminada?');">
                                                    <input type="hidden" name="action" value="finish">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success mb-1 mb-sm-0 mr-sm-1" title="Marcar como terminada">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge badge-secondary mb-1 mb-sm-0 mr-sm-1" title="Terminada">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                            <?php endif; ?>

                                            <form method="POST" action="<?= $self ?>" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta serie? Esta acción no se puede deshacer.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
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

<footer class="footer fixed-bottom bg-light border-top">
    <div class="container text-center">
        <small class="text-muted d-block">
            SerieLog - Control de series | Licencia MIT
        </small>
        <small class="text-muted d-block">
            <?= $modelId ?>
        </small>
    </div>
</footer>

</body>
</html>
