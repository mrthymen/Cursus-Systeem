<?php
/**
 * Cursus Systeem - Course Management v6.0.6
 * Clean foundation - no integration complexity
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
                        INSERT INTO courses (name, description, course_date, time_range, max_participants, price, instructor_name, location, active, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    
                    $result = $stmt->execute([
                        trim($_POST['course_name']),
                        trim($_POST['course_description']),
                        $_POST['course_date'],
                        $_POST['course_time'],
                        (int)$_POST['max_participants'],
                        (float)$_POST['price'],
                        trim($_POST['instructor']),
                        trim($_POST['location'])
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
                            max_participants = ?, price = ?, instructor_name = ?, location = ?
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
    <title>Cursus Beheer - Cursus Systeem v6.0.6</title>
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
                    
                    <div class="form-group full-width">
                        <label for="course_description">Cursus Beschrijving</label>
                        <textarea id="course_description" name="course_description" required><?= htmlspecialchars($editing_course['description'] ?? '') ?></textarea>
                    </div>
                    
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
                        <div class="course-header">
                            <div>
                                <h4 style="color: #1f2937; margin-bottom: 0.5rem;">
                                    <?= htmlspecialchars($course['course_name']) ?>
                                </h4>
                                <p style="color: var(--neutral); font-size: 14px;">
                                    <strong>Instructor:</strong> <?= htmlspecialchars($course['instructor']) ?> | 
                                    <strong>Date:</strong> <?= date('d-m-Y', strtotime($course['course_date'])) ?> <?= date('H:i', strtotime($course['course_time'])) ?> | 
                                    <strong>Duration:</strong> <?= $course['duration_hours'] ?>h | 
                                    <strong>Location:</strong> <?= htmlspecialchars($course['location']) ?>
                                </p>
                            </div>
                            <span class="course-status <?= $course['active'] ? '' : 'inactive' ?>">
                                <?= $course['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        
                        <p style="margin-bottom: 1rem; color: #555; line-height: 1.6;">
                            <?= isset($course['description']) && !empty(trim($course['description'])) ? nl2br(htmlspecialchars(trim($course['description']))) : '<em>Geen beschrijving beschikbaar</em>' ?>
                        </p>
                        
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
                        
                        <?php if ($course['participant_count'] > 0): ?>
                            <div class="participants-section">
                                <strong style="margin-bottom: 1rem; display: block;">Deelnemers:</strong>
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
                                    
                                    foreach ($participants as $participant):
                                ?>
                                    <div class="participant-item">
                                        <div class="participant-info">
                                            <div class="participant-name"><?= htmlspecialchars($participant['name']) ?></div>
                                            <div class="participant-email"><?= htmlspecialchars($participant['email']) ?></div>
                                        </div>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_participant_payment">
                                            <input type="hidden" name="participant_id" value="<?= $participant['id'] ?>">
                                            <select name="payment_status" onchange="this.form.submit()" class="payment-select">
                                                <option value="pending" <?= $participant['payment_status'] === 'pending' ? 'selected' : '' ?>>Wachtend</option>
                                                <option value="paid" <?= $participant['payment_status'] === 'paid' ? 'selected' : '' ?>>Betaald</option>
                                                <option value="cancelled" <?= $participant['payment_status'] === 'cancelled' ? 'selected' : '' ?>>Geannuleerd</option>
                                            </select>
                                        </form>
                                    </div>
                                <?php
                                    endforeach;
                                } catch (Exception $e) {
                                    echo '<p style="color: var(--error);">Fout bij laden deelnemers: ' . $e->getMessage() . '</p>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="btn-group">
                            <a href="courses.php?edit=<?= $course['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Bewerken
                            </a>
                            
                            <?php if ($course['participant_count'] == 0): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Weet je zeker dat je deze cursus wilt verwijderen?')">
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
</body>
</html>