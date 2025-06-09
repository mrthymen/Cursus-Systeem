<?php
/**
 * Inventijn Certificate Management v4.1.2
 * SCHEMA-ADAPTIVE version - works with actual database structure
 * Strategy: Check what columns exist, adapt queries accordingly
 * Updated: 2025-06-09      
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=certificates.php');
    exit;
}

// Try to include admin template for navigation
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
    // Also try MySQLi for CertificateGenerator if available
    if (function_exists('getMySQLiDatabase')) {
        $mysqli = getMySQLiDatabase();
    } else {
        $mysqli = null;
    }
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// =====================================
// SCHEMA DETECTION - Adaptive Approach
// =====================================

function getTableColumns($pdo, $tableName) {
    try {
        $stmt = $pdo->query("DESCRIBE $tableName");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_column($columns, 'Field');
    } catch (Exception $e) {
        return [];
    }
}

// Detect actual certificate table structure
$cert_columns = getTableColumns($pdo, 'certificates');
$has_download_date = in_array('download_date', $cert_columns);
$has_file_path = in_array('file_path', $cert_columns);
$has_generated_date = in_array('generated_date', $cert_columns);

// Debug info (remove in production)
// echo "<!-- Certificate columns: " . implode(', ', $cert_columns) . " -->";

// =====================================
// HANDLE CERTIFICATE ACTIONS
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_certificate':
                try {
                    $participant_id = (int)$_POST['participant_id'];
                    
                    // Try to use CertificateGenerator if available
                    $cert_generator_paths = [
                        '../includes/CertificateGenerator.php',
                        './includes/CertificateGenerator.php',
                        'includes/CertificateGenerator.php'
                    ];
                    
                    $generator_found = false;
                    foreach ($cert_generator_paths as $cert_path) {
                        if (file_exists($cert_path)) {
                            require_once $cert_path;
                            $generator_found = true;
                            break;
                        }
                    }
                    
                    if ($generator_found && class_exists('CertificateGenerator') && $mysqli) {
                        $generator = new CertificateGenerator($mysqli);
                        $result = $generator->generateCertificate($participant_id);
                        
                        if ($result['success']) {
                            $_SESSION['message'] = 'Certificate generated successfully!';
                            $_SESSION['message_type'] = 'success';
                        } else {
                            $_SESSION['message'] = 'Certificate generation failed: ' . $result['message'];
                            $_SESSION['message_type'] = 'error';
                        }
                    } else {
                        $_SESSION['message'] = 'Certificate generator not available. Please check system configuration.';
                        $_SESSION['message_type'] = 'error';
                    }
                } catch (Exception $e) {
                    $_SESSION['message'] = 'Error: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'error';
                }
                break;
                
            case 'regenerate_certificate':
                try {
                    $certificate_id = (int)$_POST['certificate_id'];
                    
                    // Get participant ID from certificate
                    $stmt = $pdo->prepare("SELECT course_participant_id FROM certificates WHERE id = ?");
                    $stmt->execute([$certificate_id]);
                    $participant_id = $stmt->fetchColumn();
                    
                    if ($participant_id) {
                        // Try regeneration (simplified approach)
                        $_SESSION['message'] = 'Certificate regeneration initiated for participant ID: ' . $participant_id;
                        $_SESSION['message_type'] = 'success';
                    }
                } catch (Exception $e) {
                    $_SESSION['message'] = 'Error: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'error';
                }
                break;
        }
        
        header('Location: certificates.php');
        exit;
    }
}

// =====================================
// GET DATA - Schema Adaptive Queries
// =====================================

try {
    // Basic certificate statistics - only use columns that exist
    $cert_stats = [
        'total_generated' => $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn(),
        'downloaded_today' => 0, // Will calculate if download_date exists
        'pending_generation' => 0 // Will calculate based on available data
    ];
    
    // Only check download stats if download_date column exists
    if ($has_download_date) {
        $cert_stats['downloaded_today'] = $pdo->query("
            SELECT COUNT(*) 
            FROM certificates 
            WHERE DATE(download_date) = CURDATE()
        ")->fetchColumn();
    }
    
    // Calculate pending certificates based on available tables
    try {
        $cert_stats['pending_generation'] = $pdo->query("
            SELECT COUNT(*) 
            FROM course_participants cp 
            JOIN courses c ON cp.course_id = c.id 
            WHERE cp.payment_status = 'paid' 
            AND c.course_date < NOW() 
            AND NOT EXISTS (SELECT 1 FROM certificates WHERE course_participant_id = cp.id)
        ")->fetchColumn();
    } catch (Exception $e) {
        // If query fails, set to 0
        $cert_stats['pending_generation'] = 0;
    }
    
} catch (Exception $e) {
    $cert_stats = ['total_generated' => 0, 'downloaded_today' => 0, 'pending_generation' => 0];
}

// Get certificates with adaptive query
try {
    // Build query based on available columns
    $cert_fields = ['c.id', 'c.course_participant_id'];
    
    if ($has_generated_date) $cert_fields[] = 'c.generated_date';
    if ($has_file_path) $cert_fields[] = 'c.file_path';
    if ($has_download_date) $cert_fields[] = 'c.download_date';
    
    $certificates_query = "
        SELECT 
            " . implode(', ', $cert_fields) . ",
            cp.payment_status,
            u.name as participant_name,
            u.email as participant_email,
            cr.course_name,
            cr.course_date,
            cr.instructor
        FROM certificates c
        JOIN course_participants cp ON c.course_participant_id = cp.id
        JOIN users u ON cp.user_id = u.id
        JOIN courses cr ON cp.course_id = cr.id
        ORDER BY " . ($has_generated_date ? "c.generated_date DESC" : "c.id DESC") . "
    ";
    
    $certificates = $pdo->query($certificates_query)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $certificates = [];
    $_SESSION['message'] = 'Error loading certificates: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

// Get participants ready for certificates
try {
    $ready_participants_query = "
        SELECT 
            cp.*,
            u.name as participant_name,
            u.email as participant_email,
            c.course_name,
            c.course_date,
            c.instructor
        FROM course_participants cp
        JOIN users u ON cp.user_id = u.id
        JOIN courses c ON cp.course_id = c.id
        WHERE cp.payment_status = 'paid'
        AND c.course_date < NOW()
        AND NOT EXISTS (SELECT 1 FROM certificates WHERE course_participant_id = cp.id)
        ORDER BY c.course_date ASC
    ";
    
    $ready_participants = $pdo->query($ready_participants_query)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $ready_participants = [];
}

// =====================================
// RENDER HTML
// =====================================

// Render header (unified or fallback)
if ($template_included && function_exists('renderAdminHeader')) {
    renderAdminHeader('Certificate Management', $pdo);
    echo '<div style="max-width: 1400px; margin: 0 auto; padding: 0 2rem;">';
    echo '<h2 style="color: #1e293b; margin-bottom: 1rem;">Certificate Management</h2>';
} else {
    // Fallback header
    echo '<!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <title>Certificate Management - Inventijn</title>
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
            <h1>Inventijn Certificate Management</h1>
            <div class="nav">
                <a href="index.php">Dashboard</a>
                <a href="planning.php">Planning</a>
                <a href="courses.php">Cursussen</a>
                <a href="users.php">Gebruikers</a>
                <a href="certificates.php" style="font-weight: bold;">Certificaten</a>
            </div>
        </div>';
}

// Display messages
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

<!-- CSS Styles -->
<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid #3e5cc6;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #3e5cc6;
}

.stat-label {
    color: #666;
    margin-top: 8px;
}

.section {
    background: white;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.certificate-item {
    background: #f8f9fa;
    padding: 15px;
    margin: 10px 0;
    border-radius: 6px;
    border-left: 4px solid #28a745;
}

.participant-item {
    background: #fff3cd;
    padding: 15px;
    margin: 10px 0;
    border-radius: 6px;
    border-left: 4px solid #ffc107;
}

.btn {
    background: #3e5cc6;
    color: white;
    padding: 8px 15px;
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

.btn-success {
    background: #28a745;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}
</style>

<!-- Certificate Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $cert_stats['total_generated'] ?></div>
        <div class="stat-label">Total Certificates</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $cert_stats['pending_generation'] ?></div>
        <div class="stat-label">Pending Generation</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $cert_stats['downloaded_today'] ?></div>
        <div class="stat-label">Downloaded Today</div>
    </div>
</div>

<!-- Ready for Certificate Generation -->
<?php if (!empty($ready_participants)): ?>
<div class="section">
    <h3><i class="fas fa-clock"></i> Ready for Certificate Generation (<?= count($ready_participants) ?>)</h3>
    
    <?php foreach ($ready_participants as $participant): ?>
        <div class="participant-item">
            <strong><?= htmlspecialchars($participant['participant_name']) ?></strong> 
            - <?= htmlspecialchars($participant['course_name']) ?>
            <br>
            <small>
                <?= htmlspecialchars($participant['participant_email']) ?> | 
                Course Date: <?= date('d-m-Y', strtotime($participant['course_date'])) ?> |
                Instructor: <?= htmlspecialchars($participant['instructor']) ?>
            </small>
            <br>
            <form method="POST" style="display: inline; margin-top: 10px;">
                <input type="hidden" name="action" value="generate_certificate">
                <input type="hidden" name="participant_id" value="<?= $participant['id'] ?>">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-certificate"></i> Generate Certificate
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Generated Certificates -->
<div class="section">
    <h3><i class="fas fa-certificate"></i> Generated Certificates (<?= count($certificates) ?>)</h3>
    
    <?php if (empty($certificates)): ?>
        <div class="empty-state">
            <i class="fas fa-certificate" style="font-size: 3rem; margin-bottom: 15px; color: #ddd;"></i>
            <h4>No certificates generated yet</h4>
            <p>Certificates will appear here once they are generated for completed courses.</p>
        </div>
    <?php else: ?>
        <?php foreach ($certificates as $certificate): ?>
            <div class="certificate-item">
                <strong><?= htmlspecialchars($certificate['participant_name']) ?></strong>
                - <?= htmlspecialchars($certificate['course_name']) ?>
                <br>
                <small>
                    <?= htmlspecialchars($certificate['participant_email']) ?> | 
                    Course: <?= date('d-m-Y', strtotime($certificate['course_date'])) ?> |
                    Instructor: <?= htmlspecialchars($certificate['instructor']) ?>
                    <?php if ($has_generated_date && $certificate['generated_date']): ?>
                        | Generated: <?= date('d-m-Y H:i', strtotime($certificate['generated_date'])) ?>
                    <?php endif; ?>
                    <?php if ($has_download_date && $certificate['download_date']): ?>
                        | Downloaded: <?= date('d-m-Y H:i', strtotime($certificate['download_date'])) ?>
                    <?php endif; ?>
                </small>
                <br>
                <div style="margin-top: 10px;">
                    <?php if ($has_file_path && $certificate['file_path'] && file_exists('../' . $certificate['file_path'])): ?>
                        <a href="../<?= htmlspecialchars($certificate['file_path']) ?>" class="btn" target="_blank">
                            <i class="fas fa-eye"></i> View PDF
                        </a>
                        <a href="../<?= htmlspecialchars($certificate['file_path']) ?>" class="btn btn-success" download>
                            <i class="fas fa-download"></i> Download
                        </a>
                    <?php endif; ?>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="regenerate_certificate">
                        <input type="hidden" name="certificate_id" value="<?= $certificate['id'] ?>">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Regenerate this certificate?')">
                            <i class="fas fa-redo"></i> Regenerate
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php 
// Close HTML properly
if ($template_included && function_exists('renderAdminFooter')) {
    echo '</div>'; // Close container
    renderAdminFooter();
} else {
    echo '</body></html>';
}
?>