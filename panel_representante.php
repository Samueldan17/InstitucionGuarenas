<?php
// panel_representante.php - Panel del representante (VERSIÓN 3.0)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

if ($_SESSION['usuario_rol'] !== 'representante') {
    header('Location: dashboard.php');
    exit;
}

$db = getDBConnection();
$usuario_id = $_SESSION['usuario_id'];

// Obtener datos del representante
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$representante = $stmt->fetch();

// Obtener representados
$representados = getRepresentados($usuario_id);

// Si no hay representados, mostrar mensaje
if (empty($representados)) {
    // Intentar buscar por coincidencia de cédula en estudiantes
    $stmt = $db->prepare("SELECT e.* FROM estudiantes e WHERE e.cedula_representante = ? AND e.estado = 'activo'");
    $stmt->execute([$representante['cedula']]);
    $representados_directos = $stmt->fetchAll();
    
    if (!empty($representados_directos)) {
        // Auto-vincular
        foreach ($representados_directos as $est) {
            vincularRepresentanteEstudiante($usuario_id, $est['id'], $est['parentesco'] ?: 'Representante', 1);
        }
        $representados = getRepresentados($usuario_id);
    }
}

// Estudiante seleccionado
$estudiante_id = (int)($_GET['estudiante_id'] ?? 0);
if ($estudiante_id == 0 && !empty($representados)) {
    $estudiante_id = $representados[0]['id'];
}

// Verificar que el estudiante esté en la lista de representados
$estudiante_actual = null;
foreach ($representados as $rep) {
    if ($rep['id'] == $estudiante_id) {
        $estudiante_actual = $rep;
        break;
    }
}

// Año escolar activo
$anio_escolar_texto = getConfig($db, 'anio_escolar_activo', date('Y') . '-' . (date('Y') + 1));
$anio_seleccionado = (int)explode('-', $anio_escolar_texto)[0];

// Consultar notas
$lapso = (int)($_GET['lapso'] ?? 0);
$mostrar_boleta = isset($_GET['ver_boleta']) || $lapso > 0;

$boleta_data = null;
$error_boleta = null;

if ($mostrar_boleta && $lapso > 0 && $estudiante_actual) {
    $boleta_data = getBoletaData($estudiante_id, $lapso, $anio_seleccionado);
    if (isset($boleta_data['error'])) {
        $error_boleta = $boleta_data['error'];
        $boleta_data = null;
    }
}

$promedio_general = 0;
if ($boleta_data && !empty($boleta_data['notas'])) {
    $suma = 0; $contador = 0;
    foreach ($boleta_data['notas'] as $n) {
        $def = obtenerNotaFinal($n['definitiva'], $n['recuperacion']);
        $suma += $def; $contador++;
    }
    $promedio_general = $contador > 0 ? round($suma / $contador, 2) : 0;
}

$iniciales_rep = strtoupper(substr($representante['nombres'], 0, 1) . substr($representante['apellidos'], 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Representante - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .rep-header {
            background: linear-gradient(135deg, #27ae60, #2c3e50);
            color: white; padding: 25px; border-radius: 12px;
            margin-bottom: 20px; display: flex; align-items: center; gap: 20px;
        }
        .rep-avatar {
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(255,255,255,0.2); display: flex;
            align-items: center; justify-content: center;
            font-size: 32px; font-weight: bold; flex-shrink: 0;
        }
        .rep-header h2 { margin: 0 0 5px 0; color: white; }
        .rep-header p { margin: 3px 0; opacity: 0.9; font-size: 14px; }
        .estudiante-card {
            display: flex; align-items: center; gap: 15px; padding: 15px;
            border: 2px solid #e1e4e8; border-radius: 8px; cursor: pointer;
            transition: all 0.2s; text-decoration: none; color: inherit;
        }
        .estudiante-card:hover { border-color: #3498db; background: #f8f9fa; }
        .estudiante-card.active { border-color: #3498db; background: #eaf4fc; }
        .estudiante-avatar-mini {
            width: 50px; height: 50px; border-radius: 50%;
            background: #3498db; color: white; display: flex;
            align-items: center; justify-content: center;
            font-weight: bold; flex-shrink: 0;
        }
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
    <?php echo getSidebar('representante'); ?>
    <main class="main-content">
        <header class="main-header no-print">
            <h1><i class="fas fa-users"></i> Panel del Representante</h1>
        </header>
        <div class="content-container">

            <div class="rep-header no-print">
                <div class="rep-avatar"><?php echo $iniciales_rep; ?></div>
                <div class="rep-header-content" style="flex:1;">
                    <h2><?php echo htmlspecialchars($representante['nombres'] . ' ' . $representante['apellidos']); ?></h2>
                    <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($representante['cedula']); ?></p>
                    <p><i class="fas fa-users"></i> <?php echo count($representados); ?> representado<?php echo count($representados) != 1 ? 's' : ''; ?></p>
                </div>
            </div>

            <?php if (empty($representados)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i> No se encontraron estudiantes vinculados a su usuario. 
                    Contacte al administrador del sistema.
                </div>
            <?php else: ?>

                <!-- Selector de representado -->
                <div class="card no-print" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-user-friends"></i> Seleccione un Representado</h2></div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">
                            <?php foreach ($representados as $rep): ?>
                                <?php 
                                    $inic = strtoupper(substr($rep['nombres'], 0, 1) . substr($rep['apellidos'], 0, 1));
                                    $es_activo = ($rep['id'] == $estudiante_id);
                                ?>
                                <a href="?estudiante_id=<?php echo $rep['id']; ?>" class="estudiante-card <?php echo $es_activo ? 'active' : ''; ?>">
                                    <div class="estudiante-avatar-mini"><?php echo $inic; ?></div>
                                    <div style="flex:1;">
                                        <strong><?php echo htmlspecialchars($rep['nombres'] . ' ' . $rep['apellidos']); ?></strong>
                                        <p style="margin: 3px 0 0 0; font-size: 13px; color: #586069;">
                                            <?php echo htmlspecialchars($rep['grado_nombre'] . ' "' . $rep['seccion_letra'] . '"'); ?> 
                                            - <?php echo htmlspecialchars($rep['parentesco']); ?>
                                        </p>
                                    </div>
                                    <?php if ($es_activo): ?>
                                        <i class="fas fa-check-circle" style="color: #3498db; font-size: 20px;"></i>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($estudiante_actual): ?>

                    <!-- Selector de lapso -->
                    <div class="card no-print">
                        <div class="card-header"><h2><i class="fas fa-file-alt"></i> Consultar Notas de <?php echo htmlspecialchars($estudiante_actual['nombres']); ?></h2></div>
                        <div class="card-body">
                            <form method="GET">
                                <input type="hidden" name="estudiante_id" value="<?php echo $estudiante_id; ?>">
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
                        <div class="alert alert-warning"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($error_boleta); ?></div>
                    <?php endif; ?>

                    <?php if ($boleta_data): ?>
                        
                        <div class="promedio-card no-print">
                            <h3><i class="fas fa-chart-line"></i> Promedio General del <?php echo $lapso; ?>er Lapso</h3>
                            <div class="promedio-valor <?php echo $promedio_general >= 10 ? 'nota-aprobado' : 'nota-reprobado'; ?>">
                                <?php echo number_format($promedio_general, 2); ?> pts
                            </div>
                            <small class="text-muted"><?php echo $promedio_general >= 10 ? '✅ Aprobado' : '⚠️ En riesgo académico'; ?></small>
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
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="no-print" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                            <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Imprimir</button>
                            <a href="boletas.php?estudiante_id=<?php echo $estudiante_id; ?>&lapso=<?php echo $lapso; ?>&anio_escolar=<?php echo $anio_seleccionado; ?>" target="_blank" class="btn btn-success">
                                <i class="fas fa-file-pdf"></i> Ver Boletín Oficial
                            </a>
                        </div>
                    <?php endif; ?> 

                <?php endif; ?>

            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>