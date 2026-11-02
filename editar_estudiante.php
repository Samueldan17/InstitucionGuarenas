<?php
// editar_estudiante.php - Edición completa (VERSIÓN 4.2 - Solo letras en nombres)
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
        $cedula_prefijo = $_POST['cedula_prefijo'] ?? 'V';
        $cedula_numero = trim($_POST['cedula_numero'] ?? '');
        $cedula = formatearCedulaParaBD($cedula_prefijo . '-' . $cedula_numero);

        $nombres = formatearNombrePropio($_POST['nombres'] ?? '');
        $apellidos = formatearNombrePropio($_POST['apellidos'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $fecha_nac = $_POST['fecha_nacimiento'] ?? null;
        $genero = $_POST['genero'] ?? '';
        $nacionalidad = sanitize($_POST['nacionalidad'] ?? 'Venezolana');
        $lugar_nacimiento = sanitize($_POST['lugar_nacimiento'] ?? '');
        $direccion = sanitize($_POST['direccion'] ?? '');
        
        // Teléfono Estudiante
        $tel_est_prefijo = $_POST['telefono_contacto_prefijo'] ?? '';
        $tel_est_numero = trim($_POST['telefono_contacto_numero'] ?? '');
        $tel_contacto = (!empty($tel_est_prefijo) && !empty($tel_est_numero)) ? $tel_est_prefijo . '-' . $tel_est_numero : '';

        $cedula_rep_prefijo = $_POST['cedula_rep_prefijo'] ?? 'V';
        $cedula_rep_numero = trim($_POST['cedula_rep_numero'] ?? '');
        $cedula_rep = formatearCedulaParaBD($cedula_rep_prefijo . '-' . $cedula_rep_numero);

        $nombre_rep = formatearNombrePropio($_POST['nombre_representante'] ?? '');
        $email_rep = sanitize($_POST['email_representante'] ?? '');
        $parentesco = sanitize($_POST['parentesco'] ?? '');
        
        // Teléfono Representante
        $tel_rep_prefijo = $_POST['telefono_representante_prefijo'] ?? '';
        $tel_rep_numero = trim($_POST['telefono_representante_numero'] ?? '');
        $tel_rep = (!empty($tel_rep_prefijo) && !empty($tel_rep_numero)) ? $tel_rep_prefijo . '-' . $tel_rep_numero : '';

        $procedencia = sanitize($_POST['procedencia'] ?? '');
        $seccion_id = (int)($_POST['seccion_id'] ?? 0);
        $fecha_inscripcion = $_POST['fecha_inscripcion'] ?? null;
        $estado = $_POST['estado'] ?? 'activo';

        // Contraseñas
        $pass_est = $_POST['password_estudiante'] ?? '';
        $confirm_est = $_POST['confirm_password_estudiante'] ?? '';
        $pass_rep = $_POST['password_representante'] ?? '';
        $confirm_rep = $_POST['confirm_password_representante'] ?? '';

        $validacion = validarCedulaVenezolana($cedula);
        if ($validacion !== true) $error = is_string($validacion) ? $validacion : "Cédula del estudiante inválida.";
        elseif (empty($nombres) || empty($apellidos)) $error = "Nombres y apellidos obligatorios.";
        elseif (empty($seccion_id)) $error = "Debe seleccionar una sección.";
        elseif ($email && !validarEmail($email)) $error = "Email del estudiante inválido.";
        elseif ($email_rep && !validarEmail($email_rep)) $error = "Email del representante inválido.";
        elseif (!in_array($estado, ['activo','inactivo','graduado'])) $error = "Estado no válido.";
        else {
            if (!empty($pass_est)) {
                if (($pass_valid = validarPasswordSegura($pass_est)) !== true) $error = "Contraseña Estudiante: " . $pass_valid;
                elseif ($pass_est !== $confirm_est) $error = "Las contraseñas del estudiante no coinciden.";
            }
            if (empty($error) && !empty($pass_rep)) {
                if (($pass_valid = validarPasswordSegura($pass_rep)) !== true) $error = "Contraseña Representante: " . $pass_valid;
                elseif ($pass_rep !== $confirm_rep) $error = "Las contraseñas del representante no coinciden.";
            }

            if (!$error) {
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
                    
                    if (!empty($pass_est)) {
                        $hash_est = password_hash($pass_est, PASSWORD_DEFAULT);
                        $stmtUser = $db->prepare("SELECT id FROM usuarios WHERE cedula = ?");
                        $stmtUser->execute([$cedula]);
                        $user = $stmtUser->fetch();
                        if ($user) {
                            $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([$hash_est, $user['id']]);
                        } else {
                            crearUsuarioEstudiante($id, $pass_est);
                        }
                    }

                    if (!empty($cedula_rep)) {
                        $rep_id = crearYVincularRepresentante($id, $cedula_rep, $nombre_rep, '', $email_rep, $tel_rep, $parentesco ?: 'Representante');
                        if (!empty($pass_rep) && $rep_id) {
                            $hash_rep = password_hash($pass_rep, PASSWORD_DEFAULT);
                            $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([$hash_rep, $rep_id]);
                        }
                    }
                    
                    $message = "✅ Estudiante actualizado correctamente.";
                    registrarLog('editar_estudiante', 'estudiantes', $id);
                    
                    $stmt = $db->prepare("SELECT * FROM estudiantes WHERE id = ?");
                    $stmt->execute([$id]);
                    $estudiante = $stmt->fetch();
                }
            }
        }
    }
}

$secciones = $db->query("SELECT s.id, CONCAT(g.nombre,' ',s.letra,' (',s.turno,')') as nombre FROM secciones s JOIN grados g ON s.grado_id=g.id WHERE s.activo=1 ORDER BY g.orden, s.letra")->fetchAll();

$stmt = $db->prepare("SELECT re.*, u.cedula, u.nombres, u.apellidos, u.email FROM representantes_estudiantes re JOIN usuarios u ON re.representante_id = u.id WHERE re.estudiante_id = ?");
$stmt->execute([$id]);
$representantes_vinculados = $stmt->fetchAll();

$iniciales = strtoupper(substr($estudiante['nombres'], 0, 1) . substr($estudiante['apellidos'], 0, 1));

$partes_cedula_est = explode('-', $estudiante['cedula']);
$cedula_est_pref = $partes_cedula_est[0] ?? 'V';
$cedula_est_num = $partes_cedula_est[1] ?? $estudiante['cedula'];

$partes_cedula_rep = explode('-', $estudiante['cedula_representante'] ?? '');
$cedula_rep_pref = $partes_cedula_rep[0] ?? 'V';
$cedula_rep_num = $partes_cedula_rep[1] ?? '';

$tel_est_actual = explode('-', $estudiante['telefono_contacto'] . '-');
$tel_est_prefijo_actual = $tel_est_actual[0];
$tel_est_numero_actual = $tel_est_actual[1] ?? '';

$tel_rep_actual = explode('-', $estudiante['telefono_representante'] . '-');
$tel_rep_prefijo_actual = $tel_rep_actual[0];
$tel_rep_numero_actual = $tel_rep_actual[1] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Estudiante - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .phone-group { display: flex; gap: 8px; width: 100%; align-items: center; }
        .phone-group select.form-control { width: 85px !important; flex-shrink: 0; padding: 8px 4px; text-align: center; }
        .phone-group input.form-control { flex: 1; min-width: 0; padding: 8px; }
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper input { width: 100%; padding-right: 40px; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #586069; z-index: 10; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('estudiantes'); ?>
    <main class="main-content">
        <header class="main-header">
            <div>
                <h1><i class="fas fa-user-edit"></i> Editar Estudiante</h1>
                <p class="text-muted" style="margin: 5px 0 0 0; font-size: 14px;">Editando a: <strong><?php echo htmlspecialchars($estudiante['nombres'] . ' ' . $estudiante['apellidos']); ?></strong></p>
            </div>
            <div class="header-actions">
                <a href="estudiantes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </header>
        <div class="content-container">
            <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #3498db, #2c3e50); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; flex-shrink: 0;"><?php echo $iniciales; ?></div>
                    <div>
                        <h2 style="margin: 0;"><?php echo htmlspecialchars($estudiante['nombres'] . ' ' . $estudiante['apellidos']); ?></h2>
                        <p style="margin: 5px 0; color: #7f8c8d;"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($estudiante['cedula']); ?></p>
                        <span class="badge badge-<?php echo $estudiante['estado']=='activo'?'success':($estudiante['estado']=='inactivo'?'danger':'info'); ?>"><?php echo ucfirst($estudiante['estado']); ?></span>
                    </div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-user"></i> Datos Personales</h2></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Cédula</label>
                                <div class="phone-group">
                                    <select name="cedula_prefijo" class="form-control" style="width: 80px !important;">
                                        <option value="V" <?php echo $cedula_est_pref == 'V' ? 'selected' : ''; ?>>V</option>
                                        <option value="E" <?php echo $cedula_est_pref == 'E' ? 'selected' : ''; ?>>E</option>
                                    </select>
                                    <input type="text" name="cedula_numero" class="form-control" value="<?php echo htmlspecialchars($cedula_est_num); ?>" required maxlength="8" pattern="\d{6,8}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                </div>
                            </div>

                            <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" value="<?php echo htmlspecialchars($estudiante['nombres']); ?>" required oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')"></div>
                            <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" value="<?php echo htmlspecialchars($estudiante['apellidos']); ?>" required oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')"></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($estudiante['email']); ?>"></div>
                            <div class="form-group"><label>Fecha de Nacimiento</label><input type="date" name="fecha_nacimiento" class="form-control" value="<?php echo $estudiante['fecha_nacimiento']; ?>"></div>
                            <div class="form-group"><label>Género</label><select name="genero" class="form-control"><option value="">Seleccione...</option><option value="M" <?php echo $estudiante['genero']=='M'?'selected':''; ?>>Masculino</option><option value="F" <?php echo $estudiante['genero']=='F'?'selected':''; ?>>Femenino</option></select></div>
                            <div class="form-group"><label>Nacionalidad</label><input type="text" name="nacionalidad" class="form-control" value="<?php echo htmlspecialchars($estudiante['nacionalidad']); ?>" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')"></div>
                            <div class="form-group"><label>Lugar de Nacimiento</label><input type="text" name="lugar_nacimiento" class="form-control" value="<?php echo htmlspecialchars($estudiante['lugar_nacimiento']); ?>" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')"></div>
                            <div class="form-group full-width"><label>Dirección</label><textarea name="direccion" class="form-control" rows="2"><?php echo htmlspecialchars($estudiante['direccion']); ?></textarea></div>
                            
                            <div class="form-group">
                                <label>Teléfono Estudiante</label>
                                <div class="phone-group">
                                    <select name="telefono_contacto_prefijo" class="form-control">
                                        <option value="">-</option>
                                        <option value="0412" <?php echo $tel_est_prefijo_actual == '0412' ? 'selected' : ''; ?>>0412</option>
                                        <option value="0414" <?php echo $tel_est_prefijo_actual == '0414' ? 'selected' : ''; ?>>0414</option>
                                        <option value="0416" <?php echo $tel_est_prefijo_actual == '0416' ? 'selected' : ''; ?>>0416</option>
                                        <option value="0424" <?php echo $tel_est_prefijo_actual == '0424' ? 'selected' : ''; ?>>0424</option>
                                        <option value="0426" <?php echo $tel_est_prefijo_actual == '0426' ? 'selected' : ''; ?>>0426</option>
                                        <option value="0428" <?php echo $tel_est_prefijo_actual == '0428' ? 'selected' : ''; ?>>0428</option>
                                    </select>
                                    <input type="text" name="telefono_contacto_numero" class="form-control" value="<?php echo htmlspecialchars($tel_est_numero_actual); ?>" placeholder="1234567" maxlength="7" pattern="\d{7}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h2><i class="fas fa-users"></i> Datos del Representante</h2></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Cédula del Representante</label>
                                <div class="phone-group">
                                    <select name="cedula_rep_prefijo" class="form-control" style="width: 80px !important;">
                                        <option value="V" <?php echo $cedula_rep_pref == 'V' ? 'selected' : ''; ?>>V</option>
                                        <option value="E" <?php echo $cedula_rep_pref == 'E' ? 'selected' : ''; ?>>E</option>
                                        <option value="J" <?php echo $cedula_rep_pref == 'J' ? 'selected' : ''; ?>>J</option>
                                    </select>
                                    <input type="text" name="cedula_rep_numero" class="form-control" value="<?php echo htmlspecialchars($cedula_rep_num); ?>" maxlength="8" pattern="\d{6,8}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                </div>
                            </div>

                            <div class="form-group"><label>Nombre del Representante</label><input type="text" name="nombre_representante" class="form-control" value="<?php echo htmlspecialchars($estudiante['nombre_representante']); ?>" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')"></div>
                            <div class="form-group"><label>Parentesco</label><input type="text" name="parentesco" class="form-control" value="<?php echo htmlspecialchars($estudiante['parentesco']); ?>" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')"></div>
                            <div class="form-group"><label>Email del Representante</label><input type="email" name="email_representante" class="form-control" value="<?php echo htmlspecialchars($estudiante['email_representante']); ?>"></div>
                            
                            <div class="form-group">
                                <label>Teléfono Representante</label>
                                <div class="phone-group">
                                    <select name="telefono_representante_prefijo" class="form-control">
                                        <option value="">-</option>
                                        <option value="0412" <?php echo $tel_rep_prefijo_actual == '0412' ? 'selected' : ''; ?>>0412</option>
                                        <option value="0414" <?php echo $tel_rep_prefijo_actual == '0414' ? 'selected' : ''; ?>>0414</option>
                                        <option value="0416" <?php echo $tel_rep_prefijo_actual == '0416' ? 'selected' : ''; ?>>0416</option>
                                        <option value="0424" <?php echo $tel_rep_prefijo_actual == '0424' ? 'selected' : ''; ?>>0424</option>
                                        <option value="0426" <?php echo $tel_rep_prefijo_actual == '0426' ? 'selected' : ''; ?>>0426</option>
                                        <option value="0428" <?php echo $tel_rep_prefijo_actual == '0428' ? 'selected' : ''; ?>>0428</option>
                                    </select>
                                    <input type="text" name="telefono_representante_numero" class="form-control" value="<?php echo htmlspecialchars($tel_rep_numero_actual); ?>" placeholder="1234567" maxlength="7" pattern="\d{7}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                </div>
                            </div>
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

                <!-- NUEVA SECCIÓN DE CONTRASEÑAS -->
                <div class="card" style="margin-bottom: 20px; border-left: 4px solid #e67e22;">
                    <div class="card-header"><h2><i class="fas fa-key"></i> Actualizar Credenciales de Acceso (Opcional)</h2></div>
                    <div class="card-body">
                        <p class="text-muted" style="margin-bottom: 15px;">Deje estos campos en blanco si no desea cambiar las contraseñas actuales.</p>
                        
                        <div class="form-grid">
                            <div class="form-group full-width" style="margin-top: 5px;">
                                <h4 style="color: #27ae60; margin-bottom: 10px;">Contraseña del Estudiante</h4>
                            </div>
                            <div class="form-group">
                                <label>Nueva Contraseña Estudiante</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password_estudiante" id="pass_est" class="form-control" placeholder="Mín. 8 caracteres">
                                    <i class="fas fa-eye toggle-password" onclick="togglePassword('pass_est', this)"></i>
                                </div>
                            </div>
                            <div class="form-group"><label>Confirmar Contraseña Estudiante</label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_password_estudiante" id="pass_est_conf" class="form-control">
                                    <i class="fas fa-eye toggle-password" onclick="togglePassword('pass_est_conf', this)"></i>
                                </div>
                            </div>

                            <div class="form-group full-width" style="margin-top: 15px;">
                                <h4 style="color: #e67e22; margin-bottom: 10px;">Contraseña del Representante</h4>
                            </div>
                            <div class="form-group">
                                <label>Nueva Contraseña Representante</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password_representante" id="pass_rep" class="form-control" placeholder="Mín. 8 caracteres">
                                    <i class="fas fa-eye toggle-password" onclick="togglePassword('pass_rep', this)"></i>
                                </div>
                            </div>
                            <div class="form-group"><label>Confirmar Contraseña Representante</label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_password_representante" id="pass_rep_conf" class="form-control">
                                    <i class="fas fa-eye toggle-password" onclick="togglePassword('pass_rep_conf', this)"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body" style="display: flex; justify-content: space-between;">
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
<script>
    function togglePassword(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash"); 
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }
</script>
</body>
</html>