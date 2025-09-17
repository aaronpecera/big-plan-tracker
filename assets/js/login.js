/**
 * Login JavaScript for BIG PLAN
 * Handles authentication and form validation
 */

class LoginManager {
    constructor() {
        this.form = document.getElementById('loginForm');
        this.submitBtn = this.form.querySelector('.login-btn');
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.checkExistingSession();
    }
    
    setupEventListeners() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Add input validation
        const inputs = this.form.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.form.dispatchEvent(new Event('submit'));
            }
        });
    }
    
    async checkExistingSession() {
        try {
            const response = await fetch('/api/user/session');
            const data = await response.json();
            
            if (data.success) {
                // User already logged in, redirect to dashboard
                window.location.href = '/dashboard.php';
            }
        } catch (error) {
            // No existing session, continue with login
            console.log('No existing session found');
        }
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        if (!this.validateForm()) {
            return;
        }
        
        const formData = new FormData(this.form);
        const loginData = {
            email: formData.get('email'),
            password: formData.get('password'),
            remember: formData.get('remember') === 'on'
        };
        
        this.setLoading(true);
        
        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(loginData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('¡Bienvenido a BIG PLAN!', 'success');
                
                // Small delay for better UX
                setTimeout(() => {
                    window.location.href = '/dashboard.php';
                }, 1000);
            } else {
                this.showNotification(data.message || 'Error al iniciar sesión', 'error');
                this.setLoading(false);
            }
            
        } catch (error) {
            console.error('Login error:', error);
            this.showNotification('Error de conexión. Inténtalo de nuevo.', 'error');
            this.setLoading(false);
        }
    }
    
    validateForm() {
        const email = this.form.querySelector('#email');
        const password = this.form.querySelector('#password');
        
        let isValid = true;
        
        if (!this.validateField(email)) {
            isValid = false;
        }
        
        if (!this.validateField(password)) {
            isValid = false;
        }
        
        return isValid;
    }
    
    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        // Remove existing error
        this.clearFieldError(field);
        
        switch (field.type) {
            case 'email':
                if (!value) {
                    errorMessage = 'El correo electrónico es requerido';
                    isValid = false;
                } else if (!this.isValidEmail(value)) {
                    errorMessage = 'Ingresa un correo electrónico válido';
                    isValid = false;
                }
                break;
                
            case 'password':
                if (!value) {
                    errorMessage = 'La contraseña es requerida';
                    isValid = false;
                } else if (value.length < 6) {
                    errorMessage = 'La contraseña debe tener al menos 6 caracteres';
                    isValid = false;
                }
                break;
        }
        
        if (!isValid) {
            this.showFieldError(field, errorMessage);
        }
        
        return isValid;
    }
    
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    showFieldError(field, message) {
        field.style.borderColor = '#f44336';
        
        // Create error message element
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.textContent = message;
        errorElement.style.cssText = `
            color: #f44336;
            font-size: 0.8rem;
            margin-top: 5px;
            animation: slideDown 0.3s ease;
        `;
        
        field.parentNode.appendChild(errorElement);
    }
    
    clearFieldError(field) {
        field.style.borderColor = '#e1e5e9';
        
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
    }
    
    setLoading(loading) {
        if (loading) {
            this.submitBtn.classList.add('loading');
            this.submitBtn.disabled = true;
            this.submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando sesión...';
        } else {
            this.submitBtn.classList.remove('loading');
            this.submitBtn.disabled = false;
            this.submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Iniciar Sesión';
        }
    }
    
    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const icon = type === 'success' ? 'check' : type === 'error' ? 'times' : 'info';
        
        notification.innerHTML = `
            <i class="fas fa-${icon}-circle"></i>
            <span>${message}</span>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Remove after 4 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }
}

// Demo login function for testing
function demoLogin() {
    const loginManager = new LoginManager();
    
    // Fill demo credentials
    document.getElementById('email').value = 'admin@bigplan.com';
    document.getElementById('password').value = 'admin123';
    
    loginManager.showNotification('Credenciales de demo cargadas', 'info');
}

// Initialize login manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new LoginManager();
    
    // Add demo login button for testing
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        const demoBtn = document.createElement('button');
        demoBtn.textContent = 'Demo Login';
        demoBtn.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            padding: 10px 15px;
            background: #ff9800;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            z-index: 1000;
        `;
        demoBtn.onclick = demoLogin;
        document.body.appendChild(demoBtn);
    }
});

// Add CSS animation for field errors
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);