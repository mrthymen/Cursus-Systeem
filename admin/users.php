<?php
/**
 * Inventijn User Management System v6.4.0 - EMERGENCY FIXED VERSION
 * Updated: 2025-06-13
 * FIXED: JavaScript functions not loading - complete rewrite
 */

session_start();

// CRITICAL: Handle AJAX requests FIRST before any HTML output
if (isset($_GET['ajax']) && isset($_GET['action'])) {
    require_once '../includes/config.php';
    header('Content-Type: application/json');
    
    try {
        $pdo = getDatabase();
        
        switch ($_GET['action']) {
            case 'get_user':
                if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_GET['id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        echo json_encode($user);
                    } else {
                        echo json_encode(['error' => 'Gebruiker niet gevonden']);
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

// NOW include HTML header
$page_title = 'Gebruikersbeheer';
require_once 'admin_header.php';

// Authentication check
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=' . basename($_SERVER['PHP_SELF']));
    exit;
}

require_once '../includes/config.php';

try {
    $pdo = getDatabase();
} catch (Exception $e) {
    die('Database verbinding mislukt: ' . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_user':
                $accessKey = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("INSERT INTO users (email, name, access_key, phone, company, notes, active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $success = $stmt->execute([$_POST['email'], $_POST['name'], $accessKey, $_POST['phone'] ?: null, $_POST['company'] ?: null, $_POST['notes'] ?: null]);
                
                if ($success) {
                    $_SESSION['admin_message'] = 'Gebruiker aangemaakt! Toegangscode: ' . $accessKey;
                    $_SESSION['admin_message_type'] = 'success';
                }
                break;
                
            case 'update_user':
                $stmt = $pdo->prepare("UPDATE users SET email=?, name=?, phone=?, company=?, notes=?, active=? WHERE id=?");
                $success = $stmt->execute([$_POST['email'], $_POST['name'], $_POST['phone'] ?: null, $_POST['company'] ?: null, $_POST['notes'] ?: null, isset($_POST['active']) ? 1 : 0, $_POST['user_id']]);
                
                if ($success) {
                    $_SESSION['admin_message'] = 'Gebruiker bijgewerkt!';
                    $_SESSION['admin_message_type'] = 'success';
                }
                break;
                
            case 'delete_user':
                $stmt = $pdo->prepare("UPDATE users SET active = 0 WHERE id = ?");
                $success = $stmt->execute([$_POST['user_id']]);
                
                if ($success) {
                    $_SESSION['admin_message'] = 'Gebruiker gedeactiveerd!';
                    $_SESSION['admin_message_type'] = 'success';
                }
                break;
        }
    } catch (Exception $e) {
        $_SESSION['admin_message'] = 'Database fout: ' . $e->getMessage();
        $_SESSION['admin_message_type'] = 'error';
    }
    
    header('Location: ' . basename($_SERVER['PHP_SELF']));
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

// Get users
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

// Get total count
$totalQuery = "SELECT COUNT(DISTINCT u.id) FROM users u $whereClause";
$totalStmt = $pdo->prepare($totalQuery);
$totalStmt->execute($params);
$totalUsers = $totalStmt->fetchColumn();

// Calculate statistics
$statsQuery = "SELECT COUNT(*) as total_users, SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_users, SUM(CASE WHEN DATE(created_at) > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_users_week FROM users";
$stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
$stats['paid_courses'] = $pdo->query("SELECT COUNT(*) FROM course_participants WHERE payment_status = 'paid'")->fetchColumn();
?>

<!-- Page content -->
<div class="container">
    <!-- Integrated Toolbar with Stats -->
    <div class="card">
        <div class="card-header">
            <h2>👥 Gebruikersbeheer</h2>
            <p style="color: var(--text-secondary); margin: 0;">Beheer gebruikers, cursustoekenningen en betalingen</p>
        </div>
        
        <!-- Statistics Row -->
        <div class="course-essentials" style="margin: 0; border-radius: 0; border-bottom: 1px solid var(--border);">
            <div class="essential-item">
                <div class="essential-label">👥 Totaal Gebruikers</div>
                <div class="essential-value"><?= number_format($stats['total_users']) ?></div>
            </div>
            <div class="essential-item">
                <div class="essential-label">✅ Actieve Gebruikers</div>
                <div class="essential-value"><?= number_format($stats['active_users']) ?></div>
            </div>
            <div class="essential-item">
                <div class="essential-label">📈 Nieuwe (7 dagen)</div>
                <div class="essential-value"><?= number_format($stats['new_users_week']) ?></div>
            </div>
            <div class="essential-item">
                <div class="essential-label">💰 Betaalde Inschrijvingen</div>
                <div class="essential-value"><?= number_format($stats['paid_courses']) ?></div>
            </div>
        </div>
        
        <!-- Toolbar Controls -->
        <div style="padding: 1.5rem;">
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" 
                           placeholder="🔍 Zoek op naam, email of bedrijf..." 
                           value="<?= htmlspecialchars($search) ?>"
                           id="searchInput"
                           onkeyup="if(event.key==='Enter') applyFilters()"
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 6px;">
                </div>
                
                <select id="statusFilter" onchange="applyFilters()" style="padding: 0.75rem; border: 1px solid var(--border); border-radius: 6px;">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Alle gebruikers</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actieve gebruikers</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactieve gebruikers</option>
                </select>
                
                <button onclick="openModal('userModal')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nieuwe Gebruiker
                </button>
                
                <button onclick="showBulkImportModal()" class="btn btn-secondary">
                    <i class="fas fa-upload"></i> Bulk Import
                </button>
            </div>
        </div>
    </div>

    <!-- Users Grid -->
    <div class="course-grid">
        <?php if (empty($users)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>Geen gebruikers gevonden</h3>
                    <p>Er zijn geen gebruikers die voldoen aan de huidige filters.</p>
                    <button onclick="openModal('userModal')" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Eerste Gebruiker
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <?php
                $user_age_days = $user['created_at'] ? floor((time() - strtotime($user['created_at'])) / 86400) : 0;
                $is_new_user = $user_age_days <= 7;
                $total_value = ($user['paid_courses'] ?? 0) * 497;
                
                $activity_status = 'planned';
                $activity_label = 'Rustig';
                if ($user['course_count'] >= 3) {
                    $activity_status = 'soon';
                    $activity_label = 'Actief';
                } elseif ($user['course_count'] >= 1) {
                    $activity_status = 'upcoming';
                    $activity_label = 'Betrokken';
                }
                ?>
                
                <div class="course-item">
                    <div class="course-header">
                        <div>
                            <h2 class="course-title"><?= htmlspecialchars($user['name']) ?></h2>
                            <div class="course-subtitle">
                                <strong>Email:</strong> <?= htmlspecialchars($user['email']) ?>
                                <?php if ($user['company']): ?>
                                    | <strong>Bedrijf:</strong> <?= htmlspecialchars($user['company']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-end;">
                            <?php if ($is_new_user): ?>
                                <span class="date-status soon">NIEUW</span>
                            <?php endif; ?>
                            <span class="date-status <?= $activity_status ?>"><?= $activity_label ?></span>
                        </div>
                    </div>
                    
                    <!-- User Essentials -->
                    <div class="course-essentials">
                        <div class="essential-item">
                            <div class="essential-label">Lid sinds</div>
                            <div class="essential-value">
                                <?= date('d M Y', strtotime($user['created_at'])) ?>
                                <br><small>(<?= $user_age_days ?> dagen)</small>
                            </div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Contact</div>
                            <div class="essential-value">
                                <?php if ($user['phone']): ?>
                                    📞 <?= htmlspecialchars($user['phone']) ?><br>
                                <?php endif; ?>
                                🔑 <?= substr($user['access_key'], 0, 8) ?>...
                            </div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Inschrijvingen</div>
                            <div class="essential-value">
                                <?= $user['course_count'] ?? 0 ?>/<?= $user['paid_courses'] ?? 0 ?>
                                <br><small>totaal/betaald</small>
                            </div>
                        </div>
                        <div class="essential-item">
                            <div class="essential-label">Status</div>
                            <div class="essential-value">
                                <?= $user['active'] ? '✅ Actief' : '❌ Inactief' ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Course Participation -->
                    <?php if ($user['course_count'] > 0): ?>
                        <div class="participants-preview">
                            <div class="participants-header">
                                <span><i class="fas fa-graduation-cap"></i> Cursus Participatie (<?= $user['course_count'] ?>)</span>
                                <span><?= $user['paid_courses'] ?? 0 ?>/<?= $user['course_count'] ?? 0 ?> betaald</span>
                            </div>
                            <div class="participant-list">
                                <?php if ($user['course_names']): ?>
                                    <?php foreach (array_slice(explode(', ', $user['course_names']), 0, 3) as $course_name): ?>
                                        <div class="participant-item">
                                            <div class="participant-name"><?= htmlspecialchars($course_name) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="participants-preview">
                            <div class="no-participants">
                                <i class="fas fa-user-graduate"></i>
                                Nog geen cursus participatie
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="btn-group">
                        <button onclick="editUser(<?= $user['id'] ?>)" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Bewerken
                        </button>
                        
                        <button onclick="assignCourses(<?= $user['id'] ?>)" class="btn btn-success">
                            <i class="fas fa-graduation-cap"></i> Cursussen
                        </button>
                        
                        <?php if ($user['active']): ?>
                            <button onclick="deleteUser(<?= $user['id'] ?>)" class="btn btn-warning">
                                <i class="fas fa-pause"></i> Deactiveren
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="userModalTitle">Nieuwe Gebruiker Aanmaken</h3>
            <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="userForm">
                <input type="hidden" name="action" value="create_user" id="userAction">
                <input type="hidden" name="user_id" id="userId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Volledige Naam</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mailadres</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Telefoonnummer</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="company">Bedrijf</label>
                        <input type="text" id="company" name="company">
                    </div>
                    <div class="form-group full-width">
                        <label for="notes">Notities</label>
                        <textarea id="notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="active" value="1" id="active" checked>
                            Gebruiker is actief
                        </label>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span id="userModalSubmitText">Gebruiker Aanmaken</span>
                    </button>
                    <button type="button" onclick="closeModal('userModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuleren
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>

<!-- EMERGENCY JAVASCRIPT - COMPLETE REWRITE -->
<script>
console.log('🔥 EMERGENCY JavaScript Loading...');

// Define ALL functions as regular functions first
function openModal(modalId) {
    console.log('📖 openModal called:', modalId);
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        console.log('✅ Modal opened:', modalId);
    } else {
        console.error('❌ Modal not found:', modalId);
    }
}

function closeModal(modalId) {
    console.log('📕 closeModal called:', modalId);
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        console.log('✅ Modal closed:', modalId);
    }
}

function editUser(userId) {
    console.log('✏️ editUser called:', userId);
    alert('Edit User function called with ID: ' + userId);
}

function deleteUser(userId) {
    console.log('🗑️ deleteUser called:', userId);
    if (confirm('Weet je zeker dat je deze gebruiker wilt deactiveren?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="' + userId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function assignCourses(userId) {
    console.log('📚 assignCourses called:', userId);
    alert('Assign Courses function called with ID: ' + userId);
}

function showBulkImportModal() {
    console.log('📋 showBulkImportModal called');
    alert('Bulk Import function called');
}

function applyFilters() {
    console.log('🔍 applyFilters called');
    var search = document.getElementById('searchInput').value;
    var status = document.getElementById('statusFilter').value;
    
    var url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('status', status);
    url.searchParams.set('page', '1');
    
    window.location.href = url.toString();
}

// Also assign to window object
window.openModal = openModal;
window.closeModal = closeModal;
window.editUser = editUser;
window.deleteUser = deleteUser;
window.assignCourses = assignCourses;
window.showBulkImportModal = showBulkImportModal;
window.applyFilters = applyFilters;

console.log('🎯 All functions defined');

// Test after a delay
setTimeout(function() {
    console.log('🧪 Testing functions:');
    console.log('- openModal type:', typeof openModal);
    console.log('- editUser type:', typeof editUser);
    console.log('- deleteUser type:', typeof deleteUser);
    console.log('- assignCourses type:', typeof assignCourses);
    console.log('- showBulkImportModal type:', typeof showBulkImportModal);
    
    if (typeof openModal === 'function') {
        console.log('🎉 SUCCESS: Functions are working!');
    } else {
        console.error('💥 FAIL: Functions still not working');
    }
}, 200);

// Close modal on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
};
</script>