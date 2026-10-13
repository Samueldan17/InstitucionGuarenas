<?php
// editar_estudiante.php - Edición completa (VERSIÓN 3.6)
require_once 'config.php';
require_once 'functions.php';
requireAuth();
$db = getDBConnection();

$id = (int)($_GET['id'] ?? 0);
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
        $cedula = formatearCedulaParaBD($_POST['cedula'] ?? '');
        $nombres = formatearNombrePropio($_POST['nombres'] ?? '');
        $apellidos = formatearNombrePropio($_POST['apellidos'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $fecha_nac = $_POST['fecha_nacimiento'] ?? null;
        $genero = $_POST['genero'] ?? '';
        $nacionalidad = sanitize($_POST['nacionalidad'] ?? 'Venezolana');
        $lugar_nacimiento = sanitize($_POST['lugar_nacimiento'] ?? '');
        $direccion = sanitize($_POST['direccion'] ?? '');
        $tel_contacto = sanitize($_POST['telefono_contacto'] ?? '');
        $nombre_rep = formatearNombrePropio($_POST['nombre_representante'] ?? '');
        $cedula_rep = formatearCedulaParaBD($_POST['cedula_representante'] ?? '');
        $email_rep = sanitize($_POST['email_representante'] ?? '');
        $tel_rep = sanitize($_POST['telefono_representante'] ?? '');
        $parentesco = sanitize($_POST['parentesco'] ?? '');
        $procedencia = sanitize($_POST['procedencia'] ?? '');
        $seccion_id = (int)($_POST['seccion_id'] ?? 0);
        $fecha_inscripcion = $_POST['fecha_inscripcion'] ?? null;
        $estado = $_POST['estado'] ?? 'activo';

        $validacion = validarCedulaVenezolana($cedula);
        if ($validacion !== true) $error = is_string($validacion) ? $validacion : "Cédula inválida.";
        elseif (empty($nombres) || empty($apellidos)) $error = "Nombres y apellidos obligatorios.";
        elseif (empty($seccion_id)) $error = "Debe seleccionar una sección.";
        elseif ($email && !validarEmail($email)) $error = "Email del estudiante inválido.";
        elseif ($email_rep && !validarEmail($email_rep)) $error = "Email del representante inválido.";
        elseif (!in_array($estado, ['activo','inactivo','graduado'])) $error = "Estado no válido.";
        else {
            $check = $db->prepare("SELECT id FROM estudiantes WHERE cedula = ? AND id != ?");
            $check->execute([$cedula, $id]);
            if ($check->fetch()) {
                $error = "Ya existe otro estudiante con esa cédula.";
            } else {
                $stmt = $db->prepare("UPDATE estudiantes SET 
                    cedula=?, nombres=?, apellidos=?, email=?, fecha_nacimiento=?, genero=?, nacionalidad=?, 
                    lugar_nacimiento=?, direccion=?, telefono_contacto=?, nombre_representante=?, cedula_representante=?,
                    email_representante=?, telefono_representante=?, parentesco=?, procedencia=?, 
                    seccion_id=?, fecha_inscripcion=?, estado=? 
                    WHERE id=?");
                $stmt->execute([
                    $cedula, $nombres, $apellidos, $email, $fecha_nac, $genero, $nacionalidad,
                    $lugar_nacimiento, $direccion, $tel_contacto, $nombre_rep, $cedula_rep,
                    $email_rep, $tel_rep, $parentesco, $procedencia,
                    $seccion_id, $fecha_inscripcion, $estado, $id
                ]);
                $message = "✅ Estudiante actualizado correctamente.";
                registrarLog('editar_estudiante', 'estudiantes', $id);
                
                // Si hay cédula de representante, crear/actualizar vinculación
                if (!empty($cedula_rep)) {
                    crearYVincularRepresentante($id, $cedula_rep, $nombre_rep, '', $email_rep, $tel_rep, $parentesco ?: 'Representante');
                }
                
                $stmt = $db->prepare("SELECT * FROM estudiantes WHERE id = ?");
                $stmt->execute([$id]);
                $estudiante = $stmt->fetch();
            }
        }
    }
}

$secciones = $db->query("SELECT s.id, CONCAT(g.nombre,' ',s.letra,' (',s.turno,')') as nombre FROM secciones s JOIN grados g ON s.grado_id=g.id WHERE s.activo=1 ORDER BY g.orden, s.letra")->fetchAll();

// Representantes vinculados
$stmt = $db->prepare("
    SELECT re.*, u.cedula, u.nombres, u.apellidos, u.email 
    FROM representantes_estudiantes re 
    JOIN usuarios u ON re.representante_id = u.id 
    WHERE re.estudiante_id = ?
");
$stmt->execute([$id]);
$representantes_vinculados = $stmt->fetchAll();

$iniciales = strtoupper(substr($estudiante['nombres'], 0, 1) . substr($estudiante['apellidos'], 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Estudiante - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('estudiantes'); ?>
    <main class="main-content">
        <header class="main-header">
            <div>
                <h1><i class="fas fa-user-edit"></i> Editar Estudiante</h1>
                <p class="text-muted" style="margin: 5px 0 0 0; font-size: 14px;">
                    Editando a: <strong><?php echo htmlspecialchars($estudiante['nombres'] . ' ' . $estudiante['apellidos']); ?></strong>
                </p>
            </div>
            <div class="header-actions">
                <a href="estudiantes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Listado</a>
            </div>
        </header>
        <div class="content-container">
            <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #3498db, #2c3e50); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; flex-shrink: 0;">
                        <?php echo $iniciales; ?>
                    </div>
                    <div>
                        <h2 style="margin: 0;"><?php echo htmlspecialchars($estudiante['nombres'] . ' ' . $estudiante['apellidos']); ?></h2>
                        <p style="margin: 5px 0; color: #7f8c8d;">
                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($estudiante['cedula']); ?> | 
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($estudiante['email'] ?: 'Sin email'); ?>
                        </p>
                        <span class="badge badge-<?php echo $estudiante['estado']=='activo'?'success':($estudiante['estado']=='inactivo'?'danger':'info'); ?>">
                            <?php echo ucfirst($estudiante['estado']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if (!empty($representantes_vinculados)): ?>
            <div class="card" style="margin-bottom: 20px; border-left: 4px solid #27ae60;">
                <div class="card-header"><h2><i class="fas fa-users"></i> Representantes Vinculados</h2></div>
                <div class="card-body">
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($representantes_vinculados as $rep): ?>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            <strong><?php echo htmlspecialchars($rep['nombres'] . ' ' . $rep['apellidos']); ?></strong> 
                            (<?php echo htmlspecialchars($rep['cedula']); ?>) 
                            - <em><?php echo htmlspecialchars($rep['parentesco']); ?></em>
                            <?php if ($rep['es_principal']): ?>
                                <span class="badge badge-success">Principal</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-user"></i> Datos Personales</h2></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group"><label>Cédula</label><input type="text" name="cedula" class="form-control cedula" value="<?php echo htmlspecialchars($estudiante['cedula']); ?>" required></div>
                            <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" value="<?php echo htmlspecialchars($estudiante['nombres']); ?>" required></div>
                            <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" value="<?php echo htmlspecialchars($estudiante['apellidos']); ?>" required></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($estudiante['email']); ?>"></div>
                            <div class="form-group"><label>Fecha de Nacimiento</label><input type="date" name="fecha_nacimiento" class="form-control" value="<?php echo $estudiante['fecha_nacimiento']; ?>"></div>
                            <div class="form-group"><label>Género</label><select name="genero" class="form-control"><option value="">Seleccione...</option><option value="M" <?php echo $estudiante['genero']=='M'?'selected':''; ?>>Masculino</option><option value="F" <?php echo $estudiante['genero']=='F'?'selected':''; ?>>Femenino</option></select></div>
                            <div class="form-group"><label>Nacionalidad</label><input type="text" name="nacionalidad" class="form-control" value="<?php echo htmlspecialchars($estudiante['nacionalidad']); ?>"></div>
                            <div class="form-group"><label>Lugar de Nacimiento</label><input type="text" name="lugar_nacimiento" class="form-control" value="<?php echo htmlspecialchars($estudiante['lugar_nacimiento']); ?>"></div>
                            <div class="form-group full-width"><label>Dirección</label><textarea name="direccion" class="form-control" rows="2"><?php echo htmlspecialchars($estudiante['direccion']); ?></textarea></div>
                            <div class="form-group"><label>Teléfono</label><input type="text" name="telefono_contacto" class="form-control telefono" value="<?php echo htmlspecialchars($estudiante['telefono_contacto']); ?>"></div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-users"></i> Datos del Representante</h2></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group"><label>Cédula del Representante</label><input type="text" name="cedula_representante" class="form-control cedula" value="<?php echo htmlspecialchars($estudiante['cedula_representante'] ?? ''); ?>" placeholder="12345678"></div>
                            <div class="form-group"><label>Nombre del Representante</label><input type="text" name="nombre_representante" class="form-control" value="<?php echo htmlspecialchars($estudiante['nombre_representante']); ?>"></div>
                            <div class="form-group"><label>Parentesco</label><input type="text" name="parentesco" class="form-control" value="<?php echo htmlspecialchars($estudiante['parentesco']); ?>"></div>
                            <div class="form-group"><label>Email del Representante</label><input type="email" name="email_representante" class="form-control" value="<?php echo htmlspecialchars($estudiante['email_representante']); ?>"></div>
                            <div class="form-group"><label>Teléfono del Representante</label><input type="text" name="telefono_representante" class="form-control telefono" value="<?php echo htmlspecialchars($estudiante['telefono_representante']); ?>"></div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-graduation-cap"></i> Datos Académicos</h2></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group"><label>Sección</label><select name="seccion_id" class="form-control" required><option value="">Seleccione...</option><?php foreach($secciones as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $estudiante['seccion_id']==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['nombre']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Procedencia</label><input type="text" name="procedencia" class="form-control" value="<?php echo htmlspecialchars($estudiante['procedencia']); ?>"></div>
                            <div class="form-group"><label>Fecha de Inscripción</label><input type="date" name="fecha_inscripcion" class="form-control" value="<?php echo $estudiante['fecha_inscripcion']; ?>"></div>
                            <div class="form-group"><label>Estado</label><select name="estado" class="form-control"><option value="activo" <?php echo $estudiante['estado']=='activo'?'selected':''; ?>>Activo</option><option value="inactivo" <?php echo $estudiante['estado']=='inactivo'?'selected':''; ?>>Inactivo</option><option value="graduado" <?php echo $estudiante['estado']=='graduado'?'selected':''; ?>>Graduado</option></select></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                            <a href="estudiantes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>
<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
</body>
</html>