/**
 * Dashboard JavaScript for BIG PLAN
 * Handles all dashboard functionality including time tracking, task management, and UI interactions
 */

class DashboardManager {
    constructor() {
        this.currentUser = null;
        this.activeTask = null;
        this.timer = null;
        this.startTime = null;
        this.elapsedTime = 0;
        this.companies = [];
        this.tasks = [];
        this.currentCompany = null;
        
        this.init();
    }
    
    init() {
        this.loadUserSession();
        this.setupEventListeners();
        this.startPeriodicUpdates();
    }
    
    setupEventListeners() {
        // Task action buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.task-action-btn')) {
                this.handleTaskAction(e);
            }
            
            if (e.target.closest('.company-tab')) {
                this.handleCompanyTabClick(e);
            }
            
            if (e.target.closest('.task-item')) {
                this.handleTaskClick(e);
            }
        });
        
        // Modal handlers
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal(e.target.id);
            }
        });
        
        // Background selection
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('bg-option')) {
                this.handleBackgroundChange(e);
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }
    
    startPeriodicUpdates() {
        // Update every 30 seconds
        setInterval(() => {
            this.refreshDashboardData();
        }, 30000);
        
        // Update timer every second
        setInterval(() => {
            this.updateTimerDisplay();
        }, 1000);
    }
    
    async loadUserSession() {
        try {
            const response = await fetch('/api/user/session');
            const data = await response.json();
            
            if (data.success) {
                this.currentUser = data.user;
                this.updateUserDisplay();
            } else {
                window.location.href = '/login';
            }
        } catch (error) {
            console.error('Error loading user session:', error);
            this.showNotification('Error al cargar la sesión', 'error');
        }
    }
    
    updateUserDisplay() {
        if (!this.currentUser) return;
        
        document.getElementById('userName').textContent = this.currentUser.name;
        document.getElementById('userAvatar').src = this.currentUser.avatar || '/assets/images/default-avatar.png';
        
        // Update profile modal
        document.getElementById('profileName').value = this.currentUser.name;
        document.getElementById('profileEmail').value = this.currentUser.email;
        document.getElementById('profileAvatar').src = this.currentUser.avatar || '/assets/images/default-avatar.png';
    }
    
    async loadDailyExpression() {
        try {
            const response = await fetch('/api/admin/daily-expression');
            const data = await response.json();
            
            if (data.success && data.expression) {
                document.getElementById('expressionText').textContent = data.expression.text;
                document.querySelector('.expression-author').textContent = `- ${data.expression.author}`;
            }
        } catch (error) {
            console.error('Error loading daily expression:', error);
        }
    }
    
    async loadCurrentTasks() {
        try {
            const response = await fetch('/api/tasks/user-tasks');
            const data = await response.json();
            
            if (data.success) {
                this.tasks = data.tasks;
                this.companies = data.companies;
                this.renderCompanyTabs();
                this.renderTasks();
            }
        } catch (error) {
            console.error('Error loading tasks:', error);
            this.showNotification('Error al cargar las tareas', 'error');
        }
    }
    
    renderCompanyTabs() {
        const tabsContainer = document.getElementById('companyTabs');
        if (!tabsContainer) return;
        
        tabsContainer.innerHTML = '';
        
        // Add "All" tab
        const allTab = document.createElement('button');
        allTab.className = 'company-tab active';
        allTab.textContent = 'Todas';
        allTab.dataset.companyId = 'all';
        tabsContainer.appendChild(allTab);
        
        // Add company tabs
        this.companies.forEach(company => {
            const tab = document.createElement('button');
            tab.className = 'company-tab';
            tab.textContent = company.name;
            tab.dataset.companyId = company._id;
            tabsContainer.appendChild(tab);
        });
    }
    
    renderTasks() {
        const tasksContainer = document.getElementById('tasksContainer');
        if (!tasksContainer) return;
        
        tasksContainer.innerHTML = '';
        
        let filteredTasks = this.tasks;
        if (this.currentCompany && this.currentCompany !== 'all') {
            filteredTasks = this.tasks.filter(task => task.company_id === this.currentCompany);
        }
        
        if (filteredTasks.length === 0) {
            tasksContainer.innerHTML = `
                <div class="no-tasks">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No hay tareas asignadas</p>
                </div>
            `;
            return;
        }
        
        filteredTasks.forEach(task => {
            const taskElement = this.createTaskElement(task);
            tasksContainer.appendChild(taskElement);
        });
    }
    
    createTaskElement(task) {
        const taskDiv = document.createElement('div');
        taskDiv.className = 'task-item';
        taskDiv.dataset.taskId = task._id;
        
        const statusClass = task.status.toLowerCase().replace('_', '-');
        const statusText = this.getStatusText(task.status);
        
        taskDiv.innerHTML = `
            <div class="task-status ${statusClass}"></div>
            <div class="task-info">
                <h4 class="task-title">${task.title}</h4>
                <div class="task-meta">
                    <span>${statusText}</span>
                    ${task.due_date ? `<span>Vence: ${this.formatDate(task.due_date)}</span>` : ''}
                    ${task.estimated_hours ? `<span>Est: ${task.estimated_hours}h</span>` : ''}
                </div>
            </div>
            <div class="task-actions">
                ${this.getTaskActionButtons(task)}
            </div>
        `;
        
        return taskDiv;
    }
    
    getTaskActionButtons(task) {
        const buttons = [];
        
        switch (task.status) {
            case 'NO_INICIADA':
                buttons.push('<button class="task-action-btn start" data-action="start"><i class="fas fa-play"></i></button>');
                break;
            case 'EN_PROGRESO':
                buttons.push('<button class="task-action-btn pause" data-action="pause"><i class="fas fa-pause"></i></button>');
                buttons.push('<button class="task-action-btn complete" data-action="complete"><i class="fas fa-check"></i></button>');
                break;
            case 'PAUSADA':
                buttons.push('<button class="task-action-btn start" data-action="resume"><i class="fas fa-play"></i></button>');
                buttons.push('<button class="task-action-btn complete" data-action="complete"><i class="fas fa-check"></i></button>');
                break;
        }
        
        return buttons.join('');
    }
    
    getStatusText(status) {
        const statusMap = {
            'NO_INICIADA': 'No iniciada',
            'EN_PROGRESO': 'En progreso',
            'PAUSADA': 'Pausada',
            'COMPLETADA': 'Completada'
        };
        return statusMap[status] || status;
    }
    
    async handleTaskAction(e) {
        e.stopPropagation();
        
        const button = e.target.closest('.task-action-btn');
        const taskItem = button.closest('.task-item');
        const taskId = taskItem.dataset.taskId;
        const action = button.dataset.action;
        
        try {
            const response = await fetch(`/api/tasks/${taskId}/${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification(data.message, 'success');
                
                if (action === 'start' || action === 'resume') {
                    this.startTaskTimer(taskId);
                } else if (action === 'pause' || action === 'complete') {
                    this.stopTaskTimer();
                }
                
                this.loadCurrentTasks();
                this.loadTodayStats();
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error handling task action:', error);
            this.showNotification('Error al procesar la acción', 'error');
        }
    }
    
    handleCompanyTabClick(e) {
        const tab = e.target.closest('.company-tab');
        const companyId = tab.dataset.companyId;
        
        // Update active tab
        document.querySelectorAll('.company-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        this.currentCompany = companyId;
        this.renderTasks();
    }
    
    handleTaskClick(e) {
        if (e.target.closest('.task-action-btn')) return;
        
        const taskItem = e.target.closest('.task-item');
        const taskId = taskItem.dataset.taskId;
        
        // Open task details modal or navigate to task page
        this.openTaskDetails(taskId);
    }
    
    startTaskTimer(taskId) {
        this.activeTask = taskId;
        this.startTime = Date.now();
        this.elapsedTime = 0;
        
        // Update UI
        this.updateActiveTaskDisplay();
        this.updateTimerControls(true);
        
        // Start timer
        this.timer = setInterval(() => {
            this.updateTimerDisplay();
        }, 1000);
    }
    
    stopTaskTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        
        this.activeTask = null;
        this.startTime = null;
        this.elapsedTime = 0;
        
        this.updateActiveTaskDisplay();
        this.updateTimerControls(false);
        this.updateTimerDisplay();
    }
    
    updateTimerDisplay() {
        const timeDisplay = document.getElementById('timeDisplay');
        if (!timeDisplay) return;
        
        let currentTime = 0;
        
        if (this.activeTask && this.startTime) {
            currentTime = this.elapsedTime + (Date.now() - this.startTime);
        }
        
        const hours = Math.floor(currentTime / (1000 * 60 * 60));
        const minutes = Math.floor((currentTime % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((currentTime % (1000 * 60)) / 1000);
        
        const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        const timeValue = timeDisplay.querySelector('.time-value');
        if (timeValue) {
            timeValue.textContent = timeString;
        }
    }
    
    updateActiveTaskDisplay() {
        const activeTaskContainer = document.getElementById('activeTask');
        if (!activeTaskContainer) return;
        
        if (this.activeTask) {
            const task = this.tasks.find(t => t._id === this.activeTask);
            if (task) {
                activeTaskContainer.innerHTML = `
                    <div class="active-task-info">
                        <h4>${task.title}</h4>
                        <p>Trabajando en esta tarea...</p>
                    </div>
                `;
            }
        } else {
            activeTaskContainer.innerHTML = `
                <div class="no-active-task">
                    <i class="fas fa-coffee"></i>
                    <p>No hay tarea activa</p>
                    <small>Selecciona una tarea para comenzar</small>
                </div>
            `;
        }
    }
    
    updateTimerControls(isActive) {
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        
        if (startBtn && pauseBtn) {
            if (isActive) {
                startBtn.style.display = 'none';
                pauseBtn.style.display = 'flex';
            } else {
                startBtn.style.display = 'flex';
                pauseBtn.style.display = 'none';
            }
        }
    }
    
    async loadUrgentTasks() {
        try {
            const response = await fetch('/api/tasks/urgent');
            const data = await response.json();
            
            if (data.success) {
                this.renderUrgentTasks(data.tasks);
            }
        } catch (error) {
            console.error('Error loading urgent tasks:', error);
        }
    }
    
    renderUrgentTasks(urgentTasks) {
        const urgentList = document.getElementById('urgentTasksList');
        if (!urgentList) return;
        
        urgentList.innerHTML = '';
        
        if (urgentTasks.length === 0) {
            urgentList.innerHTML = `
                <div class="no-urgent-tasks">
                    <i class="fas fa-check-circle"></i>
                    <p>No hay tareas urgentes</p>
                </div>
            `;
            return;
        }
        
        urgentTasks.forEach(task => {
            const urgentItem = document.createElement('div');
            urgentItem.className = 'urgent-item';
            urgentItem.innerHTML = `
                <div class="urgent-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="urgent-info">
                    <h4 class="urgent-title">${task.title}</h4>
                    <div class="urgent-deadline">Vence: ${this.formatDate(task.due_date)}</div>
                </div>
            `;
            urgentList.appendChild(urgentItem);
        });
    }
    
    async loadTodayStats() {
        try {
            const response = await fetch('/api/stats/today');
            const data = await response.json();
            
            if (data.success) {
                this.updateStatsDisplay(data.stats);
            }
        } catch (error) {
            console.error('Error loading today stats:', error);
        }
    }
    
    updateStatsDisplay(stats) {
        const elements = {
            todayTasks: document.getElementById('todayTasks'),
            todayHours: document.getElementById('todayHours'),
            todayEarnings: document.getElementById('todayEarnings'),
            activeTasks: document.getElementById('activeTasks')
        };
        
        if (elements.todayTasks) elements.todayTasks.textContent = stats.completed_tasks || 0;
        if (elements.todayHours) elements.todayHours.textContent = `${stats.hours_worked || 0}h`;
        if (elements.todayEarnings) elements.todayEarnings.textContent = `€${stats.earnings || 0}`;
        if (elements.activeTasks) elements.activeTasks.textContent = stats.active_tasks || 0;
    }
    
    async refreshDashboardData() {
        await Promise.all([
            this.loadCurrentTasks(),
            this.loadUrgentTasks(),
            this.loadTodayStats(),
            this.loadDailyExpression()
        ]);
    }
    
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
                <span>${message}</span>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }
    
    handleBackgroundChange(e) {
        const bgType = e.target.dataset.bg;
        document.querySelectorAll('.bg-option').forEach(opt => opt.classList.remove('selected'));
        e.target.classList.add('selected');
        setDesktopBackground(bgType);
    }
}

// Global functions for HTML onclick handlers
function openModule(module) {
    window.location.href = `/views/${module}`;
}

function startQuickTask() {
    document.getElementById('taskSelectionModal').style.display = 'flex';
    loadTaskSelectionModal();
}

function pauseCurrentTask() {
    if (dashboard.activeTask) {
        dashboard.handleTaskAction({
            target: { closest: () => ({ dataset: { action: 'pause' }, closest: () => ({ dataset: { taskId: dashboard.activeTask } }) }) },
            stopPropagation: () => {}
        });
    }
}

function openOneDrive() {
    window.open('https://onedrive.live.com', '_blank');
}

function openChat() {
    window.location.href = '/views/chat';
}

function refreshTasks() {
    dashboard.loadCurrentTasks();
}

function startTimer() {
    if (!dashboard.activeTask) {
        startQuickTask();
    }
}

function pauseTimer() {
    pauseCurrentTask();
}

function stopTimer() {
    dashboard.stopTaskTimer();
}

function closeModal(modalId) {
    dashboard.closeModal(modalId);
}

function saveProfile() {
    // Implement profile saving
    dashboard.closeModal('profileModal');
}

async function loadTaskSelectionModal() {
    try {
        const response = await fetch('/api/tasks/available');
        const data = await response.json();
        
        if (data.success) {
            const taskList = document.getElementById('modalTaskList');
            taskList.innerHTML = '';
            
            data.tasks.forEach(task => {
                const taskItem = document.createElement('div');
                taskItem.className = 'modal-task-item';
                taskItem.innerHTML = `
                    <h4>${task.title}</h4>
                    <p>${task.description}</p>
                    <button onclick="selectTask('${task._id}')">Seleccionar</button>
                `;
                taskList.appendChild(taskItem);
            });
        }
    } catch (error) {
        console.error('Error loading task selection:', error);
    }
}

function selectTask(taskId) {
    dashboard.startTaskTimer(taskId);
    dashboard.closeModal('taskSelectionModal');
}

// Initialize dashboard when DOM is loaded
let dashboard;
document.addEventListener('DOMContentLoaded', function() {
    dashboard = new DashboardManager();
});

// Export for use in other modules
window.DashboardManager = DashboardManager;