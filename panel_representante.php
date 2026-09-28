<?php
// panel_representante.php - Vista para representantes
require_once 'config.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'representante') {
    header('Location: dashboard.php');
    exit;
}

$db = getDBConnection();
$usuario_id = $_SESSION['usuario_id'];

// Obtener representante
$stmt = $db->prepare("SELECT id FROM representantes WHERE usuario_id = ? AND activo = 1");
$stmt->execute([$usuario_id]);
$rep = $stmt->fetch();
if (!$rep) die('Representante no encontrado.');
$representante_id = $rep['id'];

// Obtener estudiantes asociados
$stmt = $db->prepare("
    SELECT e.id, e.cedula, e.nombres, e.apellidos, g.nombre as grado, s.letra as seccion
    FROM representante_estudiante re
    JOIN estudiantes e ON re.estudiante_id = e.id
    JOIN secciones s ON e.seccion_id = s.id
    JOIN grados g ON s.grado_id = g.id
    WHERE re.representante_id = ? AND e.estado = 'activo'
");
$stmt->execute([$representante_id]);
$estudiantes = $stmt->fetchAll();

$boleta_data = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ver_boleta'])) {
    $estudiante_id = (int)$_POST['estudiante_id'];
    $lapso = (int)$_POST['lapso'];
    $anio = (int)$_POST['anio_escolar'];
    // Verificar que el representante tenga acceso a ese estudiante
    $check = $db->prepare("SELECT * FROM representante_estudiante WHERE representante_id = ? AND estudiante_id = ?");
    $check->execute([$representante_id, $estudiante_id]);
    if ($check->fetch()) {
        $boleta_data = getBoletaData($estudiante_id, $lapso, $anio);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Representante - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('representante'); ?>
    <main class="main-content">
        <header class="main-header"><h1>Panel de Representante</h1></header>
        <div class="content-container">
            <div class="card">
                <div class="card-header"><h2>Seleccionar Estudiante</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Estudiante</label>
                                <select name="estudiante_id" class="form-control" required>
                                    <?php foreach ($estudiantes as $est): ?>
                                    <option value="<?php echo $est['id']; ?>"><?php echo htmlspecialchars($est['apellidos'].', '.$est['nombres'].' - '.$est['grado'].' '.$est['seccion']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Lapso</label>
                                <select name="lapso" class="form-control">
                                    <option value="1">1er Lapso</option>
                                    <option value="2">2do Lapso</option>
                                    <option value="3">3er Lapso</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Año Escolar</label>
                                <select name="anio_escolar" class="form-control">
                                    <?php for ($y = date('Y')-1; $y <= date('Y'); $y++): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?>-<?php echo $y+1; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="ver_boleta" class="btn btn-primary">Ver Boleta</button>
                    </form>
                </div>
            </div>
            <?php if ($boleta_data): ?>
            <div class="boleta-container" id="boleta">
                <?php include 'templates/boleta_template.php'; ?>
                <div class="form-actions no-print">
                    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>