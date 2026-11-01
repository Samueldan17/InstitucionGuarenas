<?php
// usuarios.php - Gestión de usuarios (VERSIÓN 3.9 - Ojito perfecto integrado)
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
            
            $tel_prefijo = $_POST['telefono_prefijo'] ?? '';
            $tel_numero = trim($_POST['telefono_numero'] ?? '');
            $telefono = (!empty($tel_prefijo) && !empty($tel_numero)) ? $tel_prefijo . '-' . $tel_numero : '';
            
            $rol = $_POST['rol'];
            $password = $_POST['password'];
            $confirm = $_POST['confirm_password'];

            $validacion = validarCedulaVenezolana($cedula);
            if ($validacion !== true) throw new Exception(is_string($validacion) ? $validacion : "Cédula inválida.");
            if (empty($nombres) || empty($apellidos)) throw new Exception("Nombres y apellidos obligatorios.");
            if (!validarEmail($email)) throw new Exception("Email inválido.");
            if ($telefono && !validarTelefonoVenezolano($telefono)) throw new Exception("Teléfono inválido.");
            if (($pass_valid = validarPasswordSegura($password)) !== true) throw new Exception($pass_valid);
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

// Eliminar usuario
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
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .phone-group { display: flex; gap: 8px; width: 100%; align-items: center; }
        .phone-group select.form-control { width: 85px !important; flex-shrink: 0; padding: 8px 4px; text-align: center; }
        .phone-group input.form-control { flex: 1; min-width: 0; padding: 8px; }
        
        /* CSS PERFECTO PARA EL OJITO */
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper input { width: 100%; padding-right: 40px; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #586069; z-index: 10; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('usuarios'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-users-cog"></i> Gestión de Usuarios</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="abrirModalNuevoUsuario()"><i class="fas fa-user-plus"></i> Nuevo Usuario</button>
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
                                    <a href="editar_usuario.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                    <?php if(!$esActual): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este usuario?')">
                                        <input type="hidden" name="action" value="eliminar_usuario">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-users fa-4x"></i><h3>No hay usuarios registrados</h3></div>
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
                        </div>
                        <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" required></div>
                        <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                        
                        <div class="form-group">
                            <label>Teléfono</label>
                            <div class="phone-group">
                                <select name="telefono_prefijo" class="form-control">
                                    <option value="">-</option><option value="0412">0412</option><option value="0414">0414</option><option value="0416">0416</option><option value="0424">0424</option><option value="0426">0426</option><option value="0428">0428</option>
                                </select>
                                <input type="text" name="telefono_numero" class="form-control" placeholder="1234567" maxlength="7" pattern="\d{7}">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Rol</label>
                            <select name="rol" class="form-control" required>
                                <option value="docente">Docente</option>
                                <option value="secretario">Secretario</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>

                        <!-- CONTRASEÑA CON OJITO -->
                        <div class="form-group">
                            <label>Contraseña Segura</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="pass1" class="form-control" minlength="8" required placeholder="Mín. 8 caracteres">
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('pass1', this)"></i>
                            </div>
                            <small class="form-text">Mayúscula, minúscula, número y símbolo.</small>
                        </div>
                        
                        <!-- CONFIRMAR CONTRASEÑA CON OJITO -->
                        <div class="form-group">
                            <label>Confirmar</label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="pass2" class="form-control" minlength="8" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('pass2', this)"></i>
                            </div>
                        </div>
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

function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash"); 
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>
</body>
</html>