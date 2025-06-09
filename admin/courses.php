<?php
/**
 * Inventijn Course Management v4.1.1
 * Now with unified admin template integration + defensive paths
 * Previous: v2.4.0 (standalone) → Current: v4.1.1 (integrated)
 * File size: ~50.4KB → Enhanced with template integration
 * Updated: 2025-06-09
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=courses.php');
    exit;
}

// Defensive path handling - learned from certificates.php v4.1.1
$possible_paths = [
    '../includes/',
    './includes/',
    'includes/',
    '../../includes/'
];

$template_included = false;
$config_included = false;

// Try to find and include admin_template.php
foreach ($possible_paths as $path) {
    if (file_exists($path . 'admin_template.php') && !$template_included) {
        require_once $path . 'admin_template.php';
        $template_included = true;
        break;
    }
}

// Try to find and include config.php
foreach ($possible_paths as $path) {
    if (file_exists($path . 'config.php') && !$config_included) {
        require_once $path . 'config.php';
        $config_included = true;
        break;
    }
}

// Fallback if unified template not available
if (!$template_included || !function_exists('renderAdminHeader')) {
    // Minimal fallback HTML with course-specific navigation
    echo '<!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Course Management - Inventijn Admin</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --inventijn-primary: #3e5cc6;
                --inventijn-secondary: #6b80e8;
                --inventijn-success: #10b981;
                --inventijn-warning: #f59e0b;
                --inventijn-danger: #ef4444;
            }
            body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f8fafc; }
            .header { background: linear-gradient(135deg, var(--inventijn-primary) 0%, var(--inventijn-secondary) 100%); color: white; padding: 1rem 2rem; margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(62, 92, 198, 0.3); }
            .nav { display: flex; gap: 1rem; align-items: center; margin-top: 1rem; }
            .nav a { color: rgba(255,255,255,0.9); margin-right: 1rem; text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s ease; }
            .nav a:hover { background: rgba(255,255,255,0.15); color: white; }
            .nav a.active { background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.3); }
            .content { max-width: 1400px; margin: 0 auto; padding: 0 2rem; }
            .btn { background: var(--inventijn-primary); color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; margin-right: 0.5rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease; }
            .btn:hover { background: var(--inventijn-secondary); transform: translateY(-1px); }
            .btn-success { background: var(--inventijn-success); }
            .btn-warning { background: var(--inventijn-warning); }
            .btn-danger { background: var(--inventijn-danger); }
            .course-card { background: white; padding: 1.5rem; margin: 1rem 0; border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-left: 4px solid var(--inventijn-primary); transition: all 0.3s ease; }
            .course-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
            .success { background: #dcfce7; color: #166534; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; border: 1px solid #bbf7d0; }
            .error { background: #fef2f2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; border: 1px solid #fecaca; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 2rem 0; }
            .stat-card { background: white; padding: 1.5rem; border-radius: 1rem; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-left: 4px solid var(--inventijn-primary); }
            .stat-value { font-size: 2rem; font-weight: bold; color: var(--inventijn-primary); }
            .stat-label { color: #64748b; margin-top: 0.5rem; }
            .form-group { margin-bottom: 1rem; }
            .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
            .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 0.5rem; font-size: 1rem; }
            .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--inventijn-primary); }
            .participants-section { background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem; }
            .participant-item { background: white; padding: 0.75rem; margin: 0.5rem 0; border-radius: 0.375rem; border-left: 3px solid var(--inventijn-success); }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><i class="fas fa-graduation-cap"></i> Inventijn Admin</h1>
            <div class="nav">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="planning.php"><i class="fas fa-clipboard-list"></i> Planning</a>
                <a href="courses.php" class="active"><i class="fas fa-book"></i> Cursussen</a>
                <a href="users.php"><i class="fas fa-users"></i> Gebruikers</a>
                <a href="certificates.php"><i class="fas fa-certificate"></i> Certificaten</a>
            </div>
        </div>
        <div class="content">';
    
    // Simple renderPageHeader fallback
    function renderPageHeader($title, $breadcrumb = null) {
        echo '<h2 style="color: #1e293b; margin-bottom: 0.5rem;">' . htmlspecialchars($title) . '</h2>';
        if ($breadcrumb) echo '<p style="color: #64748b; font-size: 0.9rem;">' . $breadcrumb . '</p>';
    }
    
    // Simple renderAdminFooter fallback
    function renderAdminFooter() {
        echo '</div></body></html>';
    }
}

// Database connections with error handling
try {
    if (function_exists('getDatabase')) {
        $pdo = getDatabase();
    } else {
        throw new Exception("Database configuration not available");
    }
} catch (Exception $e) {
    echo '<div class="error">Database connection failed: ' . $e->getMessage() . '</div>';
    echo '<p>Please check your database configuration in includes/config.php</p>';
    exit;
}

// Handle course actions (preserve existing functionality)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_course':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO courses (course_name, course_description, course_date, course_time, duration_hours, max_participants, price, instructor, location, active, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    
                    $result = $stmt->execute([
                        $_POST['course_name'],
                        $_POST['course_description'],
                        $_POST['course_date'],
                        $_POST['course_time'],
                        (int)$_POST['duration_hours'],
                        (int)$_POST['max_participants'],
                        (float)$_POST['price'],
                        $_POST['instructor'],
                        $_POST['location']
                    ]);
                    
                    if ($result) {
                        $_SESSION['admin_message'] = [
                            'text' => 'Course created successfully!',
                            'type' => 'success'
                        ];
                    } else {
                        $_SESSION['admin_message'] = [
                            'text' => 'Failed to create course.',
                            'type' => 'error'
                        ];
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = [
                        'text' => 'Error creating course: ' . $e->getMessage(),
                        'type' => 'error'
                    ];
                }
                break;
                
            case 'update_course':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE courses SET 
                            course_name = ?, course_description = ?, course_date = ?, course_time = ?, 
                            duration_hours = ?, max_participants = ?, price = ?, instructor = ?, location = ?
                        WHERE id = ?
                    ");
                    
                    $result = $stmt->execute([
                        $_POST['course_name'],
                        $_POST['course_description'],
                        $_POST['course_date'],
                        $_POST['course_time'],
                        (int)$_POST['duration_hours'],
                        (int)$_POST['max_participants'],
                        (float)$_POST['price'],
                        $_POST['instructor'],
                        $_POST['location'],
                        (int)$_POST['course_id']
                    ]);
                    
                    if ($result) {
                        $_SESSION['admin_message'] = [
                            'text' => 'Course updated successfully!',
                            'type' => 'success'
                        ];
                    } else {
                        $_SESSION['admin_message'] = [
                            'text' => 'Failed to update course.',
                            'type' => 'error'
                        ];
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = [
                        'text' => 'Error updating course: ' . $e->getMessage(),
                        'type' => 'error'
                    ];
                }
                break;
                
            case 'delete_course':
                try {
                    $course_id = (int)$_POST['course_id'];
                    
                    // Check if course has participants
                    $participant_count = $pdo->prepare("SELECT COUNT(*) FROM course_participants WHERE course_id = ?");
                    $participant_count->execute([$course_id]);
                    $count = $participant_count->fetchColumn();
                    
                    if ($count > 0) {
                        $_SESSION['admin_message'] = [
                            'text' => 'Cannot delete course with enrolled participants. Please remove participants first.',
                            'type' => 'error'
                        ];
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                        $result = $stmt->execute([$course_id]);
                        
                        if ($result) {
                            $_SESSION['admin_message'] = [
                                'text' => 'Course deleted successfully!',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['admin_message'] = [
                                'text' => 'Failed to delete course.',
                                'type' => 'error'
                            ];
                        }
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = [
                        'text' => 'Error deleting course: ' . $e->getMessage(),
                        'type' => 'error'
                    ];
                }
                break;
                
            case 'update_participant_payment':
                try {
                    $stmt = $pdo->prepare("UPDATE course_participants SET payment_status = ?, payment_date = NOW() WHERE id = ?");
                    $result = $stmt->execute([$_POST['payment_status'], (int)$_POST['participant_id']]);
                    
                    if ($result) {
                        $_SESSION['admin_message'] = [
                            'text' => 'Payment status updated successfully!',
                            'type' => 'success'
                        ];
                    } else {
                        $_SESSION['admin_message'] = [
                            'text' => 'Failed to update payment status.',
                            'type' => 'error'
                        ];
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = [
                        'text' => 'Error updating payment: ' . $e->getMessage(),
                        'type' => 'error'
                    ];
                }
                break;
        }
        
        header('Location: courses.php');
        exit;
    }
}

// Get course statistics for enhanced dashboard
try {
    $course_stats = [
        'total_courses' => $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
        'active_courses' => $pdo->query("SELECT COUNT(*) FROM courses WHERE active = 1 AND course_date > NOW()")->fetchColumn(),
        'total_participants' => $pdo->query("SELECT COUNT(*) FROM course_participants")->fetchColumn(),
        'revenue_month' => $pdo->query("
            SELECT COALESCE(SUM(c.price), 0) 
            FROM course_participants cp 
            JOIN courses c ON cp.course_id = c.id 
            WHERE cp.payment_status = 'paid' 
            AND MONTH(cp.payment_date) = MONTH(NOW()) 
            AND YEAR(cp.payment_date) = YEAR(NOW())
        ")->fetchColumn(),
        'pending_payments' => $pdo->query("SELECT COUNT(*) FROM course_participants WHERE payment_status = 'pending'")->fetchColumn()
    ];
} catch (Exception $e) {
    $course_stats = ['total_courses' => 0, 'active_courses' => 0, 'total_participants' => 0, 'revenue_month' => 0, 'pending_payments' => 0];
}

// Get all courses with participant information
try {
    $courses_query = "
        SELECT 
            c.*,
            COUNT(cp.id) as participant_count,
            SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_participants,
            SUM(CASE WHEN cp.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_participants,
            COALESCE(SUM(CASE WHEN cp.payment_status = 'paid' THEN c.price ELSE 0 END), 0) as course_revenue
        FROM courses c
        LEFT JOIN course_participants cp ON c.id = cp.course_id
        GROUP BY c.id
        ORDER BY c.course_date DESC
    ";
    
    $courses = $pdo->query($courses_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo '<div class="error">Error loading courses: ' . $e->getMessage() . '</div>';
    $courses = [];
}

// Handle edit mode
$editing_course = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editing_course = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo '<div class="error">Error loading course for editing: ' . $e->getMessage() . '</div>';
    }
}

// Render header (unified or fallback)
if (function_exists('renderAdminHeader')) {
    renderAdminHeader('Course Management', $pdo);
} else {
    // Fallback header already rendered above
}

renderPageHeader('Course Management', '<a href="index.php">Dashboard</a> > Courses');

// Display session messages
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    echo '<div class="' . $message['type'] . '">' . htmlspecialchars($message['text']) . '</div>';
    unset($_SESSION['admin_message']);
}

?>

<!-- Course Statistics Dashboard -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $course_stats['total_courses'] ?></div>
        <div class="stat-label">Total Courses</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $course_stats['active_courses'] ?></div>
        <div class="stat-label">Active Courses</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $course_stats['total_participants'] ?></div>
        <div class="stat-label">Total Participants</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">€<?= number_format($course_stats['revenue_month'], 2) ?></div>
        <div class="stat-label">Revenue This Month</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $course_stats['pending_payments'] ?></div>
        <div class="stat-label">Pending Payments</div>
    </div>
</div>

<!-- Course Creation/Edit Form -->
<div class="course-card">
    <h3><i class="fas fa-plus"></i> <?= $editing_course ? 'Edit Course' : 'Create New Course' ?></h3>
    
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editing_course ? 'update_course' : 'create_course' ?>">
        <?php if ($editing_course): ?>
            <input type="hidden" name="course_id" value="<?= $editing_course['id'] ?>">
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="course_name">Course Name</label>
                <input type="text" id="course_name" name="course_name" 
                       value="<?= htmlspecialchars($editing_course['course_name'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="instructor">Instructor</label>
                <input type="text" id="instructor" name="instructor" 
                       value="<?= htmlspecialchars($editing_course['instructor'] ?? '') ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="course_description">Course Description</label>
            <textarea id="course_description" name="course_description" rows="3" required><?= htmlspecialchars($editing_course['course_description'] ?? '') ?></textarea>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="course_date">Date</label>
                <input type="date" id="course_date" name="course_date" 
                       value="<?= $editing_course['course_date'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="course_time">Time</label>
                <input type="time" id="course_time" name="course_time" 
                       value="<?= $editing_course['course_time'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="duration_hours">Duration (hours)</label>
                <input type="number" id="duration_hours" name="duration_hours" min="1" max="24"
                       value="<?= $editing_course['duration_hours'] ?? '8' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="max_participants">Max Participants</label>
                <input type="number" id="max_participants" name="max_participants" min="1"
                       value="<?= $editing_course['max_participants'] ?? '20' ?>" required>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="price">Price (€)</label>
                <input type="number" id="price" name="price" step="0.01" min="0"
                       value="<?= $editing_course['price'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" 
                       value="<?= htmlspecialchars($editing_course['location'] ?? '') ?>" required>
            </div>
        </div>
        
        <button type="submit" class="btn">
            <i class="fas fa-save"></i> <?= $editing_course ? 'Update Course' : 'Create Course' ?>
        </button>
        
        <?php if ($editing_course): ?>
            <a href="courses.php" class="btn btn-warning">
                <i class="fas fa-times"></i> Cancel Edit
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Courses List -->
<h3><i class="fas fa-list"></i> All Courses</h3>

<?php if (empty($courses)): ?>
    <div style="text-align: center; padding: 3rem; color: #64748b;">
        <i class="fas fa-book" style="font-size: 3rem; margin-bottom: 1rem; color: #e5e7eb;"></i>
        <h3>No courses created yet</h3>
        <p>Create your first course using the form above.</p>
    </div>
<?php else: ?>
    <?php foreach ($courses as $course): ?>
        <div class="course-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                <div>
                    <h4 style="margin: 0; color: #1e293b;"><?= htmlspecialchars($course['course_name']) ?></h4>
                    <p style="margin: 0.25rem 0; color: #64748b;">
                        <i class="fas fa-user-tie"></i> <?= htmlspecialchars($course['instructor']) ?> | 
                        <i class="fas fa-calendar"></i> <?= date('d-m-Y', strtotime($course['course_date'])) ?> <?= date('H:i', strtotime($course['course_time'])) ?> |
                        <i class="fas fa-clock"></i> <?= $course['duration_hours'] ?>h |
                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($course['location']) ?>
                    </p>
                </div>
                <div style="text-align: right;">
                    <span style="background: <?= $course['active'] ? '#10b981' : '#6b7280' ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.8rem;">
                        <?= $course['active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>
            
            <p style="margin-bottom: 1rem; color: #374151;"><?= nl2br(htmlspecialchars($course['course_description'])) ?></p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem; background: #f8fafc; padding: 1rem; border-radius: 0.5rem;">
                <div>
                    <strong>Participants:</strong><br>
                    <?= $course['participant_count'] ?>/<?= $course['max_participants'] ?>
                </div>
                <div>
                    <strong>Paid:</strong><br>
                    <span style="color: #10b981;"><?= $course['paid_participants'] ?></span>
                </div>
                <div>
                    <strong>Pending:</strong><br>
                    <span style="color: #f59e0b;"><?= $course['pending_participants'] ?></span>
                </div>
                <div>
                    <strong>Revenue:</strong><br>
                    €<?= number_format($course['course_revenue'], 2) ?>
                </div>
                <div>
                    <strong>Price:</strong><br>
                    €<?= number_format($course['price'], 2) ?>
                </div>
            </div>
            
            <?php if ($course['participant_count'] > 0): ?>
                <div class="participants-section">
                    <strong><i class="fas fa-users"></i> Participants:</strong>
                    <?php
                    try {
                        $participants_query = "
                            SELECT cp.*, u.name, u.email 
                            FROM course_participants cp 
                            JOIN users u ON cp.user_id = u.id 
                            WHERE cp.course_id = ?
                            ORDER BY cp.enrollment_date DESC
                        ";
                        $stmt = $pdo->prepare($participants_query);
                        $stmt->execute([$course['id']]);
                        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($participants as $participant):
                    ?>
                        <div class="participant-item">
                            <strong><?= htmlspecialchars($participant['name']) ?></strong> 
                            - <?= htmlspecialchars($participant['email']) ?>
                            <span style="float: right;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_participant_payment">
                                    <input type="hidden" name="participant_id" value="<?= $participant['id'] ?>">
                                    <select name="payment_status" onchange="this.form.submit()" style="padding: 0.25rem; border-radius: 0.25rem; border: 1px solid #d1d5db;">
                                        <option value="pending" <?= $participant['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="paid" <?= $participant['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="cancelled" <?= $participant['payment_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </form>
                            </span>
                        </div>
                    <?php
                        endforeach;
                    } catch (Exception $e) {
                        echo '<p style="color: #dc2626;">Error loading participants: ' . $e->getMessage() . '</p>';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <div style="display: flex; gap: 0.75rem; align-items: center; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                <a href="courses.php?edit=<?= $course['id'] ?>" class="btn">
                    <i class="fas fa-edit"></i> Edit Course
                </a>
                
                <?php if ($course['participant_count'] == 0): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course?')">
                        <input type="hidden" name="action" value="delete_course">
                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Course
                        </button>
                    </form>
                <?php endif; ?>
                
                <a href="certificates.php?course_id=<?= $course['id'] ?>" class="btn btn-success">
                    <i class="fas fa-certificate"></i> View Certificates
                </a>
                
                <?php if ($course['course_date'] < date('Y-m-d') && $course['paid_participants'] > 0): ?>
                    <span style="background: #10b981; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.9rem;">
                        <i class="fas fa-check"></i> Course Completed
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
// Enhanced JavaScript for course management
document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-refresh course stats every 30 seconds
    function refreshCourseStats() {
        fetch(window.location.href + '?ajax=stats')
            .then(response => response.json())
            .then(data => {
                // Update course counts in navigation and dashboard
                const statCards = document.querySelectorAll('.stat-value');
                if (data.active_courses !== undefined && statCards[1]) {
                    statCards[1].textContent = data.active_courses;
                }
                if (data.total_participants !== undefined && statCards[2]) {
                    statCards[2].textContent = data.total_participants;
                }
            })
            .catch(err => console.log('Stats refresh failed:', err));
    }
    
    // Refresh every 30 seconds
    setInterval(refreshCourseStats, 30000);
    
    // Add smooth form submission feedback
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.innerHTML.includes('spinner')) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
                
                // Re-enable after 3 seconds as fallback
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    });
    
    // Enhanced date/time validation
    const dateInput = document.getElementById('course_date');
    const timeInput = document.getElementById('course_time');
    
    if (dateInput) {
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
    }
    
    // Auto-fill time if not set
    if (timeInput && !timeInput.value) {
        timeInput.value = '09:00';
    }
});

// Handle AJAX stats requests for real-time updates
<?php if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats'): ?>
<?php
header('Content-Type: application/json');
echo json_encode($course_stats);
exit;
?>
<?php endif; ?>
</script>

<?php renderAdminFooter(); ?>