<?php
// functions.php - Funciones complejas para lógica de negocio (SIGEB-G)

function getBoletaData(int $estudianteId, int $lapso, int $anio_escolar = null) {
    $db = getDBConnection();
    if (!$db) {
        return ['error' => 'No se pudo conectar a la base de datos.'];
    }

    if ($anio_escolar === null) {
        $anio_escolar = date('Y');
    }

    try {
        // 1. Información del estudiante, grado, sección y docente guía
        $sqlEstudiante = "
            SELECT 
                e.id, e.cedula, e.nombres, e.apellidos, e.fecha_nacimiento,
                e.email, e.nacionalidad, e.lugar_nacimiento,
                e.nombre_representante, e.email_representante, e.parentesco,
                g.nombre AS grado_nombre, g.modalidad, s.letra AS seccion_letra, s.turno,
                CONCAT(u.nombres, ' ', u.apellidos) AS docente_guia_nombre
            FROM estudiantes e
            JOIN secciones s ON e.seccion_id = s.id
            JOIN grados g ON s.grado_id = g.id
            LEFT JOIN usuarios u ON s.docente_guia_id = u.id
            WHERE e.id = :estudiante_id
        ";
        $stmtEstudiante = $db->prepare($sqlEstudiante);
        $stmtEstudiante->execute([':estudiante_id' => $estudianteId]);
        $infoEstudiante = $stmtEstudiante->fetch();

        if (!$infoEstudiante) {
            return ['error' => 'Estudiante no encontrado.'];
        }

        // 2. Notas del lapso (3 momentos + D + R)
        $sqlNotas = "
            SELECT 
                m.nombre AS materia_nombre,
                m.area,
                n.evaluacion1,
                n.evaluacion2,
                n.evaluacion3,
                n.promedio AS definitiva,
                n.recuperacion,
                n.observaciones,
                CONCAT(doc.nombres, ' ', doc.apellidos) AS docente_nombre
            FROM notas n
            JOIN seccion_materias sm ON n.seccion_materia_id = sm.id
            JOIN materias m ON sm.materia_id = m.id
            LEFT JOIN usuarios doc ON sm.docente_id = doc.id
            WHERE n.estudiante_id = :estudiante_id 
              AND n.lapso = :lapso
              AND n.anio_escolar = :anio_escolar
            ORDER BY m.area, m.nombre
        ";
        $stmtNotas = $db->prepare($sqlNotas);
        $stmtNotas->execute([
            ':estudiante_id' => $estudianteId,
            ':lapso' => $lapso,
            ':anio_escolar' => $anio_escolar
        ]);
        $notas = $stmtNotas->fetchAll();

        // Si no hay notas, devolver error
        if (empty($notas)) {
            return ['error' => 'No hay notas registradas para este estudiante en el lapso seleccionado.'];
        }

        // 3. Información de la escuela
        $infoEscuela = [
            'nombre' => 'U.E.N. "GUARENAS"',
            'direccion' => 'Calle Roscio El Rincón, Los Teques',
            'telefono' => '0212-3641463',
            'codigo' => 'S3176D1510',
            'periodo_escolar' => $anio_escolar . '-' . ($anio_escolar + 1),
            'logo_path' => APP_LOGO,
            'lema' => 'Educación, Amor y Patria'
        ];

        return [
            'info_escuela' => $infoEscuela,
            'info_estudiante' => $infoEstudiante,
            'notas' => $notas,
            'lapso_consultado' => $lapso,
            'anio_escolar' => $anio_escolar
        ];

    } catch (PDOException $e) {
        error_log("Error en getBoletaData: " . $e->getMessage());
        return ['error' => 'Error al consultar datos en la base de datos.'];
    }
}

// Función para calcular la definitiva (D) a partir de 3 momentos (siempre usa los 3)
function calcularDefinitiva($e1, $e2, $e3) {
    $notas = [floatval($e1), floatval($e2), floatval($e3)];
    $suma = array_sum($notas);
    return round($suma / 3, 2);
}

// Función para obtener la nota definitiva final (considerando R si existe y es mayor)
function obtenerNotaFinal($definitiva, $recuperacion) {
    $d = floatval($definitiva);
    $r = floatval($recuperacion);
    if ($r > $d && $r > 0) {
        return $r;
    }
    return $d;
}

// Función para formato de fecha en español
function fechaEspañol($fecha) {
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $timestamp = strtotime($fecha);
    $dia = date('d', $timestamp);
    $mes = $meses[date('n', $timestamp)-1];
    $anio = date('Y', $timestamp);
    return "$dia de $mes de $anio";
}
?>