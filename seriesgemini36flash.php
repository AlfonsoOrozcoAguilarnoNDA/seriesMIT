/*
License Mit
Alfonso Orozco Aguilar
Experimento de revisar estado de streaming de series, y el control por LLM
https://vibecodingmexico.com/pruebalo-ya-visor-de-series/
Gemini 3.6 Flash
*/
<?php 
die("Es basura, no usar");
if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo htmlspecialchars($tipo_mensaje, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show mb-4" role="alert">
        <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Formulario (Agregar / Editar) -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas <?php echo $edit_data ? 'fa-edit' : 'fa-plus-circle'; ?> mr-2"></i>
            <?php echo $edit_data ? 'Editar Serie' : 'Agregar Serie'; ?>
        </h5>
        <?php if ($edit_data): ?>
            <a href="<?php echo $self_url; ?>" class="btn btn-sm btn-light">
                <i class="fas fa-times mr-1"></i> Cancelar edición
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form action="<?php echo $self_url; ?>" method="POST">
            <input type="hidden" name="action" value="guardar">
            <?php if ($edit_data): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_data['id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="titulo">Título <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="150"
                           value="<?php echo htmlspecialchars($edit_data['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="temporada">Temporada <span class="text-danger">*</span></label>
                    <select class="form-control" id="temporada" name="temporada" required>
                        <?php
                        $temp_actual = (int)($edit_data['temporada'] ?? 1);
                        for ($i = 1; $i <= 5; $i++):
                        ?>
                            <option value="<?php echo $i; ?>" <?php echo $temp_actual ===$i ? 'selected' : ''; ?>>
                                Temporada <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="total_episodios">Total Episodios <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="total_episodios" name="total_episodios" min="1" max="65535" required
                           value="<?php echo htmlspecialchars($edit_data['total_episodios'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="episodio_actual">Episodio Actual</label>
                    <input type="number" class="form-control" id="episodio_actual" name="episodio_actual" min="0" max="65535"
                           value="<?php echo htmlspecialchars($edit_data['episodio_actual'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="enlace">Enlace (http:// o https://)</label>
                    <input type="url" class="form-control" id="enlace" name="enlace" maxlength="500" placeholder="https://..."
                           value="<?php echo htmlspecialchars($edit_data['enlace'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="comentarios">Comentarios</label>
                    <input type="text" class="form-control" id="comentarios" name="comentarios"
                           value="<?php echo htmlspecialchars($edit_data['comentarios'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i> <?php echo $edit_data ? 'Guardar Cambios' : 'Registrar Serie'; ?>
            </button>
        </form>
    </div>
</div>

<!-- Tabla de Series -->
<div class="card shadow-sm">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-list mr-2"></i>Mis Series</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 table-middle">
                <thead class="thead-light">
                    <tr>
                        <th>Título</th>
                        <th class="text-center">Temp.</th>
                        <th style="min-width: 180px;">Progreso</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Enlace</th>
                        <th>Comentarios</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($series)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No hay series registradas aún.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($series as$s): ?>
                            <?php
                            $total = (int)$s['total_episodios'];
                            $actual = (int)$s['episodio_actual'];
                            $porcentaje = ($total > 0) ? min(100, round(($actual / $total) * 100)) : 0;
                            ?>
                            <tr>
                                <td class="font-weight-bold">
                                    <?php echo htmlspecialchars($s['titulo'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-secondary"><?php echo (int)$s['temporada']; ?></span>
                                </td>
                                <td>
                                    <div class="small font-weight-bold mb-1">
                                        Ep. <?php echo $actual; ?> / <?php echo $total; ?> (<?php echo$porcentaje; ?>%)
                                    </div>
                                    <div class="progress" style="height: 12px;">
                                        <div class="progress-bar <?php echo $porcentaje === 100 ? 'bg-success' : 'bg-info'; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $porcentaje; ?>%;" 
                                             aria-valuenow="<?php echo $porcentaje; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($s['fecha_inicio'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($s['fecha_fin']): ?>
                                        <span class="badge badge-success"><?php echo htmlspecialchars($s['fecha_fin'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-warning text-dark">En curso</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($s['enlace'])): ?>
                                        <?php
                                        $host = parse_url($s['enlace'], PHP_URL_HOST);$domain = $host ? $host : 'Ver enlace';
                                        ?>
                                        <a href="<?php echo htmlspecialchars($s['enlace'], ENT_QUOTES, 'UTF-8'); ?>" 
                                           target="_blank" 
                                           rel="noopener noreferrer" 
                                           class="btn btn-sm btn-outline-primary text-truncate" style="max-width: 150px;">
                                            <i class="fas fa-external-link-alt mr-1"></i><?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($s['comentarios'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                </td>
                                <td class="text-right">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if (empty($s['fecha_fin'])): ?>
                                            <form action="<?php echo $self_url; ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="marcar_terminada">
                                                <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success mr-1" title="Marcar como Terminada">
                                                    <i class="fas fa-check"></i> <span class="d-none d-lg-inline">Terminada</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="<?php echo $self_url . '?edit=' . (int)$s['id']; ?>" class="btn btn-sm btn-info mr-1" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="<?php echo $self_url; ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar esta serie?');">
                                            <input type="hidden" name="action" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
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
