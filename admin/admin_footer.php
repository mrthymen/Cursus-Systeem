<?php
/**
 * Unified Admin Footer v6.4.0
 * JavaScript, modals and closing tags for all admin pages
 * Based on courses.php v6.4.1 design system
 * Updated: 2025-06-13
 * Changes: 
 * v6.4.0 - Extracted from courses.php for reusability
 * v6.4.0 - Universal modal system
 * v6.4.0 - Smart notification system
 * v6.4.0 - Keyboard shortcuts
 */
?>
            </div> <!-- End container -->
        </div> <!-- End main-content -->
    </div> <!-- End admin-wrapper -->
    
    <script>
    // ==========================================
    // UNIFIED ADMIN JAVASCRIPT v6.4.0
    // ==========================================
    
    /**
     * MODAL SYSTEM
     */
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Focus first input field
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            
            // Trigger reset function if it exists
            const resetFunction = window[`reset${modalId.replace('Modal', '')}Form`];
            if (typeof resetFunction === 'function') {
                resetFunction();
            }
        }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target.id);
        }
    });

    /**
     * NOTIFICATION SYSTEM
     */
    function showNotification(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        
        const typeIcons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        
        const typeColors = {
            success: '#059669',
            error: '#dc2626',
            warning: '#d97706',
            info: '#2563eb'
        };
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            background: ${typeColors[type] || typeColors.info};
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            transform: translateX(100%);
            opacity: 0;
            max-width: 400px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        `;
        
        notification.innerHTML = `
            <i class="${typeIcons[type] || typeIcons.info}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
            notification.style.opacity = '1';
        }, 10);
        
        // Animate out and remove
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    }
    
    /**
     * AJAX HELPER FUNCTIONS
     */
    async function fetchData(url, options = {}) {
        try {
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            return data;
        } catch (error) {
            console.error('Fetch error:', error);
            throw error;
        }
    }
    
    /**
     * FORM HELPERS
     */
    function resetForm(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.reset();
            
            // Reset any custom states
            const selectElements = form.querySelectorAll('select');
            selectElements.forEach(select => {
                if (select.onchange) {
                    select.onchange();
                }
            });
        }
    }
    
    function fillFormFromData(formId, data) {
        const form = document.getElementById(formId);
        if (!form || !data) return;
        
        Object.keys(data).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = data[key] == '1' || data[key] === true;
                } else if (field.type === 'date' && data[key]) {
                    // Handle date formatting for input fields
                    const date = new Date(data[key]);
                    if (!isNaN(date.getTime())) {
                        field.value = date.toISOString().split('T')[0];
                    }
                } else {
                    field.value = data[key] || '';
                }
            }
        });
    }
    
    /**
     * CONFIRMATION DIALOGS
     */
    function confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
    
    function confirmDelete(itemName, callback) {
        const message = `Weet je zeker dat je "${itemName}" wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.`;
        confirmAction(message, callback);
    }
    
    /**
     * TABLE HELPERS
     */
    function sortTable(table, column, ascending = true) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            const aVal = a.cells[column].textContent.trim();
            const bVal = b.cells[column].textContent.trim();
            
            // Try to parse as numbers
            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return ascending ? aNum - bNum : bNum - aNum;
            }
            
            // Compare as strings
            return ascending ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }
    
    /**
     * SEARCH/FILTER HELPERS
     */
    function filterItems(searchTerm, itemSelector, textSelector) {
        const items = document.querySelectorAll(itemSelector);
        const term = searchTerm.toLowerCase();
        
        items.forEach(item => {
            const textElements = item.querySelectorAll(textSelector);
            let text = '';
            textElements.forEach(el => text += el.textContent + ' ');
            
            const matches = text.toLowerCase().includes(term);
            item.style.display = matches ? '' : 'none';
        });
    }
    
    /**
     * URL HELPERS
     */
    function updateURLParameter(param, value) {
        const url = new URL(window.location);
        if (value) {
            url.searchParams.set(param, value);
        } else {
            url.searchParams.delete(param);
        }
        window.history.replaceState({}, '', url);
    }
    
    function getURLParameter(param) {
        const url = new URL(window.location);
        return url.searchParams.get(param);
    }
    
    /**
     * LOADING STATES
     */
    function setButtonLoading(button, loading = true) {
        if (loading) {
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Laden...';
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText || button.innerHTML;
        }
    }
    
    /**
     * KEYBOARD SHORTCUTS
     */
    document.addEventListener('keydown', function(e) {
        // Escape to close modals
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                closeModal(modal.id);
            });
        }
        
        // Ctrl+S to save forms (prevent default browser save)
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const activeModal = document.querySelector('.modal.active');
            if (activeModal) {
                const form = activeModal.querySelector('form');
                if (form) {
                    form.submit();
                }
            }
        }
        
        // Ctrl+N for new item (if current page supports it)
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            
            // Try to trigger "new" button
            const newButton = document.querySelector('[onclick*="Modal"]');
            if (newButton) {
                newButton.click();
            }
        }
    });
    
    /**
     * FORM VALIDATION
     */
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function validateRequired(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = 'var(--error)';
                isValid = false;
            } else {
                field.style.borderColor = '';
            }
        });
        
        return isValid;
    }
    
    /**
     * AUTO-INITIALIZATION
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-focus first input in modals when opened
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const target = mutation.target;
                    if (target.classList.contains('modal') && target.classList.contains('active')) {
                        const firstInput = target.querySelector('input:not([type="hidden"]), select, textarea');
                        if (firstInput) {
                            setTimeout(() => firstInput.focus(), 100);
                        }
                    }
                }
            });
        });
        
        document.querySelectorAll('.modal').forEach(modal => {
            observer.observe(modal, { attributes: true });
        });
        
        // Auto-resize textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
        
        // Form validation on submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!validateRequired(this)) {
                    e.preventDefault();
                    showNotification('Vul alle verplichte velden in', 'error');
                }
            });
        });
        
        // Auto-hide messages after 5 seconds
        document.querySelectorAll('.message').forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 300);
            }, 5000);
        });
        
        // Initialize tooltips if any
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function() {
                // Basic tooltip implementation
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.title;
                tooltip.style.cssText = `
                    position: absolute;
                    background: rgba(0,0,0,0.8);
                    color: white;
                    padding: 0.5rem;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 1100;
                    pointer-events: none;
                `;
                document.body.appendChild(tooltip);
                this.dataset.tooltip = 'active';
                
                // Position tooltip
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + 'px';
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
            });
            
            element.addEventListener('mouseleave', function() {
                if (this.dataset.tooltip === 'active') {
                    const tooltip = document.querySelector('.tooltip');
                    if (tooltip) {
                        tooltip.remove();
                    }
                    delete this.dataset.tooltip;
                }
            });
        });
    });
    
    /**
     * PAGE-SPECIFIC INITIALIZATIONS
     * Pages can override these functions
     */
    
    // Template auto-fill functionality
    if (typeof fillTemplateData === 'undefined') {
        window.fillTemplateData = function(templateKey) {
            console.log('fillTemplateData called with:', templateKey);
            // Default implementation - can be overridden by specific pages
        };
    }
    
    // Location description update
    if (typeof updateLocationDescription === 'undefined') {
        window.updateLocationDescription = function() {
            const locationSelect = document.getElementById('location');
            const locationDescription = document.getElementById('location-description');
            
            if (locationSelect && locationDescription && locationSelect.options.length > 0) {
                const selectedIndex = locationSelect.selectedIndex;
                if (selectedIndex >= 0 && selectedIndex < locationSelect.options.length) {
                    const selectedOption = locationSelect.options[selectedIndex];
                    if (selectedOption && selectedOption.dataset) {
                        const description = selectedOption.dataset.description || '';
                        locationDescription.textContent = description;
                    }
                }
            }
        };
    }
    
    // Debug mode check
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        console.log('🎯 Admin System v6.4.0 loaded in debug mode');
        console.log('Available functions:', {
            openModal,
            closeModal,
            showNotification,
            fetchData,
            fillFormFromData,
            confirmDelete
        });
    }
    </script>
</body>
</html>