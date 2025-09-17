class ChatManager {
    constructor() {
        this.currentChatUser = null;
        this.conversations = [];
        this.messages = [];
        this.isOpen = false;
        this.unreadCount = 0;
        this.init();
    }

    init() {
        this.createChatInterface();
        this.setupEventListeners();
        this.loadConversations();
        this.startPolling();
    }

    createChatInterface() {
        const chatHTML = `
            <div id="chatWidget" class="chat-widget">
                <div class="chat-toggle" onclick="chatManager.toggleChat()">
                    <i class="fas fa-comments"></i>
                    <span class="chat-badge" id="chatBadge" style="display: none;">0</span>
                </div>
                
                <div class="chat-container" id="chatContainer" style="display: none;">
                    <div class="chat-header">
                        <h3><i class="fas fa-comments"></i> Chat</h3>
                        <button class="chat-close" onclick="chatManager.toggleChat()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="chat-content">
                        <div class="chat-sidebar">
                            <div class="chat-search">
                                <input type="text" id="chatSearch" placeholder="Buscar conversaciones..." 
                                       onkeyup="chatManager.filterConversations(this.value)">
                            </div>
                            <div class="conversations-list" id="conversationsList">
                                <div class="loading-conversations">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando...
                                </div>
                            </div>
                        </div>
                        
                        <div class="chat-main">
                            <div class="chat-welcome" id="chatWelcome">
                                <div class="welcome-content">
                                    <i class="fas fa-comments"></i>
                                    <h4>Bienvenido al Chat</h4>
                                    <p>Selecciona una conversación para comenzar a chatear</p>
                                </div>
                            </div>
                            
                            <div class="chat-conversation" id="chatConversation" style="display: none;">
                                <div class="conversation-header" id="conversationHeader">
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="user-details">
                                            <h4 id="chatUserName">Usuario</h4>
                                            <span id="chatUserRole" class="user-role">Rol</span>
                                        </div>
                                    </div>
                                    <div class="conversation-actions">
                                        <button onclick="chatManager.refreshMessages()" title="Actualizar">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="messages-container" id="messagesContainer">
                                    <div class="messages-list" id="messagesList"></div>
                                </div>
                                
                                <div class="message-input-container">
                                    <div class="message-input">
                                        <textarea id="messageInput" placeholder="Escribe tu mensaje..." 
                                                rows="1" onkeypress="chatManager.handleKeyPress(event)"></textarea>
                                        <button onclick="chatManager.sendMessage()" id="sendButton">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Agregar al body si no existe
        if (!document.getElementById('chatWidget')) {
            document.body.insertAdjacentHTML('beforeend', chatHTML);
        }
    }

    setupEventListeners() {
        // Auto-resize del textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }

        // Cerrar chat al hacer clic fuera
        document.addEventListener('click', (e) => {
            const chatWidget = document.getElementById('chatWidget');
            if (chatWidget && !chatWidget.contains(e.target) && this.isOpen) {
                // No cerrar automáticamente para mejor UX
            }
        });
    }

    toggleChat() {
        const container = document.getElementById('chatContainer');
        const toggle = document.querySelector('.chat-toggle');
        
        this.isOpen = !this.isOpen;
        
        if (this.isOpen) {
            container.style.display = 'block';
            toggle.classList.add('active');
            this.loadConversations();
        } else {
            container.style.display = 'none';
            toggle.classList.remove('active');
        }
    }

    async loadConversations() {
        try {
            const response = await fetch('/api/chat/messages.php');
            const data = await response.json();

            if (data.success) {
                this.conversations = data.conversations;
                this.renderConversations();
                this.updateUnreadCount();
            } else {
                console.error('Error cargando conversaciones:', data.error);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    renderConversations() {
        const container = document.getElementById('conversationsList');
        
        if (this.conversations.length === 0) {
            container.innerHTML = `
                <div class="no-conversations">
                    <i class="fas fa-inbox"></i>
                    <p>No hay conversaciones</p>
                </div>
            `;
            return;
        }

        const html = this.conversations.map(conv => `
            <div class="conversation-item ${conv.unread_count > 0 ? 'unread' : ''}" 
                 onclick="chatManager.openConversation('${conv.user_id}', '${conv.user_name}', '${conv.user_role}')">
                <div class="conversation-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="conversation-info">
                    <div class="conversation-header">
                        <h5>${conv.user_name}</h5>
                        <span class="conversation-time">${this.formatTime(conv.last_message.created_at)}</span>
                    </div>
                    <div class="conversation-preview">
                        <p>${this.truncateMessage(conv.last_message.content)}</p>
                        ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                    </div>
                    <div class="conversation-meta">
                        <span class="user-role-badge ${conv.user_role}">${this.getRoleText(conv.user_role)}</span>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    filterConversations(query) {
        const items = document.querySelectorAll('.conversation-item');
        
        items.forEach(item => {
            const name = item.querySelector('h5').textContent.toLowerCase();
            const preview = item.querySelector('.conversation-preview p').textContent.toLowerCase();
            
            if (name.includes(query.toLowerCase()) || preview.includes(query.toLowerCase())) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    async openConversation(userId, userName, userRole) {
        this.currentChatUser = {
            id: userId,
            name: userName,
            role: userRole
        };

        // Actualizar header
        document.getElementById('chatUserName').textContent = userName;
        document.getElementById('chatUserRole').textContent = this.getRoleText(userRole);
        document.getElementById('chatUserRole').className = `user-role ${userRole}`;

        // Mostrar conversación
        document.getElementById('chatWelcome').style.display = 'none';
        document.getElementById('chatConversation').style.display = 'block';

        // Marcar conversación como activa
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        event.currentTarget.classList.add('active');

        // Cargar mensajes
        await this.loadMessages(userId);
    }

    async loadMessages(userId) {
        try {
            const response = await fetch(`/api/chat/messages.php?chat_with=${userId}`);
            const data = await response.json();

            if (data.success) {
                this.messages = data.messages;
                this.renderMessages();
                this.scrollToBottom();
                this.updateConversationReadStatus(userId);
            } else {
                console.error('Error cargando mensajes:', data.error);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    renderMessages() {
        const container = document.getElementById('messagesList');
        
        if (this.messages.length === 0) {
            container.innerHTML = `
                <div class="no-messages">
                    <i class="fas fa-comment"></i>
                    <p>No hay mensajes. ¡Inicia la conversación!</p>
                </div>
            `;
            return;
        }

        const html = this.messages.map(message => {
            const isOwn = message.sender_id === this.getCurrentUserId();
            const messageClass = isOwn ? 'message own' : 'message';
            
            return `
                <div class="${messageClass}" data-message-id="${message.id}">
                    <div class="message-content">
                        <p>${this.formatMessageContent(message.content)}</p>
                        ${message.attachments && message.attachments.length > 0 ? 
                            this.renderAttachments(message.attachments) : ''}
                    </div>
                    <div class="message-meta">
                        <span class="message-time">${this.formatTime(message.created_at)}</span>
                        ${isOwn ? `<span class="message-status ${message.read ? 'read' : 'sent'}">
                            <i class="fas fa-check${message.read ? '-double' : ''}"></i>
                        </span>` : ''}
                        ${isOwn ? `<button class="message-delete" onclick="chatManager.deleteMessage('${message.id}')" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = html;
    }

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const content = input.value.trim();

        if (!content || !this.currentChatUser) {
            return;
        }

        const sendButton = document.getElementById('sendButton');
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await fetch('/api/chat/messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'send',
                    recipient_id: this.currentChatUser.id,
                    content: content,
                    message_type: 'text'
                })
            });

            const data = await response.json();

            if (data.success) {
                input.value = '';
                input.style.height = 'auto';
                await this.loadMessages(this.currentChatUser.id);
                await this.loadConversations(); // Actualizar lista de conversaciones
            } else {
                this.showNotification('Error al enviar mensaje: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Error de conexión', 'error');
        } finally {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
        }
    }

    async deleteMessage(messageId) {
        if (!confirm('¿Estás seguro de que quieres eliminar este mensaje?')) {
            return;
        }

        try {
            const response = await fetch('/api/chat/messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'delete',
                    message_id: messageId
                })
            });

            const data = await response.json();

            if (data.success) {
                await this.loadMessages(this.currentChatUser.id);
                this.showNotification('Mensaje eliminado', 'success');
            } else {
                this.showNotification('Error al eliminar mensaje: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Error de conexión', 'error');
        }
    }

    handleKeyPress(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.sendMessage();
        }
    }

    async refreshMessages() {
        if (this.currentChatUser) {
            await this.loadMessages(this.currentChatUser.id);
        }
    }

    updateConversationReadStatus(userId) {
        const conversation = this.conversations.find(conv => conv.user_id === userId);
        if (conversation) {
            conversation.unread_count = 0;
            this.renderConversations();
            this.updateUnreadCount();
        }
    }

    updateUnreadCount() {
        const totalUnread = this.conversations.reduce((sum, conv) => sum + conv.unread_count, 0);
        this.unreadCount = totalUnread;
        
        const badge = document.getElementById('chatBadge');
        if (badge) {
            if (totalUnread > 0) {
                badge.textContent = totalUnread > 99 ? '99+' : totalUnread;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    startPolling() {
        // Actualizar conversaciones cada 30 segundos
        setInterval(() => {
            if (this.isOpen) {
                this.loadConversations();
                if (this.currentChatUser) {
                    this.loadMessages(this.currentChatUser.id);
                }
            }
        }, 30000);

        // Actualizar badge cada 10 segundos
        setInterval(() => {
            if (!this.isOpen) {
                this.loadConversations();
            }
        }, 10000);
    }

    scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Ahora';
        if (diffMins < 60) return `${diffMins}m`;
        if (diffHours < 24) return `${diffHours}h`;
        if (diffDays < 7) return `${diffDays}d`;
        
        return date.toLocaleDateString();
    }

    formatMessageContent(content) {
        // Escapar HTML y convertir URLs en enlaces
        const escaped = content.replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const withLinks = escaped.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" rel="noopener">$1</a>'
        );
        return withLinks.replace(/\n/g, '<br>');
    }

    truncateMessage(content, maxLength = 50) {
        if (content.length <= maxLength) return content;
        return content.substring(0, maxLength) + '...';
    }

    getRoleText(role) {
        const roleMap = {
            'admin': 'Administrador',
            'worker': 'Trabajador',
            'manager': 'Gerente'
        };
        return roleMap[role] || role;
    }

    renderAttachments(attachments) {
        return attachments.map(attachment => `
            <div class="message-attachment">
                <i class="fas fa-paperclip"></i>
                <a href="${attachment.url}" target="_blank">${attachment.name}</a>
            </div>
        `).join('');
    }

    getCurrentUserId() {
        // Obtener del session storage o variable global
        return window.currentUserId || sessionStorage.getItem('user_id');
    }

    showNotification(message, type) {
        // Implementar sistema de notificaciones
        console.log(`${type.toUpperCase()}: ${message}`);
        
        // Crear notificación temporal
        const notification = document.createElement('div');
        notification.className = `chat-notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'}"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}

// Inicializar chat cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.chatManager = new ChatManager();
});

// Función global para abrir chat con usuario específico
function openChatWith(userId, userName, userRole) {
    if (window.chatManager) {
        if (!window.chatManager.isOpen) {
            window.chatManager.toggleChat();
        }
        setTimeout(() => {
            window.chatManager.openConversation(userId, userName, userRole);
        }, 100);
    }
}