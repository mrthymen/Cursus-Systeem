<?php
/**
 * Inventijn Unified Admin Header Component
 * Cross-module navigation with real-time stats
 * 
 * @version 4.1.0
 * @author Martijn Planken & Claude
 * @date 2025-06-09
 * @integration Ready for all admin modules
 */

// Auto-detect current page
$current_page = basename($_SERVER['PHP_SELF'], '.php');

/**
 * Get real-time admin counts for navigation badges
 */
function getAdminNavigationCounts($pdo) {
    try {
        $counts = [
            'pending_interest' => 0,
            'upcoming_courses' => 0,
            'new_users_week' => 0,
            'ready_certificates' => 0,
            'high_priority' => 0
        ];
        
        // Pending interest
        $counts['pending_interest'] = $pdo->query("
            SELECT COUNT(*) FROM course_interest WHERE status = 'pending'
        ")->fetchColumn();
        
        // High priority interest
        $counts['high_priority'] = $pdo->query("
            SELECT COUNT(*) FROM course_interest WHERE status = 'pending' AND priority = 'high'
        ")->fetchColumn();
        
        // Upcoming courses
        $counts['upcoming_courses'] = $pdo->query("
            SELECT COUNT(*) FROM courses WHERE active = 1 AND course_date > NOW()
        ")->fetchColumn();
        
        // New users this week
        $counts['new_users_week'] = $pdo->query("
            SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")->fetchColumn();
        
        // Ready for certificates (paid participants without certificates)
        $counts['ready_certificates'] = $pdo->query("
            SELECT COUNT(*) FROM course_participants cp 
            JOIN courses c ON cp.course_id = c.id 
            WHERE cp.payment_status = 'paid' 
            AND c.course_date < NOW() 
            AND NOT EXISTS (
                SELECT 1 FROM certificates WHERE course_participant_id = cp.id
            )
        ")->fetchColumn();
        
        return $counts;
        
    } catch (Exception $e) {
        error_log("Admin navigation counts failed: " . $e->getMessage());
        return [
            'pending_interest' => 0,
            'upcoming_courses' => 0, 
            'new_users_week' => 0,
            'ready_certificates' => 0,
            'high_priority' => 0
        ];
    }
}

// Get counts for badges
$nav_counts = getAdminNavigationCounts($pdo);
$admin_user = $_SESSION['admin_user'] ?? 'Admin';
?>

<style>
/* Unified Admin Header Styles v4.1.0 */
.admin-header {
    background: linear-gradient(135deg, var(--inventijn-dark-blue), var(--inventijn-purple));
    color: white;
    padding: 1rem 2rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.admin-header-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-logo {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-logo h1 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
}

.admin-version {
    background: rgba(255,255,255,0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.admin-nav {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.nav-item {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.nav-item:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateY(-1px);
}

.nav-item.active {
    background: rgba(255,255,255,0.2);
    color: white;
    box-shadow: 0 2px 8px rgba(255,255,255,0.2);
}

.nav-badge {
    background: var(--orange);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    min-width: 20px;
    text-align: center;
    line-height: 1;
}

.nav-badge.priority {
    background: var(--danger);
    animation: pulse 2s infinite;
}

.nav-badge.success {
    background: var(--success);
}

.admin-user {
    display: flex;
    align-items: center;
    gap: 1rem;
    color: rgba(255,255,255,0.9);
    font-size: 0.875rem;
}

.admin-user .user-info {
    text-align: right;
}

.admin-user .user-name {
    font-weight: 600;
}

.admin-user .user-time {
    font-size: 0.75rem;
    opacity: 0.8;
}

.logout-btn {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.logout-btn:hover {
    background: rgba(255,255,255,0.2);
    border-color: rgba(255,255,255,0.3);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@media (max-width: 768px) {
    .admin-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .admin-nav {
        order: 3;
        width: 100%;
        justify-content: center;
    }
    
    .nav-item {
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;
    }
}
</style>

<header class="admin-header">
    <div class="admin-header-content">
        <div class="admin-logo">
            <h1>🎯 Inventijn Admin</h1>
            <div class="admin-version">v4.1.0</div>
        </div>
        
        <nav class="admin-nav">
            <a href="index.php" class="nav-item <?= $current_page === 'index' ? 'active' : '' ?>">
                📊 Dashboard
            </a>
            
            <a href="planning.php" class="nav-item <?= $current_page === 'planning' ? 'active' : '' ?>">
                📋 Planning
                <?php if ($nav_counts['pending_interest'] > 0): ?>
                    <span class="nav-badge <?= $nav_counts['high_priority'] > 0 ? 'priority' : '' ?>">
                        <?= $nav_counts['pending_interest'] ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="courses.php" class="nav-item <?= $current_page === 'courses' ? 'active' : '' ?>">
                📚 Cursussen
                <?php if ($nav_counts['upcoming_courses'] > 0): ?>
                    <span class="nav-badge"><?= $nav_counts['upcoming_courses'] ?></span>
                <?php endif; ?>
            </a>
            
            <a href="users.php" class="nav-item <?= $current_page === 'users' ? 'active' : '' ?>">
                👥 Gebruikers
                <?php if ($nav_counts['new_users_week'] > 0): ?>
                    <span class="nav-badge success"><?= $nav_counts['new_users_week'] ?></span>
                <?php endif; ?>
            </a>
            
            <a href="certificates.php" class="nav-item <?= $current_page === 'certificates' ? 'active' : '' ?>">
                📜 Certificaten
                <?php if ($nav_counts['ready_certificates'] > 0): ?>
                    <span class="nav-badge"><?= $nav_counts['ready_certificates'] ?></span>
                <?php endif; ?>
            </a>
        </nav>
        
        <div class="admin-user">
            <div class="user-info">
                <div class="user-name">👤 <?= htmlspecialchars($admin_user) ?></div>
                <div class="user-time"><?= date('d-m H:i') ?></div>
            </div>
            <a href="?logout=1" class="logout-btn">🚪 Logout</a>
        </div>
    </div>
</header>

<?php
/**
 * Quick Actions Bar - Context-sensitive actions based on current page
 */
if ($nav_counts['pending_interest'] > 0 || $nav_counts['ready_certificates'] > 0): ?>
<div style="max-width: 1400px; margin: 0 auto; padding: 0 2rem;">
    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <?php if ($nav_counts['high_priority'] > 0): ?>
                <span style="color: #d63031; font-weight: 600;">
                    🔥 <?= $nav_counts['high_priority'] ?> high priority interest waiting
                </span>
            <?php endif; ?>
            
            <?php if ($nav_counts['ready_certificates'] > 0): ?>
                <span style="color: #6c5ce7; font-weight: 600;">
                    📜 <?= $nav_counts['ready_certificates'] ?> ready for certificates
                </span>
            <?php endif; ?>
        </div>
        
        <div style="display: flex; gap: 0.5rem;">
            <?php if ($nav_counts['pending_interest'] > 0 && $current_page !== 'planning'): ?>
                <a href="planning.php" style="background: var(--orange); color: white; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.875rem; font-weight: 600;">
                    🎯 View Planning
                </a>
            <?php endif; ?>
            
            <?php if ($nav_counts['ready_certificates'] > 0 && $current_page !== 'certificates'): ?>
                <a href="certificates.php?ready=1" style="background: var(--inventijn-purple); color: white; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.875rem; font-weight: 600;">
                    📜 Generate Certificates
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Auto-refresh navigation counts every 2 minutes
setInterval(function() {
    if (document.hidden) return; // Don't refresh when tab is not active
    
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newNavBadges = doc.querySelectorAll('.nav-badge');
            const currentNavBadges = document.querySelectorAll('.nav-badge');
            
            // Update badges if they changed
            newNavBadges.forEach((newBadge, index) => {
                if (currentNavBadges[index] && newBadge.textContent !== currentNavBadges[index].textContent) {
                    currentNavBadges[index].textContent = newBadge.textContent;
                    currentNavBadges[index].style.animation = 'none';
                    setTimeout(() => {
                        currentNavBadges[index].style.animation = 'pulse 1s';
                    }, 10);
                }
            });
        })
        .catch(e => console.log('Navigation refresh failed:', e));
}, 120000); // 2 minutes

console.log('🎯 Inventijn Unified Admin Header v4.1.0 loaded');
</script>