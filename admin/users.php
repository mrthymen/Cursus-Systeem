<?php
/**
 * Inventijn User Management Systeem
 * Gebruikers beheer, cursus toekenning en betalingen
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
            case 'create_user':
                // Genereer unieke access key
                $accessKey = bin2hex(random_bytes(16));
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, name, access_key, phone, company, notes, active) 
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $success = $stmt->execute([
                    $_POST['email'],
                    $_POST['name'], 
                    $accessKey,
                    $_POST['phone'] ?: null,
                    $_POST['company'] ?: null,
                    $_POST['notes'] ?: null
                ]);
                
                echo json_encode(['success' => $success, 'message' => $success ? 'Gebruiker aangemaakt!' : 'Fout bij aanmaken', 'access_key' => $accessKey]);
                break;
                
            case 'update_user':
                $stmt = $pdo->prepare("
                    UPDATE users SET email=?, name=?, phone=?, company=?, notes=?, active=? 
                    WHERE id=?
                ");
                $success = $stmt->execute([
                    $_POST['email'],
                    $_POST['name'],
                    $_POST['phone'] ?: null,
                    $_POST['company'] ?: null,
                    $_POST['notes'] ?: null,
                    $_POST['active'] ? 1 : 0,
                    $_POST['user_id']
                ]);
                
                echo json_encode(['success' => $success, 'message' => $success ? 'Gebruiker bijgewerkt!' : 'Fout bij bijwerken']);
                break;
                
            case 'delete_user':
                $stmt = $pdo->prepare("UPDATE users SET active = 0 WHERE id = ?");
                $success = $stmt->execute([$_POST['user_id']]);
                echo json_encode(['success' => $success, 'message' => $success ? 'Gebruiker gedeactiveerd!' : 'Fout bij deactiveren']);
                break;
                
            case 'assign_courses':
                // Parse the courses data
                $courses = json_decode($_POST['courses'], true);
                $userId = $_POST['user_id'];
                
                // First, remove all current assignments for this user
                $stmt = $pdo->prepare("DELETE FROM course_participants WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Add new assignments
                $insertStmt = $pdo->prepare("
                    INSERT INTO course_participants (user_id, course_id, payment_status, notes) 
                    VALUES (?, ?, ?, ?)
                ");
                
                $successCount = 0;
                foreach ($courses as $course) {
                    $success = $insertStmt->execute([
                        $userId,
                        $course['course_id'],
                        $course['payment_status'],
                        $course['notes'] ?: null
                    ]);
                    if ($success) $successCount++;
                }
                
                echo json_encode([
                    'success' => $successCount > 0, 
                    'message' => "Cursustoekenningen bijgewerkt! ($successCount cursussen toegekend)"
                ]);
                break;
                
            case 'get_user_courses':
                $stmt = $pdo->prepare("
                    SELECT cp.*, c.name as course_name, c.course_date, c.price
                    FROM course_participants cp
                    JOIN courses c ON cp.course_id = c.id
                    WHERE cp.user_id = ? AND c.active = 1
                    ORDER BY c.course_date ASC
                ");
                $stmt->execute([$_POST['user_id']]);
                $userCourses = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'courses' => $userCourses]);
                break;
                
            case 'regenerate_access_key':
                $newAccessKey = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("UPDATE users SET access_key = ? WHERE id = ?");
                $success = $stmt->execute([$newAccessKey, $_POST['user_id']]);
                
                echo json_encode([
                    'success' => $success, 
                    'message' => $success ? 'Nieuwe toegangscode gegenereerd!' : 'Fout bij genereren',
                    'access_key' => $newAccessKey
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Onbekende actie']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
    }
    exit;
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.company LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($status === 'active') {
    $whereClause .= " AND u.active = 1";
} elseif ($status === 'inactive') {
    $whereClause .= " AND u.active = 0";
}

// Get users with course count
$usersQuery = "
    SELECT u.*, 
           COUNT(cp.id) as course_count,
           SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_courses,
           GROUP_CONCAT(c.name SEPARATOR ', ') as course_names
    FROM users u 
    LEFT JOIN course_participants cp ON u.id = cp.user_id 
    LEFT JOIN courses c ON cp.course_id = c.id AND c.active = 1
    $whereClause
    GROUP BY u.id 
    ORDER BY u.created_at DESC 
    LIMIT $limit OFFSET $offset
";

$users = $pdo->prepare($usersQuery);
$users->execute($params);
$users = $users->fetchAll();

// Get total count for pagination
$totalQuery = "SELECT COUNT(DISTINCT u.id) FROM users u $whereClause";
$totalStmt = $pdo->prepare($totalQuery);
$totalStmt->execute($params);
$totalUsers = $totalStmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Get all courses for assignment dropdown
$allCourses = $pdo->query("
    SELECT id, name, course_date, price, max_participants,
           (SELECT COUNT(*) FROM course_participants WHERE course_id = courses.id) as current_participants
    FROM courses 
    WHERE active = 1 
    ORDER BY course_date ASC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gebruikersbeheer - Inventijn</title>
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
        
        .toolbar {
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
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--orange);
        }
        
        .filter-select {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
        }
        
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
        
        .users-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table th {
            background: var(--inventijn-dark-blue);
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .table tr:hover {
            background: rgba(62, 92, 198, 0.02);
        }
        
        .user-name {
            font-weight: 600;
            color: var(--inventijn-dark-blue);
            margin-bottom: 0.25rem;
        }
        
        .user-email {
            color: var(--inventijn-purple);
            font-size: 0.875rem;
        }
        
        .user-company {
            color: #6b7280;
            font-size: 0.8rem;
            font-style: italic;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: rgba(227, 161, 229, 0.2); color: var(--inventijn-light-pink); }
        
        .course-badge {
            background: var(--grey-light);
            color: var(--inventijn-dark-blue);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin: 0.125rem;
            display: inline-block;
        }
        
        .course-badge.paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            color: var(--inventijn-dark-blue);
        }
        
        .pagination a:hover {
            background: var(--grey-light);
        }
        
        .pagination .current {
            background: var(--inventijn-dark-blue);
            color: white;
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
            margin: 3% auto; 
            padding: 2rem; 
            border-radius: 12px; 
            max-width: 800px; 
            position: relative; 
            max-height: 90vh;
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
        
        .course-assignment {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: var(--grey-light);
        }
        
        .course-assignment.remove {
            opacity: 0.5;
            background: #fee2e2;
        }
        
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .course-name {
            font-weight: 600;
            color: var(--inventijn-dark-blue);
        }
        
        .course-meta {
            font-size: 0.875rem;
            color: var(--inventijn-purple);
        }
        
        .payment-select {
            margin-top: 0.5rem;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid var(--inventijn-light-blue);
        }
        
        .stat-card h3 {
            color: var(--inventijn-purple);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-card .value {
            color: var(--inventijn-dark-blue);
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .form-grid { grid-template-columns: 1fr; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .table { font-size: 0.875rem; }
            .table th, .table td { padding: 0.75rem 0.5rem; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>👥 Gebruikersbeheer</h1>
            <div class="nav-breadcrumb">
                <a href="index.php">Dashboard</a> → 
                <a href="courses.php">Cursussen</a> → 
                Gebruikers
            </div>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>Gebruikers Management Center</h2>
            <p>Beheer gebruikers, cursustoekenningen en betalingen vanuit één centrale plek</p>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>👥 Totaal Gebruikers</h3>
                <div class="value"><?= number_format($totalUsers) ?></div>
            </div>
            <div class="stat-card">
                <h3>✅ Actieve Gebruikers</h3>
                <div class="value"><?= count(array_filter($users, fn($u) => $u['active'])) ?></div>
            </div>
            <div class="stat-card">
                <h3>📚 Gem. Cursussen/User</h3>
                <div class="value"><?= $totalUsers > 0 ? number_format(array_sum(array_column($users, 'course_count')) / $totalUsers, 1) : '0' ?></div>
            </div>
            <div class="stat-card">
                <h3>💰 Betaalde Inschrijvingen</h3>
                <div class="value"><?= array_sum(array_column($users, 'paid_courses')) ?></div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-box">
                <input type="text" 
                       placeholder="🔍 Zoek op naam, email of bedrijf..." 
                       value="<?= htmlspecialchars($search) ?>"
                       onkeyup="if(event.key==='Enter') applyFilters()">
            </div>
            
            <select class="filter-select" onchange="applyFilters()">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Alle gebruikers</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actieve gebruikers</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactieve gebruikers</option>
            </select>
            
            <button class="btn" onclick="showNewUserModal()">
                ➕ Nieuwe Gebruiker
            </button>
            
            <button class="btn btn-secondary" onclick="showBulkImportModal()">
                📋 Bulk Import
            </button>
        </div>

        <!-- Users Table -->
        <div class="users-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Gebruiker</th>
                        <th>Contact</th>
                        <th>Cursussen</th>
                        <th>Status</th>
                        <th>Aangemaakt</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--inventijn-purple);">
                                👥 Geen gebruikers gevonden met de huidige filters.<br>
                                <button class="btn btn-sm" onclick="showNewUserModal()" style="margin-top: 1rem;">➕ Eerste Gebruiker</button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                <?php if ($user['company']): ?>
                                    <div class="user-company"><?= htmlspecialchars($user['company']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['phone']): ?>
                                    <div>📞 <?= htmlspecialchars($user['phone']) ?></div>
                                <?php endif; ?>
                                <div style="font-size: 0.8rem; color: #6b7280;">
                                    🔑 <?= substr($user['access_key'], 0, 8) ?>...
                                </div>
                            </td>
                            <td>
                                <?php if ($user['course_count'] > 0): ?>
                                    <div style="margin-bottom: 0.5rem;">
                                        <span class="course-badge">📚 <?= $user['course_count'] ?> cursussen</span>
                                        <?php if ($user['paid_courses'] > 0): ?>
                                            <span class="course-badge paid">💰 <?= $user['paid_courses'] ?> betaald</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($user['course_names']): ?>
                                        <div style="font-size: 0.8rem; color: var(--inventijn-purple);">
                                            <?= htmlspecialchars(strlen($user['course_names']) > 50 ? substr($user['course_names'], 0, 50) . '...' : $user['course_names']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #6b7280; font-style: italic;">Geen cursussen</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $user['active'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $user['active'] ? '✅ Actief' : '❌ Inactief' ?>
                                </span>
                            </td>
                            <td>
                                <?= date('d-m-Y', strtotime($user['created_at'])) ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button class="btn btn-sm btn-secondary" onclick="editUser(<?= $user['id'] ?>)" title="Bewerken">
                                        ✏️
                                    </button>
                                    <button class="btn btn-sm" onclick="assignCourses(<?= $user['id'] ?>)" title="Cursussen toekennen">
                                        📚
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $user['id'] ?>)" title="Deactiveren">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>">‹ Vorige</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>">Volgende ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- New User Modal -->
    <div id="newUserModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('newUserModal')">&times;</button>
            <h2 style="color: var(--inventijn-dark-blue); font-family: 'Space Grotesk', sans-serif; margin-bottom: 1.5rem;">
                ➕ Nieuwe Gebruiker Aanmaken
            </h2>
            
            <form id="newUserForm" onsubmit="createUser(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="user_name">Volledige Naam *</label>
                        <input type="text" id="user_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="user_email">E-mailadres *</label>
                        <input type="email" id="user_email" name="email" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="user_phone">Telefoonnummer</label>
                        <input type="tel" id="user_phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="user_company">Bedrijf</label>
                        <input type="text" id="user_company" name="company">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="user_notes">Notities</label>
                    <textarea id="user_notes" name="notes" rows="2" placeholder="Eventuele opmerkingen..."></textarea>
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn">💾 Gebruiker Aanmaken</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('newUserModal')">Annuleren</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
            <h2 style="color: var(--inventijn-dark-blue); font-family: 'Space Grotesk', sans-serif; margin-bottom: 1.5rem;">
                ✏️ Gebruiker Bewerken
            </h2>
            
            <form id="editUserForm" onsubmit="updateUser(event)">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_user_name">Volledige Naam *</label>
                        <input type="text" id="edit_user_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_user_email">E-mailadres *</label>
                        <input type="email" id="edit_user_email" name="email" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_user_phone">Telefoonnummer</label>
                        <input type="tel" id="edit_user_phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="edit_user_company">Bedrijf</label>
                        <input type="text" id="edit_user_company" name="company">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_user_notes">Notities</label>
                    <textarea id="edit_user_notes" name="notes" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_user_active" name="active" style="margin-right: 0.5rem;">
                        Gebruiker is actief
                    </label>
                </div>
                
                <div style="background: var(--grey-light); padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                    <div style="font-size: 0.9rem; color: var(--inventijn-dark-blue); margin-bottom: 0.5rem;">
                        <strong>Huidige Toegangscode:</strong>
                    </div>
                    <div style="font-family: monospace; font-size: 0.8rem; color: var(--inventijn-purple);" id="current_access_key">
                        <!-- Filled by JavaScript -->
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="regenerateAccessKey()" style="margin-top: 0.5rem;">
                        🔄 Nieuwe Code Genereren
                    </button>
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn">💾 Bijwerken</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Annuleren</button>
                    <button type="button" class="btn btn-danger" onclick="deleteUser(currentEditUserId)">🗑️ Deactiveren</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Course Assignment Modal -->
    <div id="courseAssignmentModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('courseAssignmentModal')">&times;</button>
            <h2 style="color: var(--inventijn-dark-blue); font-family: 'Space Grotesk', sans-serif; margin-bottom: 1.5rem;">
                📚 Cursussen Toekennen
            </h2>
            
            <div id="user_course_info" style="background: var(--grey-light); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <!-- Filled by JavaScript -->
            </div>
            
            <div id="course_assignments">
                <!-- Course assignments will be added here by JavaScript -->
            </div>
            
            <div style="margin: 1rem 0; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                <button type="button" class="btn btn-secondary" onclick="addCourseAssignment()">
                    ➕ Cursus Toevoegen
                </button>
            </div>
            
            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                <button type="button" class="btn" onclick="saveCourseAssignments()">💾 Cursussen Opslaan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('courseAssignmentModal')">Annuleren</button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentEditUserId = null;
        let currentAssignUserId = null;
        let courseAssignmentCounter = 0;
        
        // All users and courses data (for JavaScript operations)
        const usersData = <?= json_encode($users) ?>;
        const allCoursesData = <?= json_encode($allCourses) ?>;
        
        // Modal functions
        function showNewUserModal() {
            document.getElementById('newUserModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Search and filter functions
        function applyFilters() {
            const search = document.querySelector('.search-box input').value;
            const status = document.querySelector('.filter-select').value;
            
            const url = new URL(window.location);
            url.searchParams.set('search', search);
            url.searchParams.set('status', status);
            url.searchParams.set('page', '1'); // Reset to first page
            
            window.location.href = url.toString();
        }
        
        // Create new user
        async function createUser(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'create_user');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message + '\nToegangssleutel: ' + result.access_key);
                    closeModal('newUserModal');
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Edit user
        function editUser(userId) {
            const user = usersData.find(u => u.id == userId);
            if (!user) {
                alert('❌ Gebruiker niet gevonden');
                return;
            }
            
            currentEditUserId = userId;
            
            // Fill the form
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_user_name').value = user.name || '';
            document.getElementById('edit_user_email').value = user.email || '';
            document.getElementById('edit_user_phone').value = user.phone || '';
            document.getElementById('edit_user_company').value = user.company || '';
            document.getElementById('edit_user_notes').value = user.notes || '';
            document.getElementById('edit_user_active').checked = user.active == 1;
            document.getElementById('current_access_key').textContent = user.access_key;
            
            document.getElementById('editUserModal').style.display = 'block';
        }
        
        // Update user
        async function updateUser(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'update_user');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    closeModal('editUserModal');
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Delete user
        async function deleteUser(userId) {
            if (!confirm('🗑️ Weet je zeker dat je deze gebruiker wilt deactiveren?\n\nDe gebruiker wordt niet verwijderd maar gemarkeerd als inactief.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    closeModal('editUserModal');
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Regenerate access key
        async function regenerateAccessKey() {
            if (!confirm('🔄 Weet je zeker dat je een nieuwe toegangscode wilt genereren?\n\nDe oude code werkt dan niet meer.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'regenerate_access_key');
            formData.append('user_id', currentEditUserId);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('current_access_key').textContent = result.access_key;
                    alert('✅ ' + result.message + '\nNieuwe code: ' + result.access_key);
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Course assignment functions
        async function assignCourses(userId) {
            const user = usersData.find(u => u.id == userId);
            if (!user) {
                alert('❌ Gebruiker niet gevonden');
                return;
            }
            
            currentAssignUserId = userId;
            courseAssignmentCounter = 0;
            
            // Set user info
            document.getElementById('user_course_info').innerHTML = `
                <div style="font-weight: 600; color: var(--inventijn-dark-blue); margin-bottom: 0.5rem;">
                    👤 ${user.name}
                </div>
                <div style="font-size: 0.9rem; color: var(--inventijn-purple);">
                    📧 ${user.email} | 📚 ${user.course_count} cursussen | 💰 ${user.paid_courses} betaald
                </div>
            `;
            
            // Get current course assignments
            try {
                const formData = new FormData();
                formData.append('action', 'get_user_courses');
                formData.append('user_id', userId);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Clear assignments
                    document.getElementById('course_assignments').innerHTML = '';
                    
                    // Add existing assignments
                    result.courses.forEach(course => {
                        addCourseAssignment(course);
                    });
                    
                    // Show modal
                    document.getElementById('courseAssignmentModal').style.display = 'block';
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Add course assignment row
        function addCourseAssignment(existingCourse = null) {
            const id = ++courseAssignmentCounter;
            const container = document.getElementById('course_assignments');
            
            const assignmentDiv = document.createElement('div');
            assignmentDiv.className = 'course-assignment';
            assignmentDiv.id = `assignment-${id}`;
            
            let courseOptions = '<option value="">-- Selecteer Cursus --</option>';
            allCoursesData.forEach(course => {
                const selected = existingCourse && existingCourse.course_id == course.id ? 'selected' : '';
                const courseInfo = `${course.name} (${new Date(course.course_date).toLocaleDateString('nl-NL')})`;
                courseOptions += `<option value="${course.id}" ${selected}>${courseInfo}</option>`;
            });
            
            const paymentStatus = existingCourse ? existingCourse.payment_status : 'pending';
            
            assignmentDiv.innerHTML = `
                <div class="course-header">
                    <div class="course-name">Cursus Toekenning #${id}</div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeCourseAssignment(${id})">
                        🗑️ Verwijderen
                    </button>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Cursus:</label>
                        <select name="course_id" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
                            ${courseOptions}
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Betaalstatus:</label>
                        <select name="payment_status" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="pending" ${paymentStatus === 'pending' ? 'selected' : ''}>⏳ Wachtend</option>
                            <option value="paid" ${paymentStatus === 'paid' ? 'selected' : ''}>✅ Betaald</option>
                            <option value="cancelled" ${paymentStatus === 'cancelled' ? 'selected' : ''}>❌ Geannuleerd</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 0.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Notities:</label>
                    <input type="text" name="notes" placeholder="Eventuele opmerkingen..." 
                           value="${existingCourse ? (existingCourse.notes || '') : ''}"
                           style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            `;
            
            container.appendChild(assignmentDiv);
        }
        
        // Remove course assignment
        function removeCourseAssignment(id) {
            const element = document.getElementById(`assignment-${id}`);
            if (element) {
                element.remove();
            }
        }
        
        // Save course assignments
        async function saveCourseAssignments() {
            const assignments = [];
            const container = document.getElementById('course_assignments');
            const assignmentDivs = container.querySelectorAll('.course-assignment');
            
            assignmentDivs.forEach(div => {
                const courseId = div.querySelector('select[name="course_id"]').value;
                const paymentStatus = div.querySelector('select[name="payment_status"]').value;
                const notes = div.querySelector('input[name="notes"]').value;
                
                if (courseId) {
                    assignments.push({
                        course_id: courseId,
                        payment_status: paymentStatus,
                        notes: notes
                    });
                }
            });
            
            const formData = new FormData();
            formData.append('action', 'assign_courses');
            formData.append('user_id', currentAssignUserId);
            formData.append('courses', JSON.stringify(assignments));
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    closeModal('courseAssignmentModal');
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Er is een fout opgetreden: ' + error.message);
            }
        }
        
        // Bulk import placeholder
        function showBulkImportModal() {
            alert('📋 Bulk import functionaliteit komt binnenkort!\n\nDeze feature zal CSV import ondersteunen voor grote aantallen gebruikers.');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>