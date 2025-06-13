<?php
/**
 * Inventijn User Management Systeem v6.4.0
 * Gebruikers beheer, cursus toekenning en betalingen
 * Converted to unified admin system
 * Updated: 2025-06-13
 * Changes: 
 * v6.4.0 - Converted to unified admin design system
 * v6.4.0 - FIXED: Event parameter issue in saveCourseAssignments
 * v6.4.0 - FIXED: All modal and JavaScript function calls
 * v6.4.0 - Added defensive programming for missing functions
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
                if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                    $stmt = $pdo->prepare("
                        SELECT cp.*, c.name as course_name, c.course_date, c.price
                        FROM course_participants cp
                        JOIN courses c ON cp.course_id = c.id
                        WHERE cp.user_id = ? AND c.active = 1
                        ORDER BY c.course_date ASC
                    ");
                    $stmt->execute([$_GET['user_id']]);
                    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode(['success' => true, 'courses' => $courses]);
                } else {
                    echo json_encode(['error' => 'Invalid user ID parameter']);
                }
                break;
                
            case 'regenerate_access_key':
                if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                    $newAccessKey = bin2hex(random_bytes(16));
                    $stmt = $pdo->prepare("UPDATE users SET access_key = ? WHERE id = ?");
                    $success = $stmt->execute([$newAccessKey, $_GET['user_id']]);
                    
                    if ($success) {
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Nieuwe toegangscode gegenereerd!',
                            'access_key' => $newAccessKey
                        ]);
                    } else {
                        echo json_encode(['error' => 'Fout bij genereren nieuwe code']);
                    }
                } else {
                    echo json_encode(['error' => 'Invalid user ID parameter']);
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

// Include dependencies
require_once '../includes/config.php';

try {
    $pdo = getDatabase();
} catch (Exception $e) {
    die('Database verbinding mislukt: ' . $e->getMessage());
}

$message = '';
$error = '';

// Handle POST form submissions (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_user':
                // Generate unique access key
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
                    $message = "Gebruiker aangemaakt! Toegangscode: " . $accessKey;
                } else {
                    $error = "Fout bij aanmaken gebruiker";
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
                
                $message = $success ? 'Gebruiker bijgewerkt!' : 'Fout bij bijwerken';
                break;
                
            case 'delete_user':
                $stmt = $pdo->prepare("UPDATE users SET active = 0 WHERE id = ?");
                $success = $stmt->execute([$_POST['user_id']]);
                $message = $success ? 'Gebruiker gedeactiveerd!' : 'Fout bij deactiveren';
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
                
                $message = "Cursustoekenningen bijgewerkt! ($successCount cursussen toegekend)";
                break;
                
            case 'regenerate_access_key':
                $newAccessKey = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("UPDATE users SET access_key = ? WHERE id = ?");
                $success = $stmt->execute([$newAccessKey, $_POST['user_id']]);
                
                $message = $success ? "Nieuwe toegangscode: $newAccessKey" : 'Fout bij genereren';
                break;
        }
    } catch (Exception $e) {
        $error = 'Database fout: ' . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
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

// Get statistics
$stats = [
    'total_users' => $totalUsers,
    'active_users' => count(array_filter($users, fn($u) => $u['active'])),
    'avg_courses' => $totalUsers > 0 ? round(array_sum(array_column($users, 'course_count')) / $totalUsers, 1) : 0,
    'paid_enrollments' => array_sum(array_column($users, 'paid_courses'))
];
?>

<!-- Page Header Card -->
<div class="card">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-users"></i> Gebruikersbeheer</h3>
        </div>
        <div style="display: flex; gap: var(--space-2);">
            <button onclick="openUserModal()" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nieuwe Gebruiker
            </button>
            <button class="btn btn-secondary" onclick="showBulkImportModal()">
                <i class="fas fa-upload"></i> Bulk Import
            </button>
        </div>
    </div>
    <div class="course-essentials">
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-users"></i> Totaal Gebruikers</div>
            <div class="essential-value"><?= number_format($stats['total_users']) ?></div>
        </div>
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-check-circle"></i> Actieve Gebruikers</div>
            <div class="essential-value"><?= number_format($stats['active_users']) ?></div>
        </div>
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-book"></i> Gem. Cursussen/User</div>
            <div class="essential-value"><?= $stats['avg_courses'] ?></div>
        </div>
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-euro-sign"></i> Betaalde Inschrijvingen</div>
            <div class="essential-value"><?= number_format($stats['paid_enrollments']) ?></div>
        </div>
    </div>
</div>

<!-- Messages -->
<?php if ($message): ?>
    <div class="message success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Search and Filter Toolbar -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div style="display: flex; gap: var(--space-4); align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 250px;">
            <input type="text" 
                   placeholder="🔍 Zoek op naam, email of bedrijf..." 
                   value="<?= htmlspecialchars($search) ?>"
                   onkeyup="if(event.key==='Enter') applyFilters()"
                   style="width: 100%; padding: var(--space-3); border: 1px solid var(--border); border-radius: var(--radius-sm);">
        </div>
        
        <select onchange="applyFilters()" style="padding: var(--space-3); border: 1px solid var(--border); border-radius: var(--radius-sm);">
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Alle gebruikers</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actieve gebruikers</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactieve gebruikers</option>
        </select>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>Geen gebruikers gevonden met de huidige filters.</p>
            <button class="btn btn-primary" onclick="openUserModal()">
                <i class="fas fa-plus"></i> Eerste Gebruiker
            </button>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--surface-hover); border-bottom: 2px solid var(--border);">
                    <th style="padding: var(--space-4); text-align: left; font-weight: 600; color: var(--text-secondary);">Gebruiker</th>
                    <th style="padding: var(--space-4); text-align: left; font-weight: 600; color: var(--text-secondary);">Contact</th>
                    <th style="padding: var(--space-4); text-align: left; font-weight: 600; color: var(--text-secondary);">Cursussen</th>
                    <th style="padding: var(--space-4); text-align: left; font-weight: 600; color: var(--text-secondary);">Status</th>
                    <th style="padding: var(--space-4); text-align: left; font-weight: 600; color: var(--text-secondary);">Aangemaakt</th>
                    <th style="padding: var(--space-4); text-align: left; font-weight: 600; color: var(--text-secondary);">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr style="border-bottom: 1px solid var(--border); transition: background 0.2s;"
                    onmouseover="this.style.background='var(--surface-hover)'"
                    onmouseout="this.style.background=''">
                    <td style="padding: var(--space-4);">
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-1);">
                            <?= htmlspecialchars($user['name']) ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: var(--font-size-sm);">
                            <?= htmlspecialchars($user['email']) ?>
                        </div>
                        <?php if ($user['company']): ?>
                            <div style="color: var(--text-tertiary); font-size: var(--font-size-xs); font-style: italic;">
                                <?= htmlspecialchars($user['company']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: var(--space-4);">
                        <?php if ($user['phone']): ?>
                            <div style="margin-bottom: var(--space-1);">
                                <i class="fas fa-phone" style="width: 16px;"></i> <?= htmlspecialchars($user['phone']) ?>
                            </div>
                        <?php endif; ?>
                        <div style="font-size: var(--font-size-xs); color: var(--text-tertiary); font-family: monospace;">
                            <i class="fas fa-key" style="width: 16px;"></i> <?= substr($user['access_key'], 0, 8) ?>...
                        </div>
                    </td>
                    <td style="padding: var(--space-4);">
                        <?php if ($user['course_count'] > 0): ?>
                            <div style="margin-bottom: var(--space-2);">
                                <span style="background: var(--border-light); color: var(--text-primary); padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs); margin-right: var(--space-1);">
                                    <i class="fas fa-book"></i> <?= $user['course_count'] ?> cursussen
                                </span>
                                <?php if ($user['paid_courses'] > 0): ?>
                                    <span style="background: #d1fae5; color: #065f46; padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs);">
                                        <i class="fas fa-euro-sign"></i> <?= $user['paid_courses'] ?> betaald
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($user['course_names']): ?>
                                <div style="font-size: var(--font-size-xs); color: var(--text-secondary);">
                                    <?= htmlspecialchars(strlen($user['course_names']) > 50 ? substr($user['course_names'], 0, 50) . '...' : $user['course_names']) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: var(--text-tertiary); font-style: italic;">Geen cursussen</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: var(--space-4);">
                        <span style="padding: var(--space-1) var(--space-3); border-radius: 12px; font-size: var(--font-size-xs); font-weight: 600; 
                                     background: <?= $user['active'] ? '#d1fae5' : '#fee2e2' ?>; 
                                     color: <?= $user['active'] ? '#065f46' : '#991b1b' ?>;">
                            <?= $user['active'] ? '✅ Actief' : '❌ Inactief' ?>
                        </span>
                    </td>
                    <td style="padding: var(--space-4); color: var(--text-secondary); font-size: var(--font-size-sm);">
                        <?= date('d-m-Y', strtotime($user['created_at'])) ?>
                    </td>
                    <td style="padding: var(--space-4);">
                        <div class="btn-group">
                            <button onclick="editUser(<?= $user['id'] ?>)" class="btn btn-sm btn-primary" title="Bewerken">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="assignCourses(<?= $user['id'] ?>)" class="btn btn-sm btn-secondary" title="Cursussen">
                                <i class="fas fa-book"></i>
                            </button>
                            <button onclick="safeConfirmDelete('<?= htmlspecialchars($user['name']) ?>', () => deleteUser(<?= $user['id'] ?>))" 
                                    class="btn btn-sm btn-danger" title="Deactiveren">
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
<div style="display: flex; justify-content: center; align-items: center; gap: var(--space-2); margin-top: var(--space-6);">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>" 
           class="btn btn-outline">‹ Vorige</a>
    <?php endif; ?>
    
    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
            <span class="btn btn-primary"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>" 
               class="btn btn-outline"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>" 
           class="btn btn-outline">Volgende ›</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="userModalTitle">Nieuwe Gebruiker</h3>
            <button class="modal-close" onclick="safeCloseModal('userModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="userForm">
                <input type="hidden" name="action" value="create_user" id="userAction">
                <input type="hidden" name="user_id" id="userId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Volledige Naam *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mailadres *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="phone">Telefoonnummer</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="company">Bedrijf</label>
                        <input type="text" id="company" name="company">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="notes">Notities</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Eventuele opmerkingen..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="active" name="active" value="1" checked>
                        Gebruiker is actief
                    </label>
                </div>
                
                <!-- Access Key Management (only visible when editing) -->
                <div id="accessKeySection" style="display: none; background: var(--surface-hover); padding: var(--space-4); border-radius: var(--radius-sm); margin: var(--space-4) 0; border: 1px solid var(--border);">
                    <div style="font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--space-2); font-weight: 600;">
                        <i class="fas fa-key"></i> Toegangscode Beheer
                    </div>
                    <div style="font-family: monospace; font-size: var(--font-size-sm); color: var(--text-primary); margin-bottom: var(--space-3); padding: var(--space-2); background: var(--surface); border-radius: var(--radius-sm); border: 1px solid var(--border);" id="current_access_key">
                        <!-- Filled by JavaScript -->
                    </div>
                    <button type="button" class="btn btn-sm btn-warning" onclick="regenerateAccessKey()" style="margin-top: var(--space-2);">
                        <i class="fas fa-sync-alt"></i> Nieuwe Code Genereren
                    </button>
                    <div style="font-size: var(--font-size-xs); color: var(--text-tertiary); margin-top: var(--space-2);">
                        ⚠️ Let op: De oude toegangscode werkt dan niet meer
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span id="userModalSubmitText">Gebruiker Aanmaken</span>
                    </button>
                    <button type="button" onclick="safeCloseModal('userModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuleren
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Course Assignment Modal -->
<div id="courseAssignmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-book"></i> Cursussen Toekennen</h3>
            <button class="modal-close" onclick="safeCloseModal('courseAssignmentModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="user_course_info" style="background: var(--surface-hover); padding: var(--space-4); border-radius: var(--radius-sm); margin-bottom: var(--space-4);">
                <!-- Filled by JavaScript -->
            </div>
            
            <div id="course_assignments">
                <!-- Course assignments will be added here by JavaScript -->
            </div>
            
            <div style="margin: var(--space-4) 0; padding-top: var(--space-4); border-top: 1px solid var(--border);">
                <button type="button" class="btn btn-secondary" onclick="addCourseAssignment()">
                    <i class="fas fa-plus"></i> Cursus Toevoegen
                </button>
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn btn-primary" onclick="saveCourseAssignments(event)">
                    <i class="fas fa-save"></i> Cursussen Opslaan
                </button>
                <button type="button" class="btn btn-secondary" onclick="safeCloseModal('courseAssignmentModal')">
                    <i class="fas fa-times"></i> Annuleren
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentEditUserId = null;
let currentAssignUserId = null;
let courseAssignmentCounter = 0;

// All data (for JavaScript operations)
const usersData = <?= json_encode($users) ?>;
const allCoursesData = <?= json_encode($allCourses) ?>;

// Safe loading modal functions
function safeOpenLoadingModal() {
    if (typeof openLoadingModal !== 'undefined') {
        openLoadingModal();
    } else {
        console.log('ℹ️ Loading modal not available, continuing...');
    }
}

function safeCloseLoadingModal() {
    if (typeof closeLoadingModal !== 'undefined') {
        closeLoadingModal();
    } else {
        console.log('ℹ️ Loading modal not available, continuing...');
    }
}

// Safe modal functions
function safeOpenModal(modalId) {
    if (typeof openModal !== 'undefined') {
        openModal(modalId);
    } else {
        // Fallback: show modal manually
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
}

function safeCloseModal(modalId) {
    if (typeof closeModal !== 'undefined') {
        closeModal(modalId);
    } else {
        // Fallback: hide modal manually
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
}

// Safe notification function
function safeShowNotification(message, type = 'info') {
    if (typeof showNotification !== 'undefined') {
        showNotification(message, type);
    } else {
        // Fallback to alert
        const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
        alert((icons[type] || 'ℹ️') + ' ' + message);
    }
}

// Debug function to check if all required functions exist
function checkRequiredFunctions() {
    const requiredFunctions = [
        'openModal', 'closeModal', 'showNotification', 'fetchData',
        'setButtonLoading', 'fillFormFromData', 'resetForm', 'confirmDelete'
    ];
    
    const missing = requiredFunctions.filter(fn => typeof window[fn] === 'undefined');
    
    if (missing.length > 0) {
        console.error('❌ Missing functions:', missing);
        console.log('💡 This usually means admin_footer.php is not loaded correctly');
        return false;
    } else {
        console.log('✅ All required functions are available');
        return true;
    }
}

// AJAX testing function
function testAjax() {
    if (typeof fetchData !== 'undefined') {
        fetchData('?ajax=1&action=test')
            .then(result => {
                console.log('✅ AJAX Test Result:', result);
                safeShowNotification('AJAX verbinding werkt!', 'success');
            })
            .catch(error => {
                console.error('❌ AJAX Test Failed:', error);
                safeShowNotification('AJAX fout: ' + error.message, 'error');
            });
    } else {
        console.error('❌ fetchData function not available');
        alert('❌ AJAX functions not loaded');
    }
}

// Safe modal opener
function openUserModal() {
    resetUserForm();
    safeOpenModal('userModal');
}

// Reset user form function  
function resetUserForm() {
    if (typeof resetForm !== 'undefined') {
        resetForm('userForm');
    } else {
        // Manual reset
        const form = document.getElementById('userForm');
        if (form) form.reset();
    }
    
    document.getElementById('userAction').value = 'create_user';
    document.getElementById('userId').value = '';
    document.getElementById('userModalTitle').textContent = 'Nieuwe Gebruiker';
    document.getElementById('userModalSubmitText').textContent = 'Gebruiker Aanmaken';
    
    // Hide access key section for new users
    document.getElementById('accessKeySection').style.display = 'none';
}

// Edit user function
async function editUser(userId) {
    if (!checkRequiredFunctions()) {
        alert('❌ System functions not loaded. Please refresh the page.');
        return;
    }
    
    try {
        safeOpenLoadingModal();
        
        const response = await fetchData(`?ajax=1&action=get_user&id=${userId}`);
        
        safeCloseLoadingModal();
        
        if (response.error) {
            throw new Error(response.error);
        }
        
        // Fill form
        if (typeof fillFormFromData !== 'undefined') {
            fillFormFromData('userForm', response);
        } else {
            // Manual form filling
            document.getElementById('name').value = response.name || '';
            document.getElementById('email').value = response.email || '';
            document.getElementById('phone').value = response.phone || '';
            document.getElementById('company').value = response.company || '';
            document.getElementById('notes').value = response.notes || '';
        }
        
        // Update form action and modal title  
        document.getElementById('userAction').value = 'update_user';
        document.getElementById('userId').value = response.id;
        document.getElementById('userModalTitle').textContent = 'Gebruiker Bewerken';
        document.getElementById('userModalSubmitText').textContent = 'Gebruiker Bijwerken';
        
        // Set active checkbox
        document.getElementById('active').checked = response.active == '1';
        
        // Show access key section and fill access key
        document.getElementById('accessKeySection').style.display = 'block';
        const accessKey = response.access_key || 'Geen toegangscode';
        const formattedKey = accessKey.length > 10 ? accessKey.match(/.{1,8}/g).join('-') : accessKey;
        document.getElementById('current_access_key').innerHTML = `
            <div style="font-weight: 600; margin-bottom: var(--space-1);">Huidige Toegangscode:</div>
            <div style="color: var(--primary); font-size: var(--font-size-lg);">${formattedKey}</div>
            <div style="font-size: var(--font-size-xs); color: var(--text-tertiary); margin-top: var(--space-1);">
                Aangemaakt: ${new Date(response.created_at || '').toLocaleDateString('nl-NL') || 'Onbekend'}
            </div>
        `;
        
        // Store current user ID for access key regeneration
        currentEditUserId = userId;
        
        // Open modal
        safeOpenModal('userModal');
        safeShowNotification('Gebruiker geladen!', 'success');
        
    } catch (error) {
        safeCloseLoadingModal();
        console.error('Error loading user:', error);
        safeShowNotification('Fout bij laden: ' + error.message, 'error');
    }
}

// Course assignment functions
async function assignCourses(userId) {
    if (!checkRequiredFunctions()) {
        alert('❌ System functions not loaded. Please refresh the page.');
        return;
    }
    
    const user = usersData.find(u => u.id == userId);
    if (!user) {
        safeShowNotification('Gebruiker niet gevonden', 'error');
        return;
    }
    
    currentAssignUserId = userId;
    courseAssignmentCounter = 0;
    
    // Set user info
    document.getElementById('user_course_info').innerHTML = `
        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-1);">
            <i class="fas fa-user"></i> ${user.name}
        </div>
        <div style="font-size: var(--font-size-sm); color: var(--text-secondary);">
            <i class="fas fa-envelope"></i> ${user.email} | 
            <i class="fas fa-book"></i> ${user.course_count} cursussen | 
            <i class="fas fa-euro-sign"></i> ${user.paid_courses} betaald
        </div>
    `;
    
    // Get current course assignments
    try {
        safeOpenLoadingModal();
        
        const result = await fetchData(`?ajax=1&action=get_user_courses&user_id=${userId}`);
        
        safeCloseLoadingModal();
        
        if (result.success) {
            // Clear assignments
            document.getElementById('course_assignments').innerHTML = '';
            
            // Add existing assignments
            result.courses.forEach(course => {
                addCourseAssignment(course);
            });
            
            // Show modal
            safeOpenModal('courseAssignmentModal');
            safeShowNotification('Cursussen geladen!', 'success');
        } else {
            safeShowNotification('Fout bij laden: ' + (result.error || 'Onbekende fout'), 'error');
        }
    } catch (error) {
        safeCloseLoadingModal();
        console.error('Error loading courses:', error);
        safeShowNotification('Fout bij laden: ' + error.message, 'error');
    }
}

// Add course assignment row
function addCourseAssignment(existingCourse = null) {
    const id = ++courseAssignmentCounter;
    const container = document.getElementById('course_assignments');
    
    const assignmentDiv = document.createElement('div');
    assignmentDiv.className = 'card';
    assignmentDiv.id = `assignment-${id}`;
    assignmentDiv.style.marginBottom = 'var(--space-4)';
    assignmentDiv.style.borderLeft = '3px solid var(--primary)';
    
    let courseOptions = '<option value="">-- Selecteer Cursus --</option>';
    allCoursesData.forEach(course => {
        const selected = existingCourse && existingCourse.course_id == course.id ? 'selected' : '';
        const courseInfo = `${course.name} (${new Date(course.course_date).toLocaleDateString('nl-NL')})`;
        courseOptions += `<option value="${course.id}" ${selected}>${courseInfo}</option>`;
    });
    
    const paymentStatus = existingCourse ? existingCourse.payment_status : 'pending';
    
    assignmentDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
            <div style="font-weight: 600; color: var(--text-primary);">
                <i class="fas fa-book"></i> Cursus Toekenning #${id}
            </div>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeCourseAssignment(${id})">
                <i class="fas fa-trash"></i> Verwijderen
            </button>
        </div>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
            <div class="form-group">
                <label>Cursus:</label>
                <select name="course_id">${courseOptions}</select>
            </div>
            
            <div class="form-group">
                <label>Betaalstatus:</label>
                <select name="payment_status">
                    <option value="pending" ${paymentStatus === 'pending' ? 'selected' : ''}>⏳ Wachtend</option>
                    <option value="paid" ${paymentStatus === 'paid' ? 'selected' : ''}>✅ Betaald</option>
                    <option value="cancelled" ${paymentStatus === 'cancelled' ? 'selected' : ''}>❌ Geannuleerd</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Notities:</label>
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

// FIXED: Save course assignments with proper event handling
async function saveCourseAssignments(event = null) {
    // Find the button that was clicked
    const saveButton = event ? event.target : document.querySelector('#courseAssignmentModal .btn-primary');
    
    const assignments = [];
    const container = document.getElementById('course_assignments');
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
    
    const formData = new FormData();
    formData.append('action', 'assign_courses');
    formData.append('user_id', currentAssignUserId);
    formData.append('courses', JSON.stringify(assignments));
    
    try {
        // Safe button loading
        if (saveButton && typeof setButtonLoading !== 'undefined') {
            setButtonLoading(saveButton, true);
        }
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        // Reset button loading
        if (saveButton && typeof setButtonLoading !== 'undefined') {
            setButtonLoading(saveButton, false);
        }
        
        if (response.ok) {
            safeShowNotification('Cursussen opgeslagen!', 'success');
            safeCloseModal('courseAssignmentModal');
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error('Server error: ' + response.status);
        }
    } catch (error) {
        // Reset button loading on error
        if (saveButton && typeof setButtonLoading !== 'undefined') {
            setButtonLoading(saveButton, false);
        }
        console.error('Error saving courses:', error);
        safeShowNotification('Fout bij opslaan: ' + error.message, 'error');
    }
}

// Safe confirm delete function
function safeConfirmDelete(itemName, callback) {
    if (typeof confirmDelete !== 'undefined') {
        confirmDelete(itemName, callback);
    } else {
        // Fallback to standard confirm
        if (confirm(`Weet je zeker dat je "${itemName}" wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.`)) {
            callback();
        }
    }
}

// Regenerate access key function
async function regenerateAccessKey() {
    if (!currentEditUserId) {
        safeShowNotification('Geen gebruiker geselecteerd', 'error');
        return;
    }
    
    if (!confirm('🔄 Weet je zeker dat je een nieuwe toegangscode wilt genereren?\n\nDe oude code werkt dan niet meer.')) {
        return;
    }
    
    try {
        let result;
        
        if (typeof fetchData !== 'undefined') {
            // Use unified system
            result = await fetchData(`?ajax=1&action=regenerate_access_key&user_id=${currentEditUserId}`);
        } else {
            // Fallback fetch
            const response = await fetch(`?ajax=1&action=regenerate_access_key&user_id=${currentEditUserId}`);
            result = await response.json();
        }
        
        if (result.success) {
            // Format the access key nicely
            const formattedKey = result.access_key.match(/.{1,8}/g).join('-');
            document.getElementById('current_access_key').innerHTML = `
                <div style="font-weight: 600; margin-bottom: var(--space-1);">Huidige Toegangscode:</div>
                <div style="color: var(--primary); font-size: var(--font-size-lg);">${formattedKey}</div>
                <div style="font-size: var(--font-size-xs); color: var(--text-tertiary); margin-top: var(--space-1);">
                    Gegenereerd: ${new Date().toLocaleString('nl-NL')}
                </div>
            `;
            safeShowNotification(result.message, 'success');
        } else {
            throw new Error(result.error || 'Onbekende fout');
        }
    } catch (error) {
        console.error('Error regenerating access key:', error);
        safeShowNotification('Fout bij genereren nieuwe code: ' + error.message, 'error');
    }
}

// Delete user function
async function deleteUser(userId) {
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', userId);
    
    try {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            safeShowNotification('Gebruiker gedeactiveerd!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error('Server error: ' + response.status);
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        safeShowNotification('Fout bij verwijderen: ' + error.message, 'error');
    }
}

// Search and filter functions
function applyFilters() {
    const search = document.querySelector('input[placeholder*="Zoek"]').value;
    const status = document.querySelector('select').value;
    
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('status', status);
    url.searchParams.set('page', '1'); // Reset to first page
    
    window.location.href = url.toString();
}

// Bulk import placeholder
function showBulkImportModal() {
    safeShowNotification('Bulk import functionaliteit komt binnenkort!', 'info', 4000);
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Check if all functions are available
    checkRequiredFunctions();
    
    // Test AJAX connection on load (for debugging)  
    console.log('🎯 Users page loaded. Test AJAX with: testAjax()');
    
    // Check if admin_footer.php loaded correctly
    setTimeout(() => {
        if (typeof openModal === 'undefined') {
            console.error('❌ admin_footer.php not loaded correctly');
            console.log('💡 Check if admin_footer.php exists and is included properly');
        }
    }, 100);
});
</script>

<?php
require_once 'admin_footer.php';
?>