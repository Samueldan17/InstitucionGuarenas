<?php
// editar_usuario.php - Edición de usuario (VERSIÓN 3.4)
require_once 'config.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'admin') { header('Location: dashboard.php'); exit; }
$db = getDBConnection();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: usuarios.php'); exit; }

$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch();
if (!$usuario) { header('Location: usuarios.php'); exit; }

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $cedula = formatearCedulaParaBD($_POST['cedula'] ?? '');
        $nombres = formatearNombrePropio($_POST['nombres'] ?? '');
        $apellidos = formatearNombrePropio($_POST['apellidos'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $telefono = sanitize($_POST['telefono'] ?? '');
        $rol = $_POST['rol'] ?? '';
        $activo = isset($_POST['activo']) ? 1 : 0;

        $valid_ced = validarCedulaVenezolana($cedula);
        if ($valid_ced !== true) $error = is_string($valid_ced) ? $valid_ced : "Cédula inválida.";
        elseif (empty($nombres) || empty($apellidos)) $error = "Nombres y apellidos obligatorios.";
        elseif (!validarEmail($email)) $error = "Email inválido.";
        elseif ($telefono && !validarTelefonoVenezolano($telefono)) $error = "Teléfono inválido. Use formato 0412-1234567";
        elseif (!in_array($rol, ['admin','docente','secretario'])) $error = "Rol no válido.";
        else {
            $check = $db->prepare("SELECT id FROM usuarios WHERE (cedula = ? OR email = ?) AND id != ?");
            $check->execute([$cedula, $email, $id]);
            if ($check->fetch()) {
                $error = "Ya existe otro usuario con la misma cédula o email.";
            } else {
                $stmt = $db->prepare("UPDATE usuarios SET cedula=?, nombres=?, apellidos=?, email=?, telefono=?, rol=?, activo=? WHERE id=?");
                $stmt->execute([$cedula, $nombres, $apellidos, $email, $telefono, $rol, $activo, $id]);
                $message = "✅ Usuario actualizado correctamente.";
                registrarLog('editar_usuario', 'usuarios', $id);
                
                $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $usuario = $stmt->fetch();
            }
        }
    }
}

// Iniciales para el avatar
$iniciales = strtoupper(substr($usuario['nombres'], 0, 1) . substr($usuario['apellidos'], 0, 1));

// Verificar si es el usuario actual
$es_mismo_usuario = ($usuario['id'] == $_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('usuarios'); ?>
    <main class="main-content">
        <header class="main-header">
            <div>
                <h1><i class="fas fa-user-edit"></i> Editar Usuario</h1>
                <p class="text-muted" style="margin: 5px 0 0 0; font-size: 14px;">
                    Editando a: <strong><?php echo htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']); ?></strong>
                </p>
            </div>
            <div class="header-actions">
                <a href="usuarios.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Listado</a>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <!-- Tarjeta de resumen -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #3498db, #2c3e50); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; flex-shrink: 0;">
                        <?php echo $iniciales; ?>
                    </div>
                    <div style="flex: 1;">
                        <h2 style="margin: 0;">
                            <?php echo htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']); ?>
                            <?php if ($es_mismo_usuario): ?>
                                <span class="badge badge-info">Tú</span>
                            <?php endif; ?>
                        </h2>
                        <p style="margin: 5px 0; color: #7f8c8d;">
                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($usuario['cedula']); ?> | 
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($usuario['email']); ?>
                        </p>
                        <span class="badge badge-<?php echo $usuario['rol']=='admin'?'danger':($usuario['rol']=='docente'?'warning':'info'); ?>">
                            <?php echo ucfirst($usuario['rol']); ?>
                        </span>
                        <span class="badge badge-<?php echo $usuario['activo']?'success':'secondary'; ?>">
                            <?php echo $usuario['activo']?'Activo':'Inactivo'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Formulario de edición -->
            <div class="card">
                <div class="card-header"><h2><i class="fas fa-user-cog"></i> Datos del Usuario</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Cédula</label>
                                <input type="text" name="cedula" class="form-control cedula" value="<?php echo htmlspecialchars($usuario['cedula']); ?>" required>
                                <small class="form-text">Escribe solo los números. El sistema agrega la "V-".</small>
                            </div>
                            <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" value="<?php echo htmlspecialchars($usuario['nombres']); ?>" required></div>
                            <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" value="<?php echo htmlspecialchars($usuario['apellidos']); ?>" required></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usuario['email']); ?>" required></div>
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" name="telefono" class="form-control telefono" value="<?php echo htmlspecialchars($usuario['telefono']); ?>" placeholder="0412-1234567">
                            </div>
                            <div class="form-group">
                                <label>Rol</label>
                                <select name="rol" class="form-control" required>
                                    <option value="admin" <?php echo $usuario['rol']=='admin'?'selected':''; ?>>Administrador</option>
                                    <option value="docente" <?php echo $usuario['rol']=='docente'?'selected':''; ?>>Docente</option>
                                    <option value="secretario" <?php echo $usuario['rol']=='secretario'?'selected':''; ?>>Secretario</option>
                                </select>
                                <small class="form-text">Define los permisos del usuario en el sistema.</small>
                            </div>
                            <div class="form-group full-width">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="activo" value="1" <?php echo $usuario['activo'] ? 'checked' : ''; ?> style="width: auto;">
                                    <span>Usuario activo (puede iniciar sesión en el sistema)</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                            <a href="usuarios.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info extra -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-body">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Último acceso: <?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?> | 
                        Fecha de creación: <?php echo date('d/m/Y', strtotime($usuario['fecha_creacion'])); ?>
                    </small>
                </div>
            </div>
        </div>
    </main>
</div>
<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
</body>
</html>