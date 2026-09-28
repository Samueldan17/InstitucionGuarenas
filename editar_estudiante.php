<?php
// editar_estudiante.php - Edición de estudiante con todos los campos
require_once 'config.php';
requireAuth();
$db = getDBConnection();
$id = (int)$_GET['id'];
if (!$id) { header('Location: estudiantes.php'); exit; }

$stmt = $db->prepare("SELECT * FROM estudiantes WHERE id = ?");
$stmt->execute([$id]);
$estudiante = $stmt->fetch();
if (!$estudiante) { header('Location: estudiantes.php'); exit; }

$message = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $cedula = formatearCedulaParaBD($_POST['cedula']);
        $nombres = formatearNombrePropio($_POST['nombres']);
        $apellidos = formatearNombrePropio($_POST['apellidos']);
        $fecha_nac = $_POST['fecha_nacimiento'];
        $genero = $_POST['genero'];
        $nacionalidad = sanitize($_POST['nacionalidad']);
        $lugar_nacimiento = sanitize($_POST['lugar_nacimiento']);
        $direccion = sanitize($_POST['direccion']);
        $tel_contacto = sanitize($_POST['telefono_contacto']);
        $email = sanitize($_POST['email']);
        $nombre_rep = formatearNombrePropio($_POST['nombre_representante']);
        $email_rep = sanitize($_POST['email_representante']);
        $tel_rep = sanitize($_POST['telefono_representante']);
        $parentesco = sanitize($_POST['parentesco']);
        $procedencia = sanitize($_POST['procedencia']);
        $seccion_id = (int)$_POST['seccion_id'];

        if (!validarCedulaVenezolana($cedula)) $error = "Cédula inválida.";
        elseif (empty($nombres) || empty($apellidos)) $error = "Nombres y apellidos obligatorios.";
        elseif ($email && !validarEmail($email)) $error = "Email del estudiante inválido.";
        elseif ($email_rep && !validarEmail($email_rep)) $error = "Email del representante inválido.";
        else {
            $stmt = $db->prepare("UPDATE estudiantes SET cedula=?, nombres=?, apellidos=?, fecha_nacimiento=?, genero=?, nacionalidad=?, lugar_nacimiento=?, direccion=?, telefono_contacto=?, email=?, nombre_representante=?, email_representante=?, telefono_representante=?, parentesco=?, procedencia=?, seccion_id=? WHERE id=?");
            $stmt->execute([$cedula, $nombres, $apellidos, $fecha_nac, $genero, $nacionalidad, $lugar_nacimiento, $direccion, $tel_contacto, $email, $nombre_rep, $email_rep, $tel_rep, $parentesco, $procedencia, $seccion_id, $id]);
            $message = "Estudiante actualizado correctamente.";
            registrarLog('editar_estudiante', 'estudiantes', $id);
            // Refrescar
            $stmt = $db->prepare("SELECT * FROM estudiantes WHERE id = ?");
            $stmt->execute([$id]);
            $estudiante = $stmt->fetch();
        }
    }
}

$secciones = $db->query("SELECT s.id, CONCAT(g.nombre,' ',s.letra,' (',s.turno,')') as nombre FROM secciones s JOIN grados g ON s.grado_id=g.id WHERE s.activo=1 ORDER BY g.orden, s.letra")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Editar Estudiante</title><link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('estudiantes'); ?>
    <main class="main-content">
        <header class="main-header"><h1>Editar Estudiante</h1></header>
        <div class="content-container">
            <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <div class="card"><div class="card-header"><h2>Datos del Estudiante</h2></div><div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="form-grid">
                        <div class="form-group"><label>Cédula</label><input type="text" name="cedula" class="form-control cedula" value="<?php echo htmlspecialchars($estudiante['cedula']); ?>" required></div>
                        <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" value="<?php echo htmlspecialchars($estudiante['nombres']); ?>" required></div>
                        <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" value="<?php echo htmlspecialchars($estudiante['apellidos']); ?>" required></div>
                        <div class="form-group"><label>Fecha Nacimiento</label><input type="date" name="fecha_nacimiento" class="form-control" value="<?php echo $estudiante['fecha_nacimiento']; ?>"></div>
                        <div class="form-group"><label>Género</label><select name="genero" class="form-control"><option value="">Seleccione</option><option value="M" <?php echo $estudiante['genero']=='M'?'selected':''; ?>>Masculino</option><option value="F" <?php echo $estudiante['genero']=='F'?'selected':''; ?>>Femenino</option></select></div>
                        <div class="form-group"><label>Nacionalidad</label><input type="text" name="nacionalidad" class="form-control" value="<?php echo htmlspecialchars($estudiante['nacionalidad']); ?>"></div>
                        <div class="form-group"><label>Lugar Nacimiento</label><input type="text" name="lugar_nacimiento" class="form-control" value="<?php echo htmlspecialchars($estudiante['lugar_nacimiento']); ?>"></div>
                        <div class="form-group"><label>Dirección</label><textarea name="direccion" class="form-control" rows="2"><?php echo htmlspecialchars($estudiante['direccion']); ?></textarea></div>
                        <div class="form-group"><label>Teléfono Contacto</label><input type="text" name="telefono_contacto" class="form-control telefono" value="<?php echo htmlspecialchars($estudiante['telefono_contacto']); ?>"></div>
                        <div class="form-group"><label>Email Estudiante</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($estudiante['email']); ?>"></div>
                        <div class="form-group"><label>Sección</label><select name="seccion_id" class="form-control" required><?php foreach($secciones as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $estudiante['seccion_id']==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['nombre']); ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Nombre Representante</label><input type="text" name="nombre_representante" class="form-control" value="<?php echo htmlspecialchars($estudiante['nombre_representante']); ?>"></div>
                        <div class="form-group"><label>Email Representante</label><input type="email" name="email_representante" class="form-control" value="<?php echo htmlspecialchars($estudiante['email_representante']); ?>"></div>
                        <div class="form-group"><label>Teléfono Representante</label><input type="text" name="telefono_representante" class="form-control telefono" value="<?php echo htmlspecialchars($estudiante['telefono_representante']); ?>"></div>
                        <div class="form-group"><label>Parentesco</label><input type="text" name="parentesco" class="form-control" value="<?php echo htmlspecialchars($estudiante['parentesco']); ?>"></div>
                        <div class="form-group"><label>Procedencia</label><input type="text" name="procedencia" class="form-control" value="<?php echo htmlspecialchars($estudiante['procedencia']); ?>"></div>
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary">Guardar Cambios</button><a href="estudiantes.php" class="btn btn-secondary">Cancelar</a></div>
                </form>
            </div></div>
        </div>
    </main>
</div>
<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
</body>
</html>