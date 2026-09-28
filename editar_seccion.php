<?php
// editar_seccion.php - Edición de sección con validación de duplicados
require_once 'config.php';
requireAuth();
$db = getDBConnection();
$id = (int)$_GET['id'];
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
        $turno = $_POST['turno'];
        $capacidad = (int)$_POST['capacidad'];
        $docente_guia_id = (int)$_POST['docente_guia_id'];
        $aula = sanitize($_POST['aula']);

        if (empty($grado_id)) $error = "Debe seleccionar un grado.";
        elseif (empty($letra) || strlen($letra) > 1) $error = "La letra debe ser un solo carácter.";
        else {
            // Validar duplicado (excluyendo la misma sección)
            $check = $db->prepare("SELECT id FROM secciones WHERE grado_id = ? AND letra = ? AND turno = ? AND id != ? AND activo = 1");
            $check->execute([$grado_id, $letra, $turno, $id]);
            if ($check->fetch()) {
                $error = "Ya existe otra sección con la misma letra y turno para este grado.";
            } else {
                $stmt = $db->prepare("UPDATE secciones SET grado_id=?, letra=?, turno=?, capacidad=?, docente_guia_id=?, aula=? WHERE id=?");
                $stmt->execute([$grado_id, $letra, $turno, $capacidad, $docente_guia_id, $aula, $id]);
                $message = "✅ Sección actualizada correctamente.";
                registrarLog('editar_seccion', 'secciones', $id);
                $stmt = $db->prepare("SELECT * FROM secciones WHERE id = ?");
                $stmt->execute([$id]);
                $seccion = $stmt->fetch();
            }
        }
    }
}

$grados = $db->query("SELECT * FROM grados WHERE activo = 1 ORDER BY orden")->fetchAll();
$docentes = $db->query("SELECT id, CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE rol = 'docente' AND activo = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Editar Sección</title><link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('secciones'); ?>
    <main class="main-content">
        <header class="main-header"><h1><i class="fas fa-edit"></i> Editar Sección</h1></header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <div class="card">
                <div class="card-header"><h2>Datos de la Sección</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="form-grid">
                            <div class="form-group"><label>Grado</label><select name="grado_id" class="form-control" required><?php foreach($grados as $g): ?><option value="<?php echo $g['id']; ?>" <?php echo $seccion['grado_id']==$g['id']?'selected':''; ?>><?php echo $g['nombre']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Letra</label><input type="text" name="letra" class="form-control" maxlength="1" value="<?php echo htmlspecialchars($seccion['letra']); ?>" required></div>
                            <div class="form-group"><label>Turno</label><select name="turno" class="form-control"><option value="mañana" <?php echo $seccion['turno']=='mañana'?'selected':''; ?>>Mañana</option><option value="tarde" <?php echo $seccion['turno']=='tarde'?'selected':''; ?>>Tarde</option></select></div>
                            <div class="form-group"><label>Capacidad</label><input type="number" name="capacidad" class="form-control" min="1" max="50" value="<?php echo $seccion['capacidad']; ?>"></div>
                            <div class="form-group"><label>Docente Guía</label><select name="docente_guia_id" class="form-control"><option value="">Sin asignar</option><?php foreach($docentes as $d): ?><option value="<?php echo $d['id']; ?>" <?php echo $seccion['docente_guia_id']==$d['id']?'selected':''; ?>><?php echo $d['nombre_completo']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Aula</label><input type="text" name="aula" class="form-control" value="<?php echo htmlspecialchars($seccion['aula']); ?>"></div>
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn-primary">Guardar Cambios</button><a href="secciones.php" class="btn btn-secondary">Cancelar</a></div>
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