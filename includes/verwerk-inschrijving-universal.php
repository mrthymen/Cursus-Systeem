<?php
/**
 * Debug Processor with Custom Logging v6.3.3
 * Creates its own debug log file that you can read via FTP
 * ADDED: Custom logging system when server error log is not configured
 */

// CREATE CUSTOM ERROR LOG SYSTEM
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/cursus-systeem/php_errors.log');
ini_set('log_errors', 1);
ini_set('display_errors', 0);

// Custom debug logging function
function debugLog($message, $step = '') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] STEP $step: $message\n";
    
    // Write to our custom debug file
    file_put_contents(__DIR__ . '/debug_processor.log', $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also write to system error log if available
    error_log($logEntry);
}

// Clean any existing output
if (ob_get_level()) {
    ob_clean();
}

debugLog("=== PROCESSOR START ===", "START");
debugLog("Request method: " . $_SERVER['REQUEST_METHOD'], "INIT");
debugLog("POST data count: " . count($_POST), "INIT");

session_start();
debugLog("Session started successfully", "1");

require_once 'config.php';
debugLog("Config loaded successfully", "2");

// Email Configuration Constants with SAFE fallbacks
if (!defined('SITE_NAME')) define('SITE_NAME', 'Inventijn');
if (!defined('SITE_URL')) define('SITE_URL', 'https://inventijn.nl');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'martijn@inventijn.nl');
if (!defined('FROM_EMAIL')) define('FROM_EMAIL', 'martijn@inventijn.nl'); // Use your working email
if (!defined('FROM_NAME')) define('FROM_NAME', 'Inventijn Training');

debugLog("Email constants defined - ADMIN_EMAIL: " . ADMIN_EMAIL, "3");

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: text/plain; charset=utf-8');

debugLog("Security headers set", "4");

// Security: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("ERROR: Invalid method - " . $_SERVER['REQUEST_METHOD'], "ERROR");
    http_response_code(405);
    echo 'method_not_allowed';
    exit;
}

debugLog("POST method confirmed", "5");

// Honeypot spam protection
if (!empty($_POST['website'])) {
    debugLog("SECURITY: Spam detected in honeypot field", "SECURITY");
    echo 'spam_detected';
    exit;
}

debugLog("Honeypot check passed", "6");

// Log received POST data (safely)
$postKeys = array_keys($_POST);
debugLog("POST fields received: " . implode(', ', $postKeys), "7");

// Get database connection
try {
    debugLog("Attempting database connection...", "8");
    $pdo = getDatabase();
    debugLog("Database connection successful", "9");
} catch (Exception $e) {
    debugLog("DATABASE ERROR: " . $e->getMessage(), "ERROR");
    echo 'database_error';
    exit;
}

// Validate required fields
$required_fields = ['training_type', 'training_name', 'naam', 'email'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        debugLog("VALIDATION ERROR: Missing field - $field", "ERROR");
        echo "missing_field_$field";
        exit;
    }
}

debugLog("Required fields validation passed", "10");

// Sanitize input data
$data = [
    'training_type' => trim($_POST['training_type']),
    'training_name' => trim($_POST['training_name']),
    'naam' => trim($_POST['naam']),
    'email' => filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL),
    'telefoon' => trim($_POST['telefoon'] ?? ''),
    'organisatie' => trim($_POST['organisatie'] ?? ''),
    'selected_course_id' => $_POST['selected_course_id'] ?? null,
    'opmerkingen' => trim($_POST['opmerkingen'] ?? ''),
];

debugLog("Data sanitized - Training: {$data['training_name']}, Email: {$data['email']}", "11");

// Validate email
if (!$data['email']) {
    debugLog("VALIDATION ERROR: Invalid email format", "ERROR");
    echo 'invalid_email';
    exit;
}

// Validate name
if (strlen($data['naam']) < 2) {
    debugLog("VALIDATION ERROR: Name too short", "ERROR");
    echo 'invalid_name';
    exit;
}

debugLog("Email and name validation passed", "12");

// ===== SIMPLE EMAIL FUNCTIONS WITH EXTENSIVE LOGGING =====

/**
 * Simple Email Function with Debug
 */
function sendSimpleEmail($to_email, $to_name, $subject, $message) {
    debugLog("EMAIL: Starting send to $to_email", "EMAIL_START");
    debugLog("EMAIL: Subject - $subject", "EMAIL_PREP");
    
    try {
        // Test if mail function exists
        if (!function_exists('mail')) {
            debugLog("EMAIL ERROR: mail() function not available", "EMAIL_ERROR");
            return false;
        }
        
        // Simple headers
        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . ADMIN_EMAIL;
        
        debugLog("EMAIL: Headers prepared", "EMAIL_PREP");
        
        // Attempt to send
        debugLog("EMAIL: Calling mail() function...", "EMAIL_SEND");
        $success = mail($to_email, $subject, $message, implode("\r\n", $headers));
        
        debugLog("EMAIL: mail() returned: " . ($success ? 'TRUE' : 'FALSE'), "EMAIL_RESULT");
        
        return $success;
        
    } catch (Exception $e) {
        debugLog("EMAIL EXCEPTION: " . $e->getMessage(), "EMAIL_ERROR");
        return false;
    } catch (Error $e) {
        debugLog("EMAIL FATAL ERROR: " . $e->getMessage(), "EMAIL_ERROR");
        return false;
    }
}

/**
 * Simple Email Content Generator
 */
function getSimpleEmailContent($type, $data) {
    debugLog("EMAIL: Generating content for type: $type", "EMAIL_CONTENT");
    
    $naam = htmlspecialchars($data['naam']);
    $training = htmlspecialchars($data['training_name']);
    
    $templates = [
        'enrollment' => [
            'subject' => "Inschrijving bevestigd: $training",
            'participant' => "<h2>Inschrijving Bevestigd!</h2><p>Beste $naam,</p><p>Je inschrijving voor <strong>$training</strong> is ontvangen!</p><p>Met vriendelijke groet,<br>Martijn Planken</p>",
            'admin' => "<h2>Nieuwe Inschrijving</h2><p><strong>Training:</strong> $training</p><p><strong>Naam:</strong> $naam</p><p><strong>Email:</strong> {$data['email']}</p>"
        ],
        'interest' => [
            'subject' => "Interesse geregistreerd: $training", 
            'participant' => "<h2>Interesse Geregistreerd</h2><p>Beste $naam,</p><p>Bedankt voor je interesse in <strong>$training</strong>!</p><p>Met vriendelijke groet,<br>Martijn Planken</p>",
            'admin' => "<h2>Nieuwe Interesse</h2><p><strong>Training:</strong> $training</p><p><strong>Naam:</strong> $naam</p>"
        ],
        'incompany' => [
            'subject' => "Incompany aanvraag: $training",
            'participant' => "<h2>Incompany Aanvraag</h2><p>Beste $naam,</p><p>Je aanvraag voor <strong>$training</strong> is ontvangen!</p><p>Met vriendelijke groet,<br>Martijn Planken</p>",
            'admin' => "<h2>Incompany Aanvraag</h2><p><strong>Training:</strong> $training</p><p><strong>Naam:</strong> $naam</p>"
        ]
    ];
    
    debugLog("EMAIL: Template found for type: $type", "EMAIL_CONTENT");
    return $templates[$type] ?? $templates['interest'];
}

/**
 * Main Email Sending Function
 */
function sendConfirmationEmail($type, $data, $result_id = null) {
    debugLog("EMAIL: sendConfirmationEmail called - Type: $type", "EMAIL_MAIN");
    
    try {
        $email_content = getSimpleEmailContent($type, $data);
        
        if (!$email_content) {
            debugLog("EMAIL ERROR: No template found for type: $type", "EMAIL_ERROR");
            return false;
        }
        
        debugLog("EMAIL: Sending participant email...", "EMAIL_PARTICIPANT");
        // Send to participant
        $participant_sent = sendSimpleEmail(
            $data['email'],
            $data['naam'],
            $email_content['subject'],
            $email_content['participant']
        );
        
        debugLog("EMAIL: Participant email result: " . ($participant_sent ? 'SUCCESS' : 'FAILED'), "EMAIL_PARTICIPANT");
        
        debugLog("EMAIL: Sending admin email...", "EMAIL_ADMIN");
        // Send to admin  
        $admin_sent = sendSimpleEmail(
            ADMIN_EMAIL,
            'Martijn Planken',
            'Admin: ' . $email_content['subject'],
            $email_content['admin']
        );
        
        debugLog("EMAIL: Admin email result: " . ($admin_sent ? 'SUCCESS' : 'FAILED'), "EMAIL_ADMIN");
        
        debugLog("EMAIL: sendConfirmationEmail completed", "EMAIL_MAIN");
        return $participant_sent;
        
    } catch (Exception $e) {
        debugLog("EMAIL FUNCTION ERROR: " . $e->getMessage(), "EMAIL_ERROR");
        return false;
    }
}

// ===== DATABASE FUNCTIONS (same as before, working) =====

function createOrGetUser($pdo, $naam, $email, $telefoon = '', $organisatie = '') {
    debugLog("DB: Creating/getting user for email: $email", "DB_USER");
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            debugLog("DB: User exists, updating - ID: " . $existing_user['id'], "DB_USER");
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, company = ?, updated_at = NOW() WHERE email = ?");
            $stmt->execute([$naam, $telefoon, $organisatie, $email]);
            return $existing_user['id'];
        } else {
            debugLog("DB: Creating new user", "DB_USER");
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, company, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$naam, $email, $telefoon, $organisatie]);
            $user_id = $pdo->lastInsertId();
            debugLog("DB: New user created - ID: $user_id", "DB_USER");
            return $user_id;
        }
    } catch (Exception $e) {
        debugLog("DB ERROR: User creation failed - " . $e->getMessage(), "DB_ERROR");
        return false;
    }
}

function createInterest($pdo, $data) {
    debugLog("DB: Creating interest record", "DB_INTEREST");
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO interests 
            (naam, email, telefoon, organisatie, training_type, training_name, opmerkingen, source, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'universal_form', NOW())
        ");
        
        $stmt->execute([
            $data['naam'],
            $data['email'], 
            $data['telefoon'],
            $data['organisatie'],
            $data['training_type'],
            $data['training_name'],
            $data['opmerkingen']
        ]);
        
        $interest_id = $pdo->lastInsertId();
        debugLog("DB: Interest created - ID: $interest_id", "DB_INTEREST");
        return $interest_id;
        
    } catch (Exception $e) {
        debugLog("DB ERROR: Interest creation failed - " . $e->getMessage(), "DB_ERROR");
        return false;
    }
}

// ===== MAIN PROCESSING =====

try {
    debugLog("MAIN: Starting main processing logic", "MAIN_START");
    $pdo->beginTransaction();
    debugLog("MAIN: Transaction started", "MAIN_TRANS");
    
    // Create user
    debugLog("MAIN: Creating user...", "MAIN_USER");
    $user_id = createOrGetUser($pdo, $data['naam'], $data['email'], $data['telefoon'], $data['organisatie']);
    
    if (!$user_id) {
        throw new Exception("Failed to create/update user");
    }
    
    debugLog("MAIN: User created/updated - ID: $user_id", "MAIN_USER");
    
    // For simplicity, let's just create an interest record for all types for now
    debugLog("MAIN: Creating interest record...", "MAIN_INTEREST");
    $result = createInterest($pdo, $data);
    
    if (!$result) {
        throw new Exception("Failed to create interest");
    }
    
    debugLog("MAIN: Interest created - ID: $result", "MAIN_INTEREST");
    
    // Determine email type
    $email_type = ($data['training_type'] === 'incompany') ? 'incompany' : 'interest';
    debugLog("MAIN: Email type determined: $email_type", "MAIN_EMAIL");
    
    // Send confirmation email
    debugLog("MAIN: Calling sendConfirmationEmail...", "MAIN_EMAIL");
    $email_sent = sendConfirmationEmail($email_type, $data, $result);
    debugLog("MAIN: Email sending completed - Result: " . ($email_sent ? 'SUCCESS' : 'FAILED'), "MAIN_EMAIL");
    
    // Commit transaction
    debugLog("MAIN: Committing transaction...", "MAIN_COMMIT");
    $pdo->commit();
    debugLog("MAIN: Transaction committed successfully", "MAIN_COMMIT");
    
    // Send response
    $response = 'interest_success';
    debugLog("MAIN: Sending response: $response", "MAIN_RESPONSE");
    echo $response;
    
    debugLog("MAIN: Processing completed successfully", "MAIN_END");
    
} catch (Exception $e) {
    debugLog("MAIN ERROR: " . $e->getMessage(), "MAIN_ERROR");
    $pdo->rollback();
    debugLog("MAIN: Transaction rolled back", "MAIN_ERROR");
    echo 'error: ' . $e->getMessage();
}

debugLog("=== PROCESSOR END ===", "END");
?>