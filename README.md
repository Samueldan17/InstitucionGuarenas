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
*   **Respaldos Completos:** Botón para exportar todas las tablas de la base de datos a un archivo `.sql` en un solo clic, vital para la continuidad operativa ante cortes eléctricos.
*   **Validaciones de Negocio:** Restricción de edad para inscripción (10 a 20 años) y validación de cédulas venezolanas.

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

## ⚙️ Instalación y Despliegue Local (100% offline)

Requisitos:

* XAMPP con Apache y MySQL/MariaDB (PHP 8.2 o superior).
* El proyecto debe estar en `C:\xampp\htdocs\InstitucionGuarenas`. `config.php` usa `$base_path = '/InstitucionGuarenas'`; si cambia la carpeta, actualice ese valor.
* Las dependencias de `vendor/` deben estar incluidas en el paquete de instalación. Si no existen, ejecute `composer install` en un equipo con Internet y luego copie la carpeta `vendor/`.

Pasos para instalar en la computadora de la escuela:

1. Copiar la carpeta del proyecto a `C:\xampp\htdocs\InstitucionGuarenas`.
2. Iniciar Apache y MySQL desde el Panel de Control de XAMPP.
3. Importar la base de datos:
   * Abrir `http://localhost/phpmyadmin`.
   * Importar `basededatos/sistema_gestion_escolar.sql`. Este dump se genera desde la base real y ya incluye las tablas y columnas actuales.
4. Configurar las credenciales de la base:
   * Copiar `config.local.php.example` como `config.local.php`.
   * Ajustar `DB_USER` y `DB_PASS` con el usuario dedicado de la aplicación.
   * `config.local.php` no se versiona y no debe compartirse.
5. Crear el usuario dedicado en MySQL si aún no existe:

   ```sql
   CREATE USER 'sigeb_app'@'localhost' IDENTIFIED BY 'UNA_CONTRASENA_SEGURA';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP,
         REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
     ON sistema_gestion_escolar.* TO 'sigeb_app'@'localhost';
   FLUSH PRIVILEGES;
   ```

6. Abrir `http://localhost/InstitucionGuarenas/login.php`. El dump de demostración incluye el usuario `admin` con contraseña temporal `Cambiar2026*`; cámbiela inmediatamente después de entrar.
7. Verificar la sintaxis de todos los archivos PHP:

   ```bash
   php tools/verificar.php
   ```

## 💾 Respaldo y restauración

* El botón **Configuración → Descargar Respaldo** exporta **todas las tablas** de la base de datos.
* Guardar cada respaldo en una carpeta local y en una memoria USB o disco externo.
* Realizar un respaldo semanal y otro después de cerrar cada lapso.
* Para restaurar, importar el archivo `.sql` desde phpMyAdmin o con:

  ```bash
  mysql -u root sistema_gestion_escolar < respaldo_sigeb.sql
  ```

* Antes de la entrega, probar una restauración completa en una base de prueba.

## 🩺 Diagnóstico

* `config.php` tiene `display_errors=0`; los errores no se muestran en el navegador.
* Revisar `C:\xampp\apache\logs\error.log` y `C:\xampp\php\logs\php_error_log`.
* La auditoría de cambios está disponible en `auditoria.php` y en la tabla `logs`.

## 🔒 Seguridad en entorno offline

* Si el sistema se usa solo en una computadora, limitar Apache a `127.0.0.1`.
* Usar el usuario MySQL dedicado, no `root`.
* Cambiar las contraseñas iniciales de administradores, docentes, estudiantes y representantes.
* Los endpoints de `api/` y `ajax/` requieren una sesión autenticada.
