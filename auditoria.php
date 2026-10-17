<?php
// auditoria.php - Historial de acciones del sistema
require_once 'config.php';
requireAuth();

// Solo el admin puede ver la auditoría
if ($_SESSION['usuario_rol'] !== 'admin') { 
    header('Location: dashboard.php'); 
    exit; 
}

$db = getDBConnection();

// Filtros
$filtro_usuario = (int)($_GET['usuario_id'] ?? 0);
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

$where = ["DATE(l.fecha) >= ?", "DATE(l.fecha) <= ?"];
$params = [$fecha_inicio, $fecha_fin];

if ($filtro_usuario > 0) {
    $where[] = "l.usuario_id = ?";
    $params[] = $filtro_usuario;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Paginación
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countStmt = $db->prepare("SELECT COUNT(*) FROM logs l $whereClause");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Obtener registros
$stmt = $db->prepare("
    SELECT l.*, CONCAT(u.nombres, ' ', u.apellidos) as usuario_nombre, u.rol 
    FROM logs l 
    LEFT JOIN usuarios u ON l.usuario_id = u.id 
    $whereClause 
    ORDER BY l.fecha DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Usuarios para el filtro
$usuarios = $db->query("SELECT id, nombres, apellidos FROM usuarios ORDER BY nombres")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditoría - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .log-action { font-family: monospace; background: #e1e4e8; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
        .badge-rol { font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
        .rol-admin { background: #f8d7da; color: #721c24; }
        .rol-docente { background: #fff3cd; color: #856404; }
        .rol-secretario { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('auditoria'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-history"></i> Auditoría del Sistema</h1>
        </header>
        <div class="content-container">
            
            <div class="card no-print" style="margin-bottom: 20px;">
                <div class="card-header"><h2>Filtros de Búsqueda</h2></div>
                <div class="card-body">
                    <form method="GET" class="form-grid">
                        <div class="form-group">
                            <label>Usuario</label>
                            <select name="usuario_id" class="form-control">
                                <option value="">Todos los usuarios</option>
                                <?php foreach($usuarios as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo $filtro_usuario == $u['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['nombres'] . ' ' . $u['apellidos']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Desde</label>
                            <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $fecha_inicio; ?>">
                        </div>
                        <div class="form-group">
                            <label>Hasta</label>
                            <input type="date" name="fecha_fin" class="form-control" value="<?php echo $fecha_fin; ?>">
                        </div>
                        <div class="form-actions" style="align-items: flex-end; display: flex;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Historial de Actividad (<?php echo $totalItems; ?> registros)</h2>
                </div>
                <div class="card-body">
                    <?php if (count($logs) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Fecha y Hora</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Acción</th>
                                    <th>Detalles</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($log['usuario_nombre'] ?: 'Sistema / Desconocido'); ?></strong></td>
                                    <td>
                                        <?php if($log['rol']): ?>
                                            <span class="badge-rol rol-<?php echo $log['rol']; ?>"><?php echo ucfirst($log['rol']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="log-action"><?php echo htmlspecialchars($log['accion']); ?></span></td>
                                    <td><?php echo htmlspecialchars($log['detalles'] ?: '—'); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($log['ip']); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="table-footer">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-secondary" href="?page=<?php echo $page-1; ?>&usuario_id=<?php echo $filtro_usuario; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>">&laquo; Anterior</a>
                            <?php endif; ?>
                            <span>Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-secondary" href="?page=<?php echo $page+1; ?>&usuario_id=<?php echo $filtro_usuario; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>">Siguiente &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search fa-4x"></i>
                        <h3>No hay actividad en este rango de fechas</h3>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>