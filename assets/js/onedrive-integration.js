/**
 * Microsoft OneDrive Integration for BIG PLAN
 * Provides seamless access to OneDrive files and folders
 */

class OneDriveManager {
    constructor() {
        this.clientId = 'your-onedrive-client-id'; // Configure in production
        this.redirectUri = window.location.origin + '/auth/onedrive-callback';
        this.scopes = 'files.readwrite offline_access';
        this.accessToken = null;
        this.isAuthenticated = false;
        
        this.init();
    }
    
    init() {
        this.loadStoredToken();
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        // OneDrive button click handler
        document.addEventListener('click', (e) => {
            if (e.target.closest('.onedrive-btn') || e.target.closest('[onclick*="openOneDrive"]')) {
                e.preventDefault();
                this.openOneDriveInterface();
            }
        });
    }
    
    loadStoredToken() {
        const storedToken = localStorage.getItem('onedrive_access_token');
        const tokenExpiry = localStorage.getItem('onedrive_token_expiry');
        
        if (storedToken && tokenExpiry && new Date().getTime() < parseInt(tokenExpiry)) {
            this.accessToken = storedToken;
            this.isAuthenticated = true;
        }
    }
    
    async authenticate() {
        if (this.isAuthenticated) {
            return true;
        }
        
        try {
            // For demo purposes, we'll simulate authentication
            // In production, implement proper OAuth2 flow
            const authUrl = `https://login.microsoftonline.com/common/oauth2/v2.0/authorize?` +
                `client_id=${this.clientId}&` +
                `response_type=code&` +
                `redirect_uri=${encodeURIComponent(this.redirectUri)}&` +
                `scope=${encodeURIComponent(this.scopes)}&` +
                `response_mode=query`;
            
            // Open authentication popup
            const popup = window.open(authUrl, 'onedrive-auth', 'width=600,height=700');
            
            return new Promise((resolve, reject) => {
                const checkClosed = setInterval(() => {
                    if (popup.closed) {
                        clearInterval(checkClosed);
                        // Check if authentication was successful
                        this.loadStoredToken();
                        resolve(this.isAuthenticated);
                    }
                }, 1000);
                
                // Timeout after 5 minutes
                setTimeout(() => {
                    clearInterval(checkClosed);
                    if (!popup.closed) {
                        popup.close();
                    }
                    reject(new Error('Authentication timeout'));
                }, 300000);
            });
            
        } catch (error) {
            console.error('OneDrive authentication error:', error);
            return false;
        }
    }
    
    async openOneDriveInterface() {
        // Show OneDrive modal
        this.showOneDriveModal();
        
        if (!this.isAuthenticated) {
            // Show authentication required message
            this.showAuthenticationRequired();
            return;
        }
        
        // Load OneDrive files
        await this.loadOneDriveFiles();
    }
    
    showOneDriveModal() {
        // Remove existing modal if any
        const existingModal = document.getElementById('oneDriveModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create OneDrive modal
        const modal = document.createElement('div');
        modal.id = 'oneDriveModal';
        modal.className = 'modal onedrive-modal';
        modal.innerHTML = `
            <div class="modal-content onedrive-content">
                <div class="modal-header">
                    <h2>
                        <i class="fab fa-microsoft"></i>
                        Microsoft OneDrive
                    </h2>
                    <button class="close-btn" onclick="closeOneDriveModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="onedrive-toolbar">
                    <div class="breadcrumb">
                        <span class="breadcrumb-item active">
                            <i class="fas fa-home"></i>
                            Mi OneDrive
                        </span>
                    </div>
                    
                    <div class="onedrive-actions">
                        <button class="action-btn" onclick="refreshOneDrive()">
                            <i class="fas fa-sync-alt"></i>
                            Actualizar
                        </button>
                        <button class="action-btn" onclick="uploadToOneDrive()">
                            <i class="fas fa-upload"></i>
                            Subir
                        </button>
                        <button class="action-btn" onclick="createOneDriveFolder()">
                            <i class="fas fa-folder-plus"></i>
                            Nueva Carpeta
                        </button>
                    </div>
                </div>
                
                <div class="onedrive-content-area">
                    <div id="oneDriveFiles" class="files-grid">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Cargando archivos...</p>
                        </div>
                    </div>
                </div>
                
                <div class="onedrive-footer">
                    <div class="storage-info">
                        <div class="storage-bar">
                            <div class="storage-used" style="width: 45%"></div>
                        </div>
                        <span class="storage-text">4.5 GB de 10 GB utilizados</span>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.style.display = 'flex';
        
        // Add modal styles if not already added
        this.addOneDriveStyles();
    }
    
    showAuthenticationRequired() {
        const filesContainer = document.getElementById('oneDriveFiles');
        if (!filesContainer) return;
        
        filesContainer.innerHTML = `
            <div class="auth-required">
                <div class="auth-icon">
                    <i class="fab fa-microsoft"></i>
                </div>
                <h3>Conectar con OneDrive</h3>
                <p>Para acceder a tus archivos de OneDrive, necesitas autenticarte con tu cuenta de Microsoft.</p>
                <button class="auth-btn" onclick="authenticateOneDrive()">
                    <i class="fab fa-microsoft"></i>
                    Conectar con Microsoft
                </button>
                <div class="demo-note">
                    <p><strong>Nota:</strong> En esta demo, se simulará la conexión con OneDrive.</p>
                </div>
            </div>
        `;
    }
    
    async loadOneDriveFiles() {
        const filesContainer = document.getElementById('oneDriveFiles');
        if (!filesContainer) return;
        
        try {
            // In production, make actual API call to Microsoft Graph
            // For demo, we'll simulate OneDrive files
            const demoFiles = await this.getDemoFiles();
            
            filesContainer.innerHTML = '';
            
            if (demoFiles.length === 0) {
                filesContainer.innerHTML = `
                    <div class="empty-folder">
                        <i class="fas fa-folder-open"></i>
                        <p>Esta carpeta está vacía</p>
                    </div>
                `;
                return;
            }
            
            demoFiles.forEach(file => {
                const fileElement = this.createFileElement(file);
                filesContainer.appendChild(fileElement);
            });
            
        } catch (error) {
            console.error('Error loading OneDrive files:', error);
            filesContainer.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error al cargar los archivos</p>
                    <button onclick="refreshOneDrive()">Reintentar</button>
                </div>
            `;
        }
    }
    
    async getDemoFiles() {
        // Simulate API delay
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        return [
            {
                id: '1',
                name: 'Proyectos BIG PLAN',
                type: 'folder',
                size: null,
                modified: new Date('2024-01-15'),
                icon: 'fas fa-folder'
            },
            {
                id: '2',
                name: 'Documentos Empresas',
                type: 'folder',
                size: null,
                modified: new Date('2024-01-14'),
                icon: 'fas fa-folder'
            },
            {
                id: '3',
                name: 'Reporte Mensual.xlsx',
                type: 'file',
                size: 2.5,
                modified: new Date('2024-01-13'),
                icon: 'fas fa-file-excel'
            },
            {
                id: '4',
                name: 'Presentación Cliente.pptx',
                type: 'file',
                size: 15.2,
                modified: new Date('2024-01-12'),
                icon: 'fas fa-file-powerpoint'
            },
            {
                id: '5',
                name: 'Contrato Servicios.pdf',
                type: 'file',
                size: 1.8,
                modified: new Date('2024-01-11'),
                icon: 'fas fa-file-pdf'
            },
            {
                id: '6',
                name: 'Notas Reunión.docx',
                type: 'file',
                size: 0.5,
                modified: new Date('2024-01-10'),
                icon: 'fas fa-file-word'
            }
        ];
    }
    
    createFileElement(file) {
        const fileDiv = document.createElement('div');
        fileDiv.className = 'file-item';
        fileDiv.dataset.fileId = file.id;
        fileDiv.dataset.fileType = file.type;
        
        const sizeText = file.size ? `${file.size} MB` : '';
        const modifiedText = file.modified.toLocaleDateString('es-ES');
        
        fileDiv.innerHTML = `
            <div class="file-icon">
                <i class="${file.icon}"></i>
            </div>
            <div class="file-info">
                <div class="file-name">${file.name}</div>
                <div class="file-meta">
                    ${sizeText} ${sizeText && modifiedText ? '•' : ''} ${modifiedText}
                </div>
            </div>
            <div class="file-actions">
                <button class="file-action-btn" onclick="downloadOneDriveFile('${file.id}')" title="Descargar">
                    <i class="fas fa-download"></i>
                </button>
                <button class="file-action-btn" onclick="shareOneDriveFile('${file.id}')" title="Compartir">
                    <i class="fas fa-share-alt"></i>
                </button>
                <button class="file-action-btn" onclick="showOneDriveFileMenu('${file.id}')" title="Más opciones">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
        `;
        
        // Add double-click handler
        fileDiv.addEventListener('dblclick', () => {
            if (file.type === 'folder') {
                this.openFolder(file.id);
            } else {
                this.openFile(file.id);
            }
        });
        
        return fileDiv;
    }
    
    addOneDriveStyles() {
        if (document.getElementById('onedrive-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'onedrive-styles';
        style.textContent = `
            .onedrive-modal .modal-content {
                width: 90%;
                max-width: 1000px;
                height: 80vh;
                max-height: 700px;
            }
            
            .onedrive-content {
                display: flex;
                flex-direction: column;
            }
            
            .onedrive-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                border-bottom: 1px solid #e1e5e9;
                background: #f8f9fa;
            }
            
            .breadcrumb {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .breadcrumb-item {
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 5px 10px;
                border-radius: 5px;
                font-size: 0.9rem;
                color: #666;
            }
            
            .breadcrumb-item.active {
                background: #e3f2fd;
                color: #1976d2;
            }
            
            .onedrive-actions {
                display: flex;
                gap: 10px;
            }
            
            .action-btn {
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 5px;
                background: white;
                color: #333;
                font-size: 0.9rem;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .action-btn:hover {
                background: #f0f0f0;
                border-color: #ccc;
            }
            
            .onedrive-content-area {
                flex: 1;
                padding: 20px;
                overflow-y: auto;
            }
            
            .files-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }
            
            .file-item {
                display: flex;
                align-items: center;
                padding: 12px;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                background: white;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .file-item:hover {
                border-color: #0078d4;
                box-shadow: 0 2px 8px rgba(0, 120, 212, 0.1);
            }
            
            .file-icon {
                margin-right: 12px;
                font-size: 1.5rem;
                color: #0078d4;
            }
            
            .file-info {
                flex: 1;
                min-width: 0;
            }
            
            .file-name {
                font-weight: 500;
                color: #333;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .file-meta {
                font-size: 0.8rem;
                color: #666;
                margin-top: 2px;
            }
            
            .file-actions {
                display: flex;
                gap: 5px;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .file-item:hover .file-actions {
                opacity: 1;
            }
            
            .file-action-btn {
                padding: 5px;
                border: none;
                border-radius: 3px;
                background: transparent;
                color: #666;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .file-action-btn:hover {
                background: #f0f0f0;
                color: #333;
            }
            
            .onedrive-footer {
                padding: 15px 20px;
                border-top: 1px solid #e1e5e9;
                background: #f8f9fa;
            }
            
            .storage-info {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .storage-bar {
                width: 200px;
                height: 6px;
                background: #e1e5e9;
                border-radius: 3px;
                overflow: hidden;
            }
            
            .storage-used {
                height: 100%;
                background: #0078d4;
                transition: width 0.3s ease;
            }
            
            .storage-text {
                font-size: 0.8rem;
                color: #666;
            }
            
            .loading-spinner {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 40px;
                color: #666;
            }
            
            .loading-spinner i {
                font-size: 2rem;
                margin-bottom: 10px;
                color: #0078d4;
            }
            
            .auth-required {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 40px;
                text-align: center;
            }
            
            .auth-icon {
                font-size: 4rem;
                color: #0078d4;
                margin-bottom: 20px;
            }
            
            .auth-required h3 {
                margin-bottom: 10px;
                color: #333;
            }
            
            .auth-required p {
                color: #666;
                margin-bottom: 20px;
                max-width: 400px;
            }
            
            .auth-btn {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 12px 24px;
                background: #0078d4;
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 1rem;
                cursor: pointer;
                transition: background 0.3s ease;
            }
            
            .auth-btn:hover {
                background: #106ebe;
            }
            
            .demo-note {
                margin-top: 20px;
                padding: 15px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 5px;
                color: #856404;
                font-size: 0.9rem;
            }
            
            .empty-folder, .error-message {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 40px;
                color: #666;
                text-align: center;
            }
            
            .empty-folder i, .error-message i {
                font-size: 3rem;
                margin-bottom: 15px;
                color: #ccc;
            }
            
            .error-message i {
                color: #f44336;
            }
        `;
        
        document.head.appendChild(style);
    }
    
    async openFolder(folderId) {
        console.log('Opening folder:', folderId);
        // Implement folder navigation
    }
    
    async openFile(fileId) {
        console.log('Opening file:', fileId);
        // Implement file opening
    }
}

// Global functions for HTML onclick handlers
function closeOneDriveModal() {
    const modal = document.getElementById('oneDriveModal');
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
}

function authenticateOneDrive() {
    // Simulate authentication for demo
    localStorage.setItem('onedrive_access_token', 'demo_token_' + Date.now());
    localStorage.setItem('onedrive_token_expiry', (Date.now() + 3600000).toString());
    
    window.oneDriveManager.isAuthenticated = true;
    window.oneDriveManager.loadOneDriveFiles();
    
    // Show success notification
    if (window.dashboard) {
        window.dashboard.showNotification('¡Conectado con OneDrive exitosamente!', 'success');
    }
}

function refreshOneDrive() {
    if (window.oneDriveManager) {
        window.oneDriveManager.loadOneDriveFiles();
    }
}

function uploadToOneDrive() {
    console.log('Upload to OneDrive');
    // Implement file upload
}

function createOneDriveFolder() {
    console.log('Create OneDrive folder');
    // Implement folder creation
}

function downloadOneDriveFile(fileId) {
    console.log('Download file:', fileId);
    // Implement file download
}

function shareOneDriveFile(fileId) {
    console.log('Share file:', fileId);
    // Implement file sharing
}

function showOneDriveFileMenu(fileId) {
    console.log('Show file menu:', fileId);
    // Implement context menu
}

// Initialize OneDrive manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.oneDriveManager = new OneDriveManager();
});

// Export for use in other modules
window.OneDriveManager = OneDriveManager;