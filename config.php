<?php
// config.php - Configuración y funciones del sistema (VERSIÓN 3.4 - SIGEB-G)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================
// 1. CONFIGURACIÓN DE BASE DE DATOS
// =========================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_gestion_escolar');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// =========================================================
// 2. CONFIGURACIÓN DE LA APLICACIÓN
// =========================================================
define('APP_NAME', 'U.E.N. Guarenas');
define('APP_VERSION', '3.4');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_path = '/InstitucionGuarenas';
define('APP_URL', $protocol . '://' . $host . $base_path);

// --- LOGO CON VERIFICACIÓN Y FALLBACK ---
$logo_fallback_svg = 'data:image/svg+xml;charset=utf-8,' . rawurlencode('
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100" viewBox="0 0 200 100">
  <rect width="200" height="100" fill="#2c3e50"/>
  <text x="100" y="40" font-family="Arial" font-size="20" fill="white" text-anchor="middle">CEN LOS TEQUES</text>
  <text x="100" y="70" font-family="Arial" font-size="14" fill="#f1c40f" text-anchor="middle">Amor y Patria</text>
</svg>');

$logo_path = __DIR__ . '/assets/img/logo.png';
if (file_exists($logo_path) && @getimagesize($logo_path) !== false) {
    define('APP_LOGO', APP_URL . '/assets/img/logo.png');
} else {
    define('APP_LOGO', $logo_fallback_svg);
}
define('APP_LOGO_FALLBACK', $logo_fallback_svg);

// =========================================================
// 3. SEGURIDAD
// =========================================================
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_BLOCK_TIME', 900);
define('SESSION_TIMEOUT', 3600);

// =========================================================
// 4. CONEXIÓN A LA BASE DE DATOS (PDO)
// =========================================================
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            error_log("Error de conexión DB: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// =========================================================
// 5. MANEJO GLOBAL DE EXCEPCIONES
// =========================================================
set_exception_handler(function($e) {
    error_log("Excepción no capturada: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    if (headers_sent()) {
        echo "<h1>Error interno del servidor</h1><p>Por favor, intente más tarde.</p>";
    } else {
        http_response_code(500);
        jsonResponse(false, 'Ha ocurrido un error interno. Por favor, intente más tarde.', [], 500, true);
    }
    exit;
});

// =========================================================
// 6. AUTENTICACIÓN Y ROLES
// =========================================================
function requireAuth() {
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_nombre'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// =========================================================
// 7. CSRF (Tokens)
// =========================================================
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// =========================================================
// 8. SANITIZACIÓN Y RESPUESTAS
// =========================================================
function sanitize($data) {
    if (is_array($data)) return array_map('sanitize', $data);
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($success, $message = '', $data = [], $statusCode = 200, $exit = true) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    if ($exit) exit;
}

// =========================================================
// 9. VALIDACIONES VENEZOLANAS
// =========================================================
function validarCedulaVenezolana($cedula) {
    $cedula = strtoupper(trim($cedula));
    if (!preg_match('/^[VE]-/', $cedula)) $cedula = 'V-' . $cedula;
    if (!preg_match('/^[VE]-\d{6,9}$/', $cedula)) return "La cédula debe tener formato V-12345678 o 12345678";
    return true;
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validarTelefonoVenezolano($telefono) {
    $telefono = trim($telefono);
    if (empty($telefono)) return true;
    return preg_match('/^0(412|414|416|424|426|428)[-.]?\d{7}$/', $telefono) === 1;
}

function formatearCedulaParaBD($cedula) {
    $cedula = strtoupper(trim($cedula));
    if (!preg_match('/^[VE]-/', $cedula)) $cedula = 'V-' . $cedula;
    return preg_replace('/[^VE0-9-]/', '', $cedula);
}

function formatearNombrePropio($nombre) {
    return ucwords(strtolower(trim($nombre)));
}

// =========================================================
// 10. LOGS DE AUDITORÍA
// =========================================================
function registrarLog($accion, $tabla = null, $registro_id = null, $detalles = null) {
    $db = getDBConnection();
    if (!$db) return false;
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    try {
        $stmt = $db->prepare("INSERT INTO logs (usuario_id, accion, tabla, registro_id, detalles, ip) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $accion, $tabla, $registro_id, $detalles, $ip]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al registrar log: " . $e->getMessage());
        return false;
    }
}

// =========================================================
// 11. SIDEBAR
// =========================================================
function getSidebar($currentPage = '') {
    $rol = $_SESSION['usuario_rol'] ?? 'guest';
    $nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
    
    $sidebar = '
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="' . APP_LOGO . '" alt="Logo" class="logo-img" onerror="this.onerror=null; this.src=\'' . APP_LOGO_FALLBACK . '\';">
            </div>
            <h2>' . APP_NAME . '</h2>
        </div>
        <nav class="sidebar-nav">
            <ul>';
    
    if ($rol === 'estudiante') {
        $sidebar .= '
            <li class="nav-item ' . ($currentPage == 'estudiante' ? 'active' : '') . '">
                <a href="panel_estudiante.php" class="nav-link"><i class="fas fa-home"></i><span>Mi Panel</span></a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a>
            </li>';
    } elseif ($rol === 'representante') {
        $sidebar .= '
            <li class="nav-item ' . ($currentPage == 'representante' ? 'active' : '') . '">
                <a href="panel_representante.php" class="nav-link"><i class="fas fa-home"></i><span>Mi Panel</span></a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a>
            </li>';
    } else {
        $sidebar .= '
            <li class="nav-item ' . ($currentPage == 'dashboard' ? 'active' : '') . '">
                <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            </li>
            <li class="nav-item ' . ($currentPage == 'estudiantes' ? 'active' : '') . '">
                <a href="estudiantes.php" class="nav-link"><i class="fas fa-user-graduate"></i><span>Estudiantes</span></a>
            </li>
            <li class="nav-item ' . ($currentPage == 'notas' ? 'active' : '') . '">
                <a href="asignar_notas.php" class="nav-link"><i class="fas fa-edit"></i><span>Asignar Notas</span></a>
            </li>
            <li class="nav-item ' . ($currentPage == 'grados' ? 'active' : '') . '">
                <a href="grados.php" class="nav-link"><i class="fas fa-layer-group"></i><span>Grados</span></a>
            </li>
            <li class="nav-item ' . ($currentPage == 'materias' ? 'active' : '') . '">
                <a href="materias.php" class="nav-link"><i class="fas fa-book"></i><span>Materias</span></a>
            </li>
            <li class="nav-item ' . ($currentPage == 'secciones' ? 'active' : '') . '">
                <a href="secciones.php" class="nav-link"><i class="fas fa-users"></i><span>Secciones</span></a>
            </li>
            <li class="nav-item ' . ($currentPage == 'carga_academica' ? 'active' : '') . '">
                <a href="carga_academica.php" class="nav-link"><i class="fas fa-chalkboard-teacher"></i><span>Carga Académica</span></a>
            </li>
            <li class="nav-item ' . ($currentPage == 'boletas' ? 'active' : '') . '">
                <a href="boletas.php" class="nav-link"><i class="fas fa-file-alt"></i><span>Boletas</span></a>
            </li>
            <li class="nav-item ' . ($currentPage == 'reportes' ? 'active' : '') . '">
                <a href="reportes.php" class="nav-link"><i class="fas fa-chart-bar"></i><span>Reportes</span></a>
            </li>';
        
        if ($rol === 'admin') {
            $sidebar .= '
                <li class="nav-item ' . ($currentPage == 'usuarios' ? 'active' : '') . '">
                    <a href="usuarios.php" class="nav-link"><i class="fas fa-users-cog"></i><span>Usuarios</span></a>
                </li>
                <li class="nav-item ' . ($currentPage == 'auditoria' ? 'active' : '') . '">
                    <a href="auditoria.php" class="nav-link"><i class="fas fa-history"></i><span>Auditoría</span></a>
                </li>
                <li class="nav-item ' . ($currentPage == 'configuracion' ? 'active' : '') . '">
                    <a href="configuracion.php" class="nav-link"><i class="fas fa-cog"></i><span>Configuración</span></a>
                </li>';
        }
        
        $sidebar .= '
                <li class="nav-item ' . ($currentPage == 'perfil' ? 'active' : '') . '">
                    <a href="perfil.php" class="nav-link"><i class="fas fa-user-circle"></i><span>Mi Perfil</span></a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a>
                </li>';
    }
    
    $sidebar .= '
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">' . substr($nombre, 0, 2) . '</div>
                <div class="user-details">
                    <span class="user-name">' . $nombre . '</span>
                    <span class="user-role">' . (isset($_SESSION['usuario_rol']) ? ucfirst($_SESSION['usuario_rol']) : 'Usuario') . '</span>
                </div>
            </div>
        </div>
    </aside>';
    
    return $sidebar;
}

// =========================================================
// 12. CARGAR FUNCIONES DE NEGOCIO
// =========================================================
require_once __DIR__ . '/functions.php';
?>