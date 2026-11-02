<?php
// functions.php - Funciones de lógica de negocio (SIGEB-G)
require_once 'config.php';

// =========================================================
// FUNCIONES DE BOLETA
// =========================================================
function getBoletaData(int $estudianteId, int $lapso, int $anio_escolar = null) {
    $db = getDBConnection();
    if (!$db) return ['error' => 'No se pudo conectar a la base de datos.'];
    if ($anio_escolar === null) $anio_escolar = date('Y');

    try {
        $stmt = $db->prepare("
            SELECT e.id, e.cedula, e.nombres, e.apellidos, e.fecha_nacimiento,
                   e.email, e.nacionalidad, e.lugar_nacimiento,
                   e.nombre_representante, e.email_representante, e.parentesco,
                   g.nombre AS grado_nombre, g.nivel,
                   s.letra AS seccion_letra, s.turno, s.anio_escolar,
                   CONCAT(u.nombres, ' ', u.apellidos) AS docente_guia_nombre
            FROM estudiantes e
            JOIN secciones s ON e.seccion_id = s.id
            JOIN grados g ON s.grado_id = g.id
            LEFT JOIN usuarios u ON s.docente_guia_id = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([$estudianteId]);
        $infoEstudiante = $stmt->fetch();
        if (!$infoEstudiante) return ['error' => 'Estudiante no encontrado.'];

        $stmt = $db->prepare("
            SELECT m.id AS materia_id, m.nombre AS materia_nombre, m.area,
                   n.lapso, n.nota, n.es_recuperacion, n.observaciones,
                   CONCAT(doc.nombres, ' ', doc.apellidos) AS docente_nombre
            FROM seccion_materias sm
            JOIN materias m ON sm.materia_id = m.id
            LEFT JOIN notas n ON n.seccion_materia_id = sm.id 
                              AND n.estudiante_id = ? 
                              AND n.anio_escolar = ?
            LEFT JOIN usuarios doc ON sm.docente_id = doc.id
            WHERE sm.seccion_id = (SELECT seccion_id FROM estudiantes WHERE id = ?)
              AND sm.anio_escolar = ?
            ORDER BY m.area, m.nombre, n.lapso
        ");
        $stmt->execute([$estudianteId, $anio_escolar, $estudianteId, $anio_escolar]);
        $filas = $stmt->fetchAll();

        $materias_agrupadas = [];
        foreach ($filas as $fila) {
            $mid = $fila['materia_id'];
            if (!isset($materias_agrupadas[$mid])) {
                $materias_agrupadas[$mid] = [
                    'materia_nombre' => $fila['materia_nombre'],
                    'area' => $fila['area'],
                    'docente_nombre' => $fila['docente_nombre'],
                    'lapso1' => null, 
                    'lapso2' => null, 
                    'lapso3' => null,
                    'recuperacion' => null,
                    'observaciones' => $fila['observaciones']
                ];
            }
            if ($fila['es_recuperacion'] == 1) {
                $materias_agrupadas[$mid]['recuperacion'] = $fila['nota'];
            } else if ($fila['lapso'] !== null) {
                $materias_agrupadas[$mid]['lapso' . $fila['lapso']] = $fila['nota'];
            }
        }

        $notas = [];
        foreach ($materias_agrupadas as $mat) {
            $lapsos = array_filter([$mat['lapso1'], $mat['lapso2'], $mat['lapso3']], function($n) { return $n !== null; });
            $definitiva = count($lapsos) > 0 ? round(array_sum($lapsos) / count($lapsos), 2) : 0;
            $recuperacion = $mat['recuperacion'];
            $nota_final = $definitiva;
            if ($recuperacion !== null && $recuperacion > 10 && $recuperacion > $definitiva) {
                $nota_final = $recuperacion;
            }
            $notas[] = [
                'materia_nombre' => $mat['materia_nombre'],
                'area' => $mat['area'],
                'evaluacion1' => $mat['lapso1'],
                'evaluacion2' => $mat['lapso2'],
                'evaluacion3' => $mat['lapso3'],
                'definitiva' => $definitiva,
                'recuperacion' => $recuperacion,
                'nota_final' => $nota_final,
                'observaciones' => $mat['observaciones'],
                'docente_nombre' => $mat['docente_nombre']
            ];
        }

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
        return ['error' => 'Error al consultar datos: ' . $e->getMessage()];
    }
}

function calcularDefinitiva($e1, $e2, $e3) {
    $notas = [floatval($e1), floatval($e2), floatval($e3)];
    return round(array_sum($notas) / 3, 2);
}

function obtenerNotaFinal($definitiva, $recuperacion) {
    $d = floatval($definitiva);
    $r = floatval($recuperacion);
    if ($r > $d && $r > 10) return $r;
    return $d;
}

function fechaEspañol($fecha) {
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $t = strtotime($fecha);
    return date('d', $t) . " de " . $meses[date('n', $t)-1] . " de " . date('Y', $t);
}

// =========================================================
// FUNCIONES DE REPRESENTANTES Y ESTUDIANTES
// =========================================================
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
        return [];
    }
}

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
        return null;
    }
}

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
        return false;
    }
}

function crearUsuarioEstudiante($estudiante_id, $custom_password = null) {
    $db = getDBConnection();
    if (!$db) return false;
    try {
        $stmt = $db->prepare("SELECT id, cedula, nombres, apellidos, email, telefono_contacto, usuario_id FROM estudiantes WHERE id = ?");
        $stmt->execute([$estudiante_id]);
        $est = $stmt->fetch();
        if (!$est) return false;
        if ($est['usuario_id']) return $est['usuario_id'];

        $check = $db->prepare("SELECT id FROM usuarios WHERE cedula = ?");
        $check->execute([$est['cedula']]);
        if ($check->fetch()) return false;

        $username = $est['cedula'];
        $password_a_usar = $custom_password ?: 'Guarenas2026*'; 
        $password_hash = password_hash($password_a_usar, PASSWORD_DEFAULT);
        
        $email = $est['email'] ?: strtolower(str_replace('-', '', $est['cedula'])) . '@uenguarenas.edu.ve';

        $stmt = $db->prepare("INSERT INTO usuarios (username, cedula, nombres, apellidos, email, telefono, rol, password, activo) VALUES (?, ?, ?, ?, ?, ?, 'estudiante', ?, 1)");
        $stmt->execute([$username, $est['cedula'], $est['nombres'], $est['apellidos'], $email, $est['telefono_contacto'], $password_hash]);
        $nuevo_usuario_id = $db->lastInsertId();

        $stmt = $db->prepare("UPDATE estudiantes SET usuario_id = ? WHERE id = ?");
        $stmt->execute([$nuevo_usuario_id, $estudiante_id]);
        registrarLog('crear_usuario_estudiante', 'usuarios', $nuevo_usuario_id, "Usuario para estudiante {$est['cedula']}");
        return $nuevo_usuario_id;
    } catch (PDOException $e) {
        return false;
    }
}

function crearYVincularRepresentante($estudiante_id, $cedula_rep, $nombre_rep = '', $apellido_rep = '', $email_rep = null, $telefono_rep = null, $parentesco = 'Representante', $custom_password = null) {
    $db = getDBConnection();
    if (!$db) return false;
    try {
        $cedula_rep = formatearCedulaParaBD($cedula_rep);
        if (empty($cedula_rep)) return false;

        $nombre_rep = trim($nombre_rep);
        if (empty($apellido_rep) && strpos($nombre_rep, ' ') !== false) {
            $partes = explode(' ', $nombre_rep, 2);
            $nombre_rep = $partes[0];
            $apellido_rep = $partes[1] ?? '';
        }

        $stmt = $db->prepare("SELECT id FROM usuarios WHERE cedula = ? AND rol = 'representante'");
        $stmt->execute([$cedula_rep]);
        $rep = $stmt->fetch();

        if ($rep) {
            $representante_id = $rep['id'];
        } else {
            $password_a_usar = $custom_password ?: 'Representante2026*';
            $hash = password_hash($password_a_usar, PASSWORD_DEFAULT);
            $email = $email_rep ?: strtolower(str_replace('-', '', $cedula_rep)) . '@uenguarenas.edu.ve';
            
            $stmt = $db->prepare("INSERT INTO usuarios (username, cedula, nombres, apellidos, email, telefono, rol, password, activo) VALUES (?, ?, ?, ?, ?, ?, 'representante', ?, 1)");
            $stmt->execute([$cedula_rep, $cedula_rep, $nombre_rep ?: 'Representante', $apellido_rep, $email, $telefono_rep, $hash]);
            $representante_id = $db->lastInsertId();
            registrarLog('crear_usuario_representante', 'usuarios', $representante_id, "Nuevo representante: $cedula_rep");
        }

        vincularRepresentanteEstudiante($representante_id, $estudiante_id, $parentesco, 1);
        return $representante_id;
    } catch (PDOException $e) {
        return false;
    }
}

// =========================================================
// CONFIGURACIÓN
// =========================================================
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

function setConfig($db, $clave, $valor) {
    try {
        $stmt = $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        return $stmt->execute([$clave, $valor]);
    } catch (PDOException $e) {
        return false;
    }
}

function validarPasswordSegura($password) {
    if (strlen($password) < 8) return "La contraseña debe tener al menos 8 caracteres.";
    if (!preg_match('/[A-Z]/', $password)) return "La contraseña debe incluir al menos una letra MAYÚSCULA.";
    if (!preg_match('/[a-z]/', $password)) return "La contraseña debe incluir al menos una letra minúscula.";
    if (!preg_match('/[0-9]/', $password)) return "La contraseña debe incluir al menos un número.";
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return "La contraseña debe incluir al menos un símbolo (ej. ! @ # $ %).";
    return true;
}
?>