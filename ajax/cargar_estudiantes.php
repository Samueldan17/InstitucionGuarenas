<?php
// ajax/cargar_estudiantes.php - Carga dinámica de estudiantes
require_once '../config.php';

// Verificar que sea una petición AJAX y tenga los parámetros necesarios
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['seccion_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Petición inválida']);
    exit;
}

$seccion_id = (int)$_GET['seccion_id'];

if ($seccion_id <= 0) {
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
    $stmt = $db->prepare("
        SELECT id, nombres, apellidos, cedula 
        FROM estudiantes 
        WHERE seccion_id = ? AND estado = 'activo' 
        ORDER BY apellidos, nombres
    ");
    $stmt->execute([$seccion_id]);
    $estudiantes = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($estudiantes);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar estudiantes: ' . $e->getMessage()]);
}
?>