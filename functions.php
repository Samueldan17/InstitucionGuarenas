<?php
// functions.php - Funciones complejas para lógica de negocio (SIGEB-G) - VERSIÓN 3.5

// =========================================================
// FUNCIONES DE BOLETA
// =========================================================

/**
 * Obtiene los datos completos de un estudiante para generar su boleta
 */
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
                g.nombre AS grado_nombre, g.nivel,
                s.letra AS seccion_letra, s.turno, s.anio_escolar,
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

        // 2. Notas del lapso
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

/**
 * Calcula la definitiva (D) a partir de 3 momentos
 */
function calcularDefinitiva($e1, $e2, $e3) {
    $notas = [floatval($e1), floatval($e2), floatval($e3)];
    $suma = array_sum($notas);
    return round($suma / 3, 2);
}

/**
 * Obtiene la nota final considerando R si existe y es mayor
 */
function obtenerNotaFinal($definitiva, $recuperacion) {
    $d = floatval($definitiva);
    $r = floatval($recuperacion);
    if ($r > $d && $r > 0) {
        return $r;
    }
    return $d;
}

/**
 * Formato de fecha en español
 */
function fechaEspañol($fecha) {
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $timestamp = strtotime($fecha);
    $dia = date('d', $timestamp);
    $mes = $meses[date('n', $timestamp)-1];
    $anio = date('Y', $timestamp);
    return "$dia de $mes de $anio";
}

// =========================================================
// FUNCIONES DE REPRESENTANTES
// =========================================================

/**
 * Obtiene la lista de estudiantes representados por un usuario
 */
function getRepresentados($representante_id) {
    $db = getDBConnection();
    if (!$db) return [];
    
    try {
        $stmt = $db->prepare("
            SELECT e.id, e.cedula, e.nombres, e.apellidos, e.email,
                   g.nombre AS grado_nombre, s.letra AS seccion_letra, s.turno,
                   re.parentesco, re.es_principal
            FROM representantes_estudiantes re
            JOIN estudiantes e ON re.estudiante_id = e.id
            LEFT JOIN secciones s ON e.seccion_id = s.id
            LEFT JOIN grados g ON s.grado_id = g.id
            WHERE re.representante_id = ? AND e.estado = 'activo'
            ORDER BY e.apellidos, e.nombres
        ");
        $stmt->execute([$representante_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error en getRepresentados: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene los datos del estudiante vinculado a un usuario
 */
function getEstudiantePorUsuario($usuario_id) {
    $db = getDBConnection();
    if (!$db) return null;
    
    try {
        $stmt = $db->prepare("
            SELECT e.*, g.nombre AS grado_nombre, s.letra AS seccion_letra, s.turno
            FROM estudiantes e
            LEFT JOIN secciones s ON e.seccion_id = s.id
            LEFT JOIN grados g ON s.grado_id = g.id
            WHERE e.usuario_id = ? AND e.estado = 'activo'
            LIMIT 1
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error en getEstudiantePorUsuario: " . $e->getMessage());
        return null;
    }
}

/**
 * Vincula un representante con un estudiante
 */
function vincularRepresentanteEstudiante($representante_id, $estudiante_id, $parentesco = 'Representante', $es_principal = 0) {
    $db = getDBConnection();
    if (!$db) return false;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO representantes_estudiantes (representante_id, estudiante_id, parentesco, es_principal)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE parentesco = VALUES(parentesco), es_principal = VALUES(es_principal)
        ");
        return $stmt->execute([$representante_id, $estudiante_id, $parentesco, $es_principal]);
    } catch (PDOException $e) {
        error_log("Error en vincularRepresentanteEstudiante: " . $e->getMessage());
        return false;
    }
}

// =========================================================
// FUNCIONES DE CREACIÓN DE USUARIOS
// =========================================================

/**
 * Crea un usuario para un estudiante (usando su cédula como username y contraseña)
 * Devuelve el ID del usuario creado, o false si falla
 */
function crearUsuarioEstudiante($estudiante_id) {
    $db = getDBConnection();
    if (!$db) return false;
    
    try {
        // Obtener datos del estudiante
        $stmt = $db->prepare("SELECT id, cedula, nombres, apellidos, email, telefono_contacto, usuario_id FROM estudiantes WHERE id = ?");
        $stmt->execute([$estudiante_id]);
        $est = $stmt->fetch();
        
        if (!$est) return false;
        if ($est['usuario_id']) return $est['usuario_id']; // Ya tiene usuario
        
        // Verificar si ya existe un usuario con esa cédula
        $check = $db->prepare("SELECT id FROM usuarios WHERE cedula = ?");
        $check->execute([$est['cedula']]);
        if ($check->fetch()) return false;
        
        // Crear usuario
        $username = $est['cedula'];
        $password_hash = password_hash($est['cedula'], PASSWORD_DEFAULT);
        $email = $est['email'] ?: strtolower(str_replace('-', '', $est['cedula'])) . '@uenguarenas.edu.ve';
        
        $stmt = $db->prepare("
            INSERT INTO usuarios (username, cedula, nombres, apellidos, email, telefono, rol, password, activo)
            VALUES (?, ?, ?, ?, ?, ?, 'estudiante', ?, 1)
        ");
        $stmt->execute([
            $username, $est['cedula'], $est['nombres'], $est['apellidos'],
            $email, $est['telefono_contacto'], $password_hash
        ]);
        
        $nuevo_usuario_id = $db->lastInsertId();
        
        // Vincular usuario al estudiante
        $stmt = $db->prepare("UPDATE estudiantes SET usuario_id = ? WHERE id = ?");
        $stmt->execute([$nuevo_usuario_id, $estudiante_id]);
        
        registrarLog('crear_usuario_estudiante', 'usuarios', $nuevo_usuario_id, "Usuario para estudiante {$est['cedula']}");
        
        return $nuevo_usuario_id;
    } catch (PDOException $e) {
        error_log("Error en crearUsuarioEstudiante: " . $e->getMessage());
        return false;
    }
}

/**
 * Crea un usuario con rol de representante
 */
function crearUsuarioRepresentante($cedula, $nombres, $apellidos, $email = null, $telefono = null) {
    $db = getDBConnection();
    if (!$db) return false;
    
    try {
        $cedula = formatearCedulaParaBD($cedula);
        
        // Verificar si ya existe
        $check = $db->prepare("SELECT id FROM usuarios WHERE cedula = ?");
        $check->execute([$cedula]);
        if ($check->fetch()) return false;
        
        $username = $cedula;
        $password_hash = password_hash($cedula, PASSWORD_DEFAULT);
        $email = $email ?: strtolower(str_replace('-', '', $cedula)) . '@uenguarenas.edu.ve';
        
        $stmt = $db->prepare("
            INSERT INTO usuarios (username, cedula, nombres, apellidos, email, telefono, rol, password, activo)
            VALUES (?, ?, ?, ?, ?, ?, 'representante', ?, 1)
        ");
        $stmt->execute([$username, $cedula, $nombres, $apellidos, $email, $telefono, $password_hash]);
        
        $nuevo_id = $db->lastInsertId();
        registrarLog('crear_usuario_representante', 'usuarios', $nuevo_id, "Nuevo representante: $cedula");
        
        return $nuevo_id;
    } catch (PDOException $e) {
        error_log("Error en crearUsuarioRepresentante: " . $e->getMessage());
        return false;
    }
}
// =========================================================
// FUNCIONES DE CONFIGURACIÓN (agregar al final de functions.php)
// =========================================================

/**
 * Obtiene un valor de configuración desde la base de datos
 */
function getConfig($db, $clave, $default = null) {
    try {
        $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $stmt->execute([$clave]);
        $resultado = $stmt->fetchColumn();
        return $resultado !== false ? $resultado : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Guarda un valor de configuración en la base de datos
 */
function setConfig($db, $clave, $valor) {
    try {
        $stmt = $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        return $stmt->execute([$clave, $valor]);
    } catch (PDOException $e) {
        return false;
    }
}
?>