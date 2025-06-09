<?php
/**
 * Inventijn Course Management v4.1.2
 * CONSERVATIVE integration - minimal changes to preserve existing UI
 * Previous: v4.1.1 (broken layout) → Current: v4.1.2 (conservative)
 * Strategy: Only add navigation, keep ALL existing styling and structure
 * Updated: 2025-06-09
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=courses.php');
    exit;
}

// Try to include admin template for navigation only
$template_included = false;
$possible_paths = ['../includes/', './includes/', 'includes/'];

foreach ($possible_paths as $path) {
    if (file_exists($path . 'admin_template.php') && !$template_included) {
        require_once $path . 'admin_template.php';
        $template_included = true;
        break;
    }
}

// Include config
foreach ($possible_paths as $path) {
    if (file_exists($path . 'config.php')) {
        require_once $path . 'config.php';
        break;
    }
}

// Get database connection
try {
    $pdo = getDatabase();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// =====================================
// PRESERVE ALL EXISTING FUNCTIONALITY
// =====================================

// Handle all existing course actions (keep original logic)
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
                        $_SESSION['message'] = 'Course created successfully!';
                        $_SESSION['message_type'] = 'success';
                    }
                } catch (Exception $e) {
                    $_SESSION['message'] = 'Error creating course: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'error';
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
                        $_SESSION['message'] = 'Course updated successfully!';
                        $_SESSION['message_type'] = 'success';
                    }
                } catch (Exception $e) {
                    $_SESSION['message'] = 'Error updating course: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'error';
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
                        $_SESSION['message'] = 'Cannot delete course with enrolled participants.';
                        $_SESSION['message_type'] = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                        $result = $stmt->execute([$course_id]);
                        
                        if ($result) {
                            $_SESSION['message'] = 'Course deleted successfully!';
                            $_SESSION['message_type'] = 'success';
                        }
                    }
                } catch (Exception $e) {
                    $_SESSION['message'] = 'Error deleting course: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'error';
                }
                break;
                
            case 'update_participant_payment':
                try {
                    $stmt = $pdo->prepare("UPDATE course_participants SET payment_status = ?, payment_date = NOW() WHERE id = ?");
                    $result = $stmt->execute([$_POST['payment_status'], (int)$_POST['participant_id']]);
                    
                    if ($result) {
                        $_SESSION['message'] = 'Payment status updated!';
                        $_SESSION['message_type'] = 'success';
                    }
                } catch (Exception $e) {
                    $_SESSION['message'] = 'Error updating payment: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'error';
                }
                break;
        }
        
        header('Location: courses.php');
        exit;
    }
}

// Get all courses with participant info (preserve original query structure)
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
    $courses = [];
    $_SESSION['message'] = 'Error loading courses: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

// Handle edit mode (preserve original logic)
$editing_course = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editing_course = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error loading course for editing: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

// =====================================
// RENDER HTML - CONSERVATIVE APPROACH
// =====================================

// Only render unified header if template available, otherwise use original header
if ($template_included && function_exists('renderAdminHeader')) {
    // Use unified navigation
    renderAdminHeader('Course Management', $pdo);
    echo '<div style="max-width: 1400px; margin: 0 auto; padding: 0 2rem;">';
    echo '<h2 style="color: #1e293b; margin-bottom: 1rem;">Course Management</h2>';
} else {
    // Use original header style
    echo '<!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <title>Course Management - Inventijn</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .header { background: #3e5cc6; color: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
            .nav a { color: white; margin-right: 15px; text-decoration: none; }
            .nav a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Inventijn Course Management</h1>
            <div class="nav">
                <a href="index.php">Dashboard</a>
                <a href="planning.php">Planning</a>
                <a href="courses.php" style="font-weight: bold;">Cursussen</a>
                <a href="users.php">Gebruikers</a>
                <a href="certificates.php">Certificaten</a>
            </div>
        </div>';
}

// Display messages (preserve original styling approach)
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message_type'] ?? 'info';
    $bg_color = $msg_type === 'success' ? '#d4edda' : '#f8d7da';
    $text_color = $msg_type === 'success' ? '#155724' : '#721c24';
    
    echo "<div style='background: $bg_color; color: $text_color; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
    echo htmlspecialchars($_SESSION['message']);
    echo "</div>";
    
    unset($_SESSION['message'], $_SESSION['message_type']);
}

?>

<!-- PRESERVE ORIGINAL STYLING - Minimal CSS that doesn't conflict -->
<style>
/* Only add essential non-conflicting styles */
.course-form {
    background: white;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.course-item {
    background: white;
    padding: 20px;
    margin: 15px 0;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    border-left: 4px solid #3e5cc6;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.btn {
    background: #3e5cc6;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin-right: 10px;
    font-size: 14px;
}

.btn:hover {
    background: #2d4aa7;
}

.btn-danger {
    background: #dc3545;
}

.btn-success {
    background: #28a745;
}

.participant-list {
    background: #f8f9fa;
    padding: 15px;
    margin-top: 15px;
    border-radius: 5px;
}

.participant-item {
    background: white;
    padding: 10px;
    margin: 8px 0;
    border-radius: 4px;
    border-left: 3px solid #28a745;
}
</style>

<!-- Course Creation/Edit Form - PRESERVE ORIGINAL STRUCTURE -->
<div class="course-form">
    <h3><?= $editing_course ? 'Edit Course' : 'Create New Course' ?></h3>
    
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editing_course ? 'update_course' : 'create_course' ?>">
        <?php if ($editing_course): ?>
            <input type="hidden" name="course_id" value="<?= $editing_course['id'] ?>">
        <?php endif; ?>
        
        <div class="form-row">
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
        
        <div class="form-row">
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
        
        <div class="form-row">
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
            <?= $editing_course ? 'Update Course' : 'Create Course' ?>
        </button>
        
        <?php if ($editing_course): ?>
            <a href="courses.php" class="btn" style="background: #6c757d;">Cancel Edit</a>
        <?php endif; ?>
    </form>
</div>

<!-- Courses List - PRESERVE ORIGINAL LAYOUT -->
<h3>All Courses</h3>

<?php if (empty($courses)): ?>
    <div style="text-align: center; padding: 50px; color: #666;">
        <p>No courses created yet. Use the form above to create your first course.</p>
    </div>
<?php else: ?>
    <?php foreach ($courses as $course): ?>
        <div class="course-item">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                <div>
                    <h4 style="margin: 0; color: #333;"><?= htmlspecialchars($course['course_name']) ?></h4>
                    <p style="margin: 5px 0; color: #666;">
                        <strong>Instructor:</strong> <?= htmlspecialchars($course['instructor']) ?> | 
                        <strong>Date:</strong> <?= date('d-m-Y', strtotime($course['course_date'])) ?> <?= date('H:i', strtotime($course['course_time'])) ?> |
                        <strong>Duration:</strong> <?= $course['duration_hours'] ?>h |
                        <strong>Location:</strong> <?= htmlspecialchars($course['location']) ?>
                    </p>
                </div>
                <span style="background: <?= $course['active'] ? '#28a745' : '#6c757d' ?>; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px;">
                    <?= $course['active'] ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            
            <p style="margin-bottom: 15px; color: #555;"><?= nl2br(htmlspecialchars($course['course_description'])) ?></p>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">
                    <div>
                        <strong>Participants:</strong><br>
                        <?= $course['participant_count'] ?>/<?= $course['max_participants'] ?>
                    </div>
                    <div>
                        <strong>Paid:</strong><br>
                        <span style="color: #28a745;"><?= $course['paid_participants'] ?></span>
                    </div>
                    <div>
                        <strong>Pending:</strong><br>
                        <span style="color: #ffc107;"><?= $course['pending_participants'] ?></span>
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
            </div>
            
            <?php if ($course['participant_count'] > 0): ?>
                <div class="participant-list">
                    <strong>Participants:</strong>
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
                                    <select name="payment_status" onchange="this.form.submit()" style="padding: 3px;">
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
                        echo '<p style="color: #dc3545;">Error loading participants: ' . $e->getMessage() . '</p>';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                <a href="courses.php?edit=<?= $course['id'] ?>" class="btn">Edit Course</a>
                
                <?php if ($course['participant_count'] == 0): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course?')">
                        <input type="hidden" name="action" value="delete_course">
                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                        <button type="submit" class="btn btn-danger">Delete Course</button>
                    </form>
                <?php endif; ?>
                
                <a href="certificates.php?course_id=<?= $course['id'] ?>" class="btn btn-success">View Certificates</a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php 
// Close HTML properly
if ($template_included && function_exists('renderAdminFooter')) {
    echo '</div>'; // Close container
    renderAdminFooter();
} else {
    echo '</body></html>';
}
?>