<?php
// editar_usuario.php - Edición de usuario (VERSIÓN 3.8 - Teléfono estilizado)
require_once 'config.php';
require_once 'functions.php';
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
        
        $tel_prefijo = $_POST['telefono_prefijo'] ?? '';
        $tel_numero = trim($_POST['telefono_numero'] ?? '');
        $telefono = (!empty($tel_prefijo) && !empty($tel_numero)) ? $tel_prefijo . '-' . $tel_numero : '';

        $rol = $_POST['rol'] ?? '';
        $activo = isset($_POST['activo']) ? 1 : 0;
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $valid_ced = validarCedulaVenezolana($cedula);
        if ($valid_ced !== true) $error = is_string($valid_ced) ? $valid_ced : "Cédula inválida.";
        elseif (empty($nombres) || empty($apellidos)) $error = "Nombres y apellidos obligatorios.";
        elseif (!validarEmail($email)) $error = "Email inválido.";
        elseif ($telefono && !validarTelefonoVenezolano($telefono)) $error = "Teléfono inválido. Debe tener 7 dígitos después del prefijo.";
        elseif (!in_array($rol, ['admin','docente','secretario'])) $error = "Rol no válido.";
        elseif (!empty($new_password)) {
            if (($pass_valid = validarPasswordSegura($new_password)) !== true) {
                $error = is_string($pass_valid) ? $pass_valid : 'Contraseña inválida';
            } elseif ($new_password !== $confirm_password) {
                $error = "Las contraseñas no coinciden.";
            }
        }
        
        if (!$error) {
            $check = $db->prepare("SELECT id FROM usuarios WHERE (cedula = ? OR email = ?) AND id != ?");
            $check->execute([$cedula, $email, $id]);
            if ($check->fetch()) {
                $error = "Ya existe otro usuario con la misma cédula o email.";
            } else {
                if (!empty($new_password)) {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET cedula=?, nombres=?, apellidos=?, email=?, telefono=?, rol=?, activo=?, password=? WHERE id=?");
                    $stmt->execute([$cedula, $nombres, $apellidos, $email, $telefono, $rol, $activo, $hash, $id]);
                    $message = "✅ Usuario y contraseña actualizados correctamente.";
                } else {
                    $stmt = $db->prepare("UPDATE usuarios SET cedula=?, nombres=?, apellidos=?, email=?, telefono=?, rol=?, activo=? WHERE id=?");
                    $stmt->execute([$cedula, $nombres, $apellidos, $email, $telefono, $rol, $activo, $id]);
                    $message = "✅ Usuario actualizado correctamente.";
                }
                registrarLog('editar_usuario', 'usuarios', $id);
                
                $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $usuario = $stmt->fetch();
            }
        }
    }
}

// Separar el teléfono existente para mostrarlo en el dropdown
$tel_prefijo_actual = '';
$tel_numero_actual = '';
if (!empty($usuario['telefono'])) {
    $partes = explode('-', $usuario['telefono']);
    if (count($partes) == 2) {
        $tel_prefijo_actual = $partes[0];
        $tel_numero_actual = $partes[1];
    } else {
        $tel_prefijo_actual = substr($usuario['telefono'], 0, 4);
        $tel_numero_actual = substr($usuario['telefono'], 4);
    }
}

$iniciales = strtoupper(substr($usuario['nombres'], 0, 1) . substr($usuario['apellidos'], 0, 1));
$es_mismo_usuario = ($usuario['id'] == $_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>    
     
        /* Arreglo para que el teléfono no se desborde */
        .phone-group {
            display: flex;
            gap: 8px;
            width: 100%;
            align-items: center;
        }
        .phone-group select.form-control {
            width: 85px !important;
            flex-shrink: 0;
            padding: 8px 4px;
            text-align: center;
        }
        .phone-group input.form-control {
            flex: 1;
            min-width: 0; /* Evita que el input se salga de la caja */
            padding: 8px;
        }
    </style>
        
        
    </style>
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
                <a href="usuarios.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #3498db, #2c3e50); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; flex-shrink: 0;">
                        <?php echo $iniciales; ?>
                    </div>
                    <div>
                        <h2 style="margin: 0;">
                            <?php echo htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']); ?>
                            <?php if ($es_mismo_usuario): ?><span class="badge badge-info">Tú</span><?php endif; ?>
                        </h2>
                        <p style="margin: 5px 0; color: #7f8c8d;">
                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($usuario['cedula']); ?> | 
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($usuario['email']); ?>
                        </p>
                        <span class="badge badge-<?php echo $usuario['rol']=='admin'?'danger':($usuario['rol']=='docente'?'warning':'info'); ?>">
                            <?php echo ucfirst($usuario['rol']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-user-cog"></i> Datos Básicos</h2></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group"><label>Cédula</label><input type="text" name="cedula" class="form-control cedula" value="<?php echo htmlspecialchars($usuario['cedula']); ?>" required></div>
                            <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" value="<?php echo htmlspecialchars($usuario['nombres']); ?>" required></div>
                            <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" value="<?php echo htmlspecialchars($usuario['apellidos']); ?>" required></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usuario['email']); ?>" required></div>
                            
                            <div class="form-group">
                                <label>Teléfono</label>
                                <div class="phone-group">
                                    <select name="telefono_prefijo" class="form-control">
                                        <option value="">-</option>
                                        <option value="0412" <?php echo $tel_prefijo_actual == '0412' ? 'selected' : ''; ?>>0412</option>
                                        <option value="0414" <?php echo $tel_prefijo_actual == '0414' ? 'selected' : ''; ?>>0414</option>
                                        <option value="0416" <?php echo $tel_prefijo_actual == '0416' ? 'selected' : ''; ?>>0416</option>
                                        <option value="0424" <?php echo $tel_prefijo_actual == '0424' ? 'selected' : ''; ?>>0424</option>
                                        <option value="0426" <?php echo $tel_prefijo_actual == '0426' ? 'selected' : ''; ?>>0426</option>
                                        <option value="0428" <?php echo $tel_prefijo_actual == '0428' ? 'selected' : ''; ?>>0428</option>
                                    </select>
                                    <input type="text" name="telefono_numero" class="form-control" value="<?php echo htmlspecialchars($tel_numero_actual); ?>" placeholder="1234567" maxlength="7" pattern="\d{7}" title="Debe tener exactamente 7 números">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Rol</label>
                                <select name="rol" class="form-control" required>
                                    <option value="admin" <?php echo $usuario['rol']=='admin'?'selected':''; ?>>Administrador</option>
                                    <option value="docente" <?php echo $usuario['rol']=='docente'?'selected':''; ?>>Docente</option>
                                    <option value="secretario" <?php echo $usuario['rol']=='secretario'?'selected':''; ?>>Secretario</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="activo" value="1" <?php echo $usuario['activo'] ? 'checked' : ''; ?> style="width: auto;">
                                    <span>Usuario activo (puede iniciar sesión)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px; border-left: 4px solid #e74c3c;">
                    <div class="card-header"><h2><i class="fas fa-key"></i> Cambiar Contraseña (Opcional)</h2></div>
                    <div class="card-body">
                        <p class="text-muted" style="margin-bottom: 15px;">Deje estos campos en blanco si no desea cambiar la contraseña actual.</p>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nueva Contraseña</label>
                                <input type="password" name="new_password" class="form-control" minlength="8" placeholder="Mín. 8 caracteres">
                                <small class="form-text">Mayúscula, minúscula, número y símbolo.</small>
                            </div>
                            <div class="form-group"><label>Confirmar Nueva Contraseña</label><input type="password" name="confirm_password" class="form-control" minlength="8"></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body" style="display: flex; justify-content: space-between;">
                        <div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                            <a href="usuarios.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>
<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
</body>
</html>