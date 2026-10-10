<?php
// asignar_notas.php - Asignación de notas con modal por estudiante (VERSIÓN 3.5)
require_once 'config.php';
requireAuth();

$db = getDBConnection();
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Obtener el ID del docente logueado
$docente_id = $_SESSION['usuario_id'];

// Obtener listas para filtros
$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$secciones = [];
$materias = [];
$estudiantes = [];
$materia_seleccionada = null;
$seccion_id = $_GET['seccion_id'] ?? 0;
$materia_id = $_GET['materia_id'] ?? 0;
$lapso = $_GET['lapso'] ?? 1;
$anio_escolar = $_GET['anio_escolar'] ?? date('Y');
$grado_id = $_GET['grado_id'] ?? 0;

// Cargar secciones según el grado seleccionado
if ($grado_id) {
    $stmtSec = $db->prepare("SELECT id, letra, turno FROM secciones WHERE grado_id = ? AND activo = 1 ORDER BY letra");
    $stmtSec->execute([$grado_id]);
    $secciones_disponibles = $stmtSec->fetchAll();
} else {
    $secciones_disponibles = [];
}

// Si hay sección seleccionada, cargar materias de esa sección (solo las del docente)
if ($seccion_id) {
    $stmt = $db->prepare("
        SELECT sm.id, m.id as materia_id, m.nombre, m.area, CONCAT(u.nombres, ' ', u.apellidos) as docente
        FROM seccion_materias sm
        JOIN materias m ON sm.materia_id = m.id
        LEFT JOIN usuarios u ON sm.docente_id = u.id
        WHERE sm.seccion_id = ? AND sm.anio_escolar = ? AND sm.lapso = ? AND sm.docente_id = ?
        ORDER BY m.area, m.nombre
    ");
    $stmt->execute([$seccion_id, $anio_escolar, $lapso, $docente_id]);
    $materias = $stmt->fetchAll();
}

// Si hay materia seleccionada, cargar estudiantes y sus notas
if ($seccion_id && $materia_id) {
    $stmtPermiso = $db->prepare("
        SELECT id FROM seccion_materias 
        WHERE seccion_id = ? AND materia_id = ? AND lapso = ? AND anio_escolar = ? AND docente_id = ?
    ");
    $stmtPermiso->execute([$seccion_id, $materia_id, $lapso, $anio_escolar, $docente_id]);
    $seccion_materia = $stmtPermiso->fetch();
    
    if (!$seccion_materia) {
        $error = "No tiene permisos para acceder a esta materia en esta sección.";
        $materia_id = 0;
    } else {
        $seccion_materia_id = $seccion_materia['id'];
        
        // Cargar estudiantes de la sección
        $stmt = $db->prepare("
            SELECT e.id, e.cedula, e.nombres, e.apellidos
            FROM estudiantes e
            WHERE e.seccion_id = ? AND e.estado = 'activo'
            ORDER BY e.apellidos, e.nombres
        ");
        $stmt->execute([$seccion_id]);
        $estudiantes = $stmt->fetchAll();

        // Cargar notas existentes
        $notasExistentes = [];
        $stmtNotas = $db->prepare("
            SELECT estudiante_id, evaluacion1, evaluacion2, evaluacion3, proyecto, participacion, promedio, recuperacion 
            FROM notas 
            WHERE seccion_materia_id = ?
        ");
        $stmtNotas->execute([$seccion_materia_id]);
        while ($row = $stmtNotas->fetch()) {
            $notasExistentes[$row['estudiante_id']] = $row;
        }

        $stmtMat = $db->prepare("SELECT nombre FROM materias WHERE id = ?");
        $stmtMat->execute([$materia_id]);
        $materia_seleccionada = $stmtMat->fetchColumn();
    }
}

// Procesar guardado de notas individuales (desde el modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_nota_individual'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $seccion_id = (int)$_POST['seccion_id'];
        $materia_id = (int)$_POST['materia_id'];
        $lapso = (int)$_POST['lapso'];
        $anio_escolar = (int)$_POST['anio_escolar'];
        $estudiante_id = (int)$_POST['estudiante_id'];
        
        // Verificar permiso
        $stmt = $db->prepare("
            SELECT id FROM seccion_materias 
            WHERE seccion_id = ? AND materia_id = ? AND lapso = ? AND anio_escolar = ? AND docente_id = ?
        ");
        $stmt->execute([$seccion_id, $materia_id, $lapso, $anio_escolar, $docente_id]);
        $seccion_materia = $stmt->fetch();
        
        if (!$seccion_materia) {
            $error = "No tiene permisos para modificar notas de esta materia.";
        } else {
            $seccion_materia_id = $seccion_materia['id'];
            
            $e1 = floatval($_POST['evaluacion1'] ?? 0);
            $e2 = floatval($_POST['evaluacion2'] ?? 0);
            $e3 = floatval($_POST['evaluacion3'] ?? 0);
            $proyecto = floatval($_POST['proyecto'] ?? 0);
            $participacion = floatval($_POST['participacion'] ?? 0);
            $recuperacion = isset($_POST['recuperacion']) && $_POST['recuperacion'] !== '' ? floatval($_POST['recuperacion']) : null;
            
            // Calcular definitiva (promedio de los 3 momentos con valor > 0)
            $momentos = [];
            if ($e1 > 0) $momentos[] = $e1;
            if ($e2 > 0) $momentos[] = $e2;
            if ($e3 > 0) $momentos[] = $e3;
            $definitiva = count($momentos) > 0 ? array_sum($momentos) / count($momentos) : 0;
            
            // Nota final: si recuperacion > definitiva, usar recuperacion
            $promedio_final = ($recuperacion !== null && $recuperacion > $definitiva) ? $recuperacion : $definitiva;
            
            try {
                // Verificar si ya existe registro
                $check = $db->prepare("SELECT id FROM notas WHERE estudiante_id = ? AND seccion_materia_id = ?");
                $check->execute([$estudiante_id, $seccion_materia_id]);
                if ($check->fetch()) {
                    $stmtUpd = $db->prepare("UPDATE notas SET evaluacion1=?, evaluacion2=?, evaluacion3=?, proyecto=?, participacion=?, promedio=?, recuperacion=? WHERE estudiante_id=? AND seccion_materia_id=?");
                    $stmtUpd->execute([$e1, $e2, $e3, $proyecto, $participacion, $promedio_final, $recuperacion, $estudiante_id, $seccion_materia_id]);
                } else {
                    $stmtIns = $db->prepare("INSERT INTO notas (estudiante_id, seccion_materia_id, evaluacion1, evaluacion2, evaluacion3, proyecto, participacion, promedio, recuperacion, lapso, anio_escolar) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                    $stmtIns->execute([$estudiante_id, $seccion_materia_id, $e1, $e2, $e3, $proyecto, $participacion, $promedio_final, $recuperacion, $lapso, $anio_escolar]);
                }
                $message = "✅ Notas guardadas correctamente.";
                registrarLog('guardar_notas', 'notas', $seccion_materia_id, "Estudiante $estudiante_id");
            } catch (Exception $e) {
                $error = "❌ Error al guardar notas: " . $e->getMessage();
            }
        }
    }
    // Redirigir para evitar reenvío
    header("Location: asignar_notas.php?grado_id=$grado_id&seccion_id=$seccion_id&materia_id=$materia_id&lapso=$lapso&anio_escolar=$anio_escolar&message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Notas - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .nota-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-weight: bold; }
        .nota-aprobado { background: #d4edda; color: #155724; }
        .nota-reprobado { background: #f8d7da; color: #721c24; }
        .nota-pendiente { background: #fff3cd; color: #856404; }
        .btn-notas { padding: 6px 12px; font-size: 13px; }
        .modal-notas .modal-content { max-width: 550px; }
        .modal-notas .form-grid { grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-notas .full-width { grid-column: 1 / -1; }
        .modal-notas input[type="number"] { text-align: center; }
        .preview-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 15px 0; }
        .preview-box .preview-item { display: flex; justify-content: space-between; padding: 5px 0; }
        .preview-box .preview-item strong { color: #2c3e50; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('notas'); ?>
    <main class="main-content">
        <header class="main-header"><h1><i class="fas fa-edit"></i> Asignar Notas</h1></header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <div class="card no-print">
                <div class="card-header"><h2>Seleccionar Materia y Sección</h2></div>
                <div class="card-body">
                    <form method="GET" id="formFiltros" class="form-grid">
                        <div class="form-group">
                            <label>Grado</label>
                            <select name="grado_id" id="grado_id" class="form-control">
                                <option value="">-- Seleccione Grado --</option>
                                <?php foreach ($grados as $g): ?>
                                    <option value="<?php echo $g['id']; ?>" <?php echo $grado_id == $g['id'] ? 'selected' : ''; ?>><?php echo $g['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sección</label>
                            <select name="seccion_id" id="seccion_id" class="form-control">
                                <option value="">-- Seleccione sección --</option>
                                <?php foreach ($secciones_disponibles as $sec): ?>
                                    <option value="<?php echo $sec['id']; ?>" <?php echo ($seccion_id == $sec['id']) ? 'selected' : ''; ?>>
                                        Sección <?php echo $sec['letra']; ?> - Turno <?php echo ucfirst($sec['turno']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Materia</label>
                            <select name="materia_id" id="materia_id" class="form-control">
                                <option value="">-- Seleccione materia --</option>
                                <?php foreach ($materias as $m): ?>
                                    <option value="<?php echo $m['materia_id']; ?>" <?php echo ($materia_id == $m['materia_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['nombre']); ?> (<?php echo $m['area']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Lapso</label>
                            <select name="lapso" class="form-control">
                                <option value="1" <?php echo $lapso == 1 ? 'selected' : ''; ?>>1er Lapso</option>
                                <option value="2" <?php echo $lapso == 2 ? 'selected' : ''; ?>>2do Lapso</option>
                                <option value="3" <?php echo $lapso == 3 ? 'selected' : ''; ?>>3er Lapso</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Año Escolar</label>
                            <select name="anio_escolar" class="form-control">
                                <?php for ($y = 2024; $y <= date('Y')+1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $anio_escolar == $y ? 'selected' : ''; ?>><?php echo $y; ?> - <?php echo $y+1; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-actions full-width">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($seccion_id && $materia_id && !empty($estudiantes)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Materia: <?php echo htmlspecialchars($materia_seleccionada); ?></h2>
                        <div class="card-actions">
                            <span class="badge badge-info">Lapso <?php echo $lapso; ?></span>
                            <span class="badge badge-info"><?php echo $anio_escolar; ?> - <?php echo $anio_escolar+1; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Cédula</th>
                                        <th>Definitiva</th>
                                        <th>Recuperación</th>
                                        <th>Nota Final</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes as $est): 
                                        $nota = $notasExistentes[$est['id']] ?? null;
                                        $definitiva = $nota ? floatval($nota['promedio']) : 0;
                                        $recuperacion = $nota && $nota['recuperacion'] !== null ? floatval($nota['recuperacion']) : null;
                                        $nota_final = ($recuperacion !== null && $recuperacion > $definitiva) ? $recuperacion : $definitiva;
                                        
                                        if ($nota_final == 0) {
                                            $clase = 'nota-pendiente';
                                        } elseif ($nota_final >= 10) {
                                            $clase = 'nota-aprobado';
                                        } else {
                                            $clase = 'nota-reprobado';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($est['apellidos'] . ', ' . $est['nombres']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                                        <td><span class="nota-badge <?php echo $clase; ?>"><?php echo number_format($definitiva, 2); ?></span></td>
                                        <td>
                                            <?php if ($recuperacion !== null): ?>
                                                <span class="nota-badge nota-aprobado"><?php echo number_format($recuperacion, 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="nota-badge <?php echo $clase; ?>" style="font-size: 15px;"><?php echo number_format($nota_final, 2); ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-notas" onclick='abrirModalNotas(<?php echo json_encode([
                                                "id" => $est["id"],
                                                "nombre" => $est["apellidos"] . ", " . $est["nombres"],
                                                "cedula" => $est["cedula"],
                                                "e1" => $nota ? floatval($nota["evaluacion1"]) : 0,
                                                "e2" => $nota ? floatval($nota["evaluacion2"]) : 0,
                                                "e3" => $nota ? floatval($nota["evaluacion3"]) : 0,
                                                "proyecto" => $nota ? floatval($nota["proyecto"]) : 0,
                                                "participacion" => $nota ? floatval($nota["participacion"]) : 0,
                                                "recuperacion" => $recuperacion
                                            ]); ?>)'>
                                                <i class="fas fa-edit"></i> Asignar Notas
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($seccion_id && $materia_id && empty($estudiantes)): ?>
                <div class="alert alert-warning">No hay estudiantes activos en esta sección.</div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal para asignar notas -->
<div id="modalNotas" class="modal modal-notas">
    <div class="modal-content">
        <div class="modal-close" onclick="cerrarModalNotas()">&times;</div>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-edit"></i> Asignar Notas</h2>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 15px;">
                    <strong id="modalEstudianteNombre"></strong><br>
                    <small class="text-muted" id="modalEstudianteCedula"></small>
                </div>
                
                <form method="POST" id="formNotaIndividual">
                    <input type="hidden" name="guardar_nota_individual" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
                    <input type="hidden" name="materia_id" value="<?php echo $materia_id; ?>">
                    <input type="hidden" name="lapso" value="<?php echo $lapso; ?>">
                    <input type="hidden" name="anio_escolar" value="<?php echo $anio_escolar; ?>">
                    <input type="hidden" name="estudiante_id" id="modalEstudianteId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Evaluación 1</label>
                            <input type="number" step="0.01" min="0" max="20" name="evaluacion1" id="modalE1" class="form-control" oninput="calcularPromedioModal()">
                        </div>
                        <div class="form-group">
                            <label>Evaluación 2</label>
                            <input type="number" step="0.01" min="0" max="20" name="evaluacion2" id="modalE2" class="form-control" oninput="calcularPromedioModal()">
                        </div>
                        <div class="form-group">
                            <label>Evaluación 3</label>
                            <input type="number" step="0.01" min="0" max="20" name="evaluacion3" id="modalE3" class="form-control" oninput="calcularPromedioModal()">
                        </div>
                        <div class="form-group">
                            <label>Proyecto</label>
                            <input type="number" step="0.01" min="0" max="20" name="proyecto" id="modalProyecto" class="form-control" oninput="calcularPromedioModal()">
                        </div>
                        <div class="form-group">
                            <label>Participación</label>
                            <input type="number" step="0.01" min="0" max="20" name="participacion" id="modalParticipacion" class="form-control" oninput="calcularPromedioModal()">
                        </div>
                        <div class="form-group">
                            <label>Recuperación</label>
                            <input type="number" step="0.01" min="0" max="20" name="recuperacion" id="modalRecuperacion" class="form-control" oninput="calcularPromedioModal()">
                        </div>
                    </div>
                    
                    <div class="preview-box">
                        <div class="preview-item">
                            <span>Definitiva (promedio de los 3 momentos):</span>
                            <strong id="previewDefinitiva">0.00</strong>
                        </div>
                        <div class="preview-item">
                            <span>Nota Final (mayor entre D y R):</span>
                            <strong id="previewFinal" style="color: #27ae60;">0.00</strong>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Guardar Notas</button>
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalNotas()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    var APP_URL = "<?php echo APP_URL; ?>";

    function abrirModalNotas(datos) {
        document.getElementById('modalEstudianteId').value = datos.id;
        document.getElementById('modalEstudianteNombre').textContent = datos.nombre;
        document.getElementById('modalEstudianteCedula').textContent = 'Cédula: ' + datos.cedula;
        
        document.getElementById('modalE1').value = datos.e1 > 0 ? datos.e1 : '';
        document.getElementById('modalE2').value = datos.e2 > 0 ? datos.e2 : '';
        document.getElementById('modalE3').value = datos.e3 > 0 ? datos.e3 : '';
        document.getElementById('modalProyecto').value = datos.proyecto > 0 ? datos.proyecto : '';
        document.getElementById('modalParticipacion').value = datos.participacion > 0 ? datos.participacion : '';
        document.getElementById('modalRecuperacion').value = datos.recuperacion !== null ? datos.recuperacion : '';
        
        calcularPromedioModal();
        document.getElementById('modalNotas').style.display = 'block';
    }

    function cerrarModalNotas() {
        document.getElementById('modalNotas').style.display = 'none';
    }

    function calcularPromedioModal() {
        const e1 = parseFloat(document.getElementById('modalE1').value) || 0;
        const e2 = parseFloat(document.getElementById('modalE2').value) || 0;
        const e3 = parseFloat(document.getElementById('modalE3').value) || 0;
        const recuperacion = parseFloat(document.getElementById('modalRecuperacion').value) || 0;
        
        let momentos = [];
        if (e1 > 0) momentos.push(e1);
        if (e2 > 0) momentos.push(e2);
        if (e3 > 0) momentos.push(e3);
        let definitiva = momentos.length > 0 ? momentos.reduce((a,b) => a+b, 0) / momentos.length : 0;
        
        document.getElementById('previewDefinitiva').textContent = definitiva.toFixed(2);
        
        let notaFinal = (recuperacion > definitiva && recuperacion > 0) ? recuperacion : definitiva;
        document.getElementById('previewFinal').textContent = notaFinal.toFixed(2);
    }

    window.onclick = function(e) {
        if (e.target == document.getElementById('modalNotas')) {
            cerrarModalNotas();
        }
    }

    // Al cambiar el grado, recargar la página para cargar las secciones
    document.getElementById('grado_id')?.addEventListener('change', function() {
        const gradoId = this.value;
        const lapso = document.querySelector('select[name="lapso"]').value;
        const anio = document.querySelector('select[name="anio_escolar"]').value;
        if (gradoId) {
            window.location.href = `?grado_id=${gradoId}&lapso=${lapso}&anio_escolar=${anio}`;
        } else {
            window.location.href = `?lapso=${lapso}&anio_escolar=${anio}`;
        }
    });

    // Al cambiar la sección, recargar la página para cargar las materias
    document.getElementById('seccion_id')?.addEventListener('change', function() {
        const seccionId = this.value;
        const gradoId = document.getElementById('grado_id').value;
        const lapso = document.querySelector('select[name="lapso"]').value;
        const anio = document.querySelector('select[name="anio_escolar"]').value;
        if (seccionId && gradoId) {
            window.location.href = `?grado_id=${gradoId}&seccion_id=${seccionId}&lapso=${lapso}&anio_escolar=${anio}`;
        }
    });
</script>

<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
</body>
</html>