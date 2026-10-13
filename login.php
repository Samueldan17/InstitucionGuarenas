<?php
// login.php - Sistema de autenticación con seguridad mejorada (VERSIÓN 3.5)
require_once 'config.php';

if (isset($_SESSION['usuario_id'])) {
    // Redirigir según rol
    if ($_SESSION['usuario_rol'] === 'estudiante') {
        header('Location: panel_estudiante.php');
    } elseif ($_SESSION['usuario_rol'] === 'representante') {
        header('Location: panel_representante.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';
$success = '';
$show_register = false;
$show_recovery = false;

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'register') $show_register = true;
    elseif ($_GET['action'] === 'recovery') $show_recovery = true;
}

// =========================================================
// PROCESAR LOGIN
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (empty($username) || empty($password)) {
        $error = 'Por favor ingrese usuario/cédula y contraseña';
    } else {
        $db = getDBConnection();
        if (!$db) {
            $error = 'Error de conexión a la base de datos';
        } else {
            try {
                $stmt = $db->prepare("SELECT bloqueado_hasta FROM login_attempts WHERE ip = ? ORDER BY ultimo_intento DESC LIMIT 1");
                $stmt->execute([$ip]);
                $bloqueo = $stmt->fetch();
                if ($bloqueo && $bloqueo['bloqueado_hasta'] && strtotime($bloqueo['bloqueado_hasta']) > time()) {
                    $error = 'Demasiados intentos fallidos. Intente nuevamente después de 15 minutos.';
                } else {
                    $cedula_formateada = formatearCedulaParaBD($username);
                    $stmt = $db->prepare("
                        SELECT id, username, cedula, password, nombres, apellidos, rol, activo 
                        FROM usuarios 
                        WHERE (username = ? OR cedula = ? OR cedula = ?) AND activo = 1
                    ");
                    $stmt->execute([$username, $username, $cedula_formateada]);
                    $usuario = $stmt->fetch();

                    if ($usuario && password_verify($password, $usuario['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['usuario_id'] = $usuario['id'];
                        $_SESSION['usuario_nombre'] = $usuario['nombres'] . ' ' . $usuario['apellidos'];
                        $_SESSION['usuario_rol'] = $usuario['rol'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip = ?");
                        $stmt->execute([$ip]);

                        $updateStmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                        $updateStmt->execute([$usuario['id']]);

                        registrarLog('login_exitoso', 'usuarios', $usuario['id'], "Usuario {$usuario['username']} inició sesión");

                        // Redirigir según rol
                        switch ($usuario['rol']) {
                            case 'admin':
                            case 'docente':
                            case 'secretario':
                                header('Location: dashboard.php');
                                break;
                            case 'estudiante':
                                header('Location: panel_estudiante.php');
                                break;
                            case 'representante':
                                header('Location: panel_representante.php');
                                break;
                            default:
                                header('Location: dashboard.php');
                        }
                        exit;
                    } else {
                        $stmt = $db->prepare("INSERT INTO login_attempts (ip, usuario, intentos, ultimo_intento) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE intentos = intentos + 1, ultimo_intento = NOW()");
                        $stmt->execute([$ip, $username]);
                        
                        $stmt = $db->prepare("SELECT intentos FROM login_attempts WHERE ip = ?");
                        $stmt->execute([$ip]);
                        $intentos = $stmt->fetch();
                        if ($intentos && $intentos['intentos'] >= MAX_LOGIN_ATTEMPTS) {
                            $bloqueo_hasta = date('Y-m-d H:i:s', time() + LOGIN_BLOCK_TIME);
                            $stmt = $db->prepare("UPDATE login_attempts SET bloqueado_hasta = ? WHERE ip = ?");
                            $stmt->execute([$bloqueo_hasta, $ip]);
                            $error = 'Demasiados intentos fallidos. Su IP ha sido bloqueada por 15 minutos.';
                        } else {
                            $error = 'Usuario/cédula o contraseña incorrectos';
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Error del sistema. Intente más tarde.';
                error_log("Error de login: " . $e->getMessage());
            }
        }
    }
}

// =========================================================
// PROCESAR REGISTRO
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $nombres = formatearNombrePropio($_POST['nombres'] ?? '');
    $apellidos = formatearNombrePropio($_POST['apellidos'] ?? '');
    $cedula = formatearCedulaParaBD($_POST['cedula'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $telefono = sanitize($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($nombres) || empty($apellidos) || empty($cedula) || empty($username) || empty($email) || empty($password)) {
        $error = 'Todos los campos obligatorios deben ser llenados';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif (!validarEmail($email)) {
        $error = 'El email no es válido';
    } elseif (($validacion = validarCedulaVenezolana($cedula)) !== true) {
        $error = is_string($validacion) ? $validacion : 'Cédula inválida';
    } else {
        $db = getDBConnection();
        if ($db) {
            try {
                $checkStmt = $db->prepare("SELECT id FROM usuarios WHERE username = ? OR cedula = ? OR email = ?");
                $checkStmt->execute([$username, $cedula, $email]);
                if ($checkStmt->fetch()) {
                    $error = 'El nombre de usuario, cédula o email ya está registrado';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO usuarios (username, cedula, nombres, apellidos, email, telefono, password, rol, activo) VALUES (?, ?, ?, ?, ?, ?, ?, 'docente', 1)");
                    $stmt->execute([$username, $cedula, $nombres, $apellidos, $email, $telefono, $hashed_password]);
                    $success = '¡Registro exitoso! Ahora puedes iniciar sesión.';
                    registrarLog('registro_usuario', 'usuarios', $db->lastInsertId(), "Nuevo usuario: $username");
                    $show_register = false;
                }
            } catch (PDOException $e) {
                $error = 'Error al registrar usuario: ' . $e->getMessage();
            }
        } else {
            $error = 'Error de conexión a la base de datos';
        }
    }
}

// =========================================================
// PROCESAR RECUPERACIÓN
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recovery'])) {
    $email = sanitize($_POST['email'] ?? '');
    if (empty($email)) {
        $error = 'Por favor ingrese su email';
    } elseif (!validarEmail($email)) {
        $error = 'El email no es válido';
    } else {
        $db = getDBConnection();
        if ($db) {
            try {
                $stmt = $db->prepare("SELECT id, nombres, apellidos FROM usuarios WHERE email = ? AND activo = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $updateStmt = $db->prepare("UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE email = ?");
                    $updateStmt->execute([$token, $expiracion, $email]);
                    $reset_link = APP_URL . "/reset_password.php?token=$token";
                    $success = "Se ha enviado un enlace de recuperación a tu email.<br><small>Enlace: <a href='$reset_link'>$reset_link</a></small>";
                } else {
                    $error = 'No existe una cuenta activa con ese email';
                }
            } catch (PDOException $e) {
                $error = 'Error al procesar la solicitud: ' . $e->getMessage();
            }
        } else {
            $error = 'Error de conexión a la base de datos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .alert-error { background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:1rem 1.5rem; margin-bottom:1.5rem; border-radius:8px; display:flex; align-items:center; }
        .alert-error i { margin-right:1rem; }
        .hidden { display:none; }
        .login-options { display:flex; flex-direction:column; gap:12px; margin-top:25px; padding-top:25px; border-top:1px solid #e0e0e0; }
        .option-btn { display:flex; align-items:center; justify-content:center; gap:10px; padding:12px 20px; border:2px solid #3498db; border-radius:8px; background:transparent; color:#3498db; text-decoration:none; font-weight:600; font-size:14px; transition:all 0.3s; cursor:pointer; }
        .option-btn:hover { background:#3498db; color:white; transform:translateY(-2px); box-shadow:0 4px 12px rgba(52,152,219,0.3); }
        .option-btn.register { border-color:#27ae60; color:#27ae60; }
        .option-btn.register:hover { background:#27ae60; box-shadow:0 4px 12px rgba(39,174,96,0.3); }
        .option-btn.recovery { border-color:#e74c3c; color:#e74c3c; }
        .option-btn.recovery:hover { background:#e74c3c; box-shadow:0 4px 12px rgba(231,76,60,0.3); }
        .switcher-btn { background:none; border:none; color:#3498db; cursor:pointer; font-size:14px; text-decoration:underline; }
        .modal-nosotros-btn { background:transparent; border:none; color:#3498db; cursor:pointer; margin:5px 0 15px 0; font-size:14px; text-decoration:underline; }
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); overflow:auto; }
        .modal-content { background:white; margin:5% auto; padding:25px; width:80%; max-width:800px; border-radius:10px; box-shadow:0 0 20px rgba(0,0,0,0.3); position:relative; }
        .modal-close { float:right; cursor:pointer; font-size:28px; font-weight:bold; color:#333; position:absolute; right:15px; top:10px; }
        .modal-close:hover { color:#e74c3c; }
        .modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px; }
        @media (max-width:768px){ .modal-grid { grid-template-columns:1fr; } }
        .modal-section { margin-bottom:20px; }
        .modal-section h3 { border-bottom:2px solid; padding-bottom:5px; margin-bottom:10px; }
        .info-general h3 { color:#3498db; border-color:#3498db; }
        .subsistemas h3 { color:#e74c3c; border-color:#e74c3c; }
        .mision-vision h3 { color:#27ae60; border-color:#27ae60; }
        .valores h3 { color:#9b59b6; border-color:#9b59b6; }
        .comunidad h3 { color:#f39c12; border-color:#f39c12; }
        .valores-box { background:#f8f9fa; padding:15px; border-radius:8px; text-align:center; }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header text-center">
            <div class="logo-container">
               <img src="<?php echo APP_LOGO; ?>" alt="Logo U.E.N. Guarenas" class="logo" onerror="this.onerror=null; this.src='<?php echo APP_LOGO_FALLBACK; ?>';">
            </div>
            <h1><?php echo APP_NAME; ?></h1>
            <p class="text-muted">Sistema de Gestión Académica (SIGEB-G)</p>
            <button class="modal-nosotros-btn" onclick="document.getElementById('modal-nosotros').style.display='block'">
                <i class="fas fa-info-circle"></i> Acerca de U.E.N Guarenas
            </button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['timeout'])): ?>
            <div class="alert alert-warning"><i class="fas fa-clock"></i> La sesión ha expirado. Por favor ingrese nuevamente.</div>
        <?php endif; ?>
        
        <div id="login-form" <?php echo ($show_register || $show_recovery) ? 'class="hidden"' : ''; ?>>
            <form method="POST" class="login-form">
                <input type="hidden" name="login" value="1">
                <div class="form-group">
                    <label for="username">Usuario o Cédula</label>
                    <input type="text" id="username" name="username" required class="form-control" placeholder="Ingrese su usuario o cédula (12345678)" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required class="form-control" placeholder="Ingrese su contraseña">
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</button>
                <div class="demo-credentials">
                    <details>
                        <summary class="text-muted small">Credenciales de demostración</summary>
                        <div class="mt-2">
                            <div><strong>Admin:</strong> admin / password</div>
                            <div><strong>Docente:</strong> jcastro / password</div>
                            <div><strong>Por cédula:</strong> 12345678 / password</div>
                        </div>
                    </details>
                </div>
            </form>
            <div class="login-options">
                <a href="?action=register" class="option-btn register"><i class="fas fa-user-plus"></i> Crear Cuenta Nueva</a>
                <a href="?action=recovery" class="option-btn recovery"><i class="fas fa-key"></i> ¿Olvidaste tu Contraseña?</a>
            </div>
        </div>
        
        <div id="register-form" <?php echo $show_register ? '' : 'class="hidden"'; ?>>
            <form method="POST" class="login-form">
                <input type="hidden" name="register" value="1">
                <div class="form-group"><label>Nombres</label><input type="text" name="nombres" required class="form-control" placeholder="Ingrese sus nombres" value="<?php echo htmlspecialchars($_POST['nombres'] ?? ''); ?>"></div>
                <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" required class="form-control" placeholder="Ingrese sus apellidos" value="<?php echo htmlspecialchars($_POST['apellidos'] ?? ''); ?>"></div>
                <div class="form-group">
                    <label>Cédula</label>
                    <input type="text" name="cedula" required class="form-control cedula" placeholder="12345678" value="<?php echo htmlspecialchars($_POST['cedula'] ?? ''); ?>">
                    <small class="form-text">Escribe solo los números. El sistema agregará la "V-" automáticamente.</small>
                </div>
                <div class="form-group"><label>Usuario</label><input type="text" name="username" required class="form-control" placeholder="Elija un nombre de usuario" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" required class="form-control" placeholder="Ingrese su email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></div>
                <div class="form-group"><label>Teléfono (opcional)</label><input type="text" name="telefono" class="form-control telefono" placeholder="0412-1234567" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>"></div>
                <div class="form-group"><label>Contraseña</label><input type="password" name="password" required class="form-control" placeholder="Mínimo 6 caracteres" minlength="6"></div>
                <div class="form-group"><label>Confirmar Contraseña</label><input type="password" name="confirm_password" required class="form-control" placeholder="Repita su contraseña"></div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-user-plus"></i> Registrarse</button>
                <div class="form-switcher"><button type="button" class="switcher-btn" onclick="window.location.href='login.php'">← Volver al Login</button></div>
            </form>
        </div>
        
        <div id="recovery-form" <?php echo $show_recovery ? '' : 'class="hidden"'; ?>>
            <form method="POST" class="login-form">
                <input type="hidden" name="recovery" value="1">
                <div class="form-group"><label>Email</label><input type="email" name="email" required class="form-control" placeholder="Ingrese su email para recuperar contraseña" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-key"></i> Recuperar Contraseña</button>
                <div class="form-switcher"><button type="button" class="switcher-btn" onclick="window.location.href='login.php'">← Volver al Login</button></div>
            </form>
        </div>
        
        <div class="login-footer text-center mt-4">
            <p class="text-muted small">República Bolivariana de Venezuela<br>Ministerio del Poder Popular para la Educación<br>Los Teques - Estado Miranda</p>
        </div>
    </div>

    <div id="modal-nosotros" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="document.getElementById('modal-nosotros').style.display='none'">&times;</span>
            <center>
              <img src="<?php echo APP_LOGO; ?>" alt="Logo" style="max-width:120px; margin:10px; border-radius:8px;" onerror="this.onerror=null; this.src='<?php echo APP_LOGO_FALLBACK; ?>';">
                <h2 style="color:#2c3e50; margin-bottom:5px;">U.E.N. "GUARENAS"</h2>
                <p style="color:#7f8c8d;"><em>Educación, Amor y Patria</em></p>
            </center>
            <div class="modal-grid">
                <div>
                    <div class="modal-section info-general">
                        <h3>📋 Información General</h3>
                        <p><strong>📍 Dirección:</strong> Calle Roscio El Rincón, Los Teques</p>
                        <p><strong>📞 Teléfono:</strong> 0212-3641463</p>
                        <p><strong>🏛️ Zona Educativa:</strong> Miranda - Distrito Escolar Nº 1</p>
                        <p><strong>👨‍🏫 Director:</strong> Prof. Jhony Castro</p>
                        <p><strong>🕒 Horarios:</strong> Mañana: 7:00 AM - 12:30 PM | Tarde: 1:00 PM - 5:45 PM</p>
                    </div>
                    <div class="modal-section comunidad">
                        <h3>🏘️ Comunidad</h3>
                        <p><strong>📍 Ubicación:</strong> Sector El Rincón<br>
                        <strong>👥 Población atendida:</strong> Barrios aledaños (El Rincón, Alberto Ravel, La Lagunetica, El Panadero, Palo Alto, La Matica, entre otros)</p>
                    </div>
                </div>
                <div>
                    <div class="modal-section subsistemas">
                        <h3>🎓 Subsistemas</h3>
                        <p><strong>📊 Matrícula Total:</strong> 580 estudiantes</p>
                        <p><strong>👶 Educación Inicial:</strong> 63 estudiantes (5 secciones)</p>
                        <p><strong>👧 Educación Primaria:</strong> 195 estudiantes (12 secciones)</p>
                        <p><strong>👨‍🎓 Media General:</strong> 322 estudiantes (10 secciones)</p>
                        <p><strong>🦷 Mención Técnica:</strong> "Higiene Dental" (1er año)</p>
                    </div>
                </div>
            </div>
            <div class="modal-section mision-vision">
                <h3>🎯 Misión y Visión</h3>
                <p><strong>MISIÓN:</strong> Formar ciudadanos integrales con valores éticos, académicos y conciencia social, brindando servicios educativos desde Educación Inicial hasta Media General con enfoque humanista y calidad.</p>
                <p><strong>VISIÓN:</strong> Ser institución referente en educación integral, vinculada a la familia y comunidad, que aporte a la sociedad modelos educativos basados en desarrollo ético, académico, deportivo y socio-cultural.</p>
            </div>
            <div class="modal-section valores">
                <h3>💫 Valores Institucionales</h3>
                <div class="valores-box"><strong>Respeto • Responsabilidad • Solidaridad • Honestidad • Justicia • Tolerancia • Identidad Nacional • Trabajo</strong></div>
            </div>
        </div>
    </div>

    <script>
        function showLogin() { window.location.href = 'login.php'; }
        document.addEventListener('DOMContentLoaded', function() {
            window.onclick = function(event) {
                const modal = document.getElementById('modal-nosotros');
                if (event.target == modal) modal.style.display = "none";
            }
        });
    </script>
</body>
</html>