class ReportsManager {
    constructor() {
        this.currentReport = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadCompaniesAndUsers();
    }

    setupEventListeners() {
        // Botón para generar reportes
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="generate-report"]')) {
                this.generateReport();
            }
            if (e.target.matches('[data-action="export-report"]')) {
                this.exportReport();
            }
            if (e.target.matches('[data-action="print-report"]')) {
                this.printReport();
            }
        });

        // Cambio de tipo de reporte
        document.addEventListener('change', (e) => {
            if (e.target.matches('#reportType')) {
                this.onReportTypeChange();
            }
        });
    }

    async loadCompaniesAndUsers() {
        try {
            // Cargar empresas
            const companiesResponse = await fetch('/api/companies/list.php');
            const companiesData = await companiesResponse.json();
            
            if (companiesData.success) {
                this.populateCompanySelect(companiesData.companies);
            }

            // Cargar usuarios
            const usersResponse = await fetch('/api/admin/users.php');
            const usersData = await usersResponse.json();
            
            if (usersData.success) {
                this.populateUserSelect(usersData.users);
            }
        } catch (error) {
            console.error('Error cargando datos:', error);
        }
    }

    populateCompanySelect(companies) {
        const select = document.getElementById('companyFilter');
        if (!select) return;

        select.innerHTML = '<option value="">Todas las empresas</option>';
        companies.forEach(company => {
            const option = document.createElement('option');
            option.value = company.id;
            option.textContent = company.name;
            select.appendChild(option);
        });
    }

    populateUserSelect(users) {
        const select = document.getElementById('userFilter');
        if (!select) return;

        select.innerHTML = '<option value="">Todos los usuarios</option>';
        users.filter(user => user.role !== 'admin').forEach(user => {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = user.name;
            select.appendChild(option);
        });
    }

    onReportTypeChange() {
        const reportType = document.getElementById('reportType').value;
        const companyFilter = document.getElementById('companyFilter').parentElement;
        const userFilter = document.getElementById('userFilter').parentElement;

        // Mostrar/ocultar filtros según el tipo de reporte
        switch (reportType) {
            case 'company':
                companyFilter.style.display = 'block';
                userFilter.style.display = 'none';
                break;
            case 'user':
                companyFilter.style.display = 'none';
                userFilter.style.display = 'block';
                break;
            default:
                companyFilter.style.display = 'block';
                userFilter.style.display = 'block';
        }
    }

    async generateReport() {
        const reportType = document.getElementById('reportType').value;
        const companyId = document.getElementById('companyFilter').value;
        const userId = document.getElementById('userFilter').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        if (!reportType) {
            this.showNotification('Por favor selecciona un tipo de reporte', 'error');
            return;
        }

        this.showLoading(true);

        try {
            const params = new URLSearchParams({
                type: reportType,
                ...(companyId && { company_id: companyId }),
                ...(userId && { user_id: userId }),
                ...(startDate && { start_date: startDate }),
                ...(endDate && { end_date: endDate })
            });

            const response = await fetch(`/api/admin/reports.php?${params}`);
            const data = await response.json();

            if (data.success) {
                this.currentReport = data.report;
                this.displayReport(data.report, reportType);
                this.showNotification('Reporte generado exitosamente', 'success');
            } else {
                throw new Error(data.error || 'Error al generar reporte');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Error al generar reporte: ' + error.message, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    displayReport(report, type) {
        const container = document.getElementById('reportResults');
        if (!container) return;

        let html = '';

        switch (type) {
            case 'general':
                html = this.renderGeneralReport(report);
                break;
            case 'company':
                html = this.renderCompanyReport(report);
                break;
            case 'user':
                html = this.renderUserReport(report);
                break;
            case 'time':
                html = this.renderTimeReport(report);
                break;
            case 'cost':
                html = this.renderCostReport(report);
                break;
        }

        container.innerHTML = html;
        container.style.display = 'block';
    }

    renderGeneralReport(report) {
        const summary = report.summary;
        
        return `
            <div class="report-container">
                <div class="report-header">
                    <h3><i class="fas fa-chart-bar"></i> Reporte General</h3>
                    <div class="report-actions">
                        <button class="btn btn-secondary" data-action="export-report">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                        <button class="btn btn-primary" data-action="print-report">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>

                <div class="report-summary">
                    <div class="summary-cards">
                        <div class="summary-card">
                            <div class="card-icon"><i class="fas fa-tasks"></i></div>
                            <div class="card-content">
                                <h4>${summary.total_tasks}</h4>
                                <p>Total de Tareas</p>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="card-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="card-content">
                                <h4>${summary.completed_tasks}</h4>
                                <p>Completadas</p>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="card-icon"><i class="fas fa-clock"></i></div>
                            <div class="card-content">
                                <h4>${this.formatTime(summary.total_time_spent)}</h4>
                                <p>Tiempo Total</p>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="card-icon"><i class="fas fa-euro-sign"></i></div>
                            <div class="card-content">
                                <h4>€${summary.total_cost.toFixed(2)}</h4>
                                <p>Costo Total</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="report-sections">
                    <div class="report-section">
                        <h4>Por Empresa</h4>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Empresa</th>
                                        <th>Tareas</th>
                                        <th>Tiempo</th>
                                        <th>Costo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${report.by_company.map(company => `
                                        <tr>
                                            <td>${company.company_name}</td>
                                            <td>${company.task_count}</td>
                                            <td>${this.formatTime(company.total_time)}</td>
                                            <td>€${company.total_cost.toFixed(2)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="report-section">
                        <h4>Por Usuario</h4>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Tareas</th>
                                        <th>Tiempo</th>
                                        <th>Costo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${report.by_user.map(user => `
                                        <tr>
                                            <td>${user.user_name}</td>
                                            <td>${user.task_count}</td>
                                            <td>${this.formatTime(user.total_time)}</td>
                                            <td>€${user.total_cost.toFixed(2)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderCompanyReport(report) {
        return `
            <div class="report-container">
                <div class="report-header">
                    <h3><i class="fas fa-building"></i> Reporte por Empresa</h3>
                    <div class="report-actions">
                        <button class="btn btn-secondary" data-action="export-report">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                        <button class="btn btn-primary" data-action="print-report">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>

                <div class="companies-report">
                    ${report.map(company => `
                        <div class="company-section">
                            <div class="company-header">
                                <h4>${company.company_name}</h4>
                                <div class="company-stats">
                                    <span class="stat">
                                        <i class="fas fa-tasks"></i>
                                        ${company.total_tasks} tareas
                                    </span>
                                    <span class="stat">
                                        <i class="fas fa-clock"></i>
                                        ${this.formatTime(company.total_time)}
                                    </span>
                                    <span class="stat">
                                        <i class="fas fa-euro-sign"></i>
                                        €${company.total_cost.toFixed(2)}
                                    </span>
                                </div>
                            </div>

                            <div class="status-breakdown">
                                <div class="status-item">
                                    <span class="status-label pending">Pendientes:</span>
                                    <span class="status-count">${company.status_breakdown.pending}</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label in-progress">En Progreso:</span>
                                    <span class="status-count">${company.status_breakdown.in_progress}</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label completed">Completadas:</span>
                                    <span class="status-count">${company.status_breakdown.completed}</span>
                                </div>
                            </div>

                            <div class="tasks-list">
                                <h5>Tareas Recientes</h5>
                                <div class="table-responsive">
                                    <table class="report-table">
                                        <thead>
                                            <tr>
                                                <th>Título</th>
                                                <th>Estado</th>
                                                <th>Asignado a</th>
                                                <th>Tiempo</th>
                                                <th>Creado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${company.tasks.slice(0, 10).map(task => `
                                                <tr>
                                                    <td>${task.title}</td>
                                                    <td><span class="status-badge ${task.status}">${this.getStatusText(task.status)}</span></td>
                                                    <td>${task.assigned_to}</td>
                                                    <td>${this.formatTime(task.time_spent)}</td>
                                                    <td>${new Date(task.created_at).toLocaleDateString()}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    renderUserReport(report) {
        return `
            <div class="report-container">
                <div class="report-header">
                    <h3><i class="fas fa-users"></i> Reporte por Usuario</h3>
                    <div class="report-actions">
                        <button class="btn btn-secondary" data-action="export-report">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                        <button class="btn btn-primary" data-action="print-report">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>

                <div class="users-report">
                    ${report.map(user => `
                        <div class="user-section">
                            <div class="user-header">
                                <div class="user-info">
                                    <h4>${user.user_name}</h4>
                                    <p>${user.email}</p>
                                </div>
                                <div class="user-stats">
                                    <div class="stat-item">
                                        <span class="stat-value">${user.total_tasks}</span>
                                        <span class="stat-label">Tareas</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value">${this.formatTime(user.total_time)}</span>
                                        <span class="stat-label">Tiempo</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value">${user.productivity_score}%</span>
                                        <span class="stat-label">Productividad</span>
                                    </div>
                                </div>
                            </div>

                            <div class="user-breakdown">
                                <div class="status-chart">
                                    <div class="chart-item">
                                        <div class="chart-bar pending" style="width: ${(user.status_breakdown.pending / user.total_tasks) * 100}%"></div>
                                        <span>Pendientes: ${user.status_breakdown.pending}</span>
                                    </div>
                                    <div class="chart-item">
                                        <div class="chart-bar in-progress" style="width: ${(user.status_breakdown.in_progress / user.total_tasks) * 100}%"></div>
                                        <span>En Progreso: ${user.status_breakdown.in_progress}</span>
                                    </div>
                                    <div class="chart-item">
                                        <div class="chart-bar completed" style="width: ${(user.status_breakdown.completed / user.total_tasks) * 100}%"></div>
                                        <span>Completadas: ${user.status_breakdown.completed}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="recent-tasks">
                                <h5>Tareas Recientes</h5>
                                <div class="tasks-grid">
                                    ${user.recent_tasks.map(task => `
                                        <div class="task-card">
                                            <h6>${task.title}</h6>
                                            <p class="task-company">${task.company_name}</p>
                                            <div class="task-meta">
                                                <span class="status-badge ${task.status}">${this.getStatusText(task.status)}</span>
                                                <span class="task-time">${this.formatTime(task.time_spent)}</span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    renderTimeReport(report) {
        return `
            <div class="report-container">
                <div class="report-header">
                    <h3><i class="fas fa-clock"></i> Reporte de Tiempo</h3>
                    <div class="report-actions">
                        <button class="btn btn-secondary" data-action="export-report">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                        <button class="btn btn-primary" data-action="print-report">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>

                <div class="time-summary">
                    <div class="summary-card">
                        <h4>Tiempo Promedio por Tarea</h4>
                        <p class="big-number">${this.formatTime(report.average_task_time)}</p>
                    </div>
                </div>

                <div class="time-breakdown">
                    <h4>Distribución Diaria</h4>
                    <div class="daily-chart">
                        ${Object.entries(report.daily_breakdown).map(([date, time]) => `
                            <div class="day-item">
                                <span class="day-date">${new Date(date).toLocaleDateString()}</span>
                                <div class="day-bar">
                                    <div class="bar-fill" style="width: ${(time / Math.max(...Object.values(report.daily_breakdown))) * 100}%"></div>
                                </div>
                                <span class="day-time">${this.formatTime(time)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <div class="top-tasks">
                    <h4>Tareas que Más Tiempo Consumen</h4>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Tarea</th>
                                    <th>Empresa</th>
                                    <th>Tiempo</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${report.most_time_consuming_tasks.map(task => `
                                    <tr>
                                        <td>${task.title}</td>
                                        <td>${task.company_name}</td>
                                        <td>${this.formatTime(task.time_spent)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    renderCostReport(report) {
        return `
            <div class="report-container">
                <div class="report-header">
                    <h3><i class="fas fa-euro-sign"></i> Reporte de Costos</h3>
                    <div class="report-actions">
                        <button class="btn btn-secondary" data-action="export-report">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                        <button class="btn btn-primary" data-action="print-report">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>

                <div class="cost-summary">
                    <div class="summary-card">
                        <h4>Costo Total</h4>
                        <p class="big-number">€${report.total_cost.toFixed(2)}</p>
                    </div>
                </div>

                <div class="cost-breakdown">
                    <div class="breakdown-section">
                        <h4>Costos por Empresa</h4>
                        <div class="cost-chart">
                            ${Object.entries(report.cost_by_company).map(([company, cost]) => `
                                <div class="cost-item">
                                    <span class="company-name">${company}</span>
                                    <div class="cost-bar">
                                        <div class="bar-fill" style="width: ${(cost / report.total_cost) * 100}%"></div>
                                    </div>
                                    <span class="cost-amount">€${cost.toFixed(2)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>

                    <div class="breakdown-section">
                        <h4>Costos por Mes</h4>
                        <div class="monthly-chart">
                            ${Object.entries(report.cost_by_month).map(([month, cost]) => `
                                <div class="month-item">
                                    <span class="month-label">${month}</span>
                                    <span class="month-cost">€${cost.toFixed(2)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>

                <div class="expensive-tasks">
                    <h4>Tareas Más Costosas</h4>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Tarea</th>
                                    <th>Empresa</th>
                                    <th>Costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${report.most_expensive_tasks.map(task => `
                                    <tr>
                                        <td>${task.title}</td>
                                        <td>${task.company_name}</td>
                                        <td>€${task.cost.toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    formatTime(seconds) {
        if (!seconds) return '0h 0m';
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        
        return `${hours}h ${minutes}m`;
    }

    getStatusText(status) {
        const statusMap = {
            'pending': 'Pendiente',
            'in_progress': 'En Progreso',
            'paused': 'Pausada',
            'completed': 'Completada'
        };
        return statusMap[status] || status;
    }

    exportReport() {
        if (!this.currentReport) {
            this.showNotification('No hay reporte para exportar', 'error');
            return;
        }

        const dataStr = JSON.stringify(this.currentReport, null, 2);
        const dataBlob = new Blob([dataStr], { type: 'application/json' });
        
        const link = document.createElement('a');
        link.href = URL.createObjectURL(dataBlob);
        link.download = `reporte_${new Date().toISOString().split('T')[0]}.json`;
        link.click();
    }

    printReport() {
        const reportContent = document.getElementById('reportResults');
        if (!reportContent) {
            this.showNotification('No hay reporte para imprimir', 'error');
            return;
        }

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Reporte BIG PLAN</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .report-container { max-width: 100%; }
                        .report-header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                        .summary-cards { display: flex; gap: 20px; margin-bottom: 30px; }
                        .summary-card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
                        .report-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        .report-table th, .report-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .report-table th { background-color: #f5f5f5; }
                        .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 12px; }
                        .status-badge.pending { background-color: #ffeaa7; }
                        .status-badge.in-progress { background-color: #74b9ff; color: white; }
                        .status-badge.completed { background-color: #00b894; color: white; }
                        @media print { .report-actions { display: none; } }
                    </style>
                </head>
                <body>
                    ${reportContent.innerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }

    showLoading(show) {
        const button = document.querySelector('[data-action="generate-report"]');
        if (button) {
            if (show) {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            } else {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-chart-bar"></i> Generar Reporte';
            }
        }
    }

    showNotification(message, type) {
        // Implementar sistema de notificaciones
        console.log(`${type.toUpperCase()}: ${message}`);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('reportsSection')) {
        window.reportsManager = new ReportsManager();
    }
});

// Función global para abrir la sección de reportes
function openReportsSection() {
    const reportsHTML = `
        <div class="reports-container">
            <div class="reports-header">
                <h2><i class="fas fa-chart-bar"></i> Sistema de Reportes</h2>
                <p>Genera reportes detallados sobre tareas, tiempo y costos</p>
            </div>

            <div class="report-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="reportType">Tipo de Reporte:</label>
                        <select id="reportType" class="form-control">
                            <option value="">Seleccionar tipo</option>
                            <option value="general">Reporte General</option>
                            <option value="company">Por Empresa</option>
                            <option value="user">Por Usuario</option>
                            <option value="time">Análisis de Tiempo</option>
                            <option value="cost">Análisis de Costos</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="companyFilter">Empresa:</label>
                        <select id="companyFilter" class="form-control">
                            <option value="">Todas las empresas</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="userFilter">Usuario:</label>
                        <select id="userFilter" class="form-control">
                            <option value="">Todos los usuarios</option>
                        </select>
                    </div>
                </div>

                <div class="filter-row">
                    <div class="filter-group">
                        <label for="startDate">Fecha Inicio:</label>
                        <input type="date" id="startDate" class="form-control">
                    </div>

                    <div class="filter-group">
                        <label for="endDate">Fecha Fin:</label>
                        <input type="date" id="endDate" class="form-control">
                    </div>

                    <div class="filter-group">
                        <button class="btn btn-primary" data-action="generate-report">
                            <i class="fas fa-chart-bar"></i> Generar Reporte
                        </button>
                    </div>
                </div>
            </div>

            <div id="reportResults" class="report-results" style="display: none;"></div>
        </div>
    `;

    // Mostrar en modal o sección principal
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.innerHTML = reportsHTML;
        
        // Inicializar el manager de reportes
        if (!window.reportsManager) {
            window.reportsManager = new ReportsManager();
        }
    }
}