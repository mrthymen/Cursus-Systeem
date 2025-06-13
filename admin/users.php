<?php
/**
 * Inventijn User Management System v6.6.0 - WORKING MODALS FIXED
 * Updated: 2025-06-13
 * FIXED: Applied working modal CSS and JavaScript from test
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

<style>
/* WORKING MODAL CSS - TESTED AND VERIFIED */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 9999;
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 0;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 20px 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    position: relative;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.modal-header h3 {
    margin: 0;
    color: #1f2937;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-close {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 24px;
    font-weight: bold;
    color: #999;
    cursor: pointer;
    background: none;
    border: none;
    padding: 5px;
    border-radius: 4px;
}

.modal-close:hover {
    color: #000;
    background: #e9ecef;
}

.modal-body {
    padding: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: 600;
    color: #374151;
}

.form-group input,
.form-group textarea,
.form-group select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-group {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
}

@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .btn-group {
        flex-direction: column;
    }
}
</style>

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

<!-- Course Assignment Modal -->
<div id="courseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="courseModalTitle">Cursus Toekenning</h3>
            <button class="modal-close" onclick="closeModal('courseModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="courseContent">
                <p>🔄 Cursussen laden...</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>

<script>
console.log('🚀 User Management v6.6.0 - WORKING MODALS');

// TESTED AND WORKING MODAL FUNCTIONS
function openModal(modalId) {
    console.log('📖 Opening modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        console.log('✅ Modal opened:', modalId);
    } else {
        console.error('❌ Modal not found:', modalId);
    }
}

function closeModal(modalId) {
    console.log('📕 Closing modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        console.log('✅ Modal closed:', modalId);
    }
}

function editUser(userId) {
    console.log('✏️ Edit user:', userId);
    
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
            document.getElementById('userModalTitle').textContent = 'Gebruiker Bewerken: ' + user.name;
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
    console.log('🗑️ Delete user:', userId);
    if (confirm('Weet je zeker dat je deze gebruiker wilt deactiveren?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="' + userId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function assignCourses(userId) {
    console.log('📚 Assign courses for user:', userId);
    
    // Update modal title
    document.getElementById('courseModalTitle').textContent = 'Cursussen voor Gebruiker ' + userId;
    
    // Show loading state
    document.getElementById('courseContent').innerHTML = '<p>🔄 Beschikbare cursussen laden...</p>';
    
    // Open modal first
    openModal('courseModal');
    
    // Load available courses via AJAX
    fetch('?ajax=1&action=get_courses')
        .then(response => response.json())
        .then(courses => {
            if (courses.length === 0) {
                document.getElementById('courseContent').innerHTML = `
                    <p>Geen actieve cursussen beschikbaar.</p>
                    <div style="margin-top: 20px;">
                        <button onclick="closeModal('courseModal')" class="btn btn-secondary">Sluiten</button>
                    </div>
                `;
                return;
            }
            
            let coursesHtml = '<div style="margin-bottom: 20px;"><h4>Beschikbare Cursussen:</h4></div>';
            
            courses.forEach(course => {
                const startDate = course.start_date ? new Date(course.start_date).toLocaleDateString('nl-NL') : 'Datum TBD';
                coursesHtml += `
                    <div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px;">
                        <strong>${course.name}</strong><br>
                        📅 ${startDate} | 💰 €${course.price || 'TBD'}<br>
                        <button onclick="assignUserToCourse(${userId}, ${course.id})" class="btn btn-primary" style="margin-top: 10px;">
                            ➕ Toekennen
                        </button>
                    </div>
                `;
            });
            
            coursesHtml += `
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <button onclick="closeModal('courseModal')" class="btn btn-secondary">Sluiten</button>
                </div>
            `;
            
            document.getElementById('courseContent').innerHTML = coursesHtml;
        })
        .catch(error => {
            console.error('Error loading courses:', error);
            document.getElementById('courseContent').innerHTML = '<p>❌ Fout bij laden cursussen</p>';
        });
}

function assignUserToCourse(userId, courseId) {
    if (confirm('Weet je zeker dat je deze cursus wilt toekennen aan de gebruiker?')) {
        alert(`✅ Cursus ${courseId} toegekend aan gebruiker ${userId}!\n(Database functionaliteit wordt nog geïmplementeerd)`);
    }
}

function showBulkImportModal() {
    alert('📋 Bulk import functionaliteit wordt binnenkort geïmplementeerd!');
}

function applyFilters() {
    console.log('🔍 Apply filters called');
    var search = document.getElementById('searchInput').value;
    var status = document.getElementById('statusFilter').value;
    
    var url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('status', status);
    url.searchParams.set('page', '1');
    
    window.location.href = url.toString();
}

function resetUserForm() {
    console.log('🔄 Reset user form');
    document.getElementById('userForm').reset();
    document.getElementById('userAction').value = 'create_user';
    document.getElementById('userId').value = '';
    document.getElementById('userModalTitle').textContent = 'Nieuwe Gebruiker Aanmaken';
    document.getElementById('userModalSubmitText').textContent = 'Gebruiker Aanmaken';
}

// Form submit handler with AJAX for smoother experience
document.addEventListener('DOMContentLoaded', function() {
    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '💾 Opslaan...';
            submitBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('✅ Gebruiker succesvol opgeslagen!');
                closeModal('userModal');
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Fout bij opslaan gebruiker');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
};

console.log('✅ All functions loaded and ready!');
</script>