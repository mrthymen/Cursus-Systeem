<?php
/**
 * Unified Admin Header v6.4.0
 * Consistent header and navigation for all admin pages
 * Based on courses.php v6.4.1 design system
 * Updated: 2025-06-13
 * Changes: 
 * v6.4.0 - Extracted from courses.php for reusability
 * v6.4.0 - Added dynamic navigation badges
 * v6.4.0 - Enhanced mobile responsiveness
 * v6.4.0 - Integrated admin stats system
 */

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=' . basename($_SERVER['PHP_SELF']));
    exit;
}

// Include config if not already included
if (!function_exists('getDatabase')) {
    require_once __DIR__ . '/../includes/config.php';
}

// Auto-detect current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');

/**
 * Get navigation statistics for badges
 */
function getNavigationStats($pdo) {
    try {
        $stats = [];
        
        // Planning - Pending interests
        $stats['pending_interest'] = $pdo->query("
            SELECT COUNT(*) FROM course_interest 
            WHERE status = 'pending'
        ")->fetchColumn();
        
        // Planning - High priority interests
        $stats['high_priority'] = $pdo->query("
            SELECT COUNT(*) FROM course_interest 
            WHERE status = 'pending' AND priority = 'high'
        ")->fetchColumn();
        
        // Courses - Upcoming courses
        $stats['upcoming_courses'] = $pdo->query("
            SELECT COUNT(*) FROM courses 
            WHERE active = 1 AND course_date > NOW()
        ")->fetchColumn();
        
        // Users - New users this week
        $stats['new_users_week'] = $pdo->query("
            SELECT COUNT(*) FROM users 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")->fetchColumn();
        
        // Certificates - Ready to generate
        $stats['ready_certificates'] = $pdo->query("
            SELECT COUNT(*) 
            FROM course_participants cp 
            JOIN courses c ON cp.course_id = c.id 
            WHERE cp.payment_status = 'paid' 
            AND c.course_date < NOW()
            AND NOT EXISTS (
                SELECT 1 FROM certificates 
                WHERE course_participant_id = cp.id
            )
        ")->fetchColumn();
        
        return $stats;
        
    } catch (Exception $e) {
        // Return zeros if database error
        return [
            'pending_interest' => 0,
            'high_priority' => 0,
            'upcoming_courses' => 0,
            'new_users_week' => 0,
            'ready_certificates' => 0
        ];
    }
}

// Get database connection and stats
try {
    $pdo = getDatabase();
    $nav_stats = getNavigationStats($pdo);
} catch (Exception $e) {
    $nav_stats = [
        'pending_interest' => 0,
        'high_priority' => 0,
        'upcoming_courses' => 0,
        'new_users_week' => 0,
        'ready_certificates' => 0
    ];
}

// Function to render navigation badge
function renderNavBadge($count, $type = 'default') {
    if ($count <= 0) return '';
    
    $classes = 'nav-badge';
    if ($type === 'priority') $classes .= ' priority';
    if ($type === 'success') $classes .= ' success';
    
    return "<span class=\"{$classes}\">{$count}</span>";
}

// Get admin user info
$admin_user = $_SESSION['admin_user'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Admin') ?> - Cursus Systeem v6.4.0</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="admin_styles.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="logo-section">
                    <h1><i class="fas fa-graduation-cap"></i> Inventijn Admin</h1>
                    <span class="version-badge">v6.4.0</span>
                </div>
                
                <nav class="main-nav">
                    <a href="index.php" class="nav-item <?= $current_page === 'index' ? 'active' : '' ?>">
                        <i class="fas fa-dashboard"></i> Dashboard
                    </a>
                    
                    <a href="planning.php" class="nav-item <?= $current_page === 'planning' ? 'active' : '' ?>">
                        <i class="fas fa-calendar"></i> Planning
                        <?= renderNavBadge($nav_stats['pending_interest'], $nav_stats['high_priority'] > 0 ? 'priority' : 'default') ?>
                    </a>
                    
                    <a href="courses.php" class="nav-item <?= $current_page === 'courses' ? 'active' : '' ?>">
                        <i class="fas fa-book"></i> Cursussen
                        <?= renderNavBadge($nav_stats['upcoming_courses']) ?>
                    </a>
                    
                    <a href="users.php" class="nav-item <?= $current_page === 'users' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> Gebruikers
                        <?= renderNavBadge($nav_stats['new_users_week'], 'success') ?>
                    </a>
                    
                    <a href="certificates.php" class="nav-item <?= $current_page === 'certificates' ? 'active' : '' ?>">
                        <i class="fas fa-certificate"></i> Certificaten
                        <?= renderNavBadge($nav_stats['ready_certificates']) ?>
                    </a>
                </nav>
                
                <div class="admin-user">
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($admin_user) ?></div>
                        <div class="user-time"><?= date('d M Y H:i') ?></div>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Uitloggen
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content Container -->
        <div class="main-content">
            <div class="container"><?php
// Rest of page content goes here
// Page-specific content should come after including this header
?>