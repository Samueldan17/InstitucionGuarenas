<?php
// templates/boleta_template.php
// Este archivo espera la variable $boleta_data con la estructura que devuelve getBoletaData()
if (!isset($boleta_data) || !$boleta_data || isset($boleta_data['error'])) {
    echo '<div class="alert alert-danger">No hay datos disponibles para generar el boletín.</div>';
    return;
}

$escuela = $boleta_data['info_escuela'];
$est = $boleta_data['info_estudiante'];
$notas = $boleta_data['notas'];
$lapso = $boleta_data['lapso_consultado'];
$anio = $boleta_data['anio_escolar'];

// Calcular promedio general
$suma = 0; $contador = 0;
foreach ($notas as $n) {
    $final = obtenerNotaFinal($n['definitiva'], $n['recuperacion']);
    $suma += $final;
    $contador++;
}
$promedio_general = $contador > 0 ? round($suma / $contador, 2) : 0;
?>
<div class="boleta-oficial">
    <style>
        .boleta-oficial {
            background: white;
            padding: 30px 40px;
            max-width: 900px;
            margin: 0 auto;
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            border: 1px solid #ccc;
        }
        .boleta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .boleta-header .logo-left,
        .boleta-header .logo-right {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .boleta-header-text {
            text-align: center;
            flex: 1;
        }
        .boleta-header-text .pais { font-size: 12px; font-weight: bold; }
        .boleta-header-text .ministerio { font-size: 11px; }
        .boleta-header-text .nombre-escuela { font-size: 16px; font-weight: bold; margin-top: 5px; }
        .boleta-header-text .codigo { font-size: 10px; }
        .boleta-header-text .anio-escolar { font-size: 11px; margin-top: 3px; }
        .boleta-titulo {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            text-decoration: underline;
            margin: 15px 0;
            letter-spacing: 2px;
        }
        .boleta-datos {
            font-size: 13px;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        .boleta-datos .linea {
            display: flex;
            gap: 10px;
            align-items: baseline;
        }
        .boleta-datos .campo {
            border-bottom: 1px solid #000;
            padding: 0 5px;
            min-width: 100px;
            font-weight: bold;
        }
        .boleta-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 15px 0;
        }
        .boleta-tabla th,
        .boleta-tabla td {
            border: 1px solid #000;
            padding: 5px 8px;
            text-align: center;
        }
        .boleta-tabla th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .boleta-tabla td.materia {
            text-align: left;
            font-weight: 500;
        }
        .boleta-promedio {
            text-align: right;
            font-size: 13px;
            margin: 10px 0;
            font-weight: bold;
        }
        .boleta-pie {
            font-size: 12px;
            margin-top: 20px;
            line-height: 1.6;
        }
        .boleta-firmas {
            display: flex;
            justify-content: space-around;
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
        }
        .boleta-firmas .firma {
            border-top: 1px solid #000;
            padding-top: 5px;
            min-width: 180px;
        }
        .boleta-firmas .nombre {
            font-weight: bold;
        }
        .boleta-firmas .cargo {
            font-size: 11px;
        }
        @media print {
            .boleta-oficial { border: none; padding: 10mm; }
            body { margin: 0; }
        }
    </style>

    <!-- Encabezado -->
    <div class="boleta-header">
        <img src="<?php echo APP_LOGO; ?>" alt="Logo" class="logo-left" onerror="this.style.display='none';">
        <div class="boleta-header-text">
            <div class="pais">REPÚBLICA BOLIVARIANA DE VENEZUELA</div>
            <div class="ministerio">MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN</div>
            <div class="ministerio">COMPLEJO EDUCATIVO NACIONAL</div>
            <div class="nombre-escuela">GUARENAS</div>
            <div class="codigo">CÓDIGO D.E.A. <?php echo htmlspecialchars($escuela['codigo']); ?></div>
            <div class="anio-escolar">AÑO ESCOLAR <?php echo htmlspecialchars($escuela['periodo_escolar']); ?></div>
        </div>
        <img src="<?php echo APP_LOGO; ?>" alt="Logo" class="logo-right" onerror="this.style.display='none';">
    </div>

    <!-- Título -->
    <div class="boleta-titulo">BOLETÍN INFORMATIVO</div>

    <!-- Datos del estudiante -->
    <div class="boleta-datos">
        <div class="linea">
            Quien suscribe, Director del C.E.N. "Guarenas", hace constar que el (la) estudiante:
        </div>
        <div class="linea">
            <span class="campo" style="min-width: 350px;"><?php echo htmlspecialchars(strtoupper($est['apellidos'] . ' ' . $est['nombres'])); ?></span>
        </div>
        <div class="linea">
            del <span class="campo" style="min-width: 60px;"><?php echo htmlspecialchars($est['grado_nombre']); ?></span>
            año Sección <span class="campo" style="min-width: 40px;"><?php echo htmlspecialchars($est['seccion_letra']); ?></span>
            y portador de la C.I. N° <span class="campo" style="min-width: 120px;"><?php echo htmlspecialchars($est['cedula']); ?></span>
        </div>
        <div class="linea">
            obtuvo las calificaciones siguientes durante el <span class="campo" style="min-width: 80px;"><?php echo $lapso; ?>er Lapso</span>
            del Año Escolar <span class="campo" style="min-width: 100px;"><?php echo htmlspecialchars($escuela['periodo_escolar']); ?></span> en curso.
        </div>
    </div>

    <!-- Tabla de notas -->
    <table class="boleta-tabla">
        <thead>
            <tr>
                <th style="width: 40%;">ASIGNATURAS</th>
                <th>1er MOMENTO</th>
                <th>2do MOMENTO</th>
                <th>3er MOMENTO</th>
                <th>DEFINITIVA</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notas as $n): 
                $def = floatval($n['definitiva']);
                $rec = $n['recuperacion'];
                $final = obtenerNotaFinal($def, $rec);
            ?>
            <tr>
                <td class="materia"><?php echo htmlspecialchars(strtoupper($n['materia_nombre'])); ?></td>
                <td><?php echo $n['evaluacion1'] !== null ? number_format($n['evaluacion1'], 0) : '—'; ?></td>
                <td><?php echo $n['evaluacion2'] !== null ? number_format($n['evaluacion2'], 0) : '—'; ?></td>
                <td><?php echo $n['evaluacion3'] !== null ? number_format($n['evaluacion3'], 0) : '—'; ?></td>
                <td><strong><?php echo number_format($final, 0); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Promedio -->
    <div class="boleta-promedio">
        PROMEDIO GENERAL: <?php echo number_format($promedio_general, 2); ?> pts
    </div>

    <!-- Pie -->
    <div class="boleta-pie">
        Se expide la presente constancia a petición de la parte interesada en Los Teques, a los
        ____ días del mes de ____________________ año <?php echo date('Y'); ?>.
    </div>

    <!-- Firmas -->
    <div class="boleta-firmas">
        <div class="firma">
            <div class="nombre">Prof. Jhony Castro</div>
            <div class="cargo">Director</div>
        </div>
        <div class="firma">
            <div class="nombre">Coordinadora</div>
            <div class="cargo">Evaluación y Control de Estudios</div>
        </div>
    </div>
</div>