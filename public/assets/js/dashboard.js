/**
 * BIG PLAN TRACKER - Dashboard JavaScript
 * MongoDB + Render Version
 */

// Global variables
let activeTimerInterval = null;
let currentActiveTask = null;
let dashboardData = {};

// Configuration
const CONFIG = {
    API_BASE: '/api/',
    REFRESH_INTERVAL: 30000, // 30 seconds
    TIMER_UPDATE_INTERVAL: 1000, // 1 second
    NOTIFICATION_DURATION: 5000 // 5 seconds
};

/**
 * Dashboard class for managing the main interface
 */
class Dashboard {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadInitialData();
        this.startPeriodicUpdates();
        this.updateDateTime();
    }

    bindEvents() {
        // Filter events
        const expiringFilter = document.getElementById('expiringFilter');
        if (expiringFilter) {
            expiringFilter.addEventListener('change', (e) => {
                this.filterExpiringTasks(e.target.value);
            });
        }

        // Form events
        const newTaskForm = document.getElementById('newTaskForm');
        if (newTaskForm) {
            newTaskForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createNewTask();
            });
        }

        // Keyboard events
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });

        // Page visibility events
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refreshData();
            }
        });
    }

    async loadInitialData() {
        try {
            this.showLoading(true);
            await Promise.all([
                this.loadUserProfile(),
                this.loadDashboardStats(),
                this.loadRecentTasks(),
                this.loadProjects()
            ]);
            this.showLoading(false);
        } catch (error) {
            console.error('Error loading initial data:', error);
            this.showNotification('Error loading initial data', 'error');
            this.showLoading(false);
        }
    }

    startPeriodicUpdates() {
        // Update stats every 30 seconds
        setInterval(() => {
            if (!document.hidden) {
                this.loadDashboardStats();
            }
        }, CONFIG.REFRESH_INTERVAL);

        // Update time every second
        setInterval(() => {
            this.updateDateTime();
        }, 1000);
    }

    updateDateTime() {
        const now = new Date();
        const timeOptions = { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: false
        };
        const dateOptions = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        };

        const timeElement = document.getElementById('currentTime');
        const dateElement = document.getElementById('currentDate');

        if (timeElement) {
            timeElement.textContent = now.toLocaleTimeString('es-ES', timeOptions);
        }
        if (dateElement) {
            dateElement.textContent = now.toLocaleDateString('es-ES', dateOptions);
        }
    }

    async loadUserProfile() {
        try {
            const response = await fetch(`${CONFIG.API_BASE}users?action=profile`, {
                method: 'GET',
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateUserDisplay(data.user);
                }
            }
        } catch (error) {
            console.error('Error loading user profile:', error);
        }
    }

    updateUserDisplay(user) {
        const userNameElement = document.getElementById('userName');
        const userRoleElement = document.getElementById('userRole');

        if (userNameElement) {
            userNameElement.textContent = user.name || 'Usuario';
        }
        if (userRoleElement) {
            userRoleElement.textContent = user.role || 'Empleado';
        }
    }

    async loadDashboardStats() {
        try {
            const [projectsResponse, tasksResponse, usersResponse] = await Promise.all([
                fetch(`${CONFIG.API_BASE}projects?action=count`, { credentials: 'include' }),
                fetch(`${CONFIG.API_BASE}tasks?action=count`, { credentials: 'include' }),
                fetch(`${CONFIG.API_BASE}users?action=count`, { credentials: 'include' })
            ]);

            const [projectsData, tasksData, usersData] = await Promise.all([
                projectsResponse.json(),
                tasksResponse.json(),
                usersResponse.json()
            ]);

            this.updateStatsDisplay({
                projects: projectsData.count || 0,
                tasks: tasksData.count || 0,
                users: usersData.count || 0
            });
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
        }
    }

    updateStatsDisplay(stats) {
        const projectsCountElement = document.getElementById('projectsCount');
        const tasksCountElement = document.getElementById('tasksCount');
        const usersCountElement = document.getElementById('usersCount');

        if (projectsCountElement) {
            projectsCountElement.textContent = stats.projects;
        }
        if (tasksCountElement) {
            tasksCountElement.textContent = stats.tasks;
        }
        if (usersCountElement) {
            usersCountElement.textContent = stats.users;
        }
    }

    async loadRecentTasks() {
        try {
            const response = await fetch(`${CONFIG.API_BASE}tasks?action=recent&limit=10`, {
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.renderRecentTasks(data.tasks);
                }
            }
        } catch (error) {
            console.error('Error loading recent tasks:', error);
        }
    }

    renderRecentTasks(tasks) {
        const container = document.getElementById('recentTasksList');
        if (!container) return;

        if (!tasks || tasks.length === 0) {
            container.innerHTML = '<p class="text-center text-secondary">No se encontraron tareas recientes</p>';
            return;
        }

        const tasksHtml = tasks.map(task => this.createTaskItemHtml(task)).join('');
        container.innerHTML = tasksHtml;
    }

    createTaskItemHtml(task) {
        const statusClass = this.getStatusClass(task.status);
        const priorityClass = this.getPriorityClass(task.priority);
        const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Sin fecha límite';

        return `
            <div class="card task-item" data-task-id="${task._id}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-1">${this.escapeHtml(task.title)}</h6>
                            <p class="card-text text-secondary mb-2">${this.escapeHtml(task.description || '')}</p>
                            <div class="d-flex gap-2 mb-2">
                                <span class="status-badge ${statusClass}">${task.status}</span>
                                <span class="badge ${priorityClass}">${task.priority}</span>
                            </div>
                            <small class="text-muted">Vence: ${dueDate}</small>
                        </div>
                        <div class="task-actions">
                            <button class="btn btn-sm btn-outline" onclick="viewTask('${task._id}')">
                                Ver
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async loadProjects() {
        try {
            const response = await fetch(`${CONFIG.API_BASE}projects?action=list&limit=5`, {
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.renderRecentProjects(data.projects);
                }
            }
        } catch (error) {
            console.error('Error loading projects:', error);
        }
    }

    renderRecentProjects(projects) {
        const container = document.getElementById('recentProjectsList');
        if (!container) return;

        if (!projects || projects.length === 0) {
            container.innerHTML = '<p class="text-center text-secondary">No se encontraron proyectos</p>';
            return;
        }

        const projectsHtml = projects.map(project => `
            <div class="card project-item" data-project-id="${project._id}">
                <div class="card-body">
                    <h6 class="card-title">${this.escapeHtml(project.name)}</h6>
                    <p class="card-text text-secondary">${this.escapeHtml(project.description || '')}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="status-badge ${this.getStatusClass(project.status)}">${project.status}</span>
                        <button class="btn btn-sm btn-outline" onclick="viewProject('${project._id}')">
                            Ver
                        </button>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = projectsHtml;
    }

    getStatusClass(status) {
        const statusClasses = {
            'pending': 'status-pending',
            'in_progress': 'status-in-progress',
            'completed': 'status-completed',
            'cancelled': 'status-cancelled'
        };
        return statusClasses[status] || 'status-pending';
    }

    getPriorityClass(priority) {
        const priorityClasses = {
            'low': 'bg-success',
            'medium': 'bg-warning',
            'high': 'bg-danger'
        };
        return priorityClasses[priority] || 'bg-secondary';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showLoading(show) {
        const loader = document.getElementById('dashboardLoader');
        if (loader) {
            loader.style.display = show ? 'block' : 'none';
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
        `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, CONFIG.NOTIFICATION_DURATION);
    }

    filterExpiringTasks(filter) {
        // Implementation for filtering expiring tasks
        console.log('Filtering expiring tasks:', filter);
    }

    async createNewTask() {
        // Implementation for creating new task
        console.log('Creating new task');
    }

    closeAllModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
    }

    async refreshData() {
        await this.loadDashboardStats();
        await this.loadRecentTasks();
        await this.loadProjects();
    }

    async logout() {
        try {
            const response = await fetch(`${CONFIG.API_BASE}auth`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'logout' }),
                credentials: 'include'
            });

            if (response.ok) {
                window.location.href = '/login';
            }
        } catch (error) {
            console.error('Error during logout:', error);
        }
    }
}

// Global functions for backward compatibility
function viewTask(taskId) {
    console.log('Viewing task:', taskId);
    // Implementation for viewing task details
}

function viewProject(projectId) {
    console.log('Viewing project:', projectId);
    // Implementation for viewing project details
}

function logout() {
    if (window.dashboard) {
        window.dashboard.logout();
    }
}

function refreshTasks() {
    if (window.dashboard) {
        window.dashboard.refreshData();
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.dashboard = new Dashboard();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Dashboard;
}