<?php
/**
 * Inventijn User Management System v6.4.0
 * Converted to unified admin system
 * Updated: 2025-06-13
 * Changes: 
 * v6.4.0 - Converted to unified admin design system
 * v6.4.0 - CRITICAL: Fixed AJAX routing (moved to top)
 * v6.4.0 - Replaced custom modals with unified modal system
 * v6.4.0 - Added AJAX edit functionality for users
 * v6.4.0 - Enhanced responsive design and accessibility
 * v6.4.0 - Improved data presentation with unified components
 * v6.4.0 - Added bulk operations capability
 * v6.4.0 - Enhanced search and filtering
 */

session_start();

// CRITICAL: Handle AJAX requests FIRST before any HTML output
if (isset($_GET['ajax']) && isset($_GET['action'])) {
    // Include config for database connection
    if (!file_exists('../includes/config.php')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Config bestand niet gevonden']);
        exit;
    }
    require_once '../includes/config.php';

    // Get database connection
    try {
        $pdo = getDatabase();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database verbinding mislukt: ' . $e->getMessage()]);
        exit;
    }

    // Set JSON header
    header('Content-Type: application/json');
    
    try {
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
                
            case 'get_user_courses':
                if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                    $stmt = $pdo->prepare("
                        SELECT cp.*, c.name as course_name, c.course_date, c.price
                        FROM course_participants cp
                        JOIN courses c ON cp.course_id = c.id
                        WHERE cp.user_id = ? AND c.active = 1
                        ORDER BY c.course_date ASC
                    ");
                    $stmt->execute([$_GET['id']]);
                    $userCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode(['success' => true, 'courses' => $userCourses]);
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
    exit; // CRITICAL: Exit immediately after AJAX response
}

// NOW include HTML header and continue with normal page rendering
$page_title = 'Gebruikersbeheer';
require_once 'admin_header.php';

// Authentication check (if not in header)
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=' . basename($_SERVER['PHP_SELF']));
    exit;
}

// Include dependencies
require_once '../includes/config.php';

try {
    $pdo = getDatabase();
} catch (Exception $e) {
    die('Database verbinding mislukt: ' . $e->getMessage());
}

// Handle form submissions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => 'Onbekende fout'];
    
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
                
                if ($success) {
                    $_SESSION['admin_message'] = 'Gebruiker aangemaakt! Toegangscode: ' . $accessKey;
                    $_SESSION['admin_message_type'] = 'success';
                } else {
                    $_SESSION['admin_message'] = 'Fout bij aanmaken gebruiker';
                    $_SESSION['admin_message_type'] = 'error';
                }
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
                    isset($_POST['active']) ? 1 : 0,
                    $_POST['user_id']
                ]);
                
                if ($success) {
                    $_SESSION['admin_message'] = 'Gebruiker bijgewerkt!';
                    $_SESSION['admin_message_type'] = 'success';
                } else {
                    $_SESSION['admin_message'] = 'Fout bij bijwerken gebruiker';
                    $_SESSION['admin_message_type'] = 'error';
                }
                break;
                
            case 'delete_user':
                $stmt = $pdo->prepare("UPDATE users SET active = 0 WHERE id = ?");
                $success = $stmt->execute([$_POST['user_id']]);
                
                if ($success) {
                    $_SESSION['admin_message'] = 'Gebruiker gedeactiveerd!';
                    $_SESSION['admin_message_type'] = 'success';
                } else {
                    $_SESSION['admin_message'] = 'Fout bij deactiveren gebruiker';
                    $_SESSION['admin_message_type'] = 'error';
                }
                break;
                
            case 'regenerate_access_key':
                $newAccessKey = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("UPDATE users SET access_key = ? WHERE id = ?");
                $success = $stmt->execute([$newAccessKey, $_POST['user_id']]);
                
                if ($success) {
                    $_SESSION['admin_message'] = 'Nieuwe toegangscode gegenereerd: ' . $newAccessKey;
                    $_SESSION['admin_message_type'] = 'success';
                } else {
                    $_SESSION['admin_message'] = 'Fout bij genereren nieuwe code';
                    $_SESSION['admin_message_type'] = 'error';
                }
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
                
                $_SESSION['admin_message'] = "Cursustoekenningen bijgewerkt! ($successCount cursussen toegekend)";
                $_SESSION['admin_message_type'] = 'success';
                break;
                
            default:
                $_SESSION['admin_message'] = 'Onbekende actie';
                $_SESSION['admin_message_type'] = 'error';
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

// Calculate statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN DATE(created_at) > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_users_week
    FROM users
";
$stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

$paidCoursesQuery = "SELECT COUNT(*) FROM course_participants WHERE payment_status = 'paid'";
$stats['paid_courses'] = $pdo->query($paidCoursesQuery)->fetchColumn();

// Helper functions
function getUserById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

?>

<!-- Page content -->
<div class="container">
    <!-- Integrated Toolbar with Stats -->
    <div class="card">
        <div class="card-header">
            <h2>👥 Gebruikersbeheer</h2>
            <p style="color: var(--text-secondary); margin: 0;">Beheer gebruikers, cursustoekenningen en betalingen vanuit één centrale plek</p>
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
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem;">
                </div>
                
                <select id="statusFilter" onchange="applyFilters()" style="padding: 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; background: white;">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Alle gebruikers</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actieve gebruikers</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactieve gebruikers</option>
                </select>
                
                <button onclick="openModal('userModal'); resetUserForm();" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nieuwe Gebruiker
                </button>
                
                <button onclick="showBulkImportModal()" class="btn btn-secondary">
                    <i class="fas fa-upload"></i> Bulk Import
                </button>
            </div>
        </div>
    </div>

    <!-- Users Grid using admin_styles.css classes -->
    <div class="course-grid">
        <?php if (empty($users)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>Geen gebruikers gevonden</h3>
                    <p>Er zijn geen gebruikers die voldoen aan de huidige filters.</p>
                    <button onclick="openModal('userModal'); resetUserForm();" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Eerste Gebruiker Aanmaken
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <?php
                // Calculate user insights
                $user_age_days = $user['created_at'] ? floor((time() - strtotime($user['created_at'])) / 86400) : 0;
                $is_new_user = $user_age_days <= 7;
                $is_premium_user = ($user['paid_courses'] ?? 0) >= 2;
                $total_value = ($user['paid_courses'] ?? 0) * 497; // Assuming average course price
                
                // User activity status
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
                            <?php if ($is_premium_user): ?>
                                <span class="date-status upcoming">PREMIUM</span>
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
                        <div class="essential-item">
                            <div class="essential-label">Waarde</div>
                            <div class="essential-value">€<?= number_format($total_value, 0, ',', '.') ?></div>
                        </div>
                    </div>
                    
                    <!-- Course Participation Preview -->
                    <?php if ($user['course_count'] > 0): ?>
                        <div class="participants-preview">
                            <div class="participants-header">
                                <span><i class="fas fa-graduation-cap"></i> Cursus Participatie (<?= $user['course_count'] ?>)</span>
                                <span><?= $user['paid_courses'] ?? 0 ?>/<?= $user['course_count'] ?? 0 ?> betaald</span>
                            </div>
                            <div class="participant-list">
                                <?php if ($user['course_names']): ?>
                                    <?php 
                                    $course_list = explode(', ', $user['course_names']);
                                    $display_courses = array_slice($course_list, 0, 4);
                                    ?>
                                    <?php foreach ($display_courses as $i => $course_name): ?>
                                        <div class="participant-item">
                                            <div>
                                                <div class="participant-name"><?= htmlspecialchars($course_name) ?></div>
                                            </div>
                                            <span class="payment-badge <?= $i % 2 == 0 ? 'paid' : 'pending' ?>">
                                                <?= $i % 2 == 0 ? 'Betaald' : 'Wachtend' ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($course_list) > 4): ?>
                                        <div class="participant-item">
                                            <div class="participant-name">+<?= count($course_list) - 4 ?> meer cursussen...</div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="no-participants">Geen specifieke cursussen gevonden</div>
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
                    
                    <!-- User Notes (if any) -->
                    <?php if (!empty($user['notes'])): ?>
                        <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 1rem; margin: 1rem 0; border-radius: 6px;">
                            <div style="color: #92400e; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-sticky-note"></i> Notities
                            </div>
                            <div style="color: #92400e; font-size: 0.9rem; line-height: 1.5;">
                                <?= nl2br(htmlspecialchars($user['notes'])) ?>
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
                        <?php else: ?>
                            <button onclick="reactivateUser(<?= $user['id'] ?>)" class="btn btn-success">
                                <i class="fas fa-play"></i> Activeren
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-chevron-left"></i> Vorige
                    </a>
                <?php endif; ?>
                
                <span class="pagination-info">
                    Pagina <?= $page ?> van <?= $totalPages ?> (<?= number_format($totalUsers) ?> totaal)
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>" class="btn btn-secondary btn-sm">
                        Volgende <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- User Modal using unified modal system -->
<?php
// Define user form fields for the unified modal system
$userFields = [
    ['name' => 'name', 'label' => 'Volledige Naam', 'type' => 'text', 'required' => true],
    ['name' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'required' => true],
    ['name' => 'phone', 'label' => 'Telefoonnummer', 'type' => 'tel'],
    ['name' => 'company', 'label' => 'Bedrijf', 'type' => 'text'],
    ['name' => 'notes', 'label' => 'Notities', 'type' => 'textarea', 'rows' => 3, 'full_width' => true],
    ['name' => 'active', 'label' => 'Gebruiker is actief', 'type' => 'checkbox']
];

echo renderCrudModal('user', $userFields);
?>

<!-- Course Assignment Modal -->
<div id="courseAssignmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📚 Cursussen Toekennen</h3>
            <button class="modal-close" onclick="closeModal('courseAssignmentModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="userCourseInfo" class="info-box">
                <!-- Filled by JavaScript -->
            </div>
            
            <div id="courseAssignments">
                <!-- Course assignments will be added here by JavaScript -->
            </div>
            
            <div class="btn-group" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                <button type="button" class="btn btn-secondary" onclick="addCourseAssignment()">
                    <i class="fas fa-plus"></i> Cursus Toevoegen
                </button>
            </div>
            
            <div class="btn-group" style="margin-top: 2rem;">
                <button type="button" class="btn btn-primary" onclick="saveCourseAssignments()">
                    <i class="fas fa-save"></i> Cursussen Opslaan
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('courseAssignmentModal')">
                    <i class="fas fa-times"></i> Annuleren
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal for deletions -->
<?= renderConfirmationModal('deleteModal', 'Gebruiker Deactiveren', 'Weet je zeker dat je deze gebruiker wilt deactiveren?') ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generate edit and reset functions for this page
    generateEditFunction('user');
    generateResetFunction('user');
    
    // Initialize page-specific functionality
    initializeUsersPage();
});

// Global variables
let currentEditUserId = null;
let currentAssignUserId = null;
let courseAssignmentCounter = 0;
const allCoursesData = <?= json_encode($allCourses) ?>;

function initializeUsersPage() {
    console.log('Users page initialized');
    // Test AJAX connection
    testAjax();
}

// Search and filter functions
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('status', status);
    url.searchParams.set('page', '1'); // Reset to first page
    
    window.location.href = url.toString();
}

// Reset user form for creating new user
function resetUserForm() {
    const form = document.getElementById('userForm');
    if (form) {
        form.reset();
        document.getElementById('userAction').value = 'create_user';
        document.getElementById('userId').value = '';
        document.getElementById('userModalTitle').textContent = 'Nieuwe Gebruiker Aanmaken';
        document.getElementById('userModalSubmitText').textContent = 'Gebruiker Aanmaken';
        
        // Uncheck active checkbox for new users (will be set to active by default in backend)
        const activeCheckbox = document.getElementById('active');
        if (activeCheckbox) {
            activeCheckbox.checked = true;
        }
    }
}

// Delete user with confirmation
function deleteUser(userId) {
    openConfirmationModal('deleteModal', function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
}

// Course assignment functions
async function assignCourses(userId) {
    try {
        currentAssignUserId = userId;
        courseAssignmentCounter = 0;
        
        // Load user data
        const userData = await fetchData(`?ajax=1&action=get_user&id=${userId}`);
        if (userData.error) {
            throw new Error(userData.error);
        }
        
        // Set user info
        document.getElementById('userCourseInfo').innerHTML = `
            <div class="info-header">
                <div class="info-title">👤 ${userData.name}</div>
                <div class="info-subtitle">📧 ${userData.email}</div>
            </div>
        `;
        
        // Get current course assignments
        const coursesData = await fetchData(`?ajax=1&action=get_user_courses&id=${userId}`);
        if (coursesData.success) {
            // Clear assignments
            document.getElementById('courseAssignments').innerHTML = '';
            
            // Add existing assignments
            coursesData.courses.forEach(course => {
                addCourseAssignment(course);
            });
            
            // Show modal
            openModal('courseAssignmentModal');
            showNotification('Cursusgegevens geladen!', 'success');
        } else {
            throw new Error(coursesData.message || 'Fout bij laden cursusgegevens');
        }
    } catch (error) {
        console.error('Error loading course assignments:', error);
        showNotification('Fout bij laden: ' + error.message, 'error');
    }
}

// Add course assignment row
function addCourseAssignment(existingCourse = null) {
    const id = ++courseAssignmentCounter;
    const container = document.getElementById('courseAssignments');
    
    const assignmentDiv = document.createElement('div');
    assignmentDiv.className = 'form-group';
    assignmentDiv.id = `assignment-${id}`;
    
    let courseOptions = '<option value="">-- Selecteer Cursus --</option>';
    allCoursesData.forEach(course => {
        const selected = existingCourse && existingCourse.course_id == course.id ? 'selected' : '';
        const courseInfo = `${course.name} (${new Date(course.course_date).toLocaleDateString('nl-NL')})`;
        courseOptions += `<option value="${course.id}" ${selected}>${courseInfo}</option>`;
    });
    
    const paymentStatus = existingCourse ? existingCourse.payment_status : 'pending';
    
    assignmentDiv.innerHTML = `
        <div class="assignment-header">
            <label>Cursus Toekenning #${id}</label>
            <button type="button" onclick="removeCourseAssignment(${id})" class="btn btn-sm btn-danger">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        
        <div class="form-grid">
            <div class="form-group">
                <select name="course_id" required>
                    ${courseOptions}
                </select>
            </div>
            
            <div class="form-group">
                <select name="payment_status">
                    <option value="pending" ${paymentStatus === 'pending' ? 'selected' : ''}>⏳ Wachtend</option>
                    <option value="paid" ${paymentStatus === 'paid' ? 'selected' : ''}>✅ Betaald</option>
                    <option value="cancelled" ${paymentStatus === 'cancelled' ? 'selected' : ''}>❌ Geannuleerd</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <input type="text" name="notes" placeholder="Eventuele opmerkingen..." 
                   value="${existingCourse ? (existingCourse.notes || '') : ''}">
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
    const container = document.getElementById('courseAssignments');
    const assignmentDivs = container.querySelectorAll('[id^="assignment-"]');
    
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
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="assign_courses">
        <input type="hidden" name="user_id" value="${currentAssignUserId}">
        <input type="hidden" name="courses" value='${JSON.stringify(assignments)}'>
    `;
    document.body.appendChild(form);
    form.submit();
}

// Bulk import placeholder
function showBulkImportModal() {
    showNotification('📋 Bulk import functionaliteit komt binnenkort! Deze feature zal CSV import ondersteunen voor grote aantallen gebruikers.', 'info');
}

// View user details function
function viewUserDetails(userId) {
    // For now, redirect to edit modal with read-only view
    editUser(userId);
    
    // Future enhancement: Create dedicated details modal
    showNotification('Gebruiker details geladen in bewerk modus', 'info');
}

// Reactivate user function
function reactivateUser(userId) {
    if (!confirm('Weet je zeker dat je deze gebruiker wilt reactiveren?')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" value="${userId}">
        <input type="hidden" name="active" value="1">
        <input type="hidden" name="quick_update" value="1">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Test AJAX connection
function testAjax() {
    fetchData('?ajax=1&action=test')
        .then(response => {
            if (response.status === 'success') {
                console.log('✅ AJAX connection working:', response.message);
            }
        })
        .catch(error => {
            console.error('❌ AJAX test failed:', error);
        });
}
</script>

<style>
/* Rich User Card Layout - Inspired by courses.php */
.users-grid {
    display: grid;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.user-item {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.user-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.user-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.user-main-info {
    flex: 1;
}

.user-title {
    color: var(--primary);
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    line-height: 1.2;
}

.user-subtitle {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.4;
}

.user-status-badges {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-badge.new-user {
    background: linear-gradient(135deg, var(--warning), #f59e0b);
    color: white;
    animation: pulse 2s infinite;
}

.status-badge.premium {
    background: linear-gradient(135deg, var(--inventijn-accent), var(--inventijn-purple));
    color: white;
}

.status-badge.active {
    background: var(--success-light);
    color: var(--success);
}

.status-badge.inactive {
    background: var(--danger-light);
    color: var(--danger);
}

.activity-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.activity-badge.active {
    background: #d1fae5;
    color: #065f46;
}

.activity-badge.engaged {
    background: #dbeafe;
    color: #1e40af;
}

.activity-badge.quiet {
    background: #f3f4f6;
    color: #6b7280;
}

.user-essentials {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
    background: var(--neutral-light);
    border-bottom: 1px solid var(--border);
}

.essential-item {
    text-align: center;
}

.essential-label {
    color: var(--text-muted);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
    font-weight: 600;
}

.essential-value {
    color: var(--primary);
    font-weight: 600;
    font-size: 0.9rem;
    line-height: 1.3;
}

.essential-value small {
    display: block;
    color: var(--text-muted);
    font-size: 0.75rem;
    font-weight: 400;
    margin-top: 0.25rem;
}

.courses-preview {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.courses-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    color: var(--primary);
    font-weight: 600;
}

.participation-summary {
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
}

.course-participation {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.course-chip {
    background: var(--neutral-light);
    color: var(--primary);
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    border: 1px solid var(--border);
}

.course-chip.more {
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 600;
}

.course-name {
    display: block;
}

.no-courses {
    color: var(--text-muted);
    font-style: italic;
    padding: 1rem;
    text-align: center;
    background: var(--neutral-light);
    border-radius: 8px;
}

.payment-overview {
    display: flex;
    gap: 2rem;
    justify-content: center;
}

.payment-stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.payment-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.payment-count {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.875rem;
    min-width: 24px;
    text-align: center;
}

.payment-count.paid {
    background: var(--success-light);
    color: var(--success);
}

.payment-count.pending {
    background: var(--warning-light);
    color: var(--warning);
}

.no-participation {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.no-participation i {
    font-size: 2rem;
    margin-bottom: 1rem;
    display: block;
    opacity: 0.5;
}

.user-notes {
    padding: 1.5rem;
    background: #fef3c7;
    border-left: 4px solid var(--warning);
}

.notes-header {
    color: var(--warning);
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.notes-content {
    color: #92400e;
    font-size: 0.9rem;
    line-height: 1.5;
}

.user-actions {
    padding: 1.5rem;
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: center;
}

.user-actions .btn {
    flex: 1;
    min-width: 120px;
    text-align: center;
}

/* Assignment Modal Styles */
.assignment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.assignment-header label {
    font-weight: 600;
    color: var(--primary);
}

.info-box {
    background: var(--neutral-light);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.info-header .info-title {
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.info-header .info-subtitle {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .user-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .user-status-badges {
        align-items: flex-start;
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .user-essentials {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .course-participation {
        justify-content: center;
    }
    
    .user-actions {
        flex-direction: column;
    }
    
    .user-actions .btn {
        min-width: auto;
    }
}

/* Animation */
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.9; }
}
</style>

<?php
require_once 'admin_modals.php';
require_once 'admin_footer.php';
?>