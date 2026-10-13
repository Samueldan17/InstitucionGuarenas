<?php
// panel_estudiante.php - Panel del estudiante (VERSIÓN 3.7 - con GET)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

if ($_SESSION['usuario_rol'] !== 'estudiante') {
    header('Location: dashboard.php');
    exit;
}

$db = getDBConnection();
$usuario_id = $_SESSION['usuario_id'];

$estudiante = getEstudiantePorUsuario($usuario_id);
if (!$estudiante) {
    die('<div style="padding: 40px; text-align: center; font-family: Arial;">
        <h2>⚠️ No se encontró un estudiante vinculado a su usuario</h2>
        <p>Contacte al administrador del sistema.</p>
        <a href="logout.php" style="color: #3498db;">Cerrar Sesión</a>
    </div>');
}

$estudiante_id = $estudiante['id'];

// Año escolar activo desde configuración
$anio_escolar_texto = getConfig($db, 'anio_escolar_activo', date('Y') . '-' . (date('Y') + 1));
$anio_seleccionado = (int)explode('-', $anio_escolar_texto)[0];

// Procesar consulta (ahora con GET)
$lapso = (int)($_GET['lapso'] ?? 0);
$mostrar_boleta = isset($_GET['ver_boleta']) || $lapso > 0;

$boleta_data = null;
$error_boleta = null;

if ($mostrar_boleta && $lapso > 0) {
    $boleta_data = getBoletaData($estudiante_id, $lapso, $anio_seleccionado);
    if (isset($boleta_data['error'])) {
        $error_boleta = $boleta_data['error'];
        $boleta_data = null;
    }
}

$promedio_general = 0;
if ($boleta_data && !empty($boleta_data['notas'])) {
    $suma = 0;
    $contador = 0;
    foreach ($boleta_data['notas'] as $n) {
        $def = obtenerNotaFinal($n['definitiva'], $n['recuperacion']);
        $suma += $def;
        $contador++;
    }
    $promedio_general = $contador > 0 ? round($suma / $contador, 2) : 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Panel - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .student-header {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white; padding: 25px; border-radius: 12px;
            margin-bottom: 20px; display: flex; align-items: center; gap: 20px;
        }
        .student-avatar {
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(255,255,255,0.2); display: flex;
            align-items: center; justify-content: center;
            font-size: 32px; font-weight: bold; flex-shrink: 0;
        }
        .student-info h2 { margin: 0 0 5px 0; color: white; }
        .student-info p { margin: 3px 0; opacity: 0.9; font-size: 14px; }
        .nota-cell { font-family: 'Consolas', monospace; font-weight: 600; text-align: center; }
        .nota-aprobado { color: #27ae60; }
        .nota-reprobado { color: #e74c3c; }
        .promedio-card {
            background: #f8f9fa; border-left: 4px solid #3498db;
            padding: 20px; border-radius: 8px; margin-bottom: 20px;
        }
        .promedio-card h3 { margin: 0 0 5px 0; color: #2c3e50; }
        .promedio-card .promedio-valor { font-size: 36px; font-weight: bold; color: #3498db; }
        @media print {
            .no-print { display: none !important; }
            .sidebar, .main-header { display: none !important; }
            .main-content { margin: 0 !important; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('estudiante'); ?>
    <main class="main-content">
        <header class="main-header no-print">
            <h1><i class="fas fa-user-graduate"></i> Mi Panel de Notas</h1>
        </header>
        <div class="content-container">

            <div class="student-header no-print">
                <div class="student-avatar">
                    <?php echo strtoupper(substr($estudiante['nombres'], 0, 1) . substr($estudiante['apellidos'], 0, 1)); ?>
                </div>
                <div class="student-info">
                    <h2><?php echo htmlspecialchars($estudiante['nombres'] . ' ' . $estudiante['apellidos']); ?></h2>
                    <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($estudiante['cedula']); ?></p>
                    <p><i class="fas fa-school"></i> <?php echo htmlspecialchars($estudiante['grado_nombre'] . ' "' . $estudiante['seccion_letra'] . '" (' . $estudiante['turno'] . ')'); ?></p>
                    <p><i class="fas fa-calendar"></i> Año Escolar: <strong><?php echo htmlspecialchars($anio_escolar_texto); ?></strong></p>
                </div>
            </div>

            <div class="card no-print">
                <div class="card-header"><h2><i class="fas fa-file-alt"></i> Consultar Notas</h2></div>
                <div class="card-body">
                    <form method="GET">
                        <input type="hidden" name="ver_boleta" value="1">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Lapso a consultar</label>
                                <select name="lapso" class="form-control">
                                    <option value="1" <?php echo $lapso == 1 ? 'selected' : ''; ?>>1er Lapso</option>
                                    <option value="2" <?php echo $lapso == 2 ? 'selected' : ''; ?>>2do Lapso</option>
                                    <option value="3" <?php echo $lapso == 3 ? 'selected' : ''; ?>>3er Lapso</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Consultar Notas
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($error_boleta): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($error_boleta); ?>
                </div>
            <?php endif; ?>

            <?php if ($boleta_data): ?>
                
                <div class="promedio-card no-print">
                    <h3><i class="fas fa-chart-line"></i> Promedio General del <?php echo $lapso; ?>er Lapso</h3>
                    <div class="promedio-valor <?php echo $promedio_general >= 10 ? 'nota-aprobado' : 'nota-reprobado'; ?>">
                        <?php echo number_format($promedio_general, 2); ?> pts
                    </div>
                    <small class="text-muted">
                        <?php echo $promedio_general >= 10 ? '✅ Aprobado' : '⚠️ En riesgo académico'; ?>
                    </small>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-table"></i> Notas del <?php echo $lapso; ?>er Lapso - <?php echo htmlspecialchars($anio_escolar_texto); ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th style="text-align: center;">1er Momento</th>
                                        <th style="text-align: center;">2do Momento</th>
                                        <th style="text-align: center;">3er Momento</th>
                                        <th style="text-align: center;">Definitiva</th>
                                        <th style="text-align: center;">Recuperación</th>
                                        <th style="text-align: center;">Nota Final</th>
                                        <th>Docente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($boleta_data['notas'] as $n): 
                                        $def = floatval($n['definitiva']);
                                        $rec = $n['recuperacion'] !== null ? floatval($n['recuperacion']) : null;
                                        $final = obtenerNotaFinal($def, $rec);
                                        $clase = $final >= 10 ? 'nota-aprobado' : 'nota-reprobado';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($n['materia_nombre']); ?></strong></td>
                                        <td class="nota-cell"><?php echo $n['evaluacion1'] !== null ? number_format($n['evaluacion1'], 1) : '—'; ?></td>
                                        <td class="nota-cell"><?php echo $n['evaluacion2'] !== null ? number_format($n['evaluacion2'], 1) : '—'; ?></td>
                                        <td class="nota-cell"><?php echo $n['evaluacion3'] !== null ? number_format($n['evaluacion3'], 1) : '—'; ?></td>
                                        <td class="nota-cell"><?php echo number_format($def, 1); ?></td>
                                        <td class="nota-cell"><?php echo $rec !== null ? number_format($rec, 1) : '—'; ?></td>
                                        <td class="nota-cell <?php echo $clase; ?>"><?php echo number_format($final, 1); ?></td>
                                        <td><small><?php echo htmlspecialchars($n['docente_nombre'] ?: 'Sin asignar'); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="no-print" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Imprimir Notas
                    </button>
                    <a href="boletas.php?estudiante_id=<?php echo $estudiante_id; ?>&lapso=<?php echo $lapso; ?>&anio_escolar=<?php echo $anio_seleccionado; ?>" target="_blank" class="btn btn-success">
                        <i class="fas fa-file-pdf"></i> Ver Boletín Oficial
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>