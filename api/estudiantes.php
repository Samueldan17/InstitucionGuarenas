<?php
// api/estudiantes.php - Script para devolver estudiantes por Sección en formato JSON

// 1. Incluir el archivo de configuración y funciones base
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Opcional: Proteger la ruta si tienes un sistema de autenticación
// requireAuth(); 

// 2. Verificar que el método HTTP sea GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método no permitido.', [], 405);
}

// 3. Obtener el ID de la sección enviado por el JavaScript
$seccion_id = isset($_GET['seccion_id']) ? (int) $_GET['seccion_id'] : 0;

// Validar el ID
if ($seccion_id <= 0) {
    jsonResponse(false, 'ID de Sección inválido.', [], 400);
}

// 4. Conexión a la base de datos
$db = getDBConnection();

if (!$db) {
    jsonResponse(false, 'Error de conexión a la base de datos.');
}

try {
    // 5. Preparar y ejecutar la consulta SQL
    $stmt = $db->prepare("
        SELECT 
            id, 
            CONCAT(apellidos, ', ', nombres) AS nombre,
            cedula
        FROM estudiantes 
        WHERE seccion_id = ?
        ORDER BY nombre ASC
    ");
    $stmt->execute([$seccion_id]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. ÉXITO: Devolver los datos en formato JSON
    jsonResponse(true, 'Estudiantes cargados correctamente.', $estudiantes);

} catch (PDOException $e) {
    // Manejo de errores de base de datos
    error_log("Error al cargar estudiantes: " . $e->getMessage());
    jsonResponse(false, 'Error interno del servidor al cargar la lista de estudiantes.');
}
?>