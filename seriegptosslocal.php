<?php
/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
openai/gpt-oss-20b

*/
require_once 'config.php';

/* ---------- Crear tabla si no existe ---------- */
$createTable = "
CREATE TABLE IF NOT EXISTS `series` (
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
$link->query($createTable);

/* ---------- Variables de ayuda ---------- */
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Función para redirigir con mensaje
function redirect_with_message(string $msg, string $type = 'success'): void {
    header("Location: " . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . "?msg=" . urlencode($msg) . "&type=$type");
    exit;
}

// Obtener mensaje de GET si existe
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $msg_type = $_GET['type'] ?? 'success';
}

/* ---------- Procesar POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Añadir serie
    if ($action === 'add') {
        $titulo = trim($_POST['titulo'] ?? '');
        $temporada = (int)($_POST['temporada'] ?? 1);
        $total_episodios = (int)($_POST['total_episodios'] ?? 0);
        $episodio_actual = (int)($_POST['episodio_actual'] ?? 0);
        $enlace = trim($_POST['enlace'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        // Validaciones
        if ($titulo === '') {
            redirect_with_message('El título es obligatorio.', 'danger');
        }
        if ($temporada < 1 || $temporada > 5) {
            redirect_with_message('La temporada debe estar entre 1 y 5.', 'danger');
        }
        if ($total_episodios < 1) {
            redirect_with_message('El total de episodios debe ser al menos 1.', 'danger');
        }
        if ($episodio_actual > $total_episodios || $episodio_actual < 0) {
            redirect_with_message('Episodio actual fuera de rango.', 'danger');
        }
        if ($enlace !== '' && !preg_match('#^https?://#i', $enlace)) {
            redirect_with_message('El enlace debe comenzar con http:// o https://.', 'danger');
        }

        // Insertar
        $stmt = $link->prepare("
            INSERT INTO series (titulo, temporada, total_episodios, episodio_actual, fecha_inicio, enlace, comentarios)
            VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
        $fecha_inicio = date('Y-m-d'); // no usado en query, pero para claridad
        $stmt->bind_param(
            'siiiss',
            $titulo,
            $temporada,
            $total_episodios,
            $episodio_actual,
            $enlace,
            $comentarios
        );
        if ($stmt->execute()) {
            redirect_with_message('Serie añadida correctamente.');
        } else {
            redirect_with_message('Error al añadir la serie.', 'danger');
        }
    }

    // Editar serie
    if ($action === 'edit' && isset($_POST['id'])) {
        $id_edit = (int)$_POST['id'];
        $titulo = trim($_POST['titulo'] ?? '');
        $temporada = (int)($_POST['temporada'] ?? 1);
        $total_episodios = (int)($_POST['total_episodios'] ?? 0);
        $episodio_actual = (int)($_POST['episodio_actual'] ?? 0);
        $enlace = trim($_POST['enlace'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        // Validaciones
        if ($titulo === '') {
            redirect_with_message('El título es obligatorio.', 'danger');
        }
        if ($temporada < 1 || $temporada > 5) {
            redirect_with_message('La temporada debe estar entre 1 y 5.', 'danger');
        }
        if ($total_episodios < 1) {
            redirect_with_message('El total de episodios debe ser al menos 1.', 'danger');
        }
        if ($episodio_actual > $total_episodios || $episodio_actual < 0) {
            redirect_with_message('Episodio actual fuera de rango.', 'danger');
        }
        if ($enlace !== '' && !preg_match('#^https?://#i', $enlace)) {
            redirect_with_message('El enlace debe comenzar con http:// o https://.', 'danger');
        }

        // Update
        $stmt = $link->prepare("
            UPDATE series SET titulo=?, temporada=?, total_episodios=?, episodio_actual=?, enlace=?, comentarios=?
            WHERE id=?");
        $stmt->bind_param(
            'siiissi',
            $titulo,
            $temporada,
            $total_episodios,
            $episodio_actual,
            $enlace,
            $comentarios,
            $id_edit
        );
        if ($stmt->execute()) {
            redirect_with_message('Serie actualizada correctamente.');
        } else {
            redirect_with_message('Error al actualizar la serie.', 'danger');
        }
    }

    // Terminar serie
    if ($action === 'finish' && isset($_POST['id'])) {
        $id_finish = (int)$_POST['id'];
        // Solo si fecha_fin es NULL
        $stmt = $link->prepare("SELECT fecha_fin FROM series WHERE id=?");
        $stmt->bind_param('i', $id_finish);
        $stmt->execute();
        $stmt->bind_result($fecha_fin);
        $stmt->fetch();
        $stmt->close();

        if ($fecha_fin === null) {
            $now = date('Y-m-d');
            $stmt2 = $link->prepare("UPDATE series SET fecha_fin=? WHERE id=?");
            $stmt2->bind_param('si', $now, $id_finish);
            $stmt2->execute();
            redirect_with_message('Serie marcada como terminada.');
        } else {
            redirect_with_message('La serie ya está terminada.', 'warning');
        }
    }

    // Eliminar serie
    if ($action === 'delete' && isset($_POST['id'])) {
        $id_del = (int)$_POST['id'];
        $stmt = $link->prepare("DELETE FROM series WHERE id=?");
        $stmt->bind_param('i', $id_del);
        if ($stmt->execute()) {
            redirect_with_message('Serie eliminada correctamente.');
        } else {
            redirect_with_message('Error al eliminar la serie.', 'danger');
        }
    }
}

/* ---------- Obtener todas las series ---------- */
$series = [];
$result = $link->query("SELECT * FROM series ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $series[] = $row;
}
$result->free();

/* ---------- Función para mostrar dominio del enlace ---------- */
function get_domain(string $url): string {
    $host = parse_url($url, PHP_URL_HOST);
    return $host ?: $url;
}

/* ---------- HTML ---------- */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>SerieLog</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
<style>
body{background:#EBEBEB;padding-top:70px;padding-bottom:70px;}
footer{position:fixed;bottom:0;width:100%;height:60px;background:#f8f9fa;text-align:center;line-height:60px;}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
  <a class="navbar-brand" href="#"><i class="fas fa-film"></i> SerieLog</a>
  <div class="ml-auto text-muted">Generado por ModeloX versión 1.0 el no verificable</div>
</nav>

<div class="container">

<?php if ($message): ?>
<div class="alert alert-<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
</div>
<?php endif; ?>

<h2>Agregar Serie</h2>
<form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="action" value="add">
  <div class="form-row">
    <div class="col-md-4 mb-3"><label>Título</label><input type="text" name="titulo" class="form-control" required></div>
    <div class="col-md-2 mb-3"><label>Temporada</label><select name="temporada" class="form-control">
      <?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
    </select></div>
    <div class="col-md-2 mb-3"><label>Total Episodios</label><input type="number" name="total_episodios" min="1" class="form-control" required></div>
    <div class="col-md-2 mb-3"><label>Episodio Actual</label><input type="number" name="episodio_actual" min="0" value="0" class="form-control"></div>
  </div>
  <div class="form-row">
    <div class="col-md-6 mb-3"><label>Enlace (opcional)</label><input type="url" name="enlace" class="form-control"></div>
    <div class="col-md-6 mb-3"><label>Comentarios (opcional)</label><textarea name="comentarios" class="form-control"></textarea></div>
  </div>
  <button type="submit" class="btn btn-primary">Añadir</button>
</form>

<hr>

<h2>Series Registradas</h2>
<div class="table-responsive">
<table class="table table-bordered table-hover">
<thead class="thead-light"><tr>
<th>Título</th><th>Temporada</th><th>Progreso</th><th>Inicio</th><th>Fin</th><th>Enlace</th><th>Comentarios</th><th>Acciones</th></tr></thead>
<tbody>
<?php foreach ($series as $s): ?>
<tr>
<td><?= htmlspecialchars($s['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= (int)$s['temporada'] ?></td>
<td>
  <?= (int)$s['episodio_actual'] ?>/<?= (int)$s['total_episodios'] ?><br>
  <?php
    $percent = ($s['total_episodios'] > 0) ? round(($s['episodio_actual'] / $s['total_episodios']) * 100, 2) : 0;
  ?>
  <div class="progress" style="height:10px;">
    <div class="progress-bar" role="progressbar" style="width:<?= $percent ?>%" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
  </div>
</td>
<td><?= htmlspecialchars($s['fecha_inicio'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= $s['fecha_fin'] ? htmlspecialchars($s['fecha_fin'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">En curso</span>' ?></td>
<td>
  <?php if ($s['enlace']): ?>
    <a href="<?= htmlspecialchars($s['enlace'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-external-link-alt"></i> <?= get_domain($s['enlace']) ?>
    </a>
  <?php else: ?>N/A<?php endif; ?>
</td>
<td><?= htmlspecialchars($s['comentarios'], ENT_QUOTES, 'UTF-8') ?></td>
<td>
  <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id" value="<?= $s['id'] ?>">
    <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editModal<?= $s['id'] ?>">Editar</button>
  </form>

  <?php if (!$s['fecha_fin']): ?>
  <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
    <input type="hidden" name="action" value="finish">
    <input type="hidden" name="id" value="<?= $s['id'] ?>">
    <button class="btn btn-sm btn-outline-success">Terminada</button>
  </form>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar esta serie?');">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= $s['id'] ?>">
    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
  </form>
</td>
</tr>

<!-- Modal Editar -->
<div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="editLabel<?= $s['id'] ?>" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editLabel<?= $s['id'] ?>">Editar Serie</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?= $s['id'] ?>">
      <div class="modal-body">
        <div class="form-group"><label>Título</label><input type="text" name="titulo" class="form-control" required value="<?= htmlspecialchars($s['titulo'], ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="form-group"><label>Temporada</label><select name="temporada" class="form-control">
          <?php for ($i=1;$i<=5;$i++): ?>
            <option value="<?= $i ?>" <?= $s['temporada']==$i?'selected':'' ?>><?= $i ?></option>
          <?php endfor; ?>
        </select></div>
        <div class="form-group"><label>Total Episodios</label><input type="number" name="total_episodios" min="1" class="form-control" required value="<?= (int)$s['total_episodios'] ?>"></div>
        <div class="form-group"><label>Episodio Actual</label><input type="number" name="episodio_actual" min="0" class="form-control" value="<?= (int)$s['episodio_actual'] ?>"></div>
        <div class="form-group"><label>Enlace (opcional)</label><input type="url" name="enlace" class="form-control" value="<?= htmlspecialchars($s['enlace'], ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="form-group"><label>Comentarios (opcional)</label><textarea name="comentarios" class="form-control"><?= htmlspecialchars($s['comentarios'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
    </form>
  </div></div>
</div>

<?php endforeach; ?>
</tbody>
</table>
</div>

</div> <!-- container -->

<footer>
Generado por ModeloX versión 1.0 el no verificable – Licencia MIT
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
