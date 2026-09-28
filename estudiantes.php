<?php
// estudiantes.php - Gestión de Estudiantes con planilla de inscripción completa
require_once 'config.php';
require_once 'functions.php';
requireAuth();

$db = getDBConnection();
$message = '';
$error = '';

// Crear estudiante
if (($_POST['action'] ?? '') == 'crear_estudiante') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido. Recargue la página.";
    } else {
        try {
            $cedula = formatearCedulaParaBD($_POST['cedula'] ?? '');
            $nombres = formatearNombrePropio($_POST['nombres'] ?? '');
            $apellidos = formatearNombrePropio($_POST['apellidos'] ?? '');
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
            $genero = $_POST['genero'] ?? '';
            $nacionalidad = sanitize($_POST['nacionalidad'] ?? 'Venezolana');
            $lugar_nacimiento = sanitize($_POST['lugar_nacimiento'] ?? '');
            $direccion = sanitize($_POST['direccion'] ?? '');
            $telefono_contacto = sanitize($_POST['telefono_contacto'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $nombre_representante = formatearNombrePropio($_POST['nombre_representante'] ?? '');
            $email_representante = sanitize($_POST['email_representante'] ?? '');
            $telefono_representante = sanitize($_POST['telefono_representante'] ?? '');
            $parentesco = sanitize($_POST['parentesco'] ?? '');
            $procedencia = sanitize($_POST['procedencia'] ?? '');
            $seccion_id = (int)($_POST['seccion_id'] ?? 0);
            $fecha_inscripcion = $_POST['fecha_inscripcion'] ?? date('Y-m-d');

            if (!validarCedulaVenezolana($cedula)) throw new Exception("La cédula debe tener formato V-12345678");
            if (empty($nombres) || empty($apellidos)) throw new Exception("Los nombres y apellidos son obligatorios");
            if (empty($seccion_id)) throw new Exception("Debe seleccionar una sección");
            if ($email && !validarEmail($email)) throw new Exception("El email del estudiante no es válido");
            if ($email_representante && !validarEmail($email_representante)) throw new Exception("El email del representante no es válido");

            $stmt = $db->prepare("SELECT id FROM estudiantes WHERE cedula = ?");
            $stmt->execute([$cedula]);
            if ($stmt->fetch()) throw new Exception("Ya existe un estudiante con la misma cédula");

            $stmt = $db->prepare("INSERT INTO estudiantes (cedula, nombres, apellidos, fecha_nacimiento, genero, nacionalidad, lugar_nacimiento, direccion, telefono_contacto, email, nombre_representante, email_representante, telefono_representante, parentesco, procedencia, seccion_id, fecha_inscripcion, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')");
            $stmt->execute([$cedula, $nombres, $apellidos, $fecha_nacimiento, $genero, $nacionalidad, $lugar_nacimiento, $direccion, $telefono_contacto, $email, $nombre_representante, $email_representante, $telefono_representante, $parentesco, $procedencia, $seccion_id, $fecha_inscripcion]);
            $message = "✅ Estudiante registrado exitosamente";
            registrarLog('crear_estudiante', 'estudiantes', $db->lastInsertId(), "Nuevo estudiante: $cedula - $nombres $apellidos");
        } catch (Exception $e) {
            $error = "❌ " . $e->getMessage();
        }
    }
}

// Eliminar estudiante (POST con CSRF)
if (($_POST['action'] ?? '') == 'eliminar_estudiante' && isset($_POST['id'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $estudiante_id = (int)$_POST['id'];
        try {
            $stmt = $db->prepare("UPDATE estudiantes SET estado = 'inactivo' WHERE id = ?");
            $stmt->execute([$estudiante_id]);
            $message = "✅ Estudiante eliminado exitosamente";
            registrarLog('eliminar_estudiante', 'estudiantes', $estudiante_id);
        } catch (Exception $e) {
            $error = "❌ Error al eliminar estudiante: " . $e->getMessage();
        }
    }
}

$estudiantes = $db->query("SELECT e.*, g.nombre as grado_nombre, s.letra as seccion_letra, s.turno FROM estudiantes e JOIN secciones s ON e.seccion_id = s.id JOIN grados g ON s.grado_id = g.id WHERE e.estado = 'activo' ORDER BY e.apellidos, e.nombres")->fetchAll();
$secciones = $db->query("SELECT s.id, g.nombre as grado, s.letra, s.turno FROM secciones s JOIN grados g ON s.grado_id = g.id WHERE s.activo = 1 ORDER BY g.orden, s.letra")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Estudiantes - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php echo getSidebar('estudiantes'); ?>
    <main class="main-content">
        <header class="main-header">
            <h1><i class="fas fa-user-graduate"></i> Gestión de Estudiantes</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="abrirModalNuevoEstudiante()"><i class="fas fa-user-plus"></i> Nuevo Estudiante</button>
            </div>
        </header>
        <div class="content-container">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h3><?php echo count($estudiantes); ?></h3><p>Estudiantes Activos</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:var(--warning-color);"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo count($secciones); ?></h3><p>Secciones Activas</p></div></div>
            </div>
            <div class="card">
                <div class="card-header"><h2><i class="fas fa-list"></i> Listado de Estudiantes</h2>
                    <div class="card-actions">
                        <div class="search-form"><div class="search-box"><input type="text" id="buscarEstudiante" placeholder="Buscar estudiante..." onkeyup="filtrarEstudiantes()"><button type="button" class="search-btn"><i class="fas fa-search"></i></button></div></div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($estudiantes) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table" id="tablaEstudiantes">
                            <thead><tr><th>Cédula</th><th>Nombres y Apellidos</th><th>Grado y Sección</th><th>Teléfono</th><th>Email</th><th>Representante</th><th>Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($estudiantes as $est): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($est['cedula']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($est['apellidos'] . ', ' . $est['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($est['grado_nombre'] . ' ' . $est['seccion_letra'] . ' ' . $est['turno']); ?></td>
                                    <td><?php echo htmlspecialchars($est['telefono_contacto'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($est['email'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($est['nombre_representante'] ?: 'N/A'); ?></td>
                                    <td class="actions">
                                        <button class="btn btn-sm btn-info" onclick="editarEstudiante(<?php echo $est['id']; ?>)"><i class="fas fa-edit"></i> Editar</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar al estudiante <?php echo htmlspecialchars($est['nombres'] . ' ' . $est['apellidos']); ?>?')">
                                            <input type="hidden" name="action" value="eliminar_estudiante">
                                            <input type="hidden" name="id" value="<?php echo $est['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-user-graduate fa-4x"></i><h3>No hay estudiantes registrados</h3><button class="btn btn-primary" onclick="abrirModalNuevoEstudiante()">Agregar Primer Estudiante</button></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Nuevo Estudiante (Planilla de Inscripción) -->
<div id="modalNuevoEstudiante" class="modal">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-close" onclick="cerrarModalNuevoEstudiante()">&times;</div>
        <div class="card"><div class="card-header"><h2><i class="fas fa-user-plus"></i> Planilla de Inscripción</h2></div><div class="card-body">
            <form method="POST" action="estudiantes.php" class="editable" id="formEstudiante">
                <input type="hidden" name="action" value="crear_estudiante">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="form-grid">
                    <div class="form-group"><label>Cédula</label><input type="text" name="cedula" class="form-control cedula" placeholder="V-12345678" required></div>
                    <div class="form-group"><label>Nombres</label><input type="text" name="nombres" class="form-control" required></div>
                    <div class="form-group"><label>Apellidos</label><input type="text" name="apellidos" class="form-control" required></div>
                    <div class="form-group"><label>Fecha Nacimiento</label><input type="date" name="fecha_nacimiento" class="form-control"></div>
                    <div class="form-group"><label>Género</label><select name="genero" class="form-control"><option value="">Seleccione...</option><option value="M">Masculino</option><option value="F">Femenino</option></select></div>
                    <div class="form-group"><label>Nacionalidad</label><input type="text" name="nacionalidad" class="form-control" placeholder="Venezolana"></div>
                    <div class="form-group"><label>Lugar de Nacimiento</label><input type="text" name="lugar_nacimiento" class="form-control" placeholder="Ciudad, Estado"></div>
                    <div class="form-group"><label>Dirección</label><textarea name="direccion" class="form-control" rows="2" placeholder="Dirección completa"></textarea></div>
                    <div class="form-group"><label>Teléfono Contacto</label><input type="text" name="telefono_contacto" class="form-control telefono" placeholder="0412-1234567"></div>
                    <div class="form-group"><label>Email Estudiante</label><input type="email" name="email" class="form-control" placeholder="estudiante@correo.com"></div>
                    <div class="form-group"><label>Sección</label><select name="seccion_id" class="form-control" required><option value="">Seleccione</option><?php foreach ($secciones as $sec): ?><option value="<?php echo $sec['id']; ?>"><?php echo htmlspecialchars($sec['grado'] . ' ' . $sec['letra'] . ' (' . $sec['turno'] . ')'); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Nombre Representante</label><input type="text" name="nombre_representante" class="form-control" placeholder="Nombre completo"></div>
                    <div class="form-group"><label>Email Representante</label><input type="email" name="email_representante" class="form-control" placeholder="representante@correo.com"></div>
                    <div class="form-group"><label>Teléfono Representante</label><input type="text" name="telefono_representante" class="form-control telefono" placeholder="0412-1234567"></div>
                    <div class="form-group"><label>Parentesco</label><input type="text" name="parentesco" class="form-control" placeholder="Padre/Madre/Tutor"></div>
                    <div class="form-group"><label>Procedencia</label><input type="text" name="procedencia" class="form-control" placeholder="Escuela de origen"></div>
                    <div class="form-group"><label>Fecha Inscripción</label><input type="date" name="fecha_inscripcion" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Estudiante</button><button type="reset" class="btn btn-secondary" onclick="cerrarModalNuevoEstudiante()"><i class="fas fa-times"></i> Cancelar</button></div>
            </form>
        </div></div>
    </div>
</div>

<script>var APP_URL = "<?php echo APP_URL; ?>";</script>
<script src="<?php echo APP_URL; ?>/assets/js/usabilidad.js"></script>
<script>
function abrirModalNuevoEstudiante() { document.getElementById('modalNuevoEstudiante').style.display = 'block'; }
function cerrarModalNuevoEstudiante() { document.getElementById('modalNuevoEstudiante').style.display = 'none'; }
function editarEstudiante(id) { window.location.href = `editar_estudiante.php?id=${id}`; }
function filtrarEstudiantes() {
    const input = document.getElementById('buscarEstudiante'), filter = input.value.toUpperCase(), table = document.getElementById('tablaEstudiantes'), tr = table.getElementsByTagName('tr');
    for (let i = 1; i < tr.length; i++) {
        const tdCedula = tr[i].getElementsByTagName('td')[0], tdNombre = tr[i].getElementsByTagName('td')[1];
        if (tdNombre || tdCedula) {
            const txtCed = tdCedula.textContent || tdCedula.innerText, txtNom = tdNombre.textContent || tdNombre.innerText;
            if (txtCed.toUpperCase().indexOf(filter) > -1 || txtNom.toUpperCase().indexOf(filter) > -1) tr[i].style.display = ''; else tr[i].style.display = 'none';
        }
    }
}
window.onclick = function(e) { if(e.target == document.getElementById('modalNuevoEstudiante')) cerrarModalNuevoEstudiante(); }
</script>
</body>
</html>