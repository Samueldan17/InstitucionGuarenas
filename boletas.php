<?php
// boletas.php - Generación de boletines (VERSIÓN 3.5)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

$db = getDBConnection();

// Datos desde POST (panel estudiante) o GET (admin)
$estudiante_id = (int)($_POST['estudiante_id'] ?? $_GET['estudiante_id'] ?? 0);
$lapso = (int)($_POST['lapso'] ?? $_GET['lapso'] ?? 1);
$anio_escolar = (int)($_POST['anio_escolar'] ?? $_GET['anio_escolar'] ?? date('Y'));

// Si viene del panel estudiante, verificar que sea su propio estudiante
if ($_SESSION['usuario_rol'] === 'estudiante') {
    $estudiante_actual = getEstudiantePorUsuario($_SESSION['usuario_id']);
    if (!$estudiante_actual || $estudiante_actual['id'] != $estudiante_id) {
        header('Location: panel_estudiante.php');
        exit;
    }
}

// Si es admin y no hay estudiante seleccionado, mostrar selector
$mostrar_selector = ($estudiante_id == 0 && in_array($_SESSION['usuario_rol'], ['admin', 'docente', 'secretario']));

// Listas para el selector del admin
$estudiantes = [];
$secciones_admin = [];
$anios_disponibles = [];
if ($mostrar_selector) {
    $estudiantes = $db->query("
        SELECT e.id, e.cedula, e.nombres, e.apellidos, g.nombre as grado, s.letra as seccion
        FROM estudiantes e
        LEFT JOIN secciones s ON e.seccion_id = s.id
        LEFT JOIN grados g ON s.grado_id = g.id
        WHERE e.estado = 'activo'
        ORDER BY e.apellidos, e.nombres
    ")->fetchAll();
    $anios_disponibles = $db->query("SELECT DISTINCT anio_escolar FROM notas ORDER BY anio_escolar DESC")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($anios_disponibles)) $anios_disponibles = [date('Y')];
}

// Generar boleta si hay estudiante seleccionado
$boleta_data = null;
$error_boleta = null;
if ($estudiante_id > 0) {
    $boleta_data = getBoletaData($estudiante_id, $lapso, $anio_escolar);
    if (isset($boleta_data['error'])) {
        $error_boleta = $boleta_data['error'];
        $boleta_data = null;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boletín - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar($_SESSION['usuario_rol'] === 'estudiante' ? 'estudiante' : 'boletas'); ?>
    <main class="main-content">
        <header class="main-header no-print">
            <h1><i class="fas fa-file-alt"></i> Boletín Informativo</h1>
            <div class="header-actions no-print">
                <?php if ($_SESSION['usuario_rol'] === 'estudiante'): ?>
                    <a href="panel_estudiante.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
                <?php else: ?>
                    <a href="boletas.php" class="btn btn-secondary"><i class="fas fa-list"></i> Ver Todos</a>
                <?php endif; ?>
            </div>
        </header>
        <div class="content-container">

            <?php if ($mostrar_selector): ?>
            <!-- Selector para admin -->
            <div class="card no-print">
                <div class="card-header"><h2><i class="fas fa-search"></i> Seleccionar Estudiante</h2></div>
                <div class="card-body">
                    <form method="GET">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Estudiante</label>
                                <select name="estudiante_id" class="form-control" required>
                                    <option value="">Seleccione un estudiante...</option>
                                    <?php foreach ($estudiantes as $est): ?>
                                        <option value="<?php echo $est['id']; ?>">
                                            <?php echo htmlspecialchars($est['apellidos'] . ', ' . $est['nombres'] . ' - ' . $est['grado'] . ' "' . $est['seccion'] . '"'); ?>
                                        </option>
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
                                    <?php foreach ($anios_disponibles as $a): ?>
                                        <option value="<?php echo $a; ?>"><?php echo $a; ?>-<?php echo $a + 1; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generar Boletín</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error_boleta): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($error_boleta); ?>
                </div>
            <?php endif; ?>

            <?php if ($boleta_data): ?>
                <!-- Botones de acción -->
                <div class="no-print" style="margin-bottom: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>

                <!-- Boleta -->
                <div class="boleta-container" id="boleta">
                    <?php include __DIR__ . '/templates/boleta_template.php'; ?>
                </div>
            <?php endif; ?>

            <?php if ($mostrar_selector && !$boleta_data): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt fa-4x"></i>
                    <h3>Seleccione un estudiante para generar su boletín</h3>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>