// assets/js/usabilidad.js - Mejoras de usabilidad para el sistema

// =========================================================
// 0. ASEGURAR QUE APP_URL ESTÉ DEFINIDA (fallback)
// =========================================================
if (typeof APP_URL === 'undefined') {
    var APP_URL = '';
}

// =========================================================
// 1. FORMATEO AUTOMÁTICO DE CÉDULA VENEZOLANA
// =========================================================

function formatearCedula(input) {
    let value = input.value.toUpperCase().replace(/[^VE0-9]/g, '');
    
    if (/^[VE]$/.test(value)) {
        value = value + '-';
    }
    if (/^[VE][0-9]+$/.test(value)) {
        value = value.charAt(0) + '-' + value.substring(1);
    }
    if (value.length > 10) {
        value = value.substring(0, 10);
    }
    input.value = value;
    validarCampoEnTiempoReal(input, validarCedula);
}

function validarCedula(cedula) {
    return /^[VE]-\d{5,8}$/.test(cedula);
}

// =========================================================
// 2. VALIDACIÓN DE CAMPOS NUMÉRICOS
// =========================================================

function soloNumeros(event) {
    const charCode = event.which ? event.which : event.keyCode;
    if ((charCode >= 48 && charCode <= 57) || 
        charCode === 8 || charCode === 9 || charCode === 13 ||
        (charCode >= 37 && charCode <= 40)) {
        return true;
    }
    event.preventDefault();
    mostrarToast('Solo se permiten números en este campo', 'warning');
    return false;
}

function soloNumerosYDecimal(event) {
    const charCode = event.which ? event.which : event.keyCode;
    const value = event.target.value;
    if ((charCode >= 48 && charCode <= 57) || 
        charCode === 46 || charCode === 8 || charCode === 9 || 
        charCode === 13 || (charCode >= 37 && charCode <= 40)) {
        if (charCode === 46 && value.includes('.')) {
            event.preventDefault();
            mostrarToast('Solo se permite un punto decimal', 'warning');
            return false;
        }
        return true;
    }
    event.preventDefault();
    mostrarToast('Solo se permiten números y punto decimal', 'warning');
    return false;
}

function formatearTelefono(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    if (value.length >= 4) {
        value = value.substring(0, 4) + '-' + value.substring(4);
    }
    if (value.length > 12) {
        value = value.substring(0, 12);
    }
    input.value = value;
    validarCampoEnTiempoReal(input, validarTelefono);
}

function validarTelefono(telefono) {
    return /^04(12|14|16|24|26|28)-\d{7}$/.test(telefono);
}

// =========================================================
// 3. CARGA DINÁMICA DE SECCIONES Y ESTUDIANTES (con APP_URL)
// =========================================================

function cargarSecciones(gradoId) {
    const seccionSelect = document.getElementById('seccion_id');
    const estudianteSelect = document.getElementById('estudiante_id');
    
    if (!seccionSelect) return;
    
    seccionSelect.innerHTML = '<option value="">👥 Seleccione una sección</option>';
    if (estudianteSelect) {
        estudianteSelect.innerHTML = '<option value="">👨‍🎓 Seleccione un estudiante</option>';
        estudianteSelect.disabled = true;
    }
    seccionSelect.disabled = true;
    
    if (!gradoId) return;
    
    seccionSelect.innerHTML = '<option value="">🔄 Cargando secciones...</option>';
    seccionSelect.disabled = false;
    seccionSelect.classList.add('select-loading');
    
    const url = (APP_URL ? APP_URL : '') + '/ajax/cargar_secciones.php?grado_id=' + gradoId;
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta');
            return response.json();
        })
        .then(data => {
            seccionSelect.innerHTML = '<option value="">👥 Seleccione una sección</option>';
            seccionSelect.classList.remove('select-loading');
            if (data.length > 0) {
                data.forEach(seccion => {
                    const option = document.createElement('option');
                    option.value = seccion.id;
                    option.textContent = `Sección ${seccion.letra} - Turno ${seccion.turno}`;
                    option.setAttribute('data-turno', seccion.turno);
                    seccionSelect.appendChild(option);
                });
                mostrarToast(`Se cargaron ${data.length} secciones`, 'success');
            } else {
                seccionSelect.innerHTML = '<option value="">❌ No hay secciones para este grado</option>';
                mostrarToast('No se encontraron secciones para este grado', 'warning');
            }
            seccionSelect.disabled = false;
        })
        .catch(error => {
            seccionSelect.innerHTML = '<option value="">❌ Error al cargar secciones</option>';
            seccionSelect.classList.remove('select-loading');
            console.error('Error:', error);
            mostrarToast('Error al cargar las secciones', 'error');
        });
}

function cargarEstudiantes(seccionId) {
    const estudianteSelect = document.getElementById('estudiante_id');
    if (!estudianteSelect) return;
    
    estudianteSelect.innerHTML = '<option value="">👨‍🎓 Seleccione un estudiante</option>';
    estudianteSelect.disabled = true;
    if (!seccionId) return;
    
    estudianteSelect.innerHTML = '<option value="">🔄 Cargando estudiantes...</option>';
    estudianteSelect.disabled = false;
    estudianteSelect.classList.add('select-loading');
    
    const url = (APP_URL ? APP_URL : '') + '/ajax/cargar_estudiantes.php?seccion_id=' + seccionId;
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta');
            return response.json();
        })
        .then(data => {
            estudianteSelect.innerHTML = '<option value="">👨‍🎓 Seleccione un estudiante</option>';
            estudianteSelect.classList.remove('select-loading');
            if (data.length > 0) {
                data.forEach(estudiante => {
                    const option = document.createElement('option');
                    option.value = estudiante.id;
                    option.textContent = `${estudiante.apellidos}, ${estudiante.nombres} - ${estudiante.cedula}`;
                    option.setAttribute('data-cedula', estudiante.cedula);
                    estudianteSelect.appendChild(option);
                });
                mostrarToast(`Se cargaron ${data.length} estudiantes`, 'success');
            } else {
                estudianteSelect.innerHTML = '<option value="">❌ No hay estudiantes en esta sección</option>';
                mostrarToast('No se encontraron estudiantes en esta sección', 'warning');
            }
            estudianteSelect.disabled = false;
        })
        .catch(error => {
            estudianteSelect.innerHTML = '<option value="">❌ Error al cargar estudiantes</option>';
            estudianteSelect.classList.remove('select-loading');
            console.error('Error:', error);
            mostrarToast('Error al cargar los estudiantes', 'error');
        });
}

// =========================================================
// 4. VALIDACIÓN EN TIEMPO REAL
// =========================================================

function validarCampoEnTiempoReal(input, validacionFn) {
    const value = input.value.trim();
    input.classList.remove('input-success', 'input-error', 'input-warning');
    if (!value) return;
    if (validacionFn(value)) {
        input.classList.add('input-success');
    } else {
        input.classList.add('input-error');
    }
}

// =========================================================
// 5. PREVENCIÓN DE ENVÍOS DOBLES
// =========================================================

function prevenirEnviosDobles() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn) {
            if (!submitBtn.getAttribute('data-original-text')) {
                submitBtn.setAttribute('data-original-text', submitBtn.innerHTML);
            }
            form.addEventListener('submit', function(e) {
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-loading');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('btn-loading');
                        submitBtn.innerHTML = submitBtn.getAttribute('data-original-text');
                        mostrarToast('La operación está tomando más tiempo de lo normal', 'warning');
                    }
                }, 10000);
            });
        }
    });
}

// =========================================================
// 6. SISTEMA DE TOAST/MENSAJES
// =========================================================

function mostrarToast(mensaje, tipo = 'info', duracion = 5000) {
    const toast = document.createElement('div');
    toast.className = `toast ${tipo}`;
    toast.innerHTML = `<div style="display: flex; align-items: center; gap: 10px;"><i class="fas ${getToastIcon(tipo)}"></i><span>${mensaje}</span></div>`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 300);
    }, duracion);
    toast.addEventListener('click', () => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 300);
    });
}

function getToastIcon(tipo) {
    const icons = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    };
    return icons[tipo] || 'fa-info-circle';
}

// =========================================================
// 7. CONFIRMACIONES CONTEXTUALES
// =========================================================

function confirmarAccion(accion, datos = {}) {
    const mensajes = {
        'eliminar': `¿Está seguro de eliminar ${datos.nombre || 'este registro'}? Esta acción no se puede deshacer.`,
        'cambiar_seccion': `¿Mover a ${datos.nombre || 'el estudiante'} a la sección ${datos.nuevaSeccion || 'nueva'}?`,
        'cerrar_periodo': `¿Cerrar el ${datos.periodo || 'período'}? No se podrán modificar notas después.`,
        'guardar_cambios': '¿Guardar los cambios realizados?',
        'salir_sin_guardar': 'Tiene cambios sin guardar. ¿Está seguro de salir?'
    };
    const mensaje = mensajes[accion] || '¿Está seguro de realizar esta acción?';
    return confirm(mensaje);
}

// =========================================================
// 8. INICIALIZACIÓN
// =========================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Sistema de usabilidad cargado');
    
    // 1. Formateo automático de cédulas
    const cedulaInputs = document.querySelectorAll('input[name="cedula"], input.cedula');
    cedulaInputs.forEach(input => {
        input.addEventListener('input', function() { formatearCedula(this); });
        if (input.value) formatearCedula(input);
    });
    
    // 2. Campos numéricos
    const numericoInputs = document.querySelectorAll('input[type="number"], input.numerico');
    numericoInputs.forEach(input => input.addEventListener('keypress', soloNumeros));
    const decimalInputs = document.querySelectorAll('input.decimal');
    decimalInputs.forEach(input => input.addEventListener('keypress', soloNumerosYDecimal));
    
    // 3. Teléfonos
    const telefonoInputs = document.querySelectorAll('input[name="telefono"], input.telefono');
    telefonoInputs.forEach(input => input.addEventListener('input', function() { formatearTelefono(this); }));
    
    // 4. Prevenir envíos dobles
    prevenirEnviosDobles();
    
    // 5. Carga dinámica para boletas
    const gradoSelect = document.getElementById('grado_id');
    if (gradoSelect) {
        gradoSelect.addEventListener('change', function() { cargarSecciones(this.value); });
    }
    const seccionSelect = document.getElementById('seccion_id');
    if (seccionSelect) {
        seccionSelect.addEventListener('change', function() { cargarEstudiantes(this.value); });
    }
    
    // 6. Validación email
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validarCampoEnTiempoReal(this, email => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));
        });
    });
    
    // 7. Confirmación eliminar
    const enlacesEliminar = document.querySelectorAll('a.eliminar, button.eliminar');
    enlacesEliminar.forEach(enlace => {
        enlace.addEventListener('click', function(e) {
            const nombre = this.getAttribute('data-nombre') || 'este registro';
            if (!confirmarAccion('eliminar', { nombre: nombre })) e.preventDefault();
        });
    });
    
    // 8. Detectar cambios en formularios
    const formsEditables = document.querySelectorAll('form.editable');
    formsEditables.forEach(form => {
        let formChanged = false;
        form.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('change', () => formChanged = true);
            element.addEventListener('input', () => formChanged = true);
        });
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        form.addEventListener('submit', () => formChanged = false);
    });
});

// =========================================================
// 9. FUNCIONES GLOBALES PARA USO EN OTROS ARCHIVOS
// =========================================================

window.Usabilidad = {
    mostrarToast,
    confirmarAccion,
    formatearCedula,
    validarCedula,
    cargarSecciones,
    cargarEstudiantes,
    prevenirEnviosDobles
};