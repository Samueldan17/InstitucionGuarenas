<?php
// estudiantes.php - Gestión de Estudiantes (VERSIÓN 3.8 - Teléfonos estilizados)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

$db = getDBConnection();
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// CREAR ESTUDIANTE
if (($_POST['action'] ?? '') == 'crear_estudiante') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        try {
            $cedula = formatearCedulaParaBD($_POST['cedula'] ?? '');
            $nombres = formatearNombrePropio($_POST['nombres'] ?? '');
            $apellidos = formatearNombrePropio($_POST['apellidos'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
            $genero = $_POST['genero'] ?? '';
            $nacionalidad = sanitize($_POST['nacionalidad'] ?? 'Venezolana');
            $lugar_nacimiento = sanitize($_POST['lugar_nacimiento'] ?? '');
            $direccion = sanitize($_POST['direccion'] ?? '');
            
            // Teléfono Estudiante
            $tel_est_prefijo = $_POST['telefono_contacto_prefijo'] ?? '';
            $tel_est_numero = trim($_POST['telefono_contacto_numero'] ?? '');
            $telefono_contacto = (!empty($tel_est_prefijo) && !empty($tel_est_numero)) ? $tel_est_prefijo . '-' . $tel_est_numero : '';

            $nombre_representante = formatearNombrePropio($_POST['nombre_representante'] ?? '');
            $cedula_representante = formatearCedulaParaBD($_POST['cedula_representante'] ?? '');
            $email_representante = sanitize($_POST['email_representante'] ?? '');
            
            // Teléfono Representante
            $tel_rep_prefijo = $_POST['telefono_representante_prefijo'] ?? '';
            $tel_rep_numero = trim($_POST['telefono_representante_numero'] ?? '');
            $telefono_representante = (!empty($tel_rep_prefijo) && !empty($tel_rep_numero)) ? $tel_rep_prefijo . '-' . $tel_rep_numero : '';

            $parentesco = sanitize($_POST['parentesco'] ?? '');
            $procedencia = sanitize($_POST['procedencia'] ?? '');
            $seccion_id = (int)($_POST['seccion_id'] ?? 0);
            $fecha_inscripcion = $_POST['fecha_inscripcion'] ?? date('Y-m-d');

            $validacion = validarCedulaVenezolana($cedula);
            if ($validacion !== true) throw new Exception(is_string($validacion) ? $validacion : "Cédula inválida.");
            if (empty($nombres) || empty($apellidos)) throw new Exception("Los nombres y apellidos son obligatorios");
            if (empty($seccion_id)) throw new Exception("Debe seleccionar una sección");
            if ($email && !validarEmail($email)) throw new Exception("El email del estudiante no es válido");
            if ($email_representante && !validarEmail($email_representante)) throw new Exception("El email del representante no es válido");

            $stmt = $db->prepare("SELECT id FROM estudiantes WHERE cedula = ?");
            $stmt->execute([$cedula]);
            if ($stmt->fetch()) throw new Exception("Ya existe un estudiante con la misma cédula");

            $stmt = $db->prepare("INSERT INTO estudiantes (cedula, nombres, apellidos, email, fecha_nacimiento, genero, nacionalidad, lugar_nacimiento, direccion, telefono_contacto, nombre_representante, cedula_representante, email_representante, telefono_representante, parentesco, procedencia, seccion_id, fecha_inscripcion, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')");
            $stmt->execute([$cedula, $nombres, $apellidos, $email, $fecha_nacimiento, $genero, $nacionalidad, $lugar_nacimiento, $direccion, $telefono_contacto, $nombre_representante, $cedula_representante, $email_representante, $telefono_representante, $parentesco, $procedencia, $seccion_id, $fecha_inscripcion]);
            
            $estudiante_id = $db->lastInsertId();
            
            $rep_info = "";
            if (!empty($cedula_representante)) {
                $rep_id = crearYVincularRepresentante($estudiante_id, $cedula_representante, $nombre_representante, '', $email_representante, $telefono_representante, $parentesco ?: 'Representante');
                if ($rep_id) { $rep_info = " Se creó el usuario del representante."; }
            }
            
            $message = "✅ Estudiante registrado exitosamente.$rep_info";
            registrarLog('crear_estudiante', 'estudiantes', $estudiante_id, "Nuevo estudiante: $cedula");
        } catch (Exception $e) { $error = "❌ " . $e->getMessage(); }
    }
    header("Location: estudiantes.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// CREAR USUARIO PARA ESTUDIANTE
if (($_POST['action'] ?? '') == 'crear_usuario_estudiante' && isset($_POST['estudiante_id'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $estudiante_id = (int)$_POST['estudiante_id'];
        try {
            $usuario_id = crearUsuarioEstudiante($estudiante_id);
            if ($usuario_id) {
                $stmt = $db->prepare("SELECT cedula, nombres, apellidos FROM estudiantes WHERE id = ?");
                $stmt->execute([$estudiante_id]);
                $est = $stmt->fetch();
                $message = "✅ Usuario creado para {$est['nombres']}. Usuario y contraseña: {$est['cedula']}";
            } else { $error = "❌ No se pudo crear el usuario."; }
        } catch (Exception $e) { $error = "❌ Error: " . $e->getMessage(); }
    }
    header("Location: estudiantes.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// ELIMINAR ESTUDIANTE
if (($_POST['action'] ?? '') == 'eliminar_estudiante' && isset($_POST['id'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $estudiante_id = (int)$_POST['id'];
        try {
            $stmt = $db->prepare("UPDATE estudiantes SET estado = 'inactivo' WHERE id = ?");
            $stmt->execute([$estudiante_id]);
            $message = "✅ Estudiante desactivado exitosamente";
            registrarLog('eliminar_estudiante', 'estudiantes', $estudiante_id);
        } catch (Exception $e) { $error = "❌ Error al eliminar: " . $e->getMessage(); }
    }
    header("Location: estudiantes.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// FILTROS Y PAGINACIÓN
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$filtro_grado = (int)($_GET['filtro_grado'] ?? 0);
$filtro_seccion = (int)($_GET['filtro_seccion'] ?? 0);
$filtro_estado = $_GET['filtro_estado'] ?? 'activo';
$filtro_usuario = $_GET['filtro_usuario'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
$params = [];
if ($filtro_grado > 0) { $where[] = "g.id = ?"; $params[] = $filtro_grado; }
if ($filtro_seccion > 0) { $where[] = "s.id = ?"; $params[] = $filtro_seccion; }
if ($filtro_estado && $filtro_estado !== 'todos') { $where[] = "e.estado = ?"; $params[] = $filtro_estado; }
if ($filtro_usuario === 'con') { $where[] = "e.usuario_id IS NOT NULL"; }
elseif ($filtro_usuario === 'sin') { $where[] = "e.usuario_id IS NULL"; }
if ($search) {
    $where[] = "(e.nombres LIKE ? OR e.apellidos LIKE ? OR e.cedula LIKE ? OR e.nombre_representante LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$countStmt = $db->prepare("SELECT COUNT(*) FROM estudiantes e LEFT JOIN secciones s ON e.seccion_id = s.id LEFT JOIN grados g ON s.grado_id = g.id $whereClause");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

$query = "SELECT e.*, g.nombre as grado_nombre, s.letra as seccion_letra, s.turno,
          (SELECT COUNT(*) FROM representantes_estudiantes re WHERE re.estudiante_id = e.id) as total_representantes
          FROM estudiantes e 
          LEFT JOIN secciones s ON e.seccion_id = s.id 
          LEFT JOIN grados g ON s.grado_id = g.id 
          $whereClause ORDER BY e.apellidos, e.nombres LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll();

$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$secciones = $db->query("SELECT s.id, g.nombre as grado, s.letra, s.turno FROM secciones s JOIN grados g ON s.grado_id = g.id WHERE s.activo = 1 ORDER BY g.orden, s.letra")->fetchAll();

$totalActivos = $db->query("SELECT COUNT(*) FROM estudiantes WHERE estado = 'activo'")->fetchColumn();
$totalInactivos = $db->query("SELECT COUNT(*) FROM estudiantes WHERE estado = 'inactivo'")->fetchColumn();
$totalConUsuario = $db->query("SELECT COUNT(*) FROM estudiantes WHERE usuario_id IS NOT NULL AND estado = 'activo'")->fetchColumn();
$totalSinUsuario = $db->query("SELECT COUNT(*) FROM estudiantes WHERE usuario_id IS NULL AND estado = 'activo'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Estudiantes - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .phone-group { display: flex; gap: 8px; width: 100%; align-items: center; }
        .phone-group select.form-control { width: 85px !important; flex-shrink: 0; padding: 8px 4px; text-align: center; }
        .phone-group input.form-control { flex: 1; min-width: 0; padding: 8px; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('estudiantes'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-user-graduate"></i> Gestión de Estudiantes</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="abrirModalNuevoEstudiante()"><i class="fas fa-user-plus"></i> Nuevo Estudiante</button>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h3><?php echo $totalActivos; ?></h3><p>Estudiantes Activos</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#27ae60;"><i class="fas fa-key"></i></div><div class="stat-info"><h3><?php echo $totalConUsuario; ?></h3><p>Con Usuario</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#e67e22;"><i class="fas fa-user-slash"></i></div><div class="stat-info"><h3><?php echo $totalSinUsuario; ?></h3><p>Sin Usuario</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#e74c3c;"><i class="fas fa-user-times"></i></div><div class="stat-info"><h3><?php echo $totalInactivos; ?></h3><p>Inactivos</p></div></div>
            </div>

            <div class="card">
                <div class="card-header"><h2><i class="fas fa-list"></i> Listado de Estudiantes</h2></div>
                <div class="card-body">
                    <form method="GET" class="filters-form" style="margin-bottom: 1rem; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <select name="filtro_grado" class="form-control" style="max-width: 160px;">
                            <option value="">Todos los grados</option>
                            <?php foreach($grados as $g): ?>
                                <option value="<?php echo $g['id']; ?>" <?php echo $filtro_grado == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="filtro_seccion" class="form-control" style="max-width: 180px;">
                            <option value="">Todas las secciones</option>
                            <?php foreach($secciones as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $filtro_seccion == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['grado'] . ' ' . $s['letra'] . ' (' . $s['turno'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="filtro_usuario" class="form-control" style="max-width: 160px;">
                            <option value="">Todos (usuario)</option>
                            <option value="con" <?php echo $filtro_usuario == 'con' ? 'selected' : ''; ?>>Con usuario</option>
                            <option value="sin" <?php echo $filtro_usuario == 'sin' ? 'selected' : ''; ?>>Sin usuario</option>
                        </select>
                        <select name="filtro_estado" class="form-control" style="max-width: 130px;">
                            <option value="activo" <?php echo $filtro_estado == 'activo' ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactivo" <?php echo $filtro_estado == 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                            <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos</option>
                        </select>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar..." class="form-control" style="max-width: 220px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                        <a href="estudiantes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Limpiar</a>
                    </form>
                    
                    <?php if (count($estudiantes) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr><th>Cédula</th><th>Nombres y Apellidos</th><th>Grado y Sección</th><th>Representante</th><th>Usuario</th><th>Estado</th><th>Acciones</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $est): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($est['cedula']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($est['apellidos'] . ', ' . $est['nombres']); ?></td>
                                    <td>
                                        <?php if ($est['grado_nombre']): ?><?php echo htmlspecialchars($est['grado_nombre'] . ' ' . $est['seccion_letra']); ?> <small class="text-muted">(<?php echo $est['turno']; ?>)</small><?php else: ?><span class="text-muted">Sin sección</span><?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($est['nombre_representante'] ?: '—'); ?></td>
                                    <td><?php if ($est['usuario_id']): ?><span class="badge badge-success"><i class="fas fa-check"></i> Sí</span><?php else: ?><span class="badge badge-secondary"><i class="fas fa-times"></i> No</span><?php endif; ?></td>
                                    <td><span class="badge badge-<?php echo $est['estado']=='activo'?'success':($est['estado']=='inactivo'?'danger':'info'); ?>"><?php echo ucfirst($est['estado']); ?></span></td>
                                    <td class="actions">
                                        <a href="editar_estudiante.php?id=<?php echo $est['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                        <?php if (!$est['usuario_id'] && $est['estado'] == 'activo'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Crear usuario?')">
                                                <input type="hidden" name="action" value="crear_usuario_estudiante">
                                                <input type="hidden" name="estudiante_id" value="<?php echo $est['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-key"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desactivar?')">
                                            <input type="hidden" name="action" value="eliminar_estudiante">
                                            <input type="hidden" name="id" value="<?php echo $est['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="table-footer">
                        <div class="pagination">
                            <?php if ($page > 1): ?><a class="btn btn-secondary" href="?page=<?php echo $page-1; ?>&filtro_grado=<?php echo $filtro_grado; ?>&filtro_seccion=<?php echo $filtro_seccion; ?>&filtro_estado=<?php echo urlencode($filtro_estado); ?>&filtro_usuario=<?php echo urlencode($filtro_usuario); ?>&search=<?php echo urlencode($search); ?>">&laquo;</a><?php endif; ?>
                            <span>Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?><a class="btn btn-secondary" href="?page=<?php echo $page+1; ?>&filtro_grado=<?php echo $filtro_grado; ?>&filtro_seccion=<?php echo $filtro_seccion; ?>&filtro_estado=<?php echo urlencode($filtro_estado); ?>&filtro_usuario=<?php echo urlencode($filtro_usuario); ?>&search=<?php echo urlencode($search); ?>">&raquo;</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-user-graduate fa-4x"></i><h3>No se encontraron estudiantes</h3></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="modalNuevoEstudiante" class="modal">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-close" onclick="cerrarModalNuevoEstudiante()">&times;</div>
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-user-plus"></i> Planilla de Inscripción</h2></div>
            <div class="card-body">
                <form method="POST" action="estudiantes.php">
                    <input type="hidden" name="action" value="crear_estudiante">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="form-grid">
                        <div class="form-group"><label>Cédula</label><input type="text" name="cedula" class="form-control cedula" required placeholder="12345678"></div>
                        <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" required></div>
                        <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="form-group"><label>Fecha Nacimiento</label><input type="date" name="fecha_nacimiento" class="form-control"></div>
                        <div class="form-group"><label>Género</label><select name="genero" class="form-control"><option value="">Seleccione...</option><option value="M">Masculino</option><option value="F">Femenino</option></select></div>
                        <div class="form-group"><label>Nacionalidad</label><input type="text" name="nacionalidad" class="form-control" value="Venezolana"></div>
                        <div class="form-group"><label>Lugar de Nacimiento</label><input type="text" name="lugar_nacimiento" class="form-control"></div>
                        <div class="form-group full-width"><label>Dirección</label><textarea name="direccion" class="form-control" rows="2"></textarea></div>
                        
                        <div class="form-group">
                            <label>Teléfono Estudiante</label>
                            <div class="phone-group">
                                <select name="telefono_contacto_prefijo" class="form-control">
                                    <option value="">-</option><option value="0412">0412</option><option value="0414">0414</option><option value="0416">0416</option><option value="0424">0424</option><option value="0426">0426</option><option value="0428">0428</option>
                                </select>
                                <input type="text" name="telefono_contacto_numero" class="form-control" placeholder="1234567" maxlength="7" pattern="\d{7}">
                            </div>
                        </div>
                        
                        <div class="form-group"><label>Sección</label><select name="seccion_id" class="form-control" required><option value="">Seleccione</option><?php foreach ($secciones as $sec): ?><option value="<?php echo $sec['id']; ?>"><?php echo htmlspecialchars($sec['grado'] . ' ' . $sec['letra'] . ' (' . $sec['turno'] . ')'); ?></option><?php endforeach; ?></select></div>
                        
                        <div class="form-group full-width" style="margin-top: 10px;"><h3 style="border-bottom: 2px solid #3498db; padding-bottom: 5px;"><i class="fas fa-users"></i> Datos del Representante</h3></div>
                        
                        <div class="form-group"><label>Cédula del Representante</label><input type="text" name="cedula_representante" class="form-control cedula" placeholder="12345678"></div>
                        <div class="form-group"><label>Nombre</label><input type="text" name="nombre_representante" class="form-control"></div>
                        <div class="form-group"><label>Parentesco</label><input type="text" name="parentesco" class="form-control"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email_representante" class="form-control"></div>
                        
                        <div class="form-group">
                            <label>Teléfono Representante</label>
                            <div class="phone-group">
                                <select name="telefono_representante_prefijo" class="form-control">
                                    <option value="">-</option><option value="0412">0412</option><option value="0414">0414</option><option value="0416">0416</option><option value="0424">0424</option><option value="0426">0426</option><option value="0428">0428</option>
                                </select>
                                <input type="text" name="telefono_representante_numero" class="form-control" placeholder="1234567" maxlength="7" pattern="\d{7}">
                            </div>
                        </div>

                        <div class="form-group"><label>Procedencia</label><input type="text" name="procedencia" class="form-control"></div>
                        <div class="form-group"><label>Fecha Inscripción</label><input type="date" name="fecha_inscripcion" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Estudiante</button>
                        <button type="reset" class="btn btn-secondary" onclick="cerrarModalNuevoEstudiante()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
<script>
function abrirModalNuevoEstudiante() { document.getElementById('modalNuevoEstudiante').style.display = 'block'; }
function cerrarModalNuevoEstudiante() { document.getElementById('modalNuevoEstudiante').style.display = 'none'; }
window.onclick = function(e) { if(e.target == document.getElementById('modalNuevoEstudiante')) cerrarModalNuevoEstudiante(); }
</script>
</body>
</html>