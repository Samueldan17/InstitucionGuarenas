<?php
require_once 'config.php';
requireAuth();

// Redirigir según rol
if ($_SESSION['usuario_rol'] === 'estudiante') {
    header('Location: panel_estudiante.php');
    exit;
}
if ($_SESSION['usuario_rol'] === 'representante') {
    header('Location: panel_representante.php');
    exit;
}

$db = getDBConnection();

// Estadísticas generales
$totalEstudiantes = $db->query("SELECT COUNT(*) FROM estudiantes WHERE estado = 'activo'")->fetchColumn();
$totalSecciones = $db->query("SELECT COUNT(*) FROM secciones WHERE activo = 1")->fetchColumn();
$totalMaterias = $db->query("SELECT COUNT(*) FROM materias WHERE activo = 1")->fetchColumn();
$totalDocentes = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol IN ('docente','secretario') AND activo = 1")->fetchColumn();

// Promedio general (nueva estructura: columna `nota`)
$promedioGeneral = $db->query("
    SELECT AVG(nota) FROM notas 
    WHERE anio_escolar = YEAR(CURDATE()) AND nota > 0
")->fetchColumn();
$promedioGeneral = $promedioGeneral ? round($promedioGeneral, 1) : 0;

// Estudiantes en riesgo (promedio < 10)
$estudiantesRiesgo = $db->query("
    SELECT COUNT(DISTINCT estudiante_id) FROM notas 
    WHERE anio_escolar = YEAR(CURDATE()) AND nota < 10
")->fetchColumn();

// Rendimiento por grado
$rendimientoGrados = $db->query("
    SELECT g.nombre, AVG(n.nota) as promedio
    FROM notas n
    JOIN estudiantes e ON n.estudiante_id = e.id
    JOIN secciones s ON e.seccion_id = s.id
    JOIN grados g ON s.grado_id = g.id
    WHERE n.anio_escolar = YEAR(CURDATE())
    GROUP BY g.id
    ORDER BY g.orden
")->fetchAll();

// Últimas notas registradas
$ultimasNotas = $db->query("
    SELECT CONCAT(e.apellidos, ', ', e.nombres) as estudiante, 
           m.nombre as materia, 
           n.nota, 
           n.lapso,
           n.fecha_actualizacion
    FROM notas n
    JOIN estudiantes e ON n.estudiante_id = e.id
    JOIN seccion_materias sm ON n.seccion_materia_id = sm.id
    JOIN materias m ON sm.materia_id = m.id
    ORDER BY n.fecha_actualizacion DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <script src="<?php echo APP_URL; ?>/assets/js/chart.min.js"></script>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('dashboard'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1>Panel Principal</h1>
        </header>
        <div class="content-container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-info"><h3><?php echo $totalEstudiantes; ?></h3><p>Estudiantes Activos</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info"><h3><?php echo $totalSecciones; ?></h3><p>Secciones</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-info"><h3><?php echo $totalMaterias; ?></h3><p>Materias</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-info"><h3><?php echo $totalDocentes; ?></h3><p>Docentes</p></div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-chart-bar"></i> Rendimiento por Grado</h2></div>
                    <div class="card-body">
                        <?php if (empty($rendimientoGrados)): ?>
                            <p class="text-muted">Aún no hay notas cargadas.</p>
                        <?php else: ?>
                            <canvas id="rendimientoChart" width="400" height="200"></canvas>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-exclamation-triangle"></i> Estudiantes en Riesgo</h2></div>
                    <div class="card-body">
                        <div style="text-align:center; font-size:3rem; color:#e74c3c;"><?php echo $estudiantesRiesgo; ?></div>
                        <p style="text-align:center;">Estudiantes con nota menor a 10</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-clock"></i> Últimas Notas Registradas</h2></div>
                    <div class="card-body">
                        <?php if ($ultimasNotas): ?>
                        <ul style="list-style:none; padding:0;">
                            <?php foreach ($ultimasNotas as $nota): ?>
                            <li style="padding:8px 0; border-bottom:1px solid #eee;">
                                <strong><?php echo htmlspecialchars($nota['estudiante']); ?></strong><br>
                                <small><?php echo htmlspecialchars($nota['materia']); ?> - Lapso <?php echo $nota['lapso']; ?>:</small> 
                                <span class="badge badge-<?php echo $nota['nota'] >= 10 ? 'success' : 'danger'; ?>">
                                    <?php echo number_format($nota['nota'], 1); ?>
                                </span>
                                <br>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($nota['fecha_actualizacion'])); ?></small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p class="text-muted">No hay notas registradas aún.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-bolt"></i> Acciones Rápidas</h2></div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="estudiantes.php" class="action-btn"><i class="fas fa-user-plus"></i><span>Estudiantes</span></a>
                            <a href="asignar_notas.php" class="action-btn"><i class="fas fa-edit"></i><span>Cargar Notas</span></a>
                            <a href="reportes.php" class="action-btn"><i class="fas fa-chart-bar"></i><span>Reportes</span></a>
                            <a href="secciones.php" class="action-btn"><i class="fas fa-school"></i><span>Secciones</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php if (!empty($rendimientoGrados)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('rendimientoChart');
    if (!ctx) return;
    new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($rendimientoGrados, 'nombre')); ?>,
            datasets: [{
                label: 'Promedio',
                data: <?php echo json_encode(array_map(function($v) { return round($v['promedio'], 1); }, $rendimientoGrados)); ?>,
                backgroundColor: '#3498db'
            }]
        },
        options: {
            scales: { y: { beginAtZero: true, max: 20 } },
            responsive: true
        }
    });
});
</script>
<?php endif; ?>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>