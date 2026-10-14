<?php
// asignar_notas.php - Carga de notas por lapso (VERSIÓN 4.0)
require_once 'config.php';
require_once 'functions.php';
requireAuth();

$db = getDBConnection();
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

$docente_id = $_SESSION['usuario_id'];
$rol = $_SESSION['usuario_rol'];

// Año escolar activo
$anio_escolar_texto = getConfig($db, 'anio_escolar_activo', date('Y') . '-' . (date('Y') + 1));
$anio_escolar = (int)explode('-', $anio_escolar_texto)[0];

// Filtros
$grado_id = (int)($_GET['grado_id'] ?? 0);
$seccion_id = (int)($_GET['seccion_id'] ?? 0);
$materia_id = (int)($_GET['materia_id'] ?? 0);
$lapso = (int)($_GET['lapso'] ?? 1);

// =========================================================
// GUARDAR NOTAS
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_notas'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token inválido.";
    } else {
        $seccion_id_post = (int)$_POST['seccion_id'];
        $materia_id_post = (int)$_POST['materia_id'];
        $lapso_post = (int)$_POST['lapso'];
        $anio_post = (int)$_POST['anio_escolar'];
        $notas_post = $_POST['notas'] ?? [];
        
        try {
            // Buscar el seccion_materia_id
            $stmt = $db->prepare("SELECT id FROM seccion_materias WHERE seccion_id = ? AND materia_id = ? AND anio_escolar = ?");
            $stmt->execute([$seccion_id_post, $materia_id_post, $anio_post]);
            $sm = $stmt->fetch();
            
            if (!$sm) throw new Exception("No existe asignación de esta materia para esta sección.");
            $seccion_materia_id = $sm['id'];
            
            $guardadas = 0;
            foreach ($notas_post as $estudiante_id => $nota) {
                $estudiante_id = (int)$estudiante_id;
                $nota = trim($nota);
                
                if ($nota === '') {
                    // Borrar si existe
                    $stmt = $db->prepare("DELETE FROM notas WHERE estudiante_id = ? AND seccion_materia_id = ? AND lapso = ? AND es_recuperacion = 0 AND anio_escolar = ?");
                    $stmt->execute([$estudiante_id, $seccion_materia_id, $lapso_post, $anio_post]);
                    continue;
                }
                
                $nota = floatval($nota);
                if ($nota < 0 || $nota > 20) continue;
                
                // Insertar o actualizar
                $stmt = $db->prepare("
                    INSERT INTO notas (estudiante_id, seccion_materia_id, lapso, nota, es_recuperacion, anio_escolar)
                    VALUES (?, ?, ?, ?, 0, ?)
                    ON DUPLICATE KEY UPDATE nota = VALUES(nota)
                ");
                $stmt->execute([$estudiante_id, $seccion_materia_id, $lapso_post, $nota, $anio_post]);
                $guardadas++;
            }
            
            registrarLog('guardar_notas', 'notas', $seccion_materia_id, "Lapso $lapso_post - $guardadas notas");
            $message = "✅ $guardadas notas guardadas correctamente.";
        } catch (Exception $e) {
            $error = "❌ " . $e->getMessage();
        }
    }
    header("Location: asignar_notas.php?grado_id=$grado_id&seccion_id=$seccion_id&materia_id=$materia_id&lapso=$lapso&message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// =========================================================
// CARGAR DATOS
// =========================================================
$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();

$secciones_disponibles = [];
if ($grado_id) {
    $stmt = $db->prepare("SELECT * FROM secciones WHERE grado_id = ? AND activo = 1 ORDER BY letra");
    $stmt->execute([$grado_id]);
    $secciones_disponibles = $stmt->fetchAll();
}

$materias_disponibles = [];
if ($seccion_id) {
    // Si es docente, solo sus materias. Si es admin, todas.
    if ($rol === 'docente') {
        $stmt = $db->prepare("
            SELECT sm.id, m.id AS materia_id, m.nombre, m.area
            FROM seccion_materias sm
            JOIN materias m ON sm.materia_id = m.id
            WHERE sm.seccion_id = ? AND sm.anio_escolar = ? AND sm.docente_id = ?
            ORDER BY m.nombre
        ");
        $stmt->execute([$seccion_id, $anio_escolar, $docente_id]);
    } else {
        $stmt = $db->prepare("
            SELECT sm.id, m.id AS materia_id, m.nombre, m.area
            FROM seccion_materias sm
            JOIN materias m ON sm.materia_id = m.id
            WHERE sm.seccion_id = ? AND sm.anio_escolar = ?
            ORDER BY m.nombre
        ");
        $stmt->execute([$seccion_id, $anio_escolar]);
    }
    $materias_disponibles = $stmt->fetchAll();
}

$estudiantes = [];
$notas_existentes = [];
if ($seccion_id && $materia_id) {
    // Buscar el seccion_materia_id
    $stmt = $db->prepare("SELECT id FROM seccion_materias WHERE seccion_id = ? AND materia_id = ? AND anio_escolar = ?");
    $stmt->execute([$seccion_id, $materia_id, $anio_escolar]);
    $sm = $stmt->fetch();
    
    if ($sm) {
        $seccion_materia_id = $sm['id'];
        
        // Estudiantes
        $stmt = $db->prepare("SELECT id, cedula, nombres, apellidos FROM estudiantes WHERE seccion_id = ? AND estado = 'activo' ORDER BY apellidos, nombres");
        $stmt->execute([$seccion_id]);
        $estudiantes = $stmt->fetchAll();
        
        // Notas existentes del lapso
        $stmt = $db->prepare("SELECT estudiante_id, nota FROM notas WHERE seccion_materia_id = ? AND lapso = ? AND es_recuperacion = 0 AND anio_escolar = ?");
        $stmt->execute([$seccion_materia_id, $lapso, $anio_escolar]);
        foreach ($stmt as $n) {
            $notas_existentes[$n['estudiante_id']] = $n['nota'];
        }
    }
}

$materia_actual = null;
foreach ($materias_disponibles as $m) {
    if ($m['materia_id'] == $materia_id) {
        $materia_actual = $m;
        break;
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
        .nota-input {
            width: 70px;
            text-align: center;
            font-weight: bold;
            font-size: 15px;
        }
        .nota-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 13px;
        }
        .nota-aprobado { background: #d4edda; color: #155724; }
        .nota-reprobado { background: #f8d7da; color: #721c24; }
        .nota-vacia { background: #e1e4e8; color: #586069; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('notas'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-edit"></i> Asignar Notas</h1>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header"><h2>Seleccionar Sección y Materia</h2></div>
                <div class="card-body">
                    <form method="GET">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Grado</label>
                                <select name="grado_id" class="form-control" onchange="this.form.submit()">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($grados as $g): ?>
                                        <option value="<?php echo $g['id']; ?>" <?php echo $grado_id == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($grado_id): ?>
                            <div class="form-group">
                                <label>Sección</label>
                                <select name="seccion_id" class="form-control" onchange="this.form.submit()">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($secciones_disponibles as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo $seccion_id == $s['id'] ? 'selected' : ''; ?>>Sección <?php echo $s['letra']; ?> (<?php echo $s['turno']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <?php if ($seccion_id && !empty($materias_disponibles)): ?>
                            <div class="form-group">
                                <label>Materia</label>
                                <select name="materia_id" class="form-control" onchange="this.form.submit()">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($materias_disponibles as $m): ?>
                                        <option value="<?php echo $m['materia_id']; ?>" <?php echo $materia_id == $m['materia_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <?php if ($materia_id): ?>
                            <div class="form-group">
                                <label>Lapso</label>
                                <select name="lapso" class="form-control" onchange="this.form.submit()">
                                    <option value="1" <?php echo $lapso == 1 ? 'selected' : ''; ?>>1er Lapso</option>
                                    <option value="2" <?php echo $lapso == 2 ? 'selected' : ''; ?>>2do Lapso</option>
                                    <option value="3" <?php echo $lapso == 3 ? 'selected' : ''; ?>>3er Lapso</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($seccion_id && $materia_id && !empty($estudiantes)): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="guardar_notas" value="1">
                <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
                <input type="hidden" name="materia_id" value="<?php echo $materia_id; ?>">
                <input type="hidden" name="lapso" value="<?php echo $lapso; ?>">
                <input type="hidden" name="anio_escolar" value="<?php echo $anio_escolar; ?>">

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-table"></i> <?php echo htmlspecialchars($materia_actual['nombre'] ?? 'Materia'); ?> - <?php echo $lapso; ?>er Lapso</h2>
                        <div class="card-actions">
                            <span class="badge badge-info"><?php echo $anio_escolar_texto; ?></span>
                            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Guardar Notas</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nº</th>
                                        <th>Apellidos y Nombres</th>
                                        <th>Cédula</th>
                                        <th style="text-align: center;">Nota (0-20)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($estudiantes as $est): 
                                        $nota_actual = $notas_existentes[$est['id']] ?? '';
                                    ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($est['apellidos'] . ', ' . $est['nombres']); ?></td>
                                        <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                                        <td style="text-align: center;">
                                            <input type="number" 
                                                   name="notas[<?php echo $est['id']; ?>]" 
                                                   class="form-control nota-input" 
                                                   value="<?php echo htmlspecialchars($nota_actual); ?>"
                                                   min="0" max="20" step="0.01"
                                                   placeholder="—">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top: 15px; text-align: right;">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Deja en blanco si el estudiante aún no tiene nota.
                            </small>
                        </div>
                    </div>
                </div>
            </form>
            <?php elseif ($seccion_id && $materia_id): ?>
                <div class="alert alert-warning">No hay estudiantes activos en esta sección.</div>
            <?php elseif ($seccion_id && empty($materias_disponibles)): ?>
                <div class="alert alert-warning">No tienes materias asignadas en esta sección.</div>
            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>