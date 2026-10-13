<?php
// reportes.php - Sistema de reportes completo (VERSIÓN 3.5)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

$db = getDBConnection();
$tipo_reporte = $_GET['tipo_reporte'] ?? 'estadisticas';
$grado_id = (int)($_GET['grado_id'] ?? 0);
$seccion_id = (int)($_GET['seccion_id'] ?? 0);
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$accion = $_GET['accion'] ?? '';

// Datos para filtros
$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$secciones = [];
if ($grado_id) {
    $stmt = $db->prepare("SELECT * FROM secciones WHERE grado_id = ? AND activo = 1 ORDER BY letra");
    $stmt->execute([$grado_id]);
    $secciones = $stmt->fetchAll();
}

// =========================================================
// EXPORTAR A EXCEL (CSV)
// =========================================================
if ($accion === 'exportar_excel') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_' . $tipo_reporte . '_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['REPORTE SIGEB-G - U.E.N. GUARENAS']);
    fputcsv($output, ['Tipo: ' . ucfirst($tipo_reporte)]);
    fputcsv($output, ['Generado: ' . date('d/m/Y H:i')]);
    fputcsv($output, []);

    if ($tipo_reporte === 'matricula') {
        fputcsv($output, ['Grado', 'Sección', 'Turno', 'Hombres', 'Mujeres', 'Total']);
        $stmt = $db->query("
            SELECT g.nombre AS grado, s.letra AS seccion, s.turno,
                   SUM(CASE WHEN e.genero = 'M' THEN 1 ELSE 0 END) AS hombres,
                   SUM(CASE WHEN e.genero = 'F' THEN 1 ELSE 0 END) AS mujeres,
                   COUNT(e.id) AS total
            FROM secciones s
            JOIN grados g ON s.grado_id = g.id
            LEFT JOIN estudiantes e ON e.seccion_id = s.id AND e.estado = 'activo'
            WHERE s.activo = 1
            GROUP BY s.id
            ORDER BY g.orden, s.letra
        ");
        foreach ($stmt as $row) {
            fputcsv($output, [$row['grado'], $row['seccion'], $row['turno'], $row['hombres'], $row['mujeres'], $row['total']]);
        }
    } elseif ($tipo_reporte === 'ingresos_egresos') {
        fputcsv($output, ['Mes', 'Ingresos (nuevos)', 'Egresos (retiros)']);
        $stmt = $db->prepare("
            SELECT DATE_FORMAT(fecha_inscripcion, '%Y-%m') AS mes, COUNT(*) AS total
            FROM estudiantes
            WHERE YEAR(fecha_inscripcion) = ? AND MONTH(fecha_inscripcion) = ?
            GROUP BY mes
        ");
        $stmt->execute([$anio, $mes]);
        $ingresos = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total FROM estudiantes
            WHERE YEAR(fecha_retiro) = ? AND MONTH(fecha_retiro) = ? AND estado IN ('inactivo', 'graduado')
        ");
        $stmt->execute([$anio, $mes]);
        $egresos = $stmt->fetch()['total'] ?? 0;
        
        fputcsv($output, [date('m/Y', strtotime("$anio-$mes-01")), $ingresos, $egresos]);
    } elseif ($tipo_reporte === 'sabana' && $seccion_id) {
        $stmt = $db->prepare("SELECT apellidos, nombres, cedula FROM estudiantes WHERE seccion_id = ? AND estado = 'activo' ORDER BY apellidos, nombres");
        $stmt->execute([$seccion_id]);
        fputcsv($output, ['Nº', 'Apellidos y Nombres', 'Cédula']);
        $i = 1;
        foreach ($stmt as $est) {
            fputcsv($output, [$i++, $est['apellidos'] . ' ' . $est['nombres'], $est['cedula']]);
        }
    }
    
    fclose($output);
    exit;
}

// =========================================================
// DATOS PARA LOS REPORTES
// =========================================================

// Estadísticas generales
$total_estudiantes = $db->query("SELECT COUNT(*) FROM estudiantes WHERE estado='activo'")->fetchColumn();
$total_secciones = $db->query("SELECT COUNT(*) FROM secciones WHERE activo=1")->fetchColumn();
$total_materias = $db->query("SELECT COUNT(*) FROM materias WHERE activo=1")->fetchColumn();
$total_docentes = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol='docente' AND activo=1")->fetchColumn();
$total_hombres = $db->query("SELECT COUNT(*) FROM estudiantes WHERE genero='M' AND estado='activo'")->fetchColumn();
$total_mujeres = $db->query("SELECT COUNT(*) FROM estudiantes WHERE genero='F' AND estado='activo'")->fetchColumn();

// Matrícula por grado
$matricula_grados = $db->query("
    SELECT g.nombre AS grado,
           SUM(CASE WHEN e.genero = 'M' THEN 1 ELSE 0 END) AS hombres,
           SUM(CASE WHEN e.genero = 'F' THEN 1 ELSE 0 END) AS mujeres,
           COUNT(e.id) AS total
    FROM grados g
    LEFT JOIN secciones s ON s.grado_id = g.id AND s.activo = 1
    LEFT JOIN estudiantes e ON e.seccion_id = s.id AND e.estado = 'activo'
    WHERE g.activo = 1
    GROUP BY g.id
    ORDER BY g.orden
")->fetchAll();

// Matrícula por sección (del grado seleccionado)
$matricula_secciones = [];
if ($grado_id) {
    $stmt = $db->prepare("
        SELECT s.letra AS seccion, s.turno,
               SUM(CASE WHEN e.genero = 'M' THEN 1 ELSE 0 END) AS hombres,
               SUM(CASE WHEN e.genero = 'F' THEN 1 ELSE 0 END) AS mujeres,
               COUNT(e.id) AS total
        FROM secciones s
        LEFT JOIN estudiantes e ON e.seccion_id = s.id AND e.estado = 'activo'
        WHERE s.grado_id = ? AND s.activo = 1
        GROUP BY s.id
        ORDER BY s.letra
    ");
    $stmt->execute([$grado_id]);
    $matricula_secciones = $stmt->fetchAll();
}

// Ingresos del mes seleccionado
$stmt = $db->prepare("
    SELECT COUNT(*) FROM estudiantes
    WHERE YEAR(fecha_inscripcion) = ? AND MONTH(fecha_inscripcion) = ?
");
$stmt->execute([$anio, $mes]);
$ingresos_mes = $stmt->fetchColumn();

// Egresos del mes seleccionado
$stmt = $db->prepare("
    SELECT COUNT(*) FROM estudiantes
    WHERE YEAR(fecha_retiro) = ? AND MONTH(fecha_retiro) = ? AND estado IN ('inactivo', 'graduado')
");
$stmt->execute([$anio, $mes]);
$egresos_mes = $stmt->fetchColumn();

// Historial de ingresos/egresos por mes del año
$historial_meses = [];
for ($m = 1; $m <= 12; $m++) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM estudiantes WHERE YEAR(fecha_inscripcion) = ? AND MONTH(fecha_inscripcion) = ?");
    $stmt->execute([$anio, $m]);
    $ing = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM estudiantes WHERE YEAR(fecha_retiro) = ? AND MONTH(fecha_retiro) = ? AND estado IN ('inactivo', 'graduado')");
    $stmt->execute([$anio, $m]);
    $egr = $stmt->fetchColumn();

    $historial_meses[] = ['mes' => $m, 'nombre' => date('F', mktime(0, 0, 0, $m, 1)), 'ingresos' => $ing, 'egresos' => $egr];
}

// Sábana de notas
$sabana_estudiantes = [];
if ($tipo_reporte === 'sabana' && $seccion_id) {
    $stmt = $db->prepare("SELECT id, apellidos, nombres, cedula FROM estudiantes WHERE seccion_id = ? AND estado = 'activo' ORDER BY apellidos, nombres");
    $stmt->execute([$seccion_id]);
    $sabana_estudiantes = $stmt->fetchAll();
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
        .report-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e1e4e8;
            flex-wrap: wrap;
        }
        .report-tab {
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: #586069;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .report-tab:hover { color: #3498db; background: #f8f9fa; }
        .report-tab.active { color: #3498db; border-bottom-color: #3498db; }
        .report-tab i { margin-right: 6px; }
        .matricula-table { width: 100%; border-collapse: collapse; }
        .matricula-table th {
            background: #f6f8fa;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            color: #586069;
            border-bottom: 1px solid #e1e4e8;
        }
        .matricula-table td {
            padding: 12px;
            border-bottom: 1px solid #eaecef;
            font-size: 14px;
        }
        .matricula-table td.num { text-align: right; font-weight: 600; }
        .matricula-table tr:last-child { font-weight: bold; background: #f6f8fa; }
        .gender-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .gender-m { background: #d1ecf1; color: #0c5460; }
        .gender-f { background: #f8d7da; color: #721c24; }
        @media print {
            .no-print { display: none !important; }
            .sidebar, .main-header { display: none !important; }
            .main-content { margin: 0 !important; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('reportes'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-chart-bar"></i> Sistema de Reportes</h1>
            <div class="header-actions no-print">
                <a href="?tipo_reporte=<?php echo $tipo_reporte; ?>&accion=exportar_excel&grado_id=<?php echo $grado_id; ?>&seccion_id=<?php echo $seccion_id; ?>&mes=<?php echo $mes; ?>&anio=<?php echo $anio; ?>" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </header>
        <div class="content-container">

            <!-- Pestañas de reportes -->
            <div class="report-tabs no-print">
                <a href="?tipo_reporte=estadisticas" class="report-tab <?php echo $tipo_reporte == 'estadisticas' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> Estadísticas
                </a>
                <a href="?tipo_reporte=matricula" class="report-tab <?php echo $tipo_reporte == 'matricula' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Matrícula
                </a>
                <a href="?tipo_reporte=ingresos_egresos" class="report-tab <?php echo $tipo_reporte == 'ingresos_egresos' ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt"></i> Ingresos/Egresos
                </a>
                <a href="?tipo_reporte=sabana" class="report-tab <?php echo $tipo_reporte == 'sabana' ? 'active' : ''; ?>">
                    <i class="fas fa-table"></i> Sábana de Notas
                </a>
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
                    <div class="card-header"><h2><i class="fas fa-chart-bar"></i> Matrícula por Grado</h2></div>
                    <div class="card-body">
                        <table class="matricula-table">
                            <thead>
                                <tr>
                                    <th>Grado</th>
                                    <th class="num">Hombres</th>
                                    <th class="num">Mujeres</th>
                                    <th class="num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $tot_h = 0; $tot_m = 0; $tot_t = 0;
                                foreach ($matricula_grados as $mg): 
                                    $tot_h += $mg['hombres']; $tot_m += $mg['mujeres']; $tot_t += $mg['total'];
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($mg['grado']); ?></strong></td>
                                    <td class="num"><?php echo $mg['hombres']; ?></td>
                                    <td class="num"><?php echo $mg['mujeres']; ?></td>
                                    <td class="num"><?php echo $mg['total']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: #eaf4fc; font-weight: bold;">
                                    <td>TOTAL GENERAL</td>
                                    <td class="num"><?php echo $tot_h; ?></td>
                                    <td class="num"><?php echo $tot_m; ?></td>
                                    <td class="num"><?php echo $tot_t; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================================================= -->
            <!-- REPORTE: MATRÍCULA POR GÉNERO -->
            <!-- ================================================= -->
            <?php if ($tipo_reporte === 'matricula'): ?>
                <div class="card no-print" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-filter"></i> Filtros</h2></div>
                    <div class="card-body">
                        <form method="GET">
                            <input type="hidden" name="tipo_reporte" value="matricula">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Grado (opcional)</label>
                                    <select name="grado_id" class="form-control" onchange="this.form.submit()">
                                        <option value="">Todos los grados</option>
                                        <?php foreach($grados as $g): ?>
                                            <option value="<?php echo $g['id']; ?>" <?php echo $grado_id == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-users"></i> Matrícula <?php echo $grado_id ? 'por Sección' : 'por Grado'; ?></h2></div>
                    <div class="card-body">
                        <table class="matricula-table">
                            <thead>
                                <tr>
                                    <?php if ($grado_id): ?>
                                        <th>Sección</th>
                                        <th>Turno</th>
                                    <?php else: ?>
                                        <th>Grado</th>
                                    <?php endif; ?>
                                    <th class="num">Hombres</th>
                                    <th class="num">Mujeres</th>
                                    <th class="num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_h = 0; $total_m = 0; $total_t = 0;
                                $datos = $grado_id ? $matricula_secciones : $matricula_grados;
                                foreach ($datos as $d): 
                                    $total_h += $d['hombres']; $total_m += $d['mujeres']; $total_t += $d['total'];
                                ?>
                                <tr>
                                    <?php if ($grado_id): ?>
                                        <td><strong>Sección <?php echo htmlspecialchars($d['seccion']); ?></strong></td>
                                        <td><?php echo ucfirst($d['turno']); ?></td>
                                    <?php else: ?>
                                        <td><strong><?php echo htmlspecialchars($d['grado']); ?></strong></td>
                                    <?php endif; ?>
                                    <td class="num"><span class="gender-badge gender-m"><?php echo $d['hombres']; ?></span></td>
                                    <td class="num"><span class="gender-badge gender-f"><?php echo $d['mujeres']; ?></span></td>
                                    <td class="num"><?php echo $d['total']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: #eaf4fc;">
                                    <td colspan="<?php echo $grado_id ? 2 : 1; ?>"><strong>TOTAL</strong></td>
                                    <td class="num"><?php echo $total_h; ?></td>
                                    <td class="num"><?php echo $total_m; ?></td>
                                    <td class="num"><?php echo $total_t; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================================================= -->
            <!-- REPORTE: INGRESOS Y EGRESOS -->
            <!-- ================================================= -->
            <?php if ($tipo_reporte === 'ingresos_egresos'): ?>
                <div class="card no-print" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-calendar"></i> Seleccionar Mes</h2></div>
                    <div class="card-body">
                        <form method="GET">
                            <input type="hidden" name="tipo_reporte" value="ingresos_egresos">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Mes</label>
                                    <select name="mes" class="form-control" onchange="this.form.submit()">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $mes == $m ? 'selected' : ''; ?>>
                                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Año</label>
                                    <select name="anio" class="form-control" onchange="this.form.submit()">
                                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                            <option value="<?php echo $y; ?>" <?php echo $anio == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background:#27ae60;"><i class="fas fa-user-plus"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $ingresos_mes; ?></h3>
                            <p>Ingresos en <?php echo date('F Y', strtotime("$anio-$mes-01")); ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:#e74c3c;"><i class="fas fa-user-minus"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $egresos_mes; ?></h3>
                            <p>Egresos en <?php echo date('F Y', strtotime("$anio-$mes-01")); ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:#3498db;"><i class="fas fa-balance-scale"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $ingresos_mes - $egresos_mes; ?></h3>
                            <p>Saldo Neto del Mes</p>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <div class="card-header"><h2><i class="fas fa-chart-line"></i> Historial del Año <?php echo $anio; ?></h2></div>
                    <div class="card-body">
                        <table class="matricula-table">
                            <thead>
                                <tr>
                                    <th>Mes</th>
                                    <th class="num">Ingresos</th>
                                    <th class="num">Egresos</th>
                                    <th class="num">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $tot_i = 0; $tot_e = 0;
                                foreach ($historial_meses as $h): 
                                    $tot_i += $h['ingresos']; $tot_e += $h['egresos'];
                                    $saldo = $h['ingresos'] - $h['egresos'];
                                ?>
                                <tr <?php echo $h['mes'] == $mes ? 'style="background:#eaf4fc;"' : ''; ?>>
                                    <td><?php echo $h['nombre']; ?></td>
                                    <td class="num"><?php echo $h['ingresos']; ?></td>
                                    <td class="num"><?php echo $h['egresos']; ?></td>
                                    <td class="num" style="color: <?php echo $saldo >= 0 ? '#27ae60' : '#e74c3c'; ?>;">
                                        <?php echo $saldo > 0 ? '+' : ''; ?><?php echo $saldo; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: #eaf4fc; font-weight: bold;">
                                    <td>TOTAL ANUAL</td>
                                    <td class="num"><?php echo $tot_i; ?></td>
                                    <td class="num"><?php echo $tot_e; ?></td>
                                    <td class="num" style="color: <?php echo ($tot_i - $tot_e) >= 0 ? '#27ae60' : '#e74c3c'; ?>;">
                                        <?php echo ($tot_i - $tot_e) > 0 ? '+' : ''; ?><?php echo $tot_i - $tot_e; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================================================= -->
            <!-- REPORTE: SÁBANA DE NOTAS -->
            <!-- ================================================= -->
            <?php if ($tipo_reporte === 'sabana'): ?>
                <div class="card no-print" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-filter"></i> Filtros</h2></div>
                    <div class="card-body">
                        <form method="GET">
                            <input type="hidden" name="tipo_reporte" value="sabana">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Grado</label>
                                    <select name="grado_id" class="form-control" onchange="this.form.submit()">
                                        <option value="">Seleccione...</option>
                                        <?php foreach($grados as $g): ?>
                                            <option value="<?php echo $g['id']; ?>" <?php echo $grado_id == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if ($grado_id): ?>
                                <div class="form-group">
                                    <label>Sección</label>
                                    <select name="seccion_id" class="form-control" onchange="this.form.submit()">
                                        <option value="">Seleccione...</option>
                                        <?php foreach($secciones as $s): ?>
                                            <option value="<?php echo $s['id']; ?>" <?php echo $seccion_id == $s['id'] ? 'selected' : ''; ?>>Sección <?php echo htmlspecialchars($s['letra']); ?> (<?php echo $s['turno']; ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($seccion_id && !empty($sabana_estudiantes)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-table"></i> Lista de Estudiantes - Sección <?php 
                            foreach($secciones as $s) if ($s['id'] == $seccion_id) echo htmlspecialchars($s['letra']); 
                        ?></h2>
                    </div>
                    <div class="card-body">
                        <p class="text-muted" style="margin-bottom: 15px;">
                            <i class="fas fa-info-circle"></i> La sábana de notas completa se activará cuando se implemente el módulo de carga de notas.
                        </p>
                        <table class="matricula-table">
                            <thead>
                                <tr>
                                    <th>Nº</th>
                                    <th>Apellidos y Nombres</th>
                                    <th>Cédula</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($sabana_estudiantes as $est): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($est['apellidos'] . ' ' . $est['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Seleccione un grado y una sección para ver la lista.</div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>