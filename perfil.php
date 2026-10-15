<?php
// perfil.php - Mi Perfil (con CSRF) - VERSIÓN 3.2
require_once 'config.php';
requireAuth();
$db = getDBConnection();
$user_id = $_SESSION['usuario_id'];
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        if (isset($_POST['actualizar_perfil'])) {
            $email = sanitize($_POST['email']);
            $telefono = sanitize($_POST['telefono']);
            if (!validarEmail($email)) $error = "Email inválido.";
            elseif ($telefono && !validarTelefonoVenezolano($telefono)) $error = "Teléfono inválido. Use el formato 0412-1234567";
            else {
                $stmt = $db->prepare("UPDATE usuarios SET email=?, telefono=? WHERE id=?");
                $stmt->execute([$email, $telefono, $user_id]);
                $message = "Perfil actualizado correctamente.";
                registrarLog('actualizar_perfil', 'usuarios', $user_id);
            }
        } elseif (isset($_POST['cambiar_password'])) {
            $pass_actual = $_POST['pass_actual'];
            $pass_nueva = $_POST['pass_nueva'];
            $pass_confirm = $_POST['pass_confirm'];
            $stmt = $db->prepare("SELECT password FROM usuarios WHERE id=?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            if (!password_verify($pass_actual, $user['password'])) $error = "Contraseña actual incorrecta.";
            elseif ($pass_nueva !== $pass_confirm) $error = "Las nuevas contraseñas no coinciden.";
            elseif (strlen($pass_nueva) < 6) $error = "La nueva contraseña debe tener al menos 6 caracteres.";
            else {
                $hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE usuarios SET password=? WHERE id=?");
                $stmt->execute([$hash, $user_id]);
                $message = "Contraseña actualizada.";
                registrarLog('cambiar_password', 'usuarios', $user_id);
            }
        }
    }
}

// Consulta corregida: obtiene los datos del usuario actual
$stmt = $db->prepare("SELECT nombres, apellidos, email, telefono, rol, cedula FROM usuarios WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Si por alguna razón no encuentra al usuario, redirigir
if (!$user) {
    header('Location: logout.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('perfil'); ?>
    <main class="main-content">
        <header class="main-header"><h1><i class="fas fa-user-circle"></i> Mi Perfil</h1></header>
        <div class="content-container">
            <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="card">
                <div class="card-header"><h2>Datos Personales</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-group">
                            <label>Cédula</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['cedula']); ?>" readonly class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Nombres y Apellidos</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['nombres'].' '.$user['apellidos']); ?>" readonly class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" value="<?php echo htmlspecialchars($user['telefono']); ?>" class="form-control telefono" placeholder="0412-1234567">
                        </div>
                        
                        <div class="form-group">
                            <label>Rol</label>
                            <input type="text" value="<?php echo ucfirst($user['rol']); ?>" readonly class="form-control">
                        </div>
                        
                        <button type="submit" name="actualizar_perfil" class="btn btn-primary">
                            <i class="fas fa-save"></i> Actualizar Datos
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h2>Cambiar Contraseña</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-group">
                            <label>Contraseña actual</label>
                            <input type="password" name="pass_actual" required class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Nueva contraseña</label>
                            <input type="password" name="pass_nueva" required minlength="6" class="form-control" placeholder="Mínimo 6 caracteres">
                        </div>
                        
                        <div class="form-group">
                            <label>Confirmar nueva contraseña</label>
                            <input type="password" name="pass_confirm" required class="form-control">
                        </div>
                        
                        <button type="submit" name="cambiar_password" class="btn btn-warning">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
</body>
</html>