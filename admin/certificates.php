<?php
/**
 * Inventijn Certificate Management v4.1.1
 * Fixed version with defensive path handling
 * Previous: v4.1.0 (path issues) → Current: v4.1.1 (defensive)
 * Updated: 2025-06-09
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=certificates.php');
    exit;
}

// Defensive path handling - try multiple possible locations
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
    // Minimal fallback HTML
    echo '<!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Certificate Management - Inventijn Admin</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; }
            .header { background: #3e5cc6; color: white; padding: 1rem; margin-bottom: 2rem; border-radius: 0.5rem; }
            .nav a { color: white; margin-right: 1rem; text-decoration: none; }
            .nav a:hover { text-decoration: underline; }
            .content { background: white; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .btn { background: #3e5cc6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.25rem; cursor: pointer; margin-right: 0.5rem; }
            .btn:hover { background: #2d4aa7; }
            .certificate-item { background: #f8f9fa; padding: 1rem; margin: 1rem 0; border-radius: 0.25rem; border-left: 4px solid #3e5cc6; }
            .success { background: #d4edda; color: #155724; padding: 1rem; border-radius: 0.25rem; margin: 1rem 0; }
            .error { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 0.25rem; margin: 1rem 0; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><i class="fas fa-certificate"></i> Inventijn Certificate Management</h1>
            <div class="nav">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="planning.php"><i class="fas fa-clipboard-list"></i> Planning</a>
                <a href="courses.php"><i class="fas fa-book"></i> Cursussen</a>
                <a href="users.php"><i class="fas fa-users"></i> Gebruikers</a>
                <a href="certificates.php" style="background: rgba(255,255,255,0.2); padding: 0.5rem; border-radius: 0.25rem;"><i class="fas fa-certificate"></i> Certificaten</a>
            </div>
        </div>
        <div class="content">';
    
    // Simple renderPageHeader fallback
    function renderPageHeader($title, $breadcrumb = null) {
        echo '<h2>' . htmlspecialchars($title) . '</h2>';
        if ($breadcrumb) echo '<p style="color: #666;">' . $breadcrumb . '</p>';
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
        // Fallback database connection
        $pdo = new PDO("mysql:host=localhost;dbname=inventijn_cursus", "username", "password");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (Exception $e) {
    echo '<div class="error">Database connection failed: ' . $e->getMessage() . '</div>';
    exit;
}

try {
    if (function_exists('getMySQLiDatabase')) {
        $mysqli = getMySQLiDatabase();
    } else {
        // Fallback MySQLi connection
        $mysqli = new mysqli("localhost", "username", "password", "inventijn_cursus");
        if ($mysqli->connect_error) {
            throw new Exception("MySQLi connection failed: " . $mysqli->connect_error);
        }
    }
} catch (Exception $e) {
    echo '<div class="error">MySQLi connection failed: ' . $e->getMessage() . '</div>';
    // Continue without MySQLi for basic functionality
    $mysqli = null;
}

// Handle certificate actions (simplified version)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
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
                        $_SESSION['admin_message'] = [
                            'text' => 'Certificate generated successfully!',
                            'type' => 'success'
                        ];
                    } else {
                        $_SESSION['admin_message'] = [
                            'text' => 'Certificate generation failed: ' . $result['message'],
                            'type' => 'error'
                        ];
                    }
                } else {
                    $_SESSION['admin_message'] = [
                        'text' => 'CertificateGenerator not available. Please check system configuration.',
                        'type' => 'error'
                    ];
                }
            } catch (Exception $e) {
                $_SESSION['admin_message'] = [
                    'text' => 'Error: ' . $e->getMessage(),
                    'type' => 'error'
                ];
            }
            break;
    }
    
    header('Location: certificates.php');
    exit;
}

// Get certificate data with error handling
try {
    // Get certificate statistics
    $certificate_stats = [
        'total_generated' => $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn(),
        'pending_generation' => $pdo->query("
            SELECT COUNT(*) 
            FROM course_participants cp 
            JOIN courses c ON cp.course_id = c.id 
            WHERE cp.payment_status = 'paid' 
            AND c.course_date < NOW() 
            AND NOT EXISTS (SELECT 1 FROM certificates WHERE course_participant_id = cp.id)
        ")->fetchColumn(),
        'downloaded_today' => $pdo->query("
            SELECT COUNT(*) 
            FROM certificates 
            WHERE DATE(download_date) = CURDATE()
        ")->fetchColumn()
    ];
    
    // Get all certificates
    $certificates_query = "
        SELECT 
            c.*,
            cp.payment_status,
            u.name as participant_name,
            u.email as participant_email,
            cr.course_name,
            cr.course_date,
            CASE 
                WHEN c.download_date IS NOT NULL THEN 'Downloaded'
                WHEN c.generated_date IS NOT NULL THEN 'Ready'
                ELSE 'Pending'
            END as status
        FROM certificates c
        JOIN course_participants cp ON c.course_participant_id = cp.id
        JOIN users u ON cp.user_id = u.id
        JOIN courses cr ON cp.course_id = cr.id
        ORDER BY c.generated_date DESC
    ";
    
    $certificates = $pdo->query($certificates_query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get participants ready for certificates
    $ready_participants = $pdo->query("
        SELECT 
            cp.*,
            u.name as participant_name,
            u.email as participant_email,
            c.course_name,
            c.course_date
        FROM course_participants cp
        JOIN users u ON cp.user_id = u.id
        JOIN courses c ON cp.course_id = c.id
        WHERE cp.payment_status = 'paid'
        AND c.course_date < NOW()
        AND NOT EXISTS (SELECT 1 FROM certificates WHERE course_participant_id = cp.id)
        ORDER BY c.course_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    echo '<div class="error">Database query error: ' . $e->getMessage() . '</div>';
    $certificates = [];
    $ready_participants = [];
    $certificate_stats = ['total_generated' => 0, 'pending_generation' => 0, 'downloaded_today' => 0];
}

// Render header (unified or fallback)
if (function_exists('renderAdminHeader')) {
    renderAdminHeader('Certificate Management', $pdo);
} else {
    // Fallback header already rendered above
}

renderPageHeader('Certificate Management', '<a href="index.php">Dashboard</a> > Certificates');

// Display session messages
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    echo '<div class="' . $message['type'] . '">' . htmlspecialchars($message['text']) . '</div>';
    unset($_SESSION['admin_message']);
}

?>

<!-- Certificate Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 2rem 0;">
    <div style="background: white; padding: 1.5rem; border-radius: 0.5rem; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; font-weight: bold; color: #3e5cc6;"><?= $certificate_stats['total_generated'] ?></div>
        <div style="color: #666;">Total Certificates</div>
    </div>
    <div style="background: white; padding: 1.5rem; border-radius: 0.5rem; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; font-weight: bold; color: #f59e0b;"><?= $certificate_stats['pending_generation'] ?></div>
        <div style="color: #666;">Pending Generation</div>
    </div>
    <div style="background: white; padding: 1.5rem; border-radius: 0.5rem; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; font-weight: bold; color: #10b981;"><?= $certificate_stats['downloaded_today'] ?></div>
        <div style="color: #666;">Downloaded Today</div>
    </div>
</div>

<!-- Ready for Certificate Generation -->
<?php if (!empty($ready_participants)): ?>
<h3><i class="fas fa-clock"></i> Ready for Certificate Generation</h3>
<div>
    <?php foreach ($ready_participants as $participant): ?>
        <div class="certificate-item">
            <strong><?= htmlspecialchars($participant['participant_name']) ?></strong> 
            - <?= htmlspecialchars($participant['course_name']) ?>
            <br>
            <small><?= htmlspecialchars($participant['participant_email']) ?> | Course Date: <?= date('d-m-Y', strtotime($participant['course_date'])) ?></small>
            <br>
            <form method="POST" style="display: inline; margin-top: 0.5rem;">
                <input type="hidden" name="action" value="generate_certificate">
                <input type="hidden" name="participant_id" value="<?= $participant['id'] ?>">
                <button type="submit" class="btn">
                    <i class="fas fa-certificate"></i> Generate Certificate
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Generated Certificates -->
<h3><i class="fas fa-certificate"></i> Generated Certificates</h3>
<?php if (empty($certificates)): ?>
    <p style="text-align: center; color: #666; padding: 2rem;">No certificates generated yet.</p>
<?php else: ?>
    <div>
        <?php foreach ($certificates as $certificate): ?>
            <div class="certificate-item">
                <strong><?= htmlspecialchars($certificate['participant_name']) ?></strong>
                - <?= htmlspecialchars($certificate['course_name']) ?>
                <span style="float: right; background: #10b981; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.8rem;">
                    <?= $certificate['status'] ?>
                </span>
                <br>
                <small>
                    <?= htmlspecialchars($certificate['participant_email']) ?> | 
                    Course: <?= date('d-m-Y', strtotime($certificate['course_date'])) ?> | 
                    Generated: <?= date('d-m-Y H:i', strtotime($certificate['generated_date'])) ?>
                </small>
                <br>
                <?php if ($certificate['file_path'] && file_exists('../' . $certificate['file_path'])): ?>
                    <a href="../<?= htmlspecialchars($certificate['file_path']) ?>" class="btn" target="_blank">
                        <i class="fas fa-eye"></i> View PDF
                    </a>
                    <a href="../<?= htmlspecialchars($certificate['file_path']) ?>" class="btn" download>
                        <i class="fas fa-download"></i> Download
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php renderAdminFooter(); ?>