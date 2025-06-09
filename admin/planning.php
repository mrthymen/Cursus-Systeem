<?php
/**
 * Inventijn Planning Dashboard - INTEGRATED VERSION
 * Converts course interest to actual enrollments with cross-module integration
 * 
 * @version 4.1.0
 * @author Martijn Planken & Claude
 * @date 2025-06-09
 * @changelog v4.1.0 - Added unified header, enhanced cross-module integration, improved conversion flow
 */

// Enable comprehensive error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session first
session_start();

// Check admin login
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=planning.php');
    exit;
}

try {
    require_once '../includes/config.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("❌ System Error: " . $e->getMessage());
}

$message = '';
$error = '';

// Handle AJAX and form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle AJAX requests
    if (isset($_POST['action']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        
        try {
            switch ($_POST['action']) {
                case 'convert_to_enrollment':
                    $interest_id = intval($_POST['interest_id']);
                    $course_id = intval($_POST['course_id']);
                    
                    // Enhanced conversion with full integration
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
                    echo json_encode(['success' => false, 'message' => 'Unknown action']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
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
$relatedUsers = [];

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
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning Dashboard v4.1.0 - Inventijn</title>
    <style>
        :root {
            --inventijn-light-pink: #e3a1e5;
            --inventijn-purple: #b998e4;
            --inventijn-light-blue: #6b80e8;
            --inventijn-dark-blue: #3e5cc6;
            --yellow: #F9CB40;
            --orange: #F9A03F;
            --white: #FFFFFF;
            --grey-light: #F2F2F2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif; 
            background: var(--grey-light); 
            line-height: 1.6; 
            color: #374151;
        }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 2rem; 
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .page-title {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 6px solid var(--yellow);
        }
        
        .page-title h1 {
            color: var(--inventijn-dark-blue);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .page-title .subtitle {
            color: var(--inventijn-purple);
            font-size: 1.1rem;
        }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 2rem; 
        }
        
        .stat-card { 
            background: white; 
            padding: 1.5rem; 
            border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
            border-left: 6px solid var(--orange);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.info { border-left-color: var(--inventijn-light-blue); }
        
        .stat-card h3 { 
            color: var(--inventijn-purple); 
            margin-bottom: 0.5rem; 
            font-size: 0.875rem; 
            text-transform: uppercase; 
            font-weight: 600;
        }
        
        .stat-card .value { 
            font-size: 2.5rem; 
            font-weight: 700; 
            color: var(--inventijn-dark-blue);
            margin-bottom: 0.25rem;
        }
        
        .stat-card .subtitle {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .stat-card .action-link {
            color: var(--orange);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        .panel { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .panel-header { 
            background: var(--inventijn-dark-blue); 
            color: white; 
            padding: 1.5rem; 
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-content { 
            padding: 1.5rem; 
        }
        
        .btn { 
            background: var(--orange); 
            color: white; 
            padding: 0.75rem 1.5rem; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 0.875rem; 
            font-weight: 600; 
            text-decoration: none; 
            display: inline-block;
            margin: 0.25rem;
            transition: all 0.2s;
        }
        
        .btn:hover { 
            background: #e69500; 
            transform: translateY(-1px);
        }
        
        .btn.small { padding: 0.5rem 1rem; font-size: 0.75rem; }
        .btn.success { background: var(--success); }
        .btn.success:hover { background: #059669; }
        .btn.danger { background: var(--danger); }
        .btn.danger:hover { background: #dc2626; }
        .btn.secondary { background: var(--inventijn-purple); }
        .btn.secondary:hover { background: #a78bfa; }
        
        .interest-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
            position: relative;
        }
        
        .interest-item:hover {
            border-color: var(--inventijn-purple);
            box-shadow: 0 2px 8px rgba(185, 152, 228, 0.2);
        }
        
        .interest-item.high-priority {
            border-left: 4px solid var(--danger);
            background: #fef2f2;
        }
        
        .interest-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .interest-details { flex: 1; }
        
        .interest-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .interest-title {
            font-weight: 600;
            color: var(--inventijn-dark-blue);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .interest-meta {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .interest-training {
            background: var(--inventijn-light-blue);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.5rem;
        }
        
        .user-badge {
            background: var(--grey-light);
            color: var(--inventijn-dark-blue);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin: 0.125rem 0.25rem 0.125rem 0;
            display: inline-block;
        }
        
        .priority-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--danger);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .bulk-actions {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
        }
        
        .bulk-actions select,
        .bulk-actions button {
            margin-right: 0.5rem;
        }
        
        .conversion-section {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .course-option {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.75rem;
            margin: 0.5rem 0;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .course-option:hover {
            border-color: var(--inventijn-purple);
            background: #faf5ff;
        }
        
        .course-option.selected {
            border-color: var(--inventijn-dark-blue);
            background: #eff6ff;
        }
        
        .cross-module-links {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .interest-header { flex-direction: column; gap: 1rem; }
            .interest-actions { width: 100%; justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <?php include 'unified_admin_header.php'; ?>

    <div class="container">
        <div class="page-title">
            <h1>📊 Planning Dashboard</h1>
            <div class="subtitle">v4.1.0 - Integrated Edition | Interest Management & Course Planning</div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert success">
            ✅ <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert error">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Enhanced Statistics Overview -->
        <div class="stats-grid">
            <?php foreach ($conversionStats as $stat): ?>
            <div class="stat-card <?= $stat['type'] ?>">
                <h3><?= $stat['metric'] ?></h3>
                <div class="value"><?= $stat['value'] ?></div>
                <?php if ($stat['metric'] === 'Ready for Certificates' && $stat['value'] > 0): ?>
                    <a href="certificates.php?ready=1" class="action-link">→ Generate Certificates</a>
                <?php elseif ($stat['metric'] === 'High Priority' && $stat['value'] > 0): ?>
                    <div class="subtitle">Needs immediate attention</div>
                <?php elseif ($stat['metric'] === 'Successfully Converted'): ?>
                    <a href="courses.php" class="action-link">→ View Courses</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Cross-Module Quick Actions -->
        <div class="cross-module-links">
            <a href="courses.php" class="btn secondary">📚 Manage Courses</a>
            <a href="users.php" class="btn secondary">👥 View Users</a>
            <a href="certificates.php" class="btn secondary">📜 Certificates</a>
            <a href="../formulier-ai2.php" class="btn">📝 Test Form</a>
        </div>

        <!-- Main Interest Management -->
        <div class="panel">
            <div class="panel-header">
                <span>🎯 Interest Management (<?= count($recentInterest) ?> pending)</span>
                <div>
                    <button onclick="refreshData()" class="btn small">🔄 Refresh</button>
                    <a href="courses.php" class="btn small">➕ New Course</a>
                </div>
            </div>
            <div class="panel-content">
                <?php if (empty($recentInterest)): ?>
                    <div style="text-align: center; padding: 3rem; color: #6b7280;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                        <h3>Geen openstaande interesse</h3>
                        <p>Alle interesse is geconverteerd of er zijn nog geen nieuwe aanmeldingen.</p>
                        <div class="cross-module-links" style="justify-content: center; margin-top: 2rem;">
                            <a href="../formulier-ai2.php" class="btn">📝 Test Formulier</a>
                            <a href="courses.php" class="btn secondary">📚 Manage Courses</a>
                        </div>
                    </div>
                <?php else: ?>
                
                <!-- Enhanced Bulk Actions -->
                <form method="post" class="bulk-actions">
                    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                        <strong>Bulk Actions:</strong>
                        <select name="bulk_action">
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
                        
                        <button type="submit" class="btn small">Apply to Selected</button>
                        <label style="margin-left: 1rem;">
                            <input type="checkbox" id="select-all"> Select All
                        </label>
                    </div>
                </form>
                
                <!-- Interest Records with Enhanced Information -->
                <?php foreach ($recentInterest as $interest): ?>
                <div class="interest-item <?= $interest['priority'] === 'high' ? 'high-priority' : '' ?>">
                    
                    <?php if ($interest['priority'] === 'high'): ?>
                    <div class="priority-badge">🔥 HIGH</div>
                    <?php endif; ?>
                    
                    <div class="interest-header">
                        <div class="interest-details">
                            <div style="float: left; margin-right: 1rem;">
                                <input type="checkbox" name="selected_interests[]" value="<?= $interest['id'] ?>" class="interest-checkbox">
                            </div>
                            
                            <div class="interest-title">
                                <?= htmlspecialchars($interest['display_name']) ?>
                                <?php if ($interest['user_course_count'] > 0): ?>
                                    <span class="user-badge">🎓 <?= $interest['user_course_count'] ?> courses</span>
                                <?php endif; ?>
                                <?php if ($interest['user_paid_courses'] > 0): ?>
                                    <span class="user-badge">💰 <?= $interest['user_paid_courses'] ?> paid</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="interest-training">
                                <?= htmlspecialchars($interest['training_name']) ?>
                            </div>
                            
                            <div class="interest-meta">
                                📧 <?= htmlspecialchars($interest['user_email'] ?: 'No email') ?> |
                                🏢 <?= htmlspecialchars($interest['company'] ?: 'No company') ?> |
                                👥 <?= $interest['participant_count'] ?> participant(s) |
                                📅 <?= date('d-m-Y H:i', strtotime($interest['created_at'])) ?>
                                <?php if ($interest['user_since']): ?>
                                    | 👤 User since <?= date('M Y', strtotime($interest['user_since'])) ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($interest['availability_comment']): ?>
                            <div style="background: #f3f4f6; padding: 0.5rem; border-radius: 4px; margin-top: 0.5rem; font-size: 0.875rem;">
                                💬 <?= htmlspecialchars($interest['availability_comment']) ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($interest['admin_notes']): ?>
                            <div style="background: #fef3c7; padding: 0.5rem; border-radius: 4px; margin-top: 0.5rem; font-size: 0.875rem;">
                                📝 <?= htmlspecialchars($interest['admin_notes']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="interest-actions">
                            <button type="button" onclick="toggleConversion(<?= $interest['id'] ?>)" class="btn success small">
                                🎯 Convert
                            </button>
                            <?php if ($interest['user_id']): ?>
                                <a href="users.php?user_id=<?= $interest['user_id'] ?>" class="btn secondary small">
                                    👤 User
                                </a>
                            <?php endif; ?>
                            <button type="button" onclick="setPriority(<?= $interest['id'] ?>, 'high')" class="btn small">
                                🔥 Priority
                            </button>
                            <button type="button" onclick="addNote(<?= $interest['id'] ?>)" class="btn small">
                                📝 Note
                            </button>
                        </div>
                    </div>
                    
                    <!-- Enhanced Conversion Section -->
                    <div id="conversion-<?= $interest['id'] ?>" class="conversion-section" style="display: none;">
                        <h4>🎯 Convert to Course Enrollment</h4>
                        <p>Select a course to enroll this participant:</p>
                        
                        <?php foreach ($plannedCourses as $course): ?>
                        <div class="course-option" onclick="selectCourse(<?= $interest['id'] ?>, <?= $course['id'] ?>)" data-course-id="<?= $course['id'] ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong><?= htmlspecialchars($course['name']) ?></strong><br>
                                    📅 <?= date('d-m-Y H:i', strtotime($course['course_date'])) ?> |
                                    📍 <?= htmlspecialchars($course['location'] ?: 'Location TBD') ?>
                                </div>
                                <div style="text-align: right;">
                                    <div>👥 <?= $course['current_participants'] ?>/<?= $course['max_participants'] ?> enrolled</div>
                                    <div>💰 €<?= number_format($course['price'], 2) ?></div>
                                    <?php if ($course['available_spots'] <= 0): ?>
                                        <div style="color: red; font-weight: bold;">FULL</div>
                                    <?php elseif ($course['available_spots'] <= 3): ?>
                                        <div style="color: orange; font-weight: bold;"><?= $course['available_spots'] ?> spots left</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 1rem;">
                            <button type="button" onclick="executeConversion(<?= $interest['id'] ?>)" class="btn success" id="convert-btn-<?= $interest['id'] ?>" disabled>
                                ✅ Confirm Conversion
                            </button>
                            <button type="button" onclick="toggleConversion(<?= $interest['id'] ?>)" class="btn">
                                ❌ Cancel
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enhanced Interest Summary with Cross-Module Links -->
        <?php if (!empty($interestSummary)): ?>
        <div class="panel">
            <div class="panel-header">
                <span>📈 Interest by Training Type</span>
                <a href="courses.php" class="btn small">➕ Create Matching Course</a>
            </div>
            <div class="panel-content">
                <?php foreach ($interestSummary as $summary): ?>
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; color: var(--inventijn-dark-blue); margin-bottom: 0.5rem;">
                                <?= htmlspecialchars($summary['training_name']) ?>
                                <span style="font-size: 0.75rem; background: var(--inventijn-purple); color: white; padding: 0.25rem 0.5rem; border-radius: 12px; margin-left: 0.5rem;">
                                    <?= htmlspecialchars($summary['training_type']) ?>
                                </span>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--inventijn-purple);">
                                ⏳ <strong><?= $summary['pending_interest'] ?></strong> pending |
                                ✅ <strong><?= $summary['converted_interest'] ?></strong> converted |
                                👥 <strong><?= $summary['total_participants_wanted'] ?></strong> participants wanted |
                                🔥 <strong><?= $summary['high_priority_count'] ?></strong> high priority
                            </div>
                        </div>
                        <?php if ($summary['pending_interest'] > 0): ?>
                        <div>
                            <a href="courses.php?template=<?= urlencode($summary['training_type']) ?>" class="btn small secondary">
                                📚 Create Course
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Info with Version Tracking -->
        <div style="text-align: center; color: #9ca3af; font-size: 0.875rem; background: white; padding: 1rem; border-radius: 8px; margin-top: 2rem;">
            <p>🎯 Planning Dashboard v4.1.0 - Integrated Edition | Data loaded: <?= count($recentInterest) ?> interests, <?= count($plannedCourses) ?> courses</p>
            <p>Last updated: <?= date('d-m-Y H:i:s') ?> | Session: <?= $_SESSION['admin_user'] ?> | Integration Status: ✅ Active</p>
        </div>

    </div>

    <script>
        // Enhanced JavaScript with cross-module integration
        let selectedCourseId = null;
        let currentInterestId = null;

        // Bulk action enhancement
        document.querySelector('select[name="bulk_action"]').addEventListener('change', function() {
            const courseSelect = document.getElementById('bulk-course-select');
            if (this.value === 'bulk_convert') {
                courseSelect.style.display = 'inline-block';
                courseSelect.required = true;
            } else {
                courseSelect.style.display = 'none';
                courseSelect.required = false;
            }
        });

        // Select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.interest-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Enhanced conversion function with redirect capability
        function executeConversion(interestId) {
            if (!selectedCourseId) {
                alert('Please select a course first');
                return;
            }

            const button = document.getElementById('convert-btn-' + interestId);
            button.disabled = true;
            button.textContent = '⏳ Converting...';

            fetch('planning.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=convert_to_enrollment&interest_id=${interestId}&course_id=${selectedCourseId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Successfully converted to enrollment!\nCourse: ${data.course_name}\nParticipant ID: ${data.participant_id}`);
                    
                    // Offer to go to course management
                    if (confirm('Would you like to view the course details?')) {
                        window.location.href = data.redirect_url;
                    } else {
                        location.reload();
                    }
                } else {
                    alert('❌ Conversion failed: ' + data.message);
                    button.disabled = false;
                    button.textContent = '✅ Confirm Conversion';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Network error occurred');
                button.disabled = false;
                button.textContent = '✅ Confirm Conversion';
            });
        }

        // Toggle conversion panel
        function toggleConversion(interestId) {
            const panel = document.getElementById('conversion-' + interestId);
            if (panel.style.display === 'none') {
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
            document.querySelectorAll('.course-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            document.querySelector(`[data-course-id="${courseId}"]`).classList.add('selected');
            
            selectedCourseId = courseId;
            updateConvertButton(interestId);
        }

        // Update convert button state
        function updateConvertButton(interestId) {
            const button = document.getElementById('convert-btn-' + interestId);
            if (selectedCourseId) {
                button.disabled = false;
                button.textContent = '✅ Confirm Conversion';
            } else {
                button.disabled = true;
                button.textContent = '⚠️ Select Course First';
            }
        }

        // Set priority
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
                    location.reload();
                } else {
                    alert('Failed to update priority: ' + data.message);
                }
            });
        }

        // Add note
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
                        location.reload();
                    } else {
                        alert('Failed to add note: ' + data.message);
                    }
                });
            }
        }

        // Refresh data
        function refreshData() {
            location.reload();
        }

        // Auto-refresh every 3 minutes (more frequent for planning)
        setInterval(refreshData, 180000);

        console.log('🎯 Planning Dashboard v4.1.0 - Integrated Edition loaded');
        console.log('✅ Cross-module navigation active');
        console.log('✅ Enhanced conversion flow ready');
    </script>
</body>
</html>