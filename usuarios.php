<?php
// usuarios.php - Gestión de usuarios (con CSRF) - VERSIÓN 3.2
require_once 'config.php';
require_once 'functions.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'admin') { header('Location: dashboard.php'); exit; }

$db = getDBConnection();
$message = $error = '';

// Crear usuario
if (($_POST['action'] ?? '') == 'crear_usuario') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        try {
            $cedula = formatearCedulaParaBD($_POST['cedula']);
            $nombres = formatearNombrePropio($_POST['nombres']);
            $apellidos = formatearNombrePropio($_POST['apellidos']);
            $email = sanitize($_POST['email']);
            $telefono = sanitize($_POST['telefono']);
            $rol = $_POST['rol'];
            $password = $_POST['password'];
            $confirm = $_POST['confirm_password'];

            $validacion = validarCedulaVenezolana($cedula);
            if ($validacion !== true) throw new Exception(is_string($validacion) ? $validacion : "Cédula inválida.");
            if (empty($nombres) || empty($apellidos)) throw new Exception("Nombres y apellidos obligatorios.");
            if (!validarEmail($email)) throw new Exception("Email inválido.");
            if ($telefono && !validarTelefonoVenezolano($telefono)) throw new Exception("Teléfono inválido. Use el formato 0412-1234567");
            if (strlen($password) < 6) throw new Exception("La contraseña debe tener al menos 6 caracteres.");
            if ($password !== $confirm) throw new Exception("Las contraseñas no coinciden.");
            if (!in_array($rol, ['admin','docente','secretario'])) throw new Exception("Rol no válido.");

            $check = $db->prepare("SELECT id FROM usuarios WHERE cedula = ? OR email = ?");
            $check->execute([$cedula, $email]);
            if ($check->fetch()) throw new Exception("Ya existe un usuario con esa cédula o email.");

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO usuarios (cedula, nombres, apellidos, email, telefono, rol, password, activo) VALUES (?,?,?,?,?,?,?,1)");
            $stmt->execute([$cedula, $nombres, $apellidos, $email, $telefono, $rol, $hash]);
            $message = "✅ Usuario registrado exitosamente";
            registrarLog('crear_usuario', 'usuarios', $db->lastInsertId());
        } catch (Exception $e) { $error = "❌ " . $e->getMessage(); }
    }
}

// Eliminar usuario (POST con CSRF)
if (($_POST['action'] ?? '') == 'eliminar_usuario' && isset($_POST['id'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $id = (int)$_POST['id'];
        if ($id == $_SESSION['usuario_id']) {
            $error = "No puede eliminarse a sí mismo";
        } else {
            $stmt = $db->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Usuario eliminado exitosamente";
            registrarLog('eliminar_usuario', 'usuarios', $id);
        }
    }
}

$usuarios = $db->query("SELECT * FROM usuarios WHERE activo = 1 ORDER BY apellidos, nombres")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('usuarios'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-users-cog"></i> Gestión de Usuarios</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="abrirModalNuevoUsuario()">
                    <i class="fas fa-user-plus"></i> Nuevo Usuario
                </button>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="card">
                <div class="card-header"><h2>Listado de Usuarios</h2></div>
                <div class="card-body">
                    <?php if (count($usuarios) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Nombres y Apellidos</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Rol</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($usuarios as $user): $esActual = ($user['id'] == $_SESSION['usuario_id']); ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['cedula']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($user['apellidos'].', '.$user['nombres']); ?>
                                    <?php if($esActual): ?><span class="badge badge-info">Tú</span><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['telefono'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['rol']=='admin'?'badge-danger':($user['rol']=='docente'?'badge-warning':'badge-info'); ?>">
                                        <?php echo ucfirst($user['rol']); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="editar_usuario.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <?php if(!$esActual): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este usuario?')">
                                        <input type="hidden" name="action" value="eliminar_usuario">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Eliminar</button>
                                    </form>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-ban"></i> No disponible</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users fa-4x"></i>
                        <h3>No hay usuarios registrados</h3>
                        <button class="btn btn-primary" onclick="abrirModalNuevoUsuario()">Agregar Primer Usuario</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="modalNuevoUsuario" class="modal">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-close" onclick="cerrarModalNuevoUsuario()">&times;</div>
        <div class="card">
            <div class="card-header"><h2>Registrar Nuevo Usuario</h2></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="crear_usuario">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Cédula</label>
                            <input type="text" name="cedula" class="form-control cedula" required placeholder="Ej: 12345678">
                            <small class="form-text">Escribe solo los números. El sistema agregará la "V-" automáticamente.</small>
                        </div>
                        <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" required></div>
                        <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" class="form-control telefono" placeholder="0412-1234567">
                        </div>
                        <div class="form-group">
                            <label>Rol</label>
                            <select name="rol" class="form-control" required>
                                <option value="docente">Docente</option>
                                <option value="secretario">Secretario</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Contraseña</label><input type="password" name="password" class="form-control" minlength="6" required></div>
                        <div class="form-group"><label>Confirmar</label><input type="password" name="confirm_password" class="form-control" required></div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                        <button type="reset" class="btn btn-secondary" onclick="cerrarModalNuevoUsuario()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
<script>
function abrirModalNuevoUsuario() { document.getElementById('modalNuevoUsuario').style.display = 'block'; }
function cerrarModalNuevoUsuario() { document.getElementById('modalNuevoUsuario').style.display = 'none'; }
window.onclick = function(e) { if(e.target == document.getElementById('modalNuevoUsuario')) cerrarModalNuevoUsuario(); }
</script>
</body>
</html>