-- ============================================
-- RESPALDO SIGEB-G (DATOS DE DEMOSTRACIÓN)
-- Esquema actual + datos sintéticos, sin información personal real.
-- Fecha: 19/11/2026 00:58:10
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS `sistema_gestion_escolar`;
CREATE DATABASE IF NOT EXISTS `sistema_gestion_escolar` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sistema_gestion_escolar`;

-- --------------------------------------------
-- Estructura de tabla `configuracion`
-- --------------------------------------------
DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Datos de `configuracion` (4 filas)
INSERT INTO `configuracion` (`id`, `clave`, `valor`, `fecha_actualizacion`) VALUES ('1', 'ultimo_respaldo', '2026-11-19 00:53:09', '2026-11-18 19:53:09');
INSERT INTO `configuracion` (`id`, `clave`, `valor`, `fecha_actualizacion`) VALUES ('2', 'responsable_sistema', 'Departamento de Evaluación y Control de Estudios', '2026-10-11 13:03:50');
INSERT INTO `configuracion` (`id`, `clave`, `valor`, `fecha_actualizacion`) VALUES ('3', 'anio_escolar_activo', '2025-2026', '2026-10-11 13:03:50');
INSERT INTO `configuracion` (`id`, `clave`, `valor`, `fecha_actualizacion`) VALUES ('5', 'lapso_1_cerrado', '0', '2026-11-03 17:03:23');


-- --------------------------------------------
-- Estructura de tabla `estudiantes`
-- --------------------------------------------
DROP TABLE IF EXISTS `estudiantes`;
CREATE TABLE `estudiantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(20) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `genero` enum('M','F') DEFAULT NULL,
  `nacionalidad` varchar(50) DEFAULT 'Venezolana',
  `lugar_nacimiento` varchar(150) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `telefono_contacto` varchar(20) DEFAULT NULL,
  `nombre_representante` varchar(100) DEFAULT NULL,
  `cedula_representante` varchar(20) DEFAULT NULL,
  `telefono_representante` varchar(20) DEFAULT NULL,
  `email_representante` varchar(100) DEFAULT NULL,
  `parentesco` varchar(50) DEFAULT NULL,
  `procedencia` varchar(200) DEFAULT NULL,
  `seccion_id` int(11) DEFAULT NULL,
  `fecha_inscripcion` date DEFAULT NULL,
  `fecha_retiro` date DEFAULT NULL,
  `estado` enum('activo','inactivo','graduado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_estudiante_usuario` (`usuario_id`),
  CONSTRAINT `fk_estudiante_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- Datos de `estudiantes` (6 filas)
INSERT INTO `estudiantes` (`id`, `cedula`, `nombres`, `apellidos`, `email`, `usuario_id`, `fecha_nacimiento`, `genero`, `nacionalidad`, `lugar_nacimiento`, `direccion`, `telefono_contacto`, `nombre_representante`, `cedula_representante`, `telefono_representante`, `email_representante`, `parentesco`, `procedencia`, `seccion_id`, `fecha_inscripcion`, `fecha_retiro`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('1', 'V-80000001', 'Estudiante', 'Demo 1', 'estudiante1@example.local', NULL, '2010-01-01', 'F', 'Venezolana', 'Los Teques', 'Dirección de prueba', '0412-0000001', 'Representante Demo', 'V-70000001', '0414-0000001', 'representante1@example.local', NULL, 'Escuela de prueba', '1', '2024-01-15', NULL, 'activo', '2026-07-25 12:22:44', '2026-11-18 19:53:56');
INSERT INTO `estudiantes` (`id`, `cedula`, `nombres`, `apellidos`, `email`, `usuario_id`, `fecha_nacimiento`, `genero`, `nacionalidad`, `lugar_nacimiento`, `direccion`, `telefono_contacto`, `nombre_representante`, `cedula_representante`, `telefono_representante`, `email_representante`, `parentesco`, `procedencia`, `seccion_id`, `fecha_inscripcion`, `fecha_retiro`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('2', 'V-80000002', 'Estudiante', 'Demo 2', 'estudiante2@example.local', '5', '2010-01-01', 'M', 'Venezolana', 'Los Teques', 'Dirección de prueba', '0412-0000002', 'Representante Demo', 'V-70000002', '0414-0000002', 'representante2@example.local', '', 'Escuela de prueba', '1', '2024-01-15', NULL, 'activo', '2026-10-13 20:05:42', '2026-11-02 14:49:17');
INSERT INTO `estudiantes` (`id`, `cedula`, `nombres`, `apellidos`, `email`, `usuario_id`, `fecha_nacimiento`, `genero`, `nacionalidad`, `lugar_nacimiento`, `direccion`, `telefono_contacto`, `nombre_representante`, `cedula_representante`, `telefono_representante`, `email_representante`, `parentesco`, `procedencia`, `seccion_id`, `fecha_inscripcion`, `fecha_retiro`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('3', 'V-80000003', 'Estudiante', 'Demo 3', 'estudiante3@example.local', NULL, '2010-01-01', 'F', 'Venezolana', 'Los Teques', 'Dirección de prueba', '0412-0000003', 'Representante Demo', 'V-70000003', '0414-0000003', 'representante3@example.local', NULL, 'Escuela de prueba', '1', '2024-01-15', NULL, 'activo', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `estudiantes` (`id`, `cedula`, `nombres`, `apellidos`, `email`, `usuario_id`, `fecha_nacimiento`, `genero`, `nacionalidad`, `lugar_nacimiento`, `direccion`, `telefono_contacto`, `nombre_representante`, `cedula_representante`, `telefono_representante`, `email_representante`, `parentesco`, `procedencia`, `seccion_id`, `fecha_inscripcion`, `fecha_retiro`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('4', 'V-80000004', 'Estudiante', 'Demo 4', 'estudiante4@example.local', NULL, '2010-01-01', 'M', 'Venezolana', 'Los Teques', 'Dirección de prueba', '0412-0000004', 'Representante Demo', 'V-70000004', '0414-0000004', 'representante4@example.local', NULL, 'Escuela de prueba', '2', '2024-01-15', NULL, 'activo', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `estudiantes` (`id`, `cedula`, `nombres`, `apellidos`, `email`, `usuario_id`, `fecha_nacimiento`, `genero`, `nacionalidad`, `lugar_nacimiento`, `direccion`, `telefono_contacto`, `nombre_representante`, `cedula_representante`, `telefono_representante`, `email_representante`, `parentesco`, `procedencia`, `seccion_id`, `fecha_inscripcion`, `fecha_retiro`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('5', 'V-80000005', 'Estudiante', 'Demo 5', 'estudiante5@example.local', '8', '2010-01-01', 'F', 'Venezolana', 'Los Teques', 'Dirección de prueba', '0412-0000005', 'Representante Demo', 'V-70000005', '0414-0000005', 'representante5@example.local', NULL, 'Escuela de prueba', '2', '2024-01-15', NULL, 'activo', '2026-07-25 12:22:44', '2026-10-15 15:11:20');
INSERT INTO `estudiantes` (`id`, `cedula`, `nombres`, `apellidos`, `email`, `usuario_id`, `fecha_nacimiento`, `genero`, `nacionalidad`, `lugar_nacimiento`, `direccion`, `telefono_contacto`, `nombre_representante`, `cedula_representante`, `telefono_representante`, `email_representante`, `parentesco`, `procedencia`, `seccion_id`, `fecha_inscripcion`, `fecha_retiro`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('7', 'V-80000007', 'Estudiante', 'Demo 7', 'estudiante7@example.local', '16', '2010-01-01', 'M', 'Venezolana', 'Los Teques', 'Dirección de prueba', '0412-0000007', 'Representante Demo', 'V-70000007', '0414-0000007', 'representante7@example.local', 'Madre', 'Escuela de prueba', '1', '2026-11-02', NULL, 'activo', '2026-11-02 12:29:35', '2026-11-02 12:29:35');


-- --------------------------------------------
-- Estructura de tabla `grados`
-- --------------------------------------------
DROP TABLE IF EXISTS `grados`;
CREATE TABLE `grados` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `nivel` varchar(20) DEFAULT NULL,
  `modalidad` enum('Media General','Media Técnica') DEFAULT 'Media General',
  `orden` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- Datos de `grados` (5 filas)
INSERT INTO `grados` (`id`, `nombre`, `nivel`, `modalidad`, `orden`, `activo`, `fecha_creacion`) VALUES ('1', '1er Año', 'Media General', 'Media General', '1', '1', '2026-07-25 12:22:44');
INSERT INTO `grados` (`id`, `nombre`, `nivel`, `modalidad`, `orden`, `activo`, `fecha_creacion`) VALUES ('2', '2do Año', 'Media General', 'Media General', '2', '1', '2026-07-25 12:22:44');
INSERT INTO `grados` (`id`, `nombre`, `nivel`, `modalidad`, `orden`, `activo`, `fecha_creacion`) VALUES ('3', '3er Año', 'Media General', 'Media General', '3', '1', '2026-07-25 12:22:44');
INSERT INTO `grados` (`id`, `nombre`, `nivel`, `modalidad`, `orden`, `activo`, `fecha_creacion`) VALUES ('4', '4to Año', 'Media General', 'Media General', '4', '1', '2026-07-25 12:22:44');
INSERT INTO `grados` (`id`, `nombre`, `nivel`, `modalidad`, `orden`, `activo`, `fecha_creacion`) VALUES ('5', '5to Año', 'Media General', 'Media General', '5', '1', '2026-07-25 12:22:44');


-- --------------------------------------------
-- Estructura de tabla `login_attempts`
-- --------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `intentos` int(11) DEFAULT 1,
  `ultimo_intento` datetime DEFAULT NULL,
  `bloqueado_hasta` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------
-- Estructura de tabla `logs`
-- --------------------------------------------
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `tabla` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------
-- Estructura de tabla `materias`
-- --------------------------------------------
DROP TABLE IF EXISTS `materias`;
CREATE TABLE `materias` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `area` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `horas_semanales` int(11) DEFAULT 1,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- Datos de `materias` (9 filas)
INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `horas_semanales`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('0', 'LEN-2026-001', 'psicologia', 'Lengua y Literatura', '', '3', '1', '2026-11-02 14:29:15', '2026-11-02 14:29:15');
INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `horas_semanales`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('1', 'MAT-001', 'Matemáticas', 'Matemáticas', 'Álgebra, geometría y cálculo básico para educación media', '1', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `horas_semanales`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('2', 'LEN-001', 'Lengua y Literatura', 'Lengua', 'Comprensión lectora, redacción y literatura venezolana', '1', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `horas_semanales`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('3', 'CIE-001', 'Ciencias Naturales', 'Ciencias', 'Biología, química y física aplicada al entorno', '1', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `horas_semanales`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('4', 'SOC-001', 'Ciencias Sociales', 'Sociales', 'Historia universal, de Venezuela y geografía', '1', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `horas_semanales`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('5', 'ING-001', 'Inglés', 'Idiomas', 'Inglés básico e intermedio para comunicación', '1', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `horas_semanales`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('6', 'EDF-001', 'Educación Física', 'Educación Física', 'Desarrollo físico, deportes y salud integral', '1', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `horas_semanales`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('7', 'ART-001', 'Arte y Cultura', 'Arte', 'Expresión artística, música y cultura venezolana', '1', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `horas_semanales`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('8', 'TEC-001', 'Tecnología', 'Tecnología', 'Informática básica y tecnología educativa', '1', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');


-- --------------------------------------------
-- Estructura de tabla `notas`
-- --------------------------------------------
DROP TABLE IF EXISTS `notas`;
CREATE TABLE `notas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estudiante_id` int(11) NOT NULL,
  `seccion_materia_id` int(11) NOT NULL,
  `lapso` tinyint(1) NOT NULL COMMENT '1, 2, 3',
  `nota` decimal(4,2) DEFAULT NULL,
  `es_recuperacion` tinyint(1) DEFAULT 0,
  `anio_escolar` year(4) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_nota` (`estudiante_id`,`seccion_materia_id`,`lapso`,`es_recuperacion`,`anio_escolar`),
  KEY `idx_estudiante` (`estudiante_id`),
  KEY `idx_seccion_materia` (`seccion_materia_id`),
  CONSTRAINT `fk_notas_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notas_seccion_materia` FOREIGN KEY (`seccion_materia_id`) REFERENCES `seccion_materias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Datos de `notas` (4 filas)
INSERT INTO `notas` (`id`, `estudiante_id`, `seccion_materia_id`, `lapso`, `nota`, `es_recuperacion`, `anio_escolar`, `observaciones`, `fecha_registro`, `fecha_actualizacion`) VALUES ('7', '5', '8', '1', '16.00', '0', '2025', NULL, '2026-10-15 15:13:00', '2026-10-15 15:13:00');
INSERT INTO `notas` (`id`, `estudiante_id`, `seccion_materia_id`, `lapso`, `nota`, `es_recuperacion`, `anio_escolar`, `observaciones`, `fecha_registro`, `fecha_actualizacion`) VALUES ('12', '2', '2', '1', '15.00', '0', '2025', NULL, '2026-10-15 17:39:43', '2026-10-15 17:39:43');
INSERT INTO `notas` (`id`, `estudiante_id`, `seccion_materia_id`, `lapso`, `nota`, `es_recuperacion`, `anio_escolar`, `observaciones`, `fecha_registro`, `fecha_actualizacion`) VALUES ('18', '2', '9', '1', '16.00', '0', '2025', NULL, '2026-11-01 17:41:51', '2026-11-01 17:41:51');
INSERT INTO `notas` (`id`, `estudiante_id`, `seccion_materia_id`, `lapso`, `nota`, `es_recuperacion`, `anio_escolar`, `observaciones`, `fecha_registro`, `fecha_actualizacion`) VALUES ('20', '3', '9', '1', '12.00', '0', '2025', NULL, '2026-11-02 14:31:15', '2026-11-02 14:31:15');


-- --------------------------------------------
-- Estructura de tabla `representantes_estudiantes`
-- --------------------------------------------
DROP TABLE IF EXISTS `representantes_estudiantes`;
CREATE TABLE `representantes_estudiantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `representante_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `parentesco` varchar(50) DEFAULT NULL,
  `es_principal` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_relacion` (`representante_id`,`estudiante_id`),
  KEY `idx_representante` (`representante_id`),
  KEY `idx_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_rep_est_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rep_est_representante` FOREIGN KEY (`representante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Datos de `representantes_estudiantes` (3 filas)
INSERT INTO `representantes_estudiantes` (`id`, `representante_id`, `estudiante_id`, `parentesco`, `es_principal`, `fecha_creacion`) VALUES ('7', '17', '7', 'Madre', '1', '2026-11-02 12:29:35');
INSERT INTO `representantes_estudiantes` (`id`, `representante_id`, `estudiante_id`, `parentesco`, `es_principal`, `fecha_creacion`) VALUES ('8', '9', '2', 'Representante', '1', '2026-11-02 14:41:14');
INSERT INTO `representantes_estudiantes` (`id`, `representante_id`, `estudiante_id`, `parentesco`, `es_principal`, `fecha_creacion`) VALUES ('9', '17', '2', 'Representante', '1', '2026-11-02 14:49:17');


-- --------------------------------------------
-- Estructura de tabla `seccion_materias`
-- --------------------------------------------
DROP TABLE IF EXISTS `seccion_materias`;
CREATE TABLE `seccion_materias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seccion_id` int(11) DEFAULT NULL,
  `materia_id` int(11) DEFAULT NULL,
  `docente_id` int(11) DEFAULT NULL,
  `horario` varchar(50) DEFAULT NULL,
  `lapso` enum('1','2','3') DEFAULT '1',
  `anio_escolar` year(4) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- Datos de `seccion_materias` (7 filas)
INSERT INTO `seccion_materias` (`id`, `seccion_id`, `materia_id`, `docente_id`, `horario`, `lapso`, `anio_escolar`, `fecha_creacion`) VALUES ('2', '1', '2', '3', 'Martes 7:00-8:30', '1', '2025', '2026-07-25 12:22:44');
INSERT INTO `seccion_materias` (`id`, `seccion_id`, `materia_id`, `docente_id`, `horario`, `lapso`, `anio_escolar`, `fecha_creacion`) VALUES ('4', '1', '4', '3', 'Jueves 7:00-8:30', '1', '2025', '2026-07-25 12:22:44');
INSERT INTO `seccion_materias` (`id`, `seccion_id`, `materia_id`, `docente_id`, `horario`, `lapso`, `anio_escolar`, `fecha_creacion`) VALUES ('6', '2', '1', '3', 'Lunes 13:00-14:30', '1', '2025', '2026-07-25 12:22:44');
INSERT INTO `seccion_materias` (`id`, `seccion_id`, `materia_id`, `docente_id`, `horario`, `lapso`, `anio_escolar`, `fecha_creacion`) VALUES ('7', '2', '2', '2', 'Martes 13:00-14:30', '1', '2025', '2026-07-25 12:22:44');
INSERT INTO `seccion_materias` (`id`, `seccion_id`, `materia_id`, `docente_id`, `horario`, `lapso`, `anio_escolar`, `fecha_creacion`) VALUES ('8', '2', '3', '3', 'Miércoles 13:00-14:30', '1', '2025', '2026-07-25 12:22:44');
INSERT INTO `seccion_materias` (`id`, `seccion_id`, `materia_id`, `docente_id`, `horario`, `lapso`, `anio_escolar`, `fecha_creacion`) VALUES ('9', '1', '7', '14', NULL, '1', '2025', '2026-11-01 17:38:38');
INSERT INTO `seccion_materias` (`id`, `seccion_id`, `materia_id`, `docente_id`, `horario`, `lapso`, `anio_escolar`, `fecha_creacion`) VALUES ('10', '1', '0', '12', NULL, '1', '2025', '2026-11-02 14:29:47');


-- --------------------------------------------
-- Estructura de tabla `secciones`
-- --------------------------------------------
DROP TABLE IF EXISTS `secciones`;
CREATE TABLE `secciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grado_id` int(11) DEFAULT NULL,
  `letra` varchar(5) NOT NULL,
  `anio_escolar` varchar(9) DEFAULT '2025-2026',
  `turno` enum('mañana','tarde') DEFAULT 'mañana',
  `capacidad` int(11) DEFAULT 30,
  `docente_guia_id` int(11) DEFAULT NULL,
  `aula` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- Datos de `secciones` (6 filas)
INSERT INTO `secciones` (`id`, `grado_id`, `letra`, `anio_escolar`, `turno`, `capacidad`, `docente_guia_id`, `aula`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('1', '1', 'A', '2025-2026', 'mañana', '30', '2', 'Aula 101', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `secciones` (`id`, `grado_id`, `letra`, `anio_escolar`, `turno`, `capacidad`, `docente_guia_id`, `aula`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('2', '1', 'B', '2025-2026', 'tarde', '25', '3', 'Aula 102', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `secciones` (`id`, `grado_id`, `letra`, `anio_escolar`, `turno`, `capacidad`, `docente_guia_id`, `aula`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('3', '2', 'A', '2025-2026', 'mañana', '28', '2', 'Aula 201', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `secciones` (`id`, `grado_id`, `letra`, `anio_escolar`, `turno`, `capacidad`, `docente_guia_id`, `aula`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('4', '2', 'B', '2025-2026', 'tarde', '26', '3', 'Aula 202', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `secciones` (`id`, `grado_id`, `letra`, `anio_escolar`, `turno`, `capacidad`, `docente_guia_id`, `aula`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('5', '3', 'A', '2025-2026', 'mañana', '30', '2', 'Aula 301', '1', '2026-07-25 12:22:44', '2026-07-25 12:22:44');
INSERT INTO `secciones` (`id`, `grado_id`, `letra`, `anio_escolar`, `turno`, `capacidad`, `docente_guia_id`, `aula`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES ('6', '3', 'B', '2025-2026', 'tarde', '30', '0', 'Aula 302', '1', '2026-10-10 15:23:17', '2026-10-10 15:23:17');


-- --------------------------------------------
-- Estructura de tabla `usuarios`
-- --------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `cedula` varchar(20) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `rol` enum('admin','docente','secretario','estudiante','representante') DEFAULT 'docente',
  `password` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `token_recuperacion` varchar(255) DEFAULT NULL,
  `token_expiracion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- Datos de `usuarios` (13 filas)
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('1', 'admin', 'V-90000001', 'Usuario', 'Demo 1', 'usuario1@example.local', '0412-0000001', 'admin', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-07-25 12:22:44', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('2', 'usuario2', 'V-90000002', 'Usuario', 'Demo 2', 'usuario2@example.local', '0412-0000002', 'admin', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-07-25 12:22:44', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('3', 'usuario3', 'V-90000003', 'Usuario', 'Demo 3', 'usuario3@example.local', '0412-0000003', 'docente', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-07-25 12:22:44', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('4', 'usuario4', 'V-90000004', 'Usuario', 'Demo 4', 'usuario4@example.local', '0412-0000004', 'docente', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-07-25 12:22:44', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('5', 'usuario5', 'V-90000005', 'Usuario', 'Demo 5', 'usuario5@example.local', '0412-0000005', 'estudiante', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-10-13 18:00:21', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('8', 'usuario8', 'V-90000008', 'Usuario', 'Demo 8', 'usuario8@example.local', '0412-0000008', 'estudiante', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-10-15 15:11:20', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('9', 'usuario9', 'V-90000009', 'Usuario', 'Demo 9', 'usuario9@example.local', '0412-0000009', 'representante', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '0', '2026-10-18 18:39:08', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('12', 'usuario12', 'V-90000012', 'Usuario', 'Demo 12', 'usuario12@example.local', '0412-0000012', 'docente', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-10-20 19:13:41', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('13', 'usuario13', 'V-90000013', 'Usuario', 'Demo 13', 'usuario13@example.local', '0412-0000013', 'docente', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '0', '2026-11-01 12:41:25', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('14', 'usuario14', 'V-90000014', 'Usuario', 'Demo 14', 'usuario14@example.local', '0412-0000014', 'docente', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-11-01 17:36:41', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('15', 'usuario15', 'V-90000015', 'Usuario', 'Demo 15', 'usuario15@example.local', '0412-0000015', 'estudiante', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-11-01 17:49:46', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('16', 'usuario16', 'V-90000016', 'Usuario', 'Demo 16', 'usuario16@example.local', '0412-0000016', 'estudiante', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-11-02 12:29:35', NULL, NULL, NULL);
INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `fecha_creacion`, `ultimo_acceso`, `token_recuperacion`, `token_expiracion`) VALUES ('17', 'usuario17', 'V-90000017', 'Usuario', 'Demo 17', 'usuario17@example.local', '0412-0000017', 'representante', '$2y$10$BNVyghURqjwJ8BBBAHOyFuNYJHC0.GbnL7aN.qD0hkqUibdsHz9ma', '1', '2026-11-02 12:29:35', NULL, NULL, NULL);


SET FOREIGN_KEY_CHECKS = 1;
-- ============================================
-- FIN DEL RESPALDO
-- ============================================
