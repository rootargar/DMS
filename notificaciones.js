/**
 * =============================================
 * JAVASCRIPT PARA NOTIFICACIONES INTERNAS
 * Sistema de Gestión Documental
 * =============================================
 */

// Variables globales
let notificationDropdown = null;
let notificationInterval = null;

/**
 * Inicializar sistema de notificaciones
 */
document.addEventListener('DOMContentLoaded', function() {
    notificationDropdown = document.getElementById('notificationDropdown');

    // Cargar notificaciones iniciales
    cargarNotificaciones();

    // Actualizar cada 30 segundos
    notificationInterval = setInterval(cargarNotificaciones, 30000);

    // Cerrar dropdown al hacer click fuera
    document.addEventListener('click', function(event) {
        const widget = document.querySelector('.notification-widget');
        if (widget && !widget.contains(event.target)) {
            cerrarNotificaciones();
        }
    });
});

/**
 * Toggle del dropdown de notificaciones
 */
function toggleNotificaciones() {
    if (notificationDropdown.classList.contains('show')) {
        cerrarNotificaciones();
    } else {
        abrirNotificaciones();
    }
}

/**
 * Abrir dropdown de notificaciones
 */
function abrirNotificaciones() {
    notificationDropdown.classList.add('show');
    cargarNotificaciones();
}

/**
 * Cerrar dropdown de notificaciones
 */
function cerrarNotificaciones() {
    notificationDropdown.classList.remove('show');
}

/**
 * Cargar notificaciones desde el servidor
 */
function cargarNotificaciones() {
    fetch('ajax_notificaciones.php?action=get')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                actualizarContador(data.count);
                renderizarNotificaciones(data.notifications);
            }
        })
        .catch(error => {
            console.error('Error cargando notificaciones:', error);
        });
}

/**
 * Actualizar contador de notificaciones
 */
function actualizarContador(count) {
    const bell = document.getElementById('notificationBell');
    let badge = bell.querySelector('.notification-badge');

    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'notification-badge';
            bell.appendChild(badge);
        }
        badge.textContent = count > 99 ? '99+' : count;
    } else {
        if (badge) {
            badge.remove();
        }
    }
}

/**
 * Renderizar lista de notificaciones
 */
function renderizarNotificaciones(notifications) {
    const list = document.getElementById('notificationList');

    if (!notifications || notifications.length === 0) {
        list.innerHTML = `
            <div class="notification-empty">
                <i class="fa fa-bell-slash"></i>
                <p>No tienes notificaciones</p>
            </div>
        `;
        return;
    }

    let html = '';
    notifications.forEach(notif => {
        const unreadClass = notif.leida == 0 ? 'unread' : '';
        const priorityClass = 'priority-' + notif.prioridad;
        const timeAgo = calcularTiempoTranscurrido(notif.fecha_creacion);

        html += `
            <div class="notification-item ${unreadClass} ${priorityClass}"
                 data-id="${notif.id}"
                 onclick="clickNotificacion(${notif.id}, '${notif.url || ''}')">
                <div class="notification-item-header">
                    <div class="notification-icon">
                        <i class="fa ${notif.icono}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${escapeHtml(notif.titulo)}</div>
                        <div class="notification-message">${escapeHtml(notif.mensaje)}</div>
                        <div class="notification-time">
                            <i class="fa fa-clock"></i>
                            ${timeAgo}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    list.innerHTML = html;
}

/**
 * Click en una notificación
 */
function clickNotificacion(id, url) {
    // Marcar como leída
    marcarComoLeida(id);

    // Redirigir si tiene URL
    if (url && url !== '') {
        window.location.href = url;
    }
}

/**
 * Marcar una notificación como leída
 */
function marcarComoLeida(id) {
    fetch('ajax_notificaciones.php?action=mark_read&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cargarNotificaciones();
            }
        })
        .catch(error => {
            console.error('Error marcando como leída:', error);
        });
}

/**
 * Marcar todas las notificaciones como leídas
 */
function marcarTodasLeidas() {
    if (confirm('¿Marcar todas las notificaciones como leídas?')) {
        fetch('ajax_notificaciones.php?action=mark_all_read')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cargarNotificaciones();
                }
            })
            .catch(error => {
                console.error('Error marcando todas:', error);
            });
    }
}

/**
 * Limpiar notificaciones leídas
 */
function limpiarLeidas() {
    if (confirm('¿Eliminar todas las notificaciones leídas?')) {
        fetch('ajax_notificaciones.php?action=clear_read')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cargarNotificaciones();
                }
            })
            .catch(error => {
                console.error('Error limpiando:', error);
            });
    }
}

/**
 * Calcular tiempo transcurrido
 */
function calcularTiempoTranscurrido(fecha) {
    const ahora = new Date();
    const entonces = new Date(fecha);
    const segundos = Math.floor((ahora - entonces) / 1000);

    if (segundos < 60) return 'Hace un momento';

    const minutos = Math.floor(segundos / 60);
    if (minutos < 60) return `Hace ${minutos} min`;

    const horas = Math.floor(minutos / 60);
    if (horas < 24) return `Hace ${horas} hora${horas > 1 ? 's' : ''}`;

    const dias = Math.floor(horas / 24);
    if (dias < 7) return `Hace ${dias} día${dias > 1 ? 's' : ''}`;

    const semanas = Math.floor(dias / 7);
    if (semanas < 4) return `Hace ${semanas} semana${semanas > 1 ? 's' : ''}`;

    const meses = Math.floor(dias / 30);
    return `Hace ${meses} mes${meses > 1 ? 'es' : ''}`;
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
}

/**
 * Limpiar intervalo al salir
 */
window.addEventListener('beforeunload', function() {
    if (notificationInterval) {
        clearInterval(notificationInterval);
    }
});
