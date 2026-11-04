# 🎓 SIGEB-G | Sistema de Gestión de Calificaciones

![Estado](https://img.shields.io/badge/Estado-Listo%20para%20Producción-success)
![PHP](https://img.shields.io/badge/PHP-8.x-blue)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-orange)
![Offline](https://img.shields.io/badge/Entorno-100%25%20Offline-lightgrey)
![Licencia](https://img.shields.io/badge/Licencia-Académica-green)

**SIGEB-G** (Sistema Automatizado para la Gestión de Calificaciones del Bachillerato) es una aplicación web de entorno local (offline) diseñada para el Departamento de Evaluación y Control de Estudios de la **U.E.N. "Guarenas"**. 

El sistema sustituye el manejo manual y fragmentado de notas (basado en hojas de cálculo Excel) por una base de datos relacional centralizada, segura y con capacidad de generar reportes oficiales en PDF.

## 🎯 El Problema y la Solución
La institución carecía de un sistema centralizado. Los datos se perdían entre múltiples archivos Excel, no existía auditoría de cambios, y el riesgo de pérdida de información por fallas eléctricas era latente. 

**SIGEB-G** resuelve esto mediante una arquitectura Cliente-Servidor ligera, optimizada para correr en equipos con recursos limitados (2GB - 4GB de RAM), garantizando la integridad referencial de los datos y la generación automatizada de documentos probatorios.

## 🚀 Características Principales

*   **Control de Acceso Basado en Roles (RBAC):** Diferentes vistas y permisos para Administrador, Docente, Estudiante y Representante.
*   **Gestión de Calificaciones:** Carga de notas con validación estricta (rango de 0 a 20 pts), cálculo automático de promedios por lapso y notas finales. Bloqueo de edición al cerrar un lapso.
*   **Generación de Reportes:** Creación de boletines y actas de cierre en formato PDF real (usando `DomPDF`), listos para imprimir en carta/oficio.
*   **Seguridad de Nivel Empresarial:**
    *   Prevención de Inyección SQL mediante **PDO** (Sentencias Preparadas).
    *   Encriptación de contraseñas con **Bcrypt**.
    *   Protección contra ataques CSRF (Tokens únicos por formulario).
    *   Bloqueo de IP tras 5 intentos fallidos de inicio de sesión.
*   **Auditoría:** Registro detallado (Logs) de quién modifica qué nota, a qué hora y desde qué IP.
*   **Respaldos Automatizados:** Botón para exportar toda la base de datos a un archivo `.sql` en un solo clic, vital para la continuidad operativa ante cortes eléctricos.
*   **Validaciones de Negocio:** Restricción de edad para inscripción (10 a 25 años) y validación de cédulas venezolanas.

## 🛠️ Stack Tecnológico

*   **Backend:** PHP Nativo (sin frameworks pesados, optimizado para bajo consumo de RAM).
*   **Base de Datos:** MySQL / MariaDB.
*   **Servidor Local:** XAMPP (Apache).
*   **Librerías Externas:** Composer para la gestión de dependencias (DomPDF).
*   **Frontend:** HTML5, CSS3, JavaScript (Vanilla) e íconos de FontAwesome.
*   ## 🚀 Estado Actual del Proyecto
✅ **¡Implementado en Producción!** 
El sistema SIGEB-G fue presentado exitosamente en la Expoferia de Proyectos Socio-Tecnológicos de la UPTAMCA y **aprobado para su implementación inmediata** en la U.E.N. "Guarenas". 

Actualmente el sistema se encuentra operativo en la oficina de Control de Estudios, sustituyendo completamente el uso de hojas de cálculo manuales y garantizando la integridad de la memoria académica de cientos de estudiantes.D

## ⚙️ Instalación y Despliegue Local

Si deseas correr este proyecto en tu computadora, sigue estos pasos:

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/Samueldan17/InstitucionGuarenas.git
