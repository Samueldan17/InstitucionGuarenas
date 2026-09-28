<?php
// configuracion.php - Configuración del sistema (con logo dinámico)
require_once 'config.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'admin') { header('Location: dashboard.php'); exit; }
$db = getDBConnection();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_info'])) {
    // Placeholder para futura tabla de configuración
    $message = "Configuración guardada (demo).";
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Configuración del Sistema</title><link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('configuracion'); ?>
    <main class="main-content">
        <header class="main-header"><h1>Configuración del Sistema</h1></header>
        <div class="content-container">
            <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <div class="card"><div class="card-header"><h2>Información General</h2></div><div class="card-body">
                <form method="POST">
                    <div class="form-group"><label>Nombre de la Institución</label><input type="text" class="form-control" value="<?php echo APP_NAME; ?>" disabled></div>
                    <div class="form-group"><label>Versión del Sistema</label><input type="text" class="form-control" value="<?php echo APP_VERSION; ?>" disabled></div>
                    <div class="form-group"><label>Ruta Base</label><input type="text" class="form-control" value="<?php echo APP_URL; ?>" disabled></div>
                    <div class="form-group"><label>Logo</label>
                    <img src="<?php echo APP_LOGO; ?>" alt="Logo" style="max-width:100px; display:block; margin-top:5px;" onerror="this.onerror=null; this.src='<?php echo APP_LOGO_FALLBACK; ?>';"></div>
                    <button type="submit" name="guardar_info" class="btn btn-primary" disabled>Guardar (Demo)</button>
                </form>
            </div></div>
        </div>
    </main>
</div>
</body>
</html>