<?php
/**
 * Cursus Systeem - Course Management v6.4.1
 * Modal-based template management with smart course overview & AJAX editing
 * Features: Modal forms, Smart participant display, Enhanced course metrics, AJAX edit
 * Updated: 2025-06-13
 * Changes: 
 * v6.4.0 - Moved forms to modals for better UX
 * v6.4.0 - Enhanced course list with date, location, participants
 * v6.4.0 - Added participant details per course
 * v6.4.0 - Removed distracting booking URL blocks
 * v6.4.0 - Added quick action buttons in course rows
 * v6.4.1 - Added AJAX editing functionality for modals
 * v6.4.1 - Smart URL handling for direct edit links
 * v6.4.1 - Enhanced error handling and user feedback
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=courses.php');
    exit;
}

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
        
        header('Location: courses.php' . (isset($_GET['tab']) ? '?tab=' . $_GET['tab'] : ''));
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

// Predefined locations
$predefined_locations = [
    'The Rise Tilburg' => 'The Rise, Spoorlaan 444, 5038 CG Tilburg',
    'Online' => 'Online via Zoom/Teams',
    'Incompany' => 'Op locatie bij de klant',
    'Eigen Locatie' => 'Eigen trainingslocatie Inventijn'
];

// Template categories
$template_categories = [
    'ai-training' => 'AI Training',
    'horeca' => 'Horeca Training', 
    'marketing' => 'Marketing',
    'management' => 'Management',
    'algemeen' => 'Algemeen'
];

/**
 * TEMPLATE MANAGEMENT FUNCTIONS
 */
function handleCreateTemplate($pdo) {
    try {
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
            $_POST['default_target_audience'],
            $_POST['default_learning_goals'],
            $_POST['default_materials'],
            (int)$_POST['default_duration_hours'],
            (int)$_POST['default_max_participants'],
            $_POST['booking_form_url'],
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
            $_POST['default_target_audience'],
            $_POST['default_learning_goals'],
            $_POST['default_materials'],
            (int)$_POST['default_duration_hours'],
            (int)$_POST['default_max_participants'],
            $_POST['booking_form_url'],
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
        // Check if template is used by courses
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_template = (SELECT template_key FROM course_templates WHERE id = ?)");
        $stmt->execute([(int)$_POST['template_id']]);
        $usage_count = $stmt->fetchColumn();
        
        if ($usage_count > 0) {
            $_SESSION['message'] = "Template kan niet verwijderd worden: wordt gebruikt door $usage_count cursus(sen)";
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM course_templates WHERE id = ?");
            $result = $stmt->execute([(int)$_POST['template_id']]);
            
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
            // Create duplicate with modified key
            $new_key = $template['template_key'] . '_copy_' . date('Ymd');
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

/**
 * COURSE MANAGEMENT FUNCTIONS
 */
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
        
        $booking_url = generateBookingUrl($_POST['course_template'], $_POST['category']);
        
        $result = $stmt->execute([
            trim($_POST['course_name']),
            trim($_POST['course_description']),
            $_POST['course_date'],
            $_POST['course_time'],
            (int)$_POST['max_participants'],
            (float)$_POST['price'],
            trim($_POST['instructor']),
            trim($_POST['location']),
            $_POST['course_template'] ?? 'general',
            $_POST['category'] ?? 'general',
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
        $booking_url = generateBookingUrl($_POST['course_template'], $_POST['category']);
        
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
            $_POST['course_time'],
            (int)$_POST['max_participants'],
            (float)$_POST['price'],
            trim($_POST['instructor']),
            trim($_POST['location']),
            $_POST['course_template'] ?? 'general',
            $_POST['category'] ?? 'general', 
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
        
        // Check if course has participants
        $participant_count = $pdo->prepare("SELECT COUNT(*) FROM course_participants WHERE course_id = ?");
        $participant_count->execute([$course_id]);
        $count = $participant_count->fetchColumn();
        
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
    try {
        $stmt = $pdo->prepare("UPDATE course_participants SET payment_status = ?, payment_date = NOW() WHERE id = ?");
        $result = $stmt->execute([$_POST['payment_status'], (int)$_POST['participant_id']]);
        
        if ($result) {
            $_SESSION['message'] = 'Betalingsstatus bijgewerkt!';
            $_SESSION['message_type'] = 'success';
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij bijwerken betaling: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

/**
 * HELPER FUNCTIONS
 */
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
        return [];
    }
}

function getTemplateById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM course_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
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
                        CONCAT(u.name, '|', u.email, '|', u.company, '|', cp.payment_status, '|', DATE_FORMAT(cp.enrollment_date, '%d-%m-%Y'))
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
        return [];
    }
}

function getCourseById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
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
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursus & Template Beheer - Cursus Systeem v6.4.1</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Enhanced v6.4 Design System with Modal Support */
        :root {
            --primary: #2563eb;
            --success: #059669;
            --warning: #d97706;
            --error: #dc2626;
            --neutral: #6b7280;
            --background: #f9fafb;
            --surface: #ffffff;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-lg: 0 4px 12px rgba(0,0,0,0.1);
            --z-modal: 1000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background);
            color: #333;
            line-height: 1.5;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header */
        .header {
            background: var(--primary);
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .nav {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .nav a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .nav a:hover,
        .nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        /* Tab System */
        .tab-navigation {
            background: white;
            border-radius: var(--radius);
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            gap: 0.5rem;
            justify-content: space-between;
            align-items: center;
        }

        .tab-links {
            display: flex;
            gap: 0.5rem;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: var(--neutral);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-button.active {
            background: var(--primary);
            color: white;
        }

        .tab-button:hover:not(.active) {
            background: #f3f4f6;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .message.success {
            background: #ecfdf5;
            color: #065f46;
            border-color: var(--success);
        }

        .message.error {
            background: #fef2f2;
            color: #991b1b;
            border-color: var(--error);
        }

        /* Cards */
        .card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            color: #1f2937;
            font-size: 1.2rem;
        }

        /* Modal System */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: var(--z-modal);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 90vw;
            max-height: 90vh;
            width: 800px;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal.active .modal-content {
            transform: scale(1);
        }

        .modal-header {
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--neutral);
            cursor: pointer;
            padding: 0.5rem;
            margin-left: auto;
        }

        .modal-close:hover {
            color: var(--error);
        }

        /* Forms */
        .form-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        input, select, textarea {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        /* Enhanced Course Items */
        .course-grid {
            display: grid;
            gap: 1.5rem;
        }

        .course-item {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            border-left: 4px solid #e5e7eb;
            transition: all 0.2s;
        }

        .course-item:hover {
            border-left-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .course-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: start;
            margin-bottom: 1rem;
        }

        .course-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 0.5rem 0;
        }

        .course-subtitle {
            color: var(--neutral);
            font-size: 0.9rem;
        }

        .course-essentials {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }

        .essential-item {
            text-align: center;
        }

        .essential-label {
            font-size: 11px;
            color: var(--neutral);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.25rem;
            letter-spacing: 0.5px;
        }

        .essential-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }

        .date-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .date-status.soon {
            background: #fef3c7;
            color: #92400e;
        }

        .date-status.upcoming {
            background: #dbeafe;
            color: #1e40af;
        }

        .date-status.planned {
            background: #f3f4f6;
            color: #6b7280;
        }

        .date-status.expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .participants-preview {
            background: #f0f9ff;
            border: 1px solid #93c5fd;
            border-radius: 6px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .participants-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #1e40af;
        }

        .participant-list {
            display: grid;
            gap: 0.5rem;
        }

        .participant-item {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 0.75rem;
            align-items: center;
            padding: 0.5rem;
            background: rgba(255,255,255,0.7);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .participant-name {
            font-weight: 500;
            color: #1f2937;
        }

        .participant-company {
            color: var(--neutral);
            font-size: 0.8rem;
        }

        .payment-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .payment-badge.paid {
            background: #d1fae5;
            color: #065f46;
        }

        .payment-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .payment-badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .no-participants {
            text-align: center;
            color: var(--neutral);
            font-style: italic;
            padding: 1rem;
        }

        /* Template Items */
        .template-item {
            border-left: 3px solid #e5e7eb;
            transition: border-color 0.2s;
        }

        .template-item:hover {
            border-left-color: var(--primary);
        }

        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .template-info {
            flex: 1;
        }

        .template-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .template-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            font-size: 14px;
        }

        .template-usage {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            color: #1e40af;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .nav, .tab-navigation {
                flex-direction: column;
            }
            
            .template-header, .course-header {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .course-essentials {
                grid-template-columns: repeat(2, 1fr);
            }

            .participant-item {
                grid-template-columns: 1fr;
                gap: 0.25rem;
            }

            .modal-content {
                width: 95vw;
                margin: 1rem;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--neutral);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-graduation-cap"></i> Cursus & Template Beheer</h1>
            <div class="nav">
                <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="planning.php"><i class="fas fa-calendar"></i> Planning</a>
                <a href="courses.php" class="active"><i class="fas fa-book"></i> Cursussen</a>
                <a href="users.php"><i class="fas fa-users"></i> Gebruikers</a>
                <a href="certificates.php"><i class="fas fa-certificate"></i> Certificaten</a>
            </div>
        </div>

        <!-- Tab Navigation with Action Buttons -->
        <div class="tab-navigation">
            <div class="tab-links">
                <a href="courses.php?tab=courses" class="tab-button <?= $current_tab === 'courses' ? 'active' : '' ?>">
                    <i class="fas fa-book"></i> Cursussen (<?= count($courses ?? []) ?>)
                </a>
                <a href="courses.php?tab=templates" class="tab-button <?= $current_tab === 'templates' ? 'active' : '' ?>">
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
            
            <!-- Templates List -->
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
                                        <?= htmlspecialchars($template['display_name']) ?>
                                        <span class="template-usage"><?= $template['course_count'] ?> cursussen</span>
                                    </h3>
                                    <p style="color: #6b7280; margin-bottom: 1rem;">
                                        <strong>Key:</strong> <code><?= htmlspecialchars($template['template_key']) ?></code> | 
                                        <strong>Categorie:</strong> <?= $template_categories[$template['category']] ?? $template['category'] ?>
                                        <?php if ($template['subcategory']): ?>
                                            → <?= htmlspecialchars($template['subcategory']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="template-actions">
                                    <button onclick="editTemplate(<?= $template['id'] ?>)" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Bewerken
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="duplicate_template">
                                        <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-copy"></i> Dupliceer
                                        </button>
                                    </form>
                                    <?php if ($template['course_count'] == 0): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Weet je zeker dat je deze template wilt verwijderen?')">
                                            <input type="hidden" name="action" value="delete_template">
                                            <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-trash"></i> Verwijderen
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="background: #f8fafc; padding: 1rem; border-radius: 6px; margin: 1rem 0;">
                                <p style="color: #374151; margin-bottom: 1rem;">
                                    <?= nl2br(htmlspecialchars($template['default_description'])) ?>
                                </p>
                                
                                <div class="template-meta">
                                    <div>
                                        <strong>Doelgroep:</strong><br>
                                        <small><?= htmlspecialchars($template['default_target_audience'] ?: 'Niet gespecificeerd') ?></small>
                                    </div>
                                    <div>
                                        <strong>Duur:</strong><br>
                                        <small><?= $template['default_duration_hours'] ?> uur</small>
                                    </div>
                                    <div>
                                        <strong>Max Deelnemers:</strong><br>
                                        <small><?= $template['default_max_participants'] ?></small>
                                    </div>
                                    <div>
                                        <strong>Incompany:</strong><br>
                                        <small><?= $template['incompany_available'] ? 'Ja' : 'Nee' ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- COURSE MANAGEMENT -->
            
            <!-- Courses List -->
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
                        $participants = parseParticipantsData($course['participants_data']);
                        $booking_url = $course['booking_url'] ?: generateBookingUrl($course['course_template'], $course['category']);
                        ?>
                        <div class="card course-item">
                            <div class="course-header">
                                <div>
                                    <h2 class="course-title"><?= htmlspecialchars($course['name']) ?></h2>
                                    <div class="course-subtitle">
                                        <strong>Template:</strong> <?= htmlspecialchars($course['course_template']) ?> | 
                                        <strong>Categorie:</strong> <?= $template_categories[$course['category']] ?? $course['category'] ?>
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
                                    <div class="essential-value"><?= htmlspecialchars($course['time_range'] ?: 'Niet gezet') ?></div>
                                </div>
                                <div class="essential-item">
                                    <div class="essential-label">Locatie</div>
                                    <div class="essential-value"><?= htmlspecialchars($course['location'] ?: 'Niet gezet') ?></div>
                                </div>
                                <div class="essential-item">
                                    <div class="essential-label">Inschrijvingen</div>
                                    <div class="essential-value"><?= $course['participant_count'] ?>/<?= $course['max_participants'] ?></div>
                                </div>
                                <div class="essential-item">
                                    <div class="essential-label">Betaald</div>
                                    <div class="essential-value"><?= $course['paid_participants'] ?: 0 ?></div>
                                </div>
                                <div class="essential-item">
                                    <div class="essential-label">Omzet</div>
                                    <div class="essential-value">€<?= number_format($course['course_revenue'], 0, ',', '.') ?></div>
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
                                <button onclick="editCourse(<?= $course['id'] ?>)" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Bewerken
                                </button>
                                
                                <a href="<?= htmlspecialchars($booking_url) ?>" target="_blank" class="btn btn-success">
                                    <i class="fas fa-external-link-alt"></i> Inschrijven
                                </a>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="duplicate_course">
                                    <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-copy"></i> Dupliceer
                                    </button>
                                </form>
                                
                                <?php if ($course['participant_count'] == 0): ?>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Weet je zeker dat je deze cursus wilt verwijderen?')">
                                        <input type="hidden" name="action" value="delete_course">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Verwijderen
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

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
                        <!-- Template Key -->
                        <div class="form-group">
                            <label for="template_key">Template Key (Uniek)</label>
                            <input type="text" id="template_key" name="template_key" 
                                   placeholder="bijv: ai-booster-intro" required>
                            <small style="color: #6b7280;">Gebruikt voor identificatie en URL mapping</small>
                        </div>
                        
                        <!-- Display Name -->
                        <div class="form-group">
                            <label for="display_name">Weergave Naam</label>
                            <input type="text" id="display_name" name="display_name" 
                                   placeholder="bijv: AI Booster Introductie" required>
                        </div>
                        
                        <!-- Category -->
                        <div class="form-group">
                            <label for="category">Categorie</label>
                            <select id="category" name="category" required>
                                <?php foreach ($template_categories as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Subcategory -->
                        <div class="form-group">
                            <label for="subcategory">Subcategorie</label>
                            <input type="text" id="subcategory" name="subcategory" 
                                   placeholder="bijv: introductie, verdieping">
                        </div>
                        
                        <!-- Default Description -->
                        <div class="form-group full-width">
                            <label for="default_description">Standaard Beschrijving</label>
                            <textarea id="default_description" name="default_description" rows="3" required></textarea>
                        </div>
                        
                        <!-- Target Audience -->
                        <div class="form-group full-width">
                            <label for="default_target_audience">Standaard Doelgroep</label>
                            <textarea id="default_target_audience" name="default_target_audience" rows="2"></textarea>
                        </div>
                        
                        <!-- Learning Goals -->
                        <div class="form-group full-width">
                            <label for="default_learning_goals">Standaard Leerdoelen</label>
                            <textarea id="default_learning_goals" name="default_learning_goals" rows="2"></textarea>
                        </div>
                        
                        <!-- Materials -->
                        <div class="form-group full-width">
                            <label for="default_materials">Standaard Materialen</label>
                            <textarea id="default_materials" name="default_materials" rows="2"></textarea>
                        </div>
                        
                        <!-- Duration & Participants -->
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
                        
                        <!-- Booking Form URL -->
                        <div class="form-group full-width">
                            <label for="booking_form_url">Booking Formulier URL</label>
                            <input type="text" id="booking_form_url" name="booking_form_url" 
                                   placeholder="universal_registration_form.php?type=introductie">
                            <small style="color: #6b7280;">Relatieve URL naar het inschrijfformulier</small>
                        </div>
                        
                        <!-- Incompany Available -->
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
                        <!-- Template Selection -->
                        <div class="form-group">
                            <label for="course_template">Cursus Template</label>
                            <select id="course_template" name="course_template" onchange="fillTemplateData(this.value)">
                                <option value="general">Aangepast (geen template)</option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?= $template['template_key'] ?>" 
                                            data-category="<?= $template['category'] ?>"
                                            data-subcategory="<?= $template['subcategory'] ?>"
                                            data-description="<?= htmlspecialchars($template['default_description']) ?>"
                                            data-target="<?= htmlspecialchars($template['default_target_audience']) ?>"
                                            data-goals="<?= htmlspecialchars($template['default_learning_goals']) ?>"
                                            data-materials="<?= htmlspecialchars($template['default_materials']) ?>"
                                            data-duration="<?= $template['default_duration_hours'] ?>"
                                            data-maxparticipants="<?= $template['default_max_participants'] ?>">
                                        <?= htmlspecialchars($template['display_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: var(--neutral); font-size: 12px;">Template selecteren vult automatisch de velden in</small>
                        </div>
                        
                        <!-- Basic Info -->
                        <div class="form-group">
                            <label for="course_name">Cursus Naam</label>
                            <input type="text" id="course_name" name="course_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="instructor">Instructeur</label>
                            <input type="text" id="instructor" name="instructor" value="Martijn Planken">
                        </div>
                        
                        <!-- Category -->
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
                        
                        <!-- Descriptions -->
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
                        
                        <!-- Practical Details -->
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
                            <small id="location-description" style="color: #6b7280; font-size: 12px;"></small>
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
                        
                        <!-- Incompany Option -->
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
    // Modal System
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset forms
        if (modalId === 'templateModal') {
            resetTemplateForm();
        } else if (modalId === 'courseModal') {
            resetCourseForm();
        }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            const modalId = e.target.id;
            closeModal(modalId);
        }
    });

    // Template Functions
    function resetTemplateForm() {
        document.getElementById('templateForm').reset();
        document.getElementById('templateAction').value = 'create_template';
        document.getElementById('templateId').value = '';
        document.getElementById('templateModalTitle').textContent = 'Nieuwe Template Aanmaken';
        document.getElementById('templateSubmitText').textContent = 'Template Aanmaken';
    }

    async function editTemplate(templateId) {
        try {
            showNotification('Template gegevens laden...', 'info');
            
            const response = await fetch(`courses.php?ajax=1&action=get_template&id=${templateId}`);
            const template = await response.json();
            
            if (template.error) {
                throw new Error(template.error);
            }
            
            // Fill form with template data
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
            document.getElementById('default_duration_hours').value = template.default_duration_hours || '8';
            document.getElementById('default_max_participants').value = template.default_max_participants || '20';
            document.getElementById('booking_form_url').value = template.booking_form_url || '';
            document.getElementById('incompany_available').checked = template.incompany_available == '1';
            
            // Update modal titles
            document.getElementById('templateModalTitle').textContent = 'Template Bewerken';
            document.getElementById('templateSubmitText').textContent = 'Template Bijwerken';
            
            // Open modal
            openModal('templateModal');
            showNotification('Template geladen!', 'success');
            
        } catch (error) {
            console.error('Error loading template:', error);
            showNotification('Fout bij laden template: ' + error.message, 'error');
        }
    }

    // Course Functions
    function resetCourseForm() {
        document.getElementById('courseForm').reset();
        document.getElementById('courseAction').value = 'create_course';
        document.getElementById('courseId').value = '';
        document.getElementById('courseModalTitle').textContent = 'Nieuwe Cursus Aanmaken';
        document.getElementById('courseSubmitText').textContent = 'Cursus Aanmaken';
        document.getElementById('instructor').value = 'Martijn Planken';
        
        // Wait a bit for form reset to complete before updating location description
        setTimeout(() => {
            updateLocationDescription();
        }, 10);
    }

    async function editCourse(courseId) {
        try {
            showNotification('Cursus gegevens laden...', 'info');
            
            const response = await fetch(`courses.php?ajax=1&action=get_course&id=${courseId}`);
            const course = await response.json();
            
            if (course.error) {
                throw new Error(course.error);
            }
            
            // Fill form with course data
            document.getElementById('courseAction').value = 'update_course';
            document.getElementById('courseId').value = course.id;
            document.getElementById('course_template').value = course.course_template || 'general';
            document.getElementById('course_name').value = course.name || '';
            document.getElementById('instructor').value = course.instructor_name || '';
            document.getElementById('course_category').value = course.category || 'algemeen';
            document.getElementById('course_subcategory').value = course.subcategory || '';
            document.getElementById('short_description').value = course.short_description || '';
            document.getElementById('course_description').value = course.description || '';
            document.getElementById('target_audience').value = course.target_audience || '';
            document.getElementById('learning_goals').value = course.learning_goals || '';
            document.getElementById('materials_included').value = course.materials_included || '';
            
            // Handle date formatting for input field
            if (course.course_date) {
                const date = new Date(course.course_date);
                const formattedDate = date.toISOString().split('T')[0];
                document.getElementById('course_date').value = formattedDate;
            }
            
            document.getElementById('course_time').value = course.time_range || '';
            document.getElementById('location').value = course.location || '';
            document.getElementById('max_participants').value = course.max_participants || '20';
            document.getElementById('price').value = course.price || '';
            document.getElementById('course_incompany_available').checked = course.incompany_available == '1';
            
            // Update modal titles
            document.getElementById('courseModalTitle').textContent = 'Cursus Bewerken';
            document.getElementById('courseSubmitText').textContent = 'Cursus Bijwerken';
            
            // Open modal first
            openModal('courseModal');
            
            // Update location description after a short delay to ensure DOM is ready
            setTimeout(() => {
                updateLocationDescription();
            }, 100);
            
            showNotification('Cursus geladen!', 'success');
            
        } catch (error) {
            console.error('Error loading course:', error);
            showNotification('Fout bij laden cursus: ' + error.message, 'error');
        }
    }

    // Enhanced template auto-fill functionality
    function fillTemplateData(templateKey) {
        if (templateKey === 'general') return;
        
        const option = document.querySelector(`option[value="${templateKey}"]`);
        if (!option) return;
        
        // Auto-fill fields from template data
        const fields = {
            course_category: option.dataset.category || '',
            course_subcategory: option.dataset.subcategory || '',
            course_description: option.dataset.description || '',
            short_description: option.dataset.description || '',
            target_audience: option.dataset.target || '',
            learning_goals: option.dataset.goals || '',
            materials_included: option.dataset.materials || '',
            max_participants: option.dataset.maxparticipants || '20'
        };
        
        Object.keys(fields).forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field && fields[fieldName]) {
                field.value = fields[fieldName];
            }
        });
        
        // Show confirmation
        showNotification('Template gegevens ingevuld!', 'success');
    }
    
    // Location description update
    function updateLocationDescription() {
        const locationSelect = document.getElementById('location');
        const locationDescription = document.getElementById('location-description');
        
        if (locationSelect && locationDescription && locationSelect.options.length > 0) {
            const selectedIndex = locationSelect.selectedIndex;
            if (selectedIndex >= 0 && selectedIndex < locationSelect.options.length) {
                const selectedOption = locationSelect.options[selectedIndex];
                if (selectedOption && selectedOption.dataset) {
                    const description = selectedOption.dataset.description || '';
                    locationDescription.textContent = description;
                } else {
                    locationDescription.textContent = '';
                }
            } else {
                locationDescription.textContent = '';
            }
        }
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const locationSelect = document.getElementById('location');
        if (locationSelect) {
            locationSelect.addEventListener('change', updateLocationDescription);
            updateLocationDescription(); // Initial call
        }

        // Auto-open edit modals if edit parameters are present
        const urlParams = new URLSearchParams(window.location.search);
        const editTemplate = urlParams.get('edit_template');
        const editCourse = urlParams.get('edit');
        
        if (editTemplate) {
            editTemplate(parseInt(editTemplate));
            // Clean URL without page reload
            const newUrl = window.location.pathname + (urlParams.get('tab') ? '?tab=' + urlParams.get('tab') : '');
            window.history.replaceState({}, '', newUrl);
        } else if (editCourse) {
            editCourse(parseInt(editCourse));
            // Clean URL without page reload
            const newUrl = window.location.pathname + (urlParams.get('tab') ? '?tab=' + urlParams.get('tab') : '');
            window.history.replaceState({}, '', newUrl);
        }

        // Auto-generate template key from display name
        const displayNameField = document.getElementById('display_name');
        const templateKeyField = document.getElementById('template_key');
        
        if (displayNameField && templateKeyField) {
            displayNameField.addEventListener('input', function() {
                if (!templateKeyField.value) { // Only auto-generate if field is empty
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
    });
    
    // Notification system
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 1100;
            padding: 1rem 1.5rem; border-radius: 8px; color: white;
            background: ${type === 'success' ? '#059669' : type === 'error' ? '#dc2626' : '#2563eb'};
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        `;
        notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'info'}"></i> ${message}`;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape to close modals
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                closeModal(modal.id);
            });
        }
        
        // Ctrl+N for new course/template
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            const currentTab = '<?= $current_tab ?>';
            if (currentTab === 'templates') {
                openModal('templateModal');
            } else {
                openModal('courseModal');
            }
        }
    });
    </script>
</body>
</html>