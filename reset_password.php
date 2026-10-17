<?php
// reset_password.php - Restablecimiento (VERSIÓN 3.6 - Ciberseguridad)
require_once 'config.php';
require_once 'functions.php';

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
        $error = 'El enlace de recuperación es inválido o ha expirado.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (($pass_valid = validarPasswordSegura($password)) !== true) {
        $error = is_string($pass_valid) ? $pass_valid : 'Contraseña inválida';
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
    <title>Restablecer Contraseña - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .user-info-box { background: #eaf4fc; border-left: 4px solid #3498db; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header text-center">
            <div class="logo-container"><img src="<?php echo APP_LOGO; ?>" alt="Logo" class="logo" onerror="this.onerror=null; this.src='<?php echo APP_LOGO_FALLBACK; ?>';"></div>
            <h1><?php echo APP_NAME; ?></h1>
            <p class="text-muted">Sistema de Gestión Académica (SIGEB-G)</p>
        </div>

        <div class="card" style="margin-top: 20px;">
            <div class="card-header" style="text-align: center;"><h2><i class="fas fa-key"></i> Restablecer Contraseña</h2></div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
                    <div style="text-align: center; margin-top: 20px;"><a href="login.php" class="btn btn-primary">Ir al Inicio de Sesión</a></div>
                <?php endif; ?>

                <?php if ($valid_token): ?>
                    <?php if ($nombre_usuario): ?><div class="user-info-box"><i class="fas fa-user-circle"></i> Restableciendo para: <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></div><?php endif; ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>Nueva contraseña</label>
                            <input type="password" name="password" id="password" required minlength="8" class="form-control" placeholder="Mín. 8 caracteres, mayúscula, número y símbolo">
                            <small class="form-text text-muted">Debe contener mayúscula, minúscula, número y símbolo.</small>
                        </div>
                        <div class="form-group"><label>Confirmar contraseña</label><input type="password" name="confirm_password" required class="form-control" minlength="8"></div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Restablecer Contraseña</button>
                    </form>
                    <div style="text-align: center; margin-top: 15px;"><a href="login.php" style="color: #586069; font-size: 13px;"><i class="fas fa-arrow-left"></i> Volver al inicio de sesión</a></div>
                <?php elseif (!$success): ?>
                    <div style="text-align: center; padding: 20px 0;">
                        <i class="fas fa-times-circle" style="font-size: 48px; color: #e74c3c;"></i>
                        <h3 style="margin-top: 15px;">Enlace inválido o expirado</h3>
                        <p class="text-muted">Solicita un nuevo enlace de recuperación desde el login.</p>
                        <a href="login.php" class="btn btn-primary" style="margin-top: 15px;">Volver al Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>