<?php
/**
 * Cursus Beheer - Unified System Example v6.4.0
 * Example of how to convert existing pages to unified system
 * Based on courses.php v6.4.1 but using shared components
 * Updated: 2025-06-13
 */

session_start();

// Set page title for header
$page_title = 'Cursus & Template Beheer';

// Include unified header
require_once 'admin_header.php';

// Your existing business logic here...
require_once '../includes/config.php';

try {
    $pdo = getDatabase();
} catch (Exception $e) {
    die('Database verbinding mislukt: ' . $e->getMessage());
}

// Handle AJAX requests for editing
if (isset($_GET['ajax']) && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_template':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $template = getTemplateById($pdo, $_GET['id']);
                echo json_encode($template ?: ['error' => 'Template niet gevonden']);
            } else {
                echo json_encode(['error' => 'Invalid ID']);
            }
            break;
            
        case 'get_course':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $course = getCourseById($pdo, $_GET['id']);
                echo json_encode($course ?: ['error' => 'Cursus niet gevonden']);
            } else {
                echo json_encode(['error' => 'Invalid ID']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

// Handle form submissions (your existing logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... your form handling logic
    
    header('Location: courses_unified.php' . (isset($_GET['tab']) ? '?tab=' . $_GET['tab'] : ''));
    exit;
}

// Get current tab
$current_tab = $_GET['tab'] ?? 'courses';

// Get data (your existing data fetching)
if ($current_tab === 'templates') {
    $templates = getCourseTemplates($pdo);
} else {
    $courses = getCoursesWithParticipants($pdo);
    $templates = getCourseTemplates($pdo);
}

// Your existing helper functions...
function getCourseTemplates($pdo) {
    // ... existing implementation
    return [];
}

function getCoursesWithParticipants($pdo) {
    // ... existing implementation  
    return [];
}

function getTemplateById($pdo, $id) {
    // ... existing implementation
    return null;
}

function getCourseById($pdo, $id) {
    // ... existing implementation
    return null;
}

// Predefined data
$template_categories = [
    'ai-training' => 'AI Training',
    'horeca' => 'Horeca Training', 
    'marketing' => 'Marketing',
    'management' => 'Management',
    'algemeen' => 'Algemeen'
];

$predefined_locations = [
    'The Rise Tilburg' => 'The Rise, Spoorlaan 444, 5038 CG Tilburg',
    'Online' => 'Online via Zoom/Teams',
    'Incompany' => 'Op locatie bij de klant',
    'Eigen Locatie' => 'Eigen trainingslocatie Inventijn'
];
?>

<!-- Tab Navigation -->
<div class="tab-navigation">
    <div class="tab-links">
        <a href="?tab=courses" class="tab-button <?= $current_tab === 'courses' ? 'active' : '' ?>">
            <i class="fas fa-book"></i> Cursussen (<?= count($courses ?? []) ?>)
        </a>
        <a href="?tab=templates" class="tab-button <?= $current_tab === 'templates' ? 'active' : '' ?>">
            <i class="fas fa-clone"></i> Templates (<?= count($templates ?? []) ?>)
        </a>
    </div>
    <div class="action-buttons">
        <?php if ($current_tab === 'templates'): ?>
            <button onclick="openModal('templateModal')" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nieuwe Template
            </button>
        <?php else: ?>
            <button onclick="openModal('courseModal')" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nieuwe Cursus
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Messages -->
<?php if (isset($_SESSION['message'])): ?>
    <div class="message <?= $_SESSION['message_type'] ?? 'info' ?>">
        <?= htmlspecialchars($_SESSION['message']) ?>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<?php if ($current_tab === 'templates'): ?>
    <!-- TEMPLATE MANAGEMENT -->
    
    <div class="card">
        <div class="card-header">
            <h3>Alle Templates</h3>
        </div>

        <?php if (empty($templates)): ?>
            <div class="empty-state">
                <i class="fas fa-clone"></i>
                <p>Nog geen templates aangemaakt.<br>Klik op "Nieuwe Template" om te beginnen.</p>
            </div>
        <?php else: ?>
            <?php foreach ($templates as $template): ?>
                <div class="card template-item">
                    <div class="template-header">
                        <div class="template-info">
                            <h3 style="color: #1f2937; margin-bottom: 0.5rem; font-size: 1.3rem;">
                                <?= htmlspecialchars($template['display_name'] ?? '') ?>
                                <span class="template-usage"><?= $template['course_count'] ?? 0 ?> cursussen</span>
                            </h3>
                            <p style="color: #6b7280; margin-bottom: 1rem;">
                                <strong>Key:</strong> <code><?= htmlspecialchars($template['template_key'] ?? '') ?></code> | 
                                <strong>Categorie:</strong> <?= $template_categories[$template['category'] ?? ''] ?? $template['category'] ?? '' ?>
                            </p>
                        </div>
                        <div class="template-actions">
                            <button onclick="editTemplate(<?= $template['id'] ?? 0 ?>)" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Bewerken
                            </button>
                            <?= renderDuplicateAction('template', $template['id'] ?? 0) ?>
                            <?php if (($template['course_count'] ?? 0) == 0): ?>
                                <?= renderDeleteConfirmation('template', $template['id'] ?? 0, $template['display_name'] ?? '') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
<?php else: ?>
    <!-- COURSE MANAGEMENT -->
    
    <div class="course-grid">
        <?php if (empty($courses)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-book"></i>
                    <p>Nog geen cursussen aangemaakt.<br>Klik op "Nieuwe Cursus" om te beginnen.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
                <div class="card course-item">
                    <div class="course-header">
                        <div>
                            <h2 class="course-title"><?= htmlspecialchars($course['name'] ?? '') ?></h2>
                            <div class="course-subtitle">
                                <strong>Template:</strong> <?= htmlspecialchars($course['course_template'] ?? '') ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Course Essentials -->
                    <div class="course-essentials">
                        <div class="essential-item">
                            <div class="essential-label">Datum</div>
                            <div class="essential-value"><?= date('d M Y', strtotime($course['course_date'] ?? 'now')) ?></div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Locatie</div>
                            <div class="essential-value"><?= htmlspecialchars($course['location'] ?? 'Niet gezet') ?></div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Deelnemers</div>
                            <div class="essential-value"><?= $course['participant_count'] ?? 0 ?>/<?= $course['max_participants'] ?? 0 ?></div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <?= renderActionGroup('course', $course, ($course['participant_count'] ?? 0) > 0) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Template Modal -->
<div id="templateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="templateModalTitle">Nieuwe Template Aanmaken</h3>
            <button class="modal-close" onclick="closeModal('templateModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="templateForm">
                <input type="hidden" name="action" value="create_template" id="templateAction">
                <input type="hidden" name="template_id" id="templateId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="template_key">Template Key (Uniek)</label>
                        <input type="text" id="template_key" name="template_key" 
                               placeholder="bijv: ai-booster-intro" required>
                        <small>Gebruikt voor identificatie en URL mapping</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="display_name">Weergave Naam</label>
                        <input type="text" id="display_name" name="display_name" 
                               placeholder="bijv: AI Booster Introductie" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Categorie</label>
                        <select id="category" name="category" required>
                            <?php foreach ($template_categories as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="default_description">Standaard Beschrijving</label>
                        <textarea id="default_description" name="default_description" rows="3" required></textarea>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span id="templateSubmitText">Template Aanmaken</span>
                    </button>
                    <button type="button" onclick="closeModal('templateModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuleren
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Course Modal -->
<div id="courseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="courseModalTitle">Nieuwe Cursus Aanmaken</h3>
            <button class="modal-close" onclick="closeModal('courseModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="courseForm">
                <input type="hidden" name="action" value="create_course" id="courseAction">
                <input type="hidden" name="course_id" id="courseId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="course_name">Cursus Naam</label>
                        <input type="text" id="course_name" name="course_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_date">Datum</label>
                        <input type="date" id="course_date" name="course_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Locatie</label>
                        <select id="location" name="location" required>
                            <?php foreach ($predefined_locations as $key => $description): ?>
                                <option value="<?= $key ?>" data-description="<?= htmlspecialchars($description) ?>">
                                    <?= htmlspecialchars($key) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="location-description"></small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="course_description">Beschrijving</label>
                        <textarea id="course_description" name="course_description" rows="4" required></textarea>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span id="courseSubmitText">Cursus Aanmaken</span>
                    </button>
                    <button type="button" onclick="closeModal('courseModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuleren
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Page-specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Generate edit and reset functions for this page
    generateEditFunction('template');
    generateEditFunction('course');
    generateResetFunction('template');
    generateResetFunction('course');
    
    // Initialize location description
    updateLocationDescription();
    
    // Auto-generate template key from display name
    const displayNameField = document.getElementById('display_name');
    const templateKeyField = document.getElementById('template_key');
    
    if (displayNameField && templateKeyField) {
        displayNameField.addEventListener('input', function() {
            if (!templateKeyField.value) {
                const key = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
                    
                templateKeyField.value = key;
            }
        });
    }
    
    // Location description update
    const locationSelect = document.getElementById('location');
    if (locationSelect) {
        locationSelect.addEventListener('change', updateLocationDescription);
    }
});

// Page-specific template filling function
window.fillTemplateData = function(templateKey) {
    if (templateKey === 'general') return;
    
    const option = document.querySelector(`option[value="${templateKey}"]`);
    if (!option) return;
    
    // Fill template data (implement based on your data structure)
    showNotification('Template functionaliteit nog niet geïmplementeerd', 'info');
};
</script>

<?php
// Include shared modals
require_once 'admin_modals.php';

// Include unified footer
require_once 'admin_footer.php';
?>