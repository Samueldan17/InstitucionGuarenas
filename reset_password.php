<?php
// reset_password.php - Restablecimiento de contraseña (VERSIÓN 3.0)
require_once 'config.php';

$error = '';
$success = '';
$valid_token = false;
$token = $_GET['token'] ?? '';
$nombre_usuario = '';

if ($token) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, nombres, apellidos, token_expiracion FROM usuarios WHERE token_recuperacion = ? AND activo = 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user && strtotime($user['token_expiracion']) > time()) {
        $valid_token = true;
        $user_id = $user['id'];
        $nombre_usuario = $user['nombres'] . ' ' . $user['apellidos'];
    } else {
        $error = 'El enlace de recuperación es inválido o ha expirado. Solicite uno nuevo.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE usuarios SET password = ?, token_recuperacion = NULL, token_expiracion = NULL WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        registrarLog('reset_password', 'usuarios', $user_id, "Contraseña restablecida");
        $success = '¡Contraseña restablecida exitosamente!';
        $valid_token = false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .password-strength {
            height: 6px;
            background: #e1e4e8;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
        }
        .strength-weak { background: #e74c3c; width: 33%; }
        .strength-medium { background: #f39c12; width: 66%; }
        .strength-strong { background: #27ae60; width: 100%; }
        .user-info-box {
            background: #eaf4fc;
            border-left: 4px solid #3498db;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .user-info-box strong {
            color: #2c3e50;
        }
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

        <div class="card" style="margin-top: 20px;">
            <div class="card-header" style="text-align: center;">
                <h2><i class="fas fa-key"></i> Restablecer Contraseña</h2>
            </div>
            <div class="card-body">

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="login.php" class="btn btn-primary" style="display: inline-block;">
                            <i class="fas fa-sign-in-alt"></i> Ir al Inicio de Sesión
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($valid_token): ?>
                    <?php if ($nombre_usuario): ?>
                        <div class="user-info-box">
                            <i class="fas fa-user-circle"></i> Restableciendo contraseña para: <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>Nueva contraseña</label>
                            <input type="password" name="password" id="password" required minlength="6" 
                                   class="form-control" placeholder="Mínimo 6 caracteres"
                                   oninput="checkStrength(this.value)">
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <small class="text-muted" id="strengthText">Ingrese una contraseña</small>
                        </div>
                        <div class="form-group">
                            <label>Confirmar contraseña</label>
                            <input type="password" name="confirm_password" id="confirm" required 
                                   class="form-control" placeholder="Repita la contraseña"
                                   oninput="checkMatch()">
                            <small class="text-muted" id="matchText"></small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" id="btnSubmit" disabled>
                            <i class="fas fa-save"></i> Restablecer Contraseña
                        </button>
                    </form>

                    <div style="text-align: center; margin-top: 15px;">
                        <a href="login.php" style="color: #586069; font-size: 13px;">
                            <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                        </a>
                    </div>

                <?php elseif (!$success): ?>
                    <div style="text-align: center; padding: 20px 0;">
                        <i class="fas fa-times-circle" style="font-size: 48px; color: #e74c3c;"></i>
                        <h3 style="margin-top: 15px;">Enlace inválido o expirado</h3>
                        <p class="text-muted">Solicita un nuevo enlace de recuperación desde el login.</p>
                        <a href="login.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-sign-in-alt"></i> Volver al Login
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <div class="login-footer text-center mt-4">
            <p class="text-muted small">República Bolivariana de Venezuela<br>Ministerio del Poder Popular para la Educación<br>Los Teques - Estado Miranda</p>
        </div>
    </div>

    <script>
        function checkStrength(pass) {
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            bar.className = 'password-strength-bar';
            
            if (pass.length === 0) {
                text.textContent = 'Ingrese una contraseña';
                bar.style.width = '0%';
                return;
            }
            
            let strength = 0;
            if (pass.length >= 6) strength++;
            if (pass.length >= 10) strength++;
            if (/[A-Z]/.test(pass)) strength++;
            if (/[0-9]/.test(pass)) strength++;
            if (/[^A-Za-z0-9]/.test(pass)) strength++;
            
            if (strength <= 2) {
                bar.classList.add('strength-weak');
                text.textContent = 'Contraseña débil';
            } else if (strength <= 4) {
                bar.classList.add('strength-medium');
                text.textContent = 'Contraseña media';
            } else {
                bar.classList.add('strength-strong');
                text.textContent = 'Contraseña fuerte';
            }
            checkMatch();
        }
        
        function checkMatch() {
            const pass = document.getElementById('password').value;
            const confirm = document.getElementById('confirm').value;
            const matchText = document.getElementById('matchText');
            const btnSubmit = document.getElementById('btnSubmit');
            
            if (confirm.length === 0) {
                matchText.textContent = '';
                btnSubmit.disabled = true;
                return;
            }
            
            if (pass === confirm && pass.length >= 6) {
                matchText.innerHTML = '<i class="fas fa-check-circle" style="color:#27ae60;"></i> Las contraseñas coinciden';
                btnSubmit.disabled = false;
            } else if (pass === confirm) {
                matchText.innerHTML = '<i class="fas fa-info-circle" style="color:#f39c12;"></i> Mínimo 6 caracteres';
                btnSubmit.disabled = true;
            } else {
                matchText.innerHTML = '<i class="fas fa-times-circle" style="color:#e74c3c;"></i> Las contraseñas no coinciden';
                btnSubmit.disabled = true;
            }
        }
    </script>
</body>
</html>