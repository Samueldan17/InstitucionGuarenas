<?php
// api/boleta.php - Script para devolver datos de la boleta en formato JSON

// 1. Incluir los archivos necesarios
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// 2. Asegurar que solo usuarios autenticados puedan acceder
// requireAuth();

// 3. Verificar que la petición sea de tipo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método no permitido.', [], 405);
}

// 4. Obtener los parámetros
$estudiante_id = isset($_GET['estudiante_id']) ? (int) $_GET['estudiante_id'] : 0;
$lapso = isset($_GET['lapso']) ? (int) $_GET['lapso'] : 0;

// Validar parámetros
if ($estudiante_id <= 0 || $lapso <= 0 || $lapso > 3) {
    jsonResponse(false, 'Parámetros de estudiante o lapso inválidos.', [], 400);
}

// 5. Llamar a la función
$boletaData = getBoletaData($estudiante_id, $lapso);

// 6. Procesar la respuesta
if (isset($boletaData['error'])) {
    jsonResponse(false, $boletaData['error']);
} else if (!$boletaData['info_estudiante']) {
    jsonResponse(false, 'Estudiante no encontrado o datos incompletos.');
} else {
    jsonResponse(true, 'Datos de boleta obtenidos correctamente.', $boletaData);
}
?>