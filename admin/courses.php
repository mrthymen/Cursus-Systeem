<?php
// Cursus Systeem v6.1.1 - Safe Debug Version
// Module: courses.php - Course & Template Management  
// Last Update: 13 juni 2025
// Changes: Added error reporting, minimal modal fix, kept existing structure
// Goal: Fix white screen first, then enhance features

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

// Simple query to test what tables exist and get courses
try {
    // Check what tables exist first
    $tables_result = $pdo->query("SHOW TABLES");
    $available_tables = $tables_result->fetchAll(PDO::FETCH_COLUMN);
    
    // Find the courses table (might be 'courses' or something else)
    $courses_table = 'courses';
    if (!in_array('courses', $available_tables)) {
        // Look for any table with 'course' in the name
        foreach ($available_tables as $table) {
            if (strpos($table, 'course') !== false) {
                $courses_table = $table;
                break;
            }
        }
    }
    
    // Simple course query that should work with any structure
    $courses_query = "SELECT * FROM $courses_table ORDER BY id DESC LIMIT 20";
    $courses_result = $pdo->query($courses_query);
    $courses = $courses_result->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Show detailed error for debugging
    die('COURSE QUERY ERROR: ' . $e->getMessage() . '<br>Available tables: ' . implode(', ', $available_tables ?? ['NONE']));
}

// Get templates (if template system exists)
$templates = [];
try {
    // Try to find templates - could be separate table or same table with flag
    if (in_array('course_templates', $available_tables ?? [])) {
        $templates_result = $pdo->query("SELECT * FROM course_templates ORDER BY id DESC");
        $templates = $templates_result->fetchAll(PDO::FETCH_ASSOC);
    } elseif (in_array('courses', $available_tables ?? [])) {
        // Check if courses table has is_template column
        $columns_result = $pdo->query("DESCRIBE courses");
        $columns = $columns_result->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('is_template', $columns)) {
            $templates_result = $pdo->query("SELECT * FROM courses WHERE is_template = 1 ORDER BY id DESC");
            $templates = $templates_result->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    // Templates are optional, continue without them
    $templates = [];
}

// Basic form handling - simplified
if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_course':
                // Minimal course creation - adapt to actual table structure
                $stmt = $pdo->prepare("INSERT INTO $courses_table (name, description, price) VALUES (?, ?, ?)");
                $result = $stmt->execute([
                    $_POST['course_name'] ?? 'Nieuwe Cursus',
                    $_POST['description'] ?? '',
                    (float)($_POST['price'] ?? 0)
                ]);
                
                if ($result) {
                    $_SESSION['message'] = 'Cursus succesvol aangemaakt!';
                    $_SESSION['message_type'] = 'success';
                }
                break;
                
            default:
                $_SESSION['message'] = 'Onbekende actie: ' . $_POST['action'];
                $_SESSION['message_type'] = 'error';
        }
        
        // Redirect to prevent resubmission
        header('Location: courses.php?tab=' . $current_tab);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursus Beheer - Debug Mode</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Minimal Safe Styling v6.1.1 */
        :root {
            --primary: #3e5cc6;
            --success: #059669;
            --error: #dc2626;
            --background: #f9fafb;
            --surface: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--background);
            color: #1f2937;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: var(--surface);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: var(--primary);
            font-size: 2rem;
        }

        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 8px;
        }

        .nav {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .nav a {
            padding: 0.75rem 1.5rem;
            background: var(--background);
            color: #6b7280;
            text-decoration: none;
            border-radius: 8px;
        }

        .nav a.active, .nav a:hover {
            background: var(--primary);
            color: white;
        }

        .card {
            background: var(--surface);
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #6b80e8, #b998e4);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Modal System - Simplified */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .modal-content {
            background: var(--surface);
            border-radius: 8px;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }

        .course-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .course-item:last-child {
            border-bottom: none;
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .course-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .course-meta {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-weight: 500;
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
            padding: 3rem;
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .course-header { flex-direction: column; }
            .modal-content { margin: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-graduation-cap"></i> Cursus Beheer (Debug Mode)</h1>
            <div class="nav">
                <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="planning.php"><i class="fas fa-calendar"></i> Planning</a>
                <a href="courses.php" class="active"><i class="fas fa-book"></i> Cursussen</a>
                <a href="users.php"><i class="fas fa-users"></i> Gebruikers</a>
            </div>
        </div>

        <!-- Debug Information -->
        <div class="debug-info">
            <h3><i class="fas fa-bug"></i> Debug Info</h3>
            <p><strong>Available Tables:</strong> <?= implode(', ', $available_tables ?? ['NONE']) ?></p>
            <p><strong>Courses Table:</strong> <?= $courses_table ?></p>
            <p><strong>Courses Found:</strong> <?= count($courses) ?></p>
            <p><strong>Templates Found:</strong> <?= count($templates) ?></p>
            <p><strong>Current Tab:</strong> <?= $current_tab ?></p>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?= $_SESSION['message_type'] ?? 'success' ?>">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="card">
            <div class="card-header">
                <h3>Cursussen (<?= count($courses) ?>)</h3>
                <button onclick="openModal('courseModal')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nieuwe Cursus
                </button>
            </div>

            <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <i class="fas fa-book fa-3x" style="margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>Nog geen cursussen aangemaakt.</p>
                    <button onclick="openModal('courseModal')" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Eerste Cursus Aanmaken
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <div class="course-item">
                        <div class="course-header">
                            <div>
                                <div class="course-title">
                                    <?= htmlspecialchars($course['name'] ?? 'Naamloze Cursus') ?>
                                </div>
                                <div class="course-meta">
                                    ID: <?= $course['id'] ?? 'N/A' ?> |
                                    Prijs: €<?= number_format($course['price'] ?? 0, 2) ?> |
                                    Aangemaakt: <?= $course['created_at'] ?? 'Onbekend' ?>
                                </div>
                            </div>
                            <div class="course-actions">
                                <a href="?edit=<?= $course['id'] ?>" class="btn btn-small btn-primary">
                                    <i class="fas fa-edit"></i> Bewerken
                                </a>
                                <?php if (!empty($course['booking_url'])): ?>
                                    <a href="<?= htmlspecialchars($course['booking_url']) ?>" target="_blank" class="btn btn-small" style="background: var(--success); color: white;">
                                        <i class="fas fa-external-link-alt"></i> Inschrijven
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Templates Section (if available) -->
        <?php if (!empty($templates)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Templates (<?= count($templates) ?>)</h3>
                </div>
                
                <?php foreach ($templates as $template): ?>
                    <div class="course-item">
                        <div class="course-header">
                            <div>
                                <div class="course-title">
                                    <?= htmlspecialchars($template['name'] ?? 'Naamloze Template') ?>
                                </div>
                                <div class="course-meta">
                                    Template ID: <?= $template['id'] ?? 'N/A' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Simple Course Modal -->
    <div id="courseModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('courseModal')">&times;</button>
            <h2 style="margin-bottom: 1.5rem;">
                <i class="fas fa-book"></i> Nieuwe Cursus Aanmaken
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_course">
                
                <div class="form-group">
                    <label for="course_name">Cursus Naam</label>
                    <input type="text" id="course_name" name="course_name" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Beschrijving</label>
                    <textarea id="description" name="description" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="price">Prijs (€)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" value="0">
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Cursus Aanmaken
                    </button>
                    <button type="button" onclick="closeModal('courseModal')" class="btn" style="background: #6b7280; color: white;">
                        Annuleren
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Minimal Modal System v6.1.1
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
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

        console.log('Debug Mode: courses.php v6.1.1 loaded successfully');
    </script>
</body>
</html>