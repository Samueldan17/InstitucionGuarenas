<?php
// templates/boleta_template.php - Template reutilizable para boletas
// Espera que la variable $boleta_data esté definida.
if (!isset($boleta_data) || empty($boleta_data)) {
    echo '<div class="alert alert-danger">No hay datos para mostrar.</div>';
    return;
}
?>
<div class="boleta-container" id="boleta">
    <div class="boleta-header">
        <img src="<?php echo APP_LOGO; ?>" alt="Logo" class="logo-escuela" onerror="this.src='<?php echo APP_LOGO_FALLBACK; ?>';">
        <div class="info-escuela">
            <h1><?php echo $boleta_data['info_escuela']['nombre']; ?></h1>
            <p><?php echo $boleta_data['info_escuela']['direccion']; ?> | Teléfono: <?php echo $boleta_data['info_escuela']['telefono']; ?></p>
            <p>Código D.E.A.: <?php echo $boleta_data['info_escuela']['codigo']; ?></p>
        </div>
    </div>

    <div class="boleta-title">
        BOLETÍN INFORMATIVO - Lapso <?php echo $boleta_data['lapso_consultado']; ?>
    </div>

    <div class="student-details">
        <div class="detail-item"><strong>Estudiante:</strong> <?php echo htmlspecialchars($boleta_data['info_estudiante']['apellidos'].', '.$boleta_data['info_estudiante']['nombres']); ?></div>
        <div class="detail-item"><strong>Cédula:</strong> <?php echo htmlspecialchars($boleta_data['info_estudiante']['cedula']); ?></div>
        <div class="detail-item"><strong>Grado y Sección:</strong> <?php echo htmlspecialchars($boleta_data['info_estudiante']['grado_nombre'].' '.$boleta_data['info_estudiante']['seccion_letra'].' ('.$boleta_data['info_estudiante']['turno'].')'); ?></div>
        <div class="detail-item"><strong>Docente Guía:</strong> <?php echo htmlspecialchars($boleta_data['info_estudiante']['docente_guia_nombre'] ?: 'No asignado'); ?></div>
    </div>

    <table class="grades-table">
        <thead>
            <tr>
                <th>Asignaturas</th>
                <th>1er Momento</th>
                <th>2do Momento</th>
                <th>3er Momento</th>
                <th>Definitiva (D)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $promedio_general = 0; 
            $total_materias = count($boleta_data['notas']); 
            foreach ($boleta_data['notas'] as $nota): 
                $d = floatval($nota['definitiva']);
                $r = floatval($nota['recuperacion']);
                $nota_final = ($r > $d && $r > 0) ? $r : $d;
                $promedio_general += $nota_final;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($nota['materia_nombre']); ?></td>
                <td><?php echo number_format($nota['evaluacion1'], 1); ?></td>
                <td><?php echo number_format($nota['evaluacion2'], 1); ?></td>
                <td><?php echo number_format($nota['evaluacion3'], 1); ?></td>
                <td><strong><?php echo number_format($nota_final, 1); ?></strong></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($total_materias > 0): $promedio_final = $promedio_general / $total_materias; ?>
            <tr class="total-row">
                <td colspan="4"><strong>PROMEDIO GENERAL</strong></td>
                <td><strong><?php echo number_format($promedio_final, 1); ?></strong></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="observaciones">
        <strong><i class="fas fa-comment"></i> Observaciones:</strong>
        <p><?php echo $boleta_data['notas'][0]['observaciones'] ?? 'Sin observaciones'; ?></p>
    </div>

    <div class="boleta-footer">
        <div class="firmas">
            <div>
                <div class="linea"></div>
                <p><strong>Dpto. de Evaluación</strong></p>
                <p>Prof. Daniela González</p>
            </div>
            <div>
                <div class="linea"></div>
                <p><strong>Director</strong></p>
                <p>Prof. Jhony Castro</p>
            </div>
            <div>
                <div class="linea"></div>
                <p><strong>Coordinadora</strong></p>
                <p>Prof. Eddy Peña</p>
            </div>
        </div>
    </div>

    <div style="margin-top:20px; text-align:right; font-size:0.85rem; color:#555;">
        <p>Los Teques, <?php echo fechaEspañol(date('Y-m-d')); ?></p>
    </div>
</div>