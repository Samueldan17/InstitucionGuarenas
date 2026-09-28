<?php
// secciones.php - Gestión de Secciones (con CSRF y eliminación por POST)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

$db = getDBConnection();
$message = '';
$error = '';

// Crear sección
if (($_POST['action'] ?? '') == 'crear_seccion') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        try {
            $grado_id = (int)($_POST['grado_id'] ?? 0);
            $letra = strtoupper(sanitize($_POST['letra'] ?? ''));
            $turno = $_POST['turno'] ?? 'mañana';
            $capacidad = (int)($_POST['capacidad'] ?? 30);
            $docente_guia_id = (int)($_POST['docente_guia_id'] ?? 0);
            $aula = sanitize($_POST['aula'] ?? '');
            if (empty($grado_id)) throw new Exception("Debe seleccionar un grado");
            if (empty($letra) || strlen($letra) > 1) throw new Exception("La letra debe ser un solo carácter");
            $stmt = $db->prepare("SELECT id FROM secciones WHERE grado_id = ? AND letra = ? AND turno = ? AND activo = 1");
            $stmt->execute([$grado_id, $letra, $turno]);
            if ($stmt->fetch()) throw new Exception("Ya existe una sección con esa letra y turno para este grado");
            $stmt = $db->prepare("INSERT INTO secciones (grado_id, letra, turno, capacidad, docente_guia_id, aula, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$grado_id, $letra, $turno, $capacidad, $docente_guia_id, $aula]);
            $message = "✅ Sección creada exitosamente";
            registrarLog('crear_seccion', 'secciones', $db->lastInsertId());
        } catch (Exception $e) { $error = "❌ " . $e->getMessage(); }
    }
}

// Eliminar sección (POST con CSRF)
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
}

$secciones = $db->query("SELECT s.*, g.nombre as grado_nombre, CONCAT(u.nombres, ' ', u.apellidos) as docente_guia_nombre FROM secciones s JOIN grados g ON s.grado_id = g.id LEFT JOIN usuarios u ON s.docente_guia_id = u.id WHERE s.activo = 1 ORDER BY g.orden, s.letra")->fetchAll();
$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$docentes = $db->query("SELECT id, CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE rol = 'docente' AND activo = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Gestión de Secciones - <?php echo APP_NAME; ?></title><link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('secciones'); ?>
    <main class="main-content">
        <header class="main-header"><h1><i class="fas fa-users"></i> Gestión de Secciones</h1><div class="header-actions"><button class="btn btn-primary" onclick="abrirModalNuevaSeccion()"><i class="fas fa-plus"></i> Nueva Sección</button></div></header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo count($secciones); ?></h3><p>Secciones Activas</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:var(--warning-color);"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h3><?php echo $db->query("SELECT COUNT(*) as total FROM estudiantes WHERE estado='activo'")->fetch()['total']; ?></h3><p>Estudiantes Activos</p></div></div>
            </div>
            <div class="card"><div class="card-header"><h2>Listado de Secciones</h2></div><div class="card-body">
                <?php if (count($secciones) > 0): ?>
                <div class="table-responsive"><table class="data-table"><thead><tr><th>Grado</th><th>Sección</th><th>Turno</th><th>Capacidad</th><th>Aula</th><th>Docente Guía</th><th>Acciones</th></tr></thead><tbody>
                <?php foreach ($secciones as $sec): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($sec['grado_nombre']); ?></strong></td>
                    <td><?php echo htmlspecialchars($sec['letra']); ?></td>
                    <td><span class="badge <?php echo $sec['turno']=='mañana'?'badge-info':'badge-warning'; ?>"><?php echo ucfirst($sec['turno']); ?></span></td>
                    <td><?php echo $sec['capacidad']; ?> estudiantes</td>
                    <td><?php echo htmlspecialchars($sec['aula'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($sec['docente_guia_nombre'] ?: 'Sin asignar'); ?></td>
                    <td class="actions">
                        <a href="editar_seccion.php?id=<?php echo $sec['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i> Editar</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar la sección <?php echo htmlspecialchars($sec['grado_nombre'].' '.$sec['letra']); ?>?')">
                            <input type="hidden" name="action" value="eliminar_seccion">
                            <input type="hidden" name="id" value="<?php echo $sec['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?></tbody></table></div>
                <?php else: ?>
                <div class="empty-state"><i class="fas fa-users fa-4x"></i><h3>No hay secciones registradas</h3><button class="btn btn-primary" onclick="abrirModalNuevaSeccion()">Agregar Primera Sección</button></div>
                <?php endif; ?>
            </div></div>
        </div>
    </main>
</div>

<!-- Modal Nueva Sección con CSRF -->
<div id="modalNuevaSeccion" class="modal"><div class="modal-content" style="max-width:700px;"><div class="modal-close" onclick="cerrarModalNuevaSeccion()">&times;</div><div class="card"><div class="card-header"><h2>Crear Nueva Sección</h2></div><div class="card-body"><form method="POST" action="secciones.php"><input type="hidden" name="action" value="crear_seccion"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><div class="form-grid"><div class="form-group"><label>Grado</label><select name="grado_id" class="form-control" required><?php foreach($grados as $g): ?><option value="<?php echo $g['id']; ?>"><?php echo $g['nombre']; ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Letra</label><input type="text" name="letra" class="form-control" maxlength="1" pattern="[A-Z]" title="Una letra mayúscula" required></div><div class="form-group"><label>Turno</label><select name="turno" class="form-control"><option value="mañana">Mañana</option><option value="tarde">Tarde</option></select></div><div class="form-group"><label>Capacidad</label><input type="number" name="capacidad" class="form-control" value="30" min="1" max="50"></div><div class="form-group"><label>Docente Guía</label><select name="docente_guia_id" class="form-control"><option value="">Sin asignar</option><?php foreach($docentes as $d): ?><option value="<?php echo $d['id']; ?>"><?php echo $d['nombre_completo']; ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Aula</label><input type="text" name="aula" class="form-control" placeholder="Aula 101"></div></div><div class="form-actions"><button type="submit" class="btn btn-primary">Guardar Sección</button><button type="reset" class="btn btn-secondary" onclick="cerrarModalNuevaSeccion()">Cancelar</button></div></form></div></div></div></div>

<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
<script>
function abrirModalNuevaSeccion() { document.getElementById('modalNuevaSeccion').style.display = 'block'; }
function cerrarModalNuevaSeccion() { document.getElementById('modalNuevaSeccion').style.display = 'none'; }
window.onclick = function(e) { if(e.target == document.getElementById('modalNuevaSeccion')) cerrarModalNuevaSeccion(); }
</script>
</body>
</html>