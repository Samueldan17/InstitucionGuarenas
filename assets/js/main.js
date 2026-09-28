// assets/js/main.js - Funcionalidades JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    initTooltips();
    
    // Inicializar validaciones de formularios
    initFormValidations();
    
    // Configurar búsqueda en tiempo real
    initSearchFunctionality();
    
    // Configurar notificaciones
    initNotifications();
    
    // Configurar comportamientos generales
    initGeneralBehaviors();
});

function initTooltips() {
    const tooltipElements = document.querySelectorAll('[title]');
    tooltipElements.forEach(el => {
        el.addEventListener('mouseenter', showTooltip);
        el.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = this.getAttribute('title');
    document.body.appendChild(tooltip);

    const rect = this.getBoundingClientRect();
    tooltip.style.left = (rect.left + window.scrollX) + 'px';
    tooltip.style.top = (rect.top + window.scrollY - tooltip.offsetHeight - 5) + 'px';

    this._tooltip = tooltip;
}

function hideTooltip() {
    if (this._tooltip) {
        this._tooltip.remove();
        this._tooltip = null;
    }
}

function initFormValidations() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    highlightField(field, false);
                    showFieldError(field, 'Este campo es requerido');
                } else {
                    highlightField(field, true);
                    clearFieldError(field);
                }
            });
            
            // Validaciones específicas
            const emailFields = this.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value && !isValidEmail(field.value)) {
                    isValid = false;
                    highlightField(field, false);
                    showFieldError(field, 'Ingrese un email válido');
                }
            });
            
            const numberFields = this.querySelectorAll('input[type="number"]');
            numberFields.forEach(field => {
                if (field.value) {
                    const min = parseFloat(field.min);
                    const max = parseFloat(field.max);
                    const value = parseFloat(field.value);
                    
                    if (!isNaN(min) && value < min) {
                        isValid = false;
                        highlightField(field, false);
                        showFieldError(field, `El valor mínimo es ${min}`);
                    } else if (!isNaN(max) && value > max) {
                        isValid = false;
                        highlightField(field, false);
                        showFieldError(field, `El valor máximo es ${max}`);
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Por favor complete todos los campos requeridos correctamente', 'error');
            }
        });
    });
}

function highlightField(field, isValid) {
    if (isValid) {
        field.style.borderColor = '#27ae60';
    } else {
        field.style.borderColor = '#e74c3c';
    }
}

function showFieldError(field, message) {
    let errorElement = field.parentNode.querySelector('.field-error');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        field.parentNode.appendChild(errorElement);
    }
    errorElement.textContent = message;
    errorElement.style.color = '#e74c3c';
    errorElement.style.fontSize = '0.8rem';
    errorElement.style.marginTop = '0.25rem';
}

function clearFieldError(field) {
    const errorElement = field.parentNode.querySelector('.field-error');
    if (errorElement) {
        errorElement.remove();
    }
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function initSearchFunctionality() {
    const searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(this.value);
            }, 500);
        });
    }
}

function performSearch(query) {
    if (query.length >= 2) {
        // Aquí se implementaría la búsqueda en tiempo real
        console.log('Buscando:', query);
        // En una implementación real, haríamos una petición AJAX al servidor
    }
}

function initNotifications() {
    const notificationBtn = document.querySelector('.notification-btn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            showNotificationPanel();
        });
    }
}

function showNotificationPanel() {
    // Implementar panel de notificaciones
    showNotification('Panel de notificaciones en desarrollo', 'info');
}

function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification-toast notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-left: 4px solid var(--${type}-color);
        z-index: 1000;
        max-width: 300px;
        animation: slideInRight 0.3s ease-out;
    `;
    
    notification.innerHTML = `
        <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
            <i class="fas fa-${getNotificationIcon(type)}" style="color: var(--${type}-color); margin-top: 0.1rem;"></i>
            <div style="flex: 1;">
                <p style="margin: 0; font-size: 0.9rem; line-height: 1.4;">${message}</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #666; padding: 0; margin-left: 0.5rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-triangle',
        'warning': 'exclamation-circle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function initGeneralBehaviors() {
    // Confirmación para acciones destructivas
    const destructiveButtons = document.querySelectorAll('.btn-danger, [data-confirm]');
    destructiveButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || '¿Está seguro de realizar esta acción?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Mejorar experiencia en formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            form.addEventListener('submit', function() {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                
                // Re-habilitar después de 5 segundos por si hay error
                setTimeout(() => {
                    submitButton.disabled = false;
                    const originalText = submitButton.getAttribute('data-original-text') || 'Guardar';
                    submitButton.innerHTML = originalText;
                }, 5000);
            });
        }
    });
    
    // Guardar texto original de botones
    document.querySelectorAll('button[type="submit"]').forEach(button => {
        button.setAttribute('data-original-text', button.innerHTML);
    });
}

// Funciones utilitarias
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-ES', options);
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('es-ES', options);
}

function calculateAge(birthDate) {
    if (!birthDate) return null;
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    
    return age;
}

// Exportar datos
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) {
        showNotification('No se encontró la tabla para exportar', 'error');
        return;
    }
    
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Excluir columnas de acciones
            if (!cols[j].closest('.actions')) {
                let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s)/gm, " ");
                text = text.replace(/"/g, '""');
                row.push('"' + text + '"');
            }
        }
        
        csv.push(row.join(','));
    }
    
    downloadCSV(csv.join('\n'), filename);
    showNotification('Datos exportados correctamente', 'success');
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const downloadLink = document.createElement('a');
    
    downloadLink.download = filename || 'export.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Funciones para cálculos de notas
function calcularPromedio(notas) {
    if (!notas || notas.length === 0) return 0;
    
    const suma = notas.reduce((total, nota) => total + (parseFloat(nota) || 0), 0);
    return (suma / notas.length).toFixed(1);
}

function determinarEstado(promedio) {
    const numPromedio = parseFloat(promedio);
    if (numPromedio >= 16) return 'excelente';
    if (numPromedio >= 13) return 'bueno';
    if (numPromedio >= 10) return 'regular';
    return 'reprobado';
}

// Animaciones CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .loading {
        position: relative;
        pointer-events: none;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid transparent;
        border-top: 2px solid var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
