<?php
// asignar_notas.php - Asignación de notas con 3 momentos + Definitiva + Recuperación
// Modificado para filtrar por docente y generar alertas de bajo rendimiento
require_once 'config.php';
requireAuth();

$db = getDBConnection();
$message = '';
$error = '';

// Obtener el ID del docente logueado
$docente_id = $_SESSION['usuario_id'];

// Obtener listas para filtros
$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$secciones = [];
$materias = [];
$estudiantes = [];
$materia_seleccionada = null;
$seccion_id = $_GET['seccion_id'] ?? $_POST['seccion_id'] ?? 0;
$materia_id = $_GET['materia_id'] ?? $_POST['materia_id'] ?? 0;
$lapso = $_GET['lapso'] ?? $_POST['lapso'] ?? 1;
$anio_escolar = $_GET['anio_escolar'] ?? date('Y');

// Si hay sección seleccionada, cargar materias de esa sección (solo las del docente)
if ($seccion_id) {
    $stmt = $db->prepare("
        SELECT sm.id, m.nombre, m.area, CONCAT(u.nombres, ' ', u.apellidos) as docente
        FROM seccion_materias sm
        JOIN materias m ON sm.materia_id = m.id
        LEFT JOIN usuarios u ON sm.docente_id = u.id
        WHERE sm.seccion_id = ? AND sm.anio_escolar = ? AND sm.lapso = ? AND sm.docente_id = ?
        ORDER BY m.area, m.nombre
    ");
    $stmt->execute([$seccion_id, $anio_escolar, $lapso, $docente_id]);
    $materias = $stmt->fetchAll();
}

// Si hay materia seleccionada, cargar estudiantes de la sección y sus notas (optimizado)
if ($seccion_id && $materia_id) {
    // Verificar que el docente tenga permiso sobre esta materia-sección
    $stmtPermiso = $db->prepare("
        SELECT id FROM seccion_materias 
        WHERE seccion_id = ? AND materia_id = ? AND lapso = ? AND anio_escolar = ? AND docente_id = ?
    ");
    $stmtPermiso->execute([$seccion_id, $materia_id, $lapso, $anio_escolar, $docente_id]);
    if (!$stmtPermiso->fetch()) {
        // Si no tiene permiso, redirigir o mostrar error
        $error = "No tiene permisos para acceder a esta materia en esta sección.";
        $materia_id = 0;
    } else {
        $stmt = $db->prepare("
            SELECT e.id, e.cedula, e.nombres, e.apellidos
            FROM estudiantes e
            WHERE e.seccion_id = ? AND e.estado = 'activo'
            ORDER BY e.apellidos, e.nombres
        ");
        $stmt->execute([$seccion_id]);
        $estudiantes = $stmt->fetchAll();

        // Obtener todas las notas de la sección-materia de una vez
        $notasExistentes = [];
        $stmtNotas = $db->prepare("
            SELECT estudiante_id, evaluacion1, evaluacion2, evaluacion3, proyecto, participacion, promedio, recuperacion 
            FROM notas 
            WHERE seccion_materia_id = (SELECT id FROM seccion_materias WHERE seccion_id = ? AND materia_id = ? AND lapso = ? AND anio_escolar = ?)
        ");
        $stmtNotas->execute([$seccion_id, $materia_id, $lapso, $anio_escolar]);
        while ($row = $stmtNotas->fetch()) {
            $notasExistentes[$row['estudiante_id']] = $row;
        }

        $stmtMat = $db->prepare("SELECT nombre FROM materias WHERE id = ?");
        $stmtMat->execute([$materia_id]);
        $materia_seleccionada = $stmtMat->fetchColumn();
    }
}

// Procesar guardado de notas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_notas'])) {
    // Validar CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        $seccion_id = (int)$_POST['seccion_id'];
        $materia_id = (int)$_POST['materia_id'];
        $lapso = (int)$_POST['lapso'];
        $anio_escolar = (int)$_POST['anio_escolar'];

        // Obtener el seccion_materia_id y verificar que pertenezca al docente
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
            $notas = $_POST['notas'] ?? [];

            // Obtener nota mínima desde configuración
            $nota_minima = 10; // fallback
            $stmtConf = $db->query("SELECT valor FROM configuracion WHERE clave = 'nota_minima'");
            $valor = $stmtConf->fetchColumn();
            if ($valor !== false) {
                $nota_minima = (float)$valor;
            }

            try {
                $db->beginTransaction();
                foreach ($notas as $estudiante_id => $campos) {
                    $e1 = floatval($campos['evaluacion1'] ?? 0);
                    $e2 = floatval($campos['evaluacion2'] ?? 0);
                    $e3 = floatval($campos['evaluacion3'] ?? 0);
                    $proyecto = floatval($campos['proyecto'] ?? 0);
                    $participacion = floatval($campos['participacion'] ?? 0);
                    $recuperacion = isset($campos['recuperacion']) && $campos['recuperacion'] !== '' ? floatval($campos['recuperacion']) : null;

                    // Calcular definitiva (D) como promedio de los 3 momentos
                    $definitiva = calcularDefinitiva($e1, $e2, $e3);

                    // Obtener la nota final (si R > D, usar R)
                    $promedio_final = obtenerNotaFinal($definitiva, $recuperacion);

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

                    // --- GENERAR ALERTA DE BAJO RENDIMIENTO ---
                    if ($promedio_final < $nota_minima && $promedio_final > 0) {
                        // Obtener nombre de la materia
                        $stmtMat = $db->prepare("SELECT nombre FROM materias WHERE id = ?");
                        $stmtMat->execute([$materia_id]);
                        $nombre_materia = $stmtMat->fetchColumn();

                        $mensaje = "El estudiante (ID: $estudiante_id) tiene un promedio de $promedio_final en la materia '$nombre_materia' (lapso $lapso), por debajo de la nota mínima ($nota_minima).";
                        $stmtAlerta = $db->prepare("INSERT INTO alertas (estudiante_id, mensaje, tipo) VALUES (?, ?, 'bajo_rendimiento')");
                        $stmtAlerta->execute([$estudiante_id, $mensaje]);
                    }
                }
                $db->commit();
                $message = "✅ Notas guardadas correctamente.";
                registrarLog('guardar_notas', 'notas', $seccion_materia_id, "Sección $seccion_id, Materia $materia_id, Lapso $lapso");
            } catch (Exception $e) {
                $db->rollBack();
                $error = "❌ Error al guardar notas: " . $e->getMessage();
            }
        }
    }
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
        .notas-table input { width: 60px; text-align: center; }
        .promedio-cell { font-weight: bold; background: #f0f9ff; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .table-responsive { overflow-x: auto; }
        @media print { .no-print { display: none; } }
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
                                    <option value="<?php echo $g['id']; ?>" <?php echo ($_GET['grado_id'] ?? '') == $g['id'] ? 'selected' : ''; ?>><?php echo $g['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sección</label>
                            <select name="seccion_id" id="seccion_id" class="form-control" <?php echo empty($_GET['grado_id']) ? 'disabled' : ''; ?>>
                                <option value="">-- Primero seleccione grado --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Materia</label>
                            <select name="materia_id" id="materia_id" class="form-control" <?php echo empty($seccion_id) ? 'disabled' : ''; ?>>
                                <option value="">-- Primero seleccione sección --</option>
                                <?php foreach ($materias as $m): ?>
                                    <option value="<?php echo $m['id']; ?>" <?php echo ($materia_id == $m['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['nombre']); ?> (<?php echo $m['area']; ?>)</option>
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
                                <?php for ($y = date('Y')-1; $y <= date('Y')+1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $anio_escolar == $y ? 'selected' : ''; ?>><?php echo $y; ?> - <?php echo $y+1; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-actions full-width">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                            <?php if ($seccion_id && $materia_id): ?>
                                <a href="?seccion_id=<?php echo $seccion_id; ?>&materia_id=<?php echo $materia_id; ?>&lapso=<?php echo $lapso; ?>&anio_escolar=<?php echo $anio_escolar; ?>" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> Recargar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($seccion_id && $materia_id && !empty($estudiantes)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Materia: <?php echo htmlspecialchars($materia_seleccionada); ?> - Sección: <?php echo htmlspecialchars($seccion_id); ?></h2>
                        <div class="card-actions">
                            <button type="button" class="btn btn-info btn-sm" onclick="calcularTodosPromedios()"><i class="fas fa-calculator"></i> Calcular todos los promedios</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formNotas">
                            <input type="hidden" name="guardar_notas" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
                            <input type="hidden" name="materia_id" value="<?php echo $materia_id; ?>">
                            <input type="hidden" name="lapso" value="<?php echo $lapso; ?>">
                            <input type="hidden" name="anio_escolar" value="<?php echo $anio_escolar; ?>">
                            <div class="table-responsive">
                                <table class="data-table notas-table">
                                    <thead>
                                        <tr>
                                            <th>Estudiante</th>
                                            <th>Cédula</th>
                                            <th>Eval 1</th>
                                            <th>Eval 2</th>
                                            <th>Eval 3</th>
                                            <th>Proyecto</th>
                                            <th>Particip.</th>
                                            <th>Definitiva (D)</th>
                                            <th>Recuperación (R)</th>
                                            <th>Nota Final</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($estudiantes as $est): 
                                            $notaExistente = $notasExistentes[$est['id']] ?? null;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($est['apellidos'] . ', ' . $est['nombres']); ?></td>
                                            <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                                            <td><input type="number" step="0.01" min="0" max="20" name="notas[<?php echo $est['id']; ?>][evaluacion1]" class="form-control nota" value="<?php echo $notaExistente ? number_format($notaExistente['evaluacion1'], 2) : ''; ?>" oninput="calcularPromedio(<?php echo $est['id']; ?>)"></td>
                                            <td><input type="number" step="0.01" min="0" max="20" name="notas[<?php echo $est['id']; ?>][evaluacion2]" class="form-control nota" value="<?php echo $notaExistente ? number_format($notaExistente['evaluacion2'], 2) : ''; ?>" oninput="calcularPromedio(<?php echo $est['id']; ?>)"></td>
                                            <td><input type="number" step="0.01" min="0" max="20" name="notas[<?php echo $est['id']; ?>][evaluacion3]" class="form-control nota" value="<?php echo $notaExistente ? number_format($notaExistente['evaluacion3'], 2) : ''; ?>" oninput="calcularPromedio(<?php echo $est['id']; ?>)"></td>
                                            <td><input type="number" step="0.01" min="0" max="20" name="notas[<?php echo $est['id']; ?>][proyecto]" class="form-control nota" value="<?php echo $notaExistente ? number_format($notaExistente['proyecto'], 2) : ''; ?>" oninput="calcularPromedio(<?php echo $est['id']; ?>)"></td>
                                            <td><input type="number" step="0.01" min="0" max="20" name="notas[<?php echo $est['id']; ?>][participacion]" class="form-control nota" value="<?php echo $notaExistente ? number_format($notaExistente['participacion'], 2) : ''; ?>" oninput="calcularPromedio(<?php echo $est['id']; ?>)"></td>
                                            <td id="definitiva_<?php echo $est['id']; ?>" class="promedio-cell"><?php echo $notaExistente ? number_format($notaExistente['promedio'], 2) : '0.00'; ?></td>
                                            <td><input type="number" step="0.01" min="0" max="20" name="notas[<?php echo $est['id']; ?>][recuperacion]" class="form-control nota" value="<?php echo $notaExistente && $notaExistente['recuperacion'] !== null ? number_format($notaExistente['recuperacion'], 2) : ''; ?>" oninput="calcularPromedio(<?php echo $est['id']; ?>)"></td>
                                            <td id="final_<?php echo $est['id']; ?>" class="promedio-cell" style="background:#d4edda;"><?php 
                                                if ($notaExistente) {
                                                    $d = floatval($notaExistente['promedio']);
                                                    $r = floatval($notaExistente['recuperacion']);
                                                    echo number_format(($r > $d && $r > 0) ? $r : $d, 2);
                                                } else {
                                                    echo '0.00';
                                                }
                                            ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Guardar Notas</button>
                                <button type="button" class="btn btn-secondary" onclick="window.location.reload();"><i class="fas fa-redo"></i> Reiniciar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($seccion_id && $materia_id && empty($estudiantes)): ?>
                <div class="alert alert-warning">No hay estudiantes activos en esta sección.</div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
    var APP_URL = "<?php echo APP_URL; ?>";

    function calcularPromedio(estudianteId) {
        const eval1 = parseFloat(document.querySelector(`input[name="notas[${estudianteId}][evaluacion1]"]`).value) || 0;
        const eval2 = parseFloat(document.querySelector(`input[name="notas[${estudianteId}][evaluacion2]"]`).value) || 0;
        const eval3 = parseFloat(document.querySelector(`input[name="notas[${estudianteId}][evaluacion3]"]`).value) || 0;
        const proyecto = parseFloat(document.querySelector(`input[name="notas[${estudianteId}][proyecto]"]`).value) || 0;
        const participacion = parseFloat(document.querySelector(`input[name="notas[${estudianteId}][participacion]"]`).value) || 0;
        const recuperacion = parseFloat(document.querySelector(`input[name="notas[${estudianteId}][recuperacion]"]`).value) || 0;

        // Calcular definitiva (promedio de momentos con valores > 0)
        let momentos = [];
        if (eval1 > 0) momentos.push(eval1);
        if (eval2 > 0) momentos.push(eval2);
        if (eval3 > 0) momentos.push(eval3);
        let definitiva = momentos.length > 0 ? momentos.reduce((a,b) => a+b, 0) / momentos.length : 0;
        document.getElementById(`definitiva_${estudianteId}`).textContent = definitiva.toFixed(2);

        // Nota final: si recuperacion > definitiva, usar recuperacion
        let notaFinal = (recuperacion > definitiva && recuperacion > 0) ? recuperacion : definitiva;
        document.getElementById(`final_${estudianteId}`).textContent = notaFinal.toFixed(2);
    }

    function calcularTodosPromedios() {
        <?php foreach ($estudiantes as $est): ?>
            calcularPromedio(<?php echo $est['id']; ?>);
        <?php endforeach; ?>
        mostrarToast("Todos los promedios han sido actualizados", "success");
    }

    // Carga dinámica de secciones
    document.getElementById('grado_id')?.addEventListener('change', function() {
        const gradoId = this.value;
        const seccionSelect = document.getElementById('seccion_id');
        const materiaSelect = document.getElementById('materia_id');
        if (!gradoId) {
            seccionSelect.innerHTML = '<option value="">-- Seleccione sección --</option>';
            seccionSelect.disabled = true;
            materiaSelect.innerHTML = '<option value="">-- Seleccione materia --</option>';
            materiaSelect.disabled = true;
            return;
        }
        seccionSelect.disabled = false;
        seccionSelect.innerHTML = '<option value="">Cargando...</option>';
        fetch(APP_URL + '/ajax/cargar_secciones.php?grado_id=' + gradoId)
            .then(res => res.json())
            .then(data => {
                seccionSelect.innerHTML = '<option value="">-- Seleccione sección --</option>';
                if (data.length) {
                    data.forEach(sec => {
                        const opt = document.createElement('option');
                        opt.value = sec.id;
                        opt.textContent = `Sección ${sec.letra} - Turno ${sec.turno}`;
                        seccionSelect.appendChild(opt);
                    });
                    seccionSelect.disabled = false;
                } else {
                    seccionSelect.innerHTML = '<option value="">No hay secciones</option>';
                    seccionSelect.disabled = true;
                }
                materiaSelect.innerHTML = '<option value="">-- Seleccione materia --</option>';
                materiaSelect.disabled = true;
            })
            .catch(err => {
                console.error(err);
                seccionSelect.innerHTML = '<option value="">Error al cargar</option>';
                seccionSelect.disabled = true;
            });
    });

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

<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script
</body>
</html>