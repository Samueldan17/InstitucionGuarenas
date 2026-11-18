# AGENTS.md

SIGEB-G is a plain PHP 8 + MySQL/MariaDB school-grades app served by XAMPP/Apache. No framework, no router, no build step, no automated tests.

## Run and verify

- The app must live at `C:\xampp\htdocs\InstitucionGuarenas`. `config.php` hardcodes `$base_path = '/InstitucionGuarenas'`, so renaming/moving the folder requires editing that value.
- Dependencies: `composer install`. `vendor/` is gitignored but required for PDF export (DomPDF).
- Verify edits with `php tools/verificar.php` (runs `php -l` across repo PHP files) and manually at `http://localhost/InstitucionGuarenas/login.php`.
- `config.php` sets `display_errors=0` with `log_errors=1`; fatal errors do not render in the browser. Check `C:\xampp\apache\logs\error.log` or `C:\xampp\php\logs\php_error_log`.
- DB credentials: `config.php` loads `config.local.php` when present (gitignored). If absent it falls back to `localhost` / `sistema_gestion_escolar` / `root` / empty password. `config.local.php.example` documents the keys. There is no `.env`.
- The app uses a dedicated MySQL user (`sigeb_app`); do not switch back to `root` in deployment.

## Database

- `basededatos/sistema_gestion_escolar.sql` was regenerated from the live database and includes current tables/columns (`estudiantes.usuario_id/email/cedula_representante/...`, `usuarios.rol` values `estudiante`/`representante`, `notas.nota`/`es_recuperacion`, `secciones.anio_escolar`, `representantes_estudiantes`). Still inspect the live DB (`SHOW CREATE TABLE ...`) before schema changes; do not "fix" code to match old assumptions.
- The tracked dump is **sanitized for the public GitHub repo**: synthetic people, no logs/IPs, and a temporary admin password (`Cambiar2026*`). Never commit a raw live dump containing real student/representative data or password hashes.
- `configuracion` stores runtime keys such as `anio_escolar_activo` and `lapso_1_cerrado`..`lapso_3_cerrado`; closing a lapso blocks note editing.
- The backup button (`configuracion.php?accion=backup`) calls `generarRespaldoBaseDatos()` in `functions.php`: it dumps all base tables, data included, with foreign-key checks disabled. It is a PHP dump, not `mysqldump`, but it is complete.

## Conventions

- Protected pages start with `require_once 'config.php'; require_once 'functions.php'; requireAuth();`. Files under subdirectories include those with `__DIR__`.
- Every state-changing POST must carry `csrf_token` from `generateCSRFToken()` and validate it with `validateCSRFToken()`. Add this to any new form.
- Use PDO prepared statements exclusively. Passwords are bcrypt via `password_hash`/`password_verify`; never reintroduce a login bypass.
- Roles: `admin`, `docente`, `secretario`, `estudiante`, `representante`. Menu permissions live in `getSidebar()` (`config.php:219`); individual pages also guard roles (e.g. `estudiantes.php` redirects `docente`).
- Student/representative entry pages are `panel_estudiante.php` and `panel_representante.php`; all other features are flat root-level `.php` pages with inline HTML/CSS.
- Venezuelan-specific rules live in `config.php` / `functions.php`: cédula `V|E|J-12345678`, phone prefixes `0412/0414/0416/0424/0426/0428`, name normalization via `formatearNombrePropio()`, password policy via `validarPasswordSegura()` (min 8, upper, lower, digit, symbol).
- Student age range is controlled by `EDAD_MINIMA_ESTUDIANTE` / `EDAD_MAXIMA_ESTUDIANTE` (10/20) in `config.php`; use those constants instead of hardcoding ages. `README.md` reflects the same range.
- `api/*.php` and `ajax/*.php` return JSON and now require a session via `requireApiAuth()` or `requireApiRole([...])` (HTTP 401/403). List endpoints allow `admin`/`docente`/`secretario`; `api/boleta.php` also allows students to read only their own data and representatives only their linked students.
- PDF export uses DomPDF from `vendor/autoload.php` in `boletas.php` and `reportes.php`. `templates/boleta_template.php` checks `IS_PDF_GENERATION` to hide the logo for PDFs. TCPDF was removed from `composer.json`.
- UI text and validation messages are Spanish; keep them Spanish.
