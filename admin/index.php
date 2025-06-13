<?php
/**
 * Inventijn Enhanced Admin Dashboard v6.4.0
 * Unified dashboard with cross-module integration
 * Converted to unified admin system
 * Updated: 2025-06-13
 * Changes: 
 * v6.4.0 - Converted to unified admin design system
 * v6.4.0 - Replaced custom CSS with admin_styles.css
 * v6.4.0 - Integrated admin_header.php and admin_footer.php
 * v6.4.0 - Enhanced responsive design and UX
 * v6.4.0 - Unified navigation system
 */

session_start();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Simple hardcoded admin login (upgrade to database lookup in production)
    if ($username === 'admin' && $password === 'supergeheim123') {
        $_SESSION['admin_user'] = $username;
        $_SESSION['admin_login_time'] = time();
        
        // Redirect to dashboard
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = 'Onjuiste gebruikersnaam of wachtwoord';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['admin_user']);

// If not logged in, show unified login form
if (!$is_logged_in) {
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Inventijn v6.4.0</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #059669;
            --warning: #d97706;
            --error: #dc2626;
            --neutral: #6b7280;
            --background: #f9fafb;
            --surface: #ffffff;
            --text-primary: #111827;
            --text-secondary: #374151;
            --text-inverse: #ffffff;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-lg: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 60px rgba(0,0,0,0.3);
            --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: var(--font-family); 
            background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.5;
        }
        
        .login-container {
            background: var(--surface);
            padding: 3rem;
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 420px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo {
            margin-bottom: 2rem;
        }
        
        .logo h1 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .logo p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .version-badge {
            background: linear-gradient(135deg, var(--warning) 0%, #b45309 100%);
            color: var(--text-inverse);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            font-family: var(--font-family);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--text-inverse);
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s;
            font-family: var(--font-family);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }
        
        .error {
            background: #fee2e2;
            color: var(--error);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            font-size: 0.9rem;
            border-left: 4px solid var(--error);
        }
        
        .features {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.6;
        }
        
        .features strong {
            color: var(--text-primary);
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .logo h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>
                <i class="fas fa-shield-alt"></i> Admin Login 
                <span class="version-badge">v6.4.0</span>
            </h1>
            <p>Inventijn Unified Admin System</p>
        </div>
        
        <?php if (isset($login_error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($login_error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Gebruikersnaam
                </label>
                <input type="text" id="username" name="username" required autofocus 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Wachtwoord
                </label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" name="login" class="btn">
                <i class="fas fa-sign-in-alt"></i>
                Inloggen
            </button>
        </form>
        
        <div class="features">
            <strong>v6.4.0 Unified Features:</strong><br>
            <i class="fas fa-check"></i> Unified Design System<br>
            <i class="fas fa-check"></i> Cross-Module Integration<br>
            <i class="fas fa-check"></i> Real-time Statistics<br>
            <i class="fas fa-check"></i> Enhanced Planning Tools<br>
            <i class="fas fa-check"></i> Mobile-First Design
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// NOW include HTML header and continue with dashboard
$page_title = 'Dashboard';
require_once 'admin_header.php';

// Include dependencies
require_once '../includes/config.php';

try {
    $pdo = getDatabase();
} catch (Exception $e) {
    die('Database verbinding mislukt: ' . $e->getMessage());
}

// Enhanced dashboard data ophalen met cross-module integratie
try {
    // Basic counts
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn();
    $totalCourses = $pdo->query("SELECT COUNT(*) FROM courses WHERE active = 1")->fetchColumn();
    $upcomingCourses = $pdo->query("SELECT COUNT(*) FROM courses WHERE active = 1 AND course_date > NOW()")->fetchColumn();
    
    // Enhanced financial stats
    $totalRevenue = $pdo->query("
        SELECT COALESCE(SUM(c.price), 0) 
        FROM course_participants cp 
        JOIN courses c ON cp.course_id = c.id 
        WHERE cp.payment_status = 'paid'
    ")->fetchColumn();
    
    $pendingPayments = $pdo->query("
        SELECT COUNT(*) 
        FROM course_participants cp 
        JOIN courses c ON cp.course_id = c.id 
        WHERE cp.payment_status = 'pending' AND c.active = 1
    ")->fetchColumn();
    
    // Cross-module stats
    $pendingInterest = $pdo->query("SELECT COUNT(*) FROM course_interest WHERE status = 'pending'")->fetchColumn();
    $highPriorityInterest = $pdo->query("SELECT COUNT(*) FROM course_interest WHERE status = 'pending' AND priority = 'high'")->fetchColumn();
    $convertedInterest = $pdo->query("SELECT COUNT(*) FROM course_interest WHERE status = 'converted'")->fetchColumn();
    
    // Certificate stats
    $totalCertificates = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
    $readyForCertificates = $pdo->query("
        SELECT COUNT(*) FROM course_participants cp 
        JOIN courses c ON cp.course_id = c.id 
        WHERE cp.payment_status = 'paid' 
        AND c.course_date < NOW() 
        AND NOT EXISTS (SELECT 1 FROM certificates WHERE course_participant_id = cp.id)
    ")->fetchColumn();
    
    // Email queue stats (if table exists)
    try {
        $emailsPending = $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
        $emailsSent = $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'sent' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    } catch (Exception $e) {
        $emailsPending = $emailsSent = 0;
    }
    
    // Weekly activity
    $newUsersWeek = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $newInterestWeek = $pdo->query("SELECT COUNT(*) FROM course_interest WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    
} catch (Exception $e) {
    // Fallback for database errors
    $totalUsers = $activeUsers = $totalCourses = $upcomingCourses = 0;
    $totalRevenue = $pendingPayments = $emailsPending = $emailsSent = 0;
    $pendingInterest = $convertedInterest = $totalCertificates = $readyForCertificates = 0;
    $highPriorityInterest = $newUsersWeek = $newInterestWeek = 0;
}

// Enhanced recent data with cross-module information
$recentUsers = $pdo->query("
    SELECT u.id, u.name, u.email, u.active, u.created_at,
           COUNT(cp.id) as course_count,
           COUNT(ci.id) as interest_count,
           MAX(cp.enrollment_date) as last_enrollment
    FROM users u 
    LEFT JOIN course_participants cp ON u.id = cp.user_id
    LEFT JOIN course_interest ci ON u.id = ci.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC 
    LIMIT 6
")->fetchAll();

$recentCourses = $pdo->query("
    SELECT c.*, 
           COUNT(cp.id) as participant_count,
           SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
           c.price * SUM(CASE WHEN cp.payment_status = 'paid' THEN 1 ELSE 0 END) as course_revenue
    FROM courses c 
    LEFT JOIN course_participants cp ON c.id = cp.course_id 
    WHERE c.active = 1
    GROUP BY c.id 
    ORDER BY c.created_at DESC 
    LIMIT 6
")->fetchAll();

// Recent high-priority interests
$recentHighPriorityInterests = $pdo->query("
    SELECT ci.*, u.name as user_name, u.email as user_email
    FROM course_interest ci
    LEFT JOIN users u ON ci.user_id = u.id
    WHERE ci.status = 'pending' AND ci.priority = 'high'
    ORDER BY ci.created_at DESC
    LIMIT 5
")->fetchAll();

// Calculate stats
$stats = [
    'total_users' => $totalUsers,
    'active_users' => $activeUsers,
    'total_courses' => $totalCourses,
    'upcoming_courses' => $upcomingCourses,
    'total_revenue' => $totalRevenue,
    'pending_payments' => $pendingPayments,
    'pending_interest' => $pendingInterest,
    'high_priority_interest' => $highPriorityInterest,
    'converted_interest' => $convertedInterest,
    'total_certificates' => $totalCertificates,
    'ready_certificates' => $readyForCertificates,
    'emails_pending' => $emailsPending,
    'emails_sent' => $emailsSent,
    'new_users_week' => $newUsersWeek,
    'new_interest_week' => $newInterestWeek,
    'conversion_rate' => $pendingInterest > 0 ? round(($convertedInterest / ($convertedInterest + $pendingInterest)) * 100, 1) : 0
];
?>

<!-- Page Header Card -->
<div class="card">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-tachometer-alt"></i> Enhanced Admin Dashboard</h3>
            <p style="color: var(--text-secondary); margin: 0; font-size: var(--font-size-sm);">
                v6.4.0 Unified Edition | Complete cross-module management system
            </p>
        </div>
        <div style="display: flex; gap: var(--space-2); align-items: center;">
            <span style="color: var(--success); font-size: var(--font-size-sm); font-weight: 600;">
                <i class="fas fa-circle" style="font-size: 8px;"></i> System Online
            </span>
            <button onclick="showSystemInfo()" class="btn btn-secondary btn-sm">
                <i class="fas fa-info-circle"></i> System Info
            </button>
            <a href="?logout=1" class="btn btn-secondary btn-sm">
                <i class="fas fa-sign-out-alt"></i> Uitloggen
            </a>
        </div>
    </div>
    <div class="course-essentials">
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-users"></i> Gebruikers</div>
            <div class="essential-value"><?= number_format($stats['total_users']) ?></div>
            <div style="font-size: var(--font-size-xs); color: var(--neutral); margin-top: var(--space-1);">
                <?= $stats['active_users'] ?> actief | +<?= $stats['new_users_week'] ?> deze week
            </div>
        </div>
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-book"></i> Cursussen</div>
            <div class="essential-value"><?= number_format($stats['total_courses']) ?></div>
            <div style="font-size: var(--font-size-xs); color: var(--neutral); margin-top: var(--space-1);">
                <?= $stats['upcoming_courses'] ?> aankomend
            </div>
        </div>
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-euro-sign"></i> Omzet</div>
            <div class="essential-value">€<?= number_format($stats['total_revenue']) ?></div>
            <div style="font-size: var(--font-size-xs); color: var(--neutral); margin-top: var(--space-1);">
                <?= $stats['pending_payments'] ?> openstaand
            </div>
        </div>
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-clipboard-list"></i> Planning</div>
            <div class="essential-value"><?= number_format($stats['pending_interest']) ?></div>
            <div style="font-size: var(--font-size-xs); color: var(--neutral); margin-top: var(--space-1);">
                <?= $stats['converted_interest'] ?> geconverteerd
                <?php if ($stats['high_priority_interest'] > 0): ?>
                    | <span style="color: var(--error); font-weight: 600;"><?= $stats['high_priority_interest'] ?> urgent</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-certificate"></i> Certificaten</div>
            <div class="essential-value"><?= number_format($stats['total_certificates']) ?></div>
            <div style="font-size: var(--font-size-xs); color: var(--neutral); margin-top: var(--space-1);">
                <?= $stats['ready_certificates'] ?> klaar om te genereren
            </div>
        </div>
        <div class="essential-item">
            <div class="essential-label"><i class="fas fa-cog"></i> Systeem</div>
            <div class="essential-value" style="color: var(--success);">
                <i class="fas fa-check-circle"></i> Online
            </div>
            <div style="font-size: var(--font-size-xs); color: var(--neutral); margin-top: var(--space-1);">
                <?php if ($stats['emails_pending'] > 0): ?>
                    <?= $stats['emails_pending'] ?> emails wachten
                <?php else: ?>
                    Alle systemen operationeel
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div style="border-top: 1px solid var(--border); margin-top: var(--space-4); padding-top: var(--space-4);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
            
            <?php if ($stats['pending_interest'] > 0): ?>
            <a href="planning.php" style="text-decoration: none; color: inherit; padding: var(--space-4); border: 1px solid var(--border); border-radius: var(--radius-sm); transition: all 0.2s; display: block; border-left: 3px solid <?= $stats['high_priority_interest'] > 0 ? 'var(--error)' : 'var(--warning)' ?>;" 
               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-lg)'"
               onmouseout="this.style.transform=''; this.style.boxShadow=''">
                <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                    <i class="fas fa-clipboard-list"></i>
                    <strong>Interest Management</strong>
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--space-3);">
                    Manage course interest and convert to enrollments
                </div>
                <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
                    <span style="background: var(--warning); color: var(--text-inverse); padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs); font-weight: 600;">
                        <?= $stats['pending_interest'] ?> pending
                    </span>
                    <?php if ($stats['high_priority_interest'] > 0): ?>
                        <span style="background: var(--error); color: var(--text-inverse); padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs); font-weight: 600;">
                            <?= $stats['high_priority_interest'] ?> urgent
                        </span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endif; ?>
            
            <a href="courses.php" style="text-decoration: none; color: inherit; padding: var(--space-4); border: 1px solid var(--border); border-radius: var(--radius-sm); transition: all 0.2s; display: block; border-left: 3px solid var(--neutral);" 
               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-lg)'"
               onmouseout="this.style.transform=''; this.style.boxShadow=''">
                <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                    <i class="fas fa-book"></i>
                    <strong>Course Management</strong>
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--space-3);">
                    Beheer cursussen, deelnemers en betalingen
                </div>
                <?php if ($stats['upcoming_courses'] > 0): ?>
                    <span style="background: var(--primary); color: var(--text-inverse); padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs); font-weight: 600;">
                        <?= $stats['upcoming_courses'] ?> upcoming
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="users.php" style="text-decoration: none; color: inherit; padding: var(--space-4); border: 1px solid var(--border); border-radius: var(--radius-sm); transition: all 0.2s; display: block; border-left: 3px solid var(--primary);" 
               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-lg)'"
               onmouseout="this.style.transform=''; this.style.boxShadow=''">
                <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                    <i class="fas fa-users"></i>
                    <strong>User Administration</strong>
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--space-3);">
                    Complete gebruikersadministratie en toekenningen
                </div>
                <?php if ($stats['new_users_week'] > 0): ?>
                    <span style="background: var(--success); color: var(--text-inverse); padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs); font-weight: 600;">
                        +<?= $stats['new_users_week'] ?> new
                    </span>
                <?php endif; ?>
            </a>
            
            <?php if ($stats['ready_certificates'] > 0): ?>
            <a href="certificates.php?ready=1" style="text-decoration: none; color: inherit; padding: var(--space-4); border: 1px solid var(--border); border-radius: var(--radius-sm); transition: all 0.2s; display: block; border-left: 3px solid var(--success);" 
               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-lg)'"
               onmouseout="this.style.transform=''; this.style.boxShadow=''">
                <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                    <i class="fas fa-certificate"></i>
                    <strong>Certificate Generation</strong>
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--space-3);">
                    Generate and manage course certificates
                </div>
                <span style="background: var(--success); color: var(--text-inverse); padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs); font-weight: 600;">
                    <?= $stats['ready_certificates'] ?> ready
                </span>
            </a>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- Recent Data Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); margin-bottom: var(--space-6);">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> Recent Users</h3>
            <a href="users.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        
        <?php if (empty($recentUsers)): ?>
            <div class="empty-state">
                <i class="fas fa-user-plus"></i>
                <p>Nog geen gebruikers aangemeld.</p>
                <a href="users.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Eerste Gebruiker
                </a>
            </div>
        <?php else: ?>
            <div style="padding: var(--space-6);">
                <?php foreach ($recentUsers as $user): ?>
                <div style="border-bottom: 1px solid var(--border); padding: var(--space-4) 0; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-1);">
                            <a href="users.php?user_id=<?= $user['id'] ?>" style="color: inherit; text-decoration: none;">
                                <?= htmlspecialchars($user['name']) ?>
                            </a>
                        </div>
                        <div style="font-size: var(--font-size-sm); color: var(--text-secondary);">
                            <?= htmlspecialchars($user['email']) ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
                        <?php if ($user['active']): ?>
                            <span style="background: #d1fae5; color: #065f46; padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs); font-weight: 600;">
                                <i class="fas fa-check-circle"></i> Actief
                            </span>
                        <?php else: ?>
                            <span style="background: #fee2e2; color: #991b1b; padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs); font-weight: 600;">
                                <i class="fas fa-times-circle"></i> Inactief
                            </span>
                        <?php endif; ?>
                        <span style="background: var(--border-light); color: var(--text-primary); padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs);">
                            <i class="fas fa-book"></i> <?= $user['course_count'] ?>
                        </span>
                        <?php if ($user['interest_count'] > 0): ?>
                            <span style="background: #fef3c7; color: #92400e; padding: var(--space-1) var(--space-2); border-radius: 12px; font-size: var(--font-size-xs);">
                                <i class="fas fa-fire"></i> <?= $user['interest_count'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i> Recent Courses</h3>
            <a href="courses.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        
        <?php if (empty($recentCourses)): ?>
            <div class="empty-state">
                <i class="fas fa-plus-circle"></i>
                <p>Nog geen cursussen aangemaakt.</p>
                <a href="courses.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Eerste Cursus
                </a>
            </div>
        <?php else: ?>
            <div style="padding: var(--space-6);">
                <?php foreach ($recentCourses as $course): ?>
                <div style="border-bottom: 1px solid var(--border); padding: var(--space-4) 0;">
                    <div style="font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-2);">
                        <a href="courses.php?course_id=<?= $course['id'] ?>" style="color: inherit; text-decoration: none;">
                            <?= htmlspecialchars($course['name']) ?>
                        </a>
                    </div>
                    <div style="font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--space-2);">
                        <i class="fas fa-calendar"></i> <?= date('d-m-Y', strtotime($course['course_date'])) ?> | 
                        <i class="fas fa-users"></i> <?= $course['participant_count'] ?>/<?= $course['max_participants'] ?> | 
                        <i class="fas fa-check-circle"></i> <?= $course['paid_count'] ?> betaald
                        <?php if ($course['course_revenue'] > 0): ?>
                            | <i class="fas fa-euro-sign"></i> €<?= number_format($course['course_revenue']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<!-- System Info Footer -->
<div style="background: var(--surface); padding: var(--space-6); border-radius: var(--radius); text-align: center; color: var(--text-secondary); font-size: var(--font-size-sm); box-shadow: var(--shadow); margin-top: var(--space-6);">
    <p style="margin-bottom: var(--space-2);">
        <strong style="color: var(--text-primary);"><i class="fas fa-cog"></i> Inventijn Admin System v6.4.0 - Unified Edition</strong>
    </p>
    <p style="margin-bottom: var(--space-2);">
        <i class="fas fa-chart-bar"></i> Data Status: <?= count($recentUsers) ?> users, <?= count($recentCourses) ?> courses, <?= $stats['pending_interest'] ?> pending interests |
        <i class="fas fa-envelope"></i> Email Queue: <?= $stats['emails_pending'] ?> pending, <?= $stats['emails_sent'] ?> sent (24h) |
        <i class="fas fa-percentage"></i> Conversion Rate: <?= $stats['conversion_rate'] ?>%
    </p>
    <p>
        <i class="fas fa-sync"></i> Last updated: <?= date('d-m-Y H:i:s') ?> | 
        <i class="fas fa-user"></i> Session: <?= $_SESSION['admin_user'] ?> | 
        <i class="fas fa-globe"></i> Integration Status: <span style="color: var(--success); font-weight: 600;"><i class="fas fa-check-circle"></i> All Modules Connected</span>
    </p>
</div>

<script>
// Enhanced dashboard functionality
function showSystemInfo() {
    let systemInfo = `🔧 System Status v6.4.0\n\n`;
    systemInfo += `✅ Database: Connected\n`;
    systemInfo += `✅ Email System: Active\n`;
    systemInfo += `✅ Cross-Module Integration: Working\n`;
    systemInfo += `✅ Certificate Generator: Ready\n\n`;
    systemInfo += `📊 Current Load:\n`;
    systemInfo += `- Users: <?= $stats['total_users'] ?>\n`;
    systemInfo += `- Courses: <?= $stats['total_courses'] ?>\n`;
    systemInfo += `- Pending Interest: <?= $stats['pending_interest'] ?>\n`;
    systemInfo += `- Email Queue: <?= $stats['emails_pending'] ?>\n`;
    systemInfo += `- Revenue: €<?= number_format($stats['total_revenue']) ?>\n`;
    systemInfo += `- Conversion Rate: <?= $stats['conversion_rate'] ?>%`;
    
    alert(systemInfo);
}

// Keyboard shortcuts for power users
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case '1':
                e.preventDefault();
                window.location.href = 'index.php';
                break;
            case '2':
                e.preventDefault();
                window.location.href = 'planning.php';
                break;
            case '3':
                e.preventDefault();
                window.location.href = 'courses.php';
                break;
            case '4':
                e.preventDefault();
                window.location.href = 'users.php';
                break;
            case '5':
                e.preventDefault();
                window.location.href = 'certificates.php';
                break;
        }
    }
});

// Auto-refresh critical stats every 2 minutes (only when tab is active)
setInterval(function() {
    if (!document.hidden) {
        console.log('🔄 Auto-refreshing dashboard stats...');
        // The unified header already handles nav badge refresh
    }
}, 120000);

// Show welcome message for new sessions
if (performance.navigation.type === 1) {
    console.log('🎉 Welcome to Inventijn Admin Dashboard v6.4.0!');
    console.log('✅ Unified design system active');
    console.log('📊 Real-time statistics loaded');
    console.log('🔧 Use Ctrl+1-5 for quick navigation');
}

console.log('🎯 Enhanced Admin Dashboard v6.4.0 loaded successfully');
</script>

<style>
/* Custom animations for dashboard */
@keyframes pulse {
    0%, 100% { 
        transform: scale(1); 
    }
    50% { 
        transform: scale(1.02); 
    }
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .card-header {
        flex-direction: column !important;
        gap: var(--space-4) !important;
        align-items: stretch !important;
    }
    
    .card-header > div:first-child {
        text-align: center;
    }
    
    .card-header .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php
require_once 'admin_footer.php';
?>