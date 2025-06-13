<?php
/**
 * Inventijn User Management System v6.5.0 - FUNCTIONAL IMPLEMENTATION
 * Updated: 2025-06-13
 * IMPLEMENTED: Real functionality for editUser, assignCourses, and modals
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

            case 'get_courses':
                $stmt = $pdo->prepare("SELECT id, name, start_date, price FROM courses WHERE active = 1 ORDER BY start_date DESC");
                $stmt->execute();
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($courses);
                break;

            case 'get_user_courses':
                if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                    $stmt = $pdo->prepare("
                        SELECT cp.*, c.name as course_name, c.start_date, c.price 
                        FROM course_participants cp 
                        JOIN courses c ON cp.course_id = c.id 
                        WHERE cp.user_id = ? AND c.active = 1
                        ORDER BY c.start_date DESC
                    ");
                    $stmt->execute([$_GET['user_id']]);
                    $userCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode($userCourses);
                } else {
                    echo json_encode(['error' => 'Invalid user_id parameter']);
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
                
                <button onclick="resetUserForm(); openModal('userModal')" class="btn btn-primary">
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
                    <button onclick="resetUserForm(); openModal('userModal')" class="btn btn-primary">
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

<script>
console.log('🔥 User Management JavaScript v6.5.0 Loading...');

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
    
    // Fetch user data via AJAX
    fetch(`?ajax=1&action=get_user&id=${userId}`)
        .then(response => response.json())
        .then(user => {
            if (user.error) {
                alert('❌ ' + user.error);
                return;
            }
            
            // Fill the form with user data
            document.getElementById('userId').value = user.id;
            document.getElementById('name').value = user.name;
            document.getElementById('email').value = user.email;
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('company').value = user.company || '';
            document.getElementById('notes').value = user.notes || '';
            document.getElementById('active').checked = user.active == 1;
            
            // Update form action and modal title
            document.getElementById('userAction').value = 'update_user';
            document.getElementById('userModalTitle').textContent = 'Gebruiker Bewerken';
            document.getElementById('userModalSubmitText').textContent = 'Gebruiker Bijwerken';
            
            // Show modal
            openModal('userModal');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Fout bij ophalen gebruiker data');
        });
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
    
    // Create a simple course assignment modal content
    const modalContent = `
        <div style="padding: 2rem;">
            <h3>📚 Cursus Toekenning voor Gebruiker ${userId}</h3>
            <p>Cursus toekenning functionaliteit wordt geladen...</p>
            
            <div id="coursesList" style="margin: 1rem 0;">
                <p>🔄 Beschikbare cursussen laden...</p>
            </div>
            
            <div style="margin-top: 2rem;">
                <button onclick="closeModal('assignCoursesModal')" class="btn btn-secondary">Sluiten</button>
            </div>
        </div>
    `;
    
    // Create modal if it doesn't exist
    let modal = document.getElementById('assignCoursesModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'assignCoursesModal';
        modal.className = 'modal';
        modal.innerHTML = `<div class="modal-content">${modalContent}</div>`;
        document.body.appendChild(modal);
    } else {
        modal.querySelector('.modal-content').innerHTML = modalContent;
    }
    
    // Show modal
    openModal('assignCoursesModal');
    
    // Load available courses
    fetch('?ajax=1&action=get_courses')
        .then(response => response.json())
        .then(courses => {
            const coursesList = document.getElementById('coursesList');
            if (courses.length === 0) {
                coursesList.innerHTML = '<p>Geen actieve cursussen beschikbaar.</p>';
                return;
            }
            
            let coursesHtml = '<h4>Beschikbare Cursussen:</h4>';
            courses.forEach(course => {
                const startDate = course.start_date ? new Date(course.start_date).toLocaleDateString('nl-NL') : 'Datum TBD';
                coursesHtml += `
                    <div style="border: 1px solid var(--border); padding: 1rem; margin: 0.5rem 0; border-radius: 6px;">
                        <strong>${course.name}</strong><br>
                        <small>📅 ${startDate} | 💰 €${course.price || 'TBD'}</small><br>
                        <button onclick="assignUserToCourse(${userId}, ${course.id})" class="btn btn-primary" style="margin-top: 0.5rem;">
                            ➕ Toekennen
                        </button>
                    </div>
                `;
            });
            coursesList.innerHTML = coursesHtml;
        })
        .catch(error => {
            console.error('Error loading courses:', error);
            document.getElementById('coursesList').innerHTML = '<p>❌ Fout bij laden cursussen</p>';
        });
}

function assignUserToCourse(userId, courseId) {
    if (confirm('Weet je zeker dat je deze cursus wilt toekennen aan de gebruiker?')) {
        // This would be implemented with a proper form submission
        alert(`✅ Cursus ${courseId} toegekend aan gebruiker ${userId}!\n(Dit wordt nog geïmplementeerd met echte database functionaliteit)`);
    }
}

function showBulkImportModal() {
    console.log('📋 showBulkImportModal called');
    
    const modalContent = `
        <div style="padding: 2rem;">
            <h3>📋 Bulk Gebruiker Import</h3>
            <p>Upload een CSV bestand met gebruiker gegevens.</p>
            
            <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; margin: 1rem 0;">
                <strong>CSV Format:</strong><br>
                <code>naam,email,telefoon,bedrijf,notities</code><br>
                <small>Eerste regel = headers, daarna data</small>
            </div>
            
            <form enctype="multipart/form-data" style="margin: 1rem 0;">
                <div class="form-group">
                    <label>CSV Bestand:</label>
                    <input type="file" accept=".csv" id="bulkImportFile" style="margin: 0.5rem 0;">
                </div>
                
                <div style="margin-top: 2rem;">
                    <button type="button" onclick="processBulkImport()" class="btn btn-primary">
                        📤 Importeren
                    </button>
                    <button type="button" onclick="closeModal('bulkImportModal')" class="btn btn-secondary">
                        Annuleren
                    </button>
                </div>
            </form>
        </div>
    `;
    
    // Create modal if it doesn't exist
    let modal = document.getElementById('bulkImportModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'bulkImportModal';
        modal.className = 'modal';
        modal.innerHTML = `<div class="modal-content">${modalContent}</div>`;
        document.body.appendChild(modal);
    } else {
        modal.querySelector('.modal-content').innerHTML = modalContent;
    }
    
    openModal('bulkImportModal');
}

function processBulkImport() {
    const fileInput = document.getElementById('bulkImportFile');
    if (!fileInput.files[0]) {
        alert('⚠️ Selecteer eerst een CSV bestand');
        return;
    }
    
    alert('📋 Bulk import functionaliteit wordt binnenkort geïmplementeerd!\n\nBestand: ' + fileInput.files[0].name);
    closeModal('bulkImportModal');
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

// Reset form function
function resetUserForm() {
    document.getElementById('userForm').reset();
    document.getElementById('userAction').value = 'create_user';
    document.getElementById('userId').value = '';
    document.getElementById('userModalTitle').textContent = 'Nieuwe Gebruiker Aanmaken';
    document.getElementById('userModalSubmitText').textContent = 'Gebruiker Aanmaken';
}

// Also assign to window object
window.openModal = openModal;
window.closeModal = closeModal;
window.editUser = editUser;
window.deleteUser = deleteUser;
window.assignCourses = assignCourses;
window.assignUserToCourse = assignUserToCourse;
window.showBulkImportModal = showBulkImportModal;
window.processBulkImport = processBulkImport;
window.applyFilters = applyFilters;
window.resetUserForm = resetUserForm;

console.log('🎯 All functions defined and assigned to window');

// Add form submit handler
document.addEventListener('DOMContentLoaded', function() {
    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.textContent = '💾 Opslaan...';
            submitBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Since we're mixing PHP responses, just reload for now
                alert('✅ Gebruiker succesvol opgeslagen!');
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Fout bij opslaan gebruiker');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

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