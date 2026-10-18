<?php
// carga_academica.php - Asignación de docentes a materias por sección
require_once 'config.php';
require_once 'functions.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'admin') { header('Location: dashboard.php'); exit; }
$db = getDBConnection();

// Año escolar activo
$anio_escolar_texto = getConfig($db, 'anio_escolar_activo', date('Y') . '-' . (date('Y') + 1));
$anio_escolar = (int)explode('-', $anio_escolar_texto)[0];

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Filtros
$grado_id = (int)($_GET['grado_id'] ?? 0);
$seccion_id = (int)($_GET['seccion_id'] ?? 0);

// PROCESAR GUARDADO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_asignaciones'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $seccion_id_post = (int)$_POST['seccion_id'];
        $docentes_post = $_POST['docentes'] ?? []; // Array con formato [materia_id => docente_id]
        
        try {
            $db->beginTransaction();
            foreach ($docentes_post as $materia_id => $docente_id) {
                $materia_id = (int)$materia_id;
                $docente_id = (int)$docente_id;
                
                // Buscar si ya existe la asignación
                $stmt = $db->prepare("SELECT id FROM seccion_materias WHERE seccion_id = ? AND materia_id = ? AND anio_escolar = ?");
                $stmt->execute([$seccion_id_post, $materia_id, $anio_escolar]);
                $asignacion = $stmt->fetch();
                
                if ($docente_id > 0) {
                    if ($asignacion) {
                        // Actualizar docente
                        $stmt = $db->prepare("UPDATE seccion_materias SET docente_id = ? WHERE id = ?");
                        $stmt->execute([$docente_id, $asignacion['id']]);
                    } else {
                        // Crear nueva asignación
                        $stmt = $db->prepare("INSERT INTO seccion_materias (seccion_id, materia_id, docente_id, anio_escolar, lapso) VALUES (?, ?, ?, ?, 1)");
                        $stmt->execute([$seccion_id_post, $materia_id, $docente_id, $anio_escolar]);
                    }
                } else {
                    // Si seleccionó "Sin asignar", borrar la asignación si existe
                    if ($asignacion) {
                        $stmt = $db->prepare("DELETE FROM seccion_materias WHERE id = ?");
                        $stmt->execute([$asignacion['id']]);
                    }
                }
            }
            $db->commit();
            $message = "✅ Carga académica actualizada correctamente.";
            registrarLog('actualizar_carga_academica', 'seccion_materias', $seccion_id_post, "Año: $anio_escolar");
        } catch (Exception $e) {
            $db->rollBack();
            $error = "❌ Error al guardar: " . $e->getMessage();
        }
    }
}

// CARGAR DATOS
$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();

$secciones_disponibles = [];
if ($grado_id) {
    $stmt = $db->prepare("SELECT * FROM secciones WHERE grado_id = ? AND activo = 1 ORDER BY letra");
    $stmt->execute([$grado_id]);
    $secciones_disponibles = $stmt->fetchAll();
}

// Obtener docentes activos
$docentes = $db->query("SELECT id, nombres, apellidos FROM usuarios WHERE rol = 'docente' AND activo = 1 ORDER BY apellidos, nombres")->fetchAll();

// Obtener materias y asignaciones actuales
$materias_asignadas = [];
if ($seccion_id) {
    $stmt = $db->prepare("
        SELECT m.id AS materia_id, m.nombre AS materia_nombre, m.area,
               sm.id AS asignacion_id, sm.docente_id
        FROM materias m
        LEFT JOIN seccion_materias sm ON sm.materia_id = m.id AND sm.seccion_id = ? AND sm.anio_escolar = ?
        WHERE m.activo = 1
        ORDER BY m.area, m.nombre
    ");
    $stmt->execute([$seccion_id, $anio_escolar]);
    $materias_asignadas = $stmt->fetchAll();
}

$materia_actual = null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carga Académica - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .teacher-select { width: 100%; max-width: 400px; padding: 6px; }
        .table-middle td { vertical-align: middle !important; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('carga_academica'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-chalkboard-teacher"></i> Carga Académica</h1>
            <div class="header-actions">
                <span class="badge badge-info" style="font-size: 14px; padding: 8px 15px;">
                    Año Escolar: <?php echo $anio_escolar_texto; ?>
                </span>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <div class="card no-print">
                <div class="card-header"><h2>Seleccionar Sección</h2></div>
                <div class="card-body">
                    <form method="GET" class="form-grid">
                        <div class="form-group">
                            <label>Grado</label>
                            <select name="grado_id" class="form-control" onchange="this.form.submit()">
                                <option value="">Seleccione...</option>
                                <?php foreach ($grados as $g): ?>
                                    <option value="<?php echo $g['id']; ?>" <?php echo $grado_id == $g['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($grado_id): ?>
                        <div class="form-group">
                            <label>Sección</label>
                            <select name="seccion_id" class="form-control" onchange="this.form.submit()">
                                <option value="">Seleccione...</option>
                                <?php foreach ($secciones_disponibles as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $seccion_id == $s['id'] ? 'selected' : ''; ?>>
                                        Sección <?php echo $s['letra']; ?> (<?php echo $s['turno']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if ($seccion_id && !empty($materias_asignadas)): ?>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="guardar_asignaciones" value="1">
                <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-book"></i> Asignación de Docentes por Materia</h2>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table table-middle">
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th>Área</th>
                                        <th>Docente Asignado</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materias_asignadas as $mat): 
                                        $docente_actual = $mat['docente_id'] ?? '';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($mat['materia_nombre']); ?></strong></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($mat['area']); ?></span></td>
                                        <td>
                                            <select name="docentes[<?php echo $mat['materia_id']; ?>]" class="form-control teacher-select">
                                                <option value="">-- Sin asignar --</option>
                                                <?php foreach ($docentes as $doc): ?>
                                                    <option value="<?php echo $doc['id']; ?>" <?php echo $docente_actual == $doc['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($doc['apellidos'] . ', ' . $doc['nombres']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <?php if ($docente_actual): ?>
                                                <span class="badge badge-success"><i class="fas fa-check"></i> Asignado</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary"><i class="fas fa-times"></i> Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>
            <?php elseif ($seccion_id): ?>
                <div class="alert alert-warning" style="margin-top: 20px;">
                    <i class="fas fa-exclamation-triangle"></i> No hay materias activas registradas en el sistema. Ve a <a href="materias.php">Materias</a> para crearlas.
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>