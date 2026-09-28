<?php
require_once 'config.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'admin') { header('Location: dashboard.php'); exit; }
$db = getDBConnection();
$id = (int)$_GET['id'];
if (!$id) { header('Location: usuarios.php'); exit; }

$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch();
if (!$usuario) { header('Location: usuarios.php'); exit; }

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = formatearCedulaParaBD($_POST['cedula']);
    $nombres = formatearNombrePropio($_POST['nombres']);
    $apellidos = formatearNombrePropio($_POST['apellidos']);
    $email = sanitize($_POST['email']);
    $telefono = sanitize($_POST['telefono']);
    $rol = $_POST['rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    $valid_ced = validarCedulaVenezolana($cedula);
    if ($valid_ced !== true) $error = $valid_ced;
    elseif (empty($nombres) || empty($apellidos)) $error = "Nombres y apellidos obligatorios.";
    else {
        $check = $db->prepare("SELECT id FROM usuarios WHERE (cedula = ? OR email = ?) AND id != ?");
        $check->execute([$cedula, $email, $id]);
        if ($check->fetch()) $error = "Ya existe otro usuario con la misma cédula o email.";
        else {
            $stmt = $db->prepare("UPDATE usuarios SET cedula=?, nombres=?, apellidos=?, email=?, telefono=?, rol=?, activo=? WHERE id=?");
            $stmt->execute([$cedula, $nombres, $apellidos, $email, $telefono, $rol, $activo, $id]);
            $message = "Usuario actualizado correctamente.";
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $usuario = $stmt->fetch();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('usuarios'); ?>
    <main class="main-content">
        <header class="main-header"><h1><i class="fas fa-user-edit"></i> Editar Usuario</h1></header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <div class="card">
                <div class="card-header"><h2>Datos del Usuario</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group"><label>Cédula</label><input type="text" name="cedula" class="form-control cedula" value="<?php echo htmlspecialchars($usuario['cedula']); ?>" required></div>
                            <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" value="<?php echo htmlspecialchars($usuario['nombres']); ?>" required></div>
                            <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" value="<?php echo htmlspecialchars($usuario['apellidos']); ?>" required></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usuario['email']); ?>" required></div>
                            <div class="form-group"><label>Teléfono</label><input type="text" name="telefono" class="form-control telefono" value="<?php echo htmlspecialchars($usuario['telefono']); ?>"></div>
                            <div class="form-group"><label>Rol</label>
                                <select name="rol" class="form-control">
                                    <option value="admin" <?php echo $usuario['rol']=='admin'?'selected':''; ?>>Administrador</option>
                                    <option value="docente" <?php echo $usuario['rol']=='docente'?'selected':''; ?>>Docente</option>
                                    <option value="secretario" <?php echo $usuario['rol']=='secretario'?'selected':''; ?>>Secretario</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><input type="checkbox" name="activo" value="1" <?php echo $usuario['activo'] ? 'checked' : ''; ?>> Usuario activo</label>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                        </div>
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