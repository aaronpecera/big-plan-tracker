class DailyExpressionManager {
    constructor() {
        this.currentDate = new Date().toISOString().split('T')[0];
        this.expressions = [];
        this.stats = {};
        this.hasSubmittedToday = false;
        this.init();
    }

    init() {
        this.createExpressionInterface();
        this.loadTodayExpression();
        this.setupEventListeners();
    }

    createExpressionInterface() {
        const expressionHTML = `
            <div id="dailyExpressionModal" class="expression-modal" style="display: none;">
                <div class="expression-modal-content">
                    <div class="expression-header">
                        <h3><i class="fas fa-heart"></i> Expresión Diaria</h3>
                        <button class="expression-close" onclick="dailyExpressionManager.closeModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="expression-body">
                        <div class="expression-form" id="expressionForm">
                            <div class="form-section">
                                <h4><i class="fas fa-smile"></i> ¿Cómo te sientes hoy?</h4>
                                <div class="mood-selector">
                                    <div class="mood-option" data-mood="very_bad">
                                        <div class="mood-emoji">😢</div>
                                        <span>Muy mal</span>
                                    </div>
                                    <div class="mood-option" data-mood="bad">
                                        <div class="mood-emoji">😞</div>
                                        <span>Mal</span>
                                    </div>
                                    <div class="mood-option" data-mood="neutral">
                                        <div class="mood-emoji">😐</div>
                                        <span>Neutral</span>
                                    </div>
                                    <div class="mood-option" data-mood="good">
                                        <div class="mood-emoji">😊</div>
                                        <span>Bien</span>
                                    </div>
                                    <div class="mood-option" data-mood="very_good">
                                        <div class="mood-emoji">😄</div>
                                        <span>Muy bien</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-battery-three-quarters"></i> Nivel de Energía</h4>
                                <div class="slider-container">
                                    <input type="range" id="energySlider" min="1" max="10" value="5" class="energy-slider">
                                    <div class="slider-labels">
                                        <span>Bajo</span>
                                        <span id="energyValue">5</span>
                                        <span>Alto</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-brain"></i> Nivel de Estrés</h4>
                                <div class="slider-container">
                                    <input type="range" id="stressSlider" min="1" max="10" value="5" class="stress-slider">
                                    <div class="slider-labels">
                                        <span>Bajo</span>
                                        <span id="stressValue">5</span>
                                        <span>Alto</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-chart-line"></i> Productividad</h4>
                                <div class="slider-container">
                                    <input type="range" id="productivitySlider" min="1" max="10" value="5" class="productivity-slider">
                                    <div class="slider-labels">
                                        <span>Baja</span>
                                        <span id="productivityValue">5</span>
                                        <span>Alta</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-bed"></i> Horas de Sueño</h4>
                                <div class="sleep-input">
                                    <input type="number" id="sleepHours" min="0" max="24" step="0.5" placeholder="8.0">
                                    <span>horas</span>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-cloud-sun"></i> Clima</h4>
                                <div class="weather-selector">
                                    <div class="weather-option" data-weather="sunny">
                                        <i class="fas fa-sun"></i>
                                        <span>Soleado</span>
                                    </div>
                                    <div class="weather-option" data-weather="cloudy">
                                        <i class="fas fa-cloud"></i>
                                        <span>Nublado</span>
                                    </div>
                                    <div class="weather-option" data-weather="rainy">
                                        <i class="fas fa-cloud-rain"></i>
                                        <span>Lluvioso</span>
                                    </div>
                                    <div class="weather-option" data-weather="stormy">
                                        <i class="fas fa-bolt"></i>
                                        <span>Tormentoso</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-tags"></i> Etiquetas</h4>
                                <div class="tags-container">
                                    <div class="predefined-tags">
                                        <span class="tag-option" data-tag="trabajo">Trabajo</span>
                                        <span class="tag-option" data-tag="familia">Familia</span>
                                        <span class="tag-option" data-tag="ejercicio">Ejercicio</span>
                                        <span class="tag-option" data-tag="estudio">Estudio</span>
                                        <span class="tag-option" data-tag="social">Social</span>
                                        <span class="tag-option" data-tag="descanso">Descanso</span>
                                    </div>
                                    <input type="text" id="customTag" placeholder="Agregar etiqueta personalizada..." 
                                           onkeypress="dailyExpressionManager.handleTagKeyPress(event)">
                                </div>
                                <div class="selected-tags" id="selectedTags"></div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-sticky-note"></i> Notas</h4>
                                <textarea id="expressionNotes" placeholder="¿Qué destacarías de tu día? ¿Algo especial que quieras recordar?" 
                                         rows="3" maxlength="500"></textarea>
                                <div class="char-count">
                                    <span id="notesCharCount">0</span>/500
                                </div>
                            </div>
                        </div>

                        <div class="expression-success" id="expressionSuccess" style="display: none;">
                            <div class="success-content">
                                <i class="fas fa-check-circle"></i>
                                <h4>¡Expresión Registrada!</h4>
                                <p>Gracias por compartir cómo te sientes hoy. Tu bienestar es importante.</p>
                                <div class="success-stats" id="successStats"></div>
                            </div>
                        </div>

                        <div class="expression-history" id="expressionHistory" style="display: none;">
                            <h4><i class="fas fa-history"></i> Historial de Expresiones</h4>
                            <div class="history-filters">
                                <select id="historyPeriod" onchange="dailyExpressionManager.loadHistory()">
                                    <option value="week">Esta semana</option>
                                    <option value="month">Este mes</option>
                                    <option value="quarter">Último trimestre</option>
                                </select>
                            </div>
                            <div class="history-content" id="historyContent"></div>
                        </div>
                    </div>

                    <div class="expression-footer">
                        <div class="footer-buttons" id="footerButtons">
                            <button class="btn-secondary" onclick="dailyExpressionManager.showHistory()">
                                <i class="fas fa-history"></i> Ver Historial
                            </button>
                            <button class="btn-primary" onclick="dailyExpressionManager.submitExpression()" id="submitBtn">
                                <i class="fas fa-heart"></i> Registrar Expresión
                            </button>
                        </div>
                        
                        <div class="footer-success" id="footerSuccess" style="display: none;">
                            <button class="btn-secondary" onclick="dailyExpressionManager.showHistory()">
                                <i class="fas fa-history"></i> Ver Historial
                            </button>
                            <button class="btn-primary" onclick="dailyExpressionManager.closeModal()">
                                <i class="fas fa-check"></i> Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón flotante para abrir expresión diaria -->
            <div id="expressionFloatingBtn" class="expression-floating-btn" onclick="dailyExpressionManager.openModal()">
                <i class="fas fa-heart"></i>
                <span class="floating-tooltip">Expresión Diaria</span>
            </div>
        `;

        // Agregar al body si no existe
        if (!document.getElementById('dailyExpressionModal')) {
            document.body.insertAdjacentHTML('beforeend', expressionHTML);
        }
    }

    setupEventListeners() {
        // Mood selector
        document.querySelectorAll('.mood-option').forEach(option => {
            option.addEventListener('click', () => {
                document.querySelectorAll('.mood-option').forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');
            });
        });

        // Weather selector
        document.querySelectorAll('.weather-option').forEach(option => {
            option.addEventListener('click', () => {
                document.querySelectorAll('.weather-option').forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');
            });
        });

        // Tag selector
        document.querySelectorAll('.tag-option').forEach(option => {
            option.addEventListener('click', () => {
                option.classList.toggle('selected');
                this.updateSelectedTags();
            });
        });

        // Sliders
        const energySlider = document.getElementById('energySlider');
        const stressSlider = document.getElementById('stressSlider');
        const productivitySlider = document.getElementById('productivitySlider');

        if (energySlider) {
            energySlider.addEventListener('input', (e) => {
                document.getElementById('energyValue').textContent = e.target.value;
            });
        }

        if (stressSlider) {
            stressSlider.addEventListener('input', (e) => {
                document.getElementById('stressValue').textContent = e.target.value;
            });
        }

        if (productivitySlider) {
            productivitySlider.addEventListener('input', (e) => {
                document.getElementById('productivityValue').textContent = e.target.value;
            });
        }

        // Notes character count
        const notesTextarea = document.getElementById('expressionNotes');
        if (notesTextarea) {
            notesTextarea.addEventListener('input', (e) => {
                const charCount = e.target.value.length;
                document.getElementById('notesCharCount').textContent = charCount;
                
                if (charCount > 450) {
                    document.getElementById('notesCharCount').style.color = '#dc3545';
                } else {
                    document.getElementById('notesCharCount').style.color = '#6c757d';
                }
            });
        }

        // Modal close on outside click
        document.getElementById('dailyExpressionModal').addEventListener('click', (e) => {
            if (e.target.id === 'dailyExpressionModal') {
                this.closeModal();
            }
        });
    }

    async loadTodayExpression() {
        try {
            const response = await fetch(`/api/daily-expression.php?date=${this.currentDate}&period=day`);
            const data = await response.json();

            if (data.success && data.expressions.length > 0) {
                this.hasSubmittedToday = true;
                this.updateFloatingButton();
            } else {
                this.hasSubmittedToday = false;
                this.updateFloatingButton();
            }
        } catch (error) {
            console.error('Error loading today expression:', error);
        }
    }

    updateFloatingButton() {
        const btn = document.getElementById('expressionFloatingBtn');
        if (btn) {
            if (this.hasSubmittedToday) {
                btn.classList.add('completed');
                btn.querySelector('.floating-tooltip').textContent = 'Expresión completada hoy';
            } else {
                btn.classList.remove('completed');
                btn.querySelector('.floating-tooltip').textContent = 'Registrar expresión diaria';
            }
        }
    }

    openModal() {
        const modal = document.getElementById('dailyExpressionModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            if (this.hasSubmittedToday) {
                this.showHistory();
            } else {
                this.showForm();
            }
        }
    }

    closeModal() {
        const modal = document.getElementById('dailyExpressionModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            this.resetForm();
        }
    }

    showForm() {
        document.getElementById('expressionForm').style.display = 'block';
        document.getElementById('expressionSuccess').style.display = 'none';
        document.getElementById('expressionHistory').style.display = 'none';
        document.getElementById('footerButtons').style.display = 'flex';
        document.getElementById('footerSuccess').style.display = 'none';
    }

    showSuccess() {
        document.getElementById('expressionForm').style.display = 'none';
        document.getElementById('expressionSuccess').style.display = 'block';
        document.getElementById('expressionHistory').style.display = 'none';
        document.getElementById('footerButtons').style.display = 'none';
        document.getElementById('footerSuccess').style.display = 'flex';
    }

    showHistory() {
        document.getElementById('expressionForm').style.display = 'none';
        document.getElementById('expressionSuccess').style.display = 'none';
        document.getElementById('expressionHistory').style.display = 'block';
        this.loadHistory();
    }

    async submitExpression() {
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        
        // Validar datos requeridos
        const selectedMood = document.querySelector('.mood-option.selected');
        if (!selectedMood) {
            this.showNotification('Por favor selecciona tu estado de ánimo', 'error');
            return;
        }

        const energyLevel = document.getElementById('energySlider').value;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';

        try {
            const expressionData = {
                mood: selectedMood.dataset.mood,
                energy_level: parseInt(energyLevel),
                stress_level: parseInt(document.getElementById('stressSlider').value),
                productivity_level: parseInt(document.getElementById('productivitySlider').value),
                sleep_hours: parseFloat(document.getElementById('sleepHours').value) || null,
                weather: document.querySelector('.weather-option.selected')?.dataset.weather || null,
                notes: document.getElementById('expressionNotes').value.trim(),
                tags: this.getSelectedTags()
            };

            const response = await fetch('/api/daily-expression.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(expressionData)
            });

            const data = await response.json();

            if (data.success) {
                this.hasSubmittedToday = true;
                this.updateFloatingButton();
                this.showSuccessStats(expressionData);
                this.showSuccess();
                this.showNotification('¡Expresión registrada exitosamente!', 'success');
            } else {
                this.showNotification(data.error || 'Error al registrar expresión', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Error de conexión', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async loadHistory() {
        const period = document.getElementById('historyPeriod').value;
        const historyContent = document.getElementById('historyContent');
        
        historyContent.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando historial...</div>';

        try {
            const response = await fetch(`/api/daily-expression.php?period=${period}`);
            const data = await response.json();

            if (data.success) {
                this.renderHistory(data.expressions, data.stats);
            } else {
                historyContent.innerHTML = '<div class="error">Error cargando historial</div>';
            }
        } catch (error) {
            console.error('Error:', error);
            historyContent.innerHTML = '<div class="error">Error de conexión</div>';
        }
    }

    renderHistory(expressions, stats) {
        const historyContent = document.getElementById('historyContent');
        
        if (expressions.length === 0) {
            historyContent.innerHTML = `
                <div class="no-history">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No hay expresiones registradas en este período</p>
                </div>
            `;
            return;
        }

        const statsHTML = `
            <div class="history-stats">
                <div class="stat-card">
                    <div class="stat-value">${stats.total_expressions}</div>
                    <div class="stat-label">Expresiones</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.avg_energy}</div>
                    <div class="stat-label">Energía Promedio</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.avg_stress}</div>
                    <div class="stat-label">Estrés Promedio</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.avg_productivity}</div>
                    <div class="stat-label">Productividad Promedio</div>
                </div>
            </div>
        `;

        const expressionsHTML = expressions.map(expr => {
            const date = new Date(expr.date.$date || expr.date);
            const moodEmoji = this.getMoodEmoji(expr.mood);
            
            return `
                <div class="history-item">
                    <div class="history-date">
                        <div class="date-day">${date.getDate()}</div>
                        <div class="date-month">${date.toLocaleDateString('es', { month: 'short' })}</div>
                    </div>
                    <div class="history-content">
                        <div class="history-mood">
                            <span class="mood-emoji">${moodEmoji}</span>
                            <span class="mood-text">${this.getMoodText(expr.mood)}</span>
                        </div>
                        <div class="history-metrics">
                            <span class="metric">⚡ ${expr.energy_level}/10</span>
                            <span class="metric">🧠 ${expr.stress_level}/10</span>
                            <span class="metric">📈 ${expr.productivity_level}/10</span>
                            ${expr.sleep_hours ? `<span class="metric">😴 ${expr.sleep_hours}h</span>` : ''}
                        </div>
                        ${expr.notes ? `<div class="history-notes">${expr.notes}</div>` : ''}
                        ${expr.tags && expr.tags.length > 0 ? `
                            <div class="history-tags">
                                ${expr.tags.map(tag => `<span class="tag">${tag}</span>`).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');

        historyContent.innerHTML = statsHTML + '<div class="history-list">' + expressionsHTML + '</div>';
    }

    showSuccessStats(expressionData) {
        const successStats = document.getElementById('successStats');
        const moodEmoji = this.getMoodEmoji(expressionData.mood);
        
        successStats.innerHTML = `
            <div class="success-summary">
                <div class="summary-item">
                    <span class="summary-emoji">${moodEmoji}</span>
                    <span class="summary-text">${this.getMoodText(expressionData.mood)}</span>
                </div>
                <div class="summary-metrics">
                    <span>⚡ ${expressionData.energy_level}/10</span>
                    <span>🧠 ${expressionData.stress_level}/10</span>
                    <span>📈 ${expressionData.productivity_level}/10</span>
                </div>
            </div>
        `;
    }

    updateSelectedTags() {
        const selectedTags = document.getElementById('selectedTags');
        const tags = this.getSelectedTags();
        
        selectedTags.innerHTML = tags.map(tag => `
            <span class="selected-tag">
                ${tag}
                <button onclick="dailyExpressionManager.removeTag('${tag}')">
                    <i class="fas fa-times"></i>
                </button>
            </span>
        `).join('');
    }

    getSelectedTags() {
        const selectedOptions = document.querySelectorAll('.tag-option.selected');
        const tags = Array.from(selectedOptions).map(option => option.dataset.tag);
        
        // Agregar tags personalizados del input
        const customTagsInput = document.getElementById('selectedTags');
        if (customTagsInput) {
            const customTags = Array.from(customTagsInput.querySelectorAll('.selected-tag'))
                .map(tag => tag.textContent.trim().replace('×', ''))
                .filter(tag => !tags.includes(tag));
            tags.push(...customTags);
        }
        
        return tags;
    }

    handleTagKeyPress(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            const input = event.target;
            const tag = input.value.trim();
            
            if (tag && !this.getSelectedTags().includes(tag)) {
                this.addCustomTag(tag);
                input.value = '';
            }
        }
    }

    addCustomTag(tag) {
        const selectedTags = document.getElementById('selectedTags');
        const tagElement = document.createElement('span');
        tagElement.className = 'selected-tag custom';
        tagElement.innerHTML = `
            ${tag}
            <button onclick="dailyExpressionManager.removeTag('${tag}')">
                <i class="fas fa-times"></i>
            </button>
        `;
        selectedTags.appendChild(tagElement);
    }

    removeTag(tag) {
        // Remover de opciones predefinidas
        const predefinedOption = document.querySelector(`[data-tag="${tag}"]`);
        if (predefinedOption) {
            predefinedOption.classList.remove('selected');
        }
        
        // Remover de tags personalizados
        const customTags = document.querySelectorAll('.selected-tag');
        customTags.forEach(tagElement => {
            if (tagElement.textContent.trim().replace('×', '') === tag) {
                tagElement.remove();
            }
        });
    }

    resetForm() {
        // Reset mood
        document.querySelectorAll('.mood-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        // Reset weather
        document.querySelectorAll('.weather-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        // Reset tags
        document.querySelectorAll('.tag-option').forEach(option => {
            option.classList.remove('selected');
        });
        document.getElementById('selectedTags').innerHTML = '';
        
        // Reset sliders
        document.getElementById('energySlider').value = 5;
        document.getElementById('stressSlider').value = 5;
        document.getElementById('productivitySlider').value = 5;
        document.getElementById('energyValue').textContent = '5';
        document.getElementById('stressValue').textContent = '5';
        document.getElementById('productivityValue').textContent = '5';
        
        // Reset other inputs
        document.getElementById('sleepHours').value = '';
        document.getElementById('expressionNotes').value = '';
        document.getElementById('customTag').value = '';
        document.getElementById('notesCharCount').textContent = '0';
        document.getElementById('notesCharCount').style.color = '#6c757d';
    }

    getMoodEmoji(mood) {
        const moodEmojis = {
            'very_bad': '😢',
            'bad': '😞',
            'neutral': '😐',
            'good': '😊',
            'very_good': '😄'
        };
        return moodEmojis[mood] || '😐';
    }

    getMoodText(mood) {
        const moodTexts = {
            'very_bad': 'Muy mal',
            'bad': 'Mal',
            'neutral': 'Neutral',
            'good': 'Bien',
            'very_good': 'Muy bien'
        };
        return moodTexts[mood] || 'Neutral';
    }

    showNotification(message, type) {
        // Crear notificación temporal
        const notification = document.createElement('div');
        notification.className = `expression-notification ${type}`;
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

// Función global para abrir expresión diaria
function openDailyExpression() {
    if (window.dailyExpressionManager) {
        window.dailyExpressionManager.openModal();
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.dailyExpressionManager = new DailyExpressionManager();
});