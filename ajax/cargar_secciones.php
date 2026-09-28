<?php
// ajax/cargar_secciones.php - Carga dinámica de secciones
require_once '../config.php';

// Verificar que sea una petición AJAX y tenga los parámetros necesarios
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['grado_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Petición inválida']);
    exit;
}

$grado_id = (int)$_GET['grado_id'];

if ($grado_id <= 0) {
    echo json_encode([]);
    exit;
}

$db = getDBConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT id, letra, turno FROM secciones WHERE grado_id = ? AND activo = 1 ORDER BY letra");
    $stmt->execute([$grado_id]);
    $secciones = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($secciones);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar secciones: ' . $e->getMessage()]);
}
?>