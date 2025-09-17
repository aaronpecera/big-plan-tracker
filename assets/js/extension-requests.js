class ExtensionRequestManager {
    constructor() {
        this.currentPage = 1;
        this.currentFilter = {};
        this.userTasks = [];
        this.init();
    }

    init() {
        this.createFloatingButton();
        this.loadUserTasks();
        this.loadExtensionRequests();
        this.loadStats();
    }

    createFloatingButton() {
        // Solo mostrar para trabajadores
        if (window.userRole === 'admin') return;

        const button = document.createElement('div');
        button.className = 'extension-floating-btn';
        button.innerHTML = `
            <i class="fas fa-clock"></i>
            <div class="floating-tooltip">Solicitar Extensión</div>
        `;
        button.onclick = () => this.openRequestModal();
        document.body.appendChild(button);
    }

    async loadUserTasks() {
        try {
            const response = await fetch('/api/extension-requests.php?action=user_tasks');
            const data = await response.json();
            
            if (data.success) {
                this.userTasks = data.data;
            }
        } catch (error) {
            console.error('Error loading user tasks:', error);
        }
    }

    async loadExtensionRequests(page = 1) {
        try {
            const params = new URLSearchParams({
                action: 'list',
                page: page,
                limit: 20,
                ...this.currentFilter
            });

            const response = await fetch(`/api/extension-requests.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderRequestsList(data.data, data.pagination);
                this.currentPage = page;
            }
        } catch (error) {
            console.error('Error loading extension requests:', error);
            this.showNotification('Error al cargar solicitudes', 'error');
        }
    }

    async loadStats() {
        try {
            const response = await fetch('/api/extension-requests.php?action=stats');
            const data = await response.json();
            
            if (data.success) {
                this.renderStats(data.data);
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    openRequestModal() {
        const modal = document.createElement('div');
        modal.className = 'extension-modal';
        modal.innerHTML = `
            <div class="extension-modal-content">
                <div class="extension-header">
                    <h3><i class="fas fa-clock"></i> Solicitar Extensión de Plazo</h3>
                    <button class="extension-close" onclick="this.closest('.extension-modal').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="extension-body">
                    <form id="extensionRequestForm">
                        <div class="form-section">
                            <h4><i class="fas fa-tasks"></i> Seleccionar Tarea</h4>
                            <select id="taskSelect" required>
                                <option value="">Selecciona una tarea...</option>
                                ${this.userTasks.map(task => `
                                    <option value="${task._id}" data-deadline="${task.deadline}">
                                        ${task.title} (Vence: ${this.formatDate(task.deadline)})
                                    </option>
                                `).join('')}
                            </select>
                        </div>

                        <div class="form-section">
                            <h4><i class="fas fa-calendar-alt"></i> Nueva Fecha Límite</h4>
                            <input type="datetime-local" id="requestedDeadline" required>
                            <div class="deadline-info">
                                <span id="currentDeadline">Fecha actual: -</span>
                                <span id="extensionDays">Extensión: -</span>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4><i class="fas fa-exclamation-triangle"></i> Prioridad</h4>
                            <div class="priority-selector">
                                <label class="priority-option">
                                    <input type="radio" name="priority" value="low" required>
                                    <span class="priority-badge low">Baja</span>
                                </label>
                                <label class="priority-option">
                                    <input type="radio" name="priority" value="medium" required>
                                    <span class="priority-badge medium">Media</span>
                                </label>
                                <label class="priority-option">
                                    <input type="radio" name="priority" value="high" required>
                                    <span class="priority-badge high">Alta</span>
                                </label>
                                <label class="priority-option">
                                    <input type="radio" name="priority" value="urgent" required>
                                    <span class="priority-badge urgent">Urgente</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4><i class="fas fa-comment"></i> Motivo de la Solicitud</h4>
                            <textarea id="reason" placeholder="Explica por qué necesitas una extensión..." required maxlength="500"></textarea>
                            <div class="char-count">
                                <span id="reasonCount">0</span>/500 caracteres
                            </div>
                        </div>

                        <div class="form-section">
                            <h4><i class="fas fa-info-circle"></i> Información Adicional (Opcional)</h4>
                            <textarea id="additionalInfo" placeholder="Cualquier información adicional que pueda ser relevante..." maxlength="300"></textarea>
                            <div class="char-count">
                                <span id="additionalCount">0</span>/300 caracteres
                            </div>
                        </div>
                    </form>
                </div>
                <div class="extension-footer">
                    <div class="footer-buttons">
                        <button type="button" class="btn-secondary" onclick="this.closest('.extension-modal').remove()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn-primary" form="extensionRequestForm">
                            <i class="fas fa-paper-plane"></i> Enviar Solicitud
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        this.setupRequestForm(modal);
    }

    setupRequestForm(modal) {
        const form = modal.querySelector('#extensionRequestForm');
        const taskSelect = modal.querySelector('#taskSelect');
        const deadlineInput = modal.querySelector('#requestedDeadline');
        const reasonTextarea = modal.querySelector('#reason');
        const additionalTextarea = modal.querySelector('#additionalInfo');

        // Configurar fecha mínima
        const now = new Date();
        now.setHours(now.getHours() + 1); // Mínimo 1 hora en el futuro
        deadlineInput.min = now.toISOString().slice(0, 16);

        // Actualizar información cuando se selecciona una tarea
        taskSelect.addEventListener('change', (e) => {
            const selectedOption = e.target.selectedOptions[0];
            if (selectedOption && selectedOption.dataset.deadline) {
                const currentDeadline = new Date(selectedOption.dataset.deadline);
                modal.querySelector('#currentDeadline').textContent = 
                    `Fecha actual: ${this.formatDate(selectedOption.dataset.deadline)}`;
                
                // Establecer fecha mínima como la fecha actual de la tarea
                deadlineInput.min = currentDeadline.toISOString().slice(0, 16);
                deadlineInput.value = '';
                modal.querySelector('#extensionDays').textContent = 'Extensión: -';
            }
        });

        // Calcular días de extensión
        deadlineInput.addEventListener('change', (e) => {
            const selectedTask = taskSelect.selectedOptions[0];
            if (selectedTask && selectedTask.dataset.deadline) {
                const currentDeadline = new Date(selectedTask.dataset.deadline);
                const newDeadline = new Date(e.target.value);
                const diffTime = newDeadline - currentDeadline;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                modal.querySelector('#extensionDays').textContent = 
                    `Extensión: ${diffDays} día${diffDays !== 1 ? 's' : ''}`;
            }
        });

        // Contadores de caracteres
        reasonTextarea.addEventListener('input', (e) => {
            modal.querySelector('#reasonCount').textContent = e.target.value.length;
        });

        additionalTextarea.addEventListener('input', (e) => {
            modal.querySelector('#additionalCount').textContent = e.target.value.length;
        });

        // Envío del formulario
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.submitRequest(form, modal);
        });
    }

    async submitRequest(form, modal) {
        const formData = new FormData(form);
        const data = {
            task_id: formData.get('taskSelect') || document.getElementById('taskSelect').value,
            requested_deadline: document.getElementById('requestedDeadline').value,
            priority: formData.get('priority'),
            reason: document.getElementById('reason').value,
            additional_info: document.getElementById('additionalInfo').value || null
        };

        try {
            const response = await fetch('/api/extension-requests.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Solicitud enviada exitosamente', 'success');
                modal.remove();
                this.loadExtensionRequests();
                this.loadStats();
            } else {
                this.showNotification(result.error || 'Error al enviar solicitud', 'error');
            }
        } catch (error) {
            console.error('Error submitting request:', error);
            this.showNotification('Error al enviar solicitud', 'error');
        }
    }

    openExtensionsSection() {
        const existingSection = document.querySelector('.extensions-section');
        if (existingSection) {
            existingSection.remove();
        }

        const section = document.createElement('div');
        section.className = 'extensions-section';
        section.innerHTML = `
            <div class="section-header">
                <h2><i class="fas fa-clock"></i> Solicitudes de Extensión</h2>
                <div class="section-actions">
                    ${window.userRole !== 'admin' ? `
                        <button class="btn-primary" onclick="extensionManager.openRequestModal()">
                            <i class="fas fa-plus"></i> Nueva Solicitud
                        </button>
                    ` : ''}
                </div>
            </div>

            <div class="extensions-stats" id="extensionsStats">
                <!-- Stats will be loaded here -->
            </div>

            <div class="extensions-filters">
                <div class="filter-group">
                    <label>Estado:</label>
                    <select id="statusFilter">
                        <option value="">Todos</option>
                        <option value="pending">Pendiente</option>
                        <option value="approved">Aprobada</option>
                        <option value="rejected">Rechazada</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Prioridad:</label>
                    <select id="priorityFilter">
                        <option value="">Todas</option>
                        <option value="low">Baja</option>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                </div>
                <button class="btn-secondary" onclick="extensionManager.applyFilters()">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <button class="btn-secondary" onclick="extensionManager.clearFilters()">
                    <i class="fas fa-times"></i> Limpiar
                </button>
            </div>

            <div class="extensions-list" id="extensionsList">
                <!-- Requests will be loaded here -->
            </div>
        `;

        // Reemplazar contenido principal
        const mainContent = document.querySelector('.main-content');
        mainContent.innerHTML = '';
        mainContent.appendChild(section);

        this.loadExtensionRequests();
        this.loadStats();
    }

    renderStats(stats) {
        const statsContainer = document.getElementById('extensionsStats');
        if (!statsContainer) return;

        statsContainer.innerHTML = `
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <div class="stat-value">${stats.pending}</div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-icon"><i class="fas fa-check"></i></div>
                    <div class="stat-content">
                        <div class="stat-value">${stats.approved}</div>
                        <div class="stat-label">Aprobadas</div>
                    </div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-icon"><i class="fas fa-times"></i></div>
                    <div class="stat-content">
                        <div class="stat-value">${stats.rejected}</div>
                        <div class="stat-label">Rechazadas</div>
                    </div>
                </div>
                <div class="stat-card total">
                    <div class="stat-icon"><i class="fas fa-list"></i></div>
                    <div class="stat-content">
                        <div class="stat-value">${stats.total}</div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
            </div>
        `;
    }

    renderRequestsList(requests, pagination) {
        const listContainer = document.getElementById('extensionsList');
        if (!listContainer) return;

        if (requests.length === 0) {
            listContainer.innerHTML = `
                <div class="no-requests">
                    <i class="fas fa-inbox"></i>
                    <h3>No hay solicitudes</h3>
                    <p>No se encontraron solicitudes de extensión.</p>
                </div>
            `;
            return;
        }

        listContainer.innerHTML = `
            <div class="requests-container">
                ${requests.map(request => this.renderRequestCard(request)).join('')}
            </div>
            ${this.renderPagination(pagination)}
        `;
    }

    renderRequestCard(request) {
        const statusClass = request.status;
        const priorityClass = request.priority;
        const canReview = window.userRole === 'admin' && request.status === 'pending';
        const canEdit = window.userRole !== 'admin' && request.user_id === window.userId && request.status === 'pending';

        return `
            <div class="request-card ${statusClass}">
                <div class="request-header">
                    <div class="request-info">
                        <h4>${request.task_title}</h4>
                        ${window.userRole === 'admin' ? `<span class="user-name">${request.user_name}</span>` : ''}
                    </div>
                    <div class="request-badges">
                        <span class="status-badge ${statusClass}">${this.getStatusText(request.status)}</span>
                        <span class="priority-badge ${priorityClass}">${this.getPriorityText(request.priority)}</span>
                    </div>
                </div>

                <div class="request-body">
                    <div class="request-dates">
                        <div class="date-item">
                            <label>Fecha Original:</label>
                            <span>${this.formatDate(request.task_original_deadline)}</span>
                        </div>
                        <div class="date-item">
                            <label>Fecha Solicitada:</label>
                            <span>${this.formatDate(request.requested_deadline)}</span>
                        </div>
                        <div class="date-item extension-days">
                            <label>Extensión:</label>
                            <span>${this.calculateExtensionDays(request.task_original_deadline, request.requested_deadline)} días</span>
                        </div>
                    </div>

                    <div class="request-reason">
                        <label>Motivo:</label>
                        <p>${request.reason}</p>
                    </div>

                    ${request.additional_info ? `
                        <div class="request-additional">
                            <label>Información Adicional:</label>
                            <p>${request.additional_info}</p>
                        </div>
                    ` : ''}

                    ${request.admin_comments ? `
                        <div class="admin-comments">
                            <label>Comentarios del Administrador:</label>
                            <p>${request.admin_comments}</p>
                        </div>
                    ` : ''}
                </div>

                <div class="request-footer">
                    <div class="request-meta">
                        <span>Creada: ${this.formatDate(request.created_at)}</span>
                        ${request.reviewed_at ? `<span>Revisada: ${this.formatDate(request.reviewed_at)}</span>` : ''}
                    </div>
                    <div class="request-actions">
                        ${canReview ? `
                            <button class="btn-success" onclick="extensionManager.reviewRequest('${request._id}', 'approved')">
                                <i class="fas fa-check"></i> Aprobar
                            </button>
                            <button class="btn-danger" onclick="extensionManager.reviewRequest('${request._id}', 'rejected')">
                                <i class="fas fa-times"></i> Rechazar
                            </button>
                        ` : ''}
                        ${canEdit ? `
                            <button class="btn-secondary" onclick="extensionManager.editRequest('${request._id}')">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                        ` : ''}
                        ${(canEdit || window.userRole === 'admin') ? `
                            <button class="btn-danger" onclick="extensionManager.deleteRequest('${request._id}')">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    async reviewRequest(requestId, status) {
        const comments = prompt(`Comentarios para ${status === 'approved' ? 'aprobar' : 'rechazar'} la solicitud:`);
        
        try {
            const response = await fetch('/api/extension-requests.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: requestId,
                    action: 'review',
                    status: status,
                    admin_comments: comments
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(`Solicitud ${status === 'approved' ? 'aprobada' : 'rechazada'} exitosamente`, 'success');
                this.loadExtensionRequests();
                this.loadStats();
            } else {
                this.showNotification(result.error || 'Error al revisar solicitud', 'error');
            }
        } catch (error) {
            console.error('Error reviewing request:', error);
            this.showNotification('Error al revisar solicitud', 'error');
        }
    }

    async deleteRequest(requestId) {
        if (!confirm('¿Estás seguro de que quieres eliminar esta solicitud?')) {
            return;
        }

        try {
            const response = await fetch('/api/extension-requests.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: requestId })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Solicitud eliminada exitosamente', 'success');
                this.loadExtensionRequests();
                this.loadStats();
            } else {
                this.showNotification(result.error || 'Error al eliminar solicitud', 'error');
            }
        } catch (error) {
            console.error('Error deleting request:', error);
            this.showNotification('Error al eliminar solicitud', 'error');
        }
    }

    applyFilters() {
        const statusFilter = document.getElementById('statusFilter').value;
        const priorityFilter = document.getElementById('priorityFilter').value;

        this.currentFilter = {};
        if (statusFilter) this.currentFilter.status = statusFilter;
        if (priorityFilter) this.currentFilter.priority = priorityFilter;

        this.loadExtensionRequests(1);
    }

    clearFilters() {
        document.getElementById('statusFilter').value = '';
        document.getElementById('priorityFilter').value = '';
        this.currentFilter = {};
        this.loadExtensionRequests(1);
    }

    renderPagination(pagination) {
        if (pagination.pages <= 1) return '';

        let paginationHTML = '<div class="pagination">';
        
        // Botón anterior
        if (pagination.page > 1) {
            paginationHTML += `<button onclick="extensionManager.loadExtensionRequests(${pagination.page - 1})">Anterior</button>`;
        }

        // Números de página
        for (let i = 1; i <= pagination.pages; i++) {
            if (i === pagination.page) {
                paginationHTML += `<button class="active">${i}</button>`;
            } else {
                paginationHTML += `<button onclick="extensionManager.loadExtensionRequests(${i})">${i}</button>`;
            }
        }

        // Botón siguiente
        if (pagination.page < pagination.pages) {
            paginationHTML += `<button onclick="extensionManager.loadExtensionRequests(${pagination.page + 1})">Siguiente</button>`;
        }

        paginationHTML += '</div>';
        return paginationHTML;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    calculateExtensionDays(originalDate, newDate) {
        const original = new Date(originalDate);
        const requested = new Date(newDate);
        const diffTime = requested - original;
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    getStatusText(status) {
        const statusTexts = {
            pending: 'Pendiente',
            approved: 'Aprobada',
            rejected: 'Rechazada'
        };
        return statusTexts[status] || status;
    }

    getPriorityText(priority) {
        const priorityTexts = {
            low: 'Baja',
            medium: 'Media',
            high: 'Alta',
            urgent: 'Urgente'
        };
        return priorityTexts[priority] || priority;
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `extension-notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i>
            ${message}
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

// Función global para abrir la sección de extensiones
function openExtensionsSection() {
    if (window.extensionManager) {
        window.extensionManager.openExtensionsSection();
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.extensionManager = new ExtensionRequestManager();
});