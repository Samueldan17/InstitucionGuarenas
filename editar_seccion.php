<?php
// editar_seccion.php - Edición de sección (VERSIÓN 3.4)
require_once 'config.php';
requireAuth();
$db = getDBConnection();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: secciones.php'); exit; }

$stmt = $db->prepare("SELECT * FROM secciones WHERE id = ?");
$stmt->execute([$id]);
$seccion = $stmt->fetch();
if (!$seccion) { header('Location: secciones.php'); exit; }

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $grado_id = (int)$_POST['grado_id'];
        $letra = strtoupper(sanitize($_POST['letra']));
        $anio_escolar = sanitize($_POST['anio_escolar'] ?? '2025-2026');
        $turno = $_POST['turno'];
        $capacidad = (int)$_POST['capacidad'];
        $docente_guia_id = (int)$_POST['docente_guia_id'];
        $aula = sanitize($_POST['aula']);

        if (empty($grado_id)) $error = "Debe seleccionar un grado.";
        elseif (empty($letra) || strlen($letra) > 1) $error = "La sección debe ser un solo carácter.";
        elseif (empty($anio_escolar)) $error = "Debe indicar el año escolar.";
        else {
            // Validar duplicado (excluyendo la misma sección)
            $check = $db->prepare("SELECT id FROM secciones WHERE grado_id = ? AND letra = ? AND turno = ? AND anio_escolar = ? AND id != ? AND activo = 1");
            $check->execute([$grado_id, $letra, $turno, $anio_escolar, $id]);
            if ($check->fetch()) {
                $error = "Ya existe otra sección con la misma letra, turno y año escolar.";
            } else {
                $stmt = $db->prepare("UPDATE secciones SET grado_id=?, letra=?, anio_escolar=?, turno=?, capacidad=?, docente_guia_id=?, aula=? WHERE id=?");
                $stmt->execute([$grado_id, $letra, $anio_escolar, $turno, $capacidad, $docente_guia_id, $aula, $id]);
                $message = "✅ Sección actualizada correctamente.";
                registrarLog('editar_seccion', 'secciones', $id);
                
                // Refrescar datos
                $stmt = $db->prepare("SELECT * FROM secciones WHERE id = ?");
                $stmt->execute([$id]);
                $seccion = $stmt->fetch();
            }
        }
    }
}

$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$docentes = $db->query("SELECT id, CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE rol = 'docente' AND activo = 1")->fetchAll();

// Obtener nombre del grado para el encabezado
$stmt = $db->prepare("SELECT nombre FROM grados WHERE id = ?");
$stmt->execute([$seccion['grado_id']]);
$grado_nombre = $stmt->fetchColumn();

// Contar estudiantes en la sección
$stmt = $db->prepare("SELECT COUNT(*) FROM estudiantes WHERE seccion_id = ? AND estado = 'activo'");
$stmt->execute([$id]);
$total_estudiantes = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Sección - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('secciones'); ?>
    <main class="main-content">
        <header class="main-header">
            <div>
                <h1><i class="fas fa-edit"></i> Editar Sección</h1>
                <p class="text-muted" style="margin: 5px 0 0 0; font-size: 14px;">
                    Editando: <strong><?php echo htmlspecialchars($grado_nombre . ' "' . $seccion['letra'] . '" (' . $seccion['turno'] . ')'); ?></strong>
                </p>
            </div>
            <div class="header-actions">
                <a href="secciones.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Listado</a>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <!-- Tarjeta de resumen -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 12px; background: linear-gradient(135deg, #3498db, #2c3e50); color: white; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: bold; flex-shrink: 0;">
                        <?php echo htmlspecialchars($seccion['letra']); ?>
                    </div>
                    <div style="flex: 1;">
                        <h2 style="margin: 0;"><?php echo htmlspecialchars($grado_nombre . ' "' . $seccion['letra'] . '"'); ?></h2>
                        <p style="margin: 5px 0; color: #7f8c8d;">
                            <i class="fas fa-calendar"></i> <?php echo htmlspecialchars($seccion['anio_escolar'] ?? 'Sin año'); ?> | 
                            <i class="fas fa-clock"></i> Turno <?php echo ucfirst($seccion['turno']); ?> | 
                            <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($seccion['aula'] ?: 'Sin aula'); ?>
                        </p>
                        <span class="badge badge-info">
                            <i class="fas fa-user-graduate"></i> <?php echo $total_estudiantes; ?> estudiantes
                        </span>
                        <span class="badge badge-secondary">
                            Capacidad: <?php echo $seccion['capacidad']; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Formulario de edición -->
            <div class="card">
                <div class="card-header"><h2><i class="fas fa-edit"></i> Datos de la Sección</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Grado</label>
                                <select name="grado_id" class="form-control" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach($grados as $g): ?>
                                        <option value="<?php echo $g['id']; ?>" <?php echo $seccion['grado_id']==$g['id']?'selected':''; ?>>
                                            <?php echo htmlspecialchars($g['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sección</label>
                                <input type="text" name="letra" class="form-control" maxlength="1" value="<?php echo htmlspecialchars($seccion['letra']); ?>" required placeholder="A">
                                <small class="form-text">Una sola letra mayúscula (A, B, C...).</small>
                            </div>
                            <div class="form-group">
                                <label>Año Escolar</label>
                                <input type="text" name="anio_escolar" class="form-control" value="<?php echo htmlspecialchars($seccion['anio_escolar'] ?? '2025-2026'); ?>" placeholder="2025-2026" required>
                                <small class="form-text">Formato: 2025-2026</small>
                            </div>
                            <div class="form-group">
                                <label>Turno</label>
                                <select name="turno" class="form-control" required>
                                    <option value="mañana" <?php echo $seccion['turno']=='mañana'?'selected':''; ?>>Mañana</option>
                                    <option value="tarde" <?php echo $seccion['turno']=='tarde'?'selected':''; ?>>Tarde</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Capacidad</label>
                                <input type="number" name="capacidad" class="form-control" min="1" max="50" value="<?php echo $seccion['capacidad']; ?>" required>
                                <small class="form-text">Máximo de estudiantes que caben.</small>
                            </div>
                            <div class="form-group">
                                <label>Aula</label>
                                <input type="text" name="aula" class="form-control" value="<?php echo htmlspecialchars($seccion['aula']); ?>" placeholder="Aula 101">
                            </div>
                            <div class="form-group full-width">
                                <label>Docente Guía</label>
                                <select name="docente_guia_id" class="form-control">
                                    <option value="">Sin asignar</option>
                                    <?php foreach($docentes as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo $seccion['docente_guia_id']==$d['id']?'selected':''; ?>>
                                            <?php echo htmlspecialchars($d['nombre_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                            <a href="secciones.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
</body>
</html>