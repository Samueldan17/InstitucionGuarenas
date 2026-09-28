<?php
require_once 'config.php';

$error = '';
$success = '';
$valid_token = false;
$token = $_GET['token'] ?? '';

if ($token) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, token_expiracion FROM usuarios WHERE token_recuperacion = ? AND activo = 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user && strtotime($user['token_expiracion']) > time()) {
        $valid_token = true;
        $user_id = $user['id'];
    } else {
        $error = 'El enlace de recuperación es inválido o ha expirado.';
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
        $success = '¡Contraseña restablecida exitosamente!';
        $valid_token = false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Restablecer Contraseña</title><link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
<body class="login-page">
<div class="login-container">
    <div class="login-header"><h1>Restablecer Contraseña</h1></div>
    <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?> <a href="login.php">Iniciar sesión</a></div><?php endif; ?>
    <?php if($valid_token): ?>
    <form method="POST">
        <div class="form-group"><label>Nueva contraseña</label><input type="password" name="password" required minlength="6" class="form-control"></div>
        <div class="form-group"><label>Confirmar contraseña</label><input type="password" name="confirm_password" required class="form-control"></div>
        <button type="submit" class="btn btn-primary btn-block">Restablecer</button>
    </form>
    <?php elseif(!$success): ?>
    <p>No se pudo procesar la solicitud. <a href="login.php">Volver al login</a></p>
    <?php endif; ?>
</div>
</body>
</html>