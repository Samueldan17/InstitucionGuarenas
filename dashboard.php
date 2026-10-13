<?php
require_once 'config.php';
requireAuth();

// =========================================================
// PROTECCIÓN DE ROLES - Redirigir según el tipo de usuario
// =========================================================
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
$totalEstudiantes = $db->query("SELECT COUNT(*) as total FROM estudiantes WHERE estado = 'activo'")->fetch()['total'];
$totalSecciones = $db->query("SELECT COUNT(*) as total FROM secciones WHERE activo = 1")->fetch()['total'];
$totalMaterias = $db->query("SELECT COUNT(*) as total FROM materias WHERE activo = 1")->fetch()['total'];

$promedioGeneral = $db->query("SELECT AVG(promedio) as promedio FROM notas WHERE anio_escolar = YEAR(CURDATE()) AND promedio > 0")->fetch()['promedio'];
$promedioGeneral = $promedioGeneral ? round($promedioGeneral, 1) : 0;

$estudiantesRiesgo = $db->query("
    SELECT COUNT(DISTINCT n.estudiante_id) as total
    FROM notas n
    WHERE n.anio_escolar = YEAR(CURDATE()) AND n.lapso = (SELECT MAX(lapso) FROM notas WHERE anio_escolar = YEAR(CURDATE()))
    AND n.promedio < 10
")->fetch()['total'];

$rendimientoGrados = $db->query("
    SELECT g.nombre, AVG(n.promedio) as promedio
    FROM notas n
    JOIN estudiantes e ON n.estudiante_id = e.id
    JOIN secciones s ON e.seccion_id = s.id
    JOIN grados g ON s.grado_id = g.id
    WHERE n.anio_escolar = YEAR(CURDATE())
    GROUP BY g.id
    ORDER BY g.orden
")->fetchAll();

$ultimasNotas = $db->query("
    SELECT CONCAT(e.apellidos, ', ', e.nombres) as estudiante, m.nombre as materia, n.promedio, n.fecha_actualizacion
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('dashboard'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1>Panel Principal</h1>
            <div class="header-actions">
                <div class="search-box">
                    <input type="text" placeholder="Buscar..." id="globalSearch">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </header>
        <div class="content-container">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h3><?php echo $totalEstudiantes; ?></h3><p>Estudiantes Activos</p></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo $totalSecciones; ?></h3><p>Secciones</p></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-book"></i></div><div class="stat-info"><h3><?php echo $totalMaterias; ?></h3><p>Materias</p></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-chart-line"></i></div><div class="stat-info"><h3><?php echo $promedioGeneral; ?></h3><p>Promedio General</p></div></div>
            </div>
            <div class="content-grid">
                <div class="card">
                    <div class="card-header"><h2>Rendimiento por Grado</h2></div>
                    <div class="card-body"><canvas id="rendimientoChart" width="400" height="200"></canvas></div>
                </div>
                <div class="card">
                    <div class="card-header"><h2>Estudiantes en Riesgo</h2></div>
                    <div class="card-body">
                        <div style="text-align:center; font-size:3rem; color:#e74c3c;"><?php echo $estudiantesRiesgo; ?></div>
                        <p style="text-align:center;">Estudiantes con promedio < 10 en el último lapso</p>
                        <a href="reportes.php?tipo_reporte=rendimiento&lapso=3" class="btn btn-primary btn-block">Ver Detalle</a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h2>Últimas Notas Registradas</h2></div>
                    <div class="card-body">
                        <?php if($ultimasNotas): ?>
                        <ul style="list-style:none; padding:0;">
                            <?php foreach($ultimasNotas as $nota): ?>
                            <li style="padding:8px 0; border-bottom:1px solid #eee;">
                                <strong><?php echo htmlspecialchars($nota['estudiante']); ?></strong> - <?php echo htmlspecialchars($nota['materia']); ?>: 
                                <span class="badge badge-<?php echo $nota['promedio']>=10?'success':'danger'; ?>"><?php echo number_format($nota['promedio'],1); ?></span>
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
                    <div class="card-header"><h2>Acciones Rápidas</h2></div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="estudiantes.php?action=new" class="action-btn"><i class="fas fa-user-plus"></i><span>Nuevo Estudiante</span></a>
                            <a href="asignar_notas.php" class="action-btn"><i class="fas fa-edit"></i><span>Cargar Notas</span></a>
                            <a href="reportes.php" class="action-btn"><i class="fas fa-chart-bar"></i><span>Generar Reporte</span></a>
                            <a href="secciones.php" class="action-btn"><i class="fas fa-school"></i><span>Gestionar Secciones</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('rendimientoChart').getContext('2d');
    const grados = <?php echo json_encode(array_column($rendimientoGrados, 'nombre')); ?>;
    const promedios = <?php echo json_encode(array_column($rendimientoGrados, 'promedio')); ?>;
    new Chart(ctx, {
        type: 'bar',
        data: { labels: grados, datasets: [{ label: 'Promedio General', data: promedios, backgroundColor: '#3498db' }] },
        options: { scales: { y: { beginAtZero: true, max: 20 } }, responsive: true }
    });
});
</script>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>