<?php
// secciones.php - Gestión de Secciones (VERSIÓN 3.3)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

$db = getDBConnection();
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// =========================================================
// CREAR SECCIÓN
// =========================================================
if (($_POST['action'] ?? '') == 'crear_seccion') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        try {
            $grado_id = (int)($_POST['grado_id'] ?? 0);
            $letra = strtoupper(sanitize($_POST['letra'] ?? ''));
            $anio_escolar = sanitize($_POST['anio_escolar'] ?? '2025-2026');
            $turno = $_POST['turno'] ?? 'mañana';
            $capacidad = (int)($_POST['capacidad'] ?? 30);
            $docente_guia_id = (int)($_POST['docente_guia_id'] ?? 0);
            $aula = sanitize($_POST['aula'] ?? '');
            
            if (empty($grado_id)) throw new Exception("Debe seleccionar un grado");
            if (empty($letra) || strlen($letra) > 1) throw new Exception("La sección debe ser un solo carácter");
            
            $stmt = $db->prepare("SELECT id FROM secciones WHERE grado_id = ? AND letra = ? AND turno = ? AND anio_escolar = ? AND activo = 1");
            $stmt->execute([$grado_id, $letra, $turno, $anio_escolar]);
            if ($stmt->fetch()) throw new Exception("Ya existe una sección con esa letra, turno y año escolar");
            
            $stmt = $db->prepare("INSERT INTO secciones (grado_id, letra, anio_escolar, turno, capacidad, docente_guia_id, aula, activo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$grado_id, $letra, $anio_escolar, $turno, $capacidad, $docente_guia_id, $aula]);
            $message = "✅ Sección creada exitosamente";
            registrarLog('crear_seccion', 'secciones', $db->lastInsertId());
        } catch (Exception $e) { $error = "❌ " . $e->getMessage(); }
    }
    header("Location: secciones.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// =========================================================
// ELIMINAR SECCIÓN
// =========================================================
if (($_POST['action'] ?? '') == 'eliminar_seccion' && isset($_POST['id'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $seccion_id = (int)$_POST['id'];
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM estudiantes WHERE seccion_id = ? AND estado = 'activo'");
            $stmt->execute([$seccion_id]);
            if ($stmt->fetch()['total'] > 0) {
                throw new Exception("No se puede eliminar porque tiene estudiantes asignados");
            }
            $stmt = $db->prepare("UPDATE secciones SET activo = 0 WHERE id = ?");
            $stmt->execute([$seccion_id]);
            $message = "✅ Sección eliminada exitosamente";
            registrarLog('eliminar_seccion', 'secciones', $seccion_id);
        } catch (Exception $e) { $error = "❌ " . $e->getMessage(); }
    }
    header("Location: secciones.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// =========================================================
// FILTROS Y PAGINACIÓN
// =========================================================
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$filtro_grado = (int)($_GET['filtro_grado'] ?? 0);
$filtro_turno = $_GET['filtro_turno'] ?? '';
$search = $_GET['search'] ?? '';

$where = ["s.activo = 1"];
$params = [];

if ($filtro_grado > 0) {
    $where[] = "s.grado_id = ?";
    $params[] = $filtro_grado;
}
if ($filtro_turno) {
    $where[] = "s.turno = ?";
    $params[] = $filtro_turno;
}
if ($search) {
    $where[] = "(s.letra LIKE ? OR s.aula LIKE ? OR CONCAT(u.nombres, ' ', u.apellidos) LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$whereClause = "WHERE " . implode(" AND ", $where);

// Contar total de secciones
$countStmt = $db->prepare("SELECT COUNT(DISTINCT s.id) FROM secciones s LEFT JOIN usuarios u ON s.docente_guia_id = u.id $whereClause");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Obtener secciones con contador de estudiantes
$query = "SELECT s.*, g.nombre as grado_nombre, g.orden as grado_orden, 
          CONCAT(u.nombres, ' ', u.apellidos) as docente_guia_nombre,
          (SELECT COUNT(*) FROM estudiantes e WHERE e.seccion_id = s.id AND e.estado = 'activo') as total_estudiantes
          FROM secciones s 
          JOIN grados g ON s.grado_id = g.id 
          LEFT JOIN usuarios u ON s.docente_guia_id = u.id 
          $whereClause 
          ORDER BY g.orden, s.letra 
          LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$secciones = $stmt->fetchAll();

// Datos para los filtros
$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$docentes = $db->query("SELECT id, CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE rol = 'docente' AND activo = 1")->fetchAll();

// Estadísticas generales
$totalSecciones = $db->query("SELECT COUNT(*) FROM secciones WHERE activo = 1")->fetchColumn();
$totalEstudiantes = $db->query("SELECT COUNT(*) FROM estudiantes WHERE estado = 'activo'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Secciones - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('secciones'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-users"></i> Gestión de Secciones</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="abrirModalNuevaSeccion()"><i class="fas fa-plus"></i> Nueva Sección</button>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info"><h3><?php echo $totalSecciones; ?></h3><p>Secciones Activas</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:var(--warning-color);"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-info"><h3><?php echo $totalEstudiantes; ?></h3><p>Estudiantes Activos</p></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Listado de Secciones</h2>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <form method="GET" class="filters-form" style="margin-bottom: 1rem; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <select name="filtro_grado" class="form-control" style="max-width: 200px;">
                            <option value="">Todos los grados</option>
                            <?php foreach($grados as $g): ?>
                                <option value="<?php echo $g['id']; ?>" <?php echo $filtro_grado == $g['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="filtro_turno" class="form-control" style="max-width: 150px;">
                            <option value="">Todos los turnos</option>
                            <option value="mañana" <?php echo $filtro_turno == 'mañana' ? 'selected' : ''; ?>>Mañana</option>
                            <option value="tarde" <?php echo $filtro_turno == 'tarde' ? 'selected' : ''; ?>>Tarde</option>
                        </select>
                        
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por sección, aula o docente..." class="form-control" style="max-width: 300px;">
                        
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                        <a href="secciones.php" class="btn btn-secondary"><i class="fas fa-times"></i> Limpiar</a>
                    </form>
                    
                    <?php if (count($secciones) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Grado</th>
                                    <th>Sección</th>
                                    <th>Año Escolar</th>
                                    <th>Turno</th>
                                    <th>Estudiantes</th>
                                    <th>Aula</th>
                                    <th>Docente Guía</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($secciones as $sec): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sec['grado_nombre']); ?></strong></td>
                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($sec['letra']); ?></span></td>
                                <td><?php echo htmlspecialchars($sec['anio_escolar'] ?? 'N/A'); ?></td>
                                <td><span class="badge <?php echo $sec['turno']=='mañana'?'badge-info':'badge-warning'; ?>"><?php echo ucfirst($sec['turno']); ?></span></td>
                                <td><strong><?php echo $sec['total_estudiantes']; ?></strong> / <?php echo $sec['capacidad']; ?></td>
                                <td><?php echo htmlspecialchars($sec['aula'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($sec['docente_guia_nombre'] ?: 'Sin asignar'); ?></td>
                                <td class="actions">
                                    <a href="editar_seccion.php?id=<?php echo $sec['id']; ?>" class="btn btn-sm btn-info" title="Editar"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar la sección <?php echo htmlspecialchars($sec['grado_nombre'].' '.$sec['letra']); ?>?')">
                                        <input type="hidden" name="action" value="eliminar_seccion">
                                        <input type="hidden" name="id" value="<?php echo $sec['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="table-footer">
                        <div class="table-info">Mostrando <?php echo count($secciones); ?> de <?php echo $totalItems; ?> secciones</div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-secondary" href="?page=<?php echo $page-1; ?>&filtro_grado=<?php echo $filtro_grado; ?>&filtro_turno=<?php echo urlencode($filtro_turno); ?>&search=<?php echo urlencode($search); ?>">&laquo; Anterior</a>
                            <?php endif; ?>
                            <span>Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-secondary" href="?page=<?php echo $page+1; ?>&filtro_grado=<?php echo $filtro_grado; ?>&filtro_turno=<?php echo urlencode($filtro_turno); ?>&search=<?php echo urlencode($search); ?>">Siguiente &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users fa-4x"></i>
                        <h3>No se encontraron secciones</h3>
                        <p>Intenta cambiar los filtros o crea una nueva sección.</p>
                        <button class="btn btn-primary" onclick="abrirModalNuevaSeccion()">Agregar Sección</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Nueva Sección -->
<div id="modalNuevaSeccion" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-close" onclick="cerrarModalNuevaSeccion()">&times;</div>
        <div class="card">
            <div class="card-header"><h2>Crear Nueva Sección</h2></div>
            <div class="card-body">
                <form method="POST" action="secciones.php">
                    <input type="hidden" name="action" value="crear_seccion">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Grado</label>
                            <select name="grado_id" class="form-control" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($grados as $g): ?>
                                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sección</label>
                            <input type="text" name="letra" class="form-control" maxlength="1" pattern="[A-Z]" title="Una letra mayúscula" required placeholder="A">
                        </div>
                        <div class="form-group">
                            <label>Año Escolar</label>
                            <input type="text" name="anio_escolar" class="form-control" value="2025-2026" placeholder="2025-2026" required>
                        </div>
                        <div class="form-group">
                            <label>Turno</label>
                            <select name="turno" class="form-control">
                                <option value="mañana">Mañana</option>
                                <option value="tarde">Tarde</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Capacidad</label>
                            <input type="number" name="capacidad" class="form-control" value="30" min="1" max="50">
                        </div>
                        <div class="form-group">
                            <label>Docente Guía</label>
                            <select name="docente_guia_id" class="form-control">
                                <option value="">Sin asignar</option>
                                <?php foreach($docentes as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['nombre_completo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Aula</label>
                            <input type="text" name="aula" class="form-control" placeholder="Aula 101">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Sección</button>
                        <button type="reset" class="btn btn-secondary" onclick="cerrarModalNuevaSeccion()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
<script>
function abrirModalNuevaSeccion() { document.getElementById('modalNuevaSeccion').style.display = 'block'; }
function cerrarModalNuevaSeccion() { document.getElementById('modalNuevaSeccion').style.display = 'none'; }
window.onclick = function(e) { if(e.target == document.getElementById('modalNuevaSeccion')) cerrarModalNuevaSeccion(); }
</script>
</body>
</html>