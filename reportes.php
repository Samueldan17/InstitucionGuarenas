<?php
// reportes.php - Sistema de reportes completo (VERSIÓN 3.7 - PDF Acta Corregido)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

$db = getDBConnection();
$tipo_reporte = $_GET['tipo_reporte'] ?? 'estadisticas';
$grado_id = (int)($_GET['grado_id'] ?? 0);
$seccion_id = (int)($_GET['seccion_id'] ?? 0);
$lapso = (int)($_GET['lapso'] ?? 1);
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$accion = $_GET['accion'] ?? '';

// Año escolar activo
$anio_escolar_texto = getConfig($db, 'anio_escolar_activo', date('Y') . '-' . (date('Y') + 1));
$anio_escolar = (int)explode('-', $anio_escolar_texto)[0];

// Datos para filtros
$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$secciones = [];
if ($grado_id) {
    $stmt = $db->prepare("SELECT * FROM secciones WHERE grado_id = ? AND activo = 1 ORDER BY letra");
    $stmt->execute([$grado_id]);
    $secciones = $stmt->fetchAll();
}

// =========================================================
// EXPORTAR A EXCEL (CSV) - Solo para reportes simples
// =========================================================
if ($accion === 'exportar_excel' && $tipo_reporte === 'matricula') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_matricula_'.date('Ymd').'.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['REPORTE SIGEB-G - MATRÍCULA']);
    fputcsv($output, []);
    fputcsv($output, ['Grado', 'Hombres', 'Mujeres', 'Total']);
    
    $matricula = $db->query("
        SELECT g.nombre AS grado,
               SUM(CASE WHEN e.genero = 'M' THEN 1 ELSE 0 END) AS hombres,
               SUM(CASE WHEN e.genero = 'F' THEN 1 ELSE 0 END) AS mujeres,
               COUNT(e.id) AS total
        FROM grados g LEFT JOIN secciones s ON s.grado_id = g.id AND s.activo = 1
        LEFT JOIN estudiantes e ON e.seccion_id = s.id AND e.estado = 'activo'
        WHERE g.activo = 1 GROUP BY g.id ORDER BY g.orden
    ");
    foreach ($matricula as $row) {
        fputcsv($output, [$row['grado'], $row['hombres'], $row['mujeres'], $row['total']]);
    }
    fclose($output);
    exit;
}

// =========================================================
// DATOS PARA LOS REPORTES
// =========================================================
$total_estudiantes = $db->query("SELECT COUNT(*) FROM estudiantes WHERE estado='activo'")->fetchColumn();
$total_secciones = $db->query("SELECT COUNT(*) FROM secciones WHERE activo=1")->fetchColumn();
$total_materias = $db->query("SELECT COUNT(*) FROM materias WHERE activo=1")->fetchColumn();
$total_docentes = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol IN ('docente','secretario') AND activo=1")->fetchColumn();
$total_hombres = $db->query("SELECT COUNT(*) FROM estudiantes WHERE genero='M' AND estado='activo'")->fetchColumn();
$total_mujeres = $db->query("SELECT COUNT(*) FROM estudiantes WHERE genero='F' AND estado='activo'")->fetchColumn();

// Matrícula por grado
$matricula_grados = $db->query("
    SELECT g.nombre AS grado,
           SUM(CASE WHEN e.genero = 'M' THEN 1 ELSE 0 END) AS hombres,
           SUM(CASE WHEN e.genero = 'F' THEN 1 ELSE 0 END) AS mujeres,
           COUNT(e.id) AS total
    FROM grados g LEFT JOIN secciones s ON s.grado_id = g.id AND s.activo = 1
    LEFT JOIN estudiantes e ON e.seccion_id = s.id AND e.estado = 'activo'
    WHERE g.activo = 1 GROUP BY g.id ORDER BY g.orden
")->fetchAll();

// Matrícula por sección (del grado seleccionado)
$matricula_secciones = [];
if ($grado_id) {
    $stmt = $db->prepare("
        SELECT s.letra AS seccion, s.turno,
               SUM(CASE WHEN e.genero = 'M' THEN 1 ELSE 0 END) AS hombres,
               SUM(CASE WHEN e.genero = 'F' THEN 1 ELSE 0 END) AS mujeres,
               COUNT(e.id) AS total
        FROM secciones s LEFT JOIN estudiantes e ON e.seccion_id = s.id AND e.estado = 'activo'
        WHERE s.grado_id = ? AND s.activo = 1 GROUP BY s.id ORDER BY s.letra
    ");
    $stmt->execute([$grado_id]);
    $matricula_secciones = $stmt->fetchAll();
}

// =========================================================
// DATOS PARA EL ACTA (SÁBANA)
// =========================================================
$acta_materias = [];
$acta_estudiantes = [];
$acta_notas = [];
$docente_guia = '';

if ($tipo_reporte === 'acta' && $seccion_id) {
    // Materias asignadas a la sección
    $stmtMat = $db->prepare("
        SELECT m.id, m.nombre 
        FROM seccion_materias sm 
        JOIN materias m ON sm.materia_id = m.id 
        WHERE sm.seccion_id = ? AND sm.anio_escolar = ? 
        ORDER BY m.nombre
    ");
    $stmtMat->execute([$seccion_id, $anio_escolar]);
    $acta_materias = $stmtMat->fetchAll();

    // Docente Guía de la sección
    $stmtGuia = $db->prepare("
        SELECT CONCAT(u.nombres, ' ', u.apellidos) as nombre 
        FROM secciones s LEFT JOIN usuarios u ON s.docente_guia_id = u.id 
        WHERE s.id = ?
    ");
    $stmtGuia->execute([$seccion_id]);
    $guia = $stmtGuia->fetch();
    $docente_guia = $guia['nombre'] ?? 'Sin asignar';

    // Estudiantes de la sección
    $stmtEst = $db->prepare("SELECT id, apellidos, nombres, cedula FROM estudiantes WHERE seccion_id = ? AND estado = 'activo' ORDER BY apellidos, nombres");
    $stmtEst->execute([$seccion_id]);
    $acta_estudiantes = $stmtEst->fetchAll();

    // Notas de los estudiantes en este lapso
    $stmtNotas = $db->prepare("
        SELECT n.estudiante_id, sm.materia_id, n.nota
        FROM notas n
        JOIN seccion_materias sm ON n.seccion_materia_id = sm.id
        WHERE n.lapso = ? AND n.anio_escolar = ? AND n.es_recuperacion = 0 AND sm.seccion_id = ?
    ");
    $stmtNotas->execute([$lapso, $anio_escolar, $seccion_id]);
    while ($row = $stmtNotas->fetch()) {
        $acta_notas[$row['estudiante_id']][$row['materia_id']] = $row['nota'];
    }
}

// =========================================================
// EXPORTAR ACTA A PDF CON DOMPDF (Ahora sí, con los datos cargados)
// =========================================================
if ($accion === 'pdf' && $tipo_reporte === 'acta' && $seccion_id) {
    require_once __DIR__ . '/vendor/autoload.php';
    
    ob_start();
    ?>
    <style>
        body { font-family: 'Times New Roman', serif; font-size: 11px; }
        .acta-header { text-align: center; margin-bottom: 15px; }
        .acta-header h2 { margin: 0; font-size: 14px; }
        .acta-header p { margin: 2px 0; font-size: 11px; }
        .acta-table { width: 100%; border-collapse: collapse; text-align: center; }
        .acta-table th, .acta-table td { border: 1px solid #000; padding: 4px; }
        .acta-table th { background: #f0f0f0; font-weight: bold; }
        .acta-table td.estudiante { text-align: left; }
        .firmas-container { display: flex; justify-content: space-between; margin-top: 40px; text-align: center; }
        .firma { border-top: 1px solid #000; width: 180px; padding-top: 5px; }
    </style>

    <div class="acta-header">
        <h2>REPÚBLICA BOLIVARIANA DE VENEZUELA</h2>
        <p>MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN</p>
        <p><strong><?php echo APP_NAME; ?></strong></p>
        <p>ACTA DE CALIFICACIONES - <?php echo strtoupper($lapso); ?>er LAPSO</p>
        <p>Año Escolar: <?php echo $anio_escolar_texto; ?></p>
    </div>

    <table class="acta-table">
        <thead>
            <tr>
                <th style="width: 30px;">Nº</th>
                <th style="text-align: left;">Apellidos y Nombres</th>
                <th style="width: 80px;">Cédula</th>
                <?php foreach ($acta_materias as $mat): ?>
                    <th><?php echo htmlspecialchars($mat['nombre']); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($acta_estudiantes as $est): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td class="estudiante"><?php echo htmlspecialchars($est['apellidos'] . ', ' . $est['nombres']); ?></td>
                <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                <?php foreach ($acta_materias as $mat): 
                    $nota = $acta_notas[$est['id']][$mat['id']] ?? '';
                ?>
                    <td><?php echo $nota !== '' ? number_format($nota, 0) : '—'; ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="firmas-container">
        <div class="firma"><strong>Prof. Jhony Castro</strong><br>Director</div>
        <div class="firma"><strong><?php echo htmlspecialchars($docente_guia); ?></strong><br>Docente Guía</div>
        <div class="firma"><strong>Coordinación</strong><br>Evaluación y Control de Estudios</div>
    </div>
    <?php
    $html = ob_get_clean();

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'landscape'); // Horizontal para que quepan las materias
    $dompdf->render();
    
    $dompdf->stream("Acta_" . $lapso . "Lapso_" . date('Ymd') . ".pdf", array("Attachment" => false));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .report-tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #e1e4e8; flex-wrap: wrap; }
        .report-tab { padding: 12px 20px; background: transparent; border: none; border-bottom: 3px solid transparent; color: #586069; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .report-tab:hover { color: #3498db; background: #f8f9fa; }
        .report-tab.active { color: #3498db; border-bottom-color: #3498db; }
        .report-tab i { margin-right: 6px; }
        
        .matricula-table { width: 100%; border-collapse: collapse; }
        .matricula-table th { background: #f6f8fa; padding: 12px; text-align: left; font-size: 13px; color: #586069; border-bottom: 1px solid #e1e4e8; }
        .matricula-table td { padding: 12px; border-bottom: 1px solid #eaecef; font-size: 14px; }
        .matricula-table td.num { text-align: right; font-weight: 600; }
        .gender-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }
        .gender-m { background: #d1ecf1; color: #0c5460; }
        .gender-f { background: #f8d7da; color: #721c24; }

        /* Estilos para el Acta (Sábana) */
        .acta-container { width: 100%; overflow-x: auto; background: white; padding: 20px; border: 1px solid #ddd; }
        .acta-table { width: 100%; border-collapse: collapse; font-size: 12px; text-align: center; }
        .acta-table th, .acta-table td { border: 1px solid #000; padding: 6px 4px; }
        .acta-table th { background: #f0f0f0; font-weight: bold; }
        .acta-table td.estudiante { text-align: left; font-weight: 500; }
        .acta-header { text-align: center; margin-bottom: 20px; font-family: 'Times New Roman', serif; }
        .acta-header h2 { margin: 0; font-size: 16px; }
        .acta-header p { margin: 5px 0; font-size: 12px; }
        .firmas-container { display: flex; justify-content: space-around; margin-top: 50px; text-align: center; font-size: 12px; }
        .firma { border-top: 1px solid #000; width: 200px; padding-top: 5px; }
        
        @media print {
            .no-print { display: none !important; }
            .sidebar, .main-header { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .content-container { padding: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            body { background: white; }
            @page { size: landscape; margin: 1cm; }
            .acta-table { font-size: 10px; }
            .acta-table th, .acta-table td { padding: 2px; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('reportes'); ?>
    <main class="main-content">
        <header class="main-header no-print">
            <h1><i class="fas fa-chart-bar"></i> Sistema de Reportes</h1>
            <div class="header-actions">
                <?php if ($tipo_reporte === 'matricula'): ?>
                    <a href="?tipo_reporte=matricula&accion=exportar_excel&grado_id=<?php echo $grado_id; ?>" class="btn btn-success"><i class="fas fa-file-excel"></i> Exportar Excel</a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir</button>
            </div>
        </header>
        <div class="content-container">

            <!-- Pestañas -->
            <div class="report-tabs no-print">
                <a href="?tipo_reporte=estadisticas" class="report-tab <?php echo $tipo_reporte == 'estadisticas' ? 'active' : ''; ?>"><i class="fas fa-chart-pie"></i> Estadísticas</a>
                <a href="?tipo_reporte=matricula" class="report-tab <?php echo $tipo_reporte == 'matricula' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Matrícula</a>
                <a href="?tipo_reporte=acta" class="report-tab <?php echo $tipo_reporte == 'acta' ? 'active' : ''; ?>"><i class="fas fa-table"></i> Acta / Sábana</a>
            </div>

            <!-- ================================================= -->
            <!-- REPORTE: ESTADÍSTICAS GENERALES -->
            <!-- ================================================= -->
            <?php if ($tipo_reporte === 'estadisticas'): ?>
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h3><?php echo $total_estudiantes; ?></h3><p>Estudiantes Activos</p></div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:#3498db;"><i class="fas fa-male"></i></div><div class="stat-info"><h3><?php echo $total_hombres; ?></h3><p>Hombres</p></div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:#e91e63;"><i class="fas fa-female"></i></div><div class="stat-info"><h3><?php echo $total_mujeres; ?></h3><p>Mujeres</p></div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo $total_secciones; ?></h3><p>Secciones</p></div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:#27ae60;"><i class="fas fa-book"></i></div><div class="stat-info"><h3><?php echo $total_materias; ?></h3><p>Materias</p></div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:#9b59b6;"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-info"><h3><?php echo $total_docentes; ?></h3><p>Docentes</p></div></div>
                </div>
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header"><h2>Matrícula por Grado</h2></div>
                    <div class="card-body">
                        <table class="matricula-table">
                            <thead><tr><th>Grado</th><th class="num">Hombres</th><th class="num">Mujeres</th><th class="num">Total</th></tr></thead>
                            <tbody>
                                <?php $tot_h = 0; $tot_m = 0; $tot_t = 0;
                                foreach ($matricula_grados as $mg): $tot_h += $mg['hombres']; $tot_m += $mg['mujeres']; $tot_t += $mg['total']; ?>
                                <tr><td><strong><?php echo htmlspecialchars($mg['grado']); ?></strong></td><td class="num"><?php echo $mg['hombres']; ?></td><td class="num"><?php echo $mg['mujeres']; ?></td><td class="num"><?php echo $mg['total']; ?></td></tr>
                                <?php endforeach; ?>
                                <tr style="background: #eaf4fc; font-weight: bold;"><td>TOTAL GENERAL</td><td class="num"><?php echo $tot_h; ?></td><td class="num"><?php echo $tot_m; ?></td><td class="num"><?php echo $tot_t; ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================================================= -->
            <!-- REPORTE: MATRÍCULA -->
            <!-- ================================================= -->
            <?php if ($tipo_reporte === 'matricula'): ?>
                <div class="card no-print" style="margin-bottom: 20px;">
                    <div class="card-header"><h2>Filtros</h2></div>
                    <div class="card-body">
                        <form method="GET">
                            <input type="hidden" name="tipo_reporte" value="matricula">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Grado (opcional)</label>
                                    <select name="grado_id" class="form-control" onchange="this.form.submit()">
                                        <option value="">Todos los grados</option>
                                        <?php foreach($grados as $g): ?><option value="<?php echo $g['id']; ?>" <?php echo $grado_id == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['nombre']); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h2>Matrícula <?php echo $grado_id ? 'por Sección' : 'por Grado'; ?></h2></div>
                    <div class="card-body">
                        <table class="matricula-table">
                            <thead><tr><?php if ($grado_id): ?><th>Sección</th><th>Turno</th><?php else: ?><th>Grado</th><?php endif; ?><th class="num">Hombres</th><th class="num">Mujeres</th><th class="num">Total</th></tr></thead>
                            <tbody>
                                <?php $total_h = 0; $total_m = 0; $total_t = 0; $datos = $grado_id ? $matricula_secciones : $matricula_grados;
                                foreach ($datos as $d): $total_h += $d['hombres']; $total_m += $d['mujeres']; $total_t += $d['total']; ?>
                                <tr>
                                    <?php if ($grado_id): ?><td><strong>Sección <?php echo htmlspecialchars($d['seccion']); ?></strong></td><td><?php echo ucfirst($d['turno']); ?></td><?php else: ?><td><strong><?php echo htmlspecialchars($d['grado']); ?></strong></td><?php endif; ?>
                                    <td class="num"><span class="gender-badge gender-m"><?php echo $d['hombres']; ?></span></td>
                                    <td class="num"><span class="gender-badge gender-f"><?php echo $d['mujeres']; ?></span></td>
                                    <td class="num"><?php echo $d['total']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: #eaf4fc;"><td colspan="<?php echo $grado_id ? 2 : 1; ?>"><strong>TOTAL</strong></td><td class="num"><?php echo $total_h; ?></td><td class="num"><?php echo $total_m; ?></td><td class="num"><?php echo $total_t; ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================================================= -->
            <!-- REPORTE: ACTA / SÁBANA DE NOTAS -->
            <!-- ================================================= -->
            <?php if ($tipo_reporte === 'acta'): ?>
                <div class="card no-print" style="margin-bottom: 20px;">
                    <div class="card-header"><h2>Seleccionar Sección y Lapso</h2></div>
                    <div class="card-body">
                        <form method="GET" class="form-grid">
                            <input type="hidden" name="tipo_reporte" value="acta">
                            <div class="form-group">
                                <label>Grado</label>
                                <select name="grado_id" class="form-control" onchange="this.form.submit()">
                                    <option value="">Seleccione...</option>
                                    <?php foreach($grados as $g): ?><option value="<?php echo $g['id']; ?>" <?php echo $grado_id == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['nombre']); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($grado_id): ?>
                            <div class="form-group">
                                <label>Sección</label>
                                <select name="seccion_id" class="form-control" onchange="this.form.submit()">
                                    <option value="">Seleccione...</option>
                                    <?php foreach($secciones as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $seccion_id == $s['id'] ? 'selected' : ''; ?>>Sección <?php echo $s['letra']; ?> (<?php echo $s['turno']; ?>)</option><?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <?php if ($seccion_id): ?>
                            <div class="form-group">
                                <label>Lapso</label>
                                <select name="lapso" class="form-control" onchange="this.form.submit()">
                                    <option value="1" <?php echo $lapso == 1 ? 'selected' : ''; ?>>1er Lapso</option>
                                    <option value="2" <?php echo $lapso == 2 ? 'selected' : ''; ?>>2do Lapso</option>
                                    <option value="3" <?php echo $lapso == 3 ? 'selected' : ''; ?>>3er Lapso</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </form>
                        
                        <!-- BOTÓN PDF CORREGIDO: Ahora sí está en la pestaña del Acta -->
                        <?php if ($seccion_id && !empty($acta_estudiantes)): ?>
                        <div style="text-align: right; margin-top: 15px;">
                            <a href="?tipo_reporte=acta&grado_id=<?php echo $grado_id; ?>&seccion_id=<?php echo $seccion_id; ?>&lapso=<?php echo $lapso; ?>&accion=pdf" target="_blank" class="btn btn-success">
                                <i class="fas fa-file-pdf"></i> Descargar PDF
                            </a>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>

                <?php if ($seccion_id && !empty($acta_estudiantes)): ?>
                    <div class="acta-container">
                        <!-- Encabezado Oficial -->
                        <div class="acta-header">
                            <h2>REPÚBLICA BOLIVARIANA DE VENEZUELA</h2>
                            <p>MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN</p>
                            <p><strong><?php echo APP_NAME; ?></strong></p>
                            <p>ACTA DE CALIFICACIONES - <?php echo strtoupper($lapso); ?>er LAPSO</p>
                            <p>Año Escolar: <?php echo $anio_escolar_texto; ?></p>
                        </div>

                        <!-- Tabla Matriz -->
                        <table class="acta-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Nº</th>
                                    <th style="min-width: 200px; text-align: left;">Apellidos y Nombres</th>
                                    <th style="width: 80px;">Cédula</th>
                                    <?php foreach ($acta_materias as $mat): ?>
                                        <th><?php echo htmlspecialchars($mat['nombre']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($acta_estudiantes as $est): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td class="estudiante"><?php echo htmlspecialchars($est['apellidos'] . ', ' . $est['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                                    <?php foreach ($acta_materias as $mat): 
                                        $nota = $acta_notas[$est['id']][$mat['id']] ?? '';
                                    ?>
                                        <td><?php echo $nota !== '' ? number_format($nota, 0) : '—'; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Firmas -->
                        <div class="firmas-container">
                            <div class="firma">
                                <strong>Prof. Jhony Castro</strong><br>
                                Director
                            </div>
                            <div class="firma">
                                <strong><?php echo htmlspecialchars($docente_guia); ?></strong><br>
                                Docente Guía
                            </div>
                            <div class="firma">
                                <strong>Coordinación</strong><br>
                                Evaluación y Control de Estudios
                            </div>
                        </div>
                    </div>
                <?php elseif ($seccion_id): ?>
                    <div class="alert alert-warning">No hay estudiantes activos en esta sección.</div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-file-invoice fa-4x"></i><h3>Seleccione un grado, sección y lapso para generar el acta.</h3></div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>