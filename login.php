<?php
// login.php - Sistema de autenticación (VERSIÓN FINAL - Producción)
require_once 'config.php';
require_once 'functions.php';

if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_rol'] === 'estudiante') { header('Location: panel_estudiante.php'); }
    elseif ($_SESSION['usuario_rol'] === 'representante') { header('Location: panel_representante.php'); }
    else { header('Location: dashboard.php'); }
    exit;
}

$error = '';
$success = '';
$show_recovery = false;

if (isset($_GET['action']) && $_GET['action'] === 'recovery') $show_recovery = true;

// LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (empty($username) || empty($password)) {
        $error = 'Por favor ingrese usuario/cédula y contraseña';
    } else {
        $db = getDBConnection();
        if (!$db) { $error = 'Error de conexión a la base de datos'; } 
        else {
            try {
                // Verificar bloqueo por intentos fallidos
                $stmt = $db->prepare("SELECT bloqueado_hasta FROM login_attempts WHERE ip = ? ORDER BY ultimo_intento DESC LIMIT 1");
                $stmt->execute([$ip]);
                $bloqueo = $stmt->fetch();
                
                if ($bloqueo && $bloqueo['bloqueado_hasta'] && strtotime($bloqueo['bloqueado_hasta']) > time()) {
                    $error = 'Demasiados intentos fallidos. Intente nuevamente después de 15 minutos.';
                } else {
                    $cedula_formateada = formatearCedulaParaBD($username);
                    $stmt = $db->prepare("SELECT id, username, cedula, password, nombres, apellidos, rol, activo FROM usuarios WHERE (username = ? OR cedula = ? OR cedula = ?) AND activo = 1");
                    $stmt->execute([$username, $username, $cedula_formateada]);
                    $usuario = $stmt->fetch();

                    // VALIDACIÓN ESTRICTA CON BCRYPT (Sin bypass)
                    if ($usuario && password_verify($password, $usuario['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['usuario_id'] = $usuario['id'];
                        $_SESSION['usuario_nombre'] = $usuario['nombres'] . ' ' . $usuario['apellidos'];
                        $_SESSION['usuario_rol'] = $usuario['rol'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        // Limpiar intentos fallidos al entrar con éxito
                        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip = ?");
                        $stmt->execute([$ip]);
                        
                        $updateStmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                        $updateStmt->execute([$usuario['id']]);
                        registrarLog('login_exitoso', 'usuarios', $usuario['id'], "Usuario {$usuario['username']} inició sesión");

                        // Redirigir según el rol
                        switch ($usuario['rol']) {
                            case 'estudiante': header('Location: panel_estudiante.php'); break;
                            case 'representante': header('Location: panel_representante.php'); break;
                            default: header('Location: dashboard.php');
                        }
                        exit;
                    } else {
                        // Registrar intento fallido
                        $stmt = $db->prepare("INSERT INTO login_attempts (ip, usuario, intentos, ultimo_intento) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE intentos = intentos + 1, ultimo_intento = NOW()");
                        $stmt->execute([$ip, $username]);
                        $stmt = $db->prepare("SELECT intentos FROM login_attempts WHERE ip = ?");
                        $stmt->execute([$ip]);
                        $intentos = $stmt->fetch();
                        
                        // Bloquear si supera el máximo
                        if ($intentos && $intentos['intentos'] >= MAX_LOGIN_ATTEMPTS) {
                            $bloqueo_hasta = date('Y-m-d H:i:s', time() + LOGIN_BLOCK_TIME);
                            $stmt = $db->prepare("UPDATE login_attempts SET bloqueado_hasta = ? WHERE ip = ?");
                            $stmt->execute([$bloqueo_hasta, $ip]);
                            $error = 'Demasiados intentos fallidos. Su IP ha sido bloqueada por 15 minutos.';
                        } else { 
                            $error = 'Usuario/cédula o contraseña incorrectos'; 
                        }
                    }
                }
            } catch (PDOException $e) { 
                error_log("Error en login: " . $e->getMessage());
                $error = 'Error del sistema.'; 
            }
        }
    }
}

// RECUPERACIÓN DE CONTRASEÑA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recovery'])) {
    $email = sanitize($_POST['email'] ?? '');
    if (empty($email)) { $error = 'Por favor ingrese su email'; }
    elseif (!validarEmail($email)) { $error = 'El email no es válido'; }
    else {
        $db = getDBConnection();
        if ($db) {
            try {
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND activo = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $updateStmt = $db->prepare("UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE email = ?");
                    $updateStmt->execute([$token, $expiracion, $email]);
                    $reset_link = APP_URL . "/reset_password.php?token=$token";
                    $success = "Se ha generado un enlace de recuperación.<br><small>Enlace: <a href='$reset_link'>$reset_link</a></small>";
                } else { 
                    $error = 'No existe una cuenta activa con ese email'; 
                }
            } catch (PDOException $e) { $error = 'Error al procesar la solicitud'; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .alert-error { background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:1rem 1.5rem; margin-bottom:1.5rem; border-radius:8px; display:flex; align-items:center; }
        .alert-error i { margin-right:1rem; }
        .hidden { display:none; }
        .login-options { display:flex; flex-direction:column; gap:12px; margin-top:25px; padding-top:25px; border-top:1px solid #e0e0e0; }
        .option-btn { display:flex; align-items:center; justify-content:center; gap:10px; padding:12px 20px; border:2px solid #3498db; border-radius:8px; background:transparent; color:#3498db; text-decoration:none; font-weight:600; font-size:14px; transition:all 0.3s; cursor:pointer; }
        .option-btn:hover { background:#3498db; color:white; transform:translateY(-2px); }
        .option-btn.recovery { border-color:#e74c3c; color:#e74c3c; }
        .option-btn.recovery:hover { background:#e74c3c; }
        .switcher-btn { background:none; border:none; color:#3498db; cursor:pointer; font-size:14px; text-decoration:underline; }
        
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper input { width: 100%; padding-right: 40px; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #586069; z-index: 10; }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header text-center">
            <div class="logo-container">
               <img src="<?php echo APP_LOGO; ?>" alt="Logo" class="logo" onerror="this.onerror=null; this.src='<?php echo APP_LOGO_FALLBACK; ?>';">
            </div>
            <h1><?php echo APP_NAME; ?></h1>
            <p class="text-muted">Sistema de Gestión Académica (SIGEB-G)</p>
        </div>
        
        <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>
        <?php if (isset($_GET['timeout'])): ?><div class="alert alert-warning"><i class="fas fa-clock"></i> La sesión ha expirado.</div><?php endif; ?>
        
        <div id="login-form" <?php echo $show_recovery ? 'class="hidden"' : ''; ?>>
            <form method="POST" class="login-form">
                <input type="hidden" name="login" value="1">
                <div class="form-group">
                    <label for="username">Usuario o Cédula</label>
                    <input type="text" id="username" name="username" required class="form-control" placeholder="Ingrese su usuario o cédula" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required class="form-control" placeholder="Ingrese su contraseña">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</button>
            </form>
            <div class="login-options">
                <a href="?action=recovery" class="option-btn recovery"><i class="fas fa-key"></i> ¿Olvidaste tu Contraseña?</a>
            </div>
        </div>
        
        <div id="recovery-form" <?php echo $show_recovery ? '' : 'class="hidden"'; ?>>
            <form method="POST" class="login-form">
                <input type="hidden" name="recovery" value="1">
                <div class="form-group"><label>Email</label><input type="email" name="email" required class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-key"></i> Recuperar Contraseña</button>
                <div class="form-switcher"><button type="button" class="switcher-btn" onclick="window.location.href='login.php'">← Volver al Login</button></div>
            </form>
        </div>
        
        <div class="login-footer text-center mt-4">
            <p class="text-muted small">República Bolivariana de Venezuela<br>Ministerio del Poder Popular para la Educación<br>Los Teques - Estado Miranda</p>
        </div>
    </div>

    <script>
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