<?php
/**
 * INVENTIJN CERTIFICATES ADMIN INTERFACE v4.1 - UNIFIED NAVIGATION EDITION
 * Complete certificate management interface + Unified Navigation
 * 
 * Based on working v4.0 + added unified navigation header
 * All original functionality preserved 100%
 * 
 * Updated: 2025-06-09
 * Author: Martijn Planken & Claude
 * Status: Production Ready ✅ + Unified Navigation ✅
 */

// Production error handling
ini_set('display_errors', 0);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin authentication check
if (!isset($_SESSION['admin_user']) || empty($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=certificates.php');
    exit;
}

// Try to add unified navigation (completely optional)
$has_unified_nav = false;
if (file_exists('../includes/admin_template.php') && file_exists('../includes/config.php')) {
    try {
        require_once '../includes/admin_template.php';
        $unified_pdo = getDatabase();
        renderAdminHeader('Certificate Management', $unified_pdo);
        $has_unified_nav = true;
        
        // Start content container for unified navigation
        echo '<div style="max-width: 1400px; margin: 0 auto; padding: 0 2rem;">';
        
    } catch (Exception $e) {
        // If anything fails with unified nav, continue with original
        $has_unified_nav = false;
    }
}

// Continue with original working certificate system
// Load Composer autoloader (tested path from debug)
require_once '/var/www/vhosts/inventijn.nl/httpdocs/vendor/autoload.php';

// Load configuration (tested path from debug)
require_once '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/includes/config.php';

// Load CertificateGenerator (fixed version v2.1)
require_once '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/includes/CertificateGenerator.php';

// Initialize database and generator
try {
    $db = getMySQLiDatabase();
    $generator = new CertificateGenerator($db);
} catch (Exception $e) {
    die("System initialization failed. Please contact support.");
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'generate_certificate':
                $participant_id = (int)$_POST['participant_id'];
                $template = $_POST['template'] ?? 'default';
                $certificate_type = $_POST['certificate_type'] ?? 'deelname';
                
                $result = $generator->generateCertificate($participant_id, $template, $certificate_type);
                echo json_encode($result);
                exit;
                
            case 'send_certificate_email':
                $cert_id = (int)$_POST['certificate_id'];
                $email = $_POST['email'] ?? null;
                
                $result = $generator->sendCertificateByEmail($cert_id, $email);
                echo json_encode($result);
                exit;
                
            case 'bulk_generate':
                $participant_ids = json_decode($_POST['participant_ids'], true);
                if (!is_array($participant_ids)) {
                    throw new Exception("Invalid participant IDs format");
                }
                
                $template = $_POST['template'] ?? 'default';
                $certificate_type = $_POST['certificate_type'] ?? 'deelname';
                
                $results = [];
                foreach ($participant_ids as $id) {
                    $results[] = $generator->generateCertificate($id, $template, $certificate_type);
                }
                echo json_encode(['results' => $results]);
                exit;
                
            case 'delete_certificate':
                $cert_id = (int)$_POST['certificate_id'];
                
                // Get file path before deletion
                $stmt = $db->prepare("SELECT pdf_filename FROM certificates WHERE id = ?");
                $stmt->bind_param('i', $cert_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    throw new Exception("Certificate not found");
                }
                
                $filename = $result->fetch_assoc()['pdf_filename'];
                
                // Delete file
                $cert_dir = '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/certificates/';
                $filepath = $cert_dir . $filename;
                $file_deleted = false;
                
                if (file_exists($filepath)) {
                    unlink($filepath);
                    $file_deleted = true;
                }
                
                // Delete database record
                $stmt = $db->prepare("DELETE FROM certificates WHERE id = ?");
                $stmt->bind_param('i', $cert_id);
                $success = $stmt->execute();
                
                echo json_encode([
                    'success' => $success,
                    'file_deleted' => $file_deleted
                ]);
                exit;
                
            case 'load_course_participants':
                $course_id = (int)$_POST['course_id'];
                
                $query = "
                    SELECT 
                        cp.id as course_participant_id,
                        u.name as participant_name,
                        u.email as participant_email,
                        cp.payment_status,
                        cp.enrollment_date,
                        c.name as course_name,
                        c.course_date,
                        cert_check.existing_cert_id
                    FROM course_participants cp
                    JOIN users u ON cp.user_id = u.id
                    JOIN courses c ON cp.course_id = c.id
                    LEFT JOIN (
                        SELECT course_participant_id, id as existing_cert_id 
                        FROM certificates 
                        GROUP BY course_participant_id
                    ) cert_check ON cp.id = cert_check.course_participant_id
                    WHERE cp.course_id = ? 
                    ORDER BY cp.enrollment_date DESC
                ";
                
                $stmt = $db->prepare($query);
                $stmt->bind_param('i', $course_id);
                $stmt->execute();
                $participants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode(['participants' => $participants]);
                exit;
                
            default:
                throw new Exception("Unknown action");
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Handle file downloads
if (isset($_GET['download']) && isset($_GET['certificate_id'])) {
    $cert_id = (int)$_GET['certificate_id'];
    
    try {
        $stmt = $db->prepare("
            SELECT 
                c.pdf_filename, 
                u.name as participant_name
            FROM certificates c
            JOIN course_participants cp ON c.course_participant_id = cp.id
            JOIN users u ON cp.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->bind_param('i', $cert_id);
        $stmt->execute();
        $cert = $stmt->get_result()->fetch_assoc();
        
        if (!$cert) {
            throw new Exception("Certificate not found");
        }
        
        $cert_dir = '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/certificates/';
        $filepath = $cert_dir . $cert['pdf_filename'];
        
        if (!file_exists($filepath)) {
            throw new Exception("Certificate file not found");
        }
        
        // Update download tracking
        $stmt = $db->prepare("UPDATE certificates SET download_count = download_count + 1, last_download_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $cert_id);
        $stmt->execute();
        
        // Send file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $cert['pdf_filename'] . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
        
    } catch (Exception $e) {
        header('HTTP/1.0 404 Not Found');
        die("Download error: " . $e->getMessage());
    }
}

// Load data for the interface
$course_filter = $_GET['course_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// Build query conditions
$where_conditions = [];
$params = [];
$param_types = '';

if ($course_filter) {
    $where_conditions[] = "c.course_id = ?";
    $params[] = $course_filter;
    $param_types .= 'i';
}

if ($status_filter) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($search) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR course.name LIKE ? OR c.certificate_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'ssss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get certificates with pagination
$offset = ($page - 1) * $per_page;
$query = "
    SELECT 
        c.id,
        c.certificate_number,
        c.certificate_type,
        c.status,
        c.generated_at,
        c.download_count,
        c.last_download_at,
        c.pdf_filename,
        c.verification_token,
        c.template_used,
        u.name as participant_name,
        u.email as participant_email,
        u.company as participant_company,
        course.name as course_name,
        course.course_date,
        course.location as course_location
    FROM certificates c
    JOIN course_participants cp ON c.course_participant_id = cp.id
    JOIN users u ON cp.user_id = u.id
    JOIN courses course ON c.course_id = course.id
    $where_clause 
    ORDER BY c.generated_at DESC 
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$certificates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM certificates c
    JOIN course_participants cp ON c.course_participant_id = cp.id
    JOIN users u ON cp.user_id = u.id
    JOIN courses course ON c.course_id = course.id
    $where_clause
";
$stmt = $db->prepare($count_query);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_certificates = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_certificates / $per_page);

// Get courses for filter
$courses = $db->query("SELECT id, name FROM courses WHERE active = 1 ORDER BY course_date DESC")->fetch_all(MYSQLI_ASSOC);

// Get participants ready for certificates
$ready_participants_query = "
    SELECT 
        cp.id as course_participant_id,
        u.name as participant_name,
        u.email as participant_email,
        cp.payment_status,
        cp.enrollment_date,
        c.name as course_name,
        c.course_date,
        cert_check.existing_cert_id
    FROM course_participants cp
    JOIN users u ON cp.user_id = u.id
    JOIN courses c ON cp.course_id = c.id
    LEFT JOIN (
        SELECT course_participant_id, id as existing_cert_id 
        FROM certificates 
        GROUP BY course_participant_id
    ) cert_check ON cp.id = cert_check.course_participant_id
    WHERE cp.payment_status IN ('paid', 'pending')
    AND cert_check.existing_cert_id IS NULL
    ORDER BY c.course_date DESC, cp.enrollment_date DESC
    LIMIT 50
";

$ready_participants = $db->query($ready_participants_query)->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_certificates,
        COUNT(CASE WHEN status = 'generated' THEN 1 END) as generated,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
        COUNT(CASE WHEN status = 'downloaded' THEN 1 END) as downloaded,
        COALESCE(SUM(download_count), 0) as total_downloads
    FROM certificates
")->fetch_assoc();

// Only render original DOCTYPE if we don't have unified navigation
if (!$has_unified_nav) {
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificaten Beheer - Inventijn Admin</title>
<?php
} else {
    // If we have unified navigation, just add the title and styles
    echo '<title>Certificaten Beheer - Inventijn Admin</title>';
}
?>
    <style>
        /* Modern Admin Interface Styling - KEEP EXACTLY AS ORIGINAL */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            <?php if (!$has_unified_nav): ?>
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            <?php endif; ?>
            color: #2d3748;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .header h1 {
            font-size: 2.5em;
            font-weight: 700;
            background: linear-gradient(135deg, #3e5cc6 0%, #6b80e8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            color: #718096;
            font-size: 1.1em;
        }
        
        .system-status {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.3);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #2e7d32;
            font-weight: 500;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            background: linear-gradient(135deg, #3e5cc6 0%, #6b80e8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .stat-card .label {
            color: #718096;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            background: transparent;
            border: none;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #718096;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #3e5cc6 0%, #6b80e8 100%);
            color: white;
        }
        
        .tab:hover:not(.active) {
            background: rgba(62, 92, 198, 0.1);
            color: #3e5cc6;
        }
        
        .tab-content {
            display: none;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .filters {
            padding: 30px;
            background: rgba(248, 250, 252, 0.8);
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 0.9em;
            color: #4a5568;
            font-weight: 600;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 12px 16px;
            border: 2px solid rgba(226, 232, 240, 0.8);
            border-radius: 10px;
            font-size: 0.9em;
            background: white;
            transition: border-color 0.3s ease;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3e5cc6;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3e5cc6 0%, #6b80e8 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(62, 92, 198, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(62, 92, 198, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #38a169 0%, #48bb78 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(56, 161, 105, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(56, 161, 105, 0.4);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #d69e2e 0%, #ecc94b 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(214, 158, 46, 0.3);
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(214, 158, 46, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e53e3e 0%, #fc8181 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(229, 62, 62, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(229, 62, 62, 0.4);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.8em;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: rgba(248, 250, 252, 0.8);
            padding: 20px 15px;
            text-align: left;
            font-weight: 700;
            color: #2d3748;
            border-bottom: 2px solid rgba(226, 232, 240, 0.5);
            font-size: 0.9em;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.3);
            vertical-align: middle;
        }
        
        .table tr:hover {
            background: rgba(248, 250, 252, 0.5);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-generated {
            background: rgba(59, 130, 246, 0.1);
            color: #1d4ed8;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .status-sent {
            background: rgba(34, 197, 94, 0.1);
            color: #15803d;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        
        .status-downloaded {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 12px 16px;
            border: 2px solid rgba(226, 232, 240, 0.8);
            color: #4a5568;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .pagination .current {
            background: linear-gradient(135deg, #3e5cc6 0%, #6b80e8 100%);
            color: white;
            border-color: transparent;
        }
        
        .pagination a:hover {
            background: rgba(62, 92, 198, 0.1);
            border-color: #3e5cc6;
            color: #3e5cc6;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 40px;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(226, 232, 240, 0.5);
        }
        
        .modal-header h3 {
            margin: 0;
            color: #3e5cc6;
            font-size: 1.5em;
            font-weight: 700;
        }
        
        .close {
            font-size: 28px;
            cursor: pointer;
            color: #a0aec0;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: #e53e3e;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid rgba(226, 232, 240, 0.8);
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #3e5cc6;
        }
        
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #15803d;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            border: 4px solid rgba(62, 92, 198, 0.2);
            border-top: 4px solid #3e5cc6;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .checkbox-group {
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid rgba(226, 232, 240, 0.8);
            border-radius: 10px;
            padding: 20px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(241, 245, 249, 0.8);
        }
        
        .checkbox-item:last-child {
            border-bottom: none;
        }
        
        .checkbox-item input {
            margin-right: 15px;
            width: auto;
            transform: scale(1.2);
        }
        
        .participant-info {
            flex: 1;
            display: flex;
            justify-content: space-between;
        }
        
        .participant-name {
            font-weight: 600;
            color: #2d3748;
        }
        
        .participant-course {
            color: #718096;
            font-size: 0.9em;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #718096;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #4a5568;
        }
    </style>
<?php if (!$has_unified_nav): ?>
</head>
<body>
<?php endif; ?>
    <div class="container">
        <!-- System Status -->
        <div class="system-status">
            ✅ Inventijn Certificate System v4.1 - All Systems Operational | Admin: <?= htmlspecialchars($_SESSION['admin_user']) ?>
            <?php if ($has_unified_nav): ?>
                | 🎯 Unified Navigation Active
            <?php endif; ?>
        </div>
        
        <!-- Header -->
        <div class="header">
            <h1>Certificaten Beheer</h1>
            <div class="subtitle">Inventijn Certificate System v4.1 - Unified Navigation Edition</div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= number_format($stats['total_certificates']) ?></div>
                <div class="label">Totaal Certificaten</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= number_format($stats['generated']) ?></div>
                <div class="label">Gegenereerd</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= number_format($stats['sent']) ?></div>
                <div class="label">Verzonden</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= number_format($stats['downloaded']) ?></div>
                <div class="label">Gedownload</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= number_format($stats['total_downloads']) ?></div>
                <div class="label">Downloads</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('certificates')">Bestaande Certificaten</button>
            <button class="tab" onclick="showTab('generate')">Nieuwe Certificaten</button>
            <button class="tab" onclick="showTab('bulk')">Bulk Generatie</button>
        </div>
        
        <!-- Certificates Tab -->
        <div id="certificates-tab" class="tab-content active">
            <!-- Filters -->
            <div class="filters">
                <form method="GET" action="" style="display: flex; gap: 20px; flex-wrap: wrap; align-items: end; width: 100%;">
                    <div class="filter-group">
                        <label for="course_id">Cursus</label>
                        <select name="course_id" id="course_id">
                            <option value="">Alle cursussen</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Alle statussen</option>
                            <option value="generated" <?= $status_filter == 'generated' ? 'selected' : '' ?>>Gegenereerd</option>
                            <option value="sent" <?= $status_filter == 'sent' ? 'selected' : '' ?>>Verzonden</option>
                            <option value="downloaded" <?= $status_filter == 'downloaded' ? 'selected' : '' ?>>Gedownload</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Zoeken</label>
                        <input type="text" name="search" id="search" placeholder="Naam, email, cursus..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?" class="btn" style="background: rgba(226, 232, 240, 0.8); color: #4a5568;">Reset</a>
                </form>
            </div>
            
            <!-- Certificates Table -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Certificaat #</th>
                            <th>Deelnemer</th>
                            <th>Cursus</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Gegenereerd</th>
                            <th>Downloads</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificates as $cert): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($cert['certificate_number']) ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <div class="participant-name"><?= htmlspecialchars($cert['participant_name']) ?></div>
                                        <div class="participant-course"><?= htmlspecialchars($cert['participant_email']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?= htmlspecialchars($cert['course_name']) ?><br>
                                        <small style="color: #718096;"><?= date('d-m-Y', strtotime($cert['course_date'])) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span style="text-transform: capitalize;"><?= htmlspecialchars($cert['certificate_type']) ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $cert['status'] ?>">
                                        <?= ucfirst($cert['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= date('d-m-Y H:i', strtotime($cert['generated_at'])) ?>
                                </td>
                                <td>
                                    <strong><?= $cert['download_count'] ?>×</strong>
                                    <?php if ($cert['last_download_at']): ?>
                                        <br><small style="color: #718096;">
                                            Laatst: <?= date('d-m H:i', strtotime($cert['last_download_at'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <a href="?download=1&certificate_id=<?= $cert['id'] ?>" 
                                           class="btn btn-primary btn-sm">Download</a>
                                        
                                        <button onclick="sendCertificateEmail(<?= $cert['id'] ?>, '<?= htmlspecialchars($cert['participant_email']) ?>')" 
                                                class="btn btn-success btn-sm">Email</button>
                                        
                                        <a href="../verify-certificate.php?token=<?= $cert['verification_token'] ?>" 
                                           target="_blank" class="btn btn-warning btn-sm">Verifieer</a>
                                        
                                        <button onclick="deleteCertificate(<?= $cert['id'] ?>)" 
                                                class="btn btn-danger btn-sm">Verwijder</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($certificates)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <h3>Geen certificaten gevonden</h3>
                                        <p>Er zijn geen certificaten die voldoen aan de filterinstellingen.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&course_id=<?= $course_filter ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Generate Tab -->
        <div id="generate-tab" class="tab-content">
            <div style="padding: 40px;">
                <h3 style="margin-bottom: 20px; color: #3e5cc6;">Nieuwe Certificaten Genereren</h3>
                <p style="margin-bottom: 30px; color: #718096;">Selecteer deelnemers die klaar zijn voor een certificaat:</p>
                
                <?php if (empty($ready_participants)): ?>
                    <div class="alert alert-error">
                        <strong>Geen deelnemers gevonden</strong><br>
                        Er zijn geen deelnemers die klaar zijn voor een certificaat.
                        Zorg ervoor dat deelnemers de status 'paid' of 'pending' hebben.
                    </div>
                <?php else: ?>
                    <form id="generate-form">
                        <div class="form-group">
                            <label>Template</label>
                            <select name="template" required>
                                <option value="default">Standaard Template</option>
                                <option value="premium">Premium Template</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Certificaat Type</label>
                            <select name="certificate_type" required>
                                <option value="deelname">Deelname</option>
                                <option value="voltooiing">Voltooiing</option>
                                <option value="waardering">Waardering</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Deelnemers</label>
                            <div class="checkbox-group">
                                <?php foreach ($ready_participants as $participant): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="participant_ids[]" value="<?= $participant['course_participant_id'] ?>">
                                        <div class="participant-info">
                                            <div>
                                                <div class="participant-name"><?= htmlspecialchars($participant['participant_name']) ?></div>
                                                <div class="participant-course"><?= htmlspecialchars($participant['course_name']) ?></div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div><?= date('d-m-Y', strtotime($participant['course_date'])) ?></div>
                                                <div style="color: #718096; font-size: 0.8em;"><?= ucfirst($participant['payment_status']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Certificaten Genereren</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bulk Tab -->
        <div id="bulk-tab" class="tab-content">
            <div style="padding: 40px;">
                <h3 style="margin-bottom: 20px; color: #3e5cc6;">Bulk Certificaat Generatie</h3>
                <p style="margin-bottom: 30px; color: #718096;">Genereer certificaten voor alle deelnemers van een specifieke cursus:</p>
                
                <form id="bulk-form">
                    <div class="form-group">
                        <label>Cursus</label>
                        <select name="course_id" required onchange="loadCourseParticipants(this.value)">
                            <option value="">Selecteer cursus...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>">
                                    <?= htmlspecialchars($course['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Template</label>
                        <select name="template" required>
                            <option value="default">Standaard Template</option>
                            <option value="premium">Premium Template</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Certificaat Type</label>
                        <select name="certificate_type" required>
                            <option value="deelname">Deelname</option>
                            <option value="voltooiing">Voltooiing</option>
                            <option value="waardering">Waardering</option>
                        </select>
                    </div>
                    
                    <div id="bulk-participants" style="display: none;">
                        <div class="form-group">
                            <label>Deelnemers voor deze cursus</label>
                            <div id="bulk-participant-list" class="checkbox-group">
                                <!-- Loaded via AJAX -->
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Bulk Genereren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Loading Modal -->
    <div id="loading-modal" class="modal">
        <div class="modal-content" style="text-align: center;">
            <div class="spinner"></div>
            <h3>Certificaten worden gegenereerd...</h3>
            <p>Even geduld, dit kan enkele seconden duren.</p>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <div id="messages" style="position: fixed; top: 20px; right: 20px; z-index: 2000;"></div>

    <script>
        // Modern AJAX handling with better error management - KEEP ALL ORIGINAL JAVASCRIPT
        async function makeRequest(url, data) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded' 
                    },
                    body: data
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return await response.json();
                
            } catch (error) {
                console.error('Request failed:', error);
                throw error;
            }
        }
        
        // Tab switching with smooth animations
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        // Enhanced message system
        function showMessage(message, type = 'success', duration = 5000) {
            const messagesDiv = document.getElementById('messages');
            const messageEl = document.createElement('div');
            messageEl.className = `alert alert-${type === 'error' ? 'error' : 'success'}`;
            messageEl.style.marginBottom = '10px';
            messageEl.style.minWidth = '300px';
            messageEl.style.transform = 'translateX(100%)';
            messageEl.style.transition = 'transform 0.3s ease';
            messageEl.innerHTML = message;
            
            messagesDiv.appendChild(messageEl);
            
            // Slide in animation
            setTimeout(() => {
                messageEl.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto remove
            setTimeout(() => {
                if (messagesDiv.contains(messageEl)) {
                    messageEl.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (messagesDiv.contains(messageEl)) {
                            messagesDiv.removeChild(messageEl);
                        }
                    }, 300);
                }
            }, duration);
        }
        
        // Generate single certificates
        document.getElementById('generate-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const participantIds = Array.from(formData.getAll('participant_ids[]'));
            
            if (participantIds.length === 0) {
                showMessage('Selecteer minimaal één deelnemer.', 'error');
                return;
            }
            
            document.getElementById('loading-modal').style.display = 'block';
            
            let successCount = 0;
            let errorCount = 0;
            let errors = [];
            
            for (const participantId of participantIds) {
                try {
                    const data = `action=generate_certificate&participant_id=${participantId}&template=${formData.get('template')}&certificate_type=${formData.get('certificate_type')}`;
                    const result = await makeRequest('', data);
                    
                    if (result.success) {
                        successCount++;
                    } else {
                        errorCount++;
                        errors.push(result.error || 'Unknown error');
                    }
                } catch (error) {
                    errorCount++;
                    errors.push(error.message);
                }
            }
            
            document.getElementById('loading-modal').style.display = 'none';
            
            if (successCount > 0) {
                showMessage(`🎉 ${successCount} certificaten succesvol gegenereerd!`);
                setTimeout(() => location.reload(), 2000);
            }
            
            if (errorCount > 0) {
                showMessage(`⚠️ ${errorCount} certificaten konden niet worden gegenereerd. Errors: ${errors.slice(0, 3).join(', ')}`, 'error');
            }
        });
        
        // Send certificate email
        async function sendCertificateEmail(certificateId, email) {
            try {
                showMessage('📧 Email wordt verzonden...', 'info', 2000);
                
                const data = `action=send_certificate_email&certificate_id=${certificateId}&email=${email}`;
                const result = await makeRequest('', data);
                
                if (result.success) {
                    showMessage(`✅ Certificaat verzonden naar ${result.recipient}`);
                } else {
                    showMessage('❌ Fout bij verzenden email: ' + (result.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                showMessage('❌ Netwerkfout bij verzenden email: ' + error.message, 'error');
            }
        }
        
        // Delete certificate
        async function deleteCertificate(certificateId) {
            if (!confirm('Weet u zeker dat u dit certificaat wilt verwijderen?')) {
                return;
            }
            
            try {
                const data = `action=delete_certificate&certificate_id=${certificateId}`;
                const result = await makeRequest('', data);
                
                if (result.success) {
                    showMessage('🗑️ Certificaat verwijderd.');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage('❌ Fout bij verwijderen certificaat.', 'error');
                }
            } catch (error) {
                showMessage('❌ Netwerkfout bij verwijderen: ' + error.message, 'error');
            }
        }
        
        // Load course participants for bulk generation
        async function loadCourseParticipants(courseId) {
            if (!courseId) {
                document.getElementById('bulk-participants').style.display = 'none';
                return;
            }
            
            try {
                const data = `action=load_course_participants&course_id=${courseId}`;
                const result = await makeRequest('', data);
                
                if (result.participants) {
                    const listEl = document.getElementById('bulk-participant-list');
                    listEl.innerHTML = '';
                    
                    result.participants.forEach(participant => {
                        const div = document.createElement('div');
                        div.className = 'checkbox-item';
                        
                        const hasExisting = participant.existing_cert_id ? ' (heeft al certificaat)' : '';
                        const isDisabled = participant.existing_cert_id ? 'disabled' : '';
                        
                        div.innerHTML = `
                            <input type="checkbox" name="participant_ids[]" value="${participant.course_participant_id}" ${isDisabled}>
                            <div class="participant-info">
                                <div>
                                    <div class="participant-name">${participant.participant_name}${hasExisting}</div>
                                    <div class="participant-course">${participant.participant_email}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div>${new Date(participant.enrollment_date).toLocaleDateString('nl-NL')}</div>
                                    <div style="color: #718096; font-size: 0.8em;">${participant.payment_status}</div>
                                </div>
                            </div>
                        `;
                        
                        listEl.appendChild(div);
                    });
                    
                    document.getElementById('bulk-participants').style.display = 'block';
                } else {
                    showMessage('❌ Kon deelnemers niet laden.', 'error');
                }
                
            } catch (error) {
                showMessage('❌ Fout bij laden deelnemers: ' + error.message, 'error');
            }
        }
        
        // Bulk form submission
        document.getElementById('bulk-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const participantIds = Array.from(formData.getAll('participant_ids[]'));
            
            if (participantIds.length === 0) {
                showMessage('Selecteer minimaal één deelnemer.', 'error');
                return;
            }
            
            document.getElementById('loading-modal').style.display = 'block';
            
            try {
                const data = `action=bulk_generate&participant_ids=${encodeURIComponent(JSON.stringify(participantIds))}&template=${formData.get('template')}&certificate_type=${formData.get('certificate_type')}`;
                const result = await makeRequest('', data);
                
                document.getElementById('loading-modal').style.display = 'none';
                
                if (result.results) {
                    const successCount = result.results.filter(r => r.success).length;
                    const errorCount = result.results.filter(r => !r.success).length;
                    
                    if (successCount > 0) {
                        showMessage(`🎉 ${successCount} certificaten succesvol gegenereerd!`);
                    }
                    
                    if (errorCount > 0) {
                        showMessage(`⚠️ ${errorCount} certificaten konden niet worden gegenereerd.`, 'error');
                    }
                    
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showMessage('❌ Onverwacht resultaat van bulk generatie.', 'error');
                }
                
            } catch (error) {
                document.getElementById('loading-modal').style.display = 'none';
                showMessage('❌ Fout bij bulk generatie: ' + error.message, 'error');
            }
        });
        
        // Initialize tooltips and other UI enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states to buttons
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.type === 'submit') {
                        this.style.opacity = '0.7';
                        this.style.pointerEvents = 'none';
                        setTimeout(() => {
                            this.style.opacity = '1';
                            this.style.pointerEvents = 'auto';
                        }, 2000);
                    }
                });
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === '1') {
                e.preventDefault();
                showTab('certificates');
            } else if (e.ctrlKey && e.key === '2') {
                e.preventDefault();
                showTab('generate');
            } else if (e.ctrlKey && e.key === '3') {
                e.preventDefault();
                showTab('bulk');
            }
        });
        
        console.log('🎉 Inventijn Certificate System v4.1 - Unified Navigation Edition Ready!');
    </script>

<?php 
// Close HTML properly
if ($has_unified_nav) {
    echo '</div>'; // Close unified navigation container
    if (function_exists('renderAdminFooter')) {
        renderAdminFooter();
    } else {
        echo '</body></html>';
    }
} else {
    echo '</body></html>';
}
?>