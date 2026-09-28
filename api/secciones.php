<?php
// api/secciones.php - Script para devolver secciones por Grado en JSON

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
// requireAuth(); 

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método no permitido.', [], 405);
}

$grado_id = isset($_GET['grado_id']) ? (int) $_GET['grado_id'] : 0;

if ($grado_id <= 0) {
    jsonResponse(false, 'ID de Grado inválido.', [], 400);
}

$db = getDBConnection();

if (!$db) {
    jsonResponse(false, 'Error de conexión a la base de datos.');
}

try {
    $stmt = $db->prepare("
        SELECT 
            id, 
            CONCAT(letra, ' (', turno, ')') AS nombre,
            letra
        FROM secciones 
        WHERE grado_id = ?
    ");
    $stmt->execute([$grado_id]);
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(true, 'Secciones cargadas.', $secciones);

} catch (PDOException $e) {
    error_log("Error al cargar secciones: " . $e->getMessage());
    jsonResponse(false, 'Error interno del servidor.');
}
?>