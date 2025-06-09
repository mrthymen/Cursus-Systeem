<?php
/**
 * Inventijn Cursus Management Systeem
 * Cursus Management Interface met User Management Integratie
 * 
 * @version 2.4.0
 */

require_once '../includes/config.php';

session_start();

// Check admin login
if (!isset($_SESSION['admin_user'])) {
    header("Location: index.php");
    exit;
}

$pdo = getDatabase();
$message = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_course':
                $stmt = $pdo->prepare("
                    INSERT INTO courses (name, description, course_date, end_date, location, time_range, 
                                       access_start_time, max_participants, price, active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $success = $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['course_date'],
                    $_POST['end_date'] ?: null,
                    $_POST['location'],
                    $_POST['time_range'],
                    $_POST['access_start_time'],
                    $_POST['max_participants'],
                    $_POST['price'] ?: 0
                ]);
                
                echo json_encode(['success' => $success, 'message' => $success ? 'Cursus aangemaakt!' : 'Fout bij aanmaken']);
                break;
                
            case 'update_course':
                $stmt = $pdo->prepare("
                    UPDATE courses SET name=?, description=?, course_date=?, end_date=?, location=?, 
                                     time_range=?, access_start_time=?, max_participants=?, price=?, active=?
                    WHERE id=?
                ");
                $success = $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['course_date'],
                    $_POST['end_date'] ?: null,
                    $_POST['location'],
                    $_POST['time_range'],
                    $_POST['access_start_time'],
                    $_POST['max_participants'],
                    $_POST['price'] ?: 0,
                    $_POST['active'] ? 1 : 0,
                    $_POST['course_id']
                ]);
                
                echo json_encode(['success' => $success, 'message' => $success ? 'Cursus bijgewerkt!' : 'Fout bij bijwerken']);
                break;
                
            case 'delete_course':
                $stmt = $pdo->prepare("UPDATE courses SET active = 0 WHERE id = ?");
                $success = $stmt->execute([$_POST['course_id']]);
                echo json_encode(['success' => $success, 'message' => $success ? 'Cursus gedeactiveerd!' : 'Fout bij deactiveren']);
                break;
                
            case 'add_participant_to_course':
                // Check if already enrolled
                $checkStmt = $pdo->prepare("SELECT id FROM course_participants WHERE course_id = ? AND user_id = ?");
                $checkStmt->execute([$_POST['course_id'], $_POST['user_id']]);
                
                if ($checkStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Deelnemer al ingeschreven voor deze cursus']);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO course_participants (course_id, user_id, payment_status, enrollment_date, notes) 
                        VALUES (?, ?, ?, NOW(), ?)
                    ");
                    $success = $stmt->execute([$_POST['course_id'], $_POST['user_id'], $_POST['payment_status'] ?? 'pending', $_POST['notes'] ?? '']);
                    echo json_encode(['success' => $success, 'message' => $success ? 'Deelnemer toegevoegd!' : 'Fout bij toevoegen']);
                }
                break;
                
            case 'update_payment_status':
                $stmt = $pdo->prepare("UPDATE course_participants SET payment_status = ?, payment_date = ? WHERE id = ?");
                $paymentDate = $_POST['payment_status'] === 'paid' ? date('Y-m-d H:i:s') : null;
                $success = $stmt->execute([$_POST['payment_status'], $paymentDate, $_POST['participant_id']]);
                echo json_encode(['success' => $success, 'message' => $success ? 'Betaalstatus bijgewerkt!' : 'Fout bij bijwerken']);
                break;
                
            case 'remove_participant':
                $stmt = $pdo->prepare("DELETE FROM course_participants WHERE id = ?");
                $success = $stmt->execute([$_POST['participant_id']]);
                echo json_encode(['success' => $success, 'message' => $success ? 'Deelnemer verwijderd!' : 'Fout bij verwijderen']);
                break;
                
            case 'get_course_stats':
                $courseId = $_POST['course_id'];
                $stats = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_participants,
                        SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_participants,
                        SUM(CASE WHEN cp.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_participants,
                        SUM(CASE WHEN cp.payment_status = 'paid' THEN c.price ELSE 0 END) as total_revenue
                    FROM course_participants cp
                    JOIN courses c ON cp.course_id = c.id
                    WHERE cp.course_id = ?
                ");
                $stats->execute([$courseId]);
                $result = $stats->fetch();
                echo json_encode(['success' => true, 'stats' => $result]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Onbekende actie']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
    }
    exit;
}

// Get course data
$selectedCourseId = $_GET['course_id'] ?? null;
$courses = $pdo->query("
    SELECT c.*, 
           COUNT(cp.id) as participant_count,
           SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
           SUM(CASE WHEN cp.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
           SUM(CASE WHEN cp.payment_status = 'paid' THEN c.price ELSE 0 END) as course_revenue
    FROM courses c 
    LEFT JOIN course_participants cp ON c.id = cp.course_id 
    WHERE c.active = 1
    GROUP BY c.id 
    ORDER BY c.course_date ASC
")->fetchAll();

// Get all users for dropdown (enhanced query)
$users = $pdo->query("
    SELECT u.id, u.name, u.email, u.company, u.active,
           COUNT(cp.id) as total_courses,
           SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_courses
    FROM users u
    LEFT JOIN course_participants cp ON u.id = cp.user_id
    WHERE u.active = 1
    GROUP BY u.id
    ORDER BY u.name
")->fetchAll();

// Get participants for selected course (enhanced query)
$participants = [];
if ($selectedCourseId) {
    $participants = $pdo->prepare("
        SELECT cp.*, u.name, u.email, u.phone, u.company, u.active as user_active,
               COUNT(cp2.id) as user_total_courses,
               SUM(CASE WHEN cp2.payment_status = 'paid' THEN 1 ELSE 0 END) as user_paid_courses
        FROM course_participants cp
        JOIN users u ON cp.user_id = u.id
        LEFT JOIN course_participants cp2 ON u.id = cp2.user_id
        WHERE cp.course_id = ?
        GROUP BY cp.id
        ORDER BY cp.enrollment_date DESC
    ");
    $participants->execute([$selectedCourseId]);
    $participants = $participants->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursus Management - Inventijn</title>
    <style>
        /* Import Inventijn brand fonts */
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600&family=Barlow:wght@400;500;700&display=swap');
        
        :root {
            /* Inventijn Officiële Kleuren */
            --inventijn-light-pink: #e3a1e5;
            --inventijn-purple: #b998e4;
            --inventijn-light-blue: #6b80e8;
            --inventijn-dark-blue: #3e5cc6;
            --yellow: #F9CB40;
            --orange: #F9A03F;
            --white: #FFFFFF;
            --grey-light: #F2F2F2;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Barlow', sans-serif; background: var(--grey-light); line-height: 1.6; }
        
        .header { 
            background: white; 
            padding: 1rem 2rem; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            border-bottom: 3px solid var(--inventijn-dark-blue);
        }
        
        .header-content { 
            max-width: 1400px; 
            margin: 0 auto; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .header h1 { 
            color: var(--inventijn-dark-blue); 
            font-family: 'Space Grotesk', sans-serif; 
            font-size: 1.5rem; 
        }
        
        .nav-breadcrumb {
            color: var(--inventijn-purple);
            font-size: 0.9rem;
        }
        
        .nav-breadcrumb a {
            color: var(--inventijn-purple);
            text-decoration: none;
        }
        
        .nav-breadcrumb a:hover {
            color: var(--inventijn-dark-blue);
        }
        
        .version-badge {
            background: linear-gradient(135deg, var(--orange) 0%, #e69500 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 1rem;
        }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 6px solid var(--yellow);
        }
        
        .page-header h2 {
            color: var(--inventijn-dark-blue);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: var(--inventijn-purple);
            font-size: 1.1rem;
        }
        
        .management-tools {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .main-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        
        .panel { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            overflow: hidden; 
        }
        
        .panel-header { 
            background: var(--inventijn-dark-blue); 
            color: white; 
            padding: 1.5rem; 
            font-family: 'Space Grotesk', sans-serif; 
            font-weight: 600; 
        }
        
        .panel-content { padding: 1.5rem; }
        
        .btn { 
            background: linear-gradient(135deg, var(--orange) 0%, #e69500 100%); 
            color: white; 
            padding: 0.75rem 1.5rem; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 0.875rem; 
            font-weight: 600; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            transition: all 0.2s; 
            font-family: inherit;
        }
        
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 20px rgba(249, 160, 63, 0.4); 
        }
        
        .btn-secondary { background: var(--inventijn-purple); }
        .btn-secondary:hover { box-shadow: 0 6px 20px rgba(185, 152, 228, 0.4); }
        .btn-danger { background: var(--inventijn-light-pink); }
        .btn-danger:hover { box-shadow: 0 6px 20px rgba(227, 161, 229, 0.4); }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.8rem; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: var(--inventijn-dark-blue); 
            font-weight: 600; 
            font-size: 0.9rem;
        }
        
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 0.75rem; 
            border: 2px solid #e5e7eb; 
            border-radius: 8px; 
            font-size: 0.9rem; 
            transition: border-color 0.3s; 
            font-family: inherit;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
            outline: none; 
            border-color: var(--orange); 
        }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        
        .course-list { max-height: 500px; overflow-y: auto; }
        
        .course-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .course-item:hover {
            border-color: var(--inventijn-light-blue);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .course-item.selected {
            border-color: var(--inventijn-dark-blue);
            background: rgba(62, 92, 198, 0.05);
        }
        
        .course-title {
            font-weight: 600;
            color: var(--inventijn-dark-blue);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
        }
        
        .course-date {
            color: var(--inventijn-purple);
            font-size: 0.9rem;
        }
        
        .course-stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .stat-badge {
            background: var(--grey-light);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .stat-badge.participants { color: var(--inventijn-dark-blue); }
        .stat-badge.paid { color: #065f46; background: #d1fae5; }
        .stat-badge.pending { color: #92400e; background: #fef3c7; }
        .stat-badge.revenue { color: var(--orange); background: #fff7e6; }
        
        .participants-section {
            margin-top: 2rem;
        }
        
        .participants-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .participants-table th,
        .participants-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .participants-table th {
            background: var(--grey-light);
            font-weight: 600;
            color: var(--inventijn-dark-blue);
        }
        
        .payment-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .payment-status.paid { background: #d1fae5; color: #065f46; }
        .payment-status.pending { background: #fef3c7; color: #92400e; }
        .payment-status.cancelled { background: rgba(227, 161, 229, 0.2); color: var(--inventijn-light-pink); }
        
        .user-link {
            color: var(--inventijn-dark-blue);
            text-decoration: none;
            font-weight: 600;
        }
        
        .user-link:hover {
            color: var(--inventijn-light-blue);
            text-decoration: underline;
        }
        
        .user-stats {
            font-size: 0.8rem;
            color: var(--inventijn-purple);
            margin-top: 0.25rem;
        }
        
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
        
        .modal-content { 
            background: white; 
            margin: 5% auto; 
            padding: 2rem; 
            border-radius: 12px; 
            max-width: 600px; 
            position: relative; 
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-close { 
            position: absolute; 
            top: 1rem; 
            right: 1rem; 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            cursor: pointer; 
            color: var(--inventijn-purple);
        }
        
        .alert { 
            padding: 1rem; 
            border-radius: 8px; 
            margin-bottom: 1rem; 
            border: 1px solid;
        }
        
        .alert-success { 
            background: #d1fae5; 
            color: #065f46; 
            border-color: #a7f3d0; 
        }
        
        .alert-error { 
            background: rgba(227, 161, 229, 0.1); 
            color: var(--inventijn-light-pink); 
            border-color: var(--inventijn-light-pink); 
        }
        
        @media (max-width: 768px) {
            .main-grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .container { padding: 1rem; }
            .management-tools { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>📚 Cursus Management <span class="version-badge">v2.4.0</span></h1>
            <div class="nav-breadcrumb">
                <a href="index.php">Dashboard</a> → 
                <a href="users.php">Gebruikers</a> → 
                Cursussen
            </div>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>Cursus Management Center</h2>
            <p>Beheer cursussen, deelnemers en betalingen vanuit één centrale plek</p>
        </div>

        <!-- Management Tools -->
        <div class="management-tools">
            <button class="btn" onclick="showNewCourseModal()">
                ➕ Nieuwe Cursus
            </button>
            <a href="users.php" class="btn btn-secondary">
                👥 Gebruikersbeheer
            </a>
            <button class="btn btn-secondary" onclick="exportCourseData()">
                📊 Export Data
            </button>
            <button class="btn btn-secondary" onclick="showBulkEmailModal()">
                📧 Bulk E-mail
            </button>
        </div>

        <div class="main-grid">
            <!-- Left: Course Management -->
            <div class="panel">
                <div class="panel-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>📋 Alle Cursussen (<?= count($courses) ?>)</span>
                        <button class="btn btn-sm" onclick="showNewCourseModal()">➕ Nieuwe Cursus</button>
                    </div>
                </div>
                <div class="panel-content">
                    <div class="course-list">
                        <?php if (empty($courses)): ?>
                            <p style="text-align: center; color: var(--inventijn-purple); padding: 2rem;">
                                🎓 Nog geen cursussen aangemaakt.<br>
                                <button class="btn btn-sm" onclick="showNewCourseModal()" style="margin-top: 1rem;">Maak je eerste cursus</button>
                            </p>
                        <?php else: ?>
                            <?php foreach ($courses as $course): ?>
                            <div class="course-item <?= $selectedCourseId == $course['id'] ? 'selected' : '' ?>" 
                                 onclick="selectCourse(<?= $course['id'] ?>)">
                                <div class="course-title"><?= htmlspecialchars($course['name']) ?></div>
                                <div class="course-date">
                                    📅 <?= date('d-m-Y H:i', strtotime($course['course_date'])) ?>
                                    <?php if ($course['location']): ?>
                                        📍 <?= htmlspecialchars($course['location']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="course-meta">
                                    <div class="course-stats">
                                        <span class="stat-badge participants">
                                            👥 <?= $course['participant_count'] ?>/<?= $course['max_participants'] ?>
                                        </span>
                                        <span class="stat-badge paid">
                                            ✅ <?= $course['paid_count'] ?> betaald
                                        </span>
                                        <?php if ($course['pending_count'] > 0): ?>
                                        <span class="stat-badge pending">
                                            ⏳ <?= $course['pending_count'] ?> wachtend
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($course['course_revenue'] > 0): ?>
                                        <span class="stat-badge revenue">
                                            💰 €<?= number_format($course['course_revenue'], 0) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editCourse(<?= $course['id'] ?>)">
                                            ✏️ Bewerken
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Course Participants -->
            <div class="panel">
                <div class="panel-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>👥 Deelnemers</span>
                        <?php if ($selectedCourseId): ?>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn btn-sm" onclick="showAddParticipantModal()">➕ Deelnemer</button>
                                <a href="users.php" class="btn btn-sm btn-secondary">👥 Gebruikers</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-content">
                    <?php if (!$selectedCourseId): ?>
                        <p style="text-align: center; color: var(--inventijn-purple); padding: 2rem;">
                            👈 Selecteer een cursus om deelnemers te beheren
                        </p>
                    <?php elseif (empty($participants)): ?>
                        <p style="text-align: center; color: var(--inventijn-purple); padding: 2rem;">
                            👥 Nog geen deelnemers voor deze cursus.<br>
                            <button class="btn btn-sm" onclick="showAddParticipantModal()" style="margin-top: 1rem;">Voeg eerste deelnemer toe</button>
                        </p>
                    <?php else: ?>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <table class="participants-table">
                                <thead>
                                    <tr>
                                        <th>Deelnemer</th>
                                        <th>Betaalstatus</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td>
                                            <a href="users.php?user_id=<?= $participant['user_id'] ?>" class="user-link">
                                                <strong><?= htmlspecialchars($participant['name']) ?></strong>
                                            </a>
                                            <br>
                                            <small style="color: var(--inventijn-purple);"><?= htmlspecialchars($participant['email']) ?></small>
                                            <?php if ($participant['company']): ?>
                                                <br><small style="color: #6b7280;"><?= htmlspecialchars($participant['company']) ?></small>
                                            <?php endif; ?>
                                            <div class="user-stats">
                                                📚 <?= $participant['user_total_courses'] ?> cursussen | 
                                                ✅ <?= $participant['user_paid_courses'] ?> betaald
                                            </div>
                                        </td>
                                        <td>
                                            <select class="payment-status <?= $participant['payment_status'] ?>" 
                                                    onchange="updatePaymentStatus(<?= $participant['id'] ?>, this.value)"
                                                    style="border: none; background: transparent; font-weight: 600;">
                                                <option value="pending" <?= $participant['payment_status'] === 'pending' ? 'selected' : '' ?>>⏳ Wachtend</option>
                                                <option value="paid" <?= $participant['payment_status'] === 'paid' ? 'selected' : '' ?>>✅ Betaald</option>
                                                <option value="cancelled" <?= $participant['payment_status'] === 'cancelled' ? 'selected' : '' ?>>❌ Geannuleerd</option>
                                            </select>
                                            <?php if ($participant['payment_date']): ?>
                                                <br><small style="color: #6b7280;">
                                                    Betaald: <?= date('d-m-Y', strtotime($participant['payment_date'])) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <a href="users.php?user_id=<?= $participant['user_id'] ?>" class="btn btn-sm btn-secondary" title="Bekijk gebruiker">
                                                    👤
                                                </a>
                                                <button class="btn btn-sm btn-danger" onclick="removeParticipant(<?= $participant['id'] ?>)" title="Verwijderen">
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- New Course Modal -->
    <div id="newCourseModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('newCourseModal')">&times;</button>
            <h2 style="color: var(--inventijn-dark-blue); font-family: 'Space Grotesk', sans-serif; margin-bottom: 1.5rem;">
                ➕ Nieuwe Cursus Aanmaken
            </h2>
            
            <form id="newCourseForm" onsubmit="createCourse(event)">
                <div class="form-group">
                    <label for="course_name">Cursus Naam *</label>
                    <input type="text" id="course_name" name="name" required placeholder="Bijv. AI-Booster Masterclass">
                </div>
                
                <div class="form-group">
                    <label for="course_description">Beschrijving</label>
                    <textarea id="course_description" name="description" rows="3" placeholder="Korte beschrijving van de cursus..."></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="course_date">Start Datum & Tijd *</label>
                        <input type="datetime-local" id="course_date" name="course_date" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">Eind Datum & Tijd</label>
                        <input type="datetime-local" id="end_date" name="end_date">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="location">Locatie</label>
                        <input type="text" id="location" name="location" placeholder="Bijv. Amsterdam, Online">
                    </div>
                    <div class="form-group">
                        <label for="time_range">Tijd Beschrijving</label>
                        <input type="text" id="time_range" name="time_range" placeholder="Bijv. 09:00 - 17:00">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="max_participants">Max Deelnemers</label>
                        <input type="number" id="max_participants" name="max_participants" value="20" min="1">
                    </div>
                    <div class="form-group">
                        <label for="price">Prijs (€)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="access_start_time">Materiaal Beschikbaar Vanaf</label>
                    <input type="datetime-local" id="access_start_time" name="access_start_time">
                    <small style="color: var(--inventijn-purple);">Wanneer krijgen cursisten toegang tot materiaal? (standaard: dag voor cursus om 18:00)</small>
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn">💾 Cursus Aanmaken</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('newCourseModal')">Annuleren</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div id="editCourseModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editCourseModal')">&times;</button>
            <h2 style="color: var(--inventijn-dark-blue); font-family: 'Space Grotesk', sans-serif; margin-bottom: 1.5rem;">
                ✏️ Cursus Bewerken
            </h2>
            
            <form id="editCourseForm" onsubmit="updateCourse(event)">
                <input type="hidden" id="edit_course_id" name="course_id">
                
                <div class="form-group">
                    <label for="edit_course_name">Cursus Naam *</label>
                    <input type="text" id="edit_course_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_course_description">Beschrijving</label>
                    <textarea id="edit_course_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_course_date">Start Datum & Tijd *</label>
                        <input type="datetime-local" id="edit_course_date" name="course_date" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_end_date">Eind Datum & Tijd</label>
                        <input type="datetime-local" id="edit_end_date" name="end_date">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_location">Locatie</label>
                        <input type="text" id="edit_location" name="location">
                    </div>
                    <div class="form-group">
                        <label for="edit_time_range">Tijd Beschrijving</label>
                        <input type="text" id="edit_time_range" name="time_range">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_max_participants">Max Deelnemers</label>
                        <input type="number" id="edit_max_participants" name="max_participants" min="1">
                    </div>
                    <div class="form-group">
                        <label for="edit_price">Prijs (€)</label>
                        <input type="number" id="edit_price" name="price" step="0.01" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_access_start_time">Materiaal Beschikbaar Vanaf</label>
                    <input type="datetime-local" id="edit_access_start_time" name="access_start_time">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_active" name="active" checked style="margin-right: 0.5rem;">
                        Cursus is actief
                    </label>
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn">💾 Bijwerken</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCourseModal')">Annuleren</button>
                    <button type="button" class="btn btn-danger" onclick="deleteCourse()">🗑️ Deactiveren</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Participant Modal -->
    <div id="addParticipantModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('addParticipantModal')">&times;</button>
            <h2 style="color: var(--inventijn-dark-blue); font-family: 'Space Grotesk', sans-serif; margin-bottom: 1.5rem;">
                👤 Deelnemer Toevoegen
            </h2>
            
            <div style="background: var(--grey-light); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <strong>💡 Pro Tip:</strong> Voor uitgebreid gebruikersbeheer ga naar <a href="users.php" style="color: var(--orange);">Gebruikersbeheer</a>
            </div>
            
            <form id="addParticipantForm" onsubmit="addParticipantToCourse(event)">
                <div class="form-group">
                    <label for="participant_user">Selecteer Gebruiker *</label>
                    <select id="participant_user" name="user_id" required>
                        <option value="">-- Kies een gebruiker --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>">
                                <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                <?php if ($user['company']): ?> - <?= htmlspecialchars($user['company']) ?><?php endif; ?>
                                | <?= $user['paid_courses'] ?>/<?= $user['total_courses'] ?> betaald
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="participant_payment_status">Betaalstatus</label>
                    <select id="participant_payment_status" name="payment_status">
                        <option value="pending">⏳ Wachtend op betaling</option>
                        <option value="paid">✅ Betaald</option>
                        <option value="cancelled">❌ Geannuleerd</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="participant_notes">Notities</label>
                    <textarea id="participant_notes" name="notes" rows="2" placeholder="Eventuele opmerkingen..."></textarea>
                </div>
                
                <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn">✅ Toevoegen</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addParticipantModal')">Annuleren</button>
                    <a href="users.php" class="btn btn-secondary">👥 Naar Gebruikersbeheer</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        let selectedCourseId = <?= $selectedCourseId ?: 'null' ?>;
        let currentEditCourseId = null;
        
        // Course data for editing (populated from PHP)
        const coursesData = <?= json_encode($courses) ?>;
        
        // Modal functions
        function showNewCourseModal() {
            document.getElementById('newCourseModal').style.display = 'block';
        }
        
        function showAddParticipantModal() {
            if (!selectedCourseId) {
                alert('⚠️ Selecteer eerst een cursus');
                return;
            }
            document.getElementById('addParticipantModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Course selection
        function selectCourse(courseId) {
            window.location.href = `?course_id=${courseId}`;
        }
        
        // Edit course function
        function editCourse(courseId) {
            const course = coursesData.find(c => c.id == courseId);
            if (!course) {
                alert('❌ Cursus niet gevonden');
                return;
            }
            
            currentEditCourseId = courseId;
            
            // Fill the form with current data
            document.getElementById('edit_course_id').value = course.id;
            document.getElementById('edit_course_name').value = course.name || '';
            document.getElementById('edit_course_description').value = course.description || '';
            document.getElementById('edit_location').value = course.location || '';
            document.getElementById('edit_time_range').value = course.time_range || '';
            document.getElementById('edit_max_participants').value = course.max_participants || 20;
            document.getElementById('edit_price').value = course.price || '';
            document.getElementById('edit_active').checked = course.active == 1;
            
            // Format dates for datetime-local inputs
            if (course.course_date) {
                const courseDate = new Date(course.course_date);
                document.getElementById('edit_course_date').value = courseDate.toISOString().slice(0, 16);
            }
            
            if (course.end_date) {
                const endDate = new Date(course.end_date);
                document.getElementById('edit_end_date').value = endDate.toISOString().slice(0, 16);
            }
            
            if (course.access_start_time) {
                const accessTime = new Date(course.access_start_time);
                document.getElementById('edit_access_start_time').value = accessTime.toISOString().slice(0, 16);
            }
            
            // Show the modal
            document.getElementById('editCourseModal').style.display = 'block';
        }
        
        // Create new course
        async function createCourse(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'create_course');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    closeModal('newCourseModal');
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Update course
        async function updateCourse(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'update_course');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    closeModal('editCourseModal');
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Delete/deactivate course
        async function deleteCourse() {
            if (!currentEditCourseId) {
                alert('❌ Geen cursus geselecteerd');
                return;
            }
            
            if (!confirm('🗑️ Weet je zeker dat je deze cursus wilt deactiveren?\n\nDe cursus wordt niet verwijderd maar gemarkeerd als inactief.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_course');
            formData.append('course_id', currentEditCourseId);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    closeModal('editCourseModal');
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Add participant to course
        async function addParticipantToCourse(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'add_participant_to_course');
            formData.append('course_id', selectedCourseId);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    closeModal('addParticipantModal');
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Update payment status
        async function updatePaymentStatus(participantId, status) {
            const formData = new FormData();
            formData.append('action', 'update_payment_status');
            formData.append('participant_id', participantId);
            formData.append('payment_status', status);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update UI feedback
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                    location.reload(); // Reset dropdown
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
                location.reload();
            }
        }
        
        // Remove participant
        async function removeParticipant(participantId) {
            if (!confirm('🗑️ Weet je zeker dat je deze deelnemer wilt verwijderen?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'remove_participant');
            formData.append('participant_id', participantId);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Export functions
        function exportCourseData() {
            alert('📊 Export functionaliteit wordt binnenkort geactiveerd!\n\nDeze feature zal CSV/Excel export ondersteunen.');
        }
        
        function showBulkEmailModal() {
            alert('📧 Bulk email systeem wordt binnenkort geactiveerd!\n\nEmail templates zijn al geconfigureerd in de database.');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Set default access time when course date changes
        document.addEventListener('DOMContentLoaded', function() {
            function setupAccessTimeHelper(courseDateInputId, accessTimeInputId) {
                const courseDateInput = document.getElementById(courseDateInputId);
                const accessTimeInput = document.getElementById(accessTimeInputId);
                
                if (courseDateInput && accessTimeInput) {
                    courseDateInput.addEventListener('change', function() {
                        if (this.value && !accessTimeInput.value) {
                            const courseDate = new Date(this.value);
                            courseDate.setDate(courseDate.getDate() - 1);
                            courseDate.setHours(18, 0, 0, 0);
                            
                            accessTimeInput.value = courseDate.toISOString().slice(0, 16);
                        }
                    });
                }
            }
            
            // Setup for both new and edit modals
            setupAccessTimeHelper('course_date', 'access_start_time');
            setupAccessTimeHelper('edit_course_date', 'edit_access_start_time');
        });
    </script>
</body>
</html>