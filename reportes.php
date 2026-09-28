<?php
// reportes.php - Sistema de reportes con sábana de notas
require_once 'config.php';
requireAuth();

$db = getDBConnection();
$tipo_reporte = $_GET['tipo_reporte'] ?? 'estadisticas';
$lapso = $_GET['lapso'] ?? '1';
$grado_id = $_GET['grado_id'] ?? '';
$seccion_id = $_GET['seccion_id'] ?? '';
$accion = $_GET['accion'] ?? '';

$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$secciones = [];
if ($grado_id) {
    $stmt = $db->prepare("SELECT * FROM secciones WHERE grado_id = ? AND activo = 1 ORDER BY letra");
    $stmt->execute([$grado_id]);
    $secciones = $stmt->fetchAll();
}

// Exportar a Excel (CSV) con formato de sábana
if ($accion === 'exportar_excel' && $tipo_reporte === 'sabana' && $seccion_id) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sabana_notas_'.date('Ymd').'.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['SÁBANA DE NOTAS - U.E.N. GUARENAS']);
    fputcsv($output, ['Sección: ' . $seccion_id . ' - Lapso: ' . $lapso . ' - Año: ' . date('Y')]);
    fputcsv($output, []);

    // Obtener materias de la sección
    $stmt = $db->prepare("
        SELECT m.id, m.nombre 
        FROM seccion_materias sm 
        JOIN materias m ON sm.materia_id = m.id 
        WHERE sm.seccion_id = ? AND sm.lapso = ? AND sm.anio_escolar = YEAR(CURDATE())
        ORDER BY m.nombre
    ");
    $stmt->execute([$seccion_id, $lapso]);
    $materias = $stmt->fetchAll();

    // Cabecera: Estudiante + columnas por materia (1,2,3,D,R)
    $header = ['Nº', 'Apellidos y Nombres', 'Cédula'];
    foreach ($materias as $m) {
        $header[] = $m['nombre'] . ' 1';
        $header[] = $m['nombre'] . ' 2';
        $header[] = $m['nombre'] . ' 3';
        $header[] = $m['nombre'] . ' D';
        $header[] = $m['nombre'] . ' R';
    }
    fputcsv($output, $header);

    // Obtener estudiantes
    $stmt = $db->prepare("SELECT id, apellidos, nombres, cedula FROM estudiantes WHERE seccion_id = ? AND estado = 'activo' ORDER BY apellidos, nombres");
    $stmt->execute([$seccion_id]);
    $estudiantes = $stmt->fetchAll();

    $i = 1;
    foreach ($estudiantes as $est) {
        $row = [$i, $est['apellidos'] . ' ' . $est['nombres'], $est['cedula']];
        foreach ($materias as $m) {
            // Obtener notas del estudiante para esta materia
            $stmt = $db->prepare("
                SELECT evaluacion1, evaluacion2, evaluacion3, promedio, recuperacion 
                FROM notas 
                WHERE estudiante_id = ? AND seccion_materia_id = (
                    SELECT id FROM seccion_materias WHERE seccion_id = ? AND materia_id = ? AND lapso = ? AND anio_escolar = YEAR(CURDATE())
                )
            ");
            $stmt->execute([$est['id'], $seccion_id, $m['id'], $lapso]);
            $nota = $stmt->fetch();
            if ($nota) {
                $row[] = $nota['evaluacion1'] ?? '';
                $row[] = $nota['evaluacion2'] ?? '';
                $row[] = $nota['evaluacion3'] ?? '';
                $row[] = $nota['promedio'] ?? '';
                $row[] = $nota['recuperacion'] ?? '';
            } else {
                $row = array_merge($row, ['', '', '', '', '']);
            }
        }
        fputcsv($output, $row);
        $i++;
    }
    fclose($output);
    exit;
}

// Datos para mostrar en la vista
$reporte_data = [];
$titulo_reporte = '';
$sabana_materias = [];
$sabana_estudiantes = [];

if ($tipo_reporte === 'estadisticas') {
    $titulo_reporte = 'Estadísticas Generales';
    $total_estudiantes = $db->query("SELECT COUNT(*) as total FROM estudiantes WHERE estado='activo'")->fetch()['total'];
    $total_secciones = $db->query("SELECT COUNT(*) as total FROM secciones WHERE activo=1")->fetch()['total'];
    $total_materias = $db->query("SELECT COUNT(*) as total FROM materias WHERE activo=1")->fetch()['total'];
    $total_docentes = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE rol='docente' AND activo=1")->fetch()['total'];
    $reporte_data = compact('total_estudiantes','total_secciones','total_materias','total_docentes');
} elseif ($tipo_reporte === 'sabana' && $seccion_id) {
    $titulo_reporte = 'Sábana de Notas';
    // Obtener materias
    $stmt = $db->prepare("
        SELECT m.id, m.nombre 
        FROM seccion_materias sm 
        JOIN materias m ON sm.materia_id = m.id 
        WHERE sm.seccion_id = ? AND sm.lapso = ? AND sm.anio_escolar = YEAR(CURDATE())
        ORDER BY m.nombre
    ");
    $stmt->execute([$seccion_id, $lapso]);
    $sabana_materias = $stmt->fetchAll();

    // Obtener estudiantes con sus notas
    $stmt = $db->prepare("
        SELECT e.id, e.apellidos, e.nombres, e.cedula 
        FROM estudiantes e 
        WHERE e.seccion_id = ? AND e.estado = 'activo' 
        ORDER BY e.apellidos, e.nombres
    ");
    $stmt->execute([$seccion_id]);
    $sabana_estudiantes = $stmt->fetchAll();

    // Para cada estudiante, obtener notas de cada materia
    foreach ($sabana_estudiantes as &$est) {
        $est['notas'] = [];
        foreach ($sabana_materias as $m) {
            $stmt = $db->prepare("
                SELECT evaluacion1, evaluacion2, evaluacion3, promedio, recuperacion 
                FROM notas 
                WHERE estudiante_id = ? AND seccion_materia_id = (
                    SELECT id FROM seccion_materias WHERE seccion_id = ? AND materia_id = ? AND lapso = ? AND anio_escolar = YEAR(CURDATE())
                )
            ");
            $stmt->execute([$est['id'], $seccion_id, $m['id'], $lapso]);
            $nota = $stmt->fetch();
            $est['notas'][$m['id']] = $nota ?: ['evaluacion1'=>'','evaluacion2'=>'','evaluacion3'=>'','promedio'=>'','recuperacion'=>''];
        }
    }
    unset($est);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sabana-table { font-size: 0.75rem; }
        .sabana-table th, .sabana-table td { padding: 4px 6px; text-align: center; white-space: nowrap; }
        .sabana-table th { background: #2c3e50; color: white; }
        .sabana-table td:first-child, .sabana-table td:nth-child(2) { text-align: left; }
        .sabana-table .nota-cell { min-width: 40px; }
        @media print { .no-print { display: none; } .sabana-table { font-size: 0.6rem; } }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('reportes'); ?>
    <main class="main-content">
        <header class="main-header"><h1>Sistema de Reportes</h1></header>
        <div class="content-container">
            <div class="card no-print">
                <div class="card-header"><h2>Filtros del Reporte</h2></div>
                <div class="card-body">
                    <form method="GET" class="form-grid">
                        <div class="form-group">
                            <label>Tipo de Reporte</label>
                            <select name="tipo_reporte" class="form-control" onchange="this.form.submit()">
                                <option value="estadisticas" <?php echo $tipo_reporte=='estadisticas'?'selected':''; ?>>Estadísticas Generales</option>
                                <option value="sabana" <?php echo $tipo_reporte=='sabana'?'selected':''; ?>>Sábana de Notas</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Lapso</label>
                            <select name="lapso" class="form-control" onchange="this.form.submit()">
                                <option value="1" <?php echo $lapso=='1'?'selected':''; ?>>1er Lapso</option>
                                <option value="2" <?php echo $lapso=='2'?'selected':''; ?>>2do Lapso</option>
                                <option value="3" <?php echo $lapso=='3'?'selected':''; ?>>3er Lapso</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Grado</label>
                            <select name="grado_id" class="form-control" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <?php foreach($grados as $g): ?>
                                <option value="<?php echo $g['id']; ?>" <?php echo $grado_id==$g['id']?'selected':''; ?>><?php echo $g['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sección</label>
                            <select name="seccion_id" class="form-control" <?php echo !$grado_id?'disabled':''; ?> onchange="this.form.submit()">
                                <option value="">-- Seleccione --</option>
                                <?php foreach($secciones as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $seccion_id==$s['id']?'selected':''; ?>><?php echo $s['letra']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><?php echo $titulo_reporte; ?></h2>
                    <?php if($tipo_reporte === 'sabana' && $seccion_id && !empty($sabana_estudiantes)): ?>
                    <div class="card-actions no-print">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['accion'=>'exportar_excel'])); ?>" class="btn btn-success"><i class="fas fa-file-excel"></i> Exportar Excel</a>
                        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir</button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if($tipo_reporte === 'estadisticas'): ?>
                    <div class="stats-grid">
                        <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h3><?php echo $reporte_data['total_estudiantes']; ?></h3><p>Estudiantes</p></div></div>
                        <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo $reporte_data['total_secciones']; ?></h3><p>Secciones</p></div></div>
                        <div class="stat-card"><div class="stat-icon"><i class="fas fa-book"></i></div><div class="stat-info"><h3><?php echo $reporte_data['total_materias']; ?></h3><p>Materias</p></div></div>
                        <div class="stat-card"><div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-info"><h3><?php echo $reporte_data['total_docentes']; ?></h3><p>Docentes</p></div></div>
                    </div>
                    <?php elseif($tipo_reporte === 'sabana' && $seccion_id): ?>
                        <?php if(empty($sabana_estudiantes) || empty($sabana_materias)): ?>
                        <div class="alert alert-info">No hay datos para mostrar en la sábana.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table sabana-table">
                                <thead>
                                    <tr>
                                        <th>Nº</th>
                                        <th>Apellidos y Nombres</th>
                                        <th>Cédula</th>
                                        <?php foreach ($sabana_materias as $m): ?>
                                            <th colspan="5" style="text-align:center;"><?php echo htmlspecialchars($m['nombre']); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <th></th><th></th><th></th>
                                        <?php foreach ($sabana_materias as $m): ?>
                                            <th>1</th><th>2</th><th>3</th><th>D</th><th>R</th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($sabana_estudiantes as $est): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($est['apellidos'] . ' ' . $est['nombres']); ?></td>
                                        <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                                        <?php foreach ($sabana_materias as $m): 
                                            $n = $est['notas'][$m['id']] ?? ['evaluacion1'=>'','evaluacion2'=>'','evaluacion3'=>'','promedio'=>'','recuperacion'=>''];
                                        ?>
                                            <td><?php echo $n['evaluacion1'] ? number_format($n['evaluacion1'],1) : ''; ?></td>
                                            <td><?php echo $n['evaluacion2'] ? number_format($n['evaluacion2'],1) : ''; ?></td>
                                            <td><?php echo $n['evaluacion3'] ? number_format($n['evaluacion3'],1) : ''; ?></td>
                                            <td><strong><?php echo $n['promedio'] ? number_format($n['promedio'],1) : ''; ?></strong></td>
                                            <td><?php echo $n['recuperacion'] ? number_format($n['recuperacion'],1) : ''; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">Seleccione los filtros necesarios para generar el reporte.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>