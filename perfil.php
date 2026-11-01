<?php
// perfil.php - Mi Perfil (VERSIÓN 3.9 - CSS del ojito corregido)
require_once 'config.php';
require_once 'functions.php';
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
            elseif ($telefono && !validarTelefonoVenezolano($telefono)) $error = "Teléfono inválido.";
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
            
            if (!password_verify($pass_actual, $user['password'])) {
                $error = "Contraseña actual incorrecta.";
            } elseif (($pass_valid = validarPasswordSegura($pass_nueva)) !== true) {
                $error = is_string($pass_valid) ? $pass_valid : "Contraseña inválida.";
            } elseif ($pass_nueva !== $pass_confirm) {
                $error = "Las nuevas contraseñas no coinciden.";
            } else {
                $hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE usuarios SET password=? WHERE id=?");
                $stmt->execute([$hash, $user_id]);
                $message = "Contraseña actualizada.";
                registrarLog('cambiar_password', 'usuarios', $user_id);
            }
        }
    }
}

$stmt = $db->prepare("SELECT nombres, apellidos, email, telefono, rol, cedula FROM usuarios WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        /* CSS PERFECTO PARA EL OJITO EN EL PERFIL */
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper input { width: 100%; padding-right: 40px; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #586069; z-index: 10; }
    </style>
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
                            <div class="password-wrapper">
                                <input type="password" name="pass_actual" id="pass0" required class="form-control">
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('pass0', this)"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Nueva contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" name="pass_nueva" id="pass1" required minlength="8" class="form-control" placeholder="Mín. 8 caracteres">
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('pass1', this)"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirmar nueva contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" name="pass_confirm" id="pass2" required minlength="8" class="form-control">
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('pass2', this)"></i>
                            </div>
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