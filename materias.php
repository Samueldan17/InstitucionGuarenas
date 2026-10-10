<?php
// materias.php - Gestión de materias (VERSIÓN 3.3)
require_once 'config.php';
requireAuth();

$db = getDBConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// =========================================================
// ÁREAS Y SUS ABREVIATURAS PARA EL CÓDIGO AUTOMÁTICO
// =========================================================
$areas = [
    'Ciencias Exactas' => 'MAT',
    'Ciencias Naturales' => 'CIE',
    'Lengua y Literatura' => 'LEN',
    'Ciencias Sociales' => 'SOC',
    'Idiomas' => 'ING',
    'Educación Física' => 'EDF',
    'Arte y Patrimonio' => 'ART',
    'Tecnología e Informática' => 'TEC',
    'Formación para el Trabajo' => 'FPT'
];

// =========================================================
// FUNCIÓN PARA GENERAR CÓDIGO AUTOMÁTICO
// Formato: MAT-2026-001
// =========================================================
function generarCodigoMateria($db, $area) {
    global $areas;
    $prefijo = $areas[$area] ?? 'GEN';
    $anio = date('Y');
    
    // Buscar el último código con ese prefijo y año
    $stmt = $db->prepare("SELECT codigo FROM materias WHERE codigo LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefijo . '-' . $anio . '-%']);
    $ultimo = $stmt->fetch();
    
    if ($ultimo) {
        // Extraer el número secuencial y sumarle 1
        $partes = explode('-', $ultimo['codigo']);
        $secuencial = intval(end($partes)) + 1;
    } else {
        $secuencial = 1;
    }
    
    return $prefijo . '-' . $anio . '-' . str_pad($secuencial, 3, '0', STR_PAD_LEFT);
}

// =========================================================
// TOGGLE (ACTIVAR/DESACTIVAR)
// =========================================================
if ($action === 'toggle' && $id > 0 && $_SESSION['usuario_rol'] === 'admin') {
    try {
        $stmt = $db->prepare("SELECT activo FROM materias WHERE id = ?");
        $stmt->execute([$id]);
        $materia = $stmt->fetch();
        if ($materia) {
            $nuevoEstado = $materia['activo'] ? 0 : 1;
            $updateStmt = $db->prepare("UPDATE materias SET activo = ? WHERE id = ?");
            $updateStmt->execute([$nuevoEstado, $id]);
            $message = "Materia " . ($nuevoEstado ? "activada" : "desactivada") . " correctamente";
            registrarLog('toggle_materia', 'materias', $id, "Nuevo estado: $nuevoEstado");
        }
    } catch (PDOException $e) {
        $error = "Error al cambiar estado: " . $e->getMessage();
    }
    header("Location: materias.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// =========================================================
// CREAR Y ACTUALIZAR
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $data = [
            'nombre' => sanitize($_POST['nombre']),
            'area' => sanitize($_POST['area']),
            'descripcion' => sanitize($_POST['descripcion']),
            'horas_semanales' => intval($_POST['horas_semanales'] ?? 1)
        ];
        
        try {
            if ($_POST['action'] === 'create') {
                // Generar código automáticamente
                $data['codigo'] = generarCodigoMateria($db, $data['area']);
                
                $stmt = $db->prepare("INSERT INTO materias (codigo, nombre, area, descripcion, horas_semanales) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data['codigo'],
                    $data['nombre'],
                    $data['area'],
                    $data['descripcion'],
                    $data['horas_semanales']
                ]);
                $message = "Materia registrada exitosamente con código " . $data['codigo'];
                registrarLog('crear_materia', 'materias', $db->lastInsertId());
                $action = 'list';
            } elseif ($_POST['action'] === 'update') {
                $materia_id = $_POST['materia_id'];
                $stmt = $db->prepare("UPDATE materias SET nombre = ?, area = ?, descripcion = ?, horas_semanales = ? WHERE id = ?");
                $stmt->execute([
                    $data['nombre'],
                    $data['area'],
                    $data['descripcion'],
                    $data['horas_semanales'],
                    $materia_id
                ]);
                $message = "Materia actualizada exitosamente";
                registrarLog('editar_materia', 'materias', $materia_id);
                $action = 'list';
            }
        } catch (PDOException $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

// =========================================================
// CARGAR MATERIA PARA EDITAR/VER
// =========================================================
if (($action === 'edit' || $action === 'view') && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM materias WHERE id = ?");
    $stmt->execute([$id]);
    $materia = $stmt->fetch();
    if (!$materia) { $error = "Materia no encontrada"; $action = 'list'; }
}

// =========================================================
// LISTADO CON PAGINACIÓN Y BÚSQUEDA
// =========================================================
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$search = $_GET['search'] ?? '';
$where = [];
$params = [];
if ($search) {
    $where[] = "(nombre LIKE ? OR codigo LIKE ? OR area LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$countStmt = $db->prepare("SELECT COUNT(*) FROM materias $whereClause");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

if ($action === 'list') {
    $query = "SELECT * FROM materias $whereClause ORDER BY area, nombre LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $materias = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Materias - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('materias'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-book"></i> Gestión de Materias</h1>
            <div class="header-actions">
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar materia...">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                <a href="?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Nueva Materia</a>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <?php if ($action === 'list'): ?>
            <div class="card">
                <div class="card-header"><h2>Listado de Materias</h2></div>
                <div class="card-body">
                    <?php if (empty($materias)): ?>
                    <div class="empty-state"><i class="fas fa-book"></i><h3>No se encontraron materias</h3></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Área</th>
                                    <th>Horas Semanales</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($materias as $mat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mat['codigo']); ?></strong></td>
                                <td><?php echo htmlspecialchars($mat['nombre']); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($mat['area']); ?></span></td>
                                <td><?php echo htmlspecialchars($mat['horas_semanales']); ?> hrs</td>
                                <td>
                                    <span class="badge badge-<?php echo $mat['activo'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $mat['activo'] ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="?action=view&id=<?php echo $mat['id']; ?>" class="btn btn-sm btn-info" title="Ver"><i class="fas fa-eye"></i></a>
                                    <a href="?action=edit&id=<?php echo $mat['id']; ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                    <?php if ($_SESSION['usuario_rol'] === 'admin'): ?>
                                    <a href="?action=toggle&id=<?php echo $mat['id']; ?>" class="btn btn-sm btn-<?php echo $mat['activo'] ? 'warning' : 'success'; ?>" onclick="return confirm('¿Está seguro?')" title="Cambiar estado">
                                        <i class="fas fa-<?php echo $mat['activo'] ? 'times' : 'check'; ?>"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="table-footer">
                        <div class="table-info">Mostrando <?php echo count($materias); ?> de <?php echo $totalItems; ?></div>
                        <div class="pagination">
                            <?php if ($page > 1): ?><a class="btn btn-secondary" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>">&laquo; Anterior</a><?php endif; ?>
                            <span>Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?><a class="btn btn-secondary" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>">Siguiente &raquo;</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h2>
                        <?php 
                        if ($action === 'new') echo 'Nueva Materia';
                        elseif ($action === 'edit') echo 'Editar Materia';
                        else echo 'Ver Materia';
                        ?>
                    </h2>
                    <a href="materias.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
                </div>
                <div class="card-body">
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="<?php echo $action === 'new' ? 'create' : 'update'; ?>">
                        <?php if ($action === 'edit' || $action === 'view'): ?>
                            <input type="hidden" name="materia_id" value="<?php echo $materia['id']; ?>">
                        <?php endif; ?>
                        
                        <?php if ($action === 'new'): ?>
                            <div class="form-group full-width">
                                <label>Código</label>
                                <input type="text" value="Se generará automáticamente" readonly class="form-control" style="background:#f0f0f0;">
                                <small class="form-text">El código se creará automáticamente al seleccionar el área (Ej: MAT-2026-001)</small>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label>Código</label>
                                <input type="text" name="codigo" value="<?php echo htmlspecialchars($materia['codigo'] ?? ''); ?>" readonly class="form-control" style="background:#f0f0f0;">
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label>Nombre de la Materia</label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($materia['nombre'] ?? ''); ?>" required <?php echo $action === 'view' ? 'readonly' : ''; ?> class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Área</label>
                            <select name="area" required class="form-control" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="">Seleccione un área...</option>
                                <?php foreach($areas as $a => $abrev): ?>
                                    <option value="<?php echo $a; ?>" <?php echo ($materia['area'] ?? '') == $a ? 'selected' : ''; ?>>
                                        <?php echo $a; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Horas Semanales</label>
                            <select name="horas_semanales" class="form-control" required <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <?php for ($h = 1; $h <= 10; $h++): ?>
                                    <option value="<?php echo $h; ?>" <?php echo ($materia['horas_semanales'] ?? 1) == $h ? 'selected' : ''; ?>>
                                        <?php echo $h; ?> hora<?php echo $h > 1 ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <small class="form-text">Horas de clase por semana</small>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Descripción</label>
                            <textarea name="descripcion" rows="4" class="form-control" <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo htmlspecialchars($materia['descripcion'] ?? ''); ?></textarea>
                        </div>
                        
                        <?php if ($action !== 'view'): ?>
                            <div class="form-actions full-width">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $action === 'new' ? 'Registrar' : 'Actualizar'; ?>
                                </button>
                                <a href="materias.php" class="btn btn-secondary">Cancelar</a>
                            </div>
                        <?php else: ?>
                            <div class="form-actions full-width">
                                <a href="?action=edit&id=<?php echo $materia['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Editar</a>
                                <a href="materias.php" class="btn btn-secondary">Volver</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>