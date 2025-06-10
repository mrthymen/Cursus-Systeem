<?php
/**
 * Cursus Systeem - Course Management v6.0.1
 * Clean foundation - no integration complexity
 * Strategy: Make core functionality bulletproof first
 * Updated: 2025-06-10
 * Changes: 
 * - Simplified architecture (no template integration)
 * - Clean CSS (no conflicts)
 * - Verified database schema
 * - Proper error handling
 * - Version tracking fixed
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=courses.php');
    exit;
}

// Include config with error handling
if (!file_exists('config.php')) {
    die('Config file not found. Please ensure config.php exists.');
}
require_once 'config.php';

// Get database connection
try {
    $pdo = getDatabase();
    
    // Test database connection and verify table structure
    $test_query = $pdo->query("SHOW TABLES LIKE 'courses'");
    if (!$test_query->fetch()) {
        throw new Exception('Courses table does not exist. Please run database setup.');
    }
    
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_course':
                try {
                    // Validate required fields
                    $required = ['course_name', 'course_description', 'course_date', 'course_time', 'duration_hours', 'max_participants', 'price', 'instructor', 'location'];
                    foreach ($required as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Field '$field' is required.");
                        }
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO courses (course_name, course_description, course_date, course_time, duration_hours, max_participants, price, instructor, location, active, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    
                    $result = $stmt->execute([
                        trim($_POST['course_name']),
                        trim($_POST['course_description']),
                        $_POST['course_date'],
                        $_POST['course_time'],
                        (int)$_POST['duration_hours'],
                        (int)$_POST['max_participants'],
                        (float)$_POST['price'],
                        trim($_POST['instructor']),
                        trim($_POST['location'])
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
                    // Validate required fields
                    $required = ['course_id', 'course_name', 'course_description', 'course_date', 'course_time', 'duration_hours', 'max_participants', 'price', 'instructor', 'location'];
                    foreach ($required as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Field '$field' is required.");
                        }
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE courses SET
                            course_name = ?, course_description = ?, course_date = ?, course_time = ?,
                            duration_hours = ?, max_participants = ?, price = ?, instructor = ?, location = ?
                        WHERE id = ?
                    ");
                    
                    $result = $stmt->execute([
                        trim($_POST['course_name']),
                        trim($_POST['course_description']),
                        $_POST['course_date'],
                        $_POST['course_time'],
                        (int)$_POST['duration_hours'],
                        (int)$_POST['max_participants'],
                        (float)$_POST['price'],
                        trim($_POST['instructor']),
                        trim($_POST['location']),
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

// Get all courses with participant info
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

// Handle edit mode
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
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - Cursus Systeem v6.0.1</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Clean v6.0 Design System */
        :root {
            --primary: #2563eb;
            --success: #059669;
            --warning: #d97706;
            --error: #dc2626;
            --neutral: #6b7280;
            --background: #f9fafb;
            --surface: #ffffff;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background);
            color: #333;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header */
        .header {
            background: var(--primary);
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .nav {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .nav a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .nav a:hover,
        .nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .message.success {
            background: #ecfdf5;
            color: #065f46;
            border-color: var(--success);
        }

        .message.error {
            background: #fef2f2;
            color: #991b1b;
            border-color: var(--error);
        }

        /* Cards */
        .card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .card-header h3 {
            color: #1f2937;
            font-size: 1.2rem;
        }

        /* Forms */
        .form-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        input, select, textarea {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Course items */
        .course-item {
            border-left: 4px solid var(--primary);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .course-status {
            background: var(--success);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .course-status.inactive {
            background: var(--neutral);
        }

        .course-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }

        .meta-item {
            text-align: center;
        }

        .meta-label {
            font-size: 12px;
            color: var(--neutral);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .meta-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }

        /* Participants */
        .participants-section {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
        }

        .participant-item {
            background: white;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 3px solid var(--success);
        }

        .participant-info {
            flex: 1;
        }

        .participant-name {
            font-weight: 600;
            color: #1f2937;
        }

        .participant-email {
            color: var(--neutral);
            font-size: 14px;
        }

        .payment-select {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 12px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .nav {
                flex-direction: column;
            }
            
            .course-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .course-meta {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-graduation-cap"></i> Course Management</h1>
            <div class="nav">
                <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="planning.php"><i class="fas fa-calendar"></i> Planning</a>
                <a href="courses.php" class="active"><i class="fas fa-book"></i> Courses</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="certificates.php"><i class="fas fa-certificate"></i> Certificates</a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?= $_SESSION['message_type'] ?? 'info' ?>">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- Course Form -->
        <div class="card">
            <div class="card-header">
                <h3><?= $editing_course ? 'Edit Course' : 'Create New Course' ?></h3>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editing_course ? 'update_course' : 'create_course' ?>">
                <?php if ($editing_course): ?>
                    <input type="hidden" name="course_id" value="<?= $editing_course['id'] ?>">
                <?php endif; ?>
                
                <div class="form-grid">
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
                    
                    <div class="form-group full-width">
                        <label for="course_description">Course Description</label>
                        <textarea id="course_description" name="course_description" required><?= htmlspecialchars($editing_course['course_description'] ?? '') ?></textarea>
                    </div>
                    
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
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <?= $editing_course ? 'Update Course' : 'Create Course' ?>
                    </button>
                    
                    <?php if ($editing_course): ?>
                        <a href="courses.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Courses List -->
        <div class="card">
            <div class="card-header">
                <h3>All Courses (<?= count($courses) ?>)</h3>
            </div>

            <?php if (empty($courses)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--neutral);">
                    <i class="fas fa-book fa-3x" style="margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>No courses created yet. Use the form above to create your first course.</p>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <div class="card course-item">
                        <div class="course-header">
                            <div>
                                <h4 style="color: #1f2937; margin-bottom: 0.5rem;">
                                    <?= htmlspecialchars($course['course_name']) ?>
                                </h4>
                                <p style="color: var(--neutral); font-size: 14px;">
                                    <strong>Instructor:</strong> <?= htmlspecialchars($course['instructor']) ?> | 
                                    <strong>Date:</strong> <?= date('d-m-Y', strtotime($course['course_date'])) ?> <?= date('H:i', strtotime($course['course_time'])) ?> | 
                                    <strong>Duration:</strong> <?= $course['duration_hours'] ?>h | 
                                    <strong>Location:</strong> <?= htmlspecialchars($course['location']) ?>
                                </p>
                            </div>
                            <span class="course-status <?= $course['active'] ? '' : 'inactive' ?>">
                                <?= $course['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        
                        <p style="margin-bottom: 1rem; color: #555;">
                            <?= nl2br(htmlspecialchars($course['course_description'])) ?>
                        </p>
                        
                        <div class="course-meta">
                            <div class="meta-item">
                                <div class="meta-label">Participants</div>
                                <div class="meta-value"><?= $course['participant_count'] ?>/<?= $course['max_participants'] ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Paid</div>
                                <div class="meta-value" style="color: var(--success);"><?= $course['paid_participants'] ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Pending</div>
                                <div class="meta-value" style="color: var(--warning);"><?= $course['pending_participants'] ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Revenue</div>
                                <div class="meta-value">€<?= number_format($course['course_revenue'], 2) ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Price</div>
                                <div class="meta-value">€<?= number_format($course['price'], 2) ?></div>
                            </div>
                        </div>
                        
                        <?php if ($course['participant_count'] > 0): ?>
                            <div class="participants-section">
                                <strong style="margin-bottom: 1rem; display: block;">Participants:</strong>
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
                                        <div class="participant-info">
                                            <div class="participant-name"><?= htmlspecialchars($participant['name']) ?></div>
                                            <div class="participant-email"><?= htmlspecialchars($participant['email']) ?></div>
                                        </div>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_participant_payment">
                                            <input type="hidden" name="participant_id" value="<?= $participant['id'] ?>">
                                            <select name="payment_status" onchange="this.form.submit()" class="payment-select">
                                                <option value="pending" <?= $participant['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="paid" <?= $participant['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                                <option value="cancelled" <?= $participant['payment_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </div>
                                <?php
                                    endforeach;
                                } catch (Exception $e) {
                                    echo '<p style="color: var(--error);">Error loading participants: ' . $e->getMessage() . '</p>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="btn-group">
                            <a href="courses.php?edit=<?= $course['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            
                            <?php if ($course['participant_count'] == 0): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course?')">
                                    <input type="hidden" name="action" value="delete_course">
                                    <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="certificates.php?course_id=<?= $course['id'] ?>" class="btn btn-success">
                                <i class="fas fa-certificate"></i> Certificates
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>