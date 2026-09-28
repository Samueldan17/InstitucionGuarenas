<?php
// panel_estudiante.php - Vista para estudiantes
require_once 'config.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'estudiante') {
    header('Location: dashboard.php');
    exit;
}

$db = getDBConnection();
$usuario_id = $_SESSION['usuario_id'];

// Obtener el estudiante asociado al usuario
$stmt = $db->prepare("SELECT id FROM estudiantes WHERE usuario_id = ? AND estado = 'activo'");
$stmt->execute([$usuario_id]);
$estudiante = $stmt->fetch();
if (!$estudiante) {
    die('No se encontró un estudiante asociado a su usuario.');
}
$estudiante_id = $estudiante['id'];

// Obtener lapsos disponibles y años escolares
$lapsos = [1,2,3];
$anios = $db->query("SELECT DISTINCT anio_escolar FROM notas WHERE estudiante_id = $estudiante_id ORDER BY anio_escolar DESC")->fetchAll(PDO::FETCH_COLUMN);

// Si hay POST, generar boleta
$boleta_data = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ver_boleta'])) {
    $lapso = (int)$_POST['lapso'];
    $anio = (int)$_POST['anio_escolar'];
    $boleta_data = getBoletaData($estudiante_id, $lapso, $anio);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Panel - Estudiante</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('estudiante'); ?>
    <main class="main-content">
        <header class="main-header"><h1>Mi Panel de Notas</h1></header>
        <div class="content-container">
            <div class="card">
                <div class="card-header"><h2>Consultar Boletas</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Lapso</label>
                                <select name="lapso" class="form-control">
                                    <?php foreach ($lapsos as $l): ?>
                                    <option value="<?php echo $l; ?>"><?php echo $l; ?>er Lapso</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Año Escolar</label>
                                <select name="anio_escolar" class="form-control">
                                    <?php foreach ($anios as $a): ?>
                                    <option value="<?php echo $a; ?>"><?php echo $a; ?>-<?php echo $a+1; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="ver_boleta" class="btn btn-primary">Ver Boleta</button>
                    </form>
                </div>
            </div>

            <?php if ($boleta_data): ?>
            <div class="boleta-container" id="boleta">
                <!-- Aquí se incluye el mismo contenido de boleta que en boletas.php -->
                <?php include 'templates/boleta_template.php'; ?>
                <div class="form-actions no-print">
                    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>