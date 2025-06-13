<?php
// Cursus Systeem v6.2.0 - Enhanced Course Management
// Module: courses.php - Course & Template Management  
// Last Update: 13 juni 2025
// Changes: 
// - Moved forms to modals for cleaner interface
// - Enhanced course display with date, location, participants
// - Redesigned action buttons layout
// - Added participant lists and counts
// Goal: Professional course management interface

// ENABLE ERROR REPORTING FOR DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Check if config exists before including
if (!file_exists('../config.php')) {
    die('ERROR: config.php not found. Check file path.');
}

include '../config.php';

// Test database connection
try {
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die('DATABASE ERROR: ' . $e->getMessage());
}

// Determine current tab
$current_tab = $_GET['tab'] ?? 'courses';

// Get template categories for dropdowns
$template_categories = [
    'ai' => 'AI & Technologie',
    'gedrag' => 'Gedragsverandering', 
    'ux' => 'UX & Design',
    'marketing' => 'Marketing & Campagnes',
    'leiderschap' => 'Leiderschap',
    'algemeen' => 'Algemeen'
];

// Enhanced queries to get proper course and participant data
try {
    // Check what tables exist first
    $tables_result = $pdo->query("SHOW TABLES");
    $available_tables = $tables_result->fetchAll(PDO::FETCH_COLUMN);
    
    // Find the courses table
    $courses_table = 'courses';
    if (!in_array('courses', $available_tables)) {
        foreach ($available_tables as $table) {
            if (strpos($table, 'course') !== false) {
                $courses_table = $table;
                break;
            }
        }
    }
    
    // Enhanced course query with participant count
    $courses_query = "
        SELECT c.*,
               COALESCE(p.participant_count, 0) as participant_count,
               COALESCE(p.participant_list, '') as participant_list
        FROM $courses_table c
        LEFT JOIN (
            SELECT course_id, 
                   COUNT(*) as participant_count,
                   GROUP_CONCAT(CONCAT(first_name, ' ', last_name) SEPARATOR ', ') as participant_list
            FROM course_participants 
            WHERE status = 'confirmed'
            GROUP BY course_id
        ) p ON c.id = p.course_id
        WHERE (c.is_template = 0 OR c.is_template IS NULL)
        ORDER BY 
            CASE WHEN c.course_date IS NOT NULL THEN c.course_date ELSE '9999-12-31' END ASC,
            c.id DESC
        LIMIT 50
    ";
    
    // Fallback to simple query if enhanced fails
    try {
        $courses_result = $pdo->query($courses_query);
        $courses = $courses_result->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Simple fallback query
        $courses_result = $pdo->query("SELECT * FROM $courses_table ORDER BY id DESC LIMIT 20");
        $courses = $courses_result->fetchAll(PDO::FETCH_ASSOC);
        
        // Add empty participant data for fallback
        foreach ($courses as &$course) {
            $course['participant_count'] = 0;
            $course['participant_list'] = '';
        }
    }
    
} catch (PDOException $e) {
    die('COURSE QUERY ERROR: ' . $e->getMessage() . '<br>Available tables: ' . implode(', ', $available_tables ?? ['NONE']));
}

// Get templates
$templates = [];
try {
    if (in_array('course_templates', $available_tables ?? [])) {
        $templates_result = $pdo->query("SELECT * FROM course_templates ORDER BY id DESC");
        $templates = $templates_result->fetchAll(PDO::FETCH_ASSOC);
    } elseif (in_array('courses', $available_tables ?? [])) {
        $columns_result = $pdo->query("DESCRIBE courses");
        $columns = $columns_result->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('is_template', $columns)) {
            $templates_result = $pdo->query("SELECT * FROM courses WHERE is_template = 1 ORDER BY id DESC");
            $templates = $templates_result->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $templates = [];
}

// Enhanced form handling
if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_course':
                $stmt = $pdo->prepare("
                    INSERT INTO $courses_table 
                    (name, description, price, course_date, location, booking_url, max_participants) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $_POST['course_name'] ?? 'Nieuwe Cursus',
                    $_POST['description'] ?? '',
                    (float)($_POST['price'] ?? 0),
                    $_POST['course_date'] ?? null,
                    $_POST['location'] ?? '',
                    $_POST['booking_url'] ?? '',
                    (int)($_POST['max_participants'] ?? 20)
                ]);
                
                if ($result) {
                    $_SESSION['message'] = 'Cursus succesvol aangemaakt!';
                    $_SESSION['message_type'] = 'success';
                }
                break;
                
            case 'create_template':
                $stmt = $pdo->prepare("
                    INSERT INTO $courses_table 
                    (name, description, price, is_template, category) 
                    VALUES (?, ?, ?, 1, ?)
                ");
                $result = $stmt->execute([
                    $_POST['template_name'] ?? 'Nieuwe Template',
                    $_POST['description'] ?? '',
                    (float)($_POST['price'] ?? 0),
                    $_POST['category'] ?? 'algemeen'
                ]);
                
                if ($result) {
                    $_SESSION['message'] = 'Template succesvol aangemaakt!';
                    $_SESSION['message_type'] = 'success';
                }
                break;
                
            default:
                $_SESSION['message'] = 'Onbekende actie: ' . $_POST['action'];
                $_SESSION['message_type'] = 'error';
        }
        
        header('Location: courses.php?tab=' . $current_tab);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

// Helper function to format dates
function formatDate($date) {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'Nog niet gepland';
    }
    try {
        $dt = new DateTime($date);
        return $dt->format('j M Y, H:i');
    } catch (Exception $e) {
        return 'Datum onbekend';
    }
}

// Helper function to get status indicator
function getStatusIndicator($course) {
    $now = new DateTime();
    if (empty($course['course_date']) || $course['course_date'] === '0000-00-00') {
        return ['status' => 'concept', 'color' => '#94a3b8', 'text' => 'Concept'];
    }
    
    try {
        $course_date = new DateTime($course['course_date']);
        if ($course_date > $now) {
            return ['status' => 'upcoming', 'color' => '#059669', 'text' => 'Gepland'];
        } else {
            return ['status' => 'completed', 'color' => '#6366f1', 'text' => 'Afgerond'];
        }
    } catch (Exception $e) {
        return ['status' => 'unknown', 'color' => '#ef4444', 'text' => 'Onbekend'];
    }
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursus Beheer - Professional</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Enhanced Professional Styling v6.2.0 */
        :root {
            --primary: #3e5cc6;
            --success: #059669;
            --error: #dc2626;
            --warning: #d97706;
            --background: #f9fafb;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: var(--surface);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .nav {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .nav a {
            padding: 0.75rem 1.5rem;
            background: var(--background);
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .nav a.active, .nav a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
        }

        .tab-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .tab-btn {
            padding: 1rem 2rem;
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .card {
            background: var(--surface);
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .card-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .btn-icon {
            padding: 0.5rem;
            min-width: auto;
        }

        /* Enhanced Course Items */
        .course-item {
            padding: 0;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }

        .course-item:hover {
            background: #f8fafc;
        }

        .course-item:last-child {
            border-bottom: none;
        }

        .course-content {
            padding: 1.5rem 2rem;
        }

        .course-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2rem;
            align-items: start;
        }

        .course-info {
            display: grid;
            gap: 0.75rem;
        }

        .course-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }

        .course-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .detail-item i {
            width: 16px;
            text-align: center;
            color: var(--primary);
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: flex-start;
        }

        .participants-section {
            margin-top: 1rem;
            padding: 1rem;
            background: #f0f9ff;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .participants-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .participants-list {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Modal System - Enhanced */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            backdrop-filter: blur(2px);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .modal-content {
            background: var(--surface);
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 25px rgba(0,0,0,0.1);
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .modal-close:hover {
            background: var(--background);
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .course-header { grid-template-columns: 1fr; }
            .course-details { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; text-align: center; }
            .tab-container { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-graduation-cap"></i> Cursus Beheer</h1>
            <div class="nav">
                <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="planning.php"><i class="fas fa-calendar"></i> Planning</a>
                <a href="courses.php" class="active"><i class="fas fa-book"></i> Cursussen</a>
                <a href="users.php"><i class="fas fa-users"></i> Gebruikers</a>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-container">
            <button class="tab-btn active" onclick="switchTab('courses')">
                <i class="fas fa-calendar-alt"></i> Geplande Cursussen
            </button>
            <button class="tab-btn" onclick="switchTab('templates')">
                <i class="fas fa-layer-group"></i> Templates
            </button>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?= $_SESSION['message_type'] ?? 'success' ?>">
                <i class="fas fa-<?= $_SESSION['message_type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- Courses Tab -->
        <div id="courses-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Geplande Cursussen (<?= count($courses) ?>)</h3>
                    <div class="card-actions">
                        <button onclick="openModal('courseModal')" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nieuwe Cursus
                        </button>
                        <button onclick="openModal('templateModal')" class="btn" style="background: #8b5cf6; color: white;">
                            <i class="fas fa-layer-group"></i> Nieuwe Template
                        </button>
                    </div>
                </div>

                <?php if (empty($courses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-plus fa-4x"></i>
                        <h3>Nog geen cursussen gepland</h3>
                        <p>Start door je eerste cursus aan te maken of een template te gebruiken.</p>
                        <div style="margin-top: 2rem;">
                            <button onclick="openModal('courseModal')" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Eerste Cursus Plannen
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($courses as $course): 
                        $status = getStatusIndicator($course);
                    ?>
                        <div class="course-item">
                            <div class="course-content">
                                <div class="course-header">
                                    <div class="course-info">
                                        <div class="course-title">
                                            <?= htmlspecialchars($course['name'] ?? 'Naamloze Cursus') ?>
                                            <span class="status-badge" style="background: <?= $status['color'] ?>">
                                                <?= $status['text'] ?>
                                            </span>
                                        </div>
                                        
                                        <div class="course-details">
                                            <div class="detail-item">
                                                <i class="fas fa-calendar"></i>
                                                <span class="detail-value"><?= formatDate($course['course_date'] ?? '') ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span class="detail-value"><?= htmlspecialchars($course['location'] ?? 'Locatie niet opgegeven') ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-users"></i>
                                                <span class="detail-value">
                                                    <?= $course['participant_count'] ?? 0 ?> deelnemers
                                                    <?php if (!empty($course['max_participants'])): ?>
                                                        / <?= $course['max_participants'] ?> max
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-euro-sign"></i>
                                                <span class="detail-value">€<?= number_format($course['price'] ?? 0, 2) ?></span>
                                            </div>
                                        </div>

                                        <?php if (!empty($course['participant_list'])): ?>
                                            <div class="participants-section">
                                                <div class="participants-header">
                                                    <strong><i class="fas fa-users"></i> Deelnemers:</strong>
                                                </div>
                                                <div class="participants-list">
                                                    <?= htmlspecialchars($course['participant_list']) ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="course-actions">
                                        <a href="?edit=<?= $course['id'] ?>" class="btn btn-small btn-primary">
                                            <i class="fas fa-edit"></i> Bewerken
                                        </a>
                                        <?php if (!empty($course['booking_url'])): ?>
                                            <a href="<?= htmlspecialchars($course['booking_url']) ?>" target="_blank" class="btn btn-small btn-success">
                                                <i class="fas fa-external-link-alt"></i> Inschrijven
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-small" style="background: #6366f1; color: white;" onclick="openModal('participantsModal<?= $course['id'] ?>')">
                                            <i class="fas fa-users"></i> Deelnemers
                                        </button>
                                        <button class="btn btn-small" style="background: var(--warning); color: white;">
                                            <i class="fas fa-copy"></i> Kopiëren
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Templates Tab -->
        <div id="templates-tab" class="tab-content" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group"></i> Cursus Templates (<?= count($templates) ?>)</h3>
                    <button onclick="openModal('templateModal')" class="btn" style="background: #8b5cf6; color: white;">
                        <i class="fas fa-plus"></i> Nieuwe Template
                    </button>
                </div>
                
                <?php if (empty($templates)): ?>
                    <div class="empty-state">
                        <i class="fas fa-layer-group fa-4x"></i>
                        <h3>Nog geen templates aangemaakt</h3>
                        <p>Templates maken het sneller om vergelijkbare cursussen aan te maken.</p>
                        <div style="margin-top: 2rem;">
                            <button onclick="openModal('templateModal')" class="btn" style="background: #8b5cf6; color: white;">
                                <i class="fas fa-plus"></i> Eerste Template Aanmaken
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <div class="course-item">
                            <div class="course-content">
                                <div class="course-header">
                                    <div class="course-info">
                                        <div class="course-title">
                                            <?= htmlspecialchars($template['name'] ?? 'Naamloze Template') ?>
                                            <span class="status-badge" style="background: #8b5cf6;">Template</span>
                                        </div>
                                        <div class="course-details">
                                            <div class="detail-item">
                                                <i class="fas fa-tag"></i>
                                                <span class="detail-value"><?= $template_categories[$template['category'] ?? 'algemeen'] ?? 'Algemeen' ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-euro-sign"></i>
                                                <span class="detail-value">€<?= number_format($template['price'] ?? 0, 2) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="course-actions">
                                        <button class="btn btn-small btn-success" onclick="createFromTemplate(<?= $template['id'] ?>)">
                                            <i class="fas fa-magic"></i> Cursus Maken
                                        </button>
                                        <a href="?edit_template=<?= $template['id'] ?>" class="btn btn-small btn-primary">
                                            <i class="fas fa-edit"></i> Bewerken
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Course Creation Modal -->
    <div id="courseModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('courseModal')">&times;</button>
            <h2 style="margin-bottom: 2rem; color: var(--primary);">
                <i class="fas fa-calendar-plus"></i> Nieuwe Cursus Plannen
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_course">
                
                <div class="form-group">
                    <label for="course_name">Cursus Naam *</label>
                    <input type="text" id="course_name" name="course_name" required placeholder="Bijv. UX Design Masterclass">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_date">Datum & Tijd</label>
                        <input type="datetime-local" id="course_date" name="course_date">
                    </div>
                    <div class="form-group">
                        <label for="location">Locatie</label>
                        <input type="text" id="location" name="location" placeholder="Bijv. Amsterdam, Online">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Beschrijving</label>
                    <textarea id="description" name="description" rows="4" placeholder="Korte beschrijving van de cursus..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Prijs (€)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label for="max_participants">Max Deelnemers</label>
                        <input type="number" id="max_participants" name="max_participants" min="1" value="20">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="booking_url">Inschrijf URL</label>
                    <input type="url" id="booking_url" name="booking_url" placeholder="https://...">
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Cursus Aanmaken
                    </button>
                    <button type="button" onclick="closeModal('courseModal')" class="btn" style="background: var(--text-secondary); color: white;">
                        Annuleren
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Template Creation Modal -->
    <div id="templateModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('templateModal')">&times;</button>
            <h2 style="margin-bottom: 2rem; color: #8b5cf6;">
                <i class="fas fa-layer-group"></i> Nieuwe Template Aanmaken
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_template">
                
                <div class="form-group">
                    <label for="template_name">Template Naam *</label>
                    <input type="text" id="template_name" name="template_name" required placeholder="Bijv. Workshop Template">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Categorie</label>
                        <select id="category" name="category">
                            <?php foreach ($template_categories as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="template_price">Standaard Prijs (€)</label>
                        <input type="number" id="template_price" name="price" step="0.01" min="0" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="template_description">Template Beschrijving</label>
                    <textarea id="template_description" name="description" rows="4" placeholder="Beschrijving van deze template..."></textarea>
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn" style="background: #8b5cf6; color: white;">
                        <i class="fas fa-save"></i> Template Aanmaken
                    </button>
                    <button type="button" onclick="closeModal('templateModal')" class="btn" style="background: var(--text-secondary); color: white;">
                        Annuleren
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Enhanced JavaScript v6.2.0
        
        // Tab System
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
            document.getElementById(tabName + '-tab').style.display = 'block';
        }

        // Modal System
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        }

        // Create course from template
        function createFromTemplate(templateId) {
            // This would fetch template data and pre-fill the course modal
            openModal('courseModal');
            // TODO: Ajax call to populate form with template data
        }

        // Close modal on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal(modal.id);
                }
            });
        });

        // ESC key to close modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                const requiredFields = form.querySelectorAll('[required]');
                let valid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = 'var(--error)';
                        valid = false;
                    } else {
                        field.style.borderColor = 'var(--border)';
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Vul alle verplichte velden in.');
                }
            });
        });

        console.log('Enhanced courses.php v6.2.0 loaded successfully');
    </script>
</body>
</html>