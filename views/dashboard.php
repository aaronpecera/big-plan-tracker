<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIG PLAN - Dashboard</title>
    <link rel="stylesheet" href="/assets/css/macos-style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link rel="stylesheet" href="/assets/css/reports.css">
    <link rel="stylesheet" href="/assets/css/chat.css">
    <link rel="stylesheet" href="/assets/css/daily-expression.css">
    <link rel="stylesheet" href="/assets/css/extension-requests.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <!-- Desktop Background -->
    <div class="desktop-background" id="desktopBackground">
        <!-- Dynamic background will be set here -->
    </div>

    <!-- Top Menu Bar (macOS style) -->
    <div class="menu-bar">
        <div class="menu-left">
            <div class="logo">
                <i class="fas fa-cube"></i>
                <span>BIG PLAN</span>
            </div>
            <div class="menu-items">
                <span class="menu-item active">Dashboard</span>
                <span class="menu-item" onclick="openModule('tasks')">Tareas</span>
                <span class="menu-item" onclick="openModule('projects')">Proyectos</span>
                <span class="menu-item" onclick="openModule('companies')">Empresas</span>
                <span class="menu-item" onclick="openModule('reports')">Reportes</span>
                <span class="menu-item" onclick="openReportsSection()">Análisis</span>
                    <span class="menu-item" onclick="openExtensionsSection()">Extensiones</span>
            </div>
        </div>
        <div class="menu-right">
            <div class="system-info">
                <span class="current-time" id="currentTime"></span>
                <span class="current-date" id="currentDate"></span>
            </div>
            <div class="user-menu">
                <img src="/assets/images/default-avatar.png" alt="Usuario" class="user-avatar" id="userAvatar">
                <span class="user-name" id="userName">Usuario</span>
                <div class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="dashboard-content">
        <!-- Daily Expression Widget -->
        <div class="widget daily-expression" id="dailyExpression">
            <div class="widget-header">
                <i class="fas fa-quote-left"></i>
                <h3>Expresión del Día</h3>
            </div>
            <div class="widget-content">
                <p class="expression-text" id="expressionText">
                    "El éxito es la suma de pequeños esfuerzos repetidos día tras día."
                </p>
                <span class="expression-author">- Robert Collier</span>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="widget quick-actions">
            <div class="widget-header">
                <i class="fas fa-bolt"></i>
                <h3>Acciones Rápidas</h3>
            </div>
            <div class="widget-content">
                <div class="action-buttons">
                    <button class="action-btn" onclick="startQuickTask()">
                        <i class="fas fa-play"></i>
                        <span>Iniciar Tarea</span>
                    </button>
                    <button class="action-btn" onclick="pauseCurrentTask()">
                        <i class="fas fa-pause"></i>
                        <span>Pausar</span>
                    </button>
                    <button class="action-btn" onclick="openOneDrive()">
                        <i class="fab fa-microsoft"></i>
                        <span>OneDrive</span>
                    </button>
                    <button class="action-btn" onclick="openChat()">
                        <i class="fas fa-comments"></i>
                        <span>Chat</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Current Tasks by Company -->
        <div class="widget current-tasks">
            <div class="widget-header">
                <i class="fas fa-tasks"></i>
                <h3>Tareas Actuales</h3>
                <button class="refresh-btn" onclick="refreshTasks()">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="widget-content">
                <div class="company-tabs" id="companyTabs">
                    <!-- Dynamic company tabs will be loaded here -->
                </div>
                <div class="tasks-container" id="tasksContainer">
                    <!-- Dynamic tasks will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Urgent Tasks -->
        <div class="widget urgent-tasks">
            <div class="widget-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Tareas Urgentes</h3>
            </div>
            <div class="widget-content">
                <div class="urgent-list" id="urgentTasksList">
                    <!-- Dynamic urgent tasks will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Time Tracking Widget -->
        <div class="widget time-tracking">
            <div class="widget-header">
                <i class="fas fa-clock"></i>
                <h3>Tiempo Actual</h3>
            </div>
            <div class="widget-content">
                <div class="active-task" id="activeTask">
                    <div class="no-active-task">
                        <i class="fas fa-coffee"></i>
                        <p>No hay tarea activa</p>
                        <small>Selecciona una tarea para comenzar</small>
                    </div>
                </div>
                <div class="time-display" id="timeDisplay">
                    <span class="time-value">00:00:00</span>
                    <div class="time-controls">
                        <button class="time-btn start" id="startBtn" onclick="startTimer()">
                            <i class="fas fa-play"></i>
                        </button>
                        <button class="time-btn pause" id="pauseBtn" onclick="pauseTimer()" style="display: none;">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button class="time-btn stop" id="stopBtn" onclick="stopTimer()">
                            <i class="fas fa-stop"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Widget -->
        <div class="widget statistics">
            <div class="widget-header">
                <i class="fas fa-chart-bar"></i>
                <h3>Estadísticas de Hoy</h3>
            </div>
            <div class="widget-content">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="todayTasks">0</div>
                        <div class="stat-label">Tareas Completadas</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="todayHours">0h</div>
                        <div class="stat-label">Horas Trabajadas</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="todayEarnings">€0</div>
                        <div class="stat-label">Ingresos Generados</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="activeTasks">0</div>
                        <div class="stat-label">Tareas Activas</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Profile Modal -->
    <div class="modal" id="profileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Perfil de Usuario</h3>
                <button class="close-btn" onclick="closeModal('profileModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="profile-section">
                    <div class="avatar-section">
                        <img src="/assets/images/default-avatar.png" alt="Avatar" class="profile-avatar" id="profileAvatar">
                        <button class="change-avatar-btn">Cambiar Avatar</button>
                    </div>
                    <div class="profile-form">
                        <div class="form-group">
                            <label>Nombre</label>
                            <input type="text" id="profileName" placeholder="Tu nombre">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="profileEmail" placeholder="tu@email.com" readonly>
                        </div>
                        <div class="form-group">
                            <label>Fondo de Escritorio</label>
                            <div class="background-options">
                                <div class="bg-option" data-bg="default" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)"></div>
                                <div class="bg-option" data-bg="nature" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%)"></div>
                                <div class="bg-option" data-bg="sunset" style="background: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%)"></div>
                                <div class="bg-option" data-bg="ocean" style="background: linear-gradient(135deg, #00cec9 0%, #55a3ff 100%)"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('profileModal')">Cancelar</button>
                <button class="btn btn-primary" onclick="saveProfile()">Guardar Cambios</button>
            </div>
        </div>
    </div>

    <!-- Task Selection Modal -->
    <div class="modal" id="taskSelectionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Seleccionar Tarea</h3>
                <button class="close-btn" onclick="closeModal('taskSelectionModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="task-search">
                    <input type="text" placeholder="Buscar tarea..." id="taskSearch">
                </div>
                <div class="task-list" id="modalTaskList">
                    <!-- Dynamic task list will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/assets/js/dashboard.js"></script>
    <script src="/assets/js/onedrive-integration.js"></script>
    <script src="/assets/js/reports.js"></script>
    <script src="/assets/js/chat.js"></script>
    <script src="/assets/js/daily-expression.js"></script>
    <script src="/assets/js/extension-requests.js"></script>
    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
            updateDateTime();
            loadUserData();
            loadDailyExpression();
            loadCurrentTasks();
            loadUrgentTasks();
            loadTodayStats();
            
            // Update time every second
            setInterval(updateDateTime, 1000);
            
            // Refresh data every 30 seconds
            setInterval(function() {
                loadCurrentTasks();
                loadUrgentTasks();
                loadTodayStats();
            }, 30000);
        });

        function updateDateTime() {
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
            
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('es-ES', timeOptions);
            document.getElementById('currentDate').textContent = now.toLocaleDateString('es-ES', dateOptions);
        }

        function initializeDashboard() {
            // Set user's custom background
            const savedBackground = localStorage.getItem('userBackground') || 'default';
            setDesktopBackground(savedBackground);
            
            // Initialize user menu click handler
            document.querySelector('.user-menu').addEventListener('click', function() {
                document.getElementById('profileModal').style.display = 'flex';
            });
        }

        function setDesktopBackground(bgType) {
            const backgrounds = {
                'default': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'nature': 'linear-gradient(135deg, #74b9ff 0%, #0984e3 100%)',
                'sunset': 'linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%)',
                'ocean': 'linear-gradient(135deg, #00cec9 0%, #55a3ff 100%)'
            };
            
            document.getElementById('desktopBackground').style.background = backgrounds[bgType] || backgrounds['default'];
            localStorage.setItem('userBackground', bgType);
        }

        // Background selection handlers
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.bg-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.bg-option').forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    setDesktopBackground(this.dataset.bg);
                });
            });
        });
    </script>
</body>
</html>