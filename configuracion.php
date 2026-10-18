<?php
// configuracion.php - Configuración del sistema (VERSIÓN 3.7 - Control de Lapsos)
require_once 'config.php';
requireAuth();
if ($_SESSION['usuario_rol'] !== 'admin') { header('Location: dashboard.php'); exit; }
$db = getDBConnection();
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// =========================================================
// FUNCIONES HELPER
// =========================================================
function formatearPeso($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    return round($bytes / (1024 * 1024), 2) . ' MB';
}

// =========================================================
// PROCESAR SUBIDA DE LOGO
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_logo'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['logo'];
            $tipos_permitidos = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'];
            $tipo = mime_content_type($archivo['tmp_name']);
            
            if (!in_array($tipo, $tipos_permitidos)) {
                $error = "❌ Formato no permitido. Solo se aceptan PNG, JPG, SVG o WEBP.";
            } elseif ($archivo['size'] > 2 * 1024 * 1024) {
                $error = "❌ El archivo no debe superar los 2 MB.";
            } else {
                $destino = __DIR__ . '/assets/img/logo.png';
                if (move_uploaded_file($archivo['tmp_name'], $destino)) {
                    $message = "✅ Logo actualizado correctamente. Recarga con Ctrl+F5 para verlo.";
                    registrarLog('actualizar_logo', 'configuracion', null, "Nuevo logo subido");
                } else {
                    $error = "❌ Error al guardar el archivo.";
                }
            }
        } else {
            $error = "❌ No se seleccionó ningún archivo.";
        }
    }
    header("Location: configuracion.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// =========================================================
// ACTUALIZAR INFORMACIÓN DEL SISTEMA
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_info'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $responsable = sanitize($_POST['responsable_sistema'] ?? '');
        $anio_activo = sanitize($_POST['anio_escolar_activo'] ?? '');
        
        if (empty($responsable)) {
            $error = "El responsable del sistema es obligatorio.";
        } elseif (!preg_match('/^\d{4}-\d{4}$/', $anio_activo)) {
            $error = "El año escolar debe tener el formato 2025-2026.";
        } else {
            setConfig($db, 'responsable_sistema', $responsable);
            setConfig($db, 'anio_escolar_activo', $anio_activo);
            $message = "✅ Información actualizada correctamente.";
            registrarLog('actualizar_config', 'configuracion', null, "Info general actualizada");
        }
    }
    header("Location: configuracion.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// =========================================================
// CONTROL DE LAPSOS (ABRIR/CERRAR)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado_lapso'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $lapso_num = (int)$_POST['lapso_num'];
        $nuevo_estado = $_POST['nuevo_estado'] === 'cerrar' ? '1' : '0';
        
        setConfig($db, 'lapso_' . $lapso_num . '_cerrado', $nuevo_estado);
        $accion_texto = $nuevo_estado === '1' ? 'cerrado' : 'abierto';
        $message = "✅ El Lapso $lapso_num ha sido $accion_texto correctamente.";
        registrarLog('cambio_estado_lapso', 'configuracion', null, "Lapso $lapso_num -> $accion_texto");
    }
    header("Location: configuracion.php?message=" . urlencode($message) . "&error=" . urlencode($error));
    exit;
}

// =========================================================
// EXPORTAR BASE DE DATOS (RESPALDO)
// =========================================================
if (isset($_GET['accion']) && $_GET['accion'] === 'backup') {
    $tablas = ['usuarios', 'estudiantes', 'grados', 'materias', 'secciones', 'seccion_materias', 'notas', 'logs', 'configuracion'];
    $fecha = date('Y-m-d_H-i-s');
    $nombre_archivo = "respaldo_sigeb_" . $fecha . ".sql";
    
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    
    $output = "-- ============================================\n";
    $output .= "-- RESPALDO SIGEB-G\n";
    $output .= "-- Institución: " . APP_NAME . "\n";
    $output .= "-- Fecha: " . date('d/m/Y H:i:s') . "\n";
    $output .= "-- Responsable: " . getConfig($db, 'responsable_sistema', 'N/A') . "\n";
    $output .= "-- ============================================\n\n";
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $output .= "SET time_zone = \"+00:00\";\n\n";
    $output .= "DROP DATABASE IF EXISTS `" . DB_NAME . "`;\n";
    $output .= "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n";
    $output .= "USE `" . DB_NAME . "`;\n\n";
    
    foreach ($tablas as $tabla) {
        try {
            $stmt = $db->query("SHOW CREATE TABLE `$tabla`");
            $crear = $stmt->fetch();
            $output .= "\n-- --------------------------------------------\n";
            $output .= "-- Estructura de tabla `$tabla`\n";
            $output .= "-- --------------------------------------------\n";
            $output .= "DROP TABLE IF EXISTS `$tabla`;\n";
            $output .= $crear['Create Table'] . ";\n\n";
            
            $stmt = $db->query("SELECT * FROM `$tabla`");
            $filas = $stmt->fetchAll();
            if (count($filas) > 0) {
                $output .= "-- Datos de `$tabla`\n";
                foreach ($filas as $fila) {
                    $columnas = array_keys($fila);
                    $valores = array_map(function($v) use ($db) {
                        if ($v === null) return 'NULL';
                        return $db->quote($v);
                    }, array_values($fila));
                    $output .= "INSERT INTO `$tabla` (`" . implode("`, `", $columnas) . "`) VALUES (" . implode(", ", $valores) . ");\n";
                }
                $output .= "\n";
            }
        } catch (PDOException $e) {
            $output .= "-- Error en tabla $tabla: " . $e->getMessage() . "\n";
        }
    }
    $output .= "\n-- ============================================\n";
    $output .= "-- FIN DEL RESPALDO\n";
    $output .= "-- ============================================\n";
    
    setConfig($db, 'ultimo_respaldo', date('Y-m-d H:i:s'));
    registrarLog('backup_bd', 'configuracion', null, "Respaldo descargado");
    echo $output;
    exit;
}

// =========================================================
// ESTADÍSTICAS DEL SISTEMA
// =========================================================
$estadisticas = [];
$tablas_info = ['usuarios', 'estudiantes', 'secciones', 'materias', 'notas', 'grados', 'seccion_materias'];
foreach ($tablas_info as $tabla) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn();
        $estadisticas[$tabla] = $count;
    } catch (PDOException $e) { $estadisticas[$tabla] = 0; }
}

$peso_bd = 0;
try {
    $stmt = $db->prepare("SELECT SUM(data_length + index_length) as peso FROM information_schema.TABLES WHERE table_schema = ?");
    $stmt->execute([DB_NAME]);
    $peso_bd = $stmt->fetch()['peso'] ?? 0;
} catch (PDOException $e) {}

$anios_escolares = $db->query("SELECT DISTINCT anio_escolar FROM secciones WHERE anio_escolar IS NOT NULL ORDER BY anio_escolar DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($anios_escolares)) { $anios_escolares = [date('Y') . '-' . (date('Y') + 1)]; }

$info_general = [
    'institucion' => APP_NAME,
    'sistema' => 'SIGEB-G (Sistema de Gestión de Calificaciones)',
    'version' => APP_VERSION,
    'anio_escolar_activo' => getConfig($db, 'anio_escolar_activo', date('Y') . '-' . (date('Y') + 1)),
    'responsable_sistema' => getConfig($db, 'responsable_sistema', 'Departamento de Evaluación y Control de Estudios'),
    'ultimo_respaldo' => getConfig($db, 'ultimo_respaldo', null),
];

$ultimo_respaldo_texto = 'Nunca se ha realizado un respaldo';
$ultimo_respaldo_clase = 'danger';
if ($info_general['ultimo_respaldo']) {
    $dias = round((time() - strtotime($info_general['ultimo_respaldo'])) / 86400, 0);
    $ultimo_respaldo_texto = date('d/m/Y H:i', strtotime($info_general['ultimo_respaldo'])) . " (hace $dias día" . ($dias != 1 ? 's' : '') . ")";
    $ultimo_respaldo_clase = $dias > 30 ? 'warning' : 'success';
}

$info_sistema = [
    'PHP Version' => phpversion(),
    'Servidor' => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache',
    'MySQL Version' => $db->query("SELECT VERSION()")->fetchColumn(),
    'Sistema Operativo' => PHP_OS,
    'Zona Horaria' => date_default_timezone_get(),
    'Peso BD' => formatearPeso($peso_bd)
];

// Obtener estados de los lapsos
$lapsos_estado = [];
for ($i = 1; $i <= 3; $i++) {
    $lapsos_estado[$i] = getConfig($db, 'lapso_' . $i . '_cerrado', '0') == '1';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/fontawesome/css/all.min.css">
    <style>
        .config-section { background: #fff; border: 1px solid #e1e4e8; border-radius: 6px; margin-bottom: 20px; overflow: hidden; }
        .config-section-header { background: #f6f8fa; padding: 12px 20px; border-bottom: 1px solid #e1e4e8; font-weight: 600; font-size: 14px; color: #24292e; }
        .config-section-header i { color: #586069; margin-right: 8px; }
        .config-section-body { padding: 20px; }
        .data-table-formal { width: 100%; border-collapse: collapse; font-size: 14px; }
        .data-table-formal tr { border-bottom: 1px solid #eaecef; }
        .data-table-formal tr:last-child { border-bottom: none; }
        .data-table-formal td { padding: 12px 15px; vertical-align: top; }
        .data-table-formal td:first-child { width: 240px; color: #586069; font-weight: 500; background: #fafbfc; border-right: 1px solid #eaecef; }
        .data-table-formal td:last-child { color: #24292e; }
        .logo-container-formal { display: flex; gap: 25px; align-items: flex-start; flex-wrap: wrap; }
        .logo-box-formal { width: 140px; height: 140px; border: 1px solid #e1e4e8; border-radius: 6px; background: #fff; display: flex; align-items: center; justify-content: center; padding: 10px; }
        .logo-box-formal img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .stats-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .stats-table th { text-align: left; padding: 10px 15px; background: #f6f8fa; color: #586069; font-weight: 600; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #e1e4e8; }
        .stats-table td { padding: 12px 15px; border-bottom: 1px solid #eaecef; color: #24292e; }
        .stats-table td:last-child { text-align: right; font-weight: 600; color: #24292e; }
        .stats-table tr:hover { background: #fafbfc; }
        .btn-formal { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #24292e; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; text-decoration: none; transition: background 0.2s; }
        .btn-formal:hover { background: #444d56; }
        .btn-formal.btn-success-formal { background: #28a745; }
        .btn-formal.btn-success-formal:hover { background: #218838; }
        .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-success { background: #d4edda; color: #155724; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-danger { background: #f8d7da; color: #721c24; }
        .year-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px; }
        .year-badge { padding: 4px 12px; background: #e1e4e8; border-radius: 12px; font-size: 12px; font-weight: 600; color: #24292e; }
        .year-badge.active { background: #0366d6; color: white; }
        
        /* Estilos para los lapsos */
        .lapso-card { border: 1px solid #e1e4e8; border-radius: 8px; padding: 20px; text-align: center; }
        .lapso-card.cerrado { background: #f8d7da; border-color: #f5c6cb; }
        .lapso-card.abierto { background: #d4edda; border-color: #c3e6cb; }
        .lapso-icon { font-size: 40px; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('configuracion'); ?>
    <main class="main-content">
        <header class="main-header"><h1><i class="fas fa-cog"></i> Configuración del Sistema</h1></header>
        <div class="content-container">
            <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <!-- Información General -->
            <div class="config-section">
                <div class="config-section-header"><i class="fas fa-info-circle"></i> Información General</div>
                <div class="config-section-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <table class="data-table-formal">
                            <tr><td>Institución</td><td><strong><?php echo $info_general['institucion']; ?></strong></td></tr>
                            <tr><td>Sistema</td><td><?php echo $info_general['sistema']; ?> <span style="color:#586069;">(v<?php echo $info_general['version']; ?>)</span></td></tr>
                            <tr><td>Año Escolar Activo</td><td><input type="text" name="anio_escolar_activo" class="form-control" value="<?php echo htmlspecialchars($info_general['anio_escolar_activo']); ?>" pattern="\d{4}-\d{4}" style="max-width: 150px;" required></td></tr>
                            <tr><td>Responsable del Sistema</td><td><input type="text" name="responsable_sistema" class="form-control" value="<?php echo htmlspecialchars($info_general['responsable_sistema']); ?>" required></td></tr>
                            <tr><td>Estado del Sistema</td><td><span class="status-badge status-success"><i class="fas fa-circle" style="font-size: 8px;"></i> Operativo</span></td></tr>
                            <tr><td>Último Respaldo</td><td><span class="status-badge status-<?php echo $ultimo_respaldo_clase; ?>"><i class="fas fa-<?php echo $info_general['ultimo_respaldo'] ? 'check-circle' : 'exclamation-triangle'; ?>"></i> <?php echo $ultimo_respaldo_texto; ?></span></td></tr>
                        </table>
                        <div style="margin-top: 15px; text-align: right;"><button type="submit" name="guardar_info" class="btn-formal"><i class="fas fa-save"></i> Guardar Información</button></div>
                    </form>
                </div>
            </div>

            <!-- CONTROL DE LAPSOS -->
            <div class="config-section">
                <div class="config-section-header"><i class="fas fa-lock"></i> Control de Cierre de Lapsos</div>
                <div class="config-section-body">
                    <p style="color: #586069; font-size: 13px; margin-bottom: 20px;">
                        Al cerrar un lapso, las notas quedan bloqueadas para que los docentes no puedan modificarlas. Si necesita hacer una corrección, vuelva a abrirlo, edite las notas y ciérrelo de nuevo.
                    </p>
                    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
                        <?php for($i=1; $i<=3; $i++): 
                            $esta_cerrado = $lapsos_estado[$i];
                            $clase = $esta_cerrado ? 'cerrado' : 'abierto';
                        ?>
                        <div class="lapso-card <?php echo $clase; ?>">
                            <div class="lapso-icon">
                                <i class="fas fa-<?php echo $esta_cerrado ? 'lock' : 'unlock'; ?>" style="color: <?php echo $esta_cerrado ? '#721c24' : '#155724'; ?>;"></i>
                            </div>
                            <h3><?php echo $i; ?>er Lapso</h3>
                            <p style="font-weight: bold; margin: 10px 0;">
                                <?php echo $esta_cerrado ? 'CERRADO' : 'ABIERTO'; ?>
                            </p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="cambiar_estado_lapso" value="1">
                                <input type="hidden" name="lapso_num" value="<?php echo $i; ?>">
                                <input type="hidden" name="nuevo_estado" value="<?php echo $esta_cerrado ? 'abrir' : 'cerrar'; ?>">
                                <button type="submit" class="btn-formal <?php echo $esta_cerrado ? '' : 'btn-success-formal'; ?>" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-<?php echo $esta_cerrado ? 'unlock' : 'lock'; ?>"></i> 
                                    <?php echo $esta_cerrado ? 'Abrir Lapso' : 'Cerrar Lapso'; ?>
                                </button>
                            </form>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Historial de Años Escolares -->
            <div class="config-section">
                <div class="config-section-header"><i class="fas fa-calendar-alt"></i> Años Escolares Registrados (<?php echo count($anios_escolares); ?>)</div>
                <div class="config-section-body">
                    <div class="year-list">
                        <?php foreach ($anios_escolares as $anio): ?>
                            <span class="year-badge <?php echo $anio == $info_general['anio_escolar_activo'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($anio); ?>
                                <?php if ($anio == $info_general['anio_escolar_activo']): ?><i class="fas fa-check" style="margin-left: 5px;"></i><?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Logo y Respaldo -->
            <div class="content-grid">
                <div class="config-section">
                    <div class="config-section-header"><i class="fas fa-image"></i> Logo del Sistema</div>
                    <div class="config-section-body">
                        <div class="logo-container-formal">
                            <div class="logo-box-formal"><img src="<?php echo APP_LOGO; ?>?v=<?php echo time(); ?>" alt="Logo" onerror="this.onerror=null; this.src='<?php echo APP_LOGO_FALLBACK; ?>';"></div>
                            <div style="flex: 1; min-width: 220px;">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <div class="form-group">
                                        <label style="font-size: 13px; color: #586069;">Archivo de imagen</label>
                                        <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/svg+xml,image/webp" required>
                                        <small class="form-text">Máximo 2 MB.</small>
                                    </div>
                                    <button type="submit" name="subir_logo" class="btn-formal"><i class="fas fa-upload"></i> Actualizar Logo</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="config-section">
                    <div class="config-section-header"><i class="fas fa-database"></i> Respaldo de Base de Datos</div>
                    <div class="config-section-body">
                        <p style="color: #586069; font-size: 13px; margin-bottom: 15px;">Descarga un archivo <code>.sql</code> con toda la información.</p>
                        <a href="?accion=backup" class="btn-formal btn-success-formal" style="margin-bottom: 15px;"><i class="fas fa-download"></i> Descargar Respaldo</a>
                        <table class="data-table-formal" style="margin-top: 15px;">
                            <tr><td>Peso de la Base de Datos</td><td><?php echo $info_sistema['Peso BD']; ?></td></tr>
                            <tr><td>Último Respaldo</td><td><?php echo $info_general['ultimo_respaldo'] ? date('d/m/Y H:i', strtotime($info_general['ultimo_respaldo'])) : 'Nunca'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Información Técnica -->
            <div class="config-section" style="margin-top: 20px;">
                <div class="config-section-header"><i class="fas fa-server"></i> Información del Servidor</div>
                <div class="config-section-body">
                    <table class="data-table-formal">
                        <?php foreach ($info_sistema as $clave => $valor): ?>
                        <tr><td><?php echo $clave; ?></td><td><?php echo htmlspecialchars($valor); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
</body>
</html>