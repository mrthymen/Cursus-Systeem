<?php
/**
 * Quick Diagnostic Processor - Find Exact Problem
 * This will help us identify what's going wrong
 */

// Start with basic error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log everything to find the issue
function logIt($message) {
    $logFile = __DIR__ . '/debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

logIt("=== DIAGNOSTIC PROCESSOR STARTED ===");
logIt("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logIt("ERROR: Not a POST request");
    exit('Method not allowed');
}

logIt("POST data received: " . print_r($_POST, true));

// Check if required fields exist
$required = ['training_type', 'training_name', 'naam', 'email'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        logIt("ERROR: Missing field: $field");
        exit("missing_field_$field");
    }
}

logIt("All required fields present");

// Try to include config
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php', 
    __DIR__ . '/../../includes/config.php',
    dirname(__DIR__) . '/config.php'
];

$config_found = false;
foreach ($config_paths as $path) {
    logIt("Trying config path: $path");
    if (file_exists($path)) {
        logIt("Config found at: $path");
        require_once $path;
        $config_found = true;
        break;
    }
}

if (!$config_found) {
    logIt("ERROR: Config file not found in any location");
    exit('config_not_found');
}

logIt("Config loaded successfully");

// Try database connection
try {
    logIt("Attempting database connection");
    
    // Try different database connection methods
    if (function_exists('getDatabase')) {
        logIt("Using getDatabase() function");
        $pdo = getDatabase();
    } else {
        logIt("getDatabase() not found, trying direct PDO");
        // Fallback to direct connection
        $pdo = new PDO("mysql:host=localhost;dbname=your_db", "username", "password");
    }
    
    logIt("Database connected successfully");
} catch (Exception $e) {
    logIt("ERROR: Database connection failed: " . $e->getMessage());
    exit('database_error');
}

// Test if users table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        logIt("Users table exists");
    } else {
        logIt("ERROR: Users table does not exist");
        exit('users_table_missing');
    }
} catch (Exception $e) {
    logIt("ERROR: Cannot check users table: " . $e->getMessage());
    exit('table_check_error');
}

// Get the form data
$training_type = trim($_POST['training_type']);
$training_name = trim($_POST['training_name']);
$naam = trim($_POST['naam']);
$email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);

logIt("Processing: $training_type - $training_name for $naam ($email)");

if (!$email) {
    logIt("ERROR: Invalid email: " . $_POST['email']);
    exit('invalid_email');
}

// Try to create/get user
try {
    logIt("Checking if user exists");
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_user) {
        logIt("User exists with ID: " . $existing_user['id']);
        $user_id = $existing_user['id'];
    } else {
        logIt("Creating new user");
        $stmt = $pdo->prepare("INSERT INTO users (name, email, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$naam, $email]);
        $user_id = $pdo->lastInsertId();
        logIt("Created new user with ID: $user_id");
    }
} catch (Exception $e) {
    logIt("ERROR: User creation/retrieval failed: " . $e->getMessage());
    exit('user_error');
}

// Check if this is a direct course enrollment
$selected_course_id = $_POST['selected_course_id'] ?? null;

if (!empty($selected_course_id) && $selected_course_id !== 'other' && is_numeric($selected_course_id)) {
    logIt("Processing direct enrollment for course: $selected_course_id");
    
    try {
        // Check if course exists and get info
        $stmt = $pdo->prepare("
            SELECT c.*, COUNT(cp.id) as current_participants
            FROM courses c
            LEFT JOIN course_participants cp ON c.id = cp.course_id AND cp.payment_status != 'cancelled'
            WHERE c.id = ? AND c.active = 1
            GROUP BY c.id
        ");
        $stmt->execute([$selected_course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            logIt("ERROR: Course not found or inactive: $selected_course_id");
            exit('course_not_found');
        }
        
        logIt("Course found: " . $course['name'] . " (participants: {$course['current_participants']}/{$course['max_participants']})");
        
        // Check if user already enrolled
        $stmt = $pdo->prepare("SELECT id FROM course_participants WHERE user_id = ? AND course_id = ? AND payment_status != 'cancelled'");
        $stmt->execute([$user_id, $selected_course_id]);
        
        if ($stmt->fetch()) {
            logIt("User already enrolled");
            echo 'already_enrolled';
            exit;
        }
        
        // Check capacity
        if ($course['current_participants'] >= $course['max_participants']) {
            logIt("Course is full, creating interest instead");
            // Create interest instead
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO interests (naam, email, training_type, training_name, opmerkingen, source, created_at)
                    VALUES (?, ?, ?, ?, 'Auto-added to waitlist - course was full', 'universal_form', NOW())
                ");
                $stmt->execute([$naam, $email, $training_type, $training_name]);
                logIt("Added to waitlist successfully");
                echo 'waitlist_success';
                exit;
            } catch (Exception $e) {
                logIt("ERROR: Failed to add to waitlist: " . $e->getMessage());
                exit('waitlist_error');
            }
        }
        
        // Enroll user
        $stmt = $pdo->prepare("
            INSERT INTO course_participants (user_id, course_id, enrollment_date, payment_status, source)
            VALUES (?, ?, NOW(), 'pending', 'universal_form')
        ");
        $stmt->execute([$user_id, $selected_course_id]);
        $enrollment_id = $pdo->lastInsertId();
        
        logIt("User enrolled successfully with enrollment ID: $enrollment_id");
        
        // Set session for payment
        session_start();
        $_SESSION['pending_enrollment_id'] = $enrollment_id;
        $_SESSION['pending_course_id'] = $selected_course_id;
        $_SESSION['enrollment_user_id'] = $user_id;
        $_SESSION['pending_payment_amount'] = $course['price'];
        
        logIt("Session variables set for payment");
        echo 'enrolled_payment_required';
        exit;
        
    } catch (Exception $e) {
        logIt("ERROR: Enrollment process failed: " . $e->getMessage());
        exit('enrollment_error');
    }
} else {
    logIt("Processing interest registration");
    
    try {
        // Create interest record
        $periode = $_POST['periode'] ?? [];
        $periode_text = is_array($periode) ? implode(', ', $periode) : '';
        $opmerkingen = trim($_POST['opmerkingen'] ?? '');
        
        $stmt = $pdo->prepare("
            INSERT INTO interests (naam, email, telefoon, organisatie, training_type, training_name, periode_voorkeur, opmerkingen, source, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'universal_form', NOW())
        ");
        
        $stmt->execute([
            $naam,
            $email,
            trim($_POST['telefoon'] ?? ''),
            trim($_POST['organisatie'] ?? ''),
            $training_type,
            $training_name,
            $periode_text,
            $opmerkingen
        ]);
        
        $interest_id = $pdo->lastInsertId();
        logIt("Interest created successfully with ID: $interest_id");
        echo 'interest_success';
        exit;
        
    } catch (Exception $e) {
        logIt("ERROR: Interest creation failed: " . $e->getMessage());
        exit('interest_error');
    }
}

logIt("=== PROCESSOR COMPLETED ===");
?>
