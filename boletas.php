<?php
// boletas.php - Sistema de boletas con formato oficial (3 momentos + Definitiva)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

$db = getDBConnection();
$error = '';
$boleta_data = null;

// Procesar solicitud de boleta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_boleta'])) {
    try {
        $estudiante_id = (int)($_POST['estudiante_id'] ?? 0);
        $lapso = (int)($_POST['lapso'] ?? 1);
        $anio_escolar = (int)($_POST['anio_escolar'] ?? date('Y'));
        
        if (empty($estudiante_id)) throw new Exception("Debe seleccionar un estudiante");
        if ($lapso < 1 || $lapso > 3) throw new Exception("El lapso debe ser entre 1 y 3");
        
        $boletaData = getBoletaData($estudiante_id, $lapso, $anio_escolar);
        if (isset($boletaData['error'])) {
            throw new Exception($boletaData['error']);
        }
        $boleta_data = $boletaData;
        
    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
    }
}

// Obtener estudiantes para el select
$estudiantes = $db->query("
    SELECT e.id, e.cedula, e.nombres, e.apellidos, g.nombre as grado, s.letra as seccion
    FROM estudiantes e 
    JOIN secciones s ON e.seccion_id = s.id 
    JOIN grados g ON s.grado_id = g.id 
    WHERE e.estado = 'activo' 
    ORDER BY e.apellidos, e.nombres
")->fetchAll();

$anios_escolares = [];
$current_year = date('Y');
for ($i = $current_year - 2; $i <= $current_year + 1; $i++) {
    $anios_escolares[] = $i;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boletas de Calificaciones - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .boleta-container { background: white; padding: 2rem; margin: 1rem 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .boleta-header { text-align: center; border-bottom: 2px solid #2c3e50; padding-bottom: 1rem; margin-bottom: 2rem; }
        .boleta-header h2 { color: #2c3e50; margin: 0; font-size: 1.8rem; }
        .boleta-header .subtitle { color: #7f8c8d; font-size: 1.1rem; }
        .boleta-header .codigo { font-size: 0.9rem; color: #555; margin-top: 5px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .info-section { background: #f8f9fa; padding: 1rem; border-radius: 6px; border-left: 4px solid #3498db; }
        .info-section h4 { margin: 0 0 0.5rem 0; color: #2c3e50; }
        .notas-table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; font-size: 0.9rem; }
        .notas-table th { background: #2c3e50; color: white; padding: 0.75rem; text-align: center; border: 1px solid #2c3e50; }
        .notas-table td { padding: 0.6rem; border: 1px solid #dee2e6; text-align: center; }
        .notas-table td:first-child { text-align: left; font-weight: 500; }
        .notas-table tr:nth-child(even) { background: #f8f9fa; }
        .notas-table .total-row { background: #d4edda !important; font-weight: bold; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2rem; margin-top: 3rem; padding-top: 2rem; border-top: 2px solid #bdc3c7; text-align: center; }
        .firma-box p { margin: 5px 0; }
        .firma-box .linea { border-top: 1px solid #000; width: 80%; margin: 5px auto; }
        .observaciones { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #f39c12; }
        @media print {
            .no-print { display: none !important; }
            .boleta-container { box-shadow: none; margin: 0; padding: 0; }
            body { background: white !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .content-container { padding: 0 !important; }
            .notas-table th { background: #2c3e50 !important; color: white !important; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('boletas'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-file-alt"></i> Boletas de Calificaciones</h1>
        </header>
        <div class="content-container">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card no-print">
                <div class="card-header"><h2><i class="fas fa-cog"></i> Generar Boleta</h2></div>
                <div class="card-body">
                    <form method="POST" action="boletas.php" class="editable">
                        <input type="hidden" name="generar_boleta" value="1">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="estudiante_id" class="required-field"><i class="fas fa-user-graduate"></i> Estudiante</label>
                                <select id="estudiante_id" name="estudiante_id" class="form-control" required>
                                    <option value="">Seleccione un estudiante</option>
                                    <?php foreach ($estudiantes as $est): ?>
                                        <option value="<?php echo $est['id']; ?>" <?php echo (isset($_POST['estudiante_id']) && $_POST['estudiante_id'] == $est['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($est['apellidos'] . ', ' . $est['nombres'] . ' - ' . $est['grado'] . ' ' . $est['seccion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="lapso" class="required-field"><i class="fas fa-calendar-alt"></i> Lapso</label>
                                <select id="lapso" name="lapso" class="form-control" required>
                                    <option value="1" <?php echo (isset($_POST['lapso']) && $_POST['lapso'] == 1) ? 'selected' : ''; ?>>1er Lapso</option>
                                    <option value="2" <?php echo (isset($_POST['lapso']) && $_POST['lapso'] == 2) ? 'selected' : ''; ?>>2do Lapso</option>
                                    <option value="3" <?php echo (isset($_POST['lapso']) && $_POST['lapso'] == 3) ? 'selected' : ''; ?>>3er Lapso</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="anio_escolar" class="required-field"><i class="fas fa-calendar"></i> Año Escolar</label>
                                <select id="anio_escolar" name="anio_escolar" class="form-control" required>
                                    <?php foreach ($anios_escolares as $anio): ?>
                                        <option value="<?php echo $anio; ?>" <?php echo (isset($_POST['anio_escolar']) && $_POST['anio_escolar'] == $anio) ? 'selected' : (($anio == date('Y')) ? 'selected' : ''); ?>>
                                            <?php echo $anio; ?> - <?php echo $anio + 1; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Generar Boleta</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($boleta_data): ?>
            <div class="boleta-container" id="boleta">
                <div class="boleta-header">
                    <h2><?php echo $boleta_data['info_escuela']['nombre']; ?></h2>
                    <div class="subtitle"><?php echo $boleta_data['info_escuela']['direccion']; ?> | Teléfono: <?php echo $boleta_data['info_escuela']['telefono']; ?></div>
                    <div class="codigo">Código D.EA.: <?php echo $boleta_data['info_escuela']['codigo']; ?></div>
                    <hr style="margin: 10px 0;">
                    <h3 style="font-size:1.4rem; margin:10px 0;">BOLETÍN INFORMATIVO</h3>
                    <p style="font-size:0.9rem; margin:0;">Año Escolar: <?php echo $boleta_data['anio_escolar']; ?> - <?php echo $boleta_data['anio_escolar'] + 1; ?> | Lapso: <?php echo $boleta_data['lapso_consultado']; ?></p>
                </div>

                <div style="margin-bottom:20px; font-size:1.05rem;">
                    <p><strong>Estudiante:</strong> <?php echo htmlspecialchars($boleta_data['info_estudiante']['apellidos'] . ', ' . $boleta_data['info_estudiante']['nombres']); ?></p>
                    <p><strong>C.I. N°:</strong> <?php echo htmlspecialchars($boleta_data['info_estudiante']['cedula']); ?></p>
                    <p><strong>Grado y Sección:</strong> <?php echo htmlspecialchars($boleta_data['info_estudiante']['grado_nombre'] . ' ' . $boleta_data['info_estudiante']['seccion_letra'] . ' (' . $boleta_data['info_estudiante']['turno'] . ')'); ?></p>
                    <p><strong>Docente Guía:</strong> <?php echo htmlspecialchars($boleta_data['info_estudiante']['docente_guia_nombre'] ?: 'No asignado'); ?></p>
                </div>

                <table class="notas-table">
                    <thead>
                        <tr>
                            <th>Asignaturas</th>
                            <th>1er Momento</th>
                            <th>2do Momento</th>
                            <th>3er Momento</th>
                            <th>Definitiva (D)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $promedio_general = 0; $total_materias = count($boleta_data['notas']); ?>
                        <?php foreach ($boleta_data['notas'] as $nota): 
                            $d = floatval($nota['definitiva']);
                            $r = floatval($nota['recuperacion']);
                            $nota_final = ($r > $d && $r > 0) ? $r : $d;
                            $promedio_general += $nota_final;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($nota['materia_nombre']); ?></td>
                            <td><?php echo number_format($nota['evaluacion1'], 1); ?></td>
                            <td><?php echo number_format($nota['evaluacion2'], 1); ?></td>
                            <td><?php echo number_format($nota['evaluacion3'], 1); ?></td>
                            <td><strong><?php echo number_format($nota_final, 1); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($total_materias > 0): $promedio_final = $promedio_general / $total_materias; ?>
                        <tr class="total-row">
                            <td colspan="4"><strong>PROMEDIO GENERAL</strong></td>
                            <td><strong><?php echo number_format($promedio_final, 1); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="observaciones">
                    <strong><i class="fas fa-comment"></i> Observaciones:</strong>
                    <p style="margin:5px 0 0 0;"><?php echo $boleta_data['notas'][0]['observaciones'] ?? 'Sin observaciones'; ?></p>
                </div>

                <div class="firmas">
                    <div class="firma-box">
                        <div class="linea"></div>
                        <p><strong>Dpto. de Evaluación</strong></p>
                        <p>Prof. Daniela González</p>
                    </div>
                    <div class="firma-box">
                        <div class="linea"></div>
                        <p><strong>Director</strong></p>
                        <p>Prof. Jhony Castro</p>
                    </div>
                    <div class="firma-box">
                        <div class="linea"></div>
                        <p><strong>Coordinadora</strong></p>
                        <p>Prof. Eddy Peña</p>
                    </div>
                </div>

                <div style="margin-top:20px; text-align:right; font-size:0.85rem; color:#555;">
                    <p>Los Teques, <?php echo fechaEspañol(date('Y-m-d')); ?></p>
                </div>

                <div class="form-actions no-print" style="margin-top: 2rem;">
                    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir Boleta</button>
                    <button onclick="history.back()" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>