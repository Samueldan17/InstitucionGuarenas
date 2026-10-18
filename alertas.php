<?php
// alertas.php - Módulo de Alertas de Rendimiento
require_once 'config.php';
require_once 'functions.php';
requireAuth();
// Permitir acceso a admin, docentes y secretarios
if (!in_array($_SESSION['usuario_rol'], ['admin', 'secretario', 'docente'])) {
    header('Location: dashboard.php');
    exit;
}
$db = getDBConnection();

// Año escolar activo
$anio_escolar_texto = getConfig($db, 'anio_escolar_activo', date('Y') . '-' . (date('Y') + 1));
$anio_escolar = (int)explode('-', $anio_escolar_texto)[0];

// Filtro por lapso
$filtro_lapso = (int)($_GET['lapso'] ?? 0);

// Construir consulta
$where = ["n.nota < 10", "n.es_recuperacion = 0", "n.anio_escolar = ?"];
$params = [$anio_escolar];

if ($filtro_lapso > 0) {
    $where[] = "n.lapso = ?";
    $params[] = $filtro_lapso;
}

$whereClause = "WHERE " . implode(" AND ", $where);

$query = "
    SELECT 
        e.cedula AS estudiante_cedula,
        CONCAT(e.apellidos, ', ', e.nombres) AS estudiante,
        g.nombre AS grado,
        s.letra AS seccion,
        m.nombre AS materia,
        n.lapso,
        n.nota,
        CONCAT(doc.nombres, ' ', doc.apellidos) AS docente
    FROM notas n
    JOIN estudiantes e ON n.estudiante_id = e.id
    JOIN seccion_materias sm ON n.seccion_materia_id = sm.id
    JOIN secciones s ON sm.seccion_id = s.id
    JOIN grados g ON s.grado_id = g.id
    JOIN materias m ON sm.materia_id = m.id
    LEFT JOIN usuarios doc ON sm.docente_id = doc.id
    $whereClause
    ORDER BY n.nota ASC, e.apellidos ASC, n.lapso ASC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$alertas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alertas de Rendimiento - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .nota-peligro { background: #f8d7da; color: #721c24; font-weight: bold; padding: 4px 12px; border-radius: 12px; font-size: 14px; }
        .badge-lapso { background: #e1e4e8; color: #24292e; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('alertas'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-exclamation-triangle"></i> Alertas de Rendimiento Escolar</h1>
            <div class="header-actions">
                <span class="badge badge-info" style="font-size: 14px; padding: 8px 15px;">
                    Año Escolar: <?php echo $anio_escolar_texto; ?>
                </span>
            </div>
        </header>
        <div class="content-container">
            
            <div class="card no-print" style="margin-bottom: 20px;">
                <div class="card-header"><h2><i class="fas fa-filter"></i> Filtrar Alertas</h2></div>
                <div class="card-body">
                    <form method="GET" class="form-grid">
                        <div class="form-group">
                            <label>Lapso</label>
                            <select name="lapso" class="form-control" onchange="this.form.submit()">
                                <option value="0">Todos los Lapsos</option>
                                <option value="1" <?php echo $filtro_lapso == 1 ? 'selected' : ''; ?>>1er Lapso</option>
                                <option value="2" <?php echo $filtro_lapso == 2 ? 'selected' : ''; ?>>2do Lapso</option>
                                <option value="3" <?php echo $filtro_lapso == 3 ? 'selected' : ''; ?>>3er Lapso</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Estudiantes con Notas Menores a 10 (<?php echo count($alertas); ?>)</h2>
                </div>
                <div class="card-body">
                    <?php if (count($alertas) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Estudiante</th>
                                    <th>Cédula</th>
                                    <th>Grado y Sección</th>
                                    <th>Materia</th>
                                    <th>Lapso</th>
                                    <th>Nota</th>
                                    <th>Docente Responsable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alertas as $a): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($a['estudiante']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($a['estudiante_cedula']); ?></td>
                                    <td><?php echo htmlspecialchars($a['grado'] . ' "' . $a['seccion'] . '"'); ?></td>
                                    <td><?php echo htmlspecialchars($a['materia']); ?></td>
                                    <td style="text-align: center;"><span class="badge-lapso"><?php echo $a['lapso']; ?>er Lapso</span></td>
                                    <td style="text-align: center;">
                                        <span class="nota-peligro"><?php echo number_format($a['nota'], 1); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['docente'] ?: 'Sin asignar'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle fa-4x" style="color: #27ae60; margin-bottom: 15px;"></i>
                        <h3>¡No hay alertas!</h3>
                        <p>Ningún estudiante tiene notas menores a 10 en el año escolar actual.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>