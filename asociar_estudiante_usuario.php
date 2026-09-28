<?php
// asociar_estudiante_usuario.php - Asociar un estudiante a un usuario
require_once 'config.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = getDBConnection();
$message = '';
$error = '';

// Obtener estudiantes sin usuario asociado
$estudiantes = $db->query("
    SELECT e.id, e.cedula, e.nombres, e.apellidos, g.nombre as grado, s.letra as seccion
    FROM estudiantes e
    JOIN secciones s ON e.seccion_id = s.id
    JOIN grados g ON s.grado_id = g.id
    WHERE e.estado = 'activo' AND e.usuario_id IS NULL
    ORDER BY e.apellidos, e.nombres
")->fetchAll();

// Obtener usuarios con rol estudiante o representante sin asociar
$usuarios = $db->query("
    SELECT id, cedula, nombres, apellidos, email, rol
    FROM usuarios
    WHERE rol IN ('estudiante', 'representante') AND activo = 1
    ORDER BY apellidos, nombres
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estudiante_id = (int)$_POST['estudiante_id'];
    $usuario_id = (int)$_POST['usuario_id'];
    $tipo = $_POST['tipo'] ?? 'estudiante';
    
    if (!$estudiante_id || !$usuario_id) {
        $error = 'Debe seleccionar un estudiante y un usuario.';
    } else {
        try {
            if ($tipo === 'estudiante') {
                $stmt = $db->prepare("UPDATE estudiantes SET usuario_id = ? WHERE id = ?");
                $stmt->execute([$usuario_id, $estudiante_id]);
                $message = "✅ Estudiante asociado exitosamente al usuario.";
            } else {
                // Asociar representante
                $stmt = $db->prepare("INSERT INTO representante_estudiante (representante_id, estudiante_id) VALUES (?, ?)");
                $stmt->execute([$usuario_id, $estudiante_id]);
                $message = "✅ Representante asociado exitosamente al estudiante.";
            }
            registrarLog('asociar_usuario', 'estudiantes', $estudiante_id, "Usuario $usuario_id asociado como $tipo");
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Asociar Usuarios</title><link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('usuarios'); ?>
    <main class="main-content">
        <header class="main-header"><h1>Asociar Usuarios a Estudiantes</h1></header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="card">
                <div class="card-header"><h2>Asignar Usuario a Estudiante</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Estudiante (sin usuario)</label>
                                <select name="estudiante_id" class="form-control" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($estudiantes as $est): ?>
                                    <option value="<?php echo $est['id']; ?>">
                                        <?php echo htmlspecialchars($est['apellidos'].', '.$est['nombres'].' - '.$est['grado'].' '.$est['seccion']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Usuario</label>
                                <select name="usuario_id" class="form-control" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($usuarios as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['nombres'].' '.$u['apellidos'].' ('.$u['cedula'].') - '.ucfirst($u['rol'])); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Tipo de Asociación</label>
                                <select name="tipo" class="form-control">
                                    <option value="estudiante">Estudiante (login como estudiante)</option>
                                    <option value="representante">Representante (login como representante)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-link"></i> Asociar</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h2>Estudiantes sin Usuario Asociado</h2></div>
                <div class="card-body">
                    <?php if (empty($estudiantes)): ?>
                    <div class="alert alert-success">Todos los estudiantes activos ya tienen usuario asociado.</div>
                    <?php else: ?>
                    <ul>
                        <?php foreach ($estudiantes as $est): ?>
                        <li><?php echo htmlspecialchars($est['apellidos'].', '.$est['nombres'].' - '.$est['cedula']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>