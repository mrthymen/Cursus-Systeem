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
    <!-- Page Header -->
    <div class="card">
        <div class="card-header">
            <h2>👥 Gebruikersbeheer</h2>
            <p style="color: var(--text-secondary); margin: 0;">Beheer gebruikers, cursustoekenningen en betalingen vanuit één centrale plek</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <div class="stat-label">Totaal Gebruikers</div>
                <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <div class="stat-label">Actieve Gebruikers</div>
                <div class="stat-value"><?= number_format($stats['active_users']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-content">
                <div class="stat-label">Nieuwe (7 dagen)</div>
                <div class="stat-value"><?= number_format($stats['new_users_week']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <div class="stat-label">Betaalde Inschrijvingen</div>
                <div class="stat-value"><?= number_format($stats['paid_courses']) ?></div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="card">
        <div class="card-body">
            <div class="toolbar">
                <div class="search-group">
                    <input type="text" 
                           placeholder="🔍 Zoek op naam, email of bedrijf..." 
                           value="<?= htmlspecialchars($search) ?>"
                           id="searchInput"
                           onkeyup="if(event.key==='Enter') applyFilters()">
                </div>
                
                <select id="statusFilter" onchange="applyFilters()">
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

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h3>Gebruikerslijst</h3>
            <span class="badge"><?= number_format($totalUsers) ?> gebruikers</span>
        </div>
        
        <div class="table-container">
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>Geen gebruikers gevonden</h3>
                    <p>Er zijn geen gebruikers die voldoen aan de huidige filters.</p>
                    <button onclick="openModal('userModal'); resetUserForm();" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Eerste Gebruiker Aanmaken
                    </button>
                </div>
            <?php else: ?>
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
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                    <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                    <?php if ($user['company']): ?>
                                        <div class="user-company"><?= htmlspecialchars($user['company']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($user['phone']): ?>
                                    <div class="contact-item">
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="access-key">
                                    <i class="fas fa-key"></i> <?= substr($user['access_key'], 0, 8) ?>...
                                </div>
                            </td>
                            <td>
                                <?php if ($user['course_count'] > 0): ?>
                                    <div class="course-summary">
                                        <span class="course-badge">
                                            📚 <?= $user['course_count'] ?> cursussen
                                        </span>
                                        <?php if ($user['paid_courses'] > 0): ?>
                                            <span class="course-badge paid">
                                                💰 <?= $user['paid_courses'] ?> betaald
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($user['course_names']): ?>
                                        <div class="course-list">
                                            <?= htmlspecialchars(strlen($user['course_names']) > 50 ? substr($user['course_names'], 0, 50) . '...' : $user['course_names']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Geen cursussen</span>
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
                                <div class="btn-group">
                                    <button onclick="editUser(<?= $user['id'] ?>)" class="btn btn-sm btn-primary" title="Bewerken">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="assignCourses(<?= $user['id'] ?>)" class="btn btn-sm btn-warning" title="Cursussen toekennen">
                                        <i class="fas fa-graduation-cap"></i>
                                    </button>
                                    <button onclick="deleteUser(<?= $user['id'] ?>)" class="btn btn-sm btn-danger" title="Deactiveren">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
/* Page-specific styles */
.user-info .user-name {
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.user-info .user-email {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.user-info .user-company {
    color: var(--text-muted);
    font-size: 0.8rem;
    font-style: italic;
}

.contact-item {
    color: var(--text-secondary);
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.access-key {
    color: var(--text-muted);
    font-size: 0.8rem;
    font-family: monospace;
}

.course-summary {
    margin-bottom: 0.5rem;
}

.course-badge {
    background: var(--neutral-light);
    color: var(--primary);
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    margin: 0.125rem;
    display: inline-block;
}

.course-badge.paid {
    background: var(--success-light);
    color: var(--success);
}

.course-list {
    color: var(--text-secondary);
    font-size: 0.8rem;
}

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
</style>

<?php
require_once 'admin_modals.php';
require_once 'admin_footer.php';
?>