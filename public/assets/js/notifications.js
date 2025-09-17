/**
 * BIG PLAN TRACKER - Notification System
 * Real-time notifications with MongoDB backend integration
 */

class NotificationSystem {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.isInitialized = false;
        this.refreshInterval = null;
        this.config = {
            API_BASE: '/api/notifications/system.php',
            REFRESH_INTERVAL: 30000, // 30 seconds
            MAX_NOTIFICATIONS: 50,
            NOTIFICATION_DURATION: 5000 // 5 seconds for toast notifications
        };
        
        this.init();
    }

    async init() {
        try {
            this.createNotificationElements();
            this.bindEvents();
            await this.loadNotifications();
            this.startPeriodicRefresh();
            this.isInitialized = true;
            console.log('Notification system initialized successfully');
        } catch (error) {
            console.error('Failed to initialize notification system:', error);
        }
    }

    createNotificationElements() {
        // Create notification bell icon if it doesn't exist
        if (!document.getElementById('notificationBell')) {
            const bellContainer = document.createElement('div');
            bellContainer.className = 'notification-bell-container';
            bellContainer.innerHTML = `
                <button id="notificationBell" class="btn btn-link notification-bell" title="Notificaciones">
                    <i class="fas fa-bell"></i>
                    <span id="notificationBadge" class="notification-badge d-none">0</span>
                </button>
            `;
            
            // Try to add to navbar or header
            const navbar = document.querySelector('.navbar-nav') || document.querySelector('header') || document.body;
            navbar.appendChild(bellContainer);
        }

        // Create notification dropdown panel
        if (!document.getElementById('notificationPanel')) {
            const panel = document.createElement('div');
            panel.id = 'notificationPanel';
            panel.className = 'notification-panel';
            panel.innerHTML = `
                <div class="notification-header">
                    <h6 class="mb-0">Notificaciones</h6>
                    <div class="notification-actions">
                        <button id="markAllRead" class="btn btn-sm btn-link">Marcar todas como leídas</button>
                        <button id="closeNotifications" class="btn btn-sm btn-link">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="notification-filters">
                    <button class="filter-btn active" data-filter="all">Todas</button>
                    <button class="filter-btn" data-filter="unread">No leídas</button>
                    <button class="filter-btn" data-filter="high">Urgentes</button>
                </div>
                <div id="notificationList" class="notification-list">
                    <div class="notification-loading">
                        <i class="fas fa-spinner fa-spin"></i> Cargando notificaciones...
                    </div>
                </div>
                <div class="notification-footer">
                    <button id="loadMoreNotifications" class="btn btn-sm btn-outline-primary w-100">
                        Cargar más
                    </button>
                </div>
            `;
            document.body.appendChild(panel);
        }

        // Create toast container
        if (!document.getElementById('toastContainer')) {
            const toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }
    }

    bindEvents() {
        // Bell click event
        const bell = document.getElementById('notificationBell');
        if (bell) {
            bell.addEventListener('click', () => this.toggleNotificationPanel());
        }

        // Close panel event
        const closeBtn = document.getElementById('closeNotifications');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hideNotificationPanel());
        }

        // Mark all as read event
        const markAllBtn = document.getElementById('markAllRead');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }

        // Filter events
        const filterBtns = document.querySelectorAll('.filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                filterBtns.forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.filterNotifications(e.target.dataset.filter);
            });
        });

        // Load more event
        const loadMoreBtn = document.getElementById('loadMoreNotifications');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', () => this.loadMoreNotifications());
        }

        // Click outside to close panel
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('notificationPanel');
            const bell = document.getElementById('notificationBell');
            
            if (panel && !panel.contains(e.target) && !bell.contains(e.target)) {
                this.hideNotificationPanel();
            }
        });

        // Page visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.isInitialized) {
                this.loadNotifications();
            }
        });
    }

    async loadNotifications(unreadOnly = false) {
        try {
            const response = await fetch(`${this.config.API_BASE}?action=get&unread_only=${unreadOnly}&limit=${this.config.MAX_NOTIFICATIONS}`, {
                method: 'GET',
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.notifications = data.notifications || [];
                    this.updateNotificationBadge(data.stats?.unread || 0);
                    this.renderNotifications();
                }
            } else {
                console.error('Failed to load notifications:', response.statusText);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch(`${this.config.API_BASE}?action=mark_read`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: notificationId }),
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    // Update local notification
                    const notification = this.notifications.find(n => n.id === notificationId);
                    if (notification) {
                        notification.read = true;
                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                        this.updateNotificationBadge(this.unreadCount);
                        this.renderNotifications();
                    }
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            const unreadNotifications = this.notifications.filter(n => !n.read);
            
            for (const notification of unreadNotifications) {
                await this.markAsRead(notification.id);
            }
            
            this.showToast('Todas las notificaciones marcadas como leídas', 'success');
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
            this.showToast('Error al marcar notificaciones como leídas', 'error');
        }
    }

    async deleteNotification(notificationId) {
        try {
            const response = await fetch(`${this.config.API_BASE}?action=delete&notification_id=${notificationId}`, {
                method: 'DELETE',
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    // Remove from local array
                    this.notifications = this.notifications.filter(n => n.id !== notificationId);
                    this.renderNotifications();
                    this.showToast('Notificación eliminada', 'success');
                }
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
            this.showToast('Error al eliminar notificación', 'error');
        }
    }

    renderNotifications() {
        const container = document.getElementById('notificationList');
        if (!container) return;

        if (this.notifications.length === 0) {
            container.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No hay notificaciones</p>
                </div>
            `;
            return;
        }

        const notificationsHtml = this.notifications.map(notification => 
            this.createNotificationHtml(notification)
        ).join('');

        container.innerHTML = notificationsHtml;

        // Bind individual notification events
        this.bindNotificationEvents();
    }

    createNotificationHtml(notification) {
        const timeAgo = this.getTimeAgo(new Date(notification.created_at));
        const priorityClass = this.getPriorityClass(notification.priority);
        const typeIcon = this.getTypeIcon(notification.type);
        const readClass = notification.read ? 'read' : 'unread';

        return `
            <div class="notification-item ${readClass} ${priorityClass}" data-id="${notification.id}">
                <div class="notification-icon">
                    <i class="${typeIcon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                    <div class="notification-message">${this.escapeHtml(notification.message)}</div>
                    <div class="notification-meta">
                        <span class="notification-time">${timeAgo}</span>
                        ${notification.task_id ? `<span class="notification-task">Tarea relacionada</span>` : ''}
                    </div>
                </div>
                <div class="notification-actions">
                    ${!notification.read ? `<button class="btn-mark-read" title="Marcar como leída">
                        <i class="fas fa-check"></i>
                    </button>` : ''}
                    <button class="btn-delete" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }

    bindNotificationEvents() {
        // Mark as read events
        document.querySelectorAll('.btn-mark-read').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const notificationId = e.target.closest('.notification-item').dataset.id;
                this.markAsRead(notificationId);
            });
        });

        // Delete events
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const notificationId = e.target.closest('.notification-item').dataset.id;
                if (confirm('¿Estás seguro de que quieres eliminar esta notificación?')) {
                    this.deleteNotification(notificationId);
                }
            });
        });

        // Click to mark as read
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', () => {
                this.markAsRead(item.dataset.id);
            });
        });
    }

    filterNotifications(filter) {
        const items = document.querySelectorAll('.notification-item');
        
        items.forEach(item => {
            const notification = this.notifications.find(n => n.id === item.dataset.id);
            let show = true;

            switch (filter) {
                case 'unread':
                    show = !notification.read;
                    break;
                case 'high':
                    show = notification.priority === 'high' || notification.priority === 'urgent';
                    break;
                case 'all':
                default:
                    show = true;
                    break;
            }

            item.style.display = show ? 'flex' : 'none';
        });
    }

    toggleNotificationPanel() {
        const panel = document.getElementById('notificationPanel');
        if (panel) {
            const isVisible = panel.classList.contains('show');
            if (isVisible) {
                this.hideNotificationPanel();
            } else {
                this.showNotificationPanel();
            }
        }
    }

    showNotificationPanel() {
        const panel = document.getElementById('notificationPanel');
        if (panel) {
            panel.classList.add('show');
            this.loadNotifications(); // Refresh when opening
        }
    }

    hideNotificationPanel() {
        const panel = document.getElementById('notificationPanel');
        if (panel) {
            panel.classList.remove('show');
        }
    }

    updateNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            this.unreadCount = count;
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
    }

    showToast(message, type = 'info', duration = null) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="${this.getTypeIcon(type)}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
            <button class="toast-close">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(toast);

        // Show animation
        setTimeout(() => toast.classList.add('show'), 100);

        // Close button event
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.removeToast(toast));

        // Auto remove
        const toastDuration = duration || this.config.NOTIFICATION_DURATION;
        setTimeout(() => this.removeToast(toast), toastDuration);
    }

    removeToast(toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    startPeriodicRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }

        this.refreshInterval = setInterval(() => {
            if (!document.hidden) {
                this.loadNotifications();
            }
        }, this.config.REFRESH_INTERVAL);
    }

    stopPeriodicRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    // Utility methods
    getTimeAgo(date) {
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) {
            return 'Hace un momento';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `Hace ${minutes} minuto${minutes > 1 ? 's' : ''}`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `Hace ${hours} hora${hours > 1 ? 's' : ''}`;
        } else {
            const days = Math.floor(diffInSeconds / 86400);
            return `Hace ${days} día${days > 1 ? 's' : ''}`;
        }
    }

    getPriorityClass(priority) {
        const classes = {
            'low': 'priority-low',
            'medium': 'priority-medium',
            'high': 'priority-high',
            'urgent': 'priority-urgent'
        };
        return classes[priority] || 'priority-medium';
    }

    getTypeIcon(type) {
        const icons = {
            'info': 'fas fa-info-circle',
            'warning': 'fas fa-exclamation-triangle',
            'error': 'fas fa-exclamation-circle',
            'success': 'fas fa-check-circle',
            'task_due': 'fas fa-clock',
            'task_overdue': 'fas fa-exclamation-triangle'
        };
        return icons[type] || 'fas fa-bell';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Public API methods
    async refresh() {
        await this.loadNotifications();
    }

    destroy() {
        this.stopPeriodicRefresh();
        
        // Remove event listeners
        const panel = document.getElementById('notificationPanel');
        if (panel) {
            panel.remove();
        }
        
        const bell = document.getElementById('notificationBell');
        if (bell) {
            bell.remove();
        }
        
        const toastContainer = document.getElementById('toastContainer');
        if (toastContainer) {
            toastContainer.remove();
        }
    }
}

// Global notification instance
let notificationSystem = null;

// Initialize notification system when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    notificationSystem = new NotificationSystem();
    window.notificationSystem = notificationSystem;
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationSystem;
}