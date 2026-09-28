<?php
// materias.php - Gestión de materias (sin cambios mayores, solo añadir modalidad en grados)
require_once 'config.php';
requireAuth();

$db = getDBConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$message = '';
$error = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $data = [
            'codigo' => sanitize($_POST['codigo']),
            'nombre' => sanitize($_POST['nombre']),
            'area' => sanitize($_POST['area']),
            'descripcion' => sanitize($_POST['descripcion']),
            'creditos' => $_POST['creditos'] ?? 1
        ];
        try {
            if ($_POST['action'] === 'create') {
                $checkStmt = $db->prepare("SELECT id FROM materias WHERE codigo = ?");
                $checkStmt->execute([$data['codigo']]);
                if ($checkStmt->fetch()) {
                    $error = "El código de materia ya está registrado";
                } else {
                    $stmt = $db->prepare("INSERT INTO materias (codigo, nombre, area, descripcion, creditos) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute(array_values($data));
                    $message = "Materia registrada exitosamente";
                    registrarLog('crear_materia', 'materias', $db->lastInsertId());
                    $action = 'list';
                }
            } elseif ($_POST['action'] === 'update') {
                $checkStmt = $db->prepare("SELECT id FROM materias WHERE codigo = ? AND id != ?");
                $checkStmt->execute([$data['codigo'], $_POST['materia_id']]);
                if ($checkStmt->fetch()) {
                    $error = "El código de materia ya está registrado en otra materia";
                } else {
                    $stmt = $db->prepare("UPDATE materias SET codigo = ?, nombre = ?, area = ?, descripcion = ?, creditos = ? WHERE id = ?");
                    $data[] = $_POST['materia_id'];
                    $stmt->execute(array_values($data));
                    $message = "Materia actualizada exitosamente";
                    registrarLog('editar_materia', 'materias', $_POST['materia_id']);
                    $action = 'list';
                }
            }
        } catch (PDOException $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

if (($action === 'edit' || $action === 'view') && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM materias WHERE id = ?");
    $stmt->execute([$id]);
    $materia = $stmt->fetch();
    if (!$materia) { $error = "Materia no encontrada"; $action = 'list'; }
}

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

$areas = ['Matemáticas', 'Lengua', 'Ciencias', 'Sociales', 'Idiomas', 'Arte', 'Educación Física', 'Tecnología'];
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Gestión de Materias</title><link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('materias'); ?>
    <main class="main-content">
        <header class="main-header"><h1>Gestión de Materias</h1>
            <div class="header-actions">
                <form method="GET" class="search-form"><div class="search-box"><input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar materia..."><button type="submit"><i class="fas fa-search"></i></button></div></form>
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
                            <thead><tr><th>Código</th><th>Nombre</th><th>Área</th><th>Créditos</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody>
                            <?php foreach ($materias as $mat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mat['codigo']); ?></strong></td>
                                <td><?php echo htmlspecialchars($mat['nombre']); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($mat['area']); ?></span></td>
                                <td><?php echo htmlspecialchars($mat['creditos']); ?></td>
                                <td><span class="badge badge-<?php echo $mat['activo'] ? 'success' : 'secondary'; ?>"><?php echo $mat['activo'] ? 'Activa' : 'Inactiva'; ?></span></td>
                                <td class="actions">
                                    <a href="?action=view&id=<?php echo $mat['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                    <a href="?action=edit&id=<?php echo $mat['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <?php if ($_SESSION['usuario_rol'] === 'admin'): ?>
                                    <a href="?action=toggle&id=<?php echo $mat['id']; ?>" class="btn btn-sm btn-<?php echo $mat['activo'] ? 'warning' : 'success'; ?>" onclick="return confirm('¿Está seguro?')"><i class="fas fa-<?php echo $mat['activo'] ? 'times' : 'check'; ?>"></i></a>
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
                <div class="card-header"><h2><?php echo $action === 'new' ? 'Nueva Materia' : ($action === 'edit' ? 'Editar Materia' : 'Ver Materia'); ?></h2><a href="materias.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a></div>
                <div class="card-body">
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="<?php echo $action === 'new' ? 'create' : 'update'; ?>">
                        <?php if ($action === 'edit' || $action === 'view'): ?><input type="hidden" name="materia_id" value="<?php echo $materia['id']; ?>"><?php endif; ?>
                        <div class="form-group"><label>Código</label><input type="text" name="codigo" value="<?php echo htmlspecialchars($materia['codigo'] ?? ''); ?>" required pattern="[A-Z]{3}-[0-9]{3}" <?php echo $action === 'view' ? 'readonly' : ''; ?>></div>
                        <div class="form-group"><label>Nombre</label><input type="text" name="nombre" value="<?php echo htmlspecialchars($materia['nombre'] ?? ''); ?>" required <?php echo $action === 'view' ? 'readonly' : ''; ?>></div>
                        <div class="form-group"><label>Área</label><select name="area" required <?php echo $action === 'view' ? 'disabled' : ''; ?>><?php foreach($areas as $a): ?><option value="<?php echo $a; ?>" <?php echo ($materia['area']??'')==$a?'selected':''; ?>><?php echo $a; ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Créditos</label><input type="number" name="creditos" value="<?php echo htmlspecialchars($materia['creditos'] ?? 1); ?>" min="1" max="5" <?php echo $action === 'view' ? 'readonly' : ''; ?>></div>
                        <div class="form-group full-width"><label>Descripción</label><textarea name="descripcion" rows="4" <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo htmlspecialchars($materia['descripcion'] ?? ''); ?></textarea></div>
                        <?php if ($action !== 'view'): ?>
                        <div class="form-actions full-width"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'new' ? 'Registrar' : 'Actualizar'; ?></button><a href="materias.php" class="btn btn-secondary">Cancelar</a></div>
                        <?php else: ?>
                        <div class="form-actions full-width"><a href="?action=edit&id=<?php echo $materia['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Editar</a><a href="materias.php" class="btn btn-secondary">Volver</a></div>
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