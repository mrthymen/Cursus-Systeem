<?php
/**
 * Inventijn Planning Dashboard - UNIFIED ADMIN EDITION v6.4.0
 * Converts course interest to actual enrollments with cross-module integration
 * Now using unified admin system components
 * 
 * @version 6.4.0
 * @author Martijn Planken & Claude
 * @date 2025-06-13
 * @changelog v6.4.0 - Complete conversion to unified admin system
 * @changelog v6.4.0 - Using admin_header.php, admin_footer.php, admin_styles.css
 * @changelog v6.4.0 - Converted modals to admin_modals.php system
 * @changelog v6.4.0 - Enhanced responsive design and accessibility
 */

// Start session first
session_start();

// Set page title for header
$page_title = 'Planning Dashboard';

// Check admin authentication and include unified header
require_once 'admin_header.php';

// Include modal functions
require_once 'admin_modals.php';

$message = '';
$error = '';

// Handle AJAX and form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle AJAX requests
    if (isset($_POST['action']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        
        try {
            switch ($_POST['action']) {
                case 'test_connection':
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Connection test successful',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'session_user' => $_SESSION['admin_user'] ?? 'Unknown'
                    ]);
                    break;
                    
                case 'convert_to_enrollment':
                    $interest_id = intval($_POST['interest_id']);
                    $course_id = intval($_POST['course_id']);
                    
                    $result = convertInterestToEnrollmentEnhanced($pdo, $interest_id, $course_id);
                    echo json_encode($result);
                    break;
                    
                case 'create_course_from_interest':
                    $interest_id = intval($_POST['interest_id']);
                    $course_data = json_decode($_POST['course_data'], true);
                    
                    $result = createCourseFromInterest($pdo, $interest_id, $course_data);
                    echo json_encode($result);
                    break;
                    
                case 'update_priority':
                    $interest_id = intval($_POST['interest_id']);
                    $priority = $_POST['priority'];
                    
                    $stmt = $pdo->prepare("UPDATE course_interest SET priority = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$priority, $interest_id])) {
                        echo json_encode(['success' => true, 'message' => 'Priority updated']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Priority update failed']);
                    }
                    break;
                    
                case 'add_note':
                    $interest_id = intval($_POST['interest_id']);
                    $note = trim($_POST['note']);
                    
                    $stmt = $pdo->prepare("UPDATE course_interest SET admin_notes = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$note, $interest_id])) {
                        echo json_encode(['success' => true, 'message' => 'Note added']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Note update failed']);
                    }
                    break;
                    
                case 'get_user_details':
                    $user_id = intval($_POST['user_id']);
                    $result = getUserDetailsForPlanning($pdo, $user_id);
                    echo json_encode($result);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $_POST['action']]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Handle regular form submissions
    if (isset($_POST['bulk_action'])) {
        try {
            $selected_interests = $_POST['selected_interests'] ?? [];
            $action = $_POST['bulk_action'];
            
            if (empty($selected_interests)) {
                $error = "Selecteer minimaal één interesse record.";
            } else {
                switch ($action) {
                    case 'set_high_priority':
                        $placeholders = str_repeat('?,', count($selected_interests) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE course_interest SET priority = 'high', updated_at = NOW() WHERE id IN ($placeholders)");
                        $stmt->execute($selected_interests);
                        $message = count($selected_interests) . " records set to high priority.";
                        break;
                        
                    case 'mark_contacted':
                        $placeholders = str_repeat('?,', count($selected_interests) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE course_interest SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[" . date('Y-m-d') . "] Contacted'), updated_at = NOW() WHERE id IN ($placeholders)");
                        $stmt->execute($selected_interests);
                        $message = count($selected_interests) . " records marked as contacted.";
                        break;
                        
                    case 'bulk_convert':
                        $course_id = intval($_POST['bulk_course_id']);
                        if ($course_id > 0) {
                            $success_count = 0;
                            foreach ($selected_interests as $interest_id) {
                                $result = convertInterestToEnrollmentEnhanced($pdo, $interest_id, $course_id);
                                if ($result['success']) $success_count++;
                            }
                            $message = "$success_count interests successfully converted to enrollments.";
                        }
                        break;
                }
            }
        } catch (Exception $e) {
            $error = "Bulk action failed: " . $e->getMessage();
        }
    }
}

// ==========================================
// ENHANCED DATA RETRIEVAL WITH CROSS-MODULE INTEGRATION
// ==========================================

// Initialize with empty arrays
$interestSummary = [];
$recentInterest = [];
$plannedCourses = [];
$conversionStats = [];

try {
    // Get interest summary by training type with enhanced metrics
    $interestSummary = $pdo->query("
        SELECT 
            ci.training_type,
            ci.training_name,
            COUNT(*) as total_interest,
            SUM(ci.participant_count) as total_participants_wanted,
            COUNT(CASE WHEN ci.status = 'pending' THEN 1 END) as pending_interest,
            COUNT(CASE WHEN ci.status = 'converted' THEN 1 END) as converted_interest,
            COUNT(CASE WHEN ci.priority = 'high' THEN 1 END) as high_priority_count,
            MIN(ci.created_at) as first_interest,
            MAX(ci.created_at) as latest_interest,
            AVG(ci.participant_count) as avg_participants_per_interest
        FROM course_interest ci
        GROUP BY ci.training_type, ci.training_name
        ORDER BY pending_interest DESC, total_interest DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error .= "Interest summary error: " . $e->getMessage() . "\n";
}

try {
    // Get recent pending interests with enhanced user details
    $recentInterest = $pdo->query("
        SELECT 
            ci.*,
            u.name as user_name, 
            u.email as user_email, 
            u.company as user_company,
            u.phone as user_phone,
            u.created_at as user_since,
            CASE 
                WHEN u.name IS NULL THEN 'Guest Registration'
                ELSE CONCAT(u.name, ' (', u.email, ')')
            END as display_name,
            (SELECT COUNT(*) FROM course_participants cp WHERE cp.user_id = u.id) as user_course_count,
            (SELECT COUNT(*) FROM course_participants cp WHERE cp.user_id = u.id AND cp.payment_status = 'paid') as user_paid_courses
        FROM course_interest ci
        LEFT JOIN users u ON ci.user_id = u.id
        WHERE ci.status = 'pending'
        ORDER BY 
            ci.priority DESC,
            ci.created_at DESC
        LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error .= "Recent interest error: " . $e->getMessage() . "\n";
}

try {
    // Get planned courses with enhanced capacity and participant info
    $plannedCourses = $pdo->query("
        SELECT 
            c.*, 
            COUNT(cp.id) as current_participants,
            (c.max_participants - COUNT(cp.id)) as available_spots,
            SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_participants,
            SUM(CASE WHEN cp.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_participants,
            c.price * SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as confirmed_revenue
        FROM courses c
        LEFT JOIN course_participants cp ON c.id = cp.course_id
        WHERE c.active = 1 AND c.course_date > NOW()
        GROUP BY c.id
        ORDER BY c.course_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error .= "Courses error: " . $e->getMessage() . "\n";
}

try {
    // Enhanced conversion statistics
    $conversionStats = $pdo->query("
        SELECT 
            'Total Interest' as metric,
            COUNT(*) as value,
            'info' as type
        FROM course_interest
        UNION ALL
        SELECT 
            'Pending Conversion' as metric,
            COUNT(*) as value,
            'warning' as type
        FROM course_interest 
        WHERE status = 'pending'
        UNION ALL
        SELECT 
            'Successfully Converted' as metric,
            COUNT(*) as value,
            'success' as type
        FROM course_interest 
        WHERE status = 'converted'
        UNION ALL
        SELECT 
            'High Priority' as metric,
            COUNT(*) as value,
            'danger' as type
        FROM course_interest 
        WHERE priority = 'high' AND status = 'pending'
        UNION ALL
        SELECT 
            'Ready for Certificates' as metric,
            COUNT(*) as value,
            'info' as type
        FROM course_participants cp 
        JOIN courses c ON cp.course_id = c.id 
        WHERE cp.payment_status = 'paid' 
        AND c.course_date < NOW() 
        AND NOT EXISTS (SELECT 1 FROM certificates WHERE course_participant_id = cp.id)
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error .= "Stats error: " . $e->getMessage() . "\n";
}

// Calculate totals for dashboard
$stats = [];
foreach ($conversionStats as $stat) {
    $stats[$stat['metric']] = [
        'value' => $stat['value'],
        'type' => $stat['type']
    ];
}

// ==========================================
// ENHANCED HELPER FUNCTIONS
// ==========================================

/**
 * Enhanced conversion function with full integration
 */
function convertInterestToEnrollmentEnhanced($pdo, $interest_id, $course_id) {
    try {
        $pdo->beginTransaction();
        
        // Get interest data
        $stmt = $pdo->prepare("SELECT * FROM course_interest WHERE id = ?");
        $stmt->execute([$interest_id]);
        $interest = $stmt->fetch();
        
        if (!$interest) {
            throw new Exception("Interest record not found");
        }
        
        // Check course capacity
        $stmt = $pdo->prepare("
            SELECT c.*, COUNT(cp.id) as current_participants 
            FROM courses c 
            LEFT JOIN course_participants cp ON c.id = cp.course_id 
            WHERE c.id = ? 
            GROUP BY c.id
        ");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            throw new Exception("Course not found");
        }
        
        if ($course['current_participants'] >= $course['max_participants']) {
            throw new Exception("Course is full");
        }
        
        // Create enrollment record
        $stmt = $pdo->prepare("
            INSERT INTO course_participants (user_id, course_id, payment_status, enrollment_date, notes)
            VALUES (?, ?, 'pending', NOW(), ?)
        ");
        $notes = "Converted from interest #$interest_id - " . ($interest['availability_comment'] ?: 'No additional notes');
        $stmt->execute([$interest['user_id'], $course_id, $notes]);
        
        $participant_id = $pdo->lastInsertId();
        
        // Update interest status
        $stmt = $pdo->prepare("
            UPDATE course_interest 
            SET status = 'converted', converted_to_course_id = ?, converted_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$course_id, $interest_id]);
        
        // Log the conversion
        if (isset($_SESSION['admin_user'])) {
            $stmt = $pdo->prepare("
                INSERT INTO access_log (user_id, action, resource, ip_address, success, details, timestamp)
                VALUES (?, 'interest_conversion', 'planning.php', ?, 1, ?, NOW())
            ");
            $details = json_encode([
                'interest_id' => $interest_id,
                'course_id' => $course_id,
                'participant_id' => $participant_id,
                'admin_user' => $_SESSION['admin_user']
            ]);
            $stmt->execute([$interest['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', $details]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Interest successfully converted to enrollment',
            'participant_id' => $participant_id,
            'course_name' => $course['name'],
            'redirect_url' => "courses.php?course_id=$course_id"
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Create new course from interest pattern
 */
function createCourseFromInterest($pdo, $interest_id, $course_data) {
    try {
        // Implementation for creating course based on interest pattern
        // This would analyze the interest and create a suitable course
        return ['success' => true, 'message' => 'Feature coming soon'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get detailed user information for planning context
 */
function getUserDetailsForPlanning($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   COUNT(cp.id) as total_courses,
                   COUNT(CASE WHEN cp.payment_status = 'paid' THEN 1 END) as paid_courses,
                   COUNT(ci.id) as total_interests,
                   MAX(cp.enrollment_date) as last_enrollment
            FROM users u
            LEFT JOIN course_participants cp ON u.id = cp.user_id
            LEFT JOIN course_interest ci ON u.id = ci.user_id
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            return ['success' => true, 'user' => $user];
        } else {
            return ['success' => false, 'message' => 'User not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

?>

<!-- Page Header Card -->
<div class="card">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-calendar-alt"></i> Planning Dashboard</h3>
            <span class="version-badge">v6.4.0 - Unified Edition</span>
        </div>
        <div style="display: flex; gap: var(--space-2);">
            <button onclick="refreshData()" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <a href="courses.php" class="btn btn-success">
                <i class="fas fa-plus"></i> New Course
            </a>
            <button onclick="testConnection()" class="btn btn-outline btn-sm" title="Test AJAX Connection">
                <i class="fas fa-wifi"></i> Test
            </button>
        </div>
    </div>
    <div class="course-essentials">
        <?php foreach ($conversionStats as $stat): ?>
        <div class="essential-item">
            <div class="essential-label">
                <?php
                $icons = [
                    'Total Interest' => 'fas fa-heart',
                    'Pending Conversion' => 'fas fa-clock',
                    'Successfully Converted' => 'fas fa-check-circle',
                    'High Priority' => 'fas fa-flag',
                    'Ready for Certificates' => 'fas fa-certificate'
                ];
                $icon = $icons[$stat['metric']] ?? 'fas fa-info-circle';
                ?>
                <i class="<?= $icon ?>"></i> <?= $stat['metric'] ?>
            </div>
            <div class="essential-value" style="color: var(--<?= $stat['type'] === 'danger' ? 'error' : ($stat['type'] === 'warning' ? 'warning' : ($stat['type'] === 'success' ? 'success' : 'primary')) ?>);">
                <?= $stat['value'] ?>
            </div>
            <?php if ($stat['metric'] === 'Ready for Certificates' && $stat['value'] > 0): ?>
                <a href="certificates.php?ready=1" style="font-size: 0.75rem; color: var(--primary); text-decoration: none;">
                    → Generate Certificates
                </a>
            <?php elseif ($stat['metric'] === 'High Priority' && $stat['value'] > 0): ?>
                <div style="font-size: 0.75rem; color: var(--error);">Needs attention</div>
            <?php elseif ($stat['metric'] === 'Successfully Converted'): ?>
                <a href="courses.php" style="font-size: 0.75rem; color: var(--primary); text-decoration: none;">
                    → View Courses
                </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
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

<!-- Tab Navigation -->
<div class="tab-navigation">
    <div class="tab-links">
        <a href="#interests" class="tab-button active">
            <i class="fas fa-heart"></i> Active Interests (<?= count($recentInterest) ?>)
        </a>
        <a href="#summary" class="tab-button">
            <i class="fas fa-chart-pie"></i> Training Summary
        </a>
        <a href="#courses" class="tab-button">
            <i class="fas fa-book"></i> Planned Courses (<?= count($plannedCourses) ?>)
        </a>
    </div>
    <div class="action-buttons">
        <a href="../formulier-ai2.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-edit"></i> Test Form
        </a>
        <a href="users.php" class="btn btn-outline btn-sm">
            <i class="fas fa-users"></i> Users
        </a>
        <a href="certificates.php" class="btn btn-outline btn-sm">
            <i class="fas fa-certificate"></i> Certificates
        </a>
    </div>
</div>





<!-- Main Interest Management -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-heart"></i> Interest Management</h3>
        <div>
            <span class="nav-badge"><?= count($recentInterest) ?> pending</span>
        </div>
    </div>
    
    <?php if (empty($recentInterest)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p><strong>Geen openstaande interesse</strong></p>
            <p>Alle interesse is geconverteerd of er zijn nog geen nieuwe aanmeldingen.</p>
        </div>
    <?php else: ?>
    
    <!-- Enhanced Bulk Actions -->
    <form method="post" id="bulkActionsForm">
        <div class="card" style="background: var(--surface-hover); margin: 1rem; border: 1px solid var(--border);">
            <div style="padding: 1rem;">
                <strong><i class="fas fa-tasks"></i> Bulk Actions:</strong>
                <div class="btn-group" style="margin-top: 0.5rem;">
                    <select name="bulk_action" class="form-control" style="display: inline-block; width: auto; margin-right: 0.5rem;">
                        <option value="">Choose action...</option>
                        <option value="set_high_priority">🔥 Set High Priority</option>
                        <option value="mark_contacted">📞 Mark as Contacted</option>
                        <option value="bulk_convert">🎯 Convert to Course</option>
                    </select>
                    
                    <select name="bulk_course_id" style="display: none;" id="bulk-course-select">
                        <option value="">Select course...</option>
                        <?php foreach ($plannedCourses as $course): ?>
                            <option value="<?= $course['id'] ?>">
                                <?= htmlspecialchars($course['name']) ?> (<?= $course['available_spots'] ?> spots)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-check"></i> Apply to Selected
                    </button>
                    
                    <label style="margin-left: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="select-all"> Select All
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Interest Records with Enhanced Information -->
        <div style="padding: 0 1rem 1rem; display: grid; gap: 1.5rem;">
            <?php foreach ($recentInterest as $interest): ?>
            <div class="interest-item course-item <?= $interest['priority'] === 'high' ? 'high-priority' : '' ?>" style="position: relative; margin-bottom: 0;">
                
                <?php if ($interest['priority'] === 'high'): ?>
                <div style="position: absolute; top: 1rem; right: 1rem; background: var(--error); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                    🔥 HIGH
                </div>
                <?php endif; ?>
                
                <div class="course-header" style="grid-template-columns: auto 1fr auto; gap: 1rem; align-items: start;">
                    <input type="checkbox" name="selected_interests[]" value="<?= $interest['id'] ?>" class="interest-checkbox" style="margin-top: 0.25rem;">
                    
                    <div style="min-width: 0;">
                        <div class="course-title" style="margin-bottom: 0.75rem;">
                            <?= htmlspecialchars($interest['display_name']) ?>
                            <?php if ($interest['user_course_count'] > 0): ?>
                                <span class="user-badge" style="background: var(--success); color: white; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; margin-left: 0.5rem;">
                                    🎓 <?= $interest['user_course_count'] ?> courses
                                </span>
                            <?php endif; ?>
                            <?php if ($interest['user_paid_courses'] > 0): ?>
                                <span class="user-badge" style="background: var(--warning); color: white; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; margin-left: 0.5rem;">
                                    💰 <?= $interest['user_paid_courses'] ?> paid
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="background: var(--primary); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; margin-bottom: 0.75rem;">
                            <?= htmlspecialchars($interest['training_name']) ?>
                        </div>
                        
                        <div class="course-subtitle" style="margin-bottom: 0.75rem;">
                            📧 <?= htmlspecialchars($interest['user_email'] ?: 'No email') ?> |
                            🏢 <?= htmlspecialchars($interest['company'] ?: 'No company') ?> |
                            👥 <?= $interest['participant_count'] ?> participant(s) |
                            📅 <?= date('d-m-Y H:i', strtotime($interest['created_at'])) ?>
                            <?php if ($interest['user_since']): ?>
                                | 👤 User since <?= date('M Y', strtotime($interest['user_since'])) ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($interest['availability_comment']): ?>
                        <div style="background: var(--surface-hover); padding: 0.75rem; border-radius: 6px; margin-top: 0.75rem; font-size: 0.875rem; border-left: 3px solid var(--neutral);">
                            💬 <?= htmlspecialchars($interest['availability_comment']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($interest['admin_notes']): ?>
                        <div style="background: #fef3c7; padding: 0.75rem; border-radius: 6px; margin-top: 0.75rem; font-size: 0.875rem; border-left: 3px solid var(--warning);">
                            📝 <?= htmlspecialchars($interest['admin_notes']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="btn-group" style="flex-direction: column; margin: 0; gap: 0.5rem;">
                        <button type="button" onclick="toggleConversion(<?= $interest['id'] ?>)" class="btn btn-success btn-sm">
                            <i class="fas fa-exchange-alt"></i> Convert
                        </button>
                        <?php if ($interest['user_id']): ?>
                            <a href="users.php?user_id=<?= $interest['user_id'] ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-user"></i> User
                            </a>
                        <?php endif; ?>
                        <button type="button" onclick="setPriority(<?= $interest['id'] ?>, 'high')" class="btn btn-warning btn-sm">
                            <i class="fas fa-flag"></i> Priority
                        </button>
                        <button type="button" onclick="addNote(<?= $interest['id'] ?>)" class="btn btn-outline btn-sm">
                            <i class="fas fa-sticky-note"></i> Note
                        </button>
                    </div>
                </div>
                
                <!-- Enhanced Conversion Section -->
                <div id="conversion-<?= $interest['id'] ?>" class="conversion-section" style="display: none; background: #f0f9ff; border: 1px solid var(--primary); border-radius: 8px; padding: 1.5rem; margin-top: 1.5rem;">
                    <h4 style="color: var(--primary); margin-bottom: 1rem;">
                        <i class="fas fa-exchange-alt"></i> Convert to Course Enrollment
                    </h4>
                    <p style="margin-bottom: 1rem; color: var(--text-secondary);">Select a course to enroll this participant:</p>
                    
                    <div style="display: grid; gap: 0.75rem;">
                        <?php foreach ($plannedCourses as $course): ?>
                        <div class="course-option" onclick="selectCourse(<?= $interest['id'] ?>, <?= $course['id'] ?>)" data-course-id="<?= $course['id'] ?>" 
                             style="background: white; border: 1px solid var(--border); border-radius: 6px; padding: 1rem; cursor: pointer; transition: all 0.2s;">
                            <div style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: center;">
                                <div>
                                    <strong style="color: var(--text-primary);"><?= htmlspecialchars($course['name']) ?></strong><br>
                                    <small style="color: var(--text-secondary);">
                                        📅 <?= date('d-m-Y H:i', strtotime($course['course_date'])) ?> |
                                        📍 <?= htmlspecialchars($course['location'] ?: 'Location TBD') ?>
                                    </small>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                        👥 <?= $course['current_participants'] ?>/<?= $course['max_participants'] ?>
                                    </div>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        €<?= number_format($course['price'], 2) ?>
                                    </div>
                                    <?php if ($course['available_spots'] <= 0): ?>
                                        <div style="color: var(--error); font-weight: bold; font-size: 0.75rem;">FULL</div>
                                    <?php elseif ($course['available_spots'] <= 3): ?>
                                        <div style="color: var(--warning); font-weight: bold; font-size: 0.75rem;"><?= $course['available_spots'] ?> spots left</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="btn-group" style="margin-top: 1.5rem;">
                        <button type="button" onclick="executeConversion(<?= $interest['id'] ?>)" class="btn btn-success" id="convert-btn-<?= $interest['id'] ?>" disabled>
                            <i class="fas fa-check"></i> Confirm Conversion
                        </button>
                        <button type="button" onclick="toggleConversion(<?= $interest['id'] ?>)" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- Enhanced Interest Summary with Cross-Module Links -->
<?php if (!empty($interestSummary)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chart-pie"></i> Interest by Training Type</h3>
        <a href="courses.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Create Matching Course
        </a>
    </div>
    <div style="padding: 1rem; display: grid; gap: 1rem;">
        <?php foreach ($interestSummary as $summary): ?>
        <div class="course-item">
            <div class="course-header">
                <div>
                    <div class="course-title">
                        <?= htmlspecialchars($summary['training_name']) ?>
                        <span style="font-size: 0.75rem; background: var(--primary); color: white; padding: 0.25rem 0.5rem; border-radius: 12px; margin-left: 0.5rem;">
                            <?= htmlspecialchars($summary['training_type']) ?>
                        </span>
                    </div>
                    <div class="course-subtitle">
                        ⏳ <strong><?= $summary['pending_interest'] ?></strong> pending |
                        ✅ <strong><?= $summary['converted_interest'] ?></strong> converted |
                        👥 <strong><?= $summary['total_participants_wanted'] ?></strong> participants wanted |
                        🔥 <strong><?= $summary['high_priority_count'] ?></strong> high priority
                    </div>
                </div>
                <?php if ($summary['pending_interest'] > 0): ?>
                <a href="courses.php?template=<?= urlencode($summary['training_type']) ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Create Course
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Conversion Modal -->
<?= renderInfoModal('conversionDetailsModal', 'Conversion Details', '<div id="conversionDetailsContent">Loading...</div>') ?>

<!-- User Details Modal -->
<?= renderInfoModal('userDetailsModal', 'User Details', '<div id="userDetailsContent">Loading...</div>') ?>

<script>
// Enhanced JavaScript with unified admin system integration
let selectedCourseId = null;
let currentInterestId = null;

// Initialize page-specific functionality
document.addEventListener('DOMContentLoaded', function() {
    // Check if unified admin functions are available
    if (typeof showNotification !== 'function') {
        // Fallback notification function
        window.showNotification = function(message, type = 'info', duration = 3000) {
            alert(type.toUpperCase() + ': ' + message);
        };
    }
    
    if (typeof setButtonLoading !== 'function') {
        // Fallback button loading function
        window.setButtonLoading = function(button, loading = true) {
            if (loading) {
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            } else {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || button.innerHTML;
            }
        };
    }

    // Bulk action enhancement
    const bulkActionSelect = document.querySelector('select[name="bulk_action"]');
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            const courseSelect = document.getElementById('bulk-course-select');
            if (courseSelect) {
                if (this.value === 'bulk_convert') {
                    courseSelect.style.display = 'inline-block';
                    courseSelect.required = true;
                } else {
                    courseSelect.style.display = 'none';
                    courseSelect.required = false;
                }
            }
        });
    }

    // Select all functionality
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.interest-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }
    
    // Course option styling
    document.querySelectorAll('.course-option').forEach(option => {
        option.addEventListener('mouseenter', function() {
            this.style.borderColor = 'var(--primary)';
            this.style.background = '#faf5ff';
        });
        
        option.addEventListener('mouseleave', function() {
            if (!this.classList.contains('selected')) {
                this.style.borderColor = 'var(--border)';
                this.style.background = 'white';
            }
        });
    });
    
    console.log('🎯 Planning Dashboard v6.4.0 - JavaScript initialized successfully');
});

// Enhanced conversion function with unified notifications
function executeConversion(interestId) {
    if (!selectedCourseId) {
        showNotification('Please select a course first', 'warning');
        return;
    }

    const button = document.getElementById('convert-btn-' + interestId);
    setButtonLoading(button, true);

    fetchData('planning.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=convert_to_enrollment&interest_id=${interestId}&course_id=${selectedCourseId}`
    })
    .then(data => {
        if (data.success) {
            showNotification(`Successfully converted to enrollment! Course: ${data.course_name}`, 'success');
            
            // Ask user if they want to view course details
            if (confirm('Would you like to view the course details?')) {
                window.location.href = data.redirect_url;
            } else {
                location.reload();
            }
        } else {
            showNotification('Conversion failed: ' + data.message, 'error');
            setButtonLoading(button, false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error occurred', 'error');
        setButtonLoading(button, false);
    });
}

// Toggle conversion panel
function toggleConversion(interestId) {
    const panel = document.getElementById('conversion-' + interestId);
    if (panel.style.display === 'none') {
        // Hide all other panels
        document.querySelectorAll('.conversion-section').forEach(p => p.style.display = 'none');
        panel.style.display = 'block';
        currentInterestId = interestId;
    } else {
        panel.style.display = 'none';
        currentInterestId = null;
    }
    selectedCourseId = null;
    updateConvertButton(interestId);
}

// Select course for conversion
function selectCourse(interestId, courseId) {
    // Reset all course options
    document.querySelectorAll('.course-option').forEach(opt => {
        opt.classList.remove('selected');
        opt.style.borderColor = 'var(--border)';
        opt.style.background = 'white';
    });
    
    // Select the clicked option
    const selectedOption = document.querySelector(`[data-course-id="${courseId}"]`);
    selectedOption.classList.add('selected');
    selectedOption.style.borderColor = 'var(--primary)';
    selectedOption.style.background = '#eff6ff';
    
    selectedCourseId = courseId;
    updateConvertButton(interestId);
}

// Update convert button state
function updateConvertButton(interestId) {
    const button = document.getElementById('convert-btn-' + interestId);
    if (selectedCourseId) {
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-check"></i> Confirm Conversion';
        button.className = 'btn btn-success';
    } else {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Select Course First';
        button.className = 'btn btn-secondary';
    }
}

// Set priority with unified notifications
function setPriority(interestId, priority) {
    fetch('planning.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=update_priority&interest_id=${interestId}&priority=${priority}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Priority updated', 'success');
            location.reload();
        } else {
            showNotification('Failed to update priority: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Network error occurred', 'error');
    });
}

// Add note with unified modal system
function addNote(interestId) {
    const note = prompt('Add admin note:');
    if (note && note.trim()) {
        fetch('planning.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=add_note&interest_id=${interestId}&note=${encodeURIComponent(note)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Note added', 'success');
                location.reload();
            } else {
                showNotification('Failed to add note: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Network error occurred', 'error');
        });
    }
}

// Refresh data
function refreshData() {
    showNotification('Refreshing data...', 'info', 1000);
    setTimeout(() => location.reload(), 500);
}

// Test connection function (can be called from browser console)
window.testConnection = function() {
    console.log('🧪 Testing AJAX connection...');
    
    fetch('planning.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=test_connection'
    })
    .then(response => {
        console.log('📡 Response status:', response.status);
        console.log('📡 Response headers:', Object.fromEntries(response.headers.entries()));
        
        return response.text();
    })
    .then(text => {
        console.log('📄 Raw response:', text);
        
        try {
            const data = JSON.parse(text);
            console.log('✅ Parsed JSON:', data);
            if (typeof showNotification === 'function') {
                showNotification('Connection test successful!', 'success');
            } else {
                alert('Connection test successful!');
            }
            return data;
        } catch (e) {
            console.error('❌ JSON Parse Error:', e);
            console.log('📄 Response was not JSON:', text);
            if (typeof showNotification === 'function') {
                showNotification('Connection test failed: Invalid JSON response', 'error');
            } else {
                alert('Connection test failed: Invalid JSON response');
            }
            throw new Error('Invalid JSON response');
        }
    })
    .catch(error => {
        console.error('🚨 Connection Test Error:', error);
        if (typeof showNotification === 'function') {
            showNotification('Connection test failed: ' + error.message, 'error');
        } else {
            alert('Connection test failed: ' + error.message);
        }
    });
};

// Debug function for troubleshooting AJAX issues
function debugAjaxRequest(action, data = {}) {
    console.log('🔍 Debug AJAX Request:', action, data);
    
    const formData = new URLSearchParams();
    formData.append('action', action);
    Object.keys(data).forEach(key => {
        formData.append(key, data[key]);
    });
    
    fetch('planning.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData.toString()
    })
    .then(response => {
        console.log('📡 Response status:', response.status);
        console.log('📡 Response headers:', Object.fromEntries(response.headers.entries()));
        
        return response.text(); // Get as text first to see what we're getting
    })
    .then(text => {
        console.log('📄 Raw response:', text);
        
        try {
            const data = JSON.parse(text);
            console.log('✅ Parsed JSON:', data);
            return data;
        } catch (e) {
            console.error('❌ JSON Parse Error:', e);
            console.log('📄 Response was not JSON:', text);
            throw new Error('Invalid JSON response');
        }
    })
    .catch(error => {
        console.error('🚨 AJAX Error:', error);
    });
}

// Auto-refresh every 3 minutes
setInterval(refreshData, 180000);

// Generate planning dashboard auto-functions with error handling
try {
    if (typeof generateEditFunction === 'function') {
        generateEditFunction('interest');
    }
    if (typeof generateResetFunction === 'function') {
        generateResetFunction('interest');
    }
} catch (e) {
    console.warn('⚠️ Auto-generation functions not available:', e.message);
}

console.log('🎯 Planning Dashboard v6.4.0 - Unified Admin Edition loaded');
console.log('✅ Using unified admin system components');
console.log('✅ Enhanced conversion flow ready');

// Add global error handler for uncaught errors
window.addEventListener('error', function(e) {
    console.error('🚨 Global Error:', e.error);
    if (typeof showNotification === 'function') {
        showNotification('An unexpected error occurred: ' + e.message, 'error');
    }
});

// Add unhandled promise rejection handler
window.addEventListener('unhandledrejection', function(e) {
    console.error('🚨 Unhandled Promise Rejection:', e.reason);
    if (typeof showNotification === 'function') {
        showNotification('Network error: ' + e.reason, 'error');
    }
    e.preventDefault();
});
</script>

<?php 
// Include unified admin footer
require_once 'admin_footer.php'; 
?>