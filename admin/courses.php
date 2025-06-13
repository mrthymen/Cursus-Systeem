<?php
/**
 * Cursus Beheer - Unified System FIXED v6.4.3
 * Fixed version met echte database functies
 * Converted from original courses.php to unified system
 * Updated: 2025-06-13
 * Changes: 
 * v6.4.0 - Converted to unified admin design system
 * v6.4.1 - FIXED: Added real database functions from original courses.php
 * v6.4.1 - FIXED: Restored all course/template data display
 * v6.4.1 - FIXED: Working AJAX edit functionality
 * v6.4.2 - FIXED: Replaced missing generateEditFunction with real edit functions
 * v6.4.2 - FIXED: Added working editTemplate() and editCourse() functions
 * v6.4.2 - FIXED: Added modal helper functions (openModal, closeModal)
 * v6.4.2 - FIXED: Improved fillTemplateData with better UX
 * v6.4.3 - FIXED: Better AJAX error handling and URL construction
 * v6.4.3 - FIXED: Improved JSON parsing with fallback error messages
 */

session_start();

// Set page title for header
$page_title = 'Cursus & Template Beheer';

// Include unified header
require_once 'admin_header.php';

// Include config with error handling
if (!file_exists('../includes/config.php')) {
    die('Config bestand niet gevonden. Zorg ervoor dat config.php bestaat in includes/ directory.');
}
require_once '../includes/config.php';

// Get database connection
try {
    $pdo = getDatabase();
    
    // Test database connection and verify table structure
    $test_query = $pdo->query("SHOW TABLES LIKE 'courses'");
    if (!$test_query->fetch()) {
        throw new Exception('Cursus tabel bestaat niet. Voer database setup uit.');
    }
    
} catch (Exception $e) {
    die('Database verbinding mislukt: ' . $e->getMessage());
}

// Handle AJAX requests for editing
if (isset($_GET['ajax']) && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['action']) {
            case 'get_template':
                if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                    $template = getTemplateById($pdo, $_GET['id']);
                    if ($template) {
                        echo json_encode($template);
                    } else {
                        echo json_encode(['error' => 'Template niet gevonden']);
                    }
                } else {
                    echo json_encode(['error' => 'Invalid ID parameter']);
                }
                break;
                
            case 'get_course':
                if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                    $course = getCourseById($pdo, $_GET['id']);
                    if ($course) {
                        echo json_encode($course);
                    } else {
                        echo json_encode(['error' => 'Cursus niet gevonden']);
                    }
                } else {
                    echo json_encode(['error' => 'Invalid ID parameter']);
                }
                break;
                
            case 'test':
                echo json_encode(['status' => 'success', 'message' => 'AJAX connection working!']);
                break;
                
            default:
                echo json_encode(['error' => 'Unknown action: ' . $_GET['action']]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_template':
                handleCreateTemplate($pdo);
                break;
            case 'update_template':
                handleUpdateTemplate($pdo);
                break;
            case 'delete_template':
                handleDeleteTemplate($pdo);
                break;
            case 'duplicate_template':
                handleDuplicateTemplate($pdo);
                break;
            case 'create_course':
                handleCreateCourse($pdo);
                break;
            case 'update_course':
                handleUpdateCourse($pdo);
                break;
            case 'delete_course':
                handleDeleteCourse($pdo);
                break;
            case 'duplicate_course':
                handleDuplicateCourse($pdo);
                break;
            case 'update_participant_payment':
                handleUpdateParticipantPayment($pdo);
                break;
        }
        
        header('Location: courses_unified_FIXED.php' . (isset($_GET['tab']) ? '?tab=' . $_GET['tab'] : ''));
        exit;
    }
}

// Get current tab
$current_tab = $_GET['tab'] ?? 'courses';

// Get data based on current tab
if ($current_tab === 'templates') {
    $templates = getCourseTemplates($pdo);
    $editing_template = null;
    if (isset($_GET['edit_template']) && is_numeric($_GET['edit_template'])) {
        $editing_template = getTemplateById($pdo, $_GET['edit_template']);
    }
} else {
    $courses = getCoursesWithParticipants($pdo);
    $templates = getCourseTemplates($pdo);
    $editing_course = null;
    if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $editing_course = getCourseById($pdo, $_GET['edit']);
    }
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

/**
 * REAL DATABASE FUNCTIONS (from original courses.php)
 */

function getCourseTemplates($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT ct.*, 
                   COUNT(c.id) as course_count
            FROM course_templates ct
            LEFT JOIN courses c ON ct.template_key = c.course_template
            GROUP BY ct.id
            ORDER BY ct.category, ct.display_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getCourseTemplates: " . $e->getMessage());
        return [];
    }
}

function getCoursesWithParticipants($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                c.*,
                COUNT(cp.id) as participant_count,
                SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_participants,
                SUM(CASE WHEN cp.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_participants,
                SUM(CASE WHEN cp.payment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_participants,
                COALESCE(SUM(CASE WHEN cp.payment_status = 'paid' THEN c.price ELSE 0 END), 0) as course_revenue,
                GROUP_CONCAT(
                    CASE WHEN cp.payment_status != 'cancelled' THEN
                        CONCAT(u.name, '|', u.email, '|', COALESCE(u.company, ''), '|', cp.payment_status, '|', DATE_FORMAT(cp.enrollment_date, '%d-%m-%Y'))
                    END SEPARATOR ';;;'
                ) as participants_data
            FROM courses c
            LEFT JOIN course_participants cp ON c.id = cp.course_id
            LEFT JOIN users u ON cp.user_id = u.id
            GROUP BY c.id
            ORDER BY c.course_date DESC, c.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getCoursesWithParticipants: " . $e->getMessage());
        
        // Fallback to basic courses query if participants table doesn't exist
        try {
            $stmt = $pdo->query("
                SELECT c.*,
                       0 as participant_count,
                       0 as paid_participants,
                       0 as pending_participants,
                       0 as cancelled_participants,
                       0 as course_revenue,
                       '' as participants_data
                FROM courses c
                ORDER BY c.course_date DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            error_log("Error in fallback query: " . $e2->getMessage());
            return [];
        }
    }
}

function getTemplateById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM course_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getTemplateById: " . $e->getMessage());
        return null;
    }
}

function getCourseById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getCourseById: " . $e->getMessage());
        return null;
    }
}

function parseParticipantsData($participants_data) {
    if (empty($participants_data)) return [];
    
    $participants = [];
    $entries = explode(';;;', $participants_data);
    
    foreach ($entries as $entry) {
        if (empty($entry)) continue;
        
        $parts = explode('|', $entry);
        if (count($parts) >= 5) {
            $participants[] = [
                'name' => $parts[0] ?: 'Onbekend',
                'email' => $parts[1] ?: '',
                'company' => $parts[2] ?: '',
                'payment_status' => $parts[3] ?: 'pending',
                'enrollment_date' => $parts[4] ?: ''
            ];
        }
    }
    
    return $participants;
}

function formatCourseDate($date) {
    if (empty($date)) return 'Datum niet gezet';
    
    $timestamp = strtotime($date);
    $now = time();
    $diff = $timestamp - $now;
    
    $formatted = date('d M Y', $timestamp);
    $day_name = date('l', $timestamp);
    
    $day_names = [
        'Monday' => 'Ma', 'Tuesday' => 'Di', 'Wednesday' => 'Wo',
        'Thursday' => 'Do', 'Friday' => 'Vr', 'Saturday' => 'Za', 'Sunday' => 'Zo'
    ];
    
    $dutch_day = $day_names[$day_name] ?? $day_name;
    
    if ($diff < 0) {
        $status = 'verlopen';
        $class = 'expired';
    } elseif ($diff < 86400 * 7) {
        $status = 'deze week';
        $class = 'soon';
    } elseif ($diff < 86400 * 30) {
        $status = 'binnenkort';
        $class = 'upcoming';
    } else {
        $status = 'gepland';
        $class = 'planned';
    }
    
    return [
        'formatted' => $dutch_day . ' ' . $formatted,
        'status' => $status,
        'class' => $class
    ];
}

function generateBookingUrl($template_key, $category) {
    // Use relative path from admin directory to webroot
    $base_url = '../universal_registration_form.php';
    
    // Map template keys to URL types
    $template_mapping = [
        'ai-booster-intro' => 'introductie',
        'ai-booster-verdieping' => 'verdieping',
        'ai-booster-combi' => 'combi',
        'ai-booster-incompany' => 'incompany',
        'horeca-kassa' => 'kassa',
        'horeca-iva' => 'iva'
    ];
    
    if (isset($template_mapping[$template_key])) {
        return $base_url . '?type=' . $template_mapping[$template_key];
    } else {
        return $base_url . '?type=general&category=' . urlencode($category);
    }
}

/**
 * FORM HANDLERS (simplified versions)
 */
function handleCreateTemplate($pdo) {
    try {
        // Check if course_templates table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'course_templates'");
        if (!$stmt->fetch()) {
            $_SESSION['message'] = 'Template functionaliteit nog niet beschikbaar (course_templates tabel ontbreekt)';
            $_SESSION['message_type'] = 'warning';
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO course_templates (
                template_key, display_name, category, subcategory,
                default_description, default_target_audience, default_learning_goals,
                default_materials, default_duration_hours, default_max_participants,
                booking_form_url, incompany_available, active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $result = $stmt->execute([
            $_POST['template_key'],
            $_POST['display_name'],
            $_POST['category'],
            $_POST['subcategory'] ?? '',
            $_POST['default_description'],
            $_POST['default_target_audience'] ?? '',
            $_POST['default_learning_goals'] ?? '',
            $_POST['default_materials'] ?? '',
            (int)($_POST['default_duration_hours'] ?? 8),
            (int)($_POST['default_max_participants'] ?? 20),
            $_POST['booking_form_url'] ?? '',
            isset($_POST['incompany_available']) ? 1 : 0
        ]);
        
        if ($result) {
            $_SESSION['message'] = 'Template succesvol aangemaakt!';
            $_SESSION['message_type'] = 'success';
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij aanmaken template: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

function handleUpdateTemplate($pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE course_templates SET
                template_key = ?, display_name = ?, category = ?, subcategory = ?,
                default_description = ?, default_target_audience = ?, default_learning_goals = ?,
                default_materials = ?, default_duration_hours = ?, default_max_participants = ?,
                booking_form_url = ?, incompany_available = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $_POST['template_key'],
            $_POST['display_name'],
            $_POST['category'],
            $_POST['subcategory'] ?? '',
            $_POST['default_description'],
            $_POST['default_target_audience'] ?? '',
            $_POST['default_learning_goals'] ?? '',
            $_POST['default_materials'] ?? '',
            (int)($_POST['default_duration_hours'] ?? 8),
            (int)($_POST['default_max_participants'] ?? 20),
            $_POST['booking_form_url'] ?? '',
            isset($_POST['incompany_available']) ? 1 : 0,
            (int)$_POST['template_id']
        ]);
        
        if ($result) {
            $_SESSION['message'] = 'Template succesvol bijgewerkt!';
            $_SESSION['message_type'] = 'success';
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij bijwerken template: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

function handleDeleteTemplate($pdo) {
    try {
        $template_id = (int)$_POST['template_id'];
        
        // Check if template is used by courses
        $usage_count = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_template = (SELECT template_key FROM course_templates WHERE id = ?)");
        $usage_count->execute([$template_id]);
        $count = $usage_count->fetchColumn();
        
        if ($count > 0) {
            $_SESSION['message'] = 'Kan template met gekoppelde cursussen niet verwijderen.';
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM course_templates WHERE id = ?");
            $result = $stmt->execute([$template_id]);
            
            if ($result) {
                $_SESSION['message'] = 'Template succesvol verwijderd!';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij verwijderen template: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

function handleDuplicateTemplate($pdo) {
    try {
        // Get original template
        $stmt = $pdo->prepare("SELECT * FROM course_templates WHERE id = ?");
        $stmt->execute([(int)$_POST['template_id']]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template) {
            // Create duplicate with modified key and name
            $new_key = $template['template_key'] . '-copy';
            $new_name = $template['display_name'] . ' (Kopie)';
            
            $stmt = $pdo->prepare("
                INSERT INTO course_templates (
                    template_key, display_name, category, subcategory,
                    default_description, default_target_audience, default_learning_goals,
                    default_materials, default_duration_hours, default_max_participants,
                    booking_form_url, incompany_available, active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $result = $stmt->execute([
                $new_key, $new_name, $template['category'], $template['subcategory'],
                $template['default_description'], $template['default_target_audience'], 
                $template['default_learning_goals'], $template['default_materials'],
                $template['default_duration_hours'], $template['default_max_participants'],
                $template['booking_form_url'], $template['incompany_available']
            ]);
            
            if ($result) {
                $_SESSION['message'] = 'Template succesvol gedupliceerd!';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij dupliceren template: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

function handleCreateCourse($pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO courses (
                name, description, course_date, time_range, max_participants, price, 
                instructor_name, location, active, created_at, course_template, 
                category, subcategory, short_description, target_audience, 
                learning_goals, materials_included, booking_url, incompany_available
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $booking_url = generateBookingUrl($_POST['course_template'] ?? 'general', $_POST['category'] ?? 'algemeen');
        
        $result = $stmt->execute([
            trim($_POST['course_name']),
            trim($_POST['course_description']),
            $_POST['course_date'],
            $_POST['course_time'] ?? '',
            (int)($_POST['max_participants'] ?? 20),
            (float)($_POST['price'] ?? 0),
            trim($_POST['instructor'] ?? 'Martijn Planken'),
            trim($_POST['location'] ?? ''),
            $_POST['course_template'] ?? 'general',
            $_POST['category'] ?? 'algemeen',
            $_POST['subcategory'] ?? '',
            trim($_POST['short_description'] ?? ''),
            trim($_POST['target_audience'] ?? ''),
            trim($_POST['learning_goals'] ?? ''),
            trim($_POST['materials_included'] ?? ''),
            $booking_url,
            isset($_POST['incompany_available']) ? 1 : 0
        ]);
        
        if ($result) {
            $_SESSION['message'] = 'Cursus succesvol aangemaakt!';
            $_SESSION['message_type'] = 'success';
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij aanmaken cursus: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

function handleUpdateCourse($pdo) {
    try {
        $booking_url = generateBookingUrl($_POST['course_template'] ?? 'general', $_POST['category'] ?? 'algemeen');
        
        $stmt = $pdo->prepare("
            UPDATE courses SET
                name = ?, description = ?, course_date = ?, time_range = ?,
                max_participants = ?, price = ?, instructor_name = ?, location = ?,
                course_template = ?, category = ?, subcategory = ?, 
                short_description = ?, target_audience = ?, learning_goals = ?, 
                materials_included = ?, booking_url = ?, incompany_available = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            trim($_POST['course_name']),
            trim($_POST['course_description']),
            $_POST['course_date'],
            $_POST['course_time'] ?? '',
            (int)($_POST['max_participants'] ?? 20),
            (float)($_POST['price'] ?? 0),
            trim($_POST['instructor'] ?? 'Martijn Planken'),
            trim($_POST['location'] ?? ''),
            $_POST['course_template'] ?? 'general',
            $_POST['category'] ?? 'algemeen', 
            $_POST['subcategory'] ?? '',
            trim($_POST['short_description'] ?? ''),
            trim($_POST['target_audience'] ?? ''),
            trim($_POST['learning_goals'] ?? ''),
            trim($_POST['materials_included'] ?? ''),
            $booking_url,
            isset($_POST['incompany_available']) ? 1 : 0,
            (int)$_POST['course_id']
        ]);
        
        if ($result) {
            $_SESSION['message'] = 'Cursus succesvol bijgewerkt!';
            $_SESSION['message_type'] = 'success';
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij bijwerken cursus: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

function handleDeleteCourse($pdo) {
    try {
        $course_id = (int)$_POST['course_id'];
        
        // Check if course has participants (with fallback)
        try {
            $participant_count = $pdo->prepare("SELECT COUNT(*) FROM course_participants WHERE course_id = ?");
            $participant_count->execute([$course_id]);
            $count = $participant_count->fetchColumn();
        } catch (Exception $e) {
            // Table might not exist, allow deletion
            $count = 0;
        }
        
        if ($count > 0) {
            $_SESSION['message'] = 'Kan cursus met ingeschreven deelnemers niet verwijderen.';
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $result = $stmt->execute([$course_id]);
            
            if ($result) {
                $_SESSION['message'] = 'Cursus succesvol verwijderd!';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij verwijderen cursus: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

function handleDuplicateCourse($pdo) {
    try {
        // Get original course
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([(int)$_POST['course_id']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($course) {
            // Create duplicate with modified name and future date
            $new_name = $course['name'] . ' (Kopie)';
            $new_date = date('Y-m-d', strtotime('+1 month'));
            
            $stmt = $pdo->prepare("
                INSERT INTO courses (
                    name, description, course_date, time_range, max_participants, price, 
                    instructor_name, location, active, created_at, course_template, 
                    category, subcategory, short_description, target_audience, 
                    learning_goals, materials_included, booking_url, incompany_available
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $new_name, $course['description'], $new_date, $course['time_range'],
                $course['max_participants'], $course['price'], $course['instructor_name'],
                $course['location'], $course['course_template'], $course['category'],
                $course['subcategory'], $course['short_description'], $course['target_audience'],
                $course['learning_goals'], $course['materials_included'], $course['booking_url'],
                $course['incompany_available']
            ]);
            
            if ($result) {
                $_SESSION['message'] = 'Cursus succesvol gedupliceerd!';
                $_SESSION['message_type'] = 'success';
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij dupliceren cursus: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

function handleUpdateParticipantPayment($pdo) {
    $_SESSION['message'] = 'Participant payment update functionaliteit komt binnenkort beschikbaar';
    $_SESSION['message_type'] = 'info';
}

// Include shared modals helper
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
                                <?php if (!empty($template['subcategory'])): ?>
                                    → <?= htmlspecialchars($template['subcategory']) ?>
                                <?php endif; ?>
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
                    
                    <?php if (!empty($template['default_description'])): ?>
                        <div style="background: #f8fafc; padding: 1rem; border-radius: 6px; margin: 1rem 0;">
                            <p style="color: #374151; margin-bottom: 1rem;">
                                <?= nl2br(htmlspecialchars($template['default_description'])) ?>
                            </p>
                            
                            <div class="template-meta">
                                <div>
                                    <strong>Doelgroep:</strong><br>
                                    <small><?= htmlspecialchars($template['default_target_audience'] ?? 'Niet gespecificeerd') ?></small>
                                </div>
                                <div>
                                    <strong>Duur:</strong><br>
                                    <small><?= $template['default_duration_hours'] ?? 8 ?> uur</small>
                                </div>
                                <div>
                                    <strong>Max Deelnemers:</strong><br>
                                    <small><?= $template['default_max_participants'] ?? 20 ?></small>
                                </div>
                                <div>
                                    <strong>Incompany:</strong><br>
                                    <small><?= !empty($template['incompany_available']) ? 'Ja' : 'Nee' ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
                <?php 
                $date_info = formatCourseDate($course['course_date']);
                $participants = parseParticipantsData($course['participants_data'] ?? '');
                $booking_url = $course['booking_url'] ?? generateBookingUrl($course['course_template'] ?? 'general', $course['category'] ?? 'algemeen');
                ?>
                <div class="card course-item">
                    <div class="course-header">
                        <div>
                            <h2 class="course-title"><?= htmlspecialchars($course['name'] ?? '') ?></h2>
                            <div class="course-subtitle">
                                <strong>Template:</strong> <?= htmlspecialchars($course['course_template'] ?? 'Geen') ?> | 
                                <strong>Categorie:</strong> <?= $template_categories[$course['category'] ?? ''] ?? $course['category'] ?? 'Onbekend' ?>
                            </div>
                        </div>
                        <span class="date-status <?= $date_info['class'] ?>">
                            <?= $date_info['status'] ?>
                        </span>
                    </div>
                    
                    <!-- Course Essentials -->
                    <div class="course-essentials">
                        <div class="essential-item">
                            <div class="essential-label">Datum</div>
                            <div class="essential-value"><?= $date_info['formatted'] ?></div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Tijd</div>
                            <div class="essential-value"><?= htmlspecialchars($course['time_range'] ?? 'Niet gezet') ?></div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Locatie</div>
                            <div class="essential-value"><?= htmlspecialchars($course['location'] ?? 'Niet gezet') ?></div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Inschrijvingen</div>
                            <div class="essential-value"><?= $course['participant_count'] ?? 0 ?>/<?= $course['max_participants'] ?? 0 ?></div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Betaald</div>
                            <div class="essential-value"><?= $course['paid_participants'] ?? 0 ?></div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Omzet</div>
                            <div class="essential-value">€<?= number_format($course['course_revenue'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                    </div>
                    
                    <!-- Participants Preview -->
                    <?php if (!empty($participants)): ?>
                        <div class="participants-preview">
                            <div class="participants-header">
                                <span><i class="fas fa-users"></i> Deelnemers (<?= count($participants) ?>)</span>
                            </div>
                            <div class="participant-list">
                                <?php foreach ($participants as $participant): ?>
                                    <div class="participant-item">
                                        <div>
                                            <div class="participant-name"><?= htmlspecialchars($participant['name']) ?></div>
                                            <?php if ($participant['company']): ?>
                                                <div class="participant-company"><?= htmlspecialchars($participant['company']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--neutral);">
                                            <?= $participant['enrollment_date'] ?>
                                        </div>
                                        <span class="payment-badge <?= $participant['payment_status'] ?>">
                                            <?= ucfirst($participant['payment_status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="participants-preview">
                            <div class="no-participants">
                                <i class="fas fa-user-slash"></i> Nog geen inschrijvingen
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="btn-group">
                        <button onclick="editCourse(<?= $course['id'] ?? 0 ?>)" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Bewerken
                        </button>
                        
                        <a href="<?= htmlspecialchars($booking_url) ?>" target="_blank" class="btn btn-success">
                            <i class="fas fa-external-link-alt"></i> Inschrijven
                        </a>
                        
                        <?= renderDuplicateAction('course', $course['id'] ?? 0) ?>
                        
                        <?php if (($course['participant_count'] ?? 0) == 0): ?>
                            <?= renderDeleteConfirmation('course', $course['id'] ?? 0, $course['name'] ?? '') ?>
                        <?php endif; ?>
                    </div>
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
                    
                    <div class="form-group">
                        <label for="subcategory">Subcategorie</label>
                        <input type="text" id="subcategory" name="subcategory" 
                               placeholder="bijv: introductie, verdieping">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="default_description">Standaard Beschrijving</label>
                        <textarea id="default_description" name="default_description" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="default_target_audience">Standaard Doelgroep</label>
                        <textarea id="default_target_audience" name="default_target_audience" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="default_learning_goals">Standaard Leerdoelen</label>
                        <textarea id="default_learning_goals" name="default_learning_goals" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="default_materials">Standaard Materialen</label>
                        <textarea id="default_materials" name="default_materials" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="default_duration_hours">Standaard Duur (uren)</label>
                        <input type="number" id="default_duration_hours" name="default_duration_hours" 
                               value="8" min="1" max="16" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="default_max_participants">Standaard Max Deelnemers</label>
                        <input type="number" id="default_max_participants" name="default_max_participants" 
                               value="20" min="1" max="100" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="booking_form_url">Booking Formulier URL</label>
                        <input type="text" id="booking_form_url" name="booking_form_url" 
                               placeholder="universal_registration_form.php?type=introductie">
                        <small>Relatieve URL naar het inschrijfformulier</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="incompany_available" value="1" id="incompany_available">
                            Incompany beschikbaar
                        </label>
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
                        <label for="course_template">Cursus Template</label>
                        <select id="course_template" name="course_template" onchange="fillTemplateData(this.value)">
                            <option value="general">Aangepast (geen template)</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?= htmlspecialchars($template['template_key'] ?? '') ?>" 
                                        data-category="<?= htmlspecialchars($template['category'] ?? '') ?>"
                                        data-subcategory="<?= htmlspecialchars($template['subcategory'] ?? '') ?>"
                                        data-description="<?= htmlspecialchars($template['default_description'] ?? '') ?>"
                                        data-target="<?= htmlspecialchars($template['default_target_audience'] ?? '') ?>"
                                        data-goals="<?= htmlspecialchars($template['default_learning_goals'] ?? '') ?>"
                                        data-materials="<?= htmlspecialchars($template['default_materials'] ?? '') ?>"
                                        data-duration="<?= $template['default_duration_hours'] ?? 8 ?>"
                                        data-maxparticipants="<?= $template['default_max_participants'] ?? 20 ?>">
                                    <?= htmlspecialchars($template['display_name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Template selecteren vult automatisch de velden in</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_name">Cursus Naam</label>
                        <input type="text" id="course_name" name="course_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructor">Instructeur</label>
                        <input type="text" id="instructor" name="instructor" value="Martijn Planken">
                    </div>
                    
                    <div class="form-group">
                        <label for="course_category">Categorie</label>
                        <select id="course_category" name="category">
                            <?php foreach ($template_categories as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $key === 'algemeen' ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_subcategory">Subcategorie</label>
                        <input type="text" id="course_subcategory" name="subcategory">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="short_description">Korte Beschrijving</label>
                        <textarea id="short_description" name="short_description" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="course_description">Volledige Beschrijving</label>
                        <textarea id="course_description" name="course_description" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="target_audience">Doelgroep</label>
                        <textarea id="target_audience" name="target_audience" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="learning_goals">Leerdoelen</label>
                        <textarea id="learning_goals" name="learning_goals" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="materials_included">Inbegrepen Materialen</label>
                        <textarea id="materials_included" name="materials_included" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_date">Datum</label>
                        <input type="date" id="course_date" name="course_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_time">Tijdstip</label>
                        <input type="text" id="course_time" name="course_time" 
                               placeholder="09:00 - 17:00" required>
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
                    
                    <div class="form-group">
                        <label for="max_participants">Max Deelnemers</label>
                        <input type="number" id="max_participants" name="max_participants" 
                               min="1" value="20" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Prijs (€)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="incompany_available" value="1" id="course_incompany_available">
                            Incompany beschikbaar
                        </label>
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
// Debug mode - set to true for extra logging
const DEBUG_MODE = true;

// Page-specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    if (DEBUG_MODE) console.log('Courses page loaded, initializing...');
    
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

// Template Edit Function
window.editTemplate = async function(templateId) {
    if (DEBUG_MODE) console.log('Editing template:', templateId);
    
    try {
        const url = window.location.pathname + '?ajax=1&action=get_template&id=' + encodeURIComponent(templateId);
        if (DEBUG_MODE) console.log('AJAX URL:', url);
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        
        const text = await response.text();
        if (DEBUG_MODE) console.log('Raw response:', text);
        
        let template;
        
        try {
            template = JSON.parse(text);
        } catch (parseError) {
            console.error('Response was not JSON:', text);
            throw new Error('Server returned invalid JSON. Check PHP errors.');
        }
        
        if (template.error) {
            alert('❌ ' + template.error);
            return;
        }
        
        // Fill form fields
        document.getElementById('templateAction').value = 'update_template';
        document.getElementById('templateId').value = template.id;
        document.getElementById('template_key').value = template.template_key || '';
        document.getElementById('display_name').value = template.display_name || '';
        document.getElementById('category').value = template.category || '';
        document.getElementById('subcategory').value = template.subcategory || '';
        document.getElementById('default_description').value = template.default_description || '';
        document.getElementById('default_target_audience').value = template.default_target_audience || '';
        document.getElementById('default_learning_goals').value = template.default_learning_goals || '';
        document.getElementById('default_materials').value = template.default_materials || '';
        document.getElementById('default_duration_hours').value = template.default_duration_hours || 8;
        document.getElementById('default_max_participants').value = template.default_max_participants || 20;
        document.getElementById('booking_form_url').value = template.booking_form_url || '';
        document.getElementById('incompany_available').checked = template.incompany_available == 1;
        
        if (DEBUG_MODE) console.log('Template data loaded successfully:', template);
        
        // Update modal title and submit button
        document.getElementById('templateModalTitle').textContent = 'Template Bewerken';
        document.getElementById('templateSubmitText').textContent = 'Template Bijwerken';
        
        // Open modal
        openModal('templateModal');
        
    } catch (error) {
        console.error('AJAX Error Details:', {
            templateId: templateId,
            url: url,
            error: error.message
        });
        alert('❌ Fout bij ophalen template: ' + error.message + '\n\nCheck de browser console voor meer details.');
    }
};

// Test function - call testAjax() in browser console to check connection
window.testAjax = async function() {
    try {
        const url = window.location.pathname + '?ajax=1&action=test';
        console.log('Testing AJAX with URL:', url);
        
        const response = await fetch(url);
        console.log('Response status:', response.status);
        console.log('Response headers:', [...response.headers.entries()]);
        
        const text = await response.text();
        console.log('Raw response text:', text);
        
        const result = JSON.parse(text);
        console.log('Parsed JSON:', result);
        
        alert('AJAX test successful: ' + result.message);
    } catch (error) {
        console.error('AJAX test failed:', error);
        alert('AJAX test failed: ' + error.message);
    }
};

// Course Edit Function
window.editCourse = async function(courseId) {
    if (DEBUG_MODE) console.log('Editing course:', courseId);
    
    try {
        const url = window.location.pathname + '?ajax=1&action=get_course&id=' + encodeURIComponent(courseId);
        if (DEBUG_MODE) console.log('AJAX URL:', url);
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        
        const text = await response.text();
        if (DEBUG_MODE) console.log('Raw response:', text);
        
        let course;
        
        try {
            course = JSON.parse(text);
        } catch (parseError) {
            console.error('Response was not JSON:', text);
            console.error('Parse error:', parseError);
            throw new Error('Server returned invalid JSON. Check PHP errors.');
        }
        
        if (course.error) {
            alert('❌ ' + course.error);
            return;
        }
        
        // Fill form fields
        document.getElementById('courseAction').value = 'update_course';
        document.getElementById('courseId').value = course.id;
        document.getElementById('course_template').value = course.course_template || 'general';
        document.getElementById('course_name').value = course.name || '';
        document.getElementById('instructor').value = course.instructor_name || '';
        document.getElementById('course_category').value = course.category || '';
        document.getElementById('course_subcategory').value = course.subcategory || '';
        document.getElementById('short_description').value = course.short_description || '';
        document.getElementById('course_description').value = course.description || '';
        document.getElementById('target_audience').value = course.target_audience || '';
        document.getElementById('learning_goals').value = course.learning_goals || '';
        document.getElementById('materials_included').value = course.materials_included || '';
        document.getElementById('course_date').value = course.course_date || '';
        document.getElementById('course_time').value = course.time_range || '';
        document.getElementById('location').value = course.location || '';
        document.getElementById('max_participants').value = course.max_participants || 20;
        document.getElementById('price').value = course.price || 0;
        document.getElementById('course_incompany_available').checked = course.incompany_available == 1;
        
        if (DEBUG_MODE) console.log('Course data loaded successfully:', course);
        
        // Update modal title and submit button
        document.getElementById('courseModalTitle').textContent = 'Cursus Bewerken';
        document.getElementById('courseSubmitText').textContent = 'Cursus Bijwerken';
        
        // Update location description
        updateLocationDescription();
        
        // Open modal
        openModal('courseModal');
        
    } catch (error) {
        console.error('AJAX Error Details:', {
            courseId: courseId,
            url: url,
            error: error.message
        });
        alert('❌ Fout bij ophalen cursus: ' + error.message + '\n\nCheck de browser console voor meer details.');
    }
};

// Reset functions for new items
window.resetTemplateForm = function() {
    document.getElementById('templateForm').reset();
    document.getElementById('templateAction').value = 'create_template';
    document.getElementById('templateId').value = '';
    document.getElementById('templateModalTitle').textContent = 'Nieuwe Template Aanmaken';
    document.getElementById('templateSubmitText').textContent = 'Template Aanmaken';
};

window.resetCourseForm = function() {
    document.getElementById('courseForm').reset();
    document.getElementById('courseAction').value = 'create_course';
    document.getElementById('courseId').value = '';
    document.getElementById('courseModalTitle').textContent = 'Nieuwe Cursus Aanmaken';
    document.getElementById('courseSubmitText').textContent = 'Cursus Aanmaken';
    updateLocationDescription();
};

// Update location description helper
window.updateLocationDescription = function() {
    const locationSelect = document.getElementById('location');
    const descriptionElement = document.getElementById('location-description');
    
    if (locationSelect && descriptionElement) {
        const selectedOption = locationSelect.selectedOptions[0];
        const description = selectedOption ? selectedOption.dataset.description : '';
        descriptionElement.textContent = description || '';
    }
};

// Modal helper functions
window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        
        // Reset form if it's a new item
        if (modalId === 'templateModal' && !document.getElementById('templateId').value) {
            resetTemplateForm();
        }
        if (modalId === 'courseModal' && !document.getElementById('courseId').value) {
            resetCourseForm();
        }
    }
};

window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
};

// Page-specific template filling function
window.fillTemplateData = function(templateKey) {
    if (templateKey === 'general') {
        // Clear fields when general is selected
        document.getElementById('course_category').value = 'algemeen';
        document.getElementById('course_subcategory').value = '';
        document.getElementById('course_description').value = '';
        document.getElementById('short_description').value = '';
        document.getElementById('target_audience').value = '';
        document.getElementById('learning_goals').value = '';
        document.getElementById('materials_included').value = '';
        document.getElementById('max_participants').value = '20';
        return;
    }
    
    const option = document.querySelector(`option[value="${templateKey}"]`);
    if (!option) return;
    
    // Auto-fill fields from template data
    const fields = {
        course_category: option.dataset.category || 'algemeen',
        course_subcategory: option.dataset.subcategory || '',
        course_description: option.dataset.description || '',
        short_description: option.dataset.description ? option.dataset.description.substring(0, 150) + '...' : '',
        target_audience: option.dataset.target || '',
        learning_goals: option.dataset.goals || '',
        materials_included: option.dataset.materials || '',
        max_participants: option.dataset.maxparticipants || '20'
    };
    
    // Fill the form fields
    Object.keys(fields).forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field && fields[fieldName]) {
            field.value = fields[fieldName];
        }
    });
    
    // Show subtle confirmation
    const templateSelect = document.getElementById('course_template');
    if (templateSelect) {
        templateSelect.style.background = '#e8f5e8';
        setTimeout(() => {
            templateSelect.style.background = '';
        }, 1000);
    }
};
</script>

<?php
require_once 'admin_footer.php';
?>