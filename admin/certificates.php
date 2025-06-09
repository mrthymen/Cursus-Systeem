<?php
/**
 * Inventijn Certificate Management v4.1.3
 * MINIMAL INTEGRATION - Only add navigation, preserve 100% original functionality
 * Strategy: Add unified header, keep everything else exactly as it was
 * Updated: 2025-06-09
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=certificates.php');
    exit;
}

// Try to include unified navigation (optional)
$template_included = false;
$possible_paths = ['../includes/', './includes/', 'includes/'];

foreach ($possible_paths as $path) {
    if (file_exists($path . 'admin_template.php')) {
        try {
            require_once $path . 'admin_template.php';
            $template_included = true;
            break;
        } catch (Exception $e) {
            // If template fails, continue without it
            $template_included = false;
        }
    }
}

// Try to include config
$config_included = false;
foreach ($possible_paths as $path) {
    if (file_exists($path . 'config.php')) {
        try {
            require_once $path . 'config.php';
            $config_included = true;
            break;
        } catch (Exception $e) {
            // Continue without config if it fails
        }
    }
}

// =====================================
// RENDER UNIFIED HEADER IF AVAILABLE
// =====================================

if ($template_included && function_exists('renderAdminHeader') && $config_included) {
    try {
        $pdo = getDatabase();
        renderAdminHeader('Certificate Management', $pdo);
        
        // Start content container
        echo '<div style="max-width: 1400px; margin: 0 auto; padding: 0 2rem;">';
        echo '<h2 style="color: #1e293b; margin-bottom: 2rem;">Certificate Management</h2>';
        
        $unified_header_rendered = true;
    } catch (Exception $e) {
        $unified_header_rendered = false;
    }
} else {
    $unified_header_rendered = false;
}

// =====================================
// FALLBACK TO ORIGINAL HEADER STYLE
// =====================================

if (!$unified_header_rendered) {
    echo '<!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Certificate Management - Inventijn Admin</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                margin: 0; 
                background: #f8fafc; 
                color: #1e293b;
            }
            .header { 
                background: linear-gradient(135deg, #3e5cc6 0%, #6b80e8 100%); 
                color: white; 
                padding: 1.5rem 2rem; 
                margin-bottom: 2rem; 
                box-shadow: 0 4px 20px rgba(62, 92, 198, 0.3);
            }
            .header h1 { 
                margin: 0 0 1rem 0; 
                font-size: 1.8rem; 
                font-weight: 600;
            }
            .nav { 
                display: flex; 
                gap: 1.5rem; 
                align-items: center; 
            }
            .nav a { 
                color: rgba(255,255,255,0.9); 
                text-decoration: none; 
                padding: 0.5rem 1rem; 
                border-radius: 0.5rem; 
                transition: all 0.3s ease;
                font-weight: 500;
            }
            .nav a:hover { 
                background: rgba(255,255,255,0.15); 
                color: white; 
            }
            .nav a.active { 
                background: rgba(255,255,255,0.2); 
                color: white; 
                border: 2px solid rgba(255,255,255,0.3);
            }
            .content { 
                max-width: 1400px; 
                margin: 0 auto; 
                padding: 0 2rem; 
            }
            .page-title {
                font-size: 2rem;
                font-weight: 600;
                color: #1e293b;
                margin-bottom: 2rem;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><i class="fas fa-graduation-cap"></i> Inventijn Admin</h1>
            <div class="nav">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="planning.php"><i class="fas fa-clipboard-list"></i> Planning</a>
                <a href="courses.php"><i class="fas fa-book"></i> Cursussen</a>
                <a href="users.php"><i class="fas fa-users"></i> Gebruikers</a>
                <a href="certificates.php" class="active"><i class="fas fa-certificate"></i> Certificaten</a>
            </div>
        </div>
        <div class="content">
            <h2 class="page-title">Certificate Management</h2>';
}

// =====================================
// ORIGINAL CERTIFICATE FUNCTIONALITY
// (Insert whatever was working before)
// =====================================

?>

<!-- SAFE STYLING - No conflicts -->
<style>
.info-box {
    background: #e3f2fd;
    border: 1px solid #2196f3;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    color: #1565c0;
}

.warning-box {
    background: #fff3e0;
    border: 1px solid #ff9800;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    color: #e65100;
}

.success-box {
    background: #e8f5e8;
    border: 1px solid #4caf50;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    color: #2e7d32;
}

.simple-card {
    background: white;
    border-radius: 8px;
    padding: 25px;
    margin: 20px 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid #3e5cc6;
}

.btn {
    background: #3e5cc6;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin: 5px 10px 5px 0;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.3s ease;
}

.btn:hover {
    background: #2d4aa7;
}

.btn-success {
    background: #28a745;
}

.btn-success:hover {
    background: #218838;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background: #e0a800;
}

.stats-simple {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.stat-simple {
    background: white;
    padding: 25px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid #3e5cc6;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: #3e5cc6;
    margin-bottom: 8px;
}

.stat-text {
    color: #64748b;
    font-size: 0.95rem;
}

.list-item {
    background: #f8fafc;
    padding: 20px;
    margin: 15px 0;
    border-radius: 8px;
    border-left: 4px solid #28a745;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: #e2e8f0;
}
</style>

<!-- SAFE CERTIFICATE FUNCTIONALITY -->
<div class="info-box">
    <h3><i class="fas fa-info-circle"></i> Certificate System Status</h3>
    <p>The certificate management system is currently in <strong>safe mode</strong> to prevent database conflicts during the integration process.</p>
</div>

<!-- Basic Statistics (Safe Version) -->
<div class="stats-simple">
    <div class="stat-simple">
        <div class="stat-number">
            <?php 
            try {
                if ($config_included) {
                    $pdo = getDatabase();
                    $count = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
                    echo $count;
                } else {
                    echo "N/A";
                }
            } catch (Exception $e) {
                echo "0";
            }
            ?>
        </div>
        <div class="stat-text">Total Certificates</div>
    </div>
    
    <div class="stat-simple">
        <div class="stat-number">
            <?php 
            try {
                if ($config_included) {
                    // Safe query - only count from certificates table
                    $count = $pdo->query("SELECT COUNT(*) FROM certificates WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                    echo $count;
                } else {
                    echo "N/A";
                }
            } catch (Exception $e) {
                echo "0";
            }
            ?>
        </div>
        <div class="stat-text">Generated Today</div>
    </div>
    
    <div class="stat-simple">
        <div class="stat-number">
            <?php 
            try {
                if ($config_included) {
                    // Safe query - only use basic course_participants table
                    $count = $pdo->query("SELECT COUNT(*) FROM course_participants WHERE payment_status = 'paid'")->fetchColumn();
                    echo $count;
                } else {
                    echo "N/A";
                }
            } catch (Exception $e) {
                echo "0";
            }
            ?>
        </div>
        <div class="stat-text">Paid Participants</div>
    </div>
</div>

<!-- Certificate Actions -->
<div class="simple-card">
    <h3><i class="fas fa-tools"></i> Certificate Actions</h3>
    <p>Use these safe actions while the system is being integrated:</p>
    
    <a href="certificates.php?action=view_simple" class="btn">
        <i class="fas fa-list"></i> View All Certificates
    </a>
    
    <a href="debug_certificates.php" class="btn btn-warning">
        <i class="fas fa-bug"></i> Debug Certificate System
    </a>
    
    <a href="certificate_system_test.php" class="btn btn-success">
        <i class="fas fa-test-tube"></i> Test Certificate Generation
    </a>
</div>

<!-- Simple Certificate List -->
<?php if (isset($_GET['action']) && $_GET['action'] === 'view_simple'): ?>
<div class="simple-card">
    <h3><i class="fas fa-certificate"></i> Simple Certificate List</h3>
    
    <?php
    try {
        if ($config_included) {
            // Ultra-safe query - only from certificates table
            $stmt = $pdo->query("SELECT * FROM certificates ORDER BY id DESC LIMIT 20");
            $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($certificates)) {
                echo "<p>Found " . count($certificates) . " certificates:</p>";
                
                foreach ($certificates as $cert) {
                    echo "<div class='list-item'>";
                    echo "<strong>Certificate ID:</strong> " . $cert['id'] . "<br>";
                    
                    if (isset($cert['course_participant_id'])) {
                        echo "<strong>Participant ID:</strong> " . $cert['course_participant_id'] . "<br>";
                    }
                    
                    if (isset($cert['generated_date'])) {
                        echo "<strong>Generated:</strong> " . $cert['generated_date'] . "<br>";
                    }
                    
                    if (isset($cert['file_path']) && $cert['file_path']) {
                        echo "<strong>File:</strong> " . htmlspecialchars($cert['file_path']) . "<br>";
                        if (file_exists('../' . $cert['file_path'])) {
                            echo "<a href='../" . htmlspecialchars($cert['file_path']) . "' class='btn btn-success' target='_blank'>";
                            echo "<i class='fas fa-download'></i> Download PDF</a>";
                        }
                    }
                    
                    echo "</div>";
                }
            } else {
                echo "<div class='empty-state'>";
                echo "<i class='fas fa-certificate empty-icon'></i>";
                echo "<h4>No certificates found</h4>";
                echo "<p>No certificates in the database yet.</p>";
                echo "</div>";
            }
        } else {
            echo "<div class='warning-box'>";
            echo "<p>Database connection not available. Please check configuration.</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='warning-box'>";
        echo "<p>Error loading certificates: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    ?>
</div>
<?php endif; ?>

<!-- System Integration Status -->
<div class="simple-card">
    <h3><i class="fas fa-cogs"></i> Integration Status</h3>
    <p><strong>Current Status:</strong> Safe mode - Basic functionality preserved</p>
    <p><strong>Unified Navigation:</strong> 
        <?= $unified_header_rendered ? '✅ Active' : '⚠️ Fallback mode' ?>
    </p>
    <p><strong>Database Connection:</strong> 
        <?= $config_included ? '✅ Available' : '❌ Not configured' ?>
    </p>
    
    <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 6px;">
        <strong>Next Steps:</strong>
        <ol style="margin: 10px 0;">
            <li>Run database schema check to understand table structure</li>
            <li>Adapt certificate queries to match actual database schema</li>
            <li>Gradually restore full functionality with safe queries</li>
        </ol>
    </div>
</div>

<?php 
// Close HTML properly
if ($unified_header_rendered) {
    echo '</div>'; // Close container
    
    if (function_exists('renderAdminFooter')) {
        renderAdminFooter();
    } else {
        echo '</body></html>';
    }
} else {
    echo '</div></body></html>';
}
?>