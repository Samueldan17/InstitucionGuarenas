-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-07-2026
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistema_gestion_escolar`
--
DROP DATABASE IF EXISTS `sistema_gestion_escolar`;
CREATE DATABASE IF NOT EXISTS `sistema_gestion_escolar` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sistema_gestion_escolar`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id` int(11) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `genero` enum('M','F') DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `telefono_contacto` varchar(20) DEFAULT NULL,
  `nombre_representante` varchar(100) DEFAULT NULL,
  `telefono_representante` varchar(20) DEFAULT NULL,
  `seccion_id` int(11) DEFAULT NULL,
  `fecha_inscripcion` date DEFAULT NULL,
  `estado` enum('activo','inactivo','graduado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id`, `cedula`, `nombres`, `apellidos`, `fecha_nacimiento`, `genero`, `direccion`, `telefono_contacto`, `nombre_representante`, `telefono_representante`, `seccion_id`, `fecha_inscripcion`, `estado`) VALUES
(1, 'V-28987654', 'María Gabriela', 'Pérez López', '2010-05-15', 'F', 'Urbanización El Rincón, Calle 5, Casa #12, Los Teques', '0412-1234567', 'Carlos Pérez', '0414-1112233', 1, '2024-01-15', 'activo'),
(2, 'V-28765432', 'Carlos José', 'González Rodríguez', '2009-08-22', 'M', 'Sector La Linea, Av. Principal, Edificio Los Pinos, Los Teques', '0416-7654321', 'Marta Rodríguez', '0424-4445566', 1, '2024-01-15', 'activo'),
(3, 'V-28654321', 'Ana Isabel', 'Martínez Blanco', '2010-02-10', 'F', 'Urbanización San Antonio, Calle Los Jardines, Residencia Martínez', '0412-8889999', 'Roberto Martínez', '0416-7778888', 1, '2024-01-15', 'activo'),
(4, 'V-28543219', 'José Luis', 'Ramírez Silva', '2009-11-30', 'M', 'Sector Altamira, Calle Principal, Quinta Ramírez', '0426-5554444', 'Luisa Silva', '0412-3332222', 2, '2024-01-15', 'activo'),
(5, 'V-28432198', 'Laura Valentina', 'Hernández Castro', '2010-03-25', 'F', 'Urbanización La Paz, Calle Los Almendros, Casa Hernandez', '0414-6667777', 'Pedro Hernández', '0424-9990000', 2, '2024-01-15', 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grados`
--

CREATE TABLE `grados` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `nivel` varchar(20) DEFAULT NULL,
  `orden` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `grados`
--

INSERT INTO `grados` (`id`, `nombre`, `nivel`, `orden`, `activo`) VALUES
(1, '1er Año', 'Media General', 1, 1),
(2, '2do Año', 'Media General', 2, 1),
(3, '3er Año', 'Media General', 3, 1),
(4, '4to Año', 'Media General', 4, 1),
(5, '5to Año', 'Media General', 5, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `area` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `creditos` int(11) DEFAULT 1,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`id`, `codigo`, `nombre`, `area`, `descripcion`, `creditos`, `activo`) VALUES
(1, 'MAT-001', 'Matemáticas', 'Matemáticas', 'Álgebra, geometría y cálculo básico para educación media', 1, 1),
(2, 'LEN-001', 'Lengua y Literatura', 'Lengua', 'Comprensión lectora, redacción y literatura venezolana', 1, 1),
(3, 'CIE-001', 'Ciencias Naturales', 'Ciencias', 'Biología, química y física aplicada al entorno', 1, 1),
(4, 'SOC-001', 'Ciencias Sociales', 'Sociales', 'Historia universal, de Venezuela y geografía', 1, 1),
(5, 'ING-001', 'Inglés', 'Idiomas', 'Inglés básico e intermedio para comunicación', 1, 1),
(6, 'EDF-001', 'Educación Física', 'Educación Física', 'Desarrollo físico, deportes y salud integral', 1, 1),
(7, 'ART-001', 'Arte y Cultura', 'Arte', 'Expresión artística, música y cultura venezolana', 1, 1),
(8, 'TEC-001', 'Tecnología', 'Tecnología', 'Informática básica y tecnología educativa', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notas`
--   *** NUEVO CAMPO: recuperacion ***
--

CREATE TABLE `notas` (
  `id` int(11) NOT NULL,
  `estudiante_id` int(11) DEFAULT NULL,
  `seccion_materia_id` int(11) DEFAULT NULL,
  `evaluacion1` decimal(4,2) DEFAULT 0.00,
  `evaluacion2` decimal(4,2) DEFAULT 0.00,
  `evaluacion3` decimal(4,2) DEFAULT 0.00,
  `proyecto` decimal(4,2) DEFAULT 0.00,
  `participacion` decimal(4,2) DEFAULT 0.00,
  `promedio` decimal(4,2) DEFAULT 0.00,
  `recuperacion` decimal(4,2) DEFAULT NULL,   -- <-- CAMPO AGREGADO
  `lapso` enum('1','2','3') DEFAULT '1',
  `anio_escolar` year(4) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `notas` (ejemplo con recuperacion)
--

INSERT INTO `notas` (`id`, `estudiante_id`, `seccion_materia_id`, `evaluacion1`, `evaluacion2`, `evaluacion3`, `proyecto`, `participacion`, `promedio`, `recuperacion`, `lapso`, `anio_escolar`, `observaciones`) VALUES
(1, 1, 1, 18.00, 16.00, 17.00, 19.00, 18.00, 17.00, NULL, '1', 2024, 'Excelente rendimiento en ejercicios prácticos'),
(2, 1, 2, 16.00, 15.00, 17.00, 18.00, 16.00, 16.00, NULL, '1', 2024, 'Buena comprensión lectora'),
(3, 1, 3, 17.00, 18.00, 16.00, 19.00, 17.00, 17.00, NULL, '1', 2024, 'Destacado en experimentos'),
(4, 2, 1, 15.00, 14.00, 16.00, 17.00, 15.00, 15.00, 18.00, '1', 2024, 'Requiere práctica adicional - Recuperación aplicada'),
(5, 2, 2, 17.00, 16.00, 18.00, 16.00, 17.00, 17.00, NULL, '1', 2024, 'Excelente en expresión oral'),
(6, 2, 3, 16.00, 15.00, 17.00, 18.00, 16.00, 16.00, NULL, '1', 2024, 'Buen desempeño general'),
(7, 3, 1, 19.00, 18.00, 20.00, 19.00, 18.00, 19.00, NULL, '1', 2024, 'Excelente, muy destacado'),
(8, 3, 2, 18.00, 17.00, 19.00, 18.00, 17.00, 18.00, NULL, '1', 2024, 'Creativo en redacciones'),
(9, 3, 3, 17.00, 19.00, 18.00, 20.00, 18.00, 18.00, NULL, '1', 2024, 'Muy bueno en laboratorio');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secciones`
--

CREATE TABLE `secciones` (
  `id` int(11) NOT NULL,
  `grado_id` int(11) DEFAULT NULL,
  `letra` varchar(5) NOT NULL,
  `turno` enum('mañana','tarde') DEFAULT 'mañana',
  `capacidad` int(11) DEFAULT 30,
  `docente_guia_id` int(11) DEFAULT NULL,
  `aula` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `secciones`
--

INSERT INTO `secciones` (`id`, `grado_id`, `letra`, `turno`, `capacidad`, `docente_guia_id`, `aula`, `activo`) VALUES
(1, 1, 'A', 'mañana', 30, 2, 'Aula 101', 1),
(2, 1, 'B', 'tarde', 25, 3, 'Aula 102', 1),
(3, 2, 'A', 'mañana', 28, 2, 'Aula 201', 1),
(4, 2, 'B', 'tarde', 26, 3, 'Aula 202', 1),
(5, 3, 'A', 'mañana', 30, 2, 'Aula 301', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seccion_materias`
--

CREATE TABLE `seccion_materias` (
  `id` int(11) NOT NULL,
  `seccion_id` int(11) DEFAULT NULL,
  `materia_id` int(11) DEFAULT NULL,
  `docente_id` int(11) DEFAULT NULL,
  `horario` varchar(50) DEFAULT NULL,
  `lapso` enum('1','2','3') DEFAULT '1',
  `anio_escolar` year(4) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `seccion_materias`
--

INSERT INTO `seccion_materias` (`id`, `seccion_id`, `materia_id`, `docente_id`, `horario`, `lapso`, `anio_escolar`) VALUES
(1, 1, 1, 2, 'Lunes 7:00-8:30', '1', 2024),
(2, 1, 2, 3, 'Martes 7:00-8:30', '1', 2024),
(3, 1, 3, 2, 'Miércoles 7:00-8:30', '1', 2024),
(4, 1, 4, 3, 'Jueves 7:00-8:30', '1', 2024),
(5, 1, 5, 2, 'Viernes 7:00-8:30', '1', 2024),
(6, 2, 1, 3, 'Lunes 13:00-14:30', '1', 2024),
(7, 2, 2, 2, 'Martes 13:00-14:30', '1', 2024),
(8, 2, 3, 3, 'Miércoles 13:00-14:30', '1', 2024);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `cedula` varchar(20) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `rol` enum('admin','docente','secretario') DEFAULT 'docente',
  `password` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `token_recuperacion` varchar(255) DEFAULT NULL,
  `token_expiracion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `cedula`, `nombres`, `apellidos`, `email`, `telefono`, `rol`, `password`, `activo`, `ultimo_acceso`) VALUES
(1, 'admin', 'V-12345678', 'Administrador', 'del Sistema', 'admin@uenguarenas.edu.ve', '0412-1234567', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL),
(2, 'jcastro', 'V-23456789', 'Jhony', 'Castro', 'jcastro@uenguarenas.edu.ve', '0414-2345678', 'docente', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL),
(3, 'mrodriguez', 'V-34567890', 'María', 'Rodríguez', 'mrodriguez@uenguarenas.edu.ve', '0416-3456789', 'docente', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL),
(4, 'lgonzalez', 'V-45678901', 'Luis', 'González', 'lgonzalez@uenguarenas.edu.ve', '0424-4567890', 'docente', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL);

-- --------------------------------------------------------

--
-- TABLA NUEVA: login_attempts (control de intentos fallidos)
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `intentos` int(11) DEFAULT 1,
  `ultimo_intento` datetime DEFAULT NULL,
  `bloqueado_hasta` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- TABLA NUEVA: logs (auditoría)
--

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD KEY `seccion_id` (`seccion_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_seccion_estado` (`seccion_id`,`estado`);

--
-- Indices de la tabla `grados`
--
ALTER TABLE `grados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_area` (`area`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `estudiante_id` (`estudiante_id`),
  ADD KEY `seccion_materia_id` (`seccion_materia_id`),
  ADD KEY `idx_lapso_anio` (`lapso`,`anio_escolar`),
  ADD KEY `idx_estudiante_lapso` (`estudiante_id`,`lapso`,`anio_escolar`);

--
-- Indices de la tabla `secciones`
--
ALTER TABLE `secciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grado_id` (`grado_id`),
  ADD KEY `docente_guia_id` (`docente_guia_id`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_grado_activo` (`grado_id`,`activo`);

--
-- Indices de la tabla `seccion_materias`
--
ALTER TABLE `seccion_materias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seccion_id` (`seccion_id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `docente_id` (`docente_id`),
  ADD KEY `idx_seccion_materia` (`seccion_id`,`materia_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_rol_activo` (`rol`,`activo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `grados`
--
ALTER TABLE `grados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `notas`
--
ALTER TABLE `notas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `secciones`
--
ALTER TABLE `secciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `seccion_materias`
--
ALTER TABLE `seccion_materias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`seccion_id`) REFERENCES `secciones` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `notas`
--
ALTER TABLE `notas`
  ADD CONSTRAINT `notas_ibfk_1` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notas_ibfk_2` FOREIGN KEY (`seccion_materia_id`) REFERENCES `seccion_materias` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `secciones`
--
ALTER TABLE `secciones`
  ADD CONSTRAINT `secciones_ibfk_1` FOREIGN KEY (`grado_id`) REFERENCES `grados` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `secciones_ibfk_2` FOREIGN KEY (`docente_guia_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `seccion_materias`
--
ALTER TABLE `seccion_materias`
  ADD CONSTRAINT `seccion_materias_ibfk_1` FOREIGN KEY (`seccion_id`) REFERENCES `secciones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seccion_materias_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seccion_materias_ibfk_3` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;