<?php
// grados.php - Gestión de Grados (VERSIÓN 1.1 - Límite de 5to Año)
require_once 'config.php';
require_once 'functions.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'admin') { header('Location: dashboard.php'); exit; }
$db = getDBConnection();

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// CREAR O EDITAR GRADO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'crear_grado') {
            try {
                $nombre = sanitize($_POST['nombre']);
                $nivel = sanitize($_POST['nivel']);
                $modalidad = sanitize($_POST['modalidad']);
                $orden = (int)$_POST['orden'];
                
                if (empty($nombre) || empty($nivel) || empty($modalidad)) throw new Exception("Todos los campos son obligatorios.");
                // VALIDACIÓN DE SEGURIDAD: Límite de 5to año
                if ($orden < 1 || $orden > 5) throw new Exception("El orden debe estar entre 1 y 5 (La secundaria solo llega a 5to año).");
                
                $stmt = $db->prepare("INSERT INTO grados (nombre, nivel, modalidad, orden, activo) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$nombre, $nivel, $modalidad, $orden]);
                
                $message = "✅ Grado registrado exitosamente.";
                registrarLog('crear_grado', 'grados', $db->lastInsertId());
            } catch (Exception $e) { $error = "❌ " . $e->getMessage(); }
            
        } elseif ($action === 'editar_grado') {
            try {
                $id = (int)$_POST['id'];
                $nombre = sanitize($_POST['nombre']);
                $nivel = sanitize($_POST['nivel']);
                $modalidad = sanitize($_POST['modalidad']);
                $orden = (int)$_POST['orden'];
                
                if (empty($nombre) || empty($nivel) || empty($modalidad)) throw new Exception("Todos los campos son obligatorios.");
                // VALIDACIÓN DE SEGURIDAD: Límite de 5to año
                if ($orden < 1 || $orden > 5) throw new Exception("El orden debe estar entre 1 y 5 (La secundaria solo llega a 5to año).");
                
                $stmt = $db->prepare("UPDATE grados SET nombre = ?, nivel = ?, modalidad = ?, orden = ? WHERE id = ?");
                $stmt->execute([$nombre, $nivel, $modalidad, $orden, $id]);
                
                $message = "✅ Grado actualizado exitosamente.";
                registrarLog('editar_grado', 'grados', $id);
            } catch (Exception $e) { $error = "❌ " . $e->getMessage(); }
        }
    }
    header("Location: grados.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// TOGGLE (ACTIVAR/DESACTIVAR)
if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $db->prepare("SELECT activo FROM grados WHERE id = ?");
        $stmt->execute([$id]);
        $grado = $stmt->fetch();
        if ($grado) {
            $nuevoEstado = $grado['activo'] ? 0 : 1;
            $stmt = $db->prepare("UPDATE grados SET activo = ? WHERE id = ?");
            $stmt->execute([$nuevoEstado, $id]);
            $message = "Grado " . ($nuevoEstado ? "activado" : "desactivado") . " correctamente.";
            registrarLog('toggle_grado', 'grados', $id, "Nuevo estado: $nuevoEstado");
        }
    } catch (PDOException $e) { $error = "Error al cambiar estado: " . $e->getMessage(); }
    header("Location: grados.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// CARGAR GRADOS
$grados = $db->query("SELECT * FROM grados ORDER BY orden ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Grados - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .actions { display: flex; gap: 5px; justify-content: center; align-items: center; }
        .actions .btn-sm { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0; transition: none !important; }
        .actions form { margin: 0; padding: 0; display: inline-flex; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('grados'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-layer-group"></i> Gestión de Grados</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="abrirModalNuevoGrado()"><i class="fas fa-plus"></i> Nuevo Grado</button>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header"><h2>Listado de Grados / Años</h2></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px; text-align: center;">Orden</th>
                                    <th>Nombre del Grado</th>
                                    <th>Nivel Educativo</th>
                                    <th>Modalidad</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grados as $g): ?>
                                <tr>
                                    <td style="text-align: center;"><strong><?php echo $g['orden']; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($g['nombre']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($g['nivel']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $g['modalidad'] == 'Media Técnica' ? 'badge-warning' : 'badge-info'; ?>">
                                            <?php echo htmlspecialchars($g['modalidad'] ?? 'Media General'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $g['activo'] ? 'success' : 'danger'; ?>">
                                            <?php echo $g['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <button class="btn btn-sm btn-warning" onclick='abrirModalEditarGrado(<?php echo json_encode($g); ?>)' title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?action=toggle&id=<?php echo $g['id']; ?>" class="btn btn-sm btn-<?php echo $g['activo'] ? 'danger' : 'success'; ?>" onclick="return confirm('¿Cambiar estado de este grado?')" title="Cambiar Estado">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Nuevo Grado -->
<div id="modalNuevoGrado" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-close" onclick="cerrarModalNuevoGrado()">&times;</div>
        <div class="card">
            <div class="card-header"><h2>Registrar Nuevo Grado</h2></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="crear_grado">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre del Grado</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: 1er Año" required>
                        </div>
                        <div class="form-group">
                            <label>Orden (1 al 5)</label>
                            <input type="number" name="orden" class="form-control" value="1" min="1" max="5" required>
                            <small class="form-text">Máximo 5to año.</small>
                        </div>
                        <div class="form-group">
                            <label>Nivel Educativo</label>
                            <select name="nivel" class="form-control" required>
                                <option value="Media General">Media General</option>
                                <option value="Media Técnica">Media Técnica</option>
                                <option value="Primaria">Primaria</option>
                                <option value="Inicial">Inicial</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Modalidad</label>
                            <select name="modalidad" class="form-control" required>
                                <option value="Media General">Media General</option>
                                <option value="Media Técnica">Media Técnica</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Grado</button>
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalNuevoGrado()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Grado -->
<div id="modalEditarGrado" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-close" onclick="cerrarModalEditarGrado()">&times;</div>
        <div class="card">
            <div class="card-header"><h2>Editar Grado</h2></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="editar_grado">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre del Grado</label>
                            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Orden (1 al 5)</label>
                            <input type="number" name="orden" id="edit_orden" class="form-control" min="1" max="5" required>
                            <small class="form-text">Máximo 5to año.</small>
                        </div>
                        <div class="form-group">
                            <label>Nivel Educativo</label>
                            <select name="nivel" id="edit_nivel" class="form-control" required>
                                <option value="Media General">Media General</option>
                                <option value="Media Técnica">Media Técnica</option>
                                <option value="Primaria">Primaria</option>
                                <option value="Inicial">Inicial</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Modalidad</label>
                            <select name="modalidad" id="edit_modalidad" class="form-control" required>
                                <option value="Media General">Media General</option>
                                <option value="Media Técnica">Media Técnica</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar Grado</button>
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalEditarGrado()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
<script>
function abrirModalNuevoGrado() { document.getElementById('modalNuevoGrado').style.display = 'block'; }
function cerrarModalNuevoGrado() { document.getElementById('modalNuevoGrado').style.display = 'none'; }

function abrirModalEditarGrado(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_nombre').value = data.nombre;
    document.getElementById('edit_orden').value = data.orden;
    document.getElementById('edit_nivel').value = data.nivel;
    document.getElementById('edit_modalidad').value = data.modalidad || 'Media General';
    document.getElementById('modalEditarGrado').style.display = 'block';
}
function cerrarModalEditarGrado() { document.getElementById('modalEditarGrado').style.display = 'none'; }

window.onclick = function(e) {
    if(e.target == document.getElementById('modalNuevoGrado')) cerrarModalNuevoGrado();
    if(e.target == document.getElementById('modalEditarGrado')) cerrarModalEditarGrado();
}
</script>
</body>
</html>