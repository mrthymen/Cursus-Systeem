<?php
/**
 * Reusable Admin Modals v6.4.0
 * Standard modal templates for all admin pages
 * Based on courses.php v6.4.1 design system
 * Updated: 2025-06-13
 * Changes: 
 * v6.4.0 - Extracted from courses.php for reusability
 * v6.4.0 - Generic confirmation modal
 * v6.4.0 - Flexible form modal template
 * v6.4.0 - Standard edit/create patterns
 */

/**
 * Generic Confirmation Modal
 * Usage: renderConfirmationModal('deleteModal', 'Item Verwijderen', 'Weet je zeker dat je dit item wilt verwijderen?');
 */
function renderConfirmationModal($modalId, $title, $message, $confirmText = 'Bevestigen', $cancelText = 'Annuleren') {
    return "
    <div id=\"{$modalId}\" class=\"modal\">
        <div class=\"modal-content\" style=\"max-width: 500px;\">
            <div class=\"modal-header\">
                <h3>{$title}</h3>
                <button class=\"modal-close\" onclick=\"closeModal('{$modalId}')\">&times;</button>
            </div>
            <div class=\"modal-body\">
                <div style=\"text-align: center; padding: 2rem 0;\">
                    <i class=\"fas fa-exclamation-triangle\" style=\"font-size: 3rem; color: var(--warning); margin-bottom: 1rem;\"></i>
                    <p style=\"font-size: 1.1rem; color: var(--text-secondary); margin-bottom: 2rem;\">{$message}</p>
                    <div class=\"btn-group\" style=\"justify-content: center;\">
                        <button onclick=\"closeModal('{$modalId}')\" class=\"btn btn-secondary\">
                            <i class=\"fas fa-times\"></i> {$cancelText}
                        </button>
                        <button id=\"{$modalId}ConfirmBtn\" class=\"btn btn-danger\">
                            <i class=\"fas fa-check\"></i> {$confirmText}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>";
}

/**
 * Generic Form Modal
 * Usage: renderFormModal('itemModal', 'Item Bewerken', $formFields, 'saveItem');
 */
function renderFormModal($modalId, $title, $formContent, $formAction = '', $submitText = 'Opslaan') {
    $formId = str_replace('Modal', 'Form', $modalId);
    
    return "
    <div id=\"{$modalId}\" class=\"modal\">
        <div class=\"modal-content\">
            <div class=\"modal-header\">
                <h3 id=\"{$modalId}Title\">{$title}</h3>
                <button class=\"modal-close\" onclick=\"closeModal('{$modalId}')\">&times;</button>
            </div>
            <div class=\"modal-body\">
                <form method=\"POST\" id=\"{$formId}\" action=\"{$formAction}\">
                    {$formContent}
                    
                    <div class=\"btn-group\">
                        <button type=\"submit\" class=\"btn btn-primary\">
                            <i class=\"fas fa-save\"></i>
                            <span id=\"{$modalId}SubmitText\">{$submitText}</span>
                        </button>
                        <button type=\"button\" onclick=\"closeModal('{$modalId}')\" class=\"btn btn-secondary\">
                            <i class=\"fas fa-times\"></i> Annuleren
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>";
}

/**
 * Quick Action Modal (for simple forms)
 * Usage: renderQuickActionModal('statusModal', 'Status Wijzigen', $quickForm);
 */
function renderQuickActionModal($modalId, $title, $content) {
    return "
    <div id=\"{$modalId}\" class=\"modal\">
        <div class=\"modal-content\" style=\"max-width: 600px;\">
            <div class=\"modal-header\">
                <h3>{$title}</h3>
                <button class=\"modal-close\" onclick=\"closeModal('{$modalId}')\">&times;</button>
            </div>
            <div class=\"modal-body\">
                {$content}
            </div>
        </div>
    </div>";
}

/**
 * Info/Details Modal (read-only)
 * Usage: renderInfoModal('detailsModal', 'Item Details', $detailsContent);
 */
function renderInfoModal($modalId, $title, $content) {
    return "
    <div id=\"{$modalId}\" class=\"modal\">
        <div class=\"modal-content\">
            <div class=\"modal-header\">
                <h3>{$title}</h3>
                <button class=\"modal-close\" onclick=\"closeModal('{$modalId}')\">&times;</button>
            </div>
            <div class=\"modal-body\">
                {$content}
                
                <div class=\"btn-group\" style=\"justify-content: center; margin-top: 2rem;\">
                    <button onclick=\"closeModal('{$modalId}')\" class=\"btn btn-secondary\">
                        <i class=\"fas fa-times\"></i> Sluiten
                    </button>
                </div>
            </div>
        </div>
    </div>";
}

/**
 * Standard CRUD Modal Helper
 * Generates create/edit modal with standard fields
 */
function renderCrudModal($entity, $fields, $templates = []) {
    $modalId = $entity . 'Modal';
    $formId = $entity . 'Form';
    
    // Generate form fields
    $formFields = '<div class="form-grid">';
    foreach ($fields as $field) {
        $formFields .= generateFormField($field);
    }
    $formFields .= '</div>';
    
    // Add template selection if provided
    if (!empty($templates)) {
        $templateSelect = generateTemplateSelect($templates);
        $formFields = $templateSelect . $formFields;
    }
    
    $hiddenFields = "
    <input type=\"hidden\" name=\"action\" value=\"create_{$entity}\" id=\"{$entity}Action\">
    <input type=\"hidden\" name=\"{$entity}_id\" id=\"{$entity}Id\">";
    
    return renderFormModal($modalId, ucfirst($entity) . ' Aanmaken', $hiddenFields . $formFields);
}

/**
 * Generate form field HTML
 */
function generateFormField($field) {
    $name = $field['name'];
    $label = $field['label'];
    $type = $field['type'] ?? 'text';
    $required = $field['required'] ?? false;
    $placeholder = $field['placeholder'] ?? '';
    $options = $field['options'] ?? [];
    $fullWidth = $field['full_width'] ?? false;
    $help = $field['help'] ?? '';
    
    $requiredAttr = $required ? 'required' : '';
    $classAttr = $fullWidth ? 'class="form-group full-width"' : 'class="form-group"';
    
    $fieldHtml = "<div {$classAttr}>";
    $fieldHtml .= "<label for=\"{$name}\">{$label}</label>";
    
    switch ($type) {
        case 'textarea':
            $rows = $field['rows'] ?? 3;
            $fieldHtml .= "<textarea id=\"{$name}\" name=\"{$name}\" rows=\"{$rows}\" placeholder=\"{$placeholder}\" {$requiredAttr}></textarea>";
            break;
            
        case 'select':
            $fieldHtml .= "<select id=\"{$name}\" name=\"{$name}\" {$requiredAttr}>";
            foreach ($options as $value => $text) {
                $fieldHtml .= "<option value=\"{$value}\">{$text}</option>";
            }
            $fieldHtml .= "</select>";
            break;
            
        case 'checkbox':
            $fieldHtml .= "<label><input type=\"checkbox\" name=\"{$name}\" value=\"1\" id=\"{$name}\"> {$label}</label>";
            break;
            
        case 'number':
            $min = $field['min'] ?? '';
            $max = $field['max'] ?? '';
            $step = $field['step'] ?? '';
            $fieldHtml .= "<input type=\"number\" id=\"{$name}\" name=\"{$name}\" placeholder=\"{$placeholder}\" min=\"{$min}\" max=\"{$max}\" step=\"{$step}\" {$requiredAttr}>";
            break;
            
        default: // text, email, date, etc.
            $fieldHtml .= "<input type=\"{$type}\" id=\"{$name}\" name=\"{$name}\" placeholder=\"{$placeholder}\" {$requiredAttr}>";
            break;
    }
    
    if ($help) {
        $fieldHtml .= "<small style=\"color: var(--neutral); font-size: var(--font-size-xs);\">{$help}</small>";
    }
    
    $fieldHtml .= "</div>";
    
    return $fieldHtml;
}

/**
 * Generate template selection dropdown
 */
function generateTemplateSelect($templates) {
    $html = '<div class="form-group">
        <label for="template_select">Template</label>
        <select id="template_select" name="template" onchange="fillTemplateData(this.value)">
            <option value="">Aangepast (geen template)</option>';
    
    foreach ($templates as $template) {
        $html .= '<option value="' . htmlspecialchars($template['key']) . '" 
                    data-category="' . htmlspecialchars($template['category'] ?? '') . '"
                    data-description="' . htmlspecialchars($template['description'] ?? '') . '">';
        $html .= htmlspecialchars($template['name']);
        $html .= '</option>';
    }
    
    $html .= '</select>
        <small style="color: var(--neutral);">Template selecteren vult automatisch de velden in</small>
    </div>';
    
    return $html;
}

/**
 * Standard Delete Confirmation
 * Usage: echo renderDeleteConfirmation('course', $course['id'], $course['name']);
 */
function renderDeleteConfirmation($entity, $id, $name) {
    return "
    <form method=\"POST\" style=\"display: inline;\" 
          onsubmit=\"return confirm('Weet je zeker dat je &quot;" . htmlspecialchars($name) . "&quot; wilt verwijderen?')\">
        <input type=\"hidden\" name=\"action\" value=\"delete_{$entity}\">
        <input type=\"hidden\" name=\"{$entity}_id\" value=\"{$id}\">
        <button type=\"submit\" class=\"btn btn-danger\">
            <i class=\"fas fa-trash\"></i> Verwijderen
        </button>
    </form>";
}

/**
 * Standard Duplicate Action
 * Usage: echo renderDuplicateAction('course', $course['id']);
 */
function renderDuplicateAction($entity, $id) {
    return "
    <form method=\"POST\" style=\"display: inline;\">
        <input type=\"hidden\" name=\"action\" value=\"duplicate_{$entity}\">
        <input type=\"hidden\" name=\"{$entity}_id\" value=\"{$id}\">
        <button type=\"submit\" class=\"btn btn-warning\">
            <i class=\"fas fa-copy\"></i> Dupliceer
        </button>
    </form>";
}

/**
 * Standard Action Button Group
 * Usage: echo renderActionGroup($entity, $item, $hasParticipants);
 */
function renderActionGroup($entity, $item, $preventDelete = false) {
    $id = $item['id'];
    $name = $item['name'] ?? $item['display_name'] ?? "Item {$id}";
    
    $html = '<div class="btn-group">';
    
    // Edit button
    $html .= "<button onclick=\"edit" . ucfirst($entity) . "({$id})\" class=\"btn btn-primary\">
                <i class=\"fas fa-edit\"></i> Bewerken
              </button>";
    
    // Duplicate button
    $html .= renderDuplicateAction($entity, $id);
    
    // Delete button (only if no restrictions)
    if (!$preventDelete) {
        $html .= renderDeleteConfirmation($entity, $id, $name);
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render Loading Modal
 */
function renderLoadingModal() {
    return "
    <div id=\"loadingModal\" class=\"modal\">
        <div class=\"modal-content\" style=\"max-width: 400px;\">
            <div class=\"modal-body\">
                <div style=\"text-align: center; padding: 2rem;\">
                    <i class=\"fas fa-spinner fa-spin\" style=\"font-size: 2rem; color: var(--primary); margin-bottom: 1rem;\"></i>
                    <p style=\"color: var(--text-secondary);\">Bezig met laden...</p>
                </div>
            </div>
        </div>
    </div>";
}

/**
 * JavaScript helpers for modals
 */
function renderModalJavaScript() {
    return "
    <script>
    // Modal-specific helper functions
    
    function openLoadingModal() {
        openModal('loadingModal');
    }
    
    function closeLoadingModal() {
        closeModal('loadingModal');
    }
    
    function openConfirmationModal(modalId, callback) {
        openModal(modalId);
        const confirmBtn = document.getElementById(modalId + 'ConfirmBtn');
        if (confirmBtn) {
            confirmBtn.onclick = function() {
                closeModal(modalId);
                if (typeof callback === 'function') {
                    callback();
                }
            };
        }
    }
    
    // Auto-generate edit functions for CRUD entities
    function generateEditFunction(entity) {
        window['edit' + entity.charAt(0).toUpperCase() + entity.slice(1)] = async function(id) {
            try {
                openLoadingModal();
                
                const response = await fetchData(`?ajax=1&action=get_${entity}&id=\${id}`);
                
                closeLoadingModal();
                
                if (response.error) {
                    throw new Error(response.error);
                }
                
                // Fill form
                const formId = entity + 'Form';
                fillFormFromData(formId, response);
                
                // Update form action and modal title
                document.getElementById(entity + 'Action').value = 'update_' + entity;
                document.getElementById(entity + 'Id').value = response.id;
                document.getElementById(entity + 'ModalTitle').textContent = entity.charAt(0).toUpperCase() + entity.slice(1) + ' Bewerken';
                document.getElementById(entity + 'ModalSubmitText').textContent = entity.charAt(0).toUpperCase() + entity.slice(1) + ' Bijwerken';
                
                // Open modal
                openModal(entity + 'Modal');
                showNotification(entity.charAt(0).toUpperCase() + entity.slice(1) + ' geladen!', 'success');
                
            } catch (error) {
                closeLoadingModal();
                console.error('Error loading ' + entity + ':', error);
                showNotification('Fout bij laden: ' + error.message, 'error');
            }
        };
    }
    
    // Auto-generate reset functions
    function generateResetFunction(entity) {
        window['reset' + entity.charAt(0).toUpperCase() + entity.slice(1) + 'Form'] = function() {
            const formId = entity + 'Form';
            resetForm(formId);
            
            document.getElementById(entity + 'Action').value = 'create_' + entity;
            document.getElementById(entity + 'Id').value = '';
            document.getElementById(entity + 'ModalTitle').textContent = entity.charAt(0).toUpperCase() + entity.slice(1) + ' Aanmaken';
            document.getElementById(entity + 'ModalSubmitText').textContent = entity.charAt(0).toUpperCase() + entity.slice(1) + ' Aanmaken';
        };
    }
    </script>";
}

/**
 * Example usage for specific entities
 */

// Example: Course modal fields
$courseFields = [
    ['name' => 'course_name', 'label' => 'Cursus Naam', 'type' => 'text', 'required' => true],
    ['name' => 'instructor', 'label' => 'Instructeur', 'type' => 'text'],
    ['name' => 'course_date', 'label' => 'Datum', 'type' => 'date', 'required' => true],
    ['name' => 'course_time', 'label' => 'Tijdstip', 'type' => 'text', 'placeholder' => '09:00 - 17:00'],
    ['name' => 'max_participants', 'label' => 'Max Deelnemers', 'type' => 'number', 'min' => '1'],
    ['name' => 'price', 'label' => 'Prijs (€)', 'type' => 'number', 'step' => '0.01', 'min' => '0'],
    ['name' => 'course_description', 'label' => 'Beschrijving', 'type' => 'textarea', 'rows' => 4, 'full_width' => true, 'required' => true]
];

// Example: Template modal fields
$templateFields = [
    ['name' => 'template_key', 'label' => 'Template Key', 'type' => 'text', 'required' => true, 'help' => 'Unieke identificatie voor deze template'],
    ['name' => 'display_name', 'label' => 'Weergave Naam', 'type' => 'text', 'required' => true],
    ['name' => 'category', 'label' => 'Categorie', 'type' => 'select', 'options' => ['ai-training' => 'AI Training', 'horeca' => 'Horeca', 'algemeen' => 'Algemeen']],
    ['name' => 'default_description', 'label' => 'Standaard Beschrijving', 'type' => 'textarea', 'full_width' => true, 'required' => true]
];

?>

<!-- Always include loading modal -->
<?= renderLoadingModal() ?>

<!-- Include modal JavaScript helpers -->
<?= renderModalJavaScript() ?>