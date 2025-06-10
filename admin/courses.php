<?php
/**
 * Cursus Systeem - Course Management v6.1.0
 * Enhanced for smart booking flow with templates
 * Strategy: Make core functionality bulletproof first
 * Updated: 2025-06-10
 * Changes: 
 * v6.0.1 - Simplified architecture (no template integration)
 * v6.0.1 - Clean CSS (no conflicts)
 * v6.0.1 - Verified database schema
 * v6.0.1 - Proper error handling
 * v6.0.1 - Version tracking fixed
 * v6.0.2 - Fixed config.php path (../includes/config.php)
 * v6.0.3 - CRITICAL: Fixed database column mapping (name not course_name, etc.)
 * v6.0.4 - UI fixes: course title visible, softer design, NL formatting, NL text
 * v6.0.5 - HOTFIX: Course title display, remove Duration:h, proper NL date format
 * v6.0.6 - FIXED: Display bug resolved, time logic cleaned up (course_date vs time_range)
 * v6.0.7 - COMPLETE OVERHAUL: Fixed ALL display sections, removed ALL English text remnants
 * v6.0.8 - FINAL FIX: Found and fixed the EXACT display line that was missed
 * v6.0.9 - FOUND THE REMNANT: Fixed the exact old display section user pointed out
 * v6.1.0 - BOOKING FLOW: Added template support, smart enrollment, booking URLs
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_course':
                try {
                    // Validate required fields
                    $required = ['course_name', 'course_description', 'course_date', 'course_time', 'max_participants', 'price', 'instructor', 'location'];
                    foreach ($required as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Veld '$field' is verplicht.");
                        }
                    }
                    
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
                        $_POST['booking_url'] ?? '',
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
                break;
                
            case 'update_course':
                try {
                    // Validate required fields
                    $required = ['course_id', 'course_name', 'course_description', 'course_date', 'course_time', 'max_participants', 'price', 'instructor', 'location'];
                    foreach ($required as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Veld '$field' is verplicht.");
                        }
                    }
                    
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
                        $_POST['booking_url'] ?? '',
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
                break;
                
            case 'delete_course':
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
                break;
                
            case 'update_participant_payment':
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
                break;
        }
        
        header('Location: courses.php');
        exit;
    }
}

// Get all courses with participant info
try {
    $courses_query = "
        SELECT 
            c.*,
            COUNT(cp.id) as participant_count,
            SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_participants,
            SUM(CASE WHEN cp.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_participants,
            COALESCE(SUM(CASE WHEN cp.payment_status = 'paid' THEN c.price ELSE 0 END), 0) as course_revenue
        FROM courses c
        LEFT JOIN course_participants cp ON c.id = cp.course_id
        GROUP BY c.id
        ORDER BY c.course_date DESC
    ";
    
    $courses = $pdo->query($courses_query)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $courses = [];
    $_SESSION['message'] = 'Fout bij laden cursussen: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

// Get course templates for dropdown
try {
    $templates_query = "SELECT * FROM course_templates WHERE active = 1 ORDER BY category, display_name";
    $course_templates = $pdo->query($templates_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $course_templates = [];
}

// Handle edit mode
$editing_course = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editing_course = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $_SESSION['message'] = 'Fout bij laden cursus voor bewerken: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursus Beheer - Cursus Systeem v6.1.0</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Clean v6.0 Design System */
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
            max-width: 1200px;
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
        }

        .card-header h3 {
            color: #1f2937;
            font-size: 1.2rem;
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
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Course items */
        .course-item {
            border-left: 3px solid #e5e7eb;
            transition: border-color 0.2s;
        }

        .course-item:hover {
            border-left-color: var(--primary);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .course-status {
            background: var(--success);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .course-status.inactive {
            background: var(--neutral);
        }

        .course-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }

        .meta-item {
            text-align: center;
        }

        .meta-label {
            font-size: 12px;
            color: var(--neutral);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .meta-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }

        /* Participants */
        .participants-section {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
        }

        .participant-item {
            background: white;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 3px solid var(--success);
        }

        .participant-info {
            flex: 1;
        }

        .participant-name {
            font-weight: 600;
            color: #1f2937;
        }

        .participant-email {
            color: var(--neutral);
            font-size: 14px;
        }

        .payment-select {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 12px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .nav {
                flex-direction: column;
            }
            
            .course-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .course-meta {
                grid-template-columns: repeat(2, 1fr);
            }
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
                <a href="certificates.php"><i class="fas fa-certificate"></i> Certificaten</a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?= $_SESSION['message_type'] ?? 'info' ?>">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- Course Form -->
        <div class="card">
            <div class="card-header">
                <h3><?= $editing_course ? 'Cursus Bewerken' : 'Nieuwe Cursus Aanmaken' ?></h3>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editing_course ? 'update_course' : 'create_course' ?>">
                <?php if ($editing_course): ?>
                    <input type="hidden" name="course_id" value="<?= $editing_course['id'] ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <!-- Basis cursus info -->
                    <div class="form-group">
                        <label for="course_name">Cursus Naam</label>
                        <input type="text" id="course_name" name="course_name" 
                               value="<?= htmlspecialchars($editing_course['name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructor">Instructeur</label>
                        <input type="text" id="instructor" name="instructor" 
                               value="<?= htmlspecialchars($editing_course['instructor_name'] ?? '') ?>" required>
                    </div>
                    
                    <!-- Template selectie -->
                    <div class="form-group">
                        <label for="course_template">Cursus Template</label>
                        <select id="course_template" name="course_template" onchange="fillTemplateData(this.value)">
                            <option value="general" <?= ($editing_course['course_template'] ?? 'general') === 'general' ? 'selected' : '' ?>>Algemeen (Aangepast)</option>
                            <?php foreach ($course_templates as $template): ?>
                                <option value="<?= $template['template_key'] ?>" 
                                        <?= ($editing_course['course_template'] ?? '') === $template['template_key'] ? 'selected' : '' ?>
                                        data-category="<?= $template['category'] ?>"
                                        data-subcategory="<?= $template['subcategory'] ?>"
                                        data-description="<?= htmlspecialchars($template['default_description']) ?>"
                                        data-target="<?= htmlspecialchars($template['default_target_audience']) ?>"
                                        data-goals="<?= htmlspecialchars($template['default_learning_goals']) ?>"
                                        data-materials="<?= htmlspecialchars($template['default_materials']) ?>"
                                        data-duration="<?= $template['default_duration_hours'] ?>"
                                        data-maxparticipants="<?= $template['default_max_participants'] ?>"
                                        data-bookingurl="<?= $template['booking_form_url'] ?>">
                                    <?= htmlspecialchars($template['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--neutral); font-size: 12px;">Kies een template om velden automatisch in te vullen</small>
                    </div>
                    
                    <!-- Categorie en subcategorie -->
                    <div class="form-group">
                        <label for="category">Categorie</label>
                        <select id="category" name="category">
                            <option value="general" <?= ($editing_course['category'] ?? 'general') === 'general' ? 'selected' : '' ?>>Algemeen</option>
                            <option value="ai-training" <?= ($editing_course['category'] ?? '') === 'ai-training' ? 'selected' : '' ?>>AI Training</option>
                            <option value="horeca" <?= ($editing_course['category'] ?? '') === 'horeca' ? 'selected' : '' ?>>Horeca</option>
                            <option value="marketing" <?= ($editing_course['category'] ?? '') === 'marketing' ? 'selected' : '' ?>>Marketing</option>
                            <option value="management" <?= ($editing_course['category'] ?? '') === 'management' ? 'selected' : '' ?>>Management</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subcategory">Subcategorie</label>
                        <input type="text" id="subcategory" name="subcategory" 
                               value="<?= htmlspecialchars($editing_course['subcategory'] ?? '') ?>"
                               placeholder="bijv. introductie, verdieping">
                    </div>
                    
                    <!-- Beschrijvingen -->
                    <div class="form-group full-width">
                        <label for="short_description">Korte Beschrijving</label>
                        <textarea id="short_description" name="short_description" rows="2" 
                                  placeholder="Een regel beschrijving voor in overzichten"><?= htmlspecialchars($editing_course['short_description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="course_description">Volledige Cursus Beschrijving</label>
                        <textarea id="course_description" name="course_description" rows="4" required><?= htmlspecialchars($editing_course['description'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Doelgroep en leerdoelen -->
                    <div class="form-group full-width">
                        <label for="target_audience">Doelgroep</label>
                        <textarea id="target_audience" name="target_audience" rows="2" 
                                  placeholder="Voor wie is deze cursus bedoeld?"><?= htmlspecialchars($editing_course['target_audience'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="learning_goals">Leerdoelen</label>
                        <textarea id="learning_goals" name="learning_goals" rows="3" 
                                  placeholder="Wat leren deelnemers in deze cursus?"><?= htmlspecialchars($editing_course['learning_goals'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="materials_included">Inbegrepen Materialen</label>
                        <textarea id="materials_included" name="materials_included" rows="2" 
                                  placeholder="Wat krijgen deelnemers mee?"><?= htmlspecialchars($editing_course['materials_included'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Praktische details -->
                    <div class="form-group">
                        <label for="course_date">Datum</label>
                        <input type="date" id="course_date" name="course_date" 
                               value="<?= $editing_course['course_date'] ? date('Y-m-d', strtotime($editing_course['course_date'])) : '' ?>" required>
                        <small style="color: var(--neutral); font-size: 12px;">De cursusdatum (tijd wordt apart ingesteld hierboven)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_time">Tijdstip (bijv. 09:00 - 17:00)</label>
                        <input type="text" id="course_time" name="course_time" placeholder="bijv. 09:00 - 17:00"
                               value="<?= htmlspecialchars($editing_course['time_range'] ?? '') ?>" required>
                        <small style="color: var(--neutral); font-size: 12px;">Gebruik formaat zoals: 09:00 - 17:00 of 14:00 - 16:30</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_participants">Max Deelnemers</label>
                        <input type="number" id="max_participants" name="max_participants" min="1" 
                               value="<?= $editing_course['max_participants'] ?? '20' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Prijs (€)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" 
                               value="<?= $editing_course['price'] ?? '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Locatie</label>
                        <input type="text" id="location" name="location" 
                               value="<?= htmlspecialchars($editing_course['location'] ?? '') ?>" required>
                    </div>
                    
                    <!-- Booking flow settings -->
                    <div class="form-group full-width">
                        <label for="booking_url">Booking Formulier URL</label>
                        <input type="url" id="booking_url" name="booking_url" 
                               value="<?= htmlspecialchars($editing_course['booking_url'] ?? '') ?>"
                               placeholder="bijv. formulier-ai2.php?type=introductie">
                        <small style="color: var(--neutral); font-size: 12px;">URL naar het inschrijfformulier voor deze cursus</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="incompany_available" value="1" 
                                   <?= !empty($editing_course['incompany_available']) ? 'checked' : '' ?>>
                            Incompany beschikbaar
                        </label>
                        <small style="color: var(--neutral); font-size: 12px;">Kan deze cursus ook incompany gegeven worden?</small>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <?= $editing_course ? 'Cursus Bijwerken' : 'Cursus Aanmaken' ?>
                    </button>
                    
                    <?php if ($editing_course): ?>
                        <a href="courses.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuleren
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Courses List -->
        <div class="card">
            <div class="card-header">
                <h3>Alle Cursussen (<?= count($courses) ?>)</h3>
            </div>

            <?php if (empty($courses)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--neutral);">
                    <i class="fas fa-book fa-3x" style="margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>Nog geen cursussen aangemaakt. Gebruik het formulier hierboven om je eerste cursus aan te maken.</p>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <div class="card course-item">
                        <!-- 🎯 COURSE TITLE - LARGE AND PROMINENT -->
                        <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;">
                            <h2 style="color: #1f2937; margin: 0; font-size: 1.6rem; font-weight: 700; line-height: 1.3;">
                                <?php 
                                // Debug: Let's see what we're getting
                                echo "<!-- DEBUG: name = '" . ($course['name'] ?? 'NULL') . "' -->";
                                
                                $course_name = isset($course['name']) ? trim($course['name']) : '';
                                if (empty($course_name)) {
                                    echo "⚠️ NAAMLOZE CURSUS (ID: " . ($course['id'] ?? 'unknown') . ")";
                                } else {
                                    echo htmlspecialchars($course_name);
                                }
                                ?>
                            </h2>
                        </div>
                        
                        <!-- 🇳🇱 COURSE INFO - DUTCH FORMAT -->
                        <div style="margin-bottom: 1.5rem;">
                            <p style="color: #6b7280; font-size: 14px; line-height: 1.5;">
                                <?php
                                // Calculate duration from time_range  
                                $duration = 'Onbekend';
                                if (!empty($course['time_range']) && preg_match('/(\d{2}):(\d{2})\s*-\s*(\d{2}):(\d{2})/', $course['time_range'], $matches)) {
                                    $start_minutes = (int)$matches[1] * 60 + (int)$matches[2];
                                    $end_minutes = (int)$matches[3] * 60 + (int)$matches[4];
                                    $duration_minutes = $end_minutes - $start_minutes;
                                    if ($duration_minutes > 0) {
                                        $hours = floor($duration_minutes / 60);
                                        $minutes = $duration_minutes % 60;
                                        $duration = $minutes == 0 ? "$hours uur" : "$hours uur en $minutes min";
                                    }
                                }
                                
                                // Format Dutch date
                                $dutch_date = 'Niet ingesteld';
                                if (!empty($course['course_date'])) {
                                    $months = [
                                        1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april', 5 => 'mei', 6 => 'juni',
                                        7 => 'juli', 8 => 'augustus', 9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december'
                                    ];
                                    $timestamp = strtotime($course['course_date']);
                                    $day = date('d', $timestamp);
                                    $month = $months[(int)date('m', $timestamp)];
                                    $year = date('Y', $timestamp);
                                    $dutch_date = "$day $month $year";
                                }
                                ?>
                                
                                <strong>Trainer:</strong> <?= htmlspecialchars($course['instructor_name'] ?? 'Niet ingesteld') ?> | 
                                <strong>Datum:</strong> <?= $dutch_date ?> | 
                                <strong>Duur:</strong> <?= $duration ?> (<?= htmlspecialchars($course['time_range'] ?? 'Niet ingesteld') ?>) | 
                                <strong>Locatie:</strong> <?= htmlspecialchars($course['location'] ?? 'Niet ingesteld') ?>
                            </p>
                        </div>
                        
                        <!-- STATUS BADGE -->
                        <div style="margin-bottom: 1.5rem; text-align: right;">
                            <span class="course-status <?= ($course['active'] ?? 0) ? '' : 'inactive' ?>">
                                <?= ($course['active'] ?? 0) ? 'Actief' : 'Inactief' ?>
                            </span>
                        </div>
                        
                        <!-- COURSE DESCRIPTION & DETAILS -->
                        <div style="margin-bottom: 1.5rem;">
                            <?php if (!empty($course['short_description'])): ?>
                                <p style="color: #555; line-height: 1.6; font-size: 15px; font-weight: 500; margin-bottom: 1rem;">
                                    <?= nl2br(htmlspecialchars(trim($course['short_description']))) ?>
                                </p>
                            <?php endif; ?>
                            
                            <p style="color: #555; line-height: 1.6; font-size: 15px;">
                                <?= isset($course['description']) && !empty(trim($course['description'])) ? nl2br(htmlspecialchars(trim($course['description']))) : '<em>Geen beschrijving beschikbaar</em>' ?>
                            </p>
                        </div>
                        
                        <!-- COURSE DETAILS GRID -->
                        <?php if (!empty($course['target_audience']) || !empty($course['learning_goals']) || !empty($course['materials_included'])): ?>
                        <div style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                                <?php if (!empty($course['target_audience'])): ?>
                                <div>
                                    <h5 style="color: #1f2937; font-size: 14px; font-weight: 600; margin-bottom: 0.5rem;">🎯 Doelgroep</h5>
                                    <p style="color: #6b7280; font-size: 14px; line-height: 1.4; margin: 0;"><?= nl2br(htmlspecialchars($course['target_audience'])) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($course['learning_goals'])): ?>
                                <div>
                                    <h5 style="color: #1f2937; font-size: 14px; font-weight: 600; margin-bottom: 0.5rem;">🎓 Leerdoelen</h5>
                                    <p style="color: #6b7280; font-size: 14px; line-height: 1.4; margin: 0;"><?= nl2br(htmlspecialchars($course['learning_goals'])) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($course['materials_included'])): ?>
                                <div>
                                    <h5 style="color: #1f2937; font-size: 14px; font-weight: 600; margin-bottom: 0.5rem;">📦 Inbegrepen</h5>
                                    <p style="color: #6b7280; font-size: 14px; line-height: 1.4; margin: 0;"><?= nl2br(htmlspecialchars($course['materials_included'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- BOOKING SECTION -->
                        <?php 
                        $available_spots = ($course['max_participants'] ?? 0) - ($course['participant_count'] ?? 0);
                        $is_future_course = strtotime($course['course_date'] ?? 'now') > time();
                        ?>
                        
                        <?php if ($is_future_course && $available_spots > 0): ?>
                        <div style="background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                <div>
                                    <h4 style="color: #065f46; margin: 0 0 0.5rem 0; font-size: 16px;">📅 Inschrijving Open</h4>
                                    <p style="color: #047857; margin: 0; font-size: 14px;">
                                        <strong><?= $available_spots ?> van <?= $course['max_participants'] ?> plaatsen beschikbaar</strong>
                                    </p>
                                </div>
                                
                                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                    <?php if (!empty($course['booking_url'])): ?>
                                        <a href="<?= htmlspecialchars($course['booking_url']) ?>" 
                                           class="btn btn-success" 
                                           style="text-decoration: none;">
                                            <i class="fas fa-user-plus"></i> Inschrijven
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($course['incompany_available'])): ?>
                                        <a href="<?= !empty($course['booking_url']) ? str_replace('?type=', '?type=incompany&base=', $course['booking_url']) : 'formulier-universal.php?type=incompany&course=' . $course['id'] ?>" 
                                           class="btn btn-secondary" 
                                           style="text-decoration: none;">
                                            <i class="fas fa-building"></i> Incompany Aanvragen
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($is_future_course && $available_spots <= 0): ?>
                        <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                <div>
                                    <h4 style="color: #92400e; margin: 0 0 0.5rem 0; font-size: 16px;">⚠️ Cursus Vol</h4>
                                    <p style="color: #b45309; margin: 0; font-size: 14px;">Alle plaatsen zijn bezet</p>
                                </div>
                                
                                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                    <a href="formulier-universal.php?type=interest&course=<?= $course['id'] ?>" 
                                       class="btn btn-warning" 
                                       style="text-decoration: none;">
                                        <i class="fas fa-bell"></i> Wachtlijst
                                    </a>
                                    
                                    <?php if (!empty($course['incompany_available'])): ?>
                                        <a href="<?= !empty($course['booking_url']) ? str_replace('?type=', '?type=incompany&base=', $course['booking_url']) : 'formulier-universal.php?type=incompany&course=' . $course['id'] ?>" 
                                           class="btn btn-secondary" 
                                           style="text-decoration: none;">
                                            <i class="fas fa-building"></i> Incompany Aanvragen
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php elseif (!$is_future_course): ?>
                        <div style="background: #f3f4f6; border: 1px solid #9ca3af; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                <div>
                                    <h4 style="color: #6b7280; margin: 0 0 0.5rem 0; font-size: 16px;">📅 Cursus Afgerond</h4>
                                    <p style="color: #6b7280; margin: 0; font-size: 14px;">Deze cursus heeft al plaatsgevonden</p>
                                </div>
                                
                                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                    <a href="formulier-universal.php?type=interest&course=<?= $course['id'] ?>" 
                                       class="btn btn-secondary" 
                                       style="text-decoration: none;">
                                        <i class="fas fa-calendar-plus"></i> Interesse Volgende Editie
                                    </a>
                                    
                                    <?php if (!empty($course['incompany_available'])): ?>
                                        <a href="<?= !empty($course['booking_url']) ? str_replace('?type=', '?type=incompany&base=', $course['booking_url']) : 'formulier-universal.php?type=incompany&course=' . $course['id'] ?>" 
                                           class="btn btn-secondary" 
                                           style="text-decoration: none;">
                                            <i class="fas fa-building"></i> Incompany Aanvragen
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- COURSE STATISTICS - DUTCH LABELS -->
                        <div class="course-meta">
                            <div class="meta-item">
                                <div class="meta-label">Deelnemers</div>
                                <div class="meta-value"><?= $course['participant_count'] ?>/<?= $course['max_participants'] ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Betaald</div>
                                <div class="meta-value" style="color: var(--success);"><?= $course['paid_participants'] ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Wachtend</div>
                                <div class="meta-value" style="color: var(--warning);"><?= $course['pending_participants'] ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Omzet</div>
                                <div class="meta-value">€<?= number_format($course['course_revenue'], 2, ',', '.') ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Prijs</div>
                                <div class="meta-value">€<?= number_format($course['price'], 2, ',', '.') ?></div>
                            </div>
                        </div>
                        
                        <!-- PARTICIPANTS SECTION - DUTCH -->
                        <?php if ($course['participant_count'] > 0): ?>
                            <div class="participants-section">
                                <strong style="margin-bottom: 1rem; display: block; color: #1f2937;">Deelnemers:</strong>
                                <?php
                                try {
                                    $participants_query = "
                                        SELECT cp.*, u.name, u.email
                                        FROM course_participants cp
                                        JOIN users u ON cp.user_id = u.id
                                        WHERE cp.course_id = ?
                                        ORDER BY cp.enrollment_date DESC
                                    ";
                                    $stmt = $pdo->prepare($participants_query);
                                    $stmt->execute([$course['id']]);
                                    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (empty($participants)) {
                                        echo '<p style="color: var(--neutral); font-style: italic;">Geen deelnemers gevonden in de database.</p>';
                                    } else {
                                        foreach ($participants as $participant):
                                ?>
                                    <div class="participant-item">
                                        <div class="participant-info">
                                            <div class="participant-name"><?= htmlspecialchars($participant['name'] ?? 'Onbekende naam') ?></div>
                                            <div class="participant-email"><?= htmlspecialchars($participant['email'] ?? 'Geen email') ?></div>
                                        </div>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_participant_payment">
                                            <input type="hidden" name="participant_id" value="<?= $participant['id'] ?>">
                                            <select name="payment_status" onchange="this.form.submit()" class="payment-select">
                                                <option value="pending" <?= ($participant['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Wachtend</option>
                                                <option value="paid" <?= ($participant['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Betaald</option>
                                                <option value="cancelled" <?= ($participant['payment_status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Geannuleerd</option>
                                            </select>
                                        </form>
                                    </div>
                                <?php
                                        endforeach;
                                    }
                                } catch (Exception $e) {
                                    echo '<p style="color: var(--error);">Fout bij laden deelnemers: ' . htmlspecialchars($e->getMessage()) . '</p>';
                                }
                                ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: 1rem; background: #f9fafb; border-radius: 6px; margin: 1rem 0;">
                                <p style="color: var(--neutral); text-align: center; margin: 0; font-style: italic;">
                                    Nog geen deelnemers ingeschreven voor deze cursus.
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- ACTION BUTTONS - DUTCH -->
                        <div class="btn-group">
                            <a href="courses.php?edit=<?= $course['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Bewerken
                            </a>
                            
                            <?php if ($course['participant_count'] == 0): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Weet je zeker dat je deze cursus wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.')">
                                    <input type="hidden" name="action" value="delete_course">
                                    <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Verwijderen
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="certificates.php?course_id=<?= $course['id'] ?>" class="btn btn-success">
                                <i class="fas fa-certificate"></i> Certificaten
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Template auto-fill functionality
    function fillTemplateData(templateKey) {
        if (templateKey === 'general') return;
        
        const option = document.querySelector(`option[value="${templateKey}"]`);
        if (!option) return;
        
        // Auto-fill fields from template data
        document.getElementById('category').value = option.dataset.category || '';
        document.getElementById('subcategory').value = option.dataset.subcategory || '';
        document.getElementById('course_description').value = option.dataset.description || '';
        document.getElementById('short_description').value = option.dataset.description || '';
        document.getElementById('target_audience').value = option.dataset.target || '';
        document.getElementById('learning_goals').value = option.dataset.goals || '';
        document.getElementById('materials_included').value = option.dataset.materials || '';
        document.getElementById('max_participants').value = option.dataset.maxparticipants || '20';
        document.getElementById('booking_url').value = option.dataset.bookingurl || '';
        
        // Show confirmation
        const message = document.createElement('div');
        message.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 1rem; border-radius: 8px; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
        message.innerHTML = '<i class="fas fa-check"></i> Template gegevens ingevuld!';
        document.body.appendChild(message);
        
        setTimeout(() => message.remove(), 3000);
    }
    
    // Enhanced form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.inschrijf-form');
        if (!form) return;
        
        // Real-time booking URL validation
        const bookingUrlField = document.getElementById('booking_url');
        if (bookingUrlField) {
            bookingUrlField.addEventListener('blur', function() {
                const url = this.value.trim();
                if (url && !url.includes('formulier-') && !url.includes('.php')) {
                    this.style.borderColor = '#f59e0b';
                    this.title = 'URL moet een formulier bestand zijn (bijv. formulier-ai2.php?type=intro)';
                } else {
                    this.style.borderColor = '';
                    this.title = '';
                }
            });
        }
        
        // Auto-generate booking URL based on template
        const templateSelect = document.getElementById('course_template');
        const courseNameField = document.getElementById('course_name');
        
        if (templateSelect && courseNameField) {
            function generateBookingUrl() {
                const template = templateSelect.value;
                const courseName = courseNameField.value.toLowerCase();
                
                if (template === 'ai-booster-intro' || courseName.includes('introductie')) {
                    document.getElementById('booking_url').value = 'formulier-ai2.php?type=introductie';
                } else if (template === 'ai-booster-verdieping' || courseName.includes('verdieping')) {
                    document.getElementById('booking_url').value = 'formulier-ai2.php?type=verdieping';
                } else if (template === 'ai-booster-combi' || courseName.includes('combi') || courseName.includes('masterclass')) {
                    document.getElementById('booking_url').value = 'formulier-ai2.php?type=combi';
                } else if (template === 'ai-booster-incompany' || courseName.includes('incompany')) {
                    document.getElementById('booking_url').value = 'formulier-ai2.php?type=incompany';
                } else if (template.includes('horeca') || courseName.includes('kassa') || courseName.includes('iva')) {
                    document.getElementById('booking_url').value = 'formulier-universal.php?type=' + template.replace('horeca-', '');
                }
            }
            
            templateSelect.addEventListener('change', generateBookingUrl);
            courseNameField.addEventListener('blur', generateBookingUrl);
        }
    });
    
    // Enhanced admin actions
    function markAsPaid(participantId) {
        if (confirm('Markeer deze deelnemer als betaald?')) {
            fetch('course-admin-actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_paid', participant_id: participantId })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    location.reload();
                } else {
                    alert('Fout: ' + result.message);
                }
            });
        }
    }
    
    function resendInvoice(participantId) {
        if (confirm('Factuur opnieuw verzenden?')) {
            fetch('course-admin-actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'resend_invoice', participant_id: participantId })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Factuur verzonden!');
                } else {
                    alert('Fout: ' + result.message);
                }
            });
        }
    }
    
    function generateReceipt(participantId) {
        window.open('generate-receipt.php?participant_id=' + participantId, '_blank');
    }
    
    function updatePaymentStatus(participantId, status) {
        fetch('course-admin-actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'update_payment_status', 
                participant_id: participantId, 
                status: status 
            })
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                alert('Fout bij bijwerken status: ' + result.message);
                location.reload(); // Reset form
            }
        });
    }
    </script>
</body>
</html>