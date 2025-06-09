<?php
/**
 * Inventijn Admin Template System v4.1.0
 * Unified header, navigation, and data integration
 * Created: 2025-06-09
 * Creator: Martijn en Claude
 */

// Require config for database access
require_once __DIR__ . '/config.php';

// Auto-detect current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');

/**
 * Get real-time admin statistics for navigation badges
 */
function getAdminStats($pdo) {
    try {
        return [
            'pending_interest' => $pdo->query("SELECT COUNT(*) FROM course_interest WHERE status = 'pending'")->fetchColumn(),
            'active_courses' => $pdo->query("SELECT COUNT(*) FROM courses WHERE active = 1 AND course_date > NOW()")->fetchColumn(),
            'new_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
            'pending_certificates' => $pdo->query("
                SELECT COUNT(*) 
                FROM course_participants cp 
                JOIN courses c ON cp.course_id = c.id 
                WHERE cp.payment_status = 'paid' 
                AND NOT EXISTS (SELECT 1 FROM certificates WHERE course_participant_id = cp.id)
            ")->fetchColumn(),
            'high_priority' => $pdo->query("SELECT COUNT(*) FROM course_interest WHERE status = 'pending' AND priority = 'high'")->fetchColumn()
        ];
    } catch (Exception $e) {
        return array_fill_keys(['pending_interest', 'active_courses', 'new_users', 'pending_certificates', 'high_priority'], 0);
    }
}

/**
 * Render admin header with unified navigation
 */
function renderAdminHeader($page_title = 'Admin Dashboard', $pdo = null) {
    if (!$pdo) {
        $pdo = getDatabase();
    }
    
    $stats = getAdminStats($pdo);
    global $current_page;
    
    echo '
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($page_title) . ' - Inventijn Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --inventijn-primary: #3e5cc6;
            --inventijn-secondary: #6b80e8;    
            --inventijn-accent: #e3a1e5;
            --inventijn-orange: #F9A03F;
            --inventijn-success: #10b981;
            --inventijn-warning: #f59e0b;
            --inventijn-danger: #ef4444;
            --inventijn-light: #f8fafc;
            --inventijn-dark: #1e293b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--inventijn-light) 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: var(--inventijn-dark);
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--inventijn-primary) 0%, var(--inventijn-secondary) 100%);
            padding: 0;
            box-shadow: 0 4px 20px rgba(62, 92, 198, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .logo-section h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .admin-nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.9);
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            border: 2px solid transparent;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .nav-badge {
            background: var(--inventijn-orange);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: bold;
            min-width: 1.5rem;
            text-align: center;
            margin-left: 0.25rem;
        }
        
        .nav-badge.high-priority {
            background: var(--inventijn-danger);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .main-content {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--inventijn-dark);
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: var(--inventijn-primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Quick stats bar */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--inventijn-primary);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--inventijn-primary);
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="header-content">
            <div class="logo-section">
                <i class="fas fa-graduation-cap" style="font-size: 2rem;"></i>
                <h1>Inventijn Admin</h1>
            </div>
            
            <nav class="admin-nav">
                <a href="index.php" class="nav-item ' . ($current_page === 'index' ? 'active' : '') . '">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                
                <a href="planning.php" class="nav-item ' . ($current_page === 'planning' ? 'active' : '') . '">
                    <i class="fas fa-clipboard-list"></i>
                    Planning
                    ' . ($stats['pending_interest'] > 0 ? '<span class="nav-badge' . ($stats['high_priority'] > 0 ? ' high-priority' : '') . '">' . $stats['pending_interest'] . '</span>' : '') . '
                </a>
                
                <a href="courses.php" class="nav-item ' . ($current_page === 'courses' ? 'active' : '') . '">
                    <i class="fas fa-book"></i>
                    Cursussen
                    ' . ($stats['active_courses'] > 0 ? '<span class="nav-badge">' . $stats['active_courses'] . '</span>' : '') . '
                </a>
                
                <a href="users.php" class="nav-item ' . ($current_page === 'users' ? 'active' : '') . '">
                    <i class="fas fa-users"></i>
                    Gebruikers
                    ' . ($stats['new_users'] > 0 ? '<span class="nav-badge">' . $stats['new_users'] . '</span>' : '') . '
                </a>
                
                <a href="certificates.php" class="nav-item ' . ($current_page === 'certificates' ? 'active' : '') . '">
                    <i class="fas fa-certificate"></i>
                    Certificaten
                    ' . ($stats['pending_certificates'] > 0 ? '<span class="nav-badge">' . $stats['pending_certificates'] . '</span>' : '') . '
                </a>
            </nav>
            
            <div class="user-section">
                <i class="fas fa-user-circle"></i>
                <span>Admin</span>
                <a href="?logout=1" style="color: rgba(255,255,255,0.8); margin-left: 1rem;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>
    
    <main class="main-content">
';
}

/**
 * Render page header with breadcrumb
 */
function renderPageHeader($title, $breadcrumb = null) {
    echo '
        <div class="page-header">
            <h1 class="page-title">' . htmlspecialchars($title) . '</h1>
            ' . ($breadcrumb ? '<div class="breadcrumb">' . $breadcrumb . '</div>' : '') . '
        </div>
    ';
}

/**
 * Render quick stats dashboard
 */
function renderQuickStats($custom_stats = null, $pdo = null) {
    if (!$pdo) {
        $pdo = getDatabase();
    }
    
    $stats = $custom_stats ?: getAdminStats($pdo);
    
    echo '
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-value">' . $stats['pending_interest'] . '</div>
                <div class="stat-label">Pending Interest</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . $stats['active_courses'] . '</div>
                <div class="stat-label">Active Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . $stats['new_users'] . '</div>
                <div class="stat-label">New Users (7d)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . $stats['pending_certificates'] . '</div>
                <div class="stat-label">Pending Certificates</div>
            </div>
        </div>
    ';
}

/**
 * Close HTML structure
 */
function renderAdminFooter() {
    echo '
    </main>
    
    <script>
        // Auto-refresh stats every 30 seconds
        function refreshStats() {
            // Get current stats via fetch
            fetch(window.location.href + "?ajax=stats")
                .then(response => response.json())
                .then(data => {
                    // Update navigation badges
                    Object.keys(data).forEach(key => {
                        const badge = document.querySelector(`[data-stat="${key}"]`);
                        if (badge) badge.textContent = data[key];
                    });
                })
                .catch(err => console.log("Stats refresh failed:", err));
        }
        
        // Refresh every 30 seconds
        setInterval(refreshStats, 30000);
        
        // Add smooth transitions
        document.querySelectorAll(".nav-item").forEach(item => {
            item.addEventListener("click", function(e) {
                if (!this.classList.contains("active")) {
                    document.body.style.opacity = "0.8";
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 150);
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
';
}

/**
 * Handle AJAX stats requests
 */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats') {
    header('Content-Type: application/json');
    $pdo = getDatabase();
    echo json_encode(getAdminStats($pdo));
    exit;
}
?>