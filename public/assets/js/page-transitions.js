/**
 * Page Transitions - Smooth fade effects for page navigation
 */

class PageTransitions {
    constructor() {
        this.transitionDuration = 300; // milliseconds
        this.init();
    }

    init() {
        // Add transition overlay to body
        this.createTransitionOverlay();
        
        // Add page transition class to body
        document.body.classList.add('page-transition');
        
        // Handle all navigation links
        this.setupNavigationHandlers();
        
        // Handle browser back/forward buttons
        this.setupPopstateHandler();
    }

    createTransitionOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'transition-overlay';
        overlay.innerHTML = '<div class="transition-spinner"></div>';
        document.body.appendChild(overlay);
        this.overlay = overlay;
    }

    setupNavigationHandlers() {
        // Handle all links that navigate to other pages
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (link && this.shouldTransition(link)) {
                e.preventDefault();
                this.navigateWithTransition(link.href);
            }
        });

        // Handle form submissions that redirect
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.tagName === 'FORM' && this.shouldTransitionForm(form)) {
                // Let the form submit naturally, but add transition effect
                this.showTransition();
            }
        });
    }

    setupPopstateHandler() {
        window.addEventListener('popstate', () => {
            this.navigateWithTransition(window.location.href, false);
        });
    }

    shouldTransition(link) {
        const href = link.getAttribute('href');
        
        // Don't transition for:
        // - External links
        // - Anchor links
        // - JavaScript links
        // - Download links
        if (!href || 
            href.startsWith('http') || 
            href.startsWith('#') || 
            href.startsWith('javascript:') ||
            link.hasAttribute('download') ||
            link.target === '_blank') {
            return false;
        }

        return true;
    }

    shouldTransitionForm(form) {
        // Only transition forms that redirect to other pages
        const action = form.getAttribute('action');
        const method = form.getAttribute('method') || 'GET';
        
        return action && !action.startsWith('#') && method.toUpperCase() === 'POST';
    }

    async navigateWithTransition(url, pushState = true) {
        try {
            // Show transition effect
            this.showTransition();

            // Wait for transition to start
            await this.wait(50);

            // Navigate to new page
            if (pushState && url !== window.location.href) {
                window.history.pushState(null, '', url);
            }
            
            // Load new page content
            window.location.href = url;

        } catch (error) {
            console.error('Navigation transition failed:', error);
            // Fallback to normal navigation
            window.location.href = url;
        }
    }

    showTransition() {
        // Fade out current page
        document.body.classList.add('fade-out');
        
        // Show loading overlay
        this.overlay.classList.add('active');
    }

    hideTransition() {
        // Fade in new page
        document.body.classList.remove('fade-out');
        document.body.classList.add('fade-in');
        
        // Hide loading overlay
        this.overlay.classList.remove('active');

        // Clean up fade-in class after animation
        setTimeout(() => {
            document.body.classList.remove('fade-in');
        }, this.transitionDuration);
    }

    wait(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Utility functions for manual transitions
window.PageTransitions = {
    // Function to manually trigger a page transition
    navigateTo: function(url) {
        if (window.pageTransitions) {
            window.pageTransitions.navigateWithTransition(url);
        } else {
            window.location.href = url;
        }
    },

    // Function to show loading state
    showLoading: function() {
        if (window.pageTransitions) {
            window.pageTransitions.showTransition();
        }
    },

    // Function to hide loading state
    hideLoading: function() {
        if (window.pageTransitions) {
            window.pageTransitions.hideTransition();
        }
    }
};

// Initialize page transitions when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.pageTransitions = new PageTransitions();
});

// Handle page visibility changes
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && window.pageTransitions) {
        window.pageTransitions.hideTransition();
    }
});