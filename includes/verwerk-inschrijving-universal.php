<?php
/**
 * DEBUG VERSION - Enhanced Registration Processor
 * With extensive logging to find where execution stops
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Clean output buffer
if (ob_get_level()) {
    ob_clean();
}

// Debug function to log AND output
function debugLog($message, $step = '') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] STEP $step: $message";
    error_log($logMessage);
    
    // Also echo for immediate visibility
    echo "<!-- DEBUG: $logMessage -->\n";
}

debugLog("Script started", "1");

session_start();
debugLog("Session started", "2");

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');  
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: text/plain; charset=utf-8');

debugLog("Headers set", "3");

// Security: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Invalid request method: " . $_SERVER['REQUEST_METHOD'], "ERROR");
    http_response_code(405);
    echo 'method_not_allowed';
    exit;
}

debugLog("POST method confirmed", "4");

// Honeypot spam protection
if (!empty($_POST['website'])) {
    debugLog("Spam detected: " . $_POST['website'], "SECURITY");
    echo 'spam_detected';
    exit;
}

debugLog("Honeypot check passed", "5");

// Check if config exists
if (!file_exists('config.php')) {
    debugLog("Config file not found", "ERROR");
    echo 'config_missing';
    exit;
}

debugLog("Config file exists", "6");

require_once 'config.php';
debugLog("Config loaded", "7");

// Get database connection with error handling
try {
    debugLog("Attempting database connection", "8");
    $pdo = getDatabase();
    debugLog("Database connection successful", "9");
} catch (Exception $e) {
    debugLog("Database connection failed: " . $e->getMessage(), "ERROR");
    echo 'database_connection_failed';
    exit;
}

// Log POST data (sanitized)
$postData = $_POST;
if (isset($postData['email'])) {
    $postData['email'] = '***@' . substr($postData['email'], strpos($postData['email'], '@') + 1);
}
debugLog("POST data received: " . json_encode($postData), "10");

// Validate required fields
$required_fields = ['training_type', 'training_name', 'naam', 'email'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        debugLog("Missing required field: $field", "VALIDATION");
        echo "missing_field_$field";
        exit;
    }
}

debugLog("Required fields validation passed", "11");

// Sanitize input data
$data = [
    'training_type' => trim($_POST['training_type']),
    'training_name' => trim($_POST['training_name']),
    'naam' => trim($_POST['naam']),
    'email' => filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL),
    'telefoon' => trim($_POST['telefoon'] ?? ''),
    'organisatie' => trim($_POST['organisatie'] ?? ''),
    'selected_course_id' => $_POST['selected_course_id'] ?? null,
    'course_id' => $_POST['course_id'] ?? null,
    'category' => $_POST['category'] ?? null,
    'periode' => $_POST['periode'] ?? [],
    'opmerkingen' => trim($_POST['opmerkingen'] ?? ''),
];

debugLog("Data sanitized", "12");

// Validate email
if (!$data['email']) {
    debugLog("Invalid email: " . ($_POST['email'] ?? 'empty'), "VALIDATION");
    echo 'invalid_email';
    exit;
}

debugLog("Email validation passed", "13");

// Validate name
if (strlen($data['naam']) < 2) {
    debugLog("Invalid name: " . $data['naam'], "VALIDATION");
    echo 'invalid_name';
    exit;
}

debugLog("Name validation passed", "14");

// Log processing decision
debugLog("Processing registration: {$data['training_type']} for {$data['naam']}", "15");

// Helper function with logging
function createOrGetUser($pdo, $naam, $email, $telefoon = '', $organisatie = '') {
    debugLog("Creating/getting user: $email", "16");
    
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            debugLog("Existing user found: " . $existing_user['id'], "17a");
            // Update existing user info
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    name = ?, telefoon = ?, organisatie = ?, updated_at = NOW()
                WHERE email = ?
            ");
            $stmt->execute([$naam, $telefoon, $organisatie, $email]);
            debugLog("User updated: " . $existing_user['id'], "17b");
            return $existing_user['id'];
        } else {
            debugLog("Creating new user", "18a");
            // Create new user
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, telefoon, organisatie, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$naam, $email, $telefoon, $organisatie]);
            
            $user_id = $pdo->lastInsertId();
            debugLog("New user created: $user_id", "18b");
            return $user_id;
        }
    } catch (Exception $e) {
        debugLog("User creation error: " . $e->getMessage(), "ERROR");
        return false;
    }
}

// Main processing with extensive logging
try {
    debugLog("Starting transaction", "19");
    $pdo->beginTransaction();
    
    // Create or get user
    debugLog("Creating/getting user", "20");
    $user_id = createOrGetUser($pdo, $data['naam'], $data['email'], $data['telefoon'], $data['organisatie']);
    
    if (!$user_id) {
        throw new Exception("Failed to create/update user");
    }
    
    debugLog("User ID obtained: $user_id", "21");
    
    $result = null;
    $response_type = null;
    
    // Determine processing path
    if ($data['training_type'] === 'incompany') {
        debugLog("Processing incompany request", "22a");
        // For now, simple success response
        $response_type = 'incompany_created';
        debugLog("Incompany response set", "22b");
        
    } else if (!empty($data['selected_course_id']) && $data['selected_course_id'] !== 'other' && is_numeric($data['selected_course_id'])) {
        debugLog("Processing direct enrollment for course: " . $data['selected_course_id'], "23a");
        // For now, simple enrollment response
        $response_type = 'enrolled';
        debugLog("Enrollment response set", "23b");
        
    } else {
        debugLog("Processing interest registration", "24a");
        // For now, simple interest response
        $response_type = 'interest_created';
        debugLog("Interest response set", "24b");
    }
    
    debugLog("Committing transaction", "25");
    $pdo->commit();
    
    debugLog("Transaction committed, response type: $response_type", "26");
    
    // Return appropriate response
    switch ($response_type) {
        case 'enrolled':
            debugLog("Outputting enrolled_payment_required", "27a");
            echo 'enrolled_payment_required';
            break;
        case 'incompany_created':
            debugLog("Outputting incompany_success", "27b");
            echo 'incompany_success';
            break;
        case 'interest_created':
            debugLog("Outputting interest_success", "27c");
            echo 'interest_success';
            break;
        default:
            debugLog("Outputting default ok", "27d");
            echo 'ok';
    }
    
    debugLog("Output sent, script ending normally", "28");
    
} catch (Exception $e) {
    debugLog("Exception caught: " . $e->getMessage(), "ERROR");
    $pdo->rollback();
    echo 'error: ' . $e->getMessage();
}

debugLog("Script execution completed", "FINAL");
?>